<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeGoalMeasurementRequest;
use App\Http\Requests\Pme\SavePmeObjectiveRequest;
use App\Models\Pme\PmeObjective;
use App\Models\Pme\PmeStrategicGoalMeasurement;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeObjectiveController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeObjective::query()
            ->with(['plan:id,name,school_year', 'dimension:id,name', 'responsibleUser:id,name'])
            ->withCount(['strategies', 'indicators', 'actions'])
            ->orderByDesc('id');
        $query->when($request->query('pme_plan_id'), fn ($builder, $plan) => $builder->where('pme_plan_id', $plan));
        $query->when($request->query('pme_dimension_id'), fn ($builder, $dimension) => $builder->where('pme_dimension_id', $dimension));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));

        return response()->json($query->paginate(15));
    }

    public function store(SavePmeObjectiveRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateObjective($request->user()), 403);

        $objective = PmeObjective::query()->create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Objetivo estratégico creado correctamente.',
            'data' => $objective->fresh(['plan', 'dimension', 'responsibleUser']),
        ], 201);
    }

    public function update(SavePmeObjectiveRequest $request, PmeObjective $objective): JsonResponse
    {
        abort_unless($this->accessService->canEditObjective($request->user()), 403);

        $objective->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Objetivo estratégico actualizado correctamente.',
            'data' => $objective->fresh(['plan', 'dimension', 'responsibleUser']),
        ]);
    }

    public function measurements(Request $request, PmeObjective $objective): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $objective->strategicGoalMeasurements()->with(['responsibleUser:id,name', 'evidences'])->get(),
        ]);
    }

    public function storeMeasurement(SavePmeGoalMeasurementRequest $request, PmeObjective $objective): JsonResponse
    {
        abort_unless($this->accessService->canEditObjective($request->user()), 403);

        $measurement = PmeStrategicGoalMeasurement::query()->create(array_merge($request->validated(), [
            'pme_objective_id' => $objective->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Medición de meta estratégica registrada correctamente.',
            'data' => $measurement->fresh(['objective', 'responsibleUser']),
        ], 201);
    }

    public function updateMeasurement(SavePmeGoalMeasurementRequest $request, PmeStrategicGoalMeasurement $measurement): JsonResponse
    {
        abort_unless($this->accessService->canEditObjective($request->user()), 403);

        $measurement->update(array_merge($request->validated(), [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Medición de meta estratégica actualizada correctamente.',
            'data' => $measurement->fresh(['objective', 'responsibleUser']),
        ]);
    }
}
