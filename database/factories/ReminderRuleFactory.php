<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecipientScope;
use App\Enums\ReminderChannel;
use App\Models\ReminderRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderRule>
 */
class ReminderRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'asset_type' => null,
            'days_before' => 10,
            'channels' => [ReminderChannel::Mail->value, ReminderChannel::Database->value],
            'recipient_scope' => RecipientScope::Owner,
            'is_active' => true,
        ];
    }

    public function daysBefore(int $days): static
    {
        return $this->state(fn () => ['days_before' => $days]);
    }

    public function scope(RecipientScope $scope): static
    {
        return $this->state(fn () => ['recipient_scope' => $scope]);
    }

    /** @param  list<string>  $channels */
    public function channels(array $channels): static
    {
        return $this->state(fn () => ['channels' => $channels]);
    }
}
