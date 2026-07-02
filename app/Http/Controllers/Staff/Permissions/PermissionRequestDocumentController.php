<?php

namespace App\Http\Controllers\Staff\Permissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Permissions\StorePermissionRequestDocumentRequest;
use App\Http\Requests\Staff\Permissions\ValidatePermissionRequestDocumentRequest;
use App\Models\PermissionRequest;
use App\Models\PermissionRequestDocument;
use App\Services\Permissions\PermissionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermissionRequestDocumentController extends Controller
{
    public function __construct(
        private readonly PermissionWorkflowService $workflowService,
    ) {
    }

    public function store(StorePermissionRequestDocumentRequest $request, PermissionRequest $permissionRequest): JsonResponse
    {
        if (!$request->user()->can('update', $permissionRequest) && !$request->user()->can('approve', $permissionRequest)) {
            return response()->json(['message' => 'No autorizado para adjuntar documentos a esta solicitud.'], 403);
        }

        /** @var UploadedFile $file */
        $file = $request->file('document');
        $path = $file->storeAs(
            sprintf('permission-requests/%d/documents', $permissionRequest->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            'local'
        );

        $document = $permissionRequest->documents()->create([
            'uploaded_by_user_id' => $request->user()->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'comments' => $request->validated()['comments'] ?? null,
        ]);

        $permissionRequest->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'documento_adjuntado',
            'old_status' => $permissionRequest->status,
            'new_status' => $permissionRequest->status,
            'details' => ['document_id' => $document->id, 'file_name' => $document->file_name],
        ]);

        return response()->json([
            'message' => 'Documento adjuntado correctamente.',
            'data' => $document->load('uploadedByUser:id,name,email'),
        ], 201);
    }

    public function download(PermissionRequestDocument $document): StreamedResponse|JsonResponse
    {
        $permissionRequest = $document->permissionRequest()->firstOrFail();
        $this->authorize('view', $permissionRequest);

        if (!Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'El archivo no está disponible.'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function validateDocument(ValidatePermissionRequestDocumentRequest $request, PermissionRequestDocument $document): JsonResponse
    {
        $permissionRequest = $document->permissionRequest()->firstOrFail();
        $this->authorize('view', $permissionRequest);
        $this->authorize('validateDocuments', PermissionRequest::class);

        $permissionRequest = $this->workflowService->validateAttachment(
            $permissionRequest,
            $document->id,
            $request->user(),
            $request->validated()['validation_status'],
            $request->validated()['comments'] ?? null,
        );

        return response()->json([
            'message' => 'Validación de documento registrada correctamente.',
            'data' => $permissionRequest,
        ]);
    }

    public function destroy(PermissionRequestDocument $document): JsonResponse
    {
        $permissionRequest = $document->permissionRequest()->firstOrFail();

        if (!request()->user()->can('update', $permissionRequest) && !request()->user()->can('validateDocuments', PermissionRequest::class)) {
            return response()->json(['message' => 'No autorizado para eliminar este documento.'], 403);
        }

        $filePath = $document->file_path;
        $document->delete();

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        $permissionRequest->logs()->create([
            'user_id' => request()->user()->id,
            'action' => 'documento_eliminado',
            'old_status' => $permissionRequest->status,
            'new_status' => $permissionRequest->status,
            'details' => ['file_path' => $filePath],
        ]);

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }
}
