@props([
    'align' => 'right',
    'width' => 'w-56',
])

@php
    $origin = $align === 'left'
        ? 'origin-top-left left-0'
        : 'origin-top-right right-0';
@endphp

<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <div x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-on:click.outside="open = false"
        x-on:click="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-end="opacity-0 scale-95"
        role="menu"
        {{ $attributes->class("absolute z-40 mt-2 {$width} {$origin} rounded-card border border-ink-100 bg-surface shadow-float py-1.5") }}
    >
        {{ $slot }}
    </div>
</div>
