<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Permissions;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        // Everyone has a task list. What is in it is narrowed by scopeVisibleTo.
        return true;
    }

    /**
     * The definition of "my task", used by the policy, the list scope and the
     * broadcast channel check alike. If these three ever disagree, an employee
     * can read another client's private thread.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->can(Permissions::VIEW_ALL_TASKS) || $this->isParticipant($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_TASKS);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can(Permissions::MANAGE_TASKS) || $this->isParticipant($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can(Permissions::MANAGE_TASKS);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can(Permissions::MANAGE_TASKS);
    }

    /**
     * The review gate. An employee marks work `submitted`; only an approver
     * can turn that into `completed` or send it back as `reopened`.
     */
    public function approve(User $user, Task $task): bool
    {
        return $user->can(Permissions::APPROVE_TASKS);
    }

    public function transitionTo(User $user, Task $task, TaskStatus $status): bool
    {
        if (in_array($status, TaskStatus::approverOnly(), true)) {
            return $this->approve($user, $task);
        }

        return $this->update($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function trackTime(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task) || $user->can(Permissions::MANAGE_TASKS);
    }

    private function isParticipant(User $user, Task $task): bool
    {
        if ($task->assigned_to === $user->id || $task->created_by === $user->id) {
            return true;
        }

        // relationLoaded avoids an extra query on a list already eager-loaded.
        if ($task->relationLoaded('participants')) {
            return $task->participants->contains('id', $user->id);
        }

        return $task->participants()->whereKey($user->id)->exists();
    }
}
