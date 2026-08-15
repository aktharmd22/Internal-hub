@props(['title' => null])

@php $user = auth()->user(); @endphp

{{-- Page-level actions arrive through a stack rather than a slot, because a
     full-page Livewire component renders into $slot and cannot fill one. --}}

{{-- Collapses on scroll down and returns on scroll up, but only on mobile
     where vertical space is the scarce resource. --}}
<header
    x-data="stickyHeader"
    x-bind:class="{ 'max-lg:-translate-y-full': hidden }"
    class="sticky top-0 z-20 bg-canvas border-b border-ink-100 transition-transform duration-200 safe-t"
>
    <div class="flex items-center gap-2 h-14 lg:h-16 px-4 lg:px-6">
        <h1 class="t-page-title text-ink-950 truncate flex-1 min-w-0">{{ $title }}</h1>

        <div class="flex items-center gap-2 shrink-0">
            @stack('page-actions')
        </div>

        <button
            type="button"
            x-data
            x-on:click="$dispatch('open-palette')"
            class="tap grid place-items-center rounded-control text-ink-600 hover:bg-surface-2 shrink-0"
        >
            <x-icon name="search" class="size-5" label="Search everything" />
        </button>

        <a
            href="{{ route('notifications.index') }}"
            wire:navigate
            class="relative tap grid place-items-center rounded-control text-ink-600 hover:bg-surface-2 shrink-0"
        >
            <x-icon name="bell" class="size-5" label="Notifications" />

            @if ($unread = $user->unreadNotifications()->count())
                <span class="absolute top-1.5 right-1.5 min-w-4 h-4 px-1 grid place-items-center rounded-full bg-danger-600 text-on-solid text-[10px] font-medium tnum">
                    {{ $unread > 99 ? '99+' : $unread }}
                </span>
            @endif
        </a>

        <x-ui.dropdown align="right" width="w-60" class="shrink-0">
            <x-slot:trigger>
                <button type="button" class="tap grid place-items-center rounded-full">
                    <x-ui.avatar :name="$user->name" :id="$user->id" size="sm" />
                </button>
            </x-slot:trigger>

            <div class="px-3 py-2 border-b border-ink-100">
                <p class="t-sub font-medium text-ink-950 truncate">{{ $user->name }}</p>
                <p class="t-meta text-ink-600 truncate">{{ $user->email }}</p>
                <x-ui.badge tone="accent" size="sm" class="mt-1.5">{{ $user->role()?->label() ?? 'No role' }}</x-ui.badge>
            </div>

            <x-ui.dropdown-item :href="route('profile')" icon="user" wire:navigate>Profile</x-ui.dropdown-item>

            <div class="flex items-center justify-between gap-2 px-3 py-2">
                <span class="t-sub text-ink-600">Theme</span>
                <x-app.theme-toggle compact />
            </div>

            <form method="POST" action="{{ route('logout') }}" class="border-t border-ink-100 pt-1">
                @csrf
                <x-ui.dropdown-item type="submit" icon="log-out" tone="danger">Sign out</x-ui.dropdown-item>
            </form>
        </x-ui.dropdown>
    </div>
</header>
