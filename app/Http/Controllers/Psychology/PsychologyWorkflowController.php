<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Http\Requests\Psychology\SavePsychologyActivityRequest;
use App\Models\Psychology\PsychologyActivity;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyConsent;
use App\Models\Psychology\PsychologyCoordinationRequest;
use App\Models\Psychology\PsychologyExternalReferral;
use App\Models\Psychology\PsychologyInterventionPlan;
use App\Models\Psychology\PsychologyRiskAssessment;
use App\Models\Psychology\PsychologySharedFeedback;
use App\Models\Psychology\PsychologyTask;
use App\Services\Psychology\PsychologyAccessService;
use App\Services\Psychology\PsychologyAuditService;
use App\Services\Psychology\PsychologyCoordinationService;
use App\Services\Psychology\PsychologyNotificationService;
use App\Services\Records\InterviewRecordRevisionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PsychologyWorkflowController extends Controller
{
    private const FOLLOW_UP_TYPE_LABELS = [
        'phone_call' => 'Llamada telefónica',
        'new_interview' => 'Nueva entrevista',
        'guardian_contact' => 'Contacto con apoderado(a)',
        'student_check_in' => 'Seguimiento con estudiante',
        'teacher_coordination' => 'Coordinación con docente',
        'external_coordination' => 'Coordinación externa',
        'case_review' => 'Revisión de caso',
        'other' => 'Otro seguimiento',
    ];

    public function __construct(
        private readonly PsychologyAuditService $audit,
        private readonly PsychologyNotificationService $notifications,
        private readonly PsychologyAccessService $access,
        private readonly PsychologyCoordinationService $coordinationService,
        private readonly InterviewRecordRevisionService $revisions,
    ) {}

    public function storeActivity(SavePsychologyActivityRequest $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        abort_unless($request->user()->hasPermission('psychology.sessions.create'), 403);
        $activity = DB::transaction(function () use ($request, $case) {
            $payload = $request->validated();
            $coordinationPayload = $payload['coordination'] ?? null;
            unset($payload['coordination']);
            $user = $request->user()->loadMissing(['cargo', 'staff.cargo']);
            $interviewTypes = ['student_interview', 'guardian_interview', 'teacher_interview'];

            if (in_array($payload['type'], $interviewTypes, true) && empty($payload['interview_number'])) {
                $payload['interview_number'] = ((int) $case->activities()
                    ->whereIn('type', $interviewTypes)
                    ->lockForUpdate()
                    ->max('interview_number')) + 1;
            }

            $payload['interviewer_name_snapshot'] = $user->name;
            $payload['interviewer_position_snapshot'] = $payload['interviewer_position_snapshot']
                ?? $user->staff?->cargo?->name
                ?? $user->cargo?->name;

            if (($payload['acknowledgement_status'] ?? null) === 'acknowledged' && empty($payload['acknowledged_at'])) {
                $payload['acknowledged_at'] = now();
            }

            $finalized = ($payload['status'] ?? 'draft') === 'finalized';
            $nextReviewOn = $payload['next_action_on'] ?? $case->next_review_on;
            $activity = $case->activities()->create($payload + ['responsible_user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'finalized_at' => $finalized ? now() : null, 'finalized_by' => $finalized ? $user->id : null]);
            $case->forceFill([
                'last_activity_at' => now(),
                'next_action' => $payload['next_steps'] ?? $case->next_action,
                'next_review_on' => $nextReviewOn,
                'updated_by' => $user->id,
            ])->save();
            $this->audit->record('activity.created', $activity, $user, [], ['status' => $activity->status, 'type' => $activity->type, 'interview_number' => $activity->interview_number]);
            if ($coordinationPayload) {
                $this->coordinationService->create($case, $coordinationPayload, $user, $activity);
            }

            return $activity;
        });

        return response()->json(['message' => 'Actividad registrada.', 'data' => $activity->load('responsibleUser:id,name')], 201);
    }

    public function updateActivity(SavePsychologyActivityRequest $request, PsychologyActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->case);
        abort_unless($this->access->canManageActivity($request->user(), $activity), 403);
        abort_unless($request->user()->hasPermission('psychology.sessions.create'), 403);

        $updated = DB::transaction(function () use ($request, $activity) {
            $locked = PsychologyActivity::query()->lockForUpdate()->findOrFail($activity->id);
            $expectedVersion = Carbon::parse($request->validated('record_updated_at'));
            if (! $locked->updated_at?->equalTo($expectedVersion)) {
                throw ValidationException::withMessages([
                    'record' => 'La ficha fue modificada por otra persona. Recárgala antes de guardar tus cambios.',
                ]);
            }

            $payload = $request->safe()->except(['change_reason', 'record_updated_at', 'coordination']);
            if ($locked->status === 'finalized' && ($payload['status'] ?? 'finalized') !== 'finalized') {
                throw ValidationException::withMessages([
                    'status' => 'Una ficha finalizada no puede volver a borrador.',
                ]);
            }

            $snapshotFields = array_values(array_unique([...array_keys($payload), 'status', 'finalized_at', 'finalized_by']));
            $before = $locked->only($snapshotFields);
            $finalizing = $locked->status === 'draft' && ($payload['status'] ?? 'draft') === 'finalized';
            if ($finalizing) {
                $payload['finalized_at'] = now();
                $payload['finalized_by'] = $request->user()->id;
            }

            $locked->fill($payload)->forceFill(['updated_by' => $request->user()->id])->save();
            $after = $locked->fresh()->only($snapshotFields);
            $reason = $request->string('change_reason')->toString();

            $this->revisions->record(
                'psychology',
                $locked,
                $request->user(),
                $reason,
                $before,
                $after,
                $locked->case_id,
            );
            $this->audit->record('activity.updated', $locked, $request->user(), $before, $after, $reason);

            $latest = $locked->case->activities()->latest('activity_on')->latest('id')->first();
            $locked->case->forceFill([
                'last_activity_at' => now(),
                'next_action' => $latest?->next_steps ?? $locked->case->next_action,
                'next_review_on' => $latest?->next_action_on ?? $locked->case->next_review_on,
                'updated_by' => $request->user()->id,
            ])->save();

            return $locked->fresh(['responsibleUser:id,name', 'addenda.author:id,name']);
        });

        return response()->json(['message' => 'Ficha corregida con historial protegido.', 'data' => $updated]);
    }

    public function finalizeActivity(Request $request, PsychologyActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->case);
        abort_unless(app(PsychologyAccessService::class)->canManageActivity($request->user(), $activity), 403);
        abort_unless($request->user()->hasPermission('psychology.sessions.create'), 403);
        if ($activity->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'La actividad ya fue finalizada. Usa una adenda para corregirla.']);
        }
        $activity->forceFill(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
        $this->audit->record('activity.finalized', $activity, $request->user());

        return response()->json(['message' => 'Registro finalizado.', 'data' => $activity]);
    }

    public function exportActivity(Request $request, PsychologyActivity $activity): JsonResponse
    {
        $activity->loadMissing(['case.student.enrollments', 'responsibleUser:id,name']);
        $case = $activity->case;
        $this->authorize('view', $case);
        abort_unless($this->access->canManageActivity($request->user(), $activity), 404);

        $student = $case->student;
        $enrollment = $student?->preferredEnrollment();
        $includePrivate = $this->access->canViewPrivateNotes($request->user(), $case);

        $this->audit->record('activity.pdf_exported', $activity, $request->user(), [], [
            'status' => $activity->status,
            'included_private_content' => $includePrivate,
        ]);

        return response()->json([
            'message' => 'Datos de exportación autorizados.',
            'data' => [
                'generated_at' => now()->toIso8601String(),
                'case' => [
                    'id' => $case->id,
                    'code' => $case->code,
                    'status' => $case->status,
                ],
                'student' => [
                    'name' => $student?->registered_name_resolved,
                    'rut' => $student?->rut,
                    'course' => $enrollment?->snapshot_course_display_name,
                ],
                'activity' => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'interview_number' => $activity->interview_number,
                    'activity_on' => $activity->activity_on?->toDateString(),
                    'starts_at' => $activity->starts_at,
                    'ends_at' => $activity->ends_at,
                    'modality' => $activity->modality,
                    'location' => $activity->location,
                    'participant_types' => $activity->participant_types,
                    'participants' => $activity->participants,
                    'interviewee_type' => $activity->interviewee_type,
                    'interviewee_name' => $activity->interviewee_name,
                    'interviewee_rut' => $includePrivate ? $activity->interviewee_rut : null,
                    'interviewer_name' => $activity->interviewer_name_snapshot ?: $activity->responsibleUser?->name,
                    'interviewer_position' => $activity->interviewer_position_snapshot,
                    'objective' => $activity->objective,
                    'institutional_summary' => $activity->institutional_summary,
                    'general_background' => $includePrivate ? $activity->general_background : null,
                    'private_note' => $includePrivate ? $activity->private_note : null,
                    'result' => $activity->result,
                    'agreements' => $activity->agreements,
                    'next_steps' => $activity->next_steps,
                    'next_action_on' => $activity->next_action_on?->toDateString(),
                    'follow_up_type' => $activity->follow_up_type,
                    'follow_up_type_label' => self::FOLLOW_UP_TYPE_LABELS[$activity->follow_up_type] ?? null,
                    'attendance_status' => $activity->attendance_status,
                    'visibility' => $activity->visibility,
                    'referral_feedback' => $activity->referral_feedback,
                    'status' => $activity->status,
                    'finalized_at' => $activity->finalized_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function addAddendum(Request $request, PsychologyActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->case);
        abort_unless(app(PsychologyAccessService::class)->canManageActivity($request->user(), $activity), 403);
        abort_unless($activity->status === 'finalized', 422);
        $payload = $request->validate(['content' => ['required', 'string', 'max:12000'], 'reason' => ['required', 'string', 'max:2000'], 'visibility' => ['required', Rule::in(['private_psychology', 'psychology_team', 'interdisciplinary_team', 'referral_feedback'])]]);
        $addendum = $activity->addenda()->create($payload + ['created_by' => $request->user()->id]);
        $this->audit->record('activity.addendum_created', $activity, $request->user(), [], [], $payload['reason']);

        return response()->json(['message' => 'Adenda registrada sin alterar el registro original.', 'data' => $addendum->load('author:id,name')], 201);
    }

    public function storePlan(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        $payload = $request->validate(['status' => ['sometimes', Rule::in(['draft', 'active', 'under_review', 'completed', 'replaced', 'cancelled'])], 'review_on' => ['nullable', 'date'], 'general_situation' => ['required', 'string', 'max:6000'], 'general_objective' => ['required', 'string', 'max:4000'], 'specific_objectives' => ['nullable', 'string', 'max:8000'], 'planned_actions' => ['nullable', 'string', 'max:10000'], 'responsibles' => ['nullable', 'string', 'max:4000'], 'frequency' => ['nullable', 'string', 'max:100'], 'estimated_start_on' => ['nullable', 'date'], 'estimated_end_on' => ['nullable', 'date', 'after_or_equal:estimated_start_on'], 'monitoring_indicators' => ['nullable', 'string', 'max:6000'], 'participants' => ['nullable', 'string', 'max:4000'], 'family_coordination' => ['nullable', 'string', 'max:6000'], 'teacher_coordination' => ['nullable', 'string', 'max:6000'], 'coexistence_coordination' => ['nullable', 'string', 'max:6000'], 'external_coordination' => ['nullable', 'string', 'max:6000'], 'review_result' => ['nullable', 'string', 'max:6000']]);
        $plan = DB::transaction(function () use ($case, $payload, $request) {
            $plan = PsychologyInterventionPlan::query()->create(['case_id' => $case->id, 'responsible_user_id' => $request->user()->id, 'status' => $payload['status'] ?? 'draft', 'review_on' => $payload['review_on'] ?? null, 'current_version' => 1, 'created_by' => $request->user()->id]);
            $plan->versions()->create(collect($payload)->except(['status', 'review_on'])->all() + ['version' => 1, 'created_by' => $request->user()->id]);
            $this->audit->record('plan.created', $plan, $request->user(), [], ['version' => 1, 'status' => $plan->status]);

            return $plan;
        });

        return response()->json(['message' => 'Plan creado.', 'data' => $plan->load('versions')], 201);
    }

    public function versionPlan(Request $request, PsychologyInterventionPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan->case);
        $payload = $request->validate(['general_situation' => ['required', 'string', 'max:6000'], 'general_objective' => ['required', 'string', 'max:4000'], 'specific_objectives' => ['nullable', 'string', 'max:8000'], 'planned_actions' => ['nullable', 'string', 'max:10000'], 'responsibles' => ['nullable', 'string', 'max:4000'], 'frequency' => ['nullable', 'string', 'max:100'], 'estimated_start_on' => ['nullable', 'date'], 'estimated_end_on' => ['nullable', 'date'], 'monitoring_indicators' => ['nullable', 'string', 'max:6000'], 'participants' => ['nullable', 'string', 'max:4000'], 'family_coordination' => ['nullable', 'string', 'max:6000'], 'teacher_coordination' => ['nullable', 'string', 'max:6000'], 'coexistence_coordination' => ['nullable', 'string', 'max:6000'], 'external_coordination' => ['nullable', 'string', 'max:6000'], 'review_result' => ['nullable', 'string', 'max:6000']]);
        $version = DB::transaction(function () use ($plan, $payload, $request) {
            $next = $plan->current_version + 1;
            $version = $plan->versions()->create($payload + ['version' => $next, 'created_by' => $request->user()->id]);
            $plan->forceFill(['current_version' => $next])->save();
            $this->audit->record('plan.versioned', $plan, $request->user(), ['version' => $next - 1], ['version' => $next]);

            return $version;
        });

        return response()->json(['message' => 'Nueva versión creada.', 'data' => $version], 201);
    }

    public function exportPlan(Request $request, PsychologyInterventionPlan $plan): JsonResponse
    {
        $plan->loadMissing([
            'case.student.enrollments',
            'responsibleUser:id,name',
            'versions.author:id,name',
        ]);
        $case = $plan->case;
        $this->authorize('view', $case);

        $student = $case->student;
        $enrollment = $student?->preferredEnrollment();
        $version = $plan->versions->firstWhere('version', $plan->current_version)
            ?? $plan->versions->first();

        abort_unless($version, 404);

        $this->audit->record('plan.pdf_exported', $plan, $request->user(), [], [
            'version' => $version->version,
            'status' => $plan->status,
        ]);

        return response()->json([
            'message' => 'Datos del plan autorizados para exportación.',
            'data' => [
                'generated_at' => now()->toIso8601String(),
                'case' => [
                    'id' => $case->id,
                    'code' => $case->code,
                    'status' => $case->status,
                ],
                'student' => [
                    'name' => $student?->registered_name_resolved,
                    'rut' => $student?->rut,
                    'course' => $enrollment?->snapshot_course_display_name,
                ],
                'plan' => [
                    'id' => $plan->id,
                    'status' => $plan->status,
                    'current_version' => $plan->current_version,
                    'review_on' => $plan->review_on?->toDateString(),
                    'responsible_name' => $plan->responsibleUser?->name,
                    'created_at' => $plan->created_at?->toIso8601String(),
                    'version' => [
                        'number' => $version->version,
                        'general_situation' => $version->general_situation,
                        'general_objective' => $version->general_objective,
                        'specific_objectives' => $version->specific_objectives,
                        'planned_actions' => $version->planned_actions,
                        'responsibles' => $version->responsibles,
                        'frequency' => $version->frequency,
                        'estimated_start_on' => $version->estimated_start_on?->toDateString(),
                        'estimated_end_on' => $version->estimated_end_on?->toDateString(),
                        'monitoring_indicators' => $version->monitoring_indicators,
                        'participants' => $version->participants,
                        'family_coordination' => $version->family_coordination,
                        'teacher_coordination' => $version->teacher_coordination,
                        'coexistence_coordination' => $version->coexistence_coordination,
                        'external_coordination' => $version->external_coordination,
                        'review_result' => $version->review_result,
                        'author_name' => $version->author?->name,
                        'created_at' => $version->created_at?->toIso8601String(),
                    ],
                ],
            ],
        ]);
    }

    public function storeRisk(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        abort_unless($request->user()->hasPermission('psychology.risk.create'), 403);
        $payload = $request->validate(['risk_type' => ['required', 'string', 'max:80'], 'level' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])], 'structured_indicators' => ['nullable', 'string', 'max:6000'], 'professional_rationale' => ['nullable', 'string', 'max:10000'], 'immediate_action' => ['required_if:level,critical', 'nullable', 'string', 'max:6000'], 'response_responsible_user_id' => ['required_if:level,critical', 'nullable', 'integer', 'exists:users,id'], 'response_at' => ['required_if:level,critical', 'nullable', 'date'], 'protocol_reference' => ['required_if:level,critical', 'nullable', 'string', 'max:160']]);
        $risk = DB::transaction(function () use ($case, $payload, $request) {
            $risk = $case->riskAssessments()->create($payload + ['created_by' => $request->user()->id]);
            if ($risk->level === 'critical') {
                $case->forceFill(['priority' => 'critical', 'updated_by' => $request->user()->id])->save();
                $risk->actions()->create(['action' => $payload['immediate_action'], 'responsible_user_id' => $payload['response_responsible_user_id'], 'action_at' => $payload['response_at'], 'created_by' => $request->user()->id]);
            } $this->audit->record('risk.created', $risk, $request->user(), [], ['level' => $risk->level, 'risk_type' => $risk->risk_type]);

            return $risk;
        });
        if ($risk->level === 'critical') {
            $this->notifications->criticalRisk($case);
        }

        return response()->json(['message' => 'Evaluación profesional registrada. Esta clasificación no constituye un diagnóstico.', 'data' => $risk->load('actions')], 201);
    }

    public function acknowledgeRisk(Request $request, PsychologyRiskAssessment $risk): JsonResponse
    {
        $this->authorize('view', $risk->case);
        abort_unless($request->user()->hasPermission('psychology.risk.view'), 403);
        $risk->forceFill(['acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id])->save();
        $this->audit->record('risk.acknowledged', $risk, $request->user());

        return response()->json(['message' => 'Alerta reconocida.', 'data' => $risk]);
    }

    public function storeTask(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        $payload = $request->validate(['activity_id' => ['nullable', 'integer', 'exists:psychology_activities,id'], 'responsible_user_id' => ['required', 'integer', 'exists:users,id'], 'title' => ['required', 'string', 'max:191'], 'description' => ['nullable', 'string', 'max:4000'], 'type' => ['nullable', 'string', 'max:80'], 'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])], 'due_at' => ['nullable', 'date'], 'remind_at' => ['nullable', 'date', 'before_or_equal:due_at']]);
        if (! empty($payload['activity_id']) && ! $case->activities()->whereKey($payload['activity_id'])->exists()) {
            throw ValidationException::withMessages(['activity_id' => 'La actividad seleccionada no pertenece a este caso.']);
        }
        $task = $case->tasks()->create($payload + ['status' => 'pending', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->record('task.created', $task, $request->user(), [], ['priority' => $task->priority, 'due_at' => $task->due_at]);

        return response()->json(['message' => 'Tarea creada.', 'data' => $task->load('responsibleUser:id,name')], 201);
    }

    public function updateTask(Request $request, PsychologyTask $task): JsonResponse
    {
        $this->authorize('update', $task->case);
        $payload = $request->validate(['status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'overdue', 'cancelled'])], 'completion_evidence' => ['required_if:status,completed', 'nullable', 'string', 'max:4000']]);
        $old = $task->status;
        $task->forceFill($payload + ['completed_at' => $payload['status'] === 'completed' ? now() : null, 'updated_by' => $request->user()->id])->save();
        $this->audit->record('task.status_changed', $task, $request->user(), ['status' => $old], ['status' => $task->status]);

        return response()->json(['message' => 'Tarea actualizada.', 'data' => $task]);
    }

    public function storeConsent(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        $payload = $request->validate(['action_type' => ['nullable', 'string', 'max:80'], 'guardian_informed' => ['required', 'boolean'], 'informed_at' => ['nullable', 'date'], 'contact_method' => ['nullable', 'string', 'max:60'], 'contact_result' => ['nullable', 'string', 'max:100'], 'consent_required' => ['required', 'boolean'], 'status' => ['required', Rule::in(['pending', 'granted', 'rejected', 'not_required', 'exception'])], 'institutional_exception' => ['required', 'boolean'], 'observations' => ['nullable', 'string', 'max:4000']]);
        $consent = PsychologyConsent::query()->create($payload + ['case_id' => $case->id, 'contacted_by' => $request->user()->id, 'created_by' => $request->user()->id]);
        $this->audit->record('consent.created', $consent, $request->user(), [], ['status' => $consent->status, 'consent_required' => $consent->consent_required]);

        return response()->json(['message' => 'Registro de comunicación/consentimiento guardado.', 'data' => $consent], 201);
    }

    public function storeExternalReferral(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        $payload = $request->validate(['institution' => ['required', 'string', 'max:191'], 'institution_type' => ['nullable', 'string', 'max:100'], 'general_reason' => ['required', 'string', 'max:4000'], 'referred_on' => ['required', 'date'], 'guardian_informed' => ['required', 'boolean'], 'reception_status' => ['nullable', 'string', 'max:40'], 'follow_up_pending' => ['required', 'boolean'], 'next_contact_on' => ['nullable', 'date']]);
        $external = PsychologyExternalReferral::query()->create($payload + ['case_id' => $case->id, 'referred_by' => $request->user()->id, 'status' => 'open']);
        $this->audit->record('external_referral.created', $external, $request->user(), [], ['institution_type' => $external->institution_type, 'status' => $external->status]);

        return response()->json(['message' => 'Derivación externa registrada.', 'data' => $external], 201);
    }

    public function storeFeedback(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        $payload = $request->validate(['referral_id' => ['nullable', 'integer', 'exists:psychology_referrals,id'], 'content' => ['required', 'string', 'max:8000']]);
        if (! empty($payload['referral_id']) && ! $case->referrals()->whereKey($payload['referral_id'])->exists() && (int) $case->origin_referral_id !== (int) $payload['referral_id']) {
            throw ValidationException::withMessages(['referral_id' => 'La derivación seleccionada no pertenece a este caso.']);
        }
        $feedback = PsychologySharedFeedback::query()->create($payload + ['case_id' => $case->id, 'visibility' => 'referral_feedback', 'created_by' => $request->user()->id]);
        $this->audit->record('feedback.shared', $feedback, $request->user());

        return response()->json(['message' => 'Retroalimentación compartible publicada.', 'data' => $feedback], 201);
    }

    public function followUps(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'overdue'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:10,500'],
        ]);
        $visibleCases = PsychologyCase::query()->select('psychology_cases.id');
        $this->access->applyCaseVisibility($visibleCases, $request->user());

        $query = PsychologyActivity::query()
            ->whereNotNull('next_action_on')
            ->whereIn('case_id', $visibleCases)
            ->with([
                'case:id,code,student_profile_id,responsible_user_id,status,priority',
                'case.student.enrollments',
                'case.responsibleUser:id,name',
                'responsibleUser:id,name',
            ])
            ->when($filters['from'] ?? null, fn ($activities, $from) => $activities->whereDate('next_action_on', '>=', $from))
            ->when($filters['to'] ?? null, fn ($activities, $to) => $activities->whereDate('next_action_on', '<=', $to))
            ->when(($filters['status'] ?? null) === 'pending', fn ($activities) => $activities
                ->whereDate('next_action_on', '>=', today())
                ->whereHas('case', fn ($case) => $case->where('status', '!=', 'closed')))
            ->when(($filters['status'] ?? null) === 'overdue', fn ($activities) => $activities
                ->whereDate('next_action_on', '<', today())
                ->whereHas('case', fn ($case) => $case->where('status', '!=', 'closed')))
            ->when(($filters['status'] ?? null) === 'completed', fn ($activities) => $activities
                ->whereHas('case', fn ($case) => $case->where('status', 'closed')))
            ->when($filters['search'] ?? null, function ($activities, $search) {
                $term = trim($search);
                $activities->where(function ($match) use ($term) {
                    $match->where('next_steps', 'like', "%{$term}%")
                        ->orWhereHas('case', fn ($case) => $case
                            ->where('code', 'like', "%{$term}%")
                            ->orWhereHas('student', fn ($student) => $student
                                ->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhere('registered_name', 'like', "%{$term}%")));
                });
            })
            ->orderBy('next_action_on')
            ->orderBy('id');
        $this->access->applyActivityVisibility($query, $request->user());

        $page = $query->paginate($filters['per_page'] ?? 25)->through(function (PsychologyActivity $activity) {
            $student = $activity->case?->student;
            $enrollment = $student?->preferredEnrollment();
            $completed = $activity->case?->status === 'closed';
            $overdue = ! $completed && $activity->next_action_on?->isBefore(today());

            return [
                'id' => $activity->id,
                'activity_id' => $activity->id,
                'case_id' => $activity->case_id,
                'case_code' => $activity->case?->code,
                'case_status' => $activity->case?->status,
                'case_priority' => $activity->case?->priority,
                'student_name' => $student?->registered_name_resolved,
                'course' => $enrollment?->snapshot_course_display_name,
                'title' => self::FOLLOW_UP_TYPE_LABELS[$activity->follow_up_type] ?? 'Seguimiento',
                'description' => $activity->next_steps,
                'follow_up_type' => $activity->follow_up_type,
                'follow_up_type_label' => self::FOLLOW_UP_TYPE_LABELS[$activity->follow_up_type] ?? 'Seguimiento',
                'status' => $completed ? 'completed' : ($overdue ? 'overdue' : 'pending'),
                'priority' => $activity->case?->priority,
                'due_at' => $activity->next_action_on?->toDateString(),
                'source_activity_on' => $activity->activity_on?->toDateString(),
                'source_activity_type' => $activity->type,
                'responsible_name' => $activity->responsibleUser?->name,
                'case_responsible_name' => $activity->case?->responsibleUser?->name,
                'is_overdue' => $overdue,
            ];
        });

        return response()->json($page);
    }

    public function calendar(Request $request): JsonResponse
    {
        $from = $request->date('from') ?: now()->startOfMonth();
        $to = $request->date('to') ?: now()->endOfMonth();
        $visibleCases = PsychologyCase::query()->select('psychology_cases.id');
        $this->access->applyCaseVisibility($visibleCases, $request->user());
        $activitiesQuery = PsychologyActivity::query()
            ->whereIn('case_id', $visibleCases)
            ->where(function ($dates) use ($from, $to) {
                $dates->whereBetween('activity_on', [$from, $to])
                    ->orWhereBetween('next_action_on', [$from, $to]);
            })
            ->with('case:id,code');
        $this->access->applyActivityVisibility($activitiesQuery, $request->user());
        $activities = $activitiesQuery->get();
        $attentionEvents = $activities
            ->filter(fn (PsychologyActivity $activity) => $activity->activity_on?->betweenIncluded($from, $to))
            ->map(fn (PsychologyActivity $activity) => [
                'id' => 'activity-'.$activity->id,
                'title' => 'Atención de Psicología – Caso '.$activity->case->code,
                'start' => $activity->activity_on->format('Y-m-d').'T'.($activity->starts_at ?: '08:00'),
                'end' => $activity->ends_at ? $activity->activity_on->format('Y-m-d').'T'.$activity->ends_at : null,
                'type' => 'activity',
                'case_id' => $activity->case_id,
                'backgroundColor' => '#5275c7',
                'borderColor' => '#5275c7',
            ]);
        $followUpEvents = $activities
            ->filter(fn (PsychologyActivity $activity) => $activity->next_action_on?->betweenIncluded($from, $to))
            ->map(fn (PsychologyActivity $activity) => [
                'id' => 'followup-'.$activity->id,
                'title' => (self::FOLLOW_UP_TYPE_LABELS[$activity->follow_up_type] ?? 'Seguimiento').' – Caso '.$activity->case->code,
                'start' => $activity->next_action_on?->toDateString(),
                'allDay' => true,
                'type' => 'follow_up',
                'case_id' => $activity->case_id,
                'activity_id' => $activity->id,
                'follow_up_type' => $activity->follow_up_type,
                'backgroundColor' => '#70578f',
                'borderColor' => '#70578f',
            ]);
        $tasksQuery = PsychologyTask::query()
            ->whereIn('case_id', $visibleCases)
            ->whereBetween('due_at', [$from, $to])
            ->with('case:id,code');
        $this->access->applyTaskVisibility($tasksQuery, $request->user());
        $tasks = $tasksQuery->get()
            ->map(fn (PsychologyTask $task) => [
                'id' => 'task-'.$task->id,
                'title' => 'Tarea de Psicología – Caso '.$task->case->code,
                'start' => $task->due_at?->toIso8601String(),
                'type' => 'task',
                'case_id' => $task->case_id,
                'backgroundColor' => '#3f8c71',
                'borderColor' => '#3f8c71',
            ]);

        $coordinationsQuery = PsychologyCoordinationRequest::query()
            ->whereIn('case_id', $visibleCases)
            ->where('status', 'accepted')
            ->whereBetween('requested_for', [$from, $to])
            ->with('case:id,code');
        $this->access->applyCoordinationVisibility($coordinationsQuery, $request->user());
        $coordinations = $coordinationsQuery->get()
            ->map(fn (PsychologyCoordinationRequest $coordination) => [
                'id' => 'coordination-'.$coordination->id,
                'title' => 'Coordinación aceptada – Caso '.$coordination->case->code,
                'start' => $coordination->requested_for?->toDateString(),
                'allDay' => true,
                'type' => 'coordination',
                'case_id' => $coordination->case_id,
                'coordination_id' => $coordination->id,
                'backgroundColor' => '#b8783d',
                'borderColor' => '#b8783d',
            ]);

        return response()->json(['data' => $attentionEvents->concat($followUpEvents)->concat($tasks)->concat($coordinations)->values()]);
    }
}
