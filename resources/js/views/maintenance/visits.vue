<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import MaintenanceVisitPlanningModal from "../../components/maintenance/MaintenanceVisitPlanningModal.vue";
import { getPdfMake } from "../../utils/pdfmake";
import {
  buildMaintenanceVisitsCalendarPdf,
  maintenanceCalendarPdfFilename,
} from "../../utils/maintenance-visits-calendar-pdf";

const pad = (value) => String(value).padStart(2, "0");
const localYMD = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const localYM = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

const emptyForm = () => ({
  id: null,
  maintenance_dependency_id: "",
  responsible: "",
  visit_date: localYMD(),
  visit_time: "",
  visit_type: "Inspección",
  status: "Programada",
  notes: "",
});

export default {
  components: { Layout, MaintenanceVisitPlanningModal },
  data() {
    return {
      debugModals: false,
      loading: false,
      saving: false,
      exporting: "",
      error: null,
      success: null,
      showModalVisit: false,
      showPlanningModal: false,
      showExportModal: false,
      mobileFiltersOpen: false,
      viewMode: "table",
      calendarMonth: localYM(),
      search: "",
      filters: {
        from: "",
        to: "",
        dependency_id: "",
        responsible_staff_id: "",
        status: "",
        visit_type: "",
      },
      exportForm: {
        month: localYM(),
        responsible_staff_id: "",
        format: "calendar",
      },
      catalogs: {
        visit_types: ["Inspección", "Mantención", "Reunión", "Otro"],
        statuses: ["Programada", "En progreso", "Finalizada", "Cancelada"],
        review_statuses: ["OK", "No OK", "N/A"],
        responsibles: [],
        maintenance_assignees: [],
        dependencies: [],
      },
      visits: [],
      statusTotals: {},
      pagination: { current_page: 1, last_page: 1, total: 0 },
      form: emptyForm(),
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    isSuperAdmin() {
      return this.permissions.includes("__superadmin__");
    },
    isEditing() {
      return Boolean(this.form.id);
    },
    activeFiltersCount() {
      return [
        this.search,
        this.filters.from,
        this.filters.to,
        this.filters.dependency_id,
        this.filters.responsible_staff_id,
        this.filters.status,
        this.filters.visit_type,
      ].filter(Boolean).length;
    },
    responsibleOptions() {
      const catalog = this.catalogs.maintenance_assignees || [];
      const options = catalog.length
        ? catalog.map((person) => ({
            value: person.value || person.full_name,
            label: person.label || person.full_name,
          }))
        : (this.catalogs.responsibles || []).map((person) => ({
            value: person,
            label: person,
          }));

      if (this.form.responsible && !options.some((option) => option.value === this.form.responsible)) {
        return [{ value: this.form.responsible, label: `${this.form.responsible} · registro actual` }, ...options];
      }

      return options;
    },
    personFilterOptions() {
      return (this.catalogs.maintenance_assignees || []).map((person) => ({
        value: String(person.id),
        label: person.label || person.full_name,
        fullName: person.full_name,
      }));
    },
    exportResponsibleLabel() {
      if (!this.exportForm.responsible_staff_id) return "Todas las personas";

      return this.personFilterOptions.find(
        (person) => person.value === String(this.exportForm.responsible_staff_id)
      )?.label || "Persona seleccionada";
    },
    summaryCards() {
      const byStatus = (status) => Number(this.statusTotals[status] || 0);

      return [
        {
          label: "Total visitas",
          value: this.pagination.total,
          detail: this.viewMode === "calendar" ? "En el mes consultado" : "Resultado filtrado",
          icon: "mdi-calendar-search",
          tone: "blue",
        },
        {
          label: "Programadas",
          value: byStatus("Programada"),
          detail: "Visitas pendientes",
          icon: "mdi-calendar-clock",
          tone: "amber",
        },
        {
          label: "En progreso",
          value: byStatus("En progreso"),
          detail: "Revisión activa",
          icon: "mdi-progress-wrench",
          tone: "indigo",
        },
        {
          label: "Finalizadas",
          value: byStatus("Finalizada"),
          detail: "Con seguimiento cerrado",
          icon: "mdi-check-circle-outline",
          tone: "green",
        },
      ];
    },
    calendarTitle() {
      const [year, month] = this.calendarMonth.split("-").map(Number);
      const date = new Date(year, month - 1, 1, 12);
      const label = new Intl.DateTimeFormat("es-CL", { month: "long", year: "numeric" }).format(date);
      return label.charAt(0).toUpperCase() + label.slice(1);
    },
    calendarDays() {
      const [year, month] = this.calendarMonth.split("-").map(Number);
      const firstDay = new Date(year, month - 1, 1, 12);
      const firstWeekday = (firstDay.getDay() + 6) % 7;
      const gridStart = new Date(firstDay);
      gridStart.setDate(firstDay.getDate() - firstWeekday);

      return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);
        const iso = this.formatYMD(date);

        return {
          iso,
          day: date.getDate(),
          isCurrentMonth: date.getMonth() === month - 1,
          isToday: iso === localYMD(),
          visits: this.visitsForDate(iso),
        };
      });
    },
    mobileAgendaGroups() {
      const groups = new Map();
      this.visits
        .filter((visit) => this.viewMode !== "calendar" || String(visit.visit_date || "").slice(0, 7) === this.calendarMonth)
        .forEach((visit) => {
          const date = String(visit.visit_date || "").slice(0, 10);
          if (!groups.has(date)) groups.set(date, []);
          groups.get(date).push(visit);
        });

      return [...groups.entries()]
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([date, visits]) => ({
          date,
          day: String(Number(date.slice(8, 10))),
          weekday: this.formatWeekday(date),
          month: this.formatShortMonth(date),
          visits: visits.sort((left, right) =>
            String(left.visit_time || "").localeCompare(String(right.visit_time || ""))
          ),
        }));
    },
  },
  mounted() {
    try {
      this.debugModals = localStorage.getItem("CNSC_DEBUG_MODALS") === "1";
    } catch (e) {
      this.debugModals = false;
    }
    this.debugLog("mounted", { debugModals: this.debugModals });
    this.loadCatalogs();
    this.loadVisits();
  },
  methods: {
    debugLog(...args) {
      if (!this.debugModals) return;
      // eslint-disable-next-line no-console
      console.log("[CNSC][VISITS][modals]", ...args);
    },
    onModalEvent(modal, eventName) {
      this.debugLog("modal-event", modal, eventName);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/visits/catalogs");
      this.catalogs = { ...this.catalogs, ...response.data };
    },
    async loadVisits(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/visits", {
          params: {
            page,
            per_page: this.viewMode === "calendar" ? 500 : 15,
            search: this.search,
            ...this.requestFilters(),
          },
        });

        this.visits = response.data.data;
        this.statusTotals = response.data.status_totals || {};
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error cargando visitas";
      } finally {
        this.loading = false;
      }
    },
    requestFilters() {
      const filters = { ...this.filters };

      if (this.viewMode === "calendar") {
        const range = this.calendarRange();
        filters.from = filters.from || range.from;
        filters.to = filters.to || range.to;
      }

      return filters;
    },
    calendarRange() {
      const [year, month] = this.calendarMonth.split("-").map(Number);
      const from = this.formatYMD(new Date(year, month - 1, 1, 12));
      const to = this.formatYMD(new Date(year, month, 0, 12));
      return { from, to };
    },
    setViewMode(mode) {
      if (this.viewMode === mode) return;
      this.viewMode = mode;
      this.loadVisits(1);
    },
    changeCalendarMonth(offset) {
      const [year, month] = this.calendarMonth.split("-").map(Number);
      const next = new Date(year, month - 1 + offset, 1, 12);
      this.calendarMonth = `${next.getFullYear()}-${pad(next.getMonth() + 1)}`;
      this.loadVisits(1);
    },
    goCurrentMonth() {
      this.calendarMonth = localYM();
      this.loadVisits(1);
    },
    resetFilters() {
      this.search = "";
      this.filters = {
        from: "",
        to: "",
        dependency_id: "",
        responsible_staff_id: "",
        status: "",
        visit_type: "",
      };
      this.loadVisits(1);
    },
    monthRange(monthValue = this.calendarMonth) {
      const [year, month] = String(monthValue).split("-").map(Number);
      return {
        from: this.formatYMD(new Date(year, month - 1, 1, 12)),
        to: this.formatYMD(new Date(year, month, 0, 12)),
      };
    },
    exportFilters(overrides = {}) {
      const filters = { ...this.filters, ...overrides };
      const range = this.monthRange(overrides.month || this.calendarMonth);
      delete filters.month;

      filters.from = filters.from || range.from;
      filters.to = filters.to || range.to;

      return filters;
    },
    exportFilterLabels(filters = this.exportFilters()) {
      const labels = [];

      if (this.search) labels.push(`Búsqueda: ${this.search}`);
      if (filters.from) labels.push(`Desde: ${this.formatDMY(filters.from)}`);
      if (filters.to) labels.push(`Hasta: ${this.formatDMY(filters.to)}`);
      if (filters.dependency_id) labels.push(`Dependencia: ${this.selectedDependencyLabel(filters.dependency_id)}`);
      if (filters.responsible_staff_id) labels.push(`Responsable: ${this.personFilterLabel(filters.responsible_staff_id)}`);
      if (filters.visit_type) labels.push(`Tipo: ${filters.visit_type}`);
      if (filters.status) labels.push(`Estado: ${filters.status}`);

      return labels;
    },
    selectedDependencyLabel(id) {
      const dependency = (this.catalogs.dependencies || []).find((item) => Number(item.id) === Number(id));

      return dependency ? `${dependency.code} - ${dependency.name}` : "Seleccionada";
    },
    personFilterLabel(value) {
      const responsible = this.personFilterOptions.find((item) => item.value === String(value));

      return responsible?.label || value;
    },
    async fetchExportVisits(filters = this.exportFilters()) {
      const response = await axios.get("/api/maintenance/visits", {
        params: {
          page: 1,
          per_page: 1000,
          search: this.search,
          ...filters,
        },
      });

      return response.data?.data || [];
    },
    formatDateTime(value = new Date()) {
      const date = value instanceof Date ? value : new Date(value);
      if (Number.isNaN(date.getTime())) return "";

      return date.toLocaleString("es-CL", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    visitStats(visits) {
      const byStatus = (status) => visits.filter((visit) => visit.status === status).length;

      return [
        { label: "Total", value: visits.length, detail: "Visitas exportadas", fill: "#eef4ff", color: "#3152c9" },
        { label: "Programadas", value: byStatus("Programada"), detail: "Pendientes", fill: "#fffbeb", color: "#b45309" },
        { label: "En progreso", value: byStatus("En progreso"), detail: "Revisión activa", fill: "#eef2ff", color: "#4f46e5" },
        { label: "Finalizadas", value: byStatus("Finalizada"), detail: "Cerradas", fill: "#ecfdf5", color: "#047857" },
      ];
    },
    pdfFooter() {
      return (currentPage, pageCount) => ({
        columns: [
          { text: "CNSC Gestion - Mantencion", color: "#6b7280", fontSize: 8 },
          { text: `Pagina ${currentPage} de ${pageCount}`, alignment: "right", color: "#6b7280", fontSize: 8 },
        ],
        margin: [28, 0, 28, 0],
      });
    },
    pdfTableLayout() {
      return {
        hLineColor: () => "#dbe5f4",
        vLineColor: () => "#e5edf9",
        hLineWidth: (i, node) => (i === 0 || i === node.table.body.length ? 0 : 0.7),
        vLineWidth: () => 0,
        paddingLeft: () => 7,
        paddingRight: () => 7,
        paddingTop: () => 6,
        paddingBottom: () => 6,
      };
    },
    pdfCardTable(cards) {
      return {
        table: {
          widths: cards.map(() => "*"),
          body: [
            cards.map((card) => ({
              stack: [
                { text: card.label, color: "#667085", fontSize: 9, margin: [0, 0, 0, 4] },
                { text: String(card.value), color: card.color || "#243047", fontSize: 17, bold: true },
                { text: card.detail || "", color: "#7a849a", fontSize: 8, margin: [0, 4, 0, 0] },
              ],
              margin: [10, 8, 10, 8],
              fillColor: card.fill || "#f8fafc",
            })),
          ],
        },
        layout: "noBorders",
        margin: [0, 0, 0, 12],
      };
    },
    pdfStatusColor(status) {
      return {
        Programada: "#b45309",
        "En progreso": "#3152c9",
        Finalizada: "#047857",
        Cancelada: "#b91c1c",
      }[status] || "#475569";
    },
    pdfHeader(title, subtitle) {
      return {
        columns: [
          {
            stack: [
              { text: "MANTENCION", style: "eyebrow" },
              { text: title, style: "header" },
              { text: subtitle, style: "muted" },
            ],
          },
          { text: `Generado: ${this.formatDateTime()}`, style: "reportTag", alignment: "right" },
        ],
        margin: [0, 0, 0, 12],
      };
    },
    pdfStyles() {
      return {
        eyebrow: { fontSize: 8, bold: true, color: "#5b74df", characterSpacing: 0.8 },
        header: { fontSize: 20, bold: true, color: "#243047", margin: [0, 2, 0, 4] },
        muted: { fontSize: 9, color: "#667085" },
        reportTag: { fontSize: 9, color: "#475569", margin: [0, 10, 0, 0] },
        sectionTitle: { fontSize: 11, bold: true, color: "#243047", margin: [0, 0, 0, 6] },
        filterLine: { fontSize: 9, color: "#53607a" },
      };
    },
    async exportVisitsTablePdf(filters = this.exportFilters(), month = this.calendarMonth, responsibleLabel = "Todas las personas") {
      this.exporting = "table";
      this.error = null;

      try {
        const pdfMake = await getPdfMake();
        const visits = await this.fetchExportVisits(filters);
        const filterLabels = this.exportFilterLabels(filters);
        const header = ["Fecha", "Hora", "Dependencia", "Responsable", "Tipo", "Estado", "Notas"].map((text) => ({
          text,
          bold: true,
          color: "#ffffff",
          fillColor: "#334155",
          alignment: ["Fecha", "Hora", "Tipo", "Estado"].includes(text) ? "center" : "left",
        }));

        const rows = visits.length
          ? visits.map((visit, index) => [
              { text: this.formatDMY(visit.visit_date), alignment: "center" },
              { text: this.formatTime(visit.visit_time), alignment: "center" },
              { text: this.dependencyLabel(visit.dependency) },
              { text: visit.responsible || "-" },
              { text: visit.visit_type || "-", alignment: "center" },
              { text: visit.status || "-", alignment: "center", color: this.pdfStatusColor(visit.status), bold: true },
              { text: visit.notes || "-" },
            ].map((cell) => ({
              fillColor: index % 2 === 0 ? "#ffffff" : "#f8fafc",
              ...cell,
            })))
          : [[{ text: "No hay visitas para los filtros seleccionados.", colSpan: 7, alignment: "center", color: "#64748b" }, {}, {}, {}, {}, {}, {}]];

        const docDefinition = {
          pageOrientation: "landscape",
          pageMargins: [28, 32, 28, 42],
          footer: this.pdfFooter(),
          content: [
            this.pdfHeader("Calendario de visitas - tabla", `Periodo: ${this.formatDMY(filters.from)} al ${this.formatDMY(filters.to)}`),
            { canvas: [{ type: "line", x1: 0, y1: 0, x2: 786, y2: 0, lineWidth: 1, lineColor: "#dbe5f4" }], margin: [0, 0, 0, 12] },
            { text: "Filtros aplicados", style: "sectionTitle" },
            { text: filterLabels.join(" | ") || "Sin filtros aplicados", style: "filterLine", margin: [0, 0, 0, 12] },
            this.pdfCardTable(this.visitStats(visits)),
            {
              table: {
                headerRows: 1,
                widths: [64, 46, "*", 130, 74, 82, "*"],
                body: [header, ...rows],
              },
              layout: this.pdfTableLayout(),
            },
          ],
          styles: this.pdfStyles(),
          defaultStyle: { fontSize: 8.5, color: "#364154" },
        };

        pdfMake.createPdf(docDefinition).download(
          maintenanceCalendarPdfFilename(month, responsibleLabel).replace("calendario-visitas", "listado-visitas")
        );
        this.showExportModal = false;
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error generando PDF de visitas";
      } finally {
        this.exporting = "";
      }
    },
    async exportVisitsCalendarPdf(filters = this.exportFilters(), month = this.calendarMonth, responsibleLabel = "Todas las personas") {
      this.exporting = "calendar";
      this.error = null;

      try {
        const pdfMake = await getPdfMake();
        const visits = await this.fetchExportVisits(filters);
        const filterLabels = this.exportFilterLabels(filters);
        const calendarTitle = this.monthTitle(month);
        const docDefinition = buildMaintenanceVisitsCalendarPdf({
          visits,
          calendarMonth: month,
          calendarTitle,
          responsibleLabel,
          filterLabels,
        });

        pdfMake.createPdf(docDefinition).download(maintenanceCalendarPdfFilename(month, responsibleLabel));
        this.showExportModal = false;
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error generando PDF de calendario";
      } finally {
        this.exporting = "";
      }
    },
    openPdfExport() {
      this.error = null;
      this.exportForm = {
        month: this.calendarMonth,
        responsible_staff_id: this.filters.responsible_staff_id || "",
        format: "calendar",
      };
      this.showExportModal = true;
    },
    confirmPdfExport() {
      const range = this.monthRange(this.exportForm.month);
      const filters = this.exportFilters({
        ...range,
        responsible_staff_id: this.exportForm.responsible_staff_id || "",
        month: this.exportForm.month,
      });

      if (this.exportForm.format === "table") {
        return this.exportVisitsTablePdf(filters, this.exportForm.month, this.exportResponsibleLabel);
      }

      return this.exportVisitsCalendarPdf(filters, this.exportForm.month, this.exportResponsibleLabel);
    },
    openAutomaticPlanning() {
      this.error = null;
      this.success = null;
      this.showPlanningModal = true;
    },
    async onPlanningConfirmed(response) {
      this.success = response?.message || "Planificación de visitas creada correctamente.";
      this.viewMode = "calendar";
      await this.loadVisits(1);
    },
    openCreate() {
      this.debugLog("openCreate(click)");
      this.error = null;
      this.success = null;
      this.form = emptyForm();
      this.showModalVisit = true;
      this.debugLog("showModalVisit=true (create)");
    },
    editVisit(visit) {
      this.debugLog("editVisit(click)", { id: visit?.id });
      this.error = null;
      this.success = null;
      this.form = {
        ...emptyForm(),
        ...visit,
        maintenance_dependency_id: visit.maintenance_dependency_id || visit.dependency?.id || "",
        visit_date: String(visit.visit_date || "").slice(0, 10),
        visit_time: this.formatTimeInput(visit.visit_time),
      };
      this.showModalVisit = true;
      this.debugLog("showModalVisit=true (edit)");
    },
    async saveVisit() {
      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = { ...this.form };
        if (!payload.visit_time) payload.visit_time = null;

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/visits/${payload.id}`, payload)
          : await axios.post("/api/maintenance/visits", payload);

        this.success = response.data.message;
        this.showModalVisit = false;
        await this.loadVisits(this.pagination.current_page);
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      } finally {
        this.saving = false;
      }
    },
    async deleteVisit(visit) {
      if (!confirm("¿Eliminar la visita?")) return;

      try {
        const response = await axios.delete(`/api/maintenance/visits/${visit.id}`);
        this.success = response.data.message;
        await this.loadVisits(this.pagination.current_page);
      } catch (error) {
        this.error = error.response?.data?.message || error.message;
      }
    },
    goChecklist(visit) {
      this.$router.push(`/maintenance/visits/${visit.id}/checklist`);
    },
    dependencyLabel(dep) {
      if (!dep) return "-";

      const detail = this.dependencyContext(dep);
      return `${this.dependencyCode(dep)} · ${this.dependencyName(dep)}${detail ? ` · ${detail}` : ""}`;
    },
    dependencyName(dep) {
      return String(dep?.name || "").trim() || "Dependencia sin nombre";
    },
    dependencyCode(dep) {
      return String(dep?.code || "").trim() || "Sin código";
    },
    dependencyContext(dep) {
      if (!dep) return "";

      const seen = new Set([dep.code, dep.name]
        .map((value) => String(value || "").trim().toLocaleLowerCase("es-CL"))
        .filter(Boolean));

      return [dep.distribution, dep.sector, dep.zone, dep.usage]
        .map((value) => String(value || "").trim())
        .filter((value) => {
          const normalized = value.toLocaleLowerCase("es-CL");
          if (!value || seen.has(normalized)) return false;
          seen.add(normalized);
          return true;
        })
        .join(" · ");
    },
    calendarVisitTitle(visit) {
      return [
        `${this.formatTime(visit.visit_time)} · ${this.dependencyCode(visit.dependency)}`,
        this.dependencyName(visit.dependency),
        this.dependencyContext(visit.dependency),
        `Responsable: ${visit.responsible || "Sin responsable"}`,
      ].filter(Boolean).join("\n");
    },
    monthTitle(monthValue = this.calendarMonth) {
      const [year, month] = String(monthValue).split("-").map(Number);
      const label = new Intl.DateTimeFormat("es-CL", { month: "long", year: "numeric" })
        .format(new Date(year, month - 1, 1, 12));
      return label.charAt(0).toUpperCase() + label.slice(1);
    },
    formatWeekday(value) {
      const [year, month, day] = String(value).slice(0, 10).split("-").map(Number);
      const label = new Intl.DateTimeFormat("es-CL", { weekday: "short" })
        .format(new Date(year, month - 1, day, 12))
        .replace(".", "");
      return label.charAt(0).toUpperCase() + label.slice(1);
    },
    formatShortMonth(value) {
      const [year, month, day] = String(value).slice(0, 10).split("-").map(Number);
      return new Intl.DateTimeFormat("es-CL", { month: "short" })
        .format(new Date(year, month - 1, day, 12))
        .replace(".", "")
        .toUpperCase();
    },
    formatYMD(date) {
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },
    formatDMY(value) {
      if (!value) return "-";
      const [y, m, d] = String(value).slice(0, 10).split("-");
      if (!y || !m || !d) return String(value);
      return `${d}-${m}-${y}`;
    },
    formatTime(value) {
      if (!value) return "-";

      const stringValue = String(value);
      if (/^\d{2}:\d{2}/.test(stringValue)) return stringValue.slice(0, 5);

      const time = stringValue.includes("T") ? stringValue.split("T")[1] : stringValue.split(" ")[1];
      return time ? time.slice(0, 5) : stringValue.slice(0, 5);
    },
    formatTimeInput(value) {
      const time = this.formatTime(value);
      return time === "-" ? "" : time;
    },
    visitsForDate(date) {
      return this.visitsForDateFrom(this.visits, date);
    },
    visitsForDateFrom(visits, date) {
      return visits
        .filter((visit) => String(visit.visit_date || "").slice(0, 10) === date)
        .sort((a, b) => this.formatTime(a.visit_time).localeCompare(this.formatTime(b.visit_time)));
    },
    statusClass(status) {
      return {
        Programada: "visit-pill--planned",
        "En progreso": "visit-pill--active",
        Finalizada: "visit-pill--done",
        Cancelada: "visit-pill--cancelled",
      }[status] || "visit-pill--neutral";
    },
    typeClass(type) {
      return {
        Inspección: "visit-type--inspection",
        Mantención: "visit-type--maintenance",
        Reunión: "visit-type--meeting",
        Otro: "visit-type--other",
      }[type] || "visit-type--other";
    },
  },
  watch: {
    showModalVisit(value) {
      this.debugLog("watch showModalVisit", value);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="maintenance-visits-page">
      <div class="visits-header">
        <div>
          <span class="visits-eyebrow">Mantención</span>
          <h4>Planificación de visitas</h4>
          <p>Agenda revisiones por dependencia, responsable operativo y estado de ejecución.</p>
        </div>
        <div class="visits-header-actions">
          <div class="visits-view-toggle" aria-label="Modo de vista">
            <button type="button" :class="{ active: viewMode === 'table' }" @click="setViewMode('table')">
              <i class="mdi mdi-table"></i>
              Tabla
            </button>
            <button type="button" :class="{ active: viewMode === 'calendar' }" @click="setViewMode('calendar')">
              <i class="mdi mdi-calendar-month-outline"></i>
              Calendario
            </button>
          </div>
          <button class="visit-export-button" type="button" :disabled="Boolean(exporting)" @click="openPdfExport">
            <span class="visit-export-button__icon"><i class="mdi mdi-file-pdf-box"></i></span>
            <span>
              <strong>Exportar agenda</strong>
              <small>PDF por persona</small>
            </span>
            <i class="mdi mdi-chevron-right visit-export-button__arrow"></i>
          </button>
          <button v-if="isSuperAdmin" class="visit-auto-button" type="button" @click="openAutomaticPlanning">
            <i class="mdi mdi-auto-fix"></i>
            Planificar automáticamente
          </button>
          <button class="visit-primary-button" type="button" @click="openCreate">
            <i class="mdi mdi-plus"></i>
            Nueva visita
          </button>
        </div>
      </div>

      <div class="visits-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="visits-summary-card"
          :class="`visits-summary-card--${card.tone}`"
        >
          <div class="visits-summary-icon">
            <i :class="`mdi ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="visits-panel visits-filters-panel">
        <div class="visits-panel-head">
          <div>
            <span class="visits-eyebrow">Filtros</span>
            <h5>Consulta de visitas</h5>
          </div>
          <div class="visits-filter-head-actions">
            <span class="visits-filter-count" :class="{ active: activeFiltersCount > 0 }">
              {{ activeFiltersCount }} filtros
            </span>
            <button class="visits-filter-toggle" type="button" @click="mobileFiltersOpen = !mobileFiltersOpen">
              <i :class="`mdi ${mobileFiltersOpen ? 'mdi-chevron-up' : 'mdi-tune-variant'}`"></i>
              {{ mobileFiltersOpen ? "Ocultar" : "Ajustar" }}
            </button>
          </div>
        </div>

        <div class="visits-filters" :class="{ 'mobile-open': mobileFiltersOpen }">
          <label class="visit-field visit-field--search">
            <span>Búsqueda</span>
            <input v-model="search" type="search" placeholder="Código, dependencia, sector..." @keyup.enter="loadVisits()" />
          </label>
          <label class="visit-field">
            <span>Desde</span>
            <input v-model="filters.from" type="date" />
          </label>
          <label class="visit-field">
            <span>Hasta</span>
            <input v-model="filters.to" type="date" />
          </label>
          <label class="visit-field visit-field--dependency">
            <span>Dependencia</span>
            <select v-model="filters.dependency_id">
              <option value="">Todas</option>
              <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">
                {{ dep.code }} · {{ dep.name }}
              </option>
            </select>
          </label>
          <label class="visit-field visit-field--responsible">
            <span>Persona responsable</span>
            <select v-model="filters.responsible_staff_id">
              <option value="">Todas las personas</option>
              <option v-for="person in personFilterOptions" :key="person.value" :value="person.value">
                {{ person.label }}
              </option>
            </select>
          </label>
          <label class="visit-field">
            <span>Tipo</span>
            <select v-model="filters.visit_type">
              <option value="">Todos</option>
              <option v-for="type in catalogs.visit_types" :key="type" :value="type">{{ type }}</option>
            </select>
          </label>
          <label class="visit-field">
            <span>Estado</span>
            <select v-model="filters.status">
              <option value="">Todos</option>
              <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>
          <div class="visits-filter-actions">
            <button class="visit-primary-button" type="button" @click="loadVisits(1)">
              <i class="mdi mdi-filter-outline"></i>
              Filtrar
            </button>
            <button class="visit-secondary-button" type="button" @click="resetFilters">Limpiar</button>
          </div>
        </div>
      </section>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

      <section class="visits-panel">
        <div class="visits-panel-head">
          <div>
            <span class="visits-eyebrow">{{ viewMode === "calendar" ? "Calendario" : "Listado" }}</span>
            <h5>{{ viewMode === "calendar" ? calendarTitle : "Visitas registradas" }}</h5>
          </div>
          <div v-if="viewMode === 'calendar'" class="visits-calendar-controls">
            <button type="button" class="visit-secondary-button" @click="changeCalendarMonth(-1)">
              <i class="mdi mdi-chevron-left"></i>
            </button>
            <button type="button" class="visit-secondary-button" @click="goCurrentMonth">Mes actual</button>
            <button type="button" class="visit-secondary-button" @click="changeCalendarMonth(1)">
              <i class="mdi mdi-chevron-right"></i>
            </button>
          </div>
        </div>

        <div v-if="viewMode === 'table'" class="visits-table-wrap visits-desktop-view">
          <table class="visits-table">
            <colgroup>
              <col class="visit-col-date" />
              <col class="visit-col-time" />
              <col class="visit-col-dependency" />
              <col class="visit-col-responsible" />
              <col class="visit-col-type" />
              <col class="visit-col-status" />
              <col class="visit-col-actions" />
            </colgroup>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Dependencia</th>
                <th>Responsable</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7">
                  <div class="visits-empty-state">Cargando visitas...</div>
                </td>
              </tr>
              <tr v-else-if="visits.length === 0">
                <td colspan="7">
                  <div class="visits-empty-state">No hay visitas registradas.</div>
                </td>
              </tr>
              <tr v-for="visit in visits" :key="visit.id">
                <td class="visit-date-cell">{{ formatDMY(visit.visit_date) }}</td>
                <td class="visit-time-cell">{{ formatTime(visit.visit_time) }}</td>
                <td>
                  <div class="visit-dependency">{{ dependencyLabel(visit.dependency) }}</div>
                  <small v-if="visit.notes" class="visit-notes">{{ visit.notes }}</small>
                </td>
                <td>
                  <div class="visit-responsible">{{ visit.responsible }}</div>
                </td>
                <td>
                  <span class="visit-type-chip" :class="typeClass(visit.visit_type)">{{ visit.visit_type }}</span>
                </td>
                <td>
                  <span class="visit-pill" :class="statusClass(visit.status)">{{ visit.status }}</span>
                </td>
                <td class="visit-actions-cell">
                  <div class="visit-actions">
                    <button type="button" title="Checklist" data-cnsc-action-label="Checklist" @click="goChecklist(visit)">
                      <i class="mdi mdi-clipboard-check-outline"></i>
                    </button>
                    <button type="button" title="Editar" @click="editVisit(visit)">
                      <i class="mdi mdi-pencil-outline"></i>
                    </button>
                    <button type="button" title="Eliminar" @click="deleteVisit(visit)">
                      <i class="mdi mdi-trash-can-outline"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="visits-calendar visits-desktop-view">
          <div class="visits-calendar-weekdays">
            <span>Lun</span>
            <span>Mar</span>
            <span>Mié</span>
            <span>Jue</span>
            <span>Vie</span>
            <span>Sáb</span>
            <span>Dom</span>
          </div>
          <div class="visits-calendar-grid" :class="{ 'is-loading': loading }">
            <div
              v-for="day in calendarDays"
              :key="day.iso"
              class="visits-calendar-day"
              :class="{ muted: !day.isCurrentMonth, today: day.isToday }"
            >
              <div class="visits-calendar-day-head">
                <span>{{ day.day }}</span>
                <small v-if="day.visits.length">{{ day.visits.length }}</small>
              </div>
              <div class="visits-calendar-events">
                <button
                  v-for="visit in day.visits"
                  :key="visit.id"
                  type="button"
                  class="visits-calendar-event"
                  :class="statusClass(visit.status)"
                  :title="calendarVisitTitle(visit)"
                  @click="editVisit(visit)"
                >
                  <span class="visits-calendar-event__head">
                    <strong>{{ formatTime(visit.visit_time) }}</strong>
                    <em>{{ dependencyCode(visit.dependency) }}</em>
                  </span>
                  <span class="visits-calendar-event__name">{{ dependencyName(visit.dependency) }}</span>
                  <small v-if="dependencyContext(visit.dependency)" class="visits-calendar-event__location">
                    <i class="mdi mdi-map-marker-outline"></i>
                    <span>{{ dependencyContext(visit.dependency) }}</span>
                  </small>
                  <small class="visits-calendar-event__responsible">
                    <i class="mdi mdi-account-outline"></i>
                    <span>{{ visit.responsible || "Sin responsable" }}</span>
                  </small>
                </button>
              </div>
            </div>
          </div>
          <div v-if="!loading && visits.length === 0" class="visits-empty-state visits-empty-state--calendar">
            No hay visitas para el mes o filtros seleccionados.
          </div>
        </div>

        <div class="visits-mobile-agenda">
          <div v-if="loading" class="visits-mobile-loading">
            <span></span>
            <p>Cargando agenda...</p>
          </div>
          <template v-else-if="mobileAgendaGroups.length">
            <section v-for="group in mobileAgendaGroups" :key="group.date" class="visits-mobile-day-group">
              <header class="visits-mobile-date">
                <strong>{{ group.day }}</strong>
                <span>{{ group.weekday }}<small>{{ group.month }}</small></span>
                <em>{{ group.visits.length }} {{ group.visits.length === 1 ? "visita" : "visitas" }}</em>
              </header>
              <article v-for="visit in group.visits" :key="visit.id" class="visits-mobile-card">
                <div class="visits-mobile-card__timeline" :class="statusClass(visit.status)">
                  <span></span>
                  <strong>{{ formatTime(visit.visit_time) }}</strong>
                </div>
                <div class="visits-mobile-card__content">
                  <div class="visits-mobile-card__topline">
                    <span class="visit-type-chip" :class="typeClass(visit.visit_type)">{{ visit.visit_type }}</span>
                    <span class="visit-pill" :class="statusClass(visit.status)">{{ visit.status }}</span>
                  </div>
                  <h6>{{ visit.dependency?.name || "Dependencia sin nombre" }}</h6>
                  <p><i class="mdi mdi-account-outline"></i>{{ visit.responsible || "Sin responsable" }}</p>
                  <small class="visits-mobile-card__code">
                    <i class="mdi mdi-pound"></i>{{ dependencyCode(visit.dependency) }}
                  </small>
                  <small v-if="dependencyContext(visit.dependency)" class="visits-mobile-card__location">
                    <i class="mdi mdi-map-marker-outline"></i>{{ dependencyContext(visit.dependency) }}
                  </small>
                  <div class="visits-mobile-card__actions">
                    <button type="button" @click="goChecklist(visit)"><i class="mdi mdi-clipboard-check-outline"></i>Checklist</button>
                    <button type="button" @click="editVisit(visit)"><i class="mdi mdi-pencil-outline"></i>Editar</button>
                    <button type="button" class="danger" aria-label="Eliminar visita" @click="deleteVisit(visit)"><i class="mdi mdi-trash-can-outline"></i></button>
                  </div>
                </div>
              </article>
            </section>
          </template>
          <div v-else class="visits-mobile-empty">
            <i class="mdi mdi-calendar-blank-outline"></i>
            <strong>Sin visitas en este período</strong>
            <span>Ajusta los filtros o programa una nueva visita.</span>
          </div>
        </div>

        <div v-if="viewMode === 'table'" class="visits-pagination">
          <span>Total: {{ pagination.total }}</span>
          <div class="visits-pagination-actions">
            <button type="button" :disabled="pagination.current_page <= 1" @click="loadVisits(pagination.current_page - 1)">
              Anterior
            </button>
            <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadVisits(pagination.current_page + 1)">
              Siguiente
            </button>
          </div>
        </div>
      </section>
    </div>

    <BModal
      v-model="showModalVisit"
      :title="isEditing ? 'Editar visita' : 'Nueva visita'"
      title-class="visit-modal-title"
      header-class="visit-modal-header"
      body-class="visit-modal-body p-0"
      modal-class="visit-modal"
      size="lg"
      hide-footer
      centered
      scrollable
      teleport-to="body"
      lazy
      no-fade
      @show="onModalEvent('visit', 'show')"
      @shown="onModalEvent('visit', 'shown')"
      @hide="onModalEvent('visit', 'hide')"
      @hidden="onModalEvent('visit', 'hidden')"
    >
      <form class="visit-form" @submit.prevent="saveVisit">
        <div class="visit-modal-scroll">
          <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

          <section class="visit-form-section">
            <div class="visit-form-section-head">
              <i class="mdi mdi-map-marker-outline"></i>
              <div>
                <h6>Destino</h6>
                <span>Dependencia y responsable de mantención</span>
              </div>
            </div>

            <div class="visit-form-grid">
              <label class="visit-field visit-field--wide">
                <span>Dependencia</span>
                <select v-model="form.maintenance_dependency_id" required>
                  <option value="">Selecciona...</option>
                  <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">
                    {{ dep.code }} · {{ dep.name }}
                  </option>
                </select>
              </label>

              <label class="visit-field visit-field--wide">
                <span>Responsable</span>
                <select v-model="form.responsible" required>
                  <option value="">Selecciona funcionario habilitado...</option>
                  <option v-for="person in responsibleOptions" :key="person.value" :value="person.value">
                    {{ person.label }}
                  </option>
                </select>
              </label>
            </div>
          </section>

          <section class="visit-form-section">
            <div class="visit-form-section-head">
              <i class="mdi mdi-calendar-clock"></i>
              <div>
                <h6>Programación</h6>
                <span>Fecha, hora, tipo y estado</span>
              </div>
            </div>

            <div class="visit-form-grid visit-form-grid--two">
              <label class="visit-field">
                <span>Fecha</span>
                <input v-model="form.visit_date" type="date" required />
              </label>
              <label class="visit-field">
                <span>Hora</span>
                <input v-model="form.visit_time" type="time" />
              </label>
              <label class="visit-field">
                <span>Tipo</span>
                <select v-model="form.visit_type" required>
                  <option v-for="type in catalogs.visit_types" :key="type" :value="type">{{ type }}</option>
                </select>
              </label>
              <label class="visit-field">
                <span>Estado</span>
                <select v-model="form.status" required>
                  <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
                </select>
              </label>
            </div>
          </section>

          <section class="visit-form-section">
            <div class="visit-form-section-head">
              <i class="mdi mdi-note-text-outline"></i>
              <div>
                <h6>Observaciones</h6>
                <span>Contexto de la revisión o acuerdo operativo</span>
              </div>
            </div>

            <label class="visit-field">
              <span>Notas</span>
              <textarea v-model="form.notes" rows="4" placeholder="Indicaciones, alcance de la visita o coordinación previa..."></textarea>
            </label>
          </section>
        </div>

        <div class="visit-modal-footer">
          <button class="visit-secondary-button" type="button" @click="showModalVisit = false">Cancelar</button>
          <button class="visit-primary-button" type="submit" :disabled="saving">
            {{ saving ? "Guardando..." : isEditing ? "Actualizar visita" : "Crear visita" }}
          </button>
        </div>
      </form>
    </BModal>

    <BModal
      v-model="showExportModal"
      modal-class="visit-export-modal"
      dialog-class="visit-export-dialog"
      body-class="p-0"
      hide-header
      hide-footer
      centered
      teleport-to="body"
      lazy
      no-fade
    >
      <div class="visit-export-shell">
        <header class="visit-export-hero">
          <div class="visit-export-hero__icon"><i class="mdi mdi-file-pdf-box"></i></div>
          <div>
            <span>Agenda operativa</span>
            <h4>Exportar calendario de visitas</h4>
            <p>Genera un PDF listo para compartir, filtrado por mes y persona.</p>
          </div>
          <button type="button" aria-label="Cerrar exportación" @click="showExportModal = false">
            <i class="mdi mdi-close"></i>
          </button>
        </header>

        <div class="visit-export-body">
          <div class="visit-export-grid">
            <label class="visit-field">
              <span>Mes de la agenda</span>
              <input v-model="exportForm.month" type="month" required />
            </label>
            <label class="visit-field">
              <span>Persona responsable</span>
              <select v-model="exportForm.responsible_staff_id">
                <option value="">Todas las personas</option>
                <option v-for="person in personFilterOptions" :key="person.value" :value="person.value">
                  {{ person.label }}
                </option>
              </select>
            </label>
          </div>

          <section class="visit-export-person">
            <span class="visit-export-avatar"><i class="mdi mdi-account-outline"></i></span>
            <div>
              <small>Agenda seleccionada</small>
              <strong>{{ exportResponsibleLabel }}</strong>
              <p>{{ monthTitle(exportForm.month) }}</p>
            </div>
            <i class="mdi mdi-check-decagram"></i>
          </section>

          <fieldset class="visit-export-formats">
            <legend>Formato del documento</legend>
            <label :class="{ active: exportForm.format === 'calendar' }">
              <input v-model="exportForm.format" type="radio" value="calendar" />
              <span><i class="mdi mdi-calendar-month-outline"></i></span>
              <div><strong>Calendario visual</strong><small>Distribución mensual lista para imprimir</small></div>
              <i class="mdi mdi-radiobox-marked"></i>
            </label>
            <label :class="{ active: exportForm.format === 'table' }">
              <input v-model="exportForm.format" type="radio" value="table" />
              <span><i class="mdi mdi-format-list-bulleted"></i></span>
              <div><strong>Listado detallado</strong><small>Una fila por visita con observaciones</small></div>
              <i class="mdi mdi-radiobox-marked"></i>
            </label>
          </fieldset>

          <div class="visit-export-context">
            <i class="mdi mdi-filter-check-outline"></i>
            <p>
              Se conservarán los filtros activos de búsqueda, dependencia, tipo y estado.
              El mes y la persona se tomarán de esta ventana.
            </p>
          </div>
        </div>

        <footer class="visit-export-footer">
          <button class="visit-secondary-button" type="button" :disabled="Boolean(exporting)" @click="showExportModal = false">
            Cancelar
          </button>
          <button class="visit-export-confirm" type="button" :disabled="Boolean(exporting) || !exportForm.month" @click="confirmPdfExport">
            <span v-if="exporting" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <i v-else class="mdi mdi-download"></i>
            {{ exporting ? "Preparando PDF..." : "Descargar PDF" }}
          </button>
        </footer>
      </div>
    </BModal>

    <MaintenanceVisitPlanningModal
      v-if="isSuperAdmin"
      v-model="showPlanningModal"
      :catalogs="catalogs"
      @confirmed="onPlanningConfirmed"
    />
  </Layout>
</template>

<style scoped>
.maintenance-visits-page {
  padding: 4px 0 24px;
}

.visits-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 26px;
  border: 1px solid #dbe6fb;
  border-radius: 18px;
  margin-bottom: 22px;
  background:
    radial-gradient(circle at 92% 0%, rgba(123, 151, 241, 0.17), transparent 34%),
    linear-gradient(135deg, #ffffff 0%, #f7f9ff 100%);
  box-shadow: 0 18px 50px rgba(54, 76, 132, 0.08);
}

.visits-eyebrow {
  display: block;
  color: #6d7690;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0;
  line-height: 1.2;
  text-transform: uppercase;
}

.visits-header h4,
.visits-panel-head h5 {
  margin: 4px 0 0;
  color: #303848;
  font-weight: 700;
  letter-spacing: 0;
}

.visits-header h4 {
  font-size: clamp(23px, 2vw, 30px);
  line-height: 1.15;
}

.visits-header p {
  margin: 8px 0 0;
  color: #717b94;
  font-size: 15px;
  font-weight: 400;
}

.visits-header-actions,
.visits-filter-actions,
.visits-pagination-actions,
.visits-calendar-controls,
.visit-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.visits-header-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
  max-width: 860px;
}

.visit-primary-button,
.visit-secondary-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 18px;
  border-radius: 11px;
  border: 1px solid transparent;
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.visit-export-button {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) 18px;
  align-items: center;
  gap: 9px;
  min-height: 52px;
  padding: 6px 10px 6px 7px;
  border: 1px solid #f4b9b9;
  border-radius: 13px;
  color: #7f1d1d;
  text-align: left;
  background: linear-gradient(145deg, #fffafa, #fff1f1);
  box-shadow: 0 8px 20px rgba(153, 27, 27, 0.08);
  transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}

.visit-export-button:hover {
  color: #7f1d1d;
  border-color: #ee9b9b;
  transform: translateY(-1px);
  box-shadow: 0 12px 26px rgba(153, 27, 27, 0.12);
}

.visit-export-button:disabled {
  opacity: 0.62;
}

.visit-export-button__icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  color: #fff;
  background: linear-gradient(145deg, #c73030, #9f1d1d);
  font-size: 21px;
}

.visit-export-button > span:nth-child(2) {
  display: grid;
  gap: 2px;
}

.visit-export-button strong {
  font-size: 12px;
  font-weight: 750;
}

.visit-export-button small {
  color: #a34a4a;
  font-size: 10px;
  font-weight: 600;
}

.visit-export-button__arrow {
  color: #c26969;
}

.visit-primary-button {
  color: #fff;
  background: #5b74df;
  border-color: #5b74df;
}

.visit-primary-button:hover {
  color: #fff;
  background: #4f66ca;
  border-color: #4f66ca;
}

.visit-primary-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.visit-auto-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 17px;
  border: 1px solid #4160cd;
  border-radius: 8px;
  color: #fff;
  background: linear-gradient(135deg, #334fae, #617be2);
  box-shadow: 0 5px 14px rgba(55, 79, 174, 0.18);
  font-size: 13px;
  font-weight: 650;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.visit-auto-button:hover {
  color: #fff;
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(55, 79, 174, 0.24);
}

.visit-secondary-button {
  color: #566079;
  background: #fff;
  border-color: #b9c3d8;
}

.visit-secondary-button:hover {
  color: #384154;
  background: #f5f7fb;
  border-color: #8d99b2;
}

.visit-secondary-button:disabled {
  opacity: 0.62;
  cursor: not-allowed;
}

.visit-secondary-button--red {
  color: #b91c1c;
  background: #fff8f8;
  border-color: #fecaca;
}

.visit-secondary-button--red:hover {
  color: #991b1b;
  background: #fef2f2;
  border-color: #fca5a5;
}

.visits-view-toggle {
  display: inline-flex;
  padding: 4px;
  border-radius: 10px;
  border: 1px solid #dce5f4;
  background: #f8fbff;
}

.visits-view-toggle button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 12px;
  border: 0;
  border-radius: 8px;
  color: #68728b;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
}

.visits-view-toggle button.active {
  color: #3152c9;
  background: #eef4ff;
  box-shadow: inset 0 0 0 1px #c7d7fe;
}

.visits-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.visits-summary-card {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr);
  gap: 14px;
  align-items: center;
  min-height: 116px;
  padding: 20px;
  border: 1px solid #dfebfb;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: 0 18px 42px rgba(63, 84, 120, 0.06);
}

.visits-summary-icon {
  width: 46px;
  height: 46px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 24px;
}

.visits-summary-card span,
.visits-summary-card small {
  display: block;
  color: #6d7690;
  font-size: 13px;
  font-weight: 500;
}

.visits-summary-card strong {
  display: block;
  margin: 4px 0;
  color: #303848;
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
}

.visits-summary-card--blue .visits-summary-icon {
  color: #3152c9;
  background: #eef4ff;
}

.visits-summary-card--amber .visits-summary-icon {
  color: #b45309;
  background: #fffbeb;
}

.visits-summary-card--indigo .visits-summary-icon {
  color: #4f46e5;
  background: #eef2ff;
}

.visits-summary-card--green .visits-summary-icon {
  color: #047857;
  background: #ecfdf5;
}

.visits-panel {
  padding: 22px;
  border: 1px solid #dfebfb;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.84);
  box-shadow: 0 18px 44px rgba(63, 84, 120, 0.06);
}

.visits-panel + .visits-panel,
.visits-filters-panel {
  margin-bottom: 18px;
}

.visits-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.visits-filter-count {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  color: #647089;
  background: #f4f7fb;
  border: 1px solid #dce5f4;
  font-size: 13px;
  font-weight: 600;
}

.visits-filter-count.active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.visits-filter-head-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.visits-filter-toggle {
  display: none;
  min-height: 34px;
  padding: 0 11px;
  border: 1px solid #cbd7eb;
  border-radius: 9px;
  color: #45516a;
  background: #fff;
  font-size: 12px;
  font-weight: 700;
}

.visits-filters {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 12px;
  align-items: end;
}

.visit-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  grid-column: span 2;
  min-width: 0;
  margin: 0;
}

