<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import ChartMissingExportActions from "../../components/students/chart-missing-export-actions.vue";
import AttendanceReport from "../../components/students/attendance-report.vue";
import {
  downloadStudentReportExcel,
  downloadStudentReportPdf,
} from "../../components/students/report-export";

const emptyReport = () => ({
  meta: { academic_year: null, date_range: {}, generated_at: null, applied_filters: {}, capabilities: {} },
  catalogs: {
    academic_years: [],
    education_levels: [],
    courses: [],
    general_statuses: [],
    enrollment_statuses: [],
    nationalities: [],
    communes: [],
  },
  summary: {},
  trends: { categories: [], series: {} },
  distributions: {
    by_course: [],
    by_level: [],
    by_enrollment_status: [],
    by_general_status: [],
    by_age: [],
    by_nationality: [],
    by_commune: [],
    promotion_status: [],
    support: {},
    data_quality: [],
    ethnicity: [],
    religion: { affiliations: [], class_acceptance: {} },
    family: {},
    health: { insurance: [], blood_type: [], healthcare_provider: [], conditions: [] },
    infirmary: null,
  },
  details: [],
});

const pendingReportRequests = new Map();
const pendingDetailRequests = new Map();

const reportRequestKey = (params) => JSON.stringify(
  Object.keys(params)
    .sort()
    .reduce((result, key) => ({ ...result, [key]: params[key] }), {})
);

const fetchStudentReport = (params) => {
  const key = reportRequestKey(params);

  if (!pendingReportRequests.has(key)) {
    const request = axios
      .get("/api/students/reports", { params })
      .finally(() => pendingReportRequests.delete(key));
    pendingReportRequests.set(key, request);
  }

  return pendingReportRequests.get(key);
};

const fetchStudentReportDetails = (params) => {
  const key = reportRequestKey(params);

  if (!pendingDetailRequests.has(key)) {
    const request = axios
      .get("/api/students/reports/details", { params })
      .finally(() => pendingDetailRequests.delete(key));
    pendingDetailRequests.set(key, request);
  }

  return pendingDetailRequests.get(key);
};

