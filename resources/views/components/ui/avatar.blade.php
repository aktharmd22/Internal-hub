@props([
    'name' => '',
    'id' => 0,
    'src' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'size-7 text-[11px]',
        'md' => 'size-9 text-[13px]',
        'lg' => 'size-12 text-[16px]',
    ];

    $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_strtoupper(
        mb_substr($words[0] ?? '?', 0, 1).(count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '')
    );

    // Same user, same colour, everywhere — derived, never stored.
    $hue = ((int) $id * 47) % 360;

    $tint = implode(';', [
        "--av-bg:hsl({$hue} 55% 93%)",
        "--av-fg:hsl({$hue} 45% 30%)",
        "--av-bg-dark:hsl({$hue} 28% 24%)",
        "--av-fg-dark:hsl({$hue} 62% 76%)",
    ]);
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        alt="{{ $name }}"
        {{ $attributes->class("rounded-full object-cover bg-surface-2 {$sizes[$size]}") }}
    >
@else
    <span
        style="{{ $tint }}"
        title="{{ $name }}"
        {{ $attributes->class("avatar-tint grid place-items-center rounded-full font-medium select-none {$sizes[$size]}") }}
    >
        <span aria-hidden="true">{{ $initials }}</span>
        <span class="sr-only">{{ $name }}</span>
    </span>
@endif
