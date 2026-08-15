@php
    $user = auth()->user();
    $groups = \App\Support\Navigation::sidebar($user);
@endphp

<aside
    x-data
    x-bind:class="$store.sidebar.collapsed ? 'lg:w-[68px]' : 'lg:w-[248px]'"
    class="hidden lg:flex lg:flex-col sticky top-0 h-dvh shrink-0 border-r border-ink-100 bg-surface transition-[width] duration-200 lg:w-[248px]"
>
    <div class="flex items-center gap-2.5 h-16 px-4 shrink-0">
        <span class="grid place-items-center size-8 rounded-control bg-accent-600 text-on-solid shrink-0">
            <x-icon name="shield-check" class="size-[18px]" />
        </span>

        <span x-show="! $store.sidebar.collapsed" x-cloak class="min-w-0">
            <span class="block t-sub font-medium text-ink-950 truncate">{{ config('app.name') }}</span>
            <span class="block t-meta text-ink-400 truncate">Renewals &amp; tasks</span>
        </span>
    </div>

    <nav class="flex-1 overflow-y-auto no-scrollbar px-2.5 pb-4" aria-label="Sidebar">
        @foreach ($groups as $group)
            <div class="mt-4 first:mt-0">
                @if ($group['heading'])
                    <p
                        x-show="! $store.sidebar.collapsed"
                        x-cloak
                        class="t-meta font-medium uppercase tracking-wide text-ink-400 px-2.5 pb-1.5"
                    >{{ $group['heading'] }}</p>
                @endif

                <ul class="flex flex-col gap-0.5">
                    @foreach ($group['items'] as $item)
                        <li>
                            <x-app.nav-item
                                :label="$item['label']"
                                :route="$item['route']"
                                :icon="$item['icon']"
                                :active="$item['active']"
                            />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-ink-100 p-2.5 flex flex-col gap-0.5 shrink-0">
        <x-app.theme-toggle />

        <button
            type="button"
            x-on:click="$store.sidebar.toggle()"
            class="flex items-center gap-3 h-10 px-2.5 rounded-control text-ink-600 hover:bg-surface-2 transition-colors"
        >
            <x-icon name="panel-left" class="size-[18px] shrink-0" />
            <span x-show="! $store.sidebar.collapsed" x-cloak class="t-sub">Collapse</span>
        </button>
    </div>
</aside>
