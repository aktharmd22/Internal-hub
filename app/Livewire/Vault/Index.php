<?php

declare(strict_types=1);

namespace App\Livewire\Vault;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Credential;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Vault')]
class Index extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $client = '';

    public ?int $editingId = null;

    public ?int $client_id = null;

    public ?int $asset_id = null;

    public string $label = '';

    public string $username = '';

    public string $password = '';

    public string $url = '';

    public string $notes = '';

    /** The one credential currently revealed, and its plaintext. */
    public ?int $revealedId = null;

    public ?string $revealed = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Credential::class);
    }

    /**
     * Revealing is a separate action from viewing, and it is logged with the
     * actor, the time and the IP. A vault nobody can audit is a liability.
     */
    public function reveal(int $id): void
    {
        $credential = Credential::with('client')->findOrFail($id);

        $this->authorize('reveal', $credential);

        $credential->recordAccess(auth()->user());

        $this->revealedId = $id;
        $this->revealed = $credential->password;
    }

    public function hide(): void
    {
        $this->reset(['revealedId', 'revealed']);
    }

    public function newCredential(): void
    {
        $this->authorize('create', Credential::class);

        $this->reset(['editingId', 'client_id', 'asset_id', 'label', 'username', 'password', 'url', 'notes']);
        $this->resetValidation();
        $this->dispatch('open-modal', 'credential-form');
    }

    public function edit(int $id): void
    {
        $credential = Credential::findOrFail($id);

        $this->authorize('update', $credential);

        $this->editingId = $credential->id;
        $this->client_id = $credential->client_id;
        $this->asset_id = $credential->asset_id;
        $this->label = $credential->label;
        $this->username = (string) $credential->username;
        $this->password = (string) $credential->password;
        $this->url = (string) $credential->url;
        $this->notes = (string) $credential->notes;

        $credential->recordAccess(auth()->user());

        $this->dispatch('open-modal', 'credential-form');
    }

    public function save(): void
    {
        $this->editingId
            ? $this->authorize('update', Credential::findOrFail($this->editingId))
            : $this->authorize('create', Credential::class);

        $data = $this->validate();

        $payload = [
            'client_id' => $data['client_id'],
            'asset_id' => $data['asset_id'] ?: null,
            'label' => $data['label'],
            'username' => $data['username'] ?: null,
            'password' => $data['password'] ?: null,
            'url' => $data['url'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        $this->editingId
            ? Credential::findOrFail($this->editingId)->update($payload)
            : Credential::create($payload + ['created_by' => auth()->id()]);

        $this->dispatch('close-modal', 'credential-form');
        $this->dispatch('toast', message: 'Credential saved.', tone: 'ok');
        $this->reset(['editingId', 'client_id', 'asset_id', 'label', 'username', 'password', 'url', 'notes']);
    }

    public function delete(int $id): void
    {
        $credential = Credential::findOrFail($id);

        $this->authorize('delete', $credential);

        $credential->delete();

        $this->dispatch('toast', message: 'Credential deleted.', tone: 'ok');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'label' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return ['client_id' => 'client', 'asset_id' => 'asset'];
    }

    public function render(): View
    {
        return view('livewire.vault.index', [
            'credentials' => $this->credentials(),
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'assets' => $this->client_id
                ? Asset::query()->active()->where('client_id', $this->client_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /** @return Collection<int, Credential> */
    private function credentials(): Collection
    {
        return Credential::query()
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('label', 'like', "%{$this->search}%")
                ->orWhere('username', 'like', "%{$this->search}%")))
            ->when($this->client, fn ($q) => $q->where('client_id', $this->client))
            ->with(['client:id,name,company_name', 'asset:id,name'])
            ->orderBy('label')
            ->get();
    }
}
