<div class="px-4 lg:px-6 py-4 flex flex-col gap-4 max-w-3xl">
    @if ($threads->isEmpty())
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="message-circle"
                headline="No conversations yet"
                body="Every task is a thread. Threads you are on show here, unread first."
            >
                <x-ui.button variant="secondary" :href="route('tasks.index')" wire:navigate>Go to tasks</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="divide-y divide-ink-100">
                @foreach ($threads as $task)
                    @php $latest = $task->latestMessage; @endphp

                    <x-ui.list-row
                        wire:key="thread-{{ $task->id }}"
                        :href="route('tasks.show', $task)"
                        wire:navigate
                        :chevron="false"
                    >
                        <x-slot:leading>
                            @if ($latest?->user)
                                <x-ui.avatar :name="$latest->user->name" :id="$latest->user->id" />
                            @else
                                <div class="grid place-items-center size-9 rounded-full bg-surface-2 text-ink-400">
                                    <x-icon name="message-circle" class="size-4" />
                                </div>
                            @endif
                        </x-slot:leading>

                        <x-slot:body>
                            <div class="flex items-baseline gap-2">
                                <p class="t-body {{ $task->unread_count > 0 ? 'font-medium text-ink-950' : 'text-ink-950' }} truncate flex-1">
                                    {{ $task->title }}
                                </p>
                                <span class="t-meta text-ink-400 shrink-0 tnum">
                                    {{ $task->last_activity_at?->diffForHumans(short: true) }}
                                </span>
                            </div>

                            <p class="t-sub {{ $task->unread_count > 0 ? 'text-ink-800' : 'text-ink-600' }} truncate mt-0.5">
                                @if ($latest)
                                    @if ($latest->isSystem())
                                        {{ Str::limit($latest->body, 60) }}
                                    @elseif ($latest->isVoice())
                                        {{ $latest->user?->firstName() }}: voice note · {{ $latest->durationLabel() }}
                                    @else
                                        {{ $latest->user?->firstName() }}: {{ Str::limit($latest->body, 60) }}
                                    @endif
                                @else
                                    {{ $task->reference }}
                                @endif
                            </p>
                        </x-slot:body>

                        <x-slot:trailing>
                            @if ($task->unread_count > 0)
                                <span class="min-w-5 h-5 px-1.5 grid place-items-center rounded-full bg-accent-600 text-on-solid text-[11px] font-medium tnum">
                                    {{ $task->unread_count > 99 ? '99+' : $task->unread_count }}
                                </span>
                            @endif
                        </x-slot:trailing>
                    </x-ui.list-row>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</div>
