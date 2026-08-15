<x-layouts.app title="Profile">
    <div class="px-4 lg:px-6 py-4 lg:py-6 max-w-2xl flex flex-col gap-4">
        <x-ui.card>
            <div class="flex items-center gap-3.5">
                <x-ui.avatar :name="auth()->user()->name" :id="auth()->id()" size="lg" />

                <div class="min-w-0">
                    <p class="t-section text-ink-950 truncate">{{ auth()->user()->name }}</p>
                    <p class="t-sub text-ink-600 truncate">{{ auth()->user()->email }}</p>
                    <x-ui.badge tone="accent" size="sm" class="mt-1.5">
                        {{ auth()->user()->role()?->label() ?? 'No role' }}
                    </x-ui.badge>
                </div>
            </div>

            <p class="t-meta text-ink-600 mt-4">
                {{ auth()->user()->role()?->description() }}
            </p>
        </x-ui.card>

        <livewire:profile.update-profile-information-form />

        <livewire:profile.update-password-form />
    </div>
</x-layouts.app>
