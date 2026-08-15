@props([
    'icon' => 'inbox',
    'headline',
    'body' => null,
])

{{-- An empty state is an invitation, not an apology. --}}
<div {{ $attributes->class('flex flex-col items-center text-center px-6 py-12') }}>
    <div class="grid place-items-center size-12 rounded-card bg-surface-2 text-ink-400 mb-4">
        <x-icon :name="$icon" class="size-6" />
    </div>

    <p class="t-section text-ink-950">{{ $headline }}</p>

    @if ($body)
        <p class="t-sub text-ink-600 mt-1 max-w-xs">{{ $body }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
