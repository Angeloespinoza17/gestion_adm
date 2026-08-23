<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import VueApexCharts from "vue3-apexcharts";
import Swal from "sweetalert2";
import {
    LIBRO_DIGITAL_API_BASE,
    libroDigitalApi,
    waitForLibroDigitalJob,
} from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import ManualCurriculumProgramForm from "./ManualCurriculumProgramForm.vue";
import {
    confirmAction,
    formatDateTime,
    humanize,
    payloadData,
    payloadItems,
    showError,
    showSuccess,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});

const route = useRoute();
const router = useRouter();
const activeView = ref("matrix");
const loading = ref(false);
const error = ref(null);
const matrix = ref({ levels: [], rows: [], chart: { labels: [], series: [] } });
const imports = ref([]);
const selectedImport = ref(null);
const selectedProgram = ref(null);
const subjectFilter = ref("");
const levelFilter = ref("");
const statusFilter = ref("");
const searchTerm = ref("");
const searchResults = ref([]);
const searching = ref(false);
const files = ref([]);
const dragging = ref(false);
const uploading = ref(false);
const actionBusy = ref(false);
const exportingPdf = ref(false);
const exportProgress = ref(0);
const exportStatus = ref(null);
const importPoll = ref(null);
let controller = null;
let searchController = null;

const contextReady = computed(
    () => Boolean(props.context.school_id && props.context.academic_year_id)
);
const canImport = computed(() =>
    Boolean(props.capabilities.can_import_curriculum_programs)
);
const canImportBatch = computed(() =>
    Boolean(props.capabilities.can_import_curriculum_programs_batch)
);
const canReview = computed(() =>
    Boolean(props.capabilities.can_review_curriculum_programs)
);
const canResolve = computed(() =>
    Boolean(props.capabilities.can_resolve_curriculum_conflicts)
);
const canPublish = computed(() =>
    Boolean(props.capabilities.can_publish_curriculum_programs)
);
const canReprocess = computed(() =>
    Boolean(props.capabilities.can_reprocess_curriculum_programs)
);
const canExport = computed(() =>
    Boolean(props.capabilities.can_export_curriculum_programs_pdf)
);
const canViewDocuments = computed(() =>
    Boolean(props.capabilities.can_view_curriculum_documents)
);
const requestParams = computed(() => ({
    school_id: props.context.school_id,
    academic_year_id: props.context.academic_year_id,
}));
const subjectOptions = computed(() =>
    matrix.value.rows.map((row) => row.subject).sort((a, b) =>
        a.name.localeCompare(b.name, "es")
    )
);
const filteredRows = computed(() =>
    matrix.value.rows.filter((row) => {
        if (subjectFilter.value && String(row.subject.id) !== subjectFilter.value)
            return false;
        if (!statusFilter.value && !levelFilter.value) return true;
        return row.cells.some(
            (cell) =>
                (!levelFilter.value ||
                    String(cell.education_level_id) === levelFilter.value) &&
                (!statusFilter.value || cell.status === statusFilter.value)
        );
    })
);
const selectedUnitTotalHours = computed(() =>
    (selectedProgram.value?.units || []).reduce(
        (total, unit) => total + Number(unit.hours || 0),
        0
    )
);
const currentDocument = computed(
    () => selectedImport.value?.document || selectedProgram.value?.documents?.[0]
);
const selectedFileLabel = computed(() =>
    files.value.length === 1
        ? files.value[0].name
        : `${files.value.length} documentos seleccionados`
);
const exportButtonLabel = computed(() => {
    if (!exportingPdf.value) return "Exportar PDF";
    if (exportStatus.value === "queued") return "PDF en cola…";
    if (["processing", "generating"].includes(exportStatus.value))
        return `Generando ${exportProgress.value}%`;
    if (exportStatus.value === "completed") return "Preparando descarga…";

    return "Preparando PDF…";
});

const statusLabel = (status) =>
    ({
        missing: "Sin cargar",
        draft: "Borrador",
        uploaded: "Cargado",
        queued: "En cola",
        extracting: "Extrayendo",
        running_ocr: "Aplicando OCR",
        classifying: "Clasificando",
        parsing: "Analizando",
        reconciling: "Conciliando",
        processing: "Procesando",
        pending_review: "Revisión pendiente",
        validated: "Validado",
        published: "Publicado",
        published_with_warnings: "Publicado con alertas",
        failed: "Fallido",
        archived: "Archivado",
    }[status] || humanize(status));
const statusTone = (status) =>
    ({
        missing: "muted",
        draft: "dark",
        uploaded: "info",
        queued: "info",
        extracting: "info",
        running_ocr: "info",
        classifying: "info",
        parsing: "info",
        reconciling: "info",
        processing: "info",
        pending_review: "warning",
        validated: "primary",
        published: "success",
        published_with_warnings: "warning",
        failed: "danger",
        archived: "dark",
    }[status] || "muted");
const confidenceTone = (value) =>
    value >= 0.9 ? "success" : value >= 0.7 ? "warning" : "danger";
const chartColors = computed(() =>
    matrix.value.chart.labels.map(
        (label) =>
            ({
                published: "#0ab39c",
                draft: "#7b8497",
                published_with_warnings: "#f7b84b",
                validated: "#405189",
                pending_review: "#f06548",
                missing: "#d7dce5",
                failed: "#d94c63",
            }[label] || "#50a5f1")
    )
);
const matrixChartOptions = computed(() => ({
    labels: matrix.value.chart.labels.map(statusLabel),
    colors: chartColors.value,
    chart: { fontFamily: "Inter, sans-serif", toolbar: { show: false } },
    legend: { position: "bottom", fontSize: "12px" },
    dataLabels: { enabled: false },
    stroke: { width: 3, colors: ["#fff"] },
    plotOptions: { pie: { donut: { size: "72%" } } },
}));
const programBarOptions = (labels, color = "#405189") => ({
    chart: { toolbar: { show: false }, fontFamily: "Inter, sans-serif" },
    colors: [color],
    xaxis: { categories: labels, labels: { trim: true, rotate: -20 } },
    plotOptions: { bar: { borderRadius: 6, columnWidth: "48%" } },
    dataLabels: { enabled: false },
    grid: { borderColor: "#edf0f4" },
});

