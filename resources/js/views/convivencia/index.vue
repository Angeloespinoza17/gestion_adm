<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/convivencia/help-button.vue";
import StatusBadge from "../../components/convivencia/status-badge.vue";
import CriticalityBadge from "../../components/convivencia/criticality-badge.vue";
import CaseSummaryCard from "../../components/convivencia/case-summary-card.vue";
import ConvivenciaTimeline from "../../components/convivencia/timeline.vue";
import ReportFilterBar from "../../components/convivencia/report-filter-bar.vue";
import {
  basicApexOptions,
  confirmConvivenciaAction,
  downloadExcelWorkbook,
  downloadPdfReport,
  extractChartLabels,
  extractChartTotals,
  formatConvivenciaDate,
  formatConvivenciaDateTime,
  formatConvivenciaError,
  humanizeConvivenciaStatus,
  normalizeOptions,
  showConvivenciaError,
  showConvivenciaSuccess,
  toInputDateTime,
} from "../../components/convivencia/module-utils";

const routeMap = {
  "/convivencia": "dashboard",
  "/convivencia/planes": "planes",
  "/convivencia/casos": "casos",
  "/convivencia/denuncias": "denuncias",
  "/convivencia/derivaciones": "derivaciones",
  "/convivencia/protocolos": "protocolos",
  "/convivencia/entrevistas": "entrevistas",
  "/convivencia/medidas": "medidas",
  "/convivencia/bitacora": "bitacora",
  "/convivencia/sociogramas": "sociogramas",
  "/convivencia/idps": "idps",
  "/convivencia/reportes": "reportes",
};

const tabState = (form) => ({
  loading: false,
  items: [],
  pagination: null,
  showForm: false,
  saving: false,
  filters: {},
  form: form(),
});

const emptyPlanAction = () => ({ action_type: "preventiva", title: "", description: "", starts_on: "", ends_on: "", status: "borrador", advance_percentage: 0 });
const emptyCasePerson = () => ({ person_type: "estudiante", role_type: "afectado", full_name: "", identifier: "", relationship_label: "", contact_reference: "", notes: "" });
const emptyProtocolStep = () => ({ stage_name: "", responsible_label: "", due_days: 1 });
const emptyInterviewParticipant = () => ({ participant_type: "estudiante", participant_role: "participante", full_name: "", contact_reference: "", notes: "" });
const emptyQuestion = () => ({ prompt: "", selection_type: "positiva", max_choices: 3, active: true });
const emptyAnswer = () => ({ question_order: 1, respondent_student_id: null, selected_student_id: null, selection_type: "positiva", notes: "" });

