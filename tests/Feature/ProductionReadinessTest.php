<?php

use App\Models\User;
use App\Support\Lucide;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

/*
 * Shared hosting has no Node, no Redis, no Supervisor and no persistent
 * websocket process. These tests hold the app to that shape, because a
 * dependency on any of them would only show up on the live server.
 */

/*
 * `route:cache` is not optional on shared hosting — it is most of the
 * per-request boot cost. It refuses to run if any route cannot be serialised,
 * so running it for real is the only check worth having.
 */
test('the route table caches, so route:cache works on the host', function () {
    $this->artisan('route:cache')->assertSuccessful();
    $this->artisan('config:cache')->assertSuccessful();
    $this->artisan('view:cache')->assertSuccessful();
})->after(function () {
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
});

test('none of the application routes use a closure', function () {
    // Framework and package routes are their own business; these are ours.
    $ours = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array($route->getName(), [
            'dashboard', 'assets.index', 'assets.show', 'assets.import',
            'clients.index', 'clients.show', 'projects.index', 'projects.show',
            'tasks.index', 'tasks.show', 'chat.index', 'notifications.index',
            'team.index', 'reports.index', 'vault.index', 'settings.index',
            'more', 'profile', 'logout',
        ], true));

    expect($ours)->not->toBeEmpty();

    $closures = $ours
        ->filter(fn ($route) => $route->getAction('uses') instanceof Closure)
        ->map(fn ($route) => $route->uri())
        ->values();

    expect($closures->all())->toBe([]);
});

test('the kitchen sink never reaches a production route table', function () {
    $source = File::get(base_path('routes/web.php'));

    expect($source)->toContain('if (! app()->isProduction())');

    // And the guard actually wraps the route, not something else.
    $position = strpos($source, 'if (! app()->isProduction())');
    expect(substr($source, $position, 200))->toContain('kitchen-sink');
});

/*
 * Redis is not available on the target host. The test suite overrides these to
 * array/sync for speed, so the shipped .env.example is what has to be checked.
 */
test('the shipped env example targets the shared host', function () {
    $env = File::get(base_path('.env.example'));

    expect($env)->toContain('QUEUE_CONNECTION=database')
        ->and($env)->toContain('CACHE_STORE=database')
        ->and($env)->toContain('SESSION_DRIVER=database')
        ->and($env)->toContain('APP_TIMEZONE=Asia/Kolkata')
        ->and($env)->toContain('HEALTHCHECK_URL')
        ->and($env)->toContain('PUSHER_APP_KEY')
        ->and($env)->toContain('VAPID_PUBLIC_KEY')
        ->and($env)->toContain('POSTMARK_TOKEN')
        ->and($env)->toContain('WHATSAPP_ACCESS_TOKEN');
});

test('every env key this application reads is documented in the example', function () {
    $example = File::get(base_path('.env.example'));

    // Only our own code. Published vendor configs read dozens of keys that
    // have working defaults and never need setting on this host.
    $used = collect();

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        preg_match_all("/env\(\s*'([A-Z0-9_]+)'/", $file->getContents(), $matches);
        $used = $used->concat($matches[1]);
    }

    $missing = $used->unique()
        ->reject(fn (string $key) => str_contains($example, $key))
        ->values();

    expect($missing->all())->toBe([]);
});

test('every config key this application reads resolves to something', function () {
    // A typo'd config path returns null silently, which is how a channel ends
    // up permanently "not configured" with no error anywhere.
    foreach ([
        'app.timezone', 'app.name',
        'services.healthcheck.url', 'services.whatsapp.token', 'services.whatsapp.phone_number_id',
        'webpush.vapid.public_key', 'webpush.vapid.private_key',
        'broadcasting.default', 'backup.backup.name',
        'permission.models.role', 'activitylog.enabled',
    ] as $key) {
        expect(config()->has($key))->toBeTrue("config [{$key}] does not resolve");
    }
});

