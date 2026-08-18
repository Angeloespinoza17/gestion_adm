<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Http\Requests\Psychology\SavePsychologyActivityRequest;
use App\Models\Psychology\PsychologyActivity;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyConsent;
use App\Models\Psychology\PsychologyExternalReferral;
use App\Models\Psychology\PsychologyInterventionPlan;
use App\Models\Psychology\PsychologyRiskAssessment;
use App\Models\Psychology\PsychologySharedFeedback;
use App\Models\Psychology\PsychologyTask;
use App\Services\Psychology\PsychologyAccessService;
use App\Services\Psychology\PsychologyAuditService;
use App\Services\Psychology\PsychologyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PsychologyWorkflowController extends Controller
{
    public function __construct(private readonly PsychologyAuditService $audit, private readonly PsychologyNotificationService $notifications) {}

    public function storeActivity(SavePsychologyActivityRequest $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        abort_unless($request->user()->hasPermission('psychology.sessions.create'), 403);
        $activity = DB::transaction(function () use ($request, $case) {
            $payload = $request->validated();
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
            $nextReviewOn = $payload['next_action_on']
                ?? (! empty($payload['next_interview_at']) ? substr($payload['next_interview_at'], 0, 10) : $case->next_review_on);
            $activity = $case->activities()->create($payload + ['responsible_user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'finalized_at' => $finalized ? now() : null, 'finalized_by' => $finalized ? $user->id : null]);
            $case->forceFill([
                'last_activity_at' => now(),
                'next_action' => $payload['next_steps'] ?? $case->next_action,
                'next_review_on' => $nextReviewOn,
                'updated_by' => $user->id,
            ])->save();
            $this->audit->record('activity.created', $activity, $user, [], ['status' => $activity->status, 'type' => $activity->type, 'interview_number' => $activity->interview_number]);

            return $activity;
        });

        return response()->json(['message' => 'Actividad registrada.', 'data' => $activity->load('responsibleUser:id,name')], 201);
    }

    public function finalizeActivity(Request $request, PsychologyActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->case);
        abort_unless($request->user()->hasPermission('psychology.sessions.create'), 403);
        if ($activity->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'La actividad ya fue finalizada. Usa una adenda para corregirla.']);
        }
        $activity->forceFill(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
        $this->audit->record('activity.finalized', $activity, $request->user());

        return response()->json(['message' => 'Registro finalizado.', 'data' => $activity]);
    }

    public function addAddendum(Request $request, PsychologyActivity $activity): JsonResponse
    {
        $this->authorize('update', $activity->case);
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
        $feedback = PsychologySharedFeedback::query()->create($payload + ['case_id' => $case->id, 'visibility' => 'referral_feedback', 'created_by' => $request->user()->id]);
        $this->audit->record('feedback.shared', $feedback, $request->user());

        return response()->json(['message' => 'Retroalimentación compartible publicada.', 'data' => $feedback], 201);
    }

    public function calendar(Request $request): JsonResponse
    {
        $from = $request->date('from') ?: now()->startOfMonth();
        $to = $request->date('to') ?: now()->endOfMonth();
        $caseIds = PsychologyCase::query()->tap(fn ($q) => app(PsychologyAccessService::class)->applyCaseVisibility($q, $request->user()))->pluck('id');
        $activities = PsychologyActivity::query()->whereIn('case_id', $caseIds)->whereBetween('activity_on', [$from, $to])->with('case:id,code')->get()->map(fn ($a) => ['id' => 'activity-'.$a->id, 'title' => 'Atención de Psicología – Caso '.$a->case->code, 'start' => $a->activity_on->format('Y-m-d').'T'.($a->starts_at ?: '08:00'), 'end' => $a->ends_at ? $a->activity_on->format('Y-m-d').'T'.$a->ends_at : null, 'type' => 'activity']);
        $tasks = PsychologyTask::query()->whereIn('case_id', $caseIds)->whereBetween('due_at', [$from, $to])->with('case:id,code')->get()->map(fn ($t) => ['id' => 'task-'.$t->id, 'title' => 'Tarea de Psicología – Caso '.$t->case->code, 'start' => $t->due_at?->toIso8601String(), 'type' => 'task']);

        return response()->json(['data' => $activities->concat($tasks)->values()]);
    }
}
