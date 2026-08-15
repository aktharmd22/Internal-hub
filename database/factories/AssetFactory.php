<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\VerificationStatus;
use App\Models\Asset;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $type = fake()->randomElement(AssetType::cases());
        $identifier = $this->identifierFor($type);

        return [
            'client_id' => Client::factory(),
            'type' => $type,
            'name' => $identifier,
            'identifier' => $identifier,
            'provider' => fake()->randomElement(['GoDaddy', 'Namecheap', 'ResellerClub', 'Hostinger', 'DigitalOcean', "Let's Encrypt", 'Zoho']),
            'provider_account' => fake()->bothify('ACC-#####'),
            'purchased_at' => now()->subYear(),
            'expires_at' => now()->addDays(fake()->numberBetween(1, 60))->startOfDay(),
            'verification_status' => VerificationStatus::Unchecked,
            'cost' => fake()->randomElement([899, 1200, 2400, 4500, 8000, 14000]),
            'currency' => 'INR',
            'billing_cycle' => 'yearly',
            'auto_renew' => fake()->boolean(30),
            'status' => AssetStatus::Active,
            'reminders_enabled' => true,
            'is_archived' => false,
        ];
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addDays($days)->startOfDay(),
        ]);
    }

    public function expired(int $daysAgo = 3): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDays($daysAgo)->startOfDay(),
            'status' => AssetStatus::Expired,
        ]);
    }

    public function domain(): static
    {
        return $this->state(function () {
            $domain = fake()->unique()->domainName();

            return ['type' => AssetType::Domain, 'name' => $domain, 'identifier' => $domain];
        });
    }

    public function ssl(): static
    {
        return $this->state(function () {
            $host = 'www.'.fake()->unique()->domainName();

            return ['type' => AssetType::Ssl, 'name' => "SSL · {$host}", 'identifier' => $host];
        });
    }

    public function renewed(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::Renewed]);
    }

    public function remindersOff(): static
    {
        return $this->state(fn () => ['reminders_enabled' => false]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_archived' => true]);
    }

    private function identifierFor(AssetType $type): string
    {
        return match ($type) {
            AssetType::Domain => fake()->unique()->domainName(),
            AssetType::Ssl => 'www.'.fake()->unique()->domainName(),
            AssetType::Hosting, AssetType::Vps => fake()->unique()->domainWord().'-server',
            AssetType::Email => 'mail.'.fake()->unique()->domainName(),
            default => strtoupper(fake()->unique()->bothify('LIC-####-????')),
        };
    }
}
