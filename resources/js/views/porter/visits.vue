<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  visitor_name: "",
  visitor_rut: "",
  purpose: "",
  visited_staff_id: null,
  visited_department_id: null,
  visited_person_label: "",
  contact_phone: "",
  observations: "",
});

const emptyFilters = () => ({
  search: "",
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
      error: null,
      catalogs: { staff: [], departments: [], visit_statuses: [] },
      form: emptyForm(),
      items: [],
      filters: emptyFilters(),
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    statusOptions() {
      return [{ value: null, text: "Todos los estados" }].concat(
        (this.catalogs.visit_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Seleccionar funcionario..." }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
      );
    },
    departmentOptions() {
      return [{ value: null, text: "Seleccionar dependencia..." }].concat(
        (this.catalogs.departments || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    activeVisits() {
      return this.items.filter((item) => item.status === "en_curso");
    },
    closedVisits() {
      return this.items.filter((item) => item.status !== "en_curso");
    },
    visitFields() {
      return [
        { key: "visitor_name", label: "Visita", thClass: "visit-th", tdClass: "visit-td visit-person-cell" },
        { key: "purpose", label: "Destino / motivo", thClass: "visit-th", tdClass: "visit-td" },
        { key: "status", label: "Estado", thClass: "visit-th", tdClass: "visit-td" },
        { key: "entered_at", label: "Horario", thClass: "visit-th", tdClass: "visit-td" },
        { key: "actions", label: "Acciones", thClass: "visit-th text-end", tdClass: "visit-td text-end" },
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

      const issues = this.validateVisitForm();
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      const confirmed = await this.confirmVisitSubmit();
      if (!confirmed) return;

      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/porter/visits", this.form);
        this.form = emptyForm();
        await this.loadItems(1);
        await Swal.fire({
          icon: "success",
          title: "Ingreso registrado",
          text: "La visita quedó activa en portería.",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la visita");
      } finally {
        this.saving = false;
      }
    },
    async loadItems(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/visits", {
          params: {
            page,
            search: this.filters.search || null,
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
    async markExit(item) {
      const { value } = await Swal.fire({
        title: "Registrar salida",
        html: `
          <div class="swal-visit-summary">
            <strong>${this.escapeHtml(item.visitor_name)}</strong>
            <span>${this.escapeHtml(this.visitedTarget(item))}</span>
          </div>
          <select id="visit-status" class="swal2-select">
            <option value="finalizada">Finalizada</option>
            <option value="rechazada">Rechazada</option>
          </select>
          <textarea id="visit-observations" class="swal2-textarea" placeholder="Observaciones de salida"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Guardar salida",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: () => {
          const status = document.getElementById("visit-status").value;
          const observations = document.getElementById("visit-observations").value.trim();
          if (status === "rechazada" && !observations) {
            Swal.showValidationMessage("Indica una observación para una visita rechazada.");
            return false;
          }
          return { status, observations };
        },
      });

      if (!value) return;

      try {
        await axios.put(`/api/porter/visits/${item.id}/exit`, value);
        await this.loadItems(this.pagination.current_page || 1);
        await Swal.fire({
          icon: "success",
          title: "Salida registrada",
          text: `${item.visitor_name} ya no figura como visita en curso.`,
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar la salida");
      }
    },
    resetFilters() {
      this.filters = emptyFilters();
      this.loadItems(1);
    },
    clearForm() {
      this.form = emptyForm();
    },
    validateVisitForm() {
      const issues = [];
      if (!String(this.form.visitor_name || "").trim()) issues.push("Ingresa el nombre de la visita.");
      if (!String(this.form.purpose || "").trim()) issues.push("Indica el motivo de la visita.");
      if (!this.form.visited_staff_id && !this.form.visited_department_id && !String(this.form.visited_person_label || "").trim()) {
        issues.push("Indica a quién visita o la dependencia de destino.");
      }
      return issues;
    },
    async confirmVisitSubmit() {
      const result = await Swal.fire({
        icon: "question",
        title: "Registrar ingreso de visita",
        html: `
          <div class="swal-visit-confirm">
            <div><span>Visita</span><strong>${this.escapeHtml(this.form.visitor_name)}</strong></div>
            <div><span>Destino</span><strong>${this.escapeHtml(this.selectedDestinationLabel())}</strong></div>
            <div><span>Motivo</span><strong>${this.escapeHtml(this.form.purpose)}</strong></div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar ingreso",
        cancelButtonText: "Revisar",
        reverseButtons: true,
      });
      return result.isConfirmed;
    },
    async showValidationAlert(issues) {
      await Swal.fire({
        icon: "warning",
        title: "Faltan datos para registrar",
        html: `<ul class="swal-validation-list">${issues.map((issue) => `<li>${this.escapeHtml(issue)}</li>`).join("")}</ul>`,
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
    selectedDestinationLabel() {
      const staff = (this.catalogs.staff || []).find((item) => Number(item.id) === Number(this.form.visited_staff_id));
      if (staff?.full_name) return staff.full_name;

      const department = (this.catalogs.departments || []).find((item) => Number(item.id) === Number(this.form.visited_department_id));
      if (department?.name) return department.name;

      return this.form.visited_person_label || "Sin destino";
    },
    visitedTarget(item) {
      return item.visited_staff?.full_name || item.visited_department?.name || item.visited_person_label || "Destino no informado";
    },
    statusLabel(value) {
      const option = (this.catalogs.visit_statuses || []).find((item) => item.value === value);
      if (option?.label) return option.label;
      return String(value || "-").replace(/_/g, " ");
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
    <div class="visits-page">
      <div class="visits-heading">
        <div>
          <h4 class="mb-0">Control de visitas</h4>
          <p class="mb-0 text-muted">Ingreso, salida y trazabilidad operativa de visitas en portería.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(pagination.current_page || 1)">
            {{ loadingList ? "Actualizando..." : "Actualizar" }}
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="visit-stats">
        <div class="visit-stat-card">
          <span>En curso</span>
          <strong>{{ activeVisits.length }}</strong>
          <small>Visitas dentro del establecimiento</small>
        </div>
        <div class="visit-stat-card">
          <span>Finalizadas / cerradas</span>
          <strong>{{ closedVisits.length }}</strong>
          <small>Registros cargados en esta vista</small>
        </div>
        <div class="visit-stat-card">
          <span>Total filtrado</span>
          <strong>{{ pagination.total }}</strong>
          <small>Coincidencias del historial</small>
        </div>
      </div>

      <div class="row g-3 align-items-start">
        <div class="col-xxl-8">
          <BCard class="visit-panel visit-form-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Nuevo ingreso</h5>
                <p class="mb-0 text-muted">Registra la visita y deja el movimiento activo hasta su salida.</p>
              </div>
              <PorterStatusBadge value="en_curso" label="Ingreso" />
            </div>

            <div class="row g-3 mt-1">
              <div class="col-lg-5">
                <label class="form-label">Nombre de la visita</label>
                <BFormInput v-model="form.visitor_name" placeholder="Nombre y apellido" autocomplete="off" />
              </div>
              <div class="col-lg-3">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.visitor_rut" placeholder="11.111.111-1" autocomplete="off" />
              </div>
              <div class="col-lg-4">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.contact_phone" placeholder="+56 9..." autocomplete="off" />
              </div>
              <div class="col-lg-6">
                <label class="form-label">Motivo</label>
                <BFormInput v-model="form.purpose" placeholder="Reunión, entrega, entrevista..." autocomplete="off" />
              </div>
              <div class="col-lg-6">
                <label class="form-label">Persona visitada</label>
                <BFormSelect v-model="form.visited_staff_id" :options="staffOptions" />
              </div>
              <div class="col-lg-6">
                <label class="form-label">Dependencia / departamento</label>
                <BFormSelect v-model="form.visited_department_id" :options="departmentOptions" />
              </div>
              <div class="col-lg-6">
                <label class="form-label">Referencia adicional</label>
                <BFormInput v-model="form.visited_person_label" placeholder="Ej. Dirección, sala de espera, inspectoría" />
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <BFormTextarea v-model="form.observations" rows="3" placeholder="Credencial, patente, autorización u observación relevante" />
              </div>
              <div class="col-12 form-actions">
                <BButton variant="outline-secondary" :disabled="saving" @click="clearForm">Limpiar</BButton>
                <BButton variant="primary" :disabled="saving" @click="submit">
                  {{ saving ? "Guardando..." : "Registrar ingreso" }}
                </BButton>
              </div>
            </div>
          </BCard>
        </div>

        <div class="col-xxl-4">
          <BCard class="visit-panel active-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Visitas en curso</h5>
                <p class="mb-0 text-muted">Salida rápida de visitas presentes.</p>
              </div>
              <BBadge variant="success">{{ activeVisits.length }}</BBadge>
            </div>

            <div v-if="!activeVisits.length" class="empty-active">
              No hay visitas activas con los filtros actuales.
            </div>

            <div v-else class="active-list">
              <button v-for="item in activeVisits" :key="item.id" type="button" class="active-visit-row" @click="markExit(item)">
                <span>
                  <strong>{{ item.visitor_name }}</strong>
                  <small>{{ visitedTarget(item) }}</small>
                </span>
                <span>
                  <small>{{ formatDateTime(item.entered_at) }}</small>
                  <b>Registrar salida</b>
                </span>
              </button>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="visit-panel visit-history-panel">
        <div class="panel-title-row history-title">
          <div>
            <h5 class="mb-1">Historial de visitas</h5>
            <p class="mb-0 text-muted">Busca registros por nombre, RUT, motivo, destino o rango de fechas.</p>
          </div>
        </div>

        <div class="history-filters">
          <BFormInput v-model="filters.search" placeholder="Nombre, RUT, motivo o destino" @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BFormInput v-model="filters.date_from" type="date" />
          <BFormInput v-model="filters.date_to" type="date" />
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(1)">Filtrar</BButton>
          <BButton variant="outline-secondary" :disabled="loadingList" @click="resetFilters">Limpiar</BButton>
        </div>

        <BTable
          :items="items"
          :busy="loadingList"
          :fields="visitFields"
          responsive
          hover
          show-empty
          table-class="visit-table"
        >
          <template #table-busy><LoadingState message="Cargando visitas..." compact /></template>
          <template #empty>
            <div class="empty-table">No hay visitas para los filtros seleccionados.</div>
          </template>
          <template #cell(visitor_name)="{ item }">
            <div class="fw-semibold">{{ item.visitor_name }}</div>
            <div class="small text-muted">{{ item.visitor_rut || "RUT no informado" }}</div>
            <div v-if="item.contact_phone" class="small text-muted">{{ item.contact_phone }}</div>
          </template>
          <template #cell(purpose)="{ item }">
            <div class="fw-semibold">{{ item.purpose }}</div>
            <div class="small text-muted">{{ visitedTarget(item) }}</div>
          </template>
          <template #cell(status)="{ item }">
            <PorterStatusBadge :value="item.status" :label="statusLabel(item.status)" />
          </template>
          <template #cell(entered_at)="{ item }">
            <div class="fw-semibold">Ingreso: {{ formatDateTime(item.entered_at) }}</div>
            <div class="small text-muted">Salida: {{ formatDateTime(item.exited_at) }}</div>
          </template>
          <template #cell(actions)="{ item }">
            <BButton v-if="item.status === 'en_curso'" size="sm" variant="outline-primary" @click="markExit(item)">
              Registrar salida
            </BButton>
            <span v-else class="text-muted small">Cerrada</span>
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
    </div>
  </Layout>
</template>

<style scoped>
.visits-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.visits-heading,
.panel-title-row,
.form-actions,
.pagination-row {
  align-items: center;
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

.visits-heading {
  flex-wrap: wrap;
}

.visits-heading h4,
.panel-title-row h5 {
  color: #343a46;
  font-weight: 700;
}

.heading-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.visit-stats {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.visit-stat-card,
.visit-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.visit-stat-card {
  min-height: 116px;
  padding: 1.15rem 1.35rem;
}

.visit-stat-card span,
.visit-stat-card small {
  color: #747b91;
  display: block;
}

.visit-stat-card strong {
  color: #343a46;
  display: block;
  font-size: 2.4rem;
  line-height: 1;
  margin: 0.45rem 0 0.35rem;
}

.visit-panel :deep(.card-body) {
  padding: 1.35rem;
}

.visit-form-panel .form-label,
.history-filters :deep(.form-control),
.history-filters :deep(.form-select) {
  font-weight: 600;
}

.form-actions {
  border-top: 1px solid rgba(217, 226, 246, 0.88);
  justify-content: flex-end;
  padding-top: 1rem;
}

.active-panel {
  position: sticky;
  top: 1rem;
}

.active-list {
  display: grid;
  gap: 0.75rem;
  margin-top: 1.1rem;
}

.active-visit-row {
  align-items: center;
  background: #fff;
  border: 1px solid #e4e9f6;
  border-radius: 0.5rem;
  color: #343a46;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  padding: 0.95rem 1rem;
  text-align: left;
  width: 100%;
}

.active-visit-row:hover {
  border-color: #6b7fe3;
  box-shadow: 0 10px 24px rgba(90, 110, 150, 0.1);
}

.active-visit-row span {
  min-width: 0;
}

.active-visit-row strong,
.active-visit-row small,
.active-visit-row b {
  display: block;
}

.active-visit-row strong,
.active-visit-row small {
  overflow-wrap: anywhere;
}

.active-visit-row small {
  color: #747b91;
}

.active-visit-row b {
  color: #556ee6;
  font-size: 0.82rem;
  white-space: nowrap;
}

.empty-active,
.empty-table {
  color: #747b91;
  padding: 1rem 0;
  text-align: center;
}

.visit-history-panel {
  margin-top: 0.25rem;
}

.history-title {
  margin-bottom: 1rem;
}

.history-filters {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: minmax(220px, 2fr) minmax(170px, 1fr) minmax(145px, 0.75fr) minmax(145px, 0.75fr) auto auto;
  margin-bottom: 1rem;
}

:deep(.visit-table th) {
  border-bottom-color: #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.visit-table td) {
  border-color: #eef2fa;
  color: #343a46;
  vertical-align: middle;
}

:deep(.visit-person-cell) {
  min-width: 220px;
}

.pagination-row {
  justify-content: flex-end;
  margin-top: 1rem;
}

:global(.swal-visit-summary),
:global(.swal-visit-confirm) {
  text-align: left;
}

:global(.swal-visit-summary) {
  background: #f5f7fb;
  border: 1px solid #e4e9f6;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  padding: 0.85rem;
}

:global(.swal-visit-summary strong),
:global(.swal-visit-summary span),
:global(.swal-visit-confirm span),
:global(.swal-visit-confirm strong) {
  display: block;
}

:global(.swal-visit-summary span),
:global(.swal-visit-confirm span) {
  color: #747b91;
}

:global(.swal-visit-confirm) {
  display: grid;
  gap: 0.7rem;
}

:global(.swal-validation-list) {
  margin: 0;
  padding-left: 1.25rem;
  text-align: left;
}

@media (max-width: 1199.98px) {
  .active-panel {
    position: static;
  }

  .history-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .visit-stats,
  .history-filters {
    grid-template-columns: 1fr;
  }

  .visits-heading,
  .panel-title-row,
  .form-actions,
  .active-visit-row {
    align-items: stretch;
    flex-direction: column;
  }

  .heading-actions,
  .heading-actions .btn,
  .form-actions .btn,
  .history-filters .btn {
    width: 100%;
  }
}
</style>
