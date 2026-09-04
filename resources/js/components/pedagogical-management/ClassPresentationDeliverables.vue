<script setup>
import { computed } from "vue";

const props = defineProps({
    presentation: { type: Object, required: true },
    busy: { type: Boolean, default: false },
});

defineEmits(["download", "edit-canva", "sync-canva"]);

const files = computed(() => Array.isArray(props.presentation?.files) ? props.presentation.files : []);
const isCanva = computed(() => {
    const provider = props.presentation?.presentation_provider || props.presentation?.provider || props.presentation?.configuration?.presentation_provider;
    return provider === "canva" || Boolean(props.presentation?.canva?.design_id || props.presentation?.canva?.brand_template_id);
});

function isGuide(file) {
    return ["teacher_guide_pdf", "guide_pdf", "teacher_guide"].includes(String(file.type))
        || ["teacher_guide", "teacher-guide", "guia_docente"].includes(String(file.metadata?.kind || file.metadata?.document_kind || file.metadata?.artifact_role || ""));
}

const guideFiles = computed(() => files.value.filter(isGuide));
const presentationFiles = computed(() => files.value.filter((file) => !isGuide(file) && ["pptx", "pdf", "canva_pptx", "canva_pdf"].includes(String(file.type))));
const canEdit = computed(() => isCanva.value && Boolean(props.presentation?.can?.edit_canva ?? props.presentation?.canva?.design_id));
const canSync = computed(() => isCanva.value && Boolean(props.presentation?.can?.sync_canva ?? props.presentation?.canva?.design_id));

function formatBytes(bytes) {
    const value = Number(bytes || 0);
    return value >= 1048576 ? `${(value / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(value / 1024))} KB`;
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)) : "Aún no sincronizada";
}

function fileTitle(file) {
    if (isGuide(file)) return "Guía docente PDF";
    return ["pptx", "canva_pptx"].includes(String(file.type))
        ? (isCanva.value ? "Respaldo técnico · PowerPoint" : "PowerPoint editable")
        : (isCanva.value ? "Respaldo técnico · PDF" : "Documento PDF");
}
</script>

<template>
    <section class="deliverables" aria-labelledby="deliverables-title">
        <header class="deliverables-heading">
            <div><span>{{ isCanva ? 'Diseño Canva y respaldos' : 'Presentación heredada' }}</span><h3 id="deliverables-title">Entregables de la clase</h3><p>El diseño Canva, la guía docente y los respaldos técnicos se identifican por separado.</p></div>
            <div v-if="isCanva" class="canva-design-actions">
                <button v-if="canSync" type="button" class="sync-button" :disabled="busy" @click="$emit('sync-canva')"><i class="bx bx-refresh"></i>Reintentar Canva</button>
                <button v-if="canEdit" type="button" class="edit-button" :disabled="busy" @click="$emit('edit-canva')"><span v-if="busy" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-link-external"></i>Editar en Canva</button>
            </div>
        </header>

        <div v-if="isCanva" class="canva-design-summary">
            <div v-if="presentation.canva?.thumbnail_url" class="canva-thumbnail"><img :src="presentation.canva.thumbnail_url" :alt="`Diseño Canva de ${presentation.title}`"></div>
            <div class="canva-design-copy">
                <small>Plantilla aplicada</small>
                <strong>{{ presentation.canva?.brand_template_title || presentation.configuration?.canva_template_title || 'Plantilla Canva' }}</strong>
                <span><i class="bx bx-palette"></i>{{ presentation.canva?.design_id ? 'Diseño editable disponible en Canva' : 'Canva está preparando el diseño' }}</span>
                <span><i class="bx bx-time-five"></i>Última sincronización: {{ formatDate(presentation.canva?.synced_at || presentation.canva?.completed_at) }}</span>
            </div>
        </div>

        <div class="deliverable-columns">
            <article class="presentation-deliverables">
                <div class="column-heading"><i class="bx bx-slideshow"></i><div><strong>{{ isCanva ? 'Respaldos técnicos' : 'Presentación para el curso' }}</strong><span>{{ isCanva ? 'PPTX y PDF generados por el sistema antes de aplicar la plantilla; no son exportaciones del diseño Canva.' : 'Archivos conservados del generador PowerPoint anterior.' }}</span></div></div>
                <div v-if="presentationFiles.length" class="file-list">
                    <button v-for="file in presentationFiles" :key="file.id" type="button" @click="$emit('download', file)">
                        <i class="bx" :class="['pptx','canva_pptx'].includes(String(file.type)) ? 'bxs-file-blank' : 'bxs-file-pdf'"></i>
                        <span><strong>{{ fileTitle(file) }}</strong><small>{{ file.filename }} · {{ formatBytes(file.size) }}</small></span>
                        <i class="bx bx-download"></i>
                    </button>
                </div>
                <div v-else class="file-pending"><i class="bx bx-time-five"></i><span><strong>Respaldos en preparación</strong><small>Los archivos aparecerán al terminar el contenido y su control de calidad.</small></span></div>
            </article>

            <article class="guide-deliverables">
                <div class="column-heading"><i class="bx bxs-file-pdf"></i><div><strong>Guía para el docente</strong><span>Guion de implementación, tiempos, preguntas y orientaciones pedagógicas.</span></div></div>
                <div v-if="guideFiles.length" class="file-list guide-files">
                    <button v-for="file in guideFiles" :key="file.id" type="button" @click="$emit('download', file)">
                        <i class="bx bxs-file-pdf"></i>
                        <span><strong>Guía docente PDF</strong><small>{{ file.filename }} · {{ formatBytes(file.size) }}</small></span>
                        <i class="bx bx-download"></i>
                    </button>
                </div>
                <div v-else-if="isCanva || presentation.status !== 'ready'" class="file-pending"><i class="bx bx-time-five"></i><span><strong>Guía docente en preparación</strong><small>ChatGPT genera este documento por separado de las diapositivas.</small></span></div>
                <div v-else class="file-pending is-unavailable"><i class="bx bx-archive"></i><span><strong>Versión heredada sin guía docente</strong><small>Este archivo corresponde al generador anterior y se conserva sin alteraciones.</small></span></div>
            </article>
        </div>
    </section>
