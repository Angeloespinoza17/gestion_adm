<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import SupportStudentSearch from "../student-search.vue";
import {
  basicApexOptions,
  confirmSupportAction,
  downloadExcelWorkbook,
  downloadPdfReport,
  extractChartLabels,
  extractChartTotals,
  formatSupportDate,
  formatSupportDateTime,
  formatSupportError,
  humanizeSupportStatus,
  normalizeOptions,
  printSupportHtml,
  showSupportError,
  showSupportSuccess,
} from "../module-utils";

export default {
  components: {
    LoadingState,
    SupportHelpButton,
    SupportStatusBadge,
    SupportStudentSearch,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      error: null,
      report: null,
      filters: {
        period: "mensual",
        from: "",
        to: "",
        course_section_id: null,
        student_profile_id: null,
        professional_id: null,
        professional_role_name: null,
        attention_type_label: null,
        motive_label: null,
        status: null,
        professional_area_name: null,
        confidentiality_level: null,
        anonymize: false,
      },
    };
  },
  computed: {
    periodOptions() {
      return normalizeOptions(this.catalogs.report_period_options);
    },
    courseOptions() {
      return normalizeOptions(this.catalogs.courses, true);
    },
    professionalOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.professionals || []).map((item) => ({
          value: item.user_id,
          text: item.staff?.full_name || item.user?.name || item.professional_role_name,
        }))
      );
    },
    roleOptions() {
      const values = [...new Set((this.catalogs.professionals || []).map((item) => item.professional_role_name).filter(Boolean))];
      return [{ value: null, text: "Todos" }].concat(values.map((item) => ({ value: item, text: item })));
    },
    typeOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.attention_types || []).map((item) => ({
          value: item.name,
          text: item.name,
        }))
      );
    },
    motiveOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.motives || []).map((item) => ({
          value: item.name,
          text: item.name,
        }))
      );
    },
    confidentialityOptions() {
      return normalizeOptions(this.catalogs.confidentiality_options, true);
    },
    areaOptions() {
      return [{ value: null, text: "Todas" }].concat(
        (this.catalogs.area_options || []).map((item) => ({
          value: item.label,
          text: item.label,
        }))
      );
    },
    statusOptions() {
      const values = new Map();
      [
        ...(this.catalogs.attention_status_options || []),
        ...(this.catalogs.derivation_status_options || []),
        ...(this.catalogs.follow_up_status_options || []),
        ...(this.catalogs.plan_status_options || []),
        { value: "abierta", label: "Abierta" },
        { value: "cancelada", label: "Cancelada" },
      ].forEach((item) => {
        if (!item?.value) return;
        values.set(item.value, item.label || humanizeSupportStatus(item.value));
      });

      return [{ value: null, text: "Todos" }].concat(
        [...values.entries()].map(([value, text]) => ({ value, text }))
      );
    },
    summaryCards() {
      const summary = this.report?.summary || {};
      return [
        { label: "Atenciones", value: summary.attentions_total || 0, variant: "primary" },
        { label: "Derivaciones", value: summary.derivations_total || 0, variant: "warning" },
        { label: "Seguimientos", value: summary.follow_ups_total || 0, variant: "info" },
        { label: "Planes", value: summary.plans_total || 0, variant: "success" },
        { label: "Entrevistas", value: summary.interviews_total || 0, variant: "secondary" },
        { label: "Confidenciales", value: summary.confidential_cases_total || 0, variant: "dark" },
      ];
    },
    professionalChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.report?.attentions_by_professional),
          horizontal: true,
          colors: ["#556ee6"],
        }),
      };
    },
    motiveChartOptions() {
      return {
        labels: extractChartLabels(this.report?.attentions_by_motive),
        legend: { position: "bottom" },
        dataLabels: { enabled: true },
        colors: ["#34c38f", "#556ee6", "#f1b44c", "#f46a6a", "#50a5f1", "#74788d", "#8e44ad", "#ff7f50"],
      };
    },
    confidentialityChartOptions() {
      return {
        ...basicApexOptions({
          categories: extractChartLabels(this.report?.confidentiality_breakdown),
          colors: ["#f46a6a"],
        }),
      };
    },
    reportTitle() {
      const range = this.report?.date_range || {};
      return `Reporte Equipo de Apoyo ${range.from || ""} a ${range.to || ""}`.trim();
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatSupportDate,
    formatSupportDateTime,
    humanizeSupportStatus,
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/reports", {
          params: this.filters,
        });
        this.report = response.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron generar los reportes del módulo.");
      } finally {
        this.loading = false;
      }
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
    },
    barSeries(items, name = "Total") {
      return [{ name, data: extractChartTotals(items) }];
    },
    donutSeries(items) {
      return extractChartTotals(items);
    },
    buildSections() {
      if (!this.report) return [];

      const attentions = (this.report.detail_rows?.attentions || []).map((item) => [
        this.formatSupportDateTime(item.attended_at),
        item.student?.full_name || item.student_full_name_snapshot || "Estudiante anonimizado",
        item.professional_role_name || "-",
        item.attention_type_label || "-",
        item.motive_label || item.reason_summary || "-",
        item.course_name_snapshot || "-",
        this.humanizeSupportStatus(item.status),
        this.humanizeSupportStatus(item.confidentiality_level),
      ]);

      const derivations = (this.report.detail_rows?.derivations || []).map((item) => [
        this.formatSupportDateTime(item.derived_at),
        item.student?.full_name || "Estudiante anonimizado",
        item.destination_area_name || "-",
        item.reason || "-",
        this.humanizeSupportStatus(item.status),
        this.humanizeSupportStatus(item.urgency_level),
      ]);

      const followUps = (this.report.detail_rows?.follow_ups || []).map((item) => [
        this.formatSupportDateTime(item.scheduled_at),
        item.student_profile_id || "-",
        item.comment || "-",
        this.humanizeSupportStatus(item.status),
        item.next_action || "-",
      ]);

      const plans = (this.report.detail_rows?.plans || []).map((item) => [
        this.formatSupportDate(item.start_date),
        item.student_profile_id || "-",
        item.area_name || "-",
        item.motive || "-",
        this.humanizeSupportStatus(item.status),
      ]);

      const interviews = (this.report.detail_rows?.interviews || []).map((item) => [
        this.formatSupportDateTime(item.interview_at),
        item.student?.full_name || "Estudiante anonimizado",
        this.humanizeSupportStatus(item.interview_type),
        item.motive || "-",
        this.humanizeSupportStatus(item.status),
      ]);

      return [
        {
          title: "Atenciones",
          headers: ["Fecha", "Estudiante", "Profesional", "Tipo", "Motivo", "Curso", "Estado", "Confidencialidad"],
          rows: attentions,
        },
        {
          title: "Derivaciones",
          headers: ["Fecha", "Estudiante", "Área destino", "Motivo", "Estado", "Urgencia"],
          rows: derivations,
        },
        {
          title: "Seguimientos",
          headers: ["Fecha", "Estudiante", "Comentario", "Estado", "Próxima acción"],
          rows: followUps,
        },
        {
          title: "Planes",
          headers: ["Inicio", "Estudiante", "Área", "Motivo", "Estado"],
          rows: plans,
        },
        {
          title: "Entrevistas",
          headers: ["Fecha", "Estudiante", "Tipo", "Motivo", "Estado"],
          rows: interviews,
        },
      ].filter((section) => section.rows.length > 0);
    },
    async exportExcel() {
      if (!this.report) return;

      const confirmation = await confirmSupportAction({
        title: "Exportar a Excel",
        text: "Se generará un archivo con el consolidado actual del reporte.",
        confirmButtonText: "Exportar",
        icon: "question",
      });

      if (!confirmation.isConfirmed) return;

      downloadExcelWorkbook("reporte-apoyo-profesional.xls", this.buildSections());
      await showSupportSuccess("Reporte exportado a Excel.");
    },
    async exportPdf() {
      if (!this.report) return;

      downloadPdfReport(
        "reporte-apoyo-profesional.pdf",
        "Reporte Equipo de Apoyo",
        this.reportTitle,
        this.buildSections()
      );
      await showSupportSuccess("Reporte exportado a PDF.");
    },
    printReport() {
      const sections = this.buildSections();
      const html = `
        <h1>Reporte Equipo de Apoyo</h1>
        <div class="muted">${this.reportTitle}</div>
        ${sections
          .map(
            (section) => `
              <h3>${section.title}</h3>
              <table>
                <thead>
                  <tr>${section.headers.map((header) => `<th>${header}</th>`).join("")}</tr>
                </thead>
                <tbody>
                  ${section.rows
                    .map((row) => `<tr>${row.map((cell) => `<td>${cell ?? ""}</td>`).join("")}</tr>`)
                    .join("")}
                </tbody>
              </table>
            `
          )
          .join("")}
      `;

      printSupportHtml("Reporte Equipo de Apoyo", html);
    },
  },
};
</script>

