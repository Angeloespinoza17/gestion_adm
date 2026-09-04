<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyStoreroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplyStoreroomController extends Controller
{
    public function index(): JsonResponse
    {
        $storerooms = SupplyStoreroom::query()
            ->where('active', true)
            ->withCount([
                'items as items_count' => fn ($query) => $query
                    ->where('section', SupplyItem::SECTION_MAINTENANCE_STOREROOM),
            ])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description', 'active']);

        return response()->json(['data' => $storerooms]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('supply_storerooms', 'code')],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $code = filled($payload['code'] ?? null)
            ? Str::upper(trim($payload['code']))
            : $this->nextCode($payload['name']);

        $storeroom = SupplyStoreroom::query()->create([
            'code' => $code,
            'name' => trim($payload['name']),
            'description' => $payload['description'] ?? null,
            'active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Bodega del pañol creada correctamente.',
            'data' => $storeroom->setAttribute('items_count', 0),
        ], 201);
    }

    public function candidates(Request $request): JsonResponse
    {
        $payload = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'item_type' => ['nullable', Rule::in(['asset', 'consumable'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ])->validate();
        $search = trim((string) ($payload['search'] ?? ''));
        $itemType = $payload['item_type'] ?? null;

        $items = InventoryItem::query()
            ->select([
                'id', 'code', 'name', 'description', 'category_id', 'dependency_id',
                'brand', 'model', 'serial_number', 'status', 'condition', 'item_type',
                'stock_quantity', 'unit_of_measure', 'image_path', 'active', 'updated_at',
            ])
            ->with([
                'category:id,name',
                'dependency:id,code,name',
            ])
            ->where('active', true)
            ->whereDoesntHave('supplyItem')
            ->when($itemType, fn ($query) => $query->where('item_type', $itemType))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($payload['per_page'] ?? 24));

        $data = collect($items->items())->map(function (InventoryItem $item): array {
            $serialized = $item->toArray();
            $serialized['image_url'] = $item->image_path
                ? "/api/supplies/inventory-items/{$item->id}/image?v=".($item->updated_at?->timestamp ?: time())
                : null;
            $serialized['available_quantity'] = $item->item_type === 'asset'
                ? 1
                : (float) ($item->stock_quantity ?? 0);

            return $serialized;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function attachItems(Request $request, SupplyStoreroom $storeroom): JsonResponse
    {
        abort_unless($storeroom->active, 422, 'La bodega seleccionada no está activa.');
        $payload = $request->validate([
            'inventory_item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'inventory_item_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
        ]);
        $ids = collect($payload['inventory_item_ids'])->map(fn ($id): int => (int) $id)->sort()->values();

        $created = DB::transaction(function () use ($ids, $storeroom): array {
            $items = InventoryItem::query()
                ->whereIn('id', $ids)
                ->where('active', true)
                ->whereDoesntHave('supplyItem')
                ->with('category:id,name')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($items->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'inventory_item_ids' => 'Uno o más artículos ya fueron incorporados, están inactivos o ya no existen.',
                ]);
            }

            return $items->map(function (InventoryItem $item) use ($storeroom): SupplyItem {
                return SupplyItem::query()->create([
                    'inventory_item_id' => $item->id,
                    'section' => SupplyItem::SECTION_MAINTENANCE_STOREROOM,
                    'storeroom_id' => $storeroom->id,
                    'supply_type' => $this->suggestedType($item),
                ]);
            })->all();
        });

        return response()->json([
            'message' => count($created) === 1
                ? 'Artículo incorporado al pañol correctamente.'
                : count($created).' artículos incorporados al pañol correctamente.',
            'attached_count' => count($created),
        ], 201);
    }

    public function inventoryImage(InventoryItem $item): StreamedResponse
    {
        abort_unless($item->active, 404);
        abort_unless($item->image_path && Storage::disk('public')->exists($item->image_path), 404);

        return Storage::disk('public')->response($item->image_path);
    }

    private function nextCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '-')) ?: 'PANOL';
        $base = Str::limit($base, 32, '');
        $code = $base;
        $suffix = 2;

        while (SupplyStoreroom::query()->where('code', $code)->exists()) {
            $code = Str::limit($base, 35, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function suggestedType(InventoryItem $item): string
    {
        $haystack = Str::lower(implode(' ', [
            $item->name,
            $item->description,
            $item->category?->name,
        ]));

        return match (true) {
            Str::contains($haystack, ['eléctric', 'taladro', 'esmeril', 'sierra']) => 'power_tool',
            Str::contains($haystack, ['medici', 'tester', 'multímetro', 'nivel']) => 'measuring_tool',
            Str::contains($haystack, ['repuesto', 'rodamiento', 'válvula']) => 'spare_part',
            Str::contains($haystack, ['seguridad', 'protección', 'casco', 'guante']) => 'safety_item',
            $item->item_type === 'asset' => 'hand_tool',
            default => 'other',
        };
    }
}
