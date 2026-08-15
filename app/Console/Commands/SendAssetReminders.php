<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\ReminderEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendAssetReminders extends Command
{
    protected $signature = 'assets:send-reminders
                            {--date= : Run as if today were this date (Y-m-d), for testing}
                            {--no-ping : Skip the healthcheck ping}';

    protected $description = 'Send expiry reminders for every asset inside a reminder window';

    public function handle(ReminderEngine $engine): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'), config('app.timezone'))->startOfDay()
            : null;

        $summary = $engine->run($today, ping: ! $this->option('no-ping'));

        $this->table(
            ['Metric', 'Count'],
            collect($summary->toArray())->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])->values()->all(),
        );

        foreach ($summary->errors as $error) {
            $this->error($error);
        }

        Log::info('Reminder run finished.', $summary->toArray());

        // A non-zero exit tells cron, and anything watching it, that the run
        // was not clean.
        return $summary->failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
