<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('more', 'more')->name('more');
    Route::view('profile', 'profile')->name('profile');

    Route::post('logout', LogoutController::class)->name('logout');

    /*
     * Destinations that complete the shell. Each one is replaced by its real
     * screen in the phase named on the page, and the route name never changes,
     * so navigation and tests stay stable across phases.
     */
    Route::view('tasks', 'placeholder', [
        'title' => 'Tasks',
        'icon' => 'list-checks',
        'headline' => 'Task tracking lands in phase 3',
        'body' => 'Projects, assignments and the submit-for-review flow are built next.',
    ])->name('tasks.index');

    Route::view('chat', 'placeholder', [
        'title' => 'Chat',
        'icon' => 'message-circle',
        'headline' => 'Task chat lands in phase 4',
        'body' => 'Every task becomes a thread, with attachments, voice notes and live updates.',
    ])->name('chat.index');

    Route::view('notifications', 'placeholder', [
        'title' => 'Notifications',
        'icon' => 'bell',
        'headline' => 'No notifications yet',
        'body' => 'Renewal alerts start arriving once assets are added in phase 2.',
    ])->name('notifications.index');

    Route::middleware('can:'.Permissions::VIEW_ASSETS)->group(function () {
        Route::view('assets', 'placeholder', [
            'title' => 'Assets',
            'icon' => 'globe',
            'headline' => 'Assets land in phase 2',
            'body' => 'Domains, hosting, SSL and licences, with the reminder engine behind them.',
        ])->name('assets.index');
    });

    Route::middleware('can:'.Permissions::VIEW_CLIENTS)->group(function () {
        Route::view('clients', 'placeholder', [
            'title' => 'Clients',
            'icon' => 'building-2',
            'headline' => 'Clients land in phase 2',
            'body' => 'Accounts, contacts and everything the agency holds for them.',
        ])->name('clients.index');
    });

    Route::middleware('can:'.Permissions::VIEW_PROJECTS)->group(function () {
        Route::view('projects', 'placeholder', [
            'title' => 'Projects',
            'icon' => 'folder-kanban',
            'headline' => 'Projects land in phase 3',
            'body' => 'Group client work, track progress and see what is at risk.',
        ])->name('projects.index');
    });

    Route::middleware('can:'.Permissions::MANAGE_USERS)->group(function () {
        Route::view('team', 'placeholder', [
            'title' => 'Team',
            'icon' => 'users',
            'headline' => 'Team management lands in phase 5',
            'body' => 'Accounts, workload and the per-employee scorecard.',
        ])->name('team.index');
    });

    Route::middleware('can:'.Permissions::VIEW_REPORTS)->group(function () {
        Route::view('reports', 'placeholder', [
            'title' => 'Reports',
            'icon' => 'chart-column',
            'headline' => 'Reports land in phase 5',
            'body' => 'Renewals by month with cost totals, task throughput and CSV export.',
        ])->name('reports.index');
    });

    Route::middleware('can:'.Permissions::VIEW_CREDENTIALS)->group(function () {
        Route::view('vault', 'placeholder', [
            'title' => 'Vault',
            'icon' => 'key-round',
            'headline' => 'The credential vault lands in phase 5',
            'body' => 'Encrypted client credentials with an access log on every read.',
        ])->name('vault.index');
    });

    Route::middleware('can:'.Permissions::MANAGE_SETTINGS)->group(function () {
        Route::view('settings', 'placeholder', [
            'title' => 'Settings',
            'icon' => 'settings',
            'headline' => 'Settings land in phase 2',
            'body' => 'Company profile, reminder rules, channel credentials and the healthcheck URL.',
        ])->name('settings.index');
    });

    // The design system reference. Never routed in production.
    if (! app()->isProduction()) {
        Route::view('kitchen-sink', 'kitchen-sink')->name('kitchen-sink');
    }
});

require __DIR__.'/auth.php';
