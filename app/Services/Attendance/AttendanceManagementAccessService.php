<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceManagementAccessService
{
    public const VIEW = 'attendance_management.view';
    public const VIEW_ALL = 'attendance_management.view_all';
    public const MANAGE_CASES = 'attendance_management.manage_cases';
    public const MANAGE_INTERVENTIONS = 'attendance_management.manage_interventions';
    public const MANAGE_CAUSES = 'attendance_management.manage_causes';
    public const MANAGE_PLANS = 'attendance_management.manage_action_plans';
    public const EXPORT = 'attendance_management.export';
    public const VIEW_SENSITIVE = 'attendance_management.view_sensitive';
    public const CONFIGURE = 'attendance_management.configure';

    /** @var array<string, Collection<int,int>> */
    private array $courseCache = [];

    public function canView(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::VIEW, self::VIEW_ALL, 'attendance_statistics.view', 'attendance_statistics.view_global', 'attendance_statistics.view_student', 'attendance_statistics.view_course']));
    }

    public function canViewAll(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::VIEW_ALL, 'attendance_statistics.view_global']));
    }

    public function canViewSensitive(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::VIEW_SENSITIVE, 'attendance_statistics.view_sensitive_segments']));
    }

    public function canManageCases(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::MANAGE_CASES, 'attendance_statistics.manage_interventions']));
    }

    public function canManageInterventions(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::MANAGE_INTERVENTIONS, 'attendance_statistics.manage_interventions']));
    }

    public function canManageCauses(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::MANAGE_CAUSES, 'attendance_statistics.configure']));
    }

    public function canManagePlans(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::MANAGE_PLANS, 'attendance_statistics.manage_goals']));
    }

    public function canExport(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::EXPORT, 'attendance_statistics.export']));
    }

    public function canConfigure(User $user): bool
    {
        return $user->active && ($user->isSuperAdmin() || $this->hasAny($user, [self::CONFIGURE, 'attendance_statistics.configure']));
    }

    public function hasAnyForConfiguration(User $user): bool
    {
        return $this->canConfigure($user);
    }

    /** @return Collection<int,int> */
    public function courseIds(User $user, ?int $academicYearId = null): Collection
    {
        if ($this->canViewAll($user)) {
            return collect();
        }

        $key = $user->id.'|'.($academicYearId ?: 0);
        if (isset($this->courseCache[$key])) {
            return $this->courseCache[$key];
        }

        $ids = collect();
        if ($user->staff_id && Schema::hasTable('schedule_events')) {
            $ids->push(...DB::table('schedule_events')
                ->where('staff_id', $user->staff_id)
                ->where('activity_type', 'jefatura_course')
                ->whereNotNull('course_section_id')
                ->when($academicYearId, fn (QueryBuilder $query) => $query->where('academic_year_id', $academicYearId))
                ->pluck('course_section_id'));
        }
        if ($user->staff_id && Schema::hasTable('inspectoria_course_assignments')) {
            $ids->push(...DB::table('inspectoria_course_assignments')
                ->where('inspector_staff_id', $user->staff_id)
                ->where('active', true)
                ->whereDate('starts_on', '<=', today())
                ->where(fn (QueryBuilder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->when($academicYearId, fn (QueryBuilder $query) => $query->where('academic_year_id', $academicYearId))
                ->pluck('course_section_id'));
        }
        if (Schema::hasTable('lcd_teacher_assignments') && Schema::hasTable('lcd_teaching_groups')) {
            $ids->push(...DB::table('lcd_teacher_assignments as assignment')
                ->join('lcd_teaching_groups as teaching_group', 'teaching_group.id', '=', 'assignment.teaching_group_id')
                ->where('assignment.active', true)
                ->where(function (QueryBuilder $query) use ($user) {
                    $query->where('assignment.user_id', $user->id);
                    if ($user->staff_id) {
                        $query->orWhere('assignment.staff_id', $user->staff_id);
                    }
                })
                ->whereNotNull('teaching_group.course_section_id')
                ->when($academicYearId, fn (QueryBuilder $query) => $query->where('teaching_group.academic_year_id', $academicYearId))
                ->pluck('teaching_group.course_section_id'));
        }
        return $this->courseCache[$key] = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function applyCourseScope(EloquentBuilder|QueryBuilder $query, User $user, string $column = 'course_section_id', ?int $academicYearId = null): EloquentBuilder|QueryBuilder
    {
        if ($this->canViewAll($user)) {
            return $query;
        }

        return $query->whereIn($column, $this->courseIds($user, $academicYearId));
    }

    public function canViewStudent(User $user, int $studentProfileId, ?int $academicYearId = null): bool
    {
        if (! $this->canView($user)) {
            return false;
        }
        if ($this->canViewAll($user)) {
            return true;
        }
        if (Schema::hasTable('attendance_cases') && AttendanceCase::query()
            ->where('student_profile_id', $studentProfileId)
            ->when($academicYearId, fn (EloquentBuilder $query) => $query->where('academic_year_id', $academicYearId))
            ->where(function (EloquentBuilder $query) use ($user) {
                $query->where('responsible_user_id', $user->id)
                    ->orWhere('reference_adult_user_id', $user->id)
                    ->orWhereHas('participants', fn (EloquentBuilder $participants) => $participants
                        ->where('users.id', $user->id)
                        ->where('attendance_case_participants.active', true));
            })->exists()) {
            return true;
        }

        $courseIds = $this->courseIds($user, $academicYearId);
        if ($courseIds->isEmpty()) {
            return false;
        }

        return StudentEnrollment::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereIn('course_section_id', $courseIds)
            ->when($academicYearId, fn (EloquentBuilder $query) => $query->where('academic_year_id', $academicYearId))
            ->exists();
    }

    public function canViewCase(User $user, AttendanceCase $case): bool
    {
        if (! $this->canView($user)) {
            return false;
        }
        if ($this->canViewAll($user)) {
            return true;
        }
        if (in_array((int) $user->id, [(int) $case->responsible_user_id, (int) $case->reference_adult_user_id], true)) {
            return true;
        }
        if ($case->participants()->where('users.id', $user->id)->wherePivot('active', true)->exists()) {
            return true;
        }

        return $case->course_section_id && $this->courseIds($user, $case->academic_year_id)->contains((int) $case->course_section_id);
    }

    private function hasAny(User $user, array $permissions): bool
    {
        if (! $user->active) {
            return false;
        }
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
