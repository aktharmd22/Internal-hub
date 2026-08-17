<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Rebuilds the PWA icons, the favicon and the Apple touch icon from the
 * uploaded logo.
 *
 * Uses GD, which every Hostinger PHP build ships. Nothing here needs Imagick,
 * ImageMagick binaries or a Node toolchain.
 */
class GenerateBrandIcons extends Command
{
    protected $signature = 'brand:icons
                            {--background= : Hex fill behind the logo, e.g. #4338ca. Defaults to white.}
                            {--source= : Use this image instead of the saved logo}';

    protected $description = 'Rebuild the app icons and favicon from the company logo';

    public function handle(): int
    {
        $source = $this->option('source') ?: Brand::absolutePath();

        if (! $source || ! File::exists($source)) {
            $this->error('No logo found. Upload one under Settings → Company, or drop a PNG at public/logo.png.');

            return self::FAILURE;
        }

        if (str_ends_with(strtolower($source), '.svg')) {
            $this->error('GD cannot read SVG. Export the logo to PNG at 512x512 or larger and try again.');

            return self::FAILURE;
        }

        $logo = @imagecreatefromstring((string) File::get($source));

        if ($logo === false) {
            $this->error('That file is not a readable PNG, JPG or WebP.');

            return self::FAILURE;
        }

        [$r, $g, $b] = $this->background();

        $targets = [
            'icons/icon-192.png' => [192, false],
            'icons/icon-512.png' => [512, false],
            // Maskable icons are cropped to a circle by the launcher, so the
            // artwork sits inside the safe zone with a full-bleed background.
            'icons/maskable-512.png' => [512, true],
            'icons/badge-72.png' => [72, false],
            'icons/apple-touch-icon.png' => [180, false],
        ];

        File::ensureDirectoryExists(public_path('icons'));

        foreach ($targets as $path => [$size, $maskable]) {
            $this->render($logo, public_path($path), $size, $maskable, $r, $g, $b);
            $this->line("  wrote public/{$path}");
        }

        $this->render($logo, public_path('favicon.ico'), 48, false, $r, $g, $b);
        $this->line('  wrote public/favicon.ico');

        imagedestroy($logo);

        $this->info('Icons rebuilt. Hard-refresh, or reinstall the PWA, to see them.');

        return self::SUCCESS;
    }

    private function render(\GdImage $logo, string $out, int $size, bool $maskable, int $r, int $g, int $b): void
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
        imagealphablending($canvas, true);

        if (! $maskable) {
            $this->roundCorners($canvas, $size, (int) round($size * 0.22));
        }

        // Maskable icons keep 20% clear on every edge; the rest get 12%.
        $inset = (int) round($size * ($maskable ? 0.20 : 0.12));
        $box = $size - $inset * 2;

        $width = imagesx($logo);
        $height = imagesy($logo);
        $scale = min($box / $width, $box / $height);

        $drawWidth = (int) round($width * $scale);
        $drawHeight = (int) round($height * $scale);

        imagecopyresampled(
            $canvas,
            $logo,
            (int) round(($size - $drawWidth) / 2),
            (int) round(($size - $drawHeight) / 2),
            0,
            0,
            $drawWidth,
            $drawHeight,
            $width,
            $height,
        );

        imagepng($canvas, $out);
        imagedestroy($canvas);
    }

    /**
     * Punches transparent corners so the icon is not a hard square on Android.
     */
    private function roundCorners(\GdImage $image, int $size, int $radius): void
    {
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $cx = $x < $radius ? $radius : ($x >= $size - $radius ? $size - $radius - 1 : $x);
                $cy = $y < $radius ? $radius : ($y >= $size - $radius ? $size - $radius - 1 : $y);

                if ((($x - $cx) ** 2 + ($y - $cy) ** 2) > $radius ** 2) {
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }
    }

    /** @return array{int, int, int} */
    private function background(): array
    {
        $hex = ltrim((string) ($this->option('background') ?: '#ffffff'), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $this->warn('Could not read the background colour; using white.');

            return [255, 255, 255];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
