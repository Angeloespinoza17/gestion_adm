<script>
import axios from "axios";
import CentroApuntesHelpButton from "../help-button.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  basicApexOptions,
  downloadExcelWorkbook,
  downloadPdfReport,
  extractChartLabels,
  extractChartTotals,
  formatCentroApuntesError,
  normalizeOptions,
  printCentroApuntesHtml,
} from "../module-utils";

export default {
  components: {
    CentroApuntesHelpButton,
    LoadingState,
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
      report: {
        range: {},
        summary: {},
        charts: {
          requests_by_status: [],
          requests_by_machine: [],
          requests_by_subject: [],
          deliveries_by_area: [],
          supplies_by_category: [],
        },
        sections: [],
      },
      filters: {
        period: "mensual",
        start_date: "",
        end_date: "",
        requested_by_user_id: null,
        subject_id: null,
        machine_id: null,
        paper_size: null,
        task_type: null,
        status: null,
        supply_id: null,
        category: null,
        department_id: null,
        urgent_only: false,
        immediate_only: false,
      },
    };
  },
  computed: {
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export_reports);
    },
    periodOptions() {
      return normalizeOptions(this.catalogs.report_periods || []);
    },
    userOptions() {
      return normalizeOptions(this.catalogs.users || []);
    },
    subjectOptions() {
      return normalizeOptions(this.catalogs.subjects || []);
    },
    machineOptions() {
      return normalizeOptions(this.catalogs.machines || []);
    },
    paperSizeOptions() {
      return normalizeOptions(this.catalogs.paper_sizes || []);
    },
    taskTypeOptions() {
      return normalizeOptions(this.catalogs.task_types || []);
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.request_statuses || []);
    },
    supplyOptions() {
      return normalizeOptions(this.catalogs.supplies || []);
    },
    categoryOptions() {
      return normalizeOptions(this.catalogs.supply_categories || []);
    },
    departmentOptions() {
      return normalizeOptions(this.catalogs.departments || []);
    },
    statusChartOptions() {
      return {
        labels: extractChartLabels(this.report.charts?.requests_by_status),
        legend: { position: "bottom" },
        colors: ["#2f7cf6", "#34c38f", "#f1b44c", "#f46a6a", "#74788d", "#50a5f1"],
      };
    },
    machineChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.report.charts?.requests_by_machine),
        colors: ["#2f7cf6"],
        horizontal: true,
      });
    },
    subjectChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.report.charts?.requests_by_subject),
        colors: ["#34c38f"],
        horizontal: true,
      });
    },
    deliveryChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.report.charts?.deliveries_by_area),
        colors: ["#f1b44c"],
        horizontal: true,
      });
    },
    supplyChartOptions() {
      return basicApexOptions({
        categories: extractChartLabels(this.report.charts?.supplies_by_category),
        colors: ["#f46a6a"],
        horizontal: true,
      });
    },
  },
  mounted() {
    this.loadReport();
  },
  methods: {
    extractChartTotals,
    async loadReport() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/reportes", {
          params: {
            ...this.filters,
            urgent_only: this.filters.urgent_only ? 1 : "",
            immediate_only: this.filters.immediate_only ? 1 : "",
          },
        });
        this.report = response.data;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el reporte del módulo.");
      } finally {
        this.loading = false;
      }
    },
    clearFilters() {
      this.filters = {
        period: "mensual",
        start_date: "",
        end_date: "",
        requested_by_user_id: null,
        subject_id: null,
        machine_id: null,
        paper_size: null,
        task_type: null,
        status: null,
        supply_id: null,
        category: null,
        department_id: null,
        urgent_only: false,
        immediate_only: false,
      };
      this.loadReport();
    },
    exportExcel() {
      if (!this.canExport) return;
      downloadExcelWorkbook(
        `reporte-centro-apuntes-${this.report.range?.start || "periodo"}`,
        this.report.sections || []
      );
    },
    exportPdf() {
      if (!this.canExport) return;
      downloadPdfReport(
        `reporte-centro-apuntes-${this.report.range?.start || "periodo"}`,
        "Reporte Centro de Apuntes y Pañol",
        `Período ${this.report.range?.start || "-"} a ${this.report.range?.end || "-"}`,
        this.report.sections || []
      );
    },
    printReport() {
      if (!this.canExport) return;
      const html = (this.report.sections || [])
        .map((section) => `
          <h2>${this.escapeHtml(section.title)}</h2>
          <table>
            <thead><tr>${(section.headers || []).map((header) => `<th>${this.escapeHtml(header)}</th>`).join("")}</tr></thead>
            <tbody>${(section.rows || []).map((row) => `<tr>${row.map((cell) => `<td>${this.escapeHtml(cell)}</td>`).join("")}</tr>`).join("")}</tbody>
          </table>
        `)
        .join("");

      printCentroApuntesHtml("Reporte Centro de Apuntes y Pañol", html);
    },
    escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },
    series(items, name = "Total") {
      return [{ name, data: extractChartTotals(items) }];
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Reportes operativos y costeo estimado</div>
      <div class="d-flex gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: reportes y costeo"
          text="Aquí se consolidan solicitudes, movimientos y entregas por período, con exportación a Excel, PDF e impresión para seguimiento y toma de decisiones."
        />
        <BButton v-if="canExport" variant="outline-success" :disabled="loading" @click="exportExcel"><i class="bx bx-spreadsheet me-1"></i>Excel</BButton>
        <BButton v-if="canExport" variant="outline-danger" :disabled="loading" @click="exportPdf"><i class="bx bxs-file-pdf me-1"></i>PDF</BButton>
        <BButton v-if="canExport" variant="outline-dark" :disabled="loading" @click="printReport"><i class="bx bx-printer me-1"></i>Imprimir</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label">Período</label>
          <BFormSelect v-model="filters.period" :options="periodOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.start_date" type="date" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.end_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Solicitante</label>
          <BFormSelect v-model="filters.requested_by_user_id" :options="[{ value: null, text: 'Todos' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Asignatura</label>
          <BFormSelect v-model="filters.subject_id" :options="[{ value: null, text: 'Todas' }].concat(subjectOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Máquina</label>
          <BFormSelect v-model="filters.machine_id" :options="[{ value: null, text: 'Todas' }].concat(machineOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tamaño papel</label>
          <BFormSelect v-model="filters.paper_size" :options="[{ value: null, text: 'Todos' }].concat(paperSizeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo tarea</label>
          <BFormSelect v-model="filters.task_type" :options="[{ value: null, text: 'Todos' }].concat(taskTypeOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(statusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Insumo</label>
          <BFormSelect v-model="filters.supply_id" :options="[{ value: null, text: 'Todos' }].concat(supplyOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Categoría insumo</label>
          <BFormSelect v-model="filters.category" :options="[{ value: null, text: 'Todas' }].concat(categoryOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Área</label>
          <BFormSelect v-model="filters.department_id" :options="[{ value: null, text: 'Todas' }].concat(departmentOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <BFormCheckbox v-model="filters.urgent_only">Solo urgentes</BFormCheckbox>
          <BFormCheckbox v-model="filters.immediate_only">Solo inmediatas</BFormCheckbox>
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="loadReport">Generar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Generando reporte..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div class="col-md-6 col-xl-3">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Solicitudes</div>
            <div class="display-6 fw-semibold">{{ report.summary?.requests_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Urgentes</div>
            <div class="display-6 fw-semibold">{{ report.summary?.urgent_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Entregadas</div>
            <div class="display-6 fw-semibold">{{ report.summary?.delivered_total || 0 }}</div>
          </BCard>
        </div>
        <div class="col-md-6 col-xl-3">
          <BCard class="border-0 shadow-sm h-100">
            <div class="text-muted small">Costo estimado</div>
            <div class="display-6 fw-semibold">${{ Number(report.summary?.estimated_cost_total || 0).toLocaleString("es-CL") }}</div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes por estado</div></template>
            <apexchart type="donut" height="300" :options="statusChartOptions" :series="extractChartTotals(report.charts?.requests_by_status)" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes por máquina</div></template>
            <apexchart type="bar" height="300" :options="machineChartOptions" :series="series(report.charts?.requests_by_machine, 'Solicitudes')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Solicitudes por asignatura</div></template>
            <apexchart type="bar" height="300" :options="subjectChartOptions" :series="series(report.charts?.requests_by_subject, 'Solicitudes')" />
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Entregas por área</div></template>
            <apexchart type="bar" height="300" :options="deliveryChartOptions" :series="series(report.charts?.deliveries_by_area, 'Entregas')" />
          </BCard>
        </div>
        <div class="col-xl-12">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Consumo por categoría de insumo</div></template>
            <apexchart type="bar" height="300" :options="supplyChartOptions" :series="series(report.charts?.supplies_by_category, 'Cantidad')" />
          </BCard>
        </div>
      </div>

      <BCard v-for="section in report.sections || []" :key="section.title" class="border-0 shadow-sm">
        <template #header>
          <div class="fw-semibold">{{ section.title }}</div>
        </template>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th v-for="header in section.headers" :key="header">{{ header }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in section.rows.slice(0, 12)" :key="`${section.title}-${index}`">
                <td v-for="(cell, cellIndex) in row" :key="`${section.title}-${index}-${cellIndex}`">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCard>
    </template>
  </div>
</template>
