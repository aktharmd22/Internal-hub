<?php

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Notifications\FailedJobsDetected;
use App\Notifications\WeeklyRenewalDigest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Notification::fake();
    Http::fake();

    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->employee = User::factory()->employee()->create();
});

/* ------------------------------------------------------------ scheduling */

test('every scheduled command is registered and resolvable', function (string $command) {
    expect(array_key_exists($command, Artisan::all()))->toBeTrue();
})->with([
    'assets:send-reminders',
    'assets:verify-expiry',
    'reports:weekly-digest',
    'reports:daily-digest',
    'tasks:run-recurring',
    'queue:monitor-failures',
]);

test('the schedule runs everything in Asia/Kolkata and never overlaps', function () {
    $events = app(Schedule::class)->events();

    $ours = collect($events)->filter(
        fn ($event) => str_contains($event->command ?? '', 'assets:')
            || str_contains($event->command ?? '', 'reports:')
            || str_contains($event->command ?? '', 'tasks:run-recurring')
    );

    expect($ours)->not->toBeEmpty();

    $ours->each(function ($event) {
        expect($event->timezone)->toBe('Asia/Kolkata')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue();
    });
});

test('the reminder run is scheduled for 09:00', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'assets:send-reminders'));

    expect($event->expression)->toBe('0 9 * * *');
});

/* --------------------------------------------------------- weekly digest */

test('the weekly digest reaches admins and managers but not employees', function () {
    Asset::factory()->count(3)->create(['expires_at' => now()->addDays(20)]);

    $this->artisan('reports:weekly-digest')->assertSuccessful();

    Notification::assertSentTo($this->admin, WeeklyRenewalDigest::class);
    Notification::assertSentTo($this->manager, WeeklyRenewalDigest::class);
    Notification::assertNotSentTo($this->employee, WeeklyRenewalDigest::class);
});

test('the weekly digest groups by week and totals the cost', function () {
    Asset::factory()->create(['expires_at' => now()->addDays(2), 'cost' => 1000]);
    Asset::factory()->create(['expires_at' => now()->addDays(9), 'cost' => 2500]);
    Asset::factory()->create(['expires_at' => now()->subDays(4), 'cost' => 900]);

    $this->artisan('reports:weekly-digest')->assertSuccessful();

    Notification::assertSentTo($this->admin, WeeklyRenewalDigest::class, function (WeeklyRenewalDigest $n) {
        return $n->total === 2
            && (int) $n->cost === 3500
            && $n->overdue === 1;
    });
});

test('the weekly digest still sends when there is nothing due', function () {
    $this->artisan('reports:weekly-digest')->assertSuccessful();

    Notification::assertSentTo($this->admin, WeeklyRenewalDigest::class);
});

/* ---------------------------------------------------------- daily digest */

test('the daily digest goes only to people with something outstanding', function () {
    Task::factory()->dueToday()->create(['assigned_to' => $this->employee->id]);

    $this->artisan('reports:daily-digest')->assertSuccessful();

    Notification::assertSentTo($this->employee, DailyDigest::class);
    // A digest that says "nothing today" every day teaches people to ignore it.
    Notification::assertNotSentTo($this->manager, DailyDigest::class);
});

test('the daily digest carries the right buckets', function () {
    // Due later today, so it belongs under "due today" and nowhere else.
    Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'title' => 'Due later',
        'due_at' => now()->endOfDay()->subMinute(),
    ]);

    Task::factory()->overdue()->create(['assigned_to' => $this->employee->id, 'title' => 'Late']);

    Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'status' => TaskStatus::Completed,
        'completed_at' => now()->subDay()->setTime(14, 0),
    ]);

    $this->artisan('reports:daily-digest')->assertSuccessful();

    Notification::assertSentTo($this->employee, DailyDigest::class, function (DailyDigest $n) {
        return $n->dueToday->pluck('title')->all() === ['Due later']
            && $n->overdue->pluck('title')->all() === ['Late']
            && $n->completedYesterday->count() === 1;
    });
});

/*
 * The same task under two contradictory headings in one email is how people
 * learn to stop reading the digest.
 */
test('a task past its time today is overdue, and is not also listed as due today', function () {
    Task::factory()->create([
        'assigned_to' => $this->employee->id,
        'title' => 'Was due at five',
        'due_at' => now()->startOfDay()->addHours(1),
    ]);

    $this->artisan('reports:daily-digest')->assertSuccessful();

    Notification::assertSentTo($this->employee, DailyDigest::class, function (DailyDigest $n) {
        return $n->overdue->pluck('title')->all() === ['Was due at five']
            && $n->dueToday->isEmpty();
    });
});

