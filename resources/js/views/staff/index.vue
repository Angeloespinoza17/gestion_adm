<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

const exportableColumns = [
  { value: "full_name", label: "Nombre completo" },
  { value: "rut", label: "RUT" },
  { value: "institutional_email", label: "Correo institucional" },
  { value: "personal_email", label: "Correo personal" },
  { value: "phone", label: "Teléfono" },
  { value: "cargo", label: "Cargo" },
  { value: "departments", label: "Departamentos" },
  { value: "status", label: "Estado" },
  { value: "active", label: "Registro activo" },
  { value: "contract_type", label: "Tipo de contrato" },
  { value: "workday", label: "Jornada" },
  { value: "contract_hours", label: "Horas contrato" },
  { value: "start_date", label: "Fecha ingreso" },
  { value: "end_date", label: "Fecha término" },
  { value: "region", label: "Región" },
  { value: "commune", label: "Comuna" },
  { value: "associated_user", label: "Usuario asociado" },
  { value: "professional_title", label: "Título profesional" },
  { value: "specialty", label: "Especialidad" },
  { value: "created_at", label: "Creado" },
  { value: "updated_at", label: "Actualizado" },
];

const exportFormats = [
  { value: "excel", text: "Planilla Excel (.xls)" },
  { value: "csv", text: "Archivo CSV (.csv)" },
  { value: "pdf", text: "Documento PDF (.pdf)" },
];

