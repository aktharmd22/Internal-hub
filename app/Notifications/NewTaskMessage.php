<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ReminderChannel;
use App\Models\TaskMessage;
use App\Support\Channels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewTaskMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public TaskMessage $message, public bool $isMention = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // No email for chat. A message thread that emails on every line is a
        // thread people mute.
        $channels = ['database'];

        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        if (Channels::isAvailable(ReminderChannel::WebPush)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->isMention ? 'mention' : 'message',
            'task_id' => $this->message->task_id,
            'message_id' => $this->message->id,
            'reference' => $this->message->task->reference,
            'title' => $this->title(),
            'body' => $this->preview(),
            'url' => route('tasks.show', $this->message->task_id),
            'actor' => $this->message->user?->name,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + ['sound' => 'message']);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->preview())
            ->tag("task-{$this->message->task_id}")
            ->data(['url' => route('tasks.show', $this->message->task_id)]);
    }

    private function title(): string
    {
        $who = $this->message->user?->firstName() ?? 'Someone';

        return $this->isMention
            ? "{$who} mentioned you in {$this->message->task->reference}"
            : "{$who} · {$this->message->task->reference}";
    }

    private function preview(): string
    {
        return match ($this->message->type) {
            'voice' => 'Voice note · '.$this->message->durationLabel(),
            default => Str::limit((string) $this->message->body, 90),
        };
    }
}
