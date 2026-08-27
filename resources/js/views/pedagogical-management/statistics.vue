<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
    errorMessage,
    pedagogicalManagementApi,
} from "../../services/pedagogical-management-api";
import { downloadPedagogicalStatisticsPdf } from "../../utils/pedagogical-statistics-pdf";

const loading = ref(true);
const refreshing = ref(false);
const error = ref("");
const dashboard = ref(null);
const schoolId = ref(null);
const activeTab = ref("overview");
const selectedDimension = ref("");
const detail = ref(null);
const detailLoading = ref(false);
const showDetail = ref(false);
const exportingPdf = ref(false);

const filters = reactive({
    academic_year_id: "",
    owner_user_id: "",
    subject_id: "",
    course_id: "",
    education_level_id: "",
    decision: "",
    prompt_version: "",
    from_date: "",
    to_date: "",
});

const summary = computed(() => dashboard.value?.summary || {});
const meta = computed(() => dashboard.value?.meta || {});
const options = computed(() => dashboard.value?.filter_options || {});
const activeFilters = computed(() => Object.entries(filters).filter(([, value]) => value !== "" && value !== null).length);
const filtersDirty = computed(() => Object.keys(filters).some((key) => String(filters[key] ?? "") !== String(dashboard.value?.filters?.[key] ?? "")));
const dimensions = computed(() => [...new Set((dashboard.value?.criteria || []).map((item) => item.dimension))]);
const visibleCriteria = computed(() => {
    const rows = dashboard.value?.criteria || [];
    return (selectedDimension.value ? rows.filter((item) => item.dimension === selectedDimension.value) : rows)
        .slice()
        .sort((a, b) => Number(b.opportunity_index || 0) - Number(a.opportunity_index || 0));
});
const appliedFilterLabels = computed(() => {
    const applied = dashboard.value?.filters || {};
    const labels = [];
    const selectedLabel = (collection, id, formatter = (item) => item.name) => collection?.find((item) => String(item.id) === String(id)) && formatter(collection.find((item) => String(item.id) === String(id)));
    const year = selectedLabel(options.value.academic_years, applied.academic_year_id, (item) => item.year || item.name);
    const owner = selectedLabel(options.value.owners, applied.owner_user_id);
    const subject = selectedLabel(options.value.subjects, applied.subject_id);
    const course = selectedLabel(options.value.courses, applied.course_id);
    const level = selectedLabel(options.value.levels, applied.education_level_id);
    if (year) labels.push(`Año ${year}`);
    if (owner) labels.push(`Docente: ${owner}`);
    if (subject) labels.push(`Asignatura: ${subject}`);
    if (course) labels.push(`Curso: ${course}`);
    if (level) labels.push(`Nivel: ${level}`);
    if (applied.decision) labels.push(`Resolución: ${decisionLabel(applied.decision)}`);
    if (applied.from_date) labels.push(`Desde: ${formatDate(applied.from_date)}`);
    if (applied.to_date) labels.push(`Hasta: ${formatDate(applied.to_date)}`);
    return labels;
});
const executiveSignals = computed(() => {
    const priority = dashboard.value?.priorities?.[0];
    return [
        { label: "Lectura actual", value: formatPercent(summary.value.current_compliance_percentage), note: "Ajuste mediano vigente", icon: "bx-pulse", tone: "teal" },
        { label: "Foco principal", value: priority?.code || "—", note: priority?.dimension || "Sin prioridad cuantificable", icon: "bx-bullseye", tone: "amber" },
        { label: "Evidencia", value: formatPercent(summary.value.evidence_coverage_percentage), note: "Criterios con respaldo verificable", icon: "bx-search-alt", tone: "navy" },
        { label: "Confianza", value: meta.value.sample_sufficient ? "Suficiente" : "Exploratoria", note: `${formatCount(meta.value.report_count, "informe")} · ${formatCount(meta.value.comparable_pairs, "par", "pares")}`, icon: "bx-shield-quarter", tone: meta.value.sample_sufficient ? "green" : "violet" },
    ];
});

const kpis = computed(() => [
    { label: "Informes oficiales", value: formatNumber(summary.value.official_reports), note: `${formatCount(summary.value.instruments, "instrumento")} · ${formatCount(summary.value.teachers, "docente")}`, icon: "bx-file-find", tone: "navy" },
    { label: "Aprobación inicial", value: formatPercent(summary.value.first_pass_approval_rate), note: "Aprobados en su primera versión", icon: "bx-check-shield", tone: "teal" },
    { label: "Ajuste actual", value: formatPercent(summary.value.current_compliance_percentage), note: "Mediana de la última versión revisada", icon: "bx-target-lock", tone: "blue" },
    { label: "Mejora entre versiones", value: formatDelta(summary.value.median_improvement_pp), note: `${formatCount(summary.value.comparable_instruments, "instrumento comparable", "instrumentos comparables")}`, icon: "bx-trending-up", tone: Number(summary.value.median_improvement_pp || 0) < 0 ? "rose" : "violet" },
    { label: "Tiempo de rectificación", value: formatDays(summary.value.median_rectification_days), note: "Mediana desde observación a reenvío", icon: "bx-time-five", tone: "amber" },
    { label: "Rectificaciones cerradas", value: formatPercent(summary.value.rectification_closure_rate), note: `${formatPercent(summary.value.rectification_rate)} de los informes requirió rectificación`, icon: "bx-revision", tone: "green" },
]);

const trendOptions = computed(() => ({
    chart: { type: "line", fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false }, zoom: { enabled: false } },
    colors: ["#16557a", "#129487", "#c8d7e3"],
    stroke: { curve: "smooth", width: [3.2, 2.5, 0] },
    fill: { type: "solid", opacity: [1, 1, .9] },
    markers: { size: [4, 3, 0], strokeWidth: 0, hover: { size: 6 } },
    plotOptions: { bar: { columnWidth: "38%", borderRadius: 4 } },
    xaxis: { categories: (dashboard.value?.trend || []).map((item) => item.label), labels: { style: { colors: "#5f7185", fontSize: "11px", fontWeight: 600 } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: [
        { min: 0, max: 100, tickAmount: 4, labels: { formatter: (value) => `${Math.round(value)}%`, style: { colors: "#5f7185", fontSize: "10px", fontWeight: 600 } }, title: { text: "Ajuste y evidencia", style: { color: "#52667b", fontSize: "10px", fontWeight: 700 } } },
        { min: 0, max: 100, show: false },
        { opposite: true, min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value), style: { colors: "#5f7185", fontSize: "10px", fontWeight: 600 } }, title: { text: "Informes", style: { color: "#52667b", fontSize: "10px", fontWeight: 700 } } },
    ],
    grid: { borderColor: "#dfe8ed", strokeDashArray: 4, padding: { left: 8, right: 8 } },
    legend: { position: "top", horizontalAlign: "right", fontSize: "11px", markers: { size: 5 } },
    tooltip: { shared: true, y: [{ formatter: (value) => formatPercent(value) }, { formatter: (value) => formatPercent(value) }, { formatter: (value) => `${formatNumber(value)} informes` }] },
    dataLabels: { enabled: false },
    noData: { text: "Aún no hay una serie temporal suficiente" },
}));
const trendSeries = computed(() => [
    { name: "Ajuste mediano", type: "line", data: (dashboard.value?.trend || []).map((item) => item.median_compliance) },
    { name: "Cobertura de evidencia", type: "line", data: (dashboard.value?.trend || []).map((item) => item.evidence_coverage) },
    { name: "Informes", type: "column", data: (dashboard.value?.trend || []).map((item) => item.reports) },
]);

const dimensionOptions = computed(() => ({
    chart: { type: "bar", fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false } },
    colors: ["#a9bccb", "#178b82"],
    plotOptions: { bar: { horizontal: true, barHeight: "62%", borderRadius: 5 } },
    xaxis: { categories: (dashboard.value?.dimensions || []).map((item) => item.dimension), min: 0, max: 100, tickAmount: 4, labels: { formatter: (value) => `${Math.round(value)}%`, style: { colors: "#7b899a", fontSize: "10px" } } },
    yaxis: { labels: { maxWidth: 205, style: { colors: "#445166", fontSize: "10px", fontWeight: 600 } } },
    grid: { borderColor: "#edf1f4", strokeDashArray: 4 },
    legend: { position: "top", horizontalAlign: "right", fontSize: "11px" },
    tooltip: { y: { formatter: (value) => formatPercent(value) } },
    dataLabels: { enabled: false },
}));
const dimensionSeries = computed(() => [
    { name: "Primera versión", data: (dashboard.value?.dimensions || []).map((item) => item.first_score) },
    { name: "Última versión", data: (dashboard.value?.dimensions || []).map((item) => item.latest_score) },
]);

