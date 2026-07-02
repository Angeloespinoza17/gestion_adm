<script>
import axios from "axios";
import InformaticaHelpButton from "../help-button.vue";
import InformaticaStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  extractChartLabels,
  extractChartTotals,
  formatInformaticaDate,
  formatInformaticaDateTime,
  formatInformaticaError,
} from "../module-utils";

export default {
  components: {
    InformaticaHelpButton,
    InformaticaStatusBadge,
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
        charts: {
          equipment_by_status: [],
          equipment_by_type: [],
          loans_by_month: { labels: [], series: [] },
          maintenance_by_month: { labels: [], series: [], costs: [] },
          top_loaned_equipment: [],
          top_maintenance_equipment: [],
        },
        recent: {
          loans: [],
          maintenance: [],
        },
      },
    };
  },
  computed: {
    metricCards() {
      const metrics = this.dashboard.metrics || {};
      return [
        {
          label: "Equipos registrados",
          value: metrics.total_equipment || 0,
          icon: "bx-laptop",
          help: "Cantidad total de equipos informáticos registrados en el módulo.",
        },
        {
          label: "Disponibles",
          value: metrics.available_equipment || 0,
          icon: "bx-check-circle",
          help: "Equipos que pueden ser prestados o utilizados de inmediato.",
        },
        {
          label: "Prestados",
          value: metrics.loaned_equipment || 0,
          icon: "bx-transfer-alt",
          help: "Equipos actualmente vinculados a un préstamo activo o atrasado.",
        },
        {
          label: "En mantención",
          value: metrics.maintenance_equipment || 0,
          icon: "bx-wrench",
          help: "Equipos que están en proceso técnico y no están disponibles.",
        },
        {
          label: "Dañados",
          value: metrics.damaged_equipment || 0,
          icon: "bx-error",
          help: "Equipos que quedaron marcados como dañados por devolución o cierre técnico.",
        },
        {
          label: "Dados de baja",
          value: metrics.decommissioned_equipment || 0,
          icon: "bx-archive-out",
          help: "Equipos fuera de operación definitiva por baja técnica o administrativa.",
        },
        {
          label: "Préstamos activos",
          value: metrics.active_loans || 0,
          icon: "bx-calendar-check",
          help: "Préstamos abiertos, incluyendo los que todavía están dentro del plazo.",
        },
        {
          label: "Préstamos atrasados",
          value: metrics.overdue_loans || 0,
          icon: "bx-time-five",
          help: "Préstamos cuya fecha comprometida de devolución ya venció y siguen abiertos.",
        },
        {
          label: "Mantenciones pendientes",
          value: metrics.pending_maintenance || 0,
          icon: "bx-loader-circle",
          help: "Informes en borrador, finalizados o pendientes de revisión que aún no se cierran.",
        },
        {
          label: "Mantenciones cerradas del mes",
          value: metrics.maintenance_this_month || 0,
          icon: "bx-calendar-star",
          help: "Mantenciones cerradas durante el mes en curso.",
        },
      ];
    },
    equipmentStatusChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.equipment_by_status),
          colors: ["#34c38f"],
        }),
      };
    },
    equipmentTypeChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.equipment_by_type),
          colors: ["#556ee6"],
          horizontal: true,
        }),
      };
    },
    loanMonthChartOptions() {
      return {
        ...basicApexOptions({
          categories: this.dashboard.charts?.loans_by_month?.labels || [],
          colors: ["#50a5f1"],
        }),
      };
    },
    maintenanceMonthChartOptions() {
      return {
        ...basicApexOptions({
          categories: this.dashboard.charts?.maintenance_by_month?.labels || [],
          colors: ["#f1b44c"],
        }),
      };
    },
    topLoanedChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.top_loaned_equipment),
          colors: ["#556ee6"],
          horizontal: true,
        }),
      };
    },
    topMaintenanceChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.dashboard.charts?.top_maintenance_equipment),
          colors: ["#f46a6a"],
          horizontal: true,
        }),
      };
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    formatInformaticaDate,
    formatInformaticaDateTime,
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/informatica/dashboard");
        this.dashboard = response.data;
      } catch (error) {
        this.error = formatInformaticaError(error, "No se pudo cargar el dashboard de Informática.");
      } finally {
        this.loading = false;
      }
    },
    seriesFromItems(items, name = "Total") {
      return [{ name, data: extractChartTotals(items) }];
    },
    seriesFromMonthly(payload, name = "Total") {
      return [{ name, data: payload?.series || [] }];
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Panel operativo de Informática</div>
      <div class="d-flex gap-2 flex-wrap">
        <InformaticaHelpButton
          title="Ayuda: dashboard de informática"
          text="Este panel concentra disponibilidad del parque tecnológico, préstamos activos y atrasados, mantenciones en curso y movimientos recientes del módulo."
        />
        <BButton variant="primary" @click="loadDashboard">Actualizar</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando indicadores de informática..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3 col-xxl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="small text-muted d-flex align-items-center gap-2">
                  <span>{{ card.label }}</span>
                  <InformaticaHelpButton :title="card.label" :text="card.help" button-text="?" size="sm" />
                </div>
                <div class="display-6 fw-semibold">{{ card.value }}</div>
              </div>
              <div class="avatar-title rounded-circle bg-soft-primary text-primary" style="width: 42px; height: 42px">
                <i :class="`bx ${card.icon} fs-4`"></i>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Equipos por estado</div>
                <InformaticaHelpButton
                  title="Ayuda: equipos por estado"
                  text="Distribución actual de equipos disponibles, prestados, en mantención, dañados o dados de baja."
                />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="equipmentStatusChartOptions" :series="seriesFromItems(dashboard.charts?.equipment_by_status, 'Equipos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Equipos por tipo</div>
                <InformaticaHelpButton
                  title="Ayuda: equipos por tipo"
                  text="Permite visualizar qué tipos de equipos concentran mayor cantidad de registros."
                />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="equipmentTypeChartOptions" :series="seriesFromItems(dashboard.charts?.equipment_by_type, 'Equipos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Préstamos por mes</div>
                <InformaticaHelpButton
                  title="Ayuda: préstamos por mes"
                  text="Muestra la evolución mensual de préstamos registrados en el año actual."
                />
              </div>
            </template>
            <apexchart type="line" height="300" :options="loanMonthChartOptions" :series="seriesFromMonthly(dashboard.charts?.loans_by_month, 'Préstamos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Mantenciones por mes</div>
                <InformaticaHelpButton
                  title="Ayuda: mantenciones por mes"
                  text="Resume la carga de mantenciones registradas por mes para facilitar la planificación técnica."
                />
              </div>
            </template>
            <apexchart type="line" height="300" :options="maintenanceMonthChartOptions" :series="seriesFromMonthly(dashboard.charts?.maintenance_by_month, 'Mantenciones')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Equipos con más préstamos</div>
                <InformaticaHelpButton
                  title="Ayuda: equipos con más préstamos"
                  text="Identifica los equipos con mayor rotación para priorizar mantención, renovación o stock adicional."
                />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="topLoanedChartOptions" :series="seriesFromItems(dashboard.charts?.top_loaned_equipment, 'Préstamos')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Equipos con más mantenciones</div>
                <InformaticaHelpButton
                  title="Ayuda: equipos con más mantenciones"
                  text="Permite detectar equipos críticos por frecuencia de intervención técnica."
                />
              </div>
            </template>
            <apexchart type="bar" height="300" :options="topMaintenanceChartOptions" :series="seriesFromItems(dashboard.charts?.top_maintenance_equipment, 'Mantenciones')" />
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Últimos préstamos registrados</div>
                <InformaticaHelpButton
                  title="Ayuda: últimos préstamos"
                  text="Lista las entregas más recientes con su estado actual y fechas comprometidas."
                />
              </div>
            </template>
            <BTable
              small
              responsive
              :items="dashboard.recent?.loans || []"
              :fields="[
                { key: 'loan_code', label: 'Código' },
                { key: 'equipment', label: 'Equipo' },
                { key: 'borrowed_at', label: 'Préstamo' },
                { key: 'due_at', label: 'Dev. comprometida' },
                { key: 'status', label: 'Estado' },
              ]"
            >
              <template #cell(equipment)="{ item }">
                <div class="fw-semibold">{{ item.equipment?.internal_code || "-" }}</div>
                <div class="small text-muted">{{ [item.equipment?.brand, item.equipment?.model].filter(Boolean).join(" ") || "-" }}</div>
              </template>
              <template #cell(borrowed_at)="{ item }">{{ formatInformaticaDateTime(item.borrowed_at) }}</template>
              <template #cell(due_at)="{ item }">{{ formatInformaticaDateTime(item.due_at) }}</template>
              <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
            </BTable>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Últimos informes de mantención</div>
                <InformaticaHelpButton
                  title="Ayuda: últimos informes de mantención"
                  text="Muestra los informes técnicos más recientes, su estado de cierre y el equipo asociado."
                />
              </div>
            </template>
            <BTable
              small
              responsive
              :items="dashboard.recent?.maintenance || []"
              :fields="[
                { key: 'maintenance_code', label: 'Código' },
                { key: 'equipment', label: 'Equipo' },
                { key: 'maintenance_date', label: 'Fecha' },
                { key: 'maintenance_type', label: 'Tipo' },
                { key: 'status', label: 'Estado' },
              ]"
            >
              <template #cell(equipment)="{ item }">
                <div class="fw-semibold">{{ item.equipment?.internal_code || "-" }}</div>
                <div class="small text-muted">{{ [item.equipment?.brand, item.equipment?.model].filter(Boolean).join(" ") || "-" }}</div>
              </template>
              <template #cell(maintenance_date)="{ item }">{{ formatInformaticaDate(item.maintenance_date) }}</template>
              <template #cell(maintenance_type)="{ item }">{{ item.maintenance_type?.replaceAll("_", " ") }}</template>
              <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
            </BTable>
          </BCard>
        </div>
      </div>
    </template>
  </div>
</template>
