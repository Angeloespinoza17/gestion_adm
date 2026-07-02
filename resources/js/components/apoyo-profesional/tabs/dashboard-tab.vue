<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatSupportDateTime,
  formatSupportError,
} from "../module-utils";

export default {
  components: {
    LoadingState,
    SupportHelpButton,
    SupportStatusBadge,
  },
  data() {
    return {
      loading: false,
      error: null,
      dashboard: {
        metrics: {},
        alerts: {},
        charts: {
          attentions_by_month: { labels: [], series: [] },
          attentions_by_professional: [],
          attentions_by_course: [],
          frequent_motives: [],
          pending_follow_ups: [],
          derivations_by_area: [],
          open_vs_closed_cases: [],
        },
        breakdowns: {
          attentions_by_professional_area: [],
          attentions_by_role: [],
        },
        recent: {
          attentions: [],
          derivations: [],
          follow_ups: [],
        },
      },
    };
  },
  computed: {
    metricCards() {
      const metrics = this.dashboard.metrics || {};
      return [
        { label: "Atenciones hoy", value: metrics.attentions_today || 0, icon: "bx-calendar-check", variant: "primary" },
        { label: "Atenciones del mes", value: metrics.attentions_month || 0, icon: "bx-calendar-event", variant: "info" },
        { label: "Atenciones abiertas", value: metrics.open_attentions || 0, icon: "bx-folder-open", variant: "warning" },
        { label: "Atenciones cerradas", value: metrics.closed_attentions || 0, icon: "bx-check-circle", variant: "success" },
        { label: "Seguimientos pendientes", value: metrics.pending_follow_ups || 0, icon: "bx-time-five", variant: "warning" },
        { label: "Casos activos", value: metrics.active_cases || 0, icon: "bx-briefcase", variant: "primary" },
        { label: "Derivaciones pendientes", value: metrics.pending_derivations || 0, icon: "bx-transfer", variant: "danger" },
        { label: "Casos confidenciales", value: metrics.confidential_attentions || 0, icon: "bx-lock-alt", variant: "dark" },
      ];
    },
    attentionChartOptions() {
      return {
        ...basicApexOptions({
          categories: this.dashboard.charts?.attentions_by_month?.labels || [],
          colors: ["#556ee6"],
        }),
      };
    },
    professionalChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.attentions_by_professional),
          horizontal: true,
          colors: ["#34c38f"],
        }),
      };
    },
    courseChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.attentions_by_course),
          colors: ["#50a5f1"],
        }),
      };
    },
    motiveChartOptions() {
      return {
        labels: extractChartLabels(this.dashboard.charts?.frequent_motives),
        legend: { position: "bottom" },
        dataLabels: { enabled: true },
        colors: ["#556ee6", "#34c38f", "#f1b44c", "#f46a6a", "#50a5f1", "#74788d", "#8e44ad", "#ff7f50"],
      };
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    formatSupportDateTime,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar el dashboard.");
      } finally {
        this.loading = false;
      }
    },
    barSeries(items, name = "Total") {
      return [{ name, data: extractChartTotals(items) }];
    },
    lineSeries() {
      return [{ name: "Atenciones", data: this.dashboard.charts?.attentions_by_month?.series || [] }];
    },
    donutSeries(items) {
      return extractChartTotals(items);
    },
  },
};
</script>

