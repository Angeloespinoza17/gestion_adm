<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import pdfMake from "pdfmake/build/pdfmake";
import pdfFonts from "pdfmake/build/vfs_fonts";

pdfMake.vfs = pdfFonts?.pdfMake?.vfs || pdfFonts;

const pad = (value) => String(value).padStart(2, "0");
const localYMD = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const localYM = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

const emptyForm = () => {
  const now = new Date();

  return {
    id: null,
    maintenance_dependency_id: "",
    item_type: "dependency",
    inventory_item_id: "",
    technical_area_id: "",
    component_name: "",
    planned_year: now.getFullYear(),
    planned_month: now.getMonth() + 1,
    category: "General",
    responsible: "",
    frequency: "Anual",
    status: "Programada",
    title: "",
    description: "",
    scheduled_date: "",
    completed_date: "",
    last_maintenance_date: "",
    alert_days: 30,
    alert_enabled: true,
    notes: "",
  };
};

export default {
  components: { Layout },
  data() {
    const year = new Date().getFullYear();

    return {
      debugModals: false,
      loading: false,
      saving: false,
      exporting: false,
      error: null,
      success: null,
      showModalPlan: false,
      viewMode: "table",
      calendarMonth: localYM(),
      search: "",
      filters: {
        dependency_id: "",
        planned_year: year,
        planned_month: "",
        item_type: "",
        category: "",
        responsible: "",
        frequency: "",
        status: "",
      },
      catalogs: {
        frequencies: ["Diaria", "Semanal", "Mensual", "Semestral", "Anual"],
        statuses: ["Programada", "En ejecución", "Cumplida", "Vencida", "Cancelada"],
        categories: ["General"],
        item_types: [],
        component_suggestions: [],
        responsibles: [],
        maintenance_assignees: [],
        dependencies: [],
        technical_areas: [],
        inventory_items: [],
      },
      plans: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      form: emptyForm(),
      monthOptions: [
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
      ],
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    activeFiltersCount() {
      return [
        this.search,
        this.filters.dependency_id,
        this.viewMode === "upcoming" ? "" : this.filters.planned_year,
        this.viewMode === "upcoming" ? "" : this.filters.planned_month,
        this.filters.item_type,
        this.filters.category,
        this.filters.responsible,
        this.filters.frequency,
        this.filters.status,
      ].filter(Boolean).length;
    },
    responsibleOptions() {
      const catalog = this.catalogs.maintenance_assignees || [];

      if (catalog.length) {
        return catalog.map((person) => ({
          value: person.value || person.full_name,
          label: person.label || person.full_name,
        }));
      }

      return (this.catalogs.responsibles || []).map((person) => ({
        value: person,
        label: person,
      }));
    },
    itemTypeOptions() {
      return (this.catalogs.item_types || []).length
        ? this.catalogs.item_types
        : [
            { value: "dependency", label: "Dependencia completa" },
            { value: "dependency_component", label: "Elemento de dependencia" },
            { value: "inventory_item", label: "Bien de inventario" },
            { value: "technical_area", label: "Área técnica" },
          ];
    },
    filteredInventoryItems() {
      if (!this.form.maintenance_dependency_id) return this.catalogs.inventory_items || [];

      return (this.catalogs.inventory_items || []).filter((item) => {
        return !item.dependency_id || Number(item.dependency_id) === Number(this.form.maintenance_dependency_id);
      });
    },
    filteredTechnicalAreas() {
      if (!this.form.maintenance_dependency_id) return this.catalogs.technical_areas || [];

      return (this.catalogs.technical_areas || []).filter((area) => {
        return !area.parent_dependency_id || Number(area.parent_dependency_id) === Number(this.form.maintenance_dependency_id);
      });
    },
    summaryCards() {
      const byAlert = (state) => this.plans.filter((plan) => plan.alert_state === state).length;
      const open = this.plans.filter((plan) => !["Cumplida", "Cancelada"].includes(plan.status)).length;

      return [
        {
          label: "Programadas",
          value: this.pagination.total,
          detail: "Resultado filtrado",
          icon: "mdi-calendar-check-outline",
          tone: "blue",
        },
        {
          label: "Próximas",
          value: byAlert("upcoming"),
          detail: "Dentro de alerta",
          icon: "mdi-calendar-clock",
          tone: "amber",
        },
        {
          label: "Vencidas",
          value: byAlert("overdue"),
          detail: "Requieren gestión",
          icon: "mdi-calendar-alert",
          tone: "red",
        },
        {
          label: "Abiertas",
          value: open,
          detail: "No cerradas",
          icon: "mdi-progress-wrench",
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
          plans: this.plansForDate(iso),
        };
      });
    },
  },
  mounted() {
    try {
      this.debugModals = localStorage.getItem("CNSC_DEBUG_MODALS") === "1";
    } catch (e) {
      this.debugModals = false;
    }
    this.loadCatalogs();
    this.loadPlans();
  },
  methods: {
    debugLog(...args) {
      if (!this.debugModals) return;
      // eslint-disable-next-line no-console
      console.log("[CNSC][ANNUAL-PLAN][modals]", ...args);
    },
    onModalEvent(modal, eventName) {
      this.debugLog("modal-event", modal, eventName);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/annual-plans/catalogs");
      this.catalogs = { ...this.catalogs, ...response.data };
    },
    async loadPlans(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/annual-plans", {
          params: {
            page,
            per_page: this.viewMode === "table" ? 15 : 500,
            search: this.search,
            ...this.requestFilters(),
          },
        });

        this.plans = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
        if (this.viewMode !== "calendar") {
          this.$nextTick(() => this.resetTableScroll());
        }
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error cargando plan anual";
      } finally {
        this.loading = false;
      }
    },
    requestFilters() {
      const filters = { ...this.filters };

      if (this.viewMode === "calendar") {
        const [year, month] = this.calendarMonth.split("-").map(Number);
        filters.planned_year = year;
        filters.planned_month = month;
      }

      if (this.viewMode === "upcoming") {
        filters.planned_year = "";
        filters.planned_month = "";
        filters.due_scope = "upcoming";
      }

      return filters;
    },
    setViewMode(mode) {
      if (this.viewMode === mode) return;
      this.viewMode = mode;
      this.loadPlans(1);
    },
    changeCalendarMonth(offset) {
      const [year, month] = this.calendarMonth.split("-").map(Number);
      const next = new Date(year, month - 1 + offset, 1, 12);
      this.calendarMonth = `${next.getFullYear()}-${pad(next.getMonth() + 1)}`;
      this.loadPlans(1);
    },
    goCurrentMonth() {
      this.calendarMonth = localYM();
      this.loadPlans(1);
    },
    resetFilters() {
      this.search = "";
      this.filters = {
        dependency_id: "",
        planned_year: new Date().getFullYear(),
        planned_month: "",
        item_type: "",
        category: "",
        responsible: "",
        frequency: "",
        status: "",
      };
      this.loadPlans(1);
    },
    openCreate() {
      this.error = null;
      this.success = null;
      this.form = emptyForm();
      this.showModalPlan = true;
    },
    editPlan(plan) {
      this.error = null;
      this.success = null;
      this.form = {
        ...emptyForm(),
        ...plan,
        maintenance_dependency_id: plan.maintenance_dependency_id || plan.dependency?.id || "",
        inventory_item_id: plan.inventory_item_id || "",
        technical_area_id: plan.technical_area_id || "",
        component_name: plan.component_name || "",
        scheduled_date: plan.scheduled_date ? String(plan.scheduled_date).slice(0, 10) : "",
        completed_date: plan.completed_date ? String(plan.completed_date).slice(0, 10) : "",
        last_maintenance_date: plan.last_maintenance_date ? String(plan.last_maintenance_date).slice(0, 10) : "",
        alert_days: plan.alert_days || 30,
        alert_enabled: plan.alert_enabled !== false,
      };
      this.showModalPlan = true;
    },
    async savePlan() {
      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = { ...this.form };
        if (!payload.inventory_item_id) payload.inventory_item_id = null;
        if (!payload.technical_area_id) payload.technical_area_id = null;
        if (!payload.component_name) payload.component_name = null;
        if (!payload.scheduled_date) payload.scheduled_date = null;
        if (!payload.completed_date) payload.completed_date = null;
        if (!payload.last_maintenance_date) payload.last_maintenance_date = null;
        if (!payload.description) payload.description = null;
        if (!payload.notes) payload.notes = null;
        payload.alert_enabled = Boolean(payload.alert_enabled);

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/annual-plans/${payload.id}`, payload)
          : await axios.post("/api/maintenance/annual-plans", payload);

        this.success = response.data.message;
        this.showModalPlan = false;
        await this.loadPlans(this.pagination.current_page);
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      } finally {
        this.saving = false;
      }
    },
    async deletePlan(plan) {
      if (!confirm(`¿Eliminar la mantención "${plan.title}"?`)) return;

      this.error = null;
      this.success = null;

      try {
        const response = await axios.delete(`/api/maintenance/annual-plans/${plan.id}`);
        this.success = response.data.message;
        await this.loadPlans(this.pagination.current_page);
      } catch (error) {
        this.error = error.response?.data?.message || error.message;
      }
    },
    onScheduledDateChange() {
      if (!this.form.scheduled_date) return;

      const [year, month] = this.form.scheduled_date.split("-").map(Number);
      if (year && month) {
        this.form.planned_year = year;
        this.form.planned_month = month;
      }
    },
    onItemTypeChange() {
      this.form.inventory_item_id = "";
      this.form.technical_area_id = "";
      this.form.component_name = "";
      if (!this.form.title) this.form.title = this.itemTypeLabel(this.form.item_type);
    },
    syncInventoryItemSelection() {
      const item = (this.catalogs.inventory_items || []).find((row) => Number(row.id) === Number(this.form.inventory_item_id));
      if (!item) return;

      if (item.dependency_id) this.form.maintenance_dependency_id = item.dependency_id;
      if (!this.form.title) this.form.title = `Mantención ${item.name}`;
      if (String(item.name || "").toLowerCase().includes("extintor")) {
        this.form.category = "Extintores";
        this.form.frequency = "Anual";
        this.form.alert_days = 45;
      }
    },
    syncTechnicalAreaSelection() {
      const area = (this.catalogs.technical_areas || []).find((row) => Number(row.id) === Number(this.form.technical_area_id));
      if (!area) return;

      if (area.parent_dependency_id) this.form.maintenance_dependency_id = area.parent_dependency_id;
      if (!this.form.title) this.form.title = `Mantención ${area.name}`;
      this.form.category = this.form.category === "General" ? "Infraestructura" : this.form.category;
    },
    syncComponentName() {
      if (!this.form.component_name || this.form.title) return;
      this.form.title = `Mantención ${this.form.component_name.toLowerCase()}`;
      this.form.category = "Elementos constructivos";
    },
    formatYMD(date) {
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    },
    formatDMY(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).slice(0, 10).split("-");
      if (!year || !month || !day) return String(value);
      return `${day}-${month}-${year}`;
    },
    monthLabel(month) {
      return this.monthOptions.find((m) => Number(m.value) === Number(month))?.label || month;
    },
    dependencyLabel(dep) {
      if (!dep) return "-";
      return `${dep.code} · ${dep.name}`;
    },
    inventoryItemLabel(item) {
      if (!item) return "";
      const dependency = item.dependency ? ` · ${item.dependency.code}` : "";
      return `${item.code} · ${item.name}${dependency}`;
    },
    technicalAreaLabel(area) {
      if (!area) return "";
      const parent = area.parent_dependency ? ` · ${area.parent_dependency.code}` : "";
      return `${area.code} · ${area.name}${parent}`;
    },
    itemTypeLabel(type) {
      return this.itemTypeOptions.find((item) => item.value === type)?.label || "Dependencia";
    },
    compactItemTypeLabel(type) {
      return {
        dependency: "Dependencia",
        dependency_component: "Elemento",
        inventory_item: "Bien inventariado",
        technical_area: "Área técnica",
      }[type] || this.itemTypeLabel(type);
    },
    resetTableScroll() {
      const tableWrap = this.$el?.querySelector(".annual-table-wrap");
      if (tableWrap) tableWrap.scrollLeft = 0;
    },
    planDate(plan) {
      if (plan.scheduled_date) return String(plan.scheduled_date).slice(0, 10);
      return `${plan.planned_year}-${pad(plan.planned_month || 1)}-01`;
    },
    plansForDate(date) {
      return this.plans
        .filter((plan) => this.planDate(plan) === date)
        .sort((a, b) => (a.item_label || "").localeCompare(b.item_label || ""));
    },
    statusClass(status) {
      return {
        Programada: "annual-pill--planned",
        "En ejecución": "annual-pill--active",
        Cumplida: "annual-pill--done",
        Vencida: "annual-pill--overdue",
        Cancelada: "annual-pill--cancelled",
      }[status] || "annual-pill--neutral";
    },
    alertClass(state) {
      return {
        upcoming: "annual-alert--upcoming",
        overdue: "annual-alert--overdue",
        scheduled: "annual-alert--scheduled",
        closed: "annual-alert--closed",
        "no-date": "annual-alert--nodate",
      }[state] || "annual-alert--scheduled";
    },
    typeClass(type) {
      return {
        dependency: "annual-type--dependency",
        dependency_component: "annual-type--component",
        inventory_item: "annual-type--inventory",
        technical_area: "annual-type--technical",
      }[type] || "annual-type--dependency";
    },
    filterLabels(filters = this.requestFilters()) {
      const labels = [];
      if (this.search) labels.push(`Búsqueda: ${this.search}`);
      if (filters.planned_year) labels.push(`Año: ${filters.planned_year}`);
      if (filters.planned_month) labels.push(`Mes: ${this.monthLabel(filters.planned_month)}`);
      if (filters.dependency_id) labels.push(`Dependencia: ${this.selectedDependencyLabel(filters.dependency_id)}`);
      if (filters.item_type) labels.push(`Tipo: ${this.itemTypeLabel(filters.item_type)}`);
      if (filters.category) labels.push(`Categoría: ${filters.category}`);
      if (filters.responsible) labels.push(`Responsable: ${this.responsibleLabel(filters.responsible)}`);
      if (filters.frequency) labels.push(`Frecuencia: ${filters.frequency}`);
      if (filters.status) labels.push(`Estado: ${filters.status}`);
      if (filters.due_scope === "upcoming") labels.push("Fechas próximas");
      return labels;
    },
    selectedDependencyLabel(id) {
      const dependency = (this.catalogs.dependencies || []).find((item) => Number(item.id) === Number(id));
      return dependency ? `${dependency.code} - ${dependency.name}` : "Seleccionada";
    },
    responsibleLabel(value) {
      const option = this.responsibleOptions.find((item) => item.value === value);
      return option?.label || value;
    },
    async fetchExportPlans() {
      const response = await axios.get("/api/maintenance/annual-plans", {
        params: {
          page: 1,
          per_page: 1000,
          search: this.search,
          ...this.requestFilters(),
        },
      });
      return response.data?.data || [];
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
    pdfStyles() {
      return {
        eyebrow: { fontSize: 8, bold: true, color: "#5b74df", characterSpacing: 0.8 },
        header: { fontSize: 20, bold: true, color: "#243047", margin: [0, 2, 0, 4] },
        muted: { fontSize: 9, color: "#667085" },
        sectionTitle: { fontSize: 11, bold: true, color: "#243047", margin: [0, 0, 0, 6] },
        filterLine: { fontSize: 9, color: "#53607a" },
      };
    },
    pdfTableLayout() {
      return {
        hLineColor: () => "#dbe5f4",
        vLineColor: () => "#dbe5f4",
        hLineWidth: (i, node) => (i === 0 || i === node.table.body.length ? 0 : 0.7),
        vLineWidth: () => 0,
        paddingLeft: () => 6,
        paddingRight: () => 6,
        paddingTop: () => 6,
        paddingBottom: () => 6,
      };
    },
    pdfAlertColor(state) {
      return {
        upcoming: "#b45309",
        overdue: "#b91c1c",
        scheduled: "#3152c9",
        closed: "#047857",
        "no-date": "#64748b",
      }[state] || "#3152c9";
    },
    async exportPdf() {
      this.exporting = true;
      this.error = null;

      try {
        const plans = await this.fetchExportPlans();
        const title = this.viewMode === "calendar"
          ? `Plan anual - calendario ${this.calendarTitle}`
          : this.viewMode === "upcoming"
            ? "Plan anual - fechas proximas"
            : "Plan anual de mantencion";

        const content = [
          {
            columns: [
              {
                stack: [
                  { text: "MANTENCION", style: "eyebrow" },
                  { text: title, style: "header" },
                  { text: `Generado: ${new Date().toLocaleString("es-CL")}`, style: "muted" },
                ],
              },
              { text: `${plans.length} registros`, alignment: "right", color: "#475569", fontSize: 10, margin: [0, 12, 0, 0] },
            ],
            margin: [0, 0, 0, 12],
          },
          { canvas: [{ type: "line", x1: 0, y1: 0, x2: 786, y2: 0, lineWidth: 1, lineColor: "#dbe5f4" }], margin: [0, 0, 0, 12] },
          { text: this.filterLabels().join(" | ") || "Sin filtros aplicados", style: "filterLine", margin: [0, 0, 0, 12] },
        ];

        if (this.viewMode === "calendar") {
          content.push(this.pdfCalendar(plans));
        } else {
          content.push(this.pdfPlanTable(plans));
        }

        pdfMake.createPdf({
          pageOrientation: "landscape",
          pageMargins: [28, 32, 28, 42],
          footer: this.pdfFooter(),
          content,
          styles: this.pdfStyles(),
          defaultStyle: { fontSize: 8.5, color: "#364154" },
        }).download(`plan-mantencion-${this.viewMode}-${localYMD()}.pdf`);
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error generando PDF";
      } finally {
        this.exporting = false;
      }
    },
    pdfPlanTable(plans) {
      const header = ["Fecha", "Ítem", "Tipo", "Dependencia", "Responsable", "Frecuencia", "Alerta", "Estado"].map((text) => ({
        text,
        bold: true,
        color: "#ffffff",
        fillColor: "#334155",
        alignment: ["Fecha", "Tipo", "Frecuencia", "Alerta", "Estado"].includes(text) ? "center" : "left",
      }));

      const rows = plans.length
        ? plans.map((plan, index) => [
            { text: this.formatDMY(this.planDate(plan)), alignment: "center" },
            { text: `${plan.title}\n${plan.item_label || ""}` },
            { text: plan.item_type_label || this.itemTypeLabel(plan.item_type), alignment: "center" },
            { text: this.dependencyLabel(plan.dependency) },
            { text: plan.responsible || "-" },
            { text: plan.frequency || "-", alignment: "center" },
            { text: plan.alert_label || "-", alignment: "center", color: this.pdfAlertColor(plan.alert_state), bold: true },
            { text: plan.status || "-", alignment: "center" },
          ].map((cell) => ({ fillColor: index % 2 === 0 ? "#ffffff" : "#f8fafc", ...cell })))
        : [[{ text: "No hay registros para exportar.", colSpan: 8, alignment: "center" }, {}, {}, {}, {}, {}, {}, {}]];

      return {
        table: {
          headerRows: 1,
          widths: [62, "*", 82, 125, 108, 68, 72, 72],
          body: [header, ...rows],
        },
        layout: this.pdfTableLayout(),
      };
    },
    pdfCalendar(plans) {
      const [year, month] = this.calendarMonth.split("-").map(Number);
      const firstDay = new Date(year, month - 1, 1, 12);
      const firstWeekday = (firstDay.getDay() + 6) % 7;
      const gridStart = new Date(firstDay);
      gridStart.setDate(firstDay.getDate() - firstWeekday);
      const days = Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);
        const iso = this.formatYMD(date);
        return {
          day: date.getDate(),
          isCurrentMonth: date.getMonth() === month - 1,
          plans: plans.filter((plan) => this.planDate(plan) === iso),
        };
      });

      const weekdays = ["Lun", "Mar", "Mie", "Jue", "Vie", "Sab", "Dom"].map((text) => ({
        text,
        alignment: "center",
        bold: true,
        color: "#ffffff",
        fillColor: "#334155",
        margin: [0, 4, 0, 4],
      }));
      const weeks = Array.from({ length: 6 }, (_, weekIndex) =>
        days.slice(weekIndex * 7, weekIndex * 7 + 7).map((day) => this.pdfCalendarCell(day))
      );

      return {
        table: {
          headerRows: 1,
          widths: ["*", "*", "*", "*", "*", "*", "*"],
          body: [weekdays, ...weeks],
        },
        layout: {
          hLineColor: () => "#dbe5f4",
          vLineColor: () => "#dbe5f4",
          hLineWidth: () => 0.7,
          vLineWidth: () => 0.7,
          paddingLeft: () => 0,
          paddingRight: () => 0,
          paddingTop: () => 0,
          paddingBottom: () => 0,
        },
      };
    },
    pdfCalendarCell(day) {
      const stack = [
        {
          text: String(day.day),
          color: day.isCurrentMonth ? "#243047" : "#94a3b8",
          bold: true,
          fontSize: 9,
          margin: [0, 0, 0, 3],
        },
      ];

      if (!day.plans.length) stack.push({ text: " ", fontSize: 7 });

      for (const plan of day.plans.slice(0, 4)) {
        stack.push({
          text: `${plan.title}\n${plan.item_label || ""}`,
          color: this.pdfAlertColor(plan.alert_state),
          fontSize: 6.5,
          lineHeight: 1.08,
          margin: [0, 2, 0, 0],
        });
      }

      if (day.plans.length > 4) {
        stack.push({ text: `+${day.plans.length - 4} más`, color: "#64748b", fontSize: 6.5, margin: [0, 2, 0, 0] });
      }

      return {
        stack,
        fillColor: day.isCurrentMonth ? "#ffffff" : "#f8fafc",
        margin: [4, 4, 4, 4],
      };
    },
  },
  watch: {
    showModalPlan(value) {
      this.debugLog("watch showModalPlan", value);
    },
  },
};
</script>

<template>
  <Layout>
    <div class="annual-page">
      <div class="annual-header">
        <div>
          <span class="annual-eyebrow">Mantención</span>
          <h4>Plan anual de mantención</h4>
          <p>Programa bienes, áreas técnicas y elementos de dependencias con alertas por fecha.</p>
        </div>
        <div class="annual-header-actions">
          <div class="annual-view-toggle" aria-label="Vista">
            <button type="button" :class="{ active: viewMode === 'table' }" @click="setViewMode('table')">
              <i class="mdi mdi-table"></i>
              Tabla
            </button>
            <button type="button" :class="{ active: viewMode === 'calendar' }" @click="setViewMode('calendar')">
              <i class="mdi mdi-calendar-month-outline"></i>
              Calendario
            </button>
            <button type="button" :class="{ active: viewMode === 'upcoming' }" @click="setViewMode('upcoming')">
              <i class="mdi mdi-calendar-clock"></i>
              Fechas próximas
            </button>
          </div>
          <button class="annual-secondary-button annual-secondary-button--red" type="button" :disabled="exporting" @click="exportPdf">
            <i class="mdi mdi-file-pdf-box"></i>
            PDF
          </button>
          <button class="annual-primary-button" type="button" @click="openCreate">
            <i class="mdi mdi-plus"></i>
            Agregar ítem
          </button>
        </div>
      </div>

      <div class="annual-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="annual-summary-card"
          :class="`annual-summary-card--${card.tone}`"
        >
          <div class="annual-summary-icon">
            <i :class="`mdi ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="annual-panel annual-filters-panel">
        <div class="annual-panel-head">
          <div>
            <span class="annual-eyebrow">Filtros</span>
            <h5>Consulta del plan</h5>
          </div>
          <span class="annual-filter-count" :class="{ active: activeFiltersCount > 0 }">
            {{ activeFiltersCount }} filtros
          </span>
        </div>

        <div class="annual-filters">
          <label class="annual-field annual-field--search">
            <span>Búsqueda</span>
            <input v-model="search" type="search" placeholder="Título, ítem, dependencia..." @keyup.enter="loadPlans(1)" />
          </label>
          <label v-if="viewMode !== 'upcoming'" class="annual-field">
            <span>Año</span>
            <input v-model.number="filters.planned_year" type="number" min="2000" max="2100" />
          </label>
          <label v-if="viewMode !== 'upcoming'" class="annual-field">
            <span>Mes</span>
            <select v-model="filters.planned_month">
              <option value="">Todos</option>
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </label>
          <label class="annual-field annual-field--dependency">
            <span>Dependencia</span>
            <select v-model="filters.dependency_id">
              <option value="">Todas</option>
              <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">
                {{ dep.code }} · {{ dep.name }}
              </option>
            </select>
          </label>
          <label class="annual-field">
            <span>Tipo de ítem</span>
            <select v-model="filters.item_type">
              <option value="">Todos</option>
              <option v-for="type in itemTypeOptions" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
          </label>
          <label class="annual-field">
            <span>Categoría</span>
            <select v-model="filters.category">
              <option value="">Todas</option>
              <option v-for="category in catalogs.categories" :key="category" :value="category">{{ category }}</option>
            </select>
          </label>
          <label class="annual-field">
            <span>Responsable</span>
            <select v-model="filters.responsible">
              <option value="">Todos</option>
              <option v-for="person in responsibleOptions" :key="person.value" :value="person.value">{{ person.label }}</option>
            </select>
          </label>
          <label class="annual-field">
            <span>Frecuencia</span>
            <select v-model="filters.frequency">
              <option value="">Todas</option>
              <option v-for="frequency in catalogs.frequencies" :key="frequency" :value="frequency">{{ frequency }}</option>
            </select>
          </label>
          <label class="annual-field">
            <span>Estado</span>
            <select v-model="filters.status">
              <option value="">Todos</option>
              <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>
          <div class="annual-filter-actions">
            <button class="annual-primary-button" type="button" :disabled="loading" @click="loadPlans(1)">
              <i class="mdi mdi-filter-outline"></i>
              Filtrar
            </button>
            <button class="annual-secondary-button" type="button" @click="resetFilters">Limpiar</button>
          </div>
        </div>
      </section>

      <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>
      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <section class="annual-panel">
        <div class="annual-panel-head">
          <div>
            <span class="annual-eyebrow">{{ viewMode === "calendar" ? "Calendario" : viewMode === "upcoming" ? "Alertas" : "Listado" }}</span>
            <h5>{{ viewMode === "calendar" ? calendarTitle : viewMode === "upcoming" ? "Fechas próximas" : "Ítems programados" }}</h5>
          </div>
          <div v-if="viewMode === 'calendar'" class="annual-calendar-controls">
            <button class="annual-secondary-button" type="button" @click="changeCalendarMonth(-1)">
              <i class="mdi mdi-chevron-left"></i>
            </button>
            <button class="annual-secondary-button" type="button" @click="goCurrentMonth">Mes actual</button>
            <button class="annual-secondary-button" type="button" @click="changeCalendarMonth(1)">
              <i class="mdi mdi-chevron-right"></i>
            </button>
          </div>
        </div>

        <div v-if="viewMode === 'calendar'" class="annual-calendar">
          <div class="annual-calendar-weekdays">
            <span>Lun</span>
            <span>Mar</span>
            <span>Mié</span>
            <span>Jue</span>
            <span>Vie</span>
            <span>Sáb</span>
            <span>Dom</span>
          </div>
          <div class="annual-calendar-grid" :class="{ 'is-loading': loading }">
            <div
              v-for="day in calendarDays"
              :key="day.iso"
              class="annual-calendar-day"
              :class="{ muted: !day.isCurrentMonth, today: day.isToday }"
            >
              <div class="annual-calendar-day-head">
                <span>{{ day.day }}</span>
                <small v-if="day.plans.length">{{ day.plans.length }}</small>
              </div>
              <div class="annual-calendar-events">
                <button
                  v-for="plan in day.plans"
                  :key="plan.id"
                  type="button"
                  class="annual-calendar-event"
                  :class="alertClass(plan.alert_state)"
                  :title="plan.title"
                  @click="editPlan(plan)"
                >
                  <strong>{{ plan.title }}</strong>
                  <span>{{ plan.item_label }}</span>
                </button>
              </div>
            </div>
          </div>
          <div v-if="!loading && plans.length === 0" class="annual-empty-state annual-empty-state--calendar">
            No hay mantenciones programadas para el mes o filtros seleccionados.
          </div>
        </div>

        <div v-else class="annual-table-wrap">
          <table class="annual-table">
            <colgroup>
              <col class="annual-col-date" />
              <col class="annual-col-item" />
              <col class="annual-col-type" />
              <col class="annual-col-dependency" />
              <col class="annual-col-responsible" />
              <col class="annual-col-frequency" />
              <col class="annual-col-alert" />
              <col class="annual-col-status" />
              <col class="annual-col-actions" />
            </colgroup>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Ítem</th>
                <th>Tipo</th>
                <th>Dependencia</th>
                <th>Responsable</th>
                <th>Frecuencia</th>
                <th>Alerta</th>
                <th>Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="9">
                  <div class="annual-empty-state">Cargando plan anual...</div>
                </td>
              </tr>
              <tr v-else-if="plans.length === 0">
                <td colspan="9">
                  <div class="annual-empty-state">No hay ítems programados.</div>
                </td>
              </tr>
              <tr v-for="plan in plans" :key="plan.id">
                <td class="annual-cell-center">
                  <span class="annual-date-chip">{{ formatDMY(planDate(plan)) }}</span>
                  <small>{{ monthLabel(plan.planned_month) }} {{ plan.planned_year }}</small>
                </td>
                <td>
                  <div class="annual-title">{{ plan.title }}</div>
                  <div class="annual-muted">{{ plan.item_label }}</div>
                </td>
                <td class="annual-cell-center">
                  <span class="annual-type-chip" :class="typeClass(plan.item_type)" :title="plan.item_type_label">
                    {{ compactItemTypeLabel(plan.item_type) }}
                  </span>
                </td>
                <td>
                  <div class="annual-dependency">{{ dependencyLabel(plan.dependency) }}</div>
                </td>
                <td>
                  <div class="annual-responsible">{{ plan.responsible || "-" }}</div>
                </td>
                <td class="annual-cell-center">
                  <span class="annual-soft-chip">{{ plan.frequency }}</span>
                </td>
                <td class="annual-cell-center">
                  <span class="annual-alert-chip" :class="alertClass(plan.alert_state)">{{ plan.alert_label }}</span>
                  <small v-if="plan.alert_enabled">Aviso {{ plan.alert_days }} días</small>
                </td>
                <td class="annual-cell-center">
                  <span class="annual-pill" :class="statusClass(plan.status)">{{ plan.status }}</span>
                </td>
                <td class="annual-actions-cell">
                  <div class="annual-actions">
                    <button type="button" title="Editar" @click="editPlan(plan)">
                      <i class="mdi mdi-pencil-outline"></i>
                    </button>
                    <button type="button" title="Eliminar" @click="deletePlan(plan)">
                      <i class="mdi mdi-trash-can-outline"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="viewMode === 'table'" class="annual-pagination">
          <span>Total: {{ pagination.total }}</span>
          <div class="annual-pagination-actions">
            <button type="button" :disabled="pagination.current_page <= 1" @click="loadPlans(pagination.current_page - 1)">
              Anterior
            </button>
            <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadPlans(pagination.current_page + 1)">
              Siguiente
            </button>
          </div>
        </div>
      </section>
    </div>

    <BModal
      v-model="showModalPlan"
      :title="isEditing ? 'Editar ítem de mantención' : 'Nuevo ítem de mantención'"
      title-class="annual-modal-title"
      header-class="annual-modal-header"
      body-class="annual-modal-body p-0"
      modal-class="annual-modal"
      size="lg"
      hide-footer
      centered
      scrollable
      teleport-to="body"
      lazy
      no-fade
      @show="onModalEvent('annual-plan', 'show')"
      @shown="onModalEvent('annual-plan', 'shown')"
      @hide="onModalEvent('annual-plan', 'hide')"
      @hidden="onModalEvent('annual-plan', 'hidden')"
    >
      <form class="annual-form" @submit.prevent="savePlan">
        <div class="annual-modal-scroll">
          <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

          <section class="annual-form-section">
            <div class="annual-form-section-head">
              <i class="mdi mdi-shape-outline"></i>
              <div>
                <h6>Ítem mantenible</h6>
                <span>Bien de inventario, área técnica o elemento físico de una dependencia</span>
              </div>
            </div>

            <div class="annual-form-grid annual-form-grid--two">
              <label class="annual-field">
                <span>Tipo de ítem</span>
                <select v-model="form.item_type" required @change="onItemTypeChange">
                  <option v-for="type in itemTypeOptions" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
              </label>
              <label class="annual-field">
                <span>Dependencia base</span>
                <select v-model="form.maintenance_dependency_id" required>
                  <option value="">Selecciona...</option>
                  <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">
                    {{ dep.code }} · {{ dep.name }}
                  </option>
                </select>
              </label>
            </div>

            <label v-if="form.item_type === 'inventory_item'" class="annual-field annual-field--wide">
              <span>Bien inventariado</span>
              <select v-model="form.inventory_item_id" required @change="syncInventoryItemSelection">
                <option value="">Selecciona bien...</option>
                <option v-for="item in filteredInventoryItems" :key="item.id" :value="item.id">
                  {{ inventoryItemLabel(item) }}
                </option>
              </select>
            </label>

            <label v-if="form.item_type === 'technical_area'" class="annual-field annual-field--wide">
              <span>Área técnica</span>
              <select v-model="form.technical_area_id" required @change="syncTechnicalAreaSelection">
                <option value="">Selecciona área técnica...</option>
                <option v-for="area in filteredTechnicalAreas" :key="area.id" :value="area.id">
                  {{ technicalAreaLabel(area) }}
                </option>
              </select>
            </label>

            <label v-if="form.item_type === 'dependency_component'" class="annual-field annual-field--wide">
              <span>Elemento de dependencia</span>
              <input
                v-model="form.component_name"
                type="text"
                list="annual-components"
                placeholder="Ventanas, puertas, paredes..."
                required
                @change="syncComponentName"
              />
              <datalist id="annual-components">
                <option v-for="component in catalogs.component_suggestions" :key="component" :value="component" />
              </datalist>
            </label>
          </section>

          <section class="annual-form-section">
            <div class="annual-form-section-head">
              <i class="mdi mdi-calendar-clock"></i>
              <div>
                <h6>Programación y alerta</h6>
                <span>Fecha programada, frecuencia y anticipación del aviso</span>
              </div>
            </div>

            <div class="annual-form-grid annual-form-grid--three">
              <label class="annual-field">
                <span>Fecha programada</span>
                <input v-model="form.scheduled_date" type="date" @change="onScheduledDateChange" />
              </label>
              <label class="annual-field">
                <span>Frecuencia</span>
                <select v-model="form.frequency" required>
                  <option v-for="frequency in catalogs.frequencies" :key="frequency" :value="frequency">{{ frequency }}</option>
                </select>
              </label>
              <label class="annual-field">
                <span>Estado</span>
                <select v-model="form.status" required>
                  <option v-for="status in catalogs.statuses" :key="status" :value="status">{{ status }}</option>
                </select>
              </label>
              <label class="annual-field">
                <span>Año</span>
                <input v-model.number="form.planned_year" type="number" min="2000" max="2100" required />
              </label>
              <label class="annual-field">
                <span>Mes</span>
                <select v-model.number="form.planned_month" required>
                  <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                </select>
              </label>
              <label class="annual-field">
                <span>Días de alerta</span>
                <input v-model.number="form.alert_days" type="number" min="1" max="365" required />
              </label>
            </div>

            <label class="annual-switch-field">
              <input v-model="form.alert_enabled" type="checkbox" />
              <span>Activar alerta de fecha próxima</span>
            </label>
          </section>

          <section class="annual-form-section">
            <div class="annual-form-section-head">
              <i class="mdi mdi-clipboard-text-outline"></i>
              <div>
                <h6>Detalle operativo</h6>
                <span>Título, responsable y observaciones</span>
              </div>
            </div>

            <div class="annual-form-grid annual-form-grid--two">
              <label class="annual-field">
                <span>Título</span>
                <input v-model="form.title" type="text" required />
              </label>
              <label class="annual-field">
                <span>Responsable</span>
                <select v-model="form.responsible" required>
                  <option value="">Selecciona...</option>
                  <option v-for="person in responsibleOptions" :key="person.value" :value="person.value">{{ person.label }}</option>
                </select>
              </label>
              <label class="annual-field">
                <span>Categoría</span>
                <select v-model="form.category" required>
                  <option v-for="category in catalogs.categories" :key="category" :value="category">{{ category }}</option>
                </select>
              </label>
              <label class="annual-field">
                <span>Última mantención</span>
                <input v-model="form.last_maintenance_date" type="date" />
              </label>
              <label class="annual-field">
                <span>Fecha cumplida</span>
                <input v-model="form.completed_date" type="date" />
              </label>
              <label class="annual-field annual-field--wide">
                <span>Descripción</span>
                <textarea
                  v-model="form.description"
                  rows="3"
                  placeholder="Alcance de la mantención, criterios de revisión o proveedor externo..."
                ></textarea>
              </label>
              <label class="annual-field annual-field--wide">
                <span>Notas</span>
                <input v-model="form.notes" type="text" placeholder="Observaciones internas..." />
              </label>
            </div>
          </section>
        </div>

        <div class="annual-modal-footer">
          <button class="annual-secondary-button" type="button" @click="showModalPlan = false">Cancelar</button>
          <button class="annual-primary-button" type="submit" :disabled="saving">
            {{ saving ? "Guardando..." : isEditing ? "Actualizar ítem" : "Crear ítem" }}
          </button>
        </div>
      </form>
    </BModal>
  </Layout>
</template>

<style scoped>
.annual-page {
  padding: 4px 0 24px;
}

.annual-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 0 16px;
  border-bottom: 1px solid #e3ebfb;
  margin-bottom: 22px;
}