export default {
  components: { Layout, LoadingState, ChartMissingExportActions, AttendanceReport },
  data() {
    return {
      loading: true,
      detailLoading: false,
      detailError: null,
      detailLoaded: false,
      detailRequestId: 0,
      detailTotal: 0,
      detailLastPage: 1,
      detailSearchTimer: null,
      exportingExcel: false,
      exportingPdf: false,
      chartExporting: "",
      pdfCaptureMounted: false,
      reportRequestId: 0,
      error: null,
      report: emptyReport(),
      activeTab: "overview",
      mountedTabs: {
        overview: true,
        characterization: false,
        support: false,
        context: false,
        attendance: false,
        detail: false,
      },
      advancedOpen: false,
      filters: {
        academic_year_id: null,
        period: "academic_year",
        month: "",
        from: "",
        to: "",
        education_level_id: null,
        course_section_id: null,
        general_status: "",
        enrollment_status: "",
        is_pie_participant: "",
        nationality: "",
        commune: "",
        search: "",
      },
      detailSearch: "",
      detailPage: 1,
      detailPageSize: 15,
      detailSort: { key: "course", direction: "asc" },
      tabs: [
        { key: "overview", label: "Resumen", icon: "bx-grid-alt" },
        { key: "characterization", label: "Caracterización", icon: "bx-pie-chart-alt-2" },
        { key: "support", label: "Apoyos y calidad", icon: "bx-check-shield" },
        { key: "context", label: "Familia y salud", icon: "bx-heart" },
        { key: "attendance", label: "Asistencia", icon: "bx-calendar-check" },
        { key: "detail", label: "Detalle", icon: "bx-table" },
      ],
    };
  },
  computed: {
    catalogs() {
      return this.report.catalogs || emptyReport().catalogs;
    },
    availableCourses() {
      const levelId = Number(this.filters.education_level_id || 0);
      return (this.catalogs.courses || []).filter(
        (course) => !levelId || Number(course.education_level_id) === levelId
      );
    },
    activeAdvancedFilters() {
      return [
        this.filters.general_status,
        this.filters.enrollment_status,
        this.filters.is_pie_participant,
        this.filters.nationality,
        this.filters.commune,
        this.filters.search,
      ].filter((value) => value !== null && value !== "").length;
    },
    periodLabel() {
      const range = this.report.meta?.date_range || {};
      if (!range.from || !range.to) return "Sin periodo disponible";
      return `${this.formatDate(range.from)} al ${this.formatDate(range.to)}`;
    },
    reportFileName() {
      const range = this.report.meta?.date_range || {};
      return `reporte_estudiantes_${range.from || "inicio"}_${range.to || "fin"}`;
    },
    kpis() {
      const summary = this.report.summary || {};
      return [
        { label: "Matrícula vigente", value: summary.active_enrollments || 0, icon: "bx-user-check", tone: "green" },
        { label: "Registros del año", value: summary.registered_students || 0, icon: "bx-group", tone: "blue" },
        { label: "Nuevas matrículas", value: summary.new_enrollments || 0, icon: "bx-user-plus", tone: "cyan" },
        { label: "Retiros", value: summary.withdrawals || 0, icon: "bx-user-minus", tone: "red" },
        { label: "Cambios de curso", value: summary.transfers || 0, icon: "bx-transfer-alt", tone: "amber" },
        { label: "Retención", value: this.formatPercent(summary.retention_rate), icon: "bx-shield-quarter", tone: "violet" },
        { label: "Participación PIE", value: this.formatPercent(summary.pie_rate), icon: "bx-support", tone: "teal" },
        { label: "Fichas completas", value: this.formatPercent(summary.completeness_rate), icon: "bx-check-circle", tone: "slate" },
      ];
    },
    enrollmentTrendSeries() {
      const series = this.report.trends?.series || {};
      return [{ name: "Nuevas matrículas", data: series.enrollments || [] }];
    },
    enrollmentTrendOptions() {
      return {
        ...this.baseChartOptions(),
        chart: { ...this.baseChartOptions().chart, type: "bar" },
        colors: ["#2f855a"],
        plotOptions: { bar: { borderRadius: 3, columnWidth: "52%", dataLabels: { position: "top" } } },
        dataLabels: {
          enabled: true,
          formatter: (value) => (Number(value) > 0 ? Math.round(value) : ""),
          offsetY: -18,
          style: { fontSize: "10px", colors: ["#526071"] },
        },
        xaxis: {
          categories: (this.report.trends?.categories || []).map(this.formatMonth),
          labels: { rotate: -35, trim: true },
        },
        yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0 },
        legend: { show: false },
        tooltip: { ...this.baseChartOptions().tooltip, y: { formatter: (value) => `${Math.round(value)} matrículas` } },
      };
    },
    movementTrendSeries() {
      const series = this.report.trends?.series || {};
      return [
        { name: "Retiros", data: series.withdrawals || [] },
        { name: "Cambios de curso", data: series.transfers || [] },
        { name: "Reingresos", data: series.reentries || [] },
      ];
    },
    movementTrendTotal() {
      return this.movementTrendSeries.reduce(
        (total, series) => total + series.data.reduce((sum, value) => sum + Number(value || 0), 0),
        0
      );
    },
    inferredWithdrawalCount() {
      return Number(this.report.summary?.withdrawals_without_effective_date || 0);
    },
    movementTrendMax() {
      return Math.max(0, ...this.movementTrendSeries.flatMap((series) => series.data.map((value) => Number(value || 0))));
    },
    movementTrendOptions() {
      const axisMax = Math.max(2, this.movementTrendMax + 1);

      return {
        ...this.baseChartOptions(),
        chart: { ...this.baseChartOptions().chart, type: "bar" },
        colors: ["#dc3545", "#d69e2e", "#3182ce"],
        plotOptions: { bar: { borderRadius: 2, columnWidth: "68%", dataLabels: { position: "top" } } },
        dataLabels: {
          enabled: true,
          formatter: (value) => (Number(value) > 0 ? Math.round(value) : ""),
          offsetY: -15,
          style: { fontSize: "10px", colors: ["#526071"] },
        },
        xaxis: {
          categories: (this.report.trends?.categories || []).map(this.formatMonth),
          labels: { rotate: -35, trim: true },
        },
        yaxis: { min: 0, max: axisMax, tickAmount: Math.min(4, axisMax), decimalsInFloat: 0 },
        legend: { show: true, position: "top", horizontalAlign: "left" },
        tooltip: { ...this.baseChartOptions().tooltip, shared: true, intersect: false },
      };
    },
    courseSeries() {
      return [{ name: "Estudiantes", data: (this.report.distributions?.by_course || []).map((item) => Number(item.total || 0)) }];
    },
    courseOptions() {
      return {
        ...this.baseChartOptions(),
        colors: ["#405189"],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: "62%" } },
        xaxis: { categories: (this.report.distributions?.by_course || []).map((item) => item.label), min: 0 },
        yaxis: { labels: { maxWidth: 150 } },
        dataLabels: {
          enabled: true,
          formatter: (value) => Math.round(value),
          style: { fontSize: "11px", colors: ["#ffffff"] },
        },
      };
    },
    courseChartHeight() {
      return Math.max(310, (this.report.distributions?.by_course?.length || 0) * 35);
    },
    statusSeries() {
      return (this.report.distributions?.by_enrollment_status || []).map((item) => Number(item.total || 0));
    },
    statusOptions() {
      return {
        ...this.baseChartOptions(),
        labels: (this.report.distributions?.by_enrollment_status || []).map((item) => this.enrollmentStatusLabel(item.label)),
        colors: ["#2f855a", "#3182ce", "#dc3545", "#805ad5", "#d69e2e", "#718096"],
        legend: { show: true, position: "bottom" },
        plotOptions: { pie: { donut: { size: "66%", labels: { show: true, total: { show: true, label: "Total" } } } } },
      };
    },
    ageSeries() {
      return [{ name: "Estudiantes", data: (this.report.distributions?.by_age || []).map((item) => Number(item.total || 0)) }];
    },
    ageOptions() {
      return {
        ...this.baseChartOptions(),
        colors: ["#3182ce", "#2f855a", "#d69e2e", "#805ad5", "#dd6b20", "#718096"],
        plotOptions: { bar: { distributed: true, borderRadius: 3, columnWidth: "58%" } },
        xaxis: { categories: (this.report.distributions?.by_age || []).map((item) => item.label) },
        legend: { show: false },
      };
    },
    communeItems() {
      return (this.report.distributions?.by_commune || []).slice(0, 10);
    },
    communeSeries() {
      return [{ name: "Estudiantes", data: this.communeItems.map((item) => Number(item.total || 0)) }];
    },
    communeOptions() {
      return {
        ...this.baseChartOptions(),
        colors: ["#0f766e"],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: "58%" } },
        xaxis: { categories: this.communeItems.map((item) => item.label), min: 0 },
      };
    },
    supportSeries() {
      const support = this.report.distributions?.support || {};
      const keys = ["pie", "internet", "computer", "repeated_course"];
      return [
        { name: "Sí", data: keys.map((key) => Number(support[key]?.yes || 0)) },
        { name: "No", data: keys.map((key) => Number(support[key]?.no || 0)) },
        { name: "Sin información", data: keys.map((key) => Number(support[key]?.unknown || 0)) },
      ];
    },
    supportOptions() {
      return {
        ...this.baseChartOptions(),
        chart: { ...this.baseChartOptions().chart, stacked: true },
        colors: ["#2f855a", "#cbd5e1", "#d69e2e"],
        plotOptions: { bar: { horizontal: true, borderRadius: 2, barHeight: "55%" } },
        xaxis: { categories: ["Programa PIE", "Internet", "Computador", "Repitencia"], min: 0 },
        legend: { show: true, position: "top", horizontalAlign: "left" },
      };
    },
    qualitySeries() {
      return (this.report.distributions?.data_quality || []).map((item) => Number(item.total || 0));
    },
    qualityOptions() {
      return {
        ...this.baseChartOptions(),
        labels: (this.report.distributions?.data_quality || []).map((item) => item.label),
        colors: ["#2f855a", "#d69e2e", "#dc3545"],
        legend: { show: true, position: "bottom" },
        plotOptions: { pie: { donut: { size: "66%", labels: { show: true, total: { show: true, label: "Fichas" } } } } },
      };
    },
    ethnicitySeries() {
      return [{ name: "Estudiantes", data: (this.report.distributions?.ethnicity || []).map((item) => Number(item.total || 0)) }];
    },
    ethnicityOptions() {
      return {
        ...this.baseChartOptions(),
        colors: ["#0f766e"],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: "56%" } },
        xaxis: { categories: (this.report.distributions?.ethnicity || []).map((item) => item.label), min: 0 },
      };
    },
    religionSeries() {
      return (this.report.distributions?.religion?.affiliations || []).map((item) => Number(item.total || 0));
    },
    religionOptions() {
      return {
        ...this.baseChartOptions(),
        labels: (this.report.distributions?.religion?.affiliations || []).map((item) => item.label),
        colors: ["#405189", "#2f855a", "#d69e2e", "#805ad5", "#dd6b20", "#718096"],
        legend: { show: true, position: "bottom" },
        plotOptions: { pie: { donut: { size: "64%", labels: { show: true, total: { show: true, label: "Estudiantes" } } } } },
      };
    },
    familySections() {
      const family = this.report.distributions?.family || {};
      const sections = [
        {
          key: "home-context",
          kicker: "Hogar y territorio",
          title: "Residencia y composición del hogar",
          icon: "bx-home-heart",
          datasets: [
            { key: "student_communes", label: "Comuna de la estudiante" },
            { key: "lives_with", label: "Vive con" },
            { key: "guardian_communes", label: "Comuna del apoderado titular" },
            { key: "backup_guardian_communes", label: "Comuna del apoderado suplente" },
          ],
        },
        {
          key: "media-authorizations",
          kicker: "Consentimiento institucional",
          title: "Autorización para fotografía o grabación",
          icon: "bx-camera",
          datasets: [
            { key: "guardian_photo_authorizations", label: "Apoderado titular" },
            { key: "backup_guardian_photo_authorizations", label: "Apoderado suplente" },
          ],
        },
        {
          key: "guardians",
          kicker: "Red responsable",
          title: "Apoderados y parentescos",
          icon: "bx-user-pin",
          datasets: [
            { key: "guardian_relationships", label: "Parentesco del apoderado principal" },
            { key: "backup_guardian_relationships", label: "Parentesco del apoderado suplente" },
            { key: "guardian_roles", label: "Rol del apoderado principal" },
            { key: "backup_guardian_roles", label: "Rol del apoderado suplente" },
          ],
        },
        {
          key: "education",
          kicker: "Trayectoria formativa",
          title: "Nivel educacional de la familia",
          icon: "bx-book-open",
          datasets: [
            { key: "guardian_education_levels", label: "Apoderado principal" },
            { key: "backup_guardian_education_levels", label: "Apoderado suplente" },
            { key: "father_education_levels", label: "Padre" },
            { key: "mother_education_levels", label: "Madre" },
          ],
        },
        {
          key: "occupations",
          kicker: "Actividad laboral",
          title: "Ocupaciones declaradas",
          icon: "bx-briefcase-alt-2",
          datasets: [
            { key: "guardian_occupations", label: "Apoderado principal" },
            { key: "backup_guardian_occupations", label: "Apoderado suplente" },
            { key: "father_occupations", label: "Padre" },
            { key: "mother_occupations", label: "Madre" },
          ],
        },
        {
          key: "civil-origin",
          kicker: "Caracterización familiar",
          title: "Estado civil y nacionalidad",
          icon: "bx-group",
          datasets: [
            { key: "guardian_marital_statuses", label: "Estado civil del apoderado principal" },
            { key: "backup_guardian_marital_statuses", label: "Estado civil del apoderado suplente" },
            { key: "parent_nationalities", label: "Nacionalidad de madre y padre" },
          ],
        },
      ];

      return sections.map((section) => ({
        ...section,
        datasets: section.datasets.map((dataset) => ({
          ...dataset,
          items: family[dataset.key] || [],
        })),
      }));
    },
    familyDatasetOptions() {
      return this.familySections.flatMap((section) => section.datasets.map((dataset) => ({
        value: dataset.key,
        label: dataset.label,
      })));
    },
    parentPresenceSeries() {
      return (this.report.distributions?.family?.parent_presence || []).map((item) => Number(item.total || 0));
    },
    parentPresenceOptions() {
      return {
        ...this.baseChartOptions(),
        labels: (this.report.distributions?.family?.parent_presence || []).map((item) => item.label),
        colors: ["#2f855a", "#3182ce", "#d69e2e", "#718096"],
        legend: { show: true, position: "bottom" },
        plotOptions: { pie: { donut: { size: "62%", labels: { show: true, total: { show: true, label: "Fichas" } } } } },
      };
    },
    healthConditionSeries() {
      const items = this.report.distributions?.health?.conditions || [];
      return [
        { name: "Sí", data: items.map((item) => Number(item.yes || 0)) },
        { name: "No", data: items.map((item) => Number(item.no || 0)) },
        { name: "Sin información", data: items.map((item) => Number(item.unknown || 0)) },
      ];
    },
    healthConditionOptions() {
      return {
        ...this.baseChartOptions(),
        chart: { ...this.baseChartOptions().chart, stacked: true },
        colors: ["#b42332", "#2f855a", "#cbd5e1"],
        plotOptions: { bar: { horizontal: true, borderRadius: 2, barHeight: "55%" } },
        xaxis: { categories: (this.report.distributions?.health?.conditions || []).map((item) => item.label), min: 0 },
        yaxis: { labels: { maxWidth: 205 } },
        legend: { show: true, position: "top", horizontalAlign: "left" },
      };
    },
    insuranceSeries() {
      return (this.report.distributions?.health?.insurance || []).map((item) => Number(item.total || 0));
    },
    insuranceOptions() {
      return {
        ...this.baseChartOptions(),
        labels: (this.report.distributions?.health?.insurance || []).map((item) => item.label),
        colors: ["#3182ce", "#2f855a", "#d69e2e", "#805ad5", "#718096"],
        legend: { show: true, position: "bottom" },
        plotOptions: { pie: { donut: { size: "62%", labels: { show: true, total: { show: true, label: "Estudiantes" } } } } },
      };
    },
    infirmaryEnabled() {
      return Boolean(this.report.meta?.capabilities?.infirmary_statistics && this.report.distributions?.infirmary);
    },
    infirmarySummary() {
      return this.report.distributions?.infirmary?.summary || {};
    },
    infirmaryCategorySeries() {
      return [{ name: "Atenciones", data: (this.report.distributions?.infirmary?.attentions_by_category || []).map((item) => Number(item.total || 0)) }];
    },
    infirmaryCategoryOptions() {
      return {
        ...this.baseChartOptions(),
        colors: ["#28764d"],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: "58%" } },
        xaxis: { categories: (this.report.distributions?.infirmary?.attentions_by_category || []).map((item) => this.humanize(item.label)), min: 0 },
        yaxis: { labels: { maxWidth: 210 } },
      };
    },
    detailTotalPages() {
      return Math.max(1, this.detailLastPage);
    },
    paginatedDetailRows() {
      return this.report.details || [];
    },
    detailRangeLabel() {
      if (!this.detailTotal) return "0 resultados";
      const start = (this.detailPage - 1) * this.detailPageSize + 1;
      const end = Math.min(this.detailPage * this.detailPageSize, this.detailTotal);
      return `${start}-${end} de ${this.detailTotal}`;
    },
  },
  watch: {
    detailSearch() {
      window.clearTimeout(this.detailSearchTimer);
      if (!this.detailLoaded || this.activeTab !== "detail") return;
      this.detailSearchTimer = window.setTimeout(() => this.loadDetails(1), 300);
    },
    detailPageSize() {
      if (this.detailLoaded && this.activeTab === "detail") this.loadDetails(1);
    },
  },
  mounted() {
    this.loadReport();
  },
  beforeUnmount() {
    window.clearTimeout(this.detailSearchTimer);
  },
  methods: {
    baseChartOptions() {
      return {
        chart: {
          toolbar: { show: false },
          fontFamily: "Inter, var(--bs-font-sans-serif)",
          animations: { enabled: false },
          foreColor: "#64748b",
        },
        dataLabels: { enabled: false },
        grid: { borderColor: "#e9edf3", strokeDashArray: 3 },
        tooltip: { theme: "light" },
        noData: { text: "Sin datos para los filtros seleccionados" },
      };
    },
    async loadReport(forceRefresh = false) {
      const requestId = ++this.reportRequestId;
      let loadDetailsAfter = false;
      this.loading = true;
      this.error = null;
      this.mountedTabs = Object.fromEntries(
        this.tabs.map((tab) => [tab.key, tab.key === this.activeTab])
      );

      try {
        const response = await fetchStudentReport(this.reportParams(forceRefresh === true));
        if (requestId !== this.reportRequestId) return;

        this.report = response.data;
        this.resetDetails();

        if (!this.filters.academic_year_id && this.report.meta?.academic_year?.id) {
          this.filters.academic_year_id = this.report.meta.academic_year.id;
        }

        if (
          this.filters.course_section_id &&
          !(this.catalogs.courses || []).some((course) => Number(course.id) === Number(this.filters.course_section_id))
        ) {
          this.filters.course_section_id = null;
        }

        loadDetailsAfter = this.activeTab === "detail";
      } catch (error) {
        if (requestId !== this.reportRequestId) return;

        this.error = this.formatError(error, "No se pudo generar el reporte de estudiantes.");
      } finally {
        if (requestId === this.reportRequestId) {
          this.loading = false;
          if (loadDetailsAfter) this.loadDetails(1);
        }
      }
    },
    reportParams(forceRefresh = false) {
      const params = { ...this.filters };
      if (params.period !== "month") delete params.month;
      if (params.period !== "custom") {
        delete params.from;
        delete params.to;
      }
      const normalized = Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== "")
      );
      if (forceRefresh) normalized.refresh = 1;
      return normalized;
    },
    detailParams(page = 1, perPage = this.detailPageSize) {
      return {
        ...this.reportParams(),
        page,
        per_page: perPage,
        detail_search: this.detailSearch || undefined,
        sort: this.detailSort.key,
        direction: this.detailSort.direction,
      };
    },
    resetDetails() {
      this.detailRequestId += 1;
      this.report.details = [];
      this.detailLoaded = false;
      this.detailLoading = false;
      this.detailError = null;
      this.detailPage = 1;
      this.detailTotal = 0;
      this.detailLastPage = 1;
    },
    async loadDetails(page = 1) {
      const requestId = ++this.detailRequestId;
      this.detailLoading = true;
      this.detailError = null;

      try {
        const response = await fetchStudentReportDetails(this.detailParams(page));
        if (requestId !== this.detailRequestId) return;

        const meta = response.data?.meta || {};
        this.report.details = response.data?.data || [];
        this.detailPage = Number(meta.current_page || page);
        this.detailLastPage = Number(meta.last_page || 1);
        this.detailTotal = Number(meta.total || 0);
        this.detailLoaded = true;
      } catch (error) {
        if (requestId !== this.detailRequestId) return;
        this.detailError = this.formatError(error, "No se pudo cargar el detalle de estudiantes.");
      } finally {
        if (requestId === this.detailRequestId) this.detailLoading = false;
      }
    },
    handleYearChange() {
      this.filters.education_level_id = null;
      this.filters.course_section_id = null;
    },
    handleLevelChange() {
      if (
        this.filters.course_section_id &&
        !this.availableCourses.some((course) => Number(course.id) === Number(this.filters.course_section_id))
      ) {
        this.filters.course_section_id = null;
      }
    },
    resetFilters() {
      this.resetDetails();
      this.filters = {
        academic_year_id: this.report.meta?.academic_year?.id || null,
        period: "academic_year",
        month: "",
        from: "",
        to: "",
        education_level_id: null,
        course_section_id: null,
        general_status: "",
        enrollment_status: "",
        is_pie_participant: "",
        nationality: "",
        commune: "",
        search: "",
      };
      this.detailSearch = "";
      this.advancedOpen = false;
      this.loadReport();
    },
    setActiveTab(tab) {
      this.mountedTabs[tab] = true;
      this.activeTab = tab;
      if (tab === "detail" && !this.detailLoaded && !this.detailLoading) this.loadDetails(1);
    },
    setDetailSort(key) {
      if (this.detailSort.key === key) {
        this.detailSort.direction = this.detailSort.direction === "asc" ? "desc" : "asc";
      } else {
        this.detailSort = { key, direction: "asc" };
      }
      this.loadDetails(1);
    },
    sortIcon(key) {
      if (this.detailSort.key !== key) return "bx-sort";
      return this.detailSort.direction === "asc" ? "bx-sort-up" : "bx-sort-down";
    },
    moveDetailPage(delta) {
      const page = Math.min(this.detailTotalPages, Math.max(1, this.detailPage + delta));
      if (page !== this.detailPage) this.loadDetails(page);
    },
    openStudent(row) {
      if (row?.id) this.$router.push(`/students/${row.id}`);
    },
    chartExportLoading(dimension) {
      if (!this.chartExporting.startsWith(`${dimension}:`)) return "";
      return this.chartExporting.split(":")[1] || "";
    },
    async exportChartMissingData(dimension, title, format) {
      if (this.chartExporting) return;

      this.chartExporting = `${dimension}:${format}`;
      this.error = null;

      try {
        const response = await axios.get("/api/students/reports/missing-data", {
          params: { ...this.reportParams(), dimension },
        });
        const rows = response.data?.data || [];
        const total = Number(response.data?.meta?.total || rows.length || 0);
        const sections = [{
          title: "Estudiantes sin información",
          headers: ["Estudiante", "RUT", "Curso", "Campos sin información"],
          widths: [180, 90, 110, "*"],
          rows: rows.length
            ? rows.map((row) => [
                row.name || "-",
                row.rut || "-",
                row.course || "-",
                (row.missing_fields || []).join(", ") || "Sin detalle",
              ])
            : [["Sin registros pendientes", "-", "-", "No hay estudiantes en la categoría Sin información."]],
        }];
        const fileName = `${this.reportFileName}_${dimension}_sin_informacion`;

        if (format === "excel") {
          downloadStudentReportExcel(fileName, sections);
        } else {
          downloadStudentReportPdf(
            fileName,
            title,
            `${this.periodLabel} · ${total} estudiante${total === 1 ? "" : "s"} sin información`,
            sections,
            {
              generatedAt: this.report.meta?.generated_at,
              filters: this.exportFilterRows(),
            }
          );
        }
      } catch (error) {
        this.error = this.formatError(error, `No se pudo exportar el listado de ${title}.`);
      } finally {
        this.chartExporting = "";
      }
    },
    async exportExcel() {
      if (this.exportingExcel) return;
      this.exportingExcel = true;
      this.error = null;

      try {
        const details = await this.loadAllDetails();
        downloadStudentReportExcel(this.reportFileName, this.reportSections(true, 500, details));
      } catch (error) {
        this.error = this.formatError(error, "No se pudo generar el archivo Excel.");
      } finally {
        this.exportingExcel = false;
      }
    },
    async exportPdf() {
      if (this.exportingPdf) return;
      this.exportingPdf = true;
      this.error = null;

      try {
        const details = await this.loadAllDetails();
        const images = await this.captureReportCharts();
        const detailLimit = 500;
        const sections = this.reportSections(false, detailLimit, details);

        downloadStudentReportPdf(
          this.reportFileName,
          "Reporte de estudiantes",
          `${this.report.meta?.academic_year?.name || "Año académico"} · ${this.periodLabel}`,
          sections,
          {
            generatedAt: this.report.meta?.generated_at,
            filters: this.exportFilterRows(),
            images,
          }
        );
      } catch (error) {
        this.error = this.formatError(error, "No se pudo generar el archivo PDF.");
      } finally {
        this.exportingPdf = false;
      }
    },
    async loadAllDetails() {
      const perPage = 500;
      const baseParams = {
        ...this.reportParams(),
        per_page: perPage,
        sort: "course",
        direction: "asc",
      };
      const firstResponse = await fetchStudentReportDetails({ ...baseParams, page: 1 });
      const rows = [...(firstResponse.data?.data || [])];
      const lastPage = Number(firstResponse.data?.meta?.last_page || 1);

      for (let page = 2; page <= lastPage; page += 1) {
        const response = await fetchStudentReportDetails({ ...baseParams, page });
        rows.push(...(response.data?.data || []));
      }

      return rows;
    },
    reportSections(includeAllDetails = false, detailLimit = 500, detailRows = null) {
      const summary = this.report.summary || {};
      const availableDetails = detailRows || this.report.details || [];
      const details = includeAllDetails ? availableDetails : availableDetails.slice(0, detailLimit);
      const detailsNote = !includeAllDetails && availableDetails.length > detailLimit
        ? `Se incluyen las primeras ${detailLimit} filas. La exportación Excel contiene el detalle completo.`
        : "";

      return [
        {
          title: "Indicadores",
          headers: ["Indicador", "Valor", "Indicador", "Valor"],
          widths: ["*", 70, "*", 70],
          rows: [
            ["Matrícula vigente", summary.active_enrollments || 0, "Registros del año", summary.registered_students || 0],
            ["Nuevas matrículas", summary.new_enrollments || 0, "Retiros", summary.withdrawals || 0],
            ["Cambios de curso", summary.transfers || 0, "Reingresos", summary.reentries || 0],
            ["Retención", this.formatPercent(summary.retention_rate), "Ocupación", this.formatPercent(summary.occupancy_rate)],
            ["Participación PIE", this.formatPercent(summary.pie_rate), "Fichas completas", this.formatPercent(summary.completeness_rate)],
            ["Promovidas", summary.promoted_students || 0, "Repitentes", summary.repeating_students || 0],
          ],
        },
        {
          title: "Matrícula por curso",
          headers: ["Curso", "Nivel", "Matrícula", "Capacidad", "Cupos", "Ocupación"],
          widths: ["*", "*", 58, 58, 50, 62],
          rows: (this.report.distributions?.by_course || []).map((item) => [
            item.label,
            item.level || "-",
            item.total,
            item.capacity || "-",
            item.available_places,
            this.formatPercent(item.occupancy_rate),
          ]),
        },
        {
          title: "Caracterización",
          headers: ["Dimensión", "Categoría", "Total"],
          widths: [120, "*", 60],
          rows: [
            ...(this.report.distributions?.by_enrollment_status || []).map((item) => ["Estado de matrícula", this.enrollmentStatusLabel(item.label), item.total]),
            ...(this.report.distributions?.by_age || []).map((item) => ["Edad", item.label, item.total]),
            ...(this.report.distributions?.by_nationality || []).map((item) => ["Nacionalidad", item.label, item.total]),
            ...(this.report.distributions?.by_commune || []).map((item) => ["Comuna", item.label, item.total]),
          ],
        },
        {
          title: "Etnia y religión",
          headers: ["Dimensión", "Categoría", "Total"],
          widths: [130, "*", 60],
          rows: [
            ...(this.report.distributions?.ethnicity || []).map((item) => ["Etnia", item.label, item.total]),
            ...(this.report.distributions?.religion?.affiliations || []).map((item) => ["Religión", item.label, item.total]),
            ["Clases de religión", "Acepta", this.report.distributions?.religion?.class_acceptance?.yes || 0],
            ["Clases de religión", "No acepta", this.report.distributions?.religion?.class_acceptance?.no || 0],
            ["Clases de religión", "Sin información", this.report.distributions?.religion?.class_acceptance?.unknown || 0],
          ],
        },
        {
          title: "Contexto familiar",
          headers: ["Dimensión", "Categoría", "Total"],
          widths: [190, "*", 60],
          rows: this.familyExportRows(),
        },
        {
          title: "Salud declarada en la ficha",
          headers: ["Dimensión", "Categoría", "Total"],
          widths: [190, "*", 60],
          rows: [
            ...(this.report.distributions?.health?.insurance || []).map((item) => ["Previsión de salud", item.label, item.total]),
            ...(this.report.distributions?.health?.blood_type || []).map((item) => ["Grupo sanguíneo", item.label, item.total]),
            ...(this.report.distributions?.health?.healthcare_provider || []).map((item) => ["Prestador de salud", item.label, item.total]),
            ...(this.report.distributions?.health?.conditions || []).flatMap((item) => [
              [item.label, "Sí", item.yes || 0],
              [item.label, "No", item.no || 0],
              [item.label, "Sin información", item.unknown || 0],
            ]),
          ],
        },
        ...(this.infirmaryEnabled ? [{
          title: "Actividad de Enfermería",
          headers: ["Dimensión", "Categoría", "Total"],
          widths: [160, "*", 60],
          rows: [
            ["Resumen", "Estudiantes atendidas", this.infirmarySummary.students_attended || 0],
            ["Resumen", "Atenciones", this.infirmarySummary.attentions || 0],
            ["Resumen", "Accidentes", this.infirmarySummary.accidents || 0],
            ["Resumen", "Administraciones de medicamentos", this.infirmarySummary.medication_administrations || 0],
            ["Resumen", "Derivaciones", this.infirmarySummary.referrals || 0],
            ...(this.report.distributions?.infirmary?.attentions_by_category || []).map((item) => ["Atenciones", this.humanize(item.label), item.total]),
            ...(this.report.distributions?.infirmary?.accidents_by_severity || []).map((item) => ["Gravedad de accidentes", this.humanize(item.label), item.total]),
            ...(this.report.distributions?.infirmary?.referrals_by_type || []).map((item) => ["Derivaciones", this.humanize(item.label), item.total]),
          ],
        }] : []),
        {
          title: "Detalle de estudiantes",
          note: detailsNote,
          pageBreakBefore: true,
          headers: ["Estudiante", "RUT", "Curso", "Estado", "Comuna", "PIE", "Calidad"],
          widths: ["*", 82, 100, 70, 82, 36, 48],
          rows: details.map((row) => [
            row.name,
            row.rut || "-",
            row.course || "-",
            this.enrollmentStatusLabel(row.enrollment_status),
            row.commune || "-",
            row.is_pie_participant ? "Sí" : "No",
            this.formatPercent(row.quality_score),
          ]),
        },
      ];
    },
    familyExportRows() {
      const family = this.report.distributions?.family || {};
      const rows = [];

      this.familyDatasetOptions.forEach((option) => {
        (family[option.value] || []).forEach((item) => rows.push([option.label, item.label, item.total]));
      });
      (family.parent_presence || []).forEach((item) => rows.push(["Registro de padres", item.label, item.total]));

      return rows;
    },
    exportFilterRows() {
      return [
        ["Año académico", this.selectedOptionLabel(this.catalogs.academic_years, this.filters.academic_year_id, "name")],
        ["Periodo", this.periodLabel],
        ["Nivel", this.selectedOptionLabel(this.catalogs.education_levels, this.filters.education_level_id, "name")],
        ["Curso", this.selectedOptionLabel(this.catalogs.courses, this.filters.course_section_id, "display_name")],
        ["Estado general", this.generalStatusLabel(this.filters.general_status) || "Todos"],
        ["Estado matrícula", this.enrollmentStatusLabel(this.filters.enrollment_status) || "Todos"],
        ["PIE", this.filters.is_pie_participant === "" ? "Todos" : this.filters.is_pie_participant === "1" ? "Sí" : "No"],
        ["Búsqueda", this.filters.search || "Sin búsqueda"],
      ];
    },
    async captureReportCharts() {
      const charts = [
        ["pdfEnrollmentTrendChart", "Nuevas matrículas por mes"],
        ["pdfMovementTrendChart", "Movimientos administrativos por mes"],
        ["pdfCourseChart", "Matrícula vigente por curso"],
        ["pdfStatusChart", "Distribución por estado de matrícula"],
        ["pdfAgeChart", "Distribución por edad"],
        ["pdfSupportChart", "Apoyos y condiciones de acceso"],
        ["pdfQualityChart", "Calidad de las fichas"],
        ["pdfEthnicityChart", "Distribución de etnia"],
        ["pdfReligionChart", "Distribución de religión"],
        ["pdfFamilyChart", "Contexto familiar"],
        ["pdfHealthChart", "Antecedentes de salud declarados"],
        ["pdfInfirmaryChart", "Atenciones por categoría de Enfermería"],
      ];
      const images = [];

      this.pdfCaptureMounted = true;

      try {
        await this.$nextTick();
        await new Promise((resolve) => window.setTimeout(resolve, 120));

        for (const [refName, title] of charts) {
          const chart = this.$refs[refName];
          if (!chart?.dataURI) continue;

          try {
            const result = await chart.dataURI({ scale: 1.5 });
            if (result?.imgURI) images.push({ title, dataUri: result.imgURI });
          } catch (_error) {
            // El PDF conserva tablas completas aunque un gráfico no pueda convertirse.
          }
        }
      } finally {
        this.pdfCaptureMounted = false;
        await this.$nextTick();
      }

      return images;
    },
    selectedOptionLabel(options, value, labelKey) {
      if (!value) return "Todos";
      return options.find((item) => Number(item.id) === Number(value))?.[labelKey] || "Todos";
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).slice(0, 10).split("-").map(Number);
      return new Date(year, month - 1, day).toLocaleDateString("es-CL");
    },
    formatMonth(value) {
      if (!value) return "-";
      const [year, month] = String(value).split("-").map(Number);
      return new Date(year, month - 1, 1).toLocaleDateString("es-CL", { month: "short", year: "2-digit" });
    },
    formatGeneratedAt(value) {
      if (!value) return "-";
      return new Date(value).toLocaleString("es-CL", { dateStyle: "short", timeStyle: "short" });
    },
    formatPercent(value) {
      return `${Number(value || 0).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`;
    },
    distributionTotal(items) {
      return (items || []).reduce((total, item) => total + Number(item.total || 0), 0);
    },
    distributionPercent(item, items) {
      const total = this.distributionTotal(items);
      return total ? Math.round((Number(item?.total || 0) / total) * 1000) / 10 : 0;
    },
    normalizeText(value) {
      return String(value || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
    },
    enrollmentStatusLabel(value) {
      if (!value) return "";
      return this.catalogs.enrollment_statuses?.find((item) => item.value === value)?.label || this.humanize(value);
    },
    generalStatusLabel(value) {
      if (!value) return "";
      return this.catalogs.general_statuses?.find((item) => item.value === value)?.label || this.humanize(value);
    },
    humanize(value) {
      return String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    statusVariant(status) {
      const map = {
        matriculada: "primary",
        regular: "success",
        retirada: "danger",
        egresada: "secondary",
        suspendida: "warning",
        trasladada: "info",
      };
      return map[status] || "secondary";
    },
    qualityVariant(score) {
      if (Number(score) >= 90) return "success";
      if (Number(score) >= 65) return "warning";
      return "danger";
    },
    formatError(error, fallback) {
      if (error?.response?.status === 429) {
        return "Se alcanzó temporalmente el límite de consultas. Espera unos segundos y actualiza el reporte.";
      }

      const errors = error?.response?.data?.errors;
      return (errors && Object.values(errors)?.[0]?.[0]) || error?.response?.data?.message || fallback;
    },
  },
};
</script>

