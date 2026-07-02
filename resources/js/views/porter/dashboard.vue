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
    };
  },
  mounted() {
    this.loadDashboard();
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
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el dashboard.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Portería</h4>
        <div class="text-muted">Panel operativo para búsqueda, retiros, recepciones y trazabilidad.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <router-link to="/porter/students" class="btn btn-outline-primary">Buscar estudiante</router-link>
        <router-link to="/porter/withdrawals" class="btn btn-primary">Registrar retiro</router-link>
        <router-link to="/porter/received-items" class="btn btn-outline-secondary">Recepción de objetos</router-link>
        <router-link to="/porter/goods" class="btn btn-outline-secondary">Mercadería</router-link>
        <router-link to="/porter/visits" class="btn btn-outline-secondary">Visitas</router-link>
        <router-link to="/porter/providers" class="btn btn-outline-secondary">Proveedores</router-link>
        <router-link to="/porter/daily-log" class="btn btn-outline-secondary">Bitácora</router-link>
        <router-link to="/porter/keys" class="btn btn-outline-secondary">Llaves</router-link>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>
    <BCard v-if="loading">
      <LoadingState message="Cargando panel de portería..." compact />
    </BCard>

    <div v-else class="row g-3">
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Retiros del día</div>
          <div class="display-6 fw-semibold">{{ stats.withdrawals_today || 0 }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Pendientes de entrega</div>
          <div class="display-6 fw-semibold">{{ (stats.pending_items || 0) + (stats.pending_goods || 0) }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Retiros observados</div>
          <div class="display-6 fw-semibold">{{ stats.observed_withdrawals || 0 }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Visitas y proveedores activos</div>
          <div class="display-6 fw-semibold">{{ (stats.active_visits || 0) + (stats.active_external_services || 0) }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Llaves fuera de portería</div>
          <div class="display-6 fw-semibold">{{ stats.keys_out || 0 }}</div>
        </BCard>
      </div>
      <div class="col-sm-6 col-xl-3">
        <BCard>
          <div class="text-muted small">Alertas activas</div>
          <div class="display-6 fw-semibold">{{ stats.alerts_total || 0 }}</div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Alertas importantes">
          <div v-if="!alerts.length" class="text-muted">No hay alertas activas.</div>
          <div v-else>
            <div v-for="(alert, index) in alerts" :key="index" class="border rounded p-2 mb-2">
              <div class="d-flex justify-content-between gap-2">
                <div class="fw-semibold">{{ alert.label }}</div>
                <span class="text-muted small">{{ formatDateTime(alert.when) }}</span>
              </div>
              <div class="small text-muted">{{ alert.detail }}</div>
            </div>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Pendientes de entrega">
          <div class="mb-3">
            <div class="fw-semibold mb-2">Objetos o documentos</div>
            <div v-if="!(pendingDeliveries.items || []).length" class="text-muted">Sin pendientes.</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="item in pendingDeliveries.items" :key="`item-${item.id}`" class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.description }}</div>
                  <PorterStatusBadge :value="item.status" :label="item.status" />
                </div>
                <div class="small text-muted">{{ formatDateTime(item.received_at) }}</div>
              </li>
            </ul>
          </div>
          <div>
            <div class="fw-semibold mb-2">Mercadería</div>
            <div v-if="!(pendingDeliveries.goods || []).length" class="text-muted">Sin pendientes.</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="item in pendingDeliveries.goods" :key="`goods-${item.id}`" class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.goods_detail }}</div>
                  <PorterStatusBadge :value="item.status" :label="item.status" />
                </div>
                <div class="small text-muted">{{ formatDateTime(item.moved_at) }}</div>
              </li>
            </ul>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Visitas y proveedores activos">
          <div class="mb-3">
            <div class="fw-semibold mb-2">Visitas</div>
            <div v-if="!(activeControls.visits || []).length" class="text-muted">Sin visitas en curso.</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="item in activeControls.visits" :key="`visit-${item.id}`" class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.visitor_name }} · {{ item.purpose }}</div>
                  <PorterStatusBadge :value="item.status" :label="item.status" />
                </div>
                <div class="small text-muted">{{ formatDateTime(item.entered_at) }}</div>
              </li>
            </ul>
          </div>
          <div>
            <div class="fw-semibold mb-2">Proveedores y servicios</div>
            <div v-if="!(activeControls.external_services || []).length" class="text-muted">Sin ingresos activos.</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="item in activeControls.external_services" :key="`provider-${item.id}`" class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.company_name || item.contact_name }} · {{ item.service_type }}</div>
                  <PorterStatusBadge :value="item.status" :label="item.status" />
                </div>
                <div class="small text-muted">{{ formatDateTime(item.entered_at) }}</div>
              </li>
            </ul>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Llaves y bitácora">
          <div class="mb-3">
            <div class="fw-semibold mb-2">Llaves prestadas</div>
            <div v-if="!(activeControls.key_loans || []).length" class="text-muted">Sin llaves prestadas.</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="item in activeControls.key_loans" :key="`loan-${item.id}`" class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.porter_key?.name || "Llave" }} · {{ item.requester_name }}</div>
                  <PorterStatusBadge :value="item.status" :label="item.status" />
                </div>
                <div class="small text-muted">{{ formatDateTime(item.checked_out_at) }}</div>
              </li>
            </ul>
          </div>
          <div>
            <div class="fw-semibold mb-2">Bitácora destacada de hoy</div>
            <div v-if="!(activeControls.daily_logs || []).length" class="text-muted">Sin registros destacados.</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="item in activeControls.daily_logs" :key="`log-${item.id}`" class="mb-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ item.title }}</div>
                  <PorterStatusBadge :value="item.priority" :label="item.priority" />
                </div>
                <div class="small text-muted">{{ formatDateTime(item.logged_at) }}</div>
              </li>
            </ul>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Retiros del día">
          <div v-if="!withdrawalsToday.length" class="text-muted">No hay retiros registrados hoy.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Estudiante</th>
                  <th>Retira</th>
                  <th>Estado</th>
                  <th>Hora</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in withdrawalsToday" :key="item.id">
                  <td>{{ item.student_full_name_snapshot }}</td>
                  <td>{{ item.person_name }}</td>
                  <td><PorterStatusBadge :value="item.status" :label="item.status" /></td>
                  <td>{{ formatDateTime(item.withdrawn_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Últimos movimientos">
          <div v-if="!recentMovements.length" class="text-muted">Aún no hay movimientos registrados.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Acción</th>
                  <th>Usuario</th>
                  <th>Estado</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in recentMovements" :key="item.id">
                  <td>{{ item.description || item.action }}</td>
                  <td>{{ item.performed_by?.name || "-" }}</td>
                  <td><PorterStatusBadge :value="item.to_status" :label="item.to_status || '-'" /></td>
                  <td>{{ formatDateTime(item.performed_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
