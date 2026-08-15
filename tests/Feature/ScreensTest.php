<?php

use App\Enums\TaskStatus;
use App\Livewire;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire as LW;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->employee = User::factory()->employee()->create();
});

test('every screen an admin can reach renders', function (string $route) {
    Asset::factory()->count(3)->create();
    Task::factory()->count(3)->create(['assigned_to' => $this->employee->id]);
    Project::factory()->create();

    $this->actingAs($this->admin)->get(route($route))->assertOk();
})->with([
    'dashboard', 'assets.index', 'assets.import', 'clients.index', 'projects.index',
    'tasks.index', 'chat.index', 'notifications.index', 'team.index',
    'reports.index', 'vault.index', 'settings.index', 'more', 'profile', 'kitchen-sink',
]);

test('detail screens render', function () {
    $client = Client::factory()->create();
    $asset = Asset::factory()->create(['client_id' => $client->id]);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $task = Task::factory()->create(['client_id' => $client->id, 'assigned_to' => $this->employee->id]);

    $this->actingAs($this->admin)->get(route('assets.show', $asset))->assertOk()->assertSee($asset->name);
    $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->assertSee($client->displayName());
    $this->actingAs($this->admin)->get(route('projects.show', $project))->assertOk()->assertSee($project->name);
    $this->actingAs($this->admin)->get(route('tasks.show', $task))->assertOk()->assertSee($task->reference);
});

/*
 * Model::preventLazyLoading is on outside production, so any N+1 on a list
 * screen fails the request outright rather than quietly costing 200 queries.
 * These runs prove the eager loads are actually in place.
 */
test('list screens do not fire a query per row', function () {
    $client = Client::factory()->create();
    Asset::factory()->count(20)->create(['client_id' => $client->id, 'owner_id' => $this->employee->id]);
    Task::factory()->count(20)->create(['client_id' => $client->id, 'assigned_to' => $this->employee->id]);

    foreach (['assets.index', 'tasks.index', 'clients.index'] as $route) {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get(route($route))->assertOk();

        $count = count(DB::getQueryLog());

        // Comfortably above what these pages need, far below one per row.
        expect($count)->toBeLessThan(35, "{$route} ran {$count} queries for 20 rows");

        DB::disableQueryLog();
    }
});

test('the asset list filters by window', function () {
    Asset::factory()->create(['name' => 'due-this-week.test', 'expires_at' => now()->addDays(3)]);
    Asset::factory()->create(['name' => 'due-much-later.test', 'expires_at' => now()->addDays(90)]);

    LW::actingAs($this->admin)
        ->test(Livewire\Assets\Index::class)
        ->set('window', '7')
        ->assertSee('due-this-week.test')
        ->assertDontSee('due-much-later.test');
});

test('renewing from the list rolls the date forward and clears the old cycle', function () {
    $asset = Asset::factory()->create([
        'expires_at' => now()->addDays(2),
        'billing_cycle' => 'yearly',
    ]);

    $asset->reminderLogs()->create([
        'days_before' => 2,
        'channel' => 'mail',
        'recipient_type' => 'user',
        'recipient_id' => $this->admin->id,
        'sent_at' => now(),
        'status' => 'sent',
    ]);

    LW::actingAs($this->admin)
        ->test(Livewire\Assets\Index::class)
        ->call('renew', $asset->id);

    $asset->refresh();

    expect($asset->expires_at->toDateString())->toBe(now()->addDays(2)->addYear()->toDateString())
        // Without this the next cycle's reminders would be suppressed by the
        // rows the last cycle left behind.
        ->and($asset->reminderLogs()->count())->toBe(0);
});

test('the dashboard shows what is expiring and what is mine', function () {
    Asset::factory()->create(['name' => 'urgent-renewal.test', 'expires_at' => now()->addDays(2)]);
    Task::factory()->create(['title' => 'A job for today', 'assigned_to' => $this->admin->id, 'due_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('urgent-renewal.test')
        ->assertSee('A job for today');
});

test('an employee dashboard hides the asset metrics entirely', function () {
    Asset::factory()->count(5)->create(['expires_at' => now()->addDays(3)]);

    $this->actingAs($this->employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Expiring in 7 days');
});

test('the command palette only surfaces what the user may open', function () {
    Client::factory()->create(['company_name' => 'Kanchi Silks']);
    Asset::factory()->create(['name' => 'kanchisilks.test']);
    Task::factory()->create(['title' => 'Kanchi banner update', 'assigned_to' => $this->employee->id]);

    LW::actingAs($this->admin)
        ->test(Livewire\CommandPalette::class)
        ->set('query', 'kanchi')
        ->assertSee('Kanchi Silks')
        ->assertSee('kanchisilks.test');

    LW::actingAs($this->employee)
        ->test(Livewire\CommandPalette::class)
        ->set('query', 'kanchi')
        ->assertSee('Kanchi banner update')
        ->assertDontSee('kanchisilks.test');
});

test('the board refuses a drop that needs a reason', function () {
    $task = Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'status' => TaskStatus::InProgress,
    ]);

    LW::actingAs($this->admin)
        ->test(Livewire\Tasks\Index::class)
        ->call('moveTo', $task->id, TaskStatus::Blocked->value);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('a task moves between board columns', function () {
    $task = Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'status' => TaskStatus::Assigned,
    ]);

    LW::actingAs($this->admin)
        ->test(Livewire\Tasks\Index::class)
        ->call('moveTo', $task->id, TaskStatus::InProgress->value);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('a chat message posts and lands in the thread', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);

    LW::actingAs($this->employee)
        ->test(Livewire\Tasks\Chat::class, ['task' => $task])
        ->set('body', 'Starting on this now.')
        ->call('send')
        ->assertHasNoErrors();

    expect($task->messages()->where('body', 'Starting on this now.')->exists())->toBeTrue();
});

test('a mention pulls somebody onto the thread as a watcher', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);
    $colleague = User::factory()->employee()->create(['name' => 'Meera']);

    LW::actingAs($this->employee)
        ->test(Livewire\Tasks\Chat::class, ['task' => $task])
        ->set('body', '@Meera can you check the staging build?')
        ->call('send');

    expect($task->participants()->whereKey($colleague->id)->exists())->toBeTrue();
});

test('a message can only be edited inside the fifteen minute window', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);

    $fresh = $task->messages()->create(['user_id' => $this->employee->id, 'body' => 'just said', 'type' => 'text']);
    $old = $task->messages()->create(['user_id' => $this->employee->id, 'body' => 'said long ago', 'type' => 'text']);
    $old->forceFill(['created_at' => now()->subHour()])->save();

    expect($fresh->canBeEditedBy($this->employee))->toBeTrue()
        ->and($old->fresh()->canBeEditedBy($this->employee))->toBeFalse();
});

test('a deleted message leaves a tombstone rather than vanishing', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);
    $message = $task->messages()->create(['user_id' => $this->employee->id, 'body' => 'oops', 'type' => 'text']);

    LW::actingAs($this->employee)
        ->test(Livewire\Tasks\Chat::class, ['task' => $task])
        ->call('deleteMessage', $message->id);

    expect($message->fresh()->trashed())->toBeTrue()
        ->and($task->messages()->withTrashed()->count())->toBe(1);
});
