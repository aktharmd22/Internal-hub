<div class="px-4 lg:px-6 py-4 flex flex-col gap-4 max-w-3xl">

    <div class="flex gap-1 overflow-x-auto no-scrollbar -mx-4 px-4 lg:mx-0 lg:px-0" role="tablist">
        @foreach (['company' => 'Company', 'reminders' => 'Reminder rules', 'channels' => 'Channels', 'system' => 'System'] as $key => $label)
            <button
                type="button"
                role="tab"
                wire:click="$set('tab', '{{ $key }}')"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                class="shrink-0 h-10 px-3.5 rounded-control text-[14px] font-medium transition-colors
                    {{ $tab === $key ? 'bg-accent-50 text-accent-600' : 'text-ink-600 hover:bg-surface-2' }}"
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'company')
        <x-ui.card title="Logo" subtitle="Shown in the sidebar, on the sign-in screen and at the top of every email.">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4 mt-3">
                {{-- A checkered plate, so transparency reads as transparency
                     rather than as a white block. --}}
                <div
                    class="shrink-0 grid place-items-center h-20 w-40 rounded-card border border-ink-100 p-3"
                    style="background-image:
                        linear-gradient(45deg, var(--color-ink-100) 25%, transparent 25%),
                        linear-gradient(-45deg, var(--color-ink-100) 25%, transparent 25%),
                        linear-gradient(45deg, transparent 75%, var(--color-ink-100) 75%),
                        linear-gradient(-45deg, transparent 75%, var(--color-ink-100) 75%);
                        background-size: 12px 12px;
                        background-position: 0 0, 0 6px, 6px -6px, -6px 0;"
                >
                    @if (\App\Support\Brand::has())
                        <img src="{{ \App\Support\Brand::url() }}" alt="{{ \App\Support\Brand::name() }}" class="max-h-full max-w-full object-contain">
                    @else
                        <span class="grid place-items-center size-11 rounded-control bg-accent-600 text-on-solid">
                            <x-icon name="shield-check" class="size-6" />
                        </span>
                    @endif
                </div>

                <div class="min-w-0 flex-1 flex flex-col gap-2.5">
                    <label for="logo-file" class="t-sub font-medium text-ink-800">Upload a logo</label>

                    <input
                        id="logo-file"
                        type="file"
                        wire:model="logo"
                        accept=".svg,.png,.jpg,.jpeg,.webp"
                        class="w-full t-sub text-ink-600 file:mr-3 file:rounded-control file:border file:border-ink-200 file:bg-surface file:px-3 file:py-2 file:t-sub file:text-ink-800"
                    >

                    <p class="t-meta text-ink-600">
                        SVG, PNG, JPG or WebP, up to 2 MB. Square works best — it is never cropped.
                        A PNG of 512&times;512 or larger can also rebuild the app icons.
                    </p>

                    @error('logo')
                        <p class="t-meta text-danger-600 flex items-center gap-1">
                            <x-icon name="circle-alert" class="size-3.5" />{{ $message }}
                        </p>
                    @enderror

                    <div wire:loading wire:target="logo" class="t-meta text-ink-600">Reading the file…</div>

                    <div class="flex flex-wrap gap-2 mt-1">
                        <x-ui.button variant="primary" wire:click="uploadLogo" target="uploadLogo" :disabled="! $logo">
                            Save logo
                        </x-ui.button>

                        @if (\App\Support\Brand::has())
                            <x-ui.button variant="ghost" wire:click="removeLogo" wire:confirm="Remove the logo and go back to the default mark?">
                                Remove
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Company profile">
            <form wire:submit="saveCompany" class="flex flex-col gap-4 mt-3">
                <x-ui.field label="Company name" for="s-company" required wire:model="company_name" :error="$errors->first('company_name')" />

                <x-ui.field
                    label="Reminder send time"
                    for="s-time"
                    type="time"
                    required
                    hint="The scheduler fires at this time in Asia/Kolkata."
                    wire:model="reminder_send_time"
                    :error="$errors->first('reminder_send_time')"
                />

                <x-ui.field
                    label="Healthcheck ping URL"
                    for="s-healthcheck"
                    type="url"
                    placeholder="https://hc-ping.com/..."
                    hint="Pinged after every clean run. If the cron dies, this app cannot tell you — only the missing ping can."
                    wire:model="healthcheck_url"
                    :error="$errors->first('healthcheck_url')"
                />

                <div class="flex gap-2">
                    <x-ui.button variant="primary" type="submit" target="saveCompany">Save settings</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="testHealthcheck" target="testHealthcheck">Send a test ping</x-ui.button>
                </div>
            </form>
        </x-ui.card>

    @elseif ($tab === 'reminders')
        <x-ui.card title="Reminder rules" subtitle="A rule fires once per asset, per channel, per recipient. Duplicates are impossible by design." :padding="false" :flush="true">
            <x-slot:action>
                <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="newRule">Add rule</x-ui.button>
            </x-slot:action>

            <div class="divide-y divide-ink-100">
                @foreach ($rules as $rule)
                    <div wire:key="rule-{{ $rule->id }}" class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="t-body text-ink-950">
                                {{ $rule->describeTiming() }}
                                <span class="text-ink-400">·</span>
                                {{ $rule->recipient_scope->label() }}
                            </p>
                            <p class="t-meta text-ink-600 mt-0.5">
                                {{ $rule->asset_type?->label() ?? 'All types' }}
                                · {{ collect($rule->channels)->map(fn ($c) => App\Enums\ReminderChannel::from($c)->label())->implode(', ') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="toggleRule({{ $rule->id }})"
                            class="shrink-0 relative w-10 h-6 rounded-full transition-colors {{ $rule->is_active ? 'bg-accent-600' : 'bg-ink-200' }}"
                            role="switch"
                            aria-checked="{{ $rule->is_active ? 'true' : 'false' }}"
                            aria-label="{{ $rule->is_active ? 'Disable this rule' : 'Enable this rule' }}"
                        >
                            <span class="absolute top-0.5 size-5 rounded-full bg-white transition-all {{ $rule->is_active ? 'left-[1.125rem]' : 'left-0.5' }}"></span>
                        </button>

                        <x-ui.dropdown align="right" width="w-40">
                            <x-slot:trigger>
                                <button type="button" class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2">
                                    <x-icon name="ellipsis-vertical" class="size-4" label="Actions" />
                                </button>
                            </x-slot:trigger>
                            <x-ui.dropdown-item icon="pencil" wire:click="editRule({{ $rule->id }})">Edit</x-ui.dropdown-item>
                            <x-ui.dropdown-item icon="trash-2" tone="danger" wire:click="deleteRule({{ $rule->id }})">Remove</x-ui.dropdown-item>
                        </x-ui.dropdown>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

    @elseif ($tab === 'channels')
        <x-ui.card title="Delivery channels" subtitle="A channel with no credentials is skipped, not logged as sent.">
            <ul class="flex flex-col gap-2 mt-3">
                @foreach ($allChannels as $value => $label)
                    <li class="flex items-center gap-2.5 rounded-control border border-ink-100 px-3.5 py-2.5">
                        <x-icon
                            :name="in_array($value, $availableChannels, true) ? 'circle-check' : 'circle-alert'"
                            class="size-4 shrink-0 {{ in_array($value, $availableChannels, true) ? 'text-ok-600' : 'text-ink-400' }}"
                        />
                        <span class="t-sub text-ink-950 flex-1">{{ $label }}</span>
                        <span class="t-meta text-ink-400">
                            {{ in_array($value, $availableChannels, true) ? 'Ready' : 'Not configured' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.card title="WhatsApp Cloud API" subtitle="In India WhatsApp gets read and email frequently does not.">
            <form wire:submit="saveChannels" class="flex flex-col gap-4 mt-3">
                <x-ui.field label="Phone number ID" for="s-wa-id" wire:model="whatsapp_phone_number_id" :error="$errors->first('whatsapp_phone_number_id')" />
                <x-ui.field
                    label="Access token"
                    for="s-wa-token"
                    type="password"
                    hint="Stored encrypted. Leave the dots alone to keep the current token."
                    wire:model="whatsapp_token"
                />
                <div><x-ui.button variant="primary" type="submit" target="saveChannels">Save credentials</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card title="Push on this device" subtitle="Sound only works with the tab open. Push is what reaches you when it is closed.">
            <div class="mt-3" x-data="pushToggle">
                <x-ui.button variant="secondary" x-on:click="enable()" x-bind:disabled="busy || enabled">
                    <span x-show="! enabled">Enable push notifications</span>
                    <span x-show="enabled" x-cloak>Push is on for this device</span>
                </x-ui.button>
            </div>
        </x-ui.card>

    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([
                ['Queued jobs', $queueDepth, null],
                ['Failed jobs', $failedJobs, $failedJobs > 0 ? 'danger' : null],
                ['Backups', $backups['count'], null],
                ['Last backup', $backups['latest'] ?? 'None', null],
            ] as [$label, $value, $tone])
                <x-ui.card>
                    <p class="t-metric {{ $tone ? App\Support\Tone::metric($tone) : 'text-ink-950' }}">{{ $value }}</p>
                    <p class="t-sub text-ink-600 mt-1 leading-tight">{{ $label }}</p>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card title="How this runs" subtitle="Shared hosting: no Supervisor, no Redis, no persistent websocket process.">
            <dl class="flex flex-col gap-3 mt-3">
                @foreach ([
                    'Queue' => 'database driver, drained by a cron-launched worker every minute',
                    'Cache and sessions' => 'database driver',
                    'Scheduler' => 'a single cron entry running schedule:run every minute',
                    'Real-time' => 'Pusher Channels over the Pusher protocol',
                    'Backups' => 'spatie/laravel-backup, nightly at 01:30',
                ] as $label => $value)
                    <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-3">
                        <dt class="t-sub font-medium text-ink-950 sm:w-44 shrink-0">{{ $label }}</dt>
                        <dd class="t-sub text-ink-600">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Recent activity" :padding="false">
            <livewire:activity-feed :limit="15" />
        </x-ui.card>
    @endif

    <x-ui.modal name="rule-form" :title="$ruleId ? 'Edit reminder rule' : 'Add a reminder rule'">
        <form wire:submit="saveRule" class="flex flex-col gap-4" id="rule-form-element">
            <x-ui.field
                label="Timing (days before expiry)"
                for="r-days"
                type="number"
                required
                hint="Negative fires after expiry: -3 is three days overdue."
                wire:model="rule_days_before"
                :error="$errors->first('rule_days_before')"
            />

            <x-ui.field
                label="Applies to"
                for="r-type"
                type="select"
                placeholder="All asset types"
                :options="$assetTypes"
                wire:model="rule_asset_type"
                :error="$errors->first('rule_asset_type')"
            />

            <x-ui.field
                label="Send to"
                for="r-scope"
                type="select"
                required
                :options="$scopes"
                wire:model="rule_recipient_scope"
                :error="$errors->first('rule_recipient_scope')"
            />

            <div class="flex flex-col gap-2">
                <span class="t-sub font-medium text-ink-800">Channels</span>
                @foreach ($allChannels as $value => $label)
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" value="{{ $value }}" wire:model="rule_channels" class="size-4 rounded border-ink-200 text-accent-600 focus:ring-accent-500">
                        <span class="t-sub text-ink-950 flex-1">{{ $label }}</span>
                        @unless (in_array($value, $availableChannels, true))
                            <x-ui.badge tone="neutral" size="sm">Not configured</x-ui.badge>
                        @endunless
                    </label>
                @endforeach
                @error('rule_channels')
                    <p class="t-meta text-danger-600">{{ $message }}</p>
                @enderror
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'rule-form')">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" form="rule-form-element" target="saveRule">Save rule</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
