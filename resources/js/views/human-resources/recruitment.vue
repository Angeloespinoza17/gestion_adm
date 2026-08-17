<script>
import axios from 'axios'
import Swal from 'sweetalert2'
import Layout from '../../layouts/main.vue'
import OperationalWorkspaceHeader from '../../components/operational/operational-workspace-header.vue'

const today = () => new Date().toISOString().slice(0, 10)

export default {
    components: { Layout, OperationalWorkspaceHeader },
    data() {
        return {
            loading: true, saving: false, activeTab: 'pipeline', search: '',
            summary: {}, candidates: [], vacancies: [], applications: [], interviews: [], profiles: [], cargos: [], users: [], catalogs: {}, capabilities: {},
            modalType: '', editing: null, form: {}, errors: {},
            importFile: null, importPreview: null, importing: false,
        }
    },
    computed: {
        filteredCandidates() { const q = this.normalize(this.search); return this.candidates.filter(item => !q || this.normalize(`${item.full_name} ${item.email || ''} ${item.desired_position || ''}`).includes(q)) },
        filteredVacancies() { const q = this.normalize(this.search); return this.vacancies.filter(item => !q || this.normalize(`${item.title} ${item.area || ''}`).includes(q)) },
        pipelineStages() {
            return ['cv_recibido', 'filtro_inicial', 'preseleccionado', 'entrevista_psicolaboral', 'decision', 'contratado'].map(stage => ({ stage, items: this.applications.filter(item => item.stage === stage) }))
        },
        recruitmentMetrics() {
            return [
                { label: 'Candidatos', value: this.summary.candidates || 0, note: 'En el banco de talento', icon: 'bx bx-group', tone: 'indigo' },
                { label: 'Vacantes activas', value: this.summary.active_vacancies || 0, note: 'Procesos abiertos', icon: 'bx bx-briefcase-alt-2', tone: 'sky' },
                { label: 'Postulaciones', value: this.summary.applications || 0, note: 'En seguimiento', icon: 'bx bx-git-branch', tone: 'violet' },
                { label: 'Entrevistas', value: this.summary.interviews || 0, note: 'Psicolaborales', icon: 'bx bx-conversation', tone: 'amber' },
                { label: 'Contratados', value: this.summary.hired || 0, note: 'Procesos finalizados', icon: 'bx bx-user-check', tone: 'emerald' },
                { label: 'Reconsiderables', value: this.summary.reconsiderable || 0, note: 'Talento recuperable', icon: 'bx bx-refresh', tone: 'rose' },
            ]
        },
        recruitmentTabs() {
            return [
                { value: 'pipeline', label: 'Pipeline', icon: 'bx bx-columns' },
                { value: 'candidates', label: 'Banco de CV', icon: 'bx bx-id-card' },
                { value: 'vacancies', label: 'Vacantes', icon: 'bx bx-briefcase' },
                { value: 'interviews', label: 'Psicolaborales', icon: 'bx bx-conversation' },
                { value: 'profiles', label: 'Perfiles de cargo', icon: 'bx bx-file' },
                { value: 'import', label: 'Importación', icon: 'bx bx-upload' },
            ]
        },
        modalTitle() {
            const labels = { candidate: 'candidato', vacancy: 'vacante', application: 'postulación', interview: 'entrevista psicolaboral', profile: 'perfil de cargo' }
            return `${this.editing ? 'Editar' : 'Crear'} ${labels[this.modalType] || ''}`
        },
    },
    mounted() { this.load() },
    methods: {
        async load() {
            this.loading = true
            try {
                const { data } = await axios.get('/api/human-resources/recruitment')
                const p = data.data
                this.summary = p.summary; this.candidates = p.candidates; this.vacancies = p.vacancies; this.applications = p.applications
                this.interviews = p.interviews; this.profiles = p.job_profiles; this.cargos = p.cargos; this.users = p.users; this.catalogs = p.catalogs; this.capabilities = p.capabilities
            } catch (error) { this.alertError(error) } finally { this.loading = false }
        },
        openModal(type, item = null) {
            this.modalType = type; this.editing = item; this.errors = {}
            if (type === 'candidate') this.form = item ? { ...item } : { full_name: '', rut: '', email: '', phone: '', source: '', desired_position: '', specialty: '', experience_years: '', availability: '', rating: '', status: 'banco_talento', notes: '' }
            if (type === 'vacancy') this.form = item ? { ...item } : { job_profile_id: '', cargo_id: '', responsible_user_id: '', title: '', area: '', vacancy_count: 1, employment_type: '', weekly_hours: '', reason: '', opened_on: today(), target_start_on: '', closes_on: '', status: 'abierta', description: '', requirements: '' }
            if (type === 'application') this.form = item ? { ...item } : { vacancy_id: '', cv_bank_entry_id: '', stage: 'cv_recibido', source: 'Registro manual', applied_on: today(), score: '', reconsideration: '', reconsideration_notes: '', outcome: '', notes: '' }
            if (type === 'interview') this.form = item ? { ...item, scheduled_at: this.localDateTime(item.scheduled_at), completed_at: this.localDateTime(item.completed_at) } : { application_id: '', interviewer_user_id: '', scheduled_at: '', completed_at: '', interviewer_name: '', result: 'pendiente', induction_required: false, reconsideration: '', considerations: '', confidential_notes: '', status: 'programada', source: 'Registro manual' }
            if (type === 'profile') this.form = item ? { ...item, responsibilities_text: (item.responsibilities || []).join('\n'), requirements_text: (item.requirements || []).join('\n'), competencies_text: (item.competencies || []).join('\n') } : { cargo_id: '', code: '', title: '', area: '', purpose: '', responsibilities_text: '', requirements_text: '', competencies_text: '', version: '1.0', status: 'vigente', notes: '' }
        },
        closeModal() { this.modalType = ''; this.editing = null; this.form = {} },
        async saveModal() {
            this.saving = true; this.errors = {}
            try {
                let entity = `${this.modalType}s`; let payload = { ...this.form }
                if (this.modalType === 'candidate') entity = 'candidates'
                if (this.modalType === 'vacancy') entity = 'vacancies'
                if (this.modalType === 'application') entity = 'applications'
                if (this.modalType === 'interview') entity = 'interviews'
                if (this.modalType === 'profile') {
                    entity = 'job-profiles'
                    payload.responsibilities = this.lines(payload.responsibilities_text); payload.requirements = this.lines(payload.requirements_text); payload.competencies = this.lines(payload.competencies_text)
                    delete payload.responsibilities_text; delete payload.requirements_text; delete payload.competencies_text
                }
                ;['id', 'created_at', 'updated_at', 'deleted_at', 'applications', 'applications_count', 'cargo', 'job_profile', 'responsible', 'candidate', 'vacancy', 'interviews', 'interviewer', 'report_available'].forEach(key => delete payload[key])
                Object.keys(payload).forEach(key => payload[key] === '' && (payload[key] = null))
                const base = `/api/human-resources/recruitment/${entity}`
                const { data } = this.editing ? await axios.put(`${base}/${this.editing.id}`, payload) : await axios.post(base, payload)
                this.closeModal(); await this.load(); Swal.fire({ icon: 'success', title: data.message, timer: 1400, showConfirmButton: false })
            } catch (error) {
                this.errors = error.response?.data?.errors || {}
                if (!Object.keys(this.errors).length) this.alertError(error)
            } finally { this.saving = false }
        },
        async uploadCv(event, candidate) {
            const file = event.target.files?.[0]; if (!file) return
            const body = new FormData(); body.append('file', file)
            try { const { data } = await axios.post(`/api/human-resources/recruitment/candidates/${candidate.id}/cv`, body); await this.load(); Swal.fire({ icon: 'success', title: data.message, timer: 1400, showConfirmButton: false }) }
            catch (error) { this.alertError(error) } finally { event.target.value = '' }
        },
        async uploadReport(event, interview) {
            const file = event.target.files?.[0]; if (!file) return
            const body = new FormData(); body.append('file', file)
            try { const { data } = await axios.post(`/api/human-resources/recruitment/interviews/${interview.id}/report`, body); await this.load(); Swal.fire({ icon: 'success', title: data.message, timer: 1400, showConfirmButton: false }) }
            catch (error) { this.alertError(error) } finally { event.target.value = '' }
        },
        chooseImport(event) { this.importFile = event.target.files?.[0] || null; this.importPreview = null },
        async previewImport() {
            if (!this.importFile) return
            this.importing = true
            try { const body = new FormData(); body.append('file', this.importFile); const { data } = await axios.post('/api/human-resources/imports/recruitment/preview', body); this.importPreview = data.data }
            catch (error) { this.alertError(error) } finally { this.importing = false }
        },
        async commitImport() {
            this.importing = true
            try {
                const { data } = await axios.post('/api/human-resources/imports/recruitment/commit', { token: this.importPreview.token })
                this.importPreview = null; this.importFile = null; await this.load()
                Swal.fire({ icon: 'success', title: data.message, text: `${data.data.interviews_created} entrevistas y ${data.data.candidates_created} candidatos nuevos.` })
            } catch (error) { this.alertError(error) } finally { this.importing = false }
        },
        display(value) { return String(value || '—').replaceAll('_', ' ') },
        candidateName(application) { return application.candidate?.full_name || this.candidates.find(c => c.id === application.cv_bank_entry_id)?.full_name || 'Candidato' },
        vacancyName(application) { return application.vacancy?.title || this.vacancies.find(v => v.id === application.vacancy_id)?.title || 'Banco de talento' },
        resultClass(result) { if (result === 'apto') return 'bg-success-subtle text-success'; if (result === 'no_apto') return 'bg-danger-subtle text-danger'; if (result === 'apto_con_observaciones') return 'bg-warning-subtle text-warning'; return 'bg-secondary-subtle text-secondary' },
        normalize(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() },
        lines(value) { return String(value || '').split('\n').map(x => x.trim()).filter(Boolean) },
        localDateTime(value) { return value ? String(value).slice(0, 16).replace(' ', 'T') : '' },
        formatDate(value) {
            if (!value) return '—'
            const parts = String(value).slice(0, 10).split('-')
            return parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : String(value)
        },
        initials(name) { return String(name || 'SN').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase() },
        alertError(error) { Swal.fire({ icon: 'error', title: 'No fue posible completar la acción', text: error.response?.data?.message || 'Revisa los datos e inténtalo nuevamente.' }) },
    },
}
</script>

