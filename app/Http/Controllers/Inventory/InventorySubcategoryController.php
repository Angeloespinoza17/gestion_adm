<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventorySubcategoryRequest;
use App\Http\Requests\Inventory\UpdateInventorySubcategoryRequest;
use App\Models\InventorySubcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventorySubcategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');

        $query = InventorySubcategory::query()
            ->with('category:id,name,slug,code_prefix')
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $subcategories = $query
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($subcategories);
    }

    public function store(StoreInventorySubcategoryRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['slug'] = Str::slug((string) ($payload['slug'] ?? $payload['name']));

        $subcategory = InventorySubcategory::create($payload);

        return response()->json([
            'message' => 'Subcategoría creada correctamente.',
            'data' => $subcategory->load('category:id,name,slug,code_prefix'),
        ], 201);
    }

    public function show(InventorySubcategory $subcategory): JsonResponse
    {
        return response()->json([
            'data' => $subcategory->load('category:id,name,slug,code_prefix'),
        ]);
    }

    public function update(UpdateInventorySubcategoryRequest $request, InventorySubcategory $subcategory): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('name', $payload) && !array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }

        if (array_key_exists('slug', $payload) && $payload['slug']) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }

        $subcategory->update($payload);

        return response()->json([
            'message' => 'Subcategoría actualizada correctamente.',
            'data' => $subcategory->fresh()->load('category:id,name,slug,code_prefix'),
        ]);
    }

    public function destroy(InventorySubcategory $subcategory): JsonResponse
    {
        $subcategory->delete();

        return response()->json([
            'message' => 'Subcategoría eliminada correctamente.',
        ]);
    }
}

