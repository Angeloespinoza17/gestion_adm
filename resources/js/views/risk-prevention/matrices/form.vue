<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { onBeforeRouteLeave, useRoute, useRouter } from "vue-router";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import ExposureEditor from "../../../components/risk-prevention/iper/ExposureEditor.vue";
import RiskMatrixGrid from "../../../components/risk-prevention/iper/RiskMatrixGrid.vue";
import RiskValidationSummary from "../../../components/risk-prevention/iper/RiskValidationSummary.vue";
import RiskApprovalPanel from "../../../components/risk-prevention/iper/RiskApprovalPanel.vue";
import RiskLevelBadge from "../../../components/risk-prevention/iper/RiskLevelBadge.vue";
import { riskMatrixApi, toEditableStructure, toPersistableStructure } from "../../../services/risk-matrix-api";
import { formatRiskError, showRiskError, showRiskSuccess } from "../../../components/risk-prevention/module-utils";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const saving = ref(false);
const step = ref(1);
const dirty = ref(false);
const initialized = ref(false);
const catalogs = ref({ catalogs: {}, users: [], work_centers: [], departments: [], positions: [], staff: [], permissions: [], company: {} });
const matrixId = ref(route.params.id ? Number(route.params.id) : null);
const versionId = ref(route.query.version ? Number(route.query.version) : null);
const version = ref(null);
const validation = ref({ data: [], summary: {} });
const evidence = reactive({ file: null, evidence_type: "report", description: "", evidence_date: new Date().toISOString().slice(0,10), visibility: "authorized" });
const general = reactive({ code: "", folio: "", name: "", description: "", company_name: "", company_tax_id: "", company_address: "", economic_activity_code: "", work_center_id: null, department_id: null, prepared_on: new Date().toISOString().slice(0,10), total_workers: null, program_responsible_id: null, legal_representative_id: null, review_reason: "" });
const processes = ref([]);
const steps = [
  [1,"Datos generales","bx-building"],[2,"Procesos y tareas","bx-git-branch"],[3,"Personas expuestas","bx-group"],[4,"Peligros y riesgos","bx-error-alt"],
  [5,"Evaluación","bx-calculator"],[6,"Controles y medidas","bx-shield-quarter"],[7,"Participación y evidencias","bx-paperclip"],[8,"Revisión y aprobación","bx-badge-check"],
];
const completion = computed(() => Math.round(((step.value - 1) / 7) * 100));
const allTasks = computed(() => processes.value.flatMap((process, p) => (process.tasks || []).map((task, t) => ({ process, task, p, t }))));
const allRisks = computed(() => allTasks.value.flatMap(({ process, task, p, t }) => (task.risks || []).map((risk, r) => ({ process, task, risk, p, t, r }))));
const editable = computed(() => !version.value || ["draft","observed"].includes(typeof version.value.status === "string" ? version.value.status : version.value.status?.value));
const stepIssues = computed(() => validation.value.data?.filter((issue) => issue.path?.startsWith(step.value === 1 ? "general" : step.value === 2 ? "processes" : "")) || []);

function newRisk() { return { risk_family_id: catalogs.value.catalogs.risk_family?.[0]?.id, specific_risk_name: "", possible_harm: "", evaluation_method: "vep", declared_controlled_status: "not_assessed", verified_controlled_status: "not_assessed", hazard_factors: [{ category: "environment", hazard_description: "", risk_factor_description: "" }], assessments: [{ phase: "current", method: "vep", probability: 1, consequence: 1, instrument_date: new Date().toISOString().slice(0,10) }], controls: [] }; }
function newTask() { return { activity_name: "", task_name: "", routine_type: "routine", position_ids: [], job_position_text: "", location_id: null, specific_location: "", zero_exposure_justification: "", observations: "", exposures: (catalogs.value.catalogs.exposure_category || []).map(({ id }) => ({ exposure_category_id: id, count: 0 })), risks: [newRisk()] }; }
function addProcess() { processes.value.push({ name: "", description: "", process_type: "operational", observations: "", tasks: [newTask()] }); }
function addTask(process) { process.tasks.push(newTask()); }
function removeProcess(index) { processes.value.splice(index, 1); }
function removeTask(process, index) { process.tasks.splice(index, 1); }
function ensureRiskPlaceholders(structure) {
  structure.forEach((process) => (process.tasks || []).forEach((task) => {
    if (!(task.risks || []).length) task.risks = [newRisk()];
  }));
  return structure;
}

