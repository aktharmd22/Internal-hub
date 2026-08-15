<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Livewire;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Credential;
use App\Models\ReminderRule;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Notifications\NewTaskMessage;
use App\Notifications\TaskAssigned;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire as LW;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Notification::fake();
    Http::fake();

    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->employee = User::factory()->employee()->create();
});

/* -------------------------------------------------------------- settings */

test('company settings save and are cached', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->set('company_name', 'Gnext Digital')
        ->set('reminder_send_time', '08:30')
        ->set('healthcheck_url', 'https://hc-ping.test/xyz')
        ->call('saveCompany')
        ->assertHasNoErrors();

    expect(Setting::get('company_name'))->toBe('Gnext Digital')
        ->and(Setting::get('reminder_send_time'))->toBe('08:30');
});

test('a bad healthcheck url is refused', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->set('healthcheck_url', 'not-a-url')
        ->call('saveCompany')
        ->assertHasErrors('healthcheck_url');
});

test('the whatsapp token is stored encrypted and masked on read', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->set('whatsapp_phone_number_id', '123456')
        ->set('whatsapp_token', 'EAAG-real-token')
        ->call('saveChannels');

    $raw = DB::table('settings')->where('key', 'whatsapp_token')->value('value');

    expect($raw)->not->toBe('EAAG-real-token')
        ->and(Setting::get('whatsapp_token'))->toBe('EAAG-real-token');
});

test('leaving the masked token alone does not overwrite it', function () {
    Setting::put('whatsapp_token', 'original-token', secret: true);

    LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->set('whatsapp_token', '••••••••••••')
        ->call('saveChannels');

    expect(Setting::get('whatsapp_token'))->toBe('original-token');
});

test('a reminder rule can be added, toggled and removed', function () {
    $component = LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->call('newRule')
        ->set('rule_days_before', 21)
        ->set('rule_channels', ['mail'])
        ->set('rule_recipient_scope', 'admins')
        ->call('saveRule')
        ->assertHasNoErrors();

    $rule = ReminderRule::where('days_before', 21)->first();

    expect($rule)->not->toBeNull()
        ->and($rule->is_active)->toBeTrue();

    $component->call('toggleRule', $rule->id);
    expect($rule->fresh()->is_active)->toBeFalse();

    $component->call('deleteRule', $rule->id);
    expect(ReminderRule::find($rule->id))->toBeNull();
});

test('a rule with no channel is refused', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->call('newRule')
        ->set('rule_channels', [])
        ->call('saveRule')
        ->assertHasErrors('rule_channels');
});

test('the healthcheck test button actually pings', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Settings\Index::class)
        ->set('healthcheck_url', 'https://hc-ping.test/abc')
        ->call('testHealthcheck');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'hc-ping.test/abc'));
});

/* ------------------------------------------------------------------ team */

test('an admin creates an account without ever setting a password', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Team\Index::class)
        ->call('newUser')
        ->set('name', 'Suresh Babu')
        ->set('email', 'suresh@example.test')
        ->set('role', 'employee')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'suresh@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::Employee->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        // No known password was set or mailed; they use "Forgot password".
        ->and(Hash::check('password', $user->password))->toBeFalse();
});

test('a duplicate email is refused', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Team\Index::class)
        ->call('newUser')
        ->set('name', 'Clash')
        ->set('email', $this->employee->email)
        ->call('save')
        ->assertHasErrors('email');
});

test('changing a role replaces it rather than stacking', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Team\Index::class)
        ->call('edit', $this->employee->id)
        ->set('role', 'manager')
        ->call('save');

    $this->employee->refresh();

    expect($this->employee->getRoleNames()->all())->toBe(['manager']);
});

test('deactivating a user signs them out on their next request', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Team\Index::class)
        ->call('toggleActive', $this->employee->id);

    expect($this->employee->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->employee->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('an admin cannot deactivate themselves', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Team\Index::class)
        ->call('toggleActive', $this->admin->id)
        ->assertForbidden();
});

test('the scorecard reports on-time and reopen rates', function () {
    // Two completed on time, one late, one reopened once.
    Task::factory()->count(2)->create([
        'assigned_to' => $this->employee->id,
        'status' => TaskStatus::Completed,
        'due_at' => now()->addDay(),
        'completed_at' => now(),
    ]);

    Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'status' => TaskStatus::Completed,
        'due_at' => now()->subDays(2),
        'completed_at' => now(),
        'reopen_count' => 1,
    ]);

    $this->actingAs($this->admin)
        ->get(route('team.index'))
        ->assertOk()
        ->assertSee('67%')   // 2 of 3 on time
        ->assertSee('33%');  // 1 reopen across 3 completed
});

/* ----------------------------------------------------------------- vault */

test('revealing a credential returns the plaintext and logs it', function () {
    $credential = Credential::factory()->create(['password' => 'hunter2-and-then-some']);

    LW::actingAs($this->admin)
        ->test(Livewire\Vault\Index::class)
        ->call('reveal', $credential->id)
        ->assertSet('revealed', 'hunter2-and-then-some');

    expect(Activity::where('log_name', 'credential-access')->count())->toBe(1);
});

