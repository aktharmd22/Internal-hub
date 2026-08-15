@php
    $total = $tasks->count();
    $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
@endphp

<div class="px-4 lg:px-6 py-4 flex flex-col gap-4 max-w-3xl">
    <x-ui.card>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$project->status->tone()" dot>{{ $project->status->label() }}</x-ui.badge>
            @if ($project->isOverdue())
                <x-ui.badge tone="danger" size="sm">Past deadline</x-ui.badge>
            @endif
        </div>

        <h2 class="t-page-title text-ink-950 mt-2">{{ $project->name }}</h2>

        <a href="{{ route('clients.show', $project->client) }}" wire:navigate class="t-sub text-accent-600 hover:underline mt-1 inline-block">
            {{ $project->client->displayName() }}
        </a>

        @if ($project->description)
            <p class="t-sub text-ink-800 mt-3 whitespace-pre-line">{{ $project->description }}</p>
        @endif

        <div class="mt-5">
            <div class="flex items-baseline justify-between">
                <span class="t-meta text-ink-400">Progress</span>
                <span class="t-sub text-ink-950 tnum">{{ $done }}/{{ $total }} · {{ $percent }}%</span>
            </div>
            <div class="flex h-2.5 rounded-full overflow-hidden bg-ink-100 mt-2">
                <div class="bg-ok-600" style="width: {{ $percent }}%"></div>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 mt-5 pt-4 border-t border-ink-100">
            @foreach ([
                'Lead' => $project->lead?->name ?: 'Unassigned',
                'Starts' => $project->starts_on?->format('j M Y') ?: '—',
                'Deadline' => $project->deadline?->format('j M Y') ?: '—',
                'Budget' => $project->budget ? $project->currency.' '.number_format((float) $project->budget) : '—',
                'Overdue tasks' => (string) $overdue,
            ] as $label => $value)
                <div class="min-w-0">
                    <dt class="t-meta text-ink-400">{{ $label }}</dt>
                    <dd class="t-sub text-ink-950 mt-0.5 truncate tnum">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>

    <x-ui.card title="Tasks" :padding="false" :flush="true">
        @if ($tasks->isEmpty())
            <x-ui.empty-state
                icon="list-checks"
                headline="No tasks on this project"
                body="Add tasks so progress against the deadline means something."
            />
        @else
            <div class="divide-y divide-ink-100">
                @foreach ($tasks as $task)
                    <x-ui.list-row
                        wire:key="pt-{{ $task->id }}"
                        :href="route('tasks.show', $task)"
                        :title="$task->title"
                        :subtitle="$task->reference.' · '.($task->assignee?->name ?? 'Unassigned')"
                        wire:navigate
                    >
                        <x-slot:trailing>
                            @if ($task->dueLabel() && ! $task->status->isClosed())
                                <x-ui.badge :tone="$task->dueTone()" dot>{{ $task->dueLabel() }}</x-ui.badge>
                            @else
                                <x-ui.badge :tone="$task->status->tone()">{{ $task->status->label() }}</x-ui.badge>
                            @endif
                        </x-slot:trailing>
                    </x-ui.list-row>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
