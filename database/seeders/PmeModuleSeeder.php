<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Services\Pme\PmeAccessService;
use Database\Seeders\Support\ModuleSeeder;

class PmeModuleSeeder extends ModuleSeeder
{
    public function __construct(
        private readonly PmeAccessService $accessService,
    ) {
    }

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedModules();
        $this->attachRolePermissions();
    }

    private function seedPermissions(): void
    {
        foreach ($this->accessService->permissionDefinitions() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => 'Permiso del módulo Gestión PME / SEP.',
                    'active' => true,
                ],
            );
        }
    }

    private function seedModules(): void
    {
        $parent = SystemModule::query()->updateOrCreate(
            ['slug' => 'pme_sep'],
            [
                'name' => 'Gestión PME / SEP',
                'frontend_route' => null,
                'icon' => 'bx-line-chart',
                'sort_order' => 58,
                'active' => true,
                'parent_id' => null,
            ],
        );

        $children = [
            ['slug' => 'pme_sep_dashboard', 'name' => 'Dashboard', 'route' => '/pme-sep', 'sort' => 1],
            ['slug' => 'pme_sep_configuracion', 'name' => 'Configuración PME', 'route' => '/pme-sep/configuracion', 'sort' => 2],
            ['slug' => 'pme_sep_ingresos', 'name' => 'Ingresos SEP', 'route' => '/pme-sep/ingresos', 'sort' => 3],
            ['slug' => 'pme_sep_estudiantes', 'name' => 'Estudiantes SEP', 'route' => '/pme-sep/estudiantes', 'sort' => 4],
            ['slug' => 'pme_sep_dimensiones', 'name' => 'Dimensiones', 'route' => '/pme-sep/dimensiones', 'sort' => 5],
            ['slug' => 'pme_sep_objetivos', 'name' => 'Objetivos', 'route' => '/pme-sep/objetivos', 'sort' => 6],
            ['slug' => 'pme_sep_estrategias', 'name' => 'Estrategias', 'route' => '/pme-sep/estrategias', 'sort' => 7],
            ['slug' => 'pme_sep_indicadores', 'name' => 'Indicadores', 'route' => '/pme-sep/indicadores', 'sort' => 8],
            ['slug' => 'pme_sep_acciones', 'name' => 'Acciones', 'route' => '/pme-sep/acciones', 'sort' => 9],
            ['slug' => 'pme_sep_evidencias', 'name' => 'Evidencias y actividades', 'route' => '/pme-sep/evidencias', 'sort' => 10],
            ['slug' => 'pme_sep_hitos', 'name' => 'Hitos', 'route' => '/pme-sep/hitos', 'sort' => 11],
            ['slug' => 'pme_sep_metas', 'name' => 'Metas estratégicas', 'route' => '/pme-sep/metas', 'sort' => 12],
            ['slug' => 'pme_sep_monitoreo', 'name' => 'Monitoreo reflexivo', 'route' => '/pme-sep/monitoreo', 'sort' => 13],
            ['slug' => 'pme_sep_reportes', 'name' => 'Reportes', 'route' => '/pme-sep/reportes', 'sort' => 14],
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
                ],
            );
        }
    }

    private function attachRolePermissions(): void
    {
        $permissions = Permission::query()
            ->whereIn('slug', collect($this->accessService->permissionDefinitions())->pluck('slug'))
            ->pluck('id', 'slug');

        $modules = SystemModule::query()
            ->whereIn('slug', [
                'pme_sep',
                'pme_sep_dashboard',
                'pme_sep_configuracion',
                'pme_sep_ingresos',
                'pme_sep_estudiantes',
                'pme_sep_dimensiones',
                'pme_sep_objetivos',
                'pme_sep_estrategias',
                'pme_sep_indicadores',
                'pme_sep_acciones',
                'pme_sep_evidencias',
                'pme_sep_hitos',
                'pme_sep_metas',
                'pme_sep_monitoreo',
                'pme_sep_reportes',
            ])
            ->pluck('id', 'slug');

        $full = array_keys($permissions->all());
        $operational = [
            PmeAccessService::VIEW_MODULE_PERMISSION,
            PmeAccessService::VIEW_PRIORITY_STUDENTS_PERMISSION,
            PmeAccessService::VIEW_PREFERENTIAL_STUDENTS_PERMISSION,
            PmeAccessService::CREATE_OBJECTIVES_PERMISSION,
            PmeAccessService::EDIT_OBJECTIVES_PERMISSION,
            PmeAccessService::CREATE_STRATEGIES_PERMISSION,
            PmeAccessService::EDIT_STRATEGIES_PERMISSION,
            PmeAccessService::CREATE_INDICATORS_PERMISSION,
            PmeAccessService::MEASURE_INDICATORS_PERMISSION,
            PmeAccessService::CREATE_ACTIONS_PERMISSION,
            PmeAccessService::EDIT_ACTIONS_PERMISSION,
            PmeAccessService::CREATE_EVIDENCES_PERMISSION,
            PmeAccessService::CREATE_MILESTONES_PERMISSION,
            PmeAccessService::REGISTER_MONITORING_PERMISSION,
            PmeAccessService::VIEW_REPORTS_PERMISSION,
            PmeAccessService::EXPORT_REPORTS_PERMISSION,
        ];
        $limited = [
            PmeAccessService::VIEW_MODULE_PERMISSION,
            PmeAccessService::VIEW_PRIORITY_STUDENTS_PERMISSION,
            PmeAccessService::VIEW_PREFERENTIAL_STUDENTS_PERMISSION,
            PmeAccessService::VIEW_REPORTS_PERMISSION,
        ];

        $rolePermissions = [
            'super_admin' => $full,
            'administrador' => $full,
            'direccion' => $full,
            'coordinador_academico' => $operational,
            'coordinador_pie' => $operational,
            'convivencia_escolar' => $operational,
            'profesional_pie' => [
                PmeAccessService::VIEW_MODULE_PERMISSION,
                PmeAccessService::VIEW_PRIORITY_STUDENTS_PERMISSION,
                PmeAccessService::VIEW_PREFERENTIAL_STUDENTS_PERMISSION,
                PmeAccessService::CREATE_ACTIONS_PERMISSION,
                PmeAccessService::EDIT_ACTIONS_PERMISSION,
                PmeAccessService::CREATE_EVIDENCES_PERMISSION,
                PmeAccessService::REGISTER_MONITORING_PERMISSION,
                PmeAccessService::VIEW_REPORTS_PERMISSION,
            ],
            'rrhh' => [
                PmeAccessService::VIEW_MODULE_PERMISSION,
                PmeAccessService::MANAGE_INCOMES_PERMISSION,
                PmeAccessService::VIEW_PRIORITY_STUDENTS_PERMISSION,
                PmeAccessService::VIEW_PREFERENTIAL_STUDENTS_PERMISSION,
                PmeAccessService::VIEW_REPORTS_PERMISSION,
                PmeAccessService::EXPORT_REPORTS_PERMISSION,
            ],
            'inspectoria' => $limited,
        ];

        $roleModules = [
            'super_admin' => $modules->keys()->all(),
            'administrador' => $modules->keys()->all(),
            'direccion' => $modules->keys()->all(),
            'coordinador_academico' => $modules->keys()->all(),
            'coordinador_pie' => $modules->keys()->all(),
            'convivencia_escolar' => $modules->keys()->all(),
            'profesional_pie' => [
                'pme_sep',
                'pme_sep_dashboard',
                'pme_sep_estudiantes',
                'pme_sep_acciones',
                'pme_sep_evidencias',
                'pme_sep_monitoreo',
                'pme_sep_reportes',
            ],
            'rrhh' => [
                'pme_sep',
                'pme_sep_dashboard',
                'pme_sep_ingresos',
                'pme_sep_estudiantes',
                'pme_sep_reportes',
            ],
            'inspectoria' => [
                'pme_sep',
                'pme_sep_dashboard',
                'pme_sep_estudiantes',
                'pme_sep_reportes',
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
                    ->all(),
            );
        }

        foreach ($roleModules as $roleSlug => $moduleSlugs) {
            $role = Role::query()->firstWhere('slug', $roleSlug);
            if (!$role) {
                continue;
            }

            $role->modules()->syncWithoutDetaching(
                collect($moduleSlugs)
                    ->map(fn (string $slug) => $modules[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            );
        }
    }
}
