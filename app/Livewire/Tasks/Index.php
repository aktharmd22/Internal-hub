<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Actions\TaskStatusTransition;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Tasks')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'mine')]
    public string $filter = 'mine';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: 'list')]
    public string $view = 'list';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    /**
     * The list/board switch lives in the topbar, which renders outside this
     * component's root, so wire:click cannot bind to it.
     */
    #[On('tasks:set-view')]
    public function setView(string $view): void
    {
        $this->view = in_array($view, ['list', 'board'], true) ? $view : 'list';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'client']);
        $this->filter = 'mine';
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return filled($this->search) || filled($this->status) || filled($this->client) || $this->filter !== 'mine';
    }

    /**
     * Board drag-and-drop. Every move goes through the same transition action
     * as the detail screen, so the review gate cannot be sidestepped by
     * dropping a card into the Completed column.
     */
    public function moveTo(int $taskId, string $status, TaskStatusTransition $transition): void
    {
        $task = Task::query()->visibleTo(auth()->user())->findOrFail($taskId);
        $target = TaskStatus::from($status);

        if ($target->requiresReason()) {
            $this->dispatch('toast', message: 'Open the task to move it there — it needs a reason.', tone: 'warn');

            return;
        }

        try {
            $transition($task, $target, auth()->user());
            $this->dispatch('toast', message: "{$task->reference} is now {$target->label()}.", tone: 'ok');
        } catch (AuthorizationException|ValidationException $e) {
            $this->dispatch('toast', message: $this->firstMessage($e), tone: 'danger');
        }
    }

    private function firstMessage(\Throwable $e): string
    {
        return $e instanceof ValidationException
            ? collect($e->errors())->flatten()->first()
            : $e->getMessage();
    }

    public function render(): View
    {
        return view('livewire.tasks.index', [
            'tasks' => $this->view === 'board' ? collect() : $this->paginated(),
            'board' => $this->view === 'board' ? $this->board() : collect(),
            'clients' => Client::query()->active()->orderBy('name')->get(['id', 'name', 'company_name']),
            'counts' => $this->counts(),
        ]);
    }

    private function baseQuery(): Builder
    {
        return Task::query()
            ->active()
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->client, fn (Builder $q) => $q->where('client_id', $this->client))
            ->tap(fn (Builder $q) => $this->applyFilter($q))
            ->with(['client:id,name,company_name', 'assignee:id,name', 'project:id,name']);
    }

    private function applyFilter(Builder $query): void
    {
        match ($this->filter) {
            'mine' => $query->where('assigned_to', auth()->id())->whereNotIn('status', TaskStatus::closedValues()),
            'unassigned' => $query->whereNull('assigned_to')->whereNotIn('status', TaskStatus::closedValues()),
            'overdue' => $query->overdue(),
            'review' => $query->awaitingReview(),
            'open' => $query->whereNotIn('status', TaskStatus::closedValues()),
            default => null,
        };
    }

    /** @return LengthAwarePaginator<Task> */
    private function paginated(): LengthAwarePaginator
    {
        return $this->baseQuery()
            // Nulls last, then soonest first: a task with no date is not urgent,
            // it is unplanned, and it belongs at the bottom.
            ->orderByRaw('due_at is null, due_at asc')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /** @return Collection<string, Collection<int, Task>> */
    private function board(): Collection
    {
        $tasks = $this->baseQuery()
            ->whereIn('status', array_map(fn (TaskStatus $s) => $s->value, TaskStatus::boardColumns()))
            ->orderByRaw('due_at is null, due_at asc')
            ->limit(300)
            ->get();

        return collect(TaskStatus::boardColumns())
            ->mapWithKeys(fn (TaskStatus $status) => [
                $status->value => $tasks->where('status', $status)->values(),
            ]);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $user = auth()->user();
        $base = fn () => Task::query()->active()->visibleTo($user);

        return [
            'mine' => (clone $base())->where('assigned_to', $user->id)->whereNotIn('status', TaskStatus::closedValues())->count(),
            'unassigned' => (clone $base())->whereNull('assigned_to')->whereNotIn('status', TaskStatus::closedValues())->count(),
            'overdue' => (clone $base())->overdue()->count(),
            'review' => (clone $base())->awaitingReview()->count(),
        ];
    }
}
