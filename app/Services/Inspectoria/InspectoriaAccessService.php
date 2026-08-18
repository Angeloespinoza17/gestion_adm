<?php

namespace App\Services\Inspectoria;

use App\Models\AcademicYear;
use App\Models\Inspectoria\InspectoriaCourseAssignment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InspectoriaAccessService
{
    public const VIEW = 'ver_modulo_inspectoria';

    public const ATTENTIONS = 'registrar_atenciones_inspectoria';

    public const ASSIGNMENTS = 'asignar_cursos_inspectoria';

    public const PASSES = 'gestionar_pases_inspectoria';

    public const STUDENTS = 'ver_fichas_inspectoria';

    public const WITHDRAWALS = 'ver_retiros_inspectoria';

    public const RESTRICTIONS = 'social_work.pickup_restrictions.manage';

    public const DAILY_LOG = 'registrar_bitacora_inspectoria';

    public const STATISTICS = 'ver_estadisticas_inspectoria';

    /** @var array<int, Collection<int, int>> */
    private array $assignedCourseIds = [];

    public function canView(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW, self::ATTENTIONS, self::ASSIGNMENTS, self::PASSES, self::STUDENTS, self::WITHDRAWALS, self::DAILY_LOG, self::STATISTICS]);
    }

    public function can(?User $user, string $permission): bool
    {
        if ($permission === self::ASSIGNMENTS && $this->isCourseScoped($user)) {
            return false;
        }

        return $this->hasAny($user, [$permission]);
    }

    public function isCourseScoped(?User $user): bool
    {
        if (! $user || $user->isSuperAdmin()) {
            return false;
        }

        return $user->roles()->where('slug', 'inspectoria')->exists()
            || $user->cargo()->where('slug', 'inspectoria')->exists()
            || $user->staff()->whereHas('cargo', fn (Builder $query) => $query->where('slug', 'inspectoria'))->exists();
    }

    /** @return Collection<int, int> */
    public function assignedCourseIds(User $user): Collection
    {
        if (! $this->isCourseScoped($user) || ! $user->staff_id) {
            return collect();
        }

        return $this->assignedCourseIds[$user->id] ??= InspectoriaCourseAssignment::query()
            ->where('inspector_staff_id', $user->staff_id)
            ->where('active', true)
            ->whereDate('starts_on', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->pluck('course_section_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function scopeToAssignedCourses(Builder $query, User $user, string $column = 'course_section_id'): Builder
    {
        if (! $this->isCourseScoped($user)) {
            return $query;
        }

        return $query->whereIn($column, $this->assignedCourseIds($user));
    }

    public function scopeDailyLogs(Builder $query, User $user): Builder
    {
        if (! $this->isCourseScoped($user)) {
            return $query;
        }

        $courseIds = $this->assignedCourseIds($user);

        return $query->where(function (Builder $inner) use ($courseIds, $user) {
            $inner->whereIn('course_section_id', $courseIds)
                ->orWhereHas('associatedCourses', fn (Builder $courses) => $courses->whereIn('course_sections.id', $courseIds))
                ->orWhere(function (Builder $general) use ($user) {
                    $general->whereNull('course_section_id')
                        ->where('inspector_staff_id', $user->staff_id);
                });
        });
    }

    public function canAccessCourse(User $user, ?int $courseSectionId): bool
    {
        if (! $this->isCourseScoped($user)) {
            return true;
        }

        return $courseSectionId !== null && $this->assignedCourseIds($user)->contains($courseSectionId);
    }

    public function canAccessStudent(User $user, int $studentId): bool
    {
        if (! $this->isCourseScoped($user)) {
            return true;
        }

        $courseIds = $this->assignedCourseIds($user);
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        return $courseIds->isNotEmpty() && StudentProfile::query()
            ->whereKey($studentId)
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->whereIn('course_section_id', $courseIds)
                ->when($activeYearId, fn (Builder $inner) => $inner->where('academic_year_id', $activeYearId)))
            ->exists();
    }

    private function hasAny(?User $user, array $permissions): bool
    {
        if (! $user || ! $user->active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
