<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
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
    validateInstrumentCandidate,
    workflowPresentation,
} from "../../services/pedagogical-management-api";

const loading = ref(true);
const submitting = ref(false);
const downloadingReport = ref(false);
const showSubmissionModal = ref(false);
const showDetailModal = ref(false);
const selectedSchoolId = ref("");
const selectedFile = ref(null);
const fileInput = ref(null);
const list = ref([]);
const detail = ref(null);
const searchTerm = ref("");
const statusFilter = ref("");
const catalogs = ref({ schools: [], owners: [], subjects: [], courses: [], max_file_kb: 20480 });
const form = reactive({ owner_user_id: "", subject_id: "", course_id: "" });
const message = reactive({ tone: "", text: "" });

const pendingCorrection = computed(() => detail.value?.workflow_status === "rectification_requested");
const availableStatuses = computed(() => [...new Set(list.value.map((item) => item.workflow_status).filter(Boolean))]);
const filteredList = computed(() => {
    const query = searchTerm.value.trim().toLocaleLowerCase("es");
    return list.value.filter((item) => {
        const matchesStatus = !statusFilter.value || item.workflow_status === statusFilter.value;
        if (!matchesStatus || !query) return matchesStatus;
        const searchable = [
            item.title,
            item.subject?.name,
            ...(item.courses || []).map((course) => course.name),
        ].filter(Boolean).join(" ").toLocaleLowerCase("es");
        return searchable.includes(query);
    });
});

function notify(tone, text) {
    Object.assign(message, { tone, text });
    window.setTimeout(() => {
        if (message.text === text) message.text = "";
    }, 6500);
}

function formatDate(value) {
    if (!value) return "—";
    return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function formatBytes(value) {
    const bytes = Number(value || 0);
    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

async function loadCatalogs() {
    const data = await pedagogicalManagementApi.catalogs({
        scope: "mine",
        school_id: selectedSchoolId.value || undefined,
    });
    catalogs.value = data;
    if (!selectedSchoolId.value && (data.selected_school_id || data.schools.length === 1)) {
        selectedSchoolId.value = String(data.selected_school_id || data.schools[0].id);
        if (!data.selected_school_id) return loadCatalogs();
    }
    if (data.owners.length === 1) form.owner_user_id = String(data.owners[0].id);
}

async function loadList() {
    const response = await pedagogicalManagementApi.list({
        scope: "mine",
        school_id: selectedSchoolId.value || undefined,
        per_page: 100,
    });
    list.value = response.data || [];
    if (detail.value) {
        const refreshed = list.value.find((item) => item.id === detail.value.id);
        if (refreshed) await openDetail(refreshed);
    }
}

async function initialize() {
    loading.value = true;
    try {
        await loadCatalogs();
        await loadList();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible cargar Gestión pedagógica."));
    } finally {
        loading.value = false;
    }
}

function openSubmission() {
    Object.assign(form, {
        owner_user_id: catalogs.value.owners.length === 1 ? String(catalogs.value.owners[0].id) : "",
        subject_id: "",
        course_id: "",
    });
    selectedFile.value = null;
    showSubmissionModal.value = true;
}

function closeSubmission() {
    if (!submitting.value) showSubmissionModal.value = false;
}

function closeDetail() {
    if (!submitting.value) showDetailModal.value = false;
}

function chooseFile(file) {
    const validation = validateInstrumentCandidate(file, catalogs.value.max_file_kb);
    if (validation) {
        selectedFile.value = null;
        notify("danger", validation);
        return;
    }
    selectedFile.value = file;
}

async function submitInstrument() {
    const validation = validateInstrumentCandidate(selectedFile.value, catalogs.value.max_file_kb);
    if (!selectedSchoolId.value || !form.owner_user_id || !form.subject_id || !form.course_id || validation) {
        notify("danger", validation || "Completa la asignatura, el curso y el archivo del instrumento.");
        return;
    }
    submitting.value = true;
    try {
        const payload = new FormData();
        payload.append("school_id", selectedSchoolId.value);
        payload.append("owner_user_id", form.owner_user_id);
        payload.append("subject_id", form.subject_id);
        payload.append("course_id", form.course_id);
        payload.append("file", selectedFile.value);
        const response = await pedagogicalManagementApi.create(payload);
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = "";
        await loadList();
        showSubmissionModal.value = false;
        await openDetail(response.instrument);
        notify("success", "Instrumento enviado a Revisión documental.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible enviar el instrumento."));
    } finally {
        submitting.value = false;
    }
}

async function openDetail(item) {
    try {
        detail.value = await pedagogicalManagementApi.show(item.id);
        showDetailModal.value = true;
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible abrir el instrumento."));
    }
}

async function uploadRectification(event) {
    const file = event.target.files?.[0];
    event.target.value = "";
    const validation = validateInstrumentCandidate(file, catalogs.value.max_file_kb);
    if (validation) return notify("danger", validation);
    submitting.value = true;
    try {
        const payload = new FormData();
        payload.append("file", file);
        await pedagogicalManagementApi.uploadVersion(detail.value.id, payload);
        await loadList();
        notify("success", "Versión rectificada enviada. El historial anterior se conservó.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible cargar la rectificación."));
    } finally {
        submitting.value = false;
    }
}

