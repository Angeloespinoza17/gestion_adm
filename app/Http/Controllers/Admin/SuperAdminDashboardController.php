<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SuperAdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminDashboardController extends Controller
{
    public function __construct(
        private readonly SuperAdminDashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'period_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
        ]);

        return response()->json(
            $this->dashboardService->build((int) ($payload['period_days'] ?? 30)),
        );
    }

    public function report(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'scope' => ['required', Rule::in(['general', 'module'])],
            'module_slug' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(['operativo', 'en_revision', 'requiere_atencion', 'sin_datos'])],
            'search' => ['nullable', 'string', 'max:120'],
            'period_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
        ]);

        $report = $this->dashboardService->buildReport(
            $payload['scope'],
            $payload['module_slug'] ?? null,
            (int) ($payload['period_days'] ?? 30),
            [
                'module_slug' => $payload['scope'] === 'general' ? ($payload['module_slug'] ?? null) : null,
                'status' => $payload['status'] ?? null,
                'search' => $payload['search'] ?? null,
            ],
        );

        abort_if($payload['scope'] === 'module' && $report === null, 404, 'Módulo no encontrado para el reporte.');

        return response()->json($report);
    }
}
