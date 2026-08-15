<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Chat')]
class Index extends Component
{
    #[On('echo-notification')]
    #[On('connection-restored')]
    public function refresh(): void
    {
        // Re-rendering re-reads the thread list.
    }

    public function render(): View
    {
        return view('livewire.chat.index', [
            'threads' => $this->threads(),
        ]);
    }

    /**
     * Threads the user is on, unread first, then by most recent activity.
     *
     * @return Collection<int, Task>
     */
    private function threads(): Collection
    {
        $user = auth()->user();

        $tasks = Task::query()
            ->active()
            ->visibleTo($user)
            ->whereHas('messages')
            ->with(['client:id,name,company_name', 'assignee:id,name'])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->where('user_id', '!=', $user->id)
                    ->whereDoesntHave('reads', fn ($r) => $r->where('user_id', $user->id)),
            ])
            ->orderByDesc('last_activity_at')
            ->limit(60)
            ->get();

        // The preview line needs the newest message per thread; one extra query
        // for the whole page rather than one per row.
        $latest = TaskMessage::query()
            ->whereIn('task_id', $tasks->pluck('id'))
            ->with('user:id,name')
            ->orderByDesc('id')
            ->get()
            ->unique('task_id')
            ->keyBy('task_id');

        return $tasks
            ->each(fn (Task $task) => $task->setRelation('latestMessage', $latest->get($task->id)))
            ->sortByDesc(fn (Task $task) => [$task->unread_count > 0 ? 1 : 0, $task->last_activity_at?->timestamp ?? 0])
            ->values();
    }
}
