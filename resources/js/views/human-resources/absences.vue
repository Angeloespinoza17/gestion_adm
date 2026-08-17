<script>
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from '@vueform/multiselect'
import Layout from '../../layouts/main.vue'
import OperationalWorkspaceHeader from '../../components/operational/operational-workspace-header.vue'
import OperationalCalendar from '../../components/operational/operational-calendar.vue'
import { getPdfMake } from '../../utils/pdfmake'

const emptyRecord = () => ({
    staff_id: '', absence_type: 'dia_administrativo', starts_on: '', ends_on: '',
    starts_at: '', ends_at: '', quantity: 1, unit: 'dias', rest_type: '',
    status: 'justificada', affects_attendance: true, affects_payroll: false, notes: '',
})

export default {
    components: { Layout, OperationalWorkspaceHeader, OperationalCalendar, Multiselect },
    data() {
        return {
            loading: true, saving: false, activeTab: 'records',
            summary: {}, records: { data: [], current_page: 1, last_page: 1 }, balances: [], staff: [], catalogs: { types: [], statuses: [] }, capabilities: {},
            calendarRecords: [], calendarLoading: false, calendarRange: null, calendarLoadedRange: '', selectedCalendarRecord: null,
            filters: { year: new Date().getFullYear(), type: '', status: '', staff_id: '', search: '', per_page: 25, page: 1 },
            showRecordModal: false, editingRecord: null, recordForm: emptyRecord(), errors: {},
            showBalanceModal: false, balanceForm: {},
            importFile: null, importPreview: null, importing: false,
        }
    },
    computed: {
        typeMap() { return Object.fromEntries((this.catalogs.types || []).map(item => [item.value, item.label])) },
        statusMap() { return Object.fromEntries((this.catalogs.statuses || []).map(item => [item.value, item.label])) },
        typeTotals() { return Object.fromEntries((this.summary.by_type || []).map(item => [item.absence_type, Number(item.quantity || 0)])) },
        staffOptions() {
            return (this.staff || []).map(person => ({
                value: person.id,
                label: `${person.full_name} · ${person.rut || 'Sin RUT'}`,
            }))
        },
        hourlyCalendarStyles() { return this.buildHourlyOverlapStyles(this.calendarRecords) },
        absenceMetrics() {
            return [
                { label: `Registros ${this.summary.year || this.filters.year}`, value: this.summary.records || 0, note: 'Movimientos del período', icon: 'bx bx-clipboard', tone: 'indigo' },
                { label: 'Personas con ausencia', value: this.summary.people || 0, note: 'Funcionarios/as distintos', icon: 'bx bx-group', tone: 'sky' },
                { label: 'Días registrados', value: this.formatNumber(this.summary.days), note: 'Total consolidado', icon: 'bx bx-calendar', tone: 'violet' },
                { label: 'Días administrativos', value: this.formatNumber(this.typeTotals.dia_administrativo), note: 'Consumidos en el año', icon: 'bx bx-calendar-check', tone: 'emerald' },
                { label: 'Pendientes', value: this.summary.pending || 0, note: 'Requieren regularización', icon: 'bx bx-time-five', tone: 'amber' },
            ]
        },
        absenceCalendarEvents() {
            return this.calendarRecords.map(record => {
                const hourly = record.unit === 'horas' && record.starts_at && record.ends_at
                const hourlyStyle = hourly ? this.hourlyCalendarStyles[record.id] : null
                const color = hourlyStyle?.color || this.absenceColor(record.absence_type)
                const startDate = String(record.starts_on || '').slice(0, 10)
                const endDate = String(record.ends_on || record.starts_on || '').slice(0, 10)
                const schedule = hourly ? ` · ${this.formatTime(record.starts_at)} a ${this.formatTime(record.ends_at)}` : ''
                const overlap = hourlyStyle?.overlapCount ? ` · Coincide con ${hourlyStyle.overlapCount} permiso(s)` : ''
                return {
                    id: `absence-${record.id}`,
                    title: `${record.staff?.full_name || 'Funcionario/a'} · ${this.typeMap[record.absence_type] || record.absence_type}`,
                    start: hourly ? `${startDate}T${this.timeForInput(record.starts_at)}` : startDate,
                    end: hourly ? `${endDate}T${this.timeForInput(record.ends_at)}` : this.addDays(endDate, 1),
                    allDay: !hourly,
                    backgroundColor: color,
                    borderColor: hourlyStyle?.border || color,
                    textColor: '#fff',
                    classNames: hourly ? ['absence-hourly-event', ...(hourlyStyle?.overlapCount ? ['absence-hourly-overlap'] : [])] : [],
                    extendedProps: {
                        record,
                        overlapCount: hourlyStyle?.overlapCount || 0,
                        tooltip: `${record.staff?.full_name || 'Funcionario/a'} · ${this.typeMap[record.absence_type] || record.absence_type}${schedule}${overlap} · ${this.statusMap[record.status] || record.status}`,
                    },
                }
            })
        },
    },
    mounted() { this.load() },
    methods: {
        async load(page = 1) {
            this.loading = true
            try {
                const params = { ...this.filters, page }
                Object.keys(params).forEach(key => (params[key] === '' || params[key] == null) && delete params[key])
                const { data } = await axios.get('/api/human-resources/absences', { params })
                const payload = data.data
                this.summary = payload.summary; this.records = payload.records; this.balances = payload.balances
                this.staff = payload.staff; this.catalogs = payload.catalogs; this.capabilities = payload.capabilities
                this.filters.page = page
            } catch (error) { this.alertError(error) } finally { this.loading = false }
        },
        async loadCalendar(range = null) {
            if (range) {
                const rangeKey = `${range.from}:${range.to}`
                if (rangeKey === this.calendarLoadedRange) return
                if (rangeKey === `${this.calendarRange?.from}:${this.calendarRange?.to}` && this.calendarLoading) return
                this.calendarRange = range
            }
            if (!this.calendarRange) return
            this.calendarLoading = true
            try {
                const { year, per_page, page, ...calendarFilters } = this.filters
                const params = { ...calendarFilters, date_from: this.calendarRange.from, date_to: this.calendarRange.to }
                Object.keys(params).forEach(key => (params[key] === '' || params[key] == null) && delete params[key])
                const { data } = await axios.get('/api/human-resources/absences/calendar', { params })
                this.calendarRecords = data.data || []
                this.calendarLoadedRange = `${this.calendarRange.from}:${this.calendarRange.to}`
            } catch (error) { this.alertError(error) } finally { this.calendarLoading = false }
        },
        applyFilters() { return this.activeTab === 'calendar' ? this.loadCalendar() : this.load() },
        openRecord(record = null) {
            this.editingRecord = record
            this.errors = {}
            this.recordForm = record ? {
                staff_id: record.staff_id, absence_type: record.absence_type, starts_on: record.starts_on,
                ends_on: record.ends_on, starts_at: this.timeForInput(record.starts_at), ends_at: this.timeForInput(record.ends_at),
                quantity: Number(record.quantity), unit: record.unit, rest_type: record.rest_type || '', status: record.status,
                affects_attendance: !!record.affects_attendance, affects_payroll: !!record.affects_payroll, notes: record.notes || '',
            } : emptyRecord()
            this.showRecordModal = true
        },
        async saveRecord() {
            if (!this.recordForm.staff_id) {
                this.errors = { staff_id: ['Selecciona un funcionario o funcionaria.'] }
                return
            }
            if (this.recordForm.unit === 'horas' && !this.recalculateHourQuantity()) {
                this.errors = { ends_at: ['La hora de término debe ser posterior a la hora de inicio.'] }
                return
            }
            this.saving = true; this.errors = {}
            try {
                const url = this.editingRecord ? `/api/human-resources/absences/${this.editingRecord.id}` : '/api/human-resources/absences'
                const method = this.editingRecord ? 'put' : 'post'
                const { data } = await axios[method](url, this.recordForm)
                this.showRecordModal = false
                await this.load(this.records.current_page)
                if (this.activeTab === 'calendar') await this.loadCalendar()
                Swal.fire({ icon: 'success', title: data.message, timer: 1500, showConfirmButton: false })
            } catch (error) {
                this.errors = error.response?.data?.errors || {}
                if (!Object.keys(this.errors).length) this.alertError(error)
            } finally { this.saving = false }
        },
        async removeRecord(record) {
            const result = await Swal.fire({ title: '¿Anular este registro?', text: 'El consumo asociado será retirado del saldo.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar' })
            if (!result.isConfirmed) return
            try { await axios.delete(`/api/human-resources/absences/${record.id}`); await this.load(this.records.current_page); if (this.activeTab === 'calendar') await this.loadCalendar() }
            catch (error) { this.alertError(error) }
        },
        openBalance(balance = null) {
            this.balanceForm = balance ? { ...balance } : {
                staff_id: '', year: this.filters.year, administrative_entitlement: 0, administrative_adjustment: 0,
                compensatory_entitlement: 0, compensatory_adjustment: 0, notes: '',
            }
            this.showBalanceModal = true
        },
        async saveBalance() {
            this.saving = true
            try {
                const { staff_id, ...payload } = this.balanceForm
                const { data } = await axios.put(`/api/human-resources/absence-balances/${staff_id}`, payload)
                this.showBalanceModal = false; await this.load(); Swal.fire({ icon: 'success', title: data.message, timer: 1400, showConfirmButton: false })
            } catch (error) { this.alertError(error) } finally { this.saving = false }
        },
        chooseImport(event) { this.importFile = event.target.files?.[0] || null; this.importPreview = null },
        async previewImport() {
            if (!this.importFile) return
            this.importing = true
            try {
                const body = new FormData(); body.append('file', this.importFile)
                const { data } = await axios.post('/api/human-resources/imports/absences/preview', body)
                this.importPreview = data.data
            } catch (error) { this.alertError(error) } finally { this.importing = false }
        },
        async commitImport() {
            if (!this.importPreview?.token) return
            this.importing = true
            try {
                const { data } = await axios.post('/api/human-resources/imports/absences/commit', { token: this.importPreview.token })
                this.importPreview = null; this.importFile = null; await this.load()
                if (this.activeTab === 'calendar') await this.loadCalendar()
                Swal.fire({ icon: 'success', title: data.message, text: `${data.data.created} registros creados; ${data.data.skipped} omitidos.` })
            } catch (error) { this.alertError(error) } finally { this.importing = false }
        },
        exportCsv() { window.location.href = `/api/human-resources/absences/export?year=${this.filters.year}` },
        exportCalendarPdf() {
            if (!this.calendarRecords.length || !this.calendarRange) {
                Swal.fire({ icon: 'info', title: 'No hay ausencias visibles', text: 'Selecciona un período con registros antes de exportar.' })
                return
            }

            const records = [...this.calendarRecords].sort((left, right) => {
                const leftKey = `${String(left.starts_on).slice(0, 10)} ${this.timeForInput(left.starts_at) || '00:00'} ${left.staff?.full_name || ''}`
                const rightKey = `${String(right.starts_on).slice(0, 10)} ${this.timeForInput(right.starts_at) || '00:00'} ${right.staff?.full_name || ''}`
                return leftKey.localeCompare(rightKey, 'es')
            })
            const hourlyStyles = this.hourlyCalendarStyles
            const allDayCount = records.filter(record => record.unit !== 'horas' || !record.starts_at || !record.ends_at).length
            const hourlyCount = records.length - allDayCount
            const overlapCount = records.filter(record => Number(hourlyStyles[record.id]?.overlapCount || 0) > 0).length
            const peopleCount = new Set(records.map(record => record.staff_id)).size
            const rangeLabel = `${this.formatDate(this.calendarRange.from)} al ${this.formatDate(this.calendarRange.to)}`
            const filterParts = [
                this.filters.search ? `Funcionario: ${this.filters.search}` : '',
                this.filters.type ? `Tipo: ${this.typeMap[this.filters.type] || this.filters.type}` : '',
                this.filters.status ? `Estado: ${this.statusMap[this.filters.status] || this.filters.status}` : '',
            ].filter(Boolean)
            const metricCell = (value, label) => ({
                margin: [8, 7],
                fillColor: '#f3f5ff',
                stack: [
                    { text: String(value), fontSize: 15, bold: true, color: '#4652be' },
                    { text: label, fontSize: 7.5, color: '#66758a', margin: [0, 2, 0, 0] },
                ],
            })
            const tableBody = [[
                { text: '', fillColor: '#4652be' },
                { text: 'FECHA', style: 'tableHeader' },
                { text: 'JORNADA', style: 'tableHeader' },
                { text: 'FUNCIONARIO/A', style: 'tableHeader' },
                { text: 'TIPO', style: 'tableHeader' },
                { text: 'ESTADO', style: 'tableHeader' },
                { text: 'COINCIDENCIA', style: 'tableHeader' },
                { text: 'ORIGEN', style: 'tableHeader' },
            ]]

            records.forEach(record => {
                const hourly = record.unit === 'horas' && record.starts_at && record.ends_at
                const style = hourly ? hourlyStyles[record.id] : null
                const color = style?.color || this.absenceColor(record.absence_type)
                const dates = record.ends_on && record.ends_on !== record.starts_on
                    ? `${this.formatDate(record.starts_on)}\nal ${this.formatDate(record.ends_on)}`
                    : this.formatDate(record.starts_on)
                const schedule = hourly
                    ? `${this.formatTime(record.starts_at)} a ${this.formatTime(record.ends_at)}\n${this.formatNumber(record.quantity)} horas`
                    : `Todo el día\n${this.formatNumber(record.quantity)} días`
                const overlaps = Number(style?.overlapCount || 0)
                tableBody.push([
                    { text: '', fillColor: color },
                    { text: dates, style: 'tableCell' },
                    { text: schedule, style: 'tableCell' },
                    { stack: [
                        { text: record.staff?.full_name || 'Funcionario/a', bold: true, color: '#27344e', fontSize: 8 },
                        { text: record.staff?.rut || 'Sin RUT', color: '#7a8799', fontSize: 7, margin: [0, 2, 0, 0] },
                    ], margin: [4, 5] },
                    { text: this.typeMap[record.absence_type] || record.absence_type, style: 'tableCell' },
                    { text: this.statusMap[record.status] || record.status, style: 'tableCell' },
                    { text: overlaps ? `${overlaps + 1} simultáneos` : 'Sin cruce', style: 'tableCell', color: overlaps ? '#b45309' : '#66758a', bold: overlaps > 0 },
                    { text: record.source?.replaceAll('_', ' ') || 'No informado', style: 'tableCell' },
                ])
            })

            const definition = {
                pageSize: 'A4',
                pageOrientation: 'landscape',
                pageMargins: [30, 32, 30, 34],
                info: { title: `Calendario consolidado de ausencias - ${rangeLabel}` },
                footer: (currentPage, pageCount) => ({
                    columns: [
                        { text: 'CNSC Gestión - Gestión Operativa', color: '#8793a5', fontSize: 7.5, margin: [30, 0, 0, 0] },
                        { text: `Página ${currentPage} de ${pageCount}`, alignment: 'right', color: '#8793a5', fontSize: 7.5, margin: [0, 0, 30, 0] },
                    ],
                }),
                content: [
                    {
                        columns: [
                            { width: '*', stack: [
                                { text: 'CALENDARIO CONSOLIDADO DE AUSENCIAS', style: 'title' },
                                { text: rangeLabel, style: 'period' },
                                { text: filterParts.length ? `Filtros: ${filterParts.join(' - ')}` : 'Todos los tipos, estados y funcionarios/as', style: 'meta' },
                            ] },
                            { width: 180, stack: [
                                { text: 'GESTIÓN OPERATIVA', alignment: 'right', bold: true, color: '#5965cf', fontSize: 9 },
                                { text: `Emitido: ${new Date().toLocaleString('es-CL')}`, alignment: 'right', color: '#7a8799', fontSize: 7.5, margin: [0, 4, 0, 0] },
                            ] },
                        ],
                    },
                    { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 782, y2: 0, lineWidth: 1, lineColor: '#dfe4ee' }], margin: [0, 12, 0, 12] },
                    {
                        table: {
                            widths: ['*', '*', '*', '*'],
                            body: [[
                                metricCell(records.length, 'Registros visibles'),
                                metricCell(peopleCount, 'Personas'),
                                metricCell(allDayCount, 'Todo el día'),
                                metricCell(`${hourlyCount} / ${overlapCount}`, 'Por horas / con cruce'),
                            ]],
                        },
                        layout: { hLineWidth: () => 0, vLineWidth: () => 2, vLineColor: () => '#ffffff' },
                        margin: [0, 0, 0, 14],
                    },
                    { text: 'Detalle del período', style: 'sectionTitle' },
                    { text: 'Los permisos horarios coincidentes utilizan colores alternos para facilitar su lectura.', style: 'meta', margin: [0, 0, 0, 7] },
                    {
                        table: {
                            headerRows: 1,
                            dontBreakRows: true,
                            widths: [5, 62, 66, '*', 92, 74, 74, 78],
                            body: tableBody,
                        },
                        layout: {
                            fillColor: rowIndex => rowIndex === 0 ? '#4652be' : (rowIndex % 2 === 0 ? '#f8f9fc' : '#ffffff'),
                            hLineColor: () => '#e7ebf2',
                            vLineColor: () => '#e7ebf2',
                            hLineWidth: () => 0.6,
                            vLineWidth: () => 0.6,
                            paddingLeft: () => 4,
                            paddingRight: () => 4,
                            paddingTop: () => 4,
                            paddingBottom: () => 4,
                        },
                    },
                ],
                styles: {
                    title: { fontSize: 17, bold: true, color: '#27344e' },
                    period: { fontSize: 11, bold: true, color: '#5965cf', margin: [0, 4, 0, 0] },
                    meta: { fontSize: 7.5, color: '#7a8799', margin: [0, 3, 0, 0] },
                    sectionTitle: { fontSize: 10, bold: true, color: '#27344e', margin: [0, 0, 0, 2] },
                    tableHeader: { fontSize: 7, bold: true, color: '#ffffff', margin: [2, 4] },
                    tableCell: { fontSize: 7.2, color: '#445066', margin: [2, 4] },
                },
                defaultStyle: { font: 'Roboto' },
            }

            getPdfMake().createPdf(definition).download(`calendario-ausencias-${this.calendarRange.from}-al-${this.calendarRange.to}.pdf`)
        },
        labelClass(status) {
            if (['justificada', 'tramitada', 'cerrada'].includes(status)) return 'bg-success-subtle text-success'
            if (['pendiente', 'pendiente_regularizacion'].includes(status)) return 'bg-warning-subtle text-warning'
            return 'bg-secondary-subtle text-secondary'
        },
        formatNumber(value) { return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(Number(value || 0)) },
        formatDate(value) {
            if (!value) return '—'
            const raw = String(value).slice(0, 10)
            const parts = raw.split('-')
            return parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : raw
        },
        timeForInput(value) { return value ? String(value).slice(0, 5) : '' },
        formatTime(value) { return this.timeForInput(value) || '—' },
        handleStartDateChange() {
            if (this.recordForm.starts_on && (!this.recordForm.ends_on || this.recordForm.ends_on < this.recordForm.starts_on)) {
                this.recordForm.ends_on = this.recordForm.starts_on
            }
            this.recalculateHourQuantity()
        },
        changeRecordUnit() {
            if (this.recordForm.unit === 'horas') {
                if (this.recordForm.starts_on && !this.recordForm.ends_on) this.recordForm.ends_on = this.recordForm.starts_on
                if (!this.recordForm.starts_at) this.recordForm.starts_at = '08:00'
                if (!this.recordForm.ends_at) this.recordForm.ends_at = '09:00'
                this.recalculateHourQuantity()
                return
            }
            this.recordForm.starts_at = ''
            this.recordForm.ends_at = ''
            if (!Number(this.recordForm.quantity)) this.recordForm.quantity = 1
        },
        recalculateHourQuantity() {
            if (this.recordForm.unit !== 'horas') return true
            const { starts_on, ends_on, starts_at, ends_at } = this.recordForm
            if (!starts_on || !ends_on || !starts_at || !ends_at) return false
            const start = new Date(`${starts_on}T${starts_at}:00`)
            const end = new Date(`${ends_on}T${ends_at}:00`)
            const minutes = Math.round((end.getTime() - start.getTime()) / 60000)
            if (!Number.isFinite(minutes) || minutes < 15) return false
            this.recordForm.quantity = Math.round((minutes / 60) * 100) / 100
            return true
        },
        buildHourlyOverlapStyles(records) {
            const palette = [
                { color: '#0f8a70', border: '#086b56' },
                { color: '#c35a16', border: '#99430d' },
                { color: '#2f65b9', border: '#214b8f' },
                { color: '#b53f75', border: '#8d2e59' },
                { color: '#6650b5', border: '#4d3a91' },
                { color: '#b63b3b', border: '#8f2c2c' },
                { color: '#147d92', border: '#0d6071' },
                { color: '#8a5a35', border: '#684326' },
            ]
            const rows = (records || []).filter(record => record.unit === 'horas' && record.starts_at && record.ends_at).map(record => {
                const startDate = String(record.starts_on || '').slice(0, 10)
                const endDate = String(record.ends_on || record.starts_on || '').slice(0, 10)
                return {
                    id: record.id,
                    start: new Date(`${startDate}T${this.timeForInput(record.starts_at)}:00`).getTime(),
                    end: new Date(`${endDate}T${this.timeForInput(record.ends_at)}:00`).getTime(),
                }
            }).filter(row => Number.isFinite(row.start) && Number.isFinite(row.end) && row.end > row.start).sort((left, right) => left.start - right.start || left.end - right.end)
            const active = []
            const styles = {}

            rows.forEach(row => {
                for (let index = active.length - 1; index >= 0; index -= 1) {
                    if (active[index].end <= row.start) active.splice(index, 1)
                }
                const usedColors = new Set(active.map(item => item.colorIndex))
                let colorIndex = 0
                while (usedColors.has(colorIndex)) colorIndex += 1
                const overlapCount = rows.filter(other => other.id !== row.id && other.start < row.end && other.end > row.start).length
                styles[row.id] = { ...palette[colorIndex % palette.length], colorIndex, overlapCount }
                active.push({ end: row.end, colorIndex })
            })

            return styles
        },
        initials(name) {
            return String(name || 'SN').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase()
        },
        addDays(value, days) {
            if (!value) return value
            const [year, month, day] = String(value).slice(0, 10).split('-').map(Number)
            const date = new Date(year, month - 1, day)
            date.setDate(date.getDate() + days)
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
        },
        absenceColor(type) {
            return {
                licencia_medica: '#7c4fc9',
                dia_administrativo: '#5965cf',
                dia_compensatorio: '#1687b8',
                permiso_autorizado: '#0f9f7a',
                ausencia_injustificada: '#dc3154',
                otro: '#718096',
            }[type] || '#718096'
        },
        openCalendarRecord(record) { this.selectedCalendarRecord = record },
        editCalendarRecord() {
            const record = this.selectedCalendarRecord
            this.selectedCalendarRecord = null
            this.openRecord(record)
        },
        alertError(error) { Swal.fire({ icon: 'error', title: 'No fue posible completar la acción', text: error.response?.data?.message || 'Revisa los datos e inténtalo nuevamente.' }) },
    },
}
</script>

