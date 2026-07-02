<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\RelevantCalendar\CalendarEventAccessService;

class CalendarEventPolicy
{
    public function __construct(
        private readonly CalendarEventAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('ver_calendario_fechas_relevantes')
            || $user->hasPermission('ver_todo_calendario_fechas_relevantes')
            || $user->hasPermission('gestionar_calendario_fechas_relevantes_departamento')
            || $user->hasPermission('administrar_calendario_fechas_relevantes');
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->accessService->canView($user, $calendarEvent);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('administrar_calendario_fechas_relevantes')
            || $user->hasPermission('gestionar_calendario_fechas_relevantes_departamento');
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->accessService->canUpdate($user, $calendarEvent);
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->accessService->canDelete($user, $calendarEvent);
    }

    public function export(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('exportar_calendario_fechas_relevantes')
            || $user->hasPermission('administrar_calendario_fechas_relevantes');
    }

    public function manageTypes(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('administrar_tipos_calendario_fechas_relevantes')
            || $user->hasPermission('administrar_calendario_fechas_relevantes');
    }

    public function manageInstitutions(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('administrar_instituciones_calendario_fechas_relevantes')
            || $user->hasPermission('administrar_calendario_fechas_relevantes');
    }
}
