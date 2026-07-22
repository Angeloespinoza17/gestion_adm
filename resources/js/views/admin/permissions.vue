<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import EmptyState from "../../components/staff/permissions/empty-state.vue";
import MetricCard from "../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../components/staff/permissions/status-badge.vue";

const permissionTooltipBySlug = {
  // Administración y sitio público
  administrar_usuarios: "Permite administrar usuarios: crear, editar, eliminar, activar/desactivar, asignar roles y asociar cargos.",
  administrar_roles: "Permite administrar roles, incluyendo sus permisos y módulos visibles.",
  administrar_permisos: "Permite acceder al catálogo de permisos y crear nuevos permisos del sistema.",
  administrar_modulos: "Permite administrar módulos del sistema y ver el dashboard/reportes de gestión superadmin.",
  administrar_cargos: "Permite crear, editar y activar/desactivar cargos administrativos.",
  administrar_organigrama: "Permite ver y sincronizar relaciones del organigrama entre funcionarios.",
  administrar_departamentos: "Permite administrar departamentos: crear, editar, activar/desactivar y eliminar.",
  ver_noticias: "Permite ver el listado, detalle y catálogos de noticias del sitio web.",
  gestionar_noticias: "Permite crear, editar y eliminar noticias del sitio web.",
  ver_eventos: "Permite ver el listado, detalle y catálogos de eventos del sitio web.",
  gestionar_eventos: "Permite crear, editar y eliminar eventos del sitio web.",
  ver_contactos_sitio: "Permite ver mensajes recibidos desde el formulario de contacto del sitio web.",
  gestionar_contactos_sitio: "Permite actualizar el estado, notas o eliminación de mensajes de contacto del sitio web.",

  // Generales y permisos heredados
  ver_dashboard: "Permiso general heredado para dashboards. En las rutas actuales no encontré un guard directo con este slug.",
  ver_reportes: "Permiso general heredado para reportes. En las rutas actuales no encontré un guard directo con este slug.",
  exportar_reportes: "Permiso general heredado para exportación de reportes. En las rutas actuales no encontré un guard directo con este slug.",
  ver_salud: "Permiso legado de Salud/Enfermería. El módulo actual usa otros slugs, por lo que no encontré rutas activas protegidas directamente con este permiso.",
  ver_psicologia: "Permiso legado de Psicología. En las rutas actuales no encontré un guard directo con este slug.",

  // Estudiantes
  ver_estudiantes: "Permite ver el listado de estudiantes, catálogos, niveles, años académicos, cursos y exportación básica de estudiantes.",
  crear_estudiantes: "Permite crear nuevas fichas de estudiantes.",
  editar_estudiantes: "Permite actualizar datos de la ficha de estudiante.",
  eliminar_estudiantes: "Permite revisar el impacto y eliminar definitivamente una ficha de estudiante, su cuenta y registros dependientes.",
  ver_ficha_estudiante: "Permite abrir y consultar la ficha detallada de un estudiante.",
  administrar_anos_academicos: "Permite crear, editar y activar años académicos.",
  administrar_cursos_academicos: "Permite administrar niveles y cursos académicos: crear, editar y eliminar niveles, y crear o editar cursos.",
  gestionar_matriculas_estudiantes: "Permite gestionar matrículas: cambios de curso, retiros, reingresos y registros de matrícula por estudiante.",
  promover_estudiantes: "Permite ejecutar la promoción anual de estudiantes.",

  // Horarios
  ver_horarios: "Permite consultar horarios docentes, por curso, configuración, jornadas, asignaturas, planes de estudio, contratos docentes y conflictos.",
  editar_horarios: "Permite crear, editar, mover, validar y eliminar capas o bloques de horario.",
  configurar_horarios: "Permite actualizar la configuración general del módulo de horarios.",
  configurar_jornadas: "Permite administrar jornadas escolares: crear, editar, eliminar, duplicar y asignar a niveles o cursos.",
  configurar_plan_estudio: "Permite administrar asignaturas y planes de estudio, incluyendo horas por asignatura.",
  configurar_contratos_docentes: "Permite crear y actualizar contratos docentes usados por el módulo de horarios.",
  forzar_excepciones_horario: "Permite guardar o mover bloques de horario forzando advertencias o errores permitidos de validación.",
  ver_reportes_carga_horaria: "Permite consultar reportes/resúmenes de carga horaria por docente y por curso.",

  // Portería
  ver_porteria: "Permite entrar al módulo de Portería, ver dashboard, consulta de estudiantes y pantallas operativas del módulo.",
  ver_historial_porteria: "Permite consultar historiales de retiros, objetos, mercadería, visitas, proveedores, bitácora, llaves y reportes de Portería.",
  registrar_retiro_porteria: "Permite registrar retiros de estudiantes desde Portería.",
  autorizar_retiros_porteria: "Permite resolver o anular retiros observados de estudiantes.",
  registrar_objetos_porteria: "Permite registrar recepción de objetos en Portería.",
  entregar_objetos_porteria: "Permite cambiar el estado de objetos recibidos, incluyendo su entrega.",
  registrar_mercaderia_porteria: "Permite registrar ingresos o movimientos de mercadería en Portería.",
  entregar_mercaderia_porteria: "Permite cambiar el estado de mercadería registrada, incluyendo su entrega.",
  registrar_visitas_porteria: "Permite registrar visitas y marcar su salida.",
  registrar_proveedores_porteria: "Permite registrar proveedores o servicios externos y marcar su salida.",
  registrar_bitacora_porteria: "Permite registrar entradas en la bitácora diaria de Portería.",
  gestionar_llaves_porteria: "Permite administrar grupos y llaves, registrar préstamos y registrar devoluciones.",
  exportar_reportes_porteria: "Permite exportar reportes de Portería cuando el módulo ofrece descarga.",

  // Mantención
  ver_mantencion: "Permite ver áreas técnicas y órdenes de trabajo de Mantención, incluyendo catálogos y detalles.",
  crear_ot: "Permite crear órdenes de trabajo y generar una OT desde hallazgos de checklist de visita.",
  editar_ot: "Permite editar o eliminar órdenes de trabajo, incluyendo cambios de estado.",
  cerrar_ot: "Permiso definido para cierre de OT; en las rutas actuales el cierre se controla mediante editar_ot.",
  ver_reportes_mantencion: "Permite ver reportes de Mantención, como carga de trabajo.",
  exportar_mantencion: "Permite exportar reportes operativos de Mantención, como el reporte por responsable.",
  ver_visitas_mantencion: "Permite ver visitas de Mantención, detalles y checklist.",
  gestionar_visitas_mantencion: "Permite crear, editar, eliminar visitas de Mantención y completar checklist con fotos.",
  ver_plan_anual_mantencion: "Permite ver el plan anual de Mantención y sus catálogos.",
  gestionar_plan_anual_mantencion: "Permite crear, editar y eliminar actividades del plan anual de Mantención.",

  // Inventario
  ver_inventario: "Permite ver bienes, fichas, gestión por dependencia, categorías, subcategorías, proveedores, movimientos y stock.",
  crear_inventario: "Permite crear bienes de inventario y proveedores.",
  editar_inventario: "Permite editar bienes, proveedores, auditorías por dependencia, fotos y datos asociados.",
  eliminar_inventario: "Permite eliminar bienes y proveedores de inventario.",
  dar_baja_inventario: "Permiso definido para dar de baja bienes; no encontré un guard directo activo con este slug en las rutas actuales.",
  mover_inventario: "Permite mover bienes entre dependencias y registrar movimientos de stock.",
  ver_reportes_inventario: "Permite ver dashboards y reportes de inventario, incluyendo bajo stock.",
  exportar_inventario: "Permite exportar datos de inventario desde pantallas que habilitan descarga por permiso.",
  administrar_categorias_inventario: "Permite crear, editar y eliminar categorías y subcategorías de inventario.",
  subir_documentos_inventario: "Permite adjuntar documentos a bienes de inventario.",
  eliminar_documentos_inventario: "Permite eliminar documentos y fotos asociados a bienes de inventario.",
  imprimir_etiquetas_inventario: "Permite acceder a la generación/impresión de etiquetas de inventario.",

  // Funcionarios y permisos del personal
  ver_funcionarios: "Permite ver listado, catálogos, departamentos y fichas de funcionarios.",
  gestionar_funcionarios: "Permite crear, editar y activar/desactivar funcionarios.",
  eliminar_funcionarios: "Permite eliminar fichas de funcionarios.",
  subir_documentos_funcionarios: "Permite subir y eliminar documentos en la ficha de un funcionario.",
  ver_permisos_personal: "Permite ver dashboard, bandeja, detalle, documentos, reportes y tipos de permisos del personal. Las acciones finales se validan además por policy.",
  solicitar_permisos_personal: "Permite crear, editar y enviar solicitudes propias o del personal autorizado.",
  revisar_permisos_equipo: "Permite ver y actuar sobre solicitudes en etapa de jefatura para equipo directo, organigrama o departamentos a cargo.",
  aprobar_permisos_direccion: "Permite ver todas las solicitudes y actuar cuando el flujo está en etapa de Dirección.",
  revisar_permisos_rrhh: "Permite ver todas las solicitudes, revisar la etapa RR.HH., cancelar solicitudes y validar documentos.",
  administrar_tipos_permisos_personal: "Permite administrar tipos de permiso del personal y también habilita la gestión de destinatarios según policy.",
  administrar_destinatarios_permisos_personal: "Permite configurar quién debe enterarse de cada tipo de permiso y revisar destinatarios por funcionario.",
  exportar_permisos_personal: "Permite exportar reportes de permisos del personal desde las vistas que ofrecen descarga.",
  validar_documentos_permisos_personal: "Permite validar documentos adjuntos a solicitudes de permisos del personal.",
  gestionar_reemplazos_permisos_personal: "Permite asignar o actualizar reemplazos asociados a una solicitud de permiso.",
  ver_reportes_permisos_personal: "Permite consultar reportes específicos de permisos del personal.",

  // Tareas
  ver_tareas: "Permite ver el backlog y, en el controlador actual, crear, actualizar, eliminar y cambiar estado de tareas visibles propias/asignadas.",
  gestionar_tareas: "Permite gestionar tareas propias; se usa para validar creación o edición sobre tareas del usuario dueño.",
  ver_tareas_equipo: "Permite ver y administrar backlogs de equipo completos.",
  administrar_asignadores_tareas: "Permite administrar asignadores de tareas y también da acceso de gestión sobre backlogs.",

  // Contratos
  ver_contratos: "Permite ver contratos, catálogos, fichas y consultar plantillas, cláusulas y firmantes.",
  gestionar_contratos: "Permite previsualizar, crear, editar y cambiar estado de contratos.",
  eliminar_contratos: "Permite eliminar contratos.",
  exportar_contratos: "Permite descargar contratos en formato Word.",
  administrar_plantillas_contrato: "Permite administrar plantillas de contrato: catálogos, previsualización, creación, edición, activación y eliminación.",
  administrar_clausulas_contrato: "Permite administrar cláusulas contractuales: catálogos, previsualización, creación, edición, activación y eliminación.",
  administrar_firmas_contrato: "Permite administrar firmantes de contrato: catálogos, creación, edición, activación y eliminación.",
  editar_contratos_firmados: "Permite modificar contratos que ya están firmados; sin este permiso el controlador bloquea esos cambios.",

  // Dependencias, reservas y espacios
  ver_dependencias: "Permite ver dependencias, tipos de dependencia, gestores de aprobación y fichas de espacios/dependencias.",
  crear_dependencias: "Permite crear dependencias y tipos de dependencia.",
  editar_dependencias: "Permite editar dependencias, tipos de dependencia y gestores de aprobación.",
  eliminar_dependencias: "Permite eliminar dependencias y tipos de dependencia.",
  ver_reservas: "Permite ver reservas, detalle, calendario y catálogos de reservas.",
  crear_reservas: "Permite crear reservas de dependencias o espacios.",
  editar_reservas: "Permite editar reservas no cerradas; reservas aprobadas o finalizadas requieren administrar_calendario.",
  cancelar_reservas: "Permite cancelar reservas mientras no estén finalizadas.",
  aprobar_reservas: "Permite aprobar reservas pendientes, además de aprobadores asignados y administradores de calendario.",
  rechazar_reservas: "Permite rechazar reservas pendientes, además de aprobadores asignados y administradores de calendario.",
  exportar_reservas: "Permite exportar reservas desde el calendario o vistas que ofrecen descarga.",
  administrar_calendario: "Permite administrar el calendario de reservas: editar reservas aprobadas/finalizadas, aprobar/rechazar y exportar reservas o estadísticas.",
  ver_estadisticas_espacios: "Permite ver estadísticas de uso de espacios y dependencias.",
  exportar_estadisticas_espacios: "Permite exportar estadísticas de espacios desde la pantalla de estadísticas.",

  // Seguridad y rondas
  ver_rondas_seguridad: "Permite acceder al módulo de rondas, ver dashboard, turnos, rondas e incidencias visibles.",
  gestionar_turnos_nochero: "Permite crear, editar, iniciar/finalizar turnos de nochero y ver todos los turnos/incidencias.",
  registrar_rondas_seguridad: "Permite registrar rondas dentro de turnos de seguridad.",
  gestionar_novedades_rondas: "Permite gestionar novedades/incidencias de rondas y ver todas las incidencias.",
  exportar_rondas_seguridad: "Permite exportar información de rondas; quienes gestionan turnos o novedades también pueden exportar.",

  // Módulos específicos vigentes/legados
  ver_convivencia: "Permite acceder a las vistas del módulo Convivencia Escolar: dashboard, casos, denuncias, protocolos, entrevistas, bitácora, sociogramas e indicadores.",
  ver_prevencion_riesgos: "Permite acceder a Prevención de Riesgos: dashboard, extintores, accidentes, emergencias, EPP, capacitaciones, documentos y reportes.",
};

