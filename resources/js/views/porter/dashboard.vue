<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      loading: false,
      error: null,
      stats: {},
      alerts: [],
      pendingDeliveries: { items: [], goods: [] },
      activeControls: { visits: [], external_services: [], key_loans: [], daily_logs: [] },
      recentMovements: [],
      withdrawalsToday: [],
      activityTrend: [],
      generatedAt: null,
      canExport: false,
      selectedAlert: null,
      showAlertModal: false,
    };
  },
  mounted() {
    this.loadDashboard();
  },
  computed: {
    quickActions() {
      return [
        { label: "Buscar estudiante", to: "/porter/students", icon: "bx bx-search-alt", primary: true },
        { label: "Registrar retiro", to: "/porter/withdrawals", icon: "bx bx-log-out-circle", primary: true },
        { label: "Recepción", to: "/porter/received-items", icon: "bx bx-package" },
        { label: "Mercadería", to: "/porter/goods", icon: "bx bx-cube" },
        { label: "Visitas", to: "/porter/visits", icon: "bx bx-id-card" },
        { label: "Proveedores", to: "/porter/providers", icon: "bx bx-briefcase-alt-2" },
        { label: "Bitácora", to: "/porter/daily-log", icon: "bx bx-notepad" },
        { label: "Llaves", to: "/porter/keys", icon: "bx bx-key" },
      ];
    },
    statCards() {
      const pendingItems = Number(this.stats.pending_items || 0);
      const pendingGoods = Number(this.stats.pending_goods || 0);
      const activeVisits = Number(this.stats.active_visits || 0);
      const activeProviders = Number(this.stats.active_external_services || 0);

      return [
        {
          label: "Retiros hoy",
          value: this.stats.withdrawals_today || 0,
          detail: "Salidas registradas",
          icon: "bx bx-log-out-circle",
          tone: "primary",
          to: "/porter/withdrawals",
        },
        {
          label: "Pendientes",
          value: pendingItems + pendingGoods,
          detail: `${pendingItems} objetos · ${pendingGoods} mercadería`,
          icon: "bx bx-package",
          tone: "warning",
          to: "/porter/received-items",
        },
        {
          label: "Observados",
          value: this.stats.observed_withdrawals || 0,
          detail: "Retiros por resolver",
          icon: "bx bx-error-circle",
          tone: "danger",
          to: "/porter/withdrawals",
        },
        {
          label: "Activos",
          value: activeVisits + activeProviders,
          detail: `${activeVisits} visitas · ${activeProviders} proveedores`,
          icon: "bx bx-user-check",
          tone: "success",
          to: "/porter/visits",
        },
        {
          label: "Llaves fuera",
          value: this.stats.keys_out || 0,
          detail: "Sin devolución registrada",
          icon: "bx bx-key",
          tone: "info",
          to: "/porter/keys",
        },
        {
          label: "Restricciones",
          value: this.stats.students_with_pickup_restriction || 0,
          detail: "Estudiantes con alerta",
          icon: "bx bx-shield-quarter",
          tone: "secondary",
          to: "/porter/students",
        },
      ];
    },
    deliveryQueue() {
      const items = (this.pendingDeliveries.items || []).map((item) => ({
        id: `item-${item.id}`,
        type: "Objeto/documento",
        title: item.description,
        detail: item.recipient_label || "Recepción en portería",
        status: item.status,
        when: item.received_at,
        to: "/porter/received-items",
        icon: "bx bx-envelope",
      }));

      const goods = (this.pendingDeliveries.goods || []).map((item) => ({
        id: `goods-${item.id}`,
        type: "Mercadería",
        title: item.goods_detail,
        detail: item.contact_name || this.humanize(item.movement_type),
        status: item.status,
        when: item.moved_at,
        to: "/porter/goods",
        icon: "bx bx-cube",
      }));

      return items.concat(goods).sort((a, b) => this.timestamp(a.when) - this.timestamp(b.when)).slice(0, 6);
    },
    activeQueue() {
      const visits = (this.activeControls.visits || []).map((item) => ({
        id: `visit-${item.id}`,
        type: "Visita",
        title: item.visitor_name,
        detail: item.purpose || item.visited_person_label || "Ingreso activo",
        status: item.status,
        when: item.entered_at,
        to: "/porter/visits",
        icon: "bx bx-id-card",
      }));

      const providers = (this.activeControls.external_services || []).map((item) => ({
        id: `provider-${item.id}`,
        type: "Proveedor",
        title: item.company_name || item.contact_name || "Proveedor",
        detail: [item.service_type, item.vehicle_plate].filter(Boolean).join(" · "),
        status: item.status,
        when: item.entered_at,
        to: "/porter/providers",
        icon: "bx bx-briefcase-alt-2",
      }));

      return visits.concat(providers).sort((a, b) => this.timestamp(a.when) - this.timestamp(b.when)).slice(0, 6);
    },
    keyAndLogQueue() {
      const keys = (this.activeControls.key_loans || []).map((item) => ({
        id: `loan-${item.id}`,
        type: "Llave",
        title: item.porter_key?.name || "Llave",
        detail: item.requester_name,
        status: item.status,
        when: item.checked_out_at,
        to: "/porter/keys",
        icon: "bx bx-key",
      }));

      const logs = (this.activeControls.daily_logs || []).map((item) => ({
        id: `log-${item.id}`,
        type: "Bitácora",
        title: item.title,
        detail: item.registered_by?.name || "Registro destacado",
        status: item.priority,
        when: item.logged_at,
        to: "/porter/daily-log",
        icon: "bx bx-notepad",
      }));

      return keys.concat(logs).sort((a, b) => this.timestamp(a.when) - this.timestamp(b.when)).slice(0, 6);
    },
    activitySeries() {
      return [
        { name: "Retiros", data: this.activityTrend.map((item) => Number(item.withdrawals || 0)) },
        { name: "Visitas", data: this.activityTrend.map((item) => Number(item.visits || 0)) },
        { name: "Recepciones", data: this.activityTrend.map((item) => Number(item.received_items || 0) + Number(item.goods || 0)) },
      ];
    },
    activityChartOptions() {
      return {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: "inherit" },
        colors: ["#556ee6", "#34c38f", "#50a5f1"],
        dataLabels: { enabled: false },
        stroke: { curve: "smooth", width: 3 },
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.03, stops: [0, 95, 100] } },
        grid: { borderColor: "rgba(148, 163, 184, .2)", strokeDashArray: 4 },
        xaxis: { categories: this.activityTrend.map((item) => item.label), labels: { style: { colors: "#74788d" } } },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value) } },
        legend: { position: "top", horizontalAlign: "right" },
        tooltip: { shared: true, intersect: false },
      };
    },
    controlSeries() {
      return [
        Number(this.stats.active_visits || 0),
        Number(this.stats.active_external_services || 0),
        Number(this.stats.keys_out || 0),
      ];
    },
    controlChartOptions() {
      return {
        chart: { fontFamily: "inherit" },
        labels: ["Visitas", "Proveedores", "Llaves"],
        colors: ["#34c38f", "#50a5f1", "#f1b44c"],
        legend: { position: "bottom" },
        dataLabels: { enabled: true },
        plotOptions: { pie: { donut: { size: "68%", labels: { show: true, total: { show: true, label: "Activos" } } } } },
        noData: { text: "Sin controles activos" },
      };
    },
  },
  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/dashboard");
        this.stats = response.data.stats || {};
        this.alerts = response.data.alerts || [];
        this.pendingDeliveries = response.data.pending_deliveries || { items: [], goods: [] };
        this.activeControls = response.data.active_controls || { visits: [], external_services: [], key_loans: [], daily_logs: [] };
        this.recentMovements = response.data.recent_movements || [];
        this.withdrawalsToday = response.data.withdrawals_today || [];
        this.activityTrend = response.data.activity_trend || [];
        this.generatedAt = response.data.generated_at || null;
        this.canExport = Boolean(response.data.capabilities?.can_export);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatTime(value) {
      const formatted = this.formatDateTime(value);
      return formatted.includes(" ") ? formatted.split(" ").pop() : formatted;
    },
    humanize(value) {
      if (!value) return "-";
      return String(value)
        .replace(/_/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    timestamp(value) {
      if (!value) return 0;
      return new Date(String(value).replace(" ", "T")).getTime() || 0;
    },
    alertTone(priority) {
      if (priority === "high") return "danger";
      if (priority === "medium") return "warning";
      return "info";
    },
    alertIcon(kind) {
      const icons = {
        withdrawal: "bx bx-error-circle",
        received_item: "bx bx-envelope",
        goods: "bx bx-cube",
        visit: "bx bx-id-card",
        external_service: "bx bx-briefcase-alt-2",
        key_loan: "bx bx-key",
        daily_log: "bx bx-notepad",
      };

      return icons[kind] || "bx bx-bell";
    },
    openAlert(alert) {
      this.selectedAlert = alert;
      this.showAlertModal = true;
    },
    alertRoute(kind) {
      return {
        withdrawal: "/porter/withdrawals",
        received_item: "/porter/received-items",
        goods: "/porter/goods",
        visit: "/porter/visits",
        external_service: "/porter/providers",
        key_loan: "/porter/keys",
        daily_log: "/porter/daily-log",
      }[kind] || "/porter/dashboard";
    },
    exportSnapshot() {
      if (!this.canExport) return;
      const rows = [
        ["Indicador", "Valor"],
        ...this.statCards.map((item) => [item.label, item.value]),
        [],
        ["Alerta", "Detalle", "Fecha"],
        ...this.alerts.map((item) => [item.label, item.detail, this.formatDateTime(item.when)]),
      ];
      const csv = rows.map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(";")).join("\n");
      const blob = new Blob([`\uFEFF${csv}`], { type: "text/csv;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `panel_porteria_${new Date().toISOString().slice(0, 10)}.csv`;
      link.click();
      URL.revokeObjectURL(url);
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el dashboard.";
    },
  },
};
</script>

<template>
  <Layout>
    <section class="porter-dashboard">
      <div class="dashboard-heading">
        <div>
          <div class="heading-eyebrow"><span class="live-dot"></span> Centro de control</div>
          <h4 class="mb-1">Portería</h4>
          <div class="text-muted">Vista operacional en tiempo real para coordinar ingresos, salidas y recepciones.</div>
          <small v-if="generatedAt" class="last-update">Actualizado {{ formatDateTime(generatedAt) }}</small>
        </div>
        <div class="heading-actions">
          <BButton v-if="canExport" variant="outline-success" @click="exportSnapshot"><i class="bx bx-download me-1"></i>Exportar resumen</BButton>
          <router-link to="/porter/reports" class="btn btn-outline-primary"><i class="bx bx-bar-chart-alt-2 me-1"></i>Reportes</router-link>
          <BButton variant="primary" :disabled="loading" @click="loadDashboard">
            <i class="bx bx-refresh me-1" :class="{ 'bx-spin': loading }"></i>Actualizar
          </BButton>
        </div>
      </div>

      <div class="quick-action-grid">
        <router-link
          v-for="action in quickActions"
          :key="action.to"
          :to="action.to"
          class="quick-action"
          :class="{ 'quick-action--primary': action.primary }"
        >
          <span class="quick-action-icon"><i :class="action.icon"></i></span>
          <span>{{ action.label }}</span>
        </router-link>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <BCard v-if="loading" class="dashboard-panel">
        <LoadingState message="Cargando panel de portería..." compact />
      </BCard>

      <template v-else>
        <div class="stat-grid">
          <template v-for="card in statCards" :key="card.label">
            <router-link v-if="card.to" :to="card.to" class="stat-card" :class="`stat-card--${card.tone}`">
              <span class="stat-card-icon"><i :class="card.icon"></i></span>
              <span class="stat-card-content">
                <span class="stat-card-label">{{ card.label }}</span>
                <strong>{{ card.value }}</strong>
                <span class="stat-card-detail">{{ card.detail }}</span>
              </span>
            </router-link>
            <div v-else class="stat-card" :class="`stat-card--${card.tone}`">
              <span class="stat-card-icon"><i :class="card.icon"></i></span>
              <span class="stat-card-content">
                <span class="stat-card-label">{{ card.label }}</span>
                <strong>{{ card.value }}</strong>
                <span class="stat-card-detail">{{ card.detail }}</span>
              </span>
            </div>
          </template>
        </div>

        <div class="analytics-grid">
          <BCard class="dashboard-panel analytics-card">
            <div class="panel-header">
              <div>
                <h5 class="mb-1">Actividad de los últimos 7 días</h5>
                <div class="text-muted small">Evolución diaria de los principales movimientos.</div>
              </div>
              <span class="context-chip">Últimos 7 días</span>
            </div>
            <apexchart type="area" height="290" :options="activityChartOptions" :series="activitySeries" />
          </BCard>
          <BCard class="dashboard-panel analytics-card">
            <div class="panel-header">
              <div>
                <h5 class="mb-1">Controles activos</h5>
                <div class="text-muted small">Personas y recursos aún abiertos.</div>
              </div>
            </div>
            <apexchart type="donut" height="290" :options="controlChartOptions" :series="controlSeries" />
          </BCard>
        </div>

        <div class="row g-3 align-items-start">
          <div class="col-xxl-7">
            <BCard class="dashboard-panel mb-3">
              <div class="panel-header">
                <div>
                  <h5 class="mb-1">Cola operativa</h5>
                  <div class="text-muted small">Pendientes que requieren seguimiento desde portería.</div>
                </div>
                <router-link to="/porter/received-items" class="btn btn-sm btn-outline-primary">Ver pendientes</router-link>
              </div>

              <div v-if="!deliveryQueue.length" class="empty-state">
                <i class="bx bx-check-circle"></i>
                <span>Sin objetos ni mercadería pendiente.</span>
              </div>
              <div v-else class="queue-list">
                <router-link v-for="item in deliveryQueue" :key="item.id" :to="item.to" class="queue-item">
                  <span class="queue-icon"><i :class="item.icon"></i></span>
                  <span class="queue-body">
                    <span class="queue-type">{{ item.type }}</span>
                    <strong>{{ item.title }}</strong>
                    <span>{{ item.detail || "Sin detalle" }}</span>
                  </span>
                  <span class="queue-meta">
                    <PorterStatusBadge :value="item.status" :label="humanize(item.status)" />
                    <small>{{ formatDateTime(item.when) }}</small>
                  </span>
                </router-link>
              </div>
            </BCard>

            <div class="row g-3">
              <div class="col-xl-6">
                <BCard class="dashboard-panel h-100">
                  <div class="panel-header panel-header--compact">
                    <h5 class="mb-0">Activos dentro del recinto</h5>
                    <router-link to="/porter/visits" class="panel-link">Ver</router-link>
                  </div>
                  <div v-if="!activeQueue.length" class="empty-state empty-state--compact">
                    <i class="bx bx-user-check"></i>
                    <span>Sin visitas ni proveedores en curso.</span>
                  </div>
                  <div v-else class="compact-list">
                    <router-link v-for="item in activeQueue" :key="item.id" :to="item.to" class="compact-item">
                      <span>
                        <strong>{{ item.title }}</strong>
                        <small>{{ item.type }} · {{ item.detail || "Sin detalle" }}</small>
                      </span>
                      <small>{{ formatTime(item.when) }}</small>
                    </router-link>
                  </div>
                </BCard>
              </div>

              <div class="col-xl-6">
                <BCard class="dashboard-panel h-100">
                  <div class="panel-header panel-header--compact">
                    <h5 class="mb-0">Llaves y bitácora</h5>
                    <router-link to="/porter/keys" class="panel-link">Ver</router-link>
                  </div>
                  <div v-if="!keyAndLogQueue.length" class="empty-state empty-state--compact">
                    <i class="bx bx-key"></i>
                    <span>Sin llaves prestadas ni bitácoras destacadas.</span>
                  </div>
                  <div v-else class="compact-list">
                    <router-link v-for="item in keyAndLogQueue" :key="item.id" :to="item.to" class="compact-item">
                      <span>
                        <strong>{{ item.title }}</strong>
                        <small>{{ item.type }} · {{ item.detail || "Sin detalle" }}</small>
                      </span>
                      <PorterStatusBadge :value="item.status" :label="humanize(item.status)" />
                    </router-link>
                  </div>
                </BCard>
              </div>
            </div>
          </div>

          <div class="col-xxl-5">
            <BCard class="dashboard-panel alerts-panel">
              <div class="panel-header">
                <div>
                  <h5 class="mb-1">Alertas importantes</h5>
                  <div class="text-muted small">{{ stats.alerts_total || 0 }} alerta(s) activas.</div>
                </div>
                <router-link to="/porter/reports" class="btn btn-sm btn-outline-secondary">Reportes</router-link>
              </div>

              <div v-if="!alerts.length" class="empty-state">
                <i class="bx bx-bell-off"></i>
                <span>No hay alertas activas.</span>
              </div>
              <div v-else class="alert-list">
                <div
                  v-for="(alert, index) in alerts"
                  :key="`${alert.kind}-${index}`"
                  class="alert-item"
                  :class="`alert-item--${alertTone(alert.priority)}`"
                  role="button"
                  tabindex="0"
                  @click="openAlert(alert)"
                  @keydown.enter="openAlert(alert)"
                >
                  <span class="alert-icon"><i :class="alertIcon(alert.kind)"></i></span>
                  <span class="alert-body">
                    <span class="alert-title">{{ alert.label }}</span>
                    <span class="alert-detail">{{ alert.detail }}</span>
                  </span>
                  <span class="alert-time">{{ formatDateTime(alert.when) }}</span>
                </div>
              </div>
            </BCard>
          </div>
        </div>

        <div class="row g-3 mt-0">
          <div class="col-xl-6">
            <BCard class="dashboard-panel h-100">
              <div class="panel-header">
                <div>
                  <h5 class="mb-1">Retiros del día</h5>
                  <div class="text-muted small">Últimos registros de salida.</div>
                </div>
                <router-link to="/porter/withdrawals" class="btn btn-sm btn-outline-primary">Registrar</router-link>
              </div>

              <div v-if="!withdrawalsToday.length" class="empty-state">
                <i class="bx bx-log-out-circle"></i>
                <span>No hay retiros registrados hoy.</span>
              </div>
              <div v-else class="table-responsive">
                <table class="table table-sm align-middle dashboard-table mb-0">
                  <thead>
                    <tr>
                      <th>Estudiante</th>
                      <th>Retira</th>
                      <th>Estado</th>
                      <th class="text-end">Hora</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in withdrawalsToday" :key="item.id">
                      <td>
                        <div class="fw-semibold">{{ item.student_full_name_snapshot }}</div>
                        <small class="text-muted">{{ item.course_name_snapshot || "Sin curso" }}</small>
                      </td>
                      <td>{{ item.person_name }}</td>
                      <td><PorterStatusBadge :value="item.status" :label="humanize(item.status)" /></td>
                      <td class="text-end">{{ formatTime(item.withdrawn_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>

          <div class="col-xl-6">
            <BCard class="dashboard-panel h-100">
              <div class="panel-header">
                <div>
                  <h5 class="mb-1">Últimos movimientos</h5>
                  <div class="text-muted small">Trazabilidad reciente del módulo.</div>
                </div>
              </div>

              <div v-if="!recentMovements.length" class="empty-state">
                <i class="bx bx-history"></i>
                <span>Aún no hay movimientos registrados.</span>
              </div>
              <div v-else class="table-responsive">
                <table class="table table-sm align-middle dashboard-table mb-0">
                  <thead>
                    <tr>
                      <th>Acción</th>
                      <th>Usuario</th>
                      <th>Estado</th>
                      <th class="text-end">Fecha</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in recentMovements" :key="item.id">
                      <td>{{ item.description || humanize(item.action) }}</td>
                      <td>{{ item.performed_by?.name || "-" }}</td>
                      <td><PorterStatusBadge :value="item.to_status" :label="humanize(item.to_status)" /></td>
                      <td class="text-end">{{ formatDateTime(item.performed_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
        </div>
      </template>

      <BModal v-model="showAlertModal" title="Detalle de alerta" centered hide-footer>
        <div v-if="selectedAlert" class="alert-detail-modal">
          <span class="modal-alert-icon" :class="`modal-alert-icon--${alertTone(selectedAlert.priority)}`">
            <i :class="alertIcon(selectedAlert.kind)"></i>
          </span>
          <div>
            <span class="text-muted small text-uppercase">{{ humanize(selectedAlert.kind) }}</span>
            <h5 class="mt-1 mb-2">{{ selectedAlert.label }}</h5>
            <p class="mb-2">{{ selectedAlert.detail }}</p>
            <div class="text-muted small"><i class="bx bx-time-five me-1"></i>{{ formatDateTime(selectedAlert.when) }}</div>
          </div>
        </div>
        <div class="modal-actions mt-4">
          <BButton variant="outline-secondary" @click="showAlertModal = false">Cerrar</BButton>
          <router-link v-if="selectedAlert" :to="alertRoute(selectedAlert.kind)" class="btn btn-primary" @click="showAlertModal = false">Ir al registro</router-link>
        </div>
      </BModal>
    </section>
  </Layout>
</template>

<style scoped>
.porter-dashboard {
  display: grid;
  gap: 1rem;
}

.dashboard-heading {
  align-items: flex-start;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.heading-eyebrow {
  align-items: center;
  color: var(--bs-success);
  display: flex;
  font-size: 0.72rem;
  font-weight: 700;
  gap: 0.45rem;
  letter-spacing: 0.08em;
  margin-bottom: 0.35rem;
  text-transform: uppercase;
}

.live-dot {
  background: var(--bs-success);
  border-radius: 50%;
  box-shadow: 0 0 0 0.28rem rgba(var(--bs-success-rgb), 0.13);
  height: 0.45rem;
  width: 0.45rem;
}

.last-update {
  color: var(--bs-secondary-color);
  display: inline-block;
  margin-top: 0.35rem;
}

.heading-actions,
.modal-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.quick-action-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
}

.quick-action {
  align-items: center;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  color: var(--bs-body-color);
  display: flex;
  font-weight: 600;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0.5rem 0.75rem;
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
}

.quick-action:hover,
.quick-action:focus {
  border-color: var(--bs-primary);
  box-shadow: 0 0.35rem 1rem rgba(var(--bs-primary-rgb), 0.08);
  color: var(--bs-primary);
}

.quick-action--primary {
  background: var(--bs-primary);
  border-color: var(--bs-primary);
  color: #fff;
}

.quick-action--primary:hover,
.quick-action--primary:focus {
  color: #fff;
}

.quick-action-icon,
.stat-card-icon,
.queue-icon,
.alert-icon {
  align-items: center;
  display: inline-flex;
  flex: 0 0 auto;
  justify-content: center;
}

.quick-action-icon {
  font-size: 1.1rem;
}

.stat-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(6, minmax(0, 1fr));
}

.stat-card {
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  color: inherit;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: auto minmax(0, 1fr);
  min-height: 7rem;
  padding: 0.9rem;
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.stat-card:hover,
.stat-card:focus {
  border-color: var(--bs-primary);
  box-shadow: 0 0.5rem 1.25rem rgba(var(--bs-primary-rgb), 0.08);
  transform: translateY(-1px);
}

.stat-card-icon {
  border-radius: 0.5rem;
  font-size: 1.25rem;
  height: 2.35rem;
  width: 2.35rem;
}

.stat-card-content {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.stat-card-label,
.stat-card-detail {
  color: var(--bs-secondary-color);
  font-size: 0.78rem;
}

.stat-card strong {
  display: block;
  font-size: 1.8rem;
  line-height: 1.1;
  margin: 0.15rem 0;
}

.stat-card--primary .stat-card-icon {
  background: rgba(var(--bs-primary-rgb), 0.12);
  color: var(--bs-primary);
}

.stat-card--warning .stat-card-icon {
  background: rgba(var(--bs-warning-rgb), 0.16);
  color: var(--bs-warning-text-emphasis, #997404);
}

.stat-card--danger .stat-card-icon {
  background: rgba(var(--bs-danger-rgb), 0.12);
  color: var(--bs-danger);
}

.stat-card--success .stat-card-icon {
  background: rgba(var(--bs-success-rgb), 0.12);
  color: var(--bs-success);
}

.stat-card--info .stat-card-icon {
  background: rgba(var(--bs-info-rgb), 0.14);
  color: var(--bs-info);
}

.stat-card--secondary .stat-card-icon {
  background: rgba(var(--bs-secondary-rgb), 0.12);
  color: var(--bs-secondary);
}

.dashboard-panel {
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.65rem;
  box-shadow: 0 0.75rem 2.5rem rgba(90, 110, 150, 0.08);
  overflow: hidden;
}

.analytics-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 2fr) minmax(280px, 0.8fr);
}

.analytics-card :deep(.card-body) {
  padding-bottom: 0.5rem;
}

.context-chip {
  background: rgba(var(--bs-primary-rgb), 0.09);
  border-radius: 999px;
  color: var(--bs-primary);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.35rem 0.65rem;
  white-space: nowrap;
}

.dashboard-panel :deep(.card-body) {
  padding: 1rem;
}

.panel-header {
  align-items: flex-start;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 0.875rem;
}

.panel-header--compact {
  align-items: center;
}

.panel-link {
  color: var(--bs-primary);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
}

.queue-list,
.alert-list,
.compact-list {
  display: grid;
  gap: 0.5rem;
}

.queue-item,
.compact-item {
  color: inherit;
  text-decoration: none;
}

.queue-item {
  align-items: center;
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  min-height: 4.5rem;
  padding: 0.75rem;
}

.queue-item:hover,
.queue-item:focus,
.compact-item:hover,
.compact-item:focus {
  border-color: var(--bs-primary);
}

.queue-icon {
  background: rgba(var(--bs-primary-rgb), 0.08);
  border-radius: 0.5rem;
  color: var(--bs-primary);
  font-size: 1.25rem;
  height: 2.4rem;
  width: 2.4rem;
}

.queue-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.queue-body strong,
.compact-item strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.queue-body span:last-child,
.compact-item small {
  color: var(--bs-secondary-color);
}

.queue-type {
  color: var(--bs-secondary-color);
  font-size: 0.72rem;
  text-transform: uppercase;
}

.queue-meta {
  align-items: flex-end;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  text-align: right;
}

.queue-meta small,
.alert-time {
  color: var(--bs-secondary-color);
  white-space: nowrap;
}

.compact-item {
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  min-height: 3.5rem;
  padding: 0.65rem 0.75rem;
}

.compact-item span {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.alerts-panel {
  position: sticky;
  top: 1rem;
}

.alert-item {
  align-items: center;
  border: 1px solid var(--bs-border-color);
  border-left-width: 0.25rem;
  border-radius: 0.5rem;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  min-height: 4.35rem;
  padding: 0.7rem 0.75rem;
  transition: background-color 0.15s ease, transform 0.15s ease;
}

.alert-item:hover,
.alert-item:focus {
  background: rgba(var(--bs-primary-rgb), 0.035);
  outline: none;
  transform: translateX(2px);
}

.alert-item--danger {
  border-left-color: var(--bs-danger);
}

.alert-item--warning {
  border-left-color: var(--bs-warning);
}

.alert-item--info {
  border-left-color: var(--bs-info);
}

.alert-icon {
  background: rgba(var(--bs-danger-rgb), 0.08);
  border-radius: 0.5rem;
  color: var(--bs-danger);
  font-size: 1.2rem;
  height: 2.25rem;
  width: 2.25rem;
}

.alert-item--warning .alert-icon {
  background: rgba(var(--bs-warning-rgb), 0.16);
  color: var(--bs-warning-text-emphasis, #997404);
}

.alert-item--info .alert-icon {
  background: rgba(var(--bs-info-rgb), 0.14);
  color: var(--bs-info);
}

.alert-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.alert-title {
  font-weight: 700;
}

.alert-detail {
  color: var(--bs-secondary-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.empty-state {
  align-items: center;
  border: 1px dashed var(--bs-border-color);
  border-radius: 0.5rem;
  color: var(--bs-secondary-color);
  display: flex;
  gap: 0.5rem;
  min-height: 5rem;
  padding: 1rem;
}

.empty-state--compact {
  min-height: 3.75rem;
}

.empty-state i {
  font-size: 1.35rem;
}

.dashboard-table {
  font-size: 0.86rem;
}

.dashboard-table th {
  color: var(--bs-secondary-color);
  font-size: 0.72rem;
  text-transform: uppercase;
  white-space: nowrap;
}

.alert-detail-modal {
  align-items: flex-start;
  display: grid;
  gap: 1rem;
  grid-template-columns: auto minmax(0, 1fr);
}

.modal-alert-icon {
  align-items: center;
  background: rgba(var(--bs-danger-rgb), 0.12);
  border-radius: 0.75rem;
  color: var(--bs-danger);
  display: inline-flex;
  font-size: 1.5rem;
  height: 3rem;
  justify-content: center;
  width: 3rem;
}

.modal-alert-icon--warning {
  background: rgba(var(--bs-warning-rgb), 0.16);
  color: var(--bs-warning-text-emphasis, #997404);
}

.modal-alert-icon--info {
  background: rgba(var(--bs-info-rgb), 0.14);
  color: var(--bs-info);
}

.modal-actions {
  justify-content: flex-end;
}

@media (max-width: 1399.98px) {
  .stat-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .alerts-panel {
    position: static;
  }
}

@media (max-width: 991.98px) {
  .dashboard-heading {
    flex-direction: column;
  }

  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .analytics-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 575.98px) {
  .quick-action-grid,
  .stat-grid {
    grid-template-columns: 1fr;
  }

  .heading-actions,
  .heading-actions .btn {
    width: 100%;
  }

  .queue-item,
  .alert-item {
    align-items: flex-start;
    grid-template-columns: auto minmax(0, 1fr);
  }

  .queue-meta,
  .alert-time {
    grid-column: 2;
    text-align: left;
  }

  .queue-meta {
    align-items: flex-start;
  }

  .panel-header {
    flex-direction: column;
  }
}
</style>
