<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $disk = Storage::disk(config('backup.disk'));
        $directory = $this->backupDirectory();

        $backups = collect($disk->files($directory))
            ->filter(fn (string $path) => $this->isBackupFilename(basename($path)))
            ->map(function (string $path) use ($disk): array {
                $lastModified = $disk->lastModified($path);
                $size = $disk->size($path);
                $filename = basename($path);

                return [
                    'id' => $filename,
                    'filename' => $filename,
                    'created_at' => Carbon::createFromTimestamp($lastModified)->toIso8601String(),
                    'size_bytes' => $size,
                    'size_human' => $this->humanFileSize($size),
                    'format' => str_ends_with($filename, '.sqlite') ? 'SQLite' : 'SQL comprimido',
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'data' => $backups,
            'meta' => [
                'count' => $backups->count(),
                'total_size_bytes' => $backups->sum('size_bytes'),
                'total_size_human' => $this->humanFileSize((int) $backups->sum('size_bytes')),
                'retention_days' => (int) config('backup.retention_days'),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function download(string $filename): StreamedResponse
    {
        abort_unless($this->isBackupFilename($filename), 404);

        $disk = Storage::disk(config('backup.disk'));
        $path = $this->backupPath($filename);

        abort_unless($disk->exists($path), 404);

        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(
            function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            $filename,
            [
                'Content-Type' => str_ends_with($filename, '.sqlite')
                    ? 'application/vnd.sqlite3'
                    : 'application/gzip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function backupDirectory(): string
    {
        return trim((string) config('backup.path'), '/');
    }

    private function backupPath(string $filename): string
    {
        $directory = $this->backupDirectory();

        return $directory === '' ? $filename : $directory.'/'.$filename;
    }

    private function isBackupFilename(string $filename): bool
    {
        return basename($filename) === $filename
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:sql\.gz|sqlite)\z/', $filename) === 1;
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;

        foreach ($units as $index => $unit) {
            if ($size < 1024 || $index === count($units) - 1) {
                return number_format($size, $size >= 10 ? 1 : 2, ',', '.').' '.$unit;
            }

            $size /= 1024;
        }

        return $bytes.' B';
    }
}
