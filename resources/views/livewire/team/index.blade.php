<div>
    {{-- Pushed into the topbar, which lives outside this component's root, so
         wire:click would never bind. Livewire.dispatch crosses that boundary. --}}
    @push('page-actions')
        <x-ui.button variant="primary" size="sm" icon="plus" x-on:click="Livewire.dispatch('team:new-user')">
            <span class="max-sm:sr-only">Add person</span>
        </x-ui.button>
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">
        <x-ui.card :padding="false" title="The team" subtitle="On-time and reopen rates only mean something next to the volume behind them." :flush="true">
            <div class="divide-y divide-ink-100">
                @foreach ($people as $row)
                    @php $person = $row['user']; @endphp

                    <div wire:key="person-{{ $person->id }}" class="flex items-start gap-3 px-4 py-3.5">
                        <x-ui.avatar :name="$person->name" :id="$person->id" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="t-body font-medium text-ink-950 truncate">{{ $person->name }}</p>
                                <x-ui.badge tone="neutral" size="sm">{{ $person->role()?->label() ?? 'No role' }}</x-ui.badge>
                                @unless ($person->is_active)
                                    <x-ui.badge tone="danger" size="sm">Deactivated</x-ui.badge>
                                @endunless
                            </div>

                            <p class="t-meta text-ink-600 truncate mt-0.5">{{ $person->email }}</p>

                            <dl class="flex flex-wrap gap-x-5 gap-y-1 mt-2.5">
                                @foreach ([
                                    ['Open', $row['open'], null],
                                    ['Completed', $row['completed'], null],
                                    ['On time', $row['onTimePercent'] === null ? '—' : $row['onTimePercent'].'%', $row['onTimePercent'] !== null && $row['onTimePercent'] < 70 ? 'warn' : null],
                                    ['Reopened', $row['reopenRate'] === null ? '—' : $row['reopenRate'].'%', $row['reopenRate'] !== null && $row['reopenRate'] > 20 ? 'danger' : null],
                                ] as [$label, $value, $tone])
                                    <div>
                                        <dt class="t-meta text-ink-400">{{ $label }}</dt>
                                        <dd class="t-sub tnum {{ $tone ? App\Support\Tone::text($tone) : 'text-ink-950' }}">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        <x-ui.dropdown align="right" width="w-52">
                            <x-slot:trigger>
                                <button type="button" class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2">
                                    <x-icon name="ellipsis-vertical" class="size-4" label="Actions" />
                                </button>
                            </x-slot:trigger>
                            <x-ui.dropdown-item icon="pencil" wire:click="edit({{ $person->id }})">Edit account</x-ui.dropdown-item>
                            @if ($person->id !== auth()->id())
                                <x-ui.dropdown-item
                                    :icon="$person->is_active ? 'log-out' : 'check'"
                                    :tone="$person->is_active ? 'danger' : 'neutral'"
                                    wire:click="toggleActive({{ $person->id }})"
                                >
                                    {{ $person->is_active ? 'Deactivate' : 'Reactivate' }}
                                </x-ui.dropdown-item>
                            @endif
                        </x-ui.dropdown>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </div>

    <x-ui.modal name="user-form" :title="$editingId ? 'Edit account' : 'Add someone to the team'">
        <form wire:submit="save" class="flex flex-col gap-4" id="user-form-element">
            <x-ui.field label="Name" for="user-name" required wire:model="name" :error="$errors->first('name')" />
            <x-ui.field label="Email" for="user-email" type="email" required wire:model="email" :error="$errors->first('email')" />
            <x-ui.field label="Phone" for="user-phone" type="tel" hint="Used for WhatsApp reminders." wire:model="phone" :error="$errors->first('phone')" />

            <div class="flex flex-col gap-2">
                <span class="t-sub font-medium text-ink-800">Role</span>
                @foreach (App\Enums\Role::cases() as $case)
                    <label class="flex items-start gap-2.5 cursor-pointer rounded-control border border-ink-100 px-3.5 py-2.5">
                        <input type="radio" value="{{ $case->value }}" wire:model="role" name="role" class="mt-0.5 size-4 border-ink-200 text-accent-600 focus:ring-accent-500">
                        <span class="min-w-0">
                            <span class="block t-sub text-ink-950">{{ $case->label() }}</span>
                            <span class="block t-meta text-ink-600">{{ $case->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @unless ($editingId)
                <p class="t-meta text-ink-600 rounded-control bg-surface-2 px-3.5 py-2.5">
                    No password is set or emailed. They use "Forgot password" on the sign-in screen to choose their own.
                </p>
            @endunless
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'user-form')">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" form="user-form-element" target="save">
                {{ $editingId ? 'Save changes' : 'Create account' }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
