<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useSocialWork } from '../../composables/useSocialWork'
import { downloadJunaebDeliveryAct } from '../../utils/social-work-export'

const props = defineProps({
  catalogs: { type: Object, default: () => ({}) },
})
const api = reactive(useSocialWork())
const tab = ref('deliveries')
const students = ref([])
const benefits = ref([])
const deliveries = ref([])
const passes = ref([])
const services = ref([])
const devices = ref([])
const matrix = ref([])
const matrixLoaded = ref(false)
const matrixPagination = ref({ current_page: 1, last_page: 1, total: 0 })
const modal = ref('')
const success = ref('')
const year = computed(() => props.catalogs.academic_years?.find(item => item.is_active)?.year || new Date().getFullYear())
const name = student => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')
const label = value => String(value || '—').replaceAll('_', ' ')
const date = value => value ? new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium' }).format(new Date(value)) : '—'
const eligiblePassStudents = computed(() => students.value.filter(student => student.transport_pass_eligible))
const schoolSupplyBenefits = computed(() => benefits.value.filter(item => item.benefit_type?.code === 'utiles_escolares'))
const passByStudent = computed(() => Object.fromEntries(passes.value.map(item => [item.student_profile_id, item])))

const benefitForm = reactive({ student_profile_id: '', benefit_type_id: '', status: 'aprobado', approved_on: new Date().toISOString().slice(0, 10), notes: '' })
const deliveryForm = reactive({ benefit_id: '', delivered_on: new Date().toISOString().slice(0, 10), receiver_name: '', receiver_relationship: 'apoderado/a', status: 'entregada', notes: '', items_text: 'Set de útiles escolares|1|set' })
const passForm = reactive({ student_profile_id: '', pass_type: 'tne', status: 'posee', identifier: '', delivered_on: new Date().toISOString().slice(0, 10), notes: '' })
const serviceForm = reactive({ student_profile_id: '', service_type: 'oftalmologo', origin: 'junaeb', is_junaeb: true, referred_on: new Date().toISOString().slice(0, 10), status: 'pendiente', provider: '', notes: '' })
const deviceForm = reactive({ student_profile_id: '', support_type: 'lentes', origin: 'junaeb', starts_on: new Date().toISOString().slice(0, 10), active: true, renewal_on: '', brief_note: '' })

async function loadAll() {
  try {
    const [studentResponse, benefitResponse, deliveryResponse, passResponse, serviceResponse, deviceResponse] = await Promise.all([
      api.get('/junaeb/student-options'),
      api.get(`/junaeb/benefits?per_page=100&school_year=${year.value}`),
      api.get('/junaeb/deliveries?per_page=100'),
      api.get(`/transport-passes?per_page=100&school_year=${year.value}`),
      api.get('/medical-services?per_page=100'),
      api.get('/support-devices?per_page=100&active=1'),
    ])
    students.value = studentResponse.data || []
    benefits.value = benefitResponse.data || []
    deliveries.value = deliveryResponse.data || []
    passes.value = passResponse.data || []
    services.value = serviceResponse.data || []
    devices.value = deviceResponse.data || []
  } catch {}
}

async function loadMatrix(page = 1) {
  try {
    const response = await api.get(`/support-matrix?per_page=100&page=${page}&school_year=${year.value}`)
    matrix.value = response.data || []
    matrixPagination.value = {
      current_page: response.current_page || page,
      last_page: response.last_page || 1,
      total: response.total || 0,
    }
    matrixLoaded.value = true
  } catch {}
}

function selectTab(nextTab) {
  tab.value = nextTab
  if (nextTab === 'coverage' && !matrixLoaded.value) loadMatrix()
}

function currentCourseId(studentId) { return students.value.find(item => Number(item.id) === Number(studentId))?.current_enrollment?.course_section_id || null }
function open(type, defaults = {}) { modal.value = type; Object.assign(type === 'pass' ? passForm : type === 'delivery' ? deliveryForm : type === 'service' ? serviceForm : type === 'device' ? deviceForm : benefitForm, defaults) }
function close() { modal.value = '' }

