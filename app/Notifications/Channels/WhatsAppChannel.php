<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Setting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API delivery.
 *
 * In India WhatsApp gets read and email frequently does not, so this is the
 * channel that actually lands. It no-ops cleanly when credentials are absent,
 * which keeps it optional without any conditional code at the call site.
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $token = Setting::get('whatsapp_token', config('services.whatsapp.token'));
        $phoneNumberId = Setting::get('whatsapp_phone_number_id', config('services.whatsapp.phone_number_id'));

        if (blank($token) || blank($phoneNumberId)) {
            return;
        }

        $to = $this->number($notifiable);

        if (blank($to)) {
            return;
        }

        $payload = $notification->toWhatsApp($notifiable);

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->retry(2, 1000, throw: false)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => trim($payload['body'].PHP_EOL.PHP_EOL.($payload['url'] ?? '')),
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp send failed.', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            // Never let a messaging outage break the run that produced it —
            // the email and in-app copies of this reminder already went out.
            Log::warning('WhatsApp send threw.', ['error' => $e->getMessage()]);
        }
    }

    private function number(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForWhatsApp')) {
            $number = $notifiable->routeNotificationForWhatsApp();
        } else {
            $number = $notifiable->whatsapp ?? $notifiable->phone ?? null;
        }

        if (blank($number)) {
            return null;
        }

        // The Cloud API wants digits only, country code included.
        $digits = preg_replace('/\D+/', '', (string) $number);

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }
}
