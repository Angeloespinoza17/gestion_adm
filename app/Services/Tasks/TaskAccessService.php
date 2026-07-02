<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskAssigner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TaskAccessService
{
    public function canManageBacklogs(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('ver_tareas_equipo')
            || $user->hasPermission('administrar_asignadores_tareas');
    }

    public function canManageAssigners(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('administrar_asignadores_tareas');
    }

    public function isStaffUser(?User $user): bool
    {
        return $user !== null
            && $user->active
            && ($user->user_type === 'staff' || $user->staff_id !== null);
    }

    public function hasActiveAssignment(User $assigner, int $targetUserId): bool
    {
        return TaskAssigner::query()
            ->where('assigner_user_id', $assigner->id)
            ->where('target_user_id', $targetUserId)
            ->where('active', true)
            ->exists();
    }

    public function canCreateForOwner(User $actor, User $owner): bool
    {
        if (!$this->isStaffUser($owner)) {
            return false;
        }

        if ($this->canManageBacklogs($actor)) {
            return true;
        }

        if ((int) $actor->id === (int) $owner->id) {
            return $actor->hasPermission('gestionar_tareas') || $actor->hasPermission('ver_tareas');
        }

        return $this->hasActiveAssignment($actor, $owner->id);
    }

    public function visibleQuery(User $user): Builder
    {
        if ($this->canManageBacklogs($user)) {
            return Task::query();
        }

        return Task::query()->where(function (Builder $query) use ($user) {
            $query->where('owner_user_id', $user->id);

            $query->orWhere(function (Builder $createdByAssignerQuery) use ($user) {
                $createdByAssignerQuery
                    ->where('created_by_user_id', $user->id)
                    ->whereExists(function ($assignmentQuery) use ($user) {
                        $assignmentQuery
                            ->selectRaw('1')
                            ->from('task_assigners')
                            ->whereColumn('task_assigners.target_user_id', 'tasks.owner_user_id')
                            ->where('task_assigners.assigner_user_id', $user->id)
                            ->where('task_assigners.active', true);
                    });
            });
        });
    }

    public function canView(User $user, Task $task): bool
    {
        return $this->visibleQuery($user)->whereKey($task->id)->exists();
    }

    public function canUpdate(User $user, Task $task): bool
    {
        if ($this->canManageBacklogs($user)) {
            return true;
        }

        if ((int) $task->owner_user_id === (int) $user->id) {
            return $user->hasPermission('gestionar_tareas') || $user->hasPermission('ver_tareas');
        }

        return (int) $task->created_by_user_id === (int) $user->id
            && $this->hasActiveAssignment($user, (int) $task->owner_user_id);
    }

    public function canDelete(User $user, Task $task): bool
    {
        return $this->canUpdate($user, $task);
    }
}
