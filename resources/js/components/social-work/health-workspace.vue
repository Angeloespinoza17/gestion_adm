<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useSocialWork } from '../../composables/useSocialWork'

const api = reactive(useSocialWork())
const students = ref([])
const search = ref('')
const onlyAlerts = ref(false)
const selected = ref(null)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const name = student => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')
const course = student => student.current_enrollment?.course_section?.display_name || student.current_enrollment?.snapshot_course_display_name || 'Sin curso'
const value = data => data === null || data === undefined || data === '' ? 'Sin registro' : data

async function load(page = 1) {
  const query = new URLSearchParams({ page, per_page: 25 })
  if (search.value) query.set('search', search.value)
  if (onlyAlerts.value) query.set('medical_alert', '1')
  try {
    const response = await api.get(`/support-matrix?${query}`)
    students.value = response.data || []
    pagination.value = { current_page: response.current_page, last_page: response.last_page, total: response.total }
  } catch { students.value = [] }
}

onMounted(load)
</script>

<template>
  <section class="health-card">
    <header><div class="icon"><i class="bx bx-plus-medical"></i></div><div><span>Fuente oficial: módulo Estudiantes</span><h2>Salud y alertas de alumnas</h2><p>Esta vista lee la ficha maestra. Los antecedentes no se duplican en Trabajo Social.</p></div><div class="sync"><i class="bx bx-sync"></i> Sincronizado</div></header>
    <div v-if="api.error" class="alert alert-danger mx-4 mt-3 mb-0">{{ api.error }}</div>
    <div class="filters"><div class="input-group"><span class="input-group-text"><i class="bx bx-search"></i></span><input v-model="search" class="form-control" placeholder="Buscar alumna o RUT" @keyup.enter="load(1)"></div><label><input v-model="onlyAlerts" type="checkbox" @change="load(1)"> Mostrar solo alertas médicas</label><button class="btn btn-outline-primary" @click="load(1)">Actualizar</button></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Alumna</th><th>Curso</th><th>Previsión / prestador</th><th>Alergias</th><th>Condición crónica</th><th>Restricción física</th><th>Ayudas</th><th>Alerta</th><th></th></tr></thead><tbody>
      <tr v-for="student in students" :key="student.id"><td><strong>{{ name(student) }}</strong><small>{{ student.rut || 'Sin RUT' }}</small></td><td>{{ course(student) }}</td><td><strong>{{ value(student.health?.insurance) }}</strong><small>{{ value(student.health?.provider) }}</small></td><td>{{ student.health?.food_allergies || student.health?.medication_allergies_details || 'No registradas' }}</td><td>{{ student.health?.has_chronic_illness ? (student.health?.chronic_illness_details || 'Sí') : 'No registrada' }}</td><td>{{ student.health?.has_physical_restrictions ? (student.health?.physical_restrictions_details || 'Sí') : 'No' }}</td><td><span v-for="device in student.support_devices || []" :key="device.id" class="device">{{ device.support_type }}</span><span v-if="!student.support_devices?.length" class="muted">—</span></td><td><span class="alert-state" :class="{ active: student.medical_alert }"><i class="bx" :class="student.medical_alert ? 'bx-error-circle' : 'bx-check-circle'"></i>{{ student.medical_alert ? 'Revisar' : 'Sin alerta' }}</span></td><td><button class="btn btn-sm btn-outline-primary" @click="selected = student">Ver ficha</button></td></tr>
      <tr v-if="!students.length && !api.loading"><td colspan="9" class="empty">No hay antecedentes de salud para los filtros seleccionados.</td></tr>
    </tbody></table></div>
    <div v-if="api.loading" class="loading"><span class="spinner-border spinner-border-sm"></span> Leyendo ficha maestra de estudiantes…</div>
    <footer v-if="pagination.last_page > 1"><button class="btn btn-sm btn-light" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button><span>{{ pagination.current_page }} / {{ pagination.last_page }}</span><button class="btn btn-sm btn-light" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</button></footer>
  </section>

  <Teleport to="body"><div v-if="selected" class="health-overlay" @click.self="selected = null"><article class="health-detail"><header><div><span>Ficha maestra de salud</span><h2>{{ name(selected) }}</h2><p>{{ selected.rut }} · {{ course(selected) }}</p></div><button @click="selected = null"><i class="bx bx-x"></i></button></header><div class="detail-grid">
    <section><span>Previsión</span><strong>{{ value(selected.health?.insurance) }}</strong><small>{{ value(selected.health?.provider) }}</small></section>
    <section><span>Grupo sanguíneo</span><strong>{{ value(selected.health?.blood_type) }}</strong><small>{{ value(selected.health?.height_cm) }} cm · {{ value(selected.health?.weight_kg) }} kg</small></section>
    <section><span>Alergias alimentarias</span><strong>{{ value(selected.health?.food_allergies) }}</strong></section>
    <section><span>Alergias a medicamentos</span><strong>{{ selected.health?.has_medication_allergies ? 'Sí' : 'No registradas' }}</strong><small>{{ value(selected.health?.medication_allergies_details) }}</small></section>
    <section><span>Enfermedad crónica</span><strong>{{ selected.health?.has_chronic_illness ? 'Sí' : 'No registrada' }}</strong><small>{{ value(selected.health?.chronic_illness_details) }}</small></section>
    <section><span>Actividad física</span><strong>{{ selected.health?.fit_for_physical_education ? 'Apta' : 'Revisar antecedente' }}</strong><small>{{ value(selected.health?.physical_restrictions_details) }}</small></section>
    <section class="wide"><span>Medicamentos contraindicados</span><strong>{{ value(selected.health?.contraindicated_medications) }}</strong></section>
    <section class="wide"><span>Observaciones de salud</span><strong>{{ value(selected.health?.observations) }}</strong></section>
  </div><div class="source-note"><i class="bx bx-info-circle"></i> Para corregir estos datos, edita la ficha de la alumna en el módulo Estudiantes.</div></article></div></Teleport>
