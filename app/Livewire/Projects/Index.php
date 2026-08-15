<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Projects')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.projects.index', ['projects' => $this->projects()]);
    }

    /** @return LengthAwarePaginator<Project> */
    private function projects(): LengthAwarePaginator
    {
        return Project::query()
            ->active()
            ->when($this->search, fn (Builder $q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->with(['client:id,name,company_name', 'lead:id,name'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
            ])
            ->orderByRaw('deadline is null, deadline asc')
            ->paginate(25);
    }
}
