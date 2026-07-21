<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { getPdfMake } from "../../utils/pdfmake";

const STUDENT_PDF_CHUNK_BYTES = 896 * 1024;
const STUDENT_PDF_MAX_BYTES = 40 * 1024 * 1024;

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
  { key: "gender", label: "Género" },
  { key: "nationality", label: "Nacionalidad" },
  { key: "email", label: "Correo" },
  { key: "phone", label: "Teléfono" },
  { key: "address", label: "Domicilio" },
  { key: "commune", label: "Comuna" },
  { key: "school_admission_date", label: "Fecha de ingreso" },
  { key: "previous_school", label: "Colegio de procedencia" },
  { key: "emergency_contact_name", label: "Contacto de emergencia" },
  { key: "emergency_contact_phone", label: "Teléfono de emergencia" },
  { key: "religion", label: "Religión" },
  { key: "accepts_religion_classes", label: "Acepta clases de religión" },
  { key: "ethnicity", label: "Etnia" },
  { key: "general_status", label: "Estado general" },
  { key: "academic_year", label: "Año académico" },
  { key: "course", label: "Curso" },
  { key: "enrollment_status", label: "Estado matrícula" },
  { key: "registration_number", label: "Número de matrícula" },
  { key: "observations", label: "Observaciones" },
  { key: "guardian_role", label: "Quién es apoderado titular" },
  { key: "guardian_name", label: "Apoderado titular" },
  { key: "guardian_relationship", label: "Relación apoderado titular" },
  { key: "guardian_rut", label: "RUT apoderado titular" },
  { key: "guardian_passport", label: "Pasaporte apoderado titular" },
  { key: "guardian_phone", label: "Teléfono apoderado titular" },
  { key: "guardian_email", label: "Correo apoderado titular" },
  { key: "guardian_address", label: "Domicilio apoderado titular" },
  { key: "guardian_commune", label: "Comuna apoderado titular" },
  { key: "guardian_photo_authorization", label: "Autoriza fotografía titular" },
  { key: "guardian_pickup_authorization", label: "Autoriza retiro titular" },
  { key: "guardian_marital_status", label: "Estado civil apoderado titular" },
  { key: "guardian_education_level", label: "Nivel educacional apoderado titular" },
  { key: "guardian_last_education_level", label: "Último nivel apoderado titular" },
  { key: "guardian_occupation", label: "Ocupación apoderado titular" },
  { key: "guardian_backup_role", label: "Quién es apoderado suplente" },
  { key: "guardian_backup_name", label: "Apoderado suplente" },
  { key: "guardian_backup_relationship", label: "Relación apoderado suplente" },
  { key: "guardian_backup_rut", label: "RUT apoderado suplente" },
  { key: "guardian_backup_passport", label: "Pasaporte apoderado suplente" },
  { key: "guardian_backup_phone", label: "Teléfono apoderado suplente" },
  { key: "guardian_backup_email", label: "Correo apoderado suplente" },
  { key: "guardian_backup_address", label: "Domicilio apoderado suplente" },
  { key: "guardian_backup_commune", label: "Comuna apoderado suplente" },
  { key: "guardian_backup_photo_authorization", label: "Autoriza fotografía suplente" },
  { key: "guardian_backup_pickup_authorization", label: "Autoriza retiro suplente" },
  { key: "guardian_backup_marital_status", label: "Estado civil apoderado suplente" },
  { key: "guardian_backup_education_level", label: "Nivel educacional apoderado suplente" },
  { key: "guardian_backup_last_education_level", label: "Último nivel apoderado suplente" },
  { key: "guardian_backup_occupation", label: "Ocupación apoderado suplente" },
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
  { key: "height_cm", label: "Estatura (cm)" },
  { key: "weight_kg", label: "Peso (kg)" },
  { key: "blood_type", label: "Grupo sanguíneo" },
  { key: "food_allergies", label: "Alergias a alimentos" },
  { key: "beneficiary_programs", label: "Programas beneficiaria" },
  { key: "scholarships", label: "Becas" },
  { key: "has_judicial_process", label: "Proceso judicial" },
  { key: "has_chronic_illness", label: "Enfermedad crónica" },
  { key: "chronic_illness_details", label: "Detalle enfermedad crónica" },
  { key: "has_medication_allergies", label: "Alergias a medicamentos" },
  { key: "medication_allergies_details", label: "Detalle alergias medicamentos" },
  { key: "contraindicated_medications", label: "Medicamentos contraindicados" },
  { key: "fit_for_physical_education", label: "Apta para Educación Física" },
  { key: "has_private_school_insurance", label: "Seguro escolar privado" },
  { key: "healthcare_provider", label: "Centro de atención de salud" },
  { key: "health_observations", label: "Observaciones de salud" },
  { key: "is_pie_participant", label: "Permanencia PIE" },
  { key: "pie_permanence_type", label: "Tipo permanencia PIE" },
  { key: "pie_diagnosis", label: "Diagnóstico PIE" },
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
      importingPdf: false,
      pdfImportProgress: 0,
      pdfImportCourseId: null,
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
    selectedAcademicYearLabel() {
      return this.optionLabel(this.academicYearOptions, this.filters.academic_year_id);
    },
    selectedLevelLabel() {
      return this.optionLabel(this.levelOptions, this.filters.education_level_id);
    },
    selectedCourseLabel() {
      return this.optionLabel(this.courseOptions, this.filters.course_section_id);
    },
    selectedStatusLabel() {
      return this.optionLabel(this.statusOptions, this.filters.general_status);
    },
    summaryCards() {
      return [
        {
          label: "Resultados",
          value: this.formatNumber(this.pagination.total),
          detail: "estudiantes filtradas",
          icon: "bx-list-ul",
          tone: "primary",
        },
        {
          label: "Año académico",
          value: this.selectedAcademicYearLabel,
          detail: "periodo de consulta",
          icon: "bx-calendar",
          tone: "info",
        },
        {
          label: "Curso",
          value: this.selectedCourseLabel,
          detail: this.filters.education_level_id ? `Nivel: ${this.selectedLevelLabel}` : "todos los niveles",
          icon: "bx-book-open",
          tone: "success",
        },
        {
          label: "Estado",
          value: this.selectedStatusLabel,
          detail: this.filters.search ? `Búsqueda: ${this.filters.search}` : "filtro vigente",
          icon: "bx-check-shield",
          tone: "neutral",
        },
      ];
    },
    paginationRange() {
      const total = Number(this.pagination.total || 0);

      if (!total) {
        return "Sin resultados";
      }

      const perPage = 15;
      const currentPage = Number(this.pagination.current_page || 1);
      const from = (currentPage - 1) * perPage + 1;
      const to = Math.min(currentPage * perPage, total);

      return `${this.formatNumber(from)}-${this.formatNumber(to)} de ${this.formatNumber(total)}`;
    },
    pdfImportButtonLabel() {
      if (!this.importingPdf) {
        return "Importar PDF";
      }

      return this.pdfImportProgress < 100
        ? `Subiendo ${this.pdfImportProgress}%`
        : "Procesando...";
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
    courseImportLabel(course) {
      const year = course.academic_year?.name || course.academic_year?.year;

      return year ? `${course.display_name} · ${year}` : course.display_name;
    },
    async openPdfImporter() {
      this.error = null;

      try {
        await this.loadCourses();
      } catch (error) {
        this.error = this.formatError(error);
        await this.showErrorAlert(this.error);
        return;
      }

      const availableCourses = this.courseSections.filter((course) => course.active !== false);
      if (!availableCourses.length) {
        await this.showErrorAlert("No hay cursos activos disponibles para el año académico seleccionado.");
        return;
      }

      const inputOptions = Object.fromEntries(
        availableCourses.map((course) => [String(course.id), this.courseImportLabel(course)])
      );
      const suggestedCourseId = availableCourses.some(
        (course) => Number(course.id) === Number(this.filters.course_section_id)
      )
        ? String(this.filters.course_section_id)
        : "";
      const selection = await Swal.fire({
        title: "Curso de destino",
        text: "Todas las estudiantes del PDF quedarán matriculadas en el curso seleccionado.",
        input: "select",
        inputOptions,
        inputValue: suggestedCourseId,
        inputPlaceholder: "Selecciona un curso",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Continuar",
        cancelButtonText: "Cancelar",
        inputValidator: (value) => (!value ? "Debes seleccionar un curso." : undefined),
      });

      if (!selection.isConfirmed) {
        this.pdfImportCourseId = null;
        return;
      }

      this.pdfImportCourseId = Number(selection.value);
      if (this.$refs.studentPdfInput) {
        this.$refs.studentPdfInput.value = "";
        this.$refs.studentPdfInput.click();
      }
    },
    createPdfUploadId() {
      if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
      }

      return `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
    },
    async uploadStudentsPdfInChunks(file, targetCourse) {
      const chunkTotal = Math.ceil(file.size / STUDENT_PDF_CHUNK_BYTES);
      const uploadId = this.createPdfUploadId();
      let response = null;

      for (let chunkIndex = 0; chunkIndex < chunkTotal; chunkIndex += 1) {
        const start = chunkIndex * STUDENT_PDF_CHUNK_BYTES;
        const chunk = file.slice(start, Math.min(start + STUDENT_PDF_CHUNK_BYTES, file.size));

        this.pdfImportProgress = chunkIndex === chunkTotal - 1
          ? 100
          : Math.round((chunkIndex / chunkTotal) * 100);
        response = await axios.post("/api/students/import-pdf/chunk", chunk, {
          params: {
            upload_id: uploadId,
            chunk_index: chunkIndex,
            chunk_total: chunkTotal,
            file_name: file.name,
            file_size: file.size,
            course_section_id: targetCourse.id,
          },
          headers: {
            "Content-Type": "application/octet-stream",
          },
        });
      }

      this.pdfImportProgress = 100;

      return response;
    },
    async importStudentsPdf(event) {
      const input = event.target;
      const file = input.files?.[0];

      if (!file) {
        this.pdfImportCourseId = null;
        return;
      }

      const isPdf = file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf");
      if (!isPdf) {
        this.pdfImportCourseId = null;
        input.value = "";
        await this.showErrorAlert("Selecciona un archivo PDF válido.");
        return;
      }

      if (file.size === 0) {
        this.pdfImportCourseId = null;
        input.value = "";
        await this.showErrorAlert("El PDF seleccionado está vacío.");
        return;
      }

      if (file.size > STUDENT_PDF_MAX_BYTES) {
        this.pdfImportCourseId = null;
        input.value = "";
        await this.showErrorAlert("El libro PDF no puede superar los 40 MB.");
        return;
      }

      const targetCourse = this.courseSections.find(
        (course) => Number(course.id) === Number(this.pdfImportCourseId)
      );

      if (!targetCourse) {
        this.pdfImportCourseId = null;
        input.value = "";
        await this.showErrorAlert("Debes seleccionar nuevamente el curso de destino.");
        return;
      }

      const confirmation = await Swal.fire({
        title: "Importar ficha PDF",
        text: `Se importará ${file.name} y todas las estudiantes quedarán matriculadas en ${this.courseImportLabel(targetCourse)}.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Importar",
        cancelButtonText: "Cancelar",
      });

      if (!confirmation.isConfirmed) {
        this.pdfImportCourseId = null;
        input.value = "";
        return;
      }

      this.importingPdf = true;
      this.pdfImportProgress = 0;
      this.error = null;

      try {
        const response = await this.uploadStudentsPdfInChunks(file, targetCourse);
        const result = response.data.data || {};

        await this.loadCatalogs();
        await this.loadCourses();
        await this.loadStudents(1);

        const issues = Number(result.errors?.length || 0) + Number(result.warnings?.length || 0);
        await Swal.fire({
          title: issues ? "Importación finalizada con observaciones" : "Importación finalizada",
          text: `${result.created || 0} creadas, ${result.updated || 0} actualizadas y ${result.enrollments || 0} matrículas procesadas.${issues ? ` ${issues} registro(s) requieren revisión.` : ""}`,
          icon: issues ? "warning" : "success",
        });
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.importingPdf = false;
        this.pdfImportProgress = 0;
        this.pdfImportCourseId = null;
        input.value = "";
      }
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
    optionLabel(options, value, fallback = "Todos") {
      const option = (options || []).find((item) => item.value === value);

      return option?.text || fallback;
    },
    formatNumber(value) {
      return Number(value || 0).toLocaleString("es-CL");
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
        gender: this.textValue(student.gender),
        nationality: this.textValue(student.nationality),
        email: student.email || student.user?.email || "-",
        phone: student.phone || "-",
        address: student.address || "-",
        commune: this.textValue(student.commune),
        school_admission_date: this.formatDate(student.school_admission_date) || "No registra información",
        previous_school: this.textValue(student.previous_school),
        emergency_contact_name: this.textValue(student.emergency_contact_name),
        emergency_contact_phone: this.textValue(student.emergency_contact_phone),
        religion: this.textValue(student.religion),
        accepts_religion_classes: this.boolValue(student.accepts_religion_classes),
        ethnicity: this.textValue(student.ethnicity),
        general_status: this.statusText(student.general_status),
        academic_year: this.yearLabel(student),
        course: this.enrollmentLabel(student),
        enrollment_status: this.enrollmentStatusLabel(student),
        registration_number: this.textValue(
          student.selected_enrollment?.registration_number || student.current_enrollment?.registration_number
        ),
        observations: this.textValue(student.observations),
        guardian_role: this.textValue(student.guardian_role),
        guardian_name: this.textValue(student.guardian_name),
        guardian_relationship: this.textValue(student.guardian_relationship),
        guardian_rut: this.textValue(student.guardian_rut),
        guardian_passport: this.textValue(student.guardian_passport),
        guardian_phone: this.textValue(student.guardian_phone),
        guardian_email: this.textValue(student.guardian_email),
        guardian_address: this.textValue(student.guardian_address),
        guardian_commune: this.textValue(student.guardian_commune),
        guardian_photo_authorization: this.boolValue(student.guardian_photo_authorization),
        guardian_pickup_authorization: this.boolValue(student.guardian_pickup_authorization),
        guardian_marital_status: this.textValue(student.guardian_marital_status),
        guardian_education_level: this.textValue(student.guardian_education_level),
        guardian_last_education_level: this.textValue(student.guardian_last_education_level),
        guardian_occupation: this.textValue(student.guardian_occupation),
        guardian_backup_role: this.textValue(student.guardian_backup_role),
        guardian_backup_name: this.textValue(student.guardian_backup_name),
        guardian_backup_relationship: this.textValue(student.guardian_backup_relationship),
        guardian_backup_rut: this.textValue(student.guardian_backup_rut),
        guardian_backup_passport: this.textValue(student.guardian_backup_passport),
        guardian_backup_phone: this.textValue(student.guardian_backup_phone),
        guardian_backup_email: this.textValue(student.guardian_backup_email),
        guardian_backup_address: this.textValue(student.guardian_backup_address),
        guardian_backup_commune: this.textValue(student.guardian_backup_commune),
        guardian_backup_photo_authorization: this.boolValue(student.guardian_backup_photo_authorization),
        guardian_backup_pickup_authorization: this.boolValue(student.guardian_backup_pickup_authorization),
        guardian_backup_marital_status: this.textValue(student.guardian_backup_marital_status),
        guardian_backup_education_level: this.textValue(student.guardian_backup_education_level),
        guardian_backup_last_education_level: this.textValue(student.guardian_backup_last_education_level),
        guardian_backup_occupation: this.textValue(student.guardian_backup_occupation),
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
        height_cm: this.textValue(student.height_cm),
        weight_kg: this.textValue(student.weight_kg),
        blood_type: this.textValue(student.blood_type),
        food_allergies: this.textValue(student.food_allergies),
        beneficiary_programs: this.textValue(student.beneficiary_programs),
        scholarships: this.textValue(student.scholarships),
        has_judicial_process: this.boolValue(student.has_judicial_process),
        has_chronic_illness: this.boolValue(student.has_chronic_illness),
        chronic_illness_details: this.textValue(student.chronic_illness_details),
        has_medication_allergies: this.boolValue(student.has_medication_allergies),
        medication_allergies_details: this.textValue(student.medication_allergies_details),
        contraindicated_medications: this.textValue(student.contraindicated_medications),
        fit_for_physical_education: this.boolValue(student.fit_for_physical_education),
        has_private_school_insurance: this.boolValue(student.has_private_school_insurance),
        healthcare_provider: this.textValue(student.healthcare_provider),
        health_observations: this.textValue(student.health_observations),
        is_pie_participant: this.boolValue(student.is_pie_participant),
        pie_permanence_type: this.textValue(student.pie_permanence_type),
        pie_diagnosis: this.textValue(student.pie_diagnosis),
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
      if (error?.response?.status === 413) {
        return "El servidor rechazó el archivo por su tamaño. Actualiza la página e intenta importarlo nuevamente.";
      }

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
      <section class="student-list-header">
        <div class="student-list-heading">
          <div class="student-list-eyebrow">Gestión académica</div>
          <h4>Estudiantes</h4>
          <p>Listado académico filtrable por año, curso, nivel y estado.</p>
        </div>
        <div class="student-list-toolbar">
          <input
            ref="studentPdfInput"
            class="d-none"
            type="file"
            accept="application/pdf,.pdf"
            @change="importStudentsPdf"
          />
          <BButton class="student-list-import" variant="outline-primary" :disabled="importingPdf" @click="openPdfImporter">
            <span class="student-list-button-content">
              <i :class="importingPdf ? 'bx bx-loader-alt bx-spin' : 'bx bx-upload'"></i>
              <span>{{ pdfImportButtonLabel }}</span>
            </span>
          </BButton>
          <BDropdown variant="outline-secondary" menu-class="dropdown-menu-end" :disabled="exporting">
            <template #button-content>
              <span class="student-list-button-content">
                <i :class="exporting ? 'bx bx-loader-alt bx-spin' : 'bx bx-download'"></i>
                <span>{{ exporting ? "Exportando..." : "Exportar" }}</span>
              </span>
            </template>
            <BDropdownItemButton :disabled="exporting" @click="exportStudents('excel', 'full')">
              <i class="bx bx-spreadsheet me-2"></i>Excel completo
            </BDropdownItemButton>
            <BDropdownItemButton :disabled="exporting" @click="exportStudents('excel', 'base')">
              <i class="bx bx-spreadsheet me-2"></i>Excel base
            </BDropdownItemButton>
            <BDropdownItemButton :disabled="exporting" @click="exportStudents('pdf', 'full')">
              <i class="bx bx-file me-2"></i>PDF completo
            </BDropdownItemButton>
            <BDropdownItemButton :disabled="exporting" @click="exportStudents('pdf', 'base')">
              <i class="bx bx-file me-2"></i>PDF base
            </BDropdownItemButton>
          </BDropdown>
          <router-link to="/students/new" class="btn btn-primary student-list-create">
            <i class="bx bx-plus"></i>
            <span>Nueva estudiante</span>
          </router-link>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3 mb-3">
        <div v-for="card in summaryCards" :key="card.label" class="col-md-6 col-xl-3">
          <BCard class="h-100 student-list-stat-card" :class="`student-list-stat-card--${card.tone}`">
            <div class="student-list-stat-icon">
              <i :class="`bx ${card.icon}`"></i>
            </div>
            <div class="student-list-stat-content">
              <span>{{ card.label }}</span>
              <strong>{{ card.value }}</strong>
              <small>{{ card.detail }}</small>
            </div>
          </BCard>
        </div>
      </div>

      <BCard class="mb-3 student-list-filters">
        <div class="row g-2 align-items-end">
          <div class="col-md-6 col-xl-2">
            <label class="form-label">Año académico</label>
            <BFormSelect v-model="filters.academic_year_id" :options="academicYearOptions" @change="onAcademicYearChange" />
          </div>
          <div class="col-md-6 col-xl-2">
            <label class="form-label">Nivel</label>
            <BFormSelect v-model="filters.education_level_id" :options="levelOptions" />
          </div>
          <div class="col-md-6 col-xl-2">
            <label class="form-label">Curso</label>
            <BFormSelect v-model="filters.course_section_id" :options="courseOptions" />
          </div>
          <div class="col-md-6 col-xl-2">
            <label class="form-label">Estado general</label>
            <BFormSelect v-model="filters.general_status" :options="statusOptions" />
          </div>
          <div class="col-md-8 col-xl-3">
            <label class="form-label">Nombre o RUT</label>
            <BFormInput v-model="filters.search" placeholder="Buscar por nombre, RUT o correo" @keyup.enter="loadStudents(1)" />
          </div>
          <div class="col-md-4 col-xl-1">
            <div class="student-list-filter-actions">
              <BButton variant="primary" :disabled="loading" title="Buscar" aria-label="Buscar estudiantes" @click="loadStudents(1)">
                <i class="bx bx-filter-alt"></i>
              </BButton>
              <BButton variant="outline-secondary" :disabled="loading" title="Limpiar" aria-label="Limpiar filtros" @click="resetFilters">
                <i class="bx bx-x"></i>
              </BButton>
            </div>
          </div>
        </div>
      </BCard>

      <BCard class="student-list-results-card">
        <div class="student-list-card-header">
          <div>
            <div class="student-list-eyebrow">Listado</div>
            <h5>Estudiantes registradas</h5>
          </div>
          <div class="student-list-card-meta">{{ paginationRange }}</div>
        </div>
        <BTable
          :items="students"
          :busy="loading"
          responsive
          hover
          show-empty
          table-class="student-list-table align-middle mb-0"
          :fields="[
            { key: 'full_name', label: 'Estudiante', thClass: 'student-col', tdClass: 'student-col' },
            { key: 'rut', label: 'RUT' },
            { key: 'year', label: 'Año' },
            { key: 'course', label: 'Curso' },
            { key: 'general_status', label: 'Estado' },
            { key: 'account', label: 'Cuenta' },
            { key: 'actions', label: 'Acciones', thClass: 'text-end actions-col', tdClass: 'text-end actions-col' },
          ]"
        >
          <template #table-busy>
            <LoadingState message="Cargando estudiantes..." compact />
          </template>
          <template #empty>
            <div class="student-list-empty">
              <i class="bx bx-user-x"></i>
              <strong>No hay estudiantes para mostrar</strong>
              <span>Ajusta los filtros o registra una nueva ficha.</span>
            </div>
          </template>
          <template #cell(full_name)="{ item }">
            <div class="student-list-person">
              <div class="student-list-avatar">{{ String(item.full_name || "?").charAt(0).toUpperCase() }}</div>
              <div>
                <div class="fw-semibold">{{ item.full_name }}</div>
                <div class="text-muted small">{{ item.email || item.user?.email || "-" }}</div>
              </div>
            </div>
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
            <router-link :to="`/students/${item.id}`" class="btn btn-sm btn-outline-primary student-list-action">
              <i class="bx bx-show"></i>
              <span>Ver ficha</span>
            </router-link>
          </template>
        </BTable>

        <div class="student-list-pagination">
          <div class="text-muted small">Total: {{ formatNumber(pagination.total) }}</div>
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
  display: flex;
  flex-direction: column;
  gap: 0;
}

