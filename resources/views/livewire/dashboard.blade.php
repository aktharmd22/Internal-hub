@php
    use App\Support\Tone;

    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 12 => 'Good morning',
        $hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };

    // Only cards the user can act on. An employee has no asset list, so a
    // count of renewals they cannot open is noise.
    $cards = array_values(array_filter([
        $seesAssets ? [
            'label' => 'Overdue', 'value' => $metrics['overdueAssets'], 'tone' => 'danger', 'icon' => 'triangle-alert',
            'href' => route('assets.index', ['window' => 'expired']), 'hint' => 'assets past their date',
        ] : null,
        $seesAssets ? [
            'label' => 'Next 7 days', 'value' => $metrics['expiring7'], 'tone' => 'danger', 'icon' => 'calendar-clock',
            'href' => route('assets.index', ['window' => '7']), 'hint' => 'renewals due',
        ] : null,
        $seesAssets ? [
            'label' => 'Next 30 days', 'value' => $metrics['expiring30'], 'tone' => 'warn', 'icon' => 'globe',
            'href' => route('assets.index', ['window' => '30']), 'hint' => 'renewals due',
        ] : null,
        $seesAssets ? [
            'label' => 'Cost at risk', 'value' => '₹'.number_format($metrics['costAtRisk']), 'tone' => 'neutral', 'icon' => 'chart-column',
            'href' => route('assets.index', ['window' => '30']), 'hint' => 'due in 30 days',
        ] : null,
        [
            'label' => 'Overdue tasks', 'value' => $metrics['overdueTasks'], 'tone' => 'danger', 'icon' => 'circle-alert',
            'href' => route('tasks.index', ['filter' => 'overdue']), 'hint' => 'past the due date',
        ],
        [
            'label' => 'Open tasks', 'value' => $metrics['openTasks'], 'tone' => 'neutral', 'icon' => 'list-checks',
            'href' => route('tasks.index', ['filter' => 'mine']), 'hint' => 'assigned to you',
        ],
        [
            'label' => 'Awaiting review', 'value' => $metrics['awaitingReview'], 'tone' => 'accent', 'icon' => 'circle-check',
            'href' => route('tasks.index', ['filter' => 'review']), 'hint' => 'submitted for approval',
        ],
    ]));

    $maxRenewal = max(1, $renewalMonths->max('count') ?? 1);

    // Written out in full: an interpolated `xl:grid-cols-{$n}` is a class
    // Tailwind never generates, and the strip would silently collapse to one
    // column in production.
    $stripColumns = match (count($cards)) {
        3 => 'xl:grid-cols-3',
        4 => 'xl:grid-cols-4',
        5 => 'xl:grid-cols-5',
        6 => 'xl:grid-cols-6',
        default => 'xl:grid-cols-7',
    };
@endphp

<div
    x-data="pullToRefresh(() => $wire.refreshData())"
    x-on:touchstart.passive="onStart($event)"
    x-on:touchmove.passive="onMove($event)"
    x-on:touchend.passive="onEnd()"
