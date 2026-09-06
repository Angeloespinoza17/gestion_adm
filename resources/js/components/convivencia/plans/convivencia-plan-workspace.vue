<script>
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import Swal from "sweetalert2";
import convivenciaPlanApi from "../../../services/convivencia-plan-api";
import ConvivenciaDataTable from "../ui/convivencia-data-table.vue";
import ConvivenciaFormModal from "../ui/convivencia-form-modal.vue";
import ConvivenciaRowActions from "../ui/convivencia-row-actions.vue";
import { downloadConvivenciaPlanPdf } from "../pdf/convivencia-plan-pdf";

const nowYear = new Date().getFullYear();
const pad = (value) => String(value).padStart(2, "0");
const inputDateTime = (value) => {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value).slice(0, 16);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};
const clone = (value) => JSON.parse(JSON.stringify(value ?? null));

const blankPlan = (year, catalogs = {}) => ({
  id: null,
  revision: null,
  calendar_year: year,
  academic_year_id: catalogs.academic_years?.find((item) => Number(item.year) === Number(year))?.id || catalogs.active_academic_year_id || null,
  responsible_user_id: catalogs.current_user_id || null,
  responsible_staff_id: catalogs.current_staff_id || null,
  name: `Plan de Gestión de la Convivencia Escolar ${year}`,
  general_objective: "",
  specific_objectives: [],
  resources_required: "",
  indicators_summary: "",
  verification_means_summary: "",
  status: "borrador",
  starts_on: `${year}-03-01`,
  ends_on: `${year}-12-31`,
  observations: "",
  final_evaluation: "",
  institutional_protocol: [],
  evaluation_indicators: [],
  regulatory_linkage_text: "",
  regulatory_review_required: false,
  is_sensitive: false,
  change_summary: "Versión inicial del plan.",
});

const blankAction = () => ({
  id: null,
  action_type: "preventiva",
  title: "",
  objective: "",
  description: "",
  target_audience: "",
  planned_month: null,
  date_precision: "month",
  weight_percent: 0,
  starts_on: "",
  ends_on: "",
  status: "planificada",
  dimension_item_id: null,
  responsible_user_id: null,
  responsible_staff_id: null,
  responsible_department_id: null,
  responsible_label: "",
  required_resources: "",
  indicator_summary: "",
  verification_means: "",
  observations: "",
  evidence_summary: "",
  change_summary: "",
});

const blankActivity = () => ({
  id: null,
  revision: null,
  activity_type_item_id: null,
  title: "",
  description: "",
  starts_at: "",
  ends_at: "",
  status: "programada",
  contribution_percent: 0,
  original_contribution_percent: 0,
  completion_percent: 0,
  location: "",
  target_audience: "",
  attendee_count: null,
  results: "",
});