<template>
    <Layout>
        <div class="container-fluid py-3 operational-workspace absence-workspace">
        <OperationalWorkspaceHeader
            title="Ausencias y saldos"
            subtitle="Permisos, licencias, ausencias externas y saldos anuales consolidados en un único libro operativo."
            icon="bx bx-calendar-check"
        >
            <template #actions>
                <button v-if="capabilities.export" class="btn btn-outline-light" @click="exportCsv"><i class="bx bx-download me-1"></i>Exportar CSV</button>
                <button v-if="capabilities.manage" class="btn btn-light" @click="openRecord()"><i class="bx bx-plus me-1"></i>Registrar ausencia</button>
            </template>
        </OperationalWorkspaceHeader>

        <div class="row g-3 mb-4">
            <div v-for="metric in absenceMetrics" :key="metric.label" class="col-6 col-lg-4 col-xl">
                <div class="op-metric-card" :class="`op-tone-${metric.tone}`">
                    <div class="op-metric-top">
                        <span class="op-metric-label">{{ metric.label }}</span>
                        <span class="op-metric-icon"><i :class="metric.icon"></i></span>
                    </div>
                    <div class="op-metric-value">{{ metric.value }}</div>
                    <div class="op-metric-note">{{ metric.note }}</div>
                </div>
            </div>
        </div>

        <div class="card op-surface">
            <div class="card-header bg-white border-0 p-3 pb-0">
                <ul class="nav op-section-tabs">
                    <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'records' }" @click="activeTab = 'records'"><i class="bx bx-list-ul"></i>Ausencias</button></li>
                    <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'calendar' }" @click="activeTab = 'calendar'"><i class="bx bx-calendar-event"></i>Calendario consolidado</button></li>
                    <li class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'balances' }" @click="activeTab = 'balances'"><i class="bx bx-wallet"></i>Saldos administrativos</button></li>
                    <li v-if="capabilities.import" class="nav-item"><button class="nav-link" :class="{ active: activeTab === 'import' }" @click="activeTab = 'import'"><i class="bx bx-upload"></i>Importación histórica</button></li>
                </ul>
            </div>
            <div class="card-body">
                <div v-if="activeTab === 'records'">
                    <div class="op-filter-panel"><div class="row g-2 align-items-end">
                        <div class="col-md-2"><label class="op-filter-label">Año</label><input v-model.number="filters.year" type="number" class="form-control" min="2020" max="2100" @change="load()"></div>
                        <div class="col-md-3"><label class="op-filter-label">Funcionario/a</label><input v-model="filters.search" class="form-control" placeholder="Nombre o RUT" @keyup.enter="load()"></div>
                        <div class="col-md-2"><label class="op-filter-label">Tipo</label><select v-model="filters.type" class="form-select" @change="load()"><option value="">Todos los tipos</option><option v-for="item in catalogs.types" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
                        <div class="col-md-2"><label class="op-filter-label">Estado</label><select v-model="filters.status" class="form-select" @change="load()"><option value="">Todos los estados</option><option v-for="item in catalogs.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" @click="load()"><i class="bx bx-filter-alt me-1"></i>Aplicar</button></div>
                    </div>
                    </div>
                    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                    <div v-else class="table-responsive">
                        <table class="table align-middle table-hover op-data-table">
                            <thead><tr><th>Funcionario/a</th><th>Tipo</th><th>Período</th><th>Cantidad</th><th>Estado</th><th>Origen</th><th class="text-end">Acciones</th></tr></thead>
                            <tbody>
                                <tr v-for="record in records.data" :key="record.id">
                                    <td><div class="op-person"><span class="op-person-avatar">{{ initials(record.staff?.full_name) }}</span><div><strong>{{ record.staff?.full_name }}</strong><div class="small text-muted">{{ record.staff?.rut }}</div></div></div></td>
                                    <td>{{ typeMap[record.absence_type] || record.absence_type }}</td>
                                    <td class="text-nowrap"><i class="bx bx-calendar text-primary me-1"></i>{{ formatDate(record.starts_on) }}<span v-if="record.ends_on !== record.starts_on"> al {{ formatDate(record.ends_on) }}</span><div v-if="record.unit === 'horas'" class="small text-muted mt-1"><i class="bx bx-time-five me-1"></i>{{ formatTime(record.starts_at) }} a {{ formatTime(record.ends_at) }}</div></td>
                                    <td>{{ formatNumber(record.quantity) }} {{ record.unit }}</td>
                                    <td><span class="badge" :class="labelClass(record.status)">{{ statusMap[record.status] || record.status }}</span></td>
                                    <td><span class="small text-muted">{{ record.source?.replaceAll('_', ' ') }}</span></td>
                                    <td class="text-end"><template v-if="capabilities.manage"><button class="btn btn-sm btn-light me-1" title="Editar" @click="openRecord(record)"><i class="bx bx-edit"></i></button><button class="btn btn-sm btn-light text-danger" title="Anular" @click="removeRecord(record)"><i class="bx bx-trash"></i></button></template></td>
                                </tr>
                                <tr v-if="!records.data?.length"><td colspan="7" class="op-empty-state"><i class="bx bx-calendar-x"></i>No hay registros para los filtros seleccionados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="records.last_page > 1" class="d-flex justify-content-between align-items-center mt-3"><span class="text-muted small">Página {{ records.current_page }} de {{ records.last_page }}</span><div><button class="btn btn-sm btn-outline-secondary me-2" :disabled="records.current_page <= 1" @click="load(records.current_page - 1)">Anterior</button><button class="btn btn-sm btn-outline-secondary" :disabled="records.current_page >= records.last_page" @click="load(records.current_page + 1)">Siguiente</button></div></div>
                </div>

                <div v-else-if="activeTab === 'calendar'">
                    <div class="op-filter-panel"><div class="row g-2 align-items-end">
                        <div class="col-md-4"><label class="op-filter-label">Funcionario/a</label><input v-model="filters.search" class="form-control" placeholder="Nombre o RUT" @keyup.enter="loadCalendar()"></div>
                        <div class="col-md-3"><label class="op-filter-label">Tipo</label><select v-model="filters.type" class="form-select"><option value="">Todos los tipos</option><option v-for="item in catalogs.types" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
                        <div class="col-md-3"><label class="op-filter-label">Estado</label><select v-model="filters.status" class="form-select"><option value="">Todos los estados</option><option v-for="item in catalogs.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" @click="loadCalendar()"><i class="bx bx-filter-alt me-1"></i>Aplicar</button></div>
                    </div></div>
                    <OperationalCalendar
                        title="Calendario consolidado de ausencias"
                        subtitle="Incluye permisos de plataforma, licencias y registros externos que se superponen con el período visible."
                        :events="absenceCalendarEvents"
                        :loading="calendarLoading"
                        :show-all-day-events="true"
                        :avoid-timed-overlap="true"
                        @range-change="loadCalendar"
                        @event-click="openCalendarRecord"
                    >
                        <template #legend><div class="absence-calendar-legend"><span><i class="absence-dot dot-medical"></i>Licencia</span><span><i class="absence-dot dot-admin"></i>Administrativo</span><span><i class="absence-dot dot-comp"></i>Compensatorio</span><span><i class="absence-dot dot-permission"></i>Permiso</span><span><i class="absence-dot dot-pending"></i>Por regularizar</span><span><i class="absence-dot dot-overlap"></i>Horas coincidentes</span></div></template>
                        <template #actions><button v-if="capabilities.export" type="button" class="btn btn-sm btn-outline-primary op-calendar-pdf" :disabled="calendarLoading || !calendarRecords.length" @click="exportCalendarPdf"><i class="bx bxs-file-pdf me-1"></i>Exportar PDF</button></template>
                    </OperationalCalendar>
                </div>

                <div v-else-if="activeTab === 'balances'">
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-1">Saldos {{ filters.year }}</h5><p class="text-muted mb-0 small">La asignación anual es configurable por funcionario. Los días registrados descuentan automáticamente.</p></div><button v-if="capabilities.manage" class="btn btn-outline-primary" @click="openBalance()">Configurar saldo</button></div>
                    <div class="table-responsive"><table class="table align-middle op-data-table"><thead><tr><th>Funcionario/a</th><th class="text-center">Asignación administrativa</th><th class="text-center">Usado</th><th class="text-center">Disponible</th><th class="text-center">Compensatorio disponible</th><th></th></tr></thead><tbody>
                        <tr v-for="balance in balances" :key="balance.id"><td><div class="op-person"><span class="op-person-avatar">{{ initials(balance.staff?.full_name) }}</span><div><strong>{{ balance.staff?.full_name }}</strong><div class="small text-muted">{{ balance.staff?.rut }}</div></div></div></td><td class="text-center">{{ formatNumber(Number(balance.administrative_entitlement) + Number(balance.administrative_adjustment)) }}</td><td class="text-center">{{ formatNumber(balance.administrative_used) }}</td><td class="text-center"><span class="fw-semibold" :class="balance.administrative_available < 0 ? 'text-danger' : 'text-success'">{{ formatNumber(balance.administrative_available) }}</span></td><td class="text-center">{{ formatNumber(balance.compensatory_available) }}</td><td class="text-end"><button v-if="capabilities.manage" class="btn btn-sm btn-light" @click="openBalance(balance)"><i class="bx bx-edit"></i></button></td></tr>
                        <tr v-if="!balances.length"><td colspan="6" class="op-empty-state"><i class="bx bx-wallet"></i>Aún no hay saldos configurados para este año.</td></tr>
                    </tbody></table></div>
                </div>

                <div v-else class="row justify-content-center"><div class="col-xl-9"><div class="import-dropzone"><span class="import-dropzone-icon"><i class="bx bx-spreadsheet"></i></span><h5>Precargar planilla de licencias y días administrativos</h5><p class="text-muted">La vista previa concilia por RUT y nombre, normaliza los medios días y evita duplicados al volver a importar el mismo registro.</p><input type="file" accept=".xlsx" class="form-control mb-3" @change="chooseImport"><button class="btn btn-primary" :disabled="!importFile || importing" @click="previewImport"><span v-if="importing" class="spinner-border spinner-border-sm me-2"></span>Generar vista previa</button></div>
                    <div v-if="importPreview" class="mt-4"><div class="alert alert-info"><strong>{{ importPreview.importable_rows }} de {{ importPreview.total_rows }}</strong> registros listos. Licencias: {{ importPreview.summary.medical_leaves }} · Días administrativos: {{ formatNumber(importPreview.summary.administrative_days) }} · Sin coincidencia: {{ importPreview.unmatched_rows }}</div><div class="table-responsive preview-table"><table class="table table-sm op-data-table"><thead><tr><th>Hoja/fila</th><th>Nombre original</th><th>Funcionario conciliado</th><th>Fecha</th><th>Resultado</th></tr></thead><tbody><tr v-for="row in importPreview.rows" :key="`${row.sheet}-${row.row}-${row.starts_on}`"><td>{{ row.sheet }} · {{ row.row }}</td><td>{{ row.source_name }}</td><td>{{ row.staff_name || 'Sin coincidencia' }}<div class="small text-muted">{{ row.match_method }} · {{ row.match_confidence }}%</div></td><td>{{ formatDate(row.starts_on) }}</td><td><span class="badge" :class="row.can_import ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">{{ row.can_import ? 'Importable' : 'Revisar' }}</span></td></tr></tbody></table></div><button class="btn btn-success mt-3" :disabled="importing || !importPreview.importable_rows" @click="commitImport">Confirmar precarga</button></div>
                </div>
                </div>
            </div>
        </div>
        </div>

        <Teleport to="body"><div v-if="selectedCalendarRecord" class="hr-modal-backdrop" @mousedown.self="selectedCalendarRecord = null"><div class="hr-modal hr-modal-sm card shadow-lg"><div class="card-header op-modal-header d-flex justify-content-between align-items-center"><div><small class="text-white-50">Detalle consolidado</small><h5 class="mb-0">{{ selectedCalendarRecord.staff?.full_name }}</h5></div><button class="btn-close" @click="selectedCalendarRecord = null"></button></div><div class="card-body"><div class="calendar-detail-grid">
            <div><span>Tipo de ausencia</span><strong>{{ typeMap[selectedCalendarRecord.absence_type] || selectedCalendarRecord.absence_type }}</strong></div>
            <div><span>Estado</span><strong><span class="badge" :class="labelClass(selectedCalendarRecord.status)">{{ statusMap[selectedCalendarRecord.status] || selectedCalendarRecord.status }}</span></strong></div>
            <div><span>Período</span><strong>{{ formatDate(selectedCalendarRecord.starts_on) }}<template v-if="selectedCalendarRecord.ends_on !== selectedCalendarRecord.starts_on"> al {{ formatDate(selectedCalendarRecord.ends_on) }}</template></strong></div>
            <div><span>Cantidad</span><strong>{{ formatNumber(selectedCalendarRecord.quantity) }} {{ selectedCalendarRecord.unit }}</strong></div>
            <div v-if="selectedCalendarRecord.unit === 'horas'"><span>Horario</span><strong>{{ formatTime(selectedCalendarRecord.starts_at) }} a {{ formatTime(selectedCalendarRecord.ends_at) }}</strong></div>
            <div><span>Origen</span><strong class="text-capitalize">{{ selectedCalendarRecord.source?.replaceAll('_', ' ') || 'No informado' }}</strong></div>
            <div><span>RUT</span><strong>{{ selectedCalendarRecord.staff?.rut || '—' }}</strong></div>
            <div v-if="selectedCalendarRecord.notes" class="calendar-detail-wide"><span>Observaciones</span><p>{{ selectedCalendarRecord.notes }}</p></div>
        </div></div><div class="card-footer bg-white d-flex justify-content-end gap-2"><button class="btn btn-light" @click="selectedCalendarRecord = null">Cerrar</button><button v-if="capabilities.manage" class="btn btn-primary" @click="editCalendarRecord"><i class="bx bx-edit me-1"></i>Editar registro</button></div></div></div></Teleport>

        <Teleport to="body"><div v-if="showRecordModal" class="hr-modal-backdrop" @mousedown.self="showRecordModal = false"><div class="hr-modal hr-modal-form card shadow-lg"><div class="card-header op-modal-header d-flex justify-content-between align-items-center"><h5 class="mb-0">{{ editingRecord ? 'Editar ausencia' : 'Registrar ausencia externa' }}</h5><button class="btn-close" @click="showRecordModal = false"></button></div><form @submit.prevent="saveRecord"><div class="card-body modal-scroll"><div class="row g-3">
            <div class="col-12"><label class="form-label">Funcionario/a</label><Multiselect v-model="recordForm.staff_id" class="absence-staff-select" :class="{ 'absence-staff-select-error': errors.staff_id }" :options="staffOptions" :searchable="true" :can-clear="false" placeholder="Buscar por nombre o RUT" no-options-text="No hay funcionarios disponibles" no-results-text="No encontramos coincidencias"/><div class="form-text">Escribe parte del nombre o RUT para encontrar a la persona.</div></div>
            <div class="col-md-6"><label class="form-label">Tipo</label><select v-model="recordForm.absence_type" class="form-select" required><option v-for="item in catalogs.types" :key="item.value" :value="item.value">{{ item.label }}</option></select></div><div class="col-md-6"><label class="form-label">Estado</label><select v-model="recordForm.status" class="form-select" required><option v-for="item in catalogs.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></div>
            <div class="col-md-6"><label class="form-label">Inicio</label><input v-model="recordForm.starts_on" type="date" class="form-control" required @change="handleStartDateChange"><div class="form-text">Formato: dd-mm-aaaa</div></div><div class="col-md-6"><label class="form-label">Término</label><input v-model="recordForm.ends_on" type="date" class="form-control" :min="recordForm.starts_on" required @change="recalculateHourQuantity"><div class="form-text">Formato: dd-mm-aaaa</div></div>
            <div class="col-md-4"><label class="form-label">Unidad del permiso</label><select v-model="recordForm.unit" class="form-select" @change="changeRecordUnit"><option value="dias">Días</option><option value="horas">Horas</option></select></div><div class="col-md-4"><label class="form-label">{{ recordForm.unit === 'horas' ? 'Duración calculada' : 'Cantidad' }}</label><input v-model.number="recordForm.quantity" type="number" step="0.25" min="0.25" class="form-control" :readonly="recordForm.unit === 'horas'" required><div v-if="recordForm.unit === 'horas'" class="form-text">Se calcula desde el horario.</div></div><div class="col-md-4"><label class="form-label">Tipo de reposo/jornada</label><input v-model="recordForm.rest_type" class="form-control" placeholder="Total, parcial, medio día"></div>
            <template v-if="recordForm.unit === 'horas'"><div class="col-md-6"><label class="form-label">Hora de inicio</label><div class="input-group"><span class="input-group-text"><i class="bx bx-time-five"></i></span><input v-model="recordForm.starts_at" type="time" step="900" class="form-control" required @change="recalculateHourQuantity"></div></div><div class="col-md-6"><label class="form-label">Hora de término</label><div class="input-group"><span class="input-group-text"><i class="bx bx-time-five"></i></span><input v-model="recordForm.ends_at" type="time" step="900" class="form-control" required @change="recalculateHourQuantity"></div></div><div class="col-12"><div class="hourly-calendar-note"><i class="bx bx-calendar-event"></i><div><strong>Se mostrará por horario en el calendario</strong><span>{{ recordForm.starts_at || '—' }} a {{ recordForm.ends_at || '—' }} · {{ formatNumber(recordForm.quantity) }} horas</span></div></div></div></template>
            <div class="col-md-6 form-check ms-2"><input id="attendance" v-model="recordForm.affects_attendance" class="form-check-input" type="checkbox"><label for="attendance" class="form-check-label">Afecta asistencia</label></div><div class="col-md-5 form-check"><input id="payroll" v-model="recordForm.affects_payroll" class="form-check-input" type="checkbox"><label for="payroll" class="form-check-label">Informar a remuneraciones</label></div>
            <div class="col-12"><label class="form-label">Observaciones</label><textarea v-model="recordForm.notes" rows="3" class="form-control"></textarea></div><div v-if="Object.keys(errors).length" class="col-12"><div class="alert alert-danger mb-0"><div v-for="(messages, field) in errors" :key="field">{{ messages[0] }}</div></div></div>
        </div></div><div class="card-footer bg-white text-end"><button type="button" class="btn btn-light me-2" @click="showRecordModal = false">Cancelar</button><button class="btn btn-primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>Guardar</button></div></form></div></div></Teleport>

        <Teleport to="body"><div v-if="showBalanceModal" class="hr-modal-backdrop" @mousedown.self="showBalanceModal = false"><div class="hr-modal hr-modal-form hr-modal-sm card shadow-lg"><div class="card-header op-modal-header d-flex justify-content-between"><h5 class="mb-0">Configurar saldo anual</h5><button class="btn-close" @click="showBalanceModal = false"></button></div><form @submit.prevent="saveBalance"><div class="card-body modal-scroll"><div class="row g-3"><div class="col-12"><label class="form-label">Funcionario/a</label><select v-model="balanceForm.staff_id" class="form-select" :disabled="!!balanceForm.id" required><option value="" disabled>Seleccionar</option><option v-for="person in staff" :key="person.id" :value="person.id">{{ person.full_name }} · {{ person.rut }}</option></select></div><div class="col-12"><label class="form-label">Año</label><input v-model.number="balanceForm.year" type="number" min="2020" max="2100" class="form-control" required></div><div class="col-6"><label class="form-label">Asignación administrativa</label><input v-model.number="balanceForm.administrative_entitlement" type="number" step="0.5" min="0" class="form-control" required></div><div class="col-6"><label class="form-label">Ajuste administrativo</label><input v-model.number="balanceForm.administrative_adjustment" type="number" step="0.5" class="form-control"></div><div class="col-6"><label class="form-label">Asignación compensatoria</label><input v-model.number="balanceForm.compensatory_entitlement" type="number" step="0.5" min="0" class="form-control" required></div><div class="col-6"><label class="form-label">Ajuste compensatorio</label><input v-model.number="balanceForm.compensatory_adjustment" type="number" step="0.5" class="form-control"></div><div class="col-12"><label class="form-label">Nota del ajuste</label><textarea v-model="balanceForm.notes" rows="2" class="form-control"></textarea></div></div></div><div class="card-footer bg-white text-end"><button type="button" class="btn btn-light me-2" @click="showBalanceModal = false">Cancelar</button><button class="btn btn-primary" :disabled="saving">Guardar saldo</button></div></form></div></div></Teleport>
    </Layout>
