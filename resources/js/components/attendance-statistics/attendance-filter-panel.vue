<script setup>
import { computed, ref } from "vue";
import Swal from "sweetalert2";
import axios from "axios";

const props = defineProps({
  modelValue: { type: Object, required: true },
  catalogs: { type: Object, default: () => ({}) },
  activeCount: { type: Number, default: 0 },
  refreshing: { type: Boolean, default: false },
});
const emit = defineEmits(["apply", "reset"]);
const expanded = ref(false);
const courses = computed(() => (props.catalogs.courses || []).filter((course) => !props.modelValue.education_level_id || Number(course.education_level_id) === Number(props.modelValue.education_level_id)));

const saveFilter = async () => {
  const result = await Swal.fire({
    title: "Guardar vista de filtros",
    input: "text",
    inputLabel: "Nombre",
    inputPlaceholder: "Ej. Seguimiento semanal básica",
    showCancelButton: true,
    confirmButtonText: "Guardar",
    cancelButtonText: "Cancelar",
    inputValidator: (value) => (!value?.trim() ? "Ingresa un nombre." : undefined),
  });
  if (!result.isConfirmed) return;
  try {
    await axios.post("/api/attendance-statistics/saved-filters", { name: result.value.trim(), filters: props.modelValue });
    await Swal.fire({ icon: "success", title: "Vista guardada", timer: 1400, showConfirmButton: false });
  } catch (error) {
    await Swal.fire({ icon: "error", title: "No se pudo guardar", text: error.response?.data?.message || "Intenta nuevamente." });
  }
};
</script>

<template>
  <section class="attendance-filter-panel" aria-label="Filtros globales de asistencia">
    <div class="filter-primary">
      <label>
        <span>Año académico</span>
        <select v-model="modelValue.academic_year_id" class="form-select">
          <option v-for="year in catalogs.academic_years || []" :key="year.id" :value="year.id">{{ year.name }}</option>
        </select>
      </label>
      <label>
        <span>Temporalidad</span>
        <select v-model="modelValue.period" class="form-select">
          <option value="today">Hoy</option><option value="yesterday">Ayer</option>
          <option value="current_week">Semana actual</option><option value="previous_week">Semana anterior</option>
          <option value="last_7_school_days">Últimos 7 días lectivos</option><option value="last_14_school_days">Últimos 14 días lectivos</option>
          <option value="last_30_days">Últimos 30 días</option><option value="current_month">Mes actual</option>
          <option value="previous_month">Mes anterior</option><option value="quarter">Trimestre</option>
          <option value="semester">Semestre</option><option value="academic_year">Año académico</option><option value="custom">Personalizado</option>
        </select>
      </label>
      <label v-if="modelValue.period === 'custom'">
        <span>Desde</span><input v-model="modelValue.from" type="date" class="form-control" />
      </label>
      <label v-if="modelValue.period === 'custom'">
        <span>Hasta</span><input v-model="modelValue.to" type="date" class="form-control" />
      </label>
      <label>
        <span>Nivel</span>
        <select v-model="modelValue.education_level_id" class="form-select" @change="modelValue.course_section_id = null">
          <option :value="null">Todos</option><option v-for="level in catalogs.levels || []" :key="level.id" :value="level.id">{{ level.name }}</option>
        </select>
      </label>
      <label>
        <span>Curso</span>
        <select v-model="modelValue.course_section_id" class="form-select">
          <option :value="null">Todos</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.display_name }}</option>
        </select>
      </label>
      <div class="filter-actions">
        <button type="button" class="btn btn-primary" :disabled="refreshing" @click="emit('apply')"><i class="bx bx-filter-alt"></i><span>Aplicar</span></button>
        <button type="button" class="icon-btn" :aria-expanded="expanded" title="Filtros avanzados" @click="expanded = !expanded"><i class="bx bx-slider-alt"></i><span v-if="activeCount" class="filter-count">{{ activeCount }}</span></button>
        <button type="button" class="icon-btn" title="Restablecer filtros" @click="emit('reset')"><i class="bx bx-reset"></i></button>
        <button type="button" class="icon-btn" title="Guardar vista" @click="saveFilter"><i class="bx bx-bookmark"></i></button>
      </div>
    </div>
    <div v-if="expanded" class="filter-advanced">
      <label><span>Estado de matrícula</span><select v-model="modelValue.enrollment_status" class="form-select"><option value="">Todos</option><option v-for="status in catalogs.enrollment_statuses || []" :key="status.value" :value="status.value">{{ status.label }}</option></select></label>
      <label><span>Estado de asistencia</span><select v-model="modelValue.attendance_status" class="form-select"><option value="">Todos</option><option value="present">Presente</option><option value="absent">Ausente</option></select></label>
      <label><span>Justificación</span><select v-model="modelValue.is_justified" class="form-select"><option value="">Todas</option><option :value="true">Justificada</option><option :value="false">No justificada</option></select></label>
      <label><span>Motivo</span><select v-model="modelValue.absence_reason_id" class="form-select"><option :value="null">Todos</option><option v-for="reason in catalogs.absence_reasons || []" :key="reason.id" :value="reason.id">{{ reason.name }}</option></select></label>
      <label><span>Comuna</span><select v-model="modelValue.commune" class="form-select"><option value="">Todas</option><option v-for="commune in catalogs.communes || []" :key="commune" :value="commune">{{ commune }}</option></select></label>
      <label><span>PIE</span><select v-model="modelValue.is_pie_participant" class="form-select"><option value="">Todas</option><option :value="true">Participante</option><option :value="false">No participante</option></select></label>
      <label><span>Riesgo</span><select v-model="modelValue.risk" class="form-select"><option value="">Todos</option><option v-for="risk in catalogs.risk_levels || []" :key="risk.slug" :value="risk.slug">{{ risk.name }}</option></select></label>
      <label><span>Asistencia mínima</span><input v-model.number="modelValue.attendance_min" type="number" min="0" max="100" step="1" class="form-control" /></label>
      <label><span>Asistencia máxima</span><input v-model.number="modelValue.attendance_max" type="number" min="0" max="100" step="1" class="form-control" /></label>
    </div>
  </section>
