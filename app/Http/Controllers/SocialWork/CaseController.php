<?php

namespace App\Http\Controllers\SocialWork;

use App\Http\Controllers\Controller;
use App\Http\Requests\SocialWork\StoreCaseRequest;
use App\Http\Resources\SocialWork\SocialCaseResource;
use App\Models\SocialWork\SocialCase;
use App\Services\SocialWork\AccessService;
use App\Services\SocialWork\AuditService;
use App\Services\SocialWork\CaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    private const DETAIL_RELATIONS = [
        'student',
        'students',
        'academicYear:id,name,year',
        'courseSection:id,display_name',
        'responsible:id,name,email',
        'statusHistory.user:id,name',
        'reopenings.user:id,name',
        'interventions.responsible:id,name',
        'interventions.commitments.responsible:id,name',
        'alerts.responsible:id,name',
        'referrals.assignedUser:id,name',
        'protocols.protocol:id,name,code',
        'protocols.version',
        'protocols.stepLinks',
        'reports.versions',
        'documents',
        'commitments.responsible:id,name',
        'protocolZero',
        'requestedInformation',
        'riskAssessments',
    ];

    public function index(Request $request, AccessService $access): JsonResponse
    {
        $query = $access->applyCaseVisibility(SocialCase::query(), $request->user())->with(['student:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'responsible:id,name'])->withCount(['interventions', 'alerts', 'protocols']);
        $search = trim((string) $request->query('search'));
        $query->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('rut', 'like', "%{$search}%"))))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))->when($request->query('risk_level'), fn ($q, $v) => $q->where('risk_level', $v))->when($request->query('priority'), fn ($q, $v) => $q->where('priority', $v))->when($request->query('responsible_user_id'), fn ($q, $v) => $q->where('responsible_user_id', $v))->when($request->query('course_section_id'), fn ($q, $v) => $q->where('course_section_id', $v))->when($request->query('academic_year_id'), fn ($q, $v) => $q->where('academic_year_id', $v))->when($request->query('from'), fn ($q, $v) => $q->whereDate('opened_on', '>=', $v))->when($request->query('to'), fn ($q, $v) => $q->whereDate('opened_on', '<=', $v));
        return response()->json($query->latest('opened_on')->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(StoreCaseRequest $request, CaseService $service): JsonResponse
    {
        return response()->json(['message' => 'Caso social creado.', 'data' => new SocialCaseResource($service->create($request->validated(), $request->user()))], 201);
    }

    public function show(Request $request, SocialCase $case, AccessService $access, AuditService $audit): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $this->loadCaseDetail($request, $case);
        if ($case->confidentiality === 'altamente_restringido') $audit->record('case.highly_confidential_accessed', $case, $request->user());
        return response()->json(['data' => new SocialCaseResource($case)]);
    }

    public function export(Request $request, SocialCase $case, AccessService $access, AuditService $audit): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $this->loadCaseDetail($request, $case);
        $includeHighlyConfidential = $request->user()->hasPermission('social_work.highly_confidential.view');
        $audit->record('case.pdf_exported', $case, $request->user(), [], [
            'included_highly_confidential_content' => $includeHighlyConfidential,
        ]);

        return response()->json([
            'message' => 'Expediente social autorizado para exportación.',
            'generated_at' => now()->toIso8601String(),
            'data' => (new SocialCaseResource($case))->resolve($request),
        ]);
    }

    private function loadCaseDetail(Request $request, SocialCase $case): void
    {
        $case->load(self::DETAIL_RELATIONS);
        if (! $request->user()->hasPermission('social_work.highly_confidential.view')) {
            $case->interventions->each->makeHidden(['highly_confidential_notes']);
        }
    }

    public function update(Request $request, SocialCase $case, AccessService $access, AuditService $audit): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case) && $request->user()->hasPermission('social_work.cases.update'), 403);
        abort_if($case->status === 'cerrado' && ! $request->user()->hasPermission('social_work.closed_case.correct'), 422, 'El caso cerrado está bloqueado para edición ordinaria.');
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:255'], 'reason' => ['sometimes', 'string', 'max:255'], 'initial_description' => ['nullable', 'string'], 'priority' => ['sometimes', Rule::in(SocialCase::PRIORITIES)], 'risk_level' => ['sometimes', Rule::in(SocialCase::RISK_LEVELS)], 'confidentiality' => ['sometimes', Rule::in(SocialCase::CONFIDENTIALITY)], 'responsible_user_id' => ['nullable', 'exists:users,id'], 'next_milestone' => ['nullable', 'string', 'max:255'], 'due_at' => ['nullable', 'date']]);
        $old = $case->only(array_keys($data)); $case->update(array_merge($data, ['updated_by' => $request->user()->id, 'last_activity_at' => now()])); $audit->record('case.updated', $case, $request->user(), $old, $data);
        return response()->json(['message' => 'Caso actualizado.', 'data' => new SocialCaseResource($case->fresh())]);
    }

    public function changeStatus(Request $request, SocialCase $case, CaseService $service, AccessService $access): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case) && $request->user()->hasPermission('social_work.cases.update'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(SocialCase::STATUSES)], 'reason' => ['required', 'string'], 'notes' => ['nullable', 'string']]);
        return response()->json(['message' => 'Estado actualizado.', 'data' => $service->changeStatus($case, $data['status'], $request->user(), $data['reason'], $data['notes'] ?? null)]);
    }

    public function assign(Request $request, SocialCase $case, AccessService $access, AuditService $audit): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case) && $request->user()->hasPermission('social_work.cases.assign'), 403);
        $data = $request->validate(['responsible_user_id' => ['required', 'exists:users,id'], 'reason' => ['required', 'string']]);
        DB::transaction(function () use ($request, $case, $data, $audit) {
            DB::table('social_work_case_assignments')->where('case_id', $case->id)->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
            DB::table('social_work_case_assignments')->insert(['case_id' => $case->id, 'user_id' => $data['responsible_user_id'], 'role' => 'responsable', 'assigned_at' => now(), 'assigned_by' => $request->user()->id, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $old = $case->responsible_user_id; $case->update(['responsible_user_id' => $data['responsible_user_id'], 'updated_by' => $request->user()->id]); $audit->record('case.assigned', $case, $request->user(), ['responsible_user_id' => $old], ['responsible_user_id' => $data['responsible_user_id']], $data['reason']);
        });
        return response()->json(['message' => 'Caso reasignado con historial.', 'data' => $case->fresh('responsible:id,name')]);
    }

    public function close(Request $request, SocialCase $case, CaseService $service, AccessService $access): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case) && $request->user()->hasPermission('social_work.cases.close'), 403);
        $data = $request->validate(['closure_conclusion' => ['required', 'string'], 'closed_at' => ['nullable', 'date'], 'closure_result' => ['required', 'string', 'max:255'], 'closure_reason' => ['required', 'string', 'max:255'], 'final_risk_level' => ['required', Rule::in(SocialCase::RISK_LEVELS)], 'post_closure_follow_up' => ['nullable', 'string']]);
        return response()->json(['message' => 'Caso cerrado conservando su trazabilidad.', 'data' => $service->close($case, $data, $request->user())]);
    }

    public function reopen(Request $request, SocialCase $case, CaseService $service, AccessService $access): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case) && $request->user()->hasPermission('social_work.cases.reopen'), 403);
        $data = $request->validate(['reason' => ['required', 'string'], 'reopened_at' => ['nullable', 'date'], 'risk_level' => ['required', Rule::in(SocialCase::RISK_LEVELS)], 'priority' => ['required', Rule::in(SocialCase::PRIORITIES)], 'assigned_user_id' => ['required', 'exists:users,id'], 'next_action' => ['required', 'string', 'max:255']]);
        return response()->json(['message' => 'Caso reabierto sin sobrescribir el cierre anterior.', 'data' => $service->reopen($case, $data, $request->user())]);
    }

    public function timeline(Request $request, SocialCase $case, AccessService $access): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $events = collect();
        $case->statusHistory()->get()->each(fn ($e) => $events->push(['type' => 'estado', 'date' => $e->changed_at, 'title' => "Estado: {$e->to_status}", 'summary' => $e->reason]));
        $case->interventions()->get()->each(fn ($e) => $events->push(['type' => $e->kind, 'date' => $e->activity_date, 'title' => ucfirst(str_replace('_', ' ', $e->kind)), 'summary' => $e->objective, 'id' => $e->id]));
        $case->alerts()->get()->each(fn ($e) => $events->push(['type' => 'alerta', 'date' => $e->alerted_at, 'title' => 'Alerta '.$e->type, 'summary' => $e->reason, 'id' => $e->id]));
        $case->reopenings()->get()->each(fn ($e) => $events->push(['type' => 'reapertura', 'date' => $e->reopened_at, 'title' => 'Caso reabierto', 'summary' => $e->reason, 'id' => $e->id]));
        return response()->json(['data' => $events->sortByDesc('date')->values()]);
    }
}
