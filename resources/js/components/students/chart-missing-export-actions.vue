<script>
export default {
  props: {
    loadingFormat: {
      type: String,
      default: "",
    },
    compact: {
      type: Boolean,
      default: false,
    },
  },
  emits: ["export-pdf", "export-excel"],
};
</script>

<template>
  <div class="chart-export-actions" :class="{ compact }" aria-label="Exportar estudiantes sin información">
    <button
      type="button"
      class="chart-export-button chart-export-pdf"
      title="Exportar estudiantes sin información a PDF"
      aria-label="Exportar estudiantes sin información a PDF"
      :disabled="Boolean(loadingFormat)"
      @click.stop="$emit('export-pdf')"
    >
      <i v-if="loadingFormat === 'pdf'" class="bx bx-loader-alt bx-spin"></i>
      <span v-else class="chart-export-icon"><i class="mdi mdi-adobe-acrobat"></i></span>
    </button>
    <button
      type="button"
      class="chart-export-button chart-export-excel"
      title="Exportar estudiantes sin información a Excel"
      aria-label="Exportar estudiantes sin información a Excel"
      :disabled="Boolean(loadingFormat)"
      @click.stop="$emit('export-excel')"
    >
      <i v-if="loadingFormat === 'excel'" class="bx bx-loader-alt bx-spin"></i>
      <span v-else class="chart-export-icon"><i class="mdi mdi-microsoft-excel"></i></span>
    </button>
  </div>
</template>

<style scoped>
.chart-export-actions {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.3rem;
}

.chart-export-button {
  display: inline-flex;
  width: 34px;
  height: 34px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid;
  border-radius: 7px;
  background: #fff;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.chart-export-button:disabled { cursor: wait; opacity: 0.62; }
.chart-export-pdf { border-color: #fecaca; background: #fff8f8; color: #d92d3a; }
.chart-export-pdf:hover:not(:disabled) { border-color: #fda4af; background: #fff1f2; }
.chart-export-excel { border-color: #b7dfc5; background: #f6fcf8; color: #217346; }
.chart-export-excel:hover:not(:disabled) { border-color: #86c79e; background: #edf8f1; }

.chart-export-icon {
  display: inline-flex;
  width: 21px;
  height: 21px;
  align-items: center;
  justify-content: center;
  border-radius: 3px;
  color: #fff;
}

.chart-export-pdf .chart-export-icon { background: #df2634; }
.chart-export-excel .chart-export-icon { background: #217346; }
.chart-export-icon i { font-size: 15px; line-height: 1; }
.chart-export-button > .bx { font-size: 1rem; }

.compact .chart-export-button { width: 30px; height: 30px; border-radius: 6px; }
.compact .chart-export-icon { width: 19px; height: 19px; }
.compact .chart-export-icon i { font-size: 14px; }
</style>