.annual-eyebrow {
  display: block;
  color: #6d7690;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0;
  text-transform: uppercase;
}

.annual-header h4,
.annual-panel-head h5 {
  margin: 4px 0 0;
  color: #303848;
  font-weight: 700;
  letter-spacing: 0;
}

.annual-header p {
  margin: 8px 0 0;
  color: #717b94;
  font-size: 15px;
  font-weight: 400;
}

.annual-header-actions,
.annual-filter-actions,
.annual-pagination-actions,
.annual-calendar-controls,
.annual-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.annual-header-actions {
  justify-content: flex-end;
  flex-wrap: wrap;
  max-width: 920px;
}

.annual-primary-button,
.annual-secondary-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 18px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.annual-primary-button {
  color: #fff;
  background: #5b74df;
  border-color: #5b74df;
}

.annual-primary-button:hover {
  color: #fff;
  background: #4f66ca;
  border-color: #4f66ca;
}

.annual-primary-button:disabled,
.annual-secondary-button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.annual-secondary-button {
  color: #566079;
  background: #fff;
  border-color: #b9c3d8;
}

.annual-secondary-button:hover {
  color: #384154;
  background: #f5f7fb;
  border-color: #8d99b2;
}

.annual-secondary-button--red {
  color: #b91c1c;
  background: #fff8f8;
  border-color: #fecaca;
}

