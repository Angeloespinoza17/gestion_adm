<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  confirmPmeAction,
  downloadPmeFile,
  formatCurrency,
  formatPmeDate,
  formatPmeError,
  normalizeOptions,
  showPmeError,
  showPmeSuccess,
} from "../module-utils";

const actionForm = () => ({
  id: null,
  pme_plan_id: null,
  pme_dimension_id: null,
  pme_objective_id: null,
  pme_strategy_id: null,
  name: "",
  description: "",
  justification: "",
  responsible_user_id: null,
  responsible_area: "",
  start_date: "",
  end_date: "",
  planned_budget: 0,
  committed_budget: 0,
  executed_budget: 0,
  funding_source: "SEP",
  cost_center_reference: "",
  external_accounting_reference: "",
  document_reference: "",
  minimum_evidence_required: 1,
  progress_percentage: 0,
  state: "borrador",
  observations: "",
  indicator_ids: [],
});

const activityForm = () => ({
  pme_action_id: null,
  name: "",
  description: "",
  responsible_user_id: null,
  scheduled_date: "",
  completed_date: "",
  state: "pendiente",
  observations: "",
});

const milestoneForm = () => ({
  id: null,
  pme_action_id: null,
  name: "",
  description: "",
  planned_date: "",
  actual_completion_date: "",
  responsible_user_id: null,
  progress_percentage: 0,
  state: "pendiente",
  observations: "",
});

const evidenceForm = () => ({
  pme_action_id: null,
  pme_activity_id: null,
  pme_milestone_id: null,
  evidence_type: "informe",
  name: "",
  description: "",
  document: null,
  observations: "",
});

const progressForm = () => ({
  progress_percentage: 0,
  executed_budget: 0,
  state: "en_ejecucion",
  notes: "",
});

