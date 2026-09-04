<?php

namespace App\Services;

use App\Models\ManagedDocument;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PublicEducationalProjectDocumentResolver
{
    private const FALLBACK_RELATIVE_PATH = 'documents/proyecto-educativo-cnsc-2023.pdf';

    /**
     * Return only presentation-safe metadata for the public page.
     * Storage paths and model identifiers intentionally stay server-side.
     *
     * @return array{title:string,year:int|null,version:string|null,description:string|null,file_name:string,file_size:int|null,is_managed:bool,download_available:bool}
     */
    public function metadata(): array
    {
        $document = $this->resolve();

        if ($document === null) {
            return [
                'title' => 'Proyecto Educativo Institucional',
                'year' => null,
                'version' => null,
                'description' => null,
                'file_name' => 'proyecto-educativo-cnsc.pdf',
                'file_size' => null,
                'is_managed' => false,
                'download_available' => false,
            ];
        }

        return [
            'title' => $document['title'],
            'year' => $document['year'],
            'version' => $document['version'],
            'description' => $document['description'],
            'file_name' => $document['file_name'],
            'file_size' => $document['file_size'],
            'is_managed' => $document['source'] === 'managed',
            'download_available' => true,
        ];
    }

    public function download(): BinaryFileResponse|StreamedResponse
    {
        $document = $this->resolve();

        abort_if($document === null, 404);

        $headers = [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($document['source'] === 'managed') {
            return Storage::disk('local')->download(
                $document['path'],
                $document['file_name'],
                $headers,
            );
        }

        $response = response()->download(
            $document['path'],
            $document['file_name'],
            $headers,
        );

        $response->setPrivate();
        $response->headers->removeCacheControlDirective('public');
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * @return array{source:'managed'|'fallback',path:string,title:string,year:int|null,version:string|null,description:string|null,file_name:string,file_size:int|null}|null
     */
    private function resolve(): ?array
    {
        return $this->resolveManagedDocument() ?? $this->resolveFallbackDocument();
    }

    /**
     * @return array{source:'managed',path:string,title:string,year:int|null,version:string|null,description:string|null,file_name:string,file_size:int|null}|null
     */
    private function resolveManagedDocument(): ?array
    {
        if (! class_exists(ManagedDocument::class) || ! Schema::hasTable('managed_documents')) {
            return null;
        }

        try {
            $candidates = ManagedDocument::query()
                ->publiclyAvailable()
                ->where('category', ManagedDocument::CATEGORY_EDUCATIONAL_PROJECT)
                ->where('mime_type', 'application/pdf')
                ->where('original_name', 'like', '%.pdf')
                ->orderByDesc('year')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->cursor();

            $document = null;

            foreach ($candidates as $candidate) {
                if ($candidate->file_path && Storage::disk('local')->exists($candidate->file_path)) {
                    $document = $candidate;
                    break;
                }
            }
        } catch (Throwable) {
            // Keep the public page available while a pending migration is deployed.
            return null;
        }

        if (! $document) {
            return null;
        }

        $mimeType = strtolower((string) $document->mime_type);
        $isPdf = $mimeType === 'application/pdf'
            && Str::endsWith(strtolower((string) $document->original_name), '.pdf');

        if (! $isPdf) {
            return null;
        }

        $fileName = str_replace(["\r", "\n"], '', basename((string) $document->original_name));

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            $fileName = Str::slug((string) $document->title ?: 'proyecto-educativo').'.pdf';
        }

        return [
            'source' => 'managed',
            'path' => (string) $document->file_path,
            'title' => (string) ($document->title ?: 'Proyecto Educativo Institucional'),
            'year' => $document->year ? (int) $document->year : null,
            'version' => filled($document->version) ? (string) $document->version : null,
            'description' => filled($document->description) ? (string) $document->description : null,
            'file_name' => $fileName,
            'file_size' => $document->file_size ? (int) $document->file_size : null,
        ];
    }

    /**
     * @return array{source:'fallback',path:string,title:string,year:int,version:null,description:null,file_name:string,file_size:int|null}|null
     */
    private function resolveFallbackDocument(): ?array
    {
        $path = public_path(self::FALLBACK_RELATIVE_PATH);

        if (! is_file($path)) {
            return null;
        }

        $size = filesize($path);

        return [
            'source' => 'fallback',
            'path' => $path,
            'title' => 'Proyecto Educativo Institucional',
            'year' => 2023,
            'version' => null,
            'description' => null,
            'file_name' => 'proyecto-educativo-cnsc-2023.pdf',
            'file_size' => $size === false ? null : $size,
        ];
    }
}
