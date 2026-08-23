<script setup>
import { computed, ref } from "vue";
import RiskLevelBadge from "./RiskLevelBadge.vue";

const props = defineProps({ modelValue: { type: Array, default: () => [] }, catalogs: { type: Object, default: () => ({}) }, users: { type: Array, default: () => [] }, readonly: { type: Boolean, default: false } });
const emit = defineEmits(["update:modelValue", "calculate"]);
const search = ref("");
const compact = ref(true);
const selected = ref([]);
const bulkResponsible = ref("");
const bulkPeriodicity = ref("monthly");
const rows = computed(() => props.modelValue.flatMap((process, processIndex) =>
  (process.tasks || []).flatMap((task, taskIndex) =>
    (task.risks || []).map((risk, riskIndex) => ({
      process,
      task,
      risk,
      processIndex,
      taskIndex,
      riskIndex,
      key: `${processIndex}-${taskIndex}-${riskIndex}`,
    })),
  ),
));
const visibleRows = computed(() => {
  const needle = search.value.trim().toLowerCase();
  return needle ? rows.value.filter(({ process, task, risk }) => [process.name, task.activity_name, task.task_name, risk.specific_risk_name].some((value) => String(value || "").toLowerCase().includes(needle))) : rows.value;
});
const allSelected = computed(() => visibleRows.value.length > 0 && visibleRows.value.every(({ key }) => selected.value.includes(key)));
function assessment(risk) { return risk.assessments?.find((item) => item.phase === "current") || {}; }
function control(risk) { return risk.controls?.find((item) => item.control_stage !== "existing") || {}; }
function score(risk) { const a = assessment(risk); return Number(a.probability || 0) * Number(a.consequence || 0) || null; }
function update() { emit("update:modelValue", props.modelValue); }
function duplicate(row) {
  const clone = JSON.parse(JSON.stringify(row.risk));
  clone.specific_risk_code = null;
  row.task.risks.splice(row.riskIndex + 1, 0, clone);
  update();
}
function addBelow(row) {
  row.task.risks.splice(row.riskIndex + 1, 0, { risk_family_id: props.catalogs.risk_family?.[0]?.id, specific_risk_name: "", possible_harm: "", evaluation_method: "vep", declared_controlled_status: "not_assessed", verified_controlled_status: "not_assessed", hazard_factors: [{ category: "environment", hazard_description: "", risk_factor_description: "" }], assessments: [{ phase: "current", method: "vep", probability: 1, consequence: 1 }], controls: [] });
  update();
}
function remove(row) { row.task.risks.splice(row.riskIndex, 1); selected.value = selected.value.filter((key) => key !== row.key); update(); }
function toggleAll() { selected.value = allSelected.value ? [] : visibleRows.value.map(({ key }) => key); }
function applyBulk() {
  rows.value.filter(({ key }) => selected.value.includes(key)).forEach(({ risk }) => {
    let item = risk.controls.find((entry) => entry.control_stage !== "existing");
    if (!item) { item = { control_stage: "preventive", hierarchy_type: "administrative", description: "", status: "pending", priority: "medium", periodicity_type: "once", progress_percentage: 0, creates_program_action: true }; risk.controls.push(item); }
    if (bulkResponsible.value) item.responsible_user_id = Number(bulkResponsible.value);
    item.periodicity_type = bulkPeriodicity.value;
  });
  update();
}
</script>

