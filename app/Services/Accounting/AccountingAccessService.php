<?php

namespace App\Services\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AccountingAccessService
{
    public const VIEW_PERMISSION = 'contabilidad.ver';
    public const DASHBOARD_PERMISSION = 'contabilidad.dashboard';
    public const BUDGET_VIEW_PERMISSION = 'contabilidad.presupuesto.ver';
    public const BUDGET_CREATE_PERMISSION = 'contabilidad.presupuesto.crear';
    public const BUDGET_APPROVE_PERMISSION = 'contabilidad.presupuesto.aprobar';
    public const COST_CENTER_PERMISSION = 'contabilidad.centros_costo.gestionar';
    public const MANUAL_PERMISSION = 'contabilidad.manual_cuentas.gestionar';
    public const INCOMES_PERMISSION = 'contabilidad.ingresos.gestionar';
    public const EXPENSES_PERMISSION = 'contabilidad.egresos.gestionar';
    public const PAYMENTS_PERMISSION = 'contabilidad.pagos.gestionar';
    public const CASH_FUND_PERMISSION = 'contabilidad.caja_chica.gestionar';
    public const FUNDS_RENDER_PERMISSION = 'contabilidad.fondos_rendir.gestionar';
    public const RECONCILIATION_PERMISSION = 'contabilidad.conciliacion.gestionar';
    public const FUNDING_PANEL_PERMISSION = 'contabilidad.subvenciones.ver';
    public const CHEQUES_PERMISSION = 'contabilidad.cheques.gestionar';
    public const INVOICES_PERMISSION = 'contabilidad.facturas.gestionar';
    public const HONORARIES_PERMISSION = 'contabilidad.boletas.gestionar';
    public const F29_PERMISSION = 'contabilidad.f29.gestionar';
    public const DECLARATIONS_PERMISSION = 'contabilidad.dj.gestionar';
    public const INCOME_TAX_PERMISSION = 'contabilidad.renta.gestionar';
    public const BALANCE_PERMISSION = 'contabilidad.balance.ver';
    public const EXPORT_PERMISSION = 'contabilidad.reportes.exportar';
    public const ADMIN_PERMISSION = 'contabilidad.admin';

    /**
     * @return array<int, array{slug:string,name:string}>
     */
    public function permissionDefinitions(): array
    {
        return [
            ['slug' => self::VIEW_PERMISSION, 'name' => 'Ver módulo Contabilidad'],
            ['slug' => self::DASHBOARD_PERMISSION, 'name' => 'Ver dashboard Contabilidad'],
            ['slug' => self::BUDGET_VIEW_PERMISSION, 'name' => 'Ver presupuesto Contabilidad'],
            ['slug' => self::BUDGET_CREATE_PERMISSION, 'name' => 'Crear presupuesto Contabilidad'],
            ['slug' => self::BUDGET_APPROVE_PERMISSION, 'name' => 'Aprobar presupuesto Contabilidad'],
            ['slug' => self::COST_CENTER_PERMISSION, 'name' => 'Gestionar centros de costo'],
            ['slug' => self::MANUAL_PERMISSION, 'name' => 'Gestionar manual de cuentas'],
            ['slug' => self::INCOMES_PERMISSION, 'name' => 'Gestionar ingresos'],
            ['slug' => self::EXPENSES_PERMISSION, 'name' => 'Gestionar egresos'],
            ['slug' => self::PAYMENTS_PERMISSION, 'name' => 'Gestionar pagos'],
            ['slug' => self::CASH_FUND_PERMISSION, 'name' => 'Gestionar caja chica'],
            ['slug' => self::FUNDS_RENDER_PERMISSION, 'name' => 'Gestionar fondos por rendir'],
            ['slug' => self::RECONCILIATION_PERMISSION, 'name' => 'Gestionar conciliación bancaria'],
            ['slug' => self::FUNDING_PANEL_PERMISSION, 'name' => 'Ver panel de subvenciones'],
            ['slug' => self::CHEQUES_PERMISSION, 'name' => 'Gestionar cheques'],
            ['slug' => self::INVOICES_PERMISSION, 'name' => 'Gestionar facturas'],
            ['slug' => self::HONORARIES_PERMISSION, 'name' => 'Gestionar boletas de honorarios'],
            ['slug' => self::F29_PERMISSION, 'name' => 'Gestionar F29 interno'],
            ['slug' => self::DECLARATIONS_PERMISSION, 'name' => 'Gestionar declaraciones juradas'],
            ['slug' => self::INCOME_TAX_PERMISSION, 'name' => 'Gestionar declaración de renta'],
            ['slug' => self::BALANCE_PERMISSION, 'name' => 'Ver balances contables'],
            ['slug' => self::EXPORT_PERMISSION, 'name' => 'Exportar reportes contables'],
            ['slug' => self::ADMIN_PERMISSION, 'name' => 'Administrar módulo Contabilidad'],
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
            'accounting_cost_centers',
            'accounting_funding_sources',
            'accounting_manual_versions',
            'accounting_manual_accounts',
            'accounting_budgets',
            'accounting_budget_lines',
            'accounting_bank_accounts',
            'accounting_incomes',
            'accounting_expenses',
            'accounting_cash_funds',
            'accounting_renderings',
            'accounting_payables',
            'accounting_cheques',
            'accounting_tax_periods',
            'accounting_f29_declarations',
            'accounting_journal_entries',
            'accounting_journal_entry_lines',
            'accounting_declaration_types',
            'accounting_declarations',
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

    public function canView(?User $user): bool
    {
        return $this->hasAny($user, [self::VIEW_PERMISSION, self::ADMIN_PERMISSION]);
    }

    public function canViewDashboard(?User $user): bool
    {
        return $this->hasAny($user, [self::DASHBOARD_PERMISSION, self::ADMIN_PERMISSION, self::VIEW_PERMISSION]);
    }

    public function canManage(?User $user, string $permission): bool
    {
        return $this->hasAny($user, [$permission, self::ADMIN_PERMISSION]);
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
