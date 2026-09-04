<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveManagedDocumentRequest;
use App\Http\Resources\ManagedDocumentResource;
use App\Models\ManagedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ManagedDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search'));
        $category = trim((string) $request->query('category'));
        $year = $request->integer('year');
        $perPage = max(1, min($request->integer('per_page', 15), 50));

        $query = ManagedDocument::query()
            ->with(['createdBy:id,name,email', 'updatedBy:id,name,email'])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('version', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($builder) => $builder->where('category', $category))
            ->when($year > 0, fn ($builder) => $builder->where('year', $year));

        foreach (['is_public', 'is_active'] as $booleanFilter) {
            if ($request->has($booleanFilter) && $request->query($booleanFilter) !== '') {
                $query->where($booleanFilter, $request->boolean($booleanFilter));
            }
        }

        return ManagedDocumentResource::collection(
            $query
                ->orderByDesc('year')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'categories' => collect(ManagedDocument::CATEGORY_LABELS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'years' => ManagedDocument::query()->distinct()->orderByDesc('year')->pluck('year')->values(),
            'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
            'max_file_size_mb' => (int) (SaveManagedDocumentRequest::MAX_FILE_SIZE_KILOBYTES / 1024),
            'stats' => [
                'total' => ManagedDocument::query()->count(),
                'active' => ManagedDocument::query()->where('is_active', true)->count(),
                'public' => ManagedDocument::query()->publiclyAvailable()->count(),
            ],
        ]);
    }

    public function show(ManagedDocument $managedDocument): ManagedDocumentResource
    {
        return new ManagedDocumentResource(
            $managedDocument->load(['createdBy:id,name,email', 'updatedBy:id,name,email'])
        );
    }

    public function store(SaveManagedDocumentRequest $request): JsonResponse
    {
        $payload = $this->metadataPayload($request);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'Debe adjuntar un archivo.');

        $fileData = $this->storeFile($file, $payload['category'], (int) $payload['year']);

        try {
            $document = DB::transaction(function () use ($payload, $fileData, $request): ManagedDocument {
                return ManagedDocument::query()->create([
                    ...$payload,
                    ...$fileData,
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($fileData['file_path']);
            throw $exception;
        }

        return response()->json([
            'message' => 'Documento cargado correctamente.',
            'data' => (new ManagedDocumentResource(
                $document->load(['createdBy:id,name,email', 'updatedBy:id,name,email'])
            ))->resolve($request),
        ], 201);
    }

    public function update(SaveManagedDocumentRequest $request, ManagedDocument $managedDocument): JsonResponse
    {
        $payload = $this->metadataPayload($request);
        $file = $request->file('file');
        $newFileData = $file instanceof UploadedFile
            ? $this->storeFile($file, $payload['category'], (int) $payload['year'])
            : [];
        $previousPath = $managedDocument->file_path;

        try {
            DB::transaction(function () use ($managedDocument, $payload, $newFileData, $request): void {
                $managedDocument->update([
                    ...$payload,
                    ...$newFileData,
                    'updated_by' => $request->user()?->id,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            if (isset($newFileData['file_path'])) {
                Storage::disk('local')->delete($newFileData['file_path']);
            }

            throw $exception;
        }

        if (isset($newFileData['file_path']) && $previousPath !== $newFileData['file_path']) {
            Storage::disk('local')->delete($previousPath);
        }

        return response()->json([
            'message' => 'Documento actualizado correctamente.',
            'data' => (new ManagedDocumentResource(
                $managedDocument->fresh(['createdBy:id,name,email', 'updatedBy:id,name,email'])
            ))->resolve($request),
        ]);
    }

    public function destroy(Request $request, ManagedDocument $managedDocument): JsonResponse
    {
        $path = $managedDocument->file_path;

        DB::transaction(function () use ($managedDocument, $request): void {
            $managedDocument->forceFill(['deleted_by' => $request->user()?->id])->save();
            $managedDocument->delete();
        }, 3);

        Storage::disk('local')->delete($path);

        return response()->json(['message' => 'Documento eliminado correctamente.']);
    }

    public function download(ManagedDocument $managedDocument): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($managedDocument->file_path), 404);

        return Storage::disk('local')->download(
            $managedDocument->file_path,
            $managedDocument->original_name,
            [
                'Content-Type' => $managedDocument->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    private function metadataPayload(SaveManagedDocumentRequest $request): array
    {
        $payload = $request->safe()->except('file');

        foreach (['title', 'version', 'description'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }

            if (($payload[$field] ?? null) === '') {
                $payload[$field] = null;
            }
        }

        $payload['is_public'] = (bool) $payload['is_public'];
        $payload['is_active'] = (bool) $payload['is_active'];

        return $payload;
    }

    private function storeFile(UploadedFile $file, string $category, int $year): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = (string) Str::uuid().'.'.$extension;
        $path = $file->storeAs("documentation/{$category}/{$year}", $filename, 'local');

        if (! is_string($path) || $path === '') {
            abort(500, 'No fue posible guardar el documento.');
        }

        return [
            'file_path' => $path,
            'original_name' => $this->safeOriginalName($file->getClientOriginalName(), $extension),
            'mime_type' => (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream'),
            'file_size' => (int) $file->getSize(),
        ];
    }

    private function safeOriginalName(string $name, string $extension): string
    {
        $name = preg_replace('/[\\x00-\\x1F\\x7F\\/\\\\]+/u', '_', trim($name)) ?: '';
        $name = Str::limit($name, 255, '');

        return $name !== '' ? $name : "documento.{$extension}";
    }
}
