<?php

namespace App\Http\Controllers\HumanResources;

use App\Http\Controllers\Controller;
use App\Services\HumanResources\HrSpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrImportController extends Controller
{
    public function __construct(private readonly HrSpreadsheetImportService $importer) {}

    public function preview(Request $request, string $kind): JsonResponse
    {
        $this->authorizeImport($request, $kind);
        $payload = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:15360']]);

        return response()->json([
            'message' => 'Vista previa lista. Revisa las coincidencias antes de confirmar.',
            'data' => $this->importer->preview($kind, $payload['file'], $request->user()),
        ]);
    }

    public function commit(Request $request, string $kind): JsonResponse
    {
        $this->authorizeImport($request, $kind);
        $payload = $request->validate(['token' => ['required', 'uuid']]);

        return response()->json([
            'message' => 'Precarga histórica completada.',
            'data' => $this->importer->commit($kind, $payload['token'], $request->user()),
        ]);
    }

    private function authorizeImport(Request $request, string $kind): void
    {
        $permission = $kind === 'absences' ? 'rrhh.ausencias.importar' : 'rrhh.seleccion.importar';
        abort_unless(in_array($kind, ['absences', 'recruitment'], true)
            && ($request->user()->isSuperAdmin() || $request->user()->hasPermission($permission)), 403);
    }
}