<template>
  <Layout>
    <main class="student-report-page">
      <header class="report-header">
        <div class="report-heading">
          <div class="report-eyebrow">Gestión académica</div>
          <h4>Reporte de estudiantes</h4>
          <div class="report-meta-line">
            <span><i class="bx bx-calendar"></i>{{ periodLabel }}</span>
            <span><i class="bx bx-time-five"></i>Actualizado {{ formatGeneratedAt(report.meta?.generated_at) }}</span>
          </div>
        </div>

        <div v-if="activeTab !== 'attendance'" class="report-actions">
          <button type="button" class="btn btn-light report-icon-button" title="Actualizar reporte" aria-label="Actualizar reporte" :disabled="loading" @click="loadReport(true)">
            <i class="mdi mdi-refresh" :class="{ 'mdi-spin': loading }"></i>
          </button>
          <button type="button" class="btn btn-outline-success" :disabled="loading || exportingExcel || !report.summary?.registered_students" @click="exportExcel">
            <i class="bx" :class="exportingExcel ? 'bx-loader-alt bx-spin' : 'bx-spreadsheet'"></i><span>Excel</span>
          </button>
          <button
            type="button"
            class="btn btn-danger"
            :aria-label="exportingPdf ? 'Generando PDF' : 'Exportar PDF'"
            :disabled="loading || exportingPdf || !report.summary?.registered_students"
            @click="exportPdf"
          >
            <i class="bx" :class="exportingPdf ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i>
            <span>PDF</span>
          </button>
        </div>
      </header>

      <section v-show="activeTab !== 'attendance'" class="report-filter-panel" aria-label="Filtros del reporte">
        <div class="filter-primary-grid">
          <div class="filter-field">
            <label for="report-year">Año académico</label>
            <select id="report-year" v-model="filters.academic_year_id" class="form-select" @change="handleYearChange">
              <option v-for="year in catalogs.academic_years" :key="year.id" :value="year.id">
                {{ year.name }}{{ year.is_active ? " · Activo" : "" }}
              </option>
            </select>
          </div>

          <div class="filter-field">
            <label for="report-period">Temporalidad</label>
            <select id="report-period" v-model="filters.period" class="form-select">
              <option value="academic_year">Año académico</option>
              <option value="semester_1">Primer semestre</option>
              <option value="semester_2">Segundo semestre</option>
              <option value="month">Mes</option>
              <option value="custom">Rango personalizado</option>
            </select>
          </div>

          <div v-if="filters.period === 'month'" class="filter-field">
            <label for="report-month">Mes</label>
            <input id="report-month" v-model="filters.month" type="month" class="form-control" />
          </div>

          <template v-if="filters.period === 'custom'">
            <div class="filter-field">
              <label for="report-from">Desde</label>
              <input id="report-from" v-model="filters.from" type="date" class="form-control" />
            </div>
            <div class="filter-field">
              <label for="report-to">Hasta</label>
              <input id="report-to" v-model="filters.to" type="date" class="form-control" />
            </div>
          </template>

          <div class="filter-field">
            <label for="report-level">Nivel</label>
            <select id="report-level" v-model="filters.education_level_id" class="form-select" @change="handleLevelChange">
              <option :value="null">Todos</option>
              <option v-for="level in catalogs.education_levels" :key="level.id" :value="level.id">{{ level.name }}</option>
            </select>
          </div>

          <div class="filter-field">
            <label for="report-course">Curso</label>
            <select id="report-course" v-model="filters.course_section_id" class="form-select">
              <option :value="null">Todos</option>
              <option v-for="course in availableCourses" :key="course.id" :value="course.id">{{ course.display_name }}</option>
            </select>
          </div>

          <div class="filter-actions">
            <button type="button" class="btn btn-primary" :disabled="loading" @click="loadReport()">
              <i class="bx bx-filter-alt"></i><span>Aplicar</span>
            </button>
            <button type="button" class="btn btn-light filter-more-button" :aria-expanded="advancedOpen" @click="advancedOpen = !advancedOpen">
              <i class="bx bx-slider-alt"></i><span>Más filtros</span>
              <span v-if="activeAdvancedFilters" class="filter-count">{{ activeAdvancedFilters }}</span>
            </button>
          </div>
        </div>

        <div v-show="advancedOpen" class="filter-advanced-grid">
          <div class="filter-field filter-search-field">
            <label for="report-search">Estudiante o RUT</label>
            <div class="input-icon-wrap">
              <i class="bx bx-search"></i>
              <input id="report-search" v-model.trim="filters.search" type="search" class="form-control" placeholder="Buscar en el reporte" @keyup.enter="loadReport()" />
            </div>
          </div>
          <div class="filter-field">
            <label for="report-general-status">Estado general</label>
            <select id="report-general-status" v-model="filters.general_status" class="form-select">
              <option value="">Todos</option>
              <option v-for="status in catalogs.general_statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
          </div>
          <div class="filter-field">
            <label for="report-enrollment-status">Estado matrícula</label>
            <select id="report-enrollment-status" v-model="filters.enrollment_status" class="form-select">
              <option value="">Todos</option>
              <option v-for="status in catalogs.enrollment_statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
          </div>
          <div class="filter-field">
            <label for="report-pie">Participación PIE</label>
            <select id="report-pie" v-model="filters.is_pie_participant" class="form-select">
              <option value="">Todas</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
          </div>
          <div class="filter-field">
            <label for="report-nationality">Nacionalidad</label>
            <select id="report-nationality" v-model="filters.nationality" class="form-select">
              <option value="">Todas</option>
              <option v-for="nationality in catalogs.nationalities" :key="nationality" :value="nationality">{{ nationality }}</option>
            </select>
          </div>
          <div class="filter-field">
            <label for="report-commune">Comuna</label>
            <select id="report-commune" v-model="filters.commune" class="form-select">
              <option value="">Todas</option>
              <option v-for="commune in catalogs.communes" :key="commune" :value="commune">{{ commune }}</option>
            </select>
          </div>
          <button type="button" class="btn btn-link filter-reset" @click="resetFilters">
            <i class="bx bx-reset"></i>Restablecer
          </button>
        </div>
      </section>

      <div v-if="error" class="alert alert-danger report-alert" role="alert">
        <div><i class="bx bx-error-circle"></i>{{ error }}</div>
        <button type="button" class="btn btn-sm btn-outline-danger" @click="loadReport(true)">Reintentar</button>
      </div>

      <section v-if="loading" class="report-loading-panel">
        <LoadingState message="Generando reporte de estudiantes..." compact />
      </section>

      <template v-else>
        <section v-show="activeTab !== 'attendance'" class="metric-grid" aria-label="Indicadores principales">
          <article v-for="kpi in kpis" :key="kpi.label" class="metric-card" :class="`metric-${kpi.tone}`">
            <div class="metric-icon"><i class="bx" :class="kpi.icon"></i></div>
            <div class="metric-content">
              <span>{{ kpi.label }}</span>
              <strong>{{ kpi.value }}</strong>
            </div>
          </article>
        </section>

        <nav class="report-tabs" role="tablist" aria-label="Secciones del reporte">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.key"
            :class="{ active: activeTab === tab.key }"
            @click="setActiveTab(tab.key)"
          >
            <i class="bx" :class="tab.icon"></i><span>{{ tab.label }}</span>
          </button>
        </nav>

        <section v-if="mountedTabs.overview" v-show="activeTab === 'overview'" class="report-tab-content" role="tabpanel">
          <div class="analysis-grid analysis-grid-wide">
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Flujo del periodo</span><h5>Altas y movimientos de estudiantes</h5></div>
                <span class="panel-period">{{ periodLabel }}</span>
              </div>
              <div class="flow-chart-grid">
                <section class="flow-chart-section">
                  <div class="flow-chart-heading">
                    <div><span class="flow-chart-dot flow-chart-dot-enrollment"></span><h6>Nuevas matrículas</h6></div>
                    <ChartMissingExportActions
                      compact
                      :loading-format="chartExportLoading('enrollment_trend')"
                      @export-pdf="exportChartMissingData('enrollment_trend', 'Nuevas matrículas', 'pdf')"
                      @export-excel="exportChartMissingData('enrollment_trend', 'Nuevas matrículas', 'excel')"
                    />
                    <strong>{{ report.summary.new_enrollments || 0 }}</strong>
                  </div>
                  <apexchart ref="enrollmentTrendChart" type="bar" height="280" :options="enrollmentTrendOptions" :series="enrollmentTrendSeries" />
                </section>
                <section class="flow-chart-section flow-chart-movements">
                  <div class="flow-chart-heading">
                    <div><i class="bx bx-transfer-alt"></i><h6>Movimientos administrativos</h6></div>
                    <ChartMissingExportActions
                      compact
                      :loading-format="chartExportLoading('movement_trend')"
                      @export-pdf="exportChartMissingData('movement_trend', 'Movimientos administrativos', 'pdf')"
                      @export-excel="exportChartMissingData('movement_trend', 'Movimientos administrativos', 'excel')"
                    />
                    <strong>{{ movementTrendTotal }}</strong>
                  </div>
                  <div v-if="inferredWithdrawalCount" class="flow-data-note" role="note">
                    <i class="bx bx-info-circle"></i>
                    <span>
                      {{ inferredWithdrawalCount }}
                      {{ inferredWithdrawalCount === 1 ? "retiro importado no tiene" : "retiros importados no tienen" }}
                      fecha efectiva; se {{ inferredWithdrawalCount === 1 ? "ubica" : "ubican" }} en el mes de registro.
                    </span>
                  </div>
                  <apexchart
                    v-if="movementTrendTotal"
                    ref="movementTrendChart"
                    type="bar"
                    height="280"
                    :options="movementTrendOptions"
                    :series="movementTrendSeries"
                  />
                  <div v-else class="flow-empty-state">
                    <i class="bx bx-check-circle"></i>
                    <strong>Sin movimientos en el periodo</strong>
                    <span>No se registran retiros, cambios de curso ni reingresos.</span>
                  </div>
                </section>
              </div>
            </article>

            <article class="report-panel report-panel-compact">
              <div class="panel-heading"><div><span class="panel-kicker">Gestión de cupos</span><h5>Lectura ejecutiva</h5></div></div>
              <dl class="executive-list">
                <div><dt>Promedio por curso</dt><dd>{{ report.summary.average_course_size || 0 }}</dd></div>
                <div><dt>Ocupación total</dt><dd>{{ formatPercent(report.summary.occupancy_rate) }}</dd></div>
                <div><dt>Reingresos</dt><dd>{{ report.summary.reentries || 0 }}</dd></div>
                <div><dt>Promovidas</dt><dd>{{ report.summary.promoted_students || 0 }}</dd></div>
                <div><dt>Repitentes</dt><dd>{{ report.summary.repeating_students || 0 }}</dd></div>
              </dl>
            </article>
          </div>

          <article class="report-panel">
            <div class="panel-heading">
              <div><span class="panel-kicker">Distribución institucional</span><h5>Matrícula vigente por curso</h5></div>
              <div class="panel-heading-actions">
                <span class="panel-total">{{ report.summary.active_enrollments || 0 }} estudiantes</span>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('course')"
                  @export-pdf="exportChartMissingData('course', 'Matrícula vigente por curso', 'pdf')"
                  @export-excel="exportChartMissingData('course', 'Matrícula vigente por curso', 'excel')"
                />
              </div>
            </div>
            <apexchart ref="courseChart" type="bar" :height="courseChartHeight" :options="courseOptions" :series="courseSeries" />
          </article>
        </section>

        <section v-if="mountedTabs.characterization" v-show="activeTab === 'characterization'" class="report-tab-content" role="tabpanel">
          <div class="analysis-grid analysis-grid-even">
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Trayectoria</span><h5>Estado de matrícula</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('enrollment_status')"
                  @export-pdf="exportChartMissingData('enrollment_status', 'Estado de matrícula', 'pdf')"
                  @export-excel="exportChartMissingData('enrollment_status', 'Estado de matrícula', 'excel')"
                />
              </div>
              <apexchart ref="statusChart" type="donut" height="330" :options="statusOptions" :series="statusSeries" />
            </article>
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Composición</span><h5>Distribución por edad</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('age')"
                  @export-pdf="exportChartMissingData('age', 'Distribución por edad', 'pdf')"
                  @export-excel="exportChartMissingData('age', 'Distribución por edad', 'excel')"
                />
              </div>
              <apexchart ref="ageChart" type="bar" height="330" :options="ageOptions" :series="ageSeries" />
            </article>
          </div>

          <div class="analysis-grid analysis-grid-wide">
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Territorio</span><h5>Principales comunas</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('commune')"
                  @export-pdf="exportChartMissingData('commune', 'Principales comunas', 'pdf')"
                  @export-excel="exportChartMissingData('commune', 'Principales comunas', 'excel')"
                />
              </div>
              <apexchart type="bar" height="330" :options="communeOptions" :series="communeSeries" />
            </article>
            <article class="report-panel report-panel-compact">
              <div class="panel-heading"><div><span class="panel-kicker">Origen</span><h5>Nacionalidades</h5></div></div>
              <div class="rank-list">
                <div v-for="(item, index) in report.distributions.by_nationality" :key="item.label" class="rank-row">
                  <span class="rank-index">{{ index + 1 }}</span>
                  <span class="rank-label">{{ item.label }}</span>
                  <strong>{{ item.total }}</strong>
                </div>
                <div v-if="!report.distributions.by_nationality?.length" class="empty-inline">Sin información</div>
              </div>
            </article>
          </div>
        </section>

        <section v-if="mountedTabs.support" v-show="activeTab === 'support'" class="report-tab-content" role="tabpanel">
          <div class="analysis-grid analysis-grid-even">
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Condiciones y apoyos</span><h5>Cobertura declarada</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('support')"
                  @export-pdf="exportChartMissingData('support', 'Cobertura declarada', 'pdf')"
                  @export-excel="exportChartMissingData('support', 'Cobertura declarada', 'excel')"
                />
              </div>
              <apexchart ref="supportChart" type="bar" height="340" :options="supportOptions" :series="supportSeries" />
            </article>
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Integridad de datos</span><h5>Calidad de las fichas</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('quality')"
                  @export-pdf="exportChartMissingData('quality', 'Calidad de las fichas', 'pdf')"
                  @export-excel="exportChartMissingData('quality', 'Calidad de las fichas', 'excel')"
                />
              </div>
              <apexchart ref="qualityChart" type="donut" height="340" :options="qualityOptions" :series="qualitySeries" />
            </article>
          </div>

          <article class="report-panel">
            <div class="panel-heading"><div><span class="panel-kicker">Capacidad instalada</span><h5>Ocupación y cupos por curso</h5></div></div>
            <div class="table-responsive">
              <table class="table report-table occupancy-table mb-0">
                <thead><tr><th>Curso</th><th>Nivel</th><th class="text-end">Matrícula</th><th class="text-end">Capacidad</th><th class="text-end">Cupos</th><th>Ocupación</th></tr></thead>
                <tbody>
                  <tr v-for="course in report.distributions.by_course" :key="course.id">
                    <td class="fw-semibold">{{ course.label }}</td>
                    <td>{{ course.level || "-" }}</td>
                    <td class="text-end">{{ course.total }}</td>
                    <td class="text-end">{{ course.capacity || "-" }}</td>
                    <td class="text-end">{{ course.available_places }}</td>
                    <td>
                      <div class="occupancy-cell">
                        <div class="progress"><div class="progress-bar" :class="{ 'bg-danger': course.occupancy_rate > 100, 'bg-warning': course.occupancy_rate >= 90 && course.occupancy_rate <= 100 }" :style="{ width: `${Math.min(100, course.occupancy_rate)}%` }"></div></div>
                        <span>{{ formatPercent(course.occupancy_rate) }}</span>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!report.distributions.by_course?.length"><td colspan="6" class="empty-table-cell">Sin cursos para los filtros seleccionados</td></tr>
                </tbody>
              </table>
            </div>
          </article>
        </section>

        <section v-if="mountedTabs.context" v-show="activeTab === 'context'" class="report-tab-content" role="tabpanel">
          <div class="context-section-label"><span>Identidad y formación</span></div>
          <div class="analysis-grid analysis-grid-even">
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Identidad cultural</span><h5>Etnia declarada</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('ethnicity')"
                  @export-pdf="exportChartMissingData('ethnicity', 'Etnia declarada', 'pdf')"
                  @export-excel="exportChartMissingData('ethnicity', 'Etnia declarada', 'excel')"
                />
              </div>
              <apexchart ref="ethnicityChart" type="bar" height="320" :options="ethnicityOptions" :series="ethnicitySeries" />
            </article>
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Formación religiosa</span><h5>Religión declarada</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('religion')"
                  @export-pdf="exportChartMissingData('religion', 'Religión declarada', 'pdf')"
                  @export-excel="exportChartMissingData('religion', 'Religión declarada', 'excel')"
                />
              </div>
              <apexchart ref="religionChart" type="donut" height="270" :options="religionOptions" :series="religionSeries" />
              <div class="religion-class-strip">
                <span>Acepta clases <strong>{{ report.distributions.religion?.class_acceptance?.yes || 0 }}</strong></span>
                <span>No acepta <strong>{{ report.distributions.religion?.class_acceptance?.no || 0 }}</strong></span>
                <span>Sin información <strong>{{ report.distributions.religion?.class_acceptance?.unknown || 0 }}</strong></span>
              </div>
            </article>
          </div>

          <div class="context-section-label"><span>Contexto familiar</span></div>
          <article v-for="section in familySections" :key="section.key" class="report-panel family-section-panel">
            <div class="family-section-heading">
              <span class="family-section-icon"><i class="bx" :class="section.icon"></i></span>
              <div><span class="panel-kicker">{{ section.kicker }}</span><h5>{{ section.title }}</h5></div>
            </div>
            <div class="family-dataset-grid" :class="{ 'family-dataset-grid-three': section.datasets.length === 3 }">
              <section v-for="dataset in section.datasets" :key="dataset.key" class="family-dataset">
                <div class="family-dataset-heading">
                  <div class="family-dataset-title">
                    <h6>{{ dataset.label }}</h6>
                    <span>{{ distributionTotal(dataset.items) }} registros</span>
                  </div>
                  <ChartMissingExportActions
                    compact
                    :loading-format="chartExportLoading(dataset.key)"
                    @export-pdf="exportChartMissingData(dataset.key, dataset.label, 'pdf')"
                    @export-excel="exportChartMissingData(dataset.key, dataset.label, 'excel')"
                  />
                </div>
                <div v-if="dataset.items.length" class="family-distribution-list">
                  <div v-for="item in dataset.items" :key="`${dataset.key}-${item.label}`" class="family-distribution-row">
                    <div class="family-distribution-meta">
                      <span :title="item.label">{{ item.label }}</span>
                      <strong>{{ item.total }} <small>{{ formatPercent(distributionPercent(item, dataset.items)) }}</small></strong>
                    </div>
                    <div class="family-distribution-track" aria-hidden="true">
                      <span :style="{ width: `${distributionPercent(item, dataset.items)}%` }"></span>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-inline">Sin información registrada</div>
              </section>
            </div>
          </article>

          <article class="report-panel parent-presence-panel">
            <div class="panel-heading">
              <div><span class="panel-kicker">Completitud familiar</span><h5>Registro de madre y padre</h5></div>
              <div class="panel-heading-actions">
                <span class="panel-total">{{ distributionTotal(report.distributions.family?.parent_presence || []) }} fichas</span>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('parent_presence')"
                  @export-pdf="exportChartMissingData('parent_presence', 'Registro de madre y padre', 'pdf')"
                  @export-excel="exportChartMissingData('parent_presence', 'Registro de madre y padre', 'excel')"
                />
              </div>
            </div>
            <div class="parent-presence-layout">
              <apexchart type="donut" height="300" :options="parentPresenceOptions" :series="parentPresenceSeries" />
              <div class="parent-presence-list">
                <div v-for="item in report.distributions.family?.parent_presence" :key="item.label">
                  <span>{{ item.label }}</span>
                  <strong>{{ item.total }}</strong>
                </div>
              </div>
            </div>
          </article>

          <div class="context-section-label"><span>Salud declarada</span></div>
          <div class="analysis-grid analysis-grid-wide">
            <article class="report-panel">
              <div class="panel-heading">
                <div><span class="panel-kicker">Antecedentes de ficha</span><h5>Condiciones y alertas de salud</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('health_conditions')"
                  @export-pdf="exportChartMissingData('health_conditions', 'Condiciones y alertas de salud', 'pdf')"
                  @export-excel="exportChartMissingData('health_conditions', 'Condiciones y alertas de salud', 'excel')"
                />
              </div>
              <apexchart ref="healthChart" type="bar" height="360" :options="healthConditionOptions" :series="healthConditionSeries" />
            </article>
            <article class="report-panel report-panel-compact">
              <div class="panel-heading">
                <div><span class="panel-kicker">Cobertura</span><h5>Previsión de salud</h5></div>
                <ChartMissingExportActions
                  :loading-format="chartExportLoading('health_insurance')"
                  @export-pdf="exportChartMissingData('health_insurance', 'Previsión de salud', 'pdf')"
                  @export-excel="exportChartMissingData('health_insurance', 'Previsión de salud', 'excel')"
                />
              </div>
              <apexchart type="donut" height="330" :options="insuranceOptions" :series="insuranceSeries" />
            </article>
          </div>

          <template v-if="infirmaryEnabled">
            <div class="context-section-label"><span>Actividad de Enfermería</span></div>
            <article class="report-panel infirmary-overview-panel">
              <div class="panel-heading"><div><span class="panel-kicker">Mismo periodo y estudiantes</span><h5>Resumen asistencial</h5></div><span class="panel-period">{{ periodLabel }}</span></div>
              <div class="infirmary-summary-strip">
                <div><span>Estudiantes atendidas</span><strong>{{ infirmarySummary.students_attended || 0 }}</strong></div>
                <div><span>Atenciones</span><strong>{{ infirmarySummary.attentions || 0 }}</strong></div>
                <div><span>Accidentes</span><strong>{{ infirmarySummary.accidents || 0 }}</strong></div>
                <div><span>Medicamentos</span><strong>{{ infirmarySummary.medication_administrations || 0 }}</strong></div>
                <div><span>Derivaciones</span><strong>{{ infirmarySummary.referrals || 0 }}</strong></div>
                <div><span>Promedio por atendida</span><strong>{{ infirmarySummary.average_attentions_per_student || 0 }}</strong></div>
              </div>
            </article>

            <div class="analysis-grid analysis-grid-wide">
              <article class="report-panel">
                <div class="panel-heading">
                  <div><span class="panel-kicker">Motivos de atención</span><h5>Atenciones por categoría</h5></div>
                  <ChartMissingExportActions
                    :loading-format="chartExportLoading('infirmary_attentions')"
                    @export-pdf="exportChartMissingData('infirmary_attentions', 'Atenciones por categoría', 'pdf')"
                    @export-excel="exportChartMissingData('infirmary_attentions', 'Atenciones por categoría', 'excel')"
                  />
                </div>
                <apexchart ref="infirmaryChart" type="bar" height="340" :options="infirmaryCategoryOptions" :series="infirmaryCategorySeries" />
              </article>
              <article class="report-panel report-panel-compact">
                <div class="panel-heading"><div><span class="panel-kicker">Derivaciones</span><h5>Destinos registrados</h5></div></div>
                <div class="rank-list">
                  <div v-for="(item, index) in report.distributions.infirmary?.referrals_by_type" :key="item.label" class="rank-row">
                    <span class="rank-index">{{ index + 1 }}</span><span class="rank-label">{{ humanize(item.label) }}</span><strong>{{ item.total }}</strong>
                  </div>
                  <div v-if="!report.distributions.infirmary?.referrals_by_type?.length" class="empty-inline">Sin derivaciones en el periodo</div>
                </div>
              </article>
            </div>
          </template>

          <div v-else class="report-permission-state">
            <i class="bx bx-lock-alt"></i>
            <div><strong>Actividad de Enfermería restringida</strong><span>Se requiere permiso para consultar reportes de Enfermería.</span></div>
          </div>
        </section>

        <section v-if="mountedTabs.attendance" v-show="activeTab === 'attendance'" class="report-tab-content" role="tabpanel">
          <AttendanceReport
            :initial-year-id="filters.academic_year_id"
            :initial-course-id="filters.course_section_id"
          />
        </section>

        <section v-if="mountedTabs.detail" v-show="activeTab === 'detail'" class="report-tab-content" role="tabpanel">
          <article class="report-panel detail-panel">
            <div class="detail-toolbar">
              <div>
                <span class="panel-kicker">Nómina consolidada</span>
                <h5>Detalle de estudiantes</h5>
              </div>
              <div class="detail-tools">
                <div class="input-icon-wrap detail-search">
                  <i class="bx bx-search"></i>
                  <input v-model.trim="detailSearch" type="search" class="form-control" placeholder="Filtrar resultados" aria-label="Filtrar detalle" :disabled="detailLoading" />
                </div>
                <select v-model.number="detailPageSize" class="form-select detail-page-size" aria-label="Filas por página">
                  <option :value="15">15 filas</option><option :value="30">30 filas</option><option :value="50">50 filas</option>
                </select>
              </div>
            </div>

            <div v-if="detailError" class="alert alert-danger report-alert detail-alert" role="alert">
              <div><i class="bx bx-error-circle"></i>{{ detailError }}</div>
              <button type="button" class="btn btn-sm btn-outline-danger" @click="loadDetails(detailPage)">Reintentar</button>
            </div>

            <div class="table-responsive">
              <table class="table report-table detail-table mb-0">
                <thead>
                  <tr>
                    <th><button type="button" @click="setDetailSort('name')">Estudiante<i class="bx" :class="sortIcon('name')"></i></button></th>
                    <th>RUT</th>
                    <th><button type="button" @click="setDetailSort('course')">Curso<i class="bx" :class="sortIcon('course')"></i></button></th>
                    <th>Estado</th>
                    <th>Edad</th>
                    <th>Comuna</th>
                    <th>PIE</th>
                    <th>Calidad</th>
                    <th class="text-end">Ficha</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="detailLoading"><td colspan="9" class="empty-table-cell"><i class="bx bx-loader-alt bx-spin me-1"></i>Cargando estudiantes...</td></tr>
                  <template v-else>
                    <tr v-for="row in paginatedDetailRows" :key="row.id" class="detail-row" @dblclick="openStudent(row)">
                      <td><div class="student-cell"><span class="student-avatar">{{ row.name?.charAt(0) || "E" }}</span><div><strong>{{ row.name }}</strong><small>{{ row.nationality || "Sin nacionalidad" }}</small></div></div></td>
                      <td class="text-nowrap">{{ row.rut || "-" }}</td>
                      <td><strong>{{ row.course || "-" }}</strong><small class="d-block text-muted">{{ row.level || "" }}</small></td>
                      <td><span class="badge" :class="`bg-${statusVariant(row.enrollment_status)}-subtle text-${statusVariant(row.enrollment_status)}`">{{ enrollmentStatusLabel(row.enrollment_status) }}</span></td>
                      <td>{{ row.age ?? "-" }}</td>
                      <td>{{ row.commune || "-" }}</td>
                      <td><span class="binary-indicator" :class="{ active: row.is_pie_participant }"><i class="bx" :class="row.is_pie_participant ? 'bx-check' : 'bx-minus'"></i>{{ row.is_pie_participant ? "Sí" : "No" }}</span></td>
                      <td><span class="quality-score" :class="`quality-${qualityVariant(row.quality_score)}`">{{ formatPercent(row.quality_score) }}</span></td>
                      <td class="text-end"><button type="button" class="btn btn-sm btn-light table-action" title="Abrir ficha" aria-label="Abrir ficha" @click="openStudent(row)"><i class="bx bx-right-arrow-alt"></i></button></td>
                    </tr>
                    <tr v-if="!paginatedDetailRows.length"><td colspan="9" class="empty-table-cell">No hay estudiantes que coincidan con la búsqueda</td></tr>
                  </template>
                </tbody>
              </table>
            </div>

            <footer class="detail-footer">
              <span>{{ detailRangeLabel }}</span>
              <div class="detail-pagination">
                <button type="button" title="Página anterior" aria-label="Página anterior" :disabled="detailLoading || detailPage <= 1" @click="moveDetailPage(-1)"><i class="bx bx-chevron-left"></i></button>
                <span>Página {{ detailPage }} de {{ detailTotalPages }}</span>
                <button type="button" title="Página siguiente" aria-label="Página siguiente" :disabled="detailLoading || detailPage >= detailTotalPages" @click="moveDetailPage(1)"><i class="bx bx-chevron-right"></i></button>
              </div>
            </footer>
          </article>
        </section>

        <div v-if="pdfCaptureMounted" class="pdf-chart-capture-stage" aria-hidden="true">
          <div><apexchart ref="pdfEnrollmentTrendChart" type="bar" height="320" :options="enrollmentTrendOptions" :series="enrollmentTrendSeries" /></div>
          <div v-if="movementTrendTotal"><apexchart ref="pdfMovementTrendChart" type="bar" height="320" :options="movementTrendOptions" :series="movementTrendSeries" /></div>
          <div><apexchart ref="pdfCourseChart" type="bar" :height="courseChartHeight" :options="courseOptions" :series="courseSeries" /></div>
          <div><apexchart ref="pdfStatusChart" type="donut" height="330" :options="statusOptions" :series="statusSeries" /></div>
          <div><apexchart ref="pdfAgeChart" type="bar" height="330" :options="ageOptions" :series="ageSeries" /></div>
          <div><apexchart ref="pdfSupportChart" type="bar" height="340" :options="supportOptions" :series="supportSeries" /></div>
          <div><apexchart ref="pdfQualityChart" type="donut" height="340" :options="qualityOptions" :series="qualitySeries" /></div>
          <div><apexchart ref="pdfEthnicityChart" type="bar" height="320" :options="ethnicityOptions" :series="ethnicitySeries" /></div>
          <div><apexchart ref="pdfReligionChart" type="donut" height="300" :options="religionOptions" :series="religionSeries" /></div>
          <div><apexchart ref="pdfFamilyChart" type="donut" height="320" :options="parentPresenceOptions" :series="parentPresenceSeries" /></div>
          <div><apexchart ref="pdfHealthChart" type="bar" height="360" :options="healthConditionOptions" :series="healthConditionSeries" /></div>
          <div v-if="infirmaryEnabled"><apexchart ref="pdfInfirmaryChart" type="bar" height="340" :options="infirmaryCategoryOptions" :series="infirmaryCategorySeries" /></div>
        </div>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.student-report-page {
  --report-ink: #1f2937;
  --report-muted: #64748b;
  --report-border: #e3e8ef;
  --report-surface: #ffffff;
  --report-primary: #405189;
  color: var(--report-ink);
  padding-bottom: 1.5rem;
}

