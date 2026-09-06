<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\SaveConvivenciaPlanActivityRequest;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanActivity;
use App\Services\Convivencia\ConvivenciaPlanProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvivenciaPlanActivityController extends Controller
{
    public function __construct(private readonly ConvivenciaPlanProgressService $progress) {}

    public function store(SaveConvivenciaPlanActivityRequest $request, ConvivenciaPlanAction $action): JsonResponse
    {
        $this->authorize('update', $action->plan);
        $activity = $this->progress->create($action, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Actividad programada correctamente.',
            'data' => $activity,
        ], 201);
    }

    public function update(SaveConvivenciaPlanActivityRequest $request, ConvivenciaPlanActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->action->plan);

        return response()->json([
            'message' => 'Actividad actualizada correctamente.',
            'data' => $this->progress->update($activity, $request->validated(), $request->user()),
        ]);
    }

    public function destroy(Request $request, ConvivenciaPlanActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->action->plan);
        $payload = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $this->progress->delete($activity, $request->user(), (int) $payload['revision']);

        return response()->json(['message' => 'Actividad archivada correctamente.']);
    }
}
