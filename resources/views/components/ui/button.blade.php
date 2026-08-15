@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconTrailing' => null,
    'type' => 'button',
    'target' => null,
])

@php
    // One filled button per screen: `primary` and `danger` are the only fills.
    $variants = [
        'primary' => 'bg-accent-600 text-on-solid border border-transparent hover:bg-accent-500 active:bg-accent-500',
        'secondary' => 'bg-surface text-ink-800 border border-ink-200 hover:bg-surface-2 active:bg-surface-2',
        'ghost' => 'bg-transparent text-ink-600 border border-transparent hover:bg-surface-2 active:bg-surface-2',
        'danger' => 'bg-danger-600 text-on-solid border border-transparent hover:opacity-90 active:opacity-90',
    ];

    $sizes = [
        'sm' => 'h-10 md:h-9 px-3 gap-1.5 text-[13px]',
        'md' => 'h-11 md:h-10 px-4 gap-2 text-[15px] md:text-[14px]',
    ];

    $classes = implode(' ', [
        'relative inline-flex items-center justify-center rounded-control font-medium',
        'transition-colors duration-150 select-none whitespace-nowrap',
        'disabled:opacity-50 disabled:pointer-events-none',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['secondary'],
    ]);

    $iconSize = $size === 'sm' ? 'size-4' : 'size-[18px]';
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    {{ $attributes->class($classes)->merge($href ? ['href' => $href] : ['type' => $type]) }}
>
    @if ($target)
        <span wire:loading wire:target="{{ $target }}" class="absolute inset-0 grid place-items-center">
            <x-icon name="loader-circle" class="{{ $iconSize }} animate-spin" />
        </span>
    @endif

    <span
        @if ($target) wire:loading.class="invisible" wire:target="{{ $target }}" @endif
        class="inline-flex items-center {{ $size === 'sm' ? 'gap-1.5' : 'gap-2' }}"
    >
        @if ($icon)
            <x-icon :name="$icon" class="{{ $iconSize }}" />
        @endif

        {{ $slot }}

        @if ($iconTrailing)
            <x-icon :name="$iconTrailing" class="{{ $iconSize }}" />
        @endif
    </span>
</{{ $tag }}>
