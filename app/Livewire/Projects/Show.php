<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function render(): View
    {
        $tasks = $this->project->tasks()
            ->visibleTo(auth()->user())
            ->with(['assignee:id,name'])
            ->orderByRaw('due_at is null, due_at asc')
            ->get();

        return view('livewire.projects.show', [
            'tasks' => $tasks,
            'done' => $tasks->where('status', TaskStatus::Completed)->count(),
            'overdue' => $tasks->filter->isOverdue()->count(),
        ])->title($this->project->name);
    }
}
