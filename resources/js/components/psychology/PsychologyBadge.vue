<script setup>
import { computed } from 'vue'
import { priorityLabels, psychologyStatusLabels } from '../../composables/usePsychology'
const props = defineProps({ value: { type: String, default: '' }, priority: Boolean })
const label = computed(() => props.priority ? (priorityLabels[props.value] || props.value) : (psychologyStatusLabels[props.value] || props.value))
</script>
<template><span class="psi-badge" :class="`psi-badge--${value}`">{{ label }}</span></template>
<style scoped>
.psi-badge{display:inline-flex;align-items:center;padding:.25rem .55rem;border-radius:.35rem;background:#eef2f6;color:#44546a;font-size:.72rem;font-weight:700;white-space:nowrap}.psi-badge--critical,.psi-badge--overdue{background:#feeceb;color:#b42318}.psi-badge--high{background:#fff2d9;color:#945b00}.psi-badge--accepted,.psi-badge--completed,.psi-badge--closed{background:#e8f7f1;color:#08764e}.psi-badge--submitted,.psi-badge--under_review,.psi-badge--active_intervention{background:#eaf2ff;color:#2459a8}.psi-badge--information_requested,.psi-badge--awaiting_information{background:#f4edff;color:#6d3bb0}
</style>
