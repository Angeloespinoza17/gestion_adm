<?php

namespace App\Contracts\LibroDigital;

use App\DTO\LibroDigital\IdentityVerificationData;
use App\DTO\LibroDigital\IdentityVerificationResult;

interface BulkTeacherIdentityVerifier
{
    /**
     * @param  list<IdentityVerificationData>  $items
     * @return array<string, IdentityVerificationResult> keyed by normalized RUN
     */
    public function verifyMany(array $items): array;

    public function provider(): string;
}
