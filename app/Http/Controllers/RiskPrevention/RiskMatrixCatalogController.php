<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Department;
use App\Models\MaintenanceDependency;
use App\Models\RiskPrevention\RiskCatalogItem;
use App\Models\RiskPrevention\RiskMethodology;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskMatrixCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.view'), 403);
        $methodologies = RiskMethodology::query()->orderBy('code')->orderByDesc('version_number')->get();
        $methodology = $request->integer('methodology_id')
            ? $methodologies->firstWhere('id', $request->integer('methodology_id'))
            : $methodologies->first(fn ($item) => $item->code === config('risk_matrix.methodology_code') && $item->active);
        $items = RiskCatalogItem::query()->where('methodology_id', $methodology?->id)->orderBy('catalog_type')->orderBy('sort_order')->get()->groupBy('catalog_type');

        return response()->json(['data' => [
            'methodologies' => $methodologies, 'active_methodology' => $methodology, 'catalogs' => $items,
            'work_centers' => MaintenanceDependency::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name', 'location']),
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'positions' => Cargo::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'staff' => Staff::query()->where('active', true)->orderBy('full_name')->limit(1000)->get(['id', 'full_name', 'cargo_id']),
            'users' => User::query()->where('active', true)->orderBy('name')->limit(1000)->get(['id', 'name', 'email', 'staff_id']),
            'permissions' => $request->user()->permissionSlugs(),
            'company' => config('risk_matrix.company'),
        ]]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.manage-catalogs'), 403);
        $data = $request->validate([
            'methodology_id' => ['required', 'integer', 'exists:prevent_risk_methodologies,id'],
            'catalog_type' => ['required', 'string', 'max:60'], 'code' => ['required', 'alpha_dash', 'max:100'],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'sort_order' => ['nullable', 'integer', 'min:0'],
            'configuration' => ['nullable', 'array'], 'valid_from' => ['nullable', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);
        $item = RiskCatalogItem::query()->create([...$data, 'active' => true]);

        return response()->json(['message' => 'Elemento de catálogo creado.', 'data' => $item], 201);
    }

    public function toggleItem(Request $request, RiskCatalogItem $item): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.manage-catalogs'), 403);
        $data = $request->validate(['active' => ['required', 'boolean'], 'valid_until' => ['nullable', 'date']]);
        $item->update($data);

        return response()->json(['message' => 'Vigencia actualizada sin eliminar historial.', 'data' => $item]);
    }

    public function createMethodologyVersion(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.manage-catalogs'), 403);
        $data = $request->validate([
            'source_methodology_id' => ['required', 'integer', 'exists:prevent_risk_methodologies,id'],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'valid_from' => ['required', 'date'], 'configuration' => ['nullable', 'array'],
        ]);
        $source = RiskMethodology::query()->findOrFail($data['source_methodology_id']);
        $methodology = DB::transaction(function () use ($source, $data) {
            $new = RiskMethodology::query()->create([
                'code' => $source->code, 'version_number' => RiskMethodology::query()->where('code', $source->code)->max('version_number') + 1,
                'name' => $data['name'], 'description' => $data['description'] ?? $source->description,
                'valid_from' => $data['valid_from'], 'active' => true, 'configuration' => $data['configuration'] ?? $source->configuration,
            ]);
            foreach ($source->catalogItems as $item) {
                $clone = $item->replicate(['methodology_id', 'parent_id']);
                $clone->methodology_id = $new->id;
                $clone->parent_id = null;
                $clone->save();
            }
            $source->update(['active' => false, 'valid_until' => now()->subDay()->toDateString()]);

            return $new;
        });

        return response()->json(['message' => 'Nueva versión metodológica creada; el historial previo permanece intacto.', 'data' => $methodology], 201);
    }
}
