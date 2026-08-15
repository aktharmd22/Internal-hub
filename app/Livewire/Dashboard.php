<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\AssetStatus;
use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\Task;
use App\Support\Permissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

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

        return view('livewire.dashboard', [
            'metrics' => $this->metrics(),
            'expiring' => $this->expiring(),
            'myTasks' => $this->myTasks(),
            'statusBar' => $this->statusBar(),
            'canSeeAssets' => $user->can(Permissions::VIEW_ASSETS),
        ]);
    }

    /**
     * Aggregates are cached for a minute: the dashboard is the most-hit page
     * and none of these numbers are worth four count queries per visit.
     *
     * @return array<string, int>
     */
    private function metrics(): array
    {
        $user = auth()->user();

        return Cache::remember("dashboard.metrics.{$user->id}", now()->addMinute(), function () use ($user): array {
            $today = Carbon::now(config('app.timezone'))->startOfDay();

            $assets = fn () => Asset::query()->active()->whereNotIn('status', ['renewed', 'cancelled']);

            return [
                'expiring7' => $user->can(Permissions::VIEW_ASSETS)
                    ? $assets()->whereBetween('expires_at', [$today, $today->copy()->addDays(7)])->count()
                    : 0,
                'expiring30' => $user->can(Permissions::VIEW_ASSETS)
                    ? $assets()->whereBetween('expires_at', [$today, $today->copy()->addDays(30)])->count()
                    : 0,
                'openTasks' => Task::query()->active()->visibleTo($user)->open()->count(),
                'awaitingReview' => $user->can(Permissions::APPROVE_TASKS)
                    ? Task::query()->active()->awaitingReview()->count()
                    : Task::query()->active()->visibleTo($user)->awaitingReview()->count(),
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
            ->limit(6)
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
     * A single stacked bar of where the visible work sits.
     *
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

    private function clearCache(): void
    {
        Cache::forget('dashboard.metrics.'.auth()->id());
    }
}
