<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useSocialWork } from '../../composables/useSocialWork'
import { downloadSocialCaseMaster } from '../../utils/social-work-export'
import RiskBadge from './risk-badge.vue'

const props = defineProps({
  students: { type: Array, default: () => [] },
  catalogs: { type: Object, default: () => ({}) },
  initialCaseId: { type: [String, Number], default: null },
  openCreate: { type: Boolean, default: false },
})
const emit = defineEmits(['route-reset'])

const api = reactive(useSocialWork())
const cases = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const status = ref('')
const risk = ref('')
const activeCase = ref(null)
const modal = ref('')
const attentionReturn = ref('')
const success = ref('')
const studentQuery = ref('')
const staffSearch = ref('')
const form = reactive({
  primary_student_id: '', student_ids: [], title: '', reason: '', origin: 'derivacion',
  case_type: 'general', priority: 'media', risk_level: 'sin_evaluar', confidentiality: 'restringido',
  status: 'borrador', responsible_user_id: '', initial_description: '', initial_safeguards: '',
  next_milestone: '', due_at: '',
})
const intervention = reactive({
  kind: 'entrevista', type: 'seguimiento', activity_date: new Date().toISOString().slice(0, 10),
  objective: '', description: '', result: '', agreements: '', next_action: '', due_at: '',
  status: 'finalizada', confidentiality: 'restringido', participant_types: ['student'],
  participant_staff_ids: [], support_staff_ids: [], commitments: [],
})

const title = computed(() => activeCase.value ? `${activeCase.value.code} · ${activeCase.value.title}` : 'Ficha de atención')
const name = student => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')
const studentOptions = computed(() => props.students.map(student => ({ ...student, optionLabel: `${name(student)} · ${student.rut || 'Sin RUT'}${student.current_enrollment?.course_section?.display_name ? ` · ${student.current_enrollment.course_section.display_name}` : ''}` })))
const selectedStudent = computed(() => props.students.find(student => Number(student.id) === Number(form.primary_student_id)))
const canRegisterAttention = computed(() => api.can('social_work.actions.manage') || api.can('social_work.interviews.manage'))
const canSubmitAttention = computed(() => intervention.objective.trim()
  && intervention.participant_types.length > 0
  && (!intervention.participant_types.includes('staff') || intervention.participant_staff_ids.length > 0))
const guardianName = computed(() => activeCase.value?.student?.guardian_name || 'Apoderado/a de la estudiante')
const filteredStaff = computed(() => {
  const query = staffSearch.value.trim().toLocaleLowerCase('es')
  if (!query) return props.catalogs.professionals || []
  return (props.catalogs.professionals || []).filter(person => `${person.name} ${person.role || ''}`.toLocaleLowerCase('es').includes(query))
})
const label = value => String(value || '—').replaceAll('_', ' ')
const date = value => {
  if (!value) return '—'
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value
  return new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium' }).format(new Date(normalized))
}

async function load(page = 1) {
  const query = new URLSearchParams({ page, per_page: 20 })
  if (search.value) query.set('search', search.value)
  if (status.value) query.set('status', status.value)
  if (risk.value) query.set('risk_level', risk.value)
  try {
    const response = await api.get(`/cases?${query}`)
    cases.value = response.data || []
    pagination.value = { current_page: response.current_page, last_page: response.last_page, total: response.total }
  } catch { cases.value = [] }
}

async function openCase(id) {
  attentionReturn.value = ''
  modal.value = 'case'
  activeCase.value = null
  try { activeCase.value = (await api.get(`/cases/${id}`)).data } catch { modal.value = '' }
}

function resetIntervention() {
  Object.assign(intervention, {
    kind: 'entrevista', type: 'seguimiento', activity_date: new Date().toISOString().slice(0, 10),
    objective: '', description: '', result: '', agreements: '', next_action: '', due_at: '',
    status: 'finalizada', confidentiality: 'restringido', participant_types: ['student'],
    participant_staff_ids: [], support_staff_ids: [], commitments: [],
  })
  staffSearch.value = ''
}

function hasParticipant(type) { return intervention.participant_types.includes(type) }
function toggleParticipant(type) {
  const wasSelected = hasParticipant(type)
  intervention.participant_types = wasSelected
    ? intervention.participant_types.filter(item => item !== type)
    : [...intervention.participant_types, type]
  if (type === 'staff' && wasSelected) intervention.participant_staff_ids = []
}
function hasStaff(field, id) { return intervention[field].some(item => Number(item) === Number(id)) }
function toggleStaff(field, id) {
  intervention[field] = hasStaff(field, id)
    ? intervention[field].filter(item => Number(item) !== Number(id))
    : [...intervention[field], Number(id)]
}
function peopleFor(item, role) {
  return (item?.participants || [])
    .filter(person => typeof person === 'object' && (person.role || 'participant') === role)
    .map(person => `${person.name || label(person.type)}${person.position ? ` (${person.position})` : ''}`)
}

