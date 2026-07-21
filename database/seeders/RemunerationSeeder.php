<?php

namespace Database\Seeders;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingCostCenter;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Accounting\AccountingManualAccount;
use App\Models\Contract;
use App\Models\HumanResources\HrClimateActionPlan;
use App\Models\HumanResources\HrClimateSurvey;
use App\Models\HumanResources\HrCvBankEntry;
use App\Models\HumanResources\HrDocumentControl;
use App\Models\HumanResources\HrJobProfile;
use App\Models\HumanResources\HrLaborCertificate;
use App\Models\HumanResources\HrMedicalLeave;
use App\Models\HumanResources\HrOnboardingProcess;
use App\Models\HumanResources\HrReplacementPoolEntry;
use App\Models\HumanResources\HrWorkloadAssignment;
use App\Models\Permission;
use App\Models\Remuneration\RemunerationConcept;
use App\Models\Remuneration\RemunerationContractSetting;
use App\Models\Remuneration\RemunerationEmployeeConcept;
use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationLegalParameter;
use App\Models\Remuneration\RemunerationMovement;
use App\Models\Remuneration\RemunerationPayment;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SystemModule;
use App\Services\Remuneration\PayrollAccountingService;
use App\Services\Remuneration\PayrollCalculationService;
use App\Services\Remuneration\RemunerationAccessService;
use Carbon\Carbon;
use Database\Seeders\Modules\ContractsModuleSeeder;
use Database\Seeders\Modules\StaffModuleSeeder;
use Database\Seeders\Support\ModuleSeeder;
use Database\Seeders\Support\PreventsProductionSeeding;

class RemunerationSeeder extends ModuleSeeder
{
    use PreventsProductionSeeding;

