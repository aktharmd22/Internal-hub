<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\Task;
use App\Support\Permissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    #[Url(except: '12')]
    public string $months = '12';

    public function mount(): void
    {
        abort_unless(auth()->user()->can(Permissions::VIEW_REPORTS), 403);
    }

    /**
     * Streamed rather than built in memory: a year of renewals on a shared
     * host with a modest memory limit should never be an array first.
     */
    public function exportRenewals(): StreamedResponse
    {
        abort_unless(auth()->user()->can(Permissions::VIEW_REPORTS), 403);

        $filename = 'renewals-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');

            fputcsv($out, ['Asset', 'Type', 'Client', 'Provider', 'Owner', 'Expires', 'Cost', 'Currency', 'Status']);

            Asset::query()
                ->active()
                ->with(['client:id,name,company_name', 'owner:id,name'])
                ->orderBy('expires_at')
                ->chunk(200, function (Collection $assets) use ($out) {
                    foreach ($assets as $asset) {
                        fputcsv($out, [
                            $asset->name,
                            $asset->type->label(),
                            $asset->client->displayName(),
                            $asset->provider,
                            $asset->owner?->name,
                            $asset->expires_at->toDateString(),
                            $asset->cost,
                            $asset->currency,
                            $asset->status->label(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        return view('livewire.reports.index', [
            'renewals' => $this->renewalsByMonth(),
            'throughput' => $this->throughput(),
            'byType' => $this->byType(),
        ]);
    }

    /**
     * @return Collection<int, array{label: string, count: int, cost: float}>
     */
    private function renewalsByMonth(): Collection
    {
        $start = Carbon::now(config('app.timezone'))->startOfMonth();
        $end = $start->copy()->addMonths((int) $this->months);

        $assets = Asset::query()
            ->active()
            ->whereBetween('expires_at', [$start, $end])
            ->get(['expires_at', 'cost']);

        return collect(range(0, (int) $this->months - 1))->map(function (int $offset) use ($start, $assets) {
            $month = $start->copy()->addMonths($offset);

            $inMonth = $assets->filter(fn (Asset $asset) => $asset->expires_at->isSameMonth($month));

            return [
                'label' => $month->format('M y'),
                'count' => $inMonth->count(),
                'cost' => (float) $inMonth->sum(fn (Asset $asset) => (float) $asset->cost),
            ];
        });
    }

    /**
     * @return Collection<int, array{label: string, created: int, completed: int}>
     */
    private function throughput(): Collection
    {
        $start = Carbon::now(config('app.timezone'))->startOfWeek()->subWeeks(7);

        $created = Task::query()->where('created_at', '>=', $start)->get(['created_at']);
        $completed = Task::query()->where('completed_at', '>=', $start)->get(['completed_at']);

        return collect(range(0, 7))->map(function (int $offset) use ($start, $created, $completed) {
            $week = $start->copy()->addWeeks($offset);

            return [
                'label' => $week->format('j M'),
                'created' => $created->filter(fn ($t) => $t->created_at->isSameWeek($week))->count(),
                'completed' => $completed->filter(fn ($t) => $t->completed_at?->isSameWeek($week))->count(),
            ];
        });
    }

    /** @return Collection<int, array{label: string, count: int, cost: float}> */
    private function byType(): Collection
    {
        return Asset::query()
            ->active()
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('type, count(*) as aggregate, coalesce(sum(cost), 0) as total_cost')
            ->groupBy('type')
            ->get()
            // `type` is already cast to the enum by the model, so it must not
            // be passed back through AssetType::from().
            ->map(fn ($row) => [
                'label' => $row->type->label(),
                'count' => (int) $row->aggregate,
                'cost' => (float) $row->total_cost,
            ])
            ->sortByDesc('count')
            ->values();
    }

    public function taskSummary(): array
    {
        $tasks = Task::query()->active()->get(['status', 'reopen_count', 'due_at', 'completed_at']);

        $completed = $tasks->where('status', TaskStatus::Completed);
        $withDue = $completed->filter(fn (Task $t) => $t->due_at !== null);

        return [
            'open' => $tasks->reject(fn (Task $t) => $t->status->isClosed())->count(),
            'completed' => $completed->count(),
            'onTime' => $withDue->isEmpty()
                ? null
                : (int) round($withDue->filter(fn (Task $t) => $t->completed_at <= $t->due_at)->count() / $withDue->count() * 100),
            'reopened' => (int) $tasks->sum('reopen_count'),
        ];
    }
}
