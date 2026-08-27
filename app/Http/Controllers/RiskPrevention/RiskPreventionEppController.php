<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\BulkStoreRiskPreventionEppItemsRequest;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionEppDeliveryRecordRequest;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionEppDeliveryRequest;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionEppItemRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionEppDeliveryRecord;
use App\Models\RiskPrevention\RiskPreventionEppItem;
use App\Models\Staff;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiskPreventionEppController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {}

    public function itemsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionEppItem::class);

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('epp_type'));
        $lowStock = filter_var($request->query('low_stock'), FILTER_VALIDATE_BOOLEAN);

        $items = RiskPreventionEppItem::query()
            ->with('inventoryItem:id,code,name,item_type,stock_quantity,minimum_stock,unit_of_measure,active')
            ->withCount('deliveries')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('epp_type', 'like', "%{$search}%")
                        ->orWhereHas('inventoryItem', fn ($query) => $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($type !== '', fn ($query) => $query->where('epp_type', $type))
            ->when($lowStock, fn ($query) => $query->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query->whereNull('inventory_item_id')->whereColumn('stock', '<=', 'minimum_stock');
                    })
                    ->orWhereHas('inventoryItem', fn ($query) => $query
                        ->whereColumn('inventory_items.stock_quantity', '<=', 'inventory_items.minimum_stock'));
            }))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($items);
    }

    public function storeItem(SaveRiskPreventionEppItemRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionEppItem::class);

        $item = RiskPreventionEppItem::query()->create(array_merge(
            $this->normalizedItemPayload($request->validated()),
            [
                'active' => $request->boolean('active', true),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        return response()->json([
            'message' => 'Elemento EPP creado correctamente.',
            'data' => $item->fresh()->load('inventoryItem'),
        ], 201);
    }

    public function updateItem(SaveRiskPreventionEppItemRequest $request, RiskPreventionEppItem $eppItem): JsonResponse
    {
        $this->authorize('update', $eppItem);

        $eppItem->update(array_merge(
            $this->normalizedItemPayload($request->validated()),
            [
                'active' => $request->boolean('active', true),
                'updated_by' => $request->user()->id,
            ],
        ));

        return response()->json([
            'message' => 'Elemento EPP actualizado correctamente.',
            'data' => $eppItem->fresh()->load('inventoryItem'),
        ]);
    }

    public function bulkStoreItems(BulkStoreRiskPreventionEppItemsRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionEppItem::class);

        $result = DB::transaction(function () use ($request) {
            $created = 0;
            $updated = 0;

            foreach ($request->validated('items') as $payload) {
                $name = trim((string) $payload['name']);
                $type = trim((string) $payload['epp_type']);
                $item = RiskPreventionEppItem::query()
                    ->where('name', $name)
                    ->where('epp_type', $type)
                    ->first();

                $attributes = [
                    'name' => $name,
                    'epp_type' => $type,
                    'stock' => (int) $payload['stock'],
                    'minimum_stock' => (int) $payload['minimum_stock'],
                    'unit' => trim((string) $payload['unit']),
                    'description' => filled($payload['description'] ?? null)
                        ? trim((string) $payload['description'])
                        : null,
                    'active' => (bool) ($payload['active'] ?? true),
                    'updated_by' => $request->user()->id,
                ];

                if ($item) {
                    $item->update($attributes);
                    $updated++;

                    continue;
                }

                RiskPreventionEppItem::query()->create(array_merge($attributes, [
                    'created_by' => $request->user()->id,
                ]));
                $created++;
            }

            return compact('created', 'updated');
        });

        return response()->json([
            'message' => 'Listado EPP cargado correctamente.',
            'data' => $result,
        ], 201);
    }

    public function destroyItem(RiskPreventionEppItem $eppItem): JsonResponse
    {
        $this->authorize('delete', $eppItem);

        if ($eppItem->deliveries()->exists()) {
            throw ValidationException::withMessages([
                'epp_item' => 'No puedes eliminar un EPP con historial de entregas. Déjalo inactivo para conservar las actas.',
            ]);
        }

        $eppItem->delete();

        return response()->json([
            'message' => 'Elemento EPP eliminado correctamente.',
        ]);
    }

    public function deliveriesIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionEppDelivery::class);
        $this->accessService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $itemId = $request->query('epp_item_id');

        $deliveries = RiskPreventionEppDelivery::query()
            ->with([
                'item:id,inventory_item_id,name,epp_type,stock,minimum_stock,unit',
                'item.inventoryItem:id,code,name,stock_quantity,minimum_stock,unit_of_measure',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('observations', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when(filled($itemId), fn ($query) => $query->where('epp_item_id', $itemId))
            ->orderByDesc('delivered_at')
            ->paginate((int) $request->query('per_page', 12));

        return response()->json($deliveries);
    }

    public function storeDelivery(SaveRiskPreventionEppDeliveryRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionEppDelivery::class);

        $delivery = RiskPreventionEppDelivery::query()->create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        ));

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Entrega de EPP registrada correctamente.',
            'data' => $delivery->fresh()->load(['item', 'item.inventoryItem']),
        ], 201);
    }

    public function updateDelivery(SaveRiskPreventionEppDeliveryRequest $request, RiskPreventionEppDelivery $eppDelivery): JsonResponse
    {
        $this->authorize('update', $eppDelivery);

        if ($eppDelivery->delivery_record_id) {
            throw ValidationException::withMessages([
                'delivery' => 'Las líneas vinculadas a un acta no se editan individualmente.',
            ]);
        }

        $eppDelivery->update(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id],
        ));

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Entrega de EPP actualizada correctamente.',
            'data' => $eppDelivery->fresh()->load(['item', 'item.inventoryItem']),
        ]);
    }

    public function destroyDelivery(RiskPreventionEppDelivery $eppDelivery): JsonResponse
    {
        $this->authorize('delete', $eppDelivery);

        if ($eppDelivery->delivery_record_id) {
            throw ValidationException::withMessages([
                'delivery' => 'Las líneas vinculadas a un acta no se eliminan individualmente.',
            ]);
        }

        $eppDelivery->delete();

        return response()->json([
            'message' => 'Entrega de EPP eliminada correctamente.',
        ]);
    }

    public function deliveryRecordsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionEppDelivery::class);

        $search = trim((string) $request->query('search'));
        $conformity = $request->query('received_conformity');
        $from = $request->query('from');
        $to = $request->query('to');

        $records = RiskPreventionEppDeliveryRecord::query()
            ->with([
                'deliveries.item:id,inventory_item_id,name,epp_type,stock,minimum_stock,unit',
                'deliveries.item.inventoryItem:id,code,name,stock_quantity,minimum_stock,unit_of_measure',
                'staff:id,full_name,rut,cargo_id',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('folio', 'like', "%{$search}%")
                        ->orWhere('employee_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('employee_rut_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('deliveries', function ($query) use ($search) {
                            $query
                                ->where('epp_name_snapshot', 'like', "%{$search}%")
                                ->orWhereHas('item', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->when($conformity !== null && $conformity !== '', function ($query) use ($conformity) {
                $query->where('received_conformity', filter_var($conformity, FILTER_VALIDATE_BOOLEAN));
            })
            ->when(filled($from), fn ($query) => $query->whereDate('delivered_at', '>=', $from))
            ->when(filled($to), fn ($query) => $query->whereDate('delivered_at', '<=', $to))
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 12), 1), 100));

        return response()->json(array_merge($records->toArray(), [
            'summary' => [
                'total_records' => RiskPreventionEppDeliveryRecord::query()->count(),
                'pending_conformity' => RiskPreventionEppDeliveryRecord::query()
                    ->where('received_conformity', false)
                    ->count(),
                'month_records' => RiskPreventionEppDeliveryRecord::query()
                    ->whereBetween('delivered_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->count(),
                'today_records' => RiskPreventionEppDeliveryRecord::query()
                    ->whereDate('delivered_at', today())
                    ->count(),
                'delivered_units' => RiskPreventionEppDelivery::query()
                    ->whereNotNull('delivery_record_id')
                    ->sum('quantity'),
            ],
        ]));
    }

    public function storeDeliveryRecord(SaveRiskPreventionEppDeliveryRecordRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionEppDelivery::class);

        $record = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $staff = filled($validated['staff_id'] ?? null)
                ? Staff::query()->with('cargo:id,name')->findOrFail($validated['staff_id'])
                : null;

            $employeeName = $staff?->full_name ?: trim((string) $validated['employee_name']);
            $employeeRut = $staff?->rut ?: ($validated['employee_rut'] ?? null);
            $employeePosition = $staff?->cargo?->name ?: ($validated['employee_position'] ?? null);
            $receivedConformity = (bool) $validated['received_conformity'];

            $record = RiskPreventionEppDeliveryRecord::query()->create([
                'form_code' => RiskPreventionEppDeliveryRecord::FORM_CODE,
                'form_revision' => RiskPreventionEppDeliveryRecord::FORM_REVISION,
                'staff_id' => $staff?->id,
                'employee_name_snapshot' => $employeeName,
                'employee_rut_snapshot' => filled($employeeRut) ? trim((string) $employeeRut) : null,
                'employee_position_snapshot' => filled($employeePosition) ? trim((string) $employeePosition) : null,
                'delivered_at' => $validated['delivered_at'],
                'received_conformity' => $receivedConformity,
                'received_conformity_at' => $receivedConformity ? now() : null,
                'delivered_by_name' => $request->user()->name,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $record->update([
                'folio' => sprintf(
                    'EPP-%s-%06d',
                    Carbon::parse($validated['delivered_at'])->format('Y'),
                    $record->id,
                ),
            ]);

            foreach ($validated['items'] as $index => $line) {
                $item = RiskPreventionEppItem::query()
                    ->lockForUpdate()
                    ->findOrFail($line['epp_item_id']);
                $quantity = (int) $line['quantity'];

                if (! $item->active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.epp_item_id" => "El EPP {$item->name} está inactivo.",
                    ]);
                }

                $availableStock = $this->availableStockForUpdate($item);
                if ($availableStock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Stock insuficiente para {$item->name}. Disponible: {$availableStock} {$item->available_unit}.",
                    ]);
                }

                RiskPreventionEppDelivery::query()->create([
                    'delivery_record_id' => $record->id,
                    'epp_item_id' => $item->id,
                    'epp_name_snapshot' => $item->name,
                    'unit_snapshot' => $item->available_unit,
                    'employee_name' => $employeeName,
                    'quantity' => $quantity,
                    'delivered_at' => $validated['delivered_at'],
                    'replacement_due_at' => $line['replacement_due_at'] ?? null,
                    'status' => RiskPreventionEppDelivery::STATUS_VIGENTE,
                    'observations' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                $this->decrementAvailableStock(
                    $item,
                    $quantity,
                    $record->folio,
                    $employeeName,
                    $request->user()->id,
                );
            }

            return $record;
        });

        return response()->json([
            'message' => 'Entrega registrada y acta generada correctamente.',
            'data' => $record->fresh()->load(['deliveries.item', 'deliveries.item.inventoryItem']),
        ], 201);
    }

    public function catalogs(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewEppDeliveries($request->user()), 403);

        $items = RiskPreventionEppItem::query()
            ->with('inventoryItem:id,code,name,item_type,stock_quantity,minimum_stock,unit_of_measure,active')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'epp_types' => RiskPreventionEppItem::query()
                ->whereNotNull('epp_type')
                ->distinct()
                ->orderBy('epp_type')
                ->pluck('epp_type')
                ->values(),
            'epp_items' => $items,
            'epp_recipients' => Staff::query()
                ->with('cargo:id,name')
                ->where('active', true)
                ->orderBy('full_name')
                ->limit(500)
                ->get(['id', 'full_name', 'rut', 'cargo_id'])
                ->map(fn (Staff $staff) => [
                    'id' => $staff->id,
                    'name' => $staff->full_name,
                    'rut' => $staff->rut,
                    'position' => $staff->cargo?->name,
                ])
                ->values(),
            'inventory_items' => InventoryItem::query()
                ->with('eppItem:id,inventory_item_id,epp_type')
                ->where('item_type', 'consumable')
                ->where('active', true)
                ->orderBy('name')
                ->limit(1000)
                ->get([
                    'id',
                    'code',
                    'name',
                    'stock_quantity',
                    'minimum_stock',
                    'unit_of_measure',
                ]),
            'permissions' => [
                'can_manage_catalog' => $this->accessService->canManage($request->user()),
                'can_register_delivery' => $this->accessService->canRegisterEppDeliveries($request->user()),
            ],
        ]);
    }

    private function normalizedItemPayload(array $payload): array
    {
        if (! filled($payload['inventory_item_id'] ?? null)) {
            $payload['inventory_item_id'] = null;

            return $payload;
        }

        $inventoryItem = InventoryItem::query()
            ->where('item_type', 'consumable')
            ->where('active', true)
            ->findOrFail($payload['inventory_item_id']);

        $payload['name'] = $inventoryItem->name;
        $payload['stock'] = (int) floor((float) ($inventoryItem->stock_quantity ?? 0));
        $payload['minimum_stock'] = (int) floor((float) ($inventoryItem->minimum_stock ?? 0));
        $payload['unit'] = $inventoryItem->unit_of_measure ?: ($payload['unit'] ?? 'unidad');

        return $payload;
    }

    private function availableStockForUpdate(RiskPreventionEppItem $item): float
    {
        if (! $item->inventory_item_id) {
            return (float) $item->stock;
        }

        $inventoryItem = InventoryItem::query()->lockForUpdate()->findOrFail($item->inventory_item_id);
        abort_unless($inventoryItem->item_type === 'consumable' && $inventoryItem->active, 422, 'El insumo EPP vinculado no está disponible en Bodega.');
        $item->setRelation('inventoryItem', $inventoryItem);

        return (float) ($inventoryItem->stock_quantity ?? 0);
    }

    private function decrementAvailableStock(
        RiskPreventionEppItem $item,
        int $quantity,
        string $folio,
        string $employeeName,
        int $userId,
    ): void {
        if (! $item->inventory_item_id) {
            $item->decrement('stock', $quantity);
            $item->forceFill(['updated_by' => $userId])->save();

            return;
        }

        /** @var InventoryItem $inventoryItem */
        $inventoryItem = $item->inventoryItem;
        $previousStock = (float) ($inventoryItem->stock_quantity ?? 0);
        $newStock = $previousStock - $quantity;

        InventoryStockMovement::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'movement_type' => 'out',
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'reason' => "Entrega EPP {$folio} a {$employeeName}.",
            'created_by' => $userId,
        ]);

        $inventoryItem->forceFill([
            'stock_quantity' => $newStock,
            'updated_by' => $userId,
        ])->save();

        // Snapshot de compatibilidad para reportes históricos; Bodega sigue siendo la fuente vigente.
        $item->forceFill([
            'stock' => (int) floor($newStock),
            'minimum_stock' => (int) floor((float) ($inventoryItem->minimum_stock ?? 0)),
            'unit' => $inventoryItem->unit_of_measure ?: $item->unit,
            'updated_by' => $userId,
        ])->save();
    }
}
