<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_status' => TaskStatus::class,
            'to_status' => TaskStatus::class,
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function describe(): string
    {
        $who = $this->user?->firstName() ?? 'The system';

        return $this->from_status
            ? "{$who} moved this from {$this->from_status->label()} to {$this->to_status->label()}"
            : "{$who} created this as {$this->to_status->label()}";
    }
}
