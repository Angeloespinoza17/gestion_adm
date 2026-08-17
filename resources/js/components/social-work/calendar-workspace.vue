<script setup>
import { computed, ref } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import timeGridPlugin from '@fullcalendar/timegrid'
import esLocale from '@fullcalendar/core/locales/es'

const props = defineProps({
  events: { type: Array, default: () => [] },
})
const emit = defineEmits(['open'])

const viewMode = ref('calendar')
const search = ref('')
const period = ref('all')

const normalizeDate = value => {
  if (!value) return null
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value
  const parsed = new Date(normalized)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}
const dayStart = value => {
  const parsed = normalizeDate(value)
  if (!parsed) return null
  return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate())
}
const today = () => {
  const current = new Date()
  return new Date(current.getFullYear(), current.getMonth(), current.getDate())
}
const isOverdue = event => {
  const eventDate = dayStart(event.start)
  return eventDate ? eventDate < today() : false
}
const isNextSevenDays = event => {
  const eventDate = dayStart(event.start)
  if (!eventDate) return false
  const limit = new Date(today())
  limit.setDate(limit.getDate() + 7)
  return eventDate >= today() && eventDate <= limit
}
const formatDate = value => {
  const parsed = normalizeDate(value)
  return parsed ? new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium' }).format(parsed) : '—'
}
const formatTime = value => {
  if (!value || /^\d{4}-\d{2}-\d{2}$/.test(value)) return 'Todo el día'
  const parsed = normalizeDate(value)
  return parsed ? new Intl.DateTimeFormat('es-CL', { hour: '2-digit', minute: '2-digit' }).format(parsed) : '—'
}
const typeLabel = value => ({ case: 'Seguimiento de caso', intervention: 'Atención', alert: 'Alerta' }[value] || 'Actividad social')

const filteredEvents = computed(() => {
  const term = search.value.trim().toLocaleLowerCase('es')
  return props.events
    .filter(event => !term || [event.title, event.type].some(value => String(value || '').toLocaleLowerCase('es').includes(term)))
    .filter(event => period.value === 'all' || (period.value === 'overdue' ? isOverdue(event) : isNextSevenDays(event)))
    .slice()
    .sort((a, b) => (normalizeDate(a.start)?.getTime() || 0) - (normalizeDate(b.start)?.getTime() || 0))
})

const summary = computed(() => ({
  total: props.events.length,
  overdue: props.events.filter(isOverdue).length,
  next: props.events.filter(isNextSevenDays).length,
  confidential: props.events.filter(event => event.confidential).length,
}))

const calendarEvents = computed(() => filteredEvents.value.map(event => {
  const overdue = isOverdue(event)
  return {
    id: event.id,
    title: event.title,
    start: event.start,
    url: event.url,
    backgroundColor: overdue ? '#dc3545' : event.confidential ? '#69549a' : '#556ee6',
    borderColor: overdue ? '#dc3545' : event.confidential ? '#69549a' : '#556ee6',
    classNames: [overdue ? 'sw-event-overdue' : ''],
    extendedProps: { ...event, overdue },
  }
}))

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locale: esLocale,
  initialView: 'dayGridMonth',
  firstDay: 1,
  height: 'auto',
  fixedWeekCount: false,
  nowIndicator: true,
  navLinks: true,
  dayMaxEvents: 3,
  eventDisplay: 'block',
  events: calendarEvents.value,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek',
  },
  buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana' },
  allDayText: 'Todo el día',
  noEventsText: 'No hay seguimientos para este período',
  eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
  eventClick(info) {
    info.jsEvent.preventDefault()
    if (info.event.url) emit('open', info.event.url)
  },
  eventDidMount(info) {
    const visibility = info.event.extendedProps.confidential ? 'Información reservada' : 'Actividad interna'
    info.el.setAttribute('title', `${info.event.title} · ${visibility}`)
  },
}))
</script>

