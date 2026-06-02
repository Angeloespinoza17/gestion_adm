<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockMovementRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    public function index(Request $request, InventoryItem $item): JsonResponse
    {
        $movements = InventoryStockMovement::query()
            ->where('inventory_item_id', $item->id)
            ->with('createdBy:id,name')
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($movements);
    }

    public function store(StockMovementRequest $request, InventoryItem $item): JsonResponse
    {
        if ($item->item_type !== 'consumable') {
            return response()->json([
                'message' => 'Este bien no maneja stock (no es un insumo).',
            ], 422);
        }

        $payload = $request->validated();

        $previous = (float) ($item->stock_quantity ?? 0);
        $quantity = (float) $payload['quantity'];

        $new = $previous;
        if ($payload['movement_type'] === 'in') {
            $new = $previous + $quantity;
        } elseif ($payload['movement_type'] === 'out') {
            if ($quantity > $previous) {
                return response()->json([
                    'message' => 'Stock insuficiente para la salida solicitada.',
                ], 422);
            }
            $new = $previous - $quantity;
        } elseif ($payload['movement_type'] === 'adjust') {
            $new = $quantity;
        }

        $movement = InventoryStockMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => $payload['movement_type'],
            'quantity' => $quantity,
            'previous_stock' => $previous,
            'new_stock' => $new,
            'reason' => $payload['reason'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $item->update([
            'stock_quantity' => $new,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Movimiento de stock registrado correctamente.',
            'data' => $movement->load('createdBy:id,name'),
        ], 201);
    }
}

