<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeMilestoneRequest;
use App\Models\Pme\PmeMilestone;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeMilestoneController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeMilestone::query()
            ->with(['action:id,name', 'responsibleUser:id,name'])
            ->orderBy('planned_date');
        $query->when($request->query('pme_action_id'), fn ($builder, $actionId) => $builder->where('pme_action_id', $actionId));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));

        return response()->json($query->paginate(20));
    }

    public function store(SavePmeMilestoneRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateMilestone($request->user()), 403);

        $milestone = PmeMilestone::query()->create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Hito creado correctamente.',
            'data' => $milestone->fresh(['action', 'responsibleUser']),
        ], 201);
    }

    public function update(SavePmeMilestoneRequest $request, PmeMilestone $milestone): JsonResponse
    {
        abort_unless($this->accessService->canCreateMilestone($request->user()), 403);

        $milestone->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Hito actualizado correctamente.',
            'data' => $milestone->fresh(['action', 'responsibleUser']),
        ]);
    }

    public function destroy(Request $request, PmeMilestone $milestone): JsonResponse
    {
        abort_unless($this->accessService->canCreateMilestone($request->user()), 403);

        $milestone->delete();

        return response()->json(['message' => 'Hito eliminado correctamente.']);
    }
}
