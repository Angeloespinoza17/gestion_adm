<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PedagogicalAiReportContent from "../../components/pedagogical-management/PedagogicalAiReportContent.vue";
import { downloadPedagogicalAiReportPdf } from "../../utils/pedagogical-ai-report-pdf";
import {
    errorMessage,
    instrumentFileExtension,
    instrumentFileIcon,
    openBlob,
    pedagogicalManagementApi,
    saveBlob,
    workflowPresentation,
} from "../../services/pedagogical-management-api";

const loading = ref(true);
const working = ref(false);
const downloadingReport = ref(false);
const detailLoading = ref(false);
const showReviewModal = ref(false);
const queue = ref([]);
const meta = ref({ total: 0, status_counts: {} });
const detail = ref(null);
const catalogs = ref({ schools: [], openai_configured: false });
const guidance = ref([]);
const filter = reactive({ search: "", workflow_status: "" });
const review = reactive({ decision: "", coordinator_notes: "", share_ai_report: false, guidance_document_ids: [] });
const documentForm = reactive({ id: null, document_type: "evaluation_regulation", title: "", description: "", content: "", active: true });
const showDocuments = ref(false);
const alert = reactive({ tone: "", text: "" });
let aiTimer;

const pendingQueue = computed(() => Number(meta.value.status_counts?.submitted || 0) + Number(meta.value.status_counts?.resubmitted || 0));
const newQueue = computed(() => Number(meta.value.status_counts?.submitted || 0));
const resubmittedQueue = computed(() => Number(meta.value.status_counts?.resubmitted || 0));
const resolvedQueue = computed(() =>
    Number(meta.value.status_counts?.approved || 0)
    + Number(meta.value.status_counts?.approved_with_observations || 0)
    + Number(meta.value.status_counts?.rectification_requested || 0)
);
const actionable = computed(() => ["submitted", "resubmitted"].includes(detail.value?.workflow_status));
const aiReport = computed(() => detail.value?.latest_ai_report || null);
const completedAiReport = computed(() => aiReport.value?.status === "completed" ? aiReport.value : null);
const activeGuidance = computed(() => guidance.value.filter((document) => document.active));

function notify(tone, text) {
    Object.assign(alert, { tone, text });
    window.setTimeout(() => { if (alert.text === text) alert.text = ""; }, 6500);
}

function formatDate(value) {
    if (!value) return "—";
    return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function resetReview() {
    Object.assign(review, { decision: "", coordinator_notes: "", share_ai_report: false, guidance_document_ids: [] });
}

async function initialize() {
    loading.value = true;
    try {
        catalogs.value = await pedagogicalManagementApi.catalogs();
        await loadQueue();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible cargar Revisión documental."));
    } finally {
        loading.value = false;
    }
}

async function loadQueue() {
    const response = await pedagogicalManagementApi.reviewQueue({
        search: filter.search || undefined,
        workflow_status: filter.workflow_status || undefined,
        per_page: 100,
    });
    queue.value = response.data || [];
    meta.value = response.meta || meta.value;
    if (detail.value) {
        const current = queue.value.find((item) => item.id === detail.value.id);
        if (current) await openDetail(current, false);
        else closeReviewModal(true);
    }
}

async function openDetail(item, reset = true) {
    stopAiPolling();
    detailLoading.value = true;
    try {
        detail.value = await pedagogicalManagementApi.show(item.id);
        if (reset) resetReview();
        showDocuments.value = false;
        showReviewModal.value = true;
        document.body.classList.add("document-review-modal-open");
        await loadGuidance(detail.value.school?.id);
        if (["pending", "processing"].includes(aiReport.value?.status)) pollAi(aiReport.value.id);
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible abrir el instrumento."));
    } finally {
        detailLoading.value = false;
    }
}

function closeReviewModal(force = false) {
    if (working.value && force !== true) return;
    stopAiPolling();
    showReviewModal.value = false;
    showDocuments.value = false;
    detail.value = null;
    document.body.classList.remove("document-review-modal-open");
}

async function loadGuidance(schoolId) {
    if (!schoolId) return;
    try {
        guidance.value = await pedagogicalManagementApi.guidanceDocuments(schoolId);
    } catch (error) {
        guidance.value = [];
        notify("danger", errorMessage(error, "No fue posible cargar los documentos de apoyo."));
    }
}

async function accessInstrumentFile(instrument, file) {
    if (!instrument?.id || !file) return;
    const download = instrumentFileExtension(file.original_filename) !== "pdf";
    try {
        const blob = download
            ? await pedagogicalManagementApi.downloadFile(instrument.id, file.id)
            : await pedagogicalManagementApi.viewFile(instrument.id, file.id);
        if (download) saveBlob(blob, file.original_filename);
        else openBlob(blob);
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible abrir o descargar el instrumento."));
    }
}

async function viewFile() {
    return accessInstrumentFile(detail.value, detail.value?.latest_file);
}

async function viewQueueFile(item) {
    return accessInstrumentFile(item, item?.latest_file);
}

async function generateAiReport() {
    working.value = true;
    try {
        const report = await pedagogicalManagementApi.requestAiReport(detail.value.id);
        detail.value.latest_ai_report = report;
        notify("info", "Informe solicitado. Se procesará en segundo plano sobre la versión actual.");
        pollAi(report.id);
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible solicitar el informe OpenAI."));
    } finally {
        working.value = false;
    }
}

function pollAi(reportId) {
    stopAiPolling();
    const check = async () => {
        try {
            const report = await pedagogicalManagementApi.aiReport(detail.value.id, reportId);
            detail.value.latest_ai_report = report;
            if (["pending", "processing"].includes(report.status)) aiTimer = window.setTimeout(check, 4000);
            else if (report.status === "completed") notify("success", "Informe OpenAI disponible para apoyar la decisión.");
            else notify("danger", report.error_message || "No fue posible generar el informe OpenAI.");
        } catch (error) {
            notify("warning", "No se pudo actualizar el informe; puedes reintentar desde esta vista.");
        }
    };
    aiTimer = window.setTimeout(check, 2500);
}

function stopAiPolling() {
    if (aiTimer) window.clearTimeout(aiTimer);
    aiTimer = null;
}

function chooseDecision(decision) {
    review.decision = decision;
    if (decision === "approved") review.coordinator_notes = "";
    if (decision !== "rectification_requested") review.share_ai_report = false;
}

async function downloadAiReport() {
    if (!completedAiReport.value?.report || !detail.value) return;
    downloadingReport.value = true;
    try {
        await downloadPedagogicalAiReportPdf(detail.value, completedAiReport.value);
        notify("success", "Informe de retroalimentación descargado.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible descargar el informe."));
    } finally {
        downloadingReport.value = false;
    }
}

