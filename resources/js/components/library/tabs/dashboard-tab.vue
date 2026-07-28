<script>
import axios from "axios";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatLibraryDate,
  formatLibraryDateTime,
  formatLibraryError,
} from "../module-utils";

export default {
  components: {
    LibraryHelpButton,
    LibraryStatusBadge,
    LoadingState,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      error: null,
      dashboard: {
        metrics: {},
        alerts: {},
        charts: {
          loans_by_month: { labels: [], series: [] },
          loans_by_course: [],
          most_loaned_books: [],
          categories_usage: [],
          subcategories_usage: [],
          overdue_by_course: [],
          space_usage_by_month: { labels: [], series: [] },
          reading_plan_participation: [],
          inventory_availability: [],
        },
        recent: {
          loans: [],
          reservations: [],
          alerts: [],
        },
      },
    };
  },
  computed: {
    todayLabel() {
      return new Intl.DateTimeFormat("es-CL", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      }).format(new Date());
    },
    primaryMetrics() {
      const metrics = this.dashboard.metrics || {};
      return [
        {
          label: "Préstamos activos",
          value: metrics.active_loans || 0,
          detail: `${metrics.month_loans || 0} registrados este mes`,
          icon: "bx-transfer-alt",
          tone: "indigo",
          route: "/biblioteca/prestamos",
        },
        {
          label: "Ejemplares disponibles",
          value: metrics.total_copies_available || 0,
          detail: `de ${this.inventoryTotal} ejemplares controlados`,
          icon: "bx-layer",
          tone: "emerald",
          route: "/biblioteca/inventario",
        },
        {
          label: "Devoluciones pendientes",
          value: metrics.pending_returns || 0,
          detail: `${this.dashboard.alerts?.upcoming_returns || 0} vencen próximamente`,
          icon: "bx-calendar-x",
          tone: "amber",
          route: "/biblioteca/prestamos",
        },
        {
          label: "Tasa de atrasos",
          value: `${metrics.overdue_rate || 0}%`,
          detail: `${this.dashboard.alerts?.overdue_loans || 0} préstamos vencidos`,
          icon: "bx-timer",
          tone: "rose",
          route: "/biblioteca/reportes",
        },
      ];
    },
    secondaryMetrics() {
      const metrics = this.dashboard.metrics || {};
      return [
        { label: "Obras", value: metrics.total_books || 0, icon: "bx-book-open", tone: "blue" },
        { label: "Prestados", value: metrics.copies_loaned || 0, icon: "bx-log-out-circle", tone: "purple" },
        { label: "Personas con mora", value: (metrics.students_overdue || 0) + (metrics.staff_overdue || 0), icon: "bx-user-x", tone: "red" },
        { label: "Textos pendientes", value: metrics.pending_textbook_deliveries || 0, icon: "bx-book-bookmark", tone: "purple" },
        { label: "Órdenes con faltantes", value: metrics.textbook_orders_with_shortages || 0, icon: "bx-error-circle", tone: "orange" },
        { label: "Pases activos", value: metrics.active_library_passes || 0, icon: "bx-id-card", tone: "green" },
        { label: "Espacios reservados", value: metrics.reserved_spaces || 0, icon: "bx-building-house", tone: "blue" },
        { label: "Dañados o perdidos", value: (metrics.damaged_books || 0) + (metrics.lost_books || 0), icon: "bx-error-alt", tone: "red" },
      ];
    },
    quickActions() {
      return [
        { label: "Registrar préstamo", detail: "Estudiante, curso o docente", route: "/biblioteca/prestamos", icon: "bx-transfer", tone: "indigo" },
        { label: "Ingresar libro", detail: "Catálogo y ejemplares", route: "/biblioteca/catalogo", icon: "bx-book-open", tone: "blue" },
        { label: "Entregar textos", detail: "Nóminas y pendientes", route: "/biblioteca/textos-escolares", icon: "bx-book-content", tone: "amber" },
        { label: "Reservar espacio", detail: "Sala 1 o Sala 2", route: "/biblioteca/espacios", icon: "bx-calendar-plus", tone: "emerald" },
      ];
    },
    alertCards() {
      const alerts = this.dashboard.alerts || {};
      return [
        { label: "Préstamos vencidos", detail: "Requieren gestión de devolución", value: alerts.overdue_loans || 0, icon: "bx-time-five", tone: "critical", priority: 1, route: "/biblioteca/prestamos" },
        { label: "Libros perdidos", detail: "Regularizar inventario", value: alerts.lost_books || 0, icon: "bx-error", tone: "critical", priority: 1, route: "/biblioteca/inventario" },
        { label: "Libros dañados", detail: "Evaluar reparación o baja", value: alerts.damaged_books || 0, icon: "bx-error-alt", tone: "warning", priority: 2, route: "/biblioteca/inventario" },
        { label: "Baja disponibilidad", detail: "Revisar títulos con poco stock", value: alerts.low_availability || 0, icon: "bx-layer", tone: "warning", priority: 2, route: "/biblioteca/inventario" },
        { label: "Plan lector con faltantes", detail: "Completar ejemplares requeridos", value: alerts.reading_plan_shortages || 0, icon: "bx-list-check", tone: "warning", priority: 2, route: "/biblioteca/plan-lector" },
        { label: "Reservas pendientes", detail: "Esperan revisión o aprobación", value: alerts.pending_reservations || 0, icon: "bx-bookmark", tone: "info", priority: 3, route: "/biblioteca/reservas" },
        { label: "Próximas devoluciones", detail: "Vencen durante los próximos días", value: alerts.upcoming_returns || 0, icon: "bx-calendar-event", tone: "info", priority: 3, route: "/biblioteca/prestamos" },
        { label: "Usuarios con mora", detail: "Personas con préstamos vencidos", value: alerts.users_with_overdue || 0, icon: "bx-user-x", tone: "critical", priority: 1, route: "/biblioteca/prestamos" },
        { label: "Recursos reservados hoy", detail: "Retiros programados para hoy", value: alerts.resources_reserved_today || 0, icon: "bx-package", tone: "info", priority: 3, route: "/biblioteca/reservas" },
        { label: "Espacios reservados hoy", detail: "Actividades programadas", value: alerts.spaces_reserved_today || 0, icon: "bx-building-house", tone: "success", priority: 4, route: "/biblioteca/espacios" },
      ];
    },
    activeAlerts() {
      return this.alertCards
        .filter((item) => Number(item.value) > 0)
        .sort((a, b) => a.priority - b.priority || b.value - a.value);
    },
    inventoryTotal() {
      const metrics = this.dashboard.metrics || {};
      return (metrics.total_copies_available || 0)
        + (metrics.copies_loaned || 0)
        + (metrics.damaged_books || 0)
        + (metrics.lost_books || 0);
    },
    inventoryHealth() {
      if (!this.inventoryTotal) return 0;
      return Math.round(((this.dashboard.metrics?.total_copies_available || 0) / this.inventoryTotal) * 100);
    },
    inventoryHealthLabel() {
      if (!this.inventoryTotal) return "Sin inventario";
      if (this.inventoryHealth >= 80) return "Disponibilidad saludable";
      if (this.inventoryHealth >= 55) return "Disponibilidad moderada";
      return "Disponibilidad crítica";
    },
    monthChartOptions() {
      return {
        ...basicApexOptions({
          categories: this.dashboard.charts?.loans_by_month?.labels || [],
          colors: ["#556ee6"],
        }),
      };
    },
    courseChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.loans_by_course),
          colors: ["#34c38f"],
          horizontal: true,
        }),
      };
    },
    workChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.most_loaned_books),
          colors: ["#f1b44c"],
          horizontal: true,
        }),
      };
    },
    categoryChartOptions() {
      return {
        labels: extractChartLabels(this.dashboard.charts?.categories_usage),
        legend: { position: "bottom" },
        dataLabels: { enabled: true },
        colors: ["#556ee6", "#34c38f", "#50a5f1", "#f1b44c", "#f46a6a", "#74788d"],
      };
    },
    subcategoryChartOptions() {
      const options = basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.subcategories_usage),
        colors: ["#7c5ce5"],
        horizontal: true,
      });

      return {
        ...options,
        plotOptions: {
          bar: {
            ...options.plotOptions.bar,
            barHeight: "52%",
          },
        },
        yaxis: {
          labels: {
            maxWidth: 250,
          },
        },
      };
    },
    overdueChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.overdue_by_course),
          colors: ["#f46a6a"],
        }),
      };
    },
    spaceUsageChartOptions() {
      return {
        ...basicApexOptions({
          categories: this.dashboard.charts?.space_usage_by_month?.labels || [],
          colors: ["#50a5f1"],
        }),
      };
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    extractChartTotals,
    formatLibraryDate,
    formatLibraryDateTime,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo cargar el dashboard de Biblioteca.");
      } finally {
        this.loading = false;
      }
    },
    seriesFrom(labelsAndValues, name = "Total") {
      if (Array.isArray(labelsAndValues?.series)) {
        return [{ name, data: labelsAndValues.series }];
      }

      return [{ name, data: extractChartTotals(labelsAndValues) }];
    },
    hasChartData(value) {
      if (Array.isArray(value?.series)) {
        return value.series.some((item) => Number(item) > 0);
      }

      return Array.isArray(value) && value.some((item) => Number(item?.total) > 0);
    },
  },
};
</script>