export default {
  name: "ConvivenciaPlanWorkspace",
  components: { FullCalendar, ConvivenciaDataTable, ConvivenciaFormModal, ConvivenciaRowActions },
  props: { catalogs: { type: Object, default: () => ({}) } },
  data() {
    return {
      loading: false,
      saving: false,
      exporting: false,
      error: "",
      selectedYear: nowYear,
      plan: null,
      availableYears: [],
      statusOptions: { plans: [], actions: [], activities: [], action_types: [], activity_types: [] },
      capabilities: {},
      activeView: "overview",
      actionSearch: "",
      actionStatus: "",
      selectedAction: null,
      detailLoading: false,
      modal: null,
      planForm: blankPlan(nowYear),
      cloneForm: { target_year: nowYear + 1, academic_year_id: null },
      actionForm: blankAction(),
      activityForm: blankActivity(),
      evidenceForm: { activity_id: null, category: "evidencia", confidentiality_level: "general", notes: "", document: null },
      planDocumentForm: { category: "informe", confidentiality_level: "interna", notes: "Documento de respaldo del plan anual.", document: null },
    };
  },
  computed: {
    yearOptions() {
      const years = new Set([this.selectedYear, nowYear, ...this.availableYears]);
      return [...years].filter(Boolean).sort((a, b) => b - a).map((year) => ({ value: year, text: String(year) }));
    },
    months() {
      return ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    },
    monthOptions() {
      return [{ value: null, text: "Selecciona un mes" }, ...this.months.map((text, index) => ({ value: index + 1, text }))];
    },
    planStatusOptions() { return this.optionList(this.statusOptions.plans); },
    actionStatusOptions() { return this.optionList(this.statusOptions.actions); },
    activityStatusOptions() { return this.optionList(this.statusOptions.activities); },
    actionTypeOptions() { return this.optionList(this.statusOptions.action_types); },
    activityTypeOptions() {
      return (this.statusOptions.activity_types || []).map((item) => ({
        value: item.id,
        text: item.name,
        code: item.code,
        color: item.color || "#4f63d9",
        icon: item.metadata?.icon || "bx-calendar-event",
      }));
    },
    userOptions() { return [{ value: null, text: "Por definir" }, ...(this.catalogs.users || []).map((item) => ({ value: item.id, text: item.name }))]; },
    staffOptions() { return [{ value: null, text: "Sin ficha asociada" }, ...(this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))]; },
    departmentOptions() { return [{ value: null, text: "Sin departamento" }, ...(this.catalogs.departments || []).map((item) => ({ value: item.id, text: item.name }))]; },
    dimensionOptions() {
      return [{ value: null, text: "Sin dimensión" }, ...((this.catalogs.catalogs?.plan_dimension || []).map((item) => ({ value: item.id, text: item.name })) )];
    },
    academicYearOptions() { return (this.catalogs.academic_years || []).map((item) => ({ value: item.id, text: item.name || item.year })); },
    actionWeightRemaining() { return Math.max(0, 100 - Number(this.plan?.stats?.action_weight_allocated || 0)); },
    activityContributionRemaining() {
      return Math.max(0, 100 - Number(this.selectedAction?.allocated_contribution || 0) + Number(this.activityForm?.original_contribution_percent || 0));
    },
    filteredActions() {
      const search = this.actionSearch.trim().toLocaleLowerCase("es-CL");
      return (this.plan?.actions || []).filter((action) => {
        const matchesStatus = !this.actionStatus || action.status === this.actionStatus;
        const haystack = `${action.title || ""} ${action.target_audience || ""} ${action.responsible_label || ""}`.toLocaleLowerCase("es-CL");
        return matchesStatus && (!search || haystack.includes(search));
      });
    },
    calendarOptions() {
      return {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
        initialDate: `${this.selectedYear}-03-01`,
        height: "auto",
        firstDay: 1,
        headerToolbar: { left: "prev,next today", center: "title", right: "dayGridMonth,timeGridWeek,listMonth" },
        buttonText: { today: "Hoy", month: "Mes", week: "Semana", list: "Agenda" },
        events: this.fetchCalendarEvents,
        eventClick: this.openCalendarEvent,
      };
    },
  },
  mounted() { this.loadWorkspace(); },
  methods: {
    optionList(items = []) { return (items || []).map((item) => ({ value: item.value, text: item.label || item.text })); },
    apiError(error, fallback = "No fue posible completar la operación.") {
      const errors = error?.response?.data?.errors;
      return errors ? Object.values(errors).flat().join(" ") : error?.response?.data?.message || fallback;
    },
    hasValidationError(error, field) { return Boolean(error?.response?.data?.errors?.[field]?.length); },
    async loadWorkspace({ keepAction = false } = {}) {
      this.loading = true;
      this.error = "";
      try {
        const { data } = await convivenciaPlanApi.workspace(this.selectedYear);
        this.plan = data.data;
        this.availableYears = data.available_years || [];
        this.statusOptions = data.status_options || this.statusOptions;
        this.capabilities = data.capabilities || {};
        if (!keepAction) this.selectedAction = null;
      } catch (error) {
        this.error = this.apiError(error, "No fue posible cargar el plan anual.");
      } finally {
        this.loading = false;
      }
    },
    async changeYear() {
      this.activeView = "overview";
      await this.loadWorkspace();
    },
    openCreatePlan() {
      this.planForm = blankPlan(this.selectedYear, this.catalogs);
      this.modal = "plan";
    },
    openEditPlan() {
      this.planForm = {
        ...blankPlan(this.selectedYear, this.catalogs),
        ...clone(this.plan),
        specific_objectives: clone(this.plan?.specific_objectives || []),
        institutional_protocol: clone(this.plan?.institutional_protocol || []),
        evaluation_indicators: clone(this.plan?.evaluation_indicators || []),
        change_summary: "",
      };
      this.modal = "plan";
    },
    openClonePlan() {
      const targetYear = Number(this.plan?.calendar_year || this.selectedYear) + 1;
      this.cloneForm = {
        target_year: targetYear,
        academic_year_id: this.catalogs.academic_years?.find((item) => Number(item.year) === targetYear)?.id || null,
      };
      this.modal = "clone-year";
    },
    async saveClonePlan() {
      if (!this.plan) return;
      this.saving = true;
      try {
        await convivenciaPlanApi.cloneToYear(this.plan.id, { ...this.cloneForm, revision: this.plan.revision });
        this.selectedYear = Number(this.cloneForm.target_year);
        this.modal = null;
        await this.loadWorkspace();
        await Swal.fire({ icon: "success", title: `Plan ${this.selectedYear} creado`, text: "Se creó como borrador, vinculado al plan anterior y sin copiar ejecuciones ni evidencias.", confirmButtonColor: "#4f63d9" });
      } catch (error) {
        if (this.hasValidationError(error, "revision")) await this.loadWorkspace();
        await Swal.fire({ icon: "error", title: "No se pudo crear el plan anual", text: this.apiError(error), confirmButtonColor: "#4f63d9" });
      } finally { this.saving = false; }
    },
    addObjective() { this.planForm.specific_objectives.push(""); },
    addProtocolItem() { this.planForm.institutional_protocol.push({ title: "", description: "", responsible: "", timing: "", requirements: "" }); },
    addIndicator() { this.planForm.evaluation_indicators.push({ code: "", title: "", description: "", target_value: null, target_unit: "%", frequency: "", verification_source: "" }); },
    removeItem(list, index) { list.splice(index, 1); },
    async savePlan() {
      this.saving = true;
      try {
        const payload = clone(this.planForm);
        if (payload.id) await convivenciaPlanApi.updatePlan(payload.id, payload);
        else await convivenciaPlanApi.createPlan(payload);
        this.modal = null;
        await this.loadWorkspace();
        await Swal.fire({ icon: "success", title: "Plan guardado", text: "La definición quedó registrada como una nueva versión trazable.", confirmButtonColor: "#4f63d9" });
      } catch (error) {
        if (this.hasValidationError(error, "revision")) await this.loadWorkspace();
        await Swal.fire({ icon: "error", title: "No se pudo guardar", text: this.apiError(error), confirmButtonColor: "#4f63d9" });
      } finally { this.saving = false; }
    },
    openActionEditor(action = null) {
      this.actionForm = { ...blankAction(), ...clone(action || {}), change_summary: action ? "" : "Nueva acción incorporada al cronograma." };
      this.modal = "action";
    },
    async saveAction() {
      if (!this.plan) return;
      this.saving = true;
      try {
        const payload = { ...this.actionForm, plan_revision: this.plan.revision };
        if (this.actionForm.id) await convivenciaPlanApi.updateAction(this.actionForm.id, payload);
        else await convivenciaPlanApi.createAction(this.plan.id, payload);
        this.modal = null;
        await this.loadWorkspace();
        await Swal.fire({ icon: "success", title: "Acción guardada", text: "El cronograma y el historial de versiones fueron actualizados.", confirmButtonColor: "#4f63d9" });
      } catch (error) {
        if (this.hasValidationError(error, "plan_revision")) await this.loadWorkspace();
        await Swal.fire({ icon: "error", title: "No se pudo guardar la acción", text: this.apiError(error), confirmButtonColor: "#4f63d9" });
      } finally { this.saving = false; }
    },
    async openAction(action) {
      this.detailLoading = true;
      this.activeView = "actions";
      try {
        const { data } = await convivenciaPlanApi.action(action.id);
        this.selectedAction = data.data;
      } catch (error) {
        await Swal.fire({ icon: "error", title: "No se pudo abrir la acción", text: this.apiError(error) });
      } finally { this.detailLoading = false; }
    },
    async deleteAction(action) {
      const result = await Swal.fire({ icon: "warning", title: "¿Archivar esta acción?", text: "Las actividades y evidencias seguirán disponibles en el historial.", showCancelButton: true, confirmButtonText: "Archivar", cancelButtonText: "Cancelar", confirmButtonColor: "#c54b52" });
      if (!result.isConfirmed) return;
      try {
        await convivenciaPlanApi.deleteAction(action.id, this.plan.revision);
        await this.loadWorkspace();
      } catch (error) {
        if (this.hasValidationError(error, "plan_revision")) await this.loadWorkspace();
        await Swal.fire({ icon: "error", title: "No fue posible archivar", text: this.apiError(error) });
      }
    },
    actionRowActions(action) {
      return [
        { key: "open", label: "Gestionar", icon: "bx-folder-open", prominent: true },
        { key: "edit", label: "Editar", icon: "bx-edit-alt", visible: this.capabilities.can_manage },
        { key: "delete", label: "Archivar", icon: "bx-archive", tone: "danger", visible: this.capabilities.can_manage },
      ];
    },
    handleActionCommand(command, action) {
      if (command === "open") this.openAction(action);
      if (command === "edit") this.openActionEditor(action);
      if (command === "delete") this.deleteAction(action);
    },
    openActivityEditor(activity = null) {
      this.activityForm = {
        ...blankActivity(),
        ...clone(activity || {}),
        original_contribution_percent: Number(activity?.contribution_percent || 0),
        starts_at: inputDateTime(activity?.starts_at),
        ends_at: inputDateTime(activity?.ends_at),
      };
      this.modal = "activity";
    },
    async saveActivity() {
      if (!this.selectedAction) return;
      this.saving = true;
      try {
        if (this.activityForm.id) await convivenciaPlanApi.updateActivity(this.activityForm.id, this.activityForm);
        else await convivenciaPlanApi.createActivity(this.selectedAction.id, this.activityForm);
        this.modal = null;
        await this.loadWorkspace({ keepAction: true });
        await this.openAction({ id: this.selectedAction.id });
      } catch (error) {
        if (this.hasValidationError(error, "revision")) {
          await this.loadWorkspace({ keepAction: true });
          await this.openAction({ id: this.selectedAction.id });
        }
        await Swal.fire({ icon: "error", title: "No se pudo guardar la actividad", text: this.apiError(error) });
      } finally { this.saving = false; }
    },
    async deleteActivity(activity) {
      const result = await Swal.fire({ icon: "warning", title: "¿Archivar actividad?", text: "La evidencia histórica no será eliminada.", showCancelButton: true, confirmButtonText: "Archivar", cancelButtonText: "Cancelar", confirmButtonColor: "#c54b52" });
      if (!result.isConfirmed) return;
      try {
        await convivenciaPlanApi.deleteActivity(activity.id, activity.revision);
        await this.loadWorkspace({ keepAction: true });
        await this.openAction({ id: this.selectedAction.id });
      } catch (error) {
        if (this.hasValidationError(error, "revision")) {
          await this.loadWorkspace({ keepAction: true });
          await this.openAction({ id: this.selectedAction.id });
        }
        await Swal.fire({ icon: "error", title: "No fue posible archivar", text: this.apiError(error) });
      }
    },
    openEvidence(activity) {
      this.evidenceForm = { activity_id: activity.id, category: "evidencia", confidentiality_level: "general", notes: "", document: null };
      this.modal = "evidence";
    },
    openPlanDocument() {
      this.planDocumentForm = { category: "informe", confidentiality_level: "interna", notes: "Documento de respaldo del plan anual.", document: null };
      this.modal = "plan-document";
    },
    async saveEvidence() {
      if (!this.evidenceForm.document) return;
      this.saving = true;
      try {
        const data = new FormData();
        ["category", "confidentiality_level", "notes"].forEach((key) => data.append(key, this.evidenceForm[key] || ""));
        data.append("document", this.evidenceForm.document);
        await convivenciaPlanApi.uploadEvidence(this.evidenceForm.activity_id, data);
        this.modal = null;
        await this.openAction({ id: this.selectedAction.id });
      } catch (error) {
        await Swal.fire({ icon: "error", title: "No se pudo cargar la evidencia", text: this.apiError(error) });
      } finally { this.saving = false; }
    },
    fileSelected(event) { this.evidenceForm.document = event?.target?.files?.[0] || null; },
    planDocumentSelected(event) { this.planDocumentForm.document = event?.target?.files?.[0] || null; },
    async savePlanDocument() {
      if (!this.plan || !this.planDocumentForm.document) return;
      this.saving = true;
      try {
        const data = new FormData();
        ["category", "confidentiality_level", "notes"].forEach((key) => data.append(key, this.planDocumentForm[key] || ""));
        data.append("document", this.planDocumentForm.document);
        await convivenciaPlanApi.uploadPlanDocument(this.plan.id, data);
        this.modal = null;
        await this.loadWorkspace();
        await Swal.fire({ icon: "success", title: "Documento resguardado", text: "El archivo quedó asociado al plan y protegido por los permisos del módulo.", confirmButtonColor: "#4f63d9" });
      } catch (error) {
        await Swal.fire({ icon: "error", title: "No se pudo cargar el documento", text: this.apiError(error) });
      } finally { this.saving = false; }
    },
    async fetchCalendarEvents(info, success, failure) {
      if (!this.plan) return success([]);
      try {
        const { data } = await convivenciaPlanApi.calendar(this.plan.id, info.startStr, info.endStr);
        success(data.data || []);
      } catch (error) { failure(error); }
    },
    openCalendarEvent(info) {
      const props = info.event.extendedProps || {};
      if (props.action_id) this.openAction({ id: props.action_id });
    },
    async restoreVersion(version) {
      const result = await Swal.fire({ icon: "question", title: `Restaurar versión ${version.version_number}`, text: "Se creará una nueva versión vigente; el historial existente no se eliminará.", showCancelButton: true, confirmButtonText: "Restaurar como nueva versión", cancelButtonText: "Cancelar", confirmButtonColor: "#4f63d9" });
      if (!result.isConfirmed) return;
      try {
        await convivenciaPlanApi.restoreVersion(this.plan.id, version.id, this.plan.revision);
        await this.loadWorkspace();
      } catch (error) {
        if (this.hasValidationError(error, "revision")) await this.loadWorkspace();
        await Swal.fire({ icon: "error", title: "No se pudo restaurar", text: this.apiError(error) });
      }
    },
    async exportPdf() {
      if (!this.plan || !this.capabilities.can_export) return;
      this.exporting = true;
      try {
        const { data } = await convivenciaPlanApi.exportData(this.plan.id);
        await downloadConvivenciaPlanPdf(data.data);
      } catch (error) { await Swal.fire({ icon: "error", title: "No se pudo generar el PDF", text: this.apiError(error) }); }
      finally { this.exporting = false; }
    },
    statusLabel(value, type = "actions") { return this.statusOptions[type]?.find((item) => item.value === value)?.label || String(value || "Por definir").replaceAll("_", " "); },
    activityTypeLabel(activity) { return activity?.activity_type?.name || activity?.activity_type_label || "Actividad"; },
    actionPeriodLabel(action) {
      if (action?.date_precision === "month") return `${this.monthLabel(action.planned_month)} ${this.plan?.calendar_year || this.selectedYear}`;
      return action?.ends_on ? `${this.formatDate(action.starts_on)} — ${this.formatDate(action.ends_on)}` : this.formatDate(action?.starts_on);
    },
    monthLabel(value) { return this.months[Number(value) - 1] || "Fecha exacta"; },
    formatDate(value, time = false) {
      if (!value) return "Sin fecha";
      const source = String(value);
      const date = new Date(source.length === 10 ? `${source}T12:00:00` : source);
      return Number.isNaN(date.getTime()) ? source : new Intl.DateTimeFormat("es-CL", time ? { dateStyle: "medium", timeStyle: "short" } : { dateStyle: "medium" }).format(date);
    },
  },
};
</script>

