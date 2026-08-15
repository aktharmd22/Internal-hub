<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    private const CACHE_KEY = 'settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Settings are read on nearly every request, so the whole table is cached
     * as one entry and busted on write.
     *
     * @return array<string, mixed>
     */
    public static function all(...$args): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
            return static::query()
                ->get()
                ->mapWithKeys(fn (self $setting) => [
                    $setting->key => $setting->is_secret ? $setting->decrypted() : $setting->value,
                ])
                ->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, bool $secret = false): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $secret && filled($value) ? Crypt::encryptString((string) $value) : $value,
                'is_secret' => $secret,
            ],
        );
    }

    private function decrypted(): ?string
    {
        if (blank($this->value)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (\Throwable) {
            // A rotated APP_KEY should not take the whole app down.
            return null;
        }
    }
}
