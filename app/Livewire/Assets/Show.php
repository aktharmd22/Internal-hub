<?php

declare(strict_types=1);

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Services\Verification\AssetVerifier;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Asset $asset;

    public function mount(Asset $asset): void
    {
        $this->authorize('view', $asset);

        $this->asset = $asset;
    }

    #[On('asset-saved')]
    public function refreshAsset(): void
    {
        $this->asset->refresh();
    }

    public function renew(): void
    {
        $this->authorize('update', $this->asset);

        $months = match ($this->asset->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'half-yearly' => 6,
            'biennial' => 24,
            default => 12,
        };

        $this->asset->forceFill([
            'expires_at' => $this->asset->expires_at->copy()->addMonthsNoOverflow($months),
            'status' => AssetStatus::Active,
        ])->save();

        // A new cycle needs a clean idempotency slate, or the next round of
        // reminders would be suppressed by this cycle's log rows.
        $this->asset->reminderLogs()->delete();

        $this->dispatch('toast', message: 'Renewed to '.$this->asset->expires_at->format('j M Y').'.', tone: 'ok');
    }

    public function verify(AssetVerifier $verifier): void
    {
        $this->authorize('update', $this->asset);

        if (! $verifier->supports($this->asset)) {
            $this->dispatch('toast', message: 'Only domains and certificates can be checked automatically.', tone: 'warn');

            return;
        }

        $result = $verifier->verify($this->asset);
        $this->asset->refresh();

        $this->dispatch(
            'toast',
            message: $result->ok
                ? 'Registry says '.$result->expiresAt->format('j M Y').'.'
                : 'Could not check: '.$result->error,
            tone: $result->ok ? 'ok' : 'danger',
        );
    }

    public function toggleReminders(): void
    {
        $this->authorize('update', $this->asset);

        $this->asset->forceFill(['reminders_enabled' => ! $this->asset->reminders_enabled])->save();

        $this->dispatch(
            'toast',
            message: $this->asset->reminders_enabled ? 'Reminders on.' : 'Reminders off for this asset.',
            tone: $this->asset->reminders_enabled ? 'ok' : 'warn',
        );
    }

    public function archive(): void
    {
        $this->authorize('delete', $this->asset);

        $this->asset->forceFill(['is_archived' => true])->save();

        $this->redirectRoute('assets.index', navigate: true);
    }

    public function render(): View
    {
        $this->asset->loadMissing(['client', 'owner']);

        return view('livewire.assets.show', [
            'reminders' => $this->asset->reminderLogs()->latest('sent_at')->limit(12)->get(),
            'tasks' => $this->asset->tasks()->with('assignee')->latest()->limit(5)->get(),
            'history' => Activity::query()
                ->where('subject_type', 'asset')
                ->where('subject_id', $this->asset->id)
                ->with('causer')
                ->latest()
                ->limit(10)
                ->get(),
        ])->title($this->asset->name);
    }
}
