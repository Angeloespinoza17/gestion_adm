<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\Attendance\AttendanceProjectionSetting;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceAggregationService
{
    public function __construct(
        private readonly AttendanceStatisticsPeriodService $periods,
        private readonly AttendanceCalculationService $calculations,
        private readonly AttendanceRiskService $risks,
    ) {}

    public function dashboard(array $filters, ?User $user): array
    {
        $period = $this->periods->resolve($filters);
        /** @var AcademicYear $year */
        $year = $period['academic_year'];
        $target = $this->target($year->id, $period['from'], $period['to']);
        $daily = $this->dailyRows($filters, $year->id, $period['from'], $period['to']);
        $yearDaily = $period['from'] === $year->starts_at->format('Y-m-d') && $period['to'] === $year->ends_at->format('Y-m-d')
            ? $daily
            : $this->dailyRows($filters, $year->id, $year->starts_at->format('Y-m-d'), $year->ends_at->format('Y-m-d'));
        $summary = $this->summarizeDaily($daily);
        $previous = $this->summarizeDaily($this->dailyRows($filters, $year->id, $period['previous_from'], $period['previous_to']));
        $historical = $period['comparison_year']
            ? $this->summarizeDaily($this->dailyRows($filters, $period['comparison_year']->id, $period['comparison_from'], $period['comparison_to']))
            : $this->emptySummary();
        $studentMetrics = $this->studentAggregateRows($filters, $year->id, $period['from'], $period['to']);
        $courseMetrics = $this->courseRows($filters, $year->id, $period['from'], $period['to']);
        $levelMetrics = $this->levelRows($filters, $year->id, $period['from'], $period['to']);
        $riskDistribution = $this->riskDistribution($studentMetrics, $year->id);
        $currentVariation = $this->calculations->variation($summary['attendance_rate'], $previous['attendance_rate']);
        $historicalVariation = $this->calculations->variation($summary['attendance_rate'], $historical['attendance_rate']);
        $today = CarbonImmutable::today(config('app.timezone'));

        return [
            'meta' => [
                'academic_year' => $year->only(['id', 'name', 'year', 'starts_at', 'ends_at', 'is_closed']),
                'period' => $period['period'],
                'date_range' => ['from' => $period['from'], 'to' => $period['to']],
                'comparison_range' => ['from' => $period['previous_from'], 'to' => $period['previous_to']],
                'generated_at' => now()->toIso8601String(),
                'source' => 'attendance_records + school_days + student_enrollments',
                'data_status' => $summary['expected'] > 0 ? 'available' : 'no_data',
                'capabilities' => $this->capabilities($user),
            ],
            'catalogs' => $this->catalogs($year->id),
            'summary' => [
                ...$summary,
                'target_rate' => $target,
                'target_gap' => $this->calculations->gap($summary['attendance_rate'], $target),
                'period_variation' => $currentVariation,
                'year_variation' => $historicalVariation,
                'students_at_risk' => $studentMetrics->filter(fn ($row) => ($row->expected ?? 0) > 0 && $this->riskPriority((float) $row->attendance_rate, $year->id) >= 3)->count(),
                'students_declining' => $this->decliningStudentCount($filters, $year->id, $period['from'], $period['to']),
                'courses_below_target' => $courseMetrics->filter(fn ($row) => $row['attendance_rate'] !== null && $row['attendance_rate'] < $target)->count(),
                'open_alerts' => $this->activeAlerts($filters, $year->id)->count(),
                'open_interventions' => $this->interventionsQuery($filters, $year->id)->whereNotIn('status', ['improved', 'closed'])->count(),
                'pending_school_days' => DB::table('school_days')->where('academic_year_id', $year->id)->whereBetween('date', [$period['from'], $period['to']])->where('status', 'pending_confirmation')->count(),
            ],
            'kpis' => $this->kpis($yearDaily, $summary, $previous, $historical, $target, $today, $year->id),
            'timeline' => $daily->values(),
            'monthly' => $this->groupDaily($yearDaily, 'month'),
            'weekdays' => $this->groupDaily($daily, 'weekday'),
            'courses' => $courseMetrics->values(),
            'levels' => $levelMetrics->values(),
            'risk_distribution' => $riskDistribution,
            'statistics' => $this->calculations->descriptive($studentMetrics->pluck('attendance_rate')),
            'status_distribution' => [
                ['key' => 'present', 'label' => 'Presentes', 'value' => $summary['present']],
                ['key' => 'justified', 'label' => 'Ausencias justificadas', 'value' => $summary['justified_absent']],
                ['key' => 'unjustified', 'label' => 'Ausencias injustificadas', 'value' => $summary['unjustified_absent']],
                ['key' => 'late', 'label' => 'Atrasos', 'value' => $summary['late']],
                ['key' => 'early_departure', 'label' => 'Retiros anticipados', 'value' => $summary['early_departure']],
            ],
            'alert_funnel' => $this->alertFunnel($filters, $year->id),
        ];
    }

    public function students(array $filters): array
    {
        $period = $this->periods->resolve($filters);
        $year = $period['academic_year'];
        $aggregate = $this->studentAggregateQuery($filters, $year->id, $period['from'], $period['to']);
        $query = DB::query()->fromSub($aggregate, 'metrics')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.trim($search).'%';
                $query->where(fn (Builder $nested) => $nested->where('student_name', 'like', $term)->orWhere('rut', 'like', $term));
            })
            ->when(isset($filters['attendance_min']), fn (Builder $query) => $query->where('attendance_rate', '>=', (float) $filters['attendance_min']))
            ->when(isset($filters['attendance_max']), fn (Builder $query) => $query->where('attendance_rate', '<=', (float) $filters['attendance_max']))
            ->when($filters['risk'] ?? null, function (Builder $query, string $risk) use ($year) {
                $level = $this->risks->levels($year->id)->firstWhere('slug', $risk);
                if ($level) {
                    $query->whereBetween('attendance_rate', [(float) $level->minimum_rate, (float) $level->maximum_rate]);
                }
            });
        $sort = in_array($filters['sort'] ?? '', ['student_name', 'course_name', 'attendance_rate', 'absent', 'late', 'early_departure'], true)
            ? $filters['sort']
            : 'attendance_rate';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction)->orderBy('student_name');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 25), ['*'], 'page', (int) ($filters['page'] ?? 1));

        return [
            'data' => collect($paginator->items())->map(fn ($row) => $this->studentPayload($row, $year->id))->values(),
            'meta' => $this->pagination($paginator),
            'summary' => $this->summarizeStudentPage(collect($paginator->items())),
        ];
    }

    public function student(array $filters, StudentProfile $student): array
    {
        $period = $this->periods->resolve($filters);
        $year = $period['academic_year'];
        $filters['student_profile_id'] = $student->id;
        $daily = $this->dailyRows($filters, $year->id, $period['from'], $period['to']);
        $summary = $this->summarizeDaily($daily);
        $enrollment = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('student_profile_id', $student->id)
            ->with('courseSection.educationLevel')
            ->latest('id')
            ->first();
        $courseAverage = $enrollment
            ? $this->summarizeDaily($this->dailyRows(['course_section_id' => $enrollment->course_section_id], $year->id, $period['from'], $period['to']))['attendance_rate']
            : null;
        $records = DB::table('attendance_records as ar')
            ->leftJoin('attendance_absence_reasons as reason', 'reason.id', '=', 'ar.absence_reason_id')
            ->where('ar.academic_year_id', $year->id)
            ->where('ar.student_profile_id', $student->id)
            ->whereBetween('ar.attendance_date', [$period['from'], $period['to']])
            ->orderByDesc('ar.attendance_date')
            ->get(['ar.id', 'ar.attendance_date', 'ar.status', 'ar.is_justified', 'ar.minutes_late', 'ar.early_departure', 'ar.notes', 'reason.name as reason']);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->registered_name_resolved,
                'rut' => $student->rut,
                'course_id' => $enrollment?->course_section_id,
                'course' => $enrollment?->courseSection?->display_name,
                'level' => $enrollment?->courseSection?->educationLevel?->name,
            ],
            'summary' => [
                ...$summary,
                'risk' => $this->risks->classify($summary['attendance_rate'], $year->id),
                'course_average' => $courseAverage,
                'course_gap' => $this->calculations->gap($summary['attendance_rate'], $courseAverage),
                'trend' => $this->calculations->trend($daily->pluck('attendance_rate')),
                'maximum_consecutive_absences' => $this->calculations->maximumConsecutiveAbsences($records->sortBy('attendance_date')->pluck('status')),
            ],
            'timeline' => $daily->values(),
            'monthly' => $this->groupDaily($daily, 'month'),
            'weekdays' => $this->groupDaily($daily, 'weekday'),
            'records' => $records,
            'alerts' => AttendanceAlert::query()->where('academic_year_id', $year->id)->where('student_profile_id', $student->id)->with('followups.createdBy:id,name')->latest('detected_on')->get(),
            'interventions' => AttendanceIntervention::query()->where('academic_year_id', $year->id)->where('student_profile_id', $student->id)->with(['responsible:id,name', 'riskLevel', 'actions'])->latest('opened_at')->get(),
        ];
    }

    public function heatmap(array $filters): array
    {
        $period = $this->periods->resolve($filters);
        $year = $period['academic_year'];
        $courseId = (int) ($filters['course_section_id'] ?? 0);
        abort_unless($courseId > 0, 422, 'Selecciona un curso para construir la matriz.');
        $students = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('course_section_id', $courseId)
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->with('studentProfile:id,first_name,last_name,registered_name,rut')
            ->get()
            ->map(fn ($enrollment) => [
                'id' => $enrollment->student_profile_id,
                'name' => $enrollment->studentProfile?->registered_name_resolved,
                'rut' => $enrollment->studentProfile?->rut,
            ]);
        $records = DB::table('attendance_records')
            ->where('academic_year_id', $year->id)
            ->where('course_section_id', $courseId)
            ->whereBetween('attendance_date', [$period['from'], $period['to']])
            ->orderBy('attendance_date')
            ->get(['student_profile_id', 'attendance_date', 'status', 'is_justified', 'minutes_late', 'early_departure'])
            ->groupBy('student_profile_id');
        $dates = DB::table('school_days')->where('academic_year_id', $year->id)->where('is_school_day', true)->whereBetween('date', [$period['from'], $period['to']])->orderBy('date')->pluck('date');

        return [
            'dates' => $dates,
            'students' => $students->map(function (array $student) use ($records) {
                return [
                    ...$student,
                    'records' => collect($records->get($student['id'], []))->mapWithKeys(fn ($record) => [(string) $record->attendance_date => [
                        'status' => $record->status,
                        'is_justified' => (bool) $record->is_justified,
                        'minutes_late' => (int) $record->minutes_late,
                        'early_departure' => (bool) $record->early_departure,
                    ]]),
                ];
            })->values(),
            'legend' => [
                ['key' => 'present', 'label' => 'Presente'], ['key' => 'absent', 'label' => 'Ausente'],
                ['key' => 'justified', 'label' => 'Justificada'], ['key' => 'late', 'label' => 'Atraso'],
                ['key' => 'early_departure', 'label' => 'Retiro'], ['key' => 'missing', 'label' => 'Sin registro'],
            ],
        ];
    }

    private function recordsQuery(array $filters, int $academicYearId, string $from, string $to): Builder
    {
        return DB::table('attendance_records as ar')
            ->join('course_sections as cs', 'cs.id', '=', 'ar.course_section_id')
            ->join('education_levels as el', 'el.id', '=', 'cs.education_level_id')
            ->join('student_profiles as sp', 'sp.id', '=', 'ar.student_profile_id')
            ->leftJoin('student_enrollments as se', 'se.id', '=', 'ar.student_enrollment_id')
            ->where('ar.academic_year_id', $academicYearId)
            ->whereBetween('ar.attendance_date', [$from, $to])
            ->when($filters['course_section_id'] ?? null, fn (Builder $query, $id) => $query->where('ar.course_section_id', (int) $id))
            ->when($filters['education_level_id'] ?? null, fn (Builder $query, $id) => $query->where('cs.education_level_id', (int) $id))
            ->when($filters['school_day_template_id'] ?? null, fn (Builder $query, $id) => $query->where('cs.school_day_template_id', (int) $id))
            ->when($filters['student_profile_id'] ?? null, fn (Builder $query, $id) => $query->where('ar.student_profile_id', (int) $id))
            ->when($filters['student_ids'] ?? null, fn (Builder $query, $ids) => $query->whereIn('ar.student_profile_id', array_map('intval', (array) $ids)))
            ->when($filters['enrollment_status'] ?? null, fn (Builder $query, $value) => $query->where('se.enrollment_status', $value))
            ->when($filters['attendance_status'] ?? null, fn (Builder $query, $value) => $query->where('ar.status', $value))
            ->when(isset($filters['is_justified']), fn (Builder $query) => $query->where('ar.is_justified', (bool) $filters['is_justified']))
            ->when($filters['absence_reason_id'] ?? null, fn (Builder $query, $id) => $query->where('ar.absence_reason_id', (int) $id))
            ->when($filters['gender'] ?? null, fn (Builder $query, $value) => $query->where('sp.gender', $value))
            ->when($filters['commune'] ?? null, fn (Builder $query, $value) => $query->where('sp.commune', $value))
            ->when(isset($filters['is_pie_participant']), fn (Builder $query) => $query->where('sp.is_pie_participant', (bool) $filters['is_pie_participant']));
    }

    private function dailyRows(array $filters, int $academicYearId, string $from, string $to): Collection
    {
        return $this->recordsQuery($filters, $academicYearId, $from, $to)
            ->select('ar.attendance_date')
            ->selectRaw("SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' AND ar.is_justified = 1 THEN 1 ELSE 0 END) as justified_absent")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' AND ar.is_justified = 0 THEN 1 ELSE 0 END) as unjustified_absent")
            ->selectRaw('SUM(CASE WHEN ar.minutes_late > 0 THEN 1 ELSE 0 END) as late')
            ->selectRaw('SUM(CASE WHEN ar.early_departure = 1 THEN 1 ELSE 0 END) as early_departure')
            ->selectRaw('COUNT(*) as expected')
            ->groupBy('ar.attendance_date')
            ->orderBy('ar.attendance_date')
            ->get()
            ->map(function ($row) {
                $expected = (int) $row->expected;

                return [
                    'date' => (string) $row->attendance_date,
                    'present' => (int) $row->present,
                    'absent' => (int) $row->absent,
                    'justified_absent' => (int) $row->justified_absent,
                    'unjustified_absent' => (int) $row->unjustified_absent,
                    'late' => (int) $row->late,
                    'early_departure' => (int) $row->early_departure,
                    'expected' => $expected,
                    'attendance_rate' => $this->calculations->rate((int) $row->present, $expected),
                ];
            });
    }

    private function studentAggregateQuery(array $filters, int $yearId, string $from, string $to): Builder
    {
        $nameExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(CASE WHEN sp.registered_name IS NOT NULL AND sp.registered_name <> '' THEN sp.registered_name ELSE COALESCE(sp.first_name, '') || ' ' || COALESCE(sp.last_name, '') END)"
            : "TRIM(CASE WHEN sp.registered_name IS NOT NULL AND sp.registered_name <> '' THEN sp.registered_name ELSE CONCAT(COALESCE(sp.first_name, ''), ' ', COALESCE(sp.last_name, '')) END)";

        return $this->recordsQuery($filters, $yearId, $from, $to)
            ->select('ar.student_profile_id', 'ar.course_section_id', 'cs.display_name as course_name', 'el.name as level_name', 'sp.rut')
            ->selectRaw($nameExpression.' as student_name')
            ->selectRaw("SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' AND ar.is_justified = 1 THEN 1 ELSE 0 END) as justified_absent")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' AND ar.is_justified = 0 THEN 1 ELSE 0 END) as unjustified_absent")
            ->selectRaw('SUM(CASE WHEN ar.minutes_late > 0 THEN 1 ELSE 0 END) as late')
            ->selectRaw('SUM(CASE WHEN ar.early_departure = 1 THEN 1 ELSE 0 END) as early_departure')
            ->selectRaw('COUNT(*) as expected')
            ->selectRaw("ROUND(SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as attendance_rate")
            ->selectRaw("MAX(CASE WHEN ar.status = 'present' THEN ar.attendance_date END) as last_attendance")
            ->selectRaw("MAX(CASE WHEN ar.status = 'absent' THEN ar.attendance_date END) as last_absence")
            ->groupBy('ar.student_profile_id', 'ar.course_section_id', 'cs.display_name', 'el.name', 'sp.rut', 'sp.registered_name', 'sp.first_name', 'sp.last_name');
    }

    private function studentAggregateRows(array $filters, int $yearId, string $from, string $to): Collection
    {
        return $this->studentAggregateQuery($filters, $yearId, $from, $to)->get();
    }

    private function courseRows(array $filters, int $yearId, string $from, string $to): Collection
    {
        return $this->recordsQuery($filters, $yearId, $from, $to)
            ->select('cs.id', 'cs.display_name as name', 'el.id as level_id', 'el.name as level')
            ->selectRaw("SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw('COUNT(*) as expected')
            ->selectRaw('COUNT(DISTINCT ar.student_profile_id) as students')
            ->selectRaw('COUNT(DISTINCT ar.attendance_date) as school_days')
            ->groupBy('cs.id', 'cs.display_name', 'el.id', 'el.name')
            ->orderBy('cs.display_name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id, 'name' => $row->name, 'level_id' => (int) $row->level_id,
                'level' => $row->level, 'students' => (int) $row->students, 'school_days' => (int) $row->school_days,
                'present' => (int) $row->present, 'absent' => (int) $row->absent,
                'attendance_rate' => $this->calculations->rate((int) $row->present, (int) $row->expected),
            ]);
    }

    private function levelRows(array $filters, int $yearId, string $from, string $to): Collection
    {
        return $this->recordsQuery($filters, $yearId, $from, $to)
            ->select('el.id', 'el.name', 'el.type as cycle')
            ->selectRaw("SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw('COUNT(*) as expected')
            ->selectRaw('COUNT(DISTINCT ar.student_profile_id) as students')
            ->groupBy('el.id', 'el.name', 'el.type', 'el.order')
            ->orderBy('el.order')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id, 'name' => $row->name, 'cycle' => $row->cycle,
                'students' => (int) $row->students, 'present' => (int) $row->present, 'absent' => (int) $row->absent,
                'attendance_rate' => $this->calculations->rate((int) $row->present, (int) $row->expected),
            ]);
    }

    private function summarizeDaily(Collection $daily): array
    {
        if ($daily->isEmpty()) {
            return $this->emptySummary();
        }
        $expected = (int) $daily->sum('expected');
        $present = (int) $daily->sum('present');
        $absent = (int) $daily->sum('absent');

        return [
            'present' => $present,
            'absent' => $absent,
            'expected' => $expected,
            'justified_absent' => (int) $daily->sum('justified_absent'),
            'unjustified_absent' => (int) $daily->sum('unjustified_absent'),
            'late' => (int) $daily->sum('late'),
            'early_departure' => (int) $daily->sum('early_departure'),
            'school_days' => $daily->count(),
            'attendance_rate' => $this->calculations->rate($present, $expected),
            'absence_rate' => $this->calculations->rate($absent, $expected),
            'justified_rate' => $this->calculations->rate((int) $daily->sum('justified_absent'), $expected),
            'unjustified_rate' => $this->calculations->rate((int) $daily->sum('unjustified_absent'), $expected),
            'late_rate' => $this->calculations->rate((int) $daily->sum('late'), $expected),
            'early_departure_rate' => $this->calculations->rate((int) $daily->sum('early_departure'), $expected),
            'average_daily_attendance' => round((float) $daily->avg('present'), 2),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'present' => 0, 'absent' => 0, 'expected' => 0, 'justified_absent' => 0,
            'unjustified_absent' => 0, 'late' => 0, 'early_departure' => 0, 'school_days' => 0,
            'attendance_rate' => null, 'absence_rate' => null, 'justified_rate' => null,
            'unjustified_rate' => null, 'late_rate' => null, 'early_departure_rate' => null,
            'average_daily_attendance' => null,
        ];
    }

    private function kpis(Collection $yearDaily, array $summary, array $previous, array $historical, float $target, CarbonImmutable $today, int $yearId): array
    {
        $ranges = [
            ['key' => 'today', 'label' => 'Asistencia de hoy', 'from' => $today, 'to' => $today],
            ['key' => 'yesterday', 'label' => 'Asistencia de ayer', 'from' => $today->subDay(), 'to' => $today->subDay()],
            ['key' => 'week', 'label' => 'Semana actual', 'from' => $today->startOfWeek(), 'to' => $today->endOfWeek()],
            ['key' => 'month', 'label' => 'Mes actual', 'from' => $today->startOfMonth(), 'to' => $today->endOfMonth()],
            ['key' => 'semester', 'label' => 'Semestre actual', 'from' => $today->month <= 6 ? $today->startOfYear() : $today->setMonth(7)->startOfMonth(), 'to' => $today->month <= 6 ? $today->setMonth(6)->endOfMonth() : $today->endOfYear()],
            ['key' => 'annual', 'label' => 'Acumulado anual', 'from' => CarbonImmutable::parse($yearDaily->first()['date'] ?? $today), 'to' => CarbonImmutable::parse($yearDaily->last()['date'] ?? $today)],
        ];
        $cards = collect($ranges)->map(function (array $range) use ($yearDaily, $target, $yearId) {
            $subset = $yearDaily->filter(fn (array $day) => $day['date'] >= $range['from']->toDateString() && $day['date'] <= $range['to']->toDateString());
            $metric = $this->summarizeDaily($subset);

            return [
                'key' => $range['key'], 'label' => $range['label'], 'value' => $metric['attendance_rate'],
                'unit' => '%', 'gap' => $this->calculations->gap($metric['attendance_rate'], $target),
                'status' => $this->risks->classify($metric['attendance_rate'], $yearId),
                'help' => 'Presentes dividido por registros esperados en el periodo.',
            ];
        });
        $cards->push([
            'key' => 'period', 'label' => 'Periodo seleccionado', 'value' => $summary['attendance_rate'], 'unit' => '%',
            'gap' => $this->calculations->gap($summary['attendance_rate'], $target),
            'variation' => $this->calculations->variation($summary['attendance_rate'], $previous['attendance_rate']),
            'year_variation' => $this->calculations->variation($summary['attendance_rate'], $historical['attendance_rate']),
            'help' => 'Tasa ponderada del periodo y filtros activos.',
        ]);
        $cards->push(['key' => 'target', 'label' => 'Meta institucional', 'value' => $target, 'unit' => '%', 'help' => 'Meta activa configurada para el año.']);

        return $cards->values()->all();
    }

    private function groupDaily(Collection $daily, string $mode): array
    {
        $weekdayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $groups = $daily->groupBy(function (array $day) use ($mode) {
            $date = CarbonImmutable::parse($day['date']);

            return $mode === 'month' ? $date->format('Y-m') : (string) $date->dayOfWeekIso;
        });

        return $groups->map(function (Collection $rows, string $key) use ($mode, $weekdayNames) {
            $summary = $this->summarizeDaily($rows);

            return [
                'key' => $key,
                'label' => $mode === 'month' ? CarbonImmutable::createFromFormat('!Y-m', $key)->locale('es')->translatedFormat('M Y') : $weekdayNames[(int) $key],
                ...$summary,
            ];
        })->sortBy('key')->values()->all();
    }

    private function riskDistribution(Collection $students, int $yearId): array
    {
        return $students->groupBy(fn ($student) => $this->risks->classify(isset($student->attendance_rate) ? (float) $student->attendance_rate : null, $yearId)['slug'])
            ->map(function (Collection $group) use ($yearId) {
                $risk = $this->risks->classify((float) ($group->first()->attendance_rate ?? 0), $yearId);

                return [...$risk, 'value' => $group->count()];
            })->sortByDesc('priority')->values()->all();
    }

    private function studentPayload(object $row, int $yearId): array
    {
        $rate = isset($row->attendance_rate) ? (float) $row->attendance_rate : null;

        return [
            'id' => (int) $row->student_profile_id,
            'name' => $row->student_name,
            'rut' => $row->rut,
            'course_id' => (int) $row->course_section_id,
            'course' => $row->course_name,
            'level' => $row->level_name,
            'attendance_rate' => $rate,
            'present' => (int) $row->present,
            'absent' => (int) $row->absent,
            'justified_absent' => (int) $row->justified_absent,
            'unjustified_absent' => (int) $row->unjustified_absent,
            'late' => (int) $row->late,
            'early_departure' => (int) $row->early_departure,
            'expected' => (int) $row->expected,
            'last_attendance' => $row->last_attendance,
            'last_absence' => $row->last_absence,
            'risk' => $this->risks->classify($rate, $yearId),
        ];
    }

    private function target(int $yearId, string $from, string $to): float
    {
        $goal = AttendanceGoal::query()
            ->where('academic_year_id', $yearId)
            ->where('scope_type', 'institution')
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->latest('id')
            ->value('target_rate');

        return $goal !== null
            ? (float) $goal
            : (float) (AttendanceProjectionSetting::query()->where('academic_year_id', $yearId)->value('target_attendance_rate') ?? config('attendance.projection.target_attendance_rate', 85));
    }

    private function catalogs(int $yearId): array
    {
        return [
            'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'starts_at', 'ends_at', 'is_active', 'is_closed']),
            'levels' => EducationLevel::query()->orderBy('order')->get(['id', 'name', 'type']),
            'courses' => CourseSection::query()->where('academic_year_id', $yearId)->with('schoolDayTemplate:id,name')->orderBy('display_name')->get(['id', 'education_level_id', 'school_day_template_id', 'display_name']),
            'risk_levels' => $this->risks->levels($yearId)->values(),
            'absence_reasons' => DB::table('attendance_absence_reasons')->where('active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'category', 'is_sensitive']),
            'communes' => StudentProfile::query()->whereNotNull('commune')->where('commune', '<>', '')->distinct()->orderBy('commune')->pluck('commune'),
            'enrollment_statuses' => StudentEnrollment::STATUS_OPTIONS,
        ];
    }

    private function activeAlerts(array $filters, int $yearId): Collection
    {
        return AttendanceAlert::query()
            ->where('academic_year_id', $yearId)
            ->when($filters['course_section_id'] ?? null, fn ($query, $id) => $query->where('course_section_id', (int) $id))
            ->whereIn('status', ['open', 'acknowledged', 'in_progress'])
            ->get();
    }

    private function interventionsQuery(array $filters, int $yearId)
    {
        return AttendanceIntervention::query()
            ->where('academic_year_id', $yearId)
            ->when($filters['course_section_id'] ?? null, fn ($query, $id) => $query->where('course_section_id', (int) $id));
    }

    private function alertFunnel(array $filters, int $yearId): array
    {
        $alerts = $this->activeAlerts($filters, $yearId);
        $interventions = $this->interventionsQuery($filters, $yearId)->get();

        return [
            ['key' => 'alerts', 'label' => 'Alertas activas', 'value' => $alerts->count()],
            ['key' => 'assigned', 'label' => 'Alertas asignadas', 'value' => $alerts->whereNotNull('assigned_to')->count()],
            ['key' => 'interventions', 'label' => 'Intervenciones', 'value' => $interventions->count()],
            ['key' => 'follow_up', 'label' => 'En seguimiento', 'value' => $interventions->where('status', 'follow_up')->count()],
            ['key' => 'improved', 'label' => 'Mejoradas', 'value' => $interventions->where('status', 'improved')->count()],
        ];
    }

    private function decliningStudentCount(array $filters, int $yearId, string $from, string $to): int
    {
        $daily = $this->recordsQuery($filters, $yearId, $from, $to)
            ->select('ar.student_profile_id', 'ar.attendance_date')
            ->selectRaw("SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as rate")
            ->groupBy('ar.student_profile_id', 'ar.attendance_date')
            ->orderBy('ar.attendance_date')
            ->get()
            ->groupBy('student_profile_id');

        return $daily->filter(fn (Collection $rows) => $this->calculations->trend($rows->pluck('rate'))['direction'] === 'declining')->count();
    }

    private function riskPriority(float $rate, int $yearId): int
    {
        return (int) ($this->risks->classify($rate, $yearId)['priority'] ?? 0);
    }

    private function summarizeStudentPage(Collection $rows): array
    {
        return [
            'students' => $rows->count(),
            'average_attendance' => $rows->isNotEmpty() ? round((float) $rows->avg('attendance_rate'), 2) : null,
            'present' => (int) $rows->sum('present'),
            'absent' => (int) $rows->sum('absent'),
        ];
    }

    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
        ];
    }

    private function capabilities(?User $user): array
    {
        $permissions = [
            'view_global', 'view_course', 'view_student', 'view_financial', 'view_sensitive_segments',
            'export', 'configure', 'manage_goals', 'manage_alerts', 'manage_interventions',
            'manage_reports', 'view_audit',
        ];

        return collect($permissions)->mapWithKeys(fn (string $permission) => [
            'can_'.$permission => $user?->hasPermission('attendance_statistics.'.$permission) ?? false,
        ])->all();
    }
}
