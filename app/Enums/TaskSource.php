<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskSource: string
{
    case Manual = 'manual';
    case Renewal = 'renewal';
    case Recurring = 'recurring';
    case ClientPortal = 'client_portal';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Created by hand',
            self::Renewal => 'Raised by a renewal',
            self::Recurring => 'From a recurring template',
            self::ClientPortal => 'Requested by the client',
        };
    }
}
