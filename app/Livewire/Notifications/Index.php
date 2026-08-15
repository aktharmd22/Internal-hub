<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Notifications')]
class Index extends Component
{
    #[Url(except: '')]
    public string $type = '';

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        $this->dispatch('toast', message: 'All caught up.', tone: 'ok');
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function render(): View
    {
        return view('livewire.notifications.index', [
            'groups' => $this->groups(),
            'unread' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Grouped by day, so a burst of renewal alerts at 09:00 reads as one
     * morning rather than forty separate events.
     */
    private function groups(): Collection
    {
        return auth()->user()->notifications()
            ->when($this->type, fn ($q) => $q->where('data->type', $this->type))
            ->latest()
            ->limit(120)
            ->get()
            ->groupBy(fn ($notification) => match (true) {
                $notification->created_at->isToday() => 'Today',
                $notification->created_at->isYesterday() => 'Yesterday',
                $notification->created_at->isCurrentWeek() => 'This week',
                default => $notification->created_at->format('F Y'),
            });
    }
}