async function openAttention(id, returnToHistory = false) {
  resetIntervention()
  attentionReturn.value = returnToHistory ? 'case' : ''
  modal.value = 'attention'
  if (activeCase.value && Number(activeCase.value.id) === Number(id)) return
  activeCase.value = null
  try { activeCase.value = (await api.get(`/cases/${id}`)).data } catch { modal.value = '' }
}

function openCreateModal() { modal.value = 'create' }
function closeModal() {
  modal.value = ''; activeCase.value = null; attentionReturn.value = ''
  if (props.openCreate || props.initialCaseId) emit('route-reset')
}
function cancelAttention() {
  if (attentionReturn.value === 'case' && activeCase.value) {
    modal.value = 'case'; attentionReturn.value = ''
    return
  }
  closeModal()
}

async function createCase() {
  if (!form.primary_student_id) { api.error = 'Selecciona una alumna desde las sugerencias del buscador.'; return }
  const payload = {
    ...form,
    primary_student_id: Number(form.primary_student_id),
    responsible_user_id: form.responsible_user_id ? Number(form.responsible_user_id) : null,
    due_at: form.due_at || null,
  }
  try {
    const response = await api.post('/cases', payload)
    success.value = 'Caso creado correctamente.'
    await load(1)
    await openCase(response.data.id)
  } catch {}
}

async function addIntervention() {
  if (!activeCase.value || !intervention.objective.trim()) return
  try {
    await api.post(`/cases/${activeCase.value.id}/interventions`, { ...intervention, due_at: intervention.due_at || null })
    success.value = 'Atención registrada en la ficha.'
    resetIntervention()
    await openCase(activeCase.value.id)
  } catch {}
}

watch(studentQuery, value => {
  const match = studentOptions.value.find(student => student.optionLabel === value)
  form.primary_student_id = match?.id || ''
})
watch([() => props.initialCaseId, () => props.openCreate], async ([id, create]) => {
  if (id) await openCase(id)
  else if (create) openCreateModal()
}, { immediate: true })
onMounted(load)
</script>

