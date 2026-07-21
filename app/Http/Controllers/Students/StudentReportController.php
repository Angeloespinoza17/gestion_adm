<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentReportDetailRequest;
use App\Http\Requests\Students\StudentReportMissingDataRequest;
use App\Http\Requests\Students\StudentReportRequest;
use App\Services\Infirmary\InfirmaryAccessService;
use App\Services\Students\StudentReportService;
use Illuminate\Http\JsonResponse;

class StudentReportController extends Controller
{
    public function __invoke(
        StudentReportRequest $request,
        StudentReportService $reportService,
        InfirmaryAccessService $infirmaryAccessService,
    ): JsonResponse {
        $includeInfirmary = $infirmaryAccessService->isInstalled()
            && $infirmaryAccessService->canViewReports($request->user());

        $filters = $request->validated();
        $refresh = (bool) ($filters['refresh'] ?? false);

        return response()->json($reportService->summary($filters, $includeInfirmary, $refresh));
    }

    public function details(
        StudentReportDetailRequest $request,
        StudentReportService $reportService,
    ): JsonResponse {
        return response()->json($reportService->details($request->validated()));
    }

    public function missingData(
        StudentReportMissingDataRequest $request,
        StudentReportService $reportService,
        InfirmaryAccessService $infirmaryAccessService,
    ): JsonResponse {
        $includeInfirmary = $infirmaryAccessService->isInstalled()
            && $infirmaryAccessService->canViewReports($request->user());

        return response()->json($reportService->missingData($request->validated(), $includeInfirmary));
    }
}
