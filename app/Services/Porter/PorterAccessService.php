<?php

namespace App\Services\Porter;

use App\Models\User;

class PorterAccessService
{
    public function canViewModule(?User $user): bool
    {
        if (!$user || !$user->active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ([
            'ver_porteria',
            'registrar_retiro_porteria',
            'registrar_objetos_porteria',
            'registrar_mercaderia_porteria',
            'registrar_visitas_porteria',
            'registrar_proveedores_porteria',
            'registrar_bitacora_porteria',
            'gestionar_llaves_porteria',
            'ver_historial_porteria',
            'autorizar_retiros_porteria',
            'exportar_reportes_porteria',
        ] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAuthorizeSpecialWithdrawal(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission('autorizar_retiros_porteria'));
    }

    public function canExport(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission('exportar_reportes_porteria'));
    }
}
