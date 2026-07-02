<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";

const emptyActionForm = () => ({
  comments: "",
  internal_comments: "",
  visible_observations: "",
  internal_observations: "",
  with_pay: null,
  affects_salary: false,
  affects_attendance: true,
  salary_discount_hours: "",
  salary_discount_days: "",
  payroll_status: null,
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      acting: false,
      error: null,
      catalogs: { statuses: [], payroll_statuses: [], replacement_statuses: [], staff: [], types: [] },
      filters: { search: "", status: null, permission_type_id: null },
      items: [],
      selectedRequest: null,
      actionForm: emptyActionForm(),
      replacementItems: [],
    };
  },
  computed: {
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.statuses || []).map((item) => ({ value: item.value, label: item.label })));
    },
    typeOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.types || []).map((item) => ({ value: item.id, label: item.name })));
    },
    payrollStatusOptions() {
      return [{ value: null, label: "Automático" }].concat((this.catalogs.payroll_statuses || []).map((item) => ({ value: item.value, label: item.label })));
    },
    replacementStatusOptions() {
      return (this.catalogs.replacement_statuses || []).map((item) => ({ value: item.value, label: item.label }));
    },
    staffOptions() {
      return [{ value: null, label: "Sin reemplazante" }].concat((this.catalogs.staff || []).map((item) => ({ value: item.id, label: item.full_name })));
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadQueue();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/staff/permissions/catalogs");
      this.catalogs = response.data;
    },
    async loadQueue() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permissions", {
          params: {
            review_queue: true,
            search: this.filters.search || null,
            status: this.filters.status,
            permission_type_id: this.filters.permission_type_id,
          },
        });
        this.items = response.data.data || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openDetail(item) {
      try {
        const response = await axios.get(`/api/staff/permissions/${item.id}`);
        this.selectedRequest = response.data.data;
        this.actionForm = {
          ...emptyActionForm(),
          with_pay: this.selectedRequest.with_pay,
          affects_salary: Boolean(this.selectedRequest.affects_salary),
          affects_attendance: Boolean(this.selectedRequest.affects_attendance),
          payroll_status: this.selectedRequest.payroll_status || null,
        };
        this.replacementItems = (this.selectedRequest.replacements || []).map((item) => ({
          replaced_staff_id: item.replaced_staff_id,
          replacement_staff_id: item.replacement_staff_id,
          course_name: item.course_name || "",
          subject_name: item.subject_name || "",
          dependency_name: item.dependency_name || "",
          schedule_detail: item.schedule_detail || "",
          start_datetime: item.start_datetime ? String(item.start_datetime).slice(0, 16) : "",
          end_datetime: item.end_datetime ? String(item.end_datetime).slice(0, 16) : "",
          status: item.status,
          observations: item.observations || "",
        }));

        if (!this.replacementItems.length && this.selectedRequest.requires_replacement) {
          this.addReplacementRow();
        }
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async act(endpoint) {
      if (!this.selectedRequest) return;
      this.acting = true;
      try {
        await axios.post(`/api/staff/permissions/${this.selectedRequest.id}/${endpoint}`, this.actionForm);
        await this.openDetail(this.selectedRequest);
        await this.loadQueue();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.acting = false;
      }
    },
    async validateDocument(document, validation_status) {
      if (!this.selectedRequest) return;
      try {
        await axios.put(`/api/staff/permissions/documents/${document.id}/validation`, {
          validation_status,
          comments: this.actionForm.internal_comments || null,
        });
        await this.openDetail(this.selectedRequest);
      } catch (error) {
        this.error = this.formatError(error);
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
    addReplacementRow() {
      this.replacementItems.push({
        replaced_staff_id: this.selectedRequest?.staff_id || null,
        replacement_staff_id: null,
        course_name: "",
        subject_name: "",
        dependency_name: "",
        schedule_detail: "",
        start_datetime: "",
        end_datetime: "",
        status: this.replacementStatusOptions[0]?.value || "pendiente",
        observations: "",
      });
    },
    async saveReplacements() {
      if (!this.selectedRequest) return;
      try {
        await axios.put(`/api/staff/permissions/${this.selectedRequest.id}/replacements`, {
          items: this.replacementItems,
        });
        await this.openDetail(this.selectedRequest);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async executeRequest() {
      if (!this.selectedRequest) return;
      try {
        await axios.post(`/api/staff/permissions/${this.selectedRequest.id}/execute`, {
          comments: this.actionForm.comments,
        });
        await this.openDetail(this.selectedRequest);
        await this.loadQueue();
      } catch (error) {
        this.error = this.formatError(error);
      }
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
        <h4 class="mb-0">Bandeja de revisión</h4>
        <div class="text-muted">Revisión por jefatura, Dirección y RRHH con trazabilidad completa.</div>
      </div>
      <BButton variant="outline-primary" @click="loadQueue">Actualizar</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Búsqueda</label>
          <BFormInput v-model="filters.search" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.permission_type_id" :options="typeOptions" :searchable="true" />
        </div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <BButton variant="primary" @click="loadQueue">Filtrar</BButton>
      </div>
    </BCard>

    <BCard title="Pendientes">
      <LoadingState v-if="loading" message="Cargando bandeja..." compact />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Funcionario</th>
              <th>Tipo</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>{{ item.staff?.full_name || "-" }}</td>
              <td>{{ item.permission_type?.name || "-" }}</td>
              <td>{{ item.status }}</td>
              <td>{{ item.start_date }}</td>
              <td class="text-end">
                <BButton size="sm" variant="outline-primary" @click="openDetail(item)">Revisar</BButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BCard v-if="selectedRequest" class="mt-3" title="Detalle y resolución">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><span class="text-muted">Funcionario:</span> {{ selectedRequest.staff?.full_name || "-" }}</div>
        <div class="col-md-4"><span class="text-muted">Tipo:</span> {{ selectedRequest.permission_type?.name || "-" }}</div>
        <div class="col-md-4"><span class="text-muted">Estado:</span> {{ selectedRequest.status }}</div>
        <div class="col-12"><span class="text-muted">Motivo:</span> {{ selectedRequest.reason }}</div>
        <div class="col-12"><span class="text-muted">Descripción:</span> {{ selectedRequest.description || "-" }}</div>
      </div>

      <div class="mb-3">
        <h6 class="mb-2">Quiénes deben enterarse</h6>
        <div v-if="!(selectedRequest.watchers || []).length" class="text-muted">
          No hay destinatarios configurados para esta solicitud.
        </div>
        <div v-else class="table-responsive">
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
      </div>

      <div class="row g-3">
        <div class="col-lg-6">
          <label class="form-label">Comentario visible</label>
          <BFormTextarea v-model="actionForm.comments" rows="2" />
        </div>
        <div class="col-lg-6">
          <label class="form-label">Comentario interno</label>
          <BFormTextarea v-model="actionForm.internal_comments" rows="2" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Con goce</label>
          <Multiselect v-model="actionForm.with_pay" :options="[
            { value: null, label: 'Sin cambio' },
            { value: true, label: 'Sí' },
            { value: false, label: 'No' }
          ]" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado remuneración</label>
          <Multiselect v-model="actionForm.payroll_status" :options="payrollStatusOptions" :searchable="true" />
        </div>
        <div class="col-md-3"><BFormCheckbox v-model="actionForm.affects_salary">Afecta remuneración</BFormCheckbox></div>
        <div class="col-md-3"><BFormCheckbox v-model="actionForm.affects_attendance">Afecta asistencia</BFormCheckbox></div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <BButton variant="success" :disabled="acting" @click="act('approve')">Aprobar</BButton>
        <BButton variant="warning" :disabled="acting" @click="act('observe')">Observar</BButton>
        <BButton variant="danger" :disabled="acting" @click="act('reject')">Rechazar</BButton>
        <BButton v-if="selectedRequest.status === 'aprobado'" variant="outline-primary" :disabled="acting" @click="executeRequest">Marcar ejecutado</BButton>
      </div>

      <hr />
      <h6>Documentos</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Archivo</th>
              <th>Estado</th>
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
                <div class="btn-group btn-group-sm">
                  <BButton variant="outline-primary" @click="downloadDocument(document)">Descargar</BButton>
                  <BButton variant="outline-success" @click="validateDocument(document, 'validado')">Validar</BButton>
                  <BButton variant="outline-danger" @click="validateDocument(document, 'rechazado')">Rechazar</BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="selectedRequest.requires_replacement">
        <hr />
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Reemplazos</h6>
          <BButton size="sm" variant="outline-secondary" @click="addReplacementRow">Agregar fila</BButton>
        </div>
        <div class="vstack gap-3">
          <BCard v-for="(item, index) in replacementItems" :key="index" body-class="p-3">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Reemplazante</label>
                <Multiselect v-model="item.replacement_staff_id" :options="staffOptions" :searchable="true" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Curso</label>
                <BFormInput v-model="item.course_name" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Asignatura</label>
                <BFormInput v-model="item.subject_name" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Dependencia</label>
                <BFormInput v-model="item.dependency_name" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Inicio</label>
                <BFormInput v-model="item.start_datetime" type="datetime-local" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Término</label>
                <BFormInput v-model="item.end_datetime" type="datetime-local" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Estado</label>
                <Multiselect v-model="item.status" :options="replacementStatusOptions" :searchable="true" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Horario</label>
                <BFormInput v-model="item.schedule_detail" />
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <BFormTextarea v-model="item.observations" rows="2" />
              </div>
            </div>
          </BCard>
        </div>
        <BButton variant="outline-primary" class="mt-3" @click="saveReplacements">Guardar reemplazos</BButton>
      </div>
    </BCard>
  </Layout>
</template>
