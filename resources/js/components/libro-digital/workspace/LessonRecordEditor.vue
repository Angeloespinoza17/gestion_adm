<script setup>
import { onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import { errorMessage, payloadData, payloadItems, payloadMeta, showError, showSuccess } from "../module-utils";

const props = defineProps({ session: { type: Object, required: true }, context: { type: Object, default: () => ({}) }, canEdit: { type: Boolean, default: true } });
const emit = defineEmits(["updated"]);
const loading = ref(false); const saving = ref(false); const error = ref(null); const record = ref(null); let controller = null;
const curriculumObjectives = ref([]); const curriculumBlocker = ref(null); const curriculumError = ref(null);
const form = reactive({ objectives: "", contents: "", activities: "", methodology: "", resources: "", observations: "", curriculum_objective_ids: [], treatment_level: "introduced", progress_percentage: null });
const load = async () => {
  controller?.abort(); controller = new AbortController(); loading.value = true; error.value = null; curriculumError.value = null; curriculumBlocker.value = null; curriculumObjectives.value = [];
  try {
    record.value = payloadData(await libroDigitalApi.lessonRecord(props.session.id, controller.signal));
    Object.assign(form, { objectives: record.value.objectives || record.value.learning_objectives || "", contents: record.value.contents || "", activities: record.value.activities || "", methodology: record.value.methodology || "", resources: record.value.resources || "", observations: record.value.observations || "", curriculum_objective_ids: record.value.curriculum_objective_ids || [], treatment_level: record.value.treatment_level || "introduced", progress_percentage: record.value.progress_percentage ?? null });
    if (props.context.school_id && props.context.academic_year_id) {
      try {
        const payload = await libroDigitalApi.curriculumObjectives({ school_id: props.context.school_id, academic_year_id: props.context.academic_year_id, book_id: props.session.book_id, schedule_subject_id: props.session.schedule_subject_id, per_page: 100 }, controller.signal);
        curriculumObjectives.value = payloadItems(payload);
        curriculumBlocker.value = payloadMeta(payload).compliance_blocker || null;
      } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") curriculumError.value = requestError;
      }
    }
  } catch (requestError) { if (requestError?.code !== "ERR_CANCELED") error.value = requestError; }
  finally { loading.value = false; }
};
watch([() => props.session.id, () => props.context.school_id, () => props.context.academic_year_id], load, { immediate: true }); onBeforeUnmount(() => controller?.abort());
const locked = () => !props.canEdit || record.value?.locked || ["signed", "closed", "cancelled"].includes(String(props.session.status).toLowerCase());
const save = async () => { saving.value = true; try { const response = await libroDigitalApi.updateLessonRecord(props.session, record.value, { ...form }); record.value = payloadData(response); emit("updated", response); await showSuccess("Leccionario guardado"); } catch (requestError) { await showError(requestError, "No se pudo guardar el leccionario"); if (requestError.isConflict) await load(); } finally { saving.value = false; } };
</script>

<template>
  <section class="lcd-lesson" aria-labelledby="lcd-lesson-title" :aria-busy="loading || saving">
    <header><div><span>REGISTRO PEDAGÓGICO</span><h3 id="lcd-lesson-title">Leccionario</h3><p>Objetivos, contenidos y actividades de la sesión seleccionada.</p></div><LibroDigitalStatusBadge :status="record?.status || 'draft'" /></header>
    <LibroDigitalStatePanel v-if="loading && !record" state="loading" compact title="Cargando leccionario" message="Consultando la revisión vigente." />
    <LibroDigitalStatePanel v-else-if="error && !record" state="error" compact title="No se pudo cargar el leccionario" :message="errorMessage(error)" @retry="load" />
    <form v-else class="lcd-lesson__form" @submit.prevent="save">
      <BAlert v-if="locked()" show variant="info" class="small">{{ canEdit ? 'Este registro está firmado o cerrado. Solicita una enmienda para conservar la revisión original.' : 'Vista de solo lectura. No cuentas con permiso para modificar el leccionario.' }}</BAlert>
      <div><label for="lcd-lesson-objectives">Propósito pedagógico libre (no catalogado)</label><BFormTextarea id="lcd-lesson-objectives" v-model.trim="form.objectives" rows="3" maxlength="4000" placeholder="Describe el propósito en lenguaje libre; no ingreses ni inventes códigos OA." :disabled="locked()" required /><small class="lcd-field-help">Este texto complementa el leccionario y no cuenta como cobertura curricular oficial.</small></div>
      <div class="lcd-lesson__catalog"><label for="lcd-lesson-curriculum">Objetivos curriculares oficiales (OA/OAT)</label><select id="lcd-lesson-curriculum" v-model="form.curriculum_objective_ids" class="form-select" multiple size="6" :disabled="locked() || !curriculumObjectives.length"><option v-for="objective in curriculumObjectives" :key="objective.id" :value="objective.id">{{ objective.code }} · {{ objective.description }}</option></select><small v-if="curriculumObjectives.length">Mantén presionada Ctrl o ⌘ para seleccionar varios. Solo estos objetivos versionados computan cobertura oficial.</small><BAlert v-else-if="curriculumBlocker" show variant="warning" class="small mt-2 mb-0">{{ curriculumBlocker.message }} No se habilita captura manual de códigos OA.</BAlert><BAlert v-else-if="curriculumError" show variant="warning" class="small mt-2 mb-0">No se pudo consultar el catálogo curricular: {{ errorMessage(curriculumError) }}</BAlert><small v-else>No hay objetivos oficiales disponibles para este contexto.</small></div>
      <div class="lcd-lesson__columns"><div><label for="lcd-lesson-content">Contenidos</label><BFormTextarea id="lcd-lesson-content" v-model.trim="form.contents" rows="4" maxlength="5000" :disabled="locked()" required /></div><div><label for="lcd-lesson-activities">Actividades</label><BFormTextarea id="lcd-lesson-activities" v-model.trim="form.activities" rows="4" maxlength="5000" :disabled="locked()" required /></div></div>
      <div class="lcd-lesson__columns"><div><label for="lcd-lesson-methodology">Metodología</label><BFormTextarea id="lcd-lesson-methodology" v-model.trim="form.methodology" rows="2" maxlength="2000" :disabled="locked()" /></div><div><label for="lcd-lesson-resources">Recursos</label><BFormTextarea id="lcd-lesson-resources" v-model.trim="form.resources" rows="2" maxlength="2000" :disabled="locked()" /></div></div>
      <div class="lcd-lesson__coverage"><div><label for="lcd-lesson-treatment">Nivel de tratamiento</label><BFormSelect id="lcd-lesson-treatment" v-model="form.treatment_level" :disabled="locked()"><option value="introduced">Introducido</option><option value="developing">En desarrollo</option><option value="consolidated">Consolidado</option><option value="assessed">Evaluado</option></BFormSelect></div><div><label for="lcd-lesson-progress">Avance estimado (%)</label><BFormInput id="lcd-lesson-progress" v-model.number="form.progress_percentage" type="number" min="0" max="100" step="1" :disabled="locked()" /></div></div>
      <div><label for="lcd-lesson-observations">Observaciones</label><BFormTextarea id="lcd-lesson-observations" v-model.trim="form.observations" rows="2" maxlength="2000" :disabled="locked()" /></div>
      <footer><span><i class="bx bx-info-circle" aria-hidden="true"></i> Guardar no firma la sesión. La firma se realiza desde el control de sesiones.</span><BButton type="submit" variant="primary" size="sm" :disabled="saving || locked()"><span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-save" aria-hidden="true"></i> Guardar leccionario</BButton></footer>
    </form>
  </section>
