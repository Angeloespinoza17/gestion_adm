<?php

namespace App\Services\RelevantCalendar;

use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CalendarEventAccessService
{
    public function visibleEventsQuery(User $user): Builder
    {
        $query = CalendarEvent::query()->whereNull('deleted_at');

        if ($this->canViewAll($user) || $this->canManageAll($user)) {
            return $query;
        }

        $managedDepartmentIds = $this->managedDepartmentIds($user);

        return $query->where(function (Builder $builder) use ($user, $managedDepartmentIds) {
            if ($this->canManageDepartments($user) && $managedDepartmentIds !== []) {
                $builder->orWhereIn('department_id', $managedDepartmentIds);
            }

            $builder
                ->orWhere('responsible_user_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhere('completed_by', $user->id)
                ->orWhereHas('eventUsers', fn (Builder $eventUsers) => $eventUsers->where('user_id', $user->id));
        });
    }

    public function canView(User $user, CalendarEvent $event): bool
    {
        if ($this->canViewAll($user) || $this->canManageAll($user)) {
            return true;
        }

        if ($this->canManageDepartments($user) && $event->department_id && in_array($event->department_id, $this->managedDepartmentIds($user), true)) {
            return true;
        }

        if ((int) $event->responsible_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $event->created_by === (int) $user->id) {
            return true;
        }

        return $event->eventUsers()->where('user_id', $user->id)->exists();
    }

    public function canCreateForDepartment(User $user, ?int $departmentId): bool
    {
        if ($this->canManageAll($user)) {
            return true;
        }

        if (!$this->canManageDepartments($user) || !$departmentId) {
            return false;
        }

        return in_array($departmentId, $this->managedDepartmentIds($user), true);
    }

    public function canUpdate(User $user, CalendarEvent $event): bool
    {
        if ($this->canManageAll($user)) {
            return true;
        }

        return $this->canManageDepartments($user)
            && $event->department_id
            && in_array($event->department_id, $this->managedDepartmentIds($user), true);
    }

    public function canDelete(User $user, CalendarEvent $event): bool
    {
        return $this->canManageAll($user);
    }

    public function canViewAll(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('ver_todo_calendario_fechas_relevantes');
    }

    public function canManageDepartments(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('gestionar_calendario_fechas_relevantes_departamento');
    }

    public function canManageAll(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('administrar_calendario_fechas_relevantes');
    }

    /**
     * @return array<int, int>
     */
    public function managedDepartmentIds(User $user): array
    {
        if (!$user->staff_id) {
            return [];
        }

        return Department::query()
            ->where('responsible_staff_id', $user->staff_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
