<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CloudflareProxies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes the trusted proxy list from Cloudflare.
 *
 * The bundled list is accurate at the time of writing and rarely changes, but
 * "rarely" is not "never" — and a range Cloudflare adds later would arrive as
 * an untrusted proxy, silently putting edge IPs back into the audit log.
 */
class RefreshCloudflareIps extends Command
{
    protected $signature = 'cloudflare:ips {--show : Print the current list and change nothing}';

    protected $description = 'Refresh the trusted Cloudflare proxy ranges';

    public function handle(): int
    {
        if ($this->option('show')) {
            foreach (CloudflareProxies::ranges() as $range) {
                $this->line("  {$range}");
            }

            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(15)->acceptJson()->get('https://api.cloudflare.com/client/v4/ips');
        } catch (\Throwable $e) {
            $this->error("Could not reach Cloudflare: {$e->getMessage()}");
            $this->line('The bundled list stays in use, so nothing is broken.');

            return self::FAILURE;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $this->error('Cloudflare returned an unusable response. The bundled list stays in use.');

            return self::FAILURE;
        }

        $ranges = array_merge(
            $response->json('result.ipv4_cidrs') ?? [],
            $response->json('result.ipv6_cidrs') ?? [],
        );

        // A truncated response must not shrink the trusted list to nothing.
        if (count($ranges) < CloudflareProxies::minimum()) {
            $this->error('That response looked too short to be the real list. Keeping the bundled ranges.');

            return self::FAILURE;
        }

        CloudflareProxies::store($ranges);

        $this->info(count($ranges).' ranges stored. Run `php artisan config:cache` if config is cached.');

        return self::SUCCESS;
    }
}