.student-list-header {
  align-items: flex-start;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.student-list-heading h4,
.student-list-card-header h5 {
  color: #2a3042;
  font-weight: 700;
  letter-spacing: 0;
  margin: 0;
}

.student-list-heading p {
  color: #74788d;
  margin: 0.3rem 0 0;
}

.student-list-eyebrow {
  color: #556ee6;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0;
  margin-bottom: 0.2rem;
  text-transform: uppercase;
}

.student-list-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.student-list-button-content,
.student-list-create,
.student-list-filter-actions :deep(.btn),
.student-list-action {
  align-items: center;
  display: inline-flex;
  gap: 0.45rem;
  justify-content: center;
}

.student-list-toolbar :deep(.btn),
.student-list-filter-actions :deep(.btn) {
  min-height: 2.65rem;
}

.student-list-import {
  min-width: 9.75rem;
}

.student-list-stat-card,
.student-list-filters,
.student-list-results-card {
  border: 1px solid #e8edf7;
  box-shadow: 0 10px 26px rgba(18, 38, 63, 0.05);
}

.student-list-stat-card :deep(.card-body) {
  align-items: center;
  display: flex;
  gap: 0.9rem;
  min-height: 5.8rem;
}

.student-list-stat-icon {
  align-items: center;
  border-radius: 8px;
  display: inline-flex;
  flex: 0 0 2.75rem;
  height: 2.75rem;
  justify-content: center;
  width: 2.75rem;
}

.student-list-stat-icon i {
  font-size: 1.45rem;
}

.student-list-stat-content {
  min-width: 0;
}

.student-list-stat-content span,
.student-list-stat-content small {
  color: #74788d;
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.25;
}

.student-list-stat-content strong {
  color: #343a40;
  display: block;
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.student-list-stat-card--primary .student-list-stat-icon {
  background: rgba(85, 110, 230, 0.12);
  color: #556ee6;
}

.student-list-stat-card--info .student-list-stat-icon {
  background: rgba(80, 165, 241, 0.13);
  color: #328bd5;
}

.student-list-stat-card--success .student-list-stat-icon {
  background: rgba(52, 195, 143, 0.13);
  color: #22a978;
}

.student-list-stat-card--neutral .student-list-stat-icon {
  background: #eef2f7;
  color: #495057;
}

.student-list-card-header {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.student-list-card-meta {
  color: #74788d;
  font-size: 0.85rem;
  font-weight: 700;
}

.student-list-filter-actions {
  align-items: center;
  display: flex;
  gap: 0.35rem;
  justify-content: flex-start;
}

.student-list-filters :deep(.card-body) {
  padding: 0.8rem 0.9rem;
}

.student-list-filters .form-label {
  color: #74788d;
  font-size: 0.74rem;
  font-weight: 700;
  line-height: 1.1;
  margin-bottom: 0.22rem;
}

.student-list-filters :deep(.form-control),
.student-list-filters :deep(.form-select) {
  font-size: 0.82rem;
  min-height: 2.15rem;
  padding-bottom: 0.32rem;
  padding-top: 0.32rem;
}

.student-list-filter-actions :deep(.btn) {
  flex: 0 0 2.15rem;
  min-height: 2.15rem;
  padding: 0.32rem;
  width: 2.15rem;
}

.student-list-table :deep(th) {
  background: #f8f9fc;
  border-bottom: 1px solid #e8edf7;
  color: #495057;
  font-size: 0.76rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: uppercase;
}

.student-list-table :deep(td) {
  border-color: #edf1f7;
  vertical-align: middle;
}

.student-list-person {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  min-width: 15rem;
}

.student-list-avatar {
  align-items: center;
  background: #eef2ff;
  border: 1px solid rgba(85, 110, 230, 0.14);
  border-radius: 8px;
  color: #556ee6;
  display: inline-flex;
  flex: 0 0 2.25rem;
  font-weight: 800;
  height: 2.25rem;
  justify-content: center;
  width: 2.25rem;
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
}

.student-list-empty {
  align-items: center;
  color: #74788d;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  justify-content: center;
  min-height: 11rem;
  text-align: center;
}

.student-list-empty i {
  color: #a6b0cf;
  font-size: 2rem;
}

.student-list-empty strong {
  color: #343a40;
}

.student-list-pagination {
  align-items: center;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-top: 1rem;
}

@media (max-width: 991.98px) {
  .student-list-filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 575.98px) {
  .student-list-toolbar,
  .student-list-toolbar :deep(.dropdown),
  .student-list-toolbar :deep(.btn),
  .student-list-create,
  .student-list-filter-actions,
  .student-list-filter-actions :deep(.btn),
  .student-list-pagination {
    width: 100%;
  }

  .student-list-filter-actions,
  .student-list-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .student-list-filter-actions :deep(.btn) {
    flex-basis: auto;
  }

  .student-list-card-header {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.35rem;
  }
}
</style>
