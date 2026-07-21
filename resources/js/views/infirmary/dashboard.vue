<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import {
  basicApexOptions,
  downloadPdfReport,
  extractChartLabels,
  extractChartTotals,
  formatInfirmaryDate,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  normalizeOptions,
} from "../../components/infirmary/module-utils";

const emptyDashboard = () => ({
  generated_at: null,
  date_range: {},
  metrics: {},
  metric_comparisons: {},
  insights: {},
  operational: {},
  health_profile: {
    total_students: 0,
    students_with_health_information: 0,
    health_information_coverage: 0,
    chronic_illnesses: 0,
    medication_allergies: 0,
    food_allergies: 0,
    physical_restrictions: 0,
    not_fit_for_physical_education: 0,
    private_school_insurance: 0,
    health_insurance_distribution: [],
    blood_type_distribution: [],
  },
  charts: {
    activity_trend: { labels: [], attentions: [], accidents: [], granularity: "daily" },
    attentions_by_category: [],
    attentions_by_course: [],
    attentions_by_hour: [],
    accidents_by_location: [],
    accidents_by_dependency: [],
    treatment_categories: [],
    frequent_treatments: [],
    medications_administered: [],
    referrals: [],
    administration_outcomes: [],
  },
  recent: {
    attentions: [],
    accidents: [],
    medication_alerts: [],
  },
});

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
  },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      catalogs: {
        courses: [],
        attention_categories: [],
        accident_location_options: [],
        treatment_category_options: [],
        treatment_type_options: [],
        physical_treatment_options: [],
        referral_options: [],
        capabilities: {},
      },
      filters: {
        period: "mensual",
        from: "",
        to: "",
        course_section_id: null,
        attention_category: null,
        accident_location_type: null,
      },
      periodOptions: [
        { value: "diario", label: "Hoy" },
        { value: "semanal", label: "Semana" },
        { value: "mensual", label: "Mes" },
        { value: "semestral", label: "Semestre" },
        { value: "anual", label: "Año" },
        { value: "personalizado", label: "Personalizado" },
      ],
      dashboard: emptyDashboard(),
      refreshTimer: null,
      dashboardRequestId: 0,
    };
  },
  computed: {
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses, true, "Todos los cursos");
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.attention_categories, true, "Todas las categorías");
    },
    locationOptions() {
      return normalizeOptions(this.catalogs.accident_location_options, true, "Todos los lugares");
    },
    hasActiveDimensions() {
      return Boolean(
        this.filters.course_section_id ||
          this.filters.attention_category ||
          this.filters.accident_location_type ||
          this.filters.period !== "mensual"
      );
    },
    metricCards() {
      const metrics = this.dashboard.metrics || {};

      return [
        {
          key: "attentions_total",
          label: "Atenciones",
          value: this.formatNumber(metrics.attentions_total),
          icon: "bx-plus-medical",
          tone: "blue",
        },
        {
          key: "unique_students",
          label: "Estudiantes atendidas",
          value: this.formatNumber(metrics.unique_students),
          icon: "bx-group",
          tone: "teal",
        },
        {
          key: "accidents_total",
          label: "Accidentes",
          value: this.formatNumber(metrics.accidents_total),
          icon: "bx-first-aid",
          tone: "coral",
        },
        {
          key: "medications_administered_total",
          label: "Dosis administradas",
          value: this.formatNumber(metrics.medications_administered_total),
          icon: "bxs-capsule",
          tone: "green",
        },
        {
          key: "medication_adherence",
          label: "Cumplimiento de medicación",
          value: `${this.formatNumber(metrics.medication_adherence, 1)}%`,
          icon: "bx-check-shield",
          tone: "violet",
        },
        {
          key: "referrals_total",
          label: "Derivaciones",
          value: this.formatNumber(metrics.referrals_total),
          icon: "bx-transfer-alt",
          tone: "amber",
        },
        {
          key: "calls_total",
          label: "Llamados",
          value: this.formatNumber(metrics.calls_total),
          icon: "bx-phone-call",
          tone: "slate",
        },
        {
          key: "average_attention_minutes",
          label: "Duración promedio",
          value: `${this.formatNumber(metrics.average_attention_minutes, 1)} min`,
          icon: "bx-time-five",
          tone: "cyan",
        },
      ];
    },
    insightItems() {
      const insights = this.dashboard.insights || {};

      return [
        {
          label: "Categoría principal",
          value: this.categoryLabel(insights.top_category?.label),
          detail: `${insights.top_category?.total || 0} atenciones`,
        },
        {
          label: "Curso con más demanda",
          value: insights.top_course?.label || "Sin datos",
          detail: `${insights.top_course?.total || 0} atenciones`,
        },
        {
          label: "Horario de mayor carga",
          value: insights.peak_hour?.label || "Sin datos",
          detail: `${insights.peak_hour?.total || 0} atenciones`,
        },
        {
          label: "Pico del período",
          value: this.formatTrendLabel(insights.busiest_period?.label, insights.busiest_period?.granularity),
          detail: `${insights.busiest_period?.total || 0} atenciones`,
        },
        {
          label: "Tasa de accidentes",
          value: `${this.formatNumber(insights.accident_rate, 1)}%`,
          detail: "Sobre atenciones registradas",
        },
        {
          label: "Tasa de derivación",
          value: `${this.formatNumber(insights.referral_rate, 1)}%`,
          detail: "Sobre atenciones registradas",
        },
      ];
    },
    operationalItems() {
      const operational = this.dashboard.operational || {};

      return [
        { label: "Dosis atrasadas hoy", value: operational.overdue_doses_today || 0, status: "vencido", icon: "bx-time" },
        { label: "Rutinas pendientes hoy", value: operational.pending_medication_routines || 0, status: "pendiente", icon: "bx-list-check" },
        { label: "Incidencias de medicación", value: operational.medication_incidents_today || 0, status: "no_administrada", icon: "bx-error" },
        { label: "Stock crítico", value: operational.critical_stock || 0, status: "stock_bajo", icon: "bx-package" },
        { label: "Medicamentos vencidos", value: operational.expired_medications || 0, status: "vencido", icon: "bx-calendar-x" },
        { label: "Medicamentos por vencer", value: operational.expiring_medications || 0, status: "proximo_a_vencer", icon: "bx-calendar-event" },
        { label: "Seguimientos pendientes", value: operational.pending_follow_ups || 0, status: "pendiente", icon: "bx-revision" },
        { label: "Llamados pendientes", value: operational.pending_calls || 0, status: "pendiente", icon: "bx-phone" },
        { label: "Accidentes abiertos", value: operational.open_accidents || 0, status: "abierto", icon: "bx-folder-open" },
        { label: "Autorizaciones por vencer", value: operational.expiring_authorizations || 0, status: "proxima_a_vencer", icon: "bx-file" },
      ];
    },
    healthProfileItems() {
      const health = this.dashboard.health_profile || {};

      return [
        { label: "Estudiantes activos", value: health.total_students || 0, icon: "bx-group", tone: "blue" },
        { label: "Ficha de salud informada", value: health.students_with_health_information || 0, icon: "bx-clipboard", tone: "teal" },
        { label: "Enfermedad crónica", value: health.chronic_illnesses || 0, icon: "bx-heart", tone: "coral" },
        { label: "Alergia a medicamentos", value: health.medication_allergies || 0, icon: "bxs-capsule", tone: "violet" },
        { label: "Alergia alimentaria", value: health.food_allergies || 0, icon: "bx-food-menu", tone: "amber" },
        { label: "Restricción física", value: health.physical_restrictions || 0, icon: "bx-run", tone: "slate" },
        { label: "No apto para Ed. Física", value: health.not_fit_for_physical_education || 0, icon: "bx-error-circle", tone: "coral" },
        { label: "Seguro escolar privado", value: health.private_school_insurance || 0, icon: "bx-shield-quarter", tone: "green" },
      ];
    },
    healthInsuranceChartOptions() {
      return this.barOptions(this.dashboard.health_profile?.health_insurance_distribution, "#3568d4", true);
    },
    bloodTypeChartOptions() {
      return this.donutOptions(
        (this.dashboard.health_profile?.blood_type_distribution || []).map((item) => item.label),
        ["#e56b58", "#3568d4", "#27a59a", "#e5a13d", "#735dd0", "#4d8fa8", "#cf6fa6", "#7d8998"]
      );
    },
    activityChartOptions() {
      const trend = this.dashboard.charts?.activity_trend || {};

      return {
        ...basicApexOptions({ colors: ["#3568d4", "#e56b58"] }),
        chart: {
          type: "area",
          toolbar: { show: false },
          zoom: { enabled: false },
          fontFamily: "inherit",
          foreColor: "#7b8798",
          animations: { enabled: true, easing: "easeinout", speed: 500 },
        },
        colors: ["#3b6ee8", "#ef755f"],
        dataLabels: { enabled: false },
        fill: {
          type: "gradient",
          gradient: {
            shadeIntensity: 0.2,
            opacityFrom: 0.32,
            opacityTo: 0.03,
            stops: [0, 82, 100],
          },
        },
        legend: {
          show: true,
          position: "top",
          horizontalAlign: "right",
          fontSize: "12px",
          markers: { width: 8, height: 8, radius: 8 },
          itemMargin: { horizontal: 10 },
        },
        stroke: { curve: "smooth", width: [3, 2.5], lineCap: "round" },
        markers: {
          size: 0,
          hover: { size: 6, sizeOffset: 2 },
          strokeWidth: 3,
          strokeColors: "#ffffff",
        },
        grid: {
          borderColor: "#e9edf4",
          strokeDashArray: 4,
          padding: { left: 8, right: 12, bottom: 4 },
        },
        xaxis: {
          categories: (trend.labels || []).map((label) => this.formatTrendLabel(label, trend.granularity)),
          tickAmount: Math.min((trend.labels || []).length, 8),
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: { rotate: -35, hideOverlappingLabels: true, style: { fontSize: "11px" } },
        },
        yaxis: {
          min: 0,
          forceNiceScale: true,
          decimalsInFloat: 0,
          labels: { formatter: (value) => Math.round(value).toLocaleString("es-CL") },
        },
        tooltip: {
          shared: true,
          intersect: false,
          theme: "light",
          y: { formatter: (value) => `${Number(value || 0).toLocaleString("es-CL")} registros` },
        },
      };
    },
    categoryChartOptions() {
      return this.donutOptions(
        (this.dashboard.charts?.attentions_by_category || []).map((item) => this.categoryLabel(item.label)),
        ["#3568d4", "#27a59a", "#e5a13d", "#e56b58", "#735dd0", "#4d8fa8", "#7d8998", "#cf6fa6"]
      );
    },
    courseChartOptions() {
      return this.barOptions(this.dashboard.charts?.attentions_by_course, "#27a59a", true);
    },
    hourChartOptions() {
      return this.barOptions(this.dashboard.charts?.attentions_by_hour, "#3568d4", false, true);
    },
    accidentLocationChartOptions() {
      return this.donutOptions(
        (this.dashboard.charts?.accidents_by_location || []).map((item) => this.locationLabel(item.label)),
        ["#e56b58", "#e5a13d", "#7d8998"]
      );
    },
    dependencyChartOptions() {
      return this.barOptions(this.dashboard.charts?.accidents_by_dependency, "#e56b58", true);
    },
    treatmentChartOptions() {
      return this.barOptions(
        (this.dashboard.charts?.frequent_treatments || []).map((item) => ({
          ...item,
          label: this.treatmentLabel(item.label),
        })),
        "#735dd0",
        true
      );
    },
    medicationChartOptions() {
      return this.barOptions(this.dashboard.charts?.medications_administered, "#27966f", true);
    },
    referralChartOptions() {
      return this.barOptions(
        (this.dashboard.charts?.referrals || []).map((item) => ({
          ...item,
          label: this.referralLabel(item.label),
        })),
        "#e5a13d",
        true
      );
    },
  },
  mounted() {
    this.loadInitialData();
    this.startAutoRefresh();
    window.addEventListener("focus", this.refreshWhenVisible);
    document.addEventListener("visibilitychange", this.refreshWhenVisible);
  },
  beforeUnmount() {
    this.stopAutoRefresh();
    window.removeEventListener("focus", this.refreshWhenVisible);
    document.removeEventListener("visibilitychange", this.refreshWhenVisible);
  },
  methods: {
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    async loadInitialData() {
      this.loading = true;
      this.error = null;

      try {
        const [catalogResponse, dashboardResponse] = await Promise.all([
          axios.get("/api/infirmary/catalogs"),
          axios.get("/api/infirmary/dashboard", { params: this.dashboardParams() }),
        ]);
        this.catalogs = { ...this.catalogs, ...catalogResponse.data };
        this.dashboard = dashboardResponse.data;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el dashboard de enfermería.");
      } finally {
        this.loading = false;
      }
    },
    async loadDashboard({ silent = false } = {}) {
      if (this.loading) return;

      const requestId = ++this.dashboardRequestId;

      if (!silent) {
        this.loading = true;
        this.error = null;
      }

      try {
        const response = await axios.get("/api/infirmary/dashboard", { params: this.dashboardParams() });
        if (requestId === this.dashboardRequestId) {
          this.dashboard = response.data;
        }
      } catch (error) {
        if (!silent && requestId === this.dashboardRequestId) {
          this.error = formatInfirmaryError(error, "No se pudieron aplicar los filtros del dashboard.");
        }
      } finally {
        if (!silent && requestId === this.dashboardRequestId) {
          this.loading = false;
        }
      }
    },
    startAutoRefresh() {
      this.stopAutoRefresh();
      this.refreshTimer = window.setInterval(() => {
        if (document.visibilityState === "visible") {
          this.loadDashboard({ silent: true });
        }
      }, 60000);
    },
    stopAutoRefresh() {
      if (this.refreshTimer) {
        window.clearInterval(this.refreshTimer);
        this.refreshTimer = null;
      }
    },
    refreshWhenVisible() {
      if (document.visibilityState === "visible") {
        this.loadDashboard({ silent: true });
      }
    },
    lastUpdatedLabel() {
      if (!this.dashboard.generated_at) return "Actualizando datos...";

      return `Actualizado ${formatInfirmaryDateTime(this.dashboard.generated_at)}`;
    },
    dashboardParams() {
      const params = {
        period: this.filters.period,
        course_section_id: this.filters.course_section_id || undefined,
        attention_category: this.filters.attention_category || undefined,
        accident_location_type: this.filters.accident_location_type || undefined,
      };

      if (this.filters.period === "personalizado") {
        params.from = this.filters.from;
        params.to = this.filters.to;
      }

      return params;
    },
    async selectPeriod(period) {
      this.filters.period = period;

      if (period === "personalizado") {
        this.filters.from = this.filters.from || this.dashboard.date_range?.from || "";
        this.filters.to = this.filters.to || this.dashboard.date_range?.to || "";
        return;
      }

      await this.loadDashboard();
    },
    async clearFilters() {
      this.filters = {
        period: "mensual",
        from: "",
        to: "",
        course_section_id: null,
        attention_category: null,
        accident_location_type: null,
      };
      await this.loadDashboard();
    },
    formatNumber(value, maximumFractionDigits = 0) {
      return Number(value || 0).toLocaleString("es-CL", {
        minimumFractionDigits: 0,
        maximumFractionDigits,
      });
    },
    comparisonMeta(key) {
      const comparison = this.dashboard.metric_comparisons?.[key] || {};

      if (comparison.change === null || comparison.change === undefined) {
        return { icon: "bx-minus", text: "Sin base anterior", className: "is-neutral" };
      }

      const value = Number(comparison.change || 0);
      if (value === 0) {
        return { icon: "bx-minus", text: "Sin variación", className: "is-neutral" };
      }

      return {
        icon: value > 0 ? "bx-up-arrow-alt" : "bx-down-arrow-alt",
        text: `${Math.abs(value).toLocaleString("es-CL", { maximumFractionDigits: 1 })}% vs. período anterior`,
        className: value > 0 ? "is-up" : "is-down",
      };
    },
    optionLabel(options, value, fallback = "Sin datos") {
      if (!value) return fallback;
      const item = (options || []).find((option) => String(option.value ?? option.id) === String(value));
      return item?.label || item?.text || item?.name || item?.display_name || humanizeInfirmaryStatus(value);
    },
    categoryLabel(value) {
      return this.optionLabel(this.catalogs.attention_categories, value);
    },
    locationLabel(value) {
      return this.optionLabel(this.catalogs.accident_location_options, value);
    },
    treatmentLabel(value) {
      const options = []
        .concat(this.catalogs.physical_treatment_options || [])
        .concat(this.catalogs.treatment_type_options || [])
        .concat(this.catalogs.treatment_category_options || []);
      return this.optionLabel(options, value);
    },
    referralLabel(value) {
      return this.optionLabel(this.catalogs.referral_options, value);
    },
    formatTrendLabel(value, granularity = "daily") {
      if (!value || value === "Sin datos") return "Sin datos";

      if (granularity === "monthly") {
        const [year, month] = String(value).split("-");
        const date = new Date(Number(year), Number(month) - 1, 1);
        return date.toLocaleDateString("es-CL", { month: "short", year: "numeric" });
      }

      const formatted = formatInfirmaryDate(value);
      return granularity === "weekly" ? `Sem. ${formatted}` : formatted;
    },
    donutOptions(labels, colors) {
      return {
        labels: labels.length ? labels : ["Sin datos"],
        colors: labels.length ? colors : ["#d9dee7"],
        chart: {
          fontFamily: "inherit",
          foreColor: "#697588",
          animations: { enabled: true, easing: "easeinout", speed: 450 },
        },
        legend: {
          position: "bottom",
          fontSize: "12px",
          fontWeight: 500,
          markers: { width: 8, height: 8, radius: 8 },
          itemMargin: { horizontal: 8, vertical: 5 },
        },
        dataLabels: {
          enabled: true,
          dropShadow: { enabled: false },
          formatter: (percentage) => (percentage >= 6 ? `${percentage.toFixed(0)}%` : ""),
        },
        stroke: { colors: ["#ffffff"], width: 4 },
        plotOptions: {
          pie: {
            expandOnClick: false,
            donut: {
              size: "70%",
              labels: {
                show: true,
                name: { show: true, offsetY: 18, color: "#7b8798", fontSize: "12px" },
                value: { show: true, offsetY: -16, color: "#263348", fontSize: "25px", fontWeight: 700 },
                total: {
                  show: true,
                  label: "Total",
                  color: "#7b8798",
                  formatter: (chart) => chart.globals.seriesTotals.reduce((total, value) => total + value, 0).toLocaleString("es-CL"),
                },
              },
            },
          },
        },
        tooltip: { y: { formatter: (value) => `${Number(value || 0).toLocaleString("es-CL")} registros` } },
        responsive: [{ breakpoint: 480, options: { chart: { height: 285 }, legend: { position: "bottom" } } }],
      };
    },
    barOptions(items = [], color = "#3568d4", horizontal = false, distributed = false) {
      const palette = [color, "#5f84e8", "#6c9ce2", "#53aeb0", "#7c72d8", "#e6a74d", "#e67a67", "#8491a5"];

      return {
        ...basicApexOptions({
          categories: extractChartLabels(items),
          colors: distributed ? palette : [color],
          horizontal,
          distributed,
        }),
        chart: {
          type: "bar",
          toolbar: { show: false },
          fontFamily: "inherit",
          foreColor: "#748094",
          animations: { enabled: true, easing: "easeinout", speed: 450 },
        },
        colors: distributed ? palette : [color],
        plotOptions: {
          bar: {
            horizontal,
            distributed,
            borderRadius: 5,
            borderRadiusApplication: "end",
            barHeight: horizontal ? "58%" : undefined,
            columnWidth: horizontal ? undefined : "52%",
          },
        },
        dataLabels: {
          enabled: horizontal,
          textAnchor: "start",
          offsetX: 7,
          style: { fontSize: "11px", fontWeight: 700, colors: ["#ffffff"] },
          formatter: (value) => Number(value || 0).toLocaleString("es-CL"),
        },
        fill: {
          type: "gradient",
          gradient: { shade: "light", type: horizontal ? "horizontal" : "vertical", shadeIntensity: 0.14, opacityFrom: 1, opacityTo: 0.78, stops: [0, 100] },
        },
        grid: {
          borderColor: "#e9edf4",
          strokeDashArray: 4,
          xaxis: { lines: { show: horizontal } },
          yaxis: { lines: { show: !horizontal } },
          padding: { left: horizontal ? 8 : 4, right: 14 },
        },
        xaxis: {
          categories: extractChartLabels(items),
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: {
            trim: true,
            style: { fontSize: "11px" },
            formatter: horizontal ? (value) => Number(value || 0).toLocaleString("es-CL") : undefined,
          },
        },
        yaxis: {
          labels: {
            maxWidth: horizontal ? 190 : 120,
            style: { fontSize: "11px", fontWeight: 500 },
            formatter: horizontal ? undefined : (value) => Math.round(value).toLocaleString("es-CL"),
          },
        },
        legend: { show: false },
        tooltip: {
          shared: false,
          theme: "light",
          y: { formatter: (value) => Number(value || 0).toLocaleString("es-CL") },
        },
      };
    },
    chartSeries(items, name) {
      return [{ name, data: extractChartTotals(items) }];
    },
    donutSeries(items) {
      const totals = extractChartTotals(items);
      return totals.length ? totals : [0];
    },
    activitySeries() {
      const trend = this.dashboard.charts?.activity_trend || {};
      return [
        { name: "Atenciones", data: trend.attentions || [] },
        { name: "Accidentes", data: trend.accidents || [] },
      ];
    },
    chartHeight(items, minimum = 300) {
      return Math.max(minimum, Math.min(430, (items?.length || 0) * 34 + 120));
    },
    courseLabel() {
      return this.optionLabel(this.catalogs.courses, this.filters.course_section_id, "Todos los cursos");
    },
    async exportPdf() {
      if (!this.canExport || this.exporting) return;

      this.exporting = true;

      try {
        const range = this.dashboard.date_range || {};
        const metrics = this.dashboard.metrics || {};
        const charts = this.dashboard.charts || {};
        const operational = this.dashboard.operational || {};
        const fileSuffix = `${range.from || "inicio"}_${range.to || "fin"}`;

        const chartsForPdf = await this.collectPdfCharts();

        downloadPdfReport(
          `dashboard_enfermeria_${fileSuffix}`,
          "Dashboard de Enfermería",
          `Datos visualizados del ${range.label || "período seleccionado"}. Comparación: ${range.comparison_label || "sin período anterior"}.`,
          [
            {
              title: "Filtros aplicados",
              headers: ["Filtro", "Selección"],
              rows: [
                ["Período", range.label || "-"],
                ["Curso", this.courseLabel()],
                [
                  "Categoría",
                  this.optionLabel(this.catalogs.attention_categories, this.filters.attention_category, "Todas las categorías"),
                ],
                [
                  "Lugar del accidente",
                  this.optionLabel(this.catalogs.accident_location_options, this.filters.accident_location_type, "Todos los lugares"),
                ],
              ],
            },
            {
              title: "Indicadores generales",
              headers: ["Indicador", "Resultado", "Período anterior", "Variación"],
              rows: this.metricCards.map((card) => {
                const comparison = this.dashboard.metric_comparisons?.[card.key] || {};
                return [
                  card.label,
                  card.value,
                  this.formatNumber(comparison.previous, card.key.includes("minutes") || card.key.includes("adherence") ? 1 : 0),
                  comparison.change === null || comparison.change === undefined
                    ? "Sin base"
                    : `${this.formatNumber(comparison.change, 1)}%`,
                ];
              }),
            },
            {
              title: "Indicadores de resultado",
              headers: ["Indicador", "Resultado"],
              rows: [
                ["Tasa de accidentes", `${this.formatNumber(metrics.accident_rate, 1)}%`],
                ["Tasa de derivación", `${this.formatNumber(metrics.referral_rate, 1)}%`],
                ["Cumplimiento de medicación", `${this.formatNumber(metrics.medication_adherence, 1)}%`],
                ["Dosis no administradas", this.formatNumber(metrics.medications_not_administered_total)],
              ],
            },
            {
              title: "Perfil de salud estudiantil (corte actual)",
              headers: ["Indicador", "Estudiantes"],
              rows: this.healthProfileItems.map((item) => [item.label, item.value]),
            },
            this.pdfDistribution("Previsión de salud", this.dashboard.health_profile?.health_insurance_distribution),
            this.pdfDistribution("Grupo sanguíneo", this.dashboard.health_profile?.blood_type_distribution),
            {
              title: "Evolución del período",
              headers: ["Tramo", "Atenciones", "Accidentes"],
              rows: (charts.activity_trend?.labels || []).map((label, index) => [
                this.formatTrendLabel(label, charts.activity_trend?.granularity),
                charts.activity_trend?.attentions?.[index] || 0,
                charts.activity_trend?.accidents?.[index] || 0,
              ]),
            },
            this.pdfDistribution("Atenciones por categoría", charts.attentions_by_category, (value) => this.categoryLabel(value)),
            this.pdfDistribution("Atenciones por curso", charts.attentions_by_course),
            this.pdfDistribution("Atenciones por hora", charts.attentions_by_hour),
            this.pdfDistribution("Accidentes por lugar", charts.accidents_by_location, (value) => this.locationLabel(value)),
            this.pdfDistribution("Accidentes por dependencia", charts.accidents_by_dependency),
            this.pdfDistribution("Categorías de tratamiento", charts.treatment_categories, (value) => this.treatmentLabel(value)),
            this.pdfDistribution("Tratamientos aplicados", charts.frequent_treatments, (value) => this.treatmentLabel(value)),
            this.pdfDistribution("Medicamentos administrados", charts.medications_administered),
            this.pdfDistribution("Resultado de administraciones", charts.administration_outcomes, (value) => humanizeInfirmaryStatus(value)),
            this.pdfDistribution("Derivaciones", charts.referrals, (value) => this.referralLabel(value)),
            {
              title: "Estado operativo actual",
              headers: ["Alerta", "Cantidad"],
              rows: this.operationalItems.map((item) => [item.label, operational[this.operationalKey(item.label)] ?? item.value]),
            },
            {
              title: "Atenciones recientes",
              headers: ["Fecha", "Estudiante", "Curso", "Categoría", "Motivo"],
              rows: (this.dashboard.recent?.attentions || []).map((item) => [
                formatInfirmaryDateTime(item.attended_at), item.student_full_name_snapshot, item.course_name_snapshot || "Sin curso",
                this.categoryLabel(item.attention_category), item.consultation_reason || "-",
              ]),
              pageBreakBefore: true,
            },
            {
              title: "Accidentes recientes",
              headers: ["Fecha", "Estudiante", "Tipo", "Lugar", "Circunstancia"],
              rows: (this.dashboard.recent?.accidents || []).map((item) => [
                formatInfirmaryDateTime(item.occurred_at), item.student_full_name_snapshot,
                this.categoryLabel(item.attention_category), item.dependency?.name || this.locationLabel(item.accident_location_type),
                item.accident_circumstance || "-",
              ]),
            },
            {
              title: "Alertas de inventario clínico",
              headers: ["Medicamento", "Stock", "Mínimo", "Vencimiento", "Estado"],
              rows: (this.dashboard.recent?.medication_alerts || []).map((item) => [
                item.commercial_name || item.name, item.current_stock, item.minimum_stock,
                formatInfirmaryDate(item.expires_at), humanizeInfirmaryStatus(item.status),
              ]),
            },
          ],
          {
            organization: "Colegio Nuestra Señora del Carmen",
            generatedAt: new Date().toLocaleString("es-CL"),
            summary: `El informe consolida ${this.formatNumber(metrics.attentions_total)} atenciones, ${this.formatNumber(metrics.unique_students)} estudiantes atendidos y ${this.formatNumber(metrics.accidents_total)} accidentes. El perfil de salud tiene ${this.formatNumber(this.dashboard.health_profile?.health_information_coverage, 1)}% de cobertura.`,
            charts: chartsForPdf,
          }
        );
      } finally {
        this.exporting = false;
      }
    },
    async collectPdfCharts() {
      const definitions = [
        ["activityChart", "Evolución de atenciones y accidentes", "Actividad dentro del período seleccionado."],
        ["categoryChart", "Motivos de atención", "Distribución por categoría clínica."],
        ["healthInsuranceChart", "Previsión de salud", "Corte actual de estudiantes activos."],
        ["bloodTypeChart", "Grupo sanguíneo", "Registros con grupo sanguíneo informado."],
        ["courseChart", "Demanda por curso", "Cursos con mayor número de atenciones."],
        ["hourChart", "Carga horaria", "Distribución de atenciones según hora de ingreso."],
        ["accidentLocationChart", "Lugar de los accidentes", "Distribución entre colegio, trayecto y registros sin ubicación."],
        ["accidentDependencyChart", "Accidentes por dependencia", "Espacios con accidentes registrados."],
        ["treatmentChart", "Tratamientos aplicados", "Procedimientos más frecuentes."],
        ["medicationChart", "Medicamentos administrados", "Dosis administradas por medicamento."],
        ["referralChart", "Derivaciones", "Destinos registrados desde Enfermería."],
      ];

      await this.$nextTick();
      await new Promise((resolve) => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));

      const charts = [];
      for (const [ref, title, caption] of definitions) {
        const component = this.$refs[ref];
        const element = component?.$el;
        if (!component || typeof component.dataURI !== "function" || !element?.isConnected) continue;

        try {
          const result = await component.dataURI({ scale: 1.5 });
          if (result?.imgURI) {
            charts.push({ title, caption, image: result.imgURI });
          }
        } catch (_) {
          // Un gráfico sin renderizar no debe impedir la descarga del informe.
        }
      }

      return charts;
    },
    pdfDistribution(title, items = [], labelFormatter = (value) => value) {
      return {
        title,
        headers: ["Categoría", "Total"],
        rows: (items || []).map((item) => [labelFormatter(item.label), item.total]),
      };
    },
    operationalKey(label) {
      const mapping = {
        "Dosis atrasadas hoy": "overdue_doses_today",
        "Rutinas pendientes hoy": "pending_medication_routines",
        "Incidencias de medicación": "medication_incidents_today",
        "Stock crítico": "critical_stock",
        "Medicamentos vencidos": "expired_medications",
        "Medicamentos por vencer": "expiring_medications",
        "Seguimientos pendientes": "pending_follow_ups",
        "Llamados pendientes": "pending_calls",
        "Accidentes abiertos": "open_accidents",
        "Autorizaciones por vencer": "expiring_authorizations",
      };
      return mapping[label];
    },
  },
};
</script>

