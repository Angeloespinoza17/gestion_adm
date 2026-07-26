<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";
import StaffModalIntro from "../../components/staff/modal-intro.vue";
import StaffPageHeader from "../../components/staff/page-header.vue";
import "../../components/staff/staff-ui.css";

const exportableColumns = [
  { value: "full_name", label: "Nombre completo" },
  { value: "rut", label: "RUT" },
  { value: "institutional_email", label: "Correo institucional" },
  { value: "personal_email", label: "Correo personal" },
  { value: "phone", label: "Teléfono" },
  { value: "cargo", label: "Cargo" },
  { value: "departments", label: "Equipos a los que pertenece" },
  { value: "managed_departments", label: "Departamentos a cargo" },
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

const perPageOptions = [
  { value: 15, text: "15 por página" },
  { value: 30, text: "30 por página" },
  { value: 50, text: "50 por página" },
  { value: 100, text: "100 por página" },
];

export default {
  components: { Layout, Multiselect, StaffModalIntro, StaffPageHeader },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      showExportModal: false,
      showImportModal: false,
      importing: false,
      downloadingTemplate: false,
      importFile: null,
      importResult: null,
      importFileKey: 0,
      updateExisting: true,
      showAdvancedFilters: false,
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
        active: null,
        access: null,
      },
      exportForm: {
        format: "excel",
        columns: ["full_name", "rut"],
      },
      staff: [],
      summary: { total: 0, active: 0, with_account: 0, without_account: 0 },
      pagination: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
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
    activeOptions() {
      return [
        { value: null, label: "Todos" },
        { value: "1", label: "Solo activos" },
        { value: "0", label: "Solo desactivados" },
      ];
    },
    accessOptions() {
      return [
        { value: null, label: "Todas las cuentas" },
        { value: "with_account", label: "Con cuenta de acceso" },
        { value: "without_account", label: "Sin cuenta de acceso" },
      ];
    },
    perPageSelectOptions() {
      return perPageOptions;
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
        this.filters.active,
        this.filters.access,
      ].filter((value) => value !== null && value !== "").length;
    },
    currentActiveCount() {
      return (this.staff || []).filter((item) => item.active).length;
    },
    currentInactiveCount() {
      return Math.max((this.staff || []).length - this.currentActiveCount, 0);
    },
    linkedUsersCount() {
      return (this.staff || []).filter((item) => item.user).length;
    },
    hasActiveFilters() {
      return this.activeFilterCount > 0;
    },
    activeFilters() {
      const filters = [];
      const pushText = (key, label) => {
        if (this.filters[key]) {
          filters.push({ key, label, value: this.filters[key] });
        }
      };
      const pushSelect = (key, label, options) => {
        if (this.filters[key] !== null && this.filters[key] !== "") {
          filters.push({ key, label, value: this.optionLabel(options, this.filters[key]) });
        }
      };

      pushText("search", "Búsqueda");
      pushText("name", "Nombre");
      pushText("rut", "RUT");
      pushSelect("cargo_id", "Cargo", this.cargoOptions);
      pushSelect("department_id", "Departamento", this.departmentOptions);
      pushSelect("status", "Estado", this.statusOptions);
      pushSelect("contract_type", "Contrato", this.contractTypeOptions);
      pushSelect("active", "Registro", this.activeOptions);
      pushSelect("access", "Acceso", this.accessOptions);

      return filters;
    },
    paginationRange() {
      if (!this.pagination.total) {
        return "Sin resultados";
      }

      const perPage = Number(this.pagination.per_page || 15);
      const currentPage = Number(this.pagination.current_page || 1);
      const from = (currentPage - 1) * perPage + 1;
      const to = Math.min(from + (this.staff || []).length - 1, Number(this.pagination.total || 0));

      return `${this.formatInteger(from)}-${this.formatInteger(to)} de ${this.formatInteger(this.pagination.total)}`;
    },
    summaryCards() {
      return [
        {
          label: "Funcionarios",
          value: this.formatInteger(this.summary.total),
          detail: "personas categorizadas como staff",
          icon: "bx-list-ul",
          tone: "primary",
        },
        {
          label: "Activos",
          value: this.formatInteger(this.summary.active),
          detail: "fichas laborales vigentes",
          icon: "bx-id-card",
          tone: "success",
        },
        {
          label: "Con acceso",
          value: this.formatInteger(this.summary.with_account),
          detail: "cuentas de usuario vinculadas",
          icon: "bx-user-check",
          tone: "info",
        },
        {
          label: "Sin acceso",
          value: this.formatInteger(this.summary.without_account),
          detail: "fichas pendientes de cuenta",
          icon: "bx-user-x",
          tone: "warning",
        },
      ];
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
    staffRequestParams(page = 1, perPage = this.pagination.per_page) {
      return {
        page,
        per_page: perPage,
        search: this.filters.search || null,
        name: this.filters.name || null,
        rut: this.filters.rut || null,
        cargo_id: this.filters.cargo_id,
        department_id: this.filters.department_id,
        status: this.filters.status,
        contract_type: this.filters.contract_type,
        active: this.filters.active === null ? null : this.filters.active,
        access: this.filters.access,
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/staff/catalogs");
      this.catalogs = response.data;
    },
    async loadStaff(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff", {
          params: this.staffRequestParams(page),
        });

        this.staff = response.data.data;
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
          per_page: response.data.per_page || this.pagination.per_page || 15,
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
        active: null,
        access: null,
      };
      this.loadStaff(1);
    },
    removeFilter(key) {
      if (["search", "name", "rut"].includes(key)) {
        this.filters[key] = "";
      } else {
        this.filters[key] = null;
      }

      this.loadStaff(1);
    },
    changePerPage() {
      this.pagination.per_page = Number(this.pagination.per_page || 15);
      this.loadStaff(1);
    },
    initials(name) {
      return String(name || "?")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },
    visibleDepartments(item) {
      return (item?.departments || []).slice(0, 2);
    },
    remainingDepartmentCount(item) {
      return Math.max((item?.departments || []).length - 2, 0);
    },
    visibleManagedDepartments(item) {
      return (item?.managed_departments || []).slice(0, 2);
    },
    remainingManagedDepartmentCount(item) {
      return Math.max((item?.managed_departments || []).length - 2, 0);
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
        title: "Eliminar funcionario y cuenta",
        text: `Se eliminará a ${item.full_name}, su cuenta de acceso y sus registros asociados, incluidas las reservas. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, eliminar todo",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/staff/${item.id}`);
        this.showSuccessAlert("Eliminación completada", "El funcionario y su cuenta de acceso fueron eliminados correctamente.");
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
    openImportModal() {
      this.importFile = null;
      this.importResult = null;
      this.importFileKey += 1;
      this.updateExisting = true;
      this.showImportModal = true;
    },
    onImportFile(event) {
      this.importFile = event?.target?.files?.[0] || null;
      this.importResult = null;
    },
    async downloadImportTemplate() {
      this.downloadingTemplate = true;
      try {
        const response = await axios.get("/api/staff/import-template", { responseType: "blob" });
        const url = URL.createObjectURL(response.data);
        const link = document.createElement("a");
        link.href = url;
        link.download = "plantilla_importacion_funcionarios.xlsx";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        this.showErrorAlert(this.formatError(error));
      } finally {
        this.downloadingTemplate = false;
      }
    },
    async importStaff() {
      if (!this.importFile) {
        this.showErrorAlert("Selecciona una plantilla XLSX o un archivo CSV para continuar.");
        return;
      }

      this.importing = true;
      this.importResult = null;
      try {
        const payload = new FormData();
        payload.append("file", this.importFile);
        payload.append("update_existing", this.updateExisting ? "1" : "0");

        const response = await axios.post("/api/staff/import", payload);
        this.importResult = response.data.data;
        await this.loadStaff(1);

        if (this.importResult.error_count > 0) {
          Swal.fire({
            title: "Importación con observaciones",
            text: `Se procesaron ${this.importResult.processed} filas. Revisa el detalle de las filas omitidas.`,
            icon: "warning",
            confirmButtonText: "Revisar resultado",
            customClass: { popup: "staff-alert" },
          });
        } else {
          this.showSuccessAlert(
            "Importación completada",
            `${this.importResult.created} creados y ${this.importResult.updated} actualizados.`
          );
        }
      } catch (error) {
        this.showErrorAlert(this.formatError(error));
      } finally {
        this.importing = false;
      }
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
            params: this.staffRequestParams(page, 200),
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
        case "managed_departments":
          return (item.managed_departments || []).map((department) => department.name).join(", ");
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

      const activeFilters = this.activeFilters.map((filter) => `${filter.label}: ${filter.value}`);

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
        customClass: { popup: "staff-alert" },
      });
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
        customClass: { popup: "staff-alert" },
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
        customClass: { popup: "staff-alert" },
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
    optionLabel(options, value) {
      const found = (options || []).find((item) => String(item.value) === String(value));
      return found?.label || found?.text || value || "-";
    },
    formatInteger(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
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
    <StaffPageHeader
      title="Gestión de funcionarios"
      subtitle="Registro personal, laboral e institucional en un solo lugar."
      icon="bx-group"
    >
      <template #actions>
        <router-link to="/staff/departments" class="btn btn-outline-secondary">
          <i class="bx bx-buildings me-1"></i>
          Departamentos
        </router-link>
        <BButton v-if="canEdit" variant="outline-primary" @click="openImportModal">
          <i class="bx bx-import me-1"></i>
          Importación masiva
        </BButton>
        <router-link v-if="canEdit" to="/staff/new" class="btn btn-primary">
          <i class="mdi mdi-account-plus-outline me-1"></i>
          Nuevo funcionario
        </router-link>
      </template>
    </StaffPageHeader>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="staff-identity-note mb-4">
      <span class="staff-identity-note__icon"><i class="bx bx-id-card"></i></span>
      <div>
        <strong>Funcionario es una categoría de persona, no un rol.</strong>
        <p class="mb-0">
          Aquí se administra su ficha laboral. La cuenta de acceso es independiente y los roles solo determinan
          qué módulos puede utilizar.
        </p>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div v-for="card in summaryCards" :key="card.label" class="col-xl-3 col-md-6">
        <BCard no-body class="staff-summary-card h-100">
          <BCardBody>
            <div class="d-flex align-items-start justify-content-between gap-3">
              <div>
                <span class="staff-summary-label">{{ card.label }}</span>
                <h3 class="staff-summary-value">{{ card.value }}</h3>
                <p class="text-muted mb-0">{{ card.detail }}</p>
              </div>
              <span :class="`staff-summary-icon staff-summary-icon-${card.tone}`">
                <i :class="`bx ${card.icon}`"></i>
              </span>
            </div>
          </BCardBody>
        </BCard>
      </div>
    </div>

    <BCard class="mb-4 staff-filter-card">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
          <h5 class="mb-1">Buscar en el directorio</h5>
          <p class="text-muted mb-0">Encuentra personas por identidad, organización o estado de acceso.</p>
        </div>
        <span :class="['badge rounded-pill font-size-12', hasActiveFilters ? 'badge-soft-primary' : 'badge-soft-secondary']">
          {{ activeFilterCount }} filtros activos
        </span>
      </div>

      <div class="row g-3 align-items-end">
        <div class="col-xl-4 col-lg-6">
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
        <div class="col-xl-3 col-lg-6">
          <label class="form-label">Departamento</label>
          <Multiselect v-model="filters.department_id" :options="departmentOptions" :searchable="true" />
        </div>
        <div class="col-xl-2 col-md-6">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-xl-3 col-md-6">
          <label class="form-label">Cuenta de acceso</label>
          <Multiselect v-model="filters.access" :options="accessOptions" :searchable="false" />
        </div>
        <div class="col-12">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <BButton variant="primary" @click="loadStaff(1)">
              <i class="bx bx-search-alt me-1"></i>
              Buscar
            </BButton>
            <BButton variant="light" @click="resetFilters">
              <i class="bx bx-reset me-1"></i>
              Limpiar
            </BButton>
            <BButton variant="link" class="text-decoration-none" @click="showAdvancedFilters = !showAdvancedFilters">
              <i :class="showAdvancedFilters ? 'bx bx-chevron-up' : 'bx bx-slider-alt'" class="me-1"></i>
              {{ showAdvancedFilters ? "Ocultar filtros avanzados" : "Más filtros" }}
            </BButton>
          </div>
        </div>
      </div>

      <BCollapse v-model="showAdvancedFilters">
        <div class="staff-advanced-filters">
          <div class="row g-3">
            <div class="col-lg-3 col-md-6">
              <label class="form-label">Nombre exacto o parcial</label>
              <BFormInput v-model="filters.name" @keyup.enter="loadStaff(1)" />
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">RUT</label>
              <BFormInput v-model="filters.rut" @keyup.enter="loadStaff(1)" />
            </div>
            <div class="col-lg-3 col-md-6">
              <label class="form-label">Cargo</label>
              <Multiselect v-model="filters.cargo_id" :options="cargoOptions" :searchable="true" />
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Contrato</label>
              <Multiselect v-model="filters.contract_type" :options="contractTypeOptions" :searchable="true" />
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label">Ficha</label>
              <Multiselect v-model="filters.active" :options="activeOptions" :searchable="false" />
            </div>
          </div>
        </div>
      </BCollapse>

      <div v-if="activeFilters.length" class="active-filter-bar">
        <span v-for="filter in activeFilters" :key="filter.key" class="active-filter-chip">
          <span class="active-filter-label">{{ filter.label }}</span>
          <span class="active-filter-value">{{ filter.value }}</span>
          <button
            type="button"
            class="active-filter-remove"
            :aria-label="`Quitar filtro ${filter.label}`"
            @click="removeFilter(filter.key)"
          >
            <i class="bx bx-x"></i>
          </button>
        </span>
        <BButton size="sm" variant="link" class="p-0 ms-1" @click="resetFilters">Limpiar todo</BButton>
      </div>
    </BCard>

    <BCard no-body class="staff-list-card">
      <div class="card-header border-bottom">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <h5 class="mb-1">Listado de funcionarios</h5>
            <p class="text-muted mb-0">{{ paginationRange }}</p>
          </div>
          <div class="d-flex align-items-center flex-wrap gap-2">
            <BFormSelect
              v-model="pagination.per_page"
              :options="perPageSelectOptions"
              size="sm"
              class="staff-page-size"
              @update:model-value="changePerPage"
            />
            <BButton
              v-if="canExport"
              variant="outline-primary"
              :disabled="exporting"
              @click="openExportModal"
            >
              <i class="mdi mdi-file-export-outline me-1"></i>
              {{ exporting ? "Exportando..." : "Exportar" }}
            </BButton>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <BTableSimple class="table table-centered align-middle staff-table mb-0">
          <BThead class="table-light">
            <BTr>
              <BTh>Funcionario</BTh>
              <BTh>Organización</BTh>
              <BTh>Cuenta de acceso</BTh>
              <BTh class="staff-status-col">Estado</BTh>
              <BTh class="text-end staff-actions-col">Acciones</BTh>
            </BTr>
          </BThead>
          <BTbody>
            <BTr v-if="loading">
              <BTd colspan="5" class="text-center py-5">
                <BSpinner small class="me-2"></BSpinner>
                Cargando funcionarios...
              </BTd>
            </BTr>

            <BTr v-else-if="staff.length === 0">
              <BTd colspan="5" class="text-center py-5">
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
                <div class="staff-person-cell d-flex align-items-center gap-2">
                  <span class="staff-person-avatar">{{ initials(item.full_name) }}</span>
                  <div class="min-w-0">
                    <router-link :to="`/staff/${item.id}`" class="staff-name-link">
                      {{ item.full_name }}
                    </router-link>
                    <div class="staff-meta">
                      <span>{{ item.rut || "Sin RUT" }}</span>
                      <span class="staff-category-badge">Funcionario</span>
                    </div>
                  </div>
                </div>
              </BTd>

              <BTd>
                <div class="staff-organization">
                  <div class="fw-medium">{{ item.cargo?.name || "Sin cargo asignado" }}</div>
                  <div v-if="(item.departments || []).length" class="staff-department-list">
                    <span
                      v-for="department in visibleDepartments(item)"
                      :key="department.id"
                      class="staff-department-chip"
                    >
                      <i class="bx bx-buildings"></i>{{ department.name }}
                    </span>
                    <span v-if="remainingDepartmentCount(item)" class="staff-department-more">
                      +{{ remainingDepartmentCount(item) }}
                    </span>
                  </div>
                  <small v-else class="text-muted">Sin departamento</small>
                  <div v-if="(item.managed_departments || []).length" class="staff-managed-list">
                    <span class="staff-managed-label">A cargo de</span>
                    <span
                      v-for="department in visibleManagedDepartments(item)"
                      :key="department.id"
                      class="staff-managed-chip"
                    >
                      {{ department.name }}
                    </span>
                    <span v-if="remainingManagedDepartmentCount(item)" class="staff-department-more">
                      +{{ remainingManagedDepartmentCount(item) }}
                    </span>
                  </div>
                </div>
              </BTd>

              <BTd>
                <div v-if="item.user" class="staff-account-cell">
                  <span class="staff-account-status is-linked">
                    <i class="bx bx-check-circle"></i>Cuenta vinculada
                  </span>
                  <span class="staff-account-email">{{ item.user.email }}</span>
                  <small :class="item.user.active ? 'text-success' : 'text-muted'">
                    {{ item.user.active ? "Acceso activo" : "Acceso desactivado" }}
                  </small>
                </div>
                <div v-else class="staff-account-cell">
                  <span class="staff-account-status is-pending">
                    <i class="bx bx-user-x"></i>Sin cuenta de acceso
                  </span>
                  <span class="staff-account-email text-muted">
                    {{ item.institutional_email || "Falta correo institucional" }}
                  </span>
                  <small class="text-muted">No depende de un rol</small>
                </div>
              </BTd>

              <BTd class="staff-status-col">
                <div class="staff-status-stack">
                  <span :class="`badge rounded-pill badge-soft-${statusVariant(item)}`">
                    {{ statusLabel(item.status) }}
                  </span>
                  <small class="text-muted">{{ item.active ? "Ficha activa" : "Ficha desactivada" }}</small>
                </div>
              </BTd>

              <BTd class="text-end staff-actions-col">
                <div class="staff-actions">
                  <router-link :to="`/staff/${item.id}`" class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-eye-outline me-1"></i>
                    Ficha
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
            {{ paginationRange }}
          </small>
          <BPagination
            v-model="pagination.current_page"
            :per-page="pagination.per_page"
            :total-rows="pagination.total"
            pills
            align="end"
            @update:model-value="loadStaff"
          />
        </div>
      </div>
    </BCard>

    <BModal
      v-model="showExportModal"
      title="Exportar listado de funcionarios"
      centered
      scrollable
      hide-footer
      modal-class="staff-modal"
    >
      <StaffModalIntro
        title="Configura tu archivo"
        text="Elige el formato y las columnas que necesitas. Se respetarán los filtros activos del listado."
        icon="bx-export"
      />

      <div class="row g-3">
        <div class="col-md-5">
        <label class="form-label">Formato</label>
        <BFormSelect
          v-model="exportForm.format"
          :options="exportFormatOptions"
        />
        </div>

        <div class="col-md-7">
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
      </div>

      <div class="staff-modal-actions">
        <BButton variant="light" @click="showExportModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="exporting" @click="exportList">
          <i class="bx bx-download me-1"></i>
          {{ exporting ? "Exportando..." : "Exportar archivo" }}
        </BButton>
      </div>
    </BModal>

    <BModal
      v-model="showImportModal"
      title="Importación masiva de funcionarios"
      size="xl"
      centered
      scrollable
      hide-footer
      modal-class="staff-modal"
    >
      <StaffModalIntro
        title="Carga segura desde Excel"
        text="Descarga la plantilla, completa una fila por funcionario y vuelve a cargarla. Solo el nombre completo es obligatorio."
        icon="bx-spreadsheet"
      >
        <BButton
          size="sm"
          variant="outline-primary"
          :disabled="downloadingTemplate"
          @click="downloadImportTemplate"
        >
          <i class="bx bx-download me-1"></i>
          {{ downloadingTemplate ? "Descargando..." : "Descargar plantilla" }}
        </BButton>
      </StaffModalIntro>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="staff-import-dropzone">
            <span class="staff-import-dropzone__icon"><i class="bx bx-cloud-upload"></i></span>
            <h6 class="mb-1">Selecciona el archivo completado</h6>
            <p class="text-muted small mb-3">Formatos admitidos: XLSX o CSV · máximo 10 MB · hasta 2.000 filas.</p>
            <input
              :key="importFileKey"
              type="file"
              class="form-control"
              accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              @change="onImportFile"
            />
            <div v-if="importFile" class="mt-3 fw-semibold text-primary">
              <i class="bx bx-file me-1"></i>{{ importFile.name }}
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="staff-import-settings h-100">
            <h6 class="mb-2">Comportamiento de la carga</h6>
            <BFormCheckbox v-model="updateExisting" switch>
              Actualizar funcionarios existentes
            </BFormCheckbox>
            <p class="text-muted small mt-2 mb-3">
              Se identifica a cada funcionario por RUT y luego por correo institucional. Si desactivas esta opción, los duplicados se omitirán.
            </p>
            <div class="alert alert-light border mb-0 small">
              <strong class="d-block mb-1">Cuenta de acceso</strong>
              Se crea automáticamente solo cuando la fila contiene RUT y correo institucional. Los demás datos pueden quedar vacíos.
            </div>
          </div>
        </div>
      </div>

      <div v-if="importResult" class="mt-4">
        <h6 class="mb-3">Resultado de la importación</h6>
        <div class="staff-import-result-grid">
          <div class="staff-import-result-card">
            <span class="text-muted small">Procesados</span>
            <strong>{{ importResult.processed }}</strong>
          </div>
          <div class="staff-import-result-card">
            <span class="text-muted small">Creados</span>
            <strong class="text-success">{{ importResult.created }}</strong>
          </div>
          <div class="staff-import-result-card">
            <span class="text-muted small">Actualizados</span>
            <strong class="text-info">{{ importResult.updated }}</strong>
          </div>
          <div class="staff-import-result-card">
            <span class="text-muted small">Omitidos</span>
            <strong :class="importResult.skipped ? 'text-danger' : ''">{{ importResult.skipped }}</strong>
          </div>
        </div>

        <div v-if="importResult.errors?.length" class="staff-import-errors">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr><th>Fila</th><th>Campo</th><th>Observación</th></tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in importResult.errors" :key="`${item.row}-${item.field}-${index}`">
                <td class="fw-semibold">{{ item.row }}</td>
                <td><code>{{ item.field }}</code></td>
                <td>{{ item.message }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="staff-modal-actions">
        <BButton variant="light" @click="showImportModal = false">Cerrar</BButton>
        <BButton variant="primary" :disabled="importing || !importFile" @click="importStaff">
          <i :class="importing ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-import me-1'"></i>
          {{ importing ? "Importando..." : "Importar funcionarios" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.search-box .search-icon {
  left: 16px;
}

.staff-eyebrow {
  display: block;
  margin-bottom: 0.25rem;
  color: #556ee6;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.staff-summary-card,
.staff-filter-card,
.staff-list-card {
  border: 1px solid #edf0f5;
  border-radius: 8px;
  box-shadow: 0 0.125rem 0.375rem rgba(15, 23, 42, 0.035);
}

.staff-identity-note {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 1rem 1.1rem;
  border: 1px solid #dbe4ff;
  border-radius: 12px;
  background: linear-gradient(135deg, #f7f9ff, #f5fbff);
  color: #3f4858;
}

.staff-identity-note__icon {
  display: inline-flex;
  width: 2.65rem;
  height: 2.65rem;
  flex: 0 0 2.65rem;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: #e8edff;
  color: #556ee6;
  font-size: 1.35rem;
}

.staff-identity-note strong {
  display: block;
  margin-bottom: 0.2rem;
  color: #2a3042;
}

.staff-identity-note p {
  color: #667085;
  font-size: 0.86rem;
}

.staff-advanced-filters {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid #e7ebf3;
  border-radius: 10px;
  background: #fafbfe;
}

.staff-summary-label {
  display: block;
  margin-bottom: 0.35rem;
  color: #74788d;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.staff-summary-value {
  margin-bottom: 0.2rem;
  color: #2a3042;
  font-size: 1.65rem;
  font-weight: 700;
  line-height: 1.15;
}

.staff-summary-icon {
  display: inline-flex;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 1.4rem;
}

.staff-summary-icon-primary {
  background: rgba(85, 110, 230, 0.12);
  color: #556ee6;
}

.staff-summary-icon-success {
  background: rgba(52, 195, 143, 0.14);
  color: #2ca67a;
}

.staff-summary-icon-info {
  background: rgba(80, 165, 241, 0.14);
  color: #3577b8;
}

.staff-summary-icon-warning {
  background: rgba(241, 180, 76, 0.16);
  color: #b7791f;
}

.active-filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #edf0f5;
}

.active-filter-chip {
  display: inline-flex;
  max-width: 100%;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.45rem 0.35rem 0.65rem;
  border: 1px solid #dbe3f0;
  border-radius: 999px;
  background: #f8f9fb;
  color: #495057;
  font-size: 0.78rem;
  line-height: 1.2;
}

.active-filter-label {
  color: #74788d;
  font-weight: 600;
}

.active-filter-value {
  max-width: 220px;
  overflow: hidden;
  color: #2a3042;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.active-filter-remove {
  display: inline-flex;
  width: 20px;
  height: 20px;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: #74788d;
}

.active-filter-remove:hover {
  background: #e9edf5;
  color: #2a3042;
}

.staff-page-size {
  width: 150px;
  min-width: 150px;
}

.staff-table {
  min-width: 1120px;
}

.staff-table th {
  color: #74788d;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
  white-space: nowrap;
}

.staff-table td {
  padding-top: 0.72rem;
  padding-bottom: 0.72rem;
}

.staff-person-cell {
  min-width: 215px;
}

.staff-person-avatar {
  display: inline-flex;
  width: 2.45rem;
  height: 2.45rem;
  flex: 0 0 2.45rem;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: #eef1ff;
  color: #556ee6;
  font-size: 0.78rem;
  font-weight: 800;
}

.staff-name-link {
  display: block;
  max-width: 230px;
  overflow: hidden;
  color: #2a3042;
  font-size: 0.92rem;
  font-weight: 400;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-name-link:hover {
  color: #556ee6;
}

.staff-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.32rem;
  color: #74788d;
  font-size: 0.78rem;
}

.staff-category-badge {
  padding: 0.08rem 0.38rem;
  border-radius: 999px;
  background: #f0f7ff;
  color: #3577b8;
  font-size: 0.68rem;
  font-weight: 700;
}

.staff-organization {
  min-width: 220px;
}

.staff-department-list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.28rem;
  margin-top: 0.28rem;
}

.staff-managed-list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.28rem;
  margin-top: 0.38rem;
}

.staff-managed-label {
  color: #74788d;
  font-size: 0.67rem;
  font-weight: 700;
  text-transform: uppercase;
}

.staff-managed-chip {
  padding: 0.14rem 0.4rem;
  border: 1px solid #dce5ff;
  border-radius: 999px;
  background: #f7f9ff;
  color: #556ee6;
  font-size: 0.68rem;
  font-weight: 700;
}

.staff-department-chip,
.staff-department-more {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  padding: 0.16rem 0.42rem;
  border-radius: 999px;
  background: #f2f4f7;
  color: #5d6677;
  font-size: 0.69rem;
  line-height: 1.25;
}

.staff-department-more {
  background: #e9edf5;
  font-weight: 700;
}

.staff-account-cell {
  display: flex;
  min-width: 225px;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.16rem;
}

.staff-account-status {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.staff-account-status.is-linked {
  color: #2ca67a;
}

.staff-account-status.is-pending {
  color: #b7791f;
}

.staff-account-email {
  display: block;
  max-width: 240px;
  overflow: hidden;
  color: #3f4858;
  font-size: 0.82rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-status-col {
  width: 128px;
  min-width: 128px;
}

.staff-status-stack {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.18rem;
}

.staff-status-stack .badge {
  display: inline-flex;
  align-items: center;
  min-width: 76px;
  justify-content: center;
  font-size: 0.74rem;
  line-height: 1.15;
}

.staff-status-stack small {
  white-space: nowrap;
}

.staff-actions-col {
  width: 260px;
  min-width: 260px;
}

.staff-actions {
  display: inline-flex;
  flex-wrap: nowrap;
  justify-content: flex-end;
  gap: 0.38rem;
}

.staff-actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  padding-right: 0.52rem;
  padding-left: 0.52rem;
  white-space: nowrap;
}

.table > :not(caption) > * > * {
  vertical-align: middle;
}

.avatar-title.bg-soft-primary {
  background-color: rgba(85, 110, 230, 0.18) !important;
}

@media (max-width: 575.98px) {
  .staff-page-header .btn {
    display: inline-flex;
    flex: 1 1 100%;
    align-items: center;
    justify-content: center;
  }

  .staff-summary-value {
    font-size: 1.45rem;
  }

  .active-filter-value {
    max-width: 145px;
  }

  .staff-page-size {
    width: 100%;
    min-width: 100%;
  }
}
</style>
