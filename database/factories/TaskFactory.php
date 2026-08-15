<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => fake()->randomElement([
                'Update the homepage banner',
                'Migrate staging to PHP 8.3',
                'Fix the contact form spam',
                'Add Google Analytics 4',
                'Compress product images',
                'Set up the payment gateway',
                'Move DNS to Cloudflare',
                'Write the September newsletter',
            ]),
            'description' => fake()->paragraph(),
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Open,
            'due_at' => now()->addDays(fake()->numberBetween(1, 21))->setTime(17, 0),
            'source' => TaskSource::Manual,
            'estimated_minutes' => fake()->randomElement([30, 60, 120, 240]),
            'is_archived' => false,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'submitted_at' => $status === TaskStatus::Submitted ? now() : null,
            'completed_at' => $status === TaskStatus::Completed ? now() : null,
            'started_at' => in_array($status, [TaskStatus::InProgress, TaskStatus::Submitted, TaskStatus::Completed], true) ? now()->subDay() : null,
        ]);
    }

    public function overdue(int $daysAgo = 3): static
    {
        return $this->state(fn () => ['due_at' => now()->subDays($daysAgo)->setTime(17, 0)]);
    }

    public function dueToday(): static
    {
        return $this->state(fn () => ['due_at' => now()->setTime(17, 0)]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => TaskPriority::Urgent]);
    }
}
