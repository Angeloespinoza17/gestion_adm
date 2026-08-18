<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Http\Requests\Psychology\UploadPsychologyDocumentRequest;
use App\Models\Psychology\PsychologyActivity;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyDocument;
use App\Models\Psychology\PsychologyReferral;
use App\Services\Psychology\PsychologyAccessService;
use App\Services\Psychology\PsychologyAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PsychologyDocumentController extends Controller
{
    public function __construct(private readonly PsychologyAccessService $access, private readonly PsychologyAuditService $audit) {}

    public function store(UploadPsychologyDocumentRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('psychology.documents.upload'), 403);
        $payload = $request->validated();
        if (empty($payload['case_id']) && empty($payload['referral_id'])) {
            abort(422, 'El documento debe asociarse a un caso o derivación.');
        }
        if (! empty($payload['case_id'])) {
            $case = PsychologyCase::findOrFail($payload['case_id']);
            abort_unless($this->access->canViewCase($request->user(), $case), 404);
        }
        if (! empty($payload['referral_id'])) {
            $referral = PsychologyReferral::findOrFail($payload['referral_id']);
            abort_unless($this->access->canViewReferral($request->user(), $referral), 404);
        }
        if (! empty($payload['activity_id'])) {
            $activity = PsychologyActivity::findOrFail($payload['activity_id']);
            abort_unless(! empty($case) && (int) $activity->case_id === (int) $case->id, 422);
        }
        $file = $request->file('document');
        $extension = strtolower($file->guessExtension() ?: 'bin');
        abort_if(in_array($extension, ['php', 'phar', 'phtml', 'exe', 'sh', 'js', 'html', 'svg'], true), 422, 'Tipo de archivo no permitido.');
        $path = $file->storeAs('psychology/'.now()->format('Y/m'), Str::uuid().'.'.$extension, config('psychology.disk', 'local'));
        $document = DB::transaction(function () use ($payload, $path, $file, $request) {
            $document = PsychologyDocument::query()->create(collect($payload)->except('document')->all() + ['private_path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $request->user()->id]);
            $this->audit->record('document.uploaded', $document, $request->user(), [], ['category' => $document->category, 'visibility' => $document->visibility]);

            return $document;
        });

        return response()->json(['message' => 'Documento almacenado de forma privada.', 'data' => $document->load('uploadedBy:id,name')], 201);
    }

    public function download(PsychologyDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless(Storage::disk(config('psychology.disk', 'local'))->exists($document->private_path), 404);
        $this->audit->record('document.downloaded', $document, request()->user());

        return Storage::disk(config('psychology.disk', 'local'))->download($document->private_path, $document->original_name, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, PsychologyDocument $document): JsonResponse
    {
        $this->authorize('view', $document);
        abort_unless($request->user()->hasPermission('psychology.documents.upload'), 403);
        $document->forceFill(['status' => 'archived', 'deleted_by' => $request->user()->id])->save();
        $document->delete();
        $this->audit->record('document.archived', $document, $request->user());

        return response()->json(['message' => 'Documento archivado; el binario se conserva según la política institucional.']);
    }
}