const stopPolling = () => {
    if (importPoll.value) window.clearInterval(importPoll.value);
    importPoll.value = null;
};
const startPolling = () => {
    stopPolling();
    if (
        !imports.value.some((item) =>
            [
                "uploaded",
                "queued",
                "extracting",
                "running_ocr",
                "classifying",
                "parsing",
                "reconciling",
                "processing",
            ].includes(item.status)
        )
    )
        return;
    importPoll.value = window.setInterval(loadImports, 3500);
};
const loadMatrix = async () => {
    if (!contextReady.value) return;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        matrix.value = payloadData(
            await libroDigitalApi.curriculumProgramMatrix(
                requestParams.value,
                controller.signal
            ),
            matrix.value
        );
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loading.value = false;
    }
};
const loadImports = async () => {
    if (!contextReady.value) return;
    try {
        imports.value = payloadItems(
            await libroDigitalApi.curriculumProgramImports({
                ...requestParams.value,
                per_page: 100,
            })
        );
        startPolling();
        if (selectedImport.value) {
            const match = imports.value.find(
                (item) => item.id === selectedImport.value.id
            );
            if (match && match.status !== selectedImport.value.status)
                await openImport(match);
        }
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    }
};
const loadAll = async () => {
    selectedProgram.value = null;
    selectedImport.value = null;
    await Promise.all([loadMatrix(), loadImports()]);
    const requested = route.query.program;
    if (requested) await openProgram({ id: requested }, false);
};

watch(
    [
        () => props.context.school_id,
        () => props.context.academic_year_id,
        () => props.refreshToken,
    ],
    loadAll,
    { immediate: true }
);
onBeforeUnmount(() => {
    controller?.abort();
    searchController?.abort();
    stopPolling();
});

const setView = (view) => {
    activeView.value = view;
    if (view !== "catalog") selectedProgram.value = null;
    if (view !== "imports") selectedImport.value = null;
};
const manualProgramCreated = async (program) => {
    await Promise.all([loadMatrix(), loadImports()]);
    activeView.value = "catalog";
    await openProgram(program);
};
const openCell = async (cell) => {
    if (cell.program) return openProgram(cell.program);
    if (cell.import) {
        activeView.value = "imports";
        return openImport(cell.import);
    }
};
const openProgram = async (program, updateRoute = true) => {
    loading.value = true;
    error.value = null;
    try {
        selectedProgram.value = payloadData(
            await libroDigitalApi.curriculumProgram(
                program.id,
                requestParams.value
            )
        );
        activeView.value = "catalog";
        if (updateRoute)
            await router.replace({
                query: { ...route.query, program: program.id },
            });
    } catch (requestError) {
        await showError(requestError, "No se pudo abrir el programa");
    } finally {
        loading.value = false;
    }
};
const closeProgram = async () => {
    selectedProgram.value = null;
    const query = { ...route.query };
    delete query.program;
    await router.replace({ query });
};
const openImport = async (item) => {
    loading.value = true;
    try {
        selectedImport.value = payloadData(
            await libroDigitalApi.curriculumProgramImport(
                item.id,
                requestParams.value
            )
        );
    } catch (requestError) {
        await showError(requestError, "No se pudo abrir la revisión");
    } finally {
        loading.value = false;
    }
};

const pickFiles = (event) => setFiles(event.target.files);
const dropFiles = (event) => {
    dragging.value = false;
    setFiles(event.dataTransfer.files);
};
const setFiles = (fileList) => {
    const selected = [...(fileList || [])].filter(
        (file) =>
            file.type === "application/pdf" ||
            file.name.toLowerCase().endsWith(".pdf")
    );
    if (!canImportBatch.value && selected.length > 1) {
        files.value = selected.slice(0, 1);
        showError(
            new Error(
                "Tu rol permite importar un documento por vez. La carga masiva requiere un permiso adicional."
            )
        );
        return;
    }
    files.value = selected.slice(0, 30);
};
const upload = async () => {
    if (!files.value.length || !contextReady.value) return;
    uploading.value = true;
    try {
        const form = new FormData();
        form.set("school_id", props.context.school_id);
        form.set("academic_year_id", props.context.academic_year_id);
        files.value.forEach((file) => form.append("files[]", file));
        const response = payloadData(
            await libroDigitalApi.uploadCurriculumProgramDocuments(form)
        );
        files.value = [];
        await Promise.all([loadImports(), loadMatrix()]);
        const first = response.files?.[0] || response.duplicates?.[0];
        if (first) await openImport(first);
        await showSuccess(
            response.files?.length ? "Importación iniciada" : "Documento existente",
            response.files?.length
                ? "Cada PDF se procesará por separado y quedará pendiente de revisión."
                : "El archivo ya estaba registrado; se abrió la carga existente."
        );
    } catch (requestError) {
        await showError(requestError, "No se pudo cargar el documento");
    } finally {
        uploading.value = false;
    }
};

const reviewCandidate = async (candidate, reviewStatus) => {
    actionBusy.value = true;
    try {
        await libroDigitalApi.reviewCurriculumProgramCandidate(candidate.id, {
            review_status: reviewStatus,
            suggested_action:
                reviewStatus === "accepted"
                    ? candidate.suggested_existing_id
                        ? "reuse"
                        : "create"
                    : "skip",
        });
        await openImport(selectedImport.value);
    } catch (requestError) {
        await showError(requestError, "No se pudo registrar la revisión");
    } finally {
        actionBusy.value = false;
    }
};
const resolveConflict = async (conflict) => {
    const prompt = await Swal.fire({
        title: "Resolver conflicto",
        text: conflict.description,
        input: "textarea",
        inputLabel: "Criterio y resolución adoptada",
        inputPlaceholder: "Describe la fuente y el criterio utilizado…",
        inputValidator: (value) =>
            value?.trim()?.length >= 3
                ? undefined
                : "Ingresa una resolución trazable.",
        showCancelButton: true,
        confirmButtonText: "Guardar resolución",
        cancelButtonText: "Cancelar",
    });
    if (!prompt.isConfirmed) return;
    actionBusy.value = true;
    try {
        await libroDigitalApi.resolveCurriculumProgramConflict(conflict.id, {
            resolution: prompt.value.trim(),
        });
        await openImport(selectedImport.value);
    } catch (requestError) {
        await showError(requestError, "No se pudo resolver el conflicto");
    } finally {
        actionBusy.value = false;
    }
};
const importAction = async (action) => {
    const copy = {
        validate: ["Validar importación", "Verificará cobertura, conciliación y conflictos críticos."],
        publish: ["Publicar programa", "El programa validado quedará disponible para leccionarios y evaluaciones."],
        reprocess: ["Reprocesar documento", "Se repetirá la extracción conservando el historial y el PDF original."],
    }[action];
    const confirmation = await confirmAction({
        title: copy[0],
        text: copy[1],
        confirmText: copy[0],
    });
    if (!confirmation.isConfirmed) return;
    actionBusy.value = true;
    try {
        const response = payloadData(
            await libroDigitalApi.curriculumProgramImportAction(
                selectedImport.value.id,
                action,
                { academic_year_id: props.context.academic_year_id }
            )
        );
        if (action === "publish") {
            selectedImport.value = null;
            await loadAll();
            await openProgram(response);
        } else await openImport({ id: selectedImport.value.id });
        await showSuccess(statusLabel(action === "publish" ? "published" : action));
    } catch (requestError) {
        await showError(requestError, `No se pudo ${action === "validate" ? "validar" : action === "publish" ? "publicar" : "reprocesar"}`);
    } finally {
        actionBusy.value = false;
    }
};

