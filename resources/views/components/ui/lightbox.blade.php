{{-- One overlay for the whole app. Any thumbnail dispatches `open-lightbox`
     with a url and a name, rather than every message rendering its own. --}}
<div
    x-data="{ open: false, url: null, name: '' }"
    x-on:open-lightbox.window="url = $event.detail.url; name = $event.detail.name ?? ''; open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60]"
>
    <div
        class="absolute inset-0 bg-ink-950/80 dark:bg-black/85"
        x-on:click="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-end="opacity-0"
        aria-hidden="true"
    ></div>

    <div
        class="absolute inset-0 flex flex-col pointer-events-none safe-t safe-b"
        role="dialog"
        aria-modal="true"
        x-bind:aria-label="name"
    >
        <div class="pointer-events-auto flex items-center gap-2 px-4 h-14 shrink-0">
            <p class="t-sub text-white/90 truncate flex-1" x-text="name"></p>

            <a
                x-bind:href="url"
                x-bind:download="name"
                class="tap grid place-items-center rounded-control text-white/80 hover:text-white hover:bg-white/10 transition-colors"
            >
                <x-icon name="download" class="size-5" label="Download" />
            </a>

            <button
                type="button"
                x-on:click="open = false"
                class="tap grid place-items-center rounded-control text-white/80 hover:text-white hover:bg-white/10 transition-colors"
            >
                <x-icon name="x" class="size-5" label="Close" />
            </button>
        </div>

        <div class="flex-1 min-h-0 grid place-items-center p-4" x-on:click="open = false">
            <img
                x-bind:src="url"
                x-bind:alt="name"
                x-on:click.stop
                class="pointer-events-auto max-h-full max-w-full object-contain rounded-card"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
            >
        </div>
    </div>
</div>
