<?php

namespace App\Http\Controllers\Orientation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orientation\StoreOrientationEvidenceRequest;
use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationEvidence;
use App\Services\Orientation\OrientationWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrientationEvidenceController extends Controller
{
    public function __construct(
        private readonly OrientationWorkspaceService $workspace,
    ) {}

    public function store(StoreOrientationEvidenceRequest $request, OrientationAction $action): JsonResponse
    {
        $activityId = $request->integer('orientation_activity_id') ?: null;
        if ($activityId && ! $action->activities()->whereKey($activityId)->exists()) {
            throw ValidationException::withMessages([
                'orientation_activity_id' => ['La actividad seleccionada no pertenece a esta acción.'],
            ]);
        }

        $file = $request->file('file');
        $path = $file?->store("orientation/{$action->plan->year}/actions/{$action->id}", 'local');

        try {
            $evidence = $action->evidences()->create([
                'orientation_activity_id' => $activityId,
                'evidence_type' => $request->string('evidence_type')->toString(),
                'title' => $request->string('title')->toString(),
                'description' => $request->input('description'),
                'occurred_on' => $request->input('occurred_on'),
                'storage_disk' => 'local',
                'file_path' => $path,
                'external_url' => $request->input('external_url'),
                'original_name' => $file?->getClientOriginalName(),
                'mime_type' => $file?->getMimeType(),
                'size_bytes' => $file?->getSize(),
                'uploaded_by' => $request->user()?->id,
            ]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Evidencia agregada correctamente.',
            'data' => $this->workspace->serializeEvidence($evidence->load(['activity:id,title', 'uploadedBy:id,name'])),
        ], 201);
    }

    public function download(OrientationEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->file_path && Storage::disk($evidence->storage_disk)->exists($evidence->file_path), 404);

        return Storage::disk($evidence->storage_disk)->download(
            $evidence->file_path,
            $evidence->original_name ?: basename($evidence->file_path),
        );
    }
}
