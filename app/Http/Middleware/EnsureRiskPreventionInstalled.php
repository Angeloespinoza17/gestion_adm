<?php

namespace App\Http\Middleware;

use App\Services\RiskPrevention\RiskPreventionAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiskPreventionInstalled
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->accessService->isInstalled()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'El módulo de Prevención de Riesgos aún no está instalado en la base de datos. Ejecuta las migraciones del módulo antes de usarlo.',
            'missing_tables' => $this->accessService->missingTables(),
            'hint' => 'php artisan migrate',
        ], 503);
    }
}
