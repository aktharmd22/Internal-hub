@props([
    'tone' => 'neutral',
    'dot' => false,
    'size' => 'md',
])

@php
    // Colour carries status, but never alone — the label is always rendered.
    $tones = [
        'neutral' => ['chip' => 'bg-ink-100 text-ink-600', 'dot' => 'bg-ink-400'],
        'accent' => ['chip' => 'bg-accent-50 text-accent-600', 'dot' => 'bg-accent-500'],
        'ok' => ['chip' => 'bg-ok-50 text-ok-600', 'dot' => 'bg-ok-600'],
        'warn' => ['chip' => 'bg-warn-50 text-warn-600', 'dot' => 'bg-warn-600'],
        'danger' => ['chip' => 'bg-danger-50 text-danger-600', 'dot' => 'bg-danger-600'],
    ];

    $tone = $tones[$tone] ?? $tones['neutral'];
    $sizing = $size === 'sm' ? 'h-5 px-1.5 text-[11px] gap-1' : 'h-6 px-2 text-[12px] gap-1.5';
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full font-medium whitespace-nowrap {$sizing} {$tone['chip']}") }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $tone['dot'] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
