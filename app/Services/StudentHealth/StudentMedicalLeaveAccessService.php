<?php

namespace App\Services\StudentHealth;

use App\Models\AcademicYear;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Database\Eloquent\Builder;

class StudentMedicalLeaveAccessService
{
    public const VIEW_PERMISSION = 'ver_licencias_medicas_estudiantes';

    public const CREATE_PERMISSION = 'crear_licencias_medicas_estudiantes';

    private bool $academicYearResolved = false;

    private ?AcademicYear $academicYear = null;

    public function __construct(
        private readonly InspectoriaAccessService $inspectoriaAccess,
    ) {}

    public function canView(?User $user): bool
    {
        return $this->hasPermission($user, self::VIEW_PERMISSION)
            || $this->hasPermission($user, self::CREATE_PERMISSION);
    }

    public function canCreate(?User $user): bool
    {
        return $this->hasPermission($user, self::CREATE_PERMISSION);
    }

    public function scopeCertificates(Builder $query, User $user): Builder
    {
        if (! $this->inspectoriaAccess->isCourseScoped($user)) {
            return $query;
        }

        $courseIds = $this->inspectoriaAccess->assignedCourseIds($user);
        $currentAcademicYearId = $this->currentAcademicYearId();

        return $query->whereHas('student.enrollments', function (Builder $enrollments) use ($courseIds, $currentAcademicYearId): void {
            $enrollments
                ->whereIn('course_section_id', $courseIds)
                ->when($currentAcademicYearId, fn (Builder $inner) => $inner->where('academic_year_id', $currentAcademicYearId));
        });
    }

    public function scopeStudents(Builder $query, User $user): Builder
    {
        if (! $this->inspectoriaAccess->isCourseScoped($user)) {
            return $query;
        }

        $courseIds = $this->inspectoriaAccess->assignedCourseIds($user);
        $currentAcademicYearId = $this->currentAcademicYearId();

        return $query->whereHas('enrollments', function (Builder $enrollments) use ($courseIds, $currentAcademicYearId): void {
            $enrollments
                ->whereIn('course_section_id', $courseIds)
                ->when($currentAcademicYearId, fn (Builder $inner) => $inner->where('academic_year_id', $currentAcademicYearId));
        });
    }

    public function canAccessStudent(User $user, int $studentProfileId): bool
    {
        return $this->scopeStudents(StudentProfile::query(), $user)
            ->whereKey($studentProfileId)
            ->exists();
    }

    public function currentAcademicYear(): ?AcademicYear
    {
        if ($this->academicYearResolved) {
            return $this->academicYear;
        }

        $this->academicYearResolved = true;
        $this->academicYear = AcademicYear::query()
            ->where('year', today()->year)
            ->first()
            ?? AcademicYear::query()->where('is_active', true)->orderByDesc('year')->first();

        return $this->academicYear;
    }

    private function currentAcademicYearId(): ?int
    {
        return $this->currentAcademicYear()?->id;
    }

    private function hasPermission(?User $user, string $permission): bool
    {
        return (bool) $user
            && $user->active
            && ($user->isSuperAdmin() || $user->hasPermission($permission));
    }
}