.annual-secondary-button--red:hover {
  color: #991b1b;
  background: #fef2f2;
  border-color: #fca5a5;
}

.annual-view-toggle {
  display: inline-flex;
  flex-wrap: wrap;
  padding: 4px;
  border-radius: 10px;
  border: 1px solid #dce5f4;
  background: #f8fbff;
}

.annual-view-toggle button {
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

.annual-view-toggle button.active {
  color: #3152c9;
  background: #eef4ff;
  box-shadow: inset 0 0 0 1px #c7d7fe;
}

.annual-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.annual-summary-card {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr);
  gap: 14px;
  align-items: center;
  min-height: 116px;
  padding: 20px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: 0 18px 42px rgba(63, 84, 120, 0.06);
}

.annual-summary-icon {
  width: 46px;
  height: 46px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 24px;
}

.annual-summary-card span,
.annual-summary-card small {
  display: block;
  color: #6d7690;
  font-size: 13px;
  font-weight: 500;
}

.annual-summary-card strong {
  display: block;
  margin: 4px 0;
  color: #303848;
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
}

.annual-summary-card--blue .annual-summary-icon {
  color: #3152c9;
  background: #eef4ff;
}

.annual-summary-card--amber .annual-summary-icon {
  color: #b45309;
  background: #fffbeb;
}

