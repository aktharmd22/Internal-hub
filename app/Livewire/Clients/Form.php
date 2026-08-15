<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Form extends Component
{
    public ?int $clientId = null;

    public string $name = '';

    public string $company_name = '';

    public string $email = '';

    public string $alt_email = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $address = '';

    public string $gst_number = '';

    public ?int $account_manager_id = null;

    public string $status = 'active';

    public bool $send_renewal_notices = false;

    public string $notes = '';

    #[On('edit-client')]
    public function edit(int $id): void
    {
        $client = Client::findOrFail($id);

        $this->authorize('update', $client);

        $this->clientId = $client->id;

        foreach (['name', 'company_name', 'email', 'alt_email', 'phone', 'whatsapp', 'address', 'gst_number', 'notes'] as $field) {
            $this->{$field} = (string) $client->{$field};
        }

        $this->account_manager_id = $client->account_manager_id;
        $this->status = $client->status->value;
        $this->send_renewal_notices = $client->send_renewal_notices;

        $this->dispatch('open-modal', 'client-form');
    }

    public function save(): void
    {
        $this->clientId
            ? $this->authorize('update', Client::findOrFail($this->clientId))
            : $this->authorize('create', Client::class);

        $data = $this->validate();

        $payload = collect($data)
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all() + [
                'send_renewal_notices' => $this->send_renewal_notices,
            ];

        $this->clientId
            ? Client::findOrFail($this->clientId)->update($payload)
            : Client::create($payload);

        $this->dispatch('close-modal', 'client-form');
        $this->dispatch('toast', message: $this->clientId ? 'Client updated.' : 'Client added.', tone: 'ok');
        $this->dispatch('client-saved');

        $this->resetForm();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'alt_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return ['account_manager_id' => 'account manager', 'gst_number' => 'GST number'];
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->status = 'active';
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.clients.form', [
            'managers' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
