<?php

namespace App\Exceptions\PedagogicalManagement;

use RuntimeException;

class ClassPresentationGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $failureCode,
        public readonly int $httpStatus = 500,
    ) {
        parent::__construct($message);
    }
}
