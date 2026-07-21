<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmePagination from "../pagination.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  confirmPmeAction,
  formatPmeDate,
  formatPmeError,
  normalizeOptions,
  normalizePagination,
  showPmeError,
  showPmeSuccess,
} from "../module-utils";

const defaultForm = () => ({
  id: null,
  pme_plan_id: null,
  pme_dimension_id: null,
  pme_objective_id: null,
  pme_strategy_id: null,
  pme_action_id: null,
  monitored_at: new Date().toISOString().slice(0, 10),
  responsible_user_id: null,
  guiding_questions: [],
  observed_progress: "",
  difficulties: "",
  reviewed_evidences: "",
  decisions_taken: "",
  required_adjustments: "",
  next_steps: "",
  state: "registrado",
  observations: "",
});

export default {
  components: { PmeHelpButton, PmePagination, PmeStatusBadge },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      pagination: normalizePagination(),
      form: defaultForm(),
      guidingQuestionsText: "",
      filters: {
        pme_plan_id: null,
        state: null,
        responsible_user_id: null,
      },
      editing: false,
    };
  },
  computed: {
    planOptions() {
      return normalizeOptions(this.catalogs.plans, true);
    },
    dimensionOptions() {
      return normalizeOptions(this.catalogs.dimensions, true);
    },
    objectiveOptions() {
      return normalizeOptions(this.objectiveCatalog, true);
    },
    strategyOptions() {
      return normalizeOptions(this.strategyCatalog, true);
    },
    actionOptions() {
      return normalizeOptions(this.actionCatalog, true);
    },
    responsibleOptions() {
      return normalizeOptions(this.catalogs.responsibles, true);
    },
    stateOptions() {
      return normalizeOptions(this.catalogs.options?.monitoring_states, true);
    },
    objectiveCatalog() {
      return this.catalogs.objectives || [];
    },
    strategyCatalog() {
      return this.catalogs.strategies || [];
    },
    actionCatalog() {
      return this.catalogs.actions || [];
    },
    monitoringSummary() {
      return [
        { label: "Monitoreos", value: this.items.length, icon: "bx-search-alt" },
        { label: "Registrados", value: this.items.filter((item) => item.state === "registrado").length, icon: "bx-check-circle", tone: "success" },
        { label: "Con ajustes", value: this.items.filter((item) => String(item.required_adjustments || "").trim()).length, icon: "bx-slider-alt", tone: "warning" },
        { label: "Con próximos pasos", value: this.items.filter((item) => String(item.next_steps || "").trim()).length, icon: "bx-right-arrow-circle" },
      ];
    },
  },
  mounted() {
    this.filters.pme_plan_id = this.catalogs.active_plan_id || this.catalogs.plans?.[0]?.id || null;
    this.startCreate();
    this.loadItems();
  },
  methods: {
    formatPmeDate,
    defaultGuidingQuestions() {
      return [
        "¿La acción se está ejecutando según lo planificado?",
        "¿Qué evidencias respaldan el avance?",
        "¿Qué ajustes se requieren?",
      ];
    },
    syncGuidingQuestionsText(questions = []) {
      this.guidingQuestionsText = (questions || []).join("\n");
    },
    normalizedGuidingQuestions() {
      return this.guidingQuestionsText
        .split("\n")
        .map((question) => question.trim())
        .filter(Boolean);
    },
    async loadItems(page = 1) {
      const requestedPage = Number.isInteger(page) ? page : 1;
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/pme-sep/monitorings", {
          params: { ...this.filters, page: requestedPage },
        });
        this.items = response.data.data || [];
        this.pagination = normalizePagination(response.data);
      } catch (error) {
        this.error = formatPmeError(error, "No se pudo cargar el monitoreo reflexivo.");
      } finally {
        this.loading = false;
      }
    },
    startCreate() {
      this.editing = false;
      this.form = defaultForm();
      this.form.pme_plan_id = this.catalogs.active_plan_id || this.catalogs.plans?.[0]?.id || null;
      this.form.guiding_questions = this.defaultGuidingQuestions();
      this.syncGuidingQuestionsText(this.form.guiding_questions);
    },
    resetFilters() {
      this.filters = {
        pme_plan_id: this.catalogs.active_plan_id || this.catalogs.plans?.[0]?.id || null,
        state: null,
        responsible_user_id: null,
      };
      this.loadItems(1);
    },
    editItem(item) {
      this.editing = true;
      this.form = {
        id: item.id,
        pme_plan_id: item.pme_plan_id,
        pme_dimension_id: item.pme_dimension_id,
        pme_objective_id: item.pme_objective_id,
        pme_strategy_id: item.pme_strategy_id,
        pme_action_id: item.pme_action_id,
        monitored_at: item.monitored_at,
        responsible_user_id: item.responsible_user_id,
        guiding_questions: item.guiding_questions || [],
        observed_progress: item.observed_progress || "",
        difficulties: item.difficulties || "",
        reviewed_evidences: item.reviewed_evidences || "",
        decisions_taken: item.decisions_taken || "",
        required_adjustments: item.required_adjustments || "",
        next_steps: item.next_steps || "",
        state: item.state,
        observations: item.observations || "",
      };
      this.syncGuidingQuestionsText(this.form.guiding_questions);
    },
    async save() {
      this.saving = true;
      try {
        const confirmed = await confirmPmeAction({
          title: this.editing ? "Actualizar monitoreo" : "Registrar monitoreo",
          text: "Se guardará el monitoreo reflexivo del PME.",
          confirmButtonText: this.editing ? "Sí, actualizar" : "Sí, registrar",
        });
        if (!confirmed.isConfirmed) return;

        const payload = {
          ...this.form,
          guiding_questions: this.normalizedGuidingQuestions(),
        };
        let response;
        if (this.editing && this.form.id) {
          response = await axios.put(`/api/pme-sep/monitorings/${this.form.id}`, payload);
        } else {
          response = await axios.post("/api/pme-sep/monitorings", payload);
        }

        showPmeSuccess(response.data.message || "Monitoreo guardado correctamente.");
        this.startCreate();
        this.loadItems();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar el monitoreo reflexivo."));
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<template>
  <div class="row g-3">
    <div class="col-12">
      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div><div class="fw-semibold">{{ editing ? 'Editar monitoreo reflexivo' : 'Registrar monitoreo reflexivo' }}</div><div class="small text-muted">Análisis cualitativo, decisiones y próximos pasos</div></div>
            <PmeHelpButton
              title="Ayuda: monitoreo reflexivo"
              text="Aquí se registran análisis cualitativos del avance PME, decisiones tomadas, dificultades, evidencias revisadas y ajustes requeridos."
            />
          </div>
        </template>

        <div class="monitoring-filter pme-filter-bar row g-2">
          <div class="col-md-3">
            <label class="form-label">Plan</label>
            <BFormSelect v-model="filters.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="filters.state" :options="stateOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Responsable</label>
            <BFormSelect v-model="filters.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-3 d-flex align-items-end gap-2">
            <BButton variant="outline-secondary" @click="resetFilters"><i class="bx bx-reset"></i></BButton>
            <BButton variant="primary" class="flex-grow-1" :disabled="loading" @click="loadItems(1)"><i class="bx bx-filter-alt"></i>Aplicar filtros</BButton>
          </div>
        </div>

        <div class="form-section-heading"><span><i class="bx bx-link-alt"></i>Contexto del monitoreo</span><small>Vincula el análisis con el nivel más específico disponible.</small></div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Plan</label>
            <BFormSelect v-model="form.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Dimensión</label>
            <BFormSelect v-model="form.pme_dimension_id" :options="dimensionOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Responsable</label>
            <BFormSelect v-model="form.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Objetivo</label>
            <BFormSelect v-model="form.pme_objective_id" :options="objectiveOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Estrategia</label>
            <BFormSelect v-model="form.pme_strategy_id" :options="strategyOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Acción</label>
            <BFormSelect v-model="form.pme_action_id" :options="actionOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha monitoreo</label>
            <BFormInput v-model="form.monitored_at" type="date" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="form.state" :options="stateOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Preguntas orientadoras</label>
            <BFormTextarea v-model="guidingQuestionsText" rows="3" />
          </div>
          <div class="col-12"><div class="form-section-heading is-inner"><span><i class="bx bx-message-square-detail"></i>Análisis reflexivo</span><small>Registra hallazgos concretos y acuerdos accionables.</small></div></div>
          <div class="col-md-6">
            <label class="form-label">Avances observados</label>
            <BFormTextarea v-model="form.observed_progress" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Dificultades</label>
            <BFormTextarea v-model="form.difficulties" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Evidencias revisadas</label>
            <BFormTextarea v-model="form.reviewed_evidences" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Decisiones tomadas</label>
            <BFormTextarea v-model="form.decisions_taken" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Ajustes requeridos</label>
            <BFormTextarea v-model="form.required_adjustments" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Próximos pasos</label>
            <BFormTextarea v-model="form.next_steps" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Observaciones</label>
            <BFormTextarea v-model="form.observations" rows="3" />
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <BButton variant="primary" :disabled="saving" @click="save"><span v-if="saving" class="spinner-border spinner-border-sm"></span>{{ editing ? "Actualizar monitoreo" : "Registrar monitoreo" }}</BButton>
          <BButton variant="outline-secondary" :disabled="saving" @click="startCreate">Cancelar</BButton>
        </div>
      </BCard>
    </div>

    <div class="col-12">
      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div><div class="fw-semibold">Monitoreos registrados</div><div class="small text-muted">{{ items.length }} análisis en la vista actual</div></div>
            <PmeHelpButton title="Ayuda: bandeja de monitoreos" text="Esta tabla permite revisar y editar monitoreos reflexivos, acuerdos, ajustes pendientes y próximos pasos del PME." />
          </div>
        </template>
        <div class="monitoring-summary mb-3">
          <div v-for="metric in monitoringSummary" :key="metric.label" :class="metric.tone ? `is-${metric.tone}` : ''"><i class="bx" :class="metric.icon"></i><span>{{ metric.label }}</span><strong>{{ metric.value }}</strong></div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Plan</th>
                <th>Dimensión</th>
                <th>Acción</th>
                <th>Responsable</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center text-muted">Cargando monitoreos...</td>
              </tr>
              <tr v-else-if="!items.length">
                <td colspan="7" class="text-center text-muted">No hay monitoreos registrados.</td>
              </tr>
              <tr v-for="item in items" :key="item.id">
                <td><strong>{{ formatPmeDate(item.monitored_at) }}</strong></td>
                <td>{{ item.plan?.name }}</td>
                <td>{{ item.dimension?.name || "-" }}</td>
                <td>{{ item.action?.name || "-" }}</td>
                <td>{{ item.responsible_user?.name || "-" }}</td>
                <td><PmeStatusBadge :status="item.state" /></td>
                <td class="text-end">
                  <BButton size="sm" variant="outline-primary" @click="editItem(item)">Editar</BButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <PmePagination :pagination="pagination" :loading="loading" @change="loadItems" />
      </BCard>
    </div>
  </div>
</template>

<style scoped>
.form-section-heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin:1rem 0 .85rem;padding:.65rem .75rem;border-radius:8px;background:#f2f6fc;color:#3156a6}.form-section-heading span{display:flex;align-items:center;gap:.35rem;font-size:.7rem;font-weight:750}.form-section-heading small{color:#78859a;font-size:.6rem}.form-section-heading.is-inner{margin:.2rem 0 0;background:#f8fafc;color:#536b99}.monitoring-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden;border:1px solid var(--pme-border);border-radius:9px}.monitoring-summary>div{display:grid;grid-template-columns:auto 1fr;align-items:center;gap:.08rem .45rem;padding:.68rem;border-right:1px solid #e7ebf1}.monitoring-summary>div:last-child{border:0}.monitoring-summary i{grid-row:1/3;color:#6680b1;font-size:1rem}.monitoring-summary span{color:#7b8798;font-size:.57rem}.monitoring-summary strong{color:#273449;font-size:.78rem}.monitoring-summary .is-success i,.monitoring-summary .is-success strong{color:#16866f}.monitoring-summary .is-warning i,.monitoring-summary .is-warning strong{color:#bd7010}@media(max-width:767px){.form-section-heading{align-items:flex-start;flex-direction:column}.monitoring-summary{grid-template-columns:1fr 1fr}.monitoring-summary>div:nth-child(2){border-right:0}.monitoring-summary>div:nth-child(-n+2){border-bottom:1px solid #e7ebf1}}
</style>
