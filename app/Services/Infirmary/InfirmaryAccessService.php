<?php

namespace App\Services\Infirmary;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class InfirmaryAccessService
{
    public const VIEW_PERMISSION = 'ver_enfermeria';
    public const CREATE_ATTENTION_PERMISSION = 'crear_atenciones_enfermeria';
    public const EDIT_ATTENTION_PERMISSION = 'editar_atenciones_enfermeria';
    public const DELETE_ATTENTION_PERMISSION = 'eliminar_atenciones_enfermeria';
    public const EXPORT_PERMISSION = 'exportar_enfermeria';
    public const INVENTORY_PERMISSION = 'administrar_inventario_enfermeria';
    public const MEDICATION_PERMISSION = 'administrar_medicamentos_enfermeria';
    public const ACCIDENT_PERMISSION = 'gestionar_accidentes_enfermeria';
    public const REPORT_PERMISSION = 'ver_reportes_enfermeria';
    public const CATALOG_PERMISSION = 'administrar_catalogos_enfermeria';

    /**
     * @return array<int, string>
     */
    public function requiredTables(): array
    {
        return [
            'infirmary_medications',
            'infirmary_medication_movements',
            'infirmary_medication_authorizations',
            'infirmary_medication_schedules',
            'infirmary_attentions',
            'infirmary_attention_treatments',
            'infirmary_attention_referrals',
            'infirmary_attention_calls',
            'infirmary_attention_follow_ups',
            'infirmary_medication_administrations',
            'infirmary_accidents',
            'infirmary_documents',
        ];
    }

    public function isInstalled(): bool
    {
        foreach ($this->requiredTables() as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    public function canViewModule(?User $user): bool
    {
        if (!$user || !$user->active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ([
            self::VIEW_PERMISSION,
            self::CREATE_ATTENTION_PERMISSION,
            self::EDIT_ATTENTION_PERMISSION,
            self::DELETE_ATTENTION_PERMISSION,
            self::INVENTORY_PERMISSION,
            self::MEDICATION_PERMISSION,
            self::ACCIDENT_PERMISSION,
            self::REPORT_PERMISSION,
            self::EXPORT_PERMISSION,
            self::CATALOG_PERMISSION,
        ] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canCreateAttention(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::CREATE_ATTENTION_PERMISSION));
    }

    public function canEditAttention(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::EDIT_ATTENTION_PERMISSION));
    }

    public function canDeleteAttention(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::DELETE_ATTENTION_PERMISSION));
    }

    public function canManageInventory(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::INVENTORY_PERMISSION));
    }

    public function canManageMedication(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::MEDICATION_PERMISSION));
    }

    public function canManageAccidents(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::ACCIDENT_PERMISSION));
    }

    public function canViewReports(?User $user): bool
    {
        return (bool) $user && (
            $user->isSuperAdmin()
            || $user->hasPermission(self::REPORT_PERMISSION)
            || $user->hasPermission(self::EXPORT_PERMISSION)
        );
    }

    public function canExport(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::EXPORT_PERMISSION));
    }

    public function canManageCatalogs(?User $user): bool
    {
        return (bool) $user && ($user->isSuperAdmin() || $user->hasPermission(self::CATALOG_PERMISSION));
    }
}
