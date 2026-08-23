<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useSocialWork } from '../../composables/useSocialWork'

const props = defineProps({ catalogs: { type: Object, default: () => ({}) } })
const emit = defineEmits(['open-student'])
const api = reactive(useSocialWork())
const students = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const program = ref('')
const medicalAlert = ref(false)
const selected = ref(null)
const success = ref('')
const edit = reactive({
  is_pie_participant: false, pie_permanence_type: '', pie_diagnosis: '', sep_classification: 'none',
  junaeb: false, pro_retencion: false, external_program: false, external_program_name: '',
})

const name = student => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')
const label = value => String(value || '—').replaceAll('_', ' ')
const attendance = student => student?.attendance_profile?.latest || null
const attendanceRate = student => attendance(student)?.attendance_rate == null ? '—' : `${Number(attendance(student).attendance_rate).toFixed(1)}%`
const attendanceTone = student => { const value = Number(attendance(student)?.attendance_rate); return !Number.isFinite(value) ? 'empty' : value < 85 ? 'critical' : value < 90 ? 'warning' : 'ok' }

async function load(page = 1) {
  const query = new URLSearchParams({ page, per_page: 25 })
  if (search.value) query.set('search', search.value)
  if (program.value) query.set('program', program.value)
  if (medicalAlert.value) query.set('medical_alert', '1')
  try {
    const response = await api.get(`/support-matrix?${query}`)
    students.value = response.data || []
    pagination.value = { current_page: response.current_page, last_page: response.last_page, total: response.total }
  } catch { students.value = [] }
}

function course(student) { return student.current_enrollment?.course_section?.display_name || student.current_enrollment?.snapshot_course_display_name || 'Sin curso vigente' }
function showDetail(student) {
  selected.value = student
  Object.assign(edit, {
    is_pie_participant: Boolean(student.is_pie_participant),
    pie_permanence_type: student.pie_permanence_type || '',
    pie_diagnosis: student.pie_diagnosis || '',
    sep_classification: student.sep_classification || 'none',
    junaeb: Boolean(student.program_flags?.junaeb),
    pro_retencion: Boolean(student.program_flags?.pro_retencion),
    external_program: Boolean(student.program_flags?.external?.length),
    external_program_name: student.program_flags?.external?.[0] || '',
  })
}
async function saveSupportProfile() {
  if (!selected.value) return
  try {
    await api.put(`/support-matrix/${selected.value.id}`, edit)
    success.value = 'Situación social actualizada y registrada en auditoría.'
    selected.value = null
    await load(pagination.value.current_page)
  } catch {}
}
onMounted(load)
</script>

