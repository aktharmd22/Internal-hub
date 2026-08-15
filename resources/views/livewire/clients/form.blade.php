<div>
    <x-ui.modal name="client-form" :title="$clientId ? 'Edit client' : 'Add a client'" width="lg">
        <form wire:submit="save" class="flex flex-col gap-4" id="client-form-element">
            <x-ui.field
                label="Contact name"
                for="client-name"
                required
                wire:model="name"
                :error="$errors->first('name')"
            />

            <x-ui.field
                label="Company"
                for="client-company"
                hint="Shown everywhere in place of the contact name when set."
                wire:model="company_name"
                :error="$errors->first('company_name')"
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.field label="Email" for="client-email" type="email" wire:model="email" :error="$errors->first('email')" />
                <x-ui.field label="Alternate email" for="client-alt-email" type="email" wire:model="alt_email" :error="$errors->first('alt_email')" />
                <x-ui.field label="Phone" for="client-phone" type="tel" wire:model="phone" :error="$errors->first('phone')" />
                <x-ui.field label="WhatsApp" for="client-whatsapp" type="tel" wire:model="whatsapp" :error="$errors->first('whatsapp')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.field
                    label="Account manager"
                    for="client-manager"
                    type="select"
                    placeholder="Unassigned"
                    :options="$managers->pluck('name', 'id')->all()"
                    wire:model="account_manager_id"
                    :error="$errors->first('account_manager_id')"
                />
                <x-ui.field
                    label="Status"
                    for="client-status"
                    type="select"
                    :options="App\Enums\ClientStatus::options()"
                    wire:model="status"
                    :error="$errors->first('status')"
                />
            </div>

            <x-ui.field label="GST number" for="client-gst" wire:model="gst_number" :error="$errors->first('gst_number')" />

            <x-ui.field label="Address" for="client-address" type="textarea" rows="2" wire:model="address" :error="$errors->first('address')" />

            <label class="flex items-center gap-2.5 cursor-pointer rounded-control border border-ink-100 px-3.5 py-3">
                <input type="checkbox" wire:model="send_renewal_notices" class="size-4 rounded border-ink-200 text-accent-600 focus:ring-accent-500">
                <span class="min-w-0">
                    <span class="block t-sub text-ink-950">Send this client renewal notices</span>
                    <span class="block t-meta text-ink-600">A heads-up 15 days out with the amount. Off unless they ask for it.</span>
                </span>
            </label>

            <x-ui.field label="Notes" for="client-notes" type="textarea" rows="3" wire:model="notes" :error="$errors->first('notes')" />
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'client-form')">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" form="client-form-element" target="save">
                {{ $clientId ? 'Save changes' : 'Add client' }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
