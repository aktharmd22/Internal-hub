@props([
    'label',
    'route',
    'icon',
    'active' => null,
])

@php
    $isActive = request()->routeIs($active ?? $route);
@endphp

<a
    href="{{ route($route) }}"
    wire:navigate
    @if ($isActive) aria-current="page" @endif
    class="flex items-center gap-3 h-10 px-2.5 rounded-control transition-colors {{ $isActive ? 'bg-accent-50 text-accent-600 font-medium' : 'text-ink-600 hover:bg-surface-2 hover:text-ink-950' }}"
>
    <x-icon :name="$icon" class="size-[18px] shrink-0" />
    <span x-show="! $store.sidebar.collapsed" x-cloak class="t-sub truncate">{{ $label }}</span>
</a>
