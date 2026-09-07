<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LOGBOOK_REVIEW_PERMISSION = 'superadmin.logbooks.view';

    /** @var array<string, string> */
    private const REQUIRED_PERMISSION_NAMES = [
        'ver_convivencia' => 'Ver Convivencia Escolar',
        'ver_dashboard_convivencia' => 'Ver dashboard de Convivencia Escolar',
        'ver_casos_convivencia' => 'Ver casos de Convivencia Escolar',
        'ver_dependencias' => 'Ver Dependencias',
        'ver_reservas' => 'Ver Reservas',
        'ver_enfermeria' => 'Ver módulo de Enfermería',
    ];

    /** @var array<int, string> */
    private const WEB_MANAGER_PERMISSIONS = [
        'ver_metricas_sitio',
        'ver_noticias',
        'gestionar_noticias',
        'ver_eventos',
        'gestionar_eventos',
        'ver_testimonios',
        'gestionar_testimonios',
        'ver_vida_estudiantil',
        'gestionar_vida_estudiantil',
        'ver_instalaciones_sitio',
        'gestionar_instalaciones_sitio',
        'ver_cgpa_sitio',
        'gestionar_cgpa_sitio',
        'ver_cde_sitio',
        'gestionar_cde_sitio',
        'ver_comite_paritario_sitio',
        'gestionar_comite_paritario_sitio',
        'ver_contactos_sitio',
        'gestionar_contactos_sitio',
    ];

    /** @var array<int, string> */
    private const WEB_MANAGER_MODULES = [
        'public_site',
        'public_site_analytics',
        'public_site_news',
        'public_site_events',
        'public_site_contacts',
        'public_site_testimonials',
        'public_site_student_life',
        'public_site_cgpa',
        'public_site_cde',
        'public_site_joint_committee',
        'public_site_installations',
    ];

    /** @var array<int, string> */
    private const SUBDIRECTOR_PERMISSIONS = [
        'ver_dashboard',
        'ver_reportes',
        'ver_comunicaciones_internas',
        'ver_funcionarios',

        'ver_convivencia',
        'ver_dashboard_convivencia',
        'ver_casos_convivencia',
        'ver_sociogramas_convivencia',
        'ver_reportes_curso_convivencia',
        'exportar_reportes_convivencia',

        'psychology.access',
        'psychology.referrals.view_all',
        'psychology.cases.view_all',
        'psychology.risk.view',
        'psychology.documents.download',
        'psychology.reports.aggregate',
        'psychology.audit.view',

        'social_work.dashboard.view',
        'social_work.students.view',
        'social_work.student_profile.view',
        'social_work.cases.view',
        'social_work.medical_documents.view',
        'social_work.audit.view',

        'orientation.view',

        'ver_traslados_operativos',
        'solicitar_traslados_operativos',
        'visar_traslados_operativos',
        'exportar_traslados_operativos',
        'rrhh.ausencias.ver',
        'rrhh.seleccion.ver',

        'ver_dependencias',
        'ver_reservas',
        'exportar_reservas',
        'ver_estadisticas_espacios',
        'exportar_estadisticas_espacios',

        'ver_modulo_inspectoria',
        'ver_fichas_inspectoria',
        'ver_retiros_inspectoria',
        'ver_bitacora_inspectoria',
        'ver_estadisticas_inspectoria',
        'ver_licencias_medicas_estudiantes',

        self::LOGBOOK_REVIEW_PERMISSION,

        'pedagogical-instruments.view',
        'pedagogical-instruments.view-all',
        'pedagogical-instruments.download',
        'pedagogical-instruments.statistics',
        'class-presentations.view',
        'class-presentations.view-all',
        'class-presentations.download',
        'pedagogical-print-requests.view',
        'pedagogical-print-requests.download',

        'ver_enfermeria',
        'exportar_enfermeria',
        'ver_reportes_enfermeria',
        'ver_bitacora_enfermeria',

        'ver_modulo_centro_apuntes',
        'ver_reportes_centro_apuntes',
        'exportar_reportes_centro_apuntes',

        'ver_modulo_biblioteca',
        'ver_estadisticas_biblioteca',
        'exportar_reportes_biblioteca',
    ];

    /** @var array<int, string> */
    private const SUBDIRECTOR_MODULES = [
        'dashboard',
        'reports',
        'internal_communications',

        'convivencia',

        'psychology',
        'psychology_dashboard',
        'psychology_referrals',
        'psychology_cases',
        'psychology_calendar',
        'psychology_tasks',
        'psychology_alerts',
        'psychology_reports',
        'psychology_audit',

        'social_work',
        'social_work_dashboard',
        'social_work_students',
        'social_work_cases',
        'social_work_calendar',
        'social_work_audit',

        'orientation',
        'orientation_annual_plan',
        'orientation_calendar',
        'orientation_calendarization',
        'orientation_statistics',

        'operational_management',
        'operational_transfers_requests',
        'operational_transfers_review',
        'operational_transfers_reports',
        'hr_absence_management',
        'hr_recruitment_management',

        'spaces',
        'spaces_dependencies',
        'spaces_dependency_types',
        'spaces_approvers',
        'spaces_reservations',
        'spaces_calendar',
        'spaces_statistics',

        'inspectoria',
        'inspectoria_atenciones',
        'inspectoria_asignaciones',
        'inspectoria_pases',
        'inspectoria_alumnas',
        'inspectoria_retiros',
        'inspectoria_bitacora',
        'inspectoria_estadisticas',
        'inspectoria_medical_leaves',

        'superadmin',
        'superadmin_logbook_review',

        'pedagogical_management',
        'pedagogical_instrument_analysis',
        'pedagogical_my_instruments',
        'class_presentation_generator',
        'pedagogical_statistics',

        'infirmary',
        'infirmary_dashboard',
        'infirmary_attentions',
        'infirmary_staff_attentions',
        'infirmary_categories',
        'infirmary_inventory',
        'infirmary_accidents',
        'infirmary_medications',
        'infirmary_medical_leaves',
        'infirmary_daily_log',

        'centro_apuntes',
        'centro_apuntes_dashboard',
        'centro_apuntes_solicitudes',
        'centro_apuntes_pedagogical_queue',
        'centro_apuntes_asignaturas',
        'centro_apuntes_maquinas',
        'centro_apuntes_insumos',
        'centro_apuntes_movimientos',
        'centro_apuntes_entregas',
        'centro_apuntes_reportes',

        'biblioteca',
        'biblioteca_dashboard',
        'biblioteca_catalogo',
        'biblioteca_categorias',
        'biblioteca_almacenaje',
        'biblioteca_inventario',
        'biblioteca_prestamos',
        'biblioteca_materiales',
        'biblioteca_textos_escolares',
        'biblioteca_reservas',
        'biblioteca_plan_lector',
        'biblioteca_espacios',
        'biblioteca_pases',
        'biblioteca_reportes',
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

            $this->ensureRequiredModules($now);
            $this->ensureRequiredPermissions($now);
            $this->ensureLogbookReviewPermission($now);
            $this->ensureLogbookReviewGroup($now);

            $webManagerId = $this->upsertRole(
                'encargado_web',
                'Encargado Web',
                'Gestión y publicación de contenidos del sitio web institucional.',
                $now,
            );
            $subdirectorId = $this->upsertRole(
                'subdirector',
                'Subdirector',
                'Supervisión institucional de convivencia, apoyo psicosocial, operación, pedagogía, salud y servicios escolares.',
                $now,
            );

            $this->attachAccess($webManagerId, self::WEB_MANAGER_PERMISSIONS, self::WEB_MANAGER_MODULES, $now);
            $this->attachAccess($subdirectorId, self::SUBDIRECTOR_PERMISSIONS, self::SUBDIRECTOR_MODULES, $now);

            $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');
            $this->attachAccess(
                $superAdminId,
                [
                    self::LOGBOOK_REVIEW_PERMISSION,
                    'operational_logbook.view',
                    'operational_logbook.create',
                ],
                [
                    'superadmin',
                    'superadmin_logbook_review',
                    'operational_staff_logbook',
                ],
                $now,
            );
        });
    }

    public function down(): void
    {
        // Migración aditiva: se preservan roles, permisos y asignaciones al revertir.
    }

    private function ensureRequiredModules(mixed $now): void
    {
        $definitions = [
            'infirmary' => ['Enfermería', '/infirmary', 'bx-plus-medical', 50],
            'convivencia' => ['Convivencia Escolar', '/convivencia', 'bx-happy', 70],
            'spaces' => ['Dependencias y Reservas', null, 'bx-calendar-event', 115],
        ];

        foreach ($definitions as $slug => [$name, $route, $icon, $sortOrder]) {
            DB::table('system_modules')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'frontend_route' => $route,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'active' => true,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('system_modules')->where('slug', $slug)->update([
                'name' => $name,
                'frontend_route' => $route,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'active' => true,
                'parent_id' => null,
                'updated_at' => $now,
            ]);
        }

        $childrenByParent = [
            'infirmary' => [
                'infirmary_dashboard',
                'infirmary_attentions',
                'infirmary_staff_attentions',
                'infirmary_categories',
                'infirmary_inventory',
                'infirmary_accidents',
                'infirmary_medications',
                'infirmary_medical_leaves',
                'infirmary_daily_log',
            ],
            'spaces' => [
                'spaces_dependencies',
                'spaces_dependency_types',
                'spaces_approvers',
                'spaces_reservations',
                'spaces_calendar',
                'spaces_statistics',
            ],
        ];

        foreach ($childrenByParent as $parentSlug => $childSlugs) {
            $parentId = DB::table('system_modules')->where('slug', $parentSlug)->value('id');
            if ($parentId) {
                DB::table('system_modules')->whereIn('slug', $childSlugs)->update([
                    'parent_id' => $parentId,
                    'active' => true,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('permission_groups')) {
            foreach ([
                'convivencia_escolar' => 'convivencia',
                'dependencias_reservas' => 'spaces',
                'enfermeria' => 'infirmary',
            ] as $groupSlug => $moduleSlug) {
                $moduleId = DB::table('system_modules')->where('slug', $moduleSlug)->value('id');
                if ($moduleId) {
                    DB::table('permission_groups')->where('slug', $groupSlug)->update([
                        'system_module_id' => $moduleId,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function ensureRequiredPermissions(mixed $now): void
    {
        foreach (self::REQUIRED_PERMISSION_NAMES as $slug => $name) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'description' => 'Permiso de consulta requerido por el perfil Subdirector.',
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

        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $groupPermissions = [
            'convivencia_escolar' => ['ver_convivencia', 'ver_dashboard_convivencia', 'ver_casos_convivencia'],
            'dependencias_reservas' => ['ver_dependencias', 'ver_reservas'],
            'enfermeria' => ['ver_enfermeria'],
        ];

        foreach ($groupPermissions as $groupSlug => $permissionSlugs) {
            $groupId = DB::table('permission_groups')->where('slug', $groupSlug)->value('id');
            if (! $groupId) {
                continue;
            }

            foreach (DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id') as $permissionId) {
                DB::table('permission_group_permission')->insertOrIgnore([
                    'permission_group_id' => $groupId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function ensureLogbookReviewPermission(mixed $now): void
    {
        DB::table('permissions')->insertOrIgnore([
            'slug' => self::LOGBOOK_REVIEW_PERMISSION,
            'name' => 'Ver consolidado institucional de bitácoras',
            'description' => 'Permite consultar, sin editar, el consolidado protegido de bitácoras institucionales.',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permissions')
            ->where('slug', self::LOGBOOK_REVIEW_PERMISSION)
            ->update([
                'name' => 'Ver consolidado institucional de bitácoras',
                'description' => 'Permite consultar, sin editar, el consolidado protegido de bitácoras institucionales.',
                'active' => true,
                'updated_at' => $now,
            ]);
    }

    private function ensureLogbookReviewGroup(mixed $now): void
    {
        if (! Schema::hasTable('permission_groups') || ! Schema::hasTable('permission_group_permission')) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'superadmin')->value('id');
        if (! $moduleId) {
            return;
        }

        DB::table('permission_groups')->insertOrIgnore([
            'slug' => 'supervision_institucional',
            'system_module_id' => $moduleId,
            'name' => 'Supervisión institucional',
            'description' => 'Herramientas transversales de consulta y supervisión institucional.',
            'sort_order' => 257,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permission_groups')
            ->where('slug', 'supervision_institucional')
            ->update([
                'system_module_id' => $moduleId,
                'name' => 'Supervisión institucional',
                'description' => 'Herramientas transversales de consulta y supervisión institucional.',
                'sort_order' => 257,
                'active' => true,
                'updated_at' => $now,
            ]);

        $groupId = DB::table('permission_groups')->where('slug', 'supervision_institucional')->value('id');
        $permissionId = DB::table('permissions')->where('slug', self::LOGBOOK_REVIEW_PERMISSION)->value('id');

        if ($groupId && $permissionId) {
            DB::table('permission_group_permission')->insertOrIgnore([
                'permission_group_id' => $groupId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function upsertRole(string $slug, string $name, string $description, mixed $now): mixed
    {
        DB::table('roles')->insertOrIgnore([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('roles')->where('slug', $slug)->update([
            'name' => $name,
            'description' => $description,
            'active' => true,
            'updated_at' => $now,
        ]);

        return DB::table('roles')->where('slug', $slug)->value('id');
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     * @param  array<int, string>  $moduleSlugs
     */
    private function attachAccess(mixed $roleId, array $permissionSlugs, array $moduleSlugs, mixed $now): void
    {
        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('permission_role')) {
            $rows = DB::table('permissions')
                ->whereIn('slug', $permissionSlugs)
                ->where('active', true)
                ->pluck('id')
                ->map(fn ($permissionId) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($rows !== []) {
                DB::table('permission_role')->insertOrIgnore($rows);
            }
        }

        if (Schema::hasTable('role_system_module')) {
            $rows = DB::table('system_modules')
                ->whereIn('slug', $moduleSlugs)
                ->where('active', true)
                ->pluck('id')
                ->map(fn ($moduleId) => [
                    'role_id' => $roleId,
                    'system_module_id' => $moduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($rows !== []) {
                DB::table('role_system_module')->insertOrIgnore($rows);
            }
        }
    }
};
