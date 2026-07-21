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
        inventory_assets: [],
        brands: [],
        locations: [],
        capabilities: {},
      },
      tabs: [
        { key: "dashboard", route: "/informatica", label: "Resumen", caption: "Estado general", icon: "bx-grid-alt" },
        { key: "equipos", route: "/informatica/equipos", label: "Equipos", caption: "Inventario", icon: "bx-laptop" },
        { key: "prestamos", route: "/informatica/prestamos", label: "Préstamos", caption: "Entregas y devoluciones", icon: "bx-transfer-alt" },
        { key: "mantenciones", route: "/informatica/mantenciones", label: "Mantenciones", caption: "Gestión técnica", icon: "bx-wrench" },
        { key: "reportes", route: "/informatica/reportes", label: "Reportes", caption: "Análisis y exportación", icon: "bx-bar-chart-alt-2" },
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
    <main class="informatica-module d-flex flex-column gap-4">
      <section class="informatica-hero">
        <div class="informatica-hero__content">
          <div class="informatica-hero__eyebrow"><i class="bx bx-chip"></i> Centro de operaciones TI</div>
          <h1>{{ activeMeta.title }}</h1>
          <p>{{ activeMeta.subtitle }}</p>
          <div class="informatica-hero__status">
            <span><i class="bx bx-check-circle"></i> Gestión centralizada</span>
            <span><i class="bx bx-history"></i> Trazabilidad completa</span>
          </div>
        </div>
        <div class="informatica-hero__art" aria-hidden="true">
          <i class="bx bx-laptop"></i>
        </div>
        <div class="informatica-hero__action">
          <InformaticaHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" variant="light" />
        </div>
      </section>

      <nav class="informatica-nav" aria-label="Secciones de Informática">
        <router-link
          v-for="tab in tabs"
          :key="tab.key"
          :to="tab.route"
          class="informatica-nav__item"
          :class="{ 'is-active': isTabActive(tab) }"
        >
          <span class="informatica-nav__icon"><i :class="`bx ${tab.icon}`"></i></span>
          <span><strong>{{ tab.label }}</strong><small>{{ tab.caption }}</small></span>
          <i class="bx bx-chevron-right informatica-nav__arrow"></i>
        </router-link>
      </nav>

      <BAlert v-if="catalogsError" show variant="danger" class="d-flex align-items-center justify-content-between gap-3 mb-0">
        <span><i class="bx bx-error-circle me-2"></i>{{ catalogsError }}</span>
        <BButton size="sm" variant="danger" @click="loadCatalogs">Reintentar</BButton>
      </BAlert>
      <BCard v-if="catalogsLoading" class="border-0 shadow-sm">
        <LoadingState message="Cargando catálogos del módulo Informática..." compact />
      </BCard>

      <component
        :is="activeComponent"
        v-else
        :catalogs="catalogs"
        @refresh-catalogs="loadCatalogs"
      />
    </main>
  </Layout>
</template>

<style scoped>
.informatica-module { --it-primary: #4057d6; --it-ink: #18213a; --it-muted: #697386; }
.informatica-hero { position: relative; overflow: hidden; display: flex; align-items: center; min-height: 210px; padding: 2.25rem 2.5rem; color: #fff; border-radius: 22px; background: linear-gradient(125deg, #202b55 0%, #344bc0 55%, #3386c8 100%); box-shadow: 0 18px 42px rgba(36, 55, 130, .2); }
.informatica-hero::before { content: ""; position: absolute; width: 360px; height: 360px; right: -100px; top: -210px; border: 70px solid rgba(255,255,255,.08); border-radius: 50%; }
.informatica-hero__content { position: relative; z-index: 2; max-width: 760px; }
.informatica-hero__eyebrow { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .85rem; padding: .35rem .7rem; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; border-radius: 99px; background: rgba(255,255,255,.14); }
.informatica-hero h1 { margin: 0 0 .55rem; color: #fff; font-size: clamp(1.55rem, 3vw, 2.3rem); font-weight: 750; letter-spacing: -.025em; }
.informatica-hero p { max-width: 670px; margin: 0; color: rgba(255,255,255,.78); font-size: 1rem; }
.informatica-hero__status { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1.25rem; color: rgba(255,255,255,.88); font-size: .78rem; }
.informatica-hero__status span { display: inline-flex; align-items: center; gap: .35rem; }
.informatica-hero__art { position: absolute; right: 6%; bottom: -28px; color: rgba(255,255,255,.1); font-size: 10rem; transform: rotate(-8deg); }
.informatica-hero__action { position: absolute; z-index: 3; top: 1.25rem; right: 1.25rem; }
.informatica-nav { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; }
.informatica-nav__item { display: flex; align-items: center; gap: .75rem; min-height: 72px; padding: .85rem 1rem; color: var(--it-muted); border: 1px solid #e8ebf3; border-radius: 14px; background: var(--bs-card-bg, #fff); box-shadow: 0 4px 14px rgba(31,45,86,.045); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
.informatica-nav__item:hover { color: var(--it-primary); border-color: #cbd3fa; box-shadow: 0 9px 22px rgba(45,63,145,.1); transform: translateY(-2px); }
.informatica-nav__item.is-active { color: var(--it-primary); border-color: #aebafb; background: linear-gradient(135deg, rgba(85,110,230,.12), rgba(80,165,241,.05)); box-shadow: inset 0 -3px 0 var(--it-primary); }
.informatica-nav__icon { display: grid; flex: 0 0 38px; height: 38px; place-items: center; border-radius: 11px; background: #f0f3ff; font-size: 1.25rem; }
.informatica-nav__item.is-active .informatica-nav__icon { color: #fff; background: var(--it-primary); }
.informatica-nav__item strong, .informatica-nav__item small { display: block; }
.informatica-nav__item strong { color: var(--it-ink); font-size: .9rem; }
.informatica-nav__item small { margin-top: .1rem; color: inherit; font-size: .7rem; }
.informatica-nav__arrow { margin-left: auto; opacity: .45; }
:deep(.card) { border-radius: 16px; }
:deep(.card-header) { padding: 1rem 1.25rem; border-bottom-color: #edf0f6; background: transparent; }
:deep(.table) { margin-bottom: 0; }
:deep(.table > thead > tr > th) { padding: .8rem .85rem; color: #717b8f; border-bottom: 1px solid #e8ebf2; background: #f8f9fc; font-size: .69rem; font-weight: 750; letter-spacing: .045em; text-transform: uppercase; white-space: nowrap; }
:deep(.table > tbody > tr > td) { padding: .9rem .85rem; vertical-align: middle; border-color: #eff1f5; }
:deep(.table > tbody > tr:hover > td) { background: rgba(85,110,230,.035); }
:deep(.form-label) { margin-bottom: .4rem; color: #465064; font-size: .78rem; font-weight: 650; }
:deep(.form-control), :deep(.form-select) { min-height: 40px; border-color: #dfe3ec; border-radius: 9px; }
:deep(.btn) { border-radius: 9px; font-weight: 550; }
:deep(.modal-content) { overflow: hidden; border: 0; border-radius: 18px; box-shadow: 0 24px 70px rgba(23,32,65,.22); }
@media (max-width: 1199.98px) { .informatica-nav { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 767.98px) { .informatica-hero { min-height: 240px; padding: 1.5rem; align-items: flex-end; } .informatica-hero__action { top: 1rem; right: 1rem; } .informatica-hero__status { gap: .55rem; } .informatica-nav { display: flex; overflow-x: auto; padding-bottom: .35rem; scroll-snap-type: x mandatory; } .informatica-nav__item { min-width: 185px; scroll-snap-align: start; } }
</style>
