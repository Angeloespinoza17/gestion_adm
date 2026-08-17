<?php

namespace App\Services\LibroDigital\Identity;

use App\Contracts\LibroDigital\TeacherIdentityVerifier;
use App\DTO\LibroDigital\IdentityVerificationData;
use App\DTO\LibroDigital\IdentityVerificationResult;

class DisabledIdentityVerifier implements TeacherIdentityVerifier
{
    public function verify(IdentityVerificationData $data): IdentityVerificationResult
    {
        return IdentityVerificationResult::unavailable('Firma pendiente por indisponibilidad del verificador.');
    }

    public function provider(): string
    {
        return 'disabled';
    }
}
