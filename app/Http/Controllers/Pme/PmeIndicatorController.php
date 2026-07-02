<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeIndicatorMeasurementRequest;
use App\Http\Requests\Pme\SavePmeIndicatorRequest;
use App\Models\Pme\PmeIndicator;
use App\Services\Pme\PmeAccessService;
use App\Services\Pme\PmeIndicatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmeIndicatorController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
        private readonly PmeIndicatorService $indicatorService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeIndicator::query()
            ->with(['objective.plan:id,name,school_year', 'strategy:id,name', 'responsibleUser:id,name'])
            ->withCount('measurements')
            ->orderByDesc('id');
        $query->when($request->query('pme_objective_id'), fn ($builder, $objective) => $builder->where('pme_objective_id', $objective));
        $query->when($request->query('pme_strategy_id'), fn ($builder, $strategy) => $builder->where('pme_strategy_id', $strategy));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));

        return response()->json($query->paginate(15));
    }

    public function store(SavePmeIndicatorRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateIndicator($request->user()), 403);

        return response()->json([
            'message' => 'Indicador creado correctamente.',
            'data' => $this->indicatorService->store($request->validated(), $request->user()),
        ], 201);
    }

    public function update(SavePmeIndicatorRequest $request, PmeIndicator $indicator): JsonResponse
    {
        abort_unless($this->accessService->canCreateIndicator($request->user()) || $this->accessService->canMeasureIndicator($request->user()), 403);

        return response()->json([
            'message' => 'Indicador actualizado correctamente.',
            'data' => $this->indicatorService->update($indicator, $request->validated(), $request->user()),
        ]);
    }

    public function measurements(Request $request, PmeIndicator $indicator): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => $indicator->measurements()->with(['responsibleUser:id,name', 'evidences'])->get(),
        ]);
    }

    public function storeMeasurement(SavePmeIndicatorMeasurementRequest $request, PmeIndicator $indicator): JsonResponse
    {
        abort_unless($this->accessService->canMeasureIndicator($request->user()), 403);

        return response()->json([
            'message' => 'Medición del indicador registrada correctamente.',
            'data' => $this->indicatorService->storeMeasurement($indicator, $request->validated(), $request->user()),
        ], 201);
    }
}
