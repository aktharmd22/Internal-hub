<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    case Dormant = 'dormant';
    case Churned = 'churned';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'ok',
            self::Dormant => 'warn',
            self::Churned => 'neutral',
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