.report-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 0.15rem 0 1rem;
}

.report-eyebrow,
.panel-kicker {
  color: #68758a;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.report-heading h4 {
  margin: 0.18rem 0 0.32rem;
  color: var(--report-ink);
  font-size: 1.35rem;
  font-weight: 700;
}

.report-meta-line {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 1.1rem;
  color: var(--report-muted);
  font-size: 0.78rem;
}

.report-meta-line span {
  display: inline-flex;
  align-items: center;
  gap: 0.32rem;
}

.report-meta-line i { font-size: 0.95rem; }

.report-actions {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.report-actions .btn,
.filter-actions .btn {
  display: inline-flex;
  min-height: 34px;
  align-items: center;
  justify-content: center;
  gap: 0.38rem;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 600;
}

.report-icon-button {
  width: 36px;
  padding: 0;
  border: 1px solid var(--report-border);
}

.report-icon-button i { font-size: 1.18rem; }

.report-filter-panel {
  margin-bottom: 1rem;
  padding: 0.78rem;
  border: 1px solid var(--report-border);
  border-radius: 8px;
  background: var(--report-surface);
  box-shadow: 0 3px 12px rgba(31, 41, 55, 0.035);
}

.filter-primary-grid,
.filter-advanced-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 0.65rem;
  align-items: end;
}

