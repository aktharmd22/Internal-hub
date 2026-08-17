<div>
    @push('page-actions')
        @can('update', $asset)
            <x-ui.button variant="primary" size="sm" icon="refresh-cw" wire:click="renew" target="renew">
                <span class="max-sm:sr-only">Renew</span>
            </x-ui.button>

            <x-ui.dropdown align="right" width="w-56">
                <x-slot:trigger>
                    <button type="button" class="tap grid place-items-center rounded-control text-ink-600 hover:bg-surface-2">
                        <x-icon name="ellipsis-vertical" class="size-5" label="More actions" />
                    </button>
                </x-slot:trigger>
                <x-ui.dropdown-item icon="pencil" wire:click="$dispatch('edit-asset', { id: {{ $asset->id }} })">Edit details</x-ui.dropdown-item>
                @if ($asset->type->isVerifiable())
                    <x-ui.dropdown-item icon="shield-check" wire:click="verify">Check with the registry</x-ui.dropdown-item>
                @endif
                <x-ui.dropdown-item icon="bell" wire:click="toggleReminders">
                    {{ $asset->reminders_enabled ? 'Stop reminders' : 'Start reminders' }}
                </x-ui.dropdown-item>
                <x-ui.dropdown-item icon="trash-2" tone="danger" wire:click="archive" wire:confirm="Archive this asset? Reminders stop immediately.">
                    Archive
                </x-ui.dropdown-item>
            </x-ui.dropdown>
        @endcan
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">

        {{-- Headline ------------------------------------------------------- --}}
        <x-ui.card>
            <div class="flex items-start gap-3">
                <div class="shrink-0 grid place-items-center size-11 rounded-control bg-surface-2 text-ink-600">
                    <x-icon :name="$asset->type->icon()" class="size-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge :tone="$asset->urgencyTone()" dot>{{ $asset->urgencyLabel() }}</x-ui.badge>
                        <x-ui.badge tone="neutral" size="sm">{{ $asset->type->label() }}</x-ui.badge>
                        @unless ($asset->reminders_enabled)
                            <x-ui.badge tone="warn" size="sm">Reminders off</x-ui.badge>
                        @endunless
                    </div>

                    <p class="t-page-title text-ink-950 mt-2 break-words">{{ $asset->name }}</p>

                    <a href="{{ route('clients.show', $asset->client) }}" wire:navigate class="t-sub text-accent-600 hover:underline mt-1 inline-block">
                        {{ $asset->client->displayName() }}
                    </a>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 mt-5 pt-4 border-t border-ink-100">
                @foreach ([
                    'Expires' => $asset->expires_at->format('l, j F Y'),
                    'Provider' => $asset->provider ?: '—',
                    'Account' => $asset->provider_account ?: '—',
                    'Identifier' => $asset->identifier ?: '—',
                    'Cost' => $asset->cost ? $asset->currency.' '.number_format((float) $asset->cost, 2) : '—',
                    'Billing' => $asset->billing_cycle ? ucfirst($asset->billing_cycle) : '—',
                    'Owner' => $asset->owner?->name ?: 'Unassigned',
                    'Auto-renew' => $asset->auto_renew ? 'On' : 'Off',
                ] as $label => $value)
                    <div class="min-w-0">
                        <dt class="t-meta text-ink-400">{{ $label }}</dt>
                        <dd class="t-sub text-ink-950 mt-0.5 truncate tnum">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($asset->notes)
                <div class="mt-4 pt-4 border-t border-ink-100">
                    <p class="t-meta text-ink-400 mb-1">Notes</p>
                    <p class="t-sub text-ink-800 whitespace-pre-line">{{ $asset->notes }}</p>
                </div>
            @endif
        </x-ui.card>

        {{-- Verification ---------------------------------------------------- --}}
        @if ($asset->type->isVerifiable())
            <x-ui.card title="Registry check" subtitle="What the source of truth says, not what we typed.">
                <div class="flex items-center justify-between gap-3 mt-3">
                    <div class="min-w-0">
                        <x-ui.badge :tone="$asset->verification_status->tone()" dot>
                            {{ $asset->verification_status->label() }}
                        </x-ui.badge>
                        <p class="t-meta text-ink-600 mt-1.5">
                            @if ($asset->last_verified_at)
                                Checked {{ $asset->last_verified_at->diffForHumans() }}@if ($asset->verified_expires_at) · registry says {{ $asset->verified_expires_at->format('j M Y') }}@endif
                            @else
                                Not checked yet. Runs automatically at 04:00 daily.
                            @endif
                        </p>
                    </div>

                    @can('update', $asset)
                        <x-ui.button variant="secondary" size="sm" wire:click="verify" target="verify">Check now</x-ui.button>
                    @endcan
                </div>
            </x-ui.card>
        @endif

        {{-- Linked tasks ----------------------------------------------------- --}}
        @if ($tasks->isNotEmpty())
            <x-ui.card title="Tasks" :padding="false" :flush="true">
                <div class="divide-y divide-ink-100">
                    @foreach ($tasks as $task)
                        <x-ui.list-row
                            wire:key="task-{{ $task->id }}"
                            :href="route('tasks.show', $task)"
                            :title="$task->title"
                            :subtitle="$task->reference.' · '.($task->assignee?->name ?? 'Unassigned')"
                            wire:navigate
                        >
                            <x-slot:trailing>
                                <x-ui.badge :tone="$task->status->tone()">{{ $task->status->label() }}</x-ui.badge>
                            </x-slot:trailing>
                        </x-ui.list-row>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        {{-- Reminder history -------------------------------------------------- --}}
        <x-ui.card title="Reminders sent" subtitle="Proof of what went out, and to whom." :padding="false" :flush="true">
            @if ($reminders->isEmpty())
                <x-ui.empty-state
                    icon="bell"
                    headline="No reminders sent yet"
                    body="They start as the expiry date comes into range."
                />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($reminders as $log)
                        <li wire:key="log-{{ $log->id }}" class="flex items-center gap-3 px-4 py-3">
                            <x-icon
                                :name="$log->status === 'sent' ? 'circle-check' : 'circle-alert'"
                                class="size-4 shrink-0 {{ $log->status === 'sent' ? 'text-ok-600' : 'text-danger-600' }}"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="t-sub text-ink-950">
                                    {{ App\Enums\ReminderChannel::from($log->channel)->label() }}
                                    @if ($log->days_before < 0)
                                        · {{ abs($log->days_before) }} days overdue
                                    @elseif ($log->days_before === 0)
                                        · on the day
                                    @else
                                        · {{ $log->days_before }} days before
                                    @endif
                                </p>
                                <p class="t-meta text-ink-400">{{ $log->sent_at?->format('j M Y, g:i a') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- Change history ----------------------------------------------------- --}}
        @if ($history->isNotEmpty())
            <x-ui.card title="History" :padding="false" :flush="true">
                <ul class="divide-y divide-ink-100">
                    @foreach ($history as $entry)
                        <li wire:key="act-{{ $entry->id }}" class="px-4 py-3">
                            <p class="t-sub text-ink-950">
                                {{ $entry->causer?->name ?? 'The system' }} {{ $entry->description }}
                            </p>
                            <p class="t-meta text-ink-400 mt-0.5">{{ $entry->created_at->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>

    @can('update', $asset)
        <livewire:assets.form />
    @endcan
</div>
