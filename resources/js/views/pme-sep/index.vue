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
import "../../components/pme-sep/pme-sep.css";

const routeMap = {
  "/pme-sep": {
    key: "dashboard",
    component: "DashboardTab",
    title: "Dashboard PME / SEP",
    subtitle: "Indicadores, alertas, avance y ejecución presupuestaria del plan activo.",
    help: "En esta pantalla se visualizan en tiempo real el PME activo, el ciclo vigente, acciones atrasadas, indicadores críticos, evidencias pendientes, presupuesto SEP, estudiantes SEP y cumplimiento global del plan.",
    section: "dashboard",
    eyebrow: "Resumen ejecutivo",
    icon: "bx-grid-alt",
  },
  "/pme-sep/configuracion": {
    key: "configuracion",
    component: "PlanningTab",
    title: "Configuración PME",
    subtitle: "Creación anual del plan, vigencia, ciclos, cierre, archivado y duplicación de estructura.",
    help: "En esta sección se administra la configuración general del PME: año escolar, nombre, fechas, estado, responsable general, activación, cierre, archivado, duplicación y trazabilidad de ciclos.",
    section: "configuracion",
    eyebrow: "Planificación",
    icon: "bx-cog",
  },
  "/pme-sep/ingresos": {
    key: "ingresos",
    component: "SepTab",
    title: "Ingresos SEP",
    subtitle: "Registro mensual de subvención SEP, respaldo, acumulado anual y control de saldo.",
    help: "En esta sección se registran los ingresos de la subvención SEP, con tipo de ingreso, monto, fecha, respaldo, estado y comparación entre estimado y real.",
    section: "ingresos",
    eyebrow: "Gestión SEP",
    icon: "bx-wallet",
  },
  "/pme-sep/estudiantes": {
    key: "estudiantes",
    component: "SepTab",
    title: "Estudiantes Prioritarias y Preferentes",
    subtitle: "Historial anual SEP, carga manual, carga masiva, filtros por curso y seguimiento por estudiante.",
    help: "En esta sección se administra la clasificación SEP de estudiantes por año escolar, incluyendo prioritarias, preferentes, pendientes de validación y exportación de nómina.",
    section: "estudiantes",
    eyebrow: "Gestión SEP",
    icon: "bx-group",
  },
  "/pme-sep/dimensiones": {
    key: "dimensiones",
    component: "PlanningTab",
    title: "Dimensiones PME",
    subtitle: "Catálogo de dimensiones estratégicas y orden institucional.",
    help: "En esta sección se registran las dimensiones PME y su orden institucional, para asociarlas a objetivos, estrategias, acciones y reportes.",
    section: "dimensiones",
    eyebrow: "Planificación",
    icon: "bx-layer",
  },
  "/pme-sep/objetivos": {
    key: "objetivos",
    component: "PlanningTab",
    title: "Objetivos Estratégicos",
    subtitle: "Definición de metas institucionales asociadas a dimensiones, estrategias e indicadores.",
    help: "En esta sección se registran los objetivos estratégicos del PME asociados a una dimensión, responsable, meta, indicador global, fechas y estado.",
    section: "objetivos",
    eyebrow: "Planificación",
    icon: "bx-target-lock",
  },
  "/pme-sep/estrategias": {
    key: "estrategias",
    component: "PlanningTab",
    title: "Estrategias",
    subtitle: "Diseño de líneas de trabajo para alcanzar los objetivos estratégicos del PME.",
    help: "En esta sección se registran las estrategias asociadas a objetivos PME, responsables, periodo de ejecución, estado y observaciones.",
    section: "estrategias",
    eyebrow: "Planificación",
    icon: "bx-git-branch",
  },
  "/pme-sep/indicadores": {
    key: "indicadores",
    component: "IndicatorsTab",
    title: "Indicadores PME",
    subtitle: "Metas, línea base, mediciones históricas y alertas por bajo cumplimiento.",
    help: "En esta sección se registran indicadores PME, sus metas esperadas, frecuencia de medición, responsables, evolución histórica y cumplimiento.",
    section: "indicadores",
    eyebrow: "Seguimiento",
    icon: "bx-line-chart",
  },
  "/pme-sep/acciones": {
    key: "acciones",
    component: "ExecutionTab",
    title: "Acciones PME",
    subtitle: "Núcleo operativo del plan: responsables, presupuesto, avance, actividades, hitos y evidencias.",
    help: "En esta sección se registran las acciones PME asociadas a objetivos estratégicos, estrategias, indicadores, responsables, presupuesto, actividades, hitos y evidencias.",
    section: "acciones",
    eyebrow: "Ejecución",
    icon: "bx-task",
  },
  "/pme-sep/evidencias": {
    key: "evidencias",
    component: "ExecutionTab",
    title: "Actividades y Evidencias",
    subtitle: "Seguimiento documental de acciones, actividades y respaldos de cumplimiento.",
    help: "En esta sección se registran actividades de acciones PME y evidencias vinculadas a acciones, actividades, hitos, mediciones y monitoreos.",
    section: "evidencias",
    eyebrow: "Ejecución",
    icon: "bx-file",
  },
  "/pme-sep/hitos": {
    key: "hitos",
    component: "ExecutionTab",
    title: "Ejecución por Hitos",
    subtitle: "Línea de tiempo de compromisos, responsables, avance y vencimientos.",
    help: "En esta sección se registran hitos por acción, sus fechas planificadas y reales, porcentaje de avance, estado y alertas por vencimiento.",
    section: "hitos",
    eyebrow: "Ejecución",
    icon: "bx-flag",
  },
  "/pme-sep/metas": {
    key: "metas",
    component: "PlanningTab",
    title: "Medición de Metas Estratégicas",
    subtitle: "Comparación entre línea base, resultado esperado y resultado actual.",
    help: "En esta sección se registran mediciones de metas estratégicas asociadas a objetivos, con análisis cualitativo, evidencia y porcentaje de cumplimiento.",
    section: "metas",
    eyebrow: "Seguimiento",
    icon: "bx-bullseye",
  },
  "/pme-sep/monitoreo": {
    key: "monitoreo",
    component: "MonitoringTab",
    title: "Monitoreo Reflexivo",
    subtitle: "Análisis cualitativo del avance, dificultades, decisiones y ajustes del PME.",
    help: "En esta sección se registran monitoreos reflexivos del PME con preguntas orientadoras, avances observados, dificultades, decisiones, ajustes y próximos pasos.",
    section: "monitoreo",
    eyebrow: "Seguimiento",
    icon: "bx-search-alt",
  },
  "/pme-sep/reportes": {
    key: "reportes",
    component: "ReportsTab",
    title: "Reportes PME / SEP",
    subtitle: "Consolidado exportable por dimensión, objetivo, acción, presupuesto, estudiantes SEP y monitoreo.",
    help: "En esta sección se generan reportes generales y específicos del módulo PME / SEP, con filtros por año, dimensión, responsable, estado, fuente de financiamiento y tipo de evidencia.",
    section: "reportes",
    eyebrow: "Resultados",
    icon: "bx-bar-chart-square",
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
      navGroups: [
        {
          label: "Resumen",
          items: [{ key: "dashboard", route: "/pme-sep", label: "Dashboard", icon: "bx-grid-alt" }],
        },
        {
          label: "Planificación",
          items: [
            { key: "configuracion", route: "/pme-sep/configuracion", label: "Plan PME", icon: "bx-cog" },
            { key: "dimensiones", route: "/pme-sep/dimensiones", label: "Dimensiones", icon: "bx-layer" },
            { key: "objetivos", route: "/pme-sep/objetivos", label: "Objetivos", icon: "bx-target-lock" },
            { key: "estrategias", route: "/pme-sep/estrategias", label: "Estrategias", icon: "bx-git-branch" },
          ],
        },
        {
          label: "Ejecución",
          items: [
            { key: "acciones", route: "/pme-sep/acciones", label: "Acciones", icon: "bx-task" },
            { key: "hitos", route: "/pme-sep/hitos", label: "Hitos", icon: "bx-flag" },
            { key: "evidencias", route: "/pme-sep/evidencias", label: "Evidencias", icon: "bx-file" },
          ],
        },
        {
          label: "Seguimiento",
          items: [
            { key: "indicadores", route: "/pme-sep/indicadores", label: "Indicadores", icon: "bx-line-chart" },
            { key: "metas", route: "/pme-sep/metas", label: "Metas", icon: "bx-bullseye" },
            { key: "monitoreo", route: "/pme-sep/monitoreo", label: "Monitoreo", icon: "bx-search-alt" },
          ],
        },
        {
          label: "SEP y resultados",
          items: [
            { key: "ingresos", route: "/pme-sep/ingresos", label: "Ingresos", icon: "bx-wallet" },
            { key: "estudiantes", route: "/pme-sep/estudiantes", label: "Estudiantes", icon: "bx-group" },
            { key: "reportes", route: "/pme-sep/reportes", label: "Reportes", icon: "bx-bar-chart-square" },
          ],
        },
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
    activePlan() {
      return this.catalogs.plans?.find((plan) => Number(plan.id) === Number(this.catalogs.active_plan_id)) || null;
    },
    activePlanLabel() {
      return this.activePlan?.name || "Sin plan activo";
    },
    activePlanYear() {
      return this.activePlan?.school_year || this.activePlan?.year || new Date().getFullYear();
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
    <main class="pme-page">
      <div class="pme-shell">
        <header class="pme-hero">
          <div class="pme-hero__copy">
            <div class="pme-eyebrow"><i class="bx bx-compass"></i>{{ activeConfig.eyebrow }}</div>
            <div class="d-flex align-items-center gap-3">
              <span class="pme-hero__icon" aria-hidden="true"><i class="bx" :class="activeConfig.icon"></i></span>
              <div>
                <h1>{{ activeConfig.title }}</h1>
                <p>{{ activeConfig.subtitle }}</p>
              </div>
            </div>
            <div class="pme-hero__meta">
              <span><i class="bx bx-check-circle"></i>{{ activePlanLabel }}</span>
              <span><i class="bx bx-calendar"></i>Año {{ activePlanYear }}</span>
              <span><i class="bx bx-layer"></i>{{ catalogs.dimensions?.length || 0 }} dimensiones</span>
            </div>
          </div>
          <div class="pme-hero__tools">
            <div class="pme-search-wrap"><PmeGlobalSearch /></div>
            <PmeHelpButton :title="`Ayuda: ${activeConfig.title}`" :text="activeConfig.help" />
          </div>
        </header>

        <nav class="pme-module-nav" aria-label="Secciones de PME y SEP">
          <div v-for="group in navGroups" :key="group.label" class="pme-nav-group">
            <span class="pme-nav-group__label">{{ group.label }}</span>
            <div class="pme-nav-group__items">
              <router-link
                v-for="tab in group.items"
                :key="tab.key"
                :to="tab.route"
                class="pme-nav-link"
                :class="{ active: isTabActive(tab) }"
                :aria-label="tab.label"
                :aria-current="isTabActive(tab) ? 'page' : undefined"
              >
                <i class="bx" :class="tab.icon"></i><span>{{ tab.label }}</span>
              </router-link>
            </div>
          </div>
        </nav>

        <BAlert v-if="catalogsError" show variant="danger" class="pme-inline-alert">
          <i class="bx bx-error-circle"></i><span>{{ catalogsError }}</span>
          <BButton size="sm" variant="outline-danger" @click="loadCatalogs">Reintentar</BButton>
        </BAlert>
        <BCard v-if="catalogsLoading" class="pme-card">
          <LoadingState message="Preparando el espacio de gestión PME / SEP..." compact />
        </BCard>

        <section v-else class="pme-content" :aria-label="activeConfig.title">
          <component
            :is="activeComponent"
            :catalogs="catalogs"
            :section="activeConfig.section"
            @refresh-catalogs="loadCatalogs"
          />
        </section>
      </div>
    </main>
  </Layout>
</template>
