<?php

namespace App\DTO\LibroDigital;

final readonly class IdentityVerificationData
{
    public function __construct(
        public string $run,
        public string $otp,
        public string $timestamp,
        public string $correlationId,
        public string $payloadHash,
    ) {}
}
