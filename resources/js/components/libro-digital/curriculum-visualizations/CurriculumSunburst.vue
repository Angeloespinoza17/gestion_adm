<script setup>
import { ref } from "vue";
import CurriculumEChartBase from "./CurriculumEChartBase.vue";

defineProps({
    root: { type: Object, required: true },
    graph: { type: Object, default: () => ({}) },
    labelMode: { type: String, default: "automatic" },
    reducedMotion: { type: Boolean, default: false },
    selectedNode: { type: Object, default: null },
});
const emit = defineEmits(["select-node", "drilldown", "open-objective"]);
const base = ref(null);
defineExpose({
    center: () => base.value?.center(),
    reset: () => base.value?.reset(),
    exportImage: () => base.value?.exportImage(),
});
</script>

<template>
    <div class="cv-sunburst">
        <CurriculumEChartBase
            ref="base"
            variant="sunburst"
            v-bind="$props"
            @select-node="emit('select-node', $event)"
            @drilldown="emit('drilldown', $event)"
            @open-objective="emit('open-objective', $event)"
        />
        <div class="cv-sunburst__total" aria-hidden="true">
            <strong>{{
                Number(root.objectiveCount || 0).toLocaleString("es-CL")
            }}</strong>
            <span>OA</span>
        </div>
    </div>
</template>

<style scoped>
.cv-sunburst {
    position: relative;
}

.cv-sunburst__total {
    position: absolute;
    top: 50%;
    left: 50%;
    display: flex;
    width: 64px;
    height: 64px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.96);
    color: var(--lcd-ink, #17263d);
    pointer-events: none;
    transform: translate(-50%, -50%);
}

.cv-sunburst__total strong {
    font-size: 0.92rem;
}

.cv-sunburst__total span {
    color: var(--lcd-muted, #627187);
    font-size: 0.62rem;
    font-weight: 750;
}
</style>
