<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesSectionToolbar from "../section-toolbar.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatCentroApuntesDate,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  humanizeCentroApuntesStatus,
} from "../module-utils";

const emptyDashboard = () => ({
  generated_at: null,
  period: {},
  metrics: {},
  comparison: { summary: {}, deltas: {} },
  queue: {},
  alerts: {},
  charts: {
    production_by_day: { labels: [], printed_sheets: [], requests: [] },
    requests_by_status: [],
    sheets_by_user: [],
    sheets_by_department: [],
    sheets_by_subject: [],
    sheets_by_machine: [],
    sheets_by_paper_size: [],
    supply_consumption: [],
    critical_stock: [],
  },
  priority_requests: [],
  inventory_alerts: [],
  recent: { requests: [], deliveries: [], movements: [] },
  metadata: {},
});

export default {
  components: {
    CentroApuntesHelpButton,
    CentroApuntesSectionToolbar,
    CentroApuntesStatusBadge,
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
      loading: true,
      hasLoaded: false,
      error: null,
      activeRanking: "users",
      dashboard: emptyDashboard(),
    };
  },
  computed: {
    metrics() {
      return this.dashboard.metrics || {};
    },
    metricCards() {
      const deltas = this.dashboard.comparison?.deltas || {};
      return [
        {
          key: "month_printed_sheets",
          label: "Hojas impresas",
          value: this.metrics.month_printed_sheets,
          hint: "Páginas × juegos de copias",
          icon: "bx-printer",
          tone: "primary",
          delta: deltas.month_printed_sheets,
        },
        {
          key: "month_requests",
          label: "Solicitudes del mes",
          value: this.metrics.month_requests,
          hint: "Trabajos ingresados",
          icon: "bx-file",
          tone: "info",
          delta: deltas.month_requests,
        },
        {
          key: "month_delivered",
          label: "Entregadas",
          value: this.metrics.month_delivered,
          hint: "Trabajos finalizados",
          icon: "bx-check-double",
          tone: "success",
          delta: deltas.month_delivered,
        },
        {
          key: "on_time_rate",
          label: "Cumplimiento",
          value: this.metrics.on_time_rate,
          suffix: "%",
          hint: "Dentro de fecha",
          icon: "bx-target-lock",
          tone: "success",
          delta: deltas.on_time_rate,
        },
        {
          key: "open_tasks",
          label: "Carga abierta",
          value: this.metrics.open_tasks,
          hint: "Solicitudes por completar",
          icon: "bx-layer",
          tone: "warning",
        },
        {
          key: "overdue_tasks",
          label: "Atrasadas",
          value: this.metrics.overdue_tasks,
          hint: "Fecha comprometida vencida",
          icon: "bx-error-circle",
          tone: "danger",
        },
        {
          key: "critical_stock",
          label: "Stock crítico",
          value: this.metrics.critical_stock,
          hint: `${this.formatNumber(this.metrics.out_of_stock)} agotados`,
          icon: "bx-package",
          tone: "warning",
        },
        {
          key: "active_machines",
          label: "Máquinas activas",
          value: this.metrics.active_machines,
          hint: `${this.formatNumber(this.metrics.machines_in_maintenance)} en mantención`,
          icon: "bx-devices",
          tone: "secondary",
        },
      ];
    },
    queueCards() {
      const queue = this.dashboard.queue || {};
      return [
        { key: "pendiente", label: "Pendientes", value: queue.pendiente || 0, icon: "bx-time-five", tone: "warning" },
        { key: "recibida", label: "Recibidas", value: queue.recibida || 0, icon: "bx-inbox", tone: "info" },
        { key: "en_proceso", label: "En proceso", value: queue.en_proceso || 0, icon: "bx-loader-circle", tone: "primary" },
        { key: "pausada", label: "Pausadas", value: queue.pausada || 0, icon: "bx-pause-circle", tone: "secondary" },
        { key: "lista_para_retiro", label: "Listas para retiro", value: queue.lista_para_retiro || 0, icon: "bx-package", tone: "success" },
      ];
    },
    alertCards() {
      const alerts = this.dashboard.alerts || {};
      return [
        { key: "overdue_tasks", label: "Atrasadas", value: alerts.overdue_tasks || 0, icon: "bx-calendar-exclamation", tone: "danger" },
        { key: "urgent_tasks", label: "Urgentes", value: alerts.urgent_tasks || 0, icon: "bx-bell", tone: "danger" },
        { key: "immediate_deliveries", label: "Entrega inmediata", value: alerts.immediate_deliveries || 0, icon: "bx-bolt-circle", tone: "warning" },
        { key: "out_of_stock", label: "Insumos agotados", value: alerts.out_of_stock || 0, icon: "bx-box", tone: "danger" },
      ];
    },
    healthState() {
      const alerts = this.dashboard.alerts || {};
      const critical = Number(alerts.overdue_tasks || 0) + Number(alerts.out_of_stock || 0);
      const attention = critical + Number(alerts.urgent_tasks || 0) + Number(alerts.critical_stock || 0);

      if (critical > 0) {
        return {
          label: "Requiere atención",
          description: `${this.formatNumber(critical)} situaciones críticas activas`,
          tone: "danger",
          icon: "bx-error-circle",
        };
      }
      if (attention > 0) {
        return {
          label: "Operación con alertas",
          description: `${this.formatNumber(attention)} elementos por revisar`,
          tone: "warning",
          icon: "bx-bell",
        };
      }

      return {
        label: "Operación al día",
        description: "Sin alertas críticas activas",
        tone: "success",
        icon: "bx-check-shield",
      };
    },
    productionChartOptions() {
      const options = basicApexOptions({
        categories: this.dashboard.charts?.production_by_day?.labels || [],
        colors: ["#556ee6"],
      });
      return {
        ...options,
        chart: { ...options.chart, type: "area", zoom: { enabled: false } },
        fill: {
          type: "gradient",
          gradient: { shadeIntensity: 1, opacityFrom: 0.36, opacityTo: 0.04, stops: [0, 90, 100] },
        },
        stroke: { curve: "smooth", width: 3 },
        yaxis: { labels: { formatter: (value) => this.compactNumber(value) } },
        tooltip: { y: { formatter: (value) => `${this.formatNumber(value)} hojas` } },
      };
    },
    productionSeries() {
      return [{
        name: "Hojas impresas",
        data: this.dashboard.charts?.production_by_day?.printed_sheets || [],
      }];
    },
    statusChartOptions() {
      return {
        labels: extractChartLabels(this.dashboard.charts?.requests_by_status),
        legend: { position: "bottom", fontSize: "12px" },
        colors: ["#f1b44c", "#50a5f1", "#556ee6", "#74788d", "#34c38f", "#2f7cf6", "#f46a6a"],
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: "72%", labels: { show: true, total: { show: true, label: "Solicitudes" } } } } },
        noData: { text: "Sin solicitudes este mes" },
      };
    },
    rankingTabs() {
      return [
        { key: "users", label: "Funcionarios", icon: "bx-user" },
        { key: "departments", label: "Departamentos", icon: "bx-buildings" },
        { key: "subjects", label: "Asignaturas", icon: "bx-book-open" },
        { key: "machines", label: "Máquinas", icon: "bx-devices" },
      ];
    },
    activeRankingItems() {
      const keyMap = {
        users: "sheets_by_user",
        departments: "sheets_by_department",
        subjects: "sheets_by_subject",
        machines: "sheets_by_machine",
      };
      return this.dashboard.charts?.[keyMap[this.activeRanking]] || [];
    },
    activeRankingTitle() {
      return {
        users: "Hojas impresas por funcionario",
        departments: "Hojas impresas por departamento",
        subjects: "Hojas impresas por asignatura",
        machines: "Hojas impresas por máquina",
      }[this.activeRanking];
    },
    rankingChartOptions() {
      const options = basicApexOptions({
        categories: extractChartLabels(this.activeRankingItems),
        colors: ["#50a5f1"],
        horizontal: true,
      });
      return {
        ...options,
        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: "58%" } },
        xaxis: { ...options.xaxis, labels: { formatter: (value) => this.compactNumber(value) } },
        tooltip: { y: { formatter: (value) => `${this.formatNumber(value)} hojas` } },
      };
    },
    rankingSeries() {
      return [{ name: "Hojas impresas", data: extractChartTotals(this.activeRankingItems, "printed_sheets") }];
    },
    supplyChartOptions() {
      const options = basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.supply_consumption),
        colors: ["#f1b44c"],
        horizontal: true,
      });
      return {
        ...options,
        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: "58%" } },
      };
    },
    quickLinks() {
      return [
        {
          label: "Gestionar solicitudes",
          description: "Ingresar, priorizar y entregar trabajos",
          route: "/centro-apuntes/solicitudes",
          icon: "bx-printer",
          tone: "primary",
        },
        {
          label: "Revisar insumos",
          description: "Stock, mínimos y disponibilidad",
          route: "/centro-apuntes/insumos",
          icon: "bx-box",
          tone: "warning",
        },
        {
          label: "Registrar movimiento",
          description: "Entradas, salidas y ajustes de stock",
          route: "/centro-apuntes/movimientos",
          icon: "bx-transfer-alt",
          tone: "info",
        },
        {
          label: "Abrir estadísticas",
          description: "Filtros, gráficos, PDF y Excel",
          route: "/centro-apuntes/reportes",
          icon: "bx-bar-chart-alt-2",
          tone: "success",
        },
      ];
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    extractChartTotals,
    formatCentroApuntesDate,
    formatCentroApuntesDateTime,
    humanizeCentroApuntesStatus,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/dashboard");
        this.dashboard = response.data || emptyDashboard();
        this.hasLoaded = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el resumen del Centro de Apuntes.");
      } finally {
        this.loading = false;
      }
    },
    formatNumber(value, decimals = 0) {
      return Number(value || 0).toLocaleString("es-CL", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      });
    },
    compactNumber(value) {
      return new Intl.NumberFormat("es-CL", {
        notation: Number(value || 0) >= 1000 ? "compact" : "standard",
        maximumFractionDigits: 1,
      }).format(Number(value || 0));
    },
    formatMetric(card) {
      return `${this.formatNumber(card.value, card.suffix === "%" ? 1 : 0)}${card.suffix || ""}`;
    },
    deltaMeta(delta) {
      if (delta === null || delta === undefined) {
        return { label: "Sin base comparable", direction: "neutral" };
      }
      if (Number(delta) === 0) {
        return { label: "Sin variación", direction: "neutral" };
      }

      return {
        label: `${Number(delta) > 0 ? "+" : ""}${this.formatNumber(delta, 1)}%`,
        direction: Number(delta) > 0 ? "up" : "down",
      };
    },
    isPriorityRequest(item) {
      return item.is_overdue || item.is_immediate || item.is_urgent;
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-tab dashboard-view d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar
      title="Resumen operativo"
      description="Producción, cumplimiento, carga de trabajo y continuidad del pañol en una sola vista."
      icon="bx-pulse"
      eyebrow="Centro de control"
    >
      <div class="d-flex gap-2 flex-wrap">
        <CentroApuntesHelpButton
          title="Ayuda: resumen operativo"
          text="Este panel usa hojas físicas impresas —páginas originales por juegos de copias— y muestra solo indicadores internos de operación, sin costos."
        />
        <BButton variant="outline-primary" to="/centro-apuntes/reportes">
          <i class="bx bx-bar-chart-alt-2 me-1"></i>Ver estadísticas
        </BButton>
        <BButton variant="primary" :disabled="loading" @click="loadDashboard">
          <i class="bx bx-refresh me-1" :class="{ 'bx-spin': loading }"></i>Actualizar
        </BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BCard v-if="loading && !hasLoaded">
      <LoadingState message="Preparando el resumen operativo..." compact />
    </BCard>

    <template v-if="hasLoaded">
      <section class="dashboard-hero">
        <div class="dashboard-hero__content">
          <span class="dashboard-hero__eyebrow">
            <i class="bx bx-calendar"></i>
            {{ dashboard.period?.label || "Mes en curso" }}
          </span>
          <h3>Una lectura clara de la operación diaria</h3>
          <p>
            Sigue la producción real de hojas, el cumplimiento de fechas y las alertas que pueden afectar la continuidad del servicio.
          </p>
          <div class="dashboard-hero__meta">
            <span><i class="bx bx-shield-quarter"></i> Informe interno sin costos</span>
            <span v-if="dashboard.generated_at"><i class="bx bx-time"></i> Actualizado {{ formatCentroApuntesDateTime(dashboard.generated_at) }}</span>
          </div>
        </div>
        <div class="dashboard-hero__aside">
          <div class="health-pill" :class="`health-pill--${healthState.tone}`">
            <i class="bx" :class="healthState.icon"></i>
            <div>
              <strong>{{ healthState.label }}</strong>
              <span>{{ healthState.description }}</span>
            </div>
          </div>
          <div class="hero-mini-grid">
            <div>
              <span>Hojas hoy</span>
              <strong>{{ formatNumber(metrics.today_printed_sheets) }}</strong>
            </div>
            <div>
              <span>Promedio por solicitud</span>
              <strong>{{ formatNumber(metrics.average_sheets_per_request, 1) }}</strong>
            </div>
            <div>
              <span>Mediana de entrega</span>
              <strong>{{ formatNumber(metrics.median_turnaround_hours, 1) }} h</strong>
            </div>
          </div>
        </div>
      </section>

      <div class="row g-3">
        <div v-for="card in metricCards" :key="card.key" class="col-sm-6 col-xl-3">
          <article class="metric-card h-100" :class="`metric-card--${card.tone}`">
            <div class="metric-card__top">
              <span class="metric-card__icon"><i class="bx" :class="card.icon"></i></span>
              <span
                v-if="card.delta !== undefined"
                class="metric-delta"
                :class="`metric-delta--${deltaMeta(card.delta).direction}`"
              >
                <i
                  class="bx"
                  :class="deltaMeta(card.delta).direction === 'up'
                    ? 'bx-trending-up'
                    : (deltaMeta(card.delta).direction === 'down' ? 'bx-trending-down' : 'bx-minus')"
                ></i>
                {{ deltaMeta(card.delta).label }}
              </span>
            </div>
            <span class="metric-card__label">{{ card.label }}</span>
            <strong>{{ formatMetric(card) }}</strong>
            <small>{{ card.hint }}</small>
          </article>
        </div>
      </div>

      <BCard class="workflow-card">
        <template #header>
          <div class="section-heading">
            <div>
              <span class="section-kicker">Flujo actual</span>
              <strong>Carga de solicitudes por etapa</strong>
            </div>
            <span class="section-total">{{ formatNumber(metrics.open_tasks) }} abiertas</span>
          </div>
        </template>
        <div class="workflow-grid">
          <div v-for="(item, index) in queueCards" :key="item.key" class="workflow-step" :class="`workflow-step--${item.tone}`">
            <div class="workflow-step__icon"><i class="bx" :class="item.icon"></i></div>
            <div>
              <span>{{ item.label }}</span>
              <strong>{{ formatNumber(item.value) }}</strong>
            </div>
            <i v-if="index < queueCards.length - 1" class="bx bx-chevron-right workflow-step__arrow"></i>
          </div>
        </div>
      </BCard>

      <div class="row g-3">
        <div class="col-xl-8">
          <BCard class="chart-card h-100">
            <template #header>
              <div class="section-heading">
                <div>
                  <span class="section-kicker">Producción mensual</span>
                  <strong>Evolución de hojas impresas</strong>
                </div>
                <span class="chart-caption">Páginas originales × juegos de copias</span>
              </div>
            </template>
            <apexchart type="area" height="330" :options="productionChartOptions" :series="productionSeries" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="chart-card h-100">
            <template #header>
              <div>
                <span class="section-kicker">Flujo mensual</span>
                <div class="fw-semibold">Solicitudes por estado</div>
              </div>
            </template>
            <apexchart
              type="donut"
              height="330"
              :options="statusChartOptions"
              :series="extractChartTotals(dashboard.charts?.requests_by_status)"
            />
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-8">
          <BCard class="chart-card h-100">
            <template #header>
              <div class="ranking-header">
                <div>
                  <span class="section-kicker">Distribución de producción</span>
                  <div class="fw-semibold">{{ activeRankingTitle }}</div>
                </div>
                <div class="ranking-tabs" role="tablist" aria-label="Desglose de hojas impresas">
                  <button
                    v-for="tab in rankingTabs"
                    :key="tab.key"
                    type="button"
                    :class="{ active: activeRanking === tab.key }"
                    @click="activeRanking = tab.key"
                  >
                    <i class="bx" :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                  </button>
                </div>
              </div>
            </template>
            <apexchart type="bar" height="350" :options="rankingChartOptions" :series="rankingSeries" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="alerts-panel h-100">
            <template #header>
              <div>
                <span class="section-kicker">Atención operativa</span>
                <div class="fw-semibold">Alertas activas</div>
              </div>
            </template>
            <div class="alert-list">
              <div v-for="item in alertCards" :key="item.key" class="alert-list__item" :class="`alert-list__item--${item.tone}`">
                <span class="alert-list__icon"><i class="bx" :class="item.icon"></i></span>
                <div>
                  <strong>{{ item.label }}</strong>
                  <span>{{ Number(item.value) > 0 ? "Requiere revisión" : "Sin casos activos" }}</span>
                </div>
                <b>{{ formatNumber(item.value) }}</b>
              </div>
            </div>
            <BButton variant="outline-primary" to="/centro-apuntes/solicitudes" class="w-100 mt-3">
              Revisar solicitudes
            </BButton>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-8">
          <BCard class="table-card h-100">
            <template #header>
              <div class="section-heading">
                <div>
                  <span class="section-kicker">Prioridad de atención</span>
                  <strong>Solicitudes que requieren seguimiento</strong>
                </div>
                <BButton size="sm" variant="outline-primary" to="/centro-apuntes/solicitudes">Ver todas</BButton>
              </div>
            </template>
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Solicitud</th>
                    <th>Funcionario / departamento</th>
                    <th>Asignatura</th>
                    <th>Hojas</th>
                    <th>Entrega</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!(dashboard.priority_requests || []).length">
                    <td colspan="6" class="empty-table">
                      <i class="bx bx-check-circle"></i>
                      No hay solicitudes abiertas que requieran seguimiento.
                    </td>
                  </tr>
                  <tr
                    v-for="item in dashboard.priority_requests || []"
                    :key="item.id"
                    :class="{ 'priority-row': isPriorityRequest(item) }"
                  >
                    <td>
                      <div class="fw-semibold">{{ item.request_code }}</div>
                      <div v-if="item.is_overdue" class="priority-label priority-label--danger">Atrasada</div>
                      <div v-else-if="item.is_immediate" class="priority-label priority-label--warning">Inmediata</div>
                      <div v-else-if="item.is_urgent" class="priority-label priority-label--danger">Urgente</div>
                    </td>
                    <td>
                      <div>{{ item.requested_by_name }}</div>
                      <small class="text-muted">{{ item.department_name }}</small>
                    </td>
                    <td>{{ item.subject_name }}</td>
                    <td class="fw-semibold">{{ formatNumber(item.printed_sheets) }}</td>
                    <td :class="{ 'text-danger fw-semibold': item.is_overdue }">{{ formatCentroApuntesDate(item.delivery_date) }}</td>
                    <td><CentroApuntesStatusBadge :status="item.status" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="table-card h-100">
            <template #header>
              <div class="section-heading">
                <div>
                  <span class="section-kicker">Continuidad del pañol</span>
                  <strong>Insumos por revisar</strong>
                </div>
                <BButton size="sm" variant="outline-warning" to="/centro-apuntes/insumos">Abrir pañol</BButton>
              </div>
            </template>
            <div class="inventory-list">
              <div v-if="!(dashboard.inventory_alerts || []).length" class="empty-panel">
                <i class="bx bx-check-shield"></i>
                <strong>Stock bajo control</strong>
                <span>No hay insumos críticos ni próximos a vencer.</span>
              </div>
              <div v-for="item in dashboard.inventory_alerts || []" :key="item.id" class="inventory-item">
                <span class="inventory-item__status" :class="`inventory-item__status--${item.alert_status}`"></span>
                <div>
                  <strong>{{ item.name }}</strong>
                  <span>{{ item.category }} · mínimo {{ formatNumber(item.minimum_stock, 1) }} {{ item.unit.toLowerCase() }}</span>
                </div>
                <div class="inventory-item__value">
                  <b>{{ formatNumber(item.current_stock, 1) }}</b>
                  <CentroApuntesStatusBadge :status="item.alert_status" />
                </div>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="quick-links-card">
        <template #header>
          <div>
            <span class="section-kicker">Accesos rápidos</span>
            <div class="fw-semibold">Continúa con la gestión</div>
          </div>
        </template>
        <div class="quick-links-grid">
          <router-link
            v-for="item in quickLinks"
            :key="item.route"
            :to="item.route"
            class="quick-link"
            :class="`quick-link--${item.tone}`"
          >
            <span><i class="bx" :class="item.icon"></i></span>
            <div>
              <strong>{{ item.label }}</strong>
              <small>{{ item.description }}</small>
            </div>
            <i class="bx bx-right-arrow-alt"></i>
          </router-link>
        </div>
      </BCard>
    </template>
  </div>
</template>

<style scoped>
.dashboard-view { --dashboard-radius: .9rem; }
.dashboard-hero {
  align-items: stretch;
  background:
    radial-gradient(circle at 92% 5%, rgba(255, 255, 255, .2), transparent 26%),
    linear-gradient(135deg, #405189 0%, #556ee6 52%, #3b82f6 100%);
  border-radius: 1rem;
  box-shadow: 0 1.2rem 2.8rem rgba(64, 81, 137, .2);
  color: #fff;
  display: grid;
  gap: 1.4rem;
  grid-template-columns: minmax(0, 1.45fr) minmax(20rem, .8fr);
  overflow: hidden;
  padding: 1.55rem;
  position: relative;
}
.dashboard-hero::after {
  border: 1px solid rgba(255, 255, 255, .12);
  border-radius: 50%;
  content: "";
  height: 17rem;
  position: absolute;
  right: -6rem;
  top: -8rem;
  width: 17rem;
}
.dashboard-hero__content { position: relative; z-index: 1; }
.dashboard-hero__eyebrow {
  align-items: center;
  background: rgba(255, 255, 255, .14);
  border: 1px solid rgba(255, 255, 255, .16);
  border-radius: 999px;
  display: inline-flex;
  font-size: .7rem;
  font-weight: 750;
  gap: .35rem;
  letter-spacing: .045em;
  padding: .32rem .62rem;
  text-transform: uppercase;
}
.dashboard-hero h3 { color: #fff; font-size: clamp(1.35rem, 2.3vw, 2rem); letter-spacing: -.025em; margin: .9rem 0 .45rem; }
.dashboard-hero p { color: rgba(255, 255, 255, .8); font-size: .88rem; line-height: 1.55; margin: 0; max-width: 44rem; }
.dashboard-hero__meta { display: flex; flex-wrap: wrap; font-size: .7rem; gap: .8rem 1.2rem; margin-top: 1rem; }
.dashboard-hero__meta span { align-items: center; color: rgba(255, 255, 255, .74); display: flex; gap: .32rem; }
.dashboard-hero__aside {
  background: rgba(255, 255, 255, .1);
  border: 1px solid rgba(255, 255, 255, .15);
  border-radius: .85rem;
  display: flex;
  flex-direction: column;
  gap: .85rem;
  padding: .85rem;
  position: relative;
  z-index: 1;
}
.health-pill { align-items: center; background: rgba(255, 255, 255, .94); border-radius: .7rem; color: #334155; display: flex; gap: .65rem; padding: .7rem .8rem; }
.health-pill > i { font-size: 1.55rem; }
.health-pill div { display: flex; flex-direction: column; min-width: 0; }
.health-pill strong { font-size: .78rem; }
.health-pill span { color: #64748b; font-size: .65rem; }
.health-pill--success > i { color: #16a34a; }
.health-pill--warning > i { color: #d99518; }
.health-pill--danger > i { color: #dc2626; }
.hero-mini-grid { display: grid; gap: .5rem; grid-template-columns: repeat(3, 1fr); }
.hero-mini-grid > div { background: rgba(15, 23, 42, .12); border-radius: .62rem; display: flex; flex-direction: column; min-width: 0; padding: .62rem; }
.hero-mini-grid span { color: rgba(255, 255, 255, .7); font-size: .58rem; line-height: 1.25; min-height: 1.5rem; text-transform: uppercase; }
.hero-mini-grid strong { color: #fff; font-size: 1rem; margin-top: .2rem; }
.metric-card {
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: var(--dashboard-radius);
  box-shadow: 0 .55rem 1.6rem rgba(90, 110, 150, .055);
  display: flex;
  flex-direction: column;
  min-height: 10rem;
  overflow: hidden;
  padding: 1rem;
  position: relative;
  transition: transform .15s ease, box-shadow .15s ease;
}
.metric-card::before { background: var(--metric-color, var(--bs-primary)); content: ""; height: .23rem; inset: 0 0 auto; position: absolute; }
.metric-card:hover { box-shadow: 0 1rem 2.2rem rgba(90, 110, 150, .12); transform: translateY(-2px); }
.metric-card__top { align-items: center; display: flex; justify-content: space-between; margin-bottom: .8rem; }
.metric-card__icon { align-items: center; background: color-mix(in srgb, var(--metric-color) 12%, transparent); border-radius: .65rem; color: var(--metric-color); display: inline-flex; font-size: 1.25rem; height: 2.4rem; justify-content: center; width: 2.4rem; }
.metric-card__label { color: var(--bs-secondary-color); font-size: .72rem; font-weight: 650; }
.metric-card > strong { color: var(--bs-heading-color); font-size: clamp(1.55rem, 2vw, 2.05rem); letter-spacing: -.03em; line-height: 1.05; margin-top: .3rem; }
.metric-card > small { color: var(--bs-secondary-color); font-size: .65rem; margin-top: auto; padding-top: .5rem; }
.metric-card--primary { --metric-color: var(--bs-primary); }
.metric-card--info { --metric-color: var(--bs-info); }
.metric-card--success { --metric-color: var(--bs-success); }
.metric-card--warning { --metric-color: #d99518; }
.metric-card--danger { --metric-color: var(--bs-danger); }
.metric-card--secondary { --metric-color: var(--bs-secondary); }
.metric-delta { align-items: center; border-radius: 999px; display: inline-flex; font-size: .58rem; font-weight: 750; gap: .18rem; padding: .22rem .4rem; }
.metric-delta--up { background: rgba(var(--bs-success-rgb), .1); color: var(--bs-success); }
.metric-delta--down { background: rgba(var(--bs-danger-rgb), .1); color: var(--bs-danger); }
.metric-delta--neutral { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); }
.section-heading, .ranking-header { align-items: center; display: flex; gap: .75rem; justify-content: space-between; }
.section-heading > div, .ranking-header > div:first-child { display: flex; flex-direction: column; }
.section-kicker { color: var(--bs-primary); font-size: .6rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
.section-total, .chart-caption { background: var(--bs-tertiary-bg); border-radius: 999px; color: var(--bs-secondary-color); font-size: .64rem; font-weight: 700; padding: .3rem .58rem; }
.workflow-grid { display: grid; gap: .6rem; grid-template-columns: repeat(5, 1fr); }
.workflow-step {
  align-items: center;
  background: color-mix(in srgb, var(--workflow-color) 5%, var(--bs-body-bg));
  border: 1px solid color-mix(in srgb, var(--workflow-color) 20%, var(--bs-border-color));
  border-radius: .75rem;
  display: flex;
  gap: .65rem;
  min-width: 0;
  padding: .75rem;
  position: relative;
}
.workflow-step__icon { align-items: center; background: color-mix(in srgb, var(--workflow-color) 12%, transparent); border-radius: .58rem; color: var(--workflow-color); display: flex; flex: 0 0 auto; font-size: 1.15rem; height: 2.2rem; justify-content: center; width: 2.2rem; }
.workflow-step > div:nth-child(2) { display: flex; flex-direction: column; min-width: 0; }
.workflow-step span { color: var(--bs-secondary-color); font-size: .62rem; line-height: 1.2; }
.workflow-step strong { color: var(--bs-heading-color); font-size: 1.15rem; }
.workflow-step__arrow { color: var(--bs-secondary-color); font-size: 1.1rem; position: absolute; right: -.78rem; z-index: 2; }
.workflow-step--primary { --workflow-color: var(--bs-primary); }
.workflow-step--info { --workflow-color: var(--bs-info); }
.workflow-step--success { --workflow-color: var(--bs-success); }
.workflow-step--warning { --workflow-color: #d99518; }
.workflow-step--secondary { --workflow-color: var(--bs-secondary); }
.chart-card :deep(.card-body) { padding-top: .7rem; }
.ranking-tabs { align-items: center; background: var(--bs-tertiary-bg); border-radius: .58rem; display: flex; gap: .2rem; padding: .2rem; }
.ranking-tabs button { align-items: center; background: transparent; border: 0; border-radius: .45rem; color: var(--bs-secondary-color); display: inline-flex; font-size: .62rem; font-weight: 700; gap: .25rem; padding: .38rem .5rem; }
.ranking-tabs button.active { background: var(--bs-body-bg); box-shadow: 0 .2rem .55rem rgba(15, 23, 42, .08); color: var(--bs-primary); }
.alert-list { display: flex; flex-direction: column; gap: .6rem; }
.alert-list__item { align-items: center; background: color-mix(in srgb, var(--alert-color) 4%, var(--bs-body-bg)); border: 1px solid color-mix(in srgb, var(--alert-color) 18%, var(--bs-border-color)); border-radius: .72rem; display: grid; gap: .6rem; grid-template-columns: auto 1fr auto; padding: .7rem; }
.alert-list__icon { align-items: center; background: color-mix(in srgb, var(--alert-color) 12%, transparent); border-radius: .55rem; color: var(--alert-color); display: flex; font-size: 1.1rem; height: 2.15rem; justify-content: center; width: 2.15rem; }
.alert-list__item > div { display: flex; flex-direction: column; }
.alert-list__item strong { color: var(--bs-heading-color); font-size: .7rem; }
.alert-list__item span:not(.alert-list__icon) { color: var(--bs-secondary-color); font-size: .6rem; }
.alert-list__item > b { color: var(--alert-color); font-size: 1.1rem; }
.alert-list__item--danger { --alert-color: var(--bs-danger); }
.alert-list__item--warning { --alert-color: #d99518; }
.table-card :deep(.card-body) { padding: 0; }
.priority-row { background: rgba(var(--bs-warning-rgb), .025); }
.priority-label { border-radius: 999px; display: inline-flex; font-size: .55rem; font-weight: 800; margin-top: .2rem; padding: .14rem .35rem; text-transform: uppercase; }
.priority-label--danger { background: rgba(var(--bs-danger-rgb), .1); color: var(--bs-danger); }
.priority-label--warning { background: rgba(var(--bs-warning-rgb), .12); color: #a66700; }
.empty-table { color: var(--bs-secondary-color); padding: 2.2rem !important; text-align: center; }
.empty-table i { color: var(--bs-success); font-size: 1.1rem; margin-right: .3rem; }
.inventory-list { display: flex; flex-direction: column; }
.inventory-item { align-items: center; border-bottom: 1px solid var(--bs-border-color); display: grid; gap: .55rem; grid-template-columns: auto 1fr auto; padding: .78rem 1rem; }
.inventory-item:last-child { border-bottom: 0; }
.inventory-item__status { background: var(--inventory-color); border-radius: 999px; height: 2.2rem; width: .22rem; }
.inventory-item__status--agotado { --inventory-color: var(--bs-danger); }
.inventory-item__status--stock_bajo { --inventory-color: #d99518; }
.inventory-item__status--proximo_a_vencer { --inventory-color: var(--bs-info); }
.inventory-item > div:nth-child(2) { display: flex; flex-direction: column; min-width: 0; }
.inventory-item strong { color: var(--bs-heading-color); font-size: .7rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inventory-item span { color: var(--bs-secondary-color); font-size: .58rem; }
.inventory-item__value { align-items: flex-end; display: flex; flex-direction: column; gap: .18rem; }
.inventory-item__value b { color: var(--bs-heading-color); font-size: .8rem; }
.empty-panel { align-items: center; color: var(--bs-secondary-color); display: flex; flex-direction: column; min-height: 16rem; justify-content: center; padding: 2rem; text-align: center; }
.empty-panel i { color: var(--bs-success); font-size: 2rem; }
.empty-panel strong { color: var(--bs-heading-color); font-size: .78rem; margin-top: .5rem; }
.empty-panel span { font-size: .64rem; margin-top: .2rem; }
.quick-links-grid { display: grid; gap: .7rem; grid-template-columns: repeat(4, 1fr); }
.quick-link { align-items: center; border: 1px solid var(--bs-border-color); border-radius: .75rem; color: inherit; display: grid; gap: .65rem; grid-template-columns: auto 1fr auto; padding: .8rem; transition: border-color .15s ease, transform .15s ease; }
.quick-link:hover { border-color: color-mix(in srgb, var(--quick-color) 48%, var(--bs-border-color)); color: inherit; transform: translateY(-2px); }
.quick-link > span { align-items: center; background: color-mix(in srgb, var(--quick-color) 11%, transparent); border-radius: .58rem; color: var(--quick-color); display: flex; font-size: 1.15rem; height: 2.35rem; justify-content: center; width: 2.35rem; }
.quick-link > div { display: flex; flex-direction: column; min-width: 0; }
.quick-link strong { color: var(--bs-heading-color); font-size: .7rem; }
.quick-link small { color: var(--bs-secondary-color); font-size: .58rem; }
.quick-link > i { color: var(--bs-secondary-color); }
.quick-link--primary { --quick-color: var(--bs-primary); }
.quick-link--warning { --quick-color: #d99518; }
.quick-link--info { --quick-color: var(--bs-info); }
.quick-link--success { --quick-color: var(--bs-success); }
@media (max-width: 1199.98px) {
  .dashboard-hero { grid-template-columns: 1fr; }
  .workflow-grid { grid-template-columns: repeat(3, 1fr); }
  .workflow-step__arrow { display: none; }
  .quick-links-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767.98px) {
  .dashboard-hero { padding: 1.1rem; }
  .hero-mini-grid { grid-template-columns: 1fr; }
  .hero-mini-grid span { min-height: auto; }
  .workflow-grid, .quick-links-grid { grid-template-columns: 1fr; }
  .ranking-header, .section-heading { align-items: stretch; flex-direction: column; }
  .ranking-tabs { display: grid; grid-template-columns: repeat(2, 1fr); }
  .ranking-tabs button { justify-content: center; }
  .chart-caption, .section-total { align-self: flex-start; }
}
</style>
