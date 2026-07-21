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
      lastUpdatedAt: null,
      dashboard: {
        metrics: {},
        charts: {
          equipment_by_status: [],
          equipment_by_type: [],
          loans_by_month: { labels: [], series: [] },
          maintenance_by_month: { labels: [], series: [] },
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
          tone: "primary",
          help: "Cantidad total de equipos informáticos registrados en el módulo.",
        },
        {
          label: "Disponibles",
          value: metrics.available_equipment || 0,
          icon: "bx-check-circle",
          tone: "success",
          help: "Equipos que pueden ser prestados o utilizados de inmediato.",
        },
        {
          label: "Prestados",
          value: metrics.loaned_equipment || 0,
          icon: "bx-transfer-alt",
          tone: "info",
          help: "Equipos actualmente vinculados a un préstamo activo o atrasado.",
        },
        {
          label: "En mantención",
          value: metrics.maintenance_equipment || 0,
          icon: "bx-wrench",
          tone: "warning",
          help: "Equipos que están en proceso técnico y no están disponibles.",
        },
        {
          label: "Dañados",
          value: metrics.damaged_equipment || 0,
          icon: "bx-error",
          tone: "danger",
          help: "Equipos que quedaron marcados como dañados por devolución o cierre técnico.",
        },
        {
          label: "Dados de baja",
          value: metrics.decommissioned_equipment || 0,
          icon: "bx-archive-out",
          tone: "secondary",
          help: "Equipos fuera de operación definitiva por baja técnica o administrativa.",
        },
        {
          label: "Préstamos activos",
          value: metrics.active_loans || 0,
          icon: "bx-calendar-check",
          tone: "primary",
          help: "Préstamos abiertos, incluyendo los que todavía están dentro del plazo.",
        },
        {
          label: "Préstamos atrasados",
          value: metrics.overdue_loans || 0,
          icon: "bx-time-five",
          tone: "danger",
          help: "Préstamos cuya fecha comprometida de devolución ya venció y siguen abiertos.",
        },
        {
          label: "Mantenciones pendientes",
          value: metrics.pending_maintenance || 0,
          icon: "bx-loader-circle",
          tone: "warning",
          help: "Informes en borrador, finalizados o pendientes de revisión que aún no se cierran.",
        },
        {
          label: "Mantenciones cerradas del mes",
          value: metrics.maintenance_this_month || 0,
          icon: "bx-calendar-star",
          tone: "success",
          help: "Mantenciones cerradas durante el mes en curso.",
        },
      ];
    },
    operationalAlerts() {
      const metrics = this.dashboard.metrics || {};
      return [
        metrics.overdue_loans > 0 && {
          label: `${metrics.overdue_loans} préstamo${metrics.overdue_loans === 1 ? "" : "s"} con devolución atrasada`,
          detail: "Requiere seguimiento con el solicitante.",
          icon: "bx-time-five",
          tone: "danger",
          route: "/informatica/prestamos",
        },
        metrics.pending_maintenance > 0 && {
          label: `${metrics.pending_maintenance} mantención${metrics.pending_maintenance === 1 ? "" : "es"} pendiente${metrics.pending_maintenance === 1 ? "" : "s"}`,
          detail: "Revisa diagnóstico, avance o cierre técnico.",
          icon: "bx-wrench",
          tone: "warning",
          route: "/informatica/mantenciones",
        },
        metrics.damaged_equipment > 0 && {
          label: `${metrics.damaged_equipment} equipo${metrics.damaged_equipment === 1 ? "" : "s"} marcado${metrics.damaged_equipment === 1 ? "" : "s"} como dañado${metrics.damaged_equipment === 1 ? "" : "s"}`,
          detail: "Evalúa reparación, reemplazo o baja.",
          icon: "bx-error-circle",
          tone: "danger",
          route: "/informatica/equipos",
        },
      ].filter(Boolean);
    },
    lastUpdatedLabel() {
      if (!this.lastUpdatedAt) return "Sin actualizar";
      return `Actualizado ${this.lastUpdatedAt.toLocaleTimeString("es-CL", { hour: "2-digit", minute: "2-digit" })}`;
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
        this.lastUpdatedAt = new Date();
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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-1">
      <div>
        <div class="fw-semibold">Panel operativo</div>
        <div class="small text-muted">{{ lastUpdatedLabel }}</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <InformaticaHelpButton
          title="Ayuda: dashboard de informática"
          text="Este panel concentra disponibilidad del parque tecnológico, préstamos activos y atrasados, mantenciones en curso y movimientos recientes del módulo."
        />
        <BButton variant="primary" :disabled="loading" @click="loadDashboard">
          <i class="bx bx-refresh me-1" :class="{ 'bx-spin': loading }"></i>{{ loading ? "Actualizando" : "Actualizar" }}
        </BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando indicadores de informática..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div class="col-lg-8">
          <BCard class="border-0 shadow-sm h-100 dashboard-priority-card">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
              <div><div class="fw-semibold fs-5">Prioridades de hoy</div><div class="small text-muted">Situaciones que necesitan atención del equipo TI</div></div>
              <span class="badge rounded-pill" :class="operationalAlerts.length ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'">{{ operationalAlerts.length }} pendientes</span>
            </div>
            <div v-if="operationalAlerts.length" class="d-grid gap-2">
              <router-link v-for="alert in operationalAlerts" :key="alert.label" :to="alert.route" class="dashboard-alert">
                <span class="dashboard-alert__icon" :class="`is-${alert.tone}`"><i :class="`bx ${alert.icon}`"></i></span>
                <span><strong>{{ alert.label }}</strong><small>{{ alert.detail }}</small></span>
                <i class="bx bx-right-arrow-alt ms-auto"></i>
              </router-link>
            </div>
            <div v-else class="dashboard-all-clear"><i class="bx bx-check-shield"></i><div><strong>Todo está bajo control</strong><small>No hay atrasos ni incidencias críticas registradas.</small></div></div>
          </BCard>
        </div>
        <div class="col-lg-4">
          <BCard class="border-0 shadow-sm h-100">
            <div class="fw-semibold fs-5 mb-1">Acciones rápidas</div>
            <div class="small text-muted mb-3">Continúa con una tarea frecuente</div>
            <div class="d-grid gap-2">
              <router-link to="/informatica/equipos" class="dashboard-quick-link"><i class="bx bx-plus-circle"></i><span>Registrar o buscar equipo</span><i class="bx bx-chevron-right ms-auto"></i></router-link>
              <router-link to="/informatica/prestamos" class="dashboard-quick-link"><i class="bx bx-transfer"></i><span>Gestionar préstamo</span><i class="bx bx-chevron-right ms-auto"></i></router-link>
              <router-link to="/informatica/mantenciones" class="dashboard-quick-link"><i class="bx bx-wrench"></i><span>Crear informe técnico</span><i class="bx bx-chevron-right ms-auto"></i></router-link>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div v-for="card in metricCards" :key="card.label" class="col-sm-6 col-xl-3 col-xxl-2">
          <BCard class="border-0 shadow-sm h-100 metric-card" :class="`metric-card--${card.tone}`">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="small text-muted d-flex align-items-center gap-2">
                  <span>{{ card.label }}</span>
                  <InformaticaHelpButton :title="card.label" :text="card.help" button-text="?" size="sm" />
                </div>
                <div class="display-6 fw-semibold">{{ card.value }}</div>
              </div>
              <div class="metric-card__icon">
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
              show-empty
              empty-text="Aún no hay préstamos registrados."
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
              show-empty
              empty-text="Aún no hay mantenciones registradas."
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

<style scoped>
.dashboard-priority-card { background: linear-gradient(145deg, var(--bs-card-bg, #fff), rgba(85,110,230,.035)); }
.dashboard-alert, .dashboard-quick-link { display: flex; align-items: center; gap: .75rem; color: #394056; border: 1px solid #ebedf3; border-radius: 11px; background: rgba(255,255,255,.72); transition: .18s ease; }
.dashboard-alert { padding: .7rem .8rem; }
.dashboard-alert:hover, .dashboard-quick-link:hover { color: #4057d6; border-color: #cdd4f8; transform: translateX(3px); }
.dashboard-alert strong, .dashboard-alert small { display: block; }
.dashboard-alert strong { font-size: .82rem; }
.dashboard-alert small { margin-top: .1rem; color: #7a8293; font-size: .72rem; }
.dashboard-alert__icon { display: grid; flex: 0 0 34px; height: 34px; place-items: center; border-radius: 9px; font-size: 1.05rem; }
.dashboard-alert__icon.is-danger { color: #e05a67; background: rgba(244,106,106,.12); }
.dashboard-alert__icon.is-warning { color: #c88a18; background: rgba(241,180,76,.15); }
.dashboard-quick-link { padding: .75rem .85rem; font-size: .82rem; font-weight: 600; }
.dashboard-quick-link > i:first-child { color: #556ee6; font-size: 1.2rem; }
.dashboard-all-clear { display: flex; align-items: center; gap: 1rem; min-height: 105px; padding: 1rem; color: #27845f; border-radius: 12px; background: rgba(52,195,143,.09); }
.dashboard-all-clear > i { font-size: 2.2rem; }
.dashboard-all-clear strong, .dashboard-all-clear small { display: block; }.dashboard-all-clear small { margin-top: .2rem; color: #668077; }
.metric-card { position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease; }
.metric-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(32,44,89,.1) !important; }
.metric-card::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--metric-color, #556ee6); }
.metric-card--success { --metric-color: #34c38f; }.metric-card--info { --metric-color: #50a5f1; }.metric-card--warning { --metric-color: #f1b44c; }.metric-card--danger { --metric-color: #f46a6a; }.metric-card--secondary { --metric-color: #74788d; }
.metric-card__icon { display: grid; flex: 0 0 42px; height: 42px; place-items: center; color: var(--metric-color, #556ee6); border-radius: 12px; background: color-mix(in srgb, var(--metric-color, #556ee6) 12%, transparent); }
@media (max-width: 575.98px) { .metric-card :deep(.display-6) { font-size: 1.8rem; } }
</style>
