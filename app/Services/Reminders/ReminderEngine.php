<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\RecipientScope;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\ReminderLog;
use App\Models\ReminderRule;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AssetExpiring;
use App\Services\Healthcheck;
use App\Support\Channels;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The reminder engine.
 *
 * Running this five times in one day must send exactly one set of
 * notifications. That guarantee does not come from the checks in this class â€”
 * it comes from the unique index on `reminder_logs`. The checks here only save
 * the database some work; the index is what makes a duplicate impossible when
 * a queue job retries or the scheduler double-fires.
 */
class ReminderEngine
{
    /** Days either side of today that are worth scanning. */
    private const LOOK_BACK = 7;

    private const LOOK_AHEAD = 45;

    /** At or below this, every admin is notified as well as the owner. */
    private const ESCALATE_AT = 3;

    /** At this point a reminder is not enough and a task is created. */
    private const TASK_AT = 10;

    public function __construct(private Healthcheck $healthcheck) {}

    public function run(?Carbon $today = null, bool $ping = true): RunSummary
    {
        $today = ($today ?? Carbon::now(config('app.timezone')))->startOfDay();
        $summary = new RunSummary;

        $rules = ReminderRule::query()->active()->get();
        $admins = $this->admins();

        $this->assetsInWindow($today)->each(function (Asset $asset) use ($today, $rules, $admins, $summary) {
            $summary->assetsScanned++;

            $days = $asset->daysRemaining($today);

            $this->syncStatus($asset, $summary);
            $this->createRenewalTask($asset, $days, $summary);

            foreach ($this->rulesFor($rules, $asset, $days) as $rule) {
                foreach ($this->recipients($asset, $rule, $days, $admins) as $recipient) {
                    foreach (Channels::filter($rule->channels ?? []) as $channel) {
                        $this->deliver($asset, $rule, $recipient, $channel, $days, $summary);
                    }
                }
            }
        });

        if ($ping) {
            // Last thing, and only on a clean run.
            $summary->failures === 0
                ? $this->healthcheck->ping()
                : $this->healthcheck->fail("{$summary->failures} reminder(s) failed");
        }

        return $summary;
    }

    /**
     * @return EloquentCollection<int, Asset>
     */
    private function assetsInWindow(Carbon $today): EloquentCollection
    {
        return Asset::query()
            ->watched()
            ->whereBetween('expires_at', [
                $today->copy()->subDays(self::LOOK_BACK),
                $today->copy()->addDays(self::LOOK_AHEAD),
            ])
            ->with(['client.accountManager', 'owner'])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Type-specific rules win outright for a given day; the global rules are
     * only a fallback, never an addition, or an agency that wants domains
     * handled differently would get both sets.
     *
     * @param  EloquentCollection<int, ReminderRule>  $rules
     * @return Collection<int, ReminderRule>
     */
    private function rulesFor(EloquentCollection $rules, Asset $asset, int $days): Collection
    {
        $onThisDay = $rules->where('days_before', $days);

        $specific = $onThisDay->filter(fn (ReminderRule $rule) => $rule->asset_type === $asset->type);

        return $specific->isNotEmpty()
            ? $specific
            : $onThisDay->filter(fn (ReminderRule $rule) => $rule->asset_type === null);
    }

    /**
     * @param  EloquentCollection<int, User>  $admins
     * @return Collection<int, Model>
     */
    private function recipients(Asset $asset, ReminderRule $rule, int $days, EloquentCollection $admins): Collection
    {
        $recipients = match ($rule->recipient_scope) {
            RecipientScope::Owner => collect([$asset->owner ?? $asset->client->accountManager])->filter(),
            RecipientScope::AccountManager => collect([$asset->client->accountManager])->filter(),
            RecipientScope::Admins => $admins,
            RecipientScope::Client => $asset->client->send_renewal_notices && filled($asset->client->email)
                ? collect([$asset->client])
                : collect(),
        };

        // Escalation: close to the wire, the owner alone is not enough. This
        // applies to internal scopes only â€” the client is never escalated to.
        if ($days <= self::ESCALATE_AT && $rule->recipient_scope !== RecipientScope::Client) {
            $recipients = $recipients->concat($admins);
        }

        // An owner who is also an admin must not be notified twice.
        return $recipients
            ->filter(fn (?Model $model) => $model !== null)
            ->unique(fn (Model $model) => $model->getMorphClass().':'.$model->getKey())
            ->values();
    }

    /**
     * Writes the log row and queues the notification in one transaction.
     *
     * The insert comes first on purpose: if it collides with the unique index
     * nothing is queued, so a duplicate cannot escape even under a race.
     */
    private function deliver(
        Asset $asset,
        ReminderRule $rule,
        Model $recipient,
        string $channel,
        int $days,
        RunSummary $summary,
    ): void {
        try {
            DB::transaction(function () use ($asset, $rule, $recipient, $channel, $days) {
                ReminderLog::create([
                    'asset_id' => $asset->id,
                    'reminder_rule_id' => $rule->id,
                    'days_before' => $days,
                    'channel' => $channel,
                    'recipient_type' => $recipient->getMorphClass(),
                    'recipient_id' => $recipient->getKey(),
                    'sent_at' => now(),
                    'status' => 'sent',
                ]);

                $recipient->notify(new AssetExpiring(
                    asset: $asset,
                    daysRemaining: $days,
                    channel: $channel,
                    isEscalation: $days <= self::ESCALATE_AT,
                ));
            });

            $summary->remindersSent++;
        } catch (QueryException $e) {
            if (! $this->isDuplicate($e)) {
                $summary->fail("asset {$asset->id} / {$channel}: {$e->getMessage()}");
                Log::error('Reminder delivery failed.', ['asset' => $asset->id, 'channel' => $channel, 'error' => $e->getMessage()]);

                return;
            }

            // Already sent on an earlier run today. This is the system working.
            $summary->remindersSkipped++;
        } catch (\Throwable $e) {
            $summary->fail("asset {$asset->id} / {$channel}: {$e->getMessage()}");
            Log::error('Reminder delivery failed.', ['asset' => $asset->id, 'channel' => $channel, 'error' => $e->getMessage()]);
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        // 23000/23505 is an integrity constraint violation on MySQL, MariaDB,
        // SQLite and Postgres alike.
        return in_array($e->getCode(), ['23000', '23505'], true);
    }

    private function syncStatus(Asset $asset, RunSummary $summary): void
    {
        $derived = $asset->derivedStatus();

        if ($derived !== $asset->status) {
            $asset->forceFill(['status' => $derived])->saveQuietly();
            $summary->statusesUpdated++;
        }
    }

    /**
     * A reminder can be ignored. A task cannot: it has an owner, a due date and
     * a status somebody has to change.
     */
    private function createRenewalTask(Asset $asset, int $days, RunSummary $summary): void
    {
        if ($days !== self::TASK_AT) {
            return;
        }

        if (Task::openForAsset($asset)->exists()) {
            return;
        }

        $assignee = $asset->owner_id ?? $asset->client->account_manager_id;

        Task::createRenewalTask($asset, $assignee);

        $summary->tasksCreated++;
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function admins(): EloquentCollection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::Admin->value))
            ->get();
    }
}
