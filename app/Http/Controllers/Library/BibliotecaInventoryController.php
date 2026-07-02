<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\SaveBibliotecaEjemplarRequest;
use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaObra;
use App\Services\Library\BibliotecaInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BibliotecaInventoryController extends Controller
{
    public function __construct(
        private readonly BibliotecaInventoryService $inventoryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BibliotecaEjemplar::class);

        $search = trim((string) $request->query('search'));
        $query = BibliotecaEjemplar::query()
            ->with(['obra:id,title,internal_code,material_type,category', 'registeredBy:id,name'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('obra', function ($obraQuery) use ($search) {
                            $obraQuery
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('main_author', 'like', "%{$search}%")
                                ->orWhere('isbn', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('biblioteca_obra_id'), fn ($builder) => $builder->where('biblioteca_obra_id', $request->query('biblioteca_obra_id')))
            ->when($request->filled('physical_state'), fn ($builder) => $builder->where('physical_state', $request->query('physical_state')))
            ->when($request->filled('availability_status'), fn ($builder) => $builder->where('availability_status', $request->query('availability_status')))
            ->when($request->filled('physical_location'), fn ($builder) => $builder->where('physical_location', 'like', '%' . $request->query('physical_location') . '%'))
            ->when($request->boolean('only_active', true), fn ($builder) => $builder->where('is_active', true));

        $currentYear = now()->year;

        return response()->json([
            'items' => $query->orderBy('code')->paginate((int) $request->query('per_page', 15)),
            'summary' => [
                'active_total' => BibliotecaEjemplar::query()->where('is_active', true)->count(),
                'checked_this_year' => BibliotecaEjemplar::query()->whereYear('last_inventory_checked_at', $currentYear)->count(),
                'pending_check' => BibliotecaEjemplar::query()->where(function ($builder) use ($currentYear) {
                    $builder->whereNull('last_inventory_checked_at')->orWhereYear('last_inventory_checked_at', '<', $currentYear);
                })->count(),
                'damaged_or_lost' => BibliotecaEjemplar::query()->whereIn('availability_status', ['danado', 'perdido'])->count(),
            ],
        ]);
    }

    public function show(BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('view', $ejemplar);

        return response()->json([
            'data' => $ejemplar->load([
                'obra',
                'movimientos.responsible:id,name',
                'prestamos.obra:id,title',
                'prestamos.deliveredBy:id,name',
                'prestamos.receivedBy:id,name',
                'reservas.obra:id,title',
            ]),
        ]);
    }

    public function store(SaveBibliotecaEjemplarRequest $request): JsonResponse
    {
        $this->authorize('update', BibliotecaEjemplar::class);

        $ejemplar = DB::transaction(function () use ($request) {
            $ejemplar = BibliotecaEjemplar::query()->create(array_merge(
                $request->validated(),
                [
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]
            ));

            $this->inventoryService->moveEjemplar(
                $ejemplar->fresh(['obra']),
                $request->user(),
                'alta',
                [],
                'Alta de ejemplar.',
                ['movement_date' => now()]
            );

            return $ejemplar->fresh(['obra']);
        });

        return response()->json([
            'message' => 'Ejemplar registrado correctamente.',
            'data' => $ejemplar,
        ], 201);
    }

    public function update(SaveBibliotecaEjemplarRequest $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $changes = $request->validated();
        $movementType = 'ajuste';

        if (($changes['physical_location'] ?? $ejemplar->physical_location) !== $ejemplar->physical_location) {
            $movementType = 'cambio_ubicacion';
        } elseif (($changes['physical_state'] ?? $ejemplar->physical_state) !== $ejemplar->physical_state
            || ($changes['availability_status'] ?? $ejemplar->availability_status) !== $ejemplar->availability_status) {
            $movementType = 'cambio_estado';
        }

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            $movementType,
            array_merge($changes, ['updated_by' => $request->user()->id]),
            'Actualización de ejemplar.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Ejemplar actualizado correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function audit(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate([
            'physical_count_status' => ['required', 'string', 'max:80'],
            'physical_location' => ['nullable', 'string', 'max:120'],
            'physical_state' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'inventario_fisico',
            [
                'physical_location' => $payload['physical_location'] ?? $ejemplar->physical_location,
                'physical_state' => $payload['physical_state'] ?? $ejemplar->physical_state,
                'last_inventory_checked_at' => now()->format('Y-m-d'),
            ],
            $payload['notes'] ?? 'Inventario físico anual.',
            [
                'physical_count_status' => $payload['physical_count_status'],
                'movement_date' => now(),
            ]
        );

        return response()->json([
            'message' => 'Inventario físico registrado correctamente.',
            'data' => $ejemplar->fresh(['obra', 'movimientos']),
        ]);
    }

    public function markDamage(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'danio',
            [
                'physical_state' => 'danado',
                'availability_status' => 'danado',
                'damaged_at' => now(),
            ],
            $payload['notes'] ?? 'Daño registrado.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Daño registrado correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function markLoss(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'perdida',
            [
                'physical_state' => 'perdido',
                'availability_status' => 'perdido',
                'lost_at' => now(),
                'is_active' => false,
            ],
            $payload['notes'] ?? 'Pérdida registrada.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Pérdida registrada correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }

    public function deactivate(Request $request, BibliotecaEjemplar $ejemplar): JsonResponse
    {
        $this->authorize('update', $ejemplar);

        $payload = $request->validate(['notes' => ['nullable', 'string']]);

        $this->inventoryService->moveEjemplar(
            $ejemplar->fresh(['obra']),
            $request->user(),
            'baja',
            [
                'physical_state' => 'dado_de_baja',
                'availability_status' => 'dado_de_baja',
                'withdrawn_at' => now(),
                'is_active' => false,
            ],
            $payload['notes'] ?? 'Baja de ejemplar.',
            ['movement_date' => now()]
        );

        return response()->json([
            'message' => 'Ejemplar dado de baja correctamente.',
            'data' => $ejemplar->fresh(['obra']),
        ]);
    }
}
