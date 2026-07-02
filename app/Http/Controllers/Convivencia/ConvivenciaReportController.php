<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Services\Convivencia\ConvivenciaAccessService;
use App\Services\Convivencia\ConvivenciaReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaReportController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
        private readonly ConvivenciaReportService $reportService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewCourseReports($request->user()), 403);

        return response()->json($this->reportService->buildCourseReport($request->user(), $request->all()));
    }
}
