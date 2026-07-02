<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import {
  downloadExcelWorkbook,
  downloadPdfReport,
  formatPmeError,
  humanizePmeStatus,
  normalizeOptions,
  printPmeHtml,
  showPmeError,
} from "../module-utils";

export default {
  components: { PmeHelpButton },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      report: null,
      filters: {
        report_type: "general",
        format: "pantalla",
        pme_plan_id: null,
        academic_year_id: null,
        pme_dimension_id: null,
        pme_objective_id: null,
        pme_strategy_id: null,
        responsible_user_id: null,
        state: null,
        funding_source: null,
        course_section_id: null,
        evidence_type: null,
        date_from: null,
        date_to: null,
      },
    };
  },
  computed: {
    planOptions() {
      return normalizeOptions(this.catalogs.plans, true);
    },
    reportTypeOptions() {
      return normalizeOptions(this.catalogs.options?.report_types || [], false);
    },
    yearOptions() {
      return normalizeOptions(this.catalogs.academic_years, true);
    },
    dimensionOptions() {
      return normalizeOptions(this.catalogs.dimensions, true);
    },
    responsibleOptions() {
      return normalizeOptions(this.catalogs.responsibles, true);
    },
    stateOptions() {
      return normalizeOptions(this.catalogs.options?.action_states || [], true);
    },
    fundingOptions() {
      return normalizeOptions(this.catalogs.options?.action_funding_sources || [], true);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses || [], true);
    },
    evidenceOptions() {
      return normalizeOptions(this.catalogs.options?.evidence_types || [], true);
    },
    canExportReports() {
      return Boolean(this.catalogs.capabilities?.can_export_reports);
    },
  },
  mounted() {
    this.filters.pme_plan_id = this.catalogs.active_plan_id || this.catalogs.plans?.[0]?.id || null;
  },
  methods: {
    humanizePmeStatus,
    async requestReport(format = "pantalla") {
      const response = await axios.post("/api/pme-sep/reports", {
        ...this.filters,
        format,
      });

      return response.data;
    },
    async generate() {
      this.loading = true;
      try {
        this.report = await this.requestReport("pantalla");
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo generar el reporte PME / SEP."));
      } finally {
        this.loading = false;
      }
    },
    async exportExcel() {
      this.loading = true;
      try {
        const report = await this.requestReport("excel");
        this.report = report;
        downloadExcelWorkbook("reporte-pme-sep", report.sections);
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo exportar el reporte a Excel."));
      } finally {
        this.loading = false;
      }
    },
    async exportPdf() {
      this.loading = true;
      try {
        const report = await this.requestReport("pdf");
        this.report = report;
        downloadPdfReport("reporte-pme-sep", "Reporte PME / SEP", "Consolidado del plan de mejoramiento educativo", report.sections);
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo exportar el reporte a PDF."));
      } finally {
        this.loading = false;
      }
    },
    printReport() {
      if (!this.report?.sections?.length) return;
      const html = this.report.sections
        .map((section) => {
          const header = `<tr>${(section.headers || []).map((cell) => `<th>${cell}</th>`).join("")}</tr>`;
          const rows = (section.rows || [])
            .map((row) => `<tr>${row.map((cell) => `<td>${cell ?? ""}</td>`).join("")}</tr>`)
            .join("");
          return `<h3>${section.title}</h3><table>${header}${rows}</table>`;
        })
        .join("");
      printPmeHtml("Reporte PME / SEP", html);
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <BCard class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">Generador de reportes PME / SEP</div>
          <PmeHelpButton
            title="Ayuda: reportes PME / SEP"
            text="Aquí se generan reportes generales y específicos del módulo PME / SEP. Luego pueden exportarse a Excel o PDF e imprimirse."
          />
        </div>
      </template>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tipo de reporte</label>
          <BFormSelect v-model="filters.report_type" :options="reportTypeOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan</label>
          <BFormSelect v-model="filters.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Año</label>
          <BFormSelect v-model="filters.academic_year_id" :options="yearOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Dimensión</label>
          <BFormSelect v-model="filters.pme_dimension_id" :options="dimensionOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Responsable</label>
          <BFormSelect v-model="filters.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.state" :options="stateOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fuente financiamiento</label>
          <BFormSelect v-model="filters.funding_source" :options="fundingOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo evidencia</label>
          <BFormSelect v-model="filters.evidence_type" :options="evidenceOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.date_from" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.date_to" type="date" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <BButton variant="primary" class="w-100" :disabled="loading" @click="generate">
            {{ loading ? "Generando..." : "Generar reporte" }}
          </BButton>
        </div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <BButton variant="outline-success" :disabled="loading || !canExportReports" @click="exportExcel">Exportar Excel</BButton>
        <BButton variant="outline-danger" :disabled="loading || !canExportReports" @click="exportPdf">Exportar PDF</BButton>
        <BButton variant="outline-secondary" :disabled="!report?.sections?.length" @click="printReport">Imprimir</BButton>
      </div>
    </BCard>

    <BCard v-if="report" class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">Resultado del reporte</div>
          <div class="small text-muted">
            Reporte ID {{ report.meta?.report_id }} · Generado {{ report.meta?.generated_at }}
          </div>
        </div>
      </template>

      <div v-for="section in report.sections" :key="section.title" class="mb-4">
        <h6>{{ section.title }}</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th v-for="header in section.headers" :key="header">{{ header }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, rowIndex) in section.rows" :key="`${section.title}-${rowIndex}`">
                <td v-for="(cell, cellIndex) in row" :key="`${rowIndex}-${cellIndex}`">{{ cell }}</td>
              </tr>
              <tr v-if="!section.rows.length">
                <td :colspan="section.headers.length" class="text-center text-muted">Sin datos para los filtros seleccionados.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </BCard>
  </div>
</template>
