<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceManagementFilterRequest;
use App\Http\Requests\Attendance\EvaluateAttendanceActionPlanRequest;
use App\Http\Requests\Attendance\SaveAttendanceCaseRequest;
use App\Http\Requests\Attendance\StoreAttendanceActionPlanRequest;
use App\Http\Requests\Attendance\StoreAttendanceCaseCauseRequest;
use App\Http\Requests\Attendance\StoreAttendanceCaseInterventionRequest;
use App\Http\Requests\Attendance\StoreAttendanceCaseNoteRequest;
use App\Http\Requests\Attendance\StoreAttendanceFamilyContactRequest;
use App\Http\Requests\Attendance\StoreAttendanceMeetingAgreementRequest;
use App\Http\Requests\Attendance\UpdateAttendanceActionPlanActionRequest;
use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceActionPlan;
use App\Models\Attendance\AttendanceActionPlanAction;
use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceCaseService;
use App\Services\Attendance\AttendanceManagementAccessService;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceManagementCaseController extends Controller
{
    public function __construct(
        private readonly AttendanceManagementAccessService $access,
        private readonly AttendanceCaseService $service,
        private readonly AttendanceStatisticsAuditService $audit,
    ) {}

    public function index(AttendanceManagementFilterRequest $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $filters = $request->validated();
        $yearId = (int) ($filters['academic_year_id'] ?? AcademicYear::query()->where('year', now()->year)->value('id') ?? AcademicYear::query()->where('is_active', true)->value('id'));
        $query = AttendanceCase::query()
            ->where('academic_year_id', $yearId)
            ->when($filters['course_section_id'] ?? null, fn (Builder $builder, $id) => $builder->where('course_section_id', $id))
            ->when($filters['student_profile_id'] ?? null, fn (Builder $builder, $id) => $builder->where('student_profile_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $builder, $status) => $builder->where('status', $status))
            ->when($filters['responsible_user_id'] ?? null, fn (Builder $builder, $id) => $builder->where('responsible_user_id', $id))
            ->when($filters['search'] ?? null, function (Builder $builder, string $search): void {
                $term = '%'.trim($search).'%';
                $builder->where(fn (Builder $searchQuery) => $searchQuery->where('folio', 'like', $term)->orWhereHas('studentProfile', fn (Builder $student) => $student
                    ->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('registered_name', 'like', $term)->orWhere('rut', 'like', $term)));
            });
        $this->scopeVisible($query, $request->user(), $yearId);
        $query->with([
            'studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'responsible:id,name',
        ])->withCount(['interventions', 'familyContacts', 'plans']);
        $query->orderByRaw("CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->orderByRaw('CASE WHEN first_intervention_at IS NULL THEN 0 ELSE 1 END')->latest('opened_at');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (AttendanceCase $case) => $this->listPayload($case))->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function show(Request $request, AttendanceCase $attendanceCase): JsonResponse
    {
        abort_unless($this->access->canViewCase($request->user(), $attendanceCase), 403);

        return response()->json($this->sanitize($this->service->load($attendanceCase), $request->user()));
    }

    public function store(SaveAttendanceCaseRequest $request): JsonResponse
    {
        $case = $this->service->create($request->validated(), $request->user(), $request);

        return response()->json($case, 201);
    }

    public function update(SaveAttendanceCaseRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        return response()->json($this->service->update($attendanceCase, $request->validated(), $request->user(), $request));
    }

    public function storeCause(StoreAttendanceCaseCauseRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        return response()->json($this->service->addCause($attendanceCase, $request->validated(), $request->user(), $request), 201);
    }

    public function storeFamilyContact(StoreAttendanceFamilyContactRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        return response()->json($this->service->addFamilyContact($attendanceCase, $request->validated(), $request->user(), $request), 201);
    }

    public function storeIntervention(StoreAttendanceCaseInterventionRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        return response()->json($this->service->addIntervention($attendanceCase, $request->validated(), $request->user(), $request), 201);
    }

    public function storePlan(StoreAttendanceActionPlanRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        return response()->json($this->service->createPlan($attendanceCase, $request->validated(), $request->user(), $request), 201);
    }

    public function evaluatePlan(EvaluateAttendanceActionPlanRequest $request, AttendanceActionPlan $attendanceActionPlan): JsonResponse
    {
        return response()->json($this->service->evaluatePlan($attendanceActionPlan, $request->user(), $request));
    }

    public function storeAgreement(StoreAttendanceMeetingAgreementRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        $agreement = $attendanceCase->agreements()->create([
            ...$request->validated(), 'responsible_user_id' => $request->validated('responsible_user_id') ?? $request->user()->id, 'status' => 'pending', 'created_by' => $request->user()->id,
        ]);
        $this->audit->log('attendance_meeting_agreement_created', $agreement, $request->user(), newValues: $agreement->getAttributes(), request: $request);

        return response()->json($agreement->load('responsible:id,name'), 201);
    }

    public function storeNote(StoreAttendanceCaseNoteRequest $request, AttendanceCase $attendanceCase): JsonResponse
    {
        $note = $attendanceCase->notes()->create([...$request->validated(), 'created_by' => $request->user()->id]);
        $this->audit->log('attendance_case_note_created', $note, $request->user(), newValues: [
            'attendance_case_id' => $attendanceCase->id, 'is_sensitive' => $note->is_sensitive,
        ], request: $request);

        return response()->json($note->load('createdBy:id,name'), 201);
    }

    public function updatePlanAction(UpdateAttendanceActionPlanActionRequest $request, AttendanceActionPlanAction $attendanceActionPlanAction): JsonResponse
    {
        $before = $attendanceActionPlanAction->getAttributes();
        $data = $request->safe()->except('reason');
        $attendanceActionPlanAction->update([
            ...$data, 'completed_at' => $data['status'] === 'completed' ? ($attendanceActionPlanAction->completed_at ?: now()) : null,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->log('attendance_action_plan_action_updated', $attendanceActionPlanAction, $request->user(), $before, $attendanceActionPlanAction->fresh()->getAttributes(), $request->string('reason')->toString(), $request);

        return response()->json($attendanceActionPlanAction->fresh()->load('responsible:id,name'));
    }

    private function scopeVisible(Builder $query, $user, int $yearId): void
    {
        if ($this->access->canViewAll($user)) {
            return;
        }
        $courseIds = $this->access->courseIds($user, $yearId);
        $query->where(function (Builder $visible) use ($user, $courseIds): void {
            $visible->whereIn('course_section_id', $courseIds)->orWhere('responsible_user_id', $user->id)->orWhere('reference_adult_user_id', $user->id)
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('users.id', $user->id)->where('attendance_case_participants.active', true));
        });
    }

    private function sanitize(AttendanceCase $case, $user): AttendanceCase
    {
        if ($this->access->canViewSensitive($user)) {
            return $case;
        }
        $case->setRelation('notes', $case->notes->where('is_sensitive', false)->values());
        $case->setRelation('causes', $case->causes->where('is_sensitive', false)->values());
        $case->closure_notes = null;
        $case->familyContacts->each(fn ($contact) => $contact->observation = null);
        $case->interventions->each(function ($intervention): void {
            $intervention->description = null;
            $intervention->result_summary = null;
        });
        $case->plans->each(function ($plan): void {
            $plan->initial_situation = null;
            $plan->identified_causes = [];
        });

        return $case;
    }

    private function listPayload(AttendanceCase $case): array
    {
        return [
            'id' => $case->id, 'folio' => $case->folio, 'student_profile_id' => $case->student_profile_id,
            'academic_year_id' => $case->academic_year_id, 'course_section_id' => $case->course_section_id,
            'responsible_user_id' => $case->responsible_user_id, 'reference_adult_user_id' => $case->reference_adult_user_id,
            'status' => $case->status, 'priority' => $case->priority, 'opened_at' => $case->opened_at,
            'first_intervention_at' => $case->first_intervention_at, 'last_intervention_at' => $case->last_intervention_at,
            'next_review_on' => $case->next_review_on, 'student_profile' => $case->studentProfile,
            'course_section' => $case->courseSection, 'responsible' => $case->responsible,
            'interventions_count' => $case->interventions_count, 'family_contacts_count' => $case->family_contacts_count,
            'plans_count' => $case->plans_count,
        ];
    }
}
