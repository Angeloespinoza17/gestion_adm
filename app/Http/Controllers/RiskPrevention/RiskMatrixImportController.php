<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\CommitRiskImportRequest;
use App\Http\Requests\RiskPrevention\PreviewRiskImportRequest;
use App\Models\RiskPrevention\RiskImportBatch;
use App\Services\RiskPrevention\LegacyRiskMatrixImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RiskMatrixImportController extends Controller
{
    public function __construct(private readonly LegacyRiskMatrixImportService $imports) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.import'), 403);
        $batches = RiskImportBatch::query()->where('company_key', config('risk_matrix.company.key'))->withCount('issues')->latest()->paginate(20);

        return response()->json($batches);
    }

    public function preview(PreviewRiskImportRequest $request): JsonResponse
    {
        try {
            $batch = $this->imports->preview($request->file('file'), $request->user(), $request->integer('work_center_id') ?: null);
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureResponse($exception, 'No fue posible analizar el libro Excel. Revise que el archivo sea un XLSX válido e inténtelo nuevamente.');
        }

        return response()->json(['message' => 'Libro analizado sin escribir datos de matriz.', 'data' => $batch], 201);
    }

    public function show(RiskImportBatch $batch, Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.import') && $batch->company_key === config('risk_matrix.company.key'), 404);

        return response()->json(['data' => $batch->load('issues')]);
    }

    public function issues(RiskImportBatch $batch, Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.import') && $batch->company_key === config('risk_matrix.company.key'), 404);
        $query = $batch->issues();
        if ($request->filled('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        return response()->json($query->paginate(100));
    }

    public function commit(CommitRiskImportRequest $request, RiskImportBatch $batch): JsonResponse
    {
        abort_unless($batch->company_key === config('risk_matrix.company.key'), 404);
        try {
            $version = $this->imports->commit($batch, $request->validated(), $request->user());
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureResponse($exception, 'No fue posible confirmar la importación. No se guardó ninguna fila; puede corregir el origen e intentarlo nuevamente.');
        }

        return response()->json(['message' => 'Importación confirmada transaccionalmente.', 'data' => $version]);
    }

    public function cancel(RiskImportBatch $batch, Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('risk-matrix.import') && $batch->company_key === config('risk_matrix.company.key'), 404);
        abort_if($batch->status === 'committed', 409, 'Una importación confirmada no puede cancelarse ni borrar su trazabilidad.');
        Storage::disk('local')->delete($batch->stored_path);
        $batch->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Importación cancelada. El registro de auditoría se conserva.']);
    }

    private function failureResponse(Throwable $exception, string $message): JsonResponse
    {
        $reference = (string) Str::uuid();
        Log::error('Risk matrix import failed.', [
            'reference' => $reference,
            'exception' => $exception,
        ]);

        return response()->json([
            'message' => $message,
            'error_reference' => $reference,
        ], 500);
    }
}