<template>
  <div class="dashboard-view">
    <section class="command-bar">
      <div class="command-copy">
        <div class="section-kicker"><span></span> Operación diaria</div>
        <h3>Todo lo importante, en un vistazo</h3>
        <p>{{ todayLabel }} · Prioridades, circulación e inventario de la biblioteca.</p>
      </div>
      <div class="command-actions">
        <LibraryHelpButton
          title="Ayuda: panel operativo CRA"
          text="Este panel resume préstamos, devoluciones, mora, reservas, plan lector y uso de espacios con foco operativo para la bibliotecaria."
        />
        <BButton class="refresh-button" :disabled="loading" @click="loadDashboard">
          <i class="bx bx-refresh" :class="{ spinning: loading }"></i>
          Actualizar datos
        </BButton>
      </div>
    </section>

    <BAlert v-if="error" show variant="danger" class="border-0 rounded-4">{{ error }}</BAlert>
    <BCard v-if="loading" class="loading-card border-0">
      <LoadingState message="Cargando indicadores de biblioteca..." compact />
    </BCard>

    <template v-else>
      <section class="quick-actions" aria-label="Acciones frecuentes">
        <router-link
          v-for="action in quickActions"
          :key="action.label"
          :to="action.route"
          class="quick-action"
          :class="`quick-${action.tone}`"
        >
          <span class="quick-icon"><i class="bx" :class="action.icon"></i></span>
          <span class="quick-copy">
            <strong>{{ action.label }}</strong>
            <small>{{ action.detail }}</small>
          </span>
          <i class="bx bx-chevron-right quick-arrow"></i>
        </router-link>
      </section>

      <section class="overview-layout">
        <div class="overview-main">
          <div class="section-heading">
            <div>
              <div class="section-kicker"><span></span> Indicadores clave</div>
              <h4>Estado de la operación</h4>
            </div>
            <router-link to="/biblioteca/reportes" class="section-link">
              Ver estadísticas <i class="bx bx-right-arrow-alt"></i>
            </router-link>
          </div>

          <div class="primary-metrics">
            <router-link
              v-for="card in primaryMetrics"
              :key="card.label"
              :to="card.route"
              class="primary-metric"
              :class="`primary-${card.tone}`"
            >
              <div class="primary-top">
                <span class="primary-icon"><i class="bx" :class="card.icon"></i></span>
                <i class="bx bx-up-arrow-alt metric-arrow"></i>
              </div>
              <div class="primary-value">{{ card.value }}</div>
              <div class="primary-label">{{ card.label }}</div>
              <div class="primary-detail">{{ card.detail }}</div>
            </router-link>
          </div>

          <div class="secondary-strip">
            <article
              v-for="card in secondaryMetrics"
              :key="card.label"
              class="secondary-metric"
              :class="`secondary-${card.tone}`"
            >
              <span class="secondary-icon"><i class="bx" :class="card.icon"></i></span>
              <span>
                <small>{{ card.label }}</small>
                <strong>{{ card.value }}</strong>
              </span>
            </article>
          </div>
        </div>

        <aside class="priority-panel">
          <div class="priority-head">
            <div>
              <div class="section-kicker light"><span></span> Centro de atención</div>
              <h4>Prioridades activas</h4>
            </div>
            <span class="priority-count">{{ activeAlerts.length }}</span>
          </div>

          <div v-if="activeAlerts.length" class="priority-list">
            <router-link
              v-for="item in activeAlerts.slice(0, 5)"
              :key="item.label"
              :to="item.route"
              class="priority-item"
              :class="`priority-${item.tone}`"
            >
              <span class="priority-icon"><i class="bx" :class="item.icon"></i></span>
              <span class="priority-copy">
                <strong>{{ item.label }}</strong>
                <small>{{ item.detail }}</small>
              </span>
              <span class="priority-value">{{ item.value }}</span>
            </router-link>
          </div>
          <div v-else class="priority-empty">
            <span><i class="bx bx-check-shield"></i></span>
            <strong>Todo bajo control</strong>
            <p>No hay alertas operativas que requieran atención inmediata.</p>
          </div>

          <div class="inventory-health">
            <div
              class="health-ring"
              :style="{ '--health': `${inventoryHealth * 3.6}deg` }"
            >
              <span>{{ inventoryHealth }}%</span>
            </div>
            <div>
              <small>Salud del inventario</small>
              <strong>{{ inventoryHealthLabel }}</strong>
              <span>{{ dashboard.metrics?.total_copies_available || 0 }} disponibles de {{ inventoryTotal }}</span>
            </div>
          </div>
        </aside>
      </section>

      <section class="analytics-section">
        <div class="section-heading">
          <div>
            <div class="section-kicker"><span></span> Comportamiento</div>
            <h4>Análisis de circulación</h4>
          </div>
          <span class="section-note">Datos actualizados en tiempo real</span>
        </div>

        <div class="chart-grid">
          <article class="chart-card chart-wide">
            <div class="chart-card-head">
              <div>
                <span>Actividad anual</span>
                <h5>Préstamos por mes</h5>
              </div>
              <span class="chart-badge"><i class="bx bx-bar-chart-alt-2"></i> Tendencia</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.loans_by_month)"
              type="area"
              height="280"
              :options="monthChartOptions"
              :series="seriesFrom(dashboard.charts?.loans_by_month, 'Préstamos')"
            />
            <div v-else class="chart-empty">
              <i class="bx bx-line-chart"></i>
              <strong>Aún no hay actividad mensual</strong>
              <span>Los préstamos aparecerán aquí al comenzar la circulación.</span>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-head">
              <div>
                <span>Distribución</span>
                <h5>Préstamos por curso</h5>
              </div>
              <span class="chart-badge green"><i class="bx bx-group"></i> Cursos</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.loans_by_course)"
              type="bar"
              height="280"
              :options="courseChartOptions"
              :series="seriesFrom(dashboard.charts?.loans_by_course, 'Préstamos')"
            />
            <div v-else class="chart-empty compact">
              <i class="bx bx-group"></i>
              <strong>Sin préstamos por curso</strong>
              <span>La distribución se mostrará cuando existan movimientos.</span>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-head">
              <div><span>Preferencias</span><h5>Libros más prestados</h5></div>
              <span class="chart-badge amber"><i class="bx bx-star"></i> Ranking</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.most_loaned_books)"
              type="bar"
              height="260"
              :options="workChartOptions"
              :series="seriesFrom(dashboard.charts?.most_loaned_books, 'Préstamos')"
            />
            <div v-else class="chart-empty compact">
              <i class="bx bx-book-open"></i>
              <strong>Ranking en preparación</strong>
              <span>Necesitamos más préstamos para identificar preferencias.</span>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-head">
              <div><span>Catálogo</span><h5>Categorías utilizadas</h5></div>
              <span class="chart-badge purple"><i class="bx bx-pie-chart-alt"></i> Mezcla</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.categories_usage)"
              type="donut"
              height="260"
              :options="categoryChartOptions"
              :series="extractChartTotals(dashboard.charts?.categories_usage)"
            />
            <div v-else class="chart-empty compact">
              <i class="bx bx-pie-chart-alt-2"></i>
              <strong>Sin categorías utilizadas</strong>
              <span>La composición aparecerá junto con los préstamos.</span>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-head">
              <div><span>Seguimiento</span><h5>Mora por curso</h5></div>
              <span class="chart-badge red"><i class="bx bx-error-circle"></i> Riesgo</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.overdue_by_course)"
              type="bar"
              height="260"
              :options="overdueChartOptions"
              :series="seriesFrom(dashboard.charts?.overdue_by_course, 'Mora')"
            />
            <div v-else class="chart-empty compact positive">
              <i class="bx bx-check-circle"></i>
              <strong>Sin mora por curso</strong>
              <span>No hay préstamos vencidos que agrupar.</span>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-head">
              <div><span>Planificación</span><h5>Uso de espacios</h5></div>
              <span class="chart-badge cyan"><i class="bx bx-building"></i> Salas</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.space_usage_by_month)"
              type="area"
              height="260"
              :options="spaceUsageChartOptions"
              :series="seriesFrom(dashboard.charts?.space_usage_by_month, 'Reservas')"
            />
            <div v-else class="chart-empty compact">
              <i class="bx bx-building-house"></i>
              <strong>Sin reservas de espacios</strong>
              <span>Las reservas de Sala 1 y Sala 2 aparecerán aquí.</span>
            </div>
          </article>

          <article class="chart-card">
            <div class="chart-card-head">
              <div><span>Catálogo</span><h5>Subcategorías utilizadas</h5></div>
              <span class="chart-badge purple"><i class="bx bx-category"></i> Detalle</span>
            </div>
            <apexchart
              v-if="hasChartData(dashboard.charts?.subcategories_usage)"
              type="bar"
              height="260"
              :options="subcategoryChartOptions"
              :series="seriesFrom(dashboard.charts?.subcategories_usage, 'Préstamos')"
            />
            <div v-else class="chart-empty compact">
              <i class="bx bx-category-alt"></i>
              <strong>Sin subcategorías utilizadas</strong>
              <span>Los préstamos de títulos clasificados por subcategoría aparecerán aquí.</span>
            </div>
          </article>
        </div>
      </section>

      <section class="activity-section">
        <div class="section-heading">
          <div>
            <div class="section-kicker"><span></span> Actividad reciente</div>
            <h4>Últimos movimientos</h4>
          </div>
        </div>

        <div class="activity-grid">
          <article class="activity-card loans-card">
            <div class="activity-head">
              <div><span class="activity-icon indigo"><i class="bx bx-transfer"></i></span><div><small>Circulación</small><h5>Últimos préstamos</h5></div></div>
              <router-link to="/biblioteca/prestamos">Ver todos</router-link>
            </div>
            <BTable
              v-if="dashboard.recent?.loans?.length"
              small
              responsive
              borderless
              :items="dashboard.recent.loans"
              :fields="[
                { key: 'borrower_name_snapshot', label: 'Usuario' },
                { key: 'obra_title', label: 'Obra' },
                { key: 'due_at', label: 'Entrega' },
              ]"
            >
              <template #cell(obra_title)="{ item }">
                <strong>{{ item.obra?.title || "-" }}</strong>
                <small>{{ item.loan_code }}</small>
              </template>
              <template #cell(due_at)="{ item }">{{ formatLibraryDate(item.due_at) }}</template>
            </BTable>
            <div v-else class="activity-empty">
              <i class="bx bx-book-open"></i>
              <strong>Aún no hay préstamos</strong>
              <span>Registra el primer movimiento para iniciar la trazabilidad.</span>
              <router-link to="/biblioteca/prestamos">Registrar préstamo</router-link>
            </div>
          </article>

          <article class="activity-card reservations-card">
            <div class="activity-head">
              <div><span class="activity-icon amber"><i class="bx bx-bookmark"></i></span><div><small>Agenda</small><h5>Últimas reservas</h5></div></div>
              <router-link to="/biblioteca/reservas">Ver todas</router-link>
            </div>
            <BTable
              v-if="dashboard.recent?.reservations?.length"
              small
              responsive
              borderless
              :items="dashboard.recent.reservations"
              :fields="[
                { key: 'resource_type', label: 'Tipo' },
                { key: 'obra_title', label: 'Recurso' },
                { key: 'status', label: 'Estado' },
              ]"
            >
              <template #cell(obra_title)="{ item }">
                <strong>{{ item.obra?.title || "-" }}</strong>
                <small>{{ item.reservation_code }}</small>
              </template>
              <template #cell(status)="{ item }"><LibraryStatusBadge :status="item.status" /></template>
            </BTable>
            <div v-else class="activity-empty">
              <i class="bx bx-calendar-check"></i>
              <strong>Sin reservas recientes</strong>
              <span>Las nuevas solicitudes quedarán visibles en este espacio.</span>
              <router-link to="/biblioteca/reservas">Gestionar reservas</router-link>
            </div>
          </article>

          <article class="activity-card alerts-card">
            <div class="activity-head">
              <div><span class="activity-icon rose"><i class="bx bx-bell"></i></span><div><small>Seguimiento</small><h5>Alertas recientes</h5></div></div>
            </div>
            <div v-if="dashboard.recent?.alerts?.length" class="recent-alerts">
              <div v-for="alert in dashboard.recent.alerts.slice(0, 5)" :key="alert.id" class="recent-alert">
                <span></span>
                <div>
                  <strong>{{ alert.title }}</strong>
                  <small>{{ formatLibraryDateTime(alert.created_at) }}</small>
                </div>
              </div>
            </div>
            <div v-else class="activity-empty">
              <i class="bx bx-check-shield"></i>
              <strong>Sin alertas recientes</strong>
              <span>El historial de alertas aparecerá aquí cuando corresponda.</span>
            </div>
          </article>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.dashboard-view {
  --ink: #1d2b45;
  --muted: #78859a;
  --line: #e5ebf4;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  color: var(--ink);
}

