<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\User;
use App\Notifications\WeeklyRenewalDigest;
use App\Services\Healthcheck;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendWeeklyDigest extends Command
{
    protected $signature = 'reports:weekly-digest';

    protected $description = 'Email admins and managers everything falling due in the next 45 days';

    public function handle(Healthcheck $healthcheck): int
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $assets = Asset::query()
            ->active()
            ->whereNotIn('status', ['renewed', 'cancelled'])
            ->whereBetween('expires_at', [$today->copy()->subDays(30), $today->copy()->addDays(45)])
            ->with('client')
            ->orderBy('expires_at')
            ->get();

        $overdue = $assets->filter(fn (Asset $asset) => $asset->expires_at->lt($today));
        $upcoming = $assets->reject(fn (Asset $asset) => $asset->expires_at->lt($today));

        // Grouping by week is what turns a flat list into a plan: "this week"
        // reads as work, "in 45 days" reads as noise.
        $weeks = $upcoming->groupBy(function (Asset $asset) use ($today) {
            $weeksOut = (int) floor($today->diffInDays($asset->expires_at, false) / 7);

            return match (true) {
                $weeksOut <= 0 => 'This week',
                $weeksOut === 1 => 'Next week',
                default => 'Week of '.$asset->expires_at->copy()->startOfWeek()->format('j M'),
            };
        });

        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::Admin->value, Role::Manager->value]))
            ->get();

        $notification = new WeeklyRenewalDigest(
            weeks: $weeks,
            total: $upcoming->count(),
            cost: (float) $upcoming->sum(fn (Asset $asset) => (float) $asset->cost),
            overdue: $overdue->count(),
        );

        $recipients->each->notify($notification);

        $this->info("Weekly digest queued for {$recipients->count()} recipient(s): {$upcoming->count()} upcoming, {$overdue->count()} overdue.");

        $healthcheck->ping();

        return self::SUCCESS;
    }
}
