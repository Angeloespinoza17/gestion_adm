<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import ReservationFormModal from "../../components/spaces/reservation-form-modal.vue";
import ReservationPreviewModal from "../../components/spaces/reservation-preview-modal.vue";

export default {
  components: {
    Layout,
    FullCalendar,
    ReservationFormModal,
    ReservationPreviewModal,
  },
  data() {
    return {
      loading: false,
      dependency: null,
      catalogs: {
        dependencies: [],
        dependency_types: [],
        staff: [],
        departments: [],
        statuses: [],
        repetition_types: [],
      },
      showFormModal: false,
      showPreviewModal: false,
      editingReservation: null,
      selectedReservation: null,
      draftSelection: {},
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        themeSystem: "bootstrap5",
        initialView: "timeGridWeek",
        selectable: true,
        editable: false,
        firstDay: 1,
        buttonText: {
          today: "Hoy",
          month: "Mes",
          week: "Semana",
          day: "Día",
        },
        allDayText: "Todo el día",
        noEventsText: "No hay reservas para mostrar",
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay",
        },
        events: [],
      },
    };
  },
  computed: {
    itemId() {
      return this.$route.params.id;
    },
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canCreateReservation() {
      return this.permissions.includes("crear_reservas");
    },
    canReserveDependency() {
      return this.canCreateReservation && Boolean(this.dependency?.is_reservable);
    },
    canEditReservation() {
      return this.permissions.includes("editar_reservas") || this.permissions.includes("administrar_calendario");
    },
    upcomingReservations() {
      return (this.dependency?.reservations || []).filter((item) =>
        ["pendiente", "aprobada"].includes(item.status)
      );
    },
    historyReservations() {
      return (this.dependency?.reservations || []).filter((item) =>
        ["rechazada", "cancelada", "finalizada"].includes(item.status)
      );
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
    this.calendarOptions = {
      ...this.calendarOptions,
      dateClick: this.handleDateClick,
      eventClick: this.handleEventClick,
      events: this.fetchEvents,
    };
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/spaces/reservations/catalogs");
      this.catalogs = response.data;
    },
    async load() {
      this.loading = true;
      try {
        const response = await axios.get(`/api/spaces/dependencies/${this.itemId}`);
        this.dependency = response.data.data;
      } finally {
        this.loading = false;
      }
    },
    async fetchEvents(fetchInfo, successCallback, failureCallback) {
      try {
        const response = await axios.get("/api/spaces/calendar/events", {
          params: {
            date_from: fetchInfo.startStr,
            date_to: fetchInfo.endStr,
            dependency_id: this.itemId,
          },
        });
        successCallback(response.data.data || []);
      } catch (error) {
        failureCallback(error);
      }
    },
    handleDateClick(info) {
      if (!this.canReserveDependency) {
        return;
      }
      this.editingReservation = null;
      this.draftSelection = {
        maintenance_dependency_id: Number(this.itemId),
        start_date: info.dateStr,
        end_date: info.dateStr,
        start_time: "08:00",
        end_time: "09:00",
      };
      this.showFormModal = true;
    },
    async handleEventClick(info) {
      const response = await axios.get(`/api/spaces/reservations/${info.event.id}`);
      this.selectedReservation = response.data.data;
      this.showPreviewModal = true;
    },
    handleSaved() {
      this.editingReservation = null;
      this.selectedReservation = null;
      this.draftSelection = {};
      this.refreshCalendar();
    },
    openReschedule(item) {
      if (!this.canReserveDependency) {
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
    refreshCalendar() {
      this.$refs.fullCalendar?.getApi()?.refetchEvents();
      this.load();
    },
    statusVariant(status) {
      if (status === "aprobada") return "success";
      if (status === "pendiente") return "warning";
      if (status === "rechazada") return "danger";
      if (status === "cancelada") return "secondary";
      return "info";
    },
  },
};
</script>

<template>
  <Layout>
    <div v-if="dependency" class="d-sm-flex justify-content-between align-items-center mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">{{ dependency.name }}</h4>
        <div class="text-muted">{{ dependency.type?.name || "Sin tipo" }} · {{ dependency.code }}</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/spaces/dependencies" class="btn btn-outline-secondary">Volver</router-link>
        <BButton v-if="canReserveDependency" variant="primary" @click="showFormModal = true">Reservar</BButton>
      </div>
    </div>

    <div v-if="dependency" class="row g-3">
      <div class="col-lg-4">
        <BCard title="Datos generales">
          <div class="text-center mb-3" v-if="dependency.image_url">
            <img :src="dependency.image_url" alt="Dependencia" class="img-fluid rounded border" style="max-height: 220px" />
          </div>
          <div class="mb-2"><span class="text-muted">Ubicación:</span> {{ dependency.location || "-" }}</div>
          <div class="mb-2"><span class="text-muted">Piso o sector:</span> {{ dependency.floor_sector || "-" }}</div>
          <div class="mb-2"><span class="text-muted">Capacidad:</span> {{ dependency.capacity_max || "-" }}</div>
          <div class="mb-2"><span class="text-muted">Estado:</span> {{ dependency.availability_status }}</div>
          <div class="mb-2"><span class="text-muted">Responsable:</span> {{ dependency.responsible_staff?.full_name || "-" }}</div>
          <div class="mb-2"><span class="text-muted">Reservable:</span> {{ dependency.is_reservable ? "Sí" : "No" }}</div>
          <div v-if="dependency.is_reservable" class="mb-2">
            <span class="text-muted">Aprobación:</span> {{ dependency.requires_approval ? "Sí" : "No" }}
          </div>
          <div class="mb-2">
            <span class="text-muted">Inventario:</span> {{ dependency.is_inventory_auditable ? "Se revisa" : "No aplica" }}
          </div>
          <div class="mb-2">
            <span class="text-muted">Mantención:</span> {{ dependency.is_maintenance_location ? "Ubicación habilitada" : "No aplica" }}
          </div>
          <div class="mb-2"><span class="text-muted">Equipamiento:</span> {{ dependency.available_equipment || "-" }}</div>
          <div><span class="text-muted">Observaciones:</span> {{ dependency.observations || "-" }}</div>
        </BCard>

        <BCard v-if="dependency.is_reservable" title="Próximas reservas">
          <div v-if="upcomingReservations.length === 0" class="text-muted">
            No hay reservas próximas.
          </div>
          <div v-for="item in upcomingReservations.slice(0, 5)" :key="item.id" class="border rounded p-2 mb-2">
            <div class="fw-semibold">{{ item.title }}</div>
            <div class="small text-muted">{{ item.start_date }} {{ item.start_time }} - {{ item.end_time }}</div>
            <div class="small">{{ item.staff?.full_name || "-" }}</div>
          </div>
        </BCard>

        <BCard v-if="dependency.is_reservable" title="Gestores de aprobación">
          <div v-if="!(dependency.approvers || []).length" class="text-muted">
            No hay gestores asignados.
          </div>
          <div v-for="manager in dependency.approvers || []" :key="manager.id" class="border rounded p-2 mb-2">
            <div class="fw-semibold">{{ manager.staff?.full_name || manager.name }}</div>
            <div class="small text-muted">{{ manager.email || "-" }}</div>
          </div>
        </BCard>
      </div>

      <div class="col-lg-8">
        <BCard v-if="dependency.is_reservable" title="Calendario propio">
          <FullCalendar ref="fullCalendar" :options="calendarOptions" />
        </BCard>
        <BCard v-else title="Reservas">
          <div class="text-muted">Esta dependencia existe para inventario o mantención, pero no está habilitada para reservas.</div>
        </BCard>

        <BCard v-if="dependency.is_reservable" title="Historial de reservas" class="mt-3">
          <BTable
            :items="historyReservations"
            :fields="[
              { key: 'title', label: 'Reserva' },
              { key: 'staff', label: 'Funcionario' },
              { key: 'start_date', label: 'Fecha' },
              { key: 'status', label: 'Estado' },
            ]"
            small
          >
            <template #cell(staff)="{ item }">
              {{ item.staff?.full_name || "-" }}
            </template>
            <template #cell(start_date)="{ item }">
              {{ item.start_date }} {{ item.start_time }}
            </template>
            <template #cell(status)="{ item }">
              <BBadge :variant="statusVariant(item.status)">{{ item.status }}</BBadge>
            </template>
          </BTable>
        </BCard>
      </div>
    </div>

    <ReservationFormModal
      v-if="dependency?.is_reservable"
      v-model="showFormModal"
      :catalogs="catalogs"
      :reservation="editingReservation"
      :draft-selection="{ ...draftSelection, maintenance_dependency_id: Number(itemId) }"
      @saved="handleSaved"
    />
    <ReservationPreviewModal
      v-model="showPreviewModal"
      :reservation="selectedReservation"
      @saved="handleSaved"
      @reschedule="openReschedule"
      @edit="
        (reservation) => {
          if (!canEditReservation) {
            return;
          }
          editingReservation = reservation;
          showPreviewModal = false;
          draftSelection = {};
          showFormModal = true;
        }
      "
    />
  </Layout>
</template>
