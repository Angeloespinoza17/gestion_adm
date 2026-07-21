<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Infirmary\InfirmaryDashboardService;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InfirmaryDashboardController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
        private readonly InfirmaryMedicationStockService $stockService,
        private readonly InfirmaryDashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['diario', 'semanal', 'mensual', 'semestral', 'anual', 'personalizado'])],
            'from' => ['nullable', 'required_if:period,personalizado', 'date'],
            'to' => ['nullable', 'required_if:period,personalizado', 'date', 'after_or_equal:from'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'attention_category' => ['nullable', 'string', 'max:120'],
            'accident_location_type' => ['nullable', Rule::in(['colegio', 'trayecto'])],
        ]);

        $filters['period'] = $filters['period'] ?? 'mensual';
        $this->stockService->refreshDynamicStatuses();

        return response()
            ->json($this->dashboardService->build($filters))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
