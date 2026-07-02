<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\SavePanolMovimientoRequest;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use App\Services\CentroApuntes\PanolStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanolMovimientoController extends Controller
{
    public function __construct(
        private readonly PanolStockService $stockService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PanolMovimiento::class);

        $items = PanolMovimiento::query()
            ->with(['insumo:id,name,category,unit_of_measure', 'responsibleUser:id,name', 'requestedByUser:id,name', 'department:id,name'])
            ->when($request->filled('insumo_id'), fn ($builder) => $builder->where('insumo_id', $request->query('insumo_id')))
            ->when($request->filled('movement_type'), fn ($builder) => $builder->where('movement_type', $request->query('movement_type')))
            ->when($request->filled('department_id'), fn ($builder) => $builder->where('department_id', $request->query('department_id')))
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = trim((string) $request->query('search'));
                $builder->whereHas('insumo', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            })
            ->latest('moved_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($items);
    }

    public function store(SavePanolMovimientoRequest $request): JsonResponse
    {
        $this->authorize('create', PanolMovimiento::class);

        $payload = $request->validated();
        $insumo = PanolInsumo::query()->findOrFail($payload['insumo_id']);
        $movement = $this->stockService->registerMovement($insumo, $payload, $request->user());

        return response()->json([
            'message' => 'Movimiento de stock registrado correctamente.',
            'data' => $movement->fresh(['insumo:id,name,current_stock,status', 'responsibleUser:id,name', 'requestedByUser:id,name', 'department:id,name']),
        ], 201);
    }
}
