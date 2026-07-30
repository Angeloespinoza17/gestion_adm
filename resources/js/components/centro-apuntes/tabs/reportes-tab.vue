<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesSectionToolbar from "../section-toolbar.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  downloadExcelWorkbook,
  downloadPdfReport,
  extractChartLabels,
  extractChartTotals,
  formatCentroApuntesError,
  humanizeCentroApuntesStatus,
  normalizeOptions,
  printCentroApuntesHtml,
} from "../module-utils";

const emptyReport = () => ({
  generated_at: null,
  range: {},
  summary: {},
  comparison: { summary: {}, deltas: {} },
  charts: {
    volume_timeline: [],
    service_timeline: [],
    requests_by_status: [],
    sheets_by_user: [],
    sheets_by_department: [],
    sheets_by_subject: [],
    sheets_by_machine: [],
    sheets_by_task_type: [],
    sheets_by_paper_size: [],
    supply_consumption: [],
    supply_coverage: [],
  },
  rankings: {
    users: [],
    departments: [],
    subjects: [],
    machines: [],
    task_types: [],
  },
  sections: [],
  metadata: {},
});

const emptyFilters = () => ({
  period: "mensual",
  start_date: "",
  end_date: "",
  requested_by_user_id: null,
  department_id: null,
  subject_id: null,
  machine_id: null,
  paper_size: null,
  task_type: null,
  status: null,
  supply_id: null,
  category: null,
  urgent_only: false,
  immediate_only: false,
});

