<?php

use App\Livewire\Settings\Index as SettingsScreen;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brand;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->admin()->create();

    /*
     * Every logo test runs against a scratch directory, never the real public/.
     * A live install has the customer's artwork sitting there, and a suite that
     * writes to it will eventually delete it — which is exactly what happened
     * before this path became configurable.
     */
    $this->brandPath = storage_path('framework/testing/brand-'.Str::random(8));

    File::ensureDirectoryExists($this->brandPath);
    config()->set('brand.path', $this->brandPath);
});

afterEach(function () {
    File::deleteDirectory($this->brandPath);
});

function brandFile(string $name, string $contents): void
{
    File::put(Brand::basePath($name), $contents);
}

function fakeImage(int $width, int $height): string
{
    return UploadedFile::fake()->image('x.png', $width, $height)->getContent();
}

/* ------------------------------------------------------------- resolution */

test('the real public directory is never touched by these tests', function () {
    brandFile('logo.png', fakeImage(64, 64));

    // Asserting on public_path('logo.png') would be wrong here: Windows
    // resolves it case-insensitively onto a real install's Logo.png. What
    // matters is that the file we just wrote landed in the scratch directory.
    expect(Brand::has())->toBeTrue()
        ->and(Brand::basePath())->toBe($this->brandPath)
        ->and(Brand::basePath())->not->toBe(rtrim(public_path(), '/\\'))
        ->and(File::exists($this->brandPath.DIRECTORY_SEPARATOR.'logo.png'))->toBeTrue();
});

test('with no logo the built-in mark is used', function () {
    expect(Brand::has())->toBeFalse()
        ->and(Brand::url())->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        // The fallback is the indigo square, and no <img> is emitted for it.
        ->assertSee('bg-accent-600 text-on-solid', escape: false)
        ->assertDontSee('/'.Brand::DIRECTORY.'/logo-', escape: false);
});

/*
 * The zero-config path. Somebody with FTP access and no interest in the
 * settings screen should be able to drop a file in and be done.
 */
test('a file dropped in at logo.png is picked up with no configuration', function () {
    brandFile('logo.png', fakeImage(64, 64));

    expect(Brand::has())->toBeTrue()
        ->and(Brand::url())->toBe('/logo.png');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('/logo.png', escape: false);
});

test('the drop-in lookup is case-insensitive, because Linux is not', function () {
    // Windows finds this either way. Hostinger would not, so an exact-name
    // check would work locally and lose the logo in production.
    brandFile('Logo.PNG', fakeImage(64, 64));

    expect(Brand::has())->toBeTrue()
        ->and(Brand::url())->toBe('/Logo.PNG');
});

test('a file that merely starts with logo is ignored', function () {
    brandFile('logotype-draft.png', fakeImage(64, 64));

    expect(Brand::has())->toBeFalse();
});

test('an uploaded logo wins over the drop-in file', function () {
    brandFile('logo.png', fakeImage(64, 64));

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('brand.png', 256, 256))
        ->call('uploadLogo')
        ->assertHasNoErrors();

    expect(Brand::url())->toStartWith('/'.Brand::DIRECTORY.'/logo-')
        ->and(Brand::url())->toEndWith('.png');
});

/* ------------------------------------------------------------------ shape */

test('a replaced logo is seen immediately, not after the worker restarts', function () {
    brandFile('logo.png', fakeImage(400, 100));

    expect(Brand::isWordmark())->toBeTrue();

    // Same filename, different shape. A process-lifetime memo would still be
    // reporting the old aspect ratio here, and under the cron-launched queue
    // worker that process lives for a minute at a time.
    brandFile('logo.png', fakeImage(200, 200));
    touch(Brand::basePath('logo.png'), time() + 5);
    clearstatcache();

    expect(Brand::isWordmark())->toBeFalse();
});

test('a wide lockup is treated as a wordmark, a square one is not', function () {
    brandFile('logo.png', fakeImage(480, 120));
    expect(Brand::isWordmark())->toBeTrue();

    File::delete(Brand::basePath('logo.png'));

    brandFile('logo.png', fakeImage(200, 200));
    touch(Brand::basePath('logo.png'), time() + 5);
    clearstatcache();

    expect(Brand::isWordmark())->toBeFalse();
});

test('an svg aspect ratio is read from its viewBox', function () {
    brandFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100"></svg>');

    expect(Brand::aspectRatio())->toBe(3.0)
        ->and(Brand::isWordmark())->toBeTrue();
});

/*
 * A lockup already contains the company name. Rendering the name beside it
 * says everything twice and leaves the logo fighting for width — which is
 * exactly how the sign-in screen looked before this rule existed.
 */
