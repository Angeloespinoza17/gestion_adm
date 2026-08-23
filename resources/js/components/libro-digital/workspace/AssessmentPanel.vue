<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import { confirmAction, errorMessage, formatDate, payloadData, payloadItems, payloadMeta, showError, showSuccess, studentLabel } from "../module-utils";

const props = defineProps({ bookId: { type: [Number, String], required: true }, context: { type: Object, required: true }, roster: { type: Array, default: () => [] }, canManage: { type: Boolean, default: false } });
const loading = ref(false); const error = ref(null); const items = ref([]); const showForm = ref(false); const saving = ref(false); const editing = ref(null); let controller = null;
const showResults = ref(false); const loadingResults = ref(false); const savingResults = ref(false); const resultAssessment = ref(null); const resultRows = ref([]);
const curriculumObjectives = ref([]); const curriculumBlocker = ref(null); const curriculumError = ref(null);
const curriculumPrograms = ref([]); const curriculumProgram = ref(null);
const form = reactive({ name: "", description: "", assessment_type: "summative", scheduled_on: "", weighting: null, maximum_score: null, grading_scale: "1_to_7", curriculum_objective_ids: [], curriculum_program_id: null, curriculum_unit_id: null });
const selectedUnit = computed(() => curriculumProgram.value?.units?.find((unit) => unit.id === form.curriculum_unit_id) || null);
const availableObjectives = computed(() => {
  if (!selectedUnit.value) return curriculumObjectives.value;
  const codes = new Set((selectedUnit.value.objectives || []).map((objective) => objective.code));
  return curriculumObjectives.value.filter((objective) => codes.has(objective.code));
});
const loadProgram = async (programId, preserveSelection = false) => {
  curriculumProgram.value = null;
  if (!programId) return;
  curriculumProgram.value = payloadData(await libroDigitalApi.curriculumProgram(programId, { school_id: props.context.school_id, academic_year_id: props.context.academic_year_id }));
  if (!preserveSelection) {
    form.curriculum_unit_id = null;
    form.curriculum_objective_ids = [];
  }
};
const summary = computed(() => ({ total: items.value.length, draft: items.value.filter((item) => ["draft", "borrador"].includes(item.status)).length, closed: items.value.filter((item) => ["closed", "cerrado"].includes(item.status)).length }));
const load = async () => {
  controller?.abort(); controller = new AbortController(); loading.value = true; error.value = null; curriculumError.value = null; curriculumBlocker.value = null; curriculumObjectives.value = [];
  try {
    items.value = payloadItems(await libroDigitalApi.assessments(props.bookId, { academic_year_id: props.context.academic_year_id, per_page: 200 }, controller.signal));
    if (props.context.school_id && props.context.academic_year_id) {
      try {
        const payload = await libroDigitalApi.curriculumObjectives({ school_id: props.context.school_id, academic_year_id: props.context.academic_year_id, book_id: props.bookId, schedule_subject_id: props.context.schedule_subject_id, per_page: 100 }, controller.signal);
        curriculumObjectives.value = payloadItems(payload);
        curriculumBlocker.value = payloadMeta(payload).compliance_blocker || null;
        curriculumPrograms.value = payloadItems(await libroDigitalApi.curriculumPrograms({ school_id: props.context.school_id, academic_year_id: props.context.academic_year_id, schedule_subject_id: props.context.schedule_subject_id, education_level_id: props.context.education_level_id, status: "published", per_page: 25 }, controller.signal));
      } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") curriculumError.value = requestError;
      }
    }
  } catch (requestError) { if (requestError?.code !== "ERR_CANCELED") error.value = requestError; }
  finally { loading.value = false; }
};
watch([() => props.bookId, () => props.context.school_id, () => props.context.academic_year_id], load, { immediate: true }); onBeforeUnmount(() => controller?.abort());
const openCreate = () => { if (!props.canManage) return; editing.value = null; curriculumProgram.value = null; Object.assign(form, { name: "", description: "", assessment_type: "summative", scheduled_on: new Date().toISOString().slice(0, 10), weighting: null, maximum_score: null, grading_scale: "1_to_7", curriculum_objective_ids: [], curriculum_program_id: null, curriculum_unit_id: null }); showForm.value = true; };
const openEdit = async (item) => { if (!props.canManage) return; editing.value = item; Object.assign(form, { name: item.name || item.title || "", description: item.description || "", assessment_type: item.assessment_type || item.type || "summative", scheduled_on: String(item.scheduled_on || item.date || "").slice(0, 10), weighting: item.weighting ?? null, maximum_score: item.maximum_score ?? null, grading_scale: item.grading_scale || "1_to_7", curriculum_objective_ids: item.curriculum_objective_ids || [], curriculum_program_id: item.curriculum_program_id || null, curriculum_unit_id: item.curriculum_unit_id || null }); if (form.curriculum_program_id) await loadProgram(form.curriculum_program_id, true); showForm.value = true; };
const save = async () => { if (!props.canManage) return; saving.value = true; try { if (editing.value) await libroDigitalApi.updateAssessment(editing.value, { ...form }); else await libroDigitalApi.createAssessment(props.bookId, { ...form }); showForm.value = false; await load(); await showSuccess(editing.value ? "Evaluación actualizada" : "Evaluación creada"); } catch (requestError) { await showError(requestError, "No se pudo guardar la evaluación"); if (requestError.isConflict) await load(); } finally { saving.value = false; } };
const close = async (item) => { if (!props.canManage) return; const confirmation = await confirmAction({ title: "Cerrar evaluación", text: "Se bloquearán las calificaciones vigentes. Una corrección posterior deberá quedar trazada.", confirmText: "Cerrar" }); if (!confirmation.isConfirmed) return; try { await libroDigitalApi.closeAssessment(item); await load(); await showSuccess("Evaluación cerrada"); } catch (requestError) { await showError(requestError); if (requestError.isConflict) await load(); } };
const openResults = async (item) => {
  if (!props.canManage) return;
  loadingResults.value = true;
  showResults.value = true;
  try {
    const detail = payloadData(await libroDigitalApi.assessment(item.id));
    resultAssessment.value = detail;
    const existing = new Map((detail.results || []).map((row) => [Number(row.student_profile_id), row]));
    const rosterRows = props.roster.length ? props.roster : (detail.results || []);
    resultRows.value = rosterRows.map((rosterRow) => {
      const studentId = Number(rosterRow.student_profile_id || rosterRow.student?.id);
      const current = existing.get(studentId) || {};
      return {
        student_profile_id: studentId,
        student_name: current.student_name || studentLabel(rosterRow.student || rosterRow),
        raw_score: current.raw_score ?? null,
        numeric_value: current.numeric_value ?? null,
        absent: Boolean(current.absent),
        exempt: Boolean(current.exempt),
        observation: current.observation || "",
      };
    });
  } catch (requestError) {
    showResults.value = false;
    await showError(requestError, "No se pudieron cargar las calificaciones");
  } finally { loadingResults.value = false; }
};
const setResultException = (row, field) => {
  if (row[field]) {
    row[field === "absent" ? "exempt" : "absent"] = false;
    row.raw_score = null;
    row.numeric_value = null;
  }
};
const saveResults = async () => {
  const incomplete = resultRows.value.filter((row) => !row.absent && !row.exempt && [null, ""].includes(row.raw_score) && [null, ""].includes(row.numeric_value));
  if (incomplete.length) {
    await showError(new Error(`Falta calificación o condición especial para ${incomplete.length} estudiante(s).`), "Resultados incompletos");
    return;
  }
  savingResults.value = true;
  try {
    const response = await libroDigitalApi.updateAssessmentResults(resultAssessment.value, {
      results: resultRows.value.map((row) => ({
        student_profile_id: row.student_profile_id,
        raw_score: row.raw_score === "" ? null : row.raw_score,
        numeric_value: row.numeric_value === "" ? null : row.numeric_value,
        absent: row.absent,
        exempt: row.exempt,
        observation: row.observation || null,
      })),
    });
    resultAssessment.value = payloadData(response);
    showResults.value = false;
    await load();
    await showSuccess("Calificaciones guardadas", "La evaluación continúa editable hasta su cierre expreso.");
  } catch (requestError) {
    await showError(requestError, "No se pudieron guardar las calificaciones");
    if (requestError.isConflict) await openResults(resultAssessment.value);
  } finally { savingResults.value = false; }
};
</script>