const search = async () => {
    if (searchTerm.value.trim().length < 2) {
        searchResults.value = [];
        return;
    }
    searchController?.abort();
    searchController = new AbortController();
    searching.value = true;
    try {
        searchResults.value = payloadItems(
            await libroDigitalApi.searchCurriculumPrograms(
                {
                    ...requestParams.value,
                    query: searchTerm.value.trim(),
                    education_level_id: levelFilter.value || undefined,
                    schedule_subject_id: subjectFilter.value || undefined,
                    limit: 100,
                },
                searchController.signal
            )
        );
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED")
            await showError(requestError, "No se pudo buscar");
    } finally {
        searching.value = false;
    }
};
const exportPdf = async () => {
    exportingPdf.value = true;
    exportProgress.value = 0;
    exportStatus.value = "preparing";
    try {
        let job = payloadData(
            await libroDigitalApi.exportCurriculumProgramPdf(
                selectedProgram.value.id,
                { academic_year_id: props.context.academic_year_id }
            )
        );
        job = await waitForLibroDigitalJob(
            job,
            async (current) =>
                payloadData(await libroDigitalApi.report(current.id)),
            {
                onProgress: (current) => {
                    exportStatus.value = current.status || "processing";
                    const progress = Number(
                        current.progress ?? current.progress_percent ?? 0
                    );
                    exportProgress.value = Number.isFinite(progress)
                        ? Math.max(0, Math.min(100, progress))
                        : 0;
                },
            }
        );
        exportStatus.value = job.status || "completed";
        exportProgress.value = Number(job.progress ?? 100);
        await libroDigitalApi.download(
            `${LIBRO_DIGITAL_API_BASE}/reports/${job.id}/download`,
            `programa-curricular-${selectedProgram.value.grade_code}.pdf`
        );
        await showSuccess("PDF generado", "La exportación incluye trazabilidad de las fuentes.");
    } catch (requestError) {
        await showError(requestError, "No se pudo generar el PDF");
    } finally {
        exportingPdf.value = false;
        exportProgress.value = 0;
        exportStatus.value = null;
    }
};
</script>

