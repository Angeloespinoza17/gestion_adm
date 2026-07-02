<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { getPdfMake } from "../../utils/pdfmake";

const emptyForm = () => ({
  first_name: "",
  last_name: "",
  registered_name: "",
  rut: "",
  birthdate: "",
  email: "",
  phone: "",
  address: "",
  general_status: "activo",
  observations: "",
  pickup_restriction: false,
  pickup_restriction_notes: "",
  porter_alert_notes: "",
  authorized_pickup_people: [],
  tardiness_semester_one_notes: "",
  absence_notes: "",
  guardian_name: "",
  guardian_relationship: "",
  guardian_role: "",
  guardian_rut: "",
  guardian_phone: "",
  guardian_address: "",
  guardian_email: "",
  guardian_backup_name: "",
  guardian_backup_relationship: "",
  guardian_backup_role: "",
  guardian_backup_rut: "",
  guardian_backup_address: "",
  guardian_backup_phone: "",
  guardian_backup_email: "",
  lives_with: "",
  siblings_in_school: "",
  father_name: "",
  father_rut: "",
  father_nationality: "",
  father_address: "",
  father_email: "",
  father_occupation: "",
  father_phone: "",
  father_birthdate: "",
  father_education_level: "",
  mother_name: "",
  mother_rut: "",
  mother_nationality: "",
  mother_address: "",
  mother_email: "",
  mother_occupation: "",
  mother_phone: "",
  mother_birthdate: "",
  mother_education_level: "",
  has_repeated_course: null,
  has_internet: null,
  has_computer: null,
  health_insurance: "",
  beneficiary_programs: "",
  scholarships: "",
  has_judicial_process: null,
  has_chronic_illness: null,
  chronic_illness_details: "",
  has_medication_allergies: null,
  medication_allergies_details: "",
  has_physical_restrictions: null,
  physical_restrictions_details: "",
  baptism_date: "",
  baptism_place: "",
  first_communion_date: "",
  first_communion_place: "",
  confirmation_date: "",
  confirmation_place: "",
  account_active: true,
  password: "",
});

