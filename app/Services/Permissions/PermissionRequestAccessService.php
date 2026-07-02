<?php

namespace App\Services\Permissions;

use App\Models\PermissionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PermissionRequestAccessService
{
    public function visibleQuery(User $user): Builder
    {
        if (
            $user->isSuperAdmin()
            || $user->hasPermission('revisar_permisos_rrhh')
            || $user->hasPermission('aprobar_permisos_direccion')
        ) {
            return PermissionRequest::query();
        }

        if (!$user->staff_id && !$user->hasPermission('revisar_permisos_equipo')) {
            return PermissionRequest::query()->whereRaw('1 = 0');
        }

        return PermissionRequest::query()->where(function (Builder $query) use ($user) {
            if ($user->staff_id) {
                $query
                    ->orWhere('staff_id', $user->staff_id)
                    ->orWhere('requested_by_user_id', $user->id);
            }

            if ($user->hasPermission('revisar_permisos_equipo')) {
                $query
                    ->orWhere('direct_manager_user_id', $user->id)
                    ->orWhereHas('staff.organigramRelations', fn (Builder $relationQuery) => $relationQuery
                        ->where('related_staff_id', $user->staff_id)
                        ->where('relationship_type', 'direct_manager')
                        ->where('active', true))
                    ->orWhereHas('departments', fn (Builder $departmentQuery) => $departmentQuery->where('responsible_staff_id', $user->staff_id));
            }

            $query->orWhereHas('watchers', fn (Builder $watcherQuery) => $watcherQuery
                ->where('user_id', $user->id)
                ->where('can_view', true));
        });
    }

    public function reviewableQuery(User $user): Builder
    {
        $query = $this->visibleQuery($user)
            ->whereIn('status', ['pendiente_jefatura', 'pendiente_direccion', 'pendiente_rrhh']);

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            if ($user->hasPermission('revisar_permisos_equipo')) {
                $inner
                    ->orWhere(function (Builder $managerQuery) use ($user) {
                        $managerQuery
                            ->where('current_step', 'manager')
                            ->where(function (Builder $scopeQuery) use ($user) {
                                $scopeQuery
                                    ->where('direct_manager_user_id', $user->id)
                                    ->orWhereHas('staff.organigramRelations', fn (Builder $relationQuery) => $relationQuery
                                        ->where('related_staff_id', $user->staff_id)
                                        ->where('relationship_type', 'direct_manager')
                                        ->where('active', true))
                                    ->orWhereHas('departments', fn (Builder $departmentQuery) => $departmentQuery->where('responsible_staff_id', $user->staff_id));
                            });
                    });
            }

            if ($user->hasPermission('aprobar_permisos_direccion')) {
                $inner->orWhere('current_step', 'direction');
            }

            if ($user->hasPermission('revisar_permisos_rrhh')) {
                $inner->orWhere('current_step', 'hr');
            }
        });
    }

    public function canView(User $user, PermissionRequest $permissionRequest): bool
    {
        return $this->visibleQuery($user)
            ->whereKey($permissionRequest->id)
            ->exists();
    }

    public function isTeamManager(User $user, PermissionRequest $permissionRequest): bool
    {
        if (!$user->hasPermission('revisar_permisos_equipo')) {
            return false;
        }

        if ((int) $permissionRequest->direct_manager_user_id === (int) $user->id) {
            return true;
        }

        if (!$user->staff_id) {
            return false;
        }

        if ($permissionRequest->staff()
            ->whereHas('organigramRelations', fn (Builder $relationQuery) => $relationQuery
                ->where('related_staff_id', $user->staff_id)
                ->where('relationship_type', 'direct_manager')
                ->where('active', true))
            ->exists()) {
            return true;
        }

        return $permissionRequest->departments()
            ->where('responsible_staff_id', $user->staff_id)
            ->exists();
    }

    public function canActOnCurrentStep(User $user, PermissionRequest $permissionRequest): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return match ($permissionRequest->current_step) {
            'manager' => $this->isTeamManager($user, $permissionRequest),
            'direction' => $user->hasPermission('aprobar_permisos_direccion'),
            'hr' => $user->hasPermission('revisar_permisos_rrhh'),
            default => false,
        };
    }
}
