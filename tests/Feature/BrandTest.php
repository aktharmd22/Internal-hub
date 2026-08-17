<?php

use App\Livewire\Settings\Index as SettingsScreen;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brand;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->admin()->create();

    File::deleteDirectory(public_path(Brand::DIRECTORY));

    foreach (Brand::EXTENSIONS as $extension) {
        File::delete(public_path("logo.{$extension}"));
    }
});

afterEach(function () {
    File::deleteDirectory(public_path(Brand::DIRECTORY));

    foreach (Brand::EXTENSIONS as $extension) {
        File::delete(public_path("logo.{$extension}"));
    }
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
test('a file dropped at public/logo.png is picked up with no configuration', function () {
    File::put(public_path('logo.png'), UploadedFile::fake()->image('x.png', 64, 64)->getContent());

    expect(Brand::has())->toBeTrue()
        ->and(Brand::url())->toBe('/logo.png');

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('/logo.png', escape: false);
});

test('an uploaded logo wins over the drop-in file', function () {
    File::put(public_path('logo.png'), UploadedFile::fake()->image('x.png', 64, 64)->getContent());

    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('brand.png', 256, 256))
        ->call('uploadLogo')
        ->assertHasNoErrors();

    expect(Brand::url())->toStartWith('/'.Brand::DIRECTORY.'/logo-')
        ->and(Brand::url())->toEndWith('.png');
});

test('uploading a logo stores it in the web root and records it', function () {
    Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('brand.png', 256, 256))
        ->call('uploadLogo')
        ->assertHasNoErrors();

    $stored = Setting::get('logo_file');

    expect($stored)->not->toBeNull()
        // In public/, not behind storage:link — that symlink is the first
        // thing to break on shared hosting.
        ->and(File::exists(public_path(Brand::DIRECTORY.'/'.$stored)))->toBeTrue()
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
        ->and(File::exists(public_path(ltrim($first, '/'))))->toBeFalse()
        ->and(File::exists(public_path(ltrim($second, '/'))))->toBeTrue();
});

test('removing the logo restores the default mark and deletes the file', function () {
    $screen = Livewire::actingAs($this->admin)
        ->test(SettingsScreen::class)
        ->set('logo', UploadedFile::fake()->image('brand.png', 64, 64))
        ->call('uploadLogo');

    $path = public_path(ltrim(Brand::url(), '/'));

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

test('the logo appears on the sign-in screen', function () {
    File::put(public_path('logo.png'), UploadedFile::fake()->image('x.png', 64, 64)->getContent());

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('/logo.png', escape: false);
});

test('email uses an absolute url, since a root-relative path means nothing there', function () {
    File::put(public_path('logo.png'), UploadedFile::fake()->image('x.png', 64, 64)->getContent());

    expect(Brand::mailUrl())->toBe(rtrim(config('app.url'), '/').'/logo.png');
});

test('the company name from settings overrides the app name', function () {
    expect(Brand::name())->toBe(config('app.name'));

    Setting::put('company_name', 'Gnext Hub');

    expect(Brand::name())->toBe('Gnext Hub');
});

/* --------------------------------------------------------- icon rebuild */

/**
 * The command writes every icon, so every icon has to be put back — restoring
 * only the one a test asserts on leaves the working tree dirty.
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
    $sizes = iconSizes();
    $originals = snapshotIcons();

    try {
        File::put(public_path('logo.png'), UploadedFile::fake()->image('x.png', 400, 200)->getContent());

        $this->artisan('brand:icons', ['--background' => '#4338ca'])->assertSuccessful();

        foreach ($sizes as $path => $size) {
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

test('a wide logo is fitted, never stretched or cropped', function () {
    $originals = snapshotIcons();

    try {
        // 4:1. If the command stretched to fill, the result would be square.
        File::put(public_path('logo.png'), UploadedFile::fake()->image('wide.png', 800, 200)->getContent());

        $this->artisan('brand:icons')->assertSuccessful();

        $icon = imagecreatefrompng(public_path('icons/icon-512.png'));

        // Rows well above and below a centred 4:1 logo stay background.
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
    File::put(public_path('logo.svg'), '<svg xmlns="http://www.w3.org/2000/svg"/>');

    $this->artisan('brand:icons')
        ->expectsOutputToContain('GD cannot read SVG')
        ->assertFailed();
});
