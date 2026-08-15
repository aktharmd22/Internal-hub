<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->randomElement([
                'Website redesign',
                'E-commerce build',
                'Annual maintenance',
                'SEO retainer',
                'Brand refresh',
                'Mobile app phase 1',
            ]),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Active,
            'starts_on' => now()->subWeeks(3),
            'deadline' => now()->addWeeks(6),
            'budget' => fake()->randomElement([45000, 80000, 120000, 250000]),
            'currency' => 'INR',
            'is_archived' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Completed]);
    }
}