const permissionActionHints = [
  { prefix: "administrar_", action: "configurar y administrar" },
  { prefix: "gestionar_", action: "crear, editar y administrar" },
  { prefix: "exportar_", action: "exportar" },
  { prefix: "eliminar_", action: "eliminar" },
  { prefix: "aprobar_", action: "aprobar" },
  { prefix: "validar_", action: "validar" },
  { prefix: "revisar_", action: "revisar" },
  { prefix: "editar_", action: "editar" },
  { prefix: "crear_", action: "crear" },
  { prefix: "subir_", action: "subir" },
  { prefix: "mover_", action: "mover" },
  { prefix: "promover_", action: "promover" },
  { prefix: "ver_", action: "ver y consultar" },
];

const permissionTargetArticles = {
  alertas: "las",
  atenciones: "las",
  biblioteca: "la",
  calendario: "el",
  cargos: "los",
  contactos: "los",
  contratos: "los",
  dashboard: "el",
  dependencias: "las",
  documentos: "los",
  enfermería: "la",
  enfermeria: "la",
  estadísticas: "las",
  estudiantes: "los",
  eventos: "los",
  funcionarios: "los",
  historial: "el",
  horarios: "los",
  inventario: "el",
  llaves: "las",
  matriculas: "las",
  matrículas: "las",
  mantención: "la",
  módulo: "el",
  noticias: "las",
  novedades: "las",
  permisos: "los",
  plan: "el",
  porteria: "la",
  portería: "la",
  prevención: "la",
  reportes: "los",
  reservas: "las",
  roles: "los",
  rondas: "las",
  tareas: "las",
  turnos: "los",
  usuarios: "los",
  visitas: "las",
};

