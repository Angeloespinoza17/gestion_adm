<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  movement_type: "recepcion_mercaderia",
  department_id: null,
  responsible_staff_id: null,
  contact_name: "",
  contact_rut: "",
  company: "",
  phone: "",
  vehicle_plate: "",
  goods_detail: "",
  quantity: "",
  unit: "",
  document_type: "",
  document_number: "",
  status: "recibido_en_porteria",
  observations: "",
  attachment: null,
});

const emptyFilters = () => ({
  search: "",
  status: null,
  movement_type: null,
  department_id: null,
  date_from: "",
  date_to: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      saving: false,
      loadingList: false,
      error: null,
      showDetailModal: false,
      selectedMovement: null,
      catalogs: {
        goods_movement_types: [],
        goods_statuses: [],
        goods_document_types: [],
        departments: [],
        staff: [],
      },
      form: emptyForm(),
      movements: [],
      filters: emptyFilters(),
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    movementTypeOptions() {
      return (this.catalogs.goods_movement_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    movementTypeFilterOptions() {
      return [{ value: null, text: "Todos los movimientos" }].concat(this.movementTypeOptions);
    },
    statusOptions() {
      return [{ value: null, text: "Todos los estados" }].concat(
        (this.catalogs.goods_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    initialStatusOptions() {
      return this.statusOptions.filter((item) => item.value);
    },
    documentTypeOptions() {
      return [{ value: "", text: "Sin documento" }].concat(
        (this.catalogs.goods_document_types || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    departmentOptions() {
      return [{ value: null, text: "Seleccionar departamento..." }].concat(
        (this.catalogs.departments || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    departmentFilterOptions() {
      return [{ value: null, text: "Todos los departamentos" }].concat(
        (this.catalogs.departments || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Seleccionar responsable..." }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
      );
    },
    pendingCount() {
      return this.movements.filter((item) => ["recibido_en_porteria", "derivado_a_departamento", "pendiente"].includes(item.status)).length;
    },
    deliveredCount() {
      return this.movements.filter((item) => item.status === "entregado_a_responsable").length;
    },
    goodsFields() {
      return [
        { key: "goods_detail", label: "Mercadería", thClass: "goods-th", tdClass: "goods-td goods-main-cell" },
        { key: "destination", label: "Destino", thClass: "goods-th", tdClass: "goods-td" },
        { key: "status", label: "Estado", thClass: "goods-th", tdClass: "goods-td" },
        { key: "moved_at", label: "Fecha", thClass: "goods-th", tdClass: "goods-td" },
        { key: "actions", label: "Acciones", thClass: "goods-th text-end", tdClass: "goods-td text-end" },
      ];
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadMovements();
  },
  methods: {
    async loadCatalogs() {
      try {
        const response = await axios.get("/api/porter/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
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

        await axios.post("/api/porter/goods-movements", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        this.form = emptyForm();
        await this.loadMovements(1);
        await Swal.fire({
          icon: "success",
          title: "Movimiento registrado",
          text: "La mercadería quedó registrada en portería.",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la mercadería");
      } finally {
        this.saving = false;
      }
    },
    async loadMovements(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/goods-movements", {
          params: {
            page,
            search: this.filters.search || null,
            status: this.filters.status || null,
            movement_type: this.filters.movement_type || null,
            department_id: this.filters.department_id || null,
            date_from: this.filters.date_from || null,
            date_to: this.filters.date_to || null,
          },
        });
        this.movements = response.data.data || [];
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
      const options = (this.catalogs.goods_statuses || [])
        .map((status) => {
          const selected = status.value === item.status ? "selected" : "";
          return `<option value="${this.escapeHtml(status.value)}" ${selected}>${this.escapeHtml(status.label)}</option>`;
        })
        .join("");

      const { value } = await Swal.fire({
        title: "Actualizar mercadería",
        html: `
          <div class="swal-goods-summary">
            <strong>${this.escapeHtml(item.goods_detail)}</strong>
            <span>${this.escapeHtml(this.destinationLine(item))}</span>
          </div>
          <select id="goods-status" class="swal2-select">${options}</select>
          <input id="received-by-name" class="swal2-input" placeholder="Responsable que recibe" />
          <input id="received-by-identifier" class="swal2-input" placeholder="RUT o identificación" />
          <textarea id="delivery-observations" class="swal2-textarea" placeholder="Observaciones de entrega"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Guardar estado",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: () => {
          const status = document.getElementById("goods-status").value;
          const receivedByName = document.getElementById("received-by-name").value.trim();

          if (status === "entregado_a_responsable" && !receivedByName) {
            Swal.showValidationMessage("Indica la persona responsable que recibe.");
            return false;
          }

          return {
            status,
            received_by_name: receivedByName,
            received_by_identifier: document.getElementById("received-by-identifier").value.trim(),
            delivery_observations: document.getElementById("delivery-observations").value.trim(),
          };
        },
      });

      if (!value) return;

      try {
        const response = await axios.put(`/api/porter/goods-movements/${item.id}/status`, value);
        await this.loadMovements(this.pagination.current_page || 1);
        if (this.selectedMovement?.id === item.id) {
          this.selectedMovement = response.data?.data || this.movements.find((movement) => movement.id === item.id) || item;
        }
        await Swal.fire({
          icon: "success",
          title: "Estado actualizado",
          text: "El movimiento de mercadería fue actualizado.",
          timer: 1600,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo actualizar el estado");
      }
    },
    openDetail(item) {
      this.selectedMovement = item;
      this.showDetailModal = true;
    },
    resetFilters() {
      this.filters = emptyFilters();
      this.loadMovements(1);
    },
    clearForm() {
      this.form = emptyForm();
    },
    validateForm() {
      const issues = [];
      if (!String(this.form.contact_name || "").trim()) issues.push("Ingresa el proveedor o persona asociada.");
      if (!String(this.form.goods_detail || "").trim()) issues.push("Describe la mercadería.");
      return issues;
    },
    async confirmSubmit() {
      const result = await Swal.fire({
        icon: "question",
        title: "Registrar movimiento",
        html: `
          <div class="swal-goods-confirm">
            <div><span>Movimiento</span><strong>${this.escapeHtml(this.movementTypeLabel(this.form.movement_type))}</strong></div>
            <div><span>Mercadería</span><strong>${this.escapeHtml(this.form.goods_detail)}</strong></div>
            <div><span>Contacto</span><strong>${this.escapeHtml(this.form.contact_name)}</strong></div>
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
        html: `<ul class="swal-goods-validation">${issues.map((issue) => `<li>${this.escapeHtml(issue)}</li>`).join("")}</ul>`,
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
    catalogLabel(collection, value) {
      const option = (this.catalogs[collection] || []).find((item) => item.value === value);
      if (option?.label) return option.label;
      return String(value || "-").replace(/_/g, " ");
    },
    movementTypeLabel(value) {
      return this.catalogLabel("goods_movement_types", value);
    },
    statusLabel(value) {
      return this.catalogLabel("goods_statuses", value);
    },
    documentTypeLabel(value) {
      return value ? this.catalogLabel("goods_document_types", value) : "Sin documento";
    },
    destinationLine(item) {
      return [item.department?.name, item.responsible_staff?.full_name].filter(Boolean).join(" · ") || "Sin destino asignado";
    },
    contactLine(item) {
      return [item.contact_name, item.company].filter(Boolean).join(" · ") || "Contacto no informado";
    },
    contactExtraLine(item) {
      return [item.contact_rut, item.phone, item.vehicle_plate ? `Patente ${item.vehicle_plate}` : null].filter(Boolean).join(" · ") || "Sin datos adicionales";
    },
    documentLine(item) {
      return [this.documentTypeLabel(item.document_type), item.document_number].filter(Boolean).join(" · ");
    },
    formatQuantity(item) {
      if (item.quantity === null || item.quantity === undefined || item.quantity === "") return "Sin cantidad";
      const number = Number(item.quantity);
      const quantity = Number.isFinite(number)
        ? number.toLocaleString("es-CL", { maximumFractionDigits: 2 })
        : String(item.quantity);
      return [quantity, item.unit].filter(Boolean).join(" ");
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
    <div class="goods-page">
      <div class="goods-heading">
        <div>
          <h4 class="mb-0">Mercadería</h4>
          <p class="mb-0 text-muted">Recepción, derivación y entrega de mercadería institucional.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="primary" :disabled="loadingList" @click="loadMovements(pagination.current_page || 1)">
            {{ loadingList ? "Actualizando..." : "Actualizar" }}
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="goods-stats">
        <div class="goods-stat-card">
          <span>Pendientes</span>
          <strong>{{ pendingCount }}</strong>
          <small>Movimientos abiertos en esta página</small>
        </div>
        <div class="goods-stat-card">
          <span>Entregadas</span>
          <strong>{{ deliveredCount }}</strong>
          <small>Registros cerrados visibles</small>
        </div>
        <div class="goods-stat-card">
          <span>Total filtrado</span>
          <strong>{{ pagination.total }}</strong>
          <small>Coincidencias del historial</small>
        </div>
      </div>

      <BCard class="goods-panel">
        <div class="panel-title-row">
          <div>
            <h5 class="mb-1">Nuevo movimiento</h5>
            <p class="mb-0 text-muted">Datos mínimos de trazabilidad en portería.</p>
          </div>
          <PorterStatusBadge :value="form.movement_type" :label="movementTypeLabel(form.movement_type)" />
        </div>

        <div class="form-section">
          <h6>Movimiento</h6>
          <div class="row g-3">
            <div class="col-lg-3">
              <label class="form-label">Tipo</label>
              <BFormSelect v-model="form.movement_type" :options="movementTypeOptions" />
            </div>
            <div class="col-lg-3">
              <label class="form-label">Estado inicial</label>
              <BFormSelect v-model="form.status" :options="initialStatusOptions" />
            </div>
            <div class="col-lg-3">
              <label class="form-label">Departamento</label>
              <BFormSelect v-model="form.department_id" :options="departmentOptions" />
            </div>
            <div class="col-lg-3">
              <label class="form-label">Responsable interno</label>
              <BFormSelect v-model="form.responsible_staff_id" :options="staffOptions" />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h6>Contacto</h6>
          <div class="row g-3">
            <div class="col-lg-4">
              <label class="form-label">Proveedor o persona</label>
              <BFormInput v-model="form.contact_name" placeholder="Nombre y apellido" autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Empresa</label>
              <BFormInput v-model="form.company" placeholder="Empresa u origen" autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">RUT o identificación (opcional)</label>
              <BFormInput v-model="form.contact_rut" placeholder="Dato libre" autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Teléfono (opcional)</label>
              <BFormInput v-model="form.phone" placeholder="+56 9..." autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Patente</label>
              <BFormInput v-model="form.vehicle_plate" placeholder="ABCD12" autocomplete="off" />
            </div>
          </div>
        </div>

        <div class="form-section">
          <h6>Mercadería</h6>
          <div class="row g-3">
            <div class="col-lg-8">
              <label class="form-label">Detalle</label>
              <BFormTextarea v-model="form.goods_detail" rows="3" placeholder="Descripción breve de lo recibido, retirado o entregado" />
            </div>
            <div class="col-lg-2">
              <label class="form-label">Cantidad</label>
              <BFormInput v-model="form.quantity" type="number" min="0" step="0.01" />
            </div>
            <div class="col-lg-2">
              <label class="form-label">Unidad</label>
              <BFormInput v-model="form.unit" placeholder="cajas, unidades..." autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Documento</label>
              <BFormSelect v-model="form.document_type" :options="documentTypeOptions" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Número de documento</label>
              <BFormInput v-model="form.document_number" autocomplete="off" />
            </div>
            <div class="col-lg-4">
              <label class="form-label">Adjunto</label>
              <BFormInput type="file" @change="onFileChange" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.observations" rows="2" placeholder="Destino, condición de entrega o comentario relevante" />
            </div>
          </div>
        </div>

        <div class="form-actions">
          <BButton variant="outline-secondary" :disabled="saving" @click="clearForm">Limpiar</BButton>
          <BButton variant="primary" :disabled="saving" @click="submit">
            {{ saving ? "Guardando..." : "Registrar movimiento" }}
          </BButton>
        </div>
      </BCard>

      <BCard class="goods-panel">
        <div class="panel-title-row history-title">
          <div>
            <h5 class="mb-1">Historial</h5>
            <p class="mb-0 text-muted">Resumen operativo de movimientos filtrados.</p>
          </div>
        </div>

        <div class="history-filters">
          <BFormInput class="filter-search" v-model="filters.search" placeholder="Buscar mercadería, proveedor o documento" @keyup.enter="loadMovements(1)" />
          <BFormSelect class="filter-control" v-model="filters.status" :options="statusOptions" />
          <BFormSelect class="filter-control" v-model="filters.movement_type" :options="movementTypeFilterOptions" />
          <BFormSelect class="filter-control" v-model="filters.department_id" :options="departmentFilterOptions" />
          <BFormInput class="filter-date" v-model="filters.date_from" type="date" />
          <BFormInput class="filter-date" v-model="filters.date_to" type="date" />
          <div class="filter-actions">
            <BButton variant="primary" :disabled="loadingList" @click="loadMovements(1)">Filtrar</BButton>
            <BButton variant="outline-secondary" :disabled="loadingList" @click="resetFilters">Limpiar</BButton>
          </div>
        </div>

        <BTable
          :items="movements"
          :busy="loadingList"
          :fields="goodsFields"
          responsive
          hover
          show-empty
          table-class="goods-table"
        >
          <template #table-busy>
            <LoadingState message="Cargando mercadería..." compact />
          </template>
          <template #empty>
            <div class="empty-table">No hay movimientos para los filtros seleccionados.</div>
          </template>
          <template #cell(goods_detail)="{ item }">
            <div class="fw-semibold">{{ item.goods_detail }}</div>
            <div class="small text-muted table-subline">
              {{ movementTypeLabel(item.movement_type) }} · {{ formatQuantity(item) }} · {{ contactLine(item) }}
            </div>
          </template>
          <template #cell(destination)="{ item }">
            <div class="fw-semibold">{{ item.department?.name || "Sin departamento" }}</div>
            <div class="small text-muted table-subline">{{ item.responsible_staff?.full_name || "Sin responsable asignado" }}</div>
          </template>
          <template #cell(status)="{ item }">
            <PorterStatusBadge :value="item.status" :label="statusLabel(item.status)" />
          </template>
          <template #cell(moved_at)="{ item }">
            <div class="fw-semibold">{{ formatDateTime(item.moved_at) }}</div>
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
            @update:model-value="loadMovements"
          />
        </div>
      </BCard>

      <BModal v-model="showDetailModal" title="Detalle de mercadería" size="lg" hide-footer centered scrollable>
        <div v-if="selectedMovement" class="goods-detail-modal">
          <div class="detail-header">
            <div>
              <h5 class="mb-1">{{ selectedMovement.goods_detail }}</h5>
              <p class="mb-0 text-muted">{{ formatDateTime(selectedMovement.moved_at) }}</p>
            </div>
            <div class="detail-badges">
              <PorterStatusBadge :value="selectedMovement.movement_type" :label="movementTypeLabel(selectedMovement.movement_type)" />
              <PorterStatusBadge :value="selectedMovement.status" :label="statusLabel(selectedMovement.status)" />
            </div>
          </div>

          <div class="detail-grid">
            <div>
              <span>Contacto</span>
              <strong>{{ contactLine(selectedMovement) }}</strong>
              <small>{{ contactExtraLine(selectedMovement) }}</small>
            </div>
            <div>
              <span>Destino</span>
              <strong>{{ selectedMovement.department?.name || "Sin departamento" }}</strong>
              <small>{{ selectedMovement.responsible_staff?.full_name || "Sin responsable asignado" }}</small>
            </div>
            <div>
              <span>Cantidad</span>
              <strong>{{ formatQuantity(selectedMovement) }}</strong>
              <small>{{ documentLine(selectedMovement) }}</small>
            </div>
            <div>
              <span>Registro</span>
              <strong>{{ selectedMovement.registered_by?.name || "Usuario no informado" }}</strong>
              <small>{{ formatDateTime(selectedMovement.moved_at) }}</small>
            </div>
            <div>
              <span>Entrega</span>
              <strong>{{ selectedMovement.received_by_name || "Sin entrega registrada" }}</strong>
              <small>{{ selectedMovement.received_by_identifier || formatDateTime(selectedMovement.delivered_at) }}</small>
            </div>
            <div>
              <span>Entregado por</span>
              <strong>{{ selectedMovement.delivered_by?.name || "Usuario no informado" }}</strong>
              <small>{{ formatDateTime(selectedMovement.delivered_at) }}</small>
            </div>
          </div>

          <div class="detail-block">
            <span>Observaciones</span>
            <p>{{ selectedMovement.observations || "Sin observaciones." }}</p>
          </div>

          <div v-if="selectedMovement.delivery_observations" class="detail-block">
            <span>Observación de entrega</span>
            <p>{{ selectedMovement.delivery_observations }}</p>
          </div>

          <div v-if="selectedMovement.attachment_url" class="detail-block">
            <span>Adjunto</span>
            <a :href="selectedMovement.attachment_url" target="_blank" rel="noopener">{{ selectedMovement.attachment_original_name || "Ver adjunto" }}</a>
          </div>

          <div class="detail-actions">
            <BButton variant="outline-primary" @click="updateStatus(selectedMovement)">Actualizar estado</BButton>
            <BButton variant="primary" @click="showDetailModal = false">Cerrar</BButton>
          </div>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.goods-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.goods-heading,
.panel-title-row,
.form-actions,
.pagination-row,
.detail-header,
.detail-actions {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
}

.goods-heading {
  flex-wrap: wrap;
}

.goods-heading h4,
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

.goods-stats {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.goods-stat-card,
.goods-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.goods-stat-card {
  min-height: 116px;
  padding: 1.15rem 1.35rem;
}

.goods-stat-card span,
.goods-stat-card small {
  color: #747b91;
  display: block;
}

.goods-stat-card strong {
  color: #343a46;
  display: block;
  font-size: 2.4rem;
  line-height: 1;
  margin: 0.45rem 0 0.35rem;
}

.goods-panel :deep(.card-body) {
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

:deep(.goods-table) {
  table-layout: fixed;
  width: 100%;
}

:deep(.goods-table th) {
  border-bottom-color: #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.goods-table td) {
  border-color: #eef2fa;
  color: #343a46;
  overflow-wrap: anywhere;
  vertical-align: middle;
}

:deep(.goods-main-cell) {
  width: 36%;
}

:deep(.goods-table th:nth-child(2)),
:deep(.goods-table td:nth-child(2)) {
  width: 24%;
}

:deep(.goods-table th:nth-child(3)),
:deep(.goods-table td:nth-child(3)) {
  width: 15%;
}

:deep(.goods-table th:nth-child(4)),
:deep(.goods-table td:nth-child(4)) {
  width: 15%;
}

:deep(.goods-table th:nth-child(5)),
:deep(.goods-table td:nth-child(5)) {
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

.goods-detail-modal {
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

:global(.swal-goods-summary),
:global(.swal-goods-confirm) {
  text-align: left;
}

:global(.swal-goods-summary) {
  background: #f5f7fb;
  border: 1px solid #e4e9f6;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  padding: 0.85rem;
}

:global(.swal-goods-summary strong),
:global(.swal-goods-summary span),
:global(.swal-goods-confirm span),
:global(.swal-goods-confirm strong) {
  display: block;
}

:global(.swal-goods-summary span),
:global(.swal-goods-confirm span) {
  color: #747b91;
}

:global(.swal-goods-confirm) {
  display: grid;
  gap: 0.7rem;
}

:global(.swal-goods-validation) {
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
  .goods-stats,
  .history-filters,
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .goods-heading,
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
  .history-filters .btn {
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
