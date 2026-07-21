<script setup>
import { ref } from "vue";
const props = defineProps({
  title: { type: String, required: true }, kicker: { type: String, default: "Análisis" },
  help: { type: String, required: true }, source: { type: String, default: "Registros de asistencia" },
  headers: { type: Array, default: () => [] }, rows: { type: Array, default: () => [] }, empty: { type: Boolean, default: false },
});
const tableVisible = ref(false); const helpVisible = ref(false); const fullscreen = ref(false);
</script>

<template>
  <article class="chart-panel" :class="{ 'is-fullscreen': fullscreen }">
    <header>
      <div><span>{{ kicker }}</span><h3>{{ title }}</h3></div>
      <div class="panel-actions">
        <button type="button" title="Vista tabular" :aria-pressed="tableVisible" @click="tableVisible = !tableVisible"><i class="bx bx-table"></i></button>
        <button type="button" :title="fullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'" @click="fullscreen = !fullscreen"><i class="bx" :class="fullscreen ? 'bx-exit-fullscreen' : 'bx-fullscreen'"></i></button>
        <button type="button" title="Cómo interpretar" @click="helpVisible = true"><i class="bx bx-help-circle"></i></button>
      </div>
    </header>
    <div v-if="empty" class="chart-empty"><i class="bx bx-bar-chart-alt-2"></i><strong>Sin datos para este periodo</strong><span>Ajusta los filtros o confirma que existan jornadas registradas.</span></div>
    <div v-else-if="!tableVisible" class="chart-slot"><slot /></div>
    <div v-else class="table-responsive chart-table"><table class="table mb-0"><thead><tr><th v-for="header in headers" :key="header">{{ header }}</th></tr></thead><tbody><tr v-for="(row,index) in rows" :key="index"><td v-for="(cell,cellIndex) in row" :key="cellIndex">{{ cell }}</td></tr></tbody></table></div>
    <footer><span>Fuente: {{ source }}</span><span>Vista accesible disponible</span></footer>
    <div v-if="helpVisible" class="help-backdrop" role="dialog" aria-modal="true" :aria-label="`Ayuda de ${title}`" @click.self="helpVisible = false" @keydown.esc="helpVisible = false">
      <div class="help-dialog"><header><h4>{{ title }}</h4><button type="button" title="Cerrar" @click="helpVisible = false"><i class="bx bx-x"></i></button></header><p>{{ help }}</p><p class="help-limit"><strong>Limitación:</strong> solo considera registros válidos dentro del periodo y filtros activos.</p></div>
    </div>
  </article>
</template>

<style scoped>
.chart-panel{min-width:0;border:1px solid #dfe5ec;border-radius:8px;background:#fff;padding:.9rem}.chart-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:.35rem}.chart-panel>header span{color:#738093;font-size:.61rem;font-weight:700;text-transform:uppercase}.chart-panel h3{margin:.12rem 0 0;color:#273244;font-size:.88rem;font-weight:750}.panel-actions{display:flex;gap:.25rem}.panel-actions button,.help-dialog button{display:grid;place-items:center;width:30px;height:30px;border:1px solid #dfe5ec;border-radius:6px;background:#fff;color:#405189}.chart-slot{min-height:270px}.chart-empty{display:grid;place-items:center;align-content:center;min-height:270px;color:#7a8596;text-align:center}.chart-empty i{font-size:2rem}.chart-empty strong{margin-top:.4rem;color:#465366}.chart-empty span{margin-top:.2rem;font-size:.7rem}.chart-table{min-height:270px;max-height:340px}.chart-table table{font-size:.7rem}.chart-table th{position:sticky;top:0;background:#f6f8fa;color:#536174}.chart-panel>footer{display:flex;justify-content:space-between;gap:.5rem;padding-top:.45rem;border-top:1px solid #eef1f4;color:#8a94a3;font-size:.57rem}.chart-panel.is-fullscreen{position:fixed;z-index:1085;inset:1rem;overflow:auto}.chart-panel.is-fullscreen .chart-slot{min-height:calc(100vh - 130px)}.help-backdrop{position:fixed;z-index:1090;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(24,32,45,.48)}.help-dialog{width:min(460px,100%);border-radius:8px;background:#fff;padding:1rem;box-shadow:0 20px 50px rgba(25,35,50,.2)}.help-dialog header{display:flex;align-items:center;justify-content:space-between}.help-dialog h4{margin:0;font-size:1rem}.help-dialog p{margin:.75rem 0 0;color:#596678;font-size:.78rem;line-height:1.55}.help-limit{padding:.65rem;background:#f5f7fa;border-left:3px solid #405189}@media(max-width:520px){.chart-panel>footer{flex-direction:column}.chart-panel.is-fullscreen{inset:.5rem}}
</style>
