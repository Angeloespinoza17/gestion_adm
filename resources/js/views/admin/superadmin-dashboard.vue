<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
  basicAdminChartOptions,
  dashboardStatusVariant,
  downloadExcelWorkbook,
  downloadPdfReport,
  formatAdminDateTime,
  formatAdminReportCell,
  humanizeAdminStatus,
  printAdminHtml,
} from "../../components/admin/module-utils";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      reportLoading: false,
      error: null,
      dashboard: null,
      report: null,
      selectedModuleSlug: null,
      filters: {
        period_days: 30,
        report_scope: "general",
        module_slug: null,
        status: "all",
        search: "",
      },
      periodOptions: [
        { value: 7, text: "7 días" },
        { value: 30, text: "30 días" },
        { value: 60, text: "60 días" },
        { value: 90, text: "90 días" },
        { value: 180, text: "180 días" },
        { value: 365, text: "365 días" },
      ],
      scopeOptions: [
        { value: "general", text: "Reporte general" },
        { value: "module", text: "Reporte por módulo" },
      ],
      statusOptions: [
        { value: "all", text: "Todos los estados" },
        { value: "operativo", text: "Activo" },
        { value: "en_revision", text: "En riesgo" },
        { value: "requiere_atencion", text: "Crítico" },
        { value: "sin_datos", text: "Sin datos" },
      ],
    };
  },
  computed: {
    metrics() {
      return this.dashboard?.metrics || {};
    },
    modules() {
      return this.dashboard?.modules || [];
    },
    filteredModules() {
      const search = this.normalizeText(this.filters.search);

      return this.modules.filter((module) => {
        const matchesModule = !this.filters.module_slug || module.slug === this.filters.module_slug;
        const matchesStatus = this.filters.status === "all" || module.health_status === this.filters.status;
        const content = this.normalizeText(`${module.name || ""} ${module.report_summary || ""} ${module.report_title || ""}`);
        const matchesSearch = !search || content.includes(search);

        return matchesModule && matchesStatus && matchesSearch;
      });
    },
    selectedModule() {
      if (!this.filteredModules.length && (this.filters.module_slug || this.filters.status !== "all" || this.filters.search)) {
        return null;
      }

      return (
        this.filteredModules.find((module) => module.slug === this.selectedModuleSlug) ||
        this.filteredModules[0] ||
        this.modules.find((module) => module.slug === this.selectedModuleSlug) ||
        this.modules[0] ||
        null
      );
    },
    selectedOperationalSections() {
      return this.selectedModule?.operational_sections || [];
    },
    selectedReportMetrics() {
      return this.selectedModule?.report_metrics || [];
    },
    moduleOptions() {
      return [{ value: null, text: "Todos los módulos" }].concat(
        this.modules.map((module) => ({ value: module.slug, text: module.name }))
      );
    },
    filteredMetrics() {
      const quickMetrics = this.filteredModules.flatMap((module) => module.report_metrics || []);

      return {
        modules_count: this.filteredModules.length,
        modules_active: this.filteredModules.filter((module) => module.health_status === "operativo").length,
        modules_with_reports: this.filteredModules.filter((module) => (module.report_metrics || []).length > 0).length,
        today_activity_total: quickMetrics
          .filter((metric) => metric.scope === "today")
          .reduce((total, metric) => total + Number(metric.value || 0), 0),
        follow_up_total: quickMetrics
          .filter((metric) => metric.scope === "follow_up")
          .reduce((total, metric) => total + Number(metric.value || 0), 0),
        quick_indicators_total: quickMetrics.length,
        operational_sections_total: this.filteredModules.reduce((total, module) => total + (module.operational_sections || []).length, 0),
        alert_total: this.filteredModules.reduce((total, module) => total + Number(module.metrics?.alert_records || 0), 0),
      };
    },
    metricCards() {
      return [
        {
          label: "Áreas con informe",
          value: this.formatNumber(this.filteredMetrics.modules_with_reports),
          detail: `${this.formatNumber(this.filteredMetrics.modules_active)} de ${this.formatNumber(this.filteredMetrics.modules_count)} módulos activos`,
          icon: "bx-grid-alt",
          variant: "primary",
        },
        {
          label: "Actividad de hoy",
          value: this.formatNumber(this.filteredMetrics.today_activity_total),
          detail: "Indicadores diarios consolidados",
          icon: "bx-pulse",
          variant: "success",
        },
        {
          label: "Seguimiento",
          value: this.formatNumber(this.filteredMetrics.follow_up_total),
          detail: "Pendientes, abiertos o urgentes",
          icon: "bx-time-five",
          variant: "warning",
        },
        {
          label: "Indicadores",
          value: this.formatNumber(this.filteredMetrics.quick_indicators_total),
          detail: "Métricas de gestión por módulo",
          icon: "bx-bar-chart-alt-2",
          variant: "info",
        },
        {
          label: "Secciones de detalle",
          value: this.formatNumber(this.filteredMetrics.operational_sections_total),
          detail: "Bloques de información por área",
          icon: "bx-layout",
          variant: "secondary",
        },
      ];
    },
    activityChartRows() {
      return this.filteredModules
        .map((module) => ({
          label: module.name,
          today: (module.report_metrics || [])
            .filter((metric) => metric.scope === "today")
            .reduce((total, metric) => total + Number(metric.value || 0), 0),
          follow_up: (module.report_metrics || [])
            .filter((metric) => metric.scope === "follow_up")
            .reduce((total, metric) => total + Number(metric.value || 0), 0),
        }))
        .filter((row) => row.today > 0 || row.follow_up > 0)
        .slice(0, 12);
    },
    activityChartOptions() {
      return basicAdminChartOptions({
        categories: this.activityChartRows.map((row) => row.label),
        horizontal: true,
        colors: ["#34c38f", "#f1b44c"],
      });
    },
    activityChartSeries() {
      return [
        { name: "Actividad hoy", data: this.activityChartRows.map((row) => row.today) },
        { name: "Seguimiento", data: this.activityChartRows.map((row) => row.follow_up) },
      ];
    },
    statusChartOptions() {
      return {
        chart: { fontFamily: "inherit" },
        labels: this.statusOptions.filter((option) => option.value !== "all").map((option) => option.text),
        colors: ["#34c38f", "#f1b44c", "#f46a6a", "#74788d"],
        legend: { position: "bottom" },
        dataLabels: { enabled: false },
      };
    },
    statusChartSeries() {
      return ["operativo", "en_revision", "requiere_atencion", "sin_datos"].map(
        (status) => this.filteredModules.filter((module) => module.health_status === status).length
      );
    },
    selectedMetricChartOptions() {
      return basicAdminChartOptions({
        categories: this.selectedReportMetrics.map((metric) => metric.label),
        horizontal: true,
        colors: ["#556ee6"],
      });
    },
    selectedMetricChartSeries() {
      return [{ name: "Valor", data: this.selectedReportMetrics.map((metric) => Number(metric.value || 0)) }];
    },
    reportFileName() {
      if (this.report?.scope === "module") {
        return `reporte-superadmin-${this.report.module?.slug || "modulo"}`;
      }

      return "reporte-superadmin-general";
    },
  },
  mounted() {
    this.loadDashboard();
  },
  methods: {
    async loadDashboard() {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/admin/dashboard", {
          params: { period_days: this.filters.period_days },
        });
        this.dashboard = response.data;

        if (this.filters.module_slug) {
          this.selectedModuleSlug = this.filters.module_slug;
        } else if (!this.selectedModuleSlug && this.modules.length > 0) {
          this.selectedModuleSlug = this.modules[0].slug;
        }

        await this.generateReport(false);
      } catch (error) {
        this.error = this.formatError(error, "No se pudo cargar el dashboard de gestión.");
      } finally {
        this.loading = false;
      }
    },
    async generateReport(showLoader = true) {
      if (this.filters.report_scope === "module" && !this.filters.module_slug) {
        this.filters.module_slug = this.selectedModule?.slug || null;
      }

      if (this.filters.report_scope === "module" && !this.filters.module_slug) {
        this.error = "Selecciona un módulo para generar el reporte por módulo.";
        return;
      }

      this.reportLoading = showLoader;
      this.error = null;

      try {
        const moduleSlug =
          this.filters.report_scope === "module"
            ? this.filters.module_slug || this.selectedModule?.slug || null
            : this.filters.module_slug || null;

        const response = await axios.get("/api/admin/dashboard/report", {
          params: {
            scope: this.filters.report_scope,
            module_slug: moduleSlug,
            status: this.filters.status !== "all" ? this.filters.status : null,
            search: this.filters.search || null,
            period_days: this.filters.period_days,
          },
        });
        this.report = response.data;
      } catch (error) {
        this.error = this.formatError(error, "No se pudo generar el reporte.");
      } finally {
        this.reportLoading = false;
      }
    },
    selectModule(module) {
      this.selectedModuleSlug = module.slug;

      if (this.filters.report_scope === "module") {
        this.filters.module_slug = module.slug;
      }
    },
    handleModuleFilter() {
      if (this.filters.module_slug) {
        this.selectedModuleSlug = this.filters.module_slug;
      } else if (!this.filteredModules.some((module) => module.slug === this.selectedModuleSlug)) {
        this.selectedModuleSlug = this.filteredModules[0]?.slug || this.modules[0]?.slug || null;
      }
    },
    clearFilters() {
      this.filters.module_slug = null;
      this.filters.status = "all";
      this.filters.search = "";
      this.selectedModuleSlug = this.modules[0]?.slug || null;
      this.generateReport();
    },
    useModuleReport(module) {
      this.selectModule(module);
      this.filters.report_scope = "module";
      this.filters.module_slug = module.slug;
      this.generateReport();
    },
    openModule(module) {
      if (module.route) {
        this.$router.push(module.route);
      }
    },
    openRoute(route) {
      if (route) {
        this.$router.push(route);
      }
    },
    exportExcel() {
      if (!this.report?.sections?.length) return;

      downloadExcelWorkbook(this.reportFileName, this.report.sections);
    },
    async exportPdf() {
      if (!this.report?.sections?.length) return;

      const images = await this.chartImagesForReport(this.report.scope);

      downloadPdfReport(this.reportFileName, this.report.title, this.report.subtitle, this.report.sections, {
        ...this.reportPdfContext(this.report),
        images,
      });
    },
    async exportReportPdf(scope, moduleSlug = null) {
      this.reportLoading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/admin/dashboard/report", {
          params: {
            scope,
            module_slug: scope === "module" ? moduleSlug || this.selectedModule?.slug : this.filters.module_slug || null,
            status: scope === "general" && this.filters.status !== "all" ? this.filters.status : null,
            search: scope === "general" ? this.filters.search || null : null,
            period_days: this.filters.period_days,
          },
        });
        this.report = response.data;
        const images = await this.chartImagesForReport(scope);

        downloadPdfReport(
          scope === "module"
            ? `reporte-superadmin-${this.report.module?.slug || moduleSlug || "modulo"}`
            : "reporte-superadmin-general",
          this.report.title,
          this.report.subtitle,
          this.report.sections,
          {
            ...this.reportPdfContext(this.report),
            images,
          }
        );
      } catch (error) {
        this.error = this.formatError(error, "No se pudo exportar el PDF.");
      } finally {
        this.reportLoading = false;
      }
    },
    printReport() {
      if (!this.report?.sections?.length) return;

      const sections = this.report.sections
        .map((section) => {
          const headers = section.headers?.length
            ? `<tr>${section.headers.map((header) => `<th>${this.escapeHtml(this.formatReportCell(header))}</th>`).join("")}</tr>`
            : "";
          const rows = (section.rows || [])
            .map((row) => `<tr>${row.map((cell) => `<td>${this.escapeHtml(this.formatReportCell(cell))}</td>`).join("")}</tr>`)
            .join("");

          return `<h2>${this.escapeHtml(section.title)}</h2><table>${headers}${rows}</table>`;
        })
        .join("");

      printAdminHtml(
        this.report.title,
        `<h1>${this.escapeHtml(this.report.title)}</h1><div class="muted">${this.escapeHtml(this.report.subtitle || "")}</div>${sections}`,
      );
    },
    reportPdfContext(report) {
      const moduleLabel =
        report?.scope === "module"
          ? report.module?.name || this.selectedModule?.name || "Módulo seleccionado"
          : this.filters.module_slug
            ? this.modules.find((module) => module.slug === this.filters.module_slug)?.name || "Módulo filtrado"
            : "Todos";

      return {
        generatedAt: report?.meta?.generated_at || new Date().toISOString(),
        filters: [
          ["Período", `${this.filters.period_days} días`],
          ["Módulo", moduleLabel],
          ["Estado", this.filters.status === "all" ? "Todos" : this.statusLabel(this.filters.status)],
          ["Búsqueda", this.filters.search || "Sin búsqueda"],
        ],
      };
    },
    async chartImagesForReport(scope) {
      await this.$nextTick();

      const refs =
        scope === "module"
          ? [{ ref: "selectedMetricChart", title: "Gráfico de indicadores del módulo" }]
          : [
              { ref: "activityChart", title: "Actividad y seguimiento por módulo" },
              { ref: "statusChart", title: "Distribución por estado general" },
            ];

      const images = [];

      for (const item of refs) {
        const chart = this.$refs[item.ref];

        if (!chart?.dataURI) continue;

        try {
          const result = await chart.dataURI({ scale: 2 });

          if (result?.imgURI) {
            images.push({ title: item.title, dataUri: result.imgURI });
          }
        } catch (_error) {
          // La exportación del reporte debe seguir funcionando aunque un gráfico no pueda capturarse.
        }
      }

      return images;
    },
    statusVariant(status) {
      return dashboardStatusVariant(status);
    },
    rowStatusVariant(status) {
      const value = String(status || "").toLowerCase();

      if (["urgente", "critico", "crítico", "vencido", "vencida", "alta"].some((item) => value.includes(item))) {
        return "danger";
      }

      if (["pendiente", "solicitada", "borrador", "observado", "por_vencer"].some((item) => value.includes(item))) {
        return "warning";
      }

      if (["en_curso", "en curso", "revision", "revisión", "abierta", "abierto"].some((item) => value.includes(item))) {
        return "info";
      }

      if (["completado", "cerrado", "resuelto", "devuelto", "aprobado", "finalizado"].some((item) => value.includes(item))) {
        return "success";
      }

      return "secondary";
    },
    humanize(status) {
      return humanizeAdminStatus(status);
    },
    statusLabel(status) {
      const labels = {
        operativo: "Activo",
        en_revision: "En riesgo",
        requiere_atencion: "Crítico",
        sin_datos: "Sin datos",
      };

      return labels[status] || this.humanize(status);
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatDateTime(value) {
      return formatAdminDateTime(value);
    },
    formatReportCell(value) {
      return formatAdminReportCell(value);
    },
    normalizeText(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
    },
    formatError(error, fallback) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        fallback
      );
    },
    escapeHtml(value) {
      return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    },
  },
};
</script>

