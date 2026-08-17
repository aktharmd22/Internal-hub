@php $task = $this->task; @endphp

<div class="lg:flex lg:h-[calc(100dvh-4rem)] lg:overflow-hidden">

    @push('page-actions')
        @can('update', $task)
            @if ($nextStatuses->isNotEmpty())
                <x-ui.dropdown align="right" width="w-52">
                    <x-slot:trigger>
                        <x-ui.button variant="primary" size="sm" iconTrailing="chevron-down">
                            {{ $task->status->label() }}
                        </x-ui.button>
                    </x-slot:trigger>
                    @foreach ($nextStatuses as $status)
                        <x-ui.dropdown-item x-on:click="Livewire.dispatch('task:request-status', { status: '{{ $status->value }}' })">
                            Move to {{ $status->label() }}
                        </x-ui.dropdown-item>
                    @endforeach
                </x-ui.dropdown>
            @endif

            <x-ui.dropdown align="right" width="w-52">
                <x-slot:trigger>
                    <button type="button" class="tap grid place-items-center rounded-control text-ink-600 hover:bg-surface-2">
                        <x-icon name="ellipsis-vertical" class="size-5" label="More actions" />
                    </button>
                </x-slot:trigger>
                <x-ui.dropdown-item icon="pencil" x-on:click="Livewire.dispatch('edit-task', { id: {{ $task->id }} })">
                    Edit task
                </x-ui.dropdown-item>
                <x-ui.dropdown-item icon="plus" x-on:click="Livewire.dispatch('create-subtask', { parentId: {{ $task->id }} })">
                    Add a subtask
                </x-ui.dropdown-item>
                <x-ui.dropdown-item icon="clock" x-on:click="Livewire.dispatch('task:toggle-timer')">
                    {{ $timerRunning ? 'Stop the timer' : 'Start the timer' }}
                </x-ui.dropdown-item>
                <x-ui.dropdown-item icon="bell" x-on:click="Livewire.dispatch('task:toggle-mute')">
                    {{ $muted ? 'Unmute this thread' : 'Mute this thread' }}
                </x-ui.dropdown-item>
            </x-ui.dropdown>
        @endcan
    @endpush

    {{-- Details column ------------------------------------------------- --}}
    <div class="lg:w-[380px] lg:shrink-0 lg:overflow-y-auto bg-surface">
        <div class="px-4 lg:px-5 py-4 flex flex-col gap-4">

            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$task->status->tone()" dot>{{ $task->status->label() }}</x-ui.badge>
                    <span class="t-meta text-ink-400 tnum">{{ $task->reference }}</span>
                    @if ($task->priority !== App\Enums\TaskPriority::Normal)
                        <x-ui.badge :tone="$task->priority->tone()" size="sm">{{ $task->priority->label() }}</x-ui.badge>
                    @endif
                    @if ($task->reopen_count > 0)
                        <x-ui.badge tone="danger" size="sm">Reopened {{ $task->reopen_count }}×</x-ui.badge>
                    @endif
                </div>

                <h2 class="t-page-title text-ink-950 mt-2">{{ $task->title }}</h2>

                @if ($task->dueLabel())
                    <p class="t-sub mt-1.5 {{ App\Support\Tone::text($task->dueTone()) }}">{{ $task->dueLabel() }}</p>
                @endif
            </div>

            @if ($task->description)
                <div class="t-sub text-ink-800 whitespace-pre-line">{{ $task->description }}</div>
            @endif

            {{-- Collapsible on mobile, always open on desktop --}}
            <div x-data="{ open: false }" class="lg:contents">
                <button
                    type="button"
                    x-on:click="open = ! open"
                    class="lg:hidden flex items-center justify-between w-full h-11 px-3.5 rounded-control border border-ink-100 text-ink-800"
                >
                    <span class="t-sub font-medium">Details</span>
                    <x-icon name="chevron-down" class="size-4 transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>

                <div x-show="open" x-collapse class="lg:!block" x-bind:class="{ 'max-lg:hidden': ! open }">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 py-1">
                        @foreach ([
                            'Client' => $task->client?->displayName() ?: '—',
                            'Project' => $task->project?->name ?: '—',
                            'Assignee' => $task->assignee?->name ?: 'Unassigned',
                            'Created by' => $task->creator?->name ?: 'The system',
                            'Time logged' => $trackedSeconds > 0 ? gmdate($trackedSeconds >= 3600 ? 'G\h i\m' : 'i\m', $trackedSeconds) : 'None',
                            'Estimate' => $task->estimated_minutes ? $task->estimated_minutes.'m' : '—',
                        ] as $label => $value)
                            <div class="min-w-0">
                                <dt class="t-meta text-ink-400">{{ $label }}</dt>
                                <dd class="t-sub text-ink-950 mt-0.5 truncate">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($task->asset)
                        <a href="{{ route('assets.show', $task->asset) }}" wire:navigate
                           class="flex items-center gap-2.5 mt-3 rounded-control border border-ink-100 px-3.5 py-2.5 hover:bg-surface-2">
                            <x-icon :name="$task->asset->type->icon()" class="size-4 text-ink-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block t-sub text-ink-950 truncate">{{ $task->asset->name }}</span>
                                <span class="block t-meta text-ink-600">Raised by this renewal</span>
                            </span>
                            <x-icon name="chevron-right" class="size-4 text-ink-400" />
                        </a>
                    @endif
                </div>
            </div>

            {{-- Inline actions --}}
            @can('update', $task)
                <div class="flex flex-col gap-2.5 rounded-card border border-ink-100 p-3.5">
                    @can('assign', $task)
                        <label class="flex flex-col gap-1.5">
                            <span class="t-meta text-ink-400">Assignee</span>
                            <select
                                wire:change="assign($event.target.value ? Number($event.target.value) : null)"
                                class="h-10 px-3 rounded-control border border-ink-200 bg-surface text-ink-950"
                            >
                                <option value="">Unassigned</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($task->assigned_to === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endcan

                    <label class="flex flex-col gap-1.5">
                        <span class="t-meta text-ink-400">Due date</span>
                        <input
                            type="date"
                            value="{{ $task->due_at?->format('Y-m-d') }}"
                            wire:change="setDueDate($event.target.value || null)"
                            class="h-10 px-3 rounded-control border border-ink-200 bg-surface text-ink-950"
                        >
                    </label>

                    <x-ui.button
                        variant="{{ $timerRunning ? 'danger' : 'secondary' }}"
                        icon="clock"
                        wire:click="toggleTimer"
                        class="w-full mt-1"
                    >
                        {{ $timerRunning ? 'Stop the timer' : 'Start the timer' }}
                    </x-ui.button>
                </div>
            @endcan

            {{-- Subtasks --}}
            @if ($subtasks->isNotEmpty())
                <div>
                    <p class="t-meta text-ink-400 uppercase tracking-wide mb-1.5">Subtasks</p>
                    <div class="rounded-card border border-ink-100 divide-y divide-ink-100">
                        @foreach ($subtasks as $subtask)
                            <a wire:key="sub-{{ $subtask->id }}" href="{{ route('tasks.show', $subtask) }}" wire:navigate
                               class="flex items-center gap-2.5 px-3.5 py-2.5 hover:bg-surface-2">
                                <x-icon
                                    :name="$subtask->status->isClosed() ? 'circle-check' : 'circle-dashed'"
                                    class="size-4 shrink-0 {{ $subtask->status->isClosed() ? 'text-ok-600' : 'text-ink-400' }}"
                                />
                                <span class="t-sub text-ink-950 truncate flex-1">{{ $subtask->title }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Participants --}}
            <div>
                <p class="t-meta text-ink-400 uppercase tracking-wide mb-1.5">On this thread</p>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach ($task->participants as $participant)
                        <x-ui.avatar wire:key="p-{{ $participant->id }}" :name="$participant->name" :id="$participant->id" size="sm" />
                    @endforeach

                    @can('update', $task)
                        <x-ui.dropdown align="left" width="w-52">
                            <x-slot:trigger>
                                <button type="button" class="grid place-items-center size-7 rounded-full border border-dashed border-ink-200 text-ink-400 hover:bg-surface-2">
                                    <x-icon name="plus" class="size-3.5" label="Add a watcher" />
                                </button>
                            </x-slot:trigger>
                            @foreach ($users->whereNotIn('id', $task->participants->pluck('id')) as $user)
                                <x-ui.dropdown-item wire:key="w-{{ $user->id }}" wire:click="addWatcher({{ $user->id }})">
                                    {{ $user->name }}
                                </x-ui.dropdown-item>
                            @endforeach
                        </x-ui.dropdown>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Chat column ----------------------------------------------------
         Its own surface, with a border between it and the details, so the
         conversation reads as a separate place rather than as more form. --}}
    <div class="flex-1 min-w-0 lg:h-full lg:border-l lg:border-ink-100 max-lg:border-t max-lg:border-ink-100">
        <livewire:tasks.chat :task="$task" :key="'chat-'.$task->id" />
    </div>

    {{-- Reason sheet --------------------------------------------------- --}}
    <x-ui.modal
        name="status-reason"
        :title="$pendingStatus ? 'Move to '.App\Enums\TaskStatus::from($pendingStatus)->label() : 'Add a reason'"
        subtitle="A blocked or reopened task without a reason is a mystery next week."
    >
        <x-ui.field
            label="Reason"
            for="status-note"
            type="textarea"
            rows="3"
            required
            placeholder="Waiting on the client's logo files"
            wire:model="note"
            :error="$errors->first('note')"
        />

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'status-reason')">Cancel</x-ui.button>
            <x-ui.button variant="primary" wire:click="confirmStatus" target="confirmStatus">Save</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @can('update', $task)
        <livewire:tasks.form />
    @endcan
</div>
