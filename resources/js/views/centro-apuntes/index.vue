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
      tabs: [
        { key: "dashboard", route: "/centro-apuntes", label: "Dashboard" },
        { key: "solicitudes", route: "/centro-apuntes/solicitudes", label: "Solicitudes y tareas" },
        { key: "asignaturas", route: "/centro-apuntes/asignaturas", label: "Asignaturas" },
        { key: "maquinas", route: "/centro-apuntes/maquinas", label: "Máquinas" },
        { key: "insumos", route: "/centro-apuntes/insumos", label: "Pañol e insumos" },
        { key: "movimientos", route: "/centro-apuntes/movimientos", label: "Movimientos" },
        { key: "entregas", route: "/centro-apuntes/entregas", label: "Entregas" },
        { key: "reportes", route: "/centro-apuntes/reportes", label: "Reportes" },
      ],
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
    isTabActive(tab) {
      return this.activeTab === tab.key;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-column gap-3">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h4 class="mb-1">{{ activeMeta.title }}</h4>
          <div class="text-muted">{{ activeMeta.subtitle }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <CentroApuntesHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <div class="row g-3 align-items-center">
          <div class="col-xl-8">
            <CentroApuntesGlobalSearch />
          </div>
          <div class="col-xl-4">
            <div class="small text-muted">
              Buscador global disponible desde cualquier sección para abrir solicitudes, insumos, entregas y áreas del módulo.
            </div>
          </div>
        </div>
      </BCard>

      <div class="d-flex flex-wrap gap-2">
        <router-link
          v-for="tab in tabs"
          :key="tab.key"
          :to="tab.route"
          class="btn"
          :class="isTabActive(tab) ? 'btn-primary' : 'btn-outline-secondary'"
        >
          {{ tab.label }}
        </router-link>
      </div>

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
    </div>
  </Layout>
</template>
