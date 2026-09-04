<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
    analysisIsStale,
    errorMessage,
    instrumentFileIcon,
    pedagogicalManagementApi,
    pedagogicalPolling,
    pollingDecision,
    statusPresentation,
    validateInstrumentCandidate,
} from "../../services/pedagogical-management-api";
import { downloadPedagogicalFeedbackPdf } from "../../utils/pedagogical-feedback-pdf";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const submitting = ref(false);
const exportingFeedback = ref(false);
const detailLoading = ref(false);
const refreshingStatus = ref(false);
const pollingActive = ref(false);
const pollingPaused = ref(false);
const pollAttempts = ref(0);
const lastCheckedAt = ref(null);
const pollingError = ref("");
const list = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const detail = ref(null);
const selectedSchoolId = ref("");
const selectedFile = ref(null);
const fileInput = ref(null);
const dragActive = ref(false);
const formError = ref("");
const alert = reactive({ type: "", message: "" });
const form = reactive({ owner_user_id: "", subject_id: "", course_id: "" });
const catalogs = ref({
    schools: [],
    subjects: [],
    courses: [],
    owners: [],
    academic_year: null,
    max_file_kb: 30720,
    analysis_configured: false,
    analysis_engine: "",
});
let pollTimer;
let pollInFlight = false;

const permissions = computed(() => {
    try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
    } catch {
        return [];
    }
});
const canCreate = computed(() =>
    permissions.value.includes("pedagogical-instruments.create")
);
const analysisReady = computed(() => catalogs.value.analysis_configured === true);
const pending = computed(() =>
    ["pending_analysis", "processing"].includes(detail.value?.status)
);
const results = computed(() => detail.value?.latest_analysis?.results || []);
const errors = computed(() =>
    results.value.filter((item) => item.category === "error")
);
const suggestions = computed(() =>
    results.value.filter((item) => item.category === "suggestion")
);
const canExportFeedback = computed(() =>
    Boolean(
        detail.value?.latest_analysis &&
            !pending.value &&
            (detail.value.latest_analysis.review_summary ||
                results.value.length)
    )
);
function notify(type, message) {
    alert.type = type;
    alert.message = message;
    window.setTimeout(() => {
        if (alert.message === message) alert.message = "";
    }, 6500);
}

function bytes(value) {
    const number = Number(value || 0);
    return number >= 1048576
        ? `${(number / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(number / 1024))} KB`;
}

