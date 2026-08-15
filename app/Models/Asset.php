<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\VerificationStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => AssetType::class,
            'status' => AssetStatus::class,
            'verification_status' => VerificationStatus::class,
            'purchased_at' => 'date',
            'expires_at' => 'date',
            'verified_expires_at' => 'date',
            'last_verified_at' => 'datetime',
            'cost' => 'decimal:2',
            'auto_renew' => 'boolean',
            'reminders_enabled' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Whole days from today until expiry. Negative once the date has passed.
     *
     * Both sides are floored to a date first: comparing a date column against
     * a timestamp is how "3 days before" ends up firing on day 2 or day 4.
     */
    public function daysRemaining(?Carbon $from = null): int
    {
        $today = ($from ?? Carbon::now(config('app.timezone')))->startOfDay();

        return (int) $today->diffInDays($this->expires_at->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->daysRemaining() < 0;
    }

    /**
     * The status the expiry date implies, independent of what is stored.
     */
    public function derivedStatus(): AssetStatus
    {
        if (in_array($this->status, AssetStatus::silent(), true)) {
            return $this->status;
        }

        $days = $this->daysRemaining();

        return match (true) {
            $days < 0 => AssetStatus::Expired,
            $days <= 10 => AssetStatus::Expiring,
            default => AssetStatus::Active,
        };
    }

    /**
     * Urgency tone for badges. Red at 2 days, amber at 5, per the design rules.
     */
    public function urgencyTone(): string
    {
        $days = $this->daysRemaining();

        return match (true) {
            in_array($this->status, AssetStatus::silent(), true) => $this->status->tone(),
            $days <= 2 => 'danger',
            $days <= 5 => 'warn',
            $days <= 30 => 'neutral',
            default => 'neutral',
        };
    }

    public function urgencyLabel(): string
    {
        $days = $this->daysRemaining();

        return match (true) {
            $this->status === AssetStatus::Renewed => 'Renewed',
            $this->status === AssetStatus::Cancelled => 'Cancelled',
            $days < -1 => abs($days).' days overdue',
            $days === -1 => 'A day overdue',
            $days === 0 => 'Expires today',
            $days === 1 => 'Tomorrow',
            default => "{$days} days",
        };
    }

    public function scopeWatched(Builder $query): void
    {
        $query->where('is_archived', false)
            ->where('reminders_enabled', true)
            ->whereNotIn('status', array_column(AssetStatus::silent(), 'value'));
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $query->whereBetween('expires_at', [$today, $today->copy()->addDays($days)]);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('expires_at', '<', Carbon::now(config('app.timezone'))->startOfDay())
            ->whereNotIn('status', array_column(AssetStatus::silent(), 'value'));
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('identifier', 'like', "%{$term}%")
                ->orWhere('provider', 'like', "%{$term}%");
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'identifier', 'type', 'expires_at', 'status', 'cost', 'owner_id', 'auto_renew', 'reminders_enabled', 'is_archived'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
