<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Actions\TaskStatusTransition;
use App\Enums\TaskStatus;
use App\Events\TaskUpdated;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Task $task;

    /** Status the reason sheet is collecting a note for. */
    public ?string $pendingStatus = null;

    public string $note = '';

    public bool $detailsOpen = false;

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);

        $this->task = $task;
    }

    #[On('task-saved')]
    #[On('echo-private:task.{task.id},TaskUpdated')]
    public function refreshTask(): void
    {
        $this->task->refresh();
    }

    /* ------------------------------------------------------------- status */

    #[On('task:request-status')]
    public function requestStatus(string $status): void
    {
        $target = TaskStatus::from($status);

        if ($target->requiresReason()) {
            $this->pendingStatus = $status;
            $this->note = '';
            $this->dispatch('open-modal', 'status-reason');

            return;
        }

        $this->applyStatus($target, null);
    }

    public function confirmStatus(): void
    {
        if (! $this->pendingStatus) {
            return;
        }

        $this->applyStatus(TaskStatus::from($this->pendingStatus), $this->note);
    }

    private function applyStatus(TaskStatus $target, ?string $note): void
    {
        try {
            app(TaskStatusTransition::class)($this->task, $target, auth()->user(), $note);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), tone: 'danger');

            return;
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), tone: 'danger');

            return;
        }

        $this->task->refresh();
        $this->pendingStatus = null;
        $this->note = '';

        $this->dispatch('close-modal', 'status-reason');
        $this->dispatch('message-posted');
        $this->dispatch('toast', message: "Moved to {$target->label()}.", tone: 'ok');
    }

    /* --------------------------------------------------------- assignment */

    public function assign(?int $userId): void
    {
        $this->authorize('assign', $this->task);

        $previous = $this->task->assigned_to;

        $this->task->forceFill([
            'assigned_to' => $userId,
            'status' => $userId && $this->task->status === TaskStatus::Open ? TaskStatus::Assigned : $this->task->status,
            'last_activity_at' => now(),
        ])->save();

        if ($userId) {
            $this->task->participants()->syncWithoutDetaching([$userId => ['role' => 'assignee']]);
        }

        $assignee = $userId ? User::find($userId) : null;

        TaskMessage::create([
            'task_id' => $this->task->id,
            'user_id' => auth()->id(),
            'type' => 'system',
            'body' => $assignee
                ? auth()->user()->firstName().' assigned this to '.$assignee->name
                : auth()->user()->firstName().' removed the assignee',
        ]);

        if ($assignee && $assignee->id !== auth()->id() && $assignee->id !== $previous) {
            $assignee->notify(new TaskAssigned($this->task, auth()->user()));
        }

        TaskUpdated::dispatch($this->task->fresh(), auth()->id());

        $this->task->refresh();
        $this->dispatch('message-posted');
        $this->dispatch('toast', message: $assignee ? "Assigned to {$assignee->firstName()}." : 'Assignee cleared.', tone: 'ok');
    }

    public function setDueDate(?string $date): void
    {
        $this->authorize('update', $this->task);

        $this->task->forceFill([
            'due_at' => $date ? Carbon::parse($date)->setTime(17, 0) : null,
            'last_activity_at' => now(),
        ])->save();

        TaskMessage::create([
            'task_id' => $this->task->id,
            'user_id' => auth()->id(),
            'type' => 'system',
            'body' => $date
                ? auth()->user()->firstName().' set the due date to '.$this->task->due_at->format('j M Y')
                : auth()->user()->firstName().' removed the due date',
        ]);

        TaskUpdated::dispatch($this->task->fresh(), auth()->id());

        $this->task->refresh();
        $this->dispatch('message-posted');
    }

    /* ------------------------------------------------------------- timer */

    #[On('task:toggle-timer')]
    public function toggleTimer(): void
    {
        $this->authorize('trackTime', $this->task);

        $user = auth()->user();
        $running = $user->runningTimer();

        if ($running && $running->task_id === $this->task->id) {
            $running->forceFill([
                'stopped_at' => now(),
                'duration_seconds' => (int) $running->started_at->diffInSeconds(now()),
            ])->save();

            $this->dispatch('toast', message: 'Timer stopped.', tone: 'ok');

            return;
        }

        // Nobody is on two tasks at the same second: any other running timer
        // is closed before this one opens.
        if ($running) {
            $running->forceFill([
                'stopped_at' => now(),
                'duration_seconds' => (int) $running->started_at->diffInSeconds(now()),
            ])->save();
        }

        TaskTimeLog::create([
            'task_id' => $this->task->id,
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        if ($this->task->status === TaskStatus::Assigned) {
            app(TaskStatusTransition::class)($this->task, TaskStatus::InProgress, $user);
            $this->task->refresh();
        }

        $this->dispatch('toast', message: 'Timer running.', tone: 'ok');
    }

    /* -------------------------------------------------------- watchers */

    public function addWatcher(int $userId): void
    {
        $this->authorize('update', $this->task);

        $this->task->participants()->syncWithoutDetaching([$userId => ['role' => 'watcher']]);

        $this->dispatch('toast', message: 'Watcher added.', tone: 'ok');
    }

    #[On('task:toggle-mute')]
    public function toggleMute(): void
    {
        $pivot = $this->task->participants()->where('user_id', auth()->id())->first()?->pivot;

        $this->task->participants()->syncWithoutDetaching([
            auth()->id() => [
                'role' => $pivot?->role ?? 'watcher',
                'muted_at' => $pivot?->muted_at ? null : now(),
            ],
        ]);

        $this->dispatch('toast', message: $pivot?->muted_at ? 'Notifications on.' : 'Muted this thread.', tone: 'ok');
    }

    public function render(): View
    {
        $this->task->loadMissing(['client', 'project', 'assignee', 'asset', 'parent', 'participants']);

        $running = auth()->user()->runningTimer();

        return view('livewire.tasks.show', [
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'subtasks' => $this->task->subtasks()->with('assignee:id,name')->get(),
            'timerRunning' => $running?->task_id === $this->task->id,
            'trackedSeconds' => (int) $this->task->timeLogs()->sum('duration_seconds'),
            'muted' => (bool) $this->task->participants->firstWhere('id', auth()->id())?->pivot?->muted_at,
            'nextStatuses' => collect($this->task->status->allowedNext())
                ->filter(fn (TaskStatus $status) => auth()->user()->can('transitionTo', [$this->task, $status]))
                ->values(),
        ])->title($this->task->reference);
    }
}
