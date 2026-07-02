<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import EventFormModal from "../../components/relevant-calendar/event-form-modal.vue";

const currentYear = new Date().getFullYear();

export default {
  components: {
    Layout,
    LoadingState,
    Multiselect,
    FullCalendar,
    EventFormModal,
  },
  data() {
    return {
      loadingCatalogs: false,
      loadingOverview: false,
      tableLoading: false,
      error: null,
      viewMode: "calendar",
      showFormModal: false,
      editingEvent: null,
      defaultMode: "single",
      exporting: false,
      catalogs: {
        process_types: [],
        institutions: [],
        departments: [],
        users: [],
        priorities: [],
        statuses: [],
        capabilities: {},
      },
      overview: {
        stats: {},
        upcoming: [],
        overdue: [],
        current_month: [],
        history: [],
        alerts: [],
      },
      filters: {
        search: "",
        month: null,
        year: null,
        process_type_id: null,
        institution_id: null,
        status: null,
        priority: null,
        responsible_user_id: null,
        department_id: null,
        overdue_only: false,
        upcoming_only: false,
        recurring_only: false,
        manual_only: false,
      },
      items: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      },
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        timeZone: "local",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
        firstDay: 1,
        selectable: false,
        editable: false,
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
        },
        buttonText: {
          today: "Hoy",
          month: "Mes",
          week: "Semana",
          day: "Día",
          list: "Listado",
        },
        noEventsText: "No hay eventos para mostrar",
        allDayText: "Todo el día",
        events: [],
      },
    };
  },
  computed: {
    canCreate() {
      return Boolean(this.catalogs.capabilities?.can_create);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    canManageTypes() {
      return Boolean(this.catalogs.capabilities?.can_manage_types);
    },
    canManageInstitutions() {
      return Boolean(this.catalogs.capabilities?.can_manage_institutions);
    },
    processTypeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.process_types || []).map((item) => ({ value: item.id, label: item.name }))
      );
    },
    institutionOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.institutions || []).map((item) => ({ value: item.id, label: item.name }))
      );
    },
    departmentOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.departments || []).map((item) => ({ value: item.id, label: item.name }))
      );
    },
    userOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.responsible_users || []).map((item) => ({ value: item.id, label: item.name }))
      );
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((item) => ({ value: item.value, label: item.label }))
      );
    },
    priorityOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.priorities || []).map((item) => ({ value: item.value, label: item.label }))
      );
    },
    monthOptions() {
      return [
        { value: 1, label: "Enero" },
        { value: 2, label: "Febrero" },
        { value: 3, label: "Marzo" },
        { value: 4, label: "Abril" },
        { value: 5, label: "Mayo" },
        { value: 6, label: "Junio" },
        { value: 7, label: "Julio" },
        { value: 8, label: "Agosto" },
        { value: 9, label: "Septiembre" },
        { value: 10, label: "Octubre" },
        { value: 11, label: "Noviembre" },
        { value: 12, label: "Diciembre" },
      ];
    },
    yearOptions() {
      return Array.from({ length: 8 }, (_, index) => {
        const year = currentYear - 2 + index;
        return { value: year, label: String(year) };
      });
    },
  },
  mounted() {
    this.initCalendar();
    this.loadCatalogs();
    this.loadOverview();
    this.loadTable();
  },
  methods: {
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/relevant-calendar/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingCatalogs = false;
      }
    },
    initCalendar() {
      this.calendarOptions = {
        ...this.calendarOptions,
        eventClick: ({ event }) => {
          this.$router.push(`/relevant-calendar/events/${event.id}`);
        },
        events: this.fetchCalendarEvents,
      };
    },
    async fetchCalendarEvents(fetchInfo, successCallback, failureCallback) {
      try {
        const response = await axios.get("/api/relevant-calendar/feed", {
          params: {
            ...this.currentFilterParams(),
            date_from: fetchInfo.startStr.slice(0, 10),
            date_to: fetchInfo.endStr.slice(0, 10),
          },
        });
        successCallback(response.data.data || []);
      } catch (error) {
        this.error = this.formatError(error);
        failureCallback(error);
      }
    },
    async loadOverview() {
      this.loadingOverview = true;
      this.error = null;
      try {
        const response = await axios.get("/api/relevant-calendar/overview", {
          params: this.currentFilterParams(),
        });
        this.overview = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingOverview = false;
      }
    },
    async loadTable(page = 1) {
      this.tableLoading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/relevant-calendar/events", {
          params: {
            ...this.currentFilterParams(),
            page,
            per_page: this.pagination.per_page,
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
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
    currentFilterParams() {
      return {
        search: this.filters.search || null,
        month: this.filters.month || null,
        year: this.filters.year || null,
        process_type_id: this.filters.process_type_id,
        institution_id: this.filters.institution_id,
        status: this.filters.status,
        priority: this.filters.priority,
        responsible_user_id: this.filters.responsible_user_id,
        department_id: this.filters.department_id,
        overdue_only: this.filters.overdue_only,
        upcoming_only: this.filters.upcoming_only,
        recurring_only: this.filters.recurring_only,
        manual_only: this.filters.manual_only,
      };
    },
    applyFilters() {
      this.loadOverview();
      this.loadTable(1);
      this.$refs.fullCalendar?.getApi()?.refetchEvents();
    },
    resetFilters() {
      this.filters = {
        search: "",
        month: null,
        year: null,
        process_type_id: null,
        institution_id: null,
        status: null,
        priority: null,
        responsible_user_id: null,
        department_id: null,
        overdue_only: false,
        upcoming_only: false,
        recurring_only: false,
        manual_only: false,
      };
      this.applyFilters();
    },
    openCreate(mode = "single") {
      this.defaultMode = mode;
      this.editingEvent = null;
      this.showFormModal = true;
    },
    async openEdit(item) {
      try {
        const response = await axios.get(`/api/relevant-calendar/events/${item.id}`);
        this.editingEvent = response.data.data;
        this.showFormModal = true;
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    handleSaved() {
      this.showFormModal = false;
      this.editingEvent = null;
      this.loadOverview();
      this.loadTable(this.pagination.current_page || 1);
      this.$refs.fullCalendar?.getApi()?.refetchEvents();
    },
    goToDetail(item) {
      this.$router.push(`/relevant-calendar/events/${item.id}`);
    },
    canEditItem(item) {
      if (this.catalogs.capabilities?.can_manage_all) {
        return true;
      }

      const managed = this.catalogs.managed_department_ids || [];
      return Boolean(item.department_id && managed.includes(item.department_id));
    },
    async exportTableCsv() {
      if (!this.canExport) return;
      this.exporting = true;
      try {
        const rows = await this.fetchExportRows();
        const header = ["Título", "Tipo", "Institución", "Departamento", "Responsable", "Vencimiento", "Prioridad", "Estado"];
        const body = rows.map((item) => [
          item.title || "-",
          item.process_type?.name || item.processType?.name || "-",
          item.institution?.name || "-",
          item.department?.name || "-",
          item.responsible_user?.name || item.responsibleUser?.name || "-",
          item.due_date || item.end_date || item.start_date || "-",
          item.priority || "-",
          item.effective_status || item.status || "-",
        ]);
        const csv = [header, ...body]
          .map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(","))
          .join("\n");
        this.downloadBlob(csv, "text/csv;charset=utf-8", `calendario_fechas_relevantes_${currentYear}.csv`);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exporting = false;
      }
    },
    async exportTableExcel() {
      if (!this.canExport) return;
      this.exporting = true;
      try {
        const rows = await this.fetchExportRows();
        const header = ["Título", "Tipo", "Institución", "Departamento", "Responsable", "Inicio", "Término", "Prioridad", "Estado"];
        const body = rows.map((item) => [
          item.title || "-",
          item.process_type?.name || item.processType?.name || "-",
          item.institution?.name || "-",
          item.department?.name || "-",
          item.responsible_user?.name || item.responsibleUser?.name || "-",
          item.start_date || "-",
          item.end_date || "-",
          item.priority || "-",
          item.effective_status || item.status || "-",
        ]);
        const html = `
          <table>
            <thead><tr>${header.map((cell) => `<th>${cell}</th>`).join("")}</tr></thead>
            <tbody>${body.map((row) => `<tr>${row.map((cell) => `<td>${String(cell)}</td>`).join("")}</tr>`).join("")}</tbody>
          </table>
        `;
        this.downloadBlob(html, "application/vnd.ms-excel;charset=utf-8", `calendario_fechas_relevantes_${currentYear}.xls`);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.exporting = false;
      }
    },
    exportSectionCsv(name, rows) {
      const header = ["Título", "Institución", "Responsable", "Vencimiento", "Estado", "Prioridad"];
      const body = (rows || []).map((item) => [
        item.title || "-",
        item.institution?.name || "-",
        item.responsible_user?.name || item.responsibleUser?.name || "-",
        item.due_date || item.end_date || item.start_date || "-",
        item.effective_status || item.status || "-",
        item.priority || "-",
      ]);
      const csv = [header, ...body]
        .map((row) => row.map((cell) => `"${String(cell ?? "").replace(/"/g, '""')}"`).join(","))
        .join("\n");
      this.downloadBlob(csv, "text/csv;charset=utf-8", `${name}.csv`);
    },
    async fetchExportRows() {
      const response = await axios.get("/api/relevant-calendar/events", {
        params: {
          ...this.currentFilterParams(),
          page: 1,
          per_page: 5000,
        },
      });
      return response.data.data || [];
    },
    downloadBlob(contents, type, filename) {
      const blob = new Blob([contents], { type });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    },
    statusVariant(status) {
      if (status === "completado" || status === "enviado" || status === "declarado") return "success";
      if (status === "vencido") return "danger";
      if (status === "en_preparacion" || status === "en_revision") return "warning";
      if (status === "archivado" || status === "no_aplica") return "secondary";
      return "info";
    },
    priorityVariant(priority) {
      if (priority === "critica") return "danger";
      if (priority === "alta") return "warning";
      if (priority === "media") return "primary";
      return "secondary";
    },
    kindLabel(item) {
      const kind = item.event_kind || item.eventKind;
      if (kind === "stage") return "Etapa";
      if (kind === "occurrence") return "Recurrente";
      if (kind === "process") return "Proceso";
      return "Evento";
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).slice(0, 10).split("-");
      return `${day}/${month}/${year}`;
    },
    formatDateTime(item) {
      const start = this.formatDate(item.start_date);
      const end = this.formatDate(item.end_date || item.start_date);
      const startTime = item.start_time ? String(item.start_time).slice(0, 5) : "";
      const endTime = item.end_time ? String(item.end_time).slice(0, 5) : "";
      const timeText = startTime ? `${startTime}${endTime ? ` - ${endTime}` : ""}` : "Sin hora";
      return `${start} / ${end} · ${timeText}`;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo cargar el módulo."
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">Calendario y Fechas Relevantes</h4>
        <div class="text-muted">Gestión centralizada de procesos declarativos, vencimientos y alertas.</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <router-link v-if="canManageTypes" to="/relevant-calendar/process-types" class="btn btn-outline-secondary">Tipos</router-link>
        <router-link v-if="canManageInstitutions" to="/relevant-calendar/institutions" class="btn btn-outline-secondary">Instituciones</router-link>
        <BButton v-if="canExport" variant="outline-success" :disabled="exporting" @click="exportTableExcel">Exportar Excel</BButton>
        <BButton v-if="canExport" variant="outline-primary" :disabled="exporting" @click="exportTableCsv">Exportar CSV</BButton>
        <BButton v-if="canCreate" variant="primary" @click="openCreate('single')">Nuevo evento</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row g-3 mb-3">
      <div class="col-md-6 col-xl-2">
        <BCard>
          <div class="text-muted small">Activos</div>
          <div class="display-6 fw-semibold">{{ overview.stats?.active ?? 0 }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-2">
        <BCard>
          <div class="text-muted small">Próximos</div>
          <div class="display-6 fw-semibold">{{ overview.stats?.upcoming ?? 0 }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-2">
        <BCard>
          <div class="text-muted small">Vencidos</div>
          <div class="display-6 fw-semibold text-danger">{{ overview.stats?.overdue ?? 0 }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-2">
        <BCard>
          <div class="text-muted small">Del mes</div>
          <div class="display-6 fw-semibold">{{ overview.stats?.current_month ?? 0 }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-2">
        <BCard>
          <div class="text-muted small">Series</div>
          <div class="display-6 fw-semibold">{{ overview.stats?.recurring ?? 0 }}</div>
        </BCard>
      </div>
      <div class="col-md-6 col-xl-2">
        <BCard>
          <div class="text-muted small">Alertas hoy</div>
          <div class="display-6 fw-semibold">{{ overview.alerts?.length ?? 0 }}</div>
        </BCard>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Título, descripción, institución o responsable" @keyup.enter="applyFilters" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Mes</label>
          <Multiselect v-model="filters.month" :options="monthOptions" :searchable="false" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Año</label>
          <Multiselect v-model="filters.year" :options="yearOptions" :searchable="false" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.process_type_id" :options="processTypeOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Institución</label>
          <Multiselect v-model="filters.institution_id" :options="institutionOptions" :searchable="true" />
        </div>

        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="false" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Prioridad</label>
          <Multiselect v-model="filters.priority" :options="priorityOptions" :searchable="false" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Responsable</label>
          <Multiselect v-model="filters.responsible_user_id" :options="userOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
        </div>
        <div class="col-md-2 d-flex gap-2">
          <BButton variant="secondary" @click="applyFilters">Aplicar</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>

        <div class="col-md-12">
          <div class="d-flex flex-wrap gap-3">
            <BFormCheckbox v-model="filters.overdue_only">Solo vencidos</BFormCheckbox>
            <BFormCheckbox v-model="filters.upcoming_only">Solo próximos</BFormCheckbox>
            <BFormCheckbox v-model="filters.recurring_only">Solo recurrentes</BFormCheckbox>
            <BFormCheckbox v-model="filters.manual_only">Solo manuales</BFormCheckbox>
          </div>
        </div>
      </div>
    </BCard>

    <div class="d-flex gap-2 mb-3">
      <BButton :variant="viewMode === 'calendar' ? 'primary' : 'outline-primary'" @click="viewMode = 'calendar'">Calendario</BButton>
      <BButton :variant="viewMode === 'table' ? 'primary' : 'outline-primary'" @click="viewMode = 'table'">Bandeja</BButton>
      <BButton v-if="canCreate" variant="outline-success" @click="openCreate('recurring')">Nueva serie</BButton>
      <BButton v-if="canCreate" variant="outline-info" @click="openCreate('process')">Nuevo proceso por etapas</BButton>
    </div>

    <BCard v-if="viewMode === 'calendar'" class="mb-3">
      <FullCalendar ref="fullCalendar" :options="calendarOptions" />
    </BCard>

    <BCard v-else class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-1">Listado filtrado</h5>
          <div class="text-muted small">Eventos operativos según filtros actuales.</div>
        </div>
        <div class="text-muted small">Total: {{ pagination.total }}</div>
      </div>

      <BTable
        :items="items"
        :busy="tableLoading"
        small
        responsive
        :fields="[
          { key: 'title', label: 'Evento' },
          { key: 'schedule', label: 'Fechas' },
          { key: 'responsible', label: 'Responsable' },
          { key: 'priority', label: 'Prioridad' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando eventos..." compact />
        </template>
        <template #cell(title)="{ item }">
          <div class="d-flex align-items-start gap-2">
            <span class="rounded-circle mt-1" :style="{ width: '12px', height: '12px', backgroundColor: item.calendar_color || '#0d6efd' }"></span>
            <div>
              <div class="fw-semibold">{{ item.title }}</div>
              <div class="text-muted small">
                {{ kindLabel(item) }} · {{ item.process_type?.name || item.processType?.name || "Sin tipo" }} ·
                {{ item.institution?.name || "-" }}
              </div>
            </div>
          </div>
        </template>
        <template #cell(schedule)="{ item }">
          <div>{{ formatDate(item.due_date || item.end_date || item.start_date) }}</div>
          <div class="text-muted small">{{ formatDateTime(item) }}</div>
        </template>
        <template #cell(responsible)="{ item }">
          <div>{{ item.responsible_user?.name || item.responsibleUser?.name || "-" }}</div>
          <div class="text-muted small">{{ item.department?.name || "-" }}</div>
        </template>
        <template #cell(priority)="{ item }">
          <BBadge :variant="priorityVariant(item.priority)">{{ item.priority }}</BBadge>
        </template>
        <template #cell(status)="{ item }">
          <BBadge :variant="statusVariant(item.effective_status || item.status)">{{ item.effective_status || item.status }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="outline-primary" @click="goToDetail(item)">Ver</BButton>
            <BButton v-if="canEditItem(item)" size="sm" variant="outline-secondary" @click="openEdit(item)">Editar</BButton>
          </div>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-if="pagination.last_page > 1"
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          align="end"
          pills
          @update:model-value="loadTable"
        />
      </div>
    </BCard>

    <div class="row g-3">
      <div class="col-xl-6">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-1">Próximos vencimientos</h5>
              <div class="text-muted small">Ordenados por fecha y prioridad.</div>
            </div>
            <BButton v-if="canExport" size="sm" variant="outline-primary" @click="exportSectionCsv('proximos_vencimientos', overview.upcoming)">CSV</BButton>
          </div>
          <div v-if="loadingOverview" class="text-muted">Cargando...</div>
          <div v-else-if="!overview.upcoming?.length" class="text-muted">No hay procesos próximos.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Evento</th>
                  <th>Vence</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in overview.upcoming" :key="`upcoming-${item.id}`" @click="goToDetail(item)" style="cursor: pointer;">
                  <td>
                    <div class="fw-semibold">{{ item.title }}</div>
                    <div class="text-muted small">{{ item.institution?.name || "-" }}</div>
                  </td>
                  <td>{{ formatDate(item.due_date || item.end_date || item.start_date) }}</td>
                  <td><BBadge :variant="statusVariant(item.effective_status || item.status)">{{ item.effective_status || item.status }}</BBadge></td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-xl-6">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-1">Vencidos</h5>
              <div class="text-muted small">Pendientes después de su fecha de vencimiento.</div>
            </div>
            <BButton v-if="canExport" size="sm" variant="outline-primary" @click="exportSectionCsv('procesos_vencidos', overview.overdue)">CSV</BButton>
          </div>
          <div v-if="loadingOverview" class="text-muted">Cargando...</div>
          <div v-else-if="!overview.overdue?.length" class="text-muted">No hay procesos vencidos.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Evento</th>
                  <th>Vence</th>
                  <th>Prioridad</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in overview.overdue" :key="`overdue-${item.id}`" @click="goToDetail(item)" style="cursor: pointer;">
                  <td>
                    <div class="fw-semibold">{{ item.title }}</div>
                    <div class="text-muted small">{{ item.department?.name || "-" }}</div>
                  </td>
                  <td>{{ formatDate(item.due_date || item.end_date || item.start_date) }}</td>
                  <td><BBadge :variant="priorityVariant(item.priority)">{{ item.priority }}</BBadge></td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-xl-6">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-1">Procesos del mes</h5>
              <div class="text-muted small">Todo lo que vence o se ejecuta este mes.</div>
            </div>
            <BButton v-if="canExport" size="sm" variant="outline-primary" @click="exportSectionCsv('procesos_del_mes', overview.current_month)">CSV</BButton>
          </div>
          <div v-if="loadingOverview" class="text-muted">Cargando...</div>
          <div v-else-if="!overview.current_month?.length" class="text-muted">Sin procesos para este mes.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Evento</th>
                  <th>Fecha</th>
                  <th>Responsable</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in overview.current_month" :key="`month-${item.id}`" @click="goToDetail(item)" style="cursor: pointer;">
                  <td>
                    <div class="fw-semibold">{{ item.title }}</div>
                    <div class="text-muted small">{{ item.process_type?.name || "-" }}</div>
                  </td>
                  <td>{{ formatDate(item.due_date || item.end_date || item.start_date) }}</td>
                  <td>{{ item.responsible_user?.name || item.responsibleUser?.name || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-xl-6">
        <BCard>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-1">Historial</h5>
              <div class="text-muted small">Eventos ya cumplidos o cerrados.</div>
            </div>
            <BButton v-if="canExport" size="sm" variant="outline-primary" @click="exportSectionCsv('historial_calendario', overview.history)">CSV</BButton>
          </div>
          <div v-if="loadingOverview" class="text-muted">Cargando...</div>
          <div v-else-if="!overview.history?.length" class="text-muted">Sin historial disponible.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Evento</th>
                  <th>Completado</th>
                  <th>Por</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in overview.history" :key="`history-${item.id}`" @click="goToDetail(item)" style="cursor: pointer;">
                  <td>
                    <div class="fw-semibold">{{ item.title }}</div>
                    <div class="text-muted small">{{ item.status }}</div>
                  </td>
                  <td>{{ formatDate(item.completed_at || item.updated_at || item.end_date || item.start_date) }}</td>
                  <td>{{ item.completed_by?.name || item.completedBy?.name || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>

    <BCard class="mt-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-1">Alertas internas del día</h5>
          <div class="text-muted small">Recordatorios activos según reglas configuradas.</div>
        </div>
      </div>
      <div v-if="!overview.alerts?.length" class="text-muted">No hay alertas activas hoy.</div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Evento</th>
              <th>Recordatorio</th>
              <th>Vence</th>
              <th>Responsable</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="alert in overview.alerts" :key="`alert-${alert.event_id}-${alert.reminder_label}`">
              <td>{{ alert.title }}</td>
              <td>{{ alert.reminder_label }}</td>
              <td>{{ formatDate(alert.due_date) }}</td>
              <td>{{ alert.responsible || "-" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <EventFormModal
      v-model="showFormModal"
      :catalogs="catalogs"
      :event-record="editingEvent"
      :default-mode="defaultMode"
      @saved="handleSaved"
    />
  </Layout>
</template>

<style scoped>
:deep(.calendar-event-overdue .fc-event-main) {
  font-weight: 700;
}

:deep(.calendar-event-critical) {
  box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.35);
}
</style>
