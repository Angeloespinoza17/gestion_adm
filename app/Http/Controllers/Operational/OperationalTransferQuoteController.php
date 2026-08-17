<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Models\Operational\OperationalTransferQuote;
use App\Models\Operational\OperationalTransferRequest;
use App\Services\Operational\OperationalTransferWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OperationalTransferQuoteController extends Controller
{
    public function __construct(private readonly OperationalTransferWorkflowService $workflow) {}

    public function store(Request $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        $payload = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:operational_transfer_providers,id'],
            'amount' => ['required', 'integer', 'min:0', 'max:999999999'],
            'valid_until' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        return response()->json([
            'message' => 'Cotización registrada.',
            'data' => $this->workflow->addQuote($transfer, $payload, $request->user()),
        ], 201);
    }

    public function select(Request $request, OperationalTransferRequest $transfer, OperationalTransferQuote $quote): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);

        return response()->json([
            'message' => 'Cotización seleccionada.',
            'data' => $this->workflow->selectQuote($transfer, $quote, $request->user()),
        ]);
    }

    public function destroy(Request $request, OperationalTransferRequest $transfer, OperationalTransferQuote $quote): JsonResponse
    {
        $this->authorize('manage', OperationalTransferRequest::class);
        if ((int) $quote->operational_transfer_request_id !== (int) $transfer->id) {
            throw ValidationException::withMessages(['quote' => 'La cotización no pertenece a esta solicitud.']);
        }
        if ($quote->selected || $transfer->approval_status === 'aprobado') {
            throw ValidationException::withMessages(['quote' => 'No se puede eliminar una cotización seleccionada o ya aprobada.']);
        }
        $quote->delete();
        $transfer->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'cotizacion_eliminada',
            'old_status' => $transfer->approval_status,
            'new_status' => $transfer->approval_status,
            'details' => ['quote_id' => $quote->id],
        ]);

        return response()->json(['message' => 'Cotización eliminada.']);
    }
}
