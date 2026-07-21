<script setup>
import { computed } from "vue";
const props = defineProps({ item: { type: Object, required: true } });
const formatted = computed(() => props.item.value === null || props.item.value === undefined ? "Sin datos" : `${Number(props.item.value).toLocaleString("es-CL", { maximumFractionDigits: 2 })}${props.item.unit || ""}`);
const variation = computed(() => props.item.variation?.absolute);
const trendClass = computed(() => variation.value > 0 ? "trend-up" : variation.value < 0 ? "trend-down" : "trend-flat");
</script>

<template>
  <article class="attendance-kpi" :title="item.help">
    <div class="kpi-top"><span>{{ item.label }}</span><i class="bx bx-help-circle" aria-hidden="true"></i></div>
    <strong :class="{ 'is-empty': item.value === null || item.value === undefined }">{{ formatted }}</strong>
    <div class="kpi-bottom">
      <span v-if="variation !== null && variation !== undefined" :class="trendClass"><i class="bx" :class="variation > 0 ? 'bx-trending-up' : variation < 0 ? 'bx-trending-down' : 'bx-minus'"></i>{{ variation > 0 ? '+' : '' }}{{ variation }} pp</span>
      <span v-else-if="item.gap !== null && item.gap !== undefined" :class="item.gap >= 0 ? 'trend-up' : 'trend-down'">{{ item.gap > 0 ? '+' : '' }}{{ item.gap }} pp vs meta</span>
      <span v-else>Sin comparación</span>
    </div>
  </article>
</template>

<style scoped>
.attendance-kpi{min-width:0;padding:.78rem .85rem;border-right:1px solid #e5e9ef;background:#fff}.kpi-top{display:flex;align-items:center;justify-content:space-between;gap:.5rem;color:#697586;font-size:.66rem;font-weight:650}.kpi-top span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.kpi-top i{font-size:.9rem}.attendance-kpi strong{display:block;margin:.28rem 0 .18rem;color:#273244;font-size:1.18rem;line-height:1.1}.attendance-kpi strong.is-empty{font-size:.82rem;color:#8a94a3}.kpi-bottom{min-height:16px;color:#8a94a3;font-size:.62rem}.kpi-bottom span{display:inline-flex;align-items:center;gap:.2rem}.trend-up{color:#16794b}.trend-down{color:#c13c4a}.trend-flat{color:#526071}
</style>
