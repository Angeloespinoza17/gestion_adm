<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskMatrixParticipation;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Services\RiskPrevention\RiskMatrixAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskMatrixParticipationController extends Controller
{
    public function __construct(private readonly RiskMatrixAuditService $audit) {}

    public function store(RiskMatrixVersion $version, Request $request): JsonResponse
    {
        $this->authorize('view', $version);
        abort_unless($request->user()->hasPermission('risk-matrix.update') || $request->user()->hasPermission('risk-matrix.review'), 403);
        abort_unless(in_array($version->status->value, ['draft', 'observed', 'in_review'], true), 409, 'La participación de una versión aprobada es inmutable; cree una nueva versión.');
        $data = $request->validate([
            'participant_user_id' => ['nullable', 'integer', 'exists:users,id'], 'participant_employee_id' => ['nullable', 'integer', 'exists:staff,id'],
            'participant_name_snapshot' => ['required', 'string', 'max:255'], 'participant_role' => ['nullable', 'string', 'max:255'],
            'representation_type' => ['required', 'string', 'max:60'], 'participation_date' => ['required', 'date'],
            'comments' => ['nullable', 'string', 'max:5000'], 'response_or_resolution' => ['nullable', 'string', 'max:5000'],
            'acknowledged' => ['nullable', 'boolean'],
        ]);
        $item = $version->participations()->create([...$data, 'acknowledged_at' => ($data['acknowledged'] ?? false) ? now() : null]);
        $this->audit->record($version, 'participation_recorded', [], ['participation_id' => $item->id, 'representation_type' => $item->representation_type]);

        return response()->json(['message' => 'Constancia de participación registrada.', 'data' => $item], 201);
    }

    public function destroy(RiskMatrixParticipation $participation, Request $request): JsonResponse
    {
        $version = RiskMatrixVersion::query()->findOrFail($participation->risk_matrix_version_id);
        $this->authorize('update', $version);
        $this->audit->record($version, 'participation_deleted_from_draft', $participation->toArray(), []);
        $participation->delete();

        return response()->json(['message' => 'Participación eliminada del borrador.']);
    }

    public function review(RiskMatrixVersion $version, Request $request): JsonResponse
    {
        $this->authorize('review', $version);
        $data = $request->validate([
            'review_type' => ['required', 'string', 'max:50'],
            'trigger_reason' => ['required', 'string', 'max:60'],
            'review_date' => ['required', 'date'],
            'findings' => ['nullable', 'string', 'max:5000'],
            'requires_new_version' => ['required', 'boolean'],
        ]);
        $review = $version->reviews()->create([...$data, 'reviewed_by' => $request->user()->id]);
        $this->audit->record($version, 'review_recorded', [], ['review_id' => $review->id, 'trigger_reason' => $review->trigger_reason]);

        return response()->json(['message' => 'Revisión registrada.', 'data' => $review], 201);
    }
}
