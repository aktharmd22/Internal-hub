@props([
    'name',
    'label' => null,
])

@php
    // `$attributes->class()` appends rather than replaces, so a default of
    // `size-5` alongside a caller's `size-4` emits both and the winner is
    // decided by stylesheet order, not by intent. Only apply the default when
    // the caller has not sized the icon themselves.
    $sized = (bool) preg_match('/(^|\s)(size|w|h)-/', (string) $attributes->get('class', ''));
@endphp

{{-- Icons are decorative unless given a label; icon-only buttons pass one. --}}
<svg
    {{ $attributes->class(['shrink-0', 'size-5' => ! $sized]) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if ($label)
        role="img" aria-label="{{ $label }}"
    @else
        aria-hidden="true" focusable="false"
    @endif
>{!! \App\Support\Lucide::body($name) !!}</svg>
