<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Status tone to Tailwind class.
 *
 * These have to be written out in full. Tailwind scans source files for literal
 * class names, so an interpolated `text-{$tone}-600` in a Blade template
 * produces a class that was never generated — the colour silently disappears
 * in production. Every class here is a complete string on purpose.
 */
final class Tone
{
    public static function text(string $tone): string
    {
        return match ($tone) {
            'ok' => 'text-ok-600',
            'warn' => 'text-warn-600',
            'danger' => 'text-danger-600',
            'accent' => 'text-accent-600',
            default => 'text-ink-600',
        };
    }

    public static function metric(string $tone): string
    {
        return match ($tone) {
            'ok' => 'text-ok-600',
            'warn' => 'text-warn-600',
            'danger' => 'text-danger-600',
            'accent' => 'text-accent-600',
            default => 'text-ink-950',
        };
    }

    public static function fill(string $tone): string
    {
        return match ($tone) {
            'ok' => 'bg-ok-600',
            'warn' => 'bg-warn-600',
            'danger' => 'bg-danger-600',
            'accent' => 'bg-accent-500',
            default => 'bg-ink-400',
        };
    }

    public static function tint(string $tone): string
    {
        return match ($tone) {
            'ok' => 'bg-ok-50',
            'warn' => 'bg-warn-50',
            'danger' => 'bg-danger-50',
            'accent' => 'bg-accent-50',
            default => 'bg-ink-100',
        };
    }
}
