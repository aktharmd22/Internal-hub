@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 12 => 'Good morning',
        $hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };

    // An employee has no asset list, so a card counting renewals they cannot
    // open is noise. The cards they get are the ones they can act on.
    $cards = array_values(array_filter([
        $canSeeAssets ? ['label' => 'Expiring in 7 days', 'value' => $metrics['expiring7'], 'tone' => 'danger', 'href' => route('assets.index', ['window' => '7'])] : null,
        $canSeeAssets ? ['label' => 'Expiring in 30 days', 'value' => $metrics['expiring30'], 'tone' => 'warn', 'href' => route('assets.index', ['window' => '30'])] : null,
        ['label' => 'Open tasks', 'value' => $metrics['openTasks'], 'tone' => 'neutral', 'href' => route('tasks.index', ['filter' => 'mine'])],
        ['label' => 'Awaiting review', 'value' => $metrics['awaitingReview'], 'tone' => 'accent', 'href' => route('tasks.index', ['status' => 'submitted'])],
    ]));

    $columns = count($cards) === 2 ? 'grid-cols-2' : 'grid-cols-2 lg:grid-cols-4';
@endphp

<div
    x-data="pullToRefresh(() => $wire.refreshData())"
    x-on:touchstart.passive="onStart($event)"
    x-on:touchmove.passive="onMove($event)"
    x-on:touchend.passive="onEnd()"
>
    {{-- Pull-to-refresh indicator --}}
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

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4 max-w-5xl">

        <div>
            <p class="t-section text-ink-950">{{ $greeting }}, {{ auth()->user()->firstName() }}</p>
            <p class="t-sub text-ink-600 mt-0.5">
                {{ now()->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('l, j F') }}
                @if ($metrics['expiring7'] > 0)
                    · <span class="text-danger-600">{{ $metrics['expiring7'] }} {{ str('renewal')->plural($metrics['expiring7']) }} this week</span>
                @endif
            </p>
        </div>

        {{-- Metrics: 2x2 on a phone, a row of four on desktop ------------- --}}
        <div class="grid {{ $columns }} gap-3">
            @foreach ($cards as $card)
                @php $tag = $card['href'] ? 'a' : 'div'; @endphp
                <{{ $tag }}
                    @if ($card['href']) href="{{ $card['href'] }}" wire:navigate @endif
                    class="block bg-surface border border-ink-100 rounded-card p-4 transition-colors {{ $card['href'] ? 'hover:bg-surface-2' : '' }}"
                >
                    <p class="t-metric {{ $card['value'] > 0 ? App\Support\Tone::metric($card['tone']) : 'text-ink-950' }}">
                        {{ $card['value'] }}
                    </p>
                    <p class="t-sub text-ink-600 mt-1 leading-tight">{{ $card['label'] }}</p>
                </{{ $tag }}>
            @endforeach
        </div>

        {{-- Expiring soon --------------------------------------------------- --}}
        @if ($canSeeAssets)
            <x-ui.card title="Expiring soon" :padding="false" :flush="true">
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
                            <x-ui.list-row
                                wire:key="exp-{{ $asset->id }}"
                                :href="route('assets.show', $asset)"
                                :icon="$asset->type->icon()"
                                :chevron="false"
                                wire:navigate
                            >
                                <x-slot:body>
                                    <p class="t-body font-medium text-ink-950 truncate">{{ $asset->name }}</p>
                                    <p class="t-sub text-ink-600 truncate mt-0.5">
                                        {{ $asset->client->displayName() }} · {{ $asset->expires_at->format('j M') }}
                                    </p>
                                </x-slot:body>

                                <x-slot:trailing>
                                    <x-ui.badge :tone="$asset->urgencyTone()" dot>{{ $asset->urgencyLabel() }}</x-ui.badge>

                                    @can('update', $asset)
                                        <x-ui.button
                                            size="sm"
                                            variant="secondary"
                                            wire:click.stop="renew({{ $asset->id }})"
                                            target="renew"
                                            class="max-sm:hidden"
                                        >Renew</x-ui.button>
                                    @endcan
                                </x-slot:trailing>
                            </x-ui.list-row>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @endif

        {{-- My tasks --------------------------------------------------------- --}}
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
                            :title="$task->title"
                            :subtitle="$task->reference.($task->client ? ' · '.$task->client->displayName() : '')"
                            wire:navigate
                        >
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

        {{-- Status bar -------------------------------------------------------- --}}
        @if ($statusBar['total'] > 0)
            <x-ui.card title="Where the work sits" :subtitle="$statusBar['total'].' '.str('task')->plural($statusBar['total']).' you can see'">
                <div class="flex h-2.5 rounded-full overflow-hidden bg-ink-100 mt-3">
                    @foreach ($statusBar['segments'] as $segment)
                        <div
                            class="{{ App\Support\Tone::fill($segment['status']->tone()) }}"
                            style="width: {{ $segment['percent'] }}%"
                            title="{{ $segment['status']->label() }}: {{ $segment['count'] }}"
                        ></div>
                    @endforeach
                </div>

                <ul class="flex flex-wrap gap-x-4 gap-y-1.5 mt-3">
                    @foreach ($statusBar['segments'] as $segment)
                        <li class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full {{ App\Support\Tone::fill($segment['status']->tone()) }}"></span>
                            <span class="t-meta text-ink-600">{{ $segment['status']->label() }}</span>
                            <span class="t-meta text-ink-950 tnum font-medium">{{ $segment['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>

    @can('create', App\Models\Asset::class)
        <livewire:assets.form />
    @endcan
</div>
