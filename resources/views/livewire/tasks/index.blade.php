@php
    $filters = [
        ['key' => 'mine', 'label' => 'Mine', 'count' => $counts['mine']],
        ['key' => 'review', 'label' => 'Awaiting review', 'count' => $counts['review']],
        ['key' => 'overdue', 'label' => 'Overdue', 'count' => $counts['overdue']],
        ['key' => 'unassigned', 'label' => 'Unassigned', 'count' => $counts['unassigned']],
        ['key' => 'open', 'label' => 'All open', 'count' => null],
        ['key' => 'all', 'label' => 'Everything', 'count' => null],
    ];
@endphp

<div>
    @push('page-actions')
        <div class="max-lg:hidden inline-flex rounded-control bg-surface-2 p-0.5" role="group" aria-label="View">
            @foreach (['list' => 'list-checks', 'board' => 'folder-kanban'] as $value => $icon)
                <button
                    type="button"
                    wire:click="$set('view', '{{ $value }}')"
                    aria-pressed="{{ $view === $value ? 'true' : 'false' }}"
                    class="grid place-items-center size-8 rounded-[7px] transition-colors
                        {{ $view === $value ? 'bg-surface text-ink-950 shadow-float' : 'text-ink-400' }}"
                >
                    <x-icon :name="$icon" class="size-4" :label="ucfirst($value).' view'" />
                </button>
            @endforeach
        </div>

        @can('create', App\Models\Task::class)
            <x-ui.button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'task-form')">
                <span class="max-sm:sr-only">New task</span>
            </x-ui.button>
        @endcan
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">

        <div class="flex flex-col gap-3">
            <div class="relative">
                <x-icon name="search" class="size-4 text-ink-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by title or reference"
                    class="w-full h-11 md:h-10 pl-9 pr-3 rounded-control border border-ink-200 bg-surface text-ink-950 placeholder:text-ink-400"
                >
            </div>

            <div class="flex gap-2 overflow-x-auto no-scrollbar -mx-4 px-4 lg:mx-0 lg:px-0">
                @foreach ($filters as $chip)
                    <button
                        type="button"
                        wire:click="setFilter('{{ $chip['key'] }}')"
                        aria-pressed="{{ $filter === $chip['key'] ? 'true' : 'false' }}"
                        class="shrink-0 inline-flex items-center gap-1.5 h-9 px-3 rounded-full border text-[13px] font-medium transition-colors
                            {{ $filter === $chip['key']
                                ? 'bg-ink-950 text-canvas border-ink-950'
                                : 'bg-surface text-ink-600 border-ink-200 hover:bg-surface-2' }}"
                    >
                        {{ $chip['label'] }}
                        @if ($chip['count'])
                            <span class="tnum {{ $filter === $chip['key'] ? 'opacity-70' : 'text-ink-400' }}">{{ $chip['count'] }}</span>
                        @endif
                    </button>
                @endforeach

                <div class="shrink-0 w-px bg-ink-100 my-1.5"></div>

                <select wire:model.live="status" class="shrink-0 h-9 px-3 rounded-full border border-ink-200 bg-surface text-[13px] text-ink-600" aria-label="Filter by status">
                    <option value="">Any status</option>
                    @foreach (App\Enums\TaskStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select wire:model.live="client" class="shrink-0 h-9 px-3 rounded-full border border-ink-200 bg-surface text-[13px] text-ink-600 max-w-44" aria-label="Filter by client">
                    <option value="">Any client</option>
                    @foreach ($clients as $option)
                        <option value="{{ $option->id }}">{{ $option->displayName() }}</option>
                    @endforeach
                </select>

                @if ($this->hasFilters())
                    <button type="button" wire:click="clearFilters" class="shrink-0 inline-flex items-center gap-1 h-9 px-3 rounded-full text-[13px] text-ink-600 hover:bg-surface-2">
                        <x-icon name="x" class="size-3.5" />Reset
                    </button>
                @endif
            </div>
        </div>

        <div wire:loading.delay.long wire:target="search,filter,status,client,view">
            <x-ui.card :padding="false" class="divide-y divide-ink-100">
                @for ($i = 0; $i < 5; $i++)
                    <div class="flex items-center gap-3 px-4 min-h-16">
                        <x-ui.skeleton shape="avatar" />
                        <div class="flex-1 flex flex-col gap-2">
                            <x-ui.skeleton shape="title" />
                            <x-ui.skeleton class="w-2/5" />
                        </div>
                        <x-ui.skeleton shape="chip" />
                    </div>
                @endfor
            </x-ui.card>
        </div>

        <div wire:loading.remove.delay.long wire:target="search,filter,status,client,view">
            @if ($view === 'board')
                {{-- Kanban: lg and up only. A board on a 375px screen is a
                     horizontal scroll trap. --}}
                <div class="max-lg:hidden flex gap-3 overflow-x-auto pb-2">
                    @foreach (App\Enums\TaskStatus::boardColumns() as $column)
                        @php $items = $board[$column->value] ?? collect(); @endphp

                        <div
                            wire:key="col-{{ $column->value }}"
                            class="shrink-0 w-72 flex flex-col rounded-card bg-surface-2 border border-ink-100"
                            x-data="{ over: false }"
                            x-bind:class="over && 'ring-2 ring-accent-500'"
                            x-on:dragover.prevent="over = true"
                            x-on:dragleave="over = false"
                            x-on:drop.prevent="
                                over = false;
                                $wire.moveTo(Number($event.dataTransfer.getData('text/task')), '{{ $column->value }}')
                            "
                        >
                            <div class="flex items-center justify-between gap-2 px-3 py-2.5 border-b border-ink-100">
                                <span class="t-sub font-medium text-ink-950">{{ $column->label() }}</span>
                                <span class="t-meta text-ink-400 tnum">{{ $items->count() }}</span>
                            </div>

                            <div class="flex flex-col gap-2 p-2 min-h-24 max-h-[65dvh] overflow-y-auto">
                                @foreach ($items as $task)
                                    <a
                                        wire:key="b-{{ $task->id }}"
                                        href="{{ route('tasks.show', $task) }}"
                                        wire:navigate
                                        draggable="true"
                                        x-on:dragstart="$event.dataTransfer.setData('text/task', '{{ $task->id }}')"
                                        class="block bg-surface border border-ink-100 rounded-control p-3 hover:border-ink-200 transition-colors cursor-grab active:cursor-grabbing"
                                    >
                                        <p class="t-sub font-medium text-ink-950 line-clamp-2">{{ $task->title }}</p>

                                        <div class="flex items-center justify-between gap-2 mt-2">
                                            <span class="t-meta text-ink-400 tnum">{{ $task->reference }}</span>
                                            @if ($task->assignee)
                                                <x-ui.avatar :name="$task->assignee->name" :id="$task->assignee->id" size="sm" />
                                            @endif
                                        </div>

                                        @if ($task->dueLabel())
                                            <x-ui.badge :tone="$task->dueTone()" size="sm" class="mt-2">{{ $task->dueLabel() }}</x-ui.badge>
                                        @endif
                                    </a>
                                @endforeach

                                @if ($items->isEmpty())
                                    <p class="t-meta text-ink-400 text-center py-6">Nothing here</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-ui.card class="lg:hidden">
                    <x-ui.empty-state
                        icon="folder-kanban"
                        headline="The board needs a wider screen"
                        body="On a phone the list view shows the same work without a horizontal scroll."
                    >
                        <x-ui.button variant="secondary" wire:click="$set('view', 'list')">Switch to the list</x-ui.button>
                    </x-ui.empty-state>
                </x-ui.card>

            @elseif ($tasks->isEmpty())
                <x-ui.card :padding="false">
                    <x-ui.empty-state
                        icon="list-checks"
                        :headline="$this->hasFilters() ? 'Nothing matches these filters' : 'No tasks yet'"
                        :body="$this->hasFilters()
                            ? 'Try a different filter, or reset to your own open work.'
                            : 'A task has an owner, a status and a history. That is the whole point.'"
                    >
                        @if ($this->hasFilters())
                            <x-ui.button variant="secondary" wire:click="clearFilters">Reset filters</x-ui.button>
                        @else
                            @can('create', App\Models\Task::class)
                                <x-ui.button variant="primary" icon="plus" x-on:click="$dispatch('open-modal', 'task-form')">
                                    Create a task
                                </x-ui.button>
                            @endcan
                        @endif
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                <x-ui.card :padding="false">
                    <div class="divide-y divide-ink-100">
                        @foreach ($tasks as $task)
                            <x-ui.list-row
                                wire:key="t-{{ $task->id }}"
                                :href="route('tasks.show', $task)"
                                wire:navigate
                            >
                                <x-slot:leading>
                                    @if ($task->assignee)
                                        <x-ui.avatar :name="$task->assignee->name" :id="$task->assignee->id" />
                                    @else
                                        <div class="grid place-items-center size-9 rounded-full bg-surface-2 text-ink-400">
                                            <x-icon name="user" class="size-4" />
                                        </div>
                                    @endif
                                </x-slot:leading>

                                <x-slot:body>
                                    <p class="t-body font-medium text-ink-950 truncate">{{ $task->title }}</p>
                                    <p class="t-sub text-ink-600 truncate mt-0.5">
                                        <span class="tnum">{{ $task->reference }}</span>
                                        @if ($task->client) · {{ $task->client->displayName() }} @endif
                                        @if ($task->project) · {{ $task->project->name }} @endif
                                    </p>
                                </x-slot:body>

                                <x-slot:trailing>
                                    @if ($task->priority !== App\Enums\TaskPriority::Normal)
                                        <x-ui.badge :tone="$task->priority->tone()" size="sm" class="max-sm:hidden">
                                            {{ $task->priority->label() }}
                                        </x-ui.badge>
                                    @endif

                                    @if ($task->dueLabel() && ! $task->status->isClosed())
                                        <x-ui.badge :tone="$task->dueTone()" dot>{{ $task->dueLabel() }}</x-ui.badge>
                                    @else
                                        <x-ui.badge :tone="$task->status->tone()">{{ $task->status->label() }}</x-ui.badge>
                                    @endif
                                </x-slot:trailing>
                            </x-ui.list-row>
                        @endforeach
                    </div>
                </x-ui.card>

                @if ($tasks->hasPages())
                    <div>{{ $tasks->onEachSide(1)->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    @can('create', App\Models\Task::class)
        <livewire:tasks.form />
    @endcan
</div>