</template>

<style scoped>
.attendance-filter-panel{border:1px solid #dfe5ec;border-radius:8px;background:#fff;padding:.75rem}.filter-primary,.filter-advanced{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr)) auto;gap:.6rem;align-items:end}.filter-advanced{grid-template-columns:repeat(5,minmax(135px,1fr));margin-top:.7rem;padding-top:.7rem;border-top:1px solid #edf0f4}.attendance-filter-panel label{margin:0;color:#526071;font-size:.68rem;font-weight:650}.attendance-filter-panel label span{display:block;margin-bottom:.25rem}.form-select,.form-control{min-height:36px;font-size:.74rem}.filter-actions{display:flex;gap:.35rem}.filter-actions .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:36px}.icon-btn{position:relative;display:inline-grid;place-items:center;width:36px;height:36px;border:1px solid #d7dee8;border-radius:6px;background:#fff;color:#405189}.filter-count{position:absolute;top:-6px;right:-6px;display:grid;place-items:center;min-width:17px;height:17px;padding:0 4px;border-radius:9px;background:#405189;color:#fff;font-size:.58rem}.icon-btn:focus-visible,.btn:focus-visible{outline:3px solid rgba(64,81,137,.24);outline-offset:2px}@media(max-width:1200px){.filter-primary{grid-template-columns:repeat(3,minmax(150px,1fr))}.filter-advanced{grid-template-columns:repeat(3,minmax(150px,1fr))}.filter-actions{grid-column:auto}}@media(max-width:767px){.filter-primary,.filter-advanced{grid-template-columns:1fr 1fr}.filter-actions{grid-column:1/-1}.filter-actions .btn{flex:1;justify-content:center}}@media(max-width:480px){.filter-primary,.filter-advanced{grid-template-columns:1fr}}
</style>
