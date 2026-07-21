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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $inventoryType = trim((string) $request->query('inventory_type'));
        $sourceType = trim((string) $request->query('source_type'));
        $critical = filter_var($request->query('critical'), FILTER_VALIDATE_BOOLEAN);
        $expiring = filter_var($request->query('expiring'), FILTER_VALIDATE_BOOLEAN);

        $items = InfirmaryMedication::query()
            ->with([
                'supplier:id,name,business_name',
                'student:id,first_name,last_name,rut,guardian_name,guardian_relationship,guardian_phone',
                'createdBy:id,name',
            ])
            ->withCount(['movements', 'authorizations', 'administrations'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('commercial_name', 'like', "%{$search}%")
                        ->orWhere('active_ingredient', 'like', "%{$search}%")
                        ->orWhere('batch', 'like', "%{$search}%")
                        ->orWhere('received_from_guardian', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery->where(function ($studentSearch) use ($search) {
                                $studentSearch
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('rut', 'like', "%{$search}%");
                            });
                        });
                });
            })
            ->when($inventoryType !== '', fn ($query) => $query->where('inventory_type', $inventoryType))
            ->when($sourceType !== '', fn ($query) => $query->where('source_type', $sourceType))
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

        $payload = $this->normalizeInventoryPayload($request->validated());
        $initialStock = (float) ($payload['initial_stock'] ?? 0);
        unset($payload['initial_stock']);

        if ($initialStock > 0) {
            $payload['current_stock'] = 0;
        }

        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $medication = DB::transaction(function () use ($payload, $initialStock, $request) {
            $item = InfirmaryMedication::query()->create($payload);

            if ($initialStock > 0) {
                $isGuardianMedication = $item->inventory_type === InfirmaryMedication::INVENTORY_TYPE_MEDICATION
                    && $item->source_type === InfirmaryMedication::SOURCE_GUARDIAN;

                $this->stockService->increaseStock(
                    $item,
                    InfirmaryMedicationMovement::TYPE_INGRESO,
                    $initialStock,
                    $request->user(),
                    $isGuardianMedication ? 'Entrega inicial de apoderado' : 'Stock inicial',
                    $isGuardianMedication ? $item->received_from_guardian : null,
                    null,
                    $item->received_at ? Carbon::parse($item->received_at) : now(),
                );
            }

            return $item;
        });
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'message' => $this->itemLabel($medication).' creado correctamente.',
            'data' => $medication->fresh([
                'supplier:id,name,business_name',
                'student:id,first_name,last_name,rut,guardian_name,guardian_relationship,guardian_phone',
                'createdBy:id,name',
            ]),
        ], 201);
    }

    public function show(InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('view', $medication);
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'data' => $medication->load([
                'supplier:id,name,business_name,rut',
                'student:id,first_name,last_name,rut,guardian_name,guardian_relationship,guardian_phone,guardian_email',
                'createdBy:id,name',
                'updatedBy:id,name',
                'movements.performedBy:id,name',
                'authorizations.student:id,first_name,last_name,rut',
                'administrations.student:id,first_name,last_name,rut',
                'administrations.staff:id,full_name,rut,cargo_id',
                'administrations.administeredBy:id,name',
            ]),
        ]);
    }

    public function update(SaveInfirmaryMedicationRequest $request, InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('update', $medication);

        $payload = $this->normalizeInventoryPayload($request->validated());
        unset($payload['initial_stock']);

        $medication->update(array_merge(
            $payload,
            ['updated_by' => $request->user()?->id]
        ));
        $this->stockService->refreshDynamicStatuses();

        return response()->json([
            'message' => $this->itemLabel($medication).' actualizado correctamente.',
            'data' => $medication->fresh([
                'supplier:id,name,business_name',
                'student:id,first_name,last_name,rut,guardian_name,guardian_relationship,guardian_phone',
                'updatedBy:id,name',
            ]),
        ]);
    }

    public function destroy(InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('delete', $medication);
        $medication->delete();

        return response()->json([
            'message' => $this->itemLabel($medication).' eliminado correctamente.',
        ]);
    }

    public function storeMovement(SaveInfirmaryMedicationMovementRequest $request, InfirmaryMedication $medication): JsonResponse
    {
        $this->authorize('update', $medication);

        $payload = $request->validated();

        if (
            $medication->inventory_type === InfirmaryMedication::INVENTORY_TYPE_SUPPLY
            && $payload['movement_type'] === InfirmaryMedicationMovement::TYPE_ADMINISTRACION
        ) {
            throw ValidationException::withMessages([
                'movement_type' => 'Los insumos no admiten movimientos de administracion.',
            ]);
        }

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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeInventoryPayload(array $payload): array
    {
        $payload['inventory_type'] = $payload['inventory_type'] ?? InfirmaryMedication::INVENTORY_TYPE_MEDICATION;
        $payload['source_type'] = $payload['source_type'] ?? InfirmaryMedication::SOURCE_SCHOOL;

        if ($payload['inventory_type'] === InfirmaryMedication::INVENTORY_TYPE_SUPPLY) {
            $payload['source_type'] = InfirmaryMedication::SOURCE_SCHOOL;
            $payload['student_profile_id'] = null;
            $payload['received_from_guardian'] = null;
            $payload['received_at'] = null;
            $payload['active_ingredient'] = null;
            $payload['concentration'] = null;
            $payload['laboratory'] = null;
        } elseif ($payload['source_type'] !== InfirmaryMedication::SOURCE_GUARDIAN) {
            $payload['student_profile_id'] = null;
            $payload['received_from_guardian'] = null;
            $payload['received_at'] = null;
        }

        return $payload;
    }

    private function itemLabel(InfirmaryMedication $item): string
    {
        return $item->inventory_type === InfirmaryMedication::INVENTORY_TYPE_SUPPLY
            ? 'Insumo'
            : 'Medicamento';
    }
}
