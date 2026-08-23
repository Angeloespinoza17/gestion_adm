<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\MonthlyAttendanceImportRow;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class StudentMonthlyAttendanceContextService
{
    public function forStudent(StudentProfile|int $student, ?int $academicYearId = null): ?array
    {
        $studentId = $student instanceof StudentProfile ? $student->id : $student;

        return $this->forStudents(collect([$studentId]), $academicYearId)->get($studentId);
    }

    /**
     * @param  Collection<int,int>|array<int,int>  $studentIds
     * @return Collection<int,array<string,mixed>>
     */
    public function forStudents(Collection|array $studentIds, ?int $academicYearId = null): Collection
    {
        $ids = collect($studentIds)->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $yearId = $academicYearId ?: AcademicYear::query()->where('is_active', true)->value('id');
        if (! $yearId) {
            return collect();
        }

        $rows = MonthlyAttendanceImportRow::query()
            ->whereIn('student_profile_id', $ids)
            ->whereHas('monthlyImport', fn ($query) => $query
                ->where('academic_year_id', $yearId)
                ->where('is_active', true))
            ->with([
                'monthlyImport:id,academic_year_id,school_year,month,version,is_active,original_filename,completed_at',
                'courseSection:id,display_name',
            ])
            ->get([
                'id', 'monthly_attendance_import_id', 'student_profile_id', 'course_section_id',
                'present_days', 'absent_days', 'class_days', 'attendance_rate', 'is_sep_priority',
                'is_sep_preferential', 'is_pie', 'match_status', 'applied_at',
            ])
            ->sortByDesc(fn (MonthlyAttendanceImportRow $row): int => ($row->monthlyImport?->school_year ?? 0) * 1000000
                + ($row->monthlyImport?->month ?? 0) * 10000
                + ($row->monthlyImport?->version ?? 0) * 100
                + $row->id);

        return $rows->groupBy('student_profile_id')->map(function (Collection $studentRows): array {
            $history = $studentRows->unique(fn (MonthlyAttendanceImportRow $row): string => $row->monthlyImport?->school_year.'-'.$row->monthlyImport?->month)
                ->take(12)
                ->map(fn (MonthlyAttendanceImportRow $row): array => $this->rowPayload($row))
                ->values();
            $latest = $history->first();

            return [
                'latest' => $latest,
                'history' => $history,
                'source' => 'Excel de asistencia mensual',
            ];
        });
    }

    /** @return array<string,mixed> */
    private function rowPayload(MonthlyAttendanceImportRow $row): array
    {
        $year = $row->monthlyImport?->school_year;
        $month = $row->monthlyImport?->month;

        return [
            'period' => $year && $month ? sprintf('%04d-%02d', $year, $month) : null,
            'year' => $year,
            'month' => $month,
            'course' => $row->courseSection?->display_name,
            'present_days' => $row->present_days,
            'absent_days' => $row->absent_days,
            'class_days' => $row->class_days,
            'attendance_rate' => $row->attendance_rate,
            'is_sep_priority' => $row->is_sep_priority,
            'is_sep_preferential' => $row->is_sep_preferential,
            'sep_classification' => $row->is_sep_priority ? 'prioritaria' : ($row->is_sep_preferential ? 'preferente' : null),
            'is_pie' => $row->is_pie,
            'match_status' => $row->match_status,
            'source_filename' => $row->monthlyImport?->original_filename,
            'applied_at' => $row->applied_at?->toIso8601String(),
        ];
    }
}
