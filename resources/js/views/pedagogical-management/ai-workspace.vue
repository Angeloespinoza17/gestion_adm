<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PedagogicalAiReportContent from "../../components/pedagogical-management/PedagogicalAiReportContent.vue";
import { downloadPedagogicalAiReportPdf } from "../../utils/pedagogical-ai-report-pdf";
import {
    aiWorkspacePolling,
    aiWorkspacePollingDecision,
    errorMessage,
    instrumentFileExtension,
    instrumentFileIcon,
    openBlob,
    pedagogicalManagementApi,
    saveBlob,
    validateInstrumentCandidate,
} from "../../services/pedagogical-management-api";

const loading = ref(true);
const submitting = ref(false);
const detailLoading = ref(false);
const refreshing = ref(false);
const regenerating = ref(false);
const downloading = ref(false);
const selectedFile = ref(null);
const fileInput = ref(null);
const dragActive = ref(false);
const history = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const detail = ref(null);
const pollingActive = ref(false);
const pollingPaused = ref(false);
const pollAttempts = ref(0);
const catalogs = ref({
    school: null,
    academic_year: null,
    subjects: [],
    courses: [],
    max_file_kb: 30720,
    openai_configured: false,
});
const form = reactive({ subject_id: "", course_id: "" });
const alert = reactive({ tone: "", text: "" });
const formError = ref("");
let pollTimer;
let pollInFlight = false;

const aiReport = computed(() => detail.value?.latest_ai_report || null);
const reportPending = computed(() =>
    ["pending", "processing"].includes(aiReport.value?.status)
);
const completedReport = computed(() =>
    aiReport.value?.status === "completed" && aiReport.value?.report
        ? aiReport.value
        : null
);
function notify(tone, text) {
    Object.assign(alert, { tone, text });
    window.setTimeout(() => {
        if (alert.text === text) alert.text = "";
    }, 6500);
}

function formatDate(value) {
    if (!value) return "—";
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

function formatBytes(value) {
    const bytes = Number(value || 0);
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function reportStatus(report) {
    return (
        {
            pending: { label: "En cola", tone: "info" },
            processing: { label: "Analizando", tone: "violet" },
            completed: { label: "Informe listo", tone: "success" },
            failed: { label: "No completado", tone: "danger" },
        }[report?.status] || { label: "Preparando", tone: "neutral" }
    );
}

async function loadCatalogs() {
    catalogs.value = await pedagogicalManagementApi.aiWorkspaceCatalogs();
}

async function loadHistory(page = 1, { quiet = false } = {}) {
    if (!quiet) loading.value = true;
    try {
        const response = await pedagogicalManagementApi.aiWorkspaceList({
            page,
            per_page: 12,
        });
        history.value = response.data || [];
        meta.value = response.meta || meta.value;
    } catch (error) {
        notify(
            "danger",
            errorMessage(
                error,
                "No fue posible cargar tus revisiones anteriores."
            )
        );
    } finally {
        if (!quiet) loading.value = false;
    }
}

function chooseFile(file) {
    const validation = validateInstrumentCandidate(
        file,
        catalogs.value.max_file_kb
    );
    formError.value = validation || "";
    selectedFile.value = validation ? null : file;
    if (validation && fileInput.value) fileInput.value.value = "";
}

function onDrop(event) {
    dragActive.value = false;
    chooseFile(event.dataTransfer.files?.[0]);
}

function clearFile() {
    selectedFile.value = null;
    formError.value = "";
    if (fileInput.value) fileInput.value.value = "";
}

function validateForm() {
    if (!catalogs.value.school?.id)
        return "No se encontró el establecimiento institucional.";
    if (!form.subject_id) return "Selecciona la asignatura del instrumento.";
    if (!form.course_id) return "Selecciona el curso del instrumento.";
    return validateInstrumentCandidate(
        selectedFile.value,
        catalogs.value.max_file_kb
    );
}

async function submitReview() {
    formError.value = validateForm() || "";
    if (formError.value || !catalogs.value.openai_configured) return;
    submitting.value = true;
    stopPolling();
    try {
        const payload = new FormData();
        payload.append("school_id", catalogs.value.school.id);
        payload.append("subject_id", form.subject_id);
        payload.append("course_id", form.course_id);
        payload.append("file", selectedFile.value);
        detail.value = await pedagogicalManagementApi.createAiWorkspaceReview(
            payload
        );
        clearFile();
        notify(
            "success",
            "Revisión iniciada. El instrumento permanece en tu espacio privado."
        );
        await loadHistory(1, { quiet: true });
        startPolling({ reset: true });
    } catch (error) {
        formError.value = errorMessage(
            error,
            "No fue posible iniciar la revisión con IA."
        );
    } finally {
        submitting.value = false;
    }
}

async function openReview(item) {
    stopPolling();
    detailLoading.value = true;
    try {
        detail.value = await pedagogicalManagementApi.aiWorkspaceReview(
            item.id
        );
        if (reportPending.value) startPolling({ reset: true });
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible abrir esta revisión.")
        );
    } finally {
        detailLoading.value = false;
    }
}

function schedulePoll(delay = aiWorkspacePolling.intervalMs) {
    if (!pollingActive.value || pollTimer) return;
    pollTimer = window.setTimeout(runPoll, delay);
}

async function runPoll() {
    pollTimer = null;
    if (!pollingActive.value || pollInFlight || !detail.value) return;
    pollInFlight = true;
    pollAttempts.value += 1;
    let decision = "pause";
    try {
        detail.value = await pedagogicalManagementApi.aiWorkspaceReview(
            detail.value.id
        );
        decision = aiWorkspacePollingDecision({
            status: aiReport.value?.status,
            attempts: pollAttempts.value,
        });
    } catch {
        decision = aiWorkspacePollingDecision({
            status: aiReport.value?.status || "pending",
            attempts: pollAttempts.value,
            requestFailed: true,
        });
    } finally {
        pollInFlight = false;
    }

    if (decision === "complete") {
        stopPolling();
        await loadHistory(meta.value.current_page || 1, { quiet: true });
        if (completedReport.value)
            notify(
                "success",
                "El informe pedagógico con IA ya está disponible."
            );
        return;
    }
    if (decision === "pause") {
        pausePolling();
        return;
    }
    schedulePoll();
}

function startPolling({ reset = false } = {}) {
    if (!detail.value || !reportPending.value) {
        stopPolling();
        return;
    }
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = null;
    pollingActive.value = true;
    pollingPaused.value = false;
    if (reset) pollAttempts.value = 0;
    schedulePoll(2500);
}

function pausePolling() {
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = null;
    pollingActive.value = false;
    pollingPaused.value = true;
}

function stopPolling() {
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = null;
    pollingActive.value = false;
    pollingPaused.value = false;
    pollAttempts.value = 0;
}

async function refreshReview() {
    if (!detail.value || refreshing.value) return;
    refreshing.value = true;
    try {
        detail.value = await pedagogicalManagementApi.aiWorkspaceReview(
            detail.value.id
        );
        if (reportPending.value) startPolling({ reset: true });
        await loadHistory(meta.value.current_page || 1, { quiet: true });
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible actualizar el informe.")
        );
    } finally {
        refreshing.value = false;
    }
}