test('a wordmark is shown without the company name repeated beside it', function () {
    brandFile('logo.png', fakeImage(480, 120));

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('/logo.png', escape: false);

    expect(Brand::isWordmark())->toBeTrue();
});

/* ----------------------------------------------------------------- upload */

test('uploading a logo stores it in the web root and records it', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('brand.png', 256, 256))
        ->call('uploadLogo')
        ->assertHasNoErrors();

    $stored = Setting::get('logo_file');

    expect($stored)->not->toBeNull()
        // In the web root, not behind storage:link — that symlink is the first
        // thing to break on shared hosting.
        ->and(File::exists(Brand::basePath(Brand::DIRECTORY.'/'.$stored)))->toBeTrue()
        ->and(Brand::has())->toBeTrue();
});

test('replacing the logo deletes the old file and changes the url', function () {
    $screen = Livewire::actingAs($this->admin)->test(SettingsScreen::class);

    $screen->set('logo', UploadedFile::fake()->image('one.png', 64, 64))->call('uploadLogo');
    $first = Brand::url();

    $screen->set('logo', UploadedFile::fake()->image('two.png', 64, 64))->call('uploadLogo');
    $second = Brand::url();

    // The filename changes on every upload, so no browser or CDN serves a
    // stale logo.
    expect($second)->not->toBe($first)
        ->and(File::exists(Brand::basePath(ltrim($first, '/'))))->toBeFalse()
        ->and(File::exists(Brand::basePath(ltrim($second, '/'))))->toBeTrue();
});

test('removing the logo restores the default mark and deletes the file', function () {
    $screen = Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('brand.png', 64, 64))
        ->call('uploadLogo');

    $path = Brand::basePath(ltrim(Brand::url(), '/'));

    $screen->call('removeLogo');

    expect(Brand::has())->toBeFalse()
        ->and(File::exists($path))->toBeFalse();
});

test('a non-image upload is refused', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->create('payload.php', 10, 'application/x-php'))
        ->call('uploadLogo')
        ->assertHasErrors('logo');

    expect(Brand::has())->toBeFalse();
});

test('an oversized upload is refused', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('huge.png')->size(4096))
        ->call('uploadLogo')
        ->assertHasErrors('logo');
});

test('only an admin can change the logo', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('settings.index'))->assertForbidden();
});

/* ------------------------------------------------------------ where shown */

test('the logo appears on the sign-in screen', function () {
    brandFile('logo.png', fakeImage(64, 64));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('/logo.png', escape: false);
});

test('email uses an absolute url, since a root-relative path means nothing there', function () {
    brandFile('logo.png', fakeImage(64, 64));

    expect(Brand::mailUrl())->toBe(rtrim(config('app.url'), '/').'/logo.png');
});

test('the company name from settings overrides the app name', function () {
    expect(Brand::name())->toBe(config('app.name'));

    Setting::put('company_name', 'Gnext Hub');

    expect(Brand::name())->toBe('Gnext Hub');
});

/* ------------------------------------------------------- logo optimisation */

test('optimising trims the transparent margin a design tool exports', function () {
    // A small mark centred on a big canvas: 86% of the file is empty padding,
    // and in a fixed slot the mark renders at a fraction of its size.
    $canvas = imagecreatetruecolor(500, 500);
    imagesavealpha($canvas, true);
    imagealphablending($canvas, false);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagealphablending($canvas, true);
    imagefilledrectangle($canvas, 15, 181, 485, 318, imagecolorallocate($canvas, 0x43, 0x38, 0xCA));

    ob_start();
    imagepng($canvas);
    brandFile('logo.png', (string) ob_get_clean());
    imagedestroy($canvas);

    $this->artisan('brand:optimize')->assertSuccessful();

    [$width, $height] = getimagesize(Brand::basePath('logo.png'));

    expect($width)->toBe(471)
        ->and($height)->toBe(138);
})->skip(fn () => ! extension_loaded('gd'), 'GD is not available.');

test('optimising keeps the original safe outside the web root', function () {
    brandFile('logo.png', fakeImage(300, 300));

    $this->artisan('brand:optimize')->assertSuccessful();

    $backups = File::glob(storage_path('app/brand-originals/*'));

    expect($backups)->not->toBeEmpty();
});

test('a dry run writes nothing', function () {
    brandFile('logo.png', fakeImage(300, 120));

    $before = File::get(Brand::basePath('logo.png'));

    $this->artisan('brand:optimize', ['--dry-run' => true])->assertSuccessful();

    expect(File::get(Brand::basePath('logo.png')))->toBe($before);
});

