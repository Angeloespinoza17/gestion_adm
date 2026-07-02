<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\PmeReportRequest;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmeReportService;
use Illuminate\Http\JsonResponse;

class PmeReportController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmeReportService $reportService,
    ) {
    }

    public function __invoke(PmeReportRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canViewReports($request->user()), 403);

        if (($request->validated('format') ?? 'pantalla') !== 'pantalla') {
            abort_unless($this->accessService->canExportReports($request->user()), 403);
        }

        return response()->json($this->reportService->generate($request->validated(), $request->user()));
    }
}
