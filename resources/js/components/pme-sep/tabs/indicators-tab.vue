<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  formatPmeDate,
  formatPmeError,
  normalizeOptions,
  showPmeError,
  showPmeSuccess,
} from "../module-utils";

const indicatorForm = () => ({
  id: null,
  pme_objective_id: null,
  pme_strategy_id: null,
  name: "",
  description: "",
  indicator_type: "gestion",
  baseline_value: null,
  target_value: null,
  current_value: null,
  measurement_unit: "%",
  verification_source: "",
  measurement_frequency: "mensual",
  responsible_user_id: null,
  state: "sin_medicion",
  observations: "",
});

const measurementForm = () => ({
  measured_at: new Date().toISOString().slice(0, 10),
  measured_value: null,
  information_source: "",
  analysis: "",
  observations: "",
  responsible_user_id: null,
  state: "en_avance",
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
      measurements: [],
      selectedIndicator: null,
      indicatorForm: indicatorForm(),
      measurementForm: measurementForm(),
      editing: false,
    };
  },
  computed: {
    objectiveOptions() {
      return normalizeOptions(this.catalogs.objectives, true);
    },
    strategyOptions() {
      return normalizeOptions(this.catalogs.strategies, true);
    },
    responsibleOptions() {
      return normalizeOptions(this.catalogs.responsibles, true);
    },
    typeOptions() {
      return normalizeOptions(this.catalogs.options?.indicator_types || [], false);
    },
    frequencyOptions() {
      return normalizeOptions(this.catalogs.options?.indicator_frequencies || [], false);
    },
    stateOptions() {
      return normalizeOptions(this.catalogs.options?.indicator_states || [], false);
    },
  },
  mounted() {
    this.resetIndicatorForm();
    this.loadIndicators();
  },
  methods: {
    formatPmeDate,
    resetIndicatorForm() {
      this.editing = false;
      this.indicatorForm = indicatorForm();
    },
    async loadIndicators() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/pme-sep/indicators");
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatPmeError(error, "No se pudieron cargar los indicadores PME.");
      } finally {
        this.loading = false;
      }
    },
    async loadMeasurements(indicator) {
      this.selectedIndicator = indicator;
      this.measurementForm = measurementForm();
      this.measurementForm.responsible_user_id = indicator.responsible_user_id;
      const response = await axios.get(`/api/pme-sep/indicators/${indicator.id}/measurements`);
      this.measurements = response.data.data || [];
    },
    editIndicator(item) {
      this.editing = true;
      this.indicatorForm = {
        id: item.id,
        pme_objective_id: item.pme_objective_id,
        pme_strategy_id: item.pme_strategy_id,
        name: item.name,
        description: item.description || "",
        indicator_type: item.indicator_type,
        baseline_value: item.baseline_value,
        target_value: item.target_value,
        current_value: item.current_value,
        measurement_unit: item.measurement_unit || "%",
        verification_source: item.verification_source || "",
        measurement_frequency: item.measurement_frequency,
        responsible_user_id: item.responsible_user_id,
        state: item.state,
        observations: item.observations || "",
      };
    },
    async saveIndicator() {
      this.saving = true;
      try {
        if (this.editing && this.indicatorForm.id) {
          await axios.put(`/api/pme-sep/indicators/${this.indicatorForm.id}`, this.indicatorForm);
        } else {
          await axios.post("/api/pme-sep/indicators", this.indicatorForm);
        }
        showPmeSuccess("Indicador guardado correctamente.");
        this.resetIndicatorForm();
        this.loadIndicators();
        this.$emit("refresh-catalogs");
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo guardar el indicador."));
      } finally {
        this.saving = false;
      }
    },
    async saveMeasurement() {
      if (!this.selectedIndicator) {
        showPmeError("Debes seleccionar un indicador para registrar su medición.");
        return;
      }

      this.saving = true;
      try {
        await axios.post(`/api/pme-sep/indicators/${this.selectedIndicator.id}/measurements`, this.measurementForm);
        showPmeSuccess("Medición registrada correctamente.");
        this.measurementForm = measurementForm();
        this.loadMeasurements(this.selectedIndicator);
        this.loadIndicators();
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudo registrar la medición del indicador."));
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<template>
  <div class="row g-3">
    <div class="col-xl-5">
      <BCard class="border-0 shadow-sm">
        <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Formulario indicador</div><PmeHelpButton title="Ayuda: indicadores PME" text="Aquí se registran indicadores con línea base, meta esperada, valor actual, frecuencia de medición, responsable y estado." /></div></template>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Objetivo</label><BFormSelect v-model="indicatorForm.pme_objective_id" :options="objectiveOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Estrategia</label><BFormSelect v-model="indicatorForm.pme_strategy_id" :options="strategyOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-12"><label class="form-label">Nombre indicador</label><BFormInput v-model="indicatorForm.name" /></div>
          <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="indicatorForm.description" rows="3" /></div>
          <div class="col-md-4"><label class="form-label">Tipo</label><BFormSelect v-model="indicatorForm.indicator_type" :options="typeOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-4"><label class="form-label">Frecuencia</label><BFormSelect v-model="indicatorForm.measurement_frequency" :options="frequencyOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="indicatorForm.state" :options="stateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-4"><label class="form-label">Línea base</label><BFormInput v-model="indicatorForm.baseline_value" type="number" /></div>
          <div class="col-md-4"><label class="form-label">Meta</label><BFormInput v-model="indicatorForm.target_value" type="number" /></div>
          <div class="col-md-4"><label class="form-label">Valor actual</label><BFormInput v-model="indicatorForm.current_value" type="number" /></div>
          <div class="col-md-6"><label class="form-label">Unidad</label><BFormInput v-model="indicatorForm.measurement_unit" /></div>
          <div class="col-md-6"><label class="form-label">Responsable</label><BFormSelect v-model="indicatorForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-12"><label class="form-label">Fuente verificación</label><BFormInput v-model="indicatorForm.verification_source" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="indicatorForm.observations" rows="2" /></div>
        </div>
        <div class="d-flex gap-2 mt-3"><BButton variant="primary" @click="saveIndicator">Guardar</BButton><BButton variant="outline-secondary" @click="resetIndicatorForm">Limpiar</BButton></div>
      </BCard>
    </div>
    <div class="col-xl-7">
      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
      <BCard class="border-0 shadow-sm mb-3">
        <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Indicadores registrados</div><PmeHelpButton title="Ayuda: bandeja de indicadores" text="La tabla permite revisar indicadores, objetivo, estrategia, cumplimiento, estado y número de mediciones registradas." /></div></template>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Indicador</th><th>Objetivo</th><th>Meta</th><th>Actual</th><th>Cumplimiento</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              <tr v-if="loading"><td colspan="7" class="text-center text-muted">Cargando indicadores...</td></tr>
              <tr v-else-if="!items.length"><td colspan="7" class="text-center text-muted">No hay indicadores registrados.</td></tr>
              <tr v-for="item in items" :key="item.id">
                <td>{{ item.name }}</td><td>{{ item.objective?.name }}</td><td>{{ item.target_value }}</td><td>{{ item.current_value }}</td><td>{{ item.compliance_percentage }}%</td><td><PmeStatusBadge :status="item.state" /></td>
                <td class="text-end"><BButton size="sm" variant="outline-info" @click="loadMeasurements(item)">Mediciones</BButton> <BButton size="sm" variant="outline-primary" @click="editIndicator(item)">Editar</BButton></td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCard>

      <BCard v-if="selectedIndicator" class="border-0 shadow-sm">
        <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Mediciones de {{ selectedIndicator.name }}</div><PmeHelpButton title="Ayuda: mediciones de indicadores" text="Aquí se registran mediciones históricas del indicador para monitorear su cumplimiento y evolución." /></div></template>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Fecha</label><BFormInput v-model="measurementForm.measured_at" type="date" /></div>
          <div class="col-md-3"><label class="form-label">Valor medido</label><BFormInput v-model="measurementForm.measured_value" type="number" /></div>
          <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="measurementForm.state" :options="stateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-3"><label class="form-label">Responsable</label><BFormSelect v-model="measurementForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Fuente información</label><BFormInput v-model="measurementForm.information_source" /></div>
          <div class="col-md-6"><label class="form-label">Análisis</label><BFormTextarea v-model="measurementForm.analysis" rows="2" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="measurementForm.observations" rows="2" /></div>
        </div>
        <div class="d-flex gap-2 mt-3"><BButton variant="primary" @click="saveMeasurement">Registrar medición</BButton></div>
        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle">
            <thead><tr><th>Fecha</th><th>Valor</th><th>Cumplimiento</th><th>Estado</th></tr></thead>
            <tbody>
              <tr v-for="item in measurements" :key="item.id"><td>{{ formatPmeDate(item.measured_at) }}</td><td>{{ item.measured_value }}</td><td>{{ item.compliance_percentage }}%</td><td><PmeStatusBadge :status="item.state" /></td></tr>
            </tbody>
          </table>
        </div>
      </BCard>
    </div>
  </div>
</template>
