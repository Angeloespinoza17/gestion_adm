<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orientation\SaveOrientationRelatedPlanRequest;
use App\Models\Orientation\OrientationPlan;
use App\Models\Orientation\OrientationRelatedPlan;
use App\Services\Orientation\OrientationWorkspaceService;
use Illuminate\Http\JsonResponse;

class OrientationRelatedPlanController extends Controller
{
    public function __construct(
        private readonly OrientationWorkspaceService $workspace,
    ) {}

    public function store(SaveOrientationRelatedPlanRequest $request, OrientationPlan $plan): JsonResponse
    {
        $relatedPlan = $plan->relatedPlans()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Plan relacionado incorporado al índice anual.',
            'data' => $this->workspace->serializeRelatedPlan($relatedPlan),
        ], 201);
    }

    public function update(SaveOrientationRelatedPlanRequest $request, OrientationRelatedPlan $relatedPlan): JsonResponse
    {
        $relatedPlan->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Plan relacionado actualizado correctamente.',
            'data' => $this->workspace->serializeRelatedPlan($relatedPlan->loadCount('actions')),
        ]);
    }
}
