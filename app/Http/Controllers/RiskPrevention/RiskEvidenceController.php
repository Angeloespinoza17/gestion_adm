<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskAssessment;
use App\Models\RiskPrevention\RiskControl;
use App\Models\RiskPrevention\RiskEvidence;
use App\Models\RiskPrevention\RiskMatrixReview;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Services\RiskPrevention\RiskMatrixAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RiskEvidenceController extends Controller
{
    public function __construct(private readonly RiskMatrixAuditService $audit) {}

    public function store(RiskMatrixVersion $version, Request $request): JsonResponse
    {
        $this->authorize('view', $version);
        abort_unless($request->user()->hasPermission('risk-control.implement') || $request->user()->hasPermission('risk-matrix.update'), 403);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', config('risk_matrix.evidence.mimes')), 'max:'.config('risk_matrix.evidence.max_kilobytes')],
            'evidence_type' => ['required', 'string', 'max:60'], 'description' => ['nullable', 'string', 'max:1000'],
            'evidence_date' => ['required', 'date'], 'visibility' => ['required', Rule::in(['authorized', 'participants', 'public_internal'])],
            'evidenceable_type' => ['nullable', Rule::in(['control', 'assessment', 'review'])], 'evidenceable_id' => ['nullable', 'integer'],
        ]);
        [$type, $id] = $this->resolveEvidenceable($version, $data['evidenceable_type'] ?? null, $data['evidenceable_id'] ?? null);
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs("risk-prevention/evidence/{$version->risk_matrix_id}/{$version->id}", $hash.'.'.$file->getClientOriginalExtension(), 'local');
        $evidence = RiskEvidence::query()->create([
            'risk_matrix_version_id' => $version->id, 'evidenceable_type' => $type, 'evidenceable_id' => $id,
            'evidence_type' => $data['evidence_type'], 'description' => $data['description'] ?? null, 'evidence_date' => $data['evidence_date'],
            'visibility' => $data['visibility'], 'file_path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'file_hash' => $hash, 'uploaded_by' => $request->user()->id,
        ]);
        $this->audit->record($version, 'evidence_uploaded', [], ['evidence_id' => $evidence->id, 'hash' => $hash]);

        return response()->json(['message' => 'Evidencia almacenada en ubicación privada.', 'data' => $evidence], 201);
    }

    public function download(RiskEvidence $evidence, Request $request)
    {
        $version = RiskMatrixVersion::query()->findOrFail($evidence->risk_matrix_version_id);
        $this->authorize('view', $version);
        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);
        $this->audit->record($version, 'evidence_downloaded', [], ['evidence_id' => $evidence->id]);

        return Storage::disk('local')->download($evidence->file_path, $evidence->original_name, ['Cache-Control' => 'no-store, private']);
    }

    private function resolveEvidenceable(RiskMatrixVersion $version, ?string $type, ?int $id): array
    {
        if (! $type || ! $id) {
            return [null, null];
        }
        $classes = ['control' => RiskControl::class, 'assessment' => RiskAssessment::class, 'review' => RiskMatrixReview::class];
        $model = $classes[$type]::query()->findOrFail($id);
        $belongs = match ($type) {
            'control' => $model->risk->task->process->risk_matrix_version_id,
            'assessment' => $model->risk->task->process->risk_matrix_version_id,
            'review' => $model->risk_matrix_version_id,
        };
        abort_unless($belongs === $version->id, 404);

        return [$model->getMorphClass(), $model->id];
    }
}
