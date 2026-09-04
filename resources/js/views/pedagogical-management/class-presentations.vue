<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import OptionGroup from "../../components/pedagogical-management/ClassPresentationOptionGroup.vue";
import CanvaConnectionCard from "../../components/pedagogical-management/CanvaConnectionCard.vue";
import CanvaTemplatePicker from "../../components/pedagogical-management/CanvaTemplatePicker.vue";
import ClassPresentationPipeline from "../../components/pedagogical-management/ClassPresentationPipeline.vue";
import ClassPresentationDeliverables from "../../components/pedagogical-management/ClassPresentationDeliverables.vue";
import { classPresentationsApi, isPresentationPending, statusPresentation } from "../../services/class-presentations-api";
import { canvaOAuthCallbackNotice, consumeCanvaOAuthCallback } from "../../services/canva-oauth-callback";
import { errorMessage, saveBlob } from "../../services/pedagogical-management-api";

const loading = ref(true);
const working = ref(false);
const activeTab = ref("new");
const step = ref(1);
const catalogs = ref({ schools: [], academic_years: [], courses: [], authors: [], options: {}, multiple_options: {}, option_descriptions: {}, style_profiles: {}, statuses: [] });
const subjects = ref([]);
const units = ref([]);
const objectives = ref([]);
const titleSuggestions = ref([]);
const presentations = ref([]);
const detail = ref(null);
const canvaConnection = ref({ configured: false, connected: false, capabilities: {} });
const canvaTemplates = ref([]);
const canvaContinuation = ref(null);
const canvaQuery = ref("");
const canvaBusy = ref(false);
const canvaTemplatesLoading = ref(false);
const referenceFiles = ref([]);
const fileInput = ref(null);
const message = reactive({ tone: "", text: "" });
const filters = reactive({ academic_year_id: "", course_id: "", subject_id: "", unit_id: "", status: "", user_id: "" });
let pollTimer = null;

const form = reactive({
    school_id: "", academic_year_id: "", course_id: "", subject_id: "", unit_id: "", learning_objective_ids: [], title: "",
    class_type: "introduction", duration_minutes: 45, slide_count: 12, prior_knowledge: "automatic", depth: "automatic",
    methodology: ["automatic"], tone: "motivating", opening: "problematizing_question", activity: ["group"], assessment: ["exit_ticket"],
    aspect_ratio: "wide", visual_style: "institutional", palette: "institutional", visual_resources: ["editable"],
    speaker_notes: true, bibliography: true, web_research: false, generate_pdf: true, generate_activity: true,
    generate_assessment: true, include_cover: true, include_objectives: true, include_synthesis: true, include_closure: true,
    presentation_provider: "canva", canva_template_id: "", canva_template_title: "", generate_teacher_guide: true,
});

const currentSchool = computed(() => catalogs.value.schools.find((item) => String(item.id) === String(form.school_id)));
const currentYear = computed(() => catalogs.value.academic_years.find((item) => String(item.id) === String(form.academic_year_id)));
const currentCourse = computed(() => catalogs.value.courses.find((item) => String(item.id) === String(form.course_id)));
const currentSubject = computed(() => subjects.value.find((item) => String(item.id) === String(form.subject_id)));
const currentUnit = computed(() => units.value.find((item) => String(item.id) === String(form.unit_id)));
const selectedObjectives = computed(() => objectives.value.filter((item) => form.learning_objective_ids.map(String).includes(String(item.id))));
const pendingExists = computed(() => presentations.value.some(isPresentationPending));
const filteredPresentations = computed(() => presentations.value.filter((item) => Object.entries(filters).every(([key, value]) => {
    if (!value) return true;
    if (key === "user_id") return String(item.author?.id) === String(value);
    return String(item[key]?.id ?? item[key] ?? "") === String(value);
})));
const visualStyles = computed(() => Object.entries(catalogs.value.options?.visual_style || {}).filter(([key]) => key !== "children" || currentCourse.value?.allows_children_style));
const selectedStyleProfile = computed(() => catalogs.value.style_profiles?.[form.visual_style] || null);
const selectedCanvaTemplate = computed(() => canvaTemplates.value.find((item) => String(item.id) === String(form.canva_template_id)) || null);
const canvaConfigured = computed(() => canvaConnection.value.configured === true || catalogs.value.canva_configured === true || catalogs.value.canva?.configured === true);
const canvaConnected = computed(() => canvaConnection.value.connected === true && canvaConnection.value.needs_reauthorization !== true && canvaConnection.value.reconnect_required !== true);
const canvaAutofillAvailable = computed(() => canvaConnection.value.autofill_available === true
    || ["enterprise", "development_trial"].includes(String(canvaConnection.value.mode || "").toLowerCase()));
const canvaGenerationReady = computed(() => canvaConfigured.value && canvaConnected.value && canvaAutofillAvailable.value && Boolean(form.canva_template_id) && selectedCanvaTemplate.value?.compatible === true);
const uniqueHistoryCatalog = (key) => computed(() => {
    const seen = new Map();
    presentations.value.forEach((item) => { if (item[key]?.id) seen.set(String(item[key].id), item[key]); });
    return [...seen.values()].sort((a, b) => String(a.name || a.title || "").localeCompare(String(b.name || b.title || ""), "es"));
});
const historyCourses = uniqueHistoryCatalog("course");
const historySubjects = uniqueHistoryCatalog("subject");
const historyUnits = uniqueHistoryCatalog("unit");

function optionEntries(key) { return Object.entries(catalogs.value.options?.[key] || {}); }
function optionDescriptions(key) { return catalogs.value.option_descriptions?.[key] || {}; }
function selectionRule(key) { return catalogs.value.multiple_options?.[key] || null; }
function notify(tone, text) { Object.assign(message, { tone, text }); window.setTimeout(() => { if (message.text === text) message.text = ""; }, 7000); }
function formatDate(value) { return value ? new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)) : "—"; }
function isCanvaPresentation(item) {
    const provider = item?.presentation_provider || item?.provider || item?.configuration?.presentation_provider;
    return provider === "canva" || Boolean(item?.canva?.design_id || item?.canva?.brand_template_id);
}
function canvaStatusLabel(item) {
    return {
        pending: "pendiente",
        submitting: "enviando contenido",
        in_progress: "creando diseño",
        success: "diseño listo",
        failed: "requiere atención",
    }[String(item?.canva?.status || "pending")] || "pendiente";
}
function downloadableFiles(item) {
    return (item?.files || []).filter((file) => ["pptx", "pdf", "canva_pptx", "canva_pdf", "teacher_guide_pdf", "guide_pdf", "teacher_guide"].includes(String(file.type)));
}
function selectOption(key, value) {
    const rule = selectionRule(key);
    if (!rule) { form[key] = value; return; }
    const current = Array.isArray(form[key]) ? [...form[key]] : [];
    const selectedIndex = current.map(String).indexOf(String(value));
    if (selectedIndex >= 0) {
        current.splice(selectedIndex, 1);
        form[key] = current;
        return;
    }
    const exclusive = (rule.exclusive || []).map(String);
    if (exclusive.includes(String(value))) {
        form[key] = [value];
        return;
    }
    const compatible = current.filter((item) => !exclusive.includes(String(item)));
    if (compatible.length >= Number(rule.max || 1)) {
        notify("danger", `Puedes seleccionar hasta ${rule.max} alternativas en ${key === 'visual_resources' ? 'recursos visuales' : catalogs.value.options?.[key] ? key : 'esta sección'}.`);
        return;
    }
    form[key] = [...compatible, value];
}
function toggleObjective(id) { const value = String(id); const index = form.learning_objective_ids.map(String).indexOf(value); if (index >= 0) form.learning_objective_ids.splice(index, 1); else form.learning_objective_ids.push(id); }
function isSelected(key, value) { return (Array.isArray(form[key]) ? form[key] : [form[key]]).map(String).includes(String(value)); }
function selectedLabels(key) { return (Array.isArray(form[key]) ? form[key] : [form[key]]).map((value) => catalogs.value.options?.[key]?.[value] || value).join(" · "); }

async function loadOptions() {
    const data = await classPresentationsApi.options({ school_id: form.school_id || undefined, academic_year_id: form.academic_year_id || undefined });
    catalogs.value = data;
    if (!form.school_id) form.school_id = String(data.selected_school_id || data.schools[0]?.id || "");
    if (!form.academic_year_id) form.academic_year_id = String(data.selected_academic_year_id || data.academic_years[0]?.id || "");
}

