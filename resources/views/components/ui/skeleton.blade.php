@props([
    'shape' => 'line',
])

@php
    $shapes = [
        'line' => 'h-3.5 w-full rounded-full',
        'title' => 'h-4 w-2/5 rounded-full',
        'chip' => 'h-6 w-16 rounded-full',
        'avatar' => 'size-9 rounded-full',
        'block' => 'h-20 w-full rounded-card',
    ];
@endphp

<div
    {{ $attributes->class('skeleton '.($shapes[$shape] ?? $shapes['line'])) }}
    aria-hidden="true"
></div>
