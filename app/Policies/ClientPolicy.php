<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Support\Permissions;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_CLIENTS);
    }

    /**
     * An employee has no client list, but they can open the client behind a
     * task they are working on — otherwise the task is context-free.
     */
    public function view(User $user, Client $client): bool
    {
        if ($user->can(Permissions::VIEW_CLIENTS)) {
            return true;
        }

        return $client->tasks()
            ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_CLIENTS);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can(Permissions::MANAGE_CLIENTS);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can(Permissions::MANAGE_CLIENTS);
    }
}