<template>
  <section class="calendar-workspace">
    <header class="calendar-toolbar">
      <div>
        <span class="calendar-eyebrow">Agenda profesional</span>
        <h2>Seguimientos y próximos hitos</h2>
        <p>Las actividades reservadas mantienen su título protegido.</p>
      </div>
      <div class="view-switch" role="group" aria-label="Cambiar vista del calendario">
        <button type="button" :class="{ active: viewMode === 'calendar' }" @click="viewMode = 'calendar'"><i class="bx bx-calendar"></i> Calendario</button>
        <button type="button" :class="{ active: viewMode === 'table' }" @click="viewMode = 'table'"><i class="bx bx-table"></i> Tabla</button>
      </div>
    </header>

    <div class="calendar-summary">
      <article><i class="bx bx-calendar-event"></i><div><strong>{{ summary.total }}</strong><span>Seguimientos</span></div></article>
      <article><i class="bx bx-time-five"></i><div><strong>{{ summary.next }}</strong><span>Próximos 7 días</span></div></article>
      <article :class="{ danger: summary.overdue }"><i class="bx bx-error-circle"></i><div><strong>{{ summary.overdue }}</strong><span>Vencidos</span></div></article>
      <article><i class="bx bx-lock-alt"></i><div><strong>{{ summary.confidential }}</strong><span>Reservados</span></div></article>
    </div>

    <div class="calendar-filters">
      <div class="input-group">
        <span class="input-group-text"><i class="bx bx-search"></i></span>
        <input v-model="search" class="form-control" placeholder="Buscar seguimiento o código">
      </div>
      <select v-model="period" class="form-select" aria-label="Filtrar período">
        <option value="all">Todas las fechas</option>
        <option value="next">Próximos 7 días</option>
        <option value="overdue">Seguimientos vencidos</option>
      </select>
      <div class="calendar-legend"><span><i class="internal"></i> Interno</span><span><i class="reserved"></i> Reservado</span><span><i class="overdue"></i> Vencido</span></div>
    </div>

    <div v-if="viewMode === 'calendar'" class="skote-calendar">
      <FullCalendar :options="calendarOptions" />
    </div>

    <div v-else class="table-responsive calendar-table">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Fecha</th><th>Actividad</th><th>Tipo</th><th>Visibilidad</th><th>Situación</th><th></th></tr></thead>
        <tbody>
          <tr v-for="event in filteredEvents" :key="event.id">
            <td><strong>{{ formatDate(event.start) }}</strong><small>{{ formatTime(event.start) }}</small></td>
            <td><strong>{{ event.title }}</strong><small>{{ event.id }}</small></td>
            <td><span class="type-badge">{{ typeLabel(event.type) }}</span></td>
            <td><span class="visibility-badge" :class="{ reserved: event.confidential }"><i class="bx" :class="event.confidential ? 'bx-lock-alt' : 'bx-building'"></i>{{ event.confidential ? 'Reservada' : 'Interna' }}</span></td>
            <td><span v-if="isOverdue(event)" class="due-badge overdue">Vencido</span><span v-else-if="isNextSevenDays(event)" class="due-badge next">Próximo</span><span v-else class="due-badge">Programado</span></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" @click="emit('open', event.url)">Abrir caso</button></td>
          </tr>
          <tr v-if="!filteredEvents.length"><td colspan="6" class="empty-state"><i class="bx bx-calendar-x"></i><strong>Sin actividades para mostrar</strong><span>Ajusta los filtros o registra una fecha de seguimiento en un caso.</span></td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.calendar-workspace{overflow:hidden;background:#fff;border:1px solid #e1e7ef;border-radius:18px;box-shadow:0 16px 40px rgba(35,45,75,.065)}.calendar-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.35rem;border-bottom:1px solid #e8edf3}.calendar-eyebrow{font-size:.67rem;letter-spacing:.12em;text-transform:uppercase;font-weight:800;color:#75609c}.calendar-toolbar h2{font-size:1.12rem;color:#263449;margin:.15rem 0}.calendar-toolbar p{font-size:.8rem;color:#718096;margin:0}.view-switch{display:inline-flex;padding:.28rem;border:1px solid #dfe5ed;border-radius:12px;background:#f6f8fb}.view-switch button{display:flex;align-items:center;gap:.35rem;border:0;border-radius:9px;background:transparent;color:#627085;padding:.5rem .72rem;font-size:.78rem;font-weight:700}.view-switch button.active{background:#fff;color:#574277;box-shadow:0 4px 14px rgba(55,43,82,.12)}.calendar-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;padding:1rem 1.35rem;background:#f8fafc}.calendar-summary article{display:flex;align-items:center;gap:.65rem;padding:.75rem;background:#fff;border:1px solid #e6ebf2;border-radius:13px}.calendar-summary article>i{display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#eef2ff;color:#63518d;font-size:1.15rem}.calendar-summary article.danger>i{background:#fff0f1;color:#c83242}.calendar-summary strong,.calendar-summary span{display:block}.calendar-summary strong{font-size:1.12rem;color:#2b394e}.calendar-summary span{font-size:.7rem;color:#738096}.calendar-filters{display:flex;align-items:center;gap:.65rem;padding:1rem 1.35rem;border-bottom:1px solid #edf1f5}.calendar-filters .input-group{max-width:390px}.calendar-filters .form-select{max-width:210px}.calendar-legend{display:flex;align-items:center;gap:.8rem;margin-left:auto;color:#718096;font-size:.69rem}.calendar-legend span{display:flex;align-items:center;gap:.3rem}.calendar-legend i{width:9px;height:9px;border-radius:50%;background:#556ee6}.calendar-legend i.reserved{background:#69549a}.calendar-legend i.overdue{background:#dc3545}.skote-calendar{padding:1.25rem}.skote-calendar :deep(.fc){--fc-border-color:#e4e9f0;--fc-page-bg-color:#fff;--fc-neutral-bg-color:#f8fafc;--fc-today-bg-color:#fff8e5;--fc-button-bg-color:#556ee6;--fc-button-border-color:#556ee6;--fc-button-hover-bg-color:#485ec4;--fc-button-hover-border-color:#485ec4;--fc-button-active-bg-color:#3f53ae;--fc-button-active-border-color:#3f53ae;color:#475569}.skote-calendar :deep(.fc .fc-toolbar){gap:.8rem;margin-bottom:1.15rem}.skote-calendar :deep(.fc .fc-toolbar-title){font-size:1.15rem;font-weight:750;color:#263449;text-transform:capitalize}.skote-calendar :deep(.fc .fc-button){border-radius:.32rem;font-size:.82rem;font-weight:600}.skote-calendar :deep(.fc .fc-col-header-cell){background:#f8fafc}.skote-calendar :deep(.fc .fc-col-header-cell-cushion){padding:.65rem .35rem;color:#64748b;font-size:.69rem;text-transform:uppercase;letter-spacing:.05em}.skote-calendar :deep(.fc .fc-daygrid-day-number){padding:.45rem;color:#526174;font-size:.76rem}.skote-calendar :deep(.fc .fc-event){cursor:pointer;border-radius:5px;padding:1px 3px;font-size:.7rem;box-shadow:0 2px 6px rgba(15,23,42,.09)}.calendar-table thead th{padding:.8rem 1rem;background:#f8fafc;color:#718096;font-size:.67rem;letter-spacing:.07em;text-transform:uppercase}.calendar-table tbody td{padding:.85rem 1rem}.calendar-table td strong,.calendar-table td small{display:block}.calendar-table td small{color:#8793a2;margin-top:.13rem}.type-badge,.visibility-badge,.due-badge{display:inline-flex;align-items:center;gap:.28rem;padding:.28rem .5rem;border-radius:999px;background:#eef3f7;color:#536174;font-size:.7rem}.visibility-badge.reserved{background:#f0ecfa;color:#614d8c}.due-badge.next{background:#edf8f3;color:#117552}.due-badge.overdue{background:#fff0f1;color:#b52b3a}.empty-state{text-align:center!important;padding:2.5rem!important;color:#718096}.empty-state i,.empty-state strong,.empty-state span{display:block}.empty-state i{font-size:1.8rem;color:#8d79b2;margin-bottom:.35rem}.empty-state span{font-size:.76rem;margin-top:.2rem}@media(max-width:900px){.calendar-summary{grid-template-columns:1fr 1fr}.calendar-filters{flex-wrap:wrap}.calendar-legend{width:100%;margin-left:0}.skote-calendar :deep(.fc .fc-toolbar){align-items:flex-start;flex-wrap:wrap}.skote-calendar :deep(.fc .fc-toolbar-chunk:nth-child(2)){order:-1;width:100%}}@media(max-width:600px){.calendar-toolbar{align-items:flex-start;flex-direction:column}.view-switch{width:100%}.view-switch button{flex:1;justify-content:center}.calendar-summary{grid-template-columns:1fr}.calendar-filters .input-group,.calendar-filters .form-select{max-width:none;width:100%}.calendar-legend{flex-wrap:wrap}.skote-calendar{padding:.75rem}.skote-calendar :deep(.fc .fc-toolbar-chunk:last-child){width:100%;display:flex}.skote-calendar :deep(.fc .fc-toolbar-chunk:last-child .fc-button){flex:1}}
</style>
