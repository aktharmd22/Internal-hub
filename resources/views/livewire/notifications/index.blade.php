@php
    $icons = [
        'asset_expiring' => ['calendar-clock', 'warn'],
        'task_assigned' => ['list-checks', 'accent'],
        'task_status' => ['circle-check', 'ok'],
        'mention' => ['message-circle', 'accent'],
        'message' => ['message-circle', 'neutral'],
        'failed_jobs' => ['triangle-alert', 'danger'],
    ];
@endphp

<div>
    @push('page-actions')
        <x-ui.button variant="ghost" size="sm" wire:click="markAllRead">Mark all read</x-ui.button>
    @endpush

    <div class="px-4 lg:px-6 py-4 flex flex-col gap-4">
        <div class="flex gap-2 overflow-x-auto no-scrollbar -mx-4 px-4 lg:mx-0 lg:px-0">
            @foreach ([
                '' => 'Everything',
                'asset_expiring' => 'Renewals',
                'task_assigned' => 'Assignments',
                'task_status' => 'Status changes',
                'mention' => 'Mentions',
            ] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('type', '{{ $value }}')"
                    aria-pressed="{{ $type === $value ? 'true' : 'false' }}"
                    class="shrink-0 h-9 px-3 rounded-full border text-[13px] font-medium transition-colors
                        {{ $type === $value ? 'bg-ink-950 text-canvas border-ink-950' : 'bg-surface text-ink-600 border-ink-200 hover:bg-surface-2' }}"
                >{{ $label }}</button>
            @endforeach
        </div>

        @if ($groups->isEmpty())
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="bell"
                    headline="Nothing to catch up on"
                    body="Renewal alerts, assignments and mentions land here."
                />
            </x-ui.card>
        @else
            @foreach ($groups as $day => $notifications)
                <div wire:key="group-{{ Str::slug($day) }}">
                    <p class="t-meta text-ink-400 uppercase tracking-wide px-1 pb-1.5">{{ $day }}</p>

                    <x-ui.card :padding="false">
                        <div class="divide-y divide-ink-100">
                            @foreach ($notifications as $notification)
                                @php
                                    $data = $notification->data;
                                    [$icon, $tone] = $icons[$data['type'] ?? ''] ?? ['bell', 'neutral'];
                                @endphp

                                <a
                                    wire:key="n-{{ $notification->id }}"
                                    href="{{ $data['url'] ?? route('dashboard') }}"
                                    wire:click="markRead('{{ $notification->id }}')"
                                    wire:navigate
                                    class="flex items-start gap-3 px-4 py-3 min-h-16 hover:bg-surface-2 transition-colors {{ $notification->read_at ? '' : 'bg-accent-50/40' }}"
                                >
                                    <span class="shrink-0 grid place-items-center size-9 rounded-control {{ App\Support\Tone::tint($tone) }} {{ App\Support\Tone::text($tone) }}">
                                        <x-icon :name="$icon" class="size-4" />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block t-body {{ $notification->read_at ? 'text-ink-950' : 'font-medium text-ink-950' }}">
                                            {{ $data['title'] ?? 'Notification' }}
                                        </span>
                                        <span class="block t-sub text-ink-600 mt-0.5">{{ $data['body'] ?? '' }}</span>
                                        <span class="block t-meta text-ink-400 mt-1">{{ $notification->created_at->format('g:i a') }}</span>
                                    </span>

                                    @unless ($notification->read_at)
                                        <span class="shrink-0 mt-2 size-2 rounded-full bg-accent-600" aria-label="Unread"></span>
                                    @endunless
                                </a>
                            @endforeach
                        </div>
                    </x-ui.card>
                </div>
            @endforeach
        @endif
    </div>
</div>
