<script>
import axios from "axios";
import PmeHelpButton from "../help-button.vue";
import PmePagination from "../pagination.vue";
import PmeStatusBadge from "../status-badge.vue";
import {
  formatPmeDate,
  formatPmeError,
  normalizeOptions,
  normalizePagination,
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
      measurements: [],
      selectedIndicator: null,
      indicatorForm: indicatorForm(),
      measurementForm: measurementForm(),
      editing: false,
      search: "",
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
    filteredItems() {
      const term = this.search.trim().toLocaleLowerCase("es");
      if (!term) return this.items;
      return this.items.filter((item) => [item.name, item.objective?.name, item.strategy?.name, item.responsible_user?.name]
        .filter(Boolean)
        .some((value) => String(value).toLocaleLowerCase("es").includes(term)));
    },
    indicatorSummary() {
      const values = this.items.map((item) => Number(item.compliance_percentage || 0));
      return {
        total: this.pagination.total || this.items.length,
        average: values.length ? Math.round(values.reduce((sum, value) => sum + value, 0) / values.length) : 0,
        critical: this.items.filter((item) => ["critico", "critica"].includes(item.state)).length,
        measured: this.items.filter((item) => Number(item.measurements_count || 0) > 0 || item.current_value !== null).length,
      };
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
    async loadIndicators(page = 1) {
      const requestedPage = Number.isInteger(page) ? page : 1;
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/pme-sep/indicators", { params: { page: requestedPage } });
        this.items = response.data.data || [];
        this.pagination = normalizePagination(response.data);
      } catch (error) {
        this.error = formatPmeError(error, "No se pudieron cargar los indicadores PME.");
      } finally {
        this.loading = false;
      }
    },
    async loadMeasurements(indicator) {
      try {
        this.selectedIndicator = indicator;
        this.measurementForm = measurementForm();
        this.measurementForm.responsible_user_id = indicator.responsible_user_id;
        const response = await axios.get(`/api/pme-sep/indicators/${indicator.id}/measurements`);
        this.measurements = response.data.data || [];
      } catch (error) {
        showPmeError(formatPmeError(error, "No se pudieron cargar las mediciones del indicador."));
      }
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
        <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">{{ editing ? 'Editar indicador' : 'Nuevo indicador' }}</div><div class="small text-muted">Definición, meta y responsable</div></div><PmeHelpButton title="Ayuda: indicadores PME" text="Aquí se registran indicadores con línea base, meta esperada, valor actual, frecuencia de medición, responsable y estado." /></div></template>
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
        <div class="d-flex gap-2 mt-3"><BButton variant="primary" :disabled="saving" @click="saveIndicator"><span v-if="saving" class="spinner-border spinner-border-sm"></span>{{ editing ? 'Actualizar indicador' : 'Guardar indicador' }}</BButton><BButton variant="outline-secondary" :disabled="saving" @click="resetIndicatorForm">Cancelar</BButton></div>
      </BCard>
    </div>
    <div class="col-xl-7">
      <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
      <div class="indicator-summary mb-3">
        <div><i class="bx bx-line-chart"></i><span>Total</span><strong>{{ indicatorSummary.total }}</strong></div>
        <div><i class="bx bx-trending-up"></i><span>Cumplimiento promedio</span><strong>{{ indicatorSummary.average }}%</strong></div>
        <div :class="{ 'is-danger': indicatorSummary.critical }"><i class="bx bx-pulse"></i><span>Críticos</span><strong>{{ indicatorSummary.critical }}</strong></div>
        <div><i class="bx bx-check-circle"></i><span>Con medición</span><strong>{{ indicatorSummary.measured }}</strong></div>
      </div>
      <BCard class="border-0 shadow-sm mb-3">
        <template #header><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><div class="fw-semibold">Indicadores registrados</div><div class="small text-muted">{{ filteredItems.length }} de {{ items.length }} indicadores</div></div><div class="d-flex align-items-center gap-2"><div class="indicator-search"><i class="bx bx-search"></i><BFormInput v-model="search" placeholder="Buscar indicador..." /></div><PmeHelpButton title="Ayuda: bandeja de indicadores" text="La tabla permite revisar indicadores, objetivo, estrategia, cumplimiento, estado y número de mediciones registradas." /></div></div></template>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Indicador</th><th>Objetivo</th><th>Meta</th><th>Actual</th><th>Cumplimiento</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              <tr v-if="loading"><td colspan="7" class="text-center text-muted">Cargando indicadores...</td></tr>
              <tr v-else-if="!filteredItems.length"><td colspan="7" class="text-center text-muted">{{ search ? 'No hay coincidencias para la búsqueda.' : 'No hay indicadores registrados.' }}</td></tr>
              <tr v-for="item in filteredItems" :key="item.id" :class="{ 'table-active': selectedIndicator?.id === item.id }">
                <td><div class="fw-semibold">{{ item.name }}</div><div class="small text-muted">{{ item.measurement_frequency }}</div></td><td>{{ item.objective?.name || '-' }}</td><td>{{ item.target_value }} {{ item.measurement_unit }}</td><td>{{ item.current_value ?? '-' }} {{ item.current_value !== null ? item.measurement_unit : '' }}</td><td><div class="compliance-cell"><div><span>{{ item.compliance_percentage || 0 }}%</span></div><div class="pme-progress"><div class="pme-progress__bar" :style="{ width: `${Math.min(100, Number(item.compliance_percentage || 0))}%` }"></div></div></div></td><td><PmeStatusBadge :status="item.state" /></td>
                <td class="text-end"><BButton size="sm" variant="outline-info" @click="loadMeasurements(item)">Mediciones</BButton> <BButton size="sm" variant="outline-primary" @click="editIndicator(item)">Editar</BButton></td>
              </tr>
            </tbody>
          </table>
        </div>
        <PmePagination :pagination="pagination" :loading="loading" @change="loadIndicators" />
      </BCard>

      <BCard v-if="selectedIndicator" class="border-0 shadow-sm">
        <template #header><div class="d-flex justify-content-between align-items-center"><div><div class="fw-semibold">Mediciones de {{ selectedIndicator.name }}</div><div class="small text-muted">{{ measurements.length }} registros históricos</div></div><PmeHelpButton title="Ayuda: mediciones de indicadores" text="Aquí se registran mediciones históricas del indicador para monitorear su cumplimiento y evolución." /></div></template>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Fecha</label><BFormInput v-model="measurementForm.measured_at" type="date" /></div>
          <div class="col-md-3"><label class="form-label">Valor medido</label><BFormInput v-model="measurementForm.measured_value" type="number" /></div>
          <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="measurementForm.state" :options="stateOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-3"><label class="form-label">Responsable</label><BFormSelect v-model="measurementForm.responsible_user_id" :options="responsibleOptions.map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-md-6"><label class="form-label">Fuente información</label><BFormInput v-model="measurementForm.information_source" /></div>
          <div class="col-md-6"><label class="form-label">Análisis</label><BFormTextarea v-model="measurementForm.analysis" rows="2" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="measurementForm.observations" rows="2" /></div>
        </div>
        <div class="d-flex gap-2 mt-3"><BButton variant="primary" :disabled="saving" @click="saveMeasurement">Registrar medición</BButton></div>
        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle">
            <thead><tr><th>Fecha</th><th>Valor</th><th>Cumplimiento</th><th>Estado</th></tr></thead>
            <tbody>
              <tr v-for="item in measurements" :key="item.id"><td>{{ formatPmeDate(item.measured_at) }}</td><td>{{ item.measured_value }}</td><td>{{ item.compliance_percentage }}%</td><td><PmeStatusBadge :status="item.state" /></td></tr>
              <tr v-if="!measurements.length"><td colspan="4" class="text-center text-muted">Este indicador aún no tiene mediciones históricas.</td></tr>
            </tbody>
          </table>
        </div>
      </BCard>
    </div>
  </div>
</template>

<style scoped>
.indicator-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden;border:1px solid var(--pme-border);border-radius:11px;background:#fff}.indicator-summary>div{display:grid;grid-template-columns:auto 1fr;align-items:center;gap:.12rem .45rem;padding:.72rem;border-right:1px solid #e7ebf1}.indicator-summary>div:last-child{border:0}.indicator-summary i{grid-row:1/3;color:#5474b3;font-size:1.05rem}.indicator-summary span{color:#7b8798;font-size:.58rem}.indicator-summary strong{color:#273449;font-size:.86rem}.indicator-summary .is-danger i,.indicator-summary .is-danger strong{color:#c24654}.indicator-search{position:relative;min-width:210px}.indicator-search i{position:absolute;z-index:2;top:50%;left:.65rem;transform:translateY(-50%);color:#8a95a5}.indicator-search :deep(.form-control){padding-left:2rem}.compliance-cell{min-width:90px}.compliance-cell>div:first-child{display:flex;justify-content:flex-end;margin-bottom:.25rem;color:#536176;font-size:.62rem;font-weight:700}.compliance-cell .pme-progress{height:5px}@media(max-width:767px){.indicator-summary{grid-template-columns:1fr 1fr}.indicator-summary>div:nth-child(2){border-right:0}.indicator-summary>div:nth-child(-n+2){border-bottom:1px solid #e7ebf1}.indicator-search{min-width:0;width:100%}}
</style>
