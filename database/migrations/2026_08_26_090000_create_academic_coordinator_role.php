<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE = 'coordinadora_academica';

    private const BASE_CONFIGURATION_PERMISSION = 'ver_configuracion_base_estudiantes';

    private const INSPECTORIA_DAILY_LOG_VIEW_PERMISSION = 'ver_bitacora_inspectoria';

    /** @var array<string, string> */
    private const PROFILE_PERMISSION_NAMES = [
        'ver_dashboard' => 'Ver Dashboard',
        'ver_estudiantes' => 'Ver Estudiantes',
        'crear_estudiantes' => 'Crear Estudiantes',
        'editar_estudiantes' => 'Editar Estudiantes',
        'eliminar_estudiantes' => 'Eliminar Estudiantes',
        'ver_ficha_estudiante' => 'Ver Ficha Estudiante',
        'gestionar_matriculas_estudiantes' => 'Gestionar Matrículas Estudiantiles',
        'promover_estudiantes' => 'Promover Estudiantes',
        'grade_statistics.view' => 'Ver estadísticas de calificaciones',
        'grade_statistics.view_students' => 'Ver consolidado nominal de calificaciones',
        'ver_asistencia' => 'Ver asistencia',
        'importar_asistencia' => 'Importar asistencia',
        'importar_calificaciones' => 'Importar calificaciones',
        'editar_asistencia' => 'Editar asistencia',
        'gestionar_alertas_asistencia' => 'Gestionar alertas de asistencia',
        'proyectar_ingresos_asistencia' => 'Proyectar ingresos por asistencia',
        'attendance_statistics.view' => 'Ver estadísticas avanzadas de asistencia',
        'attendance_statistics.view_global' => 'Ver asistencia institucional',
        'attendance_statistics.view_course' => 'Ver asistencia por curso',
        'attendance_statistics.view_student' => 'Ver asistencia individual',
        'attendance_statistics.view_financial' => 'Ver impacto financiero de asistencia',
        'attendance_statistics.view_sensitive_segments' => 'Ver segmentos sensibles de asistencia',
        'attendance_statistics.export' => 'Exportar estadísticas de asistencia',
        'attendance_statistics.configure' => 'Configurar estadísticas de asistencia',
        'attendance_statistics.manage_goals' => 'Gestionar metas de asistencia',
        'attendance_statistics.manage_alerts' => 'Gestionar alertas avanzadas de asistencia',
        'attendance_statistics.manage_interventions' => 'Gestionar intervenciones de asistencia',
        'attendance_statistics.manage_reports' => 'Gestionar reportes de asistencia',
        'attendance_statistics.view_audit' => 'Ver auditoría de asistencia',
        'attendance_management.view' => 'Ver gestión de ausencia',
        'attendance_management.view_all' => 'Ver gestión institucional de ausencia',
        'attendance_management.manage_cases' => 'Gestionar expedientes de asistencia',
        'attendance_management.manage_interventions' => 'Gestionar intervenciones de ausencia',
        'attendance_management.manage_causes' => 'Gestionar causas de ausencia',
        'attendance_management.manage_action_plans' => 'Gestionar planes de acción de asistencia',
        'attendance_management.export' => 'Exportar gestión de ausencia',
        'attendance_management.view_sensitive' => 'Ver información sensible de ausencia',
        'attendance_management.configure' => 'Configurar gestión de ausencia',
        'ver_traslados_operativos' => 'Ver traslados operativos',
        'solicitar_traslados_operativos' => 'Solicitar traslados operativos',
        'visar_traslados_operativos' => 'Visar traslados operativos',
        'gestionar_traslados_operativos' => 'Gestionar traslados operativos',
        'exportar_traslados_operativos' => 'Exportar traslados operativos',
        'rrhh.ausencias.ver' => 'Ver gestión de ausencias y saldos',
        'rrhh.seleccion.ver' => 'Ver selección y banco de talento',
    ];

    /** @var array<int, string> */
    private const STUDENT_PERMISSIONS = [
        'ver_dashboard',
        'ver_estudiantes',
        'crear_estudiantes',
        'editar_estudiantes',
        'eliminar_estudiantes',
        'ver_ficha_estudiante',
        'gestionar_matriculas_estudiantes',
        'promover_estudiantes',
        'grade_statistics.view',
        'grade_statistics.view_students',
        'ver_asistencia',
        'importar_asistencia',
        'importar_calificaciones',
        'editar_asistencia',
        'gestionar_alertas_asistencia',
        'proyectar_ingresos_asistencia',
        'attendance_statistics.view',
        'attendance_statistics.view_global',
        'attendance_statistics.view_course',
        'attendance_statistics.view_student',
        'attendance_statistics.view_financial',
        'attendance_statistics.view_sensitive_segments',
        'attendance_statistics.export',
        'attendance_statistics.configure',
        'attendance_statistics.manage_goals',
        'attendance_statistics.manage_alerts',
        'attendance_statistics.manage_interventions',
        'attendance_statistics.manage_reports',
        'attendance_statistics.view_audit',
        'attendance_management.view',
        'attendance_management.view_all',
        'attendance_management.manage_cases',
        'attendance_management.manage_interventions',
        'attendance_management.manage_causes',
        'attendance_management.manage_action_plans',
        'attendance_management.export',
        'attendance_management.view_sensitive',
        'attendance_management.configure',
    ];

    /** @var array<int, string> */
    private const OPERATIONAL_PERMISSIONS = [
        'ver_traslados_operativos',
        'solicitar_traslados_operativos',
        'visar_traslados_operativos',
        'gestionar_traslados_operativos',
        'exportar_traslados_operativos',
        'rrhh.ausencias.ver',
        'rrhh.seleccion.ver',
    ];

    /** @var array<int, string> */
    private const MODULES = [
        'dashboard',
        'students',
        'students_directory',
        'students_promotions',
        'students_movements',
        'students_reports',
        'students_attendance_statistics',
        'students_attendance_management',
        'students_grade_statistics',
        'operational_management',
        'operational_transfers_requests',
        'operational_transfers_review',
        'operational_transfers_management',
        'operational_transfers_reports',
        'hr_absence_management',
        'hr_recruitment_management',
        'inspectoria',
        'inspectoria_bitacora',
    ];

    /** @var array<int, array{slug: string, name: string, route: string, sort: int}> */
    private const STUDENT_MODULE_DEFINITIONS = [
        ['slug' => 'students_directory', 'name' => 'Listado de estudiantes', 'route' => '/students', 'sort' => 1],
        ['slug' => 'students_promotions', 'name' => 'Promoción anual', 'route' => '/students/promotions', 'sort' => 5],
        ['slug' => 'students_movements', 'name' => 'Cambios y retiros', 'route' => '/students/movements', 'sort' => 6],
        ['slug' => 'students_reports', 'name' => 'Reportes', 'route' => '/students/reports', 'sort' => 7],
        ['slug' => 'students_attendance_statistics', 'name' => 'Estadísticas de asistencia', 'route' => '/students/attendance-statistics', 'sort' => 8],
        ['slug' => 'students_attendance_management', 'name' => 'Gestión de ausencia', 'route' => '/students/attendance-management', 'sort' => 9],
        ['slug' => 'students_grade_statistics', 'name' => 'Estadísticas de calificaciones', 'route' => '/students/grade-statistics', 'sort' => 10],
    ];

    public function up(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('system_modules')
        ) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            $this->ensurePermissions($now);
            $this->preserveExistingStudentConfigurationAccess($now);
            $this->preserveExistingInspectoriaDailyLogAccess($now);
            $this->ensureStudentModules($now);

            DB::table('roles')->insertOrIgnore([
                'slug' => self::ROLE,
                'name' => 'Coordinadora Académica',
                'description' => 'Gestión académica y operativa, con acceso integral a Estudiantes excepto su configuración base y consulta de la Bitácora de Inspectoría.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('roles')->where('slug', self::ROLE)->update([
                'name' => 'Coordinadora Académica',
                'description' => 'Gestión académica y operativa, con acceso integral a Estudiantes excepto su configuración base y consulta de la Bitácora de Inspectoría.',
                'active' => true,
                'updated_at' => $now,
            ]);

            $roleId = DB::table('roles')->where('slug', self::ROLE)->value('id');

            if (! $roleId) {
                return;
            }

            $permissionIds = DB::table('permissions')
                ->whereIn('slug', [
                    ...self::STUDENT_PERMISSIONS,
                    ...self::OPERATIONAL_PERMISSIONS,
                    self::INSPECTORIA_DAILY_LOG_VIEW_PERMISSION,
                ])
                ->pluck('id');

            if (Schema::hasTable('permission_role') && $permissionIds->isNotEmpty()) {
                DB::table('permission_role')->insertOrIgnore($permissionIds
                    ->map(fn ($permissionId) => [
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all());
            }

            if (Schema::hasTable('role_system_module')) {
                $moduleIds = DB::table('system_modules')
                    ->whereIn('slug', self::MODULES)
                    ->pluck('id');

                if ($moduleIds->isNotEmpty()) {
                    DB::table('role_system_module')->insertOrIgnore($moduleIds
                        ->map(fn ($moduleId) => [
                            'role_id' => $roleId,
                            'system_module_id' => $moduleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all());
                }
            }
        });
    }

    public function down(): void
    {
        // Cambio productivo aditivo: no se eliminan roles, permisos ni asignaciones.
    }

    private function ensurePermissions(mixed $now): void
    {
        foreach (self::PROFILE_PERMISSION_NAMES as $slug => $name) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'description' => 'Permiso requerido por el perfil de Coordinadora Académica.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')->where('slug', $slug)->update([
                'name' => $name,
                'active' => true,
                'updated_at' => $now,
            ]);
        }

        $permissions = [
            self::BASE_CONFIGURATION_PERMISSION => [
                'name' => 'Ver configuración base de Estudiantes',
                'description' => 'Permite consultar niveles, años académicos y cursos por año.',
            ],
            self::INSPECTORIA_DAILY_LOG_VIEW_PERMISSION => [
                'name' => 'Ver Bitácora de Inspectoría',
                'description' => 'Permite consultar la Bitácora diaria de Inspectoría sin registrar ni editar entradas.',
            ],
        ];

        foreach ($permissions as $slug => $definition) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('permissions')->where('slug', $slug)->update([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'active' => true,
                'updated_at' => $now,
            ]);
        }

        $this->assignPermissionToGroup(self::BASE_CONFIGURATION_PERMISSION, 'estudiantes', $now);
        $this->assignPermissionToGroup(self::INSPECTORIA_DAILY_LOG_VIEW_PERMISSION, 'inspectoria', $now);

        foreach (array_diff(self::STUDENT_PERMISSIONS, ['ver_dashboard']) as $permissionSlug) {
            $this->assignPermissionToGroup($permissionSlug, 'estudiantes', $now);
        }
    }

    private function preserveExistingStudentConfigurationAccess(mixed $now): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $viewStudentsId = DB::table('permissions')->where('slug', 'ver_estudiantes')->value('id');
        $configurationId = DB::table('permissions')->where('slug', self::BASE_CONFIGURATION_PERMISSION)->value('id');

        if (! $viewStudentsId || ! $configurationId) {
            return;
        }

        $roleIds = DB::table('permission_role as permission_access')
            ->join('roles', 'roles.id', '=', 'permission_access.role_id')
            ->where('permission_access.permission_id', $viewStudentsId)
            ->where('roles.slug', '!=', self::ROLE)
            ->pluck('permission_access.role_id')
            ->unique();

        if ($roleIds->isNotEmpty()) {
            DB::table('permission_role')->insertOrIgnore($roleIds
                ->map(fn ($roleId) => [
                    'role_id' => $roleId,
                    'permission_id' => $configurationId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());
        }
    }

    private function preserveExistingInspectoriaDailyLogAccess(mixed $now): void
    {
        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $sourcePermissionIds = DB::table('permissions')
            ->whereIn('slug', ['ver_modulo_inspectoria', 'registrar_bitacora_inspectoria'])
            ->pluck('id');
        $dailyLogViewId = DB::table('permissions')
            ->where('slug', self::INSPECTORIA_DAILY_LOG_VIEW_PERMISSION)
            ->value('id');

        if ($sourcePermissionIds->isEmpty() || ! $dailyLogViewId) {
            return;
        }

        $roleIds = DB::table('permission_role as permission_access')
            ->join('roles', 'roles.id', '=', 'permission_access.role_id')
            ->whereIn('permission_access.permission_id', $sourcePermissionIds)
            ->where('roles.slug', '!=', self::ROLE)
            ->pluck('permission_access.role_id')
            ->unique();

        if ($roleIds->isNotEmpty()) {
            DB::table('permission_role')->insertOrIgnore($roleIds
                ->map(fn ($roleId) => [
                    'role_id' => $roleId,
                    'permission_id' => $dailyLogViewId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());
        }
    }

    private function ensureStudentModules(mixed $now): void
    {
        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'dashboard',
            'name' => 'Inicio',
            'frontend_route' => '/inicio',
            'icon' => 'bx-home-circle',
            'sort_order' => 10,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'students',
            'name' => 'Estudiantes',
            'frontend_route' => null,
            'icon' => 'bx-user',
            'sort_order' => 20,
            'active' => true,
            'parent_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $parentId = DB::table('system_modules')->where('slug', 'students')->value('id');

        if (! $parentId) {
            return;
        }

        foreach (self::STUDENT_MODULE_DEFINITIONS as $module) {
            DB::table('system_modules')->insertOrIgnore([
                'slug' => $module['slug'],
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'icon' => null,
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')->where('slug', $module['slug'])->update([
                'name' => $module['name'],
                'frontend_route' => $module['route'],
                'sort_order' => $module['sort'],
                'active' => true,
                'parent_id' => $parentId,
                'updated_at' => $now,
            ]);
        }
    }

    private function assignPermissionToGroup(string $permissionSlug, string $groupSlug, mixed $now): void
    {
        if (
            ! Schema::hasTable('permission_groups')
            || ! Schema::hasTable('permission_group_permission')
        ) {
            return;
        }

        $groupId = DB::table('permission_groups')->where('slug', $groupSlug)->value('id');
        $permissionId = DB::table('permissions')->where('slug', $permissionSlug)->value('id');

        if ($groupId && $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
