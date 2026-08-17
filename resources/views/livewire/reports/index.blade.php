@php
    $summary = $this->taskSummary();
    $maxRenewal = max(1, $renewals->max('count'));
    $maxThroughput = max(1, $throughput->max('created'), $throughput->max('completed'));
    $totalCost = $renewals->sum('cost');
@endphp

<div>
    @push('page-actions')
        <x-ui.button variant="secondary" size="sm" icon="upload" wire:click="exportRenewals">
            <span class="max-sm:sr-only">Export CSV</span>
        </x-ui.button>
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([
                ['Renewals ahead', $renewals->sum('count'), null],
                ['Committed spend', '₹'.number_format($totalCost), null],
                ['On-time completion', $summary['onTime'] === null ? '—' : $summary['onTime'].'%', $summary['onTime'] !== null && $summary['onTime'] < 70 ? 'warn' : null],
                ['Reopened', $summary['reopened'], $summary['reopened'] > 0 ? 'danger' : null],
            ] as [$label, $value, $tone])
                <x-ui.card>
                    <p class="t-metric {{ $tone ? App\Support\Tone::metric($tone) : 'text-ink-950' }}">{{ $value }}</p>
                    <p class="t-sub text-ink-600 mt-1 leading-tight">{{ $label }}</p>
                </x-ui.card>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- Bar charts in CSS: no library ships for two panels, and this stays
             readable in both themes. --}}
        <x-ui.card title="Renewals by month" subtitle="Count and cost, so a heavy month is visible before it arrives.">
            <div class="flex items-end gap-1.5 h-40 mt-4">
                @foreach ($renewals as $month)
                    <div class="flex-1 flex flex-col items-center gap-1.5 min-w-0" title="{{ $month['label'] }}: {{ $month['count'] }} · ₹{{ number_format($month['cost']) }}">
                        <span class="t-meta text-ink-400 tnum">{{ $month['count'] ?: '' }}</span>
                        <div
                            class="w-full rounded-t {{ $month['count'] > 0 ? 'bg-accent-500' : 'bg-ink-100' }}"
                            style="height: {{ max(2, (int) round($month['count'] / $maxRenewal * 100)) }}%"
                        ></div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-1.5 mt-2">
                @foreach ($renewals as $month)
                    <span class="flex-1 t-meta text-ink-400 text-center truncate">{{ $month['label'] }}</span>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Task throughput" subtitle="Created against completed, by week.">
            <div class="flex items-end gap-2 h-32 mt-4">
                @foreach ($throughput as $week)
                    <div class="flex-1 flex items-end justify-center gap-0.5 h-full min-w-0">
                        <div class="w-1/2 rounded-t bg-ink-200" style="height: {{ max(2, (int) round($week['created'] / $maxThroughput * 100)) }}%" title="Created: {{ $week['created'] }}"></div>
                        <div class="w-1/2 rounded-t bg-ok-600" style="height: {{ max(2, (int) round($week['completed'] / $maxThroughput * 100)) }}%" title="Completed: {{ $week['completed'] }}"></div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-2 mt-2">
                @foreach ($throughput as $week)
                    <span class="flex-1 t-meta text-ink-400 text-center truncate">{{ $week['label'] }}</span>
                @endforeach
            </div>

            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-ink-100">
                <span class="flex items-center gap-1.5 t-meta text-ink-600"><span class="size-2 rounded-full bg-ink-200"></span>Created</span>
                <span class="flex items-center gap-1.5 t-meta text-ink-600"><span class="size-2 rounded-full bg-ok-600"></span>Completed</span>
            </div>
        </x-ui.card>

        </div>

        <x-ui.card title="Assets by type" :padding="false" :flush="true">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-ink-100">
                        <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5">Type</th>
                        <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5 text-right">Count</th>
                        <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5 text-right">Annual cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($byType as $row)
                        <tr>
                            <td class="px-4 py-2.5 t-sub text-ink-950">{{ $row['label'] }}</td>
                            <td class="px-4 py-2.5 t-sub text-ink-600 text-right tnum">{{ $row['count'] }}</td>
                            <td class="px-4 py-2.5 t-sub text-ink-600 text-right tnum">₹{{ number_format($row['cost']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </div>
</div>