<template>
  <Layout>
    <main class="infirmary-analytics">
      <header class="dashboard-header mb-3">
        <div>
          <h4 class="mb-1">Estadísticas de Enfermería</h4>
          <p class="text-muted mb-0">Actividad clínica, accidentes, tratamientos, medicación y alertas operativas.</p>
          <small class="text-muted">{{ lastUpdatedLabel() }} · actualización automática cada minuto</small>
        </div>
        <div class="dashboard-actions">
          <InfirmaryHelpButton
            title="Ayuda: estadísticas de enfermería"
            text="Los indicadores y gráficos usan el mismo período y filtros. Las variaciones comparan contra un período anterior de igual duración."
          />
          <BButton
            v-if="canExport"
            variant="outline-danger"
            :disabled="loading || exporting"
            title="Exportar dashboard a PDF"
            @click="exportPdf"
          >
            <i class="bx bxs-file-pdf me-1" aria-hidden="true"></i>
            {{ exporting ? "Generando..." : "Exportar PDF" }}
          </BButton>
          <BButton
            variant="outline-secondary"
            class="icon-command"
            :disabled="loading"
            title="Actualizar datos"
            aria-label="Actualizar datos"
            @click="loadDashboard"
          >
            <i class="bx bx-sync" :class="{ 'bx-spin': loading }" aria-hidden="true"></i>
          </BButton>
        </div>
      </header>

      <section class="analytics-toolbar mb-3" aria-label="Filtros del dashboard">
        <div class="toolbar-topline">
          <div>
            <div class="toolbar-label">Temporalidad</div>
            <div class="period-segmented" role="group" aria-label="Seleccionar temporalidad">
              <button
                v-for="option in periodOptions"
                :key="option.value"
                type="button"
                class="period-option"
                :class="{ active: filters.period === option.value }"
                :aria-pressed="filters.period === option.value"
                @click="selectPeriod(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>
          <div class="period-readout">
            <span>Período visualizado</span>
            <strong>{{ dashboard.date_range?.label || "-" }}</strong>
            <small>Comparación: {{ dashboard.date_range?.comparison_label || "-" }}</small>
          </div>
        </div>

        <div class="filter-grid mt-3">
          <div v-if="filters.period === 'personalizado'" class="filter-field">
            <label for="dashboard-from">Desde</label>
            <BFormInput id="dashboard-from" v-model="filters.from" type="date" />
          </div>
          <div v-if="filters.period === 'personalizado'" class="filter-field">
            <label for="dashboard-to">Hasta</label>
            <BFormInput id="dashboard-to" v-model="filters.to" type="date" />
          </div>
          <div class="filter-field">
            <label for="dashboard-course">Curso</label>
            <BFormSelect id="dashboard-course" v-model="filters.course_section_id" :options="courseOptions" />
          </div>
          <div class="filter-field">
            <label for="dashboard-category">Categoría</label>
            <BFormSelect id="dashboard-category" v-model="filters.attention_category" :options="categoryOptions" />
          </div>
          <div class="filter-field">
            <label for="dashboard-location">Lugar del accidente</label>
            <BFormSelect id="dashboard-location" v-model="filters.accident_location_type" :options="locationOptions" />
          </div>
          <div class="filter-actions">
            <BButton
              variant="primary"
              :disabled="loading || (filters.period === 'personalizado' && (!filters.from || !filters.to))"
              @click="loadDashboard"
            >
              <i class="bx bx-filter-alt me-1" aria-hidden="true"></i>
              Aplicar
            </BButton>
            <BButton variant="outline-secondary" :disabled="loading || !hasActiveDimensions" @click="clearFilters">
              Limpiar
            </BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <BCard v-if="loading && !dashboard.date_range?.from" class="mb-3">
        <LoadingState message="Calculando estadísticas de enfermería..." compact />
      </BCard>

      <template v-else>
        <section class="metric-grid mb-3" aria-label="Indicadores principales">
          <article v-for="card in metricCards" :key="card.key" class="metric-card" :class="`tone-${card.tone}`">
            <div class="metric-card-head">
              <div class="metric-icon"><i :class="`bx ${card.icon}`" aria-hidden="true"></i></div>
              <span class="metric-period">{{ dashboard.date_range?.days || 0 }} días</span>
            </div>
            <div class="metric-value">{{ card.value }}</div>
            <div class="metric-label">{{ card.label }}</div>
            <div class="metric-comparison" :class="comparisonMeta(card.key).className">
              <i :class="`bx ${comparisonMeta(card.key).icon}`" aria-hidden="true"></i>
              <span>{{ comparisonMeta(card.key).text }}</span>
            </div>
          </article>
        </section>

        <section class="insight-band mb-3" aria-label="Hallazgos del período">
          <div v-for="item in insightItems" :key="item.label" class="insight-cell">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
            <small>{{ item.detail }}</small>
          </div>
        </section>

        <section class="health-profile-panel mb-3" aria-label="Estadísticas agregadas del perfil de salud estudiantil">
          <div class="section-heading">
            <div>
              <h5 class="mb-1">Perfil de salud estudiantil</h5>
              <p class="text-muted mb-0">Corte actual de estudiantes activos. Solo información agregada, sin diagnósticos ni observaciones personales.</p>
            </div>
            <span class="coverage-indicator">
              {{ formatNumber(dashboard.health_profile?.health_information_coverage, 1) }}% con información
            </span>
          </div>
          <div class="health-metric-grid mt-3">
            <article v-for="item in healthProfileItems" :key="item.label" class="health-metric" :class="`tone-${item.tone}`">
              <i :class="`bx ${item.icon}`" aria-hidden="true"></i>
              <div>
                <strong>{{ formatNumber(item.value) }}</strong>
                <span>{{ item.label }}</span>
              </div>
            </article>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-xl-7">
              <BCard class="analytics-card h-100">
                <template #header><div class="chart-heading"><div><h5>Previsión de salud</h5><span>Distribución declarada en la ficha estudiantil</span></div></div></template>
                <div v-if="!dashboard.health_profile?.health_insurance_distribution?.length" class="analytics-empty">Sin previsión informada.</div>
                <apexchart v-else ref="healthInsuranceChart" type="bar" :height="chartHeight(dashboard.health_profile?.health_insurance_distribution, 280)" :options="healthInsuranceChartOptions" :series="chartSeries(dashboard.health_profile?.health_insurance_distribution, 'Estudiantes')" />
              </BCard>
            </div>
            <div class="col-xl-5">
              <BCard class="analytics-card h-100">
                <template #header><div class="chart-heading"><div><h5>Grupo sanguíneo</h5><span>Solo registros con grupo informado</span></div></div></template>
                <div v-if="!dashboard.health_profile?.blood_type_distribution?.length" class="analytics-empty">Sin grupos sanguíneos informados.</div>
                <apexchart v-else ref="bloodTypeChart" type="donut" height="300" :options="bloodTypeChartOptions" :series="donutSeries(dashboard.health_profile?.blood_type_distribution)" />
              </BCard>
            </div>
          </div>
        </section>

        <section class="operational-band mb-3">
          <div class="section-heading">
            <div>
              <h5 class="mb-1">Estado operativo actual</h5>
              <p class="text-muted mb-0">Alertas vigentes y control diario de medicación.</p>
            </div>
            <span class="live-indicator"><span></span> Hoy</span>
          </div>
          <div class="operational-grid">
            <div v-for="item in operationalItems" :key="item.label" class="operational-item">
              <i :class="`bx ${item.icon}`" aria-hidden="true"></i>
              <div>
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
              <InfirmaryStatusBadge :status="item.status" />
            </div>
          </div>
        </section>

        <section class="row g-3 mb-3" aria-label="Evolución y categorías">
          <div class="col-xl-8">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Actividad en el tiempo</h5>
                    <span>Atenciones registradas y accidentes ocurridos</span>
                  </div>
                  <span class="chart-range">{{ dashboard.date_range?.label }}</span>
                </div>
              </template>
              <apexchart ref="activityChart" type="area" height="330" :options="activityChartOptions" :series="activitySeries()" />
            </BCard>
          </div>
          <div class="col-xl-4">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Motivos de atención</h5>
                    <span>Distribución por categoría clínica</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.attentions_by_category?.length" class="analytics-empty">Sin motivos registrados para el período.</div>
              <apexchart
                v-else
                ref="categoryChart"
                type="donut"
                height="330"
                :options="categoryChartOptions"
                :series="donutSeries(dashboard.charts?.attentions_by_category)"
              />
            </BCard>
          </div>
        </section>

        <section class="row g-3 mb-3" aria-label="Demanda por curso y horario">
          <div class="col-xl-7">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Demanda por curso</h5>
                    <span>Cursos con mayor cantidad de atenciones</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.attentions_by_course?.length" class="analytics-empty">Sin datos para el período.</div>
              <apexchart
                ref="courseChart"
                v-else
                type="bar"
                :height="chartHeight(dashboard.charts?.attentions_by_course, 300)"
                :options="courseChartOptions"
                :series="chartSeries(dashboard.charts?.attentions_by_course, 'Atenciones')"
              />
            </BCard>
          </div>
          <div class="col-xl-5">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Carga horaria</h5>
                    <span>Atenciones según hora de ingreso</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.attentions_by_hour?.length" class="analytics-empty">Sin datos para el período.</div>
              <apexchart
                ref="hourChart"
                v-else
                type="bar"
                height="300"
                :options="hourChartOptions"
                :series="chartSeries(dashboard.charts?.attentions_by_hour, 'Atenciones')"
              />
            </BCard>
          </div>
        </section>

        <section class="row g-3 mb-3" aria-label="Análisis de accidentes">
          <div class="col-xl-4">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Lugar del accidente</h5>
                    <span>Colegio o trayecto</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.accidents_by_location?.length" class="analytics-empty">Sin accidentes para el período.</div>
              <apexchart
                v-else
                ref="accidentLocationChart"
                type="donut"
                height="310"
                :options="accidentLocationChartOptions"
                :series="donutSeries(dashboard.charts?.accidents_by_location)"
              />
            </BCard>
          </div>
          <div class="col-xl-8">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Accidentes por dependencia</h5>
                    <span>Espacios con mayor frecuencia de accidentes</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.accidents_by_dependency?.length" class="analytics-empty">Sin accidentes para el período.</div>
              <apexchart
                ref="accidentDependencyChart"
                v-else
                type="bar"
                :height="chartHeight(dashboard.charts?.accidents_by_dependency, 310)"
                :options="dependencyChartOptions"
                :series="chartSeries(dashboard.charts?.accidents_by_dependency, 'Accidentes')"
              />
            </BCard>
          </div>
        </section>

        <section class="row g-3 mb-3" aria-label="Tratamientos, medicamentos y derivaciones">
          <div class="col-xl-4">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Tratamientos aplicados</h5>
                    <span>Procedimientos más frecuentes</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.frequent_treatments?.length" class="analytics-empty">Sin tratamientos registrados.</div>
              <apexchart
                ref="treatmentChart"
                v-else
                type="bar"
                :height="chartHeight(dashboard.charts?.frequent_treatments, 320)"
                :options="treatmentChartOptions"
                :series="chartSeries(dashboard.charts?.frequent_treatments, 'Aplicaciones')"
              />
            </BCard>
          </div>
          <div class="col-xl-4">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Medicamentos administrados</h5>
                    <span>Dosis administradas por medicamento</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.medications_administered?.length" class="analytics-empty">Sin dosis administradas.</div>
              <apexchart
                ref="medicationChart"
                v-else
                type="bar"
                :height="chartHeight(dashboard.charts?.medications_administered, 320)"
                :options="medicationChartOptions"
                :series="chartSeries(dashboard.charts?.medications_administered, 'Dosis')"
              />
            </BCard>
          </div>
          <div class="col-xl-4">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Derivaciones</h5>
                    <span>Destinos registrados desde enfermería</span>
                  </div>
                </div>
              </template>
              <div v-if="!dashboard.charts?.referrals?.length" class="analytics-empty">Sin derivaciones registradas.</div>
              <apexchart
                ref="referralChart"
                v-else
                type="bar"
                :height="chartHeight(dashboard.charts?.referrals, 320)"
                :options="referralChartOptions"
                :series="chartSeries(dashboard.charts?.referrals, 'Derivaciones')"
              />
            </BCard>
          </div>
        </section>

        <section class="row g-3" aria-label="Últimos registros y alertas de inventario">
          <div class="col-xxl-6">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Atenciones recientes del período</h5>
                    <span>Últimos registros incluidos en los filtros</span>
                  </div>
                </div>
              </template>
              <div class="table-responsive">
                <table class="table analytics-table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Estudiante</th>
                      <th>Curso</th>
                      <th>Categoría</th>
                      <th>Motivo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in dashboard.recent?.attentions" :key="item.id">
                      <td class="text-nowrap">{{ formatInfirmaryDateTime(item.attended_at) }}</td>
                      <td class="fw-semibold">{{ item.student_full_name_snapshot }}</td>
                      <td>{{ item.course_name_snapshot || "Sin curso" }}</td>
                      <td>{{ categoryLabel(item.attention_category) }}</td>
                      <td class="text-truncate-cell">{{ item.consultation_reason || "-" }}</td>
                    </tr>
                    <tr v-if="!dashboard.recent?.attentions?.length">
                      <td colspan="5" class="text-center text-muted py-4">Sin atenciones en el período.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
          <div class="col-xxl-6">
            <BCard class="analytics-card h-100">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Accidentes recientes del período</h5>
                    <span>Contexto y lugar de los últimos accidentes</span>
                  </div>
                </div>
              </template>
              <div class="table-responsive">
                <table class="table analytics-table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Estudiante</th>
                      <th>Tipo</th>
                      <th>Lugar</th>
                      <th>Circunstancia</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in dashboard.recent?.accidents" :key="item.id">
                      <td class="text-nowrap">{{ formatInfirmaryDateTime(item.occurred_at) }}</td>
                      <td class="fw-semibold">{{ item.student_full_name_snapshot }}</td>
                      <td>{{ categoryLabel(item.attention_category) }}</td>
                      <td>{{ item.dependency?.name || locationLabel(item.accident_location_type) }}</td>
                      <td class="text-truncate-cell">{{ item.accident_circumstance || "-" }}</td>
                    </tr>
                    <tr v-if="!dashboard.recent?.accidents?.length">
                      <td colspan="5" class="text-center text-muted py-4">Sin accidentes en el período.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
          <div class="col-12">
            <BCard class="analytics-card">
              <template #header>
                <div class="chart-heading">
                  <div>
                    <h5>Alertas de inventario clínico</h5>
                    <span>Medicamentos que requieren gestión</span>
                  </div>
                </div>
              </template>
              <div class="table-responsive">
                <table class="table analytics-table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Medicamento</th>
                      <th>Stock actual</th>
                      <th>Stock mínimo</th>
                      <th>Vencimiento</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in dashboard.recent?.medication_alerts" :key="item.id">
                      <td>
                        <strong>{{ item.commercial_name || item.name }}</strong>
                        <div v-if="item.commercial_name" class="small text-muted">{{ item.name }}</div>
                      </td>
                      <td>{{ item.current_stock }}</td>
                      <td>{{ item.minimum_stock }}</td>
                      <td>{{ formatInfirmaryDate(item.expires_at) }}</td>
                      <td><InfirmaryStatusBadge :status="item.status" /></td>
                    </tr>
                    <tr v-if="!dashboard.recent?.medication_alerts?.length">
                      <td colspan="5" class="text-center text-muted py-4">No hay alertas de inventario activas.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCard>
          </div>
        </section>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.infirmary-analytics {
  --analytics-border: #dfe5ee;
  --analytics-text: #293241;
  --analytics-muted: #6f7887;
  --analytics-shadow: 0 10px 28px rgba(37, 54, 82, 0.07);
  color: var(--analytics-text);
}