async function load() {
  loading.value = true;
  try {
    catalogs.value = await riskMatrixApi.catalogs();
    Object.assign(general, { company_name: catalogs.value.company?.name || "", company_tax_id: catalogs.value.company?.tax_id || "", company_address: catalogs.value.company?.address || "", economic_activity_code: catalogs.value.company?.economic_activity_code || "" });
    if (matrixId.value) {
      if (!versionId.value) { const versions = await riskMatrixApi.versions(matrixId.value); versionId.value = Number(route.query.version || versions[0]?.id); }
      version.value = await riskMatrixApi.version(versionId.value);
      Object.assign(general, {
        code: version.value.matrix.code, folio: version.value.matrix.folio, name: version.value.matrix.name, description: version.value.matrix.description,
        company_name: version.value.company_name_snapshot, company_tax_id: version.value.company_tax_id_snapshot, company_address: version.value.company_address_snapshot,
        economic_activity_code: version.value.economic_activity_code_snapshot, work_center_id: version.value.matrix.work_center_id,
        department_id: version.value.department_id, prepared_on: version.value.prepared_on, total_workers: version.value.total_workers,
        program_responsible_id: version.value.program_responsible_id, legal_representative_id: version.value.legal_representative_id, review_reason: version.value.review_reason,
      });
      processes.value = ensureRiskPlaceholders(toEditableStructure(version.value));
      await refreshValidation();
    } else addProcess();
  } catch (error) { showRiskError(formatRiskError(error, "No se pudo preparar el asistente IPER.")); }
  finally { loading.value = false; initialized.value = true; dirty.value = false; }
}
async function saveGeneral() {
  if (!general.code || !general.name) throw new Error("Código y nombre son obligatorios.");
  if (!versionId.value) {
    const response = await riskMatrixApi.create(general);
    matrixId.value = response.data.matrix.id;
    versionId.value = response.data.version.id;
    version.value = response.data.version;
    dirty.value = false;
    await router.replace({ path: `/risk-prevention/matrices/${matrixId.value}/editar`, query: { version: versionId.value } });
  } else {
    version.value = await riskMatrixApi.updateVersion(versionId.value, {
      lock_version: version.value.lock_version, name: general.name, folio: general.folio, description: general.description,
      work_center_id: general.work_center_id, company_name_snapshot: general.company_name, company_tax_id_snapshot: general.company_tax_id,
      company_address_snapshot: general.company_address, economic_activity_code_snapshot: general.economic_activity_code,
      work_center_name_snapshot: catalogs.value.work_centers.find((item) => Number(item.id) === Number(general.work_center_id))?.name,
      department_id: general.department_id, prepared_on: general.prepared_on, total_workers: general.total_workers,
      program_responsible_id: general.program_responsible_id, program_responsible_name_snapshot: catalogs.value.users.find((item) => Number(item.id) === Number(general.program_responsible_id))?.name,
      legal_representative_id: general.legal_representative_id, legal_representative_name_snapshot: catalogs.value.staff.find((item) => Number(item.id) === Number(general.legal_representative_id))?.full_name,
      review_reason: general.review_reason,
    });
  }
}
async function saveStructure() {
  if (!versionId.value) await saveGeneral();
  version.value = await riskMatrixApi.saveStructure(versionId.value, { lock_version: version.value.lock_version, processes: toPersistableStructure(processes.value) });
  processes.value = ensureRiskPlaceholders(toEditableStructure(version.value));
}
async function save() {
  saving.value = true;
  try { if (step.value === 1) await saveGeneral(); else await saveStructure(); dirty.value = false; await refreshValidation(); showRiskSuccess("Borrador guardado y validado por el backend."); }
  catch (error) { showRiskError(formatRiskError(error, error.message || "No se pudo guardar.")); }
  finally { saving.value = false; }
}
async function next() { if (dirty.value || !versionId.value) await save(); if (step.value < 8) step.value++; if (step.value === 8) await refreshValidation(); }
async function refreshValidation() { if (versionId.value) validation.value = await riskMatrixApi.validation(versionId.value); }
async function calculate(risk) {
  const assessment = risk.assessments.find((item) => item.phase === "current");
  if (risk.evaluation_method !== "vep" || !assessment) return;
  try { risk._preview = await riskMatrixApi.calculate({ probability: assessment.probability, consequence: assessment.consequence, methodology_id: version.value?.methodology_id || catalogs.value.active_methodology?.id }); }
  catch (error) { showRiskError(formatRiskError(error, "Valores VEP inválidos.")); }
}
async function workflow(action, payload) {
  saving.value = true;
  try { const response = await riskMatrixApi.workflow(versionId.value, action, payload); showRiskSuccess(response.message); version.value = await riskMatrixApi.version(versionId.value); processes.value = ensureRiskPlaceholders(toEditableStructure(version.value)); await refreshValidation(); }
  catch (error) { showRiskError(formatRiskError(error, "No se pudo completar la transición.")); }
  finally { saving.value = false; }
}
async function uploadEvidence() {
  if (!evidence.file) return showRiskError("Selecciona un archivo de evidencia.");
  const data = new FormData(); Object.entries(evidence).forEach(([key, value]) => value !== null && data.append(key, value));
  try { await riskMatrixApi.uploadEvidence(versionId.value, data); showRiskSuccess("Evidencia privada cargada."); evidence.file = null; version.value = await riskMatrixApi.version(versionId.value); }
  catch (error) { showRiskError(formatRiskError(error, "No se pudo cargar la evidencia.")); }
}
function navigateIssue(issue) { if (issue.path.startsWith("general")) step.value = 1; else if (issue.path.includes("exposure")) step.value = 3; else if (issue.path.includes("assessment")) step.value = 5; else if (issue.path.includes("control")) step.value = 6; else step.value = 4; }
watch([general, processes], () => { if (initialized.value) dirty.value = true; }, { deep: true });
onBeforeRouteLeave(() => dirty.value ? window.confirm("Hay cambios sin guardar. ¿Deseas salir?") : true);
onMounted(load);
</script>

