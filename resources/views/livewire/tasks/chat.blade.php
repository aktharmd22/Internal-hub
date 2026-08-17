@php $me = auth()->id(); @endphp

{{-- dvh, not vh: on a phone the keyboard shrinks the visual viewport and vh
     would leave the composer under it.

     The thread sits on the canvas while the details column sits on surface, so
     the conversation reads as its own place rather than as more of the form. --}}
<div
    class="flex flex-col h-[calc(100dvh-8.5rem)] lg:h-full bg-canvas"
    x-data="chatThread()"
    x-on:message-sent.window="scrollToEnd(true)"
    x-on:focus-composer.window="$refs.composer?.focus()"
>
    {{-- Thread header --------------------------------------------------- --}}
    <div class="flex items-center gap-2 px-4 h-12 shrink-0 border-b border-ink-100 bg-surface">
        <x-icon name="message-circle" class="size-4 text-ink-400 shrink-0" />

        <span class="t-sub font-medium text-ink-950">Conversation</span>
        <span class="t-meta text-ink-400 tnum">{{ $messages->count() }}</span>

        <div class="flex-1"></div>

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
            wire:click="$toggle('showFiles')"
            aria-label="Files on this task"
            @class([
                'tap grid place-items-center rounded-control transition-colors hover:bg-surface-2',
                'text-accent-600 bg-accent-50' => $showFiles,
                'text-ink-400' => ! $showFiles,
            ])
        >
            <x-icon name="paperclip" class="size-4" />
        </button>

        <button
            type="button"
            x-on:click="toggleSound()"
            x-bind:class="soundOn ? 'text-accent-600' : 'text-ink-400'"
            x-bind:aria-pressed="soundOn"
            x-bind:aria-label="soundOn ? 'Mute new message sounds' : 'Play a sound on new messages'"
            class="tap grid place-items-center rounded-control hover:bg-surface-2 transition-colors"
        >
            <x-icon name="volume" class="size-4" x-show="soundOn" x-cloak />
            <x-icon name="volume-off" class="size-4" x-show="! soundOn" />
        </button>
    </div>

    {{-- Files ------------------------------------------------------------ --}}
    @if ($showFiles)
        <div class="px-4 py-3 border-b border-ink-100 bg-surface shrink-0 max-h-52 overflow-y-auto">
            @forelse ($files as $file)
                <a
                    wire:key="file-{{ $file->id }}"
                    href="{{ $file->getUrl() }}"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-2.5 py-2 px-2 -mx-2 rounded-control hover:bg-surface-2"
                >
                    <x-icon
                        :name="str_starts_with((string) $file->mime_type, 'image/') ? 'image' : 'file-text'"
                        class="size-4 text-ink-400 shrink-0"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block t-sub text-ink-950 truncate">{{ $file->getCustomProperty('original_name', $file->file_name) }}</span>
                        <span class="block t-meta text-ink-400 tnum">{{ number_format($file->size / 1024, 0) }} KB</span>
                    </span>
                </a>
            @empty
                <p class="t-sub text-ink-600 py-2">No files on this task yet.</p>
            @endforelse
        </div>
    @endif

    {{-- Messages ---------------------------------------------------------- --}}
    <div
        class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3"
        x-ref="scroller"
        aria-live="polite"
        aria-relevant="additions"
    >
        @forelse ($messages as $message)
            @php
                $mine = $message->user_id === $me;
                $previous = $loop->index > 0 ? $messages[$loop->index - 1] : null;
                // Consecutive messages from one person read as one turn.
                $grouped = $previous
                    && ! $previous->isSystem()
                    && ! $message->isSystem()
                    && $previous->user_id === $message->user_id
                    && $previous->created_at->diffInMinutes($message->created_at) < 5;
            @endphp

            <div wire:key="msg-{{ $message->id }}" @class(['-mt-2' => $grouped])>
                @if ($message->isSystem())
                    <div class="flex items-center gap-2 py-1">
                        <span class="h-px flex-1 bg-ink-100"></span>
                        <span class="t-meta text-ink-400 text-center">{{ $message->body }}</span>
                        <span class="h-px flex-1 bg-ink-100"></span>
                    </div>

                @elseif ($message->trashed())
                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <p class="t-meta text-ink-400 italic px-3 py-2 rounded-2xl border border-dashed border-ink-200">
                            Message deleted
                        </p>
                    </div>

                @else
                    <div class="flex items-end gap-2 {{ $mine ? 'flex-row-reverse' : '' }}">
                        {{-- The avatar column is held open when grouped, so the
                             bubbles below stay in line with the one above. --}}
                        <div class="w-7 shrink-0">
                            @unless ($mine || $grouped)
                                <x-ui.avatar :name="$message->user?->name ?? 'Unknown'" :id="$message->user_id ?? 0" size="sm" />
                            @endunless
                        </div>

                        <div class="max-w-[76%] min-w-0 group">
                            @unless ($mine || $grouped)
                                <p class="t-meta text-ink-500 mb-1 px-1">{{ $message->user?->name }}</p>
                            @endunless

                            <div @class([
                                'px-3.5 py-2.5 shadow-float',
                                'bg-accent-600 text-on-solid rounded-2xl rounded-br-md' => $mine,
                                'bg-surface border border-ink-100 text-ink-950 rounded-2xl rounded-bl-md' => ! $mine,
                            ])>
                                @if ($message->replyTo)
                                    <div @class([
                                        'border-l-2 pl-2 mb-2 py-0.5',
                                        'border-white/40' => $mine,
                                        'border-accent-500' => ! $mine,
                                    ])>
                                        <p class="t-meta font-medium {{ $mine ? 'opacity-90' : 'text-accent-600' }}">
                                            {{ $message->replyTo->user?->name }}
                                        </p>
                                        <p class="t-meta truncate {{ $mine ? 'opacity-75' : 'text-ink-600' }}">
                                            {{ Str::limit($message->replyTo->body, 70) }}
                                        </p>
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
                                        class="flex items-center gap-3 min-w-56"
                                        x-data="voiceNote(@js($audio?->getUrl()), @js($message->waveform ?? []))"
                                    >
                                        <button
                                            type="button"
                                            x-on:click="toggle()"
                                            x-bind:aria-label="playing ? 'Pause' : 'Play'"
                                            @class([
                                                'grid place-items-center size-9 rounded-full shrink-0 transition-colors',
                                                'bg-white/20 hover:bg-white/30' => $mine,
                                                'bg-accent-50 text-accent-600 hover:bg-accent-100' => ! $mine,
                                            ])
                                        >
                                            <x-icon name="play" class="size-4 ml-0.5" x-show="! playing" />
                                            <x-icon name="pause" class="size-4" x-show="playing" x-cloak />
                                        </button>

                                        <div class="flex items-center gap-px h-8 flex-1">
                                            <template x-for="(bar, i) in bars" :key="i">
                                                <span
                                                    class="flex-1 rounded-full min-h-[3px]"
                                                    x-bind:class="i / bars.length <= progress ? 'opacity-100' : 'opacity-35'"
                                                    x-bind:style="`height: ${bar}%; background: currentColor`"
                                                ></span>
                                            </template>
                                        </div>

                                        <button
                                            type="button"
                                            x-on:click="cycleSpeed()"
                                            class="t-meta tnum shrink-0 opacity-80 hover:opacity-100 tabular-nums"
                                            x-text="speed + '×'"
                                        ></button>

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
                                                @class([
                                                    'flex items-center gap-2 rounded-control px-2 py-1.5',
                                                    'bg-white/15 hover:bg-white/25' => $mine,
                                                    'bg-surface-2 hover:bg-ink-100' => ! $mine,
                                                ])
                                            >
                                                <x-icon
                                                    :name="str_starts_with((string) $media->mime_type, 'image/') ? 'image' : 'file-text'"
                                                    class="size-3.5 shrink-0"
                                                />
                                                <span class="t-meta truncate">{{ $media->getCustomProperty('original_name', $media->file_name) }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center gap-1.5 mt-1 {{ $mine ? 'justify-end' : '' }}">
                                    <span class="t-meta tnum {{ $mine ? 'opacity-70' : 'text-ink-400' }}">
                                        {{ $message->created_at->format('g:i a') }}
                                    </span>
                                    @if ($message->edited_at)
                                        <span class="t-meta {{ $mine ? 'opacity-70' : 'text-ink-400' }}">edited</span>
                                    @endif
                                    @if ($mine && $message->reads->isNotEmpty())
                                        <x-icon name="check" class="size-3 opacity-80" label="Read" />
                                    @endif
                                </div>
                            </div>

                            <div class="flex gap-2 mt-1 px-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity {{ $mine ? 'justify-end' : '' }}">
                                <button type="button" wire:click="reply({{ $message->id }})" class="inline-flex items-center gap-1 t-meta text-ink-400 hover:text-ink-800">
                                    <x-icon name="reply" class="size-3" />Reply
                                </button>
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
                    <x-icon name="reply" class="size-3.5 text-accent-600 shrink-0" />
                    <div class="border-l-2 border-accent-500 pl-2 min-w-0 flex-1">
                        <p class="t-meta font-medium text-accent-600">{{ $replyTo->user?->name }}</p>
                        <p class="t-meta text-ink-600 truncate">{{ Str::limit($replyTo->body, 80) }}</p>
                    </div>
                    <button type="button" wire:click="cancelReply" class="tap grid place-items-center text-ink-400 hover:text-ink-800">
                        <x-icon name="x" class="size-4" label="Cancel reply" />
                    </button>
                </div>
            @endif

            @if ($attachments)
                <div class="flex flex-wrap gap-1.5 px-4 pt-2.5">
                    @foreach ($attachments as $index => $file)
                        <span wire:key="att-{{ $index }}" class="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-2.5 h-7 t-meta text-ink-600">
                            <x-icon name="paperclip" class="size-3" />
                            {{ Str::limit($file->getClientOriginalName(), 24) }}
                        </span>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="flex items-end gap-2 px-3 py-2.5">
                <label class="tap grid place-items-center rounded-control text-ink-400 hover:text-ink-800 hover:bg-surface-2 cursor-pointer shrink-0 transition-colors">
                    <x-icon name="paperclip" class="size-5" label="Attach a file" />
                    <input type="file" wire:model="attachments" multiple class="sr-only">
                </label>

                <textarea
                    x-ref="composer"
                    wire:model="body"
                    rows="1"
                    placeholder="Write a message"
                    x-on:input="autoGrow($event.target); notifyTyping()"
                    x-on:keydown.enter.exact.prevent="$wire.send()"
                    class="flex-1 min-w-0 resize-none max-h-32 rounded-2xl border border-ink-200 bg-canvas px-3.5 py-2.5 text-ink-950 placeholder:text-ink-400"
                ></textarea>

                <button
                    type="button"
                    x-show="! recording"
                    x-on:pointerdown="startRecording()"
                    class="tap grid place-items-center rounded-control text-ink-400 hover:text-ink-800 hover:bg-surface-2 shrink-0 transition-colors"
                    title="Hold to record"
                >
                    <x-icon name="mic" class="size-5" label="Record a voice note" />
                </button>

                <div x-show="recording" x-cloak class="flex items-center gap-2 shrink-0">
                    <span class="inline-flex items-center gap-1.5 t-meta tnum text-danger-600">
                        <span class="size-2 rounded-full bg-danger-600 animate-pulse"></span>
                        <span x-text="recordingLabel"></span>
                    </span>
                    <button type="button" x-on:click="cancelRecording()" class="tap grid place-items-center text-ink-400 hover:text-ink-800">
                        <x-icon name="x" class="size-5" label="Cancel recording" />
                    </button>
                    <button type="button" x-on:click="stopRecording()" class="tap grid place-items-center text-danger-600">
                        <x-icon name="check" class="size-5" label="Send the recording" />
                    </button>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="tap grid place-items-center size-11 md:size-10 rounded-full bg-accent-600 text-on-solid hover:bg-accent-500 shrink-0 transition-colors disabled:opacity-50"
                >
                    <x-icon name="send" class="size-[18px]" label="Send" />
                </button>
            </form>
        </div>
    @endcan
</div>