const decisionOptions = computed(() => ({
    chart: { type: "donut", fontFamily: "Inter, system-ui, sans-serif" },
    labels: (dashboard.value?.decisions || []).map((item) => item.label),
    colors: ["#208b70", "#6c70b8", "#df873f"],
    stroke: { width: 4, colors: ["#ffffff"] },
    dataLabels: { enabled: false },
    legend: { position: "bottom", fontSize: "11px", markers: { size: 5 } },
    plotOptions: { pie: { donut: { size: "72%", labels: { show: true, total: { show: true, label: "Resoluciones", color: "#7c8999", formatter: () => formatNumber(summary.value.official_reports) }, value: { color: "#203149", fontSize: "22px", fontWeight: 750 } } } } },
    tooltip: { y: { formatter: (value) => `${formatNumber(value)} informes` } },
}));
const decisionSeries = computed(() => (dashboard.value?.decisions || []).map((item) => item.count));

const miscellaneousOptions = computed(() => ({
    chart: { type: "bar", fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false } },
    colors: ["#cf5664", "#d78a36", "#647a9b"],
    plotOptions: { bar: { horizontal: false, borderRadius: 5, columnWidth: "48%" } },
    xaxis: { categories: (dashboard.value?.miscellaneous || []).map((item) => item.label), labels: { rotate: -18, trim: true, style: { colors: "#788698", fontSize: "10px" } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => Math.round(value), style: { colors: "#788698", fontSize: "10px" } } },
    grid: { borderColor: "#edf1f4", strokeDashArray: 4 },
    legend: { position: "top", horizontalAlign: "right", fontSize: "11px" },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (value) => `${formatNumber(value)} hallazgos` } },
}));
const miscellaneousSeries = computed(() => [
    { name: "Críticos", data: (dashboard.value?.miscellaneous || []).map((item) => item.critical) },
    { name: "Importantes", data: (dashboard.value?.miscellaneous || []).map((item) => item.important) },
    { name: "Sugerencias", data: (dashboard.value?.miscellaneous || []).map((item) => item.suggestion) },
]);

function requestParams() {
    const params = { school_id: schoolId.value };
    Object.entries(filters).forEach(([key, value]) => {
        if (value !== "" && value !== null && value !== undefined) params[key] = value;
    });
    return params;
}

async function initialize() {
    loading.value = true;
    error.value = "";
    try {
        const catalogs = await pedagogicalManagementApi.catalogs();
        schoolId.value = catalogs.selected_school_id;
        filters.academic_year_id = catalogs.academic_year?.id || "";
        await loadStatistics();
    } catch (requestError) {
        error.value = errorMessage(requestError, "No fue posible preparar las estadísticas pedagógicas.");
    } finally {
        loading.value = false;
    }
}

async function loadStatistics() {
    if (!schoolId.value) return false;
    refreshing.value = Boolean(dashboard.value);
    error.value = "";
    try {
        dashboard.value = await pedagogicalManagementApi.statistics(requestParams());
        return true;
    } catch (requestError) {
        error.value = errorMessage(requestError, "No fue posible calcular las estadísticas pedagógicas.");
        return false;
    } finally {
        refreshing.value = false;
    }
}

async function exportPdf() {
    if (!dashboard.value || exportingPdf.value) return;
    exportingPdf.value = true;
    error.value = "";
    try {
        if (filtersDirty.value && !(await loadStatistics())) return;
        await downloadPedagogicalStatisticsPdf(dashboard.value, {
            filter_labels: appliedFilterLabels.value,
            generated_at: new Date(),
        });
    } catch (requestError) {
        error.value = errorMessage(requestError, "No fue posible generar el informe PDF.");
    } finally {
        exportingPdf.value = false;
    }
}

function resetFilters() {
    const activeYear = options.value.academic_years?.find((year) => year.is_active)?.id || "";
    Object.assign(filters, {
        academic_year_id: activeYear,
        owner_user_id: "",
        subject_id: "",
        course_id: "",
        education_level_id: "",
        decision: "",
        prompt_version: "",
        from_date: "",
        to_date: "",
    });
    loadStatistics();
}

function focusTeacher(teacher) {
    filters.owner_user_id = teacher.id;
    activeTab.value = "overview";
    loadStatistics();
}

async function openInstrument(instrument) {
    showDetail.value = true;
    detailLoading.value = true;
    detail.value = null;
    try {
        detail.value = await pedagogicalManagementApi.instrumentStatistics(instrument.id);
    } catch (requestError) {
        error.value = errorMessage(requestError, "No fue posible abrir la trayectoria del instrumento.");
        showDetail.value = false;
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    showDetail.value = false;
    detail.value = null;
}

function handleKeydown(event) {
    if (event.key === "Escape" && showDetail.value) closeDetail();
}

function statusWidth(criterion, key) {
    const denominator = Number(criterion.applicable || 0);
    return denominator > 0 ? `${Math.min(100, (Number(criterion[key] || 0) / denominator) * 100)}%` : "0%";
}

function formatNumber(value) {
    return new Intl.NumberFormat("es-CL").format(Number(value || 0));
}

function formatCount(value, singular, plural = `${singular}s`) {
    const count = Number(value || 0);
    return `${formatNumber(count)} ${count === 1 ? singular : plural}`;
}

function formatPercent(value) {
    return value === null || value === undefined ? "—" : `${Number(value).toLocaleString("es-CL", { maximumFractionDigits: 1 })}%`;
}

function formatDelta(value) {
    if (value === null || value === undefined) return "—";
    const numeric = Number(value);
    return `${numeric > 0 ? "+" : ""}${numeric.toLocaleString("es-CL", { maximumFractionDigits: 1 })} pp`;
}

function formatDays(value) {
    return value === null || value === undefined ? "—" : `${Number(value).toLocaleString("es-CL", { maximumFractionDigits: 1 })} días`;
}

function formatDate(value) {
    if (!value) return "—";
    const source = String(value);
    const parsed = new Date(source.length === 10 ? `${source}T12:00:00` : source);
    return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium" }).format(parsed);
}

function decisionLabel(value) {
    return ({ approved: "Aprobado", approved_with_observations: "Aprobado con observaciones", rectification_requested: "Rectificación solicitada" })[value] || value;
}

function criterionStatusLabel(value) {
    return ({ meets: "Cumple", partially_meets: "Cumple parcialmente", does_not_meet: "No cumple", not_evidenced: "No evidenciado", not_applicable: "No aplica" })[value] || value;
}

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
    initialize();
});
onBeforeUnmount(() => window.removeEventListener("keydown", handleKeydown));
</script>