</template>

<style scoped>
.preview-table { max-height: 420px; }
.absence-calendar-legend { display: flex; flex-wrap: wrap; gap: .55rem; color: #66758a; font-size: .67rem; }
.absence-calendar-legend span { display: inline-flex; align-items: center; gap: .28rem; }
.absence-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; }
.dot-medical { background: #7c4fc9; }
.dot-admin { background: #5965cf; }
.dot-comp { background: #1687b8; }
.dot-permission { background: #0f9f7a; }
.dot-pending { background: #dc3154; }
.dot-overlap { background: linear-gradient(135deg, #0f8a70 0 50%, #c35a16 50%); }
.op-calendar-pdf { border-radius: 999px; font-size: .7rem; font-weight: 700; white-space: nowrap; }
.calendar-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; }
.calendar-detail-grid > div { padding: .8rem; border: 1px solid #e7ebf2; border-radius: .75rem; background: #fafbfe; }
.calendar-detail-grid span, .calendar-detail-grid strong { display: block; }
.calendar-detail-grid > div > span { margin-bottom: .3rem; color: #7a8799; font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.calendar-detail-grid strong { color: #27344e; }
.calendar-detail-grid .calendar-detail-wide { grid-column: 1 / -1; }
.calendar-detail-grid p { margin: 0; color: #4b586d; white-space: pre-wrap; }
.import-dropzone { padding: 2rem; border: 1px dashed #bfc8e8; border-radius: 1rem; background: linear-gradient(145deg, #f8faff, #f3f5ff); text-align: center; }
.import-dropzone-icon { display: grid; place-items: center; width: 3.25rem; height: 3.25rem; margin: 0 auto .8rem; border-radius: 1rem; color: #4f46b8; background: #e8ebff; font-size: 1.6rem; }
.hr-modal-backdrop { position: fixed; inset: 0; z-index: 1060; display: flex; align-items: center; justify-content: center; padding: 1rem; overflow: hidden; background: rgba(15, 23, 42, .6); backdrop-filter: blur(4px); }
.hr-modal { display: flex; flex-direction: column; overflow: hidden; width: min(760px, 100%); max-height: calc(100dvh - 2rem); border: 0; border-radius: 1.1rem; }
.hr-modal-form { height: min(760px, calc(100dvh - 2rem)); }
.hr-modal-sm { width: min(620px, 100%); }
.hr-modal > .card-header, .hr-modal > .card-footer, .hr-modal > form > .card-footer { flex: 0 0 auto; }
.hr-modal > form { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; overflow: hidden; }
.hr-modal > .card-body, .modal-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
.form-label { color: #475569; font-size: .78rem; font-weight: 650; }
.card-footer { border-color: #edf0f5; }
.absence-staff-select { --ms-radius: .5rem; --ms-border-color: #ced4da; --ms-ring-color: rgba(85, 110, 230, .18); --ms-option-bg-selected: #5965cf; --ms-option-bg-selected-pointed: #4d58c4; }
.absence-staff-select-error { --ms-border-color: #dc3545; }
.hourly-calendar-note { display: flex; align-items: center; gap: .75rem; padding: .8rem .9rem; border: 1px solid #dce2ff; border-radius: .75rem; color: #4652be; background: #f3f5ff; }
.hourly-calendar-note > i { font-size: 1.35rem; }
.hourly-calendar-note strong, .hourly-calendar-note span { display: block; }
.hourly-calendar-note strong { font-size: .78rem; }
.hourly-calendar-note span { margin-top: .15rem; color: #67728a; font-size: .72rem; }
@media (max-width: 575.98px) { .calendar-detail-grid { grid-template-columns: 1fr; } .calendar-detail-grid .calendar-detail-wide { grid-column: auto; } }
</style>
