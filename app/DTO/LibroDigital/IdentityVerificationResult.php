<?php

namespace App\DTO\LibroDigital;

final readonly class IdentityVerificationResult
{
    public function __construct(
        public bool $verified,
        public string $status,
        public ?string $transactionId = null,
        public ?string $responseCode = null,
        public ?string $responseHash = null,
        public ?string $safeMessage = null,
    ) {}

    public static function unavailable(string $message = 'El verificador de identidad no esta disponible.'): self
    {
        return new self(false, 'unavailable', safeMessage: $message);
    }

    public static function rejected(?string $code = null): self
    {
        return new self(false, 'rejected', responseCode: $code, safeMessage: 'No fue posible verificar la identidad docente.');
    }
}
