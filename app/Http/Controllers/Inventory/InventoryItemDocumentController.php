<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDocument;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InventoryItemDocumentController extends Controller
{
    public function store(Request $request, InventoryItem $item): JsonResponse
    {
        $payload = $request->validate([
            'document' => ['required', 'file', 'max:20480'],
            'document_type' => ['nullable', 'string', 'max:191'],
            'observations' => ['nullable', 'string'],
        ]);

        /** @var UploadedFile $file */
        $file = $payload['document'];

        $path = $file->storePubliclyAs(
            sprintf('inventory/items/%d/documents', $item->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        $doc = InventoryDocument::create([
            'inventory_item_id' => $item->id,
            'document_type' => $payload['document_type'] ?? 'Otro',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'observations' => $payload['observations'] ?? null,
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Documento cargado correctamente.',
            'data' => $doc,
        ], 201);
    }

    public function destroy(InventoryDocument $document): JsonResponse
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

