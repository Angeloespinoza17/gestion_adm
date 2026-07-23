<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import CentroApuntesHelpButton from "../../components/centro-apuntes/help-button.vue";
import CentroApuntesGlobalSearch from "../../components/centro-apuntes/global-search.vue";
import DashboardTab from "../../components/centro-apuntes/tabs/dashboard-tab.vue";
import SolicitudesTab from "../../components/centro-apuntes/tabs/solicitudes-tab.vue";
import AsignaturasTab from "../../components/centro-apuntes/tabs/asignaturas-tab.vue";
import MaquinasTab from "../../components/centro-apuntes/tabs/maquinas-tab.vue";
import InsumosTab from "../../components/centro-apuntes/tabs/insumos-tab.vue";
import MovimientosTab from "../../components/centro-apuntes/tabs/movimientos-tab.vue";
import EntregasTab from "../../components/centro-apuntes/tabs/entregas-tab.vue";
import ReportesTab from "../../components/centro-apuntes/tabs/reportes-tab.vue";
import { formatCentroApuntesError } from "../../components/centro-apuntes/module-utils";

const routeMap = {
  "/centro-apuntes": "dashboard",
  "/centro-apuntes/solicitudes": "solicitudes",
  "/centro-apuntes/asignaturas": "asignaturas",
  "/centro-apuntes/maquinas": "maquinas",
  "/centro-apuntes/insumos": "insumos",
  "/centro-apuntes/movimientos": "movimientos",
  "/centro-apuntes/entregas": "entregas",
  "/centro-apuntes/reportes": "reportes",
};

