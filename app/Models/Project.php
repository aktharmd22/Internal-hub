<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_on' => 'date',
            'deadline' => 'date',
            'budget' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /**
     * Percentage of tasks completed, using counts already loaded by the caller
     * so a project list does not fire two queries per row.
     */
    public function progress(): int
    {
        $total = (int) ($this->tasks_count ?? $this->tasks()->count());

        if ($total === 0) {
            return 0;
        }

        $done = (int) ($this->completed_tasks_count ?? $this->tasks()->where('status', TaskStatus::Completed)->count());

        return (int) round($done / $total * 100);
    }

    public function isOverdue(): bool
    {
        return $this->deadline
            && $this->deadline->isPast()
            && ! in_array($this->status, [ProjectStatus::Completed, ProjectStatus::Cancelled], true);
    }
}
