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
  gender: "",
  nationality: "",
  email: "",
  phone: "",
  address: "",
  commune: "",
  school_admission_date: "",
  previous_school: "",
  emergency_contact_name: "",
  emergency_contact_phone: "",
  religion: "",
  accepts_religion_classes: null,
  ethnicity: "",
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
  guardian_passport: "",
  guardian_phone: "",
  guardian_address: "",
  guardian_commune: "",
  guardian_photo_authorization: null,
  guardian_pickup_authorization: null,
  guardian_marital_status: "",
  guardian_education_level: "",
  guardian_last_education_level: "",
  guardian_occupation: "",
  guardian_email: "",
  guardian_backup_name: "",
  guardian_backup_relationship: "",
  guardian_backup_role: "",
  guardian_backup_rut: "",
  guardian_backup_passport: "",
  guardian_backup_address: "",
  guardian_backup_commune: "",
  guardian_backup_photo_authorization: null,
  guardian_backup_pickup_authorization: null,
  guardian_backup_marital_status: "",
  guardian_backup_education_level: "",
  guardian_backup_last_education_level: "",
  guardian_backup_occupation: "",
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
  height_cm: "",
  weight_kg: "",
  blood_type: "",
  food_allergies: "",
  beneficiary_programs: "",
  scholarships: "",
  has_judicial_process: null,
  has_chronic_illness: null,
  chronic_illness_details: "",
  has_medication_allergies: null,
  medication_allergies_details: "",
  contraindicated_medications: "",
  fit_for_physical_education: null,
  has_private_school_insurance: null,
  healthcare_provider: "",
  health_observations: "",
  is_pie_participant: null,
  pie_permanence_type: "",
  pie_diagnosis: "",
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
  registration_number: "",
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
    studentDisplayName() {
      return this.textValue(
        this.student?.registered_name_resolved ||
          this.form.registered_name ||
          [this.form.first_name, this.form.last_name].filter(Boolean).join(" "),
        this.isNew ? "Nueva estudiante" : "Estudiante"
      );
    },
    studentInitials() {
      const parts = this.studentDisplayName
        .split(/\s+/)
        .filter(Boolean);

      return (parts.length > 1 ? `${parts[0][0]}${parts.at(-1)[0]}` : parts[0]?.slice(0, 2) || "NE").toUpperCase();
    },
    studentAge() {
      if (!this.form.birthdate) return "Sin registro";

      const birthdate = new Date(`${String(this.form.birthdate).slice(0, 10)}T12:00:00`);
      const today = new Date();
      let age = today.getFullYear() - birthdate.getFullYear();
      const monthDelta = today.getMonth() - birthdate.getMonth();

      if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthdate.getDate())) {
        age -= 1;
      }

      return Number.isFinite(age) && age >= 0 ? `${age} años` : "Sin registro";
    },
    studentStatusLabel() {
      return this.statusOptions.find((option) => option.value === this.form.general_status)?.text || this.form.general_status || "Sin estado";
    },
    profileCompletion() {
      const requiredFields = [
        this.form.registered_name || this.form.first_name,
        this.form.last_name,
        this.form.rut,
        this.form.birthdate,
        this.form.address,
        this.form.guardian_name,
        this.form.guardian_phone,
        this.currentEnrollment?.snapshot_course_display_name,
      ];
      const completed = requiredFields.filter((value) => String(value || "").trim() !== "").length;

      return Math.round((completed / requiredFields.length) * 100);
    },
    operationalAlertCount() {
      return [
        this.form.pickup_restriction,
        this.form.has_chronic_illness,
        this.form.has_medication_allergies,
        this.form.has_physical_restrictions,
      ].filter(Boolean).length;
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
        gender: student.gender || "",
        nationality: student.nationality || "",
        email: student.email || "",
        phone: student.phone || "",
        address: student.address || "",
        commune: student.commune || "",
        school_admission_date: student.school_admission_date || "",
        previous_school: student.previous_school || "",
        emergency_contact_name: student.emergency_contact_name || "",
        emergency_contact_phone: student.emergency_contact_phone || "",
        religion: student.religion || "",
        accepts_religion_classes: student.accepts_religion_classes ?? null,
        ethnicity: student.ethnicity || "",
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
        guardian_passport: student.guardian_passport || "",
        guardian_phone: student.guardian_phone || "",
        guardian_address: student.guardian_address || "",
        guardian_commune: student.guardian_commune || "",
        guardian_photo_authorization: student.guardian_photo_authorization ?? null,
        guardian_pickup_authorization: student.guardian_pickup_authorization ?? null,
        guardian_marital_status: student.guardian_marital_status || "",
        guardian_education_level: student.guardian_education_level || "",
        guardian_last_education_level: student.guardian_last_education_level || "",
        guardian_occupation: student.guardian_occupation || "",
        guardian_email: student.guardian_email || "",
        guardian_backup_name: student.guardian_backup_name || "",
        guardian_backup_relationship: student.guardian_backup_relationship || "",
        guardian_backup_role: student.guardian_backup_role || "",
        guardian_backup_rut: student.guardian_backup_rut || "",
        guardian_backup_passport: student.guardian_backup_passport || "",
        guardian_backup_address: student.guardian_backup_address || "",
        guardian_backup_commune: student.guardian_backup_commune || "",
        guardian_backup_photo_authorization: student.guardian_backup_photo_authorization ?? null,
        guardian_backup_pickup_authorization: student.guardian_backup_pickup_authorization ?? null,
        guardian_backup_marital_status: student.guardian_backup_marital_status || "",
        guardian_backup_education_level: student.guardian_backup_education_level || "",
        guardian_backup_last_education_level: student.guardian_backup_last_education_level || "",
        guardian_backup_occupation: student.guardian_backup_occupation || "",
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
        height_cm: student.height_cm ?? "",
        weight_kg: student.weight_kg ?? "",
        blood_type: student.blood_type || "",
        food_allergies: student.food_allergies || "",
        beneficiary_programs: student.beneficiary_programs || "",
        scholarships: student.scholarships || "",
        has_judicial_process: student.has_judicial_process ?? null,
        has_chronic_illness: student.has_chronic_illness ?? null,
        chronic_illness_details: student.chronic_illness_details || "",
        has_medication_allergies: student.has_medication_allergies ?? null,
        medication_allergies_details: student.medication_allergies_details || "",
        contraindicated_medications: student.contraindicated_medications || "",
        fit_for_physical_education: student.fit_for_physical_education ?? null,
        has_private_school_insurance: student.has_private_school_insurance ?? null,
        healthcare_provider: student.healthcare_provider || "",
        health_observations: student.health_observations || "",
        is_pie_participant: student.is_pie_participant ?? null,
        pie_permanence_type: student.pie_permanence_type || "",
        pie_diagnosis: student.pie_diagnosis || "",
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
    scrollToRecordSection(sectionId) {
      document.getElementById(sectionId)?.scrollIntoView({ behavior: "smooth", block: "start" });
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
            registration_number: enrollment.registration_number || "",
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
          ["Género", this.textValue(this.form.gender)],
          ["Nacionalidad", this.textValue(this.form.nationality)],
          ["Domicilio", this.textValue(this.form.address)],
          ["Comuna", this.textValue(this.form.commune)],
          ["Fecha de ingreso", this.formatDate(this.form.school_admission_date)],
          ["Colegio de procedencia", this.textValue(this.form.previous_school)],
          ["Contacto de emergencia", this.textValue(this.form.emergency_contact_name)],
          ["Teléfono de emergencia", this.textValue(this.form.emergency_contact_phone)],
          ["Religión", this.textValue(this.form.religion)],
          ["Acepta clases de religión", this.boolValue(this.form.accepts_religion_classes)],
          ["Etnia", this.textValue(this.form.ethnicity)],
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
          ["Pasaporte apoderado", this.textValue(this.form.guardian_passport)],
          ["Domicilio apoderado", this.textValue(this.form.guardian_address)],
          ["Comuna apoderado", this.textValue(this.form.guardian_commune)],
          ["Correo apoderado", this.textValue(this.form.guardian_email)],
          ["Teléfono apoderado", this.textValue(this.form.guardian_phone)],
          ["Estado civil apoderado", this.textValue(this.form.guardian_marital_status)],
          ["Nivel educacional apoderado", this.textValue(this.form.guardian_education_level)],
          ["Último nivel apoderado", this.textValue(this.form.guardian_last_education_level)],
          ["Ocupación apoderado", this.textValue(this.form.guardian_occupation)],
          ["Autoriza fotografía", this.boolValue(this.form.guardian_photo_authorization)],
          ["Autoriza retiro", this.boolValue(this.form.guardian_pickup_authorization)],
          ["Quién es el apoderado suplente", this.textValue(this.form.guardian_backup_role)],
          ["Nombre completo apoderado suplente", this.textValue(this.form.guardian_backup_name)],
          ["Relación apoderado suplente", this.textValue(this.form.guardian_backup_relationship)],
          ["RUT apoderado suplente", this.textValue(this.form.guardian_backup_rut)],
          ["Pasaporte apoderado suplente", this.textValue(this.form.guardian_backup_passport)],
          ["Domicilio apoderado suplente", this.textValue(this.form.guardian_backup_address)],
          ["Comuna apoderado suplente", this.textValue(this.form.guardian_backup_commune)],
          ["Correo apoderado suplente", this.textValue(this.form.guardian_backup_email)],
          ["Teléfono apoderado suplente", this.textValue(this.form.guardian_backup_phone)],
          ["Estado civil apoderado suplente", this.textValue(this.form.guardian_backup_marital_status)],
          ["Nivel educacional apoderado suplente", this.textValue(this.form.guardian_backup_education_level)],
          ["Último nivel apoderado suplente", this.textValue(this.form.guardian_backup_last_education_level)],
          ["Ocupación apoderado suplente", this.textValue(this.form.guardian_backup_occupation)],
          ["Suplente autoriza fotografía", this.boolValue(this.form.guardian_backup_photo_authorization)],
          ["Suplente autoriza retiro", this.boolValue(this.form.guardian_backup_pickup_authorization)],
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
          ["Programas beneficiaria", this.textValue(this.form.beneficiary_programs)],
          ["Becas", this.textValue(this.form.scholarships)],
          ["Proceso judicial", this.boolValue(this.form.has_judicial_process)],
        ];

        const medicalRows = [
          ["Estatura (cm)", this.textValue(this.form.height_cm)],
          ["Peso (kg)", this.textValue(this.form.weight_kg)],
          ["Grupo sanguíneo", this.textValue(this.form.blood_type)],
          ["Alergias a alimentos", this.textValue(this.form.food_allergies)],
          ["Enfermedad crónica", this.boolValue(this.form.has_chronic_illness)],
          ["Detalle enfermedad crónica", this.textValue(this.form.chronic_illness_details)],
          ["Alergias a medicamentos", this.boolValue(this.form.has_medication_allergies)],
          ["Detalle alergias medicamentos", this.textValue(this.form.medication_allergies_details)],
          ["Medicamentos contraindicados", this.textValue(this.form.contraindicated_medications)],
          ["Restricciones físicas", this.boolValue(this.form.has_physical_restrictions)],
          ["Detalle restricciones físicas", this.textValue(this.form.physical_restrictions_details)],
          ["Apta para Educación Física", this.boolValue(this.form.fit_for_physical_education)],
          ["Previsión de salud", this.textValue(this.form.health_insurance)],
          ["Seguro escolar privado", this.boolValue(this.form.has_private_school_insurance)],
          ["Centro de atención", this.textValue(this.form.healthcare_provider)],
          ["Observaciones de salud", this.textValue(this.form.health_observations)],
        ];
        const pieRows = [
          ["Permanencia PIE", this.boolValue(this.form.is_pie_participant)],
          ["Tipo de permanencia", this.textValue(this.form.pie_permanence_type)],
          ["Diagnóstico", this.textValue(this.form.pie_diagnosis)],
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
            ["Número de matrícula", item.registration_number || "-"],
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
            ...this.buildPdfSection("Programa de Integración Escolar (PIE)", pieRows),
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
      <div class="student-record-topline">
        <router-link to="/students" class="student-record-back" title="Volver a estudiantes" aria-label="Volver a estudiantes">
          <i class="bx bx-arrow-back"></i>
        </router-link>
        <div>
          <div class="student-record-eyebrow">Estudiantes</div>
          <div class="student-record-breadcrumb">{{ isNew ? "Nuevo registro" : `Ficha #${itemId}` }}</div>
        </div>
      </div>

      <section class="student-profile-header">
        <div class="student-profile-main">
          <div class="student-profile-avatar" aria-hidden="true">{{ studentInitials }}</div>
          <div class="student-profile-identity">
            <div class="student-profile-kicker">{{ isNew ? "Creación de ficha" : "Ficha de estudiante" }}</div>
            <h1>{{ studentDisplayName }}</h1>
            <div class="student-profile-meta">
              <span><i class="bx bx-id-card"></i>{{ form.rut || "RUT pendiente" }}</span>
              <span><i class="bx bx-envelope"></i>{{ generatedPlatformEmail }}</span>
              <span class="student-status" :class="`student-status--${form.general_status || 'pendiente'}`">
                <i class="bx bx-check-circle"></i>{{ studentStatusLabel }}
              </span>
            </div>
          </div>

          <div class="student-profile-actions">
            <router-link v-if="!isNew" to="/students/movements" class="btn btn-outline-secondary">
              <i class="bx bx-transfer-alt"></i><span>Movimientos</span>
            </router-link>
            <BButton v-if="!isNew" variant="outline-secondary" :disabled="exportingPdf" @click="exportPdf()">
              <i class="bx bx-download"></i><span>{{ exportingPdf ? "Generando..." : "PDF" }}</span>
            </BButton>
            <BButton v-if="!isNew" variant="outline-primary" @click="openEnrollmentModal()">
              <i class="bx bx-calendar-plus"></i><span>Nueva matrícula</span>
            </BButton>
          </div>
        </div>

        <div class="student-profile-facts">
          <div class="student-profile-fact">
            <span>Curso vigente</span>
            <strong>{{ currentEnrollment?.snapshot_course_display_name || "Sin matrícula" }}</strong>
          </div>
          <div class="student-profile-fact">
            <span>Año académico</span>
            <strong>{{ currentEnrollment?.snapshot_year_name || latestEnrollment?.snapshot_year_name || "Sin registro" }}</strong>
          </div>
          <div class="student-profile-fact">
            <span>Edad</span>
            <strong>{{ studentAge }}</strong>
          </div>
          <div class="student-profile-fact">
            <span>Apoderado titular</span>
            <strong>{{ textValue(form.guardian_name, "Sin registro") }}</strong>
          </div>
          <div class="student-profile-fact student-profile-fact--completion">
            <div>
              <span>Ficha esencial</span>
              <strong>{{ profileCompletion }}%</strong>
            </div>
            <div class="student-profile-progress" role="progressbar" :aria-valuenow="profileCompletion" aria-valuemin="0" aria-valuemax="100">
              <span :style="{ width: `${profileCompletion}%` }"></span>
            </div>
          </div>
        </div>
      </section>

      <div v-if="operationalAlertCount" class="student-operational-alerts">
        <div class="student-operational-alerts__title">
          <i class="bx bx-error-circle"></i>
          <span>{{ operationalAlertCount }} alerta{{ operationalAlertCount === 1 ? "" : "s" }} operativa{{ operationalAlertCount === 1 ? "" : "s" }}</span>
        </div>
        <div class="student-operational-alerts__items">
          <span v-if="form.pickup_restriction">Restricción de retiro</span>
          <span v-if="form.has_chronic_illness">Enfermedad crónica</span>
          <span v-if="form.has_medication_allergies">Alergia a medicamentos</span>
          <span v-if="form.has_physical_restrictions">Restricción física</span>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div v-if="loading" class="py-4">
      <LoadingState message="Cargando ficha de estudiante..." />
    </div>

    <template v-else>
      <div class="student-section-bar">
        <div class="student-section-tabs" role="navigation" aria-label="Secciones de la ficha">
          <button type="button" @click="scrollToRecordSection('record-personal')"><i class="bx bx-user"></i>Personales</button>
          <button type="button" @click="scrollToRecordSection('record-guardians')"><i class="bx bx-group"></i>Apoderados</button>
          <button type="button" @click="scrollToRecordSection('record-porter')"><i class="bx bx-shield-quarter"></i>Portería</button>
          <button type="button" @click="scrollToRecordSection('record-family')"><i class="bx bx-home-alt"></i>Familia</button>
          <button type="button" @click="scrollToRecordSection('record-school')"><i class="bx bx-book-open"></i>Escolar</button>
          <button type="button" @click="scrollToRecordSection('record-health')"><i class="bx bx-plus-medical"></i>Salud y PIE</button>
          <button v-if="!isNew" type="button" @click="scrollToRecordSection('record-history')"><i class="bx bx-history"></i>Trayectoria</button>
        </div>
        <BButton variant="primary" class="student-section-save" :disabled="saving" @click="save">
          <i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bx-save'"></i>
          <span>{{ saving ? "Guardando..." : isNew ? "Crear estudiante" : "Guardar" }}</span>
        </BButton>
      </div>

      <div class="row g-3">
        <div class="col-xl-8">
          <BCard id="record-personal" class="student-form-card student-section-anchor">
            <div class="student-card-heading">
              <div class="student-card-heading__icon"><i class="bx bx-user"></i></div>
              <div>
                <h2>Datos personales</h2>
                <p>Identidad, contacto y cuenta de acceso</p>
              </div>
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
              <div class="col-md-4">
                <label class="form-label">Género</label>
                <BFormInput v-model="form.gender" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Nacionalidad</label>
                <BFormInput v-model="form.nationality" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Comuna</label>
                <BFormInput v-model="form.commune" />
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
              <div class="col-md-8">
                <label class="form-label">Domicilio</label>
                <BFormInput v-model="form.address" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de ingreso al colegio</label>
                <BFormInput v-model="form.school_admission_date" type="date" />
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
            <div class="student-card-heading student-card-heading--compact">
              <div class="student-card-heading__icon"><i class="bx bx-map"></i></div>
              <div><h2>Contacto y procedencia</h2><p>Antecedentes de ingreso y pertenencia</p></div>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Colegio de procedencia</label>
                <BFormInput v-model="form.previous_school" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Contacto de emergencia</label>
                <BFormInput v-model="form.emergency_contact_name" />
              </div>
              <div class="col-md-2">
                <label class="form-label">Teléfono de emergencia</label>
                <BFormInput v-model="form.emergency_contact_phone" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Religión</label>
                <BFormInput v-model="form.religion" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Acepta clases de religión?</label>
                <BFormSelect v-model="form.accepts_religion_classes" :options="booleanOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Etnia o pueblo originario</label>
                <BFormInput v-model="form.ethnicity" />
              </div>
            </div>
          </BCard>

          <BCard id="record-guardians" class="mt-3 student-form-card student-section-anchor">
            <div class="student-card-heading">
              <div class="student-card-heading__icon"><i class="bx bx-group"></i></div>
              <div><h2>Apoderados</h2><p>Contactos titular y suplente</p></div>
            </div>
            <div class="row g-3">
              <div class="col-12">
                <div class="fw-semibold text-muted small text-uppercase">Apoderado titular</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Tipo de apoderado</label>
                <BFormInput v-model="form.guardian_role" placeholder="Titular" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Nombre completo</label>
                <BFormInput v-model="form.guardian_name" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Parentesco</label>
                <BFormInput v-model="form.guardian_relationship" placeholder="Madre, padre, tutor..." />
              </div>
              <div class="col-md-4">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.guardian_rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Pasaporte</label>
                <BFormInput v-model="form.guardian_passport" />
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
              <div class="col-md-4">
                <label class="form-label">Comuna</label>
                <BFormInput v-model="form.guardian_commune" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Estado civil</label>
                <BFormInput v-model="form.guardian_marital_status" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Ocupación</label>
                <BFormInput v-model="form.guardian_occupation" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Nivel educacional</label>
                <BFormInput v-model="form.guardian_education_level" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Último nivel educacional</label>
                <BFormInput v-model="form.guardian_last_education_level" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Autorización de fotografía o grabación</label>
                <BFormSelect v-model="form.guardian_photo_authorization" :options="booleanOptions" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Autorizado para retirar</label>
                <BFormSelect v-model="form.guardian_pickup_authorization" :options="booleanOptions" />
              </div>

              <div class="col-12 pt-2">
                <div class="fw-semibold text-muted small text-uppercase">Apoderado suplente</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Tipo de apoderado</label>
                <BFormInput v-model="form.guardian_backup_role" placeholder="Suplente" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Nombre completo</label>
                <BFormInput v-model="form.guardian_backup_name" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Parentesco</label>
                <BFormInput v-model="form.guardian_backup_relationship" placeholder="Madre, padre, tutor..." />
              </div>
              <div class="col-md-4">
                <label class="form-label">RUT</label>
                <BFormInput v-model="form.guardian_backup_rut" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Pasaporte</label>
                <BFormInput v-model="form.guardian_backup_passport" />
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
              <div class="col-md-4">
                <label class="form-label">Comuna</label>
                <BFormInput v-model="form.guardian_backup_commune" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Estado civil</label>
                <BFormInput v-model="form.guardian_backup_marital_status" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Ocupación</label>
                <BFormInput v-model="form.guardian_backup_occupation" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Nivel educacional</label>
                <BFormInput v-model="form.guardian_backup_education_level" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Último nivel educacional</label>
                <BFormInput v-model="form.guardian_backup_last_education_level" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Autorización de fotografía o grabación</label>
                <BFormSelect v-model="form.guardian_backup_photo_authorization" :options="booleanOptions" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Autorizado para retirar</label>
                <BFormSelect v-model="form.guardian_backup_pickup_authorization" :options="booleanOptions" />
              </div>
            </div>
          </BCard>

          <BCard id="record-porter" class="mt-3 student-form-card student-section-anchor">
            <div class="student-card-heading">
              <div class="student-card-heading__icon student-card-heading__icon--warning"><i class="bx bx-shield-quarter"></i></div>
              <div><h2>Portería y retiros</h2><p>Restricciones y personas autorizadas</p></div>
            </div>
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

          <BCard id="record-family" class="mt-3 student-form-card student-section-anchor">
            <div class="student-card-heading">
              <div class="student-card-heading__icon"><i class="bx bx-home-alt"></i></div>
              <div><h2>Antecedentes familiares</h2><p>Composición del hogar y vínculos escolares</p></div>
            </div>
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
            <div class="student-card-heading student-card-heading--compact">
              <div class="student-card-heading__icon"><i class="bx bx-user-circle"></i></div>
              <div><h2>Antecedentes del padre</h2><p>Identificación y contacto</p></div>
            </div>
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
            <div class="student-card-heading student-card-heading--compact">
              <div class="student-card-heading__icon"><i class="bx bx-user-circle"></i></div>
              <div><h2>Antecedentes de la madre</h2><p>Identificación y contacto</p></div>
            </div>
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

          <BCard id="record-school" class="mt-3 student-form-card student-section-anchor">
            <div class="student-card-heading">
              <div class="student-card-heading__icon"><i class="bx bx-book-open"></i></div>
              <div><h2>Antecedentes escolares y sociales</h2><p>Trayectoria, conectividad y beneficios</p></div>
            </div>
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

          <BCard id="record-health" class="mt-3 student-form-card student-section-anchor">
            <div class="student-card-heading">
              <div class="student-card-heading__icon student-card-heading__icon--health"><i class="bx bx-plus-medical"></i></div>
              <div><h2>Antecedentes médicos</h2><p>Condiciones, alergias y restricciones</p></div>
            </div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Estatura (cm)</label>
                <BFormInput v-model="form.height_cm" type="number" min="0" step="0.01" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Peso (kg)</label>
                <BFormInput v-model="form.weight_kg" type="number" min="0" step="0.01" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Grupo sanguíneo</label>
                <BFormInput v-model="form.blood_type" />
              </div>
              <div class="col-md-12">
                <label class="form-label">Alergias a alimentos</label>
                <BFormTextarea v-model="form.food_allergies" rows="2" />
              </div>
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
                <label class="form-label">Detalle alergias a medicamentos</label>
                <BFormTextarea v-model="form.medication_allergies_details" rows="2" />
              </div>
              <div class="col-md-12">
                <label class="form-label">Medicamentos contraindicados</label>
                <BFormTextarea v-model="form.contraindicated_medications" rows="2" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Tiene restricciones para ejercicios físicos?</label>
                <BFormSelect v-model="form.has_physical_restrictions" :options="booleanOptions" />
              </div>
              <div class="col-md-8">
                <label class="form-label">Detalle restricciones físicas</label>
                <BFormTextarea v-model="form.physical_restrictions_details" rows="2" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Apta para Educación Física?</label>
                <BFormSelect v-model="form.fit_for_physical_education" :options="booleanOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Previsión de salud</label>
                <BFormInput v-model="form.health_insurance" />
              </div>
              <div class="col-md-4">
                <label class="form-label">¿Posee seguro escolar privado?</label>
                <BFormSelect v-model="form.has_private_school_insurance" :options="booleanOptions" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Consultorio o clínica donde se atiende</label>
                <BFormInput v-model="form.healthcare_provider" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Observaciones de salud</label>
                <BFormTextarea v-model="form.health_observations" rows="2" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <div class="student-card-heading student-card-heading--compact">
              <div class="student-card-heading__icon student-card-heading__icon--pie"><i class="bx bx-support"></i></div>
              <div><h2>Programa de Integración Escolar</h2><p>Permanencia y diagnóstico PIE</p></div>
            </div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Permanencia PIE</label>
                <BFormSelect v-model="form.is_pie_participant" :options="booleanOptions" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Tipo de permanencia</label>
                <BFormInput v-model="form.pie_permanence_type" />
              </div>
              <div class="col-md-12">
                <label class="form-label">Diagnóstico</label>
                <BFormTextarea v-model="form.pie_diagnosis" rows="2" />
              </div>
            </div>
          </BCard>

          <BCard class="mt-3 student-form-card">
            <div class="student-card-heading student-card-heading--compact">
              <div class="student-card-heading__icon"><i class="bx bx-calendar-check"></i></div>
              <div><h2>Sacramentos</h2><p>Fechas y lugares registrados</p></div>
            </div>
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
          <div class="student-record-sidebar">
          <BCard class="student-form-card student-summary-card">
            <div class="student-sidebar-heading"><i class="bx bx-data"></i><h2>Resumen actual</h2></div>
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

          <BCard class="mt-3 student-form-card student-summary-card" :class="{ 'student-summary-card--alert': form.pickup_restriction }">
            <div class="student-sidebar-heading"><i class="bx bx-shield-quarter"></i><h2>Resumen de portería</h2></div>
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

          <BCard class="mt-3 student-form-card student-summary-card">
            <div class="student-sidebar-heading"><i class="bx bx-list-check"></i><h2>Vista rápida</h2></div>
            <div class="mb-2"><span class="text-muted">Vive con:</span> {{ textValue(form.lives_with) }}</div>
            <div class="mb-2"><span class="text-muted">Hermanas en el colegio:</span> {{ textValue(form.siblings_in_school, "0") }}</div>
            <div class="mb-2"><span class="text-muted">Internet:</span> {{ boolValue(form.has_internet) }}</div>
            <div class="mb-2"><span class="text-muted">Computador:</span> {{ boolValue(form.has_computer) }}</div>
            <div class="mb-2"><span class="text-muted">Proceso judicial:</span> {{ boolValue(form.has_judicial_process) }}</div>
            <div class="mb-0"><span class="text-muted">Previsión:</span> {{ textValue(form.health_insurance) }}</div>
          </BCard>

          <BCard class="mt-3 student-form-card student-summary-card" :class="{ 'student-summary-card--alert': operationalAlertCount > (form.pickup_restriction ? 1 : 0) }">
            <div class="student-sidebar-heading"><i class="bx bx-plus-medical"></i><h2>Alertas médicas</h2></div>
            <div class="mb-2"><span class="text-muted">Enfermedad crónica:</span> {{ boolValue(form.has_chronic_illness) }}</div>
            <div class="small text-muted mb-3">{{ textValue(form.chronic_illness_details) }}</div>
            <div class="mb-2"><span class="text-muted">Alergias / medicamentos:</span> {{ boolValue(form.has_medication_allergies) }}</div>
            <div class="small text-muted mb-3">{{ textValue(form.medication_allergies_details) }}</div>
            <div class="mb-2"><span class="text-muted">Restricciones físicas:</span> {{ boolValue(form.has_physical_restrictions) }}</div>
            <div class="small text-muted mb-0">{{ textValue(form.physical_restrictions_details) }}</div>
          </BCard>

          <BCard class="mt-3 student-form-card student-summary-card">
            <div class="student-sidebar-heading"><i class="bx bx-calendar-check"></i><h2>Sacramentos</h2></div>
            <div class="mb-2"><span class="text-muted">Bautismo:</span> {{ formatDate(form.baptism_date) }}<span v-if="form.baptism_place"> · {{ form.baptism_place }}</span></div>
            <div class="mb-2"><span class="text-muted">Primera comunión:</span> {{ formatDate(form.first_communion_date) }}<span v-if="form.first_communion_place"> · {{ form.first_communion_place }}</span></div>
            <div class="mb-0"><span class="text-muted">Confirmación:</span> {{ formatDate(form.confirmation_date) }}<span v-if="form.confirmation_place"> · {{ form.confirmation_place }}</span></div>
          </BCard>
          </div>
        </div>
      </div>

      <BCard v-if="!isNew" id="record-history" class="mt-3 student-form-card student-history-card student-section-anchor">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="student-card-heading mb-0">
            <div class="student-card-heading__icon"><i class="bx bx-history"></i></div>
            <div><h2>Historial académico anual</h2><p>Matrículas y cursos registrados</p></div>
          </div>
          <BButton variant="outline-primary" size="sm" @click="openEnrollmentModal()"><i class="bx bx-plus"></i>Agregar año</BButton>
        </div>
        <BTable
          :items="student?.enrollments || []"
          small
          responsive
          :fields="[
            { key: 'snapshot_year_name', label: 'Año' },
            { key: 'snapshot_course_display_name', label: 'Curso' },
            { key: 'enrollment_status', label: 'Estado' },
            { key: 'registration_number', label: 'Nº matrícula' },
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

      <BCard v-if="!isNew && (student?.promotions || []).length" class="mt-3 student-form-card student-history-card">
        <div class="student-card-heading student-card-heading--compact">
          <div class="student-card-heading__icon"><i class="bx bx-up-arrow-alt"></i></div>
          <div><h2>Promociones registradas</h2><p>Resultados de cierre académico</p></div>
        </div>
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

      <BCard v-if="!isNew && (student?.enrollment_movements || []).length" class="mt-3 student-form-card student-history-card">
        <div class="student-card-heading student-card-heading--compact">
          <div class="student-card-heading__icon"><i class="bx bx-transfer-alt"></i></div>
          <div><h2>Movimientos de matrícula</h2><p>Cambios, traslados y retiros</p></div>
        </div>
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
          <label class="form-label">Número de matrícula</label>
          <BFormInput v-model="enrollmentForm.registration_number" />
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
  --record-ink: #172033;
  --record-muted: #64748b;
  --record-border: #dfe5ec;
  --record-surface: var(--bs-card-bg, #ffffff);
  --record-soft: var(--bs-tertiary-bg, #f6f8fa);
  --record-accent: #0f766e;
  color: var(--record-ink);
  padding-bottom: 2rem;
}

:global(.premium-content-grid:has(.student-record-page)),
:global(.premium-main-content:has(.student-record-page)) {
  overflow: clip;
}

.student-record-topline {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-bottom: 0.75rem;
}

.student-record-back {
  display: inline-grid;
  width: 36px;
  height: 36px;
  place-items: center;
  flex: 0 0 36px;
  border: 1px solid var(--record-border);
  border-radius: 6px;
  background: var(--record-surface);
  color: var(--record-ink);
  font-size: 1.15rem;
}

.student-record-back:hover {
  border-color: var(--record-accent);
  color: var(--record-accent);
}

.student-record-eyebrow {
  color: var(--record-muted);
  font-size: 0.68rem;
  font-weight: 700;
  line-height: 1.1;
  text-transform: uppercase;
}

.student-record-breadcrumb {
  color: var(--record-ink);
  font-size: 0.82rem;
  font-weight: 600;
}

.student-profile-header {
  overflow: hidden;
  margin-bottom: 0.75rem;
  border: 1px solid var(--record-border);
  border-radius: 8px;
  background: var(--record-surface);
  box-shadow: 0 8px 24px rgba(23, 32, 51, 0.06);
}

.student-profile-main {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr) auto;
  gap: 1rem;
  align-items: center;
  padding: 1.25rem;
}

.student-profile-avatar {
  display: grid;
  width: 72px;
  height: 72px;
  place-items: center;
  border: 1px solid #99d5ce;
  border-radius: 50%;
  background: #dff5f1;
  color: #075e57;
  font-size: 1.25rem;
  font-weight: 800;
}

.student-profile-identity {
  min-width: 0;
}

.student-profile-kicker {
  margin-bottom: 0.2rem;
  color: var(--record-accent);
  font-size: 0.68rem;
  font-weight: 800;
  text-transform: uppercase;
}

.student-profile-identity h1 {
  overflow-wrap: anywhere;
  margin: 0;
  color: var(--record-ink);
  font-size: 1.55rem;
  font-weight: 700;
  line-height: 1.2;
}

.student-profile-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem 1rem;
  align-items: center;
  margin-top: 0.55rem;
  color: var(--record-muted);
  font-size: 0.78rem;
}

.student-profile-meta > span {
  display: inline-flex;
  min-width: 0;
  align-items: center;
  gap: 0.35rem;
  overflow-wrap: anywhere;
}

.student-profile-meta i {
  flex: 0 0 auto;
  font-size: 1rem;
}

.student-status {
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: #e7f6ed;
  color: #166534;
  font-weight: 700;
}

.student-status--retirado,
.student-status--suspendido {
  background: #fff0e5;
  color: #9a3412;
}

.student-status--egresado {
  background: #edf0f5;
  color: #475569;
}

.student-profile-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.student-profile-actions :deep(.btn),
.student-profile-actions > .btn {
  display: inline-flex;
  min-height: 40px;
  align-items: center;
  gap: 0.4rem;
  border-radius: 6px;
  font-weight: 600;
  white-space: nowrap;
}

.student-profile-actions i {
  font-size: 1.05rem;
}

.student-profile-facts {
  display: grid;
  grid-template-columns: 1fr 0.8fr 0.65fr 1.4fr 1fr;
  border-top: 1px solid var(--record-border);
  background: var(--record-soft);
}

.student-profile-fact {
  min-width: 0;
  padding: 0.85rem 1rem;
  border-right: 1px solid var(--record-border);
}

.student-profile-fact:last-child {
  border-right: 0;
}

.student-profile-fact > span,
.student-profile-fact--completion span {
  display: block;
  margin-bottom: 0.16rem;
  color: var(--record-muted);
  font-size: 0.66rem;
  font-weight: 700;
  text-transform: uppercase;
}

.student-profile-fact strong {
  display: block;
  overflow: hidden;
  color: var(--record-ink);
  font-size: 0.85rem;
  font-weight: 700;
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.student-profile-fact--completion > div:first-child {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
}

.student-profile-progress {
  overflow: hidden;
  height: 5px;
  margin-top: 0.45rem;
  border-radius: 999px;
  background: #d8e0e8;
}

.student-profile-progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--record-accent);
  transition: width 180ms ease;
}

