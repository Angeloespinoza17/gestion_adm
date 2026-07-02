<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PmeHelpButton from "../../components/pme-sep/help-button.vue";
import PmeGlobalSearch from "../../components/pme-sep/global-search.vue";
import DashboardTab from "../../components/pme-sep/tabs/dashboard-tab.vue";
import PlanningTab from "../../components/pme-sep/tabs/planning-tab.vue";
import SepTab from "../../components/pme-sep/tabs/sep-tab.vue";
import IndicatorsTab from "../../components/pme-sep/tabs/indicators-tab.vue";
import ExecutionTab from "../../components/pme-sep/tabs/execution-tab.vue";
import MonitoringTab from "../../components/pme-sep/tabs/monitoring-tab.vue";
import ReportsTab from "../../components/pme-sep/tabs/reports-tab.vue";
import { formatPmeError } from "../../components/pme-sep/module-utils";

const routeMap = {
  "/pme-sep": {
    key: "dashboard",
    component: "DashboardTab",
    title: "Dashboard PME / SEP",
    subtitle: "Indicadores, alertas, avance y ejecución presupuestaria del plan activo.",
    help: "En esta pantalla se visualizan en tiempo real el PME activo, el ciclo vigente, acciones atrasadas, indicadores críticos, evidencias pendientes, presupuesto SEP, estudiantes SEP y cumplimiento global del plan.",
    section: "dashboard",
  },
  "/pme-sep/configuracion": {
    key: "configuracion",
    component: "PlanningTab",
    title: "Configuración PME",
    subtitle: "Creación anual del plan, vigencia, ciclos, cierre, archivado y duplicación de estructura.",
    help: "En esta sección se administra la configuración general del PME: año escolar, nombre, fechas, estado, responsable general, activación, cierre, archivado, duplicación y trazabilidad de ciclos.",
    section: "configuracion",
  },
  "/pme-sep/ingresos": {
    key: "ingresos",
    component: "SepTab",
    title: "Ingresos SEP",
    subtitle: "Registro mensual de subvención SEP, respaldo, acumulado anual y control de saldo.",
    help: "En esta sección se registran los ingresos de la subvención SEP, con tipo de ingreso, monto, fecha, respaldo, estado y comparación entre estimado y real.",
    section: "ingresos",
  },
  "/pme-sep/estudiantes": {
    key: "estudiantes",
    component: "SepTab",
    title: "Estudiantes Prioritarias y Preferentes",
    subtitle: "Historial anual SEP, carga manual, carga masiva, filtros por curso y seguimiento por estudiante.",
    help: "En esta sección se administra la clasificación SEP de estudiantes por año escolar, incluyendo prioritarias, preferentes, pendientes de validación y exportación de nómina.",
    section: "estudiantes",
  },
  "/pme-sep/dimensiones": {
    key: "dimensiones",
    component: "PlanningTab",
    title: "Dimensiones PME",
    subtitle: "Catálogo de dimensiones estratégicas y orden institucional.",
    help: "En esta sección se registran las dimensiones PME y su orden institucional, para asociarlas a objetivos, estrategias, acciones y reportes.",
    section: "dimensiones",
  },
  "/pme-sep/objetivos": {
    key: "objetivos",
    component: "PlanningTab",
    title: "Objetivos Estratégicos",
    subtitle: "Definición de metas institucionales asociadas a dimensiones, estrategias e indicadores.",
    help: "En esta sección se registran los objetivos estratégicos del PME asociados a una dimensión, responsable, meta, indicador global, fechas y estado.",
    section: "objetivos",
  },
  "/pme-sep/estrategias": {
    key: "estrategias",
    component: "PlanningTab",
    title: "Estrategias",
    subtitle: "Diseño de líneas de trabajo para alcanzar los objetivos estratégicos del PME.",
    help: "En esta sección se registran las estrategias asociadas a objetivos PME, responsables, periodo de ejecución, estado y observaciones.",
    section: "estrategias",
  },
  "/pme-sep/indicadores": {
    key: "indicadores",
    component: "IndicatorsTab",
    title: "Indicadores PME",
    subtitle: "Metas, línea base, mediciones históricas y alertas por bajo cumplimiento.",
    help: "En esta sección se registran indicadores PME, sus metas esperadas, frecuencia de medición, responsables, evolución histórica y cumplimiento.",
    section: "indicadores",
  },
  "/pme-sep/acciones": {
    key: "acciones",
    component: "ExecutionTab",
    title: "Acciones PME",
    subtitle: "Núcleo operativo del plan: responsables, presupuesto, avance, actividades, hitos y evidencias.",
    help: "En esta sección se registran las acciones PME asociadas a objetivos estratégicos, estrategias, indicadores, responsables, presupuesto, actividades, hitos y evidencias.",
    section: "acciones",
  },
  "/pme-sep/evidencias": {
    key: "evidencias",
    component: "ExecutionTab",
    title: "Actividades y Evidencias",
    subtitle: "Seguimiento documental de acciones, actividades y respaldos de cumplimiento.",
    help: "En esta sección se registran actividades de acciones PME y evidencias vinculadas a acciones, actividades, hitos, mediciones y monitoreos.",
    section: "evidencias",
  },
  "/pme-sep/hitos": {
    key: "hitos",
    component: "ExecutionTab",
    title: "Ejecución por Hitos",
    subtitle: "Línea de tiempo de compromisos, responsables, avance y vencimientos.",
    help: "En esta sección se registran hitos por acción, sus fechas planificadas y reales, porcentaje de avance, estado y alertas por vencimiento.",
    section: "hitos",
  },
  "/pme-sep/metas": {
    key: "metas",
    component: "PlanningTab",
    title: "Medición de Metas Estratégicas",
    subtitle: "Comparación entre línea base, resultado esperado y resultado actual.",
    help: "En esta sección se registran mediciones de metas estratégicas asociadas a objetivos, con análisis cualitativo, evidencia y porcentaje de cumplimiento.",
    section: "metas",
  },
  "/pme-sep/monitoreo": {
    key: "monitoreo",
    component: "MonitoringTab",
    title: "Monitoreo Reflexivo",
    subtitle: "Análisis cualitativo del avance, dificultades, decisiones y ajustes del PME.",
    help: "En esta sección se registran monitoreos reflexivos del PME con preguntas orientadoras, avances observados, dificultades, decisiones, ajustes y próximos pasos.",
    section: "monitoreo",
  },
  "/pme-sep/reportes": {
    key: "reportes",
    component: "ReportsTab",
    title: "Reportes PME / SEP",
    subtitle: "Consolidado exportable por dimensión, objetivo, acción, presupuesto, estudiantes SEP y monitoreo.",
    help: "En esta sección se generan reportes generales y específicos del módulo PME / SEP, con filtros por año, dimensión, responsable, estado, fuente de financiamiento y tipo de evidencia.",
    section: "reportes",
  },
};

