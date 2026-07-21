<script>
import axios from "axios";
import InformaticaHelpButton from "../help-button.vue";
import InformaticaStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  downloadExcelWorkbook,
  downloadPdfReport,
  formatInformaticaDate,
  formatInformaticaDateTime,
  formatInformaticaError,
  humanizeInformaticaStatus,
  normalizeOptions,
  printInformaticaHtml,
  showInformaticaSuccess,
} from "../module-utils";

export default {
  components: {
    InformaticaHelpButton,
    InformaticaStatusBadge,
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
        summary: {},
        sections: {},
        detail: {},
      },
      filters: {
        period: "monthly",
        date_from: "",
        date_to: "",
        it_equipment_id: null,
        equipment_type: null,
        status: null,
      },
    };
  },
  computed: {
    periodOptions() {
      const labels = { daily: "Hoy", weekly: "Esta semana", monthly: "Este mes", semestral: "Últimos 6 meses", annual: "Este año" };
      return normalizeOptions(this.catalogs.report_periods || []).map((item) => ({ ...item, label: labels[item.value] || item.label }));
    },
    equipmentOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.equipment || []).map((item) => ({
          value: item.id,
          label: `${item.internal_code} · ${[item.brand, item.model].filter(Boolean).join(" ")}`,
        }))
      );
    },
    summaryCards() {
      const summary = this.report.summary || {};
      return [
        { label: "Equipos considerados", value: summary.total_equipment || 0, icon: "bx-laptop", tone: "primary" },
        { label: "Préstamos activos", value: summary.active_loans || 0, icon: "bx-transfer-alt", tone: "info" },
        { label: "Préstamos atrasados", value: summary.overdue_loans || 0, icon: "bx-time-five", tone: "danger" },
        { label: "Mantenciones cerradas", value: summary.closed_maintenance || 0, icon: "bx-check-circle", tone: "success" },
        { label: "Mantenciones pendientes", value: summary.pending_maintenance || 0, icon: "bx-wrench", tone: "warning" },
      ];
    },
    exportRows() {
      const sections = this.report.sections || {};
      return [
        {
          title: "Equipos por estado",
          headers: ["Estado", "Total"],
          rows: (sections.equipment_by_status || []).map((item) => [humanizeInformaticaStatus(item.label), item.total]),
        },
        {
          title: "Equipos por tipo",
          headers: ["Tipo", "Total"],
          rows: (sections.equipment_by_type || []).map((item) => [humanizeInformaticaStatus(item.label), item.total]),
        },
        {
          title: "Préstamos por equipo",
          headers: ["Equipo", "Total"],
          rows: (sections.loans_by_equipment || []).map((item) => [item.label, item.total]),
        },
        {
          title: "Mantenciones por equipo",
          headers: ["Equipo", "Total"],
          rows: (sections.maintenance_by_equipment || []).map((item) => [item.label, item.total]),
        },
        {
          title: "Mantenciones por mes",
          headers: ["Mes", "Total"],
          rows: (sections.maintenance_by_month || []).map((item) => [item.label, item.total]),
        },
        {
          title: "Equipos con más mantenciones",
          headers: ["Equipo", "Total"],
          rows: (sections.top_maintenance_equipment || []).map((item) => [item.label, item.total]),
        },
        {
          title: "Equipos con más préstamos",
          headers: ["Equipo", "Total"],
          rows: (sections.top_loaned_equipment || []).map((item) => [item.label, item.total]),
        },
      ];
    },
    chartCards() {
      const sections = this.report.sections || {};
      return [
        { title: "Estado del parque tecnológico", subtitle: "Distribución actual de los equipos", type: "donut", items: sections.equipment_by_status || [], colors: ["#34c38f", "#556ee6", "#f1b44c", "#f46a6a", "#74788d"] },
        { title: "Equipos por tipo", subtitle: "Composición del inventario", type: "bar", items: sections.equipment_by_type || [], color: "#556ee6" },
        { title: "Equipos con más préstamos", subtitle: "Mayor rotación durante el periodo", type: "bar", items: sections.top_loaned_equipment || [], color: "#50a5f1" },
        { title: "Equipos con más mantenciones", subtitle: "Mayor frecuencia de intervención", type: "bar", items: sections.top_maintenance_equipment || [], color: "#f1b44c" },
      ];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatInformaticaDate,
    formatInformaticaDateTime,
    humanizeInformaticaStatus,
    normalizeOptions,
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/informatica/reportes", { params: this.filters });
        this.report = response.data;
      } catch (error) {
        this.error = formatInformaticaError(error, "No se pudieron generar los reportes de informática.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        period: "monthly",
        date_from: "",
        date_to: "",
        it_equipment_id: null,
        equipment_type: null,
        status: null,
      };
      this.load();
    },
    async exportExcel() {
      downloadExcelWorkbook("informatica-reportes", this.exportRows);
      await showInformaticaSuccess("Reporte exportado a Excel.");
    },
    async exportPdf() {
      downloadPdfReport(
        "informatica-reportes",
        "Reportes de Informática",
        "Consolidado de equipos, préstamos y mantenciones",
        this.exportRows
      );
      await showInformaticaSuccess("Reporte exportado a PDF.");
    },
    printReport() {
      const html = this.exportRows
        .map(
          (section) => `
            <h3>${section.title}</h3>
            <table>
              <thead><tr>${section.headers.map((header) => `<th>${header}</th>`).join("")}</tr></thead>
              <tbody>${section.rows.map((row) => `<tr>${row.map((value) => `<td>${value ?? ""}</td>`).join("")}</tr>`).join("")}</tbody>
            </table>
          `
        )
        .join("");

      printInformaticaHtml("Reportes de Informática", html);
    },
    chartOptions(card) {
      const labels = card.items.map((item) => humanizeInformaticaStatus(item.label));
      if (card.type === "donut") {
        return {
          chart: { fontFamily: "inherit" }, labels, colors: card.colors,
          legend: { position: "bottom", fontSize: "12px" },
          dataLabels: { enabled: true, formatter: (value) => `${Math.round(value)}%` },
          stroke: { width: 3, colors: ["var(--bs-card-bg, #fff)"] },
          plotOptions: { pie: { donut: { size: "68%", labels: { show: true, total: { show: true, label: "Equipos", formatter: () => card.items.reduce((sum, item) => sum + Number(item.total || 0), 0) } } } } },
          responsive: [{ breakpoint: 576, options: { legend: { position: "bottom" } } }],
        };
      }
      return {
        chart: { toolbar: { show: false }, fontFamily: "inherit" },
        colors: [card.color], xaxis: { categories: labels, labels: { trim: true } },
        dataLabels: { enabled: false }, grid: { borderColor: "#edf0f6", strokeDashArray: 4 },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: "55%" } },
        tooltip: { y: { formatter: (value) => `${value} registro${value === 1 ? "" : "s"}` } },
      };
    },
    chartSeries(card) {
      const values = card.items.map((item) => Number(item.total || 0));
      return card.type === "donut" ? values : [{ name: "Total", data: values }];
    },
  },
};
</script>

