<?php

namespace App\Exceptions\PedagogicalManagement;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedagogicalInstrumentException extends Exception
{
    /** @param array<string,mixed> $details */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details,
        ], $this->status);
    }
}