<template>
  <section class="matrix-card">
    <header>
      <div><span class="eyebrow">Visión 360°</span><h2>Matriz única de alumnas y apoyos</h2><p>JUNAEB, Pro Retención, programas externos, SEP, PIE y alertas en una sola vista.</p></div>
      <div class="count"><strong>{{ pagination.total }}</strong><span>alumnas</span></div>
    </header>
    <div v-if="api.error" class="alert alert-danger mx-4 mt-3 mb-0">{{ api.error }}</div>
    <div v-if="success" class="alert alert-success mx-4 mt-3 mb-0"><i class="bx bx-check-circle"></i> {{ success }}</div>
    <div class="filters">
      <div class="input-group"><span class="input-group-text"><i class="bx bx-search"></i></span><input v-model="search" class="form-control" placeholder="Buscar por nombre o RUT" @keyup.enter="load(1)"></div>
      <select v-model="program" class="form-select" @change="load(1)"><option value="">Todos los programas</option><option value="junaeb">JUNAEB</option><option value="pro_retencion">Pro Retención</option></select>
      <label class="check"><input v-model="medicalAlert" type="checkbox" @change="load(1)"> Solo alerta médica</label>
      <button class="btn btn-outline-primary" @click="load(1)">Actualizar</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Alumna</th><th>Curso</th><th>Asistencia</th><th>JUNAEB</th><th>Pro Retención</th><th>Otro programa</th><th>SEP</th><th>Protección / PIE</th><th>Alerta médica</th><th></th></tr></thead>
        <tbody>
          <tr v-for="student in students" :key="student.id">
            <td><strong>{{ name(student) }}</strong><small>{{ student.rut || 'Sin RUT' }}</small></td>
            <td>{{ course(student) }}</td>
            <td><span class="attendance-chip" :class="attendanceTone(student)">{{ attendanceRate(student) }}</span><small v-if="attendance(student)">{{ attendance(student).period }}</small></td>
            <td><span class="flag" :class="student.program_flags?.junaeb ? 'yes' : 'no'">{{ student.program_flags?.junaeb ? 'Sí' : 'No' }}</span></td>
            <td><span class="flag" :class="student.program_flags?.pro_retencion ? 'yes' : 'no'">{{ student.program_flags?.pro_retencion ? 'Sí' : 'No' }}</span></td>
            <td><span v-if="student.program_flags?.external?.length" class="tag">{{ student.program_flags.external.join(', ') }}</span><span v-else class="muted">—</span></td>
            <td><span v-if="student.sep_classification" class="tag sep">{{ label(student.sep_classification) }}</span><span v-else class="muted">—</span></td>
            <td><div class="compact-flags"><span v-if="student.protection_summary?.has_measure" class="tag protection"><i class="bx bx-shield-quarter"></i> Medida</span><span v-if="student.is_pie_participant" class="tag pie">PIE</span><span v-if="!student.protection_summary?.has_measure && !student.is_pie_participant" class="muted">—</span></div></td>
            <td><span class="medical" :class="{ active: student.medical_alert }"><i class="bx" :class="student.medical_alert ? 'bx-plus-medical' : 'bx-check'"></i> {{ student.medical_alert ? 'Revisar' : 'Sin alerta' }}</span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-primary" @click="showDetail(student)">{{ api.can('social_work.student_profile.update') ? 'Gestionar' : 'Resumen' }}</button></td>
          </tr>
          <tr v-if="!students.length && !api.loading"><td colspan="10" class="empty">No hay alumnas para los filtros seleccionados.</td></tr>
        </tbody>
      </table>
    </div>
    <div v-if="api.loading" class="loading"><span class="spinner-border spinner-border-sm"></span> Consolidando información autorizada…</div>
    <footer v-if="pagination.last_page > 1"><button class="btn btn-sm btn-light" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button><span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span><button class="btn btn-sm btn-light" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</button></footer>
  </section>

  <Teleport to="body">
    <div v-if="selected" class="detail-overlay" @click.self="selected = null">
      <article class="detail-panel">
        <header><div><span class="eyebrow">Resumen de apoyos</span><h2>{{ name(selected) }}</h2><p>{{ selected.rut }} · {{ course(selected) }}</p></div><button @click="selected = null"><i class="bx bx-x"></i></button></header>
        <div class="detail-grid">
          <section><i class="bx bx-package"></i><div><span>JUNAEB</span><strong>{{ selected.program_flags?.junaeb ? 'Incorporada' : 'Sin registro vigente' }}</strong><small>{{ selected.junaeb_benefits?.map(item => item.benefit_type?.name).filter(Boolean).join(', ') || 'Sin prestaciones' }}</small></div></section>
          <section><i class="bx bx-network-chart"></i><div><span>Otros programas</span><strong>{{ selected.program_flags?.pro_retencion ? 'Pro Retención' : 'Sin Pro Retención' }}</strong><small>{{ selected.program_flags?.external?.join(', ') || 'Sin programa externo' }}</small></div></section>
          <section><i class="bx bx-shield-quarter"></i><div><span>Protección e inclusión</span><strong>{{ selected.sep_classification ? `SEP ${label(selected.sep_classification)}` : 'Sin clasificación SEP' }}</strong><small>{{ selected.is_pie_participant ? `PIE · ${label(selected.pie_permanence_type)}` : 'Sin participación PIE' }}</small></div></section>
          <section><i class="bx bx-line-chart"></i><div><span>Asistencia mensual</span><strong>{{ attendanceRate(selected) }}</strong><small>{{ attendance(selected) ? `${attendance(selected).period} · ${attendance(selected).present_days} presentes y ${attendance(selected).absent_days} ausentes` : 'Sin reporte mensual asociado' }}</small></div></section>
          <section><i class="bx bx-plus-medical"></i><div><span>Salud</span><strong>{{ selected.medical_alert ? 'Requiere revisión' : 'Sin alertas detectadas' }}</strong><small>{{ selected.health?.observations || selected.health?.chronic_illness_details || 'Sin observaciones maestras' }}</small></div></section>
        </div>
        <form v-if="api.can('social_work.student_profile.update')" class="support-editor" @submit.prevent="saveSupportProfile">
          <div class="editor-title"><div><span class="eyebrow">Actualización autorizada</span><h3>Gestionar situación social</h3></div><span>Año académico vigente</span></div>
          <div class="toggle-grid">
            <label class="toggle-card" :class="{ active: edit.is_pie_participant }"><input v-model="edit.is_pie_participant" type="checkbox"><i class="bx bx-universal-access"></i><span><strong>Programa PIE</strong><small>Participación vigente</small></span><i class="bx bx-check-circle"></i></label>
            <label class="toggle-card" :class="{ active: edit.junaeb }"><input v-model="edit.junaeb" type="checkbox"><i class="bx bx-package"></i><span><strong>JUNAEB</strong><small>Programa o beneficio vigente</small></span><i class="bx bx-check-circle"></i></label>
            <label class="toggle-card" :class="{ active: edit.pro_retencion }"><input v-model="edit.pro_retencion" type="checkbox"><i class="bx bx-shield"></i><span><strong>Pro Retención</strong><small>Incorporación vigente</small></span><i class="bx bx-check-circle"></i></label>
            <label class="toggle-card" :class="{ active: edit.external_program }"><input v-model="edit.external_program" type="checkbox"><i class="bx bx-network-chart"></i><span><strong>Programa externo</strong><small>Apoyo de otra institución</small></span><i class="bx bx-check-circle"></i></label>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-4"><label>Clasificación SEP</label><select v-model="edit.sep_classification" class="form-select"><option value="none">Sin clasificación</option><option value="prioritaria">Prioritaria</option><option value="preferente">Preferente</option><option value="pendiente_validacion">Pendiente de validación</option></select></div>
            <div v-if="edit.is_pie_participant" class="col-md-4"><label>Permanencia PIE</label><select v-model="edit.pie_permanence_type" class="form-select"><option value="">Sin especificar</option><option value="permanente">Permanente</option><option value="transitoria">Transitoria</option></select></div>
            <div v-if="edit.external_program" class="col-md-4"><label>Nombre del programa externo</label><input v-model="edit.external_program_name" class="form-control" maxlength="255" placeholder="Ej. Programa municipal"></div>
            <div v-if="edit.is_pie_participant" class="col-12"><label>Antecedente PIE</label><textarea v-model="edit.pie_diagnosis" class="form-control" rows="2" placeholder="Antecedente institucional pertinente"></textarea></div>
          </div>
          <div class="editor-actions"><button type="button" class="btn btn-light" @click="emit('open-student', selected.id)">Abrir ficha completa</button><button class="btn btn-primary" :disabled="api.loading"><i class="bx bx-save"></i> Guardar cambios</button></div>
        </form>
        <button v-else class="btn btn-primary w-100" @click="emit('open-student', selected.id)">Abrir ficha social completa</button>
      </article>
    </div>
  </Teleport>
