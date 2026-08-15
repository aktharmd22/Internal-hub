<?php

declare(strict_types=1);

namespace App\Livewire\Assets;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Services\Import\AssetCsvParser;
use App\Services\Import\AssetRow;
use App\Services\Verification\RdapDomainVerifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk entry. Adding assets one at a time is where this system dies in month
 * two, so all three routes in ship together: a CSV with a dry-run preview, a
 * paste-a-list box that fills expiry dates from RDAP, and duplicate detection
 * on both.
 */
#[Layout('components.layouts.app')]
#[Title('Import assets')]
class Import extends Component
{
    use WithFileUploads;

    public string $mode = 'csv';

    public $file;

    public string $pasted = '';

    public ?int $pasteClientId = null;

    public bool $lookupExpiry = true;

    /** @var array<int, array<string, mixed>> */
    public array $preview = [];

    public bool $previewed = false;

    public function mount(): void
    {
        $this->authorize('import', Asset::class);
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print AssetCsvParser::template(),
            'renewal-guard-assets-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    /* ------------------------------------------------------------------ CSV */

    public function previewCsv(AssetCsvParser $parser): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], attributes: ['file' => 'CSV file']);

        $rows = $parser->parse($this->file->getRealPath());

        $this->preview = $rows->map(fn (AssetRow $row) => [
            'line' => $row->line,
            'attributes' => $row->attributes,
            'errors' => $row->errors,
            'newClient' => $row->newClient,
            'duplicate' => $row->duplicate,
            'skip' => ! $row->isValid() || $row->duplicate,
        ])->all();

        $this->previewed = true;

        if ($this->preview === []) {
            $this->addError('file', 'That file had no readable rows.');
        }
    }

    /* --------------------------------------------------------- paste a list */

    public function previewPaste(RdapDomainVerifier $rdap): void
    {
        $this->validate([
            'pasted' => ['required', 'string', 'max:20000'],
            'pasteClientId' => ['required', 'exists:clients,id'],
        ], attributes: ['pasteClientId' => 'client']);

        $domains = Str::of($this->pasted)
            ->replace([',', ';', "\r"], "\n")
            ->explode("\n")
            ->map(fn (string $line) => $this->normalise($line))
            ->filter()
            ->unique()
            ->take(100)
            ->values();

        $existing = Asset::query()
            ->where('client_id', $this->pasteClientId)
            ->whereIn('identifier', $domains->all())
            ->pluck('identifier')
            ->all();

        $this->preview = $domains->map(function (string $domain, int $index) use ($rdap, $existing) {
            $expiry = null;
            $provider = null;
            $error = null;

            if ($this->lookupExpiry) {
                $asset = new Asset(['type' => AssetType::Domain, 'identifier' => $domain, 'name' => $domain]);
                $result = $rdap->verify($asset);

                $expiry = $result->ok ? $result->expiresAt->toDateString() : null;
                $error = $result->ok ? null : $result->error;
            }

            return [
                'line' => $index + 1,
                'attributes' => [
                    'client_id' => $this->pasteClientId,
                    'client_name' => null,
                    'type' => AssetType::Domain->value,
                    'name' => $domain,
                    'identifier' => $domain,
                    'provider' => $provider,
                    'expires_at' => $expiry,
                    'currency' => 'INR',
                    'billing_cycle' => 'yearly',
                    'auto_renew' => false,
                ],
                // A domain we could not look up is not an error — it just needs
                // a date typed in afterwards.
                'errors' => $expiry ? [] : array_filter([$error ?? 'No expiry found; set one after importing']),
                'newClient' => false,
                'duplicate' => in_array($domain, $existing, true),
                'skip' => in_array($domain, $existing, true),
            ];
        })->all();

        $this->previewed = true;
    }

    public function toggleRow(int $index): void
    {
        if (isset($this->preview[$index])) {
            $this->preview[$index]['skip'] = ! $this->preview[$index]['skip'];
        }
    }

    public function commit(): void
    {
        $this->authorize('import', Asset::class);

        $rows = collect($this->preview)->reject(fn (array $row) => $row['skip']);

        if ($rows->isEmpty()) {
            $this->dispatch('toast', message: 'Nothing selected to import.', tone: 'warn');

            return;
        }

        $created = 0;
        $clientCache = [];

        DB::transaction(function () use ($rows, &$created, &$clientCache) {
            foreach ($rows as $row) {
                $attributes = $row['attributes'];

                $clientId = $attributes['client_id'] ?? null;

                if (! $clientId && filled($attributes['client_name'] ?? null)) {
                    $key = Str::lower($attributes['client_name']);
                    $clientId = $clientCache[$key] ??= Client::create([
                        'name' => $attributes['client_name'],
                        'company_name' => $attributes['client_name'],
                    ])->id;
                }

                if (! $clientId) {
                    continue;
                }

                // A row with no expiry still gets a date, a month out, so it
                // never silently drops out of the reminder window.
                $expiresAt = $attributes['expires_at']
                    ? Carbon::parse($attributes['expires_at'])
                    : Carbon::now(config('app.timezone'))->addMonth()->startOfDay();

                $asset = Asset::create([
                    'client_id' => $clientId,
                    'type' => $attributes['type'] ?? AssetType::Other->value,
                    'name' => $attributes['name'],
                    'identifier' => $attributes['identifier'] ?? null,
                    'provider' => $attributes['provider'] ?? null,
                    'provider_account' => $attributes['provider_account'] ?? null,
                    'expires_at' => $expiresAt,
                    'purchased_at' => $attributes['purchased_at'] ?? null,
                    'cost' => $attributes['cost'] ?? null,
                    'currency' => $attributes['currency'] ?? 'INR',
                    'billing_cycle' => $attributes['billing_cycle'] ?? 'yearly',
                    'auto_renew' => (bool) ($attributes['auto_renew'] ?? false),
                    'owner_id' => $attributes['owner_id'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'reminders_enabled' => true,
                    'status' => AssetStatus::Active,
                ]);

                $asset->forceFill(['status' => $asset->derivedStatus()])->saveQuietly();

                $created++;
            }
        });

        $this->reset(['preview', 'previewed', 'file', 'pasted']);

        $this->dispatch('toast', message: "{$created} ".str('asset')->plural($created).' imported.', tone: 'ok');

        $this->redirectRoute('assets.index', navigate: true);
    }

    public function startOver(): void
    {
        $this->reset(['preview', 'previewed', 'file', 'pasted']);
        $this->resetValidation();
    }

    private function normalise(string $line): ?string
    {
        $line = trim($line);

        if (blank($line)) {
            return null;
        }

        $host = parse_url(str_contains($line, '://') ? $line : "https://{$line}", PHP_URL_HOST) ?: $line;
        $host = rtrim(strtolower(preg_replace('/^www\./', '', trim($host))), '.');

        return str_contains($host, '.') ? $host : null;
    }

    public function render(): View
    {
        return view('livewire.assets.import', [
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'validCount' => collect($this->preview)->reject(fn ($r) => $r['skip'])->count(),
            'skipCount' => collect($this->preview)->filter(fn ($r) => $r['skip'])->count(),
        ]);
    }
}
