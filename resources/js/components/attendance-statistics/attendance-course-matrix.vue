<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
const props = defineProps({ filters: { type: Object, required: true }, catalogs: { type: Object, default: () => ({}) } });
const selectedCourse = ref(props.filters.course_section_id || null); const loading = ref(false); const error = ref(null); const matrix = ref({ dates: [], students: [], legend: [] });
const courseName = computed(() => (props.catalogs.courses || []).find((course) => Number(course.id) === Number(selectedCourse.value))?.display_name || "Curso");
const cellState = (record) => { if (!record) return "missing"; if (record.status === "absent" && record.is_justified) return "justified"; if (record.status === "absent") return "absent"; if (record.early_departure) return "early_departure"; if (record.minutes_late > 0) return "late"; return "present"; };
const load = async () => {
  if (!selectedCourse.value) { matrix.value = { dates: [], students: [], legend: [] }; return; }
  loading.value = true; error.value = null;
  try { const response = await axios.get("/api/attendance-statistics/heatmap", { params: { ...props.filters, course_section_id: selectedCourse.value } }); matrix.value = response.data; }
  catch (requestError) { error.value = requestError.response?.data?.message || "No fue posible construir la matriz."; }
  finally { loading.value = false; }
};
watch(() => JSON.stringify(props.filters), () => { selectedCourse.value = props.filters.course_section_id || selectedCourse.value; load(); }); onMounted(load);
</script>

<template>
  <section class="matrix-panel">
    <header><div><span>ANÁLISIS POR CURSO</span><h2>Matriz diaria de asistencia</h2><p>Filas por estudiante y columnas por día lectivo, con desplazamiento horizontal.</p></div><label>Curso<select v-model="selectedCourse" class="form-select" @change="load"><option :value="null">Selecciona un curso</option><option v-for="course in catalogs.courses || []" :key="course.id" :value="course.id">{{ course.display_name }}</option></select></label></header>
    <div v-if="loading" class="matrix-state"><span class="spinner-border"></span>Construyendo matriz</div>
    <div v-else-if="error" class="matrix-state error"><i class="bx bx-error-circle"></i>{{ error }}<button type="button" class="btn btn-sm btn-outline-danger" @click="load">Reintentar</button></div>
    <div v-else-if="!selectedCourse" class="matrix-state"><i class="bx bx-grid-alt"></i><strong>Selecciona un curso para ver su matriz</strong></div>
    <div v-else-if="!matrix.dates.length" class="matrix-state"><i class="bx bx-calendar-x"></i><strong>{{ courseName }} no tiene jornadas en este periodo</strong></div>
    <template v-else><div class="matrix-legend"><span v-for="item in matrix.legend" :key="item.key"><i :class="`state-${item.key}`"></i>{{ item.label }}</span></div><div class="matrix-scroll"><table><thead><tr><th class="student-column">Estudiante</th><th v-for="date in matrix.dates" :key="date" :title="date">{{ date.slice(5).replace('-', '/') }}</th></tr></thead><tbody><tr v-for="student in matrix.students" :key="student.id"><th class="student-column"><strong>{{ student.name }}</strong><small>{{ student.rut || 'Sin RUT' }}</small></th><td v-for="date in matrix.dates" :key="date"><span class="matrix-cell" :class="`state-${cellState(student.records?.[date])}`" :title="`${student.name} · ${date} · ${cellState(student.records?.[date])}`"></span></td></tr></tbody></table></div></template>
  </section>
</template>

<style scoped>
.matrix-panel{border:1px solid #dfe5ec;border-radius:8px;background:#fff}.matrix-panel>header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid #e7ebf0}.matrix-panel>header span{color:#6d7889;font-size:.62rem;font-weight:700}.matrix-panel h2{margin:.12rem 0;color:#273244;font-size:1rem}.matrix-panel p{margin:0;color:#748094;font-size:.7rem}.matrix-panel>header label{width:220px;color:#526071;font-size:.68rem;font-weight:650}.matrix-panel .form-select{margin-top:.25rem;font-size:.72rem}.matrix-state{display:flex;align-items:center;justify-content:center;gap:.55rem;min-height:360px;color:#6f7b8c;font-size:.75rem}.matrix-state i{font-size:1.8rem}.matrix-state.error{color:#b43341}.matrix-legend{display:flex;flex-wrap:wrap;gap:.8rem;padding:.6rem 1rem;border-bottom:1px solid #e7ebf0}.matrix-legend span{display:flex;align-items:center;gap:.3rem;color:#657184;font-size:.62rem}.matrix-legend i,.matrix-cell{display:block;width:12px;height:12px;border-radius:2px}.matrix-scroll{max-height:560px;overflow:auto}.matrix-scroll table{border-collapse:separate;border-spacing:0;min-width:max-content;width:100%;font-size:.62rem}.matrix-scroll th,.matrix-scroll td{height:28px;padding:.2rem;border-right:1px solid #eef1f4;border-bottom:1px solid #eef1f4;text-align:center}.matrix-scroll thead th{position:sticky;z-index:2;top:0;background:#f7f9fb;color:#657184}.student-column{position:sticky!important;z-index:3!important;left:0;width:210px;max-width:210px;padding:.3rem .6rem!important;background:#fff!important;text-align:left!important}.matrix-scroll thead .student-column{z-index:4!important;background:#f7f9fb!important}.student-column strong,.student-column small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.student-column small{color:#8a94a3;font-size:.55rem}.matrix-cell{margin:auto}.state-present{background:#2b8a66}.state-absent{background:#c13c4a}.state-justified{background:#4b83c4}.state-late{background:#d59b26}.state-early_departure{background:#7866a3}.state-missing{background:#dfe4ea;border:1px solid #cbd3dc}@media(max-width:700px){.matrix-panel>header{align-items:stretch;flex-direction:column}.matrix-panel>header label{width:100%}.student-column{width:150px;max-width:150px}}
</style>
