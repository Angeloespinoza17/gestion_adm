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
    <CurriculumEChartBase
        ref="base"
        variant="sankey"
        v-bind="$props"
        @select-node="emit('select-node', $event)"
        @drilldown="emit('drilldown', $event)"
        @open-objective="emit('open-objective', $event)"
    />
</template>
