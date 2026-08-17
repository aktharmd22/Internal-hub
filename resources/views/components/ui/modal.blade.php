@props([
    'name',
    'title' => null,
    'subtitle' => null,
    'width' => 'md',
])

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-2xl',
    ];
    $labelId = 'modal-title-'.$name;
@endphp

{{-- Bottom sheet on a phone, centred dialog from `sm:` up. --}}
<div
    x-data="{ show: false, name: @js($name) }"
    {{-- $modalTarget unwraps the payload. Livewire wraps dispatched params in
         an array while Alpine passes the bare value, so comparing $event.detail
         directly works for one sender and silently fails for the other. --}}
    x-on:open-modal.window="if ($modalTarget($event) === name) show = true"
    x-on:close-modal.window="if ($modalTarget($event) === name || $event.detail === undefined) show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50"
>
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-ink-950/40 dark:bg-black/60"
        x-on:click="show = false"
        aria-hidden="true"
    ></div>

    <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:grid sm:place-items-center sm:p-6 pointer-events-none">
        <div
            x-show="show"
            x-trap.noscroll="show"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $labelId }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
            {{ $attributes->class("pointer-events-auto w-full {$widths[$width]} sm:mx-auto bg-surface border border-ink-100 rounded-t-2xl sm:rounded-card shadow-float max-h-[88dvh] flex flex-col") }}
        >
            <div class="sm:hidden pt-2 pb-1 flex justify-center shrink-0">
                <span class="h-1 w-9 rounded-full bg-ink-200" aria-hidden="true"></span>
            </div>

            <header class="flex items-start gap-3 px-4 pt-3 pb-3 border-b border-ink-100 shrink-0">
                <div class="min-w-0 flex-1">
                    <h2 id="{{ $labelId }}" class="t-section text-ink-950">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="t-sub text-ink-600 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>

                <button
                    type="button"
                    x-on:click="show = false"
                    class="tap -m-2 grid place-items-center rounded-control text-ink-600 hover:bg-surface-2"
                >
                    <x-icon name="x" class="size-5" label="Close" />
                </button>
            </header>

            <div class="px-4 py-4 overflow-y-auto flex-1">
                {{ $slot }}
            </div>

            @isset($footer)
                <footer class="px-4 py-3 border-t border-ink-100 flex items-center justify-end gap-2 shrink-0 safe-b">
                    {{ $footer }}
                </footer>
            @endisset
        </div>
    </div>
</div>