async function loadCanvaConnection({ notifyOnSuccess = false } = {}) {
    if (!form.school_id) return;
    canvaBusy.value = true;
    try {
        const data = await classPresentationsApi.canvaConnection({ school_id: form.school_id });
        canvaConnection.value = {
            configured: data.configured ?? catalogs.value.canva_configured ?? catalogs.value.canva?.configured ?? false,
            ...data,
            reconnect_required: data.reconnect_required ?? data.needs_reauthorization ?? false,
        };
        if (!canvaConnected.value || !canvaAutofillAvailable.value) {
            canvaTemplates.value = [];
            canvaContinuation.value = null;
            form.canva_template_id = "";
            form.canva_template_title = "";
        } else if (notifyOnSuccess) {
            notify("success", "Conexión Canva verificada. Ya puedes elegir una plantilla compatible.");
        }
    } catch (error) {
        canvaConnection.value = { configured: catalogs.value.canva_configured === true || catalogs.value.canva?.configured === true, connected: false, capabilities: {} };
        if (notifyOnSuccess) notify("danger", errorMessage(error, "No fue posible verificar la conexión con Canva."));
    } finally {
        canvaBusy.value = false;
    }
}

async function connectCanva() {
    if (!form.school_id) return notify("danger", "Selecciona un establecimiento antes de conectar Canva.");
    const authorizationTab = window.open("about:blank", "_blank");
    if (authorizationTab) authorizationTab.opener = null;
    canvaBusy.value = true;
    try {
        const data = await classPresentationsApi.beginCanvaAuthorization({ school_id: Number(form.school_id), redirect_to: window.location.pathname });
        const authorizationUrl = data.authorization_url || data.url;
        const parsed = new URL(authorizationUrl);
        if (parsed.protocol !== "https:" || (parsed.hostname !== "canva.com" && !parsed.hostname.endsWith(".canva.com"))) throw new Error("Invalid Canva authorization URL");
        if (authorizationTab) authorizationTab.location.replace(parsed.toString());
        else window.location.assign(parsed.toString());
        notify("success", "Autoriza la cuenta en la pestaña de Canva y luego pulsa “Verificar conexión”.");
    } catch (error) {
        authorizationTab?.close();
        notify("danger", errorMessage(error, "No fue posible iniciar la autorización con Canva."));
    } finally {
        canvaBusy.value = false;
    }
}

async function disconnectCanva() {
    const result = await Swal.fire({ icon: "warning", title: "Cambiar cuenta de Canva", text: "Se cerrará la conexión actual. Los diseños ya creados se conservarán.", showCancelButton: true, confirmButtonText: "Desconectar", cancelButtonText: "Cancelar", confirmButtonColor: "#9c4221" });
    if (!result.isConfirmed) return;
    canvaBusy.value = true;
    try {
        await classPresentationsApi.disconnectCanva({ school_id: Number(form.school_id) });
        canvaConnection.value = { configured: true, connected: false, capabilities: {} };
        canvaTemplates.value = []; form.canva_template_id = ""; form.canva_template_title = "";
        notify("success", "La cuenta Canva fue desconectada. Puedes autorizar otra cuenta.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible desconectar Canva."));
    } finally {
        canvaBusy.value = false;
    }
}

async function loadCanvaTemplates({ append = false, query = canvaQuery.value } = {}) {
    if (!canvaConnected.value || !canvaAutofillAvailable.value) return;
    canvaTemplatesLoading.value = true;
    try {
        const response = await classPresentationsApi.canvaTemplates({
            school_id: Number(form.school_id), query: query || undefined,
            continuation: append ? canvaContinuation.value || undefined : undefined, limit: 24,
        });
        const items = response.items || response.data || [];
        const normalized = items.map((item) => ({ ...item, thumbnail_url: item.thumbnail_url || item.thumbnail?.url }));
        canvaTemplates.value = append ? [...canvaTemplates.value, ...normalized.filter((candidate) => !canvaTemplates.value.some((item) => String(item.id) === String(candidate.id)))] : normalized;
        canvaContinuation.value = response.continuation || response.meta?.continuation || null;
        canvaQuery.value = query || "";
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible consultar las plantillas de Canva."));
    } finally {
        canvaTemplatesLoading.value = false;
    }
}

async function selectCanvaTemplate(template) {
    form.canva_template_id = String(template.id);
    form.canva_template_title = template.title || template.name || "Plantilla Canva";
    canvaTemplates.value = canvaTemplates.value.map((item) => String(item.id) === String(template.id) ? { ...item, compatible: null, validation_pending: true } : item);
    canvaTemplatesLoading.value = true;
    try {
        const validation = await classPresentationsApi.validateCanvaTemplate(template.id, { school_id: Number(form.school_id), slide_count: Number(form.slide_count) });
        const messages = (validation.missing_fields || []).map((field) => `Falta el campo Autofill ${field}.`);
        canvaTemplates.value = canvaTemplates.value.map((item) => String(item.id) === String(template.id) ? {
            ...item, compatible: validation.compatible !== false, validation_pending: false, dataset: validation.dataset,
            field_count: Object.keys(validation.dataset || {}).length,
            compatibility_messages: validation.compatible === false ? (messages.length ? messages : ["La plantilla no cumple el contrato requerido para esta clase."]) : [],
        } : item);
        if (validation.compatible === false) {
            form.canva_template_id = ""; form.canva_template_title = "";
            notify("danger", "La plantilla no contiene todos los campos Autofill necesarios para esta cantidad de diapositivas.");
        }
    } catch (error) {
        canvaTemplates.value = canvaTemplates.value.map((item) => String(item.id) === String(template.id) ? { ...item, compatible: false, validation_pending: false, compatibility_messages: ["No fue posible validar el contrato Autofill de esta plantilla."] } : item);
        form.canva_template_id = ""; form.canva_template_title = "";
        notify("danger", errorMessage(error, "No fue posible validar la plantilla Canva."));
    } finally {
        canvaTemplatesLoading.value = false;
    }
}

async function changeSchool() {
    form.academic_year_id = ""; form.course_id = ""; form.subject_id = ""; form.unit_id = ""; form.learning_objective_ids = [];
    form.canva_template_id = ""; form.canva_template_title = ""; canvaTemplates.value = [];
    subjects.value = []; units.value = []; objectives.value = [];
    await loadOptions();
    await loadCanvaConnection();
}

async function changeYear() {
    form.course_id = ""; form.subject_id = ""; form.unit_id = ""; form.learning_objective_ids = [];
    subjects.value = []; units.value = []; objectives.value = [];
    await loadOptions();
}

async function changeCourse() {
    form.subject_id = ""; form.unit_id = ""; form.learning_objective_ids = [];
    units.value = []; objectives.value = [];
    if (!form.course_id) return;
    subjects.value = await classPresentationsApi.subjects(form.course_id, { school_id: form.school_id, academic_year_id: form.academic_year_id });
    if (form.visual_style === "children" && !currentCourse.value?.allows_children_style) form.visual_style = "institutional";
}

async function changeSubject() {
    form.unit_id = ""; form.learning_objective_ids = []; objectives.value = [];
    if (!form.subject_id) return;
    units.value = await classPresentationsApi.units(form.subject_id, { school_id: form.school_id, academic_year_id: form.academic_year_id, course_id: form.course_id });
}

async function changeUnit() {
    form.learning_objective_ids = [];
    if (!form.unit_id) { objectives.value = []; return; }
    const unitRouteId = currentUnit.value?.public_id || form.unit_id;
    objectives.value = await classPresentationsApi.objectives(unitRouteId, { school_id: form.school_id, academic_year_id: form.academic_year_id, course_id: form.course_id, subject_id: form.subject_id });
}

function validateStep(current = step.value) {
    if (current === 1 && (!form.school_id || !form.academic_year_id || !form.course_id || !form.subject_id || !form.unit_id || !form.learning_objective_ids.length)) return "Completa la selección curricular y elige al menos un objetivo.";
    if (current === 2 && ["class_type", "duration_minutes", "slide_count", "prior_knowledge", "depth", "tone", "opening"].some((key) => form[key] === "")) return "Completa la configuración pedagógica.";
    if (current === 2 && ["methodology", "activity", "assessment"].some((key) => !Array.isArray(form[key]) || !form[key].length)) return "Selecciona al menos una alternativa en cada decisión pedagógica combinable.";
    if (current === 3 && (!form.aspect_ratio || !form.visual_style || !form.palette || !Array.isArray(form.visual_resources) || !form.visual_resources.length || !form.title)) return "Selecciona el formato visual, al menos un recurso y uno de los títulos sugeridos.";
    if (current === 3 && !canvaConfigured.value) return "Canva no está configurado en este entorno. Un administrador debe completar la integración.";
    if (current === 3 && !canvaConnected.value) return "Conecta y verifica tu cuenta de Canva antes de continuar.";
    if (current === 3 && !canvaAutofillAvailable.value) return "La cuenta Canva conectada no dispone de Brand Templates con Autofill.";
    if (current === 3 && (!form.canva_template_id || selectedCanvaTemplate.value?.compatible !== true)) return "Selecciona una plantilla Canva compatible y espera que termine su validación.";
    return "";
}

