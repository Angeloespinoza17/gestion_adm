<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceCase;
use App\Models\Attendance\AttendancePatternDetection;
use App\Models\Attendance\AttendanceRiskSnapshot;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Attendance\Patterns\AttendanceDataset;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceAnalyticsService
{
    public function __construct(
        private readonly AttendanceManagementSettingsService $settings,
        private readonly AttendanceRiskEngine $riskEngine,
        private readonly AttendancePatternEngine $patternEngine,
    ) {}

    public function validRecordsQuery(int $academicYearId, string $asOf): Builder
    {
        return DB::table('attendance_records as ar')
            ->join('school_days as sd', 'sd.id', '=', 'ar.school_day_id')
            ->leftJoin('student_enrollments as se', 'se.id', '=', 'ar.student_enrollment_id')
            ->where('ar.academic_year_id', $academicYearId)
            ->whereDate('ar.attendance_date', '<=', $asOf)
            ->where('sd.is_school_day', true)
            ->where('sd.status', 'confirmed')
            ->where(function (Builder $query) {
                $query->whereNull('se.id')
                    ->orWhere(function (Builder $enrollment) {
                        $enrollment->where(fn (Builder $q) => $q->whereNull('se.enrolled_at')->orWhereColumn('ar.attendance_date', '>=', 'se.enrolled_at'))
                            ->where(fn (Builder $q) => $q->whereNull('se.withdrawn_at')->orWhereColumn('ar.attendance_date', '<=', 'se.withdrawn_at'));
                    });
            });
    }

    public function studentSummary(StudentProfile|int $student, int $academicYearId, ?string $asOf = null): array
    {
        $student = $student instanceof StudentProfile ? $student : StudentProfile::query()->findOrFail($student);
        $year = AcademicYear::query()->findOrFail($academicYearId);
        $asOf = $this->boundedAsOf($year, $asOf);
        $records = $this->validRecordsQuery($year->id, $asOf)
            ->where('ar.student_profile_id', $student->id)
            ->orderBy('ar.attendance_date')
            ->get($this->recordColumns());
        $courseId = StudentEnrollment::query()
            ->where('academic_year_id', $year->id)
            ->where('student_profile_id', $student->id)
            ->latest('id')
            ->value('course_section_id');
        $nonSchoolDates = DB::table('school_days')->where('academic_year_id', $year->id)->where('is_school_day', false)->pluck('date');
        $hasPreviousCase = AttendanceCase::query()->where('student_profile_id', $student->id)->where('academic_year_id', $year->id)->exists();
        $analysis = $this->analyzeDataset(new AttendanceDataset(
            $student->id, $year->id, $courseId ? (int) $courseId : null, $records, $nonSchoolDates,
            $this->settings->forYear($year->id)['pattern_settings'],
        ), $hasPreviousCase);

        return [
            'student' => ['id' => $student->id, 'name' => $student->registered_name_resolved, 'rut' => $student->rut],
            ...$analysis,
            'patterns' => AttendancePatternDetection::query()->where('student_profile_id', $student->id)->where('academic_year_id', $year->id)->where('is_active', true)->get(),
            'snapshots' => AttendanceRiskSnapshot::query()->where('student_profile_id', $student->id)->where('academic_year_id', $year->id)->orderBy('snapshot_date')->get(),
        ];
    }

    public function analyzeDataset(AttendanceDataset $dataset, bool $hasPreviousCase = false): array
    {
        $records = $dataset->records;
        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent');
        $justified = $absent->where('is_justified', true)->count();
        $unjustified = $absent->count() - $justified;
        $comparison = $dataset->comparison();
        $recent30 = $dataset->recent(30);
        $recent15 = $dataset->recent(15);
        $recentWindow = (int) ($this->settings->forYear($dataset->academicYearId)['risk_thresholds']['critical_recent_school_days'] ?? 10);
        $metrics = [
            'school_days_elapsed' => $records->count(),
            'days_present' => $present,
            'days_absent' => $absent->count(),
            'justified_absences' => $justified,
            'unjustified_absences' => $unjustified,
            'late_arrivals' => $records->where('minutes_late', '>', 0)->count(),
            'early_departures' => $records->where('early_departure', true)->count(),
            'attendance_percentage' => $dataset->attendanceRate(),
            'attendance_last_30_days' => $dataset->attendanceRate($recent30),
            'attendance_last_15_days' => $dataset->attendanceRate($recent15),
            'consecutive_absences' => $dataset->currentAbsenceStreak(),
            'maximum_consecutive_absences' => $dataset->maximumAbsenceStreak(),
            'recent_absences' => $dataset->recent($recentWindow)->where('status', 'absent')->count(),
            'unjustified_absence_rate' => $absent->isEmpty() ? 0 : round(($unjustified / $absent->count()) * 100, 2),
            'trend_points' => $comparison['change'],
            'trend_drop_points' => ($comparison['change'] ?? 0) < 0 ? abs((float) $comparison['change']) : 0,
            'trend' => ($comparison['change'] ?? null) === null ? 'insufficient_data' : (($comparison['change'] > 2) ? 'improving' : (($comparison['change'] < -2) ? 'declining' : 'stable')),
            'has_previous_case' => $hasPreviousCase,
        ];
        $settings = $this->settings->forYear($dataset->academicYearId);
        $risk = $this->riskEngine->calculate($metrics, $settings);
        $patterns = $this->patternEngine->detect($dataset);

        return [
            'summary' => $metrics,
            'risk' => $risk,
            'patterns_detected' => $patterns,
            'records' => $records,
            'monthly' => $this->monthly($records),
            'calendar' => $records->map(fn (array $record) => [
                'date' => $record['date'], 'status' => $record['status'], 'is_justified' => $record['is_justified'],
                'minutes_late' => $record['minutes_late'], 'early_departure' => $record['early_departure'],
            ])->values(),
        ];
    }

    public function getLostSchoolDays(AttendanceDataset $dataset): int { return $dataset->records->where('status', 'absent')->count(); }
    public function getRecentAbsenceStreak(AttendanceDataset $dataset): int { return $dataset->currentAbsenceStreak(); }
    public function getFrequentWeekdays(AttendanceDataset $dataset): array { return collect([1, 2, 3, 4, 5])->mapWithKeys(fn (int $day) => [$day => $dataset->weekday($day)])->all(); }
    public function getAttendanceRecovery(AttendanceDataset $dataset): ?float { return $dataset->comparison()['change']; }

    public function recordColumns(): array
    {
        return ['ar.student_profile_id', 'ar.course_section_id', 'ar.attendance_date', 'ar.status', 'ar.is_justified', 'ar.minutes_late', 'ar.early_departure'];
    }

    private function boundedAsOf(AcademicYear $year, ?string $asOf): string
    {
        $date = CarbonImmutable::parse($asOf ?: now(config('attendance_management.timezone'))->toDateString());
        if ($date->lt($year->starts_at)) {
            return $year->starts_at->format('Y-m-d');
        }
        if ($date->gt($year->ends_at)) {
            return $year->ends_at->format('Y-m-d');
        }

        return $date->toDateString();
    }

    private function monthly(Collection $records): Collection
    {
        return $records->groupBy(fn (array $record) => substr($record['date'], 0, 7))->map(function (Collection $month, string $period): array {
            return [
                'period' => $period, 'present' => $month->where('status', 'present')->count(),
                'absent' => $month->where('status', 'absent')->count(), 'attendance_rate' => $month->isEmpty() ? null : round(($month->where('status', 'present')->count() / $month->count()) * 100, 2),
            ];
        })->values();
    }
}
