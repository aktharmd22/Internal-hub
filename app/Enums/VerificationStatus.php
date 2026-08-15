<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case Unchecked = 'unchecked';
    case Match = 'match';
    case Mismatch = 'mismatch';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unchecked => 'Not checked',
            self::Match => 'Verified',
            self::Mismatch => 'Date differs',
            self::Failed => 'Lookup failed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unchecked => 'neutral',
            self::Match => 'ok',
            self::Mismatch => 'warn',
            self::Failed => 'danger',
        };
    }
}
