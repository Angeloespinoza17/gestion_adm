<?php

namespace Database\Seeders;

use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\SystemModule;
use Illuminate\Database\Seeder;

class NavigationModuleBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureModules();
        $this->linkPermissionGroups();
        $this->syncRoleModulesFromPermissions();
    }

    private function ensureModules(): void
    {
        foreach ($this->moduleDefinitions() as $definition) {
            $parent = SystemModule::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'frontend_route' => null,
                    'icon' => $definition['icon'],
                    'sort_order' => $definition['sort_order'],
                    'active' => true,
                    'parent_id' => null,
                ],
            );

            foreach ($definition['children'] as $child) {
                SystemModule::query()->updateOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'name' => $child['name'],
                        'frontend_route' => $child['route'],
                        'icon' => null,
                        'sort_order' => $child['sort_order'],
                        'active' => true,
                        'parent_id' => $parent->id,
                    ],
                );
            }
        }
    }

    private function linkPermissionGroups(): void
    {
        $moduleIds = SystemModule::query()
            ->whereIn('slug', array_column($this->moduleDefinitions(), 'slug'))
            ->pluck('id', 'slug');

        foreach ($this->moduleDefinitions() as $definition) {
            PermissionGroup::query()
                ->where('slug', $definition['group_slug'])
                ->update(['system_module_id' => $moduleIds[$definition['slug']] ?? null]);
        }
    }

    private function syncRoleModulesFromPermissions(): void
    {
        $definitions = $this->moduleDefinitions();
        $moduleIds = SystemModule::query()
            ->whereIn('slug', $this->allModuleSlugs($definitions))
            ->pluck('id', 'slug');

        $groupPermissions = PermissionGroup::query()
            ->with('permissions:id,slug')
            ->whereIn('slug', array_column($definitions, 'group_slug'))
            ->get()
            ->mapWithKeys(fn (PermissionGroup $group) => [
                $group->slug => $group->permissions->pluck('slug')->values()->all(),
            ]);

        Role::query()
            ->with('permissions:id,slug')
            ->where('active', true)
            ->get()
            ->each(function (Role $role) use ($definitions, $moduleIds, $groupPermissions): void {
                $rolePermissionSlugs = $role->permissions->pluck('slug')->values()->all();
                $moduleSlugsToAttach = [];

                foreach ($definitions as $definition) {
                    $permissionSlugs = $groupPermissions->get($definition['group_slug'], $this->permissionsFromDefinition($definition));

                    if ($role->slug === 'super_admin' || $this->hasAnyPermission($rolePermissionSlugs, $permissionSlugs)) {
                        $moduleSlugsToAttach[] = $definition['slug'];
                    }

                    foreach ($definition['children'] as $child) {
                        if ($role->slug === 'super_admin' || $this->hasAnyPermission($rolePermissionSlugs, $child['permissions'])) {
                            $moduleSlugsToAttach[] = $child['slug'];
                        }
                    }
                }

                $ids = collect($moduleSlugsToAttach)
                    ->unique()
                    ->map(fn (string $slug) => $moduleIds[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($ids)) {
                    $role->modules()->syncWithoutDetaching($ids);
                }
            });
    }

    private function hasAnyPermission(array $rolePermissionSlugs, array $requiredPermissionSlugs): bool
    {
        return !empty(array_intersect($rolePermissionSlugs, $requiredPermissionSlugs));
    }

    private function allModuleSlugs(array $definitions): array
    {
        $slugs = [];

        foreach ($definitions as $definition) {
            $slugs[] = $definition['slug'];

            foreach ($definition['children'] as $child) {
                $slugs[] = $child['slug'];
            }
        }

        return array_values(array_unique($slugs));
    }

    private function permissionsFromDefinition(array $definition): array
    {
        $permissions = [];

        foreach ($definition['children'] as $child) {
            $permissions = array_merge($permissions, $child['permissions']);
        }

        return array_values(array_unique($permissions));
    }

    private function moduleDefinitions(): array
    {
        return [
            [
                'group_slug' => 'calendario_fechas_relevantes',
                'slug' => 'relevant_calendar',
                'name' => 'Calendario y Fechas Relevantes',
                'icon' => 'bx-calendar-event',
                'sort_order' => 118,
                'children' => [
                    [
                        'slug' => 'relevant_calendar_main',
                        'name' => 'Calendario',
                        'route' => '/relevant-calendar',
                        'sort_order' => 1,
                        'permissions' => [
                            'ver_calendario_fechas_relevantes',
                            'ver_todo_calendario_fechas_relevantes',
                            'gestionar_calendario_fechas_relevantes_departamento',
                            'administrar_calendario_fechas_relevantes',
                        ],
                    ],
                    [
                        'slug' => 'relevant_calendar_process_types',
                        'name' => 'Tipos de procesos',
                        'route' => '/relevant-calendar/process-types',
                        'sort_order' => 2,
                        'permissions' => [
                            'administrar_calendario_fechas_relevantes',
                            'administrar_tipos_calendario_fechas_relevantes',
                        ],
                    ],
                    [
                        'slug' => 'relevant_calendar_institutions',
                        'name' => 'Instituciones',
                        'route' => '/relevant-calendar/institutions',
                        'sort_order' => 3,
                        'permissions' => [
                            'administrar_calendario_fechas_relevantes',
                            'administrar_instituciones_calendario_fechas_relevantes',
                        ],
                    ],
                ],
            ],
            [
                'group_slug' => 'pme_sep',
                'slug' => 'pme_sep',
                'name' => 'Gestion PME / SEP',
                'icon' => 'bx-line-chart',
                'sort_order' => 58,
                'children' => [
                    ['slug' => 'pme_sep_dashboard', 'name' => 'Dashboard', 'route' => '/pme-sep', 'sort_order' => 1, 'permissions' => ['ver_modulo_pme']],
                    ['slug' => 'pme_sep_configuracion', 'name' => 'Configuracion PME', 'route' => '/pme-sep/configuracion', 'sort_order' => 2, 'permissions' => ['administrar_configuracion_pme']],
                    ['slug' => 'pme_sep_ingresos', 'name' => 'Ingresos SEP', 'route' => '/pme-sep/ingresos', 'sort_order' => 3, 'permissions' => ['administrar_ingresos_sep']],
                    ['slug' => 'pme_sep_estudiantes', 'name' => 'Estudiantes SEP', 'route' => '/pme-sep/estudiantes', 'sort_order' => 4, 'permissions' => ['ver_estudiantes_prioritarios_sep', 'ver_estudiantes_preferentes_sep', 'cargar_estudiantes_sep']],
                    ['slug' => 'pme_sep_dimensiones', 'name' => 'Dimensiones', 'route' => '/pme-sep/dimensiones', 'sort_order' => 5, 'permissions' => ['administrar_configuracion_pme', 'crear_objetivos_pme', 'editar_objetivos_pme']],
                    ['slug' => 'pme_sep_objetivos', 'name' => 'Objetivos', 'route' => '/pme-sep/objetivos', 'sort_order' => 6, 'permissions' => ['crear_objetivos_pme', 'editar_objetivos_pme']],
                    ['slug' => 'pme_sep_estrategias', 'name' => 'Estrategias', 'route' => '/pme-sep/estrategias', 'sort_order' => 7, 'permissions' => ['crear_estrategias_pme', 'editar_estrategias_pme']],
                    ['slug' => 'pme_sep_indicadores', 'name' => 'Indicadores', 'route' => '/pme-sep/indicadores', 'sort_order' => 8, 'permissions' => ['crear_indicadores_pme', 'medir_indicadores_pme']],
                    ['slug' => 'pme_sep_acciones', 'name' => 'Acciones', 'route' => '/pme-sep/acciones', 'sort_order' => 9, 'permissions' => ['crear_acciones_pme', 'editar_acciones_pme', 'cerrar_acciones_pme']],
                    ['slug' => 'pme_sep_evidencias', 'name' => 'Evidencias y actividades', 'route' => '/pme-sep/evidencias', 'sort_order' => 10, 'permissions' => ['crear_evidencias_pme', 'revisar_evidencias_pme', 'aprobar_evidencias_pme', 'rechazar_evidencias_pme']],
                    ['slug' => 'pme_sep_hitos', 'name' => 'Hitos', 'route' => '/pme-sep/hitos', 'sort_order' => 11, 'permissions' => ['crear_hitos_pme']],
                    ['slug' => 'pme_sep_metas', 'name' => 'Metas estrategicas', 'route' => '/pme-sep/metas', 'sort_order' => 12, 'permissions' => ['ver_modulo_pme']],
                    ['slug' => 'pme_sep_monitoreo', 'name' => 'Monitoreo reflexivo', 'route' => '/pme-sep/monitoreo', 'sort_order' => 13, 'permissions' => ['registrar_monitoreo_reflexivo_pme']],
                    ['slug' => 'pme_sep_reportes', 'name' => 'Reportes', 'route' => '/pme-sep/reportes', 'sort_order' => 14, 'permissions' => ['ver_reportes_pme', 'exportar_reportes_pme']],
                ],
            ],
            [
                'group_slug' => 'biblioteca',
                'slug' => 'biblioteca',
                'name' => 'Biblioteca Escolar',
                'icon' => 'bx-book-open',
                'sort_order' => 85,
                'children' => [
                    ['slug' => 'biblioteca_dashboard', 'name' => 'Dashboard', 'route' => '/biblioteca', 'sort_order' => 1, 'permissions' => ['ver_modulo_biblioteca']],
                    ['slug' => 'biblioteca_catalogo', 'name' => 'Catalogo', 'route' => '/biblioteca/catalogo', 'sort_order' => 2, 'permissions' => ['ver_modulo_biblioteca', 'administrar_catalogo_biblioteca', 'crear_libros_biblioteca', 'editar_libros_biblioteca', 'eliminar_libros_biblioteca']],
                    ['slug' => 'biblioteca_inventario', 'name' => 'Ejemplares e inventario', 'route' => '/biblioteca/inventario', 'sort_order' => 3, 'permissions' => ['administrar_inventario_biblioteca']],
                    ['slug' => 'biblioteca_prestamos', 'name' => 'Prestamos y devoluciones', 'route' => '/biblioteca/prestamos', 'sort_order' => 4, 'permissions' => ['registrar_prestamos_biblioteca', 'registrar_devoluciones_biblioteca', 'renovar_prestamos_biblioteca', 'gestionar_mora_biblioteca']],
                    ['slug' => 'biblioteca_reservas', 'name' => 'Reservas de recursos', 'route' => '/biblioteca/reservas', 'sort_order' => 5, 'permissions' => ['gestionar_reservas_biblioteca']],
                    ['slug' => 'biblioteca_plan_lector', 'name' => 'Plan lector', 'route' => '/biblioteca/plan-lector', 'sort_order' => 6, 'permissions' => ['gestionar_plan_lector_biblioteca']],
                    ['slug' => 'biblioteca_espacios', 'name' => 'Uso de espacios', 'route' => '/biblioteca/espacios', 'sort_order' => 7, 'permissions' => ['gestionar_uso_espacios_biblioteca']],
                    ['slug' => 'biblioteca_reportes', 'name' => 'Reportes', 'route' => '/biblioteca/reportes', 'sort_order' => 8, 'permissions' => ['ver_estadisticas_biblioteca', 'exportar_reportes_biblioteca']],
                ],
            ],
            [
                'group_slug' => 'centro_apuntes_panol',
                'slug' => 'centro_apuntes',
                'name' => 'Centro de Apuntes',
                'icon' => 'bx-printer',
                'sort_order' => 86,
                'children' => [
                    ['slug' => 'centro_apuntes_dashboard', 'name' => 'Dashboard', 'route' => '/centro-apuntes', 'sort_order' => 1, 'permissions' => ['ver_modulo_centro_apuntes']],
                    ['slug' => 'centro_apuntes_solicitudes', 'name' => 'Solicitudes y tareas', 'route' => '/centro-apuntes/solicitudes', 'sort_order' => 2, 'permissions' => ['ver_modulo_centro_apuntes', 'crear_solicitud_impresion', 'editar_solicitud_impresion', 'eliminar_solicitud_impresion', 'cambiar_estado_solicitud_impresion']],
                    ['slug' => 'centro_apuntes_asignaturas', 'name' => 'Asignaturas', 'route' => '/centro-apuntes/asignaturas', 'sort_order' => 3, 'permissions' => ['administrar_asignaturas_centro_apuntes']],
                    ['slug' => 'centro_apuntes_maquinas', 'name' => 'Maquinas', 'route' => '/centro-apuntes/maquinas', 'sort_order' => 4, 'permissions' => ['administrar_maquinas_centro_apuntes']],
                    ['slug' => 'centro_apuntes_insumos', 'name' => 'Panol e insumos', 'route' => '/centro-apuntes/insumos', 'sort_order' => 5, 'permissions' => ['administrar_inventario_panol']],
                    ['slug' => 'centro_apuntes_movimientos', 'name' => 'Movimientos de stock', 'route' => '/centro-apuntes/movimientos', 'sort_order' => 6, 'permissions' => ['registrar_movimientos_panol']],
                    ['slug' => 'centro_apuntes_entregas', 'name' => 'Entregas de materiales', 'route' => '/centro-apuntes/entregas', 'sort_order' => 7, 'permissions' => ['registrar_entrega_centro_apuntes', 'aprobar_entregas_panol']],
                    ['slug' => 'centro_apuntes_reportes', 'name' => 'Reportes', 'route' => '/centro-apuntes/reportes', 'sort_order' => 8, 'permissions' => ['ver_reportes_centro_apuntes', 'exportar_reportes_centro_apuntes']],
                ],
            ],
            [
                'group_slug' => 'apoyo_profesional',
                'slug' => 'apoyo_profesional',
                'name' => 'Equipo de Apoyo',
                'icon' => 'bx-heart-circle',
                'sort_order' => 56,
                'children' => [
                    ['slug' => 'apoyo_profesional_dashboard', 'name' => 'Dashboard', 'route' => '/apoyo-profesional', 'sort_order' => 1, 'permissions' => ['ver_modulo_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_attentions', 'name' => 'Atenciones', 'route' => '/apoyo-profesional/atenciones', 'sort_order' => 2, 'permissions' => ['crear_atencion_apoyo_profesional', 'editar_atencion_propia_apoyo_profesional', 'editar_cualquier_atencion_apoyo_profesional', 'eliminar_atencion_apoyo_profesional', 'ver_atenciones_propias_apoyo_profesional', 'ver_atenciones_equipo_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_history', 'name' => 'Ficha estudiante', 'route' => '/apoyo-profesional/historial', 'sort_order' => 3, 'permissions' => ['ver_atenciones_propias_apoyo_profesional', 'ver_atenciones_equipo_apoyo_profesional', 'ver_atenciones_confidenciales_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_derivations', 'name' => 'Derivaciones', 'route' => '/apoyo-profesional/derivaciones', 'sort_order' => 4, 'permissions' => ['crear_derivacion_apoyo_profesional', 'responder_derivacion_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_followups', 'name' => 'Seguimientos', 'route' => '/apoyo-profesional/seguimientos', 'sort_order' => 5, 'permissions' => ['crear_seguimiento_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_plans', 'name' => 'Planes de apoyo', 'route' => '/apoyo-profesional/planes', 'sort_order' => 6, 'permissions' => ['crear_plan_apoyo_profesional', 'cerrar_caso_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_interviews', 'name' => 'Entrevistas', 'route' => '/apoyo-profesional/entrevistas', 'sort_order' => 7, 'permissions' => ['ver_modulo_apoyo_profesional', 'crear_atencion_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_documents', 'name' => 'Documentos', 'route' => '/apoyo-profesional/documentos', 'sort_order' => 8, 'permissions' => ['ver_modulo_apoyo_profesional']],
                    ['slug' => 'apoyo_profesional_reports', 'name' => 'Reportes', 'route' => '/apoyo-profesional/reportes', 'sort_order' => 9, 'permissions' => ['ver_reportes_apoyo_profesional', 'exportar_reportes_apoyo_profesional']],
                ],
            ],
            [
                'group_slug' => 'informatica',
                'slug' => 'informatica',
                'name' => 'Informatica',
                'icon' => 'bx-laptop',
                'sort_order' => 86,
                'children' => [
                    ['slug' => 'informatica_dashboard', 'name' => 'Dashboard', 'route' => '/informatica', 'sort_order' => 1, 'permissions' => ['informatica.dashboard', 'informatica.ver']],
                    ['slug' => 'informatica_equipos', 'name' => 'Equipos', 'route' => '/informatica/equipos', 'sort_order' => 2, 'permissions' => ['informatica.equipos.ver', 'informatica.equipos.crear', 'informatica.equipos.editar', 'informatica.equipos.eliminar']],
                    ['slug' => 'informatica_prestamos', 'name' => 'Prestamos', 'route' => '/informatica/prestamos', 'sort_order' => 3, 'permissions' => ['informatica.prestamos.ver', 'informatica.prestamos.crear', 'informatica.prestamos.devolver', 'informatica.prestamos.cancelar']],
                    ['slug' => 'informatica_mantenciones', 'name' => 'Mantenciones', 'route' => '/informatica/mantenciones', 'sort_order' => 4, 'permissions' => ['informatica.mantenciones.ver', 'informatica.mantenciones.crear', 'informatica.mantenciones.editar', 'informatica.mantenciones.cerrar']],
                    ['slug' => 'informatica_reportes', 'name' => 'Reportes', 'route' => '/informatica/reportes', 'sort_order' => 5, 'permissions' => ['informatica.reportes.ver']],
                ],
            ],
        ];
    }
}