<template>
  <Layout>
    <div class="wizard-header"><div><router-link to="/risk-prevention/matrices" class="back-link"><i class="bx bx-left-arrow-alt"></i> Matrices IPER/MIPER</router-link><h3>{{ matrixId ? `Editar ${general.code}` : 'Nueva matriz IPER/MIPER' }}</h3><p>Asistente técnico con cálculo backend, control de versión y validación previa.</p></div><div class="save-state"><span :class="{ 'is-dirty': dirty }"><i class="bx" :class="dirty ? 'bx-cloud-upload' : 'bx-cloud' "></i>{{ dirty ? 'Cambios sin guardar' : 'Todo guardado' }}</span><button v-if="editable" type="button" class="btn btn-primary" :disabled="saving" @click="save"><span v-if="saving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-save"></i> Guardar borrador</button></div></div>
    <LoadingState v-if="loading" message="Preparando metodología y catálogos…" />
    <template v-else>
      <div class="completion"><div><span :style="{ width: `${completion}%` }"></span></div><small>{{ completion }}% del flujo recorrido</small></div>
      <nav class="stepper" aria-label="Pasos de creación IPER"><button v-for="item in steps" :key="item[0]" type="button" :class="{ active: step === item[0], done: step > item[0] }" @click="step = item[0]"><i class="bx" :class="step > item[0] ? 'bx-check' : item[2]"></i><span><small>Paso {{ item[0] }}</small>{{ item[1] }}</span></button></nav>
      <main class="wizard-panel">
        <header><div><span>Paso {{ step }} de 8</span><h4>{{ steps[step-1][1] }}</h4></div><span v-if="version" class="version-chip">v{{ version.version_number }} · {{ typeof version.status === 'string' ? version.status : version.status?.value }}</span></header>
        <section v-if="step === 1" class="form-grid"><label><span>Código *</span><input v-model="general.code" class="form-control" :disabled="!!matrixId" /></label><label><span>Folio</span><input v-model="general.folio" class="form-control" /></label><label class="span-2"><span>Nombre *</span><input v-model="general.name" class="form-control" /></label><label><span>Empresa</span><input v-model="general.company_name" class="form-control" /></label><label><span>RUT empresa</span><input v-model="general.company_tax_id" class="form-control" /></label><label class="span-2"><span>Dirección</span><input v-model="general.company_address" class="form-control" /></label><label><span>Centro de trabajo *</span><select v-model="general.work_center_id" class="form-select"><option :value="null">Seleccionar</option><option v-for="item in catalogs.work_centers" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label><span>Área / departamento</span><select v-model="general.department_id" class="form-select"><option :value="null">Institucional</option><option v-for="item in catalogs.departments" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label><span>Fecha de elaboración *</span><input v-model="general.prepared_on" type="date" class="form-control" /></label><label><span>Total de personas</span><input v-model.number="general.total_workers" type="number" min="0" class="form-control" /></label><label><span>Responsable del programa *</span><select v-model="general.program_responsible_id" class="form-select"><option :value="null">Seleccionar</option><option v-for="item in catalogs.users" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label><span>Representante legal</span><select v-model="general.legal_representative_id" class="form-select"><option :value="null">No informado</option><option v-for="item in catalogs.staff" :key="item.id" :value="item.id">{{ item.full_name }}</option></select></label><label class="span-2"><span>Descripción</span><textarea v-model="general.description" class="form-control" rows="3"></textarea></label><label class="span-2"><span>Motivo de revisión</span><textarea v-model="general.review_reason" class="form-control" rows="2"></textarea></label></section>
        <section v-else-if="step === 2" class="process-editor"><article v-for="(process, p) in processes" :key="p"><header><div class="process-title"><span>{{ String(p+1).padStart(2,'0') }}</span><input v-model="process.name" class="form-control" placeholder="Nombre del proceso" /></div><select v-model="process.process_type" class="form-select"><option v-for="item in catalogs.catalogs.process_type" :key="item.code" :value="item.code">{{ item.name }}</option></select><button type="button" title="Eliminar proceso" @click="removeProcess(p)"><i class="bx bx-trash"></i></button></header><div v-for="(task, t) in process.tasks" :key="t" class="task-row"><span class="task-index">{{ p+1 }}.{{ t+1 }}</span><input v-model="task.activity_name" class="form-control" placeholder="Actividad" /><input v-model="task.task_name" class="form-control" placeholder="Tarea" /><select v-model="task.routine_type" class="form-select"><option v-for="item in catalogs.catalogs.task_type" :key="item.code" :value="item.code">{{ item.name }}</option></select><input v-model="task.job_position_text" class="form-control" placeholder="Puesto específico" /><input v-model="task.specific_location" class="form-control" placeholder="Lugar" /><button type="button" title="Eliminar tarea" @click="removeTask(process,t)"><i class="bx bx-x"></i></button></div><button type="button" class="add-task" @click="addTask(process)"><i class="bx bx-plus"></i> Agregar actividad o tarea</button></article><button type="button" class="add-process" @click="addProcess"><i class="bx bx-git-branch"></i> Agregar proceso</button></section>
        <section v-else-if="step === 3" class="exposure-list"><article v-for="row in allTasks" :key="`${row.p}-${row.t}`"><header><div><span>{{ row.process.name }}</span><h6>{{ row.task.activity_name }} · {{ row.task.task_name }}</h6></div><span>{{ row.task.routine_type }}</span></header><ExposureEditor v-model="row.task.exposures" v-model:justification="row.task.zero_exposure_justification" :categories="catalogs.catalogs.exposure_category" /></article></section>
        <section v-else-if="[4,6].includes(step)"><RiskMatrixGrid v-model="processes" :catalogs="catalogs.catalogs" :users="catalogs.users" @calculate="calculate" /></section>
        <section v-else-if="step === 5" class="assessment-list"><article v-for="row in allRisks" :key="`${row.p}-${row.t}-${row.r}`"><header><div><span>{{ row.process.name }} · {{ row.task.task_name }}</span><h6>{{ row.risk.specific_risk_name || 'Riesgo sin nombre' }}</h6></div><RiskLevelBadge v-if="row.risk.evaluation_method === 'vep'" :score="Number(row.risk.assessments[0]?.probability || 0) * Number(row.risk.assessments[0]?.consequence || 0)" /></header><div class="assessment-fields"><label><span>Método</span><select v-model="row.risk.evaluation_method" class="form-select" @change="row.risk.assessments[0].method = row.risk.evaluation_method"><option v-for="item in catalogs.catalogs.evaluation_method" :key="item.code" :value="item.code">{{ item.name }}</option></select></label><template v-if="row.risk.evaluation_method === 'vep'"><label><span>Probabilidad</span><select v-model.number="row.risk.assessments[0].probability" class="form-select" @change="calculate(row.risk)"><option :value="1">1 · Baja</option><option :value="2">2 · Media</option><option :value="4">4 · Alta</option></select></label><label><span>Consecuencia</span><select v-model.number="row.risk.assessments[0].consequence" class="form-select" @change="calculate(row.risk)"><option :value="1">1 · Baja</option><option :value="2">2 · Media</option><option :value="4">4 · Alta</option></select></label><div v-if="row.risk._preview" class="backend-result"><span>Resultado backend</span><strong>VEP {{ row.risk._preview.vep }} · {{ row.risk._preview.risk_level_label }}</strong><small v-if="row.risk._preview.severe_consequence_warning">Advertencia: consecuencia severa.</small></div></template><template v-else><label><span>Protocolo</span><select v-model="row.risk.assessments[0].protocol_id" class="form-select"><option :value="null">Seleccionar</option><option v-for="item in catalogs.catalogs.protocol" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label><span>Versión</span><input v-model="row.risk.assessments[0].protocol_version" class="form-control" /></label><label><span>Fecha evaluación *</span><input v-model="row.risk.assessments[0].instrument_date" type="date" class="form-control" /></label><label><span>Resultado / clasificación *</span><input v-model="row.risk.assessments[0].result_level" class="form-control" /></label><label><span>Valor</span><input v-model.number="row.risk.assessments[0].result_value" type="number" step="any" class="form-control" /></label><label><span>Unidad</span><input v-model="row.risk.assessments[0].exposure_unit" class="form-control" /></label><label><span>Instrumento</span><input v-model="row.risk.assessments[0].instrument" class="form-control" /></label><label><span>Evaluador</span><input v-model="row.risk.assessments[0].evaluator" class="form-control" /></label><label><span>Próxima medición</span><input v-model="row.risk.assessments[0].next_measurement_at" type="date" class="form-control" /></label></template></div></article></section>
        <section v-else-if="step === 7" class="participation-grid"><article><i class="bx bx-group"></i><h5>Participación y consulta</h5><p>Registra representantes y constancias desde la vista de detalle una vez guardada la versión. La aprobación interna no se presenta como firma electrónica avanzada.</p><button type="button" class="btn btn-outline-primary" :disabled="!matrixId" @click="router.push(`/risk-prevention/matrices/${matrixId}?tab=participation`)">Gestionar participantes</button></article><article><i class="bx bx-lock-alt"></i><h5>Evidencia privada</h5><div class="evidence-form"><input type="file" class="form-control" @change="evidence.file = $event.target.files[0]" /><select v-model="evidence.evidence_type" class="form-select"><option v-for="item in catalogs.catalogs.evidence_type" :key="item.code" :value="item.code">{{ item.name }}</option></select><input v-model="evidence.description" class="form-control" placeholder="Descripción" /><button type="button" class="btn btn-primary" :disabled="!versionId" @click="uploadEvidence">Cargar evidencia</button></div></article></section>
        <section v-else-if="step === 8" class="review-grid"><RiskValidationSummary :issues="validation.data" @navigate="navigateIssue" /><RiskApprovalPanel v-if="version" :version="version" :permissions="catalogs.permissions" :busy="saving" @action="workflow" /></section>
        <footer><button type="button" class="btn btn-light" :disabled="step === 1" @click="step--"><i class="bx bx-left-arrow-alt"></i> Anterior</button><div><button v-if="editable" type="button" class="btn btn-outline-primary" :disabled="saving" @click="save">Guardar</button><button v-if="step < 8" type="button" class="btn btn-primary" :disabled="saving" @click="next">Guardar y continuar <i class="bx bx-right-arrow-alt"></i></button><router-link v-else-if="matrixId" :to="`/risk-prevention/matrices/${matrixId}`" class="btn btn-primary">Ver detalle</router-link></div></footer>
      </main>
    </template>
  </Layout>
