<script>
import ConvivenciaDataTable from "../ui/convivencia-data-table.vue";
import { formatConvivenciaDate, formatConvivenciaDateTime, humanizeConvivenciaStatus } from "../module-utils";
import { convivenciaProtocolPartCategoryLabel } from "../protocol-runtime-labels";
import { CONVIVENCIA_DASHBOARD_METRIC_LABELS as metricLabels } from "./dashboard-metrics";

const CHART_COLORS = ["#5064d9", "#2a8b77", "#d59635", "#c45259", "#7295c7", "#8b6cb0"];

export default {
  components: { ConvivenciaDataTable },
  props: {
    data: { type: Object, default: null },
    reportData: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    reportLoading: { type: Boolean, default: false },
    catalogs: { type: Object, default: () => ({}) },
    modelValue: { type: Object, default: () => ({}) },
    reportExporting: { type: String, default: null },
    reportExportProgress: { type: Number, default: 0 },
    reportFiltersDirty: { type: Boolean, default: false },
  },
  emits: ["update:modelValue", "refresh", "export-excel", "export-pdf"],
  data() {
    return { filters: { ...this.modelValue }, activeDataset: "cases", activeChartGroup: "overview" };
  },
  computed: {
    isLoading() { return this.loading || this.reportLoading; },
    hasContent() { return Boolean(this.data || this.reportData); },
    canViewReports() { return this.catalogs.capabilities?.can_view_course_reports === true; },
    canExportReports() { return this.canViewReports && this.catalogs.capabilities?.can_export_reports === true; },
    summary() { return this.reportData?.summary || {}; },
    analytics() { return this.reportData?.analytics || {}; },
    metrics() {
      if (Array.isArray(this.data?.metrics)) return this.data.metrics.map((item) => ({ key: item.key || item.code || item.label, label: item.label || metricLabels[item.key] || this.label(item.key), value: item.value ?? item.total ?? item.count, suffix: item.suffix || "" }));
      return Object.entries(this.data?.metrics || {}).map(([key, raw]) => {
        const object = raw && typeof raw === "object" ? raw : null;
        return { key, label: object?.label || metricLabels[key] || this.label(key), value: object?.value ?? object?.total ?? object?.count ?? raw, suffix: object?.suffix || (key.includes("percentage") || key.includes("rate") ? "%" : "") };
      });
    },
    executiveKpis() {
      const metric = (key, fallback = 0) => this.metrics.find((item) => item.key === key)?.value ?? fallback;
      if (!this.canViewReports || !this.reportData) {
        return [
          { key: "open_cases", label: "Casos abiertos", value: metric("open_cases"), suffix: "", icon: "bx-folder-open", tone: "primary", detail: "Expedientes que requieren seguimiento" },
          { key: "closed_cases", label: "Casos cerrados", value: metric("closed_cases"), suffix: "", icon: "bx-check-circle", tone: "success", detail: "Expedientes concluidos" },
          { key: "pending_measures", label: "Medidas pendientes", value: metric("pending_measures"), suffix: "", icon: "bx-check-shield", tone: "teal", detail: "Acciones formativas en seguimiento" },
          { key: "protocol_compliance", label: "Cumplimiento de protocolos", value: metric("protocol_compliance_percentage"), suffix: "%", icon: "bx-git-branch", tone: "indigo", detail: `${metric("active_protocols")} protocolos activos` },
          { key: "complaints", label: "Denuncias recibidas", value: metric("complaints_received"), suffix: "", icon: "bx-message-square-error", tone: "warning", detail: "Registros visibles en el período" },
          { key: "daily_events", label: "Actividad de bitácora", value: metric("daily_events"), suffix: "", icon: "bx-notepad", tone: "violet", detail: "Hechos registrados en el período" },
        ];
      }
      const protocolOrClosure = this.data
        ? { key: "protocol_compliance", label: "Cumplimiento de protocolos", value: metric("protocol_compliance_percentage", 0), suffix: "%", icon: "bx-git-branch", tone: "indigo", detail: `${metric("active_protocols", 0)} protocolos activos` }
        : { key: "closure_time", label: "Tiempo promedio de cierre", value: this.summary.average_case_closure_days ?? "-", suffix: this.summary.average_case_closure_days == null ? "" : " días", icon: "bx-time-five", tone: "indigo", detail: "Calculado sobre casos cerrados visibles" };
      return [
        { key: "open_cases", label: "Casos abiertos", value: this.summary.open_cases ?? metric("open_cases"), suffix: "", icon: "bx-folder-open", tone: "primary", detail: "Expedientes que aún requieren seguimiento" },
        { key: "resolution_rate", label: "Tasa de cierre", value: this.summary.case_resolution_rate ?? 0, suffix: "%", icon: "bx-check-circle", tone: "success", detail: `${this.summary.closed_cases ?? metric("closed_cases")} casos cerrados` },
        { key: "measure_completion", label: "Cumplimiento de medidas", value: this.summary.measure_completion_rate ?? 0, suffix: "%", icon: "bx-check-shield", tone: "teal", detail: `${this.summary.completed_measures ?? 0} medidas cumplidas o cerradas` },
        protocolOrClosure,
        { key: "complaints", label: "Denuncias recibidas", value: this.summary.complaints ?? metric("complaints_received"), suffix: "", icon: "bx-message-square-error", tone: "warning", detail: `${this.summary.complaint_conversion_rate ?? 0}% convertidas en caso` },
        { key: "climate", label: "Clima de convivencia", value: this.summary.climate?.percentage ?? "-", suffix: this.summary.climate?.percentage === null || this.summary.climate?.percentage === undefined ? "" : "%", icon: "bx-happy-heart-eyes", tone: "violet", detail: this.summary.climate ? "Último resultado IDPS visible" : "Sin medición IDPS para el filtro" },
      ];
    },
    alerts() {
      const source = this.data?.alerts || [];
      const items = Array.isArray(source) ? source : [];
      const reportAlerts = [
        { type: "overdue", title: "Medidas vencidas", total: Number(this.summary.alerts?.overdue_measures || 0) },
        { type: "warning", title: "Derivaciones pendientes", total: Number(this.summary.pending_derivations || 0) },
        { type: "warning", title: "Seguimientos de entrevista pendientes", total: Number(this.summary.pending_interview_follow_ups || 0) },
      ];
      return [...items, ...reportAlerts].filter((item) => Number(item.total ?? item.count ?? 0) > 0);
    },
    activityByType() {
      const reportValues = this.firstArray(this.analytics.activity_by_type);
      if (reportValues.length) return reportValues;
      const values = { Casos: "open_cases", Denuncias: "complaints_received", Bitácora: "daily_events", Derivaciones: "internal_derivations_pending", Entrevistas: "interviews_done", Medidas: "pending_measures" };
      return Object.entries(values).map(([label, key]) => ({ label, total: Number(this.metrics.find((item) => item.key === key)?.value || 0) }));
    },
    casesByStatus() { return this.firstArray(this.analytics.cases_by_status, this.data?.charts?.cases_by_status); },
    casesByClassification() { return this.firstArray(this.analytics.cases_by_classification, this.data?.charts?.cases_by_classification); },
    casesBySubclassification() { return this.firstArray(this.analytics.cases_by_subclassification); },
    casesByCriticality() { return this.firstArray(this.analytics.cases_by_criticality, this.data?.charts?.cases_by_criticality); },
    casesByOrigin() { return this.firstArray(this.analytics.cases_by_origin); },
    activationsByProtocol() { return this.firstArray(this.data?.charts?.activations_by_protocol); },
    currentStageDistribution() { return this.firstArray(this.data?.charts?.current_stage_distribution); },
    protocolStepsByStatus() { return this.firstArray(this.data?.charts?.protocol_steps_by_status); },
    protocolPartsByStatus() { return this.firstArray(this.data?.charts?.protocol_parts_by_status); },
    partsByCategory() { return this.firstArray(this.data?.charts?.parts_by_category); },
    bottlenecks() { return this.firstArray(this.data?.charts?.bottlenecks); },
    courseStats() { return this.firstArray(this.analytics.courses); },
    topCourses() { return this.courseStats.slice(0, 12).map((item) => ({ label: item.course, total: item.activity_total })); },
    monthlyActivity() {
      if (Array.isArray(this.analytics.monthly_activity?.series)) return this.analytics.monthly_activity;
      const trend = this.data?.charts?.monthly_trend || {};
      return { labels: trend.labels || [], series: [{ name: "Casos", data: trend.series || [] }] };
    },
    hasMonthlyActivity() { return Array.isArray(this.monthlyActivity.labels) && this.monthlyActivity.labels.length > 0; },
    chartGroups() {
      const definitions = [
        { key: "overview", label: "Panorama", description: "Tendencia, volumen y criticidad", icon: "bx-grid-alt" },
        { key: "cases", label: "Casos", description: "Clasificación, origen y cierre", icon: "bx-folder-open" },
        { key: "operations", label: "Gestión operativa", description: "Denuncias, derivaciones e intervenciones", icon: "bx-pulse" },
        { key: "courses", label: "Cursos", description: "Comparación y tasas por curso", icon: "bx-group" },
        { key: "protocols", label: "Protocolos RICE", description: "Ejecución, etapas y cumplimiento", icon: "bx-git-branch" },
      ];
      return definitions
        .map((group) => ({ ...group, count: this.chartDefinitions.filter((chart) => chart.group === group.key && chart.available !== false).length }))
        .filter((group) => group.count > 0);
    },
    chartDefinitions() {
      const distribution = (key, group, kicker, title, icon, items, colorIndex = 0, translateCategories = false) => ({
        key, group, kicker, title, icon, type: "donut", items, height: 310,
        hasData: items.some((item) => this.chartTotal(item) > 0),
        series: items.map(this.chartTotal),
        options: this.donutOptions(items, translateCategories, colorIndex),
      });
      const bars = (key, group, kicker, title, icon, items, seriesName, color, horizontal = true, wide = false, percentage = false) => ({
        key, group, kicker, title, icon, type: "bar", items, height: 310, wide,
        hasData: items.some((item) => this.chartTotal(item) > 0),
        series: this.chartSeries(items, seriesName),
        options: this.barOptions(items, horizontal, [color], percentage),
      });
      const courseItems = (field) => this.courseStats.slice(0, 14).map((item) => ({ label: item.course, total: Number(item[field] || 0) }));
      const report = this.analytics;
      const dashboardAvailable = Boolean(this.data?.charts || this.data?.metrics);
      const compliance = Number(this.metrics.find((item) => item.key === "protocol_compliance_percentage")?.value || 0);

      return [
        {
          key: "monthly_activity", group: "overview", kicker: "Evolución temporal", title: "Actividad mensual consolidada", icon: "bx-trending-up", type: "area", height: 330, wide: true,
          hasData: this.hasMonthlyActivity, series: this.monthlyActivity.series, options: this.lineOptions(),
        },
        distribution("activity_by_type", "overview", "Volumen comparado", "Registros por tipo", "bx-pie-chart-alt-2", this.activityByType, 0),
        distribution("cases_by_criticality", "overview", "Nivel de respuesta", "Casos por criticidad", "bx-pulse", this.casesByCriticality, 2),
        distribution("cases_by_status", "overview", "Estado de expedientes", "Casos por estado", "bx-folder-open", this.casesByStatus, 1),
        bars("activity_by_course", "overview", "Carga de acompañamiento", "Actividad total por curso", "bx-group", this.topCourses, "Registros", CHART_COLORS[1], true, true),
        { key: "rice_compliance", group: "overview", kicker: "Cumplimiento reglamentario", title: "Cumplimiento RICE", icon: "bx-shield-quarter", type: "radialBar", height: 310, available: dashboardAvailable, hasData: dashboardAvailable, series: [compliance], options: this.radialOptions(compliance) },

        bars("cases_by_classification", "cases", "Tipología principal", "Casos por clasificación", "bx-category-alt", this.casesByClassification, "Casos", CHART_COLORS[0]),
        bars("cases_by_subclassification", "cases", "Detalle de tipología", "Casos por subclasificación", "bx-list-ul", this.casesBySubclassification, "Casos", CHART_COLORS[4]),
        distribution("cases_by_origin", "cases", "Canal de ingreso", "Origen de los casos", "bx-log-in-circle", this.casesByOrigin, 4),
        bars("cases_by_course", "cases", "Distribución territorial", "Casos por curso", "bx-building-house", courseItems("total_cases"), "Casos", CHART_COLORS[0]),
        bars("open_cases_by_course", "cases", "Carga vigente", "Casos abiertos por curso", "bx-folder-open", courseItems("open_cases"), "Casos abiertos", CHART_COLORS[2]),
        bars("case_closure_by_course", "cases", "Capacidad de resolución", "Tasa de cierre por curso", "bx-check-circle", courseItems("resolution_rate"), "Cierre", CHART_COLORS[1], false, true, true),

        distribution("complaints_by_status", "operations", "Ciclo de denuncia", "Denuncias por estado", "bx-message-square-error", this.firstArray(report.complaints_by_status), 3),
        bars("complaints_by_type", "operations", "Situaciones informadas", "Denuncias por tipo", "bx-news", this.firstArray(report.complaints_by_type), "Denuncias", CHART_COLORS[3]),
        distribution("complaints_by_complainant", "operations", "Origen de comunicación", "Tipo de denunciante", "bx-user-voice", this.firstArray(report.complaints_by_complainant), 5),
        distribution("derivations_by_status", "operations", "Flujo de atención", "Derivaciones por estado", "bx-transfer-alt", this.firstArray(report.derivations_by_status), 1),
        distribution("derivations_by_scope", "operations", "Red de apoyo", "Derivaciones internas y externas", "bx-network-chart", this.firstArray(report.derivations_by_scope), 4),
        bars("derivations_by_priority", "operations", "Urgencia declarada", "Derivaciones por prioridad", "bx-alarm-exclamation", this.firstArray(report.derivations_by_priority), "Derivaciones", CHART_COLORS[2], false),
        bars("interviews_by_type", "operations", "Acciones de escucha", "Entrevistas por tipo", "bx-conversation", this.firstArray(report.interviews_by_type), "Entrevistas", CHART_COLORS[5]),
        distribution("interviews_by_follow_up", "operations", "Continuidad del proceso", "Seguimiento de entrevistas", "bx-calendar-check", this.firstArray(report.interviews_by_follow_up), 5),
        distribution("measures_by_status", "operations", "Ejecución de apoyos", "Medidas por estado", "bx-check-shield", this.firstArray(report.measures_by_status), 1),
        bars("measures_by_type", "operations", "Respuesta adoptada", "Medidas por tipo", "bx-shield-quarter", this.firstArray(report.measures_by_type), "Medidas", CHART_COLORS[1]),
        bars("daily_logs_by_type", "operations", "Registro cotidiano", "Bitácora por tipo de evento", "bx-notepad", this.firstArray(report.daily_logs_by_type), "Registros", CHART_COLORS[4]),
        distribution("daily_logs_by_status", "operations", "Gestión de registros", "Bitácora por estado", "bx-list-check", this.firstArray(report.daily_logs_by_status), 4),

        bars("complaints_by_course", "courses", "Canales formales", "Denuncias por curso", "bx-message-square-error", courseItems("complaints"), "Denuncias", CHART_COLORS[3]),
        bars("daily_logs_by_course", "courses", "Actividad cotidiana", "Registros de bitácora por curso", "bx-notepad", courseItems("daily_events"), "Registros", CHART_COLORS[4]),
        bars("derivations_by_course", "courses", "Apoyo especializado", "Derivaciones por curso", "bx-transfer-alt", courseItems("derivations"), "Derivaciones", CHART_COLORS[2]),
        bars("interviews_by_course", "courses", "Espacios de escucha", "Entrevistas por curso", "bx-conversation", courseItems("interviews"), "Entrevistas", CHART_COLORS[5]),
        bars("measures_by_course", "courses", "Intervenciones adoptadas", "Medidas por curso", "bx-check-shield", courseItems("measures"), "Medidas", CHART_COLORS[1]),
        bars("measure_completion_by_course", "courses", "Cumplimiento operativo", "Cumplimiento de medidas por curso", "bx-task", courseItems("measure_completion_rate"), "Cumplimiento", CHART_COLORS[1], false, true, true),
        bars("overdue_measures_by_course", "courses", "Atención prioritaria", "Medidas vencidas por curso", "bx-time-five", courseItems("overdue_measures"), "Vencidas", CHART_COLORS[3], false),

        bars("activations_by_protocol", "protocols", "Ejecución RICE", "Activaciones por protocolo", "bx-git-branch", this.activationsByProtocol, "Activaciones", CHART_COLORS[0], true, true),
        bars("current_stage_distribution", "protocols", "Ruta vigente", "Activaciones por etapa actual", "bx-map-alt", this.currentStageDistribution, "Activaciones", CHART_COLORS[4]),
        distribution("protocol_steps_by_status", "protocols", "Avance de etapas", "Etapas por estado", "bx-list-ol", this.protocolStepsByStatus, 0),
        distribution("protocol_parts_by_status", "protocols", "Ejecución de componentes", "Partes por estado", "bx-check-square", this.protocolPartsByStatus, 1),
        distribution("parts_by_category", "protocols", "Componentes reglamentarios", "Partes por categoría", "bx-layer", this.partsByCategory, 4, true),
        bars("protocol_bottlenecks", "protocols", "Tiempo y capacidad", "Cuellos de botella", "bx-timer", this.bottlenecks, "Pendientes o vencidos", CHART_COLORS[2], true, true),
      ].map((chart, index) => ({ ...chart, number: index + 1 }));
    },
    availableChartCount() { return this.chartDefinitions.filter((chart) => chart.available !== false).length; },
    visibleCharts() { return this.chartDefinitions.filter((chart) => chart.group === this.activeChartGroup && chart.available !== false); },
    datasetDefinitions() {
      return [
        { key: "cases", label: "Casos", icon: "bx-folder-open", columns: [["folio", "Folio"], ["opened_at", "Fecha"], ["classification_label", "Clasificación"], ["criticality_label", "Criticidad"], ["status", "Estado"]] },
        { key: "complaints", label: "Denuncias", icon: "bx-message-square-error", columns: [["folio", "Folio"], ["received_at", "Fecha"], ["situation_type_label", "Tipo"], ["complainant_type", "Denunciante"], ["status", "Estado"]] },
        { key: "daily_logs", label: "Bitácora", icon: "bx-notepad", columns: [["happened_at", "Fecha"], ["daily_log_type_label", "Tipo"], ["description", "Descripción"], ["status", "Estado"]] },
        { key: "derivations", label: "Derivaciones", icon: "bx-transfer-alt", columns: [["derived_at", "Fecha"], ["scope", "Ámbito"], ["destination_label", "Destino"], ["priority_level", "Prioridad"], ["status", "Estado"]] },
        { key: "interviews", label: "Entrevistas", icon: "bx-conversation", columns: [["interview_at", "Fecha"], ["interview_type_label", "Tipo"], ["motive", "Motivo"], ["follow_up_status", "Seguimiento"]] },
        { key: "measures", label: "Medidas", icon: "bx-check-shield", columns: [["assigned_at", "Asignada"], ["measure_type_label", "Tipo"], ["due_at", "Vencimiento"], ["status", "Estado"]] },
      ];
    },
    activeDatasetDefinition() { return this.datasetDefinitions.find((item) => item.key === this.activeDataset) || this.datasetDefinitions[0]; },
    activeDatasetRows() { return this.reportData?.lists?.[this.activeDataset] || []; },
    activeDatasetMeta() {
      const raw = this.reportData?.list_meta?.[this.activeDataset] || {};
      const shown = Number(raw.shown ?? this.activeDatasetRows.length);
      const total = Number(raw.total ?? shown);
      return { shown, total, truncated: raw.truncated === true || total > shown };
    },
    recentGroups() {
      return [
        { key: "protocols", title: "Protocolos recientes", icon: "bx-git-branch", items: this.asArray(this.data?.recent?.protocols) },
        { key: "cases", title: "Casos recientes", icon: "bx-folder-open", items: this.asArray(this.data?.recent?.cases) },
        { key: "complaints", title: "Denuncias recientes", icon: "bx-message-square-error", items: this.asArray(this.data?.recent?.complaints) },
      ].filter((group) => group.items.length);
    },
    years() { return this.catalogs?.academic_years || []; },
    courses() { return this.catalogs?.courses || []; },
  },
  watch: {
    modelValue: { deep: true, handler(value) { this.filters = { ...value }; } },
  },
  methods: {
    asArray(value) { return Array.isArray(value) ? value : value === null || value === undefined || value === "" ? [] : [value]; },
    firstArray(...values) { return values.find((value) => Array.isArray(value) && value.length) || []; },
    label(value) { return humanizeConvivenciaStatus(value); },
    optionValue(item) { return item?.value ?? item?.id; },
    optionText(item) { return item?.text || item?.label || item?.name || item?.display_name; },
    chartLabel(item) { return item?.label || item?.name || item?.protocol_name || item?.category || item?.stage_name || "Sin categoría"; },
    chartTotal(item) { return Number(item?.total ?? item?.count ?? item?.value ?? item?.activations ?? 0); },
    barOptions(items, horizontal = true, colors = [CHART_COLORS[0]], percentage = false) {
      return {
        chart: { toolbar: { show: true, tools: { download: true, selection: false, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false } }, animations: { speed: 420 }, fontFamily: "inherit" },
        colors,
        plotOptions: { bar: { horizontal, borderRadius: 7, borderRadiusApplication: "end", barHeight: horizontal ? "56%" : undefined, columnWidth: horizontal ? undefined : "52%", distributed: false } },
        dataLabels: { enabled: !horizontal && items.length <= 8, formatter: (value) => percentage ? `${Number(value).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%` : Number(value).toLocaleString("es-CL"), style: { fontSize: "10px", fontWeight: 700 } },
        xaxis: { categories: items.map(this.chartLabel), max: percentage ? 100 : undefined, labels: { rotate: horizontal ? 0 : -32, trim: true, style: { fontSize: "10px", colors: "#758198" }, formatter: (value) => percentage ? `${value}%` : value } },
        yaxis: { min: 0, max: !horizontal && percentage ? 100 : undefined, labels: { style: { fontSize: "10px", colors: "#58657c" }, maxWidth: 165 } },
        grid: { borderColor: "#edf0f5", strokeDashArray: 3 },
        tooltip: { y: { formatter: (value) => `${Number(value).toLocaleString("es-CL", { maximumFractionDigits: 1 })}${percentage ? "%" : ""}` } },
      };
    },
    chartSeries(items, name = "Total") { return [{ name, data: items.map(this.chartTotal) }]; },
    donutOptions(items, translatePartCategories = false, colorIndex = 0) {
      const colors = [...CHART_COLORS.slice(colorIndex), ...CHART_COLORS.slice(0, colorIndex)];
      return {
        chart: { toolbar: { show: true, tools: { download: true } }, fontFamily: "inherit" },
        labels: items.map((item) => translatePartCategories ? convivenciaProtocolPartCategoryLabel(item?.category || item?.label || item?.name) : this.label(this.chartLabel(item))),
        colors,
        legend: { position: "bottom", fontSize: "11px", fontWeight: 600, markers: { radius: 8 } },
        dataLabels: { enabled: false },
        stroke: { width: 4, colors: ["#fff"] },
        tooltip: { y: { formatter: (value) => Number(value).toLocaleString("es-CL") } },
        plotOptions: { pie: { expandOnClick: false, donut: { size: "70%", labels: { show: true, name: { show: true, color: "#657086" }, value: { show: true, color: "#202e6d", fontSize: "22px", fontWeight: 800, formatter: (value) => Number(value).toLocaleString("es-CL") }, total: { show: true, label: "Total", color: "#7d8799", formatter: (context) => context.globals.seriesTotals.reduce((total, value) => total + value, 0).toLocaleString("es-CL") } } } } },
      };
    },
    lineOptions() {
      return { chart: { toolbar: { show: true, tools: { download: true, selection: false, zoom: true, zoomin: true, zoomout: true, pan: false, reset: true } }, animations: { speed: 450 }, fontFamily: "inherit" }, colors: CHART_COLORS, stroke: { curve: "smooth", width: 3 }, fill: { type: "gradient", gradient: { shadeIntensity: .7, opacityFrom: .34, opacityTo: .05, stops: [0, 85, 100] } }, markers: { size: 3, hover: { size: 6 } }, dataLabels: { enabled: false }, xaxis: { categories: this.monthlyActivity.labels.map(this.monthLabel), labels: { style: { fontSize: "10px", colors: "#758198" } } }, yaxis: { min: 0, forceNiceScale: true }, grid: { borderColor: "#edf0f5", strokeDashArray: 3 }, legend: { position: "top", horizontalAlign: "left", fontWeight: 600 }, tooltip: { shared: true, intersect: false } };
    },
    radialOptions(value) {
      return { chart: { toolbar: { show: true, tools: { download: true } }, fontFamily: "inherit" }, colors: [value >= 80 ? CHART_COLORS[1] : value >= 60 ? CHART_COLORS[2] : CHART_COLORS[3]], stroke: { lineCap: "round" }, labels: ["Cumplimiento"], plotOptions: { radialBar: { startAngle: -130, endAngle: 130, hollow: { size: "62%", background: "#f8f9fd" }, track: { background: "#edf0f5", strokeWidth: "100%" }, dataLabels: { name: { offsetY: 18, color: "#718096", fontSize: "12px" }, value: { offsetY: -20, color: "#202e6d", fontSize: "30px", fontWeight: 800, formatter: (raw) => `${Number(raw).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%` } } } } };
    },
    monthLabel(value) {
      const [year, month] = String(value || "").split("-");
      if (!year || !month) return value;
      return new Intl.DateTimeFormat("es-CL", { month: "short", year: "2-digit" }).format(new Date(Number(year), Number(month) - 1, 1));
    },
    applyFilters() { this.$emit("update:modelValue", { ...this.filters }); this.$emit("refresh", { ...this.filters }); },
    clearFilters() { this.filters = { academic_year_id: this.catalogs?.active_academic_year_id || null, course_section_id: null, from: "", to: "", semester: null }; this.applyFilters(); },
    formatDate(value, withTime = false) { return withTime ? formatConvivenciaDateTime(value) : formatConvivenciaDate(value); },
    isDateColumn(key) { return ["opened_at", "received_at", "happened_at", "derived_at", "interview_at", "assigned_at", "due_at"].includes(key); },
    isStatusColumn(key) { return ["status", "scope", "priority_level", "complainant_type", "follow_up_status"].includes(key); },
    cellValue(row, key) {
      const value = row?.[key];
      if (this.isDateColumn(key)) return this.formatDate(value);
      if (this.isStatusColumn(key)) return this.label(value);
      return value || "-";
    },
    recentTitle(item) { return item.folio || item.protocol?.name || item.protocol_name || `Registro #${item.id || "-"}`; },
    recentSubtitle(item) { return item.current_stage_name || item.classification_label || item.situation_type_label || this.label(item.status); },
    alertTone(alert) { const value = String(alert.severity || alert.level || alert.type || "").toLowerCase(); return ["critical", "high", "danger", "overdue", "vencido"].includes(value) ? "danger" : "warning"; },
  },
};
</script>

