<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import InfirmaryStudentMedicalSummary from "../../components/infirmary/student-medical-summary.vue";
import {
  buildQuickAttentionPayload,
  QUICK_ATTENTION_ACTIONS,
  QUICK_ATTENTION_REASONS,
  QUICK_ATTENTION_RESULTS,
} from "../../components/infirmary/quick-attention";
import {
  MENTAL_HEALTH_CATEGORY,
  MENTAL_HEALTH_EVENT_OPTIONS,
  SELF_HARM_INJURY_OPTIONS,
  mentalHealthCategorizationError,
  normalizeMentalHealthCategorization,
} from "../../components/infirmary/mental-health-categorization";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  humanizeInfirmaryStatus,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDateTime,
} from "../../components/infirmary/module-utils";
import { getPdfMake } from "../../utils/pdfmake";
import {
  buildSchoolInsuranceCertificateDefinition,
  createSchoolInsuranceCertificateForm,
  schoolInsuranceCertificateFileName,
} from "../../utils/school-insurance-certificate";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStudentSearch,
    InfirmaryStudentMedicalSummary,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: {
        courses: [],
        dependencies: [],
        staff: [],
        users: [],
        medications: [],
        attention_categories: [],
        mental_health_event_options: [],
        self_harm_injury_options: [],
        accident_location_options: [],
        school_insurance_certificate: {},
        companion_staff: {},
        treatment_category_options: [],
        physical_treatment_options: [],
        treatment_derivation_options: [],
        treatment_derivation_support_options: [],
        priority_options: [],
        status_options: [],
        companion_options: [],
        treatment_type_options: [],
        referral_options: [],
        call_status_options: [],
        follow_up_status_options: [],
        capabilities: {},
      },
      filters: {
        search: "",
        student_profile_id: null,
        attention_category: null,
        status: null,
        priority: null,
        course_section_id: null,
        from: "",
        to: "",
      },
      attentions: [],
      pagination: { current_page: 1, total: 0, per_page: 12 },
      showViewModal: false,
      selectedAttention: null,
      certificateLogoDataUrl: null,
      exportingSchoolInsuranceCertificate: false,
      showSchoolInsuranceCertificateModal: false,
      schoolInsuranceCertificateForm: null,
      showModal: false,
      selectedStudentContext: null,
      studentContextLoading: false,
      studentContextRequestId: 0,
      form: this.emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    canCreate() {
      return Boolean(this.catalogs.capabilities?.can_create_attention);
    },
    canEdit() {
      return Boolean(this.catalogs.capabilities?.can_edit_attention);
    },
    canDelete() {
      return Boolean(this.catalogs.capabilities?.can_delete_attention);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    schoolInstitutionTypeOptions() {
      return [
        { value: "1", text: "Fiscal o municipal" },
        { value: "2", text: "Particular" },
        { value: "3", text: "Particular subvencionado" },
      ];
    },
    schoolInsuranceSexOptions() {
      return [
        { value: "", text: "Seleccione" },
        { value: "1", text: "Femenino" },
        { value: "2", text: "Masculino" },
      ];
    },
    schoolInsuranceAccidentTypeOptions() {
      return [
        { value: "1", text: "En trayecto" },
        { value: "2", text: "En el colegio" },
      ];
    },
    isFormAccident() {
      return this.isAccidentCategory(this.form.attention_category);
    },
    isFormMentalHealth() {
      return this.form.attention_category === MENTAL_HEALTH_CATEGORY;
    },
    isFormSelfHarm() {
      return this.isFormMentalHealth && this.form.mental_health_event_type === "autolesion";
    },
    attentionCategoryLabels() {
      return normalizeOptions(this.catalogs.attention_categories).reduce((labels, item) => {
        labels[item.value] = item.text;
        return labels;
      }, {});
    },
    accidentLocationOptions() {
      const options = this.catalogs.accident_location_options?.length
        ? this.catalogs.accident_location_options
        : [
          { value: "colegio", label: "En colegio" },
          { value: "trayecto", label: "Trayecto" },
        ];

      return normalizeOptions(options);
    },
    accidentLocationLabels() {
      return this.accidentLocationOptions.reduce((labels, item) => {
        labels[item.value] = item.text;
        return labels;
      }, {});
    },
    mentalHealthEventOptions() {
      return normalizeOptions(
        this.catalogs.mental_health_event_options?.length
          ? this.catalogs.mental_health_event_options
          : MENTAL_HEALTH_EVENT_OPTIONS,
        true,
        "Seleccione"
      );
    },
    selfHarmInjuryOptions() {
      return normalizeOptions(
        this.catalogs.self_harm_injury_options?.length
          ? this.catalogs.self_harm_injury_options
          : SELF_HARM_INJURY_OPTIONS,
        true,
        "Seleccione"
      );
    },
    dependencyOptions() {
      return [{ value: null, text: "Sin dependencia" }].concat(
        (this.catalogs.dependencies || []).map((item) => {
          const count = Number(item.attentions_count || 0);
          const label = item.label || [item.name, item.usage].filter(Boolean).join(" · ");

          return {
            value: item.id,
            text: count > 0 ? `${label} (${count})` : label,
          };
        })
      );
    },
    statusOptions() {
      return normalizeOptions(this.catalogs.status_options, true);
    },
    companionOptions() {
      return normalizeOptions(this.catalogs.companion_options);
    },
    requiresCompanionStaff() {
      return this.requiresCompanionStaffType(this.form.accompanied_by_type);
    },
    usesCompanionName() {
      return this.usesCompanionNameType(this.form.accompanied_by_type);
    },
    companionStaffOptions() {
      return [{ value: null, text: "Seleccione funcionaria" }].concat(
        this.companionStaffItems(this.form.accompanied_by_type)
      );
    },
    treatmentTypeOptions() {
      return normalizeOptions(this.catalogs.treatment_type_options);
    },
    treatmentCategoryOptions() {
      const options = this.catalogs.treatment_category_options?.length
        ? this.catalogs.treatment_category_options
        : [
          { value: "fisico", label: "Físico" },
          { value: "emocional", label: "Emocional" },
          { value: "derivacion", label: "Derivación" },
          { value: "csv", label: "CSV" },
          { value: "otro", label: "OTRO" },
        ];

      return normalizeOptions(options);
    },
    physicalTreatmentOptions() {
      const options = this.catalogs.physical_treatment_options?.length
        ? this.catalogs.physical_treatment_options
        : [
          { value: "compresa_fria", label: "Compresa fría" },
          { value: "compresa_caliente", label: "Compresa de calor" },
          { value: "administracion_medicamento", label: "Administración de medicamento" },
          { value: "medicamento_sos", label: "Medicamento S.O.S." },
          { value: "apoyo_equipo_formacion", label: "Apoyo equipo formación" },
          { value: "curaciones", label: "Curaciones" },
        ];

      return normalizeOptions(options);
    },
    treatmentDerivationOptions() {
      const options = this.catalogs.treatment_derivation_options?.length
        ? this.catalogs.treatment_derivation_options
        : [
          { value: "sala", label: "Sala" },
          { value: "domicilio", label: "Domicilio" },
          { value: "samu", label: "SAMU" },
          { value: "urgencias", label: "Urgencias" },
        ];

      return normalizeOptions(options, true, "Seleccione");
    },
    treatmentDerivationSupportOptions() {
      const options = this.catalogs.treatment_derivation_support_options?.length
        ? this.catalogs.treatment_derivation_support_options
        : [
          { value: "equipo_directivo", label: "Equipo directivo" },
          { value: "convivencia", label: "Convivencia" },
          { value: "psicosocial", label: "Psicosocial" },
        ];

      return normalizeOptions(options);
    },
    referralOptions() {
      return normalizeOptions(this.catalogs.referral_options);
    },
    callStatusOptions() {
      return normalizeOptions(this.catalogs.call_status_options);
    },
    followUpStatusOptions() {
      return normalizeOptions(this.catalogs.follow_up_status_options);
    },
    medicationOptions() {
      return [{ value: null, text: "Sin medicamento" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: item.commercial_name || item.name,
        }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Sin funcionario" }].concat(
        (this.catalogs.staff || []).map((item) => ({
          value: item.id,
          text: item.full_name,
        }))
      );
    },
    userOptions() {
      return [{ value: null, text: "Automático" }].concat(
        (this.catalogs.users || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await Promise.all([
      this.loadAttentions(),
      this.loadCertificateLogo(),
    ]);
  },
  watch: {
    "form.attention_category"(value) {
      if (value !== MENTAL_HEALTH_CATEGORY) {
        this.form.mental_health_event_type = null;
        this.form.self_harm_injury_type = null;
      }

      if (!this.isAccidentCategory(value)) {
        this.form.accident_location_type = null;
        this.form.dependency_id = null;
        this.form.accident_circumstance = "";
        return;
      }

      if (!this.form.accident_location_type) {
        this.form.accident_location_type = "colegio";
      }
    },
    "form.mental_health_event_type"(value) {
      if (value !== "autolesion") {
        this.form.self_harm_injury_type = null;
      }
    },
    "form.accident_location_type"(value) {
      if (value !== "colegio") {
        this.form.dependency_id = null;
      }
    },
    "form.accompanied_by_type"(value) {
      if (!this.requiresCompanionStaffType(value)) {
        this.form.accompanied_by_staff_id = null;
      }

      if (!this.usesCompanionNameType(value)) {
        this.form.accompanied_by_name = "";
      }
    },
    "form.attended_at"(value) {
      if (!this.form.occurred_at) {
        this.form.occurred_at = value;
      }
    },
  },
  methods: {
    formatInfirmaryDateTime,
    humanizeInfirmaryStatus,
    normalizeOptions,
    attentionCategoryLabel(value) {
      return this.attentionCategoryLabels[value] || humanizeInfirmaryStatus(value);
    },
    mentalHealthEventLabel(value) {
      return this.optionLabel(this.mentalHealthEventOptions, value);
    },
    selfHarmInjuryLabel(value) {
      return this.optionLabel(this.selfHarmInjuryOptions, value);
    },
    accidentLocationLabel(value) {
      return this.accidentLocationLabels[value] || humanizeInfirmaryStatus(value);
    },
    formatDateOnly(value) {
      if (!value) return "-";

      return new Date(value).toLocaleDateString("es-CL", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      });
    },
    formatTimeOnly(value) {
      if (!value) return "-";

      return new Date(value).toLocaleTimeString("es-CL", {
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    accidentReference(attention) {
      return `N° ${String(attention.correlative_number || attention.id || "").padStart(5, "0")}`;
    },
    attentionDetail(attention) {
      return [attention.consultation_reason, attention.accident_circumstance, attention.logbook]
        .filter(Boolean)
        .join(" · ");
    },
    studentName(attention) {
      return attention?.student_full_name_snapshot || [attention?.student?.first_name, attention?.student?.last_name].filter(Boolean).join(" ") || "-";
    },
    dependencyLabel(attention) {
      return attention?.dependency?.name || this.optionLabel(this.dependencyOptions, attention?.dependency_id) || "-";
    },
    companionSummary(attention) {
      if (!attention?.accompanied_by_type || attention.accompanied_by_type === "sin_acompanante") {
        return "Sin acompañante";
      }

      return [
        this.optionLabel(this.companionOptions, attention.accompanied_by_type),
        attention.accompanied_by_staff?.full_name || attention.accompanied_by_name,
      ].filter(Boolean).join(" · ");
    },
    guardianContacts(attention) {
      const contextContacts = attention?.student_context?.emergency_contacts;

      if (Array.isArray(contextContacts) && contextContacts.length) {
        return contextContacts;
      }

      const student = attention?.student || {};

      return [
        {
          type: "primary",
          label: "Apoderado principal",
          name: student.guardian_name,
          rut: student.guardian_rut,
          relationship: student.guardian_relationship || student.guardian_role,
          phone: student.guardian_phone,
          email: student.guardian_email,
        },
        {
          type: "backup",
          label: "Apoderado suplente",
          name: student.guardian_backup_name,
          rut: student.guardian_backup_rut,
          relationship: student.guardian_backup_relationship || student.guardian_backup_role,
          phone: student.guardian_backup_phone,
          email: student.guardian_backup_email,
        },
      ];
    },
    primaryGuardianRestriction(contact) {
      return (contact?.restrictions || [])[0] || null;
    },
    guardianRestrictionValidity(restriction) {
      if (!restriction) return "";

      const from = restriction.starts_on ? this.formatDateOnly(`${restriction.starts_on}T12:00:00`) : null;
      const to = restriction.ends_on ? this.formatDateOnly(`${restriction.ends_on}T12:00:00`) : "sin fecha de término";

      return [from ? `Desde ${from}` : null, `hasta ${to}`].filter(Boolean).join(" ");
    },
    optionLabel(options, value) {
      return (options || []).find((option) => String(option.value) === String(value))?.text || humanizeInfirmaryStatus(value);
    },
    attentionDerivationLabel(attention) {
      const treatment = (attention.treatments || []).find((item) => {
        return item.derivation_type || (item.derivation_support_teams || []).length;
      });

      if (treatment) {
        const parts = [];

        if (treatment.derivation_type) {
          parts.push(this.optionLabel(this.treatmentDerivationOptions, treatment.derivation_type));
        }

        (treatment.derivation_support_teams || []).forEach((team) => {
          parts.push(this.optionLabel(this.treatmentDerivationSupportOptions, team));
        });

        return parts.length ? parts.join(" · ") : "Sin derivación";
      }

      const referral = (attention.referrals || [])[0];

      if (!referral) {
        return "Sin derivación";
      }

      return [humanizeInfirmaryStatus(referral.referral_type), referral.result]
        .filter(Boolean)
        .join(" · ");
    },
    treatmentCategorySummary(treatment) {
      const categories = treatment?.treatment_categories?.length
        ? treatment.treatment_categories
        : this.inferTreatmentCategories(treatment || {});

      return categories.map((category) => this.optionLabel(this.treatmentCategoryOptions, category)).join(" · ") || "-";
    },
    treatmentTypeSummary(treatment) {
      return (treatment?.treatment_types || [])
        .map((type) => this.optionLabel(this.physicalTreatmentOptions, type))
        .join(" · ") || "-";
    },
    treatmentCsvSummary(treatment) {
      return [
        treatment?.blood_pressure ? `PA ${treatment.blood_pressure}` : null,
        treatment?.pulse ? `Pulso ${treatment.pulse}` : null,
        treatment?.oxygen_saturation ? `Sat. ${treatment.oxygen_saturation}%` : null,
      ].filter(Boolean).join(" · ") || "-";
    },
    treatmentDerivationSummary(treatment) {
      return [
        treatment?.derivation_type ? this.optionLabel(this.treatmentDerivationOptions, treatment.derivation_type) : null,
        ...(treatment?.derivation_support_teams || []).map((team) => this.optionLabel(this.treatmentDerivationSupportOptions, team)),
      ].filter(Boolean).join(" · ") || "-";
    },
    referralSummary(referral) {
      return [
        humanizeInfirmaryStatus(referral?.referral_type),
        referral?.result,
        referral?.responsible_user?.name || referral?.responsible_name,
      ].filter(Boolean).join(" · ") || "-";
    },
    isAccidentCategory(value) {
      return ["accidente_menor", "accidente_mayor"].includes(value);
    },
    isMentalHealthCategory(value) {
      return value === MENTAL_HEALTH_CATEGORY;
    },
    requiresCompanionStaffType(type) {
      return ["inspectora", "asistente_aula"].includes(type);
    },
    usesCompanionNameType(type) {
      return ["apoderado", "otro"].includes(type);
    },
    companionStaffItems(type) {
      return (this.catalogs.companion_staff?.[type] || []).map((item) => ({
        value: item.id,
        text: [
          item.full_name,
          item.cargo_name,
          ...(item.department_names || []),
        ].filter(Boolean).join(" · "),
      }));
    },
    treatmentHasCategory(treatment, category) {
      return (treatment.treatment_categories || []).includes(category);
    },
    treatmentHasMedication(treatment) {
      return (treatment.treatment_types || []).some((type) => ["administracion_medicamento", "medicamento_sos"].includes(type));
    },
    inferTreatmentCategories(treatment) {
      const categories = new Set(treatment.treatment_categories || []);
      const types = treatment.treatment_types || [];
      const physicalTypes = this.physicalTreatmentOptions.map((option) => option.value);

      if (types.some((type) => physicalTypes.includes(type))) categories.add("fisico");
      if (treatment.emotional_support_required || treatment.emotional_comment) categories.add("emocional");
      if (treatment.derivation_type || (treatment.derivation_support_teams || []).length) categories.add("derivacion");
      if (treatment.blood_pressure || treatment.pulse || treatment.respiratory_rate || treatment.temperature || treatment.oxygen_saturation || treatment.weight || treatment.height || treatment.vital_signs_notes) categories.add("csv");
      if (types.includes("otro") || treatment.treatment_other || treatment.other_treatments) categories.add("otro");

      return Array.from(categories);
    },
    isOccurredAfterRegistered(occurredAt, attendedAt) {
      if (!occurredAt || !attendedAt) return false;

      return new Date(occurredAt).getTime() > new Date(attendedAt).getTime();
    },
    emptyTreatment() {
      return {
        treatment_categories: ["fisico"],
        treatment_types: [],
        derivation_type: null,
        derivation_support_teams: [],
        treatment_other: "",
        medication_id: null,
        medication_quantity: 1,
        blood_pressure: "",
        pulse: null,
        respiratory_rate: null,
        temperature: null,
        oxygen_saturation: null,
        weight: null,
        height: null,
        vital_signs_notes: "",
        emotional_support_required: false,
        emotional_comment: "",
        emotional_support_type: "",
        emotional_duration_minutes: null,
        emotional_professional_id: null,
        other_treatments: "",
        notes: "",
      };
    },
    emptyReferral() {
      return {
        referral_type: "regresa_a_sala",
        referred_at: toInputDateTime(new Date().toISOString()),
        responsible_user_id: null,
        responsible_name: "",
        reason: "",
        observations: "",
        result: "",
      };
    },
    emptyCall() {
      return {
        called_at: toInputDateTime(new Date().toISOString()),
        person_contacted: "",
        relationship: "",
        phone_number: "",
        call_status: "pendiente",
        reason: "",
        conversation_summary: "",
        commitments: "",
        estimated_arrival_at: "",
        duration_minutes: 5,
        called_by_user_id: null,
      };
    },
    emptyFollowUp() {
      return {
        followed_at: toInputDateTime(new Date().toISOString()),
        responsible_user_id: null,
        comment: "",
        status: "pendiente",
        next_review_at: "",
      };
    },
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        attention_category: "accidente_menor",
        mental_health_event_type: null,
        self_harm_injury_type: null,
        accident_location_type: "colegio",
        occurred_at: toInputDateTime(new Date().toISOString()),
        attended_at: toInputDateTime(new Date().toISOString()),
        referred_by_staff_id: null,
        dependency_id: null,
        accompanied_by_type: "sin_acompanante",
        accompanied_by_staff_id: null,
        accompanied_by_name: "",
        consultation_reason: "",
        accident_circumstance: "",
        logbook: "",
        initial_description: "",
        observations: "",
        attention_duration_minutes: 15,
        priority: "media",
        status: "abierta",
        treatments: [this.emptyTreatment()],
        referrals: [],
        calls: [],
        follow_ups: [],
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadAttentions(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/attentions", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.attentions = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 12,
        };
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudieron cargar las atenciones.");
      } finally {
        this.loading = false;
      }
    },
    async fetchAttentionDetail(attention) {
      const id = typeof attention === "object" ? attention.id : attention;
      const response = await axios.get(`/api/infirmary/attentions/${id}`);
      return response.data.data;
    },
    async openView(attention) {
      try {
        this.selectedAttention = await this.fetchAttentionDetail(attention);
        this.showViewModal = true;
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo cargar la ficha."));
      }
    },
    async editFromView() {
      if (!this.selectedAttention) return;

      const attention = this.selectedAttention;
      this.showViewModal = false;
      await this.openEdit(attention);
    },
    blobToDataUrl(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },
    async loadCertificateLogo() {
      try {
        const logoUrl = this.catalogs.school_insurance_certificate?.logo_url || "/brand/logo-cnsc.png";
        const response = await fetch(logoUrl);
        if (!response.ok) return;

        this.certificateLogoDataUrl = await this.blobToDataUrl(await response.blob());
      } catch (error) {
        this.certificateLogoDataUrl = null;
      }
    },
    async openSchoolInsuranceCertificate(attention) {
      if (!this.isAccidentCategory(attention?.attention_category)) {
        await showInfirmaryWarning("El certificado de seguro escolar solo está disponible para accidentes.");
        return;
      }

      try {
        const detail = await this.fetchAttentionDetail(attention);
        this.schoolInsuranceCertificateForm = createSchoolInsuranceCertificateForm(
          detail,
          this.catalogs.school_insurance_certificate || {}
        );
        this.showViewModal = false;
        this.showSchoolInsuranceCertificateModal = true;
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudieron precargar los datos del certificado."));
      }
    },
    async exportSchoolInsuranceCertificate() {
      const form = this.schoolInsuranceCertificateForm;
      if (!form) return;

      const requiredFields = [
        [form.registration_date, "fecha de registro"],
        [form.rbd, "RBD"],
        [form.establishment_name, "nombre del establecimiento"],
        [form.given_names, "nombres de la estudiante"],
        [form.paternal_surname, "apellido paterno"],
        [form.rut, "RUT de la estudiante"],
        [form.occurred_at, "fecha y hora del accidente"],
        [form.circumstance, "circunstancia del accidente"],
      ];
      const missing = requiredFields.filter(([value]) => !String(value || "").trim()).map(([, label]) => label);

      if (missing.length) {
        await showInfirmaryWarning(`Completa: ${missing.join(", ")}.`);
        return;
      }

      this.exportingSchoolInsuranceCertificate = true;
      try {
        if (!this.certificateLogoDataUrl) {
          await this.loadCertificateLogo();
        }

        const pdfMake = await getPdfMake();
        const definition = buildSchoolInsuranceCertificateDefinition(form, this.certificateLogoDataUrl);
        pdfMake.createPdf(definition).download(schoolInsuranceCertificateFileName(form));
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo generar el certificado de seguro escolar."));
      } finally {
        this.exportingSchoolInsuranceCertificate = false;
      }
    },
    pdfValue(value) {
      if (value === null || value === undefined || value === "") {
        return "-";
      }

      return String(value);
    },
    pdfFileSegment(value) {
      return String(value || "ficha")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^\w-]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .toLowerCase() || "ficha";
    },
    pdfKeyValueSection(title, rows) {
      return [
        { text: title, style: "section" },
        {
          table: {
            widths: ["32%", "*"],
            body: rows.map(([label, value]) => [
              { text: label, style: "tableLabel" },
              this.pdfValue(value),
            ]),
          },
          layout: "lightHorizontalLines",
          margin: [0, 0, 0, 10],
        },
      ];
    },
    pdfDataTableSection(title, headers, rows, widths) {
      return [
        { text: title, style: "section" },
        {
          table: {
            headerRows: 1,
            widths,
            body: [
              headers.map((header) => ({ text: header, style: "tableHeader" })),
              ...rows.map((row) => row.map((cell) => this.pdfValue(cell))),
            ],
          },
          layout: "lightHorizontalLines",
          margin: [0, 0, 0, 10],
        },
      ];
    },
    attentionTreatmentPdfRows(attention) {
      const treatments = attention.treatments || [];

      if (!treatments.length) {
        return [["-", "Sin tratamientos registrados.", "-", "-"]];
      }

      return treatments.map((treatment, index) => {
        const details = [
          this.treatmentHasCategory(treatment, "fisico") || treatment.treatment_types?.length
            ? `Físico: ${this.treatmentTypeSummary(treatment)}`
            : null,
          this.treatmentHasCategory(treatment, "csv")
            ? `CSV: ${this.treatmentCsvSummary(treatment)}`
            : null,
          this.treatmentHasCategory(treatment, "derivacion")
            ? `Derivación: ${this.treatmentDerivationSummary(treatment)}`
            : null,
          treatment.medication
            ? `Medicamento: ${treatment.medication.commercial_name || treatment.medication.name}`
            : null,
          this.treatmentHasCategory(treatment, "emocional")
            ? `Emocional: ${treatment.emotional_comment || "-"}`
            : null,
          this.treatmentHasCategory(treatment, "otro")
            ? `Otro: ${treatment.other_treatments || treatment.treatment_other || "-"}`
            : null,
        ].filter(Boolean).join("\n");

        return [
          String(index + 1),
          this.treatmentCategorySummary(treatment),
          details || "-",
          treatment.notes || "-",
        ];
      });
    },
    attentionReferralPdfRows(attention) {
      const referrals = attention.referrals || [];

      if (!referrals.length) {
        return [["-", "Sin derivaciones registradas.", "-", "-"]];
      }

      return referrals.map((referral) => [
        this.formatInfirmaryDateTime(referral.referred_at),
        this.referralSummary(referral),
        referral.reason || "-",
        referral.observations || "-",
      ]);
    },
    attentionCallPdfRows(attention) {
      const calls = attention.calls || [];

      if (!calls.length) {
        return [["-", "Sin llamados registrados.", "-", "-"]];
      }

      return calls.map((call) => [
        this.formatInfirmaryDateTime(call.called_at),
        call.person_contacted || "-",
        humanizeInfirmaryStatus(call.call_status),
        call.conversation_summary || call.reason || "-",
      ]);
    },
    attentionFollowUpPdfRows(attention) {
      const followUps = attention.follow_ups || [];

      if (!followUps.length) {
        return [["-", "Sin seguimiento registrado.", "-", "-"]];
      }

      return followUps.map((followUp) => [
        this.formatInfirmaryDateTime(followUp.followed_at),
        humanizeInfirmaryStatus(followUp.status),
        followUp.responsible_user?.name || "-",
        followUp.comment || "-",
      ]);
    },
    async exportAttentionPdf(attention) {
      if (!attention?.id) return;

      try {
        const detail = await this.fetchAttentionDetail(attention);
        const pdfMake = await getPdfMake();
        const reference = this.accidentReference(detail);
        const student = this.studentName(detail);
        const fileName = `ficha_atencion_${String(detail.correlative_number || detail.id).padStart(5, "0")}_${this.pdfFileSegment(student)}.pdf`;
        const attentionDataRows = [
          ["Fecha del evento", this.formatDateOnly(detail.occurred_at || detail.attended_at)],
          ["Hora del evento", this.formatTimeOnly(detail.occurred_at || detail.attended_at)],
          ["Fecha de registro", this.formatInfirmaryDateTime(detail.attended_at)],
          ["Categorización", this.attentionCategoryLabel(detail.attention_category)],
        ];

        if (detail.attention_category === MENTAL_HEALTH_CATEGORY) {
          attentionDataRows.push([
            "Tipo de atención de salud mental",
            this.mentalHealthEventLabel(detail.mental_health_event_type),
          ]);

          if (detail.mental_health_event_type === "autolesion") {
            attentionDataRows.push([
              "Tipo de lesión por autolesión",
              this.selfHarmInjuryLabel(detail.self_harm_injury_type),
            ]);
          }
        }

        attentionDataRows.push(
          ["Ubicación", detail.accident_location_type ? this.accidentLocationLabel(detail.accident_location_type) : "-"],
          ["Dependencia", this.dependencyLabel(detail)],
          ["Quién acompaña", this.companionSummary(detail)],
          ["Derivación", this.attentionDerivationLabel(detail)],
        );
        const content = [
          { text: "Ficha de Atención de Enfermería", style: "title" },
          {
            text: `${reference} · Generada el ${this.formatInfirmaryDateTime(new Date())}`,
            style: "subtitle",
          },
          ...this.pdfKeyValueSection("Ficha de la estudiante", [
            ["Estudiante", student],
            ["RUT", detail.student?.rut || detail.student_rut_snapshot],
            ["Curso", detail.course_name_snapshot || detail.course_section?.display_name],
            ["N° correlativo", reference],
          ]),
          ...this.pdfKeyValueSection("Datos de la atención", attentionDataRows),
          ...this.pdfKeyValueSection("Detalle clínico", [
            ["Motivo de consulta", detail.consultation_reason],
            ["Circunstancia del accidente", detail.accident_circumstance],
            ["Bitácora", detail.logbook],
          ]),
          ...this.pdfDataTableSection(
            "Tratamientos aplicados",
            ["#", "Categorías", "Detalle", "Notas"],
            this.attentionTreatmentPdfRows(detail),
            ["8%", "22%", "45%", "25%"]
          ),
          ...this.pdfDataTableSection(
            "Derivaciones",
            ["Fecha", "Destino / equipo", "Motivo", "Observaciones"],
            this.attentionReferralPdfRows(detail),
            ["20%", "28%", "26%", "26%"]
          ),
          ...this.pdfDataTableSection(
            "Llamados",
            ["Fecha", "Contacto", "Estado", "Resumen"],
            this.attentionCallPdfRows(detail),
            ["20%", "25%", "18%", "37%"]
          ),
          ...this.pdfDataTableSection(
            "Seguimiento",
            ["Fecha", "Estado", "Responsable", "Comentario"],
            this.attentionFollowUpPdfRows(detail),
            ["20%", "18%", "24%", "38%"]
          ),
        ];

        pdfMake.createPdf({
          pageSize: "LETTER",
          pageMargins: [36, 40, 36, 44],
          content,
          footer: (currentPage, pageCount) => ({
            text: `Página ${currentPage} de ${pageCount}`,
            alignment: "right",
            margin: [0, 0, 36, 0],
            fontSize: 8,
            color: "#74788d",
          }),
          styles: {
            title: { fontSize: 18, bold: true, color: "#2a3042", margin: [0, 0, 0, 4] },
            subtitle: { fontSize: 9, color: "#74788d", margin: [0, 0, 0, 14] },
            section: { fontSize: 12, bold: true, color: "#2a3042", margin: [0, 8, 0, 6] },
            tableHeader: { bold: true, color: "#2a3042", fillColor: "#eff2f7" },
            tableLabel: { bold: true, color: "#2a3042", fillColor: "#f8fafc" },
          },
          defaultStyle: { fontSize: 9, color: "#4b5563" },
        }).download(fileName);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo exportar la ficha a PDF."));
      }
    },
    async selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
      this.selectedStudentContext = student.medical_context || null;
      if (!this.selectedStudentContext) {
        await this.loadStudentMedicalContext(student.id);
      }
    },
    clearFormStudent() {
      this.studentContextRequestId += 1;
      this.form.student_profile_id = null;
      this.form.student_label = "";
      this.selectedStudentContext = null;
      this.studentContextLoading = false;
    },
    async loadStudentMedicalContext(studentId) {
      if (!studentId) {
        this.selectedStudentContext = null;
        return;
      }

      const requestId = ++this.studentContextRequestId;
      this.studentContextLoading = true;
      try {
        const response = await axios.get(`/api/infirmary/students/${studentId}/context`);
        if (requestId === this.studentContextRequestId && Number(this.form.student_profile_id) === Number(studentId)) {
          this.selectedStudentContext = response.data.data;
        }
      } catch (error) {
        if (requestId === this.studentContextRequestId) {
          this.selectedStudentContext = null;
          await showInfirmaryError(formatInfirmaryError(error, "No se pudo cargar la ficha médica de la estudiante."));
        }
      } finally {
        if (requestId === this.studentContextRequestId) this.studentContextLoading = false;
      }
    },
    swalMedicalContextHtml(student) {
      const context = student?.medical_context || {};
      const alerts = context.medical_alerts || [];
      const guardianRestrictions = context.guardian_restrictions || [];
      const totalAlerts = alerts.length + guardianRestrictions.length;
      const alertHtml = alerts.length
        ? alerts.map((alert) => `
            <div class="swal-medical-alert swal-medical-alert--${this.escapeHtml(alert.level || "info")}">
              <strong>${this.escapeHtml(alert.label)}</strong>
              <span>${this.escapeHtml(alert.detail || "Revisar ficha médica")}</span>
            </div>
          `).join("")
        : '<div class="swal-medical-ok"><i class="bx bxs-check-shield"></i> Sin alertas médicas registradas</div>';
      const medications = (context.permanent_medications || [])
        .map((item) => [item.medication_name, [item.dose_amount, item.dose_unit].filter(Boolean).join(" ") || item.dose, item.frequency || item.schedule_text].filter(Boolean).join(" · "));
      const guardianContacts = (context.emergency_contacts || [])
        .map((contact) => {
          const restriction = this.primaryGuardianRestriction(contact);
          const restrictionHtml = restriction ? `
            <div class="swal-guardian-contact__restriction">
              <b><i class="bx bxs-error-shield"></i> NO CONTACTAR SIN VALIDAR</b>
              <strong>${this.escapeHtml(restriction.restriction_type_label || "Restricción vigente")}</strong>
              <small>${this.escapeHtml(restriction.reason || contact.contact_guidance || "Validar protocolo con Trabajo Social.")}</small>
            </div>
          ` : "";

          return `
          <div class="swal-guardian-contact${restriction ? " swal-guardian-contact--restricted" : ""}">
            <span>${this.escapeHtml(contact.label || (contact.type === "backup" ? "Apoderado suplente" : "Apoderado principal"))}</span>
            <strong>${this.escapeHtml(contact.name || "Sin nombre registrado")}</strong>
            <small><i class="bx bx-phone"></i> ${this.escapeHtml(contact.phone || "Sin teléfono registrado")}</small>
            <small><i class="bx bx-envelope"></i> ${this.escapeHtml(contact.email || "Sin correo registrado")}</small>
            ${restrictionHtml}
          </div>
        `;
        })
        .join("");
      const protectionAlert = guardianRestrictions.length ? `
        <div class="swal-protection-alert">
          <i class="bx bxs-shield-x"></i>
          <div>
            <strong>Alerta configurada por Trabajo Social</strong>
            <span>Hay ${guardianRestrictions.length} medida${guardianRestrictions.length === 1 ? "" : "s"} vigente${guardianRestrictions.length === 1 ? "" : "s"}. Revisa el contacto antes de llamar.</span>
          </div>
        </div>
      ` : "";

      return `
        <div class="swal-medical-context">
          <div class="swal-medical-context__head">
            <div><strong>${this.escapeHtml(student.full_name)}</strong><span>${this.escapeHtml(student.rut || "Sin RUT")} · ${this.escapeHtml(student.course || "Sin curso")} · ${this.escapeHtml(student.age ?? "-")} años</span></div>
            <b>${totalAlerts ? `${totalAlerts} alerta${totalAlerts === 1 ? "" : "s"}` : "Ficha revisada"}</b>
          </div>
          <div class="swal-medical-alerts">${alertHtml}</div>
          <div class="swal-medical-facts">
            <div><span>Grupo sanguíneo</span><strong>${this.escapeHtml(context.blood_type || "Sin información")}</strong></div>
            <div><span>Previsión</span><strong>${this.escapeHtml(context.health_insurance || "Sin información")}</strong></div>
            <div><span>Educación Física</span><strong>${context.fit_for_physical_education === false ? "No apta" : context.fit_for_physical_education === true ? "Apta" : "Sin información"}</strong></div>
          </div>
          ${protectionAlert}
          <div class="swal-guardian-contacts">
            <div class="swal-guardian-contacts__title"><i class="bx bx-phone-call"></i> Contactos de apoderados</div>
            <div class="swal-guardian-contacts__grid">
              ${guardianContacts || '<div class="swal-guardian-contact swal-guardian-contact--empty">Sin apoderados registrados.</div>'}
            </div>
          </div>
          ${medications.length ? `<div class="swal-medical-detail"><strong>Medicación vigente</strong><span>${medications.map((item) => this.escapeHtml(item)).join("<br>")}</span></div>` : ""}
          ${context.health_observations ? `<div class="swal-medical-detail"><strong>Observaciones de salud</strong><span>${this.escapeHtml(context.health_observations)}</span></div>` : ""}
        </div>
      `;
    },
    resetFilters() {
      this.filters = {
        search: "",
        student_profile_id: null,
        attention_category: null,
        status: null,
        priority: null,
        course_section_id: null,
        from: "",
        to: "",
      };
      this.loadAttentions(1);
    },
    escapeHtml(value) {
      const element = document.createElement("div");
      element.textContent = value ?? "";
      return element.innerHTML;
    },
    swalSelectOptions(options, selectedValue = null, includeEmpty = false, emptyLabel = "Seleccione") {
      const items = (options || []).map((item) => {
        if (typeof item === "string") {
          return { value: item, text: humanizeInfirmaryStatus(item) };
        }

        return {
          value: item.value ?? item.id,
          text: item.text ?? item.label ?? item.name ?? item.display_name ?? item.full_name ?? humanizeInfirmaryStatus(item.value ?? item.id),
        };
      });

      const normalized = includeEmpty ? [{ value: "", text: emptyLabel }].concat(items) : items;

      return normalized
        .map((option) => {
          const value = option.value ?? "";
          const selected = String(value) === String(selectedValue ?? "") ? " selected" : "";
          return `<option value="${this.escapeHtml(value)}"${selected}>${this.escapeHtml(option.text)}</option>`;
        })
        .join("");
    },
    swalCheckboxOptions(options, className, idPrefix, selectedValues = []) {
      const items = (options || [])
        .map((option) => {
          const checked = selectedValues.includes(option.value) ? " checked" : "";
          const id = `${idPrefix}-${option.value}`;

          return `
            <label class="infirmary-check-chip" for="${this.escapeHtml(id)}">
              <input id="${this.escapeHtml(id)}" class="infirmary-check-chip__input ${this.escapeHtml(className)}" type="checkbox" value="${this.escapeHtml(option.value)}"${checked} />
              <span class="infirmary-check-chip__box"></span>
              <span class="infirmary-check-chip__text">${this.escapeHtml(option.text)}</span>
            </label>
          `;
        })
        .join("");

      return `<div class="infirmary-chip-group">${items}</div>`;
    },
    async openCreate() {
      let selectedStudent = null;
      let attentionMode = "full";
      const quickReasonOptions = QUICK_ATTENTION_REASONS.map((option) => `
        <label class="infirmary-quick-choice">
          <input class="infirmary-quick-choice__input swal-quick-reason" type="radio" name="swal-quick-reason" value="${this.escapeHtml(option.value)}" />
          <span class="infirmary-quick-choice__content">
            <i class="bx ${this.escapeHtml(option.icon)}"></i>
            <strong>${this.escapeHtml(option.label)}</strong>
          </span>
        </label>
      `).join("");
      const quickActionOptions = QUICK_ATTENTION_ACTIONS.map((option) => `
        <label class="infirmary-quick-action">
          <input class="infirmary-quick-action__input swal-quick-action" type="checkbox" value="${this.escapeHtml(option.value)}" />
          <span class="infirmary-quick-action__content">
            <span class="infirmary-quick-action__icon"><i class="bx ${this.escapeHtml(option.icon)}"></i></span>
            <span>
              <strong>${this.escapeHtml(option.label)}</strong>
              <small>${this.escapeHtml(option.description)}</small>
            </span>
            <i class="bx bx-check infirmary-quick-action__check"></i>
          </span>
        </label>
      `).join("");
      const quickResultOptions = QUICK_ATTENTION_RESULTS.map((option, index) => `
        <label class="infirmary-quick-result">
          <input class="infirmary-quick-result__input swal-quick-result" type="radio" name="swal-quick-result" value="${this.escapeHtml(option.value)}"${index === 0 ? " checked" : ""} />
          <span class="infirmary-quick-result__content">
            <i class="bx ${this.escapeHtml(option.icon)}"></i>
            <span>
              <strong>${this.escapeHtml(option.label)}</strong>
              <small>${this.escapeHtml(option.description)}</small>
            </span>
          </span>
        </label>
      `).join("");
      const result = await Swal.fire({
        title: "Nueva ficha de atención",
        width: "72rem",
        customClass: {
          popup: "infirmary-attention-swal",
        },
        html: `
          <div class="text-start">
            <div class="mb-3">
              <label class="form-label">Estudiante</label>
              <div class="input-group swal-student-search-group">
                <input id="swal-student-search" class="form-control" autocomplete="off" placeholder="Buscar por nombre, apellido o RUT" />
                <button id="swal-student-search-button" type="button" class="btn btn-primary">Buscar</button>
              </div>
              <div id="swal-student-selected" class="small text-muted mt-2">Sin estudiante seleccionada.</div>
              <div id="swal-student-results" class="list-group mt-2"></div>
            </div>
            <div class="infirmary-attention-mode-tabs" role="tablist" aria-label="Tipo de registro de atención">
              <button id="swal-mode-full" class="infirmary-attention-mode-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="swal-full-form" data-attention-mode="full">
                <span class="infirmary-attention-mode-tab__icon"><i class="bx bx-clipboard"></i></span>
                <span>
                  <strong>Ficha completa</strong>
                  <small>Evaluación, llamados, tratamientos y derivaciones</small>
                </span>
              </button>
              <button id="swal-mode-quick" class="infirmary-attention-mode-tab infirmary-attention-mode-tab--quick" type="button" role="tab" aria-selected="false" aria-controls="swal-quick-form" data-attention-mode="quick">
                <span class="infirmary-attention-mode-tab__icon"><i class="bx bx-bolt-circle"></i></span>
                <span>
                  <strong>Atención rápida</strong>
                  <small>Medidas menores con selección por clic</small>
                </span>
              </button>
            </div>
            <div id="swal-full-form" class="row g-3" role="tabpanel" aria-labelledby="swal-mode-full">
              <div class="col-md-4">
                <label class="form-label">Fecha de accidente</label>
                <input id="swal-occurred-at" type="datetime-local" class="form-control" value="${this.escapeHtml(toInputDateTime(new Date().toISOString()))}" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Fecha de registro</label>
                <input id="swal-attended-at" type="datetime-local" class="form-control" value="${this.escapeHtml(toInputDateTime(new Date().toISOString()))}" />
              </div>
              <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <select id="swal-attention-category" class="form-select">${this.swalSelectOptions(this.catalogs.attention_categories, "accidente_menor")}</select>
              </div>
              <div id="swal-mental-health-wrapper" class="col-12 d-none">
                <div class="infirmary-mental-health-panel">
                  <div class="infirmary-mental-health-panel__heading">
                    <span><i class="bx bx-heart"></i></span>
                    <div>
                      <strong>Categorización de salud mental</strong>
                      <small>Selecciona el evento observado. Registra solo antecedentes necesarios para la atención.</small>
                    </div>
                  </div>
                  <div class="infirmary-field-grid">
                    <div>
                      <label class="form-label" for="swal-mental-health-event-type">Tipo de atención</label>
                      <select id="swal-mental-health-event-type" class="form-select">${this.swalSelectOptions(this.mentalHealthEventOptions, null)}</select>
                    </div>
                    <div id="swal-self-harm-injury-wrapper" class="d-none">
                      <label class="form-label" for="swal-self-harm-injury-type">Tipo de lesión por autolesión</label>
                      <select id="swal-self-harm-injury-type" class="form-select">${this.swalSelectOptions(this.selfHarmInjuryOptions, null)}</select>
                    </div>
                  </div>
                </div>
              </div>
              <div id="swal-accident-location-wrapper" class="col-md-4">
                <label class="form-label">Tipo de accidente</label>
                <select id="swal-accident-location-type" class="form-select">${this.swalSelectOptions(this.accidentLocationOptions, "colegio")}</select>
              </div>
              <div id="swal-dependency-wrapper" class="col-md-4">
                <label class="form-label">Dependencia</label>
                <select id="swal-dependency-id" class="form-select">${this.swalSelectOptions(this.dependencyOptions, null)}</select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Quién acompaña</label>
                <select id="swal-accompanied-by-type" class="form-select">${this.swalSelectOptions(this.catalogs.companion_options, "sin_acompanante")}</select>
              </div>
              <div id="swal-accompanied-by-staff-wrapper" class="col-md-4 d-none">
                <label class="form-label">Funcionaria que acompaña</label>
                <select id="swal-accompanied-by-staff-id" class="form-select"></select>
              </div>
              <div id="swal-accompanied-by-name-wrapper" class="col-md-8 d-none">
                <label class="form-label">Detalle acompañante</label>
                <input id="swal-accompanied-by-name" class="form-control" />
              </div>
              <div class="col-12">
                <label class="form-label">Motivo de consulta</label>
                <textarea
                  id="swal-consultation-reason"
                  class="form-control"
                  rows="3"
                  placeholder="Ej: dolor de cabeza persistente, mareo o malestar abdominal"
                ></textarea>
              </div>
              <div id="swal-accident-circumstance-wrapper" class="col-12">
                <label class="form-label">Circunstancia del accidente</label>
                <textarea
                  id="swal-accident-circumstance"
                  class="form-control"
                  rows="3"
                  placeholder="Ej: alumna cae en clases de educación física"
                ></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Bitácora</label>
                <textarea
                  id="swal-logbook"
                  class="form-control"
                  rows="3"
                  placeholder="Ej: antecedentes, indicaciones recibidas o información relevante para seguimiento"
                ></textarea>
              </div>
              <div class="col-12">
                <div class="infirmary-call-section">
                  <label class="infirmary-call-toggle" for="swal-register-call">
                    <input id="swal-register-call" type="checkbox" />
                    <span class="infirmary-call-toggle__icon"><i class="bx bx-phone-call"></i></span>
                    <span>
                      <strong>Registrar llamado telefónico</strong>
                      <small>Activa esta opción para dejar constancia de lo ocurrido durante la llamada.</small>
                    </span>
                  </label>
                  <div id="swal-call-wrapper" class="row g-3 mt-1 d-none">
                    <div class="col-md-6">
                      <label class="form-label">Contacto registrado</label>
                      <select id="swal-call-contact" class="form-select"></select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Resultado de la llamada</label>
                      <select id="swal-call-status" class="form-select">${this.swalSelectOptions(this.catalogs.call_status_options, "contesto")}</select>
                    </div>
                    <div class="col-12">
                      <div id="swal-call-restriction-alert" class="swal-call-restriction-alert d-none" role="alert"></div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Persona contactada</label>
                      <input id="swal-call-person" class="form-control" placeholder="Nombre del apoderado" />
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Relación</label>
                      <input id="swal-call-relationship" class="form-control" placeholder="Ej: madre, padre, tutor" />
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Número telefónico</label>
                      <input id="swal-call-phone" class="form-control" type="tel" placeholder="Número utilizado" />
                    </div>
                    <div class="col-12">
                      <label class="form-label">¿Qué ocurrió durante la llamada?</label>
                      <textarea
                        id="swal-call-summary"
                        class="form-control"
                        rows="3"
                        placeholder="Registra lo conversado, indicaciones entregadas y respuesta del apoderado"
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label d-block">Categoría Tratamiento</label>
                ${this.swalCheckboxOptions(this.treatmentCategoryOptions, "swal-treatment-category", "swal-treatment-category", ["fisico"])}
              </div>
              <div id="swal-physical-treatment-wrapper" class="col-12">
                <div class="infirmary-treatment-section">
                  <div class="infirmary-treatment-section__title">Físico</div>
                  ${this.swalCheckboxOptions(this.physicalTreatmentOptions, "swal-physical-treatment", "swal-physical-treatment")}
                </div>
              </div>
              <div id="swal-emotional-treatment-wrapper" class="col-12 d-none">
                <div class="infirmary-treatment-section">
                  <div class="infirmary-treatment-section__title">Emocional</div>
                  <label class="form-label">Comentario</label>
                  <textarea id="swal-emotional-comment" class="form-control" rows="3" placeholder="Comentario..."></textarea>
                </div>
              </div>
              <div id="swal-derivation-treatment-wrapper" class="col-12 d-none">
                <div class="infirmary-treatment-section">
                  <div class="infirmary-treatment-section__title">Derivación</div>
                  <label class="form-label">Tipo derivación</label>
                  <select id="swal-treatment-derivation-type" class="form-select mb-3">${this.swalSelectOptions(this.treatmentDerivationOptions, null)}</select>
                  <label class="form-label d-block">Se va con</label>
                  ${this.swalCheckboxOptions(this.treatmentDerivationSupportOptions, "swal-derivation-support", "swal-derivation-support")}
                </div>
              </div>
              <div id="swal-csv-treatment-wrapper" class="col-12 d-none">
                <div class="infirmary-treatment-section">
                  <div class="infirmary-treatment-section__title">CSV</div>
                  <div class="infirmary-field-grid">
                    <div>
                      <label class="form-label">Presión arterial</label>
                      <input id="swal-blood-pressure" class="form-control" placeholder="Ingrese la presión arterial" />
                    </div>
                    <div>
                      <label class="form-label">Pulso</label>
                      <input id="swal-pulse" class="form-control" type="number" min="1" placeholder="Ingrese el pulso" />
                    </div>
                    <div>
                      <label class="form-label">Saturación</label>
                      <input id="swal-oxygen-saturation" class="form-control" type="number" min="1" max="100" placeholder="Ingrese la saturación" />
                    </div>
                  </div>
                </div>
              </div>
              <div id="swal-other-treatment-wrapper" class="col-12 d-none">
                <div class="infirmary-treatment-section">
                  <div class="infirmary-treatment-section__title">OTRO</div>
                  <label class="form-label">Detalle:</label>
                  <textarea id="swal-other-treatment" class="form-control" rows="4" placeholder="Escribe los detalles..."></textarea>
                </div>
              </div>
            </div>
            <div id="swal-quick-form" class="infirmary-quick-form d-none" role="tabpanel" aria-labelledby="swal-mode-quick">
              <div class="infirmary-quick-intro">
                <span class="infirmary-quick-intro__icon"><i class="bx bx-bolt-circle"></i></span>
                <div>
                  <strong>Registro breve para cuidados menores</strong>
                  <span>Selecciona el motivo, una o más medidas y el resultado. Todo quedará en la ficha clínica de la estudiante.</span>
                </div>
              </div>

              <section class="infirmary-quick-section">
                <div class="infirmary-quick-section__heading">
                  <span>1</span>
                  <div>
                    <strong>¿Qué presenta?</strong>
                    <small>Selecciona el motivo principal</small>
                  </div>
                </div>
                <div class="infirmary-quick-choice-grid">${quickReasonOptions}</div>
              </section>

              <section class="infirmary-quick-section">
                <div class="infirmary-quick-section__heading">
                  <span>2</span>
                  <div>
                    <strong>¿Qué medida aplicaste?</strong>
                    <small>Puedes seleccionar varias</small>
                  </div>
                </div>
                <div class="infirmary-quick-action-grid">${quickActionOptions}</div>
              </section>

              <div class="infirmary-quick-bottom-grid">
                <section class="infirmary-quick-section">
                  <div class="infirmary-quick-section__heading">
                    <span>3</span>
                    <div>
                      <strong>Resultado</strong>
                      <small>Estado al cerrar este registro</small>
                    </div>
                  </div>
                  <div class="infirmary-quick-result-grid">${quickResultOptions}</div>
                </section>

                <section class="infirmary-quick-section">
                  <div class="infirmary-quick-section__heading">
                    <span>4</span>
                    <div>
                      <strong>Tiempo y observación</strong>
                      <small>Agrega contexto solo si hace falta</small>
                    </div>
                  </div>
                  <label class="form-label d-block">Duración aproximada</label>
                  <div class="infirmary-quick-duration" role="radiogroup" aria-label="Duración aproximada">
                    <label><input class="swal-quick-duration" type="radio" name="swal-quick-duration" value="5" checked /><span>5 min</span></label>
                    <label><input class="swal-quick-duration" type="radio" name="swal-quick-duration" value="10" /><span>10 min</span></label>
                    <label><input class="swal-quick-duration" type="radio" name="swal-quick-duration" value="15" /><span>15 min</span></label>
                  </div>
                  <label class="form-label mt-3" for="swal-quick-notes">Observación opcional</label>
                  <textarea id="swal-quick-notes" class="form-control" rows="3" placeholder="Ej: tolera agua, disminuye el malestar y vuelve a clases"></textarea>
                </section>
              </div>

              <div class="infirmary-quick-safety-note" role="note">
                <i class="bx bx-shield-quarter"></i>
                <span><strong>Usa la ficha completa</strong> si necesitas administrar medicamentos, registrar signos vitales completos, llamar a un apoderado o realizar una derivación.</span>
              </div>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar atención",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        focusConfirm: false,
        didOpen: () => {
          const popup = Swal.getPopup();
          const searchInput = popup.querySelector("#swal-student-search");
          const searchButton = popup.querySelector("#swal-student-search-button");
          const resultsContainer = popup.querySelector("#swal-student-results");
          const selectedContainer = popup.querySelector("#swal-student-selected");
          const modeButtons = popup.querySelectorAll("[data-attention-mode]");
          const fullForm = popup.querySelector("#swal-full-form");
          const quickForm = popup.querySelector("#swal-quick-form");
          const occurredAtInput = popup.querySelector("#swal-occurred-at");
          const attendedAtInput = popup.querySelector("#swal-attended-at");
          const categorySelect = popup.querySelector("#swal-attention-category");
          const mentalHealthWrapper = popup.querySelector("#swal-mental-health-wrapper");
          const mentalHealthEventSelect = popup.querySelector("#swal-mental-health-event-type");
          const selfHarmInjuryWrapper = popup.querySelector("#swal-self-harm-injury-wrapper");
          const selfHarmInjurySelect = popup.querySelector("#swal-self-harm-injury-type");
          const accidentLocationWrapper = popup.querySelector("#swal-accident-location-wrapper");
          const accidentLocationSelect = popup.querySelector("#swal-accident-location-type");
          const dependencyWrapper = popup.querySelector("#swal-dependency-wrapper");
          const dependencySelect = popup.querySelector("#swal-dependency-id");
          const accidentCircumstanceWrapper = popup.querySelector("#swal-accident-circumstance-wrapper");
          const accidentCircumstanceInput = popup.querySelector("#swal-accident-circumstance");
          const companionTypeSelect = popup.querySelector("#swal-accompanied-by-type");
          const companionStaffWrapper = popup.querySelector("#swal-accompanied-by-staff-wrapper");
          const companionStaffSelect = popup.querySelector("#swal-accompanied-by-staff-id");
          const companionNameWrapper = popup.querySelector("#swal-accompanied-by-name-wrapper");
          const companionNameInput = popup.querySelector("#swal-accompanied-by-name");
          const physicalTreatmentWrapper = popup.querySelector("#swal-physical-treatment-wrapper");
          const emotionalTreatmentWrapper = popup.querySelector("#swal-emotional-treatment-wrapper");
          const emotionalCommentInput = popup.querySelector("#swal-emotional-comment");
          const derivationTreatmentWrapper = popup.querySelector("#swal-derivation-treatment-wrapper");
          const derivationTypeSelect = popup.querySelector("#swal-treatment-derivation-type");
          const csvTreatmentWrapper = popup.querySelector("#swal-csv-treatment-wrapper");
          const bloodPressureInput = popup.querySelector("#swal-blood-pressure");
          const pulseInput = popup.querySelector("#swal-pulse");
          const oxygenSaturationInput = popup.querySelector("#swal-oxygen-saturation");
          const otherTreatmentWrapper = popup.querySelector("#swal-other-treatment-wrapper");
          const otherTreatmentInput = popup.querySelector("#swal-other-treatment");
          const registerCallInput = popup.querySelector("#swal-register-call");
          const callWrapper = popup.querySelector("#swal-call-wrapper");
          const callContactSelect = popup.querySelector("#swal-call-contact");
          const callRestrictionAlert = popup.querySelector("#swal-call-restriction-alert");
          const callPersonInput = popup.querySelector("#swal-call-person");
          const callRelationshipInput = popup.querySelector("#swal-call-relationship");
          const callPhoneInput = popup.querySelector("#swal-call-phone");

          const setAttentionMode = (mode) => {
            attentionMode = mode === "quick" ? "quick" : "full";
            fullForm.classList.toggle("d-none", attentionMode !== "full");
            quickForm.classList.toggle("d-none", attentionMode !== "quick");
            modeButtons.forEach((button) => {
              const isActive = button.dataset.attentionMode === attentionMode;
              button.classList.toggle("is-active", isActive);
              button.setAttribute("aria-selected", String(isActive));
            });

            const confirmButton = Swal.getConfirmButton();
            if (confirmButton) {
              confirmButton.textContent = attentionMode === "quick"
                ? "Guardar atención rápida"
                : "Registrar atención";
              confirmButton.classList.toggle("infirmary-quick-confirm", attentionMode === "quick");
            }
          };

          const syncAccidentFields = () => {
            const isAccident = this.isAccidentCategory(categorySelect.value);
            accidentLocationWrapper.classList.toggle("d-none", !isAccident);
            accidentCircumstanceWrapper.classList.toggle("d-none", !isAccident);

            if (isAccident && !accidentLocationSelect.value) {
              accidentLocationSelect.value = "colegio";
            }

            const showDependency = isAccident && accidentLocationSelect.value === "colegio";
            dependencyWrapper.classList.toggle("d-none", !showDependency);

            if (!showDependency) {
              dependencySelect.value = "";
            }

            if (!isAccident) {
              accidentCircumstanceInput.value = "";
            }
          };

          const syncMentalHealthFields = () => {
            const isMentalHealth = categorySelect.value === MENTAL_HEALTH_CATEGORY;
            mentalHealthWrapper.classList.toggle("d-none", !isMentalHealth);

            if (!isMentalHealth) {
              mentalHealthEventSelect.value = "";
              selfHarmInjurySelect.value = "";
            }

            const isSelfHarm = isMentalHealth && mentalHealthEventSelect.value === "autolesion";
            selfHarmInjuryWrapper.classList.toggle("d-none", !isSelfHarm);

            if (!isSelfHarm) {
              selfHarmInjurySelect.value = "";
            }
          };

          const syncCategorizationFields = () => {
            syncAccidentFields();
            syncMentalHealthFields();
          };

          const checkedValues = (selector) => Array.from(popup.querySelectorAll(selector))
            .filter((input) => input.checked)
            .map((input) => input.value);

          const syncTreatmentFields = () => {
            const categories = checkedValues(".swal-treatment-category");
            const showPhysical = categories.includes("fisico");
            const showEmotional = categories.includes("emocional");
            const showDerivation = categories.includes("derivacion");
            const showCsv = categories.includes("csv");
            const showOther = categories.includes("otro");

            physicalTreatmentWrapper.classList.toggle("d-none", !showPhysical);
            emotionalTreatmentWrapper.classList.toggle("d-none", !showEmotional);
            derivationTreatmentWrapper.classList.toggle("d-none", !showDerivation);
            csvTreatmentWrapper.classList.toggle("d-none", !showCsv);
            otherTreatmentWrapper.classList.toggle("d-none", !showOther);

            if (!showPhysical) {
              popup.querySelectorAll(".swal-physical-treatment").forEach((input) => {
                input.checked = false;
              });
            }

            if (!showEmotional) {
              emotionalCommentInput.value = "";
            }

            if (!showDerivation) {
              derivationTypeSelect.value = "";
              popup.querySelectorAll(".swal-derivation-support").forEach((input) => {
                input.checked = false;
              });
            }

            if (!showCsv) {
              bloodPressureInput.value = "";
              pulseInput.value = "";
              oxygenSaturationInput.value = "";
            }

            if (!showOther) {
              otherTreatmentInput.value = "";
            }
          };

          const syncCompanionFields = () => {
            const companionType = companionTypeSelect.value || "sin_acompanante";
            const showStaff = this.requiresCompanionStaffType(companionType);
            const showName = this.usesCompanionNameType(companionType);

            companionStaffSelect.innerHTML = this.swalSelectOptions(
              this.companionStaffItems(companionType),
              null,
              true,
              "Seleccione funcionaria"
            );
            companionStaffWrapper.classList.toggle("d-none", !showStaff);
            companionNameWrapper.classList.toggle("d-none", !showName);

            if (!showStaff) {
              companionStaffSelect.value = "";
            }

            if (!showName) {
              companionNameInput.value = "";
            }
          };

          const selectedGuardianContacts = () => selectedStudent?.medical_context?.emergency_contacts || [];

          const applySelectedCallContact = () => {
            const contact = selectedGuardianContacts().find((item) => item.type === callContactSelect.value);
            const restriction = this.primaryGuardianRestriction(contact);

            callRestrictionAlert.classList.toggle("d-none", !restriction);
            callRestrictionAlert.innerHTML = restriction ? `
              <i class="bx bxs-error-shield"></i>
              <div>
                <strong>No contactar sin validar el protocolo</strong>
                <span>${this.escapeHtml(contact.name)} tiene una medida vigente: ${this.escapeHtml(restriction.restriction_type_label || "Restricción")}. ${this.escapeHtml(restriction.reason || "Consulta con Trabajo Social antes de continuar.")}</span>
              </div>
            ` : "";

            if (contact) {
              callPersonInput.value = contact.name || "";
              callRelationshipInput.value = contact.relationship || "";
              callPhoneInput.value = contact.phone || "";
              return;
            }

            if (callContactSelect.value === "other") {
              callPersonInput.value = "";
              callRelationshipInput.value = "";
              callPhoneInput.value = "";
            }
          };

          const syncCallContacts = () => {
            const contacts = selectedGuardianContacts();
            const options = contacts.map((contact) => {
              const label = [contact.has_active_restriction ? "⚠ NO CONTACTAR" : null, contact.label, contact.name, contact.phone].filter(Boolean).join(" · ");
              return `<option value="${this.escapeHtml(contact.type)}">${this.escapeHtml(label)}</option>`;
            });

            callContactSelect.innerHTML = [
              '<option value="">Seleccione un contacto</option>',
              ...options,
              '<option value="other">Otro contacto</option>',
            ].join("");

            if (contacts.length) {
              callContactSelect.value = (contacts.find((contact) => !contact.has_active_restriction) || contacts[0]).type;
              applySelectedCallContact();
            } else {
              callRestrictionAlert.classList.add("d-none");
              callRestrictionAlert.innerHTML = "";
            }
          };

          const syncCallFields = () => {
            callWrapper.classList.toggle("d-none", !registerCallInput.checked);
          };

          const renderMessage = (message, variant = "muted") => {
            resultsContainer.innerHTML = `<div class="small text-${variant} p-2">${this.escapeHtml(message)}</div>`;
          };

          let searchSequence = 0;
          const searchStudents = async () => {
            const search = searchInput.value.trim();
            if (search.length < 2) {
              renderMessage("Ingresa al menos 2 caracteres para buscar.");
              return;
            }

            renderMessage("Buscando estudiantes...");
            const currentSequence = ++searchSequence;
            try {
              const response = await axios.get("/api/infirmary/students", { params: { search } });
              if (currentSequence !== searchSequence || search !== searchInput.value.trim()) return;
              const students = response.data.data || [];

              if (!students.length) {
                renderMessage("Sin resultados.");
                return;
              }

              resultsContainer.innerHTML = students
                .map((student, index) => `
                  <button type="button" class="list-group-item list-group-item-action" data-student-index="${index}">
                    <div class="fw-semibold">${this.escapeHtml(student.full_name)}</div>
                    <div class="small text-muted">${this.escapeHtml(student.rut || "Sin RUT")} · ${this.escapeHtml(student.course || "Sin curso")}</div>
                  </button>
                `)
                .join("");

              resultsContainer.querySelectorAll("[data-student-index]").forEach((button) => {
                button.addEventListener("click", () => {
                  selectedStudent = students[Number(button.dataset.studentIndex)];
                  selectedContainer.innerHTML = this.swalMedicalContextHtml(selectedStudent);
                  resultsContainer.innerHTML = "";
                  searchInput.value = selectedStudent.full_name;
                  Swal.resetValidationMessage();
                  syncCallContacts();
                });
              });
            } catch (error) {
              if (currentSequence !== searchSequence) return;
              renderMessage(formatInfirmaryError(error, "No se pudo buscar estudiantes."), "danger");
            }
          };

          searchButton.addEventListener("click", searchStudents);
          modeButtons.forEach((button) => {
            button.addEventListener("click", () => {
              Swal.resetValidationMessage();
              setAttentionMode(button.dataset.attentionMode);
            });
          });
          popup.querySelectorAll(".swal-quick-reason, .swal-quick-action, .swal-quick-result, .swal-quick-duration").forEach((input) => {
            input.addEventListener("change", () => Swal.resetValidationMessage());
          });
          let searchDebounce = null;
          searchInput.addEventListener("input", () => {
            selectedStudent = null;
            selectedContainer.textContent = "Sin estudiante seleccionada.";
            syncCallContacts();
            window.clearTimeout(searchDebounce);
            if (searchInput.value.trim().length >= 2) {
              searchDebounce = window.setTimeout(searchStudents, 300);
            } else {
              resultsContainer.innerHTML = "";
            }
          });
          searchInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
              event.preventDefault();
              searchStudents();
            }
          });
          attendedAtInput.addEventListener("change", () => {
            if (!occurredAtInput.value) {
              occurredAtInput.value = attendedAtInput.value;
            }
          });
          categorySelect.addEventListener("change", syncCategorizationFields);
          mentalHealthEventSelect.addEventListener("change", syncMentalHealthFields);
          accidentLocationSelect.addEventListener("change", syncAccidentFields);
          companionTypeSelect.addEventListener("change", syncCompanionFields);
          registerCallInput.addEventListener("change", syncCallFields);
          callContactSelect.addEventListener("change", applySelectedCallContact);
          popup.querySelectorAll(".swal-treatment-category").forEach((input) => {
            input.addEventListener("change", syncTreatmentFields);
          });
          syncCategorizationFields();
          syncCompanionFields();
          syncCallContacts();
          syncCallFields();
          syncTreatmentFields();
          setAttentionMode("full");
        },
        preConfirm: () => {
          const popup = Swal.getPopup();
          const value = (id) => popup.querySelector(`#${id}`)?.value?.trim() || "";
          const nullableNumber = (id) => {
            const current = value(id);
            return current === "" ? null : Number(current);
          };
          const checkedValues = (selector) => Array.from(popup.querySelectorAll(selector))
            .filter((input) => input.checked)
            .map((input) => input.value);

          if (!selectedStudent?.id) {
            Swal.showValidationMessage("Selecciona una estudiante.");
            return false;
          }

          if (attentionMode === "quick") {
            const quickReason = popup.querySelector(".swal-quick-reason:checked")?.value || "";
            const quickActions = checkedValues(".swal-quick-action");
            const quickResult = popup.querySelector(".swal-quick-result:checked")?.value || "";
            const quickDuration = popup.querySelector(".swal-quick-duration:checked")?.value || "5";

            if (!quickReason) {
              Swal.showValidationMessage("Selecciona qué presenta la estudiante.");
              return false;
            }

            if (!quickActions.length) {
              Swal.showValidationMessage("Selecciona al menos una medida aplicada.");
              return false;
            }

            if (!quickResult) {
              Swal.showValidationMessage("Selecciona el resultado de la atención.");
              return false;
            }

            return buildQuickAttentionPayload({
              studentId: selectedStudent.id,
              reason: quickReason,
              actions: quickActions,
              result: quickResult,
              duration: quickDuration,
              notes: value("swal-quick-notes"),
              attendedAt: value("swal-attended-at") || toInputDateTime(new Date().toISOString()),
            });
          }

          if (!value("swal-consultation-reason")) {
            Swal.showValidationMessage("Ingresa el motivo de consulta.");
            return false;
          }

          const attentionCategory = value("swal-attention-category") || "accidente_menor";
          const mentalHealthCategorization = normalizeMentalHealthCategorization({
            attentionCategory,
            mentalHealthEventType: value("swal-mental-health-event-type"),
            selfHarmInjuryType: value("swal-self-harm-injury-type"),
          });
          const mentalHealthError = mentalHealthCategorizationError({
            attentionCategory,
            ...mentalHealthCategorization,
          });
          const occurredAt = value("swal-occurred-at");
          const attendedAt = value("swal-attended-at");
          const isAccident = this.isAccidentCategory(attentionCategory);
          const accidentLocationType = isAccident ? (value("swal-accident-location-type") || "colegio") : null;
          const dependencyId = isAccident && accidentLocationType === "colegio" ? nullableNumber("swal-dependency-id") : null;
          const accompaniedByType = value("swal-accompanied-by-type") || "sin_acompanante";
          const accompaniedByStaffId = this.requiresCompanionStaffType(accompaniedByType)
            ? nullableNumber("swal-accompanied-by-staff-id")
            : null;
          const accompaniedByName = this.usesCompanionNameType(accompaniedByType)
            ? value("swal-accompanied-by-name") || null
            : null;
          const treatmentCategories = checkedValues(".swal-treatment-category");
          const treatmentTypes = treatmentCategories.includes("fisico")
            ? checkedValues(".swal-physical-treatment")
            : [];
          const derivationSupportTeams = treatmentCategories.includes("derivacion")
            ? checkedValues(".swal-derivation-support")
            : [];
          const hasPhoneCall = Boolean(popup.querySelector("#swal-register-call")?.checked);
          const callPerson = value("swal-call-person");
          const callSummary = value("swal-call-summary");

          if (mentalHealthError) {
            Swal.showValidationMessage(mentalHealthError);
            return false;
          }

          if (!occurredAt) {
            Swal.showValidationMessage("Ingresa la fecha de accidente.");
            return false;
          }

          if (!attendedAt) {
            Swal.showValidationMessage("Ingresa la fecha de registro.");
            return false;
          }

          if (this.isOccurredAfterRegistered(occurredAt, attendedAt)) {
            Swal.showValidationMessage("La fecha de accidente no puede ser posterior a la fecha de registro.");
            return false;
          }

          if (isAccident && accidentLocationType === "colegio" && !dependencyId) {
            Swal.showValidationMessage("Selecciona la dependencia del colegio.");
            return false;
          }

          if (this.requiresCompanionStaffType(accompaniedByType) && !accompaniedByStaffId) {
            Swal.showValidationMessage("Selecciona la funcionaria que acompaña.");
            return false;
          }

          if (treatmentCategories.includes("derivacion") && !value("swal-treatment-derivation-type")) {
            Swal.showValidationMessage("Selecciona el tipo de derivación.");
            return false;
          }

          if (hasPhoneCall && !callPerson) {
            Swal.showValidationMessage("Indica la persona contactada en el llamado telefónico.");
            return false;
          }

          if (hasPhoneCall && !callSummary) {
            Swal.showValidationMessage("Describe qué ocurrió durante la llamada telefónica.");
            return false;
          }

          return {
            student_profile_id: selectedStudent.id,
            attention_category: attentionCategory,
            mental_health_event_type: mentalHealthCategorization.mentalHealthEventType,
            self_harm_injury_type: mentalHealthCategorization.selfHarmInjuryType,
            accident_location_type: accidentLocationType,
            occurred_at: occurredAt,
            attended_at: attendedAt,
            referred_by_staff_id: null,
            dependency_id: dependencyId,
            accompanied_by_type: accompaniedByType,
            accompanied_by_staff_id: accompaniedByStaffId,
            accompanied_by_name: accompaniedByName,
            consultation_reason: value("swal-consultation-reason"),
            accident_circumstance: isAccident ? value("swal-accident-circumstance") || null : null,
            logbook: value("swal-logbook") || null,
            initial_description: null,
            observations: null,
            attention_duration_minutes: 15,
            priority: "media",
            status: "abierta",
            treatments: treatmentCategories.length ? [{
              treatment_categories: treatmentCategories,
              treatment_types: treatmentTypes,
              derivation_type: treatmentCategories.includes("derivacion") ? value("swal-treatment-derivation-type") || null : null,
              derivation_support_teams: derivationSupportTeams,
              treatment_other: null,
              medication_id: null,
              medication_quantity: null,
              emotional_support_required: treatmentCategories.includes("emocional"),
              emotional_comment: treatmentCategories.includes("emocional") ? value("swal-emotional-comment") || null : null,
              emotional_support_type: null,
              emotional_duration_minutes: null,
              emotional_professional_id: null,
              blood_pressure: treatmentCategories.includes("csv") ? value("swal-blood-pressure") || null : null,
              pulse: treatmentCategories.includes("csv") ? nullableNumber("swal-pulse") : null,
              respiratory_rate: null,
              temperature: null,
              oxygen_saturation: treatmentCategories.includes("csv") ? nullableNumber("swal-oxygen-saturation") : null,
              weight: null,
              height: null,
              vital_signs_notes: null,
              other_treatments: treatmentCategories.includes("otro") ? value("swal-other-treatment") || null : null,
              notes: null,
            }] : [],
            referrals: [],
            calls: hasPhoneCall ? [{
              called_at: attendedAt,
              person_contacted: callPerson,
              relationship: value("swal-call-relationship") || null,
              phone_number: value("swal-call-phone") || null,
              call_status: value("swal-call-status") || "pendiente",
              reason: "Contacto realizado durante la atención",
              conversation_summary: callSummary,
              commitments: null,
              estimated_arrival_at: null,
              duration_minutes: null,
              called_by_user_id: null,
            }] : [],
            follow_ups: [],
          };
        },
      });

      if (!result.isConfirmed) return;

      this.saving = true;
      try {
        await axios.post("/api/infirmary/attentions", result.value);
        await this.loadAttentions(1);
        await showInfirmarySuccess("Atención registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar la atención."));
      } finally {
        this.saving = false;
      }
    },
    async openEdit(attention) {
      try {
        attention = await this.fetchAttentionDetail(attention);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo cargar la atención."));
        return;
      }

      const treatmentSource = attention.treatments?.length ? attention.treatments : [this.emptyTreatment()];

      this.form = {
        id: attention.id,
        student_profile_id: attention.student_profile_id,
        student_label: attention.student ? `${attention.student.first_name} ${attention.student.last_name}` : attention.student_full_name_snapshot,
        attention_category: attention.attention_category || "accidente_menor",
        mental_health_event_type: attention.mental_health_event_type || null,
        self_harm_injury_type: attention.self_harm_injury_type || null,
        accident_location_type: attention.accident_location_type || (this.isAccidentCategory(attention.attention_category) ? "colegio" : null),
        occurred_at: toInputDateTime(attention.occurred_at || attention.attended_at),
        attended_at: toInputDateTime(attention.attended_at),
        referred_by_staff_id: attention.referred_by_staff_id || null,
        dependency_id: attention.dependency_id || null,
        accompanied_by_type: attention.accompanied_by_type || "sin_acompanante",
        accompanied_by_staff_id: attention.accompanied_by_staff_id || null,
        accompanied_by_name: attention.accompanied_by_name || "",
        consultation_reason: attention.consultation_reason || "",
        accident_circumstance: attention.accident_circumstance || "",
        logbook: attention.logbook || "",
        initial_description: attention.initial_description || "",
        observations: attention.observations || "",
        attention_duration_minutes: attention.attention_duration_minutes || 15,
        priority: attention.priority || "media",
        status: attention.status || "abierta",
        treatments: treatmentSource.map((item) => ({
          treatment_categories: this.inferTreatmentCategories(item),
          treatment_types: item.treatment_types || [],
          derivation_type: item.derivation_type || null,
          derivation_support_teams: item.derivation_support_teams || [],
          treatment_other: item.treatment_other || "",
          medication_id: item.medication_id || null,
          medication_quantity: item.medication_quantity || 1,
          blood_pressure: item.blood_pressure || "",
          pulse: item.pulse || null,
          respiratory_rate: item.respiratory_rate || null,
          temperature: item.temperature || null,
          oxygen_saturation: item.oxygen_saturation || null,
          weight: item.weight || null,
          height: item.height || null,
          vital_signs_notes: item.vital_signs_notes || "",
          emotional_support_required: Boolean(item.emotional_support_required),
          emotional_comment: item.emotional_comment || "",
          emotional_support_type: item.emotional_support_type || "",
          emotional_duration_minutes: item.emotional_duration_minutes || null,
          emotional_professional_id: item.emotional_professional_id || null,
          other_treatments: item.other_treatments || item.treatment_other || "",
          notes: item.notes || "",
        })),
        referrals: (attention.referrals || []).map((item) => ({
          referral_type: item.referral_type,
          referred_at: toInputDateTime(item.referred_at),
          responsible_user_id: item.responsible_user_id || null,
          responsible_name: item.responsible_name || "",
          reason: item.reason || "",
          observations: item.observations || "",
          result: item.result || "",
        })),
        calls: (attention.calls || []).map((item) => ({
          called_at: toInputDateTime(item.called_at),
          person_contacted: item.person_contacted || "",
          relationship: item.relationship || "",
          phone_number: item.phone_number || "",
          call_status: item.call_status || "pendiente",
          reason: item.reason || "",
          conversation_summary: item.conversation_summary || "",
          commitments: item.commitments || "",
          estimated_arrival_at: toInputDateTime(item.estimated_arrival_at),
          duration_minutes: item.duration_minutes || 5,
          called_by_user_id: item.called_by_user_id || null,
        })),
        follow_ups: (attention.follow_ups || []).map((item) => ({
          followed_at: toInputDateTime(item.followed_at),
          responsible_user_id: item.responsible_user_id || null,
          comment: item.comment || "",
          status: item.status || "pendiente",
          next_review_at: toInputDateTime(item.next_review_at),
        })),
      };
      this.selectedStudentContext = null;
      this.showModal = true;
      await this.loadStudentMedicalContext(attention.student_profile_id);
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("la ficha de atención");
      if (result.isConfirmed) this.showModal = false;
    },
    addTreatment() {
      this.form.treatments.push(this.emptyTreatment());
    },
    removeTreatment(index) {
      this.form.treatments.splice(index, 1);
      if (!this.form.treatments.length) this.addTreatment();
    },
    addReferral() {
      this.form.referrals.push(this.emptyReferral());
    },
    removeReferral(index) {
      this.form.referrals.splice(index, 1);
    },
    addCall() {
      this.form.calls.push(this.emptyCall());
    },
    removeCall(index) {
      this.form.calls.splice(index, 1);
    },
    addFollowUp() {
      this.form.follow_ups.push(this.emptyFollowUp());
    },
    removeFollowUp(index) {
      this.form.follow_ups.splice(index, 1);
    },
    async save() {
      if (!this.form.student_profile_id) {
        await showInfirmaryWarning("Debes seleccionar una estudiante.");
        return;
      }

      this.saving = true;
      try {
        const isAccident = this.isAccidentCategory(this.form.attention_category);
        const mentalHealthCategorization = normalizeMentalHealthCategorization({
          attentionCategory: this.form.attention_category,
          mentalHealthEventType: this.form.mental_health_event_type,
          selfHarmInjuryType: this.form.self_harm_injury_type,
        });
        const mentalHealthError = mentalHealthCategorizationError({
          attentionCategory: this.form.attention_category,
          ...mentalHealthCategorization,
        });
        const accidentLocationType = isAccident ? (this.form.accident_location_type || "colegio") : null;
        const dependencyId = isAccident && accidentLocationType === "colegio" ? this.form.dependency_id : null;
        const accompaniedByType = this.form.accompanied_by_type || "sin_acompanante";
        const accompaniedByStaffId = this.requiresCompanionStaffType(accompaniedByType)
          ? this.form.accompanied_by_staff_id
          : null;
        const accompaniedByName = this.usesCompanionNameType(accompaniedByType)
          ? this.form.accompanied_by_name || null
          : null;

        if (mentalHealthError) {
          await showInfirmaryWarning(mentalHealthError);
          return;
        }

        if (!this.form.occurred_at) {
          await showInfirmaryWarning("Ingresa la fecha de accidente.");
          return;
        }

        if (!this.form.attended_at) {
          await showInfirmaryWarning("Ingresa la fecha de registro.");
          return;
        }

        if (this.isOccurredAfterRegistered(this.form.occurred_at, this.form.attended_at)) {
          await showInfirmaryWarning("La fecha de accidente no puede ser posterior a la fecha de registro.");
          return;
        }

        if (isAccident && accidentLocationType === "colegio" && !dependencyId) {
          await showInfirmaryWarning("Selecciona la dependencia del colegio.");
          return;
        }

        if (this.requiresCompanionStaffType(accompaniedByType) && !accompaniedByStaffId) {
          await showInfirmaryWarning("Selecciona la funcionaria que acompaña.");
          return;
        }

        if (this.form.treatments.some((item) => this.treatmentHasCategory(item, "derivacion") && !item.derivation_type)) {
          await showInfirmaryWarning("Selecciona el tipo de derivación.");
          return;
        }

        const payload = {
          ...this.form,
          referred_by_staff_id: null,
          mental_health_event_type: mentalHealthCategorization.mentalHealthEventType,
          self_harm_injury_type: mentalHealthCategorization.selfHarmInjuryType,
          accident_location_type: accidentLocationType,
          dependency_id: dependencyId,
          accompanied_by_type: accompaniedByType,
          accompanied_by_staff_id: accompaniedByStaffId,
          accompanied_by_name: accompaniedByName,
          accident_circumstance: isAccident ? this.form.accident_circumstance || null : null,
          logbook: this.form.logbook || null,
          treatments: this.form.treatments.map((item) => ({
            ...item,
            treatment_categories: item.treatment_categories || [],
            treatment_types: this.treatmentHasCategory(item, "fisico") ? item.treatment_types || [] : [],
            derivation_type: this.treatmentHasCategory(item, "derivacion") ? item.derivation_type || null : null,
            derivation_support_teams: this.treatmentHasCategory(item, "derivacion") ? item.derivation_support_teams || [] : [],
            treatment_other: null,
            medication_id: this.treatmentHasMedication(item) ? item.medication_id || null : null,
            medication_quantity: this.treatmentHasMedication(item) ? item.medication_quantity || null : null,
            emotional_support_required: this.treatmentHasCategory(item, "emocional"),
            emotional_comment: this.treatmentHasCategory(item, "emocional") ? item.emotional_comment || null : null,
            emotional_support_type: this.treatmentHasCategory(item, "emocional") ? item.emotional_support_type || null : null,
            emotional_duration_minutes: this.treatmentHasCategory(item, "emocional") ? item.emotional_duration_minutes || null : null,
            emotional_professional_id: this.treatmentHasCategory(item, "emocional") ? item.emotional_professional_id || null : null,
            blood_pressure: this.treatmentHasCategory(item, "csv") ? item.blood_pressure || null : null,
            pulse: this.treatmentHasCategory(item, "csv") ? item.pulse || null : null,
            respiratory_rate: null,
            temperature: null,
            oxygen_saturation: this.treatmentHasCategory(item, "csv") ? item.oxygen_saturation || null : null,
            weight: null,
            height: null,
            vital_signs_notes: null,
            other_treatments: this.treatmentHasCategory(item, "otro") ? item.other_treatments || null : null,
          })),
          referrals: this.form.referrals.map((item) => ({
            ...item,
            responsible_user_id: item.responsible_user_id || null,
            responsible_name: item.responsible_name || null,
          })),
          calls: this.form.calls.map((item) => ({
            ...item,
            estimated_arrival_at: item.estimated_arrival_at || null,
            called_by_user_id: item.called_by_user_id || null,
          })),
          follow_ups: this.form.follow_ups.map((item) => ({
            ...item,
            responsible_user_id: item.responsible_user_id || null,
            next_review_at: item.next_review_at || null,
          })),
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/attentions/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/attentions", payload);
        }

        this.showModal = false;
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Atención actualizada correctamente." : "Atención registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar la atención."));
      } finally {
        this.saving = false;
      }
    },
    async remove(attention) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar atención",
        text: "Se eliminará la atención y sus registros relacionados.",
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/attentions/${attention.id}`);
        await this.loadAttentions(this.pagination.current_page || 1);
        await showInfirmarySuccess("Atención eliminada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar la atención."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Ficha de Atención de Enfermería</h4>
        <div class="text-muted">
          Registro clínico de atenciones, tratamientos, signos vitales, derivaciones, llamados, seguimiento y adjuntos.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: ficha de atención"
          text="En esta pantalla se registran todas las atenciones realizadas por enfermería, incluyendo tratamientos, medicamentos administrados, derivaciones y seguimiento del estudiante."
        />
        <BButton v-if="canCreate" variant="primary" @click="openCreate">Nueva ficha de atención</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-lg-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Nombre, RUT o motivo" @keyup.enter="loadAttentions(1)" />
        </div>
        <div class="col-md-3 col-lg-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="statusOptions" />
        </div>
        <div class="col-md-3 col-lg-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-md-3 col-lg-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-md-3 col-lg-auto d-flex gap-2">
          <BButton variant="primary" @click="loadAttentions(1)">Filtrar</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div>
            <div class="fw-semibold">Accidentes históricos</div>
            <div class="text-muted small">{{ pagination.total }} registros encontrados</div>
          </div>
          <InfirmaryHelpButton
            title="Ayuda: accidentes históricos"
            text="La tabla resume el historial de fichas con fecha del accidente, registro, tipo, detalle clínico y derivación asociada."
          />
        </div>
      </template>

      <LoadingState v-if="loading" message="Cargando atenciones..." compact />

      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>N° correlativo</th>
              <th>Ficha de la estudiante</th>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Fecha de registro</th>
              <th>Tipo de accidente</th>
              <th>Detalle</th>
              <th>Derivación</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!attentions.length">
              <td colspan="9" class="text-center text-muted py-4">No hay accidentes históricos para los filtros seleccionados.</td>
            </tr>
            <tr v-for="attention in attentions" :key="attention.id">
              <td>
                <span class="fw-semibold">{{ accidentReference(attention) }}</span>
              </td>
              <td>
                <div class="fw-semibold">{{ attention.student_full_name_snapshot || `${attention.student?.first_name || ''} ${attention.student?.last_name || ''}` }}</div>
                <div class="small text-muted">{{ attention.student?.rut || attention.student_rut_snapshot || "Sin RUT" }}</div>
                <div class="small text-muted">{{ attention.course_name_snapshot || attention.course_section?.display_name || "Sin curso" }}</div>
              </td>
              <td>{{ formatDateOnly(attention.occurred_at || attention.attended_at) }}</td>
              <td>{{ formatTimeOnly(attention.occurred_at || attention.attended_at) }}</td>
              <td class="infirmary-date-cell">
                <div>{{ formatDateOnly(attention.attended_at) }}</div>
                <div class="small text-muted">{{ formatTimeOnly(attention.attended_at) }}</div>
              </td>
              <td>
                <div>{{ attentionCategoryLabel(attention.attention_category) }}</div>
                <div v-if="attention.accident_location_type" class="small text-muted">
                  {{ accidentLocationLabel(attention.accident_location_type) }}
                </div>
              </td>
              <td class="text-wrap" style="min-width: 220px;">
                {{ attentionDetail(attention) || "-" }}
              </td>
              <td>{{ attentionDerivationLabel(attention) }}</td>
              <td class="text-end">
                <div class="infirmary-row-actions">
                  <button
                    type="button"
                    class="cnsc-action-btn cnsc-action-btn--view"
                    title="Ver ficha"
                    aria-label="Ver ficha"
                    @click="openView(attention)"
                  >
                    <i class="mdi mdi-eye-outline"></i>
                    <span class="visually-hidden">Ver</span>
                  </button>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="cnsc-action-btn cnsc-action-btn--edit"
                    title="Editar ficha"
                    aria-label="Editar ficha"
                    @click="openEdit(attention)"
                  >
                    <i class="mdi mdi-pencil-outline"></i>
                    <span class="visually-hidden">Editar ficha</span>
                  </button>
                  <button
                    v-if="canExport"
                    type="button"
                    class="cnsc-action-btn cnsc-action-btn--delete"
                    title="PDF"
                    aria-label="Exportar ficha PDF"
                    @click="exportAttentionPdf(attention)"
                  >
                    <i class="mdi mdi-file-pdf-box"></i>
                    <span class="visually-hidden">PDF</span>
                  </button>
                  <button
                    v-if="canDelete"
                    type="button"
                    class="cnsc-action-btn cnsc-action-btn--delete"
                    title="Eliminar ficha"
                    aria-label="Eliminar ficha"
                    @click="remove(attention)"
                  >
                    <i class="mdi mdi-trash-can-outline"></i>
                    <span class="visually-hidden">Eliminar</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadAttentions"
        />
      </div>
    </BCard>

    <BModal v-model="showViewModal" title="Ficha de atención" size="xl" hide-footer scrollable>
      <div v-if="selectedAttention" class="infirmary-view">
        <div class="infirmary-view-header">
          <div>
            <div class="text-muted small">Ficha de la estudiante</div>
            <h5 class="mb-1">{{ studentName(selectedAttention) }}</h5>
            <div class="text-muted small">
              {{ selectedAttention.student?.rut || selectedAttention.student_rut_snapshot || "Sin RUT" }}
              <span class="mx-1">·</span>
              {{ selectedAttention.course_name_snapshot || selectedAttention.course_section?.display_name || "Sin curso" }}
            </div>
          </div>
          <div class="infirmary-view-reference">{{ accidentReference(selectedAttention) }}</div>
        </div>

        <div class="infirmary-view-section infirmary-view-section--contacts">
          <h6><i class="bx bx-phone-call me-1"></i> Datos de contacto de apoderados</h6>
          <div class="infirmary-guardian-grid">
            <div
              v-for="contact in guardianContacts(selectedAttention)"
              :key="`guardian-${contact.type}`"
              class="infirmary-guardian-card"
              :class="{ 'infirmary-guardian-card--restricted': contact.has_active_restriction }"
            >
              <div class="infirmary-guardian-card__heading">
                <span>{{ contact.label }}</span>
                <i :class="contact.has_active_restriction ? 'bx bxs-error-shield' : contact.type === 'backup' ? 'bx bx-user-plus' : 'bx bx-user-check'"></i>
              </div>
              <strong>{{ contact.name || "Sin nombre registrado" }}</strong>
              <small>{{ contact.relationship || "Relación no informada" }}</small>
              <div class="infirmary-guardian-card__contact">
                <a v-if="contact.phone && !contact.has_active_restriction" :href="`tel:${contact.phone}`">
                  <i class="bx bx-phone"></i>{{ contact.phone }}
                </a>
                <span v-else-if="contact.phone"><i class="bx bx-phone-off"></i>{{ contact.phone }}</span>
                <span v-else><i class="bx bx-phone"></i>Sin teléfono registrado</span>
                <a v-if="contact.email" :href="`mailto:${contact.email}`">
                  <i class="bx bx-envelope"></i>{{ contact.email }}
                </a>
                <span v-else><i class="bx bx-envelope"></i>Sin correo registrado</span>
              </div>
              <div v-if="contact.has_active_restriction" class="infirmary-guardian-restriction" role="alert">
                <strong><i class="bx bxs-error-alt"></i> No contactar sin validar protocolo</strong>
                <template v-for="restriction in contact.restrictions" :key="restriction.id">
                  <b>{{ restriction.restriction_type_label }}</b>
                  <span>{{ restriction.reason }}</span>
                  <small>{{ guardianRestrictionValidity(restriction) }} · Configurado por {{ restriction.source_label }}</small>
                </template>
              </div>
            </div>
          </div>
        </div>

        <div class="infirmary-view-grid">
          <div class="infirmary-view-field">
            <span>Fecha accidente</span>
            <strong>{{ formatDateOnly(selectedAttention.occurred_at || selectedAttention.attended_at) }}</strong>
          </div>
          <div class="infirmary-view-field">
            <span>Hora</span>
            <strong>{{ formatTimeOnly(selectedAttention.occurred_at || selectedAttention.attended_at) }}</strong>
          </div>
          <div class="infirmary-view-field">
            <span>Fecha de registro</span>
            <strong>{{ formatInfirmaryDateTime(selectedAttention.attended_at) }}</strong>
          </div>
          <div class="infirmary-view-field">
            <span>Categorización</span>
            <strong>{{ attentionCategoryLabel(selectedAttention.attention_category) }}</strong>
            <small v-if="selectedAttention.accident_location_type">{{ accidentLocationLabel(selectedAttention.accident_location_type) }}</small>
          </div>
          <div v-if="isMentalHealthCategory(selectedAttention.attention_category)" class="infirmary-view-field infirmary-view-field--mental-health">
            <span>Tipo de atención de salud mental</span>
            <strong>{{ mentalHealthEventLabel(selectedAttention.mental_health_event_type) }}</strong>
            <small v-if="selectedAttention.mental_health_event_type === 'autolesion'">
              {{ selfHarmInjuryLabel(selectedAttention.self_harm_injury_type) }}
            </small>
          </div>
          <div class="infirmary-view-field">
            <span>Dependencia</span>
            <strong>{{ dependencyLabel(selectedAttention) }}</strong>
          </div>
          <div class="infirmary-view-field">
            <span>Quién acompaña</span>
            <strong>{{ companionSummary(selectedAttention) }}</strong>
          </div>
        </div>

        <div class="infirmary-view-section">
          <h6>Detalle clínico</h6>
          <div class="infirmary-view-stack">
            <div>
              <span class="infirmary-view-label">Motivo de consulta</span>
              <p>{{ selectedAttention.consultation_reason || "-" }}</p>
            </div>
            <div>
              <span class="infirmary-view-label">Circunstancia del accidente</span>
              <p>{{ selectedAttention.accident_circumstance || "-" }}</p>
            </div>
            <div>
              <span class="infirmary-view-label">Bitácora</span>
              <p>{{ selectedAttention.logbook || "-" }}</p>
            </div>
          </div>
        </div>

        <div class="infirmary-view-section">
          <h6>Tratamientos</h6>
          <div v-if="selectedAttention.treatments?.length" class="infirmary-view-list">
            <div v-for="(treatment, index) in selectedAttention.treatments" :key="`view-treatment-${treatment.id || index}`" class="infirmary-view-item">
              <div class="fw-semibold mb-2">Tratamiento {{ index + 1 }}</div>
              <div class="infirmary-view-grid infirmary-view-grid--compact">
                <div class="infirmary-view-field">
                  <span>Categorías</span>
                  <strong>{{ treatmentCategorySummary(treatment) }}</strong>
                </div>
                <div class="infirmary-view-field" v-if="treatmentHasCategory(treatment, 'fisico') || treatment.treatment_types?.length">
                  <span>Físico</span>
                  <strong>{{ treatmentTypeSummary(treatment) }}</strong>
                </div>
                <div class="infirmary-view-field" v-if="treatment.medication">
                  <span>Medicamento</span>
                  <strong>{{ treatment.medication.commercial_name || treatment.medication.name }}</strong>
                </div>
                <div class="infirmary-view-field" v-if="treatmentHasCategory(treatment, 'csv')">
                  <span>CSV</span>
                  <strong>{{ treatmentCsvSummary(treatment) }}</strong>
                </div>
                <div class="infirmary-view-field" v-if="treatmentHasCategory(treatment, 'derivacion')">
                  <span>Derivación</span>
                  <strong>{{ treatmentDerivationSummary(treatment) }}</strong>
                </div>
              </div>
              <div class="infirmary-view-stack mt-2">
                <div v-if="treatmentHasCategory(treatment, 'emocional')">
                  <span class="infirmary-view-label">Emocional</span>
                  <p>{{ treatment.emotional_comment || "-" }}</p>
                </div>
                <div v-if="treatmentHasCategory(treatment, 'otro')">
                  <span class="infirmary-view-label">Otro</span>
                  <p>{{ treatment.other_treatments || "-" }}</p>
                </div>
                <div v-if="treatment.notes">
                  <span class="infirmary-view-label">Notas clínicas</span>
                  <p>{{ treatment.notes }}</p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-muted small">Sin tratamientos registrados.</div>
        </div>

        <div class="infirmary-view-section">
          <h6>Derivaciones</h6>
          <div v-if="selectedAttention.referrals?.length" class="infirmary-view-list">
            <div v-for="referral in selectedAttention.referrals" :key="`view-referral-${referral.id}`" class="infirmary-view-item">
              <div class="fw-semibold">{{ referralSummary(referral) }}</div>
              <div class="text-muted small">{{ formatInfirmaryDateTime(referral.referred_at) }}</div>
              <p v-if="referral.reason || referral.observations" class="mt-2 mb-0">{{ [referral.reason, referral.observations].filter(Boolean).join(" · ") }}</p>
            </div>
          </div>
          <div v-else class="text-muted small">Sin derivaciones registradas.</div>
        </div>

        <div class="infirmary-view-section">
          <h6>Llamados y seguimiento</h6>
          <div class="infirmary-view-grid">
            <div>
              <span class="infirmary-view-label">Llamados</span>
              <div v-if="selectedAttention.calls?.length" class="infirmary-view-list mt-2">
                <div v-for="call in selectedAttention.calls" :key="`view-call-${call.id}`" class="infirmary-view-item">
                  <div class="fw-semibold">{{ call.person_contacted }} · {{ humanizeInfirmaryStatus(call.call_status) }}</div>
                  <div class="text-muted small">
                    {{ formatInfirmaryDateTime(call.called_at) }}
                    <span v-if="call.relationship || call.phone_number"> · {{ [call.relationship, call.phone_number].filter(Boolean).join(" · ") }}</span>
                  </div>
                  <p class="mb-0 mt-2">{{ call.conversation_summary || call.reason || "-" }}</p>
                </div>
              </div>
              <div v-else class="text-muted small mt-2">Sin llamados registrados.</div>
            </div>
            <div>
              <span class="infirmary-view-label">Seguimiento</span>
              <div v-if="selectedAttention.follow_ups?.length" class="infirmary-view-list mt-2">
                <div v-for="followUp in selectedAttention.follow_ups" :key="`view-follow-${followUp.id}`" class="infirmary-view-item">
                  <div class="fw-semibold">{{ humanizeInfirmaryStatus(followUp.status) }}</div>
                  <div class="text-muted small">{{ formatInfirmaryDateTime(followUp.followed_at) }}</div>
                  <p class="mb-0 mt-2">{{ followUp.comment }}</p>
                </div>
              </div>
              <div v-else class="text-muted small mt-2">Sin seguimiento registrado.</div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="showViewModal = false">Cerrar</BButton>
          <BButton
            v-if="canExport && isAccidentCategory(selectedAttention.attention_category)"
            variant="outline-primary"
            class="infirmary-edit-ficha-btn"
            @click="openSchoolInsuranceCertificate(selectedAttention)"
          >
            <i class="mdi mdi-file-certificate-outline"></i>
            Certificado seguro escolar
          </BButton>
          <BButton v-if="canExport" variant="outline-danger" class="infirmary-edit-ficha-btn" @click="exportAttentionPdf(selectedAttention)">
            <i class="mdi mdi-file-pdf-box"></i>
            PDF ficha
          </BButton>
          <BButton v-if="canEdit" variant="primary" class="infirmary-edit-ficha-btn" @click="editFromView">
            <i class="mdi mdi-pencil-outline"></i>
            Editar ficha
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal
      v-model="showSchoolInsuranceCertificateModal"
      title="Certificado de seguro escolar"
      size="xl"
      hide-footer
      scrollable
    >
      <div v-if="schoolInsuranceCertificateForm" class="school-insurance-form">
        <section class="school-insurance-form__section">
          <h6>Documento y establecimiento</h6>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">N° correlativo / certificado</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.certificate_number" readonly />
              <div class="form-text">Vinculado a la ficha de atención de la estudiante.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha de registro</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.registration_date" type="date" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de establecimiento</label>
              <BFormSelect
                v-model="schoolInsuranceCertificateForm.institution_type"
                :options="schoolInstitutionTypeOptions"
              />
            </div>
            <div class="col-md-3">
              <label class="form-label">RBD</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.rbd" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Nombre del establecimiento</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.establishment_name" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Comuna del establecimiento</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.establishment_commune" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Horario</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.schedule" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Curso</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.course" />
            </div>
          </div>
        </section>

        <section class="school-insurance-form__section">
          <h6>Estudiante y residencia habitual</h6>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Apellido paterno</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.paternal_surname" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Apellido materno</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.maternal_surname" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Nombres</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.given_names" />
            </div>
            <div class="col-md-2">
              <label class="form-label">RUT</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.rut" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Sexo</label>
              <BFormSelect
                v-model="schoolInsuranceCertificateForm.sex"
                :options="schoolInsuranceSexOptions"
              />
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha de nacimiento</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.birth_date" type="date" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Dirección</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.address" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Población o villa</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.neighborhood" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Comuna de residencia</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.residence_commune" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Ciudad</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.city" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Código comuna</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.commune_code" />
            </div>
          </div>
        </section>

        <section class="school-insurance-form__section">
          <h6>Informe del accidente</h6>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Fecha y hora del accidente</label>
              <BFormInput v-model="schoolInsuranceCertificateForm.occurred_at" type="datetime-local" />
            </div>
            <div class="col-md-7">
              <label class="form-label">Tipo de accidente</label>
              <BFormSelect
                v-model="schoolInsuranceCertificateForm.accident_type"
                :options="schoolInsuranceAccidentTypeOptions"
              />
            </div>
            <div class="col-12">
              <label class="form-label">Testigos en caso de trayecto</label>
              <BFormTextarea
                v-model="schoolInsuranceCertificateForm.witnesses"
                rows="2"
                placeholder="Nombres, apellidos y RUN"
              />
            </div>
            <div class="col-12">
              <label class="form-label">Circunstancia del accidente</label>
              <BFormTextarea
                v-model="schoolInsuranceCertificateForm.circumstance"
                rows="3"
                placeholder="Describa cómo ocurrió"
              />
            </div>
          </div>
        </section>

        <div class="d-flex flex-wrap justify-content-end gap-2 pt-3">
          <BButton variant="outline-secondary" @click="showSchoolInsuranceCertificateModal = false">
            Cancelar
          </BButton>
          <BButton
            variant="danger"
            :disabled="exportingSchoolInsuranceCertificate"
            @click="exportSchoolInsuranceCertificate"
          >
            <i class="mdi mdi-file-pdf-box me-1"></i>
            {{ exportingSchoolInsuranceCertificate ? "Generando..." : "Exportar certificado PDF" }}
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showModal" title="Editar atención de enfermería" size="xl" hide-footer scrollable>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Buscar estudiante</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectFormStudent" @cleared="clearFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-2">Seleccionada: {{ form.student_label }}</div>
          <InfirmaryStudentMedicalSummary
            v-if="form.student_profile_id"
            class="mt-3"
            :context="selectedStudentContext"
            :loading="studentContextLoading"
          />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de accidente</label>
          <BFormInput v-model="form.occurred_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de registro</label>
          <BFormInput v-model="form.attended_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Categoría</label>
          <BFormSelect v-model="form.attention_category" :options="normalizeOptions(catalogs.attention_categories)" />
        </div>
        <div v-if="isFormMentalHealth" class="col-12">
          <div class="infirmary-mental-health-panel">
            <div class="infirmary-mental-health-panel__heading">
              <span><i class="bx bx-heart"></i></span>
              <div>
                <strong>Categorización de salud mental</strong>
                <small>Selecciona el evento observado. Registra solo antecedentes necesarios para la atención.</small>
              </div>
            </div>
            <div class="infirmary-field-grid">
              <div>
                <label class="form-label">Tipo de atención</label>
                <BFormSelect v-model="form.mental_health_event_type" :options="mentalHealthEventOptions" />
              </div>
              <div v-if="isFormSelfHarm">
                <label class="form-label">Tipo de lesión por autolesión</label>
                <BFormSelect v-model="form.self_harm_injury_type" :options="selfHarmInjuryOptions" />
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4" v-if="isFormAccident">
          <label class="form-label">Tipo de accidente</label>
          <BFormSelect v-model="form.accident_location_type" :options="accidentLocationOptions" />
        </div>
        <div class="col-md-4" v-if="isFormAccident && form.accident_location_type === 'colegio'">
          <label class="form-label">Dependencia</label>
          <BFormSelect v-model="form.dependency_id" :options="dependencyOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Quién acompaña</label>
          <BFormSelect v-model="form.accompanied_by_type" :options="companionOptions" />
        </div>
        <div class="col-md-4" v-if="requiresCompanionStaff">
          <label class="form-label">Funcionaria que acompaña</label>
          <BFormSelect v-model="form.accompanied_by_staff_id" :options="companionStaffOptions" />
        </div>
        <div class="col-md-4" v-if="usesCompanionName">
          <label class="form-label">Detalle acompañante</label>
          <BFormInput v-model="form.accompanied_by_name" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Motivo de consulta</label>
          <BFormTextarea
            v-model="form.consultation_reason"
            rows="3"
            placeholder="Ej: dolor de cabeza persistente, mareo o malestar abdominal"
          />
        </div>
        <div class="col-12" v-if="isFormAccident">
          <label class="form-label">Circunstancia del accidente</label>
          <BFormTextarea
            v-model="form.accident_circumstance"
            rows="3"
            placeholder="Ej: alumna cae en clases de educación física"
          />
        </div>
        <div class="col-12">
          <label class="form-label">Bitácora</label>
          <BFormTextarea
            v-model="form.logbook"
            rows="3"
            placeholder="Ej: antecedentes, indicaciones recibidas o información relevante para seguimiento"
          />
        </div>

        <div class="col-12 infirmary-treatment-area">
          <div class="infirmary-treatment-toolbar">
            <div>
              <div class="fw-semibold">Tratamientos y signos vitales</div>
              <div class="text-muted small">Selecciona una o más categorías y completa solo los bloques necesarios.</div>
            </div>
            <BButton variant="outline-primary" size="sm" @click="addTreatment">Agregar tratamiento</BButton>
          </div>
          <div v-for="(treatment, index) in form.treatments" :key="`treatment-${index}`" class="infirmary-treatment-card">
            <div class="infirmary-treatment-card__header">
              <div class="fw-semibold">Tratamiento {{ index + 1 }}</div>
              <BButton variant="outline-danger" size="sm" @click="removeTreatment(index)">Quitar</BButton>
            </div>
            <div class="infirmary-treatment-grid">
              <div>
                <label class="form-label d-block">Categoría Tratamiento</label>
                <div class="infirmary-chip-group">
                  <label
                    v-for="option in treatmentCategoryOptions"
                    :key="`cat-${index}-${option.value}`"
                    class="infirmary-check-chip"
                    :class="{ 'is-selected': treatmentHasCategory(treatment, option.value) }"
                    :for="`cat-${index}-${option.value}`"
                  >
                    <input
                      :id="`cat-${index}-${option.value}`"
                      v-model="treatment.treatment_categories"
                      class="infirmary-check-chip__input"
                      type="checkbox"
                      :value="option.value"
                    />
                    <span class="infirmary-check-chip__box"></span>
                    <span class="infirmary-check-chip__text">{{ option.text }}</span>
                  </label>
                </div>
              </div>

              <div class="infirmary-treatment-section" v-if="treatmentHasCategory(treatment, 'fisico')">
                <div class="infirmary-treatment-section__title">Físico</div>
                <div class="infirmary-option-grid">
                  <label
                    v-for="option in physicalTreatmentOptions"
                    :key="`physical-${index}-${option.value}`"
                    class="infirmary-check-chip"
                    :class="{ 'is-selected': (treatment.treatment_types || []).includes(option.value) }"
                    :for="`physical-${index}-${option.value}`"
                  >
                    <input
                      :id="`physical-${index}-${option.value}`"
                      v-model="treatment.treatment_types"
                      class="infirmary-check-chip__input"
                      type="checkbox"
                      :value="option.value"
                    />
                    <span class="infirmary-check-chip__box"></span>
                    <span class="infirmary-check-chip__text">{{ option.text }}</span>
                  </label>
                </div>
                <div class="infirmary-field-grid mt-3" v-if="treatmentHasMedication(treatment)">
                  <div>
                    <label class="form-label">Medicamento</label>
                    <BFormSelect v-model="treatment.medication_id" :options="medicationOptions" />
                  </div>
                  <div>
                    <label class="form-label">Cantidad administrada</label>
                    <BFormInput v-model="treatment.medication_quantity" type="number" min="0.01" step="0.01" />
                  </div>
                </div>
              </div>

              <div class="infirmary-treatment-section" v-if="treatmentHasCategory(treatment, 'emocional')">
                <div class="infirmary-treatment-section__title">Emocional</div>
                <div class="infirmary-treatment-section__body">
                  <label class="form-label">Comentario</label>
                  <BFormTextarea v-model="treatment.emotional_comment" rows="3" placeholder="Comentario..." />
                  <div class="infirmary-field-grid">
                    <div>
                      <label class="form-label">Tipo de contención</label>
                      <BFormInput v-model="treatment.emotional_support_type" />
                    </div>
                    <div>
                      <label class="form-label">Tiempo aprox. (min)</label>
                      <BFormInput v-model="treatment.emotional_duration_minutes" type="number" min="1" />
                    </div>
                    <div>
                      <label class="form-label">Profesional interviniente</label>
                      <BFormSelect v-model="treatment.emotional_professional_id" :options="staffOptions" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="infirmary-treatment-section" v-if="treatmentHasCategory(treatment, 'derivacion')">
                <div class="infirmary-treatment-section__title">Derivación</div>
                <div class="infirmary-field-grid">
                  <div>
                    <label class="form-label">Tipo derivación</label>
                    <BFormSelect v-model="treatment.derivation_type" :options="treatmentDerivationOptions" />
                  </div>
                  <div>
                    <label class="form-label d-block">Se va con</label>
                    <div class="infirmary-chip-group">
                      <label
                        v-for="option in treatmentDerivationSupportOptions"
                        :key="`support-${index}-${option.value}`"
                        class="infirmary-check-chip"
                        :class="{ 'is-selected': (treatment.derivation_support_teams || []).includes(option.value) }"
                        :for="`support-${index}-${option.value}`"
                      >
                        <input
                          :id="`support-${index}-${option.value}`"
                          v-model="treatment.derivation_support_teams"
                          class="infirmary-check-chip__input"
                          type="checkbox"
                          :value="option.value"
                        />
                        <span class="infirmary-check-chip__box"></span>
                        <span class="infirmary-check-chip__text">{{ option.text }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="infirmary-treatment-section" v-if="treatmentHasCategory(treatment, 'csv')">
                <div class="infirmary-treatment-section__title">CSV</div>
                <div class="infirmary-field-grid">
                  <div>
                    <label class="form-label">Presión arterial</label>
                    <BFormInput v-model="treatment.blood_pressure" placeholder="Ingrese la presión arterial" />
                  </div>
                  <div>
                    <label class="form-label">Pulso</label>
                    <BFormInput v-model="treatment.pulse" type="number" min="1" placeholder="Ingrese el pulso" />
                  </div>
                  <div>
                    <label class="form-label">Saturación</label>
                    <BFormInput v-model="treatment.oxygen_saturation" type="number" min="1" max="100" placeholder="Ingrese la saturación" />
                  </div>
                </div>
              </div>

              <div class="infirmary-treatment-section" v-if="treatmentHasCategory(treatment, 'otro')">
                <div class="infirmary-treatment-section__title">OTRO</div>
                <label class="form-label">Detalle:</label>
                <BFormTextarea v-model="treatment.other_treatments" rows="4" placeholder="Escribe los detalles..." />
              </div>
              <div class="infirmary-treatment-section infirmary-treatment-section--muted">
                <label class="form-label">Notas clínicas</label>
                <BFormTextarea v-model="treatment.notes" rows="2" />
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Derivaciones</div>
            <BButton variant="outline-primary" size="sm" @click="addReferral">Agregar</BButton>
          </div>
          <div v-for="(referral, index) in form.referrals" :key="`ref-${index}`" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Destino</label>
                <BFormSelect v-model="referral.referral_type" :options="normalizeOptions(catalogs.referral_options)" />
              </div>
              <div class="col-12">
                <label class="form-label">Hora</label>
                <BFormInput v-model="referral.referred_at" type="datetime-local" />
              </div>
              <div class="col-12">
                <label class="form-label">Responsable</label>
                <BFormSelect v-model="referral.responsible_user_id" :options="userOptions" />
              </div>
              <div class="col-12">
                <label class="form-label">Motivo</label>
                <BFormTextarea v-model="referral.reason" rows="2" />
              </div>
              <div class="col-12">
                <label class="form-label">Resultado</label>
                <BFormInput v-model="referral.result" />
              </div>
              <div class="col-12 text-end">
                <BButton variant="outline-danger" size="sm" @click="removeReferral(index)">Quitar</BButton>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Llamados a apoderados</div>
            <BButton variant="outline-primary" size="sm" @click="addCall">Agregar</BButton>
          </div>
          <div v-for="(call, index) in form.calls" :key="`call-${index}`" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Fecha y hora</label>
                <BFormInput v-model="call.called_at" type="datetime-local" />
              </div>
              <div class="col-12">
                <label class="form-label">Persona contactada</label>
                <BFormInput v-model="call.person_contacted" />
              </div>
              <div class="col-12">
                <label class="form-label">Relación</label>
                <BFormInput v-model="call.relationship" />
              </div>
              <div class="col-12">
                <label class="form-label">Número</label>
                <BFormInput v-model="call.phone_number" />
              </div>
              <div class="col-12">
                <label class="form-label">Resultado</label>
                <BFormSelect v-model="call.call_status" :options="normalizeOptions(catalogs.call_status_options)" />
              </div>
              <div class="col-12">
                <label class="form-label">¿Qué ocurrió durante la llamada?</label>
                <BFormTextarea
                  v-model="call.conversation_summary"
                  rows="3"
                  placeholder="Registra lo conversado, indicaciones entregadas y respuesta del apoderado"
                />
              </div>
              <div class="col-12 text-end">
                <BButton variant="outline-danger" size="sm" @click="removeCall(index)">Quitar</BButton>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Seguimiento</div>
            <BButton variant="outline-primary" size="sm" @click="addFollowUp">Agregar</BButton>
          </div>
          <div v-for="(followUp, index) in form.follow_ups" :key="`fu-${index}`" class="border rounded p-2 mb-2">
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label">Fecha</label>
                <BFormInput v-model="followUp.followed_at" type="datetime-local" />
              </div>
              <div class="col-12">
                <label class="form-label">Responsable</label>
                <BFormSelect v-model="followUp.responsible_user_id" :options="userOptions" />
              </div>
              <div class="col-12">
                <label class="form-label">Comentario</label>
                <BFormTextarea v-model="followUp.comment" rows="2" />
              </div>
              <div class="col-12">
                <label class="form-label">Estado</label>
                <BFormSelect v-model="followUp.status" :options="normalizeOptions(catalogs.follow_up_status_options)" />
              </div>
              <div class="col-12">
                <label class="form-label">Próxima revisión</label>
                <BFormInput v-model="followUp.next_review_at" type="datetime-local" />
              </div>
              <div class="col-12 text-end">
                <BButton variant="outline-danger" size="sm" @click="removeFollowUp(index)">Quitar</BButton>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar atención" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style>
.infirmary-mental-health-panel {
  background: linear-gradient(135deg, #f7f3ff 0%, #f1f8ff 58%, #f8fbff 100%);
  border: 1px solid #d8d4f1;
  border-radius: 12px;
  box-shadow: 0 10px 26px rgba(73, 74, 137, 0.07);
  padding: 1rem;
}

.infirmary-mental-health-panel__heading {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  margin-bottom: 0.9rem;
}

.infirmary-mental-health-panel__heading > span {
  align-items: center;
  background: #fff;
  border: 1px solid #ddd7f4;
  border-radius: 10px;
  color: #6858b8;
  display: inline-flex;
  flex: 0 0 2.65rem;
  font-size: 1.35rem;
  height: 2.65rem;
  justify-content: center;
}

.infirmary-mental-health-panel__heading strong,
.infirmary-mental-health-panel__heading small {
  display: block;
}

.infirmary-mental-health-panel__heading strong {
  color: #37355b;
  font-size: 0.94rem;
}

.infirmary-mental-health-panel__heading small {
  color: #77758f;
  font-size: 0.76rem;
  line-height: 1.4;
  margin-top: 0.15rem;
}

.infirmary-view-field--mental-health {
  background: linear-gradient(135deg, #faf7ff, #f4f9ff);
  border-color: #d9d5ee;
}

.infirmary-view-field--mental-health strong {
  color: #56499a;
}

.infirmary-treatment-area {
  min-width: 0;
}

.infirmary-treatment-toolbar {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.infirmary-treatment-card {
  background: #fff;
  border: 1px solid #e5eaf4;
  border-radius: 8px;
  margin-bottom: 1rem;
  padding: 1rem;
}

.infirmary-treatment-card__header {
  align-items: center;
  border-bottom: 1px solid #eef2f8;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  margin: -0.25rem 0 1rem;
  padding-bottom: 0.75rem;
}

.infirmary-treatment-grid {
  display: grid;
  gap: 0.875rem;
  min-width: 0;
}

.infirmary-treatment-section {
  background: #fbfcff;
  border: 1px solid #e5eaf4;
  border-radius: 8px;
  min-width: 0;
  padding: 0.875rem;
}

.infirmary-treatment-section--muted {
  background: #fff;
}

.infirmary-treatment-section__title {
  color: #303846;
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 0.65rem;
}

.infirmary-treatment-section__body {
  display: grid;
  gap: 0.875rem;
}

.infirmary-call-section {
  background: #f7faff;
  border: 1px solid #dce5f2;
  border-radius: 8px;
  padding: 0.875rem;
}

.infirmary-call-toggle {
  align-items: center;
  cursor: pointer;
  display: flex;
  gap: 0.75rem;
  margin: 0;
}

.infirmary-call-toggle > input {
  height: 1rem;
  width: 1rem;
}

.infirmary-call-toggle__icon {
  align-items: center;
  background: #e9efff;
  border-radius: 8px;
  color: #5066d8;
  display: inline-flex;
  flex: 0 0 2.35rem;
  font-size: 1.25rem;
  height: 2.35rem;
  justify-content: center;
}

.infirmary-call-toggle strong,
.infirmary-call-toggle small {
  display: block;
}

.infirmary-call-toggle strong {
  color: #303846;
  font-size: 0.92rem;
}

.infirmary-call-toggle small {
  color: #707b8d;
  font-size: 0.78rem;
  margin-top: 0.15rem;
}

.infirmary-date-cell {
  white-space: nowrap;
}

.infirmary-row-actions {
  align-items: center;
  display: inline-grid;
  gap: 0.55rem;
  grid-template-columns: repeat(2, 42px);
  justify-content: center;
  white-space: nowrap;
}

.infirmary-row-actions .cnsc-action-btn + .cnsc-action-btn {
  margin-left: 0 !important;
}

.infirmary-edit-ficha-btn {
  align-items: center;
  display: inline-flex;
  gap: 0.35rem;
}

.school-insurance-form {
  display: grid;
  gap: 0;
}

.school-insurance-form__section {
  border-bottom: 1px solid #e5eaf4;
  padding: 0 0 1rem;
}

.school-insurance-form__section + .school-insurance-form__section {
  padding-top: 1rem;
}

.school-insurance-form__section h6 {
  color: #303846;
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 0.85rem;
}

.school-insurance-form__section .form-label {
  color: #4b5565;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 0.35rem;
}

.infirmary-view {
  display: grid;
  gap: 1rem;
}

.infirmary-view-header {
  align-items: flex-start;
  background: #f8fbff;
  border: 1px solid #e3eaf5;
  border-radius: 8px;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 1rem;
}

.infirmary-view-reference {
  background: #eef2ff;
  border: 1px solid #cfd7ff;
  border-radius: 8px;
  color: #3345b5;
  font-weight: 700;
  padding: 0.4rem 0.7rem;
  white-space: nowrap;
}

.infirmary-view-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}

.infirmary-view-grid--compact {
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.infirmary-view-field,
.infirmary-view-section,
.infirmary-view-item {
  border: 1px solid #e5eaf4;
  border-radius: 8px;
}

.infirmary-view-field {
  background: #fff;
  display: grid;
  gap: 0.2rem;
  min-width: 0;
  padding: 0.75rem;
}

.infirmary-view-field span,
.infirmary-view-label {
  color: #7a8498;
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.infirmary-view-field strong {
  color: #303846;
  font-size: 0.95rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.infirmary-view-field small {
  color: #7a8498;
}

.infirmary-view-section {
  background: #fbfcff;
  padding: 1rem;
}

.infirmary-view-section h6 {
  color: #303846;
  font-weight: 700;
  margin-bottom: 0.85rem;
}

.infirmary-view-section--contacts {
  background: #f7faff;
}

.infirmary-guardian-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.infirmary-guardian-card {
  background: #fff;
  border: 1px solid #dce5f2;
  border-radius: 8px;
  display: grid;
  gap: 0.25rem;
  min-width: 0;
  padding: 0.85rem;
}

.infirmary-guardian-card__heading {
  align-items: center;
  color: #63708a;
  display: flex;
  font-size: 0.72rem;
  font-weight: 700;
  justify-content: space-between;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.infirmary-guardian-card__heading i {
  color: #566ee8;
  font-size: 1.15rem;
}

.infirmary-guardian-card--restricted {
  background: #fff8f7;
  border-color: #e8a7a2;
  box-shadow: 0 8px 22px rgba(155, 45, 38, 0.08);
}

.infirmary-guardian-card--restricted .infirmary-guardian-card__heading,
.infirmary-guardian-card--restricted .infirmary-guardian-card__heading i {
  color: #a52f29;
}

.infirmary-guardian-restriction {
  background: #fff0ef;
  border: 1px solid #e5aaa5;
  border-radius: 6px;
  color: #842b26;
  display: grid;
  gap: 0.25rem;
  margin-top: 0.55rem;
  padding: 0.65rem;
}

.infirmary-guardian-restriction strong,
.infirmary-guardian-restriction b,
.infirmary-guardian-restriction span,
.infirmary-guardian-restriction small {
  display: block;
}

.infirmary-guardian-restriction strong { font-size: 0.76rem; text-transform: uppercase; }
.infirmary-guardian-restriction b { font-size: 0.82rem; }
.infirmary-guardian-restriction span,
.infirmary-guardian-restriction small { font-size: 0.72rem; }

.infirmary-guardian-card > strong {
  color: #303846;
  font-size: 0.95rem;
  overflow-wrap: anywhere;
}

.infirmary-guardian-card > small {
  color: #7a8498;
}

.infirmary-guardian-card__contact {
  border-top: 1px solid #edf0f5;
  display: grid;
  gap: 0.35rem;
  margin-top: 0.45rem;
  padding-top: 0.55rem;
}

.infirmary-guardian-card__contact a,
.infirmary-guardian-card__contact span {
  align-items: center;
  color: #526078;
  display: flex;
  font-size: 0.82rem;
  gap: 0.4rem;
  min-width: 0;
  overflow-wrap: anywhere;
}

.infirmary-guardian-card__contact a {
  color: #415ac5;
}

.infirmary-view-stack {
  display: grid;
  gap: 0.75rem;
}

.infirmary-view-stack p,
.infirmary-view-item p {
  color: #3f4858;
  margin-bottom: 0;
  overflow-wrap: anywhere;
}

.infirmary-view-list {
  display: grid;
  gap: 0.65rem;
}

.infirmary-view-item {
  background: #fff;
  padding: 0.75rem;
}

.infirmary-chip-group,
.infirmary-option-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  min-width: 0;
}

.infirmary-option-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
}

.infirmary-field-grid {
  display: grid;
  gap: 0.875rem;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  min-width: 0;
}

.infirmary-field-grid > * {
  min-width: 0;
}

.infirmary-check-chip {
  align-items: center;
  background: #fff;
  border: 1px solid #dce3ef;
  border-radius: 8px;
  color: #364152;
  cursor: pointer;
  display: inline-flex;
  font-size: 0.92rem;
  font-weight: 500;
  gap: 0.5rem;
  line-height: 1.25;
  max-width: 100%;
  min-height: 2rem;
  overflow: hidden;
  padding: 0.35rem 0.6rem;
  position: relative;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
  user-select: none;
}

.infirmary-check-chip:hover {
  border-color: #b9c5da;
}

.infirmary-check-chip:focus-within {
  box-shadow: 0 0 0 0.18rem rgba(87, 110, 231, 0.18);
}

.infirmary-check-chip.is-selected,
.infirmary-check-chip:has(.infirmary-check-chip__input:checked) {
  background: #eef2ff;
  border-color: #6578e8;
  color: #253681;
}

.infirmary-check-chip__input {
  cursor: pointer;
  inset: 0;
  opacity: 0;
  position: absolute;
}

.infirmary-check-chip__box {
  align-items: center;
  background: #fff;
  border: 1.5px solid #b8c2d6;
  border-radius: 5px;
  display: inline-flex;
  flex: 0 0 0.95rem;
  height: 0.95rem;
  justify-content: center;
  pointer-events: none;
  width: 0.95rem;
}

.infirmary-check-chip.is-selected .infirmary-check-chip__box,
.infirmary-check-chip:has(.infirmary-check-chip__input:checked) .infirmary-check-chip__box {
  background: #5b6fe5;
  border-color: #5b6fe5;
}

.infirmary-check-chip.is-selected .infirmary-check-chip__box::after,
.infirmary-check-chip:has(.infirmary-check-chip__input:checked) .infirmary-check-chip__box::after {
  border: solid #fff;
  border-width: 0 2px 2px 0;
  content: "";
  height: 0.5rem;
  margin-top: -0.1rem;
  transform: rotate(45deg);
  width: 0.28rem;
}

.infirmary-check-chip__text {
  min-width: 0;
  overflow-wrap: anywhere;
  pointer-events: none;
}

.infirmary-treatment-card .form-label,
.infirmary-attention-swal .form-label {
  color: #303846;
  font-size: 0.9rem;
  font-weight: 500;
  margin-bottom: 0.35rem;
}

.infirmary-treatment-card .fw-semibold,
.infirmary-attention-swal .fw-semibold {
  font-weight: 600 !important;
}

.infirmary-treatment-card .form-control,
.infirmary-treatment-card .form-select,
.infirmary-attention-swal .form-control,
.infirmary-attention-swal .form-select {
  border-color: #dfe5f1;
  border-radius: 8px;
  font-size: 0.92rem;
}

.infirmary-attention-swal .swal-student-search-group {
  align-items: stretch;
}

.infirmary-attention-swal .swal-student-search-group .form-control {
  border-bottom-right-radius: 0;
  border-right: 0;
  border-top-right-radius: 0;
  min-height: 2.5rem;
}

.infirmary-attention-swal .swal-student-search-group .btn {
  border-bottom-left-radius: 0;
  border-top-left-radius: 0;
  font-size: 0.92rem;
  font-weight: 600;
  min-height: 2.5rem;
  padding-left: 1rem;
  padding-right: 1rem;
}

.infirmary-attention-mode-tabs {
  background: #f3f6fa;
  border: 1px solid #e1e7f0;
  border-radius: 14px;
  display: grid;
  gap: 0.55rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin: 0 0 1.25rem;
  padding: 0.45rem;
}

.infirmary-attention-mode-tab {
  align-items: center;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 10px;
  color: #637087;
  display: flex;
  gap: 0.75rem;
  min-width: 0;
  padding: 0.75rem 0.85rem;
  text-align: left;
  transition: background 160ms ease, border-color 160ms ease, box-shadow 160ms ease, color 160ms ease;
}

.infirmary-attention-mode-tab:hover {
  background: rgba(255, 255, 255, 0.75);
  color: #38445a;
}

.infirmary-attention-mode-tab.is-active {
  background: #fff;
  border-color: #cddafb;
  box-shadow: 0 6px 18px rgba(44, 73, 145, 0.1);
  color: #3756b5;
}

.infirmary-attention-mode-tab--quick.is-active {
  border-color: #a9dfcb;
  box-shadow: 0 6px 18px rgba(31, 139, 102, 0.12);
  color: #147253;
}

.infirmary-attention-mode-tab__icon {
  align-items: center;
  background: #e9efff;
  border-radius: 9px;
  display: inline-flex;
  flex: 0 0 2.5rem;
  font-size: 1.35rem;
  height: 2.5rem;
  justify-content: center;
}

.infirmary-attention-mode-tab--quick .infirmary-attention-mode-tab__icon {
  background: #e4f7ef;
  color: #16825d;
}

.infirmary-attention-mode-tab strong,
.infirmary-attention-mode-tab small {
  display: block;
}

.infirmary-attention-mode-tab strong {
  font-size: 0.93rem;
}

.infirmary-attention-mode-tab small {
  color: #778297;
  font-size: 0.72rem;
  line-height: 1.25;
  margin-top: 0.15rem;
}

.infirmary-quick-form {
  display: grid;
  gap: 1rem;
}

.infirmary-quick-intro {
  align-items: center;
  background: linear-gradient(135deg, #e5f8f1 0%, #f3fbf8 100%);
  border: 1px solid #b9e5d5;
  border-radius: 12px;
  color: #175f49;
  display: flex;
  gap: 0.8rem;
  padding: 0.85rem 1rem;
}

.infirmary-quick-intro__icon {
  align-items: center;
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 4px 12px rgba(18, 111, 78, 0.12);
  display: inline-flex;
  flex: 0 0 2.7rem;
  font-size: 1.4rem;
  height: 2.7rem;
  justify-content: center;
}

.infirmary-quick-intro strong,
.infirmary-quick-intro span {
  display: block;
}

.infirmary-quick-intro strong {
  font-size: 0.93rem;
}

.infirmary-quick-intro span {
  font-size: 0.76rem;
  line-height: 1.35;
  margin-top: 0.12rem;
}

.infirmary-quick-section {
  background: #fff;
  border: 1px solid #e2e8f1;
  border-radius: 12px;
  padding: 1rem;
}

.infirmary-quick-section__heading {
  align-items: center;
  display: flex;
  gap: 0.65rem;
  margin-bottom: 0.8rem;
}

.infirmary-quick-section__heading > span {
  align-items: center;
  background: #e5f8f1;
  border-radius: 50%;
  color: #147253;
  display: inline-flex;
  flex: 0 0 1.8rem;
  font-size: 0.78rem;
  font-weight: 800;
  height: 1.8rem;
  justify-content: center;
}

.infirmary-quick-section__heading strong,
.infirmary-quick-section__heading small {
  display: block;
}

.infirmary-quick-section__heading strong {
  color: #2f394a;
  font-size: 0.9rem;
}

.infirmary-quick-section__heading small {
  color: #7b8799;
  font-size: 0.7rem;
  margin-top: 0.08rem;
}

.infirmary-quick-choice-grid,
.infirmary-quick-action-grid,
.infirmary-quick-result-grid {
  display: grid;
  gap: 0.65rem;
}

.infirmary-quick-choice-grid {
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
}

.infirmary-quick-action-grid {
  grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
}

.infirmary-quick-result-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.infirmary-quick-choice,
.infirmary-quick-action,
.infirmary-quick-result {
  cursor: pointer;
  margin: 0;
  min-width: 0;
  position: relative;
}

.infirmary-quick-choice__input,
.infirmary-quick-action__input,
.infirmary-quick-result__input,
.infirmary-quick-duration input {
  opacity: 0;
  pointer-events: none;
  position: absolute;
}

.infirmary-quick-choice__content,
.infirmary-quick-action__content,
.infirmary-quick-result__content {
  border: 1px solid #dfe6ef;
  border-radius: 9px;
  display: flex;
  height: 100%;
  transition: border-color 150ms ease, background 150ms ease, box-shadow 150ms ease, transform 150ms ease;
}

.infirmary-quick-choice__content {
  align-items: center;
  color: #4d596c;
  flex-direction: column;
  gap: 0.4rem;
  justify-content: center;
  min-height: 76px;
  padding: 0.65rem 0.5rem;
  text-align: center;
}

.infirmary-quick-choice__content > i {
  color: #6d7b91;
  font-size: 1.45rem;
}

.infirmary-quick-choice__content strong {
  font-size: 0.76rem;
  line-height: 1.25;
}

.infirmary-quick-action__content {
  align-items: center;
  color: #425066;
  gap: 0.65rem;
  min-height: 70px;
  padding: 0.65rem;
  position: relative;
}

.infirmary-quick-action__icon {
  align-items: center;
  background: #f0f4f9;
  border-radius: 8px;
  color: #607087;
  display: inline-flex;
  flex: 0 0 2.2rem;
  font-size: 1.2rem;
  height: 2.2rem;
  justify-content: center;
}

.infirmary-quick-action__content strong,
.infirmary-quick-action__content small,
.infirmary-quick-result__content strong,
.infirmary-quick-result__content small {
  display: block;
}

.infirmary-quick-action__content strong,
.infirmary-quick-result__content strong {
  font-size: 0.78rem;
}

.infirmary-quick-action__content small,
.infirmary-quick-result__content small {
  color: #7b8799;
  font-size: 0.67rem;
  line-height: 1.2;
  margin-top: 0.15rem;
}

.infirmary-quick-action__check {
  color: transparent;
  font-size: 1rem;
  margin-left: auto;
}

.infirmary-quick-result__content {
  align-items: center;
  gap: 0.55rem;
  min-height: 62px;
  padding: 0.6rem;
}

.infirmary-quick-result__content > i {
  color: #718096;
  font-size: 1.25rem;
}

.infirmary-quick-choice:hover .infirmary-quick-choice__content,
.infirmary-quick-action:hover .infirmary-quick-action__content,
.infirmary-quick-result:hover .infirmary-quick-result__content {
  border-color: #a9cdbc;
  transform: translateY(-1px);
}

.infirmary-quick-choice__input:checked + .infirmary-quick-choice__content,
.infirmary-quick-action__input:checked + .infirmary-quick-action__content,
.infirmary-quick-result__input:checked + .infirmary-quick-result__content {
  background: #eaf8f3;
  border-color: #42a882;
  box-shadow: 0 0 0 2px rgba(66, 168, 130, 0.12);
  color: #146f51;
}

.infirmary-quick-choice__input:checked + .infirmary-quick-choice__content > i,
.infirmary-quick-result__input:checked + .infirmary-quick-result__content > i {
  color: #16825d;
}

.infirmary-quick-action__input:checked + .infirmary-quick-action__content .infirmary-quick-action__icon {
  background: #d7f2e8;
  color: #147253;
}

.infirmary-quick-action__input:checked + .infirmary-quick-action__content .infirmary-quick-action__check {
  color: #16825d;
}

.infirmary-quick-choice__input:focus-visible + .infirmary-quick-choice__content,
.infirmary-quick-action__input:focus-visible + .infirmary-quick-action__content,
.infirmary-quick-result__input:focus-visible + .infirmary-quick-result__content,
.infirmary-quick-duration input:focus-visible + span {
  outline: 3px solid rgba(66, 168, 130, 0.25);
  outline-offset: 2px;
}

.infirmary-quick-bottom-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 1.35fr) minmax(300px, 1fr);
}

.infirmary-quick-duration {
  display: grid;
  gap: 0.45rem;
  grid-template-columns: repeat(3, 1fr);
}

.infirmary-quick-duration label {
  cursor: pointer;
  margin: 0;
  position: relative;
}

.infirmary-quick-duration span {
  background: #f6f8fb;
  border: 1px solid #dfe6ef;
  border-radius: 8px;
  color: #5a6679;
  display: block;
  font-size: 0.76rem;
  font-weight: 700;
  padding: 0.55rem;
  text-align: center;
}

.infirmary-quick-duration input:checked + span {
  background: #eaf8f3;
  border-color: #42a882;
  color: #146f51;
}

.infirmary-quick-safety-note {
  align-items: flex-start;
  background: #fff8e7;
  border: 1px solid #efd79b;
  border-radius: 10px;
  color: #6f571c;
  display: flex;
  font-size: 0.76rem;
  gap: 0.6rem;
  line-height: 1.4;
  padding: 0.75rem 0.85rem;
}

.infirmary-quick-safety-note > i {
  flex: 0 0 auto;
  font-size: 1.2rem;
}

.infirmary-attention-swal .infirmary-quick-confirm {
  background-color: #16825d !important;
}

.infirmary-attention-swal .swal-medical-context {
  margin-top: 0.65rem;
  overflow: hidden;
  border: 1px solid #dce4ef;
  border-radius: 8px;
  background: #f8fafc;
}

.infirmary-attention-swal .swal-medical-context__head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.8rem 0.9rem;
  background: #edf4fd;
}

.infirmary-attention-swal .swal-medical-context__head strong,
.infirmary-attention-swal .swal-medical-context__head span,
.infirmary-attention-swal .swal-medical-detail strong,
.infirmary-attention-swal .swal-medical-detail span {
  display: block;
}

.infirmary-attention-swal .swal-medical-context__head span {
  margin-top: 0.15rem;
  color: #6b7586;
  font-size: 0.78rem;
}

.infirmary-attention-swal .swal-medical-context__head b {
  align-self: center;
  color: #b33731;
  font-size: 0.78rem;
  white-space: nowrap;
}

.infirmary-attention-swal .swal-medical-alerts {
  display: grid;
  gap: 0.45rem;
  padding: 0.7rem 0.9rem 0;
}

.infirmary-attention-swal .swal-medical-alert,
.infirmary-attention-swal .swal-medical-ok {
  padding: 0.55rem 0.65rem;
  border-left: 4px solid #d89a2b;
  border-radius: 5px;
  background: #fff5df;
  color: #714c0d;
  font-size: 0.78rem;
}

.infirmary-attention-swal .swal-medical-alert--critical {
  border-color: #d8463f;
  background: #fff0ef;
  color: #922b25;
}

.infirmary-attention-swal .swal-medical-alert--info {
  border-color: #3974c8;
  background: #edf5ff;
  color: #28568f;
}

.infirmary-attention-swal .swal-medical-alert strong,
.infirmary-attention-swal .swal-medical-alert span {
  display: block;
}

.infirmary-attention-swal .swal-medical-ok {
  border-color: #2b9a70;
  background: #eaf8f2;
  color: #1d7254;
}

.infirmary-attention-swal .swal-medical-facts {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;
  margin: 0.7rem 0.9rem;
  background: #dce4ef;
  border: 1px solid #dce4ef;
}

.infirmary-attention-swal .swal-medical-facts > div {
  padding: 0.55rem;
  background: #fff;
}

.infirmary-attention-swal .swal-medical-facts span {
  display: block;
  color: #707b8d;
  font-size: 0.68rem;
  text-transform: uppercase;
}

.infirmary-attention-swal .swal-medical-facts strong {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.78rem;
}

.infirmary-attention-swal .swal-guardian-contacts {
  margin: 0 0.9rem 0.7rem;
  padding: 0.65rem;
  border: 1px solid #dbe5f2;
  border-radius: 6px;
  background: #f7faff;
}

.infirmary-attention-swal .swal-guardian-contacts__title {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.5rem;
  color: #526078;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.infirmary-attention-swal .swal-guardian-contacts__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.infirmary-attention-swal .swal-guardian-contact {
  min-width: 0;
  padding: 0.55rem 0.65rem;
  border: 1px solid #e0e7f1;
  border-radius: 5px;
  background: #fff;
}

.infirmary-attention-swal .swal-guardian-contact span,
.infirmary-attention-swal .swal-guardian-contact strong,
.infirmary-attention-swal .swal-guardian-contact small {
  display: block;
}

.infirmary-attention-swal .swal-guardian-contact span {
  color: #707b8d;
  font-size: 0.66rem;
  font-weight: 700;
  text-transform: uppercase;
}

.infirmary-attention-swal .swal-guardian-contact strong {
  margin-top: 0.15rem;
  overflow-wrap: anywhere;
  font-size: 0.78rem;
}

.infirmary-attention-swal .swal-guardian-contact small {
  margin-top: 0.25rem;
  color: #526078;
  font-size: 0.73rem;
}

.infirmary-attention-swal .swal-guardian-contact--empty {
  grid-column: 1 / -1;
  color: #707b8d;
  font-size: 0.75rem;
}

.infirmary-attention-swal .swal-protection-alert,
.infirmary-attention-swal .swal-call-restriction-alert {
  align-items: flex-start;
  background: #fff0ef;
  border: 1px solid #e3a39e;
  border-left: 5px solid #bf3b33;
  border-radius: 7px;
  color: #842b26;
  display: flex;
  gap: 0.65rem;
  padding: 0.75rem 0.85rem;
}

.infirmary-attention-swal .swal-protection-alert { margin: 0 0.9rem 0.7rem; }
.infirmary-attention-swal .swal-protection-alert > i,
.infirmary-attention-swal .swal-call-restriction-alert > i { font-size: 1.35rem; }
.infirmary-attention-swal .swal-protection-alert strong,
.infirmary-attention-swal .swal-protection-alert span,
.infirmary-attention-swal .swal-call-restriction-alert strong,
.infirmary-attention-swal .swal-call-restriction-alert span { display: block; }
.infirmary-attention-swal .swal-protection-alert strong,
.infirmary-attention-swal .swal-call-restriction-alert strong { font-size: 0.78rem; text-transform: uppercase; }
.infirmary-attention-swal .swal-protection-alert span,
.infirmary-attention-swal .swal-call-restriction-alert span { font-size: 0.74rem; margin-top: 0.15rem; }

.infirmary-attention-swal .swal-guardian-contact--restricted {
  background: #fff8f7;
  border-color: #e4aaa5;
}

.infirmary-attention-swal .swal-guardian-contact__restriction {
  background: #fff0ef;
  border-radius: 5px;
  color: #842b26;
  margin-top: 0.5rem;
  padding: 0.5rem;
}

.infirmary-attention-swal .swal-guardian-contact__restriction b,
.infirmary-attention-swal .swal-guardian-contact__restriction strong,
.infirmary-attention-swal .swal-guardian-contact__restriction small { display: block; }
.infirmary-attention-swal .swal-guardian-contact__restriction b { font-size: 0.68rem; }

.infirmary-attention-swal .swal-medical-detail {
  margin: 0 0.9rem 0.7rem;
  padding: 0.6rem;
  border: 1px solid #e1e7ef;
  border-radius: 5px;
  background: #fff;
  font-size: 0.76rem;
}

@media (max-width: 767.98px) {
  .infirmary-treatment-toolbar,
  .infirmary-treatment-card__header {
    align-items: stretch;
    flex-direction: column;
  }

  .infirmary-treatment-toolbar .btn,
  .infirmary-treatment-card__header .btn {
    width: 100%;
  }

  .infirmary-attention-swal .swal-guardian-contacts__grid {
    grid-template-columns: 1fr;
  }

  .infirmary-attention-mode-tabs,
  .infirmary-quick-bottom-grid,
  .infirmary-quick-result-grid {
    grid-template-columns: 1fr;
  }

  .infirmary-attention-mode-tab {
    padding: 0.65rem;
  }

  .infirmary-quick-action-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .infirmary-quick-action__content {
    align-items: flex-start;
    flex-direction: column;
  }

  .infirmary-quick-action__check {
    position: absolute;
    right: 0.55rem;
    top: 0.55rem;
  }

  .infirmary-guardian-grid {
    grid-template-columns: 1fr;
  }
}
</style>
