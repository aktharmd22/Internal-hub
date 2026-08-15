<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Support\Permissions;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'source' => TaskSource::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task): void {
            $task->reference ??= self::nextReference();
            $task->last_activity_at ??= now();
        });
    }

    /* ------------------------------------------------------------ relations */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TaskStatusLog::class)->latest();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TaskMessage::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_participants')
            ->withPivot(['role', 'muted_at'])
            ->withTimestamps();
    }

    /* --------------------------------------------------------------- scopes */

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', TaskStatus::openValues());
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', TaskStatus::closedValues());
    }

    public function scopeDueToday(Builder $query): void
    {
        $query->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('status', TaskStatus::closedValues());
    }

    public function scopeAwaitingReview(Builder $query): void
    {
        $query->where('status', TaskStatus::Submitted);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /**
     * The only tasks an employee may see: theirs, or ones they were pulled
     * into. Mirrored exactly by TaskPolicy and by the broadcast channel check.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permissions::VIEW_ALL_TASKS)) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('participants', fn (Builder $p) => $p->whereKey($user->id));
        });
    }

    public function scopeOpenForAsset(Builder $query, Asset $asset): void
    {
        $query->where('asset_id', $asset->id)
            ->whereNotIn('status', TaskStatus::closedValues());
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%");
        });
    }

    /* -------------------------------------------------------------- helpers */

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && ! $this->status->isClosed();
    }

    public function dueTone(): string
    {
        if (! $this->due_at || $this->status->isClosed()) {
            return 'neutral';
        }

        $days = now()->startOfDay()->diffInDays($this->due_at->startOfDay(), false);

        return match (true) {
            $days < 0 => 'danger',
            $days <= 2 => 'danger',
            $days <= 5 => 'warn',
            default => 'neutral',
        };
    }

    public function dueLabel(): ?string
    {
        if (! $this->due_at) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->due_at->startOfDay(), false);

        return match (true) {
            $days < -1 => abs($days).' days overdue',
            $days === -1 => 'A day overdue',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            $days <= 6 => 'Due '.$this->due_at->format('l'),
            default => 'Due '.$this->due_at->format('j M'),
        };
    }

    public function trackedSeconds(): int
    {
        return (int) ($this->time_logs_sum_duration_seconds ?? $this->timeLogs()->sum('duration_seconds'));
    }

    public function trackedLabel(): string
    {
        $seconds = $this->trackedSeconds();

        if ($seconds < 60) {
            return '0m';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    /**
     * References are sequential and human-quotable: "check TSK-0142" is how
     * this gets referred to on a phone call.
     */
    public static function nextReference(): string
    {
        // Ordered by length first so TSK-10000 sorts above TSK-9999, which a
        // plain lexical MAX() would get wrong. LENGTH() is portable across
        // MySQL, MariaDB and the SQLite used by the test suite.
        $last = DB::table('tasks')
            ->orderByRaw('LENGTH(reference) desc')
            ->orderBy('reference', 'desc')
            ->value('reference');

        $number = $last ? (int) substr($last, 4) : 0;

        return 'TSK-'.str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function createRenewalTask(Asset $asset, ?int $assigneeId): self
    {
        return self::create([
            'client_id' => $asset->client_id,
            'asset_id' => $asset->id,
            'title' => "Renew {$asset->name} for {$asset->client->displayName()}",
            'description' => sprintf(
                "%s with %s expires on %s.\n\nRaised automatically ten days out.",
                $asset->type->label(),
                $asset->provider ?: 'the current provider',
                $asset->expires_at->format('j M Y'),
            ),
            'priority' => TaskPriority::High,
            'status' => $assigneeId ? TaskStatus::Assigned : TaskStatus::Open,
            'assigned_to' => $assigneeId,
            'due_at' => $asset->expires_at->copy()->subDay()->setTime(17, 0),
            'source' => TaskSource::Renewal,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'assigned_to', 'due_at', 'project_id', 'is_archived'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
