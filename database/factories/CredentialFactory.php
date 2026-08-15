<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'label' => fake()->randomElement(['cPanel', 'WordPress admin', 'Registrar account', 'FTP', 'Google Analytics']),
            'username' => fake()->userName(),
            'password' => fake()->password(14),
            'url' => 'https://'.fake()->domainName().'/admin',
            'notes' => null,
        ];
    }
}
