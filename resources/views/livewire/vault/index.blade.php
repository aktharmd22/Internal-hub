<div x-data="clipboardVault()">
    @push('page-actions')
        @can('create', App\Models\Credential::class)
            <x-ui.button variant="primary" size="sm" icon="plus" wire:click="newCredential">
                <span class="max-sm:sr-only">Add credential</span>
            </x-ui.button>
        @endcan
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">

        <div class="rounded-control bg-warn-50 px-3.5 py-3">
            <p class="t-sub text-warn-600">
                Every reveal here is written to the activity log with your name, the time and your IP address.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-icon name="search" class="size-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by label or username"
                    class="w-full h-11 md:h-10 pl-9 pr-3 rounded-control border border-ink-200 bg-surface text-ink-950 placeholder:text-ink-400"
                >
            </div>

            <select wire:model.live="client" class="h-11 md:h-10 px-3 rounded-control border border-ink-200 bg-surface text-ink-950 sm:w-52" aria-label="Filter by client">
                <option value="">All clients</option>
                @foreach ($clients as $option)
                    <option value="{{ $option->id }}">{{ $option->displayName() }}</option>
                @endforeach
            </select>
        </div>

        @if ($credentials->isEmpty())
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="key-round"
                    headline="Nothing stored yet"
                    body="Client logins kept here are encrypted at rest, and every read is recorded."
                >
                    @can('create', App\Models\Credential::class)
                        <x-ui.button variant="primary" icon="plus" wire:click="newCredential">Add a credential</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <x-ui.card :padding="false">
                <div class="divide-y divide-ink-100">
                    @foreach ($credentials as $credential)
                        <div wire:key="cred-{{ $credential->id }}" class="px-4 py-3.5">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 grid place-items-center size-9 rounded-control bg-surface-2 text-ink-600">
                                    <x-icon name="key-round" class="size-4" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="t-body font-medium text-ink-950 truncate">{{ $credential->label }}</p>
                                    <p class="t-sub text-ink-600 truncate mt-0.5">
                                        {{ $credential->client->displayName() }}
                                        @if ($credential->username) · {{ $credential->username }} @endif
                                    </p>

                                    @if ($credential->url)
                                        <a href="{{ $credential->url }}" target="_blank" rel="noopener" class="t-meta text-accent-600 hover:underline mt-1 inline-block truncate max-w-full">
                                            {{ $credential->url }}
                                        </a>
                                    @endif

                                    @if ($revealedId === $credential->id)
                                        <div class="flex items-center gap-2 mt-2.5 rounded-control bg-surface-2 px-3 py-2">
                                            <code class="t-sub text-ink-950 font-mono break-all flex-1">{{ $revealed }}</code>
                                            <button
                                                type="button"
                                                x-on:click="copy(@js($revealed))"
                                                class="shrink-0 t-meta text-accent-600 hover:underline"
                                            >Copy</button>
                                        </div>
                                        <p class="t-meta text-ink-400 mt-1.5" x-show="countdown > 0" x-cloak>
                                            Clipboard clears in <span x-text="countdown"></span>s
                                        </p>
                                    @endif
                                </div>

                                <div class="shrink-0 flex items-center gap-1">
                                    @if ($revealedId === $credential->id)
                                        <x-ui.button size="sm" variant="ghost" wire:click="hide">Hide</x-ui.button>
                                    @else
                                        <x-ui.button size="sm" variant="secondary" wire:click="reveal({{ $credential->id }})">Reveal</x-ui.button>
                                    @endif

                                    @can('update', $credential)
                                        <x-ui.dropdown align="right" width="w-44">
                                            <x-slot:trigger>
                                                <button type="button" class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2">
                                                    <x-icon name="ellipsis-vertical" class="size-4" label="Actions" />
                                                </button>
                                            </x-slot:trigger>
                                            <x-ui.dropdown-item icon="pencil" wire:click="edit({{ $credential->id }})">Edit</x-ui.dropdown-item>
                                            <x-ui.dropdown-item icon="trash-2" tone="danger" wire:click="delete({{ $credential->id }})" wire:confirm="Delete this credential?">
                                                Delete
                                            </x-ui.dropdown-item>
                                        </x-ui.dropdown>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    </div>

    <x-ui.modal name="credential-form" :title="$editingId ? 'Edit credential' : 'Add a credential'">
        <form wire:submit="save" class="flex flex-col gap-4" id="credential-form-element">
            <x-ui.field
                label="Client"
                for="cred-client"
                type="select"
                required
                placeholder="Choose a client"
                :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()"
                wire:model.live="client_id"
                :error="$errors->first('client_id')"
            />

            @if ($assets->isNotEmpty())
                <x-ui.field
                    label="Asset"
                    for="cred-asset"
                    type="select"
                    placeholder="Not tied to one asset"
                    :options="$assets->pluck('name', 'id')->all()"
                    wire:model="asset_id"
                    :error="$errors->first('asset_id')"
                />
            @endif

            <x-ui.field label="Label" for="cred-label" required placeholder="cPanel" wire:model="label" :error="$errors->first('label')" />
            <x-ui.field label="Username" for="cred-username" wire:model="username" :error="$errors->first('username')" />
            <x-ui.field label="Password" for="cred-password" type="password" wire:model="password" :error="$errors->first('password')" />
            <x-ui.field label="URL" for="cred-url" type="url" placeholder="https://" wire:model="url" :error="$errors->first('url')" />
            <x-ui.field label="Notes" for="cred-notes" type="textarea" rows="3" wire:model="notes" :error="$errors->first('notes')" />
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'credential-form')">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" form="credential-form-element" target="save">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
