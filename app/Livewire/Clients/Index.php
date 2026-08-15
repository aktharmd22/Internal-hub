<?php

declare(strict_types=1);

namespace App\Livewire\Clients;

use App\Enums\TaskStatus;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Clients')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Client::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.clients.index', [
            'clients' => $this->clients(),
        ]);
    }

    /** @return LengthAwarePaginator<Client> */
    private function clients(): LengthAwarePaginator
    {
        return Client::query()
            ->active()
            ->search($this->search)
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->with('accountManager:id,name')
            // Counted in the query rather than per row: a 150-client list would
            // otherwise fire 300 extra queries.
            ->withCount([
                'assets as assets_count' => fn (Builder $q) => $q->where('is_archived', false),
                'assets as expiring_count' => fn (Builder $q) => $q->where('is_archived', false)
                    ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays(30)->startOfDay()])
                    ->whereNotIn('status', ['renewed', 'cancelled']),
                'tasks as open_tasks_count' => fn (Builder $q) => $q->whereNotIn('status', TaskStatus::closedValues()),
            ])
            ->orderBy('name')
            ->paginate(25);
    }
}
