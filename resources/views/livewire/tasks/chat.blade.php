@php $me = auth()->id(); @endphp

{{-- dvh, not vh: on a phone the keyboard shrinks the visual viewport and vh
     would leave the composer under it. --}}
<div
    class="flex flex-col h-[calc(100dvh-8.5rem)] lg:h-full"
    x-data="chatThread()"
    x-on:message-sent.window="scrollToEnd(true)"
    x-on:focus-composer.window="$refs.composer?.focus()"
>
    {{-- Thread header ------------------------------------------------- --}}
    <div class="flex items-center gap-2 px-4 h-11 border-b border-ink-100 shrink-0">
        <span class="t-meta text-ink-400 flex-1">
            {{ $messages->count() }} {{ str('message')->plural($messages->count()) }}
        </span>

        <template x-if="typing.length">
            <span class="t-meta text-ink-600" aria-live="polite" x-text="typingLabel"></span>
        </template>

        <template x-if="! connected">
            <span class="inline-flex items-center gap-1 t-meta text-warn-600">
                <span class="size-1.5 rounded-full bg-warn-600 animate-pulse"></span>Reconnecting
            </span>
        </template>

        <button
            type="button"
            x-on:click="$wire.set('showFiles', ! $wire.showFiles)"
            class="tap grid place-items-center rounded-control text-ink-600 hover:bg-surface-2"
        >
            <x-icon name="file-text" class="size-4" label="Files on this task" />
        </button>

        <button
            type="button"
            x-on:click="toggleSound()"
            class="tap grid place-items-center rounded-control hover:bg-surface-2"
            x-bind:class="soundOn ? 'text-accent-600' : 'text-ink-400'"
            x-bind:aria-pressed="soundOn"
        >
            <x-icon name="bell" class="size-4" label="Sound for new messages" />
        </button>
    </div>

    {{-- Files tab ------------------------------------------------------- --}}
    @if ($showFiles)
        <div class="px-4 py-3 border-b border-ink-100 shrink-0 max-h-52 overflow-y-auto">
            @forelse ($files as $file)
                <a
                    wire:key="file-{{ $file->id }}"
                    href="{{ $file->getUrl() }}"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-2.5 py-2 hover:bg-surface-2 rounded-control px-2 -mx-2"
                >
                    <x-icon name="file-text" class="size-4 text-ink-400 shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="block t-sub text-ink-950 truncate">{{ $file->getCustomProperty('original_name', $file->file_name) }}</span>
                        <span class="block t-meta text-ink-400">{{ number_format($file->size / 1024, 0) }} KB</span>
                    </span>
                </a>
            @empty
                <p class="t-sub text-ink-600 py-2">No files on this task yet.</p>
            @endforelse
        </div>
    @endif

    {{-- Messages --------------------------------------------------------- --}}
    <div
        class="flex-1 overflow-y-auto px-4 py-3 flex flex-col gap-2.5"
        x-ref="scroller"
        aria-live="polite"
        aria-relevant="additions"
    >
        @forelse ($messages as $message)
            @php $mine = $message->user_id === $me; @endphp

            <div wire:key="msg-{{ $message->id }}">
                @if ($message->isSystem())
                    <p class="t-meta text-ink-400 text-center py-1">{{ $message->body }}</p>

                @elseif ($message->trashed())
                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <p class="t-meta text-ink-400 italic px-3 py-2 rounded-card border border-dashed border-ink-200">
                            Message deleted
                        </p>
                    </div>

                @else
                    <div class="flex gap-2 {{ $mine ? 'flex-row-reverse' : '' }}">
                        @unless ($mine)
                            <x-ui.avatar :name="$message->user?->name ?? 'Unknown'" :id="$message->user_id ?? 0" size="sm" class="mt-auto shrink-0" />
                        @endunless

                        <div class="max-w-[78%] min-w-0 group">
                            @unless ($mine)
                                <p class="t-meta text-ink-400 mb-0.5 px-1">{{ $message->user?->name }}</p>
                            @endunless

                            <div class="rounded-card px-3 py-2 {{ $mine ? 'bg-accent-600 text-on-solid' : 'bg-surface border border-ink-100 text-ink-950' }}">
                                @if ($message->replyTo)
                                    <div class="border-l-2 pl-2 mb-1.5 {{ $mine ? 'border-white/40' : 'border-ink-200' }}">
                                        <p class="t-meta {{ $mine ? 'opacity-80' : 'text-ink-400' }}">{{ $message->replyTo->user?->name }}</p>
                                        <p class="t-meta truncate {{ $mine ? 'opacity-80' : 'text-ink-600' }}">{{ Str::limit($message->replyTo->body, 70) }}</p>
                                    </div>
                                @endif

                                @if ($editingId === $message->id)
                                    <form wire:submit="saveEdit" class="flex flex-col gap-2">
                                        <textarea
                                            wire:model="editingBody"
                                            rows="2"
                                            class="w-full rounded-control border border-ink-200 bg-surface text-ink-950 px-2 py-1.5"
                                        ></textarea>
                                        <div class="flex gap-2">
                                            <x-ui.button size="sm" variant="primary" type="submit">Save</x-ui.button>
                                            <x-ui.button size="sm" variant="ghost" wire:click="cancelEdit">Cancel</x-ui.button>
                                        </div>
                                    </form>

                                @elseif ($message->isVoice())
                                    @php $audio = $message->getFirstMedia('voice'); @endphp
                                    <div
                                        class="flex items-center gap-2.5 min-w-52"
                                        x-data="voiceNote(@js($audio?->getUrl()), @js($message->waveform ?? []))"
                                    >
                                        <button
                                            type="button"
                                            x-on:click="toggle()"
                                            class="grid place-items-center size-9 rounded-full shrink-0 {{ $mine ? 'bg-white/20' : 'bg-surface-2' }}"
                                        >
                                            <x-icon name="chevron-right" class="size-4" x-show="! playing" label="Play" />
                                            <x-icon name="x" class="size-4" x-show="playing" x-cloak label="Pause" />
                                        </button>

                                        <div class="flex items-end gap-px h-7 flex-1" x-ref="bars">
                                            <template x-for="(bar, i) in bars" :key="i">
                                                <span
                                                    class="flex-1 rounded-full"
                                                    x-bind:class="i / bars.length <= progress ? 'opacity-100' : 'opacity-40'"
                                                    x-bind:style="`height: ${bar}%; background: currentColor`"
                                                ></span>
                                            </template>
                                        </div>

                                        <button type="button" x-on:click="cycleSpeed()" class="t-meta tnum shrink-0 opacity-80" x-text="speed + '×'"></button>
                                        <span class="t-meta tnum shrink-0 opacity-80">{{ $message->durationLabel() }}</span>
                                    </div>

                                @elseif (filled($message->body))
                                    <p class="t-body whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                @endif

                                @if ($message->getMedia('attachments')->isNotEmpty())
                                    <div class="flex flex-col gap-1.5 mt-2">
                                        @foreach ($message->getMedia('attachments') as $media)
                                            <a
                                                wire:key="media-{{ $media->id }}"
                                                href="{{ $media->getUrl() }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="flex items-center gap-2 rounded-control px-2 py-1.5 {{ $mine ? 'bg-white/15' : 'bg-surface-2' }}"
                                            >
                                                <x-icon name="file-text" class="size-3.5 shrink-0" />
                                                <span class="t-meta truncate">{{ $media->getCustomProperty('original_name', $media->file_name) }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center gap-1.5 mt-1 {{ $mine ? 'justify-end' : '' }}">
                                    <span class="t-meta {{ $mine ? 'opacity-70' : 'text-ink-400' }} tnum">
                                        {{ $message->created_at->format('g:i a') }}
                                    </span>
                                    @if ($message->edited_at)
                                        <span class="t-meta {{ $mine ? 'opacity-70' : 'text-ink-400' }}">edited</span>
                                    @endif
                                    @if ($mine && $message->reads->isNotEmpty())
                                        <x-icon name="check" class="size-3 opacity-70" label="Read" />
                                    @endif
                                </div>
                            </div>

                            <div class="flex gap-2 mt-1 px-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity {{ $mine ? 'justify-end' : '' }}">
                                <button type="button" wire:click="reply({{ $message->id }})" class="t-meta text-ink-400 hover:text-ink-800">Reply</button>
                                @if ($message->canBeEditedBy(auth()->user()))
                                    <button type="button" wire:click="startEdit({{ $message->id }})" class="t-meta text-ink-400 hover:text-ink-800">Edit</button>
                                @endif
                                @if ($mine)
                                    <button type="button" wire:click="deleteMessage({{ $message->id }})" class="t-meta text-ink-400 hover:text-danger-600">Delete</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="flex-1 grid place-items-center">
                <x-ui.empty-state
                    icon="message-circle"
                    headline="No messages yet"
                    body="Everything said about this task lives here, alongside its status history."
                />
            </div>
        @endforelse
    </div>

    {{-- Composer ---------------------------------------------------------- --}}
    @can('comment', $this->task)
        <div class="border-t border-ink-100 bg-surface shrink-0 safe-b">
            @if ($replyTo)
                <div class="flex items-center gap-2 px-4 pt-2.5">
                    <div class="border-l-2 border-accent-500 pl-2 min-w-0 flex-1">
                        <p class="t-meta text-ink-400">Replying to {{ $replyTo->user?->name }}</p>
                        <p class="t-meta text-ink-600 truncate">{{ Str::limit($replyTo->body, 80) }}</p>
                    </div>
                    <button type="button" wire:click="cancelReply" class="tap grid place-items-center text-ink-400">
                        <x-icon name="x" class="size-4" label="Cancel reply" />
                    </button>
                </div>
            @endif

            @if ($attachments)
                <div class="flex flex-wrap gap-1.5 px-4 pt-2.5">
                    @foreach ($attachments as $index => $file)
                        <span wire:key="att-{{ $index }}" class="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-2.5 h-7 t-meta text-ink-600">
                            {{ Str::limit($file->getClientOriginalName(), 24) }}
                        </span>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="flex items-end gap-2 px-3 py-2.5">
                <label class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2 cursor-pointer shrink-0">
                    <x-icon name="plus" class="size-5" label="Attach a file" />
                    <input type="file" wire:model="attachments" multiple class="sr-only">
                </label>

                <textarea
                    x-ref="composer"
                    wire:model="body"
                    rows="1"
                    placeholder="Write a message"
                    x-on:input="autoGrow($event.target); notifyTyping()"
                    x-on:keydown.enter.exact.prevent="$wire.send()"
                    class="flex-1 min-w-0 resize-none max-h-32 rounded-control border border-ink-200 bg-surface px-3 py-2.5 text-ink-950 placeholder:text-ink-400"
                ></textarea>

                <button
                    type="button"
                    x-show="! recording"
                    x-on:pointerdown="startRecording()"
                    class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2 shrink-0"
                    title="Hold to record"
                >
                    <x-icon name="message-circle" class="size-5" label="Record a voice note" />
                </button>

                <div x-show="recording" x-cloak class="flex items-center gap-2 shrink-0">
                    <span class="t-meta tnum text-danger-600" x-text="recordingLabel"></span>
                    <button type="button" x-on:click="cancelRecording()" class="tap grid place-items-center text-ink-400">
                        <x-icon name="x" class="size-5" label="Cancel recording" />
                    </button>
                    <button type="button" x-on:click="stopRecording()" class="tap grid place-items-center text-danger-600">
                        <x-icon name="check" class="size-5" label="Send the recording" />
                    </button>
                </div>

                <x-ui.button type="submit" variant="primary" size="sm" class="shrink-0" target="send">
                    Send
                </x-ui.button>
            </form>
        </div>
    @endcan
</div>
