<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => fake()->name(),
            'company_name' => $name,
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+91 '.fake()->numberBetween(70000, 99999).' '.fake()->numberBetween(10000, 99999),
            'whatsapp' => '+91 '.fake()->numberBetween(70000, 99999).' '.fake()->numberBetween(10000, 99999),
            'address' => fake()->streetAddress().', Chennai',
            'gst_number' => strtoupper(fake()->bothify('33?????####?#Z#')),
            'status' => ClientStatus::Active,
            'send_renewal_notices' => false,
            'is_archived' => false,
        ];
    }

    public function dormant(): static
    {
        return $this->state(fn () => ['status' => ClientStatus::Dormant]);
    }

    public function billable(): static
    {
        return $this->state(fn () => ['send_renewal_notices' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_archived' => true]);
    }
}