function dateTime(value) {
    if (!value) return "";
    return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

async function loadCatalogs() {
    const data = await pedagogicalManagementApi.catalogs({
        school_id: selectedSchoolId.value || undefined,
    });
    catalogs.value = data;
    if (
        !selectedSchoolId.value &&
        (data.selected_school_id || data.schools.length === 1)
    ) {
        selectedSchoolId.value = String(
            data.selected_school_id || data.schools[0].id
        );
        if (!data.selected_school_id) return loadCatalogs();
    }
    if (!form.owner_user_id && data.owners.length === 1)
        form.owner_user_id = String(data.owners[0].id);
}

async function loadList(page = 1, { quiet = false } = {}) {
    if (!quiet) loading.value = true;
    try {
        const response = await pedagogicalManagementApi.list({
            school_id: selectedSchoolId.value || undefined,
            page,
        });
        list.value = response.data || [];
        meta.value = response.meta || meta.value;
    } catch (error) {
        if (!quiet) {
            notify(
                "danger",
                errorMessage(error, "No fue posible cargar el historial.")
            );
        }
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
}

function onDrop(event) {
    dragActive.value = false;
    chooseFile(event.dataTransfer.files?.[0]);
}

function validateForm() {
    if (!selectedSchoolId.value)
        return "No se encontró el colegio institucional configurado.";
    if (!form.owner_user_id) return "Selecciona el docente.";
    if (!form.subject_id) return "Selecciona la asignatura.";
    if (!form.course_id) return "Selecciona el curso.";
    return validateInstrumentCandidate(
        selectedFile.value,
        catalogs.value.max_file_kb
    );
}

async function submit() {
    formError.value = validateForm();
    if (formError.value || !analysisReady.value) return;
    submitting.value = true;
    try {
        const payload = new FormData();
        payload.append("school_id", selectedSchoolId.value);
        payload.append("owner_user_id", form.owner_user_id);
        payload.append("subject_id", form.subject_id);
        payload.append("course_id", form.course_id);
        payload.append("file", selectedFile.value);
        const response = await pedagogicalManagementApi.create(payload);
        detail.value = response.instrument;
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = "";
        notify(
            "success",
            "Archivo recibido. Se inició la revisión determinística."
        );
        await loadList(1);
        await router.push({
            name: "pedagogical-instrument-analysis",
            params: { instrumentId: response.instrument.id },
        });
        await loadDetail(response.instrument.id, true);
    } catch (error) {
        formError.value = errorMessage(
            error,
            "No fue posible enviar el instrumento a revisión."
        );
    } finally {
        submitting.value = false;
    }
}

async function loadDetail(id, quiet = false) {
    if (!quiet) detailLoading.value = true;
    try {
        detail.value = await pedagogicalManagementApi.show(id);
        if (pending.value && analysisIsStale(detail.value)) {
            lastCheckedAt.value = new Date();
            pausePolling();
        } else if (pending.value) startPolling({ reset: !quiet });
        else stopPolling();
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible abrir la revisión.")
        );
    } finally {
        detailLoading.value = false;
    }
}

async function openDetail(id) {
    if (route.params.instrumentId !== id) {
        await router.push({
            name: "pedagogical-instrument-analysis",
            params: { instrumentId: id },
        });
    } else {
        await loadDetail(id);
    }
}

async function clearDetail() {
    stopPolling();
    detail.value = null;
    await router.push({ name: "pedagogical-instrument-analysis" });
}

function updateListItem(instrument) {
    const index = list.value.findIndex((item) => item.id === instrument.id);
    if (index >= 0) list.value[index] = instrument;
}

function schedulePoll() {
    if (!pollingActive.value || pollTimer) return;
    pollTimer = window.setTimeout(runPoll, pedagogicalPolling.intervalMs);
}

async function runPoll() {
    pollTimer = null;
    if (!pollingActive.value || pollInFlight || !detail.value) return;
    pollInFlight = true;
    pollAttempts.value += 1;
    let decision = "pause";

    try {
        const instrument = await pedagogicalManagementApi.show(detail.value.id);
        detail.value = instrument;
        updateListItem(instrument);
        lastCheckedAt.value = new Date();
        pollingError.value = "";
        decision = pollingDecision({
            pending: pending.value,
            attempts: pollAttempts.value,
        });
    } catch (error) {
        pollingError.value = errorMessage(
            error,
            "No se pudo comprobar el estado del análisis."
        );
        decision = pollingDecision({
            pending: true,
            attempts: pollAttempts.value,
            requestFailed: true,
        });
    } finally {
        pollInFlight = false;
    }

    if (decision === "complete") {
        stopPolling();
        await loadList(meta.value.current_page || 1, { quiet: true });
        notify("success", "La retroalimentación ya está disponible.");
        return;
    }
    if (decision === "pause") {
        pausePolling();
        return;
    }
    schedulePoll();
}

function startPolling({ reset = false } = {}) {
    if (!detail.value || !pending.value) {
        stopPolling();
        return;
    }
    if (pollingActive.value && !reset) return;
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = null;
    pollingActive.value = true;
    pollingPaused.value = false;
    pollingError.value = "";
    if (reset) pollAttempts.value = 0;
    schedulePoll();
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
    pollingError.value = "";
    pollAttempts.value = 0;
}

async function refreshStatus() {
    if (!detail.value || refreshingStatus.value) return;
    refreshingStatus.value = true;
    try {
        const instrument = await pedagogicalManagementApi.show(detail.value.id);
        detail.value = instrument;
        updateListItem(instrument);
        lastCheckedAt.value = new Date();
        pollingError.value = "";
        if (pending.value) startPolling({ reset: true });
        else {
            stopPolling();
            await loadList(meta.value.current_page || 1, { quiet: true });
            notify("success", "La retroalimentación ya está disponible.");
        }
    } catch (error) {
        pollingError.value = errorMessage(
            error,
            "No se pudo comprobar el estado. Intenta nuevamente."
        );
        pausePolling();
    } finally {
        refreshingStatus.value = false;
    }
}

async function runAgain() {
    submitting.value = true;
    try {
        await pedagogicalManagementApi.analyze(detail.value.id);
        notify("success", "Se inició una nueva revisión determinística.");
        await loadDetail(detail.value.id, true);
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible iniciar la revisión.")
        );
    } finally {
        submitting.value = false;
    }
}

async function openOriginalFile() {
    try {
        const blob = await pedagogicalManagementApi.viewFile(detail.value.id, detail.value.latest_file.id);
        const url = URL.createObjectURL(blob);
        window.open(url, "_blank", "noopener,noreferrer");
        window.setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible abrir el archivo privado.")
        );
    }
}

async function exportFeedback() {
    if (!canExportFeedback.value) return;
    exportingFeedback.value = true;
    try {
        await downloadPedagogicalFeedbackPdf(detail.value);
        notify("success", "Retroalimentación exportada en PDF.");
    } catch (error) {
        notify(
            "danger",
            errorMessage(
                error,
                "No fue posible generar la retroalimentación en PDF."
            )
        );
    } finally {
        exportingFeedback.value = false;
    }
}

watch(
    () => route.params.instrumentId,
    async (id) => {
        if (id) await loadDetail(id);
        else {
            stopPolling();
            detail.value = null;
        }
    }
);

onMounted(async () => {
    try {
        await loadCatalogs();
        await loadList();
        if (route.params.instrumentId)
            await loadDetail(route.params.instrumentId);
    } catch (error) {
        notify(
            "danger",
            errorMessage(error, "No fue posible iniciar Gestión pedagógica.")
        );
        loading.value = false;
    }
});
onBeforeUnmount(stopPolling);
</script>

