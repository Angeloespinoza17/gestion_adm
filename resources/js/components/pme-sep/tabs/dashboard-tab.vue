<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatCurrency,
  formatPmeError,
  humanizePmeStatus,
} from "../module-utils";

const compactNumber = new Intl.NumberFormat("es-CL", { notation: "compact", maximumFractionDigits: 1 });
const shortCurrency = (value) => `$${compactNumber.format(Number(value || 0))}`;

export default {
  components: { PmeHelpButton, PmeStatusBadge },
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
      refreshedAt: null,
      dashboard: {
        active_plan: null,
        metrics: {},
        charts: {},
        alerts: [],
      },
    };
  },
  computed: {
    metrics() {
      return this.dashboard.metrics || {};
    },
    primaryMetrics() {
      return [
        {
          label: "Avance global",
          value: `${this.metrics.global_progress || 0}%`,
          helper: "Promedio de acciones",
          icon: "bx-trending-up",
          tone: "primary",
          route: "/pme-sep/acciones",
          progress: this.metrics.global_progress || 0,
        },
        {
          label: "Saldo disponible",
          value: formatCurrency(this.metrics.budget_available || 0),
          helper: `${shortCurrency(this.metrics.budget_executed)} ejecutado`,
          icon: "bx-wallet",
          tone: "success",
          route: "/pme-sep/ingresos",
        },
        {
          label: "Acciones atrasadas",
          value: this.metrics.actions_late || 0,
          helper: `${this.metrics.actions_execution || 0} en ejecución`,
          icon: "bx-error-circle",
          tone: this.metrics.actions_late ? "danger" : "success",
          route: "/pme-sep/acciones",
        },
        {
          label: "Indicadores críticos",
          value: this.metrics.critical_indicators || 0,
          helper: `${this.metrics.strategic_goal_compliance || 0}% cumplimiento`,
          icon: "bx-pulse",
          tone: this.metrics.critical_indicators ? "warning" : "success",
          route: "/pme-sep/indicadores",
        },
      ];
    },
    secondaryMetrics() {
      return [
        { label: "Objetivos", value: this.metrics.objectives_total || 0, icon: "bx-target-lock", route: "/pme-sep/objetivos" },
        { label: "Estrategias", value: this.metrics.strategies_total || 0, icon: "bx-git-branch", route: "/pme-sep/estrategias" },
        { label: "Acciones", value: this.metrics.actions_total || 0, icon: "bx-task", route: "/pme-sep/acciones" },
        { label: "Evidencias aprobadas", value: this.metrics.evidences_approved || 0, icon: "bx-check-shield", route: "/pme-sep/evidencias" },
        { label: "Sin evidencia", value: this.metrics.actions_without_evidence || 0, icon: "bx-folder-open", route: "/pme-sep/evidencias", warning: this.metrics.actions_without_evidence > 0 },
        { label: "Ingresos registrados", value: this.metrics.sep_incomes_registered || 0, icon: "bx-coin-stack", route: "/pme-sep/ingresos" },
        { label: "Prioritarias", value: this.metrics.priority_students || 0, icon: "bx-user-pin", route: "/pme-sep/estudiantes" },
        { label: "Preferentes", value: this.metrics.preferential_students || 0, icon: "bx-group", route: "/pme-sep/estudiantes" },
      ];
    },
    alertCount() {
      return (this.dashboard.alerts || []).reduce((sum, alert) => sum + Number(alert.count || 0), 0);
    },
    budgetExecutionPercentage() {
      const base = Number(this.metrics.sep_budget_estimated || 0);
      return base > 0 ? Math.min(100, Math.round((Number(this.metrics.budget_executed || 0) / base) * 100)) : 0;
    },
    progressDimensionOptions() {
      const options = basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.progress_by_dimension),
        colors: ["#3156a6"],
        horizontal: true,
      });
      return {
        ...options,
        xaxis: { ...options.xaxis, min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } },
        tooltip: { y: { formatter: (value) => `${value}%` } },
        plotOptions: { bar: { ...options.plotOptions.bar, horizontal: true, barHeight: "52%" } },
      };
    },
    actionsStateOptions() {
      return {
        chart: { fontFamily: "inherit" },
        labels: extractChartLabels(this.dashboard.charts?.actions_by_state).map(humanizePmeStatus),
        legend: { position: "bottom", fontSize: "11px", markers: { size: 5 } },
        colors: ["#3156a6", "#16866f", "#d49a35", "#c24654", "#5b8fc9", "#7d8797", "#8a63b8", "#344054"],
        stroke: { colors: ["#fff"], width: 3 },
        dataLabels: { enabled: false },
        plotOptions: {
          pie: {
            donut: {
              size: "68%",
              labels: {
                show: true,
                total: { show: true, label: "Acciones", formatter: () => String(this.metrics.actions_total || 0) },
              },
            },
          },
        },
      };
    },
    budgetDimensionOptions() {
      const options = basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.budget_by_dimension),
        colors: ["#3156a6", "#41a58c"],
      });
      return {
        ...options,
        yaxis: { labels: { formatter: shortCurrency } },
        tooltip: { y: { formatter: formatCurrency } },
      };
    },
    studentsCourseOptions() {
      const options = basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.students_by_course),
        colors: ["#3156a6", "#41a58c"],
      });
      return { ...options, chart: { ...options.chart, stacked: true }, plotOptions: { bar: { ...options.plotOptions.bar, columnWidth: "58%" } } };
    },
    incomesMonthOptions() {
      const months = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
      const options = basicApexOptions({
        categories: (this.dashboard.charts?.incomes_by_month || []).map((item) => months[Number(item.label) - 1] || item.label),
        colors: ["#8ca0c8", "#16866f"],
      });
      return {
        ...options,
        yaxis: { labels: { formatter: shortCurrency } },
        tooltip: { y: { formatter: formatCurrency } },
        markers: { size: 4, strokeWidth: 0 },
        stroke: { curve: "smooth", width: [2, 3], dashArray: [5, 0] },
      };
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    formatCurrency,
    humanizePmeStatus,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/pme-sep/dashboard");
        this.dashboard = response.data;
        this.refreshedAt = new Date();
      } catch (error) {
        this.error = formatPmeError(error, "No se pudo cargar el dashboard PME / SEP.");
      } finally {
        this.loading = false;
      }
    },
    seriesFrom(items, name = "Total", key = "total") {
      return [{ name, data: extractChartTotals(items, key) }];
    },
    budgetSeries() {
      return [
        { name: "Planificado", data: (this.dashboard.charts?.budget_by_dimension || []).map((item) => Number(item.planned || 0)) },
        { name: "Ejecutado", data: (this.dashboard.charts?.budget_by_dimension || []).map((item) => Number(item.executed || 0)) },
      ];
    },
    studentSeries() {
      return [
        { name: "Prioritarias", data: (this.dashboard.charts?.students_by_course || []).map((item) => Number(item.prioritarias || 0)) },
        { name: "Preferentes", data: (this.dashboard.charts?.students_by_course || []).map((item) => Number(item.preferentes || 0)) },
      ];
    },
    incomeSeries() {
      return [
        { name: "Estimado", data: (this.dashboard.charts?.incomes_by_month || []).map((item) => Number(item.estimated || 0)) },
        { name: "Recibido", data: (this.dashboard.charts?.incomes_by_month || []).map((item) => Number(item.received || 0)) },
      ];
    },
    donutSeries(items) {
      return extractChartTotals(items);
    },
    hasChartData(items, keys = ["total"]) {
      return (items || []).some((item) => keys.some((key) => Number(item?.[key] || 0) !== 0));
    },
    severityIcon(severity) {
      return severity === "alta" ? "bx-error" : severity === "media" ? "bx-time-five" : "bx-info-circle";
    },
  },
};
</script>

