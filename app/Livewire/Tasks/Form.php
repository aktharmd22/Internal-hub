<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Form extends Component
{
    public ?int $taskId = null;

    public string $title = '';

    public string $description = '';

    public ?int $client_id = null;

    public ?int $project_id = null;

    public ?int $assigned_to = null;

    public string $priority = 'normal';

    public string $due_at = '';

    public ?int $estimated_minutes = null;

    public ?int $parent_id = null;

    #[On('edit-task')]
    public function edit(int $id): void
    {
        $task = Task::findOrFail($id);

        $this->authorize('update', $task);

        $this->taskId = $task->id;
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->client_id = $task->client_id;
        $this->project_id = $task->project_id;
        $this->assigned_to = $task->assigned_to;
        $this->priority = $task->priority->value;
        $this->due_at = $task->due_at?->format('Y-m-d') ?? '';
        $this->estimated_minutes = $task->estimated_minutes;

        $this->dispatch('open-modal', 'task-form');
    }

    #[On('create-subtask')]
    public function createSubtask(int $parentId): void
    {
        $parent = Task::findOrFail($parentId);

        $this->authorize('update', $parent);

        $this->resetForm();
        $this->parent_id = $parent->id;
        $this->client_id = $parent->client_id;
        $this->project_id = $parent->project_id;

        $this->dispatch('open-modal', 'task-form');
    }

    public function save(): void
    {
        $this->taskId
            ? $this->authorize('update', Task::findOrFail($this->taskId))
            : $this->authorize('create', Task::class);

        $data = $this->validate();

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'client_id' => $data['client_id'] ?: null,
            'project_id' => $data['project_id'] ?: null,
            'assigned_to' => $data['assigned_to'] ?: null,
            'priority' => $data['priority'],
            'due_at' => $data['due_at'] ? Carbon::parse($data['due_at'])->setTime(17, 0) : null,
            'estimated_minutes' => $data['estimated_minutes'] ?: null,
        ];

        if ($this->taskId) {
            $task = Task::findOrFail($this->taskId);
            $previousAssignee = $task->assigned_to;

            $task->update($payload + ['last_activity_at' => now()]);

            if ($task->assigned_to && $task->assigned_to !== $previousAssignee) {
                $this->announceAssignment($task);
            }
        } else {
            $task = Task::create($payload + [
                'parent_id' => $this->parent_id,
                'created_by' => auth()->id(),
                'source' => TaskSource::Manual,
                // Assigning at creation skips `open`: the work already has
                // an owner, and `open` would be a lie in the history.
                'status' => $this->assigned_to ? TaskStatus::Assigned : TaskStatus::Open,
            ]);

            TaskStatusLog::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'from_status' => null,
                'to_status' => $task->status,
            ]);

            if ($task->assigned_to) {
                $this->announceAssignment($task);
            }
        }

        $this->dispatch('close-modal', 'task-form');
        $this->dispatch('toast', message: $this->taskId ? 'Task updated.' : "{$task->reference} created.", tone: 'ok');
        $this->dispatch('task-saved');

        $this->resetForm();
    }

    private function announceAssignment(Task $task): void
    {
        $task->participants()->syncWithoutDetaching([
            $task->assigned_to => ['role' => 'assignee'],
        ]);

        if ($task->assigned_to !== auth()->id()) {
            $task->assignee?->notify(new TaskAssigned($task, auth()->user()));
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:5', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'client_id' => 'client',
            'project_id' => 'project',
            'assigned_to' => 'assignee',
            'due_at' => 'due date',
            'estimated_minutes' => 'estimate',
        ];
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->priority = 'normal';
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.tasks.form', [
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'projects' => Project::query()
                ->active()
                ->when($this->client_id, fn ($q) => $q->where('client_id', $this->client_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
