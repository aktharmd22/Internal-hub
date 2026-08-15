<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pings an external dead-man's-switch after a successful scheduled run.
 *
 * This application cannot alert anyone that it has stopped running — that is
 * the one failure it is powerless against, and the exact failure it exists to
 * prevent for its clients. An outside service noticing the missing ping is the
 * only thing that catches a dead cron.
 */
class Healthcheck
{
    public function ping(string $suffix = ''): bool
    {
        $url = Setting::get('healthcheck_url', config('services.healthcheck.url'));

        if (blank($url)) {
            return false;
        }

        try {
            Http::timeout(10)->get(rtrim($url, '/').$suffix);

            return true;
        } catch (\Throwable $e) {
            // A failed ping must never fail the run that produced it.
            Log::warning('Healthcheck ping failed.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function fail(string $reason): void
    {
        $this->ping('/fail');

        Log::error('Scheduled run reported failure to healthcheck.', ['reason' => $reason]);
    }
}
