<?php

namespace App\Services\LibroDigital\Identity;

use App\Contracts\LibroDigital\TeacherIdentityVerifier;
use App\DTO\LibroDigital\IdentityVerificationData;
use App\DTO\LibroDigital\IdentityVerificationResult;

class FakeIdentityVerifier implements TeacherIdentityVerifier
{
    public function verify(IdentityVerificationData $data): IdentityVerificationResult
    {
        if (! app()->environment('testing')) {
            return IdentityVerificationResult::unavailable();
        }

        if (! hash_equals('123456', $data->otp)) {
            return IdentityVerificationResult::rejected('FAKE_INVALID_OTP');
        }

        return new IdentityVerificationResult(
            verified: true,
            status: 'verified',
            transactionId: 'fake-'.substr(hash('sha256', $data->correlationId), 0, 20),
            responseCode: 'FAKE_OK',
            responseHash: hash('sha256', $data->payloadHash.'|verified'),
            safeMessage: 'Identidad verificada en entorno de pruebas.',
        );
    }

    public function provider(): string
    {
        return 'fake';
    }
}
