<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import ReservationFormModal from "../../components/spaces/reservation-form-modal.vue";
import ReservationPreviewModal from "../../components/spaces/reservation-preview-modal.vue";

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
    statusVariant(status) {
      if (status === "aprobada") return "success";
      if (status === "pendiente") return "warning";
      if (status === "rechazada") return "danger";
      if (status === "cancelada") return "secondary";
      return "info";
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
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">Reservas de dependencias</h4>
        <div class="text-muted">Listado, filtros y gestión operativa de agendamientos.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/spaces/calendar" class="btn btn-outline-secondary">Calendario</router-link>
        <BButton v-if="canCreate" variant="primary" @click="openCreate">Nueva reserva</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Título, actividad..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Dependencia</label>
          <Multiselect v-model="filters.dependency_id" :options="dependencyOptions" :searchable="true" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Funcionario</label>
          <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="false" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.date_from" type="date" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.date_to" type="date" />
        </div>
        <div class="col-md-2">
          <BButton variant="secondary" @click="load">Filtrar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard>
      <BTable
        :items="reservations"
        :busy="loading"
        :fields="[
          { key: 'title', label: 'Reserva' },
          { key: 'dependency', label: 'Dependencia' },
          { key: 'staff', label: 'Funcionario' },
          { key: 'starts_at', label: 'Inicio' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
        responsive
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando reservas..." compact />
        </template>
        <template #cell(title)="{ item }">
          <div class="fw-semibold">{{ item.title }}</div>
          <div class="text-muted small">{{ item.activity || "-" }}</div>
        </template>
        <template #cell(dependency)="{ item }">
          {{ item.dependency?.name || "-" }}
        </template>
        <template #cell(staff)="{ item }">
          {{ item.staff?.full_name || "-" }}
        </template>
        <template #cell(starts_at)="{ item }">
          {{ item.start_date }} {{ item.start_time }}
        </template>
        <template #cell(status)="{ item }">
          <BBadge :variant="statusVariant(item.status)">{{ item.status }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="outline-primary" @click="openPreview(item)">Ver</BButton>
            <BButton v-if="canEdit && item.status !== 'cancelada'" size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

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