async function regenerateReport() {
    if (!detail.value || regenerating.value) return;
    regenerating.value = true;
    try {
        detail.value =
            await pedagogicalManagementApi.regenerateAiWorkspaceReview(
                detail.value.id
            );
        notify("info", "Se inició una nueva revisión de la versión actual.");
        startPolling({ reset: true });
        await loadHistory(meta.value.current_page || 1, { quiet: true });
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible volver a generar el informe.")
        );
    } finally {
        regenerating.value = false;
    }
}

async function accessFile() {
    const file = detail.value?.latest_file;
    if (!file) return;
    const shouldDownload =
        instrumentFileExtension(file.original_filename) !== "pdf";
    try {
        const blob = shouldDownload
            ? await pedagogicalManagementApi.downloadFile(
                  detail.value.id,
                  file.id
              )
            : await pedagogicalManagementApi.viewFile(detail.value.id, file.id);
        if (shouldDownload) saveBlob(blob, file.original_filename);
        else openBlob(blob);
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible abrir el archivo privado.")
        );
    }
}

async function downloadReport() {
    if (!completedReport.value || !detail.value) return;
    downloading.value = true;
    try {
        await downloadPedagogicalAiReportPdf(
            detail.value,
            completedReport.value
        );
        notify("success", "Informe descargado en PDF.");
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible descargar el informe.")
        );
    } finally {
        downloading.value = false;
    }
}

async function initialize() {
    loading.value = true;
    try {
        await loadCatalogs();
        await loadHistory();
    } catch (error) {
        notify(
            "danger",
            errorMessage(
                error,
                "No fue posible iniciar la herramienta de revisión."
            )
        );
        loading.value = false;
    }
}

onMounted(initialize);
onBeforeUnmount(stopPolling);
</script>

