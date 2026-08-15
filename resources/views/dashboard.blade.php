@php
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 12 => 'Good morning',
        $hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };
@endphp

<x-layouts.app title="Home">
    <div class="px-4 lg:px-6 py-4 lg:py-6 flex flex-col gap-4 max-w-5xl">
        <div>
            <p class="t-section text-ink-950">{{ $greeting }}, {{ auth()->user()->firstName() }}</p>
            <p class="t-sub text-ink-600 mt-0.5">
                {{ now()->timezone(auth()->user()->timezone ?? config('app.timezone'))->format('l, j F') }}
            </p>
        </div>

        <x-ui.card>
            <x-ui.empty-state
                icon="calendar-clock"
                headline="Nothing is being watched yet"
                body="Add clients and their domains, hosting and certificates, and every expiry gets a reminder and an owner."
            >
                @can(\App\Support\Permissions::VIEW_ASSETS)
                    <x-ui.button :href="route('assets.index')" variant="primary" icon="plus" wire:navigate>
                        Add an asset
                    </x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>

        @if (! app()->isProduction())
            <x-ui.list-row
                :href="route('kitchen-sink')"
                icon="command"
                title="Kitchen sink"
                subtitle="Every component, both themes"
                class="rounded-card border border-ink-100"
                wire:navigate
            />
        @endif
    </div>
</x-layouts.app>
