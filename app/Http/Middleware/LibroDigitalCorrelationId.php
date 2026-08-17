<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LibroDigitalCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Correlation-ID', '');
        $correlationId = preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $provided)
            ? $provided
            : (string) Str::ulid();

        $request->attributes->set('lcd_correlation_id', $correlationId);
        Log::withContext([
            'correlation_id' => $correlationId,
            'module' => 'libro_digital',
        ]);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
