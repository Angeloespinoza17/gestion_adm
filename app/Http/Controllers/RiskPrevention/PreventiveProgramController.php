<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\PreventiveProgram;
use App\Models\RiskPrevention\PreventiveProgramAction;
use App\Models\RiskPrevention\RiskControl;
use App\Models\RiskPrevention\RiskEvidence;
use App\Services\RiskPrevention\RiskMatrixAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PreventiveProgramController extends Controller
{
    public function __construct(private readonly RiskMatrixAuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('preventive-program.view'), 403);
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:30'], 'responsible_id' => ['nullable', 'integer'],
            'due' => ['nullable', Rule::in(['overdue', 'upcoming', 'completed'])], 'search' => ['nullable', 'string', 'max:100'],
        ]);
        $query = PreventiveProgramAction::query()
            ->whereHas('program', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))
            ->with([
                'program.version.matrix:id,code,name,work_center_id', 'responsible:id,name,email',
                'control.risk.task.process:id,risk_matrix_version_id,name', 'control.risk.task:id,risk_matrix_process_id,activity_name,task_name',
                'control.risk:id,risk_matrix_task_id,specific_risk_name',
            ]);
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($responsible = $filters['responsible_id'] ?? null) {
            $query->where('responsible_id', $responsible);
        }
        if (($filters['due'] ?? null) === 'overdue') {
            $query->whereDate('due_date', '<', now())->whereNotIn('status', ['verified', 'cancelled']);
        }
        if (($filters['due'] ?? null) === 'upcoming') {
            $query->whereBetween('due_date', [now(), now()->addDays(15)])->whereNotIn('status', ['verified', 'cancelled']);
        }
        if (($filters['due'] ?? null) === 'completed') {
            $query->whereIn('status', ['implemented', 'verified']);
        }
        if ($search = trim($filters['search'] ?? '')) {
            $query->where('action_description', 'like', "%{$search}%");
        }

        return response()->json($query->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date')->paginate(30));
    }

    public function show(PreventiveProgram $program, Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('preventive-program.view'), 403);
        abort_unless($program->company_key === config('risk_matrix.company.key'), 404);

        return response()->json(['data' => $program->load('version.matrix', 'actions.responsible:id,name,email', 'actions.control.risk')]);
    }

    public function updateAction(PreventiveProgramAction $action, Request $request): JsonResponse
    {
        abort_unless($action->program()->where('company_key', config('risk_matrix.company.key'))->exists(), 404);
        abort_unless($request->user()->hasPermission('preventive-program.manage') || ($request->user()->id === $action->responsible_id && $request->user()->hasPermission('risk-control.implement')), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'planned', 'in_progress', 'implemented', 'ineffective', 'cancelled'])],
            'progress_percentage' => ['required', 'integer', 'between:0,100'],
            'actual_completion_date' => ['nullable', 'date'], 'verification_result' => ['nullable', Rule::in(['effective', 'partially_effective', 'ineffective'])],
        ]);
        if ($data['status'] === 'implemented' && $data['progress_percentage'] < 100) {
            return response()->json(['message' => 'Una medida implementada requiere 100% de avance, pero seguirá pendiente de verificación.'], 422);
        }
        $old = $action->toArray();
        DB::transaction(function () use ($action, $data) {
            $action->update($data);
            $action->control->update([
                'status' => $data['status'], 'progress_percentage' => $data['progress_percentage'],
                'completed_at' => $data['status'] === 'implemented' ? ($data['actual_completion_date'] ?? now()) : null,
                'effectiveness_result' => $data['verification_result'] ?? null,
            ]);
            $action->program->update(['progress_percentage' => (int) round((float) $action->program->actions()->avg('progress_percentage'))]);
        });
        $this->audit->record($action->control, 'program_action_updated', $old, $action->fresh()->toArray());

        return response()->json(['message' => 'Seguimiento actualizado sin modificar el contenido técnico aprobado.', 'data' => $action->fresh(['responsible', 'control'])]);
    }

    public function verifyAction(PreventiveProgramAction $action, Request $request): JsonResponse
    {
        abort_unless($action->program()->where('company_key', config('risk_matrix.company.key'))->exists(), 404);
        abort_unless($request->user()->hasPermission('risk-control.verify'), 403);
        $data = $request->validate(['result' => ['required', Rule::in(['effective', 'partially_effective', 'ineffective'])], 'notes' => ['required', 'string', 'max:5000']]);
        $evidenceCount = RiskEvidence::query()->where('evidenceable_type', (new RiskControl)->getMorphClass())->where('evidenceable_id', $action->risk_control_id)->count();
        if ($evidenceCount === 0) {
            return response()->json(['message' => 'La verificación requiere al menos una evidencia autorizada.'], 422);
        }
        $status = $data['result'] === 'ineffective' ? 'ineffective' : 'verified';
        DB::transaction(function () use ($action, $data, $status, $evidenceCount, $request) {
            $action->update(['status' => $status, 'verification_result' => $data['result'], 'evidence_count' => $evidenceCount]);
            $action->control->update(['status' => $status, 'verified_at' => now(), 'verified_by' => $request->user()->id, 'effectiveness_result' => $data['result'], 'effectiveness_notes' => $data['notes']]);
        });
        $this->audit->record($action->control, 'control_verified', [], ['result' => $data['result'], 'evidence_count' => $evidenceCount], $data['notes']);

        return response()->json(['message' => $status === 'ineffective' ? 'Medida marcada como ineficaz; registre una nueva medida en una revisión.' : 'Medida verificada con evidencia.', 'data' => $action->fresh('control')]);
    }
}
