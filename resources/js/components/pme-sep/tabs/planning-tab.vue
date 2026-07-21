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

const planForm = () => ({
  id: null,
  academic_year_id: null,
  school_year: new Date().getFullYear(),
  name: "",
  period_label: "Marzo a diciembre",
  cycle_name: "planificacion",
  start_date: `${new Date().getFullYear()}-03-01`,
  end_date: `${new Date().getFullYear()}-12-20`,
  responsible_user_id: null,
  state: "borrador",
  general_description: "",
  observations: "",
  duplicate_from_plan_id: null,
});

const dimensionForm = () => ({
  id: null,
  name: "",
  description: "",
  active: true,
  sort_order: 1,
});

const objectiveForm = () => ({
  id: null,
  pme_plan_id: null,
  pme_dimension_id: null,
  name: "",
  description: "",
  strategic_goal: "",
  global_indicator: "",
  responsible_user_id: null,
  start_date: "",
  end_date: "",
  state: "vigente",
  observations: "",
});

const strategyForm = () => ({
  id: null,
  pme_objective_id: null,
  name: "",
  description: "",
  responsible_user_id: null,
  execution_period: "Marzo - Diciembre",
  state: "planificada",
  observations: "",
});

const measurementForm = () => ({
  id: null,
  pme_objective_id: null,
  goal_label: "",
  baseline_value: null,
  expected_result: null,
  current_result: null,
  information_source: "",
  measured_at: new Date().toISOString().slice(0, 10),
  responsible_user_id: null,
  analysis: "",
  state: "en_avance",
});