async function submitDecision() {
    if (!review.decision) return notify("danger", "Selecciona una resolución.");
    if (["approved_with_observations", "rectification_requested"].includes(review.decision) && !review.coordinator_notes.trim()) {
        return notify("danger", "Escribe las observaciones para el docente.");
    }
    working.value = true;
    try {
        const response = await pedagogicalManagementApi.decide(detail.value.id, {
            decision: review.decision,
            coordinator_notes: review.coordinator_notes || null,
            ai_report_id: completedAiReport.value?.id || null,
            share_ai_report: Boolean(review.decision === "rectification_requested" && review.share_ai_report && completedAiReport.value),
            guidance_document_ids: review.guidance_document_ids,
        });
        notify("success", response.message);
        await loadQueue();
        resetReview();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible registrar la resolución."));
    } finally {
        working.value = false;
    }
}

function editDocument(document = null) {
    Object.assign(documentForm, document ? {
        id: document.id,
        document_type: document.document_type,
        title: document.title,
        description: document.description || "",
        content: document.content,
        active: document.active,
    } : { id: null, document_type: "evaluation_regulation", title: "", description: "", content: "", active: true });
}

async function saveDocument() {
    if (!detail.value?.school?.id) return;
    working.value = true;
    try {
        const payload = { school_id: detail.value.school.id, ...documentForm };
        delete payload.id;
        if (documentForm.id) await pedagogicalManagementApi.updateGuidanceDocument(documentForm.id, payload);
        else await pedagogicalManagementApi.createGuidanceDocument(payload);
        await loadGuidance(detail.value.school.id);
        editDocument();
        notify("success", "Documento de orientación guardado.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible guardar el documento."));
    } finally {
        working.value = false;
    }
}

async function toggleDocument(document) {
    working.value = true;
    try {
        await pedagogicalManagementApi.updateGuidanceDocument(document.id, {
            school_id: detail.value.school.id,
            document_type: document.document_type,
            title: document.title,
            description: document.description,
            content: document.content,
            active: !document.active,
        });
        await loadGuidance(detail.value.school.id);
    } catch (error) {
        notify("danger", errorMessage(error));
    } finally {
        working.value = false;
    }
}

function handleKeydown(event) {
    if (event.key === "Escape" && showReviewModal.value) closeReviewModal();
}

onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
    initialize();
});
onBeforeUnmount(() => {
    stopAiPolling();
    window.removeEventListener("keydown", handleKeydown);
    document.body.classList.remove("document-review-modal-open");
});
</script>