export default {
  components: {
    CentroApuntesHelpButton,
    CentroApuntesSectionToolbar,
    LoadingState,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: true,
      exporting: null,
      hasLoaded: false,
      error: null,
      report: emptyReport(),
      filters: emptyFilters(),
      showAdvancedFilters: false,
      activeRanking: "users",
    };
  },
  computed: {
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export_reports);
    },
    periodOptions() {
      return normalizeOptions(this.catalogs.report_periods || []);
    },
    userOptions() {
      return normalizeOptions(this.catalogs.users || []);
    },
    departmentOptions() {
      return normalizeOptions(this.catalogs.departments || []);
    },
    subjectOptions() {
      return normalizeOptions(this.catalogs.subjects || []);
    },
    machineOptions() {
      return normalizeOptions(this.catalogs.machines || []);
    },
    paperSizeOptions() {
      return normalizeOptions(this.catalogs.paper_sizes || []);
    },
    taskTypeOptions() {
      return normalizeOptions(this.catalogs.task_types || []);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.request_statuses || []);
    },
    supplyOptions() {
      return normalizeOptions(this.catalogs.supplies || []);
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.supply_categories || []);
    },
    metricCards() {
      const summary = this.report.summary || {};
      const deltas = this.report.comparison?.deltas || {};

      return [
        {
          key: "sheets_printed_total",
          label: "Hojas impresas",
          value: summary.sheets_printed_total,
          hint: "Páginas × juegos de copias",
          icon: "bx-printer",
          tone: "primary",
          delta: deltas.sheets_printed_total,
        },
        {
          key: "requests_total",
          label: "Solicitudes",
          value: summary.requests_total,
          hint: "Trabajos ingresados",
          icon: "bx-file",
          tone: "info",
          delta: deltas.requests_total,
        },
        {
          key: "original_pages_total",
          label: "Páginas originales",
          value: summary.original_pages_total,
          hint: "Páginas base de los trabajos",
          icon: "bx-copy-alt",
          tone: "secondary",
          delta: deltas.original_pages_total,
        },
        {
          key: "delivered_total",
          label: "Entregadas",
          value: summary.delivered_total,
          hint: "Solicitudes finalizadas",
          icon: "bx-check-double",
          tone: "success",
          delta: deltas.delivered_total,
        },
        {
          key: "on_time_rate",
          label: "Cumplimiento",
          value: summary.on_time_rate,
          suffix: "%",
          hint: "Entregadas dentro de fecha",
          icon: "bx-calendar-check",
          tone: "success",
          delta: deltas.on_time_rate,
        },
        {
          key: "median_turnaround_hours",
          label: "Tiempo mediano",
          value: summary.median_turnaround_hours,
          suffix: " h",
          hint: `P90: ${this.formatNumber(summary.p90_turnaround_hours)} h`,
          icon: "bx-time-five",
          tone: "warning",
          delta: deltas.median_turnaround_hours,
          lowerIsBetter: true,
        },
        {
          key: "backlog_total",
          label: "Solicitudes abiertas",
          value: summary.backlog_total,
          hint: `${this.formatNumber(summary.overdue_open_total)} atrasadas`,
          icon: "bx-layer",
          tone: "warning",
          delta: deltas.backlog_total,
          lowerIsBetter: true,
        },
        {
          key: "critical_stock_total",
          label: "Stock crítico",
          value: summary.critical_stock_total,
          hint: `${this.formatNumber(summary.out_of_stock_total)} agotados`,
          icon: "bx-error-circle",
          tone: "danger",
          delta: null,
          lowerIsBetter: true,
        },
      ];
    },
    activeFilterCount() {
      return Object.entries(this.filters).filter(([key, value]) => {
        if (key === "period") return value !== "mensual";
        return value !== null && value !== "" && value !== false;
      }).length;
    },
    rangeLabel() {
      const range = this.report.range || {};
      if (!range.start || !range.end) return "Sin período";
      return `${this.formatDate(range.start)} al ${this.formatDate(range.end)}`;
    },
    comparisonLabel() {
      const range = this.report.range || {};
      if (!range.comparison_start || !range.comparison_end) return "";
      return `${this.formatDate(range.comparison_start)} al ${this.formatDate(range.comparison_end)}`;
    },
    volumeChartOptions() {
      const options = basicApexOptions({
        categories: (this.report.charts?.volume_timeline || []).map((item) => this.shortDate(item.label)),
        colors: ["#405189"],
      });
      return {
        ...options,
        chart: { ...options.chart, toolbar: { show: false }, zoom: { enabled: false } },
        stroke: { curve: "smooth", width: 3 },
        fill: {
          type: "gradient",
          gradient: { shadeIntensity: 1, opacityFrom: 0.32, opacityTo: 0.05, stops: [0, 95, 100] },
        },
        yaxis: { labels: { formatter: (value) => this.compactNumber(value) } },
        tooltip: { y: { formatter: (value) => `${this.formatNumber(value)} hojas` } },
      };
    },
    volumeSeries() {
      return [{
        name: "Hojas impresas",
        data: (this.report.charts?.volume_timeline || []).map((item) => Number(item.sheets_printed || 0)),
      }];
    },
    serviceChartOptions() {
      const options = basicApexOptions({
        categories: (this.report.charts?.service_timeline || []).map((item) => this.shortDate(item.label)),
        colors: ["#34c38f"],
      });
      return {
        ...options,
        yaxis: { min: 0, max: 100, labels: { formatter: (value) => `${Math.round(value)}%` } },
        tooltip: { y: { formatter: (value) => `${this.formatNumber(value)}%` } },
      };
    },
    serviceSeries() {
      return [{
        name: "Cumplimiento de fecha",
        data: (this.report.charts?.service_timeline || []).map((item) => Number(item.on_time_rate || 0)),
      }];
    },
    statusChartOptions() {
      return {
        labels: extractChartLabels(this.report.charts?.requests_by_status),
        legend: { position: "bottom", fontSize: "11px" },
        dataLabels: { enabled: true, formatter: (value) => `${Math.round(value)}%` },
        colors: ["#405189", "#34c38f", "#f1b44c", "#50a5f1", "#f46a6a", "#74788d", "#a8b0bd"],
        stroke: { width: 2, colors: ["var(--bs-body-bg)"] },
      };
    },
    userChartOptions() {
      return this.rankingChartOptions(this.report.charts?.sheets_by_user, "#405189");
    },
    departmentChartOptions() {
      return this.rankingChartOptions(this.report.charts?.sheets_by_department, "#34c38f");
    },
    subjectChartOptions() {
      return this.rankingChartOptions(this.report.charts?.sheets_by_subject, "#50a5f1");
    },
    machineChartOptions() {
      return this.rankingChartOptions(this.report.charts?.sheets_by_machine, "#f1b44c");
    },
    supplyChartOptions() {
      return this.rankingChartOptions(this.report.charts?.supply_consumption, "#f46a6a");
    },
    coverageChartOptions() {
      const items = this.report.charts?.supply_coverage || [];
      const options = basicApexOptions({
        categories: extractChartLabels(items),
        colors: ["#f1b44c"],
        horizontal: true,
      });
      return {
        ...options,
        tooltip: { y: { formatter: (value) => `${this.formatNumber(value)} días` } },
        xaxis: {
          ...options.xaxis,
          labels: { formatter: (value) => `${Math.round(value)} d` },
        },
      };
    },
    rankingTabs() {
      return [
        { key: "users", label: "Funcionarios", singular: "Funcionario", icon: "bx-user" },
        { key: "departments", label: "Departamentos", singular: "Departamento", icon: "bx-buildings" },
        { key: "subjects", label: "Asignaturas", singular: "Asignatura", icon: "bx-book-open" },
        { key: "machines", label: "Máquinas", singular: "Máquina", icon: "bx-printer" },
        { key: "task_types", label: "Tipos de trabajo", singular: "Tipo de trabajo", icon: "bx-category-alt" },
      ];
    },
    activeRankingRows() {
      return this.report.rankings?.[this.activeRanking] || [];
    },
    activeRankingLabel() {
      return this.rankingTabs.find((item) => item.key === this.activeRanking)?.label || "Ranking";
    },
    activeRankingSingular() {
      return this.rankingTabs.find((item) => item.key === this.activeRanking)?.singular || "Nombre";
    },
  },
  mounted() {
    this.loadReport();
  },
  methods: {
    extractChartTotals,
    formatNumber(value, maximumFractionDigits = 1) {
      return new Intl.NumberFormat("es-CL", { maximumFractionDigits }).format(Number(value || 0));
    },
    compactNumber(value) {
      return new Intl.NumberFormat("es-CL", { notation: "compact", maximumFractionDigits: 1 }).format(Number(value || 0));
    },
    formatDate(value) {
      if (!value) return "-";
      return new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString("es-CL");
    },
    shortDate(value) {
      if (!value) return "-";
      if (/^\d{4}-\d{2}$/.test(value)) {
        return new Date(`${value}-01T12:00:00`).toLocaleDateString("es-CL", { month: "short", year: "2-digit" });
      }
      return new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString("es-CL", { day: "2-digit", month: "short" });
    },
    deltaMeta(card) {
      if (card.delta === null || card.delta === undefined) {
        return { label: "Sin base comparable", tone: "neutral", icon: "bx-minus" };
      }
      const delta = Number(card.delta);
      if (delta === 0) return { label: "Sin variación", tone: "neutral", icon: "bx-minus" };
      const favorable = card.lowerIsBetter ? delta < 0 : delta > 0;
      return {
        label: `${delta > 0 ? "+" : ""}${this.formatNumber(delta)}%`,
        tone: favorable ? "success" : "danger",
        icon: delta > 0 ? "bx-up-arrow-alt" : "bx-down-arrow-alt",
      };
    },
    rankingChartOptions(items, color) {
      const options = basicApexOptions({
        categories: extractChartLabels(items),
        colors: [color],
        horizontal: true,
      });
      return {
        ...options,
        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: "58%" } },
        xaxis: {
          ...options.xaxis,
          labels: { formatter: (value) => this.compactNumber(value) },
        },
        tooltip: { y: { formatter: (value) => `${this.formatNumber(value)} hojas` } },
      };
    },
    series(items, name = "Hojas impresas", key = "sheets_printed") {
      return [{ name, data: (items || []).map((item) => Number(item?.[key] ?? item?.total ?? 0)) }];
    },
    requestParams() {
      return {
        ...this.filters,
        urgent_only: this.filters.urgent_only ? 1 : "",
        immediate_only: this.filters.immediate_only ? 1 : "",
      };
    },
    async loadReport() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/reportes", {
          params: this.requestParams(),
        });
        this.report = response.data;
        this.hasLoaded = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar las estadísticas del módulo.");
      } finally {
        this.loading = false;
      }
    },
    clearFilters() {
      this.filters = emptyFilters();
      this.showAdvancedFilters = false;
      this.loadReport();
    },
    async exportExcel() {
      if (!this.canExport || this.exporting) return;
      this.exporting = "excel";
      try {
        downloadExcelWorkbook(
          `estadisticas-centro-apuntes-${this.report.range?.start || "periodo"}`,
          this.report.sections || [],
          {
            title: "Estadísticas Centro de Apuntes",
            subtitle: `Período ${this.rangeLabel}. Informe operativo sin costos.`,
          }
        );
      } finally {
        this.exporting = null;
      }
    },
    async exportPdf() {
      if (!this.canExport || this.exporting) return;
      this.exporting = "pdf";
      try {
        const sections = (this.report.sections || [])
          .filter((section) => section.title !== "Detalle de solicitudes de impresión")
          .map((section) => ({ ...section, rows: (section.rows || []).slice(0, 25) }));
        downloadPdfReport(
          `estadisticas-centro-apuntes-${this.report.range?.start || "periodo"}`,
          "Estadísticas Centro de Apuntes",
          `Período ${this.rangeLabel} | Comparación ${this.comparisonLabel} | Informe operativo sin costos`,
          sections,
          {
            pageOrientation: "landscape",
            headerText: "CENTRO DE APUNTES - INFORME ESTADÍSTICO",
          }
        );
      } finally {
        this.exporting = null;
      }
    },
    printReport() {
      if (!this.canExport) return;
      const sections = (this.report.sections || []).filter((section) => section.title !== "Detalle de solicitudes de impresión");
      const html = sections
        .map((section) => `
          <h2>${this.escapeHtml(section.title)}</h2>
          ${section.subtitle ? `<p>${this.escapeHtml(section.subtitle)}</p>` : ""}
          <table>
            <thead><tr>${(section.headers || []).map((header) => `<th>${this.escapeHtml(header)}</th>`).join("")}</tr></thead>
            <tbody>${(section.rows || []).slice(0, 30).map((row) => `<tr>${row.map((cell) => `<td>${this.escapeHtml(cell)}</td>`).join("")}</tr>`).join("")}</tbody>
          </table>
        `)
        .join("");

      printCentroApuntesHtml(
        "Estadísticas Centro de Apuntes",
        `<p><strong>Período:</strong> ${this.escapeHtml(this.rangeLabel)}</p><p>Informe operativo sin costos.</p>${html}`
      );
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
  <div class="centro-apuntes-tab analytics-view d-flex flex-column gap-3">
    <CentroApuntesSectionToolbar
      title="Estadísticas e informes"
      description="Mide hojas impresas, demanda, cumplimiento, productividad e inventario sin incluir costos."
      icon="bx-line-chart"
      eyebrow="Inteligencia operativa"
    >
      <div class="analytics-toolbar">
        <CentroApuntesHelpButton
          title="Ayuda: estadísticas del Centro de Apuntes"
          text="Las hojas impresas corresponden a páginas originales multiplicadas por los juegos de copias. Los indicadores pueden desglosarse por funcionario, departamento, asignatura, máquina y tipo de trabajo."
        />
        <BButton v-if="canExport" variant="outline-success" :disabled="loading || exporting" @click="exportExcel">
          <i class="bx bx-spreadsheet me-1"></i>{{ exporting === "excel" ? "Generando..." : "Excel" }}
        </BButton>
        <BButton v-if="canExport" variant="outline-danger" :disabled="loading || exporting" @click="exportPdf">
          <i class="bx bxs-file-pdf me-1"></i>{{ exporting === "pdf" ? "Generando..." : "PDF" }}
        </BButton>
        <BButton v-if="canExport" variant="outline-dark" :disabled="loading || exporting" @click="printReport">
          <i class="bx bx-printer me-1"></i>Imprimir
        </BButton>
      </div>
    </CentroApuntesSectionToolbar>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="analytics-filter-card border-0 shadow-sm">
      <div class="analytics-filter-card__header">
        <div>
          <span>Período y segmentación</span>
          <strong>Configura el análisis</strong>
        </div>
        <div class="analytics-filter-card__meta">
          <span v-if="activeFilterCount"><i class="bx bx-filter-alt"></i>{{ activeFilterCount }} filtros</span>
          <span v-if="hasLoaded"><i class="bx bx-calendar"></i>{{ rangeLabel }}</span>
        </div>
      </div>

      <div class="row g-3 align-items-end analytics-filter-grid">
        <div class="col-sm-6 col-xl-2">
          <label class="form-label">Período</label>
          <BFormSelect v-model="filters.period" :options="periodOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-sm-6 col-xl-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.start_date" type="date" />
        </div>
        <div class="col-sm-6 col-xl-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.end_date" type="date" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Funcionario</label>
          <BFormSelect v-model="filters.requested_by_user_id" :options="[{ value: null, text: 'Todos' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Departamento</label>
          <BFormSelect v-model="filters.department_id" :options="[{ value: null, text: 'Todos' }].concat(departmentOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Asignatura</label>
          <BFormSelect v-model="filters.subject_id" :options="[{ value: null, text: 'Todas' }].concat(subjectOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Máquina</label>
          <BFormSelect v-model="filters.machine_id" :options="[{ value: null, text: 'Todas' }].concat(machineOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(statusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Tipo de trabajo</label>
          <BFormSelect v-model="filters.task_type" :options="[{ value: null, text: 'Todos' }].concat(taskTypeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
      </div>

      <div v-if="showAdvancedFilters" class="row g-3 align-items-end analytics-filter-grid analytics-filter-grid--advanced">
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Tamaño de papel</label>
          <BFormSelect v-model="filters.paper_size" :options="[{ value: null, text: 'Todos' }].concat(paperSizeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Insumo</label>
          <BFormSelect v-model="filters.supply_id" :options="[{ value: null, text: 'Todos' }].concat(supplyOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <label class="form-label">Categoría de insumo</label>
          <BFormSelect v-model="filters.category" :options="[{ value: null, text: 'Todas' }].concat(categoryOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-sm-6 col-xl-3">
          <div class="analytics-checks">
            <BFormCheckbox v-model="filters.urgent_only">Solo urgentes</BFormCheckbox>
            <BFormCheckbox v-model="filters.immediate_only">Entrega inmediata</BFormCheckbox>
          </div>
        </div>
      </div>

      <div class="analytics-filter-actions">
        <BButton variant="link" class="px-0 text-decoration-none" @click="showAdvancedFilters = !showAdvancedFilters">
          <i class="bx me-1" :class="showAdvancedFilters ? 'bx-chevron-up' : 'bx-slider-alt'"></i>
          {{ showAdvancedFilters ? "Ocultar filtros avanzados" : "Más filtros" }}
        </BButton>
        <div>
          <BButton variant="light" :disabled="loading || !activeFilterCount" @click="clearFilters"><i class="bx bx-reset me-1"></i>Limpiar</BButton>
          <BButton variant="primary" :disabled="loading" @click="loadReport"><i class="bx bx-bar-chart-alt-2 me-1"></i>{{ loading ? "Analizando..." : "Generar análisis" }}</BButton>
        </div>
      </div>
    </BCard>

    <BCard v-if="loading && !hasLoaded" class="border-0 shadow-sm">
      <LoadingState message="Calculando estadísticas del Centro de Apuntes..." compact />
    </BCard>

    <template v-if="hasLoaded">
      <div class="analytics-period-banner">
        <div>
          <span>Período analizado</span>
          <strong>{{ rangeLabel }}</strong>
        </div>
        <div>
          <span>Comparado con</span>
          <strong>{{ comparisonLabel }}</strong>
        </div>
        <div class="analytics-period-banner__note">
          <i class="bx bx-info-circle"></i>
          <span>Estadísticas exclusivamente operativas. No se muestran costos.</span>
        </div>
      </div>

      <div class="analytics-metrics">
        <article v-for="card in metricCards" :key="card.key" class="analytics-metric" :class="`analytics-metric--${card.tone}`">
          <div class="analytics-metric__top">
            <span class="analytics-metric__icon"><i class="bx" :class="card.icon"></i></span>
            <span class="analytics-delta" :class="`analytics-delta--${deltaMeta(card).tone}`">
              <i class="bx" :class="deltaMeta(card).icon"></i>{{ deltaMeta(card).label }}
            </span>
          </div>
          <span class="analytics-metric__label">{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}<small>{{ card.suffix || "" }}</small></strong>
          <p>{{ card.hint }}</p>
        </article>
      </div>

      <div class="row g-3">
        <div class="col-xl-8">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Producción</span>
                <strong>Evolución de hojas impresas</strong>
              </div>
            </template>
            <apexchart type="area" height="320" :options="volumeChartOptions" :series="volumeSeries" />
          </BCard>
        </div>
        <div class="col-xl-4">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Flujo de trabajo</span>
                <strong>Solicitudes por estado</strong>
              </div>
            </template>
            <apexchart type="donut" height="320" :options="statusChartOptions" :series="extractChartTotals(report.charts?.requests_by_status, 'requests')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Nivel de servicio</span>
                <strong>Cumplimiento de fecha</strong>
              </div>
            </template>
            <apexchart type="line" height="300" :options="serviceChartOptions" :series="serviceSeries" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Demanda interna</span>
                <strong>Hojas impresas por funcionario</strong>
              </div>
            </template>
            <apexchart type="bar" height="300" :options="userChartOptions" :series="series(report.charts?.sheets_by_user)" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Distribución institucional</span>
                <strong>Hojas impresas por departamento</strong>
              </div>
            </template>
            <apexchart type="bar" height="300" :options="departmentChartOptions" :series="series(report.charts?.sheets_by_department)" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Uso pedagógico</span>
                <strong>Hojas impresas por asignatura</strong>
              </div>
            </template>
            <apexchart type="bar" height="300" :options="subjectChartOptions" :series="series(report.charts?.sheets_by_subject)" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Equipamiento</span>
                <strong>Hojas impresas por máquina</strong>
              </div>
            </template>
            <apexchart type="bar" height="300" :options="machineChartOptions" :series="series(report.charts?.sheets_by_machine)" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Pañol</span>
                <strong>Consumo de insumos</strong>
              </div>
            </template>
            <apexchart type="bar" height="300" :options="supplyChartOptions" :series="series(report.charts?.supply_consumption, 'Consumo', 'quantity')" />
          </BCard>
        </div>
      </div>

      <BCard class="analytics-ranking-card border-0 shadow-sm">
        <div class="analytics-ranking-card__header">
          <div>
            <span class="analytics-card-eyebrow">Desglose detallado</span>
            <h6>Ranking de hojas impresas por {{ activeRankingLabel.toLowerCase() }}</h6>
          </div>
          <div class="analytics-ranking-tabs">
            <button
              v-for="tab in rankingTabs"
              :key="tab.key"
              type="button"
              :class="{ active: activeRanking === tab.key }"
              @click="activeRanking = tab.key"
            >
              <i class="bx" :class="tab.icon"></i>{{ tab.label }}
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table analytics-table align-middle mb-0">
            <thead>
              <tr>
                <th>Posición</th>
                <th>{{ activeRankingSingular }}</th>
                <th>Solicitudes</th>
                <th>Páginas originales</th>
                <th>Juegos de copias</th>
                <th>Hojas impresas</th>
                <th>Participación</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!activeRankingRows.length">
                <td colspan="7" class="text-center text-muted py-4">Sin datos para el período seleccionado.</td>
              </tr>
              <tr v-for="(item, index) in activeRankingRows.slice(0, 25)" :key="`${activeRanking}-${item.label}`">
                <td><span class="analytics-rank">{{ index + 1 }}</span></td>
                <td>
                  <strong>{{ item.label }}</strong>
                  <small v-if="activeRanking === 'users'">{{ item.departments || "Sin departamento" }}</small>
                </td>
                <td>{{ formatNumber(item.requests, 0) }}</td>
                <td>{{ formatNumber(item.original_pages, 0) }}</td>
                <td>{{ formatNumber(item.copy_sets, 0) }}</td>
                <td><strong>{{ formatNumber(item.sheets_printed, 0) }}</strong></td>
                <td>
                  <div class="analytics-share">
                    <span><i :style="{ width: `${Math.min(100, item.share)}%` }"></i></span>
                    <strong>{{ formatNumber(item.share) }}%</strong>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCard>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="analytics-chart-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Continuidad operativa</span>
                <strong>Días estimados de cobertura</strong>
              </div>
            </template>
            <apexchart type="bar" height="320" :options="coverageChartOptions" :series="series(report.charts?.supply_coverage, 'Cobertura', 'coverage_days')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="analytics-inventory-card border-0 shadow-sm h-100">
            <template #header>
              <div>
                <span class="analytics-card-eyebrow">Alertas de inventario</span>
                <strong>Estado del pañol</strong>
              </div>
            </template>
            <div class="analytics-inventory-list">
              <div>
                <span class="analytics-inventory-list__icon bg-warning-subtle text-warning"><i class="bx bx-trending-down"></i></span>
                <span><strong>{{ formatNumber(report.summary?.critical_stock_total, 0) }}</strong><small>Insumos con stock crítico</small></span>
              </div>
              <div>
                <span class="analytics-inventory-list__icon bg-danger-subtle text-danger"><i class="bx bx-x-circle"></i></span>
                <span><strong>{{ formatNumber(report.summary?.out_of_stock_total, 0) }}</strong><small>Insumos agotados</small></span>
              </div>
              <div>
                <span class="analytics-inventory-list__icon bg-info-subtle text-info"><i class="bx bx-calendar-exclamation"></i></span>
                <span><strong>{{ formatNumber(report.summary?.expiring_soon_total, 0) }}</strong><small>Próximos a vencer</small></span>
              </div>
              <div>
                <span class="analytics-inventory-list__icon bg-secondary-subtle text-secondary"><i class="bx bx-trash-alt"></i></span>
                <span><strong>{{ formatNumber(report.summary?.supplies_loss_total) }}</strong><small>Pérdidas, bajas o vencimientos</small></span>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <BAlert v-if="report.metadata?.detail_truncated" show variant="warning" class="mb-0">
        El Excel incluye las primeras {{ formatNumber(report.metadata.detail_limit, 0) }} solicitudes del período. Aplica filtros para obtener un detalle más específico.
      </BAlert>
    </template>
  </div>
</template>

<style scoped>
.analytics-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
}

