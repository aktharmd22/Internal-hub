<div class="px-4 lg:px-6 py-4 flex flex-col gap-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <x-icon name="search" class="size-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search projects"
                class="w-full h-11 md:h-10 pl-9 pr-3 rounded-control border border-ink-200 bg-surface text-ink-950 placeholder:text-ink-400"
            >
        </div>

        <select wire:model.live="status" class="h-11 md:h-10 px-3 rounded-control border border-ink-200 bg-surface text-ink-950 sm:w-44" aria-label="Filter by status">
            <option value="">All statuses</option>
            @foreach (App\Enums\ProjectStatus::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @if ($projects->isEmpty())
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="folder-kanban"
                headline="No projects yet"
                body="A project groups related tasks so progress against a deadline is visible in one place."
            />
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="divide-y divide-ink-100">
                @foreach ($projects as $project)
                    <x-ui.list-row
                        wire:key="proj-{{ $project->id }}"
                        :href="route('projects.show', $project)"
                        icon="folder-kanban"
                        wire:navigate
                    >
                        <x-slot:body>
                            <p class="t-body font-medium text-ink-950 truncate">{{ $project->name }}</p>
                            <p class="t-sub text-ink-600 truncate mt-0.5">
                                {{ $project->client->displayName() }}
                                · {{ $project->completed_tasks_count }}/{{ $project->tasks_count }} done
                                @if ($project->lead) · {{ $project->lead->name }} @endif
                            </p>

                            <div class="flex h-1.5 rounded-full overflow-hidden bg-ink-100 mt-2 max-w-48">
                                <div class="bg-ok-600" style="width: {{ $project->progress() }}%"></div>
                            </div>
                        </x-slot:body>

                        <x-slot:trailing>
                            @if ($project->isOverdue())
                                <x-ui.badge tone="danger" dot>Past deadline</x-ui.badge>
                            @else
                                <x-ui.badge :tone="$project->status->tone()">{{ $project->status->label() }}</x-ui.badge>
                            @endif
                        </x-slot:trailing>
                    </x-ui.list-row>
                @endforeach
            </div>
        </x-ui.card>

        @if ($projects->hasPages())
            <div>{{ $projects->onEachSide(1)->links() }}</div>
        @endif
    @endif
</div>