async function nextStep() {
    const error = validateStep();
    if (error) return notify("danger", error);
    if (step.value === 2) {
        await loadTitleSuggestions();
        if (canvaConnected.value && canvaAutofillAvailable.value && !canvaTemplates.value.length) await loadCanvaTemplates();
    }
    step.value = Math.min(4, step.value + 1);
    window.scrollTo({ top: 0, behavior: "smooth" });
}
function previousStep() { step.value = Math.max(1, step.value - 1); window.scrollTo({ top: 0, behavior: "smooth" }); }

async function loadTitleSuggestions() {
    working.value = true;
    try {
        titleSuggestions.value = await classPresentationsApi.titles({ school_id: Number(form.school_id), academic_year_id: Number(form.academic_year_id), course_id: Number(form.course_id), subject_id: Number(form.subject_id), unit_id: Number(form.unit_id), learning_objective_ids: form.learning_objective_ids.map(Number), class_type: form.class_type });
        if (!titleSuggestions.value.includes(form.title)) form.title = titleSuggestions.value[0] || "";
    } catch (error) { notify("danger", errorMessage(error, "No fue posible sugerir títulos.")); }
    finally { working.value = false; }
}

function chooseFiles(files) {
    const candidates = Array.from(files || []);
    const max = Number(catalogs.value.max_reference_files || 5);
    const maxBytes = Number(catalogs.value.max_reference_file_kb || 15360) * 1024;
    const allowed = ["pdf", "docx", "pptx", "txt", "md"];
    const invalid = candidates.find((file) => !allowed.includes(file.name.split(".").pop()?.toLowerCase()) || file.size > maxBytes);
    if (invalid || candidates.length > max) return notify("danger", `Adjunta hasta ${max} archivos PDF, DOCX, PPTX, TXT o Markdown dentro del tamaño permitido.`);
    referenceFiles.value = candidates;
}

async function generatePresentation() {
    const error = validateStep(3);
    if (error) return notify("danger", error);
    working.value = true;
    try {
        const payload = new FormData();
        Object.entries(form).forEach(([key, value]) => {
            if (Array.isArray(value)) value.forEach((item) => payload.append(`${key}[]`, item));
            else payload.append(key, typeof value === "boolean" ? (value ? "1" : "0") : value);
        });
        referenceFiles.value.forEach((file) => payload.append("reference_files[]", file));
        const created = await classPresentationsApi.create(payload);
        step.value = 1; referenceFiles.value = []; if (fileInput.value) fileInput.value.value = "";
        await loadHistory(); await openDetail(created.id);
        notify("success", "La presentación quedó en cola. Puedes salir y volver: el proceso y los archivos son persistentes.");
    } catch (error) { notify("danger", errorMessage(error, "No fue posible solicitar la presentación.")); }
    finally { working.value = false; }
}

async function loadHistory() {
    const response = await classPresentationsApi.list({ school_id: form.school_id || undefined, per_page: 100 });
    presentations.value = response.data || [];
    schedulePoll();
}

function schedulePoll() {
    if (pollTimer) window.clearTimeout(pollTimer);
    if (pendingExists.value) pollTimer = window.setTimeout(async () => { try { await loadHistory(); if (detail.value) detail.value = await classPresentationsApi.show(detail.value.id); } catch { schedulePoll(); } }, Number(catalogs.value.poll_interval_ms || 5000));
}

async function openDetail(id) {
    working.value = true;
    try { detail.value = await classPresentationsApi.show(id); activeTab.value = "detail"; }
    catch (error) { notify("danger", errorMessage(error, "No fue posible abrir la presentación.")); }
    finally { working.value = false; }
}

async function downloadFile(file) {
    try { const blob = await classPresentationsApi.download(file.download_url); saveBlob(blob, file.filename); }
    catch (error) { notify("danger", errorMessage(error, "No fue posible descargar el archivo.")); }
}

async function openCanvaEditor(item) {
    const editorTab = window.open("about:blank", "_blank");
    if (editorTab) editorTab.opener = null;
    canvaBusy.value = true;
    try {
        const data = await classPresentationsApi.canvaEditLink(item.id);
        const editUrl = data.edit_url || data.url;
        const parsed = new URL(editUrl);
        if (parsed.protocol !== "https:" || (parsed.hostname !== "canva.com" && !parsed.hostname.endsWith(".canva.com"))) throw new Error("Invalid Canva edit URL");
        if (editorTab) editorTab.location.replace(parsed.toString());
        else window.location.assign(parsed.toString());
    } catch (error) {
        editorTab?.close();
        notify("danger", errorMessage(error, "No fue posible abrir el diseño en Canva. Verifica la conexión e intenta nuevamente."));
    } finally {
        canvaBusy.value = false;
    }
}

async function syncCanvaDesign(item) {
    canvaBusy.value = true;
    try {
        await classPresentationsApi.syncCanvaDesign(item.id);
        detail.value = await classPresentationsApi.show(item.id);
        await loadHistory();
        notify("success", "El envío a Canva quedó en cola. La guía y los respaldos técnicos permanecen disponibles.");
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible reintentar la creación del diseño en Canva."));
    } finally {
        canvaBusy.value = false;
    }
}

async function regenerate(item) {
    const result = await Swal.fire({ icon: "question", title: "Crear una nueva versión", text: "La versión actual y sus archivos se conservarán.", showCancelButton: true, confirmButtonText: "Regenerar", cancelButtonText: "Cancelar", confirmButtonColor: "#173f67" });
    if (!result.isConfirmed) return;
    working.value = true;
    try { const created = await classPresentationsApi.regenerate(item.id); await loadHistory(); await openDetail(created.id); notify("success", `Versión ${created.version} creada sin eliminar la anterior.`); }
    catch (error) { notify("danger", errorMessage(error, "No fue posible regenerar la presentación.")); }
    finally { working.value = false; }
}

async function retry(item) {
    working.value = true;
    try { await classPresentationsApi.retry(item.id); await loadHistory(); await openDetail(item.id); notify("success", "La presentación volvió a la cola."); }
    catch (error) { notify("danger", errorMessage(error, "No fue posible reintentar la presentación.")); }
    finally { working.value = false; }
}

async function archive(item) {
    const result = await Swal.fire({ icon: "warning", title: "Archivar presentación", text: "Los archivos y versiones seguirán almacenados.", showCancelButton: true, confirmButtonText: "Archivar", cancelButtonText: "Cancelar", confirmButtonColor: "#9c4221" });
    if (!result.isConfirmed) return;
    try { await classPresentationsApi.archive(item.id); detail.value = null; activeTab.value = "history"; await loadHistory(); notify("success", "Presentación archivada."); }
    catch (error) { notify("danger", errorMessage(error, "No fue posible archivar.")); }
}

function newClass() { activeTab.value = "new"; detail.value = null; }

watch(() => form.activity, (value) => { form.generate_activity = Array.isArray(value) ? !value.includes("none") : value !== "none"; }, { deep: true });
watch(() => form.assessment, (value) => { form.generate_assessment = Array.isArray(value) ? !value.includes("none") : value !== "none"; }, { deep: true });
watch(() => form.slide_count, () => { form.canva_template_id = ""; form.canva_template_title = ""; });
onMounted(async () => {
    const canvaCallback = consumeCanvaOAuthCallback();
    try {
        await loadOptions();
        await loadCanvaConnection();
        const callbackNotice = canvaOAuthCallbackNotice(canvaCallback, canvaConnection.value);
        if (callbackNotice) notify(callbackNotice.tone, callbackNotice.text);
        await loadHistory();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible cargar el Generador de clases."));
    } finally {
        loading.value = false;
    }
});
onBeforeUnmount(() => { if (pollTimer) window.clearTimeout(pollTimer); });
</script>

