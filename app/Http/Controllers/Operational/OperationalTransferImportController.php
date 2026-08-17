<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Services\Operational\OperationalTransferSpreadsheetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalTransferImportController extends Controller
{
    public function __construct(private readonly OperationalTransferSpreadsheetImportService $importer) {}

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeImport($request);
        $payload = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx'],
        ]);

        return response()->json([
            'message' => 'Vista previa generada. Revisa las advertencias antes de confirmar.',
            'data' => $this->importer->preview($payload['file'], $request->user()),
        ]);
    }

    public function commit(Request $request): JsonResponse
    {
        $this->authorizeImport($request);
        $payload = $request->validate([
            'token' => ['required', 'uuid'],
            'rows' => ['nullable', 'array'],
            'rows.*' => ['integer', 'min:2'],
        ]);

        return response()->json([
            'message' => 'Importación histórica completada.',
            'data' => $this->importer->commit($payload['token'], $request->user(), $payload['rows'] ?? null),
        ]);
    }

    private function authorizeImport(Request $request): void
    {
        abort_unless($request->user()->hasPermission('importar_traslados_operativos') || $request->user()->isSuperAdmin(), 403);
    }
}
