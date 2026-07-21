<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  shift_label: "",
  category: "novedad",
  priority: "media",
  status: "registrado",
  title: "",
  detail: "",
});

const emptyFilters = () => ({
  search: "",
  category: null,
  priority: null,
  status: null,
  date_from: "",
  date_to: "",
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      saving: false,
      loadingList: false,
      showDetailModal: false,
      selectedEntry: null,
      error: null,
      catalogs: { daily_log_categories: [], daily_log_priorities: [], daily_log_statuses: [] },
      form: emptyForm(),
      items: [],
      filters: emptyFilters(),
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    categoryOptions() {
      return [{ value: null, text: "Todas las categorías" }].concat(
        (this.catalogs.daily_log_categories || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    priorityOptions() {
      return [{ value: null, text: "Todas las prioridades" }].concat(
        (this.catalogs.daily_log_priorities || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    statusOptions() {
      return [{ value: null, text: "Todos los estados" }].concat(
        (this.catalogs.daily_log_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    formCategoryOptions() {
      return (this.catalogs.daily_log_categories || []).map((item) => ({ value: item.value, text: item.label }));
    },
    formPriorityOptions() {
      return (this.catalogs.daily_log_priorities || []).map((item) => ({ value: item.value, text: item.label }));
    },
    formStatusOptions() {
      return (this.catalogs.daily_log_statuses || []).map((item) => ({ value: item.value, text: item.label }));
    },
    highPriorityCount() {
      return this.items.filter((item) => item.priority === "alta").length;
    },
    highlightedCount() {
      return this.items.filter((item) => item.status === "destacado").length;
    },
    logFields() {
      return [
        { key: "title", label: "Registro", thClass: "daily-log-th", tdClass: "daily-log-td log-title-cell" },
        { key: "category", label: "Categoría", thClass: "daily-log-th", tdClass: "daily-log-td" },
        { key: "priority", label: "Prioridad", thClass: "daily-log-th", tdClass: "daily-log-td" },
        { key: "status", label: "Estado", thClass: "daily-log-th", tdClass: "daily-log-td" },
        { key: "logged_at", label: "Fecha", thClass: "daily-log-th", tdClass: "daily-log-td" },
        { key: "actions", label: "Acciones", thClass: "daily-log-th text-end", tdClass: "daily-log-td text-end" },
      ];
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadItems();
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
    async submit() {
      if (this.saving) return;

      const issues = this.validateLogForm();
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      const confirmed = await this.confirmSubmit();
      if (!confirmed) return;

      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/porter/daily-log", this.form);
        this.form = emptyForm();
        await this.loadItems(1);
        await Swal.fire({
          icon: "success",
          title: "Entrada registrada",
          text: "La bitácora fue guardada correctamente.",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la entrada");
      } finally {
        this.saving = false;
      }
    },
    async loadItems(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/daily-log", {
          params: {
            page,
            search: this.filters.search || null,
            category: this.filters.category || null,
            priority: this.filters.priority || null,
            status: this.filters.status || null,
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
    openDetail(item) {
      this.selectedEntry = item;
      this.showDetailModal = true;
    },
    resetFilters() {
      this.filters = emptyFilters();
      this.loadItems(1);
    },
    clearForm() {
      this.form = emptyForm();
    },
    validateLogForm() {
      const issues = [];
      if (!String(this.form.title || "").trim()) issues.push("Ingresa un título para el registro.");
      if (!String(this.form.detail || "").trim()) issues.push("Ingresa el detalle de la bitácora.");
      return issues;
    },
    async confirmSubmit() {
      const result = await Swal.fire({
        icon: "question",
        title: "Registrar entrada de bitácora",
        html: `
          <div class="swal-log-confirm">
            <div><span>Título</span><strong>${this.escapeHtml(this.form.title)}</strong></div>
            <div><span>Categoría</span><strong>${this.escapeHtml(this.categoryLabel(this.form.category))}</strong></div>
            <div><span>Prioridad</span><strong>${this.escapeHtml(this.priorityLabel(this.form.priority))}</strong></div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar entrada",
        cancelButtonText: "Revisar",
        reverseButtons: true,
      });
      return result.isConfirmed;
    },
    async showValidationAlert(issues) {
      await Swal.fire({
        icon: "warning",
        title: "Faltan datos para registrar",
        html: `<ul class="swal-log-validation">${issues.map((issue) => `<li>${this.escapeHtml(issue)}</li>`).join("")}</ul>`,
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
    optionLabel(collection, value) {
      const option = (this.catalogs[collection] || []).find((item) => item.value === value);
      if (option?.label) return option.label;
      return String(value || "-").replace(/_/g, " ");
    },
    categoryLabel(value) {
      return this.optionLabel("daily_log_categories", value);
    },
    priorityLabel(value) {
      return this.optionLabel("daily_log_priorities", value);
    },
    statusLabel(value) {
      return this.optionLabel("daily_log_statuses", value);
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
    <div class="daily-log-page">
      <div class="daily-log-heading">
        <div>
          <h4 class="mb-0">Bitácora diaria de portería</h4>
          <p class="mb-0 text-muted">Registro continuo de novedades, incidencias y observaciones del turno.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(pagination.current_page || 1)">
            {{ loadingList ? "Actualizando..." : "Actualizar" }}
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="daily-log-stats">
        <div class="daily-log-stat-card">
          <span>Total filtrado</span>
          <strong>{{ pagination.total }}</strong>
          <small>Entradas encontradas</small>
        </div>
        <div class="daily-log-stat-card">
          <span>Alta prioridad</span>
          <strong>{{ highPriorityCount }}</strong>
          <small>Registros visibles en esta página</small>
        </div>
        <div class="daily-log-stat-card">
          <span>Destacados</span>
          <strong>{{ highlightedCount }}</strong>
          <small>Seguimiento especial del turno</small>
        </div>
      </div>

      <BCard class="daily-log-panel log-form-panel">
        <div class="panel-title-row">
          <div>
            <h5 class="mb-1">Nueva entrada</h5>
            <p class="mb-0 text-muted">Guarda el detalle operativo sin saturar la tabla principal.</p>
          </div>
          <PorterStatusBadge :value="form.priority" :label="priorityLabel(form.priority)" />
        </div>

        <div class="row g-3 mt-1">
          <div class="col-lg-3">
            <label class="form-label">Turno</label>
            <BFormInput v-model="form.shift_label" placeholder="Mañana / Tarde / Noche" autocomplete="off" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Categoría</label>
            <BFormSelect v-model="form.category" :options="formCategoryOptions" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Prioridad</label>
            <BFormSelect v-model="form.priority" :options="formPriorityOptions" />
          </div>
          <div class="col-lg-3">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="form.status" :options="formStatusOptions" />
          </div>
          <div class="col-12">
            <label class="form-label">Título</label>
            <BFormInput v-model="form.title" placeholder="Resumen breve de la novedad" autocomplete="off" />
          </div>
          <div class="col-12">
            <label class="form-label">Detalle</label>
            <BFormTextarea v-model="form.detail" rows="4" placeholder="Describe lo ocurrido, personas involucradas, acciones tomadas y observaciones de continuidad" />
          </div>
          <div class="col-12 form-actions">
            <BButton variant="outline-secondary" :disabled="saving" @click="clearForm">Limpiar</BButton>
            <BButton variant="primary" :disabled="saving" @click="submit">
              {{ saving ? "Guardando..." : "Registrar entrada" }}
            </BButton>
          </div>
        </div>
      </BCard>

      <BCard class="daily-log-panel">
        <div class="panel-title-row history-title">
          <div>
            <h5 class="mb-1">Bitácora registrada</h5>
            <p class="mb-0 text-muted">La tabla muestra solo el resumen. Abre el modal para revisar el detalle completo.</p>
          </div>
        </div>

        <div class="history-filters">
          <BFormInput v-model="filters.search" placeholder="Buscar por título o detalle" @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.category" :options="categoryOptions" />
          <BFormSelect v-model="filters.priority" :options="priorityOptions" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BFormInput v-model="filters.date_from" type="date" />
          <BFormInput v-model="filters.date_to" type="date" />
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(1)">Filtrar</BButton>
          <BButton variant="outline-secondary" :disabled="loadingList" @click="resetFilters">Limpiar</BButton>
        </div>

        <BTable
          :items="items"
          :busy="loadingList"
          :fields="logFields"
          responsive
          hover
          show-empty
          table-class="daily-log-table"
        >
          <template #table-busy><LoadingState message="Cargando bitácora..." compact /></template>
          <template #empty>
            <div class="empty-table">No hay entradas para los filtros seleccionados.</div>
          </template>
          <template #cell(title)="{ item }">
            <div class="fw-semibold">{{ item.title }}</div>
            <div class="small text-muted">
              {{ item.shift_label || "Sin turno" }} · {{ item.registered_by?.name || "Usuario no informado" }}
            </div>
          </template>
          <template #cell(category)="{ item }">
            <PorterStatusBadge :value="item.category" :label="categoryLabel(item.category)" />
          </template>
          <template #cell(priority)="{ item }">
            <PorterStatusBadge :value="item.priority" :label="priorityLabel(item.priority)" />
          </template>
          <template #cell(status)="{ item }">
            <PorterStatusBadge :value="item.status" :label="statusLabel(item.status)" />
          </template>
          <template #cell(logged_at)="{ item }">
            <div class="fw-semibold">{{ formatDateTime(item.logged_at) }}</div>
          </template>
          <template #cell(actions)="{ item }">
            <BButton size="sm" variant="outline-primary" @click="openDetail(item)">Ver detalle</BButton>
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

      <BModal v-model="showDetailModal" title="Detalle de bitácora" size="lg" hide-footer centered scrollable>
        <div v-if="selectedEntry" class="log-detail-modal">
          <div class="detail-header">
            <div>
              <h5 class="mb-1">{{ selectedEntry.title }}</h5>
              <p class="mb-0 text-muted">{{ formatDateTime(selectedEntry.logged_at) }}</p>
            </div>
            <div class="detail-badges">
              <PorterStatusBadge :value="selectedEntry.category" :label="categoryLabel(selectedEntry.category)" />
              <PorterStatusBadge :value="selectedEntry.priority" :label="priorityLabel(selectedEntry.priority)" />
              <PorterStatusBadge :value="selectedEntry.status" :label="statusLabel(selectedEntry.status)" />
            </div>
          </div>

          <div class="detail-grid">
            <div>
              <span>Turno</span>
              <strong>{{ selectedEntry.shift_label || "Sin turno" }}</strong>
            </div>
            <div>
              <span>Registrado por</span>
              <strong>{{ selectedEntry.registered_by?.name || "Usuario no informado" }}</strong>
            </div>
            <div>
              <span>Fecha operativa</span>
              <strong>{{ selectedEntry.logged_on || "-" }}</strong>
            </div>
          </div>

          <div class="detail-block">
            <span>Detalle</span>
            <p>{{ selectedEntry.detail }}</p>
          </div>

          <div class="detail-actions">
            <BButton variant="primary" @click="showDetailModal = false">Cerrar</BButton>
          </div>
        </div>
      </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.daily-log-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.daily-log-heading,
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

.daily-log-heading {
  flex-wrap: wrap;
}

.daily-log-heading h4,
.panel-title-row h5,
.detail-header h5 {
  color: #343a46;
  font-weight: 700;
}

.heading-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.daily-log-stats {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.daily-log-stat-card,
.daily-log-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.daily-log-stat-card {
  min-height: 116px;
  padding: 1.15rem 1.35rem;
}

.daily-log-stat-card span,
.daily-log-stat-card small {
  color: #747b91;
  display: block;
}

.daily-log-stat-card strong {
  color: #343a46;
  display: block;
  font-size: 2.4rem;
  line-height: 1;
  margin: 0.45rem 0 0.35rem;
}

.daily-log-panel :deep(.card-body) {
  padding: 1.35rem;
}

.log-form-panel .form-label,
.history-filters :deep(.form-control),
.history-filters :deep(.form-select) {
  font-weight: 600;
}

.form-actions {
  border-top: 1px solid rgba(217, 226, 246, 0.88);
  justify-content: flex-end;
  padding-top: 1rem;
}

.history-title {
  margin-bottom: 1rem;
}

.history-filters {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(220px, 2fr) minmax(170px, 1fr) minmax(170px, 1fr) minmax(160px, 1fr) minmax(145px, 0.75fr) minmax(145px, 0.75fr) auto auto;
  margin-bottom: 1rem;
}

:deep(.daily-log-table th) {
  border-bottom-color: #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.daily-log-table td) {
  border-color: #eef2fa;
  color: #343a46;
  vertical-align: middle;
}

:deep(.log-title-cell) {
  min-width: 260px;
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

.log-detail-modal {
  display: grid;
  gap: 1rem;
}

.detail-header {
  align-items: flex-start;
  border-bottom: 1px solid #e7ecf8;
  padding-bottom: 1rem;
}

.detail-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
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
.detail-block p {
  color: #343a46;
}

.detail-block p {
  margin: 0;
  white-space: pre-wrap;
}

.detail-actions {
  justify-content: flex-end;
}

:global(.swal-log-confirm) {
  display: grid;
  gap: 0.7rem;
  text-align: left;
}

:global(.swal-log-confirm span),
:global(.swal-log-confirm strong) {
  display: block;
}

:global(.swal-log-confirm span) {
  color: #747b91;
}

:global(.swal-log-validation) {
  margin: 0;
  padding-left: 1.25rem;
  text-align: left;
}

@media (max-width: 1399.98px) {
  .history-filters {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 991.98px) {
  .history-filters,
  .detail-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .daily-log-stats,
  .history-filters,
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .daily-log-heading,
  .panel-title-row,
  .form-actions,
  .detail-header {
    align-items: stretch;
    flex-direction: column;
  }

  .heading-actions,
  .heading-actions .btn,
  .form-actions .btn,
  .history-filters .btn {
    width: 100%;
  }

  .detail-badges {
    justify-content: flex-start;
  }
}
</style>