<template>
  <Layout>
    <div class="superadmin-dashboard">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
          <div class="text-muted small mb-1">Gestión de superadmin</div>
          <h4 class="mb-1">Dashboard de informes operativos</h4>
          <div class="text-muted">
            Informes rápidos por área: qué pasó hoy, qué requiere seguimiento y quién registró actividad.
          </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <BFormSelect v-model="filters.period_days" :options="periodOptions" class="superadmin-dashboard__period" @change="loadDashboard" />
          <BButton variant="outline-secondary" :disabled="loading" @click="loadDashboard">
            <i class="bx bx-refresh"></i>
            Actualizar
          </BButton>
          <BButton variant="primary" :disabled="reportLoading" @click="generateReport">
            <i class="bx bx-file"></i>
            Generar reporte
          </BButton>
          <BButton variant="outline-danger" :disabled="reportLoading || !filteredModules.length" @click="exportReportPdf('general')">
            <i class="bx bx-download"></i>
            PDF macro
          </BButton>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <LoadingState v-if="loading && !dashboard" message="Cargando dashboard de gestión..." />

      <template v-else-if="dashboard">
        <BCard class="border-0 shadow-sm mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Módulo</label>
              <BFormSelect v-model="filters.module_slug" :options="moduleOptions" @change="handleModuleFilter" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="filters.status" :options="statusOptions" @change="handleModuleFilter" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" placeholder="Buscar por módulo o resumen ejecutivo" @input="handleModuleFilter" />
            </div>
            <div class="col-md-2 d-grid">
              <BButton variant="outline-secondary" @click="clearFilters">
                <i class="bx bx-filter-alt"></i>
                Limpiar
              </BButton>
            </div>
          </div>
        </BCard>

        <BRow class="g-3 mb-4">
          <BCol v-for="card in metricCards" :key="card.label" sm="6" xl>
            <BCard class="border-0 shadow-sm h-100 superadmin-metric">
              <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                  <div class="text-muted small">{{ card.label }}</div>
                  <div class="superadmin-metric__value">{{ card.value }}</div>
                  <div class="text-muted small">{{ card.detail }}</div>
                </div>
                <span :class="`superadmin-metric__icon superadmin-metric__icon--${card.variant}`">
                  <i :class="`bx ${card.icon}`"></i>
                </span>
              </div>
            </BCard>
          </BCol>
        </BRow>

        <BRow class="g-4 mb-4">
          <BCol xl="7">
            <BCard class="border-0 shadow-sm h-100">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <h5 class="mb-1">Actividad y seguimiento</h5>
                  <div class="text-muted small">Comparativo de eventos diarios y puntos abiertos por módulo.</div>
                </div>
                <BBadge variant="info">{{ activityChartRows.length }} módulos con datos</BBadge>
              </div>
              <apexchart
                v-if="activityChartRows.length"
                ref="activityChart"
                type="bar"
                height="320"
                :options="activityChartOptions"
                :series="activityChartSeries"
              />
              <div v-else class="superadmin-empty-chart">No hay actividad graficable para los filtros aplicados.</div>
            </BCard>
          </BCol>
          <BCol xl="5">
            <BCard class="border-0 shadow-sm h-100">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <h5 class="mb-1">Estado general</h5>
                  <div class="text-muted small">Distribución ejecutiva de módulos según situación actual.</div>
                </div>
                <BBadge variant="primary">{{ filteredModules.length }} módulos</BBadge>
              </div>
              <apexchart
                v-if="filteredModules.length"
                ref="statusChart"
                type="donut"
                height="320"
                :options="statusChartOptions"
                :series="statusChartSeries"
              />
              <div v-else class="superadmin-empty-chart">No hay módulos para los filtros aplicados.</div>
            </BCard>
          </BCol>
        </BRow>

        <BRow class="g-4 mb-4">
          <BCol xl="8">
            <BCard class="border-0 shadow-sm h-100">
              <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div>
                  <h5 class="mb-1">Módulos del sistema</h5>
                  <div class="text-muted small">Selecciona un módulo para ver su informe rápido y detalle operativo.</div>
                </div>
                <BBadge variant="primary">{{ filteredModules.length }} módulos</BBadge>
              </div>
              <div v-if="filteredModules.length" class="superadmin-module-grid">
                <button
                  v-for="module in filteredModules"
                  :key="module.slug"
                  type="button"
                  class="superadmin-module-card"
                  :class="{ 'superadmin-module-card--active': selectedModule?.slug === module.slug }"
                  @click="selectModule(module)"
                >
                  <span class="superadmin-module-card__icon">
                    <i :class="`bx ${module.icon || 'bx-grid-alt'}`"></i>
                  </span>
                  <span class="superadmin-module-card__body">
                    <span class="d-flex justify-content-between align-items-start gap-2">
                      <strong>{{ module.name }}</strong>
                      <BBadge :variant="statusVariant(module.health_status)">{{ statusLabel(module.health_status) }}</BBadge>
                    </span>
                    <span class="superadmin-module-card__metrics">
                      <span v-for="metric in (module.report_metrics || []).slice(0, 3)" :key="metric.key">
                        {{ metric.label }}: {{ formatNumber(metric.value) }}
                      </span>
                      <span>
                        Seguimiento: {{ formatNumber(module.metrics?.attention_records) }} · Alertas: {{ formatNumber(module.metrics?.alert_records) }}
                      </span>
                      <span v-if="!(module.report_metrics || []).length">Sin informe configurado</span>
                    </span>
                  </span>
                </button>
              </div>
              <div v-else class="superadmin-empty-chart">No hay módulos que coincidan con los filtros aplicados.</div>
            </BCard>
          </BCol>
          <BCol xl="4">
            <BCard class="border-0 shadow-sm h-100" v-if="selectedModule">
              <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <BBadge :variant="statusVariant(selectedModule.health_status)" class="mb-2">
                    {{ statusLabel(selectedModule.health_status) }}
                  </BBadge>
                  <h5 class="mb-1">{{ selectedModule.name }}</h5>
                  <div class="text-muted small">{{ selectedModule.report_summary }}</div>
                  <div class="text-muted small mt-2">
                    Última actividad: {{ formatDateTime(selectedModule.last_activity_at) }}
                  </div>
                </div>
                <span class="superadmin-selected-icon">
                  <i :class="`bx ${selectedModule.icon || 'bx-grid-alt'}`"></i>
                </span>
              </div>

              <div class="superadmin-selected-metrics mb-3">
                <div v-for="metric in selectedReportMetrics.slice(0, 6)" :key="metric.key">
                  <span>{{ metric.label }}</span>
                  <strong>{{ formatNumber(metric.value) }}</strong>
                  <small>{{ metric.detail }}</small>
                </div>
                <div v-if="!selectedReportMetrics.length" class="text-muted small">
                  Sin indicadores configurados para este módulo.
                </div>
              </div>

              <div v-if="selectedReportMetrics.length" class="superadmin-selected-chart mb-3">
                <apexchart
                  ref="selectedMetricChart"
                  type="bar"
                  height="230"
                  :options="selectedMetricChartOptions"
                  :series="selectedMetricChartSeries"
                />
              </div>

              <div class="d-flex flex-wrap gap-2">
                <BButton size="sm" variant="outline-primary" :disabled="!selectedModule.route" @click="openModule(selectedModule)">
                  <i class="bx bx-link-external"></i>
                  Abrir módulo
                </BButton>
                <BButton size="sm" variant="primary" @click="useModuleReport(selectedModule)">
                  <i class="bx bx-file"></i>
                  Reporte módulo
                </BButton>
                <BButton size="sm" variant="outline-danger" :disabled="reportLoading" @click="exportReportPdf('module', selectedModule.slug)">
                  <i class="bx bx-download"></i>
                  PDF módulo
                </BButton>
              </div>
            </BCard>
          </BCol>
        </BRow>

        <section v-if="selectedOperationalSections.length" class="superadmin-operational mb-4">
          <BRow class="g-4">
            <BCol v-for="section in selectedOperationalSections" :key="section.key" xl="6">
              <BCard class="border-0 shadow-sm h-100 superadmin-operation-card">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                  <div class="d-flex align-items-start gap-3">
                    <span class="superadmin-operation-card__icon">
                      <i :class="`bx ${section.icon || 'bx-list-ul'}`"></i>
                    </span>
                    <div>
                      <h6 class="mb-1">{{ section.title }}</h6>
                      <div class="text-muted small">{{ section.description }}</div>
                    </div>
                  </div>
                  <BButton
                    v-if="section.route"
                    size="sm"
                    variant="outline-secondary"
                    title="Abrir sección"
                    aria-label="Abrir sección"
                    @click="openRoute(section.route)"
                  >
                    <i class="bx bx-link-external"></i>
                  </BButton>
                </div>

                <div v-if="(section.rows || []).length" class="table-responsive superadmin-operation-card__table">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th v-for="header in section.headers" :key="header">{{ header }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(row, rowIndex) in section.rows" :key="`${section.key}-${rowIndex}`">
                        <td v-for="(cell, cellIndex) in row.cells" :key="cellIndex">
                          <BBadge
                            v-if="cellIndex === row.cells.length - 1 && row.status && row.status !== '-'"
                            :variant="rowStatusVariant(row.status)"
                          >
                            {{ cell }}
                          </BBadge>
                          <span v-else>{{ cell }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="superadmin-operation-card__empty">
                  {{ section.empty }}
                </div>
              </BCard>
            </BCol>
          </BRow>
        </section>

        <BRow class="g-4 mb-4">
          <BCol>
            <BCard class="border-0 shadow-sm h-100">
              <h5 class="mb-3">Generador de reportes</h5>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Tipo</label>
                  <BFormSelect v-model="filters.report_scope" :options="scopeOptions" />
                </div>
                <div class="col-md-6">
                  <label class="form-label">Módulo</label>
                  <BFormSelect v-model="filters.module_slug" :options="moduleOptions" :disabled="filters.report_scope !== 'module'" />
                </div>
              </div>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <BButton variant="primary" :disabled="reportLoading" @click="generateReport">
                  {{ reportLoading ? "Generando..." : "Generar" }}
                </BButton>
                <BButton variant="outline-success" :disabled="!report?.sections?.length" @click="exportExcel">Excel</BButton>
                <BButton variant="outline-danger" :disabled="!report?.sections?.length" @click="exportPdf">PDF</BButton>
                <BButton variant="outline-secondary" :disabled="!report?.sections?.length" @click="printReport">Imprimir</BButton>
              </div>
              <LoadingState v-if="reportLoading" message="Generando reporte..." compact />
              <div v-else-if="report" class="superadmin-report-preview">
                <div class="fw-semibold">{{ report.title }}</div>
                <div class="text-muted small mb-3">{{ report.subtitle }}</div>
                <div v-for="section in report.sections" :key="section.title" class="superadmin-report-preview__section">
                  <div class="fw-semibold mb-2">{{ section.title }}</div>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                      <thead v-if="section.headers?.length">
                        <tr>
                          <th v-for="header in section.headers" :key="formatReportCell(header)">{{ formatReportCell(header) }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(row, rowIndex) in (section.rows || []).slice(0, 6)" :key="rowIndex">
                          <td v-for="(cell, cellIndex) in row" :key="cellIndex">{{ formatReportCell(cell) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-if="(section.rows || []).length > 6" class="text-muted small mt-1">
                    {{ formatNumber(section.rows.length - 6) }} filas adicionales incluidas en la exportación.
                  </div>
                </div>
              </div>
            </BCard>
          </BCol>
        </BRow>
      </template>
    </div>
  </Layout>
</template>

<style scoped>
.superadmin-dashboard__period {
  min-width: 8.5rem;
}

.superadmin-metric {
  min-height: 8rem;
}

.superadmin-metric__value {
  color: #2a3042;
  font-size: 1.55rem;
  font-weight: 700;
  line-height: 1.2;
  margin: 0.35rem 0;
}

.superadmin-metric__icon,
.superadmin-selected-icon,
.superadmin-module-card__icon {
  align-items: center;
  border-radius: 8px;
  display: inline-flex;
  flex: 0 0 auto;
  height: 2.6rem;
  justify-content: center;
  width: 2.6rem;
}

.superadmin-metric__icon {
  background: #eef2ff;
  color: #556ee6;
  font-size: 1.35rem;
}

.superadmin-metric__icon--success {
  background: #e7f8f1;
  color: #34c38f;
}

.superadmin-metric__icon--info {
  background: #e9f6fe;
  color: #50a5f1;
}

.superadmin-metric__icon--warning {
  background: #fff6e1;
  color: #f1b44c;
}

.superadmin-metric__icon--secondary {
  background: #f1f3f6;
  color: #74788d;
}

.superadmin-module-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
}

.superadmin-module-card {
  align-items: flex-start;
  background: #fff;
  border: 1px solid #e9edf4;
  border-radius: 8px;
  color: inherit;
  display: flex;
  gap: 0.85rem;
  min-height: 7rem;
  padding: 0.9rem;
  text-align: left;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.superadmin-module-card:hover,
.superadmin-module-card--active {
  border-color: #556ee6;
  box-shadow: 0 0.5rem 1.2rem rgba(85, 110, 230, 0.12);
}

.superadmin-module-card__icon,
.superadmin-selected-icon {
  background: #f3f6fb;
  color: #2a3042;
  font-size: 1.35rem;
}

.superadmin-module-card__body {
  display: grid;
  flex: 1 1 auto;
  gap: 0.75rem;
  min-width: 0;
}

.superadmin-module-card__metrics {
  color: #74788d;
  display: grid;
  font-size: 0.8rem;
  gap: 0.25rem;
}

.superadmin-selected-metrics {
  display: grid;
  gap: 0.65rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.superadmin-selected-metrics > div {
  background: #f8f9fc;
  border: 1px solid #eef1f6;
  border-radius: 8px;
  padding: 0.75rem;
}

.superadmin-selected-metrics span {
  color: #74788d;
  display: block;
  font-size: 0.78rem;
}

.superadmin-selected-metrics strong {
  color: #2a3042;
  display: block;
  font-size: 1.15rem;
  margin-top: 0.2rem;
}

.superadmin-selected-metrics small {
  color: #495057;
  display: block;
  font-size: 0.72rem;
  line-height: 1.25;
  margin-top: 0.2rem;
}

.superadmin-selected-chart {
  background: #fff;
  border: 1px solid #eef1f6;
  border-radius: 8px;
  padding: 0.75rem;
}

.superadmin-empty-chart {
  align-items: center;
  background: #f8f9fc;
  border: 1px dashed #d9dee7;
  border-radius: 8px;
  color: #74788d;
  display: flex;
  justify-content: center;
  min-height: 18rem;
  padding: 1rem;
  text-align: center;
}

.superadmin-operational {
  min-width: 0;
}

.superadmin-operation-card h6 {
  color: #2a3042;
  font-size: 0.98rem;
}

.superadmin-operation-card__icon {
  align-items: center;
  background: #e9f6fe;
  border-radius: 8px;
  color: #50a5f1;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.25rem;
  height: 2.35rem;
  justify-content: center;
  width: 2.35rem;
}

.superadmin-operation-card__table {
  max-height: 22rem;
  overflow: auto;
}

.superadmin-operation-card__table table {
  min-width: 42rem;
}

.superadmin-operation-card__table th {
  background: #f8f9fc;
  color: #495057;
  font-size: 0.72rem;
  font-weight: 700;
  position: sticky;
  top: 0;
  z-index: 1;
}

.superadmin-operation-card__table td {
  color: #2a3042;
  font-size: 0.78rem;
  vertical-align: middle;
}

.superadmin-operation-card__empty {
  align-items: center;
  background: #f8f9fc;
  border: 1px dashed #d9dee7;
  border-radius: 8px;
  color: #74788d;
  display: flex;
  min-height: 7.5rem;
  padding: 1rem;
}

.superadmin-report-preview {
  max-height: 34rem;
  overflow: auto;
  padding-right: 0.25rem;
}

.superadmin-report-preview__section {
  border-top: 1px solid #edf0f5;
  padding: 1rem 0;
}

</style>
