<?php

use App\Actions\TaskStatusTransition;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Notifications\TaskStatusChanged;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Notification::fake();

    $this->employee = User::factory()->employee()->create(['name' => 'Divya Nair']);
    $this->manager = User::factory()->manager()->create(['name' => 'Vignesh Kumar']);
    $this->admin = User::factory()->admin()->create(['name' => 'Aarthi Ramesh']);

    $this->transition = app(TaskStatusTransition::class);
});

function taskFor(User $assignee, TaskStatus $status = TaskStatus::Assigned): Task
{
    return Task::factory()->create([
        'assigned_to' => $assignee->id,
        'status' => $status,
    ]);
}

/*
 * The review gate. Without it, "completed" stops meaning anything within two
 * months and reopen_count is worthless as a quality signal.
 */
test('an employee cannot mark their own work completed', function () {
    $task = taskFor($this->employee, TaskStatus::Submitted);

    expect(fn () => ($this->transition)($task, TaskStatus::Completed, $this->employee))
        ->toThrow(AuthorizationException::class);

    expect($task->fresh()->status)->toBe(TaskStatus::Submitted);
});

test('an employee can submit their work for review', function () {
    $task = taskFor($this->employee, TaskStatus::InProgress);

    ($this->transition)($task, TaskStatus::Submitted, $this->employee);

    expect($task->fresh()->status)->toBe(TaskStatus::Submitted)
        ->and($task->fresh()->submitted_at)->not->toBeNull();
});

test('a manager approves and the task completes', function () {
    $task = taskFor($this->employee, TaskStatus::Submitted);

    ($this->transition)($task, TaskStatus::Completed, $this->manager);

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed)
        ->and($task->completed_at)->not->toBeNull();
});

test('rejecting sends the task back and increments the reopen count', function () {
    $task = taskFor($this->employee, TaskStatus::Submitted);

    ($this->transition)($task, TaskStatus::Reopened, $this->manager, 'The banner is still the old one');

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Reopened)
        ->and($task->reopen_count)->toBe(1)
        ->and($task->completed_at)->toBeNull()
        ->and($task->submitted_at)->toBeNull();
});

test('an employee cannot reopen work either', function () {
    $task = taskFor($this->employee, TaskStatus::Submitted);

    expect(fn () => ($this->transition)($task, TaskStatus::Reopened, $this->employee, 'nope'))
        ->toThrow(AuthorizationException::class);
});

test('an illegal transition is refused outright', function () {
    $task = taskFor($this->employee, TaskStatus::Open);

    // open cannot jump straight to submitted.
    expect(fn () => ($this->transition)($task, TaskStatus::Submitted, $this->admin))
        ->toThrow(ValidationException::class);
});

test('putting a task on hold requires a reason', function () {
    $task = taskFor($this->employee, TaskStatus::InProgress);

    expect(fn () => ($this->transition)($task, TaskStatus::OnHold, $this->employee))
        ->toThrow(ValidationException::class);

    ($this->transition)($task, TaskStatus::OnHold, $this->employee, 'Waiting on the client logo');

    expect($task->fresh()->status)->toBe(TaskStatus::OnHold)
        ->and($task->fresh()->hold_reason)->toBe('Waiting on the client logo');
});

test('every transition is written to the status log', function () {
    $task = taskFor($this->employee, TaskStatus::Assigned);

    ($this->transition)($task, TaskStatus::InProgress, $this->employee);
    ($this->transition)($task, TaskStatus::Submitted, $this->employee);

    $logs = TaskStatusLog::where('task_id', $task->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->from_status)->toBe(TaskStatus::Assigned)
        ->and($logs[0]->to_status)->toBe(TaskStatus::InProgress)
        ->and($logs[1]->to_status)->toBe(TaskStatus::Submitted);
});

/*
 * The timeline and the conversation are one continuous history, so a status
 * change has to appear in the thread rather than in a separate audit tab
 * nobody opens.
 */
test('every transition posts a system message into the thread', function () {
    $task = taskFor($this->employee, TaskStatus::InProgress);

    ($this->transition)($task, TaskStatus::Submitted, $this->employee);

    $message = TaskMessage::where('task_id', $task->id)->latest('id')->first();

    expect($message->type)->toBe('status_change')
        ->and($message->body)->toContain('submitted this for review');
});

test('submitting notifies the approvers, not the person who submitted', function () {
    $task = taskFor($this->employee, TaskStatus::InProgress);

    ($this->transition)($task, TaskStatus::Submitted, $this->employee);

    Notification::assertSentTo($this->manager, TaskStatusChanged::class);
    Notification::assertSentTo($this->admin, TaskStatusChanged::class);
    Notification::assertNotSentTo($this->employee, TaskStatusChanged::class);
});

test('approving notifies the person who did the work', function () {
    $task = taskFor($this->employee, TaskStatus::Submitted);

    ($this->transition)($task, TaskStatus::Completed, $this->manager);

    Notification::assertSentTo($this->employee, TaskStatusChanged::class);
    Notification::assertNotSentTo($this->manager, TaskStatusChanged::class);
});

test('moving to the same status is a no-op', function () {
    $task = taskFor($this->employee, TaskStatus::InProgress);

    ($this->transition)($task, TaskStatus::InProgress, $this->employee);

    expect(TaskStatusLog::where('task_id', $task->id)->count())->toBe(0);
});

test('starting work stamps started_at once and never moves it', function () {
    $task = taskFor($this->employee, TaskStatus::Assigned);

    ($this->transition)($task, TaskStatus::InProgress, $this->employee);
    $first = $task->fresh()->started_at;

    ($this->transition)($task->fresh(), TaskStatus::OnHold, $this->employee, 'paused');
    ($this->transition)($task->fresh(), TaskStatus::InProgress, $this->employee);

    expect($task->fresh()->started_at->timestamp)->toBe($first->timestamp);
});

test('task references are sequential and unique', function () {
    $a = Task::factory()->create();
    $b = Task::factory()->create();

    expect($a->reference)->toBe('TSK-0001')
        ->and($b->reference)->toBe('TSK-0002');
});

test('the board never offers a column that needs a reason', function () {
    // Kanban drag-and-drop must not be able to bypass the reason requirement.
    foreach (TaskStatus::boardColumns() as $status) {
        expect($status->requiresReason())->toBeFalse();
    }
});