export default {
  components: {
    Layout,
    LoadingState,
    CentroApuntesHelpButton,
    CentroApuntesGlobalSearch,
    DashboardTab,
    SolicitudesTab,
    AsignaturasTab,
    MaquinasTab,
    InsumosTab,
    MovimientosTab,
    EntregasTab,
    ReportesTab,
  },
  data() {
    return {
      catalogsLoading: false,
      catalogsError: null,
      catalogs: {
        capabilities: {},
        task_types: [],
        paper_sizes: [],
        request_priorities: [],
        request_statuses: [],
        subject_statuses: [],
        subject_areas: [],
        subject_levels: [],
        machine_types: [],
        machine_statuses: [],
        supply_categories: [],
        supply_units: [],
        supply_statuses: [],
        movement_types: [],
        delivery_statuses: [],
        report_periods: [],
        users: [],
        subjects: [],
        machines: [],
        supplies: [],
        departments: [],
        suppliers: [],
      },
      sectionMeta: {
        dashboard: {
          title: "Dashboard Centro de Apuntes",
          subtitle: "Indicadores operativos del flujo de impresión, consumos, stock y entregas.",
          help: "En esta pantalla se visualizan los indicadores en tiempo real del centro de apuntes y del pañol de librería, incluyendo tareas, urgencias, stock crítico, consumos y comportamiento por área.",
        },
        solicitudes: {
          title: "Solicitudes de Impresión y Tareas",
          subtitle: "Registro operativo de guías, evaluaciones, copias, estados y entregas.",
          help: "En esta pantalla se registran las solicitudes de impresión, indicando solicitante, asignatura, descripción, cantidad de hojas, copias, tamaño de papel, máquina asignada y fecha de entrega.",
        },
        asignaturas: {
          title: "CRUD de Asignaturas",
          subtitle: "Catálogo pedagógico para clasificar solicitudes y reportes.",
          help: "En esta pantalla se administran las asignaturas del centro de apuntes, con filtros por área, nivel educativo y estado de uso.",
        },
        maquinas: {
          title: "CRUD de Máquinas",
          subtitle: "Control de impresoras, fotocopiadoras, Riso y equipos de terminación.",
          help: "En esta pantalla se administran las máquinas del centro de apuntes, su ubicación, responsable, estado y volumen de uso.",
        },
        insumos: {
          title: "Pañol de Librería e Insumos",
          subtitle: "Control de stock, vencimientos y disponibilidad operativa.",
          help: "En esta pantalla se gestionan los insumos del pañol, su stock actual, mínimos, máximos, proveedor, fecha de compra, vencimiento y estado de disponibilidad.",
        },
        movimientos: {
          title: "Movimientos de Stock",
          subtitle: "Historial de ingresos, salidas, ajustes, pérdidas y devoluciones.",
          help: "En esta pantalla se registran todos los movimientos del pañol, actualizando automáticamente el stock y dejando trazabilidad por responsable, área y motivo.",
        },
        entregas: {
          title: "Entregas de Materiales",
          subtitle: "Solicitudes, aprobación, retiro, descuento automático y comprobantes.",
          help: "En esta pantalla se solicitan, aprueban y registran entregas de materiales a funcionarios o áreas, con descuento automático de stock y comprobante de entrega.",
        },
        reportes: {
          title: "Reportes Operativos",
          subtitle: "Análisis exportable por período, actor, asignatura, máquina y stock.",
          help: "En esta pantalla se generan reportes diarios, semanales, mensuales, semestrales y anuales del centro de apuntes y del pañol, con exportación a Excel y PDF.",
        },
      },
    };
  },
  computed: {
    activeTab() {
      return routeMap[this.$route.path] || "dashboard";
    },
    activeComponent() {
      const map = {
        dashboard: "DashboardTab",
        solicitudes: "SolicitudesTab",
        asignaturas: "AsignaturasTab",
        maquinas: "MaquinasTab",
        insumos: "InsumosTab",
        movimientos: "MovimientosTab",
        entregas: "EntregasTab",
        reportes: "ReportesTab",
      };

      return map[this.activeTab];
    },
    activeMeta() {
      return this.sectionMeta[this.activeTab];
    },
    activeIcon() {
      return {
        dashboard: "bx-grid-alt",
        solicitudes: "bx-printer",
        asignaturas: "bx-book-open",
        maquinas: "bx-devices",
        insumos: "bx-box",
        movimientos: "bx-transfer-alt",
        entregas: "bx-package",
        reportes: "bx-bar-chart-alt-2",
      }[this.activeTab] || "bx-copy-alt";
    },
    moduleSections() {
      return [
        { key: "dashboard", label: "Resumen", route: "/centro-apuntes", icon: "bx-grid-alt" },
        { key: "solicitudes", label: "Solicitudes", route: "/centro-apuntes/solicitudes", icon: "bx-printer" },
        { key: "asignaturas", label: "Asignaturas", route: "/centro-apuntes/asignaturas", icon: "bx-book-open" },
        { key: "maquinas", label: "Máquinas", route: "/centro-apuntes/maquinas", icon: "bx-devices" },
        { key: "insumos", label: "Insumos", route: "/centro-apuntes/insumos", icon: "bx-box" },
        { key: "movimientos", label: "Movimientos", route: "/centro-apuntes/movimientos", icon: "bx-transfer-alt" },
        { key: "entregas", label: "Entregas", route: "/centro-apuntes/entregas", icon: "bx-package" },
        { key: "reportes", label: "Reportes", route: "/centro-apuntes/reportes", icon: "bx-bar-chart-alt-2" },
      ];
    },
  },
  mounted() {
    this.loadCatalogs();
  },
  methods: {
    async loadCatalogs() {
      this.catalogsLoading = true;
      this.catalogsError = null;
      try {
        const response = await axios.get("/api/centro-apuntes/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        this.catalogsError = formatCentroApuntesError(error, "No se pudieron cargar los catálogos del módulo Centro de Apuntes.");
      } finally {
        this.catalogsLoading = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <section class="centro-apuntes-shell">
      <header class="module-heading">
        <div class="heading-main">
          <span class="heading-icon"><i class="bx" :class="activeIcon"></i></span>
          <div>
            <div class="heading-kicker">Centro de Apuntes · Pañol</div>
            <h4 class="mb-1">{{ activeMeta.title }}</h4>
            <div class="text-muted">{{ activeMeta.subtitle }}</div>
          </div>
        </div>
        <div class="heading-actions">
          <CentroApuntesHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
        </div>
      </header>

      <nav class="module-nav" aria-label="Secciones del Centro de Apuntes">
        <router-link
          v-for="section in moduleSections"
          :key="section.key"
          :to="section.route"
          class="module-nav__item"
          :class="{ 'module-nav__item--active': activeTab === section.key }"
        >
          <i class="bx" :class="section.icon" aria-hidden="true"></i>
          <span>{{ section.label }}</span>
        </router-link>
      </nav>

      <BCard class="module-search-card">
        <div class="row g-3 align-items-center">
          <div class="col-xl-9">
            <label class="search-label"><i class="bx bx-search-alt-2"></i> Búsqueda global</label>
            <CentroApuntesGlobalSearch />
          </div>
          <div class="col-xl-3">
            <div class="search-hint">
              <i class="bx bx-bulb"></i>
              <span>Encuentra solicitudes, insumos, entregas o áreas sin cambiar de sección.</span>
            </div>
          </div>
        </div>
      </BCard>

      <BAlert v-if="catalogsError" show variant="danger">{{ catalogsError }}</BAlert>
      <BCard v-if="catalogsLoading" class="border-0 shadow-sm">
        <LoadingState message="Cargando catálogos del módulo Centro de Apuntes..." compact />
      </BCard>

      <component
        :is="activeComponent"
        v-else
        :catalogs="catalogs"
        @refresh-catalogs="loadCatalogs"
      />
    </section>
  </Layout>
</template>

<style scoped>
.centro-apuntes-shell { display: flex; flex-direction: column; gap: 1rem; padding-bottom: 1.5rem; }
.module-heading { align-items: center; background: linear-gradient(125deg, rgba(var(--bs-primary-rgb), .09), rgba(var(--bs-info-rgb), .025) 58%, transparent); border: 1px solid rgba(var(--bs-primary-rgb), .11); border-radius: 1rem; display: flex; gap: 1rem; justify-content: space-between; overflow: hidden; padding: 1.15rem 1.2rem; position: relative; }
.module-heading::after { background: radial-gradient(circle, rgba(var(--bs-primary-rgb), .11), transparent 68%); content: ""; height: 11rem; pointer-events: none; position: absolute; right: -3.5rem; top: -5rem; width: 11rem; }
.heading-main { align-items: center; display: flex; gap: 0.9rem; min-width: 0; }
.heading-icon { align-items: center; background: linear-gradient(135deg, var(--bs-primary), #50a5f1); border-radius: 0.85rem; box-shadow: 0 0.65rem 1.5rem rgba(var(--bs-primary-rgb), .22); color: #fff; display: inline-flex; flex: 0 0 auto; font-size: 1.5rem; height: 3.4rem; justify-content: center; width: 3.4rem; }
.heading-kicker { color: var(--bs-primary); font-size: .7rem; font-weight: 700; letter-spacing: .08em; margin-bottom: .2rem; text-transform: uppercase; }
.heading-actions { display: flex; flex-wrap: wrap; gap: .5rem; position: relative; z-index: 1; }
.module-nav { background: var(--bs-body-bg); border: 1px solid rgba(217, 226, 246, .95); border-radius: .85rem; box-shadow: 0 .45rem 1.4rem rgba(62, 82, 120, .055); display: flex; gap: .25rem; overflow-x: auto; padding: .4rem; scrollbar-width: thin; }
.module-nav__item { align-items: center; border-radius: .6rem; color: var(--bs-secondary-color); display: inline-flex; flex: 0 0 auto; font-size: .76rem; font-weight: 650; gap: .38rem; min-height: 2.35rem; padding: .5rem .7rem; transition: background-color .15s ease, color .15s ease, transform .15s ease; }
.module-nav__item:hover { background: rgba(var(--bs-primary-rgb), .055); color: var(--bs-primary); transform: translateY(-1px); }
.module-nav__item--active { background: linear-gradient(135deg, var(--bs-primary), #4d8df7); box-shadow: 0 .35rem .9rem rgba(var(--bs-primary-rgb), .18); color: #fff; }
.module-nav__item--active:hover { color: #fff; }
.module-nav__item i { font-size: 1rem; }
.module-search-card { border: 1px solid rgba(217, 226, 246, .95); border-radius: .85rem; box-shadow: 0 .55rem 1.7rem rgba(90, 110, 150, .065); }
.search-label { align-items: center; color: var(--bs-body-color); display: flex; font-size: .78rem; font-weight: 700; gap: .35rem; margin-bottom: .45rem; text-transform: uppercase; }
.search-hint { align-items: center; background: rgba(var(--bs-primary-rgb), .06); border-radius: .55rem; color: var(--bs-secondary-color); display: flex; font-size: .78rem; gap: .55rem; min-height: 3rem; padding: .65rem .75rem; }
.search-hint i { color: var(--bs-primary); font-size: 1.1rem; }
.centro-apuntes-shell :deep(.card) { border: 1px solid rgba(217, 226, 246, .9) !important; border-radius: .85rem; box-shadow: 0 .7rem 2rem rgba(90, 110, 150, .065) !important; }
.centro-apuntes-shell :deep(.card-header) { background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), .025), transparent); border-bottom: 1px solid var(--bs-border-color); padding: 1rem 1.15rem; }
.centro-apuntes-shell :deep(.card-body) { padding: 1.15rem; }
.centro-apuntes-shell :deep(.form-label) { color: var(--bs-body-color); font-size: .76rem; font-weight: 700; margin-bottom: .4rem; }
.centro-apuntes-shell :deep(.form-control), .centro-apuntes-shell :deep(.form-select) { background-color: var(--bs-body-bg); border-color: var(--bs-border-color); border-radius: .55rem; min-height: 2.55rem; }
.centro-apuntes-shell :deep(.form-control:focus), .centro-apuntes-shell :deep(.form-select:focus) { border-color: rgba(var(--bs-primary-rgb), .65); box-shadow: 0 0 0 .18rem rgba(var(--bs-primary-rgb), .1); }
.centro-apuntes-shell :deep(.centro-apuntes-tab) { display: flex; flex-direction: column; gap: 1rem !important; }
.centro-apuntes-shell :deep(.filter-card) { background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), .028), rgba(var(--bs-info-rgb), .012)); }
.centro-apuntes-shell :deep(.filter-card .card-body) { padding: 1rem 1.1rem; }
.centro-apuntes-shell :deep(.btn) { border-radius: .5rem; font-weight: 600; }
.centro-apuntes-shell :deep(.btn-sm) { border-radius: .45rem; }
.centro-apuntes-shell :deep(.table) { margin-bottom: 0; }
.centro-apuntes-shell :deep(.table thead th) { background: rgba(var(--bs-primary-rgb), .04); border-bottom-width: 1px; color: var(--bs-secondary-color); font-size: .68rem; letter-spacing: .045em; padding: .82rem .75rem; text-transform: uppercase; white-space: nowrap; }
.centro-apuntes-shell :deep(.table tbody td) { border-color: rgba(148, 163, 184, .14); padding: .8rem .75rem; vertical-align: middle; }
.centro-apuntes-shell :deep(.table tbody tr) { transition: background-color .15s ease; }
.centro-apuntes-shell :deep(.table tbody tr:hover) { background: rgba(var(--bs-primary-rgb), .032); }
.centro-apuntes-shell :deep(.modal-content) { border: 0; border-radius: .9rem; box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, .18); overflow: hidden; }
.centro-apuntes-shell :deep(.modal-header) { border-bottom-color: var(--bs-border-color); padding: 1.15rem 1.25rem; }
.centro-apuntes-shell :deep(.modal-body) { padding: 1.25rem; }
.centro-apuntes-shell :deep(.pagination) { margin-bottom: 0; }
.centro-apuntes-shell :deep(.page-item .page-link) { border-radius: .42rem; margin-inline: .12rem; }
@media (max-width: 767.98px) { .module-heading { align-items: stretch; flex-direction: column; padding: 1rem; } .heading-main { align-items: flex-start; } .heading-actions, .heading-actions :deep(.btn) { width: 100%; } .centro-apuntes-shell :deep(.card-body) { padding: .9rem; } .search-hint { min-height: auto; } }
@media (max-width: 575.98px) { .heading-icon { font-size: 1.25rem; height: 3rem; width: 3rem; } .module-nav { margin-inline: -.15rem; } .module-nav__item { padding-inline: .62rem; } }
</style>

<style>
.centro-apuntes-modal .modal-dialog { margin-block: 1.25rem; }
.centro-apuntes-modal .modal-content {
  background: var(--bs-body-bg);
  border: 0;
  border-radius: 1rem;
  box-shadow: 0 1.5rem 4.5rem rgba(15, 23, 42, .22);
  overflow: hidden;
}
.centro-apuntes-modal .modal-header {
  align-items: center;
  background: linear-gradient(125deg, rgba(var(--bs-primary-rgb), .11), rgba(var(--bs-info-rgb), .045));
  border-bottom: 1px solid rgba(var(--bs-primary-rgb), .11);
  min-height: 4.25rem;
  padding: 1rem 1.25rem;
  position: relative;
}
.centro-apuntes-modal .modal-header::after {
  background: radial-gradient(circle, rgba(var(--bs-primary-rgb), .12), transparent 70%);
  content: "";
  height: 8rem;
  pointer-events: none;
  position: absolute;
  right: 1.5rem;
  top: -4.5rem;
  width: 8rem;
}
.centro-apuntes-modal .modal-title { color: var(--bs-heading-color); font-size: 1rem; font-weight: 750; letter-spacing: -.01em; position: relative; z-index: 1; }
.centro-apuntes-modal .btn-close { background-color: var(--bs-body-bg); border-radius: .55rem; box-shadow: 0 .25rem .75rem rgba(15, 23, 42, .08); opacity: .72; padding: .62rem; position: relative; z-index: 1; }
.centro-apuntes-modal .modal-body { background: rgba(var(--bs-secondary-rgb), .018); padding: 1.2rem 1.25rem 1.3rem; }
.centro-apuntes-modal .modal-form-grid { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .85rem; margin-inline: 0; padding: .15rem .15rem 1rem; }
.centro-apuntes-modal .modal-form-grid > [class*="col-"] { padding-top: .85rem; }
.centro-apuntes-modal .form-label { align-items: center; color: var(--bs-heading-color); display: flex; font-size: .72rem; font-weight: 750; gap: .35rem; margin-bottom: .42rem; }
.centro-apuntes-modal .field-optional { background: var(--bs-tertiary-bg); border-radius: 999px; color: var(--bs-secondary-color); font-size: .58rem; font-weight: 650; padding: .14rem .38rem; }
.centro-apuntes-modal .field-required { color: var(--bs-danger); font-size: .72rem; }
.centro-apuntes-modal .form-control,
.centro-apuntes-modal .form-select { background-color: var(--bs-body-bg); border-color: var(--bs-border-color); border-radius: .58rem; min-height: 2.65rem; }
.centro-apuntes-modal textarea.form-control { min-height: 5.6rem; resize: vertical; }
.centro-apuntes-modal .form-control:focus,
.centro-apuntes-modal .form-select:focus { border-color: rgba(var(--bs-primary-rgb), .62); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .1); }
.centro-apuntes-modal .modal-actions { align-items: center; border-top: 1px solid var(--bs-border-color); display: flex; gap: .55rem; justify-content: flex-end; margin-top: 1.2rem; padding-top: 1rem; }
.centro-apuntes-modal .modal-actions .btn { border-radius: .55rem; font-weight: 650; min-width: 6.5rem; }
.centro-apuntes-modal .detail-grid { margin-inline: 0; }
.centro-apuntes-modal .detail-grid > [class*="col-"] { padding-top: 0; }
.centro-apuntes-modal .detail-grid > [class*="col-"] > div:first-child { background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: .7rem; height: 100%; min-height: 4.45rem; padding: .72rem .8rem; }
.centro-apuntes-modal .detail-grid .text-muted.small { font-size: .64rem; font-weight: 750; letter-spacing: .045em; margin-bottom: .28rem; text-transform: uppercase; }
.centro-apuntes-modal .modal-section-title { align-items: center; color: var(--bs-heading-color); display: flex; font-size: .8rem; font-weight: 750; gap: .38rem; margin-bottom: .6rem; }
.centro-apuntes-modal .modal-section-title::before { background: var(--bs-primary); border-radius: 999px; content: ""; height: 1rem; width: .2rem; }
.centro-apuntes-modal .modal-line-item { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color) !important; border-radius: .78rem !important; box-shadow: 0 .35rem 1rem rgba(62, 82, 120, .045); margin-inline: 0; padding: .2rem .35rem 1rem !important; }
.centro-apuntes-modal .table-responsive { border: 1px solid var(--bs-border-color); border-radius: .75rem; overflow: hidden; }
.centro-apuntes-modal .table { margin-bottom: 0; }
.centro-apuntes-modal .table thead th { background: rgba(var(--bs-primary-rgb), .045); color: var(--bs-secondary-color); font-size: .65rem; letter-spacing: .04em; padding: .72rem; text-transform: uppercase; white-space: nowrap; }
.centro-apuntes-modal .table tbody td { border-color: rgba(148, 163, 184, .14); padding: .72rem; vertical-align: middle; }
.swal2-popup.centro-apuntes-alert { border: 1px solid rgba(var(--bs-primary-rgb), .12); border-radius: 1rem; box-shadow: 0 1.5rem 4.5rem rgba(15, 23, 42, .22); padding: 1.35rem; }
.centro-apuntes-alert .swal2-title { color: var(--bs-heading-color); font-size: 1.2rem; padding-top: .4rem; }
.centro-apuntes-alert .swal2-html-container { color: var(--bs-secondary-color); font-size: .86rem; line-height: 1.5; }
.centro-apuntes-alert .swal2-actions { gap: .55rem; }
.centro-apuntes-alert .swal2-actions button { border-radius: .55rem; font-weight: 650; margin: 0; min-width: 6.5rem; }
.centro-apuntes-alert .swal2-input,
.centro-apuntes-alert .swal2-textarea { border: 1px solid var(--bs-border-color); border-radius: .58rem; box-shadow: none; font-size: .85rem; margin-inline: 0; width: 100%; }
.centro-apuntes-alert .swal2-input:focus,
.centro-apuntes-alert .swal2-textarea:focus { border-color: rgba(var(--bs-primary-rgb), .62); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .1); }
@media (max-width: 575.98px) {
  .centro-apuntes-modal .modal-dialog { margin: .6rem; }
  .centro-apuntes-modal .modal-header { min-height: 3.8rem; padding: .9rem 1rem; }
  .centro-apuntes-modal .modal-body { padding: .9rem; }
  .centro-apuntes-modal .modal-actions { align-items: stretch; flex-direction: column-reverse; }
  .centro-apuntes-modal .modal-actions .btn { width: 100%; }
}
</style>
