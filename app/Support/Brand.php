<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The company's logo and name.
 *
 * The file lives in the web root rather than behind `storage:link`. On shared
 * hosting that symlink is one of the first things to break — a control panel
 * restore, or a deploy that copies rather than syncs, leaves it dangling and
 * the logo silently disappears. A real file in the web root cannot.
 *
 * An uploaded logo carries a random filename, so replacing it busts every
 * browser and CDN cache without any versioning query string.
 */
final class Brand
{
    public const DIRECTORY = 'brand';

    /** Accepted for display. SVG is fine here but cannot seed the PWA icons. */
    public const EXTENSIONS = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

    private const SETTING = 'logo_file';

    /**
     * The web root the logo is served from.
     *
     * Configurable rather than a hardcoded `public_path()` so the test suite
     * can point at a temporary directory. Tests that write to the real public/
     * directory end up deleting the customer's artwork.
     */
    public static function basePath(string $append = ''): string
    {
        $base = rtrim((string) config('brand.path', public_path()), '/\\');

        return $append === '' ? $base : $base.DIRECTORY_SEPARATOR.ltrim($append, '/\\');
    }

    public static function name(): string
    {
        return (string) Setting::get('company_name', config('app.name'));
    }

    public static function has(): bool
    {
        return self::url() !== null;
    }

    /**
     * Web path to the logo, or null to fall back to the built-in mark.
     */
    public static function url(): ?string
    {
        $file = Setting::get(self::SETTING);

        if (filled($file) && File::exists(self::basePath(self::DIRECTORY.'/'.$file))) {
            return '/'.self::DIRECTORY.'/'.$file;
        }

        return self::dropIn();
    }

    /**
     * Zero-config path: drop a file in at public/logo.png and it is picked up
     * without touching the settings screen.
     *
     * The directory is scanned rather than probed for exact names, because
     * "Logo.png" is what a person actually saves. Windows finds it either way;
     * Linux does not, so an exact-name check would work locally and lose the
     * logo in production. The real filename is returned, casing intact, so the
     * URL resolves on a case-sensitive server.
     */
    private static function dropIn(): ?string
    {
        // Deliberately not memoised. A static cache here lives for the whole
        // PHP process, which under the cron-launched queue worker means a
        // replaced logo would go unnoticed until the worker exited. Listing a
        // directory of a dozen entries is not worth that bug.
        foreach (File::files(self::basePath()) as $file) {
            $name = strtolower($file->getFilename());

            if (! str_starts_with($name, 'logo.')) {
                continue;
            }

            if (in_array(pathinfo($name, PATHINFO_EXTENSION), self::EXTENSIONS, true)) {
                return '/'.$file->getFilename();
            }
        }

        return null;
    }

    public static function absolutePath(): ?string
    {
        $url = self::url();

        return $url ? self::basePath(ltrim($url, '/')) : null;
    }

    public static function isRaster(): bool
    {
        $url = self::url();

        return $url !== null && ! str_ends_with(strtolower($url), '.svg');
    }

    /**
     * Width divided by height, or null when it cannot be read.
     */
    public static function aspectRatio(): ?float
    {
        $path = self::absolutePath();

        if (! $path || ! File::exists($path)) {
            return null;
        }

        // Keyed by path, modification time and size, so a logo replaced at the
        // same filename misses the cache rather than reporting the old shape.
        static $memo = [];

        $key = $path.':'.@filemtime($path).':'.@filesize($path);

        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        $memo[$key] = null;

        if (str_ends_with(strtolower($path), '.svg')) {
            $svg = (string) File::get($path);

            if (preg_match('/viewBox\s*=\s*["\']\s*[\d.-]+[,\s]+[\d.-]+[,\s]+([\d.]+)[,\s]+([\d.]+)/i', $svg, $m)) {
                $memo[$key] = (float) $m[2] > 0 ? (float) $m[1] / (float) $m[2] : null;
            }

            return $memo[$key];
        }

        $size = @getimagesize($path);

        if ($size && $size[1] > 0) {
            $memo[$key] = $size[0] / $size[1];
        }

        return $memo[$key];
    }

    /**
     * A wide logo is a lockup that already contains the company name, so
     * printing the name next to it says everything twice and leaves the mark
     * squeezed into whatever width is left. Those get the row to themselves.
     */
    public static function isWordmark(): bool
    {
        return (self::aspectRatio() ?? 1.0) >= 2.2;
    }

    /**
     * Replaces any existing logo and returns the stored filename.
     */
    public static function store(UploadedFile $file): string
    {
        $directory = self::basePath(self::DIRECTORY);

        File::ensureDirectoryExists($directory);

        self::clear();

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $filename = 'logo-'.Str::lower(Str::random(10)).'.'.$extension;

        // Copy the bytes rather than move() the file. A Livewire upload is a
        // TemporaryUploadedFile living on a filesystem disk, which move()
        // cannot relocate — it reaches for a real path that may not exist.
        File::put(
            $directory.DIRECTORY_SEPARATOR.$filename,
            method_exists($file, 'get') ? $file->get() : $file->getContent(),
        );

        Setting::put(self::SETTING, $filename);

        return $filename;
    }

    public static function clear(): void
    {
        $existing = Setting::get(self::SETTING);

        if (filled($existing)) {
            File::delete(self::basePath(self::DIRECTORY.'/'.$existing));
        }

        Setting::put(self::SETTING, null);
    }

    /**
     * Absolute URL, for use in email where a root-relative path is meaningless.
     */
    public static function mailUrl(): ?string
    {
        $url = self::url();

        return $url ? rtrim(config('app.url'), '/').$url : null;
    }
}
