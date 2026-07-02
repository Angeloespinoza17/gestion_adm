<script>
import axios from "axios";
import InformaticaHelpButton from "../help-button.vue";
import InformaticaStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  downloadExcelWorkbook,
  downloadPdfReport,
  formatCurrency,
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
      return normalizeOptions(this.catalogs.report_periods || []);
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
        { label: "Equipos considerados", value: summary.total_equipment || 0 },
        { label: "Préstamos activos", value: summary.active_loans || 0 },
        { label: "Préstamos atrasados", value: summary.overdue_loans || 0 },
        { label: "Mantenciones cerradas", value: summary.closed_maintenance || 0 },
        { label: "Mantenciones pendientes", value: summary.pending_maintenance || 0 },
        { label: "Costo de mantención", value: formatCurrency(summary.maintenance_cost_total || 0) },
      ];
    },
    sectionRows() {
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
          headers: ["Mes", "Total", "Costo"],
          rows: (sections.maintenance_by_month || []).map((item) => [item.label, item.total, formatCurrency(item.cost)]),
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
  },
  mounted() {
    this.load();
  },
  methods: {
    formatCurrency,
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
      downloadExcelWorkbook("informatica-reportes", this.sectionRows);
      await showInformaticaSuccess("Reporte exportado a Excel.");
    },
    async exportPdf() {
      downloadPdfReport(
        "informatica-reportes",
        "Reportes de Informática",
        "Consolidado de equipos, préstamos y mantenciones",
        this.sectionRows
      );
      await showInformaticaSuccess("Reporte exportado a PDF.");
    },
    printReport() {
      const html = this.sectionRows
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
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Consolidado estadístico y exportable</div>
      <div class="d-flex gap-2 flex-wrap">
        <InformaticaHelpButton
          title="Ayuda: reportes de informática"
          text="Esta sección resume equipos por estado y tipo, préstamos activos y atrasados, mantenciones, costos y equipos con mayor uso o intervención."
        />
        <BButton variant="outline-success" @click="exportExcel">Excel</BButton>
        <BButton variant="outline-danger" @click="exportPdf">PDF</BButton>
        <BButton variant="outline-secondary" @click="printReport">Imprimir</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label">Periodo</label>
          <BFormSelect v-model="filters.period" :options="periodOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Equipo</label>
          <BFormSelect v-model="filters.it_equipment_id" :options="equipmentOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.equipment_type" :options="normalizeOptions(catalogs.equipment_types || [], true).map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="normalizeOptions(catalogs.equipment_statuses || [], true).map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-1">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.date_from" type="date" />
        </div>
        <div class="col-md-1">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.date_to" type="date" />
        </div>
        <div class="col-md-1 d-flex gap-2">
          <BButton variant="primary" @click="load">Generar</BButton>
          <BButton variant="light" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Generando reportes de informática..." compact />
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
            <BTable
              small
              responsive
              :items="section.rows.map((row) => Object.fromEntries(row.map((value, index) => [`col_${index}`, value])))"
              :fields="section.headers.map((header, index) => ({ key: `col_${index}`, label: header }))"
            />
          </BCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <BCard class="border-0 shadow-sm h-100">
            <template #header><div class="fw-semibold">Préstamos activos</div></template>
            <BTable
              small
              responsive
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
