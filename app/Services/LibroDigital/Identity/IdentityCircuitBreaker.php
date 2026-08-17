<?php

namespace App\Services\LibroDigital\Identity;

use Illuminate\Support\Facades\Cache;

class IdentityCircuitBreaker
{
    private const KEY = 'lcd:identity:circuit';

    public function isOpen(): bool
    {
        $state = Cache::get(self::KEY, []);

        return (int) ($state['failures'] ?? 0) >= (int) config('libro_digital.identity_verifier.circuit_breaker_failures', 5)
            && (int) ($state['open_until'] ?? 0) > now()->timestamp;
    }

    public function success(): void
    {
        Cache::forget(self::KEY);
    }

    public function failure(): void
    {
        $state = Cache::get(self::KEY, []);
        $failures = ((int) ($state['failures'] ?? 0)) + 1;
        Cache::put(self::KEY, [
            'failures' => $failures,
            'open_until' => $failures >= (int) config('libro_digital.identity_verifier.circuit_breaker_failures', 5)
                ? now()->addSeconds((int) config('libro_digital.identity_verifier.circuit_breaker_seconds', 60))->timestamp
                : 0,
        ], now()->addMinutes(10));
    }
}
