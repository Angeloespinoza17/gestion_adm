<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceManagementFilterRequest;
use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceActionPlan;
use App\Models\Attendance\AttendanceCase;
use App\Models\Attendance\AttendancePatternDetection;
use App\Models\Attendance\AttendanceRiskSnapshot;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Services\Attendance\AttendanceAnalyticsService;
use App\Services\Attendance\AttendanceCaseService;
use App\Services\Attendance\AttendanceManagementAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceManagementDashboardController extends Controller
{
    public function __construct(
        private readonly AttendanceManagementAccessService $access,
        private readonly AttendanceAnalyticsService $analytics,
        private readonly AttendanceCaseService $cases,
    ) {}

    public function dashboard(AttendanceManagementFilterRequest $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $filters = $request->validated();
        $year = $this->year($filters['academic_year_id'] ?? null);
        $snapshotDate = AttendanceRiskSnapshot::query()->where('academic_year_id', $year->id)->max('snapshot_date');
        $snapshots = AttendanceRiskSnapshot::query()
            ->where('academic_year_id', $year->id)
            ->when($snapshotDate, fn (Builder $query) => $query->whereDate('snapshot_date', $snapshotDate))
            ->when($filters['course_section_id'] ?? null, fn (Builder $query, $id) => $query->where('course_section_id', $id));
        $this->access->applyCourseScope($snapshots, $request->user(), 'course_section_id', $year->id);
        $snapshotRows = $snapshots->get();
        $summary = $this->snapshotSummary($snapshotRows);
        $caseQuery = AttendanceCase::query()->where('academic_year_id', $year->id);
        $this->scopeCases($caseQuery, $request->user(), $year->id);
        $cases = $caseQuery->get(['id', 'status', 'priority', 'responsible_user_id', 'first_intervention_at', 'opened_at', 'last_intervention_at', 'next_review_on']);
        $todayRecords = $this->analytics->validRecordsQuery($year->id, now(config('attendance_management.timezone'))->toDateString())
            ->whereDate('ar.attendance_date', now(config('attendance_management.timezone'))->toDateString());
        $this->access->applyCourseScope($todayRecords, $request->user(), 'ar.course_section_id', $year->id);
        $today = $todayRecords->selectRaw("SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' AND ar.is_justified = 0 THEN 1 ELSE 0 END) as unjustified")
            ->first();
        $courseRows = $this->courseSummary($snapshotRows);
        $riskDistribution = collect(['green', 'yellow', 'orange', 'red', 'critical', 'no_data'])->map(function (string $level) use ($snapshotRows): array {
            $count = $snapshotRows->where('risk_level', $level)->count();

            return ['key' => $level, 'label' => $this->riskLabel($level), 'value' => $count, 'percentage' => $snapshotRows->isEmpty() ? 0 : round(($count / $snapshotRows->count()) * 100, 2)];
        })->values();
        $patterns = AttendancePatternDetection::query()->where('academic_year_id', $year->id)->where('is_active', true);
        $this->access->applyCourseScope($patterns, $request->user(), 'course_section_id', $year->id);
        $institutionalPatterns = $patterns->select('pattern_type')->selectRaw('COUNT(*) as total')->selectRaw('AVG(confidence_score) as confidence')
            ->groupBy('pattern_type')->orderByDesc('total')->limit(10)->get();
        $plans = AttendanceActionPlan::query()->whereHas('attendanceCase', fn (Builder $query) => $query->where('academic_year_id', $year->id));
        if (! $this->access->canViewAll($request->user())) {
            $courseIds = $this->access->courseIds($request->user(), $year->id);
            $plans->whereHas('attendanceCase', fn (Builder $query) => $query
                ->whereIn('course_section_id', $courseIds)
                ->orWhere('responsible_user_id', $request->user()->id)
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('users.id', $request->user()->id)->where('attendance_case_participants.active', true)));
        }
        $effectiveness = $plans->whereNotNull('result_variation')->get(['result_variation', 'evaluation_result']);

        return response()->json([
            'meta' => [
                'academic_year' => $year->only(['id', 'name', 'year', 'starts_at', 'ends_at']),
                'snapshot_date' => $snapshotDate,
                'analysis_status' => $snapshotDate ? 'available' : 'pending_analysis',
                'generated_at' => now()->toIso8601String(),
                'source' => 'attendance_records + school_days + student_enrollments (sin duplicar asistencia)',
                'capabilities' => $this->capabilities($request->user()),
            ],
            'catalogs' => $this->catalogs($year, $request->user()),
            'kpis' => [
                'school_attendance' => $summary['attendance_rate'],
                'present_today' => (int) ($today->present ?? 0),
                'absent_today' => (int) ($today->absent ?? 0),
                'unjustified_today' => (int) ($today->unjustified ?? 0),
                'yellow_students' => $snapshotRows->where('risk_level', 'yellow')->count(),
                'orange_students' => $snapshotRows->where('risk_level', 'orange')->count(),
                'red_students' => $snapshotRows->where('risk_level', 'red')->count(),
                'critical_students' => $snapshotRows->where('risk_level', 'critical')->count(),
                'new_cases_this_week' => $cases->filter(fn ($case) => CarbonImmutable::parse($case->opened_at)->isCurrentWeek())->count(),
                'recovered_cases' => $cases->whereIn('status', ['improvement', 'closed'])->count(),
                'lost_school_days' => $summary['lost_days'],
                'average_response_days' => $this->averageResponseDays($cases),
            ],
            'risk_distribution' => $riskDistribution,
            'courses' => $courseRows,
            'cycles' => $courseRows->groupBy('cycle')->map(function (Collection $rows, string $cycle): array {
                $elapsed = $rows->sum('school_days_elapsed');
                return ['cycle' => $cycle ?: 'Sin ciclo', 'students' => $rows->sum('students'), 'attendance_rate' => $elapsed > 0 ? round(($rows->sum('days_present') / $elapsed) * 100, 2) : null];
            })->values(),
            'improvement_ranking' => $courseRows->sortByDesc('trend_points')->take(10)->values(),
            'patterns' => $institutionalPatterns,
            'priority_students' => $this->priorityStudents($snapshotRows),
            'pending_management' => [
                'unreviewed_cases' => $cases->where('status', 'detected')->count(),
                'cases_without_responsible' => $cases->whereNull('responsible_user_id')->count(),
                'cases_without_intervention' => $cases->whereNull('first_intervention_at')->count(),
                'overdue_reviews' => $cases->filter(fn ($case) => $case->next_review_on && CarbonImmutable::parse($case->next_review_on)->isPast())->count(),
                'critical_without_intervention' => $cases->where('priority', 'critical')->whereNull('first_intervention_at')->count(),
            ],
            'intervention_effectiveness' => [
                'evaluated_plans' => $effectiveness->count(),
                'average_variation' => $effectiveness->isEmpty() ? null : round((float) $effectiveness->avg('result_variation'), 2),
                'improved_percentage' => $effectiveness->isEmpty() ? null : round(($effectiveness->whereIn('evaluation_result', ['significant_improvement', 'partial_improvement'])->count() / $effectiveness->count()) * 100, 2),
                'warning' => 'Las variaciones posteriores representan asociaciones observadas y no demuestran causalidad.',
            ],
        ]);
    }

    public function students(AttendanceManagementFilterRequest $request): JsonResponse
    {
        abort_unless($this->access->canView($request->user()), 403);
        $filters = $request->validated();
        $year = $this->year($filters['academic_year_id'] ?? null);
        $snapshotDate = AttendanceRiskSnapshot::query()->where('academic_year_id', $year->id)->max('snapshot_date');
        $query = AttendanceRiskSnapshot::query()
            ->where('academic_year_id', $year->id)
            ->when($snapshotDate, fn (Builder $builder) => $builder->whereDate('snapshot_date', $snapshotDate))
            ->when($filters['course_section_id'] ?? null, fn (Builder $builder, $id) => $builder->where('course_section_id', $id))
            ->when($filters['risk_level'] ?? null, fn (Builder $builder, $level) => $builder->where('risk_level', $level))
            ->when($filters['pattern_type'] ?? null, fn (Builder $builder, $type) => $builder->whereHas('patterns', fn (Builder $patterns) => $patterns
                ->where('academic_year_id', $year->id)->where('pattern_type', $type)->where('is_active', true)))
            ->when($filters['search'] ?? null, function (Builder $builder, string $search): void {
                $term = '%'.trim($search).'%';
                $builder->whereHas('studentProfile', fn (Builder $student) => $student->where(fn (Builder $name) => $name
                    ->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('registered_name', 'like', $term)->orWhere('rut', 'like', $term)));
            });
        $this->access->applyCourseScope($query, $request->user(), 'course_section_id', $year->id);
        $query->with([
            'studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name,education_level_id',
            'courseSection.educationLevel:id,name,type',
        ])->withCount(['patterns as active_patterns_count' => fn (Builder $patterns) => $patterns
            ->where('academic_year_id', $year->id)->where('is_active', true)]);
        $sort = $filters['sort'] ?? 'priority';
        $direction = $filters['direction'] ?? 'desc';
        match ($sort) {
            'attendance' => $query->orderBy('attendance_percentage', $direction),
            'trend' => $query->orderBy('trend_points', $direction),
            'lost_days' => $query->orderBy('days_absent', $direction),
            default => $query->orderByRaw("CASE risk_level WHEN 'critical' THEN 5 WHEN 'red' THEN 4 WHEN 'orange' THEN 3 WHEN 'yellow' THEN 2 WHEN 'green' THEN 1 ELSE 0 END DESC")->orderByDesc('risk_score'),
        };
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (AttendanceRiskSnapshot $snapshot) => $this->snapshotPayload($snapshot))->values(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function student(AttendanceManagementFilterRequest $request, StudentProfile $studentProfile): JsonResponse
    {
        $filters = $request->validated();
        $year = $this->year($filters['academic_year_id'] ?? null);
        abort_unless($this->access->canViewStudent($request->user(), $studentProfile->id, $year->id), 403);
        $payload = $this->analytics->studentSummary($studentProfile, $year->id, $filters['as_of'] ?? null);
        $caseQuery = AttendanceCase::query()->where('student_profile_id', $studentProfile->id)->where('academic_year_id', $year->id);
        $this->scopeCases($caseQuery, $request->user(), $year->id);
        $caseRows = $caseQuery->latest('opened_at')->get()->map(fn (AttendanceCase $case) => $this->sanitizeCase($this->cases->load($case), $request->user()));
        $teacher = DB::table('schedule_events as event')->join('staff', 'staff.id', '=', 'event.staff_id')
            ->where('event.academic_year_id', $year->id)->where('event.activity_type', 'jefatura_course')
            ->whereIn('event.course_section_id', $caseRows->pluck('course_section_id')->filter()->push($payload['records']->last()['course_section_id'] ?? null)->filter()->unique())
            ->value('staff.full_name');

        return response()->json([...$payload, 'academic_year' => $year, 'head_teacher' => $teacher, 'cases' => $caseRows]);
    }

    private function snapshotSummary(Collection $rows): array
    {
        $elapsed = (int) $rows->sum('school_days_elapsed');

        return ['attendance_rate' => $elapsed > 0 ? round(($rows->sum('days_present') / $elapsed) * 100, 2) : null, 'lost_days' => (int) $rows->sum('days_absent')];
    }

    private function courseSummary(Collection $rows): Collection
    {
        $courses = CourseSection::query()->whereIn('id', $rows->pluck('course_section_id')->filter()->unique())->with('educationLevel:id,name,type')->get()->keyBy('id');

        return $rows->groupBy('course_section_id')->map(function (Collection $group, $courseId) use ($courses): array {
            $course = $courses->get($courseId);
            $elapsed = (int) $group->sum('school_days_elapsed');

            return [
                'id' => (int) $courseId, 'name' => $course?->display_name, 'level' => $course?->educationLevel?->name,
                'cycle' => $course?->educationLevel?->type, 'students' => $group->count(), 'school_days_elapsed' => $elapsed,
                'days_present' => (int) $group->sum('days_present'), 'attendance_rate' => $elapsed > 0 ? round(($group->sum('days_present') / $elapsed) * 100, 2) : null,
                'below_90' => $group->filter(fn ($row) => $row->attendance_percentage !== null && $row->attendance_percentage < 90)->count(),
                'below_85' => $group->filter(fn ($row) => $row->attendance_percentage !== null && $row->attendance_percentage < 85)->count(),
                'critical' => $group->where('risk_level', 'critical')->count(), 'trend_points' => round((float) $group->avg('trend_points'), 2),
            ];
        })->sortBy('attendance_rate')->values();
    }

    private function priorityStudents(Collection $rows): Collection
    {
        $top = $rows->sortByDesc(fn ($row) => ($row->risk_score * 2) + ($row->consecutive_absences * 8) + max(0, -((float) $row->trend_points)))->take(20);
        $students = StudentProfile::query()->whereIn('id', $top->pluck('student_profile_id'))->get(['id', 'first_name', 'last_name', 'registered_name'])->keyBy('id');

        return $top->map(fn ($row) => [
            'student_id' => $row->student_profile_id, 'name' => $students->get($row->student_profile_id)?->registered_name_resolved,
            'risk_level' => $row->risk_level, 'risk_score' => $row->risk_score, 'attendance_rate' => $row->attendance_percentage,
            'lost_days' => $row->days_absent, 'consecutive_absences' => $row->consecutive_absences, 'trend_points' => $row->trend_points,
            'why_prioritized' => collect($row->risk_reasons)->take(3)->values(),
        ])->values();
    }

    private function snapshotPayload(AttendanceRiskSnapshot $snapshot): array
    {
        return [
            'student_id' => $snapshot->student_profile_id, 'name' => $snapshot->studentProfile?->registered_name_resolved, 'rut' => $snapshot->studentProfile?->rut,
            'course_id' => $snapshot->course_section_id, 'course' => $snapshot->courseSection?->display_name, 'level' => $snapshot->courseSection?->educationLevel?->name,
            'attendance_rate' => $snapshot->attendance_percentage, 'attendance_last_30_days' => $snapshot->attendance_last_30_days,
            'attendance_last_15_days' => $snapshot->attendance_last_15_days, 'lost_days' => $snapshot->days_absent,
            'unjustified_absences' => $snapshot->unjustified_absences, 'late_arrivals' => $snapshot->late_arrivals,
            'consecutive_absences' => $snapshot->consecutive_absences, 'trend' => $snapshot->trend, 'trend_points' => $snapshot->trend_points,
            'risk_level' => $snapshot->risk_level, 'risk_label' => $this->riskLabel($snapshot->risk_level), 'risk_score' => $snapshot->risk_score,
            'risk_reasons' => $snapshot->risk_reasons, 'active_patterns_count' => (int) ($snapshot->active_patterns_count ?? 0),
        ];
    }

    private function year(?int $id): AcademicYear
    {
        return $id ? AcademicYear::query()->findOrFail($id) : AcademicYear::query()->where('year', now()->year)->first()
            ?? AcademicYear::query()->where('is_active', true)->firstOrFail();
    }

    private function catalogs(AcademicYear $year, $user): array
    {
        $courses = CourseSection::query()->where('academic_year_id', $year->id)->with('educationLevel:id,name,type')->orderBy('display_name');
        $this->access->applyCourseScope($courses, $user, 'id', $year->id);

        return [
            'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year']),
            'courses' => $courses->get(['id', 'display_name', 'education_level_id']),
            'risk_levels' => collect(['green', 'yellow', 'orange', 'red', 'critical'])->map(fn ($level) => ['value' => $level, 'label' => $this->riskLabel($level)]),
        ];
    }

    private function scopeCases(Builder $query, $user, int $yearId): void
    {
        if ($this->access->canViewAll($user)) {
            return;
        }
        $courseIds = $this->access->courseIds($user, $yearId);
        $query->where(function (Builder $visible) use ($user, $courseIds) {
            $visible->whereIn('course_section_id', $courseIds)->orWhere('responsible_user_id', $user->id)
                ->orWhere('reference_adult_user_id', $user->id)
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('users.id', $user->id)->where('attendance_case_participants.active', true));
        });
    }

    private function averageResponseDays(Collection $cases): ?float
    {
        $values = $cases->filter(fn ($case) => $case->first_intervention_at)->map(fn ($case) => CarbonImmutable::parse($case->opened_at)->diffInMinutes(CarbonImmutable::parse($case->first_intervention_at)) / 1440);

        return $values->isEmpty() ? null : round((float) $values->avg(), 2);
    }

    private function riskLabel(string $level): string
    {
        return ['green' => 'Asistencia adecuada', 'yellow' => 'Atención preventiva', 'orange' => 'Riesgo de asistencia', 'red' => 'Apoyo prioritario', 'critical' => 'Atención crítica', 'no_data' => 'Sin datos'][$level] ?? $level;
    }

    private function capabilities($user): array
    {
        return [
            'can_view_all' => $this->access->canViewAll($user), 'can_manage_cases' => $this->access->canManageCases($user),
            'can_manage_interventions' => $this->access->canManageInterventions($user), 'can_manage_causes' => $this->access->canManageCauses($user),
            'can_manage_plans' => $this->access->canManagePlans($user), 'can_view_sensitive' => $this->access->canViewSensitive($user),
            'can_export' => $this->access->canExport($user), 'can_configure' => $this->access->canConfigure($user),
        ];
    }

    private function sanitizeCase(AttendanceCase $case, $user): AttendanceCase
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
}
