<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const moduleDescriptionBySlug = {
  public_site: "Agrupa las herramientas para administrar el contenido visible en el sitio público.",
  public_site_news: "Permite gestionar noticias publicadas en el sitio web institucional.",
  public_site_events: "Permite gestionar eventos y actividades publicadas en el sitio web.",
  public_site_contacts: "Permite revisar y responder mensajes recibidos desde el formulario de contacto.",
  dashboard: "Muestra el panel inicial con indicadores y accesos principales del sistema.",

  students: "Agrupa la administración académica y de fichas de estudiantes.",
  students_directory: "Permite consultar y mantener el listado de estudiantes.",
  students_levels: "Permite administrar niveles educativos usados por cursos y matrículas.",
  students_academic_years: "Permite administrar años académicos y su estado operativo.",
  students_courses: "Permite administrar cursos por año académico y nivel.",
  students_promotions: "Permite ejecutar y revisar procesos de promoción anual.",
  students_movements: "Permite gestionar cambios de curso, retiros y reingresos de estudiantes.",

  schedule: "Agrupa la configuración y consulta de horarios docentes y cursos.",
  schedule_teacher: "Permite consultar el horario por docente.",
  schedule_course: "Permite consultar el horario por curso.",
  schedule_config: "Permite definir reglas y parámetros generales del módulo de horarios.",
  schedule_jornadas: "Permite administrar jornadas escolares y bloques horarios.",
  schedule_study_plans: "Permite configurar asignaturas y planes de estudio.",
  schedule_contracts: "Permite configurar contratos docentes usados para carga horaria.",
  schedule_conflicts: "Permite revisar conflictos o advertencias generadas por horarios.",

  porter: "Agrupa el control de accesos, retiros, visitas, objetos, proveedores y bitácora de portería.",
  porter_dashboard: "Muestra el panel operativo de portería.",
  porter_students: "Permite buscar estudiantes y revisar contexto rápido para portería.",
  porter_withdrawals: "Permite registrar y controlar retiros de estudiantes.",
  porter_received_items: "Permite registrar recepción y entrega de objetos.",
  porter_goods: "Permite registrar mercadería recibida y entregada.",
  porter_visits: "Permite registrar visitas y marcar entradas o salidas.",
  porter_providers: "Permite registrar proveedores o servicios externos.",
  porter_daily_log: "Permite mantener la bitácora diaria de portería.",
  porter_keys: "Permite controlar llaves, préstamos y devoluciones.",
  porter_reports: "Permite consultar reportes operativos de portería.",

  guardians: "Permite consultar y administrar información de apoderados.",

  staff: "Agrupa la gestión de funcionarios, departamentos y datos laborales.",
  staff_directory: "Permite consultar y mantener fichas de funcionarios.",
  staff_departments: "Permite administrar departamentos y responsables internos.",

  staff_permissions: "Agrupa solicitudes, revisión, reportes y configuración de permisos del personal.",
  staff_permissions_dashboard: "Muestra indicadores y resumen de permisos del personal.",
  staff_permissions_requests: "Permite crear y revisar solicitudes propias de permisos.",
  staff_permissions_review: "Permite revisar solicitudes pendientes según flujo de aprobación.",
  staff_permissions_reports: "Permite consultar reportes de permisos del personal.",
  staff_permissions_types: "Permite administrar tipos de permiso disponibles.",
  staff_permissions_watchers: "Permite configurar destinatarios que deben enterarse de solicitudes.",

  tasks: "Agrupa el backlog y la configuración de asignadores de tareas.",
  tasks_backlog: "Permite consultar y gestionar tareas propias o de equipo según permisos.",
  tasks_assigners: "Permite administrar usuarios habilitados para asignar tareas.",

  contracts: "Agrupa contratos, plantillas, cláusulas y firmantes.",
  contracts_list: "Permite consultar y gestionar contratos generados.",
  contracts_templates: "Permite administrar plantillas usadas para generar contratos.",
  contracts_clauses: "Permite administrar cláusulas reutilizables de contratos.",
  contracts_signatures: "Permite administrar firmantes autorizados para contratos.",

  infirmary: "Permite gestionar atenciones, ficha médica, medicamentos, accidentes y reportes de enfermería.",
  psychology: "Permite acceder al módulo de psicología y seguimiento profesional.",
  convivencia: "Permite gestionar casos, denuncias, protocolos, entrevistas, bitácoras y reportes de convivencia escolar.",
  risk_prevention: "Permite gestionar prevención de riesgos: accidentes, EPP, extintores, capacitaciones y documentos.",

  maintenance: "Agrupa órdenes de trabajo, visitas, áreas técnicas y planificación anual de mantención.",
  maintenance_dependencies: "Permite administrar áreas técnicas y dependencias operativas de mantención.",
  maintenance_work_orders: "Permite gestionar órdenes de trabajo de mantención.",
  maintenance_workload: "Permite revisar carga de trabajo por responsables y estados.",
  maintenance_visits: "Permite planificar visitas y checklist de mantención.",
  maintenance_annual_plans: "Permite administrar el plan anual de mantención.",

  inventory: "Agrupa bienes, movimientos, categorías, proveedores, reportes y etiquetas de inventario.",
  inventory_items: "Permite consultar y gestionar bienes inventariados.",
  inventory_management: "Permite registrar movimientos, stock y gestión operativa del inventario.",
  inventory_categories: "Permite administrar categorías y subcategorías de inventario.",
  inventory_suppliers: "Permite administrar proveedores asociados al inventario.",
  inventory_reports: "Permite consultar reportes e indicadores de inventario.",
  inventory_labels: "Permite generar etiquetas para bienes inventariados.",

  reports: "Permite acceder al módulo general de reportes.",

  spaces: "Agrupa dependencias, reservas, aprobadores, calendario y estadísticas de uso de espacios.",
  spaces_dependencies: "Permite administrar dependencias y espacios reservables.",
  spaces_dependency_types: "Permite administrar tipos de dependencia.",
  spaces_approvers: "Permite configurar gestores y aprobadores de reservas.",
  spaces_reservations: "Permite consultar y gestionar reservas de espacios.",
  spaces_calendar: "Permite visualizar reservas en formato calendario.",
  spaces_statistics: "Permite consultar estadísticas de uso de espacios.",

  security: "Agrupa el control de nochero, turnos, rondas e incidencias de seguridad.",
  security_dashboard: "Muestra el panel operativo de rondas y seguridad.",
  security_shifts: "Permite gestionar turnos, rondas y registros de nochero.",
  security_incidents: "Permite revisar y gestionar novedades o incidencias pendientes.",

  settings: "Agrupa la administración base del sistema: usuarios, roles, permisos, módulos y cargos.",
  settings_superadmin_dashboard: "Muestra indicadores de administración general y salud del sistema.",
  settings_users: "Permite administrar usuarios y accesos al sistema.",
  settings_roles: "Permite administrar roles, permisos y módulos asignados.",
  settings_permissions: "Permite administrar el catálogo de permisos del sistema.",
  settings_modules: "Permite administrar el catálogo de módulos visibles por rol.",
  settings_cargos: "Permite administrar cargos institucionales.",
  settings_organigram: "Permite consultar y sincronizar relaciones del organigrama.",

  pme_sep: "Permite gestionar planificación PME/SEP, acciones, evidencias, monitoreo y reportes.",
  biblioteca: "Permite gestionar biblioteca escolar, catálogo, préstamos, reservas, plan lector y reportes.",
  accounting: "Permite gestionar contabilidad, presupuestos, egresos, ingresos, rendiciones y reportes.",
  remuneration: "Permite gestionar remuneraciones, liquidaciones, pagos, períodos y procesos de RR.HH.",
  informatica: "Permite gestionar equipos, préstamos, mantenciones y reportes de informática.",
  apoyo_profesional: "Permite registrar atenciones, derivaciones, planes, seguimientos y reportes del equipo de apoyo.",
  centro_apuntes: "Permite gestionar solicitudes de impresión, máquinas, insumos, entregas y reportes del pañol.",
  relevant_calendar: "Permite gestionar fechas relevantes, procesos, instituciones y responsables del calendario institucional.",
};

