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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                ->with('category:id,name')
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

    public function similar(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $items = InventoryItem::query()
            ->with([
                'category:id,name,slug,code_prefix',
                'subcategory:id,category_id,name,slug',
                'dependency:id,code,name,distribution,sector,zone,usage',
                'responsibleUser:id,name,email',
                'supplier:id,name,business_name,rut',
            ])
            ->where(function ($query) use ($search) {
                $query
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            })
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(
        StoreInventoryItemRequest $request,
        InventoryCodeService $codeService,
        QrValueService $qrValueService
    ): JsonResponse {
        $payload = $request->validated();
        $photo = $request->file('photo');
        $createQuantity = (int) ($payload['create_quantity'] ?? 1);
        $createMode = $payload['create_mode'] ?? 'single';
        unset($payload['photo'], $payload['create_quantity'], $payload['create_mode']);

        $category = !empty($payload['category_id'])
            ? InventoryCategory::query()->findOrFail($payload['category_id'])
            : null;

        $userId = $request->user()?->id;
        $itemType = $payload['item_type'] ?? 'asset';
        $createIndividualUnits = $itemType === 'asset'
            && $createMode === 'units'
            && $createQuantity > 1;
        $totalToCreate = $createIndividualUnits ? $createQuantity : 1;
        $items = [];

        for ($index = 1; $index <= $totalToCreate; $index++) {
            $itemPayload = $payload;
            $code = $codeService->nextCode($category);

            $itemPayload['code'] = $code;
            $itemPayload['qr_code'] = $qrValueService->forCode($code);
            $itemPayload['created_by'] = $userId;
            $itemPayload['updated_by'] = $userId;
            $itemPayload = $this->normalizeWarrantyPayload($itemPayload);

            if (($itemPayload['item_type'] ?? 'asset') === 'consumable' && !isset($itemPayload['stock_quantity'])) {
                $itemPayload['stock_quantity'] = 0;
            }

            if ($createIndividualUnits) {
                $itemPayload['stock_quantity'] = 1;
                $itemPayload['minimum_stock'] = null;
                $itemPayload['unit_of_measure'] = $itemPayload['unit_of_measure'] ?: 'un';
                $itemPayload['serial_number'] = $this->generatedFallbackSerialNumber();
            }

            $item = InventoryItem::create($itemPayload);

            if ($photo instanceof UploadedFile) {
                $this->storeMainPhoto($item, $photo, $userId);
            }

            $items[] = $item;
        }

        $relations = [
            'category:id,name,slug,code_prefix',
            'subcategory:id,category_id,name,slug',
            'dependency:id,code,name,distribution,sector,zone,usage',
            'responsibleUser:id,name,email',
            'supplier:id,name,business_name,rut',
        ];
        $createdItems = InventoryItem::query()
            ->with($relations)
            ->whereIn('id', collect($items)->pluck('id'))
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => $createdItems->count() > 1
                ? "{$createdItems->count()} bienes creados correctamente."
                : 'Bien creado correctamente.',
            'data' => $createdItems->count() === 1 ? $createdItems->first() : $createdItems,
            'created_count' => $createdItems->count(),
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

    public function image(InventoryItem $item): StreamedResponse
    {
        abort_unless($item->image_path, 404);
        abort_unless(Storage::disk('public')->exists($item->image_path), 404);

        return Storage::disk('public')->response($item->image_path);
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

    private function generatedFallbackSerialNumber(): string
    {
        return sprintf(
            'SS-%s-%s-%s',
            now()->format('Ymd'),
            now()->format('His'),
            Str::upper(Str::random(4))
        );
    }

    private function storeMainPhoto(InventoryItem $item, UploadedFile $photo, ?int $uploadedBy): void
    {
        $extension = $photo->extension() ?: $photo->getClientOriginalExtension() ?: 'jpg';
        $path = Storage::disk('public')->putFileAs(
            sprintf('inventory/items/%d', $item->id),
            $photo,
            'main_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $extension,
            ['visibility' => 'public']
        );

        if (!$path) {
            throw ValidationException::withMessages([
                'photo' => 'No se pudo guardar la foto del bien. Revisa permisos de storage en producción.',
            ]);
        }

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