export default {
  components: { Layout, Multiselect },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      showExportModal: false,
      catalogs: {
        cargos: [],
        departments: [],
        statuses: [],
        contract_types: [],
      },
      filters: {
        search: "",
        name: "",
        rut: "",
        cargo_id: null,
        department_id: null,
        status: null,
        contract_type: null,
      },
      exportForm: {
        format: "excel",
        columns: ["full_name", "rut"],
      },
      staff: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
    };
  },
  computed: {
    cargoOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.cargos || []).map((cargo) => ({
          value: cargo.id,
          label: cargo.name,
        }))
      );
    },
    departmentOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.departments || []).map((department) => ({
          value: department.id,
          label: department.name,
        }))
      );
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((status) => ({
          value: status.value,
          label: status.label,
        }))
      );
    },
    contractTypeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.contract_types || []).map((type) => ({
          value: type.value,
          label: type.label,
        }))
      );
    },
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canEdit() {
      return this.permissions.includes("gestionar_funcionarios");
    },
    canDelete() {
      return this.permissions.includes("eliminar_funcionarios");
    },
    canExport() {
      return (
        this.permissions.includes("exportar_funcionarios") ||
        this.permissions.includes("gestionar_funcionarios") ||
        this.permissions.includes("ver_funcionarios")
      );
    },
    activeFilterCount() {
      return [
        this.filters.search,
        this.filters.name,
        this.filters.rut,
        this.filters.cargo_id,
        this.filters.department_id,
        this.filters.status,
        this.filters.contract_type,
      ].filter((value) => value !== null && value !== "").length;
    },
    currentActiveCount() {
      return (this.staff || []).filter((item) => item.active).length;
    },
    exportColumnOptions() {
      return exportableColumns;
    },
    exportFormatOptions() {
      return exportFormats;
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadStaff();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/staff/catalogs");
      this.catalogs = response.data;
    },
    async loadStaff(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff", {
          params: {
            page,
            search: this.filters.search || null,
            name: this.filters.name || null,
            rut: this.filters.rut || null,
            cargo_id: this.filters.cargo_id,
            department_id: this.filters.department_id,
            status: this.filters.status,
            contract_type: this.filters.contract_type,
          },
        });

        this.staff = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        name: "",
        rut: "",
        cargo_id: null,
        department_id: null,
        status: null,
        contract_type: null,
      };
      this.loadStaff(1);
    },
    async toggleActive(item) {
      const result = await this.confirmAction({
        title: item.active ? "Desactivar funcionario" : "Activar funcionario",
        text: `${item.full_name} cambiará su estado de registro.`,
        confirmButtonText: item.active ? "Sí, desactivar" : "Sí, activar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.put(`/api/staff/${item.id}/active`, { active: !item.active });
        this.showSuccessAlert(
          item.active ? "Funcionario desactivado" : "Funcionario activado",
          "El estado fue actualizado correctamente."
        );
        this.loadStaff(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      }
    },
    async remove(item) {
      const result = await this.confirmAction({
        title: "Eliminar funcionario",
        text: `Se eliminará a ${item.full_name}.`,
        confirmButtonText: "Sí, eliminar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/staff/${item.id}`);
        this.showSuccessAlert("Funcionario eliminado", "El funcionario fue eliminado correctamente.");
        this.loadStaff(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      }
    },
    openExportModal() {
      this.exportForm = {
        format: "excel",
        columns: ["full_name", "rut"],
      };
      this.showExportModal = true;
    },
    async exportList() {
      if (!this.exportForm.columns.length) {
        this.showErrorAlert("Selecciona al menos una columna para exportar.");
        return;
      }

      this.exporting = true;
      this.error = null;

      try {
        const all = [];
        let page = 1;
        let lastPage = 1;

        do {
          const response = await axios.get("/api/staff", {
            params: {
              page,
              per_page: 200,
              search: this.filters.search || null,
              name: this.filters.name || null,
              rut: this.filters.rut || null,
              cargo_id: this.filters.cargo_id,
              department_id: this.filters.department_id,
              status: this.filters.status,
              contract_type: this.filters.contract_type,
            },
          });

          all.push(...(response.data.data || []));
          lastPage = response.data.last_page || 1;
          page += 1;
        } while (page <= lastPage);

        const selectedColumns = exportableColumns.filter((column) =>
          this.exportForm.columns.includes(column.value)
        );

        const rows = all.map((item) => {
          const row = {};

          selectedColumns.forEach((column) => {
            row[column.label] = this.exportCellValue(item, column.value);
          });

          return row;
        });

        const selectedFormat = this.resolveExportFormat();

        if (selectedFormat === "csv") {
          this.downloadCsv(rows, selectedColumns);
        } else if (selectedFormat === "pdf") {
          this.downloadPdf(rows, selectedColumns);
        } else {
          this.downloadExcel(rows, selectedColumns);
        }

        this.showExportModal = false;
        this.showSuccessAlert("Listado exportado", "El archivo se generó correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.exporting = false;
      }
    },
    resolveExportFormat() {
      const format = this.exportForm?.format;

      if (typeof format === "string") {
        return format;
      }

      if (format && typeof format === "object") {
        return format.value || "";
      }

      return "excel";
    },
    exportCellValue(item, key) {
      switch (key) {
        case "full_name":
          return item.full_name || "";
        case "rut":
          return item.rut || "";
        case "institutional_email":
          return item.institutional_email || "";
        case "personal_email":
          return item.personal_email || "";
        case "phone":
          return item.phone || "";
        case "cargo":
          return item.cargo?.name || "";
        case "departments":
          return (item.departments || []).map((department) => department.name).join(", ");
        case "status":
          return this.statusLabel(item.status);
        case "active":
          return item.active ? "Sí" : "No";
        case "contract_type":
          return this.contractTypeLabel(item.contract_type);
        case "workday":
          return this.workdayLabel(item.workday);
        case "contract_hours":
          return item.contract_hours ?? "";
        case "start_date":
          return this.formatDate(item.start_date);
        case "end_date":
          return this.formatDate(item.end_date);
        case "region":
          return item.region_record?.short_name || item.region_record?.name || item.region || "";
        case "commune":
          return item.commune_record?.name || item.commune || "";
        case "associated_user":
          return item.user ? `${item.user.name} (${item.user.email})` : "";
        case "professional_title":
          return item.professional_title || "";
        case "specialty":
          return item.specialty || "";
        case "created_at":
          return this.formatDateTime(item.created_at);
        case "updated_at":
          return this.formatDateTime(item.updated_at);
        default:
          return item[key] ?? "";
      }
    },
    downloadCsv(rows, selectedColumns) {
      const headers = selectedColumns.map((column) => column.label);
      const escape = (value) => {
        const str = String(value ?? "");
        const normalized = str.replace(/"/g, '""');
        return /[",\n\r;]/.test(str) ? `"${normalized}"` : normalized;
      };

      const lines = [headers.map(escape).join(";")];

      rows.forEach((row) => {
        lines.push(headers.map((header) => escape(row[header])).join(";"));
      });

      const csv = "\uFEFF" + lines.join("\n");
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `funcionarios_${this.todayStamp()}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    downloadExcel(rows, selectedColumns) {
      const headers = selectedColumns.map((column) => column.label);
      const escapeHtml = (value) =>
        String(value ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;");

      const table = `
        <table>
          <thead>
            <tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join("")}</tr>
          </thead>
          <tbody>
            ${rows
              .map(
                (row) => `<tr>${headers.map((header) => `<td>${escapeHtml(row[header])}</td>`).join("")}</tr>`
              )
              .join("")}
          </tbody>
        </table>
      `;

      const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:x="urn:schemas-microsoft-com:office:excel"
              xmlns="http://www.w3.org/TR/REC-html40">
          <head>
            <meta charset="utf-8" />
          </head>
          <body>${table}</body>
        </html>
      `;

      const blob = new Blob([`\uFEFF${html}`], {
        type: "application/vnd.ms-excel;charset=utf-8;",
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `funcionarios_${this.todayStamp()}.xls`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    downloadPdf(rows, selectedColumns) {
      const pdfMake = getPdfMake();
      const headers = selectedColumns.map((column) => column.label);
      const tableBody = [
        headers.map((header) => ({
          text: header,
          style: "tableHeader",
        })),
        ...rows.map((row) => headers.map((header) => String(row[header] ?? "-"))),
      ];

      const activeFilters = [
        this.filters.search ? `Búsqueda: ${this.filters.search}` : null,
        this.filters.name ? `Nombre: ${this.filters.name}` : null,
        this.filters.rut ? `RUT: ${this.filters.rut}` : null,
        this.filters.cargo_id
          ? `Cargo: ${this.cargoOptions.find((item) => item.value === this.filters.cargo_id)?.label || "-"}`
          : null,
        this.filters.department_id
          ? `Departamento: ${
              this.departmentOptions.find((item) => item.value === this.filters.department_id)?.label || "-"
            }`
          : null,
        this.filters.status
          ? `Estado: ${this.statusOptions.find((item) => item.value === this.filters.status)?.label || "-"}`
          : null,
        this.filters.contract_type
          ? `Tipo de contrato: ${
              this.contractTypeOptions.find((item) => item.value === this.filters.contract_type)?.label || "-"
            }`
          : null,
      ].filter(Boolean);

      const docDefinition = {
        pageSize: "A4",
        pageOrientation: selectedColumns.length > 6 ? "landscape" : "portrait",
        pageMargins: [28, 36, 28, 36],
        content: [
          { text: "Listado de funcionarios", style: "title" },
          {
            text: `Generado el ${this.formatDateTime(new Date().toISOString())}`,
            style: "muted",
            margin: [0, 4, 0, 8],
          },
          {
            text: `Total exportado: ${rows.length} funcionario(s)`,
            style: "subtitle",
            margin: [0, 0, 0, 6],
          },
          ...(activeFilters.length
            ? [
                {
                  text: `Filtros aplicados: ${activeFilters.join(" | ")}`,
                  style: "muted",
                  margin: [0, 0, 0, 10],
                },
              ]
            : []),
          {
            table: {
              headerRows: 1,
              widths: headers.map(() => "*"),
              body: tableBody,
            },
            layout: "lightHorizontalLines",
          },
        ],
        styles: {
          title: { fontSize: 16, bold: true, color: "#2a3042" },
          subtitle: { fontSize: 10, bold: true, color: "#495057" },
          muted: { fontSize: 9, color: "#74788d" },
          tableHeader: { bold: true, fillColor: "#eff2f7", color: "#495057" },
        },
        defaultStyle: {
          fontSize: 8,
        },
      };

      pdfMake.createPdf(docDefinition).download(`listado_funcionarios_${this.todayStamp()}.pdf`);
    },
    todayStamp() {
      return new Date().toISOString().slice(0, 10);
    },
    confirmAction({ title, text, confirmButtonText }) {
      return Swal.fire({
        title,
        text,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: "Cancelar",
        reverseButtons: true,
      });
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
      });
    },
    departmentNames(item) {
      return (item.departments || []).map((department) => department.name).join(", ");
    },
    statusVariant(item) {
      if (!item.active) return "secondary";
      if (item.status === "activo") return "success";
      if (item.status === "licencia") return "warning";
      if (item.status === "desvinculado") return "danger";
      return "info";
    },
    statusLabel(value) {
      const found = (this.catalogs.statuses || []).find((status) => status.value === value);
      return found?.label || value || "-";
    },
    contractTypeLabel(value) {
      const found = (this.catalogs.contract_types || []).find((type) => type.value === value);
      return found?.label || value || "-";
    },
    workdayLabel(value) {
      const found = [
        { value: "completa", label: "Jornada completa" },
        { value: "parcial", label: "Jornada parcial" },
        { value: "por_horas", label: "Por horas" },
        { value: "turnos", label: "Turnos" },
      ].find((item) => item.value === value);
      return found?.label || value || "-";
    },
    initials(name) {
      return String(name || "")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("") || "?";
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
    formatDate(value) {
      if (!value) return "";
      const normalized = String(value).trim().replace("T", " ");
      const datePart = normalized.split(" ")[0];
      const [year, month, day] = datePart.split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    formatDateTime(value) {
      if (!value) return "";
      const normalized = String(value).trim().replace("T", " ").replace(/\.\d+Z?$/, "");
      const [datePart, timePart = ""] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      if (!(year && month && day)) return value;
      const [hours = "00", minutes = "00"] = timePart.split(":");
      return `${day}/${month}/${year} ${hours}:${minutes}`;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">Gestión de Funcionarios</h4>
        <div class="text-muted">Registro personal, laboral e institucional.</div>
      </div>
      <router-link v-if="canEdit" to="/staff/new" class="btn btn-primary">
        <i class="mdi mdi-account-plus-outline me-1"></i>
        Nuevo funcionario
      </router-link>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="row">
      <div class="col-xl-4 col-md-6">
        <BCard no-body class="mini-stats-wid">
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="flex-grow-1">
                <p class="text-muted fw-medium mb-2">Total funcionarios</p>
                <h4 class="mb-0">{{ pagination.total }}</h4>
              </div>
              <div class="mini-stat-icon avatar-sm rounded-circle bg-primary align-self-center">
                <span class="avatar-title">
                  <i class="bx bx-user-circle font-size-24"></i>
                </span>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </div>
      <div class="col-xl-4 col-md-6">
        <BCard no-body class="mini-stats-wid">
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="flex-grow-1">
                <p class="text-muted fw-medium mb-2">Activos en la página</p>
                <h4 class="mb-0">{{ currentActiveCount }}</h4>
              </div>
              <div class="mini-stat-icon avatar-sm rounded-circle bg-success align-self-center">
                <span class="avatar-title">
                  <i class="bx bx-check-shield font-size-24"></i>
                </span>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </div>
      <div class="col-xl-4 col-md-12">
        <BCard no-body class="mini-stats-wid">
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="flex-grow-1">
                <p class="text-muted fw-medium mb-2">Filtros aplicados</p>
                <h4 class="mb-0">{{ activeFilterCount }}</h4>
              </div>
              <div class="mini-stat-icon avatar-sm rounded-circle bg-info align-self-center">
                <span class="avatar-title">
                  <i class="bx bx-slider-alt font-size-24"></i>
                </span>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </div>
    </div>

    <BCard class="mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
          <h5 class="mb-1">Buscador y filtros</h5>
          <p class="text-muted mb-0">Refina la búsqueda por nombre, RUT, cargo, estado o contrato.</p>
        </div>
        <span class="badge rounded-pill badge-soft-primary font-size-12">
          {{ activeFilterCount }} filtros activos
        </span>
      </div>

      <div class="row g-3 align-items-end">
        <div class="col-xxl-4 col-lg-6">
          <label class="form-label">Búsqueda general</label>
          <div class="search-box">
            <div class="position-relative">
              <BFormInput
                v-model="filters.search"
                class="ps-5"
                placeholder="Nombre, RUT, correo o cargo"
                @keyup.enter="loadStaff(1)"
              />
              <i class="bx bx-search-alt search-icon"></i>
            </div>
          </div>
        </div>
        <div class="col-xxl-2 col-lg-3 col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="filters.name" @keyup.enter="loadStaff(1)" />
        </div>
        <div class="col-xxl-2 col-lg-3 col-md-6">
          <label class="form-label">RUT</label>
          <BFormInput v-model="filters.rut" @keyup.enter="loadStaff(1)" />
        </div>
        <div class="col-xxl-2 col-lg-4 col-md-6">
          <label class="form-label">Cargo</label>
          <Multiselect v-model="filters.cargo_id" :options="cargoOptions" :searchable="true" />
        </div>
        <div class="col-xxl-2 col-lg-4 col-md-6">
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6">
          <label class="form-label">Tipo de contrato</label>
          <Multiselect v-model="filters.contract_type" :options="contractTypeOptions" :searchable="true" />
        </div>
        <div class="col-xl-6 col-lg-8">
          <div class="d-flex flex-wrap gap-2">
            <BButton variant="primary" @click="loadStaff(1)">
              <i class="bx bx-search-alt me-1"></i>
              Filtrar
            </BButton>
            <BButton variant="light" @click="resetFilters">
              <i class="bx bx-reset me-1"></i>
              Limpiar
            </BButton>
            <router-link to="/staff/departments" class="btn btn-outline-secondary">
              <i class="bx bx-buildings me-1"></i>
              Departamentos
            </router-link>
          </div>
        </div>
      </div>
    </BCard>

    <BCard no-body>
      <div class="card-header border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <h5 class="mb-1">Listado de funcionarios</h5>
            <p class="text-muted mb-0">Vista resumida con datos institucionales y acceso rápido a acciones.</p>
          </div>
          <div class="d-flex align-items-center gap-2">
            <BButton
              v-if="canExport"
              variant="outline-primary"
              :disabled="exporting"
              @click="openExportModal"
            >
              <i class="mdi mdi-file-export-outline me-1"></i>
              {{ exporting ? "Exportando..." : "Exportar" }}
            </BButton>
            <span class="badge rounded-pill badge-soft-success font-size-12">
              {{ currentActiveCount }} activos en pantalla
            </span>
            <span class="badge rounded-pill badge-soft-secondary font-size-12">
              Total {{ pagination.total }}
            </span>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <BTableSimple class="table table-centered align-middle table-nowrap mb-0">
          <BThead class="table-light">
            <BTr>
              <BTh>Funcionario</BTh>
              <BTh>RUT</BTh>
              <BTh>Cargo</BTh>
              <BTh>Departamentos</BTh>
              <BTh>Estado</BTh>
              <BTh>Usuario</BTh>
              <BTh class="text-end">Acciones</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-if="loading">
              <BTd colspan="7" class="text-center py-5">
                <BSpinner small class="me-2"></BSpinner>
                Cargando funcionarios...
              </BTd>
            </BTr>

            <BTr v-else-if="staff.length === 0">
              <BTd colspan="7" class="text-center py-5">
                <div class="avatar-sm mx-auto mb-3">
                  <span class="avatar-title rounded-circle bg-light text-primary font-size-24">
                    <i class="bx bx-search-alt"></i>
                  </span>
                </div>
                <h5 class="mb-1">Sin resultados</h5>
                <p class="text-muted mb-0">No hay funcionarios que coincidan con los filtros aplicados.</p>
              </BTd>
            </BTr>

            <BTr v-for="item in staff" :key="item.id">
              <BTd>
                <div class="d-flex align-items-center">
                  <div class="avatar-sm me-3">
                    <span class="avatar-title rounded-circle bg-soft-primary text-primary font-size-16 fw-semibold">
                      {{ initials(item.full_name) }}
                    </span>
                  </div>
                  <div>
                    <h5 class="font-size-14 mb-1">{{ item.full_name }}</h5>
                    <p class="text-muted mb-0">{{ item.institutional_email || item.personal_email || "Sin correo" }}</p>
                  </div>
                </div>
              </BTd>

              <BTd>
                <span class="fw-medium">{{ item.rut || "-" }}</span>
              </BTd>

              <BTd>
                <div class="fw-medium">{{ item.cargo?.name || "-" }}</div>
                <span class="badge rounded-pill badge-soft-info mt-1">
                  {{ contractTypeLabel(item.contract_type) }}
                </span>
              </BTd>

              <BTd>
                <div v-if="(item.departments || []).length" class="d-flex flex-wrap gap-1">
                  <span
                    v-for="department in item.departments"
                    :key="department.id"
                    class="badge rounded-pill department-pill"
                    :style="{
                      backgroundColor: `${department.color || '#556ee6'}22`,
                      color: department.color || '#556ee6',
                    }"
                  >
                    {{ department.name }}
                  </span>
                </div>
                <span v-else class="text-muted">Sin departamentos</span>
              </BTd>

              <BTd>
                <div class="d-flex flex-column gap-1">
                  <span :class="`badge rounded-pill badge-soft-${statusVariant(item)}`">
                    {{ statusLabel(item.status) }}
                  </span>
                  <small class="text-muted">{{ item.active ? "Registro activo" : "Registro desactivado" }}</small>
                </div>
              </BTd>

              <BTd>
                <div v-if="item.user">
                  <div class="fw-medium">{{ item.user.name }}</div>
                  <div class="text-muted">{{ item.user.email }}</div>
                </div>
                <span v-else class="text-muted">Sin acceso al sistema</span>
              </BTd>

              <BTd class="text-end">
                <div class="d-flex justify-content-end flex-wrap gap-2">
                  <router-link :to="`/staff/${item.id}`" class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-eye-outline me-1"></i>
                    Ver ficha
                  </router-link>
                  <BButton
                    v-if="canEdit"
                    size="sm"
                    :variant="item.active ? 'warning' : 'success'"
                    @click="toggleActive(item)"
                  >
                    <i :class="item.active ? 'mdi mdi-account-off-outline me-1' : 'mdi mdi-account-check-outline me-1'"></i>
                    {{ item.active ? "Desactivar" : "Activar" }}
                  </BButton>
                  <BButton v-if="canDelete" size="sm" variant="danger" @click="remove(item)">
                    <i class="mdi mdi-trash-can-outline me-1"></i>
                    Eliminar
                  </BButton>
                </div>
              </BTd>
            </BTr>
          </BTbody>
        </BTableSimple>
      </div>

      <div class="card-footer border-top">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <small class="text-muted">
            Mostrando {{ staff.length }} de {{ pagination.total }} registros
          </small>
          <BPagination
            v-model="pagination.current_page"
            :per-page="15"
            :total-rows="pagination.total"
            pills
            align="end"
            @update:model-value="loadStaff"
          />
        </div>
      </div>
    </BCard>

    <BModal v-model="showExportModal" title="Exportar listado de funcionarios" centered hide-footer>
      <div class="mb-3">
        <label class="form-label">Formato</label>
        <BFormSelect
          v-model="exportForm.format"
          :options="exportFormatOptions"
        />
      </div>

      <div class="mb-3">
        <label class="form-label">Columnas a exportar</label>
        <Multiselect
          v-model="exportForm.columns"
          :options="exportColumnOptions"
          mode="multiple"
          :close-on-select="false"
          :searchable="true"
          placeholder="Selecciona una o más columnas"
        />
        <small class="text-muted d-block mt-2">
          Debes seleccionar al menos una columna. Por defecto se exportan nombre y RUT.
        </small>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <BButton variant="light" @click="showExportModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="exporting" @click="exportList">
          {{ exporting ? "Exportando..." : "Exportar archivo" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.mini-stat-icon .avatar-title {
  color: #fff;
}

.search-box .search-icon {
  left: 16px;
}

.department-pill {
  font-size: 11px;
  font-weight: 600;
  padding: 0.35rem 0.65rem;
}

.table > :not(caption) > * > * {
  vertical-align: middle;
}

.avatar-title.bg-soft-primary {
  background-color: rgba(85, 110, 230, 0.18) !important;
}
</style>
