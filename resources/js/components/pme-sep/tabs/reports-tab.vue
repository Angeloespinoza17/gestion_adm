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
      advancedOpen: false,
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
    activeFilterCount() {
      const ignored = ["report_type", "format", "pme_plan_id"];
      return Object.entries(this.filters).filter(([key, value]) => !ignored.includes(key) && value !== null && value !== "").length;
    },
    reportRowCount() {
      return (this.report?.sections || []).reduce((sum, section) => sum + (section.rows?.length || 0), 0);
    },
  },
  mounted() {
    this.filters.pme_plan_id = this.catalogs.active_plan_id || this.catalogs.plans?.[0]?.id || null;
  },
  methods: {
    humanizePmeStatus,
    resetFilters() {
      this.filters = {
        report_type: "general",
        format: "pantalla",
        pme_plan_id: this.catalogs.active_plan_id || this.catalogs.plans?.[0]?.id || null,
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
      };
      this.report = null;
    },
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
          <div><div class="fw-semibold">Generador de reportes PME / SEP</div><div class="small text-muted">Configura el alcance y genera una vista previa antes de exportar</div></div>
          <PmeHelpButton
            title="Ayuda: reportes PME / SEP"
            text="Aquí se generan reportes generales y específicos del módulo PME / SEP. Luego pueden exportarse a Excel o PDF e imprimirse."
          />
        </div>
      </template>

      <div class="report-primary-fields row g-3">
        <div class="col-md-3">
          <label class="form-label">Tipo de reporte</label>
          <BFormSelect v-model="filters.report_type" :options="reportTypeOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Plan</label>
          <BFormSelect v-model="filters.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Dimensión</label>
          <BFormSelect v-model="filters.pme_dimension_id" :options="dimensionOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <BButton variant="primary" class="w-100" :disabled="loading" @click="generate">
            <span v-if="loading" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-play-circle"></i>
            {{ loading ? "Generando..." : "Generar vista previa" }}
          </BButton>
        </div>
      </div>

      <div class="report-advanced-toggle">
        <button type="button" :aria-expanded="advancedOpen" @click="advancedOpen = !advancedOpen"><i class="bx bx-slider-alt"></i>Filtros avanzados <span v-if="activeFilterCount">{{ activeFilterCount }}</span><i class="bx" :class="advancedOpen ? 'bx-chevron-up' : 'bx-chevron-down'"></i></button>
        <button v-if="activeFilterCount" type="button" class="reset" @click="resetFilters"><i class="bx bx-reset"></i>Limpiar filtros</button>
      </div>

      <div v-if="advancedOpen" class="report-advanced row g-3">
        <div class="col-md-3">
          <label class="form-label">Año</label>
          <BFormSelect v-model="filters.academic_year_id" :options="yearOptions.map((item) => ({ value: item.value, text: item.label }))" />
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
      </div>

      <div class="report-actions">
        <div class="small text-muted"><i class="bx bx-info-circle me-1"></i>Las exportaciones respetan todos los filtros seleccionados.</div>
        <div class="d-flex gap-2 flex-wrap">
          <BButton variant="outline-success" :disabled="loading || !canExportReports" @click="exportExcel"><i class="bx bx-spreadsheet"></i>Excel</BButton>
          <BButton variant="outline-danger" :disabled="loading || !canExportReports" @click="exportPdf"><i class="bx bxs-file-pdf"></i>PDF</BButton>
          <BButton variant="outline-secondary" :disabled="!report?.sections?.length" @click="printReport"><i class="bx bx-printer"></i>Imprimir</BButton>
        </div>
      </div>
    </BCard>

    <BCard v-if="report" class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div><div class="fw-semibold">Vista previa del reporte</div><div class="small text-muted">{{ reportRowCount }} filas en {{ report.sections?.length || 0 }} secciones</div></div>
          <BBadge variant="light">ID {{ report.meta?.report_id }} · {{ report.meta?.generated_at }}</BBadge>
        </div>
      </template>

      <section v-for="section in report.sections" :key="section.title" class="report-section">
        <div class="report-section__heading"><div><i class="bx bx-table"></i><h6>{{ section.title }}</h6></div><span>{{ section.rows?.length || 0 }} filas</span></div>
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
      </section>
    </BCard>

    <div v-else class="report-empty">
      <span><i class="bx bx-bar-chart-square"></i></span><strong>Configura y genera tu reporte</strong><p>La vista previa aparecerá aquí. Luego podrás exportarla a Excel, PDF o imprimirla.</p>
    </div>
  </div>
</template>

<style scoped>
.report-advanced-toggle{display:flex;align-items:center;justify-content:space-between;margin-top:.85rem;padding-top:.7rem;border-top:1px solid #e8edf3}.report-advanced-toggle button{display:inline-flex;align-items:center;gap:.35rem;border:0;background:transparent;color:#3156a6;font-size:.68rem;font-weight:700}.report-advanced-toggle button span{display:grid;place-items:center;min-width:18px;height:18px;border-radius:9px;background:#3156a6;color:#fff;font-size:.56rem}.report-advanced-toggle button.reset{color:#778397;font-weight:600}.report-advanced{margin-top:.1rem;padding:.8rem;border:1px solid #e3e8ef;border-radius:9px;background:#f8fafd}.report-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem;padding-top:.8rem;border-top:1px solid #e8edf3}.report-section{margin-bottom:1rem;padding:0;border:1px solid #e1e6ed;border-radius:9px}.report-section:last-child{margin-bottom:0}.report-section__heading{display:flex;align-items:center;justify-content:space-between;padding:.65rem .75rem;border-bottom:1px solid #e5eaf0;background:#f8fafc}.report-section__heading>div{display:flex;align-items:center;gap:.4rem}.report-section__heading i{color:#3156a6}.report-section__heading h6{margin:0;color:#344054;font-size:.72rem}.report-section__heading>span{color:#7c8798;font-size:.59rem}.report-section :deep(.table-responsive){border:0;border-radius:0}.report-empty{display:grid;place-items:center;align-content:center;min-height:270px;padding:2rem;border:1px dashed #cdd6e2;border-radius:12px;background:rgba(255,255,255,.55);text-align:center}.report-empty>span{display:grid;place-items:center;width:52px;height:52px;margin-bottom:.6rem;border-radius:14px;background:#eaf1fc;color:#3156a6;font-size:1.55rem}.report-empty strong{color:#344054;font-size:.82rem}.report-empty p{max-width:420px;margin:.25rem 0 0;color:#7a8698;font-size:.67rem}@media(max-width:767px){.report-actions{align-items:stretch;flex-direction:column}.report-actions>div:last-child>*{flex:1}.report-advanced-toggle{align-items:flex-start;gap:.5rem;flex-direction:column}}
</style>
