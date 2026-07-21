<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import LoadingState from "../../components/ui/loading-state.vue";
import ReservationFormModal from "../../components/spaces/reservation-form-modal.vue";
import ReservationPreviewModal from "../../components/spaces/reservation-preview-modal.vue";
import "./shared.css";

export default {
  components: {
    Layout,
    LoadingState,
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
    summaryCards() {
      if (!this.dependency) {
        return [];
      }

      return [
        {
          label: "Capacidad",
          value: this.dependency.capacity_max ? this.formatInteger(this.dependency.capacity_max) : "-",
          detail: "personas informadas",
          icon: "bx-group",
          tone: "blue",
        },
        {
          label: "Próximas reservas",
          value: this.formatInteger(this.upcomingReservations.length),
          detail: this.dependency.is_reservable ? "pendientes o aprobadas" : "no reservable",
          icon: "bx-calendar-event",
          tone: "amber",
        },
        {
          label: "Gestores",
          value: this.formatInteger((this.dependency.approvers || []).length),
          detail: "asignados a aprobación",
          icon: "bx-user-check",
          tone: "green",
        },
      ];
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
    statusClass(status) {
      return `spaces-status-pill--${status || "secondary"}`;
    },
    formatInteger(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        maximumFractionDigits: 0,
      });
    },
  },
};
</script>

<template>
  <Layout>
    <div v-if="loading && !dependency" class="spaces-panel">
      <div class="spaces-empty-state">
        <LoadingState message="Cargando dependencia..." compact />
      </div>
    </div>

    <div v-else-if="dependency" class="spaces-shell">
      <section class="spaces-hero">
        <div class="spaces-hero__body">
          <div class="spaces-eyebrow">Ficha de dependencia</div>
          <h4>{{ dependency.name }}</h4>
          <p>{{ dependency.type?.name || "Sin tipo" }} · {{ dependency.code || "Sin código" }}</p>
        </div>
        <div class="spaces-actions">
          <router-link to="/spaces/dependencies" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i>
            <span>Volver</span>
          </router-link>
          <BButton v-if="canReserveDependency" variant="primary" @click="showFormModal = true">
            <i class="bx bx-calendar-plus"></i>
            <span>Reservar</span>
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

      <div class="row g-3">
        <div class="col-xl-4">
          <section class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Datos</div>
                <h5 class="spaces-panel-title">Información general</h5>
              </div>
            </div>
            <div v-if="dependency.image_url" class="mb-3">
              <img :src="dependency.image_url" alt="Dependencia" class="img-fluid rounded border" />
            </div>
            <div class="spaces-detail-grid">
              <div class="spaces-detail-row">
                <span>Ubicación</span>
                <strong>{{ dependency.location || "-" }}</strong>
              </div>
              <div class="spaces-detail-row">
                <span>Piso o sector</span>
                <strong>{{ dependency.floor_sector || "-" }}</strong>
              </div>
              <div class="spaces-detail-row">
                <span>Estado</span>
                <strong>{{ dependency.availability_status || "-" }}</strong>
              </div>
              <div class="spaces-detail-row">
                <span>Responsable</span>
                <strong>{{ dependency.responsible_staff?.full_name || "-" }}</strong>
              </div>
              <div class="spaces-detail-row">
                <span>Reservable</span>
                <strong>{{ dependency.is_reservable ? "Sí" : "No" }}</strong>
              </div>
              <div v-if="dependency.is_reservable" class="spaces-detail-row">
                <span>Aprobación</span>
                <strong>{{ dependency.requires_approval ? "Sí" : "No" }}</strong>
              </div>
              <div class="spaces-detail-row">
                <span>Inventario</span>
                <strong>{{ dependency.is_inventory_auditable ? "Se revisa" : "No aplica" }}</strong>
              </div>
              <div class="spaces-detail-row">
                <span>Mantención</span>
                <strong>{{ dependency.is_maintenance_location ? "Habilitada" : "No aplica" }}</strong>
              </div>
            </div>
            <p class="spaces-panel-subtitle mt-3">
              <strong>Equipamiento:</strong> {{ dependency.available_equipment || "-" }}
            </p>
            <p class="spaces-panel-subtitle">
              <strong>Observaciones:</strong> {{ dependency.observations || "-" }}
            </p>
          </section>

          <section v-if="dependency.is_reservable" class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Agenda</div>
                <h5 class="spaces-panel-title">Próximas reservas</h5>
              </div>
            </div>
            <div v-if="upcomingReservations.length === 0" class="spaces-muted">No hay reservas próximas.</div>
            <div v-else class="spaces-simple-list">
              <div v-for="item in upcomingReservations.slice(0, 5)" :key="item.id" class="spaces-list-item">
                <div class="spaces-table-title">{{ item.title }}</div>
                <span class="spaces-table-subtitle">{{ item.start_date }} {{ item.start_time }} - {{ item.end_time }}</span>
                <span class="spaces-table-subtitle">{{ item.staff?.full_name || "-" }}</span>
              </div>
            </div>
          </section>

          <section v-if="dependency.is_reservable" class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Aprobación</div>
                <h5 class="spaces-panel-title">Gestores</h5>
              </div>
            </div>
            <div v-if="!(dependency.approvers || []).length" class="spaces-muted">No hay gestores asignados.</div>
            <div v-else class="spaces-simple-list">
              <div v-for="manager in dependency.approvers || []" :key="manager.id" class="spaces-list-item">
                <div class="spaces-table-title">{{ manager.staff?.full_name || manager.name }}</div>
                <span class="spaces-table-subtitle">{{ manager.email || "-" }}</span>
              </div>
            </div>
          </section>
        </div>

        <div class="col-xl-8">
          <section v-if="dependency.is_reservable" class="spaces-panel spaces-calendar-host">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Calendario</div>
                <h5 class="spaces-panel-title">Reservas de la dependencia</h5>
              </div>
            </div>
            <FullCalendar ref="fullCalendar" :options="calendarOptions" />
          </section>
          <section v-else class="spaces-panel">
            <div class="spaces-empty-state">
              <i class="bx bx-calendar-x"></i>
              <strong>No habilitada para reservas</strong>
              <span>Esta dependencia se usa para inventario o mantención.</span>
            </div>
          </section>

          <section v-if="dependency.is_reservable" class="spaces-panel">
            <div class="spaces-panel-header">
              <div>
                <div class="spaces-eyebrow">Historial</div>
                <h5 class="spaces-panel-title">Reservas cerradas</h5>
              </div>
            </div>
            <div class="spaces-table-wrap">
              <table class="table spaces-data-table">
                <thead>
                  <tr>
                    <th>Reserva</th>
                    <th>Funcionario</th>
                    <th>Fecha</th>
                    <th class="text-center">Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in historyReservations" :key="item.id">
                    <td>
                      <div class="spaces-table-title">{{ item.title }}</div>
                    </td>
                    <td>{{ item.staff?.full_name || "-" }}</td>
                    <td>{{ item.start_date }} {{ item.start_time }}</td>
                    <td class="text-center">
                      <span class="spaces-status-pill" :class="statusClass(item.status)">{{ item.status }}</span>
                    </td>
                  </tr>
                  <tr v-if="historyReservations.length === 0">
                    <td colspan="4">
                      <div class="spaces-empty-state">
                        <i class="bx bx-history"></i>
                        <strong>Sin historial</strong>
                        <span>No hay reservas cerradas para esta dependencia.</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
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