export default {
  components: { PmeHelpButton, PmePagination, PmeStatusBadge },
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
      pagination: normalizePagination(),
      planDetail: null,
      measurements: [],
      selectedObjectiveId: null,
      planForm: planForm(),
      dimensionForm: dimensionForm(),
      objectiveForm: objectiveForm(),
      strategyForm: strategyForm(),
      measurementForm: measurementForm(),
      editing: false,
    };
  },
  computed: {
    planOptions() {
      return normalizeOptions(this.catalogs.plans, true);
    },
    yearOptions() {
      return normalizeOptions(this.catalogs.academic_years, true);
    },
    dimensionOptions() {
      return normalizeOptions(this.catalogs.dimensions, true);
    },
    objectiveOptions() {
      return normalizeOptions(this.catalogs.objectives, true);
    },
    responsibleOptions() {
      return normalizeOptions(this.catalogs.responsibles, true);
    },
    planStateOptions() {
      return normalizeOptions(this.catalogs.options?.plan_states || [], false);
    },
    objectiveStateOptions() {
      return normalizeOptions(this.catalogs.options?.objective_states || [], false);
    },
    strategyStateOptions() {
      return normalizeOptions(this.catalogs.options?.strategy_states || [], false);
    },
    goalStateOptions() {
      return normalizeOptions(this.catalogs.options?.goal_states || [], false);
    },
  },
  watch: {
    section: {
      immediate: true,
      handler() {
        this.loadData();
      },
    },
  },
  methods: {
    formatPmeDate,
    async loadData(page = 1) {
      const requestedPage = Number.isInteger(page) ? page : 1;
      this.loading = true;
      this.error = null;
      try {
        if (this.section === "configuracion") {
          const response = await axios.get("/api/pme-sep/plans", { params: { page: requestedPage } });
          this.items = response.data.data || [];
          this.pagination = normalizePagination(response.data);
          const active = this.items.find((item) => item.is_active) || this.items[0];
          if (active) {
            await this.loadPlanDetail(active.id);
          }
          this.startPlanCreate();
        } else if (this.section === "dimensiones") {
          const response = await axios.get("/api/pme-sep/dimensions");
          this.items = response.data.data || [];
          this.pagination = normalizePagination({ data: this.items, total: this.items.length });
          this.dimensionForm = dimensionForm();
        } else if (this.section === "objetivos") {
          const response = await axios.get("/api/pme-sep/objectives", { params: { page: requestedPage } });
          this.items = response.data.data || [];
          this.pagination = normalizePagination(response.data);
          this.objectiveForm = objectiveForm();
          this.objectiveForm.pme_plan_id = this.catalogs.active_plan_id || null;
        } else if (this.section === "estrategias") {
          const response = await axios.get("/api/pme-sep/strategies", { params: { page: requestedPage } });
          this.items = response.data.data || [];
          this.pagination = normalizePagination(response.data);
          this.strategyForm = strategyForm();
        } else if (this.section === "metas") {
          const response = await axios.get("/api/pme-sep/objectives", { params: { page: requestedPage } });
          this.items = response.data.data || [];
          this.pagination = normalizePagination(response.data);
          this.selectedObjectiveId = this.items[0]?.id || null;
          this.measurementForm = measurementForm();
          this.measurementForm.pme_objective_id = this.selectedObjectiveId;
          if (this.selectedObjectiveId) {
            await this.loadMeasurements(this.selectedObjectiveId);
          } else {
            this.measurements = [];
          }
        }
      } catch (error) {
        this.error = formatPmeError(error, "No se pudo cargar la información de planificación PME.");
      } finally {
        this.loading = false;
      }
    },
    async loadPlanDetail(planId) {
      const response = await axios.get(`/api/pme-sep/plans/${planId}`);
      this.planDetail = response.data.data;
    },
    async loadMeasurements(objectiveId) {
      const response = await axios.get(`/api/pme-sep/objectives/${objectiveId}/measurements`);
      this.measurements = response.data.data || [];
    },
    startPlanCreate() {
      this.editing = false;
      this.planForm = planForm();
      this.planForm.academic_year_id = this.catalogs.active_academic_year_id || null;
      this.planForm.responsible_user_id = this.catalogs.responsibles?.[0]?.id || null;
    },
    startDimensionCreate() {
      this.editing = false;
      this.dimensionForm = dimensionForm();
      this.dimensionForm.sort_order = (this.items?.length || 0) + 1;
    },
    startObjectiveCreate() {
      this.editing = false;
      this.objectiveForm = objectiveForm();
      this.objectiveForm.pme_plan_id = this.catalogs.active_plan_id || null;
    },
    startStrategyCreate() {
      this.editing = false;
      this.strategyForm = strategyForm();
    },
    startMeasurementCreate() {
      this.editing = false;
      this.measurementForm = measurementForm();
      this.measurementForm.pme_objective_id = this.selectedObjectiveId;
    },
    editPlan(item) {
      this.editing = true;
      this.planForm = {
        id: item.id,
        academic_year_id: item.academic_year_id,
        school_year: item.school_year,
        name: item.name,
        period_label: item.period_label || "",
        cycle_name: item.cycle_name || "",
        start_date: item.start_date,
        end_date: item.end_date,
        responsible_user_id: item.responsible_user_id,
        state: item.state,
        general_description: item.general_description || "",
        observations: item.observations || "",
        duplicate_from_plan_id: null,
      };
    },
    editDimension(item) {
      this.editing = true;
      this.dimensionForm = { ...item };
    },
    editObjective(item) {
      this.editing = true;
      this.objectiveForm = {
        id: item.id,
        pme_plan_id: item.pme_plan_id,
        pme_dimension_id: item.pme_dimension_id,
        name: item.name,
        description: item.description || "",
        strategic_goal: item.strategic_goal || "",
        global_indicator: item.global_indicator || "",
        responsible_user_id: item.responsible_user_id,
        start_date: item.start_date || "",
        end_date: item.end_date || "",
        state: item.state,
        observations: item.observations || "",
      };
    },
    editStrategy(item) {
      this.editing = true;
      this.strategyForm = {
        id: item.id,
        pme_objective_id: item.pme_objective_id,
        name: item.name,
        description: item.description || "",
        responsible_user_id: item.responsible_user_id,
        execution_period: item.execution_period || "",
        state: item.state,
        observations: item.observations || "",
      };
    },
    editMeasurement(item) {
      this.editing = true;
      this.measurementForm = {
        id: item.id,
        pme_objective_id: item.pme_objective_id,
        goal_label: item.goal_label,
        baseline_value: item.baseline_value,
        expected_result: item.expected_result,
        current_result: item.current_result,
        information_source: item.information_source || "",
        measured_at: item.measured_at,
        responsible_user_id: item.responsible_user_id,
        analysis: item.analysis || "",
        state: item.state,
      };
    },
    async savePlan() {
      this.saving = true;
      try {
        const confirmed = await confirmPmeAction({
          title: this.editing ? "Actualizar PME" : "Guardar PME",
          text: "Se registrará la configuración general del PME.",
          confirmButtonText: this.editing ? "Sí, actualizar" : "Sí, guardar",
        });
        if (!confirmed.isConfirmed) return;

        const payload = { ...this.planForm };
        const duplicateSourceId = payload.duplicate_from_plan_id;
        delete payload.duplicate_from_plan_id;

        if (!this.editing && duplicateSourceId) {
          await axios.post(`/api/pme-sep/plans/${duplicateSourceId}/duplicate`, payload);
        } else if (this.editing && payload.id) {
          await axios.put(`/api/pme-sep/plans/${payload.id}`, payload);
        } else {
          await axios.post("/api/pme-sep/plans", payload);
        }

        showPmeSuccess("PME guardado correctamente.");
        this.startPlanCreate();
        this.$emit("refresh-catalogs");
        this.loadData();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la configuración del PME."));
      } finally {
        this.saving = false;
      }
    },
    async saveDimension() {
      this.saving = true;
      try {
        if (this.editing && this.dimensionForm.id) {
          await axios.put(`/api/pme-sep/dimensions/${this.dimensionForm.id}`, this.dimensionForm);
        } else {
          await axios.post("/api/pme-sep/dimensions", this.dimensionForm);
        }
        showPmeSuccess("Dimensión guardada correctamente.");
        this.startDimensionCreate();
        this.$emit("refresh-catalogs");
        this.loadData();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la dimensión PME."));
      } finally {
        this.saving = false;
      }
    },
    async saveObjective() {
      this.saving = true;
      try {
        if (this.editing && this.objectiveForm.id) {
          await axios.put(`/api/pme-sep/objectives/${this.objectiveForm.id}`, this.objectiveForm);
        } else {
          await axios.post("/api/pme-sep/objectives", this.objectiveForm);
        }
        showPmeSuccess("Objetivo estratégico guardado correctamente.");
        this.startObjectiveCreate();
        this.$emit("refresh-catalogs");
        this.loadData();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar el objetivo estratégico."));
      } finally {
        this.saving = false;
      }
    },
    async saveStrategy() {
      this.saving = true;
      try {
        if (this.editing && this.strategyForm.id) {
          await axios.put(`/api/pme-sep/strategies/${this.strategyForm.id}`, this.strategyForm);
        } else {
          await axios.post("/api/pme-sep/strategies", this.strategyForm);
        }
        showPmeSuccess("Estrategia guardada correctamente.");
        this.startStrategyCreate();
        this.$emit("refresh-catalogs");
        this.loadData();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la estrategia."));
      } finally {
        this.saving = false;
      }
    },
    async saveMeasurement() {
      this.saving = true;
      try {
        if (this.editing && this.measurementForm.id) {
          await axios.put(`/api/pme-sep/goal-measurements/${this.measurementForm.id}`, this.measurementForm);
        } else {
          await axios.post(`/api/pme-sep/objectives/${this.measurementForm.pme_objective_id}/measurements`, this.measurementForm);
        }
        showPmeSuccess("Medición de meta guardada correctamente.");
        this.startMeasurementCreate();
        this.loadMeasurements(this.selectedObjectiveId);
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar la medición de meta estratégica."));
      } finally {
        this.saving = false;
      }
    },
    async activatePlan(item) {
      const confirmed = await confirmPmeAction({
        title: "Activar PME",
        text: "Este PME quedará como vigente para el dashboard y la operación del módulo.",
        confirmButtonText: "Sí, activar",
      });
      if (!confirmed.isConfirmed) return;
      await axios.post(`/api/pme-sep/plans/${item.id}/activate`, {});
      showPmeSuccess("PME activado correctamente.");
      this.$emit("refresh-catalogs");
      this.loadData();
    },
    async closePlan(item) {
      const confirmed = await confirmPmeAction({
        title: "Cerrar PME",
        text: "Se confirmará el cierre del PME y del ciclo operativo vigente.",
        confirmButtonText: "Sí, cerrar",
        icon: "warning",
      });
      if (!confirmed.isConfirmed) return;
      await axios.post(`/api/pme-sep/plans/${item.id}/close`, { observations: "Cierre confirmado desde interfaz." });
      showPmeSuccess("PME cerrado correctamente.");
      this.$emit("refresh-catalogs");
      this.loadData();
    },
    async archivePlan(item) {
      const confirmed = await confirmPmeAction({
        title: "Archivar PME",
        text: "El plan pasará a estado archivado para su historial institucional.",
        confirmButtonText: "Sí, archivar",
        icon: "warning",
      });
      if (!confirmed.isConfirmed) return;
      await axios.post(`/api/pme-sep/plans/${item.id}/archive`, { observations: "Archivado desde interfaz." });
      showPmeSuccess("PME archivado correctamente.");
      this.$emit("refresh-catalogs");
      this.loadData();
    },
    async closeCycle(cycle) {
      const confirmed = await confirmPmeAction({
        title: "Cerrar ciclo PME",
        text: "Se cerrará el ciclo actual y avanzará el siguiente si corresponde.",
        confirmButtonText: "Sí, cerrar ciclo",
      });
      if (!confirmed.isConfirmed) return;
      await axios.post(`/api/pme-sep/cycles/${cycle.id}/close`, { observations: "Ciclo cerrado desde interfaz." });
      showPmeSuccess("Ciclo PME cerrado correctamente.");
      if (this.planDetail?.id) {
        this.loadPlanDetail(this.planDetail.id);
      }
      this.$emit("refresh-catalogs");
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard v-if="loading" class="border-0 shadow-sm">
      <div class="text-muted">Cargando información...</div>
    </BCard>

    <template v-else>
      <div v-if="section === 'configuracion'" class="row g-3">
        <div class="col-xl-5">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div><div class="fw-semibold">{{ editing ? 'Editar plan PME' : 'Nuevo plan PME' }}</div><div class="small text-muted">Configuración anual y ciclo de trabajo</div></div>
                <PmeHelpButton title="Ayuda: configuración PME" text="Aquí se crea o actualiza el PME anual con año escolar, fechas, responsable general, estado y opción de duplicar estructura del período anterior." />
              </div>
            </template>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Año académico</label>
                <BFormSelect v-model="planForm.academic_year_id" :options="yearOptions.map((item) => ({ value: item.value, text: item.label }))" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Año escolar</label>
                <BFormInput v-model="planForm.school_year" type="number" />
              </div>
              <div class="col-12">
                <label class="form-label">Nombre PME</label>
                <BFormInput v-model="planForm.name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Periodo</label>
                <BFormInput v-model="planForm.period_label" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Ciclo</label>
                <BFormInput v-model="planForm.cycle_name" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha inicio</label>
                <BFormInput v-model="planForm.start_date" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Fecha término</label>
                <BFormInput v-model="planForm.end_date" type="date" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Responsable general</label>
                <BFormSelect v-model="planForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Estado</label>
                <BFormSelect v-model="planForm.state" :options="planStateOptions.map((item) => ({ value: item.value, text: item.label }))" />
              </div>
              <div class="col-12">
                <label class="form-label">Descripción general</label>
                <BFormTextarea v-model="planForm.general_description" rows="3" />
              </div>
              <div class="col-12">
                <label class="form-label">Observaciones</label>
                <BFormTextarea v-model="planForm.observations" rows="2" />
              </div>
              <div class="col-12">
                <label class="form-label">Duplicar estructura desde</label>
                <BFormSelect v-model="planForm.duplicate_from_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" />
              </div>
            </div>
            <div class="d-flex gap-2 mt-3">
              <BButton variant="primary" :disabled="saving" @click="savePlan">{{ editing ? "Actualizar PME" : "Guardar PME" }}</BButton>
              <BButton variant="outline-secondary" :disabled="saving" @click="startPlanCreate">Cancelar</BButton>
            </div>
          </BCard>
        </div>

        <div class="col-xl-7">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div><div class="fw-semibold">PME registrados y ciclos</div><div class="small text-muted">{{ items.length }} planes en el historial institucional</div></div>
                <PmeHelpButton title="Ayuda: historial PME" text="Aquí se revisan los PME vigentes e históricos, con acciones para activar, cerrar, archivar o revisar el detalle del plan y sus ciclos." />
              </div>
            </template>
            <div class="row g-3">
              <div v-if="!items.length" class="col-12"><div class="pme-empty"><i class="bx bx-calendar-plus"></i><strong>Aún no hay planes registrados</strong><span>Crea el primer PME anual desde el formulario.</span></div></div>
              <div v-for="item in items" :key="item.id" class="col-12">
                <div class="plan-history-card" :class="{ 'is-active': item.is_active }">
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                      <div class="fw-semibold">{{ item.name }}</div>
                      <div class="small text-muted">Año {{ item.school_year }} · {{ item.period_label }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <PmeStatusBadge :status="item.state" />
                      <BBadge v-if="item.is_active" variant="success">Vigente</BBadge>
                    </div>
                  </div>
                  <div class="d-flex gap-2 mt-3 flex-wrap">
                    <BButton size="sm" variant="outline-primary" @click="loadPlanDetail(item.id)"><i class="bx bx-show"></i>Detalle</BButton>
                    <BButton size="sm" variant="outline-secondary" @click="editPlan(item)"><i class="bx bx-edit-alt"></i>Editar</BButton>
                    <BButton v-if="!item.is_active" size="sm" variant="outline-success" @click="activatePlan(item)"><i class="bx bx-check-circle"></i>Activar</BButton>
                    <BButton v-if="!['cerrado', 'archivado'].includes(item.state)" size="sm" variant="outline-warning" @click="closePlan(item)">Cerrar</BButton>
                    <BButton v-if="item.state !== 'archivado'" size="sm" variant="outline-dark" @click="archivePlan(item)">Archivar</BButton>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="planDetail" class="mt-4">
              <h6>Detalle del plan seleccionado: {{ planDetail.name }}</h6>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead>
                    <tr>
                      <th>Ciclo</th>
                      <th>Estado</th>
                      <th>Inicio</th>
                      <th>Término</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="cycle in planDetail.cycles" :key="cycle.id">
                      <td>{{ cycle.name }}</td>
                      <td><PmeStatusBadge :status="cycle.state" /></td>
                      <td>{{ formatPmeDate(cycle.start_date) }}</td>
                      <td>{{ formatPmeDate(cycle.end_date) }}</td>
                      <td class="text-end">
                        <BButton v-if="cycle.is_current" size="sm" variant="outline-warning" @click="closeCycle(cycle)">Cerrar ciclo</BButton>
                      </td>
                    </tr>
                    <tr v-if="!planDetail.cycles?.length"><td colspan="5" class="text-center text-muted">El plan no tiene ciclos registrados.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </BCard>
        </div>
      </div>

      <div v-else-if="section === 'dimensiones'" class="row g-3">
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">{{ editing ? 'Editar dimensión' : 'Nueva dimensión' }}</div><div class="small text-muted">Estructura estratégica del PME</div></div><PmeHelpButton title="Ayuda: dimensiones PME" text="Aquí se administran las dimensiones PME y su orden institucional." /></div></template>
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Nombre</label><BFormInput v-model="dimensionForm.name" /></div>
              <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="dimensionForm.description" rows="3" /></div>
              <div class="col-md-6"><label class="form-label">Orden</label><BFormInput v-model="dimensionForm.sort_order" type="number" /></div>
              <div class="col-md-6 d-flex align-items-end"><BFormCheckbox v-model="dimensionForm.active">Activa</BFormCheckbox></div>
            </div>
            <div class="d-flex gap-2 mt-3"><BButton variant="primary" :disabled="saving" @click="saveDimension">{{ editing ? 'Actualizar' : 'Guardar' }}</BButton><BButton variant="outline-secondary" :disabled="saving" @click="startDimensionCreate">Cancelar</BButton></div>
          </BCard>
        </div>
        <div class="col-xl-8">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Dimensiones registradas</div><div class="small text-muted">{{ items.length }} dimensiones definidas</div></div><PmeHelpButton title="Ayuda: tabla de dimensiones" text="La tabla muestra el catálogo PME con orden, estado y carga asociada." /></div></template>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Orden</th><th>Nombre</th><th>Estado</th><th>Objetivos</th><th>Acciones</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="item in items" :key="item.id">
                    <td><span class="sort-order">{{ item.sort_order }}</span></td><td><div class="fw-semibold">{{ item.name }}</div><div class="small text-muted">{{ item.description || 'Sin descripción' }}</div></td><td><PmeStatusBadge :status="item.active ? 'activo' : 'inactivo'" /></td><td>{{ item.objectives_count }}</td><td>{{ item.actions_count }}</td>
                    <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editDimension(item)">Editar</BButton></td>
                  </tr>
                  <tr v-if="!items.length"><td colspan="6" class="text-center text-muted">No hay dimensiones registradas.</td></tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>

      <div v-else-if="section === 'objetivos'" class="row g-3">
        <div class="col-xl-5">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Formulario objetivo estratégico</div><PmeHelpButton title="Ayuda: objetivos estratégicos" text="Aquí se registran objetivos estratégicos PME asociados a dimensión, meta, indicador global, responsable, fechas y estado." /></div></template>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Plan</label><BFormSelect v-model="objectiveForm.pme_plan_id" :options="planOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-md-6"><label class="form-label">Dimensión</label><BFormSelect v-model="objectiveForm.pme_dimension_id" :options="dimensionOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-12"><label class="form-label">Nombre objetivo</label><BFormInput v-model="objectiveForm.name" /></div>
              <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="objectiveForm.description" rows="3" /></div>
              <div class="col-12"><label class="form-label">Meta estratégica</label><BFormTextarea v-model="objectiveForm.strategic_goal" rows="2" /></div>
              <div class="col-md-6"><label class="form-label">Indicador global</label><BFormInput v-model="objectiveForm.global_indicator" /></div>
              <div class="col-md-6"><label class="form-label">Responsable</label><BFormSelect v-model="objectiveForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-md-4"><label class="form-label">Inicio</label><BFormInput v-model="objectiveForm.start_date" type="date" /></div>
              <div class="col-md-4"><label class="form-label">Término</label><BFormInput v-model="objectiveForm.end_date" type="date" /></div>
              <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="objectiveForm.state" :options="objectiveStateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="objectiveForm.observations" rows="2" /></div>
            </div>
            <div class="d-flex gap-2 mt-3"><BButton variant="primary" @click="saveObjective">Guardar</BButton><BButton variant="outline-secondary" @click="startObjectiveCreate">Limpiar</BButton></div>
          </BCard>
        </div>
        <div class="col-xl-7">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Objetivos registrados</div><div class="small text-muted">{{ items.length }} objetivos estratégicos</div></div><PmeHelpButton title="Ayuda: objetivos registrados" text="La tabla muestra objetivos estratégicos, dimensión, responsable, estrategias, indicadores, acciones y estado." /></div></template>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Objetivo</th><th>Dimensión</th><th>Responsable</th><th>Estado</th><th>Estrategias</th><th>Indicadores</th><th>Acciones</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="item in items" :key="item.id">
                    <td><div class="fw-semibold">{{ item.name }}</div><div class="small text-muted">{{ item.strategic_goal || 'Sin meta estratégica' }}</div></td><td>{{ item.dimension?.name }}</td><td>{{ item.responsible_user?.name || '-' }}</td><td><PmeStatusBadge :status="item.state" /></td><td>{{ item.strategies_count }}</td><td>{{ item.indicators_count }}</td><td>{{ item.actions_count }}</td>
                    <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editObjective(item)">Editar</BButton></td>
                  </tr>
                  <tr v-if="!items.length"><td colspan="8" class="text-center text-muted">No hay objetivos registrados.</td></tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>

      <div v-else-if="section === 'estrategias'" class="row g-3">
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Formulario estrategia</div><PmeHelpButton title="Ayuda: estrategias PME" text="Aquí se registran estrategias asociadas a objetivos PME y su estado de ejecución." /></div></template>
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Objetivo</label><BFormSelect v-model="strategyForm.pme_objective_id" :options="objectiveOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-12"><label class="form-label">Nombre estrategia</label><BFormInput v-model="strategyForm.name" /></div>
              <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="strategyForm.description" rows="3" /></div>
              <div class="col-md-6"><label class="form-label">Responsable</label><BFormSelect v-model="strategyForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-md-6"><label class="form-label">Periodo</label><BFormInput v-model="strategyForm.execution_period" /></div>
              <div class="col-md-6"><label class="form-label">Estado</label><BFormSelect v-model="strategyForm.state" :options="strategyStateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="strategyForm.observations" rows="2" /></div>
            </div>
            <div class="d-flex gap-2 mt-3"><BButton variant="primary" @click="saveStrategy">Guardar</BButton><BButton variant="outline-secondary" @click="startStrategyCreate">Limpiar</BButton></div>
          </BCard>
        </div>
        <div class="col-xl-8">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Estrategias registradas</div><div class="small text-muted">{{ items.length }} líneas de trabajo</div></div><PmeHelpButton title="Ayuda: estrategias registradas" text="La tabla muestra estrategias, objetivo asociado, responsable y cantidad de acciones e indicadores vinculados." /></div></template>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Estrategia</th><th>Objetivo</th><th>Responsable</th><th>Estado</th><th>Indicadores</th><th>Acciones</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="item in items" :key="item.id">
                    <td><div class="fw-semibold">{{ item.name }}</div><div class="small text-muted">{{ item.execution_period || 'Sin período' }}</div></td><td>{{ item.objective?.name }}</td><td>{{ item.responsible_user?.name || '-' }}</td><td><PmeStatusBadge :status="item.state" /></td><td>{{ item.indicators_count }}</td><td>{{ item.actions_count }}</td>
                    <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editStrategy(item)">Editar</BButton></td>
                  </tr>
                  <tr v-if="!items.length"><td colspan="7" class="text-center text-muted">No hay estrategias registradas.</td></tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>

      <div v-else-if="section === 'metas'" class="row g-3">
        <div class="col-xl-4">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Medición de meta estratégica</div><PmeHelpButton title="Ayuda: medición de metas" text="Aquí se comparan línea base, resultado esperado y resultado actual de cada meta estratégica." /></div></template>
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Objetivo</label><BFormSelect v-model="selectedObjectiveId" :options="objectiveOptions.map((item) => ({ value: item.value, text: item.label }))" @change="loadMeasurements(selectedObjectiveId); measurementForm.pme_objective_id = selectedObjectiveId" /></div>
              <div class="col-12"><label class="form-label">Meta</label><BFormInput v-model="measurementForm.goal_label" /></div>
              <div class="col-md-4"><label class="form-label">Línea base</label><BFormInput v-model="measurementForm.baseline_value" type="number" /></div>
              <div class="col-md-4"><label class="form-label">Esperado</label><BFormInput v-model="measurementForm.expected_result" type="number" /></div>
              <div class="col-md-4"><label class="form-label">Actual</label><BFormInput v-model="measurementForm.current_result" type="number" /></div>
              <div class="col-md-6"><label class="form-label">Fuente</label><BFormInput v-model="measurementForm.information_source" /></div>
              <div class="col-md-6"><label class="form-label">Fecha medición</label><BFormInput v-model="measurementForm.measured_at" type="date" /></div>
              <div class="col-md-6"><label class="form-label">Responsable</label><BFormSelect v-model="measurementForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-md-6"><label class="form-label">Estado</label><BFormSelect v-model="measurementForm.state" :options="goalStateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
              <div class="col-12"><label class="form-label">Análisis</label><BFormTextarea v-model="measurementForm.analysis" rows="3" /></div>
            </div>
            <div class="d-flex gap-2 mt-3"><BButton variant="primary" @click="saveMeasurement">Guardar</BButton><BButton variant="outline-secondary" @click="startMeasurementCreate">Limpiar</BButton></div>
          </BCard>
        </div>
        <div class="col-xl-8">
          <BCard class="border-0 shadow-sm">
            <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Historial de metas estratégicas</div><div class="small text-muted">{{ measurements.length }} mediciones del objetivo seleccionado</div></div><PmeHelpButton title="Ayuda: historial de metas" text="La tabla muestra mediciones registradas para la meta estratégica del objetivo seleccionado." /></div></template>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Meta</th><th>Fecha</th><th>Esperado</th><th>Actual</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="item in measurements" :key="item.id">
                    <td>{{ item.goal_label }}</td><td>{{ formatPmeDate(item.measured_at) }}</td><td>{{ item.expected_result }}</td><td>{{ item.current_result }}</td><td><PmeStatusBadge :status="item.state" /></td>
                    <td class="text-end"><BButton size="sm" variant="outline-primary" @click="editMeasurement(item)">Editar</BButton></td>
                  </tr>
                  <tr v-if="!measurements.length"><td colspan="6" class="text-center text-muted">Este objetivo aún no tiene mediciones de meta.</td></tr>
                </tbody>
              </table>
            </div>
          </BCard>
        </div>
      </div>
      <div v-if="section !== 'metas'" class="planning-pagination"><PmePagination :pagination="pagination" :loading="loading" @change="loadData" /></div>
    </template>
  </div>
</template>

<style scoped>
.plan-history-card{padding:.85rem;border:1px solid #e1e6ed;border-left:3px solid #a8b3c3;border-radius:9px;background:#fff;transition:border-color .15s ease,box-shadow .15s ease}.plan-history-card:hover{border-color:#b9c6d8;box-shadow:0 6px 16px rgba(25,39,70,.055)}.plan-history-card.is-active{border-left-color:#16866f;background:linear-gradient(90deg,#f2fbf8,#fff 28%)}.sort-order{display:grid;place-items:center;width:28px;height:28px;border-radius:7px;background:#eef3fb;color:#3156a6;font-weight:750}.planning-pagination{padding:.1rem .2rem}
</style>
