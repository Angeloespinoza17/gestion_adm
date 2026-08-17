<script setup>
import { ref } from "vue";

defineProps({
  title: { type: String, required: true },
  kicker: { type: String, default: "Análisis" },
  help: { type: String, required: true },
  headers: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  empty: { type: Boolean, default: false },
  source: { type: String, default: "Libro Digital de Clases" },
});

const tableVisible = ref(false);
const helpVisible = ref(false);
</script>

<template>
  <article class="lcd-analytics-panel">
    <header>
      <div class="lcd-analytics-panel__heading">
        <span class="lcd-eyebrow">{{ kicker }}</span>
        <h3>{{ title }}</h3>
      </div>
      <div class="lcd-analytics-panel__actions">
        <button class="lcd-icon-button" type="button" title="Alternar vista tabular" :aria-label="tableVisible ? `Ver gráfico de ${title}` : `Ver tabla de ${title}`" :aria-pressed="tableVisible" @click="tableVisible = !tableVisible"><i class="bx" :class="tableVisible ? 'bx-bar-chart-alt-2' : 'bx-table'" aria-hidden="true"></i></button>
        <button class="lcd-icon-button" type="button" title="Cómo interpretar" :aria-label="`Cómo interpretar ${title}`" :aria-expanded="helpVisible" @click="helpVisible = !helpVisible"><i class="bx bx-help-circle" aria-hidden="true"></i></button>
      </div>
    </header>
    <div v-if="helpVisible" class="lcd-analytics-panel__help" role="note"><span aria-hidden="true"><i class="bx bx-bulb"></i></span><p><strong>Cómo interpretar</strong>{{ help }}</p></div>
    <div v-if="empty" class="lcd-analytics-panel__empty"><span class="lcd-analytics-panel__empty-icon" aria-hidden="true"><i class="bx bx-bar-chart-alt-2"></i></span><strong>Sin datos para este periodo</strong><span>Ajusta los filtros o confirma que existan registros válidos.</span></div>
    <div v-else-if="!tableVisible" class="lcd-analytics-panel__chart" :aria-label="`Gráfico: ${title}`"><slot /></div>
    <div v-else class="table-responsive lcd-analytics-panel__table" tabindex="0" :aria-label="`Tabla de datos: ${title}`">
      <table class="table table-sm align-middle mb-0">
        <caption class="visually-hidden">Datos que sustentan el gráfico {{ title }}</caption>
        <thead><tr><th v-for="header in headers" :key="header" scope="col">{{ header }}</th></tr></thead>
        <tbody><tr v-for="(row, rowIndex) in rows" :key="rowIndex"><td v-for="(cell, cellIndex) in row" :key="cellIndex">{{ cell }}</td></tr></tbody>
      </table>
    </div>
    <footer><span><i class="bx bx-data" aria-hidden="true"></i> Fuente: {{ source }}</span><span><i class="bx bx-user-check" aria-hidden="true"></i> Vista tabular disponible</span></footer>
  </article>
</template>

<style scoped>
.lcd-analytics-panel {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--lcd-border);
  border-radius: var(--lcd-radius-lg);
  background: var(--lcd-surface-raised);
  box-shadow: var(--lcd-shadow-sm);
}

.lcd-analytics-panel > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  min-height: 76px;
  padding: 1rem 1rem 0.75rem;
  border-bottom: 1px solid var(--lcd-border);
  background: linear-gradient(112deg, var(--lcd-brand-50), var(--lcd-surface-raised) 68%);
}

.lcd-analytics-panel__heading {
  min-width: 0;
}

.lcd-analytics-panel__heading .lcd-eyebrow {
  margin-bottom: 0.18rem;
  font-size: 0.68rem;
}

.lcd-analytics-panel h3 {
  margin: 0;
  color: var(--lcd-ink);
  font-size: 0.95rem;
  font-weight: 750;
  letter-spacing: -0.012em;
}

.lcd-analytics-panel__actions {
  display: flex;
  gap: 0.35rem;
}

.lcd-analytics-panel__actions .lcd-icon-button {
  width: 36px;
  height: 36px;
}

.lcd-analytics-panel__help {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  margin: 0.8rem 1rem 0;
  padding: 0.7rem 0.8rem;
  border: 1px solid rgba(46, 105, 156, 0.2);
  border-radius: var(--lcd-radius-sm);
  background: var(--lcd-brand-50);
  color: var(--lcd-muted);
  font-size: 0.78rem;
  line-height: 1.55;
}

.lcd-analytics-panel__help > span {
  display: grid;
  flex: 0 0 30px;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 9px;
  background: var(--lcd-brand-100);
  color: var(--lcd-brand-700);
  font-size: 1rem;
}

.lcd-analytics-panel__help p {
  margin: 0;
}

.lcd-analytics-panel__help strong {
  display: block;
  margin-bottom: 0.08rem;
  color: var(--lcd-ink);
}

.lcd-analytics-panel__chart,
.lcd-analytics-panel__empty,
.lcd-analytics-panel__table {
  min-height: 290px;
  margin: 0.35rem 0.75rem 0;
}

.lcd-analytics-panel__empty {
  display: grid;
  place-items: center;
  align-content: center;
  padding: 2rem 1rem;
  color: var(--lcd-muted);
  text-align: center;
}

.lcd-analytics-panel__empty-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border: 1px solid var(--lcd-border);
  border-radius: 17px;
  background: var(--lcd-surface-muted);
  color: var(--lcd-brand-600);
  font-size: 1.65rem;
}

.lcd-analytics-panel__empty strong {
  margin-top: 0.75rem;
  color: var(--lcd-ink);
  font-size: 0.88rem;
}

.lcd-analytics-panel__empty > span:last-child {
  max-width: 340px;
  margin-top: 0.25rem;
  font-size: 0.78rem;
  line-height: 1.5;
}

.lcd-analytics-panel__table {
  max-height: 330px;
  border: 1px solid var(--lcd-border);
  border-radius: var(--lcd-radius-sm);
}

.lcd-analytics-panel__table table {
  font-size: 0.78rem;
}

.lcd-analytics-panel__table th {
  position: sticky;
  top: 0;
  z-index: 1;
}

.lcd-analytics-panel > footer {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 0.35rem;
  padding: 0.7rem 1rem;
  border-top: 1px solid var(--lcd-border);
  color: var(--lcd-subtle);
  font-size: 0.7rem;
}

.lcd-analytics-panel > footer span {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

@media (max-width: 575.98px) {
  .lcd-analytics-panel > footer {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