.student-operational-alerts {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-bottom: 0.75rem;
  padding: 0.65rem 0.85rem;
  border: 1px solid #fed7aa;
  border-left: 4px solid #ea580c;
  border-radius: 6px;
  background: #fff7ed;
  color: #7c2d12;
}

.student-operational-alerts__title,
.student-operational-alerts__items {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
}

.student-operational-alerts__title {
  flex: 0 0 auto;
  font-weight: 800;
}

.student-operational-alerts__title i {
  font-size: 1.2rem;
}

.student-operational-alerts__items span {
  padding: 0.15rem 0.45rem;
  border: 1px solid #fdba74;
  border-radius: 999px;
  background: #ffffff;
  font-size: 0.72rem;
  font-weight: 600;
}

.student-section-bar {
  position: sticky;
  z-index: 20;
  top: 70px;
  display: flex;
  gap: 0.65rem;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
  padding: 0.45rem;
  border: 1px solid var(--record-border);
  border-radius: 8px;
  background: color-mix(in srgb, var(--record-surface) 94%, transparent);
  box-shadow: 0 5px 18px rgba(23, 32, 51, 0.08);
  backdrop-filter: blur(10px);
}

.student-section-tabs {
  display: flex;
  min-width: 0;
  gap: 0.2rem;
  overflow-x: auto;
  scrollbar-width: none;
}

