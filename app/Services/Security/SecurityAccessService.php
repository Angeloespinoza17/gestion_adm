<?php

namespace App\Services\Security;

use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SecurityAccessService
{
    public function canViewModule(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('ver_rondas_seguridad')
            || $user->hasPermission('gestionar_turnos_nochero')
            || $user->hasPermission('registrar_rondas_seguridad')
            || $user->hasPermission('gestionar_novedades_rondas')
            || $user->hasPermission('exportar_rondas_seguridad');
    }

    public function canManageShifts(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('gestionar_turnos_nochero');
    }

    public function canRegisterRounds(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('registrar_rondas_seguridad');
    }

    public function canManageIncidents(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('gestionar_novedades_rondas');
    }

    public function canExport(User $user): bool
    {
        return $this->canManageIncidents($user)
            || $this->canManageShifts($user)
            || $user->hasPermission('exportar_rondas_seguridad')
            || $user->isSuperAdmin();
    }

    public function visibleShiftsQuery(User $user): Builder
    {
        $query = \App\Models\Security\SecurityShift::query();

        if ($this->canManageShifts($user) || $this->canManageIncidents($user) || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('staff_id', $user->staff_id ?: 0);
    }

    public function visibleIncidentsQuery(User $user): Builder
    {
        $query = \App\Models\Security\SecurityIncident::query();

        if ($this->canManageIncidents($user) || $this->canManageShifts($user) || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user) {
            $builder
                ->where('reported_by_user_id', $user->id)
                ->orWhere('current_responsible_user_id', $user->id)
                ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($user) {
                    $assignmentQuery
                        ->where('user_id', $user->id)
                        ->where('is_current', true);
                })
                ->orWhereHas('shift', fn (Builder $shiftQuery) => $shiftQuery->where('staff_id', $user->staff_id ?: 0));
        });
    }

    public function isShiftOwner(User $user, SecurityShift $shift): bool
    {
        return (int) $user->staff_id > 0 && (int) $shift->staff_id === (int) $user->staff_id;
    }

    public function canViewShift(User $user, SecurityShift $shift): bool
    {
        return $this->canManageShifts($user)
            || $this->canManageIncidents($user)
            || $this->isShiftOwner($user, $shift);
    }

    public function canStartShift(User $user, SecurityShift $shift): bool
    {
        if ($this->canManageShifts($user) || $user->isSuperAdmin()) {
            return true;
        }

        return $this->canRegisterRounds($user) && $this->isShiftOwner($user, $shift);
    }

    public function canRegisterRoundOnShift(User $user, SecurityShift $shift): bool
    {
        return $this->canStartShift($user, $shift)
            && in_array($shift->status, [SecurityShift::STATUS_PROGRAMADO, SecurityShift::STATUS_EN_CURSO], true);
    }

    public function canViewIncident(User $user, SecurityIncident $incident): bool
    {
        if ($this->canManageIncidents($user) || $this->canManageShifts($user) || $user->isSuperAdmin()) {
            return true;
        }

        if ((int) $incident->reported_by_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $incident->current_responsible_user_id === (int) $user->id) {
            return true;
        }

        if ($incident->assignments()->where('user_id', $user->id)->where('is_current', true)->exists()) {
            return true;
        }

        return $incident->shift && $this->isShiftOwner($user, $incident->shift);
    }

    public function canUpdateIncident(User $user, SecurityIncident $incident): bool
    {
        if ($this->canManageIncidents($user) || $user->isSuperAdmin()) {
            return true;
        }

        if ((int) $incident->current_responsible_user_id === (int) $user->id) {
            return true;
        }

        return $incident->assignments()
            ->where('user_id', $user->id)
            ->where('is_current', true)
            ->exists();
    }
}
