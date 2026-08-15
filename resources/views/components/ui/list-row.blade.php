@props([
    'title' => null,
    'subtitle' => null,
    'href' => null,
    'icon' => null,
    'chevron' => null,
])

@php
    // 64px on mobile so a thumb finds it; 52px on desktop where density wins.
    $classes = 'group relative flex w-full items-center gap-3 px-4 min-h-16 md:min-h-13 text-left bg-surface transition-colors';

    if ($href) {
        $classes .= ' hover:bg-surface-2 active:bg-surface-2';
    }

    $showChevron = $chevron ?? (bool) $href;
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    {{ $attributes->class($classes)->merge($href ? ['href' => $href] : []) }}
>
    @isset($leading)
        <div class="shrink-0">{{ $leading }}</div>
    @elseif ($icon)
        <div class="shrink-0 grid place-items-center size-9 rounded-control bg-surface-2 text-ink-600">
            <x-icon :name="$icon" class="size-[18px]" />
        </div>
    @endisset

    <div class="min-w-0 flex-1 py-2.5">
        @isset($body)
            {{ $body }}
        @else
            <p class="t-body font-medium text-ink-950 truncate">{{ $title }}</p>
            @if ($subtitle)
                <p class="t-sub text-ink-600 truncate mt-0.5">{{ $subtitle }}</p>
            @endif
        @endisset
    </div>

    @isset($trailing)
        <div class="shrink-0 flex items-center gap-2">{{ $trailing }}</div>
    @endisset

    @if ($showChevron)
        <x-icon name="chevron-right" class="shrink-0 size-4 text-ink-400" />
    @endif
</{{ $tag }}>