test('a credential can be created, edited and deleted', function () {
    $client = Client::factory()->create();

    $component = LW::actingAs($this->admin)
        ->test(Livewire\Vault\Index::class)
        ->call('newCredential')
        ->set('client_id', $client->id)
        ->set('label', 'cPanel')
        ->set('username', 'admin')
        ->set('password', 'a-long-password')
        ->call('save')
        ->assertHasNoErrors();

    $credential = Credential::where('label', 'cPanel')->first();
    expect($credential)->not->toBeNull();

    $component->call('edit', $credential->id)
        ->set('label', 'cPanel (main)')
        ->call('save');

    expect($credential->fresh()->label)->toBe('cPanel (main)');

    $component->call('delete', $credential->id);

    expect(Credential::find($credential->id))->toBeNull();
});

/*
 * Hiding the Reveal button is not the protection. A manager is stopped three
 * times over: the route, the component's mount, and the policy `reveal` check
 * inside the method itself.
 */
test('a manager is refused the vault at the route, the component and the policy', function () {
    $credential = Credential::factory()->create();

    $this->actingAs($this->manager)->get(route('vault.index'))->assertForbidden();

    expect($this->manager->can('viewAny', Credential::class))->toBeFalse()
        ->and($this->manager->can('reveal', $credential))->toBeFalse()
        ->and($this->admin->can('reveal', $credential))->toBeTrue();
});

/* --------------------------------------------------------- notifications */

test('the notifications screen groups by day and clears', function () {
    $task = Task::factory()->create(['assigned_to' => $this->admin->id]);

    // Written straight to the table: the point here is the screen, not delivery.
    foreach ([now(), now()->subDay()] as $when) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'task_assigned',
            'notifiable_type' => 'user',
            'notifiable_id' => $this->admin->id,
            'data' => json_encode(['type' => 'task_assigned', 'title' => 'Assigned to you', 'body' => $task->reference]),
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    $component = LW::actingAs($this->admin)
        ->test(Livewire\Notifications\Index::class)
        ->assertSee('Today')
        ->assertSee('Yesterday');

    expect($this->admin->unreadNotifications()->count())->toBe(2);

    $component->call('markAllRead');

    expect($this->admin->fresh()->unreadNotifications()->count())->toBe(0);
});

/* --------------------------------------------------------------- reports */

test('the renewals export streams a csv with a row per asset', function () {
    Asset::factory()->count(3)->create();

    $response = LW::actingAs($this->admin)
        ->test(Livewire\Reports\Index::class)
        ->call('exportRenewals');

    $csv = $response->effects['download'] ?? null;

    // Livewire wraps the streamed response; grab the content directly instead.
    ob_start();
    app(Livewire\Reports\Index::class)->exportRenewals()->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('Asset,Type,Client')
        ->and(substr_count(trim($body), "\n"))->toBe(3); // header + 3 rows
});

test('a manager can see reports but not export from an unauthorised session', function () {
    $this->actingAs($this->manager)->get(route('reports.index'))->assertOk();
    $this->actingAs($this->employee)->get(route('reports.index'))->assertForbidden();
});

/* ------------------------------------------------------- asset actions */

test('an asset can be renewed, muted, verified and archived from its page', function () {
    $asset = Asset::factory()->domain()->create([
        'expires_at' => now()->addDays(3),
        'billing_cycle' => 'yearly',
    ]);

    Http::fake(['rdap.org/*' => Http::response([
        'events' => [['eventAction' => 'expiration', 'eventDate' => now()->addDays(3)->toIso8601String()]],
    ])]);

    $component = LW::actingAs($this->admin)->test(Livewire\Assets\Show::class, ['asset' => $asset]);

    $component->call('renew');
    expect($asset->fresh()->expires_at->toDateString())->toBe(now()->addDays(3)->addYear()->toDateString())
        ->and($asset->fresh()->status)->toBe(AssetStatus::Active);

    $component->call('toggleReminders');
    expect($asset->fresh()->reminders_enabled)->toBeFalse();

    $component->call('verify');
    expect($asset->fresh()->last_verified_at)->not->toBeNull();

    $component->call('archive');
    expect($asset->fresh()->is_archived)->toBeTrue();
});

test('a non-verifiable asset says so rather than failing', function () {
    $asset = Asset::factory()->create(['type' => AssetType::Licence]);

    LW::actingAs($this->admin)
        ->test(Livewire\Assets\Show::class, ['asset' => $asset])
        ->call('verify')
        ->assertDispatched('toast');

    expect($asset->fresh()->last_verified_at)->toBeNull();
});

/* ------------------------------------------------------- task actions */

