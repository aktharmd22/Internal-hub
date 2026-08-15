{{-- Toast host. Sits above the bottom tab bar on mobile, bottom-right on desktop. --}}
<div
    x-data
    aria-live="polite"
    aria-atomic="false"
    class="fixed z-50 inset-x-3 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] lg:inset-x-auto lg:right-6 lg:bottom-6 lg:w-96 flex flex-col gap-2 pointer-events-none"
>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-card border border-ink-100 bg-surface shadow-float px-3.5 py-3"
            :class="{
                'border-l-2 border-l-ok-600': toast.tone === 'ok',
                'border-l-2 border-l-warn-600': toast.tone === 'warn',
                'border-l-2 border-l-danger-600': toast.tone === 'danger',
            }"
        >
            <p class="t-sub text-ink-950 flex-1 min-w-0" x-text="toast.message"></p>

            <button
                type="button"
                class="shrink-0 -m-1 p-1 rounded-control text-ink-400 hover:text-ink-800 hover:bg-surface-2"
                x-on:click="$store.toasts.dismiss(toast.id)"
            >
                <x-icon name="x" class="size-4" label="Dismiss" />
            </button>
        </div>
    </template>
</div>
