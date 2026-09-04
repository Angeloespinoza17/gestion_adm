<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Enums\PedagogicalManagement\ClassPresentationFileType;
use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\PedagogicalManagement\ClassPresentationFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresentationFileStorage
{
    /** @param array<string,mixed>|null $metadata */
    public function store(ClassPresentation $presentation, ClassPresentationFileType $type, string $localPath, ?array $metadata = null): ClassPresentationFile
    {
        $disk = (string) config('class_presentations.storage.disk', 'local');
        $root = trim((string) config('class_presentations.storage.root'), '/');
        $year = (string) data_get($presentation->curricular_snapshot, 'academic_year.year', now()->year);
        $schoolUuid = (string) data_get($presentation->curricular_snapshot, 'school.uuid', $presentation->school_id);
        $directory = "{$root}/{$schoolUuid}/{$year}/{$presentation->uuid}/v{$presentation->version}";
        $extension = pathinfo($localPath, PATHINFO_EXTENSION);
        $base = Str::slug(implode('_', [
            data_get($presentation->curricular_snapshot, 'subject.name', 'clase'),
            data_get($presentation->curricular_snapshot, 'course.name', 'curso'),
            data_get($presentation->curricular_snapshot, 'unit.code', 'unidad'),
        ]), '_');
        $suffix = match ($type) {
            ClassPresentationFileType::PowerPoint => '',
            ClassPresentationFileType::Pdf => '',
            ClassPresentationFileType::TeacherGuidePdf => '_guia_docente',
            ClassPresentationFileType::Json => '_contenido',
            ClassPresentationFileType::Thumbnail => '_miniatura',
            ClassPresentationFileType::Preview => sprintf('_diapositiva_%02d', (int) ($metadata['slide_number'] ?? 1)),
        };
        $filename = Str::limit(($base !== '' ? $base : 'clase')."_v{$presentation->version}{$suffix}.{$extension}", 191, '');
        $path = "{$directory}/{$filename}";
        $stream = fopen($localPath, 'rb');
        if (! is_resource($stream)) {
            throw new ClassPresentationGenerationException('No fue posible leer un archivo generado.', 'GENERATED_FILE_READ_FAILED');
        }
        try {
            if (! Storage::disk($disk)->put($path, $stream)) {
                throw new ClassPresentationGenerationException('No fue posible guardar un archivo generado.', 'GENERATED_FILE_STORE_FAILED');
            }
        } finally {
            fclose($stream);
        }

        return $presentation->files()->create([
            'version' => $presentation->version, 'type' => $type, 'disk' => $disk, 'path' => $path,
            'filename' => $filename, 'mime_type' => $this->mime($type, $extension),
            'size' => filesize($localPath), 'checksum' => hash_file('sha256', $localPath), 'metadata' => $metadata,
        ]);
    }

    public function purgeGeneratedFiles(ClassPresentation $presentation): void
    {
        foreach ($presentation->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }
    }

    private function mime(ClassPresentationFileType $type, string $extension): string
    {
        return match ($type) {
            ClassPresentationFileType::PowerPoint => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ClassPresentationFileType::Pdf, ClassPresentationFileType::TeacherGuidePdf => 'application/pdf',
            ClassPresentationFileType::Json => 'application/json',
            default => strtolower($extension) === 'svg' ? 'image/svg+xml' : 'image/png',
        };
    }
}
