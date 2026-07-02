<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionEppDeliveryRequest;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionEppItemRequest;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionEppItem;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskPreventionEppController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function itemsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionEppItem::class);

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('epp_type'));
        $lowStock = filter_var($request->query('low_stock'), FILTER_VALIDATE_BOOLEAN);

        $items = RiskPreventionEppItem::query()
            ->withCount('deliveries')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('epp_type', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('epp_type', $type))
            ->when($lowStock, fn ($query) => $query->whereColumn('stock', '<=', 'minimum_stock'))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 10));

        return response()->json($items);
    }

    public function storeItem(SaveRiskPreventionEppItemRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionEppItem::class);

        $item = RiskPreventionEppItem::query()->create(array_merge(
            $request->validated(),
            [
                'active' => $request->boolean('active', true),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        ));

        return response()->json([
            'message' => 'Elemento EPP creado correctamente.',
            'data' => $item->fresh(),
        ], 201);
    }

    public function updateItem(SaveRiskPreventionEppItemRequest $request, RiskPreventionEppItem $eppItem): JsonResponse
    {
        $this->authorize('update', $eppItem);

        $eppItem->update(array_merge(
            $request->validated(),
            [
                'active' => $request->boolean('active', true),
                'updated_by' => $request->user()->id,
            ],
        ));

        return response()->json([
            'message' => 'Elemento EPP actualizado correctamente.',
            'data' => $eppItem->fresh(),
        ]);
    }

    public function destroyItem(RiskPreventionEppItem $eppItem): JsonResponse
    {
        $this->authorize('delete', $eppItem);
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
            ->with('item:id,name,epp_type,unit')
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
            'data' => $delivery->fresh()->load('item:id,name,epp_type,unit'),
        ], 201);
    }

    public function updateDelivery(SaveRiskPreventionEppDeliveryRequest $request, RiskPreventionEppDelivery $eppDelivery): JsonResponse
    {
        $this->authorize('update', $eppDelivery);

        $eppDelivery->update(array_merge(
            $request->validated(),
            ['updated_by' => $request->user()->id],
        ));

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Entrega de EPP actualizada correctamente.',
            'data' => $eppDelivery->fresh()->load('item:id,name,epp_type,unit'),
        ]);
    }

    public function destroyDelivery(RiskPreventionEppDelivery $eppDelivery): JsonResponse
    {
        $this->authorize('delete', $eppDelivery);
        $eppDelivery->delete();

        return response()->json([
            'message' => 'Entrega de EPP eliminada correctamente.',
        ]);
    }
}