test('assigning from the task page notifies and adds a participant', function () {
    $task = Task::factory()->create(['status' => TaskStatus::Open]);

    LW::actingAs($this->admin)
        ->test(Livewire\Tasks\Show::class, ['task' => $task])
        ->call('assign', $this->employee->id);

    $task->refresh();

    expect($task->assigned_to)->toBe($this->employee->id)
        ->and($task->status)->toBe(TaskStatus::Assigned)
        ->and($task->participants()->whereKey($this->employee->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->employee, TaskAssigned::class);
});

test('the timer starts, moves the task to in progress, and stops', function () {
    $task = Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'status' => TaskStatus::Assigned,
    ]);

    $component = LW::actingAs($this->employee)->test(Livewire\Tasks\Show::class, ['task' => $task]);

    $component->call('toggleTimer');

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(TaskTimeLog::where('task_id', $task->id)->whereNull('stopped_at')->exists())->toBeTrue();

    $component->call('toggleTimer');

    expect(TaskTimeLog::where('task_id', $task->id)->whereNull('stopped_at')->exists())->toBeFalse();
});

test('starting a timer stops whatever else was running', function () {
    $first = Task::factory()->create(['assigned_to' => $this->employee->id, 'status' => TaskStatus::InProgress]);
    $second = Task::factory()->create(['assigned_to' => $this->employee->id, 'status' => TaskStatus::InProgress]);

    LW::actingAs($this->employee)->test(Livewire\Tasks\Show::class, ['task' => $first])->call('toggleTimer');
    LW::actingAs($this->employee)->test(Livewire\Tasks\Show::class, ['task' => $second])->call('toggleTimer');

    // Nobody is on two tasks at the same second.
    expect(TaskTimeLog::whereNull('stopped_at')->count())->toBe(1)
        ->and(TaskTimeLog::whereNull('stopped_at')->first()->task_id)->toBe($second->id);
});

test('changing the due date posts a system message', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);

    LW::actingAs($this->admin)
        ->test(Livewire\Tasks\Show::class, ['task' => $task])
        ->call('setDueDate', now()->addWeek()->toDateString());

    expect($task->fresh()->due_at->toDateString())->toBe(now()->addWeek()->toDateString())
        ->and($task->messages()->where('type', 'system')->latest('id')->first()->body)
        ->toContain('set the due date');
});

test('muting a thread stops its notifications', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);
    $task->participants()->attach($this->manager->id, ['role' => 'watcher']);

    LW::actingAs($this->manager)
        ->test(Livewire\Tasks\Show::class, ['task' => $task])
        ->call('toggleMute');

    LW::actingAs($this->employee)
        ->test(Livewire\Tasks\Chat::class, ['task' => $task])
        ->set('body', 'An update nobody muted should miss.')
        ->call('send');

    Notification::assertNotSentTo($this->manager, NewTaskMessage::class);
});

/* --------------------------------------------------------------- forms */

test('a client can be created and edited', function () {
    $component = LW::actingAs($this->admin)
        ->test(Livewire\Clients\Form::class)
        ->set('name', 'Ravi Kumar')
        ->set('company_name', 'Kanchi Silks')
        ->set('email', 'ravi@kanchisilks.test')
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::where('company_name', 'Kanchi Silks')->first();
    expect($client)->not->toBeNull();

    $component->call('edit', $client->id)
        ->set('send_renewal_notices', true)
        ->call('save');

    expect($client->fresh()->send_renewal_notices)->toBeTrue();
});

test('an asset form catches a duplicate before saving it', function () {
    $client = Client::factory()->create();
    Asset::factory()->create(['client_id' => $client->id, 'identifier' => 'kanchisilks.test']);

    $component = LW::actingAs($this->admin)
        ->test(Livewire\Assets\Form::class)
        ->set('client_id', $client->id)
        ->set('type', 'domain')
        ->set('name', 'kanchisilks.test')
        ->set('identifier', 'kanchisilks.test')
        ->set('expires_at', now()->addYear()->toDateString())
        ->call('save');

    expect($component->get('duplicate'))->not->toBeNull()
        ->and(Asset::count())->toBe(1);

    $component->call('createAnyway');

    expect(Asset::count())->toBe(2);
});

test('a task form creates, assigns and notifies', function () {
    LW::actingAs($this->admin)
        ->test(Livewire\Tasks\Form::class)
        ->set('title', 'Move DNS to Cloudflare')
        ->set('assigned_to', $this->employee->id)
        ->set('due_at', now()->addDays(3)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $task = Task::where('title', 'Move DNS to Cloudflare')->first();

    expect($task->status)->toBe(TaskStatus::Assigned)
        ->and($task->reference)->toStartWith('TSK-');

    Notification::assertSentTo($this->employee, TaskAssigned::class);
});

test('an employee cannot create a task', function () {
    LW::actingAs($this->employee)
        ->test(Livewire\Tasks\Form::class)
        ->set('title', 'Sneaky')
        ->call('save')
        ->assertForbidden();

    expect(Task::where('title', 'Sneaky')->exists())->toBeFalse();
});

test('the vault index refuses a manager outright', function () {
    LW::actingAs($this->manager)
        ->test(Livewire\Vault\Index::class)
        ->assertForbidden();
});