<template>
    <Layout>
        <div class="container-fluid py-3 operational-workspace recruitment-workspace">
        <OperationalWorkspaceHeader
            title="Selección y banco de talento"
            subtitle="Perfiles de cargo, vacantes, currículum, postulaciones y entrevistas psicolaborales con trazabilidad completa."
            icon="bx bx-group"
        >
            <template #actions>
                <button v-if="capabilities.manage" class="btn btn-outline-light" @click="openModal('vacancy')"><i class="bx bx-briefcase-alt-2 me-1"></i>Nueva vacante</button>
                <button v-if="capabilities.manage" class="btn btn-light" @click="openModal('candidate')"><i class="bx bx-user-plus me-1"></i>Agregar candidato</button>
            </template>
        </OperationalWorkspaceHeader>

        <div class="row g-3 mb-4"><div v-for="metric in recruitmentMetrics" :key="metric.label" class="col-6 col-md-4 col-xl-2"><div class="op-metric-card" :class="`op-tone-${metric.tone}`"><div class="op-metric-top"><span class="op-metric-label">{{ metric.label }}</span><span class="op-metric-icon"><i :class="metric.icon"></i></span></div><div class="op-metric-value">{{ metric.value }}</div><div class="op-metric-note">{{ metric.note }}</div></div></div></div>

        <div class="card op-surface"><div class="card-header bg-white border-0 p-3 pb-0"><div class="d-flex flex-wrap justify-content-between gap-3"><ul class="nav op-section-tabs"><li v-for="tab in recruitmentTabs" :key="tab.value" class="nav-item"><button v-if="tab.value !== 'import' || capabilities.import" class="nav-link" :class="{active:activeTab===tab.value}" @click="activeTab=tab.value"><i :class="tab.icon"></i>{{ tab.label }}</button></li></ul><div v-if="!['pipeline','import'].includes(activeTab)" class="recruitment-search"><i class="bx bx-search"></i><input v-model="search" class="form-control" placeholder="Buscar en esta sección…"></div></div></div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

                <div v-else-if="activeTab === 'pipeline'" class="pipeline-wrap"><div v-for="column in pipelineStages" :key="column.stage" class="pipeline-column"><div class="d-flex justify-content-between mb-2"><strong class="text-capitalize">{{ display(column.stage) }}</strong><span class="badge bg-light text-dark">{{ column.items.length }}</span></div><div v-for="application in column.items" :key="application.id" class="pipeline-card"><strong>{{ candidateName(application) }}</strong><div class="small text-muted mt-1">{{ vacancyName(application) }}</div><div class="d-flex justify-content-between align-items-center mt-3"><span v-if="application.score" class="badge bg-info-subtle text-info">{{ application.score }} pts</span><span v-else></span><button v-if="capabilities.manage" class="btn btn-sm btn-light" @click="openModal('application', application)"><i class="bx bx-edit"></i></button></div></div><div v-if="!column.items.length" class="text-center text-muted small border rounded p-3">Sin postulaciones</div></div></div>

                <div v-else-if="activeTab === 'candidates'"><div class="table-responsive"><table class="table table-hover align-middle op-data-table"><thead><tr><th>Candidato/a</th><th>Perfil de interés</th><th>Contacto</th><th>Disponibilidad</th><th>Estado</th><th>CV</th><th class="text-end"></th></tr></thead><tbody><tr v-for="candidate in filteredCandidates" :key="candidate.id"><td><div class="op-person"><span class="op-person-avatar">{{ initials(candidate.full_name) }}</span><div><strong>{{ candidate.full_name }}</strong><div class="small text-muted">{{ candidate.rut || 'RUT no informado' }}</div></div></div></td><td>{{ candidate.desired_position || 'Sin definir' }}<div class="small text-muted">{{ candidate.specialty }}</div></td><td>{{ candidate.email || '—' }}<div class="small text-muted">{{ candidate.phone }}</div></td><td>{{ candidate.availability || 'Por consultar' }}</td><td><span class="badge bg-info-subtle text-info text-capitalize">{{ display(candidate.status) }}</span></td><td><a v-if="candidate.cv_path" class="btn btn-sm btn-light" :href="`/api/human-resources/recruitment/candidates/${candidate.id}/cv`" target="_blank"><i class="bx bx-download"></i></a><label v-if="capabilities.manage" class="btn btn-sm btn-outline-secondary ms-1 mb-0" title="Adjuntar o reemplazar CV"><i class="bx bx-upload"></i><input type="file" accept=".pdf,.doc,.docx" hidden @change="uploadCv($event,candidate)"></label></td><td class="text-end"><button v-if="capabilities.manage" class="btn btn-sm btn-light me-1" title="Crear postulación" @click="openModal('application')"><i class="bx bx-git-branch"></i></button><button v-if="capabilities.manage" class="btn btn-sm btn-light" @click="openModal('candidate', candidate)"><i class="bx bx-edit"></i></button></td></tr><tr v-if="!filteredCandidates.length"><td colspan="7" class="op-empty-state"><i class="bx bx-user-x"></i>No hay candidatos.</td></tr></tbody></table></div></div>

                <div v-else-if="activeTab === 'vacancies'"><div class="row g-3"><div v-for="vacancy in filteredVacancies" :key="vacancy.id" class="col-md-6 col-xl-4"><div class="vacancy-card"><div class="d-flex justify-content-between gap-2"><div><span class="badge bg-primary-subtle text-primary text-capitalize">{{ display(vacancy.status) }}</span><h5 class="mt-2 mb-1">{{ vacancy.title }}</h5><div class="text-muted small"><i class="bx bx-buildings me-1"></i>{{ vacancy.area || vacancy.cargo?.name || 'Área por definir' }}</div></div><button v-if="capabilities.manage" class="btn btn-sm btn-light align-self-start" @click="openModal('vacancy', vacancy)"><i class="bx bx-edit"></i></button></div><div class="row mt-3 text-center vacancy-stats"><div class="col"><strong>{{ vacancy.applications_count }}</strong><div class="small text-muted">postulaciones</div></div><div class="col"><strong>{{ vacancy.vacancy_count }}</strong><div class="small text-muted">cupos</div></div><div class="col"><strong>{{ vacancy.weekly_hours || '—' }}</strong><div class="small text-muted">horas</div></div></div></div></div><div v-if="!filteredVacancies.length" class="col-12 op-empty-state"><i class="bx bx-briefcase-alt"></i>No hay vacantes.</div></div></div>

                <div v-else-if="activeTab === 'interviews'"><div class="d-flex justify-content-end mb-3"><button v-if="capabilities.manage" class="btn btn-outline-primary" @click="openModal('interview')"><i class="bx bx-calendar-plus me-1"></i>Programar entrevista</button></div><div class="table-responsive"><table class="table align-middle op-data-table"><thead><tr><th>Candidato/a</th><th>Vacante</th><th>Resultado</th><th>Consideraciones</th><th>Reconsiderar</th><th>Informe</th><th></th></tr></thead><tbody><tr v-for="interview in interviews" :key="interview.id"><td><div class="op-person"><span class="op-person-avatar">{{ initials(interview.application?.candidate?.full_name) }}</span><div><strong>{{ interview.application?.candidate?.full_name }}</strong><div class="small text-muted text-capitalize">{{ display(interview.status) }} · {{ formatDate(interview.scheduled_at || interview.completed_at) }}</div></div></div></td><td>{{ interview.application?.vacancy?.title || 'Banco de talento' }}</td><td><span class="badge text-capitalize" :class="resultClass(interview.result)">{{ display(interview.result) }}</span><div v-if="interview.induction_required" class="small text-warning mt-1">Inducción sugerida</div></td><td class="considerations-cell">{{ interview.considerations || '—' }}</td><td class="text-capitalize">{{ interview.reconsideration || '—' }}</td><td><a v-if="interview.report_available && capabilities.confidential" class="btn btn-sm btn-light" :href="`/api/human-resources/recruitment/interviews/${interview.id}/report`" target="_blank"><i class="bx bx-lock-open-alt me-1"></i>Descargar</a><label v-if="capabilities.confidential" class="btn btn-sm btn-outline-secondary ms-1 mb-0" title="Adjuntar informe"><i class="bx bx-upload"></i><input type="file" accept=".pdf,.doc,.docx" hidden @change="uploadReport($event,interview)"></label></td><td><button v-if="capabilities.manage" class="btn btn-sm btn-light" @click="openModal('interview', interview)"><i class="bx bx-edit"></i></button></td></tr><tr v-if="!interviews.length"><td colspan="7" class="op-empty-state"><i class="bx bx-conversation"></i>No hay entrevistas registradas.</td></tr></tbody></table></div></div>

                <div v-else-if="activeTab === 'profiles'"><div class="d-flex justify-content-between mb-3"><p class="text-muted mb-0">Define el propósito, responsabilidades, requisitos y competencias esperadas de cada cargo.</p><button v-if="capabilities.manage" class="btn btn-outline-primary" @click="openModal('profile')"><i class="bx bx-plus me-1"></i>Nuevo perfil</button></div><div class="row g-3"><div v-for="profile in profiles.filter(p => !search || normalize(`${p.title} ${p.area}`).includes(normalize(search)))" :key="profile.id" class="col-md-6 col-xl-4"><div class="profile-card"><div class="d-flex justify-content-between"><div><span class="badge bg-light text-dark">{{ profile.code }}</span><h5 class="mt-2">{{ profile.title }}</h5></div><button v-if="capabilities.manage" class="btn btn-sm btn-light align-self-start" @click="openModal('profile', profile)"><i class="bx bx-edit"></i></button></div><p class="text-muted small mb-2">{{ profile.purpose || 'Propósito pendiente de completar.' }}</p><div class="small"><strong>{{ profile.cargo?.name || 'Sin cargo vinculado' }}</strong> · versión {{ profile.version }}</div></div></div></div></div>

                <div v-else class="row justify-content-center"><div class="col-xl-9"><div class="import-dropzone"><span class="import-dropzone-icon"><i class="bx bx-spreadsheet"></i></span><h5>Precargar entrevistas y banco de CV</h5><p class="text-muted">Se crearán candidatos sin duplicar nombres, perfiles preliminares, procesos históricos y entrevistas vinculadas.</p><input type="file" accept=".xlsx" class="form-control mb-3" @change="chooseImport"><button class="btn btn-primary" :disabled="!importFile || importing" @click="previewImport"><span v-if="importing" class="spinner-border spinner-border-sm me-2"></span>Generar vista previa</button></div><div v-if="importPreview" class="mt-4"><div class="alert alert-info"><strong>{{ importPreview.importable_rows }}</strong> filas listas: {{ importPreview.summary.interviews }} entrevistas, {{ importPreview.summary.cv_entries }} currículum y {{ importPreview.summary.positions }} perfiles/cargos históricos.</div><div class="table-responsive import-preview"><table class="table table-sm op-data-table"><thead><tr><th>Tipo</th><th>Nombre</th><th>Cargo / perfil</th><th>Resultado</th><th>Contratación</th></tr></thead><tbody><tr v-for="(row,index) in importPreview.rows" :key="index"><td class="text-capitalize">{{ display(row.record_type) }}</td><td>{{ row.name }}</td><td>{{ row.position }}</td><td>{{ display(row.result) }}</td><td>{{ row.hiring_outcome || '—' }}</td></tr></tbody></table></div><button class="btn btn-success mt-3" :disabled="importing" @click="commitImport">Confirmar precarga</button></div></div></div>
            </div>
        </div>
        </div>

        <Teleport to="body"><div v-if="modalType" class="hr-modal-backdrop" @mousedown.self="closeModal"><div class="hr-modal card shadow-lg"><div class="card-header op-modal-header d-flex justify-content-between"><h5 class="mb-0">{{ modalTitle }}</h5><button class="btn-close" @click="closeModal"></button></div><form @submit.prevent="saveModal"><div class="card-body modal-scroll"><div class="row g-3">
            <template v-if="modalType === 'candidate'"><div class="col-md-8"><label class="form-label">Nombre completo</label><input v-model="form.full_name" class="form-control" required></div><div class="col-md-4"><label class="form-label">RUT</label><input v-model="form.rut" class="form-control"></div><div class="col-md-6"><label class="form-label">Correo</label><input v-model="form.email" type="email" class="form-control"></div><div class="col-md-6"><label class="form-label">Teléfono</label><input v-model="form.phone" class="form-control"></div><div class="col-md-6"><label class="form-label">Cargo de interés</label><input v-model="form.desired_position" class="form-control"></div><div class="col-md-6"><label class="form-label">Especialidad</label><input v-model="form.specialty" class="form-control"></div><div class="col-md-4"><label class="form-label">Experiencia (años)</label><input v-model="form.experience_years" type="number" step="0.5" class="form-control"></div><div class="col-md-4"><label class="form-label">Valoración</label><select v-model="form.rating" class="form-select"><option :value="null">Sin valoración</option><option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option></select></div><div class="col-md-4"><label class="form-label">Estado</label><select v-model="form.status" class="form-select" required><option v-for="status in catalogs.candidate_statuses" :key="status" :value="status">{{ display(status) }}</option></select></div><div class="col-md-6"><label class="form-label">Disponibilidad</label><input v-model="form.availability" class="form-control"></div><div class="col-md-6"><label class="form-label">Fuente</label><input v-model="form.source" class="form-control"></div><div class="col-12"><label class="form-label">Notas</label><textarea v-model="form.notes" rows="3" class="form-control"></textarea></div></template>
            <template v-if="modalType === 'vacancy'"><div class="col-md-8"><label class="form-label">Título de la vacante</label><input v-model="form.title" class="form-control" required></div><div class="col-md-4"><label class="form-label">Estado</label><select v-model="form.status" class="form-select" required><option v-for="status in catalogs.vacancy_statuses" :key="status" :value="status">{{ display(status) }}</option></select></div><div class="col-md-6"><label class="form-label">Perfil de cargo</label><select v-model="form.job_profile_id" class="form-select"><option :value="null">Sin perfil</option><option v-for="profile in profiles" :key="profile.id" :value="profile.id">{{ profile.title }}</option></select></div><div class="col-md-6"><label class="form-label">Cargo institucional</label><select v-model="form.cargo_id" class="form-select"><option :value="null">Sin cargo</option><option v-for="cargo in cargos" :key="cargo.id" :value="cargo.id">{{ cargo.name }}</option></select></div><div class="col-md-4"><label class="form-label">Área</label><input v-model="form.area" class="form-control"></div><div class="col-md-4"><label class="form-label">Cantidad de vacantes</label><input v-model="form.vacancy_count" type="number" min="1" class="form-control" required></div><div class="col-md-4"><label class="form-label">Horas semanales</label><input v-model="form.weekly_hours" type="number" step="0.5" class="form-control"></div><div class="col-md-4"><label class="form-label">Apertura</label><input v-model="form.opened_on" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Inicio esperado</label><input v-model="form.target_start_on" type="date" class="form-control"></div><div class="col-md-4"><label class="form-label">Cierre</label><input v-model="form.closes_on" type="date" class="form-control"></div><div class="col-md-6"><label class="form-label">Responsable</label><select v-model="form.responsible_user_id" class="form-select"><option :value="null">Usuario actual</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div><div class="col-md-6"><label class="form-label">Motivo</label><input v-model="form.reason" class="form-control"></div><div class="col-12"><label class="form-label">Descripción</label><textarea v-model="form.description" rows="2" class="form-control"></textarea></div><div class="col-12"><label class="form-label">Requisitos</label><textarea v-model="form.requirements" rows="2" class="form-control"></textarea></div></template>
            <template v-if="modalType === 'application'"><div class="col-md-6"><label class="form-label">Candidato/a</label><select v-model="form.cv_bank_entry_id" class="form-select" required><option value="" disabled>Seleccionar</option><option v-for="candidate in candidates" :key="candidate.id" :value="candidate.id">{{ candidate.full_name }}</option></select></div><div class="col-md-6"><label class="form-label">Vacante</label><select v-model="form.vacancy_id" class="form-select"><option :value="null">Banco de talento (sin vacante)</option><option v-for="vacancy in vacancies" :key="vacancy.id" :value="vacancy.id">{{ vacancy.title }}</option></select></div><div class="col-md-6"><label class="form-label">Etapa</label><select v-model="form.stage" class="form-select" required><option v-for="stage in catalogs.application_stages" :key="stage" :value="stage">{{ display(stage) }}</option></select></div><div class="col-md-3"><label class="form-label">Fecha</label><input v-model="form.applied_on" type="date" class="form-control"></div><div class="col-md-3"><label class="form-label">Puntaje</label><input v-model="form.score" type="number" min="0" max="100" class="form-control"></div><div class="col-md-6"><label class="form-label">¿Volver a considerar?</label><select v-model="form.reconsideration" class="form-select"><option :value="null">Sin definir</option><option value="si">Sí</option><option value="condicional">Condicional</option><option value="no">No</option></select></div><div class="col-md-6"><label class="form-label">Resultado</label><input v-model="form.outcome" class="form-control"></div><div class="col-12"><label class="form-label">Criterio de reconsideración</label><textarea v-model="form.reconsideration_notes" rows="2" class="form-control"></textarea></div><div class="col-12"><label class="form-label">Notas</label><textarea v-model="form.notes" rows="2" class="form-control"></textarea></div></template>
            <template v-if="modalType === 'interview'"><div class="col-12"><label class="form-label">Postulación</label><select v-model="form.application_id" class="form-select" required><option value="" disabled>Seleccionar</option><option v-for="application in applications" :key="application.id" :value="application.id">{{ candidateName(application) }} · {{ vacancyName(application) }}</option></select></div><div class="col-md-6"><label class="form-label">Fecha programada</label><input v-model="form.scheduled_at" type="datetime-local" class="form-control"></div><div class="col-md-6"><label class="form-label">Fecha realizada</label><input v-model="form.completed_at" type="datetime-local" class="form-control"></div><div class="col-md-6"><label class="form-label">Entrevistador/a</label><select v-model="form.interviewer_user_id" class="form-select"><option :value="null">Externo/a o sin asignar</option><option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div><div class="col-md-6"><label class="form-label">Nombre externo</label><input v-model="form.interviewer_name" class="form-control"></div><div class="col-md-4"><label class="form-label">Resultado</label><select v-model="form.result" class="form-select"><option v-for="result in catalogs.interview_results" :key="result" :value="result">{{ display(result) }}</option></select></div><div class="col-md-4"><label class="form-label">Estado</label><select v-model="form.status" class="form-select"><option v-for="status in ['programada','realizada','pendiente_informe','completada','cancelada']" :key="status" :value="status">{{ display(status) }}</option></select></div><div class="col-md-4"><label class="form-label">Reconsideración</label><select v-model="form.reconsideration" class="form-select"><option :value="null">Sin definir</option><option value="si">Sí</option><option value="condicional">Condicional</option><option value="no">No</option></select></div><div class="col-12 form-check ms-2"><input id="induction" v-model="form.induction_required" type="checkbox" class="form-check-input"><label for="induction" class="form-check-label">Requiere o sugiere inducción</label></div><div class="col-12"><label class="form-label">Consideraciones compartibles</label><textarea v-model="form.considerations" rows="3" class="form-control"></textarea></div><div v-if="capabilities.confidential" class="col-12"><label class="form-label"><i class="bx bx-lock-alt me-1"></i>Notas confidenciales</label><textarea v-model="form.confidential_notes" rows="3" class="form-control"></textarea><div class="form-text">Solo usuarios con autorización psicolaboral pueden ver este campo.</div></div></template>
            <template v-if="modalType === 'profile'"><div class="col-md-8"><label class="form-label">Nombre del perfil</label><input v-model="form.title" class="form-control" required></div><div class="col-md-4"><label class="form-label">Código</label><input v-model="form.code" class="form-control" :disabled="!!editing" placeholder="Automático"></div><div class="col-md-6"><label class="form-label">Cargo institucional</label><select v-model="form.cargo_id" class="form-select"><option :value="null">Sin vincular</option><option v-for="cargo in cargos" :key="cargo.id" :value="cargo.id">{{ cargo.name }}</option></select></div><div class="col-md-3"><label class="form-label">Versión</label><input v-model="form.version" class="form-control" required></div><div class="col-md-3"><label class="form-label">Estado</label><select v-model="form.status" class="form-select"><option value="borrador">Borrador</option><option value="vigente">Vigente</option><option value="obsoleto">Obsoleto</option></select></div><div class="col-12"><label class="form-label">Propósito del cargo</label><textarea v-model="form.purpose" rows="2" class="form-control"></textarea></div><div class="col-md-4"><label class="form-label">Responsabilidades (una por línea)</label><textarea v-model="form.responsibilities_text" rows="6" class="form-control"></textarea></div><div class="col-md-4"><label class="form-label">Requisitos (uno por línea)</label><textarea v-model="form.requirements_text" rows="6" class="form-control"></textarea></div><div class="col-md-4"><label class="form-label">Competencias (una por línea)</label><textarea v-model="form.competencies_text" rows="6" class="form-control"></textarea></div><div class="col-12"><label class="form-label">Notas</label><textarea v-model="form.notes" rows="2" class="form-control"></textarea></div></template>
            <div v-if="Object.keys(errors).length" class="col-12"><div class="alert alert-danger mb-0"><div v-for="(messages,field) in errors" :key="field">{{ messages[0] }}</div></div></div>
        </div></div><div class="card-footer bg-white text-end"><button type="button" class="btn btn-light me-2" @click="closeModal">Cancelar</button><button class="btn btn-primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>Guardar</button></div></form></div></div></Teleport>
    </Layout>
