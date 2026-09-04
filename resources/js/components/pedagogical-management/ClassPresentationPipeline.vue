<script setup>
import { computed } from "vue";

const props = defineProps({
    presentation: { type: Object, required: true },
});

const status = computed(() => String(props.presentation?.status || "queued"));
const canvaStatus = computed(() => String(props.presentation?.canva?.status || "pending"));
const files = computed(() => Array.isArray(props.presentation?.files) ? props.presentation.files : []);
const localReady = computed(() => status.value === "ready");
const guideReady = computed(() => files.value.some((file) => String(file.type) === "teacher_guide_pdf"));
const canvaReady = computed(() => canvaStatus.value === "success" && Boolean(props.presentation?.canva?.design_id));
const generationFailureStage = computed(() => {
    if (status.value !== "failed") return null;
    const code = String(props.presentation?.failure_code || "").toLowerCase();
    if (code.includes("teacher_guide") || code.includes("guide") || code.includes("guia")) return 2;
    if (code.includes("pptx") || code.includes("pdf") || code.includes("preview") || code.includes("artifact")) return 3;
    return 1;
});
const combinedProgress = computed(() => {
    if (canvaReady.value) return 100;
    if (localReady.value) return 85;
    return Math.min(84, Math.round(Number(props.presentation?.progress || 0) * 0.85));
});

const stages = computed(() => [
    {
        id: 1,
        icon: "bx-brain",
        title: "Contenido pedagógico",
        copy: "ChatGPT estructura la clase y sus actividades.",
        state: generationFailureStage.value === 1 ? "failed" : ["queued", "preparing_content"].includes(status.value) ? "active" : "complete",
    },
    {
        id: 2,
        icon: "bxs-file-pdf",
        title: "Guía docente PDF",
        copy: "Guion, tiempos y orientaciones para implementar la clase.",
        state: generationFailureStage.value === 2 ? "failed" : guideReady.value || localReady.value ? "complete" : status.value === "generating_presentation" ? "active" : "pending",
    },
    {
        id: 3,
        icon: "bx-check-shield",
        title: "Respaldo y control",
        copy: "PPTX/PDF técnicos, previsualización y validación.",
        state: generationFailureStage.value === 3 ? "failed" : localReady.value ? "complete" : status.value === "validating" ? "active" : "pending",
    },
    {
        id: 4,
        icon: "bx-palette",
        title: "Diseño en Canva",
        copy: "La plantilla recibe el contenido mediante Autofill.",
        state: canvaStatus.value === "failed" ? "failed" : canvaReady.value ? "complete" : localReady.value ? "active" : "pending",
    },
]);
</script>

<template>
    <section class="generation-pipeline" aria-labelledby="pipeline-title">
        <div class="pipeline-heading">
            <div><span>Flujo trazable</span><h3 id="pipeline-title">Contenido, guía y diseño</h3></div>
            <strong>{{ combinedProgress }}%</strong>
        </div>
        <div class="pipeline-track">
            <article v-for="stage in stages" :key="stage.id" :class="`is-${stage.state}`">
                <div class="stage-mark">
                    <i v-if="stage.state === 'complete'" class="bx bx-check"></i>
                    <i v-else-if="stage.state === 'failed'" class="bx bx-x"></i>
                    <i v-else :class="['bx', stage.icon]"></i>
                </div>
                <div class="stage-copy"><small>Etapa {{ stage.id }}</small><strong>{{ stage.title }}</strong><span>{{ stage.copy }}</span></div>
            </article>
        </div>
        <p v-if="presentation.canva?.failure_message" class="pipeline-error"><i class="bx bx-error-circle"></i>{{ presentation.canva.failure_message }}</p>
    </section>
</template>

<style scoped>
.generation-pipeline{margin:1rem 1.5rem;padding:1.05rem;border:1px solid #dce5ec;background:linear-gradient(135deg,#fbfcfe,#f7fbfa)}.pipeline-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.9rem}.pipeline-heading span{display:block;color:#6f4bd8;font-size:.63rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.pipeline-heading h3{margin:.08rem 0 0;font-size:.98rem;color:#253f59}.pipeline-heading>strong{display:grid;place-items:center;min-width:48px;height:32px;padding:0 .55rem;border-radius:16px;background:#e8f5f2;color:#23755d;font-size:.75rem}.pipeline-track{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}.pipeline-track article{position:relative;display:grid;grid-template-columns:auto 1fr;align-items:start;gap:.55rem;min-width:0;padding:.72rem;border:1px solid #dde5eb;background:#fff}.pipeline-track article:not(:last-child):after{content:"";position:absolute;z-index:2;right:-.66rem;top:25px;width:.66rem;height:2px;background:#d5dee5}.stage-mark{width:32px;height:32px;display:grid;place-items:center;border:1px solid #c7d3dc;border-radius:50%;background:#f3f6f8;color:#8393a2}.stage-copy{display:flex;min-width:0;flex-direction:column}.stage-copy small{color:#8493a0;font-size:.57rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em}.stage-copy strong{margin:.08rem 0;color:#3b5267;font-size:.75rem;line-height:1.25}.stage-copy span{color:#7a8997;font-size:.62rem;line-height:1.35}.is-complete{border-color:#cfe7df!important;background:#f8fcfa!important}.is-complete .stage-mark{border-color:#1a8d76;background:#1a8d76;color:#fff}.is-complete:not(:last-child):after{background:#1a8d76!important}.is-active{border-color:#8d78d2!important;box-shadow:inset 0 0 0 1px #8d78d2,0 5px 14px rgba(111,75,216,.08)}.is-active .stage-mark{border-color:#6f4bd8;background:#eee9fb;color:#6f4bd8;animation:pipelinePulse 1.8s infinite}.is-failed{border-color:#e2aaa5!important;background:#fff7f6!important}.is-failed .stage-mark{border-color:#bd4b41;background:#bd4b41;color:#fff}.pipeline-error{display:flex;align-items:flex-start;gap:.4rem;margin:.8rem 0 0;padding:.65rem;background:#fff3f1;color:#8d3d36;font-size:.72rem}.pipeline-error i{font-size:1rem}@keyframes pipelinePulse{0%,100%{box-shadow:0 0 0 0 rgba(111,75,216,.2)}50%{box-shadow:0 0 0 7px rgba(111,75,216,0)}}
@media(max-width:991px){.pipeline-track{grid-template-columns:repeat(2,minmax(0,1fr))}.pipeline-track article:after{display:none}}
@media(max-width:575px){.generation-pipeline{margin:1rem;padding:.85rem}.pipeline-track{grid-template-columns:1fr}.pipeline-track article{grid-template-columns:auto 1fr}.stage-copy span{font-size:.67rem}}
</style>
