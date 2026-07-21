<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import EmptyState from "../../../components/staff/permissions/empty-state.vue";
import MetricCard from "../../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../../components/staff/permissions/status-badge.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  staff_id: null,
  permission_type_id: null,
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
  components: { Layout, LoadingState, EmptyState, MetricCard, PageHeader, StatusBadge, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      uploading: false,
      showRequestModal: false,
      showDetailModal: false,
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
    currentType() {
      return (this.catalogs.types || []).find((item) => item.id === this.form.permission_type_id) || null;
    },
    summaryCards() {
      const pendingStatuses = ["ingresado", "observado", "pendiente_jefatura", "pendiente_direccion", "pendiente_rrhh"];
      const approvedStatuses = ["aprobado", "ejecutado"];

      return [
        {
          label: "Solicitudes",
          value: this.pagination.total || this.items.length,
          hint: "Registros encontrados",
          icon: "bx-folder-open",
          variant: "primary",
        },
        {
          label: "Pendientes",
          value: this.items.filter((item) => pendingStatuses.includes(item.status)).length,
          hint: "En flujo de revisión",
          icon: "bx-time-five",
          variant: "warning",
        },
        {
          label: "Aprobadas",
          value: this.items.filter((item) => approvedStatuses.includes(item.status)).length,
          hint: "Página actual",
          icon: "bx-check-circle",
          variant: "success",
        },
        {
          label: "Con respaldo",
          value: this.items.filter((item) => (item.documents || []).length).length,
          hint: "Documentos adjuntos",
          icon: "bx-paperclip",
          variant: "info",
        },
      ];
    },
    currentTypeRules() {
      if (!this.currentType) return [];

      return [
        { label: "Adjunto", active: this.currentType.requires_attachment },
        { label: "Por horas", active: this.currentType.allows_hourly },
        { label: "Media jornada", active: this.currentType.allows_half_day },
        { label: "Retroactivo", active: this.currentType.allows_retroactive },
        { label: "Con goce", active: this.currentType.allows_with_pay },
        { label: "Sin goce", active: this.currentType.allows_without_pay },
        { label: "Reemplazo", active: this.currentType.requires_replacement },
      ];
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
    async showDetail(item, openModal = true) {
      try {
        const response = await axios.get(`/api/staff/permissions/${item.id}`);
        this.selectedRequest = response.data.data;
        this.showDetailModal = openModal;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    resetForm() {
      this.editingId = null;
      this.form = { ...emptyForm(), staff_id: this.currentStaffId };
    },
    newRequest() {
      this.resetForm();
      this.selectedRequest = null;
      this.showRequestModal = true;
    },
    async editRequest(item) {
      await this.showDetail(item, false);
      this.showDetailModal = false;
      this.editingId = item.id;
      this.form = {
        staff_id: this.selectedRequest.staff_id,
        permission_type_id: this.selectedRequest.permission_type_id,
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
      this.showRequestModal = true;
    },
    closeRequestModal() {
      if (this.saving) return;

      this.showRequestModal = false;
    },
    closeDetailModal() {
      this.selectedRequest = null;
      this.documentFile = null;
      this.documentComments = "";
    },
    async save(submit = false) {
      if (!this.form.staff_id && !this.currentStaffId) {
        await Swal.fire({
          title: "Funcionario no definido",
          text: "Tu usuario debe estar asociado a una ficha de funcionario para crear solicitudes desde Mis permisos.",
          icon: "warning",
          confirmButtonText: "Entendido",
        });
        return;
      }

      if (submit) {
        const result = await Swal.fire({
          title: "Enviar solicitud",
          text: "La solicitud quedará enviada para revisión.",
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Guardar y enviar",
          cancelButtonText: "Seguir editando",
          reverseButtons: true,
        });

        if (!result.isConfirmed) return;
      }

      this.saving = true;
      this.error = null;
      const wasEditing = Boolean(this.editingId);

      try {
        const payload = {
          ...this.form,
          staff_id: this.form.staff_id || this.currentStaffId,
          submit,
        };
        if (this.editingId) {
          await axios.put(`/api/staff/permissions/${this.editingId}`, payload);
        } else {
          await axios.post("/api/staff/permissions", payload);
        }
        this.showRequestModal = false;
        this.resetForm();
        await this.loadRequests(this.pagination.current_page);
        await Swal.fire({
          title: submit ? "Solicitud enviada" : wasEditing ? "Solicitud actualizada" : "Borrador guardado",
          text: submit ? "La solicitud fue registrada y enviada correctamente." : "Los cambios fueron guardados correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        const message = this.formatError(error);
        this.error = message;
        await Swal.fire({
          title: "No se pudo guardar",
          text: message,
          icon: "error",
          confirmButtonText: "Entendido",
        });
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
    canEdit(item) {
      return ["borrador", "ingresado", "observado"].includes(item.status);
    },
    canCancel(item) {
      return !["cancelado", "rechazado", "ejecutado"].includes(item.status);
    },
    resetFilters() {
      this.filters = { search: "", status: null, permission_type_id: null };
      this.loadRequests(1);
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
    formatDateTime(value) {
      if (!value) return "-";

      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }).format(date);
    },
    statusLabel(value) {
      if (!value) return null;

      return (this.catalogs.statuses || []).find((status) => status.value === value)?.label || String(value).replaceAll("_", " ");
    },
    formatLogAction(log) {
      const labels = {
        creada: "Solicitud creada",
        actualizada: "Solicitud actualizada",
        enviada_revision: "Enviada a revision",
        aprobada_etapa: "Etapa aprobada",
        rechazada: "Solicitud rechazada",
        observada: "Solicitud observada",
        cancelada: "Solicitud cancelada",
        ejecutada: "Permiso ejecutado",
        documento_validado: "Documento validado",
      };

      return labels[log.action] || String(log.action || "Movimiento").replaceAll("_", " ");
    },
    logActionMeta(log) {
      const meta = {
        creada: { icon: "bx-plus-circle", variant: "primary" },
        actualizada: { icon: "bx-edit-alt", variant: "info" },
        enviada_revision: { icon: "bx-send", variant: "info" },
        aprobada_etapa: { icon: "bx-check-circle", variant: "success" },
        rechazada: { icon: "bx-x-circle", variant: "danger" },
        observada: { icon: "bx-error-circle", variant: "warning" },
        cancelada: { icon: "bx-block", variant: "danger" },
        ejecutada: { icon: "bx-check-double", variant: "success" },
        documento_validado: { icon: "bx-file", variant: "success" },
      };

      return meta[log.action] || { icon: "bx-history", variant: "secondary" };
    },
    logStatusChange(log) {
      if (!log.old_status || !log.new_status || log.old_status === log.new_status) return null;

      return `${this.statusLabel(log.old_status)} -> ${this.statusLabel(log.new_status)}`;
    },
    logComment(log) {
      const details = log.details || {};

      return details.comment || details.internal_comment || details.reason || details.validation_status || null;
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
      title="Mis permisos"
      subtitle="Solicita, adjunta respaldos y consulta el avance de cada permiso."
      icon="bx-calendar-plus"
    >
      <template #actions>
        <BButton variant="primary" @click="newRequest">
          <i class="bx bx-plus me-1"></i>Nueva solicitud
        </BButton>
      </template>
    </PageHeader>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BModal
      v-model="showRequestModal"
      :title="editingId ? 'Editar solicitud' : 'Nueva solicitud'"
      size="xl"
      hide-footer
      centered
      scrollable
      modal-class="permission-request-modal"
      body-class="permission-request-modal__body"
      @hidden="resetForm"
    >
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="permission-section-title mb-0">
          <i class="bx bx-edit"></i>
          <span>{{ editingId ? "Editar solicitud" : "Nueva solicitud" }}</span>
        </div>
        <StatusBadge
          v-if="editingId"
          status="borrador"
          label="Editando"
          variant="info"
          icon="bx-edit-alt"
        />
      </div>

      <div v-if="currentType" class="permission-filter-card mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
          <div>
            <div class="fw-semibold text-dark">{{ currentType.name }}</div>
            <div class="text-muted small">{{ currentType.description || "Tipo seleccionado para esta solicitud." }}</div>
          </div>
          <div class="permission-chip-list">
            <span
              v-for="rule in currentTypeRules"
              :key="rule.label"
              class="permission-chip"
              :class="{ 'opacity-50': !rule.active }"
            >
              <i :class="rule.active ? 'bx bx-check' : 'bx bx-minus'"></i>{{ rule.label }}
            </span>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-12">
          <label class="form-label">Tipo de permiso</label>
          <Multiselect v-model="form.permission_type_id" :options="typeOptions" :searchable="true" />
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
        <div class="col-12">
          <div class="permission-option-grid">
            <div class="permission-option-card"><BFormCheckbox v-model="form.is_half_day">Media jornada</BFormCheckbox></div>
            <div class="permission-option-card"><BFormCheckbox v-model="form.urgency">Urgencia</BFormCheckbox></div>
            <div class="permission-option-card"><BFormCheckbox v-model="form.retroactive">Retroactivo</BFormCheckbox></div>
            <div class="permission-option-card"><BFormCheckbox v-model="form.requires_replacement">Requiere reemplazo</BFormCheckbox></div>
          </div>
        </div>
      </div>
      <div class="permission-request-modal__footer">
        <BButton variant="light" :disabled="saving" @click="closeRequestModal">
          Cerrar
        </BButton>
        <BButton variant="outline-secondary" :disabled="saving" @click="save(false)">
          <i class="bx bx-save me-1"></i>Guardar borrador
        </BButton>
        <BButton variant="primary" :disabled="saving" @click="save(true)">
          <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-send me-1'"></i>
          {{ saving ? "Enviando..." : "Guardar y enviar" }}
        </BButton>
      </div>
    </BModal>

    <div class="row g-3 mb-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-6 col-xl-3">
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
          <i class="bx bx-list-ul"></i>
          <span>Mis solicitudes</span>
        </div>
      </template>

      <div class="permission-filter-card mb-3">
        <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Búsqueda</label>
          <div class="permission-input-icon">
            <i class="bx bx-search"></i>
            <BFormInput v-model="filters.search" placeholder="Motivo, descripcion o estado" @keyup.enter="loadRequests(1)" />
          </div>
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
        <div class="d-flex gap-2 mt-3">
          <BButton variant="primary" @click="loadRequests(1)">
            <i class="bx bx-filter-alt me-1"></i>Filtrar
          </BButton>
          <BButton variant="outline-secondary" @click="resetFilters">
            <i class="bx bx-reset me-1"></i>Limpiar
          </BButton>
        </div>
      </div>

      <LoadingState v-if="loading" message="Cargando solicitudes..." compact />
      <EmptyState
        v-else-if="!items.length"
        icon="bx-calendar-x"
        title="Sin solicitudes"
        text="No hay permisos para los filtros seleccionados."
      />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle permission-table">
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
              <td>
                <div class="fw-semibold">{{ item.permission_type?.name || "-" }}</div>
                <div class="text-muted small">{{ item.reason || "Sin motivo registrado" }}</div>
              </td>
              <td>{{ formatPeriod(item.start_date, item.end_date, item.start_time, item.end_time) }}</td>
              <td>{{ item.duration_label || "-" }}</td>
              <td><StatusBadge :status="item.status" /></td>
              <td>
                <StatusBadge
                  :label="paymentBadge(item.with_pay).label"
                  :variant="paymentBadge(item.with_pay).variant"
                  :icon="paymentBadge(item.with_pay).icon"
                />
              </td>
              <td class="text-end">
                <div class="permission-row-actions">
                  <BButton
                    class="permission-action-button permission-action-button--view"
                    variant="link"
                    title="Ver detalle"
                    aria-label="Ver detalle"
                    @click="showDetail(item)"
                  >
                    <i class="bx bx-show"></i>
                  </BButton>
                  <BButton
                    v-if="canEdit(item)"
                    class="permission-action-button permission-action-button--edit"
                    variant="link"
                    title="Editar solicitud"
                    aria-label="Editar solicitud"
                    @click="editRequest(item)"
                  >
                    <i class="bx bx-edit-alt"></i>
                  </BButton>
                  <BButton
                    v-if="canCancel(item)"
                    class="permission-action-button permission-action-button--cancel"
                    variant="link"
                    title="Cancelar solicitud"
                    aria-label="Cancelar solicitud"
                    @click="cancelRequest(item)"
                  >
                    <i class="bx bx-x-circle"></i>
                  </BButton>
                </div>
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
          @update:model-value="loadRequests"
        />
      </div>
    </BCard>

    <BModal
      v-model="showDetailModal"
      title="Detalle de solicitud"
      size="xl"
      hide-footer
      centered
      scrollable
      modal-class="permission-detail-modal"
      body-class="permission-detail-modal__body"
      @hidden="closeDetailModal"
    >
      <div v-if="selectedRequest" class="permission-detail-content">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <div class="permission-section-title mb-1">
              <i class="bx bx-detail"></i>
              <span>Detalle de solicitud</span>
            </div>
            <div class="text-muted small">{{ selectedRequest.reason || "Sin motivo registrado" }}</div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <StatusBadge :status="selectedRequest.status" />
            <BButton size="sm" variant="outline-secondary" title="Cerrar" aria-label="Cerrar detalle" @click="showDetailModal = false">
              <i class="bx bx-x"></i>
            </BButton>
          </div>
        </div>

        <div class="permission-detail-grid mt-3">
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
            <div class="permission-detail-item__label">Duración</div>
            <div class="permission-detail-item__value">{{ selectedRequest.duration_label || "-" }}</div>
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

        <div class="permission-filter-card mt-3">
          <div class="permission-section-title">
            <i class="bx bx-comment-detail"></i>
            <span>Contenido</span>
          </div>
          <div class="row g-3">
            <div class="col-lg-6">
              <div class="text-muted small fw-semibold">Descripción</div>
              <div>{{ selectedRequest.description || "-" }}</div>
            </div>
            <div class="col-lg-6">
              <div class="text-muted small fw-semibold">Observaciones visibles</div>
              <div>{{ selectedRequest.visible_observations || "-" }}</div>
            </div>
          </div>
        </div>

        <hr />
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
        <div v-else class="permission-chip-list mb-3">
          <span v-for="watcher in selectedRequest.watchers || []" :key="watcher.id" class="permission-chip">
            <i class="bx bx-user"></i>
            {{ watcher.user?.name || "-" }}
            <span v-if="watcher.user?.email" class="text-muted">{{ watcher.user.email }}</span>
          </span>
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
          text="Esta solicitud aún no tiene respaldos adjuntos."
        />
        <div v-else class="table-responsive mb-3">
          <table class="table table-sm align-middle permission-table">
            <thead>
              <tr>
                <th>Archivo</th>
                <th>Validación</th>
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
                  <BButton size="sm" variant="outline-primary" @click="downloadDocument(attachment)">
                    <i class="bx bx-download me-1"></i>Descargar
                  </BButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="permission-filter-card mt-3">
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
                <i :class="uploading ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-upload me-1'"></i>
                {{ uploading ? "Subiendo..." : "Subir" }}
              </BButton>
            </div>
          </div>
        </div>

        <hr />
        <div class="permission-section-title permission-section-title--history">
          <span>
            <i class="bx bx-history"></i>
            Historial
          </span>
          <span v-if="(selectedRequest.logs || []).length" class="permission-section-count">
            {{ selectedRequest.logs.length }} movimientos
          </span>
        </div>
        <EmptyState
          v-if="!(selectedRequest.logs || []).length"
          icon="bx-history"
          title="Sin historial"
          text="La solicitud no registra movimientos."
        />
        <div v-else class="permission-timeline">
          <div
            v-for="(log, index) in selectedRequest.logs || []"
            :key="log.id"
            class="permission-timeline__item"
            :class="{ 'permission-timeline__item--latest': index === 0 }"
          >
            <div
              class="permission-timeline__marker"
              :class="`permission-timeline__marker--${logActionMeta(log).variant}`"
            >
              <i :class="`bx ${logActionMeta(log).icon}`"></i>
            </div>
            <div class="permission-timeline__body">
              <div class="permission-timeline__head">
                <div>
                  <div class="permission-timeline__title">{{ formatLogAction(log) }}</div>
                  <div v-if="logStatusChange(log)" class="permission-timeline__status">{{ logStatusChange(log) }}</div>
                </div>
                <span v-if="index === 0" class="permission-timeline__latest">Ultimo movimiento</span>
              </div>
              <div class="permission-timeline__meta">
                <span><i class="bx bx-calendar"></i>{{ formatDateTime(log.created_at) }}</span>
                <span><i class="bx bx-user"></i>{{ log.user?.name || "Sistema" }}</span>
              </div>
              <div v-if="logComment(log)" class="permission-timeline__comment">
                {{ logComment(log) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </BModal>
  </Layout>
</template>
