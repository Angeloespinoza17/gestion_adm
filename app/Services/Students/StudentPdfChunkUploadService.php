<?php

namespace App\Services\Students;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StudentPdfChunkUploadService
{
    public const MAX_CHUNK_BYTES = 896 * 1024;

    public const MAX_FILE_BYTES = 100 * 1024 * 1024;

    public const MAX_CHUNKS = 128;

    /**
     * @return array{completed: bool, progress: int, path?: string, directory?: string}
     */
    public function receive(int $userId, array $metadata, string $contents): array
    {
        $uploadId = (string) $metadata['upload_id'];
        $chunkIndex = (int) $metadata['chunk_index'];
        $chunkTotal = (int) $metadata['chunk_total'];
        $fileSize = (int) $metadata['file_size'];
        $directory = "student-pdf-imports/{$userId}/{$uploadId}";
        $disk = Storage::disk('local');

        if ($chunkIndex === 0) {
            $this->cleanupStaleUploads($userId);
            $disk->deleteDirectory($directory);
            $disk->put("{$directory}/manifest.json", json_encode($this->manifest($metadata), JSON_THROW_ON_ERROR));
        } else {
            if (! $disk->exists("{$directory}/manifest.json")) {
                throw new RuntimeException('La carga del PDF no se inició correctamente. Vuelve a seleccionar el archivo.');
            }

            $storedManifest = json_decode((string) $disk->get("{$directory}/manifest.json"), true);
            if ($storedManifest !== $this->manifest($metadata)) {
                $disk->deleteDirectory($directory);

                throw new RuntimeException('La secuencia de carga del PDF cambió. Vuelve a seleccionar el archivo.');
            }
        }

        try {
            $disk->put($this->partPath($directory, $chunkIndex), $contents);
            $received = count($disk->files("{$directory}/parts"));

            if ($received < $chunkTotal) {
                return [
                    'completed' => false,
                    'progress' => (int) floor(($received / $chunkTotal) * 100),
                ];
            }

            $relativePath = "{$directory}/upload.pdf";
            $absolutePath = $disk->path($relativePath);
            $output = fopen($absolutePath, 'wb');

            if (! $output) {
                throw new RuntimeException('No fue posible preparar el archivo PDF para su importación.');
            }

            try {
                for ($index = 0; $index < $chunkTotal; $index++) {
                    $part = $disk->readStream($this->partPath($directory, $index));
                    if (! $part) {
                        throw new RuntimeException('Falta un fragmento del PDF. Vuelve a intentar la importación.');
                    }

                    try {
                        stream_copy_to_stream($part, $output);
                    } finally {
                        fclose($part);
                    }
                }
            } finally {
                fclose($output);
            }

            clearstatcache(true, $absolutePath);
            if (! is_file($absolutePath) || filesize($absolutePath) !== $fileSize) {
                throw new RuntimeException('El PDF ensamblado está incompleto. Vuelve a intentar la importación.');
            }

            $header = file_get_contents($absolutePath, false, null, 0, 5);
            if ($header !== '%PDF-') {
                throw new RuntimeException('El archivo seleccionado no es un PDF válido.');
            }

            return [
                'completed' => true,
                'progress' => 100,
                'path' => $absolutePath,
                'directory' => $directory,
            ];
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }
    }

    public function cleanup(string $directory): void
    {
        Storage::disk('local')->deleteDirectory($directory);
    }

    private function partPath(string $directory, int $chunkIndex): string
    {
        return sprintf('%s/parts/%04d.part', $directory, $chunkIndex);
    }

    private function manifest(array $metadata): array
    {
        return [
            'upload_id' => (string) $metadata['upload_id'],
            'chunk_total' => (int) $metadata['chunk_total'],
            'file_name' => (string) $metadata['file_name'],
            'file_size' => (int) $metadata['file_size'],
            'course_section_id' => isset($metadata['course_section_id'])
                ? (int) $metadata['course_section_id']
                : null,
        ];
    }

    private function cleanupStaleUploads(int $userId): void
    {
        $disk = Storage::disk('local');
        $root = $disk->path("student-pdf-imports/{$userId}");

        if (! is_dir($root)) {
            return;
        }

        foreach (File::directories($root) as $directory) {
            if (File::lastModified($directory) < now()->subHours(6)->getTimestamp()) {
                $disk->deleteDirectory("student-pdf-imports/{$userId}/".basename($directory));
            }
        }
    }
}
