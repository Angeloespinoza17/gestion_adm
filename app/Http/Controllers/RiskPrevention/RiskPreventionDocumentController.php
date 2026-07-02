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

        $documents = RiskPreventionDocument::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('document_group', 'like', "%{$search}%")
                        ->orWhere('responsible_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('document_type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 12));

        return response()->json($documents);
    }

    public function store(SaveRiskPreventionDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', RiskPreventionDocument::class);

        $document = RiskPreventionDocument::query()->create(array_merge(
            $request->safe()->except('document'),
            ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id],
        ));

        if ($request->file('document') instanceof UploadedFile) {
            $path = $this->storeFile($request->file('document'), "risk-prevention/documents/{$document->id}");
            $document->update([
                'document_path' => $path,
                'document_name' => $request->file('document')->getClientOriginalName(),
            ]);
        }

        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Documento registrado correctamente.',
            'data' => $document->fresh(),
        ], 201);
    }

    public function update(SaveRiskPreventionDocumentRequest $request, RiskPreventionDocument $document): JsonResponse
    {
        $this->authorize('update', $document);

        $payload = array_merge(
            $request->safe()->except('document'),
            ['updated_by' => $request->user()->id],
        );

        if ($request->file('document') instanceof UploadedFile) {
            if ($document->document_path) {
                Storage::disk('local')->delete($document->document_path);
            }

            $payload['document_path'] = $this->storeFile($request->file('document'), "risk-prevention/documents/{$document->id}");
            $payload['document_name'] = $request->file('document')->getClientOriginalName();
        }

        $document->update($payload);
        $this->accessService->refreshDynamicStatuses();

        return response()->json([
            'message' => 'Documento actualizado correctamente.',
            'data' => $document->fresh(),
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

    private function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->storeAs(
            $directory,
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            'local',
        );
    }
}
