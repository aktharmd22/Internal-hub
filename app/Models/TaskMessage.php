<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TaskMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskMessage extends Model implements HasMedia
{
    /** @use HasFactory<TaskMessageFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const EDIT_WINDOW_MINUTES = 15;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waveform' => 'array',
            'edited_at' => 'datetime',
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

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
        $this->addMediaCollection('voice')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(320)
            ->height(320)
            ->nonQueued()
            ->performOnCollections('attachments');
    }

    public function isSystem(): bool
    {
        return in_array($this->type, ['system', 'status_change'], true);
    }

    public function isVoice(): bool
    {
        return $this->type === 'voice';
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null
            && $this->user_id === $user->id
            && $this->type === 'text'
            && $this->created_at->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public function scopeVisible(Builder $query): void
    {
        $query->withTrashed();
    }

    public function durationLabel(): string
    {
        $seconds = (int) $this->duration_seconds;

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