.dashboard-header,
.dashboard-actions,
.toolbar-topline,
.section-heading,
.chart-heading,
.metric-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.dashboard-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
}

.icon-command {
  width: 40px;
  height: 38px;
  padding: 0;
  display: inline-grid;
  place-items: center;
}

.icon-command i {
  font-size: 21px;
}

.analytics-toolbar,
.insight-band,
.operational-band,
.health-profile-panel {
  background: #ffffff;
  border: 1px solid var(--analytics-border);
  border-radius: 6px;
}

.analytics-toolbar {
  padding: 18px;
}

.health-profile-panel {
  padding: 18px;
}

.coverage-indicator {
  padding: 7px 11px;
  border-radius: 999px;
  background: #e9f7f4;
  color: #18776e;
  font-size: 12px;
  font-weight: 700;
}

.health-metric-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.health-metric {
  display: flex;
  align-items: center;
  gap: 11px;
  min-height: 78px;
  padding: 13px;
  border: 1px solid var(--analytics-border);
  border-left: 4px solid #3568d4;
  border-radius: 6px;
  background: #fbfcfe;
}

.health-metric > i { font-size: 24px; color: #3568d4; }
.health-metric strong { display: block; font-size: 22px; line-height: 1; }
.health-metric span { display: block; margin-top: 5px; color: var(--analytics-muted); font-size: 12px; }
.health-metric.tone-teal { border-left-color: #27a59a; }
.health-metric.tone-teal > i { color: #27a59a; }
.health-metric.tone-coral { border-left-color: #e56b58; }
.health-metric.tone-coral > i { color: #e56b58; }
.health-metric.tone-violet { border-left-color: #735dd0; }
.health-metric.tone-violet > i { color: #735dd0; }
.health-metric.tone-amber { border-left-color: #e5a13d; }
.health-metric.tone-amber > i { color: #e5a13d; }
.health-metric.tone-slate { border-left-color: #7d8998; }
.health-metric.tone-slate > i { color: #7d8998; }
.health-metric.tone-green { border-left-color: #27966f; }
.health-metric.tone-green > i { color: #27966f; }

.toolbar-label,
.filter-field label {
  display: block;
  margin-bottom: 7px;
  color: var(--analytics-muted);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.period-segmented {
  display: inline-flex;
  flex-wrap: wrap;
  padding: 3px;
  border: 1px solid #d8e0ec;
  border-radius: 6px;
  background: #f6f8fb;
}

.period-option {
  min-height: 34px;
  padding: 6px 13px;
  border: 0;
  border-radius: 4px;
  background: transparent;
  color: #5e6878;
  font-size: 13px;
  font-weight: 600;
}

.period-option:hover {
  color: #315fc0;
}

.period-option.active {
  background: #ffffff;
  color: #315fc0;
  box-shadow: 0 1px 4px rgba(34, 55, 90, 0.14);
}

.period-readout {
  min-width: 230px;
  text-align: right;
}

.period-readout span,
.period-readout small {
  display: block;
  color: var(--analytics-muted);
  font-size: 12px;
}

.period-readout strong {
  display: block;
  margin: 2px 0;
  font-size: 15px;
}

.filter-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(180px, 1fr)) auto;
  gap: 12px;
  align-items: end;
}

.filter-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.metric-card {
  min-width: 0;
  min-height: 148px;
  padding: 15px 16px 13px;
  border: 1px solid var(--analytics-border);
  border-top: 3px solid var(--metric-color, #3568d4);
  border-radius: 6px;
  background: #ffffff;
}

.metric-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 6px;
  background: color-mix(in srgb, var(--metric-color, #3568d4) 12%, white);
  color: var(--metric-color, #3568d4);
  font-size: 20px;
}

.metric-period {
  color: var(--analytics-muted);
  font-size: 11px;
}

.metric-value {
  margin-top: 10px;
  font-size: 26px;
  font-weight: 700;
  line-height: 1.05;
}

.metric-label {
  min-height: 36px;
  margin-top: 5px;
  color: #586273;
  font-size: 13px;
  font-weight: 600;
}

.metric-comparison {
  display: flex;
  align-items: center;
  gap: 3px;
  margin-top: 6px;
  color: #6f7887;
  font-size: 11px;
}

.metric-comparison.is-up {
  color: #276fc0;
}

.metric-comparison.is-down {
  color: #4b876f;
}

.tone-blue { --metric-color: #3568d4; }
.tone-teal { --metric-color: #27a59a; }
.tone-coral { --metric-color: #e56b58; }
.tone-green { --metric-color: #27966f; }
.tone-violet { --metric-color: #735dd0; }
.tone-amber { --metric-color: #d58a24; }
.tone-slate { --metric-color: #657182; }
.tone-cyan { --metric-color: #3f91aa; }

.insight-band {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  overflow: hidden;
}

.insight-cell {
  min-width: 0;
  padding: 14px 16px;
  border-right: 1px solid #edf0f4;
}

.insight-cell:last-child {
  border-right: 0;
}

.insight-cell span,
.insight-cell small {
  display: block;
  color: var(--analytics-muted);
  font-size: 11px;
}

.insight-cell strong {
  display: block;
  margin: 4px 0 2px;
  overflow: hidden;
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.operational-band {
  padding: 18px;
}

.section-heading h5,
.chart-heading h5 {
  margin: 0;
  font-size: 15px;
  font-weight: 650;
}

.section-heading p,
.chart-heading span {
  color: var(--analytics-muted);
  font-size: 12px;
}

.live-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #39765f;
  font-size: 12px;
  font-weight: 600;
}

.live-indicator span {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #37a978;
}

.operational-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
  margin-top: 14px;
}

.operational-item {
  min-width: 0;
  min-height: 72px;
  display: grid;
  grid-template-columns: 30px minmax(0, 1fr);
  gap: 8px;
  align-items: center;
  padding: 10px;
  border: 1px solid #e5eaf1;
  border-radius: 5px;
  background: #fafbfc;
}

.operational-item > i {
  color: #667185;
  font-size: 21px;
}

.operational-item span {
  display: block;
  color: var(--analytics-muted);
  font-size: 11px;
  line-height: 1.25;
}

.operational-item strong {
  display: block;
  margin-top: 2px;
  font-size: 19px;
}

.operational-item :deep(.badge) {
  display: none;
}

.analytics-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--analytics-border);
  border-radius: 12px;
  box-shadow: var(--analytics-shadow);
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.analytics-card:hover {
  border-color: #cbd6e6;
  box-shadow: 0 14px 34px rgba(37, 54, 82, 0.1);
  transform: translateY(-1px);
}

.analytics-card::before {
  position: absolute;
  z-index: 2;
  top: 0;
  right: 0;
  left: 0;
  height: 3px;
  content: "";
  background: linear-gradient(90deg, #3b6ee8 0%, #31a8a1 52%, #735dd0 100%);
}

.analytics-card :deep(.card-header) {
  min-height: 72px;
  padding: 17px 19px 14px;
  border-bottom: 1px solid #edf0f4;
  background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
}

.analytics-card :deep(.card-body) {
  padding: 14px 17px 17px;
}

.chart-heading {
  width: 100%;
}

.chart-range {
  flex: 0 0 auto;
  padding: 6px 10px;
  border: 1px solid #dce5f4;
  border-radius: 999px;
  background: #f3f7fd;
  color: #3b65b5 !important;
  font-weight: 600;
}

.analytics-empty {
  min-height: 280px;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 9px;
  margin: 4px;
  border: 1px dashed #d8e0ec;
  border-radius: 10px;
  background: linear-gradient(145deg, #fbfcff, #f6f8fc);
  color: var(--analytics-muted);
  font-size: 13px;
  text-align: center;
}

.analytics-empty::before {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border-radius: 50%;
  content: "\eb94";
  background: #eaf0fa;
  color: #5575ad;
  font-family: "boxicons";
  font-size: 23px;
}

.analytics-card :deep(.apexcharts-canvas) {
  margin-inline: auto;
}

.analytics-card :deep(.apexcharts-tooltip) {
  overflow: hidden;
  border: 1px solid #dfe6f1 !important;
  border-radius: 9px !important;
  box-shadow: 0 10px 28px rgba(34, 49, 74, 0.14) !important;
}

.analytics-card :deep(.apexcharts-tooltip-title) {
  border-bottom: 1px solid #edf1f6 !important;
  background: #f7f9fc !important;
  font-weight: 700;
}

.analytics-card :deep(.apexcharts-legend-text) {
  color: #596579 !important;
}

.analytics-table {
  font-size: 12px;
}

.analytics-table th {
  color: #747d8e;
  font-size: 10px;
  text-transform: uppercase;
  white-space: nowrap;
}

.text-truncate-cell {
  max-width: 210px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 1399.98px) {
  .metric-grid {
    grid-template-columns: repeat(4, minmax(170px, 1fr));
  }

  .insight-band {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .insight-cell:nth-child(3) {
    border-right: 0;
  }

  .insight-cell:nth-child(-n + 3) {
    border-bottom: 1px solid #edf0f4;
  }

  .operational-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .health-metric-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 991.98px) {
  .dashboard-header,
  .toolbar-topline {
    align-items: flex-start;
    flex-direction: column;
  }

  .dashboard-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .period-readout {
    min-width: 0;
    text-align: left;
  }

  .filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .metric-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .operational-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .health-metric-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .analytics-toolbar,
  .operational-band,
  .health-profile-panel {
    padding: 14px;
  }

  .period-segmented {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    width: 100%;
  }

  .period-option {
    padding-inline: 7px;
  }

  .filter-grid,
  .metric-grid,
  .insight-band,
  .operational-grid {
    grid-template-columns: 1fr;
  }

  .health-metric-grid {
    grid-template-columns: 1fr;
  }

  .filter-actions .btn {
    flex: 1 1 0;
  }

  .insight-cell,
  .insight-cell:nth-child(3) {
    border-right: 0;
    border-bottom: 1px solid #edf0f4;
  }

  .insight-cell:last-child {
    border-bottom: 0;
  }

  .metric-label {
    min-height: 0;
  }

  .chart-heading {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
