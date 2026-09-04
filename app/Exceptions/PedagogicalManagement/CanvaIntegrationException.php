<?php

namespace App\Exceptions\PedagogicalManagement;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CanvaIntegrationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $failureCode = 'CANVA_INTEGRATION_FAILED',
        public readonly int $httpStatus = 502,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->failureCode,
            'errors' => ['canva' => [$this->getMessage()]],
        ], $this->httpStatus);
    }
}
