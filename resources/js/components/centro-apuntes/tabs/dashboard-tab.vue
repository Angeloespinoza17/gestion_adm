<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatCentroApuntesDate,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
} from "../module-utils";

export default {
  components: {
    CentroApuntesHelpButton,
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
      loading: false,
      error: null,
      dashboard: {
        generated_at: null,
        metrics: {},
        alerts: {},
        charts: {
          requests_by_day: { labels: [], series: [] },
          sheets_by_month: { labels: [], series: [] },
          copies_by_machine: [],
          requests_by_subject: [],
          requests_by_user: [],
          supply_consumption: [],
          critical_stock: [],
          deliveries_by_area: [],
        },
        recent: {
          requests: [],
          deliveries: [],
          movements: [],
        },
      },
    };
  },
  computed: {
    metricCards() {
      const metrics = this.dashboard.metrics || {};
      return [
        { label: "Tareas pendientes", value: metrics.pending_tasks || 0, icon: "bx-time-five", tone: "warning" },
        { label: "Tareas en proceso", value: metrics.in_progress_tasks || 0, icon: "bx-loader-circle", tone: "primary" },
        { label: "Listas para retiro", value: metrics.ready_for_pickup || 0, icon: "bx-package", tone: "success" },
        { label: "Entregadas hoy", value: metrics.delivered_today || 0, icon: "bx-check-double", tone: "success" },
        { label: "Solicitudes urgentes", value: metrics.urgent_requests || 0, icon: "bx-bell", tone: "danger" },
        { label: "Hojas del mes", value: metrics.month_sheets || 0, icon: "bx-file", tone: "info" },
        { label: "Copias del mes", value: metrics.month_copies || 0, icon: "bx-copy", tone: "info" },
        { label: "Consumo carta", value: metrics.month_letter_consumption || 0, icon: "bx-news", tone: "secondary" },
        { label: "Consumo oficio", value: metrics.month_officio_consumption || 0, icon: "bx-spreadsheet", tone: "secondary" },
        { label: "Stock crítico", value: metrics.critical_stock || 0, icon: "bx-error-circle", tone: "warning" },
        { label: "Materiales entregados", value: metrics.delivered_materials || 0, icon: "bx-transfer-alt", tone: "primary" },
        { label: "Costo estimado mes", value: `$${Number(metrics.month_estimated_costs || 0).toLocaleString("es-CL")}`, icon: "bx-dollar-circle", tone: "success" },
      ];
    },
    alertCards() {
      const alerts = this.dashboard.alerts || {};
      return [
        { label: "Pendientes", value: alerts.pending_tasks || 0, status: "pendiente", chipLabel: "Pendiente" },
        { label: "Urgentes", value: alerts.urgent_tasks || 0, status: "urgente", chipLabel: "Urgente" },
        { label: "Inmediatas", value: alerts.immediate_deliveries || 0, status: "entrega_inmediata", chipLabel: "Entrega inmediata" },
        { label: "Atrasadas", value: alerts.overdue_tasks || 0, status: "rechazada", chipLabel: "Atrasada" },
        { label: "Stock crítico", value: alerts.critical_stock || 0, status: "stock_bajo", chipLabel: "Stock bajo" },
        { label: "Agotados", value: alerts.out_of_stock || 0, status: "agotado", chipLabel: "Agotado" },
        { label: "Mantención", value: alerts.machines_in_maintenance || 0, status: "en_mantencion", chipLabel: "En mantención" },
        { label: "Listas para retiro", value: alerts.ready_for_pickup || 0, status: "lista_para_retiro", chipLabel: "Lista para retiro" },
        { label: "Próximos a vencer", value: alerts.supplies_expiring || 0, status: "vencido", chipLabel: "Próximo a vencer" },
      ];
    },
    requestsDayChartOptions() {
      return basicApexOptions({
        categories: this.dashboard.charts?.requests_by_day?.labels || [],
        colors: ["#2f7cf6"],
      });
    },
    sheetsMonthChartOptions() {
      return basicApexOptions({
        categories: this.dashboard.charts?.sheets_by_month?.labels || [],
        colors: ["#34c38f"],
      });
    },
    copiesMachineChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.copies_by_machine),
        colors: ["#f1b44c"],
        horizontal: true,
      });
    },
    requestsSubjectChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.requests_by_subject),
        colors: ["#556ee6"],
        horizontal: true,
      });
    },
    requestsUserChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.requests_by_user),
        colors: ["#50a5f1"],
        horizontal: true,
      });
    },
    supplyConsumptionChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.supply_consumption),
        colors: ["#f46a6a"],
        horizontal: true,
      });
    },
    criticalStockChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.dashboard.charts?.critical_stock),
        colors: ["#f1b44c"],
        horizontal: true,
      });
    },
    deliveriesAreaChartOptions() {
      return {
        labels: extractChartLabels(this.dashboard.charts?.deliveries_by_area),
        legend: { position: "bottom" },
        colors: ["#2f7cf6", "#34c38f", "#50a5f1", "#f1b44c", "#f46a6a", "#74788d"],
      };
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    extractChartTotals,
    formatCentroApuntesDate,
    formatCentroApuntesDateTime,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el dashboard del módulo.");
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
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <div class="fw-semibold">Panel operativo del Centro de Apuntes</div>
        <small v-if="dashboard.generated_at" class="text-muted">Actualizado {{ formatCentroApuntesDateTime(dashboard.generated_at) }}</small>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <CentroApuntesHelpButton
          title="Ayuda: dashboard operativo"
          text="Este panel resume solicitudes de impresión, urgencias, stock crítico, consumo de insumos, costos estimados y entregas de materiales para priorizar la operación diaria."
        />
        <BButton variant="primary" :disabled="loading" @click="loadDashboard"><i class="bx bx-refresh me-1" :class="{ 'bx-spin': loading }"></i>Actualizar</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando indicadores del centro de apuntes..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3 col-xxl-2">
          <BCard class="metric-card h-100" :class="`metric-card--${card.tone}`">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="text-muted small">{{ card.label }}</div>
                <div class="display-6 fw-semibold card-value">{{ card.value }}</div>
              </div>
              <div class="metric-icon">
                <i :class="`bx ${card.icon} fs-4`"></i>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="fw-semibold">Alertas del dashboard</div>
            <CentroApuntesHelpButton
              title="Ayuda: alertas del dashboard"
              text="Las alertas operativas muestran las tareas que requieren atención inmediata y los insumos que comprometen la continuidad del servicio."
            />
          </div>
        </template>
        <div class="row g-3">
          <div v-for="item in alertCards" :key="item.label" class="col-md-6 col-xl-4 col-xxl-3">
            <div class="alert-card h-100" :class="{ 'alert-card--active': Number(item.value) > 0 }">
              <div class="d-flex justify-content-between gap-2">
                <div>
                  <div class="small text-muted">{{ item.label }}</div>
                  <div class="h2 mb-0">{{ item.value }}</div>
                </div>
                <CentroApuntesStatusBadge :status="item.status" :label="item.chipLabel" />
              </div>
            </div>
          </div>
        </div>
      </BCard>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes por día</div></template>
            <apexchart type="line" height="300" :options="requestsDayChartOptions" :series="seriesFrom(dashboard.charts?.requests_by_day, 'Solicitudes')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Hojas impresas por mes</div></template>
            <apexchart type="bar" height="300" :options="sheetsMonthChartOptions" :series="seriesFrom(dashboard.charts?.sheets_by_month, 'Hojas')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Copias por máquina</div></template>
            <apexchart type="bar" height="300" :options="copiesMachineChartOptions" :series="seriesFrom(dashboard.charts?.copies_by_machine, 'Copias')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes por asignatura</div></template>
            <apexchart type="bar" height="300" :options="requestsSubjectChartOptions" :series="seriesFrom(dashboard.charts?.requests_by_subject, 'Solicitudes')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes por funcionario</div></template>
            <apexchart type="bar" height="300" :options="requestsUserChartOptions" :series="seriesFrom(dashboard.charts?.requests_by_user, 'Solicitudes')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Consumo de insumos</div></template>
            <apexchart type="bar" height="300" :options="supplyConsumptionChartOptions" :series="seriesFrom(dashboard.charts?.supply_consumption, 'Consumo')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Stock crítico</div></template>
            <apexchart type="bar" height="300" :options="criticalStockChartOptions" :series="seriesFrom(dashboard.charts?.critical_stock, 'Stock actual')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Entregas por área</div></template>
            <apexchart type="donut" height="300" :options="deliveriesAreaChartOptions" :series="extractChartTotals(dashboard.charts?.deliveries_by_area)" />
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes recientes</div></template>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Solicitante</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!(dashboard.recent?.requests || []).length"><td colspan="3" class="empty-table">Sin solicitudes recientes.</td></tr>
                  <tr v-for="item in dashboard.recent?.requests || []" :key="item.id">
                    <td>
                      <div class="fw-semibold">{{ item.request_code }}</div>
                      <div class="text-muted small">{{ formatCentroApuntesDateTime(item.requested_at) }}</div>
                    </td>
                    <td>{{ item.requested_by_name_snapshot }}</td>
                    <td><CentroApuntesStatusBadge :status="item.status" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Entregas recientes</div></template>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Área</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!(dashboard.recent?.deliveries || []).length"><td colspan="3" class="empty-table">Sin entregas recientes.</td></tr>
                  <tr v-for="item in dashboard.recent?.deliveries || []" :key="item.id">
                    <td>
                      <div class="fw-semibold">{{ item.delivery_code }}</div>
                      <div class="text-muted small">{{ formatCentroApuntesDateTime(item.requested_at) }}</div>
                    </td>
                    <td>{{ item.department_name_snapshot || "Sin área" }}</td>
                    <td><CentroApuntesStatusBadge :status="item.status" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Movimientos recientes</div></template>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Insumo</th>
                    <th>Tipo</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!(dashboard.recent?.movements || []).length"><td colspan="3" class="empty-table">Sin movimientos recientes.</td></tr>
                  <tr v-for="item in dashboard.recent?.movements || []" :key="item.id">
                    <td>{{ formatCentroApuntesDateTime(item.moved_at) }}</td>
                    <td>{{ item.insumo?.name }}</td>
                    <td><CentroApuntesStatusBadge :status="item.movement_type" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.card-value {
  font-size: clamp(1.4rem, 2vw, 2rem);
}
.metric-card { overflow: hidden; position: relative; transition: transform .15s ease, box-shadow .15s ease; }
.metric-card :deep(.card-body) { min-height: 7.5rem; padding: 1rem; }
.metric-card::before { background: var(--metric-color, var(--bs-primary)); content: ""; inset: 0 auto 0 0; position: absolute; width: .22rem; }
.metric-card:hover { box-shadow: 0 1rem 2.2rem rgba(90, 110, 150, .13) !important; transform: translateY(-2px); }
.metric-icon { align-items: center; background: color-mix(in srgb, var(--metric-color, var(--bs-primary)) 12%, transparent); border-radius: .65rem; color: var(--metric-color, var(--bs-primary)); display: inline-flex; height: 2.65rem; justify-content: center; width: 2.65rem; }
.metric-card--primary { --metric-color: var(--bs-primary); }
.metric-card--success { --metric-color: var(--bs-success); }
.metric-card--warning { --metric-color: #d99518; }
.metric-card--danger { --metric-color: var(--bs-danger); }
.metric-card--info { --metric-color: var(--bs-info); }
.metric-card--secondary { --metric-color: var(--bs-secondary); }
.alert-card { border: 1px solid var(--bs-border-color); border-radius: .6rem; min-height: 5.25rem; padding: .9rem; transition: border-color .15s ease, background-color .15s ease; }
.alert-card > div { align-items: center; }
.alert-card--active { background: rgba(var(--bs-warning-rgb), .035); border-color: rgba(var(--bs-warning-rgb), .42); }
.empty-table { color: var(--bs-secondary-color); padding: 1.5rem !important; text-align: center; }
</style>
