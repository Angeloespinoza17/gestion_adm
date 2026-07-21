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
import "./shared.css";

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
      visibleApprovedEventsTotal: 0,
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
        buttonIcons: false,
        buttonText: {
          prev: "‹",
          next: "›",
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
    summaryCards() {
      return [
        {
          label: "Eventos",
          value: this.formatInteger(this.visibleApprovedEventsTotal),
          detail: "aprobados en rango visible",
          icon: "bx-calendar",
          tone: "blue",
        },
        {
          label: "Vista",
          value: this.viewMode === "calendar" ? "Calendario" : "Tabla",
          detail: this.exportRangeLabel,
          icon: this.viewMode === "calendar" ? "bx-calendar-event" : "bx-table",
          tone: "slate",
        },
        {
          label: "Dependencias",
          value: this.formatInteger(this.catalogs.dependencies.length),
          detail: "disponibles para filtrar",
          icon: "bx-buildings",
          tone: "green",
        },
      ];
    },
    hasActiveFilters() {
      return Boolean(
        this.filters.dependency_id ||
        this.filters.dependency_type_id ||
        this.filters.staff_id ||
        this.filters.department_id ||
        this.filters.status ||
        this.filters.date_from ||
        this.filters.date_to
      );
    },
  },
  mounted() {
    this.loadCatalogs();
    this.initCalendar();
    this.$nextTick(() => this.ensureInitialVisibleRange());
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
        const events = response.data.data || [];
        this.visibleApprovedEventsTotal = events.length;
        successCallback(events);
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
    ensureInitialVisibleRange() {
      if (this.visibleRange.date_from && this.visibleRange.date_to) {
        return;
      }

      const calendarApi = this.$refs.fullCalendar?.getApi?.();
      const view = calendarApi?.view;

      if (!view?.activeStart || !view?.activeEnd) {
        this.loadTable(1);
        return;
      }

      this.visibleRange = {
        date_from: this.formatRangeDate(view.activeStart.toISOString()),
        date_to: this.formatRangeDate(view.activeEnd.toISOString(), true),
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
    resetFilters() {
      this.filters = {
        dependency_id: null,
        dependency_type_id: null,
        staff_id: null,
        department_id: null,
        status: null,
        date_from: "",
        date_to: "",
      };
      this.applyFilters();
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
        const pdfHeader = ["Evento", "Dependencia", "Funcionario", "Departamento", "Estado", "Inicio", "Término"].map(
          (text) => ({ text, style: "tableHeader" })
        );
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
                  pdfHeader,
                  ...rows.map((item) => [
                    item.title || "-",
                    item.dependency?.name || "-",
                    item.staff?.full_name || "-",
                    item.department?.name || "-",
                    this.statusPdfCell(item.status),
                    this.formatPdfDateTime(item.start_date, item.start_time),
                    this.formatPdfDateTime(item.end_date, item.end_time),
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
            tableHeader: {
              bold: true,
              color: "#111827",
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
    statusClass(status) {
      return `spaces-status-pill--${status || "secondary"}`;
    },
    statusPdfCell(status) {
      const normalized = String(status || "").toLowerCase();
      const palette = {
        pendiente: { fillColor: "#fef3c7", color: "#92400e" },
        aprobada: { fillColor: "#d1fae5", color: "#065f46" },
        rechazada: { fillColor: "#fee2e2", color: "#991b1b" },
        cancelada: { fillColor: "#e5e7eb", color: "#374151" },
        finalizada: { fillColor: "#dbeafe", color: "#1d4ed8" },
      };
      const style = palette[normalized] || { fillColor: "#f3f4f6", color: "#374151" };

      return {
        text: normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : "-",
        alignment: "center",
        bold: true,
        margin: [0, 2, 0, 2],
        ...style,
      };
    },
    formatPdfDateTime(date, time) {
      const [year, month, day] = String(date || "").slice(0, 10).split("-");

      if (!year || !month || !day) {
        return "-";
      }

      return `${day}-${month}-${year}${time ? ` ${String(time).slice(0, 5)}` : ""}`;
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
          <h4>Calendario de reservas</h4>
          <p>Vista diaria, semanal y mensual con filtros operativos y exportación de eventos.</p>
        </div>
        <div class="spaces-actions">
          <router-link to="/spaces/reservations" class="btn btn-outline-secondary">
            <i class="bx bx-list-ul"></i>
            <span>Reservas</span>
          </router-link>
          <BButton v-if="canExport" variant="outline-success" :disabled="exporting" @click="exportExcel">
            <i class="bx bx-spreadsheet"></i>
            <span>Excel</span>
          </BButton>
          <BButton v-if="canExport" variant="outline-danger" :disabled="exporting" @click="exportPdf">
            <i class="bx bx-file"></i>
            <span>PDF</span>
          </BButton>
          <BButton v-if="canCreate" variant="primary" @click="showFormModal = true">
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
            <div class="spaces-eyebrow">Vista</div>
            <h5 class="spaces-panel-title">Modo de revisión</h5>
          </div>
          <div class="spaces-mode-switch">
            <BButton :variant="viewMode === 'calendar' ? 'primary' : 'outline-primary'" @click="setViewMode('calendar')">
              <i class="bx bx-calendar-event"></i>
              <span>Calendario</span>
            </BButton>
            <BButton :variant="viewMode === 'table' ? 'primary' : 'outline-primary'" @click="setViewMode('table')">
              <i class="bx bx-table"></i>
              <span>Tabla</span>
            </BButton>
          </div>
        </div>

        <div class="spaces-filter-grid spaces-filter-grid--wide">
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
            <span>Departamento</span>
            <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
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
            <BButton variant="primary" @click="applyFilters">
              <i class="bx bx-filter-alt"></i>
              <span>Aplicar</span>
            </BButton>
            <BButton variant="outline-secondary" :disabled="!hasActiveFilters" @click="resetFilters">
              <i class="bx bx-x"></i>
              <span>Limpiar</span>
            </BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-0">{{ error }}</BAlert>

      <section v-if="viewMode === 'calendar'" class="spaces-panel spaces-calendar-host">
        <FullCalendar ref="fullCalendar" :options="calendarOptions" />
      </section>

      <section v-else class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Tabla</div>
            <h5 class="spaces-panel-title">Eventos por dependencia</h5>
            <p class="spaces-panel-subtitle">{{ tableRangeText }}</p>
          </div>
          <div class="spaces-panel-meta">Total: {{ formatInteger(tablePagination.total) }}</div>
        </div>

        <div v-if="tableLoading && tableReservations.length === 0" class="spaces-empty-state">
          <span>Cargando eventos...</span>
        </div>
        <div v-else class="spaces-table-wrap">
          <table class="table spaces-data-table spaces-data-table--wide">
            <thead>
              <tr>
                <th style="width: 22%">Evento</th>
                <th style="width: 18%">Dependencia</th>
                <th style="width: 17%">Funcionario</th>
                <th style="width: 15%">Departamento</th>
                <th style="width: 14%">Horario</th>
                <th style="width: 8%" class="text-center">Estado</th>
                <th style="width: 6%" class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in tableReservations" :key="item.id">
                <td>
                  <div class="spaces-table-title">{{ item.title }}</div>
                  <span class="spaces-table-subtitle">{{ item.activity || "Sin actividad" }}</span>
                </td>
                <td>
                  <div class="spaces-table-title">{{ item.dependency?.name || "-" }}</div>
                  <span class="spaces-table-subtitle">{{ item.dependency?.type?.name || "Sin tipo" }}</span>
                </td>
                <td>{{ item.staff?.full_name || "-" }}</td>
                <td>{{ item.department?.name || "-" }}</td>
                <td>
                  <div>{{ item.start_date }} {{ item.start_time }}</div>
                  <span class="spaces-table-subtitle">{{ item.end_date }} {{ item.end_time }}</span>
                </td>
                <td class="text-center">
                  <span class="spaces-status-pill" :class="statusClass(item.status)">{{ item.status }}</span>
                </td>
                <td>
                  <div class="spaces-row-actions">
                    <BButton size="sm" variant="outline-primary" title="Ver" aria-label="Ver evento" @click="openPreview(item)">
                      <i class="bx bx-show"></i>
                      <span>Ver</span>
                    </BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="tableReservations.length === 0">
                <td colspan="7">
                  <div class="spaces-empty-state">
                    <i class="bx bx-calendar-x"></i>
                    <strong>No hay eventos para mostrar</strong>
                    <span>Revisa el rango visible o cambia los filtros aplicados.</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

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
