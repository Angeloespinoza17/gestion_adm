<?php

namespace App\Services\CentroApuntes;

use App\Models\User;

class CentroApuntesAccessService
{
    /**
     * Los catálogos son usados tanto por la operación diaria como por la vista de reportes.
     */
    public function canAccessCatalogs(User $user): bool
    {
        return $this->canViewModule($user) || $this->canViewReports($user);
    }

    public function canViewModule(User $user): bool
    {
        return $user->hasPermission('ver_modulo_centro_apuntes');
    }

    public function canCreateRequest(User $user): bool
    {
        return $user->hasPermission('crear_solicitud_impresion');
    }

    public function canEditRequest(User $user): bool
    {
        return $user->hasPermission('editar_solicitud_impresion');
    }

    public function canDeleteRequest(User $user): bool
    {
        return $user->hasPermission('eliminar_solicitud_impresion');
    }

    public function canChangeRequestStatus(User $user): bool
    {
        return $user->hasPermission('cambiar_estado_solicitud_impresion');
    }

    public function canRegisterRequestDelivery(User $user): bool
    {
        return $user->hasPermission('registrar_entrega_centro_apuntes');
    }

    public function canManageSubjects(User $user): bool
    {
        return $user->hasPermission('administrar_asignaturas_centro_apuntes');
    }

    public function canManageMachines(User $user): bool
    {
        return $user->hasPermission('administrar_maquinas_centro_apuntes');
    }

    public function canManageInventory(User $user): bool
    {
        return $user->hasPermission('administrar_inventario_panol');
    }

    public function canRegisterStockMovements(User $user): bool
    {
        return $user->hasPermission('registrar_movimientos_panol');
    }

    public function canRequestMaterials(User $user): bool
    {
        return $this->canViewModule($user) || $this->canApproveDeliveries($user);
    }

    public function canApproveDeliveries(User $user): bool
    {
        return $user->hasPermission('aprobar_entregas_panol');
    }

    public function canViewReports(User $user): bool
    {
        return $user->hasPermission('ver_reportes_centro_apuntes');
    }

    public function canExportReports(User $user): bool
    {
        return $user->hasPermission('exportar_reportes_centro_apuntes');
    }
}
