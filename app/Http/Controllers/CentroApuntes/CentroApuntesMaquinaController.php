<?php

namespace App\Http\Controllers\CentroApuntes;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroApuntes\SaveCentroApuntesMaquinaRequest;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CentroApuntesMaquinaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CentroApuntesMaquina::class);

        $search = trim((string) $request->query('search'));
        $items = CentroApuntesMaquina::query()
            ->with('responsibleUser:id,name,email')
            ->withCount('solicitudes')
            ->withSum('solicitudes', 'estimated_cost_total')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('internal_code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), fn ($builder) => $builder->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->query('status')))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function show(CentroApuntesMaquina $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        return response()->json([
            'data' => $machine->load([
                'responsibleUser:id,name,email',
                'solicitudes' => fn ($query) => $query->with('requester:id,name')->limit(12),
            ]),
        ]);
    }

    public function store(SaveCentroApuntesMaquinaRequest $request): JsonResponse
    {
        $this->authorize('create', CentroApuntesMaquina::class);

        $machine = CentroApuntesMaquina::query()->create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        ));

        return response()->json([
            'message' => 'Máquina registrada correctamente.',
            'data' => $machine->fresh('responsibleUser:id,name,email'),
        ], 201);
    }

    public function update(SaveCentroApuntesMaquinaRequest $request, CentroApuntesMaquina $machine): JsonResponse
    {
        $this->authorize('update', $machine);

        $machine->fill(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id]
        ))->save();

        return response()->json([
            'message' => 'Máquina actualizada correctamente.',
            'data' => $machine->fresh('responsibleUser:id,name,email'),
        ]);
    }

    public function destroy(CentroApuntesMaquina $machine): JsonResponse
    {
        $this->authorize('delete', $machine);

        if ($machine->solicitudes()->exists()) {
            throw ValidationException::withMessages([
                'machine' => 'No se puede eliminar una máquina con solicitudes asociadas.',
            ]);
        }

        $machine->delete();

        return response()->json([
            'message' => 'Máquina eliminada correctamente.',
        ]);
    }
}