.annual-summary-card--red .annual-summary-icon {
  color: #b91c1c;
  background: #fef2f2;
}

.annual-summary-card--green .annual-summary-icon {
  color: #047857;
  background: #ecfdf5;
}

.annual-panel {
  padding: 22px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.84);
  box-shadow: 0 18px 44px rgba(63, 84, 120, 0.06);
}

.annual-panel + .annual-panel,
.annual-filters-panel {
  margin-bottom: 18px;
}

.annual-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.annual-filter-count {
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

.annual-filter-count.active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.annual-filters {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 12px;
  align-items: end;
}

.annual-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  grid-column: span 2;
  min-width: 0;
  margin: 0;
}

.annual-field--search,
.annual-field--dependency {
  grid-column: span 3;
}

.annual-field--wide {
  grid-column: 1 / -1;
  margin-top: 14px;
}

.annual-field span {
  color: #4c5568;
  font-size: 13px;
  line-height: 1.2;
  font-weight: 600;
}

.annual-field input,
.annual-field select,
.annual-field textarea {
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

.annual-field textarea {
  padding-top: 12px;
  resize: vertical;
}

.annual-field input:focus,
.annual-field select:focus,
.annual-field textarea:focus {
  border-color: #9db1f8;
  box-shadow: 0 0 0 3px rgba(91, 116, 223, 0.12);
}

.annual-filter-actions {
  grid-column: span 2;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.annual-table-wrap {
  overflow-x: auto;
  border-top: 1px solid #e2eaf8;
}

.annual-table {
  width: 100%;
  min-width: 1220px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.annual-table th,
.annual-table td {
  padding: 16px 10px;
  vertical-align: middle;
  border-bottom: 1px solid #e5edf9;
}

.annual-table th {
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
  text-align: center;
  border-bottom-color: #dce7f7;
}

.annual-table td {
  color: #364154;
  font-size: 14px;
  font-weight: 400;
  overflow: hidden;
}

.annual-col-date {
  width: 8%;
}

.annual-col-item {
  width: 18%;
}

.annual-col-type {
  width: 11%;
}

.annual-col-dependency {
  width: 13%;
}

.annual-col-responsible {
  width: 12%;
}

.annual-col-frequency {
  width: 8%;
}

.annual-col-alert {
  width: 10%;
}

.annual-col-status {
  width: 11%;
}

.annual-col-actions {
  width: 9%;
}

.annual-cell-center,
.annual-actions-cell {
  text-align: center;
}

.annual-actions-cell {
  overflow: visible;
}

.annual-cell-center small,
.annual-muted {
  display: block;
  margin-top: 5px;
  color: #778199;
  font-size: 12px;
  line-height: 1.35;
  font-weight: 400;
}

.annual-title,
.annual-dependency,
.annual-responsible {
  color: #303848;
  line-height: 1.35;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.annual-date-chip,
.annual-soft-chip,
.annual-type-chip,
.annual-alert-chip,
.annual-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  border: 1px solid transparent;
  box-sizing: border-box;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.15;
  white-space: nowrap;
  max-width: 100%;
}

.annual-type-chip,
.annual-alert-chip,
.annual-pill {
  min-width: 0;
  white-space: normal;
  text-align: center;
  padding-top: 7px;
  padding-bottom: 7px;
}

.annual-pill {
  width: 100%;
  max-width: 138px;
}

.annual-date-chip,
.annual-soft-chip {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.annual-type--dependency {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.annual-type--component {
  color: #7c3aed;
  background: #f5f3ff;
  border-color: #ddd6fe;
}

.annual-type--inventory {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.annual-type--technical {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.annual-alert--upcoming {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.annual-alert--overdue,
.annual-pill--overdue {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.annual-alert--scheduled,
.annual-pill--planned,
.annual-pill--active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.annual-alert--closed,
.annual-pill--done {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.annual-alert--nodate,
.annual-pill--cancelled,
.annual-pill--neutral {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.annual-actions {
  justify-content: center;
}

.annual-actions button {
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  border-radius: 10px;
  border: 1px solid #cfd8ea;
  color: #647089;
  background: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.annual-actions button[title="Editar"] {
  color: #b45309;
  background: #fffbeb;
  border-color: #fbbf24;
}

.annual-actions button[title="Eliminar"] {
  color: #dc2626;
  background: #fef2f2;
  border-color: #fecaca;
}

.annual-empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 94px;
  color: #7a849a;
  font-size: 14px;
  font-weight: 500;
}

.annual-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 16px;
  color: #717b94;
  font-size: 13px;
}

.annual-pagination-actions button {
  min-height: 36px;
  border-radius: 8px;
  border: 1px solid #cfd8ea;
  background: #fff;
  color: #566079;
  padding: 0 12px;
  font-weight: 600;
}

.annual-pagination-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.annual-calendar-weekdays,
.annual-calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
}

.annual-calendar-weekdays {
  gap: 8px;
  margin-bottom: 8px;
}

.annual-calendar-weekdays span {
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
}

.annual-calendar-grid {
  overflow: hidden;
  border: 1px solid #dce7f7;
  border-radius: 8px;
  background: #fff;
}

.annual-calendar-grid.is-loading {
  opacity: 0.62;
}

.annual-calendar-day {
  min-height: 140px;
  padding: 10px;
  border-right: 1px solid #e5edf9;
  border-bottom: 1px solid #e5edf9;
  background: #fff;
}

.annual-calendar-day:nth-child(7n) {
  border-right: 0;
}

.annual-calendar-day:nth-last-child(-n + 7) {
  border-bottom: 0;
}

.annual-calendar-day.muted {
  background: #f8fafc;
}

.annual-calendar-day.today {
  box-shadow: inset 0 0 0 2px #9db1f8;
}

.annual-calendar-day-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.annual-calendar-day-head span {
  color: #303848;
  font-size: 14px;
  font-weight: 700;
}

.annual-calendar-day-head small {
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

.annual-calendar-events {
  display: grid;
  gap: 6px;
}

.annual-calendar-event {
  width: 100%;
  min-width: 0;
  padding: 8px;
  border-radius: 8px;
  border: 1px solid #dce5f4;
  text-align: left;
}

.annual-calendar-event strong,
.annual-calendar-event span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.annual-calendar-event strong {
  font-size: 12px;
  font-weight: 700;
}

.annual-calendar-event span {
  margin-top: 2px;
  color: #303848;
  font-size: 11px;
  font-weight: 500;
}

.annual-empty-state--calendar {
  min-height: 72px;
  border: 1px dashed #dce5f4;
  border-radius: 8px;
  margin-top: 12px;
}

:deep(.annual-modal .modal-dialog) {
  max-width: min(920px, calc(100vw - 32px));
}

:deep(.annual-modal .modal-content) {
  border-radius: 8px;
  border: 1px solid #dce5f4;
  overflow: hidden;
}

:deep(.annual-modal-header) {
  min-height: 68px;
  padding: 18px 24px;
  border-bottom: 1px solid #e2eaf8;
  background: #fff;
}

:deep(.annual-modal-title) {
  color: #303848;
  font-size: 20px;
  font-weight: 700;
}

.annual-form {
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 110px);
  background: #f8fafc;
}

.annual-modal-scroll {
  overflow-y: auto;
  padding: 18px 22px;
}

.annual-form-section {
  padding: 18px;
  border: 1px solid #e0e8f6;
  border-radius: 8px;
  background: #fff;
}

.annual-form-section + .annual-form-section {
  margin-top: 14px;
}

.annual-form-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.annual-form-section-head i {
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

.annual-form-section-head h6 {
  margin: 0;
  color: #303848;
  font-size: 15px;
  font-weight: 600;
}

.annual-form-section-head span {
  color: #778199;
  font-size: 12px;
  font-weight: 400;
}

.annual-form-grid {
  display: grid;
  gap: 14px;
}

.annual-form-grid--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.annual-form-grid--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.annual-switch-field {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-top: 14px;
  color: #4c5568;
  font-size: 14px;
  font-weight: 600;
}

.annual-switch-field input {
  width: 18px;
  height: 18px;
  accent-color: #5b74df;
}

.annual-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid #e2eaf8;
  background: #fff;
}

@media (max-width: 1400px) {
  .annual-field,
  .annual-field--search,
  .annual-field--dependency,
  .annual-filter-actions {
    grid-column: span 4;
  }

  .annual-filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 1200px) {
  .annual-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .annual-calendar-grid,
  .annual-calendar-weekdays {
    min-width: 980px;
  }

  .annual-calendar {
    overflow-x: auto;
  }
}

@media (max-width: 768px) {
  .annual-header,
  .annual-panel-head,
  .annual-pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .annual-header-actions,
  .annual-filter-actions,
  .annual-calendar-controls,
  .annual-pagination-actions {
    flex-wrap: wrap;
  }

  .annual-summary-grid,
  .annual-filters,
  .annual-form-grid--two,
  .annual-form-grid--three {
    grid-template-columns: 1fr;
  }

  .annual-field,
  .annual-field--search,
  .annual-field--dependency,
  .annual-filter-actions {
    grid-column: 1 / -1;
  }

  .annual-filter-actions .annual-primary-button,
  .annual-filter-actions .annual-secondary-button {
    width: 100%;
  }

  .annual-panel {
    padding: 16px;
  }

  .annual-modal-scroll,
  .annual-modal-footer {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
