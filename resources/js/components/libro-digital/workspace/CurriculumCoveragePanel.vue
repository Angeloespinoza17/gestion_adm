<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import { errorMessage, payloadData } from "../module-utils";

const props = defineProps({
  bookId: { type: [Number, String], required: true },
  context: { type: Object, required: true },
});

const loading = ref(false);
const error = ref(null);
const coverage = ref(null);
let controller = null;

const objectives = computed(() => coverage.value?.objectives || []);
const percentage = computed(() => coverage.value?.coverage_percentage);
const percentageLabel = computed(() => percentage.value === null || percentage.value === undefined
  ? "No certificable"
  : `${Number(percentage.value).toLocaleString("es-CL", { maximumFractionDigits: 2 })}%`);

const load = async () => {
  controller?.abort();
  controller = new AbortController();
  loading.value = true;
  error.value = null;
  try {
    coverage.value = payloadData(await libroDigitalApi.curriculumCoverage(props.bookId, {
      school_id: props.context.school_id,
      academic_year_id: props.context.academic_year_id,
    }, controller.signal));
  } catch (requestError) {
    if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
  } finally {
    loading.value = false;
  }
};

watch([() => props.bookId, () => props.context.school_id, () => props.context.academic_year_id], load, { immediate: true });
onBeforeUnmount(() => controller?.abort());
</script>

<template>
  <section class="lcd-coverage" aria-labelledby="lcd-coverage-title" :aria-busy="loading">
    <header><div><span>TRAZABILIDAD CURRICULAR</span><h3 id="lcd-coverage-title">Cobertura curricular</h3><p>Objetivos oficiales asociados a sesiones del libro; una clase no completa un OA automáticamente.</p></div><BButton type="button" size="sm" variant="outline-secondary" :disabled="loading" @click="load"><i class="bx bx-reset"></i> Actualizar</BButton></header>
    <LibroDigitalStatePanel v-if="loading && !coverage" state="loading" compact title="Calculando cobertura" message="Consultando objetivos versionados y sesiones registradas." />
    <LibroDigitalStatePanel v-else-if="error && !coverage" state="error" compact title="No se pudo calcular la cobertura" :message="errorMessage(error)" @retry="load" />
    <template v-else-if="coverage">
      <BAlert v-if="coverage.compliance_blocker" show variant="warning" class="small mb-0"><strong>Cobertura no certificable.</strong> {{ coverage.compliance_blocker.message }}</BAlert>
      <BAlert v-else-if="error" show variant="warning" class="small mb-0">No fue posible actualizar; se conserva la última lectura.</BAlert>
      <div class="lcd-coverage__summary"><div><span>Objetivos esperados</span><strong>{{ coverage.expected_objectives ?? 0 }}</strong></div><div><span>Objetivos tratados</span><strong>{{ coverage.treated_objectives ?? 0 }}</strong></div><div><span>Cobertura oficial</span><strong>{{ percentageLabel }}</strong></div></div>
      <div v-if="objectives.length" class="table-responsive lcd-coverage__table"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Objetivos curriculares oficiales y cobertura declarada en sesiones</caption><thead><tr><th scope="col">Objetivo</th><th scope="col">Tipo</th><th scope="col">Sesiones</th><th scope="col">Avance máximo declarado</th></tr></thead><tbody><tr v-for="objective in objectives" :key="objective.id || `${objective.code}-${objective.description}`"><td><strong>{{ objective.code }}</strong><small>{{ objective.description }}</small></td><td>{{ objective.objective_type || 'No catalogado' }}</td><td>{{ objective.sessions_count }}</td><td>{{ objective.maximum_coverage === null || objective.maximum_coverage === undefined ? 'Sin estimación' : `${objective.maximum_coverage}%` }}</td></tr></tbody></table></div>
      <LibroDigitalStatePanel v-else compact title="Sin cobertura registrada" message="Asocia objetivos oficiales desde el leccionario para construir esta lectura. El propósito libre no se contabiliza como OA." />
    </template>
  </section>
</template>

<style scoped>
.lcd-coverage{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-coverage>header{display:flex;align-items:flex-end;justify-content:space-between;gap:.8rem}.lcd-coverage>header span{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.07em}.lcd-coverage h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-coverage header p{margin:0;color:var(--lcd-muted,#748093);font-size:.8rem}.lcd-coverage header .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.75rem}
.lcd-coverage :deep(.btn-sm){min-height:36px}.lcd-coverage :deep(.btn:not(.btn-sm)){min-height:40px}
.lcd-coverage__summary{display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem}.lcd-coverage__summary div{padding:.75rem .82rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-md,10px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 8px rgba(37,47,63,.04))}.lcd-coverage__summary div:last-child{border-color:color-mix(in srgb,var(--lcd-brand-500,#405189) 18%,var(--lcd-border,#dfe5ec));background:linear-gradient(135deg,var(--lcd-surface,#fff),var(--lcd-brand-50,#f5f7fc))}.lcd-coverage__summary span,.lcd-coverage__summary strong{display:block}.lcd-coverage__summary span{color:var(--lcd-muted,#758194);font-size:.7rem}.lcd-coverage__summary strong{margin-top:.12rem;color:var(--lcd-ink,#2d394b);font-size:1.05rem}
.lcd-coverage__table{max-height:600px;border:1px solid var(--lcd-border,#e0e6ed);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-coverage__table table{font-size:.78rem}.lcd-coverage__table th{position:sticky;z-index:1;top:0;padding:.65rem .7rem;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#647184);font-size:.69rem;letter-spacing:.04em;text-transform:uppercase}.lcd-coverage__table td{padding:.68rem .7rem;border-color:var(--lcd-border,#edf0f4)}.lcd-coverage__table td strong,.lcd-coverage__table td small{display:block}.lcd-coverage__table td strong{color:var(--lcd-brand-700,#405189);font-size:.8rem}.lcd-coverage__table td small{max-width:720px;margin-top:.18rem;color:var(--lcd-muted,#7d8998);font-size:.72rem;line-height:1.4;white-space:normal}
@media(max-width:680px){.lcd-coverage>header{align-items:flex-start;flex-direction:column}.lcd-coverage__summary{grid-template-columns:1fr}}
</style>
