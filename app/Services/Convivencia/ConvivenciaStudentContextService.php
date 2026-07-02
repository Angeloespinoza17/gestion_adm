<?php

namespace App\Services\Convivencia;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConvivenciaStudentContextService
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    public function currentEnrollment(StudentProfile $student, ?AcademicYear $academicYear = null): ?StudentEnrollment
    {
        $year = $academicYear ?: $this->activeAcademicYear();

        if (!$year) {
            return null;
        }

        $student->loadMissing([
            'enrollments.academicYear:id,name,year,is_active',
            'enrollments.courseSection:id,academic_year_id,education_level_id,section_name,display_name',
            'enrollments.courseSection.educationLevel:id,name,order,type',
        ]);

        return $student->preferredEnrollment($year);
    }

    public function ageAt(StudentProfile $student, Carbon|string|null $date = null): ?int
    {
        if (!$student->birthdate) {
            return null;
        }

        $reference = $date instanceof Carbon
            ? $date
            : ($date ? Carbon::parse($date) : now());

        return Carbon::parse($student->birthdate)->diffInYears($reference);
    }

    public function studentSummary(StudentProfile $student, Carbon|string|null $date = null, ?User $viewer = null): array
    {
        $reference = $date instanceof Carbon
            ? $date
            : ($date ? Carbon::parse($date) : now());
        $enrollment = $this->currentEnrollment($student);
        $course = $enrollment?->courseSection;

        $caseQuery = ConvivenciaCase::query()->where('student_profile_id', $student->id);
        $complaintQuery = ConvivenciaComplaint::query()->where('affected_student_id', $student->id);
        $derivationQuery = ConvivenciaDerivation::query()->where('student_profile_id', $student->id);
        $measureQuery = ConvivenciaMeasure::query()->where('student_profile_id', $student->id);
        $interviewQuery = ConvivenciaInterview::query()->where('student_profile_id', $student->id);
        $dailyLogQuery = ConvivenciaDailyLog::query()->where('student_profile_id', $student->id);

        if ($viewer) {
            $this->accessService->applyCaseVisibility($caseQuery, $viewer);
            $this->accessService->applyComplaintVisibility($complaintQuery, $viewer);
            $this->accessService->applyDerivationVisibility($derivationQuery, $viewer);
            $this->accessService->applyMeasureVisibility($measureQuery, $viewer);
            $this->accessService->applyInterviewVisibility($interviewQuery, $viewer);
            $this->accessService->applyDailyLogVisibility($dailyLogQuery, $viewer);
        }

        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'registered_name' => $student->registered_name_resolved,
            'rut' => $student->rut,
            'photo_url' => '/build/images/users/user-dummy-img.jpg',
            'age' => $this->ageAt($student, $reference),
            'course' => $enrollment?->snapshot_course_display_name,
            'course_section_id' => $course?->id,
            'academic_year' => $enrollment?->snapshot_year_name,
            'guardian_name' => $student->guardian_name,
            'guardian_phone' => $student->guardian_phone,
            'guardian_email' => $student->guardian_email,
            'alerts' => array_values(array_filter([
                $student->pickup_restriction ? 'Restricción de retiro registrada' : null,
                $student->has_judicial_process ? 'Proceso judicial informado' : null,
                $student->porter_alert_notes ? 'Alerta de portería registrada' : null,
            ])),
            'history_summary' => [
                'cases_total' => (clone $caseQuery)->count(),
                'open_cases_total' => (clone $caseQuery)->whereNotIn('status', ['cerrado', 'archivado'])->count(),
                'complaints_total' => (clone $complaintQuery)->count(),
                'derivations_total' => (clone $derivationQuery)->count(),
                'pending_measures_total' => (clone $measureQuery)->whereIn('status', ['asignada', 'en_proceso', 'reprogramada'])->count(),
                'interviews_total' => (clone $interviewQuery)->count(),
                'daily_logs_total' => (clone $dailyLogQuery)->count(),
                'last_case_opened_at' => (clone $caseQuery)->max('opened_at'),
            ],
            'recent_cases' => (clone $caseQuery)
                ->latest('opened_at')
                ->limit(5)
                ->get(['id', 'folio', 'opened_at', 'classification_label', 'criticality_label', 'status'])
                ->values()
                ->all(),
        ];
    }

    public function studentSearchQuery(string $search, ?int $courseSectionId = null): Builder
    {
        $activeYear = $this->activeAcademicYear();

        return StudentProfile::query()
            ->with([
                'enrollments' => fn ($query) => $query
                    ->when($activeYear, fn ($inner) => $inner->where('academic_year_id', $activeYear->id))
                    ->with('courseSection:id,display_name'),
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('registered_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhere('guardian_name', 'like', "%{$search}%")
                        ->orWhere('guardian_backup_name', 'like', "%{$search}%");
                });
            })
            ->when($courseSectionId, function (Builder $query) use ($courseSectionId, $activeYear) {
                $query->whereHas('enrollments', function (Builder $inner) use ($courseSectionId, $activeYear) {
                    if ($activeYear) {
                        $inner->where('academic_year_id', $activeYear->id);
                    }

                    $inner->where('course_section_id', $courseSectionId);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchPayload(string $search, ?int $courseSectionId = null, int $limit = 12, ?User $viewer = null): array
    {
        return $this->studentSearchQuery($search, $courseSectionId)
            ->limit($limit)
            ->get()
            ->map(function (StudentProfile $student) use ($viewer) {
                $summary = $this->studentSummary($student, null, $viewer);

                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'rut' => $student->rut,
                    'course' => $summary['course'],
                    'course_section_id' => $summary['course_section_id'],
                    'age' => $summary['age'],
                    'guardian_name' => $summary['guardian_name'],
                    'alerts' => $summary['alerts'],
                    'history_summary' => $summary['history_summary'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value:int,label:string}>
     */
    public function studentOptions(?int $courseSectionId = null): Collection
    {
        return $this->studentSearchQuery('', $courseSectionId)
            ->limit(200)
            ->get()
            ->map(function (StudentProfile $student) {
                $summary = $this->studentSummary($student);

                return [
                    'value' => $student->id,
                    'label' => trim(sprintf('%s%s', $student->registered_name_resolved, $summary['course'] ? ' · ' . $summary['course'] : '')),
                ];
            })
            ->values();
    }
}