<template>
    <Layout>
        <main class="statistics-page container-fluid py-4">
            <header class="statistics-hero">
                <div>
                    <span class="eyebrow">INTELIGENCIA PEDAGÓGICA</span>
                    <h1>Evolución documental</h1>
                    <p>Convierte la pauta de revisión en señales medibles para acompañar la mejora de cada instrumento.</p>
                    <div class="hero-meta"><span><i class="bx bx-building-house"></i>{{ meta.school?.name || "Establecimiento actual" }}</span><span><i class="bx bx-shield-quarter"></i>Datos según tu alcance autorizado</span></div>
                </div>
                <div class="hero-side">
                    <div class="hero-score"><span>INFORMES</span><strong>{{ formatNumber(summary.official_reports) }}</strong><small>oficiales revisados</small></div>
                    <button type="button" class="hero-export" data-testid="statistics-pdf-export" :disabled="loading || !dashboard || exportingPdf" @click="exportPdf">
                        <span v-if="exportingPdf" class="spinner-border spinner-border-sm"></span><i v-else class="bx bxs-file-pdf"></i>
                        <span><strong>{{ exportingPdf ? 'Preparando informe' : 'Exportar informe PDF' }}</strong><small>Incluye filtros, pauta y trayectorias</small></span>
                    </button>
                </div>
            </header>

            <div v-if="error" class="alert alert-danger statistics-alert"><i class="bx bx-error-circle"></i><span>{{ error }}</span><button type="button" @click="loadStatistics">Reintentar</button></div>
            <LoadingState v-if="loading" message="Construyendo indicadores y trayectorias..." />

            <template v-else-if="dashboard">
                <section class="filter-panel" :class="{ refreshing }">
                    <div class="filter-heading"><div><span>ALCANCE DEL ANÁLISIS</span><h2>Filtros de seguimiento</h2></div><span class="filter-count" :class="{ dirty: filtersDirty }">{{ filtersDirty ? 'Cambios sin aplicar' : `${activeFilters} ${activeFilters === 1 ? 'activo' : 'activos'}` }}</span></div>
                    <label><span>Año académico</span><select v-model="filters.academic_year_id" class="form-select"><option value="">Todos</option><option v-for="year in options.academic_years" :key="year.id" :value="year.id">{{ year.year || year.name }}</option></select></label>
                    <label><span>Docente</span><select v-model="filters.owner_user_id" class="form-select"><option value="">Todos los docentes</option><option v-for="owner in options.owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option></select></label>
                    <label><span>Asignatura</span><select v-model="filters.subject_id" class="form-select"><option value="">Todas las asignaturas</option><option v-for="subject in options.subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option></select></label>
                    <label><span>Curso</span><select v-model="filters.course_id" class="form-select"><option value="">Todos los cursos</option><option v-for="course in options.courses" :key="course.id" :value="course.id">{{ course.name }}</option></select></label>
                    <label><span>Nivel</span><select v-model="filters.education_level_id" class="form-select"><option value="">Todos los niveles</option><option v-for="level in options.levels" :key="level.id" :value="level.id">{{ level.name }}</option></select></label>
                    <label><span>Resolución</span><select v-model="filters.decision" class="form-select"><option value="">Todas</option><option value="approved">Aprobado</option><option value="approved_with_observations">Aprobado con observaciones</option><option value="rectification_requested">Rectificación solicitada</option></select></label>
                    <label><span>Desde</span><input v-model="filters.from_date" class="form-control" type="date" /></label>
                    <label><span>Hasta</span><input v-model="filters.to_date" class="form-control" type="date" /></label>
                    <div class="filter-actions"><button type="button" class="btn btn-light" @click="resetFilters"><i class="bx bx-reset"></i>Limpiar</button><button type="button" class="btn btn-primary" :disabled="refreshing" @click="loadStatistics"><span v-if="refreshing" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-filter-alt"></i>Aplicar</button></div>
                </section>

                <section class="applied-scope" aria-label="Alcance aplicado al tablero y al informe PDF">
                    <span class="scope-icon"><i class="bx bx-filter-alt"></i></span>
                    <div><small>ALCANCE APLICADO AL TABLERO Y AL PDF</small><div><span v-for="label in appliedFilterLabels" :key="label">{{ label }}</span><span v-if="!appliedFilterLabels.length">Todos los registros autorizados</span></div></div>
                    <time :datetime="meta.generated_at"><i class="bx bx-time-five"></i>{{ meta.generated_at ? formatDate(meta.generated_at) : 'Actualizado ahora' }}</time>
                </section>

                <nav class="statistics-tabs" aria-label="Secciones estadísticas">
                    <button type="button" :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'"><i class="bx bx-grid-alt"></i><span>Panorama<small>Indicadores y tendencia</small></span></button>
                    <button type="button" :class="{ active: activeTab === 'criteria' }" @click="activeTab = 'criteria'"><i class="bx bx-list-check"></i><span>Pauta y prioridades<small>19 criterios cuantificados</small></span></button>
                    <button type="button" :class="{ active: activeTab === 'trajectories' }" @click="activeTab = 'trajectories'"><i class="bx bx-git-branch"></i><span>Trayectorias<small>Docentes e instrumentos</small></span></button>
                </nav>

                <section class="kpi-grid" :class="{ refreshing }">
                    <article v-for="kpi in kpis" :key="kpi.label" class="kpi-card" :class="`tone-${kpi.tone}`"><span class="kpi-icon"><i class="bx" :class="kpi.icon"></i></span><div><small>{{ kpi.label }}</small><strong>{{ kpi.value }}</strong><p>{{ kpi.note }}</p></div></article>
                </section>

                <section class="executive-brief" aria-label="Lectura ejecutiva del período">
                    <header><span>LECTURA EJECUTIVA</span><strong>Señales clave para decidir el acompañamiento</strong></header>
                    <article v-for="signal in executiveSignals" :key="signal.label" :class="`signal-${signal.tone}`"><i class="bx" :class="signal.icon"></i><div><small>{{ signal.label }}</small><strong>{{ signal.value }}</strong><p>{{ signal.note }}</p></div></article>
                </section>

                <aside v-if="!meta.sample_sufficient" class="sample-notice"><span><i class="bx bx-info-circle"></i></span><div><strong>Historial todavía insuficiente para conclusiones firmes</strong><p>Hay {{ formatCount(meta.report_count, 'informe') }} y {{ formatCount(meta.comparable_pairs, 'comparación', 'comparaciones') }}. Los rankings requieren al menos {{ formatCount(meta.minimum_reports, 'informe') }} y {{ formatCount(meta.minimum_comparable_pairs, 'par', 'pares') }} entre versiones; mientras tanto se muestran como señales exploratorias.</p></div></aside>

                <template v-if="activeTab === 'overview'">
                    <div class="overview-grid">
                        <section class="analytics-panel trend-panel"><header><div><span>EVOLUCIÓN TEMPORAL</span><h2>Ajuste, evidencia y volumen</h2><p>La mediana reduce el efecto de valores extremos y cada punto conserva su número de informes.</p></div><i class="bx bx-line-chart"></i></header><div v-if="dashboard.trend?.length" class="chart-body"><apexchart type="line" height="330" :options="trendOptions" :series="trendSeries" /></div><div v-else class="chart-empty"><i class="bx bx-chart"></i><p>Los informes oficiales aparecerán aquí después de una resolución.</p></div></section>
                        <section class="analytics-panel decision-panel"><header><div><span>RESULTADOS</span><h2>Resoluciones oficiales</h2><p>Distribución dentro del periodo seleccionado.</p></div><i class="bx bx-check-shield"></i></header><div v-if="decisionSeries.some(Number)" class="chart-body"><apexchart type="donut" height="315" :options="decisionOptions" :series="decisionSeries" /></div><div v-else class="chart-empty compact"><i class="bx bx-doughnut-chart"></i><p>Sin resoluciones para graficar.</p></div></section>
                    </div>

                    <section class="analytics-panel dimension-panel"><header><div><span>EVOLUCIÓN POR DIMENSIÓN</span><h2>Primera versión frente a última versión</h2><p>Se comparan sólo instrumentos cuya pauta continúa siendo compatible.</p></div><div class="panel-stat"><strong>{{ formatNumber(summary.comparable_instruments) }}</strong><span>instrumentos comparables</span></div></header><div v-if="dashboard.dimensions?.some((item) => item.first_score !== null)" class="chart-body"><apexchart type="bar" :height="Math.max(360, dashboard.dimensions.length * 50)" :options="dimensionOptions" :series="dimensionSeries" /></div><div v-else class="chart-empty"><i class="bx bx-git-compare"></i><p>Se necesitan versiones revisadas para comparar dimensiones.</p></div></section>

                    <section class="analytics-panel priorities-panel"><header><div><span>FOCO DE ACOMPAÑAMIENTO</span><h2>Principales oportunidades según la pauta</h2><p>El índice combina déficit, persistencia, alcance y brecha de evidencia.</p></div><i class="bx bx-bullseye"></i></header><div class="priority-list"><article v-for="(criterion, index) in dashboard.priorities" :key="criterion.code"><span class="priority-rank">{{ index + 1 }}</span><div class="priority-copy"><div><strong>{{ criterion.code }} · {{ criterion.dimension }}</strong><span :class="{ exploratory: !criterion.sample_sufficient }">{{ criterion.sample_sufficient ? 'Muestra suficiente' : 'Exploratorio' }}</span></div><p>{{ criterion.criterion }}</p><div class="priority-facts"><span>Atención <b>{{ formatPercent(criterion.attention_rate) }}</b></span><span>Persistencia <b>{{ formatPercent(criterion.persistence_rate) }}</b></span><span>Alcance <b>{{ formatPercent(criterion.reach_rate) }}</b></span></div></div><div class="priority-score"><strong>{{ Number(criterion.opportunity_index || 0).toLocaleString('es-CL', { maximumFractionDigits: 1 }) }}</strong><small>prioridad</small></div></article><div v-if="!dashboard.priorities?.length" class="chart-empty compact"><i class="bx bx-list-check"></i><p>Sin datos de pauta para priorizar.</p></div></div></section>
                </template>

                <template v-else-if="activeTab === 'criteria'">
                    <section class="analytics-panel criteria-panel"><header><div><span>MAPA DE LA PAUTA</span><h2>Estado y evolución de los 19 criterios</h2><p>“No evidenciado” se muestra por separado y no se confunde con incumplimiento comprobado.</p></div><select v-model="selectedDimension" class="form-select dimension-filter"><option value="">Todas las dimensiones</option><option v-for="dimension in dimensions" :key="dimension" :value="dimension">{{ dimension }}</option></select></header><div class="table-responsive"><table class="criteria-table"><thead><tr><th>Criterio</th><th>Distribución de estados</th><th>Persistencia</th><th>Resolución</th><th>Alcance</th><th>Índice</th></tr></thead><tbody><tr v-for="criterion in visibleCriteria" :key="criterion.code"><td><div class="criterion-name"><span>{{ criterion.code }}</span><div><strong>{{ criterion.criterion }}</strong><small>{{ criterion.dimension }} · n={{ criterion.applicable }}</small></div></div></td><td><div class="status-bar" :title="`${criterion.meets} cumple; ${criterion.partially_meets} parcial; ${criterion.does_not_meet} no cumple; ${criterion.not_evidenced} no evidenciado`"><i class="meets" :style="{ width: statusWidth(criterion, 'meets') }"></i><i class="partial" :style="{ width: statusWidth(criterion, 'partially_meets') }"></i><i class="fails" :style="{ width: statusWidth(criterion, 'does_not_meet') }"></i><i class="missing" :style="{ width: statusWidth(criterion, 'not_evidenced') }"></i></div><div class="status-legend"><span><i class="meets"></i>{{ criterion.meets }}</span><span><i class="partial"></i>{{ criterion.partially_meets }}</span><span><i class="fails"></i>{{ criterion.does_not_meet }}</span><span><i class="missing"></i>{{ criterion.not_evidenced }}</span></div></td><td><strong class="metric-value">{{ formatPercent(criterion.persistence_rate) }}</strong><small>{{ criterion.comparable_pairs }} pares</small></td><td><strong class="metric-value positive">{{ formatPercent(criterion.resolution_rate) }}</strong><small>en la versión siguiente</small></td><td><strong class="metric-value">{{ formatPercent(criterion.reach_rate) }}</strong><small>{{ criterion.affected_teachers }} docentes</small></td><td><div class="opportunity" :class="{ exploratory: !criterion.sample_sufficient }"><strong>{{ Number(criterion.opportunity_index || 0).toLocaleString('es-CL', { maximumFractionDigits: 1 }) }}</strong><small>{{ criterion.sample_sufficient ? 'prioridad' : 'exploratorio' }}</small></div></td></tr></tbody></table></div><footer class="criteria-legend"><span><i class="meets"></i>Cumple</span><span><i class="partial"></i>Cumple parcialmente</span><span><i class="fails"></i>No cumple</span><span><i class="missing"></i>No evidenciado</span></footer></section>

                    <section class="analytics-panel miscellaneous-panel"><header><div><span>HALLAZGOS MISCELÁNEOS</span><h2>Consistencia, puntajes, redacción y presentación</h2><p>Contabiliza hallazgos que complementan la pauta, como sumas de puntaje incoherentes.</p></div><i class="bx bx-calculator"></i></header><div class="misc-layout"><div v-if="dashboard.miscellaneous?.some((item) => item.findings)" class="chart-body"><apexchart type="bar" height="320" :options="miscellaneousOptions" :series="miscellaneousSeries" /></div><div v-else class="chart-empty"><i class="bx bx-check-double"></i><p>No hay hallazgos misceláneos registrados.</p></div><div class="misc-list"><article v-for="item in dashboard.miscellaneous" :key="item.category"><span><i class="bx" :class="item.category === 'arithmetic' ? 'bx-calculator' : item.category === 'wording' ? 'bx-text' : item.category === 'presentation' ? 'bx-layout' : 'bx-git-compare'"></i></span><div><strong>{{ item.label }}</strong><small>{{ item.affected_reports }} informes · {{ item.affected_teachers }} docentes</small></div><b>{{ formatPercent(item.incidence_rate) }}</b></article></div></div></section>
                </template>

                <template v-else>
                    <section class="analytics-panel trajectory-panel"><header><div><span>TRAYECTORIA DOCENTE</span><h2>Evolución consolidada por docente</h2><p>La comparación identifica mejora; no reemplaza el juicio profesional ni constituye una calificación laboral.</p></div><i class="bx bx-user-voice"></i></header><div class="table-responsive"><table class="trajectory-table"><thead><tr><th>Docente</th><th>Instrumentos</th><th>Ajuste inicial</th><th>Ajuste actual</th><th>Evolución</th><th>Aprobación inicial</th><th>Criterios persistentes</th><th></th></tr></thead><tbody><tr v-for="teacher in dashboard.teachers" :key="teacher.id"><td><div class="teacher-cell"><span>{{ teacher.name?.split(' ').slice(0, 2).map((word) => word[0]).join('') }}</span><div><strong>{{ teacher.name }}</strong><small>{{ teacher.reports }} informes oficiales</small></div></div></td><td>{{ teacher.instruments }}</td><td>{{ formatPercent(teacher.first_score) }}</td><td><strong>{{ formatPercent(teacher.latest_score) }}</strong></td><td><span class="delta-pill" :class="{ down: Number(teacher.improvement_pp || 0) < 0 }">{{ formatDelta(teacher.improvement_pp) }}</span></td><td>{{ formatPercent(teacher.first_pass_approval_rate) }}</td><td><div class="code-list"><span v-for="code in teacher.persistent_criteria" :key="code">{{ code }}</span><small v-if="!teacher.persistent_criteria?.length">Sin persistencia comparable</small></div></td><td><button type="button" class="row-action" @click="focusTeacher(teacher)">Analizar<i class="bx bx-right-arrow-alt"></i></button></td></tr><tr v-if="!dashboard.teachers?.length"><td colspan="8"><div class="table-empty"><i class="bx bx-user-x"></i><span>No hay docentes con informes oficiales para los filtros aplicados.</span></div></td></tr></tbody></table></div></section>

                    <section class="analytics-panel instruments-panel"><header><div><span>HISTORIAL POR INSTRUMENTO</span><h2>Versiones y resultado más reciente</h2><p>Abre un instrumento para ver qué criterios se resolvieron, persistieron o retrocedieron.</p></div><span class="row-total">{{ dashboard.instruments?.length || 0 }} trayectorias</span></header><div class="table-responsive"><table class="trajectory-table instrument-table"><thead><tr><th>Instrumento</th><th>Docente</th><th>Asignatura y curso</th><th>Versiones</th><th>Ajuste</th><th>Evolución</th><th>Última resolución</th><th></th></tr></thead><tbody><tr v-for="instrument in dashboard.instruments" :key="instrument.id"><td><strong>{{ instrument.title }}</strong><small>{{ formatDate(instrument.latest_reviewed_at) }}</small></td><td>{{ instrument.teacher.name }}</td><td><strong>{{ instrument.subject.name }}</strong><small>{{ instrument.courses.map((course) => course.name).join(', ') || 'Sin curso' }}</small></td><td><span class="version-count">{{ instrument.reviewed_versions }}</span></td><td>{{ formatPercent(instrument.latest_score) }}</td><td><span v-if="instrument.rubric_compatible" class="delta-pill" :class="{ down: Number(instrument.improvement_pp || 0) < 0 }">{{ formatDelta(instrument.improvement_pp) }}</span><span v-else class="incompatible">Pauta distinta</span></td><td><span class="decision-pill" :class="instrument.latest_decision">{{ decisionLabel(instrument.latest_decision) }}</span></td><td><button type="button" class="row-action" @click="openInstrument(instrument)">Ver trayectoria<i class="bx bx-right-arrow-alt"></i></button></td></tr><tr v-if="!dashboard.instruments?.length"><td colspan="8"><div class="table-empty"><i class="bx bx-file-blank"></i><span>No hay trayectorias para los filtros aplicados.</span></div></td></tr></tbody></table></div></section>
                </template>

                <details class="methodology"><summary><span><i class="bx bx-info-circle"></i>Cómo se construyen estos indicadores</span><i class="bx bx-chevron-down"></i></summary><div><p><i class="bx bx-check"></i>{{ meta.methodology?.official_source }}</p><p><i class="bx bx-calculator"></i>{{ meta.methodology?.compliance_formula }}</p><p><i class="bx bx-git-compare"></i>{{ meta.methodology?.comparison_rule }}</p><p><i class="bx bx-code-alt"></i>Pauta: {{ meta.rubric_versions?.join(', ') || 'sin versión' }} · Prompt: {{ meta.prompt_versions?.join(', ') || 'sin versión' }}</p></div></details>
            </template>
        </main>

        <Teleport to="body">
            <div v-if="showDetail" class="trajectory-overlay" @mousedown.self="closeDetail">
                <aside class="trajectory-drawer" role="dialog" aria-modal="true" aria-labelledby="trajectory-title">
                    <div v-if="detailLoading" class="drawer-loading"><span class="spinner-border text-primary"></span><p>Cargando historial comparable...</p></div>
                    <template v-else-if="detail">
                        <header class="drawer-header"><span class="drawer-icon"><i class="bx bx-git-branch"></i></span><div><small>TRAYECTORIA DOCUMENTAL</small><h2 id="trajectory-title">{{ detail.instrument.title }}</h2><p>{{ detail.instrument.teacher }} · {{ detail.instrument.subject }} · {{ detail.instrument.courses.map((course) => course.name).join(', ') }}</p></div><button type="button" aria-label="Cerrar" @click="closeDetail"><i class="bx bx-x"></i></button></header>
                        <div class="drawer-body">
                            <section class="drawer-summary"><article><span>Primera versión</span><strong>{{ formatPercent(detail.summary.first_score) }}</strong></article><article><span>Última versión</span><strong>{{ formatPercent(detail.summary.latest_score) }}</strong></article><article><span>Evolución</span><strong>{{ formatDelta(detail.summary.improvement_pp) }}</strong></article><article><span>Versiones revisadas</span><strong>{{ detail.summary.reviewed_versions }}</strong></article></section>
                            <div v-if="!detail.summary.rubric_compatible" class="compatibility-warning"><i class="bx bx-error-circle"></i>La pauta cambió entre versiones; los puntajes no se comparan directamente.</div>
                            <section class="version-timeline"><article v-for="version in detail.versions" :key="version.snapshot_id"><div class="timeline-marker"><span>v{{ version.file_version }}</span></div><div class="version-card"><header><div><strong>Versión {{ version.file_version }}</strong><small>{{ formatDate(version.reviewed_at) }}</small></div><span class="decision-pill" :class="version.decision">{{ decisionLabel(version.decision) }}</span></header><div class="version-score"><strong>{{ formatPercent(version.compliance_percentage) }}</strong><span>Ajuste a la pauta</span><div><i :style="{ width: `${version.compliance_percentage}%` }"></i></div></div><div class="version-statuses"><span class="meets">{{ version.meets }} cumple</span><span class="partial">{{ version.partially_meets }} parciales</span><span class="fails">{{ version.does_not_meet }} no cumple</span><span class="missing">{{ version.not_evidenced }} sin evidencia</span></div></div></article></section>
                            <section v-if="detail.transitions?.length" class="transition-list"><header><span>CAMBIOS ENTRE VERSIONES</span><h3>Qué se resolvió y qué persiste</h3></header><article v-for="transition in detail.transitions" :key="`${transition.from_version}-${transition.to_version}`"><div class="transition-title"><strong>v{{ transition.from_version }} → v{{ transition.to_version }}</strong><span v-if="transition.compatible" class="delta-pill" :class="{ down: Number(transition.score_delta_pp || 0) < 0 }">{{ formatDelta(transition.score_delta_pp) }}</span><span v-else class="incompatible">Pauta distinta</span></div><div v-if="transition.compatible" class="transition-groups"><div class="resolved"><span>Resueltos</span><p><b v-for="code in transition.resolved" :key="code">{{ code }}</b><small v-if="!transition.resolved.length">Ninguno</small></p></div><div class="persistent"><span>Persisten</span><p><b v-for="code in transition.persistent" :key="code">{{ code }}</b><small v-if="!transition.persistent.length">Ninguno</small></p></div><div class="regressed"><span>Retroceden</span><p><b v-for="code in transition.regressed" :key="code">{{ code }}</b><small v-if="!transition.regressed.length">Ninguno</small></p></div></div></article></section>
                        </div>
                    </template>
                </aside>
            </div>
        </Teleport>
    </Layout>