    public function __construct(
        private readonly RemunerationAccessService $accessService,
        private readonly PayrollCalculationService $calculationService,
        private readonly PayrollAccountingService $accountingService,
    ) {
    }

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->ensureDependencies();
        $this->seedPermissionsAndModules();
        $this->seedRoles();
        $this->call(RemunerationDepartmentsAndFunctionsSeeder::class);
        $this->seedAccountingComplements();
        $this->seedPeriods();
        $this->seedLegalParameters();
        $this->seedConcepts();
        $this->seedEmployeeProfilesAndContracts();
        $this->seedHumanResourcesManagement();
        $this->seedMovements();
        $this->seedDemoPayroll();
    }

    private function ensureDependencies(): void
    {
        $this->call([
            RbacSeeder::class,
            ChileLocationSeeder::class,
            SchoolDepartmentSeeder::class,
            ContractClauseSeeder::class,
            StaffModuleSeeder::class,
            ContractsModuleSeeder::class,
            AccountingModuleSeeder::class,
        ]);
    }

    private function seedPermissionsAndModules(): void
    {
        foreach ($this->accessService->permissionDefinitions() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo Remuneraciones.',
                    'active' => true,
                ]
            );
        }

        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'remuneration'],
            [
                'name' => 'Remuneraciones',
                'frontend_route' => null,
                'icon' => 'bx-money',
                'sort_order' => 84,
                'active' => true,
                'parent_id' => null,
            ]
        );

        $children = [
            ['slug' => 'remuneration_dashboard', 'name' => 'Dashboard', 'route' => '/remuneraciones', 'sort' => 1],
            ['slug' => 'remuneration_employees', 'name' => 'Trabajadores', 'route' => '/remuneraciones/trabajadores', 'sort' => 2],
            ['slug' => 'remuneration_contracts', 'name' => 'Contratos', 'route' => '/remuneraciones/contratos', 'sort' => 3],
            ['slug' => 'remuneration_periods', 'name' => 'Períodos', 'route' => '/remuneraciones/periodos', 'sort' => 4],
            ['slug' => 'remuneration_parameters', 'name' => 'Parámetros', 'route' => '/remuneraciones/parametros', 'sort' => 5],
            ['slug' => 'remuneration_concepts', 'name' => 'Haberes y descuentos', 'route' => '/remuneraciones/conceptos', 'sort' => 6],
            ['slug' => 'remuneration_movements', 'name' => 'Movimientos', 'route' => '/remuneraciones/movimientos', 'sort' => 7],
            ['slug' => 'remuneration_payrolls', 'name' => 'Liquidaciones', 'route' => '/remuneraciones/liquidaciones', 'sort' => 8],
            ['slug' => 'remuneration_imports', 'name' => 'Importaciones', 'route' => '/remuneraciones/importaciones', 'sort' => 9],
            ['slug' => 'remuneration_import_rows', 'name' => 'Libro importado', 'route' => '/remuneraciones/libro-importado', 'sort' => 10],
            ['slug' => 'remuneration_book_analytics', 'name' => 'Datos y estadísticas', 'route' => '/remuneraciones/estadisticas-libro', 'sort' => 11],
            ['slug' => 'remuneration_payments', 'name' => 'Pagos', 'route' => '/remuneraciones/pagos', 'sort' => 12],
            ['slug' => 'remuneration_accounting', 'name' => 'Centralización', 'route' => '/remuneraciones/centralizacion', 'sort' => 13],
            ['slug' => 'remuneration_reports', 'name' => 'Reportes', 'route' => '/remuneraciones/reportes', 'sort' => 14],
            ['slug' => 'remuneration_medical_leaves', 'name' => 'Licencias médicas', 'route' => '/remuneraciones/licencias-medicas', 'sort' => 15],
            ['slug' => 'remuneration_birthdays', 'name' => 'Cumpleaños', 'route' => '/remuneraciones/cumpleanos', 'sort' => 16],
            ['slug' => 'remuneration_permissions', 'name' => 'Permisos', 'route' => '/remuneraciones/permisos', 'sort' => 17],
            ['slug' => 'remuneration_staff_management', 'name' => 'Gestión funcionarios', 'route' => '/remuneraciones/gestion-funcionarios', 'sort' => 18],
            ['slug' => 'remuneration_departments', 'name' => 'Departamentos', 'route' => '/remuneraciones/departamentos', 'sort' => 19],
            ['slug' => 'remuneration_functions', 'name' => 'Funciones', 'route' => '/remuneraciones/funciones', 'sort' => 20],
            ['slug' => 'remuneration_documents', 'name' => 'Control documental', 'route' => '/remuneraciones/control-documental', 'sort' => 21],
            ['slug' => 'remuneration_onboarding', 'name' => 'Inducción', 'route' => '/remuneraciones/induccion', 'sort' => 22],
            ['slug' => 'remuneration_climate', 'name' => 'Clima laboral', 'route' => '/remuneraciones/clima-laboral', 'sort' => 23],
            ['slug' => 'remuneration_climate_plans', 'name' => 'Planes clima', 'route' => '/remuneraciones/planes-clima', 'sort' => 24],
            ['slug' => 'remuneration_workload', 'name' => 'Dotación y carga', 'route' => '/remuneraciones/dotacion-carga', 'sort' => 25],
            ['slug' => 'remuneration_cv_bank', 'name' => 'Banco CV', 'route' => '/remuneraciones/banco-cv', 'sort' => 26],
            ['slug' => 'remuneration_replacements', 'name' => 'Buenos reemplazos', 'route' => '/remuneraciones/reemplazos', 'sort' => 27],
            ['slug' => 'remuneration_job_profiles', 'name' => 'Perfiles de cargo', 'route' => '/remuneraciones/perfiles-cargo', 'sort' => 28],
            ['slug' => 'remuneration_certificates', 'name' => 'Certificados laborales', 'route' => '/remuneraciones/certificados', 'sort' => 29],
            ['slug' => 'remuneration_audit', 'name' => 'Auditoría', 'route' => '/remuneraciones/auditoria', 'sort' => 30],
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
            ['slug' => 'remuneraciones_admin', 'name' => 'Remuneraciones Admin', 'description' => 'Administración integral del módulo de remuneraciones.'],
            ['slug' => 'remuneraciones_analista', 'name' => 'Remuneraciones Analista', 'description' => 'Operación y revisión mensual de remuneraciones.'],
            ['slug' => 'remuneraciones_solo_lectura', 'name' => 'Solo Lectura Remuneraciones', 'description' => 'Acceso de consulta a remuneraciones.'],
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

        $permissionIds = Permission::query()
            ->whereIn('slug', $this->accessService->modulePermissions())
            ->pluck('id', 'slug');

        $moduleIds = SystemModule::query()
            ->where('slug', 'remuneration')
            ->orWhere('slug', 'like', 'remuneration_%')
            ->pluck('id', 'slug');

        $allPermissions = $permissionIds->keys()->all();
        $baseView = [
            RemunerationAccessService::VIEW_PERMISSION,
            RemunerationAccessService::DASHBOARD_PERMISSION,
            RemunerationAccessService::REPORTS_PERMISSION,
        ];

        $rolePermissions = [
            'super_admin' => $allPermissions,
            'administrador' => $allPermissions,
            'rrhh' => $allPermissions,
            'direccion' => array_values(array_unique(array_merge($baseView, [
                RemunerationAccessService::APPROVE_PERMISSION,
                RemunerationAccessService::EXPORT_PERMISSION,
            ]))),
            'remuneraciones_admin' => $allPermissions,
            'remuneraciones_analista' => array_values(array_unique(array_merge($baseView, [
                RemunerationAccessService::EMPLOYEES_PERMISSION,
                RemunerationAccessService::CONTRACTS_PERMISSION,
                RemunerationAccessService::CONCEPTS_PERMISSION,
                RemunerationAccessService::MOVEMENTS_PERMISSION,
                RemunerationAccessService::CALCULATE_PERMISSION,
                RemunerationAccessService::IMPORT_PERMISSION,
                RemunerationAccessService::PAYMENTS_PERMISSION,
                RemunerationAccessService::EXPORT_PERMISSION,
                RemunerationAccessService::HR_MANAGEMENT_PERMISSION,
            ]))),
            'remuneraciones_solo_lectura' => $baseView,
        ];

        $allModules = $moduleIds->keys()->all();
        $roleModules = [
            'super_admin' => $allModules,
            'administrador' => $allModules,
            'rrhh' => $allModules,
            'direccion' => ['remuneration', 'remuneration_dashboard', 'remuneration_payrolls', 'remuneration_book_analytics', 'remuneration_reports'],
            'remuneraciones_admin' => $allModules,
            'remuneraciones_analista' => [
                'remuneration',
                'remuneration_dashboard',
                'remuneration_employees',
                'remuneration_contracts',
                'remuneration_concepts',
                'remuneration_movements',
                'remuneration_payrolls',
                'remuneration_imports',
                'remuneration_import_rows',
                'remuneration_book_analytics',
                'remuneration_payments',
                'remuneration_reports',
                'remuneration_medical_leaves',
                'remuneration_birthdays',
                'remuneration_permissions',
                'remuneration_staff_management',
                'remuneration_departments',
                'remuneration_functions',
                'remuneration_documents',
                'remuneration_onboarding',
                'remuneration_climate',
                'remuneration_climate_plans',
                'remuneration_workload',
                'remuneration_cv_bank',
                'remuneration_replacements',
                'remuneration_job_profiles',
                'remuneration_certificates',
            ],
            'remuneraciones_solo_lectura' => ['remuneration', 'remuneration_dashboard', 'remuneration_book_analytics', 'remuneration_reports'],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if (!$role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching(
                collect($permissionSlugs)
                    ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );
        }

        foreach ($roleModules as $roleSlug => $moduleSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if (!$role) {
                continue;
            }

            $role->modules()->syncWithoutDetaching(
                collect($moduleSlugs)
                    ->map(fn (string $slug) => $moduleIds[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );
        }
    }

    private function seedAccountingComplements(): void
    {
        $actor = $this->creator();

        $sources = [
            ['code' => 'FS-PRORET', 'name' => 'Pro-Retención', 'category' => 'subvencion'],
            ['code' => 'FS-MANT', 'name' => 'Mantenimiento', 'category' => 'subvencion'],
            ['code' => 'FS-INT', 'name' => 'Internado', 'category' => 'subvencion'],
            ['code' => 'FS-OTRAS', 'name' => 'Otras fuentes configurables', 'category' => 'otro'],
        ];

        foreach ($sources as $source) {
            AccountingFundingSource::query()->updateOrCreate(
                ['code' => $source['code']],
                array_merge($source, [
                    'is_active' => true,
                    'description' => 'Fuente disponible para distribución de remuneraciones.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ])
            );
        }

        $centers = [
            ['code' => 'CC-PRORET', 'name' => 'Pro-Retención', 'type' => 'subvencion'],
            ['code' => 'CC-INTERNADO', 'name' => 'Internado', 'type' => 'subvencion'],
        ];

        foreach ($centers as $center) {
            AccountingCostCenter::query()->updateOrCreate(
                ['code' => $center['code']],
                array_merge($center, [
                    'responsible_name' => 'Equipo ' . $center['name'],
                    'valid_year' => 2026,
                    'is_active' => true,
                    'description' => 'Centro de costo complementario para remuneraciones.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ])
            );
        }
    }

    private function seedPeriods(): void
    {
        $actor = $this->creator();
        foreach ([6 => 'Junio', 7 => 'Julio'] as $month => $label) {
            RemunerationPeriod::query()->updateOrCreate(
                ['year' => 2026, 'month' => $month],
                [
                    'name' => "{$label} 2026",
                    'status' => 'abierto',
                    'period_start' => Carbon::create(2026, $month, 1)->toDateString(),
                    'period_end' => Carbon::create(2026, $month, 1)->endOfMonth()->toDateString(),
                    'notes' => 'Período demo de remuneraciones.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]
            );
        }
    }

    private function seedLegalParameters(): void
    {
        $actor = $this->creator();
        $parameters = [
            ['code' => 'standard_month_days', 'name' => 'Días estándar remuneración mensual', 'category' => 'calculo', 'value' => 30, 'unit' => 'factor'],
            ['code' => 'uf_value', 'name' => 'UF demo período', 'category' => 'unidad', 'value' => 38000, 'unit' => 'clp'],
            ['code' => 'utm_value', 'name' => 'UTM demo período', 'category' => 'unidad', 'value' => 68000, 'unit' => 'clp'],
            ['code' => 'afp_rate_default', 'name' => 'Tasa AFP por defecto demo', 'category' => 'prevision', 'value' => 10.00, 'unit' => 'percent'],
            ['code' => 'health_rate_default', 'name' => 'Tasa salud por defecto demo', 'category' => 'salud', 'value' => 7.00, 'unit' => 'percent'],
            ['code' => 'afc_worker_rate', 'name' => 'AFC trabajador demo', 'category' => 'cesantia', 'value' => 0.60, 'unit' => 'percent'],
            ['code' => 'afc_employer_rate', 'name' => 'AFC empleador demo', 'category' => 'cesantia', 'value' => 2.40, 'unit' => 'percent'],
            ['code' => 'sis_rate', 'name' => 'SIS empleador demo', 'category' => 'prevision', 'value' => 1.50, 'unit' => 'percent'],
            ['code' => 'mutual_rate', 'name' => 'Mutualidad demo', 'category' => 'seguridad', 'value' => 0.90, 'unit' => 'percent'],
            ['code' => 'sanna_rate', 'name' => 'SANNA demo', 'category' => 'seguridad', 'value' => 0.03, 'unit' => 'percent'],
            ['code' => 'single_tax_rate', 'name' => 'Impuesto único simplificado demo', 'category' => 'tributario', 'value' => 0, 'unit' => 'percent'],
            ['code' => 'brp_amount', 'name' => 'BRP demo parametrizada', 'category' => 'docente', 'value' => 90000, 'unit' => 'clp'],
            ['code' => 'atdp_amount', 'name' => 'ATDP demo parametrizada', 'category' => 'docente', 'value' => 50000, 'unit' => 'clp'],
            ['code' => 'meal_allowance_amount', 'name' => 'Colación demo parametrizada', 'category' => 'beneficio', 'value' => 40000, 'unit' => 'clp'],
        ];

        foreach ($parameters as $parameter) {
            RemunerationLegalParameter::query()->updateOrCreate(
                ['code' => $parameter['code'], 'effective_from' => '2026-01-01'],
                array_merge($parameter, [
                    'effective_until' => null,
                    'source_reference' => 'Seeder demo. Reemplazar por fuente oficial vigente antes de operar en producción.',
                    'is_active' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ])
            );
        }
    }

    private function seedConcepts(): void
    {
        $actor = $this->creator();
        $concepts = [
            [
                'code' => 'sueldo_base',
                'name' => 'Sueldo base proporcional',
                'type' => 'earning',
                'is_taxable' => true,
                'is_imponible' => true,
                'affects_tax_base' => true,
                'is_system' => true,
                'calculation_type' => 'formula',
                'formula' => 'base_salary * worked_days / standard_month_days',
                'sort_order' => 10,
            ],
            [
                'code' => 'brp_demo',
                'name' => 'BRP parametrizada',
                'type' => 'earning',
                'is_taxable' => true,
                'is_imponible' => true,
                'affects_tax_base' => true,
                'is_system' => true,
                'calculation_type' => 'formula',
                'formula' => 'teacher_career * brp_amount',
                'sort_order' => 20,
            ],
            [
                'code' => 'atdp_demo',
                'name' => 'ATDP parametrizada',
                'type' => 'earning',
                'is_taxable' => true,
                'is_imponible' => true,
                'affects_tax_base' => true,
                'is_system' => true,
                'calculation_type' => 'formula',
                'formula' => 'teacher_career * atdp_amount',
                'sort_order' => 30,
            ],
            [
                'code' => 'colacion_demo',
                'name' => 'Colación parametrizada',
                'type' => 'earning',
                'is_taxable' => false,
                'is_imponible' => false,
                'affects_tax_base' => false,
                'is_system' => true,
                'calculation_type' => 'formula',
                'formula' => 'meal_allowance_amount',
                'sort_order' => 40,
            ],
            [
                'code' => 'bono_responsabilidad',
                'name' => 'Bono de responsabilidad',
                'type' => 'earning',
                'is_taxable' => true,
                'is_imponible' => true,
                'affects_tax_base' => true,
                'is_system' => false,
                'calculation_type' => 'manual',
                'sort_order' => 100,
            ],
            [
                'code' => 'descuento_anticipo',
                'name' => 'Descuento anticipo',
                'type' => 'deduction',
                'is_taxable' => false,
                'is_imponible' => false,
                'affects_tax_base' => false,
                'is_system' => false,
                'calculation_type' => 'manual',
                'sort_order' => 200,
            ],
        ];

        foreach ($concepts as $concept) {
            RemunerationConcept::query()->updateOrCreate(
                ['code' => $concept['code']],
                array_merge([
                    'affects_net' => true,
                    'is_legal' => false,
                    'is_active' => true,
                    'description' => 'Concepto demo de remuneraciones.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ], $concept)
            );
        }
    }

    private function seedEmployeeProfilesAndContracts(): void
    {
        $actor = $this->creator();
        $sources = AccountingFundingSource::query()->get()->keyBy('code');
        $centers = AccountingCostCenter::query()->get()->keyBy('code');
        $debitAccount = AccountingManualAccount::query()->where('code', '4101')->first();
        $creditAccount = AccountingManualAccount::query()->where('code', '2101')->first();

        $contracts = Contract::query()
            ->with('staff')
            ->whereIn('status', ['firmado', 'generado', 'enviado_firma'])
            ->orderBy('id')
            ->limit(4)
            ->get();

        foreach ($contracts as $index => $contract) {
            $staff = $contract->staff;
            if (!$staff) {
                continue;
            }

            $profile = RemunerationEmployeeProfile::query()->updateOrCreate(
                ['staff_id' => $staff->id],
                [
                    'payment_method' => 'transferencia',
                    'bank_name' => 'Banco Demo',
                    'bank_account_type' => 'corriente',
                    'bank_account_number' => '000000' . str_pad((string) $staff->id, 4, '0', STR_PAD_LEFT),
                    'afp_name' => $index % 2 === 0 ? 'AFP Demo Uno' : 'AFP Demo Dos',
                    'afp_rate' => $index % 2 === 0 ? 10.5 : 11.0,
                    'is_pensioned' => false,
                    'health_institution_type' => $index % 2 === 0 ? 'fonasa' : 'isapre',
                    'health_institution_name' => $index % 2 === 0 ? 'Fonasa Demo' : 'Isapre Demo',
                    'health_plan_amount' => $index % 2 === 0 ? null : 2.4,
                    'health_plan_unit' => $index % 2 === 0 ? null : 'uf',
                    'has_afc' => true,
                    'afc_started_at' => $contract->start_date,
                    'family_allowance_tramo' => 'B',
                    'apv_institution' => null,
                    'apv_amount' => null,
                    'apv_unit' => null,
                    'tax_regime' => 'general',
                    'family_dependents' => [],
                    'is_active' => true,
                    'notes' => 'Ficha demo sin datos bancarios reales.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]
            );

            $isTeacher = str_contains(mb_strtolower((string) $contract->position_name), 'profesor');
            $distribution = $isTeacher
                ? [
                    ['funding_source_id' => $sources['FS-GRAL']->id ?? null, 'cost_center_id' => $centers['CC-UTP']->id ?? null, 'percentage' => 50],
                    ['funding_source_id' => $sources['FS-SEP']->id ?? null, 'cost_center_id' => $centers['CC-SEP']->id ?? null, 'percentage' => 30],
                    ['funding_source_id' => $sources['FS-PIE']->id ?? null, 'cost_center_id' => $centers['CC-PIE']->id ?? null, 'percentage' => 20],
                ]
                : [
                    ['funding_source_id' => $sources['FS-GRAL']->id ?? null, 'cost_center_id' => $centers['CC-ADM']->id ?? null, 'percentage' => 100],
                ];

            RemunerationContractSetting::query()->updateOrCreate(
                ['contract_id' => $contract->id],
                [
                    'staff_id' => $staff->id,
                    'employee_profile_id' => $profile->id,
                    'employee_type' => $isTeacher ? 'docente' : 'asistente_educacion',
                    'teacher_career' => $isTeacher,
                    'teacher_level' => $isTeacher ? 'avanzado_demo' : null,
                    'bienios' => $isTeacher ? 4 : null,
                    'priority_percent' => $isTeacher ? 60 : null,
                    'base_salary' => (int) $contract->base_salary,
                    'weekly_hours' => (float) $contract->contract_hours,
                    'basic_hours' => $isTeacher ? 20 : null,
                    'middle_hours' => $isTeacher ? 14 : null,
                    'pie_hours' => $isTeacher ? 6 : null,
                    'sep_hours' => $isTeacher ? 8 : null,
                    'pro_retention_hours' => $isTeacher ? 2 : null,
                    'funding_distribution' => $distribution,
                    'accounting_debit_account_id' => $debitAccount?->id,
                    'accounting_credit_account_id' => $creditAccount?->id,
                    'is_active' => true,
                    'effective_from' => $contract->start_date,
                    'effective_until' => $contract->end_date,
                    'notes' => 'Configuración demo de contrato remuneracional.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]
            );
        }

        $firstProfile = RemunerationEmployeeProfile::query()->first();
        $bonus = RemunerationConcept::query()->where('code', 'bono_responsabilidad')->first();
        if ($firstProfile && $bonus) {
            RemunerationEmployeeConcept::query()->updateOrCreate(
                ['staff_id' => $firstProfile->staff_id, 'concept_id' => $bonus->id],
                [
                    'contract_id' => Contract::query()->where('staff_id', $firstProfile->staff_id)->value('id'),
                    'is_recurring' => true,
                    'amount' => 75000,
                    'starts_at' => '2026-01-01',
                    'ends_at' => null,
                    'is_active' => true,
                    'notes' => 'Bono demo recurrente.',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]
            );
        }
    }

    private function seedHumanResourcesManagement(): void
    {
        $actor = $this->creator();
        $staff = Staff::query()->orderBy('id')->take(3)->get();
        $primaryStaff = $staff->first();

        if (!$primaryStaff) {
            return;
        }

        $jobProfile = HrJobProfile::query()->updateOrCreate(
            ['code' => 'JP-DOC-DEMO'],
            [
                'cargo_id' => $primaryStaff->cargo_id,
                'title' => 'Perfil docente demo',
                'area' => 'Académica',
                'purpose' => 'Guiar procesos de aprendizaje y convivencia escolar según lineamientos institucionales.',
                'responsibilities' => [
                    'Planificación de clases',
                    'Evaluación de aprendizajes',
                    'Coordinación con equipo de ciclo',
                ],
                'requirements' => [
                    'Título profesional pertinente',
                    'Experiencia escolar deseable',
                    'Disponibilidad para trabajo colaborativo',
                ],
                'competencies' => [
                    'Comunicación efectiva',
                    'Gestión de aula',
                    'Trabajo en equipo',
                ],
                'workload_profile' => [
                    'contracted_hours' => 44,
                    'classroom_hours' => 32,
                    'non_classroom_hours' => 12,
                ],
                'version' => '1.0',
                'status' => 'vigente',
                'notes' => 'Perfil demo para estructurar cargos.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $document = HrDocumentControl::query()->updateOrCreate(
            ['staff_id' => $primaryStaff->id, 'document_type' => 'contrato', 'title' => 'Contrato laboral demo'],
            [
                'related_area' => 'rrhh',
                'folio' => 'DOC-DEMO-' . $primaryStaff->id,
                'issued_at' => '2026-06-01',
                'expires_at' => '2027-06-01',
                'alert_days' => 45,
                'status' => 'vigente',
                'owner_area' => 'RR.HH.',
                'metadata' => ['source' => 'seeder_demo'],
                'notes' => 'Control documental demo sin archivo real adjunto.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $period = RemunerationPeriod::query()->where('year', 2026)->where('month', 6)->first();
        HrMedicalLeave::query()->updateOrCreate(
            ['staff_id' => $primaryStaff->id, 'license_number' => 'LM-DEMO-' . $primaryStaff->id],
            [
                'period_id' => $period?->id,
                'document_control_id' => $document->id,
                'issuer' => 'Emisor demo',
                'diagnosis_group' => 'Reposo común demo',
                'starts_at' => '2026-06-10',
                'ends_at' => '2026-06-12',
                'days' => 3,
                'affects_payroll' => true,
                'subsidy_status' => 'por_revisar',
                'status' => 'ingresada',
                'metadata' => ['requires_follow_up' => true],
                'notes' => 'Licencia médica demo para flujo RR.HH.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        HrOnboardingProcess::query()->updateOrCreate(
            ['staff_id' => $primaryStaff->id, 'starts_at' => '2026-06-01'],
            [
                'job_profile_id' => $jobProfile->id,
                'responsible_user_id' => $actor->id,
                'target_completion_at' => '2026-06-15',
                'completed_at' => null,
                'status' => 'en_proceso',
                'documents_checklist' => [
                    ['item' => 'Contrato firmado', 'done' => true],
                    ['item' => 'Certificado de antecedentes', 'done' => false],
                ],
                'trainings_checklist' => [
                    ['item' => 'Inducción institucional', 'done' => true],
                    ['item' => 'Prevención de riesgos', 'done' => false],
                ],
                'accesses_checklist' => [
                    ['item' => 'Correo institucional', 'done' => true],
                    ['item' => 'Acceso plataforma', 'done' => false],
                ],
                'materials_checklist' => [
                    ['item' => 'Credencial', 'done' => false],
                    ['item' => 'Material de bienvenida', 'done' => true],
                ],
                'completion_percent' => 50,
                'notes' => 'Inducción demo con checklist por área.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $survey = HrClimateSurvey::query()->updateOrCreate(
            ['title' => 'Pulso clima laboral demo 2026'],
            [
                'scope' => 'Institucional',
                'starts_at' => '2026-06-01',
                'ends_at' => '2026-06-30',
                'status' => 'reportada',
                'response_count' => 25,
                'satisfaction_score' => 78.5,
                'risk_level' => 'medio',
                'questions' => [
                    ['label' => 'Carga laboral percibida', 'type' => 'scale'],
                    ['label' => 'Comunicación interna', 'type' => 'scale'],
                ],
                'alerts' => [
                    ['level' => 'medio', 'message' => 'Revisar coordinación de reemplazos.'],
                ],
                'report_payload' => [
                    'strengths' => ['Colaboración entre equipos'],
                    'risks' => ['Sobrecarga en cierres mensuales'],
                ],
                'summary' => 'Encuesta demo con alerta y reporte consolidado.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        HrClimateActionPlan::query()->updateOrCreate(
            ['survey_id' => $survey->id, 'title' => 'Plan demo de seguimiento de carga'],
            [
                'owner_user_id' => $actor->id,
                'risk_level' => 'medio',
                'action' => 'Levantar carga horaria por departamento y acordar medidas de ajuste.',
                'due_date' => '2026-07-15',
                'completed_at' => null,
                'status' => 'en_proceso',
                'evidence' => [['type' => 'acta', 'status' => 'pendiente']],
                'notes' => 'Plan demo asociado a clima laboral.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $departmentId = \App\Models\Department::query()->value('id');
        $contract = Contract::query()->where('staff_id', $primaryStaff->id)->first();
        HrWorkloadAssignment::query()->updateOrCreate(
            ['staff_id' => $primaryStaff->id, 'function_name' => 'Carga docente demo', 'period_id' => $period?->id],
            [
                'contract_id' => $contract?->id,
                'department_id' => $departmentId,
                'replacement_staff_id' => $staff->get(1)?->id,
                'role_type' => 'aula',
                'contracted_hours' => 44,
                'classroom_hours' => 32,
                'non_classroom_hours' => 8,
                'coordination_hours' => 2,
                'pie_hours' => 1,
                'sep_hours' => 1,
                'replacement_hours' => 0,
                'starts_at' => '2026-06-01',
                'ends_at' => null,
                'status' => 'vigente',
                'metadata' => ['source' => 'seeder_demo'],
                'notes' => 'Dotación y carga horaria demo.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $cv = HrCvBankEntry::query()->updateOrCreate(
            ['email' => 'postulante.demo@example.test'],
            [
                'full_name' => 'Postulante Demo',
                'rut' => '11.111.111-1',
                'phone' => '+56 9 0000 0000',
                'source' => 'Banco CV demo',
                'desired_position' => 'Docente reemplazo',
                'specialty' => 'Lenguaje',
                'experience_years' => 3,
                'availability' => 'Inmediata',
                'rating' => 4,
                'status' => 'preseleccionado',
                'metadata' => ['demo' => true],
                'notes' => 'Entrada demo sin CV real adjunto.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        HrReplacementPoolEntry::query()->updateOrCreate(
            ['cv_bank_entry_id' => $cv->id],
            [
                'staff_id' => null,
                'full_name' => $cv->full_name,
                'specialty' => $cv->specialty,
                'subject_area' => 'Lenguaje',
                'available_from' => '2026-07-01',
                'available_until' => '2026-12-31',
                'preferred_hours' => 30,
                'rating' => 5,
                'last_replacement_at' => null,
                'status' => 'disponible',
                'metadata' => ['recommended' => true],
                'notes' => 'Buen reemplazo demo.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        HrLaborCertificate::query()->updateOrCreate(
            ['staff_id' => $primaryStaff->id, 'folio' => 'CERT-DEMO-' . $primaryStaff->id],
            [
                'certificate_type' => 'antiguedad',
                'purpose' => 'Trámite personal demo',
                'requested_at' => '2026-06-20',
                'issued_at' => '2026-06-21',
                'signed_by_user_id' => $actor->id,
                'status' => 'emitido',
                'payload' => [
                    'include_salary' => false,
                    'include_hours' => true,
                    'historical_source' => 'staff_snapshot',
                ],
                'notes' => 'Certificado laboral demo exportable a PDF desde la vista.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }

    private function seedMovements(): void
    {
        $actor = $this->creator();
        $period = RemunerationPeriod::query()->where('year', 2026)->where('month', 6)->first();
        $profile = RemunerationEmployeeProfile::query()->first();
        $deduction = RemunerationConcept::query()->where('code', 'descuento_anticipo')->first();
        if (!$period || !$profile || !$deduction) {
            return;
        }

        RemunerationMovement::query()->updateOrCreate(
            [
                'period_id' => $period->id,
                'staff_id' => $profile->staff_id,
                'description' => 'Descuento demo por anticipo interno',
            ],
            [
                'contract_id' => Contract::query()->where('staff_id', $profile->staff_id)->value('id'),
                'concept_id' => $deduction->id,
                'movement_type' => 'deduction',
                'source_type' => 'manual',
                'amount' => 25000,
                'status' => 'aprobado',
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }

    private function seedDemoPayroll(): void
    {
        $period = RemunerationPeriod::query()->where('year', 2026)->where('month', 6)->first();
        $profile = RemunerationEmployeeProfile::query()->first();
        if (!$period || !$profile) {
            return;
        }

        $actor = $this->creator();
        $staff = Staff::query()->find($profile->staff_id);
        if (!$staff) {
            return;
        }

        $payroll = \App\Models\Remuneration\RemunerationPayroll::query()
            ->where('period_id', $period->id)
            ->where('staff_id', $staff->id)
            ->where('payroll_type', 'mensual')
            ->first();

        if (!$payroll || !$payroll->isLocked()) {
            $payroll = $this->calculationService->calculate($period, $staff, $actor, ['force' => true]);
            $payroll->fill([
                'status' => 'aprobada',
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();
        }

        RemunerationPayment::query()->updateOrCreate(
            ['payroll_id' => $payroll->id, 'reference' => 'PAGO-DEMO-' . $payroll->code],
            [
                'payment_date' => '2026-06-30',
                'amount' => $payroll->net_amount,
                'payment_method' => 'transferencia',
                'bank_account_id' => AccountingBankAccount::query()->value('id'),
                'status' => 'pagado',
                'paid_at' => now(),
                'paid_by' => $actor->id,
                'notes' => 'Pago demo generado por seeder.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );

        $payroll->fill([
            'status' => 'pagada',
            'paid_at' => now(),
            'paid_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        $this->accountingService->centralize($payroll->fresh(['period', 'distributions']), $actor);
    }
}
