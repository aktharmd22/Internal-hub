<?php

declare(strict_types=1);

namespace App\Enums;

enum RecipientScope: string
{
    case Owner = 'owner';
    case Admins = 'admins';
    case AccountManager = 'account_manager';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Asset owner',
            self::Admins => 'All admins',
            self::AccountManager => 'Account manager',
            self::Client => 'The client',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