async function fileAction(file, download, instrument = detail.value) {
    if (!instrument?.id) return;
    try {
        const blob = download
            ? await pedagogicalManagementApi.downloadFile(instrument.id, file.id)
            : await pedagogicalManagementApi.viewFile(instrument.id, file.id);
        if (download) saveBlob(blob, file.original_filename);
        else openBlob(blob);
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible abrir el archivo."));
    }
}

async function downloadSharedReport(reviewItem = detail.value?.latest_review) {
    if (!reviewItem?.can_download_ai_report || !reviewItem.ai_report?.report || !detail.value) return;
    downloadingReport.value = true;
    try {
        await downloadPedagogicalAiReportPdf(detail.value, reviewItem.ai_report);
        notify("success", "Informe de retroalimentación descargado.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible descargar el informe."));
    } finally {
        downloadingReport.value = false;
    }
}

function handleKeydown(event) {
    if (event.key !== "Escape") return;
    if (showDetailModal.value) closeDetail();
    else if (showSubmissionModal.value) closeSubmission();
}

watch([showSubmissionModal, showDetailModal], ([submissionOpen, detailOpen]) => {
    document.body.classList.toggle("pedagogical-modal-open", submissionOpen || detailOpen);
});
onMounted(() => {
    window.addEventListener("keydown", handleKeydown);
    initialize();
});
onBeforeUnmount(() => {
    window.removeEventListener("keydown", handleKeydown);
    document.body.classList.remove("pedagogical-modal-open");
});
</script>

