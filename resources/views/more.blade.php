@php
    $user = auth()->user();
    $groups = \App\Support\Navigation::overflow($user);
@endphp

<x-layouts.app title="More">
    <div class="px-4 lg:px-6 py-4 lg:py-6 flex flex-col gap-4 max-w-2xl">
        <x-ui.card :padding="false" :flush="true">
            <x-slot:header>
                <div class="flex items-center gap-3.5">
                    <x-ui.avatar :name="$user->name" :id="$user->id" size="lg" />
                    <div class="min-w-0">
                        <p class="t-section text-ink-950 truncate">{{ $user->name }}</p>
                        <p class="t-sub text-ink-600 truncate">{{ $user->email }}</p>
                    </div>
                </div>
            </x-slot:header>

            <x-ui.list-row
                :href="route('profile')"
                icon="user"
                title="Profile"
                subtitle="Name, email, password"
                wire:navigate
            />
        </x-ui.card>

        @foreach ($groups as $group)
            <x-ui.card :padding="false" :title="$group['heading']" :flush="(bool) $group['heading']">
                <div class="divide-y divide-ink-100">
                    @foreach ($group['items'] as $item)
                        <x-ui.list-row
                            :href="route($item['route'])"
                            :icon="$item['icon']"
                            :title="$item['label']"
                            wire:navigate
                        />
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.card>
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="t-body font-medium text-ink-950">Appearance</p>
                    <p class="t-sub text-ink-600 mt-0.5">Follows your phone unless you pick one.</p>
                </div>
                <div x-data class="shrink-0">
                    <x-app.theme-toggle compact />
                </div>
            </div>
        </x-ui.card>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" icon="log-out" class="w-full">
                Sign out
            </x-ui.button>
        </form>

        <p class="t-meta text-ink-400 text-center">
            {{ config('app.name') }} · {{ $user->role()?->label() ?? 'No role' }}
        </p>
    </div>
</x-layouts.app>