<template>
    <Layout>
        <div class="review-page container-fluid py-4">
            <header class="review-hero mb-4">
                <div>
                    <span class="eyebrow">COORDINACIÓN ACADÉMICA</span>
                    <h1>Revisión documental</h1>
                    <p>Prioriza los instrumentos recibidos, revisa cada versión y registra una resolución trazable.</p>
                </div>
                <div class="queue-counter"><strong>{{ pendingQueue }}</strong><span>{{ pendingQueue === 1 ? "pendiente" : "pendientes" }}</span></div>
            </header>

            <div v-if="alert.text" class="alert" :class="`alert-${alert.tone}`">{{ alert.text }}</div>
            <LoadingState v-if="loading" message="Preparando bandeja de revisión..." />

            <template v-else>
                <section class="review-metrics mb-4" aria-label="Resumen de revisión documental">
                    <article class="metric-card urgent"><span><i class="bx bx-time-five"></i></span><div><strong>{{ pendingQueue }}</strong><small>Por revisar</small></div></article>
                    <article class="metric-card new"><span><i class="bx bx-file"></i></span><div><strong>{{ newQueue }}</strong><small>Nuevos envíos</small></div></article>
                    <article class="metric-card corrected"><span><i class="bx bx-revision"></i></span><div><strong>{{ resubmittedQueue }}</strong><small>Rectificados</small></div></article>
                    <article class="metric-card resolved"><span><i class="bx bx-check-shield"></i></span><div><strong>{{ resolvedQueue }}</strong><small>Con resolución</small></div></article>
                </section>

                <section class="review-table-card">
                    <header class="table-card-heading">
                        <div><span class="section-kicker">BANDEJA DOCUMENTAL</span><h2>Instrumentos recibidos</h2><p>Los envíos nuevos y rectificados se muestran primero.</p></div>
                        <button class="btn btn-light refresh-button" type="button" :disabled="detailLoading" @click="loadQueue"><i class="bx bx-refresh"></i>Actualizar</button>
                    </header>

                    <div class="queue-tools">
                        <div class="search-control"><i class="bx bx-search"></i><input v-model="filter.search" class="form-control" type="search" placeholder="Buscar docente, instrumento o asignatura" aria-label="Buscar instrumentos" @keyup.enter="loadQueue" /></div>
                        <select v-model="filter.workflow_status" class="form-select status-filter" aria-label="Filtrar por estado" @change="loadQueue">
                            <option value="">Todos los estados</option>
                            <option value="submitted">Nuevos</option>
                            <option value="resubmitted">Rectificados</option>
                            <option value="approved">Aprobados</option>
                            <option value="approved_with_observations">Aprobados con observaciones</option>
                            <option value="rectification_requested">Pendientes de rectificación</option>
                        </select>
                        <button class="btn btn-primary search-button" type="button" @click="loadQueue"><i class="bx bx-search"></i>Buscar</button>
                    </div>

                    <div class="table-responsive">
                        <table class="review-table">
                            <thead><tr><th>Instrumento</th><th>Docente</th><th>Asignatura y curso</th><th>Enviado</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                            <tbody>
                                <tr v-for="item in queue" :key="item.id">
                                    <td data-label="Instrumento"><div class="instrument-cell"><span class="pdf-icon" :class="{ word: instrumentFileExtension(item.latest_file?.original_filename) === 'docx' }"><i class="bx" :class="instrumentFileIcon(item.latest_file?.original_filename)"></i></span><div><strong>{{ item.title }}</strong><small>Versión {{ item.latest_file?.version || 1 }} · {{ item.latest_file?.original_filename }}</small></div></div></td>
                                    <td data-label="Docente"><strong class="cell-primary">{{ item.owner?.name || "Sin docente" }}</strong></td>
                                    <td data-label="Asignatura y curso"><strong class="cell-primary">{{ item.subject?.name || "Sin asignatura" }}</strong><small class="cell-secondary">{{ item.courses?.map((course) => course.name).join(", ") || "Sin curso" }}</small></td>
                                    <td data-label="Enviado"><span class="date-cell">{{ formatDate(item.submitted_at || item.created_at) }}</span></td>
                                    <td data-label="Estado"><span class="status-pill" :class="`tone-${workflowPresentation(item.workflow_status).tone}`">{{ workflowPresentation(item.workflow_status).label }}</span></td>
                                    <td data-label="Acciones" class="actions-cell">
                                        <button v-if="item.latest_file" class="icon-action" type="button" :title="instrumentFileExtension(item.latest_file.original_filename) === 'pdf' ? 'Ver PDF' : 'Descargar Word'" :aria-label="instrumentFileExtension(item.latest_file.original_filename) === 'pdf' ? 'Ver PDF' : 'Descargar Word'" @click="viewQueueFile(item)"><i class="bx" :class="instrumentFileExtension(item.latest_file.original_filename) === 'pdf' ? 'bx-show' : 'bx-download'"></i></button>
                                        <button class="review-action" type="button" :disabled="detailLoading" @click="openDetail(item)">{{ ["submitted", "resubmitted"].includes(item.workflow_status) ? "Revisar" : "Ver resolución" }}<i class="bx bx-right-arrow-alt"></i></button>
                                    </td>
                                </tr>
                                <tr v-if="!queue.length"><td colspan="6"><div class="empty"><span><i class="bx bx-check-shield"></i></span><h3>Bandeja al día</h3><p>No hay instrumentos que coincidan con los filtros seleccionados.</p></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <footer class="table-footer"><span>{{ queue.length }} de {{ meta.total }} instrumentos</span><span><i class="bx bx-lock-alt"></i> Acceso restringido a coordinación académica</span></footer>
                </section>
            </template>
        </div>

        <Teleport to="body">
            <div v-if="showReviewModal && detail" class="review-modal-backdrop" role="presentation" @mousedown.self="closeReviewModal">
                <section class="review-modal" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
                    <header class="review-modal-heading">
                        <div class="review-title-block"><span class="pdf-icon large" :class="{ word: instrumentFileExtension(detail.latest_file?.original_filename) === 'docx' }"><i class="bx" :class="instrumentFileIcon(detail.latest_file?.original_filename)"></i></span><div><span class="status-pill" :class="`tone-${workflowPresentation(detail.workflow_status).tone}`">{{ workflowPresentation(detail.workflow_status).label }}</span><h2 id="review-modal-title">{{ detail.title }}</h2><p>{{ detail.owner?.name }} · {{ detail.subject?.name }} · {{ detail.courses?.map((course) => course.name).join(", ") }}</p></div></div>
                        <div class="modal-header-actions"><button type="button" class="btn btn-outline-primary" @click="viewFile"><i class="bx me-1" :class="instrumentFileExtension(detail.latest_file?.original_filename) === 'pdf' ? 'bx-show' : 'bx-download'"></i>{{ instrumentFileExtension(detail.latest_file?.original_filename) === "pdf" ? "Ver instrumento" : "Descargar Word" }}</button><button class="modal-close" type="button" aria-label="Cerrar revisión" :disabled="working" @click="closeReviewModal"><i class="bx bx-x"></i></button></div>
                    </header>

                    <div class="review-modal-body">
                        <div class="review-context-grid">
                            <article><span><i class="bx bx-user"></i>Docente</span><strong>{{ detail.owner?.name }}</strong></article>
                            <article><span><i class="bx bx-book-open"></i>Asignatura y curso</span><strong>{{ detail.subject?.name }} · {{ detail.courses?.map((course) => course.name).join(", ") }}</strong></article>
                            <article><span><i class="bx bx-git-branch"></i>Versión recibida</span><strong>v{{ detail.latest_file?.version }} · {{ formatDate(detail.submitted_at || detail.created_at) }}</strong></article>
                        </div>

                        <div v-if="detail.workflow_status === 'resubmitted'" class="resubmitted-banner"><i class="bx bx-revision"></i><div><strong>Nueva versión rectificada</strong><p>Compara este archivo con las observaciones anteriores antes de registrar una nueva resolución.</p></div></div>

                        <section class="ai-card">
                            <div class="ai-heading"><div class="ai-mark"><i class="bx bx-sparkles"></i></div><div><h3>Informe de apoyo OpenAI</h3><p>Analiza exclusivamente la versión actual. La resolución siempre queda en manos de la coordinadora.</p></div><div class="ai-actions"><button v-if="completedAiReport" type="button" class="btn btn-outline-ai" :disabled="downloadingReport" @click="downloadAiReport"><span v-if="downloadingReport" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bx bx-download me-1"></i>Descargar informe</button><button type="button" class="btn btn-ai" :disabled="working || !catalogs.openai_configured || ['pending','processing'].includes(aiReport?.status)" @click="generateAiReport">{{ ['pending','processing'].includes(aiReport?.status) ? "Generando..." : aiReport?.status === 'completed' ? "Generar nuevamente" : "Generar informe" }}</button></div></div>
                            <div v-if="!catalogs.openai_configured" class="small text-warning mt-3">La integración OpenAI no está configurada en este entorno.</div>
                            <PedagogicalAiReportContent v-if="aiReport?.status === 'completed' && aiReport.report" class="mt-3" :report="aiReport.report" />
                            <div v-if="aiReport?.status === 'failed'" class="alert alert-warning mt-3 mb-0">{{ aiReport.error_message }}</div>
                        </section>

                        <section v-if="actionable" class="decision-card">
                            <div class="section-heading"><div><span class="section-kicker">RESOLUCIÓN DOCUMENTAL</span><h3>Define el resultado de la revisión</h3><p>Selecciona una acción y configura exactamente qué recibirá el docente.</p></div><button type="button" class="btn btn-light" @click="showDocuments = !showDocuments"><i class="bx bx-file me-1"></i>Gestionar documentos</button></div>
                            <div class="decision-grid">
                                <button type="button" :class="{ selected: review.decision === 'approved' }" @click="chooseDecision('approved')"><i class="bx bx-check-circle"></i><strong>Aprobar</strong><small>Envía a Centro de Apuntes</small></button>
                                <button type="button" :class="{ selected: review.decision === 'approved_with_observations' }" @click="chooseDecision('approved_with_observations')"><i class="bx bx-message-check"></i><strong>Aprobar con observaciones</strong><small>Aprueba y comunica mejoras</small></button>
                                <button type="button" class="rectify" :class="{ selected: review.decision === 'rectification_requested' }" @click="chooseDecision('rectification_requested')"><i class="bx bx-revision"></i><strong>Solicitar rectificación</strong><small>Pide una nueva versión</small></button>
                            </div>
                            <label class="form-label mt-4">Mensaje para el docente <span v-if="review.decision !== 'approved'" class="text-danger">*</span></label>
                            <textarea v-model="review.coordinator_notes" class="form-control review-message" rows="4" placeholder="Explica con claridad los aspectos que debe mantener o rectificar..."></textarea>
                            <div class="delivery-options mt-4"><label v-if="completedAiReport && review.decision === 'rectification_requested'" class="check-card"><input v-model="review.share_ai_report" type="checkbox" /><span><strong>Enviar informe de retroalimentación completo</strong><small>El docente verá exactamente este informe y podrá descargarlo mientras gestiona la rectificación.</small></span></label><label v-for="document in activeGuidance" :key="document.id" class="check-card"><input v-model="review.guidance_document_ids" type="checkbox" :value="document.id" /><span><strong>{{ document.title }}</strong><small>{{ document.description || 'Documento de orientación institucional' }}</small></span></label><p v-if="!activeGuidance.length" class="text-muted mb-0">No hay reglamentos, rúbricas u orientaciones habilitadas.</p></div>
                            <div class="decision-footer"><span><i class="bx bx-lock-alt"></i> La resolución quedará registrada con tu usuario, fecha y versión.</span><button type="button" class="btn btn-primary btn-lg" :disabled="working || !review.decision" @click="submitDecision"><span v-if="working" class="spinner-border spinner-border-sm me-2"></span><i v-else class="bx bx-check-shield me-1"></i>{{ working ? "Registrando..." : "Confirmar resolución" }}</button></div>
                        </section>

                        <section v-else class="resolved-card"><i class="bx bx-check-shield"></i><div><h3>Resolución vigente</h3><p>{{ detail.latest_review?.decision_label }} · {{ detail.latest_review?.coordinator_notes || "Sin observaciones adicionales." }}</p></div></section>

                        <section v-if="showDocuments" class="documents-card">
                            <div class="section-heading"><div><h3>Reglamentos, rúbricas y orientaciones</h3><p>Crea el contenido institucional y habilita sólo lo que pueda adjuntarse a una revisión.</p></div><button type="button" class="btn-close" aria-label="Cerrar" @click="showDocuments = false"></button></div>
                            <form class="document-form" @submit.prevent="saveDocument"><div class="row g-3"><div class="col-md-4"><label class="form-label">Tipo</label><select v-model="documentForm.document_type" class="form-select"><option value="evaluation_regulation">Reglamento de evaluación</option><option value="rubric">Rúbrica</option><option value="guideline">Orientación</option><option value="other">Otro documento</option></select></div><div class="col-md-8"><label class="form-label">Título</label><input v-model="documentForm.title" class="form-control" maxlength="191" required /></div><div class="col-12"><label class="form-label">Descripción breve</label><input v-model="documentForm.description" class="form-control" maxlength="500" /></div><div class="col-12"><label class="form-label">Contenido que recibirá el docente</label><textarea v-model="documentForm.content" class="form-control" rows="5" required></textarea></div></div><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3"><label class="form-check"><input v-model="documentForm.active" class="form-check-input" type="checkbox" /><span class="form-check-label">Habilitado para enviar</span></label><div><button v-if="documentForm.id" type="button" class="btn btn-light me-2" @click="editDocument()">Cancelar</button><button class="btn btn-primary" type="submit" :disabled="working">{{ documentForm.id ? "Actualizar" : "Crear documento" }}</button></div></div></form>
                            <div class="document-list mt-4"><article v-for="document in guidance" :key="document.id"><span class="doc-state" :class="{ active: document.active }"><i class="bx" :class="document.active ? 'bx-check' : 'bx-pause'"></i></span><div class="flex-grow-1"><strong>{{ document.title }}</strong><small>{{ document.description }}</small></div><button class="btn btn-sm btn-light" type="button" @click="editDocument(document)">Editar</button><button class="btn btn-sm" :class="document.active ? 'btn-outline-warning' : 'btn-outline-success'" type="button" @click="toggleDocument(document)">{{ document.active ? "Deshabilitar" : "Habilitar" }}</button></article></div>
                        </section>
                    </div>
                </section>
            </div>
        </Teleport>
    </Layout>