<template>
  <div class="matrix-shell" :class="{ 'matrix-shell--compact': compact }">
    <div class="matrix-toolbar">
      <div class="matrix-search"><i class="bx bx-search"></i><input v-model="search" type="search" placeholder="Filtrar proceso, tarea o riesgo…" /></div>
      <span>{{ visibleRows.length }} riesgos</span>
      <button type="button" class="btn btn-sm btn-light" @click="compact = !compact"><i class="bx bx-columns"></i> {{ compact ? 'Expandir' : 'Compactar' }}</button>
    </div>
    <div v-if="selected.length && !readonly" class="bulk-bar">
      <strong>{{ selected.length }} seleccionados</strong>
      <select v-model="bulkResponsible" class="form-select form-select-sm"><option value="">Responsable</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select>
      <select v-model="bulkPeriodicity" class="form-select form-select-sm"><option v-for="item in catalogs.periodicity || []" :key="item.code" :value="item.code">{{ item.name }}</option></select>
      <button type="button" class="btn btn-sm btn-primary" @click="applyBulk">Aplicar</button>
    </div>
    <div class="matrix-scroll">
      <table class="table matrix-table">
        <thead><tr><th class="sticky sticky--select"><input type="checkbox" :checked="allSelected" aria-label="Seleccionar riesgos visibles" @change="toggleAll" /></th><th class="sticky sticky--activity">Actividad / tarea</th><th>Peligro y factor</th><th>Riesgo / daño</th><th>Método</th><th>Prob.</th><th>Cons.</th><th>VEP / nivel</th><th>Jerarquía y medida</th><th>Responsable</th><th>Vencimiento</th><th v-if="!readonly">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="row in visibleRows" :key="row.key" :class="{ 'is-selected': selected.includes(row.key) }">
            <td class="sticky sticky--select"><input v-model="selected" type="checkbox" :value="row.key" :aria-label="`Seleccionar ${row.risk.specific_risk_name}`" /></td>
            <td class="sticky sticky--activity"><span class="process-tag">{{ row.process.name }}</span><strong>{{ row.task.activity_name }}</strong><small>{{ row.task.task_name }}</small></td>
            <td><template v-if="readonly"><strong>{{ row.risk.hazard_factors?.[0]?.hazard_description }}</strong><small>{{ row.risk.hazard_factors?.[0]?.risk_factor_description }}</small></template><template v-else><input v-model="row.risk.hazard_factors[0].hazard_description" class="cell-input" placeholder="Peligro" @change="update" /><textarea v-model="row.risk.hazard_factors[0].risk_factor_description" class="cell-input" rows="2" placeholder="Factor de riesgo" @change="update"></textarea></template></td>
            <td><template v-if="readonly"><strong>{{ row.risk.specific_risk_name }}</strong><small>{{ row.risk.possible_harm }}</small></template><template v-else><input v-model="row.risk.specific_risk_name" class="cell-input" placeholder="Riesgo específico" @change="update" /><textarea v-model="row.risk.possible_harm" class="cell-input" rows="2" placeholder="Daño posible" @change="update"></textarea></template></td>
            <td><span v-if="readonly">{{ row.risk.evaluation_method }}</span><select v-else v-model="row.risk.evaluation_method" class="cell-input" @change="assessment(row.risk).method = row.risk.evaluation_method; update()"><option v-for="item in catalogs.evaluation_method || []" :key="item.code" :value="item.code">{{ item.name }}</option></select></td>
            <td><span v-if="row.risk.evaluation_method !== 'vep'">—</span><span v-else-if="readonly">{{ assessment(row.risk).probability }}</span><select v-else v-model.number="assessment(row.risk).probability" class="cell-input score-select" @change="emit('calculate', row.risk); update()"><option :value="1">1</option><option :value="2">2</option><option :value="4">4</option></select></td>
            <td><span v-if="row.risk.evaluation_method !== 'vep'">—</span><span v-else-if="readonly">{{ assessment(row.risk).consequence }}</span><select v-else v-model.number="assessment(row.risk).consequence" class="cell-input score-select" @change="emit('calculate', row.risk); update()"><option :value="1">1</option><option :value="2">2</option><option :value="4">4</option></select></td>
            <td><RiskLevelBadge v-if="row.risk.evaluation_method === 'vep'" :score="score(row.risk)" /><span v-else class="protocol-result">{{ assessment(row.risk).result_level || 'Pendiente' }}</span></td>
            <td><template v-if="readonly"><small>{{ control(row.risk).hierarchy_type }}</small><strong>{{ control(row.risk).description || 'Sin medida' }}</strong></template><template v-else-if="control(row.risk).control_stage"><select v-model="control(row.risk).hierarchy_type" class="cell-input" @change="update"><option v-for="item in catalogs.control_hierarchy || []" :key="item.code" :value="item.code">{{ item.name }}</option></select><textarea v-model="control(row.risk).description" class="cell-input" rows="2" placeholder="Medida" @change="update"></textarea></template><button v-else type="button" class="btn btn-sm btn-outline-primary" @click="row.risk.controls.push({ control_stage:'preventive', hierarchy_type:'administrative', description:'', status:'pending', priority:'medium', periodicity_type:'once', progress_percentage:0, creates_program_action:true }); update()">Agregar medida</button></td>
            <td><span v-if="readonly">{{ users.find((u) => u.id === control(row.risk).responsible_user_id)?.name || control(row.risk).responsible_text || '—' }}</span><select v-else-if="control(row.risk).control_stage" v-model="control(row.risk).responsible_user_id" class="cell-input" @change="update"><option :value="null">Sin asignar</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></td>
            <td><span v-if="readonly">{{ control(row.risk).due_date || '—' }}</span><input v-else-if="control(row.risk).control_stage" v-model="control(row.risk).due_date" type="date" class="cell-input" @change="update" /></td>
            <td v-if="!readonly"><div class="row-actions"><button type="button" title="Duplicar fila" @click="duplicate(row)"><i class="bx bx-copy"></i></button><button type="button" title="Agregar debajo" @click="addBelow(row)"><i class="bx bx-plus"></i></button><button type="button" title="Eliminar" @click="remove(row)"><i class="bx bx-trash"></i></button></div></td>
          </tr>
          <tr v-if="!visibleRows.length"><td :colspan="readonly ? 11 : 12" class="empty-row">No hay riesgos para mostrar.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.matrix-shell{overflow:hidden;border:1px solid #dfe6ee;border-radius:10px;background:#fff}.matrix-toolbar,.bulk-bar{display:flex;align-items:center;gap:.6rem;padding:.65rem .8rem;border-bottom:1px solid #e6ebf1}.matrix-toolbar>span{margin-left:auto;color:#667085;font-size:.68rem}.matrix-search{display:flex;align-items:center;gap:.35rem;min-width:280px;padding:.38rem .55rem;border:1px solid #d7dee8;border-radius:7px}.matrix-search input{width:100%;border:0;outline:0;font-size:.72rem}.bulk-bar{background:#edf6ff}.bulk-bar strong{font-size:.72rem}.bulk-bar .form-select{max-width:210px}.matrix-scroll{max-height:62vh;overflow:auto}.matrix-table{min-width:1580px;margin:0;font-size:.65rem}.matrix-table th{position:sticky;z-index:4;top:0;padding:.56rem;background:#17324d;color:#fff;white-space:nowrap}.matrix-table td{padding:.42rem;vertical-align:top;border-color:#edf0f4}.sticky{position:sticky!important;z-index:3;background:#fff}.sticky--select{left:0;width:38px;text-align:center}.sticky--activity{left:38px;width:190px;box-shadow:6px 0 8px -8px #344054}.matrix-table th.sticky{z-index:6;background:#17324d}.matrix-table td strong,.matrix-table td small{display:block}.matrix-table td small{color:#7b8794}.process-tag{display:inline-block;margin-bottom:.2rem;padding:.12rem .3rem;border-radius:4px;background:#e9f2f8;color:#28536f;font-size:.55rem;font-weight:700}.cell-input{display:block;width:100%;min-width:125px;margin-bottom:.25rem;padding:.3rem .38rem;border:1px solid #d9e1ea;border-radius:5px;background:#fff;font-size:.64rem}.score-select{min-width:54px}.row-actions{display:flex;gap:.2rem}.row-actions button{display:grid;place-items:center;width:28px;height:28px;border:1px solid #d7dee8;border-radius:5px;background:#fff;color:#3b556d}.is-selected td{background:#f3f8fc}.is-selected td.sticky{background:#f3f8fc}.protocol-result{display:inline-flex;padding:.25rem .4rem;border-radius:6px;background:#eef2f6;color:#475467}.empty-row{height:180px;text-align:center!important;vertical-align:middle!important;color:#667085}.matrix-shell--compact .matrix-table td{max-width:190px}.matrix-shell--compact textarea{max-height:46px}@media(max-width:700px){.matrix-toolbar{align-items:stretch;flex-direction:column}.matrix-search{min-width:0}.matrix-toolbar>span{margin-left:0}}
</style>
