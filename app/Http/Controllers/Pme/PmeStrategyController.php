<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeStrategyRequest;
use App\Models\Pme\PmeStrategy;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeStrategyController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeStrategy::query()
            ->with(['objective.plan:id,name,school_year', 'objective.dimension:id,name', 'responsibleUser:id,name'])
            ->withCount(['indicators', 'actions'])
            ->orderByDesc('id');
        $query->when($request->query('pme_objective_id'), fn ($builder, $objective) => $builder->where('pme_objective_id', $objective));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));

        return response()->json($query->paginate(15));
    }

    public function store(SavePmeStrategyRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateStrategy($request->user()), 403);

        $strategy = PmeStrategy::query()->create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Estrategia creada correctamente.',
            'data' => $strategy->fresh(['objective.plan', 'objective.dimension', 'responsibleUser']),
        ], 201);
    }

    public function update(SavePmeStrategyRequest $request, PmeStrategy $strategy): JsonResponse
    {
        abort_unless($this->accessService->canEditStrategy($request->user()), 403);

        $strategy->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Estrategia actualizada correctamente.',
            'data' => $strategy->fresh(['objective.plan', 'objective.dimension', 'responsibleUser']),
        ]);
    }
}
