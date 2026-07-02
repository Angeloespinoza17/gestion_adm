<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\PreviewContractTemplateRequest;
use App\Http\Requests\Contracts\StoreContractTemplateRequest;
use App\Http\Requests\Contracts\UpdateContractTemplateRequest;
use App\Models\ContractClause;
use App\Models\ContractTemplate;
use App\Models\Staff;
use App\Services\Contracts\ContractRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractTemplateController extends Controller
{
    public function __construct(private readonly ContractRenderer $renderer)
    {
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'clauses' => ContractClause::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['id', 'title', 'clause_type', 'is_required', 'sort_order', 'active']),
            'contract_types' => Staff::CONTRACT_TYPE_OPTIONS,
            'available_variables' => $this->renderer->availableVariables(),
        ]);
    }

    public function preview(PreviewContractTemplateRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $clauseIds = array_values($payload['clause_ids'] ?? []);

        $clauses = ContractClause::query()
            ->whereIn('id', $clauseIds)
            ->get(['id', 'title', 'content', 'sort_order', 'is_required', 'active'])
            ->sortBy(fn (ContractClause $clause) => array_search($clause->id, $clauseIds, true))
            ->values();

        return response()->json([
            'data' => [
                'content' => $this->renderer->renderTemplatePreview((string) ($payload['body'] ?? ''), $clauses),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $active = $request->query('active');

        $templates = ContractTemplate::query()
            ->with(['clauses:id,title,clause_type,active'])
            ->withCount('contracts')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('contract_type', 'like', "%{$search}%");
                });
            });

        if ($active !== null && $active !== '') {
            $templates->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $templates
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreContractTemplateRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $clauseIds = $payload['clause_ids'] ?? [];

        unset($payload['clause_ids']);
        $payload['slug'] = $this->generateSlug((string) $payload['name']);

        $template = DB::transaction(function () use ($payload, $clauseIds) {
            $template = ContractTemplate::query()->create($payload);
            $this->syncClauses($template, $clauseIds);

            return $template;
        });

        return response()->json([
            'message' => 'Plantilla creada correctamente.',
            'data' => $this->loadTemplate($template),
        ], 201);
    }

    public function show(ContractTemplate $contractTemplate): JsonResponse
    {
        return response()->json([
            'data' => $this->loadTemplate($contractTemplate),
        ]);
    }

    public function update(UpdateContractTemplateRequest $request, ContractTemplate $contractTemplate): JsonResponse
    {
        $payload = $request->validated();
        $clauseIds = $payload['clause_ids'] ?? null;

        unset($payload['clause_ids']);

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->generateSlug((string) $payload['name'], $contractTemplate->id);
        }

        DB::transaction(function () use ($contractTemplate, $payload, $clauseIds) {
            $contractTemplate->update($payload);

            if (is_array($clauseIds)) {
                $this->syncClauses($contractTemplate, $clauseIds);
            }
        });

        return response()->json([
            'message' => 'Plantilla actualizada correctamente.',
            'data' => $this->loadTemplate($contractTemplate->fresh()),
        ]);
    }

    public function destroy(ContractTemplate $contractTemplate): JsonResponse
    {
        $contractTemplate->delete();

        return response()->json([
            'message' => 'Plantilla eliminada correctamente.',
        ]);
    }

    public function setActive(Request $request, ContractTemplate $contractTemplate): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $contractTemplate->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado de la plantilla actualizado correctamente.',
            'data' => $this->loadTemplate($contractTemplate->fresh()),
        ]);
    }

    private function syncClauses(ContractTemplate $template, array $clauseIds): void
    {
        $sync = [];

        foreach (array_values($clauseIds) as $index => $clauseId) {
            $clause = ContractClause::query()->find($clauseId);
            if (!$clause) {
                continue;
            }

            $sync[$clauseId] = [
                'sort_order' => $index + 1,
                'is_required' => $clause->is_required,
            ];
        }

        $template->clauses()->sync($sync);
    }

    private function loadTemplate(ContractTemplate $template): ContractTemplate
    {
        return $template->load([
            'clauses' => fn ($query) => $query->select('contract_clauses.id', 'title', 'clause_type', 'content', 'active', 'sort_order', 'is_required')
                ->orderBy('contract_clause_template.sort_order'),
        ])->loadCount('contracts');
    }

    private function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'plantilla-contrato';
        $slug = $base;
        $counter = 2;

        while (
            ContractTemplate::query()
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