<template>
    <Layout>
        <div class="class-generator container-fluid py-4">
            <header class="module-header">
                <div>
                    <span class="eyebrow">Gestión pedagógica</span>
                    <h1>Generador de clases</h1>
                    <p>ChatGPT desarrolla el contenido y la guía docente; Canva crea la presentación editable con una plantilla validada.</p>
                </div>
                <div class="header-mark" aria-hidden="true"><i class="bx bx-slideshow"></i></div>
            </header>

            <div v-if="message.text" class="module-alert" :class="`is-${message.tone}`" role="alert">{{ message.text }}</div>
            <LoadingState v-if="loading" />
            <template v-else>
                <nav class="view-tabs" aria-label="Vistas del generador">
                    <button type="button" :class="{ active: activeTab === 'new' }" @click="newClass"><i class="bx bx-plus-circle"></i>Nueva clase</button>
                    <button type="button" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'; detail = null"><i class="bx bx-history"></i>Mis presentaciones <span>{{ presentations.length }}</span></button>
                    <button v-if="detail" type="button" class="active"><i class="bx bx-detail"></i>Detalle · v{{ detail.version }}</button>
                </nav>

                <section v-if="activeTab === 'new'" class="wizard-shell">
                    <div class="stepper" aria-label="Progreso">
                        <button v-for="item in 4" :key="item" type="button" :class="{ active: step === item, complete: step > item }" :disabled="item > step" @click="step = item">
                            <span>{{ step > item ? '✓' : item }}</span><small>{{ ['Currículum', 'Clase', 'Diseño Canva', 'Confirmación'][item - 1] }}</small>
                        </button>
                    </div>

                    <div v-if="step === 1" class="wizard-content">
                        <div class="section-heading"><span>01</span><div><h2>Selección curricular</h2><p>Las opciones se encadenan para impedir combinaciones curriculares inválidas.</p></div></div>
                        <div class="row g-3 selector-grid">
                            <div v-if="catalogs.schools.length > 1" class="col-lg-4"><label>Establecimiento</label><select v-model="form.school_id" class="form-select" @change="changeSchool"><option value="">Seleccionar</option><option v-for="item in catalogs.schools" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></div>
                            <div class="col-lg-4"><label>Año académico</label><select v-model="form.academic_year_id" class="form-select" @change="changeYear"><option value="">Seleccionar</option><option v-for="item in catalogs.academic_years" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></div>
                            <div class="col-lg-4"><label>Curso o nivel</label><select v-model="form.course_id" class="form-select" @change="changeCourse"><option value="">Seleccionar</option><option v-for="item in catalogs.courses" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></div>
                            <div class="col-lg-4"><label>Asignatura</label><select v-model="form.subject_id" class="form-select" :disabled="!form.course_id" @change="changeSubject"><option value="">Seleccionar</option><option v-for="item in subjects" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select><small v-if="form.course_id && !subjects.length">El curso aún no tiene programas curriculares publicados.</small></div>
                            <div class="col-lg-8"><label>Unidad</label><select v-model="form.unit_id" class="form-select" :disabled="!form.subject_id" @change="changeUnit"><option value="">Seleccionar</option><option v-for="item in units" :key="item.id" :value="String(item.id)">{{ item.code }} · {{ item.title }}</option></select></div>
                        </div>
                        <div class="objective-section"><div class="field-heading"><h3>Objetivos de aprendizaje</h3><span>{{ form.learning_objective_ids.length }} seleccionados</span></div><div class="objective-grid"><button v-for="item in objectives" :key="item.id" type="button" class="objective-card" :class="{ selected: form.learning_objective_ids.map(String).includes(String(item.id)) }" @click="toggleObjective(item.id)"><strong>{{ item.code }}</strong><span>{{ item.description }}</span><i class="bx" :class="form.learning_objective_ids.map(String).includes(String(item.id)) ? 'bx-check-circle' : 'bx-circle'"></i></button><div v-if="form.unit_id && !objectives.length" class="empty-inline">La unidad seleccionada no tiene objetivos vinculados.</div></div></div>
                    </div>

                    <div v-else-if="step === 2" class="wizard-content">
                        <div class="section-heading"><span>02</span><div><h2>Configuración de la clase</h2><p>Combina estrategias compatibles y mantén una sola elección cuando las alternativas sean excluyentes.</p></div></div>
                        <div class="selection-guide"><i class="bx bx-select-multiple"></i><div><strong>Ahora puedes combinar alternativas</strong><p>Metodologías, actividades y evaluaciones aceptan hasta tres elecciones. “Automática” y “Sin…” reemplazan cualquier combinación.</p></div></div>
                        <OptionGroup label="Tipo de clase" option-key="class_type" :entries="optionEntries('class_type')" :value="form.class_type" @select="selectOption" />
                        <div class="row g-4"><div class="col-lg-6"><OptionGroup label="Duración" option-key="duration_minutes" :entries="(catalogs.options.duration_minutes || []).map(v => [v, `${v} minutos`])" :value="form.duration_minutes" @select="selectOption" /></div><div class="col-lg-6"><OptionGroup label="Número exacto de diapositivas" option-key="slide_count" :entries="(catalogs.options.slide_count || []).map(v => [v, `${v} diapositivas`])" :value="form.slide_count" @select="selectOption" /></div></div>
                        <OptionGroup label="Conocimientos previos" option-key="prior_knowledge" :entries="optionEntries('prior_knowledge')" :value="form.prior_knowledge" @select="selectOption" />
                        <OptionGroup label="Nivel de profundidad" option-key="depth" :entries="optionEntries('depth')" :value="form.depth" @select="selectOption" />
                        <OptionGroup label="Estrategias metodológicas" option-key="methodology" :entries="optionEntries('methodology')" :value="form.methodology" multiple :max="selectionRule('methodology')?.max" :descriptions="optionDescriptions('methodology')" hint="Selecciona una estrategia principal y hasta dos complementarias." variant="cards" @select="selectOption" />
                        <OptionGroup label="Tono" option-key="tone" :entries="optionEntries('tone')" :value="form.tone" @select="selectOption" />
                        <OptionGroup label="Tipo de apertura" option-key="opening" :entries="optionEntries('opening')" :value="form.opening" @select="selectOption" />
                        <OptionGroup label="Actividades" option-key="activity" :entries="optionEntries('activity')" :value="form.activity" multiple :max="selectionRule('activity')?.max" :descriptions="optionDescriptions('activity')" hint="Puedes articular distintos momentos de participación dentro de la misma clase." variant="cards" @select="selectOption" />
                        <OptionGroup label="Evaluaciones" option-key="assessment" :entries="optionEntries('assessment')" :value="form.assessment" multiple :max="selectionRule('assessment')?.max" :descriptions="optionDescriptions('assessment')" hint="Combina evidencias diferentes solo cuando todas comprueben aprendizajes trabajados." variant="cards" @select="selectOption" />
                    </div>

                    <div v-else-if="step === 3" class="wizard-content">
                        <div class="section-heading"><span>03</span><div><h2>Contenido y diseño Canva</h2><p>Elige una dirección visual y confirma la plantilla real que Canva utilizará para construir la presentación.</p></div></div>
                        <CanvaConnectionCard
                            :connection="{ ...canvaConnection, configured: canvaConfigured }"
                            :busy="canvaBusy"
                            @connect="connectCanva"
                            @disconnect="disconnectCanva"
                            @refresh="loadCanvaConnection({ notifyOnSuccess: true }).then(() => canvaConnected && loadCanvaTemplates())"
                        />
                        <OptionGroup label="Relación de aspecto" option-key="aspect_ratio" :entries="optionEntries('aspect_ratio')" :value="form.aspect_ratio" @select="selectOption" />
                        <OptionGroup label="Estilo visual" option-key="visual_style" :entries="visualStyles" :value="form.visual_style" :descriptions="optionDescriptions('visual_style')" hint="Elige uno: esta decisión define la gramática visual completa." variant="style" @select="selectOption" />
                        <div v-if="selectedStyleProfile" class="style-contract-card" :class="`style-${form.visual_style}`"><div class="style-contract-mark"><i class="bx" :class="form.visual_style === 'children' ? 'bxs-happy-heart-eyes' : 'bx-shape-circle'"></i></div><div><span>Contrato visual activo</span><h3>{{ catalogs.options.visual_style?.[form.visual_style] }}</h3><p>{{ selectedStyleProfile.description }}</p><div class="style-traits"><small v-for="trait in selectedStyleProfile.traits" :key="trait">{{ trait }}</small></div></div><strong>Obligatorio</strong></div>
                        <OptionGroup label="Paleta" option-key="palette" :entries="optionEntries('palette')" :value="form.palette" :descriptions="optionDescriptions('palette')" variant="cards" @select="selectOption" />
                        <OptionGroup label="Recursos visuales" option-key="visual_resources" :entries="optionEntries('visual_resources')" :value="form.visual_resources" multiple :max="selectionRule('visual_resources')?.max" :descriptions="optionDescriptions('visual_resources')" hint="Selecciona hasta cuatro. “Automático” no se combina con otros recursos." variant="cards" @select="selectOption" />
                        <CanvaTemplatePicker
                            v-model="form.canva_template_id"
                            :templates="canvaTemplates"
                            :loading="canvaTemplatesLoading"
                            :continuation="canvaContinuation"
                            :required-slide-count="form.slide_count"
                            :required-aspect-ratio="catalogs.options.aspect_ratio?.[form.aspect_ratio]"
                            :required-style="catalogs.options.visual_style?.[form.visual_style]"
                            :disabled="!canvaConnected || !canvaAutofillAvailable"
                            @select="selectCanvaTemplate"
                            @search="query => loadCanvaTemplates({ query })"
                            @refresh="loadCanvaTemplates({ query: canvaQuery })"
                            @load-more="loadCanvaTemplates({ append: true })"
                        />
                        <div class="teacher-guide-contract"><div class="guide-mark"><i class="bx bxs-file-pdf"></i></div><div><span>Entregable pedagógico incluido</span><h3>Guía docente PDF</h3><p>ChatGPT generará un guion separado con propósito, tiempos, preguntas orientadoras, indicaciones por diapositiva y criterios de evaluación.</p></div><strong><i class="bx bx-lock-alt"></i>Siempre incluida</strong></div>
                        <div class="toggle-grid">
                            <label v-for="item in [['speaker_notes','Notas del presentador'],['bibliography','Bibliografía'],['web_research','Investigación web'],['generate_pdf','PDF adicional'],['generate_activity','Generar actividad'],['generate_assessment','Generar evaluación'],['include_cover','Incluir portada'],['include_objectives','Incluir objetivos'],['include_synthesis','Incluir síntesis'],['include_closure','Incluir cierre']]" :key="item[0]" class="toggle-card"><span><strong>{{ item[1] }}</strong><small>{{ form[item[0]] ? 'Sí' : 'No' }}</small></span><input v-model="form[item[0]]" type="checkbox" role="switch"><i></i></label>
                        </div>
                        <div class="title-section"><div class="field-heading"><h3>Título sugerido</h3><span>Selecciona uno</span></div><div class="title-grid"><button v-for="title in titleSuggestions" :key="title" type="button" :class="{ selected: form.title === title }" @click="form.title = title"><i class="bx bx-text"></i><span>{{ title }}</span><i class="bx" :class="form.title === title ? 'bx-check-circle' : 'bx-circle'"></i></button></div></div>
                        <div class="upload-zone" @click="fileInput?.click()" @keydown.enter="fileInput?.click()" tabindex="0" role="button"><input ref="fileInput" type="file" multiple accept=".pdf,.docx,.pptx,.txt,.md" class="d-none" @change="chooseFiles($event.target.files)"><i class="bx bx-cloud-upload"></i><strong>Adjuntar material de consulta</strong><span>PDF, DOCX, PPTX, TXT o Markdown · máximo {{ catalogs.max_reference_files }} archivos</span><div v-if="referenceFiles.length" class="file-chips"><span v-for="file in referenceFiles" :key="file.name"><i class="bx bx-file"></i>{{ file.name }}</span></div></div>
                    </div>

                    <div v-else class="wizard-content">
                        <div class="section-heading"><span>04</span><div><h2>Confirmación</h2><p>Revisa la síntesis antes de iniciar el trabajo en cola.</p></div></div>
                        <div class="summary-grid"><article><small>Curso</small><strong>{{ currentCourse?.name }}</strong></article><article><small>Asignatura</small><strong>{{ currentSubject?.name }}</strong></article><article><small>Unidad</small><strong>{{ currentUnit?.title }}</strong></article><article><small>Clase</small><strong>{{ catalogs.options.class_type?.[form.class_type] }} · {{ form.duration_minutes }} min</strong></article><article><small>Presentación</small><strong>{{ form.slide_count }} diapositivas · {{ catalogs.options.aspect_ratio?.[form.aspect_ratio] }}</strong></article><article><small>Entregables</small><strong>Diseño Canva + guía docente PDF + respaldos técnicos</strong></article><article><small>Metodologías</small><strong>{{ selectedLabels('methodology') }}</strong></article><article><small>Actividades</small><strong>{{ selectedLabels('activity') }}</strong></article><article><small>Evaluaciones</small><strong>{{ selectedLabels('assessment') }}</strong></article><article class="summary-style"><small>Dirección visual</small><strong>{{ catalogs.options.visual_style?.[form.visual_style] }} · {{ catalogs.options.palette?.[form.palette] }}</strong><span>{{ selectedStyleProfile?.description }}</span></article><article><small>Recursos visuales</small><strong>{{ selectedLabels('visual_resources') }}</strong></article><article><small>Notas y fuentes</small><strong>{{ form.speaker_notes ? 'Notas' : 'Sin notas' }} · {{ form.bibliography ? 'Bibliografía' : 'Sin bibliografía' }}</strong></article><article class="summary-canva"><small>Diseño Canva</small><strong>{{ form.canva_template_title || 'Plantilla pendiente' }}</strong><span>Canva aplicará la plantilla; ChatGPT no decidirá la composición final.</span></article><article class="summary-guide"><small>Guía docente</small><strong>PDF separado e incluido</strong><span>Guion con tiempos, mediación y orientaciones por diapositiva.</span></article></div>
                        <div class="summary-objectives"><small>Objetivos seleccionados</small><span v-for="item in selectedObjectives" :key="item.id"><strong>{{ item.code }}</strong>{{ item.description }}</span></div>
                        <div class="generation-note"><i class="bx bx-time-five"></i><div><strong>ChatGPT y Canva trabajarán en etapas trazables</strong><p>Primero se crea el contenido y la guía PDF; después Canva aplica la plantilla y el sistema valida las exportaciones. Puedes cerrar esta página durante el proceso.</p></div></div>
                    </div>

                    <footer class="wizard-actions"><button v-if="step > 1" type="button" class="btn btn-outline-secondary" :disabled="working" @click="previousStep"><i class="bx bx-left-arrow-alt"></i>Volver</button><span></span><button v-if="step < 4" type="button" class="btn btn-primary" :disabled="working" @click="nextStep">Continuar<i class="bx bx-right-arrow-alt"></i></button><button v-else type="button" class="btn btn-generate" :disabled="working || !catalogs.openai_configured || !canvaGenerationReady" @click="generatePresentation"><span v-if="working" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-palette"></i>Crear clase con ChatGPT + Canva</button></footer>
                    <div v-if="!catalogs.openai_configured" class="configuration-warning"><i class="bx bx-error-circle"></i>OpenAI no está configurado en este entorno. El formulario se puede revisar, pero la generación está deshabilitada.</div>
                    <div v-if="!canvaConfigured" class="configuration-warning is-canva"><i class="bx bx-error-circle"></i>Canva no está configurado en este entorno. Un administrador debe completar la integración antes de generar.</div>
                    <div v-else-if="!canvaConnected" class="configuration-warning is-canva"><i class="bx bx-link-alt"></i>Conecta y verifica una cuenta Canva habilitada antes de generar.</div>
                </section>

                <section v-else-if="activeTab === 'history'" class="history-shell">
                    <div class="history-heading"><div><h2>Mis presentaciones</h2><p>Versiones y archivos disponibles de forma permanente.</p></div><button type="button" class="btn btn-primary" @click="newClass"><i class="bx bx-plus"></i>Nueva clase</button></div>
                    <div class="filter-bar"><select v-model="filters.academic_year_id"><option value="">Todos los años</option><option v-for="item in catalogs.academic_years" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="filters.course_id"><option value="">Todos los cursos</option><option v-for="item in historyCourses" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="filters.subject_id"><option value="">Todas las asignaturas</option><option v-for="item in historySubjects" :key="item.id" :value="item.id">{{ item.name }}</option></select><select v-model="filters.unit_id"><option value="">Todas las unidades</option><option v-for="item in historyUnits" :key="item.id" :value="item.id">{{ item.code }} · {{ item.title }}</option></select><select v-model="filters.status"><option value="">Todos los estados</option><option v-for="item in catalogs.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select><select v-if="catalogs.authors.length > 1" v-model="filters.user_id"><option value="">Todos los autores</option><option v-for="item in catalogs.authors" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
                    <div class="presentation-table-wrap"><table class="presentation-table"><thead><tr><th>Presentación</th><th>Currículum</th><th>Autor / fecha</th><th>Estado</th><th>Entregables</th><th></th></tr></thead><tbody><tr v-for="item in filteredPresentations" :key="item.id"><td data-label="Presentación"><strong>{{ item.title }}</strong><span>Versión {{ item.version }} · <b class="provider-label" :class="{ 'is-canva': isCanvaPresentation(item) }">{{ isCanvaPresentation(item) ? 'Canva' : 'PowerPoint heredado' }}</b></span></td><td data-label="Currículum"><strong>{{ item.course?.name }}</strong><span>{{ item.subject?.name }} · {{ item.unit?.code }}</span></td><td data-label="Autor / fecha"><strong>{{ item.author?.name }}</strong><span>{{ formatDate(item.created_at) }}</span></td><td data-label="Estado"><span class="status-badge" :class="`is-${statusPresentation(item.status).tone}`">{{ statusPresentation(item.status).label }}</span><small v-if="isCanvaPresentation(item)" class="canva-status-line" :class="{ 'is-failed': item.canva?.status === 'failed', 'is-ready': item.canva?.status === 'success' }">Canva: {{ canvaStatusLabel(item) }}</small><div v-if="item.progress < 100 && item.status !== 'failed'" class="mini-progress"><i :style="{ width: `${item.progress}%` }"></i></div></td><td data-label="Entregables"><span class="file-count">{{ downloadableFiles(item).length }} disponibles</span><small v-if="isCanvaPresentation(item) && item.canva?.brand_template_title" class="template-table-name">{{ item.canva.brand_template_title }}</small></td><td class="row-actions"><button type="button" @click="openDetail(item.id)">Ver detalle<i class="bx bx-right-arrow-alt"></i></button></td></tr><tr v-if="!filteredPresentations.length"><td colspan="6" class="empty-state"><i class="bx bx-slideshow"></i><strong>Aún no hay presentaciones para estos filtros</strong><span>Crea una clase y su historial aparecerá aquí.</span></td></tr></tbody></table></div>
                </section>

                <section v-else-if="detail" class="detail-shell">
                    <div class="detail-heading"><button type="button" class="back-button" @click="activeTab = 'history'; detail = null"><i class="bx bx-left-arrow-alt"></i>Historial</button><div><span>{{ detail.subject?.name }} · {{ detail.course?.name }}</span><h2>{{ detail.title }}</h2><p>{{ detail.unit?.code }} · Versión {{ detail.version }} · {{ formatDate(detail.created_at) }}</p></div><span class="status-badge large" :class="`is-${statusPresentation(detail.status).tone}`">{{ statusPresentation(detail.status).label }}</span></div>
                    <div v-if="detail.status !== 'ready' && detail.status !== 'failed'" class="progress-panel"><div><strong>{{ statusPresentation(detail.status).label }}</strong><span>{{ detail.progress }}%</span></div><div class="progress-track"><i :style="{ width: `${detail.progress}%` }"></i></div><p>La página se actualiza cada {{ Math.round((catalogs.poll_interval_ms || 5000) / 1000) }} segundos.</p></div>
                    <div v-if="detail.status === 'failed'" class="failure-panel"><i class="bx bx-error"></i><div><strong>No fue posible completar esta versión</strong><p>{{ detail.failure_message }}</p><small>{{ detail.failure_code }}</small></div><button v-if="detail.can?.regenerate" type="button" class="btn btn-danger" @click="retry(detail)">Reintentar</button></div>
                    <ClassPresentationPipeline v-if="isCanvaPresentation(detail)" :presentation="detail" />
                    <div class="detail-grid"><article><small>Duración</small><strong>{{ detail.configuration?.duration_minutes }} minutos</strong></article><article><small>Diapositivas</small><strong>{{ detail.configuration?.slide_count }}</strong></article><article><small>Estilo solicitado</small><strong>{{ detail.configuration?.labels?.visual_style }}</strong></article><article><small>Contenido</small><strong>{{ detail.model || 'Pendiente' }}</strong></article><article><small>Motor visual</small><strong>{{ isCanvaPresentation(detail) ? 'Canva Autofill' : 'PowerPoint heredado' }}</strong></article></div>
                    <ClassPresentationDeliverables :presentation="detail" :busy="canvaBusy" @download="downloadFile" @edit-canva="openCanvaEditor(detail)" @sync-canva="syncCanvaDesign(detail)" />
                    <div v-if="detail.preview_files?.length" class="preview-panel"><div class="field-heading"><h3>Previsualización</h3><span>{{ detail.preview_files.length }} diapositivas</span></div><div class="preview-grid"><figure v-for="preview in detail.preview_files" :key="preview.id"><img :src="preview.url" :alt="`Diapositiva ${preview.slide_number}`"><figcaption>{{ preview.slide_number }}</figcaption></figure></div></div>
                    <div class="detail-actions"><button v-if="detail.can?.regenerate && detail.status !== 'failed'" type="button" class="btn btn-primary" @click="regenerate(detail)"><i class="bx bx-refresh"></i>Regenerar como nueva versión</button><button v-if="detail.can?.archive" type="button" class="btn btn-outline-danger" @click="archive(detail)"><i class="bx bx-archive"></i>Archivar</button></div>
                </section>
            </template>
        </div>
    </Layout>
