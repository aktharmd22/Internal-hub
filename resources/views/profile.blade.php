<x-layouts.app title="Profile">
    <div class="px-4 lg:px-6 py-4 lg:py-5 flex flex-col gap-4">

        <x-ui.card>
            <div class="flex items-center gap-3.5">
                <x-ui.avatar :name="auth()->user()->name" :id="auth()->id()" size="lg" />

                <div class="min-w-0 flex-1">
                    <p class="t-section text-ink-950 truncate">{{ auth()->user()->name }}</p>
                    <p class="t-sub text-ink-600 truncate">{{ auth()->user()->email }}</p>
                </div>

                <div class="shrink-0 text-right">
                    <x-ui.badge tone="accent">{{ auth()->user()->role()?->label() ?? 'No role' }}</x-ui.badge>
                    <p class="t-meta text-ink-400 mt-1.5 max-w-xs hidden sm:block">
                        {{ auth()->user()->role()?->description() }}
                    </p>
                </div>
            </div>
        </x-ui.card>

        {{-- Two independent forms, so they sit side by side rather than one
             narrow column stretched across the screen. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            <livewire:profile.update-profile-information-form />
            <livewire:profile.update-password-form />
        </div>
    </div>
</x-layouts.app>
