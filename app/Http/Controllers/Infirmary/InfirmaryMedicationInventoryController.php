<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\SaveInfirmaryMedicationMovementRequest;
use App\Http\Requests\Infirmary\SaveInfirmaryMedicationRequest;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationMovement;
use App\Services\Infirmary\InfirmaryMedicationStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfirmaryMedicationInventoryController extends Controller
{
    public function __construct(
        private readonly InfirmaryMedicationStockService $stockService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InfirmaryMedication::class);
        $this->stockService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $critical = filter_var($request->query('critical'), FILTER_VALIDATE_BOOLEAN);
        $expiring = filter_var($request->query('expiring'), FILTER_VALIDATE_BOOLEAN);

        $items = InfirmaryMedication::query()
            ->with(['supplier:id,name,business_name', 'createdBy:id,name'])
            ->withCount(['movements', 'authorizations', 'administrations'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('commercial_name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%")
                        ->orWhere('batch', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($critical, fn ($query) => $query->whereIn('status', ['stock_bajo', 'agotado']))
            ->when($expiring, fn ($query) => $query->whereIn('status', ['proximo_a_vencer', 'vencido']))
            ->orderBy('expires_at')
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function store(SaveInfirmaryMedicationRequest $request): JsonResponse
    {
        $this->authorize('create', InfirmaryMedication::class);

        $payload = $request->validated();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $medication = InfirmaryMedication::query()->create($payload);
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Medicamento creado correctamente.',
            'data' => $medication->fresh(['supplier:id,name,business_name', 'createdBy:id,name']),
        ], 201);
    }

    public function show(InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('view', $medication);
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'data' => $medication->load([
                'supplier:id,name,business_name,rut',
                'createdBy:id,name',
                'updatedBy:id,name',
                'movements.performedBy:id,name',
                'authorizations.student:id,first_name,last_name,rut',
                'administrations.student:id,first_name,last_name,rut',
                'administrations.administeredBy:id,name',
            ]),
        ]);
    }

    public function update(SaveInfirmaryMedicationRequest $request, InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('update', $medication);

        $medication->update(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()?->id]
        ));
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Medicamento actualizado correctamente.',
            'data' => $medication->fresh(['supplier:id,name,business_name', 'updatedBy:id,name']),
        ]);
    }

    public function destroy(InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('delete', $medication);
        $medication->delete();

        return response()->json([
            'message' => 'Medicamento eliminado correctamente.',
        ]);
    }

    public function storeMovement(SaveInfirmaryMedicationMovementRequest $request, InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('update', $medication);

        $payload = $request->validated();
        $movedAt = !empty($payload['moved_at']) ? Carbon::parse($payload['moved_at']) : now();
        $user = $request->user();

        $movement = match ($payload['movement_type']) {
            'ingreso' => $this->stockService->increaseStock($medication, InfirmaryMedicationMovement::TYPE_INGRESO, (float) $payload['quantity'], $user, $payload['reason'] ?? null, $payload['notes'] ?? null, null, $movedAt),
            'donacion' => $this->stockService->increaseStock($medication, InfirmaryMedicationMovement::TYPE_DONACION, (float) $payload['quantity'], $user, $payload['reason'] ?? null, $payload['notes'] ?? null, null, $movedAt),
            'salida' => $this->stockService->decreaseStock($medication, InfirmaryMedicationMovement::TYPE_SALIDA, (float) $payload['quantity'], $user, $payload['reason'] ?? null, $payload['notes'] ?? null, null, $movedAt),
            'administracion' => $this->stockService->decreaseStock($medication, InfirmaryMedicationMovement::TYPE_ADMINISTRACION, (float) $payload['quantity'], $user, $payload['reason'] ?? null, $payload['notes'] ?? null, null, $movedAt),
            'perdida' => $this->stockService->decreaseStock($medication, InfirmaryMedicationMovement::TYPE_PERDIDA, (float) $payload['quantity'], $user, $payload['reason'] ?? null, $payload['notes'] ?? null, null, $movedAt),
            'vencimiento' => $this->stockService->decreaseStock($medication, InfirmaryMedicationMovement::TYPE_VENCIMIENTO, (float) $payload['quantity'], $user, $payload['reason'] ?? null, $payload['notes'] ?? null, null, $movedAt),
            default => $this->stockService->applyAdjustment(
                $medication,
                ($payload['adjustment_direction'] ?? 'increase') === 'decrease'
                    ? ((float) $payload['quantity']) * -1
                    : (float) $payload['quantity'],
                $user,
                $payload['reason'] ?? null,
                $payload['notes'] ?? null,
                null,
                $movedAt,
            ),
        };

        return response()->json([
            'message' => 'Movimiento registrado correctamente.',
            'data' => $movement->load('performedBy:id,name'),
            'medication' => $medication->fresh(),
        ], 201);
    }
}
