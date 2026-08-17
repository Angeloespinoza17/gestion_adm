<?php

namespace App\Http\Controllers\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\StoreOperationalTransferDocumentRequest;
use App\Models\Operational\OperationalTransferDocument;
use App\Models\Operational\OperationalTransferRequest;
use App\Services\Operational\OperationalTransferAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalTransferDocumentController extends Controller
{
    public function __construct(private readonly OperationalTransferAccessService $access) {}

    public function store(StoreOperationalTransferDocumentRequest $request, OperationalTransferRequest $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);
        $canManage = $this->access->canManage($request->user());
        $isOwner = (int) $transfer->requested_by_user_id === (int) $request->user()->id
            || ((int) $transfer->requester_staff_id === (int) $request->user()->staff_id);
        abort_unless($canManage || ($isOwner && $transfer->isEditable()), 403);

        $payload = $request->validated();
        if (! $canManage && $payload['document_type'] !== 'solicitud_pedagogica') {
            throw ValidationException::withMessages(['document_type' => 'El solicitante solo puede adjuntar la solicitud pedagógica.']);
        }
        if (! empty($payload['quote_id']) && ! $transfer->quotes()->whereKey($payload['quote_id'])->exists()) {
            throw ValidationException::withMessages(['quote_id' => 'La cotización no pertenece a esta solicitud.']);
        }

        $file = $request->file('document');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs(
            'operational-transfers/'.$transfer->id.'/documents',
            now()->format('Ymd_His').'_'.uniqid().'_'.$safeName,
            'local',
        );
        $document = $transfer->documents()->create([
            'quote_id' => $payload['quote_id'] ?? null,
            'uploaded_by_user_id' => $request->user()->id,
            'document_type' => $payload['document_type'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'comments' => $payload['comments'] ?? null,
        ]);
        $transfer->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'documento_adjuntado',
            'old_status' => $transfer->approval_status,
            'new_status' => $transfer->approval_status,
            'details' => ['document_id' => $document->id, 'type' => $document->document_type],
        ]);

        return response()->json(['message' => 'Documento adjuntado.', 'data' => $document], 201);
    }

    public function download(Request $request, OperationalTransferDocument $document): StreamedResponse
    {
        $this->authorize('view', $document->transferRequest);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name, [
            'Content-Type' => $document->file_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroy(Request $request, OperationalTransferDocument $document): JsonResponse
    {
        $transfer = $document->transferRequest;
        $this->authorize('view', $transfer);
        abort_if($document->official_snapshot, 422, 'Una copia oficial no puede eliminarse.');
        $canManage = $this->access->canManage($request->user());
        $isOwner = (int) $transfer->requested_by_user_id === (int) $request->user()->id
            || ((int) $transfer->requester_staff_id === (int) $request->user()->staff_id);
        abort_unless($canManage || ($isOwner && $transfer->isEditable()), 403);

        $path = $document->file_path;
        $documentId = $document->id;
        $document->delete();
        Storage::disk('local')->delete($path);
        $transfer->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'documento_eliminado',
            'old_status' => $transfer->approval_status,
            'new_status' => $transfer->approval_status,
            'details' => ['document_id' => $documentId],
        ]);

        return response()->json(['message' => 'Documento eliminado.']);
    }
}
