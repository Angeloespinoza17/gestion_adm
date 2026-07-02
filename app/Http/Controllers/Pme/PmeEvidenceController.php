<?php

namespace App\Http\Controllers\Pme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pme\ReviewPmeEvidenceRequest;
use App\Http\Requests\Pme\UploadPmeEvidenceRequest;
use App\Models\Pme\PmeActivity;
use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeEvidence;
use App\Models\Pme\PmeMilestone;
use App\Services\Pme\PmeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PmeEvidenceController extends Controller
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $query = PmeEvidence::query()
            ->with(['action:id,name', 'activity:id,name', 'milestone:id,name', 'uploadedBy:id,name', 'reviewedBy:id,name'])
            ->orderByDesc('uploaded_at');
        $query->when($request->query('pme_action_id'), fn ($builder, $actionId) => $builder->where('pme_action_id', $actionId));
        $query->when($request->query('review_status'), fn ($builder, $status) => $builder->where('review_status', $status));
        $query->when($request->query('evidence_type'), fn ($builder, $type) => $builder->where('evidence_type', $type));

        return response()->json($query->paginate(20));
    }

    public function store(UploadPmeEvidenceRequest $request): JsonResponse
    {
        abort_unless($this->accessService->canCreateEvidence($request->user()), 403);

        $payload = $request->validated();
        $actionId = $payload['pme_action_id'] ?? null;
        if (!$actionId && !empty($payload['pme_activity_id'])) {
            $actionId = PmeActivity::query()->whereKey($payload['pme_activity_id'])->value('pme_action_id');
        }
        if (!$actionId && !empty($payload['pme_milestone_id'])) {
            $actionId = PmeMilestone::query()->whereKey($payload['pme_milestone_id'])->value('pme_action_id');
        }
        if (!$actionId) {
            return response()->json(['message' => 'La evidencia debe vincularse al menos a una acción o actividad relacionada.'], 422);
        }

        $path = $request->file('document')->store('pme-sep/evidences', 'public');
        $evidence = PmeEvidence::query()->create(array_merge($payload, [
            'pme_action_id' => $actionId,
            'uploaded_at' => now(),
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'original_name' => $request->file('document')->getClientOriginalName(),
            'mime_type' => $request->file('document')->getClientMimeType(),
            'file_size' => $request->file('document')->getSize(),
            'review_status' => 'cargada',
        ]));

        return response()->json([
            'message' => 'Evidencia cargada correctamente.',
            'data' => $evidence->fresh(['action', 'activity', 'milestone', 'uploadedBy']),
        ], 201);
    }

    public function review(ReviewPmeEvidenceRequest $request, PmeEvidence $evidence): JsonResponse
    {
        $user = $request->user();
        $status = $request->validated('review_status');

        abort_unless($this->accessService->canReviewEvidence($user), 403);

        if ($status === 'aprobada') {
            abort_unless($this->accessService->canApproveEvidence($user), 403);
        }

        if ($status === 'rechazada') {
            abort_unless($this->accessService->canRejectEvidence($user), 403);
        }

        $evidence->update([
            'review_status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $user->id,
            'review_comments' => $request->validated('review_comments'),
            'observations' => $request->validated('observations') ?? $evidence->observations,
        ]);

        return response()->json([
            'message' => 'Revisión de evidencia registrada correctamente.',
            'data' => $evidence->fresh(['action', 'uploadedBy', 'reviewedBy']),
        ]);
    }

    public function download(Request $request, PmeEvidence $evidence)
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return Storage::disk('public')->download($evidence->file_path, $evidence->original_name);
    }

    public function destroy(Request $request, PmeEvidence $evidence): JsonResponse
    {
        abort_unless($this->accessService->canCreateEvidence($request->user()), 403);

        if ($evidence->file_path) {
            Storage::disk('public')->delete($evidence->file_path);
        }

        $evidence->delete();

        return response()->json(['message' => 'Evidencia eliminada correctamente.']);
    }
}
