<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\Client;
use App\Models\RecurringTaskTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTaskTemplate>
 */
class RecurringTaskTemplateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                'Monthly WordPress and plugin updates',
                'Monthly uptime and backup check',
                'Quarterly SEO report',
            ]),
            'description' => 'Recurring maintenance.',
            'client_id' => Client::factory(),
            'priority' => TaskPriority::Normal,
            'frequency' => 'monthly',
            'day_of_month' => 1,
            'due_in_days' => 7,
            'next_run_at' => now()->addDay(),
            'is_active' => true,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['next_run_at' => now()->subHour()]);
    }
}