>
    <div
        class="lg:hidden grid place-items-center overflow-hidden transition-[height] duration-150"
        x-bind:style="`height: ${pull}px`"
        aria-hidden="true"
    >
        <x-icon
            name="refresh-cw"
            class="size-4 text-ink-400"
            x-bind:class="refreshing && 'animate-spin'"
            x-bind:style="`transform: rotate(${pull * 2}deg)`"
        />
    </div>

    <div class="px-4 lg:px-6 py-4 lg:py-5 flex flex-col gap-4">

        {{-- Greeting ------------------------------------------------------ --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="t-page-title text-ink-950">{{ $greeting }}, {{ auth()->user()->firstName() }}</p>
                <p class="t-sub text-ink-600 mt-1">
                    {{ now()->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('l, j F') }}
                    @if ($metrics['overdueAssets'] > 0)
                        · <span class="text-danger-600 font-medium">{{ $metrics['overdueAssets'] }} already overdue</span>
                    @elseif ($metrics['expiring7'] > 0)
                        · <span class="text-warn-600 font-medium">{{ $metrics['expiring7'] }} {{ str('renewal')->plural($metrics['expiring7']) }} this week</span>
                    @else
                        · nothing urgent today
                    @endif
                </p>
            </div>

            @can('create', App\Models\Asset::class)
                <div class="flex gap-2">
                    <x-ui.button variant="secondary" size="sm" icon="upload" :href="route('assets.import')" wire:navigate>
                        Import
                    </x-ui.button>
                    <x-ui.button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'asset-form')">
                        Add asset
                    </x-ui.button>
                </div>
            @endcan
        </div>

        {{-- Metric strip -------------------------------------------------- --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 {{ $stripColumns }} gap-3">
            @foreach ($cards as $card)
                <a
                    href="{{ $card['href'] }}"
                    wire:navigate
                    class="group relative bg-surface border border-ink-100 rounded-card p-4 hover:border-ink-200 transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="t-metric {{ is_numeric($card['value']) && $card['value'] > 0 ? Tone::metric($card['tone']) : 'text-ink-950' }}">
                            {{ $card['value'] }}
                        </p>
                        <x-icon :name="$card['icon']" class="size-4 text-ink-200 group-hover:text-ink-400 transition-colors shrink-0 mt-1" />
                    </div>
                    <p class="t-sub text-ink-950 mt-1.5 leading-tight">{{ $card['label'] }}</p>
                    <p class="t-meta text-ink-400 mt-0.5">{{ $card['hint'] }}</p>
                </a>
            @endforeach
        </div>

        {{-- Main grid ------------------------------------------------------ --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">

            {{-- Primary column -------------------------------------------- --}}
            <div class="xl:col-span-2 flex flex-col gap-4">

                @if ($seesAssets)
                    <x-ui.card title="Expiring soon" subtitle="The next thirty days, soonest first." :padding="false" :flush="true">
                        <x-slot:action>
                            <x-ui.button :href="route('assets.index')" variant="ghost" size="sm" iconTrailing="chevron-right" wire:navigate>
                                All assets
                            </x-ui.button>
                        </x-slot:action>

                        @if ($expiring->isEmpty())
                            <x-ui.empty-state
                                icon="calendar-clock"
                                headline="No renewals due in the next 30 days"
                                body="Everything on the books is paid up."
                            >
                                @can('create', App\Models\Asset::class)
                                    <x-ui.button variant="secondary" icon="plus" x-on:click="$dispatch('open-modal', 'asset-form')">
                                        Add an asset
                                    </x-ui.button>
                                @endcan
                            </x-ui.empty-state>
                        @else
                            <div class="divide-y divide-ink-100">
                                @foreach ($expiring as $asset)
                                    <div wire:key="exp-{{ $asset->id }}" class="flex items-center gap-3 px-4 min-h-16 hover:bg-surface-2 transition-colors">
                                        <span class="shrink-0 grid place-items-center size-9 rounded-control bg-surface-2 text-ink-600">
                                            <x-icon :name="$asset->type->icon()" class="size-[18px]" />
                                        </span>

                                        <a href="{{ route('assets.show', $asset) }}" wire:navigate class="min-w-0 flex-1 py-2.5">
                                            <span class="block t-body font-medium text-ink-950 truncate">{{ $asset->name }}</span>
                                            <span class="block t-sub text-ink-600 truncate mt-0.5">
                                                {{ $asset->client->displayName() }} · {{ $asset->type->label() }}
                                                @if ($asset->owner) · {{ $asset->owner->firstName() }} @endif
                                            </span>
                                        </a>

                                        <div class="shrink-0 flex items-center gap-2">
                                            <span class="hidden sm:block t-meta text-ink-400 tnum">{{ $asset->expires_at->format('j M') }}</span>
                                            <x-ui.badge :tone="$asset->urgencyTone()" dot>{{ $asset->urgencyLabel() }}</x-ui.badge>

                                            @can('update', $asset)
                                                <x-ui.button
                                                    size="sm"
                                                    variant="secondary"
                                                    wire:click="renew({{ $asset->id }})"
                                                    wire:target="renew({{ $asset->id }})"
                                                    wire:loading.attr="disabled"
                                                >Renew</x-ui.button>
                                            @endcan
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.card>
                @endif

                <x-ui.card title="Your tasks" subtitle="Due today, overdue, or with no date set." :padding="false" :flush="true">
                    <x-slot:action>
                        <x-ui.button :href="route('tasks.index', ['filter' => 'mine'])" variant="ghost" size="sm" iconTrailing="chevron-right" wire:navigate>
                            All tasks
                        </x-ui.button>
                    </x-slot:action>

                    @if ($myTasks->isEmpty())
                        <x-ui.empty-state
                            icon="list-checks"
                            headline="Nothing due today"
                            body="Work assigned to you shows here as its due date arrives."
                        />
                    @else
                        <div class="divide-y divide-ink-100">
                            @foreach ($myTasks as $task)
                                <x-ui.list-row
                                    wire:key="mt-{{ $task->id }}"
                                    :href="route('tasks.show', $task)"
                                    wire:navigate
                                >
                                    <x-slot:body>
                                        <p class="t-body font-medium text-ink-950 truncate">{{ $task->title }}</p>
                                        <p class="t-sub text-ink-600 truncate mt-0.5">
                                            <span class="tnum">{{ $task->reference }}</span>
                                            @if ($task->client) · {{ $task->client->displayName() }} @endif
                                        </p>
                                    </x-slot:body>

                                    <x-slot:trailing>
                                        @if ($task->dueLabel())
                                            <x-ui.badge :tone="$task->dueTone()" dot>{{ $task->dueLabel() }}</x-ui.badge>
                                        @else
                                            <x-ui.badge :tone="$task->status->tone()">{{ $task->status->label() }}</x-ui.badge>
                                        @endif
                                    </x-slot:trailing>
                                </x-ui.list-row>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>

            {{-- Secondary column ------------------------------------------- --}}
            <div class="flex flex-col gap-4">

                @if ($seesAssets && $renewalMonths->isNotEmpty())
                    <x-ui.card title="Renewals ahead" subtitle="Six months, so a heavy month is visible before it lands.">
                        <div class="flex items-end gap-2 h-28 mt-4">
                            @foreach ($renewalMonths as $month)
                                <div class="flex-1 flex flex-col items-center gap-1.5 min-w-0"
                                     title="{{ $month['label'] }}: {{ $month['count'] }} · ₹{{ number_format($month['cost']) }}">
                                    <span class="t-meta text-ink-400 tnum">{{ $month['count'] ?: '' }}</span>
                                    <div
                                        class="w-full rounded-t {{ $month['count'] > 0 ? 'bg-accent-500' : 'bg-ink-100' }}"
                                        style="height: {{ max(3, (int) round($month['count'] / $maxRenewal * 100)) }}%"
                                    ></div>
                                    <span class="t-meta text-ink-400">{{ $month['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif

                @if ($statusBar['total'] > 0)
                    <x-ui.card title="Where the work sits" :subtitle="$statusBar['total'].' '.str('task')->plural($statusBar['total']).' you can see'">
                        <div class="flex h-2.5 rounded-full overflow-hidden bg-ink-100 mt-3 gap-px">
                            @foreach ($statusBar['segments'] as $segment)
                                <div
                                    class="{{ Tone::fill($segment['status']->tone()) }}"
                                    style="width: {{ $segment['percent'] }}%"
                                    title="{{ $segment['status']->label() }}: {{ $segment['count'] }}"
                                ></div>
                            @endforeach
                        </div>

                        <ul class="flex flex-col gap-1.5 mt-3.5">
                            @foreach ($statusBar['segments'] as $segment)
                                <li class="flex items-center gap-2">
                                    <span class="size-2 rounded-full shrink-0 {{ Tone::fill($segment['status']->tone()) }}"></span>
                                    <span class="t-sub text-ink-600 flex-1 truncate">{{ $segment['status']->label() }}</span>
                                    <span class="t-sub text-ink-950 tnum font-medium">{{ $segment['count'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                @if ($workload->isNotEmpty())
                    <x-ui.card title="Who is carrying what" :padding="false" :flush="true">
                        <x-slot:action>
                            @can(App\Support\Permissions::MANAGE_USERS)
                                <x-ui.button :href="route('team.index')" variant="ghost" size="sm" iconTrailing="chevron-right" wire:navigate>
                                    Team
                                </x-ui.button>
                            @endcan
                        </x-slot:action>

                        <ul class="divide-y divide-ink-100">
                            @foreach ($workload as $row)
                                <li wire:key="wl-{{ $row['user']->id }}" class="flex items-center gap-3 px-4 py-2.5">
                                    <x-ui.avatar :name="$row['user']->name" :id="$row['user']->id" size="sm" />
                                    <span class="t-sub text-ink-950 flex-1 truncate">{{ $row['user']->name }}</span>

                                    @if ($row['overdue'] > 0)
                                        <x-ui.badge tone="danger" size="sm">{{ $row['overdue'] }} late</x-ui.badge>
                                    @endif

                                    <span class="t-sub text-ink-600 tnum">{{ $row['open'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                @if ($activity->isNotEmpty())
                    <x-ui.card title="Recent activity" :padding="false" :flush="true">
                        <ul class="divide-y divide-ink-100">
                            @foreach ($activity as $entry)
                                <li wire:key="act-{{ $entry->id }}" class="flex items-start gap-2.5 px-4 py-2.5">
                                    <x-ui.avatar
                                        :name="$entry->causer?->name ?? 'System'"
                                        :id="$entry->causer_id ?? 0"
                                        size="sm"
                                        class="mt-0.5"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="block t-sub text-ink-950">
                                            <span class="font-medium">{{ $entry->causer?->firstName() ?? 'System' }}</span>
                                            {{ $entry->description }}
                                        </span>
                                        <span class="block t-meta text-ink-400 mt-0.5">{{ $entry->created_at->diffForHumans() }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            </div>
        </div>
    </div>

    @can('create', App\Models\Asset::class)
        <livewire:assets.form />
    @endcan
</div>