<template>
  <section class="annual-plan-shell">
    <header class="annual-plan-command">
      <div class="annual-plan-command__identity">
        <span><i class="bx bx-calendar-star"></i></span>
        <div><small>GESTIÓN ANUAL</small><h2>Plan de Convivencia Escolar</h2><p>Planificación, ejecución, evidencias y versiones en un solo espacio.</p></div>
      </div>
      <div class="annual-plan-command__actions">
        <label><span>Año</span><BFormSelect v-model="selectedYear" :options="yearOptions" @change="changeYear" /></label>
        <button class="plan-btn plan-btn--ghost" type="button" :disabled="loading" @click="loadWorkspace()"><i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i>Actualizar</button>
        <button v-if="plan && capabilities.can_export" class="plan-btn plan-btn--pdf" type="button" :disabled="exporting" @click="exportPdf"><i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i>Informe PDF</button>
        <button v-if="plan && capabilities.can_manage" class="plan-btn plan-btn--ghost" type="button" @click="openClonePlan"><i class="bx bx-copy-alt"></i>Crear año siguiente</button>
        <button v-if="plan && capabilities.can_manage" class="plan-btn plan-btn--primary" type="button" @click="openEditPlan"><i class="bx bx-edit-alt"></i>Editar plan</button>
      </div>
    </header>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <div v-if="loading" class="annual-plan-loading"><span class="spinner-border text-primary"></span><b>Cargando plan anual…</b></div>

    <section v-else-if="!plan" class="annual-plan-empty">
      <span><i class="bx bx-calendar-plus"></i></span><small>{{ selectedYear }}</small><h3>Aún no existe un plan para este año</h3><p>Crea la versión inicial y comienza a organizar objetivos, acciones, calendario e indicadores.</p>
      <button v-if="capabilities.can_manage" class="plan-btn plan-btn--primary" type="button" @click="openCreatePlan"><i class="bx bx-plus"></i>Crear plan {{ selectedYear }}</button>
    </section>

    <template v-else>
      <section class="annual-plan-hero">
        <div class="annual-plan-hero__copy">
          <div class="annual-plan-badges"><span>Versión {{ plan.version_number }}</span><span :class="`is-${plan.status}`">{{ statusLabel(plan.status, 'plans') }}</span><span v-if="plan.previous_plan" class="is-source"><i class="bx bx-git-branch"></i>Basado en {{ plan.previous_plan.calendar_year }}</span><span v-if="plan.source_document_name" class="is-source"><i class="bx bx-file"></i>Documento {{ plan.calendar_year }} integrado</span></div>
          <h3>{{ plan.name }}</h3><p>{{ plan.general_objective }}</p>
          <div v-if="plan.regulatory_review_required" class="regulatory-alert"><i class="bx bx-shield-quarter"></i><div><b>Revisión reglamentaria pendiente</b><span>La cláusula de vinculación está preservada, pero requiere validación institucional antes de publicar.</span></div></div>
        </div>
        <div class="annual-progress-ring" :style="{ '--progress': `${Number(plan.advance_percentage || 0) * 3.6}deg` }"><div><strong>{{ Math.round(Number(plan.advance_percentage || 0)) }}%</strong><span>avance real</span></div></div>
      </section>

      <section class="annual-plan-kpis">
        <article><span class="is-indigo"><i class="bx bx-list-check"></i></span><div><small>Acciones</small><strong>{{ plan.stats?.actions || 0 }}</strong><p>{{ plan.stats?.completed_actions || 0 }} completadas</p></div></article>
        <article><span class="is-blue"><i class="bx bx-calendar-event"></i></span><div><small>Actividades</small><strong>{{ plan.stats?.activities || 0 }}</strong><p>{{ plan.stats?.completed_activities || 0 }} realizadas</p></div></article>
        <article><span class="is-green"><i class="bx bx-paperclip"></i></span><div><small>Evidencias</small><strong>{{ plan.stats?.evidences || 0 }}</strong><p>Archivos trazables</p></div></article>
        <article><span class="is-amber"><i class="bx bx-error-circle"></i></span><div><small>Alertas</small><strong>{{ plan.stats?.overdue_actions || 0 }}</strong><p>Acciones vencidas</p></div></article>
        <article><span class="is-violet"><i class="bx bx-pie-chart-alt-2"></i></span><div><small>Ponderación</small><strong>{{ Number(plan.stats?.action_weight_allocated || 0) }}%</strong><p>{{ plan.stats?.multi_year_actions || 0 }} acciones multianuales</p></div></article>
      </section>
      <section class="weight-allocation" :class="{ complete: Number(plan.stats?.action_weight_allocated || 0) === 100 }"><div><b>Ponderación del plan</b><span>{{ Number(plan.stats?.action_weight_allocated || 0) }}% asignado · {{ actionWeightRemaining }}% disponible</span></div><div class="weight-allocation__track"><i :style="{ width: `${Math.min(100, Number(plan.stats?.action_weight_allocated || 0))}%` }"></i></div><small>{{ plan.stats?.progress_method === 'weighted' ? 'El avance total considera el peso de cada acción.' : 'Plan antiguo sin ponderar: se usa promedio simple hasta asignar pesos.' }}</small></section>

      <nav class="annual-plan-tabs" aria-label="Secciones del plan">
        <button v-for="tab in [{key:'overview',label:'Resumen',icon:'bx-grid-alt'},{key:'actions',label:'Acciones y ejecución',icon:'bx-task'},{key:'calendar',label:'Calendario',icon:'bx-calendar'},{key:'versions',label:'Versiones',icon:'bx-history'}]" :key="tab.key" type="button" :class="{ active: activeView === tab.key }" @click="activeView = tab.key"><i class="bx" :class="tab.icon"></i>{{ tab.label }}</button>
      </nav>

      <div v-if="activeView === 'overview'" class="annual-overview-grid">
        <article class="plan-panel plan-panel--objectives"><header><span><i class="bx bx-target-lock"></i></span><div><small>DIRECCIÓN ESTRATÉGICA</small><h3>Objetivos del plan</h3></div></header><p class="objective-main">{{ plan.general_objective }}</p><ol><li v-for="(objective, index) in plan.specific_objectives || []" :key="index"><b>{{ index + 1 }}</b><span>{{ objective }}</span></li></ol></article>
        <article class="plan-panel"><header><span class="is-green"><i class="bx bx-home-heart"></i></span><div><small>ACOGIDA INSTITUCIONAL</small><h3>Protocolo de recepción</h3></div><em>{{ plan.institutional_protocol?.length || 0 }} componentes</em></header><div class="protocol-list"><div v-for="(item, index) in plan.institutional_protocol || []" :key="index"><b>{{ item.title }}</b><p>{{ item.description }}</p><span v-if="item.timing"><i class="bx bx-time"></i>{{ item.timing }}</span><span v-if="item.responsible"><i class="bx bx-user-check"></i>{{ item.responsible }}</span></div></div></article>
        <article class="plan-panel plan-panel--wide"><header><span class="is-blue"><i class="bx bx-line-chart"></i></span><div><small>EVALUACIÓN</small><h3>Indicadores y metas 2026</h3></div><em>{{ plan.evaluation_indicators?.length || 0 }} indicadores</em></header><div class="indicator-grid"><div v-for="indicator in plan.evaluation_indicators || []" :key="indicator.code || indicator.title"><span>{{ indicator.code }}</span><strong>{{ indicator.target_value }}{{ indicator.target_unit === '%' ? '%' : ` ${indicator.target_unit || ''}` }}</strong><b>{{ indicator.title }}</b><p>{{ indicator.description }}</p><small><i class="bx bx-check-shield"></i>{{ indicator.verification_source }}</small></div></div></article>
        <article class="plan-panel plan-panel--wide regulatory-panel" :class="{ 'needs-review': plan.regulatory_review_required }"><header><span><i class="bx bx-book-bookmark"></i></span><div><small>VINCULACIÓN CON EL RICE</small><h3>Cláusula reglamentaria</h3></div><em>{{ plan.regulatory_review_required ? 'Pendiente de revisión' : 'Revisada' }}</em></header><p>{{ plan.regulatory_linkage_text || 'No se ha registrado una cláusula de vinculación.' }}</p></article>
        <article class="plan-panel plan-panel--wide source-documents-panel"><header><span><i class="bx bx-file-blank"></i></span><div><small>CONTROL DOCUMENTAL</small><h3>Documentos fuente y respaldos</h3></div><button v-if="capabilities.can_manage" class="plan-btn plan-btn--ghost plan-btn--small" type="button" @click="openPlanDocument"><i class="bx bx-upload"></i>Agregar documento</button></header><div class="source-documents"><a v-for="file in plan.attachments || []" :key="file.id" :href="`/api/convivencia/attachments/${file.id}/download`" target="_blank" rel="noopener"><span><i class="bx bx-file"></i></span><div><b>{{ file.original_name }}</b><small>{{ file.category }} · {{ file.confidentiality_level }} · {{ Math.max(1, Math.round(Number(file.file_size || 0) / 1024)) }} KB</small></div><i class="bx bx-download"></i></a><div v-if="!plan.attachments?.length" class="source-documents__empty"><i class="bx bx-info-circle"></i><span>El contenido de <b>{{ plan.source_document_name || 'la definición anual' }}</b> ya está estructurado en el plan. Puedes adjuntar aquí el archivo original para conservarlo y descargarlo.</span></div></div></article>
      </div>

      <div v-else-if="activeView === 'actions'" class="annual-actions-view">
        <ConvivenciaDataTable title="Matriz anual de acciones" subtitle="Planificación separada de la ejecución y sus evidencias." icon="bx-task" :count="filteredActions.length" :empty="!filteredActions.length" min-width="980px">
          <template #actions><button v-if="capabilities.can_manage" class="plan-btn plan-btn--primary plan-btn--small" type="button" @click="openActionEditor()"><i class="bx bx-plus"></i>Nueva acción</button></template>
          <template #toolbar><div class="action-toolbar"><BFormInput v-model="actionSearch" placeholder="Buscar acción, público o responsable" /><BFormSelect v-model="actionStatus" :options="[{value:'',text:'Todos los estados'},...actionStatusOptions]" /></div></template>
          <table class="table"><thead><tr><th>Vigencia</th><th>Acción</th><th>Público</th><th>Responsable</th><th>Estado</th><th>Peso</th><th>Avance individual</th><th>Acciones</th></tr></thead><tbody><tr v-for="action in filteredActions" :key="action.id"><td data-label="Vigencia"><span class="month-pill">{{ actionPeriodLabel(action) }}</span><span v-if="action.is_multi_year" class="multi-year-badge"><i class="bx bx-transfer-alt"></i>Multianual</span></td><td data-label="Acción"><div class="action-title"><b>{{ action.title }}</b><small>{{ action.action_type }} · {{ action.activities_count }} actividades · {{ action.evidences_count }} evidencias</small></div></td><td data-label="Público">{{ action.target_audience || 'Por definir' }}</td><td data-label="Responsable">{{ action.responsible_user?.name || action.responsible_staff?.full_name || action.responsible_label || 'Por definir' }}</td><td data-label="Estado"><span class="status-chip" :class="`is-${action.status}`">{{ statusLabel(action.status) }}</span></td><td data-label="Peso"><b class="weight-pill">{{ Number(action.weight_percent || 0) }}%</b></td><td data-label="Avance individual"><div class="progress-cell"><span><i :style="{ width: `${Number(action.advance_percentage || 0)}%` }"></i></span><b>{{ Math.round(Number(action.advance_percentage || 0)) }}%</b></div><small class="weighted-impact">Aporta {{ Number(action.weighted_progress || 0) }} pts al plan</small></td><td data-label="Acciones"><ConvivenciaRowActions :actions="actionRowActions(action)" :item-label="action.title" @select="handleActionCommand($event, action)" /></td></tr></tbody></table>
        </ConvivenciaDataTable>

        <section v-if="detailLoading || selectedAction" class="action-detail-panel">
          <div v-if="detailLoading" class="annual-plan-loading"><span class="spinner-border text-primary"></span><b>Cargando ejecución…</b></div>
          <template v-else>
            <header><div><small>SEGUIMIENTO DE LA ACCIÓN</small><h3>{{ selectedAction.title }}</h3><p>{{ selectedAction.objective || selectedAction.description || 'Sin descripción adicional.' }}</p></div><div><button v-if="capabilities.can_manage" class="plan-btn plan-btn--primary plan-btn--small" type="button" @click="openActivityEditor()"><i class="bx bx-plus"></i>Programar actividad</button><button class="detail-close" type="button" aria-label="Cerrar detalle" @click="selectedAction = null"><i class="bx bx-x"></i></button></div></header>
            <div class="action-progress-summary"><div><b>{{ Math.round(Number(selectedAction.advance_percentage || 0)) }}%</b><span>avance individual</span></div><section><header><b>Aporte de actividades</b><span>{{ Number(selectedAction.allocated_contribution || 0) }}% asignado · {{ activityContributionRemaining }}% disponible</span></header><div><i :style="{ width: `${Math.min(100, Number(selectedAction.allocated_contribution || 0))}%` }"></i></div><small>Esta acción pesa {{ Number(selectedAction.weight_percent || 0) }}% y aporta {{ Number(selectedAction.weighted_progress || 0) }} puntos al avance total.</small></section></div>
            <div class="action-detail-meta"><span><i class="bx bx-calendar"></i>{{ actionPeriodLabel(selectedAction) }}<b v-if="selectedAction.is_multi_year"> · Multianual</b></span><span><i class="bx bx-group"></i>{{ selectedAction.target_audience || 'Público por definir' }}</span><span><i class="bx bx-check-shield"></i>{{ selectedAction.indicator_summary || 'Indicador por definir' }}</span><span><i class="bx bx-paperclip"></i>{{ selectedAction.verification_means || 'Verificación por definir' }}</span></div>
            <div v-if="!selectedAction.activities?.length" class="activity-empty"><i class="bx bx-calendar-plus"></i><b>Sin actividades ejecutables</b><p>Programa una fecha y define cuánto aporta al cumplimiento de esta acción.</p></div>
            <div v-else class="activity-list"><article v-for="activity in selectedAction.activities" :key="activity.id"><div class="activity-date"><b>{{ new Date(activity.starts_at).getDate() }}</b><span>{{ new Intl.DateTimeFormat('es-CL',{month:'short'}).format(new Date(activity.starts_at)) }}</span></div><div class="activity-main"><div><span class="activity-type-badge" :style="{ '--activity-color': activity.activity_type?.color || '#4f63d9' }"><i class="bx" :class="activity.activity_type?.metadata?.icon || 'bx-calendar-event'"></i>{{ activityTypeLabel(activity) }}</span><span class="status-chip" :class="`is-${activity.status}`">{{ statusLabel(activity.status, 'activities') }}</span><small>{{ Number(activity.contribution_percent || 0) }}% de aporte · {{ Number(activity.completion_percent || 0) }}% ejecutado · {{ Number(activity.earned_progress || 0) }} pts obtenidos</small></div><h4>{{ activity.title }}</h4><p>{{ formatDate(activity.starts_at, true) }} · {{ activity.location || 'Lugar por definir' }} · {{ activity.target_audience || selectedAction.target_audience || 'Público por definir' }}</p><div v-if="activity.results" class="activity-results"><b>Resultado</b>{{ activity.results }}</div><div v-if="activity.attachments?.length" class="activity-files"><a v-for="file in activity.attachments" :key="file.id" :href="`/api/convivencia/attachments/${file.id}/download`" target="_blank"><i class="bx bx-file"></i>{{ file.original_name }}</a></div></div><div v-if="capabilities.can_manage" class="activity-actions"><button type="button" @click="openEvidence(activity)"><i class="bx bx-paperclip"></i>Evidencia</button><button type="button" @click="openActivityEditor(activity)"><i class="bx bx-edit-alt"></i>Editar</button><button class="danger" type="button" @click="deleteActivity(activity)"><i class="bx bx-archive"></i>Archivar</button></div></article></div>
          </template>
        </section>
      </div>

      <section v-else-if="activeView === 'calendar'" class="plan-panel calendar-panel"><header><span class="is-blue"><i class="bx bx-calendar"></i></span><div><small>CALENDARIO INTEGRADO</small><h3>Acciones y actividades</h3></div></header><div class="calendar-legend"><span><i class="planned"></i>Planificada</span><span><i class="progress"></i>En ejecución</span><span><i class="done"></i>Realizada</span><span><i class="cancelled"></i>Cancelada</span></div><FullCalendar ref="calendar" :options="calendarOptions" /></section>

      <section v-else class="versions-panel"><header><div><small>CONTROL DOCUMENTAL</small><h3>Historial de versiones</h3><p>Cada edición de la definición o sus acciones crea una copia auditable.</p></div><span>Versión vigente {{ plan.version_number }}</span></header><div class="version-timeline"><article v-for="version in plan.versions || []" :key="version.id" :class="{ current: version.version_number === plan.version_number }"><div class="version-number">v{{ version.version_number }}</div><div><b>{{ version.change_summary }}</b><span>{{ formatDate(version.created_at, true) }} · {{ version.created_by?.name || 'Sistema' }}</span></div><em v-if="version.version_number === plan.version_number">Vigente</em><button v-else-if="capabilities.can_manage" type="button" @click="restoreVersion(version)"><i class="bx bx-revision"></i>Restaurar</button></article></div></section>
    </template>

    <ConvivenciaFormModal :model-value="modal === 'plan'" title="Definición del plan anual" eyebrow="Versión documental" description="Edita objetivos, protocolo de acogida, indicadores y vinculación reglamentaria." icon="bx-calendar-check" size="xl" :busy="saving" submit-label="Guardar nueva versión" @update:model-value="modal = null" @close="modal = null" @submit="savePlan">
      <div class="plan-form-grid"><label><span>Año del plan *</span><BFormInput v-model="planForm.calendar_year" type="number" min="2020" max="2100" :disabled="Boolean(planForm.id)" required /><small v-if="planForm.id">El año no cambia entre versiones.</small></label><label><span>Año académico</span><BFormSelect v-model="planForm.academic_year_id" :options="academicYearOptions" /></label><label><span>Responsable</span><BFormSelect v-model="planForm.responsible_user_id" :options="userOptions" /></label><label><span>Estado</span><BFormSelect v-model="planForm.status" :options="planStatusOptions" required /></label><label class="wide"><span>Nombre *</span><BFormInput v-model="planForm.name" required /></label><label class="wide"><span>Objetivo general *</span><BFormTextarea v-model="planForm.general_objective" rows="3" required /></label><label><span>Inicio</span><BFormInput v-model="planForm.starts_on" type="date" /></label><label><span>Término</span><BFormInput v-model="planForm.ends_on" type="date" /></label></div>
      <div class="form-collection"><header><div><b>Objetivos específicos</b><small>Resultados que orientan el plan.</small></div><button type="button" @click="addObjective"><i class="bx bx-plus"></i>Agregar</button></header><div v-for="(objective,index) in planForm.specific_objectives" :key="index" class="simple-repeat"><BFormInput v-model="planForm.specific_objectives[index]" :placeholder="`Objetivo ${index+1}`" /><button type="button" aria-label="Quitar objetivo" @click="removeItem(planForm.specific_objectives,index)"><i class="bx bx-trash"></i></button></div></div>
      <div class="form-collection"><header><div><b>Protocolo de acogida institucional</b><small>Componentes operativos para estudiantes y familias nuevas.</small></div><button type="button" @click="addProtocolItem"><i class="bx bx-plus"></i>Agregar componente</button></header><article v-for="(item,index) in planForm.institutional_protocol" :key="index" class="complex-repeat"><header><b>Componente {{ index+1 }}</b><button type="button" @click="removeItem(planForm.institutional_protocol,index)"><i class="bx bx-trash"></i>Quitar</button></header><div class="plan-form-grid"><label><span>Título *</span><BFormInput v-model="item.title" required /></label><label><span>Responsable</span><BFormInput v-model="item.responsible" /></label><label><span>Momento</span><BFormInput v-model="item.timing" /></label><label class="wide"><span>Descripción</span><BFormTextarea v-model="item.description" rows="2" /></label><label class="wide"><span>Requisitos</span><BFormTextarea v-model="item.requirements" rows="2" /></label></div></article></div>
      <div class="form-collection"><header><div><b>Indicadores de evaluación</b><small>Define meta, frecuencia y fuente de verificación.</small></div><button type="button" @click="addIndicator"><i class="bx bx-plus"></i>Agregar indicador</button></header><article v-for="(item,index) in planForm.evaluation_indicators" :key="index" class="complex-repeat"><header><b>Indicador {{ index+1 }}</b><button type="button" @click="removeItem(planForm.evaluation_indicators,index)"><i class="bx bx-trash"></i>Quitar</button></header><div class="plan-form-grid plan-form-grid--indicator"><label><span>Código</span><BFormInput v-model="item.code" /></label><label><span>Nombre *</span><BFormInput v-model="item.title" required /></label><label><span>Meta</span><BFormInput v-model="item.target_value" type="number" step="0.1" /></label><label><span>Unidad</span><BFormInput v-model="item.target_unit" /></label><label><span>Frecuencia</span><BFormInput v-model="item.frequency" /></label><label class="wide"><span>Definición</span><BFormTextarea v-model="item.description" rows="2" /></label><label class="wide"><span>Fuente de verificación</span><BFormInput v-model="item.verification_source" /></label></div></article></div>
      <div class="plan-form-grid"><label class="wide"><span>Recursos requeridos</span><BFormTextarea v-model="planForm.resources_required" rows="2" /></label><label class="wide"><span>Medios de verificación generales</span><BFormTextarea v-model="planForm.verification_means_summary" rows="2" /></label><label class="wide"><span>Cláusula de vinculación con el RICE</span><BFormTextarea v-model="planForm.regulatory_linkage_text" rows="5" /></label><label class="wide switch-label"><BFormCheckbox v-model="planForm.regulatory_review_required" switch>Requiere revisión institucional antes de publicar</BFormCheckbox></label><label class="wide"><span>Observaciones</span><BFormTextarea v-model="planForm.observations" rows="2" /></label><label v-if="planForm.id" class="wide change-summary"><span>Resumen del cambio *</span><BFormInput v-model="planForm.change_summary" required placeholder="Ej.: Se ajustaron metas y responsables del segundo semestre" /></label></div>
    </ConvivenciaFormModal>

    <ConvivenciaFormModal :model-value="modal === 'action'" :title="actionForm.id ? 'Editar acción' : 'Nueva acción del plan'" eyebrow="Matriz anual y multianual" description="Define la vigencia, su peso en el plan y luego registra actividades concretas para ejecutarla." icon="bx-task" size="lg" :busy="saving" @update:model-value="modal = null" @close="modal = null" @submit="saveAction"><div class="plan-form-grid"><label><span>Tipo de acción *</span><BFormSelect v-model="actionForm.action_type" :options="actionTypeOptions" required /></label><label><span>Estado *</span><BFormSelect v-model="actionForm.status" :options="actionStatusOptions" required /></label><label class="wide"><span>Título *</span><BFormInput v-model="actionForm.title" required /></label><label class="wide"><span>Objetivo</span><BFormTextarea v-model="actionForm.objective" rows="2" /></label><label class="wide"><span>Descripción</span><BFormTextarea v-model="actionForm.description" rows="2" /></label><label><span>Público objetivo</span><BFormInput v-model="actionForm.target_audience" /></label><label><span>Dimensión</span><BFormSelect v-model="actionForm.dimension_item_id" :options="dimensionOptions" /></label><label><span>Ponderación en el plan (%) *</span><BFormInput v-model="actionForm.weight_percent" type="number" min="0" max="100" step="0.01" required /><small>{{ actionForm.id ? 'Al editar se recalcula el total disponible.' : `${actionWeightRemaining}% disponible en el plan.` }}</small></label><label><span>Tipo de calendario *</span><BFormSelect v-model="actionForm.date_precision" :options="[{value:'month',text:'Mes del año del plan'},{value:'exact',text:'Rango de fechas (permite varios años)'}]" required /></label><label v-if="actionForm.date_precision === 'month'"><span>Mes *</span><BFormSelect v-model="actionForm.planned_month" :options="monthOptions" required /></label><template v-else><label><span>Fecha de inicio *</span><BFormInput v-model="actionForm.starts_on" type="date" required /></label><label><span>Fecha de término</span><BFormInput v-model="actionForm.ends_on" type="date" /><small>Puede finalizar en un año posterior.</small></label></template><label><span>Usuario responsable</span><BFormSelect v-model="actionForm.responsible_user_id" :options="userOptions" /></label><label><span>Ficha de funcionario</span><BFormSelect v-model="actionForm.responsible_staff_id" :options="staffOptions" /></label><label><span>Departamento</span><BFormSelect v-model="actionForm.responsible_department_id" :options="departmentOptions" /></label><label><span>Responsable descriptivo</span><BFormInput v-model="actionForm.responsible_label" /></label><label class="wide"><span>Recursos</span><BFormTextarea v-model="actionForm.required_resources" rows="2" /></label><label class="wide"><span>Indicador verificable</span><BFormTextarea v-model="actionForm.indicator_summary" rows="2" /></label><label class="wide"><span>Medios de verificación</span><BFormTextarea v-model="actionForm.verification_means" rows="2" /></label><label class="wide"><span>Observaciones</span><BFormTextarea v-model="actionForm.observations" rows="2" /></label><label class="wide change-summary"><span>Motivo del cambio *</span><BFormInput v-model="actionForm.change_summary" required /></label></div></ConvivenciaFormModal>

    <ConvivenciaFormModal :model-value="modal === 'activity'" :title="activityForm.id ? 'Editar actividad' : 'Programar actividad'" eyebrow="Ejecución real" description="Selecciona el tipo, registra su realización y define cuánto aporta al avance de la acción." icon="bx-calendar-event" size="lg" :busy="saving" @update:model-value="modal = null" @close="modal = null" @submit="saveActivity"><div class="activity-progress-note"><i class="bx bx-info-circle"></i><span>La suma de aportes de las actividades no puede superar 100%. Disponible ahora: <b>{{ activityContributionRemaining }}%</b>.</span></div><fieldset class="activity-type-picker"><legend>Tipo de actividad *</legend><button v-for="type in activityTypeOptions" :key="type.value" type="button" :class="{ active: Number(activityForm.activity_type_item_id) === Number(type.value) }" :style="{ '--type-color': type.color }" @click="activityForm.activity_type_item_id = type.value"><i class="bx" :class="type.icon"></i><span>{{ type.text }}</span></button></fieldset><div class="plan-form-grid"><label class="wide"><span>Título *</span><BFormInput v-model="activityForm.title" required /></label><label><span>Inicio *</span><BFormInput v-model="activityForm.starts_at" type="datetime-local" required /></label><label><span>Término</span><BFormInput v-model="activityForm.ends_at" type="datetime-local" /></label><label><span>Estado *</span><BFormSelect v-model="activityForm.status" :options="activityStatusOptions" required /></label><label><span>Aporte al avance (%) *</span><BFormInput v-model="activityForm.contribution_percent" type="number" min="0" max="100" required /></label><label v-if="activityForm.status === 'en_ejecucion'"><span>Ejecución parcial (%)</span><BFormInput v-model="activityForm.completion_percent" type="number" min="0" max="99" /></label><label><span>Asistentes</span><BFormInput v-model="activityForm.attendee_count" type="number" min="0" /></label><label><span>Lugar</span><BFormInput v-model="activityForm.location" /></label><label><span>Público participante</span><BFormInput v-model="activityForm.target_audience" /></label><label class="wide"><span>Descripción</span><BFormTextarea v-model="activityForm.description" rows="2" /></label><label class="wide"><span>Resultados obtenidos</span><BFormTextarea v-model="activityForm.results" rows="3" /></label></div></ConvivenciaFormModal>

    <ConvivenciaFormModal :model-value="modal === 'clone-year'" title="Crear el plan de un nuevo año" eyebrow="Continuidad anual" description="Copia la definición y las acciones como planificación pendiente; las actividades ejecutadas y sus evidencias no se duplican." icon="bx-copy-alt" size="md" :busy="saving" submit-label="Crear plan borrador" @update:model-value="modal = null" @close="modal = null" @submit="saveClonePlan"><div class="clone-year-callout"><i class="bx bx-git-branch"></i><div><b>Origen: {{ plan?.calendar_year }} · versión {{ plan?.version_number }}</b><span>El nuevo plan quedará enlazado al anterior para mantener la trazabilidad por año.</span></div></div><div class="plan-form-grid"><label><span>Nuevo año *</span><BFormInput v-model="cloneForm.target_year" type="number" min="2020" max="2100" required /></label><label><span>Año académico asociado</span><BFormSelect v-model="cloneForm.academic_year_id" :options="[{value:null,text:'Sin asociación'},...academicYearOptions]" /></label></div></ConvivenciaFormModal>

    <ConvivenciaFormModal :model-value="modal === 'evidence'" title="Agregar evidencia" eyebrow="Respaldo de ejecución" description="El archivo se almacena de forma privada y queda vinculado a la actividad." icon="bx-paperclip" size="md" :busy="saving" submit-label="Cargar evidencia" @update:model-value="modal = null" @close="modal = null" @submit="saveEvidence"><div class="plan-form-grid"><label><span>Categoría</span><BFormSelect v-model="evidenceForm.category" :options="(catalogs.attachment_categories || []).map(item => ({value:item.value,text:item.label}))" /></label><label><span>Confidencialidad</span><BFormSelect v-model="evidenceForm.confidentiality_level" :options="[{value:'general',text:'General'},{value:'interna',text:'Interna'},{value:'reservada',text:'Reservada'},{value:'confidencial',text:'Confidencial'}]" /></label><label class="wide"><span>Archivo *</span><input class="form-control" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt,.csv" required @change="fileSelected" /></label><label class="wide"><span>Notas</span><BFormTextarea v-model="evidenceForm.notes" rows="2" /></label></div></ConvivenciaFormModal>

    <ConvivenciaFormModal :model-value="modal === 'plan-document'" title="Agregar documento del plan" eyebrow="Control documental" description="Adjunta el Word original, actas de aprobación u otros respaldos. El archivo no queda expuesto públicamente." icon="bx-file-blank" size="md" :busy="saving" submit-label="Resguardar documento" @update:model-value="modal = null" @close="modal = null" @submit="savePlanDocument"><div class="plan-form-grid"><label><span>Categoría</span><BFormSelect v-model="planDocumentForm.category" :options="(catalogs.attachment_categories || []).map(item => ({value:item.value,text:item.label}))" /></label><label><span>Confidencialidad</span><BFormSelect v-model="planDocumentForm.confidentiality_level" :options="[{value:'general',text:'General'},{value:'interna',text:'Interna'},{value:'reservada',text:'Reservada'},{value:'confidencial',text:'Confidencial'}]" /></label><label class="wide"><span>Archivo *</span><input class="form-control" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.csv" required @change="planDocumentSelected" /></label><label class="wide"><span>Descripción</span><BFormTextarea v-model="planDocumentForm.notes" rows="2" /></label></div></ConvivenciaFormModal>
  </section>
