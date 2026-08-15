<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Redundancy against a single missed alert. If one reminder slips through a
 * spam filter, the Monday digest still puts it in front of someone.
 */
class WeeklyRenewalDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /**
     * @param  Collection<string, Collection<int, Asset>>  $weeks
     */
    public function __construct(
        public Collection $weeks,
        public int $total,
        public float $cost,
        public int $overdue,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->total === 0
            ? 'Nothing due in the next 45 days'
            : "{$this->total} renewals due in the next 45 days";

        if ($this->overdue > 0) {
            $subject = "{$this->overdue} overdue · ".$subject;
        }

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.weekly-digest', [
                'weeks' => $this->weeks,
                'total' => $this->total,
                'cost' => $this->cost,
                'overdue' => $this->overdue,
                'greetingName' => method_exists($notifiable, 'firstName') ? $notifiable->firstName() : $notifiable->name,
                'actionUrl' => route('assets.index'),
            ]);
    }
}
