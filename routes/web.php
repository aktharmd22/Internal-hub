<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\PushSubscriptionController;
use App\Livewire;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified', 'active'])->group(function () {

    Route::get('dashboard', Livewire\Dashboard::class)->name('dashboard');
    Route::view('more', 'more')->name('more');
    Route::view('profile', 'profile')->name('profile');

    Route::post('logout', LogoutController::class)->name('logout');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    /* ---------------------------------------------------------------- work */

    Route::get('tasks', Livewire\Tasks\Index::class)->name('tasks.index');
    Route::get('tasks/{task}', Livewire\Tasks\Show::class)->name('tasks.show');

    Route::get('chat', Livewire\Chat\Index::class)->name('chat.index');
    Route::get('notifications', Livewire\Notifications\Index::class)->name('notifications.index');

    /* -------------------------------------------------------------- assets */

    Route::middleware('can:'.Permissions::VIEW_ASSETS)->group(function () {
        Route::get('assets', Livewire\Assets\Index::class)->name('assets.index');
        Route::get('assets/import', Livewire\Assets\Import::class)->name('assets.import');
        Route::get('assets/{asset}', Livewire\Assets\Show::class)->name('assets.show');
    });

    Route::middleware('can:'.Permissions::VIEW_CLIENTS)->group(function () {
        Route::get('clients', Livewire\Clients\Index::class)->name('clients.index');
    });

    // An employee reaches a client through a task they are on, so this one is
    // guarded by the policy rather than a blanket permission.
    Route::get('clients/{client}', Livewire\Clients\Show::class)->name('clients.show');

    Route::middleware('can:'.Permissions::VIEW_PROJECTS)->group(function () {
        Route::get('projects', Livewire\Projects\Index::class)->name('projects.index');
    });

    Route::get('projects/{project}', Livewire\Projects\Show::class)->name('projects.show');

    /* --------------------------------------------------------------- admin */

    Route::middleware('can:'.Permissions::MANAGE_USERS)->group(function () {
        Route::get('team', Livewire\Team\Index::class)->name('team.index');
    });

    Route::middleware('can:'.Permissions::VIEW_REPORTS)->group(function () {
        Route::get('reports', Livewire\Reports\Index::class)->name('reports.index');
    });

    Route::middleware('can:'.Permissions::VIEW_CREDENTIALS)->group(function () {
        Route::get('vault', Livewire\Vault\Index::class)->name('vault.index');
    });

    Route::middleware('can:'.Permissions::MANAGE_SETTINGS)->group(function () {
        Route::get('settings', Livewire\Settings\Index::class)->name('settings.index');
    });

    // The design system reference. Never routed in production.
    if (! app()->isProduction()) {
        Route::view('kitchen-sink', 'kitchen-sink')->name('kitchen-sink');
    }
});

require __DIR__.'/auth.php';
