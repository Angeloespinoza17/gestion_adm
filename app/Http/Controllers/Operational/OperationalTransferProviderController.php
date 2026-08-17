<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Models\Operational\OperationalTransferProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalTransferProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('ver_traslados_operativos') || $request->user()->isSuperAdmin(), 403);
        $providers = OperationalTransferProvider::query()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('active', true))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $providers]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $provider = OperationalTransferProvider::create($this->payload($request) + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Proveedor creado.', 'data' => $provider], 201);
    }

    public function update(Request $request, OperationalTransferProvider $provider): JsonResponse
    {
        $this->authorizeManagement($request);
        $provider->update($this->payload($request, $provider) + ['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Proveedor actualizado.', 'data' => $provider->fresh()]);
    }

    public function destroy(Request $request, OperationalTransferProvider $provider): JsonResponse
    {
        $this->authorizeManagement($request);
        $provider->forceFill(['active' => false, 'updated_by' => $request->user()->id])->save();

        return response()->json(['message' => 'Proveedor desactivado.']);
    }

    private function payload(Request $request, ?OperationalTransferProvider $provider = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:30', Rule::unique('operational_transfer_providers', 'rut')->ignore($provider?->id)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()->hasPermission('administrar_proveedores_traslados') || $request->user()->isSuperAdmin(), 403);
    }
}
