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
const treeDepth = ref(2);
const expandLevel = () => {
    treeDepth.value = Math.min(5, treeDepth.value + 1);
};
const collapseAll = () => {
    treeDepth.value = 1;
};
defineExpose({
    center: () => base.value?.center(),
    reset: () => {
        treeDepth.value = 2;
        base.value?.reset();
    },
    exportImage: () => base.value?.exportImage(),
});
</script>

<template>
    <div class="cv-radial">
        <div class="cv-radial__actions" aria-label="Controles del árbol radial">
            <button
                type="button"
                :disabled="treeDepth >= 5"
                @click="expandLevel"
            >
                <i class="bx bx-expand" aria-hidden="true"></i>
                Expandir nivel
            </button>
            <button type="button" @click="collapseAll">
                <i class="bx bx-collapse" aria-hidden="true"></i>
                Contraer todo
            </button>
        </div>
        <CurriculumEChartBase
            ref="base"
            variant="radial_tree"
            v-bind="$props"
            :tree-depth="treeDepth"
            @select-node="emit('select-node', $event)"
            @drilldown="emit('drilldown', $event)"
            @open-objective="emit('open-objective', $event)"
        />
    </div>
</template>

<style scoped>
.cv-radial {
    position: relative;
}

.cv-radial__actions {
    position: absolute;
    z-index: 2;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 5px;
    padding: 4px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.95);
}

.cv-radial__actions button {
    min-height: 33px;
    padding: 6px 9px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--lcd-brand-700, #245486);
    font-size: 0.72rem;
    font-weight: 750;
}

.cv-radial__actions button:hover:not(:disabled) {
    background: var(--lcd-brand-50, #f1f7fb);
}

.cv-radial__actions button:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
}

.cv-radial__actions button:disabled {
    opacity: 0.45;
}
</style>
