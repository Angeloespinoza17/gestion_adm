<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import LibraryHelpButton from "../../components/library/help-button.vue";
import LibraryGlobalSearch from "../../components/library/global-search.vue";
import DashboardTab from "../../components/library/tabs/dashboard-tab.vue";
import CatalogTab from "../../components/library/tabs/catalog-tab.vue";
import CategoriesTab from "../../components/library/tabs/categories-tab.vue";
import StorageTab from "../../components/library/tabs/storage-tab.vue";
import InventoryTab from "../../components/library/tabs/inventory-tab.vue";
import LoansTab from "../../components/library/tabs/loans-tab.vue";
import MaterialsTab from "../../components/library/tabs/materials-tab.vue";
import TextbooksTab from "../../components/library/tabs/textbooks-tab.vue";
import ReservationsTab from "../../components/library/tabs/reservations-tab.vue";
import PlanTab from "../../components/library/tabs/plan-tab.vue";
import SpacesTab from "../../components/library/tabs/spaces-tab.vue";
import PassesTab from "../../components/library/tabs/passes-tab.vue";
import ReportsTab from "../../components/library/tabs/reports-tab.vue";
import { formatLibraryError } from "../../components/library/module-utils";

const routeMap = {
  "/biblioteca": "dashboard",
  "/biblioteca/catalogo": "catalogo",
  "/biblioteca/categorias": "categorias",
  "/biblioteca/almacenaje": "almacenaje",
  "/biblioteca/inventario": "inventario",
  "/biblioteca/prestamos": "prestamos",
  "/biblioteca/materiales": "materiales",
  "/biblioteca/textos-escolares": "textos",
  "/biblioteca/reservas": "reservas",
  "/biblioteca/plan-lector": "plan",
  "/biblioteca/espacios": "espacios",
  "/biblioteca/pases": "pases",
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
    CategoriesTab,
    StorageTab,
    InventoryTab,
    LoansTab,
    MaterialsTab,
    TextbooksTab,
    ReservationsTab,
    PlanTab,
    SpacesTab,
    PassesTab,
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
        guardians: [],
        staff: [],
        users: [],
        spaces: [],
        categories: [],
        subcategories: [],
        genres: [],
        languages: [],
        locations: [],
        legacy_locations: [],
        education_levels: [],
        capabilities: {},
      },
      tabs: [
        { key: "dashboard", route: "/biblioteca", label: "Resumen", icon: "bx-grid-alt", group: "General" },
        { key: "catalogo", route: "/biblioteca/catalogo", label: "Libros", icon: "bx-book-open", group: "Catálogo" },
        { key: "categorias", route: "/biblioteca/categorias", label: "Categorías", icon: "bx-purchase-tag", group: "Catálogo" },
        { key: "almacenaje", route: "/biblioteca/almacenaje", label: "Salas y estantes", icon: "bx-buildings", group: "Catálogo" },
        { key: "inventario", route: "/biblioteca/inventario", label: "Inventario", icon: "bx-barcode", group: "Catálogo" },
        { key: "prestamos", route: "/biblioteca/prestamos", label: "Préstamos", icon: "bx-transfer", group: "Circulación" },
        { key: "materiales", route: "/biblioteca/materiales", label: "Materiales", icon: "bx-package", group: "Circulación" },
        { key: "textos", route: "/biblioteca/textos-escolares", label: "Textos escolares", icon: "bx-book-bookmark", group: "Circulación" },
        { key: "reservas", route: "/biblioteca/reservas", label: "Reservas", icon: "bx-calendar-check", group: "Planificación" },
        { key: "plan", route: "/biblioteca/plan-lector", label: "Plan lector", icon: "bx-list-check", group: "Planificación" },
        { key: "espacios", route: "/biblioteca/espacios", label: "Espacios", icon: "bx-building", group: "Planificación" },
        { key: "pases", route: "/biblioteca/pases", label: "Pases", icon: "bx-id-card", group: "Convivencia" },
        { key: "reportes", route: "/biblioteca/reportes", label: "Estadísticas", icon: "bx-line-chart", group: "General" },
      ],
      sectionMeta: {
        dashboard: {
          title: "Dashboard Biblioteca Escolar",
          subtitle: "Indicadores operativos, alertas y comportamiento del uso CRA en tiempo real.",
          help: "En esta pantalla se visualizan indicadores en tiempo real de préstamos, devoluciones, mora, reservas, plan lector, disponibilidad del inventario y uso de espacios de biblioteca.",
        },
        catalogo: {
          title: "Gestión de libros",
          subtitle: "Fichas bibliográficas, ISBN, código AVIS, stock y búsqueda asistida por Open Library.",
          help: "En esta pantalla se administra el catálogo bibliográfico de la biblioteca, incluyendo libros, autores, ejemplares, ubicación física, estado e historial de préstamos.",
        },
        categorias: {
          title: "Categorías internas",
          subtitle: "Clasificación institucional configurable, consistente y reutilizable en todo el catálogo.",
          help: "Crea, modifica, ordena o desactiva categorías internas. Las obras asociadas mantienen su trazabilidad.",
        },
        almacenaje: {
          title: "Almacenaje y ubicación",
          subtitle: "Estructura jerárquica de salas, estantes y repisas para ubicar cada ejemplar.",
          help: "Administra Sala 1 para enseñanza media, Sala 2 para básica y sus respectivos estantes y repisas.",
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
        materiales: {
          title: "Materiales a prestar",
          subtitle: "Inventario y préstamos individuales, por docente o para cursos completos.",
          help: "Gestiona materiales didácticos desde el inventario existente y genera fichas de préstamo y firma.",
        },
        textos: {
          title: "Textos escolares",
          subtitle: "Recepción, órdenes por nivel, asignación, entrega firmada y control de pendientes.",
          help: "Registra recepciones, prepara órdenes por nivel y asignatura, y controla la entrega individual con RUT, firma y libros pendientes.",
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
          subtitle: "Agenda de Sala 1 (media), Sala 2 (básica) y solicitudes de instituciones externas.",
          help: "En esta pantalla se reservan y controlan los espacios de biblioteca, con calendario diario, semanal y mensual, responsables, asistentes, recursos solicitados y evidencias.",
        },
        pases: {
          title: "Pase de biblioteca",
          subtitle: "Emisión reglamentada con búsqueda de estudiante, profesor responsable, RUT y firma.",
          help: "Emite y controla pases de biblioteca, previene cruces de horario y deja disponible la integración operativa con Inspectoría.",
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
        categorias: "CategoriesTab",
        almacenaje: "StorageTab",
        inventario: "InventoryTab",
        prestamos: "LoansTab",
        materiales: "MaterialsTab",
        textos: "TextbooksTab",
        reservas: "ReservationsTab",
        plan: "PlanTab",
        espacios: "SpacesTab",
        pases: "PassesTab",
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
    <div class="library-shell d-flex flex-column gap-4">
      <section class="library-hero">
        <div class="hero-orb hero-orb-one"></div>
        <div class="hero-orb hero-orb-two"></div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative">
        <div>
            <div class="hero-eyebrow"><i class="bx bx-book-open"></i> Biblioteca escolar · AVIS</div>
            <h2 class="hero-title mb-2">{{ activeMeta.title }}</h2>
            <div class="hero-subtitle">{{ activeMeta.subtitle }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <LibraryHelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
            <span class="status-pill"><span class="status-dot"></span> Gestión activa</span>
          </div>
        </div>
      </section>

      <BCard class="search-card border-0">
        <div class="row g-3 align-items-center">
          <div class="col-xl-9">
            <LibraryGlobalSearch />
          </div>
        </div>
      </BCard>

      <nav class="module-nav" aria-label="Secciones de biblioteca">
        <router-link
          v-for="tab in tabs"
          :key="tab.key"
          :to="tab.route"
          class="module-link"
          :class="{ active: isTabActive(tab) }"
        >
          <i class="bx" :class="tab.icon"></i>
          <span>{{ tab.label }}</span>
        </router-link>
      </nav>

      <BAlert v-if="catalogsError" show variant="danger">{{ catalogsError }}</BAlert>
      <BCard v-if="catalogsLoading" class="content-card border-0">
        <LoadingState message="Cargando catálogos del módulo Biblioteca..." compact />
      </BCard>

      <main v-else class="library-content">
        <component
          :is="activeComponent"
          :catalogs="catalogs"
          @refresh-catalogs="loadCatalogs"
        />
      </main>
    </div>
  </Layout>
</template>

<style scoped>
.library-shell {
  --library-ink: #183153;
  --library-primary: #3563e9;
  --library-teal: #0f9f8f;
  color: var(--library-ink);
}

.library-hero {
  position: relative;
  overflow: hidden;
  padding: clamp(1.5rem, 3vw, 2.4rem);
  color: #fff;
  border-radius: 24px;
  background:
    linear-gradient(125deg, rgba(15, 42, 77, 0.98), rgba(42, 87, 163, 0.95) 58%, rgba(20, 145, 133, 0.9)),
    radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 38%);
  box-shadow: 0 24px 50px rgba(23, 52, 96, 0.2);
}

.hero-orb {
  position: absolute;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 50%;
}

.hero-orb-one { width: 230px; height: 230px; right: -70px; top: -120px; }
.hero-orb-two { width: 130px; height: 130px; right: 170px; bottom: -100px; }

.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 0.75rem;
  color: #bfe9e2;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.hero-title {
  color: #fff;
  font-weight: 800;
  letter-spacing: -0.035em;
}

.hero-subtitle { max-width: 760px; color: rgba(255, 255, 255, 0.76); }

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 0.9rem;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #74f3bd;
  box-shadow: 0 0 0 5px rgba(116, 243, 189, 0.13);
}

.search-card,
.content-card {
  border-radius: 18px;
  box-shadow: 0 14px 35px rgba(28, 52, 86, 0.08);
}

.search-hint {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  color: #718096;
  font-size: 0.84rem;
  line-height: 1.35;
}

.search-hint i { color: var(--library-primary); font-size: 1.25rem; }

.module-nav {
  display: flex;
  gap: 0.55rem;
  padding: 0.5rem;
  overflow-x: auto;
  border: 1px solid #e8edf5;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 10px 28px rgba(28, 52, 86, 0.06);
  scrollbar-width: thin;
}

.module-link {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.48rem;
  padding: 0.72rem 0.9rem;
  color: #60708a;
  border-radius: 12px;
  font-size: 0.86rem;
  font-weight: 650;
  white-space: nowrap;
  transition: 180ms ease;
}

.module-link:hover {
  color: var(--library-primary);
  background: #f1f5ff;
  transform: translateY(-1px);
}

.module-link.active {
  color: #fff;
  background: linear-gradient(135deg, var(--library-primary), #5b7cf4);
  box-shadow: 0 8px 18px rgba(53, 99, 233, 0.26);
}

.module-link i { font-size: 1.15rem; }
.library-content { min-width: 0; }

@media (max-width: 767px) {
  .library-hero { border-radius: 18px; }
  .module-link span { font-size: 0.8rem; }
}
</style>
