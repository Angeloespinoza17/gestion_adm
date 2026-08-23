<script setup>
const props = defineProps({ level: { type: String, default: "no_data" }, label: { type: String, default: "" }, compact: Boolean });
const labels = { green: "Adecuada", yellow: "Preventiva", orange: "En riesgo", red: "Prioritaria", critical: "Crítica", no_data: "Sin datos" };
</script>

<template>
  <span class="risk-pill" :class="[`risk-${props.level}`, { compact: props.compact }]">
    <i class="bx" :class="props.level === 'green' ? 'bx-check-shield' : props.level === 'no_data' ? 'bx-minus-circle' : 'bx-error-circle'"></i>
    {{ props.label || labels[props.level] || props.level }}
  </span>
</template>

<style scoped>
.risk-pill{display:inline-flex;align-items:center;gap:.3rem;width:max-content;padding:.32rem .58rem;border:1px solid transparent;border-radius:999px;font-size:.68rem;font-weight:750;line-height:1}.risk-pill.compact{padding:.24rem .45rem;font-size:.62rem}.risk-green{border-color:#b7e2cf;background:#eaf8f1;color:#147457}.risk-yellow{border-color:#f0dda5;background:#fff8df;color:#8a6500}.risk-orange{border-color:#f1c797;background:#fff0df;color:#a3540a}.risk-red{border-color:#efafb4;background:#ffebed;color:#b12d3a}.risk-critical{border-color:#d79da8;background:#5e2130;color:#fff}.risk-no_data{border-color:#d9dee6;background:#f2f4f7;color:#667085}
</style>
