<script>
import axios from "axios";
import LibraryHelpButton from "../help-button.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  downloadExcelWorkbook,
  downloadPdfReport,
  formatLibraryDate,
  formatLibraryError,
  printLibraryHtml,
} from "../module-utils";

export default {
  components: {
    LibraryHelpButton,
    LoadingState,
  },
  props: {
    catalogs: { type: Object, required: true },
  },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      report: {
        summary: {},
        sections: {},
        detail: {},
      },
      filters: {
        period: "monthly",
        date_from: "",
        date_to: "",
      },
    };
  },
  mounted() {
    this.load();
  },
  computed: {
    summaryCards() {
      const summary = this.report.summary || {};
      return [
        { label: "Préstamos", value: summary.total_loans || 0 },
        { label: "Devoluciones", value: summary.total_returns || 0 },
        { label: "Mora", value: summary.total_overdue || 0 },
        { label: "Reservas", value: summary.total_reservations || 0 },
        { label: "Uso de espacios", value: summary.total_spaces || 0 },
        { label: "Plan lector", value: summary.total_reading_plans || 0 },
      ];
    },
    sectionRows() {
      const sections = this.report.sections || {};
      return [
        { title: "Préstamos por curso", rows: Object.entries(sections.loans_by_course || {}).map(([label, total]) => [label, total]) },
        { title: "Préstamos por libro", rows: Object.entries(sections.loans_by_book || {}).map(([label, total]) => [label, total]) },
        { title: "Préstamos por categoría", rows: Object.entries(sections.loans_by_category || {}).map(([label, total]) => [label, total]) },
        { title: "Mora por usuario", rows: Object.entries(sections.overdue_by_user || {}).map(([label, total]) => [label, total]) },
        { title: "Reservas por tipo", rows: Object.entries(sections.reservations_by_type || {}).map(([label, total]) => [label, total]) },
        { title: "Plan lector por estado", rows: Object.entries(sections.reading_plan_by_status || {}).map(([label, total]) => [label, total]) },
        { title: "Uso de espacios por actividad", rows: Object.entries(sections.spaces_by_activity || {}).map(([label, total]) => [label, total]) },
      ];
    },
  },
  methods: {
    formatLibraryDate,
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/reportes", {
          params: this.filters,
        });
        this.report = response.data;
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron generar los reportes.");
      } finally {
        this.loading = false;
      }
    },
    exportExcel() {
      const sections = this.sectionRows.map((section) => ({
        title: section.title,
        headers: ["Agrupación", "Total"],
        rows: section.rows,
      }));
      downloadExcelWorkbook("biblioteca-reportes", sections);
    },
    exportPdf() {
      const sections = this.sectionRows.map((section) => ({
        title: section.title,
        headers: ["Agrupación", "Total"],
        rows: section.rows,
      }));
      downloadPdfReport("biblioteca-reportes", "Reportes Biblioteca Escolar", "Consolidado exportable", sections);
    },
    printReport() {
      const html = this.sectionRows
        .map(
          (section) => `
            <h3>${section.title}</h3>
            <table>
              <thead><tr><th>Agrupación</th><th>Total</th></tr></thead>
              <tbody>${section.rows.map((row) => `<tr><td>${row[0]}</td><td>${row[1]}</td></tr>`).join("")}</tbody>
            </table>
          `
        )
        .join("");
      printLibraryHtml("Reportes Biblioteca Escolar", html);
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Consolidado estadístico y exportable</div>
      <div class="d-flex gap-2 flex-wrap">
        <LibraryHelpButton
          title="Ayuda: reportes de biblioteca"
          text="Aquí se consolidan estadísticas y reportes diarios, semanales, mensuales, semestrales o anuales para exportación y auditoría."
        />
        <BButton variant="outline-success" @click="exportExcel">Excel</BButton>
        <BButton variant="outline-danger" @click="exportPdf">PDF</BButton>
        <BButton variant="outline-secondary" @click="printReport">Imprimir</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Periodo</label><BFormSelect v-model="filters.period" :options="[
          { value: 'daily', text: 'Diario' },
          { value: 'weekly', text: 'Semanal' },
          { value: 'monthly', text: 'Mensual' },
          { value: 'semestral', text: 'Semestral' },
          { value: 'annual', text: 'Anual' },
        ]" /></div>
        <div class="col-md-3"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-3"><BButton variant="primary" @click="load">Generar reporte</BButton></div>
      </div>
    </BCard>

    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Generando reportes..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div v-for="card in summaryCards" :key="card.label" class="col-md-4 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="small text-muted">{{ card.label }}</div>
            <div class="display-6 fw-semibold">{{ card.value }}</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div v-for="section in sectionRows" :key="section.title" class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">{{ section.title }}</div></template>
            <BTable small responsive :items="section.rows.map((row) => ({ label: row[0], total: row[1] }))" :fields="[{ key: 'label', label: 'Agrupación' }, { key: 'total', label: 'Total' }]" />
          </BCard>
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <template #header><div class="fw-semibold">Alertas vigentes</div></template>
        <BTable
          small
          responsive
          :items="report.sections?.current_alerts || []"
          :fields="[
            { key: 'title', label: 'Alerta' },
            { key: 'alert_type', label: 'Tipo' },
            { key: 'alert_level', label: 'Nivel' },
            { key: 'created_at', label: 'Fecha' },
          ]"
        >
          <template #cell(created_at)="{ item }">{{ formatLibraryDate(item.created_at) }}</template>
        </BTable>
      </BCard>
    </template>
  </div>
</template>
