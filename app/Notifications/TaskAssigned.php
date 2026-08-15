<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ReminderChannel;
use App\Models\Task;
use App\Models\User;
use App\Support\Channels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Task $task, public User $actor) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        if (Channels::isAvailable(ReminderChannel::WebPush)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Assigned to you: {$this->task->title}")
            ->line("{$this->actor->name} assigned {$this->task->reference} to you.");

        if ($this->task->due_at) {
            $message->line('Due '.$this->task->due_at->format('l, j F').'.');
        }

        return $message->action('Open the task', route('tasks.show', $this->task));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'reference' => $this->task->reference,
            'title' => "Assigned to you: {$this->task->title}",
            'body' => $this->task->dueLabel() ?? $this->task->reference,
            'url' => route('tasks.show', $this->task),
            'actor' => $this->actor->name,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + ['sound' => 'task']);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("Assigned to you: {$this->task->title}")
            ->body($this->task->dueLabel() ?? $this->task->reference)
            ->tag("task-{$this->task->id}")
            ->data(['url' => route('tasks.show', $this->task)]);
    }
}