.student-section-tabs::-webkit-scrollbar {
  display: none;
}

.student-section-tabs button {
  display: inline-flex;
  min-height: 36px;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.6rem;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: var(--record-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.student-section-tabs button:hover,
.student-section-tabs button:focus-visible {
  background: #e4f3f1;
  color: #075e57;
}

.student-section-tabs i {
  font-size: 1rem;
}

.student-section-save {
  display: inline-flex;
  min-height: 38px;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.4rem;
  border-radius: 6px;
  font-weight: 700;
}

.student-section-anchor {
  scroll-margin-top: 140px;
}

.student-form-card {
  border: 1px solid var(--record-border);
  border-radius: 8px;
  background: var(--record-surface);
  box-shadow: 0 3px 12px rgba(23, 32, 51, 0.035);
}

.student-form-card :deep(.card-body) {
  padding: 1.15rem;
}

.student-card-heading {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.8rem;
  border-bottom: 1px solid var(--record-border);
}

.student-card-heading--compact {
  margin-bottom: 0.9rem;
}

.student-card-heading__icon {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  flex: 0 0 36px;
  border-radius: 6px;
  background: #e4f3f1;
  color: #075e57;
  font-size: 1.1rem;
}

.student-card-heading__icon--warning {
  background: #fff1e6;
  color: #c2410c;
}

.student-card-heading__icon--health {
  background: #fde8e8;
  color: #b42318;
}

.student-card-heading__icon--pie {
  background: #eef2ff;
  color: #4338ca;
}

.student-card-heading h2,
.student-sidebar-heading h2 {
  margin: 0;
  color: var(--record-ink);
  font-size: 1rem;
  font-weight: 750;
  line-height: 1.25;
}

.student-card-heading p {
  margin: 0.12rem 0 0;
  color: var(--record-muted);
  font-size: 0.74rem;
  line-height: 1.3;
}

.student-form-card :deep(.form-control),
.student-form-card :deep(.form-select) {
  min-height: 40px;
  border-color: #ced6df;
  border-radius: 5px;
  font-size: 0.84rem;
}

.student-form-card :deep(.form-control:focus),
.student-form-card :deep(.form-select:focus) {
  border-color: #4ea69d;
  box-shadow: 0 0 0 0.18rem rgba(15, 118, 110, 0.12);
}

.student-form-card :deep(.form-control:disabled),
.student-form-card :deep(.form-select:disabled) {
  background: var(--record-soft);
  color: var(--record-muted);
  opacity: 1;
}

.student-form-card :deep(.form-label) {
  margin-bottom: 0.35rem;
  color: #4b5565;
  font-size: 0.73rem;
  font-weight: 700;
  line-height: 1.3;
}

.student-form-card :deep(textarea.form-control) {
  min-height: 76px;
}

.student-form-card :deep(.btn) {
  min-height: 38px;
  border-radius: 5px;
}

.student-record-sidebar {
  min-width: 0;
}

.student-sidebar-heading {
  display: flex;
  gap: 0.55rem;
  align-items: center;
  margin: -0.1rem 0 0.85rem;
  padding-bottom: 0.7rem;
  border-bottom: 1px solid var(--record-border);
}

.student-sidebar-heading > i {
  color: var(--record-accent);
  font-size: 1.1rem;
}

.student-summary-card :deep(.card-body) > .mb-2,
.student-summary-card :deep(.card-body) > .mb-0 {
  padding: 0.3rem 0;
  color: var(--record-ink);
  font-size: 0.8rem;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.student-summary-card :deep(.card-body) > .mb-2 .text-muted,
.student-summary-card :deep(.card-body) > .mb-0 .text-muted {
  display: block;
  margin-bottom: 0.08rem;
  color: var(--record-muted) !important;
  font-size: 0.66rem;
  font-weight: 700;
  text-transform: uppercase;
}

.student-summary-card--alert {
  border-left: 4px solid #ea580c;
}

.student-history-card :deep(.table) {
  margin-bottom: 0;
  font-size: 0.8rem;
}

.student-history-card :deep(.table thead th) {
  border-bottom-width: 1px;
  background: var(--record-soft);
  color: #475569;
  font-size: 0.67rem;
  font-weight: 800;
  text-transform: uppercase;
  vertical-align: middle;
}

.student-history-card :deep(.table tbody td) {
  color: var(--record-ink);
  vertical-align: middle;
}

@media (min-width: 1200px) {
  .student-record-sidebar {
    position: sticky;
    top: 140px;
  }
}

@media (max-width: 1199.98px) {
  .student-profile-main {
    grid-template-columns: 64px minmax(0, 1fr);
  }

  .student-profile-avatar {
    width: 64px;
    height: 64px;
  }

  .student-profile-actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }

  .student-profile-facts {
    grid-template-columns: repeat(3, 1fr);
  }

  .student-profile-fact:nth-child(3) {
    border-right: 0;
  }

  .student-profile-fact:nth-child(n + 4) {
    border-top: 1px solid var(--record-border);
  }

  .student-profile-fact--completion {
    grid-column: span 2;
  }
}

@media (max-width: 767.98px) {
  .student-profile-main {
    grid-template-columns: 52px minmax(0, 1fr);
    gap: 0.75rem;
    padding: 1rem;
  }

  .student-profile-avatar {
    width: 52px;
    height: 52px;
    font-size: 1rem;
  }

  .student-profile-identity h1 {
    font-size: 1.2rem;
  }

  .student-profile-meta {
    gap: 0.35rem 0.7rem;
  }

  .student-profile-meta > span:nth-child(2) {
    flex-basis: 100%;
  }

  .student-profile-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .student-profile-actions > :last-child {
    grid-column: 1 / -1;
  }

  .student-profile-actions :deep(.btn),
  .student-profile-actions > .btn {
    width: 100%;
    min-width: 0;
    justify-content: center;
    padding-right: 0.5rem;
    padding-left: 0.5rem;
    font-size: 0.73rem;
  }

  .student-profile-facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .student-profile-fact,
  .student-profile-fact:nth-child(3) {
    border-top: 1px solid var(--record-border);
    border-right: 1px solid var(--record-border);
  }

  .student-profile-fact:nth-child(2n) {
    border-right: 0;
  }

  .student-profile-fact:nth-child(-n + 2) {
    border-top: 0;
  }

  .student-profile-fact--completion {
    grid-column: 1 / -1;
    border-right: 0;
  }

  .student-operational-alerts {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.45rem;
  }

  .student-section-bar {
    top: 64px;
  }

  .student-section-save span {
    display: none;
  }

  .student-section-save {
    width: 38px;
    justify-content: center;
    padding: 0;
  }

  .student-form-card :deep(.card-body) {
    padding: 1rem;
  }

  .student-card-heading {
    align-items: flex-start;
  }

  .student-history-card > :deep(.card-body) > .d-flex {
    align-items: flex-start !important;
    flex-direction: column;
    gap: 0.75rem;
  }
}
</style>