<template>
  <div>
    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando indicadores del equipo de apoyo..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3 mb-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3">
          <BCard class="border-0 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="text-muted small">{{ card.label }}</div>
                <div class="display-6 fw-semibold">{{ card.value }}</div>
              </div>
              <div :class="`avatar-title rounded-circle bg-soft-${card.variant} text-${card.variant}`" style="width: 42px; height: 42px">
                <i :class="`bx ${card.icon} fs-4`"></i>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="mb-3">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="fw-semibold">Alertas del dashboard</div>
            <SupportHelpButton
              title="Ayuda: alertas del dashboard"
              text="Estas tarjetas permiten priorizar atenciones abiertas, seguimientos vencidos, derivaciones pendientes, casos urgentes, planes activos y casos confidenciales."
            />
          </div>
        </template>
        <div class="row g-3">
          <div class="col-md-3">
            <div class="border rounded p-3">
              <div class="small text-muted">Seguimientos vencidos</div>
              <div class="display-6 fw-semibold">{{ dashboard.alerts?.overdue_follow_ups || 0 }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3">
              <div class="small text-muted">Casos urgentes</div>
              <div class="display-6 fw-semibold">{{ dashboard.alerts?.urgent_cases || 0 }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3">
              <div class="small text-muted">Planes activos</div>
              <div class="display-6 fw-semibold">{{ dashboard.alerts?.active_plans || 0 }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3">
              <div class="small text-muted">Entrevistas próximas</div>
              <div class="display-6 fw-semibold">{{ dashboard.alerts?.upcoming_interviews || 0 }}</div>
            </div>
          </div>
        </div>
      </BCard>

      <div class="row g-3 mb-3">
        <div class="col-xl-8">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Atenciones por mes</div>
                <SupportHelpButton
                  title="Ayuda: atenciones por mes"
                  text="Este gráfico muestra la evolución mensual de atenciones del equipo de apoyo durante el último año."
                />
              </div>
            </template>
            <apexchart type="bar" height="320" :options="attentionChartOptions" :series="lineSeries()" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Motivos frecuentes</div>
                <SupportHelpButton
                  title="Ayuda: motivos frecuentes"
                  text="Aquí se visualizan los motivos de atención más recurrentes del período."
                />
              </div>
            </template>
            <apexchart type="donut" height="320" :options="motiveChartOptions" :series="donutSeries(dashboard.charts?.frequent_motives)" />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Atenciones por profesional</div></template>
            <apexchart type="bar" height="320" :options="professionalChartOptions" :series="barSeries(dashboard.charts?.attentions_by_professional, 'Atenciones')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Atenciones por curso</div></template>
            <apexchart type="bar" height="320" :options="courseChartOptions" :series="barSeries(dashboard.charts?.attentions_by_course, 'Atenciones')" />
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Atenciones recientes</div></template>
            <div class="d-grid gap-2">
              <div v-for="item in dashboard.recent?.attentions || []" :key="item.id" class="border rounded p-2">
                <div class="d-flex justify-content-between gap-2 align-items-center">
                  <div class="fw-semibold">{{ item.student?.first_name }} {{ item.student?.last_name }}</div>
                  <SupportStatusBadge :status="item.status" />
                </div>
                <div class="small">{{ item.reason_summary }}</div>
                <div class="small text-muted">{{ formatSupportDateTime(item.attended_at) }}</div>
              </div>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Derivaciones recientes</div></template>
            <div class="d-grid gap-2">
              <div v-for="item in dashboard.recent?.derivations || []" :key="item.id" class="border rounded p-2">
                <div class="d-flex justify-content-between gap-2 align-items-center">
                  <div class="fw-semibold">{{ item.student?.first_name }} {{ item.student?.last_name }}</div>
                  <SupportStatusBadge :status="item.status" />
                </div>
                <div class="small">{{ item.destination_area_name }}</div>
                <div class="small text-muted">{{ formatSupportDateTime(item.derived_at) }}</div>
              </div>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Seguimientos recientes</div></template>
            <div class="d-grid gap-2">
              <div v-for="item in dashboard.recent?.follow_ups || []" :key="item.id" class="border rounded p-2">
                <div class="d-flex justify-content-between gap-2 align-items-center">
                  <div class="fw-semibold">Seguimiento #{{ item.id }}</div>
                  <SupportStatusBadge :status="item.status" />
                </div>
                <div class="small">{{ item.comment }}</div>
                <div class="small text-muted">{{ formatSupportDateTime(item.scheduled_at) }}</div>
              </div>
            </div>
          </BCard>
        </div>
      </div>
    </template>
  </div>
</template>
