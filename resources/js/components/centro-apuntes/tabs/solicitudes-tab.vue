<script>
import axios from "axios";
import Swal from "sweetalert2";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesModalIntro from "../modal-intro.vue";
import CentroApuntesSectionToolbar from "../section-toolbar.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmCentroApuntesAction,
  confirmCentroApuntesCancel,
  formatCentroApuntesDate,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  humanizeCentroApuntesStatus,
  normalizeCentroApuntesNullableFields,
  normalizeOptions,
  showCentroApuntesSuccess,
  toInputDateTime,
} from "../module-utils";

const emptyForm = () => ({
  id: null,
  requested_by_user_id: null,
  subject_id: null,
  machine_id: null,
  task_type: "guia",
  task_type_other: null,
  requested_at: "",
  delivery_date: "",
  sheet_count: 1,
  copies_count: 1,
  paper_size: "carta",
  priority: "normal",
  instructions: null,
  observations: null,
  internal_observations: null,
  attachment: null,
});

export default {
  components: {
    CentroApuntesHelpButton,
    CentroApuntesModalIntro,
    CentroApuntesSectionToolbar,
    CentroApuntesStatusBadge,
    LoadingState,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      detailLoading: false,
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        requested_by_user_id: null,
        subject_id: null,
        machine_id: null,
        task_type: null,
        status: null,
        paper_size: null,
        priority: null,
        urgent_only: false,
        immediate_only: false,
      },
      showModal: false,
      showDetailModal: false,
      form: emptyForm(),
      selectedRequest: null,
    };
  },
  computed: {
    capabilities() {
      return this.catalogs.capabilities || {};
    },
    userOptions() {
      return normalizeOptions(this.catalogs.users || []);
    },
    subjectOptions() {
      return normalizeOptions(this.catalogs.subjects || []);
    },
    machineOptions() {
      return normalizeOptions(this.catalogs.machines || []);
    },
    taskTypeOptions() {
      return normalizeOptions(this.catalogs.task_types || []);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.request_statuses || []);
    },
    priorityOptions() {
      return normalizeOptions(this.catalogs.request_priorities || []);
    },
    paperSizeOptions() {
      return normalizeOptions(this.catalogs.paper_sizes || []);
    },
  },
  mounted() {
    this.load();
    this.consumeRouteFocus();
  },
  methods: {
    formatCentroApuntesDate,
    formatCentroApuntesDateTime,
    humanizeCentroApuntesStatus,
    taskLabel(item) {
      if (!item) return "-";
      return item.task_type === "otro" ? (item.task_type_other || "Otro") : humanizeCentroApuntesStatus(item.task_type);
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/solicitudes", {
          params: {
            page,
            ...this.filters,
            urgent_only: this.filters.urgent_only ? 1 : "",
            immediate_only: this.filters.immediate_only ? 1 : "",
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar las solicitudes.");
      } finally {
        this.loading = false;
      }
    },
    resetForm() {
      this.form = emptyForm();
      this.selectedRequest = null;
    },
    openCreate() {
      this.resetForm();
      this.form.requested_at = new Date().toISOString().slice(0, 16);
      this.showModal = true;
    },
    async openEdit(item) {
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/solicitudes/${item.id}`);
        this.selectedRequest = response.data.data;
        this.form = {
          ...emptyForm(),
          id: this.selectedRequest.id,
          requested_by_user_id: this.selectedRequest.requested_by_user_id,
          subject_id: this.selectedRequest.subject_id,
          machine_id: this.selectedRequest.machine_id,
          task_type: this.selectedRequest.task_type,
          task_type_other: this.selectedRequest.task_type_other ?? null,
          requested_at: toInputDateTime(this.selectedRequest.requested_at),
          delivery_date: String(this.selectedRequest.delivery_date || "").slice(0, 10),
          sheet_count: this.selectedRequest.sheet_count,
          copies_count: this.selectedRequest.copies_count,
          paper_size: this.selectedRequest.paper_size,
          priority: this.selectedRequest.priority,
          instructions: this.selectedRequest.instructions ?? null,
          observations: this.selectedRequest.observations ?? null,
          internal_observations: this.selectedRequest.internal_observations ?? null,
          attachment: null,
        };
        this.showModal = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo abrir la solicitud seleccionada.");
      } finally {
        this.detailLoading = false;
      }
    },
    async openDetail(itemOrId) {
      const id = typeof itemOrId === "object" ? itemOrId.id : itemOrId;
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/solicitudes/${id}`);
        this.selectedRequest = response.data.data;
        this.showDetailModal = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el detalle de la solicitud.");
      } finally {
        this.detailLoading = false;
      }
    },
    async consumeRouteFocus() {
      if (!this.$route.query.solicitud) return;
      await this.openDetail(this.$route.query.solicitud);
    },
    buildFormData() {
      const formData = new FormData();
      const attachment = Array.isArray(this.form.attachment) ? this.form.attachment[0] : this.form.attachment;
      const normalized = normalizeCentroApuntesNullableFields(this.form, [
        "task_type_other",
        "requested_at",
        "instructions",
        "observations",
        "internal_observations",
      ]);
      [
        "requested_by_user_id",
        "subject_id",
        "machine_id",
        "task_type",
        "task_type_other",
        "requested_at",
        "delivery_date",
        "sheet_count",
        "copies_count",
        "paper_size",
        "priority",
        "instructions",
        "observations",
        "internal_observations",
      ].forEach((field) => {
        const value = normalized[field];
        formData.append(field, value ?? "");
      });

      if (attachment) {
        formData.append("attachment", attachment);
      }

      return formData;
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: this.form.id ? "Confirmar edición" : "Confirmar registro",
        text: this.form.id
          ? "Se actualizará la solicitud seleccionada."
          : "Se registrará una nueva solicitud en el centro de apuntes.",
        confirmButtonText: this.form.id ? "Sí, actualizar" : "Sí, guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      this.error = null;
      try {
        const formData = this.buildFormData();

        if (this.form.id) {
          formData.append("_method", "PUT");
          await axios.post(`/api/centro-apuntes/solicitudes/${this.form.id}`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });
        } else {
          await axios.post("/api/centro-apuntes/solicitudes", formData, {
            headers: { "Content-Type": "multipart/form-data" },
          });
        }

        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess(this.form.id ? "Solicitud actualizada correctamente." : "Solicitud registrada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    async promptStatus(item) {
      const optionsHtml = this.statusOptions
        .map((option) => `<option value="${option.value}" ${item.status === option.value ? "selected" : ""}>${option.label}</option>`)
        .join("");

      const result = await Swal.fire({
        customClass: { popup: "centro-apuntes-alert" },
        title: "Cambiar estado",
        html: `
          <div class="text-start">
            <label class="form-label">Nuevo estado</label>
            <select id="status-select" class="swal2-input">${optionsHtml}</select>
            <label class="form-label mt-2">Observaciones</label>
            <textarea id="status-note" class="swal2-textarea" placeholder="Detalle del cambio de estado"></textarea>
          </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: "Guardar estado",
        cancelButtonText: "Cancelar",
        preConfirm: () => ({
          status: document.getElementById("status-select").value,
          notes: document.getElementById("status-note").value,
        }),
      });

      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/centro-apuntes/solicitudes/${item.id}/status`, result.value);
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Estado actualizado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    async promptDelivery(item) {
      const optionsHtml = this.userOptions
        .map((option) => `<option value="${option.value}">${option.label}</option>`)
        .join("");

      const result = await Swal.fire({
        customClass: { popup: "centro-apuntes-alert" },
        title: "Registrar entrega",
        html: `
          <div class="text-start">
            <label class="form-label">Quién recibió</label>
            <select id="received-by" class="swal2-input">${optionsHtml}</select>
            <label class="form-label mt-2">Observaciones</label>
            <textarea id="delivery-note" class="swal2-textarea" placeholder="Detalle de la entrega"></textarea>
          </div>
        `,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Registrar entrega",
        cancelButtonText: "Cancelar",
        preConfirm: () => ({
          received_by_user_id: document.getElementById("received-by").value,
          notes: document.getElementById("delivery-note").value,
        }),
      });

      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/centro-apuntes/solicitudes/${item.id}/deliver`, result.value);
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Entrega registrada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    async destroy(item) {
      const confirmed = await confirmCentroApuntesAction({
        title: "Eliminar solicitud",
        text: `Se eliminará la solicitud ${item.request_code}.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      try {
        await axios.delete(`/api/centro-apuntes/solicitudes/${item.id}`);
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Solicitud eliminada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    clearFilters() {
      this.filters = {
        search: "",
        requested_by_user_id: null,
        subject_id: null,
        machine_id: null,
        task_type: null,
        status: null,
        paper_size: null,
        priority: null,
        urgent_only: false,
        immediate_only: false,
      };
      this.load();
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("los cambios del formulario");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-tab d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar title="Solicitudes de impresión" description="Registra, prioriza y sigue cada trabajo desde su ingreso hasta la entrega." icon="bx-printer">
      <div class="d-flex gap-2 flex-wrap">
        <CentroApuntesHelpButton
          title="Ayuda: solicitudes de impresión"
          text="Aquí se crean, actualizan, priorizan y entregan las tareas de impresión del establecimiento, dejando trazabilidad completa del estado y de quién recibió el material."
        />
        <BButton v-if="capabilities.can_create_request" variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Nueva solicitud</BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="filter-card border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Código, solicitante, asignatura, máquina..." @keyup.enter="load" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Solicitante</label>
          <BFormSelect v-model="filters.requested_by_user_id" :options="[{ value: null, text: 'Todos' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Asignatura</label>
          <BFormSelect v-model="filters.subject_id" :options="[{ value: null, text: 'Todas' }].concat(subjectOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Máquina</label>
          <BFormSelect v-model="filters.machine_id" :options="[{ value: null, text: 'Todas' }].concat(machineOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(statusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo de tarea</label>
          <BFormSelect v-model="filters.task_type" :options="[{ value: null, text: 'Todos' }].concat(taskTypeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tamaño</label>
          <BFormSelect v-model="filters.paper_size" :options="[{ value: null, text: 'Todos' }].concat(paperSizeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Prioridad</label>
          <BFormSelect v-model="filters.priority" :options="[{ value: null, text: 'Todas' }].concat(priorityOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <BFormCheckbox v-model="filters.urgent_only">Solo urgentes</BFormCheckbox>
          <BFormCheckbox v-model="filters.immediate_only">Solo entrega inmediata</BFormCheckbox>
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="data-card border-0 shadow-sm">
      <LoadingState v-if="loading || detailLoading" message="Cargando solicitudes..." compact />
      <BTable
        v-else
        responsive
        show-empty
        empty-text="No hay solicitudes que coincidan con los filtros."
        :items="items"
        :fields="[
          { key: 'request_code', label: 'Solicitud' },
          { key: 'requested_by_name_snapshot', label: 'Solicitante' },
          { key: 'subject_name_snapshot', label: 'Asignatura' },
          { key: 'machine_name_snapshot', label: 'Máquina' },
          { key: 'volume', label: 'Volumen' },
          { key: 'priority', label: 'Prioridad' },
          { key: 'status', label: 'Estado' },
          { key: 'delivery_date', label: 'Entrega' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(request_code)="{ item }">
          <div class="fw-semibold">{{ item.request_code }}</div>
          <div class="small text-muted">{{ taskLabel(item) }}</div>
        </template>
        <template #cell(volume)="{ item }">
          <div>{{ item.sheet_count }} hoja(s)</div>
          <div class="small text-muted">{{ item.copies_count }} copia(s)</div>
        </template>
        <template #cell(priority)="{ item }">
          <CentroApuntesStatusBadge :status="item.priority" />
        </template>
        <template #cell(status)="{ item }">
          <CentroApuntesStatusBadge :status="item.status" />
        </template>
        <template #cell(delivery_date)="{ item }">
          {{ formatCentroApuntesDate(item.delivery_date) }}
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-primary" @click="openDetail(item)">Ver</BButton>
            <BButton v-if="capabilities.can_edit_request" size="sm" variant="outline-secondary" @click="openEdit(item)">Editar</BButton>
            <BButton v-if="capabilities.can_change_request_status" size="sm" variant="outline-warning" @click="promptStatus(item)">Estado</BButton>
            <BButton v-if="capabilities.can_register_request_delivery" size="sm" variant="outline-success" @click="promptDelivery(item)">Entregar</BButton>
            <BButton v-if="capabilities.can_delete_request" size="sm" variant="outline-danger" @click="destroy(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="load"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" :title="form.id ? 'Editar solicitud' : 'Nueva solicitud'" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <CentroApuntesModalIntro title="Trabajo de impresión" text="Los detalles, observaciones y el archivo adjunto son opcionales; los datos operativos marcados con * son necesarios." icon="bx-printer">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de solicitud"
          text="Use este formulario para registrar solicitudes de impresión, asignando solicitante, asignatura, máquina, hojas, copias, prioridad y observaciones internas."
        />
      </CentroApuntesModalIntro>

      <div class="modal-form-grid row g-3">
        <div class="col-md-6">
          <label class="form-label">Solicitante <span class="field-required">*</span></label>
          <BFormSelect v-model="form.requested_by_user_id" :options="userOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Asignatura <span class="field-required">*</span></label>
          <BFormSelect v-model="form.subject_id" :options="subjectOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de tarea <span class="field-required">*</span></label>
          <BFormSelect v-model="form.task_type" :options="taskTypeOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div v-if="form.task_type === 'otro'" class="col-md-8">
          <label class="form-label">Detalle obligatorio para “Otro”</label>
          <BFormInput v-model="form.task_type_other" placeholder="Describa el trabajo solicitado" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de solicitud <span class="field-optional">Opcional</span></label>
          <BFormInput v-model="form.requested_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de entrega <span class="field-required">*</span></label>
          <BFormInput v-model="form.delivery_date" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Máquina <span class="field-required">*</span></label>
          <BFormSelect v-model="form.machine_id" :options="machineOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Cantidad de hojas <span class="field-required">*</span></label>
          <BFormInput v-model="form.sheet_count" type="number" min="1" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Cantidad de copias <span class="field-required">*</span></label>
          <BFormInput v-model="form.copies_count" type="number" min="1" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tamaño de papel <span class="field-required">*</span></label>
          <BFormSelect v-model="form.paper_size" :options="paperSizeOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Prioridad <span class="field-required">*</span></label>
          <BFormSelect v-model="form.priority" :options="priorityOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Detalle del trabajo <span class="field-optional">Opcional</span></label>
          <BFormTextarea v-model="form.instructions" rows="3" placeholder="Instrucciones de impresión, curso, color, anillado u observaciones de producción" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Observaciones <span class="field-optional">Opcional</span></label>
          <BFormTextarea v-model="form.observations" rows="2" placeholder="Observaciones generales visibles para seguimiento" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Observaciones internas <span class="field-optional">Opcional</span></label>
          <BFormTextarea v-model="form.internal_observations" rows="2" placeholder="Comentarios internos del equipo del centro de apuntes" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Archivo adjunto <span class="field-optional">Opcional</span></label>
          <BFormFile v-model="form.attachment" browse-text="Seleccionar" placeholder="Adjuntar archivo de la solicitud" />
        </div>
      </div>

      <div v-if="selectedRequest?.attachments?.length" class="mt-4">
        <div class="fw-semibold mb-2">Adjuntos existentes</div>
        <ul class="mb-0">
          <li v-for="attachment in selectedRequest.attachments" :key="attachment.id">
            <a :href="attachment.file_url" target="_blank" rel="noopener">{{ attachment.original_name }}</a>
          </li>
        </ul>
      </div>

      <div class="modal-actions">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Detalle de solicitud" hide-footer centered scrollable modal-class="centro-apuntes-modal">
      <template v-if="selectedRequest">
        <div class="detail-grid row g-3">
          <div class="col-md-4">
            <div class="text-muted small">Código</div>
            <div class="fw-semibold">{{ selectedRequest.request_code }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Solicitante</div>
            <div>{{ selectedRequest.requested_by_name_snapshot }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Asignatura</div>
            <div>{{ selectedRequest.subject_name_snapshot }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Máquina</div>
            <div>{{ selectedRequest.machine_name_snapshot }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Tipo de tarea</div>
            <div>{{ taskLabel(selectedRequest) }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Estado</div>
            <CentroApuntesStatusBadge :status="selectedRequest.status" />
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Volumen</div>
            <div>{{ selectedRequest.sheet_count }} hoja(s) x {{ selectedRequest.copies_count }} copia(s)</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Fecha de entrega</div>
            <div>{{ formatCentroApuntesDate(selectedRequest.delivery_date) }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Detalle del trabajo</div>
            <div>{{ selectedRequest.instructions || "-" }}</div>
          </div>
          <div class="col-md-6">
            <div class="text-muted small">Observaciones internas</div>
            <div>{{ selectedRequest.internal_observations || "-" }}</div>
          </div>
        </div>

        <div class="mt-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="modal-section-title mb-0">Adjuntos</div>
            <CentroApuntesHelpButton
              title="Ayuda: adjuntos e historial"
              text="Esta sección muestra los archivos asociados a la solicitud y la trazabilidad completa de los cambios de estado realizados por el equipo."
            />
          </div>
          <BAlert v-if="!selectedRequest.attachments?.length" show variant="light" class="mb-0">Sin adjuntos registrados.</BAlert>
          <ul v-else class="mb-0">
            <li v-for="attachment in selectedRequest.attachments" :key="attachment.id">
              <a :href="attachment.file_url" target="_blank" rel="noopener">{{ attachment.original_name }}</a>
              <span class="text-muted small"> · {{ attachment.uploaded_by?.name || attachment.uploadedBy?.name || "Sistema" }}</span>
            </li>
          </ul>
        </div>

        <div class="mt-4">
          <div class="modal-section-title">Historial de cambios</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Acción</th>
                  <th>Estado</th>
                  <th>Detalle</th>
                  <th>Responsable</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="history in selectedRequest.history || []" :key="history.id">
                  <td>{{ formatCentroApuntesDateTime(history.created_at) }}</td>
                  <td>{{ humanizeCentroApuntesStatus(history.action_type) }}</td>
                  <td>
                    <span v-if="history.new_status">
                      {{ humanizeCentroApuntesStatus(history.previous_status) }} → {{ humanizeCentroApuntesStatus(history.new_status) }}
                    </span>
                    <span v-else>-</span>
                  </td>
                  <td>{{ history.notes || "-" }}</td>
                  <td>{{ history.performed_by?.name || history.performedBy?.name || "Sistema" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </BModal>
  </div>
</template>
