<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Support\Carbon;

/**
 * Opens a TLS connection and reads the certificate's notAfter.
 *
 * The certificate the server is actually presenting is the only source of
 * truth here — a renewal that was issued but never deployed still leaves the
 * site expiring.
 */
class SslCertificateVerifier implements ExpiryVerifier
{
    public function supports(Asset $asset): bool
    {
        return $asset->type === AssetType::Ssl && filled($this->host($asset));
    }

    public function verify(Asset $asset): VerificationResult
    {
        $host = $this->host($asset);

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
                // Read the dates even from a chain we would otherwise reject:
                // an expired certificate is exactly what we are looking for.
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errorCode,
            $errorMessage,
            15,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return VerificationResult::failed($errorMessage ?: "Could not connect to {$host}:443");
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $certificate) {
            return VerificationResult::failed('The server presented no certificate');
        }

        $parsed = openssl_x509_parse($certificate);

        if (! is_array($parsed) || empty($parsed['validTo_time_t'])) {
            return VerificationResult::failed('Certificate has no readable expiry');
        }

        return VerificationResult::found(
            Carbon::createFromTimestampUTC((int) $parsed['validTo_time_t'])->setTimezone(config('app.timezone'))
        );
    }

    private function host(Asset $asset): string
    {
        $raw = $asset->identifier ?: $asset->name;

        $host = parse_url(str_contains($raw, '://') ? $raw : "https://{$raw}", PHP_URL_HOST) ?: $raw;

        return rtrim(strtolower(trim($host)), '.');
    }
}
