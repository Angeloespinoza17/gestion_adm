<?php

namespace App\Contracts\LibroDigital;

use App\DTO\LibroDigital\IdentityVerificationData;
use App\DTO\LibroDigital\IdentityVerificationResult;

interface TeacherIdentityVerifier
{
    public function verify(IdentityVerificationData $data): IdentityVerificationResult;

    public function provider(): string;
}
