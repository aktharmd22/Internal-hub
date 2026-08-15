<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * open → assigned → in_progress → on_hold → submitted → completed
 *                        ↓            ↓
 *                    (blocked)    reopened → in_progress
 *
 * Employees can reach `submitted` but never `completed`. Without that gate,
 * "completed" stops meaning anything within two months.
 */
enum TaskStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Blocked = 'blocked';
    case Submitted = 'submitted';
    case Reopened = 'reopened';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::OnHold => 'On hold',
            self::Blocked => 'Blocked',
            self::Submitted => 'Awaiting review',
            self::Reopened => 'Reopened',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open, self::Assigned => 'neutral',
            self::InProgress => 'accent',
            self::OnHold => 'warn',
            self::Blocked, self::Reopened => 'danger',
            self::Submitted => 'warn',
            self::Completed => 'ok',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Statuses where the work is finished and the clock has stopped.
     *
     * @return list<self>
     */
    public static function closed(): array
    {
        return [self::Completed, self::Cancelled];
    }

    /** @return list<string> */
    public static function closedValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::closed());
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_values(array_diff(
            array_map(fn (self $case) => $case->value, self::cases()),
            self::closedValues(),
        ));
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }

    /**
     * Which statuses this one may move to. Enforced by TaskStatusTransition,
     * never by a controller assigning `status` directly.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::Assigned, self::InProgress, self::Cancelled],
            self::Assigned => [self::InProgress, self::OnHold, self::Blocked, self::Open, self::Cancelled],
            self::InProgress => [self::Submitted, self::OnHold, self::Blocked, self::Assigned, self::Cancelled],
            self::OnHold => [self::InProgress, self::Assigned, self::Blocked, self::Cancelled],
            self::Blocked => [self::InProgress, self::OnHold, self::Assigned, self::Cancelled],
            self::Submitted => [self::Completed, self::Reopened, self::InProgress],
            self::Reopened => [self::InProgress, self::Assigned, self::OnHold, self::Cancelled],
            self::Completed => [self::Reopened],
            self::Cancelled => [self::Open],
        };
    }

    /**
     * Transitions only an approver may make. This is the review gate.
     *
     * @return list<self>
     */
    public static function approverOnly(): array
    {
        return [self::Completed, self::Reopened];
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::OnHold, self::Blocked, self::Reopened, self::Cancelled], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Columns shown on the Kanban board, in order.
     *
     * @return list<self>
     */
    public static function boardColumns(): array
    {
        return [self::Open, self::Assigned, self::InProgress, self::Submitted, self::Completed];
    }
}
