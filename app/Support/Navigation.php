<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single definition of the app's navigation.
 *
 * The bottom tab bar (mobile) and the sidebar (lg and up) both read from here so
 * they can never drift apart, and both are filtered by permission. Hiding a link
 * is presentation only — every destination is also guarded by middleware and,
 * from Phase 2, by a policy.
 */
final class Navigation
{
    /**
     * Bottom tab bar. Capped at five entries; "More" carries the overflow.
     *
     * @return Collection<int, array{label: string, route: string, icon: string, active: string}>
     */
    public static function tabs(?User $user): Collection
    {
        return self::filter($user, [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'house', 'active' => 'dashboard'],
            ['label' => 'Assets', 'route' => 'assets.index', 'icon' => 'globe', 'active' => 'assets.*', 'permission' => 'view_assets'],
            ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'list-checks', 'active' => 'tasks.*'],
            ['label' => 'Chat', 'route' => 'chat.index', 'icon' => 'message-circle', 'active' => 'chat.*'],
            ['label' => 'More', 'route' => 'more', 'icon' => 'menu', 'active' => 'more'],
        ]);
    }

    /**
     * Sidebar groups, shown from `lg:` up.
     *
     * @return Collection<int, array{heading: ?string, items: Collection<int, array<string, mixed>>}>
     */
    public static function sidebar(?User $user): Collection
    {
        $groups = [
            [
                'heading' => null,
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'house', 'active' => 'dashboard'],
                    ['label' => 'Tasks', 'route' => 'tasks.index', 'icon' => 'list-checks', 'active' => 'tasks.*'],
                    ['label' => 'Chat', 'route' => 'chat.index', 'icon' => 'message-circle', 'active' => 'chat.*'],
                    ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell', 'active' => 'notifications.*'],
                ],
            ],
            [
                'heading' => 'Accounts',
                'items' => [
                    ['label' => 'Assets', 'route' => 'assets.index', 'icon' => 'globe', 'active' => 'assets.*', 'permission' => 'view_assets'],
                    ['label' => 'Clients', 'route' => 'clients.index', 'icon' => 'building-2', 'active' => 'clients.*', 'permission' => 'view_clients'],
                    ['label' => 'Projects', 'route' => 'projects.index', 'icon' => 'folder-kanban', 'active' => 'projects.*', 'permission' => 'view_projects'],
                ],
            ],
            [
                'heading' => 'Admin',
                'items' => [
                    ['label' => 'Team', 'route' => 'team.index', 'icon' => 'users', 'active' => 'team.*', 'permission' => 'manage_users'],
                    ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart-column', 'active' => 'reports.*', 'permission' => 'view_reports'],
                    ['label' => 'Vault', 'route' => 'vault.index', 'icon' => 'key-round', 'active' => 'vault.*', 'permission' => 'view_credentials'],
                    ['label' => 'Settings', 'route' => 'settings.index', 'icon' => 'settings', 'active' => 'settings.*', 'permission' => 'manage_settings'],
                ],
            ],
        ];

        return collect($groups)
            ->map(fn (array $group) => [
                'heading' => $group['heading'],
                'items' => self::filter($user, $group['items']),
            ])
            ->reject(fn (array $group) => $group['items']->isEmpty())
            ->values();
    }

    /**
     * Everything the bottom tab bar could not fit, for the "More" screen.
     *
     * @return Collection<int, array{heading: ?string, items: Collection<int, array<string, mixed>>}>
     */
    public static function overflow(?User $user): Collection
    {
        $inTabs = self::tabs($user)->pluck('route')->all();

        return self::sidebar($user)
            ->map(fn (array $group) => [
                'heading' => $group['heading'],
                'items' => $group['items']->reject(fn (array $item) => in_array($item['route'], $inTabs, true))->values(),
            ])
            ->reject(fn (array $group) => $group['items']->isEmpty())
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private static function filter(?User $user, array $items): Collection
    {
        return collect($items)
            ->filter(function (array $item) use ($user): bool {
                if (! isset($item['permission'])) {
                    return true;
                }

                return $user?->can($item['permission']) ?? false;
            })
            ->values();
    }
}
