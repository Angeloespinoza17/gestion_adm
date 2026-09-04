<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\PedagogicalManagement\ClassPresentationReferenceFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

class ReferenceMaterialService
{
    private const MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown'],
    ];

    public function store(ClassPresentation $presentation, UploadedFile $file, User $uploader): ClassPresentationReferenceFile
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        if (! isset(self::MIME_BY_EXTENSION[$extension]) || ! in_array($mime, self::MIME_BY_EXTENSION[$extension], true)) {
            throw new ClassPresentationGenerationException('El archivo de referencia no tiene una extensión y MIME permitidos.', 'REFERENCE_FILE_TYPE_INVALID', 422);
        }
        if (in_array($extension, ['docx', 'pptx'], true)) {
            $this->assertOfficePackage($file->getPathname(), $extension);
        }

        $maxBytes = (int) config('class_presentations.storage.max_reference_file_kb', 15360) * 1024;
        if ((int) $file->getSize() <= 0 || (int) $file->getSize() > $maxBytes) {
            throw new ClassPresentationGenerationException('El archivo de referencia supera el tamaño máximo permitido.', 'REFERENCE_FILE_SIZE_INVALID', 422);
        }

        $disk = (string) config('class_presentations.storage.disk', 'local');
        $root = trim((string) config('class_presentations.storage.root'), '/');
        $year = (string) data_get($presentation->curricular_snapshot, 'academic_year.year', now()->year);
        $schoolUuid = (string) data_get($presentation->curricular_snapshot, 'school.uuid', $presentation->school_id);
        $directory = "{$root}/{$schoolUuid}/{$year}/{$presentation->uuid}/v{$presentation->version}/references";
        $safeName = (string) Str::uuid().'.'.$extension;
        $path = Storage::disk($disk)->putFileAs($directory, $file, $safeName);
        if (! is_string($path) || $path === '') {
            throw new ClassPresentationGenerationException('No fue posible guardar el material de referencia.', 'REFERENCE_FILE_STORE_FAILED');
        }

        return $presentation->referenceFiles()->create([
            'disk' => $disk, 'path' => $path,
            'original_filename' => Str::limit(basename($file->getClientOriginalName()), 191, ''),
            'mime_type' => $mime, 'size' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getPathname()), 'uploaded_by' => $uploader->id,
        ]);
    }

    /** @return list<array{name:string,content:string}> */
    public function extractAll(ClassPresentation $presentation): array
    {
        $perFile = (int) config('class_presentations.storage.max_extracted_characters_per_file', 30000);
        $totalLimit = (int) config('class_presentations.storage.max_extracted_characters_total', 70000);
        $remaining = $totalLimit;
        $result = [];
        foreach ($presentation->referenceFiles as $file) {
            if ($remaining <= 0) {
                break;
            }
            $content = $this->sanitize($this->extract($file));
            $content = mb_substr($content, 0, min($perFile, $remaining));
            $remaining -= mb_strlen($content);
            if ($content !== '') {
                $result[] = ['name' => $file->original_filename, 'content' => $content];
            }
        }

        return $result;
    }

    public function deleteStored(ClassPresentationReferenceFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
    }

    private function extract(ClassPresentationReferenceFile $file): string
    {
        $bytes = Storage::disk($file->disk)->get($file->path);
        $extension = Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            return (new Parser)->parseContent($bytes)->getText();
        }
        if (in_array($extension, ['txt', 'md'], true)) {
            return $bytes;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'class-reference-');
        if (! is_string($temporary)) {
            throw new ClassPresentationGenerationException('No fue posible preparar el material de referencia.', 'REFERENCE_TEMP_FAILED');
        }
        try {
            file_put_contents($temporary, $bytes);

            return $this->extractOfficeXml($temporary, $extension);
        } finally {
            @unlink($temporary);
        }
    }

    private function extractOfficeXml(string $path, string $extension): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ClassPresentationGenerationException('El material Office no es un paquete ZIP válido.', 'REFERENCE_OFFICE_INVALID', 422);
        }
        try {
            $prefix = $extension === 'docx' ? 'word/' : 'ppt/slides/';
            $parts = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if (! str_starts_with($name, $prefix) || ! str_ends_with($name, '.xml')) {
                    continue;
                }
                $xml = $zip->getFromIndex($index);
                if (is_string($xml)) {
                    $parts[] = html_entity_decode(strip_tags(str_replace(['</w:p>', '</a:p>'], "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }

            return implode("\n", $parts);
        } finally {
            $zip->close();
        }
    }

    private function assertOfficePackage(string $path, string $extension): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new ClassPresentationGenerationException('El archivo Office no es válido.', 'REFERENCE_OFFICE_INVALID', 422);
        }
        try {
            $required = $extension === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($required) === false) {
                throw new ClassPresentationGenerationException('El contenido no corresponde a la extensión Office indicada.', 'REFERENCE_OFFICE_MISMATCH', 422);
            }
        } finally {
            $zip->close();
        }
    }

    private function sanitize(string $text): string
    {
        try {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        } catch (Throwable) {
            // Conserva el texto recuperable; los controles se eliminan abajo.
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? '';

        return trim((string) preg_replace('/[ \t]+/u', ' ', preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text));
    }
}