<template>
  <section class="workspace-card">
    <div class="workspace-toolbar">
      <div>
        <span class="eyebrow">Gestión de casos</span>
        <h2>Casos y fichas de atención</h2>
        <p>{{ pagination.total }} registros visibles según tus permisos.</p>
      </div>
      <button v-if="api.can('social_work.cases.create')" class="btn btn-primary" @click="openCreateModal"><i class="bx bx-plus"></i> Nuevo caso</button>
    </div>

    <div v-if="success" class="alert alert-success py-2">{{ success }}</div>
    <div v-if="api.error" class="alert alert-danger py-2">{{ api.error }}</div>
    <div class="filters">
      <div class="input-group"><span class="input-group-text"><i class="bx bx-search"></i></span><input v-model="search" class="form-control" placeholder="Buscar estudiante, RUT, código o asunto" @keyup.enter="load(1)"></div>
      <select v-model="status" class="form-select" @change="load(1)"><option value="">Todos los estados</option><option v-for="item in catalogs.case_statuses || []" :key="item" :value="item">{{ label(item) }}</option></select>
      <select v-model="risk" class="form-select" @change="load(1)"><option value="">Todos los riesgos</option><option v-for="item in catalogs.risk_levels || []" :key="item" :value="item">{{ label(item) }}</option></select>
      <button class="btn btn-outline-primary" @click="load(1)">Aplicar</button>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Caso</th><th>Alumna</th><th>Estado</th><th>Riesgo</th><th>Responsable</th><th>Última actividad</th><th></th></tr></thead>
        <tbody>
          <tr v-for="item in cases" :key="item.id">
            <td><strong>{{ item.code }}</strong><small>{{ item.title }}</small></td>
            <td><strong>{{ name(item.student) }}</strong><small>{{ item.course_section?.display_name || item.student?.rut || 'Sin curso' }}</small></td>
            <td><span class="state">{{ label(item.status) }}</span></td>
            <td><RiskBadge :level="item.risk_level" /></td>
            <td>{{ item.responsible?.name || 'Sin asignar' }}</td>
            <td>{{ date(item.last_activity_at || item.opened_on) }}</td>
            <td class="text-end"><div class="row-actions"><button class="btn btn-sm btn-outline-primary" @click="openCase(item.id)"><i class="bx bx-history"></i> Ver historial</button><button v-if="canRegisterAttention && !['cerrado','anulado'].includes(item.status)" class="btn btn-sm btn-primary" @click="openAttention(item.id)"><i class="bx bx-message-square-add"></i> Registrar atención</button></div></td>
          </tr>
          <tr v-if="!cases.length && !api.loading"><td colspan="7" class="empty">No hay casos para los filtros seleccionados.</td></tr>
        </tbody>
      </table>
    </div>
    <div v-if="api.loading" class="loading"><span class="spinner-border spinner-border-sm"></span> Cargando casos…</div>
    <div v-if="pagination.last_page > 1" class="pager"><button class="btn btn-sm btn-light" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Anterior</button><span>{{ pagination.current_page }} / {{ pagination.last_page }}</span><button class="btn btn-sm btn-light" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Siguiente</button></div>
  </section>

  <Teleport to="body">
    <div v-if="modal" class="sw-modal" role="dialog" aria-modal="true" @click.self="modal === 'attention' ? cancelAttention() : closeModal()">
      <div class="sw-modal-panel" :class="{ wide: modal === 'case', attention: modal === 'attention' }">
        <header><div><span class="eyebrow">{{ modal === 'create' ? 'Apertura confidencial' : modal === 'case' ? 'Registro histórico del caso' : 'Nueva actuación profesional' }}</span><h2>{{ modal === 'create' ? 'Nuevo caso social' : modal === 'case' ? title : `Registrar atención · ${activeCase?.code || ''}` }}</h2></div><button class="close-button" aria-label="Cerrar" @click="modal === 'attention' ? cancelAttention() : closeModal()"><i class="bx bx-x"></i></button></header>

        <form v-if="modal === 'create'" class="modal-body create-form" @submit.prevent="createCase">
          <section class="form-section"><h3><span>1</span> Alumna y asignación</h3><div class="row g-3">
            <div class="col-md-7"><label>Buscar alumna *</label><input v-model="studentQuery" list="case-student-options" class="form-control" placeholder="Escribe nombre, RUT o curso" autocomplete="off" required><datalist id="case-student-options"><option v-for="student in studentOptions" :key="student.id" :value="student.optionLabel"></option></datalist><small class="field-hint">Selecciona una coincidencia exacta de la lista.</small></div>
            <div class="col-md-5"><label>Profesional responsable</label><select v-model="form.responsible_user_id" class="form-select"><option value="">Sin asignar</option><option v-for="user in catalogs.professionals || []" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
            <div v-if="selectedStudent" class="col-12"><div class="student-preview"><i class="bx bx-user-circle"></i><div><strong>{{ name(selectedStudent) }}</strong><span>{{ selectedStudent.rut || 'Sin RUT' }} · {{ selectedStudent.current_enrollment?.course_section?.display_name || 'Sin curso vigente' }}</span></div><i class="bx bx-check-circle"></i></div></div>
          </div></section>
          <section class="form-section"><h3><span>2</span> Clasificación inicial</h3><div class="row g-3">
            <div class="col-md-8"><label>Título breve *</label><input v-model="form.title" class="form-control" maxlength="255" placeholder="Ej. Seguimiento de bienestar familiar" required></div>
            <div class="col-md-4"><label>Motivo *</label><input v-model="form.reason" class="form-control" maxlength="255" placeholder="Motivo principal" required></div>
            <div class="col-md-3"><label>Origen</label><select v-model="form.origin" class="form-select"><option value="derivacion">Derivación</option><option value="demanda_espontanea">Demanda espontánea</option><option value="alerta">Alerta</option><option value="seguimiento">Seguimiento</option><option value="otro">Otro</option></select></div>
            <div class="col-md-3"><label>Tipo de caso</label><select v-model="form.case_type" class="form-select"><option value="general">General</option><option value="familiar">Familiar</option><option value="socioeconomico">Socioeconómico</option><option value="proteccion">Protección</option><option value="salud">Salud</option><option value="asistencia">Asistencia</option></select></div>
            <div class="col-md-2"><label>Prioridad</label><select v-model="form.priority" class="form-select"><option v-for="item in catalogs.priorities || []" :key="item" :value="item">{{ label(item) }}</option></select></div>
            <div class="col-md-2"><label>Riesgo inicial</label><select v-model="form.risk_level" class="form-select"><option v-for="item in catalogs.risk_levels || []" :key="item" :value="item">{{ label(item) }}</option></select></div>
            <div class="col-md-2"><label>Privacidad</label><select v-model="form.confidentiality" class="form-select"><option v-for="item in catalogs.confidentiality || []" :key="item" :value="item">{{ label(item) }}</option></select></div>
          </div></section>
          <section class="form-section"><h3><span>3</span> Antecedentes y próximos pasos</h3><div class="row g-3">
            <div class="col-12"><label>Antecedentes iniciales</label><textarea v-model="form.initial_description" class="form-control" rows="4" placeholder="Registra antecedentes objetivos y pertinentes para la intervención."></textarea></div>
            <div class="col-12"><label>Medidas de resguardo iniciales</label><textarea v-model="form.initial_safeguards" class="form-control" rows="2" placeholder="Acciones inmediatas para proteger y acompañar a la alumna."></textarea></div>
            <div class="col-md-8"><label>Próximo hito</label><input v-model="form.next_milestone" class="form-control" maxlength="255" placeholder="Ej. Entrevista con apoderada"></div>
            <div class="col-md-4"><label>Fecha de seguimiento</label><input v-model="form.due_at" type="datetime-local" class="form-control"></div>
          </div></section>
          <div class="privacy-note"><i class="bx bx-lock-alt"></i><span>Los relatos y antecedentes no se incluirán en notificaciones generales. El acceso se controla por rol y asignación.</span></div>
          <footer><button type="button" class="btn btn-light" @click="closeModal">Cancelar</button><button class="btn btn-primary" :disabled="api.loading || !form.primary_student_id"><i class="bx bx-folder-plus"></i> Crear caso</button></footer>
        </form>

        <div v-else-if="modal === 'case'" class="modal-body case-sheet">
          <div v-if="!activeCase" class="loading"><span class="spinner-border spinner-border-sm"></span> Cargando ficha autorizada…</div>
          <template v-else>
            <section class="case-summary">
              <div><span>Alumna</span><strong>{{ name(activeCase.student) }}</strong><small>{{ activeCase.student?.rut }} · {{ activeCase.course_section?.display_name || 'Sin curso' }}</small></div>
              <div><span>Estado</span><strong>{{ label(activeCase.status) }}</strong></div>
              <div><span>Riesgo</span><RiskBadge :level="activeCase.risk_level" /></div>
              <div><span>Responsable</span><strong>{{ activeCase.responsible?.name || 'Sin asignar' }}</strong></div>
              <div class="summary-actions"><button v-if="api.can('social_work.reports.export')" class="btn btn-sm btn-outline-primary" @click="downloadSocialCaseMaster(activeCase)"><i class="bx bx-download"></i> Exportar PDF</button><button v-if="canRegisterAttention && !['cerrado','anulado'].includes(activeCase.status)" class="btn btn-sm btn-primary" @click="openAttention(activeCase.id, true)"><i class="bx bx-message-square-add"></i> Registrar atención</button></div>
            </section>
            <div class="history-layout">
              <main>
                <section class="history-section opening-record"><div class="history-heading"><div><span>01</span><h3>Antecedentes de apertura</h3></div><small>{{ date(activeCase.opened_on) }}</small></div><dl><div><dt>Motivo</dt><dd>{{ activeCase.reason || 'Sin motivo registrado.' }}</dd></div><div><dt>Antecedentes iniciales</dt><dd>{{ activeCase.initial_description || 'Sin antecedentes visibles.' }}</dd></div><div><dt>Medidas de resguardo</dt><dd>{{ activeCase.initial_safeguards || 'Sin medidas registradas.' }}</dd></div><div><dt>Próximo hito</dt><dd>{{ activeCase.next_milestone || 'Sin definir' }} · {{ date(activeCase.due_at) }}</dd></div></dl></section>
                <section class="history-section"><div class="history-heading"><div><span>02</span><h3>Atenciones e intervenciones</h3></div><small>{{ activeCase.interventions?.length || 0 }} registros</small></div>
                  <div class="timeline"><article v-for="item in activeCase.interventions || []" :key="item.id" class="timeline-row"><div class="timeline-icon"><i class="bx bx-check"></i></div><div><div class="timeline-title"><strong>{{ label(item.kind) }} · {{ item.objective }}</strong><span>{{ label(item.status) }}</span></div><small>{{ date(item.activity_date) }} · {{ item.responsible?.name || 'Sin responsable' }} · {{ label(item.confidentiality) }}</small><div v-if="peopleFor(item, 'participant').length || peopleFor(item, 'support').length" class="timeline-people"><span v-if="peopleFor(item, 'participant').length"><i class="bx bx-group"></i><b>Participantes:</b> {{ peopleFor(item, 'participant').join(', ') }}</span><span v-if="peopleFor(item, 'support').length"><i class="bx bx-user-plus"></i><b>Apoyos:</b> {{ peopleFor(item, 'support').join(', ') }}</span></div><p>{{ item.result || item.description || 'Sin resumen.' }}</p><div v-if="item.agreements || item.next_action" class="timeline-followup"><span v-if="item.agreements"><b>Acuerdos:</b> {{ item.agreements }}</span><span v-if="item.next_action"><b>Próxima acción:</b> {{ item.next_action }} · {{ date(item.due_at) }}</span></div></div></article><p v-if="!activeCase.interventions?.length" class="empty">Aún no existen atenciones registradas.</p></div>
                </section>
              </main>
              <aside class="history-sidebar">
                <section><div class="history-heading"><div><span>03</span><h3>Cambios de estado</h3></div></div><article v-for="item in activeCase.status_history || []" :key="item.id" class="status-event"><i class="bx bx-radio-circle-marked"></i><div><strong>{{ label(item.to_status) }}</strong><span>{{ date(item.changed_at) }} · {{ item.user?.name || 'Sistema' }}</span><p>{{ item.reason || 'Sin observación.' }}</p></div></article><p v-if="!activeCase.status_history?.length" class="empty compact">Sin cambios registrados.</p></section>
                <section><div class="history-heading"><div><span>04</span><h3>Alertas vinculadas</h3></div></div><article v-for="item in activeCase.alerts || []" :key="item.id" class="alert-event"><i class="bx bx-bell"></i><div><strong>{{ label(item.type) }}</strong><span>{{ label(item.severity) }} · {{ label(item.status) }}</span><p>{{ item.reason }}</p></div></article><p v-if="!activeCase.alerts?.length" class="empty compact">Sin alertas vinculadas.</p></section>
              </aside>
            </div>
          </template>
        </div>

        <form v-else class="modal-body attention-form" @submit.prevent="addIntervention">
          <div v-if="!activeCase" class="loading"><span class="spinner-border spinner-border-sm"></span> Cargando caso autorizado…</div>
          <template v-else>
            <section class="attention-context"><div class="student-avatar">{{ name(activeCase.student).slice(0,1) }}</div><div><span>Alumna</span><strong>{{ name(activeCase.student) }}</strong><small>{{ activeCase.student?.rut }} · {{ activeCase.course_section?.display_name || 'Sin curso' }}</small></div><div><span>Caso</span><strong>{{ activeCase.code }}</strong><small>{{ activeCase.title }}</small></div><RiskBadge :level="activeCase.risk_level" /></section>
            <section class="form-section"><h3><span>1</span> Identificación de la atención</h3><div class="row g-3"><div class="col-md-4"><label>Tipo de atención *</label><select v-model="intervention.kind" class="form-select"><option value="entrevista">Entrevista</option><option value="accion">Acción social</option><option value="visita_domiciliaria">Visita domiciliaria</option><option value="llamado">Llamado telefónico</option><option value="accion_familiar">Acción familiar</option><option value="reunion">Reunión</option><option value="seguimiento">Seguimiento</option></select></div><div class="col-md-4"><label>Fecha *</label><input v-model="intervention.activity_date" type="date" class="form-control" required></div><div class="col-md-4"><label>Confidencialidad *</label><select v-model="intervention.confidentiality" class="form-select"><option value="interno">Interno</option><option value="restringido">Restringido</option><option value="altamente_restringido">Altamente restringido</option></select></div><div class="col-12"><label>Objetivo de la atención *</label><input v-model="intervention.objective" class="form-control" maxlength="255" placeholder="Objetivo concreto de la actuación profesional" required></div></div></section>
            <section class="form-section participants-section">
              <h3><span>2</span> Personas que participan</h3>
              <p class="section-help">Marca una o más opciones según con quién se realiza la atención.</p>
              <div class="participant-type-grid">
                <button type="button" class="participant-option" :class="{ selected: hasParticipant('student') }" :aria-pressed="hasParticipant('student')" @click="toggleParticipant('student')"><i class="bx bx-user"></i><span><strong>Estudiante</strong><small>{{ name(activeCase.student) }}</small></span><i class="bx" :class="hasParticipant('student') ? 'bx-check-circle' : 'bx-circle'"></i></button>
                <button type="button" class="participant-option" :class="{ selected: hasParticipant('guardian') }" :aria-pressed="hasParticipant('guardian')" @click="toggleParticipant('guardian')"><i class="bx bx-home"></i><span><strong>Apoderado/a</strong><small>{{ guardianName }}</small></span><i class="bx" :class="hasParticipant('guardian') ? 'bx-check-circle' : 'bx-circle'"></i></button>
                <button type="button" class="participant-option" :class="{ selected: hasParticipant('staff') }" :aria-pressed="hasParticipant('staff')" @click="toggleParticipant('staff')"><i class="bx bx-id-card"></i><span><strong>Funcionario/a</strong><small>Profesor/a u otro integrante del establecimiento</small></span><i class="bx" :class="hasParticipant('staff') ? 'bx-check-circle' : 'bx-circle'"></i></button>
              </div>
              <div class="input-group input-group-sm staff-search"><span class="input-group-text"><i class="bx bx-search"></i></span><input v-model="staffSearch" class="form-control" placeholder="Buscar funcionario por nombre o cargo"></div>
              <div v-if="hasParticipant('staff')" class="staff-picker">
                <div class="picker-heading"><div><strong>Funcionarios presentes en la atención</strong><small>Selecciona una o más personas.</small></div><span>{{ intervention.participant_staff_ids.length }} seleccionados</span></div>
                <div class="staff-options"><button v-for="person in filteredStaff" :key="`participant-${person.id}`" type="button" class="staff-option" :class="{ selected: hasStaff('participant_staff_ids', person.id) }" :aria-pressed="hasStaff('participant_staff_ids', person.id)" @click="toggleStaff('participant_staff_ids', person.id)"><span class="staff-initial">{{ person.name.slice(0, 1) }}</span><span><strong>{{ person.name }}</strong><small>{{ person.role || 'Funcionario/a' }}</small></span><i class="bx" :class="hasStaff('participant_staff_ids', person.id) ? 'bx-check-square' : 'bx-square'"></i></button><p v-if="!filteredStaff.length" class="empty compact">No hay funcionarios que coincidan con la búsqueda.</p></div>
              </div>
              <div class="staff-picker support-picker">
                <div class="picker-heading"><div><strong>Funcionarios de apoyo</strong><small>Agrega uno o más apoyos que colaboraron en esta atención.</small></div><span>{{ intervention.support_staff_ids.length }} seleccionados</span></div>
                <div class="staff-options"><button v-for="person in filteredStaff" :key="`support-${person.id}`" type="button" class="staff-option" :class="{ selected: hasStaff('support_staff_ids', person.id) }" :aria-pressed="hasStaff('support_staff_ids', person.id)" @click="toggleStaff('support_staff_ids', person.id)"><span class="staff-initial">{{ person.name.slice(0, 1) }}</span><span><strong>{{ person.name }}</strong><small>{{ person.role || 'Funcionario/a' }}</small></span><i class="bx" :class="hasStaff('support_staff_ids', person.id) ? 'bx-check-square' : 'bx-square'"></i></button><p v-if="!filteredStaff.length" class="empty compact">No hay funcionarios que coincidan con la búsqueda.</p></div>
              </div>
            </section>
            <section class="form-section"><h3><span>3</span> Desarrollo y resultado</h3><div class="row g-3"><div class="col-md-6"><label>Descripción</label><textarea v-model="intervention.description" class="form-control" rows="4" placeholder="Antecedentes pertinentes tratados durante la atención."></textarea></div><div class="col-md-6"><label>Resultado</label><textarea v-model="intervention.result" class="form-control" rows="4" placeholder="Resultado profesional de la intervención."></textarea></div><div class="col-12"><label>Acuerdos</label><textarea v-model="intervention.agreements" class="form-control" rows="2" placeholder="Compromisos y acuerdos adoptados."></textarea></div></div></section>
            <section class="form-section"><h3><span>4</span> Seguimiento</h3><div class="row g-3"><div class="col-md-8"><label>Próxima acción</label><input v-model="intervention.next_action" class="form-control" placeholder="Acción de seguimiento acordada"></div><div class="col-md-4"><label>Fecha límite</label><input v-model="intervention.due_at" type="datetime-local" class="form-control"></div><div class="col-md-4"><label>Estado del registro</label><select v-model="intervention.status" class="form-select"><option value="finalizada">Finalizada</option><option value="borrador">Borrador</option></select></div></div></section>
            <div class="privacy-note"><i class="bx bx-shield-quarter"></i><span>La atención quedará incorporada al registro histórico del caso y su acceso respetará el nivel de confidencialidad seleccionado.</span></div>
            <footer><button type="button" class="btn btn-light" @click="cancelAttention">Cancelar</button><button class="btn btn-primary" :disabled="api.loading || !canSubmitAttention"><i class="bx bx-save"></i> Guardar atención</button></footer>
          </template>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.workspace-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 16px 40px rgba(35,45,75,.07);overflow:hidden}.workspace-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.35rem 1.5rem;border-bottom:1px solid #edf1f5}.workspace-toolbar h2{font-size:1.2rem;margin:.15rem 0;color:#24324a}.workspace-toolbar p{margin:0;color:#738096}.eyebrow{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;font-weight:800;color:#75609c}.filters{display:flex;gap:.65rem;padding:1rem 1.5rem;background:#f8fafc}.filters .input-group{max-width:480px}.filters .form-select{max-width:210px}thead th{padding:.8rem 1rem;font-size:.67rem;letter-spacing:.07em;text-transform:uppercase;color:#718096;background:#fff}tbody td{padding:.85rem 1rem}td strong,td small{display:block}td small{color:#8490a0;margin-top:.15rem}.state{padding:.28rem .55rem;border-radius:999px;background:#eef2ff;color:#51427d;font-size:.72rem;text-transform:capitalize}.row-actions,.summary-actions{display:flex;align-items:center;justify-content:flex-end;gap:.45rem;white-space:nowrap}.empty,.loading{padding:2rem;text-align:center;color:#718096}.empty.compact{padding:.7rem 0;font-size:.78rem}.pager{padding:1rem;display:flex;justify-content:flex-end;align-items:center;gap:.75rem}.sw-modal{position:fixed;inset:0;z-index:1090;background:rgba(15,23,42,.58);backdrop-filter:blur(5px);display:grid;place-items:center;padding:1rem}.sw-modal-panel{width:min(920px,97vw);max-height:94vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 28px 80px rgba(15,23,42,.32)}.sw-modal-panel.wide{width:min(1220px,97vw)}.sw-modal-panel.attention{width:min(980px,97vw)}.sw-modal-panel>header{position:sticky;top:0;z-index:2;background:rgba(255,255,255,.97);backdrop-filter:blur(12px);display:flex;align-items:center;justify-content:space-between;padding:1.15rem 1.4rem;border-bottom:1px solid #e8edf3}.sw-modal-panel header h2{margin:.15rem 0 0;font-size:1.2rem}.close-button{display:grid;place-items:center;border:0;background:#f1f5f9;width:36px;height:36px;border-radius:50%;font-size:1.4rem;color:#475569}.modal-body{padding:1.35rem}.modal-body footer{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1.2rem}label{display:block;font-size:.74rem;font-weight:700;color:#526174;margin:.65rem 0 .3rem}.form-section{padding:1rem;border:1px solid #e7ecf2;border-radius:15px;margin-bottom:.85rem;background:#fff}.form-section h3{font-size:.86rem;color:#334155;margin:0 0 .7rem}.form-section h3 span{display:inline-grid;place-items:center;width:23px;height:23px;margin-right:.35rem;border-radius:50%;background:#68549b;color:#fff;font-size:.68rem}.field-hint{display:block;color:#8390a0;margin-top:.25rem}.student-preview{display:flex;align-items:center;gap:.7rem;padding:.7rem .85rem;border-radius:12px;background:linear-gradient(135deg,#edf8ff,#f4efff);color:#334155}.student-preview>i:first-child{font-size:1.5rem;color:#69549a}.student-preview>div{flex:1}.student-preview strong,.student-preview span{display:block}.student-preview span{font-size:.72rem;color:#718096}.student-preview>i:last-child{color:#15805d;font-size:1.2rem}.privacy-note{display:flex;align-items:center;gap:.65rem;padding:.75rem .85rem;border-radius:12px;background:#f2effa;color:#584773;font-size:.78rem}.privacy-note i{font-size:1.2rem}.case-summary{display:grid;grid-template-columns:minmax(200px,1.8fr) repeat(3,minmax(115px,.8fr)) auto;gap:1rem;background:linear-gradient(135deg,#f1f8ff,#f7f1ff);padding:1rem;border:1px solid #e4e7f5;border-radius:14px;margin-bottom:1.2rem;align-items:center}.case-summary>div{display:flex;flex-direction:column;min-width:0}.case-summary span,.attention-context span{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#758196}.case-summary small,.attention-context small{color:#718096}.case-summary .summary-actions{flex-direction:row;flex-wrap:wrap}.history-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(300px,.8fr);gap:1.1rem;align-items:start}.history-layout main{display:grid;gap:1rem}.history-section,.history-sidebar>section{border:1px solid #e5eaf1;border-radius:16px;padding:1rem;background:#fff}.history-sidebar{display:grid;gap:1rem}.history-sidebar>section{background:#f8fafc}.history-heading{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-bottom:.75rem}.history-heading>div{display:flex;align-items:center;gap:.55rem}.history-heading h3{font-size:.91rem;color:#2f3d52;margin:0}.history-heading>div>span{display:grid;place-items:center;width:29px;height:29px;border-radius:9px;background:#eeeafb;color:#665292;font-size:.67rem;font-weight:800}.history-heading>small{color:#8490a0}.opening-record dl{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin:0}.opening-record dl>div{padding:.75rem;border-radius:11px;background:#f8fafc}.opening-record dt{font-size:.67rem;text-transform:uppercase;letter-spacing:.05em;color:#7a8797;margin-bottom:.25rem}.opening-record dd{font-size:.83rem;line-height:1.5;color:#445267;margin:0;white-space:pre-line}.timeline{position:relative}.timeline-row{position:relative;display:grid;grid-template-columns:34px 1fr;gap:.7rem;padding:.85rem 0;border-bottom:1px solid #edf1f5}.timeline-row:last-child{border-bottom:0}.timeline-icon{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:#eaf8f2;color:#15805d}.timeline-title{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}.timeline-title strong{color:#334155}.timeline-title>span{padding:.2rem .45rem;border-radius:999px;background:#eef2ff;color:#5d4b8a;font-size:.64rem;text-transform:capitalize}.timeline-row small{display:block;font-size:.72rem;color:#7d8998;margin-top:.12rem}.timeline-row p{margin:.35rem 0 0;color:#536174;white-space:pre-line}.timeline-followup{display:grid;gap:.25rem;margin-top:.55rem;padding:.6rem .7rem;border-left:3px solid #9b87c4;background:#faf8ff;border-radius:0 9px 9px 0;color:#536174;font-size:.75rem}.status-event,.alert-event{display:grid;grid-template-columns:22px 1fr;gap:.5rem;padding:.7rem 0;border-bottom:1px solid #e6ebf1}.status-event:last-of-type,.alert-event:last-of-type{border-bottom:0}.status-event>i{color:#725da4}.alert-event>i{color:#d97706}.status-event strong,.status-event span,.alert-event strong,.alert-event span{display:block}.status-event strong,.alert-event strong{font-size:.8rem;color:#3e4b5e;text-transform:capitalize}.status-event span,.alert-event span{font-size:.69rem;color:#8490a0;margin-top:.1rem}.status-event p,.alert-event p{font-size:.75rem;color:#607084;margin:.25rem 0 0}.attention-form{background:#f8fafc}.attention-form .form-section{box-shadow:0 5px 18px rgba(35,45,75,.035)}.attention-context{display:grid;grid-template-columns:48px minmax(180px,1fr) minmax(180px,1fr) auto;align-items:center;gap:.85rem;padding:1rem;margin-bottom:1rem;border:1px solid #e3e8f2;border-radius:15px;background:linear-gradient(135deg,#edf8ff,#f5f0ff)}.attention-context>div:not(.student-avatar){display:flex;flex-direction:column;min-width:0}.attention-context strong{color:#334155}.student-avatar{display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:#69549a;color:#fff;font-size:1.05rem;font-weight:800;box-shadow:0 7px 16px rgba(105,84,154,.24)}@media(max-width:1050px){.case-summary{grid-template-columns:1fr 1fr 1fr}.case-summary .summary-actions{grid-column:1/-1;justify-content:flex-start}}@media(max-width:900px){.filters{flex-wrap:wrap}.history-layout{grid-template-columns:1fr}.workspace-toolbar{align-items:flex-start}.sw-modal{padding:.25rem}.sw-modal-panel{max-height:98vh}.attention-context{grid-template-columns:48px 1fr}.attention-context>div:nth-child(3){grid-column:2}.attention-context>.risk-badge{grid-column:2}}@media(max-width:640px){.workspace-toolbar{flex-direction:column}.filters .input-group,.filters .form-select{max-width:none;width:100%}.case-summary,.opening-record dl{grid-template-columns:1fr}.case-summary .summary-actions{grid-column:auto}.row-actions{align-items:stretch;flex-direction:column}.timeline-title{flex-direction:column;gap:.3rem}.attention-context{grid-template-columns:40px 1fr}.student-avatar{width:38px;height:38px}}