<template>
    <Layout>
        <div class="pedagogical-page container-fluid py-4">
            <div class="hero-card mb-4">
                <div>
                    <span class="eyebrow">GESTIÓN PEDAGÓGICA</span>
                    <h1>Mis instrumentos de evaluación</h1>
                    <p>Revisa tus envíos, consulta el resultado documental y responde con una nueva versión cuando corresponda.</p>
                </div>
                <div class="hero-tools">
                    <div class="hero-stat">
                        <strong>{{ list.length }}</strong>
                        <span>{{ list.length === 1 ? "envío" : "envíos" }}</span>
                    </div>
                    <button class="btn new-submission-btn" type="button" @click="openSubmission">
                        <i class="bx bx-plus"></i>
                        Nuevo envío
                    </button>
                </div>
            </div>

            <div v-if="message.text" class="alert" :class="`alert-${message.tone}`">{{ message.text }}</div>
            <LoadingState v-if="loading" message="Cargando instrumentos..." />

            <template v-else>
                <section class="submissions-card card border-0 shadow-sm">
                    <div class="card-header submissions-header bg-white border-0">
                        <div>
                            <span class="section-kicker">SEGUIMIENTO PERSONAL</span>
                            <h2 class="section-title">Mis envíos</h2>
                            <p>Esta tabla contiene únicamente instrumentos enviados por tu usuario.</p>
                        </div>
                        <button class="btn btn-primary secondary-new-button" type="button" @click="openSubmission">
                            <i class="bx bx-plus me-1"></i>Nuevo envío
                        </button>
                    </div>

                    <div class="table-toolbar">
                        <div class="search-control">
                            <i class="bx bx-search"></i>
                            <input v-model="searchTerm" type="search" class="form-control" placeholder="Buscar por instrumento, asignatura o curso" aria-label="Buscar en mis envíos" />
                        </div>
                        <select v-model="statusFilter" class="form-select filter-select" aria-label="Filtrar por estado">
                            <option value="">Todos los estados</option>
                            <option v-for="status in availableStatuses" :key="status" :value="status">{{ workflowPresentation(status).label }}</option>
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table class="instrument-table">
                            <thead>
                                <tr>
                                    <th>Instrumento</th>
                                    <th>Asignatura y curso</th>
                                    <th>Fecha de envío</th>
                                    <th>Versión</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in filteredList" :key="item.id">
                                    <td data-label="Instrumento">
                                        <div class="instrument-name">
                                            <span class="document-icon" :class="{ word: instrumentFileExtension(item.latest_file?.original_filename) === 'docx' }"><i class="bx" :class="instrumentFileIcon(item.latest_file?.original_filename)"></i></span>
                                            <div>
                                                <strong>{{ item.title }}</strong>
                                                <small>{{ item.latest_file?.original_filename || "Instrumento PDF o Word" }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Asignatura y curso">
                                        <strong class="cell-primary">{{ item.subject?.name || "Sin asignatura" }}</strong>
                                        <small class="cell-secondary">{{ item.courses?.map((course) => course.name).join(", ") || "Sin curso" }}</small>
                                    </td>
                                    <td data-label="Fecha de envío">
                                        <span class="date-cell">{{ formatDate(item.submitted_at || item.created_at) }}</span>
                                    </td>
                                    <td data-label="Versión">
                                        <span class="version-chip">v{{ item.latest_file?.version || 1 }}</span>
                                    </td>
                                    <td data-label="Estado">
                                        <span class="status-pill" :class="`tone-${workflowPresentation(item.workflow_status).tone}`">
                                            {{ workflowPresentation(item.workflow_status).label }}
                                        </span>
                                    </td>
                                    <td data-label="Acciones" class="actions-cell">
                                        <button v-if="item.latest_file" class="icon-action" type="button" :title="instrumentFileExtension(item.latest_file.original_filename) === 'pdf' ? 'Ver PDF' : 'Descargar Word'" :aria-label="instrumentFileExtension(item.latest_file.original_filename) === 'pdf' ? 'Ver PDF' : 'Descargar Word'" @click="fileAction(item.latest_file, instrumentFileExtension(item.latest_file.original_filename) !== 'pdf', item)">
                                            <i class="bx" :class="instrumentFileExtension(item.latest_file.original_filename) === 'pdf' ? 'bx-show' : 'bx-download'"></i>
                                        </button>
                                        <button class="detail-action" type="button" @click="openDetail(item)">
                                            Ver detalle<i class="bx bx-right-arrow-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!filteredList.length">
                                    <td colspan="6">
                                        <div class="empty-state table-empty">
                                            <span class="empty-icon"><i class="bx bx-file-blank"></i></span>
                                            <h3>{{ list.length ? "No hay coincidencias" : "Aún no tienes envíos" }}</h3>
                                            <p>{{ list.length ? "Prueba con otro término o estado." : "Envía tu primer instrumento para iniciar la revisión documental." }}</p>
                                            <button v-if="!list.length" class="btn btn-primary" type="button" @click="openSubmission">
                                                <i class="bx bx-plus me-1"></i>Crear primer envío
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <span>{{ filteredList.length }} de {{ list.length }} {{ list.length === 1 ? "envío" : "envíos" }}</span>
                        <span><i class="bx bx-lock-alt"></i> Vista privada del docente</span>
                    </div>
                </section>
            </template>
        </div>

        <Teleport to="body">
            <div v-if="showSubmissionModal" class="pedagogical-modal-backdrop" role="presentation" @mousedown.self="closeSubmission">
                <section class="pedagogical-modal submission-dialog" role="dialog" aria-modal="true" aria-labelledby="submission-modal-title">
                    <header class="modal-heading">
                        <div class="modal-heading-icon"><i class="bx bx-file"></i></div>
                        <div>
                            <span class="section-kicker">GESTIÓN PEDAGÓGICA</span>
                            <h2 id="submission-modal-title">Nuevo envío</h2>
                            <p>El nombre del archivo se utilizará como título del instrumento.</p>
                        </div>
                        <button class="modal-close" type="button" aria-label="Cerrar nuevo envío" :disabled="submitting" @click="closeSubmission"><i class="bx bx-x"></i></button>
                    </header>

                    <form class="modal-form" @submit.prevent="submitInstrument">
                        <div class="form-intro">
                            <i class="bx bx-lock-alt"></i>
                            <span>El instrumento quedará asociado al colegio institucional y exclusivamente a tu usuario.</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="submission-subject">Asignatura</label>
                                <select id="submission-subject" v-model="form.subject_id" class="form-select" required>
                                    <option value="">Selecciona una asignatura</option>
                                    <option v-for="subject in catalogs.subjects" :key="subject.id" :value="String(subject.id)">{{ subject.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="submission-course">Curso</label>
                                <select id="submission-course" v-model="form.course_id" class="form-select" required>
                                    <option value="">Selecciona un curso</option>
                                    <option v-for="course in catalogs.courses" :key="course.id" :value="String(course.id)">{{ course.name }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="submission-file">Instrumento PDF o Word</label>
                                <div class="file-picker" :class="{ selected: selectedFile }">
                                    <span class="file-picker-icon" :class="{ word: instrumentFileExtension(selectedFile) === 'docx' }"><i class="bx" :class="selectedFile ? instrumentFileIcon(selectedFile) : 'bx-file'"></i></span>
                                    <div>
                                        <strong>{{ selectedFile?.name || "Selecciona el instrumento" }}</strong>
                                        <small>{{ selectedFile ? "Archivo listo para enviar" : "PDF o Word (.docx) de hasta " + Math.round(catalogs.max_file_kb / 1024) + " MB" }}</small>
                                    </div>
                                    <label class="btn btn-outline-primary mb-0" for="submission-file">Elegir archivo</label>
                                    <input id="submission-file" ref="fileInput" class="visually-hidden" type="file" accept="application/pdf,.pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.docx" required @change="chooseFile($event.target.files?.[0])" />
                                </div>
                            </div>
                        </div>

                        <footer class="modal-actions">
                            <button class="btn btn-light" type="button" :disabled="submitting" @click="closeSubmission">Cancelar</button>
                            <button class="btn btn-primary px-4" type="submit" :disabled="submitting">
                                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                <i v-else class="bx bx-send me-1"></i>{{ submitting ? "Enviando..." : "Enviar a revisión" }}
                            </button>
                        </footer>
                    </form>
                </section>
            </div>

            <div v-if="showDetailModal && detail" class="pedagogical-modal-backdrop" role="presentation" @mousedown.self="closeDetail">
                <section class="pedagogical-modal detail-dialog" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
                    <header class="detail-modal-heading">
                        <div>
                            <span class="status-pill mb-2" :class="`tone-${workflowPresentation(detail.workflow_status).tone}`">{{ workflowPresentation(detail.workflow_status).label }}</span>
                            <h2 id="detail-modal-title">{{ detail.title }}</h2>
                            <p>{{ detail.subject?.name }} · {{ detail.courses?.map((course) => course.name).join(", ") }}</p>
                        </div>
                        <div class="detail-header-actions">
                            <button v-if="detail.latest_file" class="btn btn-outline-primary" type="button" @click="fileAction(detail.latest_file, instrumentFileExtension(detail.latest_file.original_filename) !== 'pdf')"><i class="bx me-1" :class="instrumentFileExtension(detail.latest_file.original_filename) === 'pdf' ? 'bx-show' : 'bx-download'"></i>{{ instrumentFileExtension(detail.latest_file.original_filename) === 'pdf' ? "Ver PDF" : "Descargar Word" }}</button>
                            <button class="modal-close" type="button" aria-label="Cerrar detalle" :disabled="submitting" @click="closeDetail"><i class="bx bx-x"></i></button>
                        </div>
                    </header>

                    <div class="detail-modal-body">
                        <div v-if="pendingCorrection" class="correction-panel mb-4">
                            <div class="d-flex gap-3">
                                <span class="correction-icon"><i class="bx bx-revision"></i></span>
                                <div class="flex-grow-1">
                                    <h3>Se requiere una rectificación</h3>
                                    <p>{{ detail.latest_review?.coordinator_notes }}</p>
                                    <label class="btn btn-danger mb-0">
                                        <i class="bx bx-upload me-1"></i>{{ submitting ? "Cargando..." : "Cargar versión rectificada" }}
                                        <input class="d-none" type="file" accept="application/pdf,.pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.docx" :disabled="submitting" @change="uploadRectification" />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div v-if="detail.latest_review" class="review-note mb-4">
                            <div class="review-heading">
                                <strong>{{ detail.latest_review.decision_label }}</strong>
                                <span>{{ formatDate(detail.latest_review.reviewed_at) }}</span>
                            </div>
                            <p v-if="detail.latest_review.coordinator_notes" class="mb-3">{{ detail.latest_review.coordinator_notes }}</p>
                            <div v-if="detail.latest_review.guidance_documents?.length" class="guidance-list">
                                <article v-for="document in detail.latest_review.guidance_documents" :key="document.id">
                                    <i class="bx bx-book-open"></i>
                                    <div><strong>{{ document.title }}</strong><p>{{ document.content }}</p></div>
                                </article>
                            </div>
                            <div v-if="detail.latest_review.ai_report?.report" class="ai-shared mt-3">
                                <div class="ai-shared-heading"><div><span><i class="bx bx-file-find"></i> Informe de retroalimentación compartido</span><small>Corresponde exactamente al informe revisado por coordinación.</small></div><button v-if="detail.latest_review.can_download_ai_report" class="btn btn-sm btn-ai-download" type="button" :disabled="downloadingReport" @click="downloadSharedReport(detail.latest_review)"><span v-if="downloadingReport" class="spinner-border spinner-border-sm me-1"></span><i v-else class="bx bx-download me-1"></i>Descargar informe</button></div>
                                <PedagogicalAiReportContent class="mt-3" :report="detail.latest_review.ai_report.report" />
                            </div>
                        </div>

                        <template v-if="detail.reviews?.length">
                            <h3 class="history-title">Historial de revisiones y rectificaciones</h3>
                            <div class="review-timeline mb-4">
                                <article v-for="item in detail.reviews" :key="item.id">
                                    <span class="review-dot" :class="item.decision === 'rectification_requested' ? 'rectification' : 'approval'"><i class="bx" :class="item.decision === 'rectification_requested' ? 'bx-revision' : 'bx-check'"></i></span>
                                    <div class="flex-grow-1"><div class="d-flex flex-wrap gap-2 align-items-center"><strong>{{ item.decision_label }}</strong><small>versión {{ item.file?.version }} · {{ formatDate(item.reviewed_at) }}</small></div><p v-if="item.coordinator_notes">{{ item.coordinator_notes }}</p><small v-if="item.guidance_documents?.length">Documentos enviados: {{ item.guidance_documents.map((document) => document.title).join(", ") }}</small><button v-if="item.can_download_ai_report && item.ai_report?.report" class="history-report-download" type="button" :disabled="downloadingReport" @click="downloadSharedReport(item)"><i class="bx bx-download"></i> Descargar informe enviado</button></div>
                                </article>
                            </div>
                        </template>

                        <h3 class="history-title">Historial de versiones</h3>
                        <div class="version-timeline">
                            <div v-for="file in detail.files" :key="file.id" class="version-item">
                                <span class="version-number">v{{ file.version }}</span>
                                <div class="flex-grow-1">
                                    <strong>{{ file.original_filename }}</strong>
                                    <small>{{ formatDate(file.uploaded_at) }} · {{ formatBytes(file.file_size) }} · {{ file.uploaded_by?.name }}</small>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-light" type="button" title="Ver" @click="fileAction(file, false)"><i class="bx bx-show"></i></button>
                                    <button class="btn btn-sm btn-light" type="button" title="Descargar" @click="fileAction(file, true)"><i class="bx bx-download"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </Teleport>
    </Layout>
</template>

<style scoped>
.pedagogical-page { --ink: #17243a; --navy: #173f67; --teal: #1a8d86; color: var(--ink); }
.hero-card { background: linear-gradient(125deg, #133a61 0%, #17656d 58%, #1a8d86 100%); border-radius: 24px; color: #fff; padding: 2rem 2.25rem; display: flex; align-items: center; justify-content: space-between; gap: 2rem; box-shadow: 0 18px 45px rgba(18, 63, 91, .2); overflow: hidden; position: relative; }
.hero-card::after { content: ""; position: absolute; width: 250px; height: 250px; right: -90px; top: -120px; border: 1px solid rgba(255,255,255,.14); border-radius: 50%; }
.hero-card h1 { color: #fff; font-size: clamp(1.65rem, 3vw, 2.35rem); margin: .35rem 0 .45rem; }
.hero-card p { margin: 0; max-width: 720px; opacity: .86; }
.eyebrow, .section-kicker { font-size: .7rem; font-weight: 800; letter-spacing: .16em; }
.eyebrow { opacity: .78; }.section-kicker { color: #1b827e; }
.hero-tools { position: relative; z-index: 1; display: flex; align-items: center; gap: .8rem; }
.hero-stat { min-width: 104px; text-align: center; padding: .75rem 1.1rem; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); border-radius: 16px; backdrop-filter: blur(8px); }
.hero-stat strong { display: block; font-size: 1.65rem; line-height: 1.1; }.hero-stat span { font-size: .78rem; opacity: .8; }
.new-submission-btn { height: 52px; padding: 0 1.2rem; color: #174662; background: #fff; border-radius: 14px; font-weight: 800; box-shadow: 0 10px 28px rgba(10, 38, 61, .18); white-space: nowrap; }
.new-submission-btn:hover { color: #0f665f; background: #f5fffe; transform: translateY(-1px); }.new-submission-btn i { font-size: 1.25rem; vertical-align: -2px; }
.card { border-radius: 20px; }.submissions-card { overflow: hidden; }
.submissions-header { padding: 1.55rem 1.75rem 1.25rem; display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; }
.section-title { font-size: 1.4rem; color: var(--ink); margin: .25rem 0 .2rem; }.submissions-header p { color: #748092; margin: 0; }
.secondary-new-button { border-radius: 11px; padding: .65rem 1rem; font-weight: 700; }
.table-toolbar { display: flex; gap: .75rem; padding: 1rem 1.75rem; background: #f7fafc; border-top: 1px solid #edf1f4; border-bottom: 1px solid #edf1f4; }
.search-control { position: relative; flex: 1 1 360px; }.search-control i { position: absolute; z-index: 1; left: .9rem; top: 50%; transform: translateY(-50%); color: #758294; font-size: 1.1rem; }.search-control .form-control { padding-left: 2.6rem; }
.filter-select { flex: 0 0 210px; }.school-select { flex: 0 1 310px; }
.table-toolbar .form-control, .table-toolbar .form-select { min-height: 43px; border-color: #dfe6ec; border-radius: 11px; background-color: #fff; }
.table-responsive { overflow-x: auto; }.instrument-table { width: 100%; border-collapse: collapse; }
.instrument-table th { padding: .9rem 1rem; color: #7a8796; font-size: .69rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; border-bottom: 1px solid #edf1f4; }
.instrument-table th:first-child, .instrument-table td:first-child { padding-left: 1.75rem; }.instrument-table th:last-child, .instrument-table td:last-child { padding-right: 1.75rem; }
.instrument-table td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #edf1f4; }.instrument-table tbody tr { transition: background .18s ease; }.instrument-table tbody tr:hover { background: #f8fbfb; }
.instrument-name { display: flex; align-items: center; gap: .75rem; min-width: 220px; }.instrument-name strong, .instrument-name small, .cell-primary, .cell-secondary { display: block; }.instrument-name strong { color: #20304a; max-width: 280px; }.instrument-name small, .cell-secondary { color: #7c8898; font-size: .76rem; margin-top: .2rem; }.cell-primary { color: #34445b; font-size: .88rem; }.date-cell { color: #59687a; font-size: .82rem; white-space: nowrap; }
.document-icon { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 12px; display: grid; place-items: center; background: #fff0f0; color: #d84a4a; font-size: 1.35rem; }
.document-icon.word { color: #315ca8; background: #edf3ff; }
.version-chip { display: inline-grid; place-items: center; min-width: 34px; height: 30px; padding: 0 .45rem; border-radius: 9px; background: #edf4fa; color: #285576; font-size: .76rem; font-weight: 800; }
.status-pill { display: inline-flex; border-radius: 999px; padding: .4rem .7rem; font-size: .72rem; font-weight: 800; white-space: nowrap; }
.tone-info { color: #176a81; background: #e5f6fa; }.tone-warning { color: #8a5b00; background: #fff4d7; }.tone-danger { color: #9b2e36; background: #ffeaec; }.tone-success { color: #176a50; background: #e4f6ee; }.tone-neutral { color: #596273; background: #eff1f4; }
.actions-cell { text-align: right; white-space: nowrap; }.icon-action, .detail-action { border: 0; border-radius: 10px; transition: .18s ease; }.icon-action { width: 36px; height: 36px; color: #3f647d; background: #edf4f8; margin-right: .35rem; font-size: 1.05rem; }.detail-action { color: #176d70; background: #e7f6f4; padding: .54rem .75rem; font-size: .78rem; font-weight: 800; }.detail-action i { font-size: 1rem; vertical-align: -2px; margin-left: .2rem; }.icon-action:hover, .detail-action:hover { transform: translateY(-1px); filter: brightness(.97); }
.table-footer { padding: .85rem 1.75rem; display: flex; justify-content: space-between; gap: 1rem; color: #7b8796; background: #fbfcfd; font-size: .76rem; }.table-footer i { color: #27847f; vertical-align: -1px; }
.empty-state { min-height: 250px; display: grid; place-content: center; justify-items: center; text-align: center; color: #8390a1; padding: 2rem; }.empty-state h3 { color: #33445b; font-size: 1.05rem; margin: .8rem 0 .3rem; }.empty-state p { margin: 0 0 1rem; }.empty-icon { width: 58px; height: 58px; display: grid; place-items: center; border-radius: 18px; color: #43877f; background: #eaf7f5; }.empty-icon i { font-size: 1.8rem; }
.pedagogical-modal-backdrop { position: fixed; z-index: 1085; inset: 0; display: grid; place-items: center; padding: 1.25rem; background: rgba(14, 30, 47, .58); backdrop-filter: blur(5px); animation: modalFade .18s ease-out; }
.pedagogical-modal { width: min(100%, 720px); max-height: calc(100vh - 2.5rem); display: flex; flex-direction: column; overflow: hidden; background: #fff; border: 1px solid rgba(255,255,255,.6); border-radius: 22px; box-shadow: 0 26px 75px rgba(10, 28, 45, .3); animation: modalLift .22s ease-out; }
.modal-heading { padding: 1.45rem 1.55rem; display: grid; grid-template-columns: auto 1fr auto; gap: .9rem; align-items: start; background: linear-gradient(135deg, #f4fbfb, #f8faff); border-bottom: 1px solid #e8eef2; }.modal-heading h2, .detail-modal-heading h2 { color: #1d2d43; margin: .18rem 0 .18rem; font-size: 1.35rem; }.modal-heading p, .detail-modal-heading p { color: #748092; margin: 0; font-size: .85rem; }.modal-heading-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 14px; color: #fff; background: linear-gradient(135deg, #1b6872, #24978c); font-size: 1.3rem; }
.modal-close { width: 38px; height: 38px; border: 0; border-radius: 11px; display: grid; place-items: center; color: #657386; background: #edf2f5; font-size: 1.4rem; }.modal-close:hover { color: #27364a; background: #e2e9ee; }.modal-close:disabled { opacity: .55; }
.modal-form { overflow-y: auto; overflow-x: hidden; }.modal-form > .row { padding: 0 1.55rem 1.5rem; }.form-intro { margin: 1.25rem 1.55rem; display: flex; align-items: center; gap: .55rem; border-radius: 12px; padding: .72rem .85rem; color: #286d69; background: #edf8f6; font-size: .8rem; }.form-intro i { font-size: 1rem; }
.modal-form .form-label { color: #445268; font-weight: 700; font-size: .78rem; }.modal-form .form-select { min-height: 46px; border-color: #dce4ea; border-radius: 11px; }
.file-picker { display: grid; grid-template-columns: auto 1fr auto; gap: .8rem; align-items: center; padding: 1rem; border: 1px dashed #bac9d4; border-radius: 15px; background: #fafcfd; }.file-picker.selected { border-color: #5aa79e; background: #f3fbfa; }.file-picker-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 13px; color: #d14d54; background: #ffebec; font-size: 1.35rem; }.file-picker-icon.word { color: #315ca8; background: #edf3ff; }.file-picker strong, .file-picker small { display: block; }.file-picker strong { color: #34445a; overflow-wrap: anywhere; }.file-picker small { color: #7b8795; margin-top: .15rem; }
.modal-actions { position: sticky; bottom: 0; padding: 1rem 1.55rem; display: flex; justify-content: flex-end; gap: .65rem; background: #fff; border-top: 1px solid #e8edf1; }.modal-actions .btn { min-height: 42px; border-radius: 11px; font-weight: 700; }
.detail-dialog { width: min(100%, 960px); }.detail-modal-heading { padding: 1.35rem 1.55rem; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; background: #fff; border-bottom: 1px solid #e8edf1; }.detail-header-actions { display: flex; gap: .55rem; align-items: center; }.detail-header-actions .btn { border-radius: 10px; white-space: nowrap; }.detail-modal-body { overflow-y: auto; padding: 1.5rem; }
.correction-panel { background: linear-gradient(115deg, #fff3f1, #fff9f1); border: 1px solid #ffd6ce; border-radius: 18px; padding: 1.25rem; }.correction-panel h3 { font-size: 1.05rem; color: #9b3035; }.correction-icon { width: 46px; height: 46px; flex: 0 0 46px; display: grid; place-items: center; border-radius: 14px; color: #fff; background: #dc5960; font-size: 1.4rem; }
.review-note { background: #f6f9fc; border-radius: 18px; padding: 1.25rem; }.review-heading { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: .7rem; }.review-heading span { color: #718096; font-size: .8rem; }
.guidance-list article { display: flex; gap: .7rem; padding: .75rem 0; border-top: 1px solid #e4e9ef; }.guidance-list article i { color: var(--teal, #1a8d86); font-size: 1.2rem; }.guidance-list p { white-space: pre-line; margin: .25rem 0 0; font-size: .86rem; color: #536173; }
.ai-shared { background: #eeeafe; border-radius: 16px; padding: 1rem; }.ai-shared-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }.ai-shared-heading span,.ai-shared-heading small { display: block; }.ai-shared-heading span { color: #5946a8; font-weight: 800; }.ai-shared-heading small { margin-top: .15rem; color: #74699a; }.btn-ai-download { border: 1px solid #7562bc; border-radius: 9px; background: #fff; color: #5946a8; font-weight: 750; white-space: nowrap; }.history-report-download { display: inline-flex; align-items: center; gap: .3rem; margin-top: .45rem; padding: 0; border: 0; background: transparent; color: #6654b5; font-size: .75rem; font-weight: 750; }
.review-timeline { display: grid; gap: .7rem; }.review-timeline article { display: flex; gap: .75rem; border-left: 2px solid #e4e9ef; margin-left: 17px; padding: 0 0 .85rem 1.25rem; }.review-timeline article:last-child { padding-bottom: 0; }.review-timeline p { margin: .3rem 0; color: #536173; }.review-timeline small { color: #748092; }.review-dot { width: 34px; height: 34px; flex: 0 0 34px; margin-left: -2.35rem; border-radius: 50%; display: grid; place-items: center; color: #fff; }.review-dot.rectification { background: #dc5960; }.review-dot.approval { background: #2b9b72; }.ai-history { display: block; margin-top: .35rem; color: #6654b5; font-size: .78rem; font-weight: 700; }
.history-title { font-size: 1rem; margin-bottom: 1rem; }.version-timeline { display: grid; gap: .7rem; }.version-item { display: flex; align-items: center; gap: .8rem; border: 1px solid #edf0f4; border-radius: 14px; padding: .8rem; }.version-item strong, .version-item small { display: block; }.version-item small { color: #718096; }.version-number { background: #e7f2fa; color: var(--navy, #173f67); font-weight: 800; padding: .4rem .55rem; border-radius: 9px; }
:global(body.pedagogical-modal-open) { overflow: hidden; }
@keyframes modalFade { from { opacity: 0; } to { opacity: 1; } } @keyframes modalLift { from { opacity: 0; transform: translateY(12px) scale(.985); } to { opacity: 1; transform: none; } }
@media (max-width: 991px) { .hero-card { align-items: flex-start; }.hero-tools { flex-direction: column; }.hero-stat, .new-submission-btn { width: 100%; }.table-toolbar { flex-wrap: wrap; }.search-control { flex-basis: 100%; }.filter-select, .school-select { flex: 1 1 220px; } }
@media (max-width: 767px) { .pedagogical-page { padding-left: .75rem !important; padding-right: .75rem !important; }.hero-card { border-radius: 18px; padding: 1.35rem; flex-direction: column; gap: 1rem; }.hero-tools { width: 100%; flex-direction: row; }.hero-stat { width: auto; min-width: 88px; }.new-submission-btn { flex: 1; }.submissions-header { padding: 1.25rem; align-items: flex-start; }.secondary-new-button { display: none; }.table-toolbar { padding: .9rem 1rem; }.instrument-table thead { display: none; }.instrument-table, .instrument-table tbody { display: block; }.instrument-table tbody { padding: .7rem; }.instrument-table tbody tr { display: grid; gap: .65rem; padding: 1rem; margin-bottom: .7rem; border: 1px solid #e6edf1; border-radius: 16px; box-shadow: 0 5px 16px rgba(28, 54, 73, .04); }.instrument-table tbody tr:last-child { margin-bottom: 0; }.instrument-table td, .instrument-table th:first-child, .instrument-table td:first-child, .instrument-table th:last-child, .instrument-table td:last-child { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0; border: 0; text-align: right; }.instrument-table td::before { content: attr(data-label); color: #84909e; font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; text-align: left; }.instrument-table td:first-child { display: block; text-align: left; padding-bottom: .65rem; border-bottom: 1px solid #edf1f4; }.instrument-table td:first-child::before { display: none; }.instrument-table td[colspan] { display: block; }.instrument-table td[colspan]::before { display: none; }.instrument-name { min-width: 0; }.instrument-name strong { max-width: none; }.actions-cell { padding-top: .4rem !important; }.actions-cell .detail-action { flex: 1; }.table-footer { padding: .8rem 1rem; flex-direction: column; gap: .25rem; }.pedagogical-modal-backdrop { padding: 0; align-items: end; }.pedagogical-modal { max-height: 94vh; border-radius: 20px 20px 0 0; }.modal-heading, .detail-modal-heading { padding: 1.15rem; }.modal-heading { grid-template-columns: auto 1fr auto; }.modal-heading-icon { width: 40px; height: 40px; }.modal-form > .row { padding: 0 1.15rem 1.25rem; }.form-intro { margin: 1rem 1.15rem; }.file-picker { grid-template-columns: auto 1fr; }.file-picker .btn { grid-column: 1 / -1; }.modal-actions { padding: .9rem 1.15rem; }.detail-modal-heading { flex-direction: column; }.detail-header-actions { width: 100%; }.detail-header-actions .btn { flex: 1; }.detail-modal-body { padding: 1.15rem; }.review-heading { flex-direction: column; gap: .25rem; }.version-item { flex-wrap: wrap; }.version-item .btn-group { margin-left: auto; } }
@media (max-width: 767px) { .ai-shared-heading { align-items: stretch; flex-direction: column; }.btn-ai-download { width: 100%; } }
@media (prefers-reduced-motion: reduce) { .pedagogical-modal-backdrop, .pedagogical-modal { animation: none; } }
</style>
