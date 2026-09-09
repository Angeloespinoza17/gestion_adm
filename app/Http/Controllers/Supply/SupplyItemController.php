<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supply\StoreSupplyItemRequest;
use App\Http\Requests\Supply\UpdateSupplyItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\Supply\SupplyItem;
use App\Services\Inventory\InventoryCodeService;
use App\Services\Inventory\QrValueService;
use App\Services\Supply\SupplyRecipientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplyItemController extends Controller
{
    public function catalogs(Request $request, SupplyRecipientService $recipientService): JsonResponse
    {
        return response()->json([
            'sections' => [
                ['value' => SupplyItem::SECTION_CLEANING, 'label' => 'Insumos de aseo'],
                ['value' => SupplyItem::SECTION_HEATING, 'label' => 'Combustibles y calefacción'],
                ['value' => SupplyItem::SECTION_MAINTENANCE_STOREROOM, 'label' => 'Pañol de mantenimiento'],
            ],
            'types' => [
                SupplyItem::SECTION_CLEANING => [
                    ['value' => 'cleaner', 'label' => 'Limpiador y desinfectante'],
                    ['value' => 'paper', 'label' => 'Papeles y desechables'],
                    ['value' => 'implement', 'label' => 'Implementos de aseo'],
                    ['value' => 'soap', 'label' => 'Jabones y detergentes'],
                    ['value' => 'other', 'label' => 'Otro insumo de aseo'],
                ],
                SupplyItem::SECTION_HEATING => [
                    ['value' => 'pellet', 'label' => 'Pellet'],
                    ['value' => 'gas_cylinder', 'label' => 'Cilindro de gas con carga'],
                    ['value' => 'firewood', 'label' => 'Leña'],
                    ['value' => 'fuel', 'label' => 'Otro combustible'],
                    ['value' => 'other', 'label' => 'Otro insumo de calefacción'],
                ],
                SupplyItem::SECTION_MAINTENANCE_STOREROOM => [
                    ['value' => 'hand_tool', 'label' => 'Herramienta manual'],
                    ['value' => 'power_tool', 'label' => 'Herramienta eléctrica'],
                    ['value' => 'measuring_tool', 'label' => 'Medición y diagnóstico'],
                    ['value' => 'accessory', 'label' => 'Accesorio o complemento'],
                    ['value' => 'spare_part', 'label' => 'Repuesto'],
                    ['value' => 'maintenance_material', 'label' => 'Material de mantenimiento'],
                    ['value' => 'safety_item', 'label' => 'Seguridad y protección'],
                    ['value' => 'other', 'label' => 'Otro artículo de pañol'],
                ],
            ],
            'units' => [
                ['value' => 'unidad', 'label' => 'Unidad'],
                ['value' => 'litro', 'label' => 'Litro'],
                ['value' => 'kilogramo', 'label' => 'Kilogramo'],
                ['value' => 'saco', 'label' => 'Saco'],
                ['value' => 'cilindro', 'label' => 'Cilindro'],
                ['value' => 'metro_cubico', 'label' => 'Metro cúbico'],
                ['value' => 'rollo', 'label' => 'Rollo'],
                ['value' => 'caja', 'label' => 'Caja'],
                ['value' => 'bidon', 'label' => 'Bidón'],
                ['value' => 'juego', 'label' => 'Juego'],
                ['value' => 'pieza', 'label' => 'Pieza'],
                ['value' => 'par', 'label' => 'Par'],
            ],
            'suppliers' => Supplier::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'rut']),
            'delivery_recipients' => $recipientService->catalog(),
            'capabilities' => [
                'manage_items' => $request->user()->hasPermission('gestionar_insumos_abastecimiento'),
                'receive_stock' => $request->user()->hasPermission('registrar_compras_abastecimiento'),
                'create_deliveries' => $request->user()->hasPermission('registrar_entregas_abastecimiento'),
                'export_acts' => $request->user()->hasPermission('exportar_actas_abastecimiento'),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $section = $this->validatedSection($request);
        $storeroomId = $request->integer('storeroom_id');
        $search = trim((string) $request->query('search'));
        $stockStatus = trim((string) $request->query('stock_status'));
        $activeOnly = $request->boolean('active_only');

        validator(['stock_status' => $stockStatus], [
            'stock_status' => ['nullable', Rule::in(['available', 'low', 'empty'])],
        ])->validate();

        $base = SupplyItem::query()
            ->join('inventory_items as supply_inventory', 'supply_inventory.id', '=', 'supply_items.inventory_item_id')
            ->select('supply_items.*')
            ->where('supply_items.section', $section)
            ->when(
                $section === SupplyItem::SECTION_MAINTENANCE_STOREROOM && $storeroomId > 0,
                fn ($query) => $query->where('supply_items.storeroom_id', $storeroomId),
            )
            ->when($activeOnly, fn ($query) => $query->where('supply_inventory.active', true))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('supply_inventory.name', 'like', "%{$search}%")
                        ->orWhere('supply_inventory.code', 'like', "%{$search}%")
                        ->orWhere('supply_inventory.description', 'like', "%{$search}%");
                });
            });

        if ($stockStatus === 'available') {
            $base->where(function ($query) use ($section): void {
                if ($section === SupplyItem::SECTION_MAINTENANCE_STOREROOM) {
                    $query->where('supply_inventory.item_type', '!=', 'consumable')
                        ->orWhere('supply_inventory.stock_quantity', '>', 0);
                } else {
                    $query->where('supply_inventory.stock_quantity', '>', 0);
                }
            })->where(function ($query): void {
                $query->where('supply_inventory.item_type', '!=', 'consumable')
                    ->orWhere(function ($query): void {
                        $query->whereNull('supply_inventory.minimum_stock')
                            ->orWhereColumn('supply_inventory.stock_quantity', '>', 'supply_inventory.minimum_stock');
                    });
            });
        } elseif ($stockStatus === 'low') {
            $base->where('supply_inventory.item_type', 'consumable')
                ->where('supply_inventory.stock_quantity', '>', 0)
                ->whereNotNull('supply_inventory.minimum_stock')
                ->whereColumn('supply_inventory.stock_quantity', '<=', 'supply_inventory.minimum_stock');
        } elseif ($stockStatus === 'empty') {
            $base->where('supply_inventory.item_type', 'consumable')
                ->where('supply_inventory.stock_quantity', '<=', 0);
        }

        $summary = SupplyItem::query()
            ->where('supply_items.section', $section)
            ->when(
                $section === SupplyItem::SECTION_MAINTENANCE_STOREROOM && $storeroomId > 0,
                fn ($query) => $query->where('supply_items.storeroom_id', $storeroomId),
            )
            ->join('inventory_items', 'inventory_items.id', '=', 'supply_items.inventory_item_id')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN inventory_items.active = 1 THEN 1 ELSE 0 END) as active_items')
            ->selectRaw("SUM(CASE WHEN inventory_items.item_type = 'consumable' AND COALESCE(inventory_items.stock_quantity, 0) <= 0 THEN 1 ELSE 0 END) as empty_items")
            ->selectRaw("SUM(CASE WHEN inventory_items.item_type = 'consumable' AND inventory_items.minimum_stock IS NOT NULL AND inventory_items.stock_quantity > 0 AND inventory_items.stock_quantity <= inventory_items.minimum_stock THEN 1 ELSE 0 END) as low_stock_items")
            ->first();

        $items = $base
            ->with([
                'storeroom:id,code,name',
                'inventoryItem:id,code,name,description,category_id,supplier_id,item_type,stock_quantity,minimum_stock,unit_of_measure,active,image_path,updated_at',
                'inventoryItem.supplier:id,name,business_name,rut',
            ])
            ->orderByRaw("CASE WHEN supply_inventory.item_type = 'consumable' THEN COALESCE(supply_inventory.stock_quantity, 0) ELSE 1 END ASC")
            ->orderByDesc('supply_items.id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'summary' => $summary,
        ]);
    }

    public function store(
        StoreSupplyItemRequest $request,
        InventoryCodeService $codeService,
        QrValueService $qrValueService
    ): JsonResponse {
        $payload = $request->validated();
        $photo = $request->file('photo');
        $actorId = $request->user()->id;

        $supplyItem = DB::transaction(function () use ($payload, $actorId, $codeService, $qrValueService): SupplyItem {
            $category = $this->categoryForSection($payload['section']);
            $code = $codeService->nextCode($category);
            $inventoryItem = InventoryItem::query()->create([
                'code' => $code,
                'qr_code' => $qrValueService->forCode($code),
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'category_id' => $category->id,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'status' => 'En bodega',
                'condition' => 'Bueno',
                'active' => true,
                'item_type' => 'consumable',
                'stock_quantity' => 0,
                'minimum_stock' => $payload['minimum_stock'] ?? null,
                'unit_of_measure' => $payload['unit_of_measure'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return SupplyItem::query()->create([
                'inventory_item_id' => $inventoryItem->id,
                'section' => $payload['section'],
                'storeroom_id' => $payload['storeroom_id'] ?? null,
                'supply_type' => $payload['supply_type'],
            ]);
        });

        if ($photo instanceof UploadedFile) {
            $this->replacePhoto($supplyItem, $photo);
        }

        return response()->json([
            'message' => 'Insumo creado. Registra una compra para cargar existencias.',
            'data' => $this->loadItem($supplyItem),
        ], 201);
    }

    public function show(SupplyItem $item): JsonResponse
    {
        $item = $this->loadItem($item);
        $item->loadCount(['receiptItems', 'deliveryItems', 'requestItems']);

        return response()->json([
            'data' => $item,
        ]);
    }

    public function update(UpdateSupplyItemRequest $request, SupplyItem $item): JsonResponse
    {
        $payload = $request->validated();

        DB::transaction(function () use ($payload, $request, $item): void {
            $item->update([
                'supply_type' => $payload['supply_type'],
                'storeroom_id' => $payload['storeroom_id'] ?? null,
            ]);
            $item->inventoryItem()->update([
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'minimum_stock' => $payload['minimum_stock'] ?? null,
                'unit_of_measure' => $payload['unit_of_measure'],
                'active' => $payload['active'],
                'updated_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Insumo actualizado correctamente.',
            'data' => $this->loadItem($item),
        ]);
    }

    public function destroy(Request $request, SupplyItem $item): JsonResponse
    {
        $section = $item->section;

        DB::transaction(function () use ($item, $request): void {
            $lockedItem = SupplyItem::query()
                ->with('inventoryItem')
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            $inventoryItem = $lockedItem->inventoryItem;
            $isAttachedInventoryItem = $lockedItem->section === SupplyItem::SECTION_MAINTENANCE_STOREROOM;

            $lockedItem->delete();

            if (! $isAttachedInventoryItem && $inventoryItem) {
                $inventoryItem->update([
                    'active' => false,
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'message' => $section === SupplyItem::SECTION_MAINTENANCE_STOREROOM
                ? 'Artículo retirado del pañol. Su ficha, fotografía e historial permanecen conservados en Inventario.'
                : 'Producto eliminado del registro. Su ficha, fotografía, existencias y movimientos se conservaron para trazabilidad.',
            'archived' => true,
        ]);
    }

    public function storePhoto(Request $request, SupplyItem $item): JsonResponse
    {
        $payload = $request->validate([
            'photo' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/x-heic,image/x-heif'],
        ]);
        $this->replacePhoto($item, $payload['photo']);

        return response()->json([
            'message' => 'Foto de referencia actualizada.',
            'data' => $this->loadItem($item),
        ]);
    }

    public function photo(SupplyItem $item): StreamedResponse
    {
        abort_unless($item->reference_photo_path, 404);
        abort_unless(Storage::disk('local')->exists($item->reference_photo_path), 404);

        return Storage::disk('local')->response($item->reference_photo_path);
    }

    private function validatedSection(Request $request): string
    {
        return validator($request->query(), [
            'section' => ['required', Rule::in(SupplyItem::sections())],
        ])->validate()['section'];
    }

    private function categoryForSection(string $section): InventoryCategory
    {
        $definition = match ($section) {
            SupplyItem::SECTION_CLEANING => ['name' => 'Insumos de aseo', 'slug' => 'insumos-aseo', 'code_prefix' => 'ASEO'],
            SupplyItem::SECTION_HEATING => ['name' => 'Combustibles y calefacción', 'slug' => 'combustibles-calefaccion', 'code_prefix' => 'CALEF'],
            SupplyItem::SECTION_MAINTENANCE_STOREROOM => ['name' => 'Pañol de mantenimiento', 'slug' => 'panol-mantenimiento', 'code_prefix' => 'PANOL'],
        };

        return InventoryCategory::query()->firstOrCreate(
            ['slug' => $definition['slug']],
            $definition + ['description' => 'Categoría vinculada al módulo de Abastecimiento.', 'active' => true],
        );
    }

    private function replacePhoto(SupplyItem $item, UploadedFile $photo): void
    {
        $extension = $photo->extension() ?: $photo->getClientOriginalExtension() ?: 'jpg';
        $path = Storage::disk('local')->putFileAs(
            "supplies/items/{$item->id}",
            $photo,
            'reference_'.now()->format('Ymd_His').'_'.uniqid().'.'.$extension,
        );

        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'No se pudo guardar la foto de referencia.']);
        }

        $previous = $item->reference_photo_path;
        $item->update(['reference_photo_path' => $path]);

        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }
    }

    private function loadItem(SupplyItem $item): SupplyItem
    {
        return $item->fresh()->load([
            'storeroom:id,code,name',
            'inventoryItem:id,code,name,description,category_id,supplier_id,item_type,stock_quantity,minimum_stock,unit_of_measure,active,image_path,updated_at',
            'inventoryItem.supplier:id,name,business_name,rut',
        ]);
    }
}
