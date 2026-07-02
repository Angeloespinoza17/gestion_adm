<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      error: null,
      stats: {
        pending: 0,
        approved_month: 0,
        rejected_month: 0,
        without_pay: 0,
        requires_replacement: 0,
        upcoming: 0,
      },
      recentPending: [],
      upcomingPermissions: [],
      topStaff: [],
      topDepartments: [],
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
        const response = await axios.get("/api/staff/permissions/dashboard");
        this.stats = response.data.stats || this.stats;
        this.recentPending = response.data.recent_pending || [];
        this.upcomingPermissions = response.data.upcoming_permissions || [];
        this.topStaff = response.data.top_staff || [];
        this.topDepartments = response.data.top_departments || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).split(" ")[0].split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el dashboard.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Dashboard de permisos</h4>
        <div class="text-muted">Indicadores operativos y próximos permisos del personal.</div>
      </div>
      <BButton variant="outline-primary" @click="loadDashboard">Actualizar</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>
    <BCard v-if="loading">Cargando dashboard...</BCard>

    <div v-else class="row g-3">
      <div class="col-md-4 col-xl-2" v-for="(label, key) in {
        pending: 'Pendientes',
        approved_month: 'Aprobados del mes',
        rejected_month: 'Rechazados del mes',
        without_pay: 'Sin goce',
        requires_replacement: 'Con reemplazo',
        upcoming: 'Próximos'
      }" :key="key">
        <BCard>
          <div class="text-muted small">{{ label }}</div>
          <div class="display-6 fw-semibold">{{ stats[key] ?? 0 }}</div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Solicitudes pendientes de revisión">
          <div v-if="!recentPending.length" class="text-muted">No hay solicitudes pendientes.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th>Inicio</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in recentPending" :key="item.id">
                  <td>{{ item.staff?.full_name || "-" }}</td>
                  <td>{{ item.permission_type?.name || "-" }}</td>
                  <td>{{ item.status }}</td>
                  <td>{{ formatDate(item.start_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Permisos próximos">
          <div v-if="!upcomingPermissions.length" class="text-muted">No hay permisos próximos.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th>Tipo</th>
                  <th>Inicio</th>
                  <th>Término</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in upcomingPermissions" :key="item.id">
                  <td>{{ item.staff?.full_name || "-" }}</td>
                  <td>{{ item.permission_type?.name || "-" }}</td>
                  <td>{{ formatDate(item.start_date) }}</td>
                  <td>{{ formatDate(item.end_date) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Funcionarios con más solicitudes">
          <div v-if="!topStaff.length" class="text-muted">Sin datos para mostrar.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th class="text-end">Solicitudes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in topStaff" :key="item.staff_id">
                  <td>{{ item.full_name }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-6">
        <BCard title="Departamentos con más solicitudes">
          <div v-if="!topDepartments.length" class="text-muted">Sin datos para mostrar.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Departamento</th>
                  <th class="text-end">Solicitudes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in topDepartments" :key="item.id">
                  <td>{{ item.name }}</td>
                  <td class="text-end">{{ item.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
