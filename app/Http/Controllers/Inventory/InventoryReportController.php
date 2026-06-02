<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $totals = [
            'total' => (int) InventoryItem::query()->count(),
            'active' => (int) InventoryItem::query()->where('active', true)->count(),
            'assets' => (int) InventoryItem::query()->where('item_type', 'asset')->count(),
            'consumables' => (int) InventoryItem::query()->where('item_type', 'consumable')->count(),
            'without_photo' => (int) InventoryItem::query()->whereNull('image_path')->count(),
            'without_responsible' => (int) InventoryItem::query()->whereNull('responsible_user_id')->count(),
            'critical_condition' => (int) InventoryItem::query()->whereIn('condition', ['Crítico', 'Inutilizable'])->count(),
            'in_repair' => (int) InventoryItem::query()->where('status', 'En reparación')->count(),
            'retired' => (int) InventoryItem::query()->where('status', 'Dado de baja')->count(),
        ];

        $byCategory = DB::table('inventory_items')
            ->select('inventory_categories.name as category', DB::raw('count(*) as total'))
            ->join('inventory_categories', 'inventory_categories.id', '=', 'inventory_items.category_id')
            ->groupBy('inventory_categories.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $lowStock = InventoryItem::query()
            ->where('item_type', 'consumable')
            ->whereNotNull('minimum_stock')
            ->whereNotNull('stock_quantity')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();

        return response()->json([
            'totals' => $totals,
            'by_category' => $byCategory,
            'low_stock' => (int) $lowStock,
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $items = InventoryItem::query()
            ->with([
                'category:id,name,slug,code_prefix',
                'dependency:id,code,name',
            ])
            ->where('item_type', 'consumable')
            ->whereNotNull('minimum_stock')
            ->whereNotNull('stock_quantity')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderBy('name')
            ->paginate(50);

        return response()->json($items);
    }
}

