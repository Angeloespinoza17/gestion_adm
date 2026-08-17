<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\TaskAssigner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class TaskAccessService
{
    public function canManageAssigners(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('administrar_asignadores_tareas');
    }

    public function canViewReports(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('ver_reportes_tareas');
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

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ((int) $actor->id === (int) $owner->id) {
            return $actor->hasPermission('gestionar_tareas') || $actor->hasPermission('ver_tareas');
        }

        return $this->hasActiveAssignment($actor, $owner->id);
    }

    public function visibleQuery(User $user): Builder
    {
        return $this->constrainVisible(Task::query(), $user);
    }

    public function constrainVisible(Builder|Relation $query, User $user): Builder|Relation
    {
        return $query->where(function (Builder $query) use ($user) {
            $query
                ->where('owner_user_id', $user->id)
                ->orWhereHas('stakeholders', fn (Builder $stakeholders) => $stakeholders->whereKey($user->id));
        });
    }

    public function canView(User $user, Task $task): bool
    {
        return $this->visibleQuery($user)->whereKey($task->id)->exists();
    }

    public function canUpdate(User $user, Task $task): bool
    {
        if ((int) $task->owner_user_id === (int) $user->id) {
            return $user->hasPermission('gestionar_tareas') || $user->hasPermission('ver_tareas');
        }

        return false;
    }

    public function canDelete(User $user, Task $task): bool
    {
        return $this->canUpdate($user, $task);
    }
}
