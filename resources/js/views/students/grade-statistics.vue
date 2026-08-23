<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";
import Layout from "../../layouts/main.vue";

const dashboard = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref("");
const activeView = ref("overview");
const selectedCourse = ref(null);
const studentRows = ref([]);
const studentPagination = ref({ page: 1, per_page: 25, total: 0, last_page: 1 });
const studentLoading = ref(false);
const studentError = ref("");
const studentSearch = ref("");
const studentDetail = ref(null);
const studentDetailOpen = ref(false);
const studentDetailLoading = ref(false);
const studentDetailError = ref("");
const studentDetailSubject = ref("all");

const filters = reactive({
  academic_year_id: null,
  course_section_id: null,
  schedule_subject_id: null,
  assessment_period_code: null,
});

const permissions = (() => {
  try {
    return JSON.parse(localStorage.getItem("permissions") || "[]");
  } catch {
    return [];
  }
})();

const canViewStudents = computed(() => permissions.includes("grade_statistics.view_students") || permissions.includes("__superadmin__"));
const summary = computed(() => dashboard.value?.summary || {});
const catalogs = computed(() => dashboard.value?.catalogs || {});
const hasAssessments = computed(() => Number(summary.value.assessments || 0) > 0);
const sourceTotal = computed(() => Number(summary.value.imported_results || 0) + Number(summary.value.manual_results || 0));
const activeFilterCount = computed(() => [filters.course_section_id, filters.schedule_subject_id, filters.assessment_period_code].filter(Boolean).length);
const studentPanelTitle = computed(() => activeView.value === "course" ? `Detalle de ${selectedCourse.value?.name || "curso"}` : "Consolidado por alumna");
const studentPanelDescription = computed(() => activeView.value === "course"
  ? "Resultados individuales del curso seleccionado, ordenados para detectar pendientes y brechas de cobertura."
  : "Cada alumna aparece una sola vez, integrando sus resultados del año aunque haya estado asociada a más de un curso.");
const filteredStudentEvaluations = computed(() => {
  const evaluations = studentDetail.value?.evaluations || [];
  if (studentDetailSubject.value === "all") return evaluations;
  return evaluations.filter((evaluation) => Number(evaluation.subject?.id) === Number(studentDetailSubject.value));
});

const kpis = computed(() => [
  { key: "average", label: "Promedio equivalente", value: formatGrade(summary.value.average_grade), note: `${formatNumber(summary.value.comparable_results)} notas comparables`, icon: "bx-line-chart", tone: "indigo" },
  { key: "approval", label: "Aprobación", value: formatPercent(summary.value.approval_rate), note: `${formatNumber(summary.value.passed_results)} aprobadas de ${formatNumber(summary.value.comparable_results)}`, icon: "bx-check-shield", tone: "teal" },
  { key: "coverage", label: "Cobertura de registro", value: formatPercent(summary.value.coverage_rate), note: `${formatNumber(summary.value.completed_results)} de ${formatNumber(summary.value.expected_results)} resultados`, icon: "bx-target-lock", tone: "blue" },
  { key: "pending", label: "Pendientes", value: formatNumber(summary.value.pending_results), note: "Incluye P y resultados sin registrar", icon: "bx-time-five", tone: Number(summary.value.pending_results || 0) > 0 ? "amber" : "teal" },
  { key: "assessments", label: "Evaluaciones", value: formatNumber(summary.value.assessments), note: `${formatNumber(summary.value.students_evaluated)} estudiantes evaluadas`, icon: "bx-spreadsheet", tone: "slate" },
]);

const distributionTotal = computed(() => (dashboard.value?.distribution || []).reduce((sum, item) => sum + Number(item.count || 0), 0));
const distributionSeries = computed(() => (dashboard.value?.distribution || []).map((item) => Number(item.count || 0)));
const distributionOptions = computed(() => ({
  chart: { type: "donut", fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false } },
  labels: (dashboard.value?.distribution || []).map((item) => item.description),
  colors: ["#d65c68", "#d69a37", "#36a092", "#405189"],
  dataLabels: { enabled: false },
  legend: { position: "bottom", fontSize: "11px", markers: { width: 8, height: 8, radius: 8 } },
  stroke: { width: 3, colors: ["#ffffff"] },
  plotOptions: { pie: { donut: { size: "72%", labels: { show: true, name: { show: true, color: "#748095" }, value: { show: true, color: "#202b3c", fontSize: "24px", fontWeight: 750 }, total: { show: true, label: "Notas", color: "#748095", formatter: () => formatNumber(distributionTotal.value) } } } } },
  tooltip: { y: { formatter: (value) => `${formatNumber(value)} notas` } },
}));

const progress = computed(() => dashboard.value?.evaluation_progress || []);
const progressOptions = computed(() => ({
  chart: { type: "line", fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false }, zoom: { enabled: false } },
  colors: ["#405189", "#36a092"],
  stroke: { curve: "smooth", width: [3, 2.5] },
  markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
  xaxis: {
    categories: progress.value.map((item) => item.label),
    labels: { rotate: progress.value.length > 8 ? -45 : 0, trim: false, style: { colors: "#748095", fontSize: "10px" } },
    axisBorder: { show: false }, axisTicks: { show: false },
    title: { text: "Secuencia de evaluaciones por asignatura", style: { color: "#748095", fontSize: "10px", fontWeight: 500 } },
  },
  yaxis: [
    { min: 1, max: 7, tickAmount: 6, labels: { formatter: (value) => Number(value).toFixed(1), style: { colors: "#748095", fontSize: "10px" } }, title: { text: "Promedio 1–7", style: { color: "#748095", fontSize: "10px", fontWeight: 500 } } },
    { opposite: true, min: 0, max: 100, tickAmount: 4, labels: { formatter: (value) => `${Math.round(value)}%`, style: { colors: "#748095", fontSize: "10px" } }, title: { text: "Aprobación", style: { color: "#748095", fontSize: "10px", fontWeight: 500 } } },
  ],
  grid: { borderColor: "#edf0f4", strokeDashArray: 4, padding: { left: 8, right: 8 } },
  legend: { position: "top", horizontalAlign: "right", fontSize: "11px", markers: { width: 8, height: 8, radius: 8 } },
  tooltip: { shared: true, y: [{ formatter: (value) => formatGrade(value) }, { formatter: (value) => formatPercent(value) }] },
}));
const progressSeries = computed(() => [
  { name: "Promedio", type: "line", data: progress.value.map((item) => item.average_grade) },
  { name: "Aprobación", type: "line", data: progress.value.map((item) => item.approval_rate) },
]);

const subjectOptions = computed(() => ({
  chart: { type: "bar", fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false } },
  colors: ["#6073aa"], plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: "52%" } },
  dataLabels: { enabled: true, formatter: (value) => `${formatNumber(value)} eval.`, style: { fontSize: "10px", fontWeight: 750 }, offsetX: 5 },
  xaxis: { categories: (dashboard.value?.by_subject || []).slice(0, 12).map((item) => item.name), min: 0, tickAmount: 5, labels: { formatter: (value) => Math.round(value), style: { colors: "#748095", fontSize: "10px" } }, title: { text: "Cantidad de evaluaciones", style: { color: "#748095", fontSize: "10px", fontWeight: 500 } } },
  yaxis: { labels: { maxWidth: 150, style: { colors: "#536176", fontSize: "10px" } } },
  grid: { borderColor: "#edf0f4", strokeDashArray: 4 }, tooltip: { y: { formatter: (value) => `${formatNumber(value)} evaluaciones` } },
}));
const subjectSeries = computed(() => [{ name: "Evaluaciones", data: (dashboard.value?.by_subject || []).slice(0, 12).map((item) => item.assessments || 0) }]);

