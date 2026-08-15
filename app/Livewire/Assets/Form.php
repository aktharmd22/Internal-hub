<?php

declare(strict_types=1);

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Form extends Component
{
    public ?int $assetId = null;

    public ?int $client_id = null;

    public string $type = 'domain';

    public string $name = '';

    public string $identifier = '';

    public string $provider = '';

    public string $provider_account = '';

    public string $expires_at = '';

    public string $purchased_at = '';

    public ?string $cost = null;

    public string $currency = 'INR';

    public string $billing_cycle = 'yearly';

    public bool $auto_renew = false;

    public ?int $owner_id = null;

    public bool $reminders_enabled = true;

    public string $notes = '';

    /** A duplicate we found on the same client, waiting on the user's call. */
    public ?array $duplicate = null;

    public bool $forceCreate = false;

    #[On('edit-asset')]
    public function edit(int $id): void
    {
        $asset = Asset::findOrFail($id);

        $this->authorize('update', $asset);

        $this->assetId = $asset->id;
        $this->client_id = $asset->client_id;
        $this->type = $asset->type->value;
        $this->name = $asset->name;
        $this->identifier = (string) $asset->identifier;
        $this->provider = (string) $asset->provider;
        $this->provider_account = (string) $asset->provider_account;
        $this->expires_at = $asset->expires_at->toDateString();
        $this->purchased_at = $asset->purchased_at?->toDateString() ?? '';
        $this->cost = $asset->cost ? (string) $asset->cost : null;
        $this->currency = $asset->currency;
        $this->billing_cycle = (string) $asset->billing_cycle;
        $this->auto_renew = $asset->auto_renew;
        $this->owner_id = $asset->owner_id;
        $this->reminders_enabled = $asset->reminders_enabled;
        $this->notes = (string) $asset->notes;

        $this->dispatch('open-modal', 'asset-form');
    }

    #[On('create-asset')]
    public function startNew(?int $clientId = null): void
    {
        $this->resetForm();
        $this->client_id = $clientId;
        $this->dispatch('open-modal', 'asset-form');
    }

    public function updatedIdentifier(): void
    {
        // Mirror the identifier into the name until someone types their own.
        if (blank($this->name) || $this->name === $this->previousIdentifier) {
            $this->name = $this->identifier;
        }

        $this->previousIdentifier = $this->identifier;
        $this->duplicate = null;
    }

    public string $previousIdentifier = '';

    public function save(): void
    {
        $this->assetId
            ? $this->authorize('update', Asset::findOrFail($this->assetId))
            : $this->authorize('create', Asset::class);

        $data = $this->validate();

        if (! $this->forceCreate && $this->findDuplicate()) {
            return;
        }

        $payload = [
            'client_id' => $data['client_id'],
            'type' => $data['type'],
            'name' => $data['name'],
            'identifier' => $data['identifier'] ?: null,
            'provider' => $data['provider'] ?: null,
            'provider_account' => $data['provider_account'] ?: null,
            'expires_at' => $data['expires_at'],
            'purchased_at' => $data['purchased_at'] ?: null,
            'cost' => $data['cost'] ?: null,
            'currency' => $data['currency'],
            'billing_cycle' => $data['billing_cycle'] ?: null,
            'auto_renew' => $this->auto_renew,
            'owner_id' => $data['owner_id'] ?: null,
            'reminders_enabled' => $this->reminders_enabled,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->assetId) {
            $asset = Asset::findOrFail($this->assetId);
            $asset->update($payload);
        } else {
            // Let the engine settle the real status on its next run; anything
            // new starts from what the date already implies.
            $asset = Asset::create($payload + ['status' => AssetStatus::Active]);
            $asset->forceFill(['status' => $asset->derivedStatus()])->save();
        }

        $this->dispatch('close-modal', 'asset-form');
        $this->dispatch('toast', message: $this->assetId ? 'Asset updated.' : 'Asset added.', tone: 'ok');
        $this->dispatch('asset-saved');

        $this->resetForm();
    }

    /**
     * Duplicate detection is scoped to the client: two clients can perfectly
     * well hold the same registrar account number.
     */
    private function findDuplicate(): bool
    {
        if (blank($this->identifier)) {
            return false;
        }

        $existing = Asset::query()
            ->where('client_id', $this->client_id)
            ->where('identifier', $this->identifier)
            ->when($this->assetId, fn ($q) => $q->whereKeyNot($this->assetId))
            ->with('client')
            ->first();

        if (! $existing) {
            return false;
        }

        $this->duplicate = [
            'id' => $existing->id,
            'name' => $existing->name,
            'expires_at' => $existing->expires_at->format('j M Y'),
            'type' => $existing->type->label(),
        ];

        return true;
    }

    public function mergeIntoExisting(): void
    {
        $existing = Asset::findOrFail($this->duplicate['id']);

        $this->authorize('update', $existing);

        $this->assetId = $existing->id;
        $this->duplicate = null;
        $this->forceCreate = false;

        $this->save();
    }

    public function createAnyway(): void
    {
        $this->forceCreate = true;
        $this->duplicate = null;
        $this->save();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'type' => ['required', Rule::enum(AssetType::class)],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'provider_account' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['required', 'date'],
            'purchased_at' => ['nullable', 'date', 'before_or_equal:expires_at'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_cycle' => ['nullable', 'string', 'max:20'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'client_id' => 'client',
            'expires_at' => 'expiry date',
            'owner_id' => 'owner',
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'assetId', 'client_id', 'name', 'identifier', 'provider', 'provider_account',
            'expires_at', 'purchased_at', 'cost', 'auto_renew', 'owner_id', 'notes',
            'duplicate', 'forceCreate', 'previousIdentifier',
        ]);

        $this->type = 'domain';
        $this->currency = 'INR';
        $this->billing_cycle = 'yearly';
        $this->reminders_enabled = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.assets.form', [
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'owners' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'assetType' => AssetType::from($this->type),
        ]);
    }
}