async function saveBenefit() {
  try {
    await api.post('/junaeb/benefits', { ...benefitForm, student_profile_id: Number(benefitForm.student_profile_id), benefit_type_id: Number(benefitForm.benefit_type_id), course_section_id: currentCourseId(benefitForm.student_profile_id), school_year: year.value })
    success.value = 'Beneficio JUNAEB incorporado.'; matrixLoaded.value = false; close(); await loadAll()
  } catch {}
}
async function saveDelivery() {
  const items = deliveryForm.items_text.split('\n').map(line => line.trim()).filter(Boolean).map(line => { const [itemName, quantity = '1', unit = 'unidad'] = line.split('|'); return { name: itemName.trim(), quantity: Number(quantity) || 1, unit: unit.trim() } })
  try {
    await api.post(`/junaeb/benefits/${deliveryForm.benefit_id}/deliveries`, { delivered_on: deliveryForm.delivered_on, receiver_name: deliveryForm.receiver_name, receiver_relationship: deliveryForm.receiver_relationship, status: deliveryForm.status, notes: deliveryForm.notes, items })
    success.value = 'Entrega registrada. El acta PDF ya está disponible.'; matrixLoaded.value = false; close(); await loadAll()
  } catch {}
}
async function savePass() {
  try {
    await api.post('/transport-passes', { ...passForm, student_profile_id: Number(passForm.student_profile_id), school_year: year.value, delivered_on: passForm.delivered_on || null })
    success.value = 'Estado del pase escolar registrado.'; matrixLoaded.value = false; close(); await loadAll()
  } catch {}
}
async function markHasPass(student) {
  const existing = passByStudent.value[student.id]
  try {
    if (existing) await api.patch(`/transport-passes/${existing.id}`, { status: 'posee', delivered_on: new Date().toISOString().slice(0, 10) })
    else await api.post('/transport-passes', { student_profile_id: student.id, school_year: year.value, pass_type: 'tne', status: 'posee', delivered_on: new Date().toISOString().slice(0, 10) })
    success.value = `Pase confirmado para ${name(student)}.`; matrixLoaded.value = false; await loadAll()
  } catch {}
}
async function saveService() {
  try {
    await api.post('/medical-services', { ...serviceForm, student_profile_id: Number(serviceForm.student_profile_id), is_junaeb: Boolean(serviceForm.is_junaeb), confidentiality: 'restringido' })
    success.value = 'Servicio médico registrado.'; matrixLoaded.value = false; close(); await loadAll()
  } catch {}
}
async function saveDevice() {
  try {
    await api.post('/support-devices', { ...deviceForm, student_profile_id: Number(deviceForm.student_profile_id), renewal_on: deviceForm.renewal_on || null })
    success.value = 'Ayuda o condición de apoyo registrada.'; matrixLoaded.value = false; close(); await loadAll()
  } catch {}
}

onMounted(loadAll)
</script>

