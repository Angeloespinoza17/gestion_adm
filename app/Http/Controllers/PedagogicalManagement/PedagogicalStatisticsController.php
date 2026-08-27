<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\PedagogicalStatisticsRequest;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAccessService;
use App\Services\PedagogicalManagement\PedagogicalStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedagogicalStatisticsController extends Controller
{
    public function index(
        PedagogicalStatisticsRequest $request,
        PedagogicalInstrumentAccessService $access,
        PedagogicalStatisticsService $statistics,
    ): JsonResponse {
        $school = $access->resolveSchool($request);

        return response()->json([
            'data' => $statistics->dashboard($request->user(), $school, collect($request->validated())->except('school_id')->all()),
        ]);
    }

    public function instrument(
        Request $request,
        PedagogicalInstrument $instrument,
        PedagogicalStatisticsService $statistics,
    ): JsonResponse {
        abort_unless($request->user()?->hasPermission('pedagogical-instruments.statistics'), 403);

        return response()->json([
            'data' => $statistics->instrumentTrajectory($request->user(), $instrument),
        ]);
    }
}