<template>
  <div class="reports-view d-flex flex-column gap-4">
    <div class="reports-toolbar">
      <div><div class="fw-semibold fs-5">Análisis operativo</div><div class="small text-muted">Indicadores del inventario y su actividad</div></div>
      <div class="reports-toolbar__actions">
        <InformaticaHelpButton
          title="Ayuda: reportes de informática"
          text="Esta sección resume equipos por estado y tipo, préstamos activos y atrasados, mantenciones y equipos con mayor uso o intervención."
        />
        <BButton variant="outline-success" @click="exportExcel"><i class="bx bx-spreadsheet me-1"></i>Excel</BButton>
        <BButton variant="outline-danger" @click="exportPdf"><i class="bx bxs-file-pdf me-1"></i>PDF</BButton>
        <BButton variant="outline-secondary" @click="printReport"><i class="bx bx-printer me-1"></i>Imprimir</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm reports-filter-card">
      <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><div class="fw-semibold">Filtros del informe</div><div class="small text-muted">Ajusta el periodo o limita los resultados a un equipo específico</div></div><i class="bx bx-filter-alt fs-4 text-primary"></i></div>
      <div class="reports-filter-grid">
        <div class="reports-filter-field reports-filter-field--period">
          <label class="form-label">Periodo</label>
          <BFormSelect v-model="filters.period" :options="periodOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="reports-filter-field reports-filter-field--equipment">
          <label class="form-label">Equipo</label>
          <BFormSelect v-model="filters.it_equipment_id" :options="equipmentOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="reports-filter-field">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.equipment_type" :options="normalizeOptions(catalogs.equipment_types || [], true).map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="reports-filter-field">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="normalizeOptions(catalogs.equipment_statuses || [], true).map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="reports-filter-field reports-filter-field--date">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.date_from" type="date" />
        </div>
        <div class="reports-filter-field reports-filter-field--date">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.date_to" type="date" />
        </div>
        <div class="reports-filter-actions">
          <BButton variant="primary" :disabled="loading" @click="load"><i class="bx bx-line-chart me-1"></i>{{ loading ? "Generando" : "Generar" }}</BButton>
          <BButton variant="light" :disabled="loading" @click="resetFilters"><i class="bx bx-reset me-1"></i>Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Generando reportes de informática..." compact />
    </BCard>

    <template v-else>
      <div class="row g-3">
        <div v-for="card in summaryCards" :key="card.label" class="col-sm-6 col-lg col-xxl">
          <BCard class="border-0 shadow-sm h-100 report-metric" :class="`report-metric--${card.tone}`">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div><div class="small text-muted">{{ card.label }}</div><div class="display-6 fw-semibold mt-1">{{ card.value }}</div></div>
              <span class="report-metric__icon"><i :class="`bx ${card.icon}`"></i></span>
            </div>
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div v-for="card in chartCards" :key="card.title" class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100 report-chart-card">
            <template #header><div><div class="fw-semibold">{{ card.title }}</div><div class="small text-muted mt-1">{{ card.subtitle }}</div></div></template>
            <div v-if="card.items.length" class="report-chart-wrap">
              <apexchart :type="card.type" height="310" :options="chartOptions(card)" :series="chartSeries(card)" />
            </div>
            <div v-else class="report-empty"><i class="bx bx-bar-chart-alt-2"></i><strong>Sin datos para graficar</strong><span>Prueba seleccionando otro periodo o limpiando los filtros.</span></div>
          </BCard>
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <template #header><div><div class="fw-semibold">Mantenciones por mes</div><div class="small text-muted mt-1">Evolución de intervenciones técnicas internas</div></div></template>
        <BTable small responsive show-empty empty-text="No hay mantenciones en el periodo seleccionado." :items="report.sections?.maintenance_by_month || []" :fields="[{ key: 'label', label: 'Mes' }, { key: 'total', label: 'Mantenciones' }]" />
      </BCard>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Préstamos activos</div></template>
            <BTable
              small
              responsive
              show-empty
              empty-text="No hay préstamos activos."
              :items="report.detail?.active_loans || []"
              :fields="[
                { key: 'loan_code', label: 'Código' },
                { key: 'equipment', label: 'Equipo' },
                { key: 'requester_name_snapshot', label: 'Solicitante' },
                { key: 'due_at', label: 'Compromiso' },
                { key: 'status', label: 'Estado' },
              ]"
            >
              <template #cell(equipment)="{ item }">{{ item.equipment?.internal_code || "-" }}</template>
              <template #cell(due_at)="{ item }">{{ formatInformaticaDateTime(item.due_at) }}</template>
              <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
            </BTable>
          </BCard>
        </div>
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Préstamos atrasados</div></template>
            <BTable
              small
              responsive
              show-empty
              empty-text="No hay préstamos atrasados."
              :items="report.detail?.overdue_loans || []"
              :fields="[
                { key: 'loan_code', label: 'Código' },
                { key: 'equipment', label: 'Equipo' },
                { key: 'requester_name_snapshot', label: 'Solicitante' },
                { key: 'due_at', label: 'Compromiso' },
                { key: 'status', label: 'Estado' },
              ]"
            >
              <template #cell(equipment)="{ item }">{{ item.equipment?.internal_code || "-" }}</template>
              <template #cell(due_at)="{ item }">{{ formatInformaticaDateTime(item.due_at) }}</template>
              <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
            </BTable>
          </BCard>
        </div>
      </div>

      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2">
            <div class="fw-semibold">Equipos dados de baja</div>
            <InformaticaHelpButton
              title="Ayuda: equipos dados de baja"
              text="Este listado permite revisar los equipos fuera de operación, su ubicación y el responsable habitual registrado."
            />
          </div>
        </template>
        <BTable
          small
          responsive
          show-empty
          empty-text="No hay equipos dados de baja."
          :items="report.sections?.decommissioned_equipment || []"
          :fields="[
            { key: 'internal_code', label: 'Código' },
            { key: 'equipment_type', label: 'Tipo' },
            { key: 'brand', label: 'Marca' },
            { key: 'model', label: 'Modelo' },
            { key: 'location_name', label: 'Ubicación' },
            { key: 'responsible_name', label: 'Responsable' },
            { key: 'updated_at', label: 'Últ. cambio' },
          ]"
        >
          <template #cell(equipment_type)="{ item }">{{ humanizeInformaticaStatus(item.equipment_type) }}</template>
          <template #cell(updated_at)="{ item }">{{ formatInformaticaDateTime(item.updated_at) }}</template>
        </BTable>
      </BCard>
    </template>
  </div>
