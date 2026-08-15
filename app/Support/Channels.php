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

            ReminderChannel::Broadcast => self::broadcastReady(),

            ReminderChannel::WebPush => filled(config('webpush.vapid.public_key'))
                && filled(config('webpush.vapid.private_key')),

            ReminderChannel::WhatsApp => filled(Setting::get('whatsapp_token', config('services.whatsapp.token')))
                && filled(Setting::get('whatsapp_phone_number_id', config('services.whatsapp.phone_number_id'))),
        };
    }

    /**
     * Broadcasting counts as available only when a real driver is configured
     * *and* it has credentials.
     *
     * `BROADCAST_CONNECTION=null` is parsed by Laravel into PHP null, not the
     * string "null", so a naive `!== 'null'` check reports the channel as ready
     * on a system with no broadcaster at all. That writes a reminder_logs row
     * for a send that never happened — and the unique index then suppresses the
     * real one for good, the day Pusher is finally switched on.
     */
    public static function broadcastReady(): bool
    {
        $driver = config('broadcasting.default');

        if (blank($driver) || in_array($driver, ['null', 'log'], true)) {
            return false;
        }

        return match ($driver) {
            'pusher', 'reverb' => filled(config("broadcasting.connections.{$driver}.key")),
            'ably' => filled(config('broadcasting.connections.ably.key')),
            default => true,
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
