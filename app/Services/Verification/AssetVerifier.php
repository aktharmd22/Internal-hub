<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\AssetStatus;
use App\Enums\VerificationStatus;
use App\Models\Asset;
use App\Models\ReminderLog;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Support\Carbon;

/**
 * Checks a stored expiry date against the authoritative source and, when the
 * real date has moved forward, treats the asset as renewed.
 *
 * This is what makes renewals self-detecting. Nobody has to remember to come
 * back and update the record after paying the registrar — the next verification
 * run notices, advances the date, clears the reminder logs for the old cycle
 * and tells the renewal task it can close.
 */
class AssetVerifier
{
    /** @var list<ExpiryVerifier> */
    private array $verifiers;

    public function __construct(RdapDomainVerifier $rdap, SslCertificateVerifier $ssl)
    {
        $this->verifiers = [$rdap, $ssl];
    }

    public function supports(Asset $asset): bool
    {
        return $this->verifierFor($asset) !== null;
    }

    public function verify(Asset $asset): VerificationResult
    {
        $verifier = $this->verifierFor($asset);

        if (! $verifier) {
            return VerificationResult::failed('No verifier for this asset type');
        }

        $result = $verifier->verify($asset);

        if (! $result->ok) {
            $asset->forceFill([
                'verification_status' => VerificationStatus::Failed,
                'last_verified_at' => now(),
            ])->saveQuietly();

            return $result;
        }

        $stored = $asset->expires_at->startOfDay();
        $actual = $result->expiresAt;

        $asset->forceFill([
            'verified_expires_at' => $actual,
            'last_verified_at' => now(),
            'verification_status' => $actual->equalTo($stored)
                ? VerificationStatus::Match
                : VerificationStatus::Mismatch,
        ])->saveQuietly();

        if ($actual->greaterThan($stored)) {
            $this->recordRenewal($asset, $actual);
        }

        return $result;
    }

    /**
     * The verified date is later than ours: somebody renewed and did not say so.
     */
    private function recordRenewal(Asset $asset, Carbon $actual): void
    {
        $previous = $asset->expires_at->copy();

        $asset->forceFill([
            'expires_at' => $actual,
            'status' => AssetStatus::Active,
            'verification_status' => VerificationStatus::Match,
            'verified_expires_at' => $actual,
        ])->save();

        // Clear the logs for the cycle that just ended so the new cycle starts
        // with a clean idempotency slate. Without this the next round of
        // reminders would be suppressed by last year's rows.
        ReminderLog::query()->where('asset_id', $asset->id)->delete();

        Task::query()
            ->openForAsset($asset)
            ->get()
            ->each(function (Task $task) use ($asset, $previous, $actual) {
                TaskMessage::create([
                    'task_id' => $task->id,
                    'user_id' => null,
                    'type' => 'system',
                    'body' => sprintf(
                        'Verified with the registry: %s now expires %s (was %s). Renewal appears to be done.',
                        $asset->name,
                        $actual->format('j M Y'),
                        $previous->format('j M Y'),
                    ),
                ]);

                $task->forceFill(['last_activity_at' => now()])->save();
            });

        activity('asset-verification')
            ->performedOn($asset)
            ->withProperties([
                'previous_expiry' => $previous->toDateString(),
                'verified_expiry' => $actual->toDateString(),
            ])
            ->event('renewal-detected')
            ->log("detected a renewal for {$asset->name}");
    }

    private function verifierFor(Asset $asset): ?ExpiryVerifier
    {
        foreach ($this->verifiers as $verifier) {
            if ($verifier->supports($asset)) {
                return $verifier;
            }
        }

        return null;
    }
}
