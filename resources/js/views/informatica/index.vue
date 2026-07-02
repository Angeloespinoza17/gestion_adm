<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InformaticaHelpButton from "../../components/informatica/help-button.vue";
import DashboardTab from "../../components/informatica/tabs/dashboard-tab.vue";
import EquipmentTab from "../../components/informatica/tabs/equipment-tab.vue";
import LoansTab from "../../components/informatica/tabs/loans-tab.vue";
import MaintenanceTab from "../../components/informatica/tabs/maintenance-tab.vue";
import ReportsTab from "../../components/informatica/tabs/reports-tab.vue";
import { formatInformaticaError } from "../../components/informatica/module-utils";

const routeMap = {
  "/informatica": "dashboard",
  "/informatica/equipos": "equipos",
  "/informatica/prestamos": "prestamos",
  "/informatica/mantenciones": "mantenciones",
  "/informatica/reportes": "reportes",
};

export default {
  components: {
    Layout,
    LoadingState,
    InformaticaHelpButton,
    DashboardTab,
    EquipmentTab,
    LoansTab,
    MaintenanceTab,
    ReportsTab,
  },
  data() {
    return {
      catalogsLoading: false,
      catalogsError: null,
      catalogs: {
        equipment_types: [],
        equipment_statuses: [],
        loan_statuses: [],
        requester_types: [],
        return_conditions: [],
        maintenance_types: [],
        maintenance_statuses: [],
        attachment_categories: [],
        report_periods: [],
        users: [],
        staff: [],
        students: [],
        equipment: [],
        brands: [],
        locations: [],
        capabilities: {},
      },
      tabs: [
        { key: "dashboard", route: "/informatica", label: "Dashboard" },
        { key: "equipos", route: "/informatica/equipos", label: "Equipos" },
        { key: "prestamos", route: "/informatica/prestamos", label: "Préstamos" },
        { key: "mantenciones", route: "/informatica/mantenciones", label: "Mantenciones" },
        { key: "reportes", route: "/informatica/reportes", label: "Reportes" },
      ],
      sectionMeta: {
        dashboard: {
          title: "Dashboard de Informática",
          subtitle: "Visión operativa del parque tecnológico, préstamos, mantenciones y alertas.",
          help: "Esta vista resume equipos registrados, disponibilidad, préstamos activos y atrasados, mantenciones abiertas, equipos dañados y movimientos recientes del módulo.",
        },
        equipos: {
          title: "Registro de Equipos Informáticos",
          subtitle: "CRUD completo con filtros, estados, responsables, trazabilidad y baja lógica.",
          help: "Aquí se registran equipos tecnológicos, su ubicación, responsable habitual, fotografía, estado, adjuntos e historial de cambios.",
        },
        prestamos: {
          title: "Préstamo de Equipos",
          subtitle: "Registro de entrega, devolución, atraso, cancelación e historial por solicitante o equipo.",
          help: "En esta sección se controlan préstamos activos, atrasados y devueltos. El sistema valida disponibilidad, impide dobles préstamos y actualiza el estado del equipo al devolver.",
        },
        mantenciones: {
          title: "Informes de Mantención",
          subtitle: "Bitácora técnica de diagnósticos, acciones, costos, cierres y estado final del equipo.",
          help: "Aquí se registran mantenciones preventivas y correctivas, se conserva trazabilidad de estados y se puede cerrar cada informe actualizando el equipo.",
        },
        reportes: {
          title: "Reportes de Informática",
          subtitle: "Consolidado de estado de equipos, préstamos, atrasos, mantenciones y costos.",
          help: "Esta pantalla consolida métricas e historiales por periodo, tipo de equipo, estado y actividad, con estructura lista para exportación.",
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
        equipos: "EquipmentTab",
        prestamos: "LoansTab",
        mantenciones: "MaintenanceTab",
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
        const response = await axios.get("/api/informatica/catalogs");
        this.catalogs = response.data || this.catalogs;
      } catch (error) {
        this.catalogsError = formatInformaticaError(error, "No se pudieron cargar los catálogos del módulo Informática.");
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
          <InformaticaHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <div class="row g-3 align-items-center">
          <div class="col-xl-8">
            <div class="fw-semibold mb-1">Módulo Informática</div>
            <div class="text-muted">
              Gestión integrada de equipos, préstamos, devoluciones, mantenciones, historial técnico y reportes operativos, respetando el patrón visual actual del sistema.
            </div>
          </div>
          <div class="col-xl-4">
            <div class="d-flex justify-content-xl-end">
              <InformaticaHelpButton
                title="Ayuda: navegación del módulo"
                text="Usa estas pestañas para cambiar entre dashboard, equipos, préstamos, mantenciones y reportes. Cada sección conserva sus propios filtros y acciones."
              />
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
        <LoadingState message="Cargando catálogos del módulo Informática..." compact />
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
