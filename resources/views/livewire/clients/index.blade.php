<div>
    @push('page-actions')
        @can('create', App\Models\Client::class)
            <x-ui.button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'client-form')">
                <span class="max-sm:sr-only">Add client</span>
            </x-ui.button>
        @endcan
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-icon name="search" class="size-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search clients"
                    class="w-full h-11 md:h-10 pl-9 pr-3 rounded-control border border-ink-200 bg-surface text-ink-950 placeholder:text-ink-400"
                >
            </div>

            <select
                wire:model.live="status"
                class="h-11 md:h-10 px-3 rounded-control border border-ink-200 bg-surface text-ink-950 sm:w-40"
                aria-label="Filter by status"
            >
                <option value="">All statuses</option>
                @foreach (App\Enums\ClientStatus::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($clients->isEmpty())
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="building-2"
                    :headline="filled($search) || filled($status) ? 'No clients match that' : 'No clients yet'"
                    body="A client holds the assets, projects and tasks the agency looks after for them."
                >
                    @can('create', App\Models\Client::class)
                        <x-ui.button variant="primary" icon="plus" x-on:click="$dispatch('open-modal', 'client-form')">
                            Add a client
                        </x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <x-ui.card :padding="false">
                <div class="divide-y divide-ink-100">
                    @foreach ($clients as $client)
                        <x-ui.list-row
                            wire:key="client-{{ $client->id }}"
                            :href="route('clients.show', $client)"
                            wire:navigate
                        >
                            <x-slot:leading>
                                <x-ui.avatar :name="$client->displayName()" :id="$client->id" />
                            </x-slot:leading>

                            <x-slot:body>
                                <p class="t-body font-medium text-ink-950 truncate">{{ $client->displayName() }}</p>
                                <p class="t-sub text-ink-600 truncate mt-0.5">
                                    {{ $client->assets_count }} {{ str('asset')->plural($client->assets_count) }}
                                    · {{ $client->open_tasks_count }} open {{ str('task')->plural($client->open_tasks_count) }}
                                    @if ($client->accountManager) · {{ $client->accountManager->name }} @endif
                                </p>
                            </x-slot:body>

                            <x-slot:trailing>
                                @if ($client->expiring_count > 0)
                                    <x-ui.badge tone="warn" dot>{{ $client->expiring_count }} due</x-ui.badge>
                                @elseif ($client->status !== App\Enums\ClientStatus::Active)
                                    <x-ui.badge :tone="$client->status->tone()">{{ $client->status->label() }}</x-ui.badge>
                                @endif
                            </x-slot:trailing>
                        </x-ui.list-row>
                    @endforeach
                </div>
            </x-ui.card>

            @if ($clients->hasPages())
                <div>{{ $clients->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </div>

    @can('create', App\Models\Client::class)
        <livewire:clients.form />
    @endcan
</div>
