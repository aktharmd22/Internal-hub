<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Credential;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $strict = ! $this->app->isProduction();

        // N+1 queries and typo'd attributes fail loudly outside production, so
        // the test suite catches them instead of the live dashboard.
        Model::preventLazyLoading($strict);
        Model::preventSilentlyDiscardingAttributes($strict);
        Model::unguard(false);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Reminder logs and activity records store a morph type. Aliasing it
        // keeps class names out of the database, so a namespace change later
        // does not orphan every historical row.
        Relation::enforceMorphMap([
            'user' => User::class,
            'client' => Client::class,
            'asset' => Asset::class,
            'task' => Task::class,
            'task_message' => TaskMessage::class,
            'credential' => Credential::class,
            'project' => Project::class,
        ]);

        // Admins hold every permission implicitly. Returning null (not false)
        // lets the normal policy and permission checks run for everyone else.
        Gate::before(fn (User $user) => $user->hasRole(Role::Admin->value) ? true : null);
    }
}
