<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useSocialWork } from '../../composables/useSocialWork'

const props = defineProps({ catalogs: { type: Object, default: () => ({}) } })
const emit = defineEmits(['open-case'])
const api = reactive(useSocialWork())
const referrals = ref([])
const students = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const status = ref('')
const modal = ref('')
const selected = ref(null)
const studentQuery = ref('')
const success = ref('')
const canManage = computed(() => api.can('social_work.referrals.manage'))
const today = new Date().toISOString().slice(0, 10)
const form = reactive({
  student_profile_id: '', course_section_id: '', referral_date: today, source_unit: 'Inspectoría',
  source_person: '', reason: '', description: '', observed_background: '', previous_actions: '',
  urgency: 'normal', immediate_risk: false, contact_data: '', status: 'enviada', confidentiality: 'restringido',
})
const conversion = reactive({ title: '', priority: 'media', risk_level: 'sin_evaluar', responsible_user_id: '' })

const name = student => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')
const label = value => String(value || '—').replaceAll('_', ' ')
const date = value => {
  if (!value) return '—'
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value
  return new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium' }).format(new Date(normalized))
}
const studentOptions = computed(() => students.value.map(student => ({
  ...student,
  optionLabel: `${name(student)} · ${student.rut || 'Sin RUT'}${student.course ? ` · ${student.course}` : ''}`,
})))

watch(studentQuery, value => {
  const match = studentOptions.value.find(student => student.optionLabel === value)
  form.student_profile_id = match?.id || ''
  form.course_section_id = match?.course_section_id || ''
})

async function loadStudents() {
  try { students.value = (await api.get('/referral-students')).data || [] } catch { students.value = [] }
}

async function load(page = 1) {
  const query = new URLSearchParams({ page, per_page: 20 })
  if (search.value) query.set('search', search.value)
  if (status.value) query.set('status', status.value)
  try {
    const response = await api.get(`/referrals?${query}`)
    referrals.value = response.data || []
    pagination.value = { current_page: response.current_page, last_page: response.last_page, total: response.total }
  } catch { referrals.value = [] }
}

function openCreate() {
  selected.value = null
  modal.value = 'create'
}

function openDetail(item) {
  selected.value = item
  modal.value = 'detail'
}

function openConversion(item) {
  selected.value = item
  conversion.title = `Atención social · ${item.reason}`
  conversion.priority = item.urgency === 'urgente' ? 'urgente' : item.urgency === 'alta' ? 'alta' : 'media'
  conversion.risk_level = item.immediate_risk ? 'alto' : 'sin_evaluar'
  conversion.responsible_user_id = ''
  modal.value = 'convert'
}

function closeModal() { modal.value = ''; selected.value = null }

async function submitReferral() {
  if (!form.student_profile_id) { api.error = 'Selecciona una alumna desde las sugerencias del buscador.'; return }
  try {
    await api.post('/referrals', {
      ...form,
      student_profile_id: Number(form.student_profile_id),
      course_section_id: form.course_section_id ? Number(form.course_section_id) : null,
    })
    success.value = 'Derivación enviada a la bandeja de Trabajo Social.'
    closeModal()
    studentQuery.value = ''; form.student_profile_id = ''; form.course_section_id = ''; form.reason = ''
    form.description = ''; form.observed_background = ''; form.previous_actions = ''; form.contact_data = ''
    await load(1)
  } catch {}
}

async function convertToCase() {
  if (!selected.value || !conversion.responsible_user_id) return
  try {
    const response = await api.post(`/referrals/${selected.value.id}/convert-to-case`, {
      ...conversion, responsible_user_id: Number(conversion.responsible_user_id),
    })
    success.value = 'Derivación recibida y convertida en caso social.'
    const caseId = response.data.id
    closeModal(); await load(pagination.value.current_page)
    emit('open-case', caseId)
  } catch {}
}

onMounted(() => Promise.allSettled([loadStudents(), load()]))
</script>

