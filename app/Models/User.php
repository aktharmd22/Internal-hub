<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'is_active',
        'notification_prefs',
        'timezone',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notification_prefs' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function participatingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_participants')
            ->withPivot(['role', 'muted_at'])
            ->withTimestamps();
    }

    public function ownedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'owner_id');
    }

    public function managedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'account_manager_id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    /**
     * The timer currently running for this user, if any. Starting a new one
     * stops this first — nobody is on two tasks at the same second.
     */
    public function runningTimer(): ?TaskTimeLog
    {
        return $this->timeLogs()->whereNull('stopped_at')->latest('started_at')->first();
    }

    public function role(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return $name ? Role::tryFrom($name) : null;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    public function isManager(): bool
    {
        return $this->hasRole(Role::Manager->value);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole(Role::Employee->value);
    }

    /**
     * Only the first name is used in greetings — "Good morning, Ravi" reads
     * better than the full legal name on a 375px header.
     */
    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }
}
