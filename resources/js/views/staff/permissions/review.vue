<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import EmptyState from "../../../components/staff/permissions/empty-state.vue";
import MetricCard from "../../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../../components/staff/permissions/status-badge.vue";
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
  components: { Layout, LoadingState, EmptyState, MetricCard, PageHeader, StatusBadge, Multiselect },
  data() {
    return {
      loading: false,
      acting: false,
      showReviewModal: false,
      error: null,
      catalogs: { statuses: [], payroll_statuses: [], replacement_statuses: [], staff: [], types: [] },
      filters: { search: "", status: null, permission_type_id: null },
      items: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
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
    queueStats() {
      const pendingStatuses = ["ingresado", "observado", "pendiente_jefatura", "pendiente_direccion", "pendiente_rrhh"];

      return [
        {
          label: "En bandeja",
          value: this.pagination.total || this.items.length,
          hint: "Solicitudes visibles",
          icon: "bx-folder-open",
          variant: "primary",
        },
        {
          label: "Pendientes",
          value: this.items.filter((item) => pendingStatuses.includes(item.status)).length,
          hint: "Esperan decision",
          icon: "bx-time-five",
          variant: "warning",
        },
        {
          label: "Con reemplazo",
          value: this.items.filter((item) => item.requires_replacement).length,
          hint: "Requieren cobertura",
          icon: "bx-transfer-alt",
          variant: "info",
        },
        {
          label: "Urgentes",
          value: this.items.filter((item) => item.urgency || item.retroactive).length,
          hint: "Ingreso especial",
          icon: "bx-error-circle",
          variant: "danger",
        },
      ];
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
    async loadQueue(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permissions", {
          params: {
            page,
            review_queue: true,
            search: this.filters.search || null,
            status: this.filters.status,
            permission_type_id: this.filters.permission_type_id,
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || this.items.length,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openDetail(item, openModal = true) {
      try {
        const response = await axios.get(`/api/staff/permissions/${item.id}`);
        this.selectedRequest = response.data.data;
        this.showReviewModal = openModal;
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
    closeReviewModal() {
      this.selectedRequest = null;
      this.actionForm = emptyActionForm();
      this.replacementItems = [];
    },
    async act(endpoint) {
      if (!this.selectedRequest) return;

      const actionCopy = {
        approve: {
          title: "Aprobar solicitud",
          text: "La solicitud avanzará a la siguiente etapa o quedará aprobada si esta es la revisión final.",
          icon: "question",
          confirmButtonText: "Aprobar",
          successTitle: "Aprobación registrada",
        },
        reject: {
          title: "Rechazar solicitud",
          text: "La solicitud quedará rechazada y se notificará al funcionario.",
          icon: "warning",
          confirmButtonText: "Rechazar",
          successTitle: "Solicitud rechazada",
        },
        observe: {
          title: "Observar solicitud",
          text: "La solicitud volverá al funcionario con las observaciones indicadas.",
          icon: "question",
          confirmButtonText: "Observar",
          successTitle: "Observación registrada",
        },
      }[endpoint];

      if (endpoint === "reject" && !String(this.actionForm.comments || "").trim()) {
        await Swal.fire({
          title: "Comentario requerido",
          text: "Ingresa un comentario visible antes de rechazar la solicitud.",
          icon: "warning",
          confirmButtonText: "Entendido",
        });
        return;
      }

      if (actionCopy) {
        const result = await Swal.fire({
          title: actionCopy.title,
          text: actionCopy.text,
          icon: actionCopy.icon,
          showCancelButton: true,
          confirmButtonText: actionCopy.confirmButtonText,
          cancelButtonText: "Cancelar",
          reverseButtons: true,
        });

        if (!result.isConfirmed) return;
      }

      this.acting = true;
      this.error = null;
      try {
        const response = await axios.post(`/api/staff/permissions/${this.selectedRequest.id}/${endpoint}`, this.actionForm);
        await this.openDetail(this.selectedRequest);
        await this.loadQueue();
        await Swal.fire({
          title: actionCopy?.successTitle || "Acción registrada",
          text: response.data?.message || "La acción fue registrada correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        const message = this.formatError(error);
        this.error = message;
        await Swal.fire({
          title: "No se pudo registrar",
          text: message,
          icon: "error",
          confirmButtonText: "Entendido",
        });
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
    async downloadDocument(attachment) {
      try {
        const response = await axios.get(`/api/staff/permissions/documents/${attachment.id}/download`, {
          responseType: "blob",
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = attachment.file_name || "documento";
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
    resetFilters() {
      this.filters = { search: "", status: null, permission_type_id: null };
      this.loadQueue(1);
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).split(" ")[0].split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    formatTime(value) {
      if (!value) return null;

      const match = String(value).match(/(\d{2}):(\d{2})/);

      return match ? `${match[1]}:${match[2]}` : null;
    },
    formatPeriod(startDate, endDate, startTime = null, endTime = null) {
      const start = [this.formatDate(startDate), this.formatTime(startTime)].filter(Boolean).join(" ");
      const end = [this.formatDate(endDate), this.formatTime(endTime)].filter(Boolean).join(" ");

      return start === end ? start : `${start} - ${end}`;
    },
    paymentBadge(value) {
      if (value === null || value === undefined) {
        return { label: "Pendiente", variant: "warning", icon: "bx-time-five" };
      }

      return value
        ? { label: "Con goce", variant: "success", icon: "bx-wallet" }
        : { label: "Sin goce", variant: "secondary", icon: "bx-wallet-alt" };
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
    <PageHeader
      title="Bandeja de revisión"
      subtitle="Revisión por jefatura, Dirección y RRHH con trazabilidad completa."
      icon="bx-calendar-check"
    >
      <template #actions>
        <BButton variant="outline-primary" @click="loadQueue">
          <i class="bx bx-refresh me-1"></i>Actualizar
        </BButton>
      </template>
    </PageHeader>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <div class="row g-3 mb-3">
      <div v-for="card in queueStats" :key="card.label" class="col-md-6 col-xl-3">
        <MetricCard
          :label="card.label"
          :value="card.value"
          :hint="card.hint"
          :icon="card.icon"
          :variant="card.variant"
        />
      </div>
    </div>

    <BCard class="permission-card mb-3">
      <template #header>
        <div class="permission-section-title mb-0">
          <i class="bx bx-filter-alt"></i>
          <span>Filtros de revisión</span>
        </div>
      </template>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Búsqueda</label>
          <div class="permission-input-icon">
            <i class="bx bx-search"></i>
            <BFormInput v-model="filters.search" placeholder="Funcionario, motivo o estado" @keyup.enter="loadQueue(1)" />
          </div>
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
        <BButton variant="primary" @click="loadQueue(1)">
          <i class="bx bx-filter-alt me-1"></i>Filtrar
        </BButton>
        <BButton variant="outline-secondary" @click="resetFilters">
          <i class="bx bx-reset me-1"></i>Limpiar
        </BButton>
      </div>
    </BCard>

    <BCard class="permission-card">
      <template #header>
        <div class="permission-section-title mb-0">
          <i class="bx bx-list-check"></i>
          <span>Pendientes</span>
        </div>
      </template>
      <LoadingState v-if="loading" message="Cargando bandeja..." compact />
      <EmptyState
        v-else-if="!items.length"
        icon="bx-check-shield"
        title="Bandeja sin solicitudes"
        text="No hay permisos pendientes con los filtros actuales."
      />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle permission-table">
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
              <td>
                <div class="fw-semibold">{{ item.staff?.full_name || "-" }}</div>
                <div class="text-muted small">{{ item.reason || "Sin motivo registrado" }}</div>
              </td>
              <td>{{ item.permission_type?.name || "-" }}</td>
              <td><StatusBadge :status="item.status" /></td>
              <td>{{ formatDate(item.start_date) }}</td>
              <td class="text-end">
                <BButton size="sm" variant="outline-primary" @click="openDetail(item)">
                  <i class="bx bx-show me-1"></i>Revisar
                </BButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <div class="text-muted small">Total: {{ pagination.total }}</div>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="15"
          pills
          @update:model-value="loadQueue"
        />
      </div>
    </BCard>

    <BModal
      v-model="showReviewModal"
      title="Detalle y resolución"
      size="xl"
      hide-footer
      centered
      scrollable
      modal-class="permission-detail-modal"
      body-class="permission-detail-modal__body"
      @hidden="closeReviewModal"
    >
      <div v-if="selectedRequest" class="permission-detail-content">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <div class="permission-section-title mb-1">
              <i class="bx bx-task"></i>
              <span>Detalle y resolución</span>
            </div>
            <div class="text-muted small">{{ selectedRequest.staff?.full_name || "-" }}</div>
          </div>
          <StatusBadge :status="selectedRequest.status" />
        </div>

      <div class="permission-detail-grid mb-3">
        <div class="permission-detail-item">
          <div class="permission-detail-item__label">Funcionario</div>
          <div class="permission-detail-item__value">{{ selectedRequest.staff?.full_name || "-" }}</div>
        </div>
        <div class="permission-detail-item">
          <div class="permission-detail-item__label">Tipo</div>
          <div class="permission-detail-item__value">{{ selectedRequest.permission_type?.name || "-" }}</div>
        </div>
        <div class="permission-detail-item">
          <div class="permission-detail-item__label">Periodo</div>
          <div class="permission-detail-item__value">
            {{ formatPeriod(selectedRequest.start_date, selectedRequest.end_date, selectedRequest.start_time, selectedRequest.end_time) }}
          </div>
        </div>
        <div class="permission-detail-item">
          <div class="permission-detail-item__label">Remuneración</div>
          <div class="permission-detail-item__value">
            <StatusBadge
              :label="paymentBadge(selectedRequest.with_pay).label"
              :variant="paymentBadge(selectedRequest.with_pay).variant"
              :icon="paymentBadge(selectedRequest.with_pay).icon"
            />
          </div>
        </div>
      </div>

      <div class="permission-filter-card mb-3">
        <div class="permission-section-title">
          <i class="bx bx-comment-detail"></i>
          <span>Solicitud</span>
        </div>
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="text-muted small fw-semibold">Motivo</div>
            <div>{{ selectedRequest.reason || "-" }}</div>
          </div>
          <div class="col-lg-6">
            <div class="text-muted small fw-semibold">Descripción</div>
            <div>{{ selectedRequest.description || "-" }}</div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <div class="permission-section-title">
          <i class="bx bx-bell"></i>
          <span>Quienes deben enterarse</span>
        </div>
        <EmptyState
          v-if="!(selectedRequest.watchers || []).length"
          icon="bx-user-x"
          title="Sin destinatarios"
          text="No hay destinatarios configurados para esta solicitud."
        />
        <div v-else class="permission-chip-list">
          <span v-for="watcher in selectedRequest.watchers || []" :key="watcher.id" class="permission-chip">
            <i class="bx bx-user"></i>
            {{ watcher.user?.name || "-" }}
            <span v-if="watcher.source_label" class="text-muted">{{ watcher.source_label }}</span>
          </span>
        </div>
      </div>

      <div class="permission-filter-card">
        <div class="permission-section-title">
          <i class="bx bx-check-square"></i>
          <span>Resolucion</span>
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
      </div>

      <div class="d-flex gap-2 mt-3">
        <BButton variant="success" :disabled="acting" @click="act('approve')">
          <i class="bx bx-check me-1"></i>Aprobar
        </BButton>
        <BButton variant="warning" :disabled="acting" @click="act('observe')">
          <i class="bx bx-message-square-error me-1"></i>Observar
        </BButton>
        <BButton variant="danger" :disabled="acting" @click="act('reject')">
          <i class="bx bx-x me-1"></i>Rechazar
        </BButton>
        <BButton v-if="selectedRequest.status === 'aprobado'" variant="outline-primary" :disabled="acting" @click="executeRequest">
          <i class="bx bx-check-double me-1"></i>Marcar ejecutado
        </BButton>
      </div>

      <hr />
      <div class="permission-section-title">
        <i class="bx bx-paperclip"></i>
        <span>Documentos</span>
      </div>
      <EmptyState
        v-if="!(selectedRequest.documents || []).length"
        icon="bx-file-blank"
        title="Sin documentos"
        text="La solicitud no tiene documentos adjuntos."
      />
      <div v-else class="table-responsive mb-3">
        <table class="table table-sm align-middle permission-table">
          <thead>
            <tr>
              <th>Archivo</th>
              <th>Estado</th>
              <th>Comentarios</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="attachment in selectedRequest.documents || []" :key="attachment.id">
              <td>{{ attachment.file_name }}</td>
              <td><StatusBadge :status="attachment.validation_status" /></td>
              <td>{{ attachment.comments || "-" }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <BButton variant="outline-primary" @click="downloadDocument(attachment)">
                    <i class="bx bx-download me-1"></i>Descargar
                  </BButton>
                  <BButton variant="outline-success" @click="validateDocument(attachment, 'validado')">
                    <i class="bx bx-check me-1"></i>Validar
                  </BButton>
                  <BButton variant="outline-danger" @click="validateDocument(attachment, 'rechazado')">
                    <i class="bx bx-x me-1"></i>Rechazar
                  </BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="selectedRequest.requires_replacement">
        <hr />
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="permission-section-title mb-0">
            <i class="bx bx-transfer-alt"></i>
            <span>Reemplazos</span>
          </div>
          <BButton size="sm" variant="outline-secondary" @click="addReplacementRow">
            <i class="bx bx-plus me-1"></i>Agregar fila
          </BButton>
        </div>
        <div class="vstack gap-3">
          <div v-for="(item, index) in replacementItems" :key="index" class="permission-filter-card">
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
          </div>
        </div>
        <BButton variant="outline-primary" class="mt-3" @click="saveReplacements">
          <i class="bx bx-save me-1"></i>Guardar reemplazos
        </BButton>
      </div>
      </div>
    </BModal>
  </Layout>
</template>
