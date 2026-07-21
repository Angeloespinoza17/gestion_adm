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
          help: "En esta pantalla se visualizan los indicadores en tiempo real del centro de apuntes y del pañol de librería, incluyendo tareas, urgencias, stock crítico, costos estimados y comportamiento por área.",
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
          help: "En esta pantalla se administran las máquinas del centro de apuntes, su ubicación, responsable, estado, costos estimados y volumen de uso.",
        },
        insumos: {
          title: "Pañol de Librería e Insumos",
          subtitle: "Control de stock, vencimientos, costos y disponibilidad operativa.",
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
          title: "Reportes y Costeo",
          subtitle: "Análisis exportable por período, actor, asignatura, máquina, stock y costos.",
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

      <BCard class="module-search-card">
        <div class="row g-3 align-items-center">
          <div class="col-xl-9">
            <label class="search-label"><i class="bx bx-search-alt-2"></i> Búsqueda global</label>
            <CentroApuntesGlobalSearch />
          </div>
          <div class="col-xl-3">
            <div class="search-hint">
              <i class="bx bx-bulb"></i>
              <span>Busca solicitudes, insumos, entregas o áreas desde esta pantalla.</span>
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
.centro-apuntes-shell { display: flex; flex-direction: column; gap: 1rem; }
.module-heading { align-items: flex-start; display: flex; gap: 1rem; justify-content: space-between; }
.heading-main { align-items: center; display: flex; gap: 0.9rem; min-width: 0; }
.heading-icon { align-items: center; background: linear-gradient(135deg, var(--bs-primary), #50a5f1); border-radius: 0.8rem; box-shadow: 0 0.6rem 1.4rem rgba(var(--bs-primary-rgb), .2); color: #fff; display: inline-flex; flex: 0 0 auto; font-size: 1.5rem; height: 3.25rem; justify-content: center; width: 3.25rem; }
.heading-kicker { color: var(--bs-primary); font-size: .7rem; font-weight: 700; letter-spacing: .08em; margin-bottom: .2rem; text-transform: uppercase; }
.heading-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.module-search-card { border: 1px solid rgba(217, 226, 246, .95); border-radius: .7rem; box-shadow: 0 .65rem 2rem rgba(90, 110, 150, .08); }
.search-label { align-items: center; color: var(--bs-body-color); display: flex; font-size: .78rem; font-weight: 700; gap: .35rem; margin-bottom: .45rem; text-transform: uppercase; }
.search-hint { align-items: center; background: rgba(var(--bs-primary-rgb), .06); border-radius: .55rem; color: var(--bs-secondary-color); display: flex; font-size: .78rem; gap: .55rem; min-height: 3rem; padding: .65rem .75rem; }
.search-hint i { color: var(--bs-primary); font-size: 1.1rem; }
.centro-apuntes-shell :deep(.card) { border: 1px solid rgba(217, 226, 246, .9) !important; border-radius: .7rem; box-shadow: 0 .7rem 2rem rgba(90, 110, 150, .075) !important; }
.centro-apuntes-shell :deep(.card-header) { background: transparent; border-bottom: 1px solid var(--bs-border-color); padding: 1rem 1.15rem; }
.centro-apuntes-shell :deep(.card-body) { padding: 1.15rem; }
.centro-apuntes-shell :deep(.form-label) { color: var(--bs-body-color); font-size: .76rem; font-weight: 700; margin-bottom: .4rem; }
.centro-apuntes-shell :deep(.form-control), .centro-apuntes-shell :deep(.form-select) { border-color: var(--bs-border-color); border-radius: .5rem; min-height: 2.55rem; }
.centro-apuntes-shell :deep(.form-control:focus), .centro-apuntes-shell :deep(.form-select:focus) { border-color: rgba(var(--bs-primary-rgb), .65); box-shadow: 0 0 0 .18rem rgba(var(--bs-primary-rgb), .1); }
.centro-apuntes-shell :deep(.table) { margin-bottom: 0; }
.centro-apuntes-shell :deep(.table thead th) { background: rgba(var(--bs-primary-rgb), .035); border-bottom-width: 1px; color: var(--bs-secondary-color); font-size: .7rem; letter-spacing: .035em; padding: .8rem .7rem; text-transform: uppercase; white-space: nowrap; }
.centro-apuntes-shell :deep(.table tbody td) { border-color: rgba(148, 163, 184, .14); padding: .78rem .7rem; vertical-align: middle; }
.centro-apuntes-shell :deep(.table tbody tr:hover) { background: rgba(var(--bs-primary-rgb), .025); }
.centro-apuntes-shell :deep(.modal-content) { border: 0; border-radius: .8rem; box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, .18); }
.centro-apuntes-shell :deep(.modal-header) { border-bottom-color: var(--bs-border-color); padding: 1.15rem 1.25rem; }
.centro-apuntes-shell :deep(.modal-body) { padding: 1.25rem; }
.centro-apuntes-shell :deep(.pagination) { margin-bottom: 0; }
@media (max-width: 767.98px) { .module-heading { align-items: stretch; flex-direction: column; } .heading-main { align-items: flex-start; } .heading-actions, .heading-actions :deep(.btn) { width: 100%; } .centro-apuntes-shell :deep(.card-body) { padding: .9rem; } }
</style>
