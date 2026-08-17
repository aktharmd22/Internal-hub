<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Everything here runs in Asia/Kolkata. `withoutOverlapping` matters because a
| slow run must never be joined by the next one, and `onOneServer` because the
| reminder engine is the one job that must not run twice — though the unique
| index on reminder_logs would catch it even if it did.
|
| Driven by a single cron entry on the host:
|     * * * * * php /path/to/artisan schedule:run
|
| Nothing here uses runInBackground(). That relies on proc_open, which shared
| hosting routinely disables — Hostinger disables exec() and symlink() too. Every
| command below finishes in seconds, so running them inline costs nothing and
| removes a dependency that would fail silently at 4am.
|
*/

Schedule::command('assets:send-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('assets:verify-expiry')
    ->dailyAt('04:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('reports:daily-digest')
    ->dailyAt('09:15')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reports:weekly-digest')
    ->weeklyOn(1, '09:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('tasks:run-recurring')
    ->dailyAt('06:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->onOneServer();

// Shared hosting has no Supervisor, so failed jobs are swept up here rather
// than by a monitoring daemon.
Schedule::command('queue:monitor-failures')
    ->hourly()
    ->timezone('Asia/Kolkata')
    ->onOneServer();

/*
 * Backups shell out to mysqldump, which needs proc_open. Hostinger disables
 * that along with exec() and symlink(), and scheduling a job that cannot run
 * only produces a nightly failure and a misleading "backups configured" entry
 * on the settings screen. Scheduled only where it can actually work.
 */
if (function_exists('proc_open')) {
    Schedule::command('backup:clean')->dailyAt('01:00')->timezone('Asia/Kolkata')->onOneServer();
    Schedule::command('backup:run')->dailyAt('01:30')->timezone('Asia/Kolkata')->onOneServer();
}
