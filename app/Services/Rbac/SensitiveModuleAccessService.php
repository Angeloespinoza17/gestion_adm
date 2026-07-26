<?php

namespace App\Services\Rbac;

use App\Models\SystemModule;
use App\Models\User;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Remuneration\RemunerationAccessService;
use Illuminate\Support\Collection;

class SensitiveModuleAccessService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ACCOUNTING_MODULE_PERMISSIONS = [
        'accounting_dashboard' => [AccountingAccessService::DASHBOARD_PERMISSION],
        'accounting_renderings' => [AccountingAccessService::FUNDS_RENDER_PERMISSION],
        'accounting_budgets' => [
            AccountingAccessService::BUDGET_VIEW_PERMISSION,
            AccountingAccessService::BUDGET_CREATE_PERMISSION,
            AccountingAccessService::BUDGET_APPROVE_PERMISSION,
        ],
        'accounting_cost_centers' => [AccountingAccessService::COST_CENTER_PERMISSION],
        'accounting_manual' => [AccountingAccessService::MANUAL_PERMISSION],
        'accounting_incomes' => [AccountingAccessService::INCOMES_PERMISSION],
        'accounting_expenses' => [AccountingAccessService::EXPENSES_PERMISSION],
        'accounting_cash_funds' => [AccountingAccessService::CASH_FUND_PERMISSION],
        'accounting_funds_to_render' => [AccountingAccessService::FUNDS_RENDER_PERMISSION],
        'accounting_reconciliation' => [AccountingAccessService::RECONCILIATION_PERMISSION],
        'accounting_subsidies' => [AccountingAccessService::FUNDING_PANEL_PERMISSION],
        'accounting_cheques' => [AccountingAccessService::CHEQUES_PERMISSION],
        'accounting_invoices' => [AccountingAccessService::INVOICES_PERMISSION],
        'accounting_honoraries' => [AccountingAccessService::HONORARIES_PERMISSION],
        'accounting_cashflow' => [AccountingAccessService::BALANCE_PERMISSION],
        'accounting_payables' => [AccountingAccessService::PAYMENTS_PERMISSION],
        'accounting_f29' => [AccountingAccessService::F29_PERMISSION],
        'accounting_balance' => [AccountingAccessService::BALANCE_PERMISSION],
        'accounting_dj_income' => [AccountingAccessService::DECLARATIONS_PERMISSION],
        'accounting_dj_rental' => [AccountingAccessService::DECLARATIONS_PERMISSION],
        'accounting_income_tax' => [AccountingAccessService::INCOME_TAX_PERMISSION],
        'accounting_reports' => [
            AccountingAccessService::BALANCE_PERMISSION,
            AccountingAccessService::EXPORT_PERMISSION,
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const REMUNERATION_MODULE_PERMISSIONS = [
        'remuneration_dashboard' => [RemunerationAccessService::DASHBOARD_PERMISSION],
        'remuneration_employees' => [RemunerationAccessService::EMPLOYEES_PERMISSION],
        'remuneration_contracts' => [RemunerationAccessService::CONTRACTS_PERMISSION],
        'remuneration_periods' => [RemunerationAccessService::CLOSE_PERIOD_PERMISSION],
        'remuneration_parameters' => [RemunerationAccessService::PARAMETERS_PERMISSION],
        'remuneration_concepts' => [RemunerationAccessService::CONCEPTS_PERMISSION],
        'remuneration_movements' => [RemunerationAccessService::MOVEMENTS_PERMISSION],
        'remuneration_payrolls' => [
            RemunerationAccessService::CALCULATE_PERMISSION,
            RemunerationAccessService::APPROVE_PERMISSION,
            RemunerationAccessService::EXPORT_PERMISSION,
        ],
        'remuneration_imports' => [RemunerationAccessService::IMPORT_PERMISSION],
        'remuneration_import_rows' => [RemunerationAccessService::IMPORT_PERMISSION],
        'remuneration_book_analytics' => [RemunerationAccessService::REPORTS_PERMISSION],
        'remuneration_payments' => [RemunerationAccessService::PAYMENTS_PERMISSION],
        'remuneration_accounting' => [RemunerationAccessService::ACCOUNTING_PERMISSION],
        'remuneration_reports' => [
            RemunerationAccessService::REPORTS_PERMISSION,
            RemunerationAccessService::EXPORT_PERMISSION,
        ],
        'remuneration_medical_leaves' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_birthdays' => [
            RemunerationAccessService::EMPLOYEES_PERMISSION,
            RemunerationAccessService::HR_MANAGEMENT_PERMISSION,
        ],
        'remuneration_permissions' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_staff_management' => [RemunerationAccessService::EMPLOYEES_PERMISSION],
        'remuneration_departments' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_functions' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_documents' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_onboarding' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_climate' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_climate_plans' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_workload' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_cv_bank' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_replacements' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_job_profiles' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_certificates' => [RemunerationAccessService::HR_MANAGEMENT_PERMISSION],
        'remuneration_audit' => [RemunerationAccessService::ADMIN_PERMISSION],
    ];

    /**
     * @param  Collection<int, SystemModule>  $modules
     * @return Collection<int, SystemModule>
     */
    public function filterModules(User $user, Collection $modules): Collection
    {
        if ($user->isSuperAdmin()) {
            return $modules->values();
        }

        return $modules
            ->filter(fn (SystemModule $module) => $this->canAccessModuleSlug($user, $module->slug))
            ->values();
    }

    public function canAccessModuleSlug(User $user, string $slug): bool
    {
        if (!$user->active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($slug === 'accounting') {
            return $this->hasAccountingGate($user);
        }

        if (str_starts_with($slug, 'accounting_')) {
            return $this->hasAccountingGate($user)
                && $this->hasAny($user, self::ACCOUNTING_MODULE_PERMISSIONS[$slug] ?? []);
        }

        if ($slug === 'remuneration') {
            return $this->hasRemunerationGate($user);
        }

        if (str_starts_with($slug, 'remuneration_')) {
            return $this->hasRemunerationGate($user)
                && $this->hasAny($user, self::REMUNERATION_MODULE_PERMISSIONS[$slug] ?? []);
        }

        return true;
    }

    private function hasAccountingGate(User $user): bool
    {
        return $user->hasPermission(AccountingAccessService::CONFIDENTIAL_ACCESS_PERMISSION)
            && $user->hasPermission(AccountingAccessService::VIEW_PERMISSION);
    }

    private function hasRemunerationGate(User $user): bool
    {
        return $user->hasPermission(RemunerationAccessService::CONFIDENTIAL_ACCESS_PERMISSION)
            && $user->hasPermission(RemunerationAccessService::VIEW_PERMISSION);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function hasAny(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        $permissions[] = str_starts_with($permissions[0], 'contabilidad.')
            ? AccountingAccessService::ADMIN_PERMISSION
            : RemunerationAccessService::ADMIN_PERMISSION;

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
