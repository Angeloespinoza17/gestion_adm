<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\MoveInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function index(Request $request, InventoryItem $item): JsonResponse
    {
        $movements = InventoryMovement::query()
            ->where('inventory_item_id', $item->id)
            ->with([
                'fromDependency:id,code,name',
                'toDependency:id,code,name',
                'fromUser:id,name',
                'toUser:id,name',
                'createdBy:id,name',
            ])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($movements);
    }

    public function move(MoveInventoryItemRequest $request, InventoryItem $item): JsonResponse
    {
        $payload = $request->validated();

        $movement = InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'from_dependency_id' => $item->dependency_id,
            'to_dependency_id' => $payload['to_dependency_id'] ?? null,
            'from_user_id' => $item->responsible_user_id,
            'to_user_id' => $payload['to_user_id'] ?? null,
            'movement_type' => $payload['movement_type'],
            'movement_date' => $payload['movement_date'] ?? now()->toDateString(),
            'reason' => $payload['reason'] ?? null,
            'observations' => $payload['observations'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $item->update([
            'dependency_id' => $payload['to_dependency_id'] ?? $item->dependency_id,
            'responsible_user_id' => $payload['to_user_id'] ?? $item->responsible_user_id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Movimiento registrado correctamente.',
            'data' => $movement->load([
                'fromDependency:id,code,name',
                'toDependency:id,code,name',
                'fromUser:id,name',
                'toUser:id,name',
                'createdBy:id,name',
            ]),
        ], 201);
    }
}

