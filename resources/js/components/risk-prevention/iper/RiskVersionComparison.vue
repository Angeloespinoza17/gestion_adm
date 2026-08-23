<script setup>
defineProps({ comparison: { type: Object, default: null } });
const labels = { score: "VEP", level: "Nivel", exposure: "Exposición", controls: "Controles", process: "Proceso", activity: "Actividad", task: "Tarea", risk: "Riesgo" };
</script>

<template>
  <div v-if="comparison" class="compare-shell">
    <div class="compare-summary"><div><span>Agregados</span><strong>{{ comparison.summary.added }}</strong></div><div><span>Eliminados</span><strong>{{ comparison.summary.removed }}</strong></div><div><span>Modificados</span><strong>{{ comparison.summary.modified }}</strong></div><div><span>Metodología</span><strong>{{ comparison.methodology_changed ? 'Cambió' : 'Sin cambio' }}</strong></div></div>
    <div class="compare-columns">
      <section><header class="added">Filas agregadas</header><article v-for="row in comparison.added" :key="row.risk"><strong>{{ row.risk }}</strong><span>{{ row.process }} · {{ row.task }}</span></article><p v-if="!comparison.added.length">Sin filas agregadas.</p></section>
      <section><header class="removed">Filas eliminadas</header><article v-for="row in comparison.removed" :key="row.risk"><strong>{{ row.risk }}</strong><span>{{ row.process }} · {{ row.task }}</span></article><p v-if="!comparison.removed.length">Sin filas eliminadas.</p></section>
    </div>
    <section class="modified-list"><header>Riesgos modificados</header><article v-for="row in comparison.modified" :key="row.key"><h6>{{ row.risk }}</h6><div v-for="(change, field) in row.changes" :key="field"><span>{{ labels[field] || field }}</span><del>{{ typeof change.from === 'object' ? JSON.stringify(change.from) : change.from }}</del><i class="bx bx-right-arrow-alt"></i><ins>{{ typeof change.to === 'object' ? JSON.stringify(change.to) : change.to }}</ins></div></article><p v-if="!comparison.modified.length">No se detectaron cambios semánticos.</p></section>
  </div>
</template>

<style scoped>
.compare-summary{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #e1e7ee;border-radius:10px;background:#fff}.compare-summary div{padding:.7rem;border-right:1px solid #e1e7ee}.compare-summary div:last-child{border-right:0}.compare-summary span,.compare-summary strong{display:block}.compare-summary span{color:#667085;font-size:.61rem}.compare-summary strong{font-size:1rem}.compare-columns{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-top:.8rem}.compare-columns section,.modified-list{overflow:hidden;border:1px solid #e1e7ee;border-radius:10px;background:#fff}.compare-columns header,.modified-list>header{padding:.55rem .7rem;background:#f2f4f7;font-size:.7rem;font-weight:750}.compare-columns .added{border-left:4px solid #16a34a}.compare-columns .removed{border-left:4px solid #dc2626}.compare-columns article{padding:.55rem .7rem;border-top:1px solid #eef1f4}.compare-columns strong,.compare-columns span{display:block}.compare-columns span,.compare-columns p,.modified-list p{color:#667085;font-size:.62rem}.compare-columns p,.modified-list p{padding:.6rem}.modified-list{margin-top:.8rem}.modified-list article{padding:.7rem;border-top:1px solid #eef1f4}.modified-list h6{margin-bottom:.5rem}.modified-list article>div{display:grid;grid-template-columns:90px 1fr auto 1fr;align-items:center;gap:.45rem;margin-top:.3rem;font-size:.63rem}.modified-list del,.modified-list ins{padding:.3rem;border-radius:4px;text-decoration:none}.modified-list del{background:#fff1f2;color:#9f1239}.modified-list ins{background:#f0fdf4;color:#166534}@media(max-width:700px){.compare-summary{grid-template-columns:1fr 1fr}.compare-columns{grid-template-columns:1fr}.modified-list article>div{grid-template-columns:70px 1fr}.modified-list i{display:none}}
</style>
