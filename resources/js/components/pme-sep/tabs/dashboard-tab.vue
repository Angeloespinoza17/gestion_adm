<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatCurrency,
  formatPmeError,
  humanizePmeStatus,
} from "../module-utils";

export default {
  components: { PmeHelpButton },
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
        active_plan: null,
        metrics: {},
        charts: {},
        alerts: [],
      },
    };
  },
  computed: {
    metricCards() {
      const metrics = this.dashboard.metrics || {};
      return [
        { label: "PME activo", value: metrics.pme_active || "-", icon: "bx-briefcase-alt-2", variant: "primary" },
        { label: "Ciclo vigente", value: humanizePmeStatus(metrics.cycle_active), icon: "bx-refresh", variant: "info" },
        { label: "Objetivos", value: metrics.objectives_total || 0, icon: "bx-bullseye", variant: "secondary" },
        { label: "Estrategias", value: metrics.strategies_total || 0, icon: "bx-sitemap", variant: "success" },
        { label: "Indicadores", value: metrics.indicators_total || 0, icon: "bx-line-chart", variant: "warning" },
        { label: "Acciones", value: metrics.actions_total || 0, icon: "bx-task", variant: "dark" },
        { label: "Acciones en ejecución", value: metrics.actions_execution || 0, icon: "bx-play-circle", variant: "primary" },
        { label: "Acciones atrasadas", value: metrics.actions_late || 0, icon: "bx-error-circle", variant: "danger" },
        { label: "Acciones sin evidencia", value: metrics.actions_without_evidence || 0, icon: "bx-folder-open", variant: "warning" },
        { label: "Evidencias aprobadas", value: metrics.evidences_approved || 0, icon: "bx-check-shield", variant: "success" },
        { label: "Ingresos SEP", value: metrics.sep_incomes_registered || 0, icon: "bx-wallet", variant: "info" },
        { label: "Saldo disponible", value: formatCurrency(metrics.budget_available || 0), icon: "bx-coin-stack", variant: "success" },
        { label: "Estudiantes prioritarias", value: metrics.priority_students || 0, icon: "bx-user-pin", variant: "primary" },
        { label: "Estudiantes preferentes", value: metrics.preferential_students || 0, icon: "bx-group", variant: "secondary" },
        { label: "Avance global PME", value: `${metrics.global_progress || 0}%`, icon: "bx-trending-up", variant: "success" },
        { label: "Indicadores críticos", value: metrics.critical_indicators || 0, icon: "bx-pulse", variant: "danger" },
      ];
    },
    progressDimensionOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.progress_by_dimension),
        colors: ["#556ee6"],
      });
    },
    actionsStateOptions() {
      return {
        labels: extractChartLabels(this.dashboard.charts?.actions_by_state),
        legend: { position: "bottom" },
        colors: ["#556ee6", "#34c38f", "#f1b44c", "#f46a6a", "#50a5f1", "#74788d", "#ff7f50", "#343a40"],
      };
    },
    budgetDimensionOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.budget_by_dimension),
        colors: ["#34c38f", "#f46a6a"],
      });
    },
    studentsCourseOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.students_by_course),
        colors: ["#556ee6", "#34c38f"],
      });
    },
    incomesMonthOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.incomes_by_month),
        colors: ["#50a5f1", "#34c38f"],
      });
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    formatCurrency,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/pme-sep/dashboard");
        this.dashboard = response.data;
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
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <div class="d-flex justify-content-end">
      <BButton variant="primary" @click="loadDashboard">Actualizar dashboard</BButton>
    </div>

    <BCard v-if="loading" class="border-0 shadow-sm">
      <div class="text-muted">Cargando dashboard PME / SEP...</div>
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3">
          <BCard class="border-0 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="text-muted small">{{ card.label }}</div>
                <div class="fw-semibold fs-4">{{ card.value }}</div>
              </div>
              <div :class="`avatar-title rounded-circle bg-soft-${card.variant} text-${card.variant}`" style="width: 42px; height: 42px">
                <i :class="`bx ${card.icon} fs-4`"></i>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Alertas del dashboard</div>
                <PmeHelpButton
                  title="Ayuda: alertas del dashboard"
                  text="Estas alertas priorizan acciones atrasadas, evidencias pendientes, indicadores críticos, hitos vencidos, metas sin medición, ingresos sin respaldo y estudiantes pendientes de clasificación SEP."
                />
              </div>
            </template>

            <div class="row g-3">
              <div v-for="alert in dashboard.alerts" :key="alert.type" class="col-md-6 col-xl-4">
                <div class="border rounded p-3 h-100">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                      <div class="fw-semibold">{{ alert.title }}</div>
                      <div class="small text-muted">{{ alert.message }}</div>
                    </div>
                    <BBadge :variant="alert.severity === 'alta' ? 'danger' : alert.severity === 'media' ? 'warning' : 'info'">
                      {{ alert.count }}
                    </BBadge>
                  </div>
                </div>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">Avance PME por dimensión</div>
                <PmeHelpButton title="Ayuda: avance por dimensión" text="Este gráfico resume el avance promedio de las acciones PME agrupadas por dimensión institucional." />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="progressDimensionOptions" :series="seriesFrom(dashboard.charts?.progress_by_dimension, 'Avance %')" />
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">Acciones por estado</div>
                <PmeHelpButton title="Ayuda: acciones por estado" text="Este gráfico permite visualizar la distribución de acciones PME por estado operativo." />
              </div>
            </template>
            <apexchart type="donut" height="300" :options="actionsStateOptions" :series="donutSeries(dashboard.charts?.actions_by_state)" />
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">Presupuesto por dimensión</div>
                <PmeHelpButton title="Ayuda: presupuesto por dimensión" text="Aquí se compara el presupuesto planificado y el presupuesto ejecutado por dimensión PME." />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="budgetDimensionOptions" :series="budgetSeries()" />
          </BCard>
        </div>

        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">Estudiantes SEP por curso</div>
                <PmeHelpButton title="Ayuda: estudiantes SEP por curso" text="Este gráfico muestra cuántas estudiantes prioritarias y preferentes están asociadas a cada curso del año activo." />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="studentsCourseOptions" :series="studentSeries()" />
          </BCard>
        </div>

        <div class="col-12">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">Ingresos SEP por mes</div>
                <PmeHelpButton title="Ayuda: ingresos SEP por mes" text="Este gráfico compara el monto estimado versus el monto realmente recibido de subvención SEP por mes." />
              </div>
            </template>
            <apexchart type="line" height="320" :options="incomesMonthOptions" :series="incomeSeries()" />
          </BCard>
        </div>
      </div>
    </template>
  </div>
</template>
