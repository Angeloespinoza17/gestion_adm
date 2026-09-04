<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Models\Orientation\OrientationPlan;
use App\Services\Orientation\OrientationStatisticsService;
use Illuminate\Http\JsonResponse;

class OrientationStatisticsController extends Controller
{
    public function __invoke(
        OrientationPlan $plan,
        OrientationStatisticsService $statistics
    ): JsonResponse {
        return response()
            ->json(['data' => $statistics->forPlan($plan)])
            ->header('Cache-Control', 'private, no-store');
    }
}
