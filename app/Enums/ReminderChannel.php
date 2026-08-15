<?php

declare(strict_types=1);

namespace App\Enums;

enum ReminderChannel: string
{
    case Mail = 'mail';
    case Database = 'database';
    case Broadcast = 'broadcast';
    case WebPush = 'webpush';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Database => 'In-app',
            self::Broadcast => 'Live toast',
            self::WebPush => 'Web push',
            self::WhatsApp => 'WhatsApp',
        };
    }

    /**
     * Channels that always work, with no external credentials to configure.
     *
     * @return list<string>
     */
    public static function alwaysAvailable(): array
    {
        return [self::Mail->value, self::Database->value];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
