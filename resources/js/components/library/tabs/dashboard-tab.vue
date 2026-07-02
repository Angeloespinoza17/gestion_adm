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
    metricCards() {
      const metrics = this.dashboard.metrics || {};
      return [
        { label: "Libros registrados", value: metrics.total_books || 0, icon: "bx-book" },
        { label: "Ejemplares disponibles", value: metrics.total_copies_available || 0, icon: "bx-layer" },
        { label: "Ejemplares prestados", value: metrics.copies_loaned || 0, icon: "bx-transfer" },
        { label: "Préstamos activos", value: metrics.active_loans || 0, icon: "bx-calendar-check" },
        { label: "Devoluciones pendientes", value: metrics.pending_returns || 0, icon: "bx-time" },
        { label: "Estudiantes con mora", value: metrics.students_overdue || 0, icon: "bx-user-x" },
        { label: "Funcionarios con mora", value: metrics.staff_overdue || 0, icon: "bx-id-card" },
        { label: "Reservas activas", value: metrics.active_reservations || 0, icon: "bx-bookmark" },
        { label: "Recursos reservados", value: metrics.reserved_resources || 0, icon: "bx-devices" },
        { label: "Espacios reservados", value: metrics.reserved_spaces || 0, icon: "bx-building-house" },
        { label: "Plan lector activo", value: metrics.reading_plan_activities || 0, icon: "bx-notepad" },
        { label: "Préstamos del mes", value: metrics.month_loans || 0, icon: "bx-trending-up" },
      ];
    },
    alertCards() {
      const alerts = this.dashboard.alerts || {};
      return [
        { label: "Préstamos vencidos", value: alerts.overdue_loans || 0, status: "vencido" },
        { label: "Reservas pendientes", value: alerts.pending_reservations || 0, status: "solicitada" },
        { label: "Próximas devoluciones", value: alerts.upcoming_returns || 0, status: "renovado" },
        { label: "Usuarios con mora", value: alerts.users_with_overdue || 0, status: "vencido" },
        { label: "Recursos reservados hoy", value: alerts.resources_reserved_today || 0, status: "reservado" },
        { label: "Espacios reservados hoy", value: alerts.spaces_reserved_today || 0, status: "aprobada" },
        { label: "Libros dañados", value: alerts.damaged_books || 0, status: "danado" },
        { label: "Libros perdidos", value: alerts.lost_books || 0, status: "perdido" },
        { label: "Baja disponibilidad", value: alerts.low_availability || 0, status: "solicitada" },
        { label: "Plan lector con faltantes", value: alerts.reading_plan_shortages || 0, status: "planificado" },
      ];
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
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Panel operativo CRA</div>
      <div class="d-flex gap-2 flex-wrap">
        <LibraryHelpButton
          title="Ayuda: panel operativo CRA"
          text="Este panel resume préstamos, devoluciones, mora, reservas, plan lector y uso de espacios con foco operativo para la bibliotecaria."
        />
        <BButton variant="primary" @click="loadDashboard">Actualizar</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando indicadores de biblioteca..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3 col-xxl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="text-muted small">{{ card.label }}</div>
                <div class="display-6 fw-semibold">{{ card.value }}</div>
              </div>
              <div class="avatar-title rounded-circle bg-soft-primary text-primary" style="width: 42px; height: 42px">
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
            <LibraryHelpButton
              title="Ayuda: alertas del dashboard"
              text="Las alertas ayudan a priorizar mora, reservas pendientes, daños, pérdidas, baja disponibilidad y faltantes del plan lector."
            />
          </div>
        </template>
        <div class="row g-3">
          <div v-for="item in alertCards" :key="item.label" class="col-md-6 col-xl-3">
            <div class="border rounded p-3 h-100">
              <div class="d-flex justify-content-between gap-2">
                <div>
                  <div class="small text-muted">{{ item.label }}</div>
                  <div class="h2 mb-0">{{ item.value }}</div>
                </div>
                <LibraryStatusBadge :status="item.status" />
              </div>
            </div>
          </div>
        </div>
      </BCard>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="fw-semibold">Préstamos por mes</div>
            </template>
            <apexchart type="bar" height="300" :options="monthChartOptions" :series="seriesFrom(dashboard.charts?.loans_by_month, 'Préstamos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="fw-semibold">Préstamos por curso</div>
            </template>
            <apexchart type="bar" height="300" :options="courseChartOptions" :series="seriesFrom(dashboard.charts?.loans_by_course, 'Préstamos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="fw-semibold">Libros más prestados</div>
            </template>
            <apexchart type="bar" height="300" :options="workChartOptions" :series="seriesFrom(dashboard.charts?.most_loaned_books, 'Préstamos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="fw-semibold">Categorías más utilizadas</div>
            </template>
            <apexchart type="donut" height="300" :options="categoryChartOptions" :series="extractChartTotals(dashboard.charts?.categories_usage)" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="fw-semibold">Mora por curso</div>
            </template>
            <apexchart type="bar" height="300" :options="overdueChartOptions" :series="seriesFrom(dashboard.charts?.overdue_by_course, 'Mora')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="fw-semibold">Uso de espacios por mes</div>
            </template>
            <apexchart type="line" height="300" :options="spaceUsageChartOptions" :series="seriesFrom(dashboard.charts?.space_usage_by_month, 'Reservas')" />
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Participación plan lector</div></template>
            <BTable small responsive :items="dashboard.charts?.reading_plan_participation || []" :fields="[{ key: 'label', label: 'Estado' }, { key: 'total', label: 'Total' }]" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Disponibilidad del inventario</div></template>
            <BTable small responsive :items="dashboard.charts?.inventory_availability || []" :fields="[{ key: 'label', label: 'Estado' }, { key: 'total', label: 'Total' }]" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Alertas recientes</div></template>
            <div class="d-flex flex-column gap-2">
              <div v-for="alert in dashboard.recent?.alerts || []" :key="alert.id" class="border rounded p-2">
                <div class="fw-semibold">{{ alert.title }}</div>
                <div class="small text-muted">{{ alert.message }}</div>
                <div class="small text-muted">{{ formatLibraryDateTime(alert.created_at) }}</div>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Últimos préstamos</div></template>
            <BTable
              small
              responsive
              :items="dashboard.recent?.loans || []"
              :fields="[
                { key: 'loan_code', label: 'Código' },
                { key: 'borrower_name_snapshot', label: 'Usuario' },
                { key: 'obra_title', label: 'Obra' },
                { key: 'due_at', label: 'Vence' },
              ]"
            >
              <template #cell(obra_title)="{ item }">{{ item.obra?.title || "-" }}</template>
              <template #cell(due_at)="{ item }">{{ formatLibraryDate(item.due_at) }}</template>
            </BTable>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Últimas reservas</div></template>
            <BTable
              small
              responsive
              :items="dashboard.recent?.reservations || []"
              :fields="[
                { key: 'reservation_code', label: 'Código' },
                { key: 'resource_type', label: 'Tipo' },
                { key: 'obra_title', label: 'Recurso' },
                { key: 'status', label: 'Estado' },
              ]"
            >
              <template #cell(obra_title)="{ item }">{{ item.obra?.title || "-" }}</template>
              <template #cell(status)="{ item }"><LibraryStatusBadge :status="item.status" /></template>
            </BTable>
          </BCard>
        </div>
      </div>
    </template>
  </div>
</template>
