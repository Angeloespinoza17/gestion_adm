<?php

namespace App\Services\Informatica;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class InformaticaAccessService
{
    public const VIEW_PERMISSION = 'informatica.ver';
    public const DASHBOARD_PERMISSION = 'informatica.dashboard';
    public const EQUIPMENT_VIEW_PERMISSION = 'informatica.equipos.ver';
    public const EQUIPMENT_CREATE_PERMISSION = 'informatica.equipos.crear';
    public const EQUIPMENT_EDIT_PERMISSION = 'informatica.equipos.editar';
    public const EQUIPMENT_DELETE_PERMISSION = 'informatica.equipos.eliminar';
    public const LOANS_VIEW_PERMISSION = 'informatica.prestamos.ver';
    public const LOANS_CREATE_PERMISSION = 'informatica.prestamos.crear';
    public const LOANS_RETURN_PERMISSION = 'informatica.prestamos.devolver';
    public const LOANS_CANCEL_PERMISSION = 'informatica.prestamos.cancelar';
    public const MAINTENANCE_VIEW_PERMISSION = 'informatica.mantenciones.ver';
    public const MAINTENANCE_CREATE_PERMISSION = 'informatica.mantenciones.crear';
    public const MAINTENANCE_EDIT_PERMISSION = 'informatica.mantenciones.editar';
    public const MAINTENANCE_CLOSE_PERMISSION = 'informatica.mantenciones.cerrar';
    public const REPORTS_VIEW_PERMISSION = 'informatica.reportes.ver';

    /**
     * @return array<int, array{slug:string,name:string}>
     */
    public function permissionDefinitions(): array
    {
        return [
            ['slug' => self::VIEW_PERMISSION, 'name' => 'Ver módulo Informática'],
            ['slug' => self::DASHBOARD_PERMISSION, 'name' => 'Ver dashboard de Informática'],
            ['slug' => self::EQUIPMENT_VIEW_PERMISSION, 'name' => 'Ver equipos de Informática'],
            ['slug' => self::EQUIPMENT_CREATE_PERMISSION, 'name' => 'Crear equipos de Informática'],
            ['slug' => self::EQUIPMENT_EDIT_PERMISSION, 'name' => 'Editar equipos de Informática'],
            ['slug' => self::EQUIPMENT_DELETE_PERMISSION, 'name' => 'Eliminar equipos de Informática'],
            ['slug' => self::LOANS_VIEW_PERMISSION, 'name' => 'Ver préstamos de Informática'],
            ['slug' => self::LOANS_CREATE_PERMISSION, 'name' => 'Crear préstamos de Informática'],
            ['slug' => self::LOANS_RETURN_PERMISSION, 'name' => 'Registrar devoluciones de Informática'],
            ['slug' => self::LOANS_CANCEL_PERMISSION, 'name' => 'Cancelar préstamos de Informática'],
            ['slug' => self::MAINTENANCE_VIEW_PERMISSION, 'name' => 'Ver mantenciones de Informática'],
            ['slug' => self::MAINTENANCE_CREATE_PERMISSION, 'name' => 'Crear mantenciones de Informática'],
            ['slug' => self::MAINTENANCE_EDIT_PERMISSION, 'name' => 'Editar mantenciones de Informática'],
            ['slug' => self::MAINTENANCE_CLOSE_PERMISSION, 'name' => 'Cerrar mantenciones de Informática'],
            ['slug' => self::REPORTS_VIEW_PERMISSION, 'name' => 'Ver reportes de Informática'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function modulePermissions(): array
    {
        return array_column($this->permissionDefinitions(), 'slug');
    }

    /**
     * @return array<int, string>
     */
    public function requiredTables(): array
    {
        return [
            'it_equipment',
            'it_equipment_loans',
            'it_equipment_maintenance_reports',
            'it_equipment_status_logs',
            'it_equipment_attachments',
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
        return $this->hasAny($user, $this->modulePermissions());
    }

    public function canViewDashboard(?User $user): bool
    {
        return $this->hasAny($user, [self::DASHBOARD_PERMISSION, self::VIEW_PERMISSION]);
    }

    public function canViewEquipment(?User $user): bool
    {
        return $this->hasAny($user, [self::EQUIPMENT_VIEW_PERMISSION, self::VIEW_PERMISSION]);
    }

    public function canCreateEquipment(?User $user): bool
    {
        return $this->hasAny($user, [self::EQUIPMENT_CREATE_PERMISSION]);
    }

    public function canEditEquipment(?User $user): bool
    {
        return $this->hasAny($user, [self::EQUIPMENT_EDIT_PERMISSION]);
    }

    public function canDeleteEquipment(?User $user): bool
    {
        return $this->hasAny($user, [self::EQUIPMENT_DELETE_PERMISSION]);
    }

    public function canViewLoans(?User $user): bool
    {
        return $this->hasAny($user, [self::LOANS_VIEW_PERMISSION, self::VIEW_PERMISSION]);
    }

    public function canCreateLoans(?User $user): bool
    {
        return $this->hasAny($user, [self::LOANS_CREATE_PERMISSION]);
    }

    public function canReturnLoans(?User $user): bool
    {
        return $this->hasAny($user, [self::LOANS_RETURN_PERMISSION]);
    }

    public function canCancelLoans(?User $user): bool
    {
        return $this->hasAny($user, [self::LOANS_CANCEL_PERMISSION]);
    }

    public function canViewMaintenance(?User $user): bool
    {
        return $this->hasAny($user, [self::MAINTENANCE_VIEW_PERMISSION, self::VIEW_PERMISSION]);
    }

    public function canCreateMaintenance(?User $user): bool
    {
        return $this->hasAny($user, [self::MAINTENANCE_CREATE_PERMISSION]);
    }

    public function canEditMaintenance(?User $user): bool
    {
        return $this->hasAny($user, [self::MAINTENANCE_EDIT_PERMISSION]);
    }

    public function canCloseMaintenance(?User $user): bool
    {
        return $this->hasAny($user, [self::MAINTENANCE_CLOSE_PERMISSION]);
    }

    public function canViewReports(?User $user): bool
    {
        return $this->hasAny($user, [self::REPORTS_VIEW_PERMISSION]);
    }

    public function capabilities(?User $user): array
    {
        return [
            'can_view_module' => $this->canViewModule($user),
            'can_view_dashboard' => $this->canViewDashboard($user),
            'can_view_equipment' => $this->canViewEquipment($user),
            'can_create_equipment' => $this->canCreateEquipment($user),
            'can_edit_equipment' => $this->canEditEquipment($user),
            'can_delete_equipment' => $this->canDeleteEquipment($user),
            'can_view_loans' => $this->canViewLoans($user),
            'can_create_loans' => $this->canCreateLoans($user),
            'can_return_loans' => $this->canReturnLoans($user),
            'can_cancel_loans' => $this->canCancelLoans($user),
            'can_view_maintenance' => $this->canViewMaintenance($user),
            'can_create_maintenance' => $this->canCreateMaintenance($user),
            'can_edit_maintenance' => $this->canEditMaintenance($user),
            'can_close_maintenance' => $this->canCloseMaintenance($user),
            'can_view_reports' => $this->canViewReports($user),
        ];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function hasAny(?User $user, array $permissions): bool
    {
        if (!$user || !$user->active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
