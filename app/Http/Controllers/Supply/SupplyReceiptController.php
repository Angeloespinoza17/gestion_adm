<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supply\StoreSupplyReceiptRequest;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyReceipt;
use App\Services\Supply\SupplyStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplyReceiptController extends Controller
{
    public function index(Request $request, SupplyStockService $stockService): JsonResponse
    {
        $section = validator($request->query(), [
            'section' => ['required', Rule::in(SupplyItem::sections())],
        ])->validate()['section'];
        $storeroomId = $request->integer('storeroom_id');

        $receipts = SupplyReceipt::query()
            ->where('section', $section)
            ->when($section === SupplyItem::SECTION_MAINTENANCE_STOREROOM && $storeroomId > 0, fn ($query) => $query->where('storeroom_id', $storeroomId))
            ->with($stockService->receiptRelations())
            ->withSum('items', 'quantity')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json($receipts);
    }

    public function store(StoreSupplyReceiptRequest $request, SupplyStockService $stockService): JsonResponse
    {
        $receipt = $stockService->receive($request->validated(), $request->user());

        return response()->json([
            'message' => 'Compra registrada y stock actualizado correctamente.',
            'data' => $receipt,
        ], 201);
    }
}
