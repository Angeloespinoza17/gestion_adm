<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/convivencia/help-button.vue";
import StatusBadge from "../../components/convivencia/status-badge.vue";
import CriticalityBadge from "../../components/convivencia/criticality-badge.vue";
import ConvivenciaTimeline from "../../components/convivencia/timeline.vue";
import ProtocolManager from "../../components/convivencia/protocols/protocol-manager.vue";
import ConvivenciaAnalytics from "../../components/convivencia/dashboard/convivencia-analytics.vue";
import ConvivenciaFormModal from "../../components/convivencia/ui/convivencia-form-modal.vue";
import ConvivenciaRowActions from "../../components/convivencia/ui/convivencia-row-actions.vue";
import ConvivenciaSectionToolbar from "../../components/convivencia/ui/convivencia-section-toolbar.vue";
import ConvivenciaRecordForm from "../../components/convivencia/forms/convivencia-record-form.vue";
import {
  convivenciaRecordEndpoints,
  normalizeConvivenciaRecord,
} from "../../components/convivencia/forms/record-normalizers";
import ConvivenciaOperationalSection from "../../components/convivencia/operations/convivencia-operational-section.vue";
import ConvivenciaCaseManagementWorkspace from "../../components/convivencia/cases/convivencia-case-management-workspace.vue";
import ConvivenciaIdpsWorkspace from "../../components/convivencia/idps/convivencia-idps-workspace.vue";
import ConvivenciaPlanWorkspace from "../../components/convivencia/plans/convivencia-plan-workspace.vue";
import ConvivenciaSociogramAnalysis from "../../components/convivencia/sociograms/convivencia-sociogram-analysis.vue";
import { downloadConvivenciaCasePdf } from "../../components/convivencia/pdf/convivencia-case-pdf";
import { downloadConvivenciaSociogramPdf } from "../../components/convivencia/pdf/convivencia-sociogram-pdf";
import { downloadConvivenciaAnalyticsPdf } from "../../components/convivencia/pdf/convivencia-analytics-pdf";
import { fetchCompleteCourseReportLists } from "../../components/convivencia/reports/course-report-export";
import {
  firstConvivenciaFallbackRoute,
  visibleConvivenciaTabs,
} from "./tab-access";
import {
  basicApexOptions,
  confirmConvivenciaAction,
  downloadExcelWorkbook,
  extractChartLabels,
  extractChartTotals,
  formatConvivenciaDate,
  formatConvivenciaDateTime,
  formatConvivenciaError,
  humanizeConvivenciaStatus,
  normalizeOptions,
  showConvivenciaError,
  showConvivenciaSuccess,
  showConvivenciaWarning,
  toInputDateTime,
} from "../../components/convivencia/module-utils";

