<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Http\Requests\RiskPrevention\SaveRiskPreventionDocumentRequest;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RiskPreventionDocumentController extends Controller
{
    public function __construct(
        private readonly RiskPreventionAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RiskPreventionDocument::class);
        $this->accessService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('document_type'));
        $status = trim((string) $request->query('status'));
        $dissemination = trim((string) $request->query('dissemination'));

        $documents = RiskPreventionDocument::query()
            ->with(['updatedBy:id,name', 'disseminatedBy:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('document_name', 'like', "%{$search}%")
                        ->orWhere('document_group', 'like', "%{$search}%")
                        ->orWhere('responsible_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('document_type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($dissemination === 'yes', fn ($query) => $query->where('is_disseminable', true))
            ->when($dissemination === 'no', fn ($query) => $query->where('is_disseminable', false))
            ->orderByDesc('updated_at')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json(array_merge($documents->toArray(), [
            'summary' => $this->summary(),
        ]));
    }

    public function store(SaveRiskPreventionDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionDocument::class);

        $payload = array_merge(
            $request->safe()->except('document', 'is_disseminable'),
            [
                'is_disseminable' => $request->boolean('is_disseminable'),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        );

        if ($payload['is_disseminable']) {
            $payload['disseminated_at'] = now();
            $payload['disseminated_by'] = $request->user()->id;
        }

        $document = RiskPreventionDocument::query()->create($payload);

        try {
            $file = $request->file('document');
            $document->update($this->filePayload(
                $file,
                $this->storeFile($file, "risk-prevention/documents/{$document->id}"),
            ));
        } catch (Throwable $exception) {
            $document->delete();
            throw $exception;
        }

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Documento registrado correctamente.',
            'data' => $document->fresh(['updatedBy:id,name', 'disseminatedBy:id,name']),
        ], 201);
    }

    public function update(SaveRiskPreventionDocumentRequest $request, RiskPreventionDocument $document): JsonResponse
    {
        $this->authorize('update', $document);

        $payload = array_merge(
            $request->safe()->except('document', 'is_disseminable'),
            [
                'is_disseminable' => $request->boolean('is_disseminable'),
                'updated_by' => $request->user()->id,
            ],
        );

        if ($payload['is_disseminable'] && !$document->is_disseminable) {
            $payload['disseminated_at'] = now();
            $payload['disseminated_by'] = $request->user()->id;
        } elseif (!$payload['is_disseminable']) {
            $payload['disseminated_at'] = null;
            $payload['disseminated_by'] = null;
        }

        $oldPath = $document->document_path;
        $newPath = null;

        if ($request->file('document') instanceof UploadedFile) {
            $file = $request->file('document');
            $newPath = $this->storeFile($file, "risk-prevention/documents/{$document->id}");
            $payload = array_merge($payload, $this->filePayload($file, $newPath));
        }

        try {
            $document->update($payload);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Documento actualizado correctamente.',
            'data' => $document->fresh(['updatedBy:id,name', 'disseminatedBy:id,name']),
        ]);
    }

    public function destroy(RiskPreventionDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        if ($document->document_path) {
            Storage::disk('local')->delete($document->document_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }

    public function download(RiskPreventionDocument $document): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $document);

        if (!$document->document_path || !Storage::disk('local')->exists($document->document_path)) {
            return response()->json(['message' => 'El documento no está disponible.'], 404);
        }

        return Storage::disk('local')->download($document->document_path, $document->document_name ?: basename($document->document_path));
    }

    public function disseminatedIndex(Request $request): JsonResponse
    {
        $this->accessService->refreshDynamicStatuses();

        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('document_type'));

        $documents = RiskPreventionDocument::query()
            ->select([
                'id',
                'document_type',
                'title',
                'document_group',
                'version_number',
                'valid_from',
                'valid_until',
                'status',
                'is_disseminable',
                'responsible_name',
                'document_path',
                'document_name',
                'mime_type',
                'file_extension',
                'file_size',
                'disseminated_at',
                'notes',
                'updated_at',
            ])
            ->where('is_disseminable', true)
            ->whereNotNull('document_path')
            ->whereIn('status', [
                RiskPreventionDocument::STATUS_VIGENTE,
                RiskPreventionDocument::STATUS_POR_VENCER,
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('document_name', 'like', "%{$search}%")
                        ->orWhere('document_group', 'like', "%{$search}%")
                        ->orWhere('responsible_name', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('document_type', $type))
            ->orderByDesc('disseminated_at')
            ->orderBy('title')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json($documents);
    }

    public function downloadDisseminated(RiskPreventionDocument $document): StreamedResponse|JsonResponse
    {
        $this->accessService->refreshDynamicStatuses();
        $document->refresh();

        if (
            !$document->is_disseminable
            || !in_array($document->status, [
                RiskPreventionDocument::STATUS_VIGENTE,
                RiskPreventionDocument::STATUS_POR_VENCER,
            ], true)
        ) {
            return response()->json(['message' => 'El documento no está disponible para difusión.'], 404);
        }

        if (!$document->document_path || !Storage::disk('local')->exists($document->document_path)) {
            return response()->json(['message' => 'El documento no está disponible.'], 404);
        }

        return Storage::disk('local')->download(
            $document->document_path,
            $document->document_name ?: basename($document->document_path),
        );
    }

    private function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->storeAs(
            $directory,
            now()->format('Ymd_His') . '_' . uniqid() . '_' . basename($file->getClientOriginalName()),
            'local',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filePayload(UploadedFile $file, string $path): array
    {
        return [
            'document_path' => $path,
            'document_name' => basename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType(),
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'total' => RiskPreventionDocument::query()->count(),
            'disseminable' => RiskPreventionDocument::query()->where('is_disseminable', true)->count(),
            'due' => RiskPreventionDocument::query()
                ->whereIn('status', [
                    RiskPreventionDocument::STATUS_POR_VENCER,
                    RiskPreventionDocument::STATUS_VENCIDO,
                ])
                ->count(),
            'without_file' => RiskPreventionDocument::query()->whereNull('document_path')->count(),
        ];
    }
}
