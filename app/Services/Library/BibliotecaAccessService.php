<?php

namespace App\Services\Library;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class BibliotecaAccessService
{
    public const VIEW_PERMISSION = 'ver_modulo_biblioteca';
    public const CREATE_BOOKS_PERMISSION = 'crear_libros_biblioteca';
    public const EDIT_BOOKS_PERMISSION = 'editar_libros_biblioteca';
    public const DELETE_BOOKS_PERMISSION = 'eliminar_libros_biblioteca';
    public const MANAGE_CATALOG_PERMISSION = 'administrar_catalogo_biblioteca';
    public const MANAGE_INVENTORY_PERMISSION = 'administrar_inventario_biblioteca';
    public const REGISTER_LOANS_PERMISSION = 'registrar_prestamos_biblioteca';
    public const REGISTER_RETURNS_PERMISSION = 'registrar_devoluciones_biblioteca';
    public const RENEW_LOANS_PERMISSION = 'renovar_prestamos_biblioteca';
    public const MANAGE_OVERDUE_PERMISSION = 'gestionar_mora_biblioteca';
    public const MANAGE_RESERVATIONS_PERMISSION = 'gestionar_reservas_biblioteca';
    public const MANAGE_READING_PLAN_PERMISSION = 'gestionar_plan_lector_biblioteca';
    public const MANAGE_SPACES_PERMISSION = 'gestionar_uso_espacios_biblioteca';
    public const VIEW_STATS_PERMISSION = 'ver_estadisticas_biblioteca';
    public const EXPORT_REPORTS_PERMISSION = 'exportar_reportes_biblioteca';

    /**
     * @return array<int, string>
     */
    public function requiredTables(): array
    {
        return [
            'biblioteca_obras',
            'biblioteca_ejemplares',
            'biblioteca_prestamos',
            'biblioteca_reservas',
            'biblioteca_plan_lector',
            'biblioteca_espacios',
            'biblioteca_uso_espacios',
            'biblioteca_inventario_movimientos',
            'biblioteca_alertas',
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

        foreach ($this->modulePermissions() as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canManageCatalog(?User $user): bool
    {
        return $this->hasAny($user, [
            self::MANAGE_CATALOG_PERMISSION,
            self::CREATE_BOOKS_PERMISSION,
            self::EDIT_BOOKS_PERMISSION,
            self::DELETE_BOOKS_PERMISSION,
        ]);
    }

    public function canCreateBooks(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_CATALOG_PERMISSION, self::CREATE_BOOKS_PERMISSION]);
    }

    public function canEditBooks(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_CATALOG_PERMISSION, self::EDIT_BOOKS_PERMISSION]);
    }

    public function canDeleteBooks(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_CATALOG_PERMISSION, self::DELETE_BOOKS_PERMISSION]);
    }

    public function canManageInventory(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_INVENTORY_PERMISSION]);
    }

    public function canRegisterLoans(?User $user): bool
    {
        return $this->hasAny($user, [self::REGISTER_LOANS_PERMISSION]);
    }

    public function canRegisterReturns(?User $user): bool
    {
        return $this->hasAny($user, [self::REGISTER_RETURNS_PERMISSION]);
    }

    public function canRenewLoans(?User $user): bool
    {
        return $this->hasAny($user, [self::RENEW_LOANS_PERMISSION]);
    }

    public function canManageOverdue(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_OVERDUE_PERMISSION]);
    }

    public function canManageReservations(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_RESERVATIONS_PERMISSION]);
    }

    public function canManageReadingPlan(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_READING_PLAN_PERMISSION]);
    }

    public function canManageSpaces(?User $user): bool
    {
        return $this->hasAny($user, [self::MANAGE_SPACES_PERMISSION]);
    }

    public function canViewStatistics(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW_STATS_PERMISSION, self::EXPORT_REPORTS_PERMISSION]);
    }

    public function canExport(?User $user): bool
    {
        return $this->hasAny($user, [self::EXPORT_REPORTS_PERMISSION]);
    }

    /**
     * @return array<int, string>
     */
    public function modulePermissions(): array
    {
        return [
            self::VIEW_PERMISSION,
            self::CREATE_BOOKS_PERMISSION,
            self::EDIT_BOOKS_PERMISSION,
            self::DELETE_BOOKS_PERMISSION,
            self::MANAGE_CATALOG_PERMISSION,
            self::MANAGE_INVENTORY_PERMISSION,
            self::REGISTER_LOANS_PERMISSION,
            self::REGISTER_RETURNS_PERMISSION,
            self::RENEW_LOANS_PERMISSION,
            self::MANAGE_OVERDUE_PERMISSION,
            self::MANAGE_RESERVATIONS_PERMISSION,
            self::MANAGE_READING_PLAN_PERMISSION,
            self::MANAGE_SPACES_PERMISSION,
            self::VIEW_STATS_PERMISSION,
            self::EXPORT_REPORTS_PERMISSION,
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