<template>
  <section class="junaeb-shell">
    <header class="junaeb-hero"><div><span>Gestión de beneficios y apoyos</span><h2>JUNAEB y programas complementarios</h2><p>Entregas respaldadas, pases escolares, servicios médicos y ayudas técnicas.</p></div><div class="hero-metrics"><div><strong>{{ deliveries.length }}</strong><small>entregas</small></div><div><strong>{{ passes.filter(item => item.status === 'posee').length }}</strong><small>con pase</small></div><div><strong>{{ services.length }}</strong><small>atenciones</small></div></div></header>
    <div v-if="success" class="alert alert-success mx-3 mt-3 mb-0">{{ success }}</div><div v-if="api.error" class="alert alert-danger mx-3 mt-3 mb-0">{{ api.error }}</div>
    <nav class="junaeb-tabs"><button v-for="item in [['deliveries','Útiles y actas','bx-package'],['passes','Pases escolares','bx-id-card'],['services','Servicios médicos','bx-plus-medical'],['devices','Lentes y apoyos','bx-glasses'],['coverage','Cobertura de alumnas','bx-group']]" :key="item[0]" :class="{ active: tab === item[0] }" @click="selectTab(item[0])"><i class="bx" :class="item[2]"></i>{{ item[1] }}</button></nav>

    <div v-if="api.loading" class="loading"><span class="spinner-border spinner-border-sm"></span> Actualizando registros JUNAEB…</div>
    <template v-else>
      <section v-if="tab === 'deliveries'" class="panel"><div class="panel-head"><div><h3>Entrega de útiles escolares</h3><p>Cada entrega genera un acta institucional descargable en PDF.</p></div><div><button class="btn btn-outline-primary me-2" @click="open('benefit')">Incorporar beneficio</button><button class="btn btn-primary" @click="open('delivery')"><i class="bx bx-plus"></i> Registrar entrega</button></div></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Folio</th><th>Alumna</th><th>Curso</th><th>Fecha</th><th>Receptor/a</th><th>Artículos</th><th>Estado</th><th></th></tr></thead><tbody><tr v-for="item in deliveries" :key="item.id"><td><strong>{{ item.folio }}</strong></td><td><strong>{{ name(item.benefit?.student) }}</strong><small>{{ item.benefit?.student?.rut }}</small></td><td>{{ item.benefit?.course_section?.display_name || '—' }}</td><td>{{ date(item.delivered_on) }}</td><td>{{ item.receiver_name || '—' }}</td><td>{{ item.items?.length || 0 }}</td><td><span class="status">{{ label(item.status) }}</span></td><td><button class="btn btn-sm btn-outline-danger" @click="downloadJunaebDeliveryAct(item)"><i class="bx bxs-file-pdf"></i> Acta PDF</button></td></tr><tr v-if="!deliveries.length"><td colspan="8" class="empty">Aún no hay entregas registradas.</td></tr></tbody></table></div></section>

      <section v-if="tab === 'passes'" class="panel"><div class="panel-head"><div><h3>Pases escolares · 5° básico a 4° medio</h3><p>Control anual {{ year }} de alumnas que ya poseen su pase.</p></div><button class="btn btn-primary" @click="open('pass')"><i class="bx bx-plus"></i> Registrar pase</button></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Alumna</th><th>Curso</th><th>Estado del pase</th><th>Identificador</th><th>Fecha entrega</th><th></th></tr></thead><tbody><tr v-for="student in eligiblePassStudents" :key="student.id"><td><strong>{{ name(student) }}</strong><small>{{ student.rut }}</small></td><td>{{ student.current_enrollment?.course_section?.display_name }}</td><td><span class="pass-state" :class="{ has: passByStudent[student.id]?.status === 'posee' }">{{ label(passByStudent[student.id]?.status || 'sin registrar') }}</span></td><td>{{ passByStudent[student.id]?.identifier || '—' }}</td><td>{{ date(passByStudent[student.id]?.delivered_on) }}</td><td><button v-if="passByStudent[student.id]?.status !== 'posee'" class="btn btn-sm btn-outline-success" @click="markHasPass(student)"><i class="bx bx-check"></i> Marcar que lo posee</button></td></tr><tr v-if="!eligiblePassStudents.length"><td colspan="6" class="empty">No se detectaron matrículas vigentes entre 5° básico y 4° medio.</td></tr></tbody></table></div></section>

      <section v-if="tab === 'services'" class="panel"><div class="panel-head"><div><h3>Servicios médicos</h3><p>Otorrino, oftalmología, traumatología y otras especialidades; JUNAEB o externas.</p></div><button class="btn btn-primary" @click="open('service')"><i class="bx bx-plus"></i> Registrar servicio</button></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Alumna</th><th>Especialidad</th><th>Origen</th><th>Derivación</th><th>Atención</th><th>Estado</th><th>Prestador</th></tr></thead><tbody><tr v-for="item in services" :key="item.id"><td><strong>{{ name(item.student) }}</strong><small>{{ item.student?.rut }}</small></td><td>{{ label(item.service_type) }}</td><td><span class="origin" :class="{ junaeb: item.is_junaeb }">{{ item.is_junaeb ? 'JUNAEB' : (item.origin || 'Externo') }}</span></td><td>{{ date(item.referred_on) }}</td><td>{{ date(item.attended_on) }}</td><td><span class="status">{{ label(item.status) }}</span></td><td>{{ item.provider || '—' }}</td></tr><tr v-if="!services.length"><td colspan="7" class="empty">Sin servicios médicos registrados.</td></tr></tbody></table></div></section>

      <section v-if="tab === 'devices'" class="panel"><div class="panel-head"><div><h3>Lentes, audífonos y problemas de columna</h3><p>Registro independiente del origen: JUNAEB, establecimiento o programa externo.</p></div><button class="btn btn-primary" @click="open('device')"><i class="bx bx-plus"></i> Registrar apoyo</button></div><div class="device-grid"><article v-for="item in devices" :key="item.id"><div class="device-icon"><i class="bx" :class="item.support_type === 'lentes' ? 'bx-glasses' : item.support_type === 'audifonos' ? 'bx-headphone' : 'bx-body'"></i></div><div><strong>{{ name(item.student) }}</strong><span>{{ label(item.support_type) }}</span><small>{{ label(item.origin || 'sin origen') }} · desde {{ date(item.starts_on) }}</small><p>{{ item.brief_note || 'Sin observación adicional.' }}</p></div></article><p v-if="!devices.length" class="empty">Sin ayudas técnicas o condiciones registradas.</p></div></section>

      <section v-if="tab === 'coverage'" class="panel"><div class="panel-head"><div><h3>Cobertura JUNAEB y otros programas</h3><p>Resumen consolidado por alumna para el año {{ year }}.</p></div><span v-if="matrixPagination.total" class="coverage-total">{{ matrixPagination.total }} alumnas</span></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Alumna</th><th>Curso</th><th>JUNAEB</th><th>Pro Retención</th><th>Otro programa externo</th><th>Prestaciones</th></tr></thead><tbody><tr v-for="student in matrix" :key="student.id"><td><strong>{{ name(student) }}</strong><small>{{ student.rut }}</small></td><td>{{ student.current_enrollment?.course_section?.display_name || 'Sin curso' }}</td><td><span class="yesno" :class="{ yes: student.program_flags?.junaeb }">{{ student.program_flags?.junaeb ? 'Sí' : 'No' }}</span></td><td><span class="yesno" :class="{ yes: student.program_flags?.pro_retencion }">{{ student.program_flags?.pro_retencion ? 'Sí' : 'No' }}</span></td><td>{{ student.program_flags?.external?.join(', ') || '—' }}</td><td>{{ student.junaeb_benefits?.map(item => item.benefit_type?.name).filter(Boolean).join(', ') || '—' }}</td></tr><tr v-if="matrixLoaded && !matrix.length"><td colspan="6" class="empty">No hay alumnas para el período seleccionado.</td></tr></tbody></table></div><div v-if="matrixPagination.last_page > 1" class="coverage-pager"><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="matrixPagination.current_page <= 1" @click="loadMatrix(matrixPagination.current_page - 1)">Anterior</button><span>Página {{ matrixPagination.current_page }} de {{ matrixPagination.last_page }}</span><button type="button" class="btn btn-sm btn-outline-secondary" :disabled="matrixPagination.current_page >= matrixPagination.last_page" @click="loadMatrix(matrixPagination.current_page + 1)">Siguiente</button></div></section>
    </template>
  </section>

  <Teleport to="body"><div v-if="modal" class="modal-overlay" @click.self="close"><form class="modal-panel" @submit.prevent="modal === 'benefit' ? saveBenefit() : modal === 'delivery' ? saveDelivery() : modal === 'pass' ? savePass() : modal === 'service' ? saveService() : saveDevice()"><header><div><span>Registro operacional</span><h2>{{ modal === 'benefit' ? 'Incorporar beneficio JUNAEB' : modal === 'delivery' ? 'Registrar entrega de útiles' : modal === 'pass' ? 'Registrar pase escolar' : modal === 'service' ? 'Registrar servicio médico' : 'Registrar ayuda o condición' }}</h2></div><button type="button" @click="close"><i class="bx bx-x"></i></button></header><div class="form-body">
    <template v-if="modal === 'benefit'"><label>Alumna *</label><select v-model="benefitForm.student_profile_id" class="form-select" required><option value="">{{ students.length ? 'Selecciona una alumna' : 'No hay alumnas con matrícula vigente' }}</option><option v-for="student in students" :key="student.id" :value="student.id">{{ name(student) }} · {{ student.rut }} · {{ student.current_enrollment?.course_section?.display_name || 'Sin curso' }}</option></select><p v-if="!students.length" class="selector-help"><i class="bx bx-info-circle"></i> No se encontraron matrículas vigentes en el año académico activo.</p><label>Beneficio *</label><select v-model="benefitForm.benefit_type_id" class="form-select" required><option value="">Selecciona</option><option v-for="type in catalogs.junaeb_benefit_types || []" :key="type.id" :value="type.id">{{ type.name }}</option></select><label>Observaciones</label><textarea v-model="benefitForm.notes" class="form-control" rows="3"></textarea></template>
    <template v-if="modal === 'delivery'"><label>Beneficio de útiles *</label><select v-model="deliveryForm.benefit_id" class="form-select" required><option value="">{{ schoolSupplyBenefits.length ? 'Selecciona un beneficio' : 'No hay beneficios de útiles incorporados' }}</option><option v-for="benefit in schoolSupplyBenefits" :key="benefit.id" :value="benefit.id">{{ name(benefit.student) }} · {{ benefit.school_year }}</option></select><p v-if="!schoolSupplyBenefits.length" class="selector-help"><i class="bx bx-info-circle"></i> Primero incorpora el beneficio de útiles de la alumna.</p><div class="row"><div class="col-md-6"><label>Fecha de entrega *</label><input v-model="deliveryForm.delivered_on" type="date" class="form-control" required></div><div class="col-md-6"><label>Persona receptora *</label><input v-model="deliveryForm.receiver_name" class="form-control" required></div></div><label>Relación con alumna</label><input v-model="deliveryForm.receiver_relationship" class="form-control"><label>Artículos (uno por línea: nombre | cantidad | unidad)</label><textarea v-model="deliveryForm.items_text" class="form-control" rows="5" required></textarea></template>
    <template v-if="modal === 'pass'"><label>Alumna *</label><select v-model="passForm.student_profile_id" class="form-select" required><option value="">{{ eligiblePassStudents.length ? 'Selecciona una alumna' : 'No hay alumnas elegibles este año' }}</option><option v-for="student in eligiblePassStudents" :key="student.id" :value="student.id">{{ name(student) }} · {{ student.current_enrollment?.course_section?.display_name }}</option></select><p v-if="!eligiblePassStudents.length" class="selector-help"><i class="bx bx-info-circle"></i> El pase escolar aplica a matrículas vigentes de 5° básico a 4° medio.</p><label>Estado</label><select v-model="passForm.status" class="form-select"><option value="posee">Ya lo posee</option><option value="pendiente_solicitud">Pendiente solicitud</option><option value="pendiente_reposicion">Pendiente reposición</option><option value="pendiente_entrega">Pendiente entrega</option><option value="recibido">Recibido en establecimiento</option></select><label>Identificador TNE</label><input v-model="passForm.identifier" class="form-control"></template>
    <template v-if="modal === 'service'"><label>Alumna *</label><select v-model="serviceForm.student_profile_id" class="form-select" required><option value="">{{ students.length ? 'Selecciona una alumna' : 'No hay alumnas con matrícula vigente' }}</option><option v-for="student in students" :key="student.id" :value="student.id">{{ name(student) }} · {{ student.rut }} · {{ student.current_enrollment?.course_section?.display_name || 'Sin curso' }}</option></select><p v-if="!students.length" class="selector-help"><i class="bx bx-info-circle"></i> No se encontraron matrículas vigentes en el año académico activo.</p><label>Especialidad *</label><select v-model="serviceForm.service_type" class="form-select"><option value="otorrino">Otorrino</option><option value="oftalmologo">Oftalmólogo/a</option><option value="traumatologo">Traumatólogo/a</option><option value="otro">Otro</option></select><label class="check"><input v-model="serviceForm.is_junaeb" type="checkbox"> Prestación JUNAEB</label><label>Prestador</label><input v-model="serviceForm.provider" class="form-control"><label>Observaciones</label><textarea v-model="serviceForm.notes" class="form-control" rows="3"></textarea></template>
    <template v-if="modal === 'device'"><label>Alumna *</label><select v-model="deviceForm.student_profile_id" class="form-select" required><option value="">{{ students.length ? 'Selecciona una alumna' : 'No hay alumnas con matrícula vigente' }}</option><option v-for="student in students" :key="student.id" :value="student.id">{{ name(student) }} · {{ student.rut }} · {{ student.current_enrollment?.course_section?.display_name || 'Sin curso' }}</option></select><p v-if="!students.length" class="selector-help"><i class="bx bx-info-circle"></i> No se encontraron matrículas vigentes en el año académico activo.</p><label>Apoyo o condición *</label><select v-model="deviceForm.support_type" class="form-select"><option value="lentes">Usa lentes</option><option value="audifonos">Usa audífonos</option><option value="problemas_columna">Problemas a la columna</option><option value="otro">Otro apoyo</option></select><label>Origen</label><select v-model="deviceForm.origin" class="form-select"><option value="junaeb">JUNAEB</option><option value="establecimiento">Establecimiento</option><option value="programa_externo">Programa externo</option><option value="particular">Particular</option></select><label>Nota breve</label><textarea v-model="deviceForm.brief_note" class="form-control" rows="3"></textarea></template>
  </div><footer><button type="button" class="btn btn-light" @click="close">Cancelar</button><button class="btn btn-primary" :disabled="api.loading">Guardar registro</button></footer></form></div></Teleport>
