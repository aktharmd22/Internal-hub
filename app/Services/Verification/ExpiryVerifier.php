<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Asset;

interface ExpiryVerifier
{
    public function supports(Asset $asset): bool;

    public function verify(Asset $asset): VerificationResult;
}
