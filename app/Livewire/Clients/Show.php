<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Client $client;

    #[Url(except: 'assets')]
    public string $tab = 'assets';

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);

        $this->client = $client;
    }

    #[On('client-saved')]
    #[On('asset-saved')]
    public function refreshClient(): void
    {
        $this->client->refresh();
    }

    public function render(): View
    {
        return view('livewire.clients.show', [
            'tabs' => $this->tabs(),
            'rows' => $this->rows(),
        ])->title($this->client->displayName());
    }

    /** @return array<string, array{label: string, count: int}> */
    private function tabs(): array
    {
        $tabs = [
            'assets' => ['label' => 'Assets', 'count' => $this->client->assets()->where('is_archived', false)->count()],
            'projects' => ['label' => 'Projects', 'count' => $this->client->projects()->where('is_archived', false)->count()],
            'tasks' => ['label' => 'Tasks', 'count' => $this->client->tasks()->whereNotIn('status', TaskStatus::closedValues())->count()],
        ];

        if (auth()->user()->can(Permissions::VIEW_CREDENTIALS)) {
            $tabs['credentials'] = ['label' => 'Vault', 'count' => $this->client->credentials()->count()];
        }

        return $tabs;
    }

    private function rows(): Collection
    {
        return match ($this->tab) {
            'projects' => $this->client->projects()
                ->where('is_archived', false)
                ->with('lead:id,name')
                ->withCount([
                    'tasks',
                    'tasks as completed_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Completed),
                ])
                ->latest()
                ->get(),

            'tasks' => $this->client->tasks()
                ->visibleTo(auth()->user())
                ->whereNotIn('status', TaskStatus::closedValues())
                ->with(['assignee:id,name'])
                ->orderByRaw('due_at is null, due_at asc')
                ->get(),

            'credentials' => auth()->user()->can(Permissions::VIEW_CREDENTIALS)
                ? $this->client->credentials()->with('asset:id,name')->orderBy('label')->get()
                : collect(),

            default => $this->client->assets()
                ->where('is_archived', false)
                ->with('owner:id,name')
                ->orderBy('expires_at')
                ->get(),
        };
    }
}