<template>
    <Layout>
        <div
            v-if="alert.message"
            class="ai-toast"
            :class="`ai-toast--${alert.type}`"
        >
            <i
                class="bx"
                :class="
                    alert.type === 'success'
                        ? 'bx-check-circle'
                        : 'bx-error-circle'
                "
            ></i>
            <span>{{ alert.message }}</span
            ><button type="button" @click="alert.message = ''">×</button>
        </div>

        <section class="ai-hero">
            <div>
                <span class="ai-eyebrow"
                    ><i class="bx bx-sparkles"></i> Gestión pedagógica</span
                >
                <h2>Revisión determinística de instrumentos</h2>
                <p>
                    Selecciona tres datos, carga un PDF y recibe hallazgos
                    reproducibles basados en reglas institucionales.
                </p>
            </div>
        </section>

        <div v-if="!analysisReady" class="ai-config-alert">
            <i class="bx bx-error-circle"></i>
            <div>
                <strong>El motor de revisión no está disponible</strong>
                <span>Contacta al administrador antes de cargar documentos.</span>
            </div>
        </div>

        <main class="ai-workspace">
            <section class="ai-card ai-form-card">
                <header>
                    <span class="ai-step">Nueva revisión</span>
                    <h4>Solo la información necesaria</h4>
                    <p>
                        Los campos técnicos se obtienen del archivo y del
                        contexto académico.
                    </p>
                </header>

                <form @submit.prevent="submit">
                    <label class="ai-field">
                        <span><b>1</b> Docente</span>
                        <select
                            v-model="form.owner_user_id"
                            class="form-select"
                        >
                            <option value="" disabled>
                                Selecciona el docente
                            </option>
                            <option
                                v-for="owner in catalogs.owners"
                                :key="owner.id"
                                :value="String(owner.id)"
                            >
                                {{ owner.name }}
                            </option>
                        </select>
                    </label>

                    <label class="ai-field">
                        <span><b>2</b> Asignatura</span>
                        <select v-model="form.subject_id" class="form-select">
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

                    <label class="ai-field">
                        <span><b>3</b> Curso</span>
                        <select
                            v-model="form.course_id"
                            class="form-select"
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
                        <small v-if="catalogs.academic_year"
                            >Año académico
                            {{
                                catalogs.academic_year.name ||
                                catalogs.academic_year.year
                            }}</small
                        >
                    </label>

                    <div class="ai-field">
                        <span><b>4</b> Archivo PDF</span>
                        <label
                            class="ai-drop"
                            :class="{ active: dragActive, ready: selectedFile }"
                            @dragover.prevent="dragActive = true"
                            @dragleave.prevent="dragActive = false"
                            @drop.prevent="onDrop"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".pdf,application/pdf"
                                @change="chooseFile($event.target.files?.[0])"
                            />
                            <i
                                class="bx"
                                :class="
                                    selectedFile
                                        ? instrumentFileIcon(selectedFile)
                                        : 'bx-cloud-upload'
                                "
                            ></i>
                            <strong>{{
                                selectedFile
                                    ? selectedFile.name
                                    : "Arrastra o selecciona un PDF"
                            }}</strong>
                            <small>{{
                                selectedFile
                                    ? bytes(selectedFile.size)
                                    : `Máximo ${Math.round(
                                          catalogs.max_file_kb / 1024
                                      )} MB`
                            }}</small>
                        </label>
                    </div>

                    <div class="ai-privacy">
                        <i class="bx bx-shield-quarter"></i
                        ><span
                            >El archivo permanece en almacenamiento privado. El análisis
                            se ejecuta localmente con reglas Laravel y no utiliza servicios
                            externos ni inteligencia artificial.</span
                        >
                    </div>
                    <p v-if="formError" class="ai-form-error">
                        <i class="bx bx-error-circle"></i>{{ formError }}
                    </p>
                    <button
                        class="btn ai-submit"
                        type="submit"
                        :disabled="submitting || !canCreate || !analysisReady"
                    >
                        <i
                            class="bx"
                            :class="
                                submitting
                                    ? 'bx-loader-alt bx-spin'
                                    : 'bx-sparkles'
                            "
                        ></i>
                        {{ submitting ? "Enviando…" : "Analizar instrumento" }}
                    </button>
                </form>
            </section>

            <section class="ai-card ai-result-card" aria-live="polite">
                <LoadingState
                    v-if="detailLoading"
                    message="Abriendo revisión…"
                />
                <template v-else-if="detail">
                    <header class="ai-result-head">
                        <button
                            type="button"
                            class="ai-close"
                            aria-label="Cerrar revisión"
                            @click="clearDetail"
                        >
                            ×
                        </button>
                        <div class="ai-file-icon">
                            <i
                                class="bx"
                                :class="instrumentFileIcon(
                                    detail.latest_file?.original_filename
                                )"
                            ></i>
                        </div>
                        <div>
                            <span>Resultado determinístico</span>
                            <h4>
                                {{
                                    detail.latest_file?.original_filename ||
                                    detail.title
                                }}
                            </h4>
                            <p>
                                {{ detail.owner?.name }} ·
                                {{ detail.subject?.name }} ·
                                {{ detail.courses?.[0]?.name }}
                            </p>
                        </div>
                        <span
                            class="ai-status"
                            :class="`ai-status--${
                                statusPresentation(detail.status).tone
                            }`"
                            >{{ statusPresentation(detail.status).label }}</span
                        >
                    </header>

                    <div v-if="pending && pollingActive" class="ai-processing">
                        <span><i class="bx bx-sparkles"></i></span>
                        <h4>
                            {{
                                detail.status === "processing"
                                    ? "Aplicando reglas determinísticas"
                                    : "La revisión está en cola"
                            }}
                        </h4>
                        <p>
                            Comprobamos el estado por HTTP. Puedes salir de esta
                            pantalla: el resultado quedará guardado.
                        </p>
                        <div><i></i><i></i><i></i></div>
                        <small class="ai-poll-caption"
                            >Comprobación {{ pollAttempts }} de
                            {{ pedagogicalPolling.maxAttempts }} · sin
                            WebSocket</small
                        >
                    </div>

                    <div v-else-if="pending" class="ai-waiting-state">
                        <span><i class="bx bx-time-five"></i></span>
                        <div>
                            <small>Análisis en segundo plano</small>
                            <h4>La pantalla dejó de esperar automáticamente</h4>
                            <p>
                                El archivo sigue guardado y la revisión no se
                                perdió. Actualiza el estado cuando quieras; no
                                mantendremos una carga infinita.
                            </p>
                            <p v-if="pollingError" class="ai-waiting-error">
                                <i class="bx bx-error-circle"></i
                                >{{ pollingError }}
                            </p>
                            <small v-if="lastCheckedAt" class="ai-last-check"
                                >Última comprobación:
                                {{ dateTime(lastCheckedAt) }}</small
                            >
                        </div>
                        <button
                            class="btn ai-refresh-status"
                            type="button"
                            :disabled="refreshingStatus"
                            @click="refreshStatus"
                        >
                            <i
                                class="bx"
                                :class="
                                    refreshingStatus
                                        ? 'bx-loader-alt bx-spin'
                                        : 'bx-refresh'
                                "
                            ></i>
                            {{
                                refreshingStatus
                                    ? "Comprobando…"
                                    : "Comprobar ahora"
                            }}
                        </button>
                    </div>

                    <div v-else class="ai-review">
                        <div
                            v-if="detail.latest_analysis"
                            class="ai-review-overview"
                        >
                            <div class="ai-review-ready">
                                <span><i class="bx bx-check-shield"></i></span>
                                <div>
                                    <small>Retroalimentación disponible</small
                                    ><strong>{{
                                        errors.length
                                            ? "Requiere correcciones"
                                            : suggestions.length
                                            ? "Contiene oportunidades de mejora"
                                            : "Sin observaciones reportadas"
                                    }}</strong>
                                </div>
                            </div>
                            <div class="ai-review-metrics">
                                <span class="is-error"
                                    ><b>{{ errors.length }}</b> errores</span
                                >
                                <span class="is-suggestion"
                                    ><b>{{ suggestions.length }}</b>
                                    sugerencias</span
                                >
                                <span
                                    v-if="detail.latest_analysis.finished_at"
                                    class="is-time"
                                    ><i class="bx bx-time-five"></i
                                    >{{
                                        dateTime(
                                            detail.latest_analysis.finished_at
                                        )
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            v-if="detail.latest_analysis?.review_summary"
                            class="ai-summary"
                        >
                            <span>Resumen</span>
                            <p>{{ detail.latest_analysis.review_summary }}</p>
                        </div>

                        <section class="ai-findings ai-findings--errors">
                            <header>
                                <div>
                                    <i class="bx bx-error-circle"></i
                                    ><span>Errores</span>
                                </div>
                                <b>{{ errors.length }}</b>
                            </header>
                            <article v-for="item in errors" :key="item.id">
                                <span v-if="item.page_number"
                                    >Pág. {{ item.page_number }}</span
                                >
                                <h5>{{ item.title }}</h5>
                                <p>{{ item.message }}</p>
                                <blockquote v-if="item.source_excerpt">
                                    “{{ item.source_excerpt }}”
                                </blockquote>
                            </article>
                            <div v-if="!errors.length" class="ai-empty-line">
                                <i class="bx bx-check-circle"></i>No se
                                reportaron errores concretos.
                            </div>
                        </section>

                        <section class="ai-findings ai-findings--suggestions">
                            <header>
                                <div>
                                    <i class="bx bx-bulb"></i
                                    ><span>Sugerencias</span>
                                </div>
                                <b>{{ suggestions.length }}</b>
                            </header>
                            <article v-for="item in suggestions" :key="item.id">
                                <span v-if="item.page_number"
                                    >Pág. {{ item.page_number }}</span
                                >
                                <h5>{{ item.title }}</h5>
                                <p>{{ item.message }}</p>
                                <blockquote v-if="item.source_excerpt">
                                    “{{ item.source_excerpt }}”
                                </blockquote>
                            </article>
                            <div
                                v-if="!suggestions.length"
                                class="ai-empty-line"
                            >
                                <i class="bx bx-check-circle"></i>No se
                                agregaron sugerencias.
                            </div>
                        </section>

                        <p class="ai-disclaimer">
                            <i class="bx bx-info-circle"></i
                            >{{
                                detail.latest_analysis?.disclaimer ||
                                "El resultado requiere revisión profesional humana."
                            }}
                        </p>
                    </div>

                    <footer>
                        <button
                            class="btn btn-light"
                            type="button"
                            @click="openOriginalFile"
                        >
                            <i class="bx bx-show"></i>
                            Ver PDF
                        </button>
                        <button
                            v-if="canExportFeedback"
                            class="btn ai-export"
                            type="button"
                            :disabled="exportingFeedback"
                            @click="exportFeedback"
                        >
                            <i
                                class="bx"
                                :class="
                                    exportingFeedback
                                        ? 'bx-loader-alt bx-spin'
                                        : 'bx-download'
                                "
                            ></i>
                            {{
                                exportingFeedback
                                    ? "Generando…"
                                    : "Exportar retroalimentación"
                            }}
                        </button>
                        <button
                            v-if="pending && pollingActive"
                            class="btn btn-outline-primary"
                            type="button"
                            :disabled="refreshingStatus"
                            @click="refreshStatus"
                        >
                            <i class="bx bx-refresh"></i> Actualizar estado
                        </button>
                        <button
                            v-if="!pending"
                            class="btn btn-outline-primary"
                            type="button"
                            :disabled="submitting"
                            @click="runAgain"
                        >
                            <i class="bx bx-refresh"></i> Analizar otra vez
                        </button>
                    </footer>
                </template>

                <div v-else class="ai-placeholder">
                    <span><i class="bx bx-message-square-dots"></i></span>
                    <h4>Aquí aparecerá la revisión</h4>
                    <p>
                        Recibirás dos listas fáciles de leer: errores detectados
                        y sugerencias de mejora.
                    </p>
                    <div>
                        <i class="bx bx-error-circle"></i
                        ><span
                            ><b>Errores</b>Problemas concretos que conviene
                            corregir.</span
                        >
                    </div>
                    <div>
                        <i class="bx bx-bulb"></i
                        ><span
                            ><b>Sugerencias</b>Mejoras posibles para el
                            instrumento.</span
                        >
                    </div>
                </div>
            </section>
        </main>

        <section class="ai-card ai-history">
            <header>
                <div>
                    <span class="ai-step">Historial</span>
                    <h4>Revisiones anteriores</h4>
                </div>
                <small>{{ meta.total || 0 }} archivos</small>
            </header>
            <LoadingState v-if="loading" message="Cargando historial…" />
            <div v-else-if="!list.length" class="ai-history-empty">
                <i class="bx bx-folder-open"></i
                ><span
                    >Aún no hay instrumentos revisados en este
                    establecimiento.</span
                >
            </div>
            <div v-else class="table-responsive">
                <table class="table ai-table">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Docente</th>
                            <th>Asignatura y curso</th>
                            <th>Resultado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in list"
                            :key="item.id"
                            @click="openDetail(item.id)"
                        >
                            <td>
                                <div class="ai-table-file">
                                    <i
                                        class="bx"
                                        :class="instrumentFileIcon(
                                            item.latest_file?.original_filename
                                        )"
                                    ></i
                                    ><span
                                        ><b>{{
                                            item.latest_file
                                                ?.original_filename ||
                                            item.title
                                        }}</b
                                        ><small>{{
                                            dateTime(item.created_at)
                                        }}</small></span
                                    >
                                </div>
                            </td>
                            <td>{{ item.owner?.name }}</td>
                            <td>
                                <b>{{ item.subject?.name }}</b
                                ><small>{{
                                    item.courses?.[0]?.name || item.grade_label
                                }}</small>
                            </td>
                            <td>
                                <span
                                    class="ai-status"
                                    :class="`ai-status--${
                                        statusPresentation(item.status).tone
                                    }`"
                                    >{{
                                        statusPresentation(item.status).label
                                    }}</span
                                >
                            </td>
                            <td>
                                <button
                                    class="ai-open"
                                    type="button"
                                    aria-label="Abrir"
                                >
                                    <i class="bx bx-chevron-right"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <footer v-if="meta.last_page > 1">
                <button
                    class="btn btn-sm btn-light"
                    :disabled="meta.current_page <= 1"
                    @click="loadList(meta.current_page - 1)"
                >
                    Anterior</button
                ><span
                    >Página {{ meta.current_page }} de
                    {{ meta.last_page }}</span
                ><button
                    class="btn btn-sm btn-light"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="loadList(meta.current_page + 1)"
                >
                    Siguiente
                </button>
            </footer>
        </section>
    </Layout>
</template>

<style scoped>
.ai-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 0.85rem;
    padding: 1.45rem 1.6rem;
    border-radius: 18px;
    background: radial-gradient(
            circle at 78% -20%,
            rgba(117, 222, 218, 0.5),
            transparent 35%
        ),
        linear-gradient(125deg, #102a43, #145d68 62%, #16828a);
    color: #fff;
    box-shadow: 0 18px 42px rgba(18, 55, 72, 0.18);
}
.ai-hero:after {
    position: absolute;
    right: 25%;
    bottom: -90px;
    width: 190px;
    height: 190px;
    border: 30px solid rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    content: "";
}
.ai-hero h2 {
    margin: 0.25rem 0 0.2rem;
    font-size: 1.65rem;
}
.ai-hero p {
    margin: 0;
    color: #d7efef;
    font-size: 0.72rem;
}
.ai-eyebrow,
.ai-step {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: inherit;
    font-size: 0.58rem;
    font-weight: 850;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.ai-context {
    position: relative;
    z-index: 1;
    width: min(310px, 100%);
    padding: 0.65rem 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 11px;
    background: rgba(7, 39, 52, 0.3);
    backdrop-filter: blur(9px);
}
.ai-context label {
    display: block;
    margin-bottom: 0.25rem;
    color: #cde9ea;
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
}
.ai-context .form-select {
    border: 0;
    background-color: #fff;
    font-size: 0.67rem;
}
.ai-config-alert {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 0.8rem;
    padding: 0.7rem 0.85rem;
    border: 1px solid #f3cb83;
    border-radius: 10px;
    background: #fff9eb;
    color: #80530a;
}
.ai-config-alert > i {
    font-size: 1.35rem;
}
.ai-config-alert strong,
.ai-config-alert span {
    display: block;
}
.ai-config-alert strong {
    font-size: 0.68rem;
}
.ai-config-alert span {
    font-size: 0.61rem;
}
.ai-workspace {
    display: grid;
    grid-template-columns: 380px minmax(0, 1fr);
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}
.ai-card {
    border: 1px solid #dfe7ed;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 7px 22px rgba(20, 45, 70, 0.045);
}
.ai-form-card {
    padding: 1rem;
}
.ai-form-card > header {
    margin-bottom: 0.8rem;
}
.ai-form-card .ai-step,
.ai-history .ai-step {
    color: #2b7180;
}
.ai-form-card h4,
.ai-history h4 {
    margin: 0.12rem 0;
    font-size: 0.95rem;
}
.ai-form-card header p {
    margin: 0;
    color: #7d8995;
    font-size: 0.61rem;
}
.ai-form-card form {
    display: grid;
    gap: 0.7rem;
}
.ai-field > span {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.28rem;
    color: #465b6d;
    font-size: 0.62rem;
    font-weight: 800;
}
.ai-field > span b {
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    background: #e8f3f5;
    color: #176878;
    font-size: 0.55rem;
}
.ai-field .form-select {
    font-size: 0.68rem;
}
.ai-field > small {
    display: block;
    margin-top: 0.22rem;
    color: #8b96a3;
    font-size: 0.54rem;
}
.ai-drop {
    position: relative;
    display: grid;
    justify-items: center;
    padding: 1.25rem 0.7rem;
    border: 1.5px dashed #c7d5de;
    border-radius: 10px;
    background: #f8fafb;
    text-align: center;
    cursor: pointer;
    transition: 0.18s;
}
.ai-drop:hover,
.ai-drop.active {
    border-color: #268393;
    background: #f0f9fa;
}
.ai-drop.ready {
    border-style: solid;
    border-color: #7fc6a9;
    background: #f1fbf5;
}
.ai-drop input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}
.ai-drop > i {
    margin-bottom: 0.35rem;
    color: #27778a;
    font-size: 1.65rem;
}
.ai-drop.ready > i {
    color: #cf3e37;
}
.ai-drop strong {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.65rem;
}
.ai-drop small {
    margin-top: 0.15rem;
    color: #8794a1;
    font-size: 0.54rem;
}
.ai-word-note {
    display: flex;
    align-items: flex-start;
    gap: 0.3rem;
    margin-top: 0.35rem;
    padding: 0.45rem 0.55rem;
    border: 1px solid #d8e8f5;
    border-radius: 7px;
    background: #f3f8fd;
    color: #42657c;
    font-size: 0.55rem;
    line-height: 1.35;
}
.ai-word-note i {
    margin-top: 0.05rem;
    color: #2d78a6;
    font-size: 0.8rem;
}
.ai-privacy {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.55rem 0.6rem;
    border-radius: 8px;
    background: #f0f6f8;
    color: #526a79;
    font-size: 0.56rem;
}
.ai-privacy > i {
    color: #237789;
    font-size: 0.95rem;
}
.ai-form-error {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    margin: 0;
    padding: 0.5rem 0.6rem;
    border-radius: 7px;
    background: #fff0ef;
    color: #ad302a;
    font-size: 0.6rem;
}
.ai-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: 0;
    background: linear-gradient(115deg, #15566b, #16838a);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 800;
}
.ai-submit:hover {
    color: #fff;
    filter: brightness(1.04);
}
.ai-result-card {
    display: flex;
    min-height: 530px;
    flex-direction: column;
    overflow: hidden;
}
.ai-result-head {
    position: relative;
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.65rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #e8edf1;
}
.ai-file-icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #fff0ef;
    color: #c83a33;
    font-size: 1.2rem;
}
.ai-result-head span {
    color: #71808e;
    font-size: 0.54rem;
    font-weight: 800;
    text-transform: uppercase;
}
.ai-result-head h4 {
    overflow: hidden;
    margin: 0.05rem 0;
    max-width: 510px;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8rem;
}
.ai-result-head p {
    margin: 0;
    color: #81909d;
    font-size: 0.57rem;
}
.ai-close {
    position: absolute;
    right: 0.4rem;
    top: 0.25rem;
    border: 0;
    background: none;
    color: #8d98a2;
    font-size: 1.1rem;
}
.ai-result-head > .ai-status {
    margin-right: 1.1rem;
}
.ai-processing,
.ai-placeholder {
    display: grid;
    flex: 1;
    align-content: center;
    justify-items: center;
    padding: 2rem;
    text-align: center;
}
.ai-processing > span,
.ai-placeholder > span {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    margin-bottom: 0.7rem;
    border-radius: 18px;
    background: linear-gradient(135deg, #e7f5f6, #f0eaff);
    color: #306e83;
    font-size: 1.8rem;
}
.ai-processing h4,
.ai-placeholder h4 {
    margin: 0;
    font-size: 0.9rem;
}
.ai-processing p,
.ai-placeholder > p {
    max-width: 430px;
    margin: 0.3rem 0;
    color: #7c8996;
    font-size: 0.62rem;
}
.ai-processing > div {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.65rem;
}
.ai-processing > div i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #23808b;
    animation: pulse 1.15s infinite;
}
.ai-processing > div i:nth-child(2) {
    animation-delay: 0.18s;
}
.ai-processing > div i:nth-child(3) {
    animation-delay: 0.36s;
}
.ai-poll-caption {
    margin-top: 0.75rem;
    color: #738593;
    font-size: 0.55rem;
    font-weight: 700;
}
.ai-waiting-state {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.9rem;
    flex: 1;
    margin: 1rem;
    padding: 1.15rem;
    border: 1px solid #ead9a7;
    border-radius: 14px;
    background: linear-gradient(125deg, #fffbf0, #fffef9);
}
.ai-waiting-state > span {
    display: grid;
    place-items: center;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: #fff0c7;
    color: #986d12;
    font-size: 1.45rem;
}
.ai-waiting-state small {
    color: #9a782e;
    font-size: 0.54rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.ai-waiting-state h4 {
    margin: 0.12rem 0 0.22rem;
    color: #4d4532;
    font-size: 0.84rem;
}
.ai-waiting-state p {
    max-width: 520px;
    margin: 0;
    color: #776d57;
    font-size: 0.61rem;
    line-height: 1.55;
}
.ai-waiting-state .ai-last-check {
    display: block;
    margin-top: 0.45rem;
    color: #8b816d;
    font-weight: 650;
    letter-spacing: 0;
    text-transform: none;
}
.ai-waiting-error {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.4rem !important;
    color: #a63b36 !important;
}
.ai-refresh-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    min-width: 134px;
    border: 1px solid #d8bd74;
    background: #fff;
    color: #805e18;
    font-size: 0.61rem;
    font-weight: 800;
}
@keyframes pulse {
    0%,
    80%,
    100% {
        opacity: 0.25;
        transform: scale(0.8);
    }
    40% {
        opacity: 1;
        transform: scale(1.15);
    }
}
.ai-review {
    display: grid;
    gap: 0.7rem;
    overflow: auto;
    max-height: 650px;
    padding: 0.85rem;
}
.ai-review-overview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    padding: 0.72rem 0.78rem;
    border: 1px solid #cfe4e5;
    border-radius: 11px;
    background: linear-gradient(125deg, #f0f9f9, #f8fbfc);
}
.ai-review-ready {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}
.ai-review-ready > span {
    display: grid;
    place-items: center;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #dff3ee;
    color: #19735d;
    font-size: 1.05rem;
}
.ai-review-ready small,
.ai-review-ready strong {
    display: block;
}
.ai-review-ready small {
    color: #627789;
    font-size: 0.5rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.ai-review-ready strong {
    overflow: hidden;
    margin-top: 0.08rem;
    color: #274558;
    font-size: 0.66rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ai-review-metrics {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.ai-review-metrics > span {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.28rem 0.42rem;
    border-radius: 99px;
    background: #fff;
    color: #657887;
    font-size: 0.52rem;
    font-weight: 750;
    white-space: nowrap;
}
.ai-review-metrics .is-error {
    background: #fff0ef;
    color: #ad302a;
}
.ai-review-metrics .is-suggestion {
    background: #fff7e7;
    color: #916009;
}
.ai-review-metrics .is-time {
    background: #edf3f6;
    color: #597181;
}
.ai-summary {
    padding: 0.65rem 0.75rem;
    border-left: 3px solid #2a7b89;
    border-radius: 8px;
    background: #f2f8f9;
}
.ai-summary span {
    color: #2a7080;
    font-size: 0.55rem;
    font-weight: 850;
    text-transform: uppercase;
}
.ai-summary p {
    margin: 0.18rem 0 0;
    color: #4c6070;
    font-size: 0.63rem;
    line-height: 1.5;
}
.ai-findings {
    overflow: hidden;
    border: 1px solid #e2e8ed;
    border-radius: 10px;
}
.ai-findings > header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0.7rem;
    background: #f8fafb;
}
.ai-findings > header > div {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.ai-findings > header span {
    font-size: 0.65rem;
    font-weight: 850;
}
.ai-findings > header i {
    font-size: 1rem;
}
.ai-findings > header b {
    display: grid;
    place-items: center;
    min-width: 23px;
    height: 23px;
    border-radius: 99px;
    background: #fff;
    font-size: 0.58rem;
}
.ai-findings--errors > header {
    color: #aa302a;
    background: #fff3f2;
}
.ai-findings--suggestions > header {
    color: #926006;
    background: #fff9ec;
}
.ai-findings article {
    position: relative;
    padding: 0.65rem 0.75rem;
    border-top: 1px solid #edf1f4;
}
.ai-findings article > span {
    float: right;
    margin-left: 0.5rem;
    padding: 0.17rem 0.3rem;
    border-radius: 99px;
    background: #eef2f5;
    color: #758492;
    font-size: 0.49rem;
    font-weight: 750;
}
.ai-findings h5 {
    margin: 0 0 0.17rem;
    color: #33495a;
    font-size: 0.68rem;
}
.ai-findings p {
    margin: 0;
    color: #647584;
    font-size: 0.6rem;
    line-height: 1.48;
}
.ai-findings blockquote {
    margin: 0.38rem 0 0;
    padding: 0.35rem 0.45rem;
    border-radius: 5px;
    background: #f6f8fa;
    color: #71808d;
    font-size: 0.55rem;
}
.ai-empty-line {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.7rem;
    color: #457261;
    font-size: 0.6rem;
}
.ai-empty-line i {
    font-size: 0.9rem;
}
.ai-disclaimer {
    display: flex;
    align-items: flex-start;
    gap: 0.35rem;
    margin: 0;
    color: #7b8996;
    font-size: 0.54rem;
}
.ai-result-card > footer {
    display: flex;
    gap: 0.45rem;
    margin-top: auto;
    padding: 0.65rem 0.85rem;
    border-top: 1px solid #e8edf1;
    background: #fbfcfd;
}
.ai-result-card > footer .btn {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.61rem;
}
.ai-export {
    border: 1px solid #176f7a;
    background: #176f7a;
    color: #fff;
    font-weight: 800;
}
.ai-export:hover,
.ai-export:focus {
    border-color: #125b64;
    background: #125b64;
    color: #fff;
}
.ai-export:disabled {
    border-color: #8db4b8;
    background: #8db4b8;
    color: #fff;
}
.ai-placeholder > div {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    width: min(360px, 100%);
    margin-top: 0.5rem;
    padding: 0.55rem 0.65rem;
    border: 1px solid #e3e9ee;
    border-radius: 9px;
    text-align: left;
}
.ai-placeholder > div > i {
    color: #bd3b35;
    font-size: 1.1rem;
}
.ai-placeholder > div:last-child > i {
    color: #b1780f;
}
.ai-placeholder > div span,
.ai-placeholder > div b {
    display: block;
    color: #7b8794;
    font-size: 0.55rem;
}
.ai-placeholder > div b {
    color: #465a6a;
    font-size: 0.61rem;
}
.ai-history {
    overflow: hidden;
}
.ai-history > header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0.9rem;
    border-bottom: 1px solid #e8edf1;
}
.ai-history > header small {
    color: #81909d;
    font-size: 0.58rem;
}
.ai-table {
    margin: 0;
    font-size: 0.63rem;
}
.ai-table th {
    padding: 0.55rem 0.7rem;
    background: #f8fafb;
    color: #697887;
    font-size: 0.53rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.ai-table td {
    padding: 0.6rem 0.7rem;
    border-color: #edf1f4;
    vertical-align: middle;
}
.ai-table tbody tr {
    cursor: pointer;
}
.ai-table tbody tr:hover {
    background: #f8fbfc;
}
.ai-table td > b,
.ai-table td > small {
    display: block;
}
.ai-table td > small {
    margin-top: 0.08rem;
    color: #8793a0;
    font-size: 0.53rem;
}
.ai-table-file {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 250px;
}
.ai-table-file > i {
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #fff0ef;
    color: #c73c35;
    font-size: 1rem;
}
.ai-table-file b,
.ai-table-file small {
    display: block;
}
.ai-table-file b {
    max-width: 310px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ai-table-file small {
    margin-top: 0.08rem;
    color: #8a96a2;
    font-size: 0.51rem;
}
.ai-open {
    display: grid;
    place-items: center;
    width: 27px;
    height: 27px;
    border: 1px solid #d9e1e7;
    border-radius: 7px;
    background: #fff;
    color: #477083;
}
.ai-history-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    padding: 1.5rem;
    color: #80909c;
    font-size: 0.62rem;
}
.ai-history-empty i {
    font-size: 1.1rem;
}
.ai-history > footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.55rem;
    padding: 0.55rem 0.7rem;
    border-top: 1px solid #e8edf1;
    color: #758491;
    font-size: 0.55rem;
}
.ai-status {
    display: inline-flex;
    padding: 0.26rem 0.46rem;
    border-radius: 99px;
    background: #eef2f5;
    color: #5f6e7b;
    font-size: 0.54rem;
    font-weight: 800;
    white-space: nowrap;
}
.ai-status--info {
    background: #e8f5f7;
    color: #08758a;
}
.ai-status--danger {
    background: #fff0ef;
    color: #b52f29;
}
.ai-status--warning {
    background: #fff6e7;
    color: #a96808;
}
.ai-status--success {
    background: #eaf8f0;
    color: #17724f;
}
.ai-toast {
    position: fixed;
    right: 1.2rem;
    top: 5.2rem;
    z-index: 4000;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    max-width: 470px;
    padding: 0.7rem 0.8rem;
    border: 1px solid #d5dee5;
    border-radius: 10px;
    background: #fff;
    color: #34495a;
    box-shadow: 0 14px 38px rgba(16, 24, 40, 0.2);
    font-size: 0.65rem;
}
.ai-toast > i {
    font-size: 1.1rem;
}
.ai-toast button {
    margin-left: auto;
    border: 0;
    background: none;
    font-size: 1.1rem;
}
.ai-toast--success {
    border-color: #9fd6ba;
}
.ai-toast--success > i {
    color: #16845b;
}
.ai-toast--danger {
    border-color: #edb2ae;
}
.ai-toast--danger > i {
    color: #c4322b;
}
@media (max-width: 1050px) {
    .ai-workspace {
        grid-template-columns: 340px minmax(0, 1fr);
    }
}
@media (max-width: 820px) {
    .ai-hero {
        align-items: flex-start;
        flex-direction: column;
    }
    .ai-context {
        width: 100%;
    }
    .ai-workspace {
        grid-template-columns: 1fr;
    }
    .ai-result-card {
        min-height: 470px;
    }
}
@media (max-width: 560px) {
    .ai-hero {
        padding: 1rem;
    }
    .ai-hero h2 {
        font-size: 1.35rem;
    }
    .ai-result-head {
        grid-template-columns: 38px 1fr;
    }
    .ai-result-head > .ai-status {
        grid-column: 2;
        justify-self: start;
        margin: 0;
    }
    .ai-review-overview {
        align-items: flex-start;
        flex-direction: column;
    }
    .ai-review-metrics {
        justify-content: flex-start;
    }
    .ai-result-card > footer {
        flex-wrap: wrap;
    }
    .ai-waiting-state {
        grid-template-columns: 42px minmax(0, 1fr);
        align-items: start;
        margin: 0.7rem;
        padding: 0.85rem;
    }
    .ai-waiting-state > span {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        font-size: 1.15rem;
    }
    .ai-refresh-status {
        grid-column: 1 / -1;
        width: 100%;
    }
    .ai-table-file {
        min-width: 190px;
    }
    .ai-form-card {
        padding: 0.8rem;
    }
}
</style>
