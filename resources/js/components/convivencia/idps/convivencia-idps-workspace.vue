<script>
import StatusBadge from "../status-badge.vue";
import ConvivenciaDataTable from "../ui/convivencia-data-table.vue";
import ConvivenciaFormModal from "../ui/convivencia-form-modal.vue";
import ConvivenciaRowActions from "../ui/convivencia-row-actions.vue";
import ConvivenciaSectionToolbar from "../ui/convivencia-section-toolbar.vue";
import ConvivenciaRemoteSelect from "../ui/convivencia-remote-select.vue";
import { formatConvivenciaDate, normalizeOptions } from "../module-utils";

const blankPeriod = (yearId = null) => ({ id: null, academic_year_id: yearId, name: "", starts_on: "", ends_on: "", status: "abierto", notes: "" });
const blankDimension = () => ({ id: null, code: "", name: "", description: "", active: true });
const blankInstrument = () => ({ id: null, dimension_id: null, name: "", description: "", response_type: "escala", scale_label: "", active: true });
const blankResult = (yearId = null) => ({ id: null, period_id: null, dimension_id: null, instrument_id: null, academic_year_id: yearId, course_section_id: null, education_level_id: null, related_plan_id: null, result_scope: "curso", reference_label: "", score: "", percentage: "", sample_size: "", qualitative_observations: "", improvement_actions: "", is_sensitive: false });

export default {
  name: "ConvivenciaIdpsWorkspace",
  components: { StatusBadge, ConvivenciaDataTable, ConvivenciaFormModal, ConvivenciaRowActions, ConvivenciaSectionToolbar, ConvivenciaRemoteSelect },
  props: {
    state: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    periods: { type: Array, default: () => [] },
    dimensions: { type: Array, default: () => [] },
    results: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
  },
  emits: ["refresh", "submit"],
  computed: {
    activeForm() {
      const formByKind = { period: "periodForm", dimension: "dimensionForm", instrument: "instrumentForm", result: "resultForm" };
      return this.state[formByKind[this.state.modal]] || null;
    },
    modalTitle() {
      const label = { period: "período de medición", dimension: "dimensión IDPS", instrument: "instrumento", result: "resultado IDPS" }[this.state.modal] || "registro IDPS";
      return `${this.activeForm?.id ? "Editar" : "Nuevo"} ${label}`;
    },
    academicYearOptions() { return normalizeOptions(this.catalogs.academic_years || [], false); },
    courseOptions() { return normalizeOptions(this.catalogs.courses || [], true); },
    educationLevelOptions() {
      const levels = (this.catalogs.courses || []).map((course) => course.education_level || course.educationLevel).filter(Boolean);
      return normalizeOptions(Array.from(new Map(levels.map((level) => [level.id, level])).values()), true);
    },
    periodOptions() { return this.periods.map((item) => ({ value: item.id, text: item.name })); },
    dimensionOptions() { return this.dimensions.map((item) => ({ value: item.id, text: `${item.code ? `${item.code} · ` : ""}${item.name}` })); },
    resultDimensionOptions() {
      const currentId = this.state.resultForm.id ? Number(this.state.resultForm.dimension_id || 0) : 0;
      return this.dimensions
        .filter((item) => item.active !== false || Number(item.id) === currentId)
        .map((item) => ({
          value: item.id,
          text: `${item.code ? `${item.code} · ` : ""}${item.name}${item.active === false ? " (Inactiva · valor histórico)" : ""}`,
        }));
    },
    allInstruments() { return this.dimensions.flatMap((item) => item.instruments || []); },
    instrumentOptions() {
      const dimensionId = Number(this.state.resultForm.dimension_id || 0);
      const currentId = this.state.resultForm.id ? Number(this.state.resultForm.instrument_id || 0) : 0;
      return this.dimensions
        .filter((dimension) => !dimensionId || Number(dimension.id) === dimensionId)
        .flatMap((item) => item.instruments || [])
        .filter((item) => item.active !== false || Number(item.id) === currentId)
        .map((item) => ({ value: item.id, text: `${item.name}${item.active === false ? " (Inactivo · valor histórico)" : ""}` }));
    },
    scopeOptions() { return normalizeOptions(this.catalogs.idps_scope_options || []); },
  },
  methods: {
    formatDate: formatConvivenciaDate,
    open(kind, item = null) {
      if (!this.canManage) return;
      if (kind === "period") this.state.periodForm = item ? { ...blankPeriod(), ...JSON.parse(JSON.stringify(item)), academic_year_id: item.academic_year_id ?? item.academic_year?.id ?? null } : blankPeriod(this.catalogs.active_academic_year_id);
      if (kind === "dimension") this.state.dimensionForm = item ? { ...blankDimension(), ...JSON.parse(JSON.stringify(item)) } : blankDimension();
      if (kind === "instrument") this.state.instrumentForm = item ? { ...blankInstrument(), ...JSON.parse(JSON.stringify(item)), dimension_id: item.dimension_id ?? item.dimension?.id ?? null } : blankInstrument();
      if (kind === "result") this.state.resultForm = item ? { ...blankResult(), ...JSON.parse(JSON.stringify(item)), period_id: item.period_id ?? item.period?.id ?? null, dimension_id: item.dimension_id ?? item.dimension?.id ?? null, instrument_id: item.instrument_id ?? item.instrument?.id ?? null, academic_year_id: item.academic_year_id ?? item.academic_year?.id ?? item.academicYear?.id ?? null, course_section_id: item.course_section_id ?? item.course_section?.id ?? null, education_level_id: item.education_level_id ?? item.education_level?.id ?? item.educationLevel?.id ?? null, related_plan_id: item.related_plan_id ?? item.related_plan?.id ?? null } : blankResult(this.catalogs.active_academic_year_id);
      this.state.modal = kind;
    },
    close() { if (!this.state.saving) this.state.modal = null; },
    submit() { if (this.state.modal && this.activeForm) this.$emit("submit", this.state.modal); },
    onResultDimensionChanged() { this.state.resultForm.instrument_id = null; },
    editInstrument(item) { this.open("instrument", item); },
    resultLabel(item) { return item.reference_label || item.course_section?.display_name || `resultado ${item.id}`; },
  },
};
</script>