.timeline-people{display:grid;gap:.2rem;margin-top:.45rem;color:#5d6979;font-size:.72rem}.timeline-people span{display:flex;align-items:flex-start;gap:.3rem}.timeline-people i{margin-top:.1rem;color:#6b5796}.section-help{margin:-.35rem 0 .75rem;color:#7a8797;font-size:.76rem}.participant-type-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.participant-option,.staff-option{border:1px solid #dde4ed;background:#fff;color:#435166;text-align:left;transition:.16s ease}.participant-option{display:grid;grid-template-columns:34px 1fr 20px;align-items:center;gap:.6rem;min-height:76px;padding:.7rem;border-radius:12px}.participant-option>i:first-child{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#f0f3f7;color:#68778a;font-size:1.15rem}.participant-option span,.participant-option strong,.participant-option small,.staff-option span,.staff-option strong,.staff-option small{display:block}.participant-option small,.staff-option small{color:#7d8998;font-size:.68rem;margin-top:.1rem}.participant-option>i:last-child,.staff-option>i:last-child{color:#9aa5b2;font-size:1.05rem}.participant-option:hover,.staff-option:hover{border-color:#b8acd3;background:#fbfaff}.participant-option.selected,.staff-option.selected{border-color:#806aa9;background:#f5f1fc;box-shadow:0 5px 14px rgba(104,84,148,.09)}.participant-option.selected>i:first-child{background:#e5dcf5;color:#634e8d}.participant-option.selected>i:last-child,.staff-option.selected>i:last-child{color:#655092}.staff-picker{margin-top:.8rem;padding:.85rem;border:1px solid #e3e8ef;border-radius:13px;background:#f8fafc}.support-picker{background:#f5f8ff;border-color:#dfe6f4}.picker-heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem}.picker-heading>div strong,.picker-heading>div small{display:block}.picker-heading>div strong{color:#3d4b5e;font-size:.78rem}.picker-heading>div small{color:#7d8998;font-size:.7rem}.picker-heading>span{padding:.22rem .5rem;border-radius:999px;background:#ebe6f5;color:#604c85;font-size:.65rem;white-space:nowrap}.staff-search{max-width:420px;margin:.7rem 0}.staff-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;max-height:210px;overflow:auto;padding-right:.2rem}.staff-option{display:grid;grid-template-columns:30px 1fr 18px;align-items:center;gap:.5rem;padding:.55rem .65rem;border-radius:10px}.staff-initial{display:grid!important;place-items:center;width:30px;height:30px;border-radius:9px;background:#e8eef5;color:#596a7e;font-weight:800}.staff-option.selected .staff-initial{background:#dcd2ef;color:#5c477e}@media(max-width:900px){.participant-type-grid,.staff-options{grid-template-columns:1fr}}@media(max-width:640px){.picker-heading{align-items:flex-start;flex-direction:column}}
</style>
