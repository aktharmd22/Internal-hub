<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ReminderChannel;
use App\Enums\TaskStatus;
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

class TaskStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Task $task,
        public TaskStatus $status,
        public User $actor,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        if (Channels::isAvailable(ReminderChannel::WebPush)) {
            $channels[] = WebPushChannel::class;
        }

        // Only the states somebody is actually waiting on earn an email.
        if (in_array($this->status, [TaskStatus::Submitted, TaskStatus::Completed, TaskStatus::Reopened], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action('Open '.$this->task->reference, route('tasks.show', $this->task));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_status',
            'task_id' => $this->task->id,
            'reference' => $this->task->reference,
            'status' => $this->status->value,
            'title' => $this->title(),
            'body' => $this->body(),
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
            ->title($this->title())
            ->body($this->body())
            ->tag("task-{$this->task->id}")
            ->data(['url' => route('tasks.show', $this->task)]);
    }

    private function title(): string
    {
        return match ($this->status) {
            TaskStatus::Submitted => "Ready for review: {$this->task->reference}",
            TaskStatus::Completed => "Approved: {$this->task->reference}",
            TaskStatus::Reopened => "Sent back: {$this->task->reference}",
            default => "{$this->task->reference} is now {$this->status->label()}",
        };
    }

    private function body(): string
    {
        return "{$this->actor->name} · {$this->task->title}";
    }
}
