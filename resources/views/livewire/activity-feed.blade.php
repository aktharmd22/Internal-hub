<div>
    @if ($entries->isEmpty())
        <x-ui.empty-state
            icon="clock"
            headline="Nothing recorded yet"
            body="Changes to clients, assets, tasks and credentials appear here."
        />
    @else
        <ul class="divide-y divide-ink-100">
            @foreach ($entries as $entry)
                <li wire:key="a-{{ $entry->id }}" class="flex items-start gap-3 px-4 py-3">
                    <x-ui.avatar
                        :name="$entry->causer?->name ?? 'System'"
                        :id="$entry->causer_id ?? 0"
                        size="sm"
                        class="mt-0.5 shrink-0"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="t-sub text-ink-950">
                            <span class="font-medium">{{ $entry->causer?->name ?? 'The system' }}</span>
                            {{ $entry->description }}
                            @if ($entry->log_name === 'credential-access')
                                <x-ui.badge tone="warn" size="sm" class="ml-1">Vault</x-ui.badge>
                            @endif
                        </p>
                        <p class="t-meta text-ink-400 mt-0.5">
                            {{ $entry->created_at->diffForHumans() }}
                            @if ($entry->subject_type) · {{ str($entry->subject_type)->replace('_', ' ')->title() }} @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
