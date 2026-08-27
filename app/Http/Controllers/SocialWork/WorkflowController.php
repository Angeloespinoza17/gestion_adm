<?php

namespace App\Http\Controllers\SocialWork;

use App\Http\Controllers\Controller;
use App\Http\Requests\SocialWork\StoreInterventionRequest;
use App\Models\AcademicYear;
use App\Models\CalendarEvent;
use App\Models\SocialWork\Alert;
use App\Models\SocialWork\CaseProtocol;
use App\Models\SocialWork\CaseProtocolStepLink;
use App\Models\SocialWork\Commitment;
use App\Models\SocialWork\Document;
use App\Models\SocialWork\Intervention;
use App\Models\SocialWork\PedagogicalReport;
use App\Models\SocialWork\ProtocolVersion;
use App\Models\SocialWork\ProtocolZero;
use App\Models\SocialWork\Referral;
use App\Models\SocialWork\Report;
use App\Models\SocialWork\RequestedInformation;
use App\Models\SocialWork\SocialCase;
use App\Models\StudentProfile;
use App\Models\Task;
use App\Services\Inspectoria\InspectoriaAccessService;
use App\Services\SocialWork\AccessService;
use App\Services\SocialWork\AlertService;
use App\Services\SocialWork\AuditService;
use App\Services\SocialWork\CaseService;
use App\Services\SocialWork\InterventionService;
use App\Services\SocialWork\ReportGenerationService;
use App\Services\SocialWork\RiskAssessmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function interventions(Request $request, SocialCase $case, AccessService $access): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $items = $case->interventions()->with(['responsible:id,name', 'commitments'])->when($request->query('kind'), fn ($q, $v) => $q->where('kind', $v))->paginate(min((int) $request->query('per_page', 20), 100));
        if (! $request->user()->hasPermission('social_work.highly_confidential.view')) {
            $items->getCollection()->each->makeHidden(['highly_confidential_notes']);
        }

        return response()->json($items);
    }

    public function storeIntervention(StoreInterventionRequest $request, SocialCase $case, AccessService $access, InterventionService $service): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $data = $request->validated();
        if (! empty($data['highly_confidential_notes']) && ! $request->user()->hasPermission('social_work.highly_confidential.view')) {
            abort(403);
        }

        return response()->json(['message' => 'Intervención registrada.', 'data' => $service->create($case, $data, $request->user())], 201);
    }

    public function activateProtocol(Request $request, SocialCase $case, AccessService $access, AuditService $audit): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $data = $request->validate(['protocol_version_id' => ['required', 'exists:social_work_protocol_versions,id'], 'reason' => ['required', 'string'], 'responsible_user_id' => ['nullable', 'exists:users,id'], 'due_at' => ['nullable', 'date']]);
        $version = ProtocolVersion::with('protocol')->findOrFail($data['protocol_version_id']);
        $activation = DB::transaction(function () use ($data, $version, $case, $request, $audit) {
            $activation = CaseProtocol::create(['case_id' => $case->id, 'protocol_id' => $version->protocol_id, 'protocol_version_id' => $version->id, 'activated_at' => now(), 'activated_by' => $request->user()->id, 'reason' => $data['reason'], 'responsible_user_id' => $data['responsible_user_id'] ?? $request->user()->id, 'due_at' => $data['due_at'] ?? null, 'status' => 'activo', 'version_snapshot' => $version->toArray()]);
            $case->update(['last_activity_at' => now(), 'updated_by' => $request->user()->id]);
            $audit->record('protocol.activated', $activation, $request->user(), [], ['case_id' => $case->id, 'version' => $version->version]);

            return $activation;
        });

        return response()->json(['message' => 'Protocolo activado con instantánea inmutable.', 'data' => $activation->load('protocol', 'version')], 201);
    }

    public function advanceProtocol(Request $request, CaseProtocol $activation, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['step' => ['required', 'integer', 'min:0'], 'status' => ['sometimes', 'string', 'max:30'], 'conclusion' => ['nullable', 'string']]);
        $steps = $activation->version_snapshot['steps'] ?? [];
        abort_if($data['step'] >= count($steps), 422, 'La etapa no existe en la versión activada.');
        $old = $activation->only(['current_step', 'status']);
        $activation->update(['current_step' => $data['step'], 'status' => $data['status'] ?? $activation->status, 'conclusion' => $data['conclusion'] ?? $activation->conclusion]);
        $audit->record('protocol.advanced', $activation, $request->user(), $old, $data);

        return response()->json(['message' => 'Etapa de protocolo actualizada.', 'data' => $activation]);
    }

    public function linkProtocolStep(Request $request, CaseProtocol $activation, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['step' => ['required', 'integer', 'min:0'], 'link_type' => ['required', 'in:action,interview,task,document,alert,safeguard'], 'linkable_id' => ['required', 'integer']]);
        $map = ['action' => Intervention::class, 'interview' => Intervention::class, 'task' => Task::class, 'document' => Document::class, 'alert' => Alert::class, 'safeguard' => Commitment::class];
        $model = $map[$data['link_type']]::findOrFail($data['linkable_id']);
        if (isset($model->case_id) && (int) $model->case_id !== (int) $activation->case_id) {
            abort(422, 'El registro pertenece a otro caso.');
        }
        $link = CaseProtocolStepLink::firstOrCreate(['case_protocol_id' => $activation->id, 'step_index' => $data['step'], 'linkable_type' => $model::class, 'linkable_id' => $model->getKey()], ['link_type' => $data['link_type'], 'created_by' => $request->user()->id]);
        $audit->record('protocol.step_linked', $link, $request->user(), [], $data);

        return response()->json(['message' => 'Registro vinculado a la etapa del protocolo.', 'data' => $link], 201);
    }

    public function convertIntervention(Request $request, Intervention $intervention, AlertService $alerts): JsonResponse
    {
        $data = $request->validate(['target' => ['required', 'in:task,calendar,commitment,alert'], 'title' => ['nullable', 'string'], 'due_at' => ['nullable', 'date']]);
        $target = match ($data['target']) {
            'task' => Task::create(['title' => $data['title'] ?? $intervention->objective, 'description' => 'Seguimiento creado desde una intervención de Trabajo Social. No contiene relato confidencial.', 'priority' => 'media', 'status' => 'pendiente', 'stakeholder' => 'Trabajo Social', 'due_date' => isset($data['due_at']) ? date('Y-m-d', strtotime($data['due_at'])) : null, 'owner_user_id' => $intervention->responsible_user_id ?? $request->user()->id, 'created_by_user_id' => $request->user()->id]),
            'calendar' => CalendarEvent::create(['title' => $intervention->confidentiality === 'interno' ? ($data['title'] ?? 'Seguimiento de Trabajo Social') : 'Actividad reservada de Trabajo Social', 'description' => 'Abrir el módulo de Trabajo Social para consultar detalles autorizados.', 'responsible_user_id' => $intervention->responsible_user_id ?? $request->user()->id, 'start_date' => isset($data['due_at']) ? date('Y-m-d', strtotime($data['due_at'])) : $intervention->activity_date, 'priority' => 'media', 'status' => 'pendiente', 'event_kind' => 'single', 'external_url' => '/social-work/cases/'.$intervention->case_id, 'created_by' => $request->user()->id]),
            'commitment' => Commitment::create(['case_id' => $intervention->case_id, 'intervention_id' => $intervention->id, 'description' => $data['title'] ?? $intervention->next_action ?? $intervention->objective, 'responsible_user_id' => $intervention->responsible_user_id, 'due_at' => $data['due_at'] ?? $intervention->due_at, 'status' => 'pendiente']),
            'alert' => $alerts->raise(['deduplication_key' => 'intervention:'.$intervention->id.':manual', 'type' => 'seguimiento_intervencion', 'student_profile_id' => $intervention->student_profile_id, 'case_id' => $intervention->case_id, 'severity' => 'medio', 'reason' => 'Intervención convertida en alerta de seguimiento.', 'responsible_user_id' => $intervention->responsible_user_id, 'due_at' => $data['due_at'] ?? $intervention->due_at, 'confidentiality' => 'interno']),
        };

        return response()->json(['message' => 'Intervención convertida sin copiar contenido confidencial.', 'data' => $target], 201);
    }

    public function protocolZero(Request $request, SocialCase $case, AccessService $access, AuditService $audit): JsonResponse
    {
        abort_unless($access->canViewCase($request->user(), $case), 403);
        $data = $request->validate(['received_at' => ['required', 'date'], 'channel' => ['required', 'string', 'max:60'], 'informant_name' => ['required', 'string'], 'informant_relationship' => ['nullable', 'string'], 'initial_account' => ['required', 'string'], 'immediate_risk' => ['required', 'string', 'max:30'], 'urgent_attention' => ['boolean'], 'initial_safeguards' => ['nullable', 'string'], 'notified_people' => ['nullable', 'array'], 'evaluation_responsible_id' => ['required', 'exists:users,id'], 'evaluation_due_at' => ['required', 'date'], 'pending_background' => ['nullable', 'string']]);
        $record = ProtocolZero::create(array_merge($data, ['case_id' => $case->id, 'created_by' => $request->user()->id, 'status' => 'recibido']));
        $audit->record('protocol_zero.received', $record, $request->user(), [], ['case_id' => $case->id, 'urgent_attention' => $record->urgent_attention]);

        return response()->json(['message' => 'Recepción registrada sin determinar responsabilidades.', 'data' => $record], 201);
    }

    public function referrals(Request $request, InspectoriaAccessService $inspectoria): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $query = Referral::with([
            'student:id,first_name,last_name,registered_name,rut',
            'courseSection:id,display_name',
            'assignedUser:id,name',
            'creator:id,name',
            'case:id,code,title',
        ]);
        if ($inspectoria->isCourseScoped($request->user())) {
            $query->where('created_by', $request->user()->id);
        } elseif (! $request->user()->hasPermission('social_work.referrals.manage')) {
            $query->where(fn (Builder $visibility) => $visibility
                ->where('created_by', $request->user()->id)
                ->orWhere('assigned_user_id', $request->user()->id));
        }

        return response()->json($query
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('reason', 'like', "%{$search}%")
                ->orWhere('source_unit', 'like', "%{$search}%")
                ->orWhere('source_person', 'like', "%{$search}%")
                ->orWhereHas('student', fn (Builder $student) => $student
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registered_name', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%"))))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('referral_date')
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function referralStudents(Request $request, InspectoriaAccessService $inspectoria): JsonResponse
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $search = trim((string) $request->query('search'));
        $query = StudentProfile::query()
            ->select(['id', 'first_name', 'last_name', 'registered_name', 'rut'])
            ->with(['enrollments' => fn ($enrollments) => $enrollments
                ->when($activeYear, fn ($inner) => $inner->where('academic_year_id', $activeYear->id))
                ->with('courseSection:id,display_name')
                ->latest('id')])
            ->when($search !== '', fn (Builder $students) => $students->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('registered_name', 'like', "%{$search}%")
                ->orWhere('rut', 'like', "%{$search}%")));

        if ($inspectoria->isCourseScoped($request->user())) {
            $courseIds = $inspectoria->assignedCourseIds($request->user());
            $query->whereHas('enrollments', fn (Builder $enrollments) => $enrollments
                ->whereIn('course_section_id', $courseIds)
                ->when($activeYear, fn (Builder $inner) => $inner->where('academic_year_id', $activeYear->id)));
        }

        $students = $query->orderBy('last_name')->orderBy('first_name')->limit(300)->get()
            ->map(function (StudentProfile $student) {
                $enrollment = $student->enrollments->first();

                return [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'registered_name' => $student->registered_name,
                    'registered_name_resolved' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'course_section_id' => $enrollment?->course_section_id,
                    'course' => $enrollment?->courseSection?->display_name ?? $enrollment?->snapshot_course_display_name,
                ];
            });

        return response()->json(['data' => $students]);
    }

    public function storeReferral(Request $request, InspectoriaAccessService $inspectoria): JsonResponse
    {
        $data = $request->validate(['student_profile_id' => ['required', 'exists:student_profiles,id'], 'course_section_id' => ['nullable', 'exists:course_sections,id'], 'referral_date' => ['required', 'date'], 'source_unit' => ['required', 'string', 'max:80'], 'source_person' => ['nullable', 'string'], 'reason' => ['required', 'string'], 'description' => ['nullable', 'string'], 'observed_background' => ['nullable', 'string'], 'previous_actions' => ['nullable', 'string'], 'urgency' => ['required', 'in:baja,normal,alta,urgente'], 'immediate_risk' => ['boolean'], 'contact_data' => ['nullable', 'string'], 'status' => ['sometimes', 'in:borrador,enviada'], 'confidentiality' => ['sometimes', 'in:interno,restringido,altamente_restringido']]);
        abort_unless($inspectoria->canAccessStudent($request->user(), (int) $data['student_profile_id']), 403, 'La alumna no pertenece a los cursos asignados.');
        $referral = Referral::create(array_merge($data, ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]));

        return response()->json(['message' => 'Derivación enviada a Trabajo Social. Esto no concede acceso al caso.', 'data' => $referral->load(['student', 'courseSection', 'creator'])], 201);
    }

    public function convertReferral(Request $request, Referral $referral, CaseService $cases): JsonResponse
    {
        abort_unless($request->user()->hasPermission('social_work.referrals.manage') && $request->user()->hasPermission('social_work.cases.create'), 403);
        abort_if($referral->case_id, 422, 'La derivación ya está vinculada a un caso.');
        $data = $request->validate(['title' => ['required', 'string'], 'priority' => ['required', 'in:baja,media,alta,urgente'], 'risk_level' => ['required', 'in:sin_evaluar,bajo,medio,alto,critico'], 'responsible_user_id' => ['required', 'exists:users,id']]);
        $case = $cases->create(array_merge($data, ['primary_student_id' => $referral->student_profile_id, 'course_section_id' => $referral->course_section_id, 'origin' => 'derivacion', 'reason' => $referral->reason, 'initial_description' => $referral->description, 'confidentiality' => $referral->confidentiality, 'status' => 'recibido']), $request->user());
        $referral->update(['case_id' => $case->id, 'status' => 'convertida_caso', 'received_at' => now(), 'updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Derivación convertida en caso.', 'data' => $case], 201);
    }

    public function requestPedagogicalReport(Request $request): JsonResponse
    {
        $data = $request->validate(['student_profile_id' => ['required', 'exists:student_profiles,id'], 'case_id' => ['nullable', 'exists:social_work_cases,id'], 'requested_from_user_id' => ['required', 'exists:users,id'], 'period' => ['nullable', 'string'], 'due_at' => ['required', 'date']]);
        $report = PedagogicalReport::create(array_merge($data, ['requested_by' => $request->user()->id, 'requested_at' => now(), 'status' => 'solicitado']));

        return response()->json(['message' => 'Informe solicitado sin conceder acceso al caso.', 'data' => $report], 201);
    }

    public function respondPedagogicalReport(Request $request, PedagogicalReport $report): JsonResponse
    {
        abort_unless((int) $report->requested_from_user_id === (int) $request->user()->id || $request->user()->hasPermission('social_work.pedagogical_reports.request'), 403);
        $data = $request->validate(['response_data' => ['required', 'array']]);
        $report->update(['response_data' => $data['response_data'], 'responded_at' => now(), 'status' => 'respondido']);

        return response()->json(['message' => 'Informe pedagógico respondido.', 'data' => $report]);
    }

    public function addRequestedInformation(Request $request, SocialCase $case): JsonResponse
    {
        $data = $request->validate(['item' => ['required', 'string'], 'requested_from' => ['nullable', 'string'], 'requested_at' => ['nullable', 'date'], 'due_at' => ['nullable', 'date'], 'status' => ['sometimes', 'in:pendiente,solicitado,recibido,incompleto,no_disponible,vencido,descartado'], 'notes' => ['nullable', 'string'], 'responsible_user_id' => ['nullable', 'exists:users,id']]);

        return response()->json(['data' => RequestedInformation::create(array_merge($data, ['case_id' => $case->id, 'created_by' => $request->user()->id]))], 201);
    }

    public function assessRisk(Request $request, SocialCase $case, RiskAssessmentService $service): JsonResponse
    {
        $data = $request->validate(['period_from' => ['nullable', 'date'], 'period_to' => ['nullable', 'date'], 'final_level' => ['nullable', 'in:sin_evaluar,bajo,medio,alto,critico'], 'override_justification' => ['nullable', 'string'], 'notes' => ['nullable', 'string']]);

        return response()->json(['message' => 'Evaluación registrada como apoyo profesional, no como decisión automática.', 'data' => $service->evaluate($case->primary_student_id, $case, $request->user(), $data)]);
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json(Alert::with(['student:id,first_name,last_name,registered_name,rut', 'responsible:id,name'])->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))->when($request->query('severity'), fn ($q, $v) => $q->where('severity', $v))->latest('alerted_at')->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function resolveAlert(Request $request, Alert $alert, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['resolution' => ['required', 'string']]);
        $old = $alert->status;
        $alert->update(['status' => 'resuelta', 'resolution' => $data['resolution'], 'resolved_at' => now(), 'resolved_by' => $request->user()->id]);
        $audit->record('alert.resolved', $alert, $request->user(), ['status' => $old], ['status' => 'resuelta'], $data['resolution']);

        return response()->json(['message' => 'Alerta resuelta.', 'data' => $alert]);
    }

    public function updateAlert(Request $request, Alert $alert, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'in:nueva,vista,en_gestion,pospuesta,resuelta,descartada'], 'responsible_user_id' => ['nullable', 'exists:users,id'], 'due_at' => ['nullable', 'date'], 'recommended_action' => ['nullable', 'string']]);
        if (($data['status'] ?? null) === 'resuelta') {
            abort(422, 'Utilice la acción resolver e indique la resolución.');
        }
        $old = $alert->only(array_keys($data));
        $alert->update($data);
        $audit->record('alert.updated', $alert, $request->user(), $old, $data);

        return response()->json(['message' => 'Alerta actualizada.', 'data' => $alert]);
    }

    public function commentAlert(Request $request, Alert $alert): JsonResponse
    {
        $data = $request->validate(['comment' => ['required', 'string']]);
        $id = DB::table('social_work_alert_comments')->insertGetId(['alert_id' => $alert->id, 'user_id' => $request->user()->id, 'comment' => $data['comment'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Comentario agregado.', 'data' => ['id' => $id]], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        return response()->json(Report::with(['case:id,code,title', 'versions'])->latest()->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function masterReport(Request $request, SocialCase $case, ReportGenerationService $service): JsonResponse
    {
        $data = $request->validate(['sections' => ['nullable', 'array']]);

        return response()->json(['message' => 'Borrador generado; requiere revisión humana.', 'data' => $service->createDraft($case, $request->user(), $data['sections'] ?? [])], 201);
    }

    public function versionReport(Request $request, Report $report, ReportGenerationService $service): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string'], 'change_reason' => ['required', 'string']]);

        return response()->json(['data' => $service->addVersion($report, $data['content'], $data['change_reason'], $request->user())]);
    }

    public function approveReport(Request $request, Report $report, AuditService $audit): JsonResponse
    {
        abort_unless($request->user()->hasPermission('social_work.reports.approve'), 403);
        $report->update(['status' => 'aprobado', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $audit->record('report.approved', $report, $request->user());

        return response()->json(['message' => 'Informe aprobado.', 'data' => $report]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $events = collect();
        SocialCase::whereNotNull('due_at')->whereNotIn('status', ['cerrado', 'anulado'])->get()->each(fn ($case) => $events->push(['id' => 'case-'.$case->id, 'type' => 'case', 'title' => $case->confidentiality === 'interno' ? "Caso {$case->code}: {$case->next_milestone}" : 'Actividad reservada de Trabajo Social', 'start' => $case->due_at, 'url' => "/social-work/cases/{$case->id}", 'confidential' => $case->confidentiality !== 'interno']));

        return response()->json(['data' => $events]);
    }
}