.visit-field--search,
.visit-field--dependency {
  grid-column: span 3;
}

.visit-field span {
  color: #4c5568;
  font-size: 13px;
  line-height: 1.2;
  font-weight: 600;
}

.visit-field input,
.visit-field select,
.visit-field textarea {
  width: 100%;
  min-height: 44px;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  background: #fff;
  color: #303848;
  padding: 0 14px;
  font-size: 14px;
  font-weight: 400;
  outline: none;
}

.visit-field textarea {
  padding-top: 12px;
  resize: vertical;
}

.visit-field input:focus,
.visit-field select:focus,
.visit-field textarea:focus {
  border-color: #9db1f8;
  box-shadow: 0 0 0 3px rgba(91, 116, 223, 0.12);
}

.visit-field--wide {
  grid-column: 1 / -1;
}

.visits-filter-actions {
  grid-column: span 2;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.visits-filter-actions .visit-primary-button,
.visits-filter-actions .visit-secondary-button {
  min-width: 116px;
}

.visits-table-wrap {
  overflow-x: auto;
  border-top: 1px solid #e2eaf8;
}

.visits-table {
  width: 100%;
  min-width: 1180px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.visits-table th,
.visits-table td {
  padding: 18px 14px;
  vertical-align: middle;
  border-bottom: 1px solid #e5edf9;
}

.visits-table th {
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
  text-align: center;
  border-bottom-color: #dce7f7;
}

.visits-table td {
  color: #364154;
  font-size: 14px;
  font-weight: 400;
}

.visit-col-date {
  width: 116px;
}

.visit-col-time {
  width: 86px;
}

.visit-col-dependency {
  width: 320px;
}

.visit-col-responsible {
  width: 230px;
}

.visit-col-type,
.visit-col-status {
  width: 150px;
}

.visit-col-actions {
  width: 168px;
}

.visit-date-cell,
.visit-time-cell,
.visit-actions-cell {
  text-align: center;
  white-space: nowrap;
}

.visit-dependency,
.visit-responsible {
  color: #303848;
  line-height: 1.35;
  font-weight: 600;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.visit-notes {
  display: block;
  margin-top: 4px;
  color: #778199;
  font-size: 12px;
  line-height: 1.35;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

.visit-type-chip,
.visit-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  font-size: 12px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}

.visit-type--inspection {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.visit-type--maintenance {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.visit-type--meeting {
  color: #7c3aed;
  background: #f5f3ff;
  border-color: #ddd6fe;
}

.visit-type--other {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.visit-pill--planned {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.visit-pill--active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.visit-pill--done {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.visit-pill--cancelled {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.visit-pill--neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.visit-actions {
  justify-content: center;
}

.visit-actions button {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  border-radius: 12px;
  border: 1px solid #cfd8ea;
  color: #647089;
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.visit-actions .cnsc-action-btn + .cnsc-action-btn {
  margin-left: 0 !important;
}

.visits-empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 94px;
  color: #7a849a;
  font-size: 14px;
  font-weight: 500;
}

.visits-empty-state--calendar {
  min-height: 72px;
  border: 1px dashed #dce5f4;
  border-radius: 8px;
  margin-top: 12px;
}

.visits-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 16px;
  color: #717b94;
  font-size: 13px;
  font-weight: 400;
}

.visits-pagination-actions button {
  min-height: 36px;
  border-radius: 8px;
  border: 1px solid #cfd8ea;
  background: #fff;
  color: #566079;
  padding: 0 12px;
  font-weight: 600;
}

.visits-pagination-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.visits-pagination-actions span {
  color: #303848;
  font-weight: 600;
}

.visits-calendar-weekdays,
.visits-calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
}

.visits-calendar-weekdays {
  gap: 8px;
  margin-bottom: 8px;
}

.visits-calendar-weekdays span {
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
}

.visits-calendar-grid {
  overflow: hidden;
  border: 1px solid #dce7f7;
  border-radius: 8px;
  background: #fff;
}

.visits-calendar-grid.is-loading {
  opacity: 0.62;
}

.visits-calendar-day {
  min-height: 156px;
  padding: 10px;
  border-right: 1px solid #e5edf9;
  border-bottom: 1px solid #e5edf9;
  background: #fff;
}

.visits-calendar-day:nth-child(7n) {
  border-right: 0;
}

.visits-calendar-day:nth-last-child(-n + 7) {
  border-bottom: 0;
}

.visits-calendar-day.muted {
  background: #f8fafc;
}

.visits-calendar-day.today {
  box-shadow: inset 0 0 0 2px #9db1f8;
}

.visits-calendar-day-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.visits-calendar-day-head span {
  color: #303848;
  font-size: 14px;
  font-weight: 700;
}

.visits-calendar-day.muted .visits-calendar-day-head span {
  color: #9aa4b8;
}

.visits-calendar-day-head small {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 24px;
  border-radius: 999px;
  color: #3152c9;
  background: #eef4ff;
  border: 1px solid #c7d7fe;
  font-size: 11px;
  font-weight: 700;
}

.visits-calendar-events {
  display: grid;
  gap: 6px;
}

.visits-calendar-event {
  width: 100%;
  min-width: 0;
  min-height: 104px;
  padding: 8px 9px;
  border-radius: 10px;
  border: 1px solid #dce5f4;
  background: #f8fafc;
  text-align: left;
  transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.visits-calendar-event:hover {
  z-index: 1;
  box-shadow: 0 7px 18px rgba(31, 46, 86, 0.12);
  transform: translateY(-1px);
}

.visits-calendar-event__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  min-width: 0;
}

.visits-calendar-event__head strong,
.visits-calendar-event__head em,
.visits-calendar-event__name,
.visits-calendar-event__location span,
.visits-calendar-event__responsible span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.visits-calendar-event__head strong {
  flex: 0 0 auto;
  font-size: 12px;
  font-weight: 800;
}

.visits-calendar-event__head em {
  padding: 2px 6px;
  border-radius: 999px;
  color: #5f6b82;
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(128, 142, 170, 0.24);
  font-size: 9px;
  font-style: normal;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.visits-calendar-event__name {
  display: -webkit-box;
  margin-top: 5px;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  white-space: normal;
  color: #303848;
  font-size: 12px;
  font-weight: 750;
  line-height: 1.2;
}

.visits-calendar-event__location,
.visits-calendar-event__responsible {
  display: grid;
  grid-template-columns: 12px minmax(0, 1fr);
  align-items: center;
  gap: 3px;
  margin-top: 4px;
  color: #6b758b;
  font-size: 9.5px;
  font-weight: 550;
  line-height: 1.2;
}

.visits-calendar-event__location i,
.visits-calendar-event__responsible i {
  color: #8390a6;
  font-size: 11px;
}

.visits-calendar-event__responsible {
  margin-top: 3px;
  color: #7b8598;
}

.visits-calendar-event__location {
  align-items: start;
}

.visits-calendar-event__location span {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  white-space: normal;
}

.visits-calendar-event.visit-pill--planned {
  border-color: #fcd34d;
  background: #fffbeb;
}

.visits-calendar-event.visit-pill--active {
  border-color: #c7d7fe;
  background: #eef4ff;
}

.visits-calendar-event.visit-pill--done {
  border-color: #a7f3d0;
  background: #ecfdf5;
}

.visits-calendar-event.visit-pill--cancelled {
  border-color: #fecaca;
  background: #fef2f2;
}

.visits-mobile-agenda {
  display: none;
}

:deep(.visit-export-dialog) {
  width: min(720px, calc(100vw - 32px));
  max-width: 720px;
}

:deep(.visit-export-dialog .modal-content) {
  overflow: hidden;
  border: 1px solid #d9e2f2;
  border-radius: 20px;
  box-shadow: 0 32px 90px rgba(20, 35, 78, 0.24);
}

.visit-export-shell {
  color: #263044;
  background: #f6f8fc;
}

.visit-export-hero {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr) 34px;
  align-items: center;
  gap: 14px;
  padding: 22px 24px;
  color: #fff;
  background:
    radial-gradient(circle at 82% -20%, rgba(255, 255, 255, 0.22), transparent 36%),
    linear-gradient(135deg, #1f3785, #3f63d0);
}

.visit-export-hero__icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border: 1px solid rgba(255, 255, 255, 0.24);
  border-radius: 15px;
  background: rgba(255, 255, 255, 0.14);
  font-size: 28px;
}

.visit-export-hero span {
  color: #cbd6ff;
  font-size: 10px;
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.visit-export-hero h4 {
  margin: 3px 0 4px;
  color: #fff;
  font-size: 20px;
  font-weight: 750;
}

.visit-export-hero p {
  margin: 0;
  color: #e2e8ff;
  font-size: 12px;
}

.visit-export-hero > button {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 10px;
  color: #fff;
  background: rgba(255, 255, 255, 0.12);
  font-size: 20px;
}

.visit-export-body {
  display: grid;
  gap: 15px;
  padding: 20px 24px;
}

.visit-export-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.45fr);
  gap: 13px;
}

.visit-export-grid .visit-field {
  grid-column: auto;
}

.visit-export-person {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) 24px;
  align-items: center;
  gap: 12px;
  padding: 13px 15px;
  border: 1px solid #cfdbf7;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8faff, #eef3ff);
}

.visit-export-avatar {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 13px;
  color: #3157c8;
  background: #fff;
  box-shadow: 0 6px 16px rgba(49, 87, 200, 0.12);
  font-size: 23px;
}

.visit-export-person div {
  display: grid;
  min-width: 0;
  gap: 2px;
}

.visit-export-person small {
  color: #74809a;
  font-size: 9px;
  font-weight: 750;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.visit-export-person strong {
  overflow: hidden;
  color: #27344d;
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.visit-export-person p {
  margin: 0;
  color: #68758e;
  font-size: 11px;
}

.visit-export-person > i {
  color: #15906a;
  font-size: 22px;
}

.visit-export-formats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 0;
  border: 0;
  margin: 0;
}

.visit-export-formats legend {
  grid-column: 1 / -1;
  margin: 0 0 -1px;
  color: #4c586e;
  font-size: 12px;
  font-weight: 700;
}

.visit-export-formats label {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) 18px;
  align-items: center;
  gap: 10px;
  min-height: 70px;
  padding: 11px;
  border: 1px solid #d9e1ee;
  border-radius: 13px;
  background: #fff;
  cursor: pointer;
}

.visit-export-formats label.active {
  border-color: #8da6f2;
  background: #f5f7ff;
  box-shadow: 0 0 0 3px rgba(49, 87, 200, 0.08);
}

.visit-export-formats input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.visit-export-formats label > span {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 11px;
  color: #3157c8;
  background: #edf2ff;
  font-size: 20px;
}

.visit-export-formats label > div {
  display: grid;
  gap: 3px;
}

.visit-export-formats strong {
  color: #303b50;
  font-size: 12px;
}

.visit-export-formats small {
  color: #77839a;
  font-size: 10px;
  line-height: 1.3;
}

.visit-export-formats label > i {
  color: #c0c9d8;
}

.visit-export-formats label.active > i {
  color: #3157c8;
}

.visit-export-context {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  padding: 10px 12px;
  border-radius: 11px;
  color: #5f6c83;
  background: #edf1f7;
}

.visit-export-context i {
  color: #526fc8;
  font-size: 18px;
}

.visit-export-context p {
  margin: 1px 0 0;
  font-size: 10.5px;
  line-height: 1.45;
}

.visit-export-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 14px 24px;
  border-top: 1px solid #dde4ef;
  background: #fff;
}

.visit-export-confirm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 43px;
  padding: 0 18px;
  border: 1px solid #284ebc;
  border-radius: 11px;
  color: #fff;
  background: linear-gradient(135deg, #294bb2, #4a6dde);
  box-shadow: 0 8px 20px rgba(41, 75, 178, 0.2);
  font-size: 12px;
  font-weight: 750;
}

.visit-export-confirm:disabled {
  opacity: 0.62;
}

:deep(.visit-modal .modal-dialog) {
  max-width: min(860px, calc(100vw - 32px));
}

:deep(.visit-modal .modal-content) {
  border-radius: 8px;
  border: 1px solid #dce5f4;
  overflow: hidden;
}

:deep(.visit-modal-header) {
  min-height: 68px;
  padding: 18px 24px;
  border-bottom: 1px solid #e2eaf8;
  background: #fff;
}

:deep(.visit-modal-title) {
  color: #303848;
  font-size: 20px;
  font-weight: 700;
}

.visit-form {
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 110px);
  background: #f8fafc;
}

.visit-modal-scroll {
  overflow-y: auto;
  padding: 18px 22px;
}

.visit-form-section {
  padding: 18px;
  border: 1px solid #e0e8f6;
  border-radius: 8px;
  background: #fff;
}

.visit-form-section + .visit-form-section {
  margin-top: 14px;
}

.visit-form-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.visit-form-section-head i {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #3152c9;
  background: #eef4ff;
  font-size: 20px;
}

.visit-form-section-head h6 {
  margin: 0;
  color: #303848;
  font-size: 15px;
  font-weight: 600;
}

.visit-form-section-head span {
  color: #778199;
  font-size: 12px;
  font-weight: 400;
}

.visit-form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

.visit-form-grid--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.visit-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid #e2eaf8;
  background: #fff;
}

@media (max-width: 1400px) {
  .visits-filters {
    grid-template-columns: repeat(12, minmax(0, 1fr));
  }

  .visit-field,
  .visit-field--search,
  .visit-field--dependency,
  .visits-filter-actions {
    grid-column: span 4;
  }

  .visits-filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 1200px) {
  .visits-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .visits-calendar-grid,
  .visits-calendar-weekdays {
    min-width: 980px;
  }

  .visits-calendar {
    overflow-x: auto;
  }
}

@media (max-width: 768px) {
  .maintenance-visits-page {
    padding-top: 0;
  }

  .visits-header,
  .visits-panel-head,
  .visits-pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .visits-header {
    gap: 17px;
    padding: 20px 17px;
    border-radius: 16px;
  }

  .visits-header p {
    font-size: 13px;
    line-height: 1.45;
  }

  .visits-filter-actions,
  .visits-calendar-controls,
  .visits-pagination-actions {
    flex-wrap: wrap;
  }

  .visits-filters,
  .visit-form-grid--two {
    grid-template-columns: 1fr;
  }

  .visits-header-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
    max-width: none;
  }

  .visits-view-toggle,
  .visit-export-button {
    grid-column: 1 / -1;
    width: 100%;
  }

  .visits-view-toggle {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .visits-view-toggle button {
    justify-content: center;
    min-height: 40px;
  }

  .visit-auto-button,
  .visits-header-actions > .visit-primary-button {
    width: 100%;
    min-height: 46px;
    padding: 0 10px;
    font-size: 11px;
  }

  .visits-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
  }

  .visits-summary-card {
    grid-template-columns: 37px minmax(0, 1fr);
    gap: 9px;
    min-height: 91px;
    padding: 13px 11px;
    border-radius: 13px;
  }

  .visits-summary-icon {
    width: 37px;
    height: 37px;
    font-size: 19px;
  }

  .visits-summary-card span,
  .visits-summary-card small {
    font-size: 10px;
  }

  .visits-summary-card strong {
    font-size: 22px;
  }

  .visit-field,
  .visit-field--search,
  .visit-field--dependency,
  .visits-filter-actions {
    grid-column: 1 / -1;
  }

  .visits-filter-actions .visit-primary-button,
  .visits-filter-actions .visit-secondary-button {
    width: 100%;
  }

  .visits-panel {
    padding: 15px;
    border-radius: 14px;
  }

  .visits-filters-panel .visits-panel-head {
    flex-direction: row;
    align-items: center;
  }

  .visits-filter-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
  }

  .visits-filters-panel .visits-filters {
    display: none;
  }

  .visits-filters-panel .visits-filters.mobile-open {
    display: grid;
    padding-top: 3px;
  }

  .visits-desktop-view {
    display: none;
  }

  .visits-mobile-agenda {
    display: grid;
    gap: 14px;
  }

  .visits-mobile-day-group {
    display: grid;
    gap: 9px;
  }

  .visits-mobile-date {
    display: grid;
    grid-template-columns: 43px minmax(0, 1fr) auto;
    align-items: center;
    gap: 9px;
    padding: 0 2px;
  }

  .visits-mobile-date > strong {
    color: #273247;
    font-size: 30px;
    line-height: 1;
    text-align: center;
  }

  .visits-mobile-date > span {
    display: grid;
    color: #33415b;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.15;
  }

  .visits-mobile-date small {
    color: #7c879b;
    font-size: 9px;
    letter-spacing: 0.07em;
  }

  .visits-mobile-date em {
    padding: 5px 8px;
    border-radius: 999px;
    color: #4560bd;
    background: #eef3ff;
    font-size: 9px;
    font-style: normal;
    font-weight: 750;
  }

  .visits-mobile-card {
    display: grid;
    grid-template-columns: 57px minmax(0, 1fr);
    overflow: hidden;
    border: 1px solid #dde5f2;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(46, 65, 105, 0.07);
  }

  .visits-mobile-card__timeline {
    position: relative;
    display: flex;
    align-items: center;
    flex-direction: column;
    padding: 15px 6px;
    color: #526079;
    background: #f6f8fc;
  }

  .visits-mobile-card__timeline span {
    width: 10px;
    height: 10px;
    margin: 2px 0 8px;
    border: 3px solid #fff;
    border-radius: 999px;
    background: #7c879b;
    box-shadow: 0 0 0 2px #c7d0df;
  }

  .visits-mobile-card__timeline::after {
    position: absolute;
    top: 39px;
    bottom: 0;
    left: 50%;
    width: 1px;
    content: "";
    background: #dfe5ef;
  }

  .visits-mobile-card__timeline strong {
    z-index: 1;
    font-size: 11px;
  }

  .visits-mobile-card__timeline.visit-pill--planned span { background: #d08a1e; box-shadow: 0 0 0 2px #f2d59f; }
  .visits-mobile-card__timeline.visit-pill--active span { background: #4563cc; box-shadow: 0 0 0 2px #bfcdf8; }
  .visits-mobile-card__timeline.visit-pill--done span { background: #15906a; box-shadow: 0 0 0 2px #b4e6d6; }
  .visits-mobile-card__timeline.visit-pill--cancelled span { background: #c23e3e; box-shadow: 0 0 0 2px #f4baba; }

  .visits-mobile-card__content {
    min-width: 0;
    padding: 13px 13px 11px;
  }

  .visits-mobile-card__topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
  }

  .visits-mobile-card .visit-type-chip,
  .visits-mobile-card .visit-pill {
    min-height: 23px;
    padding: 0 8px;
    font-size: 8px;
  }

  .visits-mobile-card h6 {
    margin: 10px 0 7px;
    color: #2d374c;
    font-size: 14px;
    font-weight: 750;
    line-height: 1.25;
  }

  .visits-mobile-card p,
  .visits-mobile-card small {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 0;
    color: #637087;
    font-size: 10px;
  }

  .visits-mobile-card small {
    margin-top: 4px;
    color: #8490a4;
  }

  .visits-mobile-card__code {
    width: fit-content;
    padding: 3px 7px;
    border: 1px solid #dce4f1;
    border-radius: 999px;
    color: #536179 !important;
    background: #f7f9fc;
    font-size: 9px !important;
    font-weight: 750;
  }

  .visits-mobile-card__location {
    align-items: flex-start !important;
    margin-top: 7px !important;
    color: #66748c !important;
    font-size: 10px !important;
    line-height: 1.4;
  }

  .visits-mobile-card__location i {
    margin-top: 1px;
  }

  .visits-mobile-card__actions {
    display: grid;
    grid-template-columns: 1fr 1fr 36px;
    gap: 6px;
    margin-top: 11px;
    padding-top: 10px;
    border-top: 1px solid #edf0f5;
  }

  .visits-mobile-card__actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-height: 36px;
    padding: 0 8px;
    border: 1px solid #d2dbea;
    border-radius: 9px;
    color: #4f5d75;
    background: #fff;
    font-size: 9px;
    font-weight: 700;
  }

  .visits-mobile-card__actions button.danger {
    padding: 0;
    color: #b13b3b;
    background: #fff6f6;
    border-color: #f0cccc;
  }

  .visits-mobile-loading,
  .visits-mobile-empty {
    display: grid;
    place-items: center;
    min-height: 180px;
    padding: 24px;
    border: 1px dashed #d2dcea;
    border-radius: 14px;
    color: #7c8799;
    text-align: center;
    background: #fafbfe;
  }

  .visits-mobile-loading span {
    width: 28px;
    height: 28px;
    border: 3px solid #dfe5f2;
    border-top-color: #4e69cf;
    border-radius: 999px;
    animation: visits-spin 0.8s linear infinite;
  }

  .visits-mobile-loading p {
    margin: -36px 0 0;
    font-size: 11px;
  }

  .visits-mobile-empty {
    gap: 5px;
  }

  .visits-mobile-empty i {
    color: #6e84d3;
    font-size: 34px;
  }

  .visits-mobile-empty strong {
    color: #4a566d;
    font-size: 13px;
  }

  .visits-mobile-empty span {
    font-size: 10px;
  }

  .visit-modal-scroll,
  .visit-modal-footer {
    padding-left: 16px;
    padding-right: 16px;
  }

  :deep(.visit-export-dialog) {
    width: 100%;
    max-width: none;
    height: 100%;
    margin: 0;
  }

  :deep(.visit-export-dialog .modal-content) {
    height: 100%;
    border: 0;
    border-radius: 0;
  }

  .visit-export-shell {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  .visit-export-hero {
    grid-template-columns: 43px minmax(0, 1fr) 32px;
    gap: 10px;
    padding: 17px 15px;
  }

  .visit-export-hero__icon {
    width: 43px;
    height: 43px;
    border-radius: 12px;
    font-size: 23px;
  }

  .visit-export-hero h4 {
    font-size: 16px;
  }

  .visit-export-hero p {
    font-size: 10px;
  }

  .visit-export-body {
    flex: 1 1 auto;
    align-content: start;
    overflow-y: auto;
    padding: 16px 14px;
  }

  .visit-export-grid,
  .visit-export-formats {
    grid-template-columns: 1fr;
  }

  .visit-export-formats legend {
    grid-column: auto;
  }

  .visit-export-footer {
    display: grid;
    grid-template-columns: 0.75fr 1.25fr;
    flex: 0 0 auto;
    padding: 11px 14px;
  }

  .visit-export-footer button {
    width: 100%;
    padding: 0 10px;
  }
}

@keyframes visits-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 420px) {
  .visits-header-actions {
    grid-template-columns: 1fr;
  }

  .visits-header-actions > * {
    grid-column: 1 / -1;
  }

  .visits-summary-card {
    grid-template-columns: 1fr;
  }

  .visits-summary-icon {
    display: none;
  }
}
</style>