</template>

<style scoped>
.annual-plan-shell{--navy:#263a8f;--indigo:#4f63d9;--blue:#3576d3;--green:#258a70;--amber:#d18a25;--ink:#273252;--muted:#758198;--line:#dfe5ef;display:grid;gap:1rem}.annual-plan-command{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.15rem;border:1px solid var(--line);border-radius:22px;background:linear-gradient(125deg,#fff 0,#f7f9ff 70%,#eef5ff 100%);box-shadow:0 14px 34px rgba(39,55,104,.075)}.annual-plan-command__identity{display:flex;align-items:center;gap:.8rem}.annual-plan-command__identity>span{display:grid;width:52px;height:52px;color:#fff;font-size:1.5rem;place-items:center;border-radius:16px;background:linear-gradient(135deg,var(--indigo),#2e9f83);box-shadow:0 10px 22px rgba(79,99,217,.2)}.annual-plan-command small,.plan-panel header small,.versions-panel header small,.action-detail-panel>header small{display:block;color:#6475ba;font-size:.59rem;font-weight:900;letter-spacing:.095em}.annual-plan-command h2{margin:.12rem 0;color:var(--ink);font-size:1.12rem;font-weight:850}.annual-plan-command p{margin:0;color:var(--muted);font-size:.7rem}.annual-plan-command__actions{display:flex;align-items:end;gap:.55rem}.annual-plan-command__actions label span{display:block;margin:0 0 .25rem .2rem;color:var(--muted);font-size:.58rem;font-weight:800;text-transform:uppercase}.annual-plan-command__actions select{min-width:105px;border-radius:11px}.plan-btn{display:inline-flex;min-height:38px;align-items:center;justify-content:center;gap:.4rem;padding:.6rem .8rem;border:1px solid transparent;border-radius:11px;font-size:.68rem;font-weight:800;transition:.18s ease}.plan-btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 8px 18px rgba(45,57,105,.13)}.plan-btn--primary{color:#fff;background:linear-gradient(135deg,#596cdf,#4154c7)}.plan-btn--ghost{color:#53617a;border-color:#d9e0eb;background:#fff}.plan-btn--pdf{color:#ad3f4a;border-color:#edcbd0;background:#fff7f7}.plan-btn--small{min-height:34px;padding:.48rem .65rem}.annual-plan-loading,.annual-plan-empty{display:flex;min-height:330px;align-items:center;justify-content:center;flex-direction:column;gap:.5rem;padding:2rem;border:1px solid var(--line);border-radius:22px;background:#fff;text-align:center}.annual-plan-empty>span{display:grid;width:68px;height:68px;color:var(--indigo);font-size:2rem;place-items:center;border-radius:22px;background:#eef1ff}.annual-plan-empty small{color:var(--indigo);font-size:.7rem;font-weight:900}.annual-plan-empty h3{margin:.2rem 0 0;color:var(--ink);font-size:1.25rem}.annual-plan-empty p{max-width:490px;margin:0 0 .6rem;color:var(--muted);font-size:.76rem}.annual-plan-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;padding:1.35rem 1.5rem;color:#fff;border-radius:24px;background:radial-gradient(circle at 82% -20%,rgba(255,255,255,.2),transparent 38%),linear-gradient(130deg,#263a8f,#4f63d9 62%,#2b8f82);box-shadow:0 18px 40px rgba(38,58,143,.2)}.annual-plan-badges{display:flex;flex-wrap:wrap;gap:.38rem}.annual-plan-badges span{display:inline-flex;align-items:center;gap:.28rem;padding:.28rem .52rem;color:#fff;font-size:.59rem;font-weight:850;border:1px solid rgba(255,255,255,.22);border-radius:999px;background:rgba(255,255,255,.13)}.annual-plan-badges .is-source{color:#dffaf2}.annual-plan-hero h3{margin:.65rem 0 .35rem;font-size:1.55rem;font-weight:850}.annual-plan-hero__copy>p{max-width:880px;margin:0;color:#e4e8ff;font-size:.78rem;line-height:1.55}.regulatory-alert{display:flex;max-width:760px;align-items:flex-start;gap:.55rem;margin-top:.8rem;padding:.65rem .75rem;border:1px solid rgba(255,223,163,.32);border-radius:12px;background:rgba(81,47,11,.2)}.regulatory-alert i{color:#ffd98e;font-size:1.15rem}.regulatory-alert b,.regulatory-alert span{display:block}.regulatory-alert b{font-size:.68rem}.regulatory-alert span{margin-top:.14rem;color:#ffebc4;font-size:.62rem}.annual-progress-ring{display:grid;width:126px;height:126px;flex:0 0 126px;place-items:center;border-radius:50%;background:conic-gradient(#73e1c5 var(--progress),rgba(255,255,255,.15) 0);box-shadow:0 10px 28px rgba(0,0,0,.16)}.annual-progress-ring:before{content:"";grid-area:1/1;width:98px;height:98px;border-radius:50%;background:#334a9f}.annual-progress-ring div{z-index:1;grid-area:1/1;text-align:center}.annual-progress-ring strong,.annual-progress-ring span{display:block}.annual-progress-ring strong{font-size:1.65rem}.annual-progress-ring span{color:#dce2ff;font-size:.58rem;font-weight:800;text-transform:uppercase}.annual-plan-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.annual-plan-kpis article{display:flex;align-items:center;gap:.75rem;padding:.9rem;border:1px solid var(--line);border-radius:17px;background:#fff;box-shadow:0 8px 20px rgba(40,52,88,.05)}.annual-plan-kpis article>span,.plan-panel>header>span{display:grid;width:42px;height:42px;flex:0 0 42px;color:#fff;font-size:1.18rem;place-items:center;border-radius:13px;background:var(--indigo)}.annual-plan-kpis .is-blue,.plan-panel .is-blue{background:var(--blue)}.annual-plan-kpis .is-green,.plan-panel .is-green{background:var(--green)}.annual-plan-kpis .is-amber{background:var(--amber)}.annual-plan-kpis small{color:var(--muted);font-size:.57rem;font-weight:850;text-transform:uppercase}.annual-plan-kpis strong{display:block;color:var(--ink);font-size:1.25rem}.annual-plan-kpis p{margin:0;color:var(--muted);font-size:.61rem}.annual-plan-tabs{display:flex;gap:.3rem;padding:.35rem;border:1px solid var(--line);border-radius:15px;background:#fff;overflow-x:auto}.annual-plan-tabs button{display:flex;align-items:center;justify-content:center;gap:.38rem;min-width:max-content;padding:.63rem .85rem;color:#657187;border:0;border-radius:11px;background:transparent;font-size:.68rem;font-weight:800}.annual-plan-tabs button.active{color:#fff;background:linear-gradient(135deg,var(--indigo),#3f53c6);box-shadow:0 7px 15px rgba(79,99,217,.2)}.annual-overview-grid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}.plan-panel,.versions-panel,.action-detail-panel{padding:1.1rem;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 10px 26px rgba(39,50,82,.055)}.plan-panel--wide{grid-column:1/-1}.plan-panel>header{display:flex;align-items:center;gap:.65rem;margin-bottom:.85rem}.plan-panel>header h3{margin:.12rem 0 0;color:var(--ink);font-size:.93rem}.plan-panel>header em{margin-left:auto;padding:.25rem .5rem;color:#69758a;font-size:.59rem;font-weight:800;font-style:normal;border-radius:999px;background:#f1f3f7}.objective-main{padding:.75rem;color:#43506b;font-size:.75rem;line-height:1.55;border-radius:12px;background:#f7f8fc}.plan-panel--objectives ol{display:grid;gap:.5rem;margin:.75rem 0 0;padding:0;list-style:none}.plan-panel--objectives li{display:flex;align-items:flex-start;gap:.55rem;color:#566278;font-size:.69rem;line-height:1.45}.plan-panel--objectives li b{display:grid;width:24px;height:24px;flex:0 0 24px;color:var(--indigo);font-size:.62rem;place-items:center;border-radius:8px;background:#edf0ff}.protocol-list{display:grid;gap:.55rem}.protocol-list>div{padding:.7rem;border:1px solid #e5eaf1;border-radius:13px;background:#fbfcfe}.protocol-list b{color:var(--ink);font-size:.72rem}.protocol-list p{margin:.25rem 0;color:#657187;font-size:.66rem;line-height:1.45}.protocol-list span{display:inline-flex;align-items:center;gap:.25rem;margin:.2rem .5rem 0 0;color:#728096;font-size:.58rem}.indicator-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}.indicator-grid>div{position:relative;padding:.8rem;border:1px solid #e1e6ef;border-radius:14px;background:linear-gradient(145deg,#fff,#f8faff)}.indicator-grid>div>span{color:var(--indigo);font-size:.56rem;font-weight:900}.indicator-grid strong{float:right;color:var(--indigo);font-size:1.15rem}.indicator-grid b{display:block;clear:both;margin-top:.35rem;color:var(--ink);font-size:.72rem}.indicator-grid p{min-height:42px;margin:.25rem 0;color:#6d788d;font-size:.62rem;line-height:1.4}.indicator-grid small{display:flex;align-items:flex-start;gap:.25rem;color:#718097;font-size:.56rem}.regulatory-panel p{margin:0;color:#59667b;font-size:.68rem;line-height:1.6;white-space:pre-line}.regulatory-panel.needs-review{border-color:#ead6b4;background:#fffcf6}.action-toolbar{display:grid;grid-template-columns:1fr 220px;gap:.65rem}.action-toolbar .form-control,.action-toolbar .form-select{border-radius:10px}.month-pill{display:inline-flex;padding:.28rem .48rem;color:#4257c7;font-size:.62rem;font-weight:850;border-radius:8px;background:#edf0ff}.action-title b,.action-title small{display:block}.action-title b{color:var(--ink);font-size:.7rem}.action-title small{margin-top:.2rem;color:var(--muted);font-size:.56rem}.status-chip{display:inline-flex;padding:.27rem .5rem;color:#5c687e;font-size:.58rem;font-weight:850;border-radius:999px;background:#eef1f5}.status-chip.is-en_ejecucion{color:#2862ad;background:#eaf3ff}.status-chip.is-completada,.status-chip.is-realizada{color:#1f745f;background:#e9f8f3}.status-chip.is-postergada{color:#98631a;background:#fff4df}.status-chip.is-cancelada{color:#8b4650;background:#fff0f1}.progress-cell{display:flex;align-items:center;gap:.4rem}.progress-cell>span{width:58px;height:6px;overflow:hidden;border-radius:99px;background:#e9edf3}.progress-cell i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--indigo),#2e9f83)}.progress-cell b{color:var(--indigo);font-size:.61rem}.action-detail-panel{margin-top:.9rem}.action-detail-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.action-detail-panel>header h3{margin:.2rem 0;color:var(--ink);font-size:1.05rem}.action-detail-panel>header p{margin:0;color:var(--muted);font-size:.68rem}.action-detail-panel>header>div:last-child{display:flex;gap:.5rem}.detail-close{display:grid;width:34px;height:34px;color:#657187;font-size:1.25rem;place-items:center;border:0;border-radius:10px;background:#eef1f5}.action-detail-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem;margin:.8rem 0}.action-detail-meta span{display:flex;align-items:flex-start;gap:.35rem;padding:.6rem;color:#5e6a80;font-size:.61rem;border-radius:11px;background:#f7f8fb}.action-detail-meta i{color:var(--indigo);font-size:.9rem}.activity-list{display:grid;gap:.6rem}.activity-list article{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:.7rem;padding:.75rem;border:1px solid #e4e9f1;border-radius:14px;background:#fff}.activity-date{display:flex;height:52px;align-items:center;justify-content:center;flex-direction:column;color:#fff;border-radius:13px;background:linear-gradient(145deg,var(--indigo),#3448b4)}.activity-date b{font-size:1.05rem}.activity-date span{font-size:.55rem;font-weight:850;text-transform:uppercase}.activity-main>div:first-child{display:flex;align-items:center;gap:.45rem}.activity-main>div:first-child small{color:var(--muted);font-size:.56rem}.activity-main h4{margin:.35rem 0 .18rem;color:var(--ink);font-size:.76rem}.activity-main>p{margin:0;color:#718097;font-size:.6rem}.activity-results{margin-top:.45rem;padding:.45rem;color:#5b687d;font-size:.61rem;border-radius:8px;background:#f7f9fc}.activity-results b{margin-right:.35rem;color:var(--ink)}.activity-files{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.45rem}.activity-files a{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .4rem;color:#4056c5;font-size:.56rem;border-radius:7px;background:#edf0ff}.activity-actions{display:flex;align-items:flex-end;justify-content:center;flex-direction:column;gap:.3rem}.activity-actions button{display:inline-flex;align-items:center;gap:.25rem;padding:.35rem .48rem;color:#4b5fbe;font-size:.56rem;font-weight:800;border:1px solid #d8def6;border-radius:8px;background:#f8f9ff}.activity-actions button.danger{color:#af4551;border-color:#eed1d5;background:#fff8f8}.activity-empty{display:flex;align-items:center;justify-content:center;flex-direction:column;padding:1.6rem;color:var(--muted);text-align:center;border:1px dashed #d8deea;border-radius:14px}.activity-empty i{color:var(--indigo);font-size:1.7rem}.activity-empty b{margin-top:.3rem;color:var(--ink);font-size:.72rem}.activity-empty p{margin:.2rem 0;font-size:.62rem}.calendar-panel{overflow:hidden}.calendar-legend{display:flex;flex-wrap:wrap;gap:.8rem;margin-bottom:.8rem;color:#6d788c;font-size:.61rem}.calendar-legend span{display:flex;align-items:center;gap:.3rem}.calendar-legend i{width:9px;height:9px;border-radius:3px;background:var(--indigo)}.calendar-legend .progress{background:var(--blue)}.calendar-legend .done{background:var(--green)}.calendar-legend .cancelled{background:#9ba6b2}.versions-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.versions-panel>header h3{margin:.2rem 0;color:var(--ink);font-size:1rem}.versions-panel>header p{margin:0;color:var(--muted);font-size:.65rem}.versions-panel>header>span{padding:.35rem .6rem;color:var(--indigo);font-size:.62rem;font-weight:850;border-radius:999px;background:#edf0ff}.version-timeline{display:grid;gap:.55rem;margin-top:1rem}.version-timeline article{display:grid;grid-template-columns:46px 1fr auto;align-items:center;gap:.7rem;padding:.7rem;border:1px solid #e3e8f0;border-radius:13px}.version-timeline article.current{border-color:#bcc7f5;background:#f7f8ff}.version-number{display:grid;width:42px;height:42px;color:#fff;font-size:.67rem;font-weight:900;place-items:center;border-radius:12px;background:#7d8799}.version-timeline .current .version-number{background:linear-gradient(145deg,var(--indigo),#394db9)}.version-timeline b,.version-timeline span{display:block}.version-timeline b{color:var(--ink);font-size:.68rem}.version-timeline span{margin-top:.18rem;color:var(--muted);font-size:.57rem}.version-timeline em{padding:.25rem .45rem;color:#24745f;font-size:.57rem;font-weight:850;font-style:normal;border-radius:999px;background:#e9f8f3}.version-timeline button{display:flex;align-items:center;gap:.25rem;padding:.36rem .5rem;color:#4a5fc1;font-size:.58rem;font-weight:800;border:1px solid #d9dff4;border-radius:8px;background:#f8f9ff}.plan-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.plan-form-grid label{min-width:0}.plan-form-grid label>span{display:block;margin-bottom:.3rem;color:#42506a;font-size:.64rem;font-weight:800}.plan-form-grid .wide{grid-column:1/-1}.plan-form-grid .form-control,.plan-form-grid .form-select{border-color:#dce2ec;border-radius:10px}.form-collection{margin:1rem 0;padding-top:1rem;border-top:1px solid #e6eaf1}.form-collection>header,.complex-repeat>header{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.6rem}.form-collection>header b,.form-collection>header small{display:block}.form-collection>header b{color:var(--ink);font-size:.75rem}.form-collection>header small{margin-top:.15rem;color:var(--muted);font-size:.59rem}.form-collection>header button,.complex-repeat>header button,.simple-repeat>button{display:inline-flex;align-items:center;gap:.25rem;padding:.38rem .5rem;color:#465bc3;font-size:.59rem;font-weight:800;border:1px solid #d8def5;border-radius:8px;background:#f7f8ff}.simple-repeat{display:grid;grid-template-columns:1fr 34px;gap:.45rem;margin-bottom:.45rem}.simple-repeat>button{justify-content:center;color:#ad4450}.complex-repeat{margin-bottom:.65rem;padding:.75rem;border:1px solid #e3e7ef;border-radius:12px;background:#fbfcfe}.complex-repeat>header b{color:var(--indigo);font-size:.64rem}.complex-repeat>header button{color:#ad4450}.switch-label{padding:.6rem;border-radius:10px;background:#fff7e9}.change-summary{padding:.65rem;border:1px solid #cad2f6;border-radius:11px;background:#f6f7ff}.activity-progress-note{display:flex;align-items:flex-start;gap:.5rem;margin-bottom:.8rem;padding:.7rem;color:#53617a;font-size:.64rem;border-radius:11px;background:#eef3ff}.activity-progress-note i{color:var(--indigo);font-size:1.1rem}:deep(.fc){--fc-border-color:#e4e8ef;--fc-button-bg-color:#4f63d9;--fc-button-border-color:#4f63d9;--fc-button-hover-bg-color:#3d50bc;--fc-button-hover-border-color:#3d50bc;font-size:.72rem}:deep(.fc .fc-toolbar-title){color:var(--ink);font-size:1.05rem;text-transform:capitalize}:deep(.fc .fc-button){border-radius:8px;font-size:.66rem;font-weight:750;text-transform:capitalize}:deep(.fc-event){padding:2px 3px;border-radius:6px;cursor:pointer}
.source-documents-panel>header>button{margin-left:auto}.source-documents{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem}.source-documents>a{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:.6rem;padding:.65rem;color:inherit;border:1px solid #e3e8f1;border-radius:12px;background:#fbfcfe}.source-documents>a:hover{border-color:#bcc7f5;background:#f7f8ff}.source-documents>a>span{display:grid;width:38px;height:38px;color:#fff;font-size:1.05rem;place-items:center;border-radius:10px;background:linear-gradient(145deg,var(--indigo),#3448b4)}.source-documents b,.source-documents small{display:block}.source-documents b{overflow:hidden;color:var(--ink);font-size:.67rem;text-overflow:ellipsis;white-space:nowrap}.source-documents small{margin-top:.15rem;color:var(--muted);font-size:.56rem}.source-documents>a>i{color:var(--indigo);font-size:1.1rem}.source-documents__empty{display:flex;grid-column:1/-1;align-items:flex-start;gap:.5rem;padding:.7rem;color:#657187;font-size:.64rem;border-radius:11px;background:#f6f8fb}.source-documents__empty>i{color:var(--indigo);font-size:1rem}
.annual-plan-kpis{grid-template-columns:repeat(5,minmax(0,1fr))}.annual-plan-kpis .is-violet{background:linear-gradient(145deg,#7456c7,#4f63d9)}.weight-allocation{display:grid;grid-template-columns:auto minmax(180px,1fr) auto;align-items:center;gap:.75rem;padding:.72rem .9rem;border:1px solid #dfe5ef;border-radius:15px;background:#fff}.weight-allocation b,.weight-allocation span{display:block}.weight-allocation b{color:var(--ink);font-size:.7rem}.weight-allocation span,.weight-allocation small{color:var(--muted);font-size:.58rem}.weight-allocation__track{height:9px;overflow:hidden;border-radius:99px;background:#e9edf5}.weight-allocation__track i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#596cdf,#2e9f83)}.weight-allocation.complete{border-color:#cde9df;background:#f9fffc}.multi-year-badge,.weight-pill,.activity-type-badge{display:inline-flex;align-items:center;gap:.25rem;border-radius:999px;font-size:.55rem;font-weight:850}.multi-year-badge{margin-top:.3rem;padding:.22rem .4rem;color:#286f7a;background:#e8f7f8}.weight-pill{padding:.3rem .5rem;color:#5744aa;background:#f0edff}.weighted-impact{display:block;margin-top:.22rem;color:#7b8495;font-size:.52rem}.action-progress-summary{display:grid;grid-template-columns:94px 1fr;align-items:center;gap:.75rem;margin:.85rem 0;padding:.75rem;border:1px solid #dce3ef;border-radius:15px;background:linear-gradient(135deg,#f8f9ff,#f4fbf8)}.action-progress-summary>div{display:grid;width:76px;height:76px;place-items:center;align-content:center;color:#fff;border-radius:50%;background:linear-gradient(145deg,#4f63d9,#2e9f83)}.action-progress-summary>div b{font-size:1.15rem}.action-progress-summary>div span{font-size:.5rem;text-transform:uppercase}.action-progress-summary section header{display:flex;justify-content:space-between;gap:.5rem;color:var(--ink);font-size:.62rem}.action-progress-summary section header span,.action-progress-summary section small{color:var(--muted);font-size:.55rem}.action-progress-summary section>div{height:8px;margin:.45rem 0;overflow:hidden;border-radius:99px;background:#e1e7ef}.action-progress-summary section>div i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#4f63d9,#2e9f83)}.activity-type-badge{padding:.26rem .45rem;color:var(--activity-color);border:1px solid color-mix(in srgb,var(--activity-color) 25%,white);background:color-mix(in srgb,var(--activity-color) 9%,white)}.activity-type-picker{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.45rem;margin:0 0 .9rem;padding:0;border:0}.activity-type-picker legend{grid-column:1/-1;margin:0 0 .1rem;color:#42506a;font-size:.64rem;font-weight:800}.activity-type-picker button{display:flex;min-height:58px;align-items:center;gap:.45rem;padding:.55rem;color:#526078;border:1px solid #dfe5ee;border-radius:11px;background:#fff;font-size:.6rem;font-weight:750;text-align:left}.activity-type-picker button i{display:grid;width:29px;height:29px;flex:0 0 29px;color:var(--type-color);font-size:1rem;place-items:center;border-radius:8px;background:color-mix(in srgb,var(--type-color) 10%,white)}.activity-type-picker button.active{color:var(--type-color);border-color:var(--type-color);box-shadow:0 0 0 2px color-mix(in srgb,var(--type-color) 12%,transparent)}.clone-year-callout{display:flex;align-items:flex-start;gap:.65rem;margin-bottom:.85rem;padding:.75rem;color:#53617a;border-radius:12px;background:#eef2ff}.clone-year-callout>i{color:var(--indigo);font-size:1.25rem}.clone-year-callout b,.clone-year-callout span{display:block}.clone-year-callout b{color:var(--ink);font-size:.68rem}.clone-year-callout span{margin-top:.18rem;font-size:.59rem;line-height:1.45}
@media(max-width:1199px){.annual-plan-command{align-items:flex-start;flex-direction:column}.annual-plan-command__actions{width:100%;flex-wrap:wrap}.annual-plan-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.indicator-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.annual-overview-grid{grid-template-columns:1fr}.plan-panel--wide{grid-column:auto}}
@media(max-width:767px){.annual-plan-command__actions{display:grid;grid-template-columns:1fr 1fr}.annual-plan-command__actions label{grid-column:1/-1}.annual-plan-command__actions select{width:100%}.annual-plan-hero{align-items:flex-start;flex-direction:column}.annual-progress-ring{align-self:center}.annual-plan-kpis{grid-template-columns:1fr 1fr}.weight-allocation{grid-template-columns:1fr}.indicator-grid{grid-template-columns:1fr}.action-toolbar{grid-template-columns:1fr}.action-detail-panel>header{flex-direction:column}.action-progress-summary{grid-template-columns:1fr}.action-detail-meta{grid-template-columns:1fr}.activity-list article{grid-template-columns:44px minmax(0,1fr)}.activity-actions{grid-column:1/-1;align-items:stretch;flex-direction:row}.activity-type-picker{grid-template-columns:repeat(2,minmax(0,1fr))}.plan-form-grid{grid-template-columns:1fr}.plan-form-grid .wide{grid-column:auto}.calendar-panel{overflow-x:auto}.calendar-panel :deep(.fc){min-width:680px}.version-timeline article{grid-template-columns:42px 1fr}.version-timeline article>em,.version-timeline article>button{grid-column:2;justify-self:start}}
@media(max-width:767px){.source-documents{grid-template-columns:1fr}.source-documents-panel>header{align-items:flex-start;flex-wrap:wrap}.source-documents-panel>header>button{width:100%;margin-left:0}}
@media(max-width:440px){.annual-plan-command__actions{grid-template-columns:1fr}.annual-plan-command__actions label{grid-column:auto}.annual-plan-kpis{grid-template-columns:1fr}.annual-plan-hero h3{font-size:1.25rem}.activity-list article{grid-template-columns:1fr}.activity-date{width:52px}.activity-actions{grid-column:auto;flex-wrap:wrap}}
</style>
