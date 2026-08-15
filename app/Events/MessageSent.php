<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TaskMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskMessage $message) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("task.{$this->message->task_id}")];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Only the id travels. Each client re-fetches through the same policy the
     * server would apply, so a payload can never leak past authorization.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'task_id' => $this->message->task_id,
            'user_id' => $this->message->user_id,
            'type' => $this->message->type,
        ];
    }
}
