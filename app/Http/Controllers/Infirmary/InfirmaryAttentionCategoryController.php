<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Models\Infirmary\InfirmaryCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InfirmaryAttentionCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $query = InfirmaryCatalogItem::query()
            ->group(InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY)
            ->withCount('attentions')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $query->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            $query
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);
        $payload['group_key'] = InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY;
        $payload['code'] = $this->normalizeCode(filled($payload['code'] ?? null) ? $payload['code'] : $payload['name']);
        $payload['sort_order'] = $payload['sort_order'] ?? 1;
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $this->ensureUniqueCode($payload['code']);

        $category = InfirmaryCatalogItem::query()->create($payload);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'data' => $category->loadCount('attentions'),
        ], 201);
    }

    public function update(Request $request, InfirmaryCatalogItem $category): JsonResponse
    {
        $this->abortUnlessAttentionCategory($category);

        $payload = $this->validatedPayload($request, $category);
        $payload['code'] = $this->normalizeCode(filled($payload['code'] ?? null) ? $payload['code'] : $payload['name']);
        $payload['sort_order'] = $payload['sort_order'] ?? 1;
        $payload['updated_by'] = $request->user()?->id;

        $this->ensureUniqueCode($payload['code'], $category->id);

        $category->update($payload);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data' => $category->fresh()->loadCount('attentions'),
        ]);
    }

    public function destroy(InfirmaryCatalogItem $category): JsonResponse
    {
        $this->abortUnlessAttentionCategory($category);

        if ($category->attentions()->exists()) {
            return response()->json([
                'message' => 'La categoría ya tiene atenciones asociadas. Desactívala para mantener el historial clínico.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente.',
        ]);
    }

    private function validatedPayload(Request $request, ?InfirmaryCatalogItem $category = null): array
    {
        $codeRule = Rule::unique('infirmary_catalog_items', 'code')
            ->where('group_key', InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY);

        if ($category) {
            $codeRule->ignore($category->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'nullable',
                'string',
                'max:120',
                $codeRule,
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'active' => ['required', 'boolean'],
        ]);
    }

    private function normalizeCode(string $value): string
    {
        return Str::slug($value, '_');
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $exists = InfirmaryCatalogItem::query()
            ->group(InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY)
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        abort_if($exists, 422, 'Ya existe una categoría con ese código.');
    }

    private function abortUnlessAttentionCategory(InfirmaryCatalogItem $category): void
    {
        abort_unless($category->group_key === InfirmaryCatalogItem::GROUP_ATTENTION_CATEGORY, 404);
    }
}