<template>
  <section class="lcd-assessments" aria-labelledby="lcd-assessments-title" :aria-busy="loading || saving">
    <header><div><span>EVALUACIÓN Y CALIFICACIÓN</span><h3 id="lcd-assessments-title">Evaluaciones</h3><p>Planifica instrumentos y controla el cierre de calificaciones.</p></div><BButton v-if="canManage" type="button" size="sm" variant="primary" @click="openCreate"><i class="bx bx-plus"></i> Nueva evaluación</BButton></header>
    <BAlert v-if="!canManage" show variant="info" class="small mb-0">Vista de consulta. No cuentas con permiso para modificar evaluaciones o calificaciones.</BAlert>
    <div class="lcd-assessments__summary" aria-label="Resumen de evaluaciones"><div><i class="bx bx-list-ul" aria-hidden="true"></i><span>Total</span><strong>{{ summary.total }}</strong></div><div><i class="bx bx-edit" aria-hidden="true"></i><span>Borradores</span><strong>{{ summary.draft }}</strong></div><div><i class="bx bx-lock-alt" aria-hidden="true"></i><span>Cerradas</span><strong>{{ summary.closed }}</strong></div></div>
    <LibroDigitalStatePanel v-if="loading && !items.length" state="loading" compact title="Cargando evaluaciones" message="Consultando instrumentos del libro." />
    <LibroDigitalStatePanel v-else-if="error && !items.length" state="error" compact title="No se pudieron cargar las evaluaciones" :message="errorMessage(error)" @retry="load" />
    <div v-else-if="items.length" class="table-responsive lcd-assessments__table"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Evaluaciones registradas y estado de sus resultados</caption><thead><tr><th scope="col">Evaluación</th><th scope="col">Fecha</th><th scope="col">Tipo / ponderación</th><th scope="col">Resultados</th><th scope="col">Estado</th><th v-if="canManage" scope="col" class="text-end">Acciones</th></tr></thead><tbody><tr v-for="item in items" :key="item.id"><td><strong>{{ item.name || item.title }}</strong><small>{{ item.description || 'Sin descripción' }}</small></td><td>{{ formatDate(item.scheduled_on || item.date) }}</td><td>{{ item.assessment_type || item.type }}<small v-if="item.weighting">{{ item.weighting }}%</small></td><td>{{ item.results_count ?? item.graded_count ?? 0 }} / {{ item.expected_results_count ?? item.students_count ?? '—' }}</td><td><LibroDigitalStatusBadge :status="item.status" /></td><td v-if="canManage"><div class="lcd-row-actions"><BButton type="button" size="sm" variant="outline-success" @click="openResults(item)"><i class="bx bx-spreadsheet" aria-hidden="true"></i> Resultados</BButton><BButton v-if="!['closed','cerrado'].includes(item.status)" type="button" size="sm" variant="outline-primary" @click="openEdit(item)"><i class="bx bx-edit" aria-hidden="true"></i> Editar</BButton><BButton v-if="!['closed','cerrado'].includes(item.status)" type="button" size="sm" variant="outline-secondary" @click="close(item)"><i class="bx bx-lock-alt" aria-hidden="true"></i> Cerrar</BButton></div></td></tr></tbody></table></div>
    <LibroDigitalStatePanel v-else compact title="Sin evaluaciones" message="Crea el primer instrumento del libro seleccionado." />
    <BModal v-model="showForm" :title="editing ? 'Editar evaluación' : 'Nueva evaluación'" size="lg" hide-footer>
      <form class="row g-3" @submit.prevent="save">
        <div class="col-md-8"><label class="form-label" for="lcd-assessment-name">Nombre</label><BFormInput id="lcd-assessment-name" v-model.trim="form.name" maxlength="191" required /></div>
        <div class="col-md-4"><label class="form-label" for="lcd-assessment-date">Fecha</label><BFormInput id="lcd-assessment-date" v-model="form.scheduled_on" type="date" required /></div>
        <div class="col-md-4"><label class="form-label" for="lcd-assessment-type">Tipo</label><BFormSelect id="lcd-assessment-type" v-model="form.assessment_type"><option value="diagnostic">Diagnóstica</option><option value="formative">Formativa</option><option value="summative">Sumativa</option><option value="recovery">Recuperativa</option></BFormSelect></div>
        <div class="col-md-4"><label class="form-label" for="lcd-assessment-weight">Ponderación (%)</label><BFormInput id="lcd-assessment-weight" v-model.number="form.weighting" type="number" min="0" max="100" step=".01" /></div>
        <div class="col-md-4"><label class="form-label" for="lcd-assessment-score">Puntaje máximo</label><BFormInput id="lcd-assessment-score" v-model.number="form.maximum_score" type="number" min=".01" step=".01" /></div>
        <div class="col-12"><label class="form-label" for="lcd-assessment-description">Descripción</label><BFormTextarea id="lcd-assessment-description" v-model.trim="form.description" rows="3" maxlength="2000" /></div>
        <div v-if="curriculumPrograms.length" class="col-12 lcd-assessment-program"><div><label class="form-label" for="lcd-assessment-program">Programa ministerial</label><BFormSelect id="lcd-assessment-program" v-model="form.curriculum_program_id" @change="loadProgram(form.curriculum_program_id)"><option :value="null">Sin programa específico</option><option v-for="program in curriculumPrograms" :key="program.id" :value="program.id">{{ program.name }} · {{ program.hours }} h</option></BFormSelect></div><div><label class="form-label" for="lcd-assessment-unit">Unidad evaluada</label><BFormSelect id="lcd-assessment-unit" v-model="form.curriculum_unit_id" :disabled="!curriculumProgram" @change="form.curriculum_objective_ids = []"><option :value="null">Todas las unidades</option><option v-for="unit in curriculumProgram?.units || []" :key="unit.id" :value="unit.id">{{ unit.code }} · {{ unit.focus || unit.title }}</option></BFormSelect></div><small><i class="bx bx-check-shield"></i> La evaluación solo admite unidades y OA pertenecientes al programa publicado del libro.</small></div>
        <div class="col-12 lcd-assessment-curriculum"><label class="form-label" for="lcd-assessment-curriculum">Objetivos curriculares oficiales (OA/OAT)</label><select id="lcd-assessment-curriculum" v-model="form.curriculum_objective_ids" class="form-select" multiple size="6" :disabled="!availableObjectives.length"><option v-for="objective in availableObjectives" :key="objective.id" :value="objective.id">{{ objective.code }} · {{ objective.description }}</option></select><small v-if="availableObjectives.length">{{ selectedUnit ? 'Objetivos acotados a la unidad seleccionada.' : 'Mantén Ctrl o ⌘ para seleccionar varios.' }} Solo se pueden asociar objetivos del catálogo oficial versionado.</small><BAlert v-else-if="curriculumBlocker" show variant="warning" class="small mt-2 mb-0">{{ curriculumBlocker.message }} No se permite ingresar códigos OA manualmente.</BAlert><BAlert v-else-if="curriculumError" show variant="warning" class="small mt-2 mb-0">No se pudo consultar el catálogo curricular: {{ errorMessage(curriculumError) }}</BAlert><small v-else>No hay objetivos oficiales disponibles para este contexto.</small></div>
        <div class="col-12 d-flex justify-content-end gap-2"><BButton type="button" variant="outline-secondary" @click="showForm = false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving || !canManage"><span v-if="saving" class="spinner-border spinner-border-sm"></span> Guardar</BButton></div>
      </form>
    </BModal>
    <BModal v-model="showResults" :title="`Resultados · ${resultAssessment?.name || 'Evaluación'}`" size="xl" hide-footer no-close-on-backdrop>
      <LibroDigitalStatePanel v-if="loadingResults" state="loading" compact title="Cargando nómina y resultados" message="Preparando la revisión vigente de la evaluación." />
      <template v-else-if="resultAssessment">
        <BAlert show variant="info" class="small">Registra una nota entre 1,0 y 7,0, un puntaje bruto, o marca ausencia/eximición. El cierre es una acción posterior e independiente.</BAlert>
        <div class="table-responsive lcd-results"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Estudiante</th><th scope="col">Nota</th><th v-if="resultAssessment.maximum_score" scope="col">Puntaje / {{ resultAssessment.maximum_score }}</th><th scope="col">Condición</th><th scope="col">Observación</th></tr></thead><tbody><tr v-for="row in resultRows" :key="row.student_profile_id"><td><strong>{{ row.student_name }}</strong></td><td><BFormInput v-model.number="row.numeric_value" type="number" min="1" max="7" step=".1" :disabled="row.absent || row.exempt || resultAssessment.status === 'closed'" :aria-label="`Nota de ${row.student_name}`" /></td><td v-if="resultAssessment.maximum_score"><BFormInput v-model.number="row.raw_score" type="number" min="0" :max="resultAssessment.maximum_score" step=".01" :disabled="row.absent || row.exempt || resultAssessment.status === 'closed'" :aria-label="`Puntaje de ${row.student_name}`" /></td><td><div class="lcd-result-flags"><BFormCheckbox v-model="row.absent" :disabled="resultAssessment.status === 'closed'" @change="setResultException(row, 'absent')">Ausente</BFormCheckbox><BFormCheckbox v-model="row.exempt" :disabled="resultAssessment.status === 'closed'" @change="setResultException(row, 'exempt')">Eximida</BFormCheckbox></div></td><td><BFormInput v-model.trim="row.observation" maxlength="2000" :disabled="resultAssessment.status === 'closed'" :aria-label="`Observación de ${row.student_name}`" /></td></tr></tbody></table></div>
        <footer class="lcd-results__footer"><span>{{ resultRows.length }} estudiantes de la nómina vigente</span><div><BButton type="button" variant="outline-secondary" @click="showResults = false">Cerrar</BButton><BButton v-if="resultAssessment.status !== 'closed'" type="button" variant="primary" :disabled="savingResults" @click="saveResults"><span v-if="savingResults" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-save"></i> Guardar resultados</BButton></div></footer>
      </template>
    </BModal>
  </section>