<template>
    <Layout>
        <div class="ai-tool-page container-fluid px-0">
            <div
                v-if="alert.text"
                class="tool-toast"
                :class="`tool-toast--${alert.tone}`"
                role="status"
            >
                <i
                    class="bx"
                    :class="
                        alert.tone === 'success'
                            ? 'bx-check-circle'
                            : 'bx-info-circle'
                    "
                ></i>
                <span>{{ alert.text }}</span>
                <button
                    type="button"
                    aria-label="Cerrar mensaje"
                    @click="alert.text = ''"
                >
                    ×
                </button>
            </div>

            <header class="tool-hero">
                <div class="hero-copy">
                    <span class="hero-eyebrow"
                        ><i class="bx bx-sparkles"></i> Gestión pedagógica</span
                    >
                    <h1>Revisión de instrumentos con IA</h1>
                    <p>
                        Trabaja directamente aquí: carga el instrumento, recibe
                        un informe pedagógico completo y descárgalo cuando lo
                        necesites.
                    </p>
                    <div class="hero-assurances">
                        <span
                            ><i class="bx bx-lock-alt"></i> Espacio
                            privado</span
                        >
                        <span
                            ><i class="bx bx-send"></i> Sin envío al
                            docente</span
                        >
                        <span
                            ><i class="bx bx-history"></i> Historial
                            personal</span
                        >
                    </div>
                </div>
                <div class="hero-orbit" aria-hidden="true">
                    <span><i class="bx bx-file-find"></i></span>
                </div>
            </header>

            <div
                v-if="!catalogs.openai_configured && !loading"
                class="configuration-alert"
            >
                <i class="bx bx-error-circle"></i>
                <div>
                    <strong>La revisión con IA no está disponible</strong>
                    <span
                        >La integración debe estar configurada antes de cargar
                        instrumentos.</span
                    >
                </div>
            </div>

            <LoadingState
                v-if="loading"
                message="Preparando tu herramienta de revisión..."
            />

            <template v-else>
                <main class="tool-workspace">
                    <section class="tool-card creation-card">
                        <div class="card-heading">
                            <span class="section-kicker">NUEVA REVISIÓN</span>
                            <h2>Instrumento a revisar</h2>
                            <p>
                                No debes crear una solicitud ni asociarla a un
                                docente.
                            </p>
                        </div>

                        <form @submit.prevent="submitReview">
                            <div class="context-chip">
                                <span
                                    ><i class="bx bx-building-house"></i>
                                    Establecimiento</span
                                >
                                <strong>{{ catalogs.school?.name }}</strong>
                                <small>{{
                                    catalogs.academic_year?.name ||
                                    catalogs.academic_year?.year
                                }}</small>
                            </div>

                            <label class="tool-field">
                                <span><b>1</b> Asignatura</span>
                                <select
                                    v-model="form.subject_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="" disabled>
                                        Selecciona la asignatura
                                    </option>
                                    <option
                                        v-for="subject in catalogs.subjects"
                                        :key="subject.id"
                                        :value="String(subject.id)"
                                    >
                                        {{ subject.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="tool-field">
                                <span><b>2</b> Curso</span>
                                <select
                                    v-model="form.course_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="" disabled>
                                        Selecciona el curso
                                    </option>
                                    <option
                                        v-for="course in catalogs.courses"
                                        :key="course.id"
                                        :value="String(course.id)"
                                    >
                                        {{ course.name }}
                                    </option>
                                </select>
                            </label>

                            <div class="tool-field">
                                <span><b>3</b> Archivo del instrumento</span>
                                <label
                                    class="drop-zone"
                                    :class="{
                                        active: dragActive,
                                        ready: selectedFile,
                                    }"
                                    @dragenter.prevent="dragActive = true"
                                    @dragover.prevent="dragActive = true"
                                    @dragleave.prevent="dragActive = false"
                                    @drop.prevent="onDrop"
                                >
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                        @change="
                                            chooseFile($event.target.files?.[0])
                                        "
                                    />
                                    <span class="drop-icon">
                                        <i
                                            class="bx"
                                            :class="
                                                selectedFile
                                                    ? instrumentFileIcon(
                                                          selectedFile
                                                      )
                                                    : 'bx-cloud-upload'
                                            "
                                        ></i>
                                    </span>
                                    <template v-if="selectedFile">
                                        <strong>{{ selectedFile.name }}</strong>
                                        <small
                                            >{{
                                                formatBytes(selectedFile.size)
                                            }}
                                            · listo para revisar</small
                                        >
                                    </template>
                                    <template v-else>
                                        <strong
                                            >Arrastra el archivo o
                                            selecciónalo</strong
                                        >
                                        <small
                                            >PDF o Word (.docx) · máximo
                                            {{
                                                Math.round(
                                                    catalogs.max_file_kb / 1024
                                                )
                                            }}
                                            MB</small
                                        >
                                    </template>
                                </label>
                                <button
                                    v-if="selectedFile"
                                    type="button"
                                    class="clear-file"
                                    @click="clearFile"
                                >
                                    <i class="bx bx-x"></i> Quitar archivo
                                </button>
                            </div>

                            <div v-if="formError" class="form-error">
                                <i class="bx bx-error-circle"></i
                                >{{ formError }}
                            </div>

                            <button
                                type="submit"
                                class="review-button"
                                :disabled="
                                    submitting || !catalogs.openai_configured
                                "
                            >
                                <span
                                    v-if="submitting"
                                    class="spinner-border spinner-border-sm"
                                ></span>
                                <i v-else class="bx bx-sparkles"></i>
                                {{
                                    submitting
                                        ? "Preparando revisión..."
                                        : "Revisar ahora con IA"
                                }}
                            </button>
                            <p class="privacy-note">
                                <i class="bx bx-shield-quarter"></i>
                                El archivo se conserva en almacenamiento privado
                                y no entra a Revisión documental.
                            </p>
                        </form>
                    </section>

                    <section class="tool-card result-card" aria-live="polite">
                        <LoadingState
                            v-if="detailLoading"
                            message="Abriendo informe..."
                        />

                        <div v-else-if="!detail" class="result-empty">
                            <span><i class="bx bx-file-find"></i></span>
                            <h2>Tu informe aparecerá aquí</h2>
                            <p>
                                La IA aplicará la pauta institucional,
                                identificará fortalezas, observaciones y
                                ejemplos concretos de mejora.
                            </p>
                            <div class="empty-flow">
                                <span><b>1</b> Carga</span
                                ><i class="bx bx-right-arrow-alt"></i>
                                <span><b>2</b> Revisión IA</span
                                ><i class="bx bx-right-arrow-alt"></i>
                                <span><b>3</b> Informe</span>
                            </div>
                        </div>

                        <template v-else>
                            <header class="result-heading">
                                <div class="result-title">
                                    <span
                                        class="file-mark"
                                        :class="{
                                            word:
                                                instrumentFileExtension(
                                                    detail.latest_file
                                                        ?.original_filename
                                                ) === 'docx',
                                        }"
                                    >
                                        <i
                                            class="bx"
                                            :class="
                                                instrumentFileIcon(
                                                    detail.latest_file
                                                        ?.original_filename
                                                )
                                            "
                                        ></i>
                                    </span>
                                    <div>
                                        <span
                                            class="report-status"
                                            :class="`tone-${
                                                reportStatus(aiReport).tone
                                            }`"
                                        >
                                            <i
                                                class="bx"
                                                :class="
                                                    reportPending
                                                        ? 'bx-loader-alt bx-spin'
                                                        : 'bx-check-circle'
                                                "
                                            ></i>
                                            {{ reportStatus(aiReport).label }}
                                        </span>
                                        <h2>{{ detail.title }}</h2>
                                        <p>
                                            {{ detail.subject?.name }} ·
                                            {{
                                                detail.courses
                                                    ?.map(
                                                        (course) => course.name
                                                    )
                                                    .join(", ")
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div class="result-actions">
                                    <button
                                        type="button"
                                        class="btn btn-light"
                                        @click="accessFile"
                                    >
                                        <i
                                            class="bx"
                                            :class="
                                                instrumentFileExtension(
                                                    detail.latest_file
                                                        ?.original_filename
                                                ) === 'pdf'
                                                    ? 'bx-show'
                                                    : 'bx-download'
                                            "
                                        ></i>
                                        {{
                                            instrumentFileExtension(
                                                detail.latest_file
                                                    ?.original_filename
                                            ) === "pdf"
                                                ? "Ver archivo"
                                                : "Descargar Word"
                                        }}
                                    </button>
                                    <button
                                        v-if="completedReport"
                                        type="button"
                                        class="btn download-button"
                                        :disabled="downloading"
                                        @click="downloadReport"
                                    >
                                        <span
                                            v-if="downloading"
                                            class="spinner-border spinner-border-sm"
                                        ></span>
                                        <i v-else class="bx bx-download"></i>
                                        Descargar PDF
                                    </button>
                                </div>
                            </header>

                            <div v-if="reportPending" class="processing-state">
                                <div class="processing-visual">
                                    <span class="document-sheet"
                                        ><i class="bx bx-file"></i
                                    ></span>
                                    <span class="scan-line"></span>
                                    <span class="spark spark-one"
                                        ><i class="bx bxs-star"></i
                                    ></span>
                                    <span class="spark spark-two"
                                        ><i class="bx bxs-star"></i
                                    ></span>
                                </div>
                                <div>
                                    <span class="section-kicker"
                                        >ANÁLISIS EN CURSO</span
                                    >
                                    <h3>
                                        Revisando la pauta institucional y la
                                        coherencia del instrumento
                                    </h3>
                                    <p>
                                        Puedes permanecer en esta pantalla o
                                        volver después; el proceso queda
                                        guardado.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="pollingPaused && reportPending"
                                class="paused-state"
                            >
                                <i class="bx bx-time-five"></i>
                                <div>
                                    <strong
                                        >La revisión continúa en segundo
                                        plano</strong
                                    ><span
                                        >La actualización automática se pausó
                                        para no dejar la pantalla esperando
                                        indefinidamente.</span
                                    >
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    :disabled="refreshing"
                                    @click="refreshReview"
                                >
                                    Actualizar estado
                                </button>
                            </div>

                            <div
                                v-if="aiReport?.status === 'failed'"
                                class="failed-state"
                            >
                                <i class="bx bx-error-circle"></i>
                                <div>
                                    <strong
                                        >No fue posible completar esta
                                        revisión</strong
                                    ><span>{{
                                        aiReport.error_message ||
                                        "Puedes volver a intentarlo sobre el mismo archivo."
                                    }}</span>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger"
                                    :disabled="regenerating"
                                    @click="regenerateReport"
                                >
                                    Intentar nuevamente
                                </button>
                            </div>

                            <div v-if="completedReport" class="report-toolbar">
                                <div>
                                    <span class="section-kicker"
                                        >INFORME COMPLETO</span
                                    >
                                    <p>
                                        Generado
                                        {{
                                            formatDate(
                                                completedReport.finished_at ||
                                                    completedReport.created_at
                                            )
                                        }}
                                        · apoyo profesional, no resolución
                                        automática.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="regenerate-link"
                                    :disabled="regenerating"
                                    @click="regenerateReport"
                                >
                                    <i class="bx bx-refresh"></i
                                    >{{
                                        regenerating
                                            ? "Iniciando..."
                                            : "Generar nuevamente"
                                    }}
                                </button>
                            </div>

                            <PedagogicalAiReportContent
                                v-if="completedReport"
                                class="embedded-report"
                                :report="completedReport.report"
                            />
                        </template>
                    </section>
                </main>

                <section class="history-section">
                    <div class="history-heading">
                        <div>
                            <span class="section-kicker"
                                >TU ESPACIO PRIVADO</span
                            >
                            <h2>Revisiones anteriores</h2>
                            <p>
                                Solo tú puedes consultar los instrumentos
                                cargados desde esta herramienta.
                            </p>
                        </div>
                        <span class="history-count"
                            >{{ meta.total }}
                            {{
                                meta.total === 1 ? "revisión" : "revisiones"
                            }}</span
                        >
                    </div>

                    <div v-if="history.length" class="history-grid">
                        <button
                            v-for="item in history"
                            :key="item.id"
                            type="button"
                            class="history-card"
                            :class="{ selected: detail?.id === item.id }"
                            @click="openReview(item)"
                        >
                            <span
                                class="history-file"
                                :class="{
                                    word:
                                        instrumentFileExtension(
                                            item.latest_file?.original_filename
                                        ) === 'docx',
                                }"
                            >
                                <i
                                    class="bx"
                                    :class="
                                        instrumentFileIcon(
                                            item.latest_file?.original_filename
                                        )
                                    "
                                ></i>
                            </span>
                            <span class="history-content">
                                <span class="history-topline">
                                    <b
                                        :class="`tone-${
                                            reportStatus(item.latest_ai_report)
                                                .tone
                                        }`"
                                        >{{
                                            reportStatus(item.latest_ai_report)
                                                .label
                                        }}</b
                                    >
                                    <small>{{
                                        formatDate(item.created_at)
                                    }}</small>
                                </span>
                                <strong>{{ item.title }}</strong>
                                <small
                                    >{{ item.subject?.name }} ·
                                    {{
                                        item.courses
                                            ?.map((course) => course.name)
                                            .join(", ")
                                    }}</small
                                >
                            </span>
                            <i class="bx bx-chevron-right history-arrow"></i>
                        </button>
                    </div>

                    <div v-else class="history-empty">
                        <i class="bx bx-history"></i>
                        <span
                            >Aún no has realizado revisiones desde esta
                            herramienta.</span
                        >
                    </div>

                    <nav
                        v-if="meta.last_page > 1"
                        class="pagination-tools"
                        aria-label="Paginación de revisiones"
                    >
                        <button
                            type="button"
                            :disabled="meta.current_page <= 1"
                            @click="loadHistory(meta.current_page - 1)"
                        >
                            <i class="bx bx-chevron-left"></i> Anterior
                        </button>
                        <span
                            >Página {{ meta.current_page }} de
                            {{ meta.last_page }}</span
                        >
                        <button
                            type="button"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="loadHistory(meta.current_page + 1)"
                        >
                            Siguiente <i class="bx bx-chevron-right"></i>
                        </button>
                    </nav>
                </section>
            </template>
        </div>
    </Layout>
</template>

<style scoped>
.ai-tool-page {
    --navy: #153b5b;
    --teal: #12847d;
    --violet: #6554b5;
    --ink: #1d2d43;
    --muted: #718092;
    color: var(--ink);
    padding-bottom: 2rem;
}
.tool-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    overflow: hidden;
    padding: 2.1rem 2.35rem;
    border-radius: 25px;
    background: linear-gradient(118deg, #112f4b 0%, #155d68 58%, #1b877d 100%);
    color: #fff;
    box-shadow: 0 22px 55px rgba(16, 53, 77, 0.23);
}
.tool-hero:before,
.tool-hero:after {
    position: absolute;
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 50%;
    content: "";
}
.tool-hero:before {
    width: 310px;
    height: 310px;
    right: -95px;
    top: -180px;
}
.tool-hero:after {
    width: 180px;
    height: 180px;
    right: 85px;
    bottom: -140px;
}
.hero-copy {
    position: relative;
    z-index: 1;
}
.hero-eyebrow,
.section-kicker {
    font-size: 0.68rem;
    font-weight: 850;
    letter-spacing: 0.13em;
}
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #c7f0eb;
}
.hero-copy h1 {
    margin: 0.45rem 0 0.55rem;
    color: #fff;
    font-size: clamp(1.8rem, 3.3vw, 2.65rem);
    letter-spacing: -0.025em;
}
.hero-copy p {
    max-width: 730px;
    margin: 0;
    color: rgba(255, 255, 255, 0.84);
    font-size: 1rem;
    line-height: 1.55;
}
.hero-assurances {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-top: 1.15rem;
}
.hero-assurances span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.65rem;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    font-size: 0.72rem;
    font-weight: 750;
    backdrop-filter: blur(8px);
}
.hero-orbit {
    position: relative;
    z-index: 1;
    display: grid;
    flex: 0 0 112px;
    height: 112px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 32px;
    background: rgba(255, 255, 255, 0.12);
    transform: rotate(5deg);
    box-shadow: inset 0 0 35px rgba(255, 255, 255, 0.08);
}
.hero-orbit span {
    display: grid;
    width: 70px;
    height: 70px;
    place-items: center;
    border-radius: 22px;
    background: #fff;
    color: var(--violet);
    font-size: 2rem;
    transform: rotate(-5deg);
    box-shadow: 0 16px 35px rgba(8, 27, 45, 0.22);
}
.configuration-alert {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-top: 1rem;
    padding: 1rem 1.15rem;
    border: 1px solid #f1dca8;
    border-radius: 15px;
    background: #fff8e5;
    color: #805809;
}
.configuration-alert > i {
    font-size: 1.5rem;
}
.configuration-alert strong,
.configuration-alert span {
    display: block;
}
.configuration-alert span {
    margin-top: 0.12rem;
    font-size: 0.8rem;
}
.tool-workspace {
    display: grid;
    grid-template-columns: minmax(330px, 390px) minmax(0, 1fr);
    gap: 1.15rem;
    margin-top: 1.15rem;
    align-items: start;
}
.tool-card {
    border: 1px solid #e4eaee;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 10px 35px rgba(21, 53, 78, 0.075);
}
.creation-card {
    position: sticky;
    top: 84px;
    padding: 1.35rem;
}
.card-heading {
    padding-bottom: 1rem;
    border-bottom: 1px solid #edf0f3;
}
.section-kicker {
    display: block;
    color: var(--teal);
}
.card-heading h2,
.history-heading h2 {
    margin: 0.28rem 0 0.2rem;
    font-size: 1.3rem;
}
.card-heading p,
.history-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 0.8rem;
}
.context-chip {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.15rem 0.6rem;
    margin: 1rem 0;
    padding: 0.78rem 0.85rem;
    border: 1px solid #dceae8;
    border-radius: 13px;
    background: #f2f9f8;
}
.context-chip span {
    grid-column: 1/-1;
    color: #4d716f;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.context-chip strong {
    font-size: 0.82rem;
}
.context-chip small {
    color: #6f7d8a;
}
.tool-field {
    display: block;
    margin-top: 1rem;
}
.tool-field > span:first-child {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: #405066;
    font-size: 0.78rem;
    font-weight: 800;
}
.tool-field > span b {
    display: grid;
    width: 24px;
    height: 24px;
    place-items: center;
    border-radius: 8px;
    background: #e5f5f2;
    color: #15736e;
    font-size: 0.7rem;
}
.tool-field .form-select {
    min-height: 46px;
    border-color: #dce4e9;
    border-radius: 11px;
    font-size: 0.84rem;
    box-shadow: none;
}
.tool-field .form-select:focus {
    border-color: #59aaa4;
    box-shadow: 0 0 0 3px rgba(18, 132, 125, 0.1);
}
.drop-zone {
    position: relative;
    display: flex;
    min-height: 165px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.15rem;
    border: 1.5px dashed #bfcdd5;
    border-radius: 16px;
    background: #f9fbfc;
    text-align: center;
    cursor: pointer;
    transition: 0.2s;
}
.drop-zone:hover,
.drop-zone.active {
    border-color: var(--teal);
    background: #f1faf8;
    transform: translateY(-1px);
}
.drop-zone.ready {
    border-style: solid;
    border-color: #9bd2cc;
    background: #f1faf8;
}
.drop-zone input {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    opacity: 0;
}
.drop-icon {
    display: grid;
    width: 48px;
    height: 48px;
    margin-bottom: 0.65rem;
    place-items: center;
    border-radius: 14px;
    background: #e5f3f5;
    color: #287b85;
    font-size: 1.4rem;
}
.drop-zone.ready .drop-icon {
    background: #e7e2f8;
    color: var(--violet);
}
.drop-zone strong {
    max-width: 100%;
    overflow: hidden;
    color: #32445a;
    font-size: 0.84rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.drop-zone small {
    margin-top: 0.25rem;
    color: #7b8796;
    font-size: 0.7rem;
}
.clear-file {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    margin: 0.45rem auto 0;
    border: 0;
    background: transparent;
    color: #7a668c;
    font-size: 0.7rem;
}
.form-error {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    margin-top: 0.9rem;
    padding: 0.7rem 0.8rem;
    border-radius: 11px;
    background: #fff0f1;
    color: #9a333a;
    font-size: 0.76rem;
}
.form-error i {
    font-size: 1rem;
}
.review-button {
    display: flex;
    width: 100%;
    min-height: 49px;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    margin-top: 1rem;
    border: 0;
    border-radius: 13px;
    background: linear-gradient(115deg, #6554b5, #735bc7);
    color: #fff;
    font-size: 0.88rem;
    font-weight: 800;
    box-shadow: 0 10px 22px rgba(101, 84, 181, 0.24);
    transition: 0.2s;
}
.review-button:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 14px 26px rgba(101, 84, 181, 0.3);
}
.review-button:disabled {
    opacity: 0.6;
    box-shadow: none;
}
.review-button i {
    font-size: 1.1rem;
}
.privacy-note {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 0.3rem;
    margin: 0.7rem 0.25rem 0;
    color: #7a8795;
    font-size: 0.66rem;
    line-height: 1.35;
    text-align: center;
}
.privacy-note i {
    color: var(--teal);
    font-size: 0.85rem;
}
.result-card {
    min-height: 540px;
    padding: 1.35rem;
}
.result-empty {
    display: grid;
    min-height: 510px;
    place-content: center;
    justify-items: center;
    padding: 2rem;
    text-align: center;
}
.result-empty > span {
    display: grid;
    width: 78px;
    height: 78px;
    place-items: center;
    border-radius: 24px;
    background: linear-gradient(145deg, #e8f6f3, #eeebfb);
    color: var(--violet);
    font-size: 2.1rem;
}
.result-empty h2 {
    margin: 1rem 0 0.35rem;
    font-size: 1.35rem;
}
.result-empty p {
    max-width: 510px;
    margin: 0;
    color: var(--muted);
    line-height: 1.55;
}
.empty-flow {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 1.25rem;
    color: #6d7784;
    font-size: 0.72rem;
}
.empty-flow span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.empty-flow b {
    display: grid;
    width: 22px;
    height: 22px;
    place-items: center;
    border-radius: 7px;
    background: #edf1f4;
    color: #46566a;
}
.empty-flow > i {
    color: #a0a9b2;
}
.result-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e8edf0;
}
.result-title {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    min-width: 0;
}
.file-mark,
.history-file {
    display: grid;
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    place-items: center;
    border-radius: 14px;
    background: #ffeded;
    color: #c7474f;
    font-size: 1.35rem;
}
.file-mark.word,
.history-file.word {
    background: #eaf1ff;
    color: #3560a8;
}
.result-title h2 {
    margin: 0.38rem 0 0.18rem;
    font-size: 1.25rem;
    overflow-wrap: anywhere;
}
.result-title p {
    margin: 0;
    color: var(--muted);
    font-size: 0.78rem;
}
.report-status {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 999px;
    padding: 0.28rem 0.52rem;
    font-size: 0.64rem;
    font-weight: 850;
}
.tone-info {
    background: #e6f5f8 !important;
    color: #176a81 !important;
}
.tone-violet {
    background: #eeeafb !important;
    color: #5b49a4 !important;
}
.tone-success {
    background: #e4f6ee !important;
    color: #176a50 !important;
}
.tone-danger {
    background: #ffeaec !important;
    color: #9b2e36 !important;
}
.tone-neutral {
    background: #eff1f4 !important;
    color: #596273 !important;
}
.result-actions {
    display: flex;
    gap: 0.45rem;
}
.result-actions .btn {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 10px;
    white-space: nowrap;
    font-size: 0.76rem;
    font-weight: 750;
}
.download-button {
    background: var(--violet);
    color: #fff;
}
.download-button:hover {
    background: #58479f;
    color: #fff;
}
.processing-state {
    display: grid;
    grid-template-columns: 170px 1fr;
    align-items: center;
    gap: 1.3rem;
    min-height: 330px;
    padding: 2rem;
}
.processing-visual {
    position: relative;
    display: grid;
    width: 150px;
    height: 170px;
    place-items: center;
}
.document-sheet {
    display: grid;
    width: 98px;
    height: 128px;
    place-items: center;
    border: 1px solid #dce4e9;
    border-radius: 14px;
    background: #fff;
    color: #688098;
    font-size: 2.3rem;
    box-shadow: 0 15px 35px rgba(22, 54, 80, 0.13);
}
.scan-line {
    position: absolute;
    width: 118px;
    height: 4px;
    border-radius: 99px;
    background: linear-gradient(90deg, transparent, #49aaa1, transparent);
    box-shadow: 0 0 12px #49aaa1;
    animation: scan 2.2s ease-in-out infinite;
}
.spark {
    position: absolute;
    color: #7661c6;
    animation: pulse 1.7s ease-in-out infinite;
}
.spark-one {
    right: 3px;
    top: 22px;
}
.spark-two {
    left: 2px;
    bottom: 28px;
    font-size: 0.65rem;
    animation-delay: 0.5s;
}
.processing-state h3 {
    max-width: 590px;
    margin: 0.38rem 0;
    font-size: 1.15rem;
    line-height: 1.4;
}
.processing-state p {
    margin: 0;
    color: var(--muted);
    font-size: 0.82rem;
}
.paused-state,
.failed-state {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
    padding: 0.9rem 1rem;
    border-radius: 13px;
}
.paused-state {
    background: #eef5f8;
    color: #3c6175;
}
.failed-state {
    background: #fff0f1;
    color: #87383e;
}
.paused-state > i,
.failed-state > i {
    font-size: 1.35rem;
}
.paused-state div,
.failed-state div {
    flex: 1;
}
.paused-state strong,
.paused-state span,
.failed-state strong,
.failed-state span {
    display: block;
}
.paused-state span,
.failed-state span {
    margin-top: 0.12rem;
    font-size: 0.72rem;
}
.paused-state .btn,
.failed-state .btn {
    border-radius: 9px;
    font-size: 0.72rem;
}
.report-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
    padding: 0.8rem 1rem;
    border: 1px solid #e3e9ed;
    border-radius: 13px;
    background: #f8fafb;
}
.report-toolbar p {
    margin: 0.25rem 0 0;
    color: var(--muted);
    font-size: 0.7rem;
}
.regenerate-link {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    border: 0;
    background: transparent;
    color: #5d4ba5;
    font-size: 0.72rem;
    font-weight: 800;
}
.embedded-report {
    margin-top: 1rem;
}
.history-section {
    margin-top: 1.15rem;
    padding: 1.35rem;
    border: 1px solid #e4eaee;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 10px 35px rgba(21, 53, 78, 0.06);
}
.history-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
}
.history-count {
    border-radius: 999px;
    padding: 0.4rem 0.7rem;
    background: #edf7f5;
    color: #19736d;
    font-size: 0.7rem;
    font-weight: 800;
}
.history-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}
.history-card {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-width: 0;
    padding: 0.85rem;
    border: 1px solid #e4e9ed;
    border-radius: 15px;
    background: #fff;
    text-align: left;
    transition: 0.18s;
}
.history-card:hover,
.history-card.selected {
    border-color: #91c9c4;
    background: #f6fbfa;
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(25, 79, 87, 0.08);
}
.history-file {
    flex-basis: 40px;
    width: 40px;
    height: 40px;
    border-radius: 11px;
    font-size: 1.1rem;
}
.history-content {
    display: block;
    min-width: 0;
    flex: 1;
}
.history-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}
.history-topline b {
    border-radius: 999px;
    padding: 0.22rem 0.4rem;
    font-size: 0.58rem;
}
.history-topline small {
    color: #8a95a0;
    font-size: 0.58rem;
}
.history-content > strong,
.history-content > small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.history-content > strong {
    margin-top: 0.35rem;
    color: #314258;
    font-size: 0.78rem;
}
.history-content > small {
    margin-top: 0.12rem;
    color: #7b8795;
    font-size: 0.65rem;
}
.history-arrow {
    color: #9aa5af;
    font-size: 1.15rem;
}
.history-empty {
    display: flex;
    min-height: 120px;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    margin-top: 1rem;
    border: 1px dashed #d7e0e5;
    border-radius: 15px;
    color: #87929f;
    font-size: 0.8rem;
}
.history-empty i {
    font-size: 1.2rem;
}
.pagination-tools {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}
.pagination-tools button {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    border: 1px solid #dce4e9;
    border-radius: 9px;
    background: #fff;
    color: #516176;
    font-size: 0.72rem;
    padding: 0.45rem 0.7rem;
}
.pagination-tools button:disabled {
    opacity: 0.45;
}
.pagination-tools span {
    color: #788493;
    font-size: 0.7rem;
}
.tool-toast {
    position: fixed;
    z-index: 1090;
    top: 78px;
    right: 18px;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    max-width: min(430px, calc(100vw - 36px));
    padding: 0.8rem 1rem;
    border: 1px solid #dce5ea;
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(12, 36, 56, 0.2);
    font-size: 0.8rem;
}
.tool-toast > i {
    font-size: 1.2rem;
}
.tool-toast > span {
    flex: 1;
}
.tool-toast > button {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 1.1rem;
}
.tool-toast--success {
    border-color: #b8e2d2;
    color: #176a50;
}
.tool-toast--danger {
    border-color: #efc6ca;
    color: #9b2e36;
}
.tool-toast--info {
    border-color: #c6dde5;
    color: #176a81;
}
@keyframes scan {
    0%,
    100% {
        transform: translateY(-48px);
        opacity: 0.35;
    }
    50% {
        transform: translateY(48px);
        opacity: 1;
    }
}
@keyframes pulse {
    0%,
    100% {
        transform: scale(0.8);
        opacity: 0.4;
    }
    50% {
        transform: scale(1.2);
        opacity: 1;
    }
}
@media (max-width: 1180px) {
    .tool-workspace {
        grid-template-columns: 340px minmax(0, 1fr);
    }
    .history-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .result-heading {
        flex-direction: column;
    }
    .result-actions {
        width: 100%;
    }
    .result-actions .btn {
        flex: 1;
        justify-content: center;
    }
}
@media (max-width: 900px) {
    .hero-orbit {
        display: none;
    }
    .tool-workspace {
        grid-template-columns: 1fr;
    }
    .creation-card {
        position: static;
    }
    .history-grid {
        grid-template-columns: 1fr;
    }
    .processing-state {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .processing-visual {
        margin: auto;
    }
    .result-card {
        min-height: 420px;
    }
    .result-empty {
        min-height: 390px;
    }
}
@media (max-width: 600px) {
    .ai-tool-page {
        padding-inline: 0.65rem;
    }
    .tool-hero {
        padding: 1.4rem;
        border-radius: 19px;
    }
    .hero-copy h1 {
        font-size: 1.75rem;
    }
    .hero-assurances {
        gap: 0.4rem;
    }
    .hero-assurances span {
        font-size: 0.63rem;
    }
    .tool-card,
    .history-section {
        padding: 1rem;
        border-radius: 18px;
    }
    .result-heading,
    .history-heading {
        align-items: stretch;
        flex-direction: column;
    }
    .result-title {
        align-items: flex-start;
    }
    .file-mark {
        flex-basis: 42px;
        width: 42px;
        height: 42px;
    }
    .result-actions {
        flex-direction: column;
    }
    .empty-flow {
        align-items: flex-start;
        flex-direction: column;
    }
    .empty-flow > i {
        display: none;
    }
    .processing-state {
        padding: 1.2rem 0.2rem;
    }
    .paused-state,
    .failed-state {
        align-items: stretch;
        flex-direction: column;
    }
    .paused-state .btn,
    .failed-state .btn {
        width: 100%;
    }
    .report-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }
    .regenerate-link {
        padding: 0;
    }
    .history-count {
        align-self: flex-start;
    }
    .tool-toast {
        top: 65px;
        right: 10px;
    }
    .pagination-tools {
        justify-content: space-between;
        gap: 0.35rem;
    }
    .pagination-tools span {
        font-size: 0.62rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .drop-zone,
    .review-button,
    .history-card {
        transition: none;
    }
    .scan-line,
    .spark {
        animation: none;
    }
}
</style>
