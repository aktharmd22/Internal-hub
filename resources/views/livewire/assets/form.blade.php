<div>
    <x-ui.modal
        name="asset-form"
        :title="$assetId ? 'Edit asset' : 'Add an asset'"
        subtitle="Expiry dates drive every reminder, so get that one right."
        width="lg"
    >
        @if ($duplicate)
            <div class="rounded-control bg-warn-50 px-3.5 py-3 mb-4">
                <p class="t-sub text-warn-600 font-medium">This client already has that identifier</p>
                <p class="t-sub text-warn-600 mt-1">
                    {{ $duplicate['type'] }} · {{ $duplicate['name'] }}, expiring {{ $duplicate['expires_at'] }}.
                </p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <x-ui.button size="sm" variant="secondary" wire:click="mergeIntoExisting">
                        Update that one instead
                    </x-ui.button>
                    <x-ui.button size="sm" variant="ghost" wire:click="createAnyway">
                        Add a second one
                    </x-ui.button>
                </div>
            </div>
        @endif

        <form wire:submit="save" class="flex flex-col gap-4" id="asset-form-element">
            <x-ui.field
                label="Client"
                for="asset-client"
                type="select"
                required
                placeholder="Choose a client"
                :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()"
                wire:model="client_id"
                :error="$errors->first('client_id')"
            />

            <div class="grid grid-cols-2 gap-3">
                <x-ui.field
                    label="Type"
                    for="asset-type"
                    type="select"
                    required
                    :options="App\Enums\AssetType::options()"
                    wire:model.live="type"
                    :error="$errors->first('type')"
                />

                <x-ui.field
                    label="Expires on"
                    for="asset-expires"
                    type="date"
                    required
                    wire:model="expires_at"
                    :error="$errors->first('expires_at')"
                />
            </div>

            <x-ui.field
                label="Identifier"
                for="asset-identifier"
                :hint="$assetType->identifierHint()"
                wire:model.blur="identifier"
                :error="$errors->first('identifier')"
            />

            <x-ui.field
                label="Display name"
                for="asset-name"
                required
                hint="What the team will recognise in a list."
                wire:model="name"
                :error="$errors->first('name')"
            />

            <div class="grid grid-cols-2 gap-3">
                <x-ui.field
                    label="Provider"
                    for="asset-provider"
                    placeholder="GoDaddy"
                    wire:model="provider"
                    :error="$errors->first('provider')"
                />
                <x-ui.field
                    label="Provider account"
                    for="asset-provider-account"
                    wire:model="provider_account"
                    :error="$errors->first('provider_account')"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-ui.field
                    label="Cost"
                    for="asset-cost"
                    type="number"
                    step="0.01"
                    placeholder="1200"
                    wire:model="cost"
                    :error="$errors->first('cost')"
                />
                <x-ui.field
                    label="Billing cycle"
                    for="asset-cycle"
                    type="select"
                    :options="['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half-yearly' => 'Half-yearly', 'yearly' => 'Yearly', 'biennial' => 'Every two years']"
                    wire:model="billing_cycle"
                    :error="$errors->first('billing_cycle')"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-ui.field
                    label="Owner"
                    for="asset-owner"
                    type="select"
                    placeholder="Unassigned"
                    hint="Who gets the reminders."
                    :options="$owners->pluck('name', 'id')->all()"
                    wire:model="owner_id"
                    :error="$errors->first('owner_id')"
                />
                <x-ui.field
                    label="Purchased on"
                    for="asset-purchased"
                    type="date"
                    wire:model="purchased_at"
                    :error="$errors->first('purchased_at')"
                />
            </div>

            <div class="flex flex-col gap-2.5 rounded-control border border-ink-100 px-3.5 py-3">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="reminders_enabled" class="size-4 rounded border-ink-200 text-accent-600 focus:ring-accent-500">
                    <span class="min-w-0">
                        <span class="block t-sub text-ink-950">Send reminders</span>
                        <span class="block t-meta text-ink-600">Turn off only for assets somebody else looks after.</span>
                    </span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="auto_renew" class="size-4 rounded border-ink-200 text-accent-600 focus:ring-accent-500">
                    <span class="min-w-0">
                        <span class="block t-sub text-ink-950">Auto-renew is on</span>
                        <span class="block t-meta text-ink-600">Reminders still go out — a failed card is why.</span>
                    </span>
                </label>
            </div>

            <x-ui.field
                label="Notes"
                for="asset-notes"
                type="textarea"
                rows="3"
                placeholder="Anything the next person needs to know"
                wire:model="notes"
                :error="$errors->first('notes')"
            />
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'asset-form')">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" form="asset-form-element" target="save">
                {{ $assetId ? 'Save changes' : 'Add asset' }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