.analytics-filter-card :deep(.card-body) {
  padding: 0 !important;
}

.analytics-filter-card__header,
.analytics-ranking-card__header {
  align-items: center;
  border-bottom: 1px solid var(--bs-border-color);
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 1rem 1.15rem;
}

.analytics-filter-card__header > div:first-child {
  display: grid;
}

.analytics-filter-card__header > div:first-child span,
.analytics-card-eyebrow {
  color: var(--bs-primary);
  font-size: .62rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.analytics-filter-card__header > div:first-child strong {
  color: var(--bs-heading-color);
  font-size: .9rem;
}

.analytics-filter-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
  justify-content: flex-end;
}

.analytics-filter-card__meta span {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), .07);
  border: 1px solid rgba(var(--bs-primary-rgb), .12);
  border-radius: 999px;
  color: var(--bs-primary);
  display: inline-flex;
  font-size: .67rem;
  font-weight: 650;
  gap: .3rem;
  padding: .32rem .58rem;
}

.analytics-filter-grid {
  margin: 0;
  padding: 1rem 1.15rem .25rem;
}

.analytics-filter-grid--advanced {
  border-top: 1px dashed var(--bs-border-color);
  margin-top: .75rem;
  padding-top: .9rem;
}

.analytics-checks {
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: .55rem;
  display: grid;
  gap: .28rem;
  min-height: 2.6rem;
  padding: .48rem .65rem;
}