</template>

<style scoped>
.wizard-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:.7rem}.back-link{display:inline-flex;align-items:center;gap:.25rem;color:#667085;font-size:.65rem}.wizard-header h3{margin:.18rem 0}.wizard-header p{margin:0;color:#667085;font-size:.7rem}.save-state{display:flex;align-items:center;gap:.6rem}.save-state>span{display:flex;align-items:center;gap:.3rem;color:#15803d;font-size:.64rem}.save-state>span.is-dirty{color:#d97706}.save-state .btn{display:inline-flex;align-items:center;gap:.35rem}.completion{display:flex;align-items:center;gap:.6rem;margin-bottom:.7rem}.completion>div{flex:1;height:5px;overflow:hidden;border-radius:99px;background:#e5e7eb}.completion>div span{display:block;height:100%;background:linear-gradient(90deg,#16806f,#3b82f6);transition:.3s}.completion small{color:#667085;font-size:.58rem}.stepper{display:grid;grid-template-columns:repeat(8,1fr);overflow:hidden;margin-bottom:.75rem;border:1px solid #e1e7ee;border-radius:11px;background:#fff}.stepper button{display:flex;align-items:center;gap:.35rem;padding:.62rem .5rem;border:0;border-right:1px solid #edf0f4;background:#fff;color:#667085;text-align:left}.stepper button:last-child{border-right:0}.stepper i{display:grid;place-items:center;width:28px;height:28px;border-radius:8px;background:#f2f4f7}.stepper span{font-size:.58rem;font-weight:700}.stepper small{display:block;color:#98a2b3;font-size:.48rem}.stepper .active{background:#eef6f8;color:#174b58}.stepper .active i,.stepper .done i{background:#16806f;color:#fff}.wizard-panel{overflow:hidden;border:1px solid #dfe6ed;border-radius:12px;background:#fff;box-shadow:0 7px 20px rgba(16,24,40,.045)}.wizard-panel>header{display:flex;align-items:center;justify-content:space-between;padding:.8rem 1rem;border-bottom:1px solid #e9edf2}.wizard-panel>header span{color:#667085;font-size:.56rem;text-transform:uppercase}.wizard-panel h4{margin:.1rem 0}.version-chip{padding:.3rem .5rem;border-radius:99px;background:#e9f2f8;color:#28536f!important;font-weight:700}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;padding:1rem}.form-grid label>span,.assessment-fields label span{display:block;margin-bottom:.3rem;color:#536174;font-size:.62rem;font-weight:700}.span-2{grid-column:1/-1}.process-editor,.exposure-list,.assessment-list,.participation-grid,.review-grid{display:grid;gap:.7rem;padding:1rem}.process-editor article,.exposure-list article,.assessment-list article{overflow:hidden;border:1px solid #e1e7ee;border-radius:10px}.process-editor article>header{display:grid;grid-template-columns:1fr 160px 34px;gap:.5rem;padding:.65rem;background:#f6f9fb}.process-title{display:flex;align-items:center;gap:.5rem}.process-title>span{display:grid;place-items:center;width:32px;height:32px;border-radius:8px;background:#17324d;color:#fff;font-size:.65rem}.process-editor header button,.task-row button{border:1px solid #d6dee7;border-radius:6px;background:#fff;color:#b42318}.task-row{display:grid;grid-template-columns:40px 1fr 1fr 130px 1fr 1fr 34px;gap:.45rem;padding:.55rem;border-top:1px solid #edf0f3}.task-index{align-self:center;color:#667085;font-size:.62rem}.add-task,.add-process{display:flex;align-items:center;gap:.3rem;padding:.55rem;border:0;background:#fff;color:#175cd3;font-size:.65rem;font-weight:700}.add-process{justify-content:center;border:1px dashed #93c5d5;border-radius:10px;background:#f2f8fa}.exposure-list article>header,.assessment-list article>header{display:flex;justify-content:space-between;padding:.65rem .8rem;background:#f7f9fb}.exposure-list header span,.assessment-list header span{color:#667085;font-size:.58rem}.exposure-list h6,.assessment-list h6{margin:.1rem 0}.exposure-list :deep(.exposure-editor){padding:.75rem}.assessment-fields{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem;padding:.75rem}.backend-result{display:flex;flex-direction:column;justify-content:center;padding:.6rem;border-left:3px solid #16806f;background:#f0fdfa}.backend-result span,.backend-result small{color:#667085;font-size:.58rem}.backend-result strong{font-size:.72rem}.participation-grid{grid-template-columns:1fr 1fr}.participation-grid article{padding:1rem;border:1px solid #e1e7ee;border-radius:10px;background:linear-gradient(145deg,#fff,#f7fafc)}.participation-grid article>i{font-size:1.6rem;color:#16806f}.participation-grid p{color:#667085;font-size:.67rem}.evidence-form{display:grid;gap:.5rem}.review-grid{grid-template-columns:1fr 1fr}.wizard-panel>footer{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-top:1px solid #e9edf2;background:#fbfcfd}.wizard-panel>footer>div{display:flex;gap:.5rem}.wizard-panel>footer .btn{display:inline-flex;align-items:center;gap:.3rem}@media(max-width:1200px){.stepper{grid-template-columns:repeat(4,1fr)}.stepper button{border-bottom:1px solid #edf0f4}.task-row{grid-template-columns:40px 1fr 1fr 110px}.assessment-fields{grid-template-columns:1fr 1fr}}@media(max-width:700px){.wizard-header{align-items:flex-start;flex-direction:column}.save-state{width:100%;justify-content:space-between}.stepper{display:flex;overflow-x:auto}.stepper button{min-width:135px}.form-grid,.participation-grid,.review-grid{grid-template-columns:1fr}.span-2{grid-column:auto}.process-editor article>header,.task-row{grid-template-columns:1fr}.task-index{display:none}.assessment-fields{grid-template-columns:1fr}.wizard-panel>footer{align-items:stretch;flex-direction:column;gap:.5rem}.wizard-panel>footer>div{justify-content:flex-end}}
</style>
