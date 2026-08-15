<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\FailedJobsDetected;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A reminder that disappears into a silent retry loop is the exact failure
 * this application exists to prevent. With no Horizon on shared hosting, this
 * is what notices.
 */
class MonitorFailedJobs extends Command
{
    protected $signature = 'queue:monitor-failures {--threshold=1 : Alert once this many failures have accrued}';

    protected $description = 'Alert admins when jobs land in the failed queue';

    public function handle(): int
    {
        $failures = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->get(['id', 'queue', 'payload', 'failed_at']);

        if ($failures->count() < (int) $this->option('threshold')) {
            $this->info('No new failed jobs.');

            return self::SUCCESS;
        }

        // One alert per hour at most: a queue that is failing hard would
        // otherwise bury the inbox it is trying to warn.
        $alerted = Cache::get('failed-jobs.alerted-at');

        if ($alerted && now()->diffInMinutes($alerted) < 60) {
            $this->info('Failures present, but an alert already went out within the hour.');

            return self::SUCCESS;
        }

        $summary = $failures
            ->map(fn ($row) => data_get(json_decode($row->payload, true), 'displayName', $row->queue))
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->all();

        User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::Admin->value))
            ->get()
            ->each->notify(new FailedJobsDetected($failures->count(), $summary));

        Cache::put('failed-jobs.alerted-at', now(), now()->addHours(2));

        $this->warn("{$failures->count()} failed job(s) in the last 24 hours. Admins notified.");

        return self::SUCCESS;
    }
}
