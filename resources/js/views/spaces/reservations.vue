<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import ReservationFormModal from "../../components/spaces/reservation-form-modal.vue";
import ReservationPreviewModal from "../../components/spaces/reservation-preview-modal.vue";
import "./shared.css";

export default {
  components: { Layout, LoadingState, Multiselect, ReservationFormModal, ReservationPreviewModal },
  data() {
    return {
      loading: false,
      error: null,
      reservations: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      catalogs: {
        dependencies: [],
        dependency_types: [],
        staff: [],
        departments: [],
        statuses: [],
        repetition_types: [],
      },
      filters: {
        search: "",
        dependency_id: null,
        dependency_type_id: null,
        staff_id: null,
        department_id: null,
        status: null,
        date_from: "",
        date_to: "",
      },
      showFormModal: false,
      showPreviewModal: false,
      selectedReservation: null,
      editingReservation: null,
      draftSelection: {},
    };
  },
  computed: {
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canCreate() {
      return this.permissions.includes("crear_reservas");
    },
    canEdit() {
      return this.permissions.includes("editar_reservas") || this.permissions.includes("administrar_calendario");
    },
    dependencyOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.dependencies || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    typeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.dependency_types || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    staffOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.staff || []).map((item) => ({
          value: item.id,
          label: item.full_name,
        }))
      );
    },
    departmentOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.departments || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((item) => ({
          value: item.value,
          label: item.label,
        }))
      );
    },
    summaryCards() {
      const pending = this.reservations.filter((item) => item.status === "pendiente").length;
      const approved = this.reservations.filter((item) => item.status === "aprobada").length;

      return [
        {
          label: "Reservas",
          value: this.formatInteger(this.pagination.total || this.reservations.length),
          detail: "según filtros aplicados",
          icon: "bx-calendar",
          tone: "blue",
        },
        {
          label: "Pendientes",
          value: this.formatInteger(pending),
          detail: "en página actual",
          icon: "bx-time-five",
          tone: "amber",
        },
        {
          label: "Aprobadas",
          value: this.formatInteger(approved),
          detail: "en página actual",
          icon: "bx-check-circle",
          tone: "green",
        },
      ];
    },
    hasActiveFilters() {
      return Boolean(
        this.filters.search ||
        this.filters.dependency_id ||
        this.filters.dependency_type_id ||
        this.filters.staff_id ||
        this.filters.department_id ||
        this.filters.status ||
        this.filters.date_from ||
        this.filters.date_to
      );
    },
    paginationRange() {
      if (!this.pagination.total) {
        return "Sin resultados";
      }

      const perPage = Number(this.pagination.per_page || 15);
      const from = (Number(this.pagination.current_page || 1) - 1) * perPage + 1;
      const to = Math.min(from + perPage - 1, Number(this.pagination.total || 0));

      return `${this.formatInteger(from)}-${this.formatInteger(to)} de ${this.formatInteger(this.pagination.total)}`;
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/spaces/reservations/catalogs");
      this.catalogs = response.data;
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/spaces/reservations", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.reservations = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      if (!this.canCreate) {
        return;
      }
      this.editingReservation = null;
      this.draftSelection = {};
      this.showFormModal = true;
    },
    resetFilters() {
      this.filters = {
        search: "",
        dependency_id: null,
        dependency_type_id: null,
        staff_id: null,
        department_id: null,
        status: null,
        date_from: "",
        date_to: "",
      };
      this.load(1);
    },
    async openEdit(item) {
      if (!this.canEdit) {
        return;
      }
      const response = await axios.get(`/api/spaces/reservations/${item.id}`);
      this.editingReservation = response.data.data;
      this.showPreviewModal = false;
      this.showFormModal = true;
    },
    async openPreview(item) {
      const response = await axios.get(`/api/spaces/reservations/${item.id}`);
      this.selectedReservation = response.data.data;
      this.showPreviewModal = true;
    },
    handleSaved() {
      this.editingReservation = null;
      this.selectedReservation = null;
      this.draftSelection = {};
      this.load(this.pagination.current_page);
    },
    openReschedule(item) {
      if (!this.canCreate) {
        return;
      }

      this.editingReservation = null;
      this.selectedReservation = null;
      this.showPreviewModal = false;
      this.draftSelection = {
        maintenance_dependency_id: item.maintenance_dependency_id,
        staff_id: item.staff_id,
        department_id: item.department_id,
        title: item.title,
        activity: item.activity,
        start_date: item.start_date,
        start_time: item.start_time,
        end_date: item.end_date,
        end_time: item.end_time,
        repetition_type: "none",
        repetition_until: "",
        observations: item.observations,
        estimated_attendees: item.estimated_attendees,
        special_requirements: item.special_requirements,
        collaborator_staff_ids: (item.collaborators || []).filter((entry) => entry.staff_id).map((entry) => entry.staff_id),
        collaborator_external_emails: (item.collaborators || []).filter((entry) => entry.external_email).map((entry) => entry.external_email),
      };
      this.showFormModal = true;
    },
    statusClass(status) {
      return `spaces-status-pill--${status || "secondary"}`;
    },
    formatInteger(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        maximumFractionDigits: 0,
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="spaces-shell">
      <section class="spaces-hero">
        <div class="spaces-hero__body">
          <div class="spaces-eyebrow">Dependencias y reservas</div>
          <h4>Reservas de dependencias</h4>
          <p>Listado, filtros y gestión operativa de agendamientos por espacio.</p>
        </div>
        <div class="spaces-actions">
          <router-link to="/spaces/calendar" class="btn btn-outline-secondary">
            <i class="bx bx-calendar-event"></i>
            <span>Calendario</span>
          </router-link>
          <BButton v-if="canCreate" variant="primary" @click="openCreate">
            <i class="bx bx-plus"></i>
            <span>Nueva reserva</span>
          </BButton>
        </div>
      </section>

      <div class="spaces-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="spaces-summary-card"
          :class="`spaces-summary-card--${card.tone}`"
        >
          <div class="spaces-summary-icon">
            <i :class="`bx ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Filtros</div>
            <h5 class="spaces-panel-title">Segmentar reservas</h5>
          </div>
        </div>

        <div class="spaces-filter-grid spaces-filter-grid--wide">
          <label class="spaces-field">
            <span>Buscar</span>
            <BFormInput v-model="filters.search" placeholder="Título, actividad..." @keyup.enter="load(1)" />
          </label>
          <label class="spaces-field">
            <span>Dependencia</span>
            <Multiselect v-model="filters.dependency_id" :options="dependencyOptions" :searchable="true" />
          </label>
          <label class="spaces-field">
            <span>Tipo</span>
            <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
          </label>
          <label class="spaces-field">
            <span>Funcionario</span>
            <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
          </label>
          <label class="spaces-field">
            <span>Estado</span>
            <Multiselect v-model="filters.status" :options="statusOptions" :searchable="false" />
          </label>
          <label class="spaces-field">
            <span>Desde</span>
            <BFormInput v-model="filters.date_from" type="date" />
          </label>
          <label class="spaces-field">
            <span>Hasta</span>
            <BFormInput v-model="filters.date_to" type="date" />
          </label>
          <div class="spaces-filter-actions">
            <BButton variant="primary" :disabled="loading" @click="load(1)">
              <i class="bx bx-filter-alt"></i>
              <span>Aplicar</span>
            </BButton>
            <BButton variant="outline-secondary" :disabled="loading || !hasActiveFilters" @click="resetFilters">
              <i class="bx bx-x"></i>
              <span>Limpiar</span>
            </BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-0">{{ error }}</BAlert>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Listado</div>
            <h5 class="spaces-panel-title">Reservas registradas</h5>
          </div>
          <div class="spaces-panel-meta">{{ paginationRange }}</div>
        </div>

        <div v-if="loading && reservations.length === 0" class="spaces-empty-state">
          <LoadingState message="Cargando reservas..." compact />
        </div>
        <div v-else class="spaces-table-wrap">
          <table class="table spaces-data-table spaces-data-table--wide">
            <thead>
              <tr>
                <th style="width: 24%">Reserva</th>
                <th style="width: 18%">Dependencia</th>
                <th style="width: 18%">Funcionario</th>
                <th style="width: 16%">Inicio</th>
                <th style="width: 12%" class="text-center">Estado</th>
                <th style="width: 12%" class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in reservations" :key="item.id">
                <td>
                  <div class="spaces-table-title">{{ item.title }}</div>
                  <span class="spaces-table-subtitle">{{ item.activity || "Sin actividad" }}</span>
                </td>
                <td>
                  <div class="spaces-table-title">{{ item.dependency?.name || "-" }}</div>
                  <span class="spaces-table-subtitle">{{ item.dependency?.type?.name || "Sin tipo" }}</span>
                </td>
                <td>{{ item.staff?.full_name || "-" }}</td>
                <td>
                  <div>{{ item.start_date }} {{ item.start_time }}</div>
                  <span class="spaces-table-subtitle">{{ item.end_date }} {{ item.end_time }}</span>
                </td>
                <td class="text-center">
                  <span class="spaces-status-pill" :class="statusClass(item.status)">{{ item.status }}</span>
                </td>
                <td>
                  <div class="spaces-row-actions">
                    <BButton
                      size="sm"
                      variant="outline-primary"
                      title="Ver"
                      aria-label="Ver reserva"
                      @click="openPreview(item)"
                    >
                      <i class="bx bx-show"></i>
                      <span>Ver</span>
                    </BButton>
                    <BButton
                      v-if="canEdit && item.status !== 'cancelada'"
                      size="sm"
                      variant="outline-info"
                      title="Editar"
                      aria-label="Editar reserva"
                      @click="openEdit(item)"
                    >
                      <i class="bx bx-edit"></i>
                      <span>Editar</span>
                    </BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="reservations.length === 0">
                <td colspan="6">
                  <div class="spaces-empty-state">
                    <i class="bx bx-calendar-x"></i>
                    <strong>No hay reservas para mostrar</strong>
                    <span>Ajusta los filtros o crea una nueva reserva.</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.total > (pagination.per_page || 15)" class="d-flex justify-content-end mt-3">
          <BPagination
            v-model="pagination.current_page"
            :per-page="pagination.per_page || 15"
            :total-rows="pagination.total"
            @update:model-value="load"
          />
        </div>
      </section>
    </div>

    <ReservationFormModal
      v-model="showFormModal"
      :catalogs="catalogs"
      :reservation="editingReservation"
      :draft-selection="draftSelection"
      @saved="handleSaved"
    />
    <ReservationPreviewModal
      v-model="showPreviewModal"
      :reservation="selectedReservation"
      @saved="handleSaved"
      @reschedule="openReschedule"
      @edit="openEdit"
    />
  </Layout>
</template>
