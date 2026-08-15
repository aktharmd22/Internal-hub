<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskMessage>
 */
class TaskMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'type' => 'text',
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'type' => 'system',
        ]);
    }

    public function voice(int $seconds = 14): static
    {
        return $this->state(fn () => [
            'type' => 'voice',
            'body' => null,
            'duration_seconds' => $seconds,
            'waveform' => collect(range(1, 40))->map(fn () => fake()->numberBetween(8, 100))->all(),
        ]);
    }
}