<template>
  <section class="referral-card">
    <header class="referral-hero">
      <div><span class="eyebrow">Canal seguro interáreas</span><h2>Bandeja de derivaciones</h2><p>{{ canManage ? 'Recibe, revisa y convierte solicitudes en casos sociales.' : 'Consulta las derivaciones enviadas por ti o asignadas directamente a tu atención.' }}</p></div>
      <div class="hero-actions"><div class="count"><strong>{{ pagination.total }}</strong><span>{{ canManage ? 'recibidas' : 'visibles' }}</span></div><button class="btn btn-primary" @click="openCreate"><i class="bx bx-send"></i> Nueva derivación</button></div>
    </header>
    <div v-if="success" class="alert alert-success mx-4 mt-3 mb-0"><i class="bx bx-check-circle"></i> {{ success }}</div>
    <div v-if="api.error" class="alert alert-danger mx-4 mt-3 mb-0"><i class="bx bx-error-circle"></i> {{ api.error }}</div>
    <div class="filters">
      <div class="input-group"><span class="input-group-text"><i class="bx bx-search"></i></span><input v-model="search" class="form-control" placeholder="Buscar alumna, RUT, motivo o unidad" @keyup.enter="load(1)"></div>
      <select v-model="status" class="form-select" @change="load(1)"><option value="">Todos los estados</option><option value="enviada">Enviada</option><option value="recibida">Recibida</option><option value="en_revision">En revisión</option><option value="antecedentes_pendientes">Antecedentes pendientes</option><option value="convertida_caso">Convertida en caso</option><option value="rechazada">No acogida</option></select>
      <button class="btn btn-outline-primary" @click="load(1)">Actualizar</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Folio</th><th>Alumna</th><th>Origen / motivo</th><th>Destinataria</th><th>Urgencia</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
        <tbody>
          <tr v-for="item in referrals" :key="item.id">
            <td><strong>DER-{{ String(item.id).padStart(5, '0') }}</strong><small>{{ item.creator?.name || 'Usuario derivante' }}</small></td>
            <td><strong>{{ name(item.student) }}</strong><small>{{ item.student?.rut || 'Sin RUT' }} · {{ item.course_section?.display_name || 'Sin curso' }}</small></td>
            <td><strong>{{ item.source_unit }}</strong><small>{{ item.reason }}</small></td>
            <td><strong>{{ item.assigned_user?.name || 'Bandeja general' }}</strong><small>{{ item.assigned_user ? 'Asignación directa' : 'Sin profesional asignada' }}</small></td>
            <td><span class="urgency" :class="item.urgency"><i v-if="item.immediate_risk" class="bx bx-error-circle"></i>{{ label(item.urgency) }}</span></td>
            <td><span class="state" :class="item.status">{{ label(item.status) }}</span></td>
            <td>{{ date(item.referral_date) }}</td>
            <td class="text-end text-nowrap"><button class="btn btn-sm btn-light" @click="openDetail(item)">Revisar</button> <button v-if="canManage && !item.case_id" class="btn btn-sm btn-outline-primary" @click="openConversion(item)">Crear caso</button><button v-else-if="item.case_id && canManage" class="btn btn-sm btn-outline-primary" @click="emit('open-case', item.case_id)">Abrir caso</button></td>
          </tr>
          <tr v-if="!referrals.length && !api.loading"><td colspan="8" class="empty"><i class="bx bx-inbox"></i><strong>Sin derivaciones</strong><span>No hay solicitudes para los filtros seleccionados.</span></td></tr>
        </tbody>
      </table>
    </div>
    <div v-if="api.loading" class="loading"><span class="spinner-border spinner-border-sm"></span> Cargando bandeja autorizada…</div>
    <footer v-if="pagination.last_page > 1"><button class="btn btn-sm btn-light" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button><span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span><button class="btn btn-sm btn-light" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</button></footer>
  </section>

  <Teleport to="body">
    <div v-if="modal" class="modal-overlay" role="dialog" aria-modal="true" @click.self="closeModal">
      <article class="modal-panel" :class="{ compact: modal !== 'create' }">
        <header><div><span class="eyebrow">{{ modal === 'create' ? 'Solicitud confidencial' : modal === 'convert' ? 'Recepción profesional' : 'Detalle de derivación' }}</span><h2>{{ modal === 'create' ? 'Nueva derivación a Trabajo Social' : modal === 'convert' ? 'Convertir derivación en caso' : `DER-${String(selected?.id || '').padStart(5, '0')}` }}</h2></div><button type="button" @click="closeModal"><i class="bx bx-x"></i></button></header>
        <form v-if="modal === 'create'" class="modal-content" @submit.prevent="submitReferral">
          <section class="form-section"><h3><span>1</span> Alumna y procedencia</h3><div class="row g-3">
            <div class="col-md-8"><label>Buscar alumna *</label><input v-model="studentQuery" list="referral-student-options" class="form-control" placeholder="Escribe nombre, RUT o curso" autocomplete="off" required><datalist id="referral-student-options"><option v-for="student in studentOptions" :key="student.id" :value="student.optionLabel"></option></datalist><small class="hint">Selecciona una coincidencia exacta de la lista.</small></div>
            <div class="col-md-4"><label>Fecha *</label><input v-model="form.referral_date" type="date" class="form-control" required></div>
            <div class="col-md-6"><label>Unidad derivante *</label><select v-model="form.source_unit" class="form-select" required><option>Inspectoría</option><option>Docencia</option><option>Dirección</option><option>Convivencia Escolar</option><option>Enfermería</option><option>PIE</option><option>Otro</option></select></div>
            <div class="col-md-6"><label>Persona de contacto</label><input v-model="form.source_person" class="form-control" maxlength="255" placeholder="Nombre y cargo"></div>
          </div></section>
          <section class="form-section"><h3><span>2</span> Antecedentes necesarios</h3><div class="row g-3">
            <div class="col-12"><label>Motivo de derivación *</label><input v-model="form.reason" class="form-control" maxlength="255" placeholder="Síntesis clara del motivo" required></div>
            <div class="col-12"><label>Descripción objetiva</label><textarea v-model="form.description" class="form-control" rows="3" placeholder="Describe hechos observables, evitando diagnósticos o juicios."></textarea></div>
            <div class="col-md-6"><label>Antecedentes observados</label><textarea v-model="form.observed_background" class="form-control" rows="3"></textarea></div>
            <div class="col-md-6"><label>Acciones previas realizadas</label><textarea v-model="form.previous_actions" class="form-control" rows="3"></textarea></div>
          </div></section>
          <section class="form-section"><h3><span>3</span> Priorización y contacto</h3><div class="row g-3 align-items-end">
            <div class="col-md-4"><label>Urgencia propuesta *</label><select v-model="form.urgency" class="form-select"><option value="baja">Baja</option><option value="normal">Normal</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div>
            <div class="col-md-5"><label>Datos para contacto</label><input v-model="form.contact_data" class="form-control" placeholder="Teléfono, correo o disponibilidad"></div>
            <div class="col-md-3"><label class="risk-check"><input v-model="form.immediate_risk" type="checkbox"> Riesgo inmediato</label></div>
          </div></section>
          <div class="privacy-note"><i class="bx bx-lock-alt"></i><span>La derivación será visible para Trabajo Social. Enviarla no concede acceso al caso que pueda originarse.</span></div>
          <footer><button type="button" class="btn btn-light" @click="closeModal">Cancelar</button><button class="btn btn-primary" :disabled="api.loading || !form.student_profile_id"><i class="bx bx-send"></i> Enviar derivación</button></footer>
        </form>
        <div v-else-if="modal === 'detail'" class="modal-content detail">
          <div class="detail-student"><div class="avatar">{{ name(selected?.student).slice(0, 1) }}</div><div><strong>{{ name(selected?.student) }}</strong><span>{{ selected?.student?.rut }} · {{ selected?.course_section?.display_name || 'Sin curso' }}</span></div><span class="state" :class="selected?.status">{{ label(selected?.status) }}</span></div>
          <dl><div><dt>Origen</dt><dd>{{ selected?.source_unit }} · {{ selected?.source_person || selected?.creator?.name || 'Sin contacto' }}</dd></div><div><dt>Destinataria</dt><dd>{{ selected?.assigned_user?.name || 'Bandeja general de Trabajo Social' }}</dd></div><div><dt>Motivo</dt><dd>{{ selected?.reason }}</dd></div><div><dt>Descripción</dt><dd>{{ selected?.description || 'Sin descripción adicional.' }}</dd></div><div><dt>Antecedentes observados</dt><dd>{{ selected?.observed_background || 'No informados.' }}</dd></div><div><dt>Acciones previas</dt><dd>{{ selected?.previous_actions || 'No informadas.' }}</dd></div></dl>
          <footer><button class="btn btn-light" @click="closeModal">Cerrar</button><button v-if="canManage && !selected?.case_id" class="btn btn-primary" @click="openConversion(selected)">Convertir en caso</button></footer>
        </div>
        <form v-else class="modal-content" @submit.prevent="convertToCase">
          <div class="conversion-callout"><i class="bx bx-transfer-alt"></i><div><strong>{{ name(selected?.student) }}</strong><span>{{ selected?.reason }}</span></div></div>
          <label>Título del caso *</label><input v-model="conversion.title" class="form-control" required maxlength="255">
          <div class="row g-3"><div class="col-md-6"><label>Prioridad *</label><select v-model="conversion.priority" class="form-select"><option v-for="item in props.catalogs.priorities || ['baja','media','alta','urgente']" :key="item" :value="item">{{ label(item) }}</option></select></div><div class="col-md-6"><label>Riesgo inicial *</label><select v-model="conversion.risk_level" class="form-select"><option v-for="item in props.catalogs.risk_levels || ['sin_evaluar','bajo','medio','alto','critico']" :key="item" :value="item">{{ label(item) }}</option></select></div></div>
          <label>Profesional responsable *</label><select v-model="conversion.responsible_user_id" class="form-select" required><option value="">Selecciona responsable</option><option v-for="user in props.catalogs.professionals || []" :key="user.id" :value="user.id">{{ user.name }}</option></select>
          <footer><button type="button" class="btn btn-light" @click="closeModal">Cancelar</button><button class="btn btn-primary" :disabled="api.loading || !conversion.responsible_user_id">Crear caso social</button></footer>
        </form>
      </article>
    </div>
  </Teleport>
