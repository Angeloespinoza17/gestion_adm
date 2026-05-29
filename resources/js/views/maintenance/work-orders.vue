<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => ({
  id: null,
  maintenance_dependency_id: "",
  reported_at: new Date().toISOString().slice(0, 10),
  requested_by: "",
  assigned_to: "",
  priority: "Media",
  status: "Sin comenzar",
  due_date: "",
  description: "",
  resolution_notes: "",
});

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      saving: false,
      search: "",
      statusFilter: "",
      priorityFilter: "",
      workOrders: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      catalogs: {
        priorities: ["Crítico", "Alta", "Media", "Baja"],
        statuses: ["Sin comenzar", "En proceso", "En espera", "Pausado", "Terminado", "Anulado"],
        dependencies: [],
        assignees: [],
        requesters: [],
        summary: {
          total: 0,
          open: 0,
          critical: 0,
          finished: 0,
        },
      },
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadWorkOrders();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/work-orders/catalogs");
      this.catalogs = response.data;
    },
    async loadWorkOrders(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/work-orders", {
          params: {
            page,
            search: this.search,
            status: this.statusFilter,
            priority: this.priorityFilter,
          },
        });

        this.workOrders = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async saveWorkOrder() {
      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = {
          ...this.form,
          maintenance_dependency_id: this.form.maintenance_dependency_id || null,
          reported_at: this.form.reported_at || null,
          due_date: this.form.due_date || null,
        };

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/work-orders/${this.form.id}`, payload)
          : await axios.post("/api/maintenance/work-orders", payload);

        this.success = response.data.message;
        this.resetForm();
        await this.loadCatalogs();
        await this.loadWorkOrders(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    editWorkOrder(workOrder) {
      this.form = {
        ...emptyForm(),
        ...workOrder,
        maintenance_dependency_id: workOrder.maintenance_dependency_id || "",
        reported_at: this.formatInputDate(workOrder.reported_at),
        due_date: this.formatInputDate(workOrder.due_date),
      };
      window.scrollTo({ top: 0, behavior: "smooth" });
    },
    async deleteWorkOrder(workOrder) {
      if (!confirm(`¿Eliminar la OT #${workOrder.id}?`)) return;

      try {
        const response = await axios.delete(`/api/maintenance/work-orders/${workOrder.id}`);
        this.success = response.data.message;
        await this.loadCatalogs();
        await this.loadWorkOrders(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    resetForm() {
      this.form = emptyForm();
    },
    formatInputDate(value) {
      return value ? String(value).slice(0, 10) : "";
    },
    dependencyLabel(dependency) {
      if (!dependency) return "Sin dependencia";

      return `${dependency.code} · ${dependency.name}`;
    },
    workOrderLocation(workOrder) {
      if (workOrder.dependency) {
        return `${workOrder.dependency.code} · ${workOrder.dependency.name}`;
      }

      const location = [
        workOrder.location_code,
        workOrder.location_distribution,
        workOrder.location_sector,
        workOrder.location_name,
        workOrder.location_usage,
      ].filter(Boolean);

      return location.length ? location.join(" · ") : "Sin dependencia";
    },
    priorityClass(priority) {
      return {
        "Crítico": "bg-dark",
        "Alta": "bg-danger",
        "Media": "bg-warning",
        "Baja": "bg-info",
      }[priority] || "bg-secondary";
    },
    statusClass(status) {
      return {
        "Sin comenzar": "bg-secondary",
        "En proceso": "bg-primary",
        "En espera": "bg-warning",
        "Pausado": "bg-warning",
        "Terminado": "bg-success",
        "Anulado": "bg-dark",
      }[status] || "bg-secondary";
    },
    formatError(error) {
      const errors = error.response?.data?.errors;

      if (errors) {
        return Object.values(errors).flat().join(" ");
      }

      return error.response?.data?.message || error.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Órdenes de trabajo</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Gestión de mantención</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BRow>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Total OT</p><h4 class="mb-0">{{ catalogs.summary.total }}</h4></BCardBody></BCard>
      </BCol>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Abiertas</p><h4 class="mb-0">{{ catalogs.summary.open }}</h4></BCardBody></BCard>
      </BCol>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Críticas</p><h4 class="mb-0">{{ catalogs.summary.critical }}</h4></BCardBody></BCard>
      </BCol>
      <BCol md="3">
        <BCard no-body><BCardBody><p class="text-muted mb-1">Terminadas</p><h4 class="mb-0">{{ catalogs.summary.finished }}</h4></BCardBody></BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="4">
        <BCard no-body>
          <BCardBody>
            <h5 class="card-title mb-3">{{ isEditing ? "Editar OT" : "Nueva OT" }}</h5>

            <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
            <BAlert v-if="success" show variant="success">{{ success }}</BAlert>

            <form @submit.prevent="saveWorkOrder">
              <div class="mb-3">
                <label class="form-label">Dependencia</label>
                <select v-model="form.maintenance_dependency_id" class="form-select">
                  <option value="">Sin dependencia</option>
                  <option v-for="dependency in catalogs.dependencies" :key="dependency.id" :value="dependency.id">
                    {{ dependency.code }} · {{ dependency.name }}
                  </option>
                </select>
              </div>

              <BRow>
                <BCol md="6">
                  <div class="mb-3">
                    <label class="form-label">Fecha ingreso</label>
                    <input v-model="form.reported_at" type="date" class="form-control" />
                  </div>
                </BCol>
                <BCol md="6">
                  <div class="mb-3">
                    <label class="form-label">Fecha límite</label>
                    <input v-model="form.due_date" type="date" class="form-control" />
                  </div>
                </BCol>
              </BRow>

              <BRow>
                <BCol md="6">
                  <div class="mb-3">
                    <label class="form-label">Prioridad</label>
                    <select v-model="form.priority" class="form-select" required>
                      <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
                    </select>
                  </div>
                </BCol>
                <BCol md="6">
                  <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select v-model="form.status" class="form-select" required>
                      <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                  </div>
                </BCol>
              </BRow>

              <div class="mb-3">
                <label class="form-label">Quién asigna</label>
                <input v-model="form.requested_by" type="text" class="form-control" list="requesters" />
                <datalist id="requesters">
                  <option v-for="requester in catalogs.requesters" :key="requester" :value="requester" />
                </datalist>
              </div>

              <div class="mb-3">
                <label class="form-label">Asignación</label>
                <input v-model="form.assigned_to" type="text" class="form-control" list="assignees" />
                <datalist id="assignees">
                  <option v-for="assignee in catalogs.assignees" :key="assignee" :value="assignee" />
                </datalist>
              </div>

              <div class="mb-3">
                <label class="form-label">Trabajo solicitado</label>
                <textarea v-model="form.description" class="form-control" rows="4" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Notas de cierre / resolución</label>
                <textarea v-model="form.resolution_notes" class="form-control" rows="3"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" :disabled="saving">
                  {{ saving ? "Guardando..." : isEditing ? "Actualizar OT" : "Crear OT" }}
                </button>
                <button class="btn btn-light" type="button" @click="resetForm">Limpiar</button>
              </div>
            </form>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="8">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <h5 class="card-title mb-0">Listado de OT</h5>
              <div class="d-flex flex-wrap gap-2">
                <input v-model="search" type="search" class="form-control" placeholder="Buscar OT..." @keyup.enter="loadWorkOrders()" />
                <select v-model="priorityFilter" class="form-select">
                  <option value="">Todas las prioridades</option>
                  <option v-for="priority in catalogs.priorities" :key="priority" :value="priority">{{ priority }}</option>
                </select>
                <select v-model="statusFilter" class="form-select">
                  <option value="">Todos los estados</option>
                  <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
                </select>
                <button class="btn btn-outline-primary" type="button" @click="loadWorkOrders()">Filtrar</button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-centered table-nowrap align-middle">
                <thead class="table-light">
                  <tr>
                    <th>OT</th>
                    <th>Dependencia</th>
                    <th>Asignado</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Fecha límite</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="7" class="text-center text-muted py-4">Cargando órdenes...</td>
                  </tr>
                  <tr v-else-if="workOrders.length === 0">
                    <td colspan="7" class="text-center text-muted py-4">No hay órdenes de trabajo registradas.</td>
                  </tr>
                  <tr v-for="workOrder in workOrders" :key="workOrder.id">
                    <td>
                      <strong>#{{ workOrder.id }}</strong>
                      <div class="text-muted text-truncate work-order-description">{{ workOrder.description }}</div>
                    </td>
                    <td>{{ workOrderLocation(workOrder) }}</td>
                    <td>{{ workOrder.assigned_to || "-" }}</td>
                    <td><span class="badge" :class="priorityClass(workOrder.priority)">{{ workOrder.priority }}</span></td>
                    <td><span class="badge" :class="statusClass(workOrder.status)">{{ workOrder.status }}</span></td>
                    <td>{{ workOrder.due_date || "-" }}</td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary me-2" type="button" @click="editWorkOrder(workOrder)">Editar</button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteWorkOrder(workOrder)">Eliminar</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex align-items-center justify-content-between">
              <span class="text-muted">Total: {{ pagination.total }}</span>
              <div class="btn-group">
                <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page <= 1" @click="loadWorkOrders(pagination.current_page - 1)">
                  Anterior
                </button>
                <button class="btn btn-outline-secondary" type="button" disabled>
                  {{ pagination.current_page }} / {{ pagination.last_page }}
                </button>
                <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadWorkOrders(pagination.current_page + 1)">
                  Siguiente
                </button>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.work-order-description {
  max-width: 260px;
}
</style>
