<script setup>
import { computed } from 'vue'

const props = defineProps({
  statuses: { type: Object, default: () => ({}) },
  risks: { type: Object, default: () => ({}) },
})
const palette = ['#66519a', '#4385b7', '#38a078', '#e0a63b', '#ca6071', '#8895a6', '#846cae']
const riskPalette = { sin_evaluar: '#a8b1bd', bajo: '#45a879', medio: '#e1ac42', alto: '#df7755', critico: '#bc3d55' }
const label = value => String(value || '—').replaceAll('_', ' ')
const statusRows = computed(() => Object.entries(props.statuses || {}).map(([key, value], index) => ({ key, value: Number(value), color: palette[index % palette.length] })))
const total = computed(() => statusRows.value.reduce((sum, row) => sum + row.value, 0))
const donut = computed(() => {
  if (!total.value) return '#edf1f5 0 100%'
  let start = 0
  return statusRows.value.map(row => {
    const end = start + (row.value / total.value * 100)
    const segment = `${row.color} ${start}% ${end}%`
    start = end
    return segment
  }).join(', ')
})
const riskRows = computed(() => Object.entries(props.risks || {}).map(([key, value]) => ({ key, value: Number(value), color: riskPalette[key] || '#725c9a' })))
const riskMax = computed(() => Math.max(1, ...riskRows.value.map(row => row.value)))
</script>

<template>
  <div class="charts-grid">
    <section class="chart-card">
      <header><div><span class="eyebrow">Distribución operativa</span><h2>Casos por estado</h2></div><i class="bx bx-doughnut-chart"></i></header>
      <div v-if="statusRows.length" class="donut-layout">
        <div class="donut" :style="{ background: `conic-gradient(${donut})` }"><div><strong>{{ total }}</strong><span>casos</span></div></div>
        <div class="legend"><div v-for="row in statusRows" :key="row.key"><i :style="{ background: row.color }"></i><span>{{ label(row.key) }}</span><strong>{{ row.value }}</strong></div></div>
      </div>
      <div v-else class="empty-chart"><i class="bx bx-doughnut-chart"></i><span>Sin casos registrados</span></div>
    </section>
    <section class="chart-card">
      <header><div><span class="eyebrow">Priorización profesional</span><h2>Nivel de riesgo</h2></div><i class="bx bx-bar-chart-alt-2"></i></header>
      <div v-if="riskRows.length" class="bars">
        <div v-for="row in riskRows" :key="row.key" class="bar-row"><div><span>{{ label(row.key) }}</span><strong>{{ row.value }}</strong></div><div class="track"><span :style="{ width: `${Math.max(4, row.value / riskMax * 100)}%`, background: row.color }"></span></div></div>
      </div>
      <div v-else class="empty-chart"><i class="bx bx-bar-chart-alt-2"></i><span>Sin evaluaciones registradas</span></div>
    </section>
  </div>
</template>

<style scoped>
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem}.chart-card{background:#fff;border:1px solid #e2e8ef;border-radius:18px;padding:1.15rem 1.25rem;box-shadow:0 14px 38px rgba(42,50,79,.06)}.chart-card>header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}.chart-card>header h2{font-size:1rem;color:#2d3b50;margin:.15rem 0}.chart-card>header>i{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#f1edf9;color:#67518f;font-size:1.3rem}.eyebrow{font-size:.63rem;text-transform:uppercase;letter-spacing:.11em;color:#7a669d;font-weight:800}.donut-layout{display:grid;grid-template-columns:190px 1fr;align-items:center;gap:1.2rem}.donut{width:168px;aspect-ratio:1;border-radius:50%;display:grid;place-items:center}.donut>div{width:104px;aspect-ratio:1;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;flex-direction:column;box-shadow:inset 0 0 0 1px #edf0f4}.donut strong{font-size:1.55rem;color:#2d3b50}.donut span{font-size:.65rem;text-transform:uppercase;color:#7b8796}.legend{display:grid;gap:.55rem}.legend>div{display:grid;grid-template-columns:9px 1fr auto;align-items:center;gap:.55rem}.legend i{width:9px;height:9px;border-radius:50%}.legend span{text-transform:capitalize;color:#5e6b7b;font-size:.78rem}.legend strong{color:#2f3d51}.bars{display:grid;gap:.82rem;padding:.3rem 0}.bar-row>div:first-child{display:flex;justify-content:space-between;margin-bottom:.3rem;text-transform:capitalize;font-size:.78rem;color:#5e6b7b}.bar-row strong{color:#2e3d51}.track{height:10px;background:#edf1f5;border-radius:999px;overflow:hidden}.track span{display:block;height:100%;border-radius:999px;transition:width .35s ease}.empty-chart{height:180px;display:grid;place-items:center;align-content:center;gap:.4rem;color:#8b97a5}.empty-chart i{font-size:2rem;color:#aeb7c2}@media(max-width:960px){.charts-grid{grid-template-columns:1fr}.donut-layout{grid-template-columns:160px 1fr}.donut{width:145px}}
</style>
