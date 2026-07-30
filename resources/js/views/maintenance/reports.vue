<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
  downloadExcelWorkbook,
  downloadPdfReport,
} from "../../components/centro-apuntes/module-utils";

const emptyReport = () => ({
  generated_at: null,
  range: {},
  summary: {},
  comparison: { summary: {}, deltas: {} },
  charts: {
    volume_timeline: [],
    orders_by_status: [],
    orders_by_priority: [],
    visits_by_status: [],
    plans_by_status: [],
    plans_timeline: [],
  },
  rankings: {
    dependencies: [],
    technical_areas: [],
    assignees: [],
    requesters: [],
    components: [],
  },
  visits: { summary: {}, rows: [] },
  annual_plan: { summary: {}, rows: [] },
  priority_work_orders: [],
  work_orders: [],
  catalogs: {
    statuses: [],
    priorities: [],
    assignees: [],
    dependencies: [],
    technical_areas: [],
  },
  metadata: {},
});

const emptyFilters = () => ({
  period: "mensual",
  start_date: "",
  end_date: "",
  status: "",
  priority: "",
  assignee: "",
  dependency_id: "",
  technical_area_id: "",
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: true,
      exporting: null,
      error: null,
      report: emptyReport(),
      filters: emptyFilters(),
      activeRanking: "dependencies",
      showAdvancedFilters: false,
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
    canExport() {
      return this.permissions.includes("exportar_mantencion")
        || this.permissions.includes("__superadmin__");
    },
    summaryCards() {
      const summary = this.report.summary || {};
      const deltas = this.report.comparison?.deltas || {};

      return [
        {
          key: "work_orders_total",
          label: "Órdenes registradas",
          value: summary.work_orders_total,
          hint: "OT ingresadas en el período",
          icon: "bx-clipboard",
          tone: "primary",
          delta: deltas.work_orders_total,
        },
        {
          key: "open_total",
          label: "Carga abierta",
          value: summary.open_total,
          hint: `${this.formatNumber(summary.unassigned_open_total)} sin asignar`,
          icon: "bx-layer",
          tone: "warning",
          delta: deltas.open_total,
          lowerIsBetter: true,
        },
        {
          key: "completed_total",
          label: "OT terminadas",
          value: summary.completed_total,
          hint: `${this.formatNumber(summary.completion_rate)}% del período`,
          icon: "bx-check-double",
          tone: "success",
          delta: deltas.completed_total,
        },
        {
          key: "overdue_open_total",
          label: "OT vencidas",
          value: summary.overdue_open_total,
          hint: "Abiertas fuera de plazo",
          icon: "bx-time-five",
          tone: "danger",
          delta: deltas.overdue_open_total,
          lowerIsBetter: true,
        },
        {
          key: "critical_open_total",
          label: "Críticas abiertas",
          value: summary.critical_open_total,
          hint: `${this.formatNumber(summary.high_priority_open_total)} críticas o altas`,
          icon: "bx-error-circle",
          tone: "danger",
          delta: deltas.critical_open_total,
          lowerIsBetter: true,
        },
        {
          key: "on_time_rate",
          label: "Cumplimiento de plazo",
          value: summary.on_time_rate,
          suffix: "%",
          hint: "OT terminadas con fecha objetivo",
          icon: "bx-calendar-check",
          tone: "success",
          delta: deltas.on_time_rate,
        },
        {
          key: "median_resolution_hours",
          label: "Tiempo mediano",
          value: summary.median_resolution_hours,
          suffix: " h",
          hint: `Promedio ${this.formatNumber(summary.average_resolution_hours)} h`,
          icon: "bx-time-five",
          tone: "info",
          delta: deltas.average_resolution_hours,
          lowerIsBetter: true,
        },
        {
          key: "plans_overdue_total",
          label: "Plan anual vencido",
          value: summary.plans_overdue_total,
          hint: `${this.formatNumber(summary.plans_completed_total)} de ${this.formatNumber(summary.plans_total)} cumplidas`,
          icon: "bx-calendar-event",
          tone: "warning",
          delta: null,
          lowerIsBetter: true,
        },
      ];
    },
    activeFilterCount() {
      return Object.entries(this.filters).filter(([key, value]) => {
        if (key === "period") return value !== "mensual";
        return value !== "" && value !== null;
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
    workflowStages() {
      const summary = this.report.summary || {};
      const total = Number(summary.work_orders_total || 0);

      return [
        { label: "Ingresadas", value: total, icon: "bx-log-in-circle", tone: "primary" },
        { label: "Abiertas", value: summary.open_total, icon: "bx-loader-circle", tone: "warning" },
        { label: "Vencidas", value: summary.overdue_open_total, icon: "bx-error-circle", tone: "danger" },
        { label: "Terminadas", value: summary.completed_total, icon: "bx-check-circle", tone: "success" },
      ].map((stage) => ({
        ...stage,
        share: total ? Math.round((Number(stage.value || 0) / total) * 100) : 0,
      }));
    },
    timelineSeries() {
      const rows = this.report.charts?.volume_timeline || [];
      return [
        { name: "OT ingresadas", type: "column", data: rows.map((row) => Number(row.total || 0)) },
        { name: "Terminadas", type: "line", data: rows.map((row) => Number(row.completed || 0)) },
      ];
    },
    timelineOptions() {
      const rows = this.report.charts?.volume_timeline || [];
      return {
        chart: { toolbar: { show: false }, fontFamily: "Inter, Arial, sans-serif" },
        colors: ["#405189", "#34c38f"],
        dataLabels: { enabled: false },
        stroke: { width: [0, 3], curve: "smooth" },
        plotOptions: { bar: { borderRadius: 5, columnWidth: "52%" } },
        xaxis: {
          categories: rows.map((row, index) => (rows.length > 14 && index % 3 !== 0 ? "" : this.shortDate(row.label))),
          labels: { style: { colors: "#667085", fontSize: "11px" }, rotate: rows.length > 12 ? -45 : 0 },
        },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value) } },
        grid: { borderColor: "#e9edf5", strokeDashArray: 4 },
        legend: { position: "top", horizontalAlign: "right" },
        tooltip: { shared: true, intersect: false },
      };
    },
    statusSeries() {
      return (this.report.charts?.orders_by_status || []).map((row) => Number(row.total || 0));
    },
    statusOptions() {
      const rows = this.report.charts?.orders_by_status || [];
      return {
        chart: { fontFamily: "Inter, Arial, sans-serif" },
        labels: rows.map((row) => row.label),
        colors: ["#74788d", "#50a5f1", "#f1b44c", "#9c6ade", "#34c38f", "#adb5bd"],
        legend: { position: "bottom", fontSize: "12px" },
        dataLabels: { enabled: true, formatter: (value) => `${Math.round(value)}%` },
        plotOptions: { pie: { donut: { size: "66%", labels: { show: true, total: { show: true, label: "Total" } } } } },
      };
    },
    prioritySeries() {
      return [{
        name: "Órdenes",
        data: (this.report.charts?.orders_by_priority || []).map((row) => Number(row.total || 0)),
      }];
    },
    priorityOptions() {
      const rows = this.report.charts?.orders_by_priority || [];
      return {
        chart: { toolbar: { show: false }, fontFamily: "Inter, Arial, sans-serif" },
        colors: ["#f46a6a"],
        dataLabels: { enabled: true },
        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: "54%" } },
        xaxis: { categories: rows.map((row) => row.label), min: 0, labels: { formatter: (value) => Math.round(value) } },
        grid: { borderColor: "#e9edf5", strokeDashArray: 4 },
        legend: { show: false },
      };
    },
    rankingTabs() {
      return [
        { key: "dependencies", label: "Dependencias", icon: "bx-buildings" },
        { key: "technical_areas", label: "Áreas técnicas", icon: "bx-wrench" },
        { key: "assignees", label: "Responsables", icon: "bx-group" },
        { key: "requesters", label: "Solicitantes", icon: "bx-user-voice" },
        { key: "components", label: "Elementos", icon: "bx-cube" },
      ];
    },
    activeRankingRows() {
      return (this.report.rankings?.[this.activeRanking] || []).slice(0, 12);
    },
    exportSections() {
      const summary = this.report.summary || {};
      const visitSummary = this.report.visits?.summary || {};
      const planSummary = this.report.annual_plan?.summary || {};

      return [
        {
          title: "Resumen ejecutivo",
          subtitle: `Período ${this.rangeLabel}. Informe interno sin costos.`,
          headers: ["Indicador", "Resultado", "Interpretación"],
          rows: [
            ["Órdenes registradas", summary.work_orders_total, "OT ingresadas en el período"],
            ["Carga abierta", summary.open_total, `${summary.unassigned_open_total || 0} sin asignar`],
            ["OT terminadas", summary.completed_total, `${summary.completion_rate || 0}% del total`],
            ["OT vencidas", summary.overdue_open_total, "Abiertas fuera de plazo"],
            ["Críticas abiertas", summary.critical_open_total, "Prioridad crítica pendiente"],
            ["Cumplimiento de plazo", `${summary.on_time_rate || 0}%`, "Terminadas dentro de fecha objetivo"],
            ["Tiempo mediano de resolución", `${summary.median_resolution_hours || 0} h`, "Calculado sobre OT terminadas"],
            ["Edad promedio de OT abiertas", `${summary.average_open_age_days || 0} días`, "Antigüedad de la carga pendiente"],
          ],
        },
        {
          title: "Órdenes por estado",
          headers: ["Estado", "Órdenes"],
          rows: (this.report.charts?.orders_by_status || []).map((row) => [row.label, row.total]),
        },
        {
          title: "Órdenes por prioridad",
          headers: ["Prioridad", "Órdenes"],
          rows: (this.report.charts?.orders_by_priority || []).map((row) => [row.label, row.total]),
        },
        ...this.rankingTabs.map((tab) => ({
          title: `Ranking por ${tab.label.toLowerCase()}`,
          subtitle: tab.key === "assignees" ? "Una OT con varios responsables cuenta como una participación para cada persona." : "",
          headers: [tab.label.slice(0, -1) || tab.label, "Total", "Abiertas", "Terminadas", "Vencidas", "Críticas", "Participación"],
          rows: (this.report.rankings?.[tab.key] || []).map((row) => [
            row.label,
            row.total,
            row.open,
            row.completed,
            row.overdue,
            row.critical,
            `${row.share || 0}%`,
          ]),
        })),
        {
          title: "Visitas de mantención",
          subtitle: `${visitSummary.completed || 0} finalizadas de ${visitSummary.total || 0}.`,
          headers: ["Fecha", "Dependencia", "Tipo", "Responsable", "Estado"],
          rows: (this.report.visits?.rows || []).map((row) => [
            this.formatDate(row.visit_date),
            row.dependency,
            row.visit_type,
            row.responsible,
            row.status,
          ]),
        },
        {
          title: "Plan anual de mantención",
          subtitle: `${planSummary.completed || 0} cumplidas y ${planSummary.overdue || 0} vencidas.`,
          headers: ["Fecha", "Actividad", "Dependencia", "Categoría", "Responsable", "Estado"],
          rows: (this.report.annual_plan?.rows || []).map((row) => [
            this.formatDate(row.scheduled_date || row.planned_date),
            row.title,
            row.dependency,
            row.category,
            row.responsible,
            row.overdue ? "Vencida" : row.status,
          ]),
        },
        {
          title: "Detalle de órdenes de trabajo",
          subtitle: this.report.metadata?.detail_truncated
            ? `Se muestran las primeras ${this.report.metadata?.detail_limit || 5000} OT.`
            : "Detalle completo del período filtrado.",
          headers: ["OT", "Ingreso", "Vencimiento", "Dependencia", "Elemento", "Solicitante", "Responsables", "Prioridad", "Estado", "Descripción"],
          rows: (this.report.work_orders || []).map((row) => [
            `OT-${row.id}`,
            this.formatDate(row.reported_at),
            this.formatDate(row.due_date),
            row.dependency,
            row.component,
            row.requested_by || "Sin solicitante",
            row.assigned_to || "Sin asignar",
            row.priority,
            row.overdue ? "Vencida" : row.status,
            row.description,
          ]),
        },
      ];
    },
  },
  mounted() {
    this.loadReport();
  },
  methods: {
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL", { maximumFractionDigits: 1 }).format(Number(value || 0));
    },
    formatDate(value) {
      if (!value) return "-";
      const parts = String(value).slice(0, 10).split("-");
      return parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : String(value);
    },
    shortDate(value) {
      if (!value) return "";
      const parts = String(value).split("-");
      return parts.length === 3 ? `${parts[2]}/${parts[1]}` : `${parts[1] || ""}/${parts[0] || ""}`;
    },
    formatDateTime(value) {
      if (!value) return "-";
      const date = new Date(value);
      return Number.isNaN(date.getTime())
        ? String(value)
        : date.toLocaleString("es-CL", {
          day: "2-digit",
          month: "2-digit",
          year: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        });
    },
    deltaLabel(card) {
      if (card.delta === null || card.delta === undefined) {
        return "Sin base comparable";
      }
      const delta = Number(card.delta || 0);
      if (delta === 0) return "Sin cambio respecto al anterior";
      const improved = card.lowerIsBetter ? delta <= 0 : delta >= 0;
      const direction = delta > 0 ? "subió" : delta < 0 ? "bajó" : "sin cambio";
      return `${direction} ${this.formatNumber(Math.abs(delta))}% · ${improved ? "favorable" : "atención"}`;
    },
    deltaClass(card) {
      if (card.delta === null || card.delta === undefined || Number(card.delta) === 0) return "neutral";
      const improved = card.lowerIsBetter ? Number(card.delta) < 0 : Number(card.delta) > 0;
      return improved ? "positive" : "negative";
    },
    requestParams() {
      return Object.fromEntries(
        Object.entries(this.filters).filter(([, value]) => value !== "" && value !== null)
      );
    },
    async loadReport() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/maintenance/reports", { params: this.requestParams() });
        this.report = response.data;
      } catch (error) {
        this.error = error?.response?.data?.message || "No se pudieron cargar las estadísticas de Mantención.";
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
      if (!this.canExport || this.exporting || this.loading) return;
      this.exporting = "excel";
      try {
        downloadExcelWorkbook(
          `reporte-mantencion-${this.report.range?.start || "periodo"}`,
          this.exportSections,
          {
            title: "Resumen y reportes de Mantención",
            author: "Área de Mantención",
            subtitle: `Período ${this.rangeLabel}. Informe operacional interno sin costos.`,
          }
        );
      } finally {
        this.exporting = null;
      }
    },
    async exportPdf() {
      if (!this.canExport || this.exporting || this.loading) return;
      this.exporting = "pdf";
      try {
        const sections = this.exportSections
          .filter((section) => section.title !== "Detalle de órdenes de trabajo")
          .map((section) => ({ ...section, rows: (section.rows || []).slice(0, 30) }));

        sections.push({
          ...this.exportSections.find((section) => section.title === "Detalle de órdenes de trabajo"),
          rows: (this.report.work_orders || []).slice(0, 50).map((row) => [
            `OT-${row.id}`,
            this.formatDate(row.reported_at),
            this.formatDate(row.due_date),
            row.dependency,
            row.component,
            row.requested_by || "Sin solicitante",
            row.assigned_to || "Sin asignar",
            row.priority,
            row.overdue ? "Vencida" : row.status,
            row.description,
          ]),
        });

        downloadPdfReport(
          `reporte-mantencion-${this.report.range?.start || "periodo"}`,
          "Resumen y reportes de Mantención",
          `Período ${this.rangeLabel} | Comparación ${this.comparisonLabel} | Informe operacional interno sin costos`,
          sections,
          {
            pageOrientation: "landscape",
            headerText: "MANTENCIÓN · INFORME ESTADÍSTICO",
            author: "Área de Mantención",
            tableFontSize: 7,
          }
        );
      } finally {
        this.exporting = null;
      }
    },
    toneClass(value) {
      const normalized = String(value || "").toLowerCase();
      if (normalized.includes("venc") || normalized.includes("crít") || normalized.includes("anulad")) return "danger";
      if (normalized.includes("alta") || normalized.includes("espera") || normalized.includes("paus")) return "warning";
      if (normalized.includes("termin") || normalized.includes("cumpl") || normalized.includes("final")) return "success";
      if (normalized.includes("proceso") || normalized.includes("ejecución")) return "info";
      return "secondary";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="maintenance-report">
      <section class="maintenance-hero">
        <div class="maintenance-hero__content">
          <div class="maintenance-hero__eyebrow">
            <i class="bx bx-pulse"></i>
            Gestión institucional · Mantención
          </div>
          <h1>Resumen y reportes de Mantención</h1>
          <p>
            Una lectura ejecutiva de órdenes de trabajo, cumplimiento, visitas y plan anual.
            Todos los indicadores son operacionales y no incluyen costos.
          </p>
          <div class="maintenance-hero__meta">
            <span><i class="bx bx-calendar"></i>{{ rangeLabel }}</span>
            <span><i class="bx bx-history"></i>Compara con {{ comparisonLabel || "el período anterior" }}</span>
            <span><i class="bx bx-refresh"></i>Actualizado {{ formatDateTime(report.generated_at) }}</span>
          </div>
        </div>
        <div class="maintenance-hero__actions">
          <button class="report-button report-button--light" type="button" :disabled="loading || exporting" @click="loadReport">
            <i class="bx bx-refresh"></i>
            Actualizar
          </button>
          <button v-if="canExport" class="report-button report-button--excel" type="button" :disabled="loading || exporting" @click="exportExcel">
            <i class="bx bx-spreadsheet"></i>
            {{ exporting === "excel" ? "Preparando…" : "Excel" }}
          </button>
          <button v-if="canExport" class="report-button report-button--pdf" type="button" :disabled="loading || exporting" @click="exportPdf">
            <i class="bx bxs-file-pdf"></i>
            {{ exporting === "pdf" ? "Preparando…" : "PDF" }}
          </button>
        </div>
      </section>

      <nav class="maintenance-nav" aria-label="Secciones de Mantención">
        <router-link class="maintenance-nav__item active" to="/maintenance">
          <i class="bx bx-bar-chart-square"></i><span>Resumen</span>
        </router-link>
        <router-link class="maintenance-nav__item" to="/maintenance/work-orders">
          <i class="bx bx-clipboard"></i><span>Órdenes</span>
        </router-link>
        <router-link class="maintenance-nav__item" to="/maintenance/workload">
          <i class="bx bx-group"></i><span>Carga de trabajo</span>
        </router-link>
        <router-link class="maintenance-nav__item" to="/maintenance/visits">
          <i class="bx bx-calendar-check"></i><span>Visitas</span>
        </router-link>
        <router-link class="maintenance-nav__item" to="/maintenance/annual-plans">
          <i class="bx bx-calendar-event"></i><span>Plan anual</span>
        </router-link>
        <router-link class="maintenance-nav__item" to="/maintenance/dependencies">
          <i class="bx bx-buildings"></i><span>Áreas técnicas</span>
        </router-link>
      </nav>

      <section class="filter-panel">
        <div class="filter-panel__header">
          <div>
            <span class="section-kicker">Explorar información</span>
            <h2>Filtros del informe</h2>
          </div>
          <div class="filter-panel__header-actions">
            <span v-if="activeFilterCount" class="filter-count">{{ activeFilterCount }} activos</span>
            <button class="filter-link" type="button" @click="showAdvancedFilters = !showAdvancedFilters">
              {{ showAdvancedFilters ? "Ocultar filtros" : "Más filtros" }}
              <i :class="showAdvancedFilters ? 'bx bx-chevron-up' : 'bx bx-chevron-down'"></i>
            </button>
          </div>
        </div>
        <form class="filter-grid" @submit.prevent="loadReport">
          <label class="filter-field">
            <span>Período rápido</span>
            <select v-model="filters.period" class="form-select">
              <option value="diario">Hoy</option>
              <option value="semanal">Semana actual</option>
              <option value="mensual">Mes actual</option>
              <option value="semestral">Semestre actual</option>
              <option value="anual">Año actual</option>
            </select>
          </label>
          <label class="filter-field">
            <span>Desde</span>
            <input v-model="filters.start_date" class="form-control" type="date" />
          </label>
          <label class="filter-field">
            <span>Hasta</span>
            <input v-model="filters.end_date" class="form-control" type="date" />
          </label>
          <label class="filter-field">
            <span>Dependencia</span>
            <select v-model="filters.dependency_id" class="form-select">
              <option value="">Todas</option>
              <option v-for="item in report.catalogs?.dependencies || []" :key="item.id" :value="item.id">
                {{ item.code }} · {{ item.name }}
              </option>
            </select>
          </label>

          <template v-if="showAdvancedFilters">
            <label class="filter-field">
              <span>Área técnica</span>
              <select v-model="filters.technical_area_id" class="form-select">
                <option value="">Todas</option>
                <option v-for="item in report.catalogs?.technical_areas || []" :key="item.id" :value="item.id">
                  {{ item.code }} · {{ item.name }}
                </option>
              </select>
            </label>
            <label class="filter-field">
              <span>Responsable</span>
              <select v-model="filters.assignee" class="form-select">
                <option value="">Todos</option>
                <option value="Sin asignar">Sin asignar</option>
                <option v-for="item in report.catalogs?.assignees || []" :key="item" :value="item">{{ item }}</option>
              </select>
            </label>
            <label class="filter-field">
              <span>Prioridad</span>
              <select v-model="filters.priority" class="form-select">
                <option value="">Todas</option>
                <option v-for="item in report.catalogs?.priorities || []" :key="item" :value="item">{{ item }}</option>
              </select>
            </label>
            <label class="filter-field">
              <span>Estado OT</span>
              <select v-model="filters.status" class="form-select">
                <option value="">Todos</option>
                <option v-for="item in report.catalogs?.statuses || []" :key="item" :value="item">{{ item }}</option>
              </select>
            </label>
          </template>

          <div class="filter-actions">
            <button class="btn btn-light" type="button" @click="clearFilters">Limpiar</button>
            <button class="btn btn-primary" type="submit" :disabled="loading">
              <i class="bx bx-filter-alt me-1"></i>Aplicar filtros
            </button>
          </div>
        </form>
      </section>

      <div v-if="error" class="alert alert-danger report-alert" role="alert">
        <i class="bx bx-error-circle"></i>
        <div><strong>No pudimos cargar el informe.</strong><br />{{ error }}</div>
        <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="loadReport">Reintentar</button>
      </div>

      <LoadingState v-if="loading" message="Preparando indicadores de Mantención…" />

      <template v-else-if="!error">
        <section class="metric-grid" aria-label="Indicadores principales">
          <article v-for="card in summaryCards" :key="card.key" class="metric-card" :class="`metric-card--${card.tone}`">
            <div class="metric-card__top">
              <span class="metric-card__icon"><i :class="`bx ${card.icon}`"></i></span>
              <span class="metric-card__delta" :class="deltaClass(card)">
                <i :class="deltaClass(card) === 'positive' ? 'bx bx-trending-up' : deltaClass(card) === 'negative' ? 'bx bx-trending-down' : 'bx bx-minus'"></i>
                {{ deltaLabel(card) }}
              </span>
            </div>
            <span class="metric-card__label">{{ card.label }}</span>
            <strong>{{ formatNumber(card.value) }}{{ card.suffix || "" }}</strong>
            <small>{{ card.hint }}</small>
          </article>
        </section>

        <section class="workflow-panel">
          <div class="panel-heading">
            <div><span class="section-kicker">Flujo operativo</span><h2>Situación de las órdenes</h2></div>
            <span class="panel-note">La etapa vencida es parte de la carga abierta</span>
          </div>
          <div class="workflow-grid">
            <article v-for="(stage, index) in workflowStages" :key="stage.label" class="workflow-stage">
              <span class="workflow-stage__number">{{ String(index + 1).padStart(2, "0") }}</span>
              <span class="workflow-stage__icon" :class="`is-${stage.tone}`"><i :class="`bx ${stage.icon}`"></i></span>
              <div><strong>{{ formatNumber(stage.value) }}</strong><span>{{ stage.label }}</span></div>
              <em>{{ stage.share }}%</em>
            </article>
          </div>
        </section>

        <section class="chart-grid">
          <article class="report-panel report-panel--wide">
            <div class="panel-heading">
              <div><span class="section-kicker">Tendencia</span><h2>Ingreso y cierre de órdenes</h2></div>
              <span class="panel-note">{{ rangeLabel }}</span>
            </div>
            <div v-if="(report.charts?.volume_timeline || []).length" class="chart-canvas">
              <apexchart type="line" height="330" :options="timelineOptions" :series="timelineSeries" />
            </div>
            <div v-else class="empty-panel">No hay OT para representar en este período.</div>
          </article>
          <article class="report-panel">
            <div class="panel-heading">
              <div><span class="section-kicker">Composición</span><h2>Órdenes por estado</h2></div>
            </div>
            <div v-if="statusSeries.length" class="chart-canvas">
              <apexchart type="donut" height="330" :options="statusOptions" :series="statusSeries" />
            </div>
            <div v-else class="empty-panel">Sin estados para mostrar.</div>
          </article>
          <article class="report-panel">
            <div class="panel-heading">
              <div><span class="section-kicker">Criticidad</span><h2>Órdenes por prioridad</h2></div>
            </div>
            <div v-if="prioritySeries[0].data.length" class="chart-canvas">
              <apexchart type="bar" height="330" :options="priorityOptions" :series="prioritySeries" />
            </div>
            <div v-else class="empty-panel">Sin prioridades para mostrar.</div>
          </article>
        </section>

        <section class="analysis-grid">
          <article class="report-panel ranking-panel">
            <div class="panel-heading">
              <div><span class="section-kicker">Distribución</span><h2>¿Dónde se concentra la demanda?</h2></div>
            </div>
            <div class="ranking-tabs" role="tablist">
              <button
                v-for="tab in rankingTabs"
                :key="tab.key"
                type="button"
                :class="{ active: activeRanking === tab.key }"
                @click="activeRanking = tab.key"
              >
                <i :class="`bx ${tab.icon}`"></i>{{ tab.label }}
              </button>
            </div>
            <div v-if="activeRankingRows.length" class="ranking-list">
              <article v-for="(row, index) in activeRankingRows" :key="`${activeRanking}-${row.label}`" class="ranking-row">
                <span class="ranking-row__position">{{ index + 1 }}</span>
                <div class="ranking-row__content">
                  <div><strong>{{ row.label }}</strong><span>{{ row.open }} abiertas · {{ row.completed }} terminadas</span></div>
                  <div class="ranking-row__bar"><span :style="{ width: `${Math.max(3, row.share || 0)}%` }"></span></div>
                </div>
                <div class="ranking-row__metrics">
                  <strong>{{ formatNumber(row.total) }}</strong>
                  <span v-if="row.overdue" class="is-danger">{{ row.overdue }} vencidas</span>
                  <span v-else>{{ formatNumber(row.share) }}%</span>
                </div>
              </article>
            </div>
            <div v-else class="empty-panel">No hay datos para este ranking.</div>
            <p v-if="activeRanking === 'assignees'" class="data-note">
              <i class="bx bx-info-circle"></i>
              Una OT compartida cuenta como participación para cada responsable asignado.
            </p>
          </article>

          <article class="report-panel attention-panel">
            <div class="panel-heading">
              <div><span class="section-kicker">Atención prioritaria</span><h2>Órdenes que requieren seguimiento</h2></div>
              <router-link to="/maintenance/work-orders">Ver todas <i class="bx bx-right-arrow-alt"></i></router-link>
            </div>
            <div v-if="report.priority_work_orders?.length" class="attention-list">
              <article v-for="row in report.priority_work_orders" :key="row.id" class="attention-item">
                <div class="attention-item__main">
                  <div class="attention-item__title">
                    <strong>OT-{{ row.id }}</strong>
                    <span class="status-pill" :class="`status-pill--${toneClass(row.overdue ? 'Vencida' : row.priority)}`">
                      {{ row.overdue ? "Vencida" : row.priority }}
                    </span>
                  </div>
                  <p>{{ row.description }}</p>
                  <span><i class="bx bx-map"></i>{{ row.dependency }}</span>
                </div>
                <div class="attention-item__side">
                  <strong>{{ row.assigned_to || "Sin asignar" }}</strong>
                  <span>Vence {{ formatDate(row.due_date) }}</span>
                  <span class="status-pill" :class="`status-pill--${toneClass(row.status)}`">{{ row.status }}</span>
                </div>
              </article>
            </div>
            <div v-else class="empty-panel empty-panel--success">
              <i class="bx bx-check-shield"></i>
              No hay órdenes abiertas prioritarias para los filtros seleccionados.
            </div>
          </article>
        </section>

        <section class="program-grid">
          <article class="report-panel">
            <div class="panel-heading">
              <div><span class="section-kicker">Trabajo en terreno</span><h2>Visitas de mantención</h2></div>
              <router-link to="/maintenance/visits">Gestionar <i class="bx bx-right-arrow-alt"></i></router-link>
            </div>
            <div class="mini-kpis">
              <div><strong>{{ formatNumber(report.visits?.summary?.total) }}</strong><span>Visitas</span></div>
              <div><strong>{{ formatNumber(report.visits?.summary?.completed) }}</strong><span>Finalizadas</span></div>
              <div><strong>{{ formatNumber(report.visits?.summary?.in_progress) }}</strong><span>En progreso</span></div>
              <div><strong>{{ formatNumber(report.visits?.summary?.completion_rate) }}%</strong><span>Cumplimiento</span></div>
            </div>
            <div class="compact-table-wrap">
              <table class="compact-table">
                <thead><tr><th>Fecha</th><th>Dependencia</th><th>Responsable</th><th>Estado</th></tr></thead>
                <tbody>
                  <tr v-for="row in (report.visits?.rows || []).slice(0, 7)" :key="row.id">
                    <td>{{ formatDate(row.visit_date) }}</td>
                    <td>{{ row.dependency }}</td>
                    <td>{{ row.responsible }}</td>
                    <td><span class="status-pill" :class="`status-pill--${toneClass(row.status)}`">{{ row.status }}</span></td>
                  </tr>
                  <tr v-if="!(report.visits?.rows || []).length"><td colspan="4" class="empty-cell">Sin visitas en el período.</td></tr>
                </tbody>
              </table>
            </div>
          </article>

          <article class="report-panel">
            <div class="panel-heading">
              <div><span class="section-kicker">Prevención</span><h2>Plan anual de mantención</h2></div>
              <router-link to="/maintenance/annual-plans">Gestionar <i class="bx bx-right-arrow-alt"></i></router-link>
            </div>
            <div class="mini-kpis">
              <div><strong>{{ formatNumber(report.annual_plan?.summary?.total) }}</strong><span>Actividades</span></div>
              <div><strong>{{ formatNumber(report.annual_plan?.summary?.completed) }}</strong><span>Cumplidas</span></div>
              <div class="is-danger"><strong>{{ formatNumber(report.annual_plan?.summary?.overdue) }}</strong><span>Vencidas</span></div>
              <div><strong>{{ formatNumber(report.annual_plan?.summary?.completion_rate) }}%</strong><span>Cumplimiento</span></div>
            </div>
            <div class="compact-table-wrap">
              <table class="compact-table">
                <thead><tr><th>Fecha</th><th>Actividad</th><th>Responsable</th><th>Estado</th></tr></thead>
                <tbody>
                  <tr v-for="row in (report.annual_plan?.rows || []).slice(0, 7)" :key="row.id">
                    <td>{{ formatDate(row.scheduled_date || row.planned_date) }}</td>
                    <td>{{ row.title }}</td>
                    <td>{{ row.responsible }}</td>
                    <td><span class="status-pill" :class="`status-pill--${toneClass(row.overdue ? 'Vencida' : row.status)}`">{{ row.overdue ? "Vencida" : row.status }}</span></td>
                  </tr>
                  <tr v-if="!(report.annual_plan?.rows || []).length"><td colspan="4" class="empty-cell">Sin actividades en el período.</td></tr>
                </tbody>
              </table>
            </div>
          </article>
        </section>

        <section class="quick-access">
          <div><span class="section-kicker">Accesos rápidos</span><h2>Continúa con la gestión operativa</h2></div>
          <div class="quick-access__links">
            <router-link to="/maintenance/work-orders"><i class="bx bx-plus-circle"></i><span><strong>Órdenes de trabajo</strong><small>Registrar y gestionar OT</small></span></router-link>
            <router-link to="/maintenance/workload"><i class="bx bx-group"></i><span><strong>Carga por responsable</strong><small>Informe detallado de asignaciones</small></span></router-link>
            <router-link to="/maintenance/visits"><i class="bx bx-calendar-check"></i><span><strong>Planificar visitas</strong><small>Agenda y checklist técnico</small></span></router-link>
            <router-link to="/maintenance/annual-plans"><i class="bx bx-calendar-event"></i><span><strong>Plan anual</strong><small>Mantenciones preventivas</small></span></router-link>
          </div>
        </section>

        <p class="report-footnote">
          <i class="bx bx-shield-quarter"></i>
          Informe operacional de uso interno. No calcula ni presenta costos. Las consultas y exportaciones son de solo lectura.
        </p>
      </template>
    </div>
  </Layout>
</template>

<style scoped>
.maintenance-report {
  --ink: #20283a;
  --muted: #667085;
  --line: #e6eaf1;
  --surface: #fff;
  --canvas: #f5f7fb;
  --primary: #405189;
  --primary-soft: #eef1fb;
  --success: #34c38f;
  --danger: #f46a6a;
  --warning: #f1b44c;
  padding: 24px 0 48px;
  color: var(--ink);
}

.maintenance-hero {
  position: relative;
  display: flex;
  justify-content: space-between;
  gap: 30px;
  overflow: hidden;
  margin-bottom: 18px;
  padding: 34px 38px;
  border-radius: 22px;
  color: #fff;
  background:
    radial-gradient(circle at 82% 10%, rgba(80, 165, 241, .38), transparent 28%),
    radial-gradient(circle at 98% 100%, rgba(52, 195, 143, .2), transparent 30%),
    linear-gradient(128deg, #253458 0%, #405189 58%, #5269a8 100%);
  box-shadow: 0 18px 46px rgba(42, 48, 66, .17);
}

.maintenance-hero::after {
  content: "";
  position: absolute;
  width: 260px;
  height: 260px;
  right: -80px;
  top: -110px;
  border: 45px solid rgba(255, 255, 255, .05);
  border-radius: 50%;
}

.maintenance-hero__content,
.maintenance-hero__actions { position: relative; z-index: 1; }
.maintenance-hero__content { max-width: 760px; }

.maintenance-hero__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 11px;
  border: 1px solid rgba(255, 255, 255, .2);
  border-radius: 999px;
  background: rgba(255, 255, 255, .1);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
}

.maintenance-hero h1 { margin: 14px 0 8px; color: #fff; font-size: clamp(28px, 3vw, 40px); font-weight: 750; letter-spacing: -.035em; }
.maintenance-hero p { max-width: 700px; margin: 0; color: rgba(255, 255, 255, .8); font-size: 15px; line-height: 1.65; }
.maintenance-hero__meta { display: flex; flex-wrap: wrap; gap: 9px 19px; margin-top: 20px; color: rgba(255, 255, 255, .76); font-size: 12px; }
.maintenance-hero__meta span { display: flex; align-items: center; gap: 6px; }
.maintenance-hero__actions { display: flex; align-items: flex-start; flex-wrap: wrap; justify-content: flex-end; gap: 9px; min-width: 310px; }

.report-button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 40px;
  padding: 0 15px;
  border: 1px solid transparent;
  border-radius: 11px;
  font-weight: 650;
  transition: transform .2s ease, box-shadow .2s ease;
}
.report-button:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0, 0, 0, .14); }
.report-button:disabled { opacity: .6; }
.report-button--light { color: #fff; border-color: rgba(255, 255, 255, .26); background: rgba(255, 255, 255, .12); }
.report-button--excel { color: #197253; background: #e8f8f1; }
.report-button--pdf { color: #b23838; background: #fff0f0; }

.maintenance-nav {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  margin-bottom: 18px;
  padding: 7px;
  border: 1px solid var(--line);
  border-radius: 15px;
  background: var(--surface);
  box-shadow: 0 6px 22px rgba(42, 48, 66, .05);
}
.maintenance-nav__item {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  flex: 1 0 auto;
  min-height: 42px;
  padding: 0 14px;
  border-radius: 10px;
  color: #5e6676;
  font-size: 13px;
  font-weight: 650;
}
.maintenance-nav__item:hover { color: var(--primary); background: #f5f7fc; }
.maintenance-nav__item.active { color: #fff; background: var(--primary); box-shadow: 0 7px 16px rgba(64, 81, 137, .22); }
.maintenance-nav__item i { font-size: 18px; }

.filter-panel,
.report-panel,
.workflow-panel,
.quick-access {
  border: 1px solid var(--line);
  border-radius: 17px;
  background: var(--surface);
  box-shadow: 0 7px 24px rgba(42, 48, 66, .055);
}
.filter-panel { margin-bottom: 18px; padding: 20px 22px; }
.filter-panel__header,
.panel-heading {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}
.filter-panel__header { margin-bottom: 16px; }
.filter-panel__header h2,
.panel-heading h2,
.quick-access h2 { margin: 3px 0 0; color: var(--ink); font-size: 18px; font-weight: 720; }
.section-kicker { color: var(--primary); font-size: 10px; font-weight: 800; letter-spacing: .095em; text-transform: uppercase; }
.filter-panel__header-actions { display: flex; align-items: center; gap: 12px; }
.filter-count { padding: 4px 8px; border-radius: 999px; color: var(--primary); background: var(--primary-soft); font-size: 11px; font-weight: 700; }
.filter-link { display: inline-flex; align-items: center; gap: 5px; padding: 0; border: 0; color: var(--primary); background: transparent; font-size: 12px; font-weight: 700; }
.filter-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 14px; }
.filter-field { margin: 0; }
.filter-field > span { display: block; margin-bottom: 6px; color: #535c6e; font-size: 11px; font-weight: 700; }
.filter-field .form-control,
.filter-field .form-select { min-height: 41px; border-color: #dfe4ec; border-radius: 9px; color: #3d4657; font-size: 12px; }
.filter-actions { display: flex; align-items: flex-end; justify-content: flex-end; gap: 8px; grid-column: 1 / -1; }
.filter-actions .btn { min-height: 39px; padding-inline: 17px; border-radius: 9px; font-size: 12px; font-weight: 650; }
.report-alert { display: flex; align-items: center; gap: 11px; border-radius: 13px; }
.report-alert > i { font-size: 25px; }

.metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.metric-card {
  position: relative;
  overflow: hidden;
  min-height: 176px;
  padding: 19px;
  border: 1px solid var(--line);
  border-radius: 17px;
  background: #fff;
  box-shadow: 0 7px 24px rgba(42, 48, 66, .055);
}
.metric-card::after { content: ""; position: absolute; inset: auto -34px -52px auto; width: 120px; height: 120px; border-radius: 50%; background: currentColor; opacity: .045; }
.metric-card__top { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 15px; }
.metric-card__icon { display: grid; place-items: center; width: 39px; height: 39px; border-radius: 12px; color: currentColor; background: currentColor; }
.metric-card__icon i { color: inherit; filter: brightness(0) invert(1); font-size: 20px; }
.metric-card__delta { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 700; }
.metric-card__delta.positive { color: #17855f; }
.metric-card__delta.negative { color: #d94b4b; }
.metric-card__delta.neutral { color: #8a93a4; }
.metric-card__label { display: block; color: var(--muted); font-size: 12px; font-weight: 650; }
.metric-card > strong { display: block; margin: 3px 0; color: var(--ink); font-size: 29px; line-height: 1.15; letter-spacing: -.025em; }
.metric-card > small { color: #8a93a4; font-size: 11px; }
.metric-card--primary { color: #405189; }
.metric-card--success { color: #34c38f; }
.metric-card--warning { color: #e5a525; }
.metric-card--danger { color: #f46a6a; }
.metric-card--info { color: #50a5f1; }

.workflow-panel { margin-bottom: 18px; padding: 20px 22px; }
.panel-note { color: #8a93a4; font-size: 11px; }
.workflow-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0; margin-top: 20px; }
.workflow-stage { position: relative; display: flex; align-items: center; gap: 12px; padding: 12px 24px 12px 12px; }
.workflow-stage:not(:last-child)::after { content: ""; position: absolute; right: -1px; width: 1px; height: 45px; background: var(--line); }
.workflow-stage__number { position: absolute; top: -8px; left: 8px; color: #e4e8f0; font-size: 32px; font-weight: 800; }
.workflow-stage__icon { z-index: 1; display: grid; place-items: center; flex: 0 0 44px; height: 44px; border-radius: 13px; font-size: 21px; }
.workflow-stage__icon.is-primary { color: #405189; background: #eef1fb; }
.workflow-stage__icon.is-warning { color: #b97b09; background: #fff7e8; }
.workflow-stage__icon.is-danger { color: #cf4444; background: #fff0f0; }
.workflow-stage__icon.is-success { color: #18865f; background: #e9f8f2; }
.workflow-stage div { z-index: 1; display: flex; flex-direction: column; }
.workflow-stage div strong { color: var(--ink); font-size: 22px; }
.workflow-stage div span { color: var(--muted); font-size: 11px; }
.workflow-stage em { margin-left: auto; color: #9aa2b1; font-size: 11px; font-style: normal; }

.chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px; }
.report-panel { min-width: 0; padding: 20px 22px; }
.report-panel--wide { grid-column: 1 / -1; }
.panel-heading { margin-bottom: 12px; }
.panel-heading a { display: inline-flex; align-items: center; gap: 3px; color: var(--primary); font-size: 11px; font-weight: 700; }
.chart-canvas { min-height: 320px; }
.empty-panel { display: grid; place-items: center; min-height: 220px; color: #8b94a5; text-align: center; }
.empty-panel--success { gap: 7px; color: #278964; }
.empty-panel--success i { font-size: 34px; }

.analysis-grid { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: 18px; margin-bottom: 18px; }
.ranking-tabs { display: flex; gap: 6px; overflow-x: auto; padding: 5px; border-radius: 11px; background: #f4f6fa; }
.ranking-tabs button { display: inline-flex; align-items: center; gap: 5px; flex: 1 0 auto; justify-content: center; min-height: 35px; padding: 0 10px; border: 0; border-radius: 8px; color: #667085; background: transparent; font-size: 10px; font-weight: 700; }
.ranking-tabs button.active { color: var(--primary); background: #fff; box-shadow: 0 3px 9px rgba(42, 48, 66, .09); }
.ranking-list { margin-top: 12px; }
.ranking-row { display: flex; align-items: center; gap: 11px; padding: 10px 2px; border-bottom: 1px solid #eef1f5; }
.ranking-row:last-child { border-bottom: 0; }
.ranking-row__position { display: grid; place-items: center; flex: 0 0 29px; height: 29px; border-radius: 8px; color: var(--primary); background: var(--primary-soft); font-size: 11px; font-weight: 800; }
.ranking-row__content { min-width: 0; flex: 1; }
.ranking-row__content > div:first-child { display: flex; justify-content: space-between; gap: 8px; }
.ranking-row__content strong { overflow: hidden; color: #30384a; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.ranking-row__content span { color: #939baa; font-size: 9px; white-space: nowrap; }
.ranking-row__bar { overflow: hidden; height: 5px; margin-top: 6px; border-radius: 99px; background: #edf0f5; }
.ranking-row__bar span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #405189, #50a5f1); }
.ranking-row__metrics { display: flex; flex-direction: column; align-items: flex-end; flex: 0 0 68px; }
.ranking-row__metrics strong { color: var(--ink); font-size: 14px; }
.ranking-row__metrics span { color: #939baa; font-size: 9px; }
.ranking-row__metrics .is-danger { color: #df5353; }
.data-note { display: flex; gap: 6px; margin: 12px 0 0; padding: 9px 10px; border-radius: 9px; color: #6c7484; background: #f7f8fb; font-size: 10px; }

.attention-list { max-height: 618px; overflow: auto; padding-right: 2px; }
.attention-item { display: flex; justify-content: space-between; gap: 15px; padding: 13px 2px; border-bottom: 1px solid #edf0f4; }
.attention-item:last-child { border-bottom: 0; }
.attention-item__main { min-width: 0; flex: 1; }
.attention-item__title { display: flex; align-items: center; gap: 7px; }
.attention-item__title strong { color: var(--primary); font-size: 11px; }
.attention-item p { display: -webkit-box; overflow: hidden; margin: 6px 0; color: #3f4757; font-size: 11px; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.attention-item__main > span { display: block; overflow: hidden; color: #8b94a4; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.attention-item__main > span i { margin-right: 4px; }
.attention-item__side { display: flex; flex: 0 0 130px; flex-direction: column; align-items: flex-end; gap: 5px; text-align: right; }
.attention-item__side strong { color: #50596a; font-size: 10px; }
.attention-item__side > span:not(.status-pill) { color: #969eac; font-size: 9px; }
.status-pill { display: inline-flex; align-items: center; min-height: 21px; padding: 2px 7px; border-radius: 999px; font-size: 9px; font-weight: 750; white-space: nowrap; }
.status-pill--danger { color: #c63e3e; background: #fff0f0; }
.status-pill--warning { color: #a96f00; background: #fff6e4; }
.status-pill--success { color: #187657; background: #e9f8f2; }
.status-pill--info { color: #2d77b8; background: #edf6ff; }
.status-pill--secondary { color: #667085; background: #f0f2f5; }

.program-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px; }
.mini-kpis { display: grid; grid-template-columns: repeat(4, 1fr); overflow: hidden; margin: 10px 0 15px; border: 1px solid var(--line); border-radius: 11px; }
.mini-kpis > div { display: flex; flex-direction: column; padding: 10px 12px; border-right: 1px solid var(--line); }
.mini-kpis > div:last-child { border-right: 0; }
.mini-kpis strong { color: var(--ink); font-size: 17px; }
.mini-kpis span { color: #8b94a4; font-size: 9px; }
.mini-kpis .is-danger strong { color: #da4f4f; }
.compact-table-wrap { overflow-x: auto; }
.compact-table { width: 100%; border-collapse: collapse; }
.compact-table th { padding: 8px 7px; border-bottom: 1px solid #dfe4ec; color: #7b8494; font-size: 9px; font-weight: 750; letter-spacing: .03em; text-align: left; text-transform: uppercase; white-space: nowrap; }
.compact-table td { max-width: 180px; padding: 10px 7px; border-bottom: 1px solid #eef1f5; color: #4a5364; font-size: 10px; vertical-align: middle; }
.compact-table td:nth-child(2) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.compact-table tr:last-child td { border-bottom: 0; }
.compact-table .empty-cell { padding: 28px; color: #929aa9; text-align: center; }

.quick-access { display: flex; align-items: center; justify-content: space-between; gap: 25px; padding: 22px; }
.quick-access__links { display: grid; grid-template-columns: repeat(4, minmax(145px, 1fr)); gap: 9px; flex: 1; max-width: 890px; }
.quick-access__links a { display: flex; align-items: center; gap: 9px; min-height: 58px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 11px; color: #3e4758; background: #fafbfc; }
.quick-access__links a:hover { border-color: #bdc8e3; background: #f4f6fb; transform: translateY(-1px); }
.quick-access__links i { color: var(--primary); font-size: 22px; }
.quick-access__links span { display: flex; flex-direction: column; }
.quick-access__links strong { font-size: 10px; }
.quick-access__links small { margin-top: 2px; color: #8e97a7; font-size: 8px; }
.report-footnote { display: flex; justify-content: center; align-items: center; gap: 7px; margin: 16px 0 0; color: #8b94a4; font-size: 10px; }

@media (max-width: 1199.98px) {
  .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .filter-grid { grid-template-columns: repeat(2, minmax(160px, 1fr)); }
  .analysis-grid,
  .program-grid { grid-template-columns: 1fr; }
  .quick-access { align-items: flex-start; flex-direction: column; }
  .quick-access__links { width: 100%; max-width: none; }
}

@media (max-width: 767.98px) {
  .maintenance-report { padding-top: 14px; }
  .maintenance-hero { flex-direction: column; padding: 25px 22px; border-radius: 17px; }
  .maintenance-hero__actions { justify-content: flex-start; min-width: 0; }
  .maintenance-hero__actions .report-button { flex: 1; justify-content: center; }
  .metric-grid,
  .chart-grid,
  .filter-grid { grid-template-columns: 1fr; }
  .report-panel--wide { grid-column: auto; }
  .workflow-grid { grid-template-columns: 1fr 1fr; }
  .workflow-stage:nth-child(2)::after { display: none; }
  .filter-panel__header { flex-direction: column; }
  .filter-actions { justify-content: stretch; }
  .filter-actions .btn { flex: 1; }
  .quick-access__links { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 479.98px) {
  .metric-grid,
  .workflow-grid,
  .quick-access__links { grid-template-columns: 1fr; }
  .workflow-stage::after { display: none; }
  .metric-card { min-height: 160px; }
  .report-panel,
  .filter-panel,
  .workflow-panel { padding: 17px; }
  .mini-kpis { grid-template-columns: 1fr 1fr; }
  .mini-kpis > div:nth-child(2) { border-right: 0; }
  .mini-kpis > div:nth-child(-n + 2) { border-bottom: 1px solid var(--line); }
  .attention-item { flex-direction: column; }
  .attention-item__side { align-items: flex-start; flex-basis: auto; text-align: left; }
}
</style>
