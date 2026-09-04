<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supply\StoreSupplyDeliveryRequest;
use App\Models\Supply\SupplyDelivery;
use App\Models\Supply\SupplyItem;
use App\Services\Supply\SupplyStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplyDeliveryController extends Controller
{
    public function index(Request $request, SupplyStockService $stockService): JsonResponse
    {
        $section = validator($request->query(), [
            'section' => ['required', Rule::in(SupplyItem::sections())],
        ])->validate()['section'];
        $storeroomId = $request->integer('storeroom_id');

        $deliveries = SupplyDelivery::query()
            ->where('section', $section)
            ->when($section === SupplyItem::SECTION_MAINTENANCE_STOREROOM && $storeroomId > 0, fn ($query) => $query->where('storeroom_id', $storeroomId))
            ->with($stockService->deliveryRelations())
            ->withSum('items', 'quantity')
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json($deliveries);
    }

    public function store(StoreSupplyDeliveryRequest $request, SupplyStockService $stockService): JsonResponse
    {
        $delivery = $stockService->deliver($request->validated(), $request->user());

        return response()->json([
            'message' => 'Entrega registrada y stock descontado correctamente.',
            'data' => $delivery,
        ], 201);
    }

    public function show(SupplyDelivery $delivery, SupplyStockService $stockService): JsonResponse
    {
        return response()->json([
            'data' => $delivery->load($stockService->deliveryRelations()),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
