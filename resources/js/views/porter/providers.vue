<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  service_type: "",
  company_name: "",
  contact_name: "",
  contact_rut: "",
  phone: "",
  vehicle_plate: "",
  responsible_staff_id: null,
  maintenance_dependency_id: null,
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
      catalogs: { staff: [], dependencies: [], external_service_statuses: [] },
      form: emptyForm(),
      items: [],
      filters: emptyFilters(),
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    statusOptions() {
      return [{ value: null, text: "Todos los estados" }].concat(
        (this.catalogs.external_service_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Seleccionar responsable..." }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
      );
    },
    dependencyOptions() {
      return [{ value: null, text: "Seleccionar dependencia..." }].concat(
        (this.catalogs.dependencies || []).map((item) => ({
          value: item.id,
          text: `${item.code || "S/C"} · ${item.name}`,
        }))
      );
    },
    activeEntries() {
      return this.items.filter((item) => item.status === "en_curso");
    },
    closedEntries() {
      return this.items.filter((item) => item.status !== "en_curso");
    },
    providerFields() {
      return [
        { key: "company_name", label: "Proveedor", thClass: "provider-th", tdClass: "provider-td provider-main-cell" },
        { key: "service_type", label: "Servicio / destino", thClass: "provider-th", tdClass: "provider-td" },
        { key: "status", label: "Estado", thClass: "provider-th", tdClass: "provider-td" },
        { key: "entered_at", label: "Horario", thClass: "provider-th", tdClass: "provider-td" },
        { key: "actions", label: "Acciones", thClass: "provider-th text-end", tdClass: "provider-td text-end" },
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

      const issues = this.validateProviderForm();
      if (issues.length) {
        await this.showValidationAlert(issues);
        return;
      }

      const confirmed = await this.confirmProviderSubmit();
      if (!confirmed) return;

      this.saving = true;
      this.error = null;
      try {
        await axios.post("/api/porter/external-services", this.form);
        this.form = emptyForm();
        await this.loadItems(1);
        await Swal.fire({
          icon: "success",
          title: "Ingreso registrado",
          text: "El proveedor quedó activo en portería.",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        await this.showRequestError(error, "No se pudo registrar el proveedor");
      } finally {
        this.saving = false;
      }
    },
    async loadItems(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/external-services", {
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
        title: "Registrar salida de proveedor",
        html: `
          <div class="swal-provider-summary">
            <strong>${this.escapeHtml(this.providerName(item))}</strong>
            <span>${this.escapeHtml(item.service_type || "Servicio no informado")}</span>
            <small>${this.escapeHtml(this.serviceTarget(item))}</small>
          </div>
          <select id="provider-status" class="swal2-select">
            <option value="finalizado">Finalizado</option>
            <option value="rechazado">Rechazado</option>
          </select>
          <textarea id="provider-observations" class="swal2-textarea" placeholder="Observaciones de salida"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Guardar salida",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: () => {
          const status = document.getElementById("provider-status").value;
          const observations = document.getElementById("provider-observations").value.trim();
          if (status === "rechazado" && !observations) {
            Swal.showValidationMessage("Indica una observación para rechazar o cerrar con reparos.");
            return false;
          }
          return { status, observations };
        },
      });

      if (!value) return;

      try {
        await axios.put(`/api/porter/external-services/${item.id}/exit`, value);
        await this.loadItems(this.pagination.current_page || 1);
        await Swal.fire({
          icon: "success",
          title: "Salida registrada",
          text: `${this.providerName(item)} ya no figura como servicio en curso.`,
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
    validateProviderForm() {
      const issues = [];
      if (!String(this.form.service_type || "").trim()) issues.push("Indica el tipo de servicio externo.");
      if (!String(this.form.contact_name || "").trim()) issues.push("Ingresa el nombre de la persona que ingresa.");
      return issues;
    },
    async confirmProviderSubmit() {
      const result = await Swal.fire({
        icon: "question",
        title: "Registrar ingreso de proveedor",
        html: `
          <div class="swal-provider-confirm">
            <div><span>Servicio</span><strong>${this.escapeHtml(this.form.service_type)}</strong></div>
            <div><span>Proveedor</span><strong>${this.escapeHtml(this.form.company_name || this.form.contact_name)}</strong></div>
            <div><span>Responsable / destino</span><strong>${this.escapeHtml(this.selectedTargetLabel())}</strong></div>
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
        html: `<ul class="swal-provider-validation">${issues.map((issue) => `<li>${this.escapeHtml(issue)}</li>`).join("")}</ul>`,
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
    selectedTargetLabel() {
      const staff = (this.catalogs.staff || []).find((item) => Number(item.id) === Number(this.form.responsible_staff_id));
      if (staff?.full_name) return staff.full_name;

      const dependency = (this.catalogs.dependencies || []).find((item) => Number(item.id) === Number(this.form.maintenance_dependency_id));
      if (dependency?.name) return dependency.code ? `${dependency.code} · ${dependency.name}` : dependency.name;

      return "Sin responsable asignado";
    },
    providerName(item) {
      return item.company_name || item.contact_name || "Proveedor sin nombre";
    },
    contactLine(item) {
      return [item.contact_name, item.contact_rut].filter(Boolean).join(" · ") || "Contacto no informado";
    },
    serviceTarget(item) {
      return item.dependency?.name || item.responsible_staff?.full_name || "Destino no informado";
    },
    statusLabel(value) {
      const option = (this.catalogs.external_service_statuses || []).find((item) => item.value === value);
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
    <div class="providers-page">
      <div class="providers-heading">
        <div>
          <h4 class="mb-0">Control de proveedores y servicios externos</h4>
          <p class="mb-0 text-muted">Ingreso, permanencia y salida de empresas, técnicos y servicios externos.</p>
        </div>
        <div class="heading-actions">
          <router-link class="btn btn-outline-primary" to="/porter/dashboard">Panel portería</router-link>
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(pagination.current_page || 1)">
            {{ loadingList ? "Actualizando..." : "Actualizar" }}
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="provider-stats">
        <div class="provider-stat-card">
          <span>En curso</span>
          <strong>{{ activeEntries.length }}</strong>
          <small>Servicios presentes o pendientes de salida</small>
        </div>
        <div class="provider-stat-card">
          <span>Cerrados</span>
          <strong>{{ closedEntries.length }}</strong>
          <small>Registros cargados con los filtros actuales</small>
        </div>
        <div class="provider-stat-card">
          <span>Total filtrado</span>
          <strong>{{ pagination.total }}</strong>
          <small>Coincidencias del historial</small>
        </div>
      </div>

      <div class="row g-3 align-items-start">
        <div class="col-xxl-8">
          <BCard class="provider-panel provider-form-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Nuevo ingreso</h5>
                <p class="mb-0 text-muted">Registra el proveedor y controla su salida desde portería.</p>
              </div>
              <PorterStatusBadge value="en_curso" label="Ingreso" />
            </div>

            <div class="row g-3 mt-1">
              <div class="col-lg-4">
                <label class="form-label">Servicio externo</label>
                <BFormInput v-model="form.service_type" placeholder="Internet, gasfitería, fumigación..." autocomplete="off" />
              </div>
              <div class="col-lg-4">
                <label class="form-label">Empresa</label>
                <BFormInput v-model="form.company_name" placeholder="Nombre de empresa" autocomplete="off" />
              </div>
              <div class="col-lg-4">
                <label class="form-label">Responsable interno</label>
                <BFormSelect v-model="form.responsible_staff_id" :options="staffOptions" />
              </div>
              <div class="col-lg-4">
                <label class="form-label">Persona que ingresa</label>
                <BFormInput v-model="form.contact_name" placeholder="Nombre y apellido" autocomplete="off" />
              </div>
              <div class="col-lg-3">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.contact_rut" placeholder="11.111.111-1" autocomplete="off" />
              </div>
              <div class="col-lg-3">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.phone" placeholder="+56 9..." autocomplete="off" />
              </div>
              <div class="col-lg-2">
                <label class="form-label">Patente</label>
                <BFormInput v-model="form.vehicle_plate" placeholder="ABCD12" autocomplete="off" />
              </div>
              <div class="col-lg-12">
                <label class="form-label">Dependencia / lugar de trabajo</label>
                <BFormSelect v-model="form.maintenance_dependency_id" :options="dependencyOptions" />
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <BFormTextarea v-model="form.observations" rows="3" placeholder="Credencial, herramientas, patente, acompañantes u observación relevante" />
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
          <BCard class="provider-panel active-panel">
            <div class="panel-title-row">
              <div>
                <h5 class="mb-1">Servicios en curso</h5>
                <p class="mb-0 text-muted">Salida rápida de proveedores presentes.</p>
              </div>
              <BBadge variant="success">{{ activeEntries.length }}</BBadge>
            </div>

            <div v-if="!activeEntries.length" class="empty-active">
              No hay proveedores activos con los filtros actuales.
            </div>

            <div v-else class="active-list">
              <button v-for="item in activeEntries" :key="item.id" type="button" class="active-provider-row" @click="markExit(item)">
                <span>
                  <strong>{{ providerName(item) }}</strong>
                  <small>{{ item.service_type }}</small>
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

      <BCard class="provider-panel provider-history-panel">
        <div class="panel-title-row history-title">
          <div>
            <h5 class="mb-1">Historial de proveedores</h5>
            <p class="mb-0 text-muted">Filtra por empresa, contacto, servicio, patente, estado o fecha de ingreso.</p>
          </div>
        </div>

        <div class="history-filters">
          <BFormInput v-model="filters.search" placeholder="Empresa, servicio, contacto o patente" @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BFormInput v-model="filters.date_from" type="date" />
          <BFormInput v-model="filters.date_to" type="date" />
          <BButton variant="primary" :disabled="loadingList" @click="loadItems(1)">Filtrar</BButton>
          <BButton variant="outline-secondary" :disabled="loadingList" @click="resetFilters">Limpiar</BButton>
        </div>

        <BTable
          :items="items"
          :busy="loadingList"
          :fields="providerFields"
          responsive
          hover
          show-empty
          table-class="provider-table"
        >
          <template #table-busy><LoadingState message="Cargando proveedores..." compact /></template>
          <template #empty>
            <div class="empty-table">No hay proveedores para los filtros seleccionados.</div>
          </template>
          <template #cell(company_name)="{ item }">
            <div class="fw-semibold">{{ providerName(item) }}</div>
            <div class="small text-muted">{{ contactLine(item) }}</div>
            <div v-if="item.phone || item.vehicle_plate" class="small text-muted">
              {{ [item.phone, item.vehicle_plate ? `Patente ${item.vehicle_plate}` : null].filter(Boolean).join(" · ") }}
            </div>
          </template>
          <template #cell(service_type)="{ item }">
            <div class="fw-semibold">{{ item.service_type }}</div>
            <div class="small text-muted">{{ serviceTarget(item) }}</div>
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
            <span v-else class="text-muted small">Cerrado</span>
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
.providers-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.providers-heading,
.panel-title-row,
.form-actions,
.pagination-row {
  align-items: center;
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

.providers-heading {
  flex-wrap: wrap;
}

.providers-heading h4,
.panel-title-row h5 {
  color: #343a46;
  font-weight: 700;
}

.heading-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.provider-stats {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.provider-stat-card,
.provider-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(217, 226, 246, 0.92);
  border-radius: 0.5rem;
  box-shadow: 0 18px 42px rgba(90, 110, 150, 0.08);
}

.provider-stat-card {
  min-height: 116px;
  padding: 1.15rem 1.35rem;
}

.provider-stat-card span,
.provider-stat-card small {
  color: #747b91;
  display: block;
}

.provider-stat-card strong {
  color: #343a46;
  display: block;
  font-size: 2.4rem;
  line-height: 1;
  margin: 0.45rem 0 0.35rem;
}

.provider-panel :deep(.card-body) {
  padding: 1.35rem;
}

.provider-form-panel .form-label,
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

.active-provider-row {
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

.active-provider-row:hover {
  border-color: #6b7fe3;
  box-shadow: 0 10px 24px rgba(90, 110, 150, 0.1);
}

.active-provider-row span {
  min-width: 0;
}

.active-provider-row strong,
.active-provider-row small,
.active-provider-row b {
  display: block;
}

.active-provider-row strong,
.active-provider-row small {
  overflow-wrap: anywhere;
}

.active-provider-row small {
  color: #747b91;
}

.active-provider-row b {
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

.provider-history-panel {
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

:deep(.provider-table th) {
  border-bottom-color: #e7ecf8;
  color: #747b91;
  font-size: 0.75rem;
  letter-spacing: 0;
  text-transform: uppercase;
}

:deep(.provider-table td) {
  border-color: #eef2fa;
  color: #343a46;
  vertical-align: middle;
}

:deep(.provider-main-cell) {
  min-width: 240px;
}

.pagination-row {
  justify-content: flex-end;
  margin-top: 1rem;
}

:global(.swal-provider-summary),
:global(.swal-provider-confirm) {
  text-align: left;
}

:global(.swal-provider-summary) {
  background: #f5f7fb;
  border: 1px solid #e4e9f6;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  padding: 0.85rem;
}

:global(.swal-provider-summary strong),
:global(.swal-provider-summary span),
:global(.swal-provider-summary small),
:global(.swal-provider-confirm span),
:global(.swal-provider-confirm strong) {
  display: block;
}

:global(.swal-provider-summary span),
:global(.swal-provider-summary small),
:global(.swal-provider-confirm span) {
  color: #747b91;
}

:global(.swal-provider-confirm) {
  display: grid;
  gap: 0.7rem;
}

:global(.swal-provider-validation) {
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
  .provider-stats,
  .history-filters {
    grid-template-columns: 1fr;
  }

  .providers-heading,
  .panel-title-row,
  .form-actions,
  .active-provider-row {
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
