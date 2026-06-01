<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCargos();
        $this->seedPermissions();
        $this->seedModules();
        $this->seedRolesAndAssignments();
        $this->seedSuperAdminUser();
    }

    private function seedCargos(): void
    {
        $cargos = [
            ['name' => 'Psicólogo/a', 'slug' => 'psicologo', 'description' => 'Equipo de psicología.'],
            ['name' => 'Coordinador Académico', 'slug' => 'coordinador_academico', 'description' => 'Coordinación académica.'],
            ['name' => 'Enfermero/a', 'slug' => 'enfermeria', 'description' => 'Enfermería escolar.'],
            ['name' => 'Prevencionista de Riesgos', 'slug' => 'prevencion_riesgos', 'description' => 'Prevención de riesgos.'],
            ['name' => 'Inspector/a', 'slug' => 'inspectoria', 'description' => 'Inspectoría.'],
            ['name' => 'Encargado/a de Mantención', 'slug' => 'encargado_mantencion', 'description' => 'Mantención e infraestructura.'],
            ['name' => 'Docente', 'slug' => 'docente', 'description' => 'Docente.'],
            ['name' => 'Administrativo', 'slug' => 'administrativo', 'description' => 'Administrativo.'],
        ];

        foreach ($cargos as $cargo) {
            Cargo::updateOrCreate(
                ['slug' => $cargo['slug']],
                [
                    'name' => $cargo['name'],
                    'description' => $cargo['description'],
                    'active' => true,
                ],
            );
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            // Generales
            ['slug' => 'ver_dashboard', 'name' => 'Ver Dashboard'],
            ['slug' => 'ver_reportes', 'name' => 'Ver Reportes'],
            ['slug' => 'exportar_reportes', 'name' => 'Exportar Reportes'],

            // Estudiantes
            ['slug' => 'ver_estudiantes', 'name' => 'Ver Estudiantes'],
            ['slug' => 'crear_estudiantes', 'name' => 'Crear Estudiantes'],
            ['slug' => 'editar_estudiantes', 'name' => 'Editar Estudiantes'],
            ['slug' => 'ver_ficha_estudiante', 'name' => 'Ver Ficha Estudiante'],

            // Módulos específicos
            ['slug' => 'ver_salud', 'name' => 'Ver Enfermería / Salud'],
            ['slug' => 'ver_psicologia', 'name' => 'Ver Psicología'],
            ['slug' => 'ver_convivencia', 'name' => 'Ver Convivencia Escolar'],
            ['slug' => 'ver_prevencion_riesgos', 'name' => 'Ver Prevención de Riesgos'],

            // Mantención
            ['slug' => 'ver_mantencion', 'name' => 'Ver Mantención'],
            ['slug' => 'crear_ot', 'name' => 'Crear Orden de Trabajo'],
            ['slug' => 'editar_ot', 'name' => 'Editar Orden de Trabajo'],
            ['slug' => 'cerrar_ot', 'name' => 'Cerrar Orden de Trabajo'],
            ['slug' => 'ver_reportes_mantencion', 'name' => 'Ver Reportes Mantención'],
            ['slug' => 'exportar_mantencion', 'name' => 'Exportar Mantención'],
            ['slug' => 'ver_visitas_mantencion', 'name' => 'Ver Visitas Mantención'],
            ['slug' => 'gestionar_visitas_mantencion', 'name' => 'Gestionar Visitas Mantención'],
            ['slug' => 'ver_plan_anual_mantencion', 'name' => 'Ver Plan Anual Mantención'],
            ['slug' => 'gestionar_plan_anual_mantencion', 'name' => 'Gestionar Plan Anual Mantención'],

            // Administración
            ['slug' => 'administrar_usuarios', 'name' => 'Administrar Usuarios'],
            ['slug' => 'administrar_roles', 'name' => 'Administrar Roles'],
            ['slug' => 'administrar_permisos', 'name' => 'Administrar Permisos'],
            ['slug' => 'administrar_modulos', 'name' => 'Administrar Módulos'],
            ['slug' => 'administrar_cargos', 'name' => 'Administrar Cargos'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'] ?? null,
                    'active' => true,
                ],
            );
        }
    }

    private function seedModules(): void
    {
        $modules = [
            ['slug' => 'dashboard', 'name' => 'Dashboard', 'frontend_route' => '/', 'icon' => 'bx-home-circle', 'sort' => 10],
            ['slug' => 'students', 'name' => 'Estudiantes', 'frontend_route' => '/students', 'icon' => 'bx-user', 'sort' => 20],
            ['slug' => 'guardians', 'name' => 'Apoderados', 'frontend_route' => '/guardians', 'icon' => 'bx-group', 'sort' => 30],
            ['slug' => 'staff', 'name' => 'Funcionarios', 'frontend_route' => '/staff', 'icon' => 'bx-id-card', 'sort' => 40],
            ['slug' => 'infirmary', 'name' => 'Enfermería', 'frontend_route' => '/infirmary', 'icon' => 'bx-plus-medical', 'sort' => 50],
            ['slug' => 'psychology', 'name' => 'Psicología', 'frontend_route' => '/psychology', 'icon' => 'bx-brain', 'sort' => 60],
            ['slug' => 'convivencia', 'name' => 'Convivencia Escolar', 'frontend_route' => '/convivencia', 'icon' => 'bx-happy', 'sort' => 70],
            ['slug' => 'risk_prevention', 'name' => 'Prevención de Riesgos', 'frontend_route' => '/risk-prevention', 'icon' => 'bx-shield-quarter', 'sort' => 80],

            // Mantención (padre + submódulos)
            ['slug' => 'maintenance', 'name' => 'Mantención', 'frontend_route' => null, 'icon' => 'bx-wrench', 'sort' => 90],
            ['slug' => 'maintenance_dependencies', 'name' => 'Dependencias', 'frontend_route' => '/maintenance/dependencies', 'icon' => null, 'sort' => 1, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_work_orders', 'name' => 'Órdenes de trabajo', 'frontend_route' => '/maintenance/work-orders', 'icon' => null, 'sort' => 2, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_workload', 'name' => 'Carga de trabajo', 'frontend_route' => '/maintenance/workload', 'icon' => null, 'sort' => 3, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_visits', 'name' => 'Planificación visitas', 'frontend_route' => '/maintenance/visits', 'icon' => null, 'sort' => 4, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_annual_plans', 'name' => 'Plan anual mantención', 'frontend_route' => '/maintenance/annual-plans', 'icon' => null, 'sort' => 5, 'parent' => 'maintenance'],

            ['slug' => 'inventory', 'name' => 'Inventario', 'frontend_route' => '/inventory', 'icon' => 'bx-box', 'sort' => 100],
            ['slug' => 'reports', 'name' => 'Reportes', 'frontend_route' => '/reports', 'icon' => 'bx-bar-chart', 'sort' => 110],

            // Configuración (padre + submódulos)
            ['slug' => 'settings', 'name' => 'Configuración', 'frontend_route' => null, 'icon' => 'bx-cog', 'sort' => 120],
            ['slug' => 'settings_users', 'name' => 'Usuarios', 'frontend_route' => '/admin/users', 'icon' => null, 'sort' => 1, 'parent' => 'settings'],
            ['slug' => 'settings_roles', 'name' => 'Roles', 'frontend_route' => '/admin/roles', 'icon' => null, 'sort' => 2, 'parent' => 'settings'],
            ['slug' => 'settings_permissions', 'name' => 'Permisos', 'frontend_route' => '/admin/permissions', 'icon' => null, 'sort' => 3, 'parent' => 'settings'],
            ['slug' => 'settings_modules', 'name' => 'Módulos', 'frontend_route' => '/admin/modules', 'icon' => null, 'sort' => 4, 'parent' => 'settings'],
            ['slug' => 'settings_cargos', 'name' => 'Cargos', 'frontend_route' => '/admin/cargos', 'icon' => null, 'sort' => 5, 'parent' => 'settings'],
        ];

        $existing = SystemModule::query()->get()->keyBy('slug');

        foreach ($modules as $module) {
            $parentId = null;
            if (!empty($module['parent'])) {
                $parent = SystemModule::query()->firstWhere('slug', $module['parent']);
                $parentId = $parent?->id;
            }

            SystemModule::updateOrCreate(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'frontend_route' => $module['frontend_route'],
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort'],
                    'active' => true,
                    'parent_id' => $parentId,
                ],
            );
        }
    }

    private function seedRolesAndAssignments(): void
    {
        $roles = [
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Acceso total.'],
            ['slug' => 'administrador', 'name' => 'Administrador', 'description' => 'Administración general del sistema.'],
            ['slug' => 'direccion', 'name' => 'Dirección', 'description' => 'Dirección del establecimiento.'],
            ['slug' => 'coordinador_academico', 'name' => 'Coordinador Académico', 'description' => 'Coordinación académica.'],
            ['slug' => 'psicologo', 'name' => 'Psicólogo/a', 'description' => 'Acceso a módulo Psicología.'],
            ['slug' => 'enfermeria', 'name' => 'Enfermería', 'description' => 'Acceso a módulo Enfermería.'],
            ['slug' => 'prevencion_riesgos', 'name' => 'Prevención de Riesgos', 'description' => 'Riesgos + Mantención (críticas/reportes).'],
            ['slug' => 'inspectoria', 'name' => 'Inspectoría', 'description' => 'Inspectoría.'],
            ['slug' => 'encargado_mantencion', 'name' => 'Encargado de Mantención', 'description' => 'Mantención: OT + visitas + exportaciones.'],
            ['slug' => 'docente', 'name' => 'Docente', 'description' => 'Acceso a estudiantes asociados.'],
            ['slug' => 'estudiante', 'name' => 'Estudiante', 'description' => 'Acceso acotado a su información.'],
            ['slug' => 'apoderado', 'name' => 'Apoderado', 'description' => 'Acceso a información de estudiantes asociados.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'active' => true,
                ],
            );
        }

        $rolesBySlug = Role::query()->get()->keyBy('slug');
        $permissionsBySlug = Permission::query()->where('active', true)->get()->keyBy('slug');
        $modulesBySlug = SystemModule::query()->where('active', true)->get()->keyBy('slug');

        $allPermissionIds = $permissionsBySlug->pluck('id')->all();
        $allModuleIds = $modulesBySlug->pluck('id')->all();

        // Super Admin: todo
        $rolesBySlug['super_admin']->permissions()->sync($allPermissionIds);
        $rolesBySlug['super_admin']->modules()->sync($allModuleIds);

        // Administrador: administración + reportes + mantención
        $rolesBySlug['administrador']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_reportes',
            'exportar_reportes',
            'ver_mantencion',
            'crear_ot',
            'editar_ot',
            'cerrar_ot',
            'ver_reportes_mantencion',
            'exportar_mantencion',
            'ver_visitas_mantencion',
            'gestionar_visitas_mantencion',
            'ver_plan_anual_mantencion',
            'gestionar_plan_anual_mantencion',
            'administrar_usuarios',
            'administrar_roles',
            'administrar_permisos',
            'administrar_modulos',
            'administrar_cargos',
        ]));

        $rolesBySlug['administrador']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'maintenance',
            'maintenance_dependencies',
            'maintenance_work_orders',
            'maintenance_workload',
            'maintenance_visits',
            'maintenance_annual_plans',
            'reports',
            'settings',
            'settings_users',
            'settings_roles',
            'settings_permissions',
            'settings_modules',
            'settings_cargos',
        ]));

        // Dirección: reportes + mantención lectura
        $rolesBySlug['direccion']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_reportes',
            'ver_mantencion',
            'ver_reportes_mantencion',
        ]));

        $rolesBySlug['direccion']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'maintenance',
            'maintenance_work_orders',
            'maintenance_workload',
            'maintenance_visits',
            'maintenance_annual_plans',
            'reports',
        ]));

        // Psicología
        $rolesBySlug['psicologo']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_estudiantes',
            'ver_ficha_estudiante',
            'ver_psicologia',
        ]));

        $rolesBySlug['psicologo']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'psychology',
        ]));

        // Enfermería
        $rolesBySlug['enfermeria']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_estudiantes',
            'ver_ficha_estudiante',
            'ver_salud',
        ]));

        $rolesBySlug['enfermeria']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'infirmary',
        ]));

        // Encargado de Mantención
        $rolesBySlug['encargado_mantencion']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_mantencion',
            'crear_ot',
            'editar_ot',
            'cerrar_ot',
            'ver_reportes_mantencion',
            'exportar_mantencion',
            'ver_visitas_mantencion',
            'gestionar_visitas_mantencion',
            'ver_plan_anual_mantencion',
            'gestionar_plan_anual_mantencion',
        ]));

        $rolesBySlug['encargado_mantencion']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'maintenance',
            'maintenance_dependencies',
            'maintenance_work_orders',
            'maintenance_workload',
            'maintenance_visits',
            'maintenance_annual_plans',
        ]));

        // Prevención de Riesgos (lectura + reportes)
        $rolesBySlug['prevencion_riesgos']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_prevencion_riesgos',
            'ver_mantencion',
            'ver_reportes_mantencion',
            'ver_reportes',
        ]));

        $rolesBySlug['prevencion_riesgos']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'risk_prevention',
            'maintenance',
            'maintenance_work_orders',
            'maintenance_workload',
            'reports',
        ]));
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Model> $collection
     * @param array<int, string> $slugs
     * @return array<int, int>
     */
    private function ids($collection, array $slugs): array
    {
        return $collection->only($slugs)->pluck('id')->values()->all();
    }

    private function seedSuperAdminUser(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@cnscgestion.cl');
        $password = env('SUPER_ADMIN_PASSWORD', 'ADMIN');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'active' => true,
            ],
        );

        $superAdminRole = Role::query()->firstWhere('slug', 'super_admin');
        if ($superAdminRole) {
            $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }
}