.command-bar,
.section-heading,
.command-actions,
.quick-action,
.primary-top,
.secondary-metric,
.priority-head,
.priority-item,
.inventory-health,
.chart-card-head,
.activity-head,
.activity-head > div,
.recent-alert {
  display: flex;
  align-items: center;
}

.command-bar {
  justify-content: space-between;
  gap: 1.25rem;
  padding: 0.35rem 0.15rem 0;
}

.command-copy h3 {
  margin: 0.2rem 0 0.25rem;
  color: var(--ink);
  font-size: clamp(1.35rem, 2vw, 1.7rem);
  font-weight: 780;
  letter-spacing: -0.035em;
}

.command-copy p,
.section-note {
  margin: 0;
  color: var(--muted);
  font-size: 0.78rem;
}

.section-kicker {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: #4c6fd7;
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.section-kicker span {
  width: 18px;
  height: 2px;
  border-radius: 99px;
  background: currentColor;
}

.section-kicker.light { color: #aebfff; }
.command-actions { gap: 0.55rem; }

.refresh-button {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.66rem 0.95rem;
  border: 0;
  border-radius: 12px;
  background: #233861;
  box-shadow: 0 8px 18px rgba(35, 56, 97, 0.18);
}

.refresh-button i { font-size: 1.08rem; }
.spinning { animation: spin 800ms linear infinite; }
.loading-card { border-radius: 18px; box-shadow: 0 12px 28px rgba(29, 43, 69, 0.08); }

.quick-actions {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
}

.quick-action {
  gap: 0.7rem;
  min-width: 0;
  padding: 0.9rem;
  color: var(--ink);
  border: 1px solid var(--line);
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 9px 24px rgba(31, 49, 81, 0.055);
  transition: 180ms ease;
}

.quick-action:hover {
  color: var(--ink);
  border-color: #cfd9eb;
  box-shadow: 0 14px 28px rgba(31, 49, 81, 0.1);
  transform: translateY(-2px);
}

.quick-icon,
.primary-icon,
.secondary-icon,
.priority-icon,
.activity-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
}

.quick-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  font-size: 1.2rem;
}

