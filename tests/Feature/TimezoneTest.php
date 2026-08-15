<?php

use App\Models\Asset;
use App\Models\Client;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\Task;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
 * Chennai is UTC+5:30. The reminder run fires at 09:00 IST, which is 03:30 UTC
 * and still the previous calendar day. Anything that compares an expiry date
 * against a UTC clock is off by one every single morning — the exact trap that
 * makes "3 days before" fire on the wrong day.
 */

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Notification::fake();
    Http::fake();

    $this->owner = User::factory()->employee()->create();
    $this->engine = app(ReminderEngine::class);
});

test('the application clock is Asia/Kolkata', function () {
    expect(config('app.timezone'))->toBe('Asia/Kolkata')
        ->and(now()->timezoneName)->toBe('Asia/Kolkata');
});

test('the timezone comes from the environment rather than being hardcoded', function () {
    $config = File::get(base_path('config/app.php'));

    expect($config)->toContain("env('APP_TIMEZONE'")
        ->and($config)->not->toContain("'timezone' => 'UTC'");
});

/*
 * The window that was broken: between midnight and 05:30 IST the UTC date is
 * still yesterday, and 09:00 IST sits right inside it.
 */
test('days remaining is counted from the Indian date, not the UTC date', function () {
    // 09:00 in Chennai on 15 August; 03:30 UTC, still 14 August in UTC.
    Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00', 'Asia/Kolkata'));

    $asset = Asset::factory()->create(['expires_at' => '2026-08-18']);

    expect($asset->daysRemaining())->toBe(3);

    Carbon::setTestNow();
});

test('an asset expiring today reads as zero days, not one', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00', 'Asia/Kolkata'));

    $asset = Asset::factory()->create(['expires_at' => '2026-08-15']);

    expect($asset->daysRemaining())->toBe(0)
        ->and($asset->urgencyLabel())->toBe('Expires today');

    Carbon::setTestNow();
});

test('the three-day rule fires on the right morning across the UTC boundary', function () {
    ReminderRule::factory()->daysBefore(3)->create();

    // 09:00 IST on the 15th. UTC still says the 14th.
    Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00', 'Asia/Kolkata'));

    Asset::factory()->create([
        'expires_at' => '2026-08-18',
        'owner_id' => $this->owner->id,
    ]);

    $summary = $this->engine->run();

    expect($summary->remindersSent)->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('the same asset is silent on the day before the rule matches', function () {
    ReminderRule::factory()->daysBefore(3)->create();

    Carbon::setTestNow(Carbon::parse('2026-08-14 09:00:00', 'Asia/Kolkata'));

    Asset::factory()->create([
        'expires_at' => '2026-08-18',
        'owner_id' => $this->owner->id,
    ]);

    expect($this->engine->run()->remindersSent)->toBe(0);

    Carbon::setTestNow();
});

test('an expiry date is stored and read back as a plain date', function () {
    $asset = Asset::factory()->create(['expires_at' => '2026-08-18']);

    $raw = DB::table('assets')->where('id', $asset->id)->value('expires_at');

    // The column is a DATE. SQLite writes a zero time component and MySQL
    // writes none, but neither carries an offset — which is the point.
    expect($raw)->toStartWith('2026-08-18')
        ->and($raw)->not->toContain('T')
        ->and($asset->fresh()->expires_at->toDateString())->toBe('2026-08-18')
        ->and($asset->fresh()->expires_at->format('H:i:s'))->toBe('00:00:00');
});

test('a renewal task is due the evening before expiry, in Indian time', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00', 'Asia/Kolkata'));

    $asset = Asset::factory()->create([
        'expires_at' => '2026-08-25',
        'owner_id' => $this->owner->id,
    ]);

    $this->engine->run();

    $task = Task::where('asset_id', $asset->id)->first();

    expect($task)->not->toBeNull()
        ->and($task->due_at->toDateString())->toBe('2026-08-24')
        ->and($task->due_at->format('H:i'))->toBe('17:00');

    Carbon::setTestNow();
});

test('running at either end of the Indian day gives the same answer', function () {
    ReminderRule::factory()->daysBefore(5)->create();

    $client = Client::factory()->create();

    // Just after midnight IST — the worst case for a UTC-based clock.
    Carbon::setTestNow(Carbon::parse('2026-08-15 00:15:00', 'Asia/Kolkata'));

    $asset = Asset::factory()->create([
        'client_id' => $client->id,
        'expires_at' => '2026-08-20',
        'owner_id' => $this->owner->id,
    ]);

    $this->engine->run();
    $afterMidnight = ReminderLog::count();

    // And again late the same evening.
    Carbon::setTestNow(Carbon::parse('2026-08-15 23:45:00', 'Asia/Kolkata'));

    $this->engine->run();

    expect($afterMidnight)->toBeGreaterThan(0)
        // The second run is the same day, so the index stops it dead.
        ->and(ReminderLog::count())->toBe($afterMidnight);

    Carbon::setTestNow();
});
