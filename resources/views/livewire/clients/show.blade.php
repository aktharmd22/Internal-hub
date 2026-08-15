<div>
    @can('update', $client)
        @push('page-actions')
            <x-ui.button variant="secondary" size="sm" icon="pencil" wire:click="$dispatch('edit-client', { id: {{ $client->id }} })">
                <span class="max-sm:sr-only">Edit</span>
            </x-ui.button>
        @endpush
    @endcan

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4 max-w-4xl">

        <x-ui.card>
            <div class="flex items-start gap-3.5">
                <x-ui.avatar :name="$client->displayName()" :id="$client->id" size="lg" />

                <div class="min-w-0 flex-1">
                    <p class="t-page-title text-ink-950 break-words">{{ $client->displayName() }}</p>
                    <p class="t-sub text-ink-600 mt-0.5">{{ $client->name }}</p>

                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <x-ui.badge :tone="$client->status->tone()" dot>{{ $client->status->label() }}</x-ui.badge>
                        @if ($client->send_renewal_notices)
                            <x-ui.badge tone="accent" size="sm">Gets renewal notices</x-ui.badge>
                        @endif
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 mt-5 pt-4 border-t border-ink-100">
                @foreach ([
                    'Email' => $client->email ?: '—',
                    'Phone' => $client->phone ?: '—',
                    'WhatsApp' => $client->whatsapp ?: '—',
                    'Account manager' => $client->accountManager?->name ?: 'Unassigned',
                    'GST' => $client->gst_number ?: '—',
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="t-meta text-ink-400">{{ $label }}</dt>
                        <dd class="t-sub text-ink-950 mt-0.5 truncate">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($client->notes)
                <div class="mt-4 pt-4 border-t border-ink-100">
                    <p class="t-meta text-ink-400 mb-1">Notes</p>
                    <p class="t-sub text-ink-800 whitespace-pre-line">{{ $client->notes }}</p>
                </div>
            @endif
        </x-ui.card>

        {{-- Tabs ------------------------------------------------------------ --}}
        <div class="flex gap-1 overflow-x-auto no-scrollbar -mx-4 px-4 lg:mx-0 lg:px-0" role="tablist">
            @foreach ($tabs as $key => $meta)
                <button
                    type="button"
                    role="tab"
                    wire:click="$set('tab', '{{ $key }}')"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    class="shrink-0 inline-flex items-center gap-1.5 h-10 px-3.5 rounded-control text-[14px] font-medium transition-colors
                        {{ $tab === $key ? 'bg-accent-50 text-accent-600' : 'text-ink-600 hover:bg-surface-2' }}"
                >
                    {{ $meta['label'] }}
                    <span class="tnum t-meta {{ $tab === $key ? 'opacity-70' : 'text-ink-400' }}">{{ $meta['count'] }}</span>
                </button>
            @endforeach
        </div>

        <x-ui.card :padding="false">
            @if ($rows->isEmpty())
                <x-ui.empty-state
                    :icon="match ($tab) { 'projects' => 'folder-kanban', 'tasks' => 'list-checks', 'credentials' => 'key-round', default => 'globe' }"
                    :headline="match ($tab) {
                        'projects' => 'No projects for this client',
                        'tasks' => 'No open tasks for this client',
                        'credentials' => 'No credentials stored',
                        default => 'No assets for this client',
                    }"
                    :body="match ($tab) {
                        'projects' => 'A project groups related work so progress is visible in one place.',
                        'tasks' => 'Anything the team owes this client shows here.',
                        'credentials' => 'Logins stored here are encrypted, and every reveal is logged.',
                        default => 'Domains, hosting, certificates and licences you look after for them.',
                    }"
                >
                    @if ($tab === 'assets')
                        @can('create', App\Models\Asset::class)
                            <x-ui.button variant="primary" icon="plus" wire:click="$dispatch('create-asset', { clientId: {{ $client->id }} })">
                                Add an asset
                            </x-ui.button>
                        @endcan
                    @endif
                </x-ui.empty-state>
            @else
                <div class="divide-y divide-ink-100">
                    @foreach ($rows as $row)
                        @if ($tab === 'assets')
                            <x-ui.list-row
                                wire:key="a-{{ $row->id }}"
                                :href="route('assets.show', $row)"
                                :icon="$row->type->icon()"
                                :title="$row->name"
                                :subtitle="$row->type->label().' · '.($row->provider ?: 'no provider').' · '.($row->owner?->name ?? 'unassigned')"
                                wire:navigate
                            >
                                <x-slot:trailing>
                                    <x-ui.badge :tone="$row->urgencyTone()" dot>{{ $row->urgencyLabel() }}</x-ui.badge>
                                </x-slot:trailing>
                            </x-ui.list-row>

                        @elseif ($tab === 'projects')
                            <x-ui.list-row
                                wire:key="p-{{ $row->id }}"
                                :href="route('projects.show', $row)"
                                icon="folder-kanban"
                                :title="$row->name"
                                :subtitle="$row->progress().'% done · '.($row->lead?->name ?? 'no lead').($row->deadline ? ' · due '.$row->deadline->format('j M') : '')"
                                wire:navigate
                            >
                                <x-slot:trailing>
                                    <x-ui.badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-ui.badge>
                                </x-slot:trailing>
                            </x-ui.list-row>

                        @elseif ($tab === 'tasks')
                            <x-ui.list-row
                                wire:key="t-{{ $row->id }}"
                                :href="route('tasks.show', $row)"
                                :title="$row->title"
                                :subtitle="$row->reference.' · '.($row->assignee?->name ?? 'Unassigned').($row->dueLabel() ? ' · '.$row->dueLabel() : '')"
                                wire:navigate
                            >
                                <x-slot:leading>
                                    @if ($row->assignee)
                                        <x-ui.avatar :name="$row->assignee->name" :id="$row->assignee->id" />
                                    @else
                                        <div class="grid place-items-center size-9 rounded-full bg-surface-2 text-ink-400">
                                            <x-icon name="user" class="size-4" />
                                        </div>
                                    @endif
                                </x-slot:leading>
                                <x-slot:trailing>
                                    <x-ui.badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-ui.badge>
                                </x-slot:trailing>
                            </x-ui.list-row>

                        @else
                            <x-ui.list-row
                                wire:key="c-{{ $row->id }}"
                                :href="route('vault.index')"
                                icon="key-round"
                                :title="$row->label"
                                :subtitle="$row->username ?: 'No username'"
                                wire:navigate
                            />
                        @endif
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    @can('update', $client)
        <livewire:clients.form />
    @endcan
    @can('create', App\Models\Asset::class)
        <livewire:assets.form />
    @endcan
</div>