</template>

<style scoped>
.deliverables{margin:0 1.5rem 1rem;border:1px solid #dce5ec;background:#fff;padding:1rem}.deliverables-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.deliverables-heading>div:first-child>span{display:block;color:#6f4bd8;font-size:.63rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.deliverables-heading h3{margin:.1rem 0;font-size:1rem;color:#263f58}.deliverables-heading p{margin:0;color:#748495;font-size:.73rem}.canva-design-actions{display:flex;align-items:center;gap:.45rem}.canva-design-actions button{display:inline-flex;align-items:center;gap:.35rem;border-radius:5px;padding:.55rem .7rem;font-size:.72rem;font-weight:800;white-space:nowrap}.edit-button{border:1px solid #6f4bd8;background:#6f4bd8;color:#fff}.sync-button{border:1px solid #cbd7df;background:#fff;color:#31526e}.canva-design-actions button:disabled{opacity:.6}.canva-design-summary{display:grid;grid-template-columns:auto 1fr;gap:.8rem;align-items:center;margin-bottom:.85rem;padding:.7rem;border:1px solid #e1dcf3;background:linear-gradient(135deg,#faf8ff,#f5fbfa)}.canva-thumbnail{width:112px;aspect-ratio:16/9;background:#e8edf1;overflow:hidden}.canva-thumbnail img{width:100%;height:100%;object-fit:cover}.canva-design-copy{display:flex;min-width:0;flex-direction:column}.canva-design-copy>small{color:#715ab2;font-size:.61rem;font-weight:850;text-transform:uppercase;letter-spacing:.07em}.canva-design-copy>strong{margin:.08rem 0 .3rem;color:#29445e;font-size:.82rem}.canva-design-copy>span{display:inline-flex;align-items:center;gap:.28rem;color:#6c7c8c;font-size:.65rem;line-height:1.45}.canva-design-copy i{color:#168b82}.deliverable-columns{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}.deliverable-columns>article{min-width:0;padding:.85rem;border:1px solid #dfe7ed;background:#fbfcfd}.guide-deliverables{border-left:4px solid #c84b43!important;background:linear-gradient(135deg,#fffafa,#fff)!important}.presentation-deliverables{border-left:4px solid #6f4bd8!important}.column-heading{display:grid;grid-template-columns:auto 1fr;gap:.55rem;align-items:start;margin-bottom:.6rem}.column-heading>i{width:34px;height:34px;display:grid;place-items:center;border-radius:7px;background:#eee9fa;color:#6f4bd8;font-size:1.05rem}.guide-deliverables .column-heading>i{background:#fdecea;color:#b3443d}.column-heading div{display:flex;flex-direction:column}.column-heading strong{color:#324c64;font-size:.78rem}.column-heading span{margin-top:.08rem;color:#778796;font-size:.64rem;line-height:1.35}.file-list{display:flex;flex-direction:column;gap:.4rem}.file-list button{width:100%;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.55rem;text-align:left;border:1px solid #dce4ea;background:#fff;padding:.65rem;border-radius:5px;color:#314c64}.file-list button:hover{border-color:#9b88d2;background:#fdfcff}.file-list button:focus-visible{outline:3px solid rgba(111,75,216,.2);outline-offset:1px}.file-list button>i:first-child{font-size:1.25rem;color:#654eb0}.guide-files button>i:first-child{color:#b7433c}.file-list button>span{display:flex;min-width:0;flex-direction:column}.file-list button strong{font-size:.73rem}.file-list button small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#7b8997;font-size:.62rem}.file-list button>i:last-child{color:#6e7f8e}.file-pending{display:grid;grid-template-columns:auto 1fr;gap:.5rem;align-items:center;padding:.65rem;border:1px dashed #ccd7df;color:#758493}.file-pending.is-unavailable{background:#f3f5f7;color:#7d8994}.file-pending>i{font-size:1.15rem}.file-pending span{display:flex;flex-direction:column}.file-pending strong{font-size:.7rem;color:#566b7d}.file-pending small{font-size:.61rem;line-height:1.35}
@media(max-width:767px){.deliverables{margin:0 1rem 1rem}.deliverables-heading{flex-direction:column}.canva-design-actions{width:100%;display:grid;grid-template-columns:1fr 1fr}.canva-design-actions button{justify-content:center}.deliverable-columns{grid-template-columns:1fr}}
@media(max-width:480px){.canva-design-summary{grid-template-columns:1fr}.canva-thumbnail{width:100%}.canva-design-actions{grid-template-columns:1fr}}
</style>
