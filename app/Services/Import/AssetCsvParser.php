<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads an asset CSV row by row and reports what each line would do.
 *
 * Parsing is deliberately native `fgetcsv` rather than a spreadsheet library:
 * it streams one line at a time, so a 5,000-row import runs inside the modest
 * memory limit of a shared host instead of loading the whole sheet into RAM.
 */
class AssetCsvParser
{
    public const COLUMNS = [
        'client',
        'type',
        'name',
        'identifier',
        'provider',
        'provider_account',
        'expires_at',
        'purchased_at',
        'cost',
        'currency',
        'billing_cycle',
        'auto_renew',
        'owner_email',
        'notes',
    ];

    private const MAX_ROWS = 5000;

    /**
     * @return Collection<int, AssetRow>
     */
    public function parse(string $path): Collection
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return collect();
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return collect();
        }

        // Strip a UTF-8 BOM, which Excel writes and which otherwise turns the
        // first header into "﻿client" and breaks every mapping.
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        $header = array_map(fn ($value) => Str::of((string) $value)->trim()->lower()->replace(' ', '_')->toString(), $header);

        $clients = Client::query()->active()->get();
        $owners = User::query()->where('is_active', true)->get(['id', 'email']);

        $rows = collect();
        $line = 1;

        while (($values = fgetcsv($handle)) !== false && $rows->count() < self::MAX_ROWS) {
            $line++;

            if (count(array_filter($values, fn ($v) => filled($v))) === 0) {
                continue;
            }

            $rows->push($this->buildRow($line, $this->combine($header, $values), $clients, $owners));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $values
     * @return array<string, string>
     */
    private function combine(array $header, array $values): array
    {
        $values = array_pad(array_slice($values, 0, count($header)), count($header), null);

        return array_map(fn ($value) => trim((string) $value), array_combine($header, $values));
    }

    /**
     * @param  array<string, string>  $raw
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, User>  $owners
     */
    private function buildRow(int $line, array $raw, Collection $clients, Collection $owners): AssetRow
    {
        $errors = [];

        $clientName = $raw['client'] ?? '';
        $client = $clients->first(fn (Client $c) => $this->matches($c->name, $clientName) || $this->matches($c->company_name ?? '', $clientName));

        if (blank($clientName)) {
            $errors[] = 'Client is missing';
        }

        $type = AssetType::tryFrom(Str::of($raw['type'] ?? '')->lower()->trim()->toString());

        if (! $type) {
            $errors[] = 'Type must be one of: '.implode(', ', array_column(AssetType::cases(), 'value'));
        }

        $expiresAt = $this->date($raw['expires_at'] ?? '');

        if (! $expiresAt) {
            $errors[] = 'Expiry date is missing or unreadable';
        }

        $identifier = $raw['identifier'] ?? '';
        $name = filled($raw['name'] ?? '') ? $raw['name'] : $identifier;

        if (blank($name)) {
            $errors[] = 'Needs a name or an identifier';
        }

        $duplicate = false;

        if ($client && filled($identifier)) {
            $duplicate = Asset::query()
                ->where('client_id', $client->id)
                ->where('identifier', $identifier)
                ->exists();
        }

        $owner = filled($raw['owner_email'] ?? '')
            ? $owners->firstWhere('email', strtolower($raw['owner_email']))
            : null;

        if (filled($raw['owner_email'] ?? '') && ! $owner) {
            $errors[] = "No user with the email {$raw['owner_email']}";
        }

        return new AssetRow(
            line: $line,
            attributes: [
                'client_name' => $clientName,
                'client_id' => $client?->id,
                'type' => $type?->value,
                'name' => $name,
                'identifier' => $identifier ?: null,
                'provider' => $raw['provider'] ?: null,
                'provider_account' => $raw['provider_account'] ?? null ?: null,
                'expires_at' => $expiresAt?->toDateString(),
                'purchased_at' => $this->date($raw['purchased_at'] ?? '')?->toDateString(),
                'cost' => is_numeric($raw['cost'] ?? '') ? (float) $raw['cost'] : null,
                'currency' => Str::upper($raw['currency'] ?? '') ?: 'INR',
                'billing_cycle' => Str::lower($raw['billing_cycle'] ?? '') ?: 'yearly',
                'auto_renew' => in_array(Str::lower($raw['auto_renew'] ?? ''), ['1', 'yes', 'true', 'y'], true),
                'owner_id' => $owner?->id,
                'notes' => $raw['notes'] ?? null ?: null,
            ],
            errors: $errors,
            newClient: $client === null && filled($clientName),
            duplicate: $duplicate,
        );
    }

    private function matches(string $a, string $b): bool
    {
        return Str::of($a)->trim()->lower()->toString() === Str::of($b)->trim()->lower()->toString();
    }

    private function date(string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        // Indian sheets are overwhelmingly d/m/Y; ISO is what exports produce.
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j M Y', 'd M Y', 'm/d/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value, config('app.timezone'));

                if ($parsed && $parsed->format($format) === $value) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function template(): string
    {
        $header = implode(',', self::COLUMNS);

        $examples = [
            'Kanchi Silks,domain,kanchisilks.com,kanchisilks.com,GoDaddy,ACC-10293,2027-03-14,2020-03-14,1200,INR,yearly,no,vignesh@example.com,Renewed by the client directly',
            'TVM Logistics,ssl,SSL · api.tvmlogistics.in,api.tvmlogistics.in,Let\'s Encrypt,,2026-09-02,,0,INR,quarterly,yes,,Auto via certbot',
            'Anand Textiles,hosting,Shared hosting,anand-shared,Hostinger,HP-88213,2026-11-30,2024-11-30,8400,INR,yearly,no,,',
        ];

        return $header."\n".implode("\n", $examples)."\n";
    }
}
