<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveAttendanceGoalRequest;
use App\Http\Requests\Attendance\SaveAttendanceInterventionRequest;
use App\Http\Resources\Attendance\AttendanceGoalResource;
use App\Http\Resources\Attendance\AttendanceInterventionResource;
use App\Models\Attendance\AttendanceAbsenceReason;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceAlertRule;
use App\Models\Attendance\AttendanceFinancialParameter;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\Attendance\AttendanceRiskLevel;
use App\Models\Attendance\AttendanceScheduledReport;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use App\Services\Attendance\AttendanceStatisticsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceStatisticsManagementController extends Controller
{
    public function __construct(
        private readonly AttendanceStatisticsAuditService $audit,
        private readonly AttendanceStatisticsCache $cache,
    ) {}

    public function goals(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceGoal::class);
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'scope_type' => ['nullable', 'string', 'max:40'], 'status' => ['nullable', 'string', 'max:30'],
        ]);
        $goals = AttendanceGoal::query()
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['scope_type'] ?? null, fn ($query, $value) => $query->where('scope_type', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->with('responsible:id,name')
            ->orderByDesc('starts_on')
            ->get();

        return response()->json(['data' => AttendanceGoalResource::collection($goals)]);
    }

    public function storeGoal(SaveAttendanceGoalRequest $request): JsonResponse
    {
        $this->authorize('create', AttendanceGoal::class);
        $data = $request->safe()->except('reason');
        $goal = AttendanceGoal::query()->create([...$data, 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
        $this->audit->log('goal_created', $goal, $request->user(), newValues: $data, reason: $request->string('reason')->toString(), request: $request);
        $this->cache->invalidate();

        return (new AttendanceGoalResource($goal->load('responsible:id,name')))->response()->setStatusCode(201);
    }

    public function updateGoal(SaveAttendanceGoalRequest $request, AttendanceGoal $attendanceGoal): AttendanceGoalResource
    {
        $this->authorize('update', $attendanceGoal);
        $before = $attendanceGoal->getAttributes();
        $data = $request->safe()->except('reason');
        $attendanceGoal->update([...$data, 'updated_by' => $request->user()?->id]);
        $this->audit->log('goal_updated', $attendanceGoal, $request->user(), $before, $attendanceGoal->fresh()->getAttributes(), $request->string('reason')->toString(), $request);
        $this->cache->invalidate();

        return new AttendanceGoalResource($attendanceGoal->fresh()->load('responsible:id,name'));
    }

    public function destroyGoal(Request $request, AttendanceGoal $attendanceGoal): JsonResponse
    {
        $this->authorize('delete', $attendanceGoal);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $before = $attendanceGoal->getAttributes();
        $attendanceGoal->delete();
        $this->audit->log('goal_deleted', $attendanceGoal, $request->user(), $before, reason: $data['reason'], request: $request);
        $this->cache->invalidate();

        return response()->json(['message' => 'Meta eliminada.']);
    }

    public function interventions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceIntervention::class);
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'status' => ['nullable', 'string', 'max:40'], 'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $paginator = AttendanceIntervention::query()
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['course_section_id'] ?? null, fn ($query, $id) => $query->where('course_section_id', $id))
            ->when($filters['student_profile_id'] ?? null, fn ($query, $id) => $query->where('student_profile_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['responsible_user_id'] ?? null, fn ($query, $id) => $query->where('responsible_user_id', $id))
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'responsible:id,name', 'riskLevel', 'actions.responsible:id,name'])
            ->orderByRaw('CASE WHEN due_on IS NOT NULL AND due_on < CURRENT_DATE THEN 0 ELSE 1 END')
            ->latest('opened_at')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'data' => AttendanceInterventionResource::collection(collect($paginator->items())),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'per_page' => $paginator->perPage()],
        ]);
    }

    public function storeIntervention(SaveAttendanceInterventionRequest $request): JsonResponse
    {
        $this->authorize('create', AttendanceIntervention::class);
        $data = $request->safe()->except(['reason', 'actions']);
        $intervention = DB::transaction(function () use ($request, $data) {
            $next = ((int) AttendanceIntervention::withTrashed()->max('id')) + 1;
            $intervention = AttendanceIntervention::query()->create([
                ...$data,
                'folio' => 'ASI-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'opened_at' => $data['opened_at'] ?? now(),
                'status' => $data['status'] ?? 'new',
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
            foreach ($request->validated('actions', []) as $action) {
                $intervention->actions()->create([...$action, 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
            }

            return $intervention;
        });
        $this->audit->log('intervention_created', $intervention, $request->user(), newValues: $data, reason: $request->string('reason')->toString(), request: $request);
        $this->cache->invalidate();

        return (new AttendanceInterventionResource($this->loadIntervention($intervention)))->response()->setStatusCode(201);
    }

    public function updateIntervention(SaveAttendanceInterventionRequest $request, AttendanceIntervention $attendanceIntervention): AttendanceInterventionResource
    {
        $this->authorize('update', $attendanceIntervention);
        $before = $attendanceIntervention->getAttributes();
        $data = $request->safe()->except(['reason', 'actions']);
        if (($data['status'] ?? null) === 'closed' && ! $attendanceIntervention->closed_at) {
            $data['closed_at'] = now();
            $data['closed_by'] = $request->user()?->id;
        }
        $attendanceIntervention->update([...$data, 'updated_by' => $request->user()?->id]);
        foreach ($request->validated('actions', []) as $action) {
            $attendanceIntervention->actions()->create([...$action, 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
        }
        $this->audit->log('intervention_updated', $attendanceIntervention, $request->user(), $before, $attendanceIntervention->fresh()->getAttributes(), $request->string('reason')->toString(), $request);
        $this->cache->invalidate();

        return new AttendanceInterventionResource($this->loadIntervention($attendanceIntervention));
    }

    public function assignAlert(Request $request, AttendanceAlert $attendanceAlert): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['open', 'acknowledged', 'in_progress', 'resolved'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $attendanceAlert->getAttributes();
        $attendanceAlert->update(['assigned_to' => $data['assigned_to'] ?? $request->user()?->id, 'status' => $data['status'] ?? 'acknowledged', 'acknowledged_at' => now(), 'acknowledged_by' => $request->user()?->id]);
        $this->audit->log('alert_assigned', $attendanceAlert, $request->user(), $before, $attendanceAlert->fresh()->getAttributes(), $data['reason'], $request);
        $this->cache->invalidate();

        return response()->json($attendanceAlert->fresh()->load('assignedTo:id,name'));
    }

    public function configuration(Request $request): JsonResponse
    {
        $yearId = $request->validate(['academic_year_id' => ['required', 'integer', 'exists:academic_years,id']])['academic_year_id'];

        return response()->json([
            'risk_levels' => AttendanceRiskLevel::query()->where(fn ($query) => $query->where('academic_year_id', $yearId)->orWhereNull('academic_year_id'))->orderByDesc('priority')->get(),
            'alert_rules' => AttendanceAlertRule::query()->where(fn ($query) => $query->where('academic_year_id', $yearId)->orWhereNull('academic_year_id'))->orderBy('name')->get(),
            'absence_reasons' => AttendanceAbsenceReason::query()->orderBy('sort_order')->get(),
            'financial_parameters' => AttendanceFinancialParameter::query()->where('academic_year_id', $yearId)->orderByDesc('valid_from')->get(),
        ]);
    }

    public function updateRiskLevel(Request $request, AttendanceRiskLevel $attendanceRiskLevel): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'minimum_rate' => ['required', 'numeric', 'between:0,100'],
            'maximum_rate' => ['required', 'numeric', 'between:0,100', 'gte:minimum_rate'], 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['required', 'string', 'max:80'], 'priority' => ['required', 'integer', 'min:1', 'max:100'],
            'suggested_actions' => ['nullable', 'string', 'max:2000'], 'intervention_due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $attendanceRiskLevel->getAttributes();
        $attendanceRiskLevel->update([...collect($data)->except('reason')->all(), 'updated_by' => $request->user()?->id]);
        $this->audit->log('risk_level_updated', $attendanceRiskLevel, $request->user(), $before, $attendanceRiskLevel->fresh()->getAttributes(), $data['reason'], $request);
        $this->cache->invalidate();

        return response()->json($attendanceRiskLevel->fresh());
    }

    public function updateAlertRule(Request $request, AttendanceAlertRule $attendanceAlertRule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'],
            'operator' => ['required', Rule::in(['lt', 'lte', 'gt', 'gte', 'eq'])], 'threshold' => ['required', 'numeric'],
            'evaluation_period' => ['required', 'string', 'max:40'], 'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'cooldown_days' => ['required', 'integer', 'min:0', 'max:365'], 'response_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'auto_create_case' => ['required', 'boolean'], 'active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500'],
        ]);
        $before = $attendanceAlertRule->getAttributes();
        $attendanceAlertRule->update([...collect($data)->except('reason')->all(), 'updated_by' => $request->user()?->id]);
        $this->audit->log('alert_rule_updated', $attendanceAlertRule, $request->user(), $before, $attendanceAlertRule->fresh()->getAttributes(), $data['reason'], $request);
        $this->cache->invalidate();

        return response()->json($attendanceAlertRule->fresh());
    }

    public function storeFinancialParameter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'], 'name' => ['required', 'string', 'max:160'],
            'subsidy_type' => ['required', 'string', 'max:80'], 'unit_value' => ['required', 'numeric', 'min:0'],
            'attendance_factor' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3'],
            'valid_from' => ['required', 'date'], 'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'source_reference' => ['required', 'string', 'max:255'], 'assumptions' => ['required', 'string', 'max:2000'],
            'active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500'],
        ]);
        $parameter = AttendanceFinancialParameter::query()->create([...collect($data)->except('reason')->all(), 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
        $this->audit->log('financial_parameter_created', $parameter, $request->user(), newValues: $parameter->getAttributes(), reason: $data['reason'], request: $request);
        $this->cache->invalidate();

        return response()->json($parameter, 201);
    }

    public function savedFilters(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('attendance_saved_filters')->where('user_id', $request->user()->id)->orWhere('is_institutional', true)->orderByDesc('is_default')->orderBy('name')->get()->map(function ($filter) {
            $filter->filters = json_decode($filter->filters, true);

            return $filter;
        })]);
    }

    public function storeSavedFilter(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'filters' => ['required', 'array'], 'is_default' => ['nullable', 'boolean']]);
        $id = DB::table('attendance_saved_filters')->updateOrInsert(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['filters' => json_encode($data['filters']), 'is_default' => (bool) ($data['is_default'] ?? false), 'is_institutional' => false, 'created_at' => now(), 'updated_at' => now()],
        );

        return response()->json(['saved' => (bool) $id], 201);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout' => ['nullable', 'array'], 'visible_widgets' => ['nullable', 'array'],
            'favorite_kpis' => ['nullable', 'array'], 'default_view' => ['nullable', 'string', 'max:40'],
        ]);
        DB::table('attendance_dashboard_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id],
            [...collect($data)->map(fn ($value) => is_array($value) ? json_encode($value) : $value)->all(), 'created_at' => now(), 'updated_at' => now()],
        );

        return response()->json(['saved' => true]);
    }

    public function scheduledReports(Request $request): JsonResponse
    {
        return response()->json(['data' => AttendanceScheduledReport::query()
            ->where('owner_user_id', $request->user()->id)
            ->with('academicYear:id,name,year')
            ->latest('id')
            ->get()]);
    }

    public function storeScheduledReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:160'],
            'report_type' => ['required', Rule::in(['executive', 'students', 'courses', 'risk', 'alerts', 'interventions', 'goals', 'financial', 'data_quality'])],
            'format' => ['required', Rule::in(['pdf', 'xls', 'csv'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'semester', 'annual', 'once'])],
            'run_at' => ['required', 'date_format:H:i'], 'next_run_at' => ['required', 'date'],
            'filters' => ['nullable', 'array'], 'recipients' => ['required', 'array', 'min:1', 'max:30'],
            'recipients.*' => ['required', 'email:rfc', 'max:255'],
        ]);
        $report = AttendanceScheduledReport::query()->create([
            ...$data, 'owner_user_id' => $request->user()->id, 'active' => true,
            'next_run_at' => Carbon::parse($data['next_run_at'], config('app.timezone')),
        ]);
        $this->audit->log('scheduled_report_created', $report, $request->user(), newValues: $report->getAttributes(), request: $request);

        return response()->json($report->load('academicYear:id,name,year'), 201);
    }

    public function destroyScheduledReport(Request $request, AttendanceScheduledReport $attendanceScheduledReport): JsonResponse
    {
        abort_unless($attendanceScheduledReport->owner_user_id === $request->user()->id || $request->user()->hasPermission('attendance_statistics.manage_reports'), 403);
        $before = $attendanceScheduledReport->getAttributes();
        $attendanceScheduledReport->delete();
        $this->audit->log('scheduled_report_deleted', $attendanceScheduledReport, $request->user(), $before, reason: 'Eliminado desde el centro de reportes.', request: $request);

        return response()->json(['message' => 'Programación eliminada.']);
    }

    private function loadIntervention(AttendanceIntervention $intervention): AttendanceIntervention
    {
        return $intervention->fresh()->load(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name', 'responsible:id,name', 'riskLevel', 'actions.responsible:id,name']);
    }
}
