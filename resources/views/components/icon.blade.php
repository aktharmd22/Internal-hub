@props([
    'name',
    'label' => null,
])

{{-- Icons are decorative unless given a label; icon-only buttons pass one. --}}
<svg
    {{ $attributes->class('shrink-0 size-5') }}
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