<template>
  <section class="convivencia-idps">
    <ConvivenciaSectionToolbar title="Gestión de indicadores IDPS" description="Períodos, dimensiones, instrumentos y resultados comparables por curso." icon="bx-bar-chart-square" :can-create="canManage" primary-label="Registrar resultado" :refreshing="state.loading" @create="open('result')" @refresh="$emit('refresh')">
      <template v-if="canManage" #filters>
        <div class="convivencia-idps__quick-actions">
          <button type="button" class="btn btn-outline-primary" @click="open('period')"><i class="bx bx-calendar-plus"></i>Período</button>
          <button type="button" class="btn btn-outline-primary" @click="open('dimension')"><i class="bx bx-category-alt"></i>Dimensión</button>
          <button type="button" class="btn btn-outline-primary" @click="open('instrument')"><i class="bx bx-list-check"></i>Instrumento</button>
        </div>
      </template>
    </ConvivenciaSectionToolbar>

    <div class="convivencia-idps__metrics">
      <article><span><i class="bx bx-calendar"></i></span><div><b>{{ periods.length }}</b><small>períodos</small></div></article>
      <article><span><i class="bx bx-category"></i></span><div><b>{{ dimensions.length }}</b><small>dimensiones</small></div></article>
      <article><span><i class="bx bx-list-check"></i></span><div><b>{{ allInstruments.length }}</b><small>instrumentos</small></div></article>
      <article><span><i class="bx bx-line-chart"></i></span><div><b>{{ state.overview?.results?.total ?? results.length }}</b><small>resultados</small></div></article>
    </div>

    <div class="row g-3">
      <div class="col-xl-5">
        <ConvivenciaDataTable title="Períodos de medición" subtitle="Ventanas de aplicación por año académico." icon="bx-calendar" :count="periods.length" :loading="state.loading" :empty="periods.length === 0" min-width="520px">
          <table class="table"><thead><tr><th>Período</th><th>Vigencia</th><th>Estado</th><th v-if="canManage" class="text-end">Acciones</th></tr></thead><tbody><tr v-for="item in periods" :key="item.id"><td data-label="Período"><b>{{ item.name }}</b><small>{{ item.academic_year?.name || item.academicYear?.name || "-" }}</small></td><td data-label="Vigencia">{{ formatDate(item.starts_on) }} — {{ formatDate(item.ends_on) }}</td><td data-label="Estado"><StatusBadge :status="item.status" /></td><td v-if="canManage" data-label="Acciones"><ConvivenciaRowActions :actions="[{ key: 'edit', label: 'Editar', icon: 'bx-edit-alt' }]" :item-label="item.name" @select="open('period', item)" /></td></tr></tbody></table>
        </ConvivenciaDataTable>
      </div>
      <div class="col-xl-7">
        <ConvivenciaDataTable title="Dimensiones e instrumentos" subtitle="Catálogo utilizado en los resultados institucionales." icon="bx-category" :count="dimensions.length" :loading="state.loading" :empty="dimensions.length === 0" min-width="620px">
          <table class="table"><thead><tr><th>Dimensión</th><th>Instrumentos</th><th>Estado</th><th v-if="canManage" class="text-end">Acciones</th></tr></thead><tbody><template v-for="dimension in dimensions" :key="dimension.id"><tr><td data-label="Dimensión"><b>{{ dimension.code }} · {{ dimension.name }}</b><small>{{ dimension.description || "Sin descripción" }}</small></td><td data-label="Instrumentos"><span v-if="dimension.instruments?.length" class="convivencia-idps__chips"><button v-for="instrument in dimension.instruments" :key="instrument.id" type="button" :class="{ 'is-inactive': instrument.active === false }" :disabled="!canManage" @click="editInstrument(instrument)">{{ instrument.name }}<em v-if="instrument.active === false">Inactivo</em></button></span><span v-else>Sin instrumentos</span></td><td data-label="Estado"><StatusBadge :status="dimension.active ? 'activo' : 'inactivo'" /></td><td v-if="canManage" data-label="Acciones"><ConvivenciaRowActions :actions="[{ key: 'edit', label: 'Editar', icon: 'bx-edit-alt' }]" :item-label="dimension.name" @select="open('dimension', dimension)" /></td></tr></template></tbody></table>
        </ConvivenciaDataTable>
      </div>
      <div class="col-12">
        <ConvivenciaDataTable title="Resultados registrados" subtitle="Lecturas cuantitativas y acciones de mejora vinculadas." icon="bx-line-chart" :count="state.overview?.results?.total ?? results.length" :loading="state.loading" :empty="results.length === 0" min-width="840px">
          <table class="table"><thead><tr><th>Período</th><th>Dimensión</th><th>Curso / referencia</th><th>Puntaje</th><th>Porcentaje</th><th>Plan</th><th v-if="canManage" class="text-end">Acciones</th></tr></thead><tbody><tr v-for="item in results" :key="item.id"><td data-label="Período">{{ item.period?.name || "-" }}</td><td data-label="Dimensión">{{ item.dimension?.name || "-" }}<small>{{ item.instrument?.name || "" }}</small></td><td data-label="Curso / referencia">{{ item.reference_label || item.course_section?.display_name || "-" }}</td><td data-label="Puntaje">{{ item.score ?? "-" }}</td><td data-label="Porcentaje"><span class="convivencia-idps__score">{{ item.percentage ?? "-" }}<small v-if="item.percentage !== null && item.percentage !== undefined">%</small></span></td><td data-label="Plan">{{ item.related_plan?.name || "-" }}</td><td v-if="canManage" data-label="Acciones"><ConvivenciaRowActions :actions="[{ key: 'edit', label: 'Editar', icon: 'bx-edit-alt' }]" :item-label="resultLabel(item)" @select="open('result', item)" /></td></tr></tbody></table>
          <template v-if="state.overview?.results?.last_page > 1" #footer><div class="convivencia-idps__pager"><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="state.loading || state.overview.results.current_page <= 1" @click="$emit('refresh', state.overview.results.current_page - 1)"><i class="bx bx-chevron-left"></i>Anterior</button><span>Página {{ state.overview.results.current_page }} de {{ state.overview.results.last_page }}</span><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="state.loading || state.overview.results.current_page >= state.overview.results.last_page" @click="$emit('refresh', state.overview.results.current_page + 1)">Siguiente<i class="bx bx-chevron-right"></i></button></div></template>
        </ConvivenciaDataTable>
      </div>
    </div>

    <ConvivenciaFormModal :model-value="Boolean(state.modal)" :title="modalTitle" eyebrow="Configuración IDPS" description="Completa los datos y guarda para actualizar los indicadores institucionales." icon="bx-bar-chart-square" size="lg" :busy="state.saving" :submit-label="activeForm?.id ? 'Guardar cambios' : 'Crear registro'" @update:model-value="(open) => { if (!open) close() }" @submit="submit">
      <div v-if="state.modal === 'period'" class="row g-3">
        <div class="col-12"><label class="form-label">Año académico</label><BFormSelect v-model="state.periodForm.academic_year_id" :options="academicYearOptions" /></div><div class="col-12"><label class="form-label">Nombre</label><BFormInput v-model="state.periodForm.name" required /></div><div class="col-md-6"><label class="form-label">Inicio</label><BFormInput v-model="state.periodForm.starts_on" type="date" /></div><div class="col-md-6"><label class="form-label">Término</label><BFormInput v-model="state.periodForm.ends_on" type="date" /></div><div class="col-12"><label class="form-label">Estado</label><BFormInput v-model="state.periodForm.status" required /></div><div class="col-12"><label class="form-label">Notas</label><BFormTextarea v-model="state.periodForm.notes" rows="3" /></div>
      </div>
      <div v-else-if="state.modal === 'dimension'" class="row g-3">
        <div class="col-md-4"><label class="form-label">Código</label><BFormInput v-model="state.dimensionForm.code" required /></div><div class="col-md-8"><label class="form-label">Nombre</label><BFormInput v-model="state.dimensionForm.name" required /></div><div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="state.dimensionForm.description" rows="3" /></div><div class="col-12"><BFormCheckbox v-model="state.dimensionForm.active">Dimensión activa</BFormCheckbox></div>
      </div>
      <div v-else-if="state.modal === 'instrument'" class="row g-3">
        <div class="col-12"><label class="form-label">Dimensión</label><BFormSelect v-model="state.instrumentForm.dimension_id" :options="dimensionOptions" /></div><div class="col-12"><label class="form-label">Nombre</label><BFormInput v-model="state.instrumentForm.name" required /></div><div class="col-md-6"><label class="form-label">Tipo de respuesta</label><BFormSelect v-model="state.instrumentForm.response_type" :options="[{ value: 'escala', text: 'Escala' }, { value: 'abierta', text: 'Abierta' }, { value: 'si_no', text: 'Sí / No' }]" /></div><div class="col-md-6"><label class="form-label">Etiqueta de escala</label><BFormInput v-model="state.instrumentForm.scale_label" /></div><div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="state.instrumentForm.description" rows="3" /></div><div class="col-12"><BFormCheckbox v-model="state.instrumentForm.active">Instrumento activo</BFormCheckbox></div>
      </div>
      <div v-else-if="state.modal === 'result'" class="row g-3">
        <div class="col-md-3"><label class="form-label">Período</label><BFormSelect v-model="state.resultForm.period_id" :options="periodOptions" required /></div>
        <div class="col-md-3"><label class="form-label">Año académico</label><BFormSelect v-model="state.resultForm.academic_year_id" :options="academicYearOptions" /></div>
        <div class="col-md-3"><label class="form-label">Dimensión</label><BFormSelect v-model="state.resultForm.dimension_id" :options="resultDimensionOptions" required @change="onResultDimensionChanged" /></div>
        <div class="col-md-3"><label class="form-label">Instrumento</label><BFormSelect v-model="state.resultForm.instrument_id" :options="instrumentOptions" /></div>
        <div class="col-md-4"><label class="form-label">Alcance</label><BFormSelect v-model="state.resultForm.result_scope" :options="scopeOptions" /></div>
        <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="state.resultForm.course_section_id" :options="courseOptions" /></div>
        <div class="col-md-4"><label class="form-label">Nivel educativo</label><BFormSelect v-model="state.resultForm.education_level_id" :options="educationLevelOptions" /></div>
        <div class="col-md-6"><label class="form-label">Plan vinculado</label><ConvivenciaRemoteSelect v-model="state.resultForm.related_plan_id" type="plans" placeholder="Buscar plan por nombre" aria-label="Buscar plan para vincular al resultado IDPS" /></div>
        <div class="col-md-6"><label class="form-label">Referencia</label><BFormInput v-model="state.resultForm.reference_label" /></div>
        <div class="col-md-4"><label class="form-label">Puntaje</label><BFormInput v-model="state.resultForm.score" type="number" step="0.01" /></div>
        <div class="col-md-4"><label class="form-label">Porcentaje</label><BFormInput v-model="state.resultForm.percentage" type="number" min="0" max="100" step="0.01" /></div>
        <div class="col-md-4"><label class="form-label">Tamaño de muestra</label><BFormInput v-model="state.resultForm.sample_size" type="number" min="0" /></div>
        <div class="col-md-6"><label class="form-label">Observaciones cualitativas</label><BFormTextarea v-model="state.resultForm.qualitative_observations" rows="3" /></div>
        <div class="col-md-6"><label class="form-label">Acciones de mejora</label><BFormTextarea v-model="state.resultForm.improvement_actions" rows="3" /></div>
        <div class="col-12"><BFormCheckbox v-model="state.resultForm.is_sensitive">Resultado con información sensible</BFormCheckbox></div>
      </div>
    </ConvivenciaFormModal>
  </section>
