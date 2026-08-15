<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetType: string
{
    case Domain = 'domain';
    case Hosting = 'hosting';
    case Ssl = 'ssl';
    case Vps = 'vps';
    case Email = 'email';
    case Licence = 'licence';
    case Subscription = 'subscription';
    case Amc = 'amc';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Domain => 'Domain',
            self::Hosting => 'Hosting',
            self::Ssl => 'SSL certificate',
            self::Vps => 'VPS',
            self::Email => 'Email',
            self::Licence => 'Licence',
            self::Subscription => 'Subscription',
            self::Amc => 'AMC',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Domain => 'globe',
            self::Hosting, self::Vps => 'server',
            self::Ssl => 'shield-check',
            self::Email => 'mail',
            self::Licence, self::Subscription => 'file-text',
            self::Amc => 'refresh-cw',
            self::Other => 'inbox',
        };
    }

    /**
     * What the `identifier` column holds for this type, shown as form help text.
     */
    public function identifierHint(): string
    {
        return match ($this) {
            self::Domain => 'The domain itself, e.g. kanchisilks.com',
            self::Ssl => 'The host the certificate covers, e.g. api.example.in',
            self::Hosting, self::Vps => 'Server name, IP or control panel account',
            self::Email => 'Mailbox or the domain email is hosted for',
            default => 'Account number, licence key or reference',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Types whose expiry can be checked against an authoritative source.
     */
    public function isVerifiable(): bool
    {
        return in_array($this, [self::Domain, self::Ssl], true);
    }
}