<template>
    <section class="curriculum-programs">
        <div class="catalog-hero">
            <div>
                <span class="eyebrow"><i class="bx bx-certification"></i> Fuentes ministeriales verificables</span>
                <h3>Mapa curricular institucional</h3>
                <p>
                    Consulta qué programa oficial respalda cada asignatura y nivel. Toda carga pasa por
                    extracción, conciliación con los OA existentes, revisión humana y publicación explícita.
                </p>
            </div>
            <div class="hero-metric">
                <strong>{{ matrix.chart.series.reduce((sum, item) => sum + item, 0) }}</strong>
                <span>combinaciones monitoreadas</span>
            </div>
        </div>

        <nav class="view-tabs" aria-label="Vistas del catálogo curricular">
            <button :class="{ active: activeView === 'matrix' }" @click="setView('matrix')">
                <i class="bx bx-grid-alt"></i> Matriz de cobertura
            </button>
            <button :class="{ active: activeView === 'catalog' }" @click="setView('catalog')">
                <i class="bx bx-book-open"></i> Programas publicados
            </button>
            <button :class="{ active: activeView === 'imports' }" @click="setView('imports')">
                <i class="bx bx-cloud-upload"></i> Importar y revisar
                <span v-if="imports.some((item) => item.status === 'pending_review')" class="tab-dot"></span>
            </button>
            <button v-if="canImport" :class="{ active: activeView === 'manual' }" @click="setView('manual')">
                <i class="bx bx-edit-alt"></i> Crear manualmente
            </button>
            <button :class="{ active: activeView === 'search' }" @click="setView('search')">
                <i class="bx bx-search-alt"></i> Búsqueda transversal
            </button>
        </nav>

        <LibroDigitalStatePanel
            v-if="!contextReady"
            icon="bx-calendar-check"
            title="Selecciona establecimiento y año académico"
            message="El catálogo se consulta dentro de un contexto institucional para mostrar importaciones, permisos y trazabilidad correctos."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !matrix.rows.length"
            icon="bx-error-circle"
            title="No fue posible cargar el catálogo"
            :message="error.message"
            action-label="Reintentar"
            @action="loadAll"
        />

        <template v-else-if="contextReady">
            <div v-if="activeView === 'matrix'" class="view-grid matrix-view">
                <div class="card-panel coverage-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">Disponibilidad oficial</span>
                            <h4>Asignatura × nivel de enseñanza</h4>
                        </div>
                        <button class="btn-icon" title="Actualizar" :disabled="loading" @click="loadAll">
                            <i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i>
                        </button>
                    </div>
                    <div class="filters-row">
                        <select v-model="subjectFilter" class="form-select">
                            <option value="">Todas las asignaturas</option>
                            <option v-for="subject in subjectOptions" :key="subject.id" :value="String(subject.id)">
                                {{ subject.name }}
                            </option>
                        </select>
                        <select v-model="levelFilter" class="form-select">
                            <option value="">Todos los niveles</option>
                            <option v-for="level in matrix.levels" :key="level.id" :value="String(level.id)">
                                {{ level.name }}
                            </option>
                        </select>
                        <select v-model="statusFilter" class="form-select">
                            <option value="">Todos los estados</option>
                            <option v-for="status in matrix.legend" :key="status" :value="status">
                                {{ statusLabel(status) }}
                            </option>
                        </select>
                    </div>
                    <div class="matrix-scroll">
                        <table class="coverage-matrix">
                            <thead>
                                <tr>
                                    <th>Asignatura</th>
                                    <th v-for="level in matrix.levels" :key="level.id" :class="{ dimmed: levelFilter && levelFilter !== String(level.id) }">
                                        <span>{{ level.name }}</span>
                                        <small>{{ level.grade_code }}</small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in filteredRows" :key="row.subject.id">
                                    <th>
                                        <span class="subject-mark" :style="{ background: row.subject.color || '#405189' }"></span>
                                        <span>{{ row.subject.name }}</span>
                                        <small>{{ row.subject.area || row.subject.code }}</small>
                                    </th>
                                    <td v-for="cell in row.cells" :key="cell.education_level_id" :class="{ dimmed: levelFilter && levelFilter !== String(cell.education_level_id) }">
                                        <button class="status-cell" :class="`tone-${statusTone(cell.status)}`" :disabled="cell.status === 'missing'" @click="openCell(cell)">
                                            <i :class="cell.program ? 'bx bx-check-shield' : cell.import ? 'bx bx-loader-circle' : 'bx bx-minus'"></i>
                                            <span>{{ statusLabel(cell.status) }}</span>
                                            <small v-if="cell.program">{{ cell.program.hours || 0 }} h · {{ cell.program.weeks || 0 }} sem.</small>
                                            <small v-else-if="cell.import">{{ cell.import.progress || 0 }}%</small>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <aside class="card-panel chart-panel">
                    <span class="panel-kicker">Lectura rápida</span>
                    <h4>Estado de cobertura</h4>
                    <VueApexCharts type="donut" height="275" :options="matrixChartOptions" :series="matrix.chart.series" />
                    <div class="integrity-note">
                        <i class="bx bx-lock-alt"></i>
                        <p><strong>Catálogo maestro protegido.</strong> La importación reutiliza OA existentes y bloquea códigos incompatibles.</p>
                    </div>
                </aside>
            </div>

            <div v-else-if="activeView === 'catalog'" class="catalog-view">
                <div v-if="!selectedProgram" class="card-panel program-list-panel">
                    <div class="panel-heading">
                        <div><span class="panel-kicker">Repositorio vigente</span><h4>Programas publicados</h4></div>
                    </div>
                    <div class="program-grid">
                        <button v-for="row in matrix.rows" :key="row.subject.id" class="program-card" @click="openProgram(row.cells.find((cell) => cell.program)?.program)" :disabled="!row.cells.some((cell) => cell.program)">
                            <span class="program-icon" :style="{ background: `${row.subject.color || '#405189'}18`, color: row.subject.color || '#405189' }"><i class="bx bx-book-content"></i></span>
                            <strong>{{ row.subject.name }}</strong>
                            <span>{{ row.cells.filter((cell) => cell.program).length }} niveles publicados</span>
                            <i class="bx bx-right-arrow-alt"></i>
                        </button>
                    </div>
                </div>
                <article v-else class="program-detail">
                    <header class="detail-header">
                        <button class="back-button" @click="closeProgram"><i class="bx bx-left-arrow-alt"></i> Volver</button>
                        <div class="detail-title">
                            <span class="status-pill" :class="`tone-${statusTone(selectedProgram.status)}`"><i class="bx" :class="selectedProgram.status === 'published' ? 'bx-check-shield' : 'bx-edit-alt'"></i>{{ statusLabel(selectedProgram.status) }}</span>
                            <h3>{{ selectedProgram.name }}</h3>
                            <p>{{ selectedProgram.subject?.name }} · {{ selectedProgram.education_level?.name }} · {{ selectedProgram.version?.decree || selectedProgram.version?.name }}</p>
                        </div>
                        <button v-if="canExport" class="btn-primary-soft" :disabled="exportingPdf" @click="exportPdf">
                            <i class="bx" :class="exportingPdf ? 'bx-loader-alt bx-spin' : 'bx-file'"></i>{{ exportButtonLabel }}
                        </button>
                    </header>
                    <div class="metric-strip">
                        <div><strong>{{ selectedProgram.weeks }}</strong><span>semanas</span></div>
                        <div><strong>{{ selectedProgram.hours }}</strong><span>horas pedagógicas</span></div>
                        <div><strong>{{ selectedProgram.objectives?.length || 0 }}</strong><span>objetivos</span></div>
                        <div><strong>{{ selectedProgram.axes?.length || 0 }}</strong><span>ejes</span></div>
                        <div><strong>{{ selectedProgram.units?.length || 0 }}</strong><span>unidades</span></div>
                    </div>
                    <section class="card-panel annual-map">
                        <div class="panel-heading"><div><span class="panel-kicker">Secuencia anual</span><h4>Mapa proporcional de unidades</h4></div><span>{{ selectedUnitTotalHours }} horas distribuidas</span></div>
                        <div class="unit-timeline">
                            <div v-for="(unit, index) in selectedProgram.units" :key="unit.id" class="timeline-unit" :style="{ flexGrow: Math.max(Number(unit.hours || 1), 12) }">
                                <span>U{{ index + 1 }} · {{ unit.hours }} h</span><strong>{{ unit.focus || unit.title }}</strong><small>Págs. {{ unit.page_start }}–{{ unit.page_end }}</small>
                            </div>
                        </div>
                    </section>
                    <div class="detail-charts">
                        <div class="card-panel"><span class="panel-kicker">Carga anual</span><h4>Horas por unidad</h4><VueApexCharts type="bar" height="265" :options="programBarOptions(selectedProgram.charts.hours_by_unit.labels)" :series="[{ name: 'Horas', data: selectedProgram.charts.hours_by_unit.series }]" /></div>
                        <div class="card-panel"><span class="panel-kicker">Cobertura</span><h4>OA por eje</h4><VueApexCharts type="bar" height="265" :options="programBarOptions(selectedProgram.charts.objectives_by_axis.labels, '#0ab39c')" :series="[{ name: 'Objetivos', data: selectedProgram.charts.objectives_by_axis.series }]" /></div>
                    </div>
                    <section class="units-grid">
                        <article v-for="unit in selectedProgram.units" :key="unit.id" class="card-panel unit-card">
                            <div class="unit-number">{{ unit.code }}</div>
                            <div><span class="panel-kicker">{{ unit.semester ? `${unit.semester}.º semestre` : 'Secuencia anual' }} · {{ unit.hours }} h</span><h4>{{ unit.title }}</h4><p>{{ unit.purpose }}</p></div>
                            <div class="unit-tags"><span v-for="objective in unit.objectives" :key="objective.id">{{ objective.code }}</span></div>
                            <details><summary>Habilidades, actitudes y palabras clave</summary><p><strong>Habilidades:</strong> {{ unit.skills.map((item) => item.name).join(' · ') || '—' }}</p><p><strong>Actitudes:</strong> {{ unit.attitudes.map((item) => item.text).join(' · ') || '—' }}</p><p><strong>Palabras clave:</strong> {{ unit.keywords.map((item) => item.text).join(' · ') || '—' }}</p></details>
                        </article>
                    </section>
                    <section v-if="canViewDocuments && selectedProgram.documents?.length" class="card-panel source-panel">
                        <div class="panel-heading"><div><span class="panel-kicker">Evidencia documental</span><h4>Fuente ministerial</h4></div><a class="btn-primary-soft" :href="libroDigitalApi.curriculumDocumentUrl(selectedProgram.documents[0].id)" target="_blank" rel="noopener"><i class="bx bx-link-external"></i>Abrir PDF</a></div>
                        <p>{{ selectedProgram.documents[0].title }} · {{ selectedProgram.documents[0].page_count }} páginas · SHA-256 {{ selectedProgram.documents[0].sha256.slice(0, 16) }}…</p>
                    </section>
                </article>
            </div>

            <div v-else-if="activeView === 'imports'" class="imports-layout">
                <aside class="imports-sidebar card-panel">
                    <div class="panel-heading"><div><span class="panel-kicker">Procesamiento asincrónico</span><h4>Importaciones</h4></div><span class="count-badge">{{ imports.length }}</span></div>
                    <label v-if="canImport" class="drop-zone" :class="{ dragging }" @dragenter.prevent="dragging = true" @dragover.prevent @dragleave.prevent="dragging = false" @drop.prevent="dropFiles">
                        <input type="file" accept="application/pdf,.pdf" :multiple="canImportBatch" @change="pickFiles" />
                        <i class="bx bx-cloud-upload"></i><strong>{{ files.length ? selectedFileLabel : 'Arrastra PDF oficiales' }}</strong><span>{{ canImportBatch ? 'Hasta 30 archivos; cada uno se procesa por separado' : 'Un documento por importación' }}</span>
                    </label>
                    <button v-if="files.length" class="btn-upload" :disabled="uploading" @click="upload"><i class="bx" :class="uploading ? 'bx-loader-alt bx-spin' : 'bx-play-circle'"></i>{{ uploading ? 'Cargando…' : 'Iniciar importación' }}</button>
                    <div class="import-list">
                        <button v-for="item in imports" :key="item.id" :class="{ active: selectedImport?.id === item.id }" @click="openImport(item)">
                            <span class="file-icon"><i class="bx bxs-file-pdf"></i></span><span><strong>{{ item.name }}</strong><small>{{ statusLabel(item.status) }} · {{ item.progress || 0 }}%</small></span><span class="status-dot" :class="`tone-${statusTone(item.status)}`"></span>
                        </button>
                    </div>
                </aside>
                <main class="review-workspace card-panel">
                    <LibroDigitalStatePanel v-if="!selectedImport" icon="bx-scan" title="Selecciona una importación" message="Aquí podrás revisar clasificación, candidatos, conflictos y evidencia antes de publicar." />
                    <template v-else>
                        <header class="review-header">
                            <div><span class="status-pill" :class="`tone-${statusTone(selectedImport.status)}`">{{ statusLabel(selectedImport.status) }}</span><h3>{{ selectedImport.name }}</h3><p>{{ selectedImport.document?.subject || 'Asignatura por confirmar' }} · {{ selectedImport.document?.grade || 'Nivel por confirmar' }}</p></div>
                            <div class="review-actions"><button v-if="canReprocess && selectedImport.status === 'failed'" class="btn-secondary" :disabled="actionBusy" @click="importAction('reprocess')">Reprocesar</button><button v-if="canReview && selectedImport.status === 'pending_review'" class="btn-secondary" :disabled="actionBusy" @click="importAction('validate')">Validar</button><button v-if="canPublish && selectedImport.status === 'validated'" class="btn-publish" :disabled="actionBusy" @click="importAction('publish')"><i class="bx bx-check-shield"></i>Publicar</button></div>
                        </header>
                        <div class="progress-track"><span :style="{ width: `${selectedImport.progress || 0}%` }"></span></div>
                        <div class="review-summary">
                            <div><span>Clasificación</span><strong>{{ humanize(selectedImport.classification?.document_type || selectedImport.document?.type) }}</strong></div><div><span>Páginas</span><strong>{{ selectedImport.document?.page_count || '—' }}</strong></div><div><span>Candidatos</span><strong>{{ selectedImport.candidates?.length || 0 }}</strong></div><div><span>Alertas</span><strong>{{ selectedImport.warnings_count || 0 }}</strong></div>
                        </div>
                        <div v-if="selectedImport.error_summary" class="alert-card danger"><i class="bx bx-error-circle"></i><div><strong>El procesamiento se detuvo</strong><p>{{ selectedImport.error_summary }}</p></div></div>
                        <div v-if="selectedImport.conflicts?.length" class="review-section">
                            <div class="section-title"><div><span class="panel-kicker">Control de integridad</span><h4>Conflictos detectados</h4></div></div>
                            <article v-for="conflict in selectedImport.conflicts" :key="conflict.id" class="conflict-card" :class="conflict.severity"><i class="bx bx-error"></i><div><strong>{{ conflict.title }}</strong><p>{{ conflict.description }}</p><small>{{ humanize(conflict.type) }} · {{ humanize(conflict.status) }}</small></div><button v-if="canResolve && conflict.status === 'open'" :disabled="actionBusy" @click="resolveConflict(conflict)">Resolver</button><span v-else-if="conflict.status !== 'open'" class="resolved"><i class="bx bx-check"></i>Resuelto</span></article>
                        </div>
                        <div class="review-section">
                            <div class="section-title"><div><span class="panel-kicker">Staging verificable</span><h4>Candidatos extraídos</h4></div><span>{{ selectedImport.candidates?.length || 0 }} grupos</span></div>
                            <article v-for="candidate in selectedImport.candidates" :key="candidate.id" class="candidate-card">
                                <div class="candidate-main"><span class="entity-type">{{ humanize(candidate.type) }}</span><div><strong>{{ candidate.key || candidate.value }}</strong><p>{{ candidate.value }}</p><small>Página {{ candidate.printed_page || candidate.physical_page || '—' }} · {{ candidate.source_excerpt }}</small></div></div>
                                <div class="candidate-review"><span class="confidence" :class="`tone-${confidenceTone(candidate.confidence)}`">{{ Math.round(candidate.confidence * 100) }}%</span><span class="review-state">{{ humanize(candidate.review_status) }}</span><div v-if="canReview && candidate.review_status === 'pending'" class="candidate-actions"><button title="Aceptar" :disabled="actionBusy" @click="reviewCandidate(candidate, 'accepted')"><i class="bx bx-check"></i></button><button title="Omitir" :disabled="actionBusy" @click="reviewCandidate(candidate, 'skipped')"><i class="bx bx-x"></i></button></div></div>
                                <div v-if="candidate.children?.length" class="candidate-children">
                                    <div v-for="child in candidate.children" :key="child.id" class="child-candidate">
                                        <span class="entity-type">{{ humanize(child.type) }}</span>
                                        <div><strong>{{ child.value }}</strong><small>Página {{ child.physical_page || '—' }}<template v-if="child.warnings?.length"> · requiere revisión de extracción</template></small></div>
                                        <span class="confidence" :class="`tone-${confidenceTone(child.confidence)}`">{{ Math.round(child.confidence * 100) }}%</span>
                                        <span class="review-state">{{ humanize(child.review_status) }}</span>
                                        <div v-if="canReview && child.review_status === 'pending'" class="candidate-actions"><button title="Aceptar extracto" :disabled="actionBusy" @click="reviewCandidate(child, 'accepted')"><i class="bx bx-check"></i></button><button title="Omitir extracto" :disabled="actionBusy" @click="reviewCandidate(child, 'skipped')"><i class="bx bx-x"></i></button></div>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <div v-if="selectedImport.logs?.length" class="review-section"><div class="section-title"><div><span class="panel-kicker">Auditoría técnica</span><h4>Bitácora del proceso</h4></div></div><ol class="process-log"><li v-for="(log, index) in selectedImport.logs" :key="index"><span></span><div><strong>{{ humanize(log.stage) }} · {{ log.progress }}%</strong><p>{{ log.message }}</p><small>{{ formatDateTime(log.at) }}</small></div></li></ol></div>
                        <div v-if="canViewDocuments && currentDocument" class="review-section"><div class="section-title"><div><span class="panel-kicker">Comparación con fuente</span><h4>Documento original</h4></div><a :href="libroDigitalApi.curriculumDocumentUrl(currentDocument.id)" target="_blank" rel="noopener">Abrir en pestaña nueva <i class="bx bx-link-external"></i></a></div><iframe class="pdf-frame" :src="libroDigitalApi.curriculumDocumentUrl(currentDocument.id)" title="Documento curricular ministerial"></iframe></div>
                    </template>
                </main>
            </div>

            <ManualCurriculumProgramForm
                v-else-if="activeView === 'manual'"
                :context="context"
                :matrix="matrix"
                :capabilities="capabilities"
                @created="manualProgramCreated"
            />

            <div v-else class="search-view card-panel">
                <div class="search-hero"><span class="panel-kicker">Índice transversal</span><h3>Busca en todo el programa curricular</h3><p>Encuentra OA, unidades, conocimientos, habilidades y otros elementos sin perder la referencia de página.</p><form @submit.prevent="search"><i class="bx bx-search"></i><input v-model="searchTerm" type="search" placeholder="Ej.: observar, ecosistema, experimentar, OA 6…" /><button :disabled="searching || searchTerm.trim().length < 2">{{ searching ? 'Buscando…' : 'Buscar' }}</button></form></div>
                <div v-if="searchResults.length" class="search-results"><button v-for="(result, index) in searchResults" :key="`${result.program_id}-${index}`" @click="openProgram({ id: result.program_id })"><span class="result-type">{{ result.type }}</span><div><small>{{ result.subject }} · {{ result.grade_code }} · pág. {{ result.source_page || '—' }}</small><strong>{{ result.title }}</strong><p>{{ result.text }}</p></div><i class="bx bx-right-arrow-alt"></i></button></div>
                <LibroDigitalStatePanel v-else-if="searchTerm && !searching" icon="bx-search-alt" title="Sin coincidencias" message="Prueba con otro término o amplía los filtros de asignatura y nivel." />
            </div>
        </template>
    </section>