.analytics-checks :deep(.form-check-label) {
  font-size: .72rem;
}

.analytics-filter-actions {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: .65rem 1.15rem 1rem;
}

.analytics-filter-actions > div {
  display: flex;
  gap: .45rem;
}

.analytics-period-banner {
  align-items: center;
  background:
    linear-gradient(115deg, rgba(var(--bs-primary-rgb), .1), rgba(var(--bs-info-rgb), .035)),
    var(--bs-body-bg);
  border: 1px solid rgba(var(--bs-primary-rgb), .14);
  border-radius: .85rem;
  display: grid;
  gap: 1.25rem;
  grid-template-columns: auto auto minmax(260px, 1fr);
  padding: .8rem 1rem;
}

.analytics-period-banner > div:not(.analytics-period-banner__note) {
  display: grid;
}

.analytics-period-banner span {
  color: var(--bs-secondary-color);
  font-size: .65rem;
}

.analytics-period-banner strong {
  color: var(--bs-heading-color);
  font-size: .78rem;
}

.analytics-period-banner__note {
  align-items: center;
  color: var(--bs-secondary-color);
  display: flex;
  gap: .45rem;
  justify-content: flex-end;
}

.analytics-period-banner__note i {
  color: var(--bs-primary);
  font-size: 1rem;
}