function dashboardParams() {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== ""));
}

async function loadDashboard({ resetDependent = false } = {}) {
  error.value = "";
  if (dashboard.value) refreshing.value = true;
  else loading.value = true;
  if (resetDependent) {
    filters.course_section_id = null;
    filters.schedule_subject_id = null;
    filters.assessment_period_code = null;
    activeView.value = "overview";
  }
  try {
    const { data } = await axios.get("/api/students/grades/statistics", { params: dashboardParams() });
    dashboard.value = data;
    if (!filters.academic_year_id) filters.academic_year_id = data.meta?.academic_year?.id || null;
    if (activeView.value !== "overview" && canViewStudents.value) await loadStudents(1);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || Object.values(requestError.response?.data?.errors || {})?.flat()?.[0] || "No fue posible calcular las estadísticas de calificaciones.";
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
}

async function loadStudents(page = 1) {
  if (!canViewStudents.value) return;
  studentLoading.value = true;
  studentError.value = "";
  try {
    const params = {
      academic_year_id: filters.academic_year_id,
      schedule_subject_id: filters.schedule_subject_id,
      assessment_period_code: filters.assessment_period_code,
      search: studentSearch.value.trim() || null,
      page,
      per_page: studentPagination.value.per_page || 25,
    };
    if (activeView.value === "course") params.course_section_id = selectedCourse.value?.id;
    const { data } = await axios.get("/api/students/grades/statistics/students", {
      params: Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== "")),
    });
    studentRows.value = data.data || [];
    studentPagination.value = data.pagination || studentPagination.value;
  } catch (requestError) {
    studentError.value = requestError.response?.data?.message || "No fue posible cargar el detalle nominal.";
  } finally {
    studentLoading.value = false;
  }
}

function openCourseDetail(course) {
  selectedCourse.value = course;
  activeView.value = "course";
  studentSearch.value = "";
  loadStudents(1);
  requestAnimationFrame(() => document.querySelector("#student-explorer")?.scrollIntoView({ behavior: "smooth", block: "start" }));
}

function openStudentConsolidation() {
  selectedCourse.value = null;
  activeView.value = "students";
  studentSearch.value = "";
  loadStudents(1);
  requestAnimationFrame(() => document.querySelector("#student-explorer")?.scrollIntoView({ behavior: "smooth", block: "start" }));
}

async function openStudentDetail(student) {
  studentDetailOpen.value = true;
  studentDetailLoading.value = true;
  studentDetailError.value = "";
  studentDetail.value = null;
  studentDetailSubject.value = "all";
  try {
    const params = {
      academic_year_id: filters.academic_year_id,
      schedule_subject_id: filters.schedule_subject_id,
      assessment_period_code: filters.assessment_period_code,
    };
    if (activeView.value === "course") params.course_section_id = selectedCourse.value?.id;
    const { data } = await axios.get(`/api/students/grades/statistics/students/${student.student_profile_id}`, {
      params: Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== "" && value !== undefined)),
    });
    studentDetail.value = data;
  } catch (requestError) {
    studentDetailError.value = requestError.response?.data?.message
      || Object.values(requestError.response?.data?.errors || {})?.flat()?.[0]
      || "No fue posible cargar el detalle académico de la alumna.";
  } finally {
    studentDetailLoading.value = false;
  }
}

function closeStudentDetail() {
  studentDetailOpen.value = false;
  studentDetailError.value = "";
}

function resetFilters() {
  filters.course_section_id = null;
  filters.schedule_subject_id = null;
  filters.assessment_period_code = null;
  activeView.value = "overview";
  selectedCourse.value = null;
  loadDashboard();
}

function formatNumber(value) { return new Intl.NumberFormat("es-CL").format(Number(value || 0)); }
function formatGrade(value) { return value === null || value === undefined ? "—" : Number(value).toLocaleString("es-CL", { minimumFractionDigits: 1, maximumFractionDigits: 2 }); }
function formatPercent(value) { return value === null || value === undefined ? "—" : `${Number(value).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`; }
function studentInitials(name) { return String(name || "A").split(" ").filter(Boolean).slice(0, 2).map((part) => part[0]).join("").slice(0, 2).toUpperCase(); }
function resultTone(state) {
  if (state === "recorded") return "recorded";
  if (state === "absent" || state === "exempt" || state === "not_applicable") return "neutral";
  return "pending";
}
function resultValue(evaluation) {
  if (evaluation.grade !== null && evaluation.grade !== undefined) return formatGrade(evaluation.grade);
  if (evaluation.qualitative_value) return evaluation.qualitative_value;
  return evaluation.state_label;
}
function resultSource(source) {
  if (source === "annual_import") return "Importación anual";
  if (source === "direct_entry") return "Registro directo";
  return "Sin registro";
}
function rateTone(rate, inverse = false) {
  const value = Number(rate || 0);
  if (inverse) return value === 0 ? "good" : value <= 5 ? "watch" : "risk";
  return value >= 90 ? "good" : value >= 70 ? "watch" : "risk";
}

onMounted(() => loadDashboard());
</script>