</template>

<style scoped>
.reports-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.reports-toolbar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: .55rem; }
.reports-filter-card :deep(.card-body) { padding: 1.25rem; }
.reports-filter-grid { display: grid; grid-template-columns: minmax(130px,.8fr) minmax(220px,1.5fr) repeat(2,minmax(150px,1fr)) repeat(2,minmax(145px,.9fr)); gap: 1rem; align-items: end; }
.reports-filter-field { min-width: 0; }
.reports-filter-actions { display: flex; justify-content: flex-end; gap: .6rem; grid-column: 1 / -1; padding-top: .1rem; border-top: 1px solid #edf0f6; padding-top: 1rem; }
.report-metric { position: relative; overflow: hidden; min-height: 128px; --metric-color: #556ee6; }
.report-metric::after { content: ""; position: absolute; inset: auto 0 0; height: 3px; background: var(--metric-color); }
.report-metric--info { --metric-color: #50a5f1; }.report-metric--danger { --metric-color: #f46a6a; }.report-metric--success { --metric-color: #34c38f; }.report-metric--warning { --metric-color: #f1b44c; }
.report-metric__icon { display: grid; flex: 0 0 40px; height: 40px; place-items: center; color: var(--metric-color); border-radius: 11px; background: color-mix(in srgb, var(--metric-color) 12%, transparent); font-size: 1.25rem; }
.report-chart-card :deep(.card-body) { min-height: 330px; }
.report-chart-wrap { overflow: hidden; }
.report-empty { display: flex; min-height: 280px; flex-direction: column; align-items: center; justify-content: center; color: #8a92a3; text-align: center; }
.report-empty i { margin-bottom: .7rem; color: #b9c0ce; font-size: 2.8rem; }.report-empty strong { color: #596274; }.report-empty span { max-width: 300px; margin-top: .25rem; font-size: .78rem; }
@media (max-width: 1399.98px) { .reports-filter-grid { grid-template-columns: repeat(4, minmax(0,1fr)); }.reports-filter-field--equipment { grid-column: span 2; } }
@media (max-width: 991.98px) { .reports-filter-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }.reports-filter-field--equipment { grid-column: span 1; } }
@media (max-width: 575.98px) { .reports-toolbar { align-items: flex-start; flex-direction: column; }.reports-toolbar__actions { width: 100%; }.reports-toolbar__actions :deep(.btn:not(.informatica-help-button)) { flex: 1; }.reports-filter-grid { grid-template-columns: 1fr; }.reports-filter-actions { flex-direction: column; }.reports-filter-actions :deep(.btn) { width: 100%; }.report-metric { min-height: 110px; } }
</style>