.analytics-metrics {
  display: grid;
  gap: .8rem;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.analytics-metric {
  --metric-rgb: var(--bs-primary-rgb);
  background: var(--bs-body-bg);
  border: 1px solid rgba(var(--metric-rgb), .14);
  border-radius: .85rem;
  box-shadow: 0 .55rem 1.45rem rgba(48, 65, 102, .05);
  min-height: 9.2rem;
  padding: .9rem 1rem;
  position: relative;
}

.analytics-metric::after {
  background: rgb(var(--metric-rgb));
  border-radius: 999px;
  bottom: 0;
  content: "";
  height: .18rem;
  left: 1rem;
  opacity: .75;
  position: absolute;
  right: 1rem;
}

.analytics-metric--success { --metric-rgb: var(--bs-success-rgb); }
.analytics-metric--warning { --metric-rgb: var(--bs-warning-rgb); }
.analytics-metric--danger { --metric-rgb: var(--bs-danger-rgb); }
.analytics-metric--info { --metric-rgb: var(--bs-info-rgb); }
.analytics-metric--secondary { --metric-rgb: var(--bs-secondary-rgb); }

.analytics-metric__top {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: .65rem;
}

.analytics-metric__icon {
  align-items: center;
  background: rgba(var(--metric-rgb), .1);
  border-radius: .62rem;
  color: rgb(var(--metric-rgb));
  display: inline-flex;
  font-size: 1.1rem;
  height: 2.25rem;
  justify-content: center;
  width: 2.25rem;
}

.analytics-delta {
  align-items: center;
  border-radius: 999px;
  display: inline-flex;
  font-size: .61rem;
  font-weight: 700;
  gap: .15rem;
  padding: .25rem .4rem;
}

.analytics-delta--success { background: rgba(var(--bs-success-rgb), .1); color: var(--bs-success); }
.analytics-delta--danger { background: rgba(var(--bs-danger-rgb), .1); color: var(--bs-danger); }
.analytics-delta--neutral { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); }

