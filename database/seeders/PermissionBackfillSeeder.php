<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = [];

        foreach ($this->permissions() as $permission) {
            $model = Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'active' => true,
                ],
            );

            $permissionIds[] = $model->id;
        }

        Role::query()
            ->where('slug', 'super_admin')
            ->first()
            ?->permissions()
            ->syncWithoutDetaching($permissionIds);
    }

    /**
     * @return array<int, array{slug:string,name:string,description:string}>
     */
    private function permissions(): array
    {
        return [
            // Estudiantes
            ['slug' => 'eliminar_estudiantes', 'name' => 'Eliminar Estudiantes', 'description' => 'Permite revisar el impacto y eliminar de forma definitiva la ficha de una estudiante junto con su cuenta y registros dependientes.'],

            // Funcionarios
            ['slug' => 'exportar_funcionarios', 'name' => 'Exportar Funcionarios', 'description' => 'Permite exportar listados y fichas de funcionarios desde el modulo de personal.'],

            // Enfermeria
            ['slug' => 'ver_enfermeria', 'name' => 'Ver modulo Enfermeria', 'description' => 'Permite acceder al dashboard, atenciones, ficha medica, inventario, accidentes y reportes de Enfermeria.'],
            ['slug' => 'crear_atenciones_enfermeria', 'name' => 'Crear atenciones de Enfermeria', 'description' => 'Permite registrar nuevas atenciones, llamados o controles asociados a estudiantes en Enfermeria.'],
            ['slug' => 'editar_atenciones_enfermeria', 'name' => 'Editar atenciones de Enfermeria', 'description' => 'Permite modificar atenciones, llamados y registros clinicos operativos del modulo Enfermeria.'],
            ['slug' => 'eliminar_atenciones_enfermeria', 'name' => 'Eliminar atenciones de Enfermeria', 'description' => 'Permite eliminar atenciones o registros operativos de Enfermeria cuando corresponde.'],
            ['slug' => 'exportar_enfermeria', 'name' => 'Exportar Enfermeria', 'description' => 'Permite descargar reportes o listados generados por el modulo Enfermeria.'],
            ['slug' => 'administrar_inventario_enfermeria', 'name' => 'Administrar inventario de Enfermeria', 'description' => 'Permite gestionar stock, movimientos y productos del inventario de Enfermeria.'],
            ['slug' => 'administrar_medicamentos_enfermeria', 'name' => 'Administrar medicamentos de Enfermeria', 'description' => 'Permite gestionar medicamentos, autorizaciones y administraciones dentro de Enfermeria.'],
            ['slug' => 'administrar_catalogos_enfermeria', 'name' => 'Administrar catalogos de Enfermeria', 'description' => 'Permite gestionar categorias y catalogos operativos del modulo Enfermeria.'],
            ['slug' => 'gestionar_accidentes_enfermeria', 'name' => 'Gestionar accidentes de Enfermeria', 'description' => 'Permite registrar, editar y dar seguimiento a accidentes escolares desde Enfermeria.'],
            ['slug' => 'ver_reportes_enfermeria', 'name' => 'Ver reportes de Enfermeria', 'description' => 'Permite consultar indicadores y reportes del modulo Enfermeria.'],

            // Calendario de fechas relevantes
            ['slug' => 'ver_calendario_fechas_relevantes', 'name' => 'Ver Calendario de Fechas Relevantes', 'description' => 'Permite acceder al calendario de fechas relevantes y ver eventos visibles para el usuario.'],
            ['slug' => 'ver_todo_calendario_fechas_relevantes', 'name' => 'Ver Todo el Calendario de Fechas Relevantes', 'description' => 'Permite ver todos los eventos del calendario de fechas relevantes, sin limitarse al departamento o responsable.'],
            ['slug' => 'gestionar_calendario_fechas_relevantes_departamento', 'name' => 'Gestionar Calendario por Departamento', 'description' => 'Permite crear y modificar eventos del calendario asociados a departamentos bajo responsabilidad del usuario.'],
            ['slug' => 'administrar_calendario_fechas_relevantes', 'name' => 'Administrar Calendario de Fechas Relevantes', 'description' => 'Permite administrar todos los eventos del calendario, incluyendo edicion y eliminacion global.'],
            ['slug' => 'administrar_tipos_calendario_fechas_relevantes', 'name' => 'Administrar Tipos del Calendario', 'description' => 'Permite crear, editar y activar tipos de proceso usados por el calendario de fechas relevantes.'],
            ['slug' => 'administrar_instituciones_calendario_fechas_relevantes', 'name' => 'Administrar Instituciones del Calendario', 'description' => 'Permite crear, editar y activar instituciones asociadas al calendario de fechas relevantes.'],
            ['slug' => 'exportar_calendario_fechas_relevantes', 'name' => 'Exportar Calendario de Fechas Relevantes', 'description' => 'Permite exportar eventos e informacion del calendario de fechas relevantes.'],

            // Prevencion de riesgos
            ['slug' => 'gestionar_prevencion_riesgos', 'name' => 'Gestionar Prevencion de Riesgos', 'description' => 'Permite crear, editar y administrar registros de extintores, accidentes, EPP, capacitaciones y documentos de Prevencion de Riesgos.'],
            ['slug' => 'exportar_prevencion_riesgos', 'name' => 'Exportar Prevencion de Riesgos', 'description' => 'Permite exportar reportes y listados del modulo Prevencion de Riesgos.'],

            // Biblioteca
            ['slug' => 'ver_modulo_biblioteca', 'name' => 'Ver modulo Biblioteca', 'description' => 'Permite acceder al modulo Biblioteca Escolar, dashboard, catalogo, prestamos, reservas y reportes visibles.'],
            ['slug' => 'crear_libros_biblioteca', 'name' => 'Crear libros de Biblioteca', 'description' => 'Permite crear nuevas obras o libros dentro del catalogo de Biblioteca.'],
            ['slug' => 'editar_libros_biblioteca', 'name' => 'Editar libros de Biblioteca', 'description' => 'Permite modificar obras, libros y datos bibliograficos existentes.'],
            ['slug' => 'eliminar_libros_biblioteca', 'name' => 'Eliminar libros de Biblioteca', 'description' => 'Permite eliminar obras o registros bibliograficos de Biblioteca.'],
            ['slug' => 'administrar_catalogo_biblioteca', 'name' => 'Administrar catalogo de Biblioteca', 'description' => 'Permite gestionar catalogos bibliograficos, materias, autores y configuracion del catalogo.'],
            ['slug' => 'administrar_inventario_biblioteca', 'name' => 'Administrar inventario de Biblioteca', 'description' => 'Permite administrar ejemplares, stock, ubicaciones y movimientos de inventario de Biblioteca.'],
            ['slug' => 'registrar_prestamos_biblioteca', 'name' => 'Registrar prestamos de Biblioteca', 'description' => 'Permite registrar prestamos de libros o recursos de Biblioteca.'],
            ['slug' => 'registrar_devoluciones_biblioteca', 'name' => 'Registrar devoluciones de Biblioteca', 'description' => 'Permite registrar devoluciones de prestamos y actualizar disponibilidad de ejemplares.'],
            ['slug' => 'renovar_prestamos_biblioteca', 'name' => 'Renovar prestamos de Biblioteca', 'description' => 'Permite extender o renovar prestamos activos de Biblioteca.'],
            ['slug' => 'gestionar_mora_biblioteca', 'name' => 'Gestionar mora de Biblioteca', 'description' => 'Permite revisar y gestionar atrasos, multas o alertas de prestamos vencidos.'],
            ['slug' => 'gestionar_reservas_biblioteca', 'name' => 'Gestionar reservas de Biblioteca', 'description' => 'Permite crear, aprobar, cancelar o administrar reservas de recursos de Biblioteca.'],
            ['slug' => 'gestionar_plan_lector_biblioteca', 'name' => 'Gestionar plan lector de Biblioteca', 'description' => 'Permite crear y administrar planes lectores, obras asignadas y seguimiento lector.'],
            ['slug' => 'gestionar_uso_espacios_biblioteca', 'name' => 'Gestionar uso de espacios de Biblioteca', 'description' => 'Permite registrar y administrar uso de salas, espacios o recursos de Biblioteca.'],
            ['slug' => 'ver_estadisticas_biblioteca', 'name' => 'Ver estadisticas de Biblioteca', 'description' => 'Permite consultar estadisticas e indicadores del modulo Biblioteca.'],
            ['slug' => 'exportar_reportes_biblioteca', 'name' => 'Exportar reportes de Biblioteca', 'description' => 'Permite exportar reportes, indicadores o listados del modulo Biblioteca.'],

            // Centro de Apuntes y Panol
            ['slug' => 'ver_modulo_centro_apuntes', 'name' => 'Ver modulo Centro de Apuntes', 'description' => 'Permite acceder al Centro de Apuntes y Panol, dashboard, solicitudes, insumos, entregas y reportes visibles.'],
            ['slug' => 'crear_solicitud_impresion', 'name' => 'Crear solicitud de impresion', 'description' => 'Permite crear solicitudes de impresion o produccion en Centro de Apuntes.'],
            ['slug' => 'editar_solicitud_impresion', 'name' => 'Editar solicitud de impresion', 'description' => 'Permite modificar solicitudes de impresion antes o durante su gestion operativa.'],
            ['slug' => 'eliminar_solicitud_impresion', 'name' => 'Eliminar solicitud de impresion', 'description' => 'Permite eliminar solicitudes de impresion del Centro de Apuntes.'],
            ['slug' => 'cambiar_estado_solicitud_impresion', 'name' => 'Cambiar estado de solicitud de impresion', 'description' => 'Permite avanzar, pausar, completar o cambiar estados de solicitudes de impresion.'],
            ['slug' => 'registrar_entrega_centro_apuntes', 'name' => 'Registrar entrega Centro de Apuntes', 'description' => 'Permite registrar entregas de trabajos o materiales desde Centro de Apuntes.'],
            ['slug' => 'administrar_asignaturas_centro_apuntes', 'name' => 'Administrar asignaturas Centro de Apuntes', 'description' => 'Permite crear y editar asignaturas usadas para clasificar solicitudes del Centro de Apuntes.'],
            ['slug' => 'administrar_maquinas_centro_apuntes', 'name' => 'Administrar maquinas Centro de Apuntes', 'description' => 'Permite administrar maquinas, impresoras o equipos operativos del Centro de Apuntes.'],
            ['slug' => 'administrar_inventario_panol', 'name' => 'Administrar inventario Panol', 'description' => 'Permite administrar insumos, stock y catalogo del Panol.'],
            ['slug' => 'registrar_movimientos_panol', 'name' => 'Registrar movimientos Panol', 'description' => 'Permite registrar ingresos, salidas y ajustes de stock del Panol.'],
            ['slug' => 'aprobar_entregas_panol', 'name' => 'Aprobar entregas Panol', 'description' => 'Permite aprobar, rechazar o cerrar entregas de materiales del Panol.'],
            ['slug' => 'ver_reportes_centro_apuntes', 'name' => 'Ver reportes Centro de Apuntes', 'description' => 'Permite consultar reportes e indicadores del Centro de Apuntes y Panol.'],
            ['slug' => 'exportar_reportes_centro_apuntes', 'name' => 'Exportar reportes Centro de Apuntes', 'description' => 'Permite exportar reportes del Centro de Apuntes y Panol.'],

            // PME / SEP
            ['slug' => 'ver_modulo_pme', 'name' => 'Ver modulo PME / SEP', 'description' => 'Permite acceder al modulo PME / SEP y consultar dashboard, estudiantes, acciones, evidencias y reportes visibles.'],
            ['slug' => 'crear_pme', 'name' => 'Crear PME', 'description' => 'Permite crear planes PME dentro del modulo PME / SEP.'],
            ['slug' => 'editar_pme', 'name' => 'Editar PME', 'description' => 'Permite editar datos generales, etapas o configuracion de planes PME.'],
            ['slug' => 'cerrar_pme', 'name' => 'Cerrar PME', 'description' => 'Permite cerrar o finalizar planes PME.'],
            ['slug' => 'administrar_ingresos_sep', 'name' => 'Administrar ingresos SEP', 'description' => 'Permite gestionar ingresos, recursos y montos asociados a SEP.'],
            ['slug' => 'ver_estudiantes_prioritarios_sep', 'name' => 'Ver estudiantes prioritarios SEP', 'description' => 'Permite consultar estudiantes clasificados como prioritarios SEP.'],
            ['slug' => 'ver_estudiantes_preferentes_sep', 'name' => 'Ver estudiantes preferentes SEP', 'description' => 'Permite consultar estudiantes clasificados como preferentes SEP.'],
            ['slug' => 'cargar_estudiantes_sep', 'name' => 'Cargar estudiantes SEP', 'description' => 'Permite importar o cargar clasificaciones de estudiantes SEP.'],
            ['slug' => 'crear_objetivos_pme', 'name' => 'Crear objetivos PME', 'description' => 'Permite crear objetivos estrategicos del PME.'],
            ['slug' => 'editar_objetivos_pme', 'name' => 'Editar objetivos PME', 'description' => 'Permite editar objetivos estrategicos del PME.'],
            ['slug' => 'crear_estrategias_pme', 'name' => 'Crear estrategias PME', 'description' => 'Permite crear estrategias asociadas a objetivos PME.'],
            ['slug' => 'editar_estrategias_pme', 'name' => 'Editar estrategias PME', 'description' => 'Permite editar estrategias asociadas a objetivos PME.'],
            ['slug' => 'crear_indicadores_pme', 'name' => 'Crear indicadores PME', 'description' => 'Permite crear indicadores de seguimiento PME.'],
            ['slug' => 'medir_indicadores_pme', 'name' => 'Medir indicadores PME', 'description' => 'Permite registrar mediciones y avances de indicadores PME.'],
            ['slug' => 'crear_acciones_pme', 'name' => 'Crear acciones PME', 'description' => 'Permite crear acciones del plan PME.'],
            ['slug' => 'editar_acciones_pme', 'name' => 'Editar acciones PME', 'description' => 'Permite editar acciones, responsables, fechas y datos operativos del PME.'],
            ['slug' => 'cerrar_acciones_pme', 'name' => 'Cerrar acciones PME', 'description' => 'Permite cerrar acciones PME y dejar registro de cumplimiento.'],
            ['slug' => 'crear_evidencias_pme', 'name' => 'Crear evidencias PME', 'description' => 'Permite cargar o registrar evidencias asociadas a acciones PME.'],
            ['slug' => 'revisar_evidencias_pme', 'name' => 'Revisar evidencias PME', 'description' => 'Permite revisar evidencias cargadas y registrar observaciones.'],
            ['slug' => 'aprobar_evidencias_pme', 'name' => 'Aprobar evidencias PME', 'description' => 'Permite aprobar evidencias de cumplimiento de acciones PME.'],
            ['slug' => 'rechazar_evidencias_pme', 'name' => 'Rechazar evidencias PME', 'description' => 'Permite rechazar evidencias PME y solicitar correcciones.'],
            ['slug' => 'crear_hitos_pme', 'name' => 'Crear hitos PME', 'description' => 'Permite crear hitos o actividades relevantes de seguimiento PME.'],
            ['slug' => 'registrar_monitoreo_reflexivo_pme', 'name' => 'Registrar monitoreo reflexivo PME', 'description' => 'Permite registrar monitoreo reflexivo, avances y analisis de implementacion PME.'],
            ['slug' => 'ver_reportes_pme', 'name' => 'Ver reportes PME', 'description' => 'Permite consultar reportes e indicadores del modulo PME / SEP.'],
            ['slug' => 'exportar_reportes_pme', 'name' => 'Exportar reportes PME', 'description' => 'Permite exportar reportes del modulo PME / SEP.'],
            ['slug' => 'administrar_configuracion_pme', 'name' => 'Administrar configuracion PME', 'description' => 'Permite administrar configuraciones, dimensiones y catalogos del modulo PME / SEP.'],

            // Apoyo profesional
            ['slug' => 'ver_modulo_apoyo_profesional', 'name' => 'Ver modulo Apoyo Profesional', 'description' => 'Permite acceder al modulo Equipo de Apoyo, atenciones, derivaciones, seguimientos, planes, entrevistas y reportes visibles.'],
            ['slug' => 'crear_atencion_apoyo_profesional', 'name' => 'Crear atencion Apoyo Profesional', 'description' => 'Permite registrar nuevas atenciones profesionales de estudiantes.'],
            ['slug' => 'editar_atencion_propia_apoyo_profesional', 'name' => 'Editar atencion propia Apoyo Profesional', 'description' => 'Permite editar atenciones profesionales creadas por el propio usuario.'],
            ['slug' => 'editar_cualquier_atencion_apoyo_profesional', 'name' => 'Editar cualquier atencion Apoyo Profesional', 'description' => 'Permite editar atenciones profesionales de cualquier integrante del equipo autorizado.'],
            ['slug' => 'eliminar_atencion_apoyo_profesional', 'name' => 'Eliminar atencion Apoyo Profesional', 'description' => 'Permite eliminar registros de atencion profesional.'],
            ['slug' => 'ver_atenciones_propias_apoyo_profesional', 'name' => 'Ver atenciones propias Apoyo Profesional', 'description' => 'Permite consultar atenciones profesionales propias.'],
            ['slug' => 'ver_atenciones_equipo_apoyo_profesional', 'name' => 'Ver atenciones de equipo Apoyo Profesional', 'description' => 'Permite consultar atenciones del equipo de apoyo profesional.'],
            ['slug' => 'ver_atenciones_confidenciales_apoyo_profesional', 'name' => 'Ver atenciones confidenciales Apoyo Profesional', 'description' => 'Permite consultar atenciones marcadas como confidenciales dentro del equipo autorizado.'],
            ['slug' => 'crear_derivacion_apoyo_profesional', 'name' => 'Crear derivacion Apoyo Profesional', 'description' => 'Permite crear derivaciones internas hacia profesionales o areas de apoyo.'],
            ['slug' => 'responder_derivacion_apoyo_profesional', 'name' => 'Responder derivacion Apoyo Profesional', 'description' => 'Permite aceptar, responder o cerrar derivaciones recibidas por el equipo de apoyo.'],
            ['slug' => 'crear_seguimiento_apoyo_profesional', 'name' => 'Crear seguimiento Apoyo Profesional', 'description' => 'Permite registrar seguimientos de atenciones, planes o casos de apoyo profesional.'],
            ['slug' => 'cerrar_caso_apoyo_profesional', 'name' => 'Cerrar caso Apoyo Profesional', 'description' => 'Permite cerrar casos o procesos de apoyo profesional.'],
            ['slug' => 'crear_plan_apoyo_profesional', 'name' => 'Crear plan Apoyo Profesional', 'description' => 'Permite crear planes de apoyo para estudiantes.'],
            ['slug' => 'ver_reportes_apoyo_profesional', 'name' => 'Ver reportes Apoyo Profesional', 'description' => 'Permite consultar reportes del modulo Apoyo Profesional.'],
            ['slug' => 'exportar_reportes_apoyo_profesional', 'name' => 'Exportar reportes Apoyo Profesional', 'description' => 'Permite exportar reportes del modulo Apoyo Profesional.'],
            ['slug' => 'administrar_configuracion_apoyo_profesional', 'name' => 'Administrar configuracion Apoyo Profesional', 'description' => 'Permite administrar catalogos, motivos, tipos de atencion y configuracion del modulo Apoyo Profesional.'],

            // Convivencia
            ['slug' => 'ver_dashboard_convivencia', 'name' => 'Ver dashboard Convivencia', 'description' => 'Permite consultar el dashboard e indicadores generales de Convivencia Escolar.'],
            ['slug' => 'gestionar_plan_convivencia', 'name' => 'Gestionar plan Convivencia', 'description' => 'Permite crear y administrar planes de gestion de Convivencia Escolar.'],
            ['slug' => 'crear_casos_convivencia', 'name' => 'Crear casos Convivencia', 'description' => 'Permite crear casos de convivencia escolar.'],
            ['slug' => 'ver_casos_convivencia', 'name' => 'Ver casos Convivencia', 'description' => 'Permite consultar casos de convivencia escolar visibles para el usuario.'],
            ['slug' => 'editar_casos_convivencia', 'name' => 'Editar casos Convivencia', 'description' => 'Permite modificar casos, datos de seguimiento y antecedentes de convivencia.'],
            ['slug' => 'cerrar_casos_convivencia', 'name' => 'Cerrar casos Convivencia', 'description' => 'Permite cerrar casos de convivencia y registrar conclusion o resolucion.'],
            ['slug' => 'ver_casos_sensibles_convivencia', 'name' => 'Ver casos sensibles Convivencia', 'description' => 'Permite consultar casos marcados como sensibles o reservados en Convivencia Escolar.'],
            ['slug' => 'gestionar_denuncias_convivencia', 'name' => 'Gestionar denuncias Convivencia', 'description' => 'Permite registrar, revisar y convertir denuncias de convivencia.'],
            ['slug' => 'gestionar_protocolos_convivencia', 'name' => 'Gestionar protocolos Convivencia', 'description' => 'Permite crear, editar y administrar protocolos de actuacion de convivencia.'],
            ['slug' => 'activar_protocolos_convivencia', 'name' => 'Activar protocolos Convivencia', 'description' => 'Permite activar protocolos de convivencia sobre casos o denuncias.'],
            ['slug' => 'gestionar_entrevistas_convivencia', 'name' => 'Gestionar entrevistas Convivencia', 'description' => 'Permite registrar y administrar entrevistas del modulo Convivencia.'],
            ['slug' => 'gestionar_medidas_formativas_convivencia', 'name' => 'Gestionar medidas formativas Convivencia', 'description' => 'Permite crear y administrar medidas formativas asociadas a casos de convivencia.'],
            ['slug' => 'gestionar_derivaciones_internas_convivencia', 'name' => 'Gestionar derivaciones internas Convivencia', 'description' => 'Permite crear y administrar derivaciones internas del modulo Convivencia.'],
            ['slug' => 'gestionar_derivaciones_externas_convivencia', 'name' => 'Gestionar derivaciones externas Convivencia', 'description' => 'Permite crear y administrar derivaciones hacia instituciones externas.'],
            ['slug' => 'ver_sociogramas_convivencia', 'name' => 'Ver sociogramas Convivencia', 'description' => 'Permite consultar sociogramas y resultados del modulo Convivencia.'],
            ['slug' => 'gestionar_sociogramas_convivencia', 'name' => 'Gestionar sociogramas Convivencia', 'description' => 'Permite crear, editar y administrar sociogramas de convivencia.'],
            ['slug' => 'ver_reportes_curso_convivencia', 'name' => 'Ver reportes por curso Convivencia', 'description' => 'Permite consultar reportes de convivencia por curso.'],
            ['slug' => 'gestionar_bitacora_inspectoria_convivencia', 'name' => 'Gestionar bitacora Convivencia', 'description' => 'Permite registrar y administrar bitacora de inspectoria dentro de Convivencia.'],
            ['slug' => 'exportar_reportes_convivencia', 'name' => 'Exportar reportes Convivencia', 'description' => 'Permite exportar reportes e indicadores de Convivencia Escolar.'],
            ['slug' => 'administrar_configuraciones_convivencia', 'name' => 'Administrar configuraciones Convivencia', 'description' => 'Permite administrar catalogos, parametros e instituciones del modulo Convivencia.'],

            // Informatica
            ['slug' => 'informatica.ver', 'name' => 'Ver modulo Informatica', 'description' => 'Permite acceder al modulo Informatica y consultar recursos visibles.'],
            ['slug' => 'informatica.dashboard', 'name' => 'Ver dashboard Informatica', 'description' => 'Permite consultar el dashboard e indicadores del modulo Informatica.'],
            ['slug' => 'informatica.equipos.ver', 'name' => 'Ver equipos Informatica', 'description' => 'Permite consultar equipos, fichas y adjuntos de Informatica.'],
            ['slug' => 'informatica.equipos.crear', 'name' => 'Crear equipos Informatica', 'description' => 'Permite registrar nuevos equipos de Informatica.'],
            ['slug' => 'informatica.equipos.editar', 'name' => 'Editar equipos Informatica', 'description' => 'Permite modificar datos, estado y adjuntos de equipos de Informatica.'],
            ['slug' => 'informatica.equipos.eliminar', 'name' => 'Eliminar equipos Informatica', 'description' => 'Permite eliminar equipos de Informatica.'],
            ['slug' => 'informatica.prestamos.ver', 'name' => 'Ver prestamos Informatica', 'description' => 'Permite consultar prestamos de equipos de Informatica.'],
            ['slug' => 'informatica.prestamos.crear', 'name' => 'Crear prestamos Informatica', 'description' => 'Permite registrar prestamos de equipos de Informatica.'],
            ['slug' => 'informatica.prestamos.devolver', 'name' => 'Registrar devoluciones Informatica', 'description' => 'Permite registrar devoluciones de equipos prestados.'],
            ['slug' => 'informatica.prestamos.cancelar', 'name' => 'Cancelar prestamos Informatica', 'description' => 'Permite cancelar prestamos de equipos de Informatica.'],
            ['slug' => 'informatica.mantenciones.ver', 'name' => 'Ver mantenciones Informatica', 'description' => 'Permite consultar mantenciones de equipos de Informatica.'],
            ['slug' => 'informatica.mantenciones.crear', 'name' => 'Crear mantenciones Informatica', 'description' => 'Permite registrar mantenciones de equipos de Informatica.'],
            ['slug' => 'informatica.mantenciones.editar', 'name' => 'Editar mantenciones Informatica', 'description' => 'Permite modificar mantenciones de equipos de Informatica.'],
            ['slug' => 'informatica.mantenciones.cerrar', 'name' => 'Cerrar mantenciones Informatica', 'description' => 'Permite cerrar mantenciones de equipos de Informatica.'],
            ['slug' => 'informatica.reportes.ver', 'name' => 'Ver reportes Informatica', 'description' => 'Permite consultar reportes del modulo Informatica.'],

            // Contabilidad
            ['slug' => 'contabilidad.ver', 'name' => 'Ver modulo Contabilidad', 'description' => 'Permite acceder al modulo Contabilidad y consultar recursos visibles.'],
            ['slug' => 'contabilidad.dashboard', 'name' => 'Ver dashboard Contabilidad', 'description' => 'Permite consultar el dashboard contable.'],
            ['slug' => 'contabilidad.presupuesto.ver', 'name' => 'Ver presupuesto Contabilidad', 'description' => 'Permite consultar presupuestos y lineas presupuestarias.'],
            ['slug' => 'contabilidad.presupuesto.crear', 'name' => 'Crear presupuesto Contabilidad', 'description' => 'Permite crear o registrar presupuestos contables.'],
            ['slug' => 'contabilidad.presupuesto.aprobar', 'name' => 'Aprobar presupuesto Contabilidad', 'description' => 'Permite aprobar presupuestos y ajustes presupuestarios.'],
            ['slug' => 'contabilidad.centros_costo.gestionar', 'name' => 'Gestionar centros de costo', 'description' => 'Permite crear, editar y administrar centros de costo contables.'],
            ['slug' => 'contabilidad.manual_cuentas.gestionar', 'name' => 'Gestionar manual de cuentas', 'description' => 'Permite administrar el manual y plan de cuentas contable.'],
            ['slug' => 'contabilidad.ingresos.gestionar', 'name' => 'Gestionar ingresos Contabilidad', 'description' => 'Permite registrar y administrar ingresos contables.'],
            ['slug' => 'contabilidad.egresos.gestionar', 'name' => 'Gestionar egresos Contabilidad', 'description' => 'Permite registrar y administrar egresos contables.'],
            ['slug' => 'contabilidad.pagos.gestionar', 'name' => 'Gestionar pagos Contabilidad', 'description' => 'Permite registrar y administrar pagos contables.'],
            ['slug' => 'contabilidad.caja_chica.gestionar', 'name' => 'Gestionar caja chica', 'description' => 'Permite administrar caja chica, rendiciones y movimientos asociados.'],
            ['slug' => 'contabilidad.fondos_rendir.gestionar', 'name' => 'Gestionar fondos por rendir', 'description' => 'Permite administrar fondos por rendir y sus comprobantes.'],
            ['slug' => 'contabilidad.conciliacion.gestionar', 'name' => 'Gestionar conciliacion bancaria', 'description' => 'Permite administrar conciliaciones bancarias y movimientos conciliados.'],
            ['slug' => 'contabilidad.subvenciones.ver', 'name' => 'Ver subvenciones Contabilidad', 'description' => 'Permite consultar paneles y datos de subvenciones o fuentes de financiamiento.'],
            ['slug' => 'contabilidad.cheques.gestionar', 'name' => 'Gestionar cheques Contabilidad', 'description' => 'Permite administrar cheques y documentos de pago.'],
            ['slug' => 'contabilidad.facturas.gestionar', 'name' => 'Gestionar facturas Contabilidad', 'description' => 'Permite administrar facturas y registros asociados.'],
            ['slug' => 'contabilidad.boletas.gestionar', 'name' => 'Gestionar boletas Contabilidad', 'description' => 'Permite administrar boletas de honorarios u otros documentos tributarios.'],
            ['slug' => 'contabilidad.f29.gestionar', 'name' => 'Gestionar F29 Contabilidad', 'description' => 'Permite administrar declaraciones F29 internas.'],
            ['slug' => 'contabilidad.dj.gestionar', 'name' => 'Gestionar declaraciones juradas', 'description' => 'Permite administrar declaraciones juradas contables o tributarias.'],
            ['slug' => 'contabilidad.renta.gestionar', 'name' => 'Gestionar declaracion de renta', 'description' => 'Permite administrar datos y procesos de declaracion de renta.'],
            ['slug' => 'contabilidad.balance.ver', 'name' => 'Ver balances Contabilidad', 'description' => 'Permite consultar balances contables y reportes de cierre.'],
            ['slug' => 'contabilidad.reportes.exportar', 'name' => 'Exportar reportes Contabilidad', 'description' => 'Permite exportar reportes contables.'],
            ['slug' => 'contabilidad.admin', 'name' => 'Administrar modulo Contabilidad', 'description' => 'Permite administrar globalmente configuracion y operaciones del modulo Contabilidad.'],

            // Remuneraciones
            ['slug' => 'remuneraciones.ver', 'name' => 'Ver modulo Remuneraciones', 'description' => 'Permite acceder al modulo Remuneraciones y consultar recursos visibles.'],
            ['slug' => 'remuneraciones.dashboard', 'name' => 'Ver dashboard Remuneraciones', 'description' => 'Permite consultar el dashboard del modulo Remuneraciones.'],
            ['slug' => 'remuneraciones.trabajadores.gestionar', 'name' => 'Gestionar trabajadores Remuneraciones', 'description' => 'Permite administrar fichas remuneracionales de trabajadores.'],
            ['slug' => 'remuneraciones.contratos.gestionar', 'name' => 'Gestionar contratos Remuneraciones', 'description' => 'Permite administrar contratos y configuraciones remuneracionales.'],
            ['slug' => 'remuneraciones.parametros.gestionar', 'name' => 'Gestionar parametros Remuneraciones', 'description' => 'Permite administrar parametros legales y valores usados en calculos de remuneraciones.'],
            ['slug' => 'remuneraciones.conceptos.gestionar', 'name' => 'Gestionar conceptos Remuneraciones', 'description' => 'Permite administrar haberes, descuentos y conceptos de liquidacion.'],
            ['slug' => 'remuneraciones.movimientos.gestionar', 'name' => 'Gestionar movimientos Remuneraciones', 'description' => 'Permite registrar y administrar movimientos de remuneraciones.'],
            ['slug' => 'remuneraciones.liquidaciones.calcular', 'name' => 'Calcular liquidaciones', 'description' => 'Permite calcular liquidaciones de sueldo.'],
            ['slug' => 'remuneraciones.liquidaciones.aprobar', 'name' => 'Aprobar liquidaciones', 'description' => 'Permite aprobar liquidaciones antes de pago o cierre.'],
            ['slug' => 'remuneraciones.pagos.gestionar', 'name' => 'Gestionar pagos Remuneraciones', 'description' => 'Permite administrar pagos de remuneraciones.'],
            ['slug' => 'remuneraciones.contabilidad.centralizar', 'name' => 'Centralizar remuneraciones', 'description' => 'Permite generar o administrar centralizaciones contables de remuneraciones.'],
            ['slug' => 'remuneraciones.reportes.ver', 'name' => 'Ver reportes Remuneraciones', 'description' => 'Permite consultar reportes del modulo Remuneraciones.'],
            ['slug' => 'remuneraciones.reportes.exportar', 'name' => 'Exportar reportes Remuneraciones', 'description' => 'Permite exportar reportes del modulo Remuneraciones.'],
            ['slug' => 'remuneraciones.periodos.cerrar', 'name' => 'Cerrar periodos Remuneraciones', 'description' => 'Permite cerrar o reabrir periodos de remuneraciones.'],
            ['slug' => 'remuneraciones.rrhh.gestionar', 'name' => 'Gestionar RR.HH. Remuneraciones', 'description' => 'Permite administrar procesos integrales de RR.HH. vinculados a remuneraciones.'],
            ['slug' => 'remuneraciones.admin', 'name' => 'Administrar modulo Remuneraciones', 'description' => 'Permite administrar globalmente configuracion y operaciones del modulo Remuneraciones.'],
        ];
    }
}
