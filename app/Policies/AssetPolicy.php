<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Support\Permissions;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_ASSETS);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can(Permissions::VIEW_ASSETS) || $asset->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_ASSETS);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can(Permissions::MANAGE_ASSETS);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can(Permissions::MANAGE_ASSETS);
    }

    public function import(User $user): bool
    {
        return $user->can(Permissions::MANAGE_ASSETS);
    }
}
