<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { getPdfMake } from "../../utils/pdfmake";

const baseColumns = [
  { key: "name", label: "Nombre" },
  { key: "rut", label: "RUT" },
  { key: "email", label: "Correo" },
  { key: "course", label: "Curso" },
];

const fullColumns = [
  { key: "registered_name", label: "Nombre registral" },
  { key: "first_name", label: "Nombres" },
  { key: "last_name", label: "Apellidos" },
  { key: "rut", label: "RUT" },
  { key: "birthdate", label: "Fecha de nacimiento" },
  { key: "email", label: "Correo" },
  { key: "phone", label: "Teléfono" },
  { key: "address", label: "Domicilio" },
  { key: "general_status", label: "Estado general" },
  { key: "academic_year", label: "Año académico" },
  { key: "course", label: "Curso" },
  { key: "enrollment_status", label: "Estado matrícula" },
  { key: "observations", label: "Observaciones" },
  { key: "guardian_role", label: "Quién es apoderado titular" },
  { key: "guardian_name", label: "Apoderado titular" },
  { key: "guardian_relationship", label: "Relación apoderado titular" },
  { key: "guardian_rut", label: "RUT apoderado titular" },
  { key: "guardian_phone", label: "Teléfono apoderado titular" },
  { key: "guardian_email", label: "Correo apoderado titular" },
  { key: "guardian_address", label: "Domicilio apoderado titular" },
  { key: "guardian_backup_role", label: "Quién es apoderado suplente" },
  { key: "guardian_backup_name", label: "Apoderado suplente" },
  { key: "guardian_backup_relationship", label: "Relación apoderado suplente" },
  { key: "guardian_backup_rut", label: "RUT apoderado suplente" },
  { key: "guardian_backup_phone", label: "Teléfono apoderado suplente" },
  { key: "guardian_backup_email", label: "Correo apoderado suplente" },
  { key: "guardian_backup_address", label: "Domicilio apoderado suplente" },
  { key: "lives_with", label: "Vive con" },
  { key: "siblings_in_school", label: "Hermanas en el colegio" },
  { key: "father_name", label: "Nombre padre" },
  { key: "father_rut", label: "RUT padre" },
  { key: "father_nationality", label: "Nacionalidad padre" },
  { key: "father_address", label: "Domicilio padre" },
  { key: "father_email", label: "Correo padre" },
  { key: "father_occupation", label: "Ocupación padre" },
  { key: "father_phone", label: "Teléfono padre" },
  { key: "father_birthdate", label: "Nacimiento padre" },
  { key: "father_education_level", label: "Escolaridad padre" },
  { key: "mother_name", label: "Nombre madre" },
  { key: "mother_rut", label: "RUT madre" },
  { key: "mother_nationality", label: "Nacionalidad madre" },
  { key: "mother_address", label: "Domicilio madre" },
  { key: "mother_email", label: "Correo madre" },
  { key: "mother_occupation", label: "Ocupación madre" },
  { key: "mother_phone", label: "Teléfono madre" },
  { key: "mother_birthdate", label: "Nacimiento madre" },
  { key: "mother_education_level", label: "Escolaridad madre" },
  { key: "has_repeated_course", label: "Ha repetido curso" },
  { key: "has_internet", label: "Internet en domicilio" },
  { key: "has_computer", label: "Computador en domicilio" },
  { key: "health_insurance", label: "Previsión de salud" },
  { key: "beneficiary_programs", label: "Programas beneficiaria" },
  { key: "scholarships", label: "Becas" },
  { key: "has_judicial_process", label: "Proceso judicial" },
  { key: "has_chronic_illness", label: "Enfermedad crónica" },
  { key: "chronic_illness_details", label: "Detalle enfermedad crónica" },
  { key: "has_medication_allergies", label: "Alergias a medicamentos" },
  { key: "medication_allergies_details", label: "Detalle alergias medicamentos" },
  { key: "has_physical_restrictions", label: "Restricciones físicas" },
  { key: "physical_restrictions_details", label: "Detalle restricciones físicas" },
  { key: "baptism_date", label: "Fecha bautismo" },
  { key: "baptism_place", label: "Lugar bautismo" },
  { key: "first_communion_date", label: "Fecha primera comunión" },
  { key: "first_communion_place", label: "Lugar primera comunión" },
  { key: "confirmation_date", label: "Fecha confirmación" },
  { key: "confirmation_place", label: "Lugar confirmación" },
];

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      exporting: false,
      error: null,
      catalogs: {
        academic_years: [],
        education_levels: [],
        general_statuses: [],
        active_academic_year_id: null,
      },
      courseSections: [],
      students: [],
      filters: {
        academic_year_id: null,
        education_level_id: null,
        course_section_id: null,
        general_status: "activo",
        search: "",
      },
      pagination: { current_page: 1, last_page: 1, total: 0 },
    };
  },
  computed: {
    academicYearOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.academic_years || []).map((year) => ({
          value: year.id,
          text: `${year.name}${year.is_active ? " · activo" : ""}`,
        }))
      );
    },
    levelOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.education_levels || []).map((level) => ({
          value: level.id,
          text: level.name,
        }))
      );
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.general_statuses || []).map((status) => ({
          value: status.value,
          text: status.label,
        }))
      );
    },
    courseOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.courseSections || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadCourses();
    await this.loadStudents();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/students/catalogs");
      this.catalogs = response.data;
      this.filters.academic_year_id = this.catalogs.active_academic_year_id || null;
      this.filters.general_status = this.filters.general_status || "activo";
    },
    exportParams() {
      return {
        academic_year_id: this.filters.academic_year_id,
        education_level_id: this.filters.education_level_id,
        course_section_id: this.filters.course_section_id,
        general_status: this.filters.general_status,
        search: this.filters.search || null,
      };
    },
    async loadCourses() {
      const response = await axios.get("/api/students/courses", {
        params: {
          academic_year_id: this.filters.academic_year_id,
        },
      });
      this.courseSections = response.data.data || [];
    },
    async loadStudents(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/students", {
          params: {
            page,
            ...this.exportParams(),
          },
        });

        this.students = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async onAcademicYearChange() {
      this.filters.course_section_id = null;
      await this.loadCourses();
      this.loadStudents(1);
    },
    resetFilters() {
      this.filters = {
        academic_year_id: this.catalogs.active_academic_year_id || null,
        education_level_id: null,
        course_section_id: null,
        general_status: "activo",
        search: "",
      };
      this.loadCourses().then(() => this.loadStudents(1));
    },
    enrollmentLabel(student) {
      return (
        student.selected_enrollment?.snapshot_course_display_name ||
        student.current_enrollment?.snapshot_course_display_name ||
        "-"
      );
    },
    yearLabel(student) {
      return (
        student.selected_enrollment?.snapshot_year_name ||
        student.current_enrollment?.snapshot_year_name ||
        "-"
      );
    },
    enrollmentStatusLabel(student) {
      return (
        student.selected_enrollment?.enrollment_status ||
        student.current_enrollment?.enrollment_status ||
        "-"
      );
    },
    textValue(value, fallback = "No registra información") {
      if (value === null || value === undefined) {
        return fallback;
      }

      const normalized = typeof value === "string" ? value.trim() : value;
      return normalized === "" ? fallback : normalized;
    },
    boolValue(value) {
      if (value === true) return "Sí";
      if (value === false) return "No";
      return "No registra información";
    },
    formatDate(value) {
      if (!value) return "";
      const normalized = String(value).trim().replace("T", " ");
      const datePart = normalized.split(" ")[0];
      const [year, month, day] = datePart.split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    statusText(value) {
      const found = (this.catalogs.general_statuses || []).find((status) => status.value === value);
      return found?.label || value || "-";
    },
    buildExportRow(student) {
      return {
        registered_name: student.registered_name_resolved || student.registered_name || student.full_name || "-",
        name: student.full_name || "-",
        first_name: student.first_name || "-",
        last_name: student.last_name || "-",
        rut: student.rut || "-",
        birthdate: this.formatDate(student.birthdate) || "-",
        email: student.email || student.user?.email || "-",
        phone: student.phone || "-",
        address: student.address || "-",
        general_status: this.statusText(student.general_status),
        academic_year: this.yearLabel(student),
        course: this.enrollmentLabel(student),
        enrollment_status: this.enrollmentStatusLabel(student),
        observations: this.textValue(student.observations),
        guardian_role: this.textValue(student.guardian_role),
        guardian_name: this.textValue(student.guardian_name),
        guardian_relationship: this.textValue(student.guardian_relationship),
        guardian_rut: this.textValue(student.guardian_rut),
        guardian_phone: this.textValue(student.guardian_phone),
        guardian_email: this.textValue(student.guardian_email),
        guardian_address: this.textValue(student.guardian_address),
        guardian_backup_role: this.textValue(student.guardian_backup_role),
        guardian_backup_name: this.textValue(student.guardian_backup_name),
        guardian_backup_relationship: this.textValue(student.guardian_backup_relationship),
        guardian_backup_rut: this.textValue(student.guardian_backup_rut),
        guardian_backup_phone: this.textValue(student.guardian_backup_phone),
        guardian_backup_email: this.textValue(student.guardian_backup_email),
        guardian_backup_address: this.textValue(student.guardian_backup_address),
        lives_with: this.textValue(student.lives_with),
        siblings_in_school: student.siblings_in_school ?? 0,
        father_name: this.textValue(student.father_name),
        father_rut: this.textValue(student.father_rut),
        father_nationality: this.textValue(student.father_nationality),
        father_address: this.textValue(student.father_address),
        father_email: this.textValue(student.father_email),
        father_occupation: this.textValue(student.father_occupation),
        father_phone: this.textValue(student.father_phone),
        father_birthdate: this.formatDate(student.father_birthdate) || "No registra información",
        father_education_level: this.textValue(student.father_education_level),
        mother_name: this.textValue(student.mother_name),
        mother_rut: this.textValue(student.mother_rut),
        mother_nationality: this.textValue(student.mother_nationality),
        mother_address: this.textValue(student.mother_address),
        mother_email: this.textValue(student.mother_email),
        mother_occupation: this.textValue(student.mother_occupation),
        mother_phone: this.textValue(student.mother_phone),
        mother_birthdate: this.formatDate(student.mother_birthdate) || "No registra información",
        mother_education_level: this.textValue(student.mother_education_level),
        has_repeated_course: this.boolValue(student.has_repeated_course),
        has_internet: this.boolValue(student.has_internet),
        has_computer: this.boolValue(student.has_computer),
        health_insurance: this.textValue(student.health_insurance),
        beneficiary_programs: this.textValue(student.beneficiary_programs),
        scholarships: this.textValue(student.scholarships),
        has_judicial_process: this.boolValue(student.has_judicial_process),
        has_chronic_illness: this.boolValue(student.has_chronic_illness),
        chronic_illness_details: this.textValue(student.chronic_illness_details),
        has_medication_allergies: this.boolValue(student.has_medication_allergies),
        medication_allergies_details: this.textValue(student.medication_allergies_details),
        has_physical_restrictions: this.boolValue(student.has_physical_restrictions),
        physical_restrictions_details: this.textValue(student.physical_restrictions_details),
        baptism_date: this.formatDate(student.baptism_date) || "No registra información",
        baptism_place: this.textValue(student.baptism_place),
        first_communion_date: this.formatDate(student.first_communion_date) || "No registra información",
        first_communion_place: this.textValue(student.first_communion_place),
        confirmation_date: this.formatDate(student.confirmation_date) || "No registra información",
        confirmation_place: this.textValue(student.confirmation_place),
      };
    },
    resolveExportColumns(scope) {
      return scope === "base" ? baseColumns : fullColumns;
    },
    downloadExcel(rows, columns, scope) {
      const headers = columns.map((column) => column.label);
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
                (row) => `<tr>${columns.map((column) => `<td>${escapeHtml(row[column.key])}</td>`).join("")}</tr>`
              )
              .join("")}
          </tbody>
        </table>
      `;

      const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:x="urn:schemas-microsoft-com:office:excel"
              xmlns="http://www.w3.org/TR/REC-html40">
          <head><meta charset="utf-8" /></head>
          <body>${table}</body>
        </html>
      `;

      const blob = new Blob([`\uFEFF${html}`], {
        type: "application/vnd.ms-excel;charset=utf-8;",
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `estudiantes_${scope}_${this.todayStamp()}.xls`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    },
    chunkColumns(columns, size) {
      const chunks = [];

      for (let index = 0; index < columns.length; index += size) {
        chunks.push(columns.slice(index, index + size));
      }

      return chunks;
    },
    softWrap(value) {
      const input = String(value ?? "-");
      const withBreakHints = input.replace(/([@._,;:/\\-])/g, "$1\u200B");

      return withBreakHints.replace(/([^\s\u200B]{18})(?=[^\s\u200B])/g, "$1\u200B");
    },
    downloadPdf(rows, columns, scope) {
      const pdfMake = getPdfMake();
      const activeFilters = [
        this.filters.academic_year_id
          ? `Año: ${this.academicYearOptions.find((item) => item.value === this.filters.academic_year_id)?.text || "-"}`
          : null,
        this.filters.education_level_id
          ? `Nivel: ${this.levelOptions.find((item) => item.value === this.filters.education_level_id)?.text || "-"}`
          : null,
        this.filters.course_section_id
          ? `Curso: ${this.courseOptions.find((item) => item.value === this.filters.course_section_id)?.text || "-"}`
          : null,
        this.filters.general_status
          ? `Estado: ${this.statusOptions.find((item) => item.value === this.filters.general_status)?.text || "-"}`
          : null,
        this.filters.search ? `Búsqueda: ${this.filters.search}` : null,
      ].filter(Boolean);

      const buildTable = (blockColumns) => ({
        table: {
          headerRows: 1,
          widths: blockColumns.map(() => "*"),
          body: [
            blockColumns.map((column) => ({ text: column.label, style: "tableHeader" })),
            ...rows.map((row) =>
              blockColumns.map((column) => ({
                text: this.softWrap(row[column.key] ?? "-"),
                noWrap: false,
              }))
            ),
          ],
        },
        layout: "lightHorizontalLines",
      });

      const content = [
        { text: scope === "base" ? "Listado base de estudiantes" : "Listado completo de estudiantes", style: "title" },
        {
          text: `Generado el ${this.todayStamp()} · Total exportado: ${rows.length} estudiante(s)`,
          style: "muted",
          margin: [0, 4, 0, 8],
        },
        ...(activeFilters.length
          ? [{ text: `Filtros aplicados: ${activeFilters.join(" | ")}`, style: "muted", margin: [0, 0, 0, 8] }]
          : []),
      ];

      if (scope === "base") {
        content.push(buildTable(columns));
      } else {
        this.chunkColumns(columns, 4).forEach((blockColumns, blockIndex, blocks) => {
          content.push({
            text: `Bloque ${blockIndex + 1} de ${blocks.length}`,
            style: "sectionTitle",
            margin: [0, blockIndex === 0 ? 0 : 8, 0, 6],
            pageBreak: blockIndex === 0 ? undefined : "before",
          });
          content.push(buildTable(blockColumns));
        });
      }

      const docDefinition = {
        pageSize: "A4",
        pageOrientation: "landscape",
        pageMargins: [18, 28, 18, 28],
        content,
        styles: {
          title: { fontSize: 16, bold: true, color: "#2a3042" },
          sectionTitle: { fontSize: 10, bold: true, color: "#495057" },
          muted: { fontSize: 9, color: "#74788d" },
          tableHeader: { bold: true, fillColor: "#eff2f7", color: "#495057" },
        },
        defaultStyle: {
          fontSize: scope === "base" ? 8 : 7,
        },
      };

      pdfMake.createPdf(docDefinition).download(`estudiantes_${scope}_${this.todayStamp()}.pdf`);
    },
    async exportStudents(format, scope) {
      this.exporting = true;
      this.error = null;

      try {
        const response = await axios.get("/api/students/export", {
          params: this.exportParams(),
        });
        const source = response.data.data || [];
        const rows = source.map((student) => this.buildExportRow(student));
        const columns = this.resolveExportColumns(scope);

        if (format === "pdf") {
          this.downloadPdf(rows, columns, scope);
        } else {
          this.downloadExcel(rows, columns, scope);
        }

        this.showSuccessAlert("Exportación lista", "El archivo fue generado correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.exporting = false;
      }
    },
    todayStamp() {
      return new Date().toISOString().slice(0, 10);
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
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="student-list-page">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Estudiantes</h4>
        <div class="text-muted">Listado académico filtrable por año, curso y estado.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap student-list-toolbar">
        <BButton variant="outline-success" :disabled="exporting" @click="exportStudents('excel', 'full')">
          {{ exporting ? "Exportando..." : "Excel completo" }}
        </BButton>
        <BButton variant="outline-success" :disabled="exporting" @click="exportStudents('excel', 'base')">Excel base</BButton>
        <BButton variant="outline-danger" :disabled="exporting" @click="exportStudents('pdf', 'full')">PDF completo</BButton>
        <BButton variant="outline-danger" :disabled="exporting" @click="exportStudents('pdf', 'base')">PDF base</BButton>
        <router-link to="/students/levels" class="btn btn-outline-secondary">Niveles</router-link>
        <router-link to="/students/academic-years" class="btn btn-outline-secondary">Años</router-link>
        <router-link to="/students/courses" class="btn btn-outline-secondary">Cursos</router-link>
        <router-link to="/students/movements" class="btn btn-outline-secondary">Cambios y retiros</router-link>
        <router-link to="/students/promotions" class="btn btn-outline-secondary">Promoción</router-link>
        <router-link to="/students/new" class="btn btn-primary">Nueva estudiante</router-link>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3 student-list-filters">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Año académico</label>
          <BFormSelect v-model="filters.academic_year_id" :options="academicYearOptions" @change="onAcademicYearChange" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Nivel</label>
          <BFormSelect v-model="filters.education_level_id" :options="levelOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado general</label>
          <BFormSelect v-model="filters.general_status" :options="statusOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Nombre o RUT</label>
          <BFormInput v-model="filters.search" placeholder="Buscar por nombre, RUT o correo" @keyup.enter="loadStudents(1)" />
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2 student-list-filter-actions">
          <BButton variant="primary" @click="loadStudents(1)">Buscar</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <BTable
        :items="students"
        :busy="loading"
        responsive
        :fields="[
          { key: 'full_name', label: 'Estudiante' },
          { key: 'rut', label: 'RUT' },
          { key: 'year', label: 'Año' },
          { key: 'course', label: 'Curso' },
          { key: 'general_status', label: 'Estado' },
          { key: 'account', label: 'Cuenta' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando estudiantes..." compact />
        </template>
        <template #cell(full_name)="{ item }">
          <div class="fw-semibold">{{ item.full_name }}</div>
          <div class="text-muted small">{{ item.email || item.user?.email || "-" }}</div>
        </template>
        <template #cell(year)="{ item }">
          {{ yearLabel(item) }}
        </template>
        <template #cell(course)="{ item }">
          {{ enrollmentLabel(item) }}
        </template>
        <template #cell(general_status)="{ item }">
          <span
            class="student-list-chip"
            :class="{
              'student-list-chip--success': item.general_status === 'activo',
              'student-list-chip--primary': item.general_status === 'egresado',
              'student-list-chip--neutral': !['activo', 'egresado'].includes(item.general_status),
            }"
          >
            <span class="student-list-chip__dot"></span>
            <span>{{ statusText(item.general_status) }}</span>
          </span>
        </template>
        <template #cell(account)="{ item }">
          <span
            class="student-list-chip"
            :class="item.user?.active ? 'student-list-chip--success' : 'student-list-chip--neutral'"
          >
            <span class="student-list-chip__dot"></span>
            <span>{{ item.user?.active ? "Activa" : "Inactiva" }}</span>
          </span>
        </template>
        <template #cell(actions)="{ item }">
          <router-link :to="`/students/${item.id}`" class="btn btn-sm btn-outline-primary student-list-action">Ver ficha</router-link>
        </template>
      </BTable>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">Total: {{ pagination.total }}</div>
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="15"
          @update:model-value="loadStudents"
        />
      </div>
    </BCard>
    </div>
  </Layout>
</template>

<style scoped>
.student-list-page {
  display: block;
}

.student-list-toolbar :deep(.btn),
.student-list-filter-actions :deep(.btn) {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.student-list-filters :deep(.form-control),
.student-list-filters :deep(.form-select) {
  min-height: 44px;
}

.student-list-chip {
  min-width: 92px;
  min-height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  padding: 0.35rem 0.8rem;
  border-radius: 999px;
  font-size: 0.8rem;
  line-height: 1;
  font-weight: 600;
  border: 1px solid transparent;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
}

.student-list-chip__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  flex: 0 0 auto;
  background: currentColor;
  opacity: 0.95;
}

.student-list-chip--success {
  color: #167c59;
  background: linear-gradient(180deg, rgba(52, 195, 143, 0.18) 0%, rgba(52, 195, 143, 0.12) 100%);
  border-color: rgba(52, 195, 143, 0.28);
}

.student-list-chip--primary {
  color: #4f63d8;
  background: linear-gradient(180deg, rgba(85, 110, 230, 0.16) 0%, rgba(85, 110, 230, 0.1) 100%);
  border-color: rgba(85, 110, 230, 0.24);
}

.student-list-chip--neutral {
  color: #6c757d;
  background: linear-gradient(180deg, rgba(116, 120, 141, 0.12) 0%, rgba(116, 120, 141, 0.08) 100%);
  border-color: rgba(116, 120, 141, 0.18);
}

.student-list-action {
  min-height: 31px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>
