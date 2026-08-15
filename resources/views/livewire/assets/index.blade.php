@php
    $chips = [
        ['key' => '7', 'label' => 'Next 7 days', 'count' => $counts['7'], 'tone' => 'danger'],
        ['key' => '30', 'label' => 'Next 30 days', 'count' => $counts['30'], 'tone' => 'warn'],
        ['key' => 'expired', 'label' => 'Expired', 'count' => $counts['expired'], 'tone' => 'danger'],
        ['key' => 'renewed', 'label' => 'Renewed', 'count' => null, 'tone' => 'ok'],
    ];
@endphp

<div>
    @push('page-actions')
        @can('create', App\Models\Asset::class)
            <x-ui.button :href="route('assets.import')" variant="ghost" size="sm" icon="upload" wire:navigate class="max-lg:hidden">
                Import
            </x-ui.button>
            <x-ui.button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'asset-form')">
                <span class="max-sm:sr-only">Add asset</span>
            </x-ui.button>
        @endcan
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">

        {{-- Search and filters ------------------------------------------- --}}
        <div class="flex flex-col gap-3">
            <div class="relative">
                <x-icon name="search" class="size-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, identifier or provider"
                    class="w-full h-11 md:h-10 pl-9 pr-3 rounded-control border border-ink-200 bg-surface text-ink-950 placeholder:text-ink-400"
                >
            </div>

            <div class="flex gap-2 overflow-x-auto no-scrollbar -mx-4 px-4 lg:mx-0 lg:px-0">
                @foreach ($chips as $chip)
                    <button
                        type="button"
                        wire:click="setWindow('{{ $chip['key'] }}')"
                        aria-pressed="{{ $window === $chip['key'] ? 'true' : 'false' }}"
                        class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-full border text-[13px] font-medium transition-colors
                            {{ $window === $chip['key']
                                ? 'bg-ink-950 text-canvas border-ink-950'
                                : 'bg-surface text-ink-600 border-ink-200 hover:bg-surface-2' }}"
                    >
                        {{ $chip['label'] }}
                        @if ($chip['count'] !== null && $chip['count'] > 0)
                            <span class="tnum {{ $window === $chip['key'] ? 'opacity-70' : App\Support\Tone::text($chip['tone']) }}">{{ $chip['count'] }}</span>
                        @endif
                    </button>
                @endforeach

                <div class="shrink-0 w-px bg-ink-100 my-1.5"></div>

                <select
                    wire:model.live="type"
                    class="shrink-0 h-9 px-3 rounded-full border border-ink-200 bg-surface text-[13px] text-ink-600"
                    aria-label="Filter by type"
                >
                    <option value="">All types</option>
                    @foreach (App\Enums\AssetType::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select
                    wire:model.live="client"
                    class="shrink-0 h-9 px-3 rounded-full border border-ink-200 bg-surface text-[13px] text-ink-600 max-w-44"
                    aria-label="Filter by client"
                >
                    <option value="">All clients</option>
                    @foreach ($clients as $option)
                        <option value="{{ $option->id }}">{{ $option->displayName() }}</option>
                    @endforeach
                </select>

                @if ($this->hasFilters())
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="shrink-0 inline-flex items-center gap-1 h-9 px-3 rounded-full text-[13px] text-ink-600 hover:bg-surface-2"
                    >
                        <x-icon name="x" class="size-3.5" />
                        Clear
                    </button>
                @endif
            </div>
        </div>

        {{-- Loading -------------------------------------------------------- --}}
        <div wire:loading.delay.long wire:target="search,window,type,client,sort,gotoPage,nextPage,previousPage">
            <x-ui.card :padding="false" class="divide-y divide-ink-100">
                @for ($i = 0; $i < 5; $i++)
                    <div class="flex items-center gap-3 px-4 min-h-16">
                        <x-ui.skeleton shape="avatar" />
                        <div class="flex-1 flex flex-col gap-2">
                            <x-ui.skeleton shape="title" />
                            <x-ui.skeleton class="w-3/5" />
                        </div>
                        <x-ui.skeleton shape="chip" />
                    </div>
                @endfor
            </x-ui.card>
        </div>

        {{-- List ----------------------------------------------------------- --}}
        <div wire:loading.remove.delay.long wire:target="search,window,type,client,sort,gotoPage,nextPage,previousPage">
            @if ($assets->isEmpty())
                <x-ui.card :padding="false">
                    <x-ui.empty-state
                        icon="calendar-clock"
                        :headline="$this->hasFilters() ? 'Nothing matches these filters' : 'No assets yet'"
                        :body="$this->hasFilters()
                            ? 'Try a wider window, or clear the filters to see everything.'
                            : 'Add the domains, hosting, certificates and licences you look after, and every expiry gets a reminder and an owner.'"
                    >
                        @if ($this->hasFilters())
                            <x-ui.button variant="secondary" wire:click="clearFilters">Clear filters</x-ui.button>
                        @else
                            @can('create', App\Models\Asset::class)
                                <div class="flex flex-wrap justify-center gap-2">
                                    <x-ui.button variant="primary" icon="plus" x-on:click="$dispatch('open-modal', 'asset-form')">
                                        Add an asset
                                    </x-ui.button>
                                    <x-ui.button variant="secondary" icon="upload" :href="route('assets.import')" wire:navigate>
                                        Import a list
                                    </x-ui.button>
                                </div>
                            @endcan
                        @endif
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                {{-- Mobile: stacked cards. No horizontal scrolling, ever. --}}
                <x-ui.card :padding="false" class="lg:hidden">
                    <div class="divide-y divide-ink-100">
                        @foreach ($assets as $asset)
                            <div wire:key="m-{{ $asset->id }}" class="relative overflow-hidden" x-data="swipeRow(112)">
                                @can('update', $asset)
                                    <div class="absolute inset-y-0 right-0 w-28 flex items-stretch">
                                        <button
                                            type="button"
                                            wire:click="renew({{ $asset->id }})"
                                            x-on:click="close()"
                                            class="flex-1 bg-ok-600 text-on-solid text-[13px] font-medium"
                                        >Renew</button>
                                    </div>
                                @endcan

                                <div
                                    class="relative bg-surface touch-pan-y"
                                    x-bind:style="`transform: translateX(${offset}px)`"
                                    x-bind:class="tracking ? '' : 'transition-transform duration-200'"
                                    x-on:pointerdown="start($event)"
                                    x-on:pointermove="move($event)"
                                    x-on:pointerup="end()"
                                    x-on:pointercancel="end()"
                                >
                                    <x-ui.list-row
                                        :href="route('assets.show', $asset)"
                                        :icon="$asset->type->icon()"
                                        wire:navigate
                                    >
                                        <x-slot:body>
                                            <p class="t-body font-medium text-ink-950 truncate">{{ $asset->name }}</p>
                                            <p class="t-sub text-ink-600 truncate mt-0.5">
                                                {{ $asset->type->label() }} · {{ $asset->client->displayName() }}
                                            </p>
                                        </x-slot:body>

                                        <x-slot:trailing>
                                            <x-ui.badge :tone="$asset->urgencyTone()" dot>{{ $asset->urgencyLabel() }}</x-ui.badge>
                                        </x-slot:trailing>
                                    </x-ui.list-row>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                {{-- Desktop: a real table, only from lg up. --}}
                <x-ui.card :padding="false" class="max-lg:hidden">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-ink-100">
                                <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5">Asset</th>
                                <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5">Client</th>
                                <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5">Provider</th>
                                <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5">Owner</th>
                                <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5 text-right">Cost</th>
                                <th scope="col" class="t-meta font-medium text-ink-400 uppercase tracking-wide px-4 py-2.5 text-right">Expires</th>
                                <th scope="col" class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($assets as $asset)
                                <tr wire:key="d-{{ $asset->id }}" class="hover:bg-surface-2 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('assets.show', $asset) }}" wire:navigate class="flex items-center gap-2.5 min-h-13">
                                            <x-icon :name="$asset->type->icon()" class="size-4 text-ink-400 shrink-0" />
                                            <span class="min-w-0">
                                                <span class="block t-body font-medium text-ink-950 truncate">{{ $asset->name }}</span>
                                                <span class="block t-meta text-ink-400">{{ $asset->type->label() }}</span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 t-sub text-ink-600 truncate max-w-40">{{ $asset->client->displayName() }}</td>
                                    <td class="px-4 py-2.5 t-sub text-ink-600">{{ $asset->provider ?: '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($asset->owner)
                                            <x-ui.avatar :name="$asset->owner->name" :id="$asset->owner->id" size="sm" />
                                        @else
                                            <span class="t-sub text-ink-400">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 t-sub text-ink-600 text-right tnum">
                                        {{ $asset->cost ? $asset->currency.' '.number_format((float) $asset->cost) : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <x-ui.badge :tone="$asset->urgencyTone()" dot>{{ $asset->urgencyLabel() }}</x-ui.badge>
                                        <span class="block t-meta text-ink-400 mt-0.5 tnum">{{ $asset->expires_at->format('j M Y') }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @can('update', $asset)
                                            <x-ui.dropdown align="right" width="w-44">
                                                <x-slot:trigger>
                                                    <button type="button" class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2 ml-auto">
                                                        <x-icon name="ellipsis-vertical" class="size-4" label="Actions" />
                                                    </button>
                                                </x-slot:trigger>
                                                <x-ui.dropdown-item icon="refresh-cw" wire:click="renew({{ $asset->id }})">Mark renewed</x-ui.dropdown-item>
                                                <x-ui.dropdown-item icon="pencil" :href="route('assets.show', $asset)" wire:navigate>Open</x-ui.dropdown-item>
                                            </x-ui.dropdown>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.card>

                @if ($assets->hasPages())
                    <div class="pt-1">{{ $assets->onEachSide(1)->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    @can('create', App\Models\Asset::class)
        <livewire:assets.form />
    @endcan
</div>
