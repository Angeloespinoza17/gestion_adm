<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\PreviewContractClauseRequest;
use App\Http\Requests\Contracts\StoreContractClauseRequest;
use App\Http\Requests\Contracts\UpdateContractClauseRequest;
use App\Models\ContractClause;
use App\Services\Contracts\ContractRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractClauseController extends Controller
{
    public function __construct(private readonly ContractRenderer $renderer)
    {
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'clause_types' => ContractClause::query()
                ->whereNotNull('clause_type')
                ->select('clause_type')
                ->distinct()
                ->orderBy('clause_type')
                ->pluck('clause_type')
                ->values(),
            'available_variables' => $this->renderer->availableVariables(),
            'preview_variables' => $this->renderer->previewVariableMap(),
        ]);
    }

    public function preview(PreviewContractClauseRequest $request): JsonResponse
    {
        $payload = $request->validated();

        return response()->json([
            'data' => [
                'content' => $this->renderer->renderClausePreview(
                    (string) ($payload['title'] ?? ''),
                    (string) ($payload['content'] ?? '')
                ),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');
        $type = trim((string) $request->query('clause_type'));

        $clauses = ContractClause::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('clause_type', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('clause_type', $type));

        if ($active !== null && $active !== '') {
            $clauses->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $clauses
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function store(StoreContractClauseRequest $request): JsonResponse
    {
        $clause = ContractClause::query()->create($request->validated());

        return response()->json([
            'message' => 'Cláusula creada correctamente.',
            'data' => $clause,
        ], 201);
    }

    public function show(ContractClause $contractClause): JsonResponse
    {
        return response()->json([
            'data' => $contractClause->load('templates:id,name'),
        ]);
    }

    public function update(UpdateContractClauseRequest $request, ContractClause $contractClause): JsonResponse
    {
        $contractClause->update($request->validated());

        return response()->json([
            'message' => 'Cláusula actualizada correctamente.',
            'data' => $contractClause->fresh(),
        ]);
    }

    public function destroy(ContractClause $contractClause): JsonResponse
    {
        $contractClause->delete();

        return response()->json([
            'message' => 'Cláusula eliminada correctamente.',
        ]);
    }

    public function setActive(Request $request, ContractClause $contractClause): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $contractClause->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado de la cláusula actualizado correctamente.',
            'data' => $contractClause->fresh(),
        ]);
    }
}
