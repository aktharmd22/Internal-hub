<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ReminderChannel;
use App\Models\Setting;

/**
 * Which notification channels can actually deliver right now.
 *
 * A rule may list WhatsApp before anyone has configured it. Rather than log a
 * send that never happened, the engine skips unavailable channels entirely —
 * so the moment credentials are added, the next run delivers them.
 */
final class Channels
{
    /** @return list<string> */
    public static function available(): array
    {
        return collect(ReminderChannel::cases())
            ->filter(fn (ReminderChannel $channel) => self::isAvailable($channel))
            ->map(fn (ReminderChannel $channel) => $channel->value)
            ->values()
            ->all();
    }

    public static function isAvailable(ReminderChannel $channel): bool
    {
        return match ($channel) {
            ReminderChannel::Mail, ReminderChannel::Database => true,

            ReminderChannel::Broadcast => config('broadcasting.default') !== 'null',

            ReminderChannel::WebPush => filled(config('webpush.vapid.public_key'))
                && filled(config('webpush.vapid.private_key')),

            ReminderChannel::WhatsApp => filled(Setting::get('whatsapp_token', config('services.whatsapp.token')))
                && filled(Setting::get('whatsapp_phone_number_id', config('services.whatsapp.phone_number_id'))),
        };
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    public static function filter(array $channels): array
    {
        $available = self::available();

        return array_values(array_intersect($channels, $available));
    }
}
