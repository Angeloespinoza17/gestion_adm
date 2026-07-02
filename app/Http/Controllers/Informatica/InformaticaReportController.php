<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informatica\InformaticaReportRequest;
use App\Services\Informatica\InformaticaAccessService;
use App\Services\Informatica\InformaticaReportService;
use Illuminate\Http\JsonResponse;

class InformaticaReportController extends Controller
{
    public function __construct(
        private readonly InformaticaReportService $reportService,
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function __invoke(InformaticaReportRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()), 403);

        return response()->json($this->reportService->generate($request->validated()));
    }
}
