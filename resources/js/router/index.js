import { createRouter, createWebHistory } from 'vue-router'
import axios from 'axios'

const pmeSepRoute = (path, title) => ({
    path,
    meta: { authRequired: true, title, permission: 'ver_modulo_pme' },
    component: () => import('../views/pme-sep/index.vue'),
})

const accountingRoute = (path, title, permission = 'contabilidad.ver') => ({
    path,
    meta: { authRequired: true, title, permission },
    component: () => import('../views/accounting/index.vue'),
})

const remunerationRoute = (path, title, permission = 'remuneraciones.ver') => ({
    path,
    meta: { authRequired: true, title, permission },
    component: () => import('../views/remuneration/index.vue'),
})

const informaticaRoute = (path, title, permission) => ({
    path,
    meta: { authRequired: true, title, permission },
    component: () => import('../views/informatica/index.vue'),
})

const scheduleRoute = (path, title, permission = 'ver_horarios') => ({
    path,
    meta: { authRequired: true, title, permission },
    component: () => import('../views/schedule/index.vue'),
})

const routes = [
    {
        path: '/',
        redirect: '/inicio',
    },
    {
        path: '/inicio',
        name: 'inicio',
        meta: {
            title: 'Inicio',
            authRequired: true,
        },
        component: () => import('../views/home.vue'),
    },
    {
        path: '/dashboard',
        redirect: '/inicio',
    },
    {
        path: '/dashboard/saas',
        meta: { title: 'Saas', authRequired: true, },
        component: () => import('../views/dashboard/saas.vue'),
    },
    {
        path: '/dashboard/crypto',
        meta: { title: 'Crypto', authRequired: true, },
        component: () => import('../views/dashboard/crypto.vue'),
    },
    {
        path: '/dashboard/blog',
        meta: { title: 'Blog', authRequired: true, },
        component: () => import('../views/dashboard/blog.vue'),
    },
    {
        path: '/dashboard/job',
        meta: { authRequired: true, title: 'Job' },
        component: () => import('../views/dashboard/jobs/index.vue'),
    },
    {
        path: '/test',
        meta: { authRequired: true, title: 'Test' },
        component: () => import('../views/dashboard/test.vue'),
    },
    {
        path: '/deploy',
        meta: { authRequired: true, title: 'Deploy' },
        component: () => import('../views/dashboard/deploy.vue'),
    },
    {
        path: '/maintenance/dependencies',
        meta: { authRequired: true, title: 'Activos técnicos', permission: 'ver_mantencion' },
        component: () => import('../views/maintenance/dependencies.vue'),
    },
    {
        path: '/spaces/dependencies',
        meta: { authRequired: true, title: 'Dependencias', permission: 'ver_dependencias' },
        component: () => import('../views/spaces/dependencies.vue'),
    },
    {
        path: '/spaces/dependencies/:id',
        meta: { authRequired: true, title: 'Ficha dependencia', permission: 'ver_dependencias' },
        component: () => import('../views/spaces/dependency-show.vue'),
    },
    {
        path: '/spaces/dependency-types',
        meta: { authRequired: true, title: 'Tipos de dependencia', permission: 'ver_dependencias' },
        component: () => import('../views/spaces/dependency-types.vue'),
    },
    {
        path: '/spaces/approvers',
        meta: { authRequired: true, title: 'Gestores de aprobación', permission: 'ver_dependencias' },
        component: () => import('../views/spaces/approvers.vue'),
    },
    {
        path: '/spaces/reservations',
        meta: { authRequired: true, title: 'Reservas', permission: 'ver_reservas' },
        component: () => import('../views/spaces/reservations.vue'),
    },
    {
        path: '/spaces/calendar',
        meta: { authRequired: true, title: 'Calendario de reservas', permission: 'ver_reservas' },
        component: () => import('../views/spaces/calendar.vue'),
    },
    {
        path: '/spaces/statistics',
        meta: { authRequired: true, title: 'Estadísticas de espacios', permission: 'ver_estadisticas_espacios' },
        component: () => import('../views/spaces/statistics.vue'),
    },
    {
        path: '/relevant-calendar',
        meta: { authRequired: true, title: 'Calendario y Fechas Relevantes', permission: 'ver_calendario_fechas_relevantes' },
        component: () => import('../views/relevant-calendar/index.vue'),
    },
    {
        path: '/relevant-calendar/events/:id',
        meta: { authRequired: true, title: 'Detalle de proceso', permission: 'ver_calendario_fechas_relevantes' },
        component: () => import('../views/relevant-calendar/detail.vue'),
    },
    {
        path: '/relevant-calendar/process-types',
        meta: { authRequired: true, title: 'Tipos de procesos', permission: 'administrar_tipos_calendario_fechas_relevantes' },
        component: () => import('../views/relevant-calendar/process-types.vue'),
    },
    {
        path: '/relevant-calendar/institutions',
        meta: { authRequired: true, title: 'Instituciones', permission: 'administrar_instituciones_calendario_fechas_relevantes' },
        component: () => import('../views/relevant-calendar/institutions.vue'),
    },
    {
        path: '/risk-prevention',
        meta: { authRequired: true, title: 'Dashboard de Prevención', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/dashboard.vue'),
    },
    {
        path: '/risk-prevention/extinguishers',
        meta: { authRequired: true, title: 'Gestión de Extintores', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/extinguishers.vue'),
    },
    {
        path: '/risk-prevention/accidents',
        meta: { authRequired: true, title: 'Registro de Accidentes', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/accidents.vue'),
    },
    {
        path: '/risk-prevention/emergencies',
        meta: { authRequired: true, title: 'Emergencias y Planes', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/emergencies.vue'),
    },
    {
        path: '/risk-prevention/epp',
        meta: { authRequired: true, title: 'EPP y Seguridad', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/epp.vue'),
    },
    {
        path: '/risk-prevention/trainings',
        meta: { authRequired: true, title: 'Capacitaciones', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/trainings.vue'),
    },
    {
        path: '/risk-prevention/documents',
        meta: { authRequired: true, title: 'Centro de Documentos', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/documents.vue'),
    },
    {
        path: '/risk-prevention/reports',
        meta: { authRequired: true, title: 'Reportes de Prevención', permission: 'ver_prevencion_riesgos' },
        component: () => import('../views/risk-prevention/reports.vue'),
    },
    {
        path: '/security/dashboard',
        meta: { authRequired: true, title: 'Panel de rondas', permission: 'ver_rondas_seguridad' },
        component: () => import('../views/security/dashboard.vue'),
    },
    {
        path: '/security/shifts',
        meta: { authRequired: true, title: 'Turnos y rondas', permission: 'ver_rondas_seguridad' },
        component: () => import('../views/security/shifts.vue'),
    },
    {
        path: '/security/incidents',
        meta: { authRequired: true, title: 'Novedades pendientes', permission: 'ver_rondas_seguridad' },
        component: () => import('../views/security/incidents.vue'),
    },
    {
        path: '/maintenance/work-orders',
        meta: { authRequired: true, title: 'Órdenes de trabajo', permission: 'ver_mantencion' },
        component: () => import('../views/maintenance/work-orders.vue'),
    },
    {
        path: '/maintenance/workload',
        meta: { authRequired: true, title: 'Carga de trabajo', permission: 'ver_reportes_mantencion' },
        component: () => import('../views/maintenance/workload.vue'),
    },
    {
        path: '/maintenance/visits',
        meta: { authRequired: true, title: 'Planificación de visitas', permission: 'ver_visitas_mantencion' },
        component: () => import('../views/maintenance/visits.vue'),
    },
    {
        path: '/maintenance/visits/:id/checklist',
        meta: { authRequired: true, title: 'Checklist de visita', permission: 'ver_visitas_mantencion' },
        component: () => import('../views/maintenance/visit-checklist.vue'),
    },
    {
        path: '/maintenance/annual-plans',
        meta: { authRequired: true, title: 'Plan anual de mantención', permission: 'ver_plan_anual_mantencion' },
        component: () => import('../views/maintenance/annual-plan.vue'),
    },
    {
        path: '/admin/users',
        meta: { authRequired: true, title: 'Usuarios', permission: 'administrar_usuarios' },
        component: () => import('../views/admin/users.vue'),
    },
    {
        path: '/admin/roles',
        meta: { authRequired: true, title: 'Roles', permission: 'administrar_roles' },
        component: () => import('../views/admin/roles.vue'),
    },
    {
        path: '/admin/permissions',
        meta: { authRequired: true, title: 'Permisos', permission: 'administrar_permisos' },
        component: () => import('../views/admin/permissions.vue'),
    },
    {
        path: '/admin/modules',
        meta: { authRequired: true, title: 'Módulos', permission: 'administrar_modulos' },
        component: () => import('../views/admin/modules.vue'),
    },
    {
        path: '/admin/cargos',
        meta: { authRequired: true, title: 'Cargos', permission: 'administrar_cargos' },
        component: () => import('../views/admin/cargos.vue'),
    },
    {
        path: '/admin/organigram',
        meta: { authRequired: true, title: 'Organigrama', permission: 'administrar_organigrama' },
        component: () => import('../views/admin/organigram.vue'),
    },
    {
        path: '/students',
        meta: { authRequired: true, title: 'Estudiantes', permission: 'ver_estudiantes' },
        component: () => import('../views/students/index.vue'),
    },
    {
        path: '/students/levels',
        meta: { authRequired: true, title: 'Niveles', permission: 'ver_estudiantes' },
        component: () => import('../views/students/levels.vue'),
    },
    {
        path: '/students/academic-years',
        meta: { authRequired: true, title: 'Años académicos', permission: 'ver_estudiantes' },
        component: () => import('../views/students/academic-years.vue'),
    },
    {
        path: '/students/courses',
        meta: { authRequired: true, title: 'Cursos por año', permission: 'ver_estudiantes' },
        component: () => import('../views/students/courses.vue'),
    },
    {
        path: '/students/promotions',
        meta: { authRequired: true, title: 'Promoción anual', permission: 'promover_estudiantes' },
        component: () => import('../views/students/promotions.vue'),
    },
    {
        path: '/students/movements',
        meta: { authRequired: true, title: 'Cambios y retiros', permission: 'gestionar_matriculas_estudiantes' },
        component: () => import('../views/students/movements.vue'),
    },
    {
        path: '/schedule',
        redirect: '/schedule/teacher',
    },
    scheduleRoute('/schedule/teacher', 'Horario docente'),
    scheduleRoute('/schedule/course', 'Horario por curso'),
    scheduleRoute('/schedule/config', 'Configuración horaria', 'configurar_horarios'),
    scheduleRoute('/schedule/jornadas', 'Jornadas escolares', 'configurar_jornadas'),
    scheduleRoute('/schedule/study-plans', 'Asignaturas y plan de estudio', 'configurar_plan_estudio'),
    scheduleRoute('/schedule/contracts', 'Contratos docentes', 'configurar_contratos_docentes'),
    scheduleRoute('/schedule/conflicts', 'Conflictos de horario'),
    {
        path: '/students/new',
        meta: { authRequired: true, title: 'Nueva estudiante', permission: 'crear_estudiantes' },
        component: () => import('../views/students/form.vue'),
    },
    {
        path: '/students/:id',
        meta: { authRequired: true, title: 'Ficha de estudiante', permission: 'ver_ficha_estudiante' },
        component: () => import('../views/students/form.vue'),
    },
    {
        path: '/porter/dashboard',
        meta: { authRequired: true, title: 'Panel de portería', permission: 'ver_porteria' },
        component: () => import('../views/porter/dashboard.vue'),
    },
    {
        path: '/porter/students',
        meta: { authRequired: true, title: 'Consulta de estudiantes', permission: 'ver_porteria' },
        component: () => import('../views/porter/students.vue'),
    },
    {
        path: '/porter/withdrawals',
        meta: { authRequired: true, title: 'Retiros de estudiantes', permission: 'ver_porteria' },
        component: () => import('../views/porter/withdrawals.vue'),
    },
    {
        path: '/porter/received-items',
        meta: { authRequired: true, title: 'Recepción de objetos', permission: 'ver_porteria' },
        component: () => import('../views/porter/received-items.vue'),
    },
    {
        path: '/porter/goods',
        meta: { authRequired: true, title: 'Mercadería', permission: 'ver_porteria' },
        component: () => import('../views/porter/goods.vue'),
    },
    {
        path: '/porter/visits',
        meta: { authRequired: true, title: 'Control de visitas', permission: 'ver_porteria' },
        component: () => import('../views/porter/visits.vue'),
    },
    {
        path: '/porter/providers',
        meta: { authRequired: true, title: 'Control de proveedores', permission: 'ver_porteria' },
        component: () => import('../views/porter/providers.vue'),
    },
    {
        path: '/porter/daily-log',
        meta: { authRequired: true, title: 'Bitácora diaria', permission: 'ver_porteria' },
        component: () => import('../views/porter/daily-log.vue'),
    },
    {
        path: '/porter/keys',
        meta: { authRequired: true, title: 'Control de llaves', permission: 'ver_porteria' },
        component: () => import('../views/porter/keys.vue'),
    },
    {
        path: '/porter/reports',
        meta: { authRequired: true, title: 'Reportes de portería', permission: 'ver_porteria' },
        component: () => import('../views/porter/reports.vue'),
    },
    {
        path: '/infirmary',
        meta: { authRequired: true, title: 'Dashboard de Enfermería', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/dashboard.vue'),
    },
    {
        path: '/infirmary/attentions',
        meta: { authRequired: true, title: 'Atenciones de Enfermería', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/attentions.vue'),
    },
    {
        path: '/infirmary/history',
        meta: { authRequired: true, title: 'Ficha médica', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/history.vue'),
    },
    {
        path: '/infirmary/inventory',
        meta: { authRequired: true, title: 'Inventario de medicamentos', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/inventory.vue'),
    },
    {
        path: '/infirmary/medications',
        meta: { authRequired: true, title: 'Administración de medicamentos', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/medications.vue'),
    },
    {
        path: '/infirmary/accidents',
        meta: { authRequired: true, title: 'Accidentes escolares', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/accidents.vue'),
    },
    {
        path: '/infirmary/calls',
        meta: { authRequired: true, title: 'Registro de llamados', permission: 'ver_enfermeria' },
        component: () => import('../views/infirmary/calls.vue'),
    },
    {
        path: '/infirmary/reports',
        meta: { authRequired: true, title: 'Reportes de Enfermería', permission: 'ver_reportes_enfermeria' },
        component: () => import('../views/infirmary/reports.vue'),
    },
    {
        path: '/biblioteca',
        meta: { authRequired: true, title: 'Dashboard Biblioteca Escolar', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/catalogo',
        meta: { authRequired: true, title: 'Catálogo Bibliográfico', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/inventario',
        meta: { authRequired: true, title: 'Ejemplares e Inventario', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/prestamos',
        meta: { authRequired: true, title: 'Préstamos y Devoluciones', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/reservas',
        meta: { authRequired: true, title: 'Reservas de Recursos', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/plan-lector',
        meta: { authRequired: true, title: 'Plan Lector', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/espacios',
        meta: { authRequired: true, title: 'Uso de Espacios', permission: 'ver_modulo_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    {
        path: '/biblioteca/reportes',
        meta: { authRequired: true, title: 'Estadísticas y Reportes Biblioteca', permission: 'ver_estadisticas_biblioteca' },
        component: () => import('../views/library/index.vue'),
    },
    informaticaRoute('/informatica', 'Dashboard Informática', 'informatica.dashboard'),
    informaticaRoute('/informatica/equipos', 'Equipos Informáticos', 'informatica.equipos.ver'),
    informaticaRoute('/informatica/prestamos', 'Préstamos de Equipos', 'informatica.prestamos.ver'),
    informaticaRoute('/informatica/mantenciones', 'Mantenciones Informáticas', 'informatica.mantenciones.ver'),
    informaticaRoute('/informatica/reportes', 'Reportes de Informática', 'informatica.reportes.ver'),
    remunerationRoute('/remuneraciones', 'Dashboard Remuneraciones'),
    remunerationRoute('/remuneraciones/trabajadores', 'Trabajadores Remuneraciones', 'remuneraciones.trabajadores.gestionar'),
    remunerationRoute('/remuneraciones/contratos', 'Contratos Remuneraciones', 'remuneraciones.contratos.gestionar'),
    remunerationRoute('/remuneraciones/periodos', 'Períodos Remuneraciones', 'remuneraciones.periodos.cerrar'),
    remunerationRoute('/remuneraciones/parametros', 'Parámetros Remuneraciones', 'remuneraciones.parametros.gestionar'),
    remunerationRoute('/remuneraciones/conceptos', 'Haberes y Descuentos', 'remuneraciones.conceptos.gestionar'),
    remunerationRoute('/remuneraciones/movimientos', 'Movimientos Remuneraciones', 'remuneraciones.movimientos.gestionar'),
    remunerationRoute('/remuneraciones/liquidaciones', 'Liquidaciones de Sueldo', 'remuneraciones.liquidaciones.calcular'),
    remunerationRoute('/remuneraciones/pagos', 'Pagos Remuneraciones', 'remuneraciones.pagos.gestionar'),
    remunerationRoute('/remuneraciones/centralizacion', 'Centralización Remuneraciones', 'remuneraciones.contabilidad.centralizar'),
    remunerationRoute('/remuneraciones/reportes', 'Reportes Remuneraciones', 'remuneraciones.reportes.ver'),
    remunerationRoute('/remuneraciones/licencias-medicas', 'Licencias Médicas RR.HH.', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/cumpleanos', 'Cumpleaños RR.HH.'),
    remunerationRoute('/remuneraciones/permisos', 'Permisos RR.HH.', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/gestion-funcionarios', 'Gestión Funcionarios', 'remuneraciones.trabajadores.gestionar'),
    remunerationRoute('/remuneraciones/control-documental', 'Control Documental RR.HH.', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/induccion', 'Inducción Funcionarios', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/clima-laboral', 'Clima Laboral', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/planes-clima', 'Planes Clima Laboral', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/dotacion-carga', 'Dotación y Carga Horaria', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/banco-cv', 'Banco de CV', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/reemplazos', 'Banco de Reemplazos', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/perfiles-cargo', 'Perfiles de Cargo', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/certificados', 'Certificados Laborales', 'remuneraciones.rrhh.gestionar'),
    remunerationRoute('/remuneraciones/auditoria', 'Auditoría Remuneraciones', 'remuneraciones.admin'),
    accountingRoute('/contabilidad', 'Dashboard Contabilidad'),
    accountingRoute('/contabilidad/rendiciones', 'Rendición de Cuentas'),
    accountingRoute('/contabilidad/presupuesto', 'Presupuesto Anual', 'contabilidad.presupuesto.ver'),
    accountingRoute('/contabilidad/centros-costo', 'Centros de Costo'),
    accountingRoute('/contabilidad/manual-cuentas', 'Manual de Cuentas'),
    accountingRoute('/contabilidad/ingresos', 'Ingresos'),
    accountingRoute('/contabilidad/egresos', 'Egresos y Pagos'),
    accountingRoute('/contabilidad/caja-chica', 'Caja Chica'),
    accountingRoute('/contabilidad/fondos-rendir', 'Fondos por Rendir'),
    accountingRoute('/contabilidad/conciliacion', 'Conciliación Bancaria'),
    accountingRoute('/contabilidad/subvenciones', 'Panel de Subvenciones'),
    accountingRoute('/contabilidad/cheques', 'Gestión de Cheques'),
    accountingRoute('/contabilidad/facturas', 'Gestión de Facturas'),
    accountingRoute('/contabilidad/boletas-honorarios', 'Boletas de Honorarios'),
    accountingRoute('/contabilidad/flujo-caja', 'Flujo de Caja'),
    accountingRoute('/contabilidad/cuentas-por-pagar', 'Cuentas por Pagar'),
    accountingRoute('/contabilidad/f29', 'Gestión F29'),
    accountingRoute('/contabilidad/balance', 'Balance 8 y 9 Columnas', 'contabilidad.balance.ver'),
    accountingRoute('/contabilidad/dj-ingresos', 'Declaraciones Juradas de Ingresos'),
    accountingRoute('/contabilidad/dj-arriendo', 'Declaración Jurada de Arriendo'),
    accountingRoute('/contabilidad/declaracion-renta', 'Declaración de Renta'),
    accountingRoute('/contabilidad/reportes', 'Reportes Contables', 'contabilidad.balance.ver'),
    {
        path: '/convivencia',
        meta: { authRequired: true, title: 'Dashboard de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/planes',
        meta: { authRequired: true, title: 'Plan de Gestión de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/casos',
        meta: { authRequired: true, title: 'Casos de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/denuncias',
        meta: { authRequired: true, title: 'Denuncias de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/derivaciones',
        meta: { authRequired: true, title: 'Derivaciones de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/protocolos',
        meta: { authRequired: true, title: 'Protocolos de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/entrevistas',
        meta: { authRequired: true, title: 'Entrevistas de Convivencia', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/medidas',
        meta: { authRequired: true, title: 'Medidas Formativas', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/bitacora',
        meta: { authRequired: true, title: 'Bitácora de Inspectoría', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/sociogramas',
        meta: { authRequired: true, title: 'Sociogramas', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/idps',
        meta: { authRequired: true, title: 'Indicadores IDPS', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/convivencia/reportes',
        meta: { authRequired: true, title: 'Reportes por Curso', permission: 'ver_convivencia' },
        component: () => import('../views/convivencia/index.vue'),
    },
    {
        path: '/apoyo-profesional',
        meta: { authRequired: true, title: 'Dashboard Equipo de Apoyo', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/atenciones',
        meta: { authRequired: true, title: 'Atenciones Profesionales', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/historial',
        meta: { authRequired: true, title: 'Ficha de Apoyo por Estudiante', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/derivaciones',
        meta: { authRequired: true, title: 'Derivaciones Internas', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/seguimientos',
        meta: { authRequired: true, title: 'Seguimientos del Equipo de Apoyo', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/planes',
        meta: { authRequired: true, title: 'Planes de Apoyo', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/entrevistas',
        meta: { authRequired: true, title: 'Entrevistas Profesionales', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/documentos',
        meta: { authRequired: true, title: 'Documentos del Equipo de Apoyo', permission: 'ver_modulo_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/apoyo-profesional/reportes',
        meta: { authRequired: true, title: 'Reportes del Equipo de Apoyo', permission: 'ver_reportes_apoyo_profesional' },
        component: () => import('../views/apoyo-profesional/index.vue'),
    },
    {
        path: '/centro-apuntes',
        meta: { authRequired: true, title: 'Dashboard Centro de Apuntes', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/solicitudes',
        meta: { authRequired: true, title: 'Solicitudes y Tareas', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/asignaturas',
        meta: { authRequired: true, title: 'Asignaturas', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/maquinas',
        meta: { authRequired: true, title: 'Máquinas', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/insumos',
        meta: { authRequired: true, title: 'Pañol e Insumos', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/movimientos',
        meta: { authRequired: true, title: 'Movimientos de Stock', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/entregas',
        meta: { authRequired: true, title: 'Entregas de Materiales', permission: 'ver_modulo_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/centro-apuntes/reportes',
        meta: { authRequired: true, title: 'Reportes Centro de Apuntes', permission: 'ver_reportes_centro_apuntes' },
        component: () => import('../views/centro-apuntes/index.vue'),
    },
    {
        path: '/staff',
        meta: { authRequired: true, title: 'Funcionarios', permission: 'ver_funcionarios' },
        component: () => import('../views/staff/index.vue'),
    },
    {
        path: '/staff/departments',
        meta: { authRequired: true, title: 'Departamentos', permission: 'administrar_departamentos' },
        component: () => import('../views/staff/departments.vue'),
    },
    {
        path: '/staff/permissions/dashboard',
        meta: { authRequired: true, title: 'Dashboard de permisos', permission: 'ver_permisos_personal' },
        component: () => import('../views/staff/permissions/dashboard.vue'),
    },
    {
        path: '/staff/permissions',
        meta: { authRequired: true, title: 'Mis permisos', permission: 'ver_permisos_personal' },
        component: () => import('../views/staff/permissions/index.vue'),
    },
    {
        path: '/staff/permissions/review',
        meta: { authRequired: true, title: 'Bandeja de permisos', permission: 'ver_permisos_personal' },
        component: () => import('../views/staff/permissions/review.vue'),
    },
    {
        path: '/staff/permissions/reports',
        meta: { authRequired: true, title: 'Reportes de permisos', permission: 'ver_permisos_personal' },
        component: () => import('../views/staff/permissions/reports.vue'),
    },
    {
        path: '/staff/permissions/types',
        meta: { authRequired: true, title: 'Tipos de permiso', permission: 'ver_permisos_personal' },
        component: () => import('../views/staff/permissions/types.vue'),
    },
    {
        path: '/staff/permissions/watchers',
        meta: { authRequired: true, title: 'Quién debe enterarse', permission: 'administrar_destinatarios_permisos_personal' },
        component: () => import('../views/staff/permissions/watchers.vue'),
    },
    {
        path: '/staff/permissions/watchers-summary',
        meta: { authRequired: true, title: 'Destinatarios por funcionario', permission: 'administrar_destinatarios_permisos_personal' },
        component: () => import('../views/staff/permissions/watchers-summary.vue'),
    },
    {
        path: '/tasks/backlog',
        meta: { authRequired: true, title: 'Backlog de tareas', permission: 'ver_tareas' },
        component: () => import('../views/tasks/backlog.vue'),
    },
    {
        path: '/tasks/assigners',
        meta: { authRequired: true, title: 'Asignadores de tareas', permission: 'administrar_asignadores_tareas' },
        component: () => import('../views/tasks/backlog.vue'),
    },
    {
        path: '/staff/new',
        meta: { authRequired: true, title: 'Nuevo funcionario', permission: 'gestionar_funcionarios' },
        component: () => import('../views/staff/form.vue'),
    },
    {
        path: '/staff/:id',
        meta: { authRequired: true, title: 'Ficha funcionario', permission: 'ver_funcionarios' },
        component: () => import('../views/staff/form.vue'),
    },
    {
        path: '/contracts',
        meta: { authRequired: true, title: 'Contratos', permission: 'ver_contratos' },
        component: () => import('../views/contracts/index.vue'),
    },
    {
        path: '/contracts/templates',
        meta: { authRequired: true, title: 'Plantillas de contrato', permission: 'administrar_plantillas_contrato' },
        component: () => import('../views/contracts/templates.vue'),
    },
    {
        path: '/contracts/clauses',
        meta: { authRequired: true, title: 'Cláusulas contractuales', permission: 'administrar_clausulas_contrato' },
        component: () => import('../views/contracts/clauses.vue'),
    },
    {
        path: '/contracts/signatures',
        meta: { authRequired: true, title: 'Firmas de contrato', permission: 'administrar_firmas_contrato' },
        component: () => import('../views/contracts/signatures.vue'),
    },
    {
        path: '/contracts/new',
        meta: { authRequired: true, title: 'Nuevo contrato', permission: 'gestionar_contratos' },
        component: () => import('../views/contracts/form.vue'),
    },
    {
        path: '/contracts/:id',
        meta: { authRequired: true, title: 'Ficha contrato', permission: 'ver_contratos' },
        component: () => import('../views/contracts/form.vue'),
    },
    {
        path: '/inventory/items',
        meta: { authRequired: true, title: 'Inventario · Bienes', permission: 'ver_inventario' },
        component: () => import('../views/inventory/items.vue'),
    },
    {
        path: '/inventory/items/:id',
        meta: { authRequired: true, title: 'Inventario · Ficha', permission: 'ver_inventario' },
        component: () => import('../views/inventory/item-show.vue'),
    },
    {
        path: '/inventory/categories',
        meta: { authRequired: true, title: 'Inventario · Categorías', permission: 'administrar_categorias_inventario' },
        component: () => import('../views/inventory/categories.vue'),
    },
    {
        path: '/inventory/suppliers',
        meta: { authRequired: true, title: 'Inventario · Proveedores', permission: 'ver_inventario' },
        component: () => import('../views/inventory/suppliers.vue'),
    },
    {
        path: '/inventory/reports',
        meta: { authRequired: true, title: 'Inventario · Reportes', permission: 'ver_reportes_inventario' },
        component: () => import('../views/inventory/reports.vue'),
    },
    {
        path: '/inventory/labels',
        meta: { authRequired: true, title: 'Inventario · Etiquetas', permission: 'imprimir_etiquetas_inventario' },
        component: () => import('../views/inventory/labels.vue'),
    },
    {
        path: "/calendar/tui-calendar",
        name: "TUICalendar",
        meta: { title: "TUI Calendar", authRequired: true },
        component: () => import("../views/calendar/tui-calendar.vue")
    },
    {
        path: "/calendar/full-calendar",
        name: "Full Calendar",
        meta: { title: "Full Calendar", authRequired: true },
        component: () => import("../views/calendar/full-calendar.vue")
    },
    {
        path: '/chat',
        meta: { authRequired: true, title: 'Chat' },
        component: () => import('../views/chat/chat.vue')
    },
    {
        path: '/file-manager',
        meta: { authRequired: true, title: 'File Manager' },
        component: () => import('../views/file-manager/index.vue')
    },
    {
        path: '/ecommerce/products',
        meta: { authRequired: true, title: 'Products' },
        component: () => import('../views/ecommerce/products.vue')
    },
    {
        path: '/ecommerce/product-detail',
        meta: { authRequired: true, title: 'Product Detail' },
        component: () => import('../views/ecommerce/product-detail.vue')
    },
    {
        path: '/ecommerce/orders',
        meta: { authRequired: true, title: 'Orders' },
        component: () => import('../views/ecommerce/orders.vue')
    },
    {
        path: '/ecommerce/customers',
        meta: { authRequired: true, title: 'Customers' },
        component: () => import('../views/ecommerce/customers.vue')
    },
    {
        path: '/ecommerce/cart',
        meta: { authRequired: true, title: 'Cart' },
        component: () => import('../views/ecommerce/cart.vue')
    },
    {
        path: '/ecommerce/checkout',
        meta: { authRequired: true, title: 'Checkout' },
        component: () => import('../views/ecommerce/checkout.vue')
    },
    {
        path: '/ecommerce/shops',
        meta: { authRequired: true, title: 'Shops' },
        component: () => import('../views/ecommerce/shops.vue')
    },
    {
        path: '/ecommerce/add-product',
        meta: { authRequired: true, title: 'Add Product' },
        component: () => import('../views/ecommerce/add-product.vue')
    },
    {
        path: '/crypto/wallet',
        meta: { authRequired: true, title: 'Wallet' },
        component: () => import('../views/crypto/wallet.vue')
    },
    {
        path: '/crypto/buy-sell',
        meta: { authRequired: true, title: 'Buy/Sell' },
        component: () => import('../views/crypto/buy-sell.vue')
    },
    {
        path: '/crypto/exchange',
        meta: { authRequired: true, title: 'Exchange' },
        component: () => import('../views/crypto/exchange.vue')
    },
    {
        path: '/crypto/lending',
        meta: { authRequired: true, title: 'Lending' },
        component: () => import('../views/crypto/lending.vue')
    },
    {
        path: '/crypto/orders',
        meta: { authRequired: true, title: 'Orders' },
        component: () => import('../views/crypto/orders.vue')
    },
    {
        path: '/crypto/kyc-application',
        meta: { authRequired: true, title: 'KYC Application' },
        component: () => import('../views/crypto/kyc-application.vue')
    },
    {
        path: '/crypto/ico-landing',
        meta: { authRequired: true, title: 'ICO Landing' },
        component: () => import('../views/crypto/ico-landing.vue')
    },
    {
        path: '/email/inbox',
        meta: { authRequired: true, title: 'Inbox' },
        component: () => import('../views/email/inbox.vue')
    },
    {
        path: '/email/reademail/:id',
        meta: { authRequired: true, title: 'Read Email' },
        component: () => import('../views/email/reademail.vue')
    },
    {
        path: '/email/templates/basic',
        meta: { authRequired: true, title: 'Basic Template' },
        component: () => import('../views/email/templates/basic.vue')
    },
    {
        path: '/email/templates/alert',
        meta: { authRequired: true, title: 'Alert Template' },
        component: () => import('../views/email/templates/alert.vue')
    },
    {
        path: '/email/templates/billing',
        meta: { authRequired: true, title: 'Billing Template' },
        component: () => import('../views/email/templates/billing.vue')
    },
    {
        path: '/invoices/list',
        meta: { authRequired: true, title: 'Invoice List' },
        component: () => import('../views/invoices/list.vue')
    },
    {
        path: '/invoices/detail',
        meta: { authRequired: true, title: 'Invoice Detail' },
        component: () => import('../views/invoices/detail.vue')
    },
    {
        path: '/projects/grid',
        meta: { authRequired: true, title: 'Project Grid' },
        component: () => import('../views/projects/projects-grid.vue')
    },
    {
        path: '/projects/list',
        meta: { authRequired: true, title: 'Project List' },
        component: () => import('../views/projects/projects-list.vue')
    },
    {
        path: '/projects/create',
        meta: { authRequired: true, title: 'Project Create' },
        component: () => import('../views/projects/create.vue')
    },
    {
        path: '/projects/overview',
        meta: { authRequired: true, title: 'Project Overview' },
        component: () => import('../views/projects/overview.vue')
    },
    {
        path: '/tasks/list',
        meta: { authRequired: true, title: 'Task List' },
        component: () => import('../views/tasks/task-list.vue')
    },
    {
        path: '/tasks/create',
        meta: { authRequired: true, title: 'Create Task' },
        component: () => import('../views/tasks/task-create.vue')
    },
    {
        path: '/tasks/kanban',
        meta: { authRequired: true, title: 'Kanban Board' },
        component: () => import('../views/tasks/kanbanboard.vue')
    },
    {
        path: '/contacts/grid',
        meta: { authRequired: true, title: 'Contact Grid' },
        component: () => import('../views/contacts/contacts-grid.vue')
    },
    {
        path: '/contacts/list',
        meta: { authRequired: true, title: 'Contact List' },
        component: () => import('../views/contacts/contacts-list.vue')
    },
    {
        path: '/contacts/profile',
        meta: { authRequired: true, title: 'Contact Profile' },
        component: () => import('../views/contacts/contacts-profile.vue')
    },
    {
        path: '/blog/grid',
        meta: { authRequired: true, title: 'Blog Grid' },
        component: () => import('../views/blog/grid.vue')
    },
    {
        path: '/blog/list',
        meta: { authRequired: true, title: 'Blog List' },
        component: () => import('../views/blog/list.vue')
    },
    {
        path: '/blog/detail',
        meta: { authRequired: true, title: 'Blog Detail' },
        component: () => import('../views/blog/detail.vue')
    },
    {
        path: '/jobs/list',
        meta: { authRequired: true, title: 'Job List' },
        component: () => import('../views/jobs/job-list.vue')
    },
    {
        path: '/jobs/grid',
        meta: { authRequired: true, title: 'Job Grid' },
        component: () => import('../views/jobs/job-grid.vue')
    },
    {
        path: '/jobs/apply',
        meta: { authRequired: true, title: 'Job Apply' },
        component: () => import('../views/jobs/job-apply.vue')
    },
    {
        path: '/jobs/details',
        meta: { authRequired: true, title: 'Job Details' },
        component: () => import('../views/jobs/job-details.vue')
    },
    {
        path: '/jobs/categories',
        meta: { authRequired: true, title: 'Job Categories' },
        component: () => import('../views/jobs/job-categories.vue')
    },
    {
        path: '/jobs/candidate/list',
        meta: { authRequired: true, title: 'Candidate List' },
        component: () => import('../views/jobs/candidate/list.vue')
    },
    {
        path: '/jobs/candidate/overview',
        meta: { authRequired: true, title: 'Candidate Overview' },
        component: () => import('../views/jobs/candidate/overview.vue')
    },
    {
        path: '/auth/login-1',
        meta: { authRequired: true, title: 'Login' },
        component: () => import('../views/sample-pages/login-sample.vue')
    },
    {
        path: '/login',
        name: 'login',
        meta: { title: 'Login' },
        component: () => import('../views/account/login.vue')
    },
    {
        path: '/auth/login-2',
        meta: { authRequired: true, title: 'Login' },
        component: () => import('../views/sample-pages/login-2.vue')
    },
    {
        path: '/auth/register',
        name: 'register',
        meta: { title: 'Register' },
        component: () => import('../views/account/register.vue')
    },
    {
        path: '/auth/register-1',
        meta: { title: 'Register' },
        component: () => import('../views/sample-pages/register-sample.vue')
    },
    {
        path: '/auth/register-2',
        meta: { authRequired: true, title: 'Register' },
        component: () => import('../views/sample-pages/register-2.vue')
    },
    {
        path: '/forget-password',
        meta: { title: 'Forget Password' },
        component: () => import('../views/account/forgot-password.vue')
    },
    {
        path: '/reset-password/:token',
        meta: { title: 'Reset Password' },
        component: () => import('../views/account/reset-password.vue')
    },
    {
        path: '/auth/recoverpw',
        meta: { authRequired: true, title: 'Reset Password' },
        component: () => import('../views/sample-pages/recoverpw-sample.vue')
    },
    {
        path: '/auth/recoverpwd-2',
        meta: { authRequired: true, title: 'Reset Password' },
        component: () => import('../views/sample-pages/recoverpwd-2.vue')
    },
    {
        path: '/auth/lock-screen',
        meta: { authRequired: true, title: 'Lock Screen' },
        component: () => import('../views/sample-pages/lockscreen.vue')
    },
    {
        path: '/auth/lock-screen-2',
        meta: { authRequired: true, title: 'Lock Screen' },
        component: () => import('../views/sample-pages/lockscreen-2.vue')
    },
    {
        path: '/auth/confirm-mail',
        meta: { authRequired: true, title: 'Confirm Mail' },
        component: () => import('../views/sample-pages/confirm-mail.vue')
    },
    {
        path: '/auth/confirm-mail-2',
        meta: { authRequired: true, title: 'Confirm Mail' },
        component: () => import('../views/sample-pages/confirm-mail-2.vue')
    },
    {
        path: '/auth/email-verification',
        meta: { authRequired: true, title: 'Email Verification' },
        component: () => import('../views/sample-pages/email-verification.vue')
    },
    {
        path: '/auth/email-verification-2',
        meta: { authRequired: true, title: 'Email Verification' },
        component: () => import('../views/sample-pages/email-verification-2.vue')
    },
    {
        path: '/auth/two-step-verification',
        meta: { authRequired: true, title: 'Two Step Verification' },
        component: () => import('../views/sample-pages/two-step-verification.vue')
    },
    {
        path: '/auth/two-step-verification-2',
        meta: { authRequired: true, title: 'Two Step Verification' },
        component: () => import('../views/sample-pages/two-step-verification-2.vue')
    },
    {
        path: '/pages/starter',
        meta: { authRequired: true, title: 'Starter Page' },
        component: () => import('../views/utility/starter.vue')
    },
    {
        path: '/pages/maintenance',
        meta: { authRequired: true, title: 'Maintenance' },
        component: () => import('../views/utility/maintenance.vue')
    },
    {
        path: '/pages/coming-soon',
        meta: { authRequired: true, title: 'Comming Soon' },
        component: () => import('../views/utility/coming-soon.vue')
    },
    {
        path: '/pages/timeline',
        meta: { authRequired: true, title: 'Timeline' },
        component: () => import('../views/utility/timeline.vue')
    },
    {
        path: '/pages/faqs',
        meta: { authRequired: true, title: 'FaQs' },
        component: () => import('../views/utility/faqs.vue')
    },
    {
        path: '/pages/pricing',
        meta: { authRequired: true, title: 'Pricing' },
        component: () => import('../views/utility/pricing.vue')
    },
    {
        path: '/pages/404',
        meta: { authRequired: true, title: '404' },
        component: () => import('../views/utility/404.vue')
    },
    {
        path: '/pages/500',
        alias: '/pages-500',
        meta: { authRequired: true, title: '500' },
        component: () => import('../views/utility/500.vue')
    },
    {
        path: '/ui/alerts',
        meta: { authRequired: true, title: 'Alerts' },
        component: () => import('../views/ui/alerts.vue')
    },
    {
        path: '/ui/buttons',
        meta: { authRequired: true, title: 'Buttons' },
        component: () => import('../views/ui/buttons.vue')
    },
    {
        path: '/ui/cards',
        meta: { authRequired: true, title: 'Cards' },
        component: () => import('../views/ui/cards.vue')
    },
    {
        path: '/ui/carousel',
        meta: { authRequired: true, title: 'Carousel' },
        component: () => import('../views/ui/carousel.vue')
    },
    {
        path: '/ui/colors',
        meta: { authRequired: true, title: 'Colors' },
        component: () => import('../views/ui/colors.vue')
    },
    {
        path: '/ui/image-cropper',
        meta: { authRequired: true, title: 'Cropper' },
        component: () => import('../views/ui/cropper.vue')
    },
    {
        path: "/ui/notifications",
        name: "Notifications",
        meta: { title: "Notifications", authRequired: true },
        component: () => import("../views/ui/notifications.vue")
    },
    {
        path: '/ui/dropdowns',
        meta: { authRequired: true, title: 'Dropdowns' },
        component: () => import('../views/ui/dropdowns.vue')
    },
    {
        path: '/ui/general',
        meta: { authRequired: true, title: 'General' },
        component: () => import('../views/ui/general.vue')
    },
    {
        path: '/ui/grid',
        meta: { authRequired: true, title: 'Grid' },
        component: () => import('../views/ui/grid.vue')
    },
    {
        path: '/ui/images',
        meta: { authRequired: true, title: 'Images' },
        component: () => import('../views/ui/images.vue')
    },
    {
        path: '/ui/lightbox',
        meta: { authRequired: true, title: 'Lightbox' },
        component: () => import('../views/ui/lightbox.vue')
    },
    {
        path: '/ui/modals',
        meta: { authRequired: true, title: 'Modals' },
        component: () => import('../views/ui/modals.vue')
    },
    {
        path: '/ui/offcanvas',
        meta: { authRequired: true, title: 'Offcanvas' },
        component: () => import('../views/ui/offcanvas.vue')
    },
    {
        path: '/ui/placeholder',
        meta: { authRequired: true, title: 'Placeholder' },
        component: () => import('../views/ui/placeholder.vue')
    },
    {
        path: '/ui/progressbars',
        meta: { authRequired: true, title: 'Progressbars' },
        component: () => import('../views/ui/progressbars.vue')
    },
    {
        path: '/ui/rangeslider',
        meta: { authRequired: true, title: 'Rangeslider' },
        component: () => import('../views/ui/rangeslider.vue')
    },
    {
        path: "/ui/session-timeout",
        name: "session-timeout",
        meta: { title: "Session Timeout", authRequired: true },
        component: () => import("../views/ui/session-timeout.vue")
    },
    {
        path: '/ui/sweet-alert',
        meta: { authRequired: true, title: 'Sweet Alert' },
        component: () => import('../views/ui/sweet-alert.vue')
    },
    {
        path: '/ui/tabs-accordions',
        meta: { authRequired: true, title: 'Tabs Accordions' },
        component: () => import('../views/ui/tabs-accordions.vue')
    },
    {
        path: '/ui/typography',
        meta: { authRequired: true, title: 'Typography' },
        component: () => import('../views/ui/typography.vue')
    },
    {
        path: '/ui/utilities',
        meta: { authRequired: true, title: 'Utilities' },
        component: () => import('../views/ui/utilities.vue')
    },
    {
        path: '/ui/video',
        meta: { authRequired: true, title: 'Video' },
        component: () => import('../views/ui/video.vue')
    },
    {
        path: '/form/elements',
        meta: { authRequired: true, title: 'Form Element' },
        component: () => import('../views/forms/elements.vue')
    },
    {
        path: '/form/layouts',
        meta: { authRequired: true, title: 'Form Layouts' },
        component: () => import('../views/forms/layouts.vue')
    },
    {
        path: '/form/advanced',
        meta: { authRequired: true, title: 'Form Advance' },
        component: () => import('../views/forms/advanced.vue')
    },
    {
        path: '/form/editor',
        meta: { authRequired: true, title: 'Form Editor' },
        component: () => import('../views/forms/ckeditor.vue')
    },
    {
        path: '/form/uploads',
        meta: { authRequired: true, title: 'Form Uploads' },
        component: () => import('../views/forms/uploads.vue')
    },
    {
        path: '/form/repeater',
        meta: { authRequired: true, title: 'Form Repeater' },
        component: () => import('../views/forms/repeater.vue')
    },
    {
        path: '/form/wizard',
        meta: { authRequired: true, title: 'Form Wizard' },
        component: () => import('../views/forms/wizard.vue')
    },
    {
        path: '/form/mask',
        meta: { authRequired: true, title: 'Form Mask' },
        component: () => import('../views/forms/mask.vue')
    },
    {
        path: '/tables/basic',
        meta: { authRequired: true, title: 'Basic Tables' },
        component: () => import('../views/tables/basictable.vue')
    },
    {
        path: '/charts/apex',
        meta: { authRequired: true, title: 'Apexchart' },
        component: () => import('../views/charts/apex.vue')
    },
    {
        path: '/charts/chartjs',
        meta: { authRequired: true, title: 'Chartjs' },
        component: () => import('../views/charts/chartjs/index.vue')
    },
    {
        path: '/icons/boxicons',
        meta: { authRequired: true, title: 'Boxicons' },
        component: () => import('../views/icons/boxicons.vue')
    },
    {
        path: '/icons/materialdesign',
        meta: { authRequired: true, title: 'Material Design' },
        component: () => import('../views/icons/materialdesign.vue')
    },
    {
        path: '/icons/dripicons',
        meta: { authRequired: true, title: 'Drip Icons' },
        component: () => import('../views/icons/dripicons.vue')
    },
    {
        path: '/icons/fontawesome',
        meta: { authRequired: true, title: 'Font Awesome' },
        component: () => import('../views/icons/fontawesome.vue')
    },
    {
        path: '/maps/google',
        meta: { authRequired: true, title: 'Google Maps' },
        component: () => import('../views/maps/google.vue')
    },
    {
        path: '/maps/leaflet',
        meta: { authRequired: true, title: 'Leaflet Maps' },
        component: () => import('../views/maps/leaflet/index.vue')
    },
    {
        path: "/maps/amcharts",
        meta: { authRequired: true, title: "Amcharts Maps" },
        component: () => import('../views/maps/amcharts/index.vue')
    },
    pmeSepRoute('/pme-sep', 'Dashboard PME / SEP'),
    pmeSepRoute('/pme-sep/configuracion', 'Configuración PME'),
    pmeSepRoute('/pme-sep/ingresos', 'Ingresos SEP'),
    pmeSepRoute('/pme-sep/estudiantes', 'Estudiantes SEP'),
    pmeSepRoute('/pme-sep/dimensiones', 'Dimensiones PME'),
    pmeSepRoute('/pme-sep/objetivos', 'Objetivos PME'),
    pmeSepRoute('/pme-sep/estrategias', 'Estrategias PME'),
    pmeSepRoute('/pme-sep/indicadores', 'Indicadores PME'),
    pmeSepRoute('/pme-sep/acciones', 'Acciones PME'),
    pmeSepRoute('/pme-sep/evidencias', 'Evidencias PME'),
    pmeSepRoute('/pme-sep/hitos', 'Hitos PME'),
    pmeSepRoute('/pme-sep/metas', 'Metas Estratégicas PME'),
    pmeSepRoute('/pme-sep/monitoreo', 'Monitoreo Reflexivo PME'),
    pmeSepRoute('/pme-sep/reportes', 'Reportes PME / SEP'),
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Before each route evaluates...
router.beforeEach(async (routeTo, routeFrom, next) => {
    // set title name
    if (routeTo.meta.title != undefined) {
        document.title = routeTo.meta.title + " | Skote Laravel 11 + Vue 3 Admin & Dashboard";
    }

    const authRequired = routeTo.matched.some((route) => route.meta.authRequired);
    if (!authRequired) return next();

    const token = localStorage.getItem('token');
    if (!token) {
        return next({ name: 'login', query: { redirectFrom: routeTo.fullPath } });
    }

    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    const requiredPermission = routeTo.meta.permission;
    if (!requiredPermission) {
        return next();
    }

    let permissions = null;
    try {
        permissions = JSON.parse(localStorage.getItem('permissions') || 'null');
    } catch (e) {
        permissions = null;
    }

    const fetchPermissions = async () => {
        try {
            const response = await axios.get('/api/me/permissions');
            const freshPermissions = response.data.data || [];
            localStorage.setItem('permissions', JSON.stringify(freshPermissions));
            return freshPermissions;
        } catch (error) {
            return [];
        }
    };

    if (!Array.isArray(permissions)) {
        permissions = await fetchPermissions();
    }

    if (permissions.includes('__superadmin__') || permissions.includes(requiredPermission)) {
        return next();
    }

    permissions = await fetchPermissions();
    if (permissions.includes('__superadmin__') || permissions.includes(requiredPermission)) {
        return next();
    }

    return next({ path: '/inicio' });

});

export default router;
