<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Services\Healthcheck;
use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SendDailyDigest extends Command
{
    protected $signature = 'reports:daily-digest {--force : Send even to users with nothing outstanding}';

    protected $description = 'Email each user their day: due today, overdue, closed yesterday, awaiting review';

    public function handle(Healthcheck $healthcheck): int
    {
        $sent = 0;

        User::query()
            ->where('is_active', true)
            ->with('roles')
            ->get()
            ->each(function (User $user) use (&$sent) {
                $overdue = $this->tasksFor($user)->overdue()->get();

                // A task due at 17:00, read at 22:10, is overdue — not "due
                // today". Without this the same task appears twice in one
                // email, under two headings that contradict each other.
                $dueToday = $this->tasksFor($user)
                    ->dueToday()
                    ->whereIntegerNotInRaw('id', $overdue->modelKeys() ?: [0])
                    ->get();

                $completedYesterday = $this->tasksFor($user)
                    ->where('status', TaskStatus::Completed)
                    ->whereBetween('completed_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
                    ->get();

                // Only approvers have a review queue.
                $awaitingReview = $user->can(Permissions::APPROVE_TASKS)
                    ? Task::query()->active()->awaitingReview()->with(['client', 'assignee'])->get()
                    : new Collection;

                $expiringSoon = $user->can(Permissions::VIEW_ASSETS)
                    ? Asset::query()->watched()->expiringWithin(7)->with('client')->orderBy('expires_at')->get()
                    : new Collection;

                $hasSomething = $dueToday->isNotEmpty()
                    || $overdue->isNotEmpty()
                    || $awaitingReview->isNotEmpty()
                    || $expiringSoon->isNotEmpty();

                // A daily email that says "nothing today" every day is how a
                // digest teaches people to ignore it.
                if (! $hasSomething && ! $this->option('force')) {
                    return;
                }

                $user->notify(new DailyDigest(
                    $dueToday,
                    $overdue,
                    $completedYesterday,
                    $awaitingReview,
                    $expiringSoon,
                ));

                $sent++;
            });

        $this->info("Daily digest queued for {$sent} user(s).");

        $healthcheck->ping();

        return self::SUCCESS;
    }

    private function tasksFor(User $user): Builder
    {
        return Task::query()
            ->active()
            ->where('assigned_to', $user->id)
            ->with(['client', 'project']);
    }
}
