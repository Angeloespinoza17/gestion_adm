<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceActionPlan;
use App\Models\Attendance\AttendanceCase;
use App\Models\Attendance\AttendanceCaseCause;
use App\Models\Attendance\AttendanceCaseStatusHistory;
use App\Models\Attendance\AttendanceFamilyContact;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\Security\SecurityNotification;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCaseService
{
    public function __construct(
        private readonly AttendanceAnalyticsService $analytics,
        private readonly AttendanceStatisticsAuditService $audit,
        private readonly AttendanceStatisticsCache $cache,
    ) {}

    public function create(array $data, User $actor, Request $request): AttendanceCase
    {
        $participantIds = $data['participant_user_ids'] ?? [];
        unset($data['participant_user_ids'], $data['reason']);
        $case = DB::transaction(function () use ($data, $participantIds, $actor): AttendanceCase {
            $next = ((int) AttendanceCase::query()->lockForUpdate()->max('id')) + 1;
            $case = AttendanceCase::query()->create([
                ...$data,
                'folio' => 'AUS-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'status' => $data['status'] ?? 'detected',
                'priority' => $data['priority'] ?? 'preventive',
                'opened_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            AttendanceCaseStatusHistory::query()->create([
                'attendance_case_id' => $case->id, 'from_status' => null, 'to_status' => $case->status,
                'reason' => 'Apertura del caso.', 'changed_by' => $actor->id, 'changed_at' => now(),
            ]);
            $this->syncParticipants($case, $participantIds, $actor);

            return $case;
        });
        $this->audit->log('attendance_case_created', $case, $actor, newValues: $case->getAttributes(), reason: $request->string('reason')->toString(), request: $request);
        $this->notifyCaseTeam($case, 'Nuevo caso de asistencia asignado', 'Se creó el caso '.$case->folio.' y formas parte de su equipo.');
        $this->cache->invalidate();

        return $this->load($case);
    }

    public function update(AttendanceCase $case, array $data, User $actor, Request $request): AttendanceCase
    {
        $participantIds = $data['participant_user_ids'] ?? null;
        $reason = (string) ($data['reason'] ?? 'Actualización del caso.');
        unset($data['participant_user_ids'], $data['reason']);
        $before = $case->getAttributes();
        DB::transaction(function () use ($case, $data, $participantIds, $actor, $reason): void {
            $fromStatus = $case->status;
            if (($data['status'] ?? null) === 'closed' && ! $case->closed_at) {
                $data['closed_at'] = now();
                $data['closed_by'] = $actor->id;
            }
            if (($data['status'] ?? null) === 'reopened') {
                $data['closed_at'] = null;
                $data['closed_by'] = null;
                $data['closure_reason'] = null;
            }
            $case->update([...$data, 'updated_by' => $actor->id]);
            if (isset($data['status']) && $fromStatus !== $data['status']) {
                AttendanceCaseStatusHistory::query()->create([
                    'attendance_case_id' => $case->id, 'from_status' => $fromStatus, 'to_status' => $data['status'],
                    'reason' => $reason, 'changed_by' => $actor->id, 'changed_at' => now(),
                ]);
            }
            if (is_array($participantIds)) {
                $this->syncParticipants($case, $participantIds, $actor);
            }
        });
        $this->audit->log('attendance_case_updated', $case, $actor, $before, $case->fresh()->getAttributes(), $reason, $request);
        $this->notifyCaseTeam($case, 'Caso de asistencia actualizado', 'El caso '.$case->folio.' cambió a '.$case->fresh()->status.'.');
        $this->cache->invalidate();

        return $this->load($case);
    }

    public function addCause(AttendanceCase $case, array $data, User $actor, Request $request): AttendanceCaseCause
    {
        $cause = DB::transaction(function () use ($case, $data, $actor): AttendanceCaseCause {
            if ($data['is_primary']) {
                $case->causes()->update(['is_primary' => false]);
            }

            return $case->causes()->updateOrCreate(
                ['absence_reason_id' => $data['absence_reason_id']],
                [...$data, 'registered_by' => $actor->id],
            );
        });
        $this->audit->log('attendance_case_cause_saved', $cause, $actor, newValues: $cause->getAttributes(), request: $request);

        return $cause->load('reason');
    }

    public function addFamilyContact(AttendanceCase $case, array $data, User $actor, Request $request): AttendanceFamilyContact
    {
        $contact = DB::transaction(function () use ($case, $data, $actor): AttendanceFamilyContact {
            $contact = $case->familyContacts()->create([
                ...$data, 'responsible_user_id' => $data['responsible_user_id'] ?? $actor->id, 'created_by' => $actor->id,
            ]);
            $case->update([
                'first_intervention_at' => $case->first_intervention_at ?: $contact->contacted_at,
                'last_intervention_at' => $contact->contacted_at,
                'status' => $case->status === 'detected' ? 'family_contact' : $case->status,
                'updated_by' => $actor->id,
            ]);

            return $contact;
        });
        $this->audit->log('attendance_family_contact_created', $contact, $actor, newValues: $contact->getAttributes(), request: $request);

        return $contact->load('responsible:id,name');
    }

    public function addIntervention(AttendanceCase $case, array $data, User $actor, Request $request): AttendanceIntervention
    {
        $intervention = DB::transaction(function () use ($case, $data, $actor): AttendanceIntervention {
            $next = ((int) AttendanceIntervention::withTrashed()->lockForUpdate()->max('id')) + 1;
            $intervention = AttendanceIntervention::query()->create([
                ...$data, 'attendance_case_id' => $case->id, 'folio' => 'ASI-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'academic_year_id' => $case->academic_year_id, 'course_section_id' => $case->course_section_id,
                'student_profile_id' => $case->student_profile_id, 'responsible_user_id' => $data['responsible_user_id'] ?? $actor->id,
                'status' => $data['status'] ?? 'intervention', 'first_action_at' => $data['opened_at'],
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $case->update([
                'first_intervention_at' => $case->first_intervention_at ?: $intervention->opened_at,
                'last_intervention_at' => $intervention->opened_at,
                'status' => $case->status === 'detected' ? 'follow_up' : $case->status,
                'updated_by' => $actor->id,
            ]);

            return $intervention;
        });
        $this->audit->log('attendance_case_intervention_created', $intervention, $actor, newValues: $intervention->getAttributes(), request: $request);
        $this->cache->invalidate();

        return $intervention->load(['interventionType', 'responsible:id,name']);
    }

    public function createPlan(AttendanceCase $case, array $data, User $actor, Request $request): AttendanceActionPlan
    {
        $actions = $data['actions'] ?? [];
        unset($data['actions']);
        $student = StudentProfile::query()->findOrFail($case->student_profile_id);
        $analysis = $this->analytics->studentSummary($student, $case->academic_year_id, $data['starts_on']);
        $plan = DB::transaction(function () use ($case, $data, $actions, $analysis, $actor): AttendanceActionPlan {
            $next = ((int) AttendanceActionPlan::query()->lockForUpdate()->max('id')) + 1;
            $plan = $case->plans()->create([
                ...$data, 'folio' => 'PIA-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'initial_attendance_rate' => $analysis['summary']['attendance_percentage'],
                'initial_lost_days' => $analysis['summary']['days_absent'],
                'initial_patterns' => $analysis['patterns_detected']->pluck('type')->values()->all(),
                'identified_causes' => $case->causes()->with('reason:id,name')->get()->pluck('reason.name')->filter()->values()->all(),
                'status' => 'active', 'responsible_user_id' => $data['responsible_user_id'] ?? $case->responsible_user_id ?? $actor->id,
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            foreach ($actions as $action) {
                $plan->actions()->create([...$action, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            }
            $case->update(['status' => 'active_plan', 'next_review_on' => $data['review_on'], 'updated_by' => $actor->id]);

            return $plan;
        });
        $this->audit->log('attendance_action_plan_created', $plan, $actor, newValues: $plan->getAttributes(), request: $request);
        $this->notifyCaseTeam($case, 'Plan individual de asistencia creado', 'El plan '.$plan->folio.' requiere seguimiento.');
        $this->cache->invalidate();

        return $plan->load(['actions.responsible:id,name', 'responsible:id,name']);
    }

    public function evaluatePlan(AttendanceActionPlan $plan, User $actor, Request $request): AttendanceActionPlan
    {
        $plan->loadMissing('attendanceCase.studentProfile');
        $analysis = $this->analytics->studentSummary($plan->attendanceCase->studentProfile, $plan->attendanceCase->academic_year_id);
        $rate = $analysis['summary']['attendance_last_30_days'] ?? $analysis['summary']['attendance_percentage'];
        $variation = $rate !== null && $plan->initial_attendance_rate !== null ? round($rate - $plan->initial_attendance_rate, 2) : null;
        $result = $variation === null ? 'insufficient_data' : ($variation >= 8 ? 'significant_improvement' : ($variation >= 3 ? 'partial_improvement' : ($variation > -3 ? 'no_change' : 'deterioration')));
        $before = $plan->getAttributes();
        $plan->update([
            'review_attendance_rate' => $rate, 'result_variation' => $variation, 'evaluation_result' => $result,
            'evaluated_at' => now(), 'evaluated_by' => $actor->id, 'status' => $result === 'significant_improvement' ? 'completed' : 'reviewed',
            'updated_by' => $actor->id,
        ]);
        $this->audit->log('attendance_action_plan_evaluated', $plan, $actor, $before, $plan->fresh()->getAttributes(), $request->string('reason')->toString(), $request);
        $this->cache->invalidate();

        return $plan->fresh()->load(['actions.responsible:id,name', 'responsible:id,name']);
    }

    public function load(AttendanceCase $case): AttendanceCase
    {
        return $case->fresh()->load([
            'studentProfile:id,first_name,last_name,registered_name,rut,guardian_name,guardian_phone,guardian_email',
            'courseSection:id,display_name', 'responsible:id,name', 'referenceAdult:id,name',
            'participants:id,name', 'causes.reason', 'causes.registeredBy:id,name', 'statusHistory.changedBy:id,name',
            'notes.createdBy:id,name', 'familyContacts.responsible:id,name', 'interventions.interventionType',
            'interventions.responsible:id,name', 'plans.actions.responsible:id,name', 'plans.responsible:id,name',
            'agreements.responsible:id,name',
        ]);
    }

    private function syncParticipants(AttendanceCase $case, array $participantIds, User $actor): void
    {
        $ids = collect($participantIds)->map(fn ($id) => (int) $id)->unique()->values();
        DB::table('attendance_case_participants')->where('attendance_case_id', $case->id)->whereNotIn('user_id', $ids)
            ->where('active', true)->update(['active' => false, 'ended_at' => now(), 'updated_at' => now()]);
        foreach ($ids as $userId) {
            DB::table('attendance_case_participants')->updateOrInsert(
                ['attendance_case_id' => $case->id, 'user_id' => $userId],
                ['participation_role' => 'support', 'active' => true, 'joined_at' => now(), 'ended_at' => null, 'added_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function notifyCaseTeam(AttendanceCase $case, string $title, string $message): void
    {
        $userIds = collect([$case->responsible_user_id, $case->reference_adult_user_id])
            ->merge($case->participants()->wherePivot('active', true)->pluck('users.id'))->filter()->unique();
        foreach ($userIds as $userId) {
            SecurityNotification::query()->create([
                'user_id' => $userId, 'title' => $title, 'message' => $message, 'priority' => $case->priority === 'critical' ? 'alta' : 'media',
                'action_url' => '/students/attendance-management?section=cases&case='.$case->id,
            ]);
        }
    }
}
