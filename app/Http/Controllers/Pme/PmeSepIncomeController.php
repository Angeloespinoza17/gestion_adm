<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\SavePmeSepIncomeRequest;
use App\Models\Pme\PmeSepIncome;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PmeSepIncomeController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeSepIncome::query()->with('plan:id,name,school_year')->orderByDesc('school_year')->orderBy('month');
        $query->when($request->query('school_year'), fn ($builder, $year) => $builder->where('school_year', $year));
        $query->when($request->query('month'), fn ($builder, $month) => $builder->where('month', $month));
        $query->when($request->query('state'), fn ($builder, $state) => $builder->where('state', $state));

        return response()->json($query->paginate(15));
    }

    public function store(SavePmeSepIncomeRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canManageIncomes($request->user()), 403);

        $payload = $request->validated();
        if ($request->hasFile('document')) {
            $payload['supporting_document_path'] = $request->file('document')->store('pme-sep/incomes', 'public');
            $payload['supporting_document_name'] = $request->file('document')->getClientOriginalName();
        }

        $income = PmeSepIncome::query()->create(array_merge($payload, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Ingreso SEP registrado correctamente.',
            'data' => $income->fresh('plan:id,name,school_year'),
        ], 201);
    }

    public function update(SavePmeSepIncomeRequest $request, PmeSepIncome $income): JsonResponse
    {
        abort_unless($this->accessService->canManageIncomes($request->user()), 403);

        $payload = $request->validated();
        if ($request->hasFile('document')) {
            if ($income->supporting_document_path) {
                Storage::disk('public')->delete($income->supporting_document_path);
            }
            $payload['supporting_document_path'] = $request->file('document')->store('pme-sep/incomes', 'public');
            $payload['supporting_document_name'] = $request->file('document')->getClientOriginalName();
        }

        $income->update(array_merge($payload, [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Ingreso SEP actualizado correctamente.',
            'data' => $income->fresh('plan:id,name,school_year'),
        ]);
    }

    public function destroy(Request $request, PmeSepIncome $income): JsonResponse
    {
        abort_unless($this->accessService->canManageIncomes($request->user()), 403);

        if ($income->supporting_document_path) {
            Storage::disk('public')->delete($income->supporting_document_path);
        }

        $income->delete();

        return response()->json(['message' => 'Ingreso SEP eliminado correctamente.']);
    }
}
