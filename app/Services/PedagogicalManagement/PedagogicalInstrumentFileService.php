<?php

namespace App\Services\PedagogicalManagement;

use App\Contracts\PedagogicalManagement\PdfTextExtractorInterface;
use App\Exceptions\PedagogicalManagement\PedagogicalInstrumentException;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;
use App\Models\User;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class PedagogicalInstrumentFileService
{
    public function __construct(private readonly PdfTextExtractorInterface $extractor) {}

    /** @return array<string,mixed> */
    public function inspect(UploadedFile $file, int $schoolId): array
    {
        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new PedagogicalInstrumentException('El archivo está vacío.', 'INSTRUMENT_FILE_EMPTY');
        }
        $maxBytes = max(1, (int) config('pedagogical_management.storage.max_file_kb', 30720)) * 1024;
        if ($size > $maxBytes) {
            throw new PedagogicalInstrumentException('El archivo supera el tamaño máximo permitido.', 'INSTRUMENT_FILE_TOO_LARGE', 422, ['max_bytes' => $maxBytes]);
        }
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'docx'], true)) {
            throw new PedagogicalInstrumentException('Solo se permiten archivos PDF o Word (.docx).', 'INSTRUMENT_FILE_INVALID_EXTENSION');
        }

        $path = $file->getRealPath();
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $formatMetadata = match ($extension) {
            'pdf' => $this->inspectPdf($path, $mime, $size),
            'docx' => $this->inspectDocx($path, $mime, $size),
        };

        $sha256 = hash_file('sha256', $path);
        $duplicate = PedagogicalInstrumentFile::query()->where('school_id', $schoolId)->where('sha256', $sha256)->first();
        if ($duplicate) {
            throw new PedagogicalInstrumentException(
                'Este archivo ya fue importado en el establecimiento.',
                'DUPLICATE_FILE_HASH',
                409,
                ['instrument_uuid' => $duplicate->instrument?->uuid, 'file_uuid' => $duplicate->uuid],
            );
        }

        return [
            ...$formatMetadata,
            'file_size' => $size,
            'sha256' => $sha256,
            'technical_metadata' => [
                ...$formatMetadata['technical_metadata'],
                'extension' => $extension,
                'detected_mime' => $mime,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function inspectPdf(string $path, string $mime, int $size): array
    {
        if (! in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            throw new PedagogicalInstrumentException('El contenido real del archivo no corresponde a un PDF.', 'PDF_INVALID_MIME', 422, ['detected_mime' => $mime]);
        }
        $handle = fopen($path, 'rb');
        $signature = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw new PedagogicalInstrumentException('La firma interna del archivo no corresponde a PDF.', 'PDF_INVALID_SIGNATURE');
        }

        $head = file_get_contents($path, false, null, 0, min($size, 1048576)) ?: '';
        $encrypted = str_contains($head, '/Encrypt');
        $pageCount = null;
        $hasTextLayer = null;
        $preflightError = null;
        if (! $encrypted) {
            try {
                $extracted = $this->extractor->extract($path);
                $pageCount = count($extracted['pages']);
                if ($pageCount < 1) {
                    throw new PedagogicalInstrumentException('El PDF no contiene páginas interpretables.', 'PDF_CORRUPTED');
                }
                $textCharacters = collect($extracted['pages'])->sum(fn (array $page): int => mb_strlen(trim((string) ($page['normalized_text'] ?? ''))));
                $hasTextLayer = $textCharacters >= (int) config('pedagogical_management.analysis.minimum_text_characters', 40);
            } catch (PedagogicalInstrumentException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $preflightError = $exception->getMessage();
                throw new PedagogicalInstrumentException(
                    'El PDF está corrupto o no puede ser interpretado por el extractor instalado.',
                    'PDF_CORRUPTED',
                    422,
                );
            }
        }

        return [
            'mime_type' => 'application/pdf',
            'page_count' => $pageCount, 'is_encrypted' => $encrypted, 'has_text_layer' => $hasTextLayer,
            'technical_metadata' => [
                'document_type' => 'pdf', 'signature_valid' => true,
                'integrity_checked' => ! $encrypted, 'preflight_error' => $preflightError,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function inspectDocx(string $path, string $mime, int $compressedSize): array
    {
        if (! in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ], true)) {
            throw new PedagogicalInstrumentException(
                'El contenido real del archivo no corresponde a un documento Word .docx.',
                'DOCX_INVALID_MIME',
                422,
                ['detected_mime' => $mime],
            );
        }

        $handle = fopen($path, 'rb');
        $signature = $handle ? fread($handle, 4) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (! in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
            throw new PedagogicalInstrumentException('La firma interna del archivo no corresponde a Word .docx.', 'DOCX_INVALID_SIGNATURE');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new PedagogicalInstrumentException('El documento Word está corrupto o no puede ser interpretado.', 'DOCX_CORRUPTED');
        }

        try {
            if ($zip->numFiles < 3 || $zip->numFiles > 10000) {
                throw new PedagogicalInstrumentException('La estructura interna del documento Word no es válida.', 'DOCX_INVALID_STRUCTURE');
            }

            $totalUncompressed = 0;
            $maxUncompressed = max(50 * 1024 * 1024, $compressedSize * 5);
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if (! is_array($entry)) {
                    throw new PedagogicalInstrumentException('No fue posible verificar la estructura interna del documento Word.', 'DOCX_CORRUPTED');
                }
                $entryName = str_replace('\\', '/', (string) ($entry['name'] ?? ''));
                if ($entryName === '' || str_starts_with($entryName, '/') || preg_match('#(^|/)\.\.(/|$)#', $entryName)) {
                    throw new PedagogicalInstrumentException('El documento Word contiene una ruta interna no permitida.', 'DOCX_UNSAFE_ARCHIVE_PATH');
                }
                if ((int) ($entry['encryption_method'] ?? 0) !== 0) {
                    throw new PedagogicalInstrumentException('No se admiten documentos Word protegidos o cifrados.', 'DOCX_ENCRYPTED');
                }
                $totalUncompressed += max(0, (int) ($entry['size'] ?? 0));
                if ($totalUncompressed > $maxUncompressed) {
                    throw new PedagogicalInstrumentException('El documento Word excede el tamaño interno seguro permitido.', 'DOCX_EXPANDED_SIZE_TOO_LARGE');
                }
            }

            $contentTypes = $zip->getFromName('[Content_Types].xml');
            $documentXml = $zip->getFromName('word/document.xml');
            if (! is_string($contentTypes) || ! is_string($documentXml)
                || ! str_contains($contentTypes, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')
                || ! str_contains($documentXml, '<w:document')) {
                throw new PedagogicalInstrumentException('El archivo no contiene la estructura principal de un documento Word .docx.', 'DOCX_INVALID_STRUCTURE');
            }
            if ($zip->locateName('word/vbaProject.bin', ZipArchive::FL_NOCASE) !== false
                || str_contains(mb_strtolower($contentTypes), 'macroenabled')) {
                throw new PedagogicalInstrumentException('No se admiten documentos Word con macros.', 'DOCX_MACROS_NOT_ALLOWED');
            }

            $visibleText = preg_replace('/<w:tab\b[^>]*\/>/u', "\t", $documentXml) ?? $documentXml;
            $visibleText = preg_replace('/<\/w:p>/u', "\n", $visibleText) ?? $visibleText;
            $visibleText = html_entity_decode(strip_tags($visibleText), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $hasTextLayer = mb_strlen(trim(preg_replace('/\s+/u', ' ', $visibleText) ?? ''))
                >= (int) config('pedagogical_management.analysis.minimum_text_characters', 40);

            return [
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'page_count' => null,
                'is_encrypted' => false,
                'has_text_layer' => $hasTextLayer,
                'technical_metadata' => [
                    'document_type' => 'docx',
                    'signature_valid' => true,
                    'integrity_checked' => true,
                    'archive_entries' => $zip->numFiles,
                    'uncompressed_size' => $totalUncompressed,
                    'contains_macros' => false,
                ],
            ];
        } finally {
            $zip->close();
        }
    }

    /** @param array<string,mixed> $metadata */
    public function store(PedagogicalInstrument $instrument, UploadedFile $file, array $metadata, User $actor): PedagogicalInstrumentFile
    {
        $disk = (string) config('pedagogical_management.storage.disk', 'local');
        $root = trim((string) config('pedagogical_management.storage.root', 'private/pedagogical-management/instruments'), '/');
        $version = ((int) $instrument->files()->max('version')) + 1;
        $extension = (string) ($metadata['technical_metadata']['extension'] ?? 'bin');
        $internalName = (string) Str::uuid().'.'.$extension;
        $directory = $root.'/'.$instrument->school_id.'/'.$instrument->uuid.'/v'.$version;
        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $internalName);
        if (! $storedPath) {
            throw new PedagogicalInstrumentException('No fue posible conservar el archivo en almacenamiento privado.', 'INSTRUMENT_FILE_STORAGE_FAILED', 500);
        }

        try {
            return PedagogicalInstrumentFile::query()->create([
                'school_id' => $instrument->school_id,
                'instrument_id' => $instrument->id,
                'version' => $version,
                'original_filename' => mb_substr($this->safeOriginalName($file->getClientOriginalName()), 0, 191),
                'internal_filename' => $internalName,
                'storage_disk' => $disk,
                'storage_path' => $storedPath,
                'mime_type' => $metadata['mime_type'],
                'file_size' => $metadata['file_size'],
                'sha256' => $metadata['sha256'],
                'page_count' => $metadata['page_count'],
                'is_encrypted' => $metadata['is_encrypted'],
                'has_text_layer' => $metadata['has_text_layer'],
                'technical_metadata' => $metadata['technical_metadata'],
                'uploaded_by' => $actor->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);
            throw $exception;
        }
    }

    public function absolutePath(PedagogicalInstrumentFile $file): string
    {
        $disk = Storage::disk($file->storage_disk);
        if (! $disk->exists($file->storage_path)) {
            throw new PedagogicalInstrumentException('La copia privada del archivo no está disponible.', 'INSTRUMENT_FILE_PRIVATE_COPY_MISSING', 404);
        }

        return $disk->path($file->storage_path);
    }

    private function safeOriginalName(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', basename($name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'instrumento';

        return trim($name) !== '' ? trim($name) : 'instrumento';
    }
}