test('the timezone is Asia/Kolkata and dates are compared as dates', function () {
    expect(config('app.timezone'))->toBe('Asia/Kolkata');

    $migration = File::get(base_path('database/migrations/2026_08_16_000200_create_assets_table.php'));

    // A datetime here drifts across timezones and "3 days before" fires on the
    // wrong day. This is the trap that motivated the whole column choice.
    expect($migration)->toContain("\$table->date('expires_at')")
        ->and($migration)->not->toContain("dateTime('expires_at')");
});

test('the reminder log idempotency index is present and complete', function () {
    $migration = File::get(base_path('database/migrations/2026_08_16_000400_create_reminder_logs_table.php'));

    expect($migration)->toContain('reminder_logs_idempotency');

    foreach (['asset_id', 'days_before', 'channel', 'recipient_type', 'recipient_id'] as $column) {
        expect($migration)->toContain("'{$column}'");
    }
});

test('no livewire component polls, because reverb and polling double-render', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        if (preg_match('/wire:poll/', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

test('the built assets exist and are committed for a host with no node', function () {
    expect(File::exists(public_path('build/manifest.json')))->toBeTrue();

    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);

    expect($manifest)->toHaveKey('resources/css/app.css')
        ->and($manifest)->toHaveKey('resources/js/app.js');

    foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
        expect(File::exists(public_path('build/'.$manifest[$entry]['file'])))->toBeTrue();
    }

    // /public/build must not be ignored, or a deploy ships no CSS.
    expect(File::get(base_path('.gitignore')))->not->toContain("\n/public/build");
});

test('the compiled css carries both themes and the self-hosted font', function () {
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);
    $css = File::get(public_path('build/'.$manifest['resources/css/app.css']['file']));

    expect($css)->toContain('DM Sans Variable')
        ->and($css)->toContain('.dark')
        // Status colours must survive the build. An interpolated class name
        // would be missing here and the colour would vanish in production.
        ->and($css)->toContain('--color-danger-600')
        ->and($css)->toContain('--color-ok-600')
        ->and($css)->toContain('--color-warn-600');
});

test('the pwa ships a manifest, a worker, icons and an offline page', function () {
    foreach ([
        'manifest.webmanifest',
        'sw.js',
        'offline.html',
        'icons/icon-192.png',
        'icons/icon-512.png',
        'icons/maskable-512.png',
        'icons/apple-touch-icon.png',
    ] as $file) {
        expect(File::exists(public_path($file)))->toBeTrue("public/{$file} is missing");
    }

    $manifest = json_decode(File::get(public_path('manifest.webmanifest')), true);

    expect($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('purpose'))->toContain('maskable');
});

test('every icon referenced anywhere in the views exists', function () {
    $missing = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        preg_match_all('/<x-icon[^>]*\bname="([a-z0-9-]+)"/', $file->getContents(), $matches);

        foreach ($matches[1] as $name) {
            if (! Lucide::has($name)) {
                $missing[] = $file->getRelativePathname().' → '.$name;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('every blade view referenced by a livewire component exists', function () {
    $missing = [];

    foreach (Finder::create()->files()->in(app_path('Livewire'))->name('*.php') as $file) {
        preg_match_all("/view\(\s*'([a-z0-9\.\-]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $view) {
            if (! view()->exists($view)) {
                $missing[] = $file->getRelativePathname().' → '.$view;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('the deployment notes cover cron, the queue worker and the healthcheck', function () {
    $readme = File::get(base_path('README.md'));

    expect($readme)->toContain('schedule:run')
        ->and($readme)->toContain('queue:work --stop-when-empty')
        ->and($readme)->toContain('HEALTHCHECK_URL')
        ->and($readme)->toContain('public_html');
});

test('a guest is turned away from every authenticated screen', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (['dashboard', 'tasks.index', 'chat.index', 'notifications.index', 'more', 'profile'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
});

test('the app never exposes itself to search engines', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertSee('noindex, nofollow', escape: false);
});
