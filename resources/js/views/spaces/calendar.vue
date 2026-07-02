<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import { getPdfMake } from "../../utils/pdfmake";
import ReservationFormModal from "../../components/spaces/reservation-form-modal.vue";
import ReservationPreviewModal from "../../components/spaces/reservation-preview-modal.vue";

export default {
  components: {
    Layout,
    Multiselect,
    FullCalendar,
    ReservationFormModal,
    ReservationPreviewModal,
  },
  data() {
    return {
      error: null,
      catalogs: {
        dependencies: [],
        dependency_types: [],
        staff: [],
        departments: [],
        statuses: [],
        repetition_types: [],
      },
      filters: {
        dependency_id: null,
        dependency_type_id: null,
        staff_id: null,
        department_id: null,
        status: null,
        date_from: "",
        date_to: "",
      },
      viewMode: "calendar",
      exporting: false,
      tableLoading: false,
      tableReservations: [],
      tablePagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      },
      visibleRange: {
        date_from: "",
        date_to: "",
      },
      showFormModal: false,
      showPreviewModal: false,
      draftSelection: {},
      editingReservation: null,
      selectedReservation: null,
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        timeZone: "local",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
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
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canCreate() {
      return this.permissions.includes("crear_reservas");
    },
    canEdit() {
      return this.permissions.includes("editar_reservas") || this.permissions.includes("administrar_calendario");
    },
    canExport() {
      return this.permissions.includes("exportar_reservas") || this.permissions.includes("administrar_calendario");
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
    resolvedDateFrom() {
      return this.filters.date_from || this.visibleRange.date_from || null;
    },
    resolvedDateTo() {
      return this.filters.date_to || this.visibleRange.date_to || null;
    },
    tableRangeText() {
      if (!this.resolvedDateFrom || !this.resolvedDateTo) {
        return "Mostrando todas las reservas según filtros aplicados.";
      }

      return `Mostrando eventos desde ${this.resolvedDateFrom} hasta ${this.resolvedDateTo}.`;
    },
    exportRangeLabel() {
      return this.resolvedDateFrom && this.resolvedDateTo
        ? `${this.resolvedDateFrom} a ${this.resolvedDateTo}`
        : "rango actual";
    },
  },
  mounted() {
    this.loadCatalogs();
    this.initCalendar();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/spaces/reservations/catalogs");
      this.catalogs = response.data;
    },
    initCalendar() {
      this.calendarOptions = {
        ...this.calendarOptions,
        dateClick: this.handleDateClick,
        datesSet: this.handleDatesSet,
        eventClick: this.handleEventClick,
        events: this.fetchEvents,
      };
    },
    async fetchEvents(fetchInfo, successCallback, failureCallback) {
      try {
        const response = await axios.get("/api/spaces/calendar/events", {
          params: {
            date_from: this.filters.date_from || fetchInfo.startStr,
            date_to: this.filters.date_to || fetchInfo.endStr,
            ...this.filters,
          },
        });
        successCallback(response.data.data || []);
      } catch (error) {
        this.error = this.formatError(error);
        failureCallback(error);
      }
    },
    formatRangeDate(value, subtractDay = false) {
      const base = String(value || "").slice(0, 10);
      if (!base) {
        return "";
      }

      const date = new Date(`${base}T00:00:00`);
      if (subtractDay) {
        date.setDate(date.getDate() - 1);
      }

      return date.toISOString().slice(0, 10);
    },
    handleDatesSet(info) {
      this.visibleRange = {
        date_from: this.formatRangeDate(info.startStr),
        date_to: this.formatRangeDate(info.endStr, true),
      };
      this.loadTable(1);
    },
    handleDateClick(info) {
      if (!this.canCreate) {
        return;
      }
      this.editingReservation = null;
      this.draftSelection = {
        maintenance_dependency_id: this.filters.dependency_id,
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
    async openPreview(item) {
      const response = await axios.get(`/api/spaces/reservations/${item.id}`);
      this.selectedReservation = response.data.data;
      this.showPreviewModal = true;
    },
    async loadTable(page = 1) {
      this.tableLoading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/spaces/reservations", {
          params: {
            page,
            per_page: this.tablePagination.per_page,
            dependency_id: this.filters.dependency_id,
            dependency_type_id: this.filters.dependency_type_id,
            staff_id: this.filters.staff_id,
            department_id: this.filters.department_id,
            status: this.filters.status,
            date_from: this.resolvedDateFrom,
            date_to: this.resolvedDateTo,
          },
        });

        this.tableReservations = response.data.data || [];
        this.tablePagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.tableLoading = false;
      }
    },
    applyFilters() {
      this.loadTable(1);
      this.refreshCalendar();
    },
    setViewMode(mode) {
      this.viewMode = mode;

      if (mode === "table") {
        this.loadTable(1);
      }
    },
    handleSaved() {
      this.editingReservation = null;
      this.selectedReservation = null;
      this.draftSelection = {};
      this.refreshCalendar();
      this.loadTable(this.tablePagination.current_page || 1);
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
    refreshCalendar() {
      this.$refs.fullCalendar?.getApi()?.refetchEvents();
    },
    async fetchReservationsForExport() {
      const response = await axios.get("/api/spaces/reservations", {
        params: {
          page: 1,
          per_page: 5000,
          dependency_id: this.filters.dependency_id,
          dependency_type_id: this.filters.dependency_type_id,
          staff_id: this.filters.staff_id,
          department_id: this.filters.department_id,
          status: this.filters.status,
          date_from: this.resolvedDateFrom,
          date_to: this.resolvedDateTo,
        },
      });

      return response.data.data || [];
    },
    async exportExcel() {
      if (!this.canExport) {
        return;
      }

      this.exporting = true;
      this.error = null;

      try {
        const rows = await this.fetchReservationsForExport();
        const header = [
          "Evento",
          "Dependencia",
          "Tipo",
          "Funcionario",
          "Departamento",
          "Inicio",
          "Término",
          "Estado",
          "Actividad",
        ];
        const body = rows.map((item) => [
          item.title || "-",
          item.dependency?.name || "-",
          item.dependency?.type?.name || "-",
          item.staff?.full_name || "-",
          item.department?.name || "-",
          `${item.start_date} ${item.start_time}`,
          `${item.end_date} ${item.end_time}`,
          item.status || "-",
          item.activity || "-",
        ]);

        const html = `
          <table>
            <thead>
              <tr>${header.map((cell) => `<th>${cell}</th>`).join("")}</tr>
            </thead>
            <tbody>
              ${body
                .map((row) => `<tr>${row.map((cell) => `<td>${String(cell)}</td>`).join("")}</tr>`)
                .join("")}
            </tbody>
          </table>
        `;
        const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `reservas_${this.exportRangeLabel.replace(/\s+/g, "_")}.xls`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exporting = false;
      }
    },
    async exportPdf() {
      if (!this.canExport) {
        return;
      }

      this.exporting = true;
      this.error = null;

      try {
        const rows = await this.fetchReservationsForExport();
        const pdfMake = getPdfMake();
        const docDefinition = {
          pageOrientation: "landscape",
          content: [
            { text: "Reservas de dependencias", style: "header" },
            { text: `Período: ${this.exportRangeLabel}`, margin: [0, 4, 0, 12] },
            {
              table: {
                headerRows: 1,
                widths: [120, 100, 100, 90, 70, 80, "*"],
                body: [
                  ["Evento", "Dependencia", "Funcionario", "Departamento", "Estado", "Inicio", "Término"],
                  ...rows.map((item) => [
                    item.title || "-",
                    item.dependency?.name || "-",
                    item.staff?.full_name || "-",
                    item.department?.name || "-",
                    item.status || "-",
                    `${item.start_date} ${item.start_time}`,
                    `${item.end_date} ${item.end_time}`,
                  ]),
                ],
              },
              layout: "lightHorizontalLines",
            },
          ],
          styles: {
            header: {
              fontSize: 16,
              bold: true,
            },
          },
          defaultStyle: {
            fontSize: 9,
          },
        };
        pdfMake.createPdf(docDefinition).download(`reservas_${this.exportRangeLabel.replace(/\s+/g, "_")}.pdf`);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exporting = false;
      }
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
        <h4 class="mb-0">Calendario de reservas</h4>
        <div class="text-muted">Vista diaria, semanal y mensual con filtros operativos.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/spaces/reservations" class="btn btn-outline-secondary">Reservas</router-link>
        <BButton v-if="canExport" variant="outline-success" :disabled="exporting" @click="exportExcel">Exportar Excel</BButton>
        <BButton v-if="canExport" variant="outline-danger" :disabled="exporting" @click="exportPdf">Exportar PDF</BButton>
        <BButton v-if="canCreate" variant="primary" @click="showFormModal = true">Nueva reserva</BButton>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <BButton :variant="viewMode === 'calendar' ? 'primary' : 'outline-primary'" @click="setViewMode('calendar')">
        Vista calendario
      </BButton>
      <BButton :variant="viewMode === 'table' ? 'primary' : 'outline-primary'" @click="setViewMode('table')">
        Vista tabla
      </BButton>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
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
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
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
        <div class="col-md-1">
          <BButton variant="secondary" @click="applyFilters">Aplicar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard v-if="viewMode === 'calendar'">
      <FullCalendar ref="fullCalendar" :options="calendarOptions" />
    </BCard>

    <BCard v-else>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-1">Tabla de eventos por dependencia</h5>
          <div class="text-muted small">{{ tableRangeText }}</div>
        </div>
        <div class="text-muted small">Total: {{ tablePagination.total }}</div>
      </div>

      <BTable
        :items="tableReservations"
        :busy="tableLoading"
        :fields="[
          { key: 'title', label: 'Evento' },
          { key: 'dependency', label: 'Dependencia' },
          { key: 'staff', label: 'Funcionario' },
          { key: 'department', label: 'Departamento' },
          { key: 'schedule', label: 'Horario' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
        responsive
        small
      >
        <template #cell(title)="{ item }">
          <div class="fw-semibold">{{ item.title }}</div>
          <div class="text-muted small">{{ item.activity || "-" }}</div>
        </template>
        <template #cell(dependency)="{ item }">
          <div class="fw-semibold">{{ item.dependency?.name || "-" }}</div>
          <div class="text-muted small">{{ item.dependency?.type?.name || "Sin tipo" }}</div>
        </template>
        <template #cell(staff)="{ item }">
          {{ item.staff?.full_name || "-" }}
        </template>
        <template #cell(department)="{ item }">
          {{ item.department?.name || "-" }}
        </template>
        <template #cell(schedule)="{ item }">
          <div>{{ item.start_date }} {{ item.start_time }}</div>
          <div class="text-muted small">{{ item.end_date }} {{ item.end_time }}</div>
        </template>
        <template #cell(status)="{ item }">
          <BBadge :variant="statusVariant(item.status)">{{ item.status }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <BButton size="sm" variant="outline-primary" @click="openPreview(item)">Ver</BButton>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-if="tablePagination.last_page > 1"
          v-model="tablePagination.current_page"
          :total-rows="tablePagination.total"
          :per-page="tablePagination.per_page"
          pills
          align="end"
          @update:model-value="loadTable"
        />
      </div>
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
      @edit="
        (reservation) => {
          if (!canEdit) {
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