</template>

<style scoped>
.module-alert.is-warning{border-left-color:#d28a25;color:#805a1e;background:#fffaf2}
.class-generator{--navy:#173f67;--teal:#1a8d86;--ink:#17243a;--muted:#68798c;--line:#dfe7ee;--soft:#f4f8fa;color:var(--ink);max-width:1500px}.module-header{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1.55rem 1.75rem;background:#fff;border:1px solid var(--line);border-left:5px solid var(--teal);box-shadow:0 8px 22px rgba(29,53,76,.06)}.module-header h1{font-size:1.75rem;margin:.18rem 0 .35rem}.module-header p{margin:0;color:var(--muted);max-width:760px}.eyebrow{font-size:.72rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:var(--teal)}.header-mark{width:64px;height:64px;display:grid;place-items:center;background:#eaf5f4;color:var(--teal);font-size:2rem;border:1px solid #d6ebe8;border-radius:8px}.module-alert{margin:1rem 0;padding:.85rem 1rem;border-left:4px solid;background:#fff;border-top:1px solid var(--line);border-right:1px solid var(--line);border-bottom:1px solid var(--line)}.module-alert.is-danger{border-left-color:#c0392b;color:#8e2c22}.module-alert.is-success{border-left-color:#27875c;color:#206b4a}.view-tabs{display:flex;gap:.35rem;margin:1rem 0;background:#edf3f6;padding:.35rem;border:1px solid var(--line);width:max-content;max-width:100%}.view-tabs button{display:flex;align-items:center;gap:.45rem;border:0;background:transparent;color:#526476;font-weight:700;padding:.62rem .9rem;border-radius:5px}.view-tabs button.active{background:#fff;color:var(--navy);box-shadow:0 2px 8px rgba(23,63,103,.09)}.view-tabs button span{background:#e8f1f4;color:var(--teal);padding:.08rem .4rem;font-size:.72rem;border-radius:10px}.wizard-shell,.history-shell,.detail-shell{background:#fff;border:1px solid var(--line);box-shadow:0 12px 30px rgba(31,55,78,.06)}.stepper{display:grid;grid-template-columns:repeat(4,1fr);background:#f7fafb;border-bottom:1px solid var(--line)}.stepper button{border:0;border-right:1px solid var(--line);background:transparent;padding:1rem;display:flex;align-items:center;justify-content:center;gap:.65rem;color:#778698}.stepper button:last-child{border-right:0}.stepper button span{width:30px;height:30px;display:grid;place-items:center;border:1px solid #b9c6d1;border-radius:50%;font-weight:800}.stepper button small{font-weight:700}.stepper button.active{color:var(--navy);background:#fff}.stepper button.active span,.stepper button.complete span{background:var(--teal);border-color:var(--teal);color:#fff}.wizard-content{padding:1.75rem}.section-heading{display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.6rem}.section-heading>span{width:42px;height:42px;display:grid;place-items:center;background:#eaf5f4;color:var(--teal);font-weight:800;border-radius:6px}.section-heading h2,.history-heading h2,.detail-heading h2{font-size:1.25rem;margin:0 0 .25rem}.section-heading p,.history-heading p,.detail-heading p{margin:0;color:var(--muted)}.selector-grid label{display:block;font-size:.78rem;font-weight:800;color:#42556a;margin-bottom:.42rem}.selector-grid .form-select{min-height:44px;border-color:#ccd8e2;border-radius:5px}.selector-grid small{display:block;color:#9c4221;margin-top:.35rem}.objective-section,.title-section{margin-top:1.65rem}.field-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem}.field-heading h3{font-size:.98rem;margin:0}.field-heading span{font-size:.74rem;font-weight:800;color:var(--teal);text-transform:uppercase;letter-spacing:.06em}.objective-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.objective-card{position:relative;text-align:left;display:grid;grid-template-columns:auto 1fr auto;align-items:start;gap:.75rem;border:1px solid var(--line);background:#fff;padding:.9rem;border-radius:6px;color:var(--ink)}.objective-card strong{color:var(--navy);font-size:.78rem;white-space:nowrap}.objective-card span{font-size:.83rem;line-height:1.42;color:#4f6071}.objective-card i{font-size:1.15rem;color:#9aacb9}.objective-card.selected{border-color:var(--teal);background:#f2f9f8;box-shadow:inset 3px 0 var(--teal)}.objective-card.selected i{color:var(--teal)}.empty-inline{grid-column:1/-1;background:var(--soft);padding:1rem;color:var(--muted)}:deep(.option-group){border:0;padding:0;margin:0 0 1.45rem}:deep(.option-group legend){font-size:.82rem;font-weight:800;margin-bottom:.55rem;color:#42556a}:deep(.option-list){display:flex;flex-wrap:wrap;gap:.45rem}:deep(.option-list button){display:flex;align-items:center;gap:.38rem;border:1px solid #d7e1e8;background:#fff;padding:.58rem .72rem;color:#4d5f70;font-weight:650;font-size:.82rem;border-radius:5px}:deep(.option-list button i){font-size:1rem;color:#a3b1bc}:deep(.option-list button.selected){border-color:var(--teal);background:#eef8f6;color:#176d67}:deep(.option-list button.selected i){color:var(--teal)}.toggle-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.55rem;margin:1.2rem 0}.toggle-card{position:relative;display:flex;align-items:center;justify-content:space-between;gap:.6rem;border:1px solid var(--line);padding:.72rem;background:#fff;border-radius:6px;cursor:pointer}.toggle-card span{display:flex;flex-direction:column}.toggle-card strong{font-size:.78rem}.toggle-card small{font-size:.68rem;color:var(--teal)}.toggle-card input{position:absolute;opacity:0}.toggle-card>i{width:32px;height:18px;background:#cbd5df;border-radius:10px;position:relative}.toggle-card>i:after{content:"";position:absolute;width:14px;height:14px;background:#fff;top:2px;left:2px;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.15s}.toggle-card input:checked+i{background:var(--teal)}.toggle-card input:checked+i:after{left:16px}.title-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem}.title-grid button{display:grid;grid-template-columns:auto 1fr auto;gap:.65rem;align-items:center;text-align:left;border:1px solid var(--line);background:#fff;padding:.85rem;border-radius:6px;color:var(--ink);font-weight:700}.title-grid button>i:first-child{color:var(--navy);font-size:1.25rem}.title-grid button>i:last-child{color:#9aacb9}.title-grid button.selected{border-color:var(--teal);background:#f2f9f8}.title-grid button.selected>i:last-child{color:var(--teal)}.upload-zone{margin-top:1.25rem;border:1px dashed #9fb3c3;background:#f8fbfc;padding:1.25rem;text-align:center;display:flex;flex-direction:column;align-items:center;gap:.25rem;cursor:pointer}.upload-zone>i{font-size:1.8rem;color:var(--teal)}.upload-zone>span{color:var(--muted);font-size:.78rem}.file-chips{display:flex;flex-wrap:wrap;justify-content:center;gap:.35rem;margin-top:.55rem}.file-chips span{background:#eaf5f4;color:#176d67;padding:.3rem .55rem;font-size:.73rem;border-radius:4px}.summary-grid,.detail-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem}.summary-grid article,.detail-grid article{border:1px solid var(--line);padding:.85rem;background:#fbfcfd}.summary-grid small,.detail-grid small{display:block;color:var(--muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.28rem}.summary-grid strong,.detail-grid strong{font-size:.9rem}.summary-objectives{margin-top:1rem;border-left:4px solid var(--teal);background:#f4faf9;padding:1rem}.summary-objectives>small{display:block;font-weight:800;text-transform:uppercase;color:var(--teal);margin-bottom:.6rem}.summary-objectives>span{display:block;font-size:.82rem;margin:.35rem 0}.summary-objectives>span strong{margin-right:.5rem;color:var(--navy)}.generation-note{display:flex;gap:.75rem;margin-top:1rem;padding:1rem;background:#fff7ec;border:1px solid #f2d5aa}.generation-note>i{font-size:1.4rem;color:#b46b1f}.generation-note p{margin:.18rem 0 0;color:#72583c;font-size:.8rem}.wizard-actions{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.75rem;border-top:1px solid var(--line);background:#f9fbfc}.wizard-actions .btn{display:flex;align-items:center;gap:.4rem;border-radius:5px}.btn-primary,.btn-generate{background:var(--navy);border-color:var(--navy);color:#fff}.btn-generate{padding:.65rem 1rem;border-radius:5px}.configuration-warning{margin:0 1.75rem 1.25rem;padding:.8rem;background:#fff4e5;border-left:4px solid #d9822b;color:#79511d}.history-heading,.detail-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.5rem;border-bottom:1px solid var(--line)}.filter-bar{display:flex;flex-wrap:wrap;gap:.5rem;padding:.85rem 1.5rem;background:#f8fafb;border-bottom:1px solid var(--line)}.filter-bar select{min-width:170px;border:1px solid #ccd8e2;background:#fff;padding:.5rem .65rem;border-radius:4px;color:#45596c}.presentation-table-wrap{overflow:auto}.presentation-table{width:100%;border-collapse:collapse}.presentation-table th{padding:.72rem 1rem;text-align:left;background:#fbfcfd;color:#6b7b8c;font-size:.67rem;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)}.presentation-table td{padding:.85rem 1rem;border-bottom:1px solid #edf1f4;vertical-align:middle}.presentation-table td>strong,.presentation-table td>span{display:block}.presentation-table td>strong{font-size:.84rem}.presentation-table td>span{font-size:.72rem;color:var(--muted);margin-top:.18rem}.status-badge{display:inline-flex!important;width:max-content;padding:.26rem .5rem;border-radius:4px;font-weight:800!important;font-size:.68rem!important}.status-badge.is-success{background:#e7f6ee;color:#247450}.status-badge.is-danger{background:#fdeceb;color:#a43a31}.status-badge.is-info,.status-badge.is-primary{background:#eaf2fa;color:#285f94}.status-badge.is-warning{background:#fff3d8;color:#926516}.status-badge.is-neutral{background:#edf1f4;color:#5d6b78}.status-badge.large{font-size:.74rem!important;padding:.38rem .65rem}.mini-progress{width:100px;height:3px;background:#e4e9ed;margin-top:.35rem}.mini-progress i,.progress-track i{display:block;height:100%;background:var(--teal)}.row-actions button{border:0;background:transparent;color:var(--navy);font-weight:800;display:flex;align-items:center;gap:.25rem;white-space:nowrap}.empty-state{text-align:center!important;padding:3rem!important}.empty-state i,.empty-state strong,.empty-state span{display:block}.empty-state i{font-size:2rem;color:#a9bac6}.empty-state span{color:var(--muted);font-size:.8rem}.detail-heading{align-items:flex-start}.detail-heading>div{flex:1}.detail-heading>div>span{color:var(--teal);font-size:.72rem;font-weight:800;text-transform:uppercase}.back-button{border:0;background:#edf3f6;color:var(--navy);padding:.45rem .65rem;border-radius:4px}.progress-panel,.failure-panel{margin:1rem 1.5rem;padding:1rem;border:1px solid var(--line)}.progress-panel>div:first-child{display:flex;justify-content:space-between}.progress-track{height:8px;background:#e6ecef;margin:.65rem 0}.progress-panel p{margin:0;color:var(--muted);font-size:.75rem}.failure-panel{display:flex;align-items:center;gap:1rem;background:#fff7f6;border-color:#efd3d0}.failure-panel>i{font-size:1.7rem;color:#b43a31}.failure-panel>div{flex:1}.failure-panel p{margin:.15rem 0;color:#71433e}.failure-panel small{color:#a66b65}.detail-grid{padding:1rem 1.5rem;grid-template-columns:repeat(4,1fr)}.download-panel,.preview-panel{margin:0 1.5rem 1rem;border:1px solid var(--line);padding:1rem}.download-panel>div:first-child h3{font-size:.98rem;margin:0}.download-panel>div:first-child p{font-size:.75rem;color:var(--muted)}.download-panel>button{width:100%;display:grid;grid-template-columns:auto 1fr auto;gap:.75rem;align-items:center;text-align:left;border:1px solid var(--line);background:#fbfcfd;padding:.75rem;margin-top:.45rem;border-radius:5px}.download-panel>button>i:first-child{font-size:1.45rem;color:var(--navy)}.download-panel>button span{display:flex;flex-direction:column}.download-panel>button small{color:var(--muted)}.preview-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.65rem;max-height:620px;overflow:auto}.preview-grid figure{margin:0;border:1px solid var(--line);background:#f5f7f9;position:relative}.preview-grid img{display:block;width:100%;aspect-ratio:16/9;object-fit:contain;background:#fff}.preview-grid figcaption{position:absolute;right:.3rem;bottom:.25rem;background:rgba(23,36,58,.8);color:#fff;width:22px;height:22px;display:grid;place-items:center;font-size:.65rem;border-radius:3px}.detail-actions{display:flex;gap:.5rem;padding:0 1.5rem 1.5rem}.detail-actions .btn{display:flex;align-items:center;gap:.4rem}.file-count{white-space:nowrap}
.selection-guide{display:flex;align-items:flex-start;gap:.8rem;margin:-.35rem 0 1.5rem;padding:1rem 1.1rem;background:linear-gradient(135deg,#eff8f7,#f8fbfc);border:1px solid #d7eae7;border-left:4px solid var(--teal)}.selection-guide>i{font-size:1.45rem;color:var(--teal)}.selection-guide strong{display:block;color:var(--navy);font-size:.84rem}.selection-guide p{margin:.2rem 0 0;color:var(--muted);font-size:.76rem;line-height:1.45}.style-contract-card{position:relative;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:1rem;margin:-.35rem 0 1.5rem;padding:1.1rem;border:1px solid #d7e3ea;background:linear-gradient(135deg,#f8fafc,#fff);overflow:hidden}.style-contract-card:after{content:"";position:absolute;right:-35px;bottom:-45px;width:110px;height:110px;border-radius:50%;background:rgba(26,141,134,.08)}.style-contract-mark{width:54px;height:54px;display:grid;place-items:center;border-radius:14px;background:var(--navy);color:#fff;font-size:1.65rem}.style-contract-card>div:nth-child(2)>span{font-size:.65rem;font-weight:850;text-transform:uppercase;letter-spacing:.09em;color:var(--teal)}.style-contract-card h3{margin:.15rem 0;font-size:1rem}.style-contract-card p{margin:0;color:var(--muted);font-size:.77rem}.style-contract-card>strong{position:relative;z-index:1;align-self:start;background:#e7f5ef;color:#247450;padding:.28rem .5rem;border-radius:4px;font-size:.66rem;text-transform:uppercase;letter-spacing:.05em}.style-traits{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.55rem}.style-traits small{background:#edf3f6;color:#476075;padding:.24rem .42rem;border-radius:4px;font-size:.65rem}.style-contract-card.style-children{border-color:#f3c76e;background:linear-gradient(135deg,#fff8df,#fffdf7)}.style-contract-card.style-children .style-contract-mark{background:#4055a8;border-radius:50%;box-shadow:8px 7px 0 #ffca58}.style-contract-card.style-children:after{background:#ff6b6b;width:90px;height:90px;bottom:-52px}.style-contract-card.style-children:before{content:"";position:absolute;right:65px;top:-25px;width:62px;height:62px;border-radius:50%;background:#20b486;opacity:.16}.summary-grid .summary-style{grid-column:span 2;background:linear-gradient(135deg,#f0f8f7,#fff);border-left:4px solid var(--teal)}.summary-grid .summary-style span{display:block;margin-top:.3rem;color:var(--muted);font-size:.73rem;line-height:1.4}
.teacher-guide-contract{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:1rem;margin:1.35rem 0;padding:1rem 1.1rem;border:1px solid #efcfca;border-left:5px solid #b7433c;background:linear-gradient(135deg,#fff9f8,#fff)}.guide-mark{width:48px;height:48px;display:grid;place-items:center;border-radius:10px;background:#fde9e7;color:#b7433c;font-size:1.45rem}.teacher-guide-contract>div:nth-child(2)>span{display:block;color:#a44741;font-size:.62rem;font-weight:850;letter-spacing:.09em;text-transform:uppercase}.teacher-guide-contract h3{margin:.08rem 0;font-size:.95rem;color:#3c4e61}.teacher-guide-contract p{margin:0;color:#6e7d8c;font-size:.73rem;line-height:1.4}.teacher-guide-contract>strong{display:inline-flex;align-items:center;gap:.28rem;align-self:start;padding:.3rem .48rem;border-radius:4px;background:#f8e8e5;color:#9e423b;font-size:.65rem;text-transform:uppercase;letter-spacing:.04em}.summary-grid .summary-canva{grid-column:span 2;border-left:4px solid #6f4bd8;background:linear-gradient(135deg,#faf8ff,#fff)}.summary-grid .summary-guide{border-left:4px solid #b7433c;background:#fffafa}.summary-grid .summary-canva span,.summary-grid .summary-guide span{display:block;margin-top:.3rem;color:var(--muted);font-size:.7rem;line-height:1.4}.configuration-warning.is-canva{border-left-color:#6f4bd8;background:#faf8ff;color:#594a82}.provider-label{display:inline-flex;padding:.08rem .3rem;border-radius:3px;background:#edf1f4;color:#627180;font-size:.62rem;font-weight:800}.provider-label.is-canva{background:#eee9fa;color:#654ea8}.template-table-name{display:block;max-width:180px;margin-top:.22rem;color:#716685;font-size:.63rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.detail-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
@media(max-width:991px){.toggle-grid{grid-template-columns:repeat(3,1fr)}.title-grid,.summary-grid{grid-template-columns:1fr 1fr}.preview-grid{grid-template-columns:repeat(2,1fr)}.detail-grid{grid-template-columns:1fr 1fr}.objective-grid{grid-template-columns:1fr}}
.canva-status-line{display:block;margin-top:.28rem;color:#6f4bd8;font-size:.63rem;font-weight:750}.canva-status-line.is-ready{color:#23755d}.canva-status-line.is-failed{color:#ad433b}
@media(max-width:767px){.class-generator{padding-left:.65rem!important;padding-right:.65rem!important}.module-header{padding:1.15rem}.header-mark{display:none}.view-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%;overflow:hidden}.view-tabs button{min-width:0;justify-content:center;white-space:normal;font-size:.76rem;padding:.62rem .45rem;text-align:center}.view-tabs button:nth-child(3){grid-column:1/-1}.stepper button{padding:.7rem .3rem;flex-direction:column;gap:.3rem}.stepper button small{font-size:.62rem}.wizard-content{padding:1rem}.toggle-grid{grid-template-columns:1fr 1fr}.title-grid,.summary-grid,.detail-grid{grid-template-columns:1fr}.summary-grid .summary-style,.summary-grid .summary-canva{grid-column:1}.style-contract-card,.teacher-guide-contract{grid-template-columns:auto 1fr}.style-contract-card>strong,.teacher-guide-contract>strong{grid-column:1/-1;width:max-content}.wizard-actions{padding:.85rem 1rem}.history-heading,.detail-heading{padding:1rem;flex-wrap:wrap}.filter-bar{padding:.75rem}.filter-bar select{flex:1;min-width:140px}.presentation-table thead{display:none}.presentation-table,.presentation-table tbody{display:block}.presentation-table tr{display:block;margin:.7rem;border:1px solid var(--line);padding:.85rem}.presentation-table td{display:flex;justify-content:space-between;gap:1rem;border:0;padding:.35rem 0;text-align:right}.presentation-table td:before{content:attr(data-label);font-size:.67rem;font-weight:800;color:#80909e;text-transform:uppercase;text-align:left}.presentation-table td:first-child{display:block;text-align:left;border-bottom:1px solid var(--line);padding-bottom:.65rem}.presentation-table td:first-child:before,.presentation-table .row-actions:before,.presentation-table .empty-state:before{display:none}.row-actions button{margin-left:auto}.preview-grid{grid-template-columns:1fr}.download-panel,.preview-panel{margin:0 1rem 1rem}.detail-actions{padding:0 1rem 1rem;flex-direction:column}.failure-panel{margin:1rem;align-items:flex-start;flex-wrap:wrap}.progress-panel{margin:1rem}}
</style>
