<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  confirmPmeAction,
  formatPmeDate,
  formatPmeError,
  normalizeOptions,
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
  components: { PmeHelpButton, PmeStatusBadge },
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
    async loadItems() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/pme-sep/monitorings", {
          params: this.filters,
        });
        this.items = response.data.data || [];
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
            <div class="fw-semibold">Filtros y registro de monitoreo reflexivo</div>
            <PmeHelpButton
              title="Ayuda: monitoreo reflexivo"
              text="Aquí se registran análisis cualitativos del avance PME, decisiones tomadas, dificultades, evidencias revisadas y ajustes requeridos."
            />
          </div>
        </template>

        <div class="row g-3">
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
          <div class="col-md-3 d-flex align-items-end">
            <BButton variant="primary" class="w-100" @click="loadItems">Aplicar filtros</BButton>
          </div>
        </div>

        <hr />

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
          <BButton variant="primary" :disabled="saving" @click="save">{{ editing ? "Actualizar" : "Registrar" }}</BButton>
          <BButton variant="outline-secondary" @click="startCreate">Limpiar</BButton>
        </div>
      </BCard>
    </div>

    <div class="col-12">
      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
      <BCard class="border-0 shadow-sm">
        <template #header>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="fw-semibold">Monitoreos registrados</div>
            <PmeHelpButton title="Ayuda: bandeja de monitoreos" text="Esta tabla permite revisar y editar monitoreos reflexivos, acuerdos, ajustes pendientes y próximos pasos del PME." />
          </div>
        </template>
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
                <td>{{ formatPmeDate(item.monitored_at) }}</td>
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
      </BCard>
    </div>
  </div>
</template>