</template>

<style scoped>
.review-page{--navy:#163d62;--teal:#18877f;--ink:#17243a;color:var(--ink)}
.review-hero{position:relative;overflow:hidden;padding:2rem 2.25rem;border-radius:24px;background:linear-gradient(120deg,#122f4c,#175b6a 62%,#238c80);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:2rem;box-shadow:0 18px 44px rgba(16,54,79,.22)}.review-hero::after{content:"";position:absolute;width:250px;height:250px;right:-85px;top:-130px;border:1px solid rgba(255,255,255,.14);border-radius:50%}.review-hero h1{color:#fff;margin:.3rem 0;font-size:clamp(1.7rem,3vw,2.4rem)}.review-hero p{margin:0;opacity:.84;max-width:760px}.eyebrow,.section-kicker{letter-spacing:.16em;font-size:.68rem;font-weight:800}.eyebrow{opacity:.75}.section-kicker{color:#18877f}.queue-counter{position:relative;z-index:1;min-width:128px;text-align:center;padding:1rem;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.12);border-radius:18px;backdrop-filter:blur(8px)}.queue-counter strong,.queue-counter span{display:block}.queue-counter strong{font-size:2rem;line-height:1.1}.queue-counter span{font-size:.8rem;opacity:.85}
.review-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}.metric-card{display:flex;align-items:center;gap:.85rem;padding:1rem 1.15rem;border:1px solid #e8edf1;border-radius:17px;background:#fff;box-shadow:0 6px 22px rgba(27,52,76,.06)}.metric-card>span{width:44px;height:44px;display:grid;place-items:center;border-radius:13px;font-size:1.25rem}.metric-card strong,.metric-card small{display:block}.metric-card strong{font-size:1.45rem;color:#22334b;line-height:1.1}.metric-card small{color:#7a8796;margin-top:.2rem}.metric-card.urgent>span{color:#8b5d04;background:#fff4d8}.metric-card.new>span{color:#187085;background:#e6f5f8}.metric-card.corrected>span{color:#6a53b2;background:#efebfb}.metric-card.resolved>span{color:#23795b;background:#e5f5ed}
.review-table-card{overflow:hidden;border-radius:20px;background:#fff;box-shadow:0 8px 30px rgba(24,49,76,.08)}.table-card-heading{padding:1.45rem 1.65rem 1.15rem;display:flex;justify-content:space-between;align-items:flex-end;gap:1rem}.table-card-heading h2{font-size:1.35rem;margin:.25rem 0 .2rem}.table-card-heading p{margin:0;color:#748092}.refresh-button{display:flex;align-items:center;gap:.3rem;border-radius:11px;font-weight:700}.refresh-button i{font-size:1.15rem}
.queue-tools{display:flex;gap:.7rem;padding:1rem 1.65rem;background:#f7fafc;border-top:1px solid #edf1f4;border-bottom:1px solid #edf1f4}.search-control{position:relative;flex:1}.search-control>i{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);z-index:1;color:#7a8796}.search-control .form-control{padding-left:2.55rem}.queue-tools .form-control,.queue-tools .form-select{min-height:44px;border-color:#dfe6ec;border-radius:11px}.status-filter{flex:0 0 250px}.search-button{display:flex;align-items:center;gap:.3rem;border-radius:11px;padding-inline:1rem;font-weight:700}
.review-table{width:100%;border-collapse:collapse}.review-table th{padding:.85rem 1rem;border-bottom:1px solid #edf1f4;color:#7b8796;font-size:.68rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;white-space:nowrap}.review-table th:first-child,.review-table td:first-child{padding-left:1.65rem}.review-table th:last-child,.review-table td:last-child{padding-right:1.65rem}.review-table td{padding:1rem;vertical-align:middle;border-bottom:1px solid #edf1f4}.review-table tbody tr{transition:background .18s ease}.review-table tbody tr:hover{background:#f8fbfb}.instrument-cell{display:flex;align-items:center;gap:.75rem;min-width:245px}.instrument-cell strong,.instrument-cell small,.cell-primary,.cell-secondary{display:block}.instrument-cell strong{color:#223249;max-width:320px}.instrument-cell small,.cell-secondary{color:#7b8796;font-size:.75rem;margin-top:.18rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cell-primary{color:#3b4a60;font-size:.86rem}.date-cell{color:#637083;font-size:.8rem;white-space:nowrap}.pdf-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;background:#ffeded;color:#cf4b52;font-size:1.25rem;flex:0 0 40px}.pdf-icon.word{background:#edf3ff;color:#315ca8}.pdf-icon.large{width:48px;height:48px;flex-basis:48px;font-size:1.4rem}.status-pill{display:inline-flex;border-radius:999px;padding:.4rem .7rem;font-size:.7rem;font-weight:800;white-space:nowrap}.tone-info{background:#e5f6fa;color:#176a81}.tone-warning{background:#fff4d7;color:#8a5b00}.tone-danger{background:#ffeaec;color:#9b2e36}.tone-success{background:#e4f6ee;color:#176a50}.tone-neutral{background:#eff1f4;color:#596273}.actions-cell{text-align:right;white-space:nowrap}.icon-action,.review-action{border:0;border-radius:10px;transition:.18s}.icon-action{width:36px;height:36px;margin-right:.35rem;background:#edf4f8;color:#3e647e;font-size:1.05rem}.review-action{padding:.55rem .78rem;background:#e6f5f3;color:#176f6c;font-size:.78rem;font-weight:800}.review-action i{font-size:1rem;vertical-align:-2px;margin-left:.2rem}.icon-action:hover,.review-action:hover{transform:translateY(-1px);filter:brightness(.97)}.review-action:disabled{opacity:.55}.empty{min-height:260px;display:grid;place-content:center;justify-items:center;text-align:center;color:#8490a0;padding:2rem}.empty>span{width:58px;height:58px;display:grid;place-items:center;border-radius:18px;background:#e8f6f1;color:#318164}.empty i{font-size:1.8rem}.empty h3{font-size:1.05rem;color:#34445b;margin:.8rem 0 .25rem}.empty p{margin:0}.table-footer{display:flex;justify-content:space-between;gap:1rem;padding:.8rem 1.65rem;background:#fbfcfd;color:#7b8796;font-size:.75rem}.table-footer i{color:#27847f;vertical-align:-1px}
.review-modal-backdrop{position:fixed;z-index:1085;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(13,29,46,.6);backdrop-filter:blur(5px);animation:fadeIn .18s ease}.review-modal{width:min(100%,1180px);max-height:calc(100vh - 2rem);display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;box-shadow:0 28px 80px rgba(9,27,44,.34);animation:liftIn .22s ease}.review-modal-heading{padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;border-bottom:1px solid #e8edf1;background:linear-gradient(135deg,#f9fcfc,#f7f9fd)}.review-title-block{display:flex;align-items:flex-start;gap:.85rem;min-width:0}.review-title-block h2{margin:.35rem 0 .18rem;color:#1d2d43;font-size:1.35rem}.review-title-block p{margin:0;color:#748092}.modal-header-actions{display:flex;align-items:center;gap:.55rem}.modal-header-actions .btn{border-radius:10px;white-space:nowrap}.modal-close{width:38px;height:38px;display:grid;place-items:center;border:0;border-radius:11px;background:#edf2f5;color:#647386;font-size:1.4rem}.modal-close:hover{background:#e2e9ee;color:#26364a}.review-modal-body{overflow-y:auto;overflow-x:hidden;padding:1.4rem;background:#fcfdfd}.review-context-grid{display:grid;grid-template-columns:.8fr 1.2fr 1fr;gap:.75rem}.review-context-grid article{padding:.9rem 1rem;border:1px solid #e8edf1;border-radius:14px;background:#fff}.review-context-grid span,.review-context-grid strong{display:block}.review-context-grid span{color:#7a8796;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.review-context-grid span i{color:#26877f;margin-right:.2rem}.review-context-grid strong{margin-top:.35rem;color:#33445a;font-size:.86rem}.resubmitted-banner{display:flex;gap:.8rem;align-items:center;margin-top:1rem;padding:.9rem 1rem;border:1px solid #ddd5f4;border-radius:14px;background:#f3f0fb;color:#5d4a9f}.resubmitted-banner>i{font-size:1.5rem}.resubmitted-banner p{margin:.15rem 0 0;color:#70658f;font-size:.8rem}
.ai-card,.decision-card,.documents-card{border:1px solid #e8edf2;border-radius:18px;padding:1.25rem;margin-top:1rem;background:#fff}.ai-card{background:linear-gradient(135deg,#f5f2ff,#f8fbff)}.ai-heading{display:flex;align-items:center;gap:.85rem}.ai-heading h3,.section-heading h3{font-size:1.05rem;margin:0}.ai-heading p,.section-heading p{font-size:.82rem;color:#6e7a8b;margin:.2rem 0 0}.ai-mark{width:44px;height:44px;flex:0 0 44px;border-radius:14px;display:grid;place-items:center;background:#6654b5;color:#fff;font-size:1.35rem}.btn-ai{margin-left:auto;background:#6654b5;color:#fff;border-radius:10px}.btn-ai:hover{background:#5847a1;color:#fff}.ai-report{background:#fff;border-radius:14px;padding:1rem}.ai-report .summary{font-weight:600}.ai-report h4{font-size:.82rem;text-transform:uppercase;letter-spacing:.07em;color:#6654b5}.ai-report ul{padding-left:1.1rem;margin-bottom:0}.ai-report li{margin-bottom:.4rem;font-size:.86rem}.ai-observations{display:grid;gap:.55rem}.ai-observations>h4{margin-bottom:0}.ai-observations article{display:flex;align-items:flex-start;gap:.65rem;border:1px solid #ece9f8;border-radius:12px;padding:.7rem}.ai-observations article>span{border-radius:999px;padding:.25rem .45rem;font-size:.65rem;font-weight:800}.severity-critical{background:#ffeaec;color:#9b2e36}.severity-important{background:#fff3d4;color:#8a5b00}.severity-suggestion{background:#e5f4f9;color:#176a81}.ai-observations article strong,.ai-observations article p,.ai-observations article small{display:block}.ai-observations article p{margin:.2rem 0;color:#536174}.teacher-message{background:#f2effc;border-radius:12px;padding:.8rem}.teacher-message p{margin:.35rem 0 0}
.ai-actions{display:flex;gap:.5rem;margin-left:auto}.ai-actions .btn-ai{margin-left:0}.btn-outline-ai{border:1px solid #7665bf;border-radius:10px;background:#fff;color:#5d4ca5;white-space:nowrap}.btn-outline-ai:hover{background:#f0edfb;color:#514196}
.criteria-review{padding:1rem 0;border-top:1px solid #ebe8f7;border-bottom:1px solid #ebe8f7}.criteria-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:.6rem}.criteria-heading span{display:block;color:#18877f;font-size:.65rem;font-weight:800;letter-spacing:.1em}.criteria-heading h4{margin:.2rem 0 0!important;color:#263851!important;font-size:.95rem!important;letter-spacing:0!important;text-transform:none!important}.criteria-heading>small{color:#758193}.criteria-scope-note{display:flex;align-items:flex-start;gap:.35rem;margin:0 0 .8rem;padding:.55rem .65rem;border-radius:10px;background:#f2f5f9;color:#687588;font-size:.7rem;line-height:1.35}.criteria-scope-note i{color:#6654b5;font-size:.9rem}.criteria-groups{display:grid;gap:.9rem}.criteria-dimension{padding:.8rem;border:1px solid #ebeaf4;border-radius:15px;background:rgba(249,250,255,.72)}.criteria-dimension>header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.65rem}.criteria-dimension>header strong{color:#34445a;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}.criteria-dimension>header span{border-radius:999px;padding:.25rem .5rem;background:#ebe7f7;color:#65569a;font-size:.62rem;font-weight:800}.criteria-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.criteria-grid article{padding:.8rem;border:1px solid #e7eaf3;border-radius:13px;background:#fff}.criterion-top{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.45rem}.criterion-code{display:inline-grid;place-items:center;min-width:34px;height:28px;border-radius:8px;background:#e5f5f3;color:#176f6c;font-size:.72rem;font-weight:900}.criterion-status{border-radius:999px;padding:.3rem .52rem;font-size:.63rem;font-weight:800}.criterion-applicability{display:flex!important;align-items:center;gap:.25rem;margin-bottom:.45rem;color:#6d5ca5!important;font-weight:700}.criterion-applicability i{font-size:.82rem}.criteria-grid article>strong{display:block;color:#34445a;font-size:.8rem;line-height:1.35}.criteria-grid article>p{margin:.45rem 0;color:#5d697a;font-size:.78rem;line-height:1.4}.criteria-grid article>small{display:block;color:#778292;font-size:.7rem;line-height:1.4}.criterion-recommendation{margin-top:.35rem;color:#5c4e91!important}
.section-heading{display:flex;justify-content:space-between;align-items:center;gap:1rem}.section-heading .section-kicker{display:block;margin-bottom:.3rem}.decision-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;margin-top:1rem}.decision-grid button{background:#fff;border:1px solid #dfe5ec;border-radius:15px;padding:1rem;text-align:left;color:#17243a;transition:.18s}.decision-grid button:hover,.decision-grid button.selected{border-color:#18877f;background:#edf8f6;box-shadow:0 0 0 3px rgba(24,135,127,.1)}.decision-grid button.rectify:hover,.decision-grid button.rectify.selected{border-color:#dc5960;background:#fff1f2;box-shadow:0 0 0 3px rgba(220,89,96,.08)}.decision-grid i,.decision-grid strong,.decision-grid small{display:block}.decision-grid i{font-size:1.35rem;color:#18877f;margin-bottom:.45rem}.decision-grid .rectify i{color:#dc5960}.decision-grid small{color:#758193;margin-top:.2rem}.review-message{border-radius:12px;border-color:#dfe6ec;resize:vertical}.delivery-options{display:grid;grid-template-columns:repeat(2,1fr);gap:.55rem}.check-card{border:1px solid #e4e9ef;border-radius:13px;padding:.8rem;display:flex;gap:.7rem;align-items:flex-start;cursor:pointer;background:#fff}.check-card:hover{background:#f8fafc}.check-card input{margin-top:.25rem}.check-card strong,.check-card small{display:block}.check-card small{color:#738092}.decision-footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #e8edf1}.decision-footer>span{color:#748092;font-size:.76rem}.decision-footer>span i{color:#27847f}.decision-footer .btn{border-radius:11px;font-weight:700}.resolved-card{display:flex;align-items:center;gap:1rem;border-radius:16px;background:#eaf7f1;padding:1.1rem;margin-top:1rem}.resolved-card>i{font-size:2rem;color:#2d936b}.resolved-card h3{font-size:1rem;margin:0}.resolved-card p{margin:.2rem 0 0;color:#587065}
.documents-card{background:#f7fafc}.document-form{background:#fff;border-radius:15px;padding:1rem;margin-top:1rem}.document-list{display:grid;gap:.55rem}.document-list article{display:flex;gap:.65rem;align-items:center;background:#fff;border:1px solid #e8edf2;border-radius:13px;padding:.7rem}.document-list strong,.document-list small{display:block}.document-list small{color:#758193}.doc-state{width:30px;height:30px;border-radius:9px;background:#edf0f4;color:#8a94a2;display:grid;place-items:center}.doc-state.active{background:#ddf3e9;color:#24835f}:global(body.document-review-modal-open){overflow:hidden}@keyframes fadeIn{from{opacity:0}to{opacity:1}}@keyframes liftIn{from{opacity:0;transform:translateY(12px) scale(.988)}to{opacity:1;transform:none}}
@media(max-width:1100px){.review-metrics{grid-template-columns:repeat(2,1fr)}.queue-tools{flex-wrap:wrap}.search-control{flex-basis:100%}.status-filter{flex:1}.review-context-grid{grid-template-columns:1fr 1fr}.review-context-grid article:last-child{grid-column:1/-1}}
@media(max-width:767px){.review-page{padding-left:.7rem!important;padding-right:.7rem!important}.review-hero{padding:1.35rem;border-radius:18px}.queue-counter{display:none}.review-metrics{grid-template-columns:1fr 1fr;gap:.65rem}.metric-card{padding:.8rem}.metric-card>span{width:38px;height:38px}.table-card-heading{padding:1.2rem;align-items:flex-start}.refresh-button{display:none}.queue-tools{padding:.85rem}.status-filter,.search-button{flex:1}.review-table thead{display:none}.review-table,.review-table tbody{display:block}.review-table tbody{padding:.7rem}.review-table tbody tr{display:grid;gap:.65rem;padding:1rem;margin-bottom:.7rem;border:1px solid #e6edf1;border-radius:16px}.review-table td,.review-table th:first-child,.review-table td:first-child,.review-table th:last-child,.review-table td:last-child{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0;border:0;text-align:right}.review-table td::before{content:attr(data-label);color:#84909e;font-size:.65rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;text-align:left}.review-table td:first-child{display:block;text-align:left;padding-bottom:.65rem;border-bottom:1px solid #edf1f4}.review-table td:first-child::before,.review-table td[colspan]::before{display:none}.review-table td[colspan]{display:block}.instrument-cell{min-width:0}.instrument-cell strong{max-width:none}.actions-cell{padding-top:.35rem!important}.actions-cell .review-action{flex:1}.table-footer{padding:.8rem 1rem;flex-direction:column;gap:.25rem}.review-modal-backdrop{padding:0;align-items:end}.review-modal{max-height:95vh;border-radius:20px 20px 0 0}.review-modal-heading{padding:1rem;flex-direction:column}.modal-header-actions{width:100%}.modal-header-actions .btn{flex:1}.review-modal-body{padding:1rem}.review-context-grid{grid-template-columns:1fr}.review-context-grid article:last-child{grid-column:auto}.ai-heading,.section-heading{align-items:stretch;flex-direction:column}.ai-actions{width:100%;margin-left:0}.ai-actions .btn{flex:1}.btn-ai{margin-left:0}.decision-grid,.delivery-options{grid-template-columns:1fr}.decision-footer{align-items:stretch;flex-direction:column}.decision-footer .btn{width:100%}.document-list article{flex-wrap:wrap}.document-list .flex-grow-1{width:calc(100% - 42px)}}
@media(max-width:767px){.criteria-heading{align-items:flex-start;flex-direction:column}.criteria-grid{grid-template-columns:1fr}}
@media(max-width:430px){.review-metrics{grid-template-columns:1fr}.status-filter,.search-button{flex-basis:100%}.review-title-block .pdf-icon{display:none}}
@media(prefers-reduced-motion:reduce){.review-modal-backdrop,.review-modal{animation:none}}
</style>