export default {
  components: { PmeHelpButton, PmeStatusBadge },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
    section: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      selectedActionDetail: null,
      actionForm: actionForm(),
      activityForm: activityForm(),
      milestoneForm: milestoneForm(),
      evidenceForm: evidenceForm(),
      progressForm: progressForm(),
      editingAction: false,
      editingMilestone: false,
      filters: {
        pme_plan_id: null,
        state: null,
        responsible_user_id: null,
        funding_source: null,
        review_status: null,
        evidence_type: null,
      },
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
      return normalizeOptions(this.catalogs.objectives, true);
    },
    strategyOptions() {
      return normalizeOptions(this.catalogs.strategies, true);
    },
    indicatorOptions() {
      return normalizeOptions(this.catalogs.indicators, false);
    },
    actionOptions() {
      return normalizeOptions(this.catalogs.actions, true);
    },
    responsibleOptions() {
      return normalizeOptions(this.catalogs.responsibles, true);
    },
    actionStateOptions() {
      return normalizeOptions(this.catalogs.options?.action_states || [], true);
    },
    fundingOptions() {
      return normalizeOptions(this.catalogs.options?.action_funding_sources || [], true);
    },
    activityStateOptions() {
      return normalizeOptions(this.catalogs.options?.activity_states || [], false);
    },
    milestoneStateOptions() {
      return normalizeOptions(this.catalogs.options?.milestone_states || [], true);
    },
    evidenceTypeOptions() {
      return normalizeOptions(this.catalogs.options?.evidence_types || [], true);
    },
    evidenceStateOptions() {
      return normalizeOptions(this.catalogs.options?.evidence_states || [], true);
    },
  },
  watch: {
    section: {
      immediate: true,
      handler() {
        this.startActionCreate();
        this.startMilestoneCreate();
        this.startEvidenceCreate();
        this.loadItems();
      },
    },
  },
  methods: {
    formatPmeDate,
    formatCurrency,
    startActionCreate() {
      this.editingAction = false;
      this.actionForm = actionForm();
      this.actionForm.pme_plan_id = this.catalogs.active_plan_id || null;
    },
    startMilestoneCreate() {
      this.editingMilestone = false;
      this.milestoneForm = milestoneForm();
    },
    startEvidenceCreate() {
      this.evidenceForm = evidenceForm();
    },
    async loadItems() {
      this.loading = true;
      this.error = null;
      try {
        if (this.section === "acciones") {
          const response = await axios.get("/api/pme-sep/actions", { params: this.filters });
          this.items = response.data.data || [];
        } else if (this.section === "hitos") {
          const response = await axios.get("/api/pme-sep/milestones", { params: this.filters });
          this.items = response.data.data || [];
        } else {
          const response = await axios.get("/api/pme-sep/evidences", { params: this.filters });
          this.items = response.data.data || [];
        }
      } catch (error) {
        this.error = formatPmeError(error, "No se pudo cargar la información de ejecución PME.");
      } finally {
        this.loading = false;
      }
    },
    async loadActionDetail(actionId) {
      const response = await axios.get(`/api/pme-sep/actions/${actionId}`);
      this.selectedActionDetail = response.data.data;
      this.activityForm.pme_action_id = actionId;
      this.milestoneForm.pme_action_id = actionId;
      this.evidenceForm.pme_action_id = actionId;
      this.progressForm.progress_percentage = this.selectedActionDetail.progress_percentage || 0;
      this.progressForm.executed_budget = this.selectedActionDetail.executed_budget || 0;
      this.progressForm.state = this.selectedActionDetail.state || "en_ejecucion";
    },
    editAction(item) {
      this.editingAction = true;
      this.actionForm = {
        id: item.id,
        pme_plan_id: item.pme_plan_id,
        pme_dimension_id: item.pme_dimension_id,
        pme_objective_id: item.pme_objective_id,
        pme_strategy_id: item.pme_strategy_id,
        name: item.name,
        description: item.description || "",
        justification: item.justification || "",
        responsible_user_id: item.responsible_user_id,
        responsible_area: item.responsible_area || "",
        start_date: item.start_date || "",
        end_date: item.end_date || "",
        planned_budget: item.planned_budget || 0,
        committed_budget: item.committed_budget || 0,
        executed_budget: item.executed_budget || 0,
        funding_source: item.funding_source || "SEP",
        cost_center_reference: item.cost_center_reference || "",
        external_accounting_reference: item.external_accounting_reference || "",
        document_reference: item.document_reference || "",
        minimum_evidence_required: item.minimum_evidence_required || 1,
        progress_percentage: item.progress_percentage || 0,
        state: item.state,
        observations: item.observations || "",
        indicator_ids: (item.indicators || []).map((indicator) => indicator.id),
      };
    },
    editMilestone(item) {
      this.editingMilestone = true;
      this.milestoneForm = {
        id: item.id,
        pme_action_id: item.pme_action_id,
        name: item.name,
        description: item.description || "",
        planned_date: item.planned_date || "",
        actual_completion_date: item.actual_completion_date || "",
        responsible_user_id: item.responsible_user_id,
        progress_percentage: item.progress_percentage || 0,
        state: item.state,
        observations: item.observations || "",
      };
    },
    async saveAction() {
      this.saving = true;
      try {
        if (this.editingAction && this.actionForm.id) {
          await axios.put(`/api/pme-sep/actions/${this.actionForm.id}`, this.actionForm);
        } else {
          await axios.post("/api/pme-sep/actions", this.actionForm);
        }
        showPmeSuccess("Acción PME guardada correctamente.");
        this.startActionCreate();
        this.loadItems();
        this.$emit("refresh-catalogs");
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la acción PME."));
      } finally {
        this.saving = false;
      }
    },
    async saveActivity() {
      this.saving = true;
      try {
        await axios.post("/api/pme-sep/activities", this.activityForm);
        showPmeSuccess("Actividad registrada correctamente.");
        this.activityForm = activityForm();
        if (this.selectedActionDetail?.id) {
          this.loadActionDetail(this.selectedActionDetail.id);
        }
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la actividad."));
      } finally {
        this.saving = false;
      }
    },
    async saveMilestone() {
      this.saving = true;
      try {
        if (this.editingMilestone && this.milestoneForm.id) {
          await axios.put(`/api/pme-sep/milestones/${this.milestoneForm.id}`, this.milestoneForm);
        } else {
          await axios.post("/api/pme-sep/milestones", this.milestoneForm);
        }
        showPmeSuccess("Hito guardado correctamente.");
        this.startMilestoneCreate();
        this.loadItems();
        if (this.selectedActionDetail?.id) {
          this.loadActionDetail(this.selectedActionDetail.id);
        }
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar el hito."));
      } finally {
        this.saving = false;
      }
    },
    async saveEvidence() {
      this.saving = true;
      try {
        const formData = new FormData();
        Object.entries(this.evidenceForm).forEach(([key, value]) => {
          if (value !== null && value !== undefined) {
            formData.append(key, value);
          }
        });
        await axios.post("/api/pme-sep/evidences", formData);
        showPmeSuccess("Evidencia cargada correctamente.");
        this.startEvidenceCreate();
        this.loadItems();
        if (this.selectedActionDetail?.id) {
          this.loadActionDetail(this.selectedActionDetail.id);
        }
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo cargar la evidencia."));
      } finally {
        this.saving = false;
      }
    },
    async registerProgress() {
      if (!this.selectedActionDetail?.id) {
        showPmeError("Debes seleccionar una acción para registrar avance.");
        return;
      }
      try {
        await axios.post(`/api/pme-sep/actions/${this.selectedActionDetail.id}/progress`, this.progressForm);
        showPmeSuccess("Avance registrado correctamente.");
        this.loadActionDetail(this.selectedActionDetail.id);
        this.loadItems();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo registrar el avance."));
      }
    },
    async closeAction() {
      if (!this.selectedActionDetail?.id) return;
      const confirmed = await confirmPmeAction({
        title: "Cerrar acción",
        text: "La acción quedará cerrada y se validará la evidencia mínima requerida.",
        confirmButtonText: "Sí, cerrar acción",
      });
      if (!confirmed.isConfirmed) return;
      await axios.post(`/api/pme-sep/actions/${this.selectedActionDetail.id}/close`, { state: "cerrada", notes: "Cierre desde interfaz." });
      showPmeSuccess("Acción cerrada correctamente.");
      this.loadActionDetail(this.selectedActionDetail.id);
      this.loadItems();
    },
    async reopenAction() {
      if (!this.selectedActionDetail?.id) return;
      await axios.post(`/api/pme-sep/actions/${this.selectedActionDetail.id}/reopen`, { state: "en_monitoreo", notes: "Reapertura desde interfaz." });
      showPmeSuccess("Acción reabierta correctamente.");
      this.loadActionDetail(this.selectedActionDetail.id);
      this.loadItems();
    },
    async reviewEvidence(item, status) {
      await axios.post(`/api/pme-sep/evidences/${item.id}/review`, {
        review_status: status,
        review_comments: `Estado actualizado a ${status}.`,
      });
      showPmeSuccess("Revisión de evidencia registrada.");
      this.loadItems();
      if (this.selectedActionDetail?.id) {
        this.loadActionDetail(this.selectedActionDetail.id);
      }
    },
    async downloadEvidence(item) {
      try {
        await downloadPmeFile(`/api/pme-sep/evidences/${item.id}/download`, item.original_name || item.name);
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo descargar la evidencia."));
      }
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">
            {{ section === "acciones" ? "Gestión de acciones PME" : section === "hitos" ? "Gestión de hitos" : "Gestión de evidencias" }}
          </div>
          <PmeHelpButton :title="section === 'acciones' ? 'Ayuda: acciones PME' : section === 'hitos' ? 'Ayuda: hitos PME' : 'Ayuda: evidencias PME'" :text="section === 'acciones' ? 'Aquí se administran acciones PME, responsables, presupuesto, indicadores, actividades y avance.' : section === 'hitos' ? 'Aquí se administran hitos por acción y su línea de tiempo de cumplimiento.' : 'Aquí se cargan, revisan, aprueban o rechazan evidencias vinculadas al PME.'" />
        </div>
      </template>

      <div v-if="section === 'acciones'" class="row g-3">
        <div class="col-md-3"><label class="form-label">Plan</label><BFormSelect v-model="actionForm.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Dimensión</label><BFormSelect v-model="actionForm.pme_dimension_id" :options="dimensionOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Objetivo</label><BFormSelect v-model="actionForm.pme_objective_id" :options="objectiveOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Estrategia</label><BFormSelect v-model="actionForm.pme_strategy_id" :options="strategyOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-6"><label class="form-label">Nombre acción</label><BFormInput v-model="actionForm.name" /></div>
        <div class="col-md-6"><label class="form-label">Área responsable</label><BFormInput v-model="actionForm.responsible_area" /></div>
        <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="actionForm.description" rows="2" /></div>
        <div class="col-12"><label class="form-label">Justificación</label><BFormTextarea v-model="actionForm.justification" rows="2" /></div>
        <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="actionForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Inicio</label><BFormInput v-model="actionForm.start_date" type="date" /></div>
        <div class="col-md-4"><label class="form-label">Término</label><BFormInput v-model="actionForm.end_date" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Planificado</label><BFormInput v-model="actionForm.planned_budget" type="number" /></div>
        <div class="col-md-3"><label class="form-label">Comprometido</label><BFormInput v-model="actionForm.committed_budget" type="number" /></div>
        <div class="col-md-3"><label class="form-label">Ejecutado</label><BFormInput v-model="actionForm.executed_budget" type="number" /></div>
        <div class="col-md-3"><label class="form-label">Fuente financiamiento</label><BFormSelect v-model="actionForm.funding_source" :options="fundingOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="actionForm.state" :options="actionStateOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Centro de costo</label><BFormInput v-model="actionForm.cost_center_reference" /></div>
        <div class="col-md-4"><label class="form-label">Referencia documental</label><BFormInput v-model="actionForm.document_reference" /></div>
        <div class="col-12">
          <label class="form-label">Indicadores asociados</label>
          <BFormSelect v-model="actionForm.indicator_ids" multiple :options="indicatorOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="actionForm.observations" rows="2" /></div>
        <div class="col-12 d-flex gap-2"><BButton variant="primary" @click="saveAction">Guardar acción</BButton><BButton variant="outline-secondary" @click="startActionCreate">Limpiar</BButton></div>
      </div>

      <div v-else-if="section === 'hitos'" class="row g-3">
        <div class="col-md-4"><label class="form-label">Acción</label><BFormSelect v-model="milestoneForm.pme_action_id" :options="actionOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Nombre hito</label><BFormInput v-model="milestoneForm.name" /></div>
        <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="milestoneForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Fecha planificada</label><BFormInput v-model="milestoneForm.planned_date" type="date" /></div>
        <div class="col-md-4"><label class="form-label">Fecha real</label><BFormInput v-model="milestoneForm.actual_completion_date" type="date" /></div>
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="milestoneForm.state" :options="milestoneStateOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Avance %</label><BFormInput v-model="milestoneForm.progress_percentage" type="number" /></div>
        <div class="col-md-8"><label class="form-label">Descripción</label><BFormTextarea v-model="milestoneForm.description" rows="2" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="milestoneForm.observations" rows="2" /></div>
        <div class="col-12 d-flex gap-2"><BButton variant="primary" @click="saveMilestone">Guardar hito</BButton><BButton variant="outline-secondary" @click="startMilestoneCreate">Limpiar</BButton></div>
      </div>

      <div v-else class="row g-3">
        <div class="col-md-4"><label class="form-label">Acción</label><BFormSelect v-model="evidenceForm.pme_action_id" :options="actionOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Tipo</label><BFormSelect v-model="evidenceForm.evidence_type" :options="evidenceTypeOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Nombre</label><BFormInput v-model="evidenceForm.name" /></div>
        <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="evidenceForm.description" rows="2" /></div>
        <div class="col-md-6"><label class="form-label">Archivo</label><BFormFile @change="evidenceForm.document = $event.target.files[0]" /></div>
        <div class="col-md-6"><label class="form-label">Observaciones</label><BFormTextarea v-model="evidenceForm.observations" rows="2" /></div>
        <div class="col-12"><BButton variant="primary" @click="saveEvidence">Cargar evidencia</BButton></div>
      </div>
    </BCard>

    <BCard v-if="section === 'acciones' && selectedActionDetail" class="border-0 shadow-sm">
      <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Detalle de acción y actividades</div><PmeHelpButton title="Ayuda: detalle de acción" text="Desde aquí se registran actividades, avances, evidencias y cierre de la acción seleccionada." /></div></template>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Avance %</label><BFormInput v-model="progressForm.progress_percentage" type="number" /></div>
        <div class="col-md-4"><label class="form-label">Ejecutado</label><BFormInput v-model="progressForm.executed_budget" type="number" /></div>
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="progressForm.state" :options="actionStateOptions.filter((item) => item.value).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-12"><label class="form-label">Notas</label><BFormTextarea v-model="progressForm.notes" rows="2" /></div>
        <div class="col-12 d-flex gap-2"><BButton variant="outline-primary" @click="registerProgress">Registrar avance</BButton><BButton variant="outline-warning" @click="closeAction">Cerrar acción</BButton><BButton variant="outline-secondary" @click="reopenAction">Reabrir acción</BButton></div>
      </div>
      <hr />
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Actividad</label><BFormInput v-model="activityForm.name" /></div>
        <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="activityForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Fecha programada</label><BFormInput v-model="activityForm.scheduled_date" type="date" /></div>
        <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="activityForm.description" rows="2" /></div>
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="activityForm.state" :options="activityStateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-8"><label class="form-label">Observaciones</label><BFormTextarea v-model="activityForm.observations" rows="2" /></div>
        <div class="col-12"><BButton variant="outline-success" @click="saveActivity">Guardar actividad</BButton></div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-xl-6">
          <h6>Actividades</h6>
          <ul class="list-group">
            <li v-for="activity in selectedActionDetail.activities" :key="activity.id" class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold">{{ activity.name }}</div>
                <div class="small text-muted">{{ activity.scheduled_date }}</div>
              </div>
              <PmeStatusBadge :status="activity.state" />
            </li>
          </ul>
        </div>
        <div class="col-xl-6">
          <h6>Evidencias</h6>
          <ul class="list-group">
            <li v-for="evidence in selectedActionDetail.evidences" :key="evidence.id" class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold">{{ evidence.name }}</div>
                <div class="small text-muted">{{ evidence.original_name }}</div>
              </div>
              <PmeStatusBadge :status="evidence.review_status" />
            </li>
          </ul>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">{{ section === "acciones" ? "Acciones registradas" : section === "hitos" ? "Hitos registrados" : "Evidencias registradas" }}</div>
          <PmeHelpButton :title="section === 'acciones' ? 'Ayuda: tabla de acciones' : section === 'hitos' ? 'Ayuda: tabla de hitos' : 'Ayuda: tabla de evidencias'" :text="section === 'acciones' ? 'La tabla muestra acciones con presupuesto, estado, responsable y cantidad de hitos, actividades y evidencias.' : section === 'hitos' ? 'La tabla muestra hitos por acción con fecha planificada, avance y estado.' : 'La tabla muestra evidencias, tipo, acción asociada y estado de revisión.'" />
        </div>
      </template>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr v-if="section === 'acciones'"><th>Acción</th><th>Responsable</th><th>Estado</th><th>Planificado</th><th>Ejecutado</th><th>Evidencias</th><th></th></tr>
            <tr v-else-if="section === 'hitos'"><th>Hito</th><th>Acción</th><th>Planificada</th><th>Avance</th><th>Estado</th><th></th></tr>
            <tr v-else><th>Evidencia</th><th>Acción</th><th>Tipo</th><th>Estado</th><th>Archivo</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td :colspan="section === 'acciones' ? 7 : 6" class="text-center text-muted">Cargando información...</td></tr>
            <tr v-else-if="!items.length"><td :colspan="section === 'acciones' ? 7 : 6" class="text-center text-muted">Sin registros.</td></tr>
            <tr v-for="item in items" :key="item.id">
              <template v-if="section === 'acciones'">
                <td>{{ item.name }}</td><td>{{ item.responsible_user?.name || '-' }}</td><td><PmeStatusBadge :status="item.state" /></td><td>{{ formatCurrency(item.planned_budget) }}</td><td>{{ formatCurrency(item.executed_budget) }}</td><td>{{ item.evidences_count }}</td>
                <td class="text-end"><BButton size="sm" variant="outline-info" @click="loadActionDetail(item.id)">Detalle</BButton> <BButton size="sm" variant="outline-primary" @click="editAction(item)">Editar</BButton></td>
              </template>
              <template v-else-if="section === 'hitos'">
                <td>{{ item.name }}</td><td>{{ item.action?.name }}</td><td>{{ formatPmeDate(item.planned_date) }}</td><td>{{ item.progress_percentage }}%</td><td><PmeStatusBadge :status="item.state" /></td>
                <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editMilestone(item)">Editar</BButton></td>
              </template>
              <template v-else>
                <td>{{ item.name }}</td><td>{{ item.action?.name || '-' }}</td><td>{{ item.evidence_type }}</td><td><PmeStatusBadge :status="item.review_status" /></td><td>{{ item.original_name || '-' }}</td>
                <td class="text-end">
                  <BButton size="sm" variant="outline-secondary" @click="downloadEvidence(item)">Descargar</BButton>
                  <BButton size="sm" variant="outline-success" @click="reviewEvidence(item, 'aprobada')">Aprobar</BButton>
                  <BButton size="sm" variant="outline-warning" @click="reviewEvidence(item, 'observada')">Observar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="reviewEvidence(item, 'rechazada')">Rechazar</BButton>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>
  </div>
</template>
