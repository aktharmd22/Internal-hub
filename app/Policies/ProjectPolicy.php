<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\Permissions;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_PROJECTS);
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->can(Permissions::VIEW_PROJECTS)) {
            return true;
        }

        // Reachable through a task the employee is working on.
        return $project->tasks()
            ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_PROJECTS);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can(Permissions::MANAGE_PROJECTS);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(Permissions::MANAGE_PROJECTS);
    }
}
