<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyForm = () => ({
  id: null,
  title: "",
  body: "",
  category: "General",
  priority: "normal",
  status: "draft",
  pinned: false,
  audience_all: true,
  requires_ack: false,
  published_at: "",
  expires_at: "",
  role_ids: [],
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      search: "",
      statusFilter: "",
      priorityFilter: "",
      roleFilter: "",
      items: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      catalogs: {
        statuses: [],
        priorities: [],
        roles: [],
        categories: [],
        stats: {},
        capabilities: {},
      },
      form: emptyForm(),
      showModal: false,
      error: null,
      success: null,
    };
  },
  computed: {
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage);
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    statusOptions() {
      return [{ value: "", text: "Todos" }].concat(
        (this.catalogs.statuses || []).map((status) => ({ value: status.value, text: status.label }))
      );
    },
    priorityOptions() {
      return [{ value: "", text: "Todas" }].concat(
        (this.catalogs.priorities || []).map((priority) => ({ value: priority.value, text: priority.label }))
      );
    },
    formStatusOptions() {
      return (this.catalogs.statuses || []).map((status) => ({ value: status.value, text: status.label }));
    },
    formPriorityOptions() {
      return (this.catalogs.priorities || []).map((priority) => ({ value: priority.value, text: priority.label }));
    },
    roleOptions() {
      return (this.catalogs.roles || []).map((role) => ({ value: role.id, text: role.name }));
    },
    roleFilterOptions() {
      return [{ value: "", text: "Todos los roles" }].concat(this.roleOptions);
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/internal-communications/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/internal-communications", {
          params: {
            page,
            search: this.search || null,
            status: this.statusFilter || null,
            priority: this.priorityFilter || null,
            role_id: this.roleFilter || null,
          },
        });

        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.search = "";
      this.statusFilter = "";
      this.priorityFilter = "";
      this.roleFilter = "";
      this.load();
    },
    openCreate() {
      if (!this.canManage) return;

      this.form = emptyForm();
      this.error = null;
      this.success = null;
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        title: item.title || "",
        body: item.body || "",
        category: item.category || "General",
        priority: item.priority || "normal",
        status: item.status || "draft",
        pinned: Boolean(item.pinned),
        audience_all: Boolean(item.audience_all),
        requires_ack: Boolean(item.requires_ack),
        published_at: this.toDatetimeLocal(item.published_at),
        expires_at: this.toDatetimeLocal(item.expires_at),
        role_ids: (item.roles || []).map((role) => role.id),
      };
      this.error = null;
      this.success = null;
      this.showModal = true;
    },
    payload() {
      return {
        title: this.form.title,
        body: this.form.body,
        category: this.form.category,
        priority: this.form.priority,
        status: this.form.status,
        pinned: Boolean(this.form.pinned),
        audience_all: Boolean(this.form.audience_all),
        requires_ack: Boolean(this.form.requires_ack),
        published_at: this.form.published_at || null,
        expires_at: this.form.expires_at || null,
        role_ids: this.form.audience_all ? [] : this.form.role_ids,
      };
    },
    async save() {
      if (!this.canManage) return;

      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        if (this.isEditing) {
          await axios.put(`/api/internal-communications/${this.form.id}`, this.payload());
          this.success = "Comunicacion actualizada correctamente.";
        } else {
          await axios.post("/api/internal-communications", this.payload());
          this.success = "Comunicacion creada correctamente.";
        }

        this.showModal = false;
        this.loadCatalogs();
        this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      if (!this.canManage) return;

      const result = await Swal.fire({
        title: "Eliminar comunicacion",
        text: `Se eliminara "${item.title}".`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#74788d",
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/internal-communications/${item.id}`);
        this.success = "Comunicacion eliminada correctamente.";
        this.loadCatalogs();
        this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    audienceText(item) {
      if (item.audience_all) return "Todos";

      const names = (item.roles || []).map((role) => role.name);
      return names.length ? names.join(", ") : "Sin destinatarios";
    },
    statusLabel(value) {
      return (this.catalogs.statuses || []).find((status) => status.value === value)?.label || value;
    },
    priorityLabel(value) {
      return (this.catalogs.priorities || []).find((priority) => priority.value === value)?.label || value;
    },
    statusClass(status) {
      return {
        published: "bg-success-subtle text-success",
        draft: "bg-secondary-subtle text-secondary",
        archived: "bg-dark-subtle text-dark",
      }[status] || "bg-secondary-subtle text-secondary";
    },
    priorityClass(priority) {
      return {
        urgent: "bg-danger-subtle text-danger",
        important: "bg-warning-subtle text-warning",
        normal: "bg-info-subtle text-info",
      }[priority] || "bg-secondary-subtle text-secondary";
    },
    toDatetimeLocal(value) {
      if (!value) return "";

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";

      const pad = (number) => String(number).padStart(2, "0");
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    },
    formatDateTime(value) {
      if (!value) return "-";

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value));
    },
    formatError(error) {
      const validation = error?.response?.data?.errors;
      if (validation) {
        return Object.values(validation).flat().join(" ");
      }

      return error?.response?.data?.message || error?.message || "No se pudo completar la operacion.";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="communications-page">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
          <div class="text-muted text-uppercase fw-semibold small">Comunicaciones</div>
          <h1 class="h3 mb-1">Comunicaciones internas</h1>
          <p class="text-muted mb-0">Avisos institucionales visibles en Inicio segun los roles destinatarios.</p>
        </div>
        <BButton v-if="canManage" variant="primary" @click="openCreate">
          <i class="bx bx-plus me-1"></i>
          Nuevo aviso
        </BButton>
      </div>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

      <BRow class="g-3 mb-4">
        <BCol sm="6" xl="3">
          <BCard no-body class="communications-stat h-100">
            <BCardBody>
              <span>Total</span>
              <strong>{{ catalogs.stats?.total || 0 }}</strong>
            </BCardBody>
          </BCard>
        </BCol>
        <BCol sm="6" xl="3">
          <BCard no-body class="communications-stat h-100">
            <BCardBody>
              <span>Publicadas</span>
              <strong>{{ catalogs.stats?.published || 0 }}</strong>
            </BCardBody>
          </BCard>
        </BCol>
        <BCol sm="6" xl="3">
          <BCard no-body class="communications-stat h-100">
            <BCardBody>
              <span>Vigentes</span>
              <strong>{{ catalogs.stats?.active || 0 }}</strong>
            </BCardBody>
          </BCard>
        </BCol>
        <BCol sm="6" xl="3">
          <BCard no-body class="communications-stat h-100">
            <BCardBody>
              <span>Borradores</span>
              <strong>{{ catalogs.stats?.draft || 0 }}</strong>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>

      <BCard no-body class="border-0 shadow-sm mb-4">
        <BCardBody>
          <BRow class="g-3 align-items-end">
            <BCol lg="4">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="search" placeholder="Titulo, texto o categoria" @keyup.enter="load()" />
            </BCol>
            <BCol md="4" lg="2">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="statusFilter" :options="statusOptions" />
            </BCol>
            <BCol md="4" lg="2">
              <label class="form-label">Prioridad</label>
              <BFormSelect v-model="priorityFilter" :options="priorityOptions" />
            </BCol>
            <BCol md="4" lg="2">
              <label class="form-label">Rol</label>
              <BFormSelect v-model="roleFilter" :options="roleFilterOptions" />
            </BCol>
            <BCol lg="2" class="d-flex gap-2">
              <BButton variant="primary" class="flex-fill" @click="load()">Filtrar</BButton>
              <BButton variant="outline-secondary" @click="resetFilters">
                <i class="bx bx-reset"></i>
              </BButton>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <LoadingState v-if="loading" message="Cargando comunicaciones..." />

      <BCard v-else no-body class="border-0 shadow-sm">
        <BCardBody>
          <div v-if="items.length" class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th>Aviso</th>
                  <th>Destinatarios</th>
                  <th>Estado</th>
                  <th>Prioridad</th>
                  <th>Lecturas</th>
                  <th>Vigencia</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td>
                    <div class="fw-semibold">
                      <i v-if="item.pinned" class="bx bxs-pin text-primary me-1"></i>
                      {{ item.title }}
                    </div>
                    <div class="text-muted small">{{ item.category || "Sin categoria" }}</div>
                  </td>
                  <td class="communications-audience">{{ audienceText(item) }}</td>
                  <td><span class="badge" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span></td>
                  <td><span class="badge" :class="priorityClass(item.priority)">{{ priorityLabel(item.priority) }}</span></td>
                  <td>
                    <div class="small">{{ item.read_count || 0 }} leidos</div>
                    <div v-if="item.requires_ack" class="text-muted small">{{ item.acknowledged_count || 0 }} confirmados</div>
                  </td>
                  <td>
                    <div class="small">{{ formatDateTime(item.published_at) }}</div>
                    <div class="text-muted small">hasta {{ formatDateTime(item.expires_at) }}</div>
                  </td>
                  <td class="text-end">
                    <BButton size="sm" variant="outline-primary" class="me-2" @click="openEdit(item)">
                      <i class="bx bx-show"></i>
                    </BButton>
                    <BButton v-if="canManage" size="sm" variant="outline-danger" @click="remove(item)">
                      <i class="bx bx-trash"></i>
                    </BButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="communications-empty">No hay comunicaciones para los filtros seleccionados.</div>
        </BCardBody>

        <BCardFooter v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center">
          <span class="text-muted small">Pagina {{ pagination.current_page }} de {{ pagination.last_page }} · {{ pagination.total }} registros</span>
          <div class="d-flex gap-2">
            <BButton size="sm" variant="outline-secondary" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</BButton>
            <BButton size="sm" variant="outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</BButton>
          </div>
        </BCardFooter>
      </BCard>

      <BModal v-model="showModal" :title="isEditing ? 'Editar comunicacion' : 'Nueva comunicacion'" size="lg" hide-footer>
        <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

        <form @submit.prevent="save">
          <BRow class="g-3">
            <BCol lg="8">
              <label class="form-label">Titulo</label>
              <BFormInput v-model="form.title" :disabled="!canManage" required maxlength="191" />
            </BCol>
            <BCol lg="4">
              <label class="form-label">Categoria</label>
              <BFormInput v-model="form.category" :disabled="!canManage" maxlength="80" />
            </BCol>
            <BCol cols="12">
              <label class="form-label">Mensaje</label>
              <BFormTextarea v-model="form.body" :disabled="!canManage" rows="6" max-rows="12" required />
            </BCol>
            <BCol md="6">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="form.status" :disabled="!canManage" :options="formStatusOptions" />
            </BCol>
            <BCol md="6">
              <label class="form-label">Prioridad</label>
              <BFormSelect v-model="form.priority" :disabled="!canManage" :options="formPriorityOptions" />
            </BCol>
            <BCol md="6">
              <label class="form-label">Publicacion</label>
              <BFormInput v-model="form.published_at" :disabled="!canManage" type="datetime-local" />
            </BCol>
            <BCol md="6">
              <label class="form-label">Vencimiento</label>
              <BFormInput v-model="form.expires_at" :disabled="!canManage" type="datetime-local" />
            </BCol>
            <BCol cols="12">
              <div class="d-flex flex-wrap gap-3">
                <BFormCheckbox v-model="form.pinned" :disabled="!canManage">Fijar arriba</BFormCheckbox>
                <BFormCheckbox v-model="form.requires_ack" :disabled="!canManage">Requiere confirmacion</BFormCheckbox>
                <BFormCheckbox v-model="form.audience_all" :disabled="!canManage">Todos los roles</BFormCheckbox>
              </div>
            </BCol>
            <BCol cols="12">
              <label class="form-label">Roles destinatarios</label>
              <BFormSelect v-model="form.role_ids" :disabled="!canManage || form.audience_all" :options="roleOptions" multiple :select-size="8" />
            </BCol>
          </BRow>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <BButton variant="outline-secondary" type="button" @click="showModal = false">Cerrar</BButton>
            <BButton v-if="canManage" variant="primary" type="submit" :disabled="saving">
              <i class="bx bx-save me-1"></i>
              {{ saving ? "Guardando..." : "Guardar" }}
            </BButton>
          </div>
        </form>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.communications-page {
  color: #2f3542;
}

.communications-stat {
  border: 0;
  box-shadow: 0 0.125rem 0.35rem rgba(18, 38, 63, 0.08);
}

.communications-stat span,
.communications-stat strong {
  display: block;
}

.communications-stat span {
  color: #74788d;
  font-size: 0.82rem;
}

.communications-stat strong {
  color: #212529;
  font-size: 1.6rem;
  line-height: 1.1;
  margin-top: 0.25rem;
}

.communications-audience {
  max-width: 260px;
}

.communications-empty {
  background: #f8f9fa;
  border: 1px dashed #d7dde6;
  border-radius: 8px;
  color: #74788d;
  padding: 1.5rem;
  text-align: center;
}
</style>