const routeMap = {
  "/convivencia": "dashboard",
  "/convivencia/planes": "planes",
  "/convivencia/casos": "casos",
  "/convivencia/denuncias": "casos",
  "/convivencia/derivaciones": "casos",
  "/convivencia/protocolos": "protocolos",
  "/convivencia/entrevistas": "entrevistas",
  "/convivencia/medidas": "medidas",
  "/convivencia/bitacora": "bitacora",
  "/convivencia/sociogramas": "sociogramas",
  "/convivencia/idps": "idps",
  "/convivencia/reportes": "dashboard",
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

const emptyPlanAction = () => ({ dimension_item_id: null, responsible_user_id: null, responsible_staff_id: null, responsible_department_id: null, action_type: "preventiva", title: "", description: "", dimension_label: "", responsible_label: "", starts_on: "", ends_on: "", required_resources: "", indicator_summary: "", verification_means: "", status: "borrador", advance_percentage: 0, observations: "", evidence_summary: "" });
const emptyCasePerson = () => ({ student_profile_id: null, user_id: null, staff_id: null, course_section_id: null, person_type: "estudiante", role_type: "afectado", full_name: "", identifier: "", relationship_label: "", contact_reference: "", notes: "", is_sensitive: false });
const emptyProtocolStep = () => ({ stage_name: "", responsible_label: "", due_days: 1 });
const emptyInterviewParticipant = () => ({ student_profile_id: null, user_id: null, staff_id: null, participant_type: "estudiante", participant_role: "participante", full_name: "", contact_reference: "", notes: "" });
const emptyQuestion = () => ({ prompt: "", selection_type: "positiva", max_choices: 3, active: true });
const emptyAnswer = () => ({ question_order: 1, respondent_student_id: null, selected_student_id: null, selection_type: "positiva", notes: "" });
const currentLocalDateTime = () => {
  const now = new Date();
  return new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
};

const operationalStateBySection = {
  planes: "plans",
  casos: "cases",
  denuncias: "complaints",
  derivaciones: "derivations",
  entrevistas: "interviews",
  medidas: "measures",
  bitacora: "dailyLogs",
  sociogramas: "sociograms",
};

export default {
  components: {
    Layout,
    LoadingState,
    HelpButton,
    StatusBadge,
    CriticalityBadge,
    ConvivenciaTimeline,
    ProtocolManager,
    ConvivenciaAnalytics,
    ConvivenciaFormModal,
    ConvivenciaRowActions,
    ConvivenciaSectionToolbar,
    ConvivenciaRecordForm,
    ConvivenciaOperationalSection,
    ConvivenciaCaseManagementWorkspace,
    ConvivenciaIdpsWorkspace,
    ConvivenciaPlanWorkspace,
    ConvivenciaSociogramAnalysis,
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
      tabs: [
        { key: "dashboard", route: "/convivencia", label: "Análisis e informes", icon: "bx-line-chart", capability: "can_view_dashboard" },
        { key: "planes", route: "/convivencia/planes", label: "Plan de gestión", icon: "bx-calendar-check", capability: "can_manage_plans" },
        { key: "casos", route: "/convivencia/casos", label: "Expedientes", icon: "bx-folder-open", capability: "can_view_cases" },
        { key: "protocolos", route: "/convivencia/protocolos", label: "Protocolos", icon: "bx-shield-quarter", capability: "can_manage_protocols" },
        { key: "entrevistas", route: "/convivencia/entrevistas", label: "Entrevistas", icon: "bx-conversation", capability: "can_manage_interviews" },
        { key: "medidas", route: "/convivencia/medidas", label: "Medidas", icon: "bx-check-shield", capability: "can_manage_measures" },
        { key: "bitacora", route: "/convivencia/bitacora", label: "Bitácora", icon: "bx-book-content", capability: "can_manage_daily_logs" },
        { key: "sociogramas", route: "/convivencia/sociogramas", label: "Sociogramas", icon: "bx-network-chart", capability: "can_view_sociograms" },
        { key: "idps", route: "/convivencia/idps", label: "IDPS", icon: "bx-bar-chart-square", capability: "can_manage_plans" },
      ],
      meta: {
        dashboard: { title: "Análisis e Informes de Convivencia", subtitle: "Indicadores, gráficos, comparación por curso y reportes exportables en una sola vista.", help: "Aplica un único filtro para analizar expedientes, protocolos, medidas, entrevistas, denuncias y derivaciones. Los informes PDF y Excel respetan los permisos y el ámbito seleccionado." },
        planes: { title: "Plan de Gestión de Convivencia Escolar", subtitle: "Administración anual de objetivos, acciones, responsables y avance del plan.", help: "Registra el plan anual con acciones preventivas, promocionales, formativas y reactivas, con trazabilidad de avance y responsables." },
        casos: { title: "Gestión Unificada de Expedientes", subtitle: "Casos, denuncias y derivaciones en un solo espacio de registro y seguimiento.", help: "Selecciona el tipo de registro dentro de la misma vista. Cada formulario conserva sus reglas, permisos y trazabilidad, pero comparte una experiencia guiada y consistente." },
        denuncias: { title: "Denuncia de convivencia", subtitle: "Recepción, admisibilidad y conversión trazable a caso.", help: "Registra una denuncia desde el formulario unificado." },
        derivaciones: { title: "Derivación de convivencia", subtitle: "Destino, prioridad, plazo, respuesta y seguimiento.", help: "Registra una derivación interna o externa desde el formulario unificado." },
        protocolos: { title: "Protocolos de Actuación", subtitle: "Catálogo de protocolos y activaciones asociadas a casos o denuncias.", help: "Administra protocolos configurables y permite activarlos sobre un caso o denuncia con seguimiento por etapas y plazos." },
        entrevistas: { title: "Entrevistas de Convivencia", subtitle: "Registro de entrevistas, participantes, acuerdos y compromisos.", help: "Permite registrar entrevistas con estudiantes, apoderados o funcionarios y dejar acuerdos y seguimiento." },
        medidas: { title: "Medidas Formativas", subtitle: "Seguimiento de acciones formativas, reparadoras y de mediación.", help: "Registra medidas asociadas a estudiantes, cursos o casos, con fecha de cumplimiento, evidencia y cierre." },
        bitacora: { title: "Bitácora de Inspectoría", subtitle: "Registro diario de hechos relevantes de la jornada y su eventual escalamiento.", help: "La bitácora diaria permite dejar constancia de atrasos, conflictos, observaciones positivas y otros hechos que luego pueden convertirse en caso o derivación." },
        sociogramas: { title: "Sociogramas", subtitle: "Aplicación y análisis simple de relaciones sociométricas por curso.", help: "Permite registrar preguntas, respuestas y visualizar liderazgos, aislamiento, rechazo y vínculos recíprocos en un curso." },
        idps: { title: "Indicadores IDPS", subtitle: "Períodos, dimensiones, instrumentos y resultados comparables.", help: "Configura períodos, dimensiones e instrumentos IDPS y vincula resultados con cursos, niveles y el plan de convivencia." },
      },
      dashboard: { loading: false, filters: { academic_year_id: null, course_section_id: null, from: "", to: "", semester: null }, data: null },
      plans: tabState(() => ({ id: null, academic_year_id: null, responsible_user_id: null, responsible_staff_id: null, name: "", general_objective: "", specific_objectives: [], resources_required: "", indicators_summary: "", verification_means_summary: "", final_evaluation: "", advance_percentage: 0, status: "borrador", starts_on: "", ends_on: "", observations: "", is_sensitive: false, actions: [emptyPlanAction()] })),
      cases: tabState(() => ({ id: null, academic_year_id: null, student_profile_id: null, course_section_id: null, case_type_item_id: null, classification_item_id: null, subclassification_item_id: null, criticality_item_id: null, responsible_user_id: null, responsible_staff_id: null, opened_at: "", happened_at: "", origin: "observacion", status: "abierto", place: "", initial_report: "", background: "", immediate_measures: "", safeguarding_measures: "", internal_notes: "", resolution: "", conclusion: "", follow_up_due_at: "", is_sensitive: false, people: [] })),
      complaints: tabState(() => ({ id: null, academic_year_id: null, responsible_user_id: null, case_id: null, affected_student_id: null, course_section_id: null, situation_type_item_id: null, complainant_name: "", complainant_type: "apoderado", contact_email: "", contact_phone: "", place: "", received_at: "", happened_at: "", report_text: "", involved_snapshot: [], status: "recibida", truth_declaration_accepted: true, is_anonymous: false, is_sensitive: false, admissibility_result: "" })),
      derivations: tabState(() => ({ id: null, case_id: null, academic_year_id: null, student_profile_id: null, course_section_id: null, scope: "internal", status: "ingresada", priority_level: "media", confidentiality_level: "reservada", destination_department_id: null, destination_user_id: null, destination_staff_id: null, external_institution_id: null, responsible_user_id: null, destination_label: "", external_contact_name: "", external_contact_email: "", external_contact_phone: "", derived_at: "", sent_at: "", response_due_at: "", responded_at: "", closed_at: "", motive: "", narrative: "", response_text: "", suggested_actions: "", follow_up_notes: "", is_sensitive: false })),
      protocols: tabState(() => ({ id: null, protocol_type_item_id: null, criticality_item_id: null, name: "", description: "", default_due_days: 5, status: "activo", steps: [emptyProtocolStep()] })),
      protocolActivationForm: { protocol_id: null, case_id: null, complaint_id: null, status: "activo", current_stage_name: "", actions_taken: "", measures_adopted: "" },
      measures: tabState(() => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, measure_type_item_id: null, responsible_user_id: null, responsible_staff_id: null, validated_by: null, assigned_at: "", due_at: "", closed_at: "", status: "asignada", description: "", training_objective: "", evidence_summary: "", student_reflection: "", repair_action: "", responsible_notes: "", closure_notes: "", is_sensitive: false })),
      interviews: tabState(() => ({ id: null, record_updated_at: "", change_reason: "", case_id: null, student_profile_id: null, course_section_id: null, interview_type_item_id: null, responsible_user_id: null, interview_at: "", motive: "", topics: "", agreements: "", commitments: "", follow_up_date: "", follow_up_status: "pendiente", internal_notes: "", participants: [emptyInterviewParticipant()], is_sensitive: false })),
      dailyLogs: tabState(() => ({ id: null, case_id: null, academic_year_id: null, student_profile_id: null, course_section_id: null, daily_log_type_item_id: null, inspector_user_id: null, inspector_staff_id: null, happened_at: "", place: "", description: "", immediate_action: "", involved_snapshot: [], status: "registrado", guardian_informed: false, guardian_contact_note: "", is_sensitive: false })),
      sociograms: tabState(() => ({ id: null, academic_year_id: null, course_section_id: null, title: "", applied_on: currentLocalDateTime().slice(0, 10), status: "borrador", confidentiality_level: "alta_confidencialidad", interpretation: "", is_sensitive: true, questions: [emptyQuestion()], answers: [] })),
      idps: { loading: false, saving: false, modal: null, overview: null, periodForm: { id: null, academic_year_id: null, name: "", starts_on: "", ends_on: "", status: "abierto", notes: "" }, dimensionForm: { id: null, code: "", name: "", description: "", active: true }, instrumentForm: { id: null, dimension_id: null, name: "", description: "", response_type: "escala", scale_label: "", active: true }, resultForm: { id: null, period_id: null, dimension_id: null, instrument_id: null, academic_year_id: null, course_section_id: null, education_level_id: null, related_plan_id: null, result_scope: "curso", reference_label: "", score: "", percentage: "", sample_size: "", qualitative_observations: "", improvement_actions: "", is_sensitive: false } },
      reports: { loading: false, exporting: null, exportProgress: 0, filters: { academic_year_id: null, course_section_id: null, from: "", to: "", semester: null }, appliedFilters: null, data: null },
      recordLoadingId: null,
      caseConversion: {
        show: false,
        saving: false,
        sourceType: null,
        source: null,
        form: {
          case_type_item_id: null,
          classification_item_id: null,
          subclassification_item_id: null,
          criticality_item_id: null,
          responsible_user_id: null,
          follow_up_due_at: "",
          is_sensitive: false,
        },
      },
      caseDetailModal: false,
      caseDetail: null,
      caseFollowUpForm: {
        follow_up_at: "",
        title: "",
        notes: "",
        next_follow_up_at: "",
      },
      caseFollowUpSaving: false,
      casePdfLoadingId: null,
      sociogramAnalysis: { show: false, loading: false, exporting: false, record: null },
      caseManagementSection: "casos",
    };
  },
  computed: {
    activeTab() {
      return routeMap[this.$route.path] || "dashboard";
    },
    activeMeta() {
      return this.meta[this.activeTab];
    },
    activeSection() {
      return this.tabs.find((tab) => tab.key === this.activeTab) || this.tabs[0];
    },
    visibleTabs() {
      const capabilities = this.catalogs.capabilities || {};
      return visibleConvivenciaTabs(this.tabs, capabilities);
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
    idpsPeriods() {
      return this.idps.overview?.periods || [];
    },
    idpsDimensions() {
      return this.idps.overview?.dimensions || [];
    },
    idpsResults() {
      return this.idps.overview?.results?.data || [];
    },
    canExportReports() {
      return this.catalogs.capabilities?.can_export_reports === true;
    },
    reportFiltersDirty() {
      if (!this.reports.appliedFilters) return true;
      return JSON.stringify(this.reports.filters || {}) !== JSON.stringify(this.reports.appliedFilters);
    },
    caseManagementTypes() {
      const capabilities = this.catalogs.capabilities || {};
      return [
        { key: "casos", label: "Caso", icon: "bx-folder-open", available: capabilities.can_view_cases === true || capabilities.can_create_cases === true },
        { key: "denuncias", label: "Denuncia", icon: "bx-message-square-error", available: capabilities.can_manage_complaints === true || capabilities.can_view_cases === true },
        { key: "derivaciones", label: "Derivación", icon: "bx-transfer-alt", available: capabilities.can_manage_internal_derivations === true || capabilities.can_manage_external_derivations === true || capabilities.can_view_cases === true },
      ].filter((type) => type.available);
    },
    availableRecordCreationTypes() {
      return this.caseManagementTypes.filter(({ key }) => this.canCreateSection(key));
    },
    recordTypePickerOptions() {
      if (this.currentOperationalState?.form?.id) {
        return this.caseManagementTypes.filter(({ key }) => key === this.recordFormSection);
      }
      return this.availableRecordCreationTypes;
    },
    recordFormSection() {
      return this.activeTab === "casos" ? this.caseManagementSection : this.activeTab;
    },
    recordFormMeta() {
      return this.meta[this.recordFormSection] || this.activeMeta;
    },
    recordFormType() {
      return this.caseManagementTypes.find(({ key }) => key === this.recordFormSection) || null;
    },
    recordFormIcon() {
      return this.recordFormType?.icon || this.activeSection.icon;
    },
    recordFormSubmitLabel() {
      if (this.currentOperationalState?.form?.id) return "Guardar cambios";
      return {
        casos: "Abrir caso",
        denuncias: "Registrar denuncia",
        derivaciones: "Crear derivación",
      }[this.recordFormSection] || "Crear registro";
    },
    caseConversionTitle() {
      return this.caseConversion.sourceType === "derivaciones"
        ? "Convertir derivación en caso"
        : "Convertir denuncia en caso";
    },
    caseConversionSourceLabel() {
      const source = this.caseConversion.source || {};
      if (this.caseConversion.sourceType === "denuncias") return source.folio || `Denuncia #${source.id || ""}`;
      return source.destination_label || source.destination_department?.name || source.external_institution?.name || `Derivación #${source.id || ""}`;
    },
    caseConversionSubclassifications() {
      const classificationId = Number(this.caseConversion.form.classification_item_id || 0);
      const options = this.subclassificationOptions.filter((option) => (
        !option.parentId || Number(option.parentId) === classificationId
      ));
      return [{ value: null, text: classificationId ? "Sin subclasificación" : "Selecciona primero una clasificación", disabled: !classificationId }].concat(options);
    },
    currentOperationalState() {
      return this[operationalStateBySection[this.recordFormSection]] || null;
    },
    caseDetailSupportTeam() {
      return (this.caseDetail?.people || []).filter((person) => person.role_type === "profesional_apoyo");
    },
    caseDetailInvolvedPeople() {
      return (this.caseDetail?.people || []).filter((person) => person.role_type !== "profesional_apoyo");
    },
    recordFormOptions() {
      const capabilities = this.catalogs.capabilities || {};
      const derivationScopes = normalizeOptions(this.catalogs.derivation_scope_options).filter((option) => {
        if (option.value === "external") return capabilities.can_manage_external_derivations === true;
        if (option.value === "internal") return capabilities.can_manage_internal_derivations === true;
        return true;
      });
      const staffUsers = (this.catalogs.users || []).filter((user) => user.staff_id || user.id === this.catalogs.current_user_id);
      const staffById = new Map((this.catalogs.staff || []).map((staff) => [Number(staff.id), staff]));
      const caseResponsibleUsers = staffUsers.map((user) => {
        const staff = staffById.get(Number(user.staff_id || 0));

        return {
          value: user.id,
          text: user.name,
          staff_id: user.staff_id || null,
          staff_name: staff?.full_name || null,
        };
      });
      const historicalResponsibleId = Number(this.cases.form?.responsible_user_id || 0);
      if (historicalResponsibleId && !caseResponsibleUsers.some((user) => Number(user.value) === historicalResponsibleId)) {
        caseResponsibleUsers.push({
          value: historicalResponsibleId,
          text: this.cases.form?.responsible_user_name || `Responsable histórico #${historicalResponsibleId}`,
          staff_id: this.cases.form?.responsible_staff_id || null,
          staff_name: this.cases.form?.responsible_staff_name || null,
        });
      }
      return {
        capabilities,
        academicYears: normalizeOptions(this.catalogs.academic_years, false),
        users: this.userOptions(false),
        caseResponsibleUsers,
        staffUsersOptional: normalizeOptions(staffUsers, true, "Sin usuario vinculado"),
        usersOptional: this.userOptions(true, "Sin usuario vinculado"),
        staffOptional: normalizeOptions(this.catalogs.staff, true, "Sin funcionario vinculado"),
        supportProfessionals: (this.catalogs.support_professionals || []).map((professional) => ({
          ...professional,
          value: professional.key,
          text: [professional.full_name, professional.area_name, professional.professional_role_name]
            .filter(Boolean)
            .filter((value, index, values) => values.indexOf(value) === index)
            .join(" — "),
        })),
        studentsRequired: this.studentOptions(false),
        studentsOptional: this.studentOptions(true, "Sin estudiante"),
        coursesRequired: this.courseOptions(false),
        coursesOptional: this.courseOptions(true, "Sin curso"),
        caseTypes: this.catalogOptions("case_type"),
        classifications: this.classificationOptions,
        subclassifications: this.subclassificationOptions,
        criticalities: this.criticalityOptions,
        planStatuses: normalizeOptions(this.catalogs.plan_status_options),
        planActionTypes: normalizeOptions(this.catalogs.plan_action_type_options),
        planDimensions: [{ value: null, text: "Sin dimensión" }].concat(this.catalogOptions("plan_dimension")),
        caseOrigins: normalizeOptions(this.catalogs.case_origin_options),
        caseStatuses: normalizeOptions(this.catalogs.case_status_options),
        personTypes: normalizeOptions(this.catalogs.person_type_options),
        personRoles: normalizeOptions(this.catalogs.person_role_options),
        complaintTypes: normalizeOptions(this.catalogs.complaint_type_options),
        complaintStatuses: normalizeOptions(this.catalogs.complaint_status_options),
        situationTypes: this.catalogOptions("situation_type"),
        derivationScopes,
        derivationStatuses: normalizeOptions(this.catalogs.derivation_status_options),
        derivationPriorities: normalizeOptions(this.catalogs.derivation_priority_options),
        departmentsOptional: this.departmentOptions(true),
        institutionsOptional: this.institutionOptions(true),
        confidentialityLevels: [
          { value: "alta_confidencialidad", text: "Alta confidencialidad" },
          { value: "reservada", text: "Reservada" },
          { value: "interna", text: "Interna" },
        ],
        interviewTypes: this.catalogOptions("interview_type"),
        interviewStatuses: normalizeOptions(this.catalogs.interview_follow_up_status_options),
        participantTypes: [
          { value: "estudiante", text: "Estudiante" },
          { value: "apoderado", text: "Apoderado" },
          { value: "funcionario", text: "Funcionario" },
          { value: "grupo_estudiantes", text: "Grupo de estudiantes" },
          { value: "otro", text: "Otro" },
        ],
        measureTypes: this.catalogOptions("measure_type"),
        measureStatuses: normalizeOptions(this.catalogs.measure_status_options),
        dailyLogTypes: this.catalogOptions("daily_log_type"),
        dailyLogStatuses: normalizeOptions(this.catalogs.daily_log_status_options),
        sociogramStatuses: normalizeOptions(this.catalogs.sociogram_status_options),
        selectionTypes: [
          { value: "positiva", text: "Positiva" },
          { value: "negativa", text: "Negativa" },
          { value: "neutra", text: "Neutra" },
        ],
      };
    },
    operationalFilterOptions() {
      return {
        academicYearsWithAll: normalizeOptions(this.catalogs.academic_years, true, "Todos los años"),
        coursesWithAll: this.courseOptions(true),
        planStatusesWithAll: normalizeOptions(this.catalogs.plan_status_options, true, "Todos los estados"),
        caseStatusesWithAll: normalizeOptions(this.catalogs.case_status_options, true, "Todos los estados"),
        complaintStatusesWithAll: normalizeOptions(this.catalogs.complaint_status_options, true, "Todos los estados"),
        derivationScopesWithAll: normalizeOptions(this.catalogs.derivation_scope_options, true, "Todos los alcances"),
        derivationStatusesWithAll: normalizeOptions(this.catalogs.derivation_status_options, true, "Todos los estados"),
        interviewStatusesWithAll: normalizeOptions(this.catalogs.interview_follow_up_status_options, true, "Todos los seguimientos"),
        measureStatusesWithAll: normalizeOptions(this.catalogs.measure_status_options, true, "Todos los estados"),
        dailyLogTypesWithAll: [{ value: null, text: "Todos los tipos" }].concat(this.catalogOptions("daily_log_type")),
        dailyLogStatusesWithAll: normalizeOptions(this.catalogs.daily_log_status_options, true, "Todos los estados"),
        sociogramStatusesWithAll: normalizeOptions(this.catalogs.sociogram_status_options, true, "Todos los estados"),
      };
    },
  },
  watch: {
    "$route.path"() {
      this.loadActiveTab();
    },
    "reports.filters": {
      deep: true,
      handler() {
        this.cancelReportExport();
      },
    },
    "$route.query.tipo"(value) {
      if (this.activeTab !== "casos") return;
      const requested = ["casos", "denuncias", "derivaciones"].includes(value) ? value : "casos";
      if (requested !== this.caseManagementSection && this.caseManagementTypes.some(({ key }) => key === requested)) {
        this.caseManagementSection = requested;
        this.loadCaseManagementSection(requested);
      }
    },
  },
  mounted() {
    this.loadCatalogs();
  },
  beforeUnmount() {
    this.cancelReportExport();
  },
  methods: {
    ensureActiveTabVisible() {
      const fallbackRoute = firstConvivenciaFallbackRoute(this.$route.path, this.visibleTabs);
      if (!fallbackRoute) return this.visibleTabs.some((tab) => tab.route === this.$route.path);

      if (fallbackRoute !== this.$route.path) {
        const navigation = this.$router.replace(fallbackRoute);
        if (navigation?.catch) navigation.catch(() => {});
      }

      return false;
    },
    navigateSection(route) {
      if (route && route !== this.$route.path) {
        this.$router.push(route);
      }
    },
    catalogOptions(group) {
      return (this.catalogs.catalogs?.[group] || []).map((item) => ({
        value: item.id,
        text: item.name,
        code: item.code,
        parentId: item.parent_id,
        description: item.description,
        color: item.color,
        metadata: item.metadata || {},
      }));
    },
    catalogItemId(group, code) {
      return this.catalogOptions(group).find((item) => item.code === code)?.value || null;
    },
    courseOptions(includeEmpty = true, emptyLabel = "Todos") {
      return normalizeOptions(this.catalogs.courses, includeEmpty, emptyLabel);
    },
    studentOptions(includeEmpty = true, emptyLabel = "Todos") {
      return normalizeOptions(this.catalogs.students, includeEmpty, emptyLabel);
    },
    userOptions(includeEmpty = true, emptyLabel = "Todos") {
      return normalizeOptions(this.catalogs.users, includeEmpty, emptyLabel);
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
    selectCaseManagementSection(section, updateRoute = true) {
      if (!this.caseManagementTypes.some(({ key }) => key === section)) return;
      this.caseManagementSection = section;
      if (updateRoute && this.$route.path === "/convivencia/casos" && this.$route.query?.tipo !== section) {
        const navigation = this.$router.replace({
          path: "/convivencia/casos",
          query: { ...this.$route.query, tipo: section },
        });
        if (navigation?.catch) navigation.catch(() => {});
      }
      this.loadCaseManagementSection(section);
    },
    loadCaseManagementSection(section = this.caseManagementSection) {
      const capabilities = this.catalogs.capabilities || {};
      if (section === "casos") {
        if (capabilities.can_view_cases === true) return this.loadCases();
        return Promise.resolve();
      }
      if (section === "denuncias") return this.loadComplaints();
      if (section === "derivaciones") return this.loadDerivations();
      return Promise.resolve();
    },
    loadCaseManagementWorkspace() {
      const requested = ["casos", "denuncias", "derivaciones"].includes(this.$route.query?.tipo)
        ? this.$route.query.tipo
        : this.caseManagementSection;
      const selected = this.caseManagementTypes.some(({ key }) => key === requested)
        ? requested
        : this.caseManagementTypes[0]?.key;
      if (!selected) return Promise.resolve();
      this.caseManagementSection = selected;
      return this.loadCaseManagementSection(selected);
    },
    switchUnifiedRecordType(section) {
      if (this.currentOperationalState?.form?.id || !this.canCreateSection(section) || section === this.recordFormSection) return;
      const previousSection = this.recordFormSection;
      this.resetForm(previousSection);
      this.selectCaseManagementSection(section);
      this.resetForm(section);
      const stateKey = operationalStateBySection[section];
      if (section === "derivaciones" && this.catalogs.capabilities?.can_manage_internal_derivations !== true) {
        this.derivations.form.scope = "external";
      }
      if (stateKey) this[stateKey].showForm = true;
    },
    resetForm(section) {
      const stateBySection = {
        planes: "plans",
        casos: "cases",
        denuncias: "complaints",
        derivaciones: "derivations",
        protocolos: "protocols",
        medidas: "measures",
        entrevistas: "interviews",
        bitacora: "dailyLogs",
        sociogramas: "sociograms",
      };
      const defaults = {
        planes: () => ({ id: null, academic_year_id: this.catalogs.active_academic_year_id, responsible_user_id: null, responsible_staff_id: null, name: "", general_objective: "", specific_objectives: [], resources_required: "", indicators_summary: "", verification_means_summary: "", final_evaluation: "", advance_percentage: 0, status: "borrador", starts_on: "", ends_on: "", observations: "", is_sensitive: false, actions: [emptyPlanAction()] }),
        casos: () => ({ id: null, academic_year_id: this.catalogs.active_academic_year_id, student_profile_id: null, course_section_id: null, case_type_item_id: this.catalogItemId("case_type", "caso_convivencia"), classification_item_id: null, subclassification_item_id: null, criticality_item_id: null, responsible_user_id: this.catalogs.current_user_id || null, responsible_staff_id: this.catalogs.current_staff_id || null, opened_at: currentLocalDateTime(), happened_at: "", origin: "observacion", status: "abierto", place: "", initial_report: "", background: "", immediate_measures: "", safeguarding_measures: "", internal_notes: "", resolution: "", conclusion: "", follow_up_due_at: "", is_sensitive: false, people: [] }),
        denuncias: () => ({ id: null, academic_year_id: this.catalogs.active_academic_year_id, responsible_user_id: null, case_id: null, affected_student_id: null, course_section_id: null, situation_type_item_id: null, complainant_name: "", complainant_type: "apoderado", contact_email: "", contact_phone: "", place: "", received_at: toInputDateTime(new Date().toISOString()), happened_at: "", report_text: "", involved_snapshot: [], status: "recibida", truth_declaration_accepted: true, is_anonymous: false, is_sensitive: false, admissibility_result: "" }),
        derivaciones: () => ({ id: null, case_id: null, academic_year_id: this.catalogs.active_academic_year_id, student_profile_id: null, course_section_id: null, scope: "internal", status: "ingresada", priority_level: "media", confidentiality_level: "reservada", destination_department_id: null, destination_user_id: null, destination_staff_id: null, external_institution_id: null, responsible_user_id: null, destination_label: "", external_contact_name: "", external_contact_email: "", external_contact_phone: "", derived_at: toInputDateTime(new Date().toISOString()), sent_at: "", response_due_at: "", responded_at: "", closed_at: "", motive: "", narrative: "", response_text: "", suggested_actions: "", follow_up_notes: "", is_sensitive: false }),
        protocolos: () => ({ id: null, protocol_type_item_id: null, criticality_item_id: null, name: "", description: "", default_due_days: 5, status: "activo", steps: [emptyProtocolStep()] }),
        medidas: () => ({ id: null, case_id: null, student_profile_id: null, course_section_id: null, measure_type_item_id: null, responsible_user_id: null, responsible_staff_id: null, validated_by: null, assigned_at: toInputDateTime(new Date().toISOString()), due_at: "", closed_at: "", status: "asignada", description: "", training_objective: "", evidence_summary: "", student_reflection: "", repair_action: "", responsible_notes: "", closure_notes: "", is_sensitive: false }),
        entrevistas: () => ({ id: null, record_updated_at: "", change_reason: "", case_id: null, student_profile_id: null, course_section_id: null, interview_type_item_id: null, responsible_user_id: null, interview_at: toInputDateTime(new Date().toISOString()), motive: "", topics: "", agreements: "", commitments: "", follow_up_date: "", follow_up_status: "pendiente", internal_notes: "", participants: [emptyInterviewParticipant()], is_sensitive: false }),
        bitacora: () => ({ id: null, case_id: null, academic_year_id: this.catalogs.active_academic_year_id, student_profile_id: null, course_section_id: null, daily_log_type_item_id: null, inspector_user_id: this.inspectorUserId(), inspector_staff_id: null, happened_at: toInputDateTime(new Date().toISOString()), place: "", description: "", immediate_action: "", involved_snapshot: [], status: "registrado", guardian_informed: false, guardian_contact_note: "", is_sensitive: false }),
        sociogramas: () => ({ id: null, academic_year_id: this.catalogs.active_academic_year_id, course_section_id: null, title: "", applied_on: currentLocalDateTime().slice(0, 10), status: "borrador", confidentiality_level: "alta_confidencialidad", interpretation: "", is_sensitive: true, questions: [emptyQuestion()], answers: [] }),
      };
      const stateKey = stateBySection[section];
      const target = stateKey ? this[stateKey] : null;
      const factory = defaults[section];
      if (!target || typeof factory !== "function") return false;

      target.form = factory();
      target.showForm = false;
      return true;
    },
    canCreateSection(section) {
      const capabilities = this.catalogs.capabilities || {};
      const permissionBySection = {
        planes: "can_manage_plans",
        casos: "can_create_cases",
        denuncias: "can_manage_complaints",
        entrevistas: "can_manage_interviews",
        medidas: "can_manage_measures",
        bitacora: "can_manage_daily_logs",
        sociogramas: "can_manage_sociograms",
      };
      if (section === "derivaciones") return capabilities.can_manage_internal_derivations === true || capabilities.can_manage_external_derivations === true;
      return capabilities[permissionBySection[section]] === true;
    },
    canEditSection(section) {
      if (section === "casos") return this.catalogs.capabilities?.can_edit_cases === true;
      return this.canCreateSection(section);
    },
    canEditItem(section, item = {}) {
      if (section !== "derivaciones") return this.canEditSection(section);
      if (item.scope === "external") return this.catalogs.capabilities?.can_manage_external_derivations === true;
      return this.catalogs.capabilities?.can_manage_internal_derivations === true;
    },
    openCreateModal(section) {
      if (!this.canCreateSection(section)) return;
      if (["casos", "denuncias", "derivaciones"].includes(section)) this.caseManagementSection = section;
      this.resetForm(section);
      const stateKey = operationalStateBySection[section];
      if (section === "derivaciones" && this.catalogs.capabilities?.can_manage_internal_derivations !== true) {
        this.derivations.form.scope = "external";
      }
      if (stateKey) this[stateKey].showForm = true;
    },
    closeRecordModal(section = this.recordFormSection) {
      const stateKey = operationalStateBySection[section];
      if (!stateKey || this[stateKey].saving) return;
      this.resetForm(section);
    },
    submitRecordModal() {
      const saveBySection = {
        planes: this.savePlans,
        casos: this.saveCases,
        denuncias: this.saveComplaints,
        derivaciones: this.saveDerivations,
        entrevistas: this.saveInterviews,
        medidas: this.saveMeasures,
        bitacora: this.saveDailyLogs,
        sociogramas: this.saveSociograms,
      };
      saveBySection[this.recordFormSection]?.call(this);
    },
    loadOperationalPage(section, page = 1) {
      const stateKey = operationalStateBySection[section];
      const loadBySection = {
        planes: async () => {},
        casos: this.loadCases,
        denuncias: this.loadComplaints,
        derivaciones: this.loadDerivations,
        entrevistas: this.loadInterviews,
        medidas: this.loadMeasures,
        bitacora: this.loadDailyLogs,
        sociogramas: this.loadSociograms,
      };
      if (!stateKey || !loadBySection[section]) return;
      this[stateKey].filters.page = page;
      loadBySection[section].call(this);
    },
    refreshOperational(section) {
      this.loadOperationalPage(section, 1);
    },
    handleRecordFormAdd(collection) {
      if (collection === "involved_snapshot") {
        const target = this.currentOperationalState?.form;
        if (!target) return;
        if (!Array.isArray(target.involved_snapshot)) target.involved_snapshot = [];
        target.involved_snapshot.push(this.recordFormSection === "denuncias"
          ? { student_profile_id: null, person_type: "estudiante", role_type: "afectado", full_name: "", identifier: "", contact_reference: "" }
          : { full_name: "" });
        return;
      }
      const addByCollection = {
        actions: this.addAction,
        people: this.addPerson,
        participants: this.addParticipant,
        questions: this.addQuestion,
        answers: this.addAnswer,
      };
      addByCollection[collection]?.call(this);
    },
    removeRecordFormItem(collection, index) {
      const target = this.currentOperationalState?.form?.[collection];
      if (!Array.isArray(target)) return;
      if (collection === "questions") {
        const removedOrder = index + 1;
        const answers = this.currentOperationalState?.form?.answers;
        if (Array.isArray(answers)) {
          this.currentOperationalState.form.answers = answers
            .filter((answer) => Number(answer.question_order) !== removedOrder)
            .map((answer) => ({
              ...answer,
              question_order: Number(answer.question_order) > removedOrder
                ? Number(answer.question_order) - 1
                : Number(answer.question_order),
            }));
        }
      }
      this.removeArrayItem(target, index);
    },
    operationalActions(section, item) {
      const actions = [];
      if (section === "casos") {
        actions.push({ key: "view", label: "Ver detalle", icon: "bx-show", tone: "primary", prominent: true });
        if (this.canExportReports) actions.push({
          key: "pdf",
          label: "Descargar PDF",
          icon: "bxs-file-pdf",
          tone: "pdf",
          iconOnly: true,
          loading: this.casePdfLoadingId === item.id,
          disabled: this.casePdfLoadingId !== null,
        });
      }
      if (section === "sociogramas") {
        actions.push({ key: "view", label: "Ver sociograma", icon: "bx-network-chart", tone: "primary", prominent: true });
        if (this.canExportReports) actions.push({ key: "pdf", label: "Descargar informe PDF", icon: "bxs-file-pdf", tone: "pdf", iconOnly: true, loading: this.sociogramAnalysis.exporting && this.sociogramAnalysis.record?.id === item.id });
      }
      if (this.canEditItem(section, item)) actions.push({ key: "edit", label: "Editar", icon: "bx-edit-alt", tone: "primary", iconOnly: section === "casos" });
      if (section === "casos" && this.catalogs.capabilities?.can_close_cases === true && !["cerrado", "archivado"].includes(item.status)) actions.push({ key: "close", label: "Cerrar", icon: "bx-check-circle", tone: "success", iconOnly: true });
      if (["denuncias", "derivaciones"].includes(section) && this.canEditItem(section, item) && this.catalogs.capabilities?.can_create_cases === true && !item.case_id) actions.push({ key: "convert", label: "Convertir en caso", icon: "bx-folder-plus", tone: "success", prominent: true });
      if (["denuncias", "derivaciones"].includes(section) && item.case_id && this.catalogs.capabilities?.can_view_cases === true) actions.push({ key: "view-linked-case", label: "Ver caso", icon: "bx-folder-open", tone: "primary", prominent: true });
      if (section === "bitacora" && this.canEditSection(section)) {
        if (this.catalogs.capabilities?.can_create_cases === true) actions.push({ key: "case", label: "Pasar a caso", icon: "bx-folder-plus", tone: "success" });
        if (this.catalogs.capabilities?.can_manage_internal_derivations === true) actions.push({ key: "derive", label: "Derivar", icon: "bx-transfer-alt", tone: "warning" });
      }
      if (this.canEditItem(section, item)) actions.push({ key: "archive", label: "Archivar", icon: "bx-archive-in", tone: "danger", iconOnly: section === "casos" });
      return actions;
    },
    handleOperationalAction(section, action, item) {
      if (action === "view") return section === "sociogramas" ? this.openSociogramAnalysis(item) : this.openCaseDetail(item);
      if (action === "pdf") return section === "sociogramas" ? this.exportSociogramPdf(item) : this.exportCasePdf(item);
      if (action === "edit") return this.editItem(section, item);
      if (action === "close") return this.closeCase(item);
      if (action === "convert") return this.openCaseConversion(section, item);
      if (action === "view-linked-case") return this.openCaseDetail({ id: item.case_id });
      if (action === "case") return this.convertDailyLogToCase(item);
      if (action === "derive") return this.convertDailyLogToDerivation(item);
      if (action !== "archive") return undefined;
      const resources = {
        planes: ["/api/convivencia/plans", this.loadPlans, "Se archivará el plan seleccionado.", "plans"],
        casos: ["/api/convivencia/cases", this.loadCases, "Se archivará el caso seleccionado.", "cases"],
        denuncias: ["/api/convivencia/complaints", this.loadComplaints, "Se archivará la denuncia seleccionada.", "complaints"],
        derivaciones: ["/api/convivencia/derivations", this.loadDerivations, "Se archivará la derivación seleccionada."],
        entrevistas: ["/api/convivencia/interviews", this.loadInterviews, "Se archivará la entrevista seleccionada."],
        medidas: ["/api/convivencia/measures", this.loadMeasures, "Se archivará la medida formativa seleccionada."],
        bitacora: ["/api/convivencia/daily-logs", this.loadDailyLogs, "Se archivará el registro diario seleccionado."],
        sociogramas: ["/api/convivencia/sociograms", this.loadSociograms, "Se archivará el sociograma seleccionado."],
      };
      const resource = resources[section];
      if (resource) return this.deleteItem(resource[0], item.id, resource[1], resource[2], resource[3]);
      return undefined;
    },
    async loadCatalogs() {
      this.catalogsLoading = true;
      this.catalogsError = null;
      let catalogsLoaded = false;
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
        catalogsLoaded = true;
      } catch (error) {
        this.catalogsError = formatConvivenciaError(error, "No se pudieron cargar los catálogos de convivencia.");
      } finally {
        this.catalogsLoading = false;
      }

      if (catalogsLoaded) {
        await this.loadActiveTab();
      }
    },
    async loadActiveTab() {
      if (this.catalogsLoading || !this.ensureActiveTabVisible()) return;
      const map = {
        dashboard: this.loadAnalytics,
        planes: this.loadPlans,
        casos: this.loadCaseManagementWorkspace,
        protocolos: async () => {},
        entrevistas: this.loadInterviews,
        medidas: this.loadMeasures,
        bitacora: this.loadDailyLogs,
        sociogramas: this.loadSociograms,
        idps: this.loadIdps,
      };
      if (map[this.activeTab]) await map[this.activeTab].call(this);
    },
    setPaginated(state, payload) {
      state.items = payload?.data || [];
      state.pagination = { current_page: payload?.current_page, last_page: payload?.last_page, total: payload?.total };
    },
    async loadDashboard() {
      if (this.catalogs.capabilities?.can_view_dashboard !== true) return;
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
    async loadAnalytics(filters = null) {
      this.cancelReportExport();
      const appliedFilters = JSON.parse(JSON.stringify(filters || this.reports.filters || {}));
      this.dashboard.filters = { ...appliedFilters };
      this.reports.filters = { ...appliedFilters };
      const canLoadDashboard = this.catalogs.capabilities?.can_view_dashboard === true;
      const canLoadReports = this.catalogs.capabilities?.can_view_course_reports === true;
      this.dashboard.loading = canLoadDashboard;
      this.reports.loading = canLoadReports;

      try {
        const requests = [];
        if (canLoadDashboard) requests.push(axios.get("/api/convivencia/dashboard", { params: appliedFilters }).then((response) => { this.dashboard.data = response.data; }));
        if (canLoadReports) requests.push(axios.get("/api/convivencia/reports/course", { params: appliedFilters }).then((response) => { this.reports.data = response.data; }));
        await Promise.all(requests);
        if (canLoadReports) this.reports.appliedFilters = appliedFilters;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo construir el análisis de convivencia."));
      } finally {
        this.dashboard.loading = false;
        this.reports.loading = false;
      }
    },
    async loadPlans() {
      await this.loadCollection("plans", "/api/convivencia/plans", this.plans.filters);
    },
    async loadCases() {
      await this.loadCollection("cases", "/api/convivencia/cases", this.cases.filters);
    },
    async loadComplaints() {
      await this.loadCollection("complaints", "/api/convivencia/complaints", this.complaints.filters);
    },
    async loadDerivations() {
      await this.loadCollection("derivations", "/api/convivencia/derivations", this.derivations.filters);
    },
    async loadProtocols() {
      this.protocols.loading = true;
      try {
        const [protocols, activations] = await Promise.all([
          axios.get("/api/convivencia/protocols", { params: this.protocols.filters }),
          axios.get("/api/convivencia/protocol-activations"),
        ]);
        this.setPaginated(this.protocols, protocols.data);
        this.protocols.activations = activations.data.data || [];
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.protocols.loading = false;
      }
    },
    async loadMeasures() {
      await this.loadCollection("measures", "/api/convivencia/measures", this.measures.filters);
    },
    async loadInterviews() {
      await this.loadCollection("interviews", "/api/convivencia/interviews", this.interviews.filters);
    },
    async loadDailyLogs() {
      await this.loadCollection("dailyLogs", "/api/convivencia/daily-logs", this.dailyLogs.filters);
    },
    async loadSociograms() {
      await this.loadCollection("sociograms", "/api/convivencia/sociograms", this.sociograms.filters);
    },
    async loadIdps(page = 1) {
      this.idps.loading = true;
      try {
        const response = await axios.get("/api/convivencia/idps", { params: { page } });
        this.idps.overview = response.data;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.idps.loading = false;
      }
    },
    async loadReports() {
      return this.loadAnalytics(this.reports.filters);
    },
    cancelReportExport() {
      this._reportExportController?.abort();
      this._reportExportController = null;
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
        ["opened_at", "received_at", "happened_at", "follow_up_due_at", "interview_at", "assigned_at", "due_at", "derived_at", "sent_at", "response_due_at", "responded_at", "closed_at"].forEach((field) => {
          if (typeof body[field] === "string" && body[field].includes("T")) body[field] = body[field].replace("T", " ");
        });
        const request = body.id ? axios.put(`${endpoint}/${body.id}`, body) : axios.post(endpoint, body);
        await request;
        await showConvivenciaSuccess(successText);
        const sectionByState = {
          plans: "planes",
          cases: "casos",
          complaints: "denuncias",
          derivations: "derivaciones",
          protocols: "protocolos",
          measures: "medidas",
          interviews: "entrevistas",
          dailyLogs: "bitacora",
          sociograms: "sociogramas",
        };
        if (sectionByState[key]) this.resetForm(sectionByState[key]);
        if (key !== "cases" || this.catalogs.capabilities?.can_view_cases === true) {
          await reload.call(this);
        }
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this[key].saving = false;
      }
    },
    async editItem(section, item) {
      if (!this.canEditItem(section, item) || !item?.id || this.recordLoadingId) return;
      const endpoint = convivenciaRecordEndpoints[section];
      const stateKey = operationalStateBySection[section];
      if (!endpoint || !stateKey) return;
      this.recordLoadingId = `${section}:${item.id}`;
      try {
        const response = await axios.get(`${endpoint}/${item.id}`);
        const detail = response.data?.data || response.data;
        this[stateKey].form = normalizeConvivenciaRecord(section, detail);
        this[stateKey].showForm = true;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo cargar el registro completo para editarlo."));
      } finally {
        this.recordLoadingId = null;
      }
    },
    async editCaseInterview(item) {
      if (this.catalogs.capabilities?.can_manage_interviews !== true || !item?.id) return;
      this.caseDetailModal = false;
      const navigation = this.$router.push('/convivencia/entrevistas');
      if (navigation?.catch) await navigation.catch(() => {});
      await this.$nextTick();
      await this.editItem('entrevistas', item);
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
    openCaseConversion(section, item) {
      if (!["denuncias", "derivaciones"].includes(section)
        || !item?.id
        || item.case_id
        || this.catalogs.capabilities?.can_create_cases !== true
        || !this.canEditItem(section, item)) return;

      const responsibleOptions = this.recordFormOptions.caseResponsibleUsers || [];
      const inheritedFollowUp = section === "derivaciones"
        && item.response_due_at
        && new Date(item.response_due_at).getTime() >= Date.now()
        ? toInputDateTime(item.response_due_at)
        : "";
      this.caseConversion.sourceType = section;
      this.caseConversion.source = item;
      this.caseConversion.form = {
        case_type_item_id: this.catalogItemId("case_type", "caso_convivencia"),
        classification_item_id: null,
        subclassification_item_id: null,
        criticality_item_id: null,
        responsible_user_id: responsibleOptions.some((option) => Number(option.value) === Number(this.catalogs.current_user_id))
          ? this.catalogs.current_user_id
          : (responsibleOptions[0]?.value || null),
        follow_up_due_at: inheritedFollowUp,
        is_sensitive: item.is_sensitive === true,
      };
      this.caseConversion.show = true;
    },
    closeCaseConversion() {
      if (this.caseConversion.saving) return;
      this.caseConversion.show = false;
      this.caseConversion.sourceType = null;
      this.caseConversion.source = null;
    },
    onCaseConversionClassificationChanged() {
      const subclassificationId = Number(this.caseConversion.form.subclassification_item_id || 0);
      if (!subclassificationId) return;
      const remainsValid = this.caseConversionSubclassifications.some((option) => Number(option.value) === subclassificationId);
      if (!remainsValid) this.caseConversion.form.subclassification_item_id = null;
    },
    async submitCaseConversion() {
      const sourceType = this.caseConversion.sourceType;
      const source = this.caseConversion.source;
      if (this.caseConversion.saving || !source?.id || !["denuncias", "derivaciones"].includes(sourceType)) return;

      const endpoint = sourceType === "denuncias" ? "complaints" : "derivations";
      this.caseConversion.saving = true;
      try {
        await axios.post(`/api/convivencia/${endpoint}/${source.id}/convert-to-case`, this.caseConversion.form);
        this.caseConversion.show = false;
        await showConvivenciaSuccess(`${sourceType === "denuncias" ? "La denuncia" : "La derivación"} fue convertida correctamente en caso.`);
        if (sourceType === "denuncias") await this.loadComplaints();
        else await this.loadDerivations();
        if (this.catalogs.capabilities?.can_view_cases === true) await this.loadCases();
        this.caseConversion.sourceType = null;
        this.caseConversion.source = null;
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.caseConversion.saving = false;
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
    async openSociogramAnalysis(item) {
      if (!item?.id || this.sociogramAnalysis.loading) return;
      this.sociogramAnalysis.show = true;
      this.sociogramAnalysis.loading = true;
      this.sociogramAnalysis.record = null;
      try {
        const response = await axios.get(`/api/convivencia/sociograms/${item.id}`);
        this.sociogramAnalysis.record = response.data?.data || null;
      } catch (error) {
        this.sociogramAnalysis.show = false;
        showConvivenciaError(formatConvivenciaError(error, "No se pudo construir el análisis gráfico del sociograma."));
      } finally {
        this.sociogramAnalysis.loading = false;
      }
    },
    closeSociogramAnalysis() {
      if (this.sociogramAnalysis.exporting) return;
      this.sociogramAnalysis.show = false;
      this.sociogramAnalysis.record = null;
    },
    async exportSociogramPdf(item = this.sociogramAnalysis.record) {
      if (!this.canExportReports || !item?.id || this.sociogramAnalysis.exporting) return;
      this.sociogramAnalysis.exporting = true;
      try {
        let record = item;
        if (!record.analysis) {
          const response = await axios.get(`/api/convivencia/sociograms/${item.id}`);
          record = response.data?.data || null;
        }
        if (!record) throw new Error("No se recibió información del sociograma.");
        this.sociogramAnalysis.record = record;
        await downloadConvivenciaSociogramPdf(record);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo generar el informe PDF del sociograma."));
      } finally {
        this.sociogramAnalysis.exporting = false;
      }
    },
    async exportCasePdf(item) {
      if (!this.canExportReports || !item?.id || this.casePdfLoadingId !== null) return;

      this.casePdfLoadingId = item.id;
      try {
        const response = await axios.get(`/api/convivencia/cases/${item.id}/export-data`);
        await downloadConvivenciaCasePdf(response.data);
      } catch (error) {
        await showConvivenciaError(formatConvivenciaError(error, "No se pudo generar la ficha PDF del caso."));
      } finally {
        this.casePdfLoadingId = null;
      }
    },
    async saveCaseFollowUp() {
      if (this.catalogs.capabilities?.can_edit_cases !== true || !this.caseDetail?.id) return;

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
      if (this.catalogs.capabilities?.can_create_cases !== true) return;
      const confirmation = await confirmConvivenciaAction({
        title: "Convertir registro en caso",
        text: "Se creará un expediente formal conservando la trazabilidad de la bitácora.",
        confirmButtonText: "Crear caso",
        icon: "question",
      });
      if (!confirmation.isConfirmed) return;
      try {
        await axios.post(`/api/convivencia/daily-logs/${item.id}/convert-to-case`, {
          classification_item_id: this.classificationOptions[1]?.value || this.classificationOptions[0]?.value,
          criticality_item_id: this.criticalityOptions[1]?.value || this.criticalityOptions[0]?.value,
          responsible_user_id: this.inspectorUserId(),
        });
        await showConvivenciaSuccess("La bitácora fue convertida correctamente en caso.");
        this.loadDailyLogs();
        if (this.catalogs.capabilities?.can_view_cases === true) this.loadCases();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      }
    },
    async convertDailyLogToDerivation(item) {
      if (this.catalogs.capabilities?.can_manage_internal_derivations !== true) return;
      const confirmation = await confirmConvivenciaAction({
        title: "Derivar registro de bitácora",
        text: "Se generará una derivación interna vinculada a este antecedente.",
        confirmButtonText: "Crear derivación",
        icon: "question",
      });
      if (!confirmation.isConfirmed) return;
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
    async saveIdpsRecord(kind) {
      const config = {
        period: { endpoint: "/api/convivencia/idps/periods", form: "periodForm", label: "Período IDPS" },
        dimension: { endpoint: "/api/convivencia/idps/dimensions", form: "dimensionForm", label: "Dimensión IDPS" },
        instrument: { endpoint: "/api/convivencia/idps/instruments", form: "instrumentForm", label: "Instrumento IDPS" },
        result: { endpoint: "/api/convivencia/idps/results", form: "resultForm", label: "Resultado IDPS" },
      }[kind];
      if (!config || this.idps.saving) return;
      const payload = JSON.parse(JSON.stringify(this.idps[config.form]));
      const id = payload.id;
      this.idps.saving = true;
      try {
        if (id) await axios.put(`${config.endpoint}/${id}`, payload);
        else await axios.post(config.endpoint, payload);
        await showConvivenciaSuccess(`${config.label} ${id ? "actualizado" : "registrado"} correctamente.`);
        this.idps.modal = null;
        await this.loadIdps(this.idps.overview?.results?.current_page || 1);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error));
      } finally {
        this.idps.saving = false;
      }
    },
    reportListMeta(dataset) {
      const preview = this.reports.data?.lists?.[dataset] || [];
      const meta = this.reports.data?.list_meta?.[dataset] || {};
      const shown = Number(meta.shown ?? preview.length);
      const total = Number(meta.total ?? shown);
      return { shown, total, truncated: meta.truncated === true || total > shown };
    },
    reportListCaption(dataset) {
      const { shown, total, truncated } = this.reportListMeta(dataset);
      if (truncated) return `Vista previa: últimos ${shown} de ${total}. La exportación incluirá los ${total} registros autorizados.`;
      return `${total} ${total === 1 ? "registro autorizado" : "registros autorizados"} para los filtros seleccionados.`;
    },
    reportExportSections(exportLists = null) {
      const data = this.reports.data || {};
      const dashboard = this.dashboard.data || {};
      const analytics = data.analytics || {};
      const lists = exportLists || data.lists || {};
      return [
        {
          title: "Resumen ejecutivo",
          headers: ["Indicador", "Resultado"],
          rows: [
            ["Clima de convivencia", data.summary?.climate?.percentage ?? "Sin dato"],
            ["Total de casos", data.summary?.total_cases ?? 0],
            ["Casos abiertos", data.summary?.open_cases ?? 0],
            ["Casos cerrados", data.summary?.closed_cases ?? 0],
            ["Tasa de cierre de casos", `${data.summary?.case_resolution_rate ?? 0}%`],
            ["Promedio de cierre de casos", data.summary?.average_case_closure_days == null ? "Sin muestra" : `${data.summary.average_case_closure_days} días`],
            ["Denuncias recibidas", data.summary?.complaints ?? 0],
            ["Denuncias convertidas en caso", `${data.summary?.complaint_conversion_rate ?? 0}%`],
            ["Conflictos registrados", data.summary?.conflicts_registered ?? 0],
            ["Atrasos", data.summary?.tardiness ?? 0],
            ["Derivaciones pendientes", data.summary?.pending_derivations ?? 0],
            ["Seguimientos de entrevistas pendientes", data.summary?.pending_interview_follow_ups ?? 0],
            ["Cumplimiento de medidas", `${data.summary?.measure_completion_rate ?? 0}%`],
            ["Medidas vencidas", data.summary?.alerts?.overdue_measures ?? 0],
            ["Cumplimiento de protocolos RICE", `${dashboard.metrics?.protocol_compliance_percentage ?? 0}%`],
            ["Etapas de protocolo vencidas", dashboard.metrics?.overdue_protocol_steps ?? 0],
            ["Partes de protocolo vencidas", dashboard.metrics?.overdue_protocol_parts ?? 0],
          ],
        },
        {
          title: "Estadísticas por curso",
          headers: ["Curso", "Nivel", "Año", "Casos", "Abiertos", "Denuncias", "Bitácora", "Derivaciones", "Entrevistas", "Medidas", "% cierre casos", "% cumplimiento medidas", "Medidas vencidas", "Actividad total"],
          rows: (analytics.courses || []).map((item) => [item.course, item.education_level, item.academic_year, item.total_cases, item.open_cases, item.complaints, item.daily_events, item.derivations, item.interviews, item.measures, item.resolution_rate, item.measure_completion_rate, item.overdue_measures, item.activity_total]),
        },
        ...[
          ["Actividad por tipo", analytics.activity_by_type],
          ["Casos por estado", analytics.cases_by_status],
          ["Casos por clasificación", analytics.cases_by_classification],
          ["Casos por criticidad", analytics.cases_by_criticality],
          ["Denuncias por estado", analytics.complaints_by_status],
          ["Derivaciones por ámbito", analytics.derivations_by_scope],
          ["Medidas por estado", analytics.measures_by_status],
        ].map(([title, rows]) => ({ title, headers: ["Categoría", "Total"], rows: (rows || []).map((item) => [item.label, item.total]) })),
        { title: "Casos", headers: ["Folio", "Fecha", "Clasificación", "Criticidad", "Estado"], rows: (lists.cases || []).map((item) => [item.folio, formatConvivenciaDate(item.opened_at), item.classification_label, item.criticality_label, humanizeConvivenciaStatus(item.status)]) },
        { title: "Denuncias", headers: ["Folio", "Fecha", "Tipo", "Denunciante", "Estado"], rows: (lists.complaints || []).map((item) => [item.folio, formatConvivenciaDate(item.received_at), item.situation_type_label, humanizeConvivenciaStatus(item.complainant_type), humanizeConvivenciaStatus(item.status)]) },
        { title: "Bitácora", headers: ["Fecha", "Tipo", "Descripción", "Estado"], rows: (lists.daily_logs || []).map((item) => [formatConvivenciaDate(item.happened_at), item.daily_log_type_label, item.description, humanizeConvivenciaStatus(item.status)]) },
        { title: "Derivaciones", headers: ["Fecha", "Ámbito", "Destino", "Estado"], rows: (lists.derivations || []).map((item) => [formatConvivenciaDate(item.derived_at), humanizeConvivenciaStatus(item.scope), item.destination_label || "-", humanizeConvivenciaStatus(item.status)]) },
        { title: "Entrevistas", headers: ["Fecha", "Motivo", "Seguimiento"], rows: (lists.interviews || []).map((item) => [formatConvivenciaDate(item.interview_at), item.motive, humanizeConvivenciaStatus(item.follow_up_status)]) },
        { title: "Medidas", headers: ["Fecha", "Tipo", "Estado"], rows: (lists.measures || []).map((item) => [formatConvivenciaDate(item.assigned_at), item.measure_type_label, humanizeConvivenciaStatus(item.status)]) },
      ];
    },
    async exportCompleteReport(format) {
      if (!this.reports.data || !this.canExportReports || this.reports.exporting) return;
      if (this.reportFiltersDirty) {
        await showConvivenciaWarning("Hay filtros sin aplicar. Actualiza el reporte antes de exportar para mantener coherentes el resumen y los listados.");
        return;
      }

      const filters = JSON.parse(JSON.stringify(this.reports.appliedFilters || {}));
      const filterSignature = JSON.stringify(filters);
      const controller = new AbortController();
      const completedDatasets = new Set();
      this._reportExportController = controller;
      this.reports.exporting = format;
      this.reports.exportProgress = 0;

      try {
        const lists = await fetchCompleteCourseReportLists({
          filters,
          signal: controller.signal,
          concurrency: 2,
          perPage: 200,
          onProgress: ({ dataset, page, lastPage }) => {
            if (page === lastPage) completedDatasets.add(dataset);
            this.reports.exportProgress = completedDatasets.size;
          },
        });

        if (filterSignature !== JSON.stringify(this.reports.appliedFilters || {})) {
          throw new Error("Los filtros cambiaron durante la exportación. Vuelve a intentarlo con el reporte actualizado.");
        }

        const sections = this.reportExportSections(lists);
        if (format === "excel") {
          downloadExcelWorkbook("informe-analitico-convivencia", sections);
        } else {
          await downloadConvivenciaAnalyticsPdf({
            dashboard: this.dashboard.data,
            report: this.reports.data,
            lists,
            filters,
            catalogs: this.catalogs,
          });
        }
        await showConvivenciaSuccess(`Reporte completo exportado en ${format === "excel" ? "Excel" : "PDF"}.`);
      } catch (error) {
        if (!controller.signal.aborted) {
          await showConvivenciaError(formatConvivenciaError(error, "No se pudo reunir el reporte completo para exportarlo."));
        }
      } finally {
        if (this._reportExportController === controller) this._reportExportController = null;
        this.reports.exporting = null;
        this.reports.exportProgress = 0;
      }
    },
    exportReportExcel() {
      return this.exportCompleteReport("excel");
    },
    exportReportPdf() {
      return this.exportCompleteReport("pdf");
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
    <div class="convivencia-page d-flex flex-column gap-3">
      <section class="convivencia-hero" aria-labelledby="convivencia-page-title">
        <div class="convivencia-hero__main">
          <div class="convivencia-hero__icon" aria-hidden="true">
            <i class="bx" :class="activeSection.icon"></i>
          </div>
          <div class="convivencia-hero__copy">
            <div class="convivencia-hero__eyebrow">
              <span>Convivencia Escolar</span>
              <i class="bx bx-chevron-right"></i>
              <span>{{ activeSection.label }}</span>
            </div>
            <h1 id="convivencia-page-title" class="convivencia-hero__title">{{ activeMeta.title }}</h1>
            <p class="convivencia-hero__subtitle">{{ activeMeta.subtitle }}</p>
          </div>
        </div>
        <div class="convivencia-hero__actions">
          <span class="convivencia-hero__status"><span></span>Módulo activo</span>
          <HelpButton :title="`Ayuda: ${activeMeta.title}`" :text="activeMeta.help" button-text="Ayuda" variant="light" />
        </div>
        <div class="convivencia-mobile-nav">
          <label for="convivencia-section">Sección</label>
          <BFormSelect
            id="convivencia-section"
            :model-value="$route.path"
            :options="visibleTabs.map((tab) => ({ value: tab.route, text: tab.label }))"
            @update:model-value="navigateSection"
          />
        </div>
      </section>

      <BAlert v-if="catalogsError" show variant="danger">{{ catalogsError }}</BAlert>
      <BCard v-if="catalogsLoading" class="border-0 shadow-sm"><LoadingState compact message="Cargando módulo de convivencia..." /></BCard>

      <template v-else>
        <template v-if="activeTab === 'dashboard'">
          <ConvivenciaAnalytics
            v-model="reports.filters"
            :data="dashboard.data"
            :report-data="reports.data"
            :loading="dashboard.loading"
            :report-loading="reports.loading"
            :catalogs="catalogs"
            :report-exporting="reports.exporting"
            :report-export-progress="reports.exportProgress"
            :report-filters-dirty="reportFiltersDirty"
            @refresh="loadAnalytics"
            @export-excel="exportReportExcel"
            @export-pdf="exportReportPdf"
          />
        </template>

        <template v-else-if="activeTab === 'planes'">
          <ConvivenciaPlanWorkspace :catalogs="catalogs" />
        </template>

        <template v-else-if="activeTab === 'casos'">
          <ConvivenciaCaseManagementWorkspace
            :selected-type="caseManagementSection"
            :states="{ cases, complaints, derivations }"
            :options="operationalFilterOptions"
            :capabilities="catalogs.capabilities"
            :can-create-provider="canCreateSection"
            :action-provider="operationalActions"
            @update:selected-type="selectCaseManagementSection"
            @create="openCreateModal"
            @refresh="refreshOperational"
            @page="loadOperationalPage"
            @action="handleOperationalAction"
          />
        </template>

        <template v-else-if="activeTab === 'protocolos'">
          <ProtocolManager
            :catalogs="catalogs"
          />
        </template>

        <template v-else-if="activeTab === 'entrevistas'">
          <ConvivenciaOperationalSection section="entrevistas" :state="interviews" :options="operationalFilterOptions" :can-create="canCreateSection('entrevistas')" :action-provider="operationalActions" @create="openCreateModal('entrevistas')" @refresh="refreshOperational('entrevistas')" @page="loadOperationalPage('entrevistas', $event)" @action="(action, item) => handleOperationalAction('entrevistas', action, item)" />
        </template>

        <template v-else-if="activeTab === 'medidas'">
          <ConvivenciaOperationalSection section="medidas" :state="measures" :options="operationalFilterOptions" :can-create="canCreateSection('medidas')" :action-provider="operationalActions" @create="openCreateModal('medidas')" @refresh="refreshOperational('medidas')" @page="loadOperationalPage('medidas', $event)" @action="(action, item) => handleOperationalAction('medidas', action, item)" />
        </template>

        <template v-else-if="activeTab === 'bitacora'">
          <ConvivenciaOperationalSection section="bitacora" :state="dailyLogs" :options="operationalFilterOptions" :can-create="canCreateSection('bitacora')" :action-provider="operationalActions" @create="openCreateModal('bitacora')" @refresh="refreshOperational('bitacora')" @page="loadOperationalPage('bitacora', $event)" @action="(action, item) => handleOperationalAction('bitacora', action, item)" />
        </template>

        <template v-else-if="activeTab === 'sociogramas'">
          <ConvivenciaOperationalSection section="sociogramas" :state="sociograms" :options="operationalFilterOptions" :can-create="canCreateSection('sociogramas')" :action-provider="operationalActions" @create="openCreateModal('sociogramas')" @refresh="refreshOperational('sociogramas')" @page="loadOperationalPage('sociogramas', $event)" @action="(action, item) => handleOperationalAction('sociogramas', action, item)" />
        </template>

        <template v-else-if="activeTab === 'idps'">
          <ConvivenciaIdpsWorkspace
            :state="idps"
            :catalogs="catalogs"
            :periods="idpsPeriods"
            :dimensions="idpsDimensions"
            :results="idpsResults"
            :can-manage="catalogs.capabilities?.can_manage_plans === true || catalogs.capabilities?.can_manage_settings === true"
            @refresh="loadIdps"
            @submit="saveIdpsRecord"
          />
        </template>

      </template>
    </div>

    <ConvivenciaFormModal
      v-if="currentOperationalState"
      :model-value="currentOperationalState.showForm"
      :title="`${currentOperationalState.form.id ? 'Editar' : 'Registrar'} ${recordFormType?.label?.toLocaleLowerCase('es-CL') || recordFormMeta.title}`"
      :eyebrow="currentOperationalState.form.id ? 'Edición de registro' : 'Nuevo registro'"
      :description="recordFormMeta.subtitle"
      :icon="recordFormIcon"
      size="xl"
      :busy="currentOperationalState.saving"
      :submit-label="recordFormSubmitLabel"
      @update:model-value="(open) => { if (!open) closeRecordModal(recordFormSection) }"
      @close="closeRecordModal(recordFormSection)"
      @submit="submitRecordModal"
    >
      <div v-if="activeTab === 'casos'" class="unified-record-picker" role="radiogroup" aria-label="Tipo de registro">
        <div class="unified-record-picker__copy">
          <span>TIPO DE REGISTRO</span>
          <b>{{ currentOperationalState.form.id ? "El tipo no cambia durante la edición" : "¿Qué necesitas registrar?" }}</b>
        </div>
        <button
          v-for="type in recordTypePickerOptions"
          :key="type.key"
          type="button"
          role="radio"
          :aria-checked="recordFormSection === type.key"
          :class="{ 'is-active': recordFormSection === type.key }"
          :disabled="Boolean(currentOperationalState.form.id)"
          @click="switchUnifiedRecordType(type.key)"
        >
          <i class="bx" :class="type.icon" aria-hidden="true"></i><span>{{ type.label }}</span><i v-if="recordFormSection === type.key" class="bx bx-check-circle" aria-hidden="true"></i>
        </button>
      </div>
      <ConvivenciaRecordForm
        :section="recordFormSection"
        :form="currentOperationalState.form"
        :options="recordFormOptions"
        @add="handleRecordFormAdd"
        @remove="removeRecordFormItem"
      />
    </ConvivenciaFormModal>

    <ConvivenciaFormModal
      :model-value="caseConversion.show"
      :title="caseConversionTitle"
      eyebrow="Nuevo expediente formal"
      :description="`El nuevo caso conservará el vínculo y los antecedentes de ${caseConversionSourceLabel}.`"
      icon="bx-folder-plus"
      size="lg"
      :busy="caseConversion.saving"
      submit-label="Crear caso vinculado"
      @update:model-value="(open) => { if (!open) closeCaseConversion() }"
      @close="closeCaseConversion"
      @submit="submitCaseConversion"
    >
      <div class="case-conversion-guide">
        <span><i class="bx bx-git-branch"></i></span>
        <div>
          <small>REGISTRO DE ORIGEN</small>
          <b>{{ caseConversionSourceLabel }}</b>
          <p>Se copiarán estudiante, curso, fechas, relato, medidas y seguimiento disponibles. El registro original seguirá accesible y quedará enlazado al caso.</p>
        </div>
      </div>
      <div class="case-conversion-form">
        <div class="case-conversion-form__field">
          <label class="form-label" for="conversion-case-type">Tipo de caso</label>
          <BFormSelect id="conversion-case-type" v-model="caseConversion.form.case_type_item_id" :options="recordFormOptions.caseTypes" />
        </div>
        <div class="case-conversion-form__field">
          <label class="form-label" for="conversion-classification">Clasificación <span class="required-mark">*</span></label>
          <BFormSelect id="conversion-classification" v-model="caseConversion.form.classification_item_id" :options="[{ value: null, text: 'Selecciona una clasificación', disabled: true }].concat(classificationOptions)" required @change="onCaseConversionClassificationChanged" />
        </div>
        <div class="case-conversion-form__field">
          <label class="form-label" for="conversion-subclassification">Subclasificación</label>
          <BFormSelect id="conversion-subclassification" v-model="caseConversion.form.subclassification_item_id" :options="caseConversionSubclassifications" :disabled="!caseConversion.form.classification_item_id" />
        </div>
        <div class="case-conversion-form__field">
          <label class="form-label" for="conversion-criticality">Criticidad <span class="required-mark">*</span></label>
          <BFormSelect id="conversion-criticality" v-model="caseConversion.form.criticality_item_id" :options="[{ value: null, text: 'Selecciona la criticidad', disabled: true }].concat(criticalityOptions)" required />
        </div>
        <div class="case-conversion-form__field case-conversion-form__field--wide">
          <label class="form-label" for="conversion-responsible">Responsable de la gestión <span class="required-mark">*</span></label>
          <BFormSelect id="conversion-responsible" v-model="caseConversion.form.responsible_user_id" :options="[{ value: null, text: 'Selecciona una persona responsable', disabled: true }].concat(recordFormOptions.caseResponsibleUsers)" required />
          <small>La ficha institucional asociada se vinculará automáticamente.</small>
        </div>
        <div class="case-conversion-form__field">
          <label class="form-label" for="conversion-follow-up">Próximo seguimiento</label>
          <BFormInput id="conversion-follow-up" v-model="caseConversion.form.follow_up_due_at" type="datetime-local" />
        </div>
        <div class="case-conversion-form__sensitive">
          <BFormCheckbox v-model="caseConversion.form.is_sensitive" switch><b>Expediente con información sensible</b></BFormCheckbox>
          <small>Conserva la protección del registro de origen y permite reforzarla antes de crear el caso.</small>
        </div>
      </div>
    </ConvivenciaFormModal>

    <ConvivenciaFormModal
      :model-value="sociogramAnalysis.show"
      title="Análisis gráfico del sociograma"
      eyebrow="Red relacional del curso"
      description="Explora nominaciones, reciprocidad, cobertura y patrones descriptivos sin perder el contexto profesional."
      icon="bx-network-chart"
      size="xxl"
      :busy="sociogramAnalysis.exporting"
      :hide-footer="true"
      :formless="true"
      @update:model-value="(open) => { if (!open) closeSociogramAnalysis() }"
      @close="closeSociogramAnalysis"
    >
      <ConvivenciaSociogramAnalysis
        :record="sociogramAnalysis.record"
        :loading="sociogramAnalysis.loading"
        :can-export="canExportReports"
        :exporting="sociogramAnalysis.exporting"
        @export="exportSociogramPdf()"
      />
    </ConvivenciaFormModal>

    <BModal
      v-model="caseDetailModal"
      size="xl"
      title="Detalle y seguimiento del caso"
      modal-class="convivencia-detail-modal"
      content-class="convivencia-detail-modal__content"
      header-class="convivencia-detail-modal__header"
      body-class="convivencia-detail-modal__body"
      hide-footer
      scrollable
    >
      <div v-if="caseDetail" class="case-detail d-flex flex-column gap-3">
        <div class="case-detail__hero">
          <div class="case-detail__hero-icon"><i class="bx bx-folder-open"></i></div>
          <div class="case-detail__hero-copy">
            <div class="case-detail__eyebrow">Caso de convivencia · {{ caseDetail.folio }}</div>
            <h5>{{ caseDetail.student?.registered_name_resolved || caseDetail.student?.registered_name || "Estudiante sin identificar" }}</h5>
            <div class="case-detail__meta">
              <span><i class="bx bx-calendar"></i>{{ formatDateTime(caseDetail.opened_at) }}</span>
              <span><i class="bx bx-group"></i>{{ caseDetail.course_section?.display_name || caseDetail.courseSection?.display_name || "Sin curso" }}</span>
            </div>
          </div>
          <div class="case-detail__hero-actions">
            <StatusBadge :status="caseDetail.status" />
            <button
              v-if="canExportReports"
              type="button"
              class="case-detail__pdf-button"
              :disabled="casePdfLoadingId !== null"
              @click="exportCasePdf(caseDetail)"
            >
              <i class="bx" :class="casePdfLoadingId === caseDetail.id ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'" aria-hidden="true"></i>
              {{ casePdfLoadingId === caseDetail.id ? "Preparando…" : "Descargar ficha PDF" }}
            </button>
          </div>
        </div>
        <BCard class="case-detail__section border-0 shadow-sm">
          <div class="case-detail__section-title"><i class="bx bx-file"></i><h6>Relato y medidas</h6></div>
          <div class="small text-muted mb-1">Relato inicial</div>
          <div class="case-detail__text mb-3">{{ caseDetail.initial_report }}</div>
          <div class="small text-muted mb-1">Antecedentes</div>
          <div class="case-detail__text mb-3">{{ caseDetail.background || "-" }}</div>
          <div class="small text-muted mb-1">Medidas inmediatas</div>
          <div class="case-detail__text">{{ caseDetail.immediate_measures || "-" }}</div>
        </BCard>
        <BCard class="case-detail__section case-detail__interviews border-0 shadow-sm">
          <div class="case-detail__section-title"><i class="bx bx-conversation"></i><h6>Actas de entrevista</h6><span class="case-detail__count">{{ caseDetail.interviews?.length || 0 }}</span></div>
          <div v-if="caseDetail.interviews?.length" class="case-interview-list">
            <article v-for="interview in caseDetail.interviews" :key="`case-interview-${interview.id}`" class="case-interview-card">
              <span class="case-interview-card__icon"><i class="bx bx-message-rounded-detail"></i></span>
              <div>
                <b>{{ interview.interview_type_label || 'Entrevista de convivencia' }}</b>
                <small>{{ formatDateTime(interview.interview_at) }} · {{ interview.responsible_user?.name || 'Sin responsable' }}</small>
                <p>{{ interview.motive || 'Sin motivo visible.' }}</p>
              </div>
              <div class="case-interview-card__actions">
                <StatusBadge :status="interview.follow_up_status" />
                <button v-if="catalogs.capabilities?.can_manage_interviews === true" type="button" class="case-interview-card__edit" @click="editCaseInterview(interview)"><i class="bx bx-edit-alt"></i>Editar acta</button>
              </div>
            </article>
          </div>
          <div v-else class="case-detail__empty">Este expediente aún no tiene actas de entrevista asociadas.</div>
        </BCard>
        <div class="row g-3">
          <div class="col-lg-6">
            <BCard class="case-detail__section border-0 shadow-sm h-100">
              <div class="case-detail__section-title"><i class="bx bx-support"></i><h6>Equipo de apoyo</h6><span class="case-detail__count">{{ caseDetailSupportTeam.length }}</span></div>
              <div v-if="caseDetailSupportTeam.length" class="case-detail__people">
                <article v-for="person in caseDetailSupportTeam" :key="`support-${person.id}`" class="case-detail__person case-detail__person--support">
                  <span><i class="bx bx-plus-medical"></i></span>
                  <div><b>{{ person.full_name }}</b><small>{{ person.relationship_label || "Profesional de apoyo" }}</small></div>
                </article>
              </div>
              <div v-else class="case-detail__empty">No hay profesionales de apoyo asignados.</div>
            </BCard>
          </div>
          <div class="col-lg-6">
            <BCard class="case-detail__section border-0 shadow-sm h-100">
              <div class="case-detail__section-title"><i class="bx bx-group"></i><h6>Personas vinculadas</h6><span class="case-detail__count">{{ caseDetailInvolvedPeople.length }}</span></div>
              <div v-if="caseDetailInvolvedPeople.length" class="case-detail__people">
                <article v-for="person in caseDetailInvolvedPeople" :key="`person-${person.id}`" class="case-detail__person">
                  <span><i class="bx bx-user"></i></span>
                  <div><b>{{ person.full_name }}</b><small>{{ statusText(person.role_type) }}<template v-if="person.identifier"> · {{ person.identifier }}</template></small></div>
                </article>
              </div>
              <div v-else class="case-detail__empty">No hay otras personas vinculadas.</div>
            </BCard>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-lg-6">
            <BCard class="case-detail__section border-0 shadow-sm h-100">
              <div class="case-detail__section-title"><i class="bx bx-time-five"></i><h6>Seguimientos</h6></div>
              <ConvivenciaTimeline :items="caseDetail.follow_ups || []" empty-text="No hay seguimientos registrados para este caso." />
            </BCard>
          </div>
          <div class="col-lg-6">
            <BCard class="case-detail__section border-0 shadow-sm h-100">
              <div class="case-detail__section-title"><i class="bx bx-history"></i><h6>Cambios de estado</h6></div>
              <ConvivenciaTimeline :items="caseDetail.status_logs || []" empty-text="No hay cambios de estado registrados." />
            </BCard>
          </div>
        </div>
        <BCard v-if="catalogs.capabilities?.can_edit_cases === true" class="case-detail__section case-detail__section--accent border-0 shadow-sm">
          <div class="case-detail__section-title"><i class="bx bx-plus-circle"></i><h6>Registrar seguimiento</h6></div>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="caseFollowUpForm.follow_up_at" type="datetime-local" /></div>
            <div class="col-md-4"><label class="form-label">Título</label><BFormInput v-model="caseFollowUpForm.title" /></div>
            <div class="col-md-4"><label class="form-label">Próximo seguimiento</label><BFormInput v-model="caseFollowUpForm.next_follow_up_at" type="datetime-local" /></div>
            <div class="col-12"><label class="form-label">Notas</label><BFormTextarea v-model="caseFollowUpForm.notes" rows="3" /></div>
            <div class="col-12 d-flex justify-content-end"><BButton variant="success" :disabled="caseFollowUpSaving" @click="saveCaseFollowUp"><i class="bx bx-check me-1"></i>Guardar seguimiento</BButton></div>
          </div>
        </BCard>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.convivencia-page {
  --convivencia-primary: #4f63d9;
  --convivencia-primary-dark: #3347b4;
  --convivencia-teal: #2e9f83;
  --convivencia-border: #e3e9f2;
  --convivencia-muted: #6b7890;
  --convivencia-soft: #f5f7fb;
  color: var(--bs-body-color);
}

.convivencia-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  min-height: 152px;
  padding: 1.65rem 1.75rem;
  overflow: hidden;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  background:
    radial-gradient(circle at 85% 10%, rgba(255, 255, 255, 0.2), transparent 28%),
    linear-gradient(125deg, #263a8f 0%, var(--convivencia-primary) 54%, #318e89 100%);
  box-shadow: 0 18px 42px rgba(43, 59, 132, 0.2);
}

.convivencia-hero::after {
  position: absolute;
  right: -65px;
  bottom: -105px;
  width: 260px;
  height: 260px;
  content: "";
  border: 40px solid rgba(255, 255, 255, 0.07);
  border-radius: 50%;
}

.convivencia-hero__main,
.convivencia-hero__actions {
  position: relative;
  z-index: 1;
}

.convivencia-hero__main {
  display: flex;
  align-items: center;
  gap: 1.15rem;
  min-width: 0;
}

.convivencia-hero__icon {
  display: grid;
  flex: 0 0 64px;
  width: 64px;
  height: 64px;
  font-size: 1.85rem;
  color: #fff;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.14);
  box-shadow: inset 0 1px rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
}

.convivencia-hero__copy {
  min-width: 0;
}

.convivencia-hero__eyebrow {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-bottom: 0.35rem;
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  opacity: 0.78;
}

.convivencia-hero__title {
  margin: 0;
  color: #fff;
  font-size: clamp(1.45rem, 2.2vw, 2rem);
  font-weight: 700;
  letter-spacing: -0.025em;
}

.convivencia-hero__subtitle {
  max-width: 760px;
  margin: 0.45rem 0 0;
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.94rem;
  line-height: 1.55;
}

.convivencia-hero__actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.75rem;
}

.convivencia-hero__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 0.8rem;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  background: rgba(12, 24, 71, 0.2);
}

.convivencia-hero__status > span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #73e6b9;
  box-shadow: 0 0 0 4px rgba(115, 230, 185, 0.14);
}

.convivencia-hero :deep(.convivencia-help-button) {
  min-height: 38px;
  color: #263a8f;
  font-weight: 700;
  border: 0;
  background: rgba(255, 255, 255, 0.92);
}

.convivencia-mobile-nav {
  position: relative;
  z-index: 1;
  display: none;
  width: 100%;
}

.convivencia-mobile-nav label {
  margin-bottom: 0.35rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.78);
}

.convivencia-mobile-nav :deep(.form-select) {
  color: #263a8f;
  border: 0;
  background-color: rgba(255, 255, 255, 0.95);
}

.convivencia-page :deep(.card) {
  border: 1px solid var(--convivencia-border) !important;
  border-radius: 16px;
  background: var(--bs-body-bg);
  box-shadow: 0 8px 24px rgba(42, 48, 66, 0.06) !important;
}

.convivencia-page :deep(.card-body) {
  padding: 1.25rem;
}

.convivencia-page :deep(.form-label) {
  margin-bottom: 0.42rem;
  color: var(--bs-body-color);
  font-size: 0.78rem;
  font-weight: 700;
}

.convivencia-page :deep(.form-control),
.convivencia-page :deep(.form-select) {
  min-height: 42px;
  border-color: #dce3ed;
  border-radius: 10px;
  background-color: var(--bs-body-bg);
  box-shadow: none;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.convivencia-page :deep(textarea.form-control) {
  min-height: auto;
}

.convivencia-page :deep(.form-control:focus),
.convivencia-page :deep(.form-select:focus) {
  border-color: rgba(79, 99, 217, 0.7);
  box-shadow: 0 0 0 3px rgba(79, 99, 217, 0.12);
}

.convivencia-page :deep(.btn) {
  min-height: 40px;
  padding-inline: 0.95rem;
  font-weight: 650;
  border-radius: 10px;
  transition: transform 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease;
}

.convivencia-page :deep(.btn:hover:not(:disabled)) {
  transform: translateY(-1px);
}

.convivencia-page :deep(.btn-primary) {
  border-color: var(--convivencia-primary);
  background: var(--convivencia-primary);
  box-shadow: 0 7px 16px rgba(79, 99, 217, 0.2);
}

.convivencia-page :deep(.btn-primary:hover) {
  border-color: var(--convivencia-primary-dark);
  background: var(--convivencia-primary-dark);
}

.convivencia-page :deep(.btn-success) {
  border-color: #258a70;
  background: #258a70;
  box-shadow: 0 7px 16px rgba(37, 138, 112, 0.18);
}

.convivencia-page :deep(.btn-sm) {
  min-height: 34px;
  padding: 0.38rem 0.7rem;
  font-size: 0.77rem;
}

.convivencia-page :deep(.table-responsive) {
  overflow-x: auto;
  border: 1px solid var(--convivencia-border);
  border-radius: 12px;
}

.convivencia-page :deep(.table) {
  min-width: 720px;
  margin-bottom: 0;
  vertical-align: middle;
}

.convivencia-page :deep(.table > :not(caption) > * > *) {
  padding: 0.9rem 0.95rem;
  border-bottom-color: var(--convivencia-border);
}

.convivencia-page :deep(.table thead th) {
  color: var(--convivencia-muted);
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  white-space: nowrap;
  text-transform: uppercase;
  border-bottom-width: 1px;
  background: var(--convivencia-soft);
}

.convivencia-page :deep(.table tbody tr) {
  transition: background-color 0.16s ease;
}

.convivencia-page :deep(.table tbody tr:hover) {
  background: rgba(79, 99, 217, 0.035);
}

.convivencia-page :deep(.table tbody tr:last-child td) {
  border-bottom: 0;
}

.convivencia-page :deep(.table td:last-child) {
  min-width: 150px;
  white-space: nowrap;
}

.convivencia-page :deep(.badge) {
  padding: 0.42rem 0.68rem;
  font-weight: 700;
}

.convivencia-page :deep(.bg-light-subtle) {
  background: var(--convivencia-soft) !important;
}

.convivencia-empty-state {
  border-style: dashed !important;
  background: var(--convivencia-soft) !important;
}

.convivencia-empty-state__icon {
  display: grid;
  width: 52px;
  height: 52px;
  margin: 0 auto;
  color: var(--convivencia-primary);
  font-size: 1.45rem;
  place-items: center;
  border-radius: 15px;
  background: rgba(79, 99, 217, 0.1);
}

.convivencia-report-lists {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.unified-record-picker {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) repeat(3, minmax(120px, 0.7fr));
  align-items: stretch;
  gap: 0.55rem;
  padding: 0.7rem;
  border: 1px solid #dce3f1;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8faff, #f4f7ff);
}

.unified-record-picker__copy {
  display: flex;
  justify-content: center;
  flex-direction: column;
  padding: 0 0.35rem;
}

.unified-record-picker__copy span {
  color: #7180a1;
  font-size: 0.52rem;
  font-weight: 850;
  letter-spacing: 0.1em;
}

.unified-record-picker__copy b {
  margin-top: 0.12rem;
  color: #30406c;
  font-size: 0.67rem;
}

.unified-record-picker > button {
  display: flex;
  min-height: 48px;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.5rem 0.6rem;
  color: #526078;
  font-size: 0.64rem;
  font-weight: 800;
  border: 1px solid #dce2ec;
  border-radius: 11px;
  background: #fff;
  box-shadow: 0 4px 10px rgba(35, 48, 80, 0.04);
  transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.unified-record-picker > button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #aebaf0;
  box-shadow: 0 7px 15px rgba(53, 70, 155, 0.1);
}

.unified-record-picker > button:focus-visible {
  outline: 3px solid rgba(79, 99, 217, 0.18);
  outline-offset: 2px;
}

.unified-record-picker > button > i:first-child {
  color: #5368d5;
  font-size: 1rem;
}

.unified-record-picker > button > i:last-child {
  color: #38a283;
  font-size: 0.85rem;
}

.unified-record-picker > button.is-active {
  color: #fff;
  border-color: #4f63d9;
  background: linear-gradient(135deg, #4f63d9, #4053bf);
  box-shadow: 0 8px 17px rgba(63, 81, 193, 0.2);
}

.unified-record-picker > button.is-active > i {
  color: #fff;
}

.unified-record-picker > button:disabled {
  cursor: default;
}

.case-conversion-guide {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem;
  color: #fff;
  border-radius: 14px;
  background: radial-gradient(circle at 92% 10%, rgba(90, 213, 177, 0.3), transparent 35%), linear-gradient(130deg, #2c429f, #5369d8);
  box-shadow: 0 10px 24px rgba(44, 66, 159, 0.15);
}

.case-conversion-guide > span {
  display: grid;
  width: 42px;
  height: 42px;
  font-size: 1.15rem;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.12);
}

.case-conversion-guide div {
  display: grid;
  gap: 0.08rem;
}

.case-conversion-guide small {
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.53rem;
  font-weight: 850;
  letter-spacing: 0.11em;
}

.case-conversion-guide b {
  font-size: 0.78rem;
}

.case-conversion-guide p {
  margin: 0.15rem 0 0;
  color: rgba(255, 255, 255, 0.78);
  font-size: 0.62rem;
  line-height: 1.45;
}

.case-conversion-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
  margin-top: 0.85rem;
  padding: 0.95rem;
  border: 1px solid #e0e5ef;
  border-radius: 14px;
  background: #fbfcff;
}

.case-conversion-form__field {
  min-width: 0;
}

.case-conversion-form__field--wide,
.case-conversion-form__sensitive {
  grid-column: 1 / -1;
}

.case-conversion-form__field > small,
.case-conversion-form__sensitive > small {
  display: block;
  margin-top: 0.25rem;
  color: #7b8699;
  font-size: 0.58rem;
}

.case-conversion-form__sensitive {
  padding: 0.7rem;
  border: 1px solid #dce5f4;
  border-radius: 11px;
  background: #f3f7ff;
}

@media (max-width: 1199.98px) {
  .convivencia-hero {
    align-items: flex-start;
  }

  .convivencia-hero__actions {
    flex-direction: column;
    align-items: flex-end;
  }
}

@media (max-width: 991.98px) {
  .convivencia-hero {
    flex-wrap: wrap;
  }

  .convivencia-mobile-nav {
    display: block;
  }

  .convivencia-report-lists {
    grid-template-columns: 1fr;
  }

  .unified-record-picker {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .unified-record-picker__copy {
    grid-column: 1 / -1;
  }
}

@media (max-width: 767.98px) {
  .case-conversion-form {
    grid-template-columns: 1fr;
  }

  .case-conversion-form__field--wide,
  .case-conversion-form__sensitive {
    grid-column: auto;
  }

  .unified-record-picker {
    grid-template-columns: 1fr;
  }

  .unified-record-picker__copy {
    grid-column: auto;
  }
  .convivencia-hero {
    min-height: auto;
    padding: 1.25rem;
    border-radius: 16px;
  }

  .convivencia-hero__main {
    align-items: flex-start;
  }

  .convivencia-hero__icon {
    flex-basis: 50px;
    width: 50px;
    height: 50px;
    font-size: 1.45rem;
    border-radius: 14px;
  }

  .convivencia-hero__actions {
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }

  .convivencia-page :deep(.card-body) {
    padding: 1rem;
  }

  .convivencia-page :deep(.btn) {
    white-space: nowrap;
  }
}

@media (prefers-reduced-motion: reduce) {
  .convivencia-page :deep(.btn),
  .convivencia-page :deep(.table tbody tr) {
    transition: none;
  }
}
</style>

<style>
.convivencia-detail-modal .modal-dialog {
  max-width: min(1120px, calc(100vw - 2rem));
}

.convivencia-detail-modal__content {
  overflow: hidden;
  border: 0;
  border-radius: 20px;
  box-shadow: 0 28px 80px rgba(25, 34, 78, 0.28);
}

.convivencia-detail-modal__header {
  padding: 1.1rem 1.35rem;
  border-bottom: 1px solid #e7ebf2;
  background: #f8f9fc;
}

.convivencia-detail-modal__header .modal-title {
  color: #273252;
  font-size: 1rem;
  font-weight: 750;
}

.convivencia-detail-modal__body {
  padding: 1.35rem;
  background: #f4f6fa;
}

.case-detail__hero {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.15rem 1.25rem;
  color: #fff;
  border-radius: 16px;
  background: linear-gradient(125deg, #2b3f96, #5368db 58%, #34958c);
}

.case-detail__hero-icon {
  display: grid;
  flex: 0 0 52px;
  width: 52px;
  height: 52px;
  font-size: 1.5rem;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.14);
}

.case-detail__hero-copy {
  flex: 1;
  min-width: 0;
}

.case-detail__eyebrow {
  margin-bottom: 0.2rem;
  font-size: 0.7rem;
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.75;
}

.case-detail__hero h5 {
  margin: 0;
  color: #fff;
  font-size: 1.15rem;
}

.case-detail__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  margin-top: 0.45rem;
  color: rgba(255, 255, 255, 0.78);
  font-size: 0.78rem;
}

.case-detail__meta span {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.case-detail__hero .badge {
  color: #273252 !important;
  background: rgba(255, 255, 255, 0.92) !important;
}

.case-detail__hero-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: flex-end;
  flex-direction: column;
  gap: 0.55rem;
}

.case-detail__pdf-button {
  display: inline-flex;
  min-height: 36px;
  align-items: center;
  justify-content: center;
  gap: 0.42rem;
  padding: 0.55rem 0.8rem;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 780;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.14);
  box-shadow: 0 7px 18px rgba(22, 32, 84, 0.16);
  backdrop-filter: blur(8px);
  transition: transform 0.16s ease, background 0.16s ease;
}

.case-detail__pdf-button:hover:not(:disabled) {
  transform: translateY(-1px);
  background: rgba(255, 255, 255, 0.23);
}

.case-detail__pdf-button:disabled {
  cursor: wait;
  opacity: 0.65;
}

.case-detail__section {
  border: 1px solid #e3e8f1 !important;
  border-radius: 15px;
  box-shadow: 0 7px 22px rgba(42, 48, 66, 0.05) !important;
}

.case-detail__section--accent {
  border-top: 3px solid #2e9f83 !important;
}

.case-detail__section-title {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin-bottom: 1rem;
  color: #344162;
}

.case-detail__section-title i {
  display: grid;
  width: 32px;
  height: 32px;
  color: #4f63d9;
  font-size: 1.05rem;
  place-items: center;
  border-radius: 9px;
  background: rgba(79, 99, 217, 0.1);
}

.case-detail__section-title h6 {
  margin: 0;
  font-weight: 750;
}

.case-detail__text {
  line-height: 1.65;
}

.case-interview-list {
  display: grid;
  gap: 0.65rem;
}

.case-interview-card {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid #dce6ef;
  border-radius: 12px;
  background: linear-gradient(135deg, #fbfdff, #f7fbfa);
}

.case-interview-card__icon {
  display: grid;
  width: 38px;
  height: 38px;
  color: #27856c;
  font-size: 1.15rem;
  place-items: center;
  border-radius: 11px;
  background: #e8f7f2;
}

.case-interview-card > div:nth-child(2) {
  display: grid;
  min-width: 0;
  gap: 0.12rem;
}

.case-interview-card b { color: #344162; font-size: 0.8rem; }
.case-interview-card small { color: #7b8799; font-size: 0.66rem; }
.case-interview-card p { margin: 0.2rem 0 0; color: #56647b; font-size: 0.72rem; }
.case-interview-card__actions { display: flex; align-items: center; gap: 0.55rem; }
.case-interview-card__edit {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.42rem 0.62rem;
  color: #3158b4;
  font-size: 0.66rem;
  font-weight: 780;
  border: 1px solid #cfd9f4;
  border-radius: 9px;
  background: #f3f6ff;
}
.case-interview-card__edit:hover,
.case-interview-card__edit:focus-visible {
  color: #243f8b;
  border-color: #98ace3;
  box-shadow: 0 5px 13px rgba(49, 88, 180, 0.11);
}

.case-detail__section-title .case-detail__count {
  display: grid;
  min-width: 24px;
  height: 24px;
  margin-left: auto;
  padding: 0 0.4rem;
  color: #4f63d9;
  font-size: 0.7rem;
  font-weight: 800;
  place-items: center;
  border-radius: 999px;
  background: rgba(79, 99, 217, 0.1);
}

.case-detail__people {
  display: grid;
  gap: 0.55rem;
}

.case-detail__person {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.6rem;
  padding: 0.65rem 0.7rem;
  border: 1px solid #e3e8f1;
  border-radius: 11px;
  background: #fafbfe;
}

.case-detail__person > span {
  display: grid;
  width: 34px;
  height: 34px;
  color: #4f63d9;
  place-items: center;
  border-radius: 10px;
  background: rgba(79, 99, 217, 0.1);
}

.case-detail__person--support {
  border-color: #d7e9e3;
  background: #f6fcfa;
}

.case-detail__person--support > span {
  color: #27856c;
  background: rgba(39, 133, 108, 0.1);
}

.case-detail__person div {
  display: grid;
  min-width: 0;
  gap: 0.08rem;
}

.case-detail__person b {
  overflow: hidden;
  color: #344162;
  font-size: 0.78rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.case-detail__person small,
.case-detail__empty {
  color: #7a869a;
  font-size: 0.68rem;
}

.case-detail__empty {
  padding: 0.75rem;
  text-align: center;
  border: 1px dashed #dce2ec;
  border-radius: 10px;
  background: #fafbfe;
}

.swal2-popup.convivencia-swal {
  padding: 1.75rem;
  border-radius: 18px;
  box-shadow: 0 24px 70px rgba(25, 34, 78, 0.24);
}

.convivencia-swal .swal2-title {
  color: #273252;
  font-size: 1.35rem;
}

.convivencia-swal .swal2-html-container {
  color: #667085;
  font-size: 0.92rem;
  line-height: 1.55;
}

.convivencia-swal-confirm,
.convivencia-swal-cancel {
  min-height: 42px;
  padding: 0.65rem 1rem;
  font-weight: 700;
  border: 0 !important;
  border-radius: 10px !important;
}

.convivencia-swal-confirm {
  background: #4f63d9 !important;
}

.convivencia-swal-cancel {
  color: #59657a !important;
  background: #edf0f5 !important;
}

@media (max-width: 575.98px) {
  .convivencia-detail-modal .modal-dialog {
    max-width: calc(100vw - 1rem);
    margin: 0.5rem;
  }

  .convivencia-detail-modal__body {
    padding: 0.85rem;
  }

  .case-detail__hero {
    align-items: flex-start;
    padding: 1rem;
  }

  .case-detail__hero-icon {
    display: none;
  }

  .case-detail__hero-actions {
    width: 100%;
    align-items: stretch;
  }

  .case-interview-card {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .case-interview-card__actions {
    grid-column: 1 / -1;
    justify-content: space-between;
  }
}
</style>
