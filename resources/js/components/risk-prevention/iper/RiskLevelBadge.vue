<script setup>
import { computed } from "vue";

const props = defineProps({ level: { type: [Object, String], default: null }, score: { type: [Number, String], default: null } });
const normalized = computed(() => {
  if (props.level && typeof props.level === "object") return { code: props.level.code, label: props.level.name || props.level.label, color: props.level.color };
  const score = Number(props.score);
  const fallback = score <= 2 ? ["tolerable", "Tolerable", "#2e7d32"] : score === 4 ? ["moderate", "Moderado", "#d97706"] : score === 8 ? ["important", "Importante", "#dc6803"] : score === 16 ? ["intolerable", "Intolerable", "#b42318"] : ["unassessed", props.level || "Sin evaluar", "#667085"];
  return { code: fallback[0], label: fallback[1], color: fallback[2] };
});
</script>

<template>
  <span class="iper-level" :class="`iper-level--${normalized.code}`" :style="{ '--level-color': normalized.color }">
    <i class="bx bx-shield-quarter" aria-hidden="true"></i>
    <span>{{ normalized.label }}</span>
    <strong v-if="score !== null && score !== ''">{{ score }}</strong>
  </span>
</template>

<style scoped>
.iper-level{display:inline-flex;align-items:center;gap:.38rem;padding:.3rem .58rem;border:1px solid color-mix(in srgb,var(--level-color) 35%,#fff);border-radius:999px;background:color-mix(in srgb,var(--level-color) 9%,#fff);color:var(--level-color);font-size:.69rem;font-weight:750;white-space:nowrap}.iper-level strong{display:grid;place-items:center;min-width:20px;height:20px;padding:0 .25rem;border-radius:999px;background:var(--level-color);color:#fff;font-size:.62rem}
</style>
