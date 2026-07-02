<?php

namespace App\Services\ApoyoProfesional;

use App\Models\AcademicYear;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ApoyoProfesionalStudentContextService
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    private ?Collection $teachers = null;

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

    public function teacherForCourse(?CourseSection $courseSection): ?Staff
    {
        if (!$courseSection) {
            return $this->teachers()->first();
        }

        $teachers = $this->teachers();

        if ($teachers->isEmpty()) {
            return null;
        }

        return $teachers->values()->get(((int) $courseSection->id) % $teachers->count()) ?: $teachers->first();
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
        $teacher = $this->teacherForCourse($enrollment?->courseSection);

        $attentionBaseQuery = ApoyoAtencion::query()
            ->where('student_profile_id', $student->id);

        if ($viewer) {
            $this->accessService->applyAttentionVisibility($attentionBaseQuery, $viewer);
        }

        $professionals = (clone $attentionBaseQuery)
            ->select('professional_role_name', 'professional_area_name')
            ->distinct()
            ->orderBy('professional_role_name')
            ->get()
            ->map(fn (ApoyoAtencion $attention) => trim(($attention->professional_role_name ?: '') . ' ' . ($attention->professional_area_name ? '· ' . $attention->professional_area_name : '')))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'registered_name' => $student->registered_name_resolved,
            'rut' => $student->rut,
            'photo_url' => '/build/images/users/user-dummy-img.jpg',
            'age' => $this->ageAt($student, $reference),
            'course' => $enrollment?->snapshot_course_display_name,
            'academic_year' => $enrollment?->snapshot_year_name,
            'teacher_name' => $teacher?->full_name,
            'guardian_name' => $student->guardian_name,
            'guardian_phone' => $student->guardian_phone,
            'guardian_email' => $student->guardian_email,
            'alerts' => array_values(array_filter([
                $student->has_chronic_illness ? 'Salud crónica registrada' : null,
                $student->has_medication_allergies ? 'Alergias registradas' : null,
                $student->has_physical_restrictions ? 'Restricciones físicas' : null,
                $student->pickup_restriction ? 'Restricción de retiro' : null,
            ])),
            'history_summary' => [
                'attentions_total' => (clone $attentionBaseQuery)->count(),
                'active_cases_total' => (clone $attentionBaseQuery)
                    ->whereNotIn('status', ['cerrada', 'anulada'])
                    ->count(),
                'derivations_total' => $this->derivationQueryForStudent($student->id, $viewer)->count(),
                'follow_ups_pending_total' => $this->followUpQueryForStudent($student->id, $viewer)
                    ->whereIn('status', ['pendiente', 'reprogramado'])
                    ->count(),
                'plans_active_total' => $this->planQueryForStudent($student->id, $viewer)
                    ->whereIn('status', ['disenado', 'en_ejecucion', 'en_seguimiento'])
                    ->count(),
                'interviews_total' => $this->interviewQueryForStudent($student->id, $viewer)->count(),
                'last_attention_at' => (clone $attentionBaseQuery)->max('attended_at'),
            ],
            'professionals' => $professionals,
            'recent_attentions' => (clone $attentionBaseQuery)
                ->latest('attended_at')
                ->limit(5)
                ->get(['id', 'attended_at', 'professional_role_name', 'reason_summary', 'status', 'confidentiality_level'])
                ->map(fn (ApoyoAtencion $attention) => [
                    'id' => $attention->id,
                    'attended_at' => $attention->attended_at,
                    'professional_role_name' => $attention->professional_role_name,
                    'reason_summary' => $attention->reason_summary,
                    'status' => $attention->status,
                    'confidentiality_level' => $attention->confidentiality_level,
                ])
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
                        ->orWhere('rut', 'like', "%{$search}%");
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
                    'teacher_name' => $summary['teacher_name'],
                    'age' => $summary['age'],
                    'photo_url' => $summary['photo_url'],
                    'alerts' => $summary['alerts'],
                    'history_summary' => $summary['history_summary'],
                ];
            })
            ->values()
            ->all();
    }

    private function derivationQueryForStudent(int $studentId, ?User $viewer): Builder
    {
        $query = ApoyoDerivacion::query()->where('student_profile_id', $studentId);

        if ($viewer) {
            $this->accessService->applyDerivationVisibility($query, $viewer);
        }

        return $query;
    }

    private function followUpQueryForStudent(int $studentId, ?User $viewer): Builder
    {
        $query = ApoyoSeguimiento::query()->where('student_profile_id', $studentId);

        if ($viewer && !$this->accessService->canViewTeamAttentions($viewer)) {
            $query->where('responsible_user_id', $viewer->id);
        }

        return $query;
    }

    private function planQueryForStudent(int $studentId, ?User $viewer): Builder
    {
        $query = ApoyoPlan::query()->where('student_profile_id', $studentId);

        if ($viewer && !$this->accessService->canViewTeamAttentions($viewer) && !$this->accessService->canViewConfidentialAttentions($viewer)) {
            $query->where('responsible_user_id', $viewer->id);
        }

        return $query;
    }

    private function interviewQueryForStudent(int $studentId, ?User $viewer): Builder
    {
        $query = ApoyoEntrevista::query()->where('student_profile_id', $studentId);

        if ($viewer && !$this->accessService->canViewTeamAttentions($viewer) && !$this->accessService->canViewConfidentialAttentions($viewer)) {
            $query->where('professional_user_id', $viewer->id);
        }

        return $query;
    }

    private function teachers(): Collection
    {
        if ($this->teachers instanceof Collection) {
            return $this->teachers;
        }

        return $this->teachers = Staff::query()
            ->with('cargo:id,slug')
            ->where('active', true)
            ->whereHas('cargo', fn ($query) => $query->whereIn('slug', ['docente', 'coordinador_academico']))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'cargo_id']);
    }
}
