<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceDataQualityIssue;
use App\Models\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceDataQualityService
{
    public function scan(int $academicYearId, ?int $courseSectionId = null): Collection
    {
        $issues = collect();
        $pendingDays = DB::table('school_days')
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'pending_confirmation')
            ->get(['id', 'date', 'label']);
        foreach ($pendingDays as $day) {
            $issues->push($this->issue(
                $academicYearId,
                $courseSectionId,
                null,
                'course_day_not_closed',
                'warning',
                'Jornada pendiente de confirmación',
                "La jornada {$day->date} aún no fue confirmada.",
                'Revisar los registros y confirmar o corregir la jornada.',
                ['school_day_id' => $day->id, 'date' => $day->date],
            ));
        }

        $failedImports = DB::table('attendance_imports')
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->where('status', 'failed')
            ->get(['id', 'course_section_id', 'original_filename', 'failure_message']);
        foreach ($failedImports as $import) {
            $issues->push($this->issue(
                $academicYearId,
                $import->course_section_id,
                null,
                'failed_import',
                'critical',
                'Importación de asistencia fallida',
                "No se completó la importación {$import->original_filename}.",
                'Corregir el archivo o la asociación del curso y volver a importar.',
                ['attendance_import_id' => $import->id, 'failure' => $import->failure_message],
            ));
        }

        $withoutEnrollment = DB::table('attendance_records as ar')
            ->leftJoin('student_enrollments as se', 'se.id', '=', 'ar.student_enrollment_id')
            ->where('ar.academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('ar.course_section_id', $courseSectionId))
            ->whereNull('se.id')
            ->select('ar.course_section_id', 'ar.student_profile_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('ar.course_section_id', 'ar.student_profile_id')
            ->get();
        foreach ($withoutEnrollment as $row) {
            $issues->push($this->issue(
                $academicYearId,
                $row->course_section_id,
                $row->student_profile_id,
                'record_without_enrollment',
                'critical',
                'Registros sin matrícula asociada',
                "Se detectaron {$row->total} registros sin una matrícula válida.",
                'Asociar la estudiante a una matrícula del año y revisar el origen de los registros.',
                ['records' => (int) $row->total],
            ));
        }

        $nonSchoolDays = DB::table('attendance_records as ar')
            ->join('school_days as sd', 'sd.id', '=', 'ar.school_day_id')
            ->where('ar.academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('ar.course_section_id', $courseSectionId))
            ->where('sd.is_school_day', false)
            ->select('ar.course_section_id', 'ar.attendance_date')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('ar.course_section_id', 'ar.attendance_date')
            ->get();
        foreach ($nonSchoolDays as $row) {
            $issues->push($this->issue(
                $academicYearId,
                $row->course_section_id,
                null,
                'attendance_on_non_school_day',
                'critical',
                'Asistencia en día no lectivo',
                "Hay {$row->total} registros el {$row->attendance_date}, marcado como no lectivo.",
                'Validar el calendario antes de modificar registros.',
                ['date' => $row->attendance_date, 'records' => (int) $row->total],
            ));
        }

        $invalidStatuses = DB::table('attendance_records')
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->whereNotIn('status', ['present', 'absent'])
            ->select('course_section_id', 'status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('course_section_id', 'status')
            ->get();
        foreach ($invalidStatuses as $row) {
            $issues->push($this->issue(
                $academicYearId,
                $row->course_section_id,
                null,
                'invalid_status',
                'critical',
                'Estado de asistencia inválido',
                "El estado {$row->status} aparece en {$row->total} registros.",
                'Revisar y corregir los estados mediante una acción auditada.',
                ['status' => $row->status, 'records' => (int) $row->total],
            ));
        }

        $coverage = DB::table('attendance_records')
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->select('course_section_id', 'attendance_date')
            ->selectRaw('COUNT(DISTINCT student_profile_id) as records')
            ->groupBy('course_section_id', 'attendance_date')
            ->get();
        $rosters = StudentEnrollment::query()
            ->where('academic_year_id', $academicYearId)
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->selectRaw('course_section_id, COUNT(*) as total')
            ->groupBy('course_section_id')
            ->pluck('total', 'course_section_id');
        foreach ($coverage as $row) {
            $expected = (int) ($rosters[$row->course_section_id] ?? 0);
            $difference = abs($expected - (int) $row->records);
            if ($expected === 0 || $difference === 0) {
                continue;
            }
            $issues->push($this->issue(
                $academicYearId,
                $row->course_section_id,
                null,
                'roster_record_difference',
                $difference > max(3, $expected * 0.1) ? 'critical' : 'warning',
                'Diferencia entre matrícula y registros',
                "El {$row->attendance_date} se esperaban {$expected} estudiantes y existen {$row->records} registros.",
                'Revisar matrícula vigente, retiros y registros faltantes del día.',
                ['date' => $row->attendance_date, 'roster' => $expected, 'records' => (int) $row->records],
            ));
        }

        $activeFingerprints = $issues->pluck('fingerprint')->filter()->values();
        AttendanceDataQualityIssue::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->where('status', 'open')
            ->when($activeFingerprints->isNotEmpty(), fn ($query) => $query->whereNotIn('fingerprint', $activeFingerprints))
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        return AttendanceDataQualityIssue::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->with(['courseSection:id,display_name'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->latest('detected_at')
            ->get();
    }

    private function issue(
        int $yearId,
        ?int $courseId,
        ?int $studentId,
        string $type,
        string $severity,
        string $title,
        string $description,
        string $suggestedAction,
        array $context,
    ): AttendanceDataQualityIssue {
        $fingerprint = hash('sha256', json_encode([$yearId, $courseId, $studentId, $type, $context]));

        return AttendanceDataQualityIssue::query()->updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'academic_year_id' => $yearId,
                'course_section_id' => $courseId,
                'student_profile_id' => $studentId,
                'type' => $type,
                'severity' => $severity,
                'status' => 'open',
                'title' => $title,
                'description' => $description,
                'suggested_action' => $suggestedAction,
                'context' => $context,
                'detected_at' => now(),
                'resolved_at' => null,
            ],
        );
    }
}
