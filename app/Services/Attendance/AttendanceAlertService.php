<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceAlertRule;
use App\Models\Attendance\AttendanceRecord;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AttendanceAlertService
{
    public function rebuild(int $academicYearId, ?int $courseSectionId = null): void
    {
        $rules = $this->rules($academicYearId);
        $records = AttendanceRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->orderBy('attendance_date')
            ->get(['course_section_id', 'student_profile_id', 'attendance_date', 'status', 'minutes_late']);
        $studentNames = StudentProfile::query()
            ->whereIn('id', $records->pluck('student_profile_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'registered_name'])
            ->keyBy('id');
        $activeKeys = [];

        foreach ($records->groupBy(fn ($record) => $record->course_section_id.'|'.$record->student_profile_id) as $group) {
            $first = $group->first();
            $total = $group->count();
            $present = $group->where('status', 'present')->count();
            $rate = $total > 0 ? round(($present / $total) * 100, 2) : 0;
            $student = $studentNames->get($first->student_profile_id);
            $name = $student?->registered_name_resolved ?? 'Estudiante';

            $attendanceRule = $this->matchingRule($rules, 'attendance_rate', $rate);
            if ($attendanceRule) {
                $key = $this->upsertStudentAlert(
                    $academicYearId,
                    $first->course_section_id,
                    $first->student_profile_id,
                    'low_attendance',
                    $attendanceRule->severity,
                    $rate,
                    (float) $attendanceRule->threshold,
                    "Asistencia bajo el umbral: {$name}",
                    "La asistencia acumulada es {$rate}%.",
                    ['present' => $present, 'absent' => $total - $present, 'total' => $total],
                );
                $activeKeys[] = $key;
            }

            $consecutive = $this->maximumConsecutiveAbsences($group);
            $consecutiveRule = $this->matchingRule($rules, 'consecutive_absences', $consecutive);
            if ($consecutiveRule) {
                $key = $this->upsertStudentAlert(
                    $academicYearId,
                    $first->course_section_id,
                    $first->student_profile_id,
                    'consecutive_absences',
                    $consecutiveRule->severity,
                    $consecutive,
                    (float) $consecutiveRule->threshold,
                    "Ausencias consecutivas: {$name}",
                    "Se detectaron {$consecutive} inasistencias consecutivas.",
                    ['maximum_consecutive_absences' => $consecutive],
                );
                $activeKeys[] = $key;
            }

            $monthlyRates = $group->groupBy(fn ($record) => $record->attendance_date->format('Y-m'))
                ->map(fn (Collection $month) => round(($month->where('status', 'present')->count() / $month->count()) * 100, 2))
                ->values();
            if ($monthlyRates->count() >= 2) {
                $drop = round($monthlyRates->get($monthlyRates->count() - 2) - $monthlyRates->last(), 2);
                $dropRule = $this->matchingRule($rules, 'period_drop', $drop);
                if ($dropRule) {
                    $key = $this->upsertStudentAlert(
                        $academicYearId,
                        $first->course_section_id,
                        $first->student_profile_id,
                        'monthly_drop',
                        $dropRule->severity,
                        $drop,
                        (float) $dropRule->threshold,
                        "Deterioro mensual: {$name}",
                        "La asistencia bajó {$drop} puntos respecto del mes anterior.",
                        ['drop_points' => $drop],
                    );
                    $activeKeys[] = $key;
                }
            }

            $lastMonth = $group->groupBy(fn ($record) => $record->attendance_date->format('Y-m'))->last();
            $monthlyAbsences = $lastMonth?->where('status', 'absent')->count() ?? 0;
            $monthlyAbsenceRule = $this->matchingRule($rules, 'monthly_absences', $monthlyAbsences);
            if ($monthlyAbsenceRule) {
                $activeKeys[] = $this->upsertStudentAlert(
                    $academicYearId, $first->course_section_id, $first->student_profile_id,
                    'monthly_absences', $monthlyAbsenceRule->severity, $monthlyAbsences,
                    (float) $monthlyAbsenceRule->threshold, "Ausencias mensuales: {$name}",
                    "Se registraron {$monthlyAbsences} ausencias durante el último mes con datos.",
                    ['monthly_absences' => $monthlyAbsences],
                );
            }

            $lateCount = $group->where('minutes_late', '>', 0)->count();
            $lateRule = $this->matchingRule($rules, 'late_count', $lateCount);
            if ($lateRule) {
                $activeKeys[] = $this->upsertStudentAlert(
                    $academicYearId, $first->course_section_id, $first->student_profile_id,
                    'frequent_lateness', $lateRule->severity, $lateCount, (float) $lateRule->threshold,
                    "Atrasos frecuentes: {$name}", "Se registraron {$lateCount} atrasos en el periodo.",
                    ['late_count' => $lateCount],
                );
            }
        }

        foreach ($records->groupBy(fn ($record) => $record->course_section_id.'|'.$record->attendance_date->format('Y-m-d')) as $group) {
            $present = $group->where('status', 'present')->count();
            $rate = $group->count() > 0 ? round(($present / $group->count()) * 100, 2) : 0;
            $courseRule = $this->matchingRule($rules, 'attendance_rate', $rate);
            if (! $courseRule) {
                continue;
            }

            $first = $group->first();
            $date = $first->attendance_date->format('Y-m-d');
            $alert = AttendanceAlert::query()->firstOrNew([
                'academic_year_id' => $academicYearId,
                'course_section_id' => $first->course_section_id,
                'student_profile_id' => null,
                'type' => 'low_course_day',
                'detected_on' => $date,
            ]);
            $alert->fill([
                'severity' => $rate === 0.0 ? 'critical' : $courseRule->severity,
                'status' => in_array($alert->status, ['acknowledged', 'in_progress'], true) ? $alert->status : 'open',
                'metric_value' => $rate,
                'threshold_value' => $courseRule->threshold,
                'title' => 'Jornada con baja asistencia del curso',
                'description' => "La asistencia del {$date} fue {$rate}%.",
                'context' => ['present' => $present, 'absent' => $group->count() - $present],
            ])->save();
            $activeKeys[] = 'course|'.$alert->id;
        }

        $studentActiveIds = collect($activeKeys)
            ->filter(fn (string $key) => str_starts_with($key, 'student|'))
            ->map(fn (string $key) => (int) Str::afterLast($key, '|'));
        AttendanceAlert::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->whereNotNull('student_profile_id')
            ->whereIn('status', ['open', 'acknowledged', 'in_progress'])
            ->when($studentActiveIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $studentActiveIds))
            ->when($studentActiveIds->isEmpty(), fn ($query) => $query)
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        $courseActiveIds = collect($activeKeys)
            ->filter(fn (string $key) => str_starts_with($key, 'course|'))
            ->map(fn (string $key) => (int) Str::afterLast($key, '|'));
        AttendanceAlert::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->whereNull('student_profile_id')
            ->where('type', 'low_course_day')
            ->whereIn('status', ['open', 'acknowledged', 'in_progress'])
            ->when($courseActiveIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $courseActiveIds))
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    private function upsertStudentAlert(
        int $academicYearId,
        int $courseSectionId,
        int $studentProfileId,
        string $type,
        string $severity,
        float|int $metric,
        float|int $threshold,
        string $title,
        string $description,
        array $context,
    ): string {
        $identity = [
            'academic_year_id' => $academicYearId,
            'course_section_id' => $courseSectionId,
            'student_profile_id' => $studentProfileId,
            'type' => $type,
        ];
        $alert = AttendanceAlert::query()
            ->where($identity)
            ->whereIn('status', ['open', 'acknowledged', 'in_progress'])
            ->first() ?? new AttendanceAlert($identity);
        if (! $alert->exists) {
            $alert->status = 'open';
            $alert->detected_on = now()->toDateString();
        }
        $alert->fill([
            'severity' => $severity,
            'metric_value' => $metric,
            'threshold_value' => $threshold,
            'title' => $title,
            'description' => $description,
            'context' => $context,
        ])->save();

        return 'student|'.$alert->id;
    }

    private function maximumConsecutiveAbsences(Collection $records): int
    {
        $maximum = 0;
        $current = 0;
        foreach ($records as $record) {
            $current = $record->status === 'absent' ? $current + 1 : 0;
            $maximum = max($maximum, $current);
        }

        return $maximum;
    }

    private function rules(int $academicYearId): Collection
    {
        if (! Schema::hasTable('attendance_alert_rules')) {
            return collect($this->fallbackRules())->map(fn (array $rule) => (object) $rule);
        }

        $rules = AttendanceAlertRule::query()
            ->where(fn ($query) => $query->where('academic_year_id', $academicYearId)->orWhereNull('academic_year_id'))
            ->where('active', true)
            ->orderByRaw('CASE WHEN academic_year_id IS NULL THEN 1 ELSE 0 END')
            ->get()
            ->unique('code')
            ->values();

        return $rules->isNotEmpty() ? $rules : collect($this->fallbackRules())->map(fn (array $rule) => (object) $rule);
    }

    private function matchingRule(Collection $rules, string $metric, float|int $value): ?object
    {
        return $rules
            ->where('metric', $metric)
            ->filter(fn ($rule) => $this->matches((string) $rule->operator, (float) $value, (float) $rule->threshold))
            ->sortByDesc(fn ($rule) => match ($rule->severity) {
                'critical' => 3, 'warning' => 2, default => 1
            })
            ->first();
    }

    private function matches(string $operator, float $value, float $threshold): bool
    {
        return match ($operator) {
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'eq' => abs($value - $threshold) < 0.00001,
            default => false,
        };
    }

    private function fallbackRules(): array
    {
        return [
            ['code' => 'attendance_warning', 'metric' => 'attendance_rate', 'operator' => 'lt', 'threshold' => (float) config('attendance.warning_threshold', 90), 'severity' => 'warning'],
            ['code' => 'attendance_critical', 'metric' => 'attendance_rate', 'operator' => 'lt', 'threshold' => (float) config('attendance.critical_threshold', 85), 'severity' => 'critical'],
            ['code' => 'consecutive_absences', 'metric' => 'consecutive_absences', 'operator' => 'gte', 'threshold' => (int) config('attendance.consecutive_absence_threshold', 3), 'severity' => 'critical'],
            ['code' => 'monthly_drop', 'metric' => 'period_drop', 'operator' => 'gt', 'threshold' => 5, 'severity' => 'warning'],
        ];
    }
}
