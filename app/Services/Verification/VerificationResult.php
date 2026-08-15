<?php

declare(strict_types=1);

namespace App\Services\Verification;

use Illuminate\Support\Carbon;

class VerificationResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?Carbon $expiresAt = null,
        public readonly ?string $error = null,
    ) {}

    public static function found(Carbon $expiresAt): self
    {
        return new self(true, $expiresAt->startOfDay());
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