<template>
  <div>
    <div class="d-flex justify-content-end mb-3">
      <SupportHelpButton
        title="Ayuda: reportes del módulo"
        text="En esta sección puedes generar reportes por período, profesional, curso, estado, motivo, área o confidencialidad, con opción de anonimizar datos sensibles y exportar a Excel o PDF."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Período</label>
          <BFormSelect v-model="filters.period" :options="periodOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
        </div>
        <div class="col-xl-6">
          <SupportStudentSearch button-label="Seleccionar" @selected="selectStudent" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Profesional</label>
          <BFormSelect v-model="filters.professional_id" :options="professionalOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Rol profesional</label>
          <BFormSelect v-model="filters.professional_role_name" :options="roleOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo atención</label>
          <BFormSelect v-model="filters.attention_type_label" :options="typeOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Motivo</label>
          <BFormSelect v-model="filters.motive_label" :options="motiveOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="statusOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Área</label>
          <BFormSelect v-model="filters.professional_area_name" :options="areaOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Confidencialidad</label>
          <BFormSelect v-model="filters.confidentiality_level" :options="confidentialityOptions" />
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <BFormCheckbox v-model="filters.anonymize">Anonimizar datos sensibles</BFormCheckbox>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
          <BButton variant="primary" @click="load">Generar reporte</BButton>
          <BButton variant="outline-success" :disabled="!report" @click="exportExcel">Excel</BButton>
          <BButton variant="outline-danger" :disabled="!report" @click="exportPdf">PDF</BButton>
          <BButton variant="outline-secondary" :disabled="!report" @click="printReport">Imprimir</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Generando reporte del equipo de apoyo..." compact />
    </BCard>

    <template v-else-if="report">
      <div class="row g-3 mb-3">
        <div v-for="card in summaryCards" :key="card.label" class="col-sm-6 col-xl-2">
          <BCard class="border-0 shadow-sm h-100">
            <div class="small text-muted">{{ card.label }}</div>
            <div class="display-6 fw-semibold">{{ card.value }}</div>
            <SupportStatusBadge :status="card.variant === 'dark' ? 'confidencial' : 'abierta'" :label="card.label" class="mt-2" />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Atenciones por profesional</div></template>
            <apexchart
              type="bar"
              height="320"
              :options="professionalChartOptions"
              :series="barSeries(report.attentions_by_professional, 'Atenciones')"
            />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Motivos más frecuentes</div></template>
            <apexchart
              type="donut"
              height="320"
              :options="motiveChartOptions"
              :series="donutSeries(report.attentions_by_motive)"
            />
          </BCard>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Confidencialidad</div></template>
            <apexchart
              type="bar"
              height="320"
              :options="confidentialityChartOptions"
              :series="barSeries(report.confidentiality_breakdown, 'Casos')"
            />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="h-100">
            <template #header><div class="fw-semibold">Distribución por área</div></template>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Área</th>
                    <th class="text-end">Atenciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.attentions_by_area || []" :key="item.label">
                    <td>{{ item.label }}</td>
                    <td class="text-end">{{ item.total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="mb-3">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="fw-semibold">Rango del reporte</div>
            <div class="small text-muted">
              {{ report.date_range?.from }} a {{ report.date_range?.to }} · {{ humanizeSupportStatus(report.date_range?.period) }}
            </div>
          </div>
        </template>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="border rounded p-3">
              <div class="small text-muted">Anonimizado</div>
              <div class="fw-semibold">{{ report.date_range?.anonymized ? "Sí" : "No" }}</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3">
              <div class="small text-muted">Cursos visibles</div>
              <div class="fw-semibold">{{ (report.attentions_by_course || []).length }}</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3">
              <div class="small text-muted">Áreas con derivaciones</div>
              <div class="fw-semibold">{{ (report.derivations_by_area || []).length }}</div>
            </div>
          </div>
        </div>
      </BCard>

      <BCard>
        <template #header><div class="fw-semibold">Detalle consolidado</div></template>
        <div v-if="!buildSections().length" class="text-muted">
          No hay registros para el rango y filtros seleccionados.
        </div>
        <div v-else class="d-grid gap-4">
          <div v-for="section in buildSections()" :key="section.title">
            <div class="fw-semibold mb-2">{{ section.title }}</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th v-for="header in section.headers" :key="header">{{ header }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, rowIndex) in section.rows" :key="`${section.title}-${rowIndex}`">
                    <td v-for="(cell, cellIndex) in row" :key="`${section.title}-${rowIndex}-${cellIndex}`">
                      {{ cell }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </BCard>
    </template>
  </div>
</template>
