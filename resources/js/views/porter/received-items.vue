<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  recipient_type: "student",
  recipient_label: "",
  student_profile_id: null,
  staff_id: null,
  department_id: null,
  received_from_name: "",
  received_from_rut: "",
  received_from_phone: "",
  item_type: "objeto",
  description: "",
  status: "recibido_en_porteria",
  observations: "",
  attachment: null,
});

const emptyFilters = () => ({
  search: "",
  status: null,
  item_type: null,
  recipient_type: null,
  date_from: "",
  date_to: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      loadingCatalogs: false,
      saving: false,
      loadingList: false,
      showDetailModal: false,
      selectedItem: null,
      error: null,
      catalogs: {
        received_item_types: [],
        received_item_statuses: [],
        received_item_recipient_types: [],
        departments: [],
        staff: [],
      },
      studentSearch: "",
      studentOptions: [],
      form: emptyForm(),
      items: [],
      filters: emptyFilters(),
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    recipientOptions() {
      return (this.catalogs.received_item_recipient_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    recipientFilterOptions() {
      return [{ value: null, text: "Todos los destinatarios" }].concat(this.recipientOptions);
    },
    typeOptions() {
      return (this.catalogs.received_item_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    typeFilterOptions() {
      return [{ value: null, text: "Todos los tipos" }].concat(this.typeOptions);
    },
    statusOptions() {
      return [{ value: null, text: "Todos los estados" }].concat(
        (this.catalogs.received_item_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    initialStatusOptions() {
      return this.statusOptions.filter((item) => item.value);
    },
    studentSelectOptions() {
      return [{ value: null, text: "Seleccionar estudiante..." }].concat(
        this.studentOptions.map((item) => ({ value: item.id, text: this.studentLabel(item) }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Seleccionar funcionario..." }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
      );
    },
    departmentOptions() {
      return [{ value: null, text: "Seleccionar departamento..." }].concat(
        (this.catalogs.departments || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    pendingCount() {
      return this.items.filter((item) => ["recibido_en_porteria", "derivado", "pendiente"].includes(item.status)).length;
    },
    deliveredCount() {
      return this.items.filter((item) => item.status === "entregado_al_destinatario").length;
    },
    receivedItemFields() {
      return [
        { key: "description", label: "Recepción", thClass: "received-th", tdClass: "received-td item-main-cell" },
        { key: "recipient", label: "Destinatario", thClass: "received-th", tdClass: "received-td" },
        { key: "status", label: "Estado", thClass: "received-th", tdClass: "received-td" },
        { key: "received_at", label: "Fecha", thClass: "received-th", tdClass: "received-td" },
        { key: "actions", label: "Acciones", thClass: "received-th text-end", tdClass: "received-td text-end" },
      ];
    },
  },
  watch: {
    "form.recipient_type"() {
      this.form.recipient_label = "";
      this.form.student_profile_id = null;
      this.form.staff_id = null;
      this.form.department_id = null;
      this.studentSearch = "";
      this.studentOptions = [];
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadItems();
  },
  methods: {
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/porter/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async searchStudents() {
      const search = this.studentSearch.trim();
      if (!search) {
        this.studentOptions = [];
        return;
      }

      try {
        const response = await axios.get("/api/porter/students", {
          params: { search, per_page: 8 },
        });
        this.studentOptions = response.data.data || [];
      } catch (error) {
        await this.showRequestError(error, "No se pudo buscar estudiantes");
      }
    },
    onFileChange(event) {
      this.form.attachment = event?.target?.files?.[0] || null;
    },
    async submit() {
      if (this.saving) return;

      const issues = this.validateForm();
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      const confirmed = await this.confirmSubmit();
      if (!confirmed) return;

      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        Object.entries(this.form).forEach(([key, value]) => {
          if (value === null || value === undefined || value === "") return;
          if (key === "attachment") {
            if (value) formData.append("attachment", value);
            return;
          }
          formData.append(key, value);
        });

        await axios.post("/api/porter/received-items", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        this.form = emptyForm();
        this.studentSearch = "";
        this.studentOptions = [];
        await this.loadItems(1);
        await Swal.fire({
          icon: "success",
          title: "Recepción registrada",
          text: "El objeto o documento quedó registrado en portería.",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la recepción");
      } finally {
        this.saving = false;
      }
    },
    async loadItems(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/received-items", {
          params: {
            page,
            search: this.filters.search || null,
            status: this.filters.status || null,
            item_type: this.filters.item_type || null,
            recipient_type: this.filters.recipient_type || null,
            date_from: this.filters.date_from || null,
            date_to: this.filters.date_to || null,
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingList = false;
      }
    },
    async updateStatus(item) {
      const options = (this.catalogs.received_item_statuses || [])
        .map((status) => {
          const selected = status.value === item.status ? "selected" : "";
          return `<option value="${this.escapeHtml(status.value)}" ${selected}>${this.escapeHtml(status.label)}</option>`;
        })
        .join("");

      const { value } = await Swal.fire({
        title: "Actualizar recepción",
        html: `
          <div class="swal-received-summary">
            <strong>${this.escapeHtml(item.description)}</strong>
            <span>${this.escapeHtml(this.recipientName(item))}</span>
          </div>
          <select id="item-status" class="swal2-select">${options}</select>
          <input id="delivered-to-name" class="swal2-input" placeholder="Persona que recibe" />
          <input id="delivered-to-rut" class="swal2-input" placeholder="RUT o identificación" />
          <textarea id="delivery-observations" class="swal2-textarea" placeholder="Observaciones de entrega"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Guardar estado",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: () => ({
          status: document.getElementById("item-status").value,
          delivered_to_name: document.getElementById("delivered-to-name").value.trim(),
          delivered_to_rut: document.getElementById("delivered-to-rut").value.trim(),
          delivery_observations: document.getElementById("delivery-observations").value.trim(),
        }),
      });

      if (!value) return;

      try {
        await axios.put(`/api/porter/received-items/${item.id}/status`, value);
        await this.loadItems(this.pagination.current_page || 1);
        await Swal.fire({
          icon: "success",
          title: "Estado actualizado",
          text: "La recepción fue actualizada correctamente.",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo actualizar el estado");
      }
    },
    openDetail(item) {
      this.selectedItem = item;
      this.showDetailModal = true;
    },
    resetFilters() {
      this.filters = emptyFilters();
      this.loadItems(1);
    },
    clearForm() {
      this.form = emptyForm();
      this.studentSearch = "";
      this.studentOptions = [];
    },
    validateForm() {
      const issues = [];
      if (!String(this.form.received_from_name || "").trim()) issues.push("Ingresa quién entrega.");
      if (!String(this.form.description || "").trim()) issues.push("Describe el objeto o documento recibido.");

      if (this.form.recipient_type === "student" && !this.form.student_profile_id) issues.push("Selecciona una estudiante destinataria.");
      if (this.form.recipient_type === "staff" && !this.form.staff_id) issues.push("Selecciona un funcionario destinatario.");
      if (this.form.recipient_type === "department" && !this.form.department_id) issues.push("Selecciona un departamento destinatario.");
      if (this.form.recipient_type === "other" && !String(this.form.recipient_label || "").trim()) issues.push("Indica el destinatario.");

      return issues;
    },
    async confirmSubmit() {
      const result = await Swal.fire({
        icon: "question",
        title: "Registrar recepción",
        html: `
          <div class="swal-received-confirm">
            <div><span>Recepción</span><strong>${this.escapeHtml(this.form.description)}</strong></div>
            <div><span>Destinatario</span><strong>${this.escapeHtml(this.selectedRecipientLabel())}</strong></div>
            <div><span>Entrega</span><strong>${this.escapeHtml(this.form.received_from_name)}</strong></div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar",
        cancelButtonText: "Revisar",
        reverseButtons: true,
      });
      return result.isConfirmed;
    },
    async showValidationAlert(issues) {
      await Swal.fire({
        icon: "warning",
        title: "Faltan datos para registrar",
        html: `<ul class="swal-received-validation">${issues.map((issue) => `<li>${this.escapeHtml(issue)}</li>`).join("")}</ul>`,
        confirmButtonText: "Entendido",
      });
    },
    async showRequestError(error, title) {
      const message = this.formatError(error);
      this.error = message;
      await Swal.fire({
        icon: "error",
        title,
        text: message,
        confirmButtonText: "Entendido",
      });
    },
    selectedRecipientLabel() {
      if (this.form.recipient_type === "student") {
        const student = this.studentOptions.find((item) => Number(item.id) === Number(this.form.student_profile_id));
        return student ? this.studentLabel(student) : "Estudiante seleccionada";
      }
      if (this.form.recipient_type === "staff") {
        const staff = (this.catalogs.staff || []).find((item) => Number(item.id) === Number(this.form.staff_id));
        return staff?.full_name || "Funcionario seleccionado";
      }
      if (this.form.recipient_type === "department") {
        const department = (this.catalogs.departments || []).find((item) => Number(item.id) === Number(this.form.department_id));
        return department?.name || "Departamento seleccionado";
      }
      return this.form.recipient_label || "Destinatario";
    },
    recipientName(item) {
      return item.recipient_label || item.student_profile?.full_name || item.department?.name || item.staff?.full_name || "-";
    },
    senderLine(item) {
      return [item.received_from_name, item.received_from_rut, item.received_from_phone].filter(Boolean).join(" · ") || "-";
    },
    studentLabel(item) {
      return [item.full_name, item.rut].filter(Boolean).join(" · ");
    },
    optionLabel(collection, value) {
      const option = (this.catalogs[collection] || []).find((item) => item.value === value);
      if (option?.label) return option.label;
      return String(value || "-").replace(/_/g, " ");
    },
    typeLabel(value) {
      return this.optionLabel("received_item_types", value);
    },
    statusLabel(value) {
      return this.optionLabel("received_item_statuses", value);
    },
    recipientTypeLabel(value) {
      return this.optionLabel("received_item_recipient_types", value);
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || {};
      const firstKey = Object.keys(errors)[0];
      return errors[firstKey]?.[0] || error?.response?.data?.message || error?.message || "Error desconocido";
    },
    escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },
  },
};
</script>

<template>
  <Layout>
    <div class="received-page">
      <div class="received-heading">
        <div>
          <h4 class="mb-0">Recepción de objetos y documentos</h4>
          <p class="mb-0 text-muted">Registro simple de entregas recibidas en portería.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(pagination.current_page || 1)">
            {{ loadingList ? "Actualizando..." : "Actualizar" }}
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="received-stats">
        <div class="received-stat-card">
          <span>Pendientes</span>
          <strong>{{ pendingCount }}</strong>
          <small>Recepciones visibles por entregar</small>
        </div>
        <div class="received-stat-card">
          <span>Entregadas</span>
          <strong>{{ deliveredCount }}</strong>
          <small>Registros cerrados en esta página</small>
        </div>
        <div class="received-stat-card">
          <span>Total filtrado</span>
          <strong>{{ pagination.total }}</strong>
          <small>Coincidencias del historial</small>
        </div>
      </div>

      <BCard class="received-panel">
        <div class="panel-title-row">
          <div>
            <h5 class="mb-1">Nueva recepción</h5>
            <p class="mb-0 text-muted">Completa solo lo esencial; el RUT queda como dato libre.</p>
          </div>
          <PorterStatusBadge :value="form.status" :label="statusLabel(form.status)" />
        </div>

        <div class="form-section">
          <h6>Destinatario</h6>
          <div class="row g-3">
            <div class="col-lg-3">
              <label class="form-label">Tipo</label>
              <BFormSelect v-model="form.recipient_type" :options="recipientOptions" />
            </div>
            <div v-if="form.recipient_type === 'student'" class="col-lg-5">
              <label class="form-label">Buscar estudiante</label>
              <div class="search-row">
                <BFormInput v-model="studentSearch" placeholder="Nombre o RUT" @keyup.enter="searchStudents" />
                <BButton variant="outline-primary" :disabled="loadingCatalogs" @click="searchStudents">Buscar</BButton>
              </div>
            </div>
            <div v-if="form.recipient_type === 'student'" class="col-lg-4">
              <label class="form-label">Seleccionar</label>
              <BFormSelect v-model="form.student_profile_id" :options="studentSelectOptions" />
            </div>
            <div v-if="form.recipient_type === 'staff'" class="col-lg-6">
              <label class="form-label">Funcionario</label>
              <BFormSelect v-model="form.staff_id" :options="staffOptions" />
            </div>
            <div v-if="form.recipient_type === 'department'" class="col-lg-6">
              <label class="form-label">Departamento</label>
              <BFormSelect v-model="form.department_id" :options="departmentOptions" />
            </div>
            <div v-if="form.recipient_type === 'other'" class="col-lg-6">
              <label class="form-label">Nombre destinatario</label>
              <BFormInput v-model="form.recipient_label" placeholder="Persona, curso o referencia" />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h6>Entrega</h6>
          <div class="row g-3">
            <div class="col-lg-4">
              <label class="form-label">Quien entrega</label>
              <BFormInput v-model="form.received_from_name" placeholder="Nombre de quien entrega" autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">RUT o identificación</label>
              <BFormInput v-model="form.received_from_rut" placeholder="Dato opcional" autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Teléfono (opcional)</label>
              <BFormInput v-model="form.received_from_phone" placeholder="Opcional" autocomplete="off" />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h6>Objeto o documento</h6>
          <div class="row g-3">
            <div class="col-lg-4">
              <label class="form-label">Tipo</label>
              <BFormSelect v-model="form.item_type" :options="typeOptions" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Estado inicial</label>
              <BFormSelect v-model="form.status" :options="initialStatusOptions" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Adjunto</label>
              <BFormInput type="file" @change="onFileChange" />
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <BFormTextarea v-model="form.description" rows="3" placeholder="Ej. Sobre, documento, material escolar, medicamento, encomienda..." />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.observations" rows="2" placeholder="Indicaciones de entrega, condiciones o comentarios" />
            </div>
          </div>
        </div>

        <div class="form-actions">
          <BButton variant="outline-secondary" :disabled="saving" @click="clearForm">Limpiar</BButton>
          <BButton variant="primary" :disabled="saving" @click="submit">
            {{ saving ? "Guardando..." : "Registrar recepción" }}
          </BButton>
        </div>
      </BCard>

      <BCard class="received-panel">
        <div class="panel-title-row history-title">
          <div>
            <h5 class="mb-1">Historial</h5>
            <p class="mb-0 text-muted">Resumen de recepciones. Usa “Ver” para revisar el detalle completo.</p>
          </div>
        </div>

        <div class="history-filters">
          <BFormInput class="filter-search" v-model="filters.search" placeholder="Buscar recepción, destinatario o entrega" @keyup.enter="loadItems(1)" />
          <BFormSelect class="filter-control" v-model="filters.status" :options="statusOptions" />
          <BFormSelect class="filter-control" v-model="filters.item_type" :options="typeFilterOptions" />
          <BFormSelect class="filter-control" v-model="filters.recipient_type" :options="recipientFilterOptions" />
          <BFormInput class="filter-date" v-model="filters.date_from" type="date" />
          <BFormInput class="filter-date" v-model="filters.date_to" type="date" />
          <div class="filter-actions">
            <BButton variant="primary" :disabled="loadingList" @click="loadItems(1)">Filtrar</BButton>
            <BButton variant="outline-secondary" :disabled="loadingList" @click="resetFilters">Limpiar</BButton>
          </div>
        </div>

        <BTable
          :items="items"
          :busy="loadingList"
          :fields="receivedItemFields"
          responsive
          hover
          show-empty
          table-class="received-table"
        >
          <template #table-busy>
            <LoadingState message="Cargando recepciones..." compact />
          </template>
          <template #empty>
            <div class="empty-table">No hay recepciones para los filtros seleccionados.</div>
          </template>
          <template #cell(description)="{ item }">
            <div class="fw-semibold">{{ item.description }}</div>
            <div class="small text-muted table-subline">{{ typeLabel(item.item_type) }} · {{ item.received_from_name || "Sin remitente" }}</div>
          </template>
          <template #cell(recipient)="{ item }">
            <div class="fw-semibold">{{ recipientName(item) }}</div>
            <div class="small text-muted">{{ recipientTypeLabel(item.recipient_type) }}</div>
          </template>
          <template #cell(status)="{ item }">
            <PorterStatusBadge :value="item.status" :label="statusLabel(item.status)" />
          </template>
          <template #cell(received_at)="{ item }">
            <div class="fw-semibold">{{ formatDateTime(item.received_at) }}</div>
            <div v-if="item.delivered_at" class="small text-muted">Entregado: {{ formatDateTime(item.delivered_at) }}</div>
          </template>
          <template #cell(actions)="{ item }">
            <div class="action-buttons">
              <BButton size="sm" variant="outline-secondary" @click="openDetail(item)">Ver</BButton>
              <BButton size="sm" variant="outline-primary" @click="updateStatus(item)">Estado</BButton>
            </div>
          </template>
        </BTable>

        <div class="pagination-row">
          <BPagination
            v-model="pagination.current_page"
            :total-rows="pagination.total"
            :per-page="pagination.per_page"
            @update:model-value="loadItems"
          />
        </div>
      </BCard>

      <BModal v-model="showDetailModal" title="Detalle de recepción" size="lg" hide-footer centered scrollable>
        <div v-if="selectedItem" class="received-detail-modal">
          <div class="detail-header">
            <div>
              <h5 class="mb-1">{{ selectedItem.description }}</h5>
              <p class="mb-0 text-muted">{{ formatDateTime(selectedItem.received_at) }}</p>
            </div>
            <div class="detail-badges">
              <PorterStatusBadge :value="selectedItem.item_type" :label="typeLabel(selectedItem.item_type)" />
              <PorterStatusBadge :value="selectedItem.status" :label="statusLabel(selectedItem.status)" />
            </div>
          </div>

          <div class="detail-grid">
            <div>
              <span>Destinatario</span>
              <strong>{{ recipientName(selectedItem) }}</strong>
              <small>{{ recipientTypeLabel(selectedItem.recipient_type) }}</small>
            </div>
            <div>
              <span>Entrega</span>
              <strong>{{ selectedItem.received_from_name || "-" }}</strong>
              <small>{{ [selectedItem.received_from_rut, selectedItem.received_from_phone].filter(Boolean).join(" · ") || "Sin datos adicionales" }}</small>
            </div>
            <div>
              <span>Registro</span>
              <strong>{{ selectedItem.registered_by?.name || "Usuario no informado" }}</strong>
              <small>{{ formatDateTime(selectedItem.received_at) }}</small>
            </div>
          </div>

          <div class="detail-block">
            <span>Observaciones</span>
            <p>{{ selectedItem.observations || "Sin observaciones." }}</p>
          </div>

          <div v-if="selectedItem.delivered_at || selectedItem.delivered_to_name || selectedItem.delivery_observations" class="detail-grid">
            <div>
              <span>Entregado a</span>
              <strong>{{ selectedItem.delivered_to_name || "-" }}</strong>
              <small>{{ selectedItem.delivered_to_rut || "Sin identificación" }}</small>
            </div>
            <div>
              <span>Entregado por</span>
              <strong>{{ selectedItem.delivered_by?.name || "Usuario no informado" }}</strong>
              <small>{{ formatDateTime(selectedItem.delivered_at) }}</small>
            </div>
            <div>
              <span>Observación de entrega</span>
              <strong>{{ selectedItem.delivery_observations || "-" }}</strong>
            </div>
          </div>

          <div v-if="selectedItem.attachment_url" class="detail-block">
            <span>Adjunto</span>
            <a :href="selectedItem.attachment_url" target="_blank" rel="noopener">{{ selectedItem.attachment_original_name || "Ver adjunto" }}</a>
          </div>

          <div class="detail-actions">
            <BButton variant="outline-primary" @click="updateStatus(selectedItem)">Actualizar estado</BButton>
            <BButton variant="primary" @click="showDetailModal = false">Cerrar</BButton>
          </div>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.received-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.received-heading,
.panel-title-row,
.form-actions,
.pagination-row,
.detail-header,
.detail-actions {
  align-items: center;
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

.received-heading {
  flex-wrap: wrap;
}

.received-heading h4,
.panel-title-row h5,
.detail-header h5 {
  color: #343a46;
  font-weight: 700;
}

.heading-actions,
.action-buttons,
.detail-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.received-stats {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.received-stat-card,
.received-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.received-stat-card {
  min-height: 116px;
  padding: 1.15rem 1.35rem;
}

.received-stat-card span,
.received-stat-card small {
  color: #747b91;
  display: block;
}

.received-stat-card strong {
  color: #343a46;
  display: block;
  font-size: 2.4rem;
  line-height: 1;
  margin: 0.45rem 0 0.35rem;
}

.received-panel :deep(.card-body) {
  padding: 1.35rem;
}

.form-section {
  border-top: 1px solid rgba(217, 226, 246, 0.88);
  margin-top: 1rem;
  padding-top: 1rem;
}

.form-section h6 {
  color: #747b91;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0;
  margin-bottom: 0.85rem;
  text-transform: uppercase;
}

.form-label,
.history-filters :deep(.form-control),
.history-filters :deep(.form-select) {
  font-weight: 600;
}

.search-row {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: minmax(0, 1fr) auto;
}

.form-actions {
  border-top: 1px solid rgba(217, 226, 246, 0.88);
  justify-content: flex-end;
  margin-top: 1rem;
  padding-top: 1rem;
}

.history-title {
  margin-bottom: 1rem;
}

.history-filters {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  margin-bottom: 1rem;
}

.history-filters > * {
  min-width: 0;
}

.filter-search {
  grid-column: span 3;
}

.filter-control,
.filter-date {
  grid-column: span 2;
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
  grid-column: span 3;
  justify-content: flex-end;
  min-width: 0;
}

.filter-actions .btn {
  min-width: 0;
  white-space: nowrap;
}

:deep(.received-table) {
  table-layout: fixed;
  width: 100%;
}

:deep(.received-table th) {
  border-bottom-color: #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.received-table td) {
  border-color: #eef2fa;
  color: #343a46;
  overflow-wrap: anywhere;
  vertical-align: middle;
}

:deep(.item-main-cell) {
  width: 36%;
}

:deep(.received-table th:nth-child(2)),
:deep(.received-table td:nth-child(2)) {
  width: 22%;
}

:deep(.received-table th:nth-child(3)),
:deep(.received-table td:nth-child(3)) {
  width: 18%;
}

:deep(.received-table th:nth-child(4)),
:deep(.received-table td:nth-child(4)) {
  width: 14%;
}

:deep(.received-table th:nth-child(5)),
:deep(.received-table td:nth-child(5)) {
  width: 10%;
}

.table-subline {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.empty-table {
  color: #747b91;
  padding: 1rem 0;
  text-align: center;
}

.pagination-row {
  justify-content: flex-end;
  margin-top: 1rem;
}

.received-detail-modal {
  display: grid;
  gap: 1rem;
}

.detail-header {
  align-items: flex-start;
  border-bottom: 1px solid #e7ecf8;
  padding-bottom: 1rem;
}

.detail-badges {
  justify-content: flex-end;
}

.detail-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.detail-grid div,
.detail-block {
  background: #f8faff;
  border: 1px solid #e7ecf8;
  border-radius: 0.5rem;
  padding: 0.9rem 1rem;
}

.detail-grid span,
.detail-block span {
  color: #747b91;
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  margin-bottom: 0.3rem;
}

.detail-grid strong,
.detail-grid small,
.detail-block p,
.detail-block a {
  overflow-wrap: anywhere;
}

.detail-grid strong {
  color: #343a46;
  display: block;
}

.detail-grid small {
  color: #747b91;
  display: block;
}

.detail-block p {
  color: #343a46;
  margin: 0;
  white-space: pre-wrap;
}

.detail-actions {
  justify-content: flex-end;
}

:global(.swal-received-summary),
:global(.swal-received-confirm) {
  text-align: left;
}

:global(.swal-received-summary) {
  background: #f5f7fb;
  border: 1px solid #e4e9f6;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  padding: 0.85rem;
}

:global(.swal-received-summary strong),
:global(.swal-received-summary span),
:global(.swal-received-confirm span),
:global(.swal-received-confirm strong) {
  display: block;
}

:global(.swal-received-summary span),
:global(.swal-received-confirm span) {
  color: #747b91;
}

:global(.swal-received-confirm) {
  display: grid;
  gap: 0.7rem;
}

:global(.swal-received-validation) {
  margin: 0;
  padding-left: 1.25rem;
  text-align: left;
}

@media (max-width: 1399.98px) {
  .history-filters {
    grid-template-columns: repeat(6, minmax(0, 1fr));
  }

  .filter-search,
  .filter-control,
  .filter-date,
  .filter-actions {
    grid-column: span 3;
  }
}

@media (max-width: 991.98px) {
  .history-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filter-search,
  .filter-control,
  .filter-date,
  .filter-actions {
    grid-column: span 1;
  }

  .detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .received-stats,
  .history-filters,
  .detail-grid,
  .search-row {
    grid-template-columns: 1fr;
  }

  .received-heading,
  .panel-title-row,
  .form-actions,
  .detail-header {
    align-items: stretch;
    flex-direction: column;
  }

  .heading-actions,
  .heading-actions .btn,
  .form-actions .btn,
  .filter-actions .btn,
  .history-filters .btn,
  .search-row .btn {
    width: 100%;
  }

  .filter-actions {
    flex-direction: column;
  }

  .detail-badges {
    justify-content: flex-start;
  }
}
</style>
