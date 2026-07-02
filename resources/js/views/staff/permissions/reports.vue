<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import { getPdfMake } from "../../../utils/pdfmake";

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      exporting: false,
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
        month: null,
        year: new Date().getFullYear(),
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
        month: null,
        year: new Date().getFullYear(),
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
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Reportes de permisos</h4>
        <div class="text-muted">Filtros por funcionario, área, tipo, estado y efectos administrativos.</div>
      </div>
      <div class="btn-group">
        <BButton variant="outline-secondary" @click="exportCsv">CSV</BButton>
        <BButton variant="outline-secondary" @click="exportExcel">Excel</BButton>
        <BButton variant="outline-primary" @click="exportPdf">PDF</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-lg-3">
          <label class="form-label">Búsqueda</label>
          <BFormInput v-model="filters.search" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Funcionario</label>
          <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.permission_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Con goce</label>
          <Multiselect v-model="filters.with_pay" :options="booleanOptions" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Requiere reemplazo</label>
          <Multiselect v-model="filters.requires_replacement" :options="booleanOptions" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Afecta remuneración</label>
          <Multiselect v-model="filters.affects_salary" :options="booleanOptions" />
        </div>
        <div class="col-lg-1">
          <label class="form-label">Mes</label>
          <BFormInput v-model="filters.month" type="number" min="1" max="12" />
        </div>
        <div class="col-lg-1">
          <label class="form-label">Año</label>
          <BFormInput v-model="filters.year" type="number" min="2024" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Fuera de plazo / urgencia</label>
          <Multiselect v-model="filters.late_or_urgent" :options="booleanOptions" />
        </div>
      </div>
      <div class="d-flex gap-2 mt-3">
        <BButton variant="primary" @click="loadRows(1)">Filtrar</BButton>
        <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
      </div>
    </BCard>

    <div class="row g-3 mb-3">
      <div class="col-md-3" v-for="(label, key) in {
        total: 'Total',
        con_goce: 'Con goce',
        sin_goce: 'Sin goce',
        pendientes: 'Pendientes'
      }" :key="key">
        <BCard>
          <div class="text-muted small">{{ label }}</div>
          <div class="h2 mb-0">{{ summary[key] ?? 0 }}</div>
        </BCard>
      </div>
    </div>

    <BCard title="Resultado">
      <LoadingState v-if="loading" message="Cargando reporte..." compact />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
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
              <td>{{ item.staff?.full_name || "-" }}</td>
              <td>{{ item.permission_type?.name || "-" }}</td>
              <td>{{ item.status }}</td>
              <td>{{ item.start_date }} - {{ item.end_date }}</td>
              <td>{{ item.duration_label || "-" }}</td>
              <td>{{ item.with_pay === null ? "Pendiente" : item.with_pay ? "Sí" : "No" }}</td>
              <td>{{ item.requires_replacement ? "Sí" : "No" }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>
  </Layout>
</template>
