<?php

namespace App\Exceptions;

use RuntimeException;

class GeoVictoriaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 502,
        public readonly ?int $providerStatus = null,
    ) {
        parent::__construct($message);
    }
}