</template>

<style scoped>
.referral-card{background:#fff;border:1px solid #dfe7ef;border-radius:20px;box-shadow:0 18px 48px rgba(39,48,77,.075);overflow:hidden}.referral-hero{display:flex;justify-content:space-between;align-items:center;gap:1.5rem;padding:1.45rem 1.6rem;background:radial-gradient(circle at 92% 0,#eee9ff 0,transparent 38%),linear-gradient(135deg,#fff,#f7fbff)}.eyebrow{font-size:.67rem;text-transform:uppercase;letter-spacing:.13em;color:#735c9a;font-weight:800}.referral-hero h2,.modal-panel h2{font-size:1.22rem;margin:.18rem 0;color:#24324a}.referral-hero p{margin:0;color:#718096}.hero-actions{display:flex;align-items:center;gap:.8rem}.count{display:flex;flex-direction:column;align-items:center;min-width:74px;padding:.55rem .8rem;border:1px solid #e1daf1;border-radius:13px;background:rgba(255,255,255,.72);color:#604b86}.count strong{font-size:1.25rem}.count span{font-size:.62rem;text-transform:uppercase}.filters{display:flex;gap:.65rem;padding:1rem 1.5rem;background:#f8fafc;border-block:1px solid #edf1f5}.filters .input-group{max-width:520px}.filters .form-select{max-width:230px}thead th{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#718096;padding:.75rem 1rem}tbody td{padding:.82rem 1rem}td strong,td small{display:block}td small{color:#8290a0}.urgency,.state{display:inline-flex;align-items:center;gap:.25rem;padding:.27rem .55rem;border-radius:999px;background:#eef2f7;color:#596779;font-size:.7rem;text-transform:capitalize;font-weight:700}.urgency.alta{background:#fff1d7;color:#875b00}.urgency.urgente{background:#ffe4e8;color:#a12c42}.state.convertida_caso{background:#e4f7ee;color:#15704e}.state.enviada,.state.en_revision{background:#e9efff;color:#3d579c}.empty{padding:2.5rem!important;text-align:center;color:#7a8797}.empty i,.empty strong,.empty span{display:block}.empty i{font-size:2rem;color:#9ba6b4}.loading{text-align:center;padding:1.5rem;color:#718096}.referral-card>footer{display:flex;justify-content:flex-end;align-items:center;gap:.8rem;padding:1rem;border-top:1px solid #edf1f5}.modal-overlay{position:fixed;inset:0;z-index:1095;background:rgba(15,23,42,.62);backdrop-filter:blur(6px);display:grid;place-items:center;padding:1rem}.modal-panel{width:min(940px,97vw);max-height:95vh;overflow:auto;background:#fff;border-radius:22px;box-shadow:0 30px 90px rgba(15,23,42,.35)}.modal-panel.compact{width:min(680px,96vw)}.modal-panel>header{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:center;padding:1.2rem 1.4rem;background:#fff;border-bottom:1px solid #e8edf3}.modal-panel>header button{border:0;background:#f1f5f9;width:37px;height:37px;border-radius:50%;font-size:1.35rem}.modal-content{padding:1.35rem}.form-section{padding:1rem;border:1px solid #e7ecf2;border-radius:15px;margin-bottom:.85rem}.form-section h3{font-size:.85rem;color:#334155;margin:0 0 .75rem}.form-section h3 span{display:inline-grid;place-items:center;width:23px;height:23px;margin-right:.35rem;border-radius:50%;background:#6a55a0;color:#fff;font-size:.7rem}label{display:block;font-size:.73rem;font-weight:700;color:#526174;margin-bottom:.3rem}.hint{display:block;color:#8390a0;margin-top:.25rem}.risk-check{display:flex;align-items:center;gap:.45rem;min-height:38px;margin:0;padding:.5rem .65rem;border-radius:10px;background:#fff1f3;color:#9a3447}.privacy-note,.conversion-callout{display:flex;align-items:center;gap:.65rem;padding:.75rem .85rem;border-radius:12px;background:#f2effa;color:#584773;font-size:.78rem}.privacy-note i,.conversion-callout i{font-size:1.25rem}.modal-content>footer{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1rem}.detail-student{display:flex;align-items:center;gap:.75rem;padding:1rem;background:linear-gradient(135deg,#edf8ff,#f6f0ff);border-radius:14px;margin-bottom:1rem}.detail-student>div:nth-child(2){flex:1}.detail-student strong,.detail-student span{display:block}.avatar{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#65508f;color:#fff;font-weight:800}.detail dl{display:grid;gap:.65rem}.detail dl>div{padding:.75rem;border:1px solid #e8edf3;border-radius:12px}.detail dt{font-size:.64rem;text-transform:uppercase;color:#7b8796}.detail dd{margin:.25rem 0 0;color:#334155;white-space:pre-wrap}.conversion-callout{margin-bottom:1rem}.conversion-callout div{display:flex;flex-direction:column}.conversion-callout span{color:#776a88}@media(max-width:800px){.referral-hero{align-items:flex-start;flex-direction:column}.hero-actions{width:100%;justify-content:space-between}.filters{flex-wrap:wrap}.modal-overlay{padding:.25rem}.modal-panel{max-height:98vh}}
</style>
