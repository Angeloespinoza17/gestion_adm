<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import {
    errorMessage,
    instrumentFileExtension,
    instrumentFileIcon,
    pedagogicalPrintApi,
    printPresentation,
    saveBlob,
} from "../../services/pedagogical-management-api";

const loading = ref(true);
const workingId = ref("");
const requests = ref([]);
const meta = ref({ total: 0, status_counts: {} });
const filter = reactive({ search: "", status: "" });
const alert = reactive({ tone: "", text: "" });

const pending = computed(() => Number(meta.value.status_counts?.pending || 0));

function notify(tone, text) {
    Object.assign(alert, { tone, text });
    window.setTimeout(() => { if (alert.text === text) alert.text = ""; }, 6000);
}

function formatDate(value) {
    if (!value) return "—";
    return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function formatBytes(value) {
    const bytes = Number(value || 0);
    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

async function load() {
    loading.value = true;
    try {
        const response = await pedagogicalPrintApi.list({ search: filter.search || undefined, status: filter.status || undefined, per_page: 100 });
        requests.value = response.data || [];
        meta.value = response.meta || meta.value;
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible cargar la cola de impresión."));
    } finally {
        loading.value = false;
    }
}

async function download(item) {
    workingId.value = item.id;
    try {
        const blob = await pedagogicalPrintApi.file(item.id, true);
        saveBlob(blob, item.file.original_filename);
        await pedagogicalPrintApi.action(item.id, "downloaded");
        await load();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible descargar el instrumento."));
    } finally {
        workingId.value = "";
    }
}

async function print(item) {
    const popup = window.open("", "_blank");
    if (!popup) return notify("warning", "El navegador bloqueó la ventana de impresión. Habilita ventanas emergentes para este sitio.");
    workingId.value = item.id;
    try {
        const blob = await pedagogicalPrintApi.file(item.id, false);
        const url = URL.createObjectURL(blob);
        popup.location.href = url;
        popup.addEventListener("load", () => {
            popup.focus();
            popup.print();
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        }, { once: true });
        await pedagogicalPrintApi.action(item.id, "printed");
        await load();
    } catch (error) {
        popup.close();
        notify("danger", errorMessage(error, "No fue posible preparar la impresión."));
    } finally {
        workingId.value = "";
    }
}

async function setStatus(item, action) {
    workingId.value = item.id;
    try {
        const response = await pedagogicalPrintApi.action(item.id, action);
        notify("success", response.message);
        await load();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible actualizar la tarea."));
    } finally {
        workingId.value = "";
    }
}

onMounted(load);
</script>

<template>
    <Layout>
        <div class="print-page container-fluid py-4">
            <header class="print-hero mb-4"><div><span>CENTRO DE APUNTES</span><h1>Instrumentos aprobados</h1><p>Cola automática con la versión exacta aprobada por Coordinación Académica.</p></div><div class="hero-count"><strong>{{ pending }}</strong><small>pendientes</small></div></header>
            <div v-if="alert.text" class="alert" :class="`alert-${alert.tone}`">{{ alert.text }}</div>
            <section class="toolbar card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3 align-items-center"><div class="col-lg-7"><div class="input-group"><span class="input-group-text"><i class="bx bx-search"></i></span><input v-model="filter.search" class="form-control" placeholder="Buscar por docente, título o asignatura" @keyup.enter="load" /></div></div><div class="col-lg-3"><select v-model="filter.status" class="form-select" @change="load"><option value="">Todos los estados</option><option value="pending">Pendientes</option><option value="in_process">En preparación</option><option value="printed">Impresos</option><option value="completed">Completados</option></select></div><div class="col-lg-2 d-grid"><button class="btn btn-outline-primary" type="button" @click="load"><i class="bx bx-refresh me-1"></i>Actualizar</button></div></div></div></section>
            <LoadingState v-if="loading" message="Cargando instrumentos aprobados..." />
            <section v-else class="print-grid">
                <article v-for="item in requests" :key="item.id" class="print-card">
                    <div class="card-top"><span class="pdf-icon" :class="{ word: instrumentFileExtension(item.file.original_filename) === 'docx' }"><i class="bx" :class="instrumentFileIcon(item.file.original_filename)"></i></span><span class="status-pill" :class="`tone-${printPresentation(item.status).tone}`">{{ printPresentation(item.status).label }}</span></div>
                    <h2>{{ item.instrument.title }}</h2><p class="context">{{ item.instrument.owner?.name }} · {{ item.instrument.subject?.name }}</p>
                    <div class="course-tags"><span v-for="course in item.instrument.courses" :key="course.id">{{ course.name }}</span></div>
                    <dl><div><dt>Archivo aprobado</dt><dd>v{{ item.file.version }} · {{ item.file.original_filename }}</dd></div><div><dt>Tamaño</dt><dd>{{ formatBytes(item.file.file_size) }}<template v-if="item.file.page_count"> · {{ item.file.page_count }} páginas</template></dd></div><div><dt>Revisado por</dt><dd>{{ item.review.reviewer?.name }} · {{ formatDate(item.review.reviewed_at) }}</dd></div></dl>
                    <div v-if="item.review.notes" class="review-note"><i class="bx bx-message-detail"></i><span>{{ item.review.notes }}</span></div>
                    <div class="primary-actions" :class="{ single: instrumentFileExtension(item.file.original_filename) !== 'pdf' }"><button v-if="instrumentFileExtension(item.file.original_filename) === 'pdf'" class="btn btn-primary" type="button" :disabled="workingId === item.id" @click="print(item)"><i class="bx bx-printer me-1"></i>Imprimir</button><button class="btn" :class="instrumentFileExtension(item.file.original_filename) === 'pdf' ? 'btn-outline-primary' : 'btn-primary'" type="button" :disabled="workingId === item.id" @click="download(item)"><i class="bx bx-download me-1"></i>{{ instrumentFileExtension(item.file.original_filename) === "pdf" ? "Descargar" : "Descargar Word para imprimir" }}</button></div>
                    <div class="workflow-actions"><button v-if="item.status === 'pending'" class="btn btn-sm btn-light" type="button" @click="setStatus(item, 'in_process')">Marcar en preparación</button><button v-if="item.status !== 'completed'" class="btn btn-sm btn-success" type="button" @click="setStatus(item, 'completed')"><i class="bx bx-check me-1"></i>Completar</button><span v-if="item.print_count || item.download_count" class="usage"><i class="bx bx-printer"></i> {{ item.print_count }} · <i class="bx bx-download"></i> {{ item.download_count }}</span></div>
                </article>
                <div v-if="!requests.length" class="empty"><i class="bx bx-printer"></i><h2>Cola al día</h2><p>No hay instrumentos aprobados para este filtro.</p></div>
            </section>
        </div>
    </Layout>
</template>

<style scoped>
.print-page{--navy:#173f67;--teal:#18877f;--ink:#17243a;color:var(--ink)}.print-hero{background:linear-gradient(120deg,#153650,#176878 62%,#248e7f);border-radius:24px;padding:2rem 2.25rem;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 18px 45px rgba(19,59,78,.2)}.print-hero>div>span{font-size:.7rem;letter-spacing:.16em;font-weight:800;opacity:.75}.print-hero h1{color:#fff;margin:.35rem 0;font-size:clamp(1.7rem,3vw,2.35rem)}.print-hero p{margin:0;opacity:.85}.hero-count{min-width:125px;padding:1rem;text-align:center;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.12);border-radius:18px}.hero-count strong,.hero-count small{display:block}.hero-count strong{font-size:2rem}.toolbar{border-radius:18px}.print-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.print-card{background:#fff;border:1px solid #e7ecf1;border-radius:20px;padding:1.25rem;box-shadow:0 7px 25px rgba(26,48,78,.07);display:flex;flex-direction:column}.card-top{display:flex;justify-content:space-between;align-items:center}.pdf-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:#ffeded;color:#d34c54;font-size:1.5rem}.pdf-icon.word{background:#edf3ff;color:#315ca8}.status-pill{border-radius:999px;padding:.38rem .7rem;font-size:.72rem;font-weight:800}.tone-warning{color:#8a5b00;background:#fff3d4}.tone-info{color:#176a81;background:#e3f4f9}.tone-primary{color:#264d88;background:#e8eefb}.tone-success{color:#176a50;background:#e4f6ee}.tone-neutral{color:#596273;background:#eff1f4}.print-card h2{font-size:1.12rem;margin:1rem 0 .25rem}.context{color:#6f7c8e}.course-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.8rem}.course-tags span{font-size:.72rem;font-weight:700;color:var(--navy);background:#eaf3f8;border-radius:999px;padding:.3rem .55rem}.print-card dl{margin:0}.print-card dl>div{padding:.65rem 0;border-top:1px solid #edf0f3}.print-card dt{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#8791a0}.print-card dd{margin:.15rem 0 0;font-size:.83rem;color:#405065;overflow-wrap:anywhere}.review-note{display:flex;gap:.6rem;background:#f5f7fa;border-radius:12px;padding:.7rem;font-size:.8rem;color:#586679;margin:.75rem 0}.review-note i{font-size:1.1rem;color:var(--teal)}.primary-actions{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:auto;padding-top:.8rem}.primary-actions.single{grid-template-columns:1fr}.workflow-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;border-top:1px solid #edf0f3;margin-top:.9rem;padding-top:.8rem}.usage{font-size:.75rem;color:#778395;margin-left:auto}.empty{grid-column:1/-1;min-height:380px;background:#fff;border-radius:20px;display:grid;place-content:center;text-align:center;color:#8792a2}.empty i{font-size:3rem}.empty h2{font-size:1.2rem;margin:.6rem 0 .2rem}@media(max-width:1199px){.print-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.print-page{padding-left:.7rem!important;padding-right:.7rem!important}.print-hero{padding:1.4rem;border-radius:18px}.hero-count{display:none}.print-grid{grid-template-columns:1fr}.primary-actions{grid-template-columns:1fr}.usage{margin-left:0;width:100%}}
</style>