</template>

<style scoped>
.lcd-lesson{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-lesson>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.lcd-lesson>header>div>span{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.07em}.lcd-lesson h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-lesson header p{margin:0;color:var(--lcd-muted,#748093);font-size:.8rem}
.lcd-lesson :deep(.btn-sm){min-height:36px}.lcd-lesson :deep(.btn:not(.btn-sm)){min-height:40px}
.lcd-lesson__form{display:grid;gap:.85rem;padding:.9rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-lesson__form label{display:block;margin-bottom:.34rem;color:var(--lcd-ink-soft,#536174);font-size:.75rem;font-weight:750}.lcd-lesson__columns{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}.lcd-lesson__coverage{display:grid;grid-template-columns:1fr 200px;gap:.85rem;padding:.72rem;border:1px solid var(--lcd-border,#e5e9ef);border-radius:var(--lcd-radius-md,9px);background:var(--lcd-surface-muted,#f7f8fa)}.lcd-lesson__form :deep(.form-control),.lcd-lesson__form :deep(.form-select){min-height:40px;border-color:var(--lcd-border,#dfe5ec);font-size:.8rem}.lcd-lesson__form :deep(textarea.form-control){min-height:auto;line-height:1.5}.lcd-lesson__form :deep(.form-control:focus),.lcd-lesson__form :deep(.form-select:focus){border-color:var(--lcd-brand-500,#5268a3);box-shadow:0 0 0 3px var(--lcd-focus,rgba(64,81,137,.18))}.lcd-lesson__catalog{padding:.75rem;border:1px solid color-mix(in srgb,var(--lcd-brand-500,#405189) 16%,var(--lcd-border,#dfe5ec));border-radius:var(--lcd-radius-md,9px);background:var(--lcd-brand-50,#f6f8fc)}.lcd-lesson__catalog select{min-height:145px;background:var(--lcd-surface,#fff)}.lcd-lesson__catalog>small,.lcd-field-help{display:block;margin-top:.34rem;color:var(--lcd-muted,#748093);font-size:.72rem;line-height:1.45}.lcd-lesson__form footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:0 -.9rem -.9rem;padding:.72rem .9rem;border-top:1px solid var(--lcd-border,#e5e9ef);border-radius:0 0 var(--lcd-radius-lg,12px) var(--lcd-radius-lg,12px);background:var(--lcd-surface-muted,#f7f8fa)}.lcd-lesson__form footer>span{display:flex;align-items:center;gap:.35rem;color:var(--lcd-muted,#788496);font-size:.72rem}.lcd-lesson__form footer>span i{color:var(--lcd-brand-600,#5268a3);font-size:1rem}.lcd-lesson__form footer .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.76rem}
@media(max-width:760px){.lcd-lesson__columns,.lcd-lesson__coverage{grid-template-columns:1fr}.lcd-lesson__form footer{align-items:stretch;flex-direction:column}.lcd-lesson__form footer .btn{justify-content:center}}
</style>
