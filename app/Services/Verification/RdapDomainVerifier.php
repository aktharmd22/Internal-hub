<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Reads a domain's expiry straight from the registry over RDAP.
 *
 * RDAP is the structured successor to WHOIS: JSON, no rate-limit theatre, and
 * rdap.org bootstraps to the right registry for any TLD.
 */
class RdapDomainVerifier implements ExpiryVerifier
{
    public function supports(Asset $asset): bool
    {
        return $asset->type === AssetType::Domain && filled($this->domain($asset));
    }

    public function verify(Asset $asset): VerificationResult
    {
        $domain = $this->domain($asset);

        try {
            $response = Http::timeout(15)
                ->retry(2, 1500, throw: false)
                ->acceptJson()
                ->withHeaders(['User-Agent' => config('app.name').' expiry verification'])
                ->get("https://rdap.org/domain/{$domain}");
        } catch (\Throwable $e) {
            return VerificationResult::failed($e->getMessage());
        }

        if ($response->status() === 404) {
            return VerificationResult::failed('Not found in any registry');
        }

        if (! $response->successful()) {
            return VerificationResult::failed("RDAP returned HTTP {$response->status()}");
        }

        $date = collect($response->json('events') ?? [])
            ->firstWhere('eventAction', 'expiration')['eventDate'] ?? null;

        if (blank($date)) {
            return VerificationResult::failed('No expiration event in the RDAP record');
        }

        try {
            return VerificationResult::found(Carbon::parse($date)->setTimezone(config('app.timezone')));
        } catch (\Throwable) {
            return VerificationResult::failed("Unreadable expiry date: {$date}");
        }
    }

    private function domain(Asset $asset): string
    {
        $raw = $asset->identifier ?: $asset->name;

        // Tolerate whatever was pasted in: scheme, path, www, trailing dot.
        $host = parse_url(str_contains($raw, '://') ? $raw : "https://{$raw}", PHP_URL_HOST) ?: $raw;

        return rtrim(strtolower(preg_replace('/^www\./', '', trim($host))), '.');
    }
}