.analytics-metric__label {
  color: var(--bs-secondary-color);
  display: block;
  font-size: .66rem;
  font-weight: 750;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.analytics-metric > strong {
  color: var(--bs-heading-color);
  display: block;
  font-size: 1.65rem;
  line-height: 1.3;
  margin-top: .1rem;
}

.analytics-metric > strong small {
  font-size: .75rem;
  font-weight: 650;
}

.analytics-metric p {
  color: var(--bs-secondary-color);
  font-size: .65rem;
  margin: .08rem 0 0;
}

.analytics-chart-card :deep(.card-header),
.analytics-inventory-card :deep(.card-header) {
  background: transparent;
  border-bottom: 1px solid var(--bs-border-color);
  padding: .9rem 1rem;
}

.analytics-chart-card :deep(.card-header > div),
.analytics-inventory-card :deep(.card-header > div) {
  display: grid;
}

.analytics-chart-card :deep(.card-header strong),
.analytics-inventory-card :deep(.card-header strong) {
  color: var(--bs-heading-color);
  font-size: .82rem;
}

.analytics-chart-card :deep(.card-body) {
  padding: .7rem .8rem .35rem;
}

.analytics-ranking-card :deep(.card-body) {
  padding: 0 !important;
}

.analytics-ranking-card__header h6 {
  color: var(--bs-heading-color);
  font-size: .88rem;
  margin: .08rem 0 0;
}

.analytics-ranking-tabs {
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: .68rem;
  display: flex;
  gap: .18rem;
  padding: .2rem;
}

.analytics-ranking-tabs button {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: .48rem;
  color: var(--bs-secondary-color);
  display: inline-flex;
  font-size: .66rem;
  font-weight: 650;
  gap: .28rem;
  padding: .42rem .55rem;
}

.analytics-ranking-tabs button.active {
  background: var(--bs-body-bg);
  box-shadow: 0 .15rem .5rem rgba(48, 65, 102, .08);
  color: var(--bs-primary);
}

.analytics-table {
  font-size: .72rem;
}

.analytics-table thead th {
  background: var(--bs-tertiary-bg);
  border-bottom-width: 1px;
  color: var(--bs-secondary-color);
  font-size: .61rem;
  letter-spacing: .035em;
  padding: .65rem .8rem;
  text-transform: uppercase;
  white-space: nowrap;
}

.analytics-table tbody td {
  padding: .65rem .8rem;
}

.analytics-table tbody td > strong,
.analytics-table tbody td > small {
  display: block;
}

.analytics-table tbody td > small {
  color: var(--bs-secondary-color);
  font-size: .62rem;
}

.analytics-rank {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), .08);
  border-radius: .48rem;
  color: var(--bs-primary);
  display: inline-flex;
  font-size: .66rem;
  font-weight: 750;
  height: 1.7rem;
  justify-content: center;
  width: 1.7rem;
}

