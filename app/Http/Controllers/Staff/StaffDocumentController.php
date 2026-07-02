<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffDocumentRequest;
use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StaffDocumentController extends Controller
{
    public function store(StoreStaffDocumentRequest $request, Staff $staff): JsonResponse
    {
        $payload = $request->validated();

        /** @var UploadedFile $file */
        $file = $payload['document'];

        $path = $file->storePubliclyAs(
            sprintf('staff/%d/documents', $staff->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        $document = StaffDocument::query()->create([
            'staff_id' => $staff->id,
            'document_type' => $payload['document_type'] ?? 'Otro',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'observations' => $payload['observations'] ?? null,
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Documento cargado correctamente.',
            'data' => $document->load('uploadedBy:id,name'),
        ], 201);
    }

    public function destroy(StaffDocument $document): JsonResponse
    {
        $path = $document->file_path;
        $document->delete();

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }
}