.filter-field { grid-column: span 2; min-width: 0; }
.filter-actions { grid-column: span 3; display: flex; gap: 0.45rem; }

.filter-field label {
  display: block;
  margin-bottom: 0.22rem;
  color: #526071;
  font-size: 0.68rem;
  font-weight: 700;
}

.filter-field .form-control,
.filter-field .form-select,
.detail-tools .form-control,
.detail-tools .form-select {
  min-height: 34px;
  padding-top: 0.35rem;
  padding-bottom: 0.35rem;
  border-color: #d8dee8;
  border-radius: 5px;
  font-size: 0.78rem;
}

.filter-field .form-control:focus,
.filter-field .form-select:focus,
.detail-tools .form-control:focus,
.detail-tools .form-select:focus {
  border-color: #8da0d4;
  box-shadow: 0 0 0 0.15rem rgba(64, 81, 137, 0.12);
}

.filter-more-button { position: relative; white-space: nowrap; }
.filter-count {
  display: inline-flex;
  width: 18px;
  height: 18px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #405189;
  color: #fff;
  font-size: 0.65rem;
}

.filter-advanced-grid {
  margin-top: 0.72rem;
  padding-top: 0.72rem;
  border-top: 1px solid #edf0f4;
}

.filter-search-field { grid-column: span 3; }
.input-icon-wrap { position: relative; }
.input-icon-wrap > i {
  position: absolute;
  z-index: 2;
  top: 50%;
  left: 0.68rem;
  color: #8a95a5;
  font-size: 1rem;
  transform: translateY(-50%);
  pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 2rem; }
