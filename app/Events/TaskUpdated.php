<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Task $task, public ?int $actorId = null) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("task.{$this->task->id}")];
    }

    public function broadcastAs(): string
    {
        return 'TaskUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->task->id,
            'status' => $this->task->status->value,
            'status_label' => $this->task->status->label(),
            'status_tone' => $this->task->status->tone(),
            'priority' => $this->task->priority->value,
            'assigned_to' => $this->task->assigned_to,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'reopen_count' => $this->task->reopen_count,
            'actor_id' => $this->actorId,
        ];
    }
}
