<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ClientStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Credential;
use App\Models\Project;
use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatusLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Realistic data on first run, so every screen has something to show and the
 * reminder windows all have something sitting inside them.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ReminderRuleSeeder::class);

        $team = $this->team();
        $clients = $this->clients($team);
        $this->assets($clients, $team);
        $projects = $this->projects($clients, $team);
        $this->tasks($clients, $projects, $team);
        $this->credentials($clients, $team);
        $this->recurring($clients, $team);
    }

    /** @return array<string, User> */
    private function team(): array
    {
        $people = [
            ['Aarthi Ramesh', 'admin@gnext.com', Role::Admin],
            ['Vignesh Kumar', 'manager@renewalguard.test', Role::Manager],
            ['Divya Nair', 'employee@renewalguard.test', Role::Employee],
            ['Suresh Babu', 'suresh@renewalguard.test', Role::Employee],
            ['Meera Iyer', 'meera@renewalguard.test', Role::Employee],
        ];

        $team = [];

        foreach ($people as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'phone' => '+91 9'.fake()->numerify('#########'),
                    'timezone' => 'Asia/Kolkata',
                ],
            );

            $user->syncRoles([$role->value]);

            $team[$role->value === 'employee' ? $email : $role->value] = $user;
        }

        return $team;
    }

    /** @return Collection<int, Client> */
    private function clients(array $team)
    {
        $names = [
            'Kanchi Silks', 'TVM Logistics', 'Anand Textiles', 'Marina Dental',
            'Nungambakkam Motors', 'Adyar Books', 'Chola Interiors', 'Velachery Fitness',
            'Besant Nagar Cafe', 'Guindy Machine Works', 'Mylapore Jewels', 'Porur Pharma',
        ];

        $managers = [$team[Role::Manager->value], $team[Role::Admin->value]];

        return collect($names)->map(fn (string $name, int $index) => Client::factory()->create([
            'company_name' => $name,
            'name' => fake()->name(),
            'account_manager_id' => $managers[$index % 2]->id,
            'send_renewal_notices' => $index % 4 === 0,
            'status' => $index === 11 ? ClientStatus::Dormant : ClientStatus::Active,
        ]));
    }

    private function assets($clients, array $team): void
    {
        $owners = collect($team)->values();

        /*
         * Sixty assets spread across the next sixty days, with several landing
         * inside every reminder window — including a few already overdue, so
         * the escalation path has something to escalate.
         */
        $offsets = collect(range(0, 59))
            ->map(fn (int $i) => match (true) {
                $i < 3 => -($i * 3 + 1),          // overdue: -1, -4, -7
                $i < 8 => $i - 3,                  // 0..4 days: the red zone
                $i < 14 => $i - 3,                 // 5..10 days: amber, and the task trigger
                $i < 24 => 11 + ($i - 14) * 2,     // 11..29
                default => 30 + ($i - 24),         // 30..65
            });

        $offsets->each(function (int $offset, int $index) use ($clients, $owners) {
            $type = collect(AssetType::cases())->random();

            Asset::factory()
                ->state(fn () => [
                    'client_id' => $clients->random()->id,
                    'owner_id' => $owners->random()->id,
                    'expires_at' => Carbon::now(config('app.timezone'))->addDays($offset)->startOfDay(),
                ])
                ->when($type === AssetType::Domain, fn ($f) => $f->domain())
                ->when($type === AssetType::Ssl, fn ($f) => $f->ssl())
                ->create();
        });

        // A couple already handled, so "Renewed" is not an empty filter.
        Asset::query()->inRandomOrder()->limit(3)->get()->each->update(['status' => AssetStatus::Renewed]);

        Asset::query()->get()->each(function (Asset $asset) {
            $asset->forceFill(['status' => $asset->derivedStatus()])->saveQuietly();
        });
    }

    private function projects($clients, array $team)
    {
        $leads = [$team[Role::Manager->value], $team[Role::Admin->value]];

        return collect([
            ['Website redesign', ProjectStatus::Active],
            ['E-commerce build', ProjectStatus::Active],
            ['Annual maintenance', ProjectStatus::Active],
            ['SEO retainer', ProjectStatus::Planning],
            ['Brand refresh', ProjectStatus::OnHold],
            ['Mobile app phase 1', ProjectStatus::Completed],
        ])->map(fn (array $row, int $index) => Project::factory()->create([
            'name' => $row[0],
            'status' => $row[1],
            'client_id' => $clients[$index]->id,
            'lead_id' => $leads[$index % 2]->id,
        ]));
    }

    private function tasks($clients, $projects, array $team): void
    {
        $employees = collect($team)->filter(fn (User $u) => $u->isEmployee())->values();
        $approver = $team[Role::Manager->value];

        $titles = [
            'Update the homepage banner', 'Migrate staging to PHP 8.3', 'Fix the contact form spam',
            'Add Google Analytics 4', 'Compress the product images', 'Set up the payment gateway',
            'Move DNS to Cloudflare', 'Write the September newsletter', 'Rebuild the sitemap',
            'Patch the WordPress plugins', 'Restore the October backup', 'Add WhatsApp click-to-chat',
            'Redesign the pricing page', 'Fix the mobile menu overlap', 'Set up staging SSL',
            'Import the product catalogue', 'Configure the SMTP relay', 'Clean up the media library',
            'Add schema markup', 'Set up the 301 redirects',
        ];

        $statuses = [
            TaskStatus::Open, TaskStatus::Assigned, TaskStatus::InProgress, TaskStatus::InProgress,
            TaskStatus::Submitted, TaskStatus::Submitted, TaskStatus::OnHold, TaskStatus::Blocked,
            TaskStatus::Reopened, TaskStatus::Completed,
        ];

        collect(range(0, 39))->each(function (int $index) use ($titles, $statuses, $clients, $projects, $employees, $approver) {
            $status = $statuses[$index % count($statuses)];
            $assignee = $status === TaskStatus::Open ? null : $employees[$index % $employees->count()];

            $task = Task::factory()->create([
                'title' => $titles[$index % count($titles)],
                'client_id' => $clients->random()->id,
                'project_id' => $index % 3 === 0 ? $projects->random()->id : null,
                'assigned_to' => $assignee?->id,
                'created_by' => $approver->id,
                'status' => $status,
                'priority' => [TaskPriority::Low, TaskPriority::Normal, TaskPriority::Normal, TaskPriority::High, TaskPriority::Urgent][$index % 5],
                'due_at' => match (true) {
                    $index % 7 === 0 => now()->subDays($index % 5 + 1)->setTime(17, 0),
                    $index % 5 === 0 => now()->setTime(17, 0),
                    default => now()->addDays($index % 21 + 1)->setTime(17, 0),
                },
                'started_at' => in_array($status, [TaskStatus::InProgress, TaskStatus::Submitted, TaskStatus::Completed], true) ? now()->subDays(2) : null,
                'submitted_at' => in_array($status, [TaskStatus::Submitted, TaskStatus::Completed], true) ? now()->subDay() : null,
                'completed_at' => $status === TaskStatus::Completed ? now()->subHours(6) : null,
                'reopen_count' => $status === TaskStatus::Reopened ? 1 : 0,
                'hold_reason' => in_array($status, [TaskStatus::OnHold, TaskStatus::Blocked], true) ? 'Waiting on the client for content' : null,
                'last_activity_at' => now()->subMinutes($index * 37),
            ]);

            TaskStatusLog::create([
                'task_id' => $task->id,
                'user_id' => $approver->id,
                'from_status' => null,
                'to_status' => TaskStatus::Open,
                'created_at' => $task->created_at,
                'updated_at' => $task->created_at,
            ]);

            if ($assignee) {
                $task->participants()->syncWithoutDetaching([
                    $assignee->id => ['role' => 'assignee'],
                    $approver->id => ['role' => 'watcher'],
                ]);
            }

            // Threaded conversation on roughly half of them.
            if ($index % 2 === 0 && $assignee) {
                $first = TaskMessage::factory()->create([
                    'task_id' => $task->id,
                    'user_id' => $approver->id,
                    'body' => 'Client called about this. Can you take a look today?',
                    'created_at' => now()->subHours(5),
                ]);

                TaskMessage::factory()->create([
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'body' => 'On it. Should be done before the end of the day.',
                    'reply_to_id' => $first->id,
                    'created_at' => now()->subHours(4),
                ]);

                if ($index % 6 === 0) {
                    TaskMessage::factory()->voice()->create([
                        'task_id' => $task->id,
                        'user_id' => $assignee->id,
                        'created_at' => now()->subHours(3),
                    ]);
                }

                TaskMessage::factory()->system()->create([
                    'task_id' => $task->id,
                    'body' => "{$approver->firstName()} moved this from Open to {$status->label()}",
                    'type' => 'status_change',
                    'created_at' => now()->subHours(2),
                ]);
            }
        });

        // Renewal-sourced tasks, so the auto-created path has real examples.
        Asset::query()
            ->with('client')
            ->whereBetween('expires_at', [now(), now()->addDays(12)])
            ->limit(3)
            ->get()
            ->each(fn (Asset $asset) => Task::createRenewalTask($asset, $asset->owner_id));
    }

    private function credentials($clients, array $team): void
    {
        $clients->take(6)->each(function (Client $client) use ($team) {
            Credential::factory()->count(2)->create([
                'client_id' => $client->id,
                'created_by' => $team[Role::Admin->value]->id,
            ]);
        });
    }

    private function recurring($clients, array $team): void
    {
        $employees = collect($team)->filter(fn (User $u) => $u->isEmployee())->values();

        RecurringTaskTemplate::factory()->count(3)->sequence(
            ['title' => 'Monthly WordPress and plugin updates', 'frequency' => 'monthly'],
            ['title' => 'Monthly uptime and backup check', 'frequency' => 'monthly'],
            ['title' => 'Quarterly SEO report', 'frequency' => 'quarterly'],
        )->create([
            'client_id' => $clients->random()->id,
            'assigned_to' => $employees->random()->id,
        ]);
    }
}
