<?php

declare(strict_types=1);

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Assets')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $window = 'all';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: 'expires_at')]
    public string $sort = 'expires_at';

    public function mount(): void
    {
        $this->authorize('viewAny', Asset::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function setWindow(string $window): void
    {
        $this->window = $this->window === $window ? 'all' : $window;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'window', 'type', 'client']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return filled($this->search) || $this->window !== 'all' || filled($this->type) || filled($this->client);
    }

    /**
     * Marks an asset renewed by rolling its expiry forward one billing cycle.
     * Reminder logs for the old cycle are cleared so the new one starts clean.
     */
    public function renew(int $assetId): void
    {
        $asset = Asset::findOrFail($assetId);

        $this->authorize('update', $asset);

        $months = match ($asset->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'half-yearly' => 6,
            'biennial' => 24,
            default => 12,
        };

        $asset->forceFill([
            'expires_at' => $asset->expires_at->copy()->addMonthsNoOverflow($months),
            'status' => AssetStatus::Active,
        ])->save();

        $asset->reminderLogs()->delete();

        $this->dispatch('toast', message: "{$asset->name} renewed to ".$asset->expires_at->format('j M Y').'.', tone: 'ok');
    }

    public function render(): View
    {
        return view('livewire.assets.index', [
            'assets' => $this->assets(),
            'clients' => Client::query()->active()->orderBy('name')->get(['id', 'name', 'company_name']),
            'counts' => $this->counts(),
        ]);
    }

    /** @return LengthAwarePaginator<Asset> */
    private function assets(): LengthAwarePaginator
    {
        return Asset::query()
            ->active()
            ->search($this->search)
            ->when($this->type, fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->client, fn (Builder $q) => $q->where('client_id', $this->client))
            ->tap(fn (Builder $q) => $this->applyWindow($q))
            // Eager loading is not optional here: preventLazyLoading turns an
            // N+1 on this list into a hard failure outside production.
            ->with(['client:id,name,company_name', 'owner:id,name'])
            ->orderBy($this->sortColumn(), $this->sortDirection())
            ->paginate(25);
    }

    private function applyWindow(Builder $query): void
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        match ($this->window) {
            '7' => $query->whereBetween('expires_at', [$today, $today->copy()->addDays(7)])
                ->whereNotIn('status', ['renewed', 'cancelled']),
            '30' => $query->whereBetween('expires_at', [$today, $today->copy()->addDays(30)])
                ->whereNotIn('status', ['renewed', 'cancelled']),
            'expired' => $query->overdue(),
            'renewed' => $query->where('status', AssetStatus::Renewed),
            default => null,
        };
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $base = fn () => Asset::query()->active()->whereNotIn('status', ['renewed', 'cancelled']);

        return [
            '7' => (clone $base())->whereBetween('expires_at', [$today, $today->copy()->addDays(7)])->count(),
            '30' => (clone $base())->whereBetween('expires_at', [$today, $today->copy()->addDays(30)])->count(),
            'expired' => (clone $base())->where('expires_at', '<', $today)->count(),
        ];
    }

    private function sortColumn(): string
    {
        return match ($this->sort) {
            'name' => 'name',
            'cost' => 'cost',
            default => 'expires_at',
        };
    }

    private function sortDirection(): string
    {
        return $this->sort === 'cost' ? 'desc' : 'asc';
    }

    /** @return array<string, string> */
    public function getTypeOptionsProperty(): array
    {
        return AssetType::options();
    }
}
