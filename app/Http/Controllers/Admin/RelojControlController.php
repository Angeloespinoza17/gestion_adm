<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GeoVictoriaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateGeoVictoriaReportsRequest;
use App\Http\Requests\Admin\ListGeoVictoriaUsersRequest;
use App\Http\Requests\Admin\QueryGeoVictoriaAttendanceRequest;
use App\Services\Integrations\GeoVictoriaAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RelojControlController extends Controller
{
    public function __construct(
        private readonly GeoVictoriaAttendanceService $attendanceService,
    ) {}

    public function users(ListGeoVictoriaUsersRequest $request): JsonResponse
    {
        try {
            return response()->json($this->attendanceService->usersCatalog($request->validated()))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        } catch (GeoVictoriaException $exception) {
            return $this->providerError($exception);
        }
    }

    public function attendance(QueryGeoVictoriaAttendanceRequest $request): JsonResponse
    {
        try {
            return response()->json($this->attendanceService->attendance($request->validated()))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        } catch (GeoVictoriaException $exception) {
            return $this->providerError($exception);
        }
    }

    public function reports(GenerateGeoVictoriaReportsRequest $request): JsonResponse
    {
        try {
            return response()->json($this->attendanceService->reports($request->validated()))
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        } catch (GeoVictoriaException $exception) {
            return $this->providerError($exception);
        }
    }

    private function providerError(GeoVictoriaException $exception): JsonResponse
    {
        Log::warning('GeoVictoria no pudo completar una consulta de Reloj Control.', [
            'provider_status' => $exception->providerStatus,
            'http_status' => $exception->httpStatus,
        ]);

        return response()->json([
            'message' => $exception->getMessage(),
            'provider_status' => $exception->providerStatus,
        ], $exception->httpStatus);
    }
}
