<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\Healthcheck;
use App\Services\Verification\AssetVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyAssetExpiry extends Command
{
    protected $signature = 'assets:verify-expiry
                            {--asset= : Verify a single asset by id}
                            {--limit=200 : Maximum assets to check in one run}';

    protected $description = 'Check stored expiry dates against RDAP and live TLS certificates';

    public function handle(AssetVerifier $verifier, Healthcheck $healthcheck): int
    {
        $query = Asset::query()->active()->with('client');

        if ($id = $this->option('asset')) {
            $query->whereKey($id);
        } else {
            // Oldest checks first, so a large book of assets rotates through
            // rather than hammering the same few every night.
            $query->orderByRaw('last_verified_at is null desc')->orderBy('last_verified_at');
        }

        $checked = $matched = $mismatched = $failed = 0;

        $query->limit((int) $this->option('limit'))->get()
            ->filter(fn (Asset $asset) => $verifier->supports($asset))
            ->each(function (Asset $asset) use ($verifier, &$checked, &$matched, &$mismatched, &$failed) {
                $checked++;

                try {
                    $result = $verifier->verify($asset);
                } catch (\Throwable $e) {
                    // One bad lookup must never end the loop.
                    $failed++;
                    Log::warning('Expiry verification threw.', ['asset' => $asset->id, 'error' => $e->getMessage()]);

                    return;
                }

                if (! $result->ok) {
                    $failed++;
                    $this->line("  <fg=yellow>skip</> {$asset->name}: {$result->error}");

                    return;
                }

                $asset->refresh();

                if ($asset->verification_status->value === 'match') {
                    $matched++;
                } else {
                    $mismatched++;
                    $this->line("  <fg=cyan>diff</> {$asset->name}: registry says {$result->expiresAt->toDateString()}");
                }

                // Be a polite client of a free public service.
                usleep(250_000);
            });

        $this->table(
            ['Checked', 'Match', 'Differs', 'Failed'],
            [[$checked, $matched, $mismatched, $failed]],
        );

        $healthcheck->ping();

        return self::SUCCESS;
    }
}
