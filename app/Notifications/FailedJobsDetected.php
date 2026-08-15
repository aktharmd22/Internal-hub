<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FailedJobsDetected extends Notification
{
    /**
     * @param  array<string, int>  $breakdown
     */
    public function __construct(public int $count, public array $breakdown) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Deliberately not queued. The queue is the thing that is broken.
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->error()
            ->subject("{$this->count} background ".str('job')->plural($this->count).' failed')
            ->line('Some background work did not complete in the last 24 hours. Reminders and digests run on this queue.');

        foreach ($this->breakdown as $job => $count) {
            $message->line("• {$job} — {$count}");
        }

        return $message
            ->line('Run `php artisan queue:retry all` after fixing the cause, or `php artisan queue:failed` to inspect them.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'failed_jobs',
            'title' => "{$this->count} background ".str('job')->plural($this->count).' failed',
            'body' => 'Reminders and digests run on this queue. Check the failed jobs table.',
            'count' => $this->count,
            'breakdown' => $this->breakdown,
            'url' => route('settings.index'),
        ];
    }
}
