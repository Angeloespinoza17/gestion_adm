<?php

namespace App\Http\Middleware;

use App\Services\Convivencia\ConvivenciaAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConvivenciaInstalled
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->accessService->isInstalled()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'El módulo de Convivencia aún no está instalado en la base de datos. Ejecuta las migraciones del módulo antes de usarlo.',
            'missing_tables' => $this->accessService->missingTables(),
            'hint' => 'php artisan migrate',
        ], 503);
    }
}