const emptyEnrollmentForm = () => ({
  id: null,
  academic_year_id: null,
  course_section_id: null,
  enrollment_status: "matriculada",
  enrolled_at: "",
  withdrawn_at: "",
  observations: "",
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      savingEnrollment: false,
      exportingPdf: false,
      error: null,
      catalogs: {
        academic_years: [],
        education_levels: [],
        general_statuses: [],
        enrollment_statuses: [],
      },
      student: null,
      form: emptyForm(),
      fatherImportSource: "",
      motherImportSource: "",
      showEnrollmentModal: false,
      enrollmentForm: emptyEnrollmentForm(),
      enrollmentCourseSections: [],
    };
  },
  computed: {
    isNew() {
      return this.$route.path === "/students/new";
    },
    itemId() {
      return this.$route.params.id;
    },
    currentEnrollment() {
      return this.student?.current_enrollment || null;
    },
    latestEnrollment() {
      return this.student?.latest_enrollment || this.student?.enrollments?.[0] || null;
    },
    statusOptions() {
      return (this.catalogs.general_statuses || []).map((status) => ({
        value: status.value,
        text: status.label,
      }));
    },
    enrollmentStatusOptions() {
      return (this.catalogs.enrollment_statuses || []).map((status) => ({
        value: status.value,
        text: status.label,
      }));
    },
    academicYearOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.catalogs.academic_years || []).map((year) => ({
          value: year.id,
          text: `${year.name}${year.is_active ? " · activo" : ""}`,
        }))
      );
    },
    enrollmentCourseOptions() {
      return [{ value: null, text: "Seleccionar..." }].concat(
        (this.enrollmentCourseSections || []).map((course) => ({
          value: course.id,
          text: course.display_name,
        }))
      );
    },
    booleanOptions() {
      return [
        { value: null, text: "No registra información" },
        { value: true, text: "Sí" },
        { value: false, text: "No" },
      ];
    },
    parentImportOptions() {
      return [
        { value: null, text: "Seleccionar..." },
        { value: "guardian", text: "Apoderado titular" },
        { value: "guardian_backup", text: "Apoderado suplente" },
      ];
    },
    generatedPlatformEmail() {
      if (this.student?.user?.email) {
        return this.student.user.email;
      }

      return this.buildGeneratedPlatformEmail();
    },
    generatedPlatformPassword() {
      return this.form.rut || "Se generará temporalmente";
    },
  },
  async mounted() {
    await this.load();
    if (this.$route.query.created) {
      this.showSuccessAlert("Estudiante creada", "La estudiante fue creada correctamente.");
    }
    if (this.$route.query.saved) {
      this.showSuccessAlert("Cambios guardados", "La ficha fue actualizada correctamente.");
    }
  },
  methods: {
    hydrateForm(student = null) {
      this.fatherImportSource = null;
      this.motherImportSource = null;

      if (!student) {
        this.form = emptyForm();
        return;
      }

      this.form = {
        first_name: student.first_name || "",
        last_name: student.last_name || "",
        registered_name: student.registered_name || student.registered_name_resolved || "",
        rut: student.rut || "",
        birthdate: student.birthdate || "",
        email: student.email || "",
        phone: student.phone || "",
        address: student.address || "",
        general_status: student.general_status || "activo",
        observations: student.observations || "",
        pickup_restriction: Boolean(student.pickup_restriction),
        pickup_restriction_notes: student.pickup_restriction_notes || "",
        porter_alert_notes: student.porter_alert_notes || "",
        authorized_pickup_people: Array.isArray(student.authorized_pickup_people)
          ? student.authorized_pickup_people.map((person) => ({
              name: person.name || "",
              rut: person.rut || "",
              relationship: person.relationship || "",
              phone: person.phone || "",
            }))
          : [],
        tardiness_semester_one_notes: student.tardiness_semester_one_notes || "",
        absence_notes: student.absence_notes || "",
        guardian_name: student.guardian_name || "",
        guardian_relationship: student.guardian_relationship || "",
        guardian_role: student.guardian_role || "",
        guardian_rut: student.guardian_rut || "",
        guardian_phone: student.guardian_phone || "",
        guardian_address: student.guardian_address || "",
        guardian_email: student.guardian_email || "",
        guardian_backup_name: student.guardian_backup_name || "",
        guardian_backup_relationship: student.guardian_backup_relationship || "",
        guardian_backup_role: student.guardian_backup_role || "",
        guardian_backup_rut: student.guardian_backup_rut || "",
        guardian_backup_address: student.guardian_backup_address || "",
        guardian_backup_phone: student.guardian_backup_phone || "",
        guardian_backup_email: student.guardian_backup_email || "",
        lives_with: student.lives_with || "",
        siblings_in_school: student.siblings_in_school ?? "",
        father_name: student.father_name || "",
        father_rut: student.father_rut || "",
        father_nationality: student.father_nationality || "",
        father_address: student.father_address || "",
        father_email: student.father_email || "",
        father_occupation: student.father_occupation || "",
        father_phone: student.father_phone || "",
        father_birthdate: student.father_birthdate || "",
        father_education_level: student.father_education_level || "",
        mother_name: student.mother_name || "",
        mother_rut: student.mother_rut || "",
        mother_nationality: student.mother_nationality || "",
        mother_address: student.mother_address || "",
        mother_email: student.mother_email || "",
        mother_occupation: student.mother_occupation || "",
        mother_phone: student.mother_phone || "",
        mother_birthdate: student.mother_birthdate || "",
        mother_education_level: student.mother_education_level || "",
        has_repeated_course: student.has_repeated_course ?? null,
        has_internet: student.has_internet ?? null,
        has_computer: student.has_computer ?? null,
        health_insurance: student.health_insurance || "",
        beneficiary_programs: student.beneficiary_programs || "",
        scholarships: student.scholarships || "",
        has_judicial_process: student.has_judicial_process ?? null,
        has_chronic_illness: student.has_chronic_illness ?? null,
        chronic_illness_details: student.chronic_illness_details || "",
        has_medication_allergies: student.has_medication_allergies ?? null,
        medication_allergies_details: student.medication_allergies_details || "",
        has_physical_restrictions: student.has_physical_restrictions ?? null,
        physical_restrictions_details: student.physical_restrictions_details || "",
        baptism_date: student.baptism_date || "",
        baptism_place: student.baptism_place || "",
        first_communion_date: student.first_communion_date || "",
        first_communion_place: student.first_communion_place || "",
        confirmation_date: student.confirmation_date || "",
        confirmation_place: student.confirmation_place || "",
        account_active: Boolean(student.user?.active),
        password: "",
      };
    },
    firstToken(value) {
      return String(value || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean)[0] || "";
    },
    normalizeEmailPart(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "");
    },
    buildGeneratedPlatformEmail() {
      const firstName = this.normalizeEmailPart(this.firstToken(this.form.first_name));
      const lastName = this.normalizeEmailPart(this.firstToken(this.form.last_name));
      const base = [firstName, lastName].filter(Boolean).join(".");

      if (!base) {
        return this.form.rut ? `${String(this.form.rut).replace(/[.\-]/g, "").toLowerCase()}@cnscvaldivia.cl` : "Se generará al guardar";
      }

      return `${base}@cnscvaldivia.cl`;
    },
    importSourcePayload(source) {
      if (source === "guardian") {
        return {
          name: this.form.guardian_name,
          rut: this.form.guardian_rut,
          address: this.form.guardian_address,
          email: this.form.guardian_email,
          phone: this.form.guardian_phone,
        };
      }

      if (source === "guardian_backup") {
        return {
          name: this.form.guardian_backup_name,
          rut: this.form.guardian_backup_rut,
          address: this.form.guardian_backup_address,
          email: this.form.guardian_backup_email,
          phone: this.form.guardian_backup_phone,
        };
      }

      return null;
    },
    applyParentImport(target, source) {
      const payload = this.importSourcePayload(source);

      if (!payload) {
        return;
      }

      this.form[`${target}_name`] = payload.name || "";
      this.form[`${target}_rut`] = payload.rut || "";
      this.form[`${target}_address`] = payload.address || "";
      this.form[`${target}_email`] = payload.email || "";
      this.form[`${target}_phone`] = payload.phone || "";

      if (target === "father") {
        this.fatherImportSource = null;
      } else {
        this.motherImportSource = null;
      }
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
      if (!value) return "No registra información";
      const normalized = String(value).trim().replace("T", " ");
      const datePart = normalized.split(" ")[0];
      const [year, month, day] = datePart.split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    softWrap(value) {
      const input = String(value ?? "-");
      const withBreakHints = input.replace(/([@._,;:/\\-])/g, "$1\u200B");

      return withBreakHints.replace(/([^\s\u200B]{18})(?=[^\s\u200B])/g, "$1\u200B");
    },
    buildPdfSection(title, rows) {
      return [
        { text: title, style: "sectionTitle" },
        {
          table: {
            widths: [180, "*"],
            body: rows.map(([label, value]) => [
              { text: String(label), style: "tableHeader" },
              { text: this.softWrap(value ?? "-"), noWrap: false },
            ]),
          },
          layout: "lightHorizontalLines",
          margin: [0, 0, 0, 10],
        },
      ];
    },
    buildPdfRecordBlocks(title, records, mapper, emptyMessage, itemLabel = "Registro") {
      if (!records.length) {
        return [
          { text: title, style: "sectionTitle" },
          { text: emptyMessage, style: "muted", margin: [0, 0, 0, 10] },
        ];
      }

      return [
        { text: title, style: "sectionTitle" },
        ...records.flatMap((record, index) => [
          {
            text: `${itemLabel} ${index + 1}`,
            style: "subsectionTitle",
            margin: [0, index === 0 ? 0 : 6, 0, 4],
          },
          {
            table: {
              widths: [160, "*"],
              body: mapper(record).map(([label, value]) => [
                { text: String(label), style: "tableHeader" },
                { text: this.softWrap(value ?? "-"), noWrap: false },
              ]),
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 8],
          },
        ]),
      ];
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const requests = [axios.get("/api/students/catalogs")];
        if (!this.isNew) {
          requests.push(axios.get(`/api/students/${this.itemId}`));
        }

        const responses = await Promise.all(requests);
        this.catalogs = responses[0].data;

        if (!this.isNew) {
          this.student = responses[1].data.data;
          this.hydrateForm(this.student);
        } else {
          this.hydrateForm();
        }
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.isNew) {
          const response = await axios.post("/api/students", this.form);
          this.$router.push(`/students/${response.data.data.id}?created=1`);
          return;
        }

        await axios.put(`/api/students/${this.itemId}`, this.form);
        await this.load();
        this.showSuccessAlert("Cambios guardados", "La ficha fue actualizada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    async openEnrollmentModal(enrollment = null) {
      this.enrollmentForm = enrollment
        ? {
            id: enrollment.id,
            academic_year_id: enrollment.academic_year_id,
            course_section_id: enrollment.course_section_id,
            enrollment_status: enrollment.enrollment_status,
            enrolled_at: enrollment.enrolled_at || "",
            withdrawn_at: enrollment.withdrawn_at || "",
            observations: enrollment.observations || "",
          }
        : {
            ...emptyEnrollmentForm(),
            academic_year_id: this.catalogs.active_academic_year_id || null,
            enrolled_at: new Date().toISOString().slice(0, 10),
          };

      await this.loadEnrollmentCourses();
      this.showEnrollmentModal = true;
    },
    async loadEnrollmentCourses() {
      if (!this.enrollmentForm.academic_year_id) {
        this.enrollmentCourseSections = [];
        return;
      }

      const response = await axios.get("/api/students/courses", {
        params: { academic_year_id: this.enrollmentForm.academic_year_id },
      });
      this.enrollmentCourseSections = response.data.data || [];
    },
    async saveEnrollment() {
      this.savingEnrollment = true;
      this.error = null;
      try {
        if (this.enrollmentForm.id) {
          await axios.put(`/api/students/enrollments/${this.enrollmentForm.id}`, this.enrollmentForm);
        } else {
          await axios.post(`/api/students/${this.itemId}/enrollments`, this.enrollmentForm);
        }

        this.showEnrollmentModal = false;
        await this.load();
        this.showSuccessAlert("Matrícula guardada", "La matrícula anual fue actualizada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.savingEnrollment = false;
      }
    },
    async exportPdf() {
      if (!this.student) {
        return;
      }

      this.exportingPdf = true;
      this.error = null;

      try {
        const pdfMake = getPdfMake();
        const personalRows = [
          ["Nombre registral", this.textValue(this.form.registered_name || this.student?.registered_name_resolved)],
          ["Nombres", this.textValue(this.form.first_name)],
          ["Apellidos", this.textValue(this.form.last_name)],
          ["RUT", this.textValue(this.form.rut)],
          ["Correo", this.textValue(this.form.email || this.student?.user?.email)],
          ["Fecha de nacimiento", this.formatDate(this.form.birthdate)],
          ["Domicilio", this.textValue(this.form.address)],
          ["Curso actual", this.textValue(this.currentEnrollment?.snapshot_course_display_name, "-")],
          ["Última matrícula", this.textValue(this.latestEnrollment?.snapshot_year_name, "-")],
          ["Estado general", this.textValue(this.form.general_status, "-")],
          ["Cuenta plataforma", this.textValue(this.generatedPlatformEmail, "-")],
          ["Observaciones", this.textValue(this.form.observations)],
        ];

        const guardianRows = [
          ["Quién es el apoderado", this.textValue(this.form.guardian_role)],
          ["Nombre completo apoderado", this.textValue(this.form.guardian_name)],
          ["Relación apoderado", this.textValue(this.form.guardian_relationship)],
          ["RUT apoderado", this.textValue(this.form.guardian_rut)],
          ["Domicilio apoderado", this.textValue(this.form.guardian_address)],
          ["Correo apoderado", this.textValue(this.form.guardian_email)],
          ["Teléfono apoderado", this.textValue(this.form.guardian_phone)],
          ["Quién es el apoderado suplente", this.textValue(this.form.guardian_backup_role)],
          ["Nombre completo apoderado suplente", this.textValue(this.form.guardian_backup_name)],
          ["Relación apoderado suplente", this.textValue(this.form.guardian_backup_relationship)],
          ["RUT apoderado suplente", this.textValue(this.form.guardian_backup_rut)],
          ["Domicilio apoderado suplente", this.textValue(this.form.guardian_backup_address)],
          ["Correo apoderado suplente", this.textValue(this.form.guardian_backup_email)],
          ["Teléfono apoderado suplente", this.textValue(this.form.guardian_backup_phone)],
        ];

        const familyRows = [
          ["Vive con", this.textValue(this.form.lives_with)],
          ["Número de hermanas en el colegio", this.textValue(this.form.siblings_in_school, "0")],
        ];

        const fatherRows = [
          ["Nombre padre", this.textValue(this.form.father_name)],
          ["RUT padre", this.textValue(this.form.father_rut)],
          ["Nacionalidad padre", this.textValue(this.form.father_nationality)],
          ["Domicilio padre", this.textValue(this.form.father_address)],
          ["Correo padre", this.textValue(this.form.father_email)],
          ["Ocupación padre", this.textValue(this.form.father_occupation)],
          ["Teléfono padre", this.textValue(this.form.father_phone)],
          ["Nacimiento padre", this.formatDate(this.form.father_birthdate)],
          ["Escolaridad padre", this.textValue(this.form.father_education_level)],
        ];

        const motherRows = [
          ["Nombre madre", this.textValue(this.form.mother_name)],
          ["RUT madre", this.textValue(this.form.mother_rut)],
          ["Nacionalidad madre", this.textValue(this.form.mother_nationality)],
          ["Domicilio madre", this.textValue(this.form.mother_address)],
          ["Correo madre", this.textValue(this.form.mother_email)],
          ["Ocupación madre", this.textValue(this.form.mother_occupation)],
          ["Teléfono madre", this.textValue(this.form.mother_phone)],
          ["Nacimiento madre", this.formatDate(this.form.mother_birthdate)],
          ["Escolaridad madre", this.textValue(this.form.mother_education_level)],
        ];

        const socialRows = [
          ["Ha repetido curso", this.boolValue(this.form.has_repeated_course)],
          ["Internet en domicilio", this.boolValue(this.form.has_internet)],
          ["Computador en domicilio", this.boolValue(this.form.has_computer)],
          ["Previsión de salud", this.textValue(this.form.health_insurance)],
          ["Programas beneficiaria", this.textValue(this.form.beneficiary_programs)],
          ["Becas", this.textValue(this.form.scholarships)],
          ["Proceso judicial", this.boolValue(this.form.has_judicial_process)],
        ];

        const medicalRows = [
          ["Enfermedad crónica", this.boolValue(this.form.has_chronic_illness)],
          ["Detalle enfermedad crónica", this.textValue(this.form.chronic_illness_details)],
          ["Alergias a medicamentos", this.boolValue(this.form.has_medication_allergies)],
          ["Detalle alergias medicamentos", this.textValue(this.form.medication_allergies_details)],
          ["Restricciones físicas", this.boolValue(this.form.has_physical_restrictions)],
          ["Detalle restricciones físicas", this.textValue(this.form.physical_restrictions_details)],
        ];
        const sacramentRows = [
          ["Fecha bautismo", this.formatDate(this.form.baptism_date)],
          ["Lugar bautismo", this.textValue(this.form.baptism_place)],
          ["Fecha primera comunión", this.formatDate(this.form.first_communion_date)],
          ["Lugar primera comunión", this.textValue(this.form.first_communion_place)],
          ["Fecha confirmación", this.formatDate(this.form.confirmation_date)],
          ["Lugar confirmación", this.textValue(this.form.confirmation_place)],
        ];
        const historyContent = this.buildPdfRecordBlocks(
          "Historial académico anual",
          this.student?.enrollments || [],
          (item) => [
            ["Año", item.snapshot_year_name || "-"],
            ["Curso", item.snapshot_course_display_name || "-"],
            ["Estado", item.enrollment_status || "-"],
            ["Fecha matrícula", this.formatDate(item.enrolled_at)],
            ["Fecha retiro", this.formatDate(item.withdrawn_at)],
            ["Observaciones", item.observations || "-"],
          ],
          "Sin historial académico registrado.",
          "Matrícula anual",
        );

        const promotionContent = this.buildPdfRecordBlocks(
          "Promociones registradas",
          this.student?.promotions || [],
          (item) => [
            ["Origen", `${item.from_academic_year?.name || "-"} · ${item.from_course_section?.display_name || "-"}`],
            ["Destino", `${item.to_academic_year?.name || "-"} · ${item.to_course_section?.display_name || "-"}`],
            ["Resultado", item.promotion_status || "-"],
            ["Notas", item.notes || "-"],
          ],
          "Sin promociones registradas.",
          "Promoción",
        );

        const movementContent = this.buildPdfRecordBlocks(
          "Movimientos de matrícula",
          this.student?.enrollment_movements || [],
          (item) => [
            ["Año", item.snapshot_year_name || "-"],
            ["Tipo", item.movement_type || "-"],
            ["Desde", item.snapshot_from_course_display_name || "-"],
            ["Hacia", item.snapshot_to_course_display_name || "-"],
            ["Fecha", this.formatDate(item.effective_date)],
            ["Estado origen", item.from_status || "-"],
            ["Estado destino", item.to_status || "-"],
            ["Notas", item.notes || "-"],
          ],
          "Sin movimientos de matrícula registrados.",
          "Movimiento",
        );

        const docDefinition = {
          pageSize: "A4",
          pageMargins: [30, 36, 30, 36],
          content: [
            { text: "Ficha de estudiante", style: "title" },
            { text: this.textValue(this.form.registered_name || this.student?.registered_name_resolved), style: "subtitle" },
            { text: `${this.textValue(this.form.rut)} · ${this.textValue(this.currentEnrollment?.snapshot_course_display_name, "-")}`, style: "muted", margin: [0, 2, 0, 10] },
            ...this.buildPdfSection("Datos personales", personalRows),
            ...this.buildPdfSection("Antecedentes apoderados", guardianRows),
            ...this.buildPdfSection("Antecedentes familiares", familyRows),
            ...this.buildPdfSection("Antecedentes del padre", fatherRows),
            ...this.buildPdfSection("Antecedentes de la madre", motherRows),
            ...this.buildPdfSection("Antecedentes escolares y sociales", socialRows),
            ...this.buildPdfSection("Antecedentes médicos", medicalRows),
            ...this.buildPdfSection("Sacramentos", sacramentRows),
            ...historyContent,
            ...promotionContent,
            ...movementContent,
          ],
          styles: {
            title: { fontSize: 18, bold: true, color: "#2a3042" },
            subtitle: { fontSize: 12, bold: true, color: "#495057" },
            sectionTitle: { fontSize: 11, bold: true, color: "#495057", margin: [0, 8, 0, 6] },
            subsectionTitle: { fontSize: 10, bold: true, color: "#495057" },
            tableHeader: { bold: true, fillColor: "#eff2f7", color: "#495057" },
            muted: { fontSize: 9, color: "#74788d" },
          },
          defaultStyle: {
            fontSize: 9,
          },
        };

        pdfMake
          .createPdf(docDefinition)
          .download(`estudiante_${(this.form.rut || this.itemId || "ficha").toString().replace(/\s+/g, "_")}.pdf`);
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error || "No fue posible generar el PDF.");
      } finally {
        this.exportingPdf = false;
      }
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
    <div class="student-record-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">{{ isNew ? "Nueva estudiante" : "Ficha de estudiante" }}</h4>
        <div class="text-muted">Perfil personal ampliado y matrículas anuales históricas.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <router-link v-if="!isNew" to="/students/movements" class="btn btn-outline-secondary">Cambios y retiros</router-link>
        <BButton v-if="!isNew" variant="outline-danger" :disabled="exportingPdf" @click="exportPdf()">
          {{ exportingPdf ? "Generando PDF..." : "Descargar PDF" }}
        </BButton>
        <BButton v-if="!isNew" variant="outline-primary" @click="openEnrollmentModal()">Nueva matrícula</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div v-if="loading" class="py-4">
      <LoadingState message="Cargando ficha de estudiante..." />
    </div>

    <template v-else>
      <div class="row g-3">
        <div class="col-xl-8">
          <BCard class="student-form-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Datos personales</h5>
              <BButton variant="primary" :disabled="saving" @click="save">
                {{ saving ? "Guardando..." : isNew ? "Crear estudiante" : "Guardar cambios" }}
              </BButton>
            </div>

            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label">Nombre registral</label>
                <BFormInput v-model="form.registered_name" placeholder="Nombre completo según registro" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Nombres</label>
                <BFormInput v-model="form.first_name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Apellidos</label>
                <BFormInput v-model="form.last_name" />
              </div>
              <div class="col-md-4">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de nacimiento</label>
                <BFormInput v-model="form.birthdate" type="date" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Estado general</label>
                <BFormSelect v-model="form.general_status" :options="statusOptions" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo</label>
                <BFormInput v-model="form.email" type="email" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.phone" />
              </div>
              <div class="col-md-3">
                <label class="form-label">Cuenta activa</label>
                <div class="pt-2">
                  <BFormCheckbox v-model="form.account_active">Permitir acceso</BFormCheckbox>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Cuenta plataforma</label>
                <BFormInput :model-value="generatedPlatformEmail" disabled />
              </div>
              <div class="col-md-3">
                <label class="form-label">Clave automática inicial</label>
                <BFormInput :model-value="generatedPlatformPassword" disabled />
              </div>
              <div class="col-md-3">
                <label class="form-label">Restablecer contraseña</label>
                <BFormInput v-model="form.password" type="password" placeholder="Opcional" />
              </div>
              <div class="col-md-12">
                <label class="form-label">Domicilio</label>
                <BFormInput v-model="form.address" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Curso actual</label>
                <BFormInput :model-value="currentEnrollment?.snapshot_course_display_name || '-'" disabled />
              </div>
              <div class="col-md-6">
                <label class="form-label">Última matrícula</label>
                <BFormInput :model-value="latestEnrollment?.snapshot_year_name || '-'" disabled />
              </div>
              <div class="col-md-12">
                <label class="form-label">Observaciones</label>
                <BFormTextarea v-model="form.observations" rows="3" placeholder="Escribe tu comentario." />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Antecedentes del apoderado</h5>
            <div class="row g-3">
              <div class="col-12">
                <div class="fw-semibold text-muted small text-uppercase">Apoderado titular</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Quién es el apoderado</label>
                <BFormInput v-model="form.guardian_role" placeholder="Madre, padre, tutor..." />
              </div>
              <div class="col-md-8">
                <label class="form-label">Nombre completo</label>
                <BFormInput v-model="form.guardian_name" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Relación</label>
                <BFormInput v-model="form.guardian_relationship" placeholder="Apoderada titular, tutor legal..." />
              </div>
              <div class="col-md-4">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.guardian_rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.guardian_phone" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo electrónico</label>
                <BFormInput v-model="form.guardian_email" type="email" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Domicilio</label>
                <BFormInput v-model="form.guardian_address" />
              </div>

              <div class="col-12 pt-2">
                <div class="fw-semibold text-muted small text-uppercase">Apoderado suplente</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Quién es el apoderado suplente</label>
                <BFormInput v-model="form.guardian_backup_role" placeholder="Madre, padre, tutor..." />
              </div>
              <div class="col-md-8">
                <label class="form-label">Nombre completo</label>
                <BFormInput v-model="form.guardian_backup_name" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Relación</label>
                <BFormInput v-model="form.guardian_backup_relationship" />
              </div>
              <div class="col-md-4">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.guardian_backup_rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.guardian_backup_phone" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo electrónico</label>
                <BFormInput v-model="form.guardian_backup_email" type="email" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Domicilio</label>
                <BFormInput v-model="form.guardian_backup_address" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Portería y autorizaciones de retiro</h5>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">¿Tiene restricción de retiro?</label>
                <BFormSelect
                  v-model="form.pickup_restriction"
                  :options="[
                    { value: false, text: 'No' },
                    { value: true, text: 'Sí' },
                  ]"
                />
              </div>
              <div class="col-md-8">
                <label class="form-label">Motivo o instrucción especial</label>
                <BFormTextarea v-model="form.pickup_restriction_notes" rows="2" />
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones visibles para portería</label>
                <BFormTextarea
                  v-model="form.porter_alert_notes"
                  rows="2"
                  placeholder="Ej: contacto especial, alerta administrativa, instrucción simple de retiro."
                />
              </div>
              <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold">Personas autorizadas adicionales</div>
                  <div class="small text-muted">Se suman al apoderado titular y suplente.</div>
                </div>
                <BButton
                  variant="outline-primary"
                  size="sm"
                  @click="form.authorized_pickup_people.push({ name: '', rut: '', relationship: '', phone: '' })"
                >
                  Agregar persona
                </BButton>
              </div>
              <div
                v-for="(person, index) in form.authorized_pickup_people"
                :key="`authorized-${index}`"
                class="col-12 border rounded p-3"
              >
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Nombre</label>
                    <BFormInput v-model="person.name" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">RUT</label>
                    <BFormInput v-model="person.rut" />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Relación</label>
                    <BFormInput v-model="person.relationship" />
                  </div>
                  <div class="col-md-2">
                    <label class="form-label">Teléfono</label>
                    <BFormInput v-model="person.phone" />
                  </div>
                  <div class="col-12 d-flex justify-content-end">
                    <BButton variant="outline-danger" size="sm" @click="form.authorized_pickup_people.splice(index, 1)">
                      Quitar
                    </BButton>
                  </div>
                </div>
              </div>
              <div v-if="!form.authorized_pickup_people.length" class="col-12 text-muted">
                No hay personas adicionales registradas.
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Antecedentes familiares</h5>
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">¿Con quién vive la estudiante?</label>
                <BFormInput v-model="form.lives_with" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Número de hermanas en el colegio</label>
                <BFormInput v-model="form.siblings_in_school" type="number" min="0" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Antecedentes del padre</h5>
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Importar desde apoderados</label>
                <BFormSelect v-model="fatherImportSource" :options="parentImportOptions" />
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <BButton class="w-100" variant="outline-secondary" :disabled="!fatherImportSource" @click="applyParentImport('father', fatherImportSource)">
                  Importar datos
                </BButton>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nombre completo</label>
                <BFormInput v-model="form.father_name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.father_rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Nacionalidad</label>
                <BFormInput v-model="form.father_nationality" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.father_phone" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de nacimiento</label>
                <BFormInput v-model="form.father_birthdate" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo electrónico</label>
                <BFormInput v-model="form.father_email" type="email" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Ocupación</label>
                <BFormInput v-model="form.father_occupation" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Domicilio</label>
                <BFormInput v-model="form.father_address" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Nivel de escolaridad</label>
                <BFormInput v-model="form.father_education_level" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Antecedentes de la madre</h5>
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Importar desde apoderados</label>
                <BFormSelect v-model="motherImportSource" :options="parentImportOptions" />
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <BButton class="w-100" variant="outline-secondary" :disabled="!motherImportSource" @click="applyParentImport('mother', motherImportSource)">
                  Importar datos
                </BButton>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nombre completo</label>
                <BFormInput v-model="form.mother_name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.mother_rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Nacionalidad</label>
                <BFormInput v-model="form.mother_nationality" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <BFormInput v-model="form.mother_phone" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de nacimiento</label>
                <BFormInput v-model="form.mother_birthdate" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo electrónico</label>
                <BFormInput v-model="form.mother_email" type="email" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Ocupación</label>
                <BFormInput v-model="form.mother_occupation" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Domicilio</label>
                <BFormInput v-model="form.mother_address" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Nivel de escolaridad</label>
                <BFormInput v-model="form.mother_education_level" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Antecedentes escolares y sociales</h5>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">¿Ha repetido algún curso?</label>
                <BFormSelect v-model="form.has_repeated_course" :options="booleanOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Cuenta con Internet en su domicilio?</label>
                <BFormSelect v-model="form.has_internet" :options="booleanOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Cuenta con computador en su domicilio?</label>
                <BFormSelect v-model="form.has_computer" :options="booleanOptions" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Previsión de salud</label>
                <BFormInput v-model="form.health_insurance" />
              </div>
              <div class="col-md-6">
                <label class="form-label">¿Cuenta con proceso judicial?</label>
                <BFormSelect v-model="form.has_judicial_process" :options="booleanOptions" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Programas de los que es beneficiaria</label>
                <BFormTextarea v-model="form.beneficiary_programs" rows="2" placeholder="Ej: Ninguno" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Becas vigentes</label>
                <BFormTextarea v-model="form.scholarships" rows="2" placeholder="Ej: Ninguna" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Antecedentes médicos</h5>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">¿Sufre alguna enfermedad crónica?</label>
                <BFormSelect v-model="form.has_chronic_illness" :options="booleanOptions" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Detalle enfermedad crónica</label>
                <BFormTextarea v-model="form.chronic_illness_details" rows="2" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Tiene alergias o contraindicaciones a medicamentos?</label>
                <BFormSelect v-model="form.has_medication_allergies" :options="booleanOptions" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Detalle alergias o contraindicaciones</label>
                <BFormTextarea v-model="form.medication_allergies_details" rows="2" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Tiene restricciones para ejercicios físicos?</label>
                <BFormSelect v-model="form.has_physical_restrictions" :options="booleanOptions" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Detalle restricciones físicas</label>
                <BFormTextarea v-model="form.physical_restrictions_details" rows="2" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Sacramentos</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Fecha de bautismo</label>
                <BFormInput v-model="form.baptism_date" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Lugar de bautismo</label>
                <BFormInput v-model="form.baptism_place" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha de primera comunión</label>
                <BFormInput v-model="form.first_communion_date" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Lugar de primera comunión</label>
                <BFormInput v-model="form.first_communion_place" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha de confirmación</label>
                <BFormInput v-model="form.confirmation_date" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Lugar de confirmación</label>
                <BFormInput v-model="form.confirmation_place" />
              </div>
            </div>
          </BCard>
        </div>

        <div class="col-xl-4">
          <BCard class="student-form-card">
            <h5 class="mb-3">Resumen actual</h5>
            <div class="mb-2"><span class="text-muted">Nombre registral:</span> {{ textValue(student?.registered_name_resolved || form.registered_name || form.first_name) }}</div>
            <div class="mb-2"><span class="text-muted">Rol:</span> Estudiante</div>
            <div class="mb-2"><span class="text-muted">Cuenta:</span> {{ generatedPlatformEmail }}</div>
            <div class="mb-2"><span class="text-muted">Curso vigente:</span> {{ currentEnrollment?.snapshot_course_display_name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Última matrícula:</span> {{ latestEnrollment?.snapshot_year_name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Estado matrícula:</span> {{ currentEnrollment?.enrollment_status || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Apoderado titular:</span> {{ textValue(form.guardian_name) }}</div>
            <div class="mb-2"><span class="text-muted">Teléfono apoderado:</span> {{ textValue(form.guardian_phone) }}</div>
            <div class="mb-0"><span class="text-muted">Correo apoderado:</span> {{ textValue(form.guardian_email) }}</div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Resumen de portería</h5>
            <div class="mb-2"><span class="text-muted">Restricción de retiro:</span> {{ form.pickup_restriction ? "Sí" : "No" }}</div>
            <div class="small text-muted mb-3">{{ textValue(form.pickup_restriction_notes) }}</div>
            <div class="mb-2"><span class="text-muted">Observaciones portería:</span> {{ textValue(form.porter_alert_notes) }}</div>
            <div class="mt-3">
              <div class="fw-semibold">Autorizadas adicionales</div>
              <div v-if="!form.authorized_pickup_people.length" class="small text-muted">No hay registros adicionales.</div>
              <ul v-else class="list-unstyled mb-0">
                <li v-for="(person, index) in form.authorized_pickup_people" :key="`summary-authorized-${index}`" class="small mb-2">
                  {{ person.name || "-" }}<span v-if="person.relationship"> · {{ person.relationship }}</span><span v-if="person.rut"> · {{ person.rut }}</span>
                </li>
              </ul>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Vista rápida</h5>
            <div class="mb-2"><span class="text-muted">Vive con:</span> {{ textValue(form.lives_with) }}</div>
            <div class="mb-2"><span class="text-muted">Hermanas en el colegio:</span> {{ textValue(form.siblings_in_school, "0") }}</div>
            <div class="mb-2"><span class="text-muted">Internet:</span> {{ boolValue(form.has_internet) }}</div>
            <div class="mb-2"><span class="text-muted">Computador:</span> {{ boolValue(form.has_computer) }}</div>
            <div class="mb-2"><span class="text-muted">Proceso judicial:</span> {{ boolValue(form.has_judicial_process) }}</div>
            <div class="mb-0"><span class="text-muted">Previsión:</span> {{ textValue(form.health_insurance) }}</div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Alertas médicas</h5>
            <div class="mb-2"><span class="text-muted">Enfermedad crónica:</span> {{ boolValue(form.has_chronic_illness) }}</div>
            <div class="small text-muted mb-3">{{ textValue(form.chronic_illness_details) }}</div>
            <div class="mb-2"><span class="text-muted">Alergias / medicamentos:</span> {{ boolValue(form.has_medication_allergies) }}</div>
            <div class="small text-muted mb-3">{{ textValue(form.medication_allergies_details) }}</div>
            <div class="mb-2"><span class="text-muted">Restricciones físicas:</span> {{ boolValue(form.has_physical_restrictions) }}</div>
            <div class="small text-muted mb-0">{{ textValue(form.physical_restrictions_details) }}</div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <h5 class="mb-3">Sacramentos</h5>
            <div class="mb-2"><span class="text-muted">Bautismo:</span> {{ formatDate(form.baptism_date) }}<span v-if="form.baptism_place"> · {{ form.baptism_place }}</span></div>
            <div class="mb-2"><span class="text-muted">Primera comunión:</span> {{ formatDate(form.first_communion_date) }}<span v-if="form.first_communion_place"> · {{ form.first_communion_place }}</span></div>
            <div class="mb-0"><span class="text-muted">Confirmación:</span> {{ formatDate(form.confirmation_date) }}<span v-if="form.confirmation_place"> · {{ form.confirmation_place }}</span></div>
          </BCard>
        </div>
      </div>

      <BCard v-if="!isNew" class="mt-3 student-form-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Historial académico anual</h5>
          <BButton variant="outline-primary" size="sm" @click="openEnrollmentModal()">Agregar año</BButton>
        </div>
        <BTable
          :items="student?.enrollments || []"
          small
          responsive
          :fields="[
            { key: 'snapshot_year_name', label: 'Año' },
            { key: 'snapshot_course_display_name', label: 'Curso' },
            { key: 'enrollment_status', label: 'Estado' },
            { key: 'enrolled_at', label: 'Fecha matrícula' },
            { key: 'observations', label: 'Observaciones' },
            { key: 'actions', label: 'Acciones' },
          ]"
        >
          <template #cell(actions)="{ item }">
            <BButton size="sm" variant="outline-secondary" @click="openEnrollmentModal(item)">Editar</BButton>
          </template>
        </BTable>
      </BCard>

      <BCard v-if="!isNew && (student?.promotions || []).length" class="mt-3 student-form-card">
        <h5 class="mb-3">Promociones registradas</h5>
        <BTable
          :items="student.promotions"
          small
          responsive
          :fields="[
            { key: 'fromAcademicYear', label: 'Origen' },
            { key: 'toAcademicYear', label: 'Destino' },
            { key: 'promotion_status', label: 'Resultado' },
            { key: 'notes', label: 'Notas' },
          ]"
        >
          <template #cell(fromAcademicYear)="{ item }">
            {{ item.from_academic_year?.name || "-" }} · {{ item.from_course_section?.display_name || "-" }}
          </template>
          <template #cell(toAcademicYear)="{ item }">
            {{ item.to_academic_year?.name || "-" }} · {{ item.to_course_section?.display_name || "-" }}
          </template>
        </BTable>
      </BCard>

      <BCard v-if="!isNew && (student?.enrollment_movements || []).length" class="mt-3 student-form-card">
        <h5 class="mb-3">Movimientos de matrícula</h5>
        <BTable
          :items="student.enrollment_movements"
          small
          responsive
          :fields="[
            { key: 'snapshot_year_name', label: 'Año' },
            { key: 'movement_type', label: 'Movimiento' },
            { key: 'from_course', label: 'Desde' },
            { key: 'to_course', label: 'Hacia' },
            { key: 'effective_date', label: 'Fecha' },
            { key: 'notes', label: 'Notas' },
          ]"
        >
          <template #cell(from_course)="{ item }">
            {{ item.snapshot_from_course_display_name || "-" }}
          </template>
          <template #cell(to_course)="{ item }">
            {{ item.snapshot_to_course_display_name || "-" }}
          </template>
        </BTable>
      </BCard>
    </template>

    <BModal v-model="showEnrollmentModal" title="Matrícula anual" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Año académico</label>
          <BFormSelect v-model="enrollmentForm.academic_year_id" :options="academicYearOptions" @change="loadEnrollmentCourses" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Curso</label>
          <BFormSelect v-model="enrollmentForm.course_section_id" :options="enrollmentCourseOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="enrollmentForm.enrollment_status" :options="enrollmentStatusOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de matrícula</label>
          <BFormInput v-model="enrollmentForm.enrolled_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de retiro</label>
          <BFormInput v-model="enrollmentForm.withdrawn_at" type="date" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="enrollmentForm.observations" rows="2" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showEnrollmentModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="savingEnrollment" @click="saveEnrollment">
          {{ savingEnrollment ? "Guardando..." : "Guardar matrícula" }}
        </BButton>
      </div>
    </BModal>
    </div>
  </Layout>
</template>

<style scoped>
.student-record-page {
  display: block;
}

.student-form-card :deep(.form-control),
.student-form-card :deep(.form-select) {
  min-height: 44px;
}

.student-form-card :deep(.form-label) {
  display: flex;
  align-items: flex-end;
  line-height: 1.25;
  margin-bottom: 0.5rem;
}

.student-form-card :deep(textarea.form-control) {
  min-height: 90px;
}

.student-form-card :deep(.btn) {
  min-height: 42px;
}

@media (min-width: 768px) {
  .student-form-card :deep(.form-label) {
    min-height: 3.15rem;
  }
}
</style>
