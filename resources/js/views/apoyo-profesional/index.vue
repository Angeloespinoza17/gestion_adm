<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import SupportHelpButton from "../../components/apoyo-profesional/help-button.vue";
import SupportGlobalSearch from "../../components/apoyo-profesional/global-search.vue";
import DashboardTab from "../../components/apoyo-profesional/tabs/dashboard-tab.vue";
import AttentionsTab from "../../components/apoyo-profesional/tabs/attentions-tab.vue";
import HistoryTab from "../../components/apoyo-profesional/tabs/history-tab.vue";
import DerivationsTab from "../../components/apoyo-profesional/tabs/derivations-tab.vue";
import FollowUpsTab from "../../components/apoyo-profesional/tabs/followups-tab.vue";
import PlansTab from "../../components/apoyo-profesional/tabs/plans-tab.vue";
import InterviewsTab from "../../components/apoyo-profesional/tabs/interviews-tab.vue";
import DocumentsTab from "../../components/apoyo-profesional/tabs/documents-tab.vue";
import ReportsTab from "../../components/apoyo-profesional/tabs/reports-tab.vue";
import { formatSupportError } from "../../components/apoyo-profesional/module-utils";

const routeMap = {
  "/apoyo-profesional": "dashboard",
  "/apoyo-profesional/atenciones": "atenciones",
  "/apoyo-profesional/historial": "historial",
  "/apoyo-profesional/derivaciones": "derivaciones",
  "/apoyo-profesional/seguimientos": "seguimientos",
  "/apoyo-profesional/planes": "planes",
  "/apoyo-profesional/entrevistas": "entrevistas",
  "/apoyo-profesional/documentos": "documentos",
  "/apoyo-profesional/reportes": "reportes",
};

export default {
  components: {
    Layout,
    LoadingState,
    SupportHelpButton,
    SupportGlobalSearch,
    DashboardTab,
    AttentionsTab,
    HistoryTab,
    DerivationsTab,
    FollowUpsTab,
    PlansTab,
    InterviewsTab,
    DocumentsTab,
    ReportsTab,
  },
  data() {
    return {
      catalogsLoading: false,
      catalogsError: null,
      catalogs: {
        academic_years: [],
        courses: [],
        professionals: [],
        attention_types: [],
        motives: [],
        area_options: [],
        modality_options: [],
        origin_options: [],
        priority_options: [],
        confidentiality_options: [],
        attention_status_options: [],
        derivation_status_options: [],
        derivation_urgency_options: [],
        follow_up_status_options: [],
        plan_status_options: [],
        interview_type_options: [],
        document_categories: [],
        report_period_options: [],
        capabilities: {},
      },
      tabs: [
        { key: "dashboard", route: "/apoyo-profesional", label: "Dashboard" },
        { key: "atenciones", route: "/apoyo-profesional/atenciones", label: "Atenciones" },
        { key: "historial", route: "/apoyo-profesional/historial", label: "Ficha estudiante" },
        { key: "derivaciones", route: "/apoyo-profesional/derivaciones", label: "Derivaciones" },
        { key: "seguimientos", route: "/apoyo-profesional/seguimientos", label: "Seguimientos" },
        { key: "planes", route: "/apoyo-profesional/planes", label: "Planes de apoyo" },
        { key: "entrevistas", route: "/apoyo-profesional/entrevistas", label: "Entrevistas" },
        { key: "documentos", route: "/apoyo-profesional/documentos", label: "Documentos" },
        { key: "reportes", route: "/apoyo-profesional/reportes", label: "Reportes" },
      ],
      sectionMeta: {
        dashboard: {
          title: "Dashboard Equipo de Apoyo",
          subtitle: "Indicadores operativos, alertas y distribución de casos del equipo de apoyo escolar.",
          help: "En esta pantalla se visualizan atenciones, seguimientos, derivaciones, casos activos, confidencialidad y distribución por profesional, curso y motivo.",
        },
        atenciones: {
          title: "Ficha de Atención Profesional",
          subtitle: "Registro formal de intervenciones realizadas por profesionales autorizados del establecimiento.",
          help: "En esta pantalla se registran las atenciones realizadas por profesionales del equipo de apoyo, como psicología, trabajo social, terapia ocupacional, PIE u orientación. Cada atención queda vinculada al estudiante, profesional, tipo de intervención y seguimiento.",
        },
        historial: {
          title: "Ficha del Estudiante",
          subtitle: "Historial consolidado con atenciones, derivaciones, seguimientos, planes y adjuntos del estudiante.",
          help: "Aquí se revisa la ficha de apoyo profesional por estudiante con filtros por año, profesional, área, estado y nivel de confidencialidad según permisos.",
        },
        derivaciones: {
          title: "Derivaciones Internas",
          subtitle: "Trazabilidad de envíos entre áreas del equipo de apoyo y otras áreas del colegio.",
          help: "En esta pantalla se registran, responden y cierran derivaciones internas entre psicología, convivencia, PIE, orientación, dirección u otras áreas autorizadas.",
        },
        seguimientos: {
          title: "Seguimientos",
          subtitle: "Programación y cierre de acciones posteriores asociadas a una atención profesional.",
          help: "Aquí se crean seguimientos pendientes, realizados, reprogramados o cerrados para asegurar continuidad de los casos atendidos.",
        },
        planes: {
          title: "Planes de Apoyo",
          subtitle: "Diseño, ejecución y monitoreo de planes de apoyo por estudiante.",
          help: "En esta pantalla se definen objetivos, acciones, responsables, indicadores y estado de los planes de apoyo asociados al estudiante.",
        },
        entrevistas: {
          title: "Entrevistas Profesionales",
          subtitle: "Registro de entrevistas con estudiantes, apoderados, docentes, familia o red externa.",
          help: "Aquí se registran entrevistas profesionales, participantes, acuerdos, compromisos y fecha de seguimiento con control de confidencialidad.",
        },
        documentos: {
          title: "Documentos y Adjuntos",
          subtitle: "Consulta y trazabilidad de archivos asociados a casos, entrevistas, planes y seguimientos.",
          help: "En esta pantalla se revisan los documentos adjuntos asociados al historial del estudiante, respetando permisos y confidencialidad.",
        },
        reportes: {
          title: "Reportes del Módulo",
          subtitle: "Consolidado exportable por profesional, curso, área, estado, motivo y confidencialidad.",
          help: "En esta pantalla se generan reportes diarios, semanales, mensuales, semestrales o anuales con opción de anonimizar datos sensibles.",
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
        atenciones: "AttentionsTab",
        historial: "HistoryTab",
        derivaciones: "DerivationsTab",
        seguimientos: "FollowUpsTab",
        planes: "PlansTab",
        entrevistas: "InterviewsTab",
        documentos: "DocumentsTab",
        reportes: "ReportsTab",
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
        const response = await axios.get("/api/apoyo-profesional/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        this.catalogsError = formatSupportError(error, "No se pudieron cargar los catálogos del módulo.");
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
          <SupportHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <div class="row g-3 align-items-center">
          <div class="col-xl-8">
            <SupportGlobalSearch />
          </div>
          <div class="col-xl-4">
            <div class="small text-muted">
              Buscador global disponible desde cualquier sección para abrir de inmediato la ficha del estudiante o una atención relacionada.
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
        <LoadingState message="Cargando catálogos del módulo Equipo de Apoyo..." compact />
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
