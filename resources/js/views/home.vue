<script>
import axios from "axios";
import Layout from "../layouts/main.vue";
import LoadingState from "../components/ui/loading-state.vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";

const emptyDashboard = () => ({
  generated_at: null,
  user: {},
  capabilities: {},
  metrics: [],
  calendar: { events: [], range: {} },
  agenda: { today: [], upcoming: [] },
  relevant_calendar: { upcoming: [], overdue_count: 0, current_month_count: 0 },
  reservations: { upcoming: [], pending: [], upcoming_count: 0, pending_count: 0 },
  public_events: { upcoming: [], upcoming_count: 0 },
  news: [],
  internal_announcements: { items: [], unread_count: 0, pending_ack_count: 0 },
  quick_links: [],
});

export default {
  components: {
    Layout,
    LoadingState,
    FullCalendar,
  },
  data() {
    return {
      loading: true,
      refreshing: false,
      error: null,
      dashboard: emptyDashboard(),
      calendarOptions: null,
      showEventModal: false,
      selectedEvent: null,
    };
  },
  computed: {
    currentUserName() {
      return this.dashboard.user?.name || this.localUserName || "equipo";
    },
    localUserName() {
      try {
        return JSON.parse(localStorage.getItem("user") || "{}").name;
      } catch (error) {
        return null;
      }
    },
    greeting() {
      const hour = new Date().getHours();

      if (hour < 12) return "Buenos días";
      if (hour < 19) return "Buenas tardes";

      return "Buenas noches";
    },
    agendaToday() {
      return this.dashboard.agenda?.today || [];
    },
    upcomingAgenda() {
      return this.dashboard.agenda?.upcoming || [];
    },
    pendingReservations() {
      return this.dashboard.reservations?.pending || [];
    },
    upcomingReservations() {
      return this.dashboard.reservations?.upcoming || [];
    },
    upcomingPublicEvents() {
      return this.dashboard.public_events?.upcoming || [];
    },
    visibleNews() {
      return this.dashboard.news || [];
    },
    visibleInternalAnnouncements() {
      return this.dashboard.internal_announcements?.items || [];
    },
    unreadInternalAnnouncements() {
      return this.visibleInternalAnnouncements.filter((item) => !item.read_at).length;
    },
    pendingInternalAcknowledgements() {
      return this.visibleInternalAnnouncements.filter((item) => item.requires_ack && !item.acknowledged_at).length;
    },
    currentDateText() {
      const value = new Intl.DateTimeFormat("es-CL", {
        weekday: "long",
        day: "numeric",
        month: "long",
      }).format(new Date());

      return value.charAt(0).toUpperCase() + value.slice(1);
    },
    attentionCount() {
      return this.unreadInternalAnnouncements + this.pendingReservations.length;
    },
    lastUpdatedText() {
      if (!this.dashboard.generated_at) {
        return "Sin actualizar";
      }

      return this.formatDateTime(this.dashboard.generated_at);
    },
  },
  mounted() {
    this.calendarOptions = this.buildCalendarOptions([]);
    this.loadDashboard();
  },
  methods: {
    async loadDashboard() {
      this.error = null;
      this.refreshing = Boolean(this.dashboard.generated_at);
      this.loading = !this.dashboard.generated_at;

      try {
        const response = await axios.get("/api/inicio/overview");
        this.dashboard = {
          ...emptyDashboard(),
          ...response.data,
        };
        this.calendarOptions = this.buildCalendarOptions(this.dashboard.calendar?.events || []);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
        this.refreshing = false;
      }
    },
    buildCalendarOptions(events) {
      return {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        timeZone: "local",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
        firstDay: 1,
        height: "auto",
        contentHeight: 720,
        expandRows: true,
        nowIndicator: true,
        dayMaxEvents: 4,
        eventDisplay: "block",
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth,timeGridWeek,listMonth",
        },
        buttonText: {
          today: "Hoy",
          month: "Mes",
          week: "Semana",
          list: "Lista",
        },
        noEventsText: "No hay eventos para mostrar",
        allDayText: "Todo el día",
        events,
        eventClick: this.handleCalendarClick,
      };
    },
    handleCalendarClick(info) {
      info.jsEvent?.preventDefault();
      this.selectedEvent = {
        id: info.event.id,
        title: info.event.title,
        start: info.event.start?.toISOString(),
        end: info.event.end?.toISOString(),
        allDay: info.event.allDay,
        ...info.event.extendedProps,
      };
      this.showEventModal = true;
    },
    showAgendaItem(item) {
      this.selectedEvent = { ...item };
      this.showEventModal = true;
    },
    goToSelectedEvent() {
      const route = this.selectedEvent?.route;
      this.showEventModal = false;

      if (route) this.openRoute(route);
    },
    openRoute(route) {
      if (!route) return;

      if (/^https?:\/\//.test(route)) {
        window.location.href = route;
        return;
      }

      const resolved = this.$router.resolve(route);

      if (resolved?.matched?.length) {
        this.$router.push(route);
        return;
      }

      window.location.href = route;
    },
    metricToneClass(tone) {
      return `inicio-metric--${tone || "secondary"}`;
    },
    badgeClass(source) {
      return {
        relevant_calendar: "bg-primary-subtle text-primary",
        reservation: "bg-success-subtle text-success",
        public_event: "bg-info-subtle text-info",
      }[source] || "bg-secondary-subtle text-secondary";
    },
    eventIcon(source) {
      return {
        relevant_calendar: "bx-calendar-event",
        reservation: "bx-building-house",
        public_event: "bx-broadcast",
      }[source] || "bx-calendar";
    },
    statusClass(status) {
      return {
        pendiente: "bg-warning-subtle text-warning",
        aprobada: "bg-success-subtle text-success",
        vencido: "bg-danger-subtle text-danger",
        completado: "bg-success-subtle text-success",
        enviado: "bg-info-subtle text-info",
        publicado: "bg-info-subtle text-info",
      }[status] || "bg-secondary-subtle text-secondary";
    },
    announcementPriorityClass(priority) {
      return {
        urgent: "bg-danger-subtle text-danger",
        important: "bg-warning-subtle text-warning",
        normal: "bg-info-subtle text-info",
      }[priority] || "bg-secondary-subtle text-secondary";
    },
    announcementCardClass(announcement) {
      return {
        "inicio-announcement--urgent": announcement.priority === "urgent",
        "inicio-announcement--important": announcement.priority === "important",
        "inicio-announcement--unread": !announcement.read_at,
      };
    },
    async markInternalAnnouncement(announcement, acknowledged = false) {
      try {
        const response = await axios.post(`/api/internal-communications/${announcement.id}/read`, {
          acknowledged,
        });

        announcement.read_at = response.data?.data?.read_at || new Date().toISOString();

        if (acknowledged) {
          announcement.acknowledged_at = response.data?.data?.acknowledged_at || new Date().toISOString();
        }
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    formatError(error) {
      return error?.response?.data?.message || "No se pudo cargar la informacion de inicio.";
    },
    formatDate(value) {
      if (!value) return "-";

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      }).format(new Date(value));
    },
    formatTime(value) {
      if (!value) return "";

      return new Intl.DateTimeFormat("es-CL", {
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value));
    },
    formatDateTime(value) {
      if (!value) return "-";

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value));
    },
    formatRange(item) {
      const start = item.start || item.starts_at;
      const end = item.end || item.ends_at;

      if (!start) return "-";

      if (item.allDay) {
        return this.formatDate(start);
      }

      const startText = `${this.formatDate(start)} ${this.formatTime(start)}`.trim();

      if (!end) {
        return startText;
      }

      const sameDay = new Date(start).toDateString() === new Date(end).toDateString();

      return sameDay
        ? `${startText} - ${this.formatTime(end)}`
        : `${startText} - ${this.formatDateTime(end)}`;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="inicio-dashboard">
      <section class="inicio-hero mb-4">
        <div class="inicio-hero__content">
          <div class="inicio-eyebrow"><i class="bx bx-calendar me-1"></i>{{ currentDateText }}</div>
          <h1 class="inicio-title">{{ greeting }}, {{ currentUserName }}</h1>
          <p class="inicio-hero__subtitle">Revisa tu jornada, comunicaciones y fechas institucionales importantes.</p>
        </div>

        <div class="inicio-hero__summary">
          <div class="inicio-hero__summary-icon"><i class="bx bx-sun"></i></div>
          <div>
            <span class="inicio-hero__summary-label">Resumen de hoy</span>
            <strong>{{ agendaToday.length }} {{ agendaToday.length === 1 ? 'actividad' : 'actividades' }}</strong>
            <span>{{ attentionCount ? `${attentionCount} elementos requieren tu atención` : 'Todo al día por ahora' }}</span>
          </div>
          <button class="inicio-refresh" type="button" :disabled="refreshing" title="Actualizar información" @click="loadDashboard">
            <i class="bx bx-refresh" :class="{ 'bx-spin': refreshing }"></i>
            <span class="visually-hidden">Actualizar</span>
          </button>
          <small>Actualizado {{ lastUpdatedText }}</small>
        </div>
      </section>

      <BAlert v-if="error" show variant="danger" class="mb-4">
        {{ error }}
      </BAlert>

      <LoadingState v-if="loading" message="Cargando inicio..." />

      <template v-else>
        <BRow class="g-3 mb-4">
          <BCol v-for="metric in dashboard.metrics" :key="metric.key" sm="6" xl="3">
            <BCard no-body class="inicio-metric border-0 h-100" :class="metricToneClass(metric.tone)">
              <BCardBody>
                <div class="inicio-metric__top">
                  <div class="inicio-metric__icon">
                    <i :class="['bx', metric.icon]"></i>
                  </div>
                  <div class="inicio-metric__value">{{ metric.value }}</div>
                </div>
                <div class="inicio-metric__label">{{ metric.label }}</div>
                <div class="inicio-metric__detail">{{ metric.detail }}</div>
              </BCardBody>
            </BCard>
          </BCol>
        </BRow>

        <BRow v-if="visibleInternalAnnouncements.length" class="g-4 mb-4">
          <BCol cols="12">
            <BCard no-body class="border-0 shadow-sm">
              <BCardBody>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div>
                    <h5 class="mb-1">Comunicaciones internas</h5>
                    <div class="text-muted small">
                      <span>{{ unreadInternalAnnouncements }} sin leer</span>
                      <span v-if="pendingInternalAcknowledgements"> · {{ pendingInternalAcknowledgements }} por confirmar</span>
                    </div>
                  </div>
                  <BButton
                    v-if="dashboard.capabilities?.can_view_internal_communications"
                    size="sm"
                    variant="outline-primary"
                    @click="openRoute('/comunicaciones')"
                  >
                    <i class="bx bx-message-square-detail me-1"></i>
                    Gestionar
                  </BButton>
                </div>

                <div class="inicio-announcements">
                  <article
                    v-for="announcement in visibleInternalAnnouncements"
                    :key="announcement.id"
                    class="inicio-announcement"
                    :class="announcementCardClass(announcement)"
                  >
                    <div class="inicio-announcement__header">
                      <span class="badge" :class="announcementPriorityClass(announcement.priority)">
                        {{ announcement.priority_label }}
                      </span>
                      <span v-if="announcement.pinned" class="badge bg-primary-subtle text-primary">Fijado</span>
                      <span v-if="!announcement.read_at" class="badge bg-light text-dark">Nuevo</span>
                    </div>
                    <h6 class="inicio-announcement__title">{{ announcement.title }}</h6>
                    <p class="inicio-announcement__body">{{ announcement.body }}</p>
                    <div class="inicio-announcement__meta">
                      <span>{{ announcement.category || "General" }}</span>
                      <span>{{ formatDateTime(announcement.published_at) }}</span>
                      <span v-if="announcement.created_by">{{ announcement.created_by }}</span>
                    </div>
                    <div class="inicio-announcement__actions">
                      <BButton
                        v-if="!announcement.read_at"
                        size="sm"
                        variant="outline-secondary"
                        @click="markInternalAnnouncement(announcement)"
                      >
                        Marcar leído
                      </BButton>
                      <BButton
                        v-if="announcement.requires_ack && !announcement.acknowledged_at"
                        size="sm"
                        variant="primary"
                        @click="markInternalAnnouncement(announcement, true)"
                      >
                        Confirmar recepción
                      </BButton>
                      <span v-else-if="announcement.requires_ack" class="text-success small fw-semibold">
                        Recepción confirmada
                      </span>
                    </div>
                  </article>
                </div>
              </BCardBody>
            </BCard>
          </BCol>
        </BRow>

        <section class="inicio-calendar-section mb-4">
          <div class="inicio-calendar-heading">
            <div class="inicio-card-heading">
              <span class="inicio-card-heading__icon"><i class="bx bx-calendar"></i></span>
              <div>
                <h4 class="mb-1">Calendario institucional</h4>
                <p class="mb-0">Selecciona un evento para ver todos sus detalles.</p>
              </div>
            </div>
            <div class="inicio-legend">
              <span><i class="inicio-dot inicio-dot--calendar"></i> Fechas relevantes</span>
              <span><i class="inicio-dot inicio-dot--reservation"></i> Reservas</span>
              <span><i class="inicio-dot inicio-dot--public"></i> Eventos públicos</span>
            </div>
          </div>
          <div class="inicio-calendar-wrap">
            <FullCalendar v-if="calendarOptions" :options="calendarOptions" />
          </div>
        </section>

        <BRow class="g-4 mb-4">
          <BCol lg="5">
            <section class="inicio-agenda-card inicio-agenda-card--today h-100">
              <header class="inicio-agenda-header">
                <div>
                  <span class="inicio-section-label">Tu jornada</span>
                  <h4>Hoy</h4>
                </div>
                <span class="inicio-agenda-count">{{ agendaToday.length }}</span>
              </header>
              <div v-if="agendaToday.length" class="inicio-timeline">
                <button
                  v-for="item in agendaToday"
                  :key="`today-${item.source}-${item.id}`"
                  type="button"
                  class="inicio-timeline-item"
                  @click="showAgendaItem(item)"
                >
                  <span class="inicio-timeline-item__icon" :class="`inicio-timeline-item__icon--${item.source}`">
                    <i :class="['bx', eventIcon(item.source)]"></i>
                  </span>
                  <span class="inicio-timeline-item__content">
                    <span class="inicio-timeline-item__top">
                      <span class="badge" :class="badgeClass(item.source)">{{ item.source_label }}</span>
                      <span>{{ item.allDay ? 'Todo el día' : formatTime(item.start || item.starts_at) }}</span>
                    </span>
                    <strong>{{ item.title }}</strong>
                    <small v-if="item.detail">{{ item.detail }}</small>
                  </span>
                  <i class="bx bx-chevron-right inicio-timeline-item__arrow"></i>
                </button>
              </div>
              <div v-else class="inicio-agenda-empty">
                <i class="bx bx-check-circle"></i>
                <strong>Jornada despejada</strong>
                <span>No tienes actividades visibles para hoy.</span>
              </div>
            </section>
          </BCol>

          <BCol lg="7">
            <section class="inicio-agenda-card h-100">
              <header class="inicio-agenda-header">
                <div>
                  <span class="inicio-section-label">Lo que viene</span>
                  <h4>Próximos hitos</h4>
                </div>
                <span class="inicio-agenda-count inicio-agenda-count--muted">{{ upcomingAgenda.length }}</span>
              </header>
              <div v-if="upcomingAgenda.length" class="inicio-milestones">
                <button
                  v-for="item in upcomingAgenda"
                  :key="`upcoming-${item.source}-${item.id}`"
                  type="button"
                  class="inicio-milestone"
                  @click="showAgendaItem(item)"
                >
                  <span class="inicio-milestone__date">
                    <strong>{{ new Date(item.start || item.starts_at).getDate() }}</strong>
                    <small>{{ new Intl.DateTimeFormat('es-CL', { month: 'short' }).format(new Date(item.start || item.starts_at)).replace('.', '') }}</small>
                  </span>
                  <span class="inicio-milestone__content">
                    <span class="badge" :class="badgeClass(item.source)">{{ item.source_label }}</span>
                    <strong>{{ item.title }}</strong>
                    <small>{{ formatRange(item) }}</small>
                  </span>
                  <i class="bx bx-right-arrow-alt"></i>
                </button>
              </div>
              <div v-else class="inicio-agenda-empty">
                <i class="bx bx-calendar-check"></i>
                <strong>Sin hitos próximos</strong>
                <span>No hay actividades en el rango visible.</span>
              </div>
            </section>
          </BCol>
        </BRow>

        <BRow class="g-4 mt-1 inicio-information-row">
          <BCol xl="5">
            <section class="inicio-information-card">
              <header class="inicio-information-card__header">
                <div class="inicio-card-heading">
                  <span class="inicio-card-heading__icon inicio-card-heading__icon--green"><i class="bx bx-building-house"></i></span>
                  <div>
                    <h5 class="mb-0">Reservas de espacios</h5>
                    <small>Solicitudes y próximos espacios reservados</small>
                  </div>
                </div>
                  <BButton
                    v-if="dashboard.capabilities?.can_create_reservations"
                    size="sm"
                    variant="outline-primary"
                    @click="openRoute('/spaces/reservations')"
                  >
                    <i class="bx bx-plus me-1"></i>
                    Nueva
                  </BButton>
              </header>
              <div class="inicio-information-card__body">

                <div v-if="pendingReservations.length" class="mb-3">
                  <div class="inicio-subsection-heading">
                    <span class="inicio-section-label">Pendientes de revisión</span>
                    <span>{{ pendingReservations.length }}</span>
                  </div>
                  <div class="inicio-compact-list">
                    <button
                      v-for="item in pendingReservations"
                      :key="`pending-${item.id}`"
                      type="button"
                      class="inicio-compact-item"
                      @click="showAgendaItem(item)"
                    >
                      <span class="inicio-compact-item__date"><i class="bx bx-time-five"></i></span>
                      <span class="inicio-compact-item__content">
                        <strong>{{ item.title }}</strong>
                        <small>{{ formatRange(item) }} · {{ item.dependency || item.department || "Sin dependencia" }}</small>
                      </span>
                      <span class="badge" :class="statusClass(item.status)">{{ item.status }}</span>
                    </button>
                  </div>
                </div>

                <div class="inicio-subsection-heading">
                  <span class="inicio-section-label">Próximas</span>
                  <span>{{ upcomingReservations.length }}</span>
                </div>
                <div v-if="upcomingReservations.length" class="inicio-compact-list">
                  <button
                    v-for="item in upcomingReservations"
                    :key="`reservation-${item.id}`"
                    type="button"
                    class="inicio-compact-item"
                    @click="showAgendaItem(item)"
                  >
                    <span class="inicio-compact-item__date">
                      <strong>{{ new Date(item.start || item.starts_at).getDate() }}</strong>
                      <small>{{ new Intl.DateTimeFormat('es-CL', { month: 'short' }).format(new Date(item.start || item.starts_at)).replace('.', '') }}</small>
                    </span>
                    <span class="inicio-compact-item__content">
                      <strong>{{ item.title }}</strong>
                      <small>{{ formatRange(item) }} · {{ item.dependency || item.department || "Sin dependencia" }}</small>
                    </span>
                    <i class="bx bx-chevron-right"></i>
                  </button>
                </div>
                <div v-else class="inicio-panel-empty">
                  <span><i class="bx bx-calendar-check"></i></span>
                  <div><strong>Sin reservas próximas</strong><small>No tienes reservas visibles en los próximos días.</small></div>
                </div>
              </div>
            </section>
          </BCol>

          <BCol xl="7">
            <section class="inicio-information-card">
              <header class="inicio-information-card__header">
                <div class="inicio-card-heading">
                  <span class="inicio-card-heading__icon inicio-card-heading__icon--blue"><i class="bx bx-news"></i></span>
                  <div>
                    <h5 class="mb-0">Actualidad institucional</h5>
                    <small>Eventos y noticias de la comunidad</small>
                  </div>
                </div>
                  <BButton size="sm" variant="outline-secondary" @click="openRoute('/noticias')">
                    Ver todas <i class="bx bx-right-arrow-alt ms-1"></i>
                  </BButton>
              </header>
              <div class="inicio-information-card__body">
                <div v-if="upcomingPublicEvents.length" class="mb-3">
                  <div class="inicio-subsection-heading">
                    <span class="inicio-section-label">Próximos eventos</span>
                    <span>{{ upcomingPublicEvents.length }}</span>
                  </div>
                  <div class="inicio-public-events">
                    <button
                      v-for="event in upcomingPublicEvents"
                      :key="`public-${event.id}`"
                      type="button"
                      class="inicio-public-event"
                      @click="showAgendaItem(event)"
                    >
                      <span class="inicio-public-event__date">
                        <strong>{{ new Date(event.start || event.starts_at).getDate() }}</strong>
                        <small>{{ new Intl.DateTimeFormat('es-CL', { month: 'short' }).format(new Date(event.start || event.starts_at)).replace('.', '') }}</small>
                      </span>
                      <span class="inicio-public-event__content">
                        <span>{{ event.category || "Evento" }}</span>
                        <strong>{{ event.title }}</strong>
                        <small v-if="event.location"><i class="bx bx-map me-1"></i>{{ event.location }}</small>
                      </span>
                    </button>
                  </div>
                </div>

                <div class="inicio-subsection-heading">
                  <span class="inicio-section-label">Últimas noticias</span>
                  <span>{{ visibleNews.length }}</span>
                </div>
                <div v-if="visibleNews.length" class="inicio-news-grid">
                  <button
                    v-for="post in visibleNews"
                    :key="`news-${post.id}`"
                    type="button"
                    class="inicio-news-item"
                    @click="openRoute(post.route)"
                  >
                    <span class="inicio-news-item__category">{{ post.category || "Noticia" }}</span>
                    <strong>{{ post.title }}</strong>
                    <span class="inicio-news-item__footer"><i class="bx bx-calendar me-1"></i>{{ formatDate(post.published_at) }}<i class="bx bx-right-arrow-alt ms-auto"></i></span>
                  </button>
                </div>
                <div v-else class="inicio-panel-empty">
                  <span><i class="bx bx-news"></i></span>
                  <div><strong>Sin noticias recientes</strong><small>Las nuevas publicaciones aparecerán aquí.</small></div>
                </div>
              </div>
            </section>
          </BCol>
        </BRow>
      </template>
    </div>

    <BModal v-model="showEventModal" centered size="lg" hide-footer hide-header class="inicio-event-modal">
      <div v-if="selectedEvent" class="inicio-event-detail">
        <div class="inicio-event-detail__hero" :class="`inicio-event-detail__hero--${selectedEvent.source || 'default'}`">
          <button type="button" class="inicio-event-detail__close" aria-label="Cerrar" @click="showEventModal = false">
            <i class="bx bx-x"></i>
          </button>
          <span class="inicio-event-detail__icon"><i :class="['bx', eventIcon(selectedEvent.source)]"></i></span>
          <div>
            <span>{{ selectedEvent.source_label || 'Actividad' }}</span>
            <h3>{{ selectedEvent.title }}</h3>
          </div>
        </div>
        <div class="inicio-event-detail__body">
          <div class="inicio-event-detail__date">
            <i class="bx bx-calendar-event"></i>
            <div><small>Fecha y hora</small><strong>{{ formatRange(selectedEvent) }}</strong></div>
          </div>
          <div v-if="selectedEvent.detail || selectedEvent.description" class="inicio-event-detail__description">
            <small>Detalle</small>
            <p>{{ selectedEvent.detail || selectedEvent.description }}</p>
          </div>
          <div class="inicio-event-detail__meta">
            <span v-if="selectedEvent.location"><i class="bx bx-map"></i>{{ selectedEvent.location }}</span>
            <span v-if="selectedEvent.dependency || selectedEvent.department"><i class="bx bx-building"></i>{{ selectedEvent.dependency || selectedEvent.department }}</span>
            <span v-if="selectedEvent.status"><i class="bx bx-info-circle"></i>{{ selectedEvent.status }}</span>
          </div>
          <div class="inicio-event-detail__actions">
            <BButton variant="light" @click="showEventModal = false">Cerrar</BButton>
            <BButton v-if="selectedEvent.route" variant="primary" @click="goToSelectedEvent">
              Ver información completa <i class="bx bx-right-arrow-alt ms-1"></i>
            </BButton>
          </div>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.inicio-dashboard {
  --inicio-primary: #4056c7;
  --inicio-ink: #20283a;
  --inicio-muted: #6b7280;
  color: var(--inicio-ink);
}

.inicio-hero {
  background: linear-gradient(128deg, #3448b5 0%, #5369dc 58%, #7083e7 100%);
  border-radius: 18px;
  box-shadow: 0 14px 34px rgba(52, 72, 181, 0.2);
  color: #fff;
  display: grid;
  gap: 2rem;
  grid-template-columns: minmax(0, 1fr) 290px;
  overflow: hidden;
  padding: clamp(1.35rem, 3vw, 2.25rem);
  position: relative;
}

.inicio-hero::after {
  background: rgba(255, 255, 255, 0.07);
  border-radius: 50%;
  content: "";
  height: 280px;
  position: absolute;
  right: -100px;
  top: -150px;
  width: 280px;
}

.inicio-hero__content,
.inicio-hero__summary {
  position: relative;
  z-index: 1;
}

.inicio-hero__subtitle {
  color: rgba(255, 255, 255, 0.78);
  font-size: 0.96rem;
  margin: 0.5rem 0 0;
}

.inicio-hero__summary {
  align-items: center;
  align-self: center;
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  display: grid;
  gap: 0.8rem;
  grid-template-columns: 42px 1fr auto;
  padding: 1rem;
}

.inicio-hero__summary-icon {
  align-items: center;
  background: rgba(255, 255, 255, 0.16);
  border-radius: 10px;
  display: flex;
  font-size: 1.35rem;
  height: 42px;
  justify-content: center;
  width: 42px;
}

.inicio-hero__summary strong,
.inicio-hero__summary span {
  display: block;
}

.inicio-hero__summary strong {
  font-size: 1.05rem;
}

.inicio-hero__summary span:not(.inicio-hero__summary-label) {
  color: rgba(255, 255, 255, 0.74);
  font-size: 0.78rem;
}

.inicio-hero__summary-label {
  color: rgba(255, 255, 255, 0.72);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.inicio-hero__summary small {
  color: rgba(255, 255, 255, 0.62);
  grid-column: 1 / -1;
}

.inicio-refresh {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 50%;
  color: #fff;
  display: flex;
  font-size: 1.35rem;
  height: 36px;
  justify-content: center;
  width: 36px;
}

.inicio-refresh:hover {
  background: rgba(255, 255, 255, 0.14);
}

.inicio-eyebrow,
.inicio-section-label {
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.inicio-eyebrow { color: rgba(255, 255, 255, 0.72); }
.inicio-section-label { color: var(--inicio-muted); }

.inicio-title {
  color: #fff;
  font-size: clamp(1.65rem, 2.5vw, 2.25rem);
  font-weight: 750;
  line-height: 1.2;
  margin: 0.3rem 0 0;
}

.inicio-metric {
  border-top: 3px solid #6c757d !important;
  border-radius: 12px;
  box-shadow: 0 5px 20px rgba(42, 48, 66, 0.06);
  transition: box-shadow 0.18s ease, transform 0.18s ease;
}

.inicio-metric:hover {
  box-shadow: 0 10px 26px rgba(42, 48, 66, 0.1);
  transform: translateY(-2px);
}

.inicio-metric--primary {
  border-top-color: #556ee6 !important;
}

.inicio-metric--warning {
  border-top-color: #f1b44c !important;
}

.inicio-metric--success {
  border-top-color: #34c38f !important;
}

.inicio-metric--info {
  border-top-color: #50a5f1 !important;
}

.inicio-metric__top { align-items: center; display: flex; justify-content: space-between; }

.inicio-metric__icon {
  align-items: center;
  background: #f3f6f9;
  border-radius: 8px;
  display: inline-flex;
  height: 42px;
  justify-content: center;
  width: 42px;
}

.inicio-metric__icon i {
  font-size: 1.35rem;
}

.inicio-metric__value {
  color: #212529;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.1;
  margin: 0;
}

.inicio-metric__label {
  font-weight: 700;
  margin-top: 0.9rem;
}

.inicio-metric__detail {
  color: var(--inicio-muted);
  font-size: 0.8rem;
  margin-top: 0.2rem;
}

.inicio-legend {
  align-items: center;
  color: #6c757d;
  display: flex;
  flex-wrap: wrap;
  font-size: 0.82rem;
  gap: 0.75rem;
}

.inicio-dot {
  border-radius: 999px;
  display: inline-block;
  height: 0.65rem;
  margin-right: 0.25rem;
  width: 0.65rem;
}

.inicio-dot--calendar {
  background: #556ee6;
}

.inicio-dot--reservation {
  background: #34c38f;
}

.inicio-dot--public {
  background: #50a5f1;
}

.inicio-list,
.inicio-announcements {
  display: grid;
  gap: 0.65rem;
}

.inicio-list__item {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: 0.35rem;
  padding: 0.75rem;
  text-align: left;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
  width: 100%;
}

.inicio-list__item:hover {
  border-color: #bfc8d6;
  box-shadow: 0 6px 18px rgba(33, 37, 41, 0.06);
  transform: translateY(-1px);
}

.inicio-list__item:focus-visible,
.inicio-refresh:focus-visible {
  outline: 3px solid rgba(80, 165, 241, 0.45);
  outline-offset: 2px;
}

.inicio-list__title {
  color: #212529;
  font-weight: 700;
  line-height: 1.25;
}

.inicio-list__meta {
  color: #6c757d;
  font-size: 0.82rem;
  line-height: 1.25;
}

.inicio-empty {
  background: #f8f9fa;
  border: 1px dashed #d7dde6;
  border-radius: 8px;
  color: #6c757d;
  padding: 1rem;
  text-align: center;
}

.inicio-card-heading { align-items: center; display: flex; gap: 0.75rem; }
.inicio-card-heading small { color: var(--inicio-muted); }
.inicio-card-heading__icon {
  align-items: center;
  background: #eef1ff;
  border-radius: 10px;
  color: var(--inicio-primary);
  display: flex;
  flex: 0 0 42px;
  font-size: 1.25rem;
  height: 42px;
  justify-content: center;
}

.inicio-announcements {
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.inicio-announcement {
  background: #ffffff;
  border: 1px solid #e9edf3;
  border-left: 4px solid #50a5f1;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  min-height: 100%;
  padding: 0.85rem;
}

.inicio-announcement--urgent {
  border-left-color: #f46a6a;
}

.inicio-announcement--important {
  border-left-color: #f1b44c;
}

.inicio-announcement--unread {
  background: #fbfcff;
}

.inicio-announcement__header,
.inicio-announcement__actions,
.inicio-announcement__meta {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.inicio-announcement__title {
  color: #212529;
  font-weight: 700;
  line-height: 1.25;
  margin: 0;
}

.inicio-announcement__body {
  color: #495057;
  display: -webkit-box;
  font-size: 0.9rem;
  line-height: 1.45;
  margin: 0;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.inicio-announcement__meta {
  color: #6c757d;
  font-size: 0.78rem;
}

.inicio-information-card {
  background: #fff;
  border: 1px solid #e8ebf2;
  border-radius: 14px;
  box-shadow: 0 6px 24px rgba(35, 44, 72, 0.06);
  overflow: hidden;
}

.inicio-information-card__header {
  align-items: center;
  border-bottom: 1px solid #edf0f5;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 1.1rem 1.2rem;
}

.inicio-information-card__body {
  padding: 1.15rem 1.2rem;
}

.inicio-card-heading__icon--green { background: #e9f8f2; color: #218762; }
.inicio-card-heading__icon--blue { background: #eaf4fd; color: #2c82c5; }

.inicio-subsection-heading {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.65rem;
}

.inicio-subsection-heading > span:last-child {
  align-items: center;
  background: #f0f2f6;
  border-radius: 999px;
  color: #707887;
  display: flex;
  font-size: 0.7rem;
  font-weight: 800;
  height: 22px;
  justify-content: center;
  min-width: 22px;
  padding: 0 0.4rem;
}

.inicio-compact-list {
  display: grid;
  gap: 0.55rem;
}

.inicio-compact-item {
  align-items: center;
  background: #fff;
  border: 1px solid #e8ecf2;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: 0.7rem;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  padding: 0.7rem;
  text-align: left;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  width: 100%;
}

.inicio-compact-item:hover,
.inicio-news-item:hover,
.inicio-public-event:hover {
  border-color: #cbd3e2;
  box-shadow: 0 6px 16px rgba(38, 47, 74, .07);
  transform: translateY(-1px);
}

.inicio-compact-item__date {
  align-items: center;
  background: #f3f5f9;
  border-radius: 9px;
  color: var(--inicio-primary);
  display: flex;
  flex-direction: column;
  height: 44px;
  justify-content: center;
  line-height: 1;
  width: 44px;
}

.inicio-compact-item__date > i { font-size: 1.25rem; }
.inicio-compact-item__date strong { font-size: 1.05rem; }
.inicio-compact-item__date small { color: var(--inicio-muted); font-size: .62rem; font-weight: 750; margin-top: .15rem; text-transform: uppercase; }
.inicio-compact-item__content { display: grid; gap: .2rem; min-width: 0; }
.inicio-compact-item__content strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inicio-compact-item__content small { color: var(--inicio-muted); font-size: .76rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inicio-compact-item > .bx { color: #a0a7b5; font-size: 1.2rem; }

.inicio-panel-empty {
  align-items: center;
  background: #f8fafc;
  border: 1px dashed #d7dde7;
  border-radius: 11px;
  color: var(--inicio-muted);
  display: flex;
  gap: .8rem;
  min-height: 92px;
  padding: 1rem;
}

.inicio-panel-empty > span {
  align-items: center;
  background: #edf0fa;
  border-radius: 10px;
  color: #6475ce;
  display: flex;
  flex: 0 0 42px;
  font-size: 1.3rem;
  height: 42px;
  justify-content: center;
}

.inicio-panel-empty strong,
.inicio-panel-empty small { display: block; }
.inicio-panel-empty strong { color: var(--inicio-ink); }
.inicio-panel-empty small { font-size: .78rem; margin-top: .15rem; }

.inicio-public-events,
.inicio-news-grid {
  display: grid;
  gap: .65rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.inicio-public-event,
.inicio-news-item {
  background: #fff;
  border: 1px solid #e8ecf2;
  border-radius: 10px;
  color: inherit;
  text-align: left;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  width: 100%;
}

.inicio-public-event {
  align-items: center;
  display: grid;
  gap: .7rem;
  grid-template-columns: 48px minmax(0, 1fr);
  padding: .75rem;
}

.inicio-public-event__date {
  align-items: center;
  background: #eaf5fe;
  border-radius: 9px;
  color: #287ab7;
  display: flex;
  flex-direction: column;
  height: 52px;
  justify-content: center;
  line-height: 1;
}

.inicio-public-event__date strong { font-size: 1.15rem; }
.inicio-public-event__date small { font-size: .65rem; font-weight: 750; margin-top: .2rem; text-transform: uppercase; }
.inicio-public-event__content { display: grid; gap: .18rem; min-width: 0; }
.inicio-public-event__content > span { color: #2c82c5; font-size: .68rem; font-weight: 750; text-transform: uppercase; }
.inicio-public-event__content strong { line-height: 1.25; }
.inicio-public-event__content small { color: var(--inicio-muted); font-size: .75rem; }

.inicio-news-item {
  display: grid;
  gap: .45rem;
  padding: .85rem;
}

.inicio-news-item__category {
  color: #667085;
  font-size: .68rem;
  font-weight: 750;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.inicio-news-item > strong { line-height: 1.3; }
.inicio-news-item__footer { align-items: center; color: var(--inicio-muted); display: flex; font-size: .75rem; }

.inicio-calendar-section,
.inicio-agenda-card {
  background: #fff;
  border: 1px solid #e8ebf2;
  border-radius: 14px;
  box-shadow: 0 6px 24px rgba(35, 44, 72, 0.06);
}

.inicio-calendar-heading {
  align-items: center;
  border-bottom: 1px solid #edf0f5;
  display: flex;
  justify-content: space-between;
  padding: 1.15rem 1.25rem;
}

.inicio-calendar-heading p,
.inicio-card-heading small {
  color: var(--inicio-muted);
  font-size: 0.82rem;
}

.inicio-calendar-wrap {
  padding: 1.25rem;
}

.inicio-agenda-card {
  padding: 1.2rem;
}

.inicio-agenda-card--today {
  background: linear-gradient(180deg, #fff 0%, #fbfcff 100%);
}

.inicio-agenda-header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.inicio-agenda-header h4 {
  margin: 0.18rem 0 0;
}

.inicio-agenda-count {
  align-items: center;
  background: #e9edff;
  border-radius: 50%;
  color: var(--inicio-primary);
  display: flex;
  font-size: 0.9rem;
  font-weight: 800;
  height: 38px;
  justify-content: center;
  width: 38px;
}

.inicio-agenda-count--muted {
  background: #f0f2f6;
  color: #606979;
}

.inicio-timeline,
.inicio-milestones {
  display: grid;
  gap: 0.65rem;
}

.inicio-timeline-item {
  align-items: center;
  background: transparent;
  border: 0;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 42px 1fr auto;
  padding: 0.65rem;
  text-align: left;
  transition: background 0.15s ease, transform 0.15s ease;
  width: 100%;
}

.inicio-timeline-item:hover {
  background: #f4f6fb;
  transform: translateX(2px);
}

.inicio-timeline-item__icon {
  align-items: center;
  background: #eef1ff;
  border-radius: 10px;
  color: #556ee6;
  display: flex;
  font-size: 1.15rem;
  height: 42px;
  justify-content: center;
  width: 42px;
}

.inicio-timeline-item__icon--reservation { background: #e8f8f2; color: #218762; }
.inicio-timeline-item__icon--public_event { background: #eaf5fe; color: #2c82c5; }

.inicio-timeline-item__content,
.inicio-milestone__content {
  display: grid;
  gap: 0.25rem;
  min-width: 0;
}

.inicio-timeline-item__content strong,
.inicio-milestone__content strong {
  color: var(--inicio-ink);
  line-height: 1.3;
}

.inicio-timeline-item__content small,
.inicio-milestone__content small,
.inicio-timeline-item__top {
  color: var(--inicio-muted);
  font-size: 0.78rem;
}

.inicio-timeline-item__top {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.inicio-timeline-item__arrow { color: #a0a7b5; font-size: 1.25rem; }

.inicio-milestones {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.inicio-milestone {
  align-items: center;
  background: #fff;
  border: 1px solid #e9edf4;
  border-radius: 11px;
  color: inherit;
  display: grid;
  gap: 0.7rem;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  padding: 0.75rem;
  text-align: left;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
  width: 100%;
}

.inicio-milestone:hover {
  border-color: #cbd3e8;
  box-shadow: 0 7px 18px rgba(40, 49, 78, 0.08);
  transform: translateY(-1px);
}

.inicio-milestone__date {
  align-items: center;
  background: #f3f5fa;
  border-radius: 9px;
  display: flex;
  flex-direction: column;
  height: 52px;
  justify-content: center;
  line-height: 1;
}

.inicio-milestone__date strong { font-size: 1.2rem; }
.inicio-milestone__date small { color: var(--inicio-muted); font-size: 0.7rem; font-weight: 700; margin-top: 0.2rem; text-transform: uppercase; }
.inicio-milestone > .bx { color: #9ca3af; font-size: 1.25rem; }

.inicio-agenda-empty {
  align-items: center;
  background: #f8fafc;
  border: 1px dashed #d8dee9;
  border-radius: 11px;
  color: var(--inicio-muted);
  display: flex;
  flex-direction: column;
  min-height: 150px;
  justify-content: center;
  padding: 1rem;
  text-align: center;
}

.inicio-agenda-empty i { color: #6f80d9; font-size: 2rem; margin-bottom: 0.5rem; }
.inicio-agenda-empty strong { color: var(--inicio-ink); }
.inicio-agenda-empty span { font-size: 0.82rem; margin-top: 0.2rem; }

.inicio-event-detail { margin: -1rem; overflow: hidden; }
.inicio-event-detail__hero {
  align-items: center;
  background: linear-gradient(125deg, #4258c8, #687be0);
  color: #fff;
  display: flex;
  gap: 1rem;
  min-height: 150px;
  padding: 1.5rem;
  position: relative;
}

.inicio-event-detail__hero--reservation { background: linear-gradient(125deg, #258362, #41ad83); }
.inicio-event-detail__hero--public_event { background: linear-gradient(125deg, #287ab7, #50a5f1); }
.inicio-event-detail__hero > div > span { color: rgba(255,255,255,.72); font-size: .76rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.inicio-event-detail__hero h3 { color: #fff; font-size: clamp(1.25rem, 3vw, 1.7rem); margin: .3rem 2rem 0 0; }
.inicio-event-detail__icon { align-items: center; background: rgba(255,255,255,.16); border-radius: 12px; display: flex; flex: 0 0 52px; font-size: 1.5rem; height: 52px; justify-content: center; }
.inicio-event-detail__close { align-items: center; background: rgba(255,255,255,.12); border: 0; border-radius: 50%; color: #fff; display: flex; font-size: 1.4rem; height: 34px; justify-content: center; position: absolute; right: 1rem; top: 1rem; width: 34px; }
.inicio-event-detail__body { padding: 1.5rem; }
.inicio-event-detail__date { align-items: center; background: #f5f7fb; border-radius: 11px; display: flex; gap: .8rem; padding: .85rem 1rem; }
.inicio-event-detail__date > i { color: var(--inicio-primary); font-size: 1.4rem; }
.inicio-event-detail__date small, .inicio-event-detail__date strong { display: block; }
.inicio-event-detail__date small, .inicio-event-detail__description small { color: var(--inicio-muted); font-size: .72rem; font-weight: 700; text-transform: uppercase; }
.inicio-event-detail__description { margin-top: 1.2rem; }
.inicio-event-detail__description p { color: #4b5563; line-height: 1.6; margin: .35rem 0 0; white-space: pre-line; }
.inicio-event-detail__meta { display: flex; flex-wrap: wrap; gap: .6rem 1rem; margin-top: 1rem; }
.inicio-event-detail__meta span { align-items: center; color: var(--inicio-muted); display: flex; font-size: .84rem; gap: .35rem; }
.inicio-event-detail__actions { border-top: 1px solid #edf0f5; display: flex; justify-content: flex-end; gap: .65rem; margin-top: 1.4rem; padding-top: 1rem; }

:deep(.fc) {
  --fc-border-color: #e7eaf0;
  --fc-button-bg-color: #fff;
  --fc-button-border-color: #dfe3eb;
  --fc-button-text-color: #596274;
  --fc-button-hover-bg-color: #f2f4f8;
  --fc-button-hover-border-color: #ccd2dd;
  --fc-button-active-bg-color: #556ee6;
  --fc-button-active-border-color: #556ee6;
  font-size: 0.88rem;
}

:deep(.fc .fc-toolbar-title) {
  color: var(--inicio-ink);
  font-size: 1.25rem;
  font-weight: 750;
  text-transform: capitalize;
}

:deep(.fc .fc-button) {
  box-shadow: none !important;
  font-size: 0.82rem;
  font-weight: 600;
  padding: .45rem .65rem;
}

:deep(.fc-event) {
  border: 0;
  border-radius: 5px;
  box-shadow: 0 2px 5px rgba(31, 41, 55, .1);
  cursor: pointer;
  padding: 2px 4px;
}

:deep(.fc .fc-col-header-cell) { background: #f7f8fb; padding: .55rem 0; }
:deep(.fc .fc-col-header-cell-cushion) { color: #646c7c; font-size: .75rem; font-weight: 750; text-transform: uppercase; }
:deep(.fc .fc-daygrid-day-number) { color: #444c5c; font-weight: 600; padding: .45rem .55rem; }
:deep(.fc .fc-day-today) { background: #f2f4ff !important; }
:deep(.fc .fc-day-today .fc-daygrid-day-number) { align-items: center; background: #556ee6; border-radius: 50%; color: #fff; display: flex; height: 28px; justify-content: center; margin: .25rem; padding: 0; width: 28px; }
:deep(.fc .fc-daygrid-more-link) { color: #556ee6; font-weight: 700; margin: 3px; }
:deep(.fc .fc-list-event:hover td) { background: #f5f7fb; }

@media (max-width: 991.98px) {
  .inicio-calendar-heading { align-items: flex-start; flex-direction: column; gap: 1rem; }
  .inicio-milestones { grid-template-columns: 1fr; }
  .inicio-information-row { gap: 1rem !important; }
}

@media (max-width: 767.98px) {
  .inicio-hero {
    gap: 1.2rem;
    grid-template-columns: 1fr;
  }

  .inicio-hero__summary {
    width: 100%;
  }

  .inicio-information-card__header {
    align-items: flex-start;
  }

  .inicio-public-events,
  .inicio-news-grid {
    grid-template-columns: 1fr;
  }

  .inicio-compact-item {
    grid-template-columns: 44px minmax(0, 1fr);
  }

  .inicio-compact-item > .badge,
  .inicio-compact-item > .bx {
    grid-column: 2;
    justify-self: start;
  }

  .inicio-calendar-wrap { padding: .75rem; }
  .inicio-calendar-heading { padding: 1rem; }
  .inicio-legend { gap: .45rem .75rem; }
  .inicio-event-detail__hero { align-items: flex-start; flex-direction: column; }
  .inicio-event-detail__actions { flex-direction: column-reverse; }
  .inicio-event-detail__actions .btn { width: 100%; }

  :deep(.fc .fc-toolbar) {
    align-items: stretch;
    flex-direction: column;
    gap: 0.65rem;
  }

  :deep(.fc .fc-toolbar-chunk) {
    display: flex;
    justify-content: center;
  }
}
</style>
