<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDependencyAudit;
use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InventoryManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $dependencies = $this->dependencyQuery()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('distribution', 'like', "%{$search}%")
                        ->orWhere('sector', 'like', "%{$search}%")
                        ->orWhere('zone', 'like', "%{$search}%")
                        ->orWhere('usage', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate((int) $request->query('per_page', 18));

        $dependencies->getCollection()->transform(
            fn (MaintenanceDependency $dependency) => $this->formatDependency($dependency),
        );

        return response()->json($dependencies);
    }

    public function show(Request $request, MaintenanceDependency $dependency): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $itemType = trim((string) $request->query('item_type'));
        $status = trim((string) $request->query('status'));
        $condition = trim((string) $request->query('condition'));

        $dependency = $this->dependencyQuery()
            ->whereKey($dependency->id)
            ->firstOrFail();

        $items = InventoryItem::query()
            ->where('dependency_id', $dependency->id)
            ->with([
                'category:id,name,slug,code_prefix',
                'subcategory:id,category_id,name,slug',
                'responsibleUser:id,name,email',
                'supplier:id,name,business_name,rut',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->when($itemType !== '', fn (Builder $query) => $query->where('item_type', $itemType))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($condition !== '', fn (Builder $query) => $query->where('condition', $condition))
            ->orderBy('code')
            ->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'dependency' => $this->formatDependency($dependency),
            'items' => $items,
            'audits' => $dependency->inventoryAudits()
                ->with('auditedBy:id,name')
                ->orderByDesc('audited_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'catalogs' => [
                'item_types' => ['asset', 'consumable'],
                'statuses' => InventoryItem::query()
                    ->where('dependency_id', $dependency->id)
                    ->whereNotNull('status')
                    ->distinct()
                    ->orderBy('status')
                    ->pluck('status')
                    ->values(),
                'conditions' => InventoryItem::query()
                    ->where('dependency_id', $dependency->id)
                    ->whereNotNull('condition')
                    ->distinct()
                    ->orderBy('condition')
                    ->pluck('condition')
                    ->values(),
            ],
        ]);
    }

    public function storeAudit(Request $request, MaintenanceDependency $dependency): JsonResponse
    {
        $validated = $request->validate([
            'audited_at' => ['nullable', 'date'],
            'found_items_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $activeItems = InventoryItem::query()
            ->where('dependency_id', $dependency->id)
            ->where('active', true);

        $expected = (clone $activeItems)->count();
        $critical = (clone $activeItems)
            ->whereIn('condition', ['Malo', 'Crítico', 'Inutilizable'])
            ->count();
        $lowStock = (clone $activeItems)
            ->where('item_type', 'consumable')
            ->whereNotNull('minimum_stock')
            ->whereNotNull('stock_quantity')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();
        $found = array_key_exists('found_items_count', $validated)
            ? (int) $validated['found_items_count']
            : $expected;

        $audit = InventoryDependencyAudit::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'audited_at' => isset($validated['audited_at'])
                ? Carbon::parse($validated['audited_at'])
                : now(),
            'expected_items_count' => $expected,
            'found_items_count' => $found,
            'missing_items_count' => max($expected - $found, 0),
            'critical_items_count' => $critical,
            'low_stock_items_count' => $lowStock,
            'notes' => $validated['notes'] ?? null,
            'audited_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Inventario de dependencia registrado correctamente.',
            'data' => $audit->load('auditedBy:id,name'),
            'dependency' => $this->formatDependency(
                $this->dependencyQuery()->whereKey($dependency->id)->firstOrFail(),
            ),
        ], 201);
    }

    private function dependencyQuery(): Builder
    {
        return MaintenanceDependency::query()
            ->inventoryAuditableSpaces()
            ->where('active', true)
            ->with([
                'type:id,name,color',
                'responsibleStaff:id,full_name,rut',
                'latestInventoryAudit.auditedBy:id,name',
            ])
            ->withCount([
                'inventoryItems as total_items_count',
                'inventoryItems as active_items_count' => fn (Builder $query) => $query->where('active', true),
                'inventoryItems as assets_count' => fn (Builder $query) => $query->where('item_type', 'asset'),
                'inventoryItems as consumables_count' => fn (Builder $query) => $query->where('item_type', 'consumable'),
                'inventoryItems as critical_items_count' => fn (Builder $query) => $query->whereIn('condition', ['Malo', 'Crítico', 'Inutilizable']),
                'inventoryItems as low_stock_items_count' => fn (Builder $query) => $query
                    ->where('item_type', 'consumable')
                    ->whereNotNull('minimum_stock')
                    ->whereNotNull('stock_quantity')
                    ->whereColumn('stock_quantity', '<=', 'minimum_stock'),
            ]);
    }

    private function formatDependency(MaintenanceDependency $dependency): array
    {
        $latestAudit = $dependency->latestInventoryAudit;

        return [
            'id' => $dependency->id,
            'code' => $dependency->code,
            'name' => $dependency->name,
            'distribution' => $dependency->distribution,
            'sector' => $dependency->sector,
            'zone' => $dependency->zone,
            'usage' => $dependency->usage,
            'active' => $dependency->active,
            'type' => $dependency->type,
            'responsible_staff' => $dependency->responsibleStaff,
            'total_items_count' => (int) ($dependency->total_items_count ?? 0),
            'active_items_count' => (int) ($dependency->active_items_count ?? 0),
            'assets_count' => (int) ($dependency->assets_count ?? 0),
            'consumables_count' => (int) ($dependency->consumables_count ?? 0),
            'critical_items_count' => (int) ($dependency->critical_items_count ?? 0),
            'low_stock_items_count' => (int) ($dependency->low_stock_items_count ?? 0),
            'latest_inventory' => $latestAudit,
            'inventory_status' => $this->inventoryStatus($latestAudit?->audited_at),
        ];
    }

    private function inventoryStatus(?Carbon $auditedAt): string
    {
        if (!$auditedAt) {
            return 'sin_inventario';
        }

        if ($auditedAt->lt(now()->subMonths(6))) {
            return 'desactualizado';
        }

        return 'vigente';
    }
}