<template>
  <Layout>
    <main class="page-content grade-statistics-page">
      <div class="container-fluid grade-statistics-container">
        <header class="grade-hero">
          <div class="hero-copy">
            <span class="eyebrow">INTELIGENCIA ACADÉMICA</span>
            <h1>Estadísticas de calificaciones</h1>
            <p>Compara desempeño por secuencia de evaluaciones, revisa cada curso y consolida la trayectoria anual de cada alumna.</p>
            <div class="hero-meta">
              <span><i class="bx bx-building-house"></i>{{ dashboard?.meta?.school?.name || "Establecimiento" }}</span>
              <span><i class="bx bx-calendar"></i>{{ dashboard?.meta?.academic_year?.name || "Año académico" }}</span>
              <span><i class="bx bx-shield-quarter"></i>Detalle nominal con permiso explícito</span>
            </div>
          </div>
          <div class="hero-mark" aria-hidden="true"><i class="bx bx-line-chart"></i><span>E1…EN</span></div>
        </header>

        <section class="filters-card" aria-label="Filtros de calificaciones">
          <div class="filter-title"><i class="bx bx-filter-alt"></i><div><strong>Segmentar análisis</strong><span v-if="activeFilterCount">{{ activeFilterCount }} filtros aplicados</span><span v-else>Vista institucional completa</span></div></div>
          <label><span>Año académico</span><select v-model="filters.academic_year_id" @change="loadDashboard({ resetDependent: true })"><option v-for="year in catalogs.academic_years || []" :key="year.id" :value="year.id">{{ year.name }}</option></select></label>
          <label><span>Curso</span><select v-model="filters.course_section_id"><option :value="null">Todos los cursos</option><option v-for="course in catalogs.courses || []" :key="course.id" :value="course.id">{{ course.name }}</option></select></label>
          <label><span>Asignatura</span><select v-model="filters.schedule_subject_id"><option :value="null">Todas las asignaturas</option><option v-for="subject in catalogs.subjects || []" :key="subject.id" :value="subject.id">{{ subject.name }}</option></select></label>
          <label><span>Periodo</span><select v-model="filters.assessment_period_code"><option :value="null">Todos los periodos</option><option v-for="period in catalogs.periods || []" :key="period.id" :value="period.id">{{ period.name }}</option></select></label>
          <div class="filter-actions"><button v-if="activeFilterCount" type="button" class="reset-button" @click="resetFilters">Limpiar</button><button type="button" class="apply-button" :disabled="refreshing" @click="loadDashboard"><span v-if="refreshing" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-search-alt"></i>Aplicar contexto</button></div>
        </section>

        <nav v-if="canViewStudents" class="view-switcher" aria-label="Vistas de estadísticas">
          <button type="button" :class="{ active: activeView === 'overview' }" @click="activeView = 'overview'"><i class="bx bx-grid-alt"></i><span><strong>Resumen institucional</strong><small>Indicadores agregados</small></span></button>
          <button type="button" :class="{ active: activeView === 'students' }" @click="openStudentConsolidation"><i class="bx bx-user-pin"></i><span><strong>Consolidado por alumna</strong><small>Independiente del curso</small></span></button>
          <button v-if="selectedCourse" type="button" :class="{ active: activeView === 'course' }" @click="openCourseDetail(selectedCourse)"><i class="bx bx-group"></i><span><strong>{{ selectedCourse.name }}</strong><small>Detalle del curso</small></span></button>
        </nav>

        <div v-if="loading && !dashboard" class="state-card"><span class="spinner-border text-primary"></span><strong>Calculando indicadores académicos</strong><p>Consolidando evaluaciones, matrícula efectiva y resultados…</p></div>
        <div v-else-if="error && !dashboard" class="state-card error"><i class="bx bx-error-circle"></i><strong>{{ error }}</strong><button type="button" class="btn btn-outline-danger btn-sm" @click="loadDashboard">Reintentar</button></div>

        <template v-else-if="dashboard">
          <div v-if="error" class="inline-error"><i class="bx bx-error-circle"></i><span>{{ error }}</span><button type="button" @click="loadDashboard">Reintentar</button></div>
          <section v-if="!hasAssessments" class="empty-dashboard"><div class="empty-icon"><i class="bx bx-bar-chart-square"></i></div><div><span>SIN EVALUACIONES EN EL SEGMENTO</span><h2>Aún no hay calificaciones para analizar</h2><p>Importa el archivo anual o registra evaluaciones en Libro Digital. La vista se actualizará sobre la misma fuente oficial.</p></div></section>

          <template v-else>
            <section class="kpi-grid" :class="{ refreshing }"><article v-for="item in kpis" :key="item.key" class="kpi-card" :class="`tone-${item.tone}`"><div class="kpi-icon"><i class="bx" :class="item.icon"></i></div><div class="kpi-content"><span>{{ item.label }}</span><strong>{{ item.value }}</strong><small>{{ item.note }}</small></div></article></section>
            <section class="signal-strip"><div class="signal-main"><i class="bx bx-data"></i><div><strong>Origen de los resultados registrados</strong><span>La procedencia se conserva para auditoría; ambos orígenes alimentan el mismo Libro Digital.</span></div></div><div class="source-stack"><div><span>Importación anual</span><strong>{{ formatNumber(summary.imported_results) }}</strong><em>{{ sourceTotal ? Math.round((summary.imported_results / sourceTotal) * 100) : 0 }}%</em></div><div><span>Registro directo</span><strong>{{ formatNumber(summary.manual_results) }}</strong><em>{{ sourceTotal ? Math.round((summary.manual_results / sourceTotal) * 100) : 0 }}%</em></div></div></section>

            <section class="analytics-grid" :class="{ refreshing }">
              <article class="panel panel-progress"><header><div><span>PROGRESIÓN POR EVALUACIÓN</span><h2>Desempeño según avance evaluativo</h2><p>Compara la primera, segunda y sucesivas evaluaciones de cada asignatura, sin utilizar fechas estimadas.</p></div><i class="bx bx-trending-up"></i></header><div v-if="progress.length" class="chart-scroll"><div :style="{ minWidth: `${Math.max(680, progress.length * 74)}px` }"><apexchart height="310" :options="progressOptions" :series="progressSeries" /></div></div><div v-else class="chart-empty"><i class="bx bx-spreadsheet"></i>Sin evaluaciones con resultados comparables</div></article>
              <article class="panel panel-distribution"><header><div><span>DISTRIBUCIÓN</span><h2>Tramos de calificación</h2><p>{{ formatNumber(distributionTotal) }} notas numéricas comparables.</p></div><i class="bx bx-doughnut-chart"></i></header><apexchart v-if="distributionTotal" height="280" :options="distributionOptions" :series="distributionSeries" /><div v-else class="chart-empty"><i class="bx bx-doughnut-chart"></i>Sin notas numéricas</div><div class="distribution-legend"><div v-for="item in dashboard.distribution" :key="item.key"><span :class="item.key"></span><p><strong>{{ item.label }}</strong><small>{{ item.description }}</small></p><em>{{ formatNumber(item.count) }}</em></div></div></article>
            </section>

            <section class="course-panel panel" :class="{ refreshing }">
              <header><div><span>COBERTURA POR CURSO</span><h2>Avance y resultados</h2><p>Selecciona un curso para revisar el detalle individual de sus alumnas.</p></div><div class="course-header-actions"><span class="row-count">{{ dashboard.by_course.length }} cursos</span><button v-if="canViewStudents" type="button" class="nominal-button" @click="openStudentConsolidation"><i class="bx bx-user-pin"></i>Consolidado por alumna</button></div></header>
              <div class="table-responsive"><table><thead><tr><th>Curso</th><th>Evaluaciones</th><th>Promedio</th><th>Aprobación</th><th>Cobertura</th><th>Pendientes</th><th v-if="canViewStudents">Detalle</th></tr></thead><tbody><tr v-for="course in dashboard.by_course" :key="course.id || course.name"><td><strong>{{ course.name }}</strong><small>{{ formatNumber(course.completed_results) }} resultados registrados</small></td><td>{{ formatNumber(course.assessments) }}</td><td><span class="grade-pill">{{ formatGrade(course.average_grade) }}</span></td><td><span class="rate-text" :class="rateTone(course.approval_rate)">{{ formatPercent(course.approval_rate) }}</span></td><td><div class="coverage-cell"><div><span :style="{ width: `${course.coverage_rate || 0}%` }" :class="rateTone(course.coverage_rate)"></span></div><strong>{{ formatPercent(course.coverage_rate) }}</strong></div></td><td><span class="pending-pill" :class="rateTone(course.pending_results, true)">{{ formatNumber(course.pending_results) }}</span></td><td v-if="canViewStudents"><button type="button" class="detail-button" @click="openCourseDetail(course)">Ver alumnas<i class="bx bx-right-arrow-alt"></i></button></td></tr></tbody></table></div>
            </section>

            <section class="analytics-grid subject-grid" :class="{ refreshing }">
              <article class="panel subject-chart"><header><div><span>VOLUMEN POR ASIGNATURA</span><h2>Cantidad de evaluaciones</h2><p>Permite comparar la carga evaluativa real sin depender de fechas.</p></div><i class="bx bx-bar-chart-alt-2"></i></header><apexchart v-if="dashboard.by_subject?.some((item) => item.assessments > 0)" :height="Math.max(285, Math.min(510, dashboard.by_subject.length * 42))" :options="subjectOptions" :series="subjectSeries" /><div v-else class="chart-empty"><i class="bx bx-book-open"></i>Sin evaluaciones</div></article>
              <article class="panel subject-list"><header><div><span>ASIGNATURAS</span><h2>Cobertura y pendientes</h2><p>La cantidad de evaluaciones puede variar entre asignaturas.</p></div><i class="bx bx-list-ul"></i></header><div class="subject-rows"><div v-for="subject in dashboard.by_subject" :key="subject.id" class="subject-row"><div><strong>{{ subject.name }}</strong><span>{{ formatNumber(subject.assessments) }} evaluaciones · {{ formatNumber(subject.completed_results) }} resultados</span></div><div><em>{{ formatPercent(subject.coverage_rate) }} cobertura</em><strong :class="{ alert: subject.pending_results > 0 }">{{ formatNumber(subject.pending_results) }} pendientes</strong></div></div></div></article>
            </section>

            <section v-if="canViewStudents && activeView !== 'overview'" id="student-explorer" class="student-panel panel">
              <header><div><span>{{ activeView === 'course' ? 'DETALLE NOMINAL DEL CURSO' : 'TRAYECTORIA ANUAL NOMINAL' }}</span><h2>{{ studentPanelTitle }}</h2><p>{{ studentPanelDescription }}</p></div><div class="privacy-badge"><i class="bx bx-lock-alt"></i><span>Acceso protegido<small>Información nominal</small></span></div></header>
              <div class="student-toolbar"><div class="student-search"><i class="bx bx-search"></i><input v-model="studentSearch" type="search" placeholder="Buscar alumna por nombre o RUT" @keyup.enter="loadStudents(1)" /><button type="button" @click="loadStudents(1)">Buscar</button></div><div class="student-context"><span v-if="activeView === 'students'"><i class="bx bx-info-circle"></i>El filtro de curso se omite para consolidar toda la trayectoria.</span><strong>{{ formatNumber(studentPagination.total) }} alumnas</strong></div></div>
              <div v-if="studentError" class="student-error"><i class="bx bx-error-circle"></i>{{ studentError }}<button type="button" @click="loadStudents(studentPagination.page)">Reintentar</button></div>
              <div v-if="studentLoading" class="student-loading"><span class="spinner-border spinner-border-sm"></span>Consolidando resultados individuales…</div>
              <div v-else-if="!studentRows.length" class="student-empty"><i class="bx bx-user-x"></i><strong>Sin alumnas en este contexto</strong><span>Prueba con otro curso, asignatura o término de búsqueda.</span></div>
              <div v-else class="table-responsive student-table-wrap"><table class="student-table"><thead><tr><th>Alumna</th><th>Curso(s)</th><th>Evaluaciones</th><th>Notas registradas</th><th>Promedio</th><th>Aprobación</th><th>Cobertura</th><th>Pendientes</th><th>Detalle</th></tr></thead><tbody><tr v-for="student in studentRows" :key="student.student_profile_id"><td><div class="student-identity"><span>{{ studentInitials(student.name) }}</span><strong>{{ student.name }}</strong></div></td><td><div class="course-chips"><span v-for="course in student.courses" :key="course.id || course.name">{{ course.name }}</span></div></td><td>{{ formatNumber(student.assessments) }}</td><td>{{ formatNumber(student.completed_results) }} <small>de {{ formatNumber(student.expected_results) }}</small></td><td><span class="grade-pill">{{ formatGrade(student.average_grade) }}</span></td><td><span class="rate-text" :class="rateTone(student.approval_rate)">{{ formatPercent(student.approval_rate) }}</span></td><td><div class="coverage-cell"><div><span :style="{ width: `${student.coverage_rate || 0}%` }" :class="rateTone(student.coverage_rate)"></span></div><strong>{{ formatPercent(student.coverage_rate) }}</strong></div></td><td><span class="pending-pill" :class="rateTone(student.pending_results, true)">{{ formatNumber(student.pending_results) }}</span></td><td><button type="button" class="student-view-button" :aria-label="`Ver detalle de ${student.name}`" @click="openStudentDetail(student)">Ver<i class="bx bx-right-arrow-alt"></i></button></td></tr></tbody></table></div>
              <footer v-if="studentPagination.last_page > 1" class="student-pagination"><button type="button" :disabled="studentPagination.page <= 1 || studentLoading" @click="loadStudents(studentPagination.page - 1)"><i class="bx bx-chevron-left"></i>Anterior</button><span>Página <strong>{{ studentPagination.page }}</strong> de {{ studentPagination.last_page }}</span><button type="button" :disabled="studentPagination.page >= studentPagination.last_page || studentLoading" @click="loadStudents(studentPagination.page + 1)">Siguiente<i class="bx bx-chevron-right"></i></button></footer>
            </section>

            <details class="methodology-card"><summary><span><i class="bx bx-info-circle"></i><strong>Cómo se calculan estas estadísticas</strong></span><i class="bx bx-chevron-down"></i></summary><div><p v-for="item in dashboard.meta.methodology" :key="item"><i class="bx bx-check"></i>{{ item }}</p></div></details>
          </template>
        </template>
      </div>
    </main>
    <Teleport to="body">
      <div v-if="studentDetailOpen" class="student-detail-overlay" role="presentation" @click.self="closeStudentDetail" @keydown.esc="closeStudentDetail">
        <aside class="student-detail-drawer" role="dialog" aria-modal="true" aria-labelledby="student-detail-title">
          <header class="student-detail-header">
            <div class="detail-student-avatar">{{ studentInitials(studentDetail?.student?.name) }}</div>
            <div>
              <span>EXPEDIENTE ACADÉMICO</span>
              <h2 id="student-detail-title">{{ studentDetail?.student?.name || "Detalle de alumna" }}</h2>
              <p v-if="studentDetail?.student?.courses?.length">{{ studentDetail.student.courses.map((course) => course.name).join(" · ") }} · {{ dashboard?.meta?.academic_year?.name }}</p>
              <p v-else>{{ dashboard?.meta?.academic_year?.name || "Año académico" }}</p>
            </div>
            <button type="button" class="student-detail-close" aria-label="Cerrar detalle" @click="closeStudentDetail"><i class="bx bx-x"></i></button>
          </header>

          <div v-if="studentDetailLoading" class="student-detail-state"><span class="spinner-border"></span><strong>Preparando expediente académico</strong><p>Consolidando asignaturas y evaluaciones de la alumna…</p></div>
          <div v-else-if="studentDetailError" class="student-detail-state error"><i class="bx bx-error-circle"></i><strong>No se pudo cargar el detalle</strong><p>{{ studentDetailError }}</p><button type="button" @click="closeStudentDetail">Cerrar</button></div>

          <div v-else-if="studentDetail" class="student-detail-content">
            <section class="student-detail-kpis" aria-label="Resumen de la alumna">
              <article><i class="bx bx-line-chart"></i><span>Promedio</span><strong>{{ formatGrade(studentDetail.summary.average_grade) }}</strong></article>
              <article><i class="bx bx-check-shield"></i><span>Aprobación</span><strong>{{ formatPercent(studentDetail.summary.approval_rate) }}</strong></article>
              <article><i class="bx bx-target-lock"></i><span>Cobertura</span><strong>{{ formatPercent(studentDetail.summary.coverage_rate) }}</strong></article>
              <article :class="{ alert: studentDetail.summary.pending_results > 0 }"><i class="bx bx-time-five"></i><span>Pendientes</span><strong>{{ formatNumber(studentDetail.summary.pending_results) }}</strong></article>
            </section>

            <section class="student-subject-summary">
              <div class="detail-section-title"><div><span>RESUMEN POR ASIGNATURA</span><h3>Desempeño y cobertura</h3></div><strong>{{ formatNumber(studentDetail.by_subject.length) }} asignaturas</strong></div>
              <div class="student-subject-cards">
                <button v-for="subject in studentDetail.by_subject" :key="subject.id" type="button" :class="{ active: Number(studentDetailSubject) === Number(subject.id) }" @click="studentDetailSubject = Number(studentDetailSubject) === Number(subject.id) ? 'all' : subject.id">
                  <span>{{ subject.name }}</span><strong>{{ formatGrade(subject.average_grade) }}</strong><small>{{ formatNumber(subject.completed_results) }} de {{ formatNumber(subject.expected_results) }} registradas</small><div><i :style="{ width: `${subject.coverage_rate || 0}%` }"></i></div>
                </button>
              </div>
            </section>

            <section class="student-evaluation-detail">
              <div class="detail-section-title">
                <div><span>DETALLE DE EVALUACIONES</span><h3>{{ studentDetailSubject === 'all' ? 'Todas las asignaturas' : studentDetail.by_subject.find((subject) => Number(subject.id) === Number(studentDetailSubject))?.name }}</h3></div>
                <button v-if="studentDetailSubject !== 'all'" type="button" @click="studentDetailSubject = 'all'"><i class="bx bx-x"></i>Ver todas</button>
              </div>
              <div class="evaluation-list">
                <article v-for="evaluation in filteredStudentEvaluations" :key="`${evaluation.assessment_id}-${evaluation.course?.id}`" class="evaluation-row">
                  <div class="evaluation-number"><span>E{{ evaluation.evaluation_number }}</span></div>
                  <div class="evaluation-description"><strong>{{ evaluation.subject.name }}</strong><span>{{ evaluation.name || evaluation.label }} · {{ evaluation.course.name }}</span><small>{{ resultSource(evaluation.source) }}</small></div>
                  <span class="evaluation-state" :class="resultTone(evaluation.state)">{{ evaluation.state_label }}</span>
                  <strong class="evaluation-value" :class="resultTone(evaluation.state)">{{ resultValue(evaluation) }}</strong>
                </article>
                <div v-if="!filteredStudentEvaluations.length" class="detail-empty"><i class="bx bx-spreadsheet"></i>Sin evaluaciones en esta asignatura.</div>
              </div>
            </section>
          </div>
        </aside>
      </div>
    </Teleport>
  </Layout>
