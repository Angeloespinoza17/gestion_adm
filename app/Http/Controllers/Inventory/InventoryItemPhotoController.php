<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryItemPhotoController extends Controller
{
    public function store(Request $request, InventoryItem $item): JsonResponse
    {
        $payload = $request->validate([
            'photo' => ['required', 'file', 'image', 'max:10240'],
            'is_main' => ['sometimes', 'boolean'],
        ]);

        /** @var UploadedFile $photo */
        $photo = $payload['photo'];

        $extension = $photo->extension() ?: $photo->getClientOriginalExtension() ?: 'jpg';
        $path = Storage::disk('public')->putFileAs(
            sprintf('inventory/items/%d/photos', $item->id),
            $photo,
            now()->format('Ymd_His') . '_' . uniqid() . '.' . $extension,
            ['visibility' => 'public']
        );

        if (!$path) {
            throw ValidationException::withMessages([
                'photo' => 'No se pudo guardar la foto del bien. Revisa permisos de storage en producción.',
            ]);
        }

        $isMain = (bool) ($payload['is_main'] ?? false);

        if ($isMain) {
            InventoryPhoto::query()
                ->where('inventory_item_id', $item->id)
                ->update(['is_main' => false]);

            $item->update(['image_path' => $path]);
        }

        $record = InventoryPhoto::create([
            'inventory_item_id' => $item->id,
            'image_path' => $path,
            'original_name' => $photo->getClientOriginalName(),
            'is_main' => $isMain,
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Foto cargada correctamente.',
            'data' => $record,
        ], 201);
    }

    public function destroy(InventoryPhoto $photo): JsonResponse
    {
        $item = $photo->item;
        $wasMain = (bool) $photo->is_main;
        $path = $photo->image_path;

        $photo->delete();

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        if ($item && $wasMain) {
            $nextMain = InventoryPhoto::query()
                ->where('inventory_item_id', $item->id)
                ->orderByDesc('is_main')
                ->orderByDesc('id')
                ->first();

            if ($nextMain) {
                $nextMain->update(['is_main' => true]);
                $item->update(['image_path' => $nextMain->image_path]);
            } else {
                $item->update(['image_path' => null]);
            }
        }

        return response()->json([
            'message' => 'Foto eliminada correctamente.',
        ]);
    }

    public function image(InventoryPhoto $photo): StreamedResponse
    {
        abort_unless($photo->image_path, 404);
        abort_unless(Storage::disk('public')->exists($photo->image_path), 404);

        return Storage::disk('public')->response($photo->image_path);
    }

    public function setMain(Request $request, InventoryPhoto $photo): JsonResponse
    {
        $item = $photo->item;
        if (!$item) {
            return response()->json([
                'message' => 'El bien asociado a la foto no existe.',
            ], 422);
        }

        InventoryPhoto::query()
            ->where('inventory_item_id', $item->id)
            ->update(['is_main' => false]);

        $photo->update(['is_main' => true]);
        $item->update(['image_path' => $photo->image_path]);

        return response()->json([
            'message' => 'Foto principal actualizada correctamente.',
            'data' => $photo->fresh(),
        ]);
    }
}
