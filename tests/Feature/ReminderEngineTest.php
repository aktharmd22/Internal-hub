<?php

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\RecipientScope;
use App\Enums\ReminderChannel;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AssetExpiring;
use App\Services\Reminders\ReminderEngine;
use App\Support\Channels;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Notification::fake();
    Http::fake();

    $this->owner = User::factory()->employee()->create(['name' => 'Divya Nair']);
    $this->admin = User::factory()->admin()->create(['name' => 'Aarthi Ramesh']);

    $this->engine = app(ReminderEngine::class);
});

function ruleFor(int $days, RecipientScope $scope = RecipientScope::Owner, array $channels = ['mail', 'database']): ReminderRule
{
    return ReminderRule::factory()->create([
        'days_before' => $days,
        'recipient_scope' => $scope,
        'channels' => $channels,
    ]);
}

function assetExpiringIn(int $days, array $attributes = []): Asset
{
    return Asset::factory()->domain()->create(array_merge([
        'client_id' => Client::factory(),
        'expires_at' => now()->addDays($days)->startOfDay(),
    ], $attributes));
}

test('a reminder goes out on a day that matches a rule', function () {
    ruleFor(10);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $summary = $this->engine->run();

    expect($summary->remindersSent)->toBe(2); // mail + database

    Notification::assertSentTo($this->owner, AssetExpiring::class);
    expect(ReminderLog::count())->toBe(2);
});

test('no reminder goes out on a day no rule covers', function () {
    ruleFor(10);
    assetExpiringIn(9, ['owner_id' => $this->owner->id]);

    $summary = $this->engine->run();

    expect($summary->remindersSent)->toBe(0);
    Notification::assertNothingSent();
});

/*
 * The guarantee the whole system rests on. This is the test that would catch a
 * duplicate 3 a.m. email after a retried queue job or a double-fired scheduler.
 */
test('running five times in one day sends exactly one set of notifications', function () {
    ruleFor(10);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    foreach (range(1, 5) as $_) {
        $this->engine->run();
    }

    expect(ReminderLog::count())->toBe(2);

    Notification::assertSentToTimes($this->owner, AssetExpiring::class, 2);
});

test('the unique index is the real guard, not the application check', function () {
    $rule = ruleFor(10);
    $asset = assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $row = [
        'asset_id' => $asset->id,
        'reminder_rule_id' => $rule->id,
        'days_before' => 10,
        'channel' => 'mail',
        'recipient_type' => 'user',
        'recipient_id' => $this->owner->id,
        'sent_at' => now(),
        'status' => 'sent',
    ];

    ReminderLog::create($row);

    expect(fn () => ReminderLog::create($row))
        ->toThrow(QueryException::class);
});

test('a duplicate log row is counted as skipped, not as a failure', function () {
    ruleFor(10);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->engine->run();
    $second = $this->engine->run();

    expect($second->remindersSkipped)->toBe(2)
        ->and($second->remindersSent)->toBe(0)
        ->and($second->failures)->toBe(0);
});

test('archived, muted, renewed and cancelled assets are skipped', function () {
    ruleFor(10);

    assetExpiringIn(10, ['owner_id' => $this->owner->id, 'is_archived' => true]);
    assetExpiringIn(10, ['owner_id' => $this->owner->id, 'reminders_enabled' => false]);
    assetExpiringIn(10, ['owner_id' => $this->owner->id, 'status' => AssetStatus::Renewed]);
    assetExpiringIn(10, ['owner_id' => $this->owner->id, 'status' => AssetStatus::Cancelled]);

    $summary = $this->engine->run();

    expect($summary->assetsScanned)->toBe(0)
        ->and($summary->remindersSent)->toBe(0);

    Notification::assertNothingSent();
});

test('overdue assets still get chased, and escalate to the admins', function () {
    ruleFor(-3);
    assetExpiringIn(-3, ['owner_id' => $this->owner->id]);

    $summary = $this->engine->run();

    // Owner and admin, two channels each. Past the expiry date the owner
    // alone has already demonstrably not been enough.
    expect($summary->remindersSent)->toBe(4);

    Notification::assertSentTo($this->owner, AssetExpiring::class);
    Notification::assertSentTo($this->admin, AssetExpiring::class);
});

