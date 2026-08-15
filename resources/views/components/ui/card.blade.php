@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'flush' => false,
])

<section {{ $attributes->class('bg-surface border border-ink-100 rounded-card overflow-hidden') }}>
    @if ($title || isset($header) || isset($action))
        <header class="flex items-start gap-3 px-4 pt-4 {{ $flush ? 'pb-3 border-b border-ink-100' : 'pb-1' }}">
            <div class="min-w-0 flex-1">
                @isset($header)
                    {{ $header }}
                @else
                    <h2 class="t-section text-ink-950">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="t-sub text-ink-600 mt-0.5">{{ $subtitle }}</p>
                    @endif
                @endisset
            </div>

            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $padding ? 'p-4' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="px-4 py-3 border-t border-ink-100 bg-surface-2">
            {{ $footer }}
        </footer>
    @endisset
</section>
