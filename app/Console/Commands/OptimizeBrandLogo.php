<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Brand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Trims, keys and re-encodes the company logo.
 *
 * Logos arrive as an export from a design tool: a small mark centred on a big
 * square canvas, often with a solid background baked in. Both are a problem
 * here — the padding makes the mark render tiny in a fixed slot, and a baked-in
 * white background becomes a white rectangle in dark mode.
 */
class OptimizeBrandLogo extends Command
{
    protected $signature = 'brand:optimize
                            {--key= : Make a background colour transparent. A hex value, or "auto" to read the corners.}
                            {--tolerance=32 : How far a pixel may differ from the keyed colour and still be removed (0-160).}
                            {--max=512 : Cap the longest side, in pixels.}
                            {--no-trim : Keep the transparent margin instead of cropping to the artwork.}
                            {--dry-run : Report what would change and write nothing.}';

    protected $description = 'Trim, key out the background and re-encode the company logo';

    public function handle(): int
    {
        $path = Brand::absolutePath();

        if (! $path || ! File::exists($path)) {
            $this->error('No logo found. Upload one under Settings → Company, or drop a PNG at public/logo.png.');

            return self::FAILURE;
        }

        if (str_ends_with(strtolower($path), '.svg')) {
            $this->info('That logo is an SVG. It is already resolution-independent and needs no optimising.');

            return self::SUCCESS;
        }

        $image = @imagecreatefromstring((string) File::get($path));

        if ($image === false) {
            $this->error('That file is not a readable PNG, JPG or WebP.');

            return self::FAILURE;
        }

        $before = ['bytes' => filesize($path), 'w' => imagesx($image), 'h' => imagesy($image)];

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($key = $this->option('key')) {
            $image = $this->keyOut($image, $key);
        }

        if (! $this->option('no-trim')) {
            $image = $this->trim($image);
        }

        $image = $this->cap($image, (int) $this->option('max'));

        $after = ['w' => imagesx($image), 'h' => imagesy($image)];

        // Compression 9 costs a little CPU once and saves every visitor bytes
        // on every page.
        ob_start();
        imagepng($image, null, 9);
        $encoded = (string) ob_get_clean();

        imagedestroy($image);

        $this->table(
            ['', 'Before', 'After'],
            [
                ['Dimensions', "{$before['w']}×{$before['h']}", "{$after['w']}×{$after['h']}"],
                ['Size', $this->kb($before['bytes']), $this->kb(strlen($encoded))],
                ['Saving', '', $this->saving($before['bytes'], strlen($encoded))],
            ],
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        // The original is kept outside public/, so it is never served and never
        // picked up as a second logo.
        $backup = storage_path('app/brand-originals/'.date('Ymd-His').'-'.basename($path));
        File::ensureDirectoryExists(dirname($backup));
        File::copy($path, $backup);

        File::put($path, $encoded);

        $this->info('Optimised. Original kept at '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $backup).'.');

        return self::SUCCESS;
    }

    /**
     * Replaces a solid background with transparency.
     *
     * Pixels close to the keyed colour go fully transparent, pixels well away
     * from it are untouched, and the band between the two fades — a hard
     * threshold would leave a jagged halo on anti-aliased edges.
     */
    private function keyOut(\GdImage $image, string $key): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        [$kr, $kg, $kb] = $key === 'auto'
            ? $this->cornerColour($image)
            : $this->hex($key);

        $inner = max(0, min(160, (int) $this->option('tolerance')));
        $outer = $inner * 2 + 12;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;

                if ($alpha === 127) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $distance = sqrt((($r - $kr) ** 2) + (($g - $kg) ** 2) + (($b - $kb) ** 2));

                if ($distance <= $inner) {
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, 127));
                } elseif ($distance < $outer) {
                    $fade = (int) round(127 * (1 - ($distance - $inner) / ($outer - $inner)));
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, max($alpha, $fade)));
                }
            }
        }

        return $image;
    }

    /** @return array{int, int, int} */
    private function cornerColour(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $samples = [[1, 1], [$width - 2, 1], [1, $height - 2], [$width - 2, $height - 2]];
        $r = $g = $b = 0;

        foreach ($samples as [$x, $y]) {
            $rgba = imagecolorat($image, $x, $y);
            $r += ($rgba >> 16) & 0xFF;
            $g += ($rgba >> 8) & 0xFF;
            $b += $rgba & 0xFF;
        }

        return [intdiv($r, 4), intdiv($g, 4), intdiv($b, 4)];
    }

    /**
     * Crops away fully transparent margin. A mark centred on a big square
     * canvas otherwise renders at a fraction of the slot it is given.
     */
    private function trim(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) === 127) {
                    continue;
                }

                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < 0) {
            $this->warn('Every pixel is transparent; skipping the trim.');

            return $image;
        }

        $cropped = imagecrop($image, [
            'x' => $minX,
            'y' => $minY,
            'width' => $maxX - $minX + 1,
            'height' => $maxY - $minY + 1,
        ]);

        if ($cropped === false) {
            return $image;
        }

        imagesavealpha($cropped, true);
        imagedestroy($image);

        return $cropped;
    }

    private function cap(\GdImage $image, int $max): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $max) {
            return $image;
        }

        $scale = $max / $longest;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /** @return array{int, int, int} */
    private function hex(string $value): array
    {
        $hex = ltrim($value, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $this->warn('Could not read that colour; keying white instead.');

            return [255, 255, 255];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function kb(int $bytes): string
    {
        return number_format($bytes / 1024, 1).' KB';
    }

    private function saving(int $before, int $after): string
    {
        if ($after >= $before) {
            return 'none';
        }

        return number_format(($before - $after) / 1024, 1).' KB ('.round(($before - $after) / $before * 100).'%)';
    }
}
