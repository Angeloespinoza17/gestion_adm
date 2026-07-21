<?php

namespace App\Services\Remuneration;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RemunerationAccessService
{
    public const VIEW_PERMISSION = 'remuneraciones.ver';
    public const DASHBOARD_PERMISSION = 'remuneraciones.dashboard';
    public const EMPLOYEES_PERMISSION = 'remuneraciones.trabajadores.gestionar';
    public const CONTRACTS_PERMISSION = 'remuneraciones.contratos.gestionar';
    public const PARAMETERS_PERMISSION = 'remuneraciones.parametros.gestionar';
    public const CONCEPTS_PERMISSION = 'remuneraciones.conceptos.gestionar';
    public const MOVEMENTS_PERMISSION = 'remuneraciones.movimientos.gestionar';
    public const CALCULATE_PERMISSION = 'remuneraciones.liquidaciones.calcular';
    public const APPROVE_PERMISSION = 'remuneraciones.liquidaciones.aprobar';
    public const PAYMENTS_PERMISSION = 'remuneraciones.pagos.gestionar';
    public const ACCOUNTING_PERMISSION = 'remuneraciones.contabilidad.centralizar';
    public const REPORTS_PERMISSION = 'remuneraciones.reportes.ver';
    public const EXPORT_PERMISSION = 'remuneraciones.reportes.exportar';
    public const IMPORT_PERMISSION = 'remuneraciones.importar';
    public const CLOSE_PERIOD_PERMISSION = 'remuneraciones.periodos.cerrar';
    public const HR_MANAGEMENT_PERMISSION = 'remuneraciones.rrhh.gestionar';
    public const ADMIN_PERMISSION = 'remuneraciones.admin';

    /**
     * @return array<int, array{slug:string,name:string}>
     */
    public function permissionDefinitions(): array
    {
        return [
            ['slug' => self::VIEW_PERMISSION, 'name' => 'Ver módulo Remuneraciones'],
            ['slug' => self::DASHBOARD_PERMISSION, 'name' => 'Ver dashboard Remuneraciones'],
            ['slug' => self::EMPLOYEES_PERMISSION, 'name' => 'Gestionar fichas remuneracionales'],
            ['slug' => self::CONTRACTS_PERMISSION, 'name' => 'Gestionar contratos remuneracionales'],
            ['slug' => self::PARAMETERS_PERMISSION, 'name' => 'Gestionar parámetros legales de remuneraciones'],
            ['slug' => self::CONCEPTS_PERMISSION, 'name' => 'Gestionar haberes y descuentos'],
            ['slug' => self::MOVEMENTS_PERMISSION, 'name' => 'Gestionar movimientos de remuneraciones'],
            ['slug' => self::CALCULATE_PERMISSION, 'name' => 'Calcular liquidaciones'],
            ['slug' => self::APPROVE_PERMISSION, 'name' => 'Aprobar liquidaciones'],
            ['slug' => self::PAYMENTS_PERMISSION, 'name' => 'Gestionar pagos de remuneraciones'],
            ['slug' => self::ACCOUNTING_PERMISSION, 'name' => 'Centralizar remuneraciones'],
            ['slug' => self::REPORTS_PERMISSION, 'name' => 'Ver reportes de remuneraciones'],
            ['slug' => self::EXPORT_PERMISSION, 'name' => 'Exportar reportes de remuneraciones'],
            ['slug' => self::IMPORT_PERMISSION, 'name' => 'Importar libro de remuneraciones'],
            ['slug' => self::CLOSE_PERIOD_PERMISSION, 'name' => 'Cerrar y reabrir períodos de remuneraciones'],
            ['slug' => self::HR_MANAGEMENT_PERMISSION, 'name' => 'Gestionar RR.HH. integral'],
            ['slug' => self::ADMIN_PERMISSION, 'name' => 'Administrar módulo Remuneraciones'],
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
            'remuneration_periods',
            'remuneration_legal_parameters',
            'remuneration_employee_profiles',
            'remuneration_contract_settings',
            'remuneration_concepts',
            'remuneration_employee_concepts',
            'remuneration_movements',
            'remuneration_payrolls',
            'remuneration_payroll_lines',
            'remuneration_payroll_distributions',
            'remuneration_book_imports',
            'remuneration_book_import_rows',
            'remuneration_book_concept_settings',
            'remuneration_book_alert_rules',
            'remuneration_payments',
            'remuneration_accounting_exports',
            'remuneration_audit_logs',
            'hr_document_controls',
            'hr_medical_leaves',
            'hr_job_profiles',
            'hr_onboarding_processes',
            'hr_climate_surveys',
            'hr_climate_action_plans',
            'hr_workload_assignments',
            'hr_cv_bank_entries',
            'hr_replacement_pool_entries',
            'hr_labor_certificates',
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
        return $this->hasAny($user, [self::DASHBOARD_PERMISSION, self::VIEW_PERMISSION, self::ADMIN_PERMISSION]);
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
