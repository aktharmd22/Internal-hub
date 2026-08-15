<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Credential extends Model
{
    /** @use HasFactory<CredentialFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'notes'];

    protected function casts(): array
    {
        return [
            // Encrypted at rest. Never appears in a dump, a query log or a
            // stack trace in the clear.
            'password' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Every reveal is written to the activity log with the actor and the time.
     * A vault nobody can audit is a liability, not a feature.
     */
    public function recordAccess(User $user): void
    {
        activity('credential-access')
            ->performedOn($this)
            ->causedBy($user)
            ->withProperties([
                'label' => $this->label,
                'client' => $this->client->displayName(),
                'ip' => request()->ip(),
            ])
            ->event('revealed')
            ->log("revealed the password for {$this->label}");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'username', 'url', 'client_id', 'asset_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
