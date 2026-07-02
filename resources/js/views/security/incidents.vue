<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: {
        priorities: [],
        incident_statuses: [],
        responsible_users: [],
        capabilities: {},
      },
      filters: {
        search: "",
        priority: null,
        status_id: null,
        responsible_user_id: null,
        from: "",
        to: "",
        pending_only: true,
      },
      incidents: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      selectedIncident: null,
      updateForm: {
        priority: "media",
        status_id: null,
        current_responsible_user_id: null,
        assignee_user_ids: [],
        response_due_at: "",
        response_summary: "",
        closure_evidence_notes: "",
        comment: "",
        evidenceFiles: [],
      },
    };
  },
  computed: {
    priorityOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.priorities || []).map((item) => ({ value: item.value, label: item.label }))
      );
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.incident_statuses || []).map((item) => ({ value: item.id, label: item.name }))
      );
    },
    responsibleOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.responsible_users || []).map((item) => ({
          value: item.id,
          label: item.staff?.full_name ? `${item.staff.full_name} (${item.name})` : item.name,
        }))
      );
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadIncidents();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/security/catalogs");
      this.catalogs = response.data;
    },
    async loadIncidents(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/security/incidents", {
          params: { page, ...this.filters },
        });
        this.incidents = response.data.data || [];
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
    async openIncident(incident) {
      const response = await axios.get(`/api/security/incidents/${incident.id}`);
      this.selectedIncident = response.data.data;
      this.updateForm = {
        priority: this.selectedIncident.priority,
        status_id: this.selectedIncident.status_id,
        current_responsible_user_id: this.selectedIncident.current_responsible_user_id,
        assignee_user_ids: (this.selectedIncident.assignments || []).filter((item) => item.is_current !== false).map((item) => item.user_id || item.user?.id),
        response_due_at: this.toInputDateTime(this.selectedIncident.response_due_at),
        response_summary: this.selectedIncident.response_summary || "",
        closure_evidence_notes: this.selectedIncident.closure_evidence_notes || "",
        comment: "",
        evidenceFiles: [],
      };
    },
    onEvidenceChange(event) {
      this.updateForm.evidenceFiles = Array.from(event.target.files || []);
    },
    async saveIncident() {
      if (!this.selectedIncident) return;
      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("priority", this.updateForm.priority);
        if (this.updateForm.status_id) formData.append("status_id", this.updateForm.status_id);
        if (this.updateForm.current_responsible_user_id) formData.append("current_responsible_user_id", this.updateForm.current_responsible_user_id);
        if (this.updateForm.response_due_at) formData.append("response_due_at", this.updateForm.response_due_at);
        if (this.updateForm.response_summary) formData.append("response_summary", this.updateForm.response_summary);
        if (this.updateForm.closure_evidence_notes) formData.append("closure_evidence_notes", this.updateForm.closure_evidence_notes);
        if (this.updateForm.comment) formData.append("comment", this.updateForm.comment);
        this.ensureArray(this.updateForm.assignee_user_ids).forEach((id) => formData.append("assignee_user_ids[]", id));
        (this.updateForm.evidenceFiles || []).forEach((file) => formData.append("evidence_files[]", file));
        formData.append("_method", "PUT");

        await axios.post(`/api/security/incidents/${this.selectedIncident.id}`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        await this.openIncident(this.selectedIncident);
        await this.loadIncidents(this.pagination.current_page);
        await this.showSuccess("Novedad actualizada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async saveFollowUp() {
      if (!this.selectedIncident || !this.updateForm.comment) {
        this.error = "Debes ingresar un comentario de seguimiento.";
        await this.showWarning(this.error);
        return;
      }

      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("comment", this.updateForm.comment);
        if (this.updateForm.status_id) formData.append("status_id", this.updateForm.status_id);
        if (this.updateForm.current_responsible_user_id) formData.append("assigned_to_user_id", this.updateForm.current_responsible_user_id);
        (this.updateForm.evidenceFiles || []).forEach((file) => formData.append("evidence_files[]", file));

        await axios.post(`/api/security/incidents/${this.selectedIncident.id}/comments`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        await this.openIncident(this.selectedIncident);
        await this.loadIncidents(this.pagination.current_page);
        await this.showSuccess("Seguimiento registrado correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    exportPdf() {
      const pdfMake = getPdfMake();
      pdfMake.createPdf({
        pageOrientation: "landscape",
        content: [
          { text: "Novedades pendientes", style: "title" },
          {
            table: {
              headerRows: 1,
              body: [
                ["Fecha", "Turno", "Sector", "Título", "Prioridad", "Estado", "Responsable"],
                ...(this.incidents || []).map((item) => [
                  this.formatDateTime(item.created_at),
                  item.shift?.staff?.full_name || "-",
                  item.sector_name || item.shift?.coverage_label || "-",
                  item.title,
                  item.priority,
                  item.status?.name || "-",
                  item.current_responsible?.name || "-",
                ]),
              ],
            },
            layout: "lightHorizontalLines",
          },
        ],
        styles: {
          title: { fontSize: 16, bold: true, margin: [0, 0, 0, 10] },
        },
        defaultStyle: { fontSize: 9 },
      }).download(`novedades-pendientes-${new Date().toISOString().slice(0, 10)}.pdf`);
    },
    toInputDateTime(value) {
      return value ? String(value).slice(0, 16) : "";
    },
    formatDateTime(value) {
      if (!value) return "-";
      return new Date(value).toLocaleString("es-CL", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "No se pudo completar la operación.";
    },
    ensureArray(value) {
      if (Array.isArray(value)) return value;
      if (value === null || value === undefined || value === "") return [];
      return [value];
    },
    showSuccess(message) {
      return Swal.fire({
        icon: "success",
        title: "Operación realizada",
        text: message,
        confirmButtonText: "OK",
      });
    },
    showWarning(message) {
      return Swal.fire({
        icon: "warning",
        title: "Revisa la información",
        text: message,
        confirmButtonText: "OK",
      });
    },
    showError(message) {
      return Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: message,
        confirmButtonText: "OK",
      });
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Novedades pendientes</h4>
        <div class="text-muted">Bandeja de observaciones, derivación y cierre con historial completo.</div>
      </div>
      <div class="d-flex gap-2">
        <BButton variant="outline-secondary" @click="loadIncidents()">Actualizar</BButton>
        <BButton variant="primary" @click="exportPdf">Exportar PDF</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-lg-3">
          <label class="form-label">Búsqueda</label>
          <BFormInput v-model="filters.search" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Prioridad</label>
          <Multiselect v-model="filters.priority" :options="priorityOptions" :searchable="true" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status_id" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Responsable</label>
          <Multiselect v-model="filters.responsible_user_id" :options="responsibleOptions" :searchable="true" />
        </div>
        <div class="col-lg-1">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-lg-1">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <BFormCheckbox v-model="filters.pending_only" switch>Solo pendientes</BFormCheckbox>
        <BButton variant="primary" @click="loadIncidents(1)">Filtrar</BButton>
      </div>
    </BCard>

    <div class="row g-3">
      <div class="col-xl-5">
        <BCard title="Bandeja">
          <LoadingState v-if="loading" message="Cargando novedades..." compact />
          <div v-else class="vstack gap-3">
            <button
              v-for="incident in incidents"
              :key="incident.id"
              type="button"
              class="btn text-start border rounded-3 p-3 incident-card"
              :class="{ 'border-primary bg-primary-subtle': selectedIncident?.id === incident.id }"
              @click="openIncident(incident)"
            >
              <div class="d-flex justify-content-between gap-2">
                <div>
                  <div class="fw-semibold">{{ incident.title }}</div>
                  <div class="small text-muted">{{ incident.shift?.staff?.full_name || "-" }} · {{ incident.sector_name || incident.shift?.coverage_label || "-" }}</div>
                </div>
                <span
                  class="badge"
                  :class="{
                    'bg-danger': incident.priority === 'critica',
                    'bg-warning text-dark': incident.priority === 'alta',
                    'bg-info': incident.priority === 'media',
                    'bg-secondary': incident.priority === 'baja',
                  }"
                >
                  {{ incident.priority }}
                </span>
              </div>
              <div class="small mt-2">{{ incident.status?.name || "-" }}</div>
            </button>
          </div>
        </BCard>
      </div>

      <div class="col-xl-7">
        <BCard v-if="selectedIncident">
          <template #header>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <div class="fw-semibold">{{ selectedIncident.title }}</div>
                <div class="small text-muted">{{ selectedIncident.shift?.staff?.full_name || "-" }} · {{ selectedIncident.sector_name || selectedIncident.shift?.coverage_label || "-" }}</div>
              </div>
              <span class="badge bg-light text-dark">{{ selectedIncident.status?.name || "-" }}</span>
            </div>
          </template>

          <div class="mb-3">{{ selectedIncident.description }}</div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Prioridad</label>
              <Multiselect v-model="updateForm.priority" :options="priorityOptions.slice(1)" :searchable="true" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Estado</label>
              <Multiselect v-model="updateForm.status_id" :options="statusOptions.slice(1)" :searchable="true" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Responsable principal</label>
              <Multiselect v-model="updateForm.current_responsible_user_id" :options="responsibleOptions.slice(1)" :searchable="true" />
            </div>
            <div class="col-12">
              <label class="form-label">Responsables asignados</label>
              <Multiselect
                v-model="updateForm.assignee_user_ids"
                :options="responsibleOptions.slice(1)"
                mode="tags"
                :searchable="true"
                :close-on-select="false"
              />
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha compromiso</label>
              <BFormInput v-model="updateForm.response_due_at" type="datetime-local" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Evidencia de cierre / seguimiento</label>
              <BFormInput type="file" accept="image/*" multiple @change="onEvidenceChange" />
            </div>
            <div class="col-12">
              <label class="form-label">Respuesta / acciones</label>
              <BFormTextarea v-model="updateForm.response_summary" rows="3" />
            </div>
            <div class="col-12">
              <label class="form-label">Comentario de seguimiento</label>
              <BFormTextarea v-model="updateForm.comment" rows="3" />
            </div>
            <div class="col-12">
              <label class="form-label">Notas de cierre</label>
              <BFormTextarea v-model="updateForm.closure_evidence_notes" rows="2" />
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-4">
            <BButton variant="primary" :disabled="saving" @click="saveIncident">Guardar cambios</BButton>
            <BButton variant="outline-secondary" :disabled="saving" @click="saveFollowUp">Agregar seguimiento</BButton>
          </div>

          <div class="fw-semibold mb-2">Historial</div>
          <div v-if="!(selectedIncident.comments || []).length" class="text-muted">Sin comentarios de seguimiento.</div>
          <div v-else class="vstack gap-3">
            <div v-for="comment in selectedIncident.comments" :key="comment.id" class="border rounded-3 p-3 bg-light-subtle">
              <div class="d-flex justify-content-between gap-2">
                <div class="fw-semibold">{{ comment.user?.name || "-" }}</div>
                <div class="small text-muted">{{ formatDateTime(comment.responded_at || comment.created_at) }}</div>
              </div>
              <div class="small text-muted">{{ comment.status?.name || "Sin cambio de estado" }}</div>
              <div class="mt-2">{{ comment.comment }}</div>
            </div>
          </div>
        </BCard>

        <BCard v-else>
          <div class="text-muted">Selecciona una novedad para revisar responsables, registrar seguimiento y cerrar el caso.</div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.incident-card {
  transition: all 0.18s ease;
}

.incident-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
}
</style>
