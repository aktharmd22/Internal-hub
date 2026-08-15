<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Asset;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  Collection<int, Task>  $dueToday
     * @param  Collection<int, Task>  $overdue
     * @param  Collection<int, Task>  $completedYesterday
     * @param  Collection<int, Task>  $awaitingReview
     * @param  Collection<int, Asset>  $expiringSoon
     */
    public function __construct(
        public Collection $dueToday,
        public Collection $overdue,
        public Collection $completedYesterday,
        public Collection $awaitingReview,
        public Collection $expiringSoon,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $parts = array_filter([
            $this->overdue->isNotEmpty() ? "{$this->overdue->count()} overdue" : null,
            $this->dueToday->isNotEmpty() ? "{$this->dueToday->count()} due today" : null,
            $this->awaitingReview->isNotEmpty() ? "{$this->awaitingReview->count()} to review" : null,
        ]);

        return (new MailMessage)
            ->subject($parts ? 'Today: '.implode(' · ', $parts) : 'Today: nothing outstanding')
            ->view('mail.daily-digest', [
                'dueToday' => $this->dueToday,
                'overdue' => $this->overdue,
                'completedYesterday' => $this->completedYesterday,
                'awaitingReview' => $this->awaitingReview,
                'expiringSoon' => $this->expiringSoon,
                'greetingName' => method_exists($notifiable, 'firstName') ? $notifiable->firstName() : $notifiable->name,
                'actionUrl' => route('dashboard'),
            ]);
    }
}
