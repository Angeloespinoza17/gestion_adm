<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConvivenciaReportController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaReportService $reportService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewCourseReports($request->user()), 403);

        $filters = $request->validate($this->filterRules());

        return response()->json($this->reportService->buildCourseReport($request->user(), $filters));
    }

    public function exportData(Request $request): JsonResponse
    {
        abort_unless(
            $this->accessService->canViewCourseReports($request->user())
                && $this->accessService->canExportReports($request->user()),
            403,
        );

        $validated = $request->validate([
            'dataset' => ['required', 'string', Rule::in(ConvivenciaReportService::EXPORT_DATASETS)],
            ...$this->filterRules(),
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ]);

        $dataset = $validated['dataset'];
        $perPage = (int) ($validated['per_page'] ?? 200);
        $paginator = $this->reportService->paginateCourseReportExport(
            $request->user(),
            $validated,
            $dataset,
            $perPage,
        );

        return response()->json([
            'dataset' => $dataset,
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function filterRules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
