<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageAttachment;
use App\Models\Messaging\TemporaryUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $max = config('messaging.attachments.max_size_mb') * 1024;
        $data = $request->validate(['file' => ['required', 'file', 'max:'.$max, 'mimes:'.implode(',', config('messaging.attachments.extensions'))]]);
        $file = $data['file'];
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, config('messaging.attachments.mimes'), true), 422, 'El tipo real del archivo no está permitido.');
        $original = basename($file->getClientOriginalName());
        abort_if(substr_count($original, '.') > 1 && preg_match('/\.(php|phtml|phar|js|sh|exe|com|bat)\./i', $original), 422, 'El nombre del archivo no es seguro.');
        $public = (string) Str::ulid();
        $stored = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $disk = config('messaging.attachments.disk');
        $path = $file->storeAs('messaging/temporary/'.$request->user()->id, $stored, $disk);
        $upload = TemporaryUpload::query()->create(['public_id' => $public, 'user_id' => $request->user()->id, 'disk' => $disk, 'path' => $path, 'original_name' => $original, 'stored_name' => $stored, 'mime_type' => $mime, 'size' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath()), 'expires_at' => now()->addMinutes(config('messaging.attachments.temporary_minutes'))]);

        return response()->json(['data' => ['token' => $upload->public_id, 'name' => $original, 'mime_type' => $mime, 'size' => $upload->size, 'expires_at' => $upload->expires_at]], 201);
    }

    public function destroy(Request $request, TemporaryUpload $upload): JsonResponse
    {
        abort_unless($upload->user_id === $request->user()->id && ! $upload->consumed_at, 404);
        Storage::disk($upload->disk)->delete($upload->path);
        $upload->delete();

        return response()->json(['message' => 'Carga eliminada.']);
    }

    public function download(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);
        abort_unless(Storage::disk($attachment->getRawOriginal('disk'))->exists($attachment->getRawOriginal('path')), 404);

        return Storage::disk($attachment->getRawOriginal('disk'))->download($attachment->getRawOriginal('path'), $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'attachment']);
    }
}
