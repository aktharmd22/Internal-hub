<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Models\MessageRead;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use App\Notifications\NewTaskMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Chat extends Component
{
    use WithFileUploads;

    public Task $task;

    public string $body = '';

    public ?int $replyToId = null;

    public ?int $editingId = null;

    public string $editingBody = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public $voice;

    public ?int $voiceDuration = null;

    /** @var array<int, int>|null */
    public ?array $voiceWaveform = null;

    public bool $showFiles = false;

    /** Highest message id this client has rendered, for gap recovery. */
    public int $lastSeenId = 0;

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);

        $this->task = $task;
        $this->markRead();
    }

    /**
     * Reverb/Pusher pushes only an id. The component re-queries through the
     * same policy the server would apply, so a payload can never outrun
     * authorization.
     */
    #[On('echo-private:task.{task.id},MessageSent')]
    public function onMessageSent(): void
    {
        $this->markRead();
    }

    #[On('echo-private:task.{task.id},MessageDeleted')]
    public function onMessageDeleted(): void
    {
        // Rendering re-reads the thread; nothing else to do.
    }

    /**
     * Called when the websocket reconnects after a drop. Re-rendering pulls
     * everything since `lastSeenId`, so nothing is lost in the gap.
     */
    #[On('connection-restored')]
    public function resync(): void
    {
        $this->markRead();
    }

    #[On('message-posted')]
    public function refreshThread(): void
    {
        $this->markRead();
    }

    public function send(): void
    {
        $this->authorize('comment', $this->task);

        $this->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachments.*' => ['file', 'max:25600'],
            'voice' => ['nullable', 'file', 'max:25600'],
        ], attributes: ['attachments.*' => 'attachment']);

        if (blank($this->body) && $this->attachments === [] && ! $this->voice) {
            return;
        }

        $message = TaskMessage::create([
            'task_id' => $this->task->id,
            'user_id' => auth()->id(),
            'body' => filled($this->body) ? $this->body : null,
            'type' => $this->voice ? 'voice' : 'text',
            'reply_to_id' => $this->replyToId,
            'duration_seconds' => $this->voice ? $this->voiceDuration : null,
            'waveform' => $this->voice ? $this->voiceWaveform : null,
        ]);

        foreach ($this->attachments as $file) {
            $message->addMedia($file->getRealPath())
                ->usingFileName(Str::random(20).'.'.$file->getClientOriginalExtension())
                ->withCustomProperties(['original_name' => $file->getClientOriginalName()])
                ->toMediaCollection('attachments');
        }

        if ($this->voice) {
            // Recorded as WAV in the browser. Chrome's native webm/opus cannot
            // be played by iOS Safari, and transcoding it would need ffmpeg,
            // which shared hosting does not have.
            $message->addMedia($this->voice->getRealPath())
                ->usingFileName(Str::random(20).'.wav')
                ->toMediaCollection('voice');
        }

        $this->task->forceFill(['last_activity_at' => now()])->save();

        $this->notifyParticipants($message);

        MessageSent::dispatch($message);

        $this->reset(['body', 'replyToId', 'attachments', 'voice', 'voiceDuration', 'voiceWaveform']);
        $this->markRead();

        $this->dispatch('message-sent');
    }

    /**
     * A mention pulls someone into the thread as a watcher, so the next
     * message reaches them without anyone having to remember.
     */
    private function notifyParticipants(TaskMessage $message): void
    {
        $mentioned = collect();
        $candidates = $this->mentionCandidates((string) $message->body);

        if ($candidates !== []) {
            $mentioned = User::query()
                ->where('is_active', true)
                ->where(function ($q) use ($candidates) {
                    foreach ($candidates as $name) {
                        // Exact for a full name, prefix for a first name only.
                        $q->orWhere('name', $name)->orWhere('name', 'like', $name.' %');
                    }
                })
                ->get();

            $this->task->participants()->syncWithoutDetaching(
                $mentioned->mapWithKeys(fn (User $u) => [$u->id => ['role' => 'watcher']])->all()
            );
        }

        $this->task->load('participants');

        $recipients = $this->task->participants
            ->concat($this->task->assignee ? [$this->task->assignee] : [])
            ->concat($mentioned)
            ->unique('id')
            ->reject(fn (User $user) => $user->id === auth()->id())
            ->reject(fn (User $user) => (bool) $this->task->participants->firstWhere('id', $user->id)?->pivot?->muted_at);

        foreach ($recipients as $user) {
            $user->notify(new NewTaskMessage($message, $mentioned->contains('id', $user->id)));
        }
    }

    /**
     * "@Divya Nair" and "@Divya can you look" both have to resolve to Divya.
     *
     * Each mention yields two candidates — the first word alone and the first
     * two words — so a bare first name matches on prefix while a full name
     * matches exactly. Matching greedily on two words alone would turn
     * "@Divya can" into a name nobody has.
     *
     * @return list<string>
     */
    private function mentionCandidates(string $body): array
    {
        if (blank($body) || ! preg_match_all('/@([\p{L}]+)(?:\s+([\p{L}]+))?/u', $body, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $candidates = [];

        foreach ($matches as $match) {
            $candidates[] = $match[1];

            if (isset($match[2])) {
                $candidates[] = $match[1].' '.$match[2];
            }
        }

        return array_values(array_unique($candidates));
    }

    public function startEdit(int $id): void
    {
        $message = $this->task->messages()->findOrFail($id);

        abort_unless($message->canBeEditedBy(auth()->user()), 403);

        $this->editingId = $id;
        $this->editingBody = (string) $message->body;
    }

    public function saveEdit(): void
    {
        $message = $this->task->messages()->findOrFail($this->editingId);

        abort_unless($message->canBeEditedBy(auth()->user()), 403);

        $this->validate(['editingBody' => ['required', 'string', 'max:5000']]);

        $message->forceFill(['body' => $this->editingBody, 'edited_at' => now()])->save();

        $this->reset(['editingId', 'editingBody']);

        MessageSent::dispatch($message);
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editingBody']);
    }

    public function deleteMessage(int $id): void
    {
        $message = $this->task->messages()->findOrFail($id);

        abort_unless($message->user_id === auth()->id() || auth()->user()->can('update', $this->task), 403);

        $message->delete();

        MessageDeleted::dispatch($this->task->id, $id);
    }

    public function reply(int $id): void
    {
        $this->replyToId = $id;
        $this->dispatch('focus-composer');
    }

    public function cancelReply(): void
    {
        $this->replyToId = null;
    }

    private function markRead(): void
    {
        $unread = $this->task->messages()
            ->where('user_id', '!=', auth()->id())
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', auth()->id()))
            ->pluck('id');

        foreach ($unread as $messageId) {
            MessageRead::firstOrCreate(
                ['task_message_id' => $messageId, 'user_id' => auth()->id()],
                ['read_at' => now()],
            );
        }
    }

    public function render(): View
    {
        $messages = $this->thread();

        $this->lastSeenId = (int) ($messages->last()?->id ?? 0);

        return view('livewire.tasks.chat', [
            'messages' => $messages,
            'files' => $this->task->messages()
                ->with('media')
                ->get()
                ->flatMap(fn (TaskMessage $m) => $m->getMedia('attachments'))
                ->sortByDesc('created_at')
                ->values(),
            'replyTo' => $this->replyToId ? $this->task->messages()->with('user')->find($this->replyToId) : null,
        ]);
    }

    /**
     * Deliberately not called `messages()`. Livewire reserves that name for a
     * component's custom validation messages, and a private method with that
     * name breaks every `$this->validate()` call in the class.
     *
     * @return Collection<int, TaskMessage>
     */
    private function thread(): Collection
    {
        return $this->task->messages()
            ->withTrashed()
            ->with(['user:id,name', 'replyTo.user:id,name', 'media', 'reads'])
            ->orderBy('id')
            ->get();
    }
}
