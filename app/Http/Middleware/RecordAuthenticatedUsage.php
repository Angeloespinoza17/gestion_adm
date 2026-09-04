<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\UserUsageRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordAuthenticatedUsage
{
    public function __construct(
        private readonly UserUsageRecorder $recorder,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user instanceof User && $response->getStatusCode() < 500) {
            try {
                $this->recorder->recordActivity($user);
            } catch (Throwable) {
                // Las métricas nunca deben interrumpir una operación normal del sistema.
            }
        }

        return $response;
    }
}
