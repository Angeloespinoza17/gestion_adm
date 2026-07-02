<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import LibraryHelpButton from "../../components/library/help-button.vue";
import LibraryGlobalSearch from "../../components/library/global-search.vue";
import DashboardTab from "../../components/library/tabs/dashboard-tab.vue";
import CatalogTab from "../../components/library/tabs/catalog-tab.vue";
import InventoryTab from "../../components/library/tabs/inventory-tab.vue";
import LoansTab from "../../components/library/tabs/loans-tab.vue";
import ReservationsTab from "../../components/library/tabs/reservations-tab.vue";
import PlanTab from "../../components/library/tabs/plan-tab.vue";
import SpacesTab from "../../components/library/tabs/spaces-tab.vue";
import ReportsTab from "../../components/library/tabs/reports-tab.vue";
import { formatLibraryError } from "../../components/library/module-utils";

const routeMap = {
  "/biblioteca": "dashboard",
  "/biblioteca/catalogo": "catalogo",
  "/biblioteca/inventario": "inventario",
  "/biblioteca/prestamos": "prestamos",
  "/biblioteca/reservas": "reservas",
  "/biblioteca/plan-lector": "plan",
  "/biblioteca/espacios": "espacios",
  "/biblioteca/reportes": "reportes",
};

export default {
  components: {
    Layout,
    LoadingState,
    LibraryHelpButton,
    LibraryGlobalSearch,
    DashboardTab,
    CatalogTab,
    InventoryTab,
    LoansTab,
    ReservationsTab,
    PlanTab,
    SpacesTab,
    ReportsTab,
  },
  data() {
    return {
      catalogsLoading: false,
      catalogsError: null,
      catalogs: {
        material_types: [],
        obra_statuses: [],
        ejemplar_origins: [],
        ejemplar_states: [],
        ejemplar_availability_statuses: [],
        loan_statuses: [],
        borrower_types: [],
        reservation_statuses: [],
        reservation_requester_types: [],
        plan_statuses: [],
        space_activity_types: [],
        space_statuses: [],
        academic_years: [],
        courses: [],
        works: [],
        exemplars: [],
        students: [],
        staff: [],
        users: [],
        spaces: [],
        categories: [],
        subcategories: [],
        genres: [],
        languages: [],
        locations: [],
      },
      tabs: [
        { key: "dashboard", route: "/biblioteca", label: "Dashboard" },
        { key: "catalogo", route: "/biblioteca/catalogo", label: "Catálogo" },
        { key: "inventario", route: "/biblioteca/inventario", label: "Ejemplares e inventario" },
        { key: "prestamos", route: "/biblioteca/prestamos", label: "Préstamos y devoluciones" },
        { key: "reservas", route: "/biblioteca/reservas", label: "Reservas de recursos" },
        { key: "plan", route: "/biblioteca/plan-lector", label: "Plan lector" },
        { key: "espacios", route: "/biblioteca/espacios", label: "Uso de espacios" },
        { key: "reportes", route: "/biblioteca/reportes", label: "Estadísticas y reportes" },
      ],
      sectionMeta: {
        dashboard: {
          title: "Dashboard Biblioteca Escolar",
          subtitle: "Indicadores operativos, alertas y comportamiento del uso CRA en tiempo real.",
          help: "En esta pantalla se visualizan indicadores en tiempo real de préstamos, devoluciones, mora, reservas, plan lector, disponibilidad del inventario y uso de espacios de biblioteca.",
        },
        catalogo: {
          title: "Catálogo Bibliográfico",
          subtitle: "Administración de obras, clasificación, ubicación y criterios pedagógicos de búsqueda.",
          help: "En esta pantalla se administra el catálogo bibliográfico de la biblioteca, incluyendo libros, autores, ejemplares, ubicación física, estado e historial de préstamos.",
        },
        inventario: {
          title: "Ejemplares e Inventario",
          subtitle: "Control unitario de ejemplares físicos, movimientos, daños, pérdidas e inventario anual.",
          help: "En esta pantalla se gestionan los ejemplares físicos asociados a cada obra, sus movimientos, ubicación, estado material y control de inventario físico anual.",
        },
        prestamos: {
          title: "Préstamos y Devoluciones",
          subtitle: "Flujo completo de entrega, mora, renovación, devolución, daño o pérdida.",
          help: "En esta pantalla se registran préstamos, renovaciones, devoluciones, cancelaciones y alertas por mora para estudiantes, funcionarios, docentes, apoderados o cursos completos.",
        },
        reservas: {
          title: "Reservas de Recursos",
          subtitle: "Gestión de solicitudes, aprobación, retiro y devolución de recursos bibliotecarios.",
          help: "En esta pantalla se administran reservas de libros, diccionarios, tablets, notebooks, proyectores, materiales didácticos y otros recursos de biblioteca con validación de disponibilidad.",
        },
        plan: {
          title: "Plan Lector",
          subtitle: "Planificación anual por curso, asignatura, docente responsable y disponibilidad de ejemplares.",
          help: "En esta pantalla se gestiona el plan lector por curso y año académico, asociando lecturas, actividades, disponibilidad de ejemplares y préstamo masivo por curso.",
        },
        espacios: {
          title: "Uso de Espacios",
          subtitle: "Calendario, reservas y trazabilidad de actividades realizadas en la biblioteca y sus salas.",
          help: "En esta pantalla se reservan y controlan los espacios de biblioteca, con calendario diario, semanal y mensual, responsables, asistentes, recursos solicitados y evidencias.",
        },
        reportes: {
          title: "Estadísticas y Reportes",
          subtitle: "Consolidado exportable para seguimiento, auditoría y toma de decisiones.",
          help: "En esta pantalla se generan estadísticas y reportes de préstamos, devoluciones, mora, reservas, inventario, plan lector y uso de espacios, con exportación a Excel y PDF.",
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
        catalogo: "CatalogTab",
        inventario: "InventoryTab",
        prestamos: "LoansTab",
        reservas: "ReservationsTab",
        plan: "PlanTab",
        espacios: "SpacesTab",
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
        const response = await axios.get("/api/biblioteca/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        this.catalogsError = formatLibraryError(error, "No se pudieron cargar los catálogos del módulo Biblioteca.");
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
          <LibraryHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <div class="row g-3 align-items-center">
          <div class="col-xl-8">
            <LibraryGlobalSearch />
          </div>
          <div class="col-xl-4">
            <div class="small text-muted">
              Buscador global disponible desde cualquier sección para abrir de inmediato la ficha del recurso, ejemplar, estudiante, funcionario o curso.
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
        <LoadingState message="Cargando catálogos del módulo Biblioteca..." compact />
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
