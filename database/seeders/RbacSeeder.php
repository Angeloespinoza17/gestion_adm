<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->seedCargos();
        $this->seedPermissions();
        $this->seedModules();
        $this->seedRolesAndAssignments();
        $this->seedSuperAdminUser();
    }

    /**
     * Restaura el perfil base sin eliminar permisos o módulos agregados por
     * funcionalidades posteriores ni personalizaciones existentes.
     */
    public function reconcileRoleAssignmentsAdditively(): void
    {
        $snapshots = Role::query()
            ->with(['permissions:id', 'modules:id'])
            ->get()
            ->mapWithKeys(fn (Role $role) => [
                $role->id => [
                    'permissions' => $role->permissions->pluck('id')->all(),
                    'modules' => $role->modules->pluck('id')->all(),
                ],
            ]);

        DB::transaction(function () use ($snapshots): void {
            $this->seedRolesAndAssignments();

            Role::query()->get()->each(function (Role $role) use ($snapshots): void {
                $snapshot = $snapshots->get($role->id);

                if (! $snapshot) {
                    return;
                }

                $role->permissions()->syncWithoutDetaching($snapshot['permissions']);
                $role->modules()->syncWithoutDetaching($snapshot['modules']);
            });
        });
    }

    private function seedCargos(): void
    {
        $cargos = [
            ['name' => 'Psicólogo/a', 'slug' => 'psicologo', 'description' => 'Equipo de psicología.'],
            ['name' => 'Coordinador Académico', 'slug' => 'coordinador_academico', 'description' => 'Coordinación académica.'],
            ['name' => 'Enfermero/a', 'slug' => 'enfermeria', 'description' => 'Enfermería escolar.'],
            ['name' => 'Prevencionista de Riesgos', 'slug' => 'prevencion_riesgos', 'description' => 'Prevención de riesgos.'],
            ['name' => 'Inspector/a', 'slug' => 'inspectoria', 'description' => 'Inspectoría.'],
            ['name' => 'Portero/a', 'slug' => 'porteria', 'description' => 'Portería y control de accesos.'],
            ['name' => 'Encargado/a de Mantención', 'slug' => 'encargado_mantencion', 'description' => 'Mantención e infraestructura.'],
            ['name' => 'Auxiliar de Mantención', 'slug' => 'auxiliar_mantenimiento', 'description' => 'Apoyo operativo en mantención e infraestructura.'],
            ['name' => 'Auxiliar de Aseo', 'slug' => 'auxiliar_aseo', 'description' => 'Aseo, limpieza y apoyo operativo.'],
            ['name' => 'Nochero/a', 'slug' => 'nochero', 'description' => 'Rondas y control nocturno.'],
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
            ['slug' => 'administrar_anos_academicos', 'name' => 'Administrar Años Académicos'],
            ['slug' => 'administrar_cursos_academicos', 'name' => 'Administrar Cursos Académicos'],
            ['slug' => 'gestionar_matriculas_estudiantes', 'name' => 'Gestionar Matrículas Estudiantiles'],
            ['slug' => 'promover_estudiantes', 'name' => 'Promover Estudiantes'],

            // Horarios docentes
            ['slug' => 'ver_horarios', 'name' => 'Ver Horarios Docentes'],
            ['slug' => 'editar_horarios', 'name' => 'Editar Horarios Docentes'],
            ['slug' => 'configurar_horarios', 'name' => 'Configurar Horarios'],
            ['slug' => 'configurar_jornadas', 'name' => 'Configurar Jornadas Escolares'],
            ['slug' => 'configurar_plan_estudio', 'name' => 'Configurar Plan de Estudio'],
            ['slug' => 'configurar_contratos_docentes', 'name' => 'Configurar Contratos Docentes'],
            ['slug' => 'forzar_excepciones_horario', 'name' => 'Forzar Excepciones de Horario'],
            ['slug' => 'ver_reportes_carga_horaria', 'name' => 'Ver Reportes de Carga Horaria'],

            // Portería
            ['slug' => 'ver_porteria', 'name' => 'Ver Portería'],
            ['slug' => 'registrar_retiro_porteria', 'name' => 'Registrar Retiro en Portería'],
            ['slug' => 'autorizar_retiros_porteria', 'name' => 'Autorizar Retiros Observados en Portería'],
            ['slug' => 'registrar_objetos_porteria', 'name' => 'Registrar Recepción de Objetos en Portería'],
            ['slug' => 'entregar_objetos_porteria', 'name' => 'Gestionar Entrega de Objetos en Portería'],
            ['slug' => 'registrar_mercaderia_porteria', 'name' => 'Registrar Mercadería en Portería'],
            ['slug' => 'entregar_mercaderia_porteria', 'name' => 'Gestionar Entrega de Mercadería en Portería'],
            ['slug' => 'registrar_visitas_porteria', 'name' => 'Registrar Visitas en Portería'],
            ['slug' => 'registrar_proveedores_porteria', 'name' => 'Registrar Proveedores en Portería'],
            ['slug' => 'registrar_bitacora_porteria', 'name' => 'Registrar Bitácora de Portería'],
            ['slug' => 'gestionar_llaves_porteria', 'name' => 'Gestionar Llaves en Portería'],
            ['slug' => 'ver_historial_porteria', 'name' => 'Ver Historial de Portería'],
            ['slug' => 'exportar_reportes_porteria', 'name' => 'Exportar Reportes de Portería'],

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

            // Inventario
            ['slug' => 'ver_inventario', 'name' => 'Ver Inventario'],
            ['slug' => 'crear_inventario', 'name' => 'Crear Inventario'],
            ['slug' => 'editar_inventario', 'name' => 'Editar Inventario'],
            ['slug' => 'eliminar_inventario', 'name' => 'Eliminar Inventario'],
            ['slug' => 'dar_baja_inventario', 'name' => 'Dar de baja Inventario'],
            ['slug' => 'mover_inventario', 'name' => 'Mover Inventario'],
            ['slug' => 'ver_reportes_inventario', 'name' => 'Ver Reportes Inventario'],
            ['slug' => 'exportar_inventario', 'name' => 'Exportar Inventario'],
            ['slug' => 'administrar_categorias_inventario', 'name' => 'Administrar Categorías Inventario'],
            ['slug' => 'subir_documentos_inventario', 'name' => 'Subir Documentos Inventario'],
            ['slug' => 'eliminar_documentos_inventario', 'name' => 'Eliminar Documentos Inventario'],
            ['slug' => 'imprimir_etiquetas_inventario', 'name' => 'Imprimir Etiquetas Inventario'],

            // Administración
            ['slug' => 'administrar_usuarios', 'name' => 'Administrar Usuarios'],
            ['slug' => 'administrar_roles', 'name' => 'Administrar Roles'],
            ['slug' => 'administrar_permisos', 'name' => 'Administrar Permisos'],
            ['slug' => 'administrar_modulos', 'name' => 'Administrar Módulos'],
            ['slug' => 'administrar_cargos', 'name' => 'Administrar Cargos'],
            ['slug' => 'administrar_organigrama', 'name' => 'Administrar Organigrama'],
            ['slug' => 'ver_noticias', 'name' => 'Ver Noticias del Sitio Web'],
            ['slug' => 'gestionar_noticias', 'name' => 'Gestionar Noticias del Sitio Web'],
            ['slug' => 'ver_eventos', 'name' => 'Ver Eventos del Sitio Web'],
            ['slug' => 'gestionar_eventos', 'name' => 'Gestionar Eventos del Sitio Web'],
            ['slug' => 'ver_contactos_sitio', 'name' => 'Ver Contactos del Sitio Web'],
            ['slug' => 'gestionar_contactos_sitio', 'name' => 'Gestionar Contactos del Sitio Web'],
            ['slug' => 'ver_comunicaciones_internas', 'name' => 'Ver Comunicaciones Internas'],
            ['slug' => 'gestionar_comunicaciones_internas', 'name' => 'Gestionar Comunicaciones Internas'],
            ['slug' => 'ver_funcionarios', 'name' => 'Ver Funcionarios'],
            ['slug' => 'gestionar_funcionarios', 'name' => 'Gestionar Funcionarios'],
            ['slug' => 'eliminar_funcionarios', 'name' => 'Eliminar Funcionarios'],
            ['slug' => 'administrar_departamentos', 'name' => 'Administrar Departamentos'],
            ['slug' => 'subir_documentos_funcionarios', 'name' => 'Subir Documentos Funcionarios'],
            ['slug' => 'ver_permisos_personal', 'name' => 'Ver Permisos del Personal'],
            ['slug' => 'solicitar_permisos_personal', 'name' => 'Solicitar Permisos del Personal'],
            ['slug' => 'revisar_permisos_equipo', 'name' => 'Revisar Permisos del Equipo'],
            ['slug' => 'aprobar_permisos_direccion', 'name' => 'Aprobar Permisos por Dirección'],
            ['slug' => 'revisar_permisos_rrhh', 'name' => 'Revisar Permisos por RRHH'],
            ['slug' => 'administrar_tipos_permisos_personal', 'name' => 'Administrar Tipos de Permiso'],
            ['slug' => 'administrar_destinatarios_permisos_personal', 'name' => 'Administrar Destinatarios de Permisos'],
            ['slug' => 'exportar_permisos_personal', 'name' => 'Exportar Permisos del Personal'],
            ['slug' => 'validar_documentos_permisos_personal', 'name' => 'Validar Documentos de Permisos'],
            ['slug' => 'gestionar_reemplazos_permisos_personal', 'name' => 'Gestionar Reemplazos de Permisos'],
            ['slug' => 'ver_reportes_permisos_personal', 'name' => 'Ver Reportes de Permisos'],
            ['slug' => 'ver_tareas', 'name' => 'Ver Backlog de Tareas'],
            ['slug' => 'gestionar_tareas', 'name' => 'Gestionar Tareas Propias'],
            ['slug' => 'ver_tareas_equipo', 'name' => 'Ver Backlogs de Equipo'],
            ['slug' => 'administrar_asignadores_tareas', 'name' => 'Administrar Asignadores de Tareas'],
            ['slug' => 'ver_contratos', 'name' => 'Ver Contratos'],
            ['slug' => 'gestionar_contratos', 'name' => 'Gestionar Contratos'],
            ['slug' => 'eliminar_contratos', 'name' => 'Eliminar Contratos'],
            ['slug' => 'exportar_contratos', 'name' => 'Exportar Contratos'],
            ['slug' => 'administrar_plantillas_contrato', 'name' => 'Administrar Plantillas de Contrato'],
            ['slug' => 'administrar_clausulas_contrato', 'name' => 'Administrar Cláusulas de Contrato'],
            ['slug' => 'administrar_firmas_contrato', 'name' => 'Administrar Firmas de Contrato'],
            ['slug' => 'editar_contratos_firmados', 'name' => 'Editar Contratos Firmados'],
            ['slug' => 'ver_dependencias', 'name' => 'Ver Dependencias'],
            ['slug' => 'crear_dependencias', 'name' => 'Crear Dependencias'],
            ['slug' => 'editar_dependencias', 'name' => 'Editar Dependencias'],
            ['slug' => 'eliminar_dependencias', 'name' => 'Eliminar Dependencias'],
            ['slug' => 'ver_reservas', 'name' => 'Ver Reservas'],
            ['slug' => 'crear_reservas', 'name' => 'Crear Reservas'],
            ['slug' => 'editar_reservas', 'name' => 'Editar Reservas'],
            ['slug' => 'cancelar_reservas', 'name' => 'Cancelar Reservas'],
            ['slug' => 'aprobar_reservas', 'name' => 'Aprobar Reservas'],
            ['slug' => 'rechazar_reservas', 'name' => 'Rechazar Reservas'],
            ['slug' => 'exportar_reservas', 'name' => 'Exportar Reservas'],
            ['slug' => 'administrar_calendario', 'name' => 'Administrar Calendario'],
            ['slug' => 'ver_estadisticas_espacios', 'name' => 'Ver Estadísticas de Espacios'],
            ['slug' => 'exportar_estadisticas_espacios', 'name' => 'Exportar Estadísticas de Espacios'],
            ['slug' => 'ver_rondas_seguridad', 'name' => 'Ver Rondas de Seguridad'],
            ['slug' => 'gestionar_turnos_nochero', 'name' => 'Gestionar Turnos de Nochero'],
            ['slug' => 'registrar_rondas_seguridad', 'name' => 'Registrar Rondas de Seguridad'],
            ['slug' => 'gestionar_novedades_rondas', 'name' => 'Gestionar Novedades de Rondas'],
            ['slug' => 'exportar_rondas_seguridad', 'name' => 'Exportar Rondas de Seguridad'],
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
            ['slug' => 'dashboard', 'name' => 'Inicio', 'frontend_route' => '/inicio', 'icon' => 'bx-home-circle', 'sort' => 10],
            ['slug' => 'students', 'name' => 'Estudiantes', 'frontend_route' => null, 'icon' => 'bx-user', 'sort' => 20],
            ['slug' => 'students_directory', 'name' => 'Listado de estudiantes', 'frontend_route' => '/students', 'icon' => null, 'sort' => 1, 'parent' => 'students'],
            ['slug' => 'students_levels', 'name' => 'Niveles', 'frontend_route' => '/students/levels', 'icon' => null, 'sort' => 2, 'parent' => 'students'],
            ['slug' => 'students_academic_years', 'name' => 'Años académicos', 'frontend_route' => '/students/academic-years', 'icon' => null, 'sort' => 3, 'parent' => 'students'],
            ['slug' => 'students_courses', 'name' => 'Cursos por año', 'frontend_route' => '/students/courses', 'icon' => null, 'sort' => 4, 'parent' => 'students'],
            ['slug' => 'students_promotions', 'name' => 'Promoción anual', 'frontend_route' => '/students/promotions', 'icon' => null, 'sort' => 5, 'parent' => 'students'],
            ['slug' => 'students_movements', 'name' => 'Cambios y retiros', 'frontend_route' => '/students/movements', 'icon' => null, 'sort' => 6, 'parent' => 'students'],
            ['slug' => 'students_reports', 'name' => 'Reportes', 'frontend_route' => '/students/reports', 'icon' => null, 'sort' => 7, 'parent' => 'students'],
            ['slug' => 'students_attendance_statistics', 'name' => 'Estadísticas de asistencia', 'frontend_route' => '/students/attendance-statistics', 'icon' => null, 'sort' => 8, 'parent' => 'students'],
            ['slug' => 'schedule', 'name' => 'Horarios docentes', 'frontend_route' => null, 'icon' => 'bx-calendar-event', 'sort' => 23],
            ['slug' => 'schedule_teacher', 'name' => 'Horario docente', 'frontend_route' => '/schedule/teacher', 'icon' => null, 'sort' => 1, 'parent' => 'schedule'],
            ['slug' => 'schedule_course', 'name' => 'Horario por curso', 'frontend_route' => '/schedule/course', 'icon' => null, 'sort' => 2, 'parent' => 'schedule'],
            ['slug' => 'schedule_config', 'name' => 'Configuración horaria', 'frontend_route' => '/schedule/config', 'icon' => null, 'sort' => 3, 'parent' => 'schedule'],
            ['slug' => 'schedule_jornadas', 'name' => 'Jornadas', 'frontend_route' => '/schedule/jornadas', 'icon' => null, 'sort' => 4, 'parent' => 'schedule'],
            ['slug' => 'schedule_study_plans', 'name' => 'Asignaturas y planes', 'frontend_route' => '/schedule/study-plans', 'icon' => null, 'sort' => 5, 'parent' => 'schedule'],
            ['slug' => 'schedule_contracts', 'name' => 'Contratos docentes', 'frontend_route' => '/schedule/contracts', 'icon' => null, 'sort' => 6, 'parent' => 'schedule'],
            ['slug' => 'schedule_conflicts', 'name' => 'Conflictos', 'frontend_route' => '/schedule/conflicts', 'icon' => null, 'sort' => 7, 'parent' => 'schedule'],
            ['slug' => 'porter', 'name' => 'Portería', 'frontend_route' => null, 'icon' => 'bx-building-house', 'sort' => 25],
            ['slug' => 'porter_dashboard', 'name' => 'Panel de portería', 'frontend_route' => '/porter/dashboard', 'icon' => null, 'sort' => 1, 'parent' => 'porter'],
            ['slug' => 'porter_students', 'name' => 'Buscar estudiante', 'frontend_route' => '/porter/students', 'icon' => null, 'sort' => 2, 'parent' => 'porter'],
            ['slug' => 'porter_withdrawals', 'name' => 'Retiros', 'frontend_route' => '/porter/withdrawals', 'icon' => null, 'sort' => 3, 'parent' => 'porter'],
            ['slug' => 'porter_received_items', 'name' => 'Recepción de objetos', 'frontend_route' => '/porter/received-items', 'icon' => null, 'sort' => 4, 'parent' => 'porter'],
            ['slug' => 'porter_goods', 'name' => 'Mercadería', 'frontend_route' => '/porter/goods', 'icon' => null, 'sort' => 5, 'parent' => 'porter'],
            ['slug' => 'porter_visits', 'name' => 'Control de visitas', 'frontend_route' => '/porter/visits', 'icon' => null, 'sort' => 6, 'parent' => 'porter'],
            ['slug' => 'porter_providers', 'name' => 'Control de proveedores', 'frontend_route' => '/porter/providers', 'icon' => null, 'sort' => 7, 'parent' => 'porter'],
            ['slug' => 'porter_daily_log', 'name' => 'Bitácora diaria', 'frontend_route' => '/porter/daily-log', 'icon' => null, 'sort' => 8, 'parent' => 'porter'],
            ['slug' => 'porter_keys', 'name' => 'Control de llaves', 'frontend_route' => '/porter/keys', 'icon' => null, 'sort' => 9, 'parent' => 'porter'],
            ['slug' => 'porter_reports', 'name' => 'Reportes de portería', 'frontend_route' => '/porter/reports', 'icon' => null, 'sort' => 10, 'parent' => 'porter'],
            ['slug' => 'guardians', 'name' => 'Apoderados', 'frontend_route' => '/guardians', 'icon' => 'bx-group', 'sort' => 30],
            ['slug' => 'staff', 'name' => 'Funcionarios', 'frontend_route' => null, 'icon' => 'bx-id-card', 'sort' => 40],
            ['slug' => 'staff_directory', 'name' => 'Listado de funcionarios', 'frontend_route' => '/staff', 'icon' => null, 'sort' => 1, 'parent' => 'staff'],
            ['slug' => 'staff_departments', 'name' => 'Departamentos', 'frontend_route' => '/staff/departments', 'icon' => null, 'sort' => 2, 'parent' => 'staff'],
            ['slug' => 'staff_permissions', 'name' => 'Permisos', 'frontend_route' => null, 'icon' => 'bx-calendar-minus', 'sort' => 42],
            ['slug' => 'staff_permissions_dashboard', 'name' => 'Dashboard permisos', 'frontend_route' => '/staff/permissions/dashboard', 'icon' => null, 'sort' => 1, 'parent' => 'staff_permissions'],
            ['slug' => 'staff_permissions_requests', 'name' => 'Mis permisos', 'frontend_route' => '/staff/permissions', 'icon' => null, 'sort' => 2, 'parent' => 'staff_permissions'],
            ['slug' => 'staff_permissions_review', 'name' => 'Bandeja de permisos', 'frontend_route' => '/staff/permissions/review', 'icon' => null, 'sort' => 3, 'parent' => 'staff_permissions'],
            ['slug' => 'staff_permissions_reports', 'name' => 'Reportes de permisos', 'frontend_route' => '/staff/permissions/reports', 'icon' => null, 'sort' => 4, 'parent' => 'staff_permissions'],
            ['slug' => 'staff_permissions_types', 'name' => 'Tipos de permiso', 'frontend_route' => '/staff/permissions/types', 'icon' => null, 'sort' => 5, 'parent' => 'staff_permissions'],
            ['slug' => 'staff_permissions_watchers', 'name' => 'Quién debe enterarse', 'frontend_route' => '/staff/permissions/watchers', 'icon' => null, 'sort' => 6, 'parent' => 'staff_permissions'],
            ['slug' => 'tasks', 'name' => 'Tareas', 'frontend_route' => null, 'icon' => 'bx-list-check', 'sort' => 43],
            ['slug' => 'tasks_backlog', 'name' => 'Mi backlog', 'frontend_route' => '/tasks/backlog', 'icon' => null, 'sort' => 1, 'parent' => 'tasks'],
            ['slug' => 'tasks_assigners', 'name' => 'Asignadores de tareas', 'frontend_route' => '/tasks/assigners', 'icon' => null, 'sort' => 2, 'parent' => 'tasks'],
            ['slug' => 'internal_communications', 'name' => 'Comunicaciones internas', 'frontend_route' => '/comunicaciones', 'icon' => 'bx-message-square-detail', 'sort' => 44],
            ['slug' => 'contracts', 'name' => 'Contratos', 'frontend_route' => null, 'icon' => 'bx-file', 'sort' => 45],
            ['slug' => 'contracts_list', 'name' => 'Listado de contratos', 'frontend_route' => '/contracts', 'icon' => null, 'sort' => 1, 'parent' => 'contracts'],
            ['slug' => 'contracts_templates', 'name' => 'Plantillas', 'frontend_route' => '/contracts/templates', 'icon' => null, 'sort' => 2, 'parent' => 'contracts'],
            ['slug' => 'contracts_clauses', 'name' => 'Cláusulas', 'frontend_route' => '/contracts/clauses', 'icon' => null, 'sort' => 3, 'parent' => 'contracts'],
            ['slug' => 'contracts_signatures', 'name' => 'Firmas', 'frontend_route' => '/contracts/signatures', 'icon' => null, 'sort' => 4, 'parent' => 'contracts'],
            ['slug' => 'infirmary', 'name' => 'Enfermería', 'frontend_route' => '/infirmary', 'icon' => 'bx-plus-medical', 'sort' => 50],
            ['slug' => 'psychology', 'name' => 'Psicología', 'frontend_route' => '/psychology', 'icon' => 'bx-brain', 'sort' => 60],
            ['slug' => 'convivencia', 'name' => 'Convivencia Escolar', 'frontend_route' => '/convivencia', 'icon' => 'bx-happy', 'sort' => 70],
            ['slug' => 'risk_prevention', 'name' => 'Prevención de Riesgos', 'frontend_route' => '/risk-prevention', 'icon' => 'bx-shield-quarter', 'sort' => 80],

            // Mantención (padre + submódulos)
            ['slug' => 'maintenance', 'name' => 'Mantención', 'frontend_route' => null, 'icon' => 'bx-wrench', 'sort' => 90],
            ['slug' => 'maintenance_dependencies', 'name' => 'Áreas técnicas', 'frontend_route' => '/maintenance/dependencies', 'icon' => null, 'sort' => 1, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_work_orders', 'name' => 'Órdenes de trabajo', 'frontend_route' => '/maintenance/work-orders', 'icon' => null, 'sort' => 2, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_workload', 'name' => 'Carga de trabajo', 'frontend_route' => '/maintenance/workload', 'icon' => null, 'sort' => 3, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_visits', 'name' => 'Planificación visitas', 'frontend_route' => '/maintenance/visits', 'icon' => null, 'sort' => 4, 'parent' => 'maintenance'],
            ['slug' => 'maintenance_annual_plans', 'name' => 'Plan anual mantención', 'frontend_route' => '/maintenance/annual-plans', 'icon' => null, 'sort' => 5, 'parent' => 'maintenance'],

            // Inventario (padre + submódulos)
            ['slug' => 'inventory', 'name' => 'Inventario', 'frontend_route' => null, 'icon' => 'bx-box', 'sort' => 100],
            ['slug' => 'inventory_items', 'name' => 'Bienes', 'frontend_route' => '/inventory/items', 'icon' => null, 'sort' => 1, 'parent' => 'inventory'],
            ['slug' => 'inventory_management', 'name' => 'Gestión de inventario', 'frontend_route' => '/inventory/management', 'icon' => null, 'sort' => 2, 'parent' => 'inventory'],
            ['slug' => 'inventory_categories', 'name' => 'Categorías', 'frontend_route' => '/inventory/categories', 'icon' => null, 'sort' => 3, 'parent' => 'inventory'],
            ['slug' => 'inventory_suppliers', 'name' => 'Proveedores', 'frontend_route' => '/inventory/suppliers', 'icon' => null, 'sort' => 4, 'parent' => 'inventory'],
            ['slug' => 'inventory_reports', 'name' => 'Reportes', 'frontend_route' => '/inventory/reports', 'icon' => null, 'sort' => 5, 'parent' => 'inventory'],
            ['slug' => 'inventory_labels', 'name' => 'Etiquetas', 'frontend_route' => '/inventory/labels', 'icon' => null, 'sort' => 6, 'parent' => 'inventory'],
            ['slug' => 'reports', 'name' => 'Reportes', 'frontend_route' => '/reports', 'icon' => 'bx-bar-chart', 'sort' => 110],
            ['slug' => 'public_site', 'name' => 'Sitio web', 'frontend_route' => null, 'icon' => 'bx-globe', 'sort' => 119],
            ['slug' => 'public_site_news', 'name' => 'Noticias', 'frontend_route' => '/admin/noticias', 'icon' => null, 'sort' => 1, 'parent' => 'public_site'],
            ['slug' => 'public_site_events', 'name' => 'Eventos', 'frontend_route' => '/admin/eventos', 'icon' => null, 'sort' => 2, 'parent' => 'public_site'],
            ['slug' => 'public_site_contacts', 'name' => 'Contactos', 'frontend_route' => '/admin/contactos', 'icon' => null, 'sort' => 3, 'parent' => 'public_site'],
            ['slug' => 'spaces', 'name' => 'Dependencias y Reservas', 'frontend_route' => null, 'icon' => 'bx-calendar-event', 'sort' => 115],
            ['slug' => 'spaces_dependencies', 'name' => 'Dependencias', 'frontend_route' => '/spaces/dependencies', 'icon' => null, 'sort' => 1, 'parent' => 'spaces'],
            ['slug' => 'spaces_dependency_types', 'name' => 'Tipos de dependencia', 'frontend_route' => '/spaces/dependency-types', 'icon' => null, 'sort' => 2, 'parent' => 'spaces'],
            ['slug' => 'spaces_approvers', 'name' => 'Gestores', 'frontend_route' => '/spaces/approvers', 'icon' => null, 'sort' => 3, 'parent' => 'spaces'],
            ['slug' => 'spaces_reservations', 'name' => 'Reservas', 'frontend_route' => '/spaces/reservations', 'icon' => null, 'sort' => 4, 'parent' => 'spaces'],
            ['slug' => 'spaces_calendar', 'name' => 'Calendario', 'frontend_route' => '/spaces/calendar', 'icon' => null, 'sort' => 5, 'parent' => 'spaces'],
            ['slug' => 'spaces_statistics', 'name' => 'Estadísticas', 'frontend_route' => '/spaces/statistics', 'icon' => null, 'sort' => 6, 'parent' => 'spaces'],
            ['slug' => 'security', 'name' => 'Control de Nochero', 'frontend_route' => null, 'icon' => 'bx-shield-quarter', 'sort' => 117],
            ['slug' => 'security_dashboard', 'name' => 'Panel de rondas', 'frontend_route' => '/security/dashboard', 'icon' => null, 'sort' => 1, 'parent' => 'security'],
            ['slug' => 'security_shifts', 'name' => 'Turnos y rondas', 'frontend_route' => '/security/shifts', 'icon' => null, 'sort' => 2, 'parent' => 'security'],
            ['slug' => 'security_incidents', 'name' => 'Novedades pendientes', 'frontend_route' => '/security/incidents', 'icon' => null, 'sort' => 3, 'parent' => 'security'],

            // Configuración (padre + submódulos)
            ['slug' => 'settings', 'name' => 'Configuración', 'frontend_route' => null, 'icon' => 'bx-cog', 'sort' => 120],
            ['slug' => 'settings_superadmin_dashboard', 'name' => 'Dashboard gestión', 'frontend_route' => '/admin/dashboard', 'icon' => null, 'sort' => 0, 'parent' => 'settings'],
            ['slug' => 'settings_users', 'name' => 'Usuarios', 'frontend_route' => '/admin/users', 'icon' => null, 'sort' => 1, 'parent' => 'settings'],
            ['slug' => 'settings_roles', 'name' => 'Roles', 'frontend_route' => '/admin/roles', 'icon' => null, 'sort' => 2, 'parent' => 'settings'],
            ['slug' => 'settings_permissions', 'name' => 'Permisos', 'frontend_route' => '/admin/permissions', 'icon' => null, 'sort' => 3, 'parent' => 'settings'],
            ['slug' => 'settings_modules', 'name' => 'Módulos', 'frontend_route' => '/admin/modules', 'icon' => null, 'sort' => 4, 'parent' => 'settings'],
            ['slug' => 'settings_cargos', 'name' => 'Cargos', 'frontend_route' => '/admin/cargos', 'icon' => null, 'sort' => 5, 'parent' => 'settings'],
            ['slug' => 'settings_organigram', 'name' => 'Organigrama', 'frontend_route' => '/admin/organigram', 'icon' => null, 'sort' => 6, 'parent' => 'settings'],
        ];

        $existing = SystemModule::query()->get()->keyBy('slug');

        foreach ($modules as $module) {
            $parentId = null;
            if (! empty($module['parent'])) {
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
            ['slug' => 'rrhh', 'name' => 'RRHH / Administración', 'description' => 'Gestión administrativa y documental del personal.'],
            ['slug' => 'direccion', 'name' => 'Dirección', 'description' => 'Dirección del establecimiento.'],
            ['slug' => 'coordinador_academico', 'name' => 'Coordinador Académico', 'description' => 'Coordinación académica.'],
            ['slug' => 'psicologo', 'name' => 'Psicólogo/a', 'description' => 'Acceso a módulo Psicología.'],
            ['slug' => 'enfermeria', 'name' => 'Enfermería', 'description' => 'Acceso a módulo Enfermería.'],
            ['slug' => 'prevencion_riesgos', 'name' => 'Prevención de Riesgos', 'description' => 'Riesgos + Mantención (críticas/reportes).'],
            ['slug' => 'inspectoria', 'name' => 'Inspectoría', 'description' => 'Inspectoría.'],
            ['slug' => 'porteria', 'name' => 'Portería', 'description' => 'Operación diaria de portería.'],
            ['slug' => 'encargado_mantencion', 'name' => 'Encargado de Mantención', 'description' => 'Mantención: OT + visitas + exportaciones.'],
            ['slug' => 'nochero', 'name' => 'Nochero', 'description' => 'Registro de rondas nocturnas.'],
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
            'ver_estudiantes',
            'crear_estudiantes',
            'editar_estudiantes',
            'ver_ficha_estudiante',
            'administrar_anos_academicos',
            'administrar_cursos_academicos',
            'gestionar_matriculas_estudiantes',
            'promover_estudiantes',
            'ver_horarios',
            'editar_horarios',
            'configurar_horarios',
            'configurar_jornadas',
            'configurar_plan_estudio',
            'configurar_contratos_docentes',
            'forzar_excepciones_horario',
            'ver_reportes_carga_horaria',
            'ver_porteria',
            'registrar_retiro_porteria',
            'autorizar_retiros_porteria',
            'registrar_objetos_porteria',
            'entregar_objetos_porteria',
            'registrar_mercaderia_porteria',
            'entregar_mercaderia_porteria',
            'registrar_visitas_porteria',
            'registrar_proveedores_porteria',
            'registrar_bitacora_porteria',
            'gestionar_llaves_porteria',
            'ver_historial_porteria',
            'exportar_reportes_porteria',
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

            'ver_inventario',
            'crear_inventario',
            'editar_inventario',
            // 'eliminar_inventario', // reservado para Super Admin
            'dar_baja_inventario',
            'mover_inventario',
            'ver_reportes_inventario',
            'exportar_inventario',
            'administrar_categorias_inventario',
            'subir_documentos_inventario',
            'eliminar_documentos_inventario',
            'imprimir_etiquetas_inventario',

            'administrar_usuarios',
            'administrar_roles',
            'administrar_permisos',
            'administrar_modulos',
            'administrar_cargos',
            'administrar_organigrama',
            'ver_noticias',
            'gestionar_noticias',
            'ver_eventos',
            'gestionar_eventos',
            'ver_contactos_sitio',
            'gestionar_contactos_sitio',
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
            'ver_funcionarios',
            'gestionar_funcionarios',
            'eliminar_funcionarios',
            'administrar_departamentos',
            'subir_documentos_funcionarios',
            'ver_permisos_personal',
            'solicitar_permisos_personal',
            'revisar_permisos_equipo',
            'aprobar_permisos_direccion',
            'revisar_permisos_rrhh',
            'administrar_tipos_permisos_personal',
            'administrar_destinatarios_permisos_personal',
            'exportar_permisos_personal',
            'validar_documentos_permisos_personal',
            'gestionar_reemplazos_permisos_personal',
            'ver_reportes_permisos_personal',
            'ver_tareas',
            'gestionar_tareas',
            'ver_tareas_equipo',
            'administrar_asignadores_tareas',
            'ver_contratos',
            'gestionar_contratos',
            'eliminar_contratos',
            'exportar_contratos',
            'administrar_plantillas_contrato',
            'administrar_clausulas_contrato',
            'administrar_firmas_contrato',
            'editar_contratos_firmados',
            'ver_dependencias',
            'crear_dependencias',
            'editar_dependencias',
            'eliminar_dependencias',
            'ver_reservas',
            'crear_reservas',
            'editar_reservas',
            'cancelar_reservas',
            'aprobar_reservas',
            'rechazar_reservas',
            'exportar_reservas',
            'administrar_calendario',
            'ver_estadisticas_espacios',
            'exportar_estadisticas_espacios',
            'ver_rondas_seguridad',
            'gestionar_turnos_nochero',
            'registrar_rondas_seguridad',
            'gestionar_novedades_rondas',
            'exportar_rondas_seguridad',
            'ver_horarios',
            'ver_reportes_carga_horaria',
        ]));

        $rolesBySlug['administrador']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'students_directory',
            'students_levels',
            'students_academic_years',
            'students_courses',
            'students_promotions',
            'students_movements',
            'schedule',
            'schedule_teacher',
            'schedule_course',
            'schedule_config',
            'schedule_jornadas',
            'schedule_study_plans',
            'schedule_contracts',
            'schedule_conflicts',
            'porter',
            'porter_dashboard',
            'porter_students',
            'porter_withdrawals',
            'porter_received_items',
            'porter_goods',
            'porter_visits',
            'porter_providers',
            'porter_daily_log',
            'porter_keys',
            'porter_reports',
            'staff',
            'staff_directory',
            'staff_departments',
            'staff_permissions',
            'staff_permissions_dashboard',
            'staff_permissions_requests',
            'staff_permissions_review',
            'staff_permissions_reports',
            'staff_permissions_types',
            'staff_permissions_watchers',
            'tasks',
            'tasks_backlog',
            'tasks_assigners',
            'internal_communications',
            'contracts',
            'contracts_list',
            'contracts_templates',
            'contracts_clauses',
            'contracts_signatures',
            'maintenance',
            'maintenance_dependencies',
            'maintenance_work_orders',
            'maintenance_workload',
            'maintenance_visits',
            'maintenance_annual_plans',
            'inventory',
            'inventory_items',
            'inventory_management',
            'inventory_categories',
            'inventory_suppliers',
            'inventory_reports',
            'inventory_labels',
            'spaces',
            'spaces_dependencies',
            'spaces_dependency_types',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'spaces_statistics',
            'security',
            'security_dashboard',
            'security_shifts',
            'security_incidents',
            'reports',
            'public_site',
            'public_site_news',
            'public_site_events',
            'public_site_contacts',
            'settings',
            'settings_users',
            'settings_roles',
            'settings_permissions',
            'settings_modules',
            'settings_cargos',
            'settings_organigram',
        ]));

        // Dirección: reportes + mantención lectura
        $rolesBySlug['direccion']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_reportes',
            'ver_noticias',
            'gestionar_noticias',
            'ver_eventos',
            'gestionar_eventos',
            'ver_contactos_sitio',
            'gestionar_contactos_sitio',
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
            'ver_funcionarios',
            'ver_porteria',
            'registrar_visitas_porteria',
            'registrar_proveedores_porteria',
            'registrar_bitacora_porteria',
            'gestionar_llaves_porteria',
            'autorizar_retiros_porteria',
            'ver_historial_porteria',
            'exportar_reportes_porteria',
            'ver_permisos_personal',
            'solicitar_permisos_personal',
            'aprobar_permisos_direccion',
            'exportar_permisos_personal',
            'validar_documentos_permisos_personal',
            'gestionar_reemplazos_permisos_personal',
            'ver_reportes_permisos_personal',
            'ver_tareas',
            'gestionar_tareas',
            'ver_tareas_equipo',
            'administrar_asignadores_tareas',
            'ver_contratos',
            'ver_mantencion',
            'ver_reportes_mantencion',
            'ver_inventario',
            'ver_reportes_inventario',
            'exportar_inventario',
            'imprimir_etiquetas_inventario',
            'ver_dependencias',
            'ver_reservas',
            'aprobar_reservas',
            'rechazar_reservas',
            'exportar_reservas',
            'ver_estadisticas_espacios',
            'exportar_estadisticas_espacios',
            'ver_rondas_seguridad',
            'gestionar_novedades_rondas',
            'exportar_rondas_seguridad',
            'ver_horarios',
            'ver_reportes_carga_horaria',
        ]));

        $rolesBySlug['direccion']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'porter',
            'porter_dashboard',
            'porter_students',
            'porter_withdrawals',
            'porter_received_items',
            'porter_goods',
            'porter_visits',
            'porter_providers',
            'porter_daily_log',
            'porter_keys',
            'porter_reports',
            'staff',
            'staff_directory',
            'staff_permissions',
            'staff_permissions_dashboard',
            'staff_permissions_requests',
            'staff_permissions_review',
            'staff_permissions_reports',
            'tasks',
            'tasks_backlog',
            'tasks_assigners',
            'internal_communications',
            'contracts',
            'contracts_list',
            'maintenance',
            'maintenance_work_orders',
            'maintenance_workload',
            'maintenance_visits',
            'maintenance_annual_plans',
            'inventory',
            'inventory_items',
            'inventory_management',
            'inventory_reports',
            'inventory_labels',
            'spaces',
            'spaces_dependencies',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'spaces_statistics',
            'security',
            'security_dashboard',
            'security_incidents',
            'reports',
            'public_site',
            'public_site_news',
            'public_site_events',
            'public_site_contacts',
            'schedule',
            'schedule_teacher',
            'schedule_course',
            'schedule_conflicts',
        ]));

        // Coordinación académica
        $rolesBySlug['coordinador_academico']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
            'ver_funcionarios',
            'ver_estudiantes',
            'ver_ficha_estudiante',
            'ver_porteria',
            'registrar_bitacora_porteria',
            'autorizar_retiros_porteria',
            'ver_historial_porteria',
            'ver_permisos_personal',
            'solicitar_permisos_personal',
            'revisar_permisos_equipo',
            'gestionar_reemplazos_permisos_personal',
            'ver_tareas',
            'gestionar_tareas',
            'ver_tareas_equipo',
            'administrar_asignadores_tareas',
            'ver_dependencias',
            'ver_reservas',
            'crear_reservas',
            'editar_reservas',
            'cancelar_reservas',
            'aprobar_reservas',
            'rechazar_reservas',
            'exportar_reservas',
            'administrar_calendario',
            'ver_estadisticas_espacios',
            'exportar_estadisticas_espacios',
            'ver_horarios',
            'editar_horarios',
            'configurar_horarios',
            'configurar_jornadas',
            'configurar_plan_estudio',
            'configurar_contratos_docentes',
            'forzar_excepciones_horario',
            'ver_reportes_carga_horaria',
        ]));

        $rolesBySlug['coordinador_academico']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'students_directory',
            'students_levels',
            'students_courses',
            'porter',
            'porter_dashboard',
            'porter_students',
            'porter_withdrawals',
            'porter_daily_log',
            'porter_reports',
            'staff',
            'staff_directory',
            'staff_permissions',
            'staff_permissions_dashboard',
            'staff_permissions_requests',
            'staff_permissions_review',
            'tasks',
            'tasks_backlog',
            'tasks_assigners',
            'internal_communications',
            'spaces',
            'spaces_dependencies',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'spaces_statistics',
            'schedule',
            'schedule_teacher',
            'schedule_course',
            'schedule_config',
            'schedule_jornadas',
            'schedule_study_plans',
            'schedule_contracts',
            'schedule_conflicts',
        ]));

        // RRHH / Administración
        $rolesBySlug['rrhh']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
            'ver_estudiantes',
            'crear_estudiantes',
            'editar_estudiantes',
            'ver_ficha_estudiante',
            'administrar_anos_academicos',
            'administrar_cursos_academicos',
            'gestionar_matriculas_estudiantes',
            'promover_estudiantes',
            'ver_porteria',
            'registrar_visitas_porteria',
            'registrar_proveedores_porteria',
            'registrar_bitacora_porteria',
            'gestionar_llaves_porteria',
            'autorizar_retiros_porteria',
            'ver_historial_porteria',
            'exportar_reportes_porteria',
            'ver_funcionarios',
            'ver_permisos_personal',
            'solicitar_permisos_personal',
            'revisar_permisos_rrhh',
            'administrar_tipos_permisos_personal',
            'administrar_destinatarios_permisos_personal',
            'exportar_permisos_personal',
            'validar_documentos_permisos_personal',
            'gestionar_reemplazos_permisos_personal',
            'ver_reportes_permisos_personal',
            'ver_tareas',
            'gestionar_tareas',
            'ver_tareas_equipo',
            'administrar_asignadores_tareas',
            'ver_rondas_seguridad',
            'gestionar_turnos_nochero',
            'gestionar_novedades_rondas',
            'exportar_rondas_seguridad',
            'ver_horarios',
            'configurar_contratos_docentes',
            'ver_reportes_carga_horaria',
        ]));

        $rolesBySlug['rrhh']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'students_directory',
            'students_levels',
            'students_academic_years',
            'students_courses',
            'students_promotions',
            'students_movements',
            'porter',
            'porter_dashboard',
            'porter_students',
            'porter_withdrawals',
            'porter_received_items',
            'porter_goods',
            'porter_visits',
            'porter_providers',
            'porter_daily_log',
            'porter_keys',
            'porter_reports',
            'staff',
            'staff_directory',
            'staff_permissions',
            'staff_permissions_dashboard',
            'staff_permissions_requests',
            'staff_permissions_review',
            'staff_permissions_reports',
            'staff_permissions_types',
            'staff_permissions_watchers',
            'tasks',
            'tasks_backlog',
            'tasks_assigners',
            'internal_communications',
            'security',
            'security_dashboard',
            'security_shifts',
            'security_incidents',
            'reports',
            'schedule',
            'schedule_teacher',
            'schedule_course',
            'schedule_contracts',
            'schedule_conflicts',
        ]));

        // Psicología
        $rolesBySlug['psicologo']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_estudiantes',
            'ver_ficha_estudiante',
            'ver_psicologia',
            'ver_tareas',
            'gestionar_tareas',
        ]));

        $rolesBySlug['psicologo']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'students_directory',
            'psychology',
            'tasks',
            'tasks_backlog',
        ]));

        // Enfermería
        $rolesBySlug['enfermeria']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_estudiantes',
            'ver_ficha_estudiante',
            'ver_salud',
            'ver_tareas',
            'gestionar_tareas',
        ]));

        $rolesBySlug['enfermeria']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'students_directory',
            'infirmary',
            'tasks',
            'tasks_backlog',
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

            'ver_inventario',
            'crear_inventario',
            'editar_inventario',
            'dar_baja_inventario',
            'mover_inventario',
            'ver_reportes_inventario',
            'exportar_inventario',
            'administrar_categorias_inventario',
            'subir_documentos_inventario',
            'imprimir_etiquetas_inventario',
            'ver_dependencias',
            'crear_dependencias',
            'editar_dependencias',
            'ver_reservas',
            'crear_reservas',
            'editar_reservas',
            'cancelar_reservas',
            'aprobar_reservas',
            'rechazar_reservas',
            'exportar_reservas',
            'administrar_calendario',
            'ver_estadisticas_espacios',
            'exportar_estadisticas_espacios',
            'ver_rondas_seguridad',
            'gestionar_novedades_rondas',
            'exportar_rondas_seguridad',
            'ver_tareas',
            'gestionar_tareas',
        ]));

        $rolesBySlug['encargado_mantencion']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'maintenance',
            'maintenance_dependencies',
            'maintenance_work_orders',
            'maintenance_workload',
            'maintenance_visits',
            'maintenance_annual_plans',
            'inventory',
            'inventory_items',
            'inventory_management',
            'inventory_categories',
            'inventory_suppliers',
            'inventory_reports',
            'inventory_labels',
            'spaces',
            'spaces_dependencies',
            'spaces_dependency_types',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'spaces_statistics',
            'security',
            'security_dashboard',
            'security_incidents',
            'tasks',
            'tasks_backlog',
        ]));

        // Prevención de Riesgos (lectura + reportes)
        $rolesBySlug['prevencion_riesgos']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_prevencion_riesgos',
            'ver_mantencion',
            'ver_reportes_mantencion',
            'ver_reportes',
            'ver_dependencias',
            'ver_reservas',
            'crear_reservas',
            'cancelar_reservas',
            'ver_estadisticas_espacios',
            'ver_rondas_seguridad',
            'gestionar_novedades_rondas',
            'exportar_rondas_seguridad',
            'ver_tareas',
            'gestionar_tareas',
        ]));

        $rolesBySlug['prevencion_riesgos']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'risk_prevention',
            'spaces',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'spaces_statistics',
            'maintenance',
            'maintenance_work_orders',
            'maintenance_workload',
            'security',
            'security_dashboard',
            'security_incidents',
            'reports',
            'tasks',
            'tasks_backlog',
        ]));

        // Inspectoría
        $rolesBySlug['inspectoria']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_estudiantes',
            'ver_ficha_estudiante',
            'ver_funcionarios',
            'ver_comunicaciones_internas',
            'gestionar_comunicaciones_internas',
            'ver_porteria',
            'registrar_retiro_porteria',
            'registrar_visitas_porteria',
            'registrar_proveedores_porteria',
            'registrar_bitacora_porteria',
            'gestionar_llaves_porteria',
            'autorizar_retiros_porteria',
            'ver_historial_porteria',
            'ver_dependencias',
            'ver_reservas',
            'crear_reservas',
            'editar_reservas',
            'cancelar_reservas',
            'aprobar_reservas',
            'rechazar_reservas',
            'exportar_reservas',
            'ver_estadisticas_espacios',
            'exportar_estadisticas_espacios',
            'ver_rondas_seguridad',
            'gestionar_novedades_rondas',
            'ver_tareas',
            'gestionar_tareas',
            'ver_horarios',
            'ver_reportes_carga_horaria',
        ]));

        $rolesBySlug['inspectoria']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'students',
            'students_directory',
            'students_courses',
            'porter',
            'porter_dashboard',
            'porter_students',
            'porter_withdrawals',
            'porter_visits',
            'porter_providers',
            'porter_daily_log',
            'porter_keys',
            'porter_reports',
            'staff',
            'staff_directory',
            'spaces',
            'spaces_dependencies',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'spaces_statistics',
            'security',
            'security_dashboard',
            'security_incidents',
            'tasks',
            'tasks_backlog',
            'internal_communications',
            'schedule',
            'schedule_teacher',
            'schedule_course',
            'schedule_conflicts',
        ]));

        // Docente
        $rolesBySlug['docente']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_permisos_personal',
            'solicitar_permisos_personal',
            'ver_dependencias',
            'ver_reservas',
            'crear_reservas',
            'editar_reservas',
            'cancelar_reservas',
            'ver_tareas',
            'gestionar_tareas',
            'ver_horarios',
            'ver_reportes_carga_horaria',
        ]));

        $rolesBySlug['docente']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'staff_permissions',
            'staff_permissions_requests',
            'spaces',
            'spaces_approvers',
            'spaces_reservations',
            'spaces_calendar',
            'tasks',
            'tasks_backlog',
            'schedule',
            'schedule_teacher',
        ]));

        // Portería
        $rolesBySlug['porteria']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_porteria',
            'registrar_retiro_porteria',
            'registrar_objetos_porteria',
            'entregar_objetos_porteria',
            'registrar_mercaderia_porteria',
            'entregar_mercaderia_porteria',
            'registrar_visitas_porteria',
            'registrar_proveedores_porteria',
            'registrar_bitacora_porteria',
            'gestionar_llaves_porteria',
            'ver_historial_porteria',
            'ver_tareas',
            'gestionar_tareas',
        ]));

        $rolesBySlug['porteria']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'porter',
            'porter_dashboard',
            'porter_students',
            'porter_withdrawals',
            'porter_received_items',
            'porter_goods',
            'porter_visits',
            'porter_providers',
            'porter_daily_log',
            'porter_keys',
            'porter_reports',
            'tasks',
            'tasks_backlog',
        ]));

        // Nochero
        $rolesBySlug['nochero']->permissions()->sync($this->ids($permissionsBySlug, [
            'ver_dashboard',
            'ver_rondas_seguridad',
            'registrar_rondas_seguridad',
            'ver_tareas',
            'gestionar_tareas',
        ]));

        $rolesBySlug['nochero']->modules()->sync($this->ids($modulesBySlug, [
            'dashboard',
            'security',
            'security_dashboard',
            'security_shifts',
            'tasks',
            'tasks_backlog',
        ]));
    }

    /**
     * @param  Collection<string, Model>  $collection
     * @param  array<int, string>  $slugs
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