test('only approvers get a review queue in their digest', function () {
    Task::factory()->create(['status' => TaskStatus::Submitted, 'assigned_to' => $this->employee->id]);
    Task::factory()->dueToday()->create(['assigned_to' => $this->employee->id]);

    $this->artisan('reports:daily-digest')->assertSuccessful();

    Notification::assertSentTo($this->manager, DailyDigest::class, fn (DailyDigest $n) => $n->awaitingReview->count() === 1);
    Notification::assertSentTo($this->employee, DailyDigest::class, fn (DailyDigest $n) => $n->awaitingReview->isEmpty());
});

test('an employee digest carries no asset list', function () {
    Asset::factory()->create(['expires_at' => now()->addDays(3)]);
    Task::factory()->dueToday()->create(['assigned_to' => $this->employee->id]);

    $this->artisan('reports:daily-digest')->assertSuccessful();

    Notification::assertSentTo($this->employee, DailyDigest::class, fn (DailyDigest $n) => $n->expiringSoon->isEmpty());
});

test('a deactivated user gets no digest', function () {
    $gone = User::factory()->employee()->inactive()->create();
    Task::factory()->dueToday()->create(['assigned_to' => $gone->id]);

    $this->artisan('reports:daily-digest')->assertSuccessful();

    Notification::assertNotSentTo($gone, DailyDigest::class);
});

/* ------------------------------------------------------ recurring tasks */

test('a due recurring template spawns a task and rolls forward', function () {
    $template = RecurringTaskTemplate::factory()->due()->create([
        'title' => 'Monthly WordPress updates',
        'assigned_to' => $this->employee->id,
        'due_in_days' => 7,
    ]);

    $this->artisan('tasks:run-recurring')->assertSuccessful();

    $task = Task::where('title', 'Monthly WordPress updates')->first();

    expect($task)->not->toBeNull()
        ->and($task->source)->toBe(TaskSource::Recurring)
        ->and($task->assigned_to)->toBe($this->employee->id)
        ->and($task->status)->toBe(TaskStatus::Assigned)
        ->and($task->due_at->toDateString())->toBe(now()->addDays(7)->toDateString());

    expect($template->fresh()->next_run_at->isFuture())->toBeTrue()
        ->and($template->fresh()->last_run_at)->not->toBeNull();
});

test('a template that is not due yet spawns nothing', function () {
    RecurringTaskTemplate::factory()->create(['next_run_at' => now()->addWeek()]);

    $this->artisan('tasks:run-recurring')->assertSuccessful();

    expect(Task::count())->toBe(0);
});

test('an inactive template never fires', function () {
    RecurringTaskTemplate::factory()->due()->create(['is_active' => false]);

    $this->artisan('tasks:run-recurring')->assertSuccessful();

    expect(Task::count())->toBe(0);
});

test('running the recurring command twice in a row does not double up', function () {
    RecurringTaskTemplate::factory()->due()->create();

    $this->artisan('tasks:run-recurring')->assertSuccessful();
    $this->artisan('tasks:run-recurring')->assertSuccessful();

    expect(Task::count())->toBe(1);
});

/* -------------------------------------------------------- failed jobs */

test('failed jobs raise an alert to admins', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Notifications\\AssetExpiring']),
        'exception' => 'boom',
        'failed_at' => now(),
    ]);

    $this->artisan('queue:monitor-failures')->assertSuccessful();

    Notification::assertSentTo($this->admin, FailedJobsDetected::class);
    Notification::assertNotSentTo($this->employee, FailedJobsDetected::class);
});

test('a queue failing hard does not bury the inbox', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'Job']),
        'exception' => 'boom',
        'failed_at' => now(),
    ]);

    $this->artisan('queue:monitor-failures')->assertSuccessful();
    $this->artisan('queue:monitor-failures')->assertSuccessful();

    Notification::assertSentToTimes($this->admin, FailedJobsDetected::class, 1);
});

test('a clean queue raises nothing', function () {
    Cache::flush();

    $this->artisan('queue:monitor-failures')->assertSuccessful();

    Notification::assertNothingSent();
});

/*
 * The alert about a broken queue must not itself sit in that queue.
 */
test('the failed-job alert is not queued', function () {
    expect(new FailedJobsDetected(1, []))->not->toBeInstanceOf(ShouldQueue::class);
});
