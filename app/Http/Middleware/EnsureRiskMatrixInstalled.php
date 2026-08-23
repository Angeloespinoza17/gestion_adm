<?php

namespace App\Http\Middleware;

use App\Services\RiskPrevention\RiskPreventionAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiskMatrixInstalled
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->accessService->isRiskMatrixInstalled()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'El submódulo Matriz IPER/MIPER aún no está instalado. Ejecute sus migraciones aditivas antes de usarlo.',
            'missing_tables' => $this->accessService->missingRiskMatrixTables(),
            'hint' => 'php artisan migrate --force --isolated',
        ], 503);
    }
}