</template>

<style scoped>
.statistics-page{--navy:#173d5e;--teal:#238d84;--ink:#1c2b40;--muted:#748195;color:var(--ink);max-width:1720px;margin:auto}.statistics-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:2rem;overflow:hidden;min-height:230px;border-radius:26px;background:radial-gradient(circle at 82% 8%,rgba(116,223,203,.24),transparent 29%),linear-gradient(118deg,#143552,#176170 62%,#209084);padding:2.4rem 2.7rem;color:#fff;box-shadow:0 20px 48px rgba(18,56,80,.22)}.statistics-hero::after{position:absolute;right:-92px;bottom:-155px;width:330px;height:330px;border:1px solid rgba(255,255,255,.14);border-radius:50%;content:""}.eyebrow{font-size:.72rem;font-weight:850;letter-spacing:.18em;opacity:.73}.statistics-hero h1{margin:.35rem 0 .45rem;color:#fff;font-size:clamp(2rem,3.5vw,3rem);letter-spacing:-.04em}.statistics-hero p{max-width:790px;margin:0;color:rgba(255,255,255,.82);font-size:1rem}.hero-meta{display:flex;flex-wrap:wrap;gap:1rem;margin-top:1.25rem}.hero-meta span{display:flex;align-items:center;gap:.4rem;color:rgba(255,255,255,.72);font-size:.75rem}.hero-meta i{font-size:1rem}.hero-score{position:relative;z-index:1;display:grid;place-items:center;min-width:160px;min-height:142px;border:1px solid rgba(255,255,255,.21);border-radius:23px;background:rgba(255,255,255,.1);backdrop-filter:blur(9px)}.hero-score span{font-size:.62rem;font-weight:850;letter-spacing:.16em;opacity:.7}.hero-score strong{font-size:2.65rem;line-height:1}.hero-score small{font-size:.73rem;opacity:.78}.statistics-alert{display:flex;align-items:center;gap:.55rem;margin:1rem 0 0;border-radius:13px}.statistics-alert button{margin-left:auto;border:0;background:transparent;color:inherit;font-weight:800}.filter-panel{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr)) auto;gap:.9rem;margin:1rem 0;padding:1.15rem;border:1px solid #e1e8ec;border-radius:20px;background:#fff;box-shadow:0 8px 25px rgba(30,56,79,.055);transition:.18s}.filter-heading{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf1f3;padding:.05rem .1rem .8rem}.filter-heading>div{display:grid}.filter-heading span{color:#238d84;font-size:.58rem;font-weight:850;letter-spacing:.14em}.filter-heading h2{margin:.1rem 0 0;font-size:1rem}.filter-count{border-radius:999px;background:#eaf6f4;padding:.28rem .55rem;letter-spacing:0!important}.filter-panel label{display:grid;gap:.3rem}.filter-panel label>span{color:#66758a;font-size:.67rem;font-weight:750}.filter-panel .form-select,.filter-panel .form-control{min-height:44px;border-color:#dce4ea;border-radius:11px;color:#36445a;font-size:.76rem}.filter-actions{display:flex;align-items:flex-end;gap:.5rem}.filter-actions .btn{display:flex;align-items:center;justify-content:center;gap:.35rem;min-height:44px;border-radius:11px;font-size:.73rem;font-weight:750}.statistics-tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-bottom:1rem}.statistics-tabs button{display:flex;align-items:center;gap:.75rem;border:1px solid #dfe6eb;border-radius:16px;background:#fff;padding:.85rem 1rem;color:#667589;text-align:left;transition:.18s}.statistics-tabs button>i{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#eff3f6;color:#60748a;font-size:1.2rem}.statistics-tabs button span{display:grid;font-size:.76rem;font-weight:800}.statistics-tabs button small{margin-top:.1rem;color:#8b97a6;font-size:.59rem;font-weight:600}.statistics-tabs button.active{border-color:#79b8ae;background:linear-gradient(110deg,#f5fbfa,#fff);box-shadow:0 6px 18px rgba(35,141,132,.09);color:#1f746e}.statistics-tabs button.active>i{background:#dff2ef;color:#20877f}.kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.75rem;margin-bottom:1rem;transition:.18s}.kpi-card{position:relative;display:flex;gap:.7rem;min-height:126px;overflow:hidden;border:1px solid #e1e7eb;border-radius:17px;background:#fff;padding:.9rem;box-shadow:0 6px 20px rgba(29,52,76,.045)}.kpi-card::after{position:absolute;right:-34px;bottom:-43px;width:105px;height:105px;border:18px solid currentColor;border-radius:50%;opacity:.035;content:""}.kpi-icon{display:grid;place-items:center;min-width:42px;height:42px;border-radius:13px;background:currentColor}.kpi-icon i{color:#fff;font-size:1.15rem}.kpi-card>div{display:grid;align-content:start;min-width:0}.kpi-card small{color:#748195;font-size:.62rem;font-weight:750}.kpi-card strong{margin:.12rem 0;color:#213149;font-size:1.45rem;letter-spacing:-.035em}.kpi-card p{margin:0;color:#8b96a4;font-size:.55rem;line-height:1.35}.tone-navy{color:#245779}.tone-teal{color:#26968c}.tone-blue{color:#4b7fa4}.tone-violet{color:#716ca9}.tone-amber{color:#d28c3b}.tone-green{color:#3b946d}.tone-rose{color:#cf626c}.sample-notice{display:flex;align-items:flex-start;gap:.75rem;margin-bottom:1rem;border:1px solid #eadab7;border-radius:16px;background:linear-gradient(105deg,#fffaf0,#fff);padding:.85rem 1rem}.sample-notice>span{display:grid;place-items:center;min-width:38px;height:38px;border-radius:11px;background:#fff0cf;color:#b6761f}.sample-notice strong{display:block;color:#684d25;font-size:.75rem}.sample-notice p{margin:.16rem 0 0;color:#8b7556;font-size:.63rem}.overview-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(330px,.7fr);gap:1rem;margin-bottom:1rem}.analytics-panel{overflow:hidden;border:1px solid #e1e7eb;border-radius:19px;background:#fff;box-shadow:0 7px 24px rgba(29,52,76,.055)}.analytics-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border-bottom:1px solid #edf1f3;padding:1rem 1.15rem}.analytics-panel>header>div:first-child{display:grid}.analytics-panel>header span{color:#238d84;font-size:.56rem;font-weight:850;letter-spacing:.13em}.analytics-panel>header h2{margin:.12rem 0;color:#27374d;font-size:1rem}.analytics-panel>header p{max-width:720px;margin:0;color:#8190a0;font-size:.62rem}.analytics-panel>header>i{display:grid;place-items:center;min-width:40px;height:40px;border-radius:12px;background:#edf5f4;color:#238d84;font-size:1.2rem}.chart-body{padding:.45rem .65rem .25rem}.chart-empty{display:flex;align-items:center;justify-content:center;gap:.7rem;min-height:310px;padding:2rem;color:#8290a0;text-align:center}.chart-empty i{font-size:1.8rem}.chart-empty p{max-width:410px;margin:0;font-size:.7rem}.chart-empty.compact{min-height:210px}.dimension-panel,.priorities-panel,.criteria-panel,.miscellaneous-panel,.trajectory-panel,.instruments-panel{margin-bottom:1rem}.panel-stat{display:grid!important;justify-items:end}.panel-stat strong{color:#234a62;font-size:1.25rem}.panel-stat span{letter-spacing:0!important;color:#7f8c9b!important;font-weight:600!important}.priority-list{display:grid}.priority-list>article{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.85rem;border-bottom:1px solid #edf1f3;padding:.85rem 1.1rem}.priority-list>article:last-child{border-bottom:0}.priority-rank{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#edf5f4;color:#21877f!important;font-size:.73rem!important;letter-spacing:0!important}.priority-copy>div:first-child{display:flex;align-items:center;gap:.5rem}.priority-copy strong{color:#34435a;font-size:.72rem}.priority-copy>div:first-child span{border-radius:999px;background:#e8f5f2;padding:.15rem .4rem;color:#26897f;font-size:.49rem;letter-spacing:0}.priority-copy>div:first-child span.exploratory{background:#fff1d9;color:#a56a1d}.priority-copy p{margin:.15rem 0;color:#758395;font-size:.61rem}.priority-facts{display:flex;flex-wrap:wrap;gap:.7rem}.priority-facts span{color:#8b96a5;font-size:.53rem;letter-spacing:0}.priority-facts b{color:#4e5e73}.priority-score{display:grid;justify-items:center;min-width:66px;border-left:1px solid #e8ecef;padding-left:.75rem}.priority-score strong{color:#214f68;font-size:1.2rem}.priority-score small{color:#8a96a4;font-size:.5rem}.dimension-filter{width:min(310px,100%);border-color:#dce4e9;border-radius:10px;font-size:.7rem}.criteria-table,.trajectory-table{width:100%;min-width:1080px;border-collapse:collapse}.criteria-table th,.trajectory-table th{border-bottom:1px solid #e5eaee;background:#f7fafb;padding:.65rem .8rem;color:#748194;font-size:.55rem;font-weight:850;letter-spacing:.05em;text-align:left;text-transform:uppercase}.criteria-table td,.trajectory-table td{border-bottom:1px solid #edf1f3;padding:.75rem .8rem;color:#536176;font-size:.64rem;vertical-align:middle}.criteria-table tbody tr:last-child td,.trajectory-table tbody tr:last-child td{border-bottom:0}.criteria-table tbody tr:hover,.trajectory-table tbody tr:hover{background:#fbfcfd}.criterion-name{display:flex;align-items:flex-start;gap:.55rem;max-width:540px}.criterion-name>span{display:grid;place-items:center;min-width:34px;height:30px;border-radius:9px;background:#eaf4f3;color:#23877f;font-size:.62rem;font-weight:850}.criterion-name>div{display:grid}.criterion-name strong{color:#344157;font-size:.64rem;line-height:1.4}.criterion-name small{margin-top:.18rem;color:#8b96a5;font-size:.52rem}.status-bar{display:flex;min-width:210px;height:10px;overflow:hidden;border-radius:999px;background:#edf1f3}.status-bar i{height:100%}.meets{background:#32a18e!important}.partial{background:#e6aa54!important}.fails{background:#dd6670!important}.missing{background:#8996a9!important}.status-legend{display:flex;gap:.48rem;margin-top:.32rem}.status-legend span{display:flex;align-items:center;gap:.18rem;color:#8491a1;font-size:.48rem;letter-spacing:0}.status-legend i,.criteria-legend i{width:7px;height:7px;border-radius:50%}.metric-value{display:block;color:#425168;font-size:.71rem}.metric-value.positive{color:#278b80}.criteria-table td>small{color:#8e99a7;font-size:.49rem}.opportunity{display:grid;justify-items:center;width:64px;border-radius:10px;background:#e8f4f2;padding:.4rem;color:#237d76}.opportunity.exploratory{background:#fff1da;color:#a16b22}.opportunity strong{font-size:.85rem}.opportunity small{font-size:.45rem}.criteria-legend{display:flex;justify-content:flex-end;gap:1rem;border-top:1px solid #edf1f3;padding:.65rem 1rem}.criteria-legend span{display:flex;align-items:center;gap:.3rem;color:#748194;font-size:.54rem;letter-spacing:0}.misc-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:1rem;padding:.35rem .6rem .8rem}.misc-list{display:grid;align-content:center}.misc-list article{display:flex;align-items:center;gap:.55rem;border-bottom:1px solid #edf1f3;padding:.65rem .35rem}.misc-list article:last-child{border-bottom:0}.misc-list article>span{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#eef4f5;color:#357d83}.misc-list article>div{display:grid}.misc-list strong{color:#3d4b60;font-size:.63rem}.misc-list small{color:#8995a5;font-size:.51rem}.misc-list b{margin-left:auto;color:#2a8078;font-size:.7rem}.teacher-cell{display:flex;align-items:center;gap:.55rem}.teacher-cell>span{display:grid;place-items:center;width:35px;height:35px;border-radius:11px;background:linear-gradient(145deg,#dff0ee,#edf5f4);color:#27877e;font-size:.58rem;font-weight:900}.teacher-cell>div,.instrument-table td:first-child,.instrument-table td:nth-child(3){display:grid}.teacher-cell strong,.instrument-table td strong{color:#344157;font-size:.64rem}.teacher-cell small,.instrument-table td small{color:#8b96a4;font-size:.5rem}.delta-pill{display:inline-flex;border-radius:999px;background:#e5f4ef;padding:.24rem .45rem;color:#238164;font-size:.54rem;font-weight:800}.delta-pill.down{background:#fae8ea;color:#b84d58}.code-list{display:flex;flex-wrap:wrap;gap:.22rem}.code-list span{border-radius:999px;background:#fff0db;padding:.18rem .35rem;color:#a46a1d;font-size:.49rem;font-weight:800}.code-list small{color:#8c97a5}.row-action{display:inline-flex;align-items:center;gap:.2rem;border:1px solid #cddde0;border-radius:9px;background:#fff;padding:.38rem .55rem;color:#217a74;font-size:.55rem;font-weight:800;white-space:nowrap}.row-action:hover{border-color:#238d84;background:#f2faf8}.version-count{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:#edf1f5;color:#52667a;font-weight:850}.incompatible{color:#a66f26;font-size:.53rem;font-weight:750}.decision-pill{display:inline-flex;border-radius:999px;padding:.25rem .45rem;font-size:.49rem;font-weight:800;white-space:nowrap}.decision-pill.approved{background:#dff3ed;color:#237a60}.decision-pill.approved_with_observations{background:#e8ecf8;color:#5e66a0}.decision-pill.rectification_requested{background:#fff0dd;color:#a8691b}.row-total{border-radius:999px;background:#e8f4f2;padding:.28rem .52rem;letter-spacing:0!important}.table-empty{display:flex;align-items:center;justify-content:center;gap:.45rem;min-height:130px;color:#8490a0}.table-empty i{font-size:1.3rem}.methodology{overflow:hidden;border:1px solid #dfe6ea;border-radius:15px;background:#fff}.methodology summary{display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:.8rem 1rem;list-style:none;color:#45556c;font-size:.67rem;font-weight:750}.methodology summary span{display:flex;align-items:center;gap:.35rem}.methodology summary span i{color:#238d84;font-size:1rem}.methodology summary::-webkit-details-marker{display:none}.methodology>div{display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;border-top:1px solid #edf1f3;padding:.8rem 1rem}.methodology p{display:flex;align-items:flex-start;gap:.35rem;margin:0;color:#768496;font-size:.57rem}.methodology p i{margin-top:.05rem;color:#238d84}.refreshing{opacity:.66;pointer-events:none}.trajectory-overlay{position:fixed;inset:0;z-index:1090;display:flex;justify-content:flex-end;background:rgba(16,30,47,.58);backdrop-filter:blur(4px)}.trajectory-drawer{display:flex;flex-direction:column;width:min(790px,calc(100vw - 24px));height:100vh;overflow:hidden;background:#f4f7f8;box-shadow:-22px 0 60px rgba(18,34,51,.28);animation:drawer-enter .22s ease-out}.drawer-loading{display:grid;place-items:center;align-content:center;height:100%;gap:.65rem;color:#6f7d8e}.drawer-header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.8rem;min-height:150px;background:radial-gradient(circle at 85% 10%,rgba(104,219,197,.22),transparent 34%),linear-gradient(120deg,#173752,#176a72);padding:1.25rem;color:#fff}.drawer-icon{display:grid;place-items:center;width:54px;height:54px;border:1px solid rgba(255,255,255,.2);border-radius:17px;background:rgba(255,255,255,.1);font-size:1.45rem}.drawer-header>div{display:grid}.drawer-header small{color:#9dded2;font-size:.56rem;font-weight:850;letter-spacing:.14em}.drawer-header h2{margin:.15rem 0;color:#fff;font-size:1.2rem}.drawer-header p{margin:0;color:rgba(255,255,255,.76);font-size:.63rem}.drawer-header>button{display:grid;place-items:center;width:38px;height:38px;border:1px solid rgba(255,255,255,.18);border-radius:11px;background:rgba(255,255,255,.08);color:#fff;font-size:1.35rem}.drawer-body{display:grid;gap:.85rem;overflow-y:auto;padding:1rem}.drawer-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:.55rem}.drawer-summary article{display:grid;border:1px solid #dfe6ea;border-radius:13px;background:#fff;padding:.75rem;box-shadow:0 4px 14px rgba(28,49,71,.04)}.drawer-summary span{color:#7c8999;font-size:.55rem}.drawer-summary strong{margin-top:.15rem;color:#27445c;font-size:1.05rem}.compatibility-warning{display:flex;align-items:center;gap:.4rem;border:1px solid #ead6af;border-radius:11px;background:#fff8ea;padding:.65rem;color:#92611f;font-size:.6rem}.version-timeline{position:relative;display:grid;gap:.65rem}.version-timeline::before{position:absolute;top:25px;bottom:25px;left:25px;width:2px;background:#cfe1df;content:""}.version-timeline>article{position:relative;display:grid;grid-template-columns:52px minmax(0,1fr);gap:.65rem}.timeline-marker{position:relative;z-index:1;display:flex;justify-content:center;padding-top:.65rem}.timeline-marker span{display:grid;place-items:center;width:42px;height:42px;border:4px solid #f4f7f8;border-radius:50%;background:#238d84;color:#fff;font-size:.65rem;font-weight:850}.version-card{overflow:hidden;border:1px solid #dfe6ea;border-radius:14px;background:#fff}.version-card>header{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #edf1f3;padding:.7rem .8rem}.version-card>header>div{display:grid}.version-card>header strong{color:#35445a;font-size:.69rem}.version-card>header small{color:#8a96a5;font-size:.51rem}.version-score{display:grid;grid-template-columns:auto 1fr;align-items:end;gap:0 .5rem;padding:.7rem .8rem}.version-score>strong{grid-row:1/3;color:#244f66;font-size:1.35rem}.version-score>span{color:#738195;font-size:.55rem}.version-score>div{height:6px;overflow:hidden;border-radius:999px;background:#edf1f3}.version-score>div i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#2b7c83,#28a28d)}.version-statuses{display:flex;flex-wrap:wrap;gap:.35rem;padding:0 .8rem .75rem}.version-statuses span{border-radius:999px;padding:.2rem .38rem;color:#fff;font-size:.49rem;font-weight:750}.transition-list{border:1px solid #dfe6ea;border-radius:14px;background:#fff}.transition-list>header{border-bottom:1px solid #edf1f3;padding:.75rem .85rem}.transition-list>header span{color:#238d84;font-size:.5rem;font-weight:850;letter-spacing:.13em}.transition-list>header h3{margin:.08rem 0;color:#344258;font-size:.75rem}.transition-list>article{border-bottom:1px solid #edf1f3;padding:.7rem .85rem}.transition-list>article:last-child{border-bottom:0}.transition-title{display:flex;align-items:center;justify-content:space-between}.transition-title strong{color:#3c4a5f;font-size:.65rem}.transition-groups{display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem;margin-top:.55rem}.transition-groups>div{border-radius:10px;padding:.5rem}.transition-groups>div.resolved{background:#eef8f4}.transition-groups>div.persistent{background:#fff6e8}.transition-groups>div.regressed{background:#fceff0}.transition-groups span{font-size:.5rem;font-weight:800}.transition-groups p{display:flex;flex-wrap:wrap;gap:.2rem;margin:.25rem 0 0}.transition-groups b{border-radius:999px;background:#fff;padding:.15rem .3rem;color:#4d5a6d;font-size:.48rem}.transition-groups small{color:#8a96a4;font-size:.48rem}@keyframes drawer-enter{from{opacity:.7;transform:translateX(55px)}to{opacity:1;transform:translateX(0)}}
.hero-side{position:relative;z-index:2;display:grid;min-width:238px;gap:.65rem}.hero-side .hero-score{min-width:100%;min-height:126px}.hero-export{display:flex;align-items:center;gap:.65rem;width:100%;border:1px solid rgba(255,255,255,.28);border-radius:15px;background:#fff;padding:.7rem .8rem;color:#173d5e;text-align:left;box-shadow:0 10px 25px rgba(10,36,55,.16);transition:transform .18s,box-shadow .18s}.hero-export:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 15px 28px rgba(10,36,55,.22)}.hero-export:disabled{cursor:not-allowed;opacity:.62}.hero-export>i{display:grid;place-items:center;min-width:35px;height:35px;border-radius:10px;background:#fdebed;color:#c84e58;font-size:1.15rem}.hero-export>span:last-child{display:grid}.hero-export strong{font-size:.69rem}.hero-export small{margin-top:.1rem;color:#7b8898;font-size:.5rem}.filter-count.dirty{background:#fff1d9;color:#9d661c!important}.applied-scope{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.75rem;margin:-.25rem 0 1rem;border:1px solid #dce7e8;border-radius:14px;background:linear-gradient(105deg,#f6fbfa,#fff);padding:.65rem .8rem}.scope-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#def1ee;color:#238d84}.applied-scope>div{display:grid;gap:.3rem}.applied-scope small{color:#238d84;font-size:.52rem;font-weight:850;letter-spacing:.12em}.applied-scope>div>div{display:flex;flex-wrap:wrap;gap:.3rem}.applied-scope>div>div span{border:1px solid #dfe8e9;border-radius:999px;background:#fff;padding:.2rem .42rem;color:#5e6e81;font-size:.53rem;font-weight:700}.applied-scope time{display:flex;align-items:center;gap:.3rem;color:#8290a0;font-size:.54rem;white-space:nowrap}.executive-brief{display:grid;grid-template-columns:minmax(190px,1.15fr) repeat(4,minmax(130px,1fr));gap:0;overflow:hidden;margin-bottom:1rem;border:1px solid #dfe7eb;border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(29,52,76,.05)}.executive-brief>header{display:grid;align-content:center;background:linear-gradient(135deg,#173d5e,#1a6872);padding:1rem 1.15rem;color:#fff}.executive-brief>header span{color:#9ddbd4;font-size:.53rem;font-weight:850;letter-spacing:.13em}.executive-brief>header strong{margin-top:.25rem;font-size:.78rem;line-height:1.3}.executive-brief>article{display:flex;align-items:center;gap:.6rem;border-right:1px solid #edf1f3;padding:.85rem}.executive-brief>article:last-child{border-right:0}.executive-brief>article>i{display:grid;place-items:center;min-width:36px;height:36px;border-radius:11px;background:currentColor;color:#fff;font-size:1rem}.executive-brief>article>div{display:grid;min-width:0}.executive-brief small{color:#7d8999;font-size:.52rem;font-weight:750}.executive-brief strong{margin:.08rem 0;color:#26374d;font-size:.88rem}.executive-brief p{overflow:hidden;margin:0;color:#8d98a5;font-size:.48rem;line-height:1.3;text-overflow:ellipsis}.signal-teal{color:#268f85}.signal-amber{color:#d1913e}.signal-navy{color:#285b7a}.signal-green{color:#3b946d}.signal-violet{color:#716ca9}
.statistics-page{--navy:#153f5f;--teal:#168c82;--ink:#19314b;--muted:#63758a;color:var(--ink)}.statistics-tabs button{border-color:#d7e2e8;background:linear-gradient(145deg,#fff,#f8fafc);color:#50657b;box-shadow:0 5px 16px rgba(30,58,79,.04)}.statistics-tabs button>i{background:#eaf0f4;color:#55708a}.statistics-tabs button small{color:#728399}.statistics-tabs button:hover{border-color:#a8c6cc;background:#fff;transform:translateY(-1px)}.statistics-tabs button.active{border-color:#4ca79e;background:linear-gradient(130deg,#effaf8,#fff 72%);box-shadow:0 9px 22px rgba(22,140,130,.13);color:#137d75}.statistics-tabs button.active>i{background:#d9f1ed;color:#12877e}.kpi-card{border-color:#d8e3e8;background:linear-gradient(155deg,#fff 68%,var(--tone-soft,#f5f8fa));box-shadow:0 8px 24px rgba(26,52,75,.065)}.kpi-card::before{position:absolute;top:0;right:20px;left:20px;height:3px;border-radius:0 0 8px 8px;background:currentColor;content:"";opacity:.9}.kpi-card small{color:#50657a}.kpi-card strong{color:#172f49}.kpi-card p{color:#6d7e91}.kpi-icon{box-shadow:0 8px 18px rgba(29,60,83,.14)}.tone-navy{--tone-soft:#eaf3f8;color:#1d6286}.tone-teal{--tone-soft:#e7f7f4;color:#159486}.tone-blue{--tone-soft:#eaf4fa;color:#3d83ab}.tone-violet{--tone-soft:#f0effa;color:#6c68ad}.tone-amber{--tone-soft:#fff4e4;color:#d28327}.tone-green{--tone-soft:#eaf7f0;color:#2f956d}.tone-rose{--tone-soft:#fcecef;color:#c95462}.executive-brief{border-color:#d6e2e7;box-shadow:0 10px 28px rgba(25,53,75,.075)}.executive-brief>header{background:linear-gradient(135deg,#123a58,#126e72)}.executive-brief>header span{color:#8fe0d5}.executive-brief>header strong{color:#fff;text-shadow:0 1px 1px rgba(0,0,0,.12)}.executive-brief>article{position:relative;background:linear-gradient(155deg,#fff 72%,var(--signal-soft,#f5f8fa))}.executive-brief>article::before{position:absolute;top:0;right:14px;left:14px;height:3px;border-radius:0 0 6px 6px;background:var(--signal,currentColor);content:""}.executive-brief>article>i{background:var(--signal,#168c82)}.executive-brief small{color:#607389}.executive-brief strong{color:#19314b}.executive-brief p{color:#718397}.signal-teal{--signal:#178d83;--signal-soft:#e9f7f5;color:var(--signal)}.signal-amber{--signal:#d08228;--signal-soft:#fff3e2;color:var(--signal)}.signal-navy{--signal:#226487;--signal-soft:#eaf3f8;color:var(--signal)}.signal-green{--signal:#32946d;--signal-soft:#eaf7f0;color:var(--signal)}.signal-violet{--signal:#6f6aaf;--signal-soft:#f0effa;color:var(--signal)}.sample-notice{border-color:#e7bf75;background:linear-gradient(110deg,#fff7e8,#fffdf8);box-shadow:0 6px 18px rgba(169,103,20,.055)}.sample-notice>span{background:#ffe8b7;color:#b86d12}.sample-notice strong{color:#654517}.sample-notice p{color:#795f38}.analytics-panel{border-color:#d8e3e8;box-shadow:0 9px 28px rgba(26,52,75,.065)}.analytics-panel>header{background:linear-gradient(120deg,#fff,#fbfdfd)}.analytics-panel>header span{color:#12877e}.analytics-panel>header h2{color:#1c344d}.analytics-panel>header p{color:#687b8f}.analytics-panel>header>i{background:#e4f3f1;color:#168c82}.chart-body{background:linear-gradient(180deg,#fff,#fbfcfd)}
@media(max-width:1450px){.filter-panel{grid-template-columns:repeat(4,1fr)}.filter-actions{grid-column:4}.kpi-grid{grid-template-columns:repeat(3,1fr)}.executive-brief{grid-template-columns:repeat(4,1fr)}.executive-brief>header{grid-column:1/-1}.executive-brief>article{border-top:1px solid #edf1f3}}
@media(max-width:980px){.statistics-page{padding-inline:.75rem}.statistics-hero{padding:1.5rem}.hero-side{min-width:210px}.filter-panel{grid-template-columns:repeat(2,1fr)}.filter-actions{grid-column:auto}.statistics-tabs{grid-template-columns:1fr}.overview-grid,.misc-layout{grid-template-columns:1fr}.methodology>div{grid-template-columns:1fr}.criteria-panel>header{flex-direction:column}.dimension-filter{width:100%}.executive-brief{grid-template-columns:1fr 1fr}.executive-brief>header{grid-column:1/-1}.executive-brief>article:nth-child(3){border-right:0}}
@media(max-width:650px){.statistics-page{padding-inline:.5rem}.statistics-hero{align-items:stretch;min-height:unset;margin-inline:-.5rem;border-radius:0 0 20px 20px;padding:1.2rem}.statistics-hero h1{font-size:1.7rem}.statistics-hero p{font-size:.78rem}.hero-side{min-width:150px;align-content:end}.hero-side .hero-score{min-width:100%;min-height:90px}.hero-score strong{font-size:2rem}.hero-export{padding:.6rem}.hero-export small{display:none}.hero-meta span:last-child{display:none}.filter-panel{grid-template-columns:1fr}.filter-heading,.filter-actions{grid-column:1}.filter-actions .btn{flex:1}.applied-scope{grid-template-columns:auto 1fr}.applied-scope time{grid-column:2}.kpi-grid{grid-template-columns:1fr 1fr}.executive-brief{grid-template-columns:1fr 1fr}.sample-notice{flex-direction:column}.analytics-panel>header{flex-direction:column}.panel-stat{justify-items:start}.criteria-legend{justify-content:flex-start;flex-wrap:wrap}.drawer-summary{grid-template-columns:1fr 1fr}.transition-groups{grid-template-columns:1fr}.trajectory-drawer{width:100vw}.drawer-header{min-height:125px;padding:.9rem}.drawer-icon{display:none}}
@media(max-width:420px){.kpi-grid{grid-template-columns:1fr}.hero-score{display:none}.hero-side{min-width:136px}.hero-export>i{display:none}.executive-brief{grid-template-columns:1fr}.executive-brief>article{border-right:0}.statistics-tabs button small{display:none}}
</style>
