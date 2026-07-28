<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\BibliotecaCategoria;
use App\Models\Library\BibliotecaSubcategoria;
use App\Models\Library\BibliotecaUbicacion;
use App\Services\Library\BibliotecaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BibliotecaManagementController extends Controller
{
    public function __construct(
        private readonly BibliotecaAccessService $accessService,
    ) {}

    public function categories(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => BibliotecaCategoria::query()
                ->with([
                    'subcategorias' => fn ($query) => $query->withCount('obras'),
                ])
                ->withCount(['obras', 'subcategorias'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageCategories($request->user()), 403);
        $payload = $this->validateCategory($request);
        $payload['slug'] = Str::slug($payload['name']);
        $payload['code'] = strtoupper($payload['code']);
        $payload['created_by'] = $request->user()->id;
        $payload['updated_by'] = $request->user()->id;

        $category = BibliotecaCategoria::query()->create($payload);

        return response()->json(['message' => 'Categoría creada correctamente.', 'data' => $category], 201);
    }

    public function updateCategory(Request $request, BibliotecaCategoria $categoria): JsonResponse
    {
        abort_unless($this->accessService->canManageCategories($request->user()), 403);
        $payload = $this->validateCategory($request, $categoria);
        $payload['slug'] = Str::slug($payload['name']);
        $payload['code'] = strtoupper($payload['code']);
        $payload['updated_by'] = $request->user()->id;
        $categoria->fill($payload)->save();

        return response()->json(['message' => 'Categoría actualizada correctamente.', 'data' => $categoria->fresh()->loadCount('obras')]);
    }

    public function destroyCategory(Request $request, BibliotecaCategoria $categoria): JsonResponse
    {
        abort_unless($this->accessService->canManageCategories($request->user()), 403);

        if ($categoria->obras()->exists() || $categoria->subcategorias()->exists()) {
            throw ValidationException::withMessages([
                'categoria' => 'La categoría tiene títulos o subcategorías asociadas. Reasigna o elimina esos registros antes de continuar.',
            ]);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }

    public function storeSubcategory(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageCategories($request->user()), 403);
        $payload = $this->validateSubcategory($request);
        $payload['slug'] = $this->uniqueSubcategorySlug(
            $payload['name'],
            (int) $payload['biblioteca_categoria_id']
        );
        $payload['created_by'] = $request->user()->id;
        $payload['updated_by'] = $request->user()->id;

        $subcategory = BibliotecaSubcategoria::query()->create($payload);

        return response()->json([
            'message' => 'Subcategoría creada correctamente.',
            'data' => $subcategory->load('categoria:id,name,code')->loadCount('obras'),
        ], 201);
    }

    public function updateSubcategory(
        Request $request,
        BibliotecaSubcategoria $subcategoria
    ): JsonResponse {
        abort_unless($this->accessService->canManageCategories($request->user()), 403);
        $payload = $this->validateSubcategory($request, $subcategoria);
        $payload['slug'] = $this->uniqueSubcategorySlug(
            $payload['name'],
            (int) $payload['biblioteca_categoria_id'],
            $subcategoria->id
        );
        $payload['updated_by'] = $request->user()->id;
        $subcategoria->fill($payload)->save();

        return response()->json([
            'message' => 'Subcategoría actualizada correctamente.',
            'data' => $subcategoria->fresh()->load('categoria:id,name,code')->loadCount('obras'),
        ]);
    }

    public function destroySubcategory(
        Request $request,
        BibliotecaSubcategoria $subcategoria
    ): JsonResponse {
        abort_unless($this->accessService->canManageCategories($request->user()), 403);

        if ($subcategoria->obras()->exists()) {
            throw ValidationException::withMessages([
                'subcategoria' => 'La subcategoría tiene títulos asociados. Reasígnalos antes de eliminarla.',
            ]);
        }

        $subcategoria->delete();

        return response()->json(['message' => 'Subcategoría eliminada correctamente.']);
    }

    public function locations(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return response()->json([
            'data' => BibliotecaUbicacion::query()
                ->with(['parent:id,name,code', 'children'])
                ->withCount(['obras', 'ejemplares'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canManageStorage($request->user()), 403);
        $payload = $this->validateLocation($request);
        $payload['code'] = strtoupper($payload['code']);
        $payload['created_by'] = $request->user()->id;
        $payload['updated_by'] = $request->user()->id;
        $location = BibliotecaUbicacion::query()->create($payload);

        return response()->json(['message' => 'Ubicación creada correctamente.', 'data' => $location], 201);
    }

    public function updateLocation(Request $request, BibliotecaUbicacion $ubicacion): JsonResponse
    {
        abort_unless($this->accessService->canManageStorage($request->user()), 403);
        $payload = $this->validateLocation($request, $ubicacion);

        if (isset($payload['parent_id']) && (int) $payload['parent_id'] === $ubicacion->id) {
            throw ValidationException::withMessages(['parent_id' => 'Una ubicación no puede depender de sí misma.']);
        }

        $payload['code'] = strtoupper($payload['code']);
        $payload['updated_by'] = $request->user()->id;
        $ubicacion->fill($payload)->save();

        return response()->json(['message' => 'Ubicación actualizada correctamente.', 'data' => $ubicacion->fresh(['parent:id,name,code'])]);
    }

    public function destroyLocation(Request $request, BibliotecaUbicacion $ubicacion): JsonResponse
    {
        abort_unless($this->accessService->canManageStorage($request->user()), 403);

        if ($ubicacion->children()->exists() || $ubicacion->obras()->exists() || $ubicacion->ejemplares()->exists()) {
            throw ValidationException::withMessages([
                'ubicacion' => 'La ubicación contiene registros asociados. Debes trasladarlos antes de eliminarla.',
            ]);
        }

        $ubicacion->delete();

        return response()->json(['message' => 'Ubicación eliminada correctamente.']);
    }

    private function validateCategory(Request $request, ?BibliotecaCategoria $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('biblioteca_categorias', 'name')->ignore($category?->id)],
            'code' => ['required', 'string', 'max:20', Rule::unique('biblioteca_categorias', 'code')->ignore($category?->id)],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ]);
    }

    private function validateSubcategory(
        Request $request,
        ?BibliotecaSubcategoria $subcategory = null
    ): array {
        $categoryId = $request->integer('biblioteca_categoria_id');

        return $request->validate([
            'biblioteca_categoria_id' => ['required', 'integer', 'exists:biblioteca_categorias,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('biblioteca_subcategorias', 'name')
                    ->where(fn ($query) => $query->where('biblioteca_categoria_id', $categoryId))
                    ->ignore($subcategory?->id),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
        ]);
    }

    private function uniqueSubcategorySlug(
        string $name,
        int $categoryId,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name) ?: 'subcategoria';
        $slug = $baseSlug;
        $suffix = 2;

        while (BibliotecaSubcategoria::query()
            ->where('biblioteca_categoria_id', $categoryId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function validateLocation(Request $request, ?BibliotecaUbicacion $location = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:biblioteca_ubicaciones,id'],
            'type' => ['required', Rule::in(BibliotecaUbicacion::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('biblioteca_ubicaciones', 'code')->ignore($location?->id)],
            'audience_type' => ['required', Rule::in(BibliotecaUbicacion::AUDIENCE_TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
