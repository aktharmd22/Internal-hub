<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecipientScope;
use App\Enums\ReminderChannel;
use App\Models\ReminderRule;
use Illuminate\Database\Seeder;

class ReminderRuleSeeder extends Seeder
{
    public function run(): void
    {
        $internal = [
            ReminderChannel::Mail->value,
            ReminderChannel::Database->value,
            ReminderChannel::Broadcast->value,
            ReminderChannel::WebPush->value,
        ];

        /*
         * The default ladder. Early warnings go to whoever owns the asset;
         * the last few days and everything overdue also reach the admins
         * through the engine's escalation rule.
         */
        foreach ([30, 10, 5, 3, 2, 1, 0, -1, -3, -7] as $days) {
            ReminderRule::updateOrCreate(
                [
                    'asset_type' => null,
                    'days_before' => $days,
                    'recipient_scope' => RecipientScope::Owner,
                ],
                [
                    'channels' => $internal,
                    'is_active' => true,
                ],
            );
        }

        // A notice to the client, far enough out to become an invoice rather
        // than an emergency. Only reaches clients who opted in.
        ReminderRule::updateOrCreate(
            [
                'asset_type' => null,
                'days_before' => 15,
                'recipient_scope' => RecipientScope::Client,
            ],
            [
                'channels' => [ReminderChannel::Mail->value],
                'is_active' => true,
            ],
        );
    }
}
