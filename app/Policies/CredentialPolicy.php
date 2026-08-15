<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Credential;
use App\Models\User;
use App\Support\Permissions;

/**
 * The vault is admin-only. Note there is no permission on the manager role for
 * this — a manager runs the agency but does not hold its clients' passwords.
 */
class CredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::VIEW_CREDENTIALS);
    }

    public function view(User $user, Credential $credential): bool
    {
        return $user->can(Permissions::VIEW_CREDENTIALS);
    }

    /**
     * Separate from `view`: seeing that a credential exists is not the same as
     * reading the secret, and only the reveal is worth logging.
     */
    public function reveal(User $user, Credential $credential): bool
    {
        return $user->can(Permissions::VIEW_CREDENTIALS);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::MANAGE_CREDENTIALS);
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->can(Permissions::MANAGE_CREDENTIALS);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->can(Permissions::MANAGE_CREDENTIALS);
    }
}