.analytics-share {
  align-items: center;
  display: flex;
  gap: .45rem;
  min-width: 125px;
}

.analytics-share > span {
  background: var(--bs-tertiary-bg);
  border-radius: 999px;
  display: block;
  height: .34rem;
  overflow: hidden;
  width: 78px;
}

.analytics-share > span i {
  background: var(--bs-primary);
  border-radius: inherit;
  display: block;
  height: 100%;
}

.analytics-share strong {
  font-size: .66rem;
}

.analytics-inventory-list {
  display: grid;
  gap: .65rem;
}

.analytics-inventory-list > div {
  align-items: center;
  background: var(--bs-tertiary-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: .72rem;
  display: flex;
  gap: .7rem;
  padding: .75rem;
}

.analytics-inventory-list__icon {
  align-items: center;
  border-radius: .6rem;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.1rem;
  height: 2.45rem;
  justify-content: center;
  width: 2.45rem;
}

.analytics-inventory-list > div > span:last-child {
  display: grid;
}

.analytics-inventory-list strong {
  color: var(--bs-heading-color);
  font-size: 1rem;
}

.analytics-inventory-list small {
  color: var(--bs-secondary-color);
  font-size: .67rem;
}

@media (max-width: 1399.98px) {
  .analytics-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .analytics-ranking-card__header { align-items: flex-start; flex-direction: column; }
  .analytics-ranking-tabs { flex-wrap: wrap; }
}

@media (max-width: 991.98px) {
  .analytics-period-banner { grid-template-columns: 1fr 1fr; }
  .analytics-period-banner__note { grid-column: 1 / -1; justify-content: flex-start; }
}

@media (max-width: 575.98px) {
  .analytics-toolbar :deep(.btn) { flex: 1 1 auto; }
  .analytics-filter-card__header { align-items: flex-start; flex-direction: column; }
  .analytics-filter-card__meta { justify-content: flex-start; }
  .analytics-filter-actions { align-items: stretch; flex-direction: column; }
  .analytics-filter-actions > div { display: grid; grid-template-columns: 1fr 1fr; }
  .analytics-period-banner { grid-template-columns: 1fr; }
  .analytics-period-banner__note { grid-column: auto; }
  .analytics-metrics { grid-template-columns: 1fr; }
  .analytics-ranking-tabs { width: 100%; }
  .analytics-ranking-tabs button { flex: 1 1 45%; justify-content: center; }
}
</style>