</template>

<style scoped>
.health-card{background:#fff;border:1px solid #e0e8ef;border-radius:18px;box-shadow:0 18px 45px rgba(39,54,76,.07);overflow:hidden}.health-card>header{display:flex;align-items:center;gap:1rem;padding:1.3rem 1.5rem;background:linear-gradient(135deg,#f2fbf8,#f3f6ff)}.icon{display:grid;place-items:center;width:48px;height:48px;border-radius:15px;background:#fff;color:#21826d;font-size:1.5rem;box-shadow:0 8px 22px rgba(33,130,109,.13)}header span{font-size:.67rem;text-transform:uppercase;letter-spacing:.1em;color:#4f897d;font-weight:800}.health-card h2,.health-detail h2{font-size:1.2rem;margin:.12rem 0;color:#253449}.health-card header p,.health-detail header p{margin:0;color:#748195}.sync{margin-left:auto;color:#227a66;background:#dcf5ed;border-radius:999px;padding:.4rem .65rem;font-size:.73rem}.filters{display:flex;align-items:center;gap:.75rem;padding:1rem 1.5rem;border-bottom:1px solid #edf1f4}.filters .input-group{max-width:480px}.filters label{display:flex;align-items:center;gap:.4rem;color:#536174;font-size:.8rem;white-space:nowrap}thead th{font-size:.64rem;text-transform:uppercase;letter-spacing:.06em;color:#738094;padding:.75rem}tbody td{padding:.8rem .75rem}td strong,td small{display:block}td small,.muted{color:#8591a0}.device{display:inline-block;background:#e9f4ff;color:#2a658c;padding:.22rem .45rem;border-radius:999px;font-size:.7rem;margin:.1rem}.alert-state{display:inline-flex;gap:.28rem;align-items:center;color:#288069;font-size:.73rem}.alert-state.active{color:#b03b4d;font-weight:700}.empty,.loading{padding:2rem;text-align:center;color:#728096}.health-card>footer{display:flex;justify-content:flex-end;align-items:center;gap:.7rem;padding:1rem}.health-overlay{position:fixed;inset:0;z-index:1090;background:rgba(15,23,42,.58);backdrop-filter:blur(5px);display:grid;place-items:center;padding:1rem}.health-detail{width:min(820px,96vw);background:#fff;padding:1.4rem;border-radius:20px;box-shadow:0 28px 80px rgba(15,23,42,.3)}.health-detail>header{display:flex;justify-content:space-between;margin-bottom:1rem}.health-detail>header button{border:0;background:#f1f5f9;border-radius:50%;width:36px;height:36px;font-size:1.4rem}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}.detail-grid section{padding:.9rem;border:1px solid #e7edf2;border-radius:12px;background:#fbfcfd}.detail-grid section.wide{grid-column:1/-1}.detail-grid span,.detail-grid strong,.detail-grid small{display:block}.detail-grid small{color:#748195;margin-top:.2rem}.source-note{margin-top:1rem;padding:.8rem;border-radius:12px;background:#eef8ff;color:#42647b}@media(max-width:800px){.filters{flex-wrap:wrap}.sync{display:none}.detail-grid{grid-template-columns:1fr}.detail-grid section.wide{grid-column:auto}}
</style>
