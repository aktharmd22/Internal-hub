<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecurringTaskTemplate;
use Illuminate\Console\Command;

class RunRecurringTasks extends Command
{
    protected $signature = 'tasks:run-recurring';

    protected $description = 'Create tasks from any recurring template that has come due';

    public function handle(): int
    {
        $created = 0;

        RecurringTaskTemplate::query()
            ->due()
            ->with(['client', 'project', 'assignee'])
            ->get()
            ->each(function (RecurringTaskTemplate $template) use (&$created) {
                $task = $template->spawn();
                $template->advance();

                $created++;

                $this->line("  {$task->reference} · {$task->title}");
            });

        $this->info("{$created} recurring ".str('task')->plural($created).' created.');

        return self::SUCCESS;
    }
}
