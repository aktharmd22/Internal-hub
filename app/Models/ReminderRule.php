<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\RecipientScope;
use Database\Factories\ReminderRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    /** @use HasFactory<ReminderRuleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'recipient_scope' => RecipientScope::class,
            'channels' => 'array',
            'days_before' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Rules that apply to a given asset type: the type-specific ones if any
     * exist for that day, otherwise the global fallback.
     */
    public function scopeForType(Builder $query, AssetType $type): void
    {
        $query->where(function (Builder $q) use ($type) {
            $q->whereNull('asset_type')->orWhere('asset_type', $type->value);
        });
    }

    public function describeTiming(): string
    {
        return match (true) {
            $this->days_before > 1 => "{$this->days_before} days before",
            $this->days_before === 1 => 'The day before',
            $this->days_before === 0 => 'On the day',
            $this->days_before === -1 => 'A day overdue',
            default => abs($this->days_before).' days overdue',
        };
    }
}
