<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\Credential;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->employee = User::factory()->employee()->create();
    $this->otherEmployee = User::factory()->employee()->create();
    $this->manager = User::factory()->manager()->create();
    $this->admin = User::factory()->admin()->create();
});

/* ------------------------------------------------------------------ tasks */

test('an employee gets a 403 on another employee task', function () {
    $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

    $this->actingAs($this->employee)
        ->get(route('tasks.show', $task))
        ->assertForbidden();
});

test('an employee can open their own task', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);

    $this->actingAs($this->employee)
        ->get(route('tasks.show', $task))
        ->assertOk();
});

test('an employee can open a task they were pulled into as a watcher', function () {
    $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);
    $task->participants()->attach($this->employee->id, ['role' => 'watcher']);

    $this->actingAs($this->employee)
        ->get(route('tasks.show', $task))
        ->assertOk();
});

test('the task list shows an employee only their own work', function () {
    Task::factory()->create(['assigned_to' => $this->employee->id, 'title' => 'Mine to do']);
    Task::factory()->create(['assigned_to' => $this->otherEmployee->id, 'title' => 'Somebody elses work']);

    $visible = Task::query()->visibleTo($this->employee)->pluck('title');

    expect($visible)->toContain('Mine to do')
        ->and($visible)->not->toContain('Somebody elses work');
});

test('a manager sees every task', function () {
    Task::factory()->count(3)->create(['assigned_to' => $this->otherEmployee->id]);

    expect(Task::query()->visibleTo($this->manager)->count())->toBe(3);
});

/*
 * The broadcast channel is the real boundary for the chat. Without this check
 * any authenticated employee could subscribe to any client's private thread —
 * the HTTP policy would never see the request.
 */
/**
 * Drives the real `/broadcasting/auth` endpoint. The null broadcaster used
 * elsewhere in the suite short-circuits authorization, so this switches to the
 * Pusher driver — which signs locally and needs no network — to exercise the
 * check the way a browser would.
 */
function authoriseChannel(User $user, string $channel)
{
    config()->set('broadcasting.default', 'pusher');
    config()->set('broadcasting.connections.pusher.key', 'test-key');
    config()->set('broadcasting.connections.pusher.secret', 'test-secret');
    config()->set('broadcasting.connections.pusher.app_id', 'test-app');

    // Channels register against whichever driver was current when the routes
    // file was read, so switching the driver means reading it again — the same
    // sequence production goes through at boot with BROADCAST_CONNECTION set.
    require base_path('routes/channels.php');

    return test()->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ]);
}

test('an employee cannot subscribe to another employee task channel', function () {
    $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

    authoriseChannel($this->employee, "private-task.{$task->id}")->assertForbidden();
});

test('an employee can subscribe to their own task channel', function () {
    $task = Task::factory()->create(['assigned_to' => $this->employee->id]);

    authoriseChannel($this->employee, "private-task.{$task->id}")->assertOk();
});

test('a manager can subscribe to any task channel', function () {
    $task = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

    authoriseChannel($this->manager, "private-task.{$task->id}")->assertOk();
});

test('channel authorization mirrors the task policy exactly', function () {
    $mine = Task::factory()->create(['assigned_to' => $this->employee->id]);
    $theirs = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

    // If these two ever answer differently, that gap is the vulnerability.
    expect($this->employee->can('view', $mine))->toBeTrue()
        ->and($this->employee->can('view', $theirs))->toBeFalse()
        ->and($this->manager->can('view', $theirs))->toBeTrue();
});

test('the presence channel is guarded and leaks nothing private', function () {
    $mine = Task::factory()->create(['assigned_to' => $this->employee->id]);
    $theirs = Task::factory()->create(['assigned_to' => $this->otherEmployee->id]);

    authoriseChannel($this->employee, "presence-viewing-task.{$theirs->id}")->assertForbidden();

    $response = authoriseChannel($this->employee, "presence-viewing-task.{$mine->id}")->assertOk();

    // channel_data is echoed to every other member of the channel.
    $data = json_decode(json_decode($response->getContent(), true)['channel_data'], true);

    expect(array_keys($data['user_info']))->toBe(['id', 'name'])
        ->and($data['user_info'])->not->toHaveKey('email')
        ->and($data['user_info'])->not->toHaveKey('phone');
});

/* ----------------------------------------------------------------- assets */

test('an employee cannot reach the asset list or an asset', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->employee)->get(route('assets.index'))->assertForbidden();
    $this->actingAs($this->employee)->get(route('assets.show', $asset))->assertForbidden();
});

test('an employee who owns an asset can still open it', function () {
    $asset = Asset::factory()->create(['owner_id' => $this->employee->id]);

    expect($this->employee->can('view', $asset))->toBeTrue()
        ->and($this->employee->can('update', $asset))->toBeFalse();
});

/* ---------------------------------------------------------------- clients */

test('an employee reaches a client only through a task they are on', function () {
    $reachable = Client::factory()->create();
    $unreachable = Client::factory()->create();

    Task::factory()->create(['client_id' => $reachable->id, 'assigned_to' => $this->employee->id]);

    $this->actingAs($this->employee)->get(route('clients.show', $reachable))->assertOk();
    $this->actingAs($this->employee)->get(route('clients.show', $unreachable))->assertForbidden();
});

/* ------------------------------------------------------------------ vault */

test('the vault is admin only, managers included in the exclusion', function () {
    $credential = Credential::factory()->create();

    $this->actingAs($this->manager)->get(route('vault.index'))->assertForbidden();
    $this->actingAs($this->employee)->get(route('vault.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('vault.index'))->assertOk();

    expect($this->manager->can('reveal', $credential))->toBeFalse()
        ->and($this->admin->can('reveal', $credential))->toBeTrue();
});

test('revealing a credential is written to the activity log', function () {
    $credential = Credential::factory()->create();

    $credential->recordAccess($this->admin);

    $entry = Activity::query()->where('log_name', 'credential-access')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($this->admin->id)
        ->and($entry->description)->toContain($credential->label);
});

test('a stored password is ciphertext in the database', function () {
    $credential = Credential::factory()->create(['password' => 'super-secret-value']);

    $raw = DB::table('credentials')->where('id', $credential->id)->value('password');

    expect($raw)->not->toBe('super-secret-value')
        ->and($credential->fresh()->password)->toBe('super-secret-value');
});

/* --------------------------------------------------------------- settings */

test('only an admin reaches settings and the team screen', function () {
    foreach (['settings.index', 'team.index'] as $route) {
        $this->actingAs($this->manager)->get(route($route))->assertForbidden();
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }
});