<template>
  <div class="pme-dashboard">
    <BAlert v-if="error" show variant="danger" class="pme-inline-alert">
      <i class="bx bx-error-circle"></i><span>{{ error }}</span>
      <BButton size="sm" variant="outline-danger" @click="loadDashboard">Reintentar</BButton>
    </BAlert>

    <div class="pme-section-toolbar">
      <div>
        <h2 class="pme-section-toolbar__title">Estado general del plan</h2>
        <div class="pme-section-toolbar__meta">
          <span v-if="refreshedAt">Actualizado {{ refreshedAt.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) }}</span>
          <span v-else>Información consolidada del período activo</span>
        </div>
      </div>
      <BButton variant="outline-primary" :disabled="loading" @click="loadDashboard">
        <span v-if="loading" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-refresh"></i>
        {{ loading ? "Actualizando" : "Actualizar" }}
      </BButton>
    </div>

    <div v-if="loading && !dashboard.active_plan" class="pme-dashboard-loading">
      <span class="spinner-border text-primary"></span><strong>Consolidando el PME activo</strong><span>Esto puede tardar unos segundos.</span>
    </div>

    <div v-else-if="!dashboard.active_plan" class="pme-empty pme-empty--large">
      <i class="bx bx-calendar-x"></i>
      <strong>No hay un PME activo</strong>
      <span>Activa un plan para comenzar a visualizar avance, presupuesto, alertas y cobertura SEP.</span>
      <router-link to="/pme-sep/configuracion" class="btn btn-primary mt-2"><i class="bx bx-cog"></i>Configurar plan</router-link>
    </div>

    <template v-else>
      <section class="pme-plan-strip" aria-label="Plan activo">
        <div class="pme-plan-strip__identity">
          <span class="pme-plan-strip__mark"><i class="bx bx-briefcase-alt-2"></i></span>
          <div><small>Plan activo</small><h2>{{ dashboard.active_plan.name }}</h2><p>Año {{ dashboard.active_plan.school_year }} · Ciclo {{ humanizePmeStatus(metrics.cycle_active) }}</p></div>
        </div>
        <div class="pme-plan-strip__budget">
          <div><span>Ejecución presupuestaria</span><strong>{{ budgetExecutionPercentage }}%</strong></div>
          <div class="pme-progress"><div class="pme-progress__bar" :style="{ width: `${budgetExecutionPercentage}%` }"></div></div>
          <small>{{ formatCurrency(metrics.budget_executed) }} de {{ formatCurrency(metrics.sep_budget_estimated) }}</small>
        </div>
        <PmeStatusBadge :status="dashboard.active_plan.state" />
      </section>

      <section class="pme-primary-metrics" aria-label="Indicadores prioritarios">
        <router-link v-for="card in primaryMetrics" :key="card.label" :to="card.route" class="pme-primary-metric" :class="`pme-primary-metric--${card.tone}`">
          <span class="pme-primary-metric__icon"><i class="bx" :class="card.icon"></i></span>
          <div><span>{{ card.label }}</span><strong>{{ card.value }}</strong><small>{{ card.helper }}</small></div>
          <i class="bx bx-chevron-right pme-primary-metric__arrow"></i>
        </router-link>
      </section>

      <section class="pme-secondary-metrics" aria-label="Resumen operativo">
        <router-link v-for="metric in secondaryMetrics" :key="metric.label" :to="metric.route" class="pme-secondary-metric" :class="{ 'is-warning': metric.warning }">
          <i class="bx" :class="metric.icon"></i><div><strong>{{ metric.value }}</strong><span>{{ metric.label }}</span></div>
        </router-link>
      </section>

      <div class="row g-3">
        <div class="col-12">
          <BCard class="pme-dashboard-card pme-alert-panel">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div><div class="fw-semibold">Prioridades que requieren atención</div><div class="small text-muted">{{ alertCount }} situaciones detectadas por las reglas del plan</div></div>
                <div class="d-flex align-items-center gap-2"><BBadge :variant="alertCount ? 'danger' : 'success'">{{ alertCount }}</BBadge><PmeHelpButton title="Ayuda: alertas del dashboard" text="Las alertas priorizan atrasos, evidencias pendientes, indicadores críticos, hitos vencidos, metas sin medición, ingresos sin respaldo y clasificaciones SEP pendientes." /></div>
              </div>
            </template>
            <div v-if="dashboard.alerts?.length" class="pme-alert-grid">
              <article v-for="alert in dashboard.alerts" :key="alert.type" class="pme-alert-item" :class="`is-${alert.severity || 'baja'}`">
                <span class="pme-alert-item__icon"><i class="bx" :class="severityIcon(alert.severity)"></i></span>
                <div><div class="d-flex justify-content-between gap-2"><strong>{{ alert.title }}</strong><span class="pme-alert-item__count">{{ alert.count }}</span></div><p>{{ alert.message }}</p></div>
              </article>
            </div>
            <div v-else class="pme-empty"><i class="bx bx-check-shield"></i><strong>Sin alertas activas</strong><span>El plan no presenta situaciones críticas según las reglas actuales.</span></div>
          </BCard>
        </div>

        <div class="col-xl-7">
          <BCard class="pme-dashboard-card h-100">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Avance por dimensión</div><div class="small text-muted">Porcentaje promedio de ejecución de acciones</div></div><PmeHelpButton title="Ayuda: avance por dimensión" text="Resume el avance promedio de las acciones agrupadas por dimensión institucional." /></div></template>
            <apexchart v-if="hasChartData(dashboard.charts?.progress_by_dimension)" type="bar" height="320" :options="progressDimensionOptions" :series="seriesFrom(dashboard.charts?.progress_by_dimension, 'Avance')" />
            <div v-else class="pme-empty"><i class="bx bx-bar-chart-alt-2"></i><strong>Sin avance para graficar</strong><span>Registra acciones y actualiza su progreso para poblar este gráfico.</span></div>
          </BCard>
        </div>
        <div class="col-xl-5">
          <BCard class="pme-dashboard-card h-100">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Distribución de acciones</div><div class="small text-muted">Carga operativa por estado</div></div><PmeHelpButton title="Ayuda: acciones por estado" text="Visualiza la distribución de acciones PME por estado operativo." /></div></template>
            <apexchart v-if="hasChartData(dashboard.charts?.actions_by_state)" type="donut" height="320" :options="actionsStateOptions" :series="donutSeries(dashboard.charts?.actions_by_state)" />
            <div v-else class="pme-empty"><i class="bx bx-doughnut-chart"></i><strong>Sin acciones registradas</strong><span>Las acciones del plan aparecerán aquí agrupadas por estado.</span></div>
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="pme-dashboard-card h-100">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Presupuesto por dimensión</div><div class="small text-muted">Planificado versus ejecutado</div></div><PmeHelpButton title="Ayuda: presupuesto por dimensión" text="Compara el presupuesto planificado y ejecutado por dimensión PME." /></div></template>
            <apexchart v-if="hasChartData(dashboard.charts?.budget_by_dimension, ['planned', 'executed'])" type="bar" height="310" :options="budgetDimensionOptions" :series="budgetSeries()" />
            <div v-else class="pme-empty"><i class="bx bx-wallet-alt"></i><strong>Sin presupuesto registrado</strong><span>Agrega montos a las acciones para visualizar su distribución.</span></div>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="pme-dashboard-card h-100">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Cobertura SEP por curso</div><div class="small text-muted">Estudiantes prioritarias y preferentes</div></div><PmeHelpButton title="Ayuda: estudiantes SEP por curso" text="Muestra la distribución de estudiantes prioritarias y preferentes por curso del año activo." /></div></template>
            <apexchart v-if="hasChartData(dashboard.charts?.students_by_course, ['prioritarias', 'preferentes'])" type="bar" height="310" :options="studentsCourseOptions" :series="studentSeries()" />
            <div v-else class="pme-empty"><i class="bx bx-group"></i><strong>Sin clasificación SEP</strong><span>Las clasificaciones vigentes aparecerán agrupadas por curso.</span></div>
          </BCard>
        </div>
        <div class="col-12">
          <BCard class="pme-dashboard-card">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Ingresos SEP acumulados por mes</div><div class="small text-muted">Comparación entre estimación y recepción efectiva</div></div><PmeHelpButton title="Ayuda: ingresos SEP por mes" text="Compara el monto estimado y el monto recibido de subvención SEP en cada mes." /></div></template>
            <apexchart v-if="hasChartData(dashboard.charts?.incomes_by_month, ['estimated', 'received'])" type="line" height="325" :options="incomesMonthOptions" :series="incomeSeries()" />
            <div v-else class="pme-empty"><i class="bx bx-line-chart"></i><strong>Sin ingresos para el período</strong><span>Registra ingresos SEP para habilitar la evolución mensual.</span></div>
          </BCard>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.pme-dashboard{display:grid;gap:1rem}.pme-dashboard-loading{display:grid;place-items:center;align-content:center;gap:.55rem;min-height:360px;border:1px solid var(--pme-border);border-radius:12px;background:#fff;color:var(--pme-muted)}.pme-dashboard-loading strong{color:var(--pme-ink);font-size:.85rem}.pme-dashboard-loading span:last-child{font-size:.68rem}.pme-empty--large{min-height:360px;border:1px solid var(--pme-border);border-radius:12px;background:#fff}.pme-plan-strip{display:grid;grid-template-columns:minmax(260px,1fr) minmax(240px,.55fr) auto;align-items:center;gap:1.25rem;padding:1rem 1.1rem;border-radius:12px;background:linear-gradient(120deg,#203f82,#3156a6 65%,#3f68ba);color:#fff;box-shadow:0 10px 26px rgba(32,63,130,.18)}.pme-plan-strip__identity{display:flex;align-items:center;gap:.8rem;min-width:0}.pme-plan-strip__mark{display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;border-radius:11px;background:rgba(255,255,255,.13);font-size:1.35rem}.pme-plan-strip small,.pme-plan-strip p{color:rgba(255,255,255,.7)}.pme-plan-strip h2{overflow:hidden;margin:.12rem 0;color:#fff;font-size:1rem;text-overflow:ellipsis;white-space:nowrap}.pme-plan-strip p{margin:0;font-size:.66rem}.pme-plan-strip__budget>div:first-child{display:flex;justify-content:space-between;margin-bottom:.38rem;font-size:.68rem}.pme-plan-strip__budget .pme-progress{background:rgba(255,255,255,.2)}.pme-plan-strip__budget .pme-progress__bar{background:#7ee2bd}.pme-plan-strip__budget small{display:block;margin-top:.35rem;font-size:.61rem}.pme-plan-strip :deep(.badge){border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.14)!important;color:#fff!important}.pme-primary-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.pme-primary-metric{position:relative;display:flex;align-items:center;gap:.7rem;min-width:0;padding:.9rem;border:1px solid var(--pme-border);border-radius:11px;background:#fff;color:inherit;box-shadow:0 4px 15px rgba(25,39,70,.04);transition:transform .16s ease,box-shadow .16s ease}.pme-primary-metric:hover{transform:translateY(-2px);box-shadow:0 9px 22px rgba(25,39,70,.08)}.pme-primary-metric__icon{display:grid;place-items:center;flex:0 0 40px;width:40px;height:40px;border-radius:10px;background:#edf3ff;color:#3156a6;font-size:1.2rem}.pme-primary-metric>div{display:grid;min-width:0}.pme-primary-metric span{color:#69768a;font-size:.64rem}.pme-primary-metric strong{overflow:hidden;color:#1d2939;font-size:1.18rem;letter-spacing:-.025em;text-overflow:ellipsis;white-space:nowrap}.pme-primary-metric small{color:#8993a3;font-size:.58rem}.pme-primary-metric__arrow{margin-left:auto;color:#a4afbf}.pme-primary-metric--success .pme-primary-metric__icon{background:#eaf8f4;color:#16866f}.pme-primary-metric--warning .pme-primary-metric__icon{background:#fff5e5;color:#b86d0e}.pme-primary-metric--danger .pme-primary-metric__icon{background:#fff0f2;color:#c24654}.pme-secondary-metrics{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));overflow:hidden;border:1px solid var(--pme-border);border-radius:11px;background:#fff}.pme-secondary-metric{display:flex;align-items:center;gap:.5rem;min-width:0;padding:.7rem;border-right:1px solid #e8edf3;color:#536176}.pme-secondary-metric:last-child{border:0}.pme-secondary-metric>i{color:#6b84b5;font-size:1rem}.pme-secondary-metric div{display:grid}.pme-secondary-metric strong{color:#263348;font-size:.82rem}.pme-secondary-metric span{overflow:hidden;font-size:.57rem;text-overflow:ellipsis;white-space:nowrap}.pme-secondary-metric.is-warning i,.pme-secondary-metric.is-warning strong{color:#bd7010}.pme-alert-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.pme-alert-item{display:grid;grid-template-columns:34px 1fr;gap:.6rem;padding:.7rem;border:1px solid #e4e9f0;border-left:3px solid #7791c2;border-radius:8px;background:#fbfcfe}.pme-alert-item__icon{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;background:#edf3ff;color:#3156a6;font-size:1rem}.pme-alert-item strong{color:#344054;font-size:.69rem}.pme-alert-item p{margin:.2rem 0 0;color:#758195;font-size:.61rem;line-height:1.4}.pme-alert-item__count{display:grid;place-items:center;min-width:23px;height:23px;padding:0 .3rem;border-radius:12px;background:#edf1f6;color:#465267;font-size:.62rem;font-weight:750}.pme-alert-item.is-alta{border-left-color:#c24654}.pme-alert-item.is-alta .pme-alert-item__icon{background:#fff0f2;color:#c24654}.pme-alert-item.is-media{border-left-color:#d49435}.pme-alert-item.is-media .pme-alert-item__icon{background:#fff5e5;color:#b86d0e}.pme-dashboard-card{height:100%}@media(max-width:1199px){.pme-primary-metrics{grid-template-columns:repeat(2,1fr)}.pme-secondary-metrics{grid-template-columns:repeat(4,1fr)}.pme-secondary-metric:nth-child(4){border-right:0}.pme-secondary-metric:nth-child(-n+4){border-bottom:1px solid #e8edf3}.pme-alert-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:767px){.pme-plan-strip{grid-template-columns:1fr}.pme-primary-metrics{grid-template-columns:1fr}.pme-secondary-metrics{grid-template-columns:repeat(2,1fr)}.pme-secondary-metric:nth-child(2n){border-right:0}.pme-secondary-metric:nth-child(-n+6){border-bottom:1px solid #e8edf3}.pme-alert-grid{grid-template-columns:1fr}}@media(max-width:420px){.pme-secondary-metrics{grid-template-columns:1fr}.pme-secondary-metric{border-right:0!important;border-bottom:1px solid #e8edf3!important}.pme-secondary-metric:last-child{border-bottom:0!important}}
</style>