.quick-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.quick-copy strong { color: #25344f; font-size: 0.8rem; }
.quick-copy small { overflow: hidden; color: #8a95a7; font-size: 0.65rem; text-overflow: ellipsis; white-space: nowrap; }
.quick-arrow { color: #aab3c1; font-size: 1.2rem; }
.quick-indigo .quick-icon { color: #536ee8; background: #eef1ff; }
.quick-blue .quick-icon { color: #337fc0; background: #eaf5ff; }
.quick-amber .quick-icon { color: #ca831d; background: #fff5df; }
.quick-emerald .quick-icon { color: #168b6c; background: #e8f8f1; }

.overview-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.65fr) minmax(300px, 0.72fr);
  gap: 1rem;
}

.overview-main,
.analytics-section,
.activity-section {
  padding: 1.25rem;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 14px 34px rgba(31, 49, 81, 0.065);
}

.section-heading {
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.section-heading h4,
.priority-head h4 {
  margin: 0.18rem 0 0;
  color: var(--ink);
  font-size: 1.05rem;
  font-weight: 760;
}

.section-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  color: #536ee8;
  font-size: 0.72rem;
  font-weight: 700;
}

.primary-metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
}

.primary-metric {
  position: relative;
  overflow: hidden;
  min-height: 176px;
  padding: 0.95rem;
  color: #fff;
  border-radius: 17px;
  box-shadow: 0 14px 25px rgba(49, 71, 124, 0.14);
  transition: 180ms ease;
}

.primary-metric::after {
  position: absolute;
  width: 110px;
  height: 110px;
  right: -46px;
  top: -48px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 50%;
  content: "";
}

.primary-metric:hover { color: #fff; transform: translateY(-2px); }
.primary-indigo { background: linear-gradient(145deg, #394f91, #6578e9); }
.primary-emerald { background: linear-gradient(145deg, #137565, #24aa8a); }
.primary-amber { background: linear-gradient(145deg, #a96b18, #e5a53d); }
.primary-rose { background: linear-gradient(145deg, #a64c5b, #e16c79); }
.primary-top { justify-content: space-between; }
.primary-icon { width: 35px; height: 35px; border-radius: 10px; background: rgba(255, 255, 255, 0.14); font-size: 1.1rem; }
.metric-arrow { opacity: 0.55; font-size: 1.2rem; transform: rotate(45deg); }
.primary-value { margin-top: 1.05rem; font-size: 2rem; font-weight: 800; line-height: 1; letter-spacing: -0.055em; }
.primary-label { margin-top: 0.35rem; font-size: 0.74rem; font-weight: 750; }
.primary-detail { margin-top: 0.15rem; color: rgba(255, 255, 255, 0.68); font-size: 0.62rem; line-height: 1.3; }

.secondary-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.55rem;
  margin-top: 0.75rem;
}

.secondary-metric {
  gap: 0.55rem;
  min-width: 0;
  padding: 0.62rem 0.7rem;
  border: 1px solid #edf0f5;
  border-radius: 12px;
  background: #fafbfe;
}

.secondary-icon { width: 31px; height: 31px; border-radius: 9px; color: #536ee8; background: #edf1ff; }
.secondary-metric > span:last-child { display: flex; min-width: 0; flex-direction: column; }
.secondary-metric small { overflow: hidden; color: #8a95a7; font-size: 0.6rem; text-overflow: ellipsis; white-space: nowrap; }
.secondary-metric strong { color: #2a3851; font-size: 0.96rem; line-height: 1.1; }
.secondary-green .secondary-icon { color: #158667; background: #e8f7f1; }
.secondary-purple .secondary-icon { color: #8158ce; background: #f1ecff; }
.secondary-orange .secondary-icon { color: #c67f1b; background: #fff3dc; }
.secondary-red .secondary-icon { color: #ce555d; background: #ffebed; }

.priority-panel {
  position: relative;
  overflow: hidden;
  padding: 1.25rem;
  color: #fff;
  border-radius: 20px;
  background:
    radial-gradient(circle at 95% 3%, rgba(105, 131, 242, 0.34), transparent 34%),
    linear-gradient(160deg, #172642, #263d68);
  box-shadow: 0 18px 36px rgba(24, 40, 69, 0.18);
}

.priority-head { justify-content: space-between; margin-bottom: 0.9rem; }
.priority-head h4 { color: #fff; }
.priority-count { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 50%; background: rgba(255, 255, 255, 0.09); font-size: 0.78rem; font-weight: 800; }
.priority-list { display: flex; flex-direction: column; gap: 0.48rem; }

.priority-item {
  gap: 0.6rem;
  padding: 0.63rem;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.065);
  transition: 160ms ease;
}

.priority-item:hover { color: #fff; background: rgba(255, 255, 255, 0.11); transform: translateX(2px); }
.priority-icon { width: 32px; height: 32px; border-radius: 9px; background: rgba(255, 255, 255, 0.1); }
.priority-critical .priority-icon { color: #ff929a; background: rgba(247, 100, 112, 0.14); }
.priority-warning .priority-icon { color: #ffc96f; background: rgba(244, 175, 64, 0.14); }
.priority-info .priority-icon { color: #8fc7ff; background: rgba(77, 157, 236, 0.14); }
.priority-success .priority-icon { color: #7be1bd; background: rgba(58, 196, 146, 0.14); }
.priority-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.priority-copy strong { font-size: 0.68rem; }
.priority-copy small { overflow: hidden; color: rgba(255, 255, 255, 0.52); font-size: 0.58rem; text-overflow: ellipsis; white-space: nowrap; }
.priority-value { display: grid; min-width: 28px; height: 28px; padding: 0 0.35rem; place-items: center; border-radius: 9px; background: rgba(255, 255, 255, 0.11); font-size: 0.72rem; font-weight: 800; }

.priority-empty { display: grid; min-height: 206px; place-items: center; align-content: center; text-align: center; }
.priority-empty > span { display: grid; width: 52px; height: 52px; margin-bottom: 0.65rem; place-items: center; border-radius: 16px; color: #79dfbb; background: rgba(67, 202, 152, 0.12); font-size: 1.5rem; }
.priority-empty strong { font-size: 0.84rem; }
.priority-empty p { max-width: 220px; margin: 0.2rem auto 0; color: rgba(255, 255, 255, 0.5); font-size: 0.64rem; }

.inventory-health {
  gap: 0.65rem;
  margin-top: 0.9rem;
  padding-top: 0.9rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.health-ring {
  display: grid;
  width: 54px;
  height: 54px;
  flex: 0 0 54px;
  place-items: center;
  border-radius: 50%;
  background: conic-gradient(#61dab1 var(--health), rgba(255, 255, 255, 0.1) 0);
}

.health-ring::before { grid-area: 1/1; width: 42px; height: 42px; border-radius: 50%; background: #233657; content: ""; }
.health-ring span { z-index: 1; grid-area: 1/1; font-size: 0.65rem; font-weight: 800; }
.inventory-health > div:last-child { display: flex; flex-direction: column; }
.inventory-health small { color: rgba(255, 255, 255, 0.48); font-size: 0.58rem; }
.inventory-health strong { font-size: 0.7rem; }
.inventory-health > div:last-child > span { color: rgba(255, 255, 255, 0.56); font-size: 0.58rem; }

.analytics-section,
.activity-section { padding: 1.3rem; }
.chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
.chart-card { min-width: 0; padding: 1rem; border: 1px solid #e9edf4; border-radius: 16px; background: linear-gradient(145deg, #fff, #fcfdff); }
.chart-wide { grid-column: 1 / -1; }
.chart-card-head { justify-content: space-between; gap: 1rem; margin-bottom: 0.35rem; }
.chart-card-head > div { display: flex; flex-direction: column; }
.chart-card-head span { color: #919bac; font-size: 0.58rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
.chart-card-head h5 { margin: 0.1rem 0 0; color: #26344e; font-size: 0.84rem; font-weight: 750; }
.chart-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.38rem 0.55rem; color: #506fe0 !important; border-radius: 99px; background: #edf1ff; font-size: 0.54rem !important; }
.chart-badge.green { color: #168568 !important; background: #e9f8f2; }
.chart-badge.amber { color: #be791b !important; background: #fff3de; }
.chart-badge.purple { color: #7c52c7 !important; background: #f1ecff; }
.chart-badge.red { color: #c84f59 !important; background: #ffebed; }
.chart-badge.cyan { color: #2e7caa !important; background: #e9f6fd; }

.chart-empty {
  display: flex;
  min-height: 280px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: #99a5b8;
  text-align: center;
}

.chart-empty.compact { min-height: 260px; }
.chart-empty i { margin-bottom: 0.5rem; color: #b5c1d4; font-size: 2rem; }
.chart-empty.positive i { color: #4fc198; }
.chart-empty strong { color: #68758b; font-size: 0.75rem; }
.chart-empty span { max-width: 280px; margin-top: 0.2rem; font-size: 0.62rem; }

.activity-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
.activity-card { min-width: 0; padding: 1rem; border: 1px solid #e9edf4; border-radius: 16px; background: #fff; }
.alerts-card { grid-column: 1 / -1; }
.activity-head { justify-content: space-between; gap: 1rem; margin-bottom: 0.85rem; }
.activity-head > div { gap: 0.6rem; }
.activity-head > div > div { display: flex; flex-direction: column; }
.activity-icon { width: 37px; height: 37px; border-radius: 11px; background: #edf1ff; color: #536ee8; font-size: 1.1rem; }
.activity-icon.amber { color: #be791b; background: #fff3de; }
.activity-icon.rose { color: #c84f59; background: #ffebed; }
.activity-head small { color: #98a1b0; font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.08em; }
.activity-head h5 { margin: 0; color: #283650; font-size: 0.82rem; font-weight: 750; }
.activity-head > a { color: #536ee8; font-size: 0.65rem; font-weight: 700; }

.activity-card :deep(.table) { margin-bottom: 0; }
.activity-card :deep(.table > :not(caption) > * > *) { padding: 0.58rem 0.45rem; border-color: #eef1f5; color: #58657a; font-size: 0.66rem; vertical-align: middle; }
.activity-card :deep(.table thead th) { color: #929cab; font-size: 0.56rem; letter-spacing: 0.05em; text-transform: uppercase; }
.activity-card :deep(td strong) { display: block; max-width: 210px; overflow: hidden; color: #2b3952; font-size: 0.66rem; text-overflow: ellipsis; white-space: nowrap; }
.activity-card :deep(td small) { display: block; color: #9ba4b2; font-size: 0.56rem; }

.activity-empty { display: flex; min-height: 160px; align-items: center; justify-content: center; flex-direction: column; color: #95a0b1; text-align: center; }
.activity-empty > i { color: #b4bfd0; font-size: 1.8rem; }
.activity-empty strong { margin-top: 0.45rem; color: #617087; font-size: 0.72rem; }
.activity-empty span { max-width: 260px; margin-top: 0.15rem; font-size: 0.6rem; }
.activity-empty a { margin-top: 0.55rem; color: #536ee8; font-size: 0.63rem; font-weight: 700; }

.recent-alerts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.45rem; }
.recent-alert { gap: 0.55rem; padding: 0.62rem; border-radius: 11px; background: #fafbfe; }
.recent-alert > span { width: 7px; height: 7px; flex: 0 0 7px; border-radius: 50%; background: #ee727c; box-shadow: 0 0 0 4px #ffedef; }
.recent-alert > div { display: flex; min-width: 0; flex-direction: column; }
.recent-alert strong { overflow: hidden; color: #334159; font-size: 0.66rem; text-overflow: ellipsis; white-space: nowrap; }
.recent-alert small { color: #99a3b2; font-size: 0.56rem; }

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 1250px) {
  .overview-layout { grid-template-columns: 1fr; }
  .primary-metrics { grid-template-columns: repeat(4, minmax(150px, 1fr)); overflow-x: auto; padding-bottom: 0.25rem; }
  .priority-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 950px) {
  .quick-actions,
  .secondary-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .primary-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); overflow: visible; }
  .chart-grid,
  .activity-grid { grid-template-columns: 1fr; }
  .chart-wide,
  .alerts-card { grid-column: auto; }
}

@media (max-width: 620px) {
  .command-bar { align-items: flex-start; flex-direction: column; }
  .quick-actions,
  .primary-metrics,
  .secondary-strip,
  .priority-list,
  .recent-alerts { grid-template-columns: 1fr; }
  .overview-main,
  .priority-panel,
  .analytics-section,
  .activity-section { padding: 1rem; border-radius: 17px; }
  .primary-metric { min-height: 154px; }
  .section-heading { align-items: flex-start; }
  .section-note { display: none; }
}
</style>
