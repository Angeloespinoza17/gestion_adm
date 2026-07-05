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
        $totalValue = (int) InventoryItem::query()->sum('purchase_value');
        $activeValue = (int) InventoryItem::query()
            ->where('active', true)
            ->sum('purchase_value');

        $totals = [
            'total' => (int) InventoryItem::query()->count(),
            'active' => (int) InventoryItem::query()->where('active', true)->count(),
            'assets' => (int) InventoryItem::query()->where('item_type', 'asset')->count(),
            'consumables' => (int) InventoryItem::query()->where('item_type', 'consumable')->count(),
            'total_value' => $totalValue,
            'active_value' => $activeValue,
            'without_photo' => (int) InventoryItem::query()->whereNull('image_path')->count(),
            'without_responsible' => (int) InventoryItem::query()->whereNull('responsible_user_id')->count(),
            'critical_condition' => (int) InventoryItem::query()->whereIn('condition', ['Crítico', 'Inutilizable'])->count(),
            'in_repair' => (int) InventoryItem::query()->where('status', 'En reparación')->count(),
            'retired' => (int) InventoryItem::query()->where('status', 'Dado de baja')->count(),
        ];

        $byCategory = DB::table('inventory_items')
            ->select(
                'inventory_categories.name as category',
                DB::raw('count(*) as total'),
                DB::raw('COALESCE(SUM(inventory_items.purchase_value), 0) as value_total')
            )
            ->leftJoin('inventory_categories', 'inventory_categories.id', '=', 'inventory_items.category_id')
            ->groupBy('inventory_categories.id', 'inventory_categories.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category ?: 'Sin categoría',
                'total' => (int) $row->total,
                'value_total' => (int) $row->value_total,
            ]);

        $byStatus = DB::table('inventory_items')
            ->select(
                'status as label',
                DB::raw('count(*) as total')
            )
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ?: 'Sin estado',
                'total' => (int) $row->total,
            ]);

        $byCondition = DB::table('inventory_items')
            ->select(
                DB::raw('`condition` as label'),
                DB::raw('count(*) as total')
            )
            ->groupBy('condition')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ?: 'Sin condición',
                'total' => (int) $row->total,
            ]);

        $lowStock = InventoryItem::query()
            ->where('item_type', 'consumable')
            ->whereNotNull('minimum_stock')
            ->whereNotNull('stock_quantity')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();

        $lowStockItems = InventoryItem::query()
            ->with([
                'category:id,name',
                'dependency:id,code,name',
            ])
            ->where('item_type', 'consumable')
            ->whereNotNull('minimum_stock')
            ->whereNotNull('stock_quantity')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get([
                'id',
                'code',
                'name',
                'category_id',
                'dependency_id',
                'stock_quantity',
                'minimum_stock',
                'unit_of_measure',
            ]);

        return response()->json([
            'totals' => $totals,
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'by_condition' => $byCondition,
            'low_stock' => (int) $lowStock,
            'low_stock_items' => $lowStockItems,
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