const permissionTargetWords = {
  enfermeria: "enfermería",
  estadisticas: "estadísticas",
  mantencion: "mantención",
  matriculas: "matrículas",
  modulo: "módulo",
  porteria: "portería",
  prevencion: "prevención",
};

const emptyForm = () => ({
  name: "",
  slug: "",
  description: "",
  active: true,
});

export default {
  components: { Layout, LoadingState, EmptyState, MetricCard, PageHeader, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      permissions: [],
      showModal: false,
      form: emptyForm(),
      filters: {
        search: "",
        active: null,
      },
      error: null,
      success: null,
    };
  },
  computed: {
    filteredPermissions() {
      const search = this.filters.search.trim().toLowerCase();

      return this.permissions.filter((permission) => {
        const matchesSearch = !search
          || [permission.name, permission.slug, permission.description, this.permissionExplanation(permission)]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(search));
        const matchesActive = this.filters.active === null || Boolean(permission.active) === this.filters.active;

        return matchesSearch && matchesActive;
      });
    },
    summaryCards() {
      return [
        {
          label: "Permisos",
          value: this.permissions.length,
          hint: "Registrados",
          icon: "bx-lock-open",
          variant: "primary",
        },
        {
          label: "Activos",
          value: this.permissions.filter((permission) => permission.active).length,
          hint: "Disponibles",
          icon: "bx-check-circle",
          variant: "success",
        },
        {
          label: "Inactivos",
          value: this.permissions.filter((permission) => !permission.active).length,
          hint: "No asignables",
          icon: "bx-pause-circle",
          variant: "neutral",
        },
      ];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const response = await axios.get("/api/admin/permissions");
        this.permissions = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    resetFilters() {
      this.filters = { search: "", active: null };
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/admin/permissions", {
          name: this.form.name,
          slug: this.form.slug,
          description: this.form.description || null,
          active: this.form.active,
        });
        this.success = "Permiso creado.";
        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
    permissionExplanation(permission) {
      const slug = String(permission?.slug || "").trim().toLowerCase();
      const name = String(permission?.name || "").trim();

      if (permissionTooltipBySlug[slug]) {
        return permissionTooltipBySlug[slug];
      }

      const description = String(permission?.description || "").trim();
      if (description) {
        return description;
      }

      if (!slug) {
        return name
          ? `Da acceso a ${name}.`
          : "Este permiso no tiene una explicación registrada.";
      }

      const hint = permissionActionHints.find((item) => slug.startsWith(item.prefix));
      if (!hint) {
        return name
          ? `Da acceso a ${name}.`
          : `Da acceso asociado al permiso ${slug}.`;
      }

      return `Permite ${hint.action} ${this.permissionTargetLabel(slug.slice(hint.prefix.length))}.`;
    },
    permissionTargetLabel(value) {
      const words = String(value || "")
        .split("_")
        .filter(Boolean)
        .map((word) => permissionTargetWords[word] || word);
      const target = words.join(" ").trim() || "este recurso";
      const article = permissionTargetArticles[words[0]] || "el recurso";

      return `${article} ${target}`;
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader
      eyebrow="Administración"
      title="Permisos"
      subtitle="Catálogo base de permisos disponibles para roles y módulos del sistema."
      icon="bx-lock-open"
    >
      <template #actions>
        <BButton variant="primary" @click="openCreate">
          <i class="bx bx-plus me-1"></i>Nuevo permiso
        </BButton>
      </template>
    </PageHeader>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row g-3 mb-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-4">
        <MetricCard
          :label="card.label"
          :value="card.value"
          :hint="card.hint"
          :icon="card.icon"
          :variant="card.variant"
        />
      </div>
    </div>

    <BCard class="permission-card">
      <template #header>
        <div class="permission-section-title mb-0">
          <i class="bx bx-list-ul"></i>
          <span>Listado</span>
        </div>
      </template>

      <div class="permission-filter-card mb-3">
        <div class="row g-3 align-items-end">
          <div class="col-lg-8">
            <label class="form-label">Búsqueda</label>
            <div class="permission-input-icon">
              <i class="bx bx-search"></i>
              <BFormInput v-model="filters.search" placeholder="Nombre, slug o descripción" />
            </div>
          </div>
          <div class="col-lg-2">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="filters.active">
              <option :value="null">Todos</option>
              <option :value="true">Activos</option>
              <option :value="false">Inactivos</option>
            </BFormSelect>
          </div>
          <div class="col-lg-2">
            <BButton variant="outline-secondary" class="w-100" @click="resetFilters">
              <i class="bx bx-reset me-1"></i>Limpiar
            </BButton>
          </div>
        </div>
      </div>

      <LoadingState v-if="loading" message="Cargando permisos..." compact />
      <EmptyState
        v-else-if="!filteredPermissions.length"
        icon="bx-search-alt"
        title="Sin permisos"
        text="No hay permisos para los filtros seleccionados."
      />
      <BTable
        v-else
        :items="filteredPermissions"
        small
        responsive
        hover
        :fields="[
          { key: 'name', label: 'Nombre' },
          { key: 'slug', label: 'Slug' },
          { key: 'description', label: 'Descripción' },
          { key: 'active', label: 'Estado' },
        ]"
      >
        <template #cell(name)="{ item }">
          <div class="permission-name-cell">
            <div class="permission-name-cell__text">
              <div class="fw-semibold">{{ item.name }}</div>
              <div class="text-muted small">ID {{ item.id }}</div>
            </div>
            <button
              type="button"
              class="permission-help-button"
              v-b-tooltip.hover.top
              :title="permissionExplanation(item)"
              :aria-label="`Explicación del permiso ${item.name}`"
            >
              <i class="bx bx-question-mark"></i>
            </button>
          </div>
        </template>
        <template #cell(description)="{ item }">
          <span class="text-muted">{{ item.description || "Sin descripción" }}</span>
        </template>
        <template #cell(active)="{ item }">
          <StatusBadge :status="item.active ? 'activo' : 'inactivo'" />
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" title="Nuevo permiso" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <BFormInput v-model="form.name" />
      </div>
      <div class="mb-3">
        <label class="form-label">Slug</label>
        <BFormInput v-model="form.slug" />
      </div>
      <div class="mb-3">
        <label class="form-label">Descripción</label>
        <BFormTextarea v-model="form.description" rows="2" />
      </div>
      <div class="mb-3">
        <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-save me-1'"></i>
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
