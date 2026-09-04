<script>
import Layout from "../../layouts/main.vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import Swal from "sweetalert2";
import orientationApi from "../../services/orientation-api";
import {
  downloadOrientationActionPdf,
  downloadOrientationPlanPdf,
  downloadOrientationStatisticsPdf,
} from "../../utils/orientation-report-pdf";

const currentYear = new Date().getFullYear();
const pad = (value) => String(value).padStart(2, "0");
const localDate = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const toLocalDateTimeInput = (value) => {
  if (!value) return "";
  const date = new Date(value);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const blankPlan = (year) => ({
  year,
  title: `Plan Anual de Orientación ${year}`,
  general_objective: "",
  description: "",
  status: "draft",
  owner_user_id: "",
});

const blankAction = () => ({
  id: null,
  title: "",
  objective: "",
  description: "",
  target_levels: "",
  planned_verification_means: "",
  material_resources: "",
  responsible_summary: "",
  start_date: "",
  end_date: "",
  status: "planned",
  progress: 0,
  progress_automatic: false,
  sort_order: null,
  responsible_user_ids: [],
  related_plan_ids: [],
});

const blankRelatedPlan = () => ({
  id: null,
  name: "",
  category: "institutional",
  description: "",
  reference_url: "",
  status: "active",
});

const blankActivity = () => ({
  id: null,
  title: "",
  description: "",
  starts_at: "",
  ends_at: "",
  status: "scheduled",
  contribution_percent: 0,
  completion_percent: 0,
  location: "",
  participants: "",
  attendee_count: null,
  results: "",
});

const blankEvidence = () => ({
  title: "",
  evidence_type: "photograph",
  description: "",
  occurred_on: localDate(),
  orientation_activity_id: "",
  external_url: "",
  file: null,
});

export default {
  components: { Layout, FullCalendar },
  data() {
    return {
      loading: false,
      saving: false,
      deletingActionId: null,
      detailLoading: false,
      statisticsLoading: false,
      exportingPdf: "",
      error: "",
      success: "",
      selectedYear: currentYear,
      plan: null,
      responsibleUsers: [],
      statusOptions: { plans: [], actions: [], activities: [] },
      capabilities: {},
      availableYears: [],
      activeAction: null,
      statistics: null,
      actionSearch: "",
      modal: null,
      planForm: blankPlan(currentYear),
      actionForm: blankAction(),
      relatedPlanForm: blankRelatedPlan(),
      activityForm: blankActivity(),
      activityContributionLimit: 100,
      evidenceForm: blankEvidence(),
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        timeZone: "local",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
        firstDay: 1,
        height: "auto",
        nowIndicator: true,
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
        },
        buttonText: { today: "Hoy", month: "Mes", week: "Semana", day: "Día", list: "Agenda" },
        noEventsText: "No hay acciones ni actividades programadas",
        allDayText: "Todo el día",
        events: (info, success, failure) => this.fetchCalendarEvents(info, success, failure),
        eventClick: ({ event }) => this.openCalendarEvent(event),
      },
    };
  },
  computed: {
    isCalendar() {
      return this.$route.path.endsWith("/calendario");
    },
    isStatistics() {
      return this.$route.path.endsWith("/estadisticas");
    },
    yearOptions() {
      const values = new Set(this.availableYears || []);
      for (let year = currentYear - 3; year <= currentYear + 5; year += 1) values.add(year);
      return [...values].sort((a, b) => b - a);
    },
    filteredActions() {
      const search = this.actionSearch.trim().toLocaleLowerCase("es");
      if (!search) return this.plan?.actions || [];
      return (this.plan?.actions || []).filter((action) =>
        [action.title, action.objective, action.target_levels, action.responsible_summary]
          .filter(Boolean)
          .some((value) => String(value).toLocaleLowerCase("es").includes(search))
      );
    },
    stats() {
      return this.plan?.stats || {
        actions: 0,
        completed_actions: 0,
        activities: 0,
        evidences: 0,
        progress: 0,
      };
    },
    selectedYearHasPlan() {
      return Boolean(this.plan);
    },
    statisticsSummary() {
      return this.statistics?.summary || {};
    },
    maximumMonthlyWorkload() {
      return Math.max(1, ...(this.statistics?.monthly || []).flatMap((item) => [item.actions, item.activities]));
    },
  },
  watch: {
    "$route.path"() {
      this.$nextTick(() => {
        if (this.isCalendar) this.$refs.orientationCalendar?.getApi()?.refetchEvents();
        if (this.isStatistics && this.plan) this.loadStatistics();
      });
    },
  },
  mounted() {
    this.loadContext();
  },
  methods: {
    async loadContext({ preserveAction = false } = {}) {
      this.loading = true;
      this.error = "";
      try {
        const response = await orientationApi.context(this.selectedYear);
        this.plan = response.data.data;
        this.availableYears = response.data.available_years || [];
        this.responsibleUsers = response.data.responsible_users || [];
        this.statusOptions = response.data.status_options || this.statusOptions;
        this.capabilities = response.data.capabilities || {};
        this.statistics = null;
        if (!preserveAction) this.activeAction = null;
        else if (this.activeAction) await this.loadAction(this.activeAction.id);
        if (this.plan && this.isStatistics) await this.loadStatistics();

        this.$nextTick(() => {
          const calendar = this.$refs.orientationCalendar?.getApi();
          if (calendar && this.plan) {
            calendar.gotoDate(`${this.plan.year}-03-01`);
            calendar.refetchEvents();
          }
        });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    changeYear() {
      this.selectedYear = Number(this.selectedYear);
      this.loadContext();
    },
    openCreatePlan() {
      this.error = "";
      this.planForm = blankPlan(this.selectedYear);
      this.modal = "plan";
    },
    openEditPlan() {
      this.error = "";
      this.planForm = {
        year: this.plan.year,
        title: this.plan.title,
        general_objective: this.plan.general_objective || "",
        description: this.plan.description || "",
        status: this.plan.status,
        owner_user_id: this.plan.owner_user_id || "",
      };
      this.modal = "plan";
    },
    async savePlan() {
      this.saving = true;
      this.error = "";
      try {
        const payload = { ...this.planForm, owner_user_id: this.planForm.owner_user_id || null };
        const response = this.plan
          ? await orientationApi.updatePlan(this.plan.id, payload)
          : await orientationApi.createPlan(payload);
        this.success = response.data.message;
        this.modal = null;
        await this.loadContext();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    openCreateAction() {
      this.error = "";
      this.actionForm = blankAction();
      this.modal = "action";
    },
    openEditAction(action = this.activeAction) {
      this.error = "";
      this.actionForm = {
        ...blankAction(),
        ...action,
        responsible_user_ids: (action.responsible_user_ids || []).map(Number),
        related_plan_ids: (action.related_plan_ids || []).map(Number),
        start_date: action.start_date || "",
        end_date: action.end_date || "",
        progress_automatic: Number(action.activity_contribution?.allocated || action.activity_contribution_allocated || 0) > 0,
      };
      this.modal = "action";
    },
    async saveAction() {
      this.saving = true;
      this.error = "";
      try {
        const payload = {
          ...this.actionForm,
          start_date: this.actionForm.start_date || null,
          end_date: this.actionForm.end_date || null,
          sort_order: this.actionForm.sort_order || null,
        };
        const response = payload.id
          ? await orientationApi.updateAction(payload.id, payload)
          : await orientationApi.createAction(this.plan.id, payload);
        this.success = response.data.message;
        this.activeAction = response.data.data;
        this.modal = null;
        await this.loadContext({ preserveAction: true });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async loadAction(id) {
      this.detailLoading = true;
      try {
        const response = await orientationApi.action(id);
        this.activeAction = response.data.data;
        return this.activeAction;
      } catch (error) {
        this.error = this.formatError(error);
        return null;
      } finally {
        this.detailLoading = false;
      }
    },
    async viewAction(action) {
      if (!(await this.loadAction(action.id))) return;
      this.$nextTick(() => document.querySelector(".orientation-detail")?.scrollIntoView({ behavior: "smooth", block: "start" }));
    },
    async editActionFromTable(action) {
      const detail = await this.loadAction(action.id);
      if (detail) this.openEditAction(detail);
    },
    async addActivityToAction(action) {
      const detail = await this.loadAction(action.id);
      if (detail) this.openCreateActivity();
    },
    async deleteAction(action) {
      const result = await Swal.fire({
        title: "Eliminar acción",
        text: `Se eliminará “${action.title}” junto con sus actividades y evidencias. Esta operación no se puede deshacer.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#c23b53",
        cancelButtonColor: "#6b7485",
        reverseButtons: true,
        focusCancel: true,
      });
      if (!result.isConfirmed) return;

      this.deletingActionId = action.id;
      this.error = "";
      try {
        const response = await orientationApi.deleteAction(action.id);
        this.success = response.data.message;
        if (this.activeAction?.id === action.id) this.activeAction = null;
        await this.loadContext();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.deletingActionId = null;
      }
    },
    async loadStatistics() {
      if (!this.plan) return;
      this.statisticsLoading = true;
      try {
        const response = await orientationApi.statistics(this.plan.id);
        this.statistics = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.statisticsLoading = false;
      }
    },
    async exportPlanPdf() {
      this.exportingPdf = "plan";
      this.error = "";
      try {
        await downloadOrientationPlanPdf(this.plan);
      } catch (error) {
        this.error = `No fue posible generar el informe general. ${this.formatError(error)}`;
      } finally {
        this.exportingPdf = "";
      }
    },
    async exportActionPdf() {
      if (!this.activeAction) return;
      this.exportingPdf = "action";
      this.error = "";
      try {
        await downloadOrientationActionPdf(this.plan, this.activeAction);
      } catch (error) {
        this.error = `No fue posible generar el informe de la acción. ${this.formatError(error)}`;
      } finally {
        this.exportingPdf = "";
      }
    },
    async exportStatisticsPdf() {
      this.exportingPdf = "statistics";
      this.error = "";
      try {
        if (!this.statistics) await this.loadStatistics();
        if (this.statistics) await downloadOrientationStatisticsPdf(this.plan, this.statistics);
      } catch (error) {
        this.error = `No fue posible generar el informe estadístico. ${this.formatError(error)}`;
      } finally {
        this.exportingPdf = "";
      }
    },
    async openStatisticsAction(actionId) {
      if (!(await this.loadAction(actionId))) return;
      await this.$router.push({ name: "orientation-annual-plan" });
      this.$nextTick(() => document.querySelector(".orientation-detail")?.scrollIntoView({ behavior: "smooth", block: "start" }));
    },
    openCreateRelatedPlan() {
      this.error = "";
      this.relatedPlanForm = blankRelatedPlan();
      this.modal = "related-plan";
    },
    openEditRelatedPlan(relatedPlan) {
      this.error = "";
      this.relatedPlanForm = { ...blankRelatedPlan(), ...relatedPlan };
      this.modal = "related-plan";
    },
    async saveRelatedPlan() {
      this.saving = true;
      this.error = "";
      try {
        const payload = {
          ...this.relatedPlanForm,
          reference_url: this.relatedPlanForm.reference_url || null,
        };
        const response = payload.id
          ? await orientationApi.updateRelatedPlan(payload.id, payload)
          : await orientationApi.createRelatedPlan(this.plan.id, payload);
        this.success = response.data.message;
        this.modal = null;
        await this.loadContext({ preserveAction: Boolean(this.activeAction) });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    openCreateActivity() {
      this.error = "";
      const date = this.activeAction?.start_date || localDate();
      const remaining = Number(this.activeAction?.activity_contribution?.remaining ?? 100);
      this.activityContributionLimit = remaining;
      this.activityForm = {
        ...blankActivity(),
        contribution_percent: Math.min(10, remaining),
        starts_at: `${date}T09:00`,
        ends_at: `${date}T10:00`,
      };
      this.modal = "activity";
    },
    openEditActivity(activity) {
      this.error = "";
      this.activityContributionLimit = Math.min(100, Number(this.activeAction?.activity_contribution?.remaining || 0) + Number(activity.contribution_percent || 0));
      this.activityForm = {
        ...blankActivity(),
        ...activity,
        starts_at: toLocalDateTimeInput(activity.starts_at),
        ends_at: toLocalDateTimeInput(activity.ends_at),
      };
      this.modal = "activity";
    },
    syncActivityCompletion() {
      if (this.activityForm.status === "completed") this.activityForm.completion_percent = 100;
      else if (["scheduled", "cancelled"].includes(this.activityForm.status)) this.activityForm.completion_percent = 0;
      else if (!this.activityForm.completion_percent) this.activityForm.completion_percent = 25;
    },
    async saveActivity() {
      this.saving = true;
      this.error = "";
      try {
        const payload = {
          ...this.activityForm,
          ends_at: this.activityForm.ends_at || null,
          attendee_count: this.activityForm.attendee_count === "" ? null : this.activityForm.attendee_count,
        };
        const response = payload.id
          ? await orientationApi.updateActivity(payload.id, payload)
          : await orientationApi.createActivity(this.activeAction.id, payload);
        this.success = response.data.message;
        this.activeAction = response.data.data;
        this.modal = null;
        await this.loadContext({ preserveAction: true });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    openCreateEvidence() {
      this.error = "";
      this.evidenceForm = blankEvidence();
      this.modal = "evidence";
    },
    onEvidenceFile(event) {
      this.evidenceForm.file = event.target.files?.[0] || null;
    },
    async saveEvidence() {
      this.saving = true;
      this.error = "";
      try {
        const formData = new FormData();
        Object.entries(this.evidenceForm).forEach(([key, value]) => {
          if (value !== null && value !== "") formData.append(key, value);
        });
        const response = await orientationApi.createEvidence(this.activeAction.id, formData);
        this.success = response.data.message;
        this.modal = null;
        await this.loadContext({ preserveAction: true });
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async fetchCalendarEvents(info, successCallback, failureCallback) {
      if (!this.plan) {
        successCallback([]);
        return;
      }
      try {
        const response = await orientationApi.calendar(
          this.plan.id,
          info.startStr.slice(0, 10),
          info.endStr.slice(0, 10)
        );
        successCallback(response.data.data || []);
      } catch (error) {
        this.error = this.formatError(error);
        failureCallback(error);
      }
    },
    async openCalendarEvent(event) {
      const actionId = event.extendedProps.action_id;
      if (!actionId) return;
      await this.loadAction(actionId);
      await this.$router.push({ name: "orientation-annual-plan" });
      this.$nextTick(() => document.querySelector(".orientation-detail")?.scrollIntoView({ behavior: "smooth", block: "start" }));
    },
    statusLabel(type, value) {
      return (this.statusOptions[type] || []).find((option) => option.value === value)?.label || value;
    },
    statusTone(value) {
      return {
        active: "success",
        completed: "success",
        in_progress: "primary",
        planned: "violet",
        scheduled: "violet",
        postponed: "warning",
        cancelled: "muted",
        archived: "muted",
        draft: "warning",
      }[value] || "muted";
    },
    formatDate(value, includeTime = false) {
      if (!value) return "Sin fecha";
      const source = includeTime ? new Date(value) : new Date(`${String(value).slice(0, 10)}T12:00:00`);
      return new Intl.DateTimeFormat("es-CL", includeTime
        ? { dateStyle: "medium", timeStyle: "short" }
        : { day: "2-digit", month: "short", year: "numeric" }).format(source);
    },
    fileSize(bytes) {
      if (!bytes) return "Enlace externo";
      if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },
    closeModal() {
      if (!this.saving) this.modal = null;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors;
      if (errors) return Object.values(errors).flat().join(" ");
      return error?.response?.data?.message || error?.message || "No fue posible completar la operación.";
    },
  },
};
</script>

<template>
  <Layout>
    <main class="orientation-page">
      <section class="orientation-hero">
        <div class="orientation-hero__glow orientation-hero__glow--one"></div>
        <div class="orientation-hero__glow orientation-hero__glow--two"></div>
        <div class="orientation-hero__content">
          <div class="orientation-kicker"><i class="bx bx-compass"></i> Formación integral</div>
          <h1>Plan Anual de Orientación</h1>
          <p>Diseña las acciones del año, conecta planes institucionales y documenta su ejecución desde un calendario común.</p>
          <div class="orientation-hero__nav" aria-label="Vistas del módulo">
            <router-link :to="{ name: 'orientation-annual-plan' }"><i class="bx bx-grid-alt"></i> Plan anual</router-link>
            <router-link :to="{ name: 'orientation-calendar' }"><i class="bx bx-calendar"></i> Calendario</router-link>
            <router-link :to="{ name: 'orientation-statistics' }"><i class="bx bx-bar-chart-alt-2"></i> Estadísticas</router-link>
          </div>
        </div>
        <div class="orientation-year-card">
          <label for="orientation-year">Año del plan</label>
          <select id="orientation-year" v-model="selectedYear" class="form-select" @change="changeYear">
            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
          </select>
          <span><i class="bx bx-lock-alt"></i> Un plan único por año calendario</span>
        </div>
      </section>

      <div v-if="error" class="orientation-alert orientation-alert--danger" role="alert">
        <i class="bx bx-error-circle"></i><span>{{ error }}</span><button type="button" aria-label="Cerrar" @click="error = ''">×</button>
      </div>
      <div v-if="success" class="orientation-alert orientation-alert--success" role="status">
        <i class="bx bx-check-circle"></i><span>{{ success }}</span><button type="button" aria-label="Cerrar" @click="success = ''">×</button>
      </div>

      <section v-if="loading" class="orientation-loading" aria-live="polite">
        <span class="orientation-spinner"></span><strong>Cargando plan {{ selectedYear }}…</strong>
      </section>

      <section v-else-if="!selectedYearHasPlan" class="orientation-empty">
        <div class="orientation-empty__icon"><i class="bx bx-calendar-plus"></i></div>
        <span class="orientation-empty__eyebrow">Periodo disponible</span>
        <h2>Aún no existe un plan para {{ selectedYear }}</h2>
        <p>Crea el único Plan de Orientación de este año para comenzar a definir acciones, responsables y actividades.</p>
        <button v-if="capabilities.can_manage_plan" type="button" class="btn-orientation-primary" @click="openCreatePlan">
          <i class="bx bx-plus"></i> Crear plan {{ selectedYear }}
        </button>
      </section>

      <template v-else>
        <section class="orientation-plan-strip">
          <div>
            <span class="status-pill" :class="`status-pill--${statusTone(plan.status)}`">{{ statusLabel('plans', plan.status) }}</span>
            <h2>{{ plan.title }}</h2>
            <p>{{ plan.general_objective || "Define el objetivo general que orientará el trabajo del año." }}</p>
          </div>
          <div class="orientation-plan-strip__actions">
            <button type="button" class="btn-orientation-primary" :disabled="exportingPdf === 'plan'" @click="exportPlanPdf">
              <span v-if="exportingPdf === 'plan'" class="orientation-spinner orientation-spinner--button"></span><i v-else class="bx bx-download"></i> {{ exportingPdf === 'plan' ? 'Generando…' : 'Informe general PDF' }}
            </button>
            <button v-if="capabilities.can_manage_plan" type="button" class="btn-orientation-ghost" @click="openEditPlan">
              <i class="bx bx-edit-alt"></i> Editar plan
            </button>
          </div>
        </section>

        <section class="orientation-stats" aria-label="Resumen del plan">
          <article><span class="stat-icon stat-icon--violet"><i class="bx bx-list-check"></i></span><div><strong>{{ stats.actions }}</strong><span>Acciones</span></div></article>
          <article><span class="stat-icon stat-icon--blue"><i class="bx bx-run"></i></span><div><strong>{{ stats.activities }}</strong><span>Actividades</span></div></article>
          <article><span class="stat-icon stat-icon--green"><i class="bx bx-folder-open"></i></span><div><strong>{{ stats.evidences }}</strong><span>Evidencias</span></div></article>
          <article class="orientation-progress-stat">
            <div class="orientation-progress-stat__top"><span>Avance global</span><strong>{{ stats.progress }}%</strong></div>
            <div class="orientation-progress"><span :style="{ width: `${stats.progress}%` }"></span></div>
            <small>{{ stats.completed_actions }} acciones completadas</small>
          </article>
        </section>

        <section v-if="isCalendar" class="orientation-calendar-card">
          <header>
            <div><span class="section-eyebrow">Agenda centralizada</span><h2>Calendario {{ plan.year }}</h2><p>Las barras muestran acciones; los eventos con hora corresponden a actividades ejecutables.</p></div>
            <button v-if="capabilities.can_manage_plan" type="button" class="btn-orientation-primary" @click="openCreateAction"><i class="bx bx-plus"></i> Nueva acción</button>
          </header>
          <div class="orientation-calendar-legend"><span><i class="legend-dot legend-dot--planned"></i> Planificada</span><span><i class="legend-dot legend-dot--progress"></i> En ejecución</span><span><i class="legend-dot legend-dot--done"></i> Completada</span><span><i class="legend-dot legend-dot--postponed"></i> Postergada</span></div>
          <FullCalendar ref="orientationCalendar" :options="calendarOptions" />
        </section>

        <section v-else-if="isStatistics" class="orientation-statistics-view">
          <header class="orientation-statistics-view__header">
            <div><span class="section-eyebrow">Lectura ejecutiva</span><h2>Estadísticas del plan {{ plan.year }}</h2><p>Avance, ejecución y cobertura documental consolidados para la toma de decisiones.</p></div>
            <button type="button" class="btn-orientation-primary" :disabled="statisticsLoading || exportingPdf === 'statistics'" @click="exportStatisticsPdf">
              <span v-if="exportingPdf === 'statistics'" class="orientation-spinner orientation-spinner--button"></span><i v-else class="bx bxs-file-pdf"></i> {{ exportingPdf === 'statistics' ? 'Generando…' : 'Exportar estadísticas PDF' }}
            </button>
          </header>

          <div v-if="statisticsLoading" class="orientation-loading orientation-loading--compact"><span class="orientation-spinner"></span><strong>Calculando indicadores…</strong></div>
          <template v-else-if="statistics">
            <div class="orientation-executive-grid">
              <article class="executive-card executive-card--violet"><span><i class="bx bx-trending-up"></i></span><div><small>Avance promedio</small><strong>{{ statisticsSummary.average_progress }}%</strong><p>{{ statisticsSummary.completed_actions }} de {{ statisticsSummary.actions }} acciones completadas</p></div></article>
              <article class="executive-card executive-card--blue"><span><i class="bx bx-shield-quarter"></i></span><div><small>Trazabilidad</small><strong>{{ statisticsSummary.traceability }}%</strong><p>Cobertura promedio de responsables, fechas y respaldos</p></div></article>
              <article class="executive-card executive-card--green"><span><i class="bx bx-check-double"></i></span><div><small>Ejecución</small><strong>{{ statisticsSummary.completed_activities }}/{{ statisticsSummary.activities }}</strong><p>Actividades realizadas sobre las registradas</p></div></article>
              <article class="executive-card executive-card--amber"><span><i class="bx bx-error-alt"></i></span><div><small>Alertas</small><strong>{{ statisticsSummary.overdue_actions + statisticsSummary.unscheduled_actions }}</strong><p>{{ statisticsSummary.overdue_actions }} vencidas · {{ statisticsSummary.unscheduled_actions }} sin fechas completas</p></div></article>
            </div>

            <div class="orientation-insight-grid">
              <article class="orientation-chart-card">
                <header><div><span class="section-eyebrow">Distribución</span><h3>Estado de las acciones</h3></div><strong>{{ statisticsSummary.actions }}</strong></header>
                <div class="orientation-status-bars">
                  <div v-for="item in statistics.action_statuses" :key="item.status">
                    <span><b>{{ item.label }}</b><small>{{ item.count }}</small></span>
                    <div><i :class="`bar-tone--${statusTone(item.status)}`" :style="{ width: `${statisticsSummary.actions ? Math.max(2, item.count / statisticsSummary.actions * 100) : 0}%` }"></i></div>
                  </div>
                </div>
              </article>

              <article class="orientation-chart-card">
                <header><div><span class="section-eyebrow">Control documental</span><h3>Cobertura de trazabilidad</h3></div><strong>{{ statisticsSummary.traceability }}%</strong></header>
                <div class="orientation-traceability-list">
                  <div v-for="item in statistics.traceability" :key="item.label">
                    <span><b>{{ item.label }}</b><small>{{ item.covered }}/{{ item.total }}</small></span>
                    <div><i :style="{ width: `${item.percent}%` }"></i></div>
                    <em>{{ item.percent }}%</em>
                  </div>
                </div>
              </article>
            </div>

            <article class="orientation-monthly-card">
              <header><div><span class="section-eyebrow">Calendario anual</span><h3>Carga mensual</h3><p>Compara el inicio de acciones con las actividades programadas o realizadas.</p></div><div class="monthly-legend"><span><i></i>Acciones</span><span><i></i>Actividades</span></div></header>
              <div class="orientation-monthly-chart" role="img" aria-label="Distribución mensual de acciones y actividades">
                <div v-for="item in statistics.monthly" :key="item.month" class="monthly-column">
                  <div class="monthly-bars"><i :style="{ height: `${Math.max(3, item.actions / maximumMonthlyWorkload * 100)}%` }" :title="`${item.actions} acciones`"><b>{{ item.actions || '' }}</b></i><i :style="{ height: `${Math.max(3, item.activities / maximumMonthlyWorkload * 100)}%` }" :title="`${item.activities} actividades`"><b>{{ item.activities || '' }}</b></i></div>
                  <span>{{ item.label }}</span>
                </div>
              </div>
            </article>

            <div class="orientation-insight-grid orientation-insight-grid--secondary">
              <article class="orientation-chart-card orientation-evidence-summary">
                <header><div><span class="section-eyebrow">Respaldo</span><h3>Tipos de evidencia</h3></div><strong>{{ statisticsSummary.evidences }}</strong></header>
                <div v-if="statistics.evidence_types.length" class="evidence-type-list"><span v-for="item in statistics.evidence_types" :key="item.type"><i class="bx bx-file"></i><b>{{ item.label }}</b><strong>{{ item.count }}</strong></span></div>
                <div v-else class="detail-empty"><i class="bx bx-folder-open"></i><span>Sin evidencias registradas.</span></div>
              </article>
              <article class="orientation-chart-card orientation-activity-summary">
                <header><div><span class="section-eyebrow">Actividades</span><h3>Estado de ejecución</h3></div><strong>{{ statisticsSummary.activities }}</strong></header>
                <div class="activity-status-list"><span v-for="item in statistics.activity_statuses" :key="item.status"><i :class="`status-dot status-dot--${statusTone(item.status)}`"></i><b>{{ item.label }}</b><strong>{{ item.count }}</strong></span></div>
              </article>
            </div>

            <article class="orientation-performance-card">
              <header><div><span class="section-eyebrow">Seguimiento detallado</span><h3>Desempeño por acción</h3><p>Selecciona una fila para abrir su ficha y descargar el informe individual.</p></div></header>
              <div class="orientation-performance-table-wrap">
                <table class="orientation-performance-table">
                  <thead><tr><th>Acción</th><th>Estado</th><th>Avance</th><th>Actividades</th><th>Evidencias</th><th>Planes</th><th>Periodo</th><th></th></tr></thead>
                  <tbody>
                    <tr v-for="action in statistics.action_performance" :key="action.id">
                      <td><strong>{{ action.title }}</strong><small>{{ action.target_levels || 'Niveles por definir' }}</small></td>
                      <td><span class="status-pill" :class="`status-pill--${statusTone(action.status)}`">{{ statusLabel('actions', action.status) }}</span></td>
                      <td><div class="performance-progress"><span><i :style="{ width: `${action.progress}%` }"></i></span><b>{{ action.progress }}%</b></div></td>
                      <td>{{ action.completed_activities_count }}/{{ action.activities_count }}<small class="performance-contribution">{{ action.activity_contribution_allocated }}% distribuido</small></td><td>{{ action.evidences_count }}</td><td>{{ action.related_plans_count }}</td><td>{{ formatDate(action.start_date) }}<br>{{ formatDate(action.end_date) }}</td>
                      <td><button type="button" aria-label="Abrir acción" @click="openStatisticsAction(action.id)"><i class="bx bx-right-arrow-alt"></i></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </article>
          </template>
        </section>

        <div v-else class="orientation-workspace">
          <section class="orientation-actions-panel">
            <header class="orientation-section-header">
              <div><span class="section-eyebrow">Matriz anual</span><h2>Acciones del plan</h2><p>La estructura del documento de referencia, ahora conectada a ejecución y evidencia.</p></div>
              <button v-if="capabilities.can_manage_plan" type="button" class="btn-orientation-primary" @click="openCreateAction"><i class="bx bx-plus"></i> Nueva acción</button>
            </header>
            <label class="orientation-search"><i class="bx bx-search"></i><input v-model="actionSearch" type="search" placeholder="Buscar acción, nivel o responsable…" /></label>

            <div v-if="!filteredActions.length" class="orientation-list-empty">No hay acciones que coincidan con la búsqueda.</div>
            <div v-else class="orientation-action-table-wrap">
              <table class="orientation-action-table">
                <caption class="visually-hidden">Acciones del Plan Anual de Orientación {{ plan.year }}</caption>
                <colgroup><col class="action-col-name"><col class="action-col-planning"><col class="action-col-tracking"><col class="action-col-options"></colgroup>
                <thead>
                  <tr><th>Acción</th><th>Planificación</th><th>Seguimiento</th><th class="action-options-heading">Opciones</th></tr>
                </thead>
                <tbody>
                  <tr v-for="action in filteredActions" :key="action.id" :class="{ 'orientation-action-table__row--active': activeAction?.id === action.id }">
                    <td class="action-table__name">
                      <span class="action-table__rail" :class="`action-table__rail--${statusTone(action.status)}`"></span>
                      <strong>{{ action.title }}</strong>
                      <small>{{ action.responsible_summary || action.responsible_users?.map((user) => user.name).join(', ') || 'Responsable por definir' }}</small>
                    </td>
                    <td class="action-table__planning-cell">
                      <span class="action-table__cell-label">Planificación</span>
                      <div class="action-table__planning">
                        <span class="action-table__secondary"><i class="bx bx-group"></i>{{ action.target_levels || "Por definir" }}</span>
                        <span class="action-table__period"><i class="bx bx-calendar"></i><span>{{ formatDate(action.start_date) }}<small>hasta {{ formatDate(action.end_date) }}</small></span></span>
                      </div>
                    </td>
                    <td class="action-table__tracking-cell">
                      <span class="action-table__cell-label">Seguimiento</span>
                      <div class="action-table__tracking">
                        <div class="action-table__tracking-top">
                          <span class="status-pill" :class="`status-pill--${statusTone(action.status)}`">{{ statusLabel('actions', action.status) }}</span>
                          <span class="action-table__activity-summary"><i class="bx bx-check-circle"></i><strong>{{ action.completed_activities_count }}/{{ action.activities_count }}</strong></span>
                        </div>
                        <div class="action-table__progress"><span><i :style="{ width: `${action.progress}%` }"></i></span><b>{{ action.progress }}%</b></div>
                        <small>{{ action.completed_activities_count }} de {{ action.activities_count }} actividades completadas · {{ action.activity_contribution_allocated || 0 }}% distribuido</small>
                      </div>
                    </td>
                    <td class="action-table__options-cell">
                      <span class="action-table__cell-label">Opciones</span>
                      <div class="action-table__buttons" role="group" :aria-label="`Acciones de ${action.title}`">
                        <button type="button" class="action-table-btn cnsc-action-btn cnsc-action-btn--view" data-cnsc-action-ignore :aria-label="`Ver acción ${action.title}`" title="Ver detalle" @click="viewAction(action)"><i class="mdi mdi-eye-outline" aria-hidden="true"></i></button>
                        <button v-if="capabilities.can_manage_plan" type="button" class="action-table-btn cnsc-action-btn cnsc-action-btn--edit" data-cnsc-action-ignore :aria-label="`Editar acción ${action.title}`" title="Editar acción" @click="editActionFromTable(action)"><i class="mdi mdi-pencil-outline" aria-hidden="true"></i></button>
                        <button v-if="capabilities.can_manage_plan" type="button" class="action-table-btn cnsc-action-btn cnsc-action-btn--delete" data-cnsc-action-ignore :disabled="deletingActionId === action.id" :aria-label="`Eliminar acción ${action.title}`" title="Eliminar acción" @click="deleteAction(action)"><span v-if="deletingActionId === action.id" class="orientation-spinner orientation-spinner--button"></span><i v-else class="mdi mdi-trash-can-outline" aria-hidden="true"></i></button>
                        <button v-if="capabilities.can_manage_execution" type="button" class="action-table-btn cnsc-action-btn cnsc-action-btn--activate" data-cnsc-action-ignore :aria-label="`Agregar actividad a ${action.title}`" title="Agregar actividad" @click="addActivityToAction(action)"><i class="mdi mdi-calendar-plus" aria-hidden="true"></i></button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <aside class="orientation-related-panel">
            <header><div><span class="section-eyebrow">Índice transversal</span><h2>Planes relacionados</h2></div><button v-if="capabilities.can_manage_plan" type="button" aria-label="Agregar plan relacionado" @click="openCreateRelatedPlan"><i class="bx bx-plus"></i></button></header>
            <p>Vincula cada acción al Plan de Afectividad y Sexualidad u otros instrumentos institucionales.</p>
            <div v-if="!plan.related_plans.length" class="related-empty"><i class="bx bx-link"></i><span>Aún no hay planes indexados.</span></div>
            <button v-for="relatedPlan in plan.related_plans" :key="relatedPlan.id" type="button" class="related-plan-card" @click="openEditRelatedPlan(relatedPlan)">
              <span class="related-plan-card__icon"><i class="bx bx-book-bookmark"></i></span>
              <span><strong>{{ relatedPlan.name }}</strong><small>{{ relatedPlan.actions_count }} acciones vinculadas</small></span>
              <i class="bx bx-dots-horizontal-rounded"></i>
            </button>
          </aside>
        </div>

        <section v-if="!isCalendar && !isStatistics && activeAction" class="orientation-detail">
          <header class="orientation-detail__header">
            <div><span class="section-eyebrow">Seguimiento de acción</span><h2>{{ activeAction.title }}</h2><p>{{ activeAction.objective || "Sin objetivo específico registrado." }}</p></div>
            <div class="orientation-detail__actions">
              <button type="button" class="btn-orientation-primary" :disabled="exportingPdf === 'action'" @click="exportActionPdf"><span v-if="exportingPdf === 'action'" class="orientation-spinner orientation-spinner--button"></span><i v-else class="bx bxs-file-pdf"></i> {{ exportingPdf === 'action' ? 'Generando…' : 'Informe de la acción' }}</button>
              <button v-if="capabilities.can_manage_plan" type="button" class="btn-orientation-ghost" @click="openEditAction"><i class="bx bx-edit"></i> Editar acción</button>
            </div>
          </header>

          <div v-if="detailLoading" class="orientation-loading orientation-loading--compact"><span class="orientation-spinner"></span> Actualizando detalle…</div>
          <div v-else class="orientation-detail__grid">
            <article class="orientation-definition-card">
              <h3><i class="bx bx-bullseye"></i> Definición planificada</h3>
              <dl>
                <div><dt>Niveles</dt><dd>{{ activeAction.target_levels || "No informados" }}</dd></div>
                <div><dt>Responsables</dt><dd>{{ activeAction.responsible_users.map((user) => user.name).join(", ") || activeAction.responsible_summary || "No informados" }}</dd></div>
                <div><dt>Medios de verificación</dt><dd>{{ activeAction.planned_verification_means || "No informados" }}</dd></div>
                <div><dt>Recursos materiales</dt><dd>{{ activeAction.material_resources || "No informados" }}</dd></div>
              </dl>
              <div v-if="activeAction.related_plans.length" class="orientation-tags"><span v-for="item in activeAction.related_plans" :key="item.id"><i class="bx bx-link-alt"></i>{{ item.name }}</span></div>
            </article>

            <article class="orientation-execution-card">
              <header><div><h3><i class="bx bx-run"></i> Actividades de la acción</h3><p>Cada actividad puede aportar al cumplimiento y avance automático.</p></div><button v-if="capabilities.can_manage_execution" type="button" @click="openCreateActivity"><i class="bx bx-plus"></i> Agregar</button></header>
              <div class="activity-contribution-overview">
                <div><span>Avance obtenido</span><strong>{{ activeAction.activity_contribution?.earned || 0 }}%</strong></div>
                <div><span>Aporte distribuido</span><strong>{{ activeAction.activity_contribution?.allocated || 0 }}%</strong></div>
                <div><span>Disponible</span><strong>{{ activeAction.activity_contribution?.remaining ?? 100 }}%</strong></div>
                <div class="activity-contribution-overview__bar"><i :style="{ width: `${activeAction.progress}%` }"></i></div>
              </div>
              <div v-if="!activeAction.activities.length" class="detail-empty"><i class="bx bx-calendar-event"></i><span>No hay actividades registradas.</span></div>
              <button v-for="activity in activeAction.activities" :key="activity.id" type="button" class="activity-row" @click="openEditActivity(activity)">
                <span class="activity-row__date"><b>{{ new Date(activity.starts_at).getDate() }}</b><small>{{ new Intl.DateTimeFormat('es-CL', { month: 'short' }).format(new Date(activity.starts_at)) }}</small></span>
                <span class="activity-row__main"><strong>{{ activity.title }}</strong><small><i class="bx bx-time"></i> {{ formatDate(activity.starts_at, true) }}<template v-if="activity.location"> · {{ activity.location }}</template></small><em>Aporta {{ activity.contribution_percent }}% · Cumplimiento {{ activity.completion_percent }}% · Suma {{ activity.earned_progress }} puntos</em></span>
                <span class="status-pill" :class="`status-pill--${statusTone(activity.status)}`">{{ statusLabel('activities', activity.status) }}</span>
              </button>
            </article>

            <article class="orientation-evidence-card">
              <header><div><h3><i class="bx bx-folder-open"></i> Evidencias</h3><p>Archivos privados o enlaces verificables.</p></div><button v-if="capabilities.can_manage_evidence" type="button" @click="openCreateEvidence"><i class="bx bx-upload"></i> Agregar</button></header>
              <div v-if="!activeAction.evidences.length" class="detail-empty"><i class="bx bx-file-blank"></i><span>No hay evidencias cargadas.</span></div>
              <a v-for="evidence in activeAction.evidences" :key="evidence.id" class="evidence-row" :href="evidence.download_url || evidence.external_url" :target="evidence.external_url ? '_blank' : null" rel="noopener">
                <span class="evidence-row__icon"><i :class="evidence.has_file ? 'bx bx-file' : 'bx bx-link-external'"></i></span>
                <span><strong>{{ evidence.title }}</strong><small>{{ evidence.original_name || evidence.evidence_type }} · {{ fileSize(evidence.size_bytes) }}</small></span>
                <i class="bx bx-download"></i>
              </a>
            </article>
          </div>
        </section>
      </template>

      <div v-if="modal" class="orientation-modal-backdrop" role="presentation" @mousedown.self="closeModal">
        <section class="orientation-modal" role="dialog" aria-modal="true" :aria-labelledby="`orientation-${modal}-title`">
          <header>
            <div>
              <span class="section-eyebrow">Orientación {{ selectedYear }}</span>
              <h2 :id="`orientation-${modal}-title`">
                {{ modal === 'plan' ? (plan ? 'Editar plan anual' : 'Crear plan anual') : modal === 'action' ? (actionForm.id ? 'Editar acción' : 'Nueva acción') : modal === 'related-plan' ? (relatedPlanForm.id ? 'Editar plan relacionado' : 'Indexar plan relacionado') : modal === 'activity' ? (activityForm.id ? 'Actualizar actividad' : 'Registrar actividad') : 'Agregar evidencia' }}
              </h2>
            </div>
            <button type="button" aria-label="Cerrar" @click="closeModal"><i class="bx bx-x"></i></button>
          </header>
          <div v-if="error" class="orientation-modal-error" role="alert"><i class="bx bx-error-circle"></i><span>{{ error }}</span></div>

          <form v-if="modal === 'plan'" @submit.prevent="savePlan">
            <div class="form-grid">
              <label><span>Año</span><input v-model.number="planForm.year" class="form-control" type="number" min="2020" max="2100" required :readonly="Boolean(plan)" /><small>Solo puede existir un plan por año.</small></label>
              <label><span>Estado</span><select v-model="planForm.status" class="form-select" required><option v-for="option in statusOptions.plans" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
              <label class="form-grid__wide"><span>Título</span><input v-model="planForm.title" class="form-control" maxlength="191" required /></label>
              <label class="form-grid__wide"><span>Objetivo general</span><textarea v-model="planForm.general_objective" class="form-control" rows="3"></textarea></label>
              <label class="form-grid__wide"><span>Descripción y énfasis del año</span><textarea v-model="planForm.description" class="form-control" rows="3"></textarea></label>
              <label class="form-grid__wide"><span>Responsable principal</span><select v-model="planForm.owner_user_id" class="form-select"><option value="">Sin asignar</option><option v-for="user in responsibleUsers" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
            </div>
            <footer><button type="button" class="btn-orientation-ghost" @click="closeModal">Cancelar</button><button type="submit" class="btn-orientation-primary" :disabled="saving"><span v-if="saving" class="orientation-spinner orientation-spinner--button"></span>{{ saving ? 'Guardando…' : 'Guardar plan' }}</button></footer>
          </form>

          <form v-else-if="modal === 'action'" @submit.prevent="saveAction">
            <div class="form-grid">
              <label class="form-grid__wide"><span>Nombre de la acción</span><input v-model="actionForm.title" class="form-control" maxlength="191" required /></label>
              <label class="form-grid__wide"><span>Objetivo específico</span><textarea v-model="actionForm.objective" class="form-control" rows="2"></textarea></label>
              <label class="form-grid__wide"><span>Descripción</span><textarea v-model="actionForm.description" class="form-control" rows="2"></textarea></label>
              <label><span>Niveles o cursos</span><input v-model="actionForm.target_levels" class="form-control" placeholder="Ej. 1° básico a IV medio" /></label>
              <label><span>Responsable textual complementario</span><input v-model="actionForm.responsible_summary" class="form-control" placeholder="Ej. Equipo de Formación" /></label>
              <label><span>Fecha de inicio</span><input v-model="actionForm.start_date" class="form-control" type="date" :min="`${selectedYear}-01-01`" :max="`${selectedYear}-12-31`" /></label>
              <label><span>Fecha de término</span><input v-model="actionForm.end_date" class="form-control" type="date" :min="actionForm.start_date || `${selectedYear}-01-01`" :max="`${selectedYear}-12-31`" /></label>
              <label><span>Estado</span><select v-model="actionForm.status" class="form-select"><option v-for="option in statusOptions.actions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
              <label><span>Avance: {{ actionForm.progress }}%</span><input v-model.number="actionForm.progress" class="form-range" type="range" min="0" max="100" step="5" :disabled="actionForm.progress_automatic" /><small v-if="actionForm.progress_automatic" class="orientation-automatic-hint"><i class="bx bx-calculator"></i> Se calcula automáticamente desde las actividades.</small></label>
              <label class="form-grid__wide"><span>Responsables del sistema</span><select v-model="actionForm.responsible_user_ids" class="form-select orientation-multiselect" multiple><option v-for="user in responsibleUsers" :key="user.id" :value="user.id">{{ user.name }}</option></select><small>Usa Cmd/Ctrl para seleccionar más de una persona.</small></label>
              <label class="form-grid__wide"><span>Planes vinculados</span><select v-model="actionForm.related_plan_ids" class="form-select orientation-multiselect" multiple><option v-for="item in plan.related_plans" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
              <label class="form-grid__wide"><span>Medios de verificación planificados</span><textarea v-model="actionForm.planned_verification_means" class="form-control" rows="3" placeholder="Actas, asistencia, fotografías, informes…"></textarea></label>
              <label class="form-grid__wide"><span>Recursos materiales</span><textarea v-model="actionForm.material_resources" class="form-control" rows="2"></textarea></label>
            </div>
            <footer><button type="button" class="btn-orientation-ghost" @click="closeModal">Cancelar</button><button type="submit" class="btn-orientation-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar acción' }}</button></footer>
          </form>

          <form v-else-if="modal === 'related-plan'" @submit.prevent="saveRelatedPlan">
            <div class="form-grid">
              <label class="form-grid__wide"><span>Nombre del plan</span><input v-model="relatedPlanForm.name" class="form-control" placeholder="Ej. Plan de Afectividad, Sexualidad y Género" maxlength="191" required /></label>
              <label><span>Tipo</span><select v-model="relatedPlanForm.category" class="form-select"><option value="institutional">Institucional</option><option value="regulatory">Normativo</option><option value="external">Externo</option><option value="other">Otro</option></select></label>
              <label><span>Estado</span><select v-model="relatedPlanForm.status" class="form-select"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label>
              <label class="form-grid__wide"><span>Descripción</span><textarea v-model="relatedPlanForm.description" class="form-control" rows="3"></textarea></label>
              <label class="form-grid__wide"><span>Enlace de referencia (opcional)</span><input v-model="relatedPlanForm.reference_url" class="form-control" type="url" placeholder="https://…" /></label>
            </div>
            <footer><button type="button" class="btn-orientation-ghost" @click="closeModal">Cancelar</button><button type="submit" class="btn-orientation-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar referencia' }}</button></footer>
          </form>

          <form v-else-if="modal === 'activity'" @submit.prevent="saveActivity">
            <div class="form-grid">
              <label class="form-grid__wide"><span>Actividad realizada o programada</span><input v-model="activityForm.title" class="form-control" maxlength="191" required /></label>
              <label><span>Inicio</span><input v-model="activityForm.starts_at" class="form-control" type="datetime-local" required /></label>
              <label><span>Término</span><input v-model="activityForm.ends_at" class="form-control" type="datetime-local" /></label>
              <label><span>Estado</span><select v-model="activityForm.status" class="form-select" @change="syncActivityCompletion"><option v-for="option in statusOptions.activities" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
              <label><span>Lugar</span><input v-model="activityForm.location" class="form-control" /></label>
              <label><span>Aporte al avance de la acción</span><div class="orientation-percent-input"><input v-model.number="activityForm.contribution_percent" class="form-control" type="number" min="0" :max="activityContributionLimit" required /><b>%</b></div><small>Máximo disponible para esta actividad: {{ activityContributionLimit }}%.</small></label>
              <label><span>Cumplimiento de la actividad: {{ activityForm.completion_percent }}%</span><input v-model.number="activityForm.completion_percent" class="form-range" type="range" min="0" max="100" step="5" :disabled="activityForm.status !== 'in_progress'" /><small>Esta actividad suma {{ Math.round((activityForm.contribution_percent || 0) * (activityForm.completion_percent || 0) / 100) }} puntos al avance de la acción.</small></label>
              <div class="activity-progress-explainer form-grid__wide"><i class="bx bx-calculator"></i><div><strong>Cálculo automático</strong><span>Una actividad realizada cumple 100% de su aporte; una actividad en ejecución aporta proporcionalmente. Las programadas o canceladas aún no suman avance.</span></div></div>
              <label class="form-grid__wide"><span>Descripción</span><textarea v-model="activityForm.description" class="form-control" rows="2"></textarea></label>
              <label class="form-grid__wide"><span>Participantes o cursos</span><textarea v-model="activityForm.participants" class="form-control" rows="2"></textarea></label>
              <label><span>Número de asistentes</span><input v-model.number="activityForm.attendee_count" class="form-control" type="number" min="0" /></label>
              <label class="form-grid__wide"><span>Resultados, acuerdos o hitos logrados</span><textarea v-model="activityForm.results" class="form-control" rows="4"></textarea></label>
            </div>
            <footer><button type="button" class="btn-orientation-ghost" @click="closeModal">Cancelar</button><button type="submit" class="btn-orientation-primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar actividad' }}</button></footer>
          </form>

          <form v-else @submit.prevent="saveEvidence">
            <div class="form-grid">
              <label class="form-grid__wide"><span>Título de la evidencia</span><input v-model="evidenceForm.title" class="form-control" maxlength="191" required /></label>
              <label><span>Tipo</span><select v-model="evidenceForm.evidence_type" class="form-select"><option value="attendance">Lista de asistencia</option><option value="photograph">Fotografía</option><option value="minutes">Acta</option><option value="report">Informe</option><option value="authorization">Autorización</option><option value="survey">Encuesta</option><option value="planning">Planificación</option><option value="other">Otro</option></select></label>
              <label><span>Fecha</span><input v-model="evidenceForm.occurred_on" class="form-control" type="date" :min="`${selectedYear}-01-01`" :max="`${selectedYear}-12-31`" /></label>
              <label class="form-grid__wide"><span>Actividad asociada (opcional)</span><select v-model="evidenceForm.orientation_activity_id" class="form-select"><option value="">Evidencia general de la acción</option><option v-for="activity in activeAction.activities" :key="activity.id" :value="activity.id">{{ activity.title }} · {{ formatDate(activity.starts_at, true) }}</option></select></label>
              <label class="form-grid__wide"><span>Descripción</span><textarea v-model="evidenceForm.description" class="form-control" rows="2"></textarea></label>
              <label class="form-grid__wide orientation-file-field"><span>Archivo privado (máx. 30 MB)</span><input class="form-control" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.mp4,.mov" @change="onEvidenceFile" /><small>Se descarga únicamente mediante una sesión autorizada.</small></label>
              <div class="orientation-or"><span>o</span></div>
              <label class="form-grid__wide"><span>Enlace externo verificable</span><input v-model="evidenceForm.external_url" class="form-control" type="url" placeholder="https://…" /></label>
            </div>
            <footer><button type="button" class="btn-orientation-ghost" @click="closeModal">Cancelar</button><button type="submit" class="btn-orientation-primary" :disabled="saving || (!evidenceForm.file && !evidenceForm.external_url)">{{ saving ? 'Cargando…' : 'Guardar evidencia' }}</button></footer>
          </form>
        </section>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.orientation-page { --ink: #15223a; --muted: #65718a; --violet: #6651c7; --violet-dark: #49379e; --blue: #3265d4; --green: #16866f; --amber: #c47716; --line: #e7e9f2; --surface: #fff; color: var(--ink); padding: 0 0 3rem; }
.orientation-hero { position: relative; overflow: hidden; min-height: 238px; padding: 2.1rem 2.3rem; border-radius: 26px; background: linear-gradient(125deg, #241b54 0%, #403282 46%, #6b51c5 100%); color: #fff; display: flex; align-items: center; justify-content: space-between; gap: 2rem; box-shadow: 0 24px 55px rgba(54, 41, 112, .24); }
.orientation-hero__glow { position: absolute; border-radius: 999px; filter: blur(2px); pointer-events: none; }
.orientation-hero__glow--one { width: 330px; height: 330px; right: 8%; top: -210px; background: rgba(164, 145, 255, .3); }
.orientation-hero__glow--two { width: 240px; height: 240px; left: 35%; bottom: -190px; background: rgba(63, 186, 201, .2); }
.orientation-hero__content, .orientation-year-card { position: relative; z-index: 1; }
.orientation-kicker { display: inline-flex; align-items: center; gap: .45rem; padding: .38rem .72rem; border-radius: 999px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); text-transform: uppercase; letter-spacing: .12em; font-size: .68rem; font-weight: 800; }
.orientation-kicker i { font-size: 1rem; }
.orientation-hero h1 { margin: .8rem 0 .4rem; font-size: clamp(2rem, 3vw, 3.05rem); letter-spacing: -.045em; color: #fff; }
.orientation-hero p { max-width: 720px; margin: 0; color: rgba(255,255,255,.76); font-size: 1rem; line-height: 1.65; }
.orientation-hero__nav { display: flex; gap: .5rem; margin-top: 1.3rem; }
.orientation-hero__nav a { color: rgba(255,255,255,.72); padding: .62rem .92rem; border-radius: 10px; font-weight: 700; transition: .2s ease; }
.orientation-hero__nav a:hover, .orientation-hero__nav a.router-link-active { color: #fff; background: rgba(255,255,255,.15); }
.orientation-year-card { min-width: 230px; padding: 1.2rem; border-radius: 18px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); backdrop-filter: blur(12px); }
.orientation-year-card label { display: block; margin-bottom: .5rem; font-size: .72rem; text-transform: uppercase; letter-spacing: .1em; font-weight: 800; color: rgba(255,255,255,.72); }
.orientation-year-card .form-select { min-height: 50px; font-size: 1.15rem; font-weight: 800; color: #302566; border: 0; }
.orientation-year-card span { display: flex; align-items: center; gap: .4rem; margin-top: .65rem; font-size: .72rem; color: rgba(255,255,255,.72); }
.orientation-alert { margin-top: 1rem; padding: .85rem 1rem; display: flex; align-items: center; gap: .65rem; border-radius: 12px; font-weight: 650; }
.orientation-alert span { flex: 1; }.orientation-alert button { border: 0; background: transparent; font-size: 1.3rem; color: inherit; }
.orientation-alert--danger { background: #fff0f1; color: #a63643; border: 1px solid #f5cbd0; }.orientation-alert--success { background: #ebfaf5; color: #116e5b; border: 1px solid #bfe7da; }
.orientation-loading, .orientation-empty { margin-top: 1.25rem; min-height: 260px; border: 1px solid var(--line); border-radius: 20px; background: #fff; display: flex; align-items: center; justify-content: center; gap: .8rem; }
.orientation-loading--compact { min-height: 70px; margin: 0; border: 0; }.orientation-spinner { width: 24px; height: 24px; border: 3px solid #ded9f5; border-top-color: var(--violet); border-radius: 50%; animation: spin .8s linear infinite; }.orientation-spinner--button { width: 16px; height: 16px; border-width: 2px; }
@keyframes spin { to { transform: rotate(360deg); } }
.orientation-empty { flex-direction: column; text-align: center; padding: 2.5rem; }.orientation-empty__icon { width: 76px; height: 76px; display: grid; place-items: center; border-radius: 23px; color: var(--violet); background: #f0edff; font-size: 2.2rem; transform: rotate(-3deg); }.orientation-empty__eyebrow, .section-eyebrow { color: var(--violet); text-transform: uppercase; font-weight: 850; letter-spacing: .11em; font-size: .66rem; }.orientation-empty h2 { margin: 0; font-size: 1.55rem; }.orientation-empty p { max-width: 540px; color: var(--muted); }
.btn-orientation-primary, .btn-orientation-ghost { min-height: 42px; border-radius: 11px; padding: .68rem 1rem; border: 0; display: inline-flex; align-items: center; justify-content: center; gap: .45rem; font-weight: 800; transition: .2s ease; }.btn-orientation-primary { color: #fff; background: linear-gradient(135deg, var(--violet), var(--violet-dark)); box-shadow: 0 9px 20px rgba(102,81,199,.22); }.btn-orientation-primary:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(102,81,199,.28); }.btn-orientation-primary:disabled { opacity: .6; transform: none; }.btn-orientation-ghost { color: #4f5c73; background: #f5f6fa; border: 1px solid #e4e7ef; }
.orientation-plan-strip { margin-top: 1.25rem; padding: 1.25rem 1.35rem; border-radius: 18px; border: 1px solid var(--line); background: linear-gradient(120deg,#fff 30%,#faf9ff); display: flex; align-items: center; justify-content: space-between; gap: 1rem; }.orientation-plan-strip h2 { margin: .5rem 0 .2rem; font-size: 1.42rem; letter-spacing: -.02em; }.orientation-plan-strip p { margin: 0; color: var(--muted); max-width: 850px; }
.orientation-plan-strip__actions, .orientation-detail__actions { flex-shrink: 0; display: flex; align-items: center; gap: .55rem; }
.status-pill { display: inline-flex; align-items: center; width: max-content; padding: .28rem .57rem; border-radius: 999px; font-size: .68rem; line-height: 1; font-weight: 850; white-space: nowrap; }.status-pill--success { color: #0d755f; background: #dff6ef; }.status-pill--primary { color: #2454b6; background: #e4ecff; }.status-pill--violet { color: #5b45b9; background: #eeeafe; }.status-pill--warning { color: #a35b09; background: #fff1da; }.status-pill--muted { color: #687386; background: #eef0f4; }
.orientation-stats { margin-top: 1rem; display: grid; grid-template-columns: repeat(3, minmax(150px, 1fr)) minmax(260px, 1.6fr); gap: .85rem; }.orientation-stats article { min-height: 100px; padding: 1rem; border: 1px solid var(--line); border-radius: 16px; background: #fff; display: flex; align-items: center; gap: .8rem; }.orientation-stats article > div { display: flex; flex-direction: column; }.orientation-stats strong { font-size: 1.5rem; line-height: 1.1; }.orientation-stats article span { color: var(--muted); font-size: .78rem; }.stat-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 13px; font-size: 1.25rem!important; }.stat-icon--violet { color: var(--violet)!important; background: #efebff; }.stat-icon--blue { color: var(--blue)!important; background: #e8efff; }.stat-icon--green { color: var(--green)!important; background: #e2f7f1; }
.orientation-progress-stat { display: block!important; }.orientation-progress-stat__top { flex-direction: row!important; justify-content: space-between; }.orientation-progress-stat__top strong { font-size: 1rem; }.orientation-progress { height: 8px; margin: .65rem 0 .45rem; overflow: hidden; border-radius: 999px; background: #eceef5; }.orientation-progress > span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg,var(--violet),#8f78e5); }.orientation-progress-stat small { color: var(--muted); }
.orientation-workspace { margin-top: 1rem; display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }.orientation-actions-panel, .orientation-related-panel, .orientation-detail, .orientation-calendar-card { background: #fff; border: 1px solid var(--line); border-radius: 20px; }.orientation-actions-panel { min-width: 0; padding: 1.25rem; }.orientation-section-header, .orientation-calendar-card > header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }.orientation-section-header h2, .orientation-related-panel h2, .orientation-calendar-card h2, .orientation-detail h2 { margin: .25rem 0 .22rem; font-size: 1.28rem; }.orientation-section-header p, .orientation-calendar-card header p { margin: 0; color: var(--muted); }
.orientation-search { margin: 1rem 0; height: 44px; display: flex; align-items: center; gap: .55rem; padding: 0 .8rem; border: 1px solid #e2e5ee; border-radius: 12px; background: #f9fafc; }.orientation-search i { color: #8892a5; font-size: 1.15rem; }.orientation-search input { flex: 1; border: 0; outline: 0; background: transparent; color: var(--ink); }.orientation-list-empty, .related-empty, .detail-empty { padding: 1.2rem; border: 1px dashed #d9ddea; border-radius: 12px; color: var(--muted); text-align: center; }
.orientation-action-table-wrap { margin-top: .2rem; overflow-x: auto; border: 1px solid #e2e5ee; border-radius: 16px; background: #fff; box-shadow: 0 10px 26px rgba(35, 31, 68, .055); scrollbar-color: #c9c2e9 #f3f2f8; }.orientation-action-table { width: 100%; min-width: 1180px; border-collapse: separate; border-spacing: 0; table-layout: fixed; }.orientation-action-table col.action-col-name { width: 24%; }.orientation-action-table col.action-col-level { width: 11%; }.orientation-action-table col.action-col-period { width: 10%; }.orientation-action-table col.action-col-status { width: 8%; }.orientation-action-table col.action-col-progress { width: 8%; }.orientation-action-table col.action-col-activities { width: 13%; }.orientation-action-table col.action-col-options { width: 26%; }.orientation-action-table th { padding: .78rem .85rem; border-bottom: 1px solid #dfe3ed; color: #68748a; background: linear-gradient(180deg,#fafafe,#f5f5fa); font-size: .65rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; white-space: nowrap; }.orientation-action-table th:first-child { padding-left: 1.1rem; }.orientation-action-table td { padding: .82rem .85rem; border-bottom: 1px solid #eceef4; color: #4f5b70; font-size: .73rem; vertical-align: middle; transition: background-color .18s ease; }.orientation-action-table th:last-child, .orientation-action-table td:last-child { position: sticky; right: 0; z-index: 1; background: #fff; box-shadow: -10px 0 18px -18px rgba(27,22,60,.75); }.orientation-action-table th:last-child { z-index: 2; background: linear-gradient(180deg,#fafafe,#f5f5fa); }.orientation-action-table tbody tr:last-child td { border-bottom: 0; }.orientation-action-table tbody tr:hover td { background: #fbfaff; }.orientation-action-table tbody tr:hover td:last-child { background: #fbfaff; }.orientation-action-table__row--active td, .orientation-action-table__row--active td:last-child { background: #f8f6ff; }.orientation-action-table__row--active:hover td, .orientation-action-table__row--active:hover td:last-child { background: #f5f2ff; }.action-table__name { position: relative; padding-left: 1.1rem !important; }.action-table__name strong, .action-table__name small { display: block; }.action-table__name strong { color: var(--ink); font-size: .82rem; line-height: 1.35; }.action-table__name small { margin-top: .23rem; overflow: hidden; color: var(--muted); font-size: .65rem; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }.action-table__rail { position: absolute; inset: .58rem auto .58rem 0; width: 4px; border-radius: 0 999px 999px 0; }.action-table__rail--violet { background: var(--violet); }.action-table__rail--primary { background: var(--blue); }.action-table__rail--success { background: var(--green); }.action-table__rail--warning { background: var(--amber); }.action-table__rail--muted { background: #a8b0bf; }.action-table__secondary, .action-table__period { display: flex; align-items: flex-start; gap: .38rem; line-height: 1.35; }.action-table__secondary i, .action-table__period i { margin-top: .04rem; color: #7968cd; font-size: .9rem; }.action-table__period small { display: block; margin-top: .1rem; color: #7a8497; }.action-table__progress { display: flex; align-items: center; gap: .45rem; }.action-table__progress > span { width: 54px; height: 6px; overflow: hidden; border-radius: 999px; background: #eceef4; }.action-table__progress i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg,var(--violet),#9c86ed); }.action-table__progress b { color: var(--violet); font-size: .69rem; }.action-table__activity-count strong, .action-table__activity-count small { display: block; }.action-table__activity-count strong { color: var(--ink); font-size: .78rem; }.action-table__activity-count small { margin-top: .15rem; color: var(--muted); font-size: .6rem; line-height: 1.3; }.action-options-heading { text-align: right; }.action-table__buttons { display: flex; align-items: center; justify-content: flex-end; gap: .3rem; white-space: nowrap; }.action-table-btn { min-height: 34px; padding: .42rem .52rem; border: 1px solid #dfe2eb; border-radius: 9px; color: #4e596d; background: #fff; display: inline-flex; align-items: center; justify-content: center; gap: .24rem; font-size: .63rem; font-weight: 800; transition: .18s ease; }.action-table-btn i { font-size: .92rem; }.action-table-btn:hover:not(:disabled) { border-color: #b8ace9; color: var(--violet-dark); background: #f7f5ff; transform: translateY(-1px); }.action-table-btn:focus-visible { outline: 3px solid rgba(102,81,199,.2); outline-offset: 2px; }.action-table-btn:disabled { cursor: wait; opacity: .58; }.action-table-btn--view { color: #315cb2; border-color: #ccd9f1; background: #f4f7fd; }.action-table-btn--danger { color: #ad3449; border-color: #f0d2d8; background: #fff8f9; }.action-table-btn--danger:hover:not(:disabled) { color: #902438; border-color: #e6aebb; background: #fff0f3; }.action-table-btn--activity { color: #fff; border-color: var(--violet); background: linear-gradient(135deg,var(--violet),#8068d6); box-shadow: 0 5px 12px rgba(102,81,199,.2); }.action-table-btn--activity:hover:not(:disabled) { color: #fff; border-color: var(--violet-dark); background: linear-gradient(135deg,var(--violet-dark),#6a53c3); }
.orientation-related-panel { padding: 1.15rem; }.orientation-related-panel > header { display: flex; align-items: center; justify-content: space-between; }.orientation-related-panel > header button, .orientation-execution-card header button, .orientation-evidence-card header button { border: 0; border-radius: 9px; background: #eeeafe; color: var(--violet); min-height: 34px; padding: .45rem .65rem; font-weight: 800; }.orientation-related-panel > p { max-width: 760px; color: var(--muted); font-size: .78rem; line-height: 1.55; }.related-empty, .detail-empty { display: flex; flex-direction: column; align-items: center; gap: .35rem; font-size: .75rem; }.related-empty i, .detail-empty i { font-size: 1.45rem; color: #9b8fe0; }.related-plan-card { width: min(380px,100%); margin: .55rem .5rem 0 0; padding: .75rem; border: 1px solid #e7e9f1; border-radius: 12px; background: #fff; color: inherit; display: inline-grid; grid-template-columns: 38px 1fr auto; align-items: center; gap: .65rem; text-align: left; vertical-align: top; }.related-plan-card:hover { border-color: #c6bcef; background: #fcfbff; }.related-plan-card__icon { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 10px; background: #efeafe; color: var(--violet); font-size: 1.1rem; }.related-plan-card span:nth-child(2) { display: flex; flex-direction: column; min-width: 0; }.related-plan-card strong { font-size: .78rem; }.related-plan-card small { color: var(--muted); font-size: .67rem; }
.orientation-detail { margin-top: 1rem; padding: 1.25rem; scroll-margin-top: 85px; }.orientation-detail__header { display: flex; justify-content: space-between; gap: 1rem; }.orientation-detail__header p { margin: 0; color: var(--muted); }.orientation-detail__grid { margin-top: 1rem; display: grid; grid-template-columns: 1fr 1.15fr 1fr; gap: .8rem; }.orientation-detail__grid > article { border: 1px solid #e7e9f2; border-radius: 15px; padding: 1rem; }.orientation-detail__grid h3 { margin: 0; font-size: .92rem; display: flex; align-items: center; gap: .4rem; }.orientation-detail__grid h3 i { color: var(--violet); font-size: 1.1rem; }.orientation-definition-card dl { margin: .8rem 0; }.orientation-definition-card dl div { padding: .55rem 0; border-bottom: 1px solid #eff0f5; }.orientation-definition-card dt { color: var(--muted); font-size: .65rem; text-transform: uppercase; letter-spacing: .07em; }.orientation-definition-card dd { margin: .22rem 0 0; font-size: .78rem; white-space: pre-line; }.orientation-tags { display: flex; flex-wrap: wrap; gap: .35rem; }.orientation-tags span { padding: .32rem .48rem; border-radius: 999px; background: #eeeafe; color: #5945b3; font-size: .66rem; font-weight: 750; }.orientation-execution-card header, .orientation-evidence-card header { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }.orientation-execution-card header p, .orientation-evidence-card header p { margin: .15rem 0 .65rem; color: var(--muted); font-size: .7rem; }.activity-row { width: 100%; margin-top: .45rem; padding: .55rem 0; border: 0; border-top: 1px solid #eef0f5; background: transparent; color: inherit; display: grid; grid-template-columns: 38px 1fr auto; gap: .55rem; align-items: center; text-align: left; }.activity-row__date { width: 38px; min-height: 42px; border-radius: 9px; background: #f0edff; color: var(--violet); display: flex; flex-direction: column; align-items: center; justify-content: center; }.activity-row__date b { line-height: 1; }.activity-row__date small { text-transform: uppercase; font-size: .56rem; }.activity-row__main { display: flex; min-width: 0; flex-direction: column; }.activity-row__main strong { font-size: .75rem; }.activity-row__main small { color: var(--muted); font-size: .63rem; }.evidence-row { margin-top: .45rem; padding: .6rem 0; border-top: 1px solid #eef0f5; display: grid; grid-template-columns: 36px 1fr auto; gap: .55rem; align-items: center; color: inherit; }.evidence-row:hover { color: var(--violet); }.evidence-row__icon { width: 36px; height: 36px; display: grid; place-items: center; border-radius: 9px; background: #e8f5f1; color: var(--green); }.evidence-row span:nth-child(2) { min-width: 0; display: flex; flex-direction: column; }.evidence-row strong { font-size: .75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.evidence-row small { color: var(--muted); font-size: .62rem; }
.activity-contribution-overview { margin: .25rem 0 .75rem; padding: .7rem; border-radius: 12px; background: linear-gradient(135deg,#f6f3ff,#f8fafc); display: grid; grid-template-columns: repeat(3,1fr); gap: .55rem; }.activity-contribution-overview > div:not(.activity-contribution-overview__bar) { display: flex; flex-direction: column; }.activity-contribution-overview span { color: var(--muted); font-size: .58rem; text-transform: uppercase; font-weight: 800; letter-spacing: .04em; }.activity-contribution-overview strong { margin-top: .15rem; font-size: .92rem; }.activity-contribution-overview__bar { grid-column: 1/-1; height: 6px; overflow: hidden; border-radius: 99px; background: #e4e3ee; }.activity-contribution-overview__bar i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg,var(--violet),#8f78e5); }.activity-row__main em { margin-top: .2rem; color: var(--violet); font-size: .6rem; font-weight: 750; font-style: normal; }
.orientation-percent-input { position: relative; }.orientation-percent-input input { padding-right: 2.2rem; }.orientation-percent-input b { position: absolute; top: 50%; right: .85rem; transform: translateY(-50%); color: var(--violet); }.activity-progress-explainer { padding: .8rem; border: 1px solid #dcd5f5; border-radius: 12px; background: #f6f3ff; display: flex; align-items: flex-start; gap: .65rem; }.activity-progress-explainer > i { color: var(--violet); font-size: 1.25rem; }.activity-progress-explainer strong, .activity-progress-explainer span { display: block; }.activity-progress-explainer strong { color: #443876; font-size: .78rem; }.activity-progress-explainer span { margin-top: .15rem; color: #6b6480; font-size: .68rem; line-height: 1.45; }.orientation-automatic-hint { color: var(--violet)!important; font-weight: 750; }
.orientation-statistics-view { margin-top: 1rem; padding: 1.3rem; border: 1px solid var(--line); border-radius: 22px; background: linear-gradient(180deg,#fff 0,#fbfbfe 100%); box-shadow: 0 18px 45px rgba(38,31,76,.05); }
.orientation-statistics-view__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }.orientation-statistics-view__header h2 { margin: .3rem 0 .25rem; font-size: 1.45rem; }.orientation-statistics-view__header p { margin: 0; color: var(--muted); }
.orientation-executive-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .8rem; }.executive-card { position: relative; overflow: hidden; min-height: 145px; padding: 1rem; border: 1px solid var(--line); border-radius: 17px; background: #fff; display: flex; align-items: flex-start; gap: .8rem; box-shadow: 0 10px 25px rgba(38,31,76,.045); }.executive-card::after { content: ""; position: absolute; width: 95px; height: 95px; right: -40px; bottom: -45px; border-radius: 50%; background: currentColor; opacity: .055; }.executive-card > span { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; border-radius: 13px; font-size: 1.35rem; background: currentColor; }.executive-card > span i { color: #fff; }.executive-card div { min-width: 0; }.executive-card small { color: var(--muted); font-size: .66rem; text-transform: uppercase; font-weight: 850; letter-spacing: .07em; }.executive-card strong { display: block; margin: .2rem 0; color: var(--ink); font-size: 1.65rem; }.executive-card p { margin: 0; color: var(--muted); font-size: .7rem; line-height: 1.45; }.executive-card--violet { color: var(--violet); }.executive-card--blue { color: var(--blue); }.executive-card--green { color: var(--green); }.executive-card--amber { color: var(--amber); }
.orientation-insight-grid { margin-top: .8rem; display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }.orientation-chart-card, .orientation-monthly-card, .orientation-performance-card { padding: 1rem; border: 1px solid var(--line); border-radius: 17px; background: #fff; }.orientation-chart-card > header, .orientation-monthly-card > header, .orientation-performance-card > header { display: flex; align-items: flex-start; justify-content: space-between; gap: .7rem; }.orientation-chart-card h3, .orientation-monthly-card h3, .orientation-performance-card h3 { margin: .25rem 0 .2rem; font-size: 1rem; }.orientation-chart-card > header > strong { color: var(--violet); font-size: 1.5rem; }.orientation-monthly-card header p, .orientation-performance-card header p { margin: 0; color: var(--muted); font-size: .72rem; }
.orientation-status-bars, .orientation-traceability-list { margin-top: .85rem; display: grid; gap: .65rem; }.orientation-status-bars > div > span, .orientation-traceability-list > div > span { display: flex; justify-content: space-between; gap: .6rem; margin-bottom: .32rem; font-size: .72rem; }.orientation-status-bars small, .orientation-traceability-list small { color: var(--muted); }.orientation-status-bars > div > div, .orientation-traceability-list > div > div { height: 8px; overflow: hidden; border-radius: 999px; background: #edeef4; }.orientation-status-bars i, .orientation-traceability-list i { display: block; height: 100%; border-radius: inherit; background: var(--violet); }.bar-tone--primary { background: var(--blue)!important; }.bar-tone--success { background: var(--green)!important; }.bar-tone--warning { background: var(--amber)!important; }.bar-tone--muted { background: #a8b0bf!important; }.orientation-traceability-list > div { display: grid; grid-template-columns: 1fr 42px; column-gap: .7rem; }.orientation-traceability-list > div > span { grid-column: 1/-1; }.orientation-traceability-list em { color: var(--violet); font-size: .7rem; font-weight: 850; font-style: normal; text-align: right; }
.orientation-monthly-card { margin-top: .8rem; }.monthly-legend { display: flex; gap: .75rem; color: var(--muted); font-size: .68rem; }.monthly-legend span { display: flex; align-items: center; gap: .3rem; }.monthly-legend i { width: 8px; height: 8px; border-radius: 3px; background: var(--violet); }.monthly-legend span:last-child i { background: var(--blue); }.orientation-monthly-chart { height: 210px; margin-top: .9rem; padding: 8px 4px 0; display: grid; grid-template-columns: repeat(12,minmax(30px,1fr)); gap: .45rem; border-bottom: 1px solid #e0e3eb; background: repeating-linear-gradient(to bottom,transparent 0,transparent 49px,#f0f1f5 50px); }.monthly-column { min-width: 0; display: grid; grid-template-rows: 1fr 22px; }.monthly-bars { min-height: 0; display: flex; align-items: flex-end; justify-content: center; gap: 4px; }.monthly-bars > i { position: relative; width: min(16px,42%); min-height: 3px; border-radius: 5px 5px 1px 1px; background: linear-gradient(180deg,#8a72e5,var(--violet)); transition: height .25s ease; }.monthly-bars > i:nth-child(2) { background: linear-gradient(180deg,#6d94e5,var(--blue)); }.monthly-bars b { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); color: var(--muted); font-size: .58rem; font-style: normal; }.monthly-column > span { padding-top: .35rem; color: var(--muted); text-align: center; text-transform: uppercase; font-size: .58rem; font-weight: 800; }
.orientation-insight-grid--secondary { grid-template-columns: 1.25fr .75fr; }.evidence-type-list, .activity-status-list { margin-top: .8rem; display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .5rem; }.evidence-type-list span, .activity-status-list span { min-width: 0; padding: .65rem; border-radius: 11px; background: #f7f7fb; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .45rem; }.evidence-type-list i { color: var(--violet); }.evidence-type-list b, .activity-status-list b { overflow: hidden; color: #4e5a6e; font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }.evidence-type-list strong, .activity-status-list strong { font-size: .9rem; }.status-dot { width: 8px; height: 8px; border-radius: 50%; background: #a8b0bf; }.status-dot--violet { background: var(--violet); }.status-dot--primary { background: var(--blue); }.status-dot--success { background: var(--green); }
.orientation-performance-card { margin-top: .8rem; }.orientation-performance-table-wrap { margin-top: .8rem; overflow-x: auto; }.orientation-performance-table { width: 100%; min-width: 920px; border-collapse: separate; border-spacing: 0; }.orientation-performance-table th { padding: .65rem .7rem; color: #737e91; background: #f5f6fa; border-bottom: 1px solid #e2e5ed; font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }.orientation-performance-table td { padding: .72rem; border-bottom: 1px solid #eceef3; color: #4e5a6e; font-size: .72rem; vertical-align: middle; }.orientation-performance-table td:first-child { min-width: 250px; }.orientation-performance-table td:first-child strong, .orientation-performance-table td:first-child small { display: block; }.orientation-performance-table td:first-child strong { color: var(--ink); font-size: .77rem; }.orientation-performance-table td:first-child small { margin-top: .15rem; color: var(--muted); }.orientation-performance-table tbody tr:hover { background: #fbfaff; }.orientation-performance-table td:last-child button { width: 32px; height: 32px; border: 0; border-radius: 9px; color: var(--violet); background: #eeeafe; font-size: 1.15rem; }.performance-progress { display: flex; align-items: center; gap: .35rem; }.performance-progress > span { width: 55px; height: 5px; overflow: hidden; border-radius: 999px; background: #eceef3; }.performance-progress i { display: block; height: 100%; background: linear-gradient(90deg,var(--violet),#9b84ee); }.performance-progress b { color: var(--violet); font-size: .67rem; }
.performance-contribution { display: block; margin-top: .14rem; color: var(--violet); font-size: .58rem; white-space: nowrap; }
.orientation-calendar-card { margin-top: 1rem; padding: 1.25rem; }.orientation-calendar-legend { display: flex; gap: 1rem; flex-wrap: wrap; margin: 1rem 0; color: var(--muted); font-size: .72rem; }.orientation-calendar-legend span { display: flex; align-items: center; gap: .35rem; }.legend-dot { width: 9px; height: 9px; border-radius: 50%; }.legend-dot--planned { background: var(--violet); }.legend-dot--progress { background: var(--blue); }.legend-dot--done { background: var(--green); }.legend-dot--postponed { background: var(--amber); }
.orientation-modal-backdrop { position: fixed; inset: 0; z-index: 1080; padding: 4vh 1rem; overflow-y: auto; background: rgba(16,20,34,.62); backdrop-filter: blur(4px); display: flex; align-items: flex-start; justify-content: center; }.orientation-modal { width: min(820px, 100%); overflow: hidden; border-radius: 20px; background: #fff; box-shadow: 0 30px 90px rgba(0,0,0,.3); }.orientation-modal > header { padding: 1.2rem 1.35rem; display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 1px solid var(--line); background: linear-gradient(120deg,#fff,#f7f5ff); }.orientation-modal > header h2 { margin: .3rem 0 0; font-size: 1.35rem; }.orientation-modal > header button { border: 0; background: #eef0f5; color: #5b667b; width: 36px; height: 36px; border-radius: 10px; font-size: 1.4rem; }.orientation-modal form { padding: 1.25rem 1.35rem; }.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }.form-grid label { min-width: 0; }.form-grid label > span { display: block; margin-bottom: .38rem; color: #404d63; font-size: .75rem; font-weight: 800; }.form-grid label small { display: block; margin-top: .28rem; color: var(--muted); font-size: .65rem; }.form-grid__wide { grid-column: 1 / -1; }.form-control, .form-select { border-color: #dfe2eb; border-radius: 10px; }.form-control:focus, .form-select:focus { border-color: #9a87e6; box-shadow: 0 0 0 .2rem rgba(102,81,199,.12); }.orientation-multiselect { min-height: 100px; }.orientation-or { grid-column: 1/-1; height: 1px; background: #e6e8ef; position: relative; }.orientation-or span { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%); padding: 0 .5rem; background: #fff; color: var(--muted); font-size: .7rem; }.orientation-modal footer { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: .6rem; }
.orientation-modal-error { margin: .9rem 1.35rem 0; padding: .7rem .8rem; border-radius: 10px; color: #a63643; background: #fff0f1; border: 1px solid #f5cbd0; display: flex; align-items: flex-start; gap: .5rem; font-size: .76rem; font-weight: 700; }
:deep(.fc) { --fc-border-color: #e7e9f1; --fc-button-bg-color: #6651c7; --fc-button-border-color: #6651c7; --fc-button-hover-bg-color: #4f3aa8; --fc-button-hover-border-color: #4f3aa8; --fc-button-active-bg-color: #49379e; font-size: .82rem; }:deep(.fc .fc-toolbar-title) { color: var(--ink); font-size: 1.15rem; text-transform: capitalize; }:deep(.fc .fc-button) { border-radius: 8px; font-weight: 700; text-transform: capitalize; }:deep(.fc .fc-daygrid-day-number) { color: #536076; }:deep(.fc .fc-col-header-cell-cushion) { color: #6d7689; padding: .7rem .2rem; text-transform: uppercase; font-size: .66rem; letter-spacing: .06em; }:deep(.fc-event) { border-radius: 6px; padding: 1px 3px; cursor: pointer; }
@media (max-width: 1199px) { .orientation-stats { grid-template-columns: repeat(2,1fr); }.orientation-detail__grid { grid-template-columns: 1fr 1fr; }.orientation-evidence-card { grid-column: 1/-1; }.orientation-executive-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media (max-width: 767px) { .orientation-hero { min-height: auto; padding: 1.35rem; border-radius: 20px; flex-direction: column; align-items: stretch; }.orientation-year-card { min-width: 0; }.orientation-hero__nav { overflow-x: auto; }.orientation-plan-strip, .orientation-section-header, .orientation-calendar-card > header, .orientation-detail__header, .orientation-statistics-view__header { align-items: stretch; flex-direction: column; }.orientation-plan-strip__actions, .orientation-detail__actions { align-items: stretch; flex-direction: column; }.orientation-stats { grid-template-columns: 1fr 1fr; }.orientation-workspace { gap: .75rem; }.orientation-actions-panel, .orientation-related-panel, .orientation-detail, .orientation-calendar-card, .orientation-statistics-view { border-radius: 16px; padding: 1rem; }.orientation-action-table-wrap { margin-right: -1rem; border-radius: 14px 0 0 14px; }.orientation-detail__grid, .orientation-insight-grid, .orientation-insight-grid--secondary { grid-template-columns: 1fr; }.orientation-evidence-card { grid-column: auto; }.form-grid { grid-template-columns: 1fr; }.form-grid__wide { grid-column: auto; }.orientation-modal-backdrop { padding: 0; align-items: flex-end; }.orientation-modal { max-height: 94vh; overflow-y: auto; border-radius: 20px 20px 0 0; }.orientation-modal form { padding: 1rem; }.orientation-calendar-card { overflow-x: auto; }.orientation-calendar-card :deep(.fc) { min-width: 660px; }.orientation-monthly-chart { gap: .18rem; overflow-x: auto; }.monthly-legend { align-self: flex-start; } }
@media (max-width: 430px) { .orientation-stats { grid-template-columns: 1fr; }.orientation-hero h1 { font-size: 1.85rem; }.activity-row { grid-template-columns: 38px 1fr; }.activity-row .status-pill { grid-column: 2; }.orientation-modal footer { position: sticky; bottom: -1rem; background: #fff; padding-bottom: .2rem; } }
.orientation-actions-panel { container-type: inline-size; }
.orientation-action-table-wrap { overflow-x: hidden; }
.orientation-action-table { min-width: 0; }
.orientation-action-table col.action-col-name { width: 35%; }
.orientation-action-table col.action-col-planning { width: 26%; }
.orientation-action-table col.action-col-tracking { width: 20%; }
.orientation-action-table col.action-col-options { width: 19%; }
.orientation-action-table th:last-child,
.orientation-action-table td:last-child { position: static; box-shadow: none; }
.action-options-heading { text-align: center; }
.action-table__cell-label { display: none; }
.action-table__planning { display: grid; gap: .58rem; }
.action-table__tracking { display: grid; gap: .42rem; }
.action-table__tracking-top { display: flex; align-items: center; justify-content: space-between; gap: .45rem; }
.action-table__activity-summary { display: inline-flex; align-items: center; gap: .24rem; color: var(--violet); font-size: .67rem; white-space: nowrap; }
.action-table__activity-summary i { font-size: .92rem; }
.action-table__tracking .action-table__progress > span { width: auto; min-width: 62px; max-width: 118px; flex: 1; }
.action-table__tracking > small { color: var(--muted); font-size: .58rem; line-height: 1.35; }
.action-table__buttons { display: grid; grid-template-columns: repeat(4,42px); justify-content: center; gap: .38rem; }
.action-table-btn { flex: 0 0 auto; }
.action-table__buttons .cnsc-action-btn + .cnsc-action-btn { margin-left: 0 !important; }
@container (max-width: 1100px) {
  .orientation-action-table col.action-col-name { width: 32%; }
  .orientation-action-table col.action-col-planning { width: 27%; }
  .orientation-action-table col.action-col-tracking { width: 23%; }
  .orientation-action-table col.action-col-options { width: 18%; }
  .action-table__buttons { grid-template-columns: repeat(2,42px); }
  .action-table__tracking-top { align-items: flex-start; flex-direction: column; }
}
@container (max-width: 780px) {
  .orientation-action-table-wrap { margin-right: 0; overflow: visible; border: 0; border-radius: 0; background: transparent; box-shadow: none; }
  .orientation-action-table,
  .orientation-action-table tbody { display: block; width: 100%; }
  .orientation-action-table colgroup,
  .orientation-action-table thead { display: none; }
  .orientation-action-table tbody { display: grid; gap: .72rem; }
  .orientation-action-table tbody tr { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); overflow: hidden; border: 1px solid #e2e5ee; border-radius: 14px; background: #fff; box-shadow: 0 7px 18px rgba(35,31,68,.045); }
  .orientation-action-table td { display: block; padding: .78rem .85rem; border: 0; background: #fff; }
  .orientation-action-table td:first-child { grid-column: 1/-1; border-bottom: 1px solid #eceef4; }
  .orientation-action-table td:nth-child(2) { border-right: 1px solid #eceef4; }
  .orientation-action-table td:last-child { grid-column: 1/-1; border-top: 1px solid #eceef4; background: #faf9fe; }
  .orientation-action-table tbody tr:hover td { background: #fff; }
  .orientation-action-table tbody tr:hover td:last-child { background: #f8f6ff; }
  .action-table__cell-label { display: block; margin-bottom: .48rem; color: #7a8497; font-size: .56rem; font-weight: 850; letter-spacing: .07em; text-transform: uppercase; }
  .action-table__tracking-top { align-items: center; flex-direction: row; }
  .action-table__buttons { grid-template-columns: repeat(4,42px); justify-content: flex-end; }
}
@container (max-width: 500px) {
  .orientation-action-table tbody tr { grid-template-columns: 1fr; }
  .orientation-action-table td:first-child,
  .orientation-action-table td:last-child { grid-column: auto; }
  .orientation-action-table td:nth-child(2) { border-right: 0; border-bottom: 1px solid #eceef4; }
  .action-table__buttons { justify-content: center; }
}
</style>
