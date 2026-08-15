@php
    $user = auth()->user();
    $tabs = \App\Support\Navigation::tabs($user);

    $unreadChats = \App\Models\Task::query()
        ->active()
        ->visibleTo($user)
        ->whereHas('messages', fn ($q) => $q
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('reads', fn ($r) => $r->where('user_id', $user->id)))
        ->count();
@endphp

<nav
    class="lg:hidden fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-surface safe-b"
    aria-label="Primary"
    x-data
>
    <ul class="flex">
        @foreach ($tabs as $tab)
            @php $isActive = request()->routeIs($tab['active']); @endphp

            <li class="flex-1">
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if ($isActive) aria-current="page" @endif
                    @if ($tab['route'] === 'dashboard')
                        {{-- Long press opens the command palette: ⌘K has no
                             equivalent on a phone keyboard. --}}
                        x-on:touchstart.passive="$el._t = setTimeout(() => $dispatch('open-palette'), 550)"
                        x-on:touchend.passive="clearTimeout($el._t)"
                        x-on:touchmove.passive="clearTimeout($el._t)"
                        x-on:contextmenu.prevent
                    @endif
                    class="relative flex flex-col items-center justify-center gap-1 min-h-14 px-1 {{ $isActive ? 'text-accent-600' : 'text-ink-400' }}"
                >
                    @if ($isActive)
                        <span class="absolute top-0 h-0.5 w-8 rounded-full bg-accent-600" aria-hidden="true"></span>
                    @endif

                    <span class="relative">
                        <x-icon :name="$tab['icon']" class="size-[22px]" />

                        @if ($tab['route'] === 'chat.index' && $unreadChats > 0)
                            <span class="absolute -top-1 -right-1.5 min-w-4 h-4 px-1 grid place-items-center rounded-full bg-danger-600 text-on-solid text-[10px] font-medium tnum">
                                {{ $unreadChats > 99 ? '99+' : $unreadChats }}
                            </span>
                        @endif
                    </span>

                    <span class="text-[11px] leading-none {{ $isActive ? 'font-medium' : '' }}">{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
