<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetStatus: string
{
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expiring => 'Expiring',
            self::Expired => 'Expired',
            self::Renewed => 'Renewed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'neutral',
            self::Expiring => 'warn',
            self::Expired => 'danger',
            self::Renewed => 'ok',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Statuses the reminder engine skips entirely.
     *
     * @return list<self>
     */
    public static function silent(): array
    {
        return [self::Renewed, self::Cancelled];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