test('keying a solid background makes it transparent', function () {
    $canvas = imagecreatetruecolor(100, 100);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagefilledrectangle($canvas, 30, 30, 70, 70, imagecolorallocate($canvas, 0x43, 0x38, 0xCA));

    ob_start();
    imagepng($canvas);
    brandFile('logo.png', (string) ob_get_clean());
    imagedestroy($canvas);

    $this->artisan('brand:optimize', ['--key' => 'auto', '--no-trim' => true])->assertSuccessful();

    $result = imagecreatefrompng(Brand::basePath('logo.png'));
    $corner = imagecolorsforindex($result, imagecolorat($result, 2, 2));
    $centre = imagecolorsforindex($result, imagecolorat($result, 50, 50));
    imagedestroy($result);

    expect($corner['alpha'])->toBe(127)
        ->and($centre['alpha'])->toBe(0);
})->skip(fn () => ! extension_loaded('gd'), 'GD is not available.');

test('optimising an svg is a no-op rather than an error', function () {
    brandFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100"></svg>');

    $this->artisan('brand:optimize')
        ->expectsOutputToContain('already resolution-independent')
        ->assertSuccessful();
});

test('optimising explains itself when there is no logo', function () {
    $this->artisan('brand:optimize')
        ->expectsOutputToContain('No logo found')
        ->assertFailed();
});

/* ------------------------------------------------------------ icon rebuild */

/**
 * The command writes every icon, so every icon has to be put back. These live
 * in the real public/ directory because that is where the PWA manifest points.
 *
 * @return array<string, int>
 */
function iconSizes(): array
{
    return [
        'icons/icon-192.png' => 192,
        'icons/icon-512.png' => 512,
        'icons/maskable-512.png' => 512,
        'icons/badge-72.png' => 72,
        'icons/apple-touch-icon.png' => 180,
        'favicon.ico' => 48,
    ];
}

/** @return array<string, string> */
function snapshotIcons(): array
{
    return collect(iconSizes())->keys()
        ->mapWithKeys(fn (string $path) => [$path => File::get(public_path($path))])
        ->all();
}

/** @param  array<string, string>  $originals */
function restoreIcons(array $originals): void
{
    foreach ($originals as $path => $contents) {
        File::put(public_path($path), $contents);
    }
}

test('the icon command rebuilds every icon at the right size', function () {
    $originals = snapshotIcons();

    try {
        brandFile('logo.png', fakeImage(400, 200));

        $this->artisan('brand:icons', ['--background' => '#4338ca'])->assertSuccessful();

        foreach (iconSizes() as $path => $size) {
            [$width, $height] = getimagesize(public_path($path));

            expect($width)->toBe($size, "{$path} is {$width}px wide")
                ->and($height)->toBe($size, "{$path} is {$height}px tall");
        }

        // The maskable icon is full-bleed, so its corner is the background
        // colour rather than transparency.
        $maskable = imagecreatefrompng(public_path('icons/maskable-512.png'));
        $corner = imagecolorsforindex($maskable, imagecolorat($maskable, 2, 2));
        imagedestroy($maskable);

        expect([$corner['red'], $corner['green'], $corner['blue']])->toBe([0x43, 0x38, 0xCA]);
    } finally {
        restoreIcons($originals);
    }
})->skip(fn () => ! extension_loaded('gd'), 'GD is not available.');

test('a wide logo is fitted into the icon, never stretched', function () {
    $originals = snapshotIcons();

    try {
        // 4:1. If the command stretched to fill, every row would be identical.
        brandFile('logo.png', fakeImage(800, 200));

        $this->artisan('brand:icons')->assertSuccessful();

        $icon = imagecreatefrompng(public_path('icons/icon-512.png'));
        $top = imagecolorsforindex($icon, imagecolorat($icon, 256, 80));
        $middle = imagecolorsforindex($icon, imagecolorat($icon, 256, 256));
        imagedestroy($icon);

        expect($top)->not->toBe($middle);
    } finally {
        restoreIcons($originals);
    }
})->skip(fn () => ! extension_loaded('gd'), 'GD is not available.');

test('the icon command explains itself rather than failing silently', function () {
    $this->artisan('brand:icons')
        ->expectsOutputToContain('No logo found')
        ->assertFailed();
});

test('the icon command refuses SVG with a usable instruction', function () {
    brandFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

    $this->artisan('brand:icons')
        ->expectsOutputToContain('GD cannot read SVG')
        ->assertFailed();
});
