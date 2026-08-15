@props(['compact' => false])

@php
    $modes = [
        ['value' => 'light', 'icon' => 'sun', 'label' => 'Light'],
        ['value' => 'dark', 'icon' => 'moon', 'label' => 'Dark'],
        ['value' => 'system', 'icon' => 'monitor', 'label' => 'System'],
    ];
@endphp

@if ($compact)
    <div {{ $attributes->class('inline-flex items-center gap-0.5 rounded-control bg-surface-2 p-0.5') }} role="group" aria-label="Theme">
        @foreach ($modes as $mode)
            <button
                type="button"
                x-on:click="$store.theme.set(@js($mode['value']))"
                x-bind:aria-pressed="$store.theme.mode === @js($mode['value'])"
                x-bind:class="$store.theme.mode === @js($mode['value'])
                    ? 'bg-surface text-ink-950 shadow-float'
                    : 'text-ink-400 hover:text-ink-800'"
                class="grid place-items-center size-8 rounded-[7px] transition-colors"
            >
                <x-icon :name="$mode['icon']" class="size-4" :label="$mode['label']" />
            </button>
        @endforeach
    </div>
@else
    <button
        type="button"
        x-on:click="$store.theme.toggle()"
        {{ $attributes->class('flex items-center gap-3 h-10 px-2.5 rounded-control text-ink-600 hover:bg-surface-2 transition-colors') }}
    >
        <span class="shrink-0">
            <x-icon name="sun" class="size-[18px] dark:hidden" />
            <x-icon name="moon" class="size-[18px] hidden dark:block" />
        </span>
        <span x-show="! $store.sidebar.collapsed" x-cloak class="t-sub">
            <span class="dark:hidden">Dark mode</span>
            <span class="hidden dark:inline">Light mode</span>
        </span>
    </button>
@endif
