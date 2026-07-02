<?php

namespace App\Http\Controllers\Spaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Spaces\StoreDependencyTypeRequest;
use App\Http\Requests\Spaces\UpdateDependencyTypeRequest;
use App\Models\DependencyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DependencyTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $types = DependencyType::query()
            ->withCount('dependencies')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $types->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $types
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreDependencyTypeRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['slug'] = $this->generateSlug((string) $payload['name']);

        $type = DependencyType::query()->create($payload);

        return response()->json([
            'message' => 'Tipo de dependencia creado correctamente.',
            'data' => $type,
        ], 201);
    }

    public function show(DependencyType $dependencyType): JsonResponse
    {
        return response()->json([
            'data' => $dependencyType->loadCount('dependencies'),
        ]);
    }

    public function update(UpdateDependencyTypeRequest $request, DependencyType $dependencyType): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->generateSlug((string) $payload['name'], $dependencyType->id);
        }

        $dependencyType->update($payload);

        return response()->json([
            'message' => 'Tipo de dependencia actualizado correctamente.',
            'data' => $dependencyType->fresh()->loadCount('dependencies'),
        ]);
    }

    public function destroy(DependencyType $dependencyType): JsonResponse
    {
        if ($dependencyType->dependencies()->exists()) {
            return response()->json([
                'message' => 'No es posible eliminar un tipo que tiene dependencias asociadas.',
            ], 422);
        }

        $dependencyType->delete();

        return response()->json([
            'message' => 'Tipo de dependencia eliminado correctamente.',
        ]);
    }

    private function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tipo-dependencia';
        $slug = $base;
        $counter = 2;

        while (
            DependencyType::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