export default {
  components: {
    Layout,
    LoadingState,
    HelpButton,
    StatusBadge,
    CriticalityBadge,
    CaseSummaryCard,
    ConvivenciaTimeline,
    ReportFilterBar,
  },
  data() {
    return {
      catalogsLoading: false,
      catalogsError: null,
      catalogs: {
        academic_years: [],
        active_academic_year_id: null,
        courses: [],
        students: [],
        staff: [],
        users: [],
        departments: [],
        external_institutions: [],
        catalogs: {},
        capabilities: {},
      },
      references: {
        cases: [],
        complaints: [],
        plans: [],
        protocols: [],
      },
      tabs: [
        { key: "dashboard", route: "/convivencia", label: "Dashboard", capability: "can_view_dashboard" },
        { key: "planes", route: "/convivencia/planes", label: "Plan de gestión", capability: "can_manage_plans" },
        { key: "casos", route: "/convivencia/casos", label: "Casos", capability: "can_view_cases" },
        { key: "denuncias", route: "/convivencia/denuncias", label: "Denuncias", capability: "can_manage_complaints" },
        { key: "derivaciones", route: "/convivencia/derivaciones", label: "Derivaciones", capability: "can_manage_internal_derivations" },
        { key: "protocolos", route: "/convivencia/protocolos", label: "Protocolos", capability: "can_manage_protocols" },
        { key: "entrevistas", route: "/convivencia/entrevistas", label: "Entrevistas", capability: "can_manage_interviews" },
        { key: "medidas", route: "/convivencia/medidas", label: "Medidas", capability: "can_manage_measures" },
        { key: "bitacora", route: "/convivencia/bitacora", label: "Bitácora", capability: "can_manage_daily_logs" },
        { key: "sociogramas", route: "/convivencia/sociogramas", label: "Sociogramas", capability: "can_view_sociograms" },
        { key: "idps", route: "/convivencia/idps", label: "IDPS", capability: "can_manage_plans" },
        { key: "reportes", route: "/convivencia/reportes", label: "Reportes", capability: "can_view_course_reports" },
      ],
      meta: {
        dashboard: { title: "Dashboard de Convivencia", subtitle: "Indicadores, alertas y distribución de casos, protocolos y seguimiento.", help: "Muestra casos abiertos y cerrados, criticidad, protocolos activos, derivaciones, entrevistas, denuncias y alertas vencidas." },
        planes: { title: "Plan de Gestión de Convivencia Escolar", subtitle: "Administración anual de objetivos, acciones, responsables y avance del plan.", help: "Registra el plan anual con acciones preventivas, promocionales, formativas y reactivas, con trazabilidad de avance y responsables." },
        casos: { title: "Gestión de Casos", subtitle: "Registro central de casos, personas involucradas, seguimiento y cierre.", help: "Aquí se crean, editan, derivan y cierran casos de convivencia. El folio se genera automáticamente y el cierre exige resolución o conclusión." },
        denuncias: { title: "Ingreso de Denuncias", subtitle: "Recepción y revisión de denuncias con folio, admisibilidad y conversión en caso.", help: "Permite recibir denuncias internas o externas, registrar antecedentes, activar protocolo y convertir una denuncia en caso de convivencia." },
        derivaciones: { title: "Derivaciones Internas y Externas", subtitle: "Trazabilidad de derivaciones a equipos internos y redes externas.", help: "Registra derivaciones dentro del establecimiento o hacia redes externas, con prioridad, plazos, respuesta y seguimiento." },
        protocolos: { title: "Protocolos de Actuación", subtitle: "Catálogo de protocolos y activaciones asociadas a casos o denuncias.", help: "Administra protocolos configurables y permite activarlos sobre un caso o denuncia con seguimiento por etapas y plazos." },
        entrevistas: { title: "Entrevistas de Convivencia", subtitle: "Registro de entrevistas, participantes, acuerdos y compromisos.", help: "Permite registrar entrevistas con estudiantes, apoderados o funcionarios y dejar acuerdos y seguimiento." },
        medidas: { title: "Medidas Formativas", subtitle: "Seguimiento de acciones formativas, reparadoras y de mediación.", help: "Registra medidas asociadas a estudiantes, cursos o casos, con fecha de cumplimiento, evidencia y cierre." },
        bitacora: { title: "Bitácora de Inspectoría", subtitle: "Registro diario de hechos relevantes de la jornada y su eventual escalamiento.", help: "La bitácora diaria permite dejar constancia de atrasos, conflictos, observaciones positivas y otros hechos que luego pueden convertirse en caso o derivación." },
        sociogramas: { title: "Sociogramas", subtitle: "Aplicación y análisis simple de relaciones sociométricas por curso.", help: "Permite registrar preguntas, respuestas y visualizar liderazgos, aislamiento, rechazo y vínculos recíprocos en un curso." },
        idps: { title: "Indicadores IDPS", subtitle: "Períodos, dimensiones, instrumentos y resultados comparables.", help: "Configura períodos, dimensiones e instrumentos IDPS y vincula resultados con cursos, niveles y el plan de convivencia." },
        reportes: { title: "Reportes por Curso", subtitle: "Consolidado por curso con conflictos, medidas, entrevistas, sociogramas y clima.", help: "Entrega un resumen por curso con casos, bitácora, derivaciones, entrevistas, medidas e indicadores de clima, con exportación básica." },
      },
      dashboard: { loading: false, filters: { academic_year_id: null, course_section_id: null, from: "", to: "" }, data: null },
      plans: tabState(() => ({ id: null, academic_year_id: null, responsible_user_id: null, responsible_staff_id: null, name: "", general_objective: "", status: "borrador", starts_on: "", ends_on: "", observations: "", actions: [emptyPlanAction()] })),
      cases: tabState(() => ({ id: null, student_profile_id: null, course_section_id: null, classification_item_id: null, subclassification_item_id: null, criticality_item_id: null, responsible_user_id: null, responsible_staff_id: null, opened_at: "", happened_at: "", origin: "observacion", status: "abierto", place: "", initial_report: "", background: "", immediate_measures: "", safeguarding_measures: "", follow_up_due_at: "", is_sensitive: false, people: [emptyCasePerson()] })),
      complaints: tabState(() => ({ id: null, affected_student_id: null, course_section_id: null, situation_type_item_id: null, complainant_name: "", complainant_type: "apoderado", contact_email: "", contact_phone: "", place: "", happened_at: "", report_text: "", status: "recibida", truth_declaration_accepted: true })),
      derivations: tabState(() => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, scope: "internal", status: "ingresada", priority_level: "media", confidentiality_level: "reservada", destination_department_id: null, destination_user_id: null, destination_staff_id: null, external_institution_id: null, destination_label: "", derived_at: "", response_due_at: "", motive: "", narrative: "", response_text: "", suggested_actions: "", follow_up_notes: "", is_sensitive: false })),
      protocols: tabState(() => ({ id: null, protocol_type_item_id: null, criticality_item_id: null, name: "", description: "", default_due_days: 5, status: "activo", steps: [emptyProtocolStep()] })),
      protocolActivationForm: { protocol_id: null, case_id: null, complaint_id: null, status: "activo", current_stage_name: "", actions_taken: "", measures_adopted: "" },
      measures: tabState(() => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, measure_type_item_id: null, responsible_user_id: null, assigned_at: "", due_at: "", status: "asignada", description: "", training_objective: "", repair_action: "", responsible_notes: "", is_sensitive: false })),
      interviews: tabState(() => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, interview_type_item_id: null, responsible_user_id: null, interview_at: "", motive: "", topics: "", agreements: "", commitments: "", follow_up_date: "", follow_up_status: "pendiente", internal_notes: "", participants: [emptyInterviewParticipant()], is_sensitive: false })),
      dailyLogs: tabState(() => ({ id: null, student_profile_id: null, course_section_id: null, daily_log_type_item_id: null, inspector_user_id: null, happened_at: "", place: "", description: "", immediate_action: "", status: "registrado", guardian_informed: false, guardian_contact_note: "", is_sensitive: false })),
      sociograms: tabState(() => ({ id: null, academic_year_id: null, course_section_id: null, title: "", applied_on: "", status: "borrador", confidentiality_level: "alta_confidencialidad", interpretation: "", is_sensitive: true, questions: [emptyQuestion()], answers: [emptyAnswer()] })),
      idps: { loading: false, overview: null, periodForm: { academic_year_id: null, name: "", starts_on: "", ends_on: "", status: "abierto", notes: "" }, dimensionForm: { code: "", name: "", description: "", active: true }, instrumentForm: { dimension_id: null, name: "", description: "", response_type: "escala", scale_label: "" }, resultForm: { period_id: null, dimension_id: null, instrument_id: null, academic_year_id: null, course_section_id: null, education_level_id: null, related_plan_id: null, result_scope: "curso", reference_label: "", score: "", percentage: "", sample_size: "", qualitative_observations: "", improvement_actions: "" } },
      reports: { loading: false, filters: { academic_year_id: null, course_section_id: null, from: "", to: "", semester: null }, data: null },
      caseDetailModal: false,
      caseDetail: null,
      caseFollowUpForm: {
        follow_up_at: "",
        title: "",
        notes: "",
        next_follow_up_at: "",
      },
      caseFollowUpSaving: false,
    };
  },
  computed: {
    activeTab() {
      return routeMap[this.$route.path] || "dashboard";
    },
    activeMeta() {
      return this.meta[this.activeTab];
    },
    visibleTabs() {
      const capabilities = this.catalogs.capabilities || {};
      return this.tabs.filter((tab) => capabilities[tab.capability] || capabilities.can_view_cases || tab.key === "dashboard");
    },
    classificationOptions() {
      return this.catalogOptions("classification");
    },
    subclassificationOptions() {
      return this.catalogOptions("subclassification");
    },
    criticalityOptions() {
      return this.catalogOptions("criticality");
    },
    caseReferenceOptions() {
      return this.references.cases.map((item) => ({ value: item.id, text: `${item.folio} · ${item.student?.registered_name_resolved || item.student?.registered_name || item.classification_label || "Caso"}` }));
    },
    complaintReferenceOptions() {
      return this.references.complaints.map((item) => ({ value: item.id, text: `${item.folio} · ${item.affected_student?.registered_name_resolved || item.affected_student?.registered_name || item.situation_type_label || "Denuncia"}` }));
    },
    planReferenceOptions() {
      return this.references.plans.map((item) => ({ value: item.id, text: item.name }));
    },
    protocolReferenceOptions() {
      return this.references.protocols.map((item) => ({ value: item.id, text: item.name }));
    },
    idpsPeriods() {
      return this.idps.overview?.periods || [];
    },
    idpsDimensions() {
      return this.idps.overview?.dimensions || [];
    },
    idpsResults() {
      return this.idps.overview?.results?.data || [];
    },
  },
  watch: {
    "$route.path"() {
      this.loadActiveTab();
    },
  },
  mounted() {
    this.loadCatalogs();
  },
  methods: {
    catalogOptions(group) {
      return (this.catalogs.catalogs?.[group] || []).map((item) => ({ value: item.id, text: item.name }));
    },
    courseOptions(includeEmpty = true) {
      return normalizeOptions(this.catalogs.courses, includeEmpty);
    },
    studentOptions(includeEmpty = true) {
      return normalizeOptions(this.catalogs.students, includeEmpty);
    },
    userOptions(includeEmpty = true) {
      return normalizeOptions(this.catalogs.users, includeEmpty);
    },
    departmentOptions(includeEmpty = true) {
      return normalizeOptions(this.catalogs.departments, includeEmpty);
    },
    institutionOptions(includeEmpty = true) {
      return normalizeOptions(this.catalogs.external_institutions, includeEmpty);
    },
    statusText(value) {
      return humanizeConvivenciaStatus(value);
    },
    normalizedOptions(items, includeEmpty = false, emptyLabel = "Seleccione") {
      return normalizeOptions(items, includeEmpty, emptyLabel);
    },
    chartLabels(items) {
      return extractChartLabels(items);
    },
    apexOptions(config = {}) {
      return basicApexOptions(config);
    },
    async ensureReferences(keys = []) {
      const jobs = [];

      if (keys.includes("cases") && this.references.cases.length === 0) {
        jobs.push(
          axios
            .get("/api/convivencia/cases", { params: { per_page: 100 } })
            .then((response) => {
              this.references.cases = response.data?.data || [];
            })
        );
      }

      if (keys.includes("complaints") && this.references.complaints.length === 0) {
        jobs.push(
          axios
            .get("/api/convivencia/complaints", { params: { per_page: 100 } })
            .then((response) => {
              this.references.complaints = response.data?.data || [];
            })
        );
      }

      if (keys.includes("plans") && this.references.plans.length === 0) {
        jobs.push(
          axios
            .get("/api/convivencia/plans", { params: { per_page: 100 } })
            .then((response) => {
              this.references.plans = response.data?.data || [];
            })
        );
      }

      if (keys.includes("protocols") && this.references.protocols.length === 0) {
        jobs.push(
          axios
            .get("/api/convivencia/protocols", { params: { per_page: 100 } })
            .then((response) => {
              this.references.protocols = response.data?.data || [];
            })
        );
      }

      if (jobs.length === 0) {
        return;
      }

      await Promise.allSettled(jobs);
    },
    resetForm(section) {
      const defaults = {
        planes: () => ({ id: null, academic_year_id: this.catalogs.active_academic_year_id, responsible_user_id: null, responsible_staff_id: null, name: "", general_objective: "", status: "borrador", starts_on: "", ends_on: "", observations: "", actions: [emptyPlanAction()] }),
        casos: () => ({ id: null, student_profile_id: null, course_section_id: null, classification_item_id: null, subclassification_item_id: null, criticality_item_id: null, responsible_user_id: null, responsible_staff_id: null, opened_at: toInputDateTime(new Date().toISOString()), happened_at: "", origin: "observacion", status: "abierto", place: "", initial_report: "", background: "", immediate_measures: "", safeguarding_measures: "", follow_up_due_at: "", is_sensitive: false, people: [emptyCasePerson()] }),
        denuncias: () => ({ id: null, affected_student_id: null, course_section_id: null, situation_type_item_id: null, complainant_name: "", complainant_type: "apoderado", contact_email: "", contact_phone: "", place: "", happened_at: "", report_text: "", status: "recibida", truth_declaration_accepted: true }),
        derivaciones: () => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, scope: "internal", status: "ingresada", priority_level: "media", confidentiality_level: "reservada", destination_department_id: null, destination_user_id: null, destination_staff_id: null, external_institution_id: null, destination_label: "", derived_at: toInputDateTime(new Date().toISOString()), response_due_at: "", motive: "", narrative: "", response_text: "", suggested_actions: "", follow_up_notes: "", is_sensitive: false }),
        protocolos: () => ({ id: null, protocol_type_item_id: null, criticality_item_id: null, name: "", description: "", default_due_days: 5, status: "activo", steps: [emptyProtocolStep()] }),
        medidas: () => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, measure_type_item_id: null, responsible_user_id: null, assigned_at: toInputDateTime(new Date().toISOString()), due_at: "", status: "asignada", description: "", training_objective: "", repair_action: "", responsible_notes: "", is_sensitive: false }),
        entrevistas: () => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, interview_type_item_id: null, responsible_user_id: null, interview_at: toInputDateTime(new Date().toISOString()), motive: "", topics: "", agreements: "", commitments: "", follow_up_date: "", follow_up_status: "pendiente", internal_notes: "", participants: [emptyInterviewParticipant()], is_sensitive: false }),
        bitacora: () => ({ id: null, student_profile_id: null, course_section_id: null, daily_log_type_item_id: null, inspector_user_id: this.inspectorUserId(), happened_at: toInputDateTime(new Date().toISOString()), place: "", description: "", immediate_action: "", status: "registrado", guardian_informed: false, guardian_contact_note: "", is_sensitive: false }),
        sociogramas: () => ({ id: null, academic_year_id: this.catalogs.active_academic_year_id, course_section_id: null, title: "", applied_on: "", status: "borrador", confidentiality_level: "alta_confidencialidad", interpretation: "", is_sensitive: true, questions: [emptyQuestion()], answers: [emptyAnswer()] }),
      };
      this[section].form = defaults[section] ? defaults[section]() : {};
      this[section].showForm = false;
    },
    async loadCatalogs() {
      this.catalogsLoading = true;
      this.catalogsError = null;
      try {
        const response = await axios.get("/api/convivencia/catalogs");
        this.catalogs = response.data || this.catalogs;
        this.resetForm("planes");
        this.resetForm("casos");
        this.resetForm("denuncias");
        this.resetForm("derivaciones");
        this.resetForm("protocolos");
        this.resetForm("medidas");
        this.resetForm("entrevistas");
        this.resetForm("bitacora");
        this.resetForm("sociogramas");
        this.dashboard.filters.academic_year_id = this.catalogs.active_academic_year_id;
        this.reports.filters.academic_year_id = this.catalogs.active_academic_year_id;
        this.idps.periodForm.academic_year_id = this.catalogs.active_academic_year_id;
        this.idps.resultForm.academic_year_id = this.catalogs.active_academic_year_id;
        this.loadActiveTab();
      } catch (error) {
        this.catalogsError = formatConvivenciaError(error, "No se pudieron cargar los catálogos de convivencia.");
      } finally {
        this.catalogsLoading = false;
      }
    },
    async loadActiveTab() {
      if (this.catalogsLoading) return;
      const map = {
        dashboard: this.loadDashboard,
        planes: this.loadPlans,
        casos: this.loadCases,
        denuncias: this.loadComplaints,
        derivaciones: this.loadDerivations,
        protocolos: this.loadProtocols,
        entrevistas: this.loadInterviews,
        medidas: this.loadMeasures,
        bitacora: this.loadDailyLogs,
        sociogramas: this.loadSociograms,
        idps: this.loadIdps,
        reportes: this.loadReports,
      };
      if (map[this.activeTab]) await map[this.activeTab].call(this);
    },
    setPaginated(state, payload) {
      state.items = payload?.data || [];
      state.pagination = { current_page: payload?.current_page, last_page: payload?.last_page, total: payload?.total };
    },
    async loadDashboard() {
      this.dashboard.loading = true;
      try {
        const response = await axios.get("/api/convivencia/dashboard", { params: this.dashboard.filters });
        this.dashboard.data = response.data;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.dashboard.loading = false;
      }
    },
    async loadPlans() {
      await this.loadCollection("plans", "/api/convivencia/plans", this.plans.filters);
      this.references.plans = this.plans.items;
    },
    async loadCases() {
      await this.loadCollection("cases", "/api/convivencia/cases", this.cases.filters);
      this.references.cases = this.cases.items;
    },
    async loadComplaints() {
      await this.loadCollection("complaints", "/api/convivencia/complaints", this.complaints.filters);
      this.references.complaints = this.complaints.items;
    },
    async loadDerivations() {
      await this.ensureReferences(["cases"]);
      await this.loadCollection("derivations", "/api/convivencia/derivations", this.derivations.filters);
    },
    async loadProtocols() {
      this.protocols.loading = true;
      try {
        await this.ensureReferences(["cases", "complaints"]);
        const [protocols, activations] = await Promise.all([
          axios.get("/api/convivencia/protocols", { params: this.protocols.filters }),
          axios.get("/api/convivencia/protocol-activations"),
        ]);
        this.setPaginated(this.protocols, protocols.data);
        this.references.protocols = this.protocols.items;
        this.protocols.activations = activations.data.data || [];
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.protocols.loading = false;
      }
    },
    async loadMeasures() {
      await this.ensureReferences(["cases"]);
      await this.loadCollection("measures", "/api/convivencia/measures", this.measures.filters);
    },
    async loadInterviews() {
      await this.ensureReferences(["cases"]);
      await this.loadCollection("interviews", "/api/convivencia/interviews", this.interviews.filters);
    },
    async loadDailyLogs() {
      await this.loadCollection("dailyLogs", "/api/convivencia/daily-logs", this.dailyLogs.filters);
    },
    async loadSociograms() {
      await this.loadCollection("sociograms", "/api/convivencia/sociograms", this.sociograms.filters);
    },
    async loadIdps() {
      this.idps.loading = true;
      try {
        await this.ensureReferences(["plans"]);
        const response = await axios.get("/api/convivencia/idps");
        this.idps.overview = response.data;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.idps.loading = false;
      }
    },
    async loadReports() {
      this.reports.loading = true;
      try {
        const response = await axios.get("/api/convivencia/reports/course", { params: this.reports.filters });
        this.reports.data = response.data;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.reports.loading = false;
      }
    },
    async loadCollection(key, endpoint, params = {}) {
      this[key].loading = true;
      try {
        const response = await axios.get(endpoint, { params });
        this.setPaginated(this[key], response.data);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this[key].loading = false;
      }
    },
    async savePlans() {
      await this.saveResource("plans", "/api/convivencia/plans", this.plans.form, this.loadPlans, "Plan guardado correctamente.");
    },
    async saveCases() {
      await this.saveResource("cases", "/api/convivencia/cases", this.cases.form, this.loadCases, "Caso guardado correctamente.");
    },
    async saveComplaints() {
      await this.saveResource("complaints", "/api/convivencia/complaints", this.complaints.form, this.loadComplaints, "Denuncia guardada correctamente.");
    },
    async saveDerivations() {
      await this.saveResource("derivations", "/api/convivencia/derivations", this.derivations.form, this.loadDerivations, "Derivación guardada correctamente.");
    },
    async saveProtocols() {
      await this.saveResource("protocols", "/api/convivencia/protocols", this.protocols.form, this.loadProtocols, "Protocolo guardado correctamente.");
    },
    async saveMeasures() {
      await this.saveResource("measures", "/api/convivencia/measures", this.measures.form, this.loadMeasures, "Medida formativa guardada correctamente.");
    },
    async saveInterviews() {
      await this.saveResource("interviews", "/api/convivencia/interviews", this.interviews.form, this.loadInterviews, "Entrevista guardada correctamente.");
    },
    async saveDailyLogs() {
      await this.saveResource("dailyLogs", "/api/convivencia/daily-logs", this.dailyLogs.form, this.loadDailyLogs, "Hecho diario guardado correctamente.");
    },
    async saveSociograms() {
      await this.saveResource("sociograms", "/api/convivencia/sociograms", this.sociograms.form, this.loadSociograms, "Sociograma guardado correctamente.");
    },
    async saveResource(key, endpoint, payload, reload, successText) {
      this[key].saving = true;
      try {
        const body = JSON.parse(JSON.stringify(payload));
        if (body.opened_at) body.opened_at = body.opened_at.replace("T", " ");
        if (body.happened_at) body.happened_at = body.happened_at.replace("T", " ");
        if (body.follow_up_due_at) body.follow_up_due_at = body.follow_up_due_at.replace("T", " ");
        if (body.interview_at) body.interview_at = body.interview_at.replace("T", " ");
        if (body.assigned_at) body.assigned_at = body.assigned_at.replace("T", " ");
        if (body.due_at && body.due_at.includes("T")) body.due_at = body.due_at.replace("T", " ");
        if (body.derived_at && body.derived_at.includes("T")) body.derived_at = body.derived_at.replace("T", " ");
        if (body.response_due_at && body.response_due_at.includes("T")) body.response_due_at = body.response_due_at.replace("T", " ");
        if (body.happened_at && body.happened_at.includes("T")) body.happened_at = body.happened_at.replace("T", " ");
        const request = body.id ? axios.put(`${endpoint}/${body.id}`, body) : axios.post(endpoint, body);
        await request;
        await showConvivenciaSuccess(successText);
        if (key === "plans") this.resetForm("planes");
        if (key === "cases") this.resetForm("casos");
        if (key === "complaints") this.resetForm("denuncias");
        if (key === "derivations") this.resetForm("derivaciones");
        if (key === "protocols") this.resetForm("protocolos");
        if (key === "measures") this.resetForm("medidas");
        if (key === "interviews") this.resetForm("entrevistas");
        if (key === "dailyLogs") this.resetForm("bitacora");
        if (key === "sociograms") this.resetForm("sociogramas");
        await reload.call(this);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this[key].saving = false;
      }
    },
    editItem(section, item) {
      const cloned = JSON.parse(JSON.stringify(item));
      if (section === "planes") {
        this.plans.form = { id: cloned.id, academic_year_id: cloned.academic_year_id, responsible_user_id: cloned.responsible_user_id, responsible_staff_id: cloned.responsible_staff_id, name: cloned.name, general_objective: cloned.general_objective, status: cloned.status, starts_on: cloned.starts_on, ends_on: cloned.ends_on, observations: cloned.observations, actions: (cloned.actions || []).map((action) => ({ action_type: action.action_type, title: action.title, description: action.description, starts_on: action.starts_on, ends_on: action.ends_on, status: action.status, advance_percentage: action.advance_percentage })) };
        this.plans.showForm = true;
      }
      if (section === "casos") {
        this.cases.form = { id: cloned.id, student_profile_id: cloned.student_profile_id, course_section_id: cloned.course_section_id, classification_item_id: cloned.classification_item_id, subclassification_item_id: cloned.subclassification_item_id, criticality_item_id: cloned.criticality_item_id, responsible_user_id: cloned.responsible_user_id, responsible_staff_id: cloned.responsible_staff_id, opened_at: toInputDateTime(cloned.opened_at), happened_at: toInputDateTime(cloned.happened_at), origin: cloned.origin, status: cloned.status, place: cloned.place, initial_report: cloned.initial_report, background: cloned.background, immediate_measures: cloned.immediate_measures, safeguarding_measures: cloned.safeguarding_measures, follow_up_due_at: toInputDateTime(cloned.follow_up_due_at), is_sensitive: cloned.is_sensitive, people: (cloned.people || []).length ? cloned.people.map((person) => ({ person_type: person.person_type, role_type: person.role_type, full_name: person.full_name, identifier: person.identifier, relationship_label: person.relationship_label, contact_reference: person.contact_reference, notes: person.notes, student_profile_id: person.student_profile_id, course_section_id: person.course_section_id })) : [emptyCasePerson()] };
        this.cases.showForm = true;
      }
      if (section === "denuncias") {
        this.complaints.form = { id: cloned.id, affected_student_id: cloned.affected_student_id, course_section_id: cloned.course_section_id, situation_type_item_id: cloned.situation_type_item_id, complainant_name: cloned.complainant_name, complainant_type: cloned.complainant_type, contact_email: cloned.contact_email, contact_phone: cloned.contact_phone, place: cloned.place, happened_at: toInputDateTime(cloned.happened_at), report_text: cloned.report_text, status: cloned.status, truth_declaration_accepted: cloned.truth_declaration_accepted };
        this.complaints.showForm = true;
      }
      if (section === "derivaciones") {
        this.derivations.form = { id: cloned.id, case_id: cloned.case_id, student_profile_id: cloned.student_profile_id, course_section_id: cloned.course_section_id, scope: cloned.scope, status: cloned.status, priority_level: cloned.priority_level, confidentiality_level: cloned.confidentiality_level, destination_department_id: cloned.destination_department_id, destination_user_id: cloned.destination_user_id, destination_staff_id: cloned.destination_staff_id, external_institution_id: cloned.external_institution_id, destination_label: cloned.destination_label, derived_at: toInputDateTime(cloned.derived_at), response_due_at: toInputDateTime(cloned.response_due_at), motive: cloned.motive, narrative: cloned.narrative, response_text: cloned.response_text, suggested_actions: cloned.suggested_actions, follow_up_notes: cloned.follow_up_notes, is_sensitive: cloned.is_sensitive };
        this.derivations.showForm = true;
      }
      if (section === "protocolos") {
        this.protocols.form = { id: cloned.id, protocol_type_item_id: cloned.protocol_type_item_id, criticality_item_id: cloned.criticality_item_id, name: cloned.name, description: cloned.description, default_due_days: cloned.default_due_days, status: cloned.status, steps: (cloned.steps || []).map((step) => ({ stage_name: step.stage_name, responsible_label: step.responsible_label, due_days: step.due_days })) };
        this.protocols.showForm = true;
      }
      if (section === "medidas") {
        this.measures.form = { id: cloned.id, case_id: cloned.case_id, student_profile_id: cloned.student_profile_id, course_section_id: cloned.course_section_id, measure_type_item_id: cloned.measure_type_item_id, responsible_user_id: cloned.responsible_user_id, assigned_at: toInputDateTime(cloned.assigned_at), due_at: toInputDateTime(cloned.due_at), status: cloned.status, description: cloned.description, training_objective: cloned.training_objective, repair_action: cloned.repair_action, responsible_notes: cloned.responsible_notes, is_sensitive: cloned.is_sensitive };
        this.measures.showForm = true;
      }
      if (section === "entrevistas") {
        this.interviews.form = { id: cloned.id, case_id: cloned.case_id, student_profile_id: cloned.student_profile_id, course_section_id: cloned.course_section_id, interview_type_item_id: cloned.interview_type_item_id, responsible_user_id: cloned.responsible_user_id, interview_at: toInputDateTime(cloned.interview_at), motive: cloned.motive, topics: cloned.topics, agreements: cloned.agreements, commitments: cloned.commitments, follow_up_date: cloned.follow_up_date, follow_up_status: cloned.follow_up_status, internal_notes: cloned.internal_notes, participants: (cloned.participants || []).length ? cloned.participants.map((participant) => ({ participant_type: participant.participant_type, participant_role: participant.participant_role, full_name: participant.full_name, contact_reference: participant.contact_reference, notes: participant.notes, student_profile_id: participant.student_profile_id })) : [emptyInterviewParticipant()], is_sensitive: cloned.is_sensitive };
        this.interviews.showForm = true;
      }
      if (section === "bitacora") {
        this.dailyLogs.form = { id: cloned.id, student_profile_id: cloned.student_profile_id, course_section_id: cloned.course_section_id, daily_log_type_item_id: cloned.daily_log_type_item_id, inspector_user_id: cloned.inspector_user_id, happened_at: toInputDateTime(cloned.happened_at), place: cloned.place, description: cloned.description, immediate_action: cloned.immediate_action, status: cloned.status, guardian_informed: cloned.guardian_informed, guardian_contact_note: cloned.guardian_contact_note, is_sensitive: cloned.is_sensitive };
        this.dailyLogs.showForm = true;
      }
      if (section === "sociogramas") {
        this.sociograms.form = { id: cloned.id, academic_year_id: cloned.academic_year_id, course_section_id: cloned.course_section_id, title: cloned.title, applied_on: cloned.applied_on, status: cloned.status, confidentiality_level: cloned.confidentiality_level, interpretation: cloned.interpretation, is_sensitive: cloned.is_sensitive, questions: (cloned.questions || []).length ? cloned.questions.map((question) => ({ prompt: question.prompt, selection_type: question.selection_type, max_choices: question.max_choices, active: question.active })) : [emptyQuestion()], answers: (cloned.answers || []).map((answer, index) => ({ question_order: index + 1, respondent_student_id: answer.respondent_student_id, selected_student_id: answer.selected_student_id, selection_type: answer.selection_type, notes: answer.notes })) };
        this.sociograms.showForm = true;
      }
    },
    async deleteItem(endpoint, id, reload, text = "Se archivará el registro seleccionado.") {
      const confirmation = await confirmConvivenciaAction({ title: "Confirmar eliminación", text, confirmButtonText: "Sí, eliminar" });
      if (!confirmation.isConfirmed) return;
      try {
        await axios.delete(`${endpoint}/${id}`);
        await showConvivenciaSuccess("Registro archivado correctamente.");
        await reload.call(this);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async convertComplaint(item) {
      const confirmation = await confirmConvivenciaAction({ title: "Convertir denuncia en caso", text: `Se generará un caso a partir de la denuncia ${item.folio}.`, confirmButtonText: "Convertir" });
      if (!confirmation.isConfirmed) return;
      try {
        await axios.post(`/api/convivencia/complaints/${item.id}/convert-to-case`, {
          classification_item_id: this.classificationOptions[0]?.value,
          criticality_item_id: this.criticalityOptions[1]?.value || this.criticalityOptions[0]?.value,
          responsible_user_id: this.catalogs.users[0]?.id,
          responsible_staff_id: this.catalogs.users[0]?.staff_id || null,
        });
        await showConvivenciaSuccess("La denuncia fue convertida correctamente en caso.");
        this.loadComplaints();
        this.loadCases();
        this.ensureReferences(["cases", "complaints"]);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async closeCase(item) {
      const confirmation = await confirmConvivenciaAction({ title: "Cerrar caso", text: `Se cerrará el caso ${item.folio}.`, confirmButtonText: "Cerrar caso" });
      if (!confirmation.isConfirmed) return;
      try {
        await axios.post(`/api/convivencia/cases/${item.id}/close`, {
          resolution: "Caso cerrado desde la interfaz del módulo.",
          conclusion: "Se aplicaron acuerdos y seguimiento suficiente para cierre.",
        });
        await showConvivenciaSuccess("Caso cerrado correctamente.");
        this.loadCases();
        this.loadDashboard();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async openCaseDetail(item) {
      try {
        const response = await axios.get(`/api/convivencia/cases/${item.id}`);
        this.caseDetail = response.data?.data || null;
        this.caseDetailModal = true;
        this.caseFollowUpForm = {
          follow_up_at: toInputDateTime(new Date().toISOString()),
          title: "",
          notes: "",
          next_follow_up_at: this.caseDetail?.follow_up_due_at ? toInputDateTime(this.caseDetail.follow_up_due_at) : "",
        };
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo cargar el detalle del caso."));
      }
    },
    async saveCaseFollowUp() {
      if (!this.caseDetail?.id) return;

      this.caseFollowUpSaving = true;

      try {
        await axios.post(`/api/convivencia/cases/${this.caseDetail.id}/follow-ups`, {
          follow_up_at: this.caseFollowUpForm.follow_up_at?.replace("T", " "),
          title: this.caseFollowUpForm.title || null,
          notes: this.caseFollowUpForm.notes,
          next_follow_up_at: this.caseFollowUpForm.next_follow_up_at ? this.caseFollowUpForm.next_follow_up_at.replace("T", " ") : null,
        });

        await showConvivenciaSuccess("Seguimiento registrado correctamente.");
        await this.openCaseDetail(this.caseDetail);
        await this.loadCases();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo registrar el seguimiento del caso."));
      } finally {
        this.caseFollowUpSaving = false;
      }
    },
    async convertDailyLogToCase(item) {
      try {
        await axios.post(`/api/convivencia/daily-logs/${item.id}/convert-to-case`, {
          classification_item_id: this.classificationOptions[1]?.value || this.classificationOptions[0]?.value,
          criticality_item_id: this.criticalityOptions[1]?.value || this.criticalityOptions[0]?.value,
          responsible_user_id: this.inspectorUserId(),
        });
        await showConvivenciaSuccess("La bitácora fue convertida correctamente en caso.");
        this.loadDailyLogs();
        this.loadCases();
        this.ensureReferences(["cases"]);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async convertDailyLogToDerivation(item) {
      try {
        await axios.post(`/api/convivencia/daily-logs/${item.id}/convert-to-derivation`, {
          scope: "internal",
          status: "ingresada",
          priority_level: "media",
          confidentiality_level: "reservada",
          destination_department_id: this.catalogs.departments[0]?.id || null,
          destination_label: this.catalogs.departments[0]?.name || "Orientación",
          derived_at: new Date().toISOString().slice(0, 16).replace("T", " "),
          motive: "Derivación generada desde bitácora.",
        });
        await showConvivenciaSuccess("La bitácora fue convertida correctamente en derivación.");
        this.loadDailyLogs();
        this.loadDerivations();
        this.ensureReferences(["cases"]);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async activateProtocol() {
      try {
        const confirmation = await confirmConvivenciaAction({
          title: "Activar protocolo",
          text: "Se iniciará la trazabilidad formal del protocolo seleccionado.",
          confirmButtonText: "Activar protocolo",
          icon: "question",
        });
        if (!confirmation.isConfirmed) return;

        await axios.post("/api/convivencia/protocol-activations", this.protocolActivationForm);
        await showConvivenciaSuccess("Protocolo activado correctamente.");
        this.protocolActivationForm = { protocol_id: null, case_id: null, complaint_id: null, status: "activo", current_stage_name: "", actions_taken: "", measures_adopted: "" };
        this.loadProtocols();
        this.loadCases();
        this.loadComplaints();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async closeActivation(activation) {
      const confirmation = await confirmConvivenciaAction({ title: "Cerrar activación", text: "Se marcará la activación de protocolo como cerrada.", confirmButtonText: "Cerrar activación" });
      if (!confirmation.isConfirmed) return;
      try {
        await axios.put(`/api/convivencia/protocol-activations/${activation.id}`, {
          status: "cerrado",
          closing_summary: "Cierre realizado desde el módulo.",
          current_stage_name: activation.current_stage_name || "Cierre",
          action_type: "cierre",
          log_notes: "Cierre registrado mediante la interfaz.",
        });
        await showConvivenciaSuccess("Activación de protocolo cerrada correctamente.");
        this.loadProtocols();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async savePeriod() {
      await this.simplePost("/api/convivencia/idps/periods", this.idps.periodForm, "Período IDPS registrado correctamente.", this.loadIdps);
    },
    async saveDimension() {
      await this.simplePost("/api/convivencia/idps/dimensions", this.idps.dimensionForm, "Dimensión IDPS registrada correctamente.", this.loadIdps);
    },
    async saveInstrument() {
      await this.simplePost("/api/convivencia/idps/instruments", this.idps.instrumentForm, "Instrumento IDPS registrado correctamente.", this.loadIdps);
    },
    async saveIdpsResult() {
      await this.simplePost("/api/convivencia/idps/results", this.idps.resultForm, "Resultado IDPS registrado correctamente.", this.loadIdps);
      this.idps.resultForm = { period_id: null, dimension_id: null, instrument_id: null, academic_year_id: this.catalogs.active_academic_year_id, course_section_id: null, education_level_id: null, related_plan_id: null, result_scope: "curso", reference_label: "", score: "", percentage: "", sample_size: "", qualitative_observations: "", improvement_actions: "" };
    },
    async simplePost(url, payload, successText, reload) {
      try {
        await axios.post(url, payload);
        await showConvivenciaSuccess(successText);
        await reload.call(this);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    exportReportExcel() {
      if (!this.reports.data) return;
      downloadExcelWorkbook("reporte-convivencia-curso", [
        {
          title: "Casos",
          headers: ["Folio", "Fecha", "Clasificación", "Criticidad", "Estado"],
          rows: (this.reports.data.lists?.cases || []).map((item) => [item.folio, formatConvivenciaDate(item.opened_at), item.classification_label, item.criticality_label, humanizeConvivenciaStatus(item.status)]),
        },
      ]);
    },
    exportReportPdf() {
      if (!this.reports.data) return;
      downloadPdfReport("reporte-convivencia-curso", "Reporte por curso de convivencia", "Exportación básica del módulo", [
        {
          title: "Casos",
          headers: ["Folio", "Fecha", "Clasificación", "Criticidad", "Estado"],
          rows: (this.reports.data.lists?.cases || []).map((item) => [item.folio, formatConvivenciaDate(item.opened_at), item.classification_label, item.criticality_label, humanizeConvivenciaStatus(item.status)]),
        },
      ]);
    },
    inspectorUserId() {
      return this.dailyLogs.form.inspector_user_id || this.catalogs.users.find((user) => user.name?.toLowerCase().includes("andrea"))?.id || this.catalogs.users[0]?.id || null;
    },
    addAction() { this.plans.form.actions.push(emptyPlanAction()); },
    addPerson() { this.cases.form.people.push(emptyCasePerson()); },
    addStep() { this.protocols.form.steps.push(emptyProtocolStep()); },
    addParticipant() { this.interviews.form.participants.push(emptyInterviewParticipant()); },
    addQuestion() { this.sociograms.form.questions.push(emptyQuestion()); },
    addAnswer() { this.sociograms.form.answers.push(emptyAnswer()); },
    removeArrayItem(collection, index) { collection.splice(index, 1); },
    lineSeries(items, name = "Casos") {
      return [{ name, data: this.dashboard.data?.charts?.monthly_trend?.series || [] }];
    },
    barSeries(items, name = "Total") {
      return [{ name, data: extractChartTotals(items) }];
    },
    donutSeries(items) {
      return extractChartTotals(items);
    },
    formatDate: formatConvivenciaDate,
    formatDateTime: formatConvivenciaDateTime,
    normalizeOptions,
    humanizeConvivenciaStatus,
    extractChartLabels,
    basicApexOptions,
    chartOptions(items, horizontal = false) {
      return basicApexOptions({ categories: extractChartLabels(items), horizontal });
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-column gap-3">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h4 class="mb-1">{{ activeMeta.title }}</h4>
          <div class="text-muted">{{ activeMeta.subtitle }}</div>
        </div>
        <HelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" />
      </div>

      <div class="d-flex flex-wrap gap-2">
        <router-link v-for="tab in visibleTabs" :key="tab.key" :to="tab.route" class="btn" :class="activeTab === tab.key ? 'btn-primary' : 'btn-outline-secondary'">
          {{ tab.label }}
        </router-link>
      </div>

      <BAlert v-if="catalogsError" show variant="danger">{{ catalogsError }}</BAlert>
      <BCard v-if="catalogsLoading" class="border-0 shadow-sm"><LoadingState compact message="Cargando módulo de convivencia..." /></BCard>

      <template v-else>
        <template v-if="activeTab === 'dashboard'">
          <ReportFilterBar v-model="dashboard.filters" :catalogs="catalogs" @submit="loadDashboard" />
          <BCard v-if="dashboard.loading" class="border-0 shadow-sm"><LoadingState compact message="Cargando dashboard..." /></BCard>
          <template v-else-if="dashboard.data">
            <div class="row g-3">
              <div v-for="(value, key) in dashboard.data.metrics" :key="key" class="col-md-3">
                <BCard class="border-0 shadow-sm h-100">
                  <div class="text-muted small">{{ humanizeConvivenciaStatus(key) }}</div>
                  <div class="display-6 fw-semibold">{{ value }}</div>
                </BCard>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-lg-6"><BCard class="border-0 shadow-sm"><h6 class="mb-3">Casos por estado</h6><apexchart type="donut" height="280" :options="{ labels: extractChartLabels(dashboard.data.charts?.cases_by_status) }" :series="donutSeries(dashboard.data.charts?.cases_by_status)" /></BCard></div>
              <div class="col-lg-6"><BCard class="border-0 shadow-sm"><h6 class="mb-3">Tendencia mensual</h6><apexchart type="line" height="280" :options="basicApexOptions({ categories: dashboard.data.charts?.monthly_trend?.labels || [] })" :series="lineSeries()" /></BCard></div>
              <div class="col-lg-6"><BCard class="border-0 shadow-sm"><h6 class="mb-3">Casos por curso</h6><apexchart type="bar" height="280" :options="chartOptions(dashboard.data.charts?.cases_by_course, true)" :series="barSeries(dashboard.data.charts?.cases_by_course)" /></BCard></div>
              <div class="col-lg-6"><BCard class="border-0 shadow-sm"><h6 class="mb-3">Casos por criticidad</h6><apexchart type="bar" height="280" :options="chartOptions(dashboard.data.charts?.cases_by_criticality)" :series="barSeries(dashboard.data.charts?.cases_by_criticality)" /></BCard></div>
            </div>
            <div class="row g-3">
              <div class="col-xl-4">
                <BCard class="border-0 shadow-sm h-100">
                  <h6 class="mb-3">Casos recientes</h6>
                  <div v-for="item in dashboard.data.recent?.cases || []" :key="item.id" class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">{{ item.folio }}</div>
                        <div class="small text-muted">{{ item.classification_label || "-" }}</div>
                      </div>
                      <StatusBadge :status="item.status" />
                    </div>
                  </div>
                </BCard>
              </div>
              <div class="col-xl-4">
                <BCard class="border-0 shadow-sm h-100">
                  <h6 class="mb-3">Denuncias recientes</h6>
                  <div v-for="item in dashboard.data.recent?.complaints || []" :key="item.id" class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">{{ item.folio }}</div>
                        <div class="small text-muted">{{ item.situation_type_label || humanizeConvivenciaStatus(item.complainant_type) }}</div>
                      </div>
                      <StatusBadge :status="item.status" />
                    </div>
                  </div>
                </BCard>
              </div>
              <div class="col-xl-4">
                <BCard class="border-0 shadow-sm h-100">
                  <h6 class="mb-3">Protocolos activos / recientes</h6>
                  <div v-for="item in dashboard.data.recent?.protocols || []" :key="item.id" class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">{{ item.protocol?.name || `#${item.id}` }}</div>
                        <div class="small text-muted">{{ item.current_stage_name || "-" }}</div>
                      </div>
                      <StatusBadge :status="item.status" />
                    </div>
                  </div>
                </BCard>
              </div>
            </div>
          </template>
        </template>

        <template v-else-if="activeTab === 'planes'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end mb-3">
              <div class="col-md-4"><label class="form-label">Año</label><BFormSelect v-model="plans.filters.academic_year_id" :options="normalizeOptions(catalogs.academic_years, true)" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="plans.filters.status" :options="normalizeOptions(catalogs.plan_status_options, true)" /></div>
              <div class="col-md-4 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadPlans">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="plans.showForm = !plans.showForm">{{ plans.showForm ? "Ocultar formulario" : "Nuevo plan" }}</BButton></div>
            </div>
            <div v-if="plans.showForm" class="row g-3">
              <div class="col-md-4"><label class="form-label">Año</label><BFormSelect v-model="plans.form.academic_year_id" :options="normalizeOptions(catalogs.academic_years)" /></div>
              <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="plans.form.responsible_user_id" :options="userOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="plans.form.status" :options="normalizeOptions(catalogs.plan_status_options)" /></div>
              <div class="col-md-6"><label class="form-label">Nombre</label><BFormInput v-model="plans.form.name" /></div>
              <div class="col-md-3"><label class="form-label">Inicio</label><BFormInput v-model="plans.form.starts_on" type="date" /></div>
              <div class="col-md-3"><label class="form-label">Término</label><BFormInput v-model="plans.form.ends_on" type="date" /></div>
              <div class="col-12"><label class="form-label">Objetivo general</label><BFormTextarea v-model="plans.form.general_objective" rows="3" /></div>
              <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="plans.form.observations" rows="2" /></div>
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2"><strong>Acciones</strong><BButton size="sm" variant="outline-primary" @click="addAction">Agregar acción</BButton></div>
                <div v-for="(action, index) in plans.form.actions" :key="index" class="border rounded p-2 mb-2">
                  <div class="row g-2">
                    <div class="col-md-3"><BFormSelect v-model="action.action_type" :options="normalizeOptions(catalogs.plan_action_type_options)" /></div>
                    <div class="col-md-5"><BFormInput v-model="action.title" placeholder="Título de la acción" /></div>
                    <div class="col-md-2"><BFormInput v-model="action.starts_on" type="date" /></div>
                    <div class="col-md-2"><BFormInput v-model="action.ends_on" type="date" /></div>
                    <div class="col-10"><BFormTextarea v-model="action.description" rows="2" placeholder="Descripción" /></div>
                    <div class="col-2 d-flex align-items-end"><BButton size="sm" variant="outline-danger" class="w-100" @click="removeArrayItem(plans.form.actions, index)">Quitar</BButton></div>
                  </div>
                </div>
              </div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="plans.saving" @click="savePlans">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('planes')">Cancelar</BButton></div>
            </div>
          </BCard>
          <BCard class="border-0 shadow-sm"><BTableSimple responsive><thead><tr><th>Plan</th><th>Año</th><th>Estado</th><th>Avance</th><th></th></tr></thead><tbody><tr v-for="item in plans.items" :key="item.id"><td>{{ item.name }}</td><td>{{ item.academic_year?.name || "-" }}</td><td><StatusBadge :status="item.status" /></td><td>{{ item.advance_percentage }}%</td><td class="text-end"><BButton size="sm" variant="outline-primary" @click="editItem('planes', item)">Editar</BButton></td></tr></tbody></BTableSimple></BCard>
        </template>

        <template v-else-if="activeTab === 'casos'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end mb-3">
              <div class="col-md-4"><label class="form-label">Buscar</label><BFormInput v-model="cases.filters.search" placeholder="Folio, relato o estudiante" /></div>
              <div class="col-md-3"><label class="form-label">Curso</label><BFormSelect v-model="cases.filters.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="cases.filters.status" :options="normalizeOptions(catalogs.case_status_options, true)" /></div>
              <div class="col-md-2 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadCases">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="cases.showForm = !cases.showForm">{{ cases.showForm ? "Ocultar" : "Nuevo" }}</BButton></div>
            </div>
            <div v-if="cases.showForm" class="row g-3">
              <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="cases.form.student_profile_id" :options="studentOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="cases.form.course_section_id" :options="courseOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="cases.form.responsible_user_id" :options="userOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Clasificación</label><BFormSelect v-model="cases.form.classification_item_id" :options="classificationOptions" /></div>
              <div class="col-md-4"><label class="form-label">Subclasificación</label><BFormSelect v-model="cases.form.subclassification_item_id" :options="subclassificationOptions" /></div>
              <div class="col-md-4"><label class="form-label">Criticidad</label><BFormSelect v-model="cases.form.criticality_item_id" :options="criticalityOptions" /></div>
              <div class="col-md-3"><label class="form-label">Apertura</label><BFormInput v-model="cases.form.opened_at" type="datetime-local" /></div>
              <div class="col-md-3"><label class="form-label">Fecha del hecho</label><BFormInput v-model="cases.form.happened_at" type="datetime-local" /></div>
              <div class="col-md-3"><label class="form-label">Origen</label><BFormSelect v-model="cases.form.origin" :options="normalizeOptions(catalogs.case_origin_options)" /></div>
              <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="cases.form.status" :options="normalizeOptions(catalogs.case_status_options)" /></div>
              <div class="col-12"><label class="form-label">Relato inicial</label><BFormTextarea v-model="cases.form.initial_report" rows="3" /></div>
              <div class="col-12"><label class="form-label">Antecedentes</label><BFormTextarea v-model="cases.form.background" rows="2" /></div>
              <div class="col-12"><div class="d-flex justify-content-between align-items-center mb-2"><strong>Personas involucradas</strong><BButton size="sm" variant="outline-primary" @click="addPerson">Agregar</BButton></div><div v-for="(person, index) in cases.form.people" :key="index" class="row g-2 border rounded p-2 mb-2"><div class="col-md-3"><BFormSelect v-model="person.person_type" :options="normalizeOptions(catalogs.person_type_options)" /></div><div class="col-md-3"><BFormSelect v-model="person.role_type" :options="normalizeOptions(catalogs.person_role_options)" /></div><div class="col-md-4"><BFormInput v-model="person.full_name" placeholder="Nombre completo" /></div><div class="col-md-2"><BButton size="sm" variant="outline-danger" class="w-100" @click="removeArrayItem(cases.form.people, index)">Quitar</BButton></div></div></div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="cases.saving" @click="saveCases">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('casos')">Cancelar</BButton></div>
            </div>
          </BCard>
          <div class="d-flex flex-column gap-3">
            <CaseSummaryCard v-for="item in cases.items" :key="item.id" :item="item" />
            <BCard v-for="item in cases.items" :key="`row-${item.id}`" class="border-0 shadow-sm">
              <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">{{ item.student?.registered_name_resolved || item.student?.registered_name || "-" }}</div>
                <div class="d-flex gap-2"><BButton size="sm" variant="outline-secondary" @click="openCaseDetail(item)">Detalle</BButton><BButton size="sm" variant="outline-primary" @click="editItem('casos', item)">Editar</BButton><BButton size="sm" variant="outline-success" @click="closeCase(item)">Cerrar</BButton><BButton size="sm" variant="outline-danger" @click="deleteItem('/api/convivencia/cases', item.id, loadCases, 'Se archivará el caso seleccionado.')">Archivar</BButton></div>
              </div>
            </BCard>
          </div>
        </template>

        <template v-else-if="activeTab === 'denuncias'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end mb-3">
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="complaints.filters.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="complaints.filters.status" :options="normalizeOptions(catalogs.complaint_status_options, true)" /></div>
              <div class="col-md-4 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadComplaints">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="complaints.showForm = !complaints.showForm">{{ complaints.showForm ? "Ocultar formulario" : "Nueva denuncia" }}</BButton></div>
            </div>
            <div v-if="complaints.showForm" class="row g-3">
              <div class="col-md-4"><label class="form-label">Estudiante afectado</label><BFormSelect v-model="complaints.form.affected_student_id" :options="studentOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="complaints.form.course_section_id" :options="courseOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Tipo denunciante</label><BFormSelect v-model="complaints.form.complainant_type" :options="normalizeOptions(catalogs.complaint_type_options)" /></div>
              <div class="col-md-6"><label class="form-label">Nombre denunciante</label><BFormInput v-model="complaints.form.complainant_name" /></div>
              <div class="col-md-3"><label class="form-label">Correo</label><BFormInput v-model="complaints.form.contact_email" /></div>
              <div class="col-md-3"><label class="form-label">Teléfono</label><BFormInput v-model="complaints.form.contact_phone" /></div>
              <div class="col-md-4"><label class="form-label">Tipo de situación</label><BFormSelect v-model="complaints.form.situation_type_item_id" :options="catalogOptions('situation_type')" /></div>
              <div class="col-md-4"><label class="form-label">Lugar</label><BFormInput v-model="complaints.form.place" /></div>
              <div class="col-md-4"><label class="form-label">Fecha del hecho</label><BFormInput v-model="complaints.form.happened_at" type="datetime-local" /></div>
              <div class="col-12"><label class="form-label">Relato</label><BFormTextarea v-model="complaints.form.report_text" rows="3" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="complaints.form.status" :options="normalizeOptions(catalogs.complaint_status_options)" /></div>
              <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="complaints.form.truth_declaration_accepted">Declara veracidad</BFormCheckbox></div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="complaints.saving" @click="saveComplaints">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('denuncias')">Cancelar</BButton></div>
            </div>
          </BCard>
          <BCard class="border-0 shadow-sm"><BTableSimple responsive><thead><tr><th>Folio</th><th>Tipo</th><th>Estudiante</th><th>Estado</th><th></th></tr></thead><tbody><tr v-for="item in complaints.items" :key="item.id"><td>{{ item.folio }}</td><td>{{ humanizeConvivenciaStatus(item.complainant_type) }}</td><td>{{ item.affected_student?.registered_name_resolved || "-" }}</td><td><StatusBadge :status="item.status" /></td><td class="text-end d-flex gap-2 justify-content-end"><BButton size="sm" variant="outline-primary" @click="editItem('denuncias', item)">Editar</BButton><BButton size="sm" variant="outline-success" @click="convertComplaint(item)">Convertir</BButton><BButton size="sm" variant="outline-danger" @click="deleteItem('/api/convivencia/complaints', item.id, loadComplaints, 'Se archivará la denuncia seleccionada.')">Archivar</BButton></td></tr></tbody></BTableSimple></BCard>
        </template>

        <template v-else-if="activeTab === 'derivaciones'">
          <BCard class="border-0 shadow-sm mb-3"><div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Derivaciones</h6><BButton size="sm" variant="primary" @click="derivations.showForm = !derivations.showForm">{{ derivations.showForm ? "Ocultar formulario" : "Nueva derivación" }}</BButton></div><div v-if="derivations.showForm" class="row g-3"><div class="col-md-4"><label class="form-label">Caso asociado</label><BFormSelect v-model="derivations.form.case_id" :options="[{ value: null, text: 'Sin caso' }].concat(caseReferenceOptions)" /></div><div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="derivations.form.student_profile_id" :options="studentOptions(true)" /></div><div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="derivations.form.course_section_id" :options="courseOptions(true)" /></div><div class="col-md-3"><label class="form-label">Alcance</label><BFormSelect v-model="derivations.form.scope" :options="normalizeOptions(catalogs.derivation_scope_options)" /></div><div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="derivations.form.status" :options="normalizeOptions(catalogs.derivation_status_options)" /></div><div class="col-md-3"><label class="form-label">Prioridad</label><BFormSelect v-model="derivations.form.priority_level" :options="normalizeOptions(catalogs.derivation_priority_options)" /></div><div class="col-md-3"><label class="form-label">Fecha</label><BFormInput v-model="derivations.form.derived_at" type="datetime-local" /></div><div class="col-md-6"><label class="form-label">Departamento destino</label><BFormSelect v-model="derivations.form.destination_department_id" :options="departmentOptions(true)" /></div><div class="col-md-6"><label class="form-label">Institución externa</label><BFormSelect v-model="derivations.form.external_institution_id" :options="institutionOptions(true)" /></div><div class="col-12"><label class="form-label">Motivo</label><BFormTextarea v-model="derivations.form.motive" rows="2" /></div><div class="col-12"><label class="form-label">Narrativa</label><BFormTextarea v-model="derivations.form.narrative" rows="2" /></div><div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="derivations.saving" @click="saveDerivations">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('derivaciones')">Cancelar</BButton></div></div></BCard>
          <BCard class="border-0 shadow-sm"><BTableSimple responsive><thead><tr><th>Ámbito</th><th>Destino</th><th>Estado</th><th>Prioridad</th><th></th></tr></thead><tbody><tr v-for="item in derivations.items" :key="item.id"><td>{{ item.scope === 'internal' ? 'Interna' : 'Externa' }}</td><td>{{ item.destination_label || item.destination_department?.name || item.external_institution?.name || "-" }}</td><td><StatusBadge :status="item.status" /></td><td><CriticalityBadge :value="item.priority_level" /></td><td class="text-end"><BButton size="sm" variant="outline-primary" @click="editItem('derivaciones', item)">Editar</BButton></td></tr></tbody></BTableSimple></BCard>
        </template>

        <template v-else-if="activeTab === 'protocolos'">
          <div class="row g-3">
            <div class="col-lg-7">
              <BCard class="border-0 shadow-sm">
                <div class="row g-3 align-items-end mb-3">
                  <div class="col-md-5"><label class="form-label">Buscar</label><BFormInput v-model="protocols.filters.search" placeholder="Nombre del protocolo" /></div>
                  <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="protocols.filters.status" :options="normalizeOptions(catalogs.protocol_status_options, true)" /></div>
                  <div class="col-md-3 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadProtocols">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="protocols.showForm = !protocols.showForm">{{ protocols.showForm ? "Ocultar formulario" : "Nuevo protocolo" }}</BButton></div>
                </div>
                <div v-if="protocols.showForm" class="row g-3">
                  <div class="col-md-6"><label class="form-label">Tipo</label><BFormSelect v-model="protocols.form.protocol_type_item_id" :options="catalogOptions('protocol_type')" /></div>
                  <div class="col-md-6"><label class="form-label">Criticidad</label><BFormSelect v-model="protocols.form.criticality_item_id" :options="criticalityOptions" /></div>
                  <div class="col-md-8"><label class="form-label">Nombre</label><BFormInput v-model="protocols.form.name" /></div>
                  <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="protocols.form.status" :options="normalizeOptions(catalogs.protocol_status_options)" /></div>
                  <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="protocols.form.description" rows="2" /></div>
                  <div class="col-12"><div class="d-flex justify-content-between align-items-center mb-2"><strong>Etapas</strong><BButton size="sm" variant="outline-primary" @click="addStep">Agregar etapa</BButton></div><div v-for="(step, index) in protocols.form.steps" :key="index" class="row g-2 border rounded p-2 mb-2"><div class="col-md-6"><BFormInput v-model="step.stage_name" placeholder="Nombre de la etapa" /></div><div class="col-md-4"><BFormInput v-model="step.responsible_label" placeholder="Responsable" /></div><div class="col-md-2"><BFormInput v-model="step.due_days" type="number" min="1" /></div></div></div>
                  <div class="col-12 d-flex gap-2"><BButton variant="success" @click="saveProtocols">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('protocolos')">Cancelar</BButton></div>
                </div>
                <BTableSimple responsive><thead><tr><th>Nombre</th><th>Criticidad</th><th>Estado</th><th></th></tr></thead><tbody><tr v-for="item in protocols.items" :key="item.id"><td>{{ item.name }}</td><td><CriticalityBadge :value="item.criticality_label" /></td><td><StatusBadge :status="item.status" /></td><td class="text-end"><BButton size="sm" variant="outline-primary" @click="editItem('protocolos', item)">Editar</BButton></td></tr></tbody></BTableSimple>
              </BCard>
            </div>
            <div class="col-lg-5">
              <BCard class="border-0 shadow-sm">
                <h6 class="mb-3">Activar protocolo</h6>
                <div class="row g-3">
                  <div class="col-12"><label class="form-label">Protocolo</label><BFormSelect v-model="protocolActivationForm.protocol_id" :options="protocolReferenceOptions" /></div>
                  <div class="col-12"><label class="form-label">Caso</label><BFormSelect v-model="protocolActivationForm.case_id" :options="[{ value: null, text: 'Sin caso' }].concat(caseReferenceOptions)" /></div>
                  <div class="col-12"><label class="form-label">Denuncia</label><BFormSelect v-model="protocolActivationForm.complaint_id" :options="[{ value: null, text: 'Sin denuncia' }].concat(complaintReferenceOptions)" /></div>
                  <div class="col-12"><label class="form-label">Acciones iniciales</label><BFormTextarea v-model="protocolActivationForm.actions_taken" rows="2" /></div>
                  <div class="col-12"><BButton variant="danger" class="w-100" @click="activateProtocol">Activar protocolo</BButton></div>
                </div>
                <hr />
                <h6 class="mb-3">Activaciones recientes</h6>
                <div v-for="activation in protocols.activations || []" :key="activation.id" class="border rounded p-2 mb-2">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <div class="fw-semibold">{{ activation.protocol?.name }}</div>
                      <div class="small text-muted">{{ activation.current_stage_name || "-" }}</div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      <StatusBadge :status="activation.status" />
                      <BButton v-if="activation.status !== 'cerrado'" size="sm" variant="outline-success" @click="closeActivation(activation)">Cerrar</BButton>
                    </div>
                  </div>
                </div>
              </BCard>
            </div>
          </div>
        </template>

        <template v-else-if="activeTab === 'entrevistas'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end">
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="interviews.filters.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Seguimiento</label><BFormSelect v-model="interviews.filters.follow_up_status" :options="normalizeOptions(catalogs.interview_follow_up_status_options, true)" /></div>
              <div class="col-md-4 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadInterviews">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="interviews.showForm = !interviews.showForm">{{ interviews.showForm ? "Ocultar formulario" : "Nueva entrevista" }}</BButton></div>
            </div>
            <div v-if="interviews.showForm" class="row g-3 mt-1">
              <div class="col-md-4"><label class="form-label">Caso asociado</label><BFormSelect v-model="interviews.form.case_id" :options="[{ value: null, text: 'Sin caso' }].concat(caseReferenceOptions)" /></div>
              <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="interviews.form.student_profile_id" :options="studentOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="interviews.form.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Tipo de entrevista</label><BFormSelect v-model="interviews.form.interview_type_item_id" :options="catalogOptions('interview_type')" /></div>
              <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="interviews.form.responsible_user_id" :options="userOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="interviews.form.interview_at" type="datetime-local" /></div>
              <div class="col-md-6"><label class="form-label">Motivo</label><BFormTextarea v-model="interviews.form.motive" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Temas tratados</label><BFormTextarea v-model="interviews.form.topics" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Acuerdos</label><BFormTextarea v-model="interviews.form.agreements" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Compromisos</label><BFormTextarea v-model="interviews.form.commitments" rows="2" /></div>
              <div class="col-md-4"><label class="form-label">Fecha seguimiento</label><BFormInput v-model="interviews.form.follow_up_date" type="date" /></div>
              <div class="col-md-4"><label class="form-label">Estado seguimiento</label><BFormSelect v-model="interviews.form.follow_up_status" :options="normalizeOptions(catalogs.interview_follow_up_status_options)" /></div>
              <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="interviews.form.is_sensitive">Información sensible</BFormCheckbox></div>
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2"><strong>Participantes</strong><BButton size="sm" variant="outline-primary" @click="addParticipant">Agregar participante</BButton></div>
                <div v-for="(participant, index) in interviews.form.participants" :key="index" class="row g-2 border rounded p-2 mb-2">
                  <div class="col-md-3"><BFormSelect v-model="participant.participant_type" :options="[{ value: 'estudiante', text: 'Estudiante' }, { value: 'apoderado', text: 'Apoderado' }, { value: 'funcionario', text: 'Funcionario' }, { value: 'grupo_estudiantes', text: 'Grupo de estudiantes' }, { value: 'otro', text: 'Otro' }]" /></div>
                  <div class="col-md-3"><BFormInput v-model="participant.participant_role" placeholder="Rol" /></div>
                  <div class="col-md-4"><BFormInput v-model="participant.full_name" placeholder="Nombre completo" /></div>
                  <div class="col-md-2"><BButton size="sm" variant="outline-danger" class="w-100" @click="removeArrayItem(interviews.form.participants, index)">Quitar</BButton></div>
                </div>
              </div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="interviews.saving" @click="saveInterviews">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('entrevistas')">Cancelar</BButton></div>
            </div>
          </BCard>
          <BCard class="border-0 shadow-sm">
            <BTableSimple responsive>
              <thead><tr><th>Fecha</th><th>Tipo</th><th>Motivo</th><th>Participantes</th><th>Seguimiento</th><th></th></tr></thead>
              <tbody>
                <tr v-for="item in interviews.items" :key="item.id">
                  <td>{{ formatDateTime(item.interview_at) }}</td>
                  <td>{{ item.interview_type_label || item.type?.name || "-" }}</td>
                  <td>{{ item.motive }}</td>
                  <td>{{ item.participants_count || (item.participants?.length || 0) }}</td>
                  <td><StatusBadge :status="item.follow_up_status" /></td>
                  <td class="text-end d-flex gap-2 justify-content-end"><BButton size="sm" variant="outline-primary" @click="editItem('entrevistas', item)">Editar</BButton><BButton size="sm" variant="outline-danger" @click="deleteItem('/api/convivencia/interviews', item.id, loadInterviews, 'Se archivará la entrevista seleccionada.')">Archivar</BButton></td>
                </tr>
              </tbody>
            </BTableSimple>
          </BCard>
        </template>

        <template v-else-if="activeTab === 'medidas'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end">
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="measures.filters.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="measures.filters.status" :options="normalizeOptions(catalogs.measure_status_options, true)" /></div>
              <div class="col-md-4 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadMeasures">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="measures.showForm = !measures.showForm">{{ measures.showForm ? "Ocultar formulario" : "Nueva medida" }}</BButton></div>
            </div>
            <div v-if="measures.showForm" class="row g-3 mt-1">
              <div class="col-md-4"><label class="form-label">Caso asociado</label><BFormSelect v-model="measures.form.case_id" :options="[{ value: null, text: 'Sin caso' }].concat(caseReferenceOptions)" /></div>
              <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="measures.form.student_profile_id" :options="studentOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="measures.form.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Tipo de medida</label><BFormSelect v-model="measures.form.measure_type_item_id" :options="catalogOptions('measure_type')" /></div>
              <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="measures.form.responsible_user_id" :options="userOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="measures.form.status" :options="normalizeOptions(catalogs.measure_status_options)" /></div>
              <div class="col-md-3"><label class="form-label">Asignación</label><BFormInput v-model="measures.form.assigned_at" type="datetime-local" /></div>
              <div class="col-md-3"><label class="form-label">Cumplimiento esperado</label><BFormInput v-model="measures.form.due_at" type="datetime-local" /></div>
              <div class="col-md-6 d-flex align-items-end"><BFormCheckbox v-model="measures.form.is_sensitive">Información sensible</BFormCheckbox></div>
              <div class="col-md-6"><label class="form-label">Descripción</label><BFormTextarea v-model="measures.form.description" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Objetivo formativo</label><BFormTextarea v-model="measures.form.training_objective" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Acción de reparación</label><BFormTextarea v-model="measures.form.repair_action" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Observaciones del responsable</label><BFormTextarea v-model="measures.form.responsible_notes" rows="2" /></div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="measures.saving" @click="saveMeasures">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('medidas')">Cancelar</BButton></div>
            </div>
          </BCard>
          <BCard class="border-0 shadow-sm">
            <BTableSimple responsive>
              <thead><tr><th>Tipo</th><th>Estudiante</th><th>Estado</th><th>Vence</th><th></th></tr></thead>
              <tbody>
                <tr v-for="item in measures.items" :key="item.id">
                  <td>{{ item.measure_type_label || item.type?.name || "-" }}</td>
                  <td>{{ item.student?.registered_name_resolved || item.student?.registered_name || "-" }}</td>
                  <td><StatusBadge :status="item.status" /></td>
                  <td>{{ formatDateTime(item.due_at) }}</td>
                  <td class="text-end d-flex gap-2 justify-content-end"><BButton size="sm" variant="outline-primary" @click="editItem('medidas', item)">Editar</BButton><BButton size="sm" variant="outline-danger" @click="deleteItem('/api/convivencia/measures', item.id, loadMeasures, 'Se archivará la medida formativa seleccionada.')">Archivar</BButton></td>
                </tr>
              </tbody>
            </BTableSimple>
          </BCard>
        </template>

        <template v-else-if="activeTab === 'bitacora'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end">
              <div class="col-md-3"><label class="form-label">Curso</label><BFormSelect v-model="dailyLogs.filters.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-3"><label class="form-label">Tipo de hecho</label><BFormSelect v-model="dailyLogs.filters.daily_log_type_item_id" :options="[{ value: null, text: 'Todos' }].concat(catalogOptions('daily_log_type'))" /></div>
              <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="dailyLogs.filters.status" :options="normalizeOptions(catalogs.daily_log_status_options, true)" /></div>
              <div class="col-md-3 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadDailyLogs">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="dailyLogs.showForm = !dailyLogs.showForm">{{ dailyLogs.showForm ? "Ocultar formulario" : "Nuevo hecho" }}</BButton></div>
            </div>
            <div v-if="dailyLogs.showForm" class="row g-3 mt-1">
              <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="dailyLogs.form.student_profile_id" :options="studentOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="dailyLogs.form.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Inspectora / responsable</label><BFormSelect v-model="dailyLogs.form.inspector_user_id" :options="userOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Tipo de hecho</label><BFormSelect v-model="dailyLogs.form.daily_log_type_item_id" :options="catalogOptions('daily_log_type')" /></div>
              <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="dailyLogs.form.happened_at" type="datetime-local" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="dailyLogs.form.status" :options="normalizeOptions(catalogs.daily_log_status_options)" /></div>
              <div class="col-md-6"><label class="form-label">Lugar</label><BFormInput v-model="dailyLogs.form.place" /></div>
              <div class="col-md-6 d-flex align-items-end"><BFormCheckbox v-model="dailyLogs.form.is_sensitive">Información sensible</BFormCheckbox></div>
              <div class="col-md-6"><label class="form-label">Descripción</label><BFormTextarea v-model="dailyLogs.form.description" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Acción inmediata</label><BFormTextarea v-model="dailyLogs.form.immediate_action" rows="2" /></div>
              <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="dailyLogs.form.guardian_informed">Apoderado informado</BFormCheckbox></div>
              <div class="col-md-8"><label class="form-label">Observación de contacto</label><BFormInput v-model="dailyLogs.form.guardian_contact_note" /></div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="dailyLogs.saving" @click="saveDailyLogs">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('bitacora')">Cancelar</BButton></div>
            </div>
          </BCard>
          <BCard class="border-0 shadow-sm">
            <BTableSimple responsive>
              <thead><tr><th>Fecha</th><th>Tipo</th><th>Estudiante</th><th>Estado</th><th></th></tr></thead>
              <tbody>
                <tr v-for="item in dailyLogs.items" :key="item.id">
                  <td>{{ formatDateTime(item.happened_at) }}</td>
                  <td>{{ item.daily_log_type_label || item.type?.name || "-" }}</td>
                  <td>{{ item.student?.registered_name_resolved || item.student?.registered_name || "-" }}</td>
                  <td><StatusBadge :status="item.status" /></td>
                  <td class="text-end d-flex gap-2 justify-content-end"><BButton size="sm" variant="outline-primary" @click="editItem('bitacora', item)">Editar</BButton><BButton size="sm" variant="outline-success" @click="convertDailyLogToCase(item)">Pasar a caso</BButton><BButton size="sm" variant="outline-warning" @click="convertDailyLogToDerivation(item)">Derivar</BButton><BButton size="sm" variant="outline-danger" @click="deleteItem('/api/convivencia/daily-logs', item.id, loadDailyLogs, 'Se archivará el registro diario seleccionado.')">Archivar</BButton></td>
                </tr>
              </tbody>
            </BTableSimple>
          </BCard>
        </template>

        <template v-else-if="activeTab === 'sociogramas'">
          <BCard class="border-0 shadow-sm mb-3">
            <div class="row g-3 align-items-end">
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="sociograms.filters.course_section_id" :options="courseOptions(true)" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="sociograms.filters.status" :options="normalizeOptions(catalogs.sociogram_status_options, true)" /></div>
              <div class="col-md-4 d-flex gap-2"><BButton variant="primary" class="w-100" @click="loadSociograms">Filtrar</BButton><BButton size="sm" variant="outline-primary" @click="sociograms.showForm = !sociograms.showForm">{{ sociograms.showForm ? "Ocultar formulario" : "Nuevo sociograma" }}</BButton></div>
            </div>
            <div v-if="sociograms.showForm" class="row g-3 mt-1">
              <div class="col-md-4"><label class="form-label">Año</label><BFormSelect v-model="sociograms.form.academic_year_id" :options="normalizeOptions(catalogs.academic_years, false)" /></div>
              <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="sociograms.form.course_section_id" :options="courseOptions(false)" /></div>
              <div class="col-md-4"><label class="form-label">Aplicado el</label><BFormInput v-model="sociograms.form.applied_on" type="date" /></div>
              <div class="col-md-6"><label class="form-label">Título</label><BFormInput v-model="sociograms.form.title" /></div>
              <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="sociograms.form.status" :options="normalizeOptions(catalogs.sociogram_status_options)" /></div>
              <div class="col-md-3"><label class="form-label">Confidencialidad</label><BFormSelect v-model="sociograms.form.confidentiality_level" :options="[{ value: 'alta_confidencialidad', text: 'Alta confidencialidad' }, { value: 'reservada', text: 'Reservada' }, { value: 'interna', text: 'Interna' }]" /></div>
              <div class="col-12"><label class="form-label">Interpretación profesional</label><BFormTextarea v-model="sociograms.form.interpretation" rows="2" /></div>
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2"><strong>Preguntas sociométricas</strong><BButton size="sm" variant="outline-primary" @click="addQuestion">Agregar pregunta</BButton></div>
                <div v-for="(question, index) in sociograms.form.questions" :key="index" class="row g-2 border rounded p-2 mb-2">
                  <div class="col-md-6"><BFormInput v-model="question.prompt" placeholder="Pregunta" /></div>
                  <div class="col-md-3"><BFormSelect v-model="question.selection_type" :options="[{ value: 'positiva', text: 'Positiva' }, { value: 'negativa', text: 'Negativa' }, { value: 'neutra', text: 'Neutra' }]" /></div>
                  <div class="col-md-2"><BFormInput v-model="question.max_choices" type="number" min="1" max="10" /></div>
                  <div class="col-md-1"><BButton size="sm" variant="outline-danger" class="w-100" @click="removeArrayItem(sociograms.form.questions, index)">X</BButton></div>
                </div>
              </div>
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2"><strong>Respuestas registradas</strong><BButton size="sm" variant="outline-primary" @click="addAnswer">Agregar respuesta</BButton></div>
                <div v-for="(answer, index) in sociograms.form.answers" :key="index" class="row g-2 border rounded p-2 mb-2">
                  <div class="col-md-2"><BFormInput v-model="answer.question_order" type="number" min="1" /></div>
                  <div class="col-md-3"><BFormSelect v-model="answer.respondent_student_id" :options="studentOptions(true)" /></div>
                  <div class="col-md-3"><BFormSelect v-model="answer.selected_student_id" :options="studentOptions(true)" /></div>
                  <div class="col-md-3"><BFormSelect v-model="answer.selection_type" :options="[{ value: 'positiva', text: 'Positiva' }, { value: 'negativa', text: 'Negativa' }, { value: 'neutra', text: 'Neutra' }]" /></div>
                  <div class="col-md-1"><BButton size="sm" variant="outline-danger" class="w-100" @click="removeArrayItem(sociograms.form.answers, index)">X</BButton></div>
                </div>
              </div>
              <div class="col-12 d-flex gap-2"><BButton variant="success" :disabled="sociograms.saving" @click="saveSociograms">Guardar</BButton><BButton variant="outline-secondary" @click="resetForm('sociogramas')">Cancelar</BButton></div>
            </div>
          </BCard>
          <BCard class="border-0 shadow-sm">
            <BTableSimple responsive>
              <thead><tr><th>Título</th><th>Curso</th><th>Aplicación</th><th>Preguntas</th><th>Estado</th><th></th></tr></thead>
              <tbody>
                <tr v-for="item in sociograms.items" :key="item.id">
                  <td>{{ item.title }}</td>
                  <td>{{ item.courseSection?.display_name || item.course_section?.display_name || "-" }}</td>
                  <td>{{ formatDate(item.applied_on) }}</td>
                  <td>{{ item.questions_count }} / {{ item.answers_count }}</td>
                  <td><StatusBadge :status="item.status" /></td>
                  <td class="text-end d-flex gap-2 justify-content-end"><BButton size="sm" variant="outline-primary" @click="editItem('sociogramas', item)">Editar</BButton><BButton size="sm" variant="outline-danger" @click="deleteItem('/api/convivencia/sociograms', item.id, loadSociograms, 'Se archivará el sociograma seleccionado.')">Archivar</BButton></td>
                </tr>
              </tbody>
            </BTableSimple>
          </BCard>
        </template>

        <template v-else-if="activeTab === 'idps'">
          <div class="row g-3">
            <div class="col-xl-4">
              <BCard class="border-0 shadow-sm h-100">
                <h6 class="mb-3">Período de medición</h6>
                <div class="row g-3">
                  <div class="col-12"><label class="form-label">Año</label><BFormSelect v-model="idps.periodForm.academic_year_id" :options="normalizeOptions(catalogs.academic_years, false)" /></div>
                  <div class="col-12"><label class="form-label">Nombre</label><BFormInput v-model="idps.periodForm.name" /></div>
                  <div class="col-md-6"><label class="form-label">Inicio</label><BFormInput v-model="idps.periodForm.starts_on" type="date" /></div>
                  <div class="col-md-6"><label class="form-label">Término</label><BFormInput v-model="idps.periodForm.ends_on" type="date" /></div>
                  <div class="col-md-6"><label class="form-label">Estado</label><BFormInput v-model="idps.periodForm.status" /></div>
                  <div class="col-12"><label class="form-label">Notas</label><BFormTextarea v-model="idps.periodForm.notes" rows="2" /></div>
                  <div class="col-12"><BButton variant="primary" class="w-100" @click="savePeriod">Guardar período</BButton></div>
                </div>
              </BCard>
            </div>
            <div class="col-xl-4">
              <BCard class="border-0 shadow-sm h-100">
                <h6 class="mb-3">Dimensión IDPS</h6>
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label">Código</label><BFormInput v-model="idps.dimensionForm.code" /></div>
                  <div class="col-md-8"><label class="form-label">Nombre</label><BFormInput v-model="idps.dimensionForm.name" /></div>
                  <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="idps.dimensionForm.description" rows="2" /></div>
                  <div class="col-12 d-flex align-items-end"><BFormCheckbox v-model="idps.dimensionForm.active">Activa</BFormCheckbox></div>
                  <div class="col-12"><BButton variant="primary" class="w-100" @click="saveDimension">Guardar dimensión</BButton></div>
                </div>
              </BCard>
            </div>
            <div class="col-xl-4">
              <BCard class="border-0 shadow-sm h-100">
                <h6 class="mb-3">Instrumento</h6>
                <div class="row g-3">
                  <div class="col-12"><label class="form-label">Dimensión</label><BFormSelect v-model="idps.instrumentForm.dimension_id" :options="idpsDimensions.map((item) => ({ value: item.id, text: item.name }))" /></div>
                  <div class="col-12"><label class="form-label">Nombre</label><BFormInput v-model="idps.instrumentForm.name" /></div>
                  <div class="col-md-6"><label class="form-label">Tipo de respuesta</label><BFormSelect v-model="idps.instrumentForm.response_type" :options="[{ value: 'escala', text: 'Escala' }, { value: 'abierta', text: 'Abierta' }, { value: 'si_no', text: 'Sí / No' }]" /></div>
                  <div class="col-md-6"><label class="form-label">Etiqueta escala</label><BFormInput v-model="idps.instrumentForm.scale_label" /></div>
                  <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="idps.instrumentForm.description" rows="2" /></div>
                  <div class="col-12"><BButton variant="primary" class="w-100" @click="saveInstrument">Guardar instrumento</BButton></div>
                </div>
              </BCard>
            </div>
            <div class="col-12">
              <BCard class="border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Registrar resultado IDPS</h6><BButton size="sm" variant="outline-primary" @click="loadIdps">Actualizar overview</BButton></div>
                <div class="row g-3">
                  <div class="col-md-3"><label class="form-label">Período</label><BFormSelect v-model="idps.resultForm.period_id" :options="idpsPeriods.map((item) => ({ value: item.id, text: item.name }))" /></div>
                  <div class="col-md-3"><label class="form-label">Dimensión</label><BFormSelect v-model="idps.resultForm.dimension_id" :options="idpsDimensions.map((item) => ({ value: item.id, text: item.name }))" /></div>
                  <div class="col-md-3"><label class="form-label">Instrumento</label><BFormSelect v-model="idps.resultForm.instrument_id" :options="idpsDimensions.flatMap((item) => item.instruments || []).map((instrument) => ({ value: instrument.id, text: instrument.name }))" /></div>
                  <div class="col-md-3"><label class="form-label">Alcance</label><BFormSelect v-model="idps.resultForm.result_scope" :options="normalizeOptions(catalogs.idps_scope_options)" /></div>
                  <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="idps.resultForm.course_section_id" :options="courseOptions(true)" /></div>
                  <div class="col-md-4"><label class="form-label">Plan vinculado</label><BFormSelect v-model="idps.resultForm.related_plan_id" :options="[{ value: null, text: 'Sin plan' }].concat(planReferenceOptions)" /></div>
                  <div class="col-md-4"><label class="form-label">Referencia</label><BFormInput v-model="idps.resultForm.reference_label" /></div>
                  <div class="col-md-3"><label class="form-label">Puntaje</label><BFormInput v-model="idps.resultForm.score" type="number" step="0.01" /></div>
                  <div class="col-md-3"><label class="form-label">Porcentaje</label><BFormInput v-model="idps.resultForm.percentage" type="number" step="0.01" min="0" max="100" /></div>
                  <div class="col-md-3"><label class="form-label">Muestra</label><BFormInput v-model="idps.resultForm.sample_size" type="number" min="0" /></div>
                  <div class="col-md-3 d-flex align-items-end"><BFormCheckbox v-model="idps.resultForm.is_sensitive">Resultado sensible</BFormCheckbox></div>
                  <div class="col-md-6"><label class="form-label">Observaciones cualitativas</label><BFormTextarea v-model="idps.resultForm.qualitative_observations" rows="2" /></div>
                  <div class="col-md-6"><label class="form-label">Acciones de mejora</label><BFormTextarea v-model="idps.resultForm.improvement_actions" rows="2" /></div>
                  <div class="col-12"><BButton variant="success" @click="saveIdpsResult">Guardar resultado</BButton></div>
                </div>
              </BCard>
            </div>
            <div class="col-12">
              <BCard class="border-0 shadow-sm">
                <h6 class="mb-3">Resultados registrados</h6>
                <div class="row g-3 mb-3">
                  <div class="col-md-4"><BCard class="border bg-light-subtle"><div class="text-muted small">Períodos</div><div class="h3 mb-0">{{ idpsPeriods.length }}</div></BCard></div>
                  <div class="col-md-4"><BCard class="border bg-light-subtle"><div class="text-muted small">Dimensiones</div><div class="h3 mb-0">{{ idpsDimensions.length }}</div></BCard></div>
                  <div class="col-md-4"><BCard class="border bg-light-subtle"><div class="text-muted small">Resultados</div><div class="h3 mb-0">{{ idps.overview?.results?.total || idpsResults.length }}</div></BCard></div>
                </div>
                <BTableSimple responsive>
                  <thead><tr><th>Período</th><th>Dimensión</th><th>Curso / referencia</th><th>Puntaje</th><th>%</th><th>Plan</th></tr></thead>
                  <tbody>
                    <tr v-for="item in idpsResults" :key="item.id">
                      <td>{{ item.period?.name || "-" }}</td>
                      <td>{{ item.dimension?.name || "-" }}</td>
                      <td>{{ item.reference_label || item.course_section?.display_name || "-" }}</td>
                      <td>{{ item.score ?? "-" }}</td>
                      <td>{{ item.percentage ?? "-" }}</td>
                      <td>{{ item.related_plan?.name || "-" }}</td>
                    </tr>
                  </tbody>
                </BTableSimple>
              </BCard>
            </div>
          </div>
        </template>

        <template v-else-if="activeTab === 'reportes'">
          <ReportFilterBar v-model="reports.filters" :catalogs="catalogs" :show-semester="true" @submit="loadReports" />
          <BCard v-if="reports.loading" class="border-0 shadow-sm"><LoadingState compact message="Cargando reporte por curso..." /></BCard>
          <template v-else-if="reports.data">
            <div class="row g-3">
              <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Clima de convivencia</div><div class="h3 mb-0">{{ reports.data.summary?.climate?.percentage ?? "-" }}</div></BCard></div>
              <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Casos abiertos</div><div class="h3 mb-0">{{ reports.data.summary?.open_cases ?? 0 }}</div></BCard></div>
              <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Conflictos registrados</div><div class="h3 mb-0">{{ reports.data.summary?.conflicts_registered ?? 0 }}</div></BCard></div>
              <div class="col-md-3"><BCard class="border-0 shadow-sm"><div class="text-muted small">Atrasos</div><div class="h3 mb-0">{{ reports.data.summary?.tardiness ?? 0 }}</div></BCard></div>
            </div>
            <BCard class="border-0 shadow-sm mt-3">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                  <h6 class="mb-1">Exportación y alertas</h6>
                  <div class="small text-muted">{{ reports.data.summary?.attendance_note }}</div>
                </div>
                <div class="d-flex gap-2"><BButton variant="primary" @click="loadReports">Actualizar reporte</BButton><BButton variant="outline-success" @click="exportReportExcel">Exportar Excel</BButton><BButton variant="outline-danger" @click="exportReportPdf">Exportar PDF</BButton></div>
              </div>
              <div class="row g-3">
                <div class="col-md-6"><BCard class="border bg-light-subtle"><div class="text-muted small">Alertas</div><div class="small">Medidas vencidas: {{ reports.data.summary?.alerts?.overdue_measures ?? 0 }}</div><div class="small">Casos abiertos: {{ reports.data.summary?.alerts?.open_cases ?? 0 }}</div></BCard></div>
                <div class="col-md-6"><BCard class="border bg-light-subtle"><div class="text-muted small">Último sociograma</div><div class="fw-semibold">{{ reports.data.sociogram?.title || "Sin sociograma" }}</div><div class="small">{{ reports.data.sociogram?.interpretation || "No hay interpretación registrada." }}</div></BCard></div>
              </div>
            </BCard>
            <div class="row g-3 mt-1">
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm">
                  <h6 class="mb-3">Casos del curso</h6>
                  <BTableSimple responsive>
                    <thead><tr><th>Folio</th><th>Fecha</th><th>Clasificación</th><th>Estado</th></tr></thead>
                    <tbody><tr v-for="item in reports.data.lists?.cases || []" :key="item.id"><td>{{ item.folio }}</td><td>{{ formatDate(item.opened_at) }}</td><td>{{ item.classification_label }}</td><td><StatusBadge :status="item.status" /></td></tr></tbody>
                  </BTableSimple>
                </BCard>
              </div>
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm">
                  <h6 class="mb-3">Bitácora del curso</h6>
                  <BTableSimple responsive>
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Estado</th></tr></thead>
                    <tbody><tr v-for="item in reports.data.lists?.daily_logs || []" :key="item.id"><td>{{ formatDate(item.happened_at) }}</td><td>{{ item.daily_log_type_label }}</td><td>{{ item.description }}</td><td><StatusBadge :status="item.status" /></td></tr></tbody>
                  </BTableSimple>
                </BCard>
              </div>
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm">
                  <h6 class="mb-3">Derivaciones</h6>
                  <BTableSimple responsive>
                    <thead><tr><th>Fecha</th><th>Ámbito</th><th>Destino</th><th>Estado</th></tr></thead>
                    <tbody><tr v-for="item in reports.data.lists?.derivations || []" :key="item.id"><td>{{ formatDate(item.derived_at) }}</td><td>{{ statusText(item.scope) }}</td><td>{{ item.destination_label || "-" }}</td><td><StatusBadge :status="item.status" /></td></tr></tbody>
                  </BTableSimple>
                </BCard>
              </div>
              <div class="col-xl-6">
                <BCard class="border-0 shadow-sm">
                  <h6 class="mb-3">Entrevistas y medidas</h6>
                  <BTableSimple responsive class="mb-3">
                    <thead><tr><th>Fecha</th><th>Motivo</th><th>Seguimiento</th></tr></thead>
                    <tbody><tr v-for="item in reports.data.lists?.interviews || []" :key="item.id"><td>{{ formatDate(item.interview_at) }}</td><td>{{ item.motive }}</td><td><StatusBadge :status="item.follow_up_status" /></td></tr></tbody>
                  </BTableSimple>
                  <BTableSimple responsive>
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Estado</th></tr></thead>
                    <tbody><tr v-for="item in reports.data.lists?.measures || []" :key="item.id"><td>{{ formatDate(item.assigned_at) }}</td><td>{{ item.measure_type_label }}</td><td><StatusBadge :status="item.status" /></td></tr></tbody>
                  </BTableSimple>
                </BCard>
              </div>
            </div>
          </template>
        </template>
      </template>
    </div>

    <BModal v-model="caseDetailModal" size="xl" title="Detalle del caso" hide-footer scrollable>
      <div v-if="caseDetail" class="d-flex flex-column gap-3">
        <div class="row g-3">
          <div class="col-md-4"><BCard class="border bg-light-subtle"><div class="text-muted small">Folio</div><div class="fw-semibold">{{ caseDetail.folio }}</div></BCard></div>
          <div class="col-md-4"><BCard class="border bg-light-subtle"><div class="text-muted small">Estudiante</div><div class="fw-semibold">{{ caseDetail.student?.registered_name_resolved || caseDetail.student?.registered_name || "-" }}</div></BCard></div>
          <div class="col-md-4"><BCard class="border bg-light-subtle"><div class="text-muted small">Estado</div><StatusBadge :status="caseDetail.status" /></BCard></div>
        </div>
        <BCard class="border-0 shadow-sm">
          <h6 class="mb-3">Relato y medidas</h6>
          <div class="small text-muted mb-1">Relato inicial</div>
          <div class="mb-3">{{ caseDetail.initial_report }}</div>
          <div class="small text-muted mb-1">Antecedentes</div>
          <div class="mb-3">{{ caseDetail.background || "-" }}</div>
          <div class="small text-muted mb-1">Medidas inmediatas</div>
          <div>{{ caseDetail.immediate_measures || "-" }}</div>
        </BCard>
        <div class="row g-3">
          <div class="col-lg-6">
            <BCard class="border-0 shadow-sm h-100">
              <h6 class="mb-3">Seguimientos</h6>
              <ConvivenciaTimeline :items="caseDetail.follow_ups || []" empty-text="No hay seguimientos registrados para este caso." />
            </BCard>
          </div>
          <div class="col-lg-6">
            <BCard class="border-0 shadow-sm h-100">
              <h6 class="mb-3">Cambios de estado</h6>
              <ConvivenciaTimeline :items="caseDetail.status_logs || []" empty-text="No hay cambios de estado registrados." />
            </BCard>
          </div>
        </div>
        <BCard class="border-0 shadow-sm">
          <h6 class="mb-3">Registrar seguimiento</h6>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="caseFollowUpForm.follow_up_at" type="datetime-local" /></div>
            <div class="col-md-4"><label class="form-label">Título</label><BFormInput v-model="caseFollowUpForm.title" /></div>
            <div class="col-md-4"><label class="form-label">Próximo seguimiento</label><BFormInput v-model="caseFollowUpForm.next_follow_up_at" type="datetime-local" /></div>
            <div class="col-12"><label class="form-label">Notas</label><BFormTextarea v-model="caseFollowUpForm.notes" rows="3" /></div>
            <div class="col-12"><BButton variant="success" :disabled="caseFollowUpSaving" @click="saveCaseFollowUp">Guardar seguimiento</BButton></div>
          </div>
        </BCard>
      </div>
    </BModal>
  </Layout>
</template>
