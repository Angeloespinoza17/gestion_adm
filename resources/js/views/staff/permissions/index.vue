<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  staff_id: null,
  permission_type_id: null,
  department_ids: [],
  direct_manager_user_id: null,
  start_date: "",
  end_date: "",
  start_time: "",
  end_time: "",
  is_half_day: false,
  with_pay: null,
  reason: "",
  description: "",
  employee_observations: "",
  urgency: false,
  retroactive: false,
  requires_replacement: false,
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      uploading: false,
      error: null,
      catalogs: { staff: [], departments: [], types: [], statuses: [], current_user: {} },
      filters: {
        search: "",
        status: null,
        permission_type_id: null,
      },
      items: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      form: emptyForm(),
      editingId: null,
      selectedRequest: null,
      documentFile: null,
      documentComments: "",
    };
  },
  computed: {
    currentStaffId() {
      return this.catalogs.current_user?.staff_id || null;
    },
    typeOptions() {
      return [{ value: null, label: "Selecciona un tipo" }].concat((this.catalogs.types || []).map((item) => ({ value: item.id, label: item.name })));
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.statuses || []).map((item) => ({ value: item.value, label: item.label })));
    },
    departmentOptions() {
      return (this.catalogs.departments || []).map((item) => ({ value: item.id, label: item.name }));
    },
    currentType() {
      return (this.catalogs.types || []).find((item) => item.id === this.form.permission_type_id) || null;
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadRequests();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/staff/permissions/catalogs");
      this.catalogs = response.data;
      if (!this.form.staff_id) {
        this.form.staff_id = this.currentStaffId;
      }
    },
    async loadRequests(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permissions", {
          params: {
            page,
            mine_only: true,
            search: this.filters.search || null,
            status: this.filters.status,
            permission_type_id: this.filters.permission_type_id,
          },
        });
        this.items = response.data.data || [];
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
    async showDetail(item) {
      try {
        const response = await axios.get(`/api/staff/permissions/${item.id}`);
        this.selectedRequest = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    newRequest() {
      this.editingId = null;
      this.form = { ...emptyForm(), staff_id: this.currentStaffId };
      this.selectedRequest = null;
    },
    async editRequest(item) {
      await this.showDetail(item);
      this.editingId = item.id;
      this.form = {
        staff_id: this.selectedRequest.staff_id,
        permission_type_id: this.selectedRequest.permission_type_id,
        department_ids: (this.selectedRequest.departments || []).map((entry) => entry.id),
        direct_manager_user_id: this.selectedRequest.direct_manager_user_id,
        start_date: this.selectedRequest.start_date || "",
        end_date: this.selectedRequest.end_date || "",
        start_time: this.selectedRequest.start_time ? String(this.selectedRequest.start_time).slice(0, 5) : "",
        end_time: this.selectedRequest.end_time ? String(this.selectedRequest.end_time).slice(0, 5) : "",
        is_half_day: Boolean(this.selectedRequest.is_half_day),
        with_pay: this.selectedRequest.with_pay,
        reason: this.selectedRequest.reason || "",
        description: this.selectedRequest.description || "",
        employee_observations: this.selectedRequest.employee_observations || "",
        urgency: Boolean(this.selectedRequest.urgency),
        retroactive: Boolean(this.selectedRequest.retroactive),
        requires_replacement: Boolean(this.selectedRequest.requires_replacement),
      };
    },
    async save(submit = false) {
      this.saving = true;
      this.error = null;
      try {
        const payload = { ...this.form, submit };
        if (this.editingId) {
          await axios.put(`/api/staff/permissions/${this.editingId}`, payload);
        } else {
          await axios.post("/api/staff/permissions", payload);
        }
        this.newRequest();
        await this.loadRequests(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async cancelRequest(item) {
      const result = await Swal.fire({
        title: "Cancelar solicitud",
        text: item.reason,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Cancelar solicitud",
        cancelButtonText: "Cerrar",
      });

      if (!result.isConfirmed) return;

      await axios.post(`/api/staff/permissions/${item.id}/cancel`);
      if (this.selectedRequest?.id === item.id) {
        await this.showDetail(item);
      }
      await this.loadRequests(this.pagination.current_page);
    },
    onDocument(event) {
      this.documentFile = event?.target?.files?.[0] || null;
    },
    async uploadDocument() {
      if (!this.selectedRequest || !this.documentFile) return;

      this.uploading = true;
      const formData = new FormData();
      formData.append("document", this.documentFile);
      if (this.documentComments) {
        formData.append("comments", this.documentComments);
      }

      try {
        await axios.post(`/api/staff/permissions/${this.selectedRequest.id}/documents`, formData);
        this.documentFile = null;
        this.documentComments = "";
        await this.showDetail(this.selectedRequest);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.uploading = false;
      }
    },
    async downloadDocument(document) {
      try {
        const response = await axios.get(`/api/staff/permissions/documents/${document.id}/download`, {
          responseType: "blob",
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = document.file_name || "documento";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    canEdit(item) {
      return ["borrador", "ingresado", "observado"].includes(item.status);
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Mis permisos</h4>
        <div class="text-muted">Solicita, adjunta respaldos y consulta el estado de tus permisos.</div>
      </div>
      <BButton variant="primary" @click="newRequest">Nueva solicitud</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BCard class="mb-3" title="Solicitud">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Tipo de permiso</label>
          <Multiselect v-model="form.permission_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Departamentos</label>
          <Multiselect v-model="form.department_ids" :options="departmentOptions" mode="tags" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha inicio</label>
          <BFormInput v-model="form.start_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha término</label>
          <BFormInput v-model="form.end_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hora inicio</label>
          <BFormInput v-model="form.start_time" type="time" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hora término</label>
          <BFormInput v-model="form.end_time" type="time" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Con goce</label>
          <Multiselect v-model="form.with_pay" :options="[
            { value: null, label: 'Por definir' },
            { value: true, label: 'Sí' },
            { value: false, label: 'No' }
          ]" />
        </div>
        <div class="col-md-9">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="form.reason" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones del funcionario</label>
          <BFormTextarea v-model="form.employee_observations" rows="2" />
        </div>
        <div class="col-md-3"><BFormCheckbox v-model="form.is_half_day">Media jornada</BFormCheckbox></div>
        <div class="col-md-3"><BFormCheckbox v-model="form.urgency">Urgencia</BFormCheckbox></div>
        <div class="col-md-3"><BFormCheckbox v-model="form.retroactive">Retroactivo</BFormCheckbox></div>
        <div class="col-md-3"><BFormCheckbox v-model="form.requires_replacement">Requiere reemplazo</BFormCheckbox></div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <BButton variant="outline-secondary" :disabled="saving" @click="save(false)">Guardar borrador</BButton>
        <BButton variant="primary" :disabled="saving" @click="save(true)">{{ saving ? "Enviando..." : "Guardar y enviar" }}</BButton>
      </div>
    </BCard>

    <BCard class="mb-3" title="Mis solicitudes">
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label">Búsqueda</label>
          <BFormInput v-model="filters.search" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.permission_type_id" :options="typeOptions" :searchable="true" />
        </div>
      </div>
      <div class="d-flex gap-2 mb-3">
        <BButton variant="primary" @click="loadRequests(1)">Filtrar</BButton>
        <BButton variant="outline-secondary" @click="filters = { search: '', status: null, permission_type_id: null }; loadRequests(1)">Limpiar</BButton>
      </div>

      <LoadingState v-if="loading" message="Cargando solicitudes..." compact />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Periodo</th>
              <th>Duración</th>
              <th>Estado</th>
              <th>Con goce</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>{{ item.permission_type?.name || "-" }}</td>
              <td>{{ item.start_date }} - {{ item.end_date }}</td>
              <td>{{ item.duration_label || "-" }}</td>
              <td>{{ item.status }}</td>
              <td>{{ item.with_pay === null ? "Pendiente" : item.with_pay ? "Sí" : "No" }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <BButton variant="outline-primary" @click="showDetail(item)">Ver</BButton>
                  <BButton v-if="canEdit(item)" variant="outline-secondary" @click="editRequest(item)">Editar</BButton>
                  <BButton v-if="item.status !== 'cancelado' && item.status !== 'rechazado' && item.status !== 'ejecutado'" variant="outline-danger" @click="cancelRequest(item)">Cancelar</BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BCard v-if="selectedRequest" title="Detalle de solicitud">
      <div class="row g-3">
        <div class="col-md-4"><span class="text-muted">Tipo:</span> {{ selectedRequest.permission_type?.name || "-" }}</div>
        <div class="col-md-4"><span class="text-muted">Estado:</span> {{ selectedRequest.status }}</div>
        <div class="col-md-4"><span class="text-muted">Duración:</span> {{ selectedRequest.duration_label || "-" }}</div>
        <div class="col-12"><span class="text-muted">Motivo:</span> {{ selectedRequest.reason }}</div>
        <div class="col-12"><span class="text-muted">Descripción:</span> {{ selectedRequest.description || "-" }}</div>
        <div class="col-12"><span class="text-muted">Observaciones visibles:</span> {{ selectedRequest.visible_observations || "-" }}</div>
      </div>

      <hr />
      <h6>Quiénes deben enterarse</h6>
      <div v-if="!(selectedRequest.watchers || []).length" class="text-muted mb-3">
        No hay destinatarios configurados para esta solicitud.
      </div>
      <div v-else class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Destinatario</th>
              <th>Origen</th>
              <th>Aviso</th>
              <th>Puede ver</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="watcher in selectedRequest.watchers || []" :key="watcher.id">
              <td>{{ watcher.user?.name || "-" }}<span v-if="watcher.user?.email" class="text-muted"> · {{ watcher.user.email }}</span></td>
              <td>{{ watcher.source_label || watcher.source_type || "-" }}</td>
              <td>{{ watcher.notify ? "Sí" : "No" }}</td>
              <td>{{ watcher.can_view ? "Sí" : "No" }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <hr />
      <h6>Documentos</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Archivo</th>
              <th>Validación</th>
              <th>Comentarios</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="document in selectedRequest.documents || []" :key="document.id">
              <td>{{ document.file_name }}</td>
              <td>{{ document.validation_status }}</td>
              <td>{{ document.comments || "-" }}</td>
              <td class="text-end">
                <BButton size="sm" variant="outline-primary" @click="downloadDocument(document)">Descargar</BButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Adjuntar documento</label>
          <input class="form-control" type="file" @change="onDocument" />
        </div>
        <div class="col-md-5">
          <label class="form-label">Comentario</label>
          <BFormInput v-model="documentComments" />
        </div>
        <div class="col-md-2">
          <BButton variant="outline-primary" class="w-100" :disabled="uploading" @click="uploadDocument">
            {{ uploading ? "Subiendo..." : "Subir" }}
          </BButton>
        </div>
      </div>

      <hr />
      <h6>Historial</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Acción</th>
              <th>Usuario</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in selectedRequest.logs || []" :key="log.id">
              <td>{{ log.created_at }}</td>
              <td>{{ log.action }}</td>
              <td>{{ log.user?.name || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>
  </Layout>
</template>
