<script>
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'
import esLocale from '@fullcalendar/core/locales/es'

export default {
    components: { FullCalendar },
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
        events: { type: Array, default: () => [] },
        loading: { type: Boolean, default: false },
        initialDate: { type: String, default: '' },
        showAllDayEvents: { type: Boolean, default: false },
        avoidTimedOverlap: { type: Boolean, default: false },
    },
    emits: ['range-change', 'event-click'],
    data() {
        return {
            calendarOptions: {
                plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
                locales: [esLocale],
                locale: 'es',
                timeZone: 'local',
                initialView: 'dayGridMonth',
                initialDate: this.initialDate || undefined,
                firstDay: 1,
                fixedWeekCount: false,
                showNonCurrentDates: true,
                editable: false,
                selectable: false,
                navLinks: true,
                dayMaxEvents: 3,
                views: {
                    dayGridMonth: {
                        dayMaxEvents: this.showAllDayEvents ? false : 3,
                        dayMaxEventRows: this.showAllDayEvents ? false : 3,
                    },
                    timeGridWeek: {
                        dayMaxEvents: this.showAllDayEvents ? false : 3,
                        dayMaxEventRows: this.showAllDayEvents ? false : 3,
                    },
                    listMonth: { dayMaxEvents: false, dayMaxEventRows: false },
                },
                slotEventOverlap: !this.avoidTimedOverlap,
                nowIndicator: true,
                slotMinTime: '07:00:00',
                slotMaxTime: '21:00:00',
                height: 'auto',
                events: this.events,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listMonth',
                },
                buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Agenda' },
                noEventsText: 'No hay registros en este período',
                moreLinkText: count => `+${count} más`,
                datesSet: this.handleDatesSet,
                eventClick: this.handleEventClick,
                eventDidMount: this.decorateEvent,
            },
        }
    },
    watch: {
        events(value) { this.calendarOptions.events = value },
    },
    methods: {
        handleDatesSet(info) {
            const inclusiveEnd = new Date(info.end)
            inclusiveEnd.setDate(inclusiveEnd.getDate() - 1)
            this.$emit('range-change', {
                from: this.localDate(info.start),
                to: this.localDate(inclusiveEnd),
                title: info.view.title,
            })
        },
        handleEventClick(info) {
            info.jsEvent?.preventDefault()
            this.$emit('event-click', info.event.extendedProps.record || info.event.extendedProps)
        },
        decorateEvent(info) {
            const tooltip = info.event.extendedProps.tooltip
            if (tooltip) info.el.setAttribute('title', tooltip)
        },
        localDate(value) {
            const date = new Date(value)
            const year = date.getFullYear()
            const month = String(date.getMonth() + 1).padStart(2, '0')
            const day = String(date.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
    },
}
</script>

<template>
    <section class="op-calendar-card">
        <header class="op-calendar-intro">
            <div class="op-calendar-title">
                <span><i class="bx bx-calendar-event"></i></span>
                <div>
                    <h5>{{ title }}</h5>
                    <p v-if="subtitle">{{ subtitle }}</p>
                </div>
            </div>
            <div class="op-calendar-summary">
                <slot name="legend"></slot>
                <slot name="actions"></slot>
                <span class="op-calendar-count"><strong>{{ events.length }}</strong> registros visibles</span>
            </div>
        </header>

        <div class="op-calendar-body">
            <div v-if="loading" class="op-calendar-loading">
                <span class="spinner-border spinner-border-sm"></span>
                Actualizando calendario…
            </div>
            <FullCalendar ref="calendar" :options="calendarOptions" />
        </div>
    </section>
</template>

<style scoped>
.op-calendar-card { overflow: hidden; border: 1px solid #e3e8f1; border-radius: 1.1rem; background: #fff; box-shadow: 0 10px 30px rgba(22,34,68,.055); }
.op-calendar-intro { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.05rem 1.2rem; border-bottom: 1px solid #e9edf4; background: linear-gradient(135deg, #fbfcff, #f6f8ff); }
.op-calendar-title { display: flex; align-items: center; gap: .75rem; min-width: 0; }
.op-calendar-title > span { display: grid; flex: 0 0 auto; place-items: center; width: 2.65rem; height: 2.65rem; border-radius: .8rem; color: #4d58c4; background: #e9ecff; font-size: 1.25rem; }
.op-calendar-title h5 { margin: 0; color: #1e293b; font-weight: 750; }
.op-calendar-title p { margin: .18rem 0 0; color: #718096; font-size: .78rem; }
.op-calendar-summary { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .7rem; }
.op-calendar-count { padding: .45rem .65rem; border: 1px solid #e0e5ee; border-radius: 999px; color: #64748b; background: #fff; font-size: .72rem; }
.op-calendar-count strong { color: #3f4ab1; }
.op-calendar-body { position: relative; padding: 1rem 1.15rem 1.2rem; }
.op-calendar-loading { position: absolute; z-index: 5; top: .75rem; left: 50%; display: flex; align-items: center; gap: .45rem; padding: .5rem .75rem; border: 1px solid #dfe4ee; border-radius: 999px; color: #4f5e74; background: rgba(255,255,255,.94); box-shadow: 0 5px 16px rgba(15,23,42,.12); font-size: .75rem; transform: translateX(-50%); }

:deep(.fc) { --fc-border-color: #e6eaf1; --fc-page-bg-color: #fff; --fc-neutral-bg-color: #f8faff; --fc-today-bg-color: #f1f4ff; color: #334155; font-size: .82rem; }
:deep(.fc .fc-toolbar) { gap: .75rem; margin-bottom: 1rem; }
:deep(.fc .fc-toolbar-title) { color: #1e293b; font-size: 1.2rem; font-weight: 750; text-transform: lowercase; }
:deep(.fc .fc-toolbar-title::first-letter) { text-transform: uppercase; }
:deep(.fc .fc-button) { padding: .42rem .65rem; border-color: #d8deea; border-radius: .58rem; color: #526074; background: #fff; font-size: .75rem; font-weight: 650; box-shadow: none; text-transform: capitalize; }
:deep(.fc .fc-button:hover), :deep(.fc .fc-button:focus) { border-color: #6975d8; color: #4652be; background: #f2f4ff; box-shadow: none; }
:deep(.fc .fc-button-primary:not(:disabled).fc-button-active) { border-color: #5965cf; color: #fff; background: #5965cf; }
:deep(.fc .fc-button-group) { gap: .2rem; }
:deep(.fc .fc-button-group > .fc-button) { border-radius: .58rem !important; }
:deep(.fc .fc-col-header-cell-cushion) { padding: .7rem .35rem; color: #64748b; font-size: .68rem; font-weight: 750; letter-spacing: .05em; text-transform: uppercase; }
:deep(.fc .fc-daygrid-day-frame) { min-height: 108px; padding: .16rem; }
:deep(.fc .fc-daygrid-day-number) { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; margin: .2rem; border-radius: 50%; color: #536176; font-weight: 700; }
:deep(.fc .fc-day-today .fc-daygrid-day-number) { color: #fff; background: #5965cf; }
:deep(.fc .fc-day-other .fc-daygrid-day-number) { color: #b0b8c5; }
:deep(.fc .fc-event) { margin: 1px 2px; padding: .18rem .3rem; border: 0; border-radius: .42rem; cursor: pointer; font-size: .69rem; font-weight: 650; box-shadow: 0 2px 6px rgba(15,23,42,.08); }
:deep(.fc .fc-event:hover) { filter: brightness(.97); transform: translateY(-1px); }
:deep(.fc .fc-timegrid-event .fc-event-main) { overflow: hidden; }
:deep(.fc .fc-timegrid-event .fc-event-title) { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; hyphens: none; overflow-wrap: normal; white-space: normal; }
:deep(.fc .fc-daygrid-more-link) { margin: .15rem .3rem; color: #4f5bc4; font-size: .68rem; font-weight: 700; }
:deep(.fc .fc-list-event:hover td) { background: #f8faff; }
:deep(.fc .fc-list-day-cushion) { color: #3d487a; background: #f0f2ff; }

@media (max-width: 767.98px) {
    .op-calendar-intro { align-items: flex-start; flex-direction: column; }
    .op-calendar-summary { justify-content: flex-start; }
    .op-calendar-body { padding: .8rem; overflow-x: auto; }
    :deep(.fc) { min-width: 680px; }
    :deep(.fc .fc-toolbar) { align-items: flex-start; flex-wrap: wrap; }
    :deep(.fc .fc-toolbar-chunk:nth-child(2)) { order: -1; width: 100%; }
    :deep(.fc .fc-toolbar-title) { font-size: 1.05rem; }
}
</style>
