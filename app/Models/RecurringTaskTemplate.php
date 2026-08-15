<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Database\Factories\RecurringTaskTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RecurringTaskTemplate extends Model
{
    /** @use HasFactory<RecurringTaskTemplateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeDue(Builder $query, ?Carbon $now = null): void
    {
        $query->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now ?? now());
    }

    public function spawn(): Task
    {
        return Task::create([
            'project_id' => $this->project_id,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->assigned_to ? TaskStatus::Assigned : TaskStatus::Open,
            'assigned_to' => $this->assigned_to,
            'due_at' => now()->addDays($this->due_in_days)->setTime(17, 0),
            'source' => TaskSource::Recurring,
        ]);
    }

    public function advance(?Carbon $from = null): void
    {
        $from = $from ?? now();

        $this->forceFill([
            'last_run_at' => $from,
            'next_run_at' => $this->calculateNextRun($from),
        ])->save();
    }

    public function calculateNextRun(?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy();

        return match ($this->frequency) {
            'daily' => $from->addDay()->startOfDay()->setTime(9, 0),
            'weekly' => $from->addWeek()->startOfWeek()->addDays(max(0, ($this->day_of_week ?? 1) - 1))->setTime(9, 0),
            'fortnightly' => $from->addWeeks(2)->setTime(9, 0),
            'quarterly' => $from->addMonthsNoOverflow(3)->startOfMonth()->addDays(max(0, ($this->day_of_month ?? 1) - 1))->setTime(9, 0),
            'yearly' => $from->addYearNoOverflow()->setTime(9, 0),
            default => $from->addMonthNoOverflow()->startOfMonth()->addDays(max(0, ($this->day_of_month ?? 1) - 1))->setTime(9, 0),
        };
    }

    /** @return array<string, string> */
    public static function frequencyOptions(): array
    {
        return [
            'daily' => 'Every day',
            'weekly' => 'Every week',
            'fortnightly' => 'Every two weeks',
            'monthly' => 'Every month',
            'quarterly' => 'Every quarter',
            'yearly' => 'Every year',
        ];
    }
}
