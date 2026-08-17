<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\AssetStatus;
use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('components.layouts.app')]
#[Title('Home')]
class Dashboard extends Component
{
    public function renew(int $assetId): void
    {
        $asset = Asset::findOrFail($assetId);

        $this->authorize('update', $asset);

        $months = match ($asset->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'half-yearly' => 6,
            'biennial' => 24,
            default => 12,
        };

        $asset->forceFill([
            'expires_at' => $asset->expires_at->copy()->addMonthsNoOverflow($months),
            'status' => AssetStatus::Active,
        ])->save();

        // A new cycle needs a clean idempotency slate, or the next round of
        // reminders would be suppressed by this cycle's log rows.
        $asset->reminderLogs()->delete();

        $this->clearCache();

        $this->dispatch('toast', message: "{$asset->name} renewed to ".$asset->expires_at->format('j M Y').'.', tone: 'ok');
    }

    public function refreshData(): void
    {
        $this->clearCache();
    }

    public function render(): View
    {
        $user = auth()->user();
        $seesAssets = $user->can(Permissions::VIEW_ASSETS);

        return view('livewire.dashboard', [
            'metrics' => $this->metrics(),
            'expiring' => $this->expiring(),
            'myTasks' => $this->myTasks(),
            'statusBar' => $this->statusBar(),
            'renewalMonths' => $seesAssets ? $this->renewalsByMonth() : collect(),
            'workload' => $user->can(Permissions::VIEW_ALL_TASKS) ? $this->workload() : collect(),
            'activity' => $user->can(Permissions::VIEW_ACTIVITY_LOG) ? $this->activity() : collect(),
            'seesAssets' => $seesAssets,
        ]);
    }

    /**
     * Cached for a minute: this is the most-hit page in the app and none of
     * these numbers are worth a dozen aggregate queries per visit.
     *
     * @return array<string, int|float>
     */
    private function metrics(): array
    {
        $user = auth()->user();

        return Cache::remember("dashboard.metrics.{$user->id}", now()->addMinute(), function () use ($user): array {
            $today = Carbon::now(config('app.timezone'))->startOfDay();
            $seesAssets = $user->can(Permissions::VIEW_ASSETS);

            $watched = fn () => Asset::query()->active()->whereNotIn('status', ['renewed', 'cancelled']);

            $dueIn30 = $seesAssets
                ? (clone $watched())->where('expires_at', '<=', $today->copy()->addDays(30))->get(['cost'])
                : collect();

            return [
                'expiring7' => $seesAssets
                    ? (clone $watched())->whereBetween('expires_at', [$today, $today->copy()->addDays(7)])->count()
                    : 0,
                'expiring30' => $seesAssets
                    ? (clone $watched())->whereBetween('expires_at', [$today, $today->copy()->addDays(30)])->count()
                    : 0,
                'overdueAssets' => $seesAssets
                    ? (clone $watched())->where('expires_at', '<', $today)->count()
                    : 0,
                // What it costs if nobody acts. The number that gets a renewal
                // approved faster than a count ever does.
                'costAtRisk' => $seesAssets ? (float) $dueIn30->sum(fn ($a) => (float) $a->cost) : 0.0,

                'openTasks' => Task::query()->active()->visibleTo($user)->open()->count(),
                'overdueTasks' => Task::query()->active()->visibleTo($user)->overdue()->count(),
                'awaitingReview' => $user->can(Permissions::APPROVE_TASKS)
                    ? Task::query()->active()->awaitingReview()->count()
                    : Task::query()->active()->visibleTo($user)->awaitingReview()->count(),

                'clients' => $user->can(Permissions::VIEW_CLIENTS) ? Client::query()->active()->count() : 0,
                'projects' => $user->can(Permissions::VIEW_PROJECTS)
                    ? Project::query()->active()->whereIn('status', ['planning', 'active'])->count()
                    : 0,
            ];
        });
    }

    /** @return Collection<int, Asset> */
    private function expiring(): Collection
    {
        if (! auth()->user()->can(Permissions::VIEW_ASSETS)) {
            return collect();
        }

        return Asset::query()
            ->active()
            ->whereNotIn('status', ['renewed', 'cancelled'])
            ->where('expires_at', '<=', Carbon::now(config('app.timezone'))->addDays(30)->startOfDay())
            ->with(['client:id,name,company_name', 'owner:id,name'])
            ->orderBy('expires_at')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, Task> */
    private function myTasks(): Collection
    {
        return Task::query()
            ->active()
            ->where('assigned_to', auth()->id())
            ->whereNotIn('status', TaskStatus::closedValues())
            ->where(function ($q) {
                $q->whereNull('due_at')->orWhere('due_at', '<=', now()->endOfDay());
            })
            ->with(['client:id,name,company_name'])
            ->orderByRaw('due_at is null, due_at asc')
            ->limit(6)
            ->get();
    }

    /**
     * @return array{total: int, segments: list<array{status: TaskStatus, count: int, percent: float}>}
     */
    private function statusBar(): array
    {
        $counts = Task::query()
            ->active()
            ->visibleTo(auth()->user())
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();

        $segments = collect(TaskStatus::boardColumns())
            ->map(fn (TaskStatus $status) => [
                'status' => $status,
                'count' => (int) ($counts[$status->value] ?? 0),
                'percent' => $total > 0 ? round(((int) ($counts[$status->value] ?? 0)) / $total * 100, 1) : 0.0,
            ])
            ->filter(fn (array $segment) => $segment['count'] > 0)
            ->values()
            ->all();

        return ['total' => $total, 'segments' => $segments];
    }

    /**
     * Six months of renewals ahead, so a heavy month is visible before it lands
     * rather than in the week it arrives.
     *
     * @return Collection<int, array{label: string, count: int, cost: float}>
     */
    private function renewalsByMonth(): Collection
    {
        $start = Carbon::now(config('app.timezone'))->startOfMonth();

        $assets = Asset::query()
            ->active()
            ->whereNotIn('status', ['renewed', 'cancelled'])
            ->whereBetween('expires_at', [$start, $start->copy()->addMonths(6)])
            ->get(['expires_at', 'cost']);

        return collect(range(0, 5))->map(function (int $offset) use ($start, $assets) {
            $month = $start->copy()->addMonths($offset);
            $inMonth = $assets->filter(fn (Asset $a) => $a->expires_at->isSameMonth($month));

            return [
                'label' => $month->format('M'),
                'count' => $inMonth->count(),
                'cost' => (float) $inMonth->sum(fn (Asset $a) => (float) $a->cost),
            ];
        });
    }

    /**
     * Who is carrying what. One query for the counts, one for the people.
     *
     * @return Collection<int, array{user: User, open: int, overdue: int}>
     */
    private function workload(): Collection
    {
        $counts = Task::query()
            ->active()
            ->whereNotNull('assigned_to')
            ->whereNotIn('status', TaskStatus::closedValues())
            ->selectRaw('assigned_to, count(*) as open_count')
            ->selectRaw('sum(case when due_at is not null and due_at < ? then 1 else 0 end) as overdue_count', [now()])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        if ($counts->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $counts->keys())
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'user' => $user,
                'open' => (int) $counts[$user->id]->open_count,
                'overdue' => (int) $counts[$user->id]->overdue_count,
            ])
            ->sortByDesc('open')
            ->take(6)
            ->values();
    }

    /** @return Collection<int, Activity> */
    private function activity(): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->latest()
            ->limit(8)
            ->get();
    }

    private function clearCache(): void
    {
        Cache::forget('dashboard.metrics.'.auth()->id());
    }
}
