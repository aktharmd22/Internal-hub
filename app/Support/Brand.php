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
 * The file lives in `public/brand/` rather than behind `storage:link`. On
 * shared hosting that symlink is one of the first things to break — a control
 * panel restore or a deploy that copies rather than syncs leaves it dangling,
 * and the logo silently disappears. A real file in the web root cannot.
 *
 * The stored filename carries a random suffix, so replacing the logo busts
 * every browser and CDN cache without any versioning query string.
 */
final class Brand
{
    public const DIRECTORY = 'brand';

    private const SETTING = 'logo_file';

    /** Accepted for display. SVG is fine here but cannot seed the PWA icons. */
    public const EXTENSIONS = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

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

        if (filled($file) && File::exists(public_path(self::DIRECTORY.'/'.$file))) {
            return '/'.self::DIRECTORY.'/'.$file;
        }

        // Zero-config path: drop a file in at public/logo.svg (or .png) and it
        // is picked up without touching the settings screen at all.
        foreach (self::EXTENSIONS as $extension) {
            if (File::exists(public_path("logo.{$extension}"))) {
                return "/logo.{$extension}";
            }
        }

        return null;
    }

    public static function absolutePath(): ?string
    {
        $url = self::url();

        return $url ? public_path(ltrim($url, '/')) : null;
    }

    public static function isRaster(): bool
    {
        $url = self::url();

        return $url !== null && ! str_ends_with(strtolower($url), '.svg');
    }

    /**
     * Replaces any existing logo and returns the stored filename.
     */
    public static function store(UploadedFile $file): string
    {
        $directory = public_path(self::DIRECTORY);

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
            File::delete(public_path(self::DIRECTORY.'/'.$existing));
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
