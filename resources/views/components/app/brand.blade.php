@props([
    'size' => 'md',
    'showName' => true,
    'subtitle' => 'Renewals &amp; tasks',
])

@php
    $logo = \App\Support\Brand::url();
    $name = \App\Support\Brand::name();

    $box = $size === 'lg' ? 'size-9' : 'size-8';
    $glyph = $size === 'lg' ? 'size-5' : 'size-[18px]';
@endphp

<span {{ $attributes->class('flex items-center gap-2.5 min-w-0') }}>
    @if ($logo)
        {{-- object-contain, never cover: a client's logo must not be cropped
             to fit our square. --}}
        <img
            src="{{ $logo }}"
            alt="{{ $name }}"
            class="{{ $box }} shrink-0 rounded-control object-contain bg-surface"
        >
    @else
        <span class="grid place-items-center {{ $box }} rounded-control bg-accent-600 text-on-solid shrink-0">
            <x-icon name="shield-check" class="{{ $glyph }}" />
        </span>
    @endif

    @if ($showName)
        <span class="min-w-0">
            <span class="block t-sub font-medium text-ink-950 truncate">{{ $name }}</span>
            @if ($subtitle)
                <span class="block t-meta text-ink-400 truncate">{!! $subtitle !!}</span>
            @endif
        </span>
    @endif
</span>
