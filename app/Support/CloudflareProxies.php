<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cloudflare's edge IP ranges, trusted as reverse proxies.
 *
 * Behind Cloudflare every request arrives from Cloudflare, so without this:
 *
 *   - `request()->ip()` is a Cloudflare address, which collapses the login
 *     rate limiter onto one bucket for every visitor on earth, and writes the
 *     wrong IP into the credential-vault access log — the one record that has
 *     to be trustworthy.
 *   - `request()->secure()` is false, so Laravel builds http:// URLs that
 *     Cloudflare then redirects to https://, which can loop.
 *
 * The ranges are listed rather than trusting `*`. The origin has a public IP
 * of its own, so trusting every proxy would let anyone who finds that IP spoof
 * X-Forwarded-For and forge the address in the audit log.
 *
 * Read with plain filesystem calls and no facades: this is resolved from
 * bootstrap/app.php, before the container is booted. Reaching for Cache here
 * throws "A facade root has not been set" and the application never starts.
 *
 * Published at https://www.cloudflare.com/ips/ and refreshed by `cloudflare:ips`.
 */
final class CloudflareProxies
{
    /** A refreshed list, if one has been fetched. */
    private const STORE = '/storage/app/cloudflare-ips.json';

    /** Below this, a response is treated as truncated rather than authoritative. */
    private const MINIMUM = 10;

    /** @var list<string> */
    private const FALLBACK = [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',

        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public static function path(): string
    {
        return dirname(__DIR__, 2).self::STORE;
    }

    /**
     * @return list<string>
     */
    public static function ranges(): array
    {
        $path = self::path();

        if (! is_readable($path)) {
            return self::FALLBACK;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        if (! is_array($decoded) || count($decoded) < self::MINIMUM) {
            return self::FALLBACK;
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    /**
     * @param  list<string>  $ranges
     */
    public static function store(array $ranges): void
    {
        $path = self::path();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode(array_values($ranges), JSON_PRETTY_PRINT));
    }

    public static function forget(): void
    {
        if (is_file(self::path())) {
            unlink(self::path());
        }
    }

    /** @return list<string> */
    public static function fallback(): array
    {
        return self::FALLBACK;
    }

    public static function minimum(): int
    {
        return self::MINIMUM;
    }
}