</template>

<style scoped>
.lcd-assessments{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-assessments>header{display:flex;align-items:flex-end;justify-content:space-between;gap:.8rem}.lcd-assessments>header span{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.07em}.lcd-assessments h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-assessments header p{margin:0;color:var(--lcd-muted,#748093);font-size:.8rem}.lcd-assessments header .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.75rem}
.lcd-assessments :deep(.btn-sm){min-height:36px}.lcd-assessments :deep(.btn:not(.btn-sm)){min-height:40px}.lcd-assessments :deep(.form-control),.lcd-assessments :deep(.form-select){min-height:40px;font-size:.8rem}
.lcd-assessments__summary{display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem}.lcd-assessments__summary div{display:grid;grid-template-columns:34px 1fr;grid-template-rows:auto auto;align-items:center;column-gap:.6rem;padding:.72rem .8rem;border:1px solid var(--lcd-border,#e0e6ed);border-radius:var(--lcd-radius-md,10px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 8px rgba(37,47,63,.04))}.lcd-assessments__summary i{grid-row:1/3;display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:var(--lcd-brand-100,#e8edf8);color:var(--lcd-brand-700,#405189);font-size:1rem}.lcd-assessments__summary span,.lcd-assessments__summary strong{display:block}.lcd-assessments__summary span{color:var(--lcd-muted,#758194);font-size:.7rem}.lcd-assessments__summary strong{color:var(--lcd-ink,#2d394b);font-size:1rem}
.lcd-assessments__table,.lcd-results{border:1px solid var(--lcd-border,#e0e6ed);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-assessments__table table,.lcd-results table{font-size:.78rem}.lcd-assessments__table th,.lcd-results th{padding:.62rem .68rem;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#647184);font-size:.69rem;letter-spacing:.04em;text-transform:uppercase}.lcd-assessments__table td,.lcd-results td{padding:.62rem .68rem;border-color:var(--lcd-border,#edf0f4)}.lcd-assessments__table td strong,.lcd-assessments__table td small{display:block}.lcd-assessments__table td small{margin-top:.14rem;color:var(--lcd-muted,#7d8998);font-size:.72rem}.lcd-row-actions{display:flex;justify-content:flex-end;gap:.3rem;white-space:nowrap}.lcd-row-actions :deep(.btn){display:inline-flex;align-items:center;gap:.25rem;min-height:36px;border-radius:6px;font-size:.72rem}.form-label{color:var(--lcd-ink-soft,#536174);font-size:.75rem;font-weight:700}
.lcd-results{max-height:590px}.lcd-results th{position:sticky;z-index:1;top:0}.lcd-results .form-control{min-width:100px;min-height:38px;border-color:var(--lcd-border,#dfe5ec);font-size:.78rem}.lcd-result-flags{display:flex;gap:.65rem;min-width:175px}.lcd-results__footer{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin-top:.85rem;padding:.68rem .75rem;border-radius:var(--lcd-radius-md,9px);background:var(--lcd-surface-muted,#f7f8fa)}.lcd-results__footer>span{color:var(--lcd-muted,#748093);font-size:.74rem}.lcd-results__footer>div{display:flex;gap:.4rem}.lcd-results__footer .btn{display:inline-flex;align-items:center;gap:.3rem;min-height:40px;border-radius:var(--lcd-radius-sm,7px)}
.lcd-assessment-curriculum{padding:.75rem;border:1px solid color-mix(in srgb,var(--lcd-brand-500,#405189) 16%,var(--lcd-border,#dfe5ec));border-radius:var(--lcd-radius-md,9px);background:var(--lcd-brand-50,#f6f8fc)}.lcd-assessment-curriculum select{min-height:145px;font-size:.78rem}.lcd-assessment-curriculum>small{display:block;margin-top:.34rem;color:var(--lcd-muted,#748093);font-size:.72rem;line-height:1.45}
.lcd-assessment-program{display:grid;grid-template-columns:1.3fr 1fr;gap:.75rem;padding:.8rem;border:1px solid #c7e8e2;border-radius:10px;background:#f1fbf9}.lcd-assessment-program>small{grid-column:1/-1;color:#087e6d;font-size:.71rem}
@media(max-width:760px){.lcd-assessments>header{align-items:flex-start;flex-direction:column}.lcd-assessments__summary,.lcd-assessment-program{grid-template-columns:1fr}.lcd-results__footer{align-items:stretch;flex-direction:column}.lcd-results__footer>div{display:grid;grid-template-columns:1fr 1fr}}
</style>
