<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConvivenciaDashboardController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaDashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewDashboard($request->user()), 403);

        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:80'],
            'criticality_label' => ['nullable', 'string', 'max:160'],
            'classification_label' => ['nullable', 'string', 'max:160'],
        ]);

        return response()->json($this->dashboardService->build($request->user(), $filters));
    }
}