</template>

<style scoped>
.junaeb-shell{background:#fff;border:1px solid #e0e7ef;border-radius:18px;box-shadow:0 18px 48px rgba(37,48,77,.08);overflow:hidden}.junaeb-hero{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.4rem 1.5rem;background:linear-gradient(135deg,#fff9e9,#f5f2ff)}.junaeb-hero>div>span,.modal-panel header span{font-size:.67rem;text-transform:uppercase;letter-spacing:.11em;font-weight:800;color:#8d6c22}.junaeb-hero h2,.modal-panel h2{font-size:1.24rem;color:#29364a;margin:.15rem 0}.junaeb-hero p{margin:0;color:#748195}.hero-metrics{display:flex;gap:.55rem}.hero-metrics>div{min-width:76px;background:rgba(255,255,255,.78);border:1px solid #eee4c8;border-radius:13px;padding:.6rem;text-align:center}.hero-metrics strong,.hero-metrics small{display:block}.hero-metrics strong{font-size:1.25rem;color:#695582}.hero-metrics small{font-size:.65rem;color:#7f8997}.junaeb-tabs{display:flex;overflow:auto;padding:.55rem .8rem;border-bottom:1px solid #e8edf3;gap:.3rem}.junaeb-tabs button{border:0;background:transparent;color:#627084;padding:.55rem .75rem;border-radius:10px;white-space:nowrap}.junaeb-tabs button.active{background:#f0ebfa;color:#5f4a86;font-weight:700}.junaeb-tabs i{margin-right:.35rem}.panel{padding:1.2rem 1.4rem}.panel-head{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem}.panel-head h3{font-size:1rem;margin:0;color:#2b394d}.panel-head p{font-size:.8rem;color:#778498;margin:.2rem 0 0}.coverage-total{display:inline-flex;padding:.32rem .62rem;border:1px solid #ded7ee;border-radius:999px;color:#665286;background:#f8f5ff;font-size:.7rem;font-weight:700;white-space:nowrap}.coverage-pager{display:flex;align-items:center;justify-content:flex-end;gap:.75rem;padding-top:.85rem;border-top:1px solid #edf0f4;color:#68758a;font-size:.75rem}thead th{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#728095}tbody td{padding:.8rem}td strong,td small{display:block}td small{color:#8591a0}.status,.origin,.pass-state,.yesno{display:inline-block;padding:.26rem .5rem;border-radius:999px;background:#eef2f6;color:#536174;font-size:.7rem;text-transform:capitalize}.origin.junaeb,.yesno.yes,.pass-state.has{background:#e4f8ef;color:#17714f;font-weight:700}.empty,.loading{text-align:center;padding:2rem;color:#748195}.device-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.device-grid article{display:flex;gap:.75rem;padding:1rem;border:1px solid #e7ecf2;border-radius:14px;background:#fbfcfe}.device-icon{display:grid;place-items:center;width:42px;height:42px;flex:0 0 42px;border-radius:12px;background:#eee9f8;color:#654f8e;font-size:1.35rem}.device-grid strong,.device-grid span,.device-grid small{display:block}.device-grid span{color:#5d4a80;font-weight:700;text-transform:capitalize}.device-grid small{color:#7b8796}.device-grid p{margin:.35rem 0 0;color:#566476;font-size:.8rem}.modal-overlay{position:fixed;inset:0;z-index:1090;background:rgba(15,23,42,.58);backdrop-filter:blur(5px);display:grid;place-items:center;padding:1rem}.modal-panel{width:min(650px,96vw);max-height:94vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 28px 80px rgba(15,23,42,.32)}.modal-panel>header{display:flex;justify-content:space-between;padding:1.15rem 1.35rem;border-bottom:1px solid #e8edf3}.modal-panel>header button{border:0;background:#f1f5f9;border-radius:50%;width:36px;height:36px;font-size:1.4rem}.form-body{padding:1.2rem 1.35rem}.form-body label{display:block;font-size:.74rem;font-weight:700;color:#526174;margin:.7rem 0 .3rem}.form-body label.check{display:flex;align-items:center;gap:.45rem}.selector-help{display:flex;align-items:center;gap:.35rem;margin:.45rem 0 0;padding:.55rem .65rem;border-radius:10px;background:#fff8e6;color:#755b1e;font-size:.72rem}.selector-help i{font-size:1rem}.modal-panel>footer{display:flex;justify-content:flex-end;gap:.6rem;padding:1rem 1.35rem;border-top:1px solid #e8edf3}@media(max-width:900px){.junaeb-hero{align-items:flex-start}.hero-metrics{display:none}.panel-head{align-items:flex-start;flex-direction:column}.device-grid{grid-template-columns:1fr}.panel{padding:.9rem}}
</style>
