<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryRequest;
use App\Models\InventoryCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $query = InventoryCategory::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code_prefix', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        $categories = $query
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($categories);
    }

    public function store(StoreInventoryCategoryRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $payload['slug'] = Str::slug((string) ($payload['slug'] ?? $payload['name']));
        $payload['code_prefix'] = strtoupper(trim((string) $payload['code_prefix']));

        $category = InventoryCategory::create($payload);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'data' => $category,
        ], 201);
    }

    public function show(InventoryCategory $category): JsonResponse
    {
        return response()->json([
            'data' => $category,
        ]);
    }

    public function update(UpdateInventoryCategoryRequest $request, InventoryCategory $category): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('name', $payload) && !array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }

        if (array_key_exists('slug', $payload) && $payload['slug']) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }

        if (array_key_exists('code_prefix', $payload) && $payload['code_prefix']) {
            $payload['code_prefix'] = strtoupper(trim((string) $payload['code_prefix']));
        }

        $category->update($payload);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(InventoryCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }
}