<template>
  <section class="convivencia-dashboard" aria-labelledby="convivencia-dashboard-title">
    <header class="convivencia-dashboard__hero">
      <div class="convivencia-dashboard__hero-copy"><span>Centro de análisis · Convivencia Escolar</span><h2 id="convivencia-dashboard-title">Una sola visión para decidir y reportar</h2><p>Indicadores institucionales, comparación entre cursos, cumplimiento RICE y detalle documental bajo los mismos filtros.</p></div>
      <div class="convivencia-dashboard__hero-actions">
        <button type="button" class="btn btn-outline-light" :disabled="isLoading" @click="applyFilters"><i class="bx bx-refresh" aria-hidden="true"></i>Actualizar</button>
        <button v-if="canExportReports" type="button" class="btn btn-light is-excel" :disabled="Boolean(reportExporting) || reportFiltersDirty" @click="$emit('export-excel')"><i class="bx" :class="reportExporting === 'excel' ? 'bx-loader-alt bx-spin' : 'bx-spreadsheet'" aria-hidden="true"></i>{{ reportExporting === 'excel' ? `Reuniendo ${reportExportProgress}/6` : 'Excel completo' }}</button>
        <button v-if="canExportReports" type="button" class="btn btn-light is-pdf" :disabled="Boolean(reportExporting) || reportFiltersDirty" @click="$emit('export-pdf')"><i class="bx" :class="reportExporting === 'pdf' ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'" aria-hidden="true"></i>{{ reportExporting === 'pdf' ? `Reuniendo ${reportExportProgress}/6` : 'Informe PDF' }}</button>
      </div>
    </header>

    <form class="convivencia-dashboard__filters" aria-label="Filtros del centro de análisis" @submit.prevent="applyFilters">
      <div><label for="dashboard-year">Año académico</label><select id="dashboard-year" v-model="filters.academic_year_id" class="form-select"><option :value="null">Todos</option><option v-for="item in years" :key="optionValue(item)" :value="optionValue(item)">{{ optionText(item) }}</option></select></div>
      <div><label for="dashboard-course">Curso</label><select id="dashboard-course" v-model="filters.course_section_id" class="form-select"><option :value="null">Todos los cursos</option><option v-for="item in courses" :key="optionValue(item)" :value="optionValue(item)">{{ optionText(item) }}</option></select></div>
      <div><label for="dashboard-semester">Semestre</label><select id="dashboard-semester" v-model="filters.semester" class="form-select"><option :value="null">Todo el año</option><option :value="1">Primer semestre</option><option :value="2">Segundo semestre</option></select></div>
      <div><label for="dashboard-from">Desde</label><input id="dashboard-from" v-model="filters.from" type="date" class="form-control" /></div>
      <div><label for="dashboard-to">Hasta</label><input id="dashboard-to" v-model="filters.to" type="date" class="form-control" /></div>
      <div class="convivencia-dashboard__filter-actions"><button type="button" class="btn btn-outline-secondary" @click="clearFilters">Limpiar</button><button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt" aria-hidden="true"></i>Aplicar</button></div>
    </form>

    <div v-if="reportFiltersDirty && canViewReports" class="convivencia-dashboard__dirty"><i class="bx bx-info-circle"></i><span>Hay filtros modificados. Presiona <b>Aplicar</b> para actualizar gráficos e informes antes de exportar.</span></div>
    <div v-if="isLoading" class="convivencia-dashboard__state" role="status"><span class="spinner-border text-primary" aria-hidden="true"></span><b>Construyendo análisis institucional…</b></div>
    <template v-else-if="hasContent">
      <section class="convivencia-dashboard__kpis" aria-label="Indicadores ejecutivos"><article v-for="item in executiveKpis" :key="item.key" :class="`is-${item.tone}`"><span><i class="bx" :class="item.icon"></i></span><div><small>{{ item.label }}</small><b>{{ item.value }}{{ item.suffix }}</b><p>{{ item.detail }}</p></div></article></section>

      <section v-if="alerts.length" class="convivencia-dashboard__alerts" aria-labelledby="dashboard-alerts-title"><header><div><span>Atención prioritaria</span><h3 id="dashboard-alerts-title">Alertas de seguimiento</h3></div><b>{{ alerts.length }}</b></header><div><article v-for="(alert, index) in alerts" :key="`${alert.title}-${index}`" :class="`is-${alertTone(alert)}`"><i class="bx" :class="alertTone(alert) === 'danger' ? 'bx-error' : 'bx-time-five'"></i><div><b>{{ alert.title || alert.label || 'Alerta operativa' }}</b><p>{{ alert.description || alert.detail || 'Requiere revisión del equipo de convivencia.' }}</p></div><strong>{{ alert.total ?? alert.count ?? 0 }}</strong></article></div></section>

      <section class="convivencia-dashboard__section-heading"><div><span>LECTURA INSTITUCIONAL</span><h3>Centro visual con {{ availableChartCount }} gráficos ApexCharts</h3><p>Explora una dimensión a la vez. Todos los gráficos responden al mismo período y curso seleccionado.</p></div><i class="bx bx-line-chart"></i></section>
      <nav class="convivencia-dashboard__chart-tabs" aria-label="Categorías de gráficos">
        <button v-for="group in chartGroups" :key="group.key" type="button" :class="{ 'is-active': activeChartGroup === group.key }" :aria-pressed="activeChartGroup === group.key" @click="activeChartGroup = group.key">
          <i class="bx" :class="group.icon" aria-hidden="true"></i><span><b>{{ group.label }}</b><small>{{ group.description }}</small></span><em>{{ group.count }}</em>
        </button>
      </nav>
      <section class="convivencia-dashboard__charts">
        <article v-for="chart in visibleCharts" :key="chart.key" :class="{ 'is-wide': chart.wide }">
          <header><div><span>{{ chart.kicker }}</span><h3>{{ chart.title }}</h3></div><div class="convivencia-dashboard__chart-id"><small>G{{ String(chart.number).padStart(2, '0') }}</small><i class="bx" :class="chart.icon"></i></div></header>
          <apexchart v-if="chart.hasData" :type="chart.type" :height="chart.height" :options="chart.options" :series="chart.series" />
          <p v-else class="convivencia-dashboard__chart-empty"><i class="bx bx-bar-chart-square"></i><span>Sin datos para este gráfico con los filtros aplicados.</span></p>
        </article>
      </section>

      <section v-if="canViewReports" class="convivencia-dashboard__section-heading"><div><span>COMPARACIÓN POR CURSO</span><h3>Indicadores de gestión y seguimiento</h3><p>La actividad es un conteo descriptivo de registros, no una calificación del curso.</p></div><i class="bx bx-table"></i></section>
      <ConvivenciaDataTable v-if="canViewReports" title="Estadísticas por curso" subtitle="Casos, denuncias, intervenciones y tasas calculadas con los registros autorizados." icon="bx-group" :count="courseStats.length" :empty="!courseStats.length" empty-title="Sin estadísticas por curso" empty-text="No existen registros asociados a cursos para los filtros seleccionados." min-width="1050px"><table class="table"><thead><tr><th>Curso</th><th>Casos</th><th>Abiertos</th><th>Denuncias</th><th>Bitácora</th><th>Derivaciones</th><th>Entrevistas</th><th>Medidas</th><th>Cierre casos</th><th>Cumpl. medidas</th><th>Vencidas</th></tr></thead><tbody><tr v-for="item in courseStats" :key="item.course_id"><td data-label="Curso"><b>{{ item.course }}</b><small>{{ item.education_level || '' }}{{ item.academic_year ? ` · ${item.academic_year}` : '' }}</small></td><td data-label="Casos">{{ item.total_cases }}</td><td data-label="Abiertos">{{ item.open_cases }}</td><td data-label="Denuncias">{{ item.complaints }}</td><td data-label="Bitácora">{{ item.daily_events }}</td><td data-label="Derivaciones">{{ item.derivations }}</td><td data-label="Entrevistas">{{ item.interviews }}</td><td data-label="Medidas">{{ item.measures }}</td><td data-label="Cierre casos"><span class="rate-pill is-primary">{{ item.resolution_rate }}%</span></td><td data-label="Cumpl. medidas"><span class="rate-pill is-success">{{ item.measure_completion_rate }}%</span></td><td data-label="Vencidas"><span :class="['overdue-pill', { 'has-value': item.overdue_measures > 0 }]">{{ item.overdue_measures }}</span></td></tr></tbody></table></ConvivenciaDataTable>

      <section v-if="canViewReports" class="convivencia-dashboard__report-detail"><header><div><span>INFORME DOCUMENTAL</span><h3>Detalle autorizado del período</h3><p>Consulta una categoría sin abandonar la vista. Las exportaciones incluyen todas las categorías y todas sus páginas.</p></div><div class="convivencia-dashboard__dataset-tabs" role="tablist" aria-label="Secciones del informe"><button v-for="item in datasetDefinitions" :key="item.key" type="button" role="tab" :aria-selected="activeDataset === item.key" :class="{ 'is-active': activeDataset === item.key }" @click="activeDataset = item.key"><i class="bx" :class="item.icon"></i>{{ item.label }}<b>{{ reportData?.list_meta?.[item.key]?.total ?? reportData?.lists?.[item.key]?.length ?? 0 }}</b></button></div></header><ConvivenciaDataTable :title="activeDatasetDefinition.label" :subtitle="activeDatasetMeta.truncated ? `Vista previa: ${activeDatasetMeta.shown} de ${activeDatasetMeta.total}. La exportación incluye el total.` : `${activeDatasetMeta.total} registros autorizados.`" :icon="activeDatasetDefinition.icon" :count="activeDatasetMeta.total" :empty="!activeDatasetRows.length" empty-title="Sin registros" empty-text="No hay información para esta categoría y filtros." min-width="720px"><table class="table"><thead><tr><th v-for="([key, title]) in activeDatasetDefinition.columns" :key="key">{{ title }}</th></tr></thead><tbody><tr v-for="item in activeDatasetRows" :key="item.id"><td v-for="([key, title]) in activeDatasetDefinition.columns" :key="key" :data-label="title"><span v-if="isStatusColumn(key)" class="status-pill">{{ cellValue(item, key) }}</span><span v-else>{{ cellValue(item, key) }}</span></td></tr></tbody></table></ConvivenciaDataTable></section>

      <section v-if="recentGroups.length" class="convivencia-dashboard__recent"><article v-for="group in recentGroups" :key="group.key"><header><i class="bx" :class="group.icon"></i><h3>{{ group.title }}</h3></header><div><div v-for="item in group.items.slice(0, 6)" :key="item.id" class="convivencia-dashboard__recent-row"><span><b>{{ recentTitle(item) }}</b><small>{{ recentSubtitle(item) }}</small></span><em>{{ label(item.status) }}</em></div></div></article></section>
    </template>
    <div v-else class="convivencia-dashboard__state"><i class="bx bx-bar-chart-alt-2"></i><b>No hay información para construir el análisis</b><p>Ajusta los filtros e inténtalo nuevamente.</p></div>
  </section>
