<script>
import axios from "axios";
import Layout from "../layouts/main.vue";
import LoadingState from "../components/ui/loading-state.vue";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import esLocale from "@fullcalendar/core/locales/es";

const emptyDashboard = () => ({
  generated_at: null,
  user: {},
  capabilities: {},
  metrics: [],
  calendar: { events: [], range: {}, sources: [] },
  agenda: { today: [], upcoming: [] },
  relevant_calendar: { upcoming: [], overdue_count: 0, current_month_count: 0 },
  reservations: { upcoming: [], pending: [], upcoming_count: 0, pending_count: 0 },
  public_events: { upcoming: [], upcoming_count: 0 },
  tasks: { items: [], pending_count: 0, overdue_count: 0, today_count: 0, blocked_count: 0 },
  documents: { items: [], total: 0, new_count: 0 },
  attention: { items: [], total: 0, critical_count: 0, today_count: 0 },
  news: [],
  internal_announcements: { items: [], unread_count: 0, pending_ack_count: 0 },
  weather: {
    provider: "WeatherAPI.com",
    location: { name: "Valdivia", region: "Región de Los Ríos", country: "Chile" },
    last_synced_at: null,
    days: [],
  },
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
      calendarSourceFilters: [],
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
    greetingIcon() {
      const hour = new Date().getHours();

      if (hour < 12) return "bx-sun";
      if (hour < 19) return "bx-sun";

      return "bx-moon";
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
    attentionItems() {
      return this.dashboard.attention?.items || [];
    },
    taskItems() {
      return this.dashboard.tasks?.items || [];
    },
    disseminatedDocuments() {
      return this.dashboard.documents?.items || [];
    },
    calendarSources() {
      return this.dashboard.calendar?.sources || [];
    },
    filteredCalendarEvents() {
      const events = this.dashboard.calendar?.events || [];

      if (!this.calendarSourceFilters.length) {
        return [];
      }

      return events.filter((event) => this.calendarSourceFilters.includes(event.source));
    },
    allCalendarSourcesActive() {
      return this.calendarSources.length > 0
        && this.calendarSources.every((source) => this.calendarSourceFilters.includes(source.key));
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
      return this.dashboard.attention?.total || 0;
    },
    weatherDays() {
      return this.dashboard.weather?.days || [];
    },
    weatherLocation() {
      return this.dashboard.weather?.location || {};
    },
    attentionSummaryText() {
      if (!this.attentionCount) return "Sin pendientes prioritarios";

      return `${this.attentionCount} ${this.attentionCount === 1 ? "elemento requiere" : "elementos requieren"} tu atención`;
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
        this.syncCalendarSourceFilters();
        this.refreshCalendarEvents();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
        this.refreshing = false;
      }
    },
    buildCalendarOptions(events) {
      const compact = window.innerWidth < 768;

      return {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin],
        locales: [esLocale],
        locale: "es",
        timeZone: "local",
        themeSystem: "standard",
        initialView: compact ? "listMonth" : "dayGridMonth",
        firstDay: 1,
        height: "auto",
        contentHeight: compact ? "auto" : 560,
        expandRows: false,
        nowIndicator: true,
        dayMaxEvents: compact ? 2 : 3,
        eventDisplay: "block",
        headerToolbar: compact
          ? {
              left: "prev,next",
              center: "title",
              right: "listMonth,dayGridMonth",
            }
          : {
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
    syncCalendarSourceFilters() {
      const available = this.calendarSources.map((source) => source.key);
      let stored = [];

      try {
        stored = JSON.parse(localStorage.getItem("inicio_calendar_sources") || "[]");
      } catch (error) {
        stored = [];
      }

      const validStored = Array.isArray(stored)
        ? stored.filter((source) => available.includes(source))
        : [];

      this.calendarSourceFilters = validStored.length ? validStored : available;
    },
    toggleCalendarSource(sourceKey) {
      if (this.calendarSourceFilters.includes(sourceKey)) {
        this.calendarSourceFilters = this.calendarSourceFilters.filter((key) => key !== sourceKey);
      } else {
        this.calendarSourceFilters = [...this.calendarSourceFilters, sourceKey];
      }

      this.persistCalendarSourceFilters();
      this.refreshCalendarEvents();
    },
    toggleAllCalendarSources() {
      this.calendarSourceFilters = this.allCalendarSourcesActive
        ? []
        : this.calendarSources.map((source) => source.key);
      this.persistCalendarSourceFilters();
      this.refreshCalendarEvents();
    },
    persistCalendarSourceFilters() {
      localStorage.setItem("inicio_calendar_sources", JSON.stringify(this.calendarSourceFilters));
    },
    refreshCalendarEvents() {
      this.calendarOptions = this.buildCalendarOptions(this.filteredCalendarEvents);
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
        task: "bg-warning-subtle text-warning",
      }[source] || "bg-secondary-subtle text-secondary";
    },
    eventIcon(source) {
      return {
        relevant_calendar: "bx-calendar-event",
        reservation: "bx-building-house",
        public_event: "bx-broadcast",
        task: "bx-list-check",
      }[source] || "bx-calendar";
    },
    statusClass(status) {
      return {
        pendiente: "bg-warning-subtle text-warning",
        en_progreso: "bg-primary-subtle text-primary",
        bloqueada: "bg-danger-subtle text-danger",
        en_revision: "bg-info-subtle text-info",
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
    attentionClass(urgency) {
      return `inicio-attention-item--${urgency || "normal"}`;
    },
    normalizedIcon(icon, fallback = "bx-grid-alt") {
      const value = String(icon || "");
      const replacements = {
        "bx-calendar-star": "bx-calendar-event",
        "bx-message-square-detail": "bx-envelope",
      };

      return replacements[value] || value || fallback;
    },
    attentionIcon(item) {
      const route = String(item?.route || "");
      const fallback = route.includes("comunic") ? "bx-envelope" : "bx-bell";

      return this.normalizedIcon(item?.icon, fallback);
    },
    attentionLabel(item) {
      if (!item.due_at) return item.detail || "Pendiente";

      const date = this.parseDateValue(item.due_at);
      const today = new Date();
      const tomorrow = new Date();
      tomorrow.setDate(today.getDate() + 1);

      if (date.toDateString() === today.toDateString()) return "Hoy";
      if (date.toDateString() === tomorrow.toDateString()) return "Mañana";

      return this.formatDate(item.due_at);
    },
    taskPriorityClass(priority) {
      return {
        urgente: "bg-danger-subtle text-danger",
        alta: "bg-warning-subtle text-warning",
        media: "bg-primary-subtle text-primary",
        baja: "bg-secondary-subtle text-secondary",
      }[priority] || "bg-secondary-subtle text-secondary";
    },
    documentStatusClass(status) {
      return status === "por_vencer"
        ? "bg-warning-subtle text-warning"
        : "bg-success-subtle text-success";
    },
    quickLinkIcon(link) {
      if (link?.icon) return this.normalizedIcon(link.icon);

      const route = String(link?.route || "");
      const title = String(link?.title || "").toLocaleLowerCase("es");

      if (route.includes("calendar") || title.includes("calendario")) return "bx-calendar-event";
      if (route.includes("comunic") || title.includes("comunicaciones")) return "bx-message-square-detail";
      if (route.includes("reservation") || title.includes("reserv")) return "bx-building-house";
      if (route.includes("permission") || title.includes("permis")) return "bx-calendar-minus";
      if (route.includes("task") || title.includes("backlog")) return "bx-list-check";
      if (route.includes("document") || title.includes("document")) return "bx-file";
      if (route.includes("news") || title.includes("noticia")) return "bx-news";
      if (route.includes("event") || title.includes("evento")) return "bx-calendar-star";
      if (route.includes("profile") || title.includes("perfil")) return "bx-user-circle";

      return "bx-grid-alt";
    },
    quickLinkToneClass(index) {
      return `inicio-quick-link--tone-${(index % 5) + 1}`;
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

        await this.loadDashboard();
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
      }).format(this.parseDateValue(value));
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
    formatWeatherDay(value) {
      const date = this.parseDateValue(value);
      const today = new Date();
      const tomorrow = new Date();
      tomorrow.setDate(today.getDate() + 1);

      if (date.toDateString() === today.toDateString()) return "Hoy";
      if (date.toDateString() === tomorrow.toDateString()) return "Mañana";

      const label = new Intl.DateTimeFormat("es-CL", { weekday: "long" }).format(date);
      return label.charAt(0).toUpperCase() + label.slice(1);
    },
    formatWeatherDate(value) {
      return new Intl.DateTimeFormat("es-CL", {
        day: "numeric",
        month: "short",
      }).format(this.parseDateValue(value)).replace(".", "");
    },
    parseDateValue(value) {
      const text = String(value || "");

      return /^\d{4}-\d{2}-\d{2}$/.test(text)
        ? new Date(`${text}T12:00:00`)
        : new Date(value);
    },
    calendarDay(value) {
      return this.parseDateValue(value).getDate();
    },
    calendarMonth(value) {
      return new Intl.DateTimeFormat("es-CL", { month: "short" })
        .format(this.parseDateValue(value))
        .replace(".", "");
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

      const sameDay = this.parseDateValue(start).toDateString() === this.parseDateValue(end).toDateString();

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
      <section class="inicio-hero mb-4" aria-labelledby="inicio-title">
        <div class="inicio-hero__content">
          <div class="inicio-hero__meta">
            <span class="inicio-hero__badge"><i class="bx bx-command"></i> Centro de operaciones</span>
            <span class="inicio-eyebrow"><i class="bx bx-calendar"></i>{{ currentDateText }}</span>
          </div>
          <h1 id="inicio-title" class="inicio-title">{{ greeting }}, {{ currentUserName }}</h1>
          <p class="inicio-hero__subtitle">Tu jornada institucional, ordenada para decidir qué atender primero.</p>

          <div class="inicio-hero__signals" aria-label="Estado de la jornada">
            <span :class="{ 'inicio-hero__signal--alert': attentionCount }">
              <i class="bx bx-bell"></i>{{ attentionCount }} {{ attentionCount === 1 ? 'pendiente' : 'pendientes' }}
            </span>
            <span><i class="bx bx-list-check"></i>{{ dashboard.tasks?.pending_count || 0 }} {{ dashboard.tasks?.pending_count === 1 ? 'tarea' : 'tareas' }}</span>
            <span><i class="bx bx-envelope"></i>{{ unreadInternalAnnouncements }} {{ unreadInternalAnnouncements === 1 ? 'aviso nuevo' : 'avisos nuevos' }}</span>
          </div>
        </div>

        <aside class="inicio-hero__summary" aria-label="Resumen de hoy">
          <div class="inicio-hero__summary-main">
            <div class="inicio-hero__summary-icon"><i class="bx" :class="greetingIcon"></i></div>
            <div>
              <span class="inicio-hero__summary-label">Resumen de hoy</span>
              <strong>{{ agendaToday.length }} {{ agendaToday.length === 1 ? 'actividad' : 'actividades' }}</strong>
              <span>{{ attentionSummaryText }}</span>
            </div>
          </div>
          <div class="inicio-hero__summary-footer">
            <small><i class="bx bx-time-five"></i> Actualizado {{ lastUpdatedText }}</small>
            <button class="inicio-refresh" type="button" :disabled="refreshing" @click="loadDashboard">
              <i class="bx bx-refresh" :class="{ 'bx-spin': refreshing }"></i>
              <span>{{ refreshing ? 'Actualizando' : 'Actualizar' }}</span>
            </button>
          </div>
        </aside>
      </section>

      <BAlert v-if="error" show variant="danger" class="mb-4">
        {{ error }}
      </BAlert>

      <LoadingState v-if="loading" message="Cargando inicio..." />

      <template v-else>
        <BRow class="g-3 mb-4 inicio-metrics-grid">
          <BCol v-for="metric in dashboard.metrics" :key="metric.key" cols="6" xl="3">
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

        <section class="inicio-weather mb-4" aria-labelledby="inicio-weather-title">
          <header class="inicio-weather__header">
            <div class="inicio-weather__heading">
              <span class="inicio-weather__heading-icon"><i class="bx bx-cloud-light-rain"></i></span>
              <div>
                <span class="inicio-section-label">Pronóstico local</span>
                <h2 id="inicio-weather-title">Clima en {{ weatherLocation.name || 'Valdivia' }}</h2>
                <p>{{ weatherLocation.region || 'Región de Los Ríos' }}, {{ weatherLocation.country || 'Chile' }}</p>
              </div>
            </div>
            <div class="inicio-weather__source">
              <span v-if="dashboard.weather?.last_synced_at">
                <i class="bx bx-time-five"></i> Actualizado {{ formatDateTime(dashboard.weather.last_synced_at) }}
              </span>
              <a href="https://www.weatherapi.com/" target="_blank" rel="noopener noreferrer">
                Datos de WeatherAPI.com <i class="bx bx-link-external"></i>
              </a>
            </div>
          </header>

          <div v-if="weatherDays.length" class="inicio-weather__days">
            <article
              v-for="(day, index) in weatherDays"
              :key="day.date"
              class="inicio-weather-day"
              :class="{ 'inicio-weather-day--today': index === 0 }"
            >
              <div class="inicio-weather-day__date">
                <strong>{{ formatWeatherDay(day.date) }}</strong>
                <span>{{ formatWeatherDate(day.date) }}</span>
              </div>
              <img
                v-if="day.condition?.icon_url"
                class="inicio-weather-day__icon"
                :src="day.condition.icon_url"
                :alt="day.condition.text || 'Condición meteorológica'"
                width="64"
                height="64"
              >
              <span v-else class="inicio-weather-day__icon inicio-weather-day__icon--fallback">
                <i class="bx bx-cloud"></i>
              </span>
              <p class="inicio-weather-day__condition">{{ day.condition?.text || 'Sin información' }}</p>
              <div class="inicio-weather-day__temperature">
                <strong>{{ Math.round(day.max_temp_c) }}°</strong>
                <span>{{ Math.round(day.min_temp_c) }}°</span>
              </div>
              <div class="inicio-weather-day__details">
                <span title="Probabilidad de lluvia"><i class="bx bx-droplet"></i>{{ day.chance_of_rain }}%</span>
                <span title="Humedad promedio"><i class="bx bx-water"></i>{{ day.avg_humidity }}%</span>
                <span title="Precipitación estimada"><i class="bx bx-cloud-rain"></i>{{ day.total_precip_mm }} mm</span>
              </div>
            </article>
          </div>
          <div v-else class="inicio-weather__empty">
            <i class="bx bx-cloud"></i>
            <div>
              <strong>Pronóstico pendiente de sincronización</strong>
              <span>Los datos diarios de Valdivia aparecerán aquí tras la próxima actualización.</span>
            </div>
          </div>
        </section>

        <div class="inicio-section-intro">
          <div>
            <span class="inicio-section-label">Prioridad personal</span>
            <h2>Lo importante ahora</h2>
            <p>Pendientes y tareas reunidos según tu perfil de acceso.</p>
          </div>
          <span class="inicio-section-intro__status"><i class="bx bx-shield-quarter"></i> Vista personalizada</span>
        </div>

        <BRow class="g-4 mb-4">
          <BCol lg="7">
            <section class="inicio-focus-card h-100">
              <header class="inicio-focus-card__header">
                <div class="inicio-card-heading">
                  <span class="inicio-card-heading__icon inicio-card-heading__icon--attention">
                    <i class="bx bx-bell"></i>
                  </span>
                  <div>
                    <h4 class="mb-1">Requiere mi atención</h4>
                    <small>Ordenado por urgencia, sin importar el módulo de origen</small>
                  </div>
                </div>
                <span class="inicio-focus-total">{{ dashboard.attention?.total || 0 }}</span>
              </header>

              <div v-if="attentionItems.length" class="inicio-attention-list">
                <button
                  v-for="item in attentionItems"
                  :key="item.id"
                  type="button"
                  class="inicio-attention-item"
                  :class="attentionClass(item.urgency)"
                  @click="openRoute(item.route)"
                >
                  <span class="inicio-attention-item__icon"><i :class="['bx', attentionIcon(item)]"></i></span>
                  <span class="inicio-attention-item__content">
                    <span class="inicio-attention-item__meta">
                      <span>{{ item.type_label }}</span>
                      <strong>{{ attentionLabel(item) }}</strong>
                    </span>
                    <strong>{{ item.title }}</strong>
                    <small>{{ item.detail }}</small>
                  </span>
                  <span class="inicio-attention-item__action">
                    {{ item.action_label }} <i class="bx bx-right-arrow-alt"></i>
                  </span>
                </button>
              </div>
              <div v-else class="inicio-agenda-empty inicio-agenda-empty--compact">
                <i class="bx bx-check-shield"></i>
                <strong>Todo al día</strong>
                <span>No hay elementos urgentes o pendientes para ti.</span>
              </div>
            </section>
          </BCol>

          <BCol lg="5">
            <section class="inicio-focus-card h-100">
              <header class="inicio-focus-card__header">
                <div class="inicio-card-heading">
                  <span class="inicio-card-heading__icon inicio-card-heading__icon--tasks">
                    <i class="bx bx-list-check"></i>
                  </span>
                  <div>
                    <h4 class="mb-1">Mis tareas</h4>
                    <small>{{ dashboard.tasks?.overdue_count || 0 }} vencidas · {{ dashboard.tasks?.today_count || 0 }} para hoy</small>
                  </div>
                </div>
                <BButton
                  v-if="dashboard.capabilities?.can_view_tasks"
                  size="sm"
                  variant="outline-primary"
                  @click="openRoute('/tasks/backlog')"
                >
                  Ver backlog
                </BButton>
              </header>

              <div v-if="taskItems.length" class="inicio-task-list">
                <button
                  v-for="task in taskItems"
                  :key="task.id"
                  type="button"
                  class="inicio-task-item"
                  @click="openRoute(task.route)"
                >
                  <span class="inicio-task-item__check"><i class="bx bx-circle"></i></span>
                  <span class="inicio-task-item__content">
                    <strong>{{ task.title }}</strong>
                    <small :class="{ 'text-danger': task.is_overdue }">
                      {{ task.due_date ? (task.is_overdue ? `Venció ${formatDate(task.due_date)}` : `Vence ${formatDate(task.due_date)}`) : "Sin fecha límite" }}
                    </small>
                  </span>
                  <span class="badge" :class="taskPriorityClass(task.priority)">{{ task.priority_label }}</span>
                </button>
              </div>
              <div v-else class="inicio-agenda-empty inicio-agenda-empty--compact">
                <i class="bx bx-task"></i>
                <strong>Sin tareas pendientes</strong>
                <span>Las tareas asignadas aparecerán aquí.</span>
              </div>
            </section>
          </BCol>
        </BRow>

        <BRow v-if="visibleInternalAnnouncements.length" class="g-4 mb-4">
          <BCol cols="12">
            <BCard no-body class="inicio-communications-card border-0">
              <BCardBody class="p-0">
                <div class="inicio-communications-card__header">
                  <div class="inicio-card-heading">
                    <span class="inicio-card-heading__icon inicio-card-heading__icon--communications">
                      <i class="bx bx-envelope"></i>
                    </span>
                    <div>
                      <span class="inicio-section-label">Información interna</span>
                      <h5 class="mb-1">Comunicaciones para ti</h5>
                      <div class="text-muted small">
                        <span>{{ unreadInternalAnnouncements }} sin leer</span>
                        <span v-if="pendingInternalAcknowledgements"> · {{ pendingInternalAcknowledgements }} por confirmar</span>
                      </div>
                    </div>
                  </div>
                  <BButton
                    v-if="dashboard.capabilities?.can_view_internal_communications"
                    size="sm"
                    variant="outline-primary"
                    @click="openRoute('/comunicaciones')"
                  >
                    <i class="bx bx-envelope me-1"></i>
                    Gestionar
                  </BButton>
                </div>

                <div class="inicio-announcements inicio-communications-card__body">
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
                <span class="inicio-section-label">Agenda consolidada</span>
                <h4 class="mb-1">Calendario maestro institucional</h4>
                <p class="mb-0">Reúne las fuentes difundibles y la agenda personal visible para ti.</p>
              </div>
            </div>
            <div class="inicio-legend">
              <span>{{ filteredCalendarEvents.length }} eventos visibles</span>
            </div>
          </div>
          <div v-if="calendarSources.length" class="inicio-calendar-filters" aria-label="Filtrar fuentes del calendario">
            <button
              type="button"
              class="inicio-calendar-filter"
              :class="{ 'inicio-calendar-filter--active': allCalendarSourcesActive }"
              @click="toggleAllCalendarSources"
            >
              <i class="bx bx-layer"></i>
              Todas
            </button>
            <button
              v-for="source in calendarSources"
              :key="source.key"
              type="button"
              class="inicio-calendar-filter"
              :class="{ 'inicio-calendar-filter--active': calendarSourceFilters.includes(source.key) }"
              :style="{ '--source-color': source.color }"
              @click="toggleCalendarSource(source.key)"
            >
              <i :class="['bx', source.icon]"></i>
              {{ source.label }}
              <span>{{ source.count }}</span>
            </button>
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
                    <strong>{{ calendarDay(item.start || item.starts_at) }}</strong>
                    <small>{{ calendarMonth(item.start || item.starts_at) }}</small>
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
                      <strong>{{ calendarDay(item.start || item.starts_at) }}</strong>
                      <small>{{ calendarMonth(item.start || item.starts_at) }}</small>
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
                        <strong>{{ calendarDay(event.start || event.starts_at) }}</strong>
                        <small>{{ calendarMonth(event.start || event.starts_at) }}</small>
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

        <BRow v-if="disseminatedDocuments.length || dashboard.quick_links?.length" class="g-4 mt-4 inicio-information-row">
          <BCol v-if="disseminatedDocuments.length" xl="7">
            <section class="inicio-information-card h-100">
              <header class="inicio-information-card__header">
                <div class="inicio-card-heading">
                  <span class="inicio-card-heading__icon inicio-card-heading__icon--documents"><i class="bx bx-file-blank"></i></span>
                  <div>
                    <h5 class="mb-0">Documentos difundidos</h5>
                    <small>Protocolos y documentación oficial disponible</small>
                  </div>
                </div>
                <BButton size="sm" variant="outline-primary" @click="openRoute('/risk-prevention/document-management')">
                  Ver todos
                </BButton>
              </header>
              <div class="inicio-information-card__body">
                <div class="inicio-document-list">
                  <button
                    v-for="document in disseminatedDocuments"
                    :key="document.id"
                    type="button"
                    class="inicio-document-item"
                    @click="openRoute(document.route)"
                  >
                    <span class="inicio-document-item__icon"><i class="bx bx-file"></i></span>
                    <span class="inicio-document-item__content">
                      <span>{{ document.document_type || "Documento" }} · v{{ document.version_number || "-" }}</span>
                      <strong>{{ document.title }}</strong>
                      <small>
                        {{ document.responsible_name || "Prevención de Riesgos" }}
                        <template v-if="document.valid_until"> · Vigente hasta {{ formatDate(document.valid_until) }}</template>
                      </small>
                    </span>
                    <span class="badge" :class="documentStatusClass(document.status)">
                      {{ document.status === "por_vencer" ? "Por vencer" : "Vigente" }}
                    </span>
                  </button>
                </div>
              </div>
            </section>
          </BCol>

          <BCol :xl="disseminatedDocuments.length ? 5 : 12">
            <section class="inicio-information-card h-100">
              <header class="inicio-information-card__header">
                <div class="inicio-card-heading">
                  <span class="inicio-card-heading__icon inicio-card-heading__icon--links"><i class="bx bx-grid-alt"></i></span>
                  <div>
                    <h5 class="mb-0">Accesos rápidos</h5>
                    <small>Herramientas disponibles según tu perfil</small>
                  </div>
                </div>
              </header>
              <div class="inicio-information-card__body">
                <div class="inicio-quick-links">
                  <button
                    v-for="(link, index) in dashboard.quick_links"
                    :key="link.route"
                    type="button"
                    class="inicio-quick-link"
                    :class="quickLinkToneClass(index)"
                    @click="openRoute(link.route)"
                  >
                    <span><i :class="['bx', quickLinkIcon(link)]"></i></span>
                    <span><strong>{{ link.title }}</strong><small>{{ link.description }}</small></span>
                    <i class="bx bx-chevron-right"></i>
                  </button>
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

.inicio-metric--danger {
  border-top-color: #f46a6a !important;
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
.inicio-card-heading__icon--attention { background: #fff0f0; color: #d84d4d; }
.inicio-card-heading__icon--tasks { background: #fff7e7; color: #b87718; }
.inicio-card-heading__icon--documents { background: #eef1ff; color: #4a5fc7; }
.inicio-card-heading__icon--links { background: #eef7f4; color: #278060; }

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

.inicio-calendar-filters {
  align-items: center;
  border-bottom: 1px solid #edf0f5;
  display: flex;
  flex-wrap: wrap;
  gap: .55rem;
  padding: .8rem 1.25rem;
}

.inicio-calendar-filter {
  --source-color: #556ee6;
  align-items: center;
  background: #fff;
  border: 1px solid #dfe4ec;
  border-radius: 999px;
  color: #606979;
  display: inline-flex;
  font-size: .78rem;
  font-weight: 650;
  gap: .35rem;
  padding: .42rem .7rem;
  transition: border-color .15s ease, box-shadow .15s ease, color .15s ease;
}

.inicio-calendar-filter > i {
  color: var(--source-color);
  font-size: 1rem;
}

.inicio-calendar-filter > span {
  align-items: center;
  background: #f0f2f6;
  border-radius: 999px;
  display: inline-flex;
  font-size: .68rem;
  height: 20px;
  justify-content: center;
  min-width: 20px;
  padding: 0 .3rem;
}

.inicio-calendar-filter:hover,
.inicio-calendar-filter--active {
  border-color: var(--source-color);
  box-shadow: 0 3px 10px color-mix(in srgb, var(--source-color) 16%, transparent);
  color: #2d3442;
}

.inicio-calendar-filter--active {
  background: color-mix(in srgb, var(--source-color) 9%, white);
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
.inicio-timeline-item__icon--task { background: #fff6e5; color: #b87718; }

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
.inicio-event-detail__hero--task { background: linear-gradient(125deg, #a96f17, #d99a32); }
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

.inicio-focus-card {
  background: #fff;
  border: 1px solid #e8ebf2;
  border-radius: 14px;
  box-shadow: 0 6px 24px rgba(35, 44, 72, .06);
  overflow: hidden;
}

.inicio-focus-card__header {
  align-items: center;
  border-bottom: 1px solid #edf0f5;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: 1.1rem 1.2rem;
}

.inicio-focus-total {
  align-items: center;
  background: #fff0f0;
  border-radius: 999px;
  color: #c63e3e;
  display: inline-flex;
  font-size: .8rem;
  font-weight: 800;
  height: 32px;
  justify-content: center;
  min-width: 32px;
  padding: 0 .55rem;
}

.inicio-attention-list,
.inicio-task-list {
  display: grid;
  gap: .55rem;
  padding: 1rem 1.15rem;
}

.inicio-attention-item {
  align-items: center;
  background: #fff;
  border: 1px solid #e8ecf2;
  border-left: 4px solid #9aa3b2;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: .75rem;
  grid-template-columns: 40px minmax(0, 1fr) auto;
  padding: .7rem .8rem;
  text-align: left;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  width: 100%;
}

.inicio-attention-item--critical { border-left-color: #f46a6a; }
.inicio-attention-item--high { border-left-color: #f1b44c; }
.inicio-attention-item--normal { border-left-color: #50a5f1; }

.inicio-attention-item:hover,
.inicio-task-item:hover,
.inicio-document-item:hover,
.inicio-quick-link:hover {
  border-color: #cbd3e2;
  box-shadow: 0 6px 16px rgba(38, 47, 74, .07);
  transform: translateY(-1px);
}

.inicio-attention-item__icon {
  align-items: center;
  background: #f4f6fa;
  border-radius: 9px;
  color: #5c6677;
  display: flex;
  font-size: 1.15rem;
  height: 40px;
  justify-content: center;
  width: 40px;
}

.inicio-attention-item--critical .inicio-attention-item__icon { background: #fff0f0; color: #d84d4d; }
.inicio-attention-item--high .inicio-attention-item__icon { background: #fff7e7; color: #b87718; }

.inicio-attention-item__content {
  display: grid;
  gap: .18rem;
  min-width: 0;
}

.inicio-attention-item__content > strong,
.inicio-task-item__content > strong,
.inicio-document-item__content > strong {
  color: var(--inicio-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.inicio-attention-item__content > small,
.inicio-task-item__content > small,
.inicio-document-item__content > small {
  color: var(--inicio-muted);
  font-size: .75rem;
}

.inicio-attention-item__meta {
  align-items: center;
  color: var(--inicio-muted);
  display: flex;
  font-size: .7rem;
  justify-content: space-between;
  text-transform: uppercase;
}

.inicio-attention-item__meta strong {
  color: #4f5868;
  font-size: .68rem;
}

.inicio-attention-item__action {
  align-items: center;
  color: var(--inicio-primary);
  display: inline-flex;
  font-size: .75rem;
  font-weight: 700;
  gap: .2rem;
}

.inicio-task-item {
  align-items: center;
  background: #fff;
  border: 1px solid #e8ecf2;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: .65rem;
  grid-template-columns: 28px minmax(0, 1fr) auto;
  padding: .7rem;
  text-align: left;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  width: 100%;
}

.inicio-task-item__check {
  color: #9ba5b5;
  font-size: 1.1rem;
  text-align: center;
}

.inicio-task-item__content {
  display: grid;
  gap: .15rem;
  min-width: 0;
}

.inicio-agenda-empty--compact {
  margin: 1rem;
  min-height: 135px;
}

.inicio-document-list,
.inicio-quick-links {
  display: grid;
  gap: .6rem;
}

.inicio-document-item {
  align-items: center;
  background: #fff;
  border: 1px solid #e8ecf2;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: .7rem;
  grid-template-columns: 42px minmax(0, 1fr) auto;
  padding: .75rem;
  text-align: left;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  width: 100%;
}

.inicio-document-item__icon {
  align-items: center;
  background: #eef1ff;
  border-radius: 9px;
  color: #5064c9;
  display: flex;
  font-size: 1.2rem;
  height: 42px;
  justify-content: center;
  width: 42px;
}

.inicio-document-item__content {
  display: grid;
  gap: .16rem;
  min-width: 0;
}

.inicio-document-item__content > span {
  color: #6373c9;
  font-size: .67rem;
  font-weight: 750;
  text-transform: uppercase;
}

.inicio-quick-links {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.inicio-quick-link {
  align-items: center;
  background: #fff;
  border: 1px solid #e8ecf2;
  border-radius: 10px;
  color: inherit;
  display: grid;
  gap: .6rem;
  grid-template-columns: 36px minmax(0, 1fr) auto;
  padding: .7rem;
  text-align: left;
  transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  width: 100%;
}

.inicio-quick-link > span:first-child {
  align-items: center;
  background: #f2f4fa;
  border-radius: 8px;
  color: #586ac6;
  display: flex;
  font-size: 1.05rem;
  height: 36px;
  justify-content: center;
  width: 36px;
}

.inicio-quick-link > span:nth-child(2) {
  display: grid;
  min-width: 0;
}

.inicio-quick-link strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.inicio-quick-link small {
  color: var(--inicio-muted);
  font-size: .7rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.inicio-quick-link > .bx {
  color: #a0a7b5;
  font-size: 1.1rem;
}

@media (max-width: 991.98px) {
  .inicio-calendar-heading { align-items: flex-start; flex-direction: column; gap: 1rem; }
  .inicio-milestones { grid-template-columns: 1fr; }
  .inicio-information-row { gap: 1rem !important; }
  .inicio-quick-links { grid-template-columns: repeat(3, minmax(0, 1fr)); }
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
  .inicio-news-grid,
  .inicio-quick-links {
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
  .inicio-calendar-filters { padding: .75rem; }
  .inicio-legend { gap: .45rem .75rem; }
  .inicio-focus-card__header { align-items: flex-start; }
  .inicio-attention-item { grid-template-columns: 40px minmax(0, 1fr); }
  .inicio-attention-item__action { grid-column: 2; }
  .inicio-document-item { grid-template-columns: 42px minmax(0, 1fr); }
  .inicio-document-item > .badge { grid-column: 2; justify-self: start; }
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

/* Refined institutional home workspace */
.inicio-dashboard {
  --inicio-primary: #5368dc;
  --inicio-primary-soft: #eef1ff;
  --inicio-ink: #202b40;
  --inicio-muted: #748096;
  --inicio-line: #e5eaf3;
  max-width: 1620px;
  margin: 0 auto;
}

.inicio-dashboard :where(h2, h3, h4, h5, h6) {
  color: var(--inicio-ink);
  letter-spacing: -0.025em;
}

.inicio-hero {
  min-height: 250px;
  align-items: center;
  gap: clamp(1.5rem, 4vw, 3.5rem);
  grid-template-columns: minmax(0, 1.45fr) minmax(310px, 0.72fr);
  padding: clamp(1.65rem, 3.3vw, 2.7rem);
  border: 1px solid rgba(126, 144, 221, 0.18);
  border-radius: 1.75rem;
  background:
    radial-gradient(circle at 88% 16%, rgba(109, 188, 255, 0.26), transparent 28%),
    radial-gradient(circle at 56% 105%, rgba(143, 111, 232, 0.16), transparent 34%),
    linear-gradient(132deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 255, 0.96) 58%, rgba(238, 242, 255, 0.95));
  color: var(--inicio-ink);
  box-shadow: 0 22px 48px rgba(50, 67, 112, 0.11);
}

.inicio-hero::before,
.inicio-hero::after {
  position: absolute;
  border: 1px solid rgba(96, 113, 197, 0.11);
  border-radius: 50%;
  background: transparent;
  content: "";
  pointer-events: none;
}

.inicio-hero::before {
  right: 9%;
  bottom: -125px;
  width: 270px;
  height: 270px;
}

.inicio-hero::after {
  top: -155px;
  right: -90px;
  width: 310px;
  height: 310px;
}

.inicio-hero__meta,
.inicio-hero__signals,
.inicio-hero__summary-main,
.inicio-hero__summary-footer {
  display: flex;
  align-items: center;
}

.inicio-hero__meta {
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-bottom: 0.85rem;
}

.inicio-hero__badge,
.inicio-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  min-height: 2rem;
  padding: 0.38rem 0.72rem;
  border-radius: 999px;
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.inicio-hero__badge {
  border: 1px solid rgba(83, 104, 220, 0.14);
  background: rgba(255, 255, 255, 0.78);
  color: #4d63d3;
  box-shadow: 0 8px 18px rgba(60, 78, 142, 0.06);
}

.inicio-eyebrow {
  padding-inline: 0;
  color: #77849b;
}

.inicio-title {
  max-width: 760px;
  margin-top: 0;
  color: #1e2940;
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 820;
  letter-spacing: -0.055em;
  line-height: 1.04;
}

.inicio-hero__subtitle {
  max-width: 650px;
  margin-top: 0.72rem;
  color: #66748a;
  font-size: 1rem;
  line-height: 1.55;
}

.inicio-hero__signals {
  flex-wrap: wrap;
  gap: 0.48rem;
  margin-top: 1.25rem;
}

.inicio-hero__signals span {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  min-height: 2rem;
  padding: 0.4rem 0.68rem;
  border: 1px solid rgba(112, 128, 181, 0.13);
  border-radius: 0.7rem;
  background: rgba(255, 255, 255, 0.66);
  color: #657188;
  font-size: 0.69rem;
  font-weight: 700;
}

.inicio-hero__signals .inicio-hero__signal--alert {
  border-color: rgba(220, 91, 102, 0.15);
  background: rgba(255, 241, 243, 0.82);
  color: #b34e5a;
}

.inicio-hero__summary {
  align-self: stretch;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 1.15rem;
  padding: 1.3rem;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 1.25rem;
  background:
    radial-gradient(circle at 85% 8%, rgba(134, 170, 255, 0.3), transparent 34%),
    linear-gradient(145deg, #344bb8, #596fda 68%, #687de1);
  color: #fff;
  box-shadow: 0 18px 36px rgba(52, 72, 181, 0.2);
}

.inicio-hero__summary-main {
  align-items: flex-start;
  gap: 0.85rem;
}

.inicio-hero__summary-icon {
  flex: 0 0 3rem;
  width: 3rem;
  height: 3rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 0.9rem;
  background: rgba(255, 255, 255, 0.13);
  font-size: 1.45rem;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.inicio-hero__summary strong {
  margin-top: 0.2rem;
  color: #fff;
  font-size: 1.3rem;
  letter-spacing: -0.03em;
}

.inicio-hero__summary span:not(.inicio-hero__summary-label) {
  margin-top: 0.2rem;
  color: rgba(255, 255, 255, 0.76);
  font-size: 0.76rem;
}

.inicio-hero__summary-label {
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.62rem;
  letter-spacing: 0.12em;
}

.inicio-hero__summary-footer {
  justify-content: space-between;
  gap: 0.75rem;
  padding-top: 0.9rem;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.inicio-hero__summary-footer small {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: rgba(255, 255, 255, 0.65);
  font-size: 0.64rem;
}

.inicio-refresh {
  width: auto;
  height: 2.15rem;
  gap: 0.35rem;
  padding: 0 0.72rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 0.7rem;
  background: rgba(255, 255, 255, 0.12);
  font-size: 0.7rem;
  font-weight: 750;
}

.inicio-refresh i {
  font-size: 1rem;
}

.inicio-refresh:hover {
  background: rgba(255, 255, 255, 0.2);
}

.inicio-metric {
  --metric-tone: #6d7ee2;
  --metric-soft: #eef1ff;
  min-height: 158px;
  overflow: hidden;
  border: 1px solid rgba(225, 231, 241, 0.92) !important;
  border-radius: 1.15rem;
  background: linear-gradient(145deg, #fff 58%, var(--metric-soft));
  box-shadow: 0 10px 28px rgba(39, 54, 91, 0.065);
}

.inicio-metric::before {
  position: absolute;
  top: 0;
  right: 1rem;
  left: 1rem;
  height: 0.2rem;
  border-radius: 0 0 999px 999px;
  background: var(--metric-tone);
  content: "";
}

.inicio-metric :deep(.card-body) {
  position: relative;
  padding: 1.18rem;
}

.inicio-metric--primary { --metric-tone: #596fda; --metric-soft: #eef1ff; }
.inicio-metric--warning { --metric-tone: #d99a3c; --metric-soft: #fff7e9; }
.inicio-metric--danger { --metric-tone: #dd6973; --metric-soft: #fff0f2; }
.inicio-metric--success { --metric-tone: #38a07b; --metric-soft: #ebf8f3; }
.inicio-metric--info { --metric-tone: #488fc2; --metric-soft: #ebf6fc; }

.inicio-metric__icon {
  width: 2.7rem;
  height: 2.7rem;
  border-radius: 0.85rem;
  background: var(--metric-soft);
  color: var(--metric-tone);
}

.inicio-metric__value {
  color: var(--inicio-ink);
  font-size: 2rem;
  font-weight: 820;
  letter-spacing: -0.055em;
}

.inicio-metric__label {
  margin-top: 0.8rem;
  color: #354156;
  font-size: 0.84rem;
}

.inicio-metric__detail {
  color: #7a8699;
  font-size: 0.72rem;
  line-height: 1.4;
}

.inicio-section-intro {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin: 0.2rem 0 1rem;
  padding-inline: 0.15rem;
}

.inicio-section-intro h2 {
  margin: 0.12rem 0 0;
  font-size: 1.35rem;
}

.inicio-section-intro p {
  margin: 0.22rem 0 0;
  color: var(--inicio-muted);
  font-size: 0.76rem;
}

.inicio-section-intro__status {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.42rem 0.68rem;
  border: 1px solid rgba(65, 151, 119, 0.14);
  border-radius: 999px;
  background: #edf8f4;
  color: #358565;
  font-size: 0.66rem;
  font-weight: 750;
}

.inicio-focus-card,
.inicio-information-card,
.inicio-calendar-section,
.inicio-agenda-card,
.inicio-communications-card {
  border: 1px solid var(--inicio-line);
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 12px 32px rgba(37, 52, 88, 0.065);
}

.inicio-focus-card__header,
.inicio-information-card__header,
.inicio-calendar-heading {
  padding: 1.15rem 1.25rem;
  border-bottom-color: #edf1f6;
  background: linear-gradient(120deg, rgba(251, 252, 255, 0.96), rgba(255, 255, 255, 0.96));
}

.inicio-card-heading__icon {
  width: 2.75rem;
  height: 2.75rem;
  flex-basis: 2.75rem;
  border-radius: 0.85rem;
  box-shadow: inset 0 0 0 1px rgba(80, 100, 190, 0.05);
}

.inicio-card-heading__icon--communications {
  background: #edf1ff;
  color: #5368d4;
}

.inicio-communications-card {
  overflow: hidden;
}

.inicio-communications-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid #edf1f6;
  background:
    radial-gradient(circle at 92% 0, rgba(114, 138, 231, 0.1), transparent 30%),
    linear-gradient(120deg, #fbfcff, #fff);
}

.inicio-communications-card__body {
  padding: 1rem 1.15rem 1.15rem;
}

.inicio-announcement {
  position: relative;
  padding: 1rem;
  padding-left: 1.2rem;
  border: 1px solid #e7ebf3;
  border-left: 0;
  border-radius: 0.95rem;
  background: linear-gradient(145deg, #fff, #fbfcff);
  box-shadow: 0 8px 20px rgba(38, 52, 89, 0.045);
}

.inicio-announcement::before {
  position: absolute;
  top: 0.85rem;
  bottom: 0.85rem;
  left: 0;
  width: 0.22rem;
  border-radius: 999px;
  background: #5d73dc;
  content: "";
}

.inicio-announcement--urgent::before { background: #dc6570; }
.inicio-announcement--important::before { background: #d9a041; }

.inicio-calendar-section {
  overflow: hidden;
}

.inicio-calendar-heading {
  border-radius: 1.25rem 1.25rem 0 0;
}

.inicio-calendar-wrap {
  padding: 1rem 1.2rem 1.2rem;
}

.inicio-calendar-filters {
  gap: 0.45rem;
  padding: 0.75rem 1.25rem;
  background: #fbfcfe;
}

.inicio-calendar-filter {
  min-height: 2rem;
  border-color: #e0e6ef;
  background: #fff;
  font-size: 0.7rem;
}

:deep(.fc) {
  --fc-border-color: #e8ecf3;
  --fc-button-bg-color: #f6f8fc;
  --fc-button-border-color: #e2e7f0;
  --fc-button-text-color: #606c82;
  --fc-button-active-bg-color: #596fda;
  --fc-button-active-border-color: #596fda;
}

:deep(.fc .fc-button) {
  border-radius: 0.65rem;
  font-size: 0.72rem;
  font-weight: 750;
}

:deep(.fc .fc-button-primary:not(:disabled):not(.fc-button-active)) {
  border-color: #dce3f0 !important;
  background: #f3f5fc !important;
  color: #59667d !important;
}

:deep(.fc .fc-button-primary:not(:disabled):not(.fc-button-active):hover) {
  border-color: #cbd4ec !important;
  background: #e9edfb !important;
  color: #354bb9 !important;
}

:deep(.fc .fc-button-primary:disabled) {
  border-color: #e1e6ef !important;
  background: #f6f7fa !important;
  color: #99a3b4 !important;
  opacity: 0.82;
}

:deep(.fc .fc-prev-button),
:deep(.fc .fc-next-button) {
  border-color: #dce3f0 !important;
  background: #f3f5fc !important;
  color: #465cc9 !important;
}

:deep(.fc .fc-prev-button:hover),
:deep(.fc .fc-next-button:hover) {
  border-color: #cbd4ec !important;
  background: #e9edfb !important;
  color: #354bb9 !important;
}

:deep(.fc .fc-prev-button .fc-icon),
:deep(.fc .fc-next-button .fc-icon) {
  color: inherit !important;
}

:deep(.fc .fc-button-group > .fc-button:not(:first-child)) {
  margin-left: 0.2rem;
  border-left-width: 1px;
  border-radius: 0.65rem;
}

:deep(.fc .fc-button-group > .fc-button:first-child) {
  border-radius: 0.65rem;
}

:deep(.fc .fc-daygrid-day-frame) {
  min-height: 68px;
}

.inicio-agenda-card {
  padding: 1.15rem;
}

.inicio-agenda-empty,
.inicio-panel-empty {
  border-color: #dfe5ef;
  border-radius: 0.95rem;
  background: linear-gradient(145deg, #fafbfe, #f6f8fc);
}

.inicio-information-card__body {
  padding: 1.05rem 1.15rem 1.15rem;
}

.inicio-news-item,
.inicio-public-event,
.inicio-compact-item,
.inicio-task-item,
.inicio-attention-item,
.inicio-document-item {
  border-radius: 0.85rem;
  border-color: #e5eaf2;
}

.inicio-quick-links {
  grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
  gap: 0.7rem;
}

.inicio-quick-link {
  --quick-tone: #5c70d7;
  --quick-soft: #eef1ff;
  position: relative;
  min-height: 98px;
  align-content: center;
  gap: 0.7rem;
  grid-template-columns: 2.6rem minmax(0, 1fr);
  padding: 0.85rem;
  border-color: #e4e9f1;
  border-radius: 0.95rem;
  background: linear-gradient(145deg, #fff 64%, var(--quick-soft));
}

.inicio-quick-link--tone-2 { --quick-tone: #2c8b72; --quick-soft: #ebf8f3; }
.inicio-quick-link--tone-3 { --quick-tone: #b57b2f; --quick-soft: #fff7e8; }
.inicio-quick-link--tone-4 { --quick-tone: #4b8ebd; --quick-soft: #eaf6fc; }
.inicio-quick-link--tone-5 { --quick-tone: #9a627f; --quick-soft: #fbf0f6; }

.inicio-quick-link > span:first-child {
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 0.78rem;
  background: var(--quick-soft);
  color: var(--quick-tone);
  font-size: 1.2rem;
}

.inicio-quick-link > span:nth-child(2) {
  gap: 0.12rem;
  padding-right: 0.6rem;
}

.inicio-quick-link strong {
  color: #344157;
  font-size: 0.78rem;
}

.inicio-quick-link small {
  color: #7b8799;
  font-size: 0.66rem;
  line-height: 1.35;
}

.inicio-quick-link > .bx {
  position: absolute;
  top: 0.7rem;
  right: 0.65rem;
  color: color-mix(in srgb, var(--quick-tone) 62%, #a9b2c1);
}

@media (max-width: 767.98px) {
  .inicio-hero {
    min-height: 0;
    gap: 1.25rem;
    grid-template-columns: 1fr;
    padding: 1.25rem;
    border-radius: 1.3rem;
  }

  .inicio-hero__meta {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.3rem;
  }

  .inicio-hero__badge,
  .inicio-eyebrow {
    min-height: 1.75rem;
    font-size: 0.57rem;
  }

  .inicio-title {
    font-size: clamp(1.75rem, 9vw, 2.15rem);
    line-height: 1.08;
  }

  .inicio-hero__subtitle {
    font-size: 0.84rem;
  }

  .inicio-hero__signals {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 1rem;
  }

  .inicio-hero__signals span:last-child {
    grid-column: 1 / -1;
  }

  .inicio-hero__summary {
    padding: 1.05rem;
    border-radius: 1rem;
  }

  .inicio-hero__summary-footer {
    align-items: flex-end;
  }

  .inicio-hero__summary-footer small {
    max-width: 55%;
  }

  .inicio-metrics-grid {
    --bs-gutter-x: 0.75rem;
    --bs-gutter-y: 0.75rem;
  }

  .inicio-metric {
    min-height: 148px;
    border-radius: 1rem;
  }

  .inicio-metric :deep(.card-body) {
    padding: 1rem 0.85rem;
  }

  .inicio-metric__icon {
    width: 2.25rem;
    height: 2.25rem;
  }

  .inicio-metric__value {
    font-size: 1.55rem;
  }

  .inicio-metric__label {
    margin-top: 0.68rem;
    font-size: 0.74rem;
    line-height: 1.25;
  }

  .inicio-metric__detail {
    font-size: 0.64rem;
  }

  .inicio-section-intro {
    align-items: flex-start;
    flex-direction: column;
  }

  .inicio-section-intro__status {
    align-self: flex-start;
  }

  .inicio-focus-card,
  .inicio-information-card,
  .inicio-calendar-section,
  .inicio-agenda-card,
  .inicio-communications-card {
    border-radius: 1.05rem;
  }

  .inicio-focus-card__header,
  .inicio-information-card__header,
  .inicio-calendar-heading,
  .inicio-communications-card__header {
    padding: 1rem;
  }

  .inicio-communications-card__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .inicio-communications-card__body {
    padding: 0.85rem;
  }

  .inicio-calendar-wrap {
    padding: 0.8rem;
  }

  :deep(.fc .fc-toolbar-title) {
    font-size: 1rem;
  }

  :deep(.fc .fc-list-empty) {
    background: #f8f9fc;
  }

  .inicio-quick-links {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .inicio-quick-link {
    min-height: 125px;
    align-content: start;
    grid-template-columns: 1fr;
  }

  .inicio-quick-link > span:nth-child(2) {
    padding-right: 0;
  }

  .inicio-quick-link strong,
  .inicio-quick-link small {
    overflow: visible;
    text-overflow: clip;
    white-space: normal;
  }
}

.inicio-weather {
  overflow: hidden;
  border: 1px solid #dfe8f2;
  border-radius: 1.25rem;
  background:
    radial-gradient(circle at 95% 0, rgba(86, 169, 215, 0.13), transparent 28%),
    linear-gradient(145deg, #fafdff 0%, #f4f9fd 100%);
  box-shadow: 0 12px 30px rgba(35, 75, 102, 0.07);
}

.inicio-weather__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.1rem 1.25rem;
  border-bottom: 1px solid rgba(205, 221, 233, 0.75);
}

.inicio-weather__heading {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.inicio-weather__heading-icon {
  display: inline-flex;
  flex: 0 0 2.8rem;
  align-items: center;
  justify-content: center;
  width: 2.8rem;
  height: 2.8rem;
  border-radius: 0.9rem;
  background: linear-gradient(145deg, #dff2fb, #eaf6fb);
  color: #2e83ad;
  font-size: 1.35rem;
}

.inicio-weather__heading h2 {
  margin: 0.12rem 0 0;
  font-size: 1.18rem;
}

.inicio-weather__heading p {
  margin: 0.15rem 0 0;
  color: #718095;
  font-size: 0.74rem;
}

.inicio-weather__source {
  display: flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 0.25rem;
  color: #758399;
  font-size: 0.68rem;
}

.inicio-weather__source span,
.inicio-weather__source a {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.inicio-weather__source a {
  color: #397ea3;
  font-weight: 700;
}

.inicio-weather__days {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 0.7rem;
  padding: 1rem 1.15rem 1.15rem;
}

.inicio-weather-day {
  display: flex;
  min-width: 0;
  align-items: center;
  flex-direction: column;
  padding: 0.9rem 0.65rem 0.75rem;
  border: 1px solid rgba(211, 224, 234, 0.88);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.8);
  text-align: center;
}

.inicio-weather-day--today {
  border-color: rgba(65, 142, 184, 0.32);
  background: linear-gradient(155deg, #eaf7fd, #fff 68%);
  box-shadow: 0 8px 18px rgba(46, 122, 160, 0.09);
}

.inicio-weather-day__date {
  display: grid;
  gap: 0.08rem;
}

.inicio-weather-day__date strong {
  color: #30465a;
  font-size: 0.78rem;
}

.inicio-weather-day__date span {
  color: #8793a3;
  font-size: 0.66rem;
}

.inicio-weather-day__icon {
  width: 3.4rem;
  height: 3.4rem;
  margin: 0.25rem 0 0.05rem;
  object-fit: contain;
}

.inicio-weather-day__icon--fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #4b93b7;
  font-size: 2rem;
}

.inicio-weather-day__condition {
  min-height: 2.1em;
  margin: 0;
  color: #5f6e80;
  font-size: 0.67rem;
  line-height: 1.15;
}

.inicio-weather-day__temperature {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
  margin-top: 0.38rem;
}

.inicio-weather-day__temperature strong {
  color: #263c50;
  font-size: 1.2rem;
  font-weight: 800;
}

.inicio-weather-day__temperature span {
  color: #8a95a4;
  font-size: 0.82rem;
}

.inicio-weather-day__details {
  display: grid;
  width: 100%;
  gap: 0.23rem;
  margin-top: 0.55rem;
  padding-top: 0.55rem;
  border-top: 1px solid #e8eef3;
  color: #708092;
  font-size: 0.62rem;
}

.inicio-weather-day__details span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.2rem;
}

.inicio-weather-day__details i {
  color: #3c8fb7;
}

.inicio-weather__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  min-height: 125px;
  padding: 1.2rem;
  color: #748296;
}

.inicio-weather__empty > i {
  color: #4b93b7;
  font-size: 2rem;
}

.inicio-weather__empty strong,
.inicio-weather__empty span {
  display: block;
}

.inicio-weather__empty strong {
  color: #34495c;
}

.inicio-weather__empty span {
  margin-top: 0.15rem;
  font-size: 0.75rem;
}

@media (max-width: 1199.98px) {
  .inicio-weather__days {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 767.98px) {
  .inicio-weather {
    border-radius: 1.05rem;
  }

  .inicio-weather__header {
    align-items: flex-start;
    flex-direction: column;
    padding: 1rem;
  }

  .inicio-weather__source {
    align-items: flex-start;
  }

  .inicio-weather__days {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 0.8rem;
  }

  .inicio-weather-day:last-child:nth-child(odd) {
    grid-column: 1 / -1;
  }
}
</style>
