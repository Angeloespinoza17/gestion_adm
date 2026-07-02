<?php

namespace App\Policies;

use App\Models\PermissionRequest;
use App\Models\PermissionRequestWatcher;
use App\Models\User;
use App\Services\Permissions\PermissionRequestAccessService;

class PermissionRequestPolicy
{
    public function __construct(
        private readonly PermissionRequestAccessService $accessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ver_permisos_personal')
            || $user->hasPermission('revisar_permisos_equipo')
            || $user->hasPermission('aprobar_permisos_direccion')
            || $user->hasPermission('revisar_permisos_rrhh')
            || PermissionRequestWatcher::query()
                ->where('user_id', $user->id)
                ->where('can_view', true)
                ->exists()
            || $user->isSuperAdmin();
    }

    public function view(User $user, PermissionRequest $permissionRequest): bool
    {
        return $this->accessService->canView($user, $permissionRequest);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('solicitar_permisos_personal')
            || $user->hasPermission('revisar_permisos_rrhh')
            || $user->hasPermission('aprobar_permisos_direccion')
            || $user->isSuperAdmin();
    }

    public function update(User $user, PermissionRequest $permissionRequest): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $permissionRequest->isEditable()
            && (
                ((int) $permissionRequest->staff?->user?->id === (int) $user->id)
                || ((int) $permissionRequest->requested_by_user_id === (int) $user->id)
                || $user->hasPermission('revisar_permisos_rrhh')
            );
    }

    public function submit(User $user, PermissionRequest $permissionRequest): bool
    {
        return $this->update($user, $permissionRequest);
    }

    public function cancel(User $user, PermissionRequest $permissionRequest): bool
    {
        if ($user->isSuperAdmin() || $user->hasPermission('revisar_permisos_rrhh')) {
            return true;
        }

        return in_array($permissionRequest->status, ['borrador', 'ingresado', 'observado', 'pendiente_jefatura', 'pendiente_direccion', 'pendiente_rrhh'], true)
            && (
                ((int) $permissionRequest->staff?->user?->id === (int) $user->id)
                || ((int) $permissionRequest->requested_by_user_id === (int) $user->id)
            );
    }

    public function approve(User $user, PermissionRequest $permissionRequest): bool
    {
        return $this->accessService->canActOnCurrentStep($user, $permissionRequest);
    }

    public function manageTypes(User $user): bool
    {
        return $user->hasPermission('administrar_tipos_permisos_personal') || $user->isSuperAdmin();
    }

    public function manageWatchers(User $user): bool
    {
        return $user->hasPermission('administrar_destinatarios_permisos_personal')
            || $user->hasPermission('administrar_tipos_permisos_personal')
            || $user->isSuperAdmin();
    }

    public function validateDocuments(User $user): bool
    {
        return $user->hasPermission('validar_documentos_permisos_personal')
            || $user->hasPermission('revisar_permisos_rrhh')
            || $user->isSuperAdmin();
    }

    public function manageReplacements(User $user): bool
    {
        return $user->hasPermission('gestionar_reemplazos_permisos_personal')
            || $user->hasPermission('revisar_permisos_rrhh')
            || $user->hasPermission('aprobar_permisos_direccion')
            || $user->isSuperAdmin();
    }
}
