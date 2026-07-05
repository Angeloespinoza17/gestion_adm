<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPhoto;
use App\Models\InventorySubcategory;
use App\Models\MaintenanceDependency;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryCodeService;
use App\Services\Inventory\QrValueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class InventoryItemController extends Controller
{
    public function catalogs(Request $request): JsonResponse
    {
        return response()->json([
            'categories' => InventoryCategory::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'code_prefix']),
            'subcategories' => InventorySubcategory::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'category_id', 'name', 'slug']),
            'dependencies' => MaintenanceDependency::query()
                ->physicalSpaces()
                ->where('active', true)
                ->orderBy('code')
                ->get([
                    'id',
                    'code',
                    'name',
                    'distribution',
                    'sector',
                    'zone',
                    'usage',
                    'is_inventory_auditable',
                    'is_maintenance_location',
                ]),
            'users' => User::query()
                ->where('active', true)
                ->where(function ($query) {
                    $query
                        ->where('user_type', 'staff')
                        ->orWhereNotNull('staff_id');
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'user_type', 'staff_id']),
            'suppliers' => Supplier::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'rut']),
            'item_types' => ['asset', 'consumable'],
            'statuses' => [
                'Activo',
                'En uso',
                'En bodega',
                'En reparación',
                'Dado de baja',
                'Perdido',
                'Prestado',
                'Pendiente de revisión',
            ],
            'conditions' => [
                'Nuevo',
                'Bueno',
                'Regular',
                'Malo',
                'Crítico',
                'Inutilizable',
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $categoryId = $request->query('category_id');
        $subcategoryId = $request->query('subcategory_id');
        $dependencyId = $request->query('dependency_id');
        $responsibleUserId = $request->query('responsible_user_id');
        $supplierId = $request->query('supplier_id');

        $status = trim((string) $request->query('status'));
        $condition = trim((string) $request->query('condition'));
        $itemType = trim((string) $request->query('item_type'));
        $lowStock = $request->query('low_stock');

        $query = InventoryItem::query()
            ->with([
                'category:id,name,slug,code_prefix',
                'subcategory:id,category_id,name,slug',
                'dependency:id,code,name,distribution,sector,zone,usage',
                'responsibleUser:id,name,email',
                'supplier:id,name,business_name,rut',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($subcategoryId, fn ($query) => $query->where('subcategory_id', $subcategoryId))
            ->when($dependencyId, fn ($query) => $query->where('dependency_id', $dependencyId))
            ->when($responsibleUserId, fn ($query) => $query->where('responsible_user_id', $responsibleUserId))
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($condition !== '', fn ($query) => $query->where('condition', $condition))
            ->when($itemType !== '', fn ($query) => $query->where('item_type', $itemType));

        if ($active !== null && $active !== '') {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($lowStock !== null && $lowStock !== '') {
            $enabled = filter_var($lowStock, FILTER_VALIDATE_BOOLEAN);
            if ($enabled) {
                $query->where('item_type', 'consumable')
                    ->whereNotNull('minimum_stock')
                    ->whereNotNull('stock_quantity')
                    ->whereColumn('stock_quantity', '<=', 'minimum_stock');
            }
        }

        $items = $query
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($items);
    }

    public function store(
        StoreInventoryItemRequest $request,
        InventoryCodeService $codeService,
        QrValueService $qrValueService
    ): JsonResponse {
        $payload = $request->validated();
        $photo = $request->file('photo');
        unset($payload['photo']);

        $category = InventoryCategory::query()->findOrFail($payload['category_id']);
        $code = $codeService->nextCode($category);

        $payload['code'] = $code;
        $payload['qr_code'] = $qrValueService->forCode($code);

        $userId = $request->user()?->id;
        $payload['created_by'] = $userId;
        $payload['updated_by'] = $userId;
        $payload = $this->normalizeWarrantyPayload($payload);

        if (($payload['item_type'] ?? 'asset') === 'consumable' && !isset($payload['stock_quantity'])) {
            $payload['stock_quantity'] = 0;
        }

        $item = InventoryItem::create($payload);

        if ($photo instanceof UploadedFile) {
            $this->storeMainPhoto($item, $photo, $userId);
        }

        return response()->json([
            'message' => 'Bien creado correctamente.',
            'data' => $item->fresh()->load([
                'category:id,name,slug,code_prefix',
                'subcategory:id,category_id,name,slug',
                'dependency:id,code,name,distribution,sector,zone,usage',
                'responsibleUser:id,name,email',
                'supplier:id,name,business_name,rut',
            ]),
        ], 201);
    }

    public function show(InventoryItem $item): JsonResponse
    {
        return response()->json([
            'data' => $item->load([
                'category:id,name,slug,code_prefix',
                'subcategory:id,category_id,name,slug',
                'dependency:id,code,name,distribution,sector,zone,usage',
                'responsibleUser:id,name,email',
                'supplier:id,name,business_name,rut',
                'photos',
                'documents',
                'movements.fromDependency:id,code,name',
                'movements.toDependency:id,code,name',
                'movements.fromUser:id,name',
                'movements.toUser:id,name',
                'movements.createdBy:id,name',
                'stockMovements.createdBy:id,name',
            ]),
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): JsonResponse
    {
        $payload = $request->validated();
        $photo = $request->file('photo');
        unset($payload['photo']);

        $payload['updated_by'] = $request->user()?->id;
        $payload = $this->normalizeWarrantyPayload($payload, $item);

        $item->update($payload);

        if ($photo instanceof UploadedFile) {
            $this->storeMainPhoto($item, $photo, $request->user()?->id);
        }

        return response()->json([
            'message' => 'Bien actualizado correctamente.',
            'data' => $item->fresh()->load([
                'category:id,name,slug,code_prefix',
                'subcategory:id,category_id,name,slug',
                'dependency:id,code,name,distribution,sector,zone,usage',
                'responsibleUser:id,name,email',
                'supplier:id,name,business_name,rut',
            ]),
        ]);
    }

    public function destroy(InventoryItem $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'message' => 'Bien eliminado correctamente.',
        ]);
    }

    private function storeMainPhoto(InventoryItem $item, UploadedFile $photo, ?int $uploadedBy): void
    {
        $path = $photo->storePubliclyAs(
            sprintf('inventory/items/%d', $item->id),
            'main_' . now()->format('Ymd_His') . '.' . $photo->getClientOriginalExtension(),
            ['disk' => 'public']
        );

        $item->image_path = $path;
        $item->save();

        InventoryPhoto::query()
            ->where('inventory_item_id', $item->id)
            ->update(['is_main' => false]);

        InventoryPhoto::create([
            'inventory_item_id' => $item->id,
            'image_path' => $path,
            'original_name' => $photo->getClientOriginalName(),
            'is_main' => true,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    private function normalizeWarrantyPayload(array $payload, ?InventoryItem $item = null): array
    {
        $touchesWarranty = array_key_exists('has_warranty', $payload)
            || array_key_exists('warranty_months', $payload)
            || array_key_exists('purchase_date', $payload);

        if (!$touchesWarranty && $item !== null) {
            return $payload;
        }

        $hasWarranty = array_key_exists('has_warranty', $payload)
            ? filter_var($payload['has_warranty'], FILTER_VALIDATE_BOOLEAN)
            : (bool) ($item?->has_warranty ?? false);

        $payload['has_warranty'] = $hasWarranty;

        if (!$hasWarranty) {
            $payload['warranty_months'] = null;
            $payload['warranty_expires_at'] = null;

            return $payload;
        }

        $months = array_key_exists('warranty_months', $payload)
            ? $payload['warranty_months']
            : $item?->warranty_months;

        $payload['warranty_months'] = $months !== null && $months !== ''
            ? (int) $months
            : null;

        $purchaseDate = array_key_exists('purchase_date', $payload)
            ? $payload['purchase_date']
            : $item?->purchase_date?->toDateString();

        $payload['warranty_expires_at'] = $payload['warranty_months'] && $purchaseDate
            ? Carbon::parse($purchaseDate)->addMonthsNoOverflow($payload['warranty_months'])->toDateString()
            : null;

        return $payload;
    }
}
