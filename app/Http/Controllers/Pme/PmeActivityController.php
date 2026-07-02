<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeActivityRequest;
use App\Models\Pme\PmeActivity;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeActivityController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function store(SavePmeActivityRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        $activity = PmeActivity::query()->create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Actividad creada correctamente.',
            'data' => $activity->fresh(['action', 'responsibleUser']),
        ], 201);
    }

    public function update(SavePmeActivityRequest $request, PmeActivity $activity): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        $activity->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Actividad actualizada correctamente.',
            'data' => $activity->fresh(['action', 'responsibleUser']),
        ]);
    }

    public function destroy(Request $request, PmeActivity $activity): JsonResponse
    {
        abort_unless($this->accessService->canEditAction($request->user()), 403);

        $activity->delete();

        return response()->json(['message' => 'Actividad eliminada correctamente.']);
    }
}