const fallbackWords = {
  academic: "académicos",
  accounting: "contabilidad",
  annual: "anual",
  approvers: "aprobadores",
  assignments: "asignaciones",
  calendar: "calendario",
  categories: "categorías",
  centro: "centro",
  config: "configuración",
  contracts: "contratos",
  course: "curso",
  dashboard: "dashboard",
  dependencies: "dependencias",
  directory: "listado",
  events: "eventos",
  infirmary: "enfermería",
  inventory: "inventario",
  maintenance: "mantención",
  modules: "módulos",
  permissions: "permisos",
  porter: "portería",
  prevention: "prevención",
  reports: "reportes",
  reservations: "reservas",
  schedule: "horarios",
  security: "seguridad",
  settings: "configuración",
  staff: "funcionarios",
  statistics: "estadísticas",
  students: "estudiantes",
  tasks: "tareas",
  users: "usuarios",
};

const emptyForm = () => ({
  id: null,
  name: "",
  slug: "",
  frontend_route: "",
  icon: "",
  sort_order: 0,
  active: true,
  parent_id: null,
});

function compareModules(a, b) {
  const orderA = Number(a.sort_order ?? 0);
  const orderB = Number(b.sort_order ?? 0);

  if (orderA !== orderB) {
    return orderA - orderB;
  }

  return String(a.name || "").localeCompare(String(b.name || ""), "es", { sensitivity: "base" });
}

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      modules: [],
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
    isEditing() {
      return Boolean(this.form.id);
    },
    moduleLookup() {
      return this.modules.reduce((lookup, module) => {
        lookup[module.id] = module;
        return lookup;
      }, {});
    },
    rootModules() {
      return this.modules
        .filter((module) => module.parent_id === null)
        .slice()
        .sort(compareModules);
    },
    orderedModules() {
      const childrenByParent = new Map();

      this.modules.forEach((module) => {
        const key = module.parent_id ?? null;
        const children = childrenByParent.get(key) || [];
        children.push(module);
        childrenByParent.set(key, children);
      });

      const ordered = [];
      const visit = (module, depth = 0) => {
        const children = (childrenByParent.get(module.id) || []).slice().sort(compareModules);
        ordered.push({
          ...module,
          _depth: depth,
          _childrenCount: children.length,
          _parentName: this.moduleLookup[module.parent_id]?.name || null,
        });
        children.forEach((child) => visit(child, depth + 1));
      };

      this.rootModules.forEach((module) => visit(module));

      const orderedIds = new Set(ordered.map((module) => module.id));
      this.modules
        .filter((module) => !orderedIds.has(module.id))
        .slice()
        .sort(compareModules)
        .forEach((module) => {
          ordered.push({
            ...module,
            _depth: 0,
            _childrenCount: 0,
            _parentName: this.moduleLookup[module.parent_id]?.name || "Sin padre encontrado",
            _orphan: true,
          });
        });

      return ordered;
    },
    filteredModules() {
      const search = this.filters.search.trim().toLowerCase();
      const activeFilter = this.filters.active;

      const statusMatches = (module) => activeFilter === null || Boolean(module.active) === activeFilter;
      const searchMatches = (module) => {
        if (!search) {
          return true;
        }

        return [
          module.name,
          module.slug,
          module.frontend_route,
          module.icon,
          module._parentName,
          this.moduleDescription(module),
        ]
          .filter(Boolean)
          .some((value) => String(value).toLowerCase().includes(search));
      };

      if (!search) {
        return this.orderedModules.filter(statusMatches);
      }

      const visibleIds = new Set();
      this.orderedModules.forEach((module) => {
        if (!statusMatches(module) || !searchMatches(module)) {
          return;
        }

        visibleIds.add(module.id);
        let parent = this.moduleLookup[module.parent_id];
        while (parent) {
          visibleIds.add(parent.id);
          parent = this.moduleLookup[parent.parent_id];
        }
      });

      return this.orderedModules.filter((module) => visibleIds.has(module.id));
    },
    parentOptions() {
      return [{ value: null, text: "Sin padre" }].concat(
        this.rootModules
          .filter((module) => module.id !== this.form.id)
          .map((module) => ({ value: module.id, text: module.name }))
      );
    },
    summaryCards() {
      const activeCount = this.modules.filter((module) => module.active).length;
      const rootCount = this.modules.filter((module) => module.parent_id === null).length;
      const childCount = this.modules.length - rootCount;

      return [
        { label: "Módulos", value: this.modules.length, hint: "Registrados", icon: "bx-grid-alt", variant: "primary" },
        { label: "Principales", value: rootCount, hint: "Sin padre", icon: "bx-category", variant: "info" },
        { label: "Submódulos", value: childCount, hint: "Dentro de un módulo", icon: "bx-subdirectory-right", variant: "warning" },
        { label: "Activos", value: activeCount, hint: "Disponibles", icon: "bx-check-circle", variant: "success" },
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
        const response = await axios.get("/api/admin/modules");
        this.modules = response.data.data || [];
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
    openEdit(mod) {
      this.form = {
        id: mod.id,
        name: mod.name,
        slug: mod.slug,
        frontend_route: mod.frontend_route || "",
        icon: mod.icon || "",
        sort_order: mod.sort_order || 0,
        active: Boolean(mod.active),
        parent_id: mod.parent_id ?? null,
      };
      this.showModal = true;
    },
    resetFilters() {
      this.filters = { search: "", active: null };
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        const payload = {
          ...this.form,
          frontend_route: this.form.frontend_route || null,
          icon: this.form.icon || null,
        };

        if (this.isEditing) {
          await axios.put(`/api/admin/modules/${this.form.id}`, payload);
          this.success = "Módulo actualizado.";
        } else {
          await axios.post("/api/admin/modules", payload);
          this.success = "Módulo creado.";
        }
        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(mod) {
      try {
        await axios.put(`/api/admin/modules/${mod.id}/active`, { active: !mod.active });
        this.success = mod.active ? "Módulo desactivado." : "Módulo activado.";
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
    moduleDescription(module) {
      const slug = String(module?.slug || "").trim();
      if (moduleDescriptionBySlug[slug]) {
        return moduleDescriptionBySlug[slug];
      }

      const name = String(module?.name || "este módulo").trim();
      const target = slug
        .split("_")
        .filter(Boolean)
        .map((word) => fallbackWords[word] || word)
        .join(" ");

      if (module?.parent_id) {
        const parentName = this.moduleLookup[module.parent_id]?.name || "su módulo padre";
        return `Submódulo de ${parentName}: permite acceder a ${target || name}.`;
      }

      return `Módulo principal para acceder y gestionar ${target || name}.`;
    },
    moduleIconClass(module) {
      const icon = String(module?.icon || (module?._depth ? "bx-subdirectory-right" : "bx-grid-alt")).trim();
      const normalized = icon.replace(/^bx\s+/, "");

      return ["bx", normalized];
    },
    moduleTypeLabel(module) {
      if (module._orphan) {
        return "Sin padre";
      }

      return module.parent_id ? "Submódulo" : "Principal";
    },
    routeLabel(module) {
      return module.frontend_route || "Contenedor";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="modules-page">
      <div class="modules-header">
        <div>
          <div class="modules-eyebrow">Administración</div>
          <h4 class="modules-title">Módulos</h4>
          <p class="modules-subtitle">
            Catálogo jerárquico de pantallas disponibles para asignar visibilidad por rol.
          </p>
        </div>
        <BButton variant="primary" @click="openCreate">
          <i class="bx bx-plus me-1"></i>Nuevo módulo
        </BButton>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

      <div class="row g-3 mb-3">
        <div v-for="card in summaryCards" :key="card.label" class="col-sm-6 col-xl-3">
          <div class="modules-metric">
            <div :class="['modules-metric__icon', `modules-metric__icon--${card.variant}`]">
              <i :class="['bx', card.icon]"></i>
            </div>
            <div>
              <div class="modules-metric__label">{{ card.label }}</div>
              <div class="modules-metric__value">{{ card.value }}</div>
              <div class="modules-metric__hint">{{ card.hint }}</div>
            </div>
          </div>
        </div>
      </div>

      <BCard class="modules-card">
        <template #header>
          <div class="modules-card__header">
            <div class="modules-section-title">
              <i class="bx bx-list-ul"></i>
              <span>Listado ordenado</span>
            </div>
            <span class="modules-count">{{ filteredModules.length }} visibles</span>
          </div>
        </template>

        <div class="modules-filter mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-8">
              <label class="form-label">Búsqueda</label>
              <div class="modules-input-icon">
                <i class="bx bx-search"></i>
                <BFormInput v-model="filters.search" placeholder="Nombre, slug, ruta o descripción" />
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

        <BTable
          :items="filteredModules"
          :busy="loading"
          small
          responsive
          hover
          class="modules-table"
          :fields="[
            { key: 'name', label: 'Módulo' },
            { key: 'description', label: 'Descripción' },
            { key: 'frontend_route', label: 'Ruta' },
            { key: 'sort_order', label: 'Orden' },
            { key: 'active', label: 'Estado' },
            { key: 'actions', label: 'Acciones' },
          ]"
        >
          <template #table-busy>
            <LoadingState message="Cargando módulos..." compact />
          </template>

          <template #cell(name)="{ item }">
            <div class="module-name-cell" :style="{ paddingLeft: `${item._depth * 22}px` }">
              <span v-if="item._depth" class="module-branch" aria-hidden="true"></span>
              <span class="module-icon">
                <i :class="moduleIconClass(item)"></i>
              </span>
              <div class="module-name-cell__text">
                <div class="module-name-line">
                  <span class="fw-semibold">{{ item.name }}</span>
                  <button
                    type="button"
                    class="module-help-button"
                    v-b-tooltip.hover.top
                    :title="moduleDescription(item)"
                    :aria-label="`Explicación del módulo ${item.name}`"
                  >
                    <i class="bx bx-question-mark"></i>
                  </button>
                </div>
                <div class="text-muted small">
                  {{ item.slug }} · {{ moduleTypeLabel(item) }}
                  <span v-if="item._parentName"> de {{ item._parentName }}</span>
                </div>
              </div>
            </div>
          </template>

          <template #cell(description)="{ item }">
            <span class="modules-description">{{ moduleDescription(item) }}</span>
          </template>

          <template #cell(frontend_route)="{ item }">
            <span :class="item.frontend_route ? 'modules-route' : 'text-muted'">
              {{ routeLabel(item) }}
            </span>
          </template>

          <template #cell(sort_order)="{ item }">
            <span class="modules-order">{{ item.sort_order }}</span>
          </template>

          <template #cell(active)="{ item }">
            <BBadge :variant="item.active ? 'success' : 'secondary'">
              {{ item.active ? "Activo" : "Inactivo" }}
            </BBadge>
          </template>

          <template #cell(actions)="{ item }">
            <div class="modules-actions">
              <BButton size="sm" variant="outline-primary" @click="openEdit(item)">
                <i class="bx bx-edit-alt me-1"></i>Editar
              </BButton>
              <BButton size="sm" :variant="item.active ? 'outline-warning' : 'outline-success'" @click="toggle(item)">
                <i :class="item.active ? 'bx bx-pause-circle me-1' : 'bx bx-check-circle me-1'"></i>
                {{ item.active ? "Desactivar" : "Activar" }}
              </BButton>
            </div>
          </template>
        </BTable>
      </BCard>

      <BModal v-model="showModal" :title="isEditing ? 'Editar módulo' : 'Nuevo módulo'" size="lg" hide-footer>
        <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Nombre</label>
            <BFormInput v-model="form.name" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Slug</label>
            <BFormInput v-model="form.slug" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Ruta frontend</label>
            <BFormInput v-model="form.frontend_route" placeholder="/ruta" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Ícono (boxicons)</label>
            <BFormInput v-model="form.icon" placeholder="bx-home-circle" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Padre</label>
            <BFormSelect v-model="form.parent_id" :options="parentOptions" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Orden</label>
            <BFormInput v-model="form.sort_order" type="number" />
          </div>
          <div class="col-md-3 mb-3 d-flex align-items-end">
            <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2">
          <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-save me-1'"></i>
            {{ saving ? "Guardando..." : "Guardar" }}
          </BButton>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.modules-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.modules-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.modules-eyebrow {
  color: #74788d;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.modules-title {
  color: #2a3042;
  font-weight: 700;
  margin: 0.15rem 0;
}

.modules-subtitle {
  color: #74788d;
  margin: 0;
}

.modules-metric {
  align-items: center;
  background: #fff;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  display: flex;
  gap: 0.85rem;
  min-height: 92px;
  padding: 1rem;
}

.modules-metric__icon {
  align-items: center;
  border-radius: 8px;
  display: inline-flex;
  flex: 0 0 42px;
  height: 42px;
  justify-content: center;
  width: 42px;
}

.modules-metric__icon i {
  font-size: 1.35rem;
}

.modules-metric__icon--primary {
  background: #eef1ff;
  color: #556ee6;
}

.modules-metric__icon--info {
  background: #e8f7fb;
  color: #50a5f1;
}

.modules-metric__icon--warning {
  background: #fff6e5;
  color: #f1b44c;
}

.modules-metric__icon--success {
  background: #e8f7ef;
  color: #34c38f;
}

.modules-metric__label,
.modules-metric__hint {
  color: #74788d;
  font-size: 0.78rem;
}

.modules-metric__value {
  color: #2a3042;
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
}

.modules-card {
  border: 1px solid #eff2f7;
  border-radius: 8px;
}

.modules-card__header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.modules-section-title {
  align-items: center;
  color: #2a3042;
  display: flex;
  font-weight: 700;
  gap: 0.4rem;
}

.modules-count {
  color: #74788d;
  font-size: 0.82rem;
}

.modules-filter {
  background: #f8f9fa;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  padding: 1rem;
}

.modules-input-icon {
  position: relative;
}

.modules-input-icon i {
  color: #74788d;
  left: 0.85rem;
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
}

.modules-input-icon :deep(input) {
  padding-left: 2.3rem;
}

.module-name-cell {
  align-items: center;
  display: flex;
  gap: 0.6rem;
  min-width: 280px;
}

.module-branch {
  border-bottom: 1px solid #ced4da;
  border-left: 1px solid #ced4da;
  display: inline-block;
  flex: 0 0 12px;
  height: 18px;
  margin-left: -0.25rem;
}

.module-icon {
  align-items: center;
  background: #f3f6f9;
  border-radius: 8px;
  color: #495057;
  display: inline-flex;
  flex: 0 0 34px;
  height: 34px;
  justify-content: center;
  width: 34px;
}

.module-icon i {
  font-size: 1.1rem;
}

.module-name-cell__text {
  min-width: 0;
}

.module-name-line {
  align-items: center;
  display: flex;
  gap: 0.4rem;
}

.module-help-button {
  align-items: center;
  background: #f8f9fa;
  border: 1px solid #ced4da;
  border-radius: 50%;
  color: #495057;
  display: inline-flex;
  flex: 0 0 24px;
  height: 24px;
  justify-content: center;
  padding: 0;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
  width: 24px;
}

.module-help-button:hover,
.module-help-button:focus {
  background: #eef1ff;
  border-color: #556ee6;
  color: #556ee6;
}

.module-help-button i {
  font-size: 0.95rem;
}

.modules-description {
  color: #495057;
  display: block;
  max-width: 460px;
}

.modules-route {
  color: #556ee6;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  font-size: 0.82rem;
}

.modules-order {
  background: #f3f6f9;
  border-radius: 999px;
  color: #495057;
  display: inline-flex;
  font-size: 0.78rem;
  font-weight: 700;
  justify-content: center;
  min-width: 34px;
  padding: 0.2rem 0.5rem;
}

.modules-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  min-width: 180px;
}

.modules-table :deep(td) {
  vertical-align: middle;
}

@media (max-width: 767.98px) {
  .modules-header {
    align-items: stretch;
    flex-direction: column;
  }

  .modules-header .btn {
    width: 100%;
  }
}
</style>