export default {
  components: {
    Layout,
    LoadingState,
    PmeHelpButton,
    PmeGlobalSearch,
    DashboardTab,
    PlanningTab,
    SepTab,
    IndicatorsTab,
    ExecutionTab,
    MonitoringTab,
    ReportsTab,
  },
  data() {
    return {
      catalogsLoading: false,
      catalogsError: null,
      catalogs: {
        plans: [],
        academic_years: [],
        dimensions: [],
        responsibles: [],
        courses: [],
        students: [],
        options: {},
        capabilities: {},
      },
      tabs: [
        { key: "dashboard", route: "/pme-sep", label: "Dashboard" },
        { key: "configuracion", route: "/pme-sep/configuracion", label: "Configuración" },
        { key: "ingresos", route: "/pme-sep/ingresos", label: "Ingresos SEP" },
        { key: "estudiantes", route: "/pme-sep/estudiantes", label: "Estudiantes SEP" },
        { key: "dimensiones", route: "/pme-sep/dimensiones", label: "Dimensiones" },
        { key: "objetivos", route: "/pme-sep/objetivos", label: "Objetivos" },
        { key: "estrategias", route: "/pme-sep/estrategias", label: "Estrategias" },
        { key: "indicadores", route: "/pme-sep/indicadores", label: "Indicadores" },
        { key: "acciones", route: "/pme-sep/acciones", label: "Acciones" },
        { key: "evidencias", route: "/pme-sep/evidencias", label: "Evidencias" },
        { key: "hitos", route: "/pme-sep/hitos", label: "Hitos" },
        { key: "metas", route: "/pme-sep/metas", label: "Metas" },
        { key: "monitoreo", route: "/pme-sep/monitoreo", label: "Monitoreo" },
        { key: "reportes", route: "/pme-sep/reportes", label: "Reportes" },
      ],
    };
  },
  computed: {
    activeConfig() {
      return routeMap[this.$route.path] || routeMap["/pme-sep"];
    },
    activeComponent() {
      return this.activeConfig.component;
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
        const response = await axios.get("/api/pme-sep/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        this.catalogsError = formatPmeError(error, "No se pudieron cargar los catálogos del módulo PME / SEP.");
      } finally {
        this.catalogsLoading = false;
      }
    },
    isTabActive(tab) {
      return this.activeConfig.key === tab.key;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-column gap-3">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h4 class="mb-1">{{ activeConfig.title }}</h4>
          <div class="text-muted">{{ activeConfig.subtitle }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <PmeHelpButton :title="`Ayuda: ${activeConfig.title}`" :text="activeConfig.help" />
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <div class="row g-3 align-items-center">
          <div class="col-xl-8">
            <PmeGlobalSearch />
          </div>
          <div class="col-xl-4">
            <div class="small text-muted">
              Buscador global disponible desde cualquier sección del módulo para localizar rápidamente planes, objetivos, estrategias, indicadores, acciones, estudiantes SEP, cursos y evidencias.
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
        <LoadingState message="Cargando catálogos del módulo PME / SEP..." compact />
      </BCard>

      <component
        :is="activeComponent"
        v-else
        :catalogs="catalogs"
        :section="activeConfig.section"
        @refresh-catalogs="loadCatalogs"
      />
    </div>
  </Layout>
</template>