</template>

<style scoped>
.matrix-card{background:#fff;border:1px solid #e1e8ef;border-radius:18px;box-shadow:0 18px 45px rgba(42,49,78,.07);overflow:hidden}.matrix-card>header{padding:1.35rem 1.5rem;display:flex;justify-content:space-between;gap:1rem;align-items:center}.eyebrow{font-size:.68rem;text-transform:uppercase;letter-spacing:.12em;color:#735c9a;font-weight:800}.matrix-card h2,.detail-panel h2{font-size:1.2rem;margin:.15rem 0;color:#24324a}.matrix-card header p,.detail-panel header p{margin:0;color:#748197}.count{display:flex;flex-direction:column;align-items:center;min-width:86px;padding:.7rem 1rem;background:#f2edfb;border-radius:14px;color:#604b86}.count strong{font-size:1.4rem}.count span{font-size:.7rem;text-transform:uppercase}.filters{display:flex;align-items:center;gap:.65rem;padding:1rem 1.5rem;background:#f8fafc;border-block:1px solid #edf1f5}.filters .input-group{max-width:460px}.filters .form-select{max-width:210px}.check{display:flex;align-items:center;gap:.4rem;color:#536174;font-size:.8rem;white-space:nowrap}thead th{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#718096;background:#fff;padding:.75rem}tbody td{padding:.78rem .75rem}td strong,td small{display:block}td small,.muted{color:#8a95a4}.flag{display:inline-grid;place-items:center;min-width:38px;padding:.25rem .45rem;border-radius:999px;font-size:.72rem;font-weight:800}.flag.yes{background:#e6f8ef;color:#167451}.flag.no{background:#f1f4f7;color:#84909d}.tag{display:inline-block;background:#eef2ff;color:#51417d;border-radius:999px;padding:.26rem .52rem;font-size:.7rem}.tag.sep{background:#fff4d6;color:#87630b}.tag.protection{background:#ffe8ec;color:#9f3347}.tag.pie{background:#e6f4ff;color:#18638c}.compact-flags{display:flex;gap:.25rem;flex-wrap:wrap}.medical{display:inline-flex;align-items:center;gap:.3rem;color:#56846e;font-size:.74rem}.medical.active{color:#b13f50;font-weight:700}.empty,.loading{text-align:center;color:#728096;padding:2rem}.matrix-card>footer{display:flex;justify-content:flex-end;align-items:center;gap:.75rem;padding:1rem;border-top:1px solid #edf1f5}.detail-overlay{position:fixed;inset:0;z-index:1090;background:rgba(15,23,42,.58);backdrop-filter:blur(5px);display:grid;place-items:center;padding:1rem}.detail-panel{width:min(860px,96vw);max-height:94vh;overflow:auto;background:#fff;border-radius:20px;padding:1.35rem;box-shadow:0 28px 80px rgba(15,23,42,.3)}.detail-panel>header{display:flex;justify-content:space-between;margin-bottom:1rem}.detail-panel>header button{border:0;background:#f1f5f9;border-radius:50%;width:36px;height:36px;font-size:1.4rem}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem}.detail-grid section{display:flex;gap:.75rem;padding:1rem;border:1px solid #e8edf3;border-radius:14px;background:#fbfcfe}.detail-grid section>i{font-size:1.4rem;color:#6d5798}.detail-grid span,.detail-grid strong,.detail-grid small{display:block}.detail-grid span{font-size:.65rem;text-transform:uppercase;color:#7a8797}.detail-grid small{color:#748196;margin-top:.2rem}.support-editor{border:1px solid #ded6ed;border-radius:16px;background:#faf8fd;padding:1rem}.editor-title{display:flex;justify-content:space-between;align-items:flex-start}.editor-title h3{font-size:1rem;margin:.15rem 0}.editor-title>span{font-size:.68rem;color:#76678c;background:#eee9f6;padding:.28rem .5rem;border-radius:999px}.toggle-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:.8rem}.toggle-card{position:relative;display:flex;align-items:center;gap:.6rem;margin:0;padding:.7rem;border:1px solid #e2e7ed;border-radius:12px;background:#fff;cursor:pointer}.toggle-card input{position:absolute;opacity:0}.toggle-card>i:first-of-type{font-size:1.3rem;color:#778497}.toggle-card>span{flex:1}.toggle-card strong,.toggle-card small{display:block}.toggle-card small{font-size:.68rem;color:#8490a0}.toggle-card>i:last-child{opacity:0;color:#17835d}.toggle-card.active{border-color:#a694cb;background:#f5f0ff}.toggle-card.active>i:first-of-type{color:#68509a}.toggle-card.active>i:last-child{opacity:1}.support-editor label:not(.toggle-card){display:block;font-size:.72rem;font-weight:700;color:#536174;margin-bottom:.3rem}.editor-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1rem}@media(max-width:900px){.filters{flex-wrap:wrap}.matrix-card>header{align-items:flex-start}.detail-grid,.toggle-grid{grid-template-columns:1fr}}
.attendance-chip{display:inline-flex;padding:.25rem .45rem;border-radius:999px;background:#edf1f5;color:#758192;font-size:.7rem;font-weight:800}.attendance-chip.ok{background:#e7f6ee;color:#187052}.attendance-chip.warning{background:#fff4dc;color:#926515}.attendance-chip.critical{background:#fbeaec;color:#b42332}
</style>