</template>

<style scoped>
.grade-statistics-page{min-height:100vh;background:#f3f6f9;color:#263246}.grade-statistics-container{display:grid;gap:.9rem;padding-bottom:2.2rem}.grade-hero{position:relative;display:flex;align-items:center;justify-content:space-between;min-height:164px;overflow:hidden;border-radius:0 0 20px 20px;background:radial-gradient(circle at 85% 35%,rgba(86,201,183,.23),transparent 30%),linear-gradient(120deg,#202c46 0%,#394a7c 54%,#405189 100%);padding:1.45rem 1.6rem;color:#fff;box-shadow:0 14px 34px rgba(31,45,72,.18)}.grade-hero:before{position:absolute;right:11%;bottom:-88px;width:260px;height:260px;border:42px solid rgba(255,255,255,.035);border-radius:50%;content:""}.hero-copy{position:relative;z-index:1;max-width:760px}.eyebrow{color:#9be0d4;font-size:.59rem;font-weight:850;letter-spacing:.15em}.grade-hero h1{margin:.25rem 0 .3rem;color:#fff;font-size:1.72rem;letter-spacing:-.025em}.hero-copy>p{max-width:700px;margin:0;color:#d9e1f0;font-size:.75rem;line-height:1.55}.hero-meta{display:flex;flex-wrap:wrap;gap:.85rem;margin-top:.75rem;color:#cbd5e7;font-size:.62rem}.hero-meta span{display:flex;align-items:center;gap:.3rem}.hero-mark{position:relative;z-index:1;display:grid;place-items:center;width:108px;height:108px;border:1px solid rgba(255,255,255,.15);border-radius:30px;background:rgba(255,255,255,.08);box-shadow:inset 0 1px rgba(255,255,255,.12);backdrop-filter:blur(7px)}.hero-mark i{color:#9be0d4;font-size:2.5rem}.hero-mark span{font-size:.65rem;font-weight:850;letter-spacing:.1em}.filters-card{display:grid;grid-template-columns:1.2fr repeat(4,minmax(135px,1fr)) auto;align-items:end;gap:.6rem;border:1px solid #e1e6ed;border-radius:13px;background:#fff;padding:.8rem;box-shadow:0 5px 16px rgba(35,48,72,.04)}.filter-title{display:flex;align-items:center;gap:.5rem;align-self:center}.filter-title>i{display:grid;place-items:center;width:36px;height:36px;border-radius:9px;background:#eef0f8;color:#405189;font-size:1rem}.filter-title div{display:grid}.filter-title strong{color:#2a3547;font-size:.67rem}.filter-title span{color:#8290a3;font-size:.55rem}.filters-card label>span{display:block;margin:0 0 .24rem;color:#68758a;font-size:.55rem;font-weight:750}.filters-card select{width:100%;height:38px;border:1px solid #dce2e9;border-radius:8px;background:#fbfcfd;padding:0 .55rem;color:#3f4c60;font-size:.62rem;outline:none}.filters-card select:focus{border-color:#7887b7;box-shadow:0 0 0 3px rgba(64,81,137,.09)}.filter-actions{display:flex;gap:.35rem}.apply-button,.reset-button{display:flex;align-items:center;justify-content:center;gap:.3rem;height:38px;border-radius:8px;padding:0 .75rem;font-size:.61rem;font-weight:750;white-space:nowrap}.apply-button{border:1px solid #405189;background:#405189;color:#fff}.reset-button{border:1px solid #dce2e9;background:#fff;color:#68758a}.view-switcher{display:flex;gap:.45rem;border:1px solid #e1e6ed;border-radius:13px;background:#fff;padding:.45rem;box-shadow:0 4px 14px rgba(36,48,70,.03)}.view-switcher button{display:flex;align-items:center;gap:.55rem;min-width:190px;border:1px solid transparent;border-radius:9px;background:transparent;padding:.55rem .7rem;color:#68758a;text-align:left;transition:.18s}.view-switcher button>i{font-size:1.1rem}.view-switcher button span{display:grid}.view-switcher strong{font-size:.62rem}.view-switcher small{font-size:.5rem;opacity:.75}.view-switcher button.active{border-color:#d8ddef;background:#eef0f8;color:#405189}.state-card{display:grid;place-items:center;align-content:center;gap:.55rem;min-height:390px;border:1px solid #e1e6ed;border-radius:14px;background:#fff;color:#728096}.state-card p{margin:0;font-size:.62rem}.state-card.error>i{color:#c74754;font-size:2.1rem}.inline-error{display:flex;align-items:center;gap:.45rem;border:1px solid #efc7cb;border-left:4px solid #c74754;border-radius:9px;background:#fff3f4;padding:.65rem .75rem;color:#a93843;font-size:.66rem}.inline-error button{margin-left:auto;border:0;background:transparent;color:inherit;font-weight:750}.empty-dashboard{display:flex;align-items:center;justify-content:center;gap:1.2rem;min-height:360px;border:1px dashed #cfd7e1;border-radius:16px;background:#fff;padding:2rem;text-align:left}.empty-icon{display:grid;place-items:center;min-width:76px;height:76px;border-radius:22px;background:#eef0f8;color:#405189;font-size:2rem}.empty-dashboard span{color:#7b89a0;font-size:.58rem;font-weight:850;letter-spacing:.12em}.empty-dashboard h2{margin:.2rem 0;color:#263246;font-size:1.2rem}.empty-dashboard p{max-width:550px;margin:0;color:#738095;font-size:.69rem}.kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.7rem;transition:opacity .18s}.kpi-card{position:relative;display:flex;align-items:flex-start;gap:.65rem;min-height:118px;overflow:hidden;border:1px solid #e1e6ed;border-radius:13px;background:#fff;padding:.85rem;box-shadow:0 5px 15px rgba(36,48,70,.035)}.kpi-card:after{position:absolute;right:-24px;bottom:-30px;width:78px;height:78px;border:14px solid currentColor;border-radius:50%;opacity:.035;content:""}.kpi-icon{display:grid;place-items:center;min-width:38px;height:38px;border-radius:10px;background:currentColor}.kpi-icon i{color:#fff;font-size:1.05rem}.kpi-content{display:grid;min-width:0}.kpi-content>span{color:#6f7d91;font-size:.58rem;font-weight:750}.kpi-content>strong{margin:.1rem 0;color:#202b3c;font-size:1.55rem;line-height:1.2;letter-spacing:-.04em}.kpi-content small{color:#8490a1;font-size:.55rem;line-height:1.35}.tone-indigo{color:#405189}.tone-teal{color:#319589}.tone-blue{color:#3e7ca9}.tone-amber{color:#ce8a2d}.tone-slate{color:#66758a}.signal-strip{display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid #dce7e7;border-radius:13px;background:linear-gradient(100deg,#f4fbfa,#fff);padding:.7rem .85rem}.signal-main{display:flex;align-items:center;gap:.6rem}.signal-main>i{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#dff3ef;color:#278d80;font-size:1.08rem}.signal-main div{display:grid}.signal-main strong{color:#314158;font-size:.66rem}.signal-main span{color:#758496;font-size:.57rem}.source-stack{display:flex;gap:.45rem}.source-stack>div{display:flex;align-items:center;gap:.45rem;min-width:155px;border:1px solid #e2e9ea;border-radius:8px;background:#fff;padding:.45rem .55rem}.source-stack span{color:#778598;font-size:.55rem}.source-stack strong{margin-left:auto;color:#2d3b50;font-size:.72rem}.source-stack em{border-radius:999px;background:#e6f4f1;padding:.12rem .3rem;color:#2c897e;font-size:.5rem;font-style:normal;font-weight:800}.analytics-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(300px,.8fr);gap:.8rem;transition:opacity .18s}.panel{overflow:hidden;border:1px solid #e1e6ed;border-radius:13px;background:#fff;box-shadow:0 5px 16px rgba(36,48,70,.035)}.panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;border-bottom:1px solid #edf0f4;padding:.78rem .9rem}.panel>header span{color:#7b88a1;font-size:.53rem;font-weight:850;letter-spacing:.11em}.panel>header h2{margin:.1rem 0;color:#2a3547;font-size:.82rem}.panel>header p{margin:0;color:#8793a4;font-size:.56rem}.panel>header>i{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#eff1f8;color:#405189;font-size:1rem}.chart-scroll{overflow-x:auto;overflow-y:hidden}.chart-empty{display:flex;align-items:center;justify-content:center;gap:.4rem;min-height:250px;color:#8a96a7;font-size:.63rem}.chart-empty i{font-size:1.2rem}.distribution-legend{display:grid;grid-template-columns:1fr 1fr;gap:.35rem;padding:0 .85rem .8rem}.distribution-legend>div{display:flex;align-items:center;gap:.4rem;border:1px solid #edf0f4;border-radius:7px;padding:.35rem .42rem}.distribution-legend>div>span{width:7px;height:26px;border-radius:4px}.distribution-legend .insufficient{background:#d65c68}.distribution-legend .sufficient{background:#d69a37}.distribution-legend .good{background:#36a092}.distribution-legend .outstanding{background:#405189}.distribution-legend p{display:grid;margin:0}.distribution-legend p strong{color:#3c495d;font-size:.58rem}.distribution-legend p small{color:#8a96a6;font-size:.49rem}.distribution-legend em{margin-left:auto;color:#344157;font-size:.65rem;font-style:normal;font-weight:800}.course-panel>header{align-items:center}.course-header-actions{display:flex;align-items:center;gap:.45rem}.row-count{border-radius:999px;background:#eef0f8;padding:.23rem .45rem;color:#405189!important;letter-spacing:0!important}.nominal-button,.detail-button{display:inline-flex;align-items:center;justify-content:center;gap:.25rem;border:1px solid #d9deec;border-radius:7px;background:#fff;padding:.36rem .52rem;color:#405189;font-size:.55rem;font-weight:780}.nominal-button:hover,.detail-button:hover{border-color:#405189;background:#f2f4fa}.course-panel table,.student-table{width:100%;border-collapse:collapse;min-width:820px}.course-panel th,.student-table th{border-bottom:1px solid #e7ebf0;background:#f8fafc;padding:.58rem .75rem;color:#758196;font-size:.53rem;font-weight:800;text-align:left;text-transform:uppercase;letter-spacing:.045em}.course-panel td,.student-table td{border-bottom:1px solid #eef1f4;padding:.62rem .75rem;color:#536075;font-size:.62rem;vertical-align:middle}.course-panel tbody tr:last-child td,.student-table tbody tr:last-child td{border-bottom:0}.course-panel tbody tr:hover,.student-table tbody tr:hover{background:#fbfcfe}.course-panel td:first-child{display:grid}.course-panel td:first-child strong{color:#2f3b4e;font-size:.65rem}.course-panel td:first-child small{color:#8a96a7;font-size:.51rem}.grade-pill{display:inline-flex;min-width:38px;justify-content:center;border-radius:7px;background:#eef0f8;padding:.24rem .38rem;color:#405189;font-weight:800}.rate-text{font-weight:800}.rate-text.good{color:#278e81}.rate-text.watch{color:#b77a27}.rate-text.risk{color:#c44b57}.coverage-cell{display:flex;align-items:center;gap:.5rem;min-width:120px}.coverage-cell>div{width:72px;height:6px;overflow:hidden;border-radius:999px;background:#edf0f3}.coverage-cell>div span{display:block;height:100%;border-radius:inherit}.coverage-cell span.good{background:#319589}.coverage-cell span.watch{background:#cf9438}.coverage-cell span.risk{background:#cf5965}.coverage-cell strong{min-width:34px;color:#536075;font-size:.55rem}.pending-pill{display:inline-flex;min-width:34px;justify-content:center;border-radius:999px;padding:.2rem .38rem;font-weight:800}.pending-pill.good{background:#e3f3f0;color:#278e81}.pending-pill.watch{background:#fff3de;color:#ae7220}.pending-pill.risk{background:#fde8ea;color:#bb3e4a}.subject-grid{grid-template-columns:minmax(0,1.2fr) minmax(330px,.8fr)}.subject-rows{display:grid;max-height:470px;overflow:auto;padding:.35rem .75rem}.subject-row{display:flex;align-items:center;justify-content:space-between;gap:.7rem;border-bottom:1px solid #edf0f4;padding:.6rem .15rem}.subject-row:last-child{border-bottom:0}.subject-row>div{display:grid}.subject-row>div:first-child strong{color:#344156;font-size:.64rem}.subject-row>div:first-child span{color:#8995a6;font-size:.51rem}.subject-row>div:last-child{text-align:right}.subject-row em{color:#69768b;font-size:.52rem;font-style:normal}.subject-row>div:last-child strong{color:#2f8d82;font-size:.58rem}.subject-row>div:last-child strong.alert{color:#bd4954}.student-panel{scroll-margin-top:20px}.student-panel>header{align-items:center;background:linear-gradient(100deg,#fff,#f8fbfb)}.privacy-badge{display:flex;align-items:center;gap:.45rem;border:1px solid #d9e8e5;border-radius:9px;background:#eef8f6;padding:.45rem .55rem;color:#277f75}.privacy-badge>i{font-size:1rem}.privacy-badge span{display:grid;color:#277f75!important;font-size:.54rem!important;letter-spacing:0!important}.privacy-badge small{color:#6e8986;font-size:.47rem;font-weight:600}.student-toolbar{display:flex;align-items:center;justify-content:space-between;gap:.7rem;border-bottom:1px solid #edf0f4;padding:.65rem .8rem}.student-search{display:flex;align-items:center;width:min(460px,100%);height:38px;border:1px solid #dce2e9;border-radius:8px;background:#fbfcfd}.student-search>i{margin-left:.6rem;color:#8490a1}.student-search input{flex:1;min-width:0;border:0;background:transparent;padding:0 .5rem;color:#39465a;font-size:.6rem;outline:none}.student-search button{align-self:stretch;border:0;border-left:1px solid #dce2e9;background:#fff;padding:0 .65rem;color:#405189;font-size:.58rem;font-weight:800}.student-context{display:flex;align-items:center;gap:.7rem;color:#768397;font-size:.52rem}.student-context span{display:flex;align-items:center;gap:.25rem}.student-context strong{border-radius:999px;background:#eef0f8;padding:.25rem .45rem;color:#405189;font-size:.55rem}.student-loading,.student-empty{display:flex;align-items:center;justify-content:center;gap:.45rem;min-height:180px;color:#7b8899;font-size:.62rem}.student-empty{flex-direction:column}.student-empty i{color:#9aa5b4;font-size:1.5rem}.student-empty span{font-size:.53rem}.student-error{display:flex;align-items:center;gap:.4rem;border-bottom:1px solid #f0d0d3;background:#fff3f4;padding:.55rem .75rem;color:#b23e49;font-size:.58rem}.student-error button{margin-left:auto;border:0;background:transparent;color:inherit;font-weight:800}.student-identity{display:flex;align-items:center;gap:.45rem;min-width:190px}.student-identity>span{display:grid;place-items:center;min-width:30px;height:30px;border-radius:9px;background:linear-gradient(145deg,#e6e9f5,#f4f6fb);color:#405189;font-size:.52rem;font-weight:850}.student-identity strong{color:#344156;font-size:.61rem}.course-chips{display:flex;flex-wrap:wrap;gap:.25rem}.course-chips span{border-radius:999px;background:#edf6f4;padding:.18rem .34rem;color:#2e8379;font-size:.48rem;font-weight:750}.student-table td small{color:#8a96a7;font-size:.49rem}.student-pagination{display:flex;align-items:center;justify-content:flex-end;gap:.6rem;border-top:1px solid #edf0f4;padding:.55rem .75rem;color:#788599;font-size:.55rem}.student-pagination button{display:flex;align-items:center;gap:.2rem;border:1px solid #dce2e9;border-radius:7px;background:#fff;padding:.32rem .45rem;color:#405189;font-size:.53rem;font-weight:750}.student-pagination button:disabled{cursor:not-allowed;opacity:.45}.methodology-card{border:1px solid #dfe5ec;border-radius:11px;background:#fff}.methodology-card summary{display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:.7rem .85rem;list-style:none;color:#46536a;font-size:.63rem}.methodology-card summary span{display:flex;align-items:center;gap:.4rem}.methodology-card summary span i{color:#405189;font-size:.95rem}.methodology-card summary::-webkit-details-marker{display:none}.methodology-card[open] summary>.bx{transform:rotate(180deg)}.methodology-card>div{display:grid;grid-template-columns:1fr 1fr;gap:.45rem;border-top:1px solid #edf0f4;padding:.7rem .85rem}.methodology-card p{display:flex;align-items:flex-start;gap:.35rem;margin:0;color:#748195;font-size:.56rem;line-height:1.45}.methodology-card p i{margin-top:.05rem;color:#319589}.refreshing{opacity:.72;pointer-events:none}
.student-view-button{display:inline-flex;align-items:center;justify-content:center;gap:.25rem;border:1px solid #405189;border-radius:7px;background:#405189;padding:.34rem .55rem;color:#fff;font-size:.54rem;font-weight:800;box-shadow:0 4px 10px rgba(64,81,137,.15);transition:.16s}.student-view-button:hover{background:#344477;transform:translateY(-1px)}
.student-detail-overlay{position:fixed;inset:0;z-index:1090;display:flex;justify-content:flex-end;background:rgba(20,29,46,.56);backdrop-filter:blur(3px)}.student-detail-drawer{display:flex;flex-direction:column;width:min(760px,calc(100vw - 24px));height:100vh;overflow:hidden;background:#f4f6f9;box-shadow:-18px 0 55px rgba(22,31,49,.24);animation:student-detail-enter .22s ease-out}.student-detail-header{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.8rem;min-height:132px;background:radial-gradient(circle at 83% 12%,rgba(126,213,198,.18),transparent 32%),linear-gradient(120deg,#202c46,#405189);padding:1.15rem 1.25rem;color:#fff}.detail-student-avatar{display:grid;place-items:center;width:58px;height:58px;border:1px solid rgba(255,255,255,.18);border-radius:18px;background:rgba(255,255,255,.1);color:#a7e3d8;font-size:1rem;font-weight:900}.student-detail-header>div:nth-child(2){display:grid}.student-detail-header span{color:#a7e3d8;font-size:.52rem;font-weight:850;letter-spacing:.13em}.student-detail-header h2{margin:.12rem 0;color:#fff;font-size:1.12rem;letter-spacing:-.02em}.student-detail-header p{margin:0;color:#d6deed;font-size:.57rem}.student-detail-close{display:grid;place-items:center;width:34px;height:34px;border:1px solid rgba(255,255,255,.18);border-radius:9px;background:rgba(255,255,255,.08);color:#fff;font-size:1.2rem}.student-detail-close:hover{background:rgba(255,255,255,.16)}.student-detail-content{display:grid;gap:.8rem;overflow-y:auto;padding:1rem}.student-detail-state{display:grid;place-items:center;align-content:center;gap:.5rem;height:100%;padding:2rem;color:#66758a;text-align:center}.student-detail-state p{max-width:410px;margin:0;font-size:.62rem}.student-detail-state.error>i{color:#c74754;font-size:2.2rem}.student-detail-state.error button{border:1px solid #dce2e9;border-radius:8px;background:#fff;padding:.4rem .7rem;color:#405189;font-size:.6rem;font-weight:800}.student-detail-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:.55rem}.student-detail-kpis article{position:relative;display:grid;min-height:92px;overflow:hidden;border:1px solid #e0e5ec;border-radius:11px;background:#fff;padding:.7rem;box-shadow:0 4px 13px rgba(31,43,65,.035)}.student-detail-kpis article>i{position:absolute;right:.6rem;top:.55rem;color:#8793b5;font-size:1rem}.student-detail-kpis article>span{align-self:end;color:#7b8798;font-size:.51rem;font-weight:750}.student-detail-kpis article>strong{color:#2c3850;font-size:1.13rem}.student-detail-kpis article.alert{border-color:#f0d7bd;background:#fffaf2}.student-detail-kpis article.alert>i,.student-detail-kpis article.alert>strong{color:#bd7722}.student-subject-summary,.student-evaluation-detail{border:1px solid #e0e5ec;border-radius:12px;background:#fff;box-shadow:0 4px 13px rgba(31,43,65,.03)}.detail-section-title{display:flex;align-items:center;justify-content:space-between;gap:.7rem;border-bottom:1px solid #edf0f4;padding:.75rem .85rem}.detail-section-title>div{display:grid}.detail-section-title span{color:#7e8aa0;font-size:.48rem;font-weight:850;letter-spacing:.1em}.detail-section-title h3{margin:.08rem 0 0;color:#2d394d;font-size:.72rem}.detail-section-title>strong{border-radius:999px;background:#eef0f8;padding:.22rem .43rem;color:#405189;font-size:.51rem}.detail-section-title>button{display:flex;align-items:center;gap:.2rem;border:1px solid #dce2e9;border-radius:7px;background:#fff;padding:.3rem .42rem;color:#405189;font-size:.5rem;font-weight:800}.student-subject-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem;padding:.65rem}.student-subject-cards>button{display:grid;grid-template-columns:1fr auto;gap:.12rem .5rem;border:1px solid #e5e9ee;border-radius:9px;background:#fbfcfd;padding:.58rem .65rem;color:#3b475b;text-align:left;transition:.16s}.student-subject-cards>button:hover,.student-subject-cards>button.active{border-color:#8593ba;background:#f4f6fb}.student-subject-cards>button>span{overflow:hidden;color:#3a4659;font-size:.57rem;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.student-subject-cards>button>strong{grid-row:1/3;grid-column:2;color:#405189;font-size:.8rem}.student-subject-cards>button>small{color:#8591a2;font-size:.47rem}.student-subject-cards>button>div{grid-column:1/-1;height:4px;overflow:hidden;border-radius:99px;background:#ebeff2}.student-subject-cards>button>div i{display:block;height:100%;border-radius:inherit;background:#36a092}.evaluation-list{display:grid;padding:0 .7rem .7rem}.evaluation-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto 74px;align-items:center;gap:.55rem;border-bottom:1px solid #edf0f4;padding:.65rem .1rem}.evaluation-row:last-child{border-bottom:0}.evaluation-number{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#eef0f8;color:#405189}.evaluation-number span{font-size:.51rem;font-weight:900}.evaluation-description{display:grid;min-width:0}.evaluation-description strong{overflow:hidden;color:#344156;font-size:.57rem;text-overflow:ellipsis;white-space:nowrap}.evaluation-description span{overflow:hidden;color:#748196;font-size:.5rem;text-overflow:ellipsis;white-space:nowrap}.evaluation-description small{color:#9aa4b1;font-size:.44rem}.evaluation-state{border-radius:999px;padding:.2rem .4rem;font-size:.47rem;font-weight:800}.evaluation-state.recorded{background:#e3f3f0;color:#278e81}.evaluation-state.pending{background:#fff1dd;color:#ad6f1e}.evaluation-state.neutral{background:#edf0f4;color:#69778a}.evaluation-value{justify-self:end;color:#3b4860;font-size:.7rem;text-align:right}.evaluation-value.recorded{color:#405189}.evaluation-value.pending{color:#ad6f1e;font-size:.54rem}.evaluation-value.neutral{color:#69778a;font-size:.54rem}.detail-empty{display:flex;align-items:center;justify-content:center;gap:.35rem;min-height:100px;color:#8491a2;font-size:.55rem}.detail-empty i{font-size:1rem}@keyframes student-detail-enter{from{opacity:.75;transform:translateX(44px)}to{opacity:1;transform:translateX(0)}}
@media(max-width:1250px){.filters-card{grid-template-columns:repeat(4,minmax(0,1fr))}.filter-title{grid-column:1/-1}.filter-actions{grid-column:4}.kpi-grid{grid-template-columns:repeat(3,1fr)}.analytics-grid,.subject-grid{grid-template-columns:1fr}.panel-distribution{display:grid;grid-template-columns:1fr 1fr}.panel-distribution>header{grid-column:1/-1}.distribution-legend{align-content:center;padding:.8rem}}
@media(max-width:800px){.grade-statistics-container{padding-inline:.65rem}.grade-hero{min-height:unset;margin-inline:-.65rem;border-radius:0 0 14px 14px;padding:1.1rem}.grade-hero h1{font-size:1.35rem}.hero-mark{display:none}.filters-card{grid-template-columns:1fr 1fr}.filter-title{grid-column:1/-1}.filter-actions{grid-column:1/-1}.filter-actions button{flex:1}.view-switcher{overflow-x:auto}.view-switcher button{min-width:175px}.kpi-grid{grid-template-columns:1fr 1fr}.kpi-card:last-child{grid-column:1/-1}.signal-strip,.student-toolbar{align-items:flex-start;flex-direction:column}.source-stack,.student-search{width:100%}.source-stack>div{flex:1;min-width:0}.panel-distribution{display:block}.course-panel>header{align-items:flex-start;flex-direction:column}.course-header-actions{width:100%;justify-content:space-between}.student-context{align-items:flex-start;flex-direction:column}.methodology-card>div{grid-template-columns:1fr}}
@media(max-width:480px){.hero-meta span:last-child{display:none}.filters-card{grid-template-columns:1fr}.filters-card label,.filter-actions{grid-column:1}.kpi-grid{grid-template-columns:1fr}.kpi-card:last-child{grid-column:1}.source-stack{flex-direction:column}.source-stack>div{width:100%}.distribution-legend{grid-template-columns:1fr}.empty-dashboard{align-items:flex-start;flex-direction:column;padding:1.2rem}.nominal-button{font-size:.5rem}.privacy-badge{display:none}.student-pagination{justify-content:space-between}.student-pagination span{font-size:.5rem}}
@media(max-width:700px){.student-detail-drawer{width:100vw}.student-detail-header{min-height:112px;padding:.9rem}.detail-student-avatar{width:46px;height:46px;border-radius:14px}.student-detail-kpis{grid-template-columns:1fr 1fr}.student-subject-cards{grid-template-columns:1fr}.evaluation-row{grid-template-columns:auto minmax(0,1fr) auto}.evaluation-state{grid-row:2;grid-column:2;justify-self:start}.evaluation-value{grid-row:1/3;grid-column:3;max-width:72px}.student-detail-content{padding:.65rem}.student-detail-header h2{font-size:.9rem}}
</style>