</template>

<style scoped>
.curriculum-programs{--ink:#17233c;--brand:#405189;--teal:#0ab39c;--line:#e7ebf2;color:var(--ink)}
.catalog-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:2rem 2.25rem;border-radius:22px;background:radial-gradient(circle at 88% 15%,rgba(80,165,241,.25),transparent 30%),linear-gradient(125deg,#202d4f,#405189 62%,#5667a3);color:#fff;box-shadow:0 18px 45px rgba(40,53,91,.2)}
.catalog-hero h3{font-size:1.8rem;margin:.45rem 0}.catalog-hero p{max-width:780px;margin:0;color:rgba(255,255,255,.78);line-height:1.6}.eyebrow,.panel-kicker{font-size:.72rem;text-transform:uppercase;letter-spacing:.13em;font-weight:800}.eyebrow{color:#9ee9df}.eyebrow i{font-size:1rem;vertical-align:-2px}.hero-metric{min-width:180px;padding:1.2rem 1.4rem;border:1px solid rgba(255,255,255,.18);border-radius:18px;background:rgba(255,255,255,.1);backdrop-filter:blur(8px);text-align:center}.hero-metric strong{display:block;font-size:2rem}.hero-metric span{font-size:.75rem;color:rgba(255,255,255,.72)}
.view-tabs{display:flex;gap:.4rem;margin:1.25rem 0;padding:.4rem;background:#f1f3f8;border-radius:14px;width:max-content;max-width:100%;overflow:auto}.view-tabs button{position:relative;border:0;background:transparent;padding:.72rem 1rem;border-radius:10px;color:#68728a;font-weight:700;white-space:nowrap}.view-tabs button.active{background:#fff;color:var(--brand);box-shadow:0 4px 14px rgba(38,51,87,.1)}.view-tabs i{font-size:1.05rem;vertical-align:-2px;margin-right:.35rem}.tab-dot{position:absolute;top:.45rem;right:.45rem;width:7px;height:7px;border-radius:50%;background:#f06548}
.card-panel{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 24px rgba(32,44,75,.055)}.view-grid{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:1rem}.coverage-panel,.chart-panel,.program-list-panel,.search-view{padding:1.35rem}.panel-heading,.section-title{display:flex;align-items:center;justify-content:space-between;gap:1rem}.panel-heading h4,.section-title h4,.chart-panel h4{margin:.25rem 0 0;font-size:1.05rem}.panel-kicker{color:#8390a8}.btn-icon{width:38px;height:38px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--brand);font-size:1.2rem}.filters-row{display:grid;grid-template-columns:repeat(3,minmax(150px,1fr));gap:.65rem;margin:1.15rem 0}.filters-row .form-select{font-size:.82rem;border-color:var(--line);border-radius:10px}.matrix-scroll{overflow:auto;max-height:620px}.coverage-matrix{border-collapse:separate;border-spacing:0;width:max-content;min-width:100%;font-size:.78rem}.coverage-matrix th,.coverage-matrix td{padding:.48rem;border-bottom:1px solid #eef1f6}.coverage-matrix thead th{position:sticky;top:0;z-index:2;background:#f8f9fc;min-width:135px;text-align:center}.coverage-matrix thead th:first-child{left:0;z-index:4;text-align:left;min-width:210px}.coverage-matrix tbody th{position:sticky;left:0;z-index:1;background:#fff;text-align:left;display:grid;grid-template-columns:8px 1fr;column-gap:.55rem;min-width:210px}.coverage-matrix th small{display:block;color:#8a95a8;font-weight:500}.coverage-matrix tbody th small{grid-column:2}.subject-mark{width:7px;height:32px;border-radius:10px;grid-row:1/3}.status-cell{width:100%;min-width:122px;min-height:58px;border:1px solid var(--line);border-radius:10px;background:#fff;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;padding:.5rem .65rem;text-align:left;transition:.2s}.status-cell:not(:disabled):hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(32,44,75,.1)}.status-cell i{font-size:1rem}.status-cell span{font-weight:700}.status-cell small{color:#8b94a7}.dimmed{opacity:.28}.tone-success{color:#078875!important;background:#e6f7f4!important;border-color:#bdeae3!important}.tone-warning{color:#a66a08!important;background:#fff7e6!important;border-color:#f7dba5!important}.tone-danger{color:#c13d55!important;background:#fff0f2!important;border-color:#f5c3cb!important}.tone-primary{color:#405189!important;background:#eef0f8!important;border-color:#cdd4eb!important}.tone-info{color:#2878aa!important;background:#eaf6fc!important;border-color:#c4e5f5!important}.tone-dark{color:#263042!important;background:#edf0f4!important}.tone-muted{color:#8992a3!important;background:#f8f9fb!important}.chart-panel{padding:1.4rem}.integrity-note{display:flex;gap:.8rem;padding:1rem;border-radius:12px;background:#f5f7fb}.integrity-note i{font-size:1.4rem;color:var(--brand)}.integrity-note p{font-size:.78rem;margin:0;color:#69738a;line-height:1.45}
.program-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.8rem;margin-top:1rem}.program-card{display:grid;grid-template-columns:46px 1fr auto;align-items:center;gap:.7rem;text-align:left;border:1px solid var(--line);background:#fff;border-radius:14px;padding:1rem;transition:.2s}.program-card:not(:disabled):hover{border-color:#bbc5e0;transform:translateY(-2px);box-shadow:0 10px 24px rgba(32,44,75,.08)}.program-card strong,.program-card span:nth-child(3){grid-column:2}.program-card span:nth-child(3){font-size:.75rem;color:#818b9f}.program-icon{grid-row:1/3;width:46px;height:46px;border-radius:12px;display:grid;place-items:center;font-size:1.3rem}.program-card>.bx{grid-column:3;grid-row:1/3;font-size:1.25rem}.detail-header{display:grid;grid-template-columns:auto 1fr auto;gap:1.25rem;align-items:start;padding:1.4rem;border-radius:18px;background:linear-gradient(145deg,#f8f9fc,#fff);border:1px solid var(--line)}.back-button{border:0;background:transparent;color:var(--brand);font-weight:700}.detail-title h3{font-size:1.45rem;margin:.5rem 0 .2rem}.detail-title p{margin:0;color:#758097}.status-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .55rem;border-radius:999px;font-size:.7rem;font-weight:800;border:1px solid}.btn-primary-soft,.btn-secondary,.btn-publish,.btn-upload{border:0;border-radius:10px;padding:.7rem 1rem;font-weight:700}.btn-primary-soft{background:#edf0f8;color:var(--brand)}.btn-publish,.btn-upload{background:var(--brand);color:#fff}.btn-secondary{background:#f1f3f7;color:#515d73}.metric-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:15px;overflow:hidden;margin:1rem 0}.metric-strip div{padding:1rem;background:#fff;text-align:center}.metric-strip strong{display:block;font-size:1.4rem;color:var(--brand)}.metric-strip span{font-size:.72rem;color:#7d879a}.annual-map{padding:1.25rem}.unit-timeline{display:flex;gap:.45rem;margin-top:1rem;overflow:auto}.timeline-unit{min-width:160px;display:flex;flex-direction:column;gap:.25rem;padding:1rem;border-radius:12px;background:linear-gradient(135deg,#eef1f8,#f9faff);border-left:4px solid var(--brand)}.timeline-unit span,.timeline-unit small{font-size:.7rem;color:#7b8599}.timeline-unit strong{font-size:.82rem}.detail-charts{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin:1rem 0}.detail-charts>.card-panel{padding:1.2rem}.units-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;gap:1rem}.unit-card{display:grid;grid-template-columns:54px minmax(0,1fr);align-self:start;gap:1rem;padding:1.25rem}.unit-number{width:54px;height:54px;border-radius:14px;background:#edf0f8;color:var(--brand);display:grid;place-items:center;font-weight:800}.unit-card h4{margin:.25rem 0}.unit-card p{color:#667187;line-height:1.55;font-size:.82rem}.unit-tags,.unit-card details{grid-column:2}.unit-tags{display:flex;flex-wrap:wrap;gap:.3rem}.unit-tags span{padding:.25rem .5rem;background:#e7f8f5;color:#087e6d;border-radius:999px;font-size:.68rem;font-weight:800}.unit-card summary{cursor:pointer;color:var(--brand);font-size:.78rem;font-weight:700}.source-panel{padding:1.25rem;margin-top:1rem}.source-panel p{font-size:.78rem;color:#7d8799;margin:1rem 0 0;word-break:break-word}
.imports-layout{display:grid;grid-template-columns:310px minmax(0,1fr);gap:1rem}.imports-sidebar,.review-workspace{padding:1.15rem}.count-badge{padding:.25rem .5rem;border-radius:999px;background:#edf0f8;color:var(--brand);font-weight:800;font-size:.72rem}.drop-zone{margin-top:1rem;min-height:145px;border:1.5px dashed #bac4dc;border-radius:14px;background:#f8f9fc;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:1rem;cursor:pointer;transition:.2s}.drop-zone.dragging{border-color:var(--teal);background:#effbf9}.drop-zone input{display:none}.drop-zone i{font-size:2rem;color:var(--brand)}.drop-zone strong{margin:.45rem 0 .2rem}.drop-zone span{font-size:.72rem;color:#7b8598}.btn-upload{width:100%;margin-top:.65rem}.import-list{display:flex;flex-direction:column;gap:.35rem;margin-top:1rem;max-height:570px;overflow:auto}.import-list button{display:grid;grid-template-columns:38px 1fr 10px;gap:.6rem;align-items:center;text-align:left;border:1px solid transparent;background:#fff;border-radius:11px;padding:.65rem}.import-list button.active{background:#f1f4fb;border-color:#d5dbec}.file-icon{width:36px;height:36px;border-radius:10px;background:#fff0f2;color:#d34a60;display:grid;place-items:center;font-size:1.2rem}.import-list strong,.import-list small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.import-list strong{font-size:.78rem;max-width:190px}.import-list small{font-size:.68rem;color:#818b9d}.status-dot{width:8px;height:8px;border-radius:50%;padding:0!important}.review-header{display:flex;justify-content:space-between;gap:1rem;padding-bottom:1rem;border-bottom:1px solid var(--line)}.review-header h3{margin:.5rem 0 .2rem;font-size:1.25rem}.review-header p{margin:0;color:#7b8598}.review-actions{display:flex;align-items:flex-start;gap:.5rem}.progress-track{height:6px;background:#edf0f4;border-radius:10px;margin:1rem 0;overflow:hidden}.progress-track span{display:block;height:100%;border-radius:10px;background:linear-gradient(90deg,var(--brand),var(--teal));transition:width .3s}.review-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem}.review-summary div{padding:.8rem;background:#f8f9fc;border-radius:10px}.review-summary span,.review-summary strong{display:block}.review-summary span{font-size:.67rem;color:#7d8799;text-transform:uppercase;letter-spacing:.08em}.review-summary strong{font-size:.82rem;margin-top:.25rem}.review-section{margin-top:1.5rem}.conflict-card,.candidate-card{display:flex;align-items:flex-start;gap:.8rem;border:1px solid var(--line);border-radius:12px;padding:.85rem;margin-top:.55rem}.conflict-card.critical{border-color:#efb7c0;background:#fff8f9}.conflict-card>.bx{font-size:1.2rem;color:#d34a60}.conflict-card div{flex:1}.conflict-card p,.candidate-card p{font-size:.78rem;color:#677287;margin:.25rem 0}.conflict-card small,.candidate-card small{font-size:.68rem;color:#929bae}.conflict-card button{border:0;background:#fff0f2;color:#c53f56;border-radius:8px;padding:.45rem .65rem;font-weight:700}.resolved{color:#078875;font-weight:700;font-size:.72rem}.candidate-card{justify-content:space-between;flex-wrap:wrap}.candidate-main{display:flex;gap:.75rem;min-width:0;flex:1}.candidate-main>div{min-width:0}.candidate-main p{white-space:pre-wrap;max-height:80px;overflow:auto}.entity-type{flex:0 0 auto;height:max-content;padding:.25rem .45rem;border-radius:6px;background:#edf0f8;color:var(--brand);font-size:.65rem;font-weight:800}.candidate-review{display:flex;align-items:center;gap:.5rem;flex:0 0 auto}.confidence,.review-state{padding:.25rem .4rem;border-radius:6px;font-size:.66rem;font-weight:800}.review-state{background:#f3f4f7;color:#697489}.candidate-actions{display:flex;gap:.25rem}.candidate-actions button{width:30px;height:30px;border:1px solid var(--line);background:#fff;border-radius:7px;color:var(--brand)}.candidate-children{flex:1 0 100%;display:flex;flex-direction:column;gap:.35rem;padding:.65rem 0 0 1.25rem;border-top:1px dashed var(--line)}.child-candidate{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto auto;align-items:center;gap:.55rem;padding:.55rem .65rem;border-radius:9px;background:#f8f9fc}.child-candidate strong,.child-candidate small{display:block}.child-candidate strong{font-size:.73rem;color:#4c576d}.alert-card{display:flex;gap:.7rem;margin-top:1rem;padding:1rem;border-radius:12px}.alert-card.danger{background:#fff1f3;color:#b83d51}.alert-card p{margin:.2rem 0 0}.process-log{list-style:none;padding:0;margin:1rem 0}.process-log li{display:grid;grid-template-columns:12px 1fr;gap:.7rem;position:relative;padding-bottom:1rem}.process-log li>span{width:10px;height:10px;border-radius:50%;background:var(--teal);margin-top:.25rem;box-shadow:0 0 0 4px #e7f8f5}.process-log li:not(:last-child):before{content:"";position:absolute;left:4px;top:15px;bottom:0;width:1px;background:#dce2ec}.process-log p{margin:.15rem 0;font-size:.77rem;color:#697489}.process-log small{color:#9aa2b1}.pdf-frame{width:100%;height:620px;border:1px solid var(--line);border-radius:12px;background:#f7f8fa}
.search-view{min-height:570px}.search-hero{text-align:center;max-width:780px;margin:1.5rem auto}.search-hero h3{font-size:1.5rem;margin:.5rem}.search-hero p{color:#727d92}.search-hero form{display:flex;align-items:center;gap:.65rem;margin-top:1.4rem;padding:.45rem .45rem .45rem 1rem;border:1px solid #d6dcea;border-radius:14px;box-shadow:0 8px 25px rgba(40,52,86,.08)}.search-hero form i{font-size:1.25rem;color:#8791a4}.search-hero input{flex:1;border:0;outline:0;min-width:0}.search-hero button{border:0;border-radius:9px;background:var(--brand);color:#fff;padding:.7rem 1.2rem;font-weight:700}.search-results{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.search-results button{display:grid;grid-template-columns:auto 1fr auto;gap:.7rem;text-align:left;border:1px solid var(--line);background:#fff;border-radius:12px;padding:1rem}.search-results button:hover{border-color:#b8c3dd;box-shadow:0 8px 20px rgba(35,48,79,.07)}.result-type{height:max-content;background:#eaf8f5;color:#078875;border-radius:7px;padding:.3rem .45rem;font-size:.67rem;font-weight:800}.search-results strong,.search-results small{display:block}.search-results small{color:#8a94a6}.search-results p{font-size:.77rem;color:#657086;margin:.3rem 0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
@media(max-width:1199px){.view-grid,.imports-layout{grid-template-columns:1fr}.chart-panel{display:grid;grid-template-columns:1fr 1fr;align-items:center}.imports-sidebar{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.imports-sidebar>.panel-heading,.imports-sidebar>.import-list{grid-column:1/-1}.btn-upload{align-self:end}.import-list{display:grid;grid-template-columns:repeat(2,1fr)}}
@media(max-width:767px){.catalog-hero{align-items:flex-start;padding:1.4rem}.hero-metric{display:none}.view-tabs{width:100%}.filters-row,.review-summary,.metric-strip{grid-template-columns:1fr 1fr}.coverage-matrix thead th:first-child,.coverage-matrix tbody th{min-width:170px}.detail-header{grid-template-columns:1fr}.detail-charts,.units-grid,.search-results{grid-template-columns:1fr}.imports-sidebar{display:block}.import-list{display:flex}.review-header{flex-direction:column}.candidate-card{flex-direction:column}.candidate-review{width:100%;justify-content:flex-end}.candidate-children{padding-left:0;width:100%}.child-candidate{grid-template-columns:auto 1fr auto}.child-candidate>.review-state,.child-candidate>.candidate-actions{grid-column:2/4;justify-self:end}.pdf-frame{height:430px}.chart-panel{display:block}.unit-timeline{flex-direction:column}.timeline-unit{min-width:100%}}
</style>
