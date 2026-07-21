<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceImport;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AttendanceReportService
{
    public function __construct(private readonly AttendanceProjectionService $projections) {}

    public function dashboard(array $filters, ?User $user): array
    {
        $year = $this->resolveYear($filters['academic_year_id'] ?? null);
        $courseId = isset($filters['course_section_id']) ? (int) $filters['course_section_id'] : null;
        [$from, $to] = $this->dateRange($year, $filters['month'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null);
        $records = $this->recordsQuery($year->id, $courseId, $from, $to);
        $summary = (clone $records)
            ->selectRaw('COUNT(*) as possible')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw('COUNT(DISTINCT attendance_date) as school_days')
            ->first();
        $possible = (int) ($summary->possible ?? 0);
        $present = (int) ($summary->present ?? 0);
        $absent = (int) ($summary->absent ?? 0);
        $schoolDays = (int) ($summary->school_days ?? 0);
        $attendanceRate = $possible > 0 ? round(($present / $possible) * 100, 2) : 0.0;
        $rosterCount = $this->rosterQuery($year->id, $courseId)->count();
        $studentGroups = $this->studentGroups($year->id, $courseId, $from, $to);
        $projections = $this->projections->build(
            $year->id,
            $courseId,
            $attendanceRate,
            $schoolDays,
            $present,
            $possible,
        );

        $daily = (clone $records)
            ->select('attendance_date')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->present + (int) $row->absent;

                return [
                    'date' => $row->attendance_date->format('Y-m-d'),
                    'present' => (int) $row->present,
                    'absent' => (int) $row->absent,
                    'total' => $total,
                    'attendance_rate' => $total > 0 ? round(((int) $row->present / $total) * 100, 2) : 0,
                ];
            });
        $schoolDayStatuses = SchoolDay::query()
            ->where('academic_year_id', $year->id)
            ->whereBetween('date', [$from, $to])
            ->get(['id', 'date', 'status', 'label'])
            ->keyBy(fn (SchoolDay $day) => $day->date->format('Y-m-d'));
        $calendar = $daily->map(function (array $day) use ($schoolDayStatuses) {
            $schoolDay = $schoolDayStatuses->get($day['date']);

            return [
                ...$day,
                'school_day_id' => $schoolDay?->id,
                'confirmation_status' => $schoolDay?->status ?? 'confirmed',
                'label' => $schoolDay?->label,
            ];
        });

        return [
            'meta' => [
                'academic_year' => $year->only(['id', 'name', 'year', 'starts_at', 'ends_at']),
                'date_range' => ['from' => $from, 'to' => $to],
                'generated_at' => now()->toIso8601String(),
                'capabilities' => [
                    'can_import' => $user?->hasPermission('importar_asistencia') ?? false,
                    'can_edit' => $user?->hasPermission('editar_asistencia') ?? false,
                    'can_manage_alerts' => $user?->hasPermission('gestionar_alertas_asistencia') ?? false,
                    'can_project_revenue' => $user?->hasPermission('proyectar_ingresos_asistencia') ?? false,
                ],
            ],
            'catalogs' => $this->catalogs($year->id),
            'summary' => [
                'roster_students' => $rosterCount,
                'school_days' => $schoolDays,
                'present' => $present,
                'absent' => $absent,
                'possible' => $possible,
                'attendance_rate' => $attendanceRate,
                'average_daily_attendance' => $schoolDays > 0 ? round($present / $schoolDays, 2) : 0,
                'open_alerts' => $this->alertsQuery($year->id, $courseId)->whereIn('status', ['open', 'acknowledged', 'in_progress'])->count(),
                'critical_alerts' => $this->alertsQuery($year->id, $courseId)->where('severity', 'critical')->whereIn('status', ['open', 'acknowledged', 'in_progress'])->count(),
                'students_below_target' => collect($studentGroups)->sum('below_target'),
                'remaining_school_days' => $projections['remaining_school_days'],
            ],
            'daily' => $daily,
            'calendar' => $calendar,
            'courses' => $this->courseMetrics($year->id, $courseId, $from, $to),
            'students' => $studentGroups,
            'alerts' => $this->alertGroups($year->id, $courseId),
            'projections' => $projections,
            'imports' => AttendanceImport::query()
                ->where('academic_year_id', $year->id)
                ->when($courseId, fn ($query) => $query->where('course_section_id', $courseId))
                ->with('courseSection:id,display_name')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AttendanceImport $import) => $this->importPayload($import)),
        ];
    }

    public function student(int $academicYearId, StudentProfile $student, ?int $courseSectionId = null): array
    {
        $records = AttendanceRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->where('student_profile_id', $student->id)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->with('courseSection:id,display_name')
            ->orderBy('attendance_date')
            ->get();
        $months = $records->groupBy(fn (AttendanceRecord $record) => $record->attendance_date->format('Y-m'))
            ->map(function ($month, string $period) {
                $present = $month->where('status', 'present')->count();
                $total = $month->count();

                return [
                    'period' => $period,
                    'present' => $present,
                    'absent' => $total - $present,
                    'total' => $total,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                ];
            })->values();
        $present = $records->where('status', 'present')->count();
        $total = $records->count();
        $absent = $total - $present;

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->registered_name_resolved,
                'rut' => $student->rut,
                'course' => $records->last()?->courseSection?->display_name,
                'course_id' => $records->last()?->course_section_id,
            ],
            'summary' => [
                'present' => $present,
                'absent' => $absent,
                'total' => $total,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                'allowed_absences' => (int) floor($total * 0.15),
                'remaining_allowed_absences' => max(0, (int) floor($total * 0.15) - $absent),
            ],
            'months' => $months,
            'absences' => $records->where('status', 'absent')->values()->map(fn (AttendanceRecord $record) => [
                'id' => $record->id,
                'date' => $record->attendance_date->format('Y-m-d'),
                'origin' => $record->origin,
                'notes' => $record->notes,
            ]),
            'alerts' => AttendanceAlert::query()
                ->where('academic_year_id', $academicYearId)
                ->where('student_profile_id', $student->id)
                ->with('followups.createdBy:id,name')
                ->latest('detected_on')
                ->get(),
        ];
    }

    public function studentDetails(array $filters): array
    {
        $year = $this->resolveYear($filters['academic_year_id'] ?? null);
        $courseId = (int) $filters['course_section_id'];
        [$from, $to] = $this->dateRange($year, $filters['month'] ?? null, $filters['from'] ?? null, $filters['to'] ?? null);
        $metrics = $this->studentMetricAggregates($year->id, $courseId, $from, $to);
        $query = $this->rosterQuery($year->id, $courseId)
            ->leftJoinSub($metrics, 'attendance_metrics', function ($join) {
                $join->on('attendance_metrics.student_profile_id', '=', 'student_enrollments.student_profile_id')
                    ->on('attendance_metrics.course_section_id', '=', 'student_enrollments.course_section_id');
            })
            ->join('student_profiles', 'student_profiles.id', '=', 'student_enrollments.student_profile_id')
            ->select('student_enrollments.*')
            ->selectRaw('COALESCE(attendance_metrics.present, 0) as attendance_present')
            ->selectRaw('COALESCE(attendance_metrics.absent, 0) as attendance_absent')
            ->selectRaw('COALESCE(attendance_metrics.total, 0) as attendance_total')
            ->with(['studentProfile:id,first_name,last_name,registered_name,rut', 'courseSection:id,display_name'])
            ->when($filters['search'] ?? null, function (Builder $builder, string $search) {
                $term = '%'.trim($search).'%';
                $builder->where(function (Builder $nested) use ($term) {
                    $nested->where('student_profiles.first_name', 'like', $term)
                        ->orWhere('student_profiles.last_name', 'like', $term)
                        ->orWhere('student_profiles.registered_name', 'like', $term)
                        ->orWhere('student_profiles.rut', 'like', $term);
                });
            })
            ->when($filters['risk'] ?? null, function (Builder $builder, string $risk) {
                match ($risk) {
                    'below_target' => $builder->whereRaw('COALESCE(attendance_metrics.total, 0) > 0 AND (attendance_metrics.present * 100.0 / attendance_metrics.total) < 85'),
                    'warning' => $builder->whereRaw('COALESCE(attendance_metrics.total, 0) > 0 AND (attendance_metrics.present * 100.0 / attendance_metrics.total) >= 85 AND (attendance_metrics.present * 100.0 / attendance_metrics.total) < 90'),
                    'on_track' => $builder->whereRaw('COALESCE(attendance_metrics.total, 0) > 0 AND (attendance_metrics.present * 100.0 / attendance_metrics.total) >= 90'),
                    'no_data' => $builder->whereRaw('COALESCE(attendance_metrics.total, 0) = 0'),
                    default => null,
                };
            })
            ->orderByRaw('CASE WHEN COALESCE(attendance_metrics.total, 0) = 0 THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN attendance_metrics.total > 0 THEN attendance_metrics.present * 100.0 / attendance_metrics.total END')
            ->orderBy('student_profiles.last_name')
            ->orderBy('student_profiles.first_name');
        $paginator = $query->paginate(
            (int) ($filters['per_page'] ?? 30),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );

        return [
            'data' => collect($paginator->items())->map(function (StudentEnrollment $enrollment) {
                $present = (int) $enrollment->attendance_present;
                $absent = (int) $enrollment->attendance_absent;
                $total = (int) $enrollment->attendance_total;
                $allowed = (int) floor($total * 0.15);

                return [
                    'id' => $enrollment->student_profile_id,
                    'name' => $enrollment->studentProfile?->registered_name_resolved,
                    'rut' => $enrollment->studentProfile?->rut,
                    'course_id' => $enrollment->course_section_id,
                    'course' => $enrollment->courseSection?->display_name,
                    'present' => $present,
                    'absent' => $absent,
                    'total' => $total,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : null,
                    'allowed_absences' => $allowed,
                    'remaining_allowed_absences' => max(0, $allowed - $absent),
                ];
            })->values(),
            'group' => collect($this->studentGroups($year->id, $courseId, $from, $to))->first(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function day(SchoolDay $schoolDay, ?int $courseSectionId = null): array
    {
        $records = AttendanceRecord::query()
            ->where('school_day_id', $schoolDay->id)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->with([
                'studentProfile:id,first_name,last_name,registered_name,rut',
                'courseSection:id,display_name',
                'attendanceImport:id,original_filename',
            ])
            ->orderBy('status')
            ->orderBy('student_profile_id')
            ->get();
        $present = $records->where('status', AttendanceRecord::PRESENT)->count();
        $absent = $records->where('status', AttendanceRecord::ABSENT)->count();
        $total = $records->count();

        return [
            'day' => [
                'id' => $schoolDay->id,
                'date' => $schoolDay->date->format('Y-m-d'),
                'status' => $schoolDay->status,
                'is_school_day' => $schoolDay->is_school_day,
                'label' => $schoolDay->label,
                'present' => $present,
                'absent' => $absent,
                'total' => $total,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            ],
            'students' => $records->map(fn (AttendanceRecord $record) => [
                'record_id' => $record->id,
                'student_id' => $record->student_profile_id,
                'name' => $record->studentProfile?->registered_name_resolved,
                'rut' => $record->studentProfile?->rut,
                'course' => $record->courseSection?->display_name,
                'status' => $record->status,
                'origin' => $record->origin,
                'source_symbol' => $record->source_symbol,
                'notes' => $record->notes,
                'import_filename' => $record->attendanceImport?->original_filename,
            ])->values(),
            'alerts' => AttendanceAlert::query()
                ->where('academic_year_id', $schoolDay->academic_year_id)
                ->where('detected_on', $schoolDay->date->format('Y-m-d'))
                ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
                ->get(['id', 'severity', 'status', 'title', 'description']),
        ];
    }

    public function alertDetails(array $filters): array
    {
        $year = $this->resolveYear($filters['academic_year_id'] ?? null);
        $courseId = isset($filters['course_section_id']) ? (int) $filters['course_section_id'] : null;
        $query = $this->alertsQuery($year->id, $courseId)
            ->when(
                ! $courseId && ($filters['unassigned'] ?? false),
                fn (Builder $builder) => $builder->whereNull('course_section_id'),
            )
            ->whereIn('status', ['open', 'acknowledged', 'in_progress'])
            ->when($filters['severity'] ?? null, fn (Builder $builder, string $severity) => $builder->where('severity', $severity))
            ->when($filters['type'] ?? null, fn (Builder $builder, string $type) => $builder->where('type', $type))
            ->when($filters['search'] ?? null, function (Builder $builder, string $search) {
                $term = '%'.trim($search).'%';
                $builder->where(function (Builder $nested) use ($term) {
                    $nested->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('studentProfile', function (Builder $student) use ($term) {
                            $student->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('registered_name', 'like', $term)
                                ->orWhere('rut', 'like', $term);
                        });
                });
            })
            ->with([
                'studentProfile:id,first_name,last_name,registered_name,rut',
                'courseSection:id,display_name',
                'assignedTo:id,name',
            ])
            ->withCount('followups')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->latest('detected_on')
            ->latest('id');
        $paginator = $query->paginate(
            (int) ($filters['per_page'] ?? 30),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );
        $groupKey = $courseId ? (string) $courseId : 'unassigned';
        $group = collect($this->alertGroups($year->id, $courseId))
            ->firstWhere('key', $groupKey);

        return [
            'data' => collect($paginator->items())->map(fn (AttendanceAlert $alert) => $this->alertPayload($alert))->values(),
            'group' => $group,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    private function courseMetrics(int $academicYearId, ?int $courseId, string $from, string $to): array
    {
        $metrics = $this->recordsQuery($academicYearId, $courseId, $from, $to)
            ->select('course_section_id')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw('COUNT(DISTINCT attendance_date) as school_days')
            ->groupBy('course_section_id')
            ->get()
            ->keyBy('course_section_id');
        $rosters = $this->rosterQuery($academicYearId, $courseId)
            ->selectRaw('course_section_id, COUNT(*) as total')
            ->groupBy('course_section_id')
            ->pluck('total', 'course_section_id');

        return CourseSection::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseId, fn ($query) => $query->whereKey($courseId))
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(function (CourseSection $course) use ($metrics, $rosters) {
                $metric = $metrics->get($course->id);
                $present = (int) ($metric->present ?? 0);
                $absent = (int) ($metric->absent ?? 0);
                $total = $present + $absent;

                return [
                    'id' => $course->id,
                    'name' => $course->display_name,
                    'roster_students' => (int) ($rosters[$course->id] ?? 0),
                    'school_days' => (int) ($metric->school_days ?? 0),
                    'present' => $present,
                    'absent' => $absent,
                    'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : null,
                ];
            })->all();
    }

    private function studentGroups(int $academicYearId, ?int $courseId, string $from, string $to): array
    {
        $metrics = $this->studentMetricAggregates($academicYearId, $courseId, $from, $to);

        return $this->rosterQuery($academicYearId, $courseId)
            ->leftJoinSub($metrics, 'attendance_metrics', function ($join) {
                $join->on('attendance_metrics.student_profile_id', '=', 'student_enrollments.student_profile_id')
                    ->on('attendance_metrics.course_section_id', '=', 'student_enrollments.course_section_id');
            })
            ->join('course_sections', 'course_sections.id', '=', 'student_enrollments.course_section_id')
            ->select('student_enrollments.course_section_id', 'course_sections.display_name')
            ->selectRaw('COUNT(*) as students')
            ->selectRaw('SUM(CASE WHEN COALESCE(attendance_metrics.total, 0) > 0 THEN 1 ELSE 0 END) as with_data')
            ->selectRaw('SUM(CASE WHEN COALESCE(attendance_metrics.total, 0) = 0 THEN 1 ELSE 0 END) as without_data')
            ->selectRaw('SUM(CASE WHEN attendance_metrics.total > 0 AND (attendance_metrics.present * 100.0 / attendance_metrics.total) < 85 THEN 1 ELSE 0 END) as below_target')
            ->selectRaw('SUM(COALESCE(attendance_metrics.present, 0)) as present')
            ->selectRaw('SUM(COALESCE(attendance_metrics.absent, 0)) as absent')
            ->selectRaw('AVG(CASE WHEN attendance_metrics.total > 0 THEN attendance_metrics.present * 100.0 / attendance_metrics.total END) as average_attendance')
            ->groupBy('student_enrollments.course_section_id', 'course_sections.display_name')
            ->orderBy('course_sections.display_name')
            ->get()
            ->map(function ($group) {
                return [
                    'key' => (string) $group->course_section_id,
                    'course_id' => (int) $group->course_section_id,
                    'course' => $group->display_name,
                    'students' => (int) $group->students,
                    'with_data' => (int) $group->with_data,
                    'without_data' => (int) $group->without_data,
                    'below_target' => (int) $group->below_target,
                    'present' => (int) $group->present,
                    'absent' => (int) $group->absent,
                    'average_attendance' => $group->average_attendance !== null ? round((float) $group->average_attendance, 2) : null,
                ];
            })
            ->values()
            ->all();
    }

    private function studentMetricAggregates(int $academicYearId, ?int $courseId, string $from, string $to): Builder
    {
        return $this->recordsQuery($academicYearId, $courseId, $from, $to)
            ->select('student_profile_id', 'course_section_id')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('student_profile_id', 'course_section_id');
    }

    private function alertGroups(int $academicYearId, ?int $courseId): array
    {
        $active = $this->alertsQuery($academicYearId, $courseId)
            ->whereIn('status', ['open', 'acknowledged', 'in_progress']);
        $groups = (clone $active)
            ->select('course_section_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical")
            ->selectRaw("SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning")
            ->selectRaw('COUNT(DISTINCT student_profile_id) as students')
            ->selectRaw('MAX(detected_on) as latest_detected_on')
            ->groupBy('course_section_id')
            ->get()
            ->keyBy(fn ($row) => $row->course_section_id === null ? 'unassigned' : (string) $row->course_section_id);
        $types = (clone $active)
            ->select('course_section_id', 'type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('course_section_id', 'type')
            ->get()
            ->groupBy(fn ($row) => $row->course_section_id === null ? 'unassigned' : (string) $row->course_section_id);
        $courseNames = CourseSection::query()
            ->whereKey($groups->pluck('course_section_id')->filter()->values())
            ->pluck('display_name', 'id');

        return $groups
            ->map(function ($group, string $key) use ($types, $courseNames) {
                return [
                    'key' => $key,
                    'course_id' => $group->course_section_id !== null ? (int) $group->course_section_id : null,
                    'course' => $courseNames[$group->course_section_id] ?? 'Sin curso asociado',
                    'total' => (int) $group->total,
                    'critical' => (int) $group->critical,
                    'warning' => (int) $group->warning,
                    'students' => (int) $group->students,
                    'latest_detected_on' => $group->latest_detected_on,
                    'types' => collect($types->get($key, []))
                        ->map(fn ($type) => ['type' => $type->type, 'count' => (int) $type->total])
                        ->sortByDesc('count')
                        ->values(),
                ];
            })
            ->sortBy('course', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function alertPayload(AttendanceAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'title' => $alert->title,
            'description' => $alert->description,
            'detected_on' => $alert->detected_on?->format('Y-m-d'),
            'metric_value' => $alert->metric_value !== null ? (float) $alert->metric_value : null,
            'student' => $alert->studentProfile?->registered_name_resolved,
            'student_rut' => $alert->studentProfile?->rut,
            'student_id' => $alert->student_profile_id,
            'course' => $alert->courseSection?->display_name,
            'course_id' => $alert->course_section_id,
            'followups_count' => (int) ($alert->followups_count ?? 0),
        ];
    }

    private function catalogs(int $academicYearId): array
    {
        return [
            'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active']),
            'courses' => CourseSection::query()->where('academic_year_id', $academicYearId)->where('active', true)->orderBy('display_name')->get(['id', 'display_name', 'education_level_id']),
        ];
    }

    private function recordsQuery(int $academicYearId, ?int $courseId, string $from, string $to): Builder
    {
        return AttendanceRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseId, fn ($query) => $query->where('course_section_id', $courseId))
            ->whereBetween('attendance_date', [$from, $to]);
    }

    private function rosterQuery(int $academicYearId, ?int $courseId): Builder
    {
        return StudentEnrollment::query()
            ->where('student_enrollments.academic_year_id', $academicYearId)
            ->when($courseId, fn ($query) => $query->where('student_enrollments.course_section_id', $courseId))
            ->whereNotIn('student_enrollments.enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES);
    }

    private function alertsQuery(int $academicYearId, ?int $courseId): Builder
    {
        return AttendanceAlert::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseId, fn ($query) => $query->where('course_section_id', $courseId));
    }

    private function resolveYear(?int $id): AcademicYear
    {
        return AcademicYear::query()
            ->when($id, fn ($query) => $query->whereKey($id), fn ($query) => $query->where('is_active', true))
            ->ordered()
            ->firstOrFail();
    }

    private function dateRange(AcademicYear $year, ?string $month, ?string $from, ?string $to): array
    {
        if ($month) {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

            return [$start->toDateString(), $start->endOfMonth()->toDateString()];
        }

        return [
            $from ?: $year->starts_at?->format('Y-m-d') ?: $year->year.'-01-01',
            $to ?: $year->ends_at?->format('Y-m-d') ?: $year->year.'-12-31',
        ];
    }

    private function importPayload(AttendanceImport $import): array
    {
        return [
            'id' => $import->id,
            'course' => $import->courseSection?->display_name,
            'filename' => $import->original_filename,
            'status' => $import->status,
            'period' => data_get($import->preview_payload, 'document.period'),
            'students' => $import->parsed_students,
            'records' => $import->imported_records,
            'conflicts' => $import->conflict_records,
            'created_at' => $import->created_at?->toIso8601String(),
            'confirmed_at' => $import->confirmed_at?->toIso8601String(),
        ];
    }
}