test('at three days out the admins are pulled in as well as the owner', function () {
    ruleFor(3);
    assetExpiringIn(3, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    Notification::assertSentTo($this->owner, AssetExpiring::class);
    Notification::assertSentTo($this->admin, AssetExpiring::class);
});

test('further out, only the owner hears about it', function () {
    ruleFor(30);
    assetExpiringIn(30, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    Notification::assertSentTo($this->owner, AssetExpiring::class);
    Notification::assertNotSentTo($this->admin, AssetExpiring::class);
});

test('an owner who is also an admin is not notified twice', function () {
    ruleFor(2);
    assetExpiringIn(2, ['owner_id' => $this->admin->id]);

    $this->engine->run();

    Notification::assertSentToTimes($this->admin, AssetExpiring::class, 2); // mail + database, once each
    expect(ReminderLog::count())->toBe(2);
});

test('the asset status is brought in line with its date', function () {
    $expiring = assetExpiringIn(5, ['status' => AssetStatus::Active]);
    $expired = assetExpiringIn(-2, ['status' => AssetStatus::Active]);

    $this->engine->run();

    expect($expiring->fresh()->status)->toBe(AssetStatus::Expiring)
        ->and($expired->fresh()->status)->toBe(AssetStatus::Expired);
});

/*
 * A reminder can be ignored. A task cannot: it has an owner, a due date and a
 * status somebody has to move.
 */
test('a renewal task is raised ten days out', function () {
    $asset = assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    $task = Task::query()->where('asset_id', $asset->id)->first();

    expect($task)->not->toBeNull()
        ->and($task->assigned_to)->toBe($this->owner->id)
        ->and($task->source)->toBe(TaskSource::Renewal)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->due_at->toDateString())->toBe($asset->expires_at->copy()->subDay()->toDateString());
});

test('the renewal task is raised once, however many times the engine runs', function () {
    $asset = assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->engine->run();
    $this->engine->run();
    $this->engine->run();

    expect(Task::query()->where('asset_id', $asset->id)->count())->toBe(1);
});

test('no task is raised on any other day', function () {
    assetExpiringIn(9, ['owner_id' => $this->owner->id]);
    assetExpiringIn(11, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    expect(Task::count())->toBe(0);
});

test('a client only hears about it when they have opted in', function () {
    ruleFor(15, RecipientScope::Client, ['mail']);

    $optedIn = Client::factory()->billable()->create(['email' => 'billing@kanchisilks.test']);
    $optedOut = Client::factory()->create(['email' => 'other@example.test']);

    assetExpiringIn(15, ['client_id' => $optedIn->id]);
    assetExpiringIn(15, ['client_id' => $optedOut->id]);

    $this->engine->run();

    Notification::assertSentTo($optedIn, AssetExpiring::class);
    Notification::assertNotSentTo($optedOut, AssetExpiring::class);
});

test('a type-specific rule replaces the global one rather than adding to it', function () {
    ruleFor(10);

    ReminderRule::factory()->create([
        'asset_type' => AssetType::Domain,
        'days_before' => 10,
        'recipient_scope' => RecipientScope::Owner,
        'channels' => ['mail'],
    ]);

    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $summary = $this->engine->run();

    // The domain rule wins outright: one channel, not three.
    expect($summary->remindersSent)->toBe(1)
        ->and(ReminderLog::where('channel', 'mail')->count())->toBe(1)
        ->and(ReminderLog::where('channel', 'database')->count())->toBe(0);
});

/*
 * BROADCAST_CONNECTION=null parses to PHP null, not the string "null". A naive
 * comparison reports the channel as ready on a system with no broadcaster, logs
 * a send that never happened, and the unique index then suppresses the real one
 * for good once Pusher is switched on.
 */
test('broadcasting is unavailable until a driver and its key are both set', function () {
    config()->set('broadcasting.default', null);
    expect(Channels::broadcastReady())->toBeFalse();

    config()->set('broadcasting.default', 'null');
    expect(Channels::broadcastReady())->toBeFalse();

    config()->set('broadcasting.default', 'log');
    expect(Channels::broadcastReady())->toBeFalse();

    // A driver with no credentials is not a working broadcaster either.
    config()->set('broadcasting.default', 'pusher');
    config()->set('broadcasting.connections.pusher.key', null);
    expect(Channels::broadcastReady())->toBeFalse();

    config()->set('broadcasting.connections.pusher.key', 'a-real-key');
    expect(Channels::broadcastReady())->toBeTrue();
});

test('no broadcast reminder is logged when there is no broadcaster', function () {
    config()->set('broadcasting.default', null);

    ruleFor(10, RecipientScope::Owner, ['mail', 'broadcast']);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    expect(ReminderLog::where('channel', 'broadcast')->count())->toBe(0)
        ->and(ReminderLog::where('channel', 'mail')->count())->toBe(1);
});

test('channels with no credentials are skipped rather than logged as sent', function () {
    ruleFor(10, RecipientScope::Owner, ['mail', ReminderChannel::WhatsApp->value]);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    expect(ReminderLog::where('channel', 'whatsapp')->count())->toBe(0)
        ->and(ReminderLog::where('channel', 'mail')->count())->toBe(1);
});

test('the healthcheck is pinged at the end of a clean run', function () {
    config()->set('services.healthcheck.url', 'https://hc-ping.test/abc');

    ruleFor(10);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->engine->run();

    Http::assertSent(fn ($request) => $request->url() === 'https://hc-ping.test/abc');
});

test('an asset with no owner falls back to the account manager', function () {
    ruleFor(10);

    $manager = User::factory()->manager()->create();
    $client = Client::factory()->create(['account_manager_id' => $manager->id]);

    assetExpiringIn(10, ['client_id' => $client->id, 'owner_id' => null]);

    $this->engine->run();

    Notification::assertSentTo($manager, AssetExpiring::class);
});

test('assets outside the scan window are never touched', function () {
    ruleFor(60);
    assetExpiringIn(60, ['owner_id' => $this->owner->id]);

    $summary = $this->engine->run();

    expect($summary->assetsScanned)->toBe(0);
});

test('the command is idempotent when run repeatedly', function () {
    ruleFor(10);
    assetExpiringIn(10, ['owner_id' => $this->owner->id]);

    $this->artisan('assets:send-reminders')->assertSuccessful();
    $this->artisan('assets:send-reminders')->assertSuccessful();

    expect(ReminderLog::count())->toBe(2);
});

test('the command can be run for a date other than today', function () {
    ruleFor(10);
    assetExpiringIn(20, ['owner_id' => $this->owner->id]);

    $this->artisan('assets:send-reminders', ['--date' => now()->addDays(10)->toDateString()])
        ->assertSuccessful();

    expect(ReminderLog::count())->toBe(2);
});
