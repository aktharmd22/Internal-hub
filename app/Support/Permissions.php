<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;

/**
 * The permission catalogue and the role that grants each one.
 *
 * This is the source of truth for the seeder, the navigation filter and the
 * tests. Admins are additionally granted everything through a `Gate::before`
 * check, so they never need to appear in the matrix below.
 */
final class Permissions
{
    public const VIEW_CLIENTS = 'view_clients';

    public const MANAGE_CLIENTS = 'manage_clients';

    public const VIEW_ASSETS = 'view_assets';

    public const MANAGE_ASSETS = 'manage_assets';

    public const VIEW_PROJECTS = 'view_projects';

    public const MANAGE_PROJECTS = 'manage_projects';

    public const VIEW_ALL_TASKS = 'view_all_tasks';

    public const MANAGE_TASKS = 'manage_tasks';

    public const APPROVE_TASKS = 'approve_tasks';

    public const VIEW_REPORTS = 'view_reports';

    public const VIEW_ACTIVITY_LOG = 'view_activity_log';

    public const MANAGE_USERS = 'manage_users';

    public const MANAGE_SETTINGS = 'manage_settings';

    public const VIEW_CREDENTIALS = 'view_credentials';

    public const MANAGE_CREDENTIALS = 'manage_credentials';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_CLIENTS,
            self::MANAGE_CLIENTS,
            self::VIEW_ASSETS,
            self::MANAGE_ASSETS,
            self::VIEW_PROJECTS,
            self::MANAGE_PROJECTS,
            self::VIEW_ALL_TASKS,
            self::MANAGE_TASKS,
            self::APPROVE_TASKS,
            self::VIEW_REPORTS,
            self::VIEW_ACTIVITY_LOG,
            self::MANAGE_USERS,
            self::MANAGE_SETTINGS,
            self::VIEW_CREDENTIALS,
            self::MANAGE_CREDENTIALS,
        ];
    }

    /** @return list<string> */
    public static function for(Role $role): array
    {
        return match ($role) {
            Role::Admin => self::all(),

            // A manager runs the agency day to day but cannot touch accounts,
            // system settings or stored client passwords.
            Role::Manager => [
                self::VIEW_CLIENTS,
                self::MANAGE_CLIENTS,
                self::VIEW_ASSETS,
                self::MANAGE_ASSETS,
                self::VIEW_PROJECTS,
                self::MANAGE_PROJECTS,
                self::VIEW_ALL_TASKS,
                self::MANAGE_TASKS,
                self::APPROVE_TASKS,
                self::VIEW_REPORTS,
                self::VIEW_ACTIVITY_LOG,
            ],

            // An employee holds no blanket permissions. Their access to a task
            // comes from being its assignee or a participant, checked by policy.
            Role::Employee => [],
        };
    }
}
