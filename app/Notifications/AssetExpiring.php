<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One reminder, on exactly one channel.
 *
 * The engine sends a separate instance per channel so that each has its own
 * `reminder_logs` row and its own idempotency guarantee. A failed WhatsApp
 * send can never suppress the email.
 */
class AssetExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public Asset $asset,
        public int $daysRemaining,
        public string $channel,
        public bool $isEscalation = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->view('mail.asset-expiring', [
                'asset' => $this->asset,
                'daysRemaining' => $this->daysRemaining,
                'isEscalation' => $this->isEscalation,
                'greetingName' => $this->greetingName($notifiable),
                'actionUrl' => route('assets.show', $this->asset),
            ])
            ->text('mail.asset-expiring-text', [
                'asset' => $this->asset,
                'daysRemaining' => $this->daysRemaining,
                'greetingName' => $this->greetingName($notifiable),
                'actionUrl' => route('assets.show', $this->asset),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'asset_expiring',
            'asset_id' => $this->asset->id,
            'asset_name' => $this->asset->name,
            'asset_type' => $this->asset->type->value,
            'client_name' => $this->asset->client->displayName(),
            'days_remaining' => $this->daysRemaining,
            'expires_at' => $this->asset->expires_at->toDateString(),
            'is_escalation' => $this->isEscalation,
            'url' => route('assets.show', $this->asset),
            'title' => $this->subject(),
            'body' => $this->line(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /** @return array<string, mixed> */
    public function toWhatsApp(object $notifiable): array
    {
        return [
            'body' => $this->subject().' — '.$this->line(),
            'url' => route('assets.show', $this->asset),
        ];
    }

    public function subject(): string
    {
        $name = $this->asset->name;

        return match (true) {
            $this->daysRemaining < 0 => "Expired: {$name}",
            $this->daysRemaining === 0 => "Expires today: {$name}",
            $this->daysRemaining === 1 => "Expires tomorrow: {$name}",
            default => "Expires in {$this->daysRemaining} days: {$name}",
        };
    }

    public function line(): string
    {
        $client = $this->asset->client->displayName();
        $date = $this->asset->expires_at->format('j M Y');

        return $this->daysRemaining < 0
            ? "{$this->asset->type->label()} for {$client} lapsed on {$date}."
            : "{$this->asset->type->label()} for {$client} is due on {$date}.";
    }

    private function greetingName(object $notifiable): string
    {
        return method_exists($notifiable, 'firstName')
            ? $notifiable->firstName()
            : (string) ($notifiable->name ?? 'there');
    }

    /**
     * Keeps the queue from collapsing distinct reminders into one job.
     */
    public function uniqueId(): string
    {
        return "{$this->asset->id}:{$this->daysRemaining}:{$this->channel}";
    }
}
