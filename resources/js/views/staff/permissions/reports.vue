<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import EmptyState from "../../../components/staff/permissions/empty-state.vue";
import MetricCard from "../../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../../components/staff/permissions/status-badge.vue";
import Multiselect from "@vueform/multiselect";
import { getPdfMake } from "../../../utils/pdfmake";

export default {
  components: { Layout, LoadingState, EmptyState, MetricCard, PageHeader, StatusBadge, Multiselect },
  data() {
    return {
      loading: false,
      exporting: false,
      showAdvancedFilters: false,
      error: null,
      catalogs: { staff: [], departments: [], types: [], statuses: [] },
      filters: {
        search: "",
        staff_id: null,
        department_id: null,
        permission_type_id: null,
        status: null,
        with_pay: null,
        requires_replacement: null,
        affects_salary: null,
        late_or_urgent: null,
      },
      rows: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      summary: {},
    };
  },
  computed: {
    staffOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.staff || []).map((item) => ({ value: item.id, label: item.full_name })));
    },
    departmentOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.departments || []).map((item) => ({ value: item.id, label: item.name })));
    },
    typeOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.types || []).map((item) => ({ value: item.id, label: item.name })));
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.statuses || []).map((item) => ({ value: item.value, label: item.label })));
    },
    booleanOptions() {
      return [
        { value: null, label: "Todos" },
        { value: true, label: "Sí" },
        { value: false, label: "No" },
      ];
    },
    summaryCards() {
      return [
        { key: "total", label: "Total", icon: "bx-folder-open", variant: "primary", hint: "Registros filtrados" },
        { key: "con_goce", label: "Con goce", icon: "bx-wallet", variant: "success", hint: "Permisos remunerados" },
        { key: "sin_goce", label: "Sin goce", icon: "bx-wallet-alt", variant: "neutral", hint: "Impacto remuneracional" },
        { key: "pendientes", label: "Pendientes", icon: "bx-time-five", variant: "warning", hint: "En flujo de revisión" },
      ];
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadRows();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/staff/permissions/catalogs");
      this.catalogs = response.data;
    },
    async loadRows(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permissions/reports", {
          params: {
            page,
            ...this.filters,
          },
        });

        this.rows = response.data.data.data || [];
        this.pagination = {
          current_page: response.data.data.current_page,
          last_page: response.data.data.last_page,
          total: response.data.data.total,
        };
        this.summary = response.data.summary || {};
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        staff_id: null,
        department_id: null,
        permission_type_id: null,
        status: null,
        with_pay: null,
        requires_replacement: null,
        affects_salary: null,
        late_or_urgent: null,
      };
      this.loadRows(1);
    },
    serializeRow(item) {
      return {
        funcionario: item.staff?.full_name || "-",
        tipo: item.permission_type?.name || "-",
        estado: item.status,
        inicio: item.start_date || "-",
        termino: item.end_date || "-",
        duracion: item.duration_label || "-",
        con_goce: item.with_pay === null ? "Pendiente" : item.with_pay ? "Sí" : "No",
        reemplazo: item.requires_replacement ? "Sí" : "No",
        afecta_remuneracion: item.affects_salary ? "Sí" : "No",
      };
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).split(" ")[0].split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    paymentBadge(value) {
      if (value === null || value === undefined) {
        return { label: "Pendiente", variant: "warning", icon: "bx-time-five" };
      }

      return value
        ? { label: "Con goce", variant: "success", icon: "bx-wallet" }
        : { label: "Sin goce", variant: "secondary", icon: "bx-wallet-alt" };
    },
    exportCsv() {
      const lines = [
        ["Funcionario", "Tipo", "Estado", "Inicio", "Término", "Duración", "Con goce", "Reemplazo", "Afecta remuneración"].join(","),
        ...this.rows.map((item) => {
          const row = this.serializeRow(item);
          return Object.values(row).map((value) => `"${String(value).replaceAll('"', '""')}"`).join(",");
        }),
      ];
      this.downloadBlob(lines.join("\n"), "text/csv;charset=utf-8", "reporte_permisos.csv");
    },
    exportExcel() {
      const lines = [
        ["Funcionario", "Tipo", "Estado", "Inicio", "Término", "Duración", "Con goce", "Reemplazo", "Afecta remuneración"].join("\t"),
        ...this.rows.map((item) => Object.values(this.serializeRow(item)).join("\t")),
      ];
      this.downloadBlob(lines.join("\n"), "application/vnd.ms-excel", "reporte_permisos.xls");
    },
    exportPdf() {
      const pdfMake = getPdfMake();
      pdfMake
        .createPdf({
          pageOrientation: "landscape",
          content: [
            { text: "Reporte de permisos del personal", style: "title" },
            {
              table: {
                headerRows: 1,
                body: [
                  ["Funcionario", "Tipo", "Estado", "Inicio", "Término", "Duración", "Con goce", "Reemplazo", "Afecta remuneración"],
                  ...this.rows.map((item) => Object.values(this.serializeRow(item))),
                ],
              },
              layout: "lightHorizontalLines",
            },
          ],
          styles: {
            title: { fontSize: 16, bold: true, margin: [0, 0, 0, 10] },
          },
          defaultStyle: { fontSize: 9 },
        })
        .download("reporte_permisos.pdf");
    },
    downloadBlob(content, type, name) {
      const blob = new Blob([content], { type });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = name;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(link.href);
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "No se pudo cargar el reporte.";
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader
      title="Reportes de permisos"
      subtitle="Permisos del año en curso por funcionario, área, tipo, estado y efectos administrativos."
      icon="bx-bar-chart-alt-2"
    >
      <template #actions>
        <div class="d-flex align-items-center gap-2">
          <BButton variant="outline-secondary" :disabled="!rows.length" @click="exportCsv">
            <i class="bx bx-file me-1"></i>CSV
          </BButton>
          <BButton
            class="permission-file-button permission-pdf-button"
            variant="outline-light"
            :disabled="!rows.length"
            aria-label="Descargar reporte PDF"
            title="Descargar reporte PDF"
            @click="exportPdf"
          >
            <i class="mdi mdi-file-pdf-box"></i>
          </BButton>
          <BButton
            class="permission-file-button permission-excel-button"
            variant="outline-light"
            :disabled="!rows.length"
            aria-label="Descargar reporte Excel"
            title="Descargar reporte Excel"
            @click="exportExcel"
          >
            <i class="mdi mdi-file-excel-box"></i>
          </BButton>
        </div>
      </template>
    </PageHeader>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BCard class="permission-card permission-compact-filter mb-3">
      <template #header>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="permission-section-title mb-0">
            <i class="bx bx-filter-alt"></i>
            <span>Filtros</span>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <BButton size="sm" variant="primary" @click="loadRows(1)">
              <i class="bx bx-filter-alt me-1"></i>Filtrar
            </BButton>
            <BButton size="sm" variant="outline-secondary" @click="resetFilters">
              <i class="bx bx-reset me-1"></i>Limpiar
            </BButton>
            <BButton size="sm" variant="outline-primary" @click="showAdvancedFilters = !showAdvancedFilters">
              <i :class="showAdvancedFilters ? 'bx bx-chevron-up me-1' : 'bx bx-chevron-down me-1'"></i>
              {{ showAdvancedFilters ? "Menos" : "Más filtros" }}
            </BButton>
          </div>
        </div>
      </template>
      <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-xl-4">
          <label class="form-label">Búsqueda</label>
          <div class="permission-input-icon">
            <i class="bx bx-search"></i>
            <BFormInput v-model="filters.search" size="sm" placeholder="Funcionario, tipo o motivo" @keyup.enter="loadRows(1)" />
          </div>
        </div>
        <div class="col-lg-2">
          <label class="form-label">Funcionario</label>
          <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.permission_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
      </div>
      <div v-if="showAdvancedFilters" class="row g-2 align-items-end mt-2">
        <div class="col-md-6 col-xl-3">
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
        </div>
        <div class="col-md-6 col-xl-2">
          <label class="form-label">Con goce</label>
          <Multiselect v-model="filters.with_pay" :options="booleanOptions" />
        </div>
        <div class="col-md-6 col-xl-2">
          <label class="form-label">Reemplazo</label>
          <Multiselect v-model="filters.requires_replacement" :options="booleanOptions" />
        </div>
        <div class="col-md-6 col-xl-2">
          <label class="form-label">Remuneración</label>
          <Multiselect v-model="filters.affects_salary" :options="booleanOptions" />
        </div>
        <div class="col-md-6 col-xl-3">
          <label class="form-label">Fuera de plazo / urgencia</label>
          <Multiselect v-model="filters.late_or_urgent" :options="booleanOptions" />
        </div>
      </div>
    </BCard>

    <div class="row g-3 mb-3">
      <div v-for="card in summaryCards" :key="card.key" class="col-md-6 col-xl-3">
        <MetricCard
          :label="card.label"
          :value="summary[card.key] ?? 0"
          :hint="card.hint"
          :icon="card.icon"
          :variant="card.variant"
        />
      </div>
    </div>

    <BCard class="permission-card">
      <template #header>
        <div class="permission-section-title mb-0">
          <i class="bx bx-table"></i>
          <span>Resultado</span>
        </div>
      </template>
      <LoadingState v-if="loading" message="Cargando reporte..." compact />
      <EmptyState
        v-else-if="!rows.length"
        icon="bx-search-alt"
        title="Sin resultados"
        text="No hay permisos para los filtros seleccionados."
      />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle permission-table">
          <thead>
            <tr>
              <th>Funcionario</th>
              <th>Tipo</th>
              <th>Estado</th>
              <th>Periodo</th>
              <th>Duración</th>
              <th>Con goce</th>
              <th>Reemplazo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in rows" :key="item.id">
              <td>
                <div class="fw-semibold">{{ item.staff?.full_name || "-" }}</div>
                <div class="text-muted small">{{ item.reason || "Sin motivo registrado" }}</div>
              </td>
              <td>{{ item.permission_type?.name || "-" }}</td>
              <td><StatusBadge :status="item.status" /></td>
              <td>{{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}</td>
              <td>{{ item.duration_label || "-" }}</td>
              <td>
                <StatusBadge
                  :label="paymentBadge(item.with_pay).label"
                  :variant="paymentBadge(item.with_pay).variant"
                  :icon="paymentBadge(item.with_pay).icon"
                />
              </td>
              <td>
                <StatusBadge
                  :status="item.requires_replacement ? 'activo' : 'inactivo'"
                  :label="item.requires_replacement ? 'Sí' : 'No'"
                  :variant="item.requires_replacement ? 'info' : 'secondary'"
                  :icon="item.requires_replacement ? 'bx-transfer-alt' : 'bx-minus'"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.total" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <div class="text-muted small">Total: {{ pagination.total }}</div>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="15"
          pills
          @update:model-value="loadRows"
        />
      </div>
    </BCard>
  </Layout>
</template>
