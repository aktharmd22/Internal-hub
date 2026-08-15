<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TaskStatus;
use App\Events\TaskUpdated;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Notifications\TaskStatusChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place a task's status is allowed to change.
 *
 * Controllers and Livewire components call this; nothing assigns `status`
 * directly. Every transition is validated against the state machine, checked
 * against the policy, written to task_status_logs and posted into the thread
 * as a system message — so the timeline and the conversation are one history.
 */
class TaskStatusTransition
{
    public function __invoke(
        Task $task,
        TaskStatus $to,
        User $actor,
        ?string $note = null,
        ?int $blockedByTaskId = null,
    ): Task {
        $from = $task->status;

        if ($from === $to) {
            return $task;
        }

        if (! in_array($to, $from->allowedNext(), true)) {
            throw ValidationException::withMessages([
                'status' => "A task cannot move from {$from->label()} to {$to->label()}.",
            ]);
        }

        if (! $actor->can('transitionTo', [$task, $to])) {
            throw new AuthorizationException(
                in_array($to, TaskStatus::approverOnly(), true)
                    ? 'Only an admin or manager can approve or reopen work.'
                    : 'You cannot change the status of this task.'
            );
        }

        if ($to->requiresReason() && blank($note)) {
            throw ValidationException::withMessages([
                'note' => "Say why this is going to {$to->label()}.",
            ]);
        }

        return DB::transaction(function () use ($task, $from, $to, $actor, $note, $blockedByTaskId) {
            $task->forceFill($this->timestamps($task, $to) + [
                'status' => $to,
                'hold_reason' => in_array($to, [TaskStatus::OnHold, TaskStatus::Blocked], true) ? $note : null,
                'blocked_by_task_id' => $to === TaskStatus::Blocked ? $blockedByTaskId : null,
                'last_activity_at' => now(),
            ])->save();

            TaskStatusLog::create([
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);

            TaskMessage::create([
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'type' => 'status_change',
                'body' => $this->systemLine($actor, $from, $to, $note),
            ]);

            $this->notify($task, $to, $actor);

            TaskUpdated::dispatch($task->fresh(), $actor->id);

            return $task;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function timestamps(Task $task, TaskStatus $to): array
    {
        return match ($to) {
            TaskStatus::InProgress => ['started_at' => $task->started_at ?? now()],
            TaskStatus::Submitted => ['submitted_at' => now()],
            TaskStatus::Completed => ['completed_at' => now()],
            // Reopening is the quality signal: a count anyone can see.
            TaskStatus::Reopened => [
                'reopen_count' => $task->reopen_count + 1,
                'completed_at' => null,
                'submitted_at' => null,
            ],
            default => [],
        };
    }

    private function systemLine(User $actor, TaskStatus $from, TaskStatus $to, ?string $note): string
    {
        $line = match ($to) {
            TaskStatus::Submitted => "{$actor->firstName()} submitted this for review",
            TaskStatus::Completed => "{$actor->firstName()} approved and completed this",
            TaskStatus::Reopened => "{$actor->firstName()} sent this back",
            TaskStatus::OnHold => "{$actor->firstName()} put this on hold",
            TaskStatus::Blocked => "{$actor->firstName()} marked this blocked",
            default => "{$actor->firstName()} moved this from {$from->label()} to {$to->label()}",
        };

        return $note ? "{$line} — {$note}" : $line;
    }

    private function notify(Task $task, TaskStatus $to, User $actor): void
    {
        $recipients = collect();

        // Submitting is a request for someone's attention, so it goes to the
        // approvers rather than back to the person who just did the work.
        if ($to === TaskStatus::Submitted) {
            $recipients = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
                ->get();
        } elseif (in_array($to, [TaskStatus::Completed, TaskStatus::Reopened], true) && $task->assignee) {
            $recipients = collect([$task->assignee]);
        }

        $recipients
            ->reject(fn (User $user) => $user->id === $actor->id)
            ->each->notify(new TaskStatusChanged($task, $to, $actor));
    }
}
