<?php

namespace App\Exceptions\LibroDigital;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class LibroDigitalException extends RuntimeException
{
    /** @param array<int, array<string, mixed>> $details */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details,
            'correlation_id' => $request->attributes->get('lcd_correlation_id'),
        ], $this->status);
    }
}