</template>

<style scoped>
.convivencia-idps{display:flex;flex-direction:column;gap:.9rem}.convivencia-idps__quick-actions{display:flex;flex-wrap:wrap;gap:.35rem}.convivencia-idps__quick-actions .btn{display:inline-flex;align-items:center;gap:.25rem;font-size:.64rem}.convivencia-idps__metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}.convivencia-idps__metrics article{display:flex;align-items:center;gap:.6rem;padding:.72rem;border:1px solid #e0e5ef;border-radius:14px;background:#fff}.convivencia-idps__metrics article>span{display:grid;width:36px;height:36px;color:#5367dc;place-items:center;border-radius:11px;background:#eef1ff}.convivencia-idps__metrics b{display:block;color:#2b396e;font-size:.92rem}.convivencia-idps__metrics small{display:block;color:#8490a2;font-size:.61rem}.convivencia-idps td b{display:block;color:#334477;font-size:.68rem}.convivencia-idps td small{display:block;color:#8a94a5;font-size:.58rem}.convivencia-idps__chips{display:flex;flex-wrap:wrap;gap:.25rem}.convivencia-idps__chips button{padding:.18rem .38rem;color:#5264bd;font-size:.57rem;border:1px solid #d9def7;border-radius:999px;background:#f5f6ff}.convivencia-idps__chips button:not(:disabled):hover{background:#e9ecff}.convivencia-idps__score{color:#2d8c74;font-size:.8rem;font-weight:800}.convivencia-idps__score small{display:inline}.convivencia-idps :deep(.form-label){color:#56637a;font-size:.65rem;font-weight:750}.convivencia-idps__pager{display:flex;align-items:center;justify-content:space-between;gap:.6rem;color:#778296;font-size:.64rem}.convivencia-idps__pager .btn{display:inline-flex;align-items:center;gap:.2rem;font-size:.62rem}
.convivencia-idps__chips button{display:inline-flex;align-items:center;gap:.25rem}.convivencia-idps__chips button.is-inactive{color:#8d5862;border-color:#eccfd4;background:#fff2f3}.convivencia-idps__chips em{padding:.08rem .24rem;color:inherit;font-size:.46rem;font-style:normal;font-weight:800;border-radius:999px;background:rgba(255,255,255,.75)}
@media(max-width:767.98px){.convivencia-idps__metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.convivencia-idps__quick-actions{display:grid;grid-template-columns:1fr}.convivencia-idps__quick-actions .btn{justify-content:center}}@media(max-width:420px){.convivencia-idps__metrics{grid-template-columns:1fr}}
</style>
