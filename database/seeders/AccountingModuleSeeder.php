<?php

namespace Database\Seeders;

use Database\Seeders\Support\PreventsProductionSeeding;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBankMovement;
use App\Models\Accounting\AccountingBudget;
use App\Models\Accounting\AccountingBudgetLine;
use App\Models\Accounting\AccountingCashFund;
use App\Models\Accounting\AccountingCheque;
use App\Models\Accounting\AccountingCostCenter;
use App\Models\Accounting\AccountingDeclaration;
use App\Models\Accounting\AccountingDeclarationType;
use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingF29Declaration;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Accounting\AccountingManualAccount;
use App\Models\Accounting\AccountingManualVersion;
use App\Models\Accounting\AccountingParty;
use App\Models\Accounting\AccountingPayable;
use App\Models\Accounting\AccountingRendering;
use App\Models\Accounting\AccountingRenderingItem;
use App\Models\Accounting\AccountingTaxCode;
use App\Models\Accounting\AccountingTaxPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Accounting\AccountingAccessService;
use App\Services\Accounting\AccountingJournalService;
use Carbon\Carbon;
use Database\Seeders\Support\ModuleSeeder;

class AccountingModuleSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    private User $actor;

    public function __construct(
        private readonly AccountingAccessService $accessService,
        private readonly AccountingJournalService $journalService,
    ) {
    }

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->call([RbacSeeder::class]);
        $this->actor = $this->creator();

        $this->seedPermissionsAndModules();
        $this->seedRoles();
        $this->seedCoreCatalogs();
        $this->seedBudgets();
        $this->seedTransactions();
        $this->seedCompliance();
        $this->seedManualJournalEntry();
        $this->recalculateBalances();
    }

    private function seedPermissionsAndModules(): void
    {
        foreach ($this->accessService->permissionDefinitions() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo Contabilidad.',
                    'active' => true,
                ]
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'accounting'],
            [
                'name' => 'Contabilidad',
                'frontend_route' => null,
                'icon' => 'bx-wallet-alt',
                'sort_order' => 86,
                'active' => true,
                'parent_id' => null,
            ]
        );

        $children = [
            ['slug' => 'accounting_dashboard', 'name' => 'Dashboard', 'route' => '/contabilidad', 'sort' => 1],
            ['slug' => 'accounting_renderings', 'name' => 'Rendición de cuentas', 'route' => '/contabilidad/rendiciones', 'sort' => 2],
            ['slug' => 'accounting_budgets', 'name' => 'Presupuesto anual', 'route' => '/contabilidad/presupuesto', 'sort' => 3],
            ['slug' => 'accounting_cost_centers', 'name' => 'Centros de costo', 'route' => '/contabilidad/centros-costo', 'sort' => 4],
            ['slug' => 'accounting_manual', 'name' => 'Manual de cuentas', 'route' => '/contabilidad/manual-cuentas', 'sort' => 5],
            ['slug' => 'accounting_incomes', 'name' => 'Ingresos', 'route' => '/contabilidad/ingresos', 'sort' => 6],
            ['slug' => 'accounting_expenses', 'name' => 'Egresos / pagos', 'route' => '/contabilidad/egresos', 'sort' => 7],
            ['slug' => 'accounting_cash_funds', 'name' => 'Caja chica', 'route' => '/contabilidad/caja-chica', 'sort' => 8],
            ['slug' => 'accounting_funds_to_render', 'name' => 'Fondos por rendir', 'route' => '/contabilidad/fondos-rendir', 'sort' => 9],
            ['slug' => 'accounting_reconciliation', 'name' => 'Conciliación bancaria', 'route' => '/contabilidad/conciliacion', 'sort' => 10],
            ['slug' => 'accounting_subsidies', 'name' => 'Subvenciones', 'route' => '/contabilidad/subvenciones', 'sort' => 11],
            ['slug' => 'accounting_cheques', 'name' => 'Cheques', 'route' => '/contabilidad/cheques', 'sort' => 12],
            ['slug' => 'accounting_invoices', 'name' => 'Facturas', 'route' => '/contabilidad/facturas', 'sort' => 13],
            ['slug' => 'accounting_honoraries', 'name' => 'Boletas de honorarios', 'route' => '/contabilidad/boletas-honorarios', 'sort' => 14],
            ['slug' => 'accounting_cashflow', 'name' => 'Flujo de caja', 'route' => '/contabilidad/flujo-caja', 'sort' => 15],
            ['slug' => 'accounting_payables', 'name' => 'Cuentas por pagar', 'route' => '/contabilidad/cuentas-por-pagar', 'sort' => 16],
            ['slug' => 'accounting_f29', 'name' => 'Gestión F29', 'route' => '/contabilidad/f29', 'sort' => 17],
            ['slug' => 'accounting_balance', 'name' => 'Balance 8 y 9 columnas', 'route' => '/contabilidad/balance', 'sort' => 18],
            ['slug' => 'accounting_dj_income', 'name' => 'DJ Ingresos', 'route' => '/contabilidad/dj-ingresos', 'sort' => 19],
            ['slug' => 'accounting_dj_rental', 'name' => 'DJ Arriendo', 'route' => '/contabilidad/dj-arriendo', 'sort' => 20],
            ['slug' => 'accounting_income_tax', 'name' => 'Declaración de Renta', 'route' => '/contabilidad/declaracion-renta', 'sort' => 21],
            ['slug' => 'accounting_reports', 'name' => 'Reportes', 'route' => '/contabilidad/reportes', 'sort' => 22],
        ];

        foreach ($children as $child) {
            SystemModule::query()->updateOrCreate(
                ['slug' => $child['slug']],
                [
                    'name' => $child['name'],
                    'frontend_route' => $child['route'],
                    'icon' => null,
                    'sort_order' => $child['sort'],
                    'active' => true,
                    'parent_id' => $parent->id,
                ]
            );
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            ['slug' => 'contabilidad_admin', 'name' => 'Contabilidad Admin', 'description' => 'Administración integral del módulo contable.'],
            ['slug' => 'contabilidad_analista', 'name' => 'Contabilidad Analista', 'description' => 'Gestión operativa y análisis contable.'],
            ['slug' => 'tesoreria', 'name' => 'Tesorería', 'description' => 'Pagos, conciliación y bancos.'],
            ['slug' => 'solo_lectura_contabilidad', 'name' => 'Solo Lectura Contabilidad', 'description' => 'Acceso de solo lectura a reportes contables.'],
            ['slug' => 'rendicion_revisor', 'name' => 'Rendición Revisor', 'description' => 'Revisión y observación de rendiciones.'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'active' => true,
                ]
            );
        }

        $permissions = Permission::query()
            ->whereIn('slug', $this->accessService->modulePermissions())
            ->pluck('id', 'slug');

        $modules = SystemModule::query()
            ->where('slug', 'accounting')
            ->orWhere('slug', 'like', 'accounting_%')
            ->pluck('id', 'slug');

        $allPermissions = array_keys($permissions->all());
        $baseView = [
            AccountingAccessService::VIEW_PERMISSION,
            AccountingAccessService::DASHBOARD_PERMISSION,
            AccountingAccessService::BUDGET_VIEW_PERMISSION,
            AccountingAccessService::BALANCE_PERMISSION,
        ];

        $rolePermissions = [
            'super_admin' => $allPermissions,
            'administrador' => $allPermissions,
            'direccion' => array_values(array_unique(array_merge($baseView, [
                AccountingAccessService::BUDGET_APPROVE_PERMISSION,
                AccountingAccessService::EXPORT_PERMISSION,
            ]))),
            'rrhh' => array_values(array_unique(array_merge($baseView, [
                AccountingAccessService::INCOMES_PERMISSION,
                AccountingAccessService::EXPENSES_PERMISSION,
                AccountingAccessService::INVOICES_PERMISSION,
                AccountingAccessService::HONORARIES_PERMISSION,
                AccountingAccessService::DECLARATIONS_PERMISSION,
            ]))),
            'contabilidad_admin' => $allPermissions,
            'contabilidad_analista' => array_values(array_unique(array_merge($baseView, [
                AccountingAccessService::BUDGET_CREATE_PERMISSION,
                AccountingAccessService::COST_CENTER_PERMISSION,
                AccountingAccessService::MANUAL_PERMISSION,
                AccountingAccessService::INCOMES_PERMISSION,
                AccountingAccessService::EXPENSES_PERMISSION,
                AccountingAccessService::PAYMENTS_PERMISSION,
                AccountingAccessService::CASH_FUND_PERMISSION,
                AccountingAccessService::FUNDS_RENDER_PERMISSION,
                AccountingAccessService::FUNDING_PANEL_PERMISSION,
                AccountingAccessService::INVOICES_PERMISSION,
                AccountingAccessService::HONORARIES_PERMISSION,
                AccountingAccessService::F29_PERMISSION,
                AccountingAccessService::DECLARATIONS_PERMISSION,
            ]))),
            'tesoreria' => array_values(array_unique(array_merge($baseView, [
                AccountingAccessService::PAYMENTS_PERMISSION,
                AccountingAccessService::CASH_FUND_PERMISSION,
                AccountingAccessService::RECONCILIATION_PERMISSION,
                AccountingAccessService::CHEQUES_PERMISSION,
            ]))),
            'solo_lectura_contabilidad' => $baseView,
            'rendicion_revisor' => array_values(array_unique(array_merge($baseView, [
                AccountingAccessService::FUNDS_RENDER_PERMISSION,
                AccountingAccessService::EXPORT_PERMISSION,
            ]))),
        ];

        $roleModules = [
            'super_admin' => $modules->keys()->all(),
            'administrador' => $modules->keys()->all(),
            'direccion' => ['accounting', 'accounting_dashboard', 'accounting_budgets', 'accounting_balance', 'accounting_reports'],
            'rrhh' => ['accounting', 'accounting_dashboard', 'accounting_incomes', 'accounting_expenses', 'accounting_invoices', 'accounting_honoraries', 'accounting_dj_income', 'accounting_income_tax', 'accounting_reports'],
            'contabilidad_admin' => $modules->keys()->all(),
            'contabilidad_analista' => [
                'accounting',
                'accounting_dashboard',
                'accounting_renderings',
                'accounting_budgets',
                'accounting_cost_centers',
                'accounting_manual',
                'accounting_incomes',
                'accounting_expenses',
                'accounting_subsidies',
                'accounting_invoices',
                'accounting_honoraries',
                'accounting_payables',
                'accounting_reports',
            ],
            'tesoreria' => [
                'accounting',
                'accounting_dashboard',
                'accounting_cash_funds',
                'accounting_funds_to_render',
                'accounting_reconciliation',
                'accounting_cheques',
                'accounting_payables',
                'accounting_reports',
            ],
            'solo_lectura_contabilidad' => [
                'accounting',
                'accounting_dashboard',
                'accounting_balance',
                'accounting_reports',
            ],
            'rendicion_revisor' => [
                'accounting',
                'accounting_dashboard',
                'accounting_renderings',
                'accounting_funds_to_render',
                'accounting_reports',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $role = Role::query()->firstWhere('slug', $roleSlug);
            if (!$role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissions[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );

            $role->modules()->syncWithoutDetaching(
                collect($roleModules[$roleSlug] ?? [])
                    ->map(fn (string $slug) => $modules[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );
        }
    }

    private function seedCoreCatalogs(): void
    {
        $year = (int) now()->year;
        $manualCurrent = AccountingManualVersion::query()->updateOrCreate(
            ['year' => $year, 'version' => '2026.1'],
            [
                'name' => 'Manual Interno de Cuentas Educacionales',
                'publication_date' => Carbon::create($year, 1, 5),
                'valid_from' => Carbon::create($year, 1, 1),
                'valid_until' => Carbon::create($year, 12, 31),
                'is_active' => true,
                'is_current' => true,
                'observations' => 'Versión operativa para control interno y rendición educacional.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        AccountingManualVersion::query()->updateOrCreate(
            ['year' => $year - 1, 'version' => '2025.1'],
            [
                'name' => 'Manual Interno de Cuentas Educacionales',
                'publication_date' => Carbon::create($year - 1, 1, 5),
                'valid_from' => Carbon::create($year - 1, 1, 1),
                'valid_until' => Carbon::create($year - 1, 12, 31),
                'is_active' => false,
                'is_current' => false,
                'observations' => 'Versión histórica.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        $costCenters = [
            ['code' => 'CC-DIR', 'name' => 'Dirección', 'type' => 'administrativo'],
            ['code' => 'CC-ADM', 'name' => 'Administración', 'type' => 'administrativo'],
            ['code' => 'CC-UTP', 'name' => 'UTP', 'type' => 'academico'],
            ['code' => 'CC-SEP', 'name' => 'SEP', 'type' => 'subvencion'],
            ['code' => 'CC-PIE', 'name' => 'PIE', 'type' => 'subvencion'],
            ['code' => 'CC-BIB', 'name' => 'Biblioteca', 'type' => 'operativo'],
            ['code' => 'CC-MAN', 'name' => 'Mantención', 'type' => 'operativo'],
            ['code' => 'CC-INF', 'name' => 'Enfermería', 'type' => 'operativo'],
            ['code' => 'CC-RRHH', 'name' => 'Recursos Humanos', 'type' => 'administrativo'],
            ['code' => 'CC-FIN', 'name' => 'Finanzas', 'type' => 'administrativo'],
        ];

        foreach ($costCenters as $center) {
            AccountingCostCenter::query()->updateOrCreate(
                ['code' => $center['code']],
                array_merge($center, [
                    'responsible_name' => 'Equipo ' . $center['name'],
                    'valid_year' => $year,
                    'is_active' => true,
                    'description' => 'Centro de costo demo del módulo Contabilidad.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ])
            );
        }

        $sources = [
            ['code' => 'FS-GRAL', 'name' => 'Subvención General', 'category' => 'subvencion'],
            ['code' => 'FS-SEP', 'name' => 'SEP', 'category' => 'subvencion'],
            ['code' => 'FS-PIE', 'name' => 'PIE', 'category' => 'subvencion'],
            ['code' => 'FS-MUNI', 'name' => 'Aporte Municipal', 'category' => 'aporte_municipal'],
            ['code' => 'FS-PROP', 'name' => 'Otros Ingresos Propios', 'category' => 'ingreso_propio'],
        ];

        foreach ($sources as $source) {
            AccountingFundingSource::query()->updateOrCreate(
                ['code' => $source['code']],
                array_merge($source, [
                    'is_active' => true,
                    'description' => 'Fuente de financiamiento demo.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ])
            );
        }

        $accounts = [
            ['code' => '1101', 'name' => 'Caja y bancos', 'type' => 'activo', 'category' => 'Caja y bancos', 'level' => 1],
            ['code' => '110101', 'name' => 'Banco principal', 'type' => 'activo', 'category' => 'Caja y bancos', 'level' => 2, 'parent_code' => '1101'],
            ['code' => '2101', 'name' => 'Cuentas por pagar', 'type' => 'pasivo', 'category' => 'Pasivos corrientes', 'level' => 1],
            ['code' => '3101', 'name' => 'Ingresos por subvención', 'type' => 'ingreso', 'category' => 'Ingresos operacionales', 'level' => 1],
            ['code' => '3102', 'name' => 'Ingresos propios', 'type' => 'ingreso', 'category' => 'Ingresos operacionales', 'level' => 1],
            ['code' => '4101', 'name' => 'Gastos de personal', 'type' => 'egreso', 'category' => 'Gastos operacionales', 'level' => 1],
            ['code' => '4102', 'name' => 'Gastos operacionales', 'type' => 'egreso', 'category' => 'Gastos operacionales', 'level' => 1],
            ['code' => '410201', 'name' => 'Servicios básicos', 'type' => 'egreso', 'category' => 'Gastos operacionales', 'level' => 2, 'parent_code' => '4102'],
            ['code' => '410202', 'name' => 'Mantención infraestructura', 'type' => 'egreso', 'category' => 'Gastos operacionales', 'level' => 2, 'parent_code' => '4102'],
            ['code' => '4103', 'name' => 'Honorarios y boletas', 'type' => 'egreso', 'category' => 'Servicios externos', 'level' => 1],
        ];

        $createdAccounts = [];
        foreach ($accounts as $accountData) {
            $parentId = null;
            if (!empty($accountData['parent_code'])) {
                $parentId = $createdAccounts[$accountData['parent_code']] ?? AccountingManualAccount::query()
                    ->where('manual_version_id', $manualCurrent->id)
                    ->where('code', $accountData['parent_code'])
                    ->value('id');
            }

            $account = AccountingManualAccount::query()->updateOrCreate(
                ['manual_version_id' => $manualCurrent->id, 'code' => $accountData['code']],
                [
                    'parent_id' => $parentId,
                    'name' => $accountData['name'],
                    'type' => $accountData['type'],
                    'category' => $accountData['category'],
                    'subcategory' => null,
                    'level' => $accountData['level'],
                    'allows_movements' => true,
                    'requires_evidence' => in_array($accountData['type'], ['egreso'], true),
                    'requires_cost_center' => in_array($accountData['type'], ['ingreso', 'egreso'], true),
                    'requires_funding_source' => in_array($accountData['type'], ['ingreso', 'egreso'], true),
                    'is_active' => true,
                    'description' => 'Cuenta demo para control interno.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]
            );

            $createdAccounts[$accountData['code']] = $account->id;
        }

        $parties = [
            ['party_type' => 'proveedor', 'name' => 'Servicios Educativos Austral', 'business_name' => 'Servicios Educativos Austral SpA', 'rut' => '76000051-5'],
            ['party_type' => 'proveedor', 'name' => 'Mantenciones del Sur', 'business_name' => 'Mantenciones del Sur Ltda.', 'rut' => '76000052-3'],
            ['party_type' => 'beneficiario', 'name' => 'María Soto', 'business_name' => 'María Soto EIRL', 'rut' => '13456789-4'],
            ['party_type' => 'arrendador', 'name' => 'Inmobiliaria Escolar Valdivia', 'business_name' => 'Inmobiliaria Escolar Valdivia SpA', 'rut' => '76999999-2'],
        ];

        foreach ($parties as $party) {
            AccountingParty::query()->updateOrCreate(
                ['rut' => $party['rut']],
                array_merge($party, [
                    'email' => strtolower(str_replace(' ', '.', $party['name'])) . '@demo.cl',
                    'phone' => '+56 9 5555 0000',
                    'address' => 'Valdivia',
                    'is_active' => true,
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ])
            );
        }

        AccountingBankAccount::query()->updateOrCreate(
            ['account_number' => '0001234567'],
            [
                'bank_name' => 'BancoEstado',
                'account_name' => 'Cuenta Corriente Principal',
                'account_type' => 'corriente',
                'current_balance' => 0,
                'is_active' => true,
                'notes' => 'Cuenta demo del módulo.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );
    }

    private function seedBudgets(): void
    {
        $year = (int) now()->year;
        $budget = AccountingBudget::query()->updateOrCreate(
            ['year' => $year, 'name' => 'Presupuesto Contable ' . $year],
            [
                'status' => 'aprobado',
                'approved_at' => Carbon::create($year, 1, 15),
                'approved_by' => $this->actor->id,
                'notes' => 'Presupuesto demo anual aprobado.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        $sep = AccountingFundingSource::query()->where('code', 'FS-SEP')->firstOrFail();
        $general = AccountingFundingSource::query()->where('code', 'FS-GRAL')->firstOrFail();
        $costCenters = AccountingCostCenter::query()->whereIn('code', ['CC-ADM', 'CC-SEP', 'CC-PIE', 'CC-MAN'])->get()->keyBy('code');
        $accounts = AccountingManualAccount::query()->whereIn('code', ['4101', '410201', '410202'])->get()->keyBy('code');

        $lines = [
            ['cost_center_code' => 'CC-ADM', 'funding_source_id' => $general->id, 'manual_account_code' => '4101', 'planned_amount' => 38000000],
            ['cost_center_code' => 'CC-SEP', 'funding_source_id' => $sep->id, 'manual_account_code' => '4101', 'planned_amount' => 24000000],
            ['cost_center_code' => 'CC-PIE', 'funding_source_id' => AccountingFundingSource::query()->where('code', 'FS-PIE')->value('id'), 'manual_account_code' => '4101', 'planned_amount' => 18000000],
            ['cost_center_code' => 'CC-MAN', 'funding_source_id' => $general->id, 'manual_account_code' => '410202', 'planned_amount' => 4500000],
            ['cost_center_code' => 'CC-ADM', 'funding_source_id' => $general->id, 'manual_account_code' => '410201', 'planned_amount' => 5200000],
        ];

        foreach ($lines as $line) {
            AccountingBudgetLine::query()->updateOrCreate(
                [
                    'budget_id' => $budget->id,
                    'cost_center_id' => $costCenters[$line['cost_center_code']]->id,
                    'funding_source_id' => $line['funding_source_id'],
                    'manual_account_id' => $accounts[$line['manual_account_code']]->id,
                    'month' => null,
                ],
                [
                    'planned_amount' => $line['planned_amount'],
                    'executed_amount' => 0,
                    'notes' => 'Línea presupuestaria demo.',
                ]
            );
        }
    }

    private function seedTransactions(): void
    {
        $year = (int) now()->year;
        $bankAccount = AccountingBankAccount::query()->firstOrFail();
        $sources = AccountingFundingSource::query()->get()->keyBy('code');
        $costCenters = AccountingCostCenter::query()->get()->keyBy('code');
        $accounts = AccountingManualAccount::query()->get()->keyBy('code');
        $parties = AccountingParty::query()->get()->keyBy('rut');

        $incomes = [
            ['code' => 'ACC-ING-0001', 'date' => Carbon::create($year, 3, 10), 'type' => 'subvencion_general', 'source' => 'FS-GRAL', 'center' => 'CC-ADM', 'account' => '3101', 'amount' => 14500000],
            ['code' => 'ACC-ING-0002', 'date' => Carbon::create($year, 4, 12), 'type' => 'subvencion_sep', 'source' => 'FS-SEP', 'center' => 'CC-SEP', 'account' => '3101', 'amount' => 7200000],
            ['code' => 'ACC-ING-0003', 'date' => Carbon::create($year, 5, 5), 'type' => 'ingreso_propio', 'source' => 'FS-PROP', 'center' => 'CC-FIN', 'account' => '3102', 'amount' => 1280000],
        ];

        foreach ($incomes as $income) {
            $record = AccountingIncome::query()->updateOrCreate(
                ['code' => $income['code']],
                [
                    'received_at' => $income['date'],
                    'income_type' => $income['type'],
                    'funding_source_id' => $sources[$income['source']]->id,
                    'cost_center_id' => $costCenters[$income['center']]->id,
                    'manual_account_id' => $accounts[$income['account']]->id,
                    'bank_account_id' => $bankAccount->id,
                    'document_reference' => 'COMP-' . $income['code'],
                    'amount' => $income['amount'],
                    'status' => 'confirmado',
                    'notes' => 'Ingreso demo registrado desde el seeder.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]
            );

            $this->journalService->syncForIncome($record->fresh(['manualAccount']));
        }

        $expenses = [
            ['code' => 'ACC-EGR-0001', 'date' => Carbon::create($year, 3, 25), 'rut' => '76000051-5', 'type' => 'factura', 'number' => 'F-1001', 'center' => 'CC-ADM', 'source' => 'FS-GRAL', 'account' => '410201', 'total' => 890000],
            ['code' => 'ACC-EGR-0002', 'date' => Carbon::create($year, 4, 18), 'rut' => '76000052-3', 'type' => 'factura', 'number' => 'F-2034', 'center' => 'CC-MAN', 'source' => 'FS-GRAL', 'account' => '410202', 'total' => 1560000],
            ['code' => 'ACC-EGR-0003', 'date' => Carbon::create($year, 5, 20), 'rut' => '13456789-4', 'type' => 'boleta_honorarios', 'number' => 'BH-088', 'center' => 'CC-SEP', 'source' => 'FS-SEP', 'account' => '4103', 'total' => 1180000],
        ];

        foreach ($expenses as $expense) {
            $net = round($expense['total'] / 1.19, 2);
            $tax = round($expense['total'] - $net, 2);

            $record = AccountingExpense::query()->updateOrCreate(
                ['code' => $expense['code']],
                [
                    'expense_date' => $expense['date'],
                    'party_id' => $parties[$expense['rut']]->id,
                    'document_type' => $expense['type'],
                    'document_number' => $expense['number'],
                    'net_amount' => $expense['type'] === 'factura' ? $net : $expense['total'],
                    'tax_amount' => $expense['type'] === 'factura' ? $tax : 0,
                    'exempt_amount' => 0,
                    'withholding_amount' => $expense['type'] === 'boleta_honorarios' ? round($expense['total'] * 0.1375, 2) : 0,
                    'total_amount' => $expense['total'],
                    'manual_account_id' => $accounts[$expense['account']]->id,
                    'cost_center_id' => $costCenters[$expense['center']]->id,
                    'funding_source_id' => $sources[$expense['source']]->id,
                    'bank_account_id' => $bankAccount->id,
                    'payment_method' => $expense['type'] === 'factura' ? 'transferencia' : 'cheque',
                    'payment_reference' => 'PAY-' . $expense['code'],
                    'status' => $expense['type'] === 'factura' ? 'pagado' : 'aprobado',
                    'notes' => 'Egreso demo registrado desde el seeder.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]
            );

            $this->journalService->syncForExpense($record->fresh(['manualAccount']));
        }

        $cashFund = AccountingCashFund::query()->updateOrCreate(
            ['code' => 'ACC-FND-0001'],
            [
                'fund_type' => 'caja_chica',
                'responsible_user_id' => $this->actor->id,
                'cost_center_id' => $costCenters['CC-ADM']->id,
                'funding_source_id' => $sources['FS-GRAL']->id,
                'initial_amount' => 250000,
                'current_balance' => 54000,
                'delivered_at' => Carbon::create($year, 4, 1),
                'due_at' => Carbon::create($year, 4, 30),
                'status' => 'rendido_parcialmente',
                'notes' => 'Caja chica operativa.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        AccountingCashFund::query()->updateOrCreate(
            ['code' => 'ACC-FND-0002'],
            [
                'fund_type' => 'fondo_por_rendir',
                'responsible_user_id' => $this->actor->id,
                'cost_center_id' => $costCenters['CC-SEP']->id,
                'funding_source_id' => $sources['FS-SEP']->id,
                'initial_amount' => 600000,
                'current_balance' => 185000,
                'delivered_at' => Carbon::create($year, 5, 3),
                'due_at' => Carbon::create($year, 6, 15),
                'status' => 'pendiente_rendicion',
                'notes' => 'Fondo pendiente de respaldo final.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        $rendering = AccountingRendering::query()->updateOrCreate(
            ['code' => 'ACC-RND-0001'],
            [
                'period_label' => 'Abril ' . $year,
                'status' => 'observado',
                'reviewed_at' => Carbon::create($year, 5, 2),
                'reviewed_by' => $this->actor->id,
                'notes' => 'Falta respaldo de un gasto menor.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        $expenseOne = AccountingExpense::query()->where('code', 'ACC-EGR-0001')->firstOrFail();
        AccountingRenderingItem::query()->updateOrCreate(
            ['rendering_id' => $rendering->id, 'expense_id' => $expenseOne->id],
            [
                'income_id' => null,
                'manual_account_id' => $expenseOne->manual_account_id,
                'cost_center_id' => $expenseOne->cost_center_id,
                'funding_source_id' => $expenseOne->funding_source_id,
                'amount' => $expenseOne->total_amount,
                'rendered_at' => Carbon::create($year, 4, 30),
                'notes' => 'Item rendido con observación.',
            ]
        );

        $expenseTwo = AccountingExpense::query()->where('code', 'ACC-EGR-0003')->firstOrFail();
        $payable = AccountingPayable::query()->updateOrCreate(
            ['code' => 'ACC-CXP-0001'],
            [
                'party_id' => $expenseTwo->party_id,
                'expense_id' => $expenseTwo->id,
                'due_date' => Carbon::create($year, 6, 10),
                'amount' => $expenseTwo->total_amount,
                'status' => 'pendiente',
                'priority' => 'alta',
                'cost_center_id' => $expenseTwo->cost_center_id,
                'funding_source_id' => $expenseTwo->funding_source_id,
                'responsible_user_id' => $this->actor->id,
                'notes' => 'Pago pendiente por revisión de respaldo.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        AccountingPayable::query()->updateOrCreate(
            ['code' => 'ACC-CXP-0002'],
            [
                'party_id' => $expenseOne->party_id,
                'expense_id' => $expenseOne->id,
                'due_date' => Carbon::create($year, 5, 5),
                'amount' => $expenseOne->total_amount,
                'status' => 'pagada',
                'priority' => 'media',
                'cost_center_id' => $expenseOne->cost_center_id,
                'funding_source_id' => $expenseOne->funding_source_id,
                'responsible_user_id' => $this->actor->id,
                'notes' => 'Cuenta por pagar ya liquidada.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        AccountingCheque::query()->updateOrCreate(
            ['bank_account_id' => $bankAccount->id, 'check_number' => '000245'],
            [
                'expense_id' => $expenseTwo->id,
                'payable_id' => $payable->id,
                'beneficiary_name' => $expenseTwo->party?->name ?? 'Beneficiario demo',
                'amount' => $expenseTwo->total_amount,
                'issued_at' => Carbon::create($year, 5, 22),
                'cashed_at' => null,
                'status' => 'emitido',
                'notes' => 'Cheque pendiente de cobro.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        $bankMovements = [
            ['description' => 'Ingreso subvención general marzo', 'date' => Carbon::create($year, 3, 10), 'type' => 'income', 'amount' => 14500000, 'status' => 'conciliado', 'reconciled' => true],
            ['description' => 'Pago servicios básicos marzo', 'date' => Carbon::create($year, 3, 25), 'type' => 'expense', 'amount' => -890000, 'status' => 'conciliado', 'reconciled' => true],
            ['description' => 'Ingreso subvención SEP abril', 'date' => Carbon::create($year, 4, 12), 'type' => 'income', 'amount' => 7200000, 'status' => 'conciliado', 'reconciled' => true],
            ['description' => 'Cheque honorarios pendiente', 'date' => Carbon::create($year, 5, 22), 'type' => 'cheque', 'amount' => -1180000, 'status' => 'pendiente', 'reconciled' => false],
        ];

        foreach ($bankMovements as $index => $movement) {
            AccountingBankMovement::query()->updateOrCreate(
                ['bank_account_id' => $bankAccount->id, 'description' => $movement['description'], 'movement_date' => $movement['date']],
                [
                    'movement_type' => $movement['type'],
                    'amount' => $movement['amount'],
                    'status' => $movement['status'],
                    'is_reconciled' => $movement['reconciled'],
                    'notes' => 'Movimiento bancario demo #' . ($index + 1),
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]
            );
        }
    }

    private function seedCompliance(): void
    {
        $year = (int) now()->year;
        $months = [1, 2, 3, 4, 5, 6];

        foreach ($months as $month) {
            $period = AccountingTaxPeriod::query()->updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'starts_at' => Carbon::create($year, $month, 1),
                    'ends_at' => Carbon::create($year, $month, 1)->endOfMonth(),
                    'filed_at' => $month < 5 ? Carbon::create($year, $month, 20) : null,
                    'status' => $month < 5 ? 'presentado' : 'pendiente',
                    'notes' => 'Período tributario demo.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]
            );

            AccountingF29Declaration::query()->updateOrCreate(
                ['tax_period_id' => $period->id],
                [
                    'status' => $month < 5 ? 'pagado' : 'en_preparacion',
                    'vat_debit' => 350000 + ($month * 10000),
                    'vat_credit' => 180000 + ($month * 5000),
                    'ppm_amount' => 45000,
                    'withholding_amount' => 25000,
                    'other_taxes' => [['code' => '91', 'label' => 'Retenciones honorarios', 'amount' => 25000]],
                    'receipt_number' => $month < 5 ? 'F29-' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) : null,
                    'filed_at' => $month < 5 ? Carbon::create($year, $month, 20) : null,
                    'paid_at' => $month < 5 ? Carbon::create($year, $month, 20) : null,
                    'attachment_path' => null,
                    'notes' => 'Declaración F29 demo.',
                    'created_by' => $this->actor->id,
                    'updated_by' => $this->actor->id,
                ]
            );
        }

        foreach ([
            ['year' => $year, 'code' => '91', 'name' => 'Retenciones honorarios'],
            ['year' => $year, 'code' => '538', 'name' => 'PPM'],
            ['year' => $year, 'code' => '562', 'name' => 'IVA débito fiscal'],
            ['year' => $year, 'code' => '563', 'name' => 'IVA crédito fiscal'],
        ] as $code) {
            AccountingTaxCode::query()->updateOrCreate(
                ['year' => $code['year'], 'code' => $code['code']],
                [
                    'name' => $code['name'],
                    'description' => 'Código tributario parametrizable demo.',
                    'is_active' => true,
                ]
            );
        }

        $types = [
            ['code' => 'dj_ingresos', 'name' => 'Declaración Jurada de Ingresos', 'category' => 'ingresos'],
            ['code' => 'dj_arriendo', 'name' => 'Declaración Jurada de Arriendo', 'category' => 'arriendos'],
            ['code' => 'renta_interna', 'name' => 'Declaración de Renta Interna', 'category' => 'renta'],
        ];

        foreach ($types as $type) {
            AccountingDeclarationType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'description' => 'Tipo parametrizable de declaración interna.',
                    'is_active' => true,
                ]
            );
        }

        $incomeType = AccountingDeclarationType::query()->where('code', 'dj_ingresos')->firstOrFail();
        $rentalType = AccountingDeclarationType::query()->where('code', 'dj_arriendo')->firstOrFail();
        $incomeTaxType = AccountingDeclarationType::query()->where('code', 'renta_interna')->firstOrFail();
        $landlord = AccountingParty::query()->where('rut', '76999999-2')->firstOrFail();

        AccountingDeclaration::query()->updateOrCreate(
            ['declaration_type_id' => $incomeType->id, 'year' => $year, 'period_label' => 'AT ' . ($year + 1)],
            [
                'status' => 'revisada',
                'party_id' => null,
                'total_amount' => 22980000,
                'payload' => [
                    'commercial_year' => $year,
                    'records' => 3,
                    'observations' => 'Base de ingresos consolidada desde subvenciones y aportes propios.',
                ],
                'filed_at' => null,
                'notes' => 'Declaración jurada interna de ingresos.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        AccountingDeclaration::query()->updateOrCreate(
            ['declaration_type_id' => $rentalType->id, 'year' => $year, 'period_label' => 'Año comercial ' . $year],
            [
                'status' => 'en_preparacion',
                'party_id' => $landlord->id,
                'total_amount' => 9600000,
                'payload' => [
                    'property' => 'Inmueble administrativo sede Valdivia',
                    'address' => 'Av. Ramón Picarte 1000',
                    'rol_avaluo' => '123-45',
                    'monthly_amount' => 800000,
                    'annual_amount' => 9600000,
                ],
                'notes' => 'Declaración interna de arriendo.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        AccountingDeclaration::query()->updateOrCreate(
            ['declaration_type_id' => $incomeTaxType->id, 'year' => $year, 'period_label' => 'AT ' . ($year + 1)],
            [
                'status' => 'pendiente',
                'party_id' => null,
                'total_amount' => 0,
                'payload' => [
                    'commercial_year' => $year,
                    'accumulated_income' => 22980000,
                    'accepted_expenses' => 3630000,
                    'non_accepted_expenses' => 0,
                    'documents_attached' => false,
                ],
                'notes' => 'Módulo interno de preparación; no reemplaza presentación oficial en SII.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );
    }

    private function seedManualJournalEntry(): void
    {
        $year = (int) now()->year;
        $bankAccount = AccountingManualAccount::query()->where('code', '110101')->firstOrFail();
        $payables = AccountingManualAccount::query()->where('code', '2101')->firstOrFail();

        $entry = AccountingJournalEntry::query()->updateOrCreate(
            ['entry_number' => 'ASC-MAN-000001'],
            [
                'entry_date' => Carbon::create($year, 6, 30),
                'status' => 'registrado',
                'description' => 'Ajuste de cierre parcial de semestre',
                'notes' => 'Asiento manual demo para balance 8 y 9 columnas.',
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]
        );

        $lines = [
            [
                'manual_account_id' => $payables->id,
                'cost_center_id' => null,
                'funding_source_id' => null,
                'line_description' => 'Ajuste devengado por pagar',
                'debit' => 0,
                'credit' => 420000,
            ],
            [
                'manual_account_id' => $bankAccount->id,
                'cost_center_id' => null,
                'funding_source_id' => null,
                'line_description' => 'Regularización bancaria interna',
                'debit' => 420000,
                'credit' => 0,
            ],
        ];

        $this->journalService->assertBalanced($lines);
        $entry->lines()->delete();
        $entry->lines()->createMany($lines);
    }

    private function recalculateBalances(): void
    {
        $bankAccount = AccountingBankAccount::query()->first();
        if ($bankAccount) {
            $incomeTotal = (float) AccountingIncome::query()->where('bank_account_id', $bankAccount->id)->sum('amount');
            $expenseTotal = (float) AccountingExpense::query()->where('bank_account_id', $bankAccount->id)->sum('total_amount');
            $bankAccount->update(['current_balance' => round($incomeTotal - $expenseTotal, 2)]);
        }

        $budget = AccountingBudget::query()->where('status', 'aprobado')->first();
        if ($budget) {
            foreach ($budget->lines as $line) {
                $executed = (float) AccountingExpense::query()
                    ->where('cost_center_id', $line->cost_center_id)
                    ->where('funding_source_id', $line->funding_source_id)
                    ->where('manual_account_id', $line->manual_account_id)
                    ->sum('total_amount');

                $line->update(['executed_amount' => round($executed, 2)]);
            }
        }
    }
}
