@props([
    'href' => null,
    'icon' => null,
    'tone' => 'neutral',
])

@php
    $tag = $href ? 'a' : 'button';
    $text = $tone === 'danger' ? 'text-danger-600' : 'text-ink-800';
@endphp

<{{ $tag }}
    role="menuitem"
    {{ $attributes->class("flex w-full items-center gap-2.5 px-3 min-h-11 md:min-h-9 text-left text-[15px] md:text-[14px] hover:bg-surface-2 {$text}")->merge($href ? ['href' => $href] : ['type' => 'button']) }}
>
    @if ($icon)
        <x-icon :name="$icon" class="size-4 shrink-0 opacity-70" />
    @endif
    {{ $slot }}
</{{ $tag }}>
