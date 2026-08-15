<div
    x-data="{
        open: false,
        index: 0,
        show() { this.open = true; this.index = 0; $nextTick(() => $refs.input?.focus()); },
        hide() { this.open = false; $wire.clear(); },
    }"
    x-on:keydown.window.prevent.cmd.k="show()"
    x-on:keydown.window.prevent.ctrl.k="show()"
    x-on:open-palette.window="show()"
    x-on:keydown.escape.window="hide()"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50">
        <div
            class="absolute inset-0 bg-ink-950/40 dark:bg-black/60"
            x-on:click="hide()"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            aria-hidden="true"
        ></div>

        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:grid sm:place-items-start sm:pt-24 sm:px-6 pointer-events-none">
            <div
                x-trap.noscroll="open"
                role="dialog"
                aria-modal="true"
                aria-label="Search everything"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                class="pointer-events-auto w-full sm:max-w-xl sm:mx-auto bg-surface border border-ink-100 rounded-t-2xl sm:rounded-card shadow-float max-h-[80dvh] flex flex-col"
            >
                <div class="flex items-center gap-2.5 px-4 h-14 border-b border-ink-100 shrink-0">
                    <x-icon name="search" class="size-4 text-ink-400 shrink-0" />
                    <input
                        x-ref="input"
                        type="search"
                        wire:model.live.debounce.200ms="query"
                        placeholder="Search tasks, assets and clients"
                        class="flex-1 min-w-0 bg-transparent border-0 p-0 text-ink-950 placeholder:text-ink-400 focus:ring-0"
                    >
                    <kbd class="max-sm:hidden t-meta text-ink-400 border border-ink-200 rounded px-1.5 py-0.5">esc</kbd>
                </div>

                <div class="overflow-y-auto flex-1 py-1.5">
                    @if (strlen(trim($query)) < 2)
                        <p class="t-sub text-ink-400 px-4 py-6 text-center">Type at least two characters.</p>
                    @elseif ($results->isEmpty())
                        <p class="t-sub text-ink-400 px-4 py-6 text-center">Nothing found for "{{ $query }}".</p>
                    @else
                        @foreach ($results->groupBy('group') as $group => $items)
                            <p class="t-meta text-ink-400 uppercase tracking-wide px-4 pt-2.5 pb-1">{{ $group }}</p>

                            @foreach ($items as $item)
                                <a
                                    wire:key="cp-{{ $item['url'] }}"
                                    href="{{ $item['url'] }}"
                                    wire:navigate
                                    x-on:click="hide()"
                                    class="flex items-center gap-3 px-4 min-h-12 hover:bg-surface-2 transition-colors"
                                >
                                    <x-icon :name="$item['icon']" class="size-4 text-ink-400 shrink-0" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block t-sub text-ink-950 truncate">{{ $item['title'] }}</span>
                                        <span class="block t-meta text-ink-400 truncate">{{ $item['subtitle'] }}</span>
                                    </span>
                                    <x-icon name="chevron-right" class="size-4 text-ink-200 shrink-0" />
                                </a>
                            @endforeach
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
