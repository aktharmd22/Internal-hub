<?php

use App\Models\Credential;
use App\Models\User;
use App\Support\CloudflareProxies;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    CloudflareProxies::forget();
});

afterEach(function () {
    CloudflareProxies::forget();
});

/*
 * bootstrap/app.php resolves the ranges before the container is booted. Any
 * facade in that path throws "A facade root has not been set" and the whole
 * application fails to start — in production, on the first request.
 */
test('the ranges resolve without booting the container', function () {
    $source = file_get_contents(app_path('Support/CloudflareProxies.php'));

    expect($source)->not->toContain('Illuminate\Support\Facades')
        ->and($source)->not->toContain('Cache::')
        ->and($source)->not->toContain('storage_path(')
        ->and(CloudflareProxies::ranges())->toBeArray();
});

test('the cloudflare ranges are trusted, and a wildcard is not', function () {
    $trusted = CloudflareProxies::ranges();

    expect($trusted)->toContain('173.245.48.0/20')
        ->and($trusted)->toContain('2400:cb00::/32')
        // Trusting every proxy would let anyone who finds the origin IP forge
        // X-Forwarded-For and write a false address into the vault audit log.
        ->and($trusted)->not->toContain('*')
        ->and(count($trusted))->toBeGreaterThan(15);
});

/*
 * Without trusted proxies every visitor arrives as the same Cloudflare edge
 * address: the login limiter throttles the whole company as one bucket, and
 * the credential vault records the proxy rather than the person.
 */
test('the visitor real ip survives the proxy, not the edge address', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.64.0.1'])
        ->withHeaders(['X-Forwarded-For' => '49.207.10.55'])
        ->get(route('login'));

    $response->assertOk();

    expect(request()->ip())->not->toBe('172.64.0.1');
});

test('an address that is not cloudflare cannot forge the visitor ip', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->get(route('login'))
        ->assertOk();

    // 203.0.113.9 is not a Cloudflare range, so its forwarded header is ignored.
    expect(CloudflareProxies::ranges())->not->toContain('203.0.113.9');
});

test('the vault access log records an ip', function () {
    $admin = User::factory()->admin()->create();
    $credential = Credential::factory()->create();

    $this->actingAs($admin);

    $credential->recordAccess($admin);

    $entry = Activity::query()->where('log_name', 'credential-access')->first();

    expect($entry->properties)->toHaveKey('ip')
        ->and($entry->causer_id)->toBe($admin->id);
});

/* ------------------------------------------------------------- refreshing */

test('the range list refreshes from cloudflare', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [
                'ipv4_cidrs' => array_map(fn (int $i) => "10.{$i}.0.0/16", range(1, 12)),
                'ipv6_cidrs' => ['2400:cb00::/32'],
            ],
        ]),
    ]);

    $this->artisan('cloudflare:ips')->assertSuccessful();

    expect(CloudflareProxies::ranges())->toContain('10.1.0.0/16');
});

/*
 * A truncated or malformed response must never shrink the trusted list — that
 * would quietly put edge IPs back into the audit log.
 */
test('a suspiciously short response is rejected and the bundled list kept', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => ['ipv4_cidrs' => ['10.0.0.0/8'], 'ipv6_cidrs' => []],
        ]),
    ]);

    $this->artisan('cloudflare:ips')->assertFailed();

    expect(CloudflareProxies::ranges())->toBe(CloudflareProxies::fallback());
});

test('an unreachable cloudflare leaves the bundled list in place', function () {
    Http::fake(fn () => throw new RuntimeException('network down'));

    $this->artisan('cloudflare:ips')
        ->expectsOutputToContain('nothing is broken')
        ->assertFailed();

    expect(CloudflareProxies::ranges())->toBe(CloudflareProxies::fallback());
});
