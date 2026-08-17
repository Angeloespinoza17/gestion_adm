<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Attachment;
use App\Models\LibroDigital\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateAttachmentService
{
    public function __construct(private readonly AuditEventWriter $audit) {}

    public function store(School $school, Model $owner, UploadedFile $file, User $actor, string $category = 'evidence', string $confidentiality = 'restricted'): Attachment
    {
        if (! $file->isValid()) {
            throw new LibroDigitalException('El archivo no se recibió correctamente.', 'LCD_ATTACHMENT_INVALID');
        }
        $maxBytes = (int) config('libro_digital.storage.max_file_kb', 20480) * 1024;
        if (($file->getSize() ?: 0) > $maxBytes) {
            throw new LibroDigitalException('El archivo supera el tamaño permitido.', 'LCD_ATTACHMENT_TOO_LARGE');
        }
        $detectedMime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        if (! in_array($detectedMime, (array) config('libro_digital.storage.allowed_mimes', []), true)) {
            throw new LibroDigitalException('El tipo real del archivo no está permitido.', 'LCD_ATTACHMENT_MIME_NOT_ALLOWED');
        }

        $contents = $file->get();
        $sha256 = hash('sha256', $contents);
        $safeName = Str::ulid().'.enc';
        $path = trim((string) config('libro_digital.storage.root', 'private/libro-digital'), '/')
            .'/attachments/'.$school->public_id.'/'.$safeName;
        $ciphertext = Crypt::encryptString($contents);
        if (! Storage::disk((string) config('libro_digital.storage.disk', 'local'))->put($path, $ciphertext)) {
            throw new LibroDigitalException('No se pudo guardar el archivo privado.', 'LCD_ATTACHMENT_STORAGE_FAILED', 500);
        }

        $attachment = Attachment::query()->firstOrCreate([
            'school_id' => $school->id,
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'sha256' => $sha256,
        ], [
            'category' => $category,
            'original_name' => mb_strimwidth($file->getClientOriginalName(), 0, 250),
            'safe_name' => $safeName,
            'detected_mime_type' => $detectedMime,
            'extension' => mb_strtolower((string) $file->guessExtension()),
            'size_bytes' => strlen($contents),
            'disk' => (string) config('libro_digital.storage.disk', 'local'),
            'private_path' => $path,
            'encrypted' => true,
            'encryption_metadata' => ['scheme' => 'laravel-aead', 'key_reference' => 'APP_KEY'],
            'confidentiality_level' => $confidentiality,
            'malware_scan_status' => config('libro_digital.attachments.scan_driver') === 'disabled' ? 'unavailable' : 'pending',
            'uploaded_by' => $actor->id,
            'uploaded_at' => now('UTC'),
        ]);

        if (! $attachment->wasRecentlyCreated) {
            Storage::disk((string) config('libro_digital.storage.disk', 'local'))->delete($path);
        }
        $this->audit->write('lcd.attachment.stored', 'upload', $attachment, actor: $actor, schoolId: $school->id, after: $attachment->only(['public_id', 'category', 'detected_mime_type', 'size_bytes', 'sha256', 'confidentiality_level', 'malware_scan_status']));

        return $attachment;
    }

    public function contents(Attachment $attachment): string
    {
        $ciphertext = Storage::disk($attachment->disk)->get($attachment->private_path);
        $contents = Crypt::decryptString($ciphertext);
        if (! hash_equals((string) $attachment->sha256, hash('sha256', $contents))) {
            throw new LibroDigitalException('El archivo no supera la verificación de integridad.', 'LCD_ATTACHMENT_HASH_MISMATCH', 409);
        }

        return $contents;
    }
}
