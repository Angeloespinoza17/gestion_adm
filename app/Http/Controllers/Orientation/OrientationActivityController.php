<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orientation\SaveOrientationActivityRequest;
use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationActivity;
use App\Services\Orientation\OrientationWorkspaceService;
use App\Services\Orientation\OrientationActivityProgressService;
use Illuminate\Http\JsonResponse;

class OrientationActivityController extends Controller
{
    public function __construct(
        private readonly OrientationWorkspaceService $workspace,
        private readonly OrientationActivityProgressService $progress,
    ) {}

    public function store(SaveOrientationActivityRequest $request, OrientationAction $action): JsonResponse
    {
        $activity = $this->progress->create($action, $request->validated(), $request->user()?->id);
        $detail = $this->workspace->serializeActionDetail($this->workspace->loadAction($action->refresh()));

        return response()->json([
            'message' => "Actividad registrada. El avance de la acción quedó en {$detail['progress']}%.",
            'data' => $detail,
            'activity_id' => $activity->id,
        ], 201);
    }

    public function update(SaveOrientationActivityRequest $request, OrientationActivity $activity): JsonResponse
    {
        $activity = $this->progress->update($activity, $request->validated(), $request->user()?->id);
        $detail = $this->workspace->serializeActionDetail($this->workspace->loadAction($activity->action));

        return response()->json([
            'message' => "Actividad actualizada. El avance de la acción quedó en {$detail['progress']}%.",
            'data' => $detail,
        ]);
    }
}
