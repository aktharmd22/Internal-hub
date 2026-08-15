<?php

declare(strict_types=1);

namespace App\Services\Reminders;

class RunSummary
{
    public int $assetsScanned = 0;

    public int $remindersSent = 0;

    public int $remindersSkipped = 0;

    public int $statusesUpdated = 0;

    public int $tasksCreated = 0;

    public int $failures = 0;

    /** @var list<string> */
    public array $errors = [];

    public function fail(string $message): void
    {
        $this->failures++;
        $this->errors[] = $message;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'assets_scanned' => $this->assetsScanned,
            'reminders_sent' => $this->remindersSent,
            'reminders_skipped' => $this->remindersSkipped,
            'statuses_updated' => $this->statusesUpdated,
            'tasks_created' => $this->tasksCreated,
            'failures' => $this->failures,
        ];
    }
}