.filter-reset { grid-column: span 1; align-self: end; min-height: 34px; padding: 0.35rem; font-size: 0.75rem; text-decoration: none; }

.report-alert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-radius: 6px;
  font-size: 0.8rem;
}
.report-alert > div { display: flex; align-items: center; gap: 0.45rem; }

.report-loading-panel,
.report-panel {
  border: 1px solid var(--report-border);
  border-radius: 8px;
  background: var(--report-surface);
}
.report-loading-panel { padding: 2rem; }

.flow-chart-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  margin-top: 0.7rem;
  border-top: 1px solid #edf0f4;
}
.flow-chart-section { min-width: 0; padding: 0.85rem 0.85rem 0; }
.flow-chart-section:first-child { padding-left: 0; }
.flow-chart-section:last-child { padding-right: 0; border-left: 1px solid #edf0f4; }
.flow-chart-heading {
  display: flex;
  min-height: 32px;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0 0.35rem 0.35rem;
}
.flow-chart-heading > div { display: flex; min-width: 0; align-items: center; gap: 0.4rem; }
.flow-chart-heading h6 { margin: 0; color: #344054; font-size: 0.76rem; font-weight: 700; }
.flow-chart-heading > strong { min-width: 28px; padding: 0.22rem 0.4rem; border-radius: 4px; background: #edf1f5; color: #344054; font-size: 0.72rem; text-align: center; }
.flow-chart-heading i { color: #526da5; font-size: 1rem; }
.flow-data-note {
  display: flex;
  align-items: flex-start;
  gap: 0.38rem;
  margin: 0.1rem 0.35rem 0.35rem;
  padding: 0.42rem 0.55rem;
  border-left: 3px solid #d69e2e;
  background: #fff8e8;
  color: #725a1e;
  font-size: 0.68rem;
  line-height: 1.4;
}
.flow-data-note i { margin-top: 0.05rem; flex: 0 0 auto; font-size: 0.9rem; }
.flow-chart-dot { display: inline-block; width: 9px; height: 9px; flex: 0 0 9px; border-radius: 50%; }
.flow-chart-dot-enrollment { background: #2f855a; }
.flow-empty-state {
  display: flex;
  min-height: 280px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 1rem;
  color: #7a8697;
  text-align: center;
}
.flow-empty-state i { margin-bottom: 0.55rem; color: #2f855a; font-size: 1.65rem; }
.flow-empty-state strong { color: #344054; font-size: 0.8rem; }
.flow-empty-state span { max-width: 280px; margin-top: 0.22rem; font-size: 0.7rem; line-height: 1.45; }

.metric-grid {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 0.7rem;
  margin-bottom: 1rem;
}

.metric-card {
  display: flex;
  min-width: 0;
  min-height: 88px;
  align-items: center;
  gap: 0.68rem;
  padding: 0.76rem;
  border: 1px solid var(--report-border);
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 3px 12px rgba(31, 41, 55, 0.035);
}

.metric-icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  align-items: center;
  justify-content: center;
  border-radius: 7px;
  font-size: 1.15rem;
}

.metric-content { min-width: 0; }
.metric-content span { display: block; color: var(--report-muted); font-size: 0.68rem; line-height: 1.25; }
.metric-content strong { display: block; margin-top: 0.22rem; color: var(--report-ink); font-size: 1.28rem; line-height: 1; }
.metric-green .metric-icon { background: #e7f5ec; color: #28764d; }
.metric-blue .metric-icon { background: #e9effb; color: #405189; }
.metric-cyan .metric-icon { background: #e5f6f6; color: #0f766e; }
.metric-red .metric-icon { background: #fbeaec; color: #b42332; }
.metric-amber .metric-icon { background: #fff4dc; color: #9a6700; }
.metric-violet .metric-icon { background: #f1ebfa; color: #7651a8; }
.metric-teal .metric-icon { background: #e6f3f1; color: #187466; }
.metric-slate .metric-icon { background: #edf1f5; color: #526071; }

.report-tabs {
  display: flex;
  gap: 0.25rem;
  margin-bottom: 0.85rem;
  padding: 0 0.25rem;
  overflow-x: auto;
  border-bottom: 1px solid var(--report-border);
}

.report-tabs button {
  display: inline-flex;
  min-height: 40px;
  align-items: center;
  gap: 0.38rem;
  padding: 0.55rem 0.78rem;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #657286;
  font-size: 0.78rem;
  font-weight: 600;
  white-space: nowrap;
}
.report-tabs button:hover { color: #405189; }
.report-tabs button.active { border-bottom-color: #405189; color: #314475; }
.report-tabs i { font-size: 1rem; }

.report-tab-content { display: grid; gap: 0.85rem; }
.pdf-chart-capture-stage {
  position: fixed;
  top: 0;
  left: -12000px;
  z-index: -1;
  width: 1100px;
  opacity: 0;
  pointer-events: none;
}
.pdf-chart-capture-stage > div { width: 1100px; background: #fff; }
.analysis-grid { display: grid; gap: 0.85rem; }
.analysis-grid-wide { grid-template-columns: minmax(0, 2fr) minmax(270px, 0.8fr); }
.analysis-grid-even { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.report-panel { min-width: 0; padding: 1rem; box-shadow: 0 3px 12px rgba(31, 41, 55, 0.03); }

.panel-heading,
.detail-toolbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.55rem;
}
.panel-heading h5,
.detail-toolbar h5 { margin: 0.14rem 0 0; color: var(--report-ink); font-size: 0.96rem; font-weight: 700; }
.panel-heading-actions { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 0.55rem; }
.panel-period,
.panel-total { color: var(--report-muted); font-size: 0.72rem; white-space: nowrap; }

.executive-list { margin: 0.5rem 0 0; }
.executive-list > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.78rem 0;
  border-bottom: 1px solid #edf0f4;
}
.executive-list > div:last-child { border-bottom: 0; }
.executive-list dt { color: #68758a; font-size: 0.76rem; font-weight: 500; }
.executive-list dd { margin: 0; color: var(--report-ink); font-size: 1rem; font-weight: 700; }

.rank-list { margin-top: 0.5rem; }
.rank-row { display: grid; grid-template-columns: 26px minmax(0, 1fr) auto; align-items: center; gap: 0.5rem; padding: 0.65rem 0; border-bottom: 1px solid #edf0f4; font-size: 0.78rem; }
.rank-row:last-child { border-bottom: 0; }
.rank-index { display: inline-flex; width: 22px; height: 22px; align-items: center; justify-content: center; border-radius: 50%; background: #edf1f5; color: #526071; font-size: 0.68rem; font-weight: 700; }
.rank-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty-inline { padding: 2rem 0; color: var(--report-muted); text-align: center; font-size: 0.78rem; }

.report-table { color: #343a40; font-size: 0.76rem; }
.report-table thead th { padding: 0.58rem 0.65rem; border-bottom-width: 1px; background: #f7f9fb; color: #526071; font-size: 0.68rem; font-weight: 700; white-space: nowrap; }
.report-table tbody td { padding: 0.62rem 0.65rem; border-color: #edf0f4; vertical-align: middle; }
.occupancy-cell { display: grid; grid-template-columns: minmax(90px, 1fr) 42px; align-items: center; gap: 0.55rem; min-width: 155px; }
.occupancy-cell .progress { height: 6px; background: #e8edf3; }
.occupancy-cell .progress-bar { background: #2f855a; }
.occupancy-cell span { color: #526071; font-size: 0.7rem; text-align: right; }
.empty-table-cell { padding: 2rem !important; color: var(--report-muted) !important; text-align: center; }

.detail-panel { padding: 0; overflow: hidden; }
.detail-toolbar { margin: 0; padding: 0.9rem 1rem; border-bottom: 1px solid var(--report-border); }
.detail-alert { margin: 0.75rem 1rem 0; }
.detail-tools { display: flex; gap: 0.5rem; }
.detail-search { width: min(280px, 34vw); }
.detail-page-size { width: 105px; }
.detail-table thead th button { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0; border: 0; background: transparent; color: inherit; font: inherit; }
.detail-row { cursor: default; }
.detail-row:hover td { background: #fafbfd; }
.student-cell { display: flex; min-width: 210px; align-items: center; gap: 0.58rem; }
.student-avatar { display: inline-flex; width: 30px; height: 30px; flex: 0 0 30px; align-items: center; justify-content: center; border-radius: 50%; background: #e9effb; color: #405189; font-weight: 700; }
.student-cell strong { display: block; max-width: 220px; overflow: hidden; color: #273244; font-size: 0.76rem; text-overflow: ellipsis; white-space: nowrap; }
.student-cell small { display: block; color: var(--report-muted); font-size: 0.66rem; }
.detail-table .badge { padding: 0.32rem 0.48rem; font-size: 0.66rem; font-weight: 600; }
.binary-indicator { display: inline-flex; align-items: center; gap: 0.18rem; color: #7b8796; font-size: 0.7rem; }
.binary-indicator.active { color: #28764d; font-weight: 700; }
.quality-score { display: inline-flex; min-width: 44px; justify-content: center; padding: 0.22rem 0.35rem; border-radius: 4px; font-size: 0.68rem; font-weight: 700; }
.quality-success { background: #e7f5ec; color: #28764d; }
.quality-warning { background: #fff4dc; color: #9a6700; }
.quality-danger { background: #fbeaec; color: #b42332; }
.table-action { display: inline-flex; width: 30px; height: 30px; align-items: center; justify-content: center; border: 1px solid #e1e6ed; border-radius: 5px; }
.table-action i { font-size: 1rem; }
.detail-footer { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.72rem 1rem; border-top: 1px solid var(--report-border); color: var(--report-muted); font-size: 0.72rem; }
.detail-pagination { display: flex; align-items: center; gap: 0.6rem; }
.detail-pagination button { display: inline-flex; width: 28px; height: 28px; align-items: center; justify-content: center; border: 1px solid #d8dee8; border-radius: 5px; background: #fff; color: #405189; }
.detail-pagination button:disabled { color: #adb5bd; cursor: not-allowed; }
.detail-pagination i { font-size: 1rem; }

.context-section-label {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  min-height: 26px;
  color: #526071;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}
.context-section-label::after { height: 1px; flex: 1; background: #dfe5ec; content: ""; }
.religion-class-strip {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid #edf0f4;
}
.religion-class-strip span { padding: 0.58rem 0.5rem; color: #68758a; font-size: 0.68rem; text-align: center; }
.religion-class-strip span + span { border-left: 1px solid #edf0f4; }
.religion-class-strip strong { display: block; margin-top: 0.12rem; color: #273244; font-size: 0.92rem; }
.family-section-panel { padding: 0; overflow: hidden; }
.family-section-heading {
  display: flex;
  align-items: center;
  gap: 0.68rem;
  padding: 0.9rem 1rem;
}
.family-section-heading h5 { margin: 0.12rem 0 0; color: var(--report-ink); font-size: 0.96rem; font-weight: 700; }
.family-section-icon {
  display: inline-flex;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  background: #e9effb;
  color: #405189;
  font-size: 1.05rem;
}
.family-dataset-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1px;
  border-top: 1px solid #e5e9ef;
  background: #e5e9ef;
}
.family-dataset-grid-three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.family-dataset { min-width: 0; padding: 0.9rem 1rem 1rem; background: #fff; }
.family-dataset-heading {
  display: flex;
  min-height: 34px;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.72rem;
}
.family-dataset-heading h6 {
  min-width: 0;
  margin: 0;
  color: #344054;
  font-size: 0.76rem;
  font-weight: 700;
  line-height: 1.35;
  overflow-wrap: anywhere;
}
.family-dataset-title { min-width: 0; }
.family-dataset-title > span { display: block; margin-top: 0.18rem; color: #7a8697; font-size: 0.65rem; white-space: nowrap; }
.family-distribution-list { display: grid; gap: 0.65rem; }
.family-distribution-row { min-width: 0; }
.family-distribution-meta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.65rem;
  margin-bottom: 0.28rem;
  font-size: 0.71rem;
}
.family-distribution-meta > span { min-width: 0; color: #526071; overflow-wrap: anywhere; }
.family-distribution-meta strong { flex: 0 0 auto; color: #273244; font-size: 0.72rem; }
.family-distribution-meta small { margin-left: 0.18rem; color: #8490a0; font-size: 0.62rem; font-weight: 500; }
.family-distribution-track { height: 5px; overflow: hidden; border-radius: 3px; background: #edf1f5; }
.family-distribution-track span { display: block; height: 100%; border-radius: inherit; background: #5b6da8; }
.parent-presence-layout { display: grid; grid-template-columns: minmax(280px, 0.75fr) minmax(300px, 1fr); align-items: center; gap: 1.5rem; }
.parent-presence-list > div { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.72rem 0; border-bottom: 1px solid #edf0f4; font-size: 0.76rem; }
.parent-presence-list > div:last-child { border-bottom: 0; }
.parent-presence-list span { color: #526071; }
.parent-presence-list strong { color: #273244; }
.infirmary-summary-strip { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); border-top: 1px solid #edf0f4; }
.infirmary-summary-strip > div { min-width: 0; padding: 0.75rem; }
.infirmary-summary-strip > div + div { border-left: 1px solid #edf0f4; }
.infirmary-summary-strip span { display: block; color: #68758a; font-size: 0.67rem; line-height: 1.25; }
.infirmary-summary-strip strong { display: block; margin-top: 0.25rem; color: #273244; font-size: 1.15rem; }
.infirmary-overview-panel { padding-bottom: 0.25rem; }
.report-permission-state { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; border: 1px dashed #cfd7e3; border-radius: 8px; background: #f8fafc; color: #526071; }
.report-permission-state > i { font-size: 1.35rem; }
.report-permission-state strong,
.report-permission-state span { display: block; }
.report-permission-state strong { color: #344054; font-size: 0.78rem; }
.report-permission-state span { margin-top: 0.15rem; font-size: 0.7rem; }

@media (max-width: 1399.98px) {
  .metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .filter-field { grid-column: span 3; }
  .filter-actions { grid-column: span 3; }
  .filter-search-field { grid-column: span 4; }
}

@media (max-width: 991.98px) {
  .report-header { align-items: stretch; }
  .analysis-grid-wide,
  .analysis-grid-even { grid-template-columns: 1fr; }
  .filter-field,
  .filter-search-field { grid-column: span 4; }
  .filter-actions { grid-column: span 4; }
  .family-dataset-grid-three { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .infirmary-summary-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .infirmary-summary-strip > div:nth-child(4) { border-left: 0; }
  .infirmary-summary-strip > div:nth-child(n + 4) { border-top: 1px solid #edf0f4; }
}

@media (max-width: 767.98px) {
  .report-header { flex-direction: column; gap: 0.8rem; }
  .report-actions { width: 100%; }
  .report-actions .btn:not(.report-icon-button) { flex: 1; }
  .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .filter-field,
  .filter-search-field { grid-column: span 6; }
  .filter-actions { grid-column: span 12; }
  .filter-actions .btn { flex: 1; }
  .detail-toolbar { flex-direction: column; }
  .detail-tools { width: 100%; }
  .detail-search { width: 100%; }
  .detail-footer { align-items: flex-start; flex-direction: column; }
  .report-panel { padding: 0.8rem; }
  .flow-chart-grid { grid-template-columns: minmax(0, 1fr); }
  .flow-chart-section,
  .flow-chart-section:first-child,
  .flow-chart-section:last-child { padding: 0.8rem 0 0; }
  .flow-chart-section:last-child { border-top: 1px solid #edf0f4; border-left: 0; }
  .detail-panel,
  .family-section-panel { padding: 0; }
  .family-dataset-grid,
  .family-dataset-grid-three { grid-template-columns: 1fr; }
  .parent-presence-layout { grid-template-columns: minmax(0, 1fr); gap: 0.5rem; }
  .parent-presence-layout > * { min-width: 0; }
}

@media (max-width: 479.98px) {
  .report-meta-line { flex-direction: column; gap: 0.25rem; }
  .filter-field,
  .filter-search-field { grid-column: span 12; }
  .metric-card { min-height: 78px; padding: 0.62rem; }
  .metric-icon { width: 30px; height: 30px; flex-basis: 30px; }
  .metric-content strong { font-size: 1.08rem; }
  .report-tabs { padding: 0; }
  .report-tabs button { padding-inline: 0.58rem; }
  .detail-tools { flex-direction: column; }
  .detail-page-size { width: 100%; }
  .religion-class-strip { grid-template-columns: 1fr; }
  .religion-class-strip span + span { border-top: 1px solid #edf0f4; border-left: 0; }
  .infirmary-summary-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .infirmary-summary-strip > div:nth-child(odd) { border-left: 0; }
  .infirmary-summary-strip > div:nth-child(n + 3) { border-top: 1px solid #edf0f4; }
}
</style>