</template>

<style scoped>
.recruitment-search { position: relative; width: min(270px, 100%); }
.recruitment-search i { position: absolute; z-index: 2; top: 50%; left: .8rem; color: #94a3b8; font-size: 1.1rem; transform: translateY(-50%); }
.recruitment-search .form-control { height: 43px; padding-left: 2.45rem; border-color: #dfe4ed; border-radius: .72rem; }
.pipeline-wrap { display: grid; grid-template-columns: repeat(6, minmax(245px, 1fr)); gap: 1rem; overflow-x: auto; padding: .15rem .1rem .75rem; }
.pipeline-column { min-height: 340px; padding: .85rem; border: 1px solid #e8ecf3; border-radius: .9rem; background: linear-gradient(180deg, #f8faff, #f5f7fb); }
.pipeline-card { position: relative; overflow: hidden; margin-bottom: .7rem; padding: .9rem; border: 1px solid #e7ebf2; border-radius: .75rem; background: #fff; box-shadow: 0 4px 14px rgba(15,23,42,.045); }
.pipeline-card::before { position: absolute; top: 0; bottom: 0; left: 0; width: 3px; background: #6670d8; content: ''; }
.vacancy-card, .profile-card { height: 100%; padding: 1.1rem; border: 1px solid #e6eaf2; border-radius: .9rem; background: linear-gradient(155deg, #fff, #fbfcff); box-shadow: 0 5px 18px rgba(30,41,80,.045); transition: transform .18s ease, box-shadow .18s ease; }
.vacancy-card:hover, .profile-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(30,41,80,.08); }
.vacancy-stats { padding-top: .85rem; border-top: 1px solid #edf0f5; }
.considerations-cell { min-width: 220px; max-width: 360px; white-space: normal; }
.import-preview { max-height: 420px; }
.import-dropzone { padding: 2rem; border: 1px dashed #bfc8e8; border-radius: 1rem; background: linear-gradient(145deg, #f8faff, #f3f5ff); text-align: center; }
.import-dropzone-icon { display: grid; place-items: center; width: 3.25rem; height: 3.25rem; margin: 0 auto .8rem; border-radius: 1rem; color: #4f46b8; background: #e8ebff; font-size: 1.6rem; }
.hr-modal-backdrop { position: fixed; inset: 0; z-index: 1060; background: rgba(15,23,42,.6); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: flex-start; padding: 4vh 1rem; overflow-y: auto; }
.hr-modal { overflow: hidden; width: min(880px, 100%); max-height: 92vh; border: 0; border-radius: 1.1rem; }
.modal-scroll { overflow-y: auto; }
.form-label { color: #475569; font-size: .78rem; font-weight: 650; }
</style>