</template>

<style scoped>
.convivencia-dashboard{--cd-navy:#202e6d;--cd-indigo:#5064d9;--cd-teal:#2a8b77;--cd-line:#dde3ed;display:grid;gap:1rem;color:#34415a}.convivencia-dashboard__hero{display:flex;align-items:center;justify-content:space-between;gap:1.2rem;padding:1.35rem 1.45rem;color:#fff;border-radius:24px;background:radial-gradient(circle at 83% 0,rgba(85,226,195,.34),transparent 29%),linear-gradient(120deg,#1c2a68,#4a5dcc 62%,#267f73);box-shadow:0 18px 42px rgba(30,44,103,.2)}.convivencia-dashboard__hero-copy>span,.convivencia-dashboard__section-heading span,.convivencia-dashboard__alerts header span,.convivencia-dashboard__charts header span,.convivencia-dashboard__report-detail>header>div>span{font-size:.64rem;font-weight:850;letter-spacing:.11em;text-transform:uppercase;opacity:.82}.convivencia-dashboard__hero h2{margin:.18rem 0;font-size:1.35rem;font-weight:850}.convivencia-dashboard__hero p{max-width:760px;margin:0;font-size:.76rem;opacity:.84}.convivencia-dashboard__hero-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.45rem}.convivencia-dashboard__hero-actions .btn{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}.convivencia-dashboard__hero-actions .is-excel{color:#17715e}.convivencia-dashboard__hero-actions .is-pdf{color:#b33d46}.convivencia-dashboard__filters{display:grid;grid-template-columns:1fr 1.25fr .9fr .9fr .9fr auto;align-items:end;gap:.65rem;padding:.9rem;border:1px solid var(--cd-line);border-radius:18px;background:#fff;box-shadow:0 8px 22px rgba(35,48,80,.04)}.convivencia-dashboard__filters label{display:block;margin-bottom:.3rem;color:#67748a;font-size:.64rem;font-weight:750}.convivencia-dashboard__filters .form-control,.convivencia-dashboard__filters .form-select{min-height:38px;font-size:.72rem;border-color:#dce2ec}.convivencia-dashboard__filter-actions{display:flex;gap:.4rem}.convivencia-dashboard__filter-actions .btn{display:inline-flex;align-items:center;gap:.25rem}.convivencia-dashboard__dirty{display:flex;align-items:center;gap:.45rem;padding:.65rem .8rem;color:#885f20;font-size:.68rem;border:1px solid #efd9af;border-radius:12px;background:#fff9ed}.convivencia-dashboard__kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.7rem}.convivencia-dashboard__kpis article{display:flex;min-width:0;gap:.65rem;padding:.85rem;border:1px solid var(--cd-line);border-radius:17px;background:#fff;box-shadow:0 10px 25px rgba(35,48,80,.045)}.convivencia-dashboard__kpis article>span{display:grid;width:38px;height:38px;flex:0 0 38px;color:#5266d9;font-size:1.05rem;place-items:center;border-radius:12px;background:#edf0ff}.convivencia-dashboard__kpis small{display:block;color:#7b8699;font-size:.57rem;font-weight:800;text-transform:uppercase}.convivencia-dashboard__kpis b{display:block;margin:.08rem 0;color:var(--cd-navy);font-size:1.12rem}.convivencia-dashboard__kpis p{overflow:hidden;margin:0;color:#8490a3;font-size:.56rem;text-overflow:ellipsis;white-space:nowrap}.convivencia-dashboard__kpis .is-success>span,.convivencia-dashboard__kpis .is-teal>span{color:#1d735f;background:#e5f5ef}.convivencia-dashboard__kpis .is-warning>span{color:#a56b1f;background:#fff3df}.convivencia-dashboard__kpis .is-violet>span{color:#7c53a6;background:#f3edfa}.convivencia-dashboard__alerts{padding:.95rem;border:1px solid #edd1ab;border-radius:18px;background:#fffaf1}.convivencia-dashboard__alerts>header,.convivencia-dashboard__section-heading,.convivencia-dashboard__charts header{display:flex;align-items:center;justify-content:space-between}.convivencia-dashboard__alerts h3,.convivencia-dashboard__section-heading h3,.convivencia-dashboard__charts h3,.convivencia-dashboard__report-detail h3{margin:.15rem 0;color:var(--cd-navy);font-size:.9rem;font-weight:850}.convivencia-dashboard__alerts header>b{display:grid;width:31px;height:31px;color:#fff;font-size:.72rem;place-items:center;border-radius:10px;background:#d59635}.convivencia-dashboard__alerts>div{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem;margin-top:.7rem}.convivencia-dashboard__alerts article{display:grid;grid-template-columns:29px minmax(0,1fr) auto;gap:.5rem;padding:.65rem;border:1px solid #ead7b8;border-radius:12px;background:#fff}.convivencia-dashboard__alerts article>i{color:#b77924;font-size:1.15rem}.convivencia-dashboard__alerts article b{display:block;color:#664c2b;font-size:.68rem}.convivencia-dashboard__alerts article p{margin:.15rem 0;color:#806d55;font-size:.6rem}.convivencia-dashboard__alerts article>strong{color:#a56619}.convivencia-dashboard__alerts article.is-danger{border-color:#ecc4c7;background:#fff7f7}.convivencia-dashboard__alerts article.is-danger>i,.convivencia-dashboard__alerts article.is-danger>strong{color:#b43d45}.convivencia-dashboard__section-heading{padding:.15rem .1rem}.convivencia-dashboard__section-heading p,.convivencia-dashboard__report-detail>header p{margin:0;color:#8290a4;font-size:.66rem}.convivencia-dashboard__section-heading>i{color:#d8def9;font-size:2.1rem}.convivencia-dashboard__charts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}.convivencia-dashboard__charts>article{min-width:0;padding:.9rem;border:1px solid var(--cd-line);border-radius:18px;background:#fff;box-shadow:0 10px 25px rgba(35,48,80,.05)}.convivencia-dashboard__charts>article.is-wide{grid-column:span 2}.convivencia-dashboard__charts header span{color:#778399}.convivencia-dashboard__charts header>i{color:var(--cd-indigo);font-size:1.3rem}.convivencia-dashboard__chart-empty{display:grid;height:300px;margin:0;color:#8591a4;font-size:.68rem;place-items:center;border:1px dashed #d5dce7;border-radius:13px;background:#fafbfe}.convivencia-dashboard__report-detail{display:grid;gap:.75rem}.convivencia-dashboard__report-detail>header{display:grid;gap:.75rem;padding:1rem;border:1px solid var(--cd-line);border-radius:18px;background:#fff}.convivencia-dashboard__dataset-tabs{display:flex;flex-wrap:wrap;gap:.4rem}.convivencia-dashboard__dataset-tabs button{display:inline-flex;align-items:center;gap:.3rem;padding:.48rem .65rem;color:#627087;font-size:.65rem;font-weight:750;border:1px solid #dce3ed;border-radius:10px;background:#f9fafc}.convivencia-dashboard__dataset-tabs button b{display:grid;min-width:20px;height:20px;padding:0 5px;color:#60708a;font-size:.54rem;place-items:center;border-radius:999px;background:#edf0f5}.convivencia-dashboard__dataset-tabs button.is-active{color:#fff;border-color:#5064d9;background:#5064d9;box-shadow:0 7px 16px rgba(80,100,217,.22)}.convivencia-dashboard__dataset-tabs button.is-active b{color:#344aa9;background:#fff}.convivencia-dashboard :deep(.convivencia-data-table td small){display:block;margin-top:.15rem;color:#8a95a7;font-size:.58rem}.rate-pill,.overdue-pill,.status-pill{display:inline-flex;padding:.25rem .45rem;font-size:.59rem;font-weight:800;border-radius:999px;background:#edf0f5;white-space:nowrap}.rate-pill.is-primary{color:#4054bb;background:#edf0ff}.rate-pill.is-success{color:#1f775f;background:#e5f5ef}.overdue-pill{color:#66748a}.overdue-pill.has-value{color:#ad3e45;background:#fff0f1}.status-pill{color:#4455ad;background:#edf0ff}.convivencia-dashboard__recent{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.convivencia-dashboard__recent>article{padding:.85rem;border:1px solid var(--cd-line);border-radius:17px;background:#fff}.convivencia-dashboard__recent>article>header{display:flex;align-items:center;gap:.4rem;margin-bottom:.55rem;color:var(--cd-navy)}.convivencia-dashboard__recent h3{margin:0;font-size:.76rem;font-weight:800}.convivencia-dashboard__recent-row{display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.5rem 0;border-top:1px solid #edf0f4}.convivencia-dashboard__recent-row b,.convivencia-dashboard__recent-row small{display:block;overflow:hidden;font-size:.62rem;text-overflow:ellipsis;white-space:nowrap}.convivencia-dashboard__recent-row small{margin-top:.13rem;color:#8590a2}.convivencia-dashboard__recent-row em{padding:.25rem .4rem;color:#637088;font-size:.53rem;font-style:normal;font-weight:750;border-radius:999px;background:#eef1f5}.convivencia-dashboard__state{display:grid;min-height:250px;gap:.5rem;color:#7b879a;text-align:center;place-content:center;border:1px dashed #d1d8e4;border-radius:18px;background:#fafbfe}.convivencia-dashboard__state>i{font-size:2rem}.convivencia-dashboard__state p{margin:0;font-size:.68rem}
@media(max-width:1399.98px){.convivencia-dashboard__kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.convivencia-dashboard__charts{grid-template-columns:repeat(2,minmax(0,1fr))}.convivencia-dashboard__filters{grid-template-columns:repeat(3,minmax(0,1fr))}.convivencia-dashboard__filter-actions{justify-content:flex-end}}
@media(max-width:991.98px){.convivencia-dashboard__hero{align-items:flex-start;flex-direction:column}.convivencia-dashboard__hero-actions{justify-content:flex-start}.convivencia-dashboard__charts>article.is-wide{grid-column:span 1}.convivencia-dashboard__alerts>div{grid-template-columns:1fr}.convivencia-dashboard__recent{grid-template-columns:1fr}}
.convivencia-dashboard__chart-tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.55rem;padding:.55rem;border:1px solid var(--cd-line);border-radius:18px;background:#fff;box-shadow:0 9px 24px rgba(35,48,80,.045)}
.convivencia-dashboard__chart-tabs button{display:grid;grid-template-columns:36px minmax(0,1fr) auto;align-items:center;gap:.55rem;min-height:62px;padding:.55rem .65rem;color:#59667d;text-align:left;border:1px solid transparent;border-radius:13px;background:#f7f8fc;transition:transform .18s ease,box-shadow .18s ease,background .18s ease}
.convivencia-dashboard__chart-tabs button:hover{transform:translateY(-1px);background:#f0f3ff}.convivencia-dashboard__chart-tabs button>i{display:grid;width:34px;height:34px;color:#5064d9;font-size:1rem;place-items:center;border-radius:10px;background:#e9edff}.convivencia-dashboard__chart-tabs button span{min-width:0}.convivencia-dashboard__chart-tabs button b,.convivencia-dashboard__chart-tabs button small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.convivencia-dashboard__chart-tabs button b{font-size:.68rem}.convivencia-dashboard__chart-tabs button small{margin-top:.12rem;color:#8791a3;font-size:.54rem}.convivencia-dashboard__chart-tabs button em{display:grid;min-width:26px;height:26px;padding:0 5px;color:#5265c8;font-size:.58rem;font-style:normal;font-weight:850;place-items:center;border-radius:9px;background:#e8ecff}.convivencia-dashboard__chart-tabs button.is-active{color:#fff;border-color:#5366d7;background:linear-gradient(130deg,#4257c8,#6375e4);box-shadow:0 9px 18px rgba(73,91,200,.22)}.convivencia-dashboard__chart-tabs button.is-active>i,.convivencia-dashboard__chart-tabs button.is-active em{color:#4257c8;background:#fff}.convivencia-dashboard__chart-tabs button.is-active small{color:rgba(255,255,255,.72)}
.convivencia-dashboard__charts>article{background:linear-gradient(180deg,#fff,#fdfdff);transition:transform .18s ease,box-shadow .18s ease}.convivencia-dashboard__charts>article:hover{transform:translateY(-2px);box-shadow:0 15px 30px rgba(35,48,80,.085)}.convivencia-dashboard__chart-id{display:flex;align-items:center;gap:.45rem}.convivencia-dashboard__chart-id small{padding:.2rem .35rem;color:#79859a;font-size:.5rem;font-weight:850;border-radius:7px;background:#f0f2f7}.convivencia-dashboard__chart-id i{color:var(--cd-indigo);font-size:1.3rem}.convivencia-dashboard__chart-empty{padding:1rem;text-align:center}.convivencia-dashboard__chart-empty i{display:block;margin-bottom:.4rem;color:#c2c9d5;font-size:1.7rem}.convivencia-dashboard__chart-empty span{display:block}
@media(max-width:1399.98px){.convivencia-dashboard__chart-tabs{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:991.98px){.convivencia-dashboard__chart-tabs{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){.convivencia-dashboard__hero{padding:1rem;border-radius:18px}.convivencia-dashboard__hero-actions{display:grid;width:100%;grid-template-columns:1fr}.convivencia-dashboard__hero-actions .btn{justify-content:center}.convivencia-dashboard__filters,.convivencia-dashboard__kpis,.convivencia-dashboard__charts,.convivencia-dashboard__chart-tabs{grid-template-columns:1fr}.convivencia-dashboard__filter-actions .btn{flex:1}.convivencia-dashboard__dataset-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.convivencia-dashboard__dataset-tabs button{justify-content:center}.convivencia-dashboard__hero p{font-size:.68rem}}
</style>
