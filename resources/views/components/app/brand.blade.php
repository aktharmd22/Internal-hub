@props([
    'size' => 'md',
    'showName' => null,
    'subtitle' => null,
    'cap' => 'max-w-full',
    'center' => false,
])

@php
    $logo = \App\Support\Brand::url();
    $name = \App\Support\Brand::name();
    $wordmark = \App\Support\Brand::isWordmark();

    // A wide lockup already contains the company name. Printing it again beside
    // the mark says everything twice and leaves the logo fighting for width.
    $showName = $showName ?? ! $wordmark;

    $heights = ['sm' => 'h-6', 'md' => 'h-8', 'lg' => 'h-10', 'xl' => 'h-12'];
    $squares = ['sm' => 'size-6', 'md' => 'size-8', 'lg' => 'size-10', 'xl' => 'size-12'];
    $glyphs = ['sm' => 'size-4', 'md' => 'size-[18px]', 'lg' => 'size-5', 'xl' => 'size-6'];

    $height = $heights[$size] ?? $heights['md'];
    $square = $squares[$size] ?? $squares['md'];
    $glyph = $glyphs[$size] ?? $glyphs['md'];
@endphp

<span {{ $attributes->class([
    'flex items-center gap-2.5 min-w-0',
    'justify-center' => $center,
]) }}>
    @if ($logo)
        {{-- No plate behind it. The file already carries its own transparency,
             and a surface-coloured square around a transparent logo is just a
             box the designer did not ask for. --}}
        <img
            src="{{ $logo }}"
            alt="{{ $name }}"
            @class([
                'shrink-0 object-contain',
                "{$height} w-auto {$cap}" => $wordmark,
                $square => ! $wordmark,
            ])
        >
    @else
        <span class="grid place-items-center {{ $square }} rounded-control bg-accent-600 text-on-solid shrink-0">
            <x-icon name="shield-check" class="{{ $glyph }}" />
        </span>
    @endif

    @if ($showName)
        <span @class(['min-w-0', 'text-center' => $center])>
            <span class="block t-sub font-medium text-ink-950 truncate">{{ $name }}</span>
            @if ($subtitle)
                <span class="block t-meta text-ink-400 truncate">{!! $subtitle !!}</span>
            @endif
        </span>
    @endif
</span>
