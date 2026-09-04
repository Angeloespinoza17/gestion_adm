<script>
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import orientationApi from "../../services/orientation-api";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import interactionPlugin from "@fullcalendar/interaction";
import esLocale from "@fullcalendar/core/locales/es";

const currentYear = new Date().getFullYear();
const addOneDay = (value) => {
  const date = new Date(`${value}T12:00:00`);
  date.setDate(date.getDate() + 1);
  const pad = (part) => String(part).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};
const emptyForm = (year) => ({
  id: null,
  orientation_action_id: "",
  level_group: "primary",
  title: "",
  description: "",
  category: "activity",
  start_date: `${year}-03-02`,
  end_date: `${year}-03-06`,
  status: "planned",
});

export default {
  components: { Layout, FullCalendar },
  data() {
    return {
      loading: false,
      saving: false,
      importing: false,
      error: "",
      selectedYear: currentYear,
      availableYears: [],
      plan: null,
      layers: [],
      categories: [],
      entries: [],
      actions: [],
      capabilities: {},
      summary: {},
      isReferencePreview: false,
      referenceAvailable: false,
      referenceNote: "",
      activeLayers: [],
      selectedMonth: "all",
      selectedCategory: "all",
      associationFilter: "all",
      search: "",
      viewMode: "calendar",
      formOpen: false,
      entryForm: emptyForm(currentYear),
    };
  },
  computed: {
    yearOptions() {
      const years = new Set(this.availableYears || []);
      for (let year = currentYear - 3; year <= currentYear + 4; year += 1) years.add(year);
      return [...years].sort((a, b) => b - a);
    },
    allLayersActive() {
      return this.layers.length > 0 && this.activeLayers.length === this.layers.length;
    },
    months() {
      return Array.from({ length: 10 }, (_, index) => {
        const month = index + 3;
        return {
          value: String(month).padStart(2, "0"),
          label: new Intl.DateTimeFormat("es-CL", { month: "long" }).format(new Date(2026, month - 1, 1)),
        };
      });
    },
    filteredEntries() {
      const query = this.search.trim().toLocaleLowerCase("es");
      return this.entries
        .filter((entry) => this.activeLayers.includes(entry.level_group))
        .filter((entry) => this.selectedMonth === "all" || entry.start_date?.slice(5, 7) === this.selectedMonth)
        .filter((entry) => this.selectedCategory === "all" || entry.category === this.selectedCategory)
        .filter((entry) => this.matchesAssociationFilter(entry))
        .filter((entry) => !query || [entry.title, entry.description, entry.action?.title]
          .filter(Boolean)
          .some((value) => String(value).toLocaleLowerCase("es").includes(query)))
        .sort((a, b) => {
          const dateOrder = String(a.start_date).localeCompare(String(b.start_date));
          if (dateOrder !== 0) return dateOrder;
          return this.layerIndex(a.level_group) - this.layerIndex(b.level_group);
        });
    },
    calendarEntries() {
      const query = this.search.trim().toLocaleLowerCase("es");
      return this.entries
        .filter((entry) => this.activeLayers.includes(entry.level_group))
        .filter((entry) => this.selectedCategory === "all" || entry.category === this.selectedCategory)
        .filter((entry) => this.matchesAssociationFilter(entry))
        .filter((entry) => !query || [entry.title, entry.description, entry.action?.title]
          .filter(Boolean)
          .some((value) => String(value).toLocaleLowerCase("es").includes(query)));
    },
    calendarEvents() {
      return this.calendarEntries.map((entry) => {
        const layerItem = this.layer(entry.level_group);
        return {
          id: String(entry.id || entry.source_key),
          title: entry.title,
          start: entry.start_date,
          end: addOneDay(entry.end_date),
          allDay: true,
          backgroundColor: layerItem.color,
          borderColor: layerItem.color,
          textColor: "#ffffff",
          extendedProps: { entry },
        };
      });
    },
    calendarOptions() {
      return {
        plugins: [dayGridPlugin, interactionPlugin],
        locales: [esLocale],
        locale: "es",
        initialDate: `${this.selectedYear}-03-01`,
        initialView: "dayGridMonth",
        firstDay: 1,
        fixedWeekCount: false,
        height: "auto",
        dayMaxEvents: 5,
        moreLinkText: (count) => `+${count} más`,
        headerToolbar: {
          left: "prev,next today",
          center: "title",
          right: "dayGridMonth",
        },
        buttonText: { today: "Hoy", month: "Mes" },
        events: this.calendarEvents,
        eventClick: this.openCalendarEntry,
        eventDidMount: ({ el, event }) => {
          const layerItem = this.layer(event.extendedProps.entry.level_group);
          el.setAttribute("title", `${event.title} · ${layerItem.short_label}`);
        },
      };
    },
    weeks() {
      const groups = new Map();
      this.filteredEntries.forEach((entry) => {
        if (!groups.has(entry.start_date)) groups.set(entry.start_date, []);
        groups.get(entry.start_date).push(entry);
      });
      return [...groups.entries()].map(([date, entries], index) => ({
        date,
        entries,
        number: index + 1,
      }));
    },
    visibleLayerCount() {
      const visibleEntries = this.viewMode === "calendar" ? this.calendarEntries : this.filteredEntries;
      return new Set(visibleEntries.map((entry) => entry.level_group)).size;
    },
    visibleEntryCount() {
      return this.viewMode === "calendar" ? this.calendarEntries.length : this.filteredEntries.length;
    },
    linkedEntriesCount() {
      return this.entries.filter((entry) => entry.orientation_action_id).length;
    },
    unlinkedEntriesCount() {
      return this.entries.length - this.linkedEntriesCount;
    },
  },
  watch: {
    selectedYear() {
      this.loadCalendarization();
    },
  },
  mounted() {
    this.loadCalendarization();
  },
  methods: {
    async loadCalendarization() {
      this.loading = true;
      this.error = "";
      try {
        const { data } = await orientationApi.calendarization(this.selectedYear);
        this.availableYears = data.available_years || [];
        this.plan = data.plan || null;
        this.layers = data.layers || [];
        this.categories = data.categories || [];
        this.entries = data.entries || [];
        this.actions = data.actions || [];
        this.capabilities = data.capabilities || {};
        this.summary = data.summary || {};
        this.isReferencePreview = Boolean(data.is_reference_preview);
        this.referenceAvailable = Boolean(data.reference_available);
        this.referenceNote = data.reference_note || "";
        const validActive = this.activeLayers.filter((key) => this.layers.some((layer) => layer.key === key));
        this.activeLayers = validActive.length ? validActive : this.layers.map((layer) => layer.key);
      } catch (error) {
        this.error = this.errorMessage(error, "No fue posible cargar la calendarización.");
      } finally {
        this.loading = false;
      }
    },
    toggleAllLayers() {
      this.activeLayers = this.allLayersActive ? [this.layers[0]?.key].filter(Boolean) : this.layers.map((layer) => layer.key);
    },
    toggleLayer(key) {
      if (this.activeLayers.includes(key)) {
        if (this.activeLayers.length === 1) return;
        this.activeLayers = this.activeLayers.filter((item) => item !== key);
      } else {
        this.activeLayers = [...this.activeLayers, key];
      }
    },
    layer(key) {
      return this.layers.find((item) => item.key === key) || {
        label: key,
        short_label: key,
        color: "#64748b",
        soft_color: "#f1f5f9",
        icon: "mdi-layers-outline",
      };
    },
    layerIndex(key) {
      return Math.max(0, this.layers.findIndex((layer) => layer.key === key));
    },
    layerCount(key) {
      return this.entries.filter((entry) => entry.level_group === key).length;
    },
    matchesAssociationFilter(entry) {
      if (this.associationFilter === "linked") return Boolean(entry.orientation_action_id);
      if (this.associationFilter === "unlinked") return !entry.orientation_action_id;
      return true;
    },
    categoryLabel(key) {
      return this.categories.find((item) => item.key === key)?.label || "Actividad formativa";
    },
    statusLabel(status) {
      return {
        planned: "Planificada",
        confirmed: "Confirmada",
        completed: "Realizada",
        cancelled: "Cancelada",
      }[status] || status;
    },
    dateLabel(value, options = { day: "2-digit", month: "short" }) {
      if (!value) return "—";
      return new Intl.DateTimeFormat("es-CL", options).format(new Date(`${value}T12:00:00`));
    },
    weekRange(entry) {
      return `${this.dateLabel(entry.start_date)} — ${this.dateLabel(entry.end_date, { day: "2-digit", month: "short", year: "numeric" })}`;
    },
    setViewMode(mode) {
      this.viewMode = mode;
      if (mode === "calendar") this.selectedMonth = "all";
    },
    reviewUnlinkedEntries() {
      this.associationFilter = "unlinked";
      this.selectedMonth = "all";
      this.viewMode = "timeline";
      this.$nextTick(() => document.querySelector(".calendarization-workspace")?.scrollIntoView({ behavior: "smooth", block: "start" }));
    },
    openCalendarEntry({ event }) {
      const entry = event.extendedProps.entry;
      if (entry?.id && this.capabilities.can_manage_plan) {
        this.openEdit(entry);
        return;
      }

      Swal.fire({
        icon: entry?.orientation_action_id ? "info" : "question",
        title: entry?.title || "Actividad calendarizada",
        text: entry?.action
          ? `Vinculada a la acción: ${entry.action.title}. El vínculo no modifica por sí solo el avance.`
          : "Esta actividad todavía no está vinculada a una acción del plan y no modifica su avance.",
        confirmButtonColor: "#5b4bc4",
      });
    },
    openCreate(entry = null) {
      if (!this.plan) return;
      this.entryForm = emptyForm(this.selectedYear);
      if (entry) {
        this.entryForm.start_date = entry.start_date;
        this.entryForm.end_date = entry.end_date;
        this.entryForm.level_group = entry.level_group;
      }
      this.formOpen = true;
    },
    openEdit(entry) {
      if (!entry.id) return;
      this.entryForm = {
        id: entry.id,
        orientation_action_id: entry.orientation_action_id || "",
        level_group: entry.level_group,
        title: entry.title,
        description: entry.description || "",
        category: entry.category,
        start_date: entry.start_date,
        end_date: entry.end_date,
        status: entry.status,
      };
      this.formOpen = true;
    },
    closeForm() {
      if (!this.saving) this.formOpen = false;
    },
    async saveEntry() {
      if (!this.plan) return;
      this.saving = true;
      try {
        const payload = {
          ...this.entryForm,
          orientation_action_id: this.entryForm.orientation_action_id || null,
        };
        delete payload.id;
        if (this.entryForm.id) {
          await orientationApi.updateCalendarizationEntry(this.entryForm.id, payload);
        } else {
          await orientationApi.createCalendarizationEntry(this.plan.id, payload);
        }
        this.formOpen = false;
        await this.loadCalendarization();
        Swal.fire({ icon: "success", title: "Calendarización actualizada", timer: 1700, showConfirmButton: false });
      } catch (error) {
        Swal.fire("No fue posible guardar", this.errorMessage(error, "Revise la información ingresada."), "error");
      } finally {
        this.saving = false;
      }
    },
    async importReference() {
      if (!this.plan) return;
      const confirmation = await Swal.fire({
        icon: "question",
        title: "¿Guardar la calendarización 2026?",
        html: "<p>Se guardarán las 164 actividades dentro de la calendarización anual.</p><p><strong>No se asignarán automáticamente a ninguna acción y no aumentarán el avance.</strong></p><p>Después podrás abrir cada actividad y elegir individualmente la acción correcta.</p>",
        showCancelButton: true,
        confirmButtonText: "Guardar 164 actividades",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#5b4bc4",
      });
      if (!confirmation.isConfirmed) return;
      this.importing = true;
      try {
        const { data } = await orientationApi.importCalendarizationReference(this.plan.id);
        await this.loadCalendarization();
        Swal.fire({
          icon: "success",
          title: "Calendarización guardada",
          text: `${data.message} Abre cada evento para vincularlo a la acción que corresponda.`,
          confirmButtonColor: "#5b4bc4",
        });
      } catch (error) {
        Swal.fire("No fue posible incorporar", this.errorMessage(error, "Intente nuevamente."), "error");
      } finally {
        this.importing = false;
      }
    },
    async deleteEntry(entry) {
      const confirmation = await Swal.fire({
        icon: "warning",
        title: "¿Retirar esta actividad?",
        text: entry.title,
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d1435b",
      });
      if (!confirmation.isConfirmed) return;
      try {
        await orientationApi.deleteCalendarizationEntry(entry.id);
        await this.loadCalendarization();
      } catch (error) {
        Swal.fire("No fue posible eliminar", this.errorMessage(error, "Intente nuevamente."), "error");
      }
    },
    errorMessage(error, fallback) {
      const errors = error?.response?.data?.errors;
      if (errors) return Object.values(errors).flat().join(" ");
      return error?.response?.data?.message || fallback;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="calendarization-page">
      <section class="calendarization-hero">
        <div class="calendarization-hero__copy">
          <div class="calendarization-eyebrow"><i class="mdi mdi-layers-triple-outline"></i> Orientación · planificación curricular</div>
          <h1>Calendarización por niveles</h1>
          <p>Visualiza las actividades semanales por capas, compara los niveles en una sola línea anual y vincula cada hito con el plan de orientación.</p>
          <div class="calendarization-hero__meta">
            <span><i class="mdi mdi-calendar-blank-outline"></i> {{ selectedYear }}</span>
            <span><i class="mdi mdi-view-grid-plus-outline"></i> {{ visibleLayerCount }} capas visibles</span>
            <span><i class="mdi mdi-calendar-check-outline"></i> {{ visibleEntryCount }} actividades</span>
          </div>
        </div>
        <div class="calendarization-hero__actions">
          <label for="calendarization-year">Año del plan</label>
          <select id="calendarization-year" v-model.number="selectedYear" class="calendarization-year-select">
            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
          </select>
          <button v-if="capabilities.can_manage_plan && plan && !isReferencePreview" type="button" class="calendarization-primary-btn" @click="openCreate()">
            <i class="mdi mdi-calendar-plus"></i> Nueva actividad
          </button>
        </div>
      </section>

      <div v-if="error" class="calendarization-alert calendarization-alert--danger">
        <i class="mdi mdi-alert-circle-outline"></i><span>{{ error }}</span>
      </div>

      <section v-if="isReferencePreview" class="calendarization-reference">
        <div class="calendarization-reference__icon"><i class="mdi mdi-file-document-multiple-outline"></i></div>
        <div>
          <span class="calendarization-reference__label">Vista previa documental</span>
          <h2>La calendarización 2026 está lista para guardarse</h2>
          <p>Se organizaron 164 actividades desde los documentos adjuntos. Se guardarán en el año 2026, inicialmente sin acción asociada y sin afectar el avance.</p>
          <small><i class="mdi mdi-information-outline"></i> {{ referenceNote }}</small>
        </div>
        <div class="calendarization-reference__action">
          <button v-if="plan && capabilities.can_manage_plan" type="button" class="calendarization-primary-btn" :disabled="importing" @click="importReference">
            <i :class="importing ? 'mdi mdi-loading mdi-spin' : 'mdi mdi-database-import-outline'"></i>
            {{ importing ? "Guardando…" : "Guardar calendarización 2026" }}
          </button>
          <router-link v-else-if="!plan" to="/orientation/plan-anual" class="calendarization-primary-btn">
            <i class="mdi mdi-file-plus-outline"></i> Crear plan {{ selectedYear }}
          </router-link>
          <span v-else class="calendarization-readonly"><i class="mdi mdi-eye-outline"></i> Acceso de lectura</span>
        </div>
      </section>

      <section v-if="!isReferencePreview && entries.length" class="calendarization-loaded-status" aria-live="polite">
        <div class="calendarization-loaded-status__icon"><i class="mdi mdi-check-decagram-outline"></i></div>
        <div class="calendarization-loaded-status__copy">
          <span class="calendarization-reference__label">Carga verificada</span>
          <h2>Actividades cargadas correctamente</h2>
          <p>La calendarización {{ selectedYear }} está guardada. Ahora puedes revisar cada actividad y asociarla a la acción del plan que corresponda.</p>
        </div>
        <div class="calendarization-loaded-status__metrics" aria-label="Resumen de asociaciones">
          <div><strong>{{ entries.length }}</strong><span>guardadas</span></div>
          <div class="pending"><strong>{{ unlinkedEntriesCount }}</strong><span>sin acción</span></div>
          <div class="linked"><strong>{{ linkedEntriesCount }}</strong><span>vinculadas</span></div>
        </div>
        <button
          type="button"
          class="calendarization-review-btn"
          :disabled="unlinkedEntriesCount === 0"
          @click="reviewUnlinkedEntries"
        >
          <i class="mdi mdi-link-variant-plus"></i>
          {{ unlinkedEntriesCount ? `Revisar ${unlinkedEntriesCount} sin asociar` : "Todas están asociadas" }}
        </button>
      </section>

      <section class="calendarization-association-guide">
        <div class="calendarization-association-guide__title">
          <span class="calendarization-kicker">¿Cómo se relaciona con el plan?</span>
          <h2>Guardar, vincular y ejecutar son pasos distintos</h2>
          <p>Así evitamos que una semana quede sumada a una acción incorrecta.</p>
        </div>
        <div class="calendarization-association-step">
          <span>1</span>
          <div><strong>Guardar calendarización</strong><small>Crea la agenda anual por niveles. No selecciona ninguna acción.</small></div>
        </div>
        <i class="mdi mdi-chevron-right calendarization-association-arrow"></i>
        <div class="calendarization-association-step">
          <span>2</span>
          <div><strong>Vincular una acción</strong><small>Abre cada evento y elige la acción del plan que realmente corresponde.</small></div>
        </div>
        <i class="mdi mdi-chevron-right calendarization-association-arrow"></i>
        <div class="calendarization-association-step">
          <span>3</span>
          <div><strong>Registrar ejecución</strong><small>Solo una actividad ejecutada desde la acción, con aporte definido, aumenta su avance.</small></div>
        </div>
      </section>

      <section class="calendarization-layer-panel">
        <div class="calendarization-section-heading">
          <div>
            <span class="calendarization-kicker">Capas curriculares</span>
            <h2>Combina los niveles que necesitas analizar</h2>
          </div>
          <button type="button" class="calendarization-all-toggle" :class="{ active: allLayersActive }" @click="toggleAllLayers">
            <i class="mdi mdi-layers-outline"></i> Todos juntos
          </button>
        </div>
        <div class="calendarization-layers">
          <button
            v-for="layerItem in layers"
            :key="layerItem.key"
            type="button"
            class="calendarization-layer"
            :class="{ active: activeLayers.includes(layerItem.key) }"
            :style="{ '--layer-color': layerItem.color, '--layer-soft': layerItem.soft_color }"
            @click="toggleLayer(layerItem.key)"
          >
            <span class="calendarization-layer__icon"><i :class="`mdi ${layerItem.icon}`"></i></span>
            <span class="calendarization-layer__copy">
              <strong>{{ layerItem.label }}</strong>
              <small>{{ layerItem.description }}</small>
            </span>
            <span class="calendarization-layer__count">{{ layerCount(layerItem.key) }}</span>
            <i :class="activeLayers.includes(layerItem.key) ? 'mdi mdi-check-circle' : 'mdi mdi-circle-outline'" class="calendarization-layer__check"></i>
          </button>
        </div>
      </section>

      <section class="calendarization-workspace">
        <div class="calendarization-viewbar">
          <div>
            <span class="calendarization-kicker">Vista de trabajo</span>
            <h2>{{ viewMode === "calendar" ? "Calendario mensual" : "Cronograma semanal" }}</h2>
          </div>
          <div class="calendarization-view-switch" role="group" aria-label="Cambiar vista">
            <button type="button" :class="{ active: viewMode === 'calendar' }" @click="setViewMode('calendar')"><i class="mdi mdi-calendar-month-outline"></i> Calendario</button>
            <button type="button" :class="{ active: viewMode === 'timeline' }" @click="setViewMode('timeline')"><i class="mdi mdi-format-list-bulleted"></i> Cronograma</button>
          </div>
        </div>
        <div class="calendarization-toolbar" :class="{ 'calendarization-toolbar--calendar': viewMode === 'calendar' }">
          <div class="calendarization-search">
            <i class="mdi mdi-magnify"></i>
            <input v-model="search" type="search" placeholder="Buscar actividad, acción o contenido…" aria-label="Buscar en la calendarización" />
          </div>
          <select v-if="viewMode === 'timeline'" v-model="selectedMonth" aria-label="Filtrar por mes">
            <option value="all">Todo el año</option>
            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
          </select>
          <select v-model="selectedCategory" aria-label="Filtrar por tipo">
            <option value="all">Todos los focos</option>
            <option v-for="category in categories" :key="category.key" :value="category.key">{{ category.label }}</option>
          </select>
          <select v-model="associationFilter" aria-label="Filtrar por asociación al plan">
            <option value="all">Todas las asociaciones</option>
            <option value="unlinked">Sin acción ({{ unlinkedEntriesCount }})</option>
            <option value="linked">Vinculadas ({{ linkedEntriesCount }})</option>
          </select>
        </div>

        <div v-if="loading" class="calendarization-empty">
          <i class="mdi mdi-loading mdi-spin"></i><strong>Cargando calendarización…</strong>
        </div>

        <div v-else-if="viewMode === 'calendar'" class="calendarization-month-view">
          <div class="calendarization-month-view__legend">
            <span v-for="layerItem in layers.filter((item) => activeLayers.includes(item.key))" :key="layerItem.key">
              <i :style="{ backgroundColor: layerItem.color }"></i>{{ layerItem.short_label }}
            </span>
            <span class="calendarization-month-view__hint"><i class="mdi mdi-cursor-default-click-outline"></i> Abre un evento para revisar o vincular su acción</span>
          </div>
          <FullCalendar :key="`calendar-${selectedYear}`" :options="calendarOptions" />
        </div>

        <div v-else-if="weeks.length" class="calendarization-timeline">
          <article v-for="week in weeks" :key="week.date" class="calendarization-week">
            <div class="calendarization-week__date">
              <span>{{ dateLabel(week.date, { month: "short" }) }}</span>
              <strong>{{ dateLabel(week.date, { day: "2-digit" }) }}</strong>
              <small>{{ dateLabel(week.date, { weekday: "short" }) }}</small>
            </div>
            <div class="calendarization-week__line"><span></span></div>
            <div class="calendarization-week__content">
              <div class="calendarization-week__heading">
                <span>Semana del {{ dateLabel(week.date, { day: "2-digit", month: "long" }) }}</span>
                <small>{{ week.entries.length }} {{ week.entries.length === 1 ? "capa" : "capas" }} en vista</small>
              </div>
              <div class="calendarization-week__cards">
                <article
                  v-for="entry in week.entries"
                  :key="entry.id || entry.source_key"
                  class="calendarization-entry"
                  :style="{ '--layer-color': layer(entry.level_group).color, '--layer-soft': layer(entry.level_group).soft_color }"
                >
                  <div class="calendarization-entry__rail"></div>
                  <div class="calendarization-entry__body">
                    <div class="calendarization-entry__top">
                      <span class="calendarization-entry__layer"><i :class="`mdi ${layer(entry.level_group).icon}`"></i>{{ layer(entry.level_group).short_label }}</span>
                      <span class="calendarization-entry__category">{{ categoryLabel(entry.category) }}</span>
                    </div>
                    <h3>{{ entry.title }}</h3>
                    <p v-if="entry.description">{{ entry.description }}</p>
                    <div class="calendarization-entry__meta">
                      <span><i class="mdi mdi-calendar-range-outline"></i>{{ weekRange(entry) }}</span>
                      <span v-if="entry.action"><i class="mdi mdi-link-variant"></i>{{ entry.action.title }}</span>
                      <button v-else-if="entry.id && capabilities.can_manage_plan" type="button" class="calendarization-entry__unlinked" @click="openEdit(entry)"><i class="mdi mdi-link-plus"></i> Vincular a una acción</button>
                      <span v-else><i class="mdi mdi-link-off"></i> Sin acción vinculada</span>
                      <span v-if="!isReferencePreview" :class="`status-${entry.status}`"><i class="mdi mdi-circle-medium"></i>{{ statusLabel(entry.status) }}</span>
                    </div>
                  </div>
                  <div v-if="capabilities.can_manage_plan && entry.id" class="calendarization-entry__actions">
                    <button type="button" title="Editar actividad" aria-label="Editar actividad" @click="openEdit(entry)"><i class="mdi mdi-pencil-outline"></i></button>
                    <button type="button" class="danger" title="Eliminar actividad" aria-label="Eliminar actividad" @click="deleteEntry(entry)"><i class="mdi mdi-trash-can-outline"></i></button>
                  </div>
                  <button v-if="capabilities.can_manage_plan && plan && !isReferencePreview" type="button" class="calendarization-entry__add" title="Agregar otra actividad en esta semana" aria-label="Agregar otra actividad en esta semana" @click="openCreate(entry)">
                    <i class="mdi mdi-plus"></i>
                  </button>
                </article>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="calendarization-empty">
          <i class="mdi mdi-calendar-search-outline"></i>
          <strong>No hay actividades con estos filtros</strong>
          <span>Activa otra capa o cambia el mes y el foco seleccionado.</span>
        </div>
      </section>

      <div v-if="formOpen" class="calendarization-modal" role="dialog" aria-modal="true" aria-labelledby="calendarization-form-title" @click.self="closeForm">
        <form class="calendarization-modal__dialog" @submit.prevent="saveEntry">
          <header>
            <div>
              <span class="calendarization-kicker">Calendarización {{ selectedYear }}</span>
              <h2 id="calendarization-form-title">{{ entryForm.id ? "Editar actividad" : "Nueva actividad" }}</h2>
            </div>
            <button type="button" aria-label="Cerrar" @click="closeForm"><i class="mdi mdi-close"></i></button>
          </header>
          <div class="calendarization-modal__body">
            <label class="calendarization-field calendarization-field--wide">
              <span>Actividad</span>
              <input v-model="entryForm.title" type="text" maxlength="191" required placeholder="Ej.: Taller de proyecto de vida" />
            </label>
            <label class="calendarization-field">
              <span>Capa o nivel</span>
              <select v-model="entryForm.level_group" required>
                <option v-for="layerItem in layers" :key="layerItem.key" :value="layerItem.key">{{ layerItem.label }}</option>
              </select>
            </label>
            <label class="calendarization-field">
              <span>Foco</span>
              <select v-model="entryForm.category" required>
                <option v-for="category in categories" :key="category.key" :value="category.key">{{ category.label }}</option>
              </select>
            </label>
            <label class="calendarization-field">
              <span>Inicio</span>
              <input v-model="entryForm.start_date" type="date" required />
            </label>
            <label class="calendarization-field">
              <span>Término</span>
              <input v-model="entryForm.end_date" type="date" required />
            </label>
            <label class="calendarization-field">
              <span>Estado</span>
              <select v-model="entryForm.status" required>
                <option value="planned">Planificada</option>
                <option value="confirmed">Confirmada</option>
                <option value="completed">Realizada</option>
                <option value="cancelled">Cancelada</option>
              </select>
            </label>
            <label class="calendarization-field">
              <span>Acción del plan a la que se vincula</span>
              <select v-model="entryForm.orientation_action_id">
                <option value="">Sin asociación</option>
                <option v-for="action in actions" :key="action.id" :value="action.id">{{ action.title }}</option>
              </select>
              <small class="calendarization-field__help"><i class="mdi mdi-information-outline"></i> Este vínculo organiza la calendarización, pero no aumenta el avance. Para que tribute, registra la ejecución desde “+ Actividad” en la acción.</small>
            </label>
            <label class="calendarization-field calendarization-field--wide">
              <span>Detalle u orientaciones</span>
              <textarea v-model="entryForm.description" rows="4" maxlength="5000" placeholder="Objetivo, recursos o indicaciones para la implementación…"></textarea>
            </label>
          </div>
          <footer>
            <button type="button" class="calendarization-secondary-btn" @click="closeForm">Cancelar</button>
            <button type="submit" class="calendarization-primary-btn" :disabled="saving">
              <i :class="saving ? 'mdi mdi-loading mdi-spin' : 'mdi mdi-content-save-outline'"></i>
              {{ saving ? "Guardando…" : "Guardar actividad" }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.calendarization-page { --ink: #17233f; --muted: #6d7893; --line: #e5e8f2; padding: 8px 0 42px; color: var(--ink); }
.calendarization-hero { position: relative; display: flex; justify-content: space-between; gap: 32px; overflow: hidden; margin-bottom: 22px; padding: 34px 38px; border-radius: 28px; background: radial-gradient(circle at 92% 5%, rgba(126, 100, 231, .48), transparent 34%), linear-gradient(135deg, #25235f 0%, #4b3faf 56%, #7659d7 100%); color: #fff; box-shadow: 0 24px 50px rgba(55, 45, 139, .2); }
.calendarization-hero::after { position: absolute; right: 29%; bottom: -110px; width: 280px; height: 280px; border: 1px solid rgba(255,255,255,.16); border-radius: 50%; content: ""; }
.calendarization-hero__copy { position: relative; z-index: 1; max-width: 760px; }
.calendarization-eyebrow, .calendarization-kicker { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 9px; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.calendarization-eyebrow { color: #ded9ff; }
.calendarization-hero h1 { margin: 0 0 10px; font-size: clamp(30px, 3vw, 46px); font-weight: 800; letter-spacing: -.035em; }
.calendarization-hero p { max-width: 720px; margin: 0; color: rgba(255,255,255,.78); font-size: 16px; line-height: 1.65; }
.calendarization-hero__meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
.calendarization-hero__meta span { display: inline-flex; align-items: center; gap: 7px; padding: 8px 11px; border: 1px solid rgba(255,255,255,.17); border-radius: 12px; background: rgba(255,255,255,.1); color: #fff; font-size: 12px; font-weight: 700; backdrop-filter: blur(8px); }
.calendarization-hero__actions { position: relative; z-index: 1; display: flex; min-width: 210px; flex-direction: column; align-items: stretch; justify-content: center; gap: 10px; }
.calendarization-hero__actions label { color: rgba(255,255,255,.72); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.calendarization-year-select { width: 100%; padding: 12px 42px 12px 15px; border: 1px solid rgba(255,255,255,.35); border-radius: 14px; background: rgba(255,255,255,.14); color: #fff; font-size: 16px; font-weight: 800; outline: none; }
.calendarization-year-select option { color: #17233f; }
.calendarization-primary-btn, .calendarization-secondary-btn { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 13px; padding: 10px 16px; font-weight: 800; text-decoration: none; transition: transform .18s ease, box-shadow .18s ease; }
.calendarization-primary-btn { background: linear-gradient(135deg, #7561dc, #5041b8); color: #fff; box-shadow: 0 11px 22px rgba(70, 55, 166, .22); }
.calendarization-hero .calendarization-primary-btn { background: #fff; color: #4437a4; box-shadow: 0 12px 25px rgba(20, 17, 70, .22); }
.calendarization-primary-btn:hover { color: #fff; transform: translateY(-1px); }
.calendarization-hero .calendarization-primary-btn:hover { color: #4437a4; }
.calendarization-primary-btn:disabled { opacity: .65; transform: none; }
.calendarization-secondary-btn { border: 1px solid #dfe3ee; background: #fff; color: #5f6b84; }
.calendarization-alert, .calendarization-reference { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; border-radius: 20px; }
.calendarization-alert { padding: 15px 18px; }
.calendarization-alert--danger { border: 1px solid #f2c7ce; background: #fff3f5; color: #a92f46; }
.calendarization-reference { padding: 22px 24px; border: 1px solid #d9d5fa; background: linear-gradient(120deg, #f8f7ff, #f2f7ff); box-shadow: 0 12px 30px rgba(69, 62, 139, .08); }
.calendarization-reference__icon { display: grid; width: 52px; height: 52px; flex: 0 0 52px; place-items: center; border-radius: 16px; background: #e9e5ff; color: #5a49bd; font-size: 24px; }
.calendarization-reference__label { color: #6453c6; font-size: 10px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
.calendarization-reference h2 { margin: 3px 0 5px; font-size: 19px; font-weight: 800; }
.calendarization-reference p { margin: 0; color: var(--muted); }
.calendarization-reference small { display: block; margin-top: 7px; color: #7a839a; }
.calendarization-reference__action { margin-left: auto; }
.calendarization-readonly { display: inline-flex; align-items: center; gap: 6px; color: #737d93; font-weight: 700; }
.calendarization-loaded-status { display: grid; grid-template-columns: 52px minmax(240px, 1fr) auto auto; align-items: center; gap: 18px; margin-bottom: 20px; padding: 20px 22px; border: 1px solid #cfe8de; border-radius: 20px; background: linear-gradient(120deg, #f7fffb, #f6f8ff); box-shadow: 0 12px 30px rgba(36, 102, 79, .07); }
.calendarization-loaded-status__icon { display: grid; width: 52px; height: 52px; place-items: center; border-radius: 16px; background: #dff6eb; color: #23805d; font-size: 25px; }
.calendarization-loaded-status__copy h2 { margin: 3px 0 4px; color: #1e3f35; font-size: 18px; font-weight: 850; }
.calendarization-loaded-status__copy p { max-width: 650px; margin: 0; color: #68778a; font-size: 12px; line-height: 1.5; }
.calendarization-loaded-status__metrics { display: flex; align-items: stretch; gap: 8px; }
.calendarization-loaded-status__metrics div { display: flex; min-width: 72px; flex-direction: column; align-items: center; justify-content: center; padding: 8px 10px; border: 1px solid #e2e7ef; border-radius: 13px; background: rgba(255,255,255,.85); }
.calendarization-loaded-status__metrics strong { color: #2a3954; font-size: 17px; line-height: 1; }
.calendarization-loaded-status__metrics span { margin-top: 4px; color: #7b8699; font-size: 9px; font-weight: 800; white-space: nowrap; }
.calendarization-loaded-status__metrics .pending strong { color: #bd6b19; }
.calendarization-loaded-status__metrics .linked strong { color: #23805d; }
.calendarization-review-btn { display: inline-flex; min-height: 42px; align-items: center; justify-content: center; gap: 7px; border: 1px solid #6f5ed0; border-radius: 12px; padding: 9px 13px; background: #fff; color: #5847b8; font-size: 11px; font-weight: 850; white-space: nowrap; transition: background .18s ease, color .18s ease, transform .18s ease; }
.calendarization-review-btn:hover:not(:disabled) { background: #6553c8; color: #fff; transform: translateY(-1px); }
.calendarization-review-btn:disabled { border-color: #d8e2dd; color: #739184; cursor: default; }
.calendarization-association-guide { display: grid; grid-template-columns: minmax(220px, 1.15fr) minmax(180px, 1fr) 26px minmax(180px, 1fr) 26px minmax(180px, 1fr); align-items: center; gap: 12px; margin-bottom: 22px; padding: 22px 24px; border: 1px solid #dfe5f0; border-radius: 22px; background: linear-gradient(120deg, #ffffff, #f8faff); box-shadow: 0 12px 28px rgba(33, 48, 82, .055); }
.calendarization-association-guide__title h2 { margin: 0 0 5px; font-size: 18px; font-weight: 800; }
.calendarization-association-guide__title p { margin: 0; color: var(--muted); font-size: 12px; }
.calendarization-association-step { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 13px; border: 1px solid #e6e9f2; border-radius: 15px; background: #fff; }
.calendarization-association-step > span { display: grid; width: 30px; height: 30px; flex: 0 0 30px; place-items: center; border-radius: 10px; background: #eeebff; color: #5c4bc0; font-size: 12px; font-weight: 900; }
.calendarization-association-step div { display: flex; min-width: 0; flex-direction: column; gap: 3px; }
.calendarization-association-step strong { font-size: 11px; font-weight: 900; }
.calendarization-association-step small { color: #788299; font-size: 9px; line-height: 1.35; }
.calendarization-association-arrow { color: #a7aec0; font-size: 20px; text-align: center; }
.calendarization-layer-panel, .calendarization-workspace { margin-bottom: 22px; border: 1px solid var(--line); border-radius: 24px; background: #fff; box-shadow: 0 13px 32px rgba(32, 48, 82, .065); }
.calendarization-layer-panel { padding: 24px; }
.calendarization-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 18px; }
.calendarization-kicker { margin: 0 0 4px; color: #6757c7; }
.calendarization-section-heading h2 { margin: 0; font-size: 21px; font-weight: 800; letter-spacing: -.015em; }
.calendarization-all-toggle { display: inline-flex; align-items: center; gap: 7px; border: 1px solid #dde1ec; border-radius: 12px; padding: 9px 13px; background: #fff; color: #67728a; font-weight: 800; }
.calendarization-all-toggle.active { border-color: #6655cc; background: #f0edff; color: #5543bd; }
.calendarization-layers { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.calendarization-layer { position: relative; display: grid; grid-template-columns: 42px 1fr auto; align-items: center; gap: 11px; min-width: 0; padding: 15px; overflow: hidden; border: 1px solid #e5e8f1; border-radius: 17px; background: #fff; color: var(--ink); text-align: left; }
.calendarization-layer::before { position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--layer-color); opacity: .25; content: ""; }
.calendarization-layer.active { border-color: color-mix(in srgb, var(--layer-color) 40%, white); background: var(--layer-soft); box-shadow: 0 9px 20px color-mix(in srgb, var(--layer-color) 12%, transparent); }
.calendarization-layer.active::before { opacity: 1; }
.calendarization-layer__icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 13px; background: var(--layer-soft); color: var(--layer-color); font-size: 21px; }
.calendarization-layer__copy { display: flex; min-width: 0; flex-direction: column; }
.calendarization-layer__copy strong { font-size: 13px; font-weight: 800; line-height: 1.25; }
.calendarization-layer__copy small { display: -webkit-box; margin-top: 4px; overflow: hidden; color: #7a8499; font-size: 10px; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.calendarization-layer__count { display: grid; min-width: 28px; height: 28px; place-items: center; border-radius: 9px; background: rgba(255,255,255,.8); color: var(--layer-color); font-size: 11px; font-weight: 900; }
.calendarization-layer__check { position: absolute; top: 7px; right: 7px; color: var(--layer-color); font-size: 13px; }
.calendarization-workspace { padding: 0 24px 28px; }
.calendarization-viewbar { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; padding: 22px 0 0; }
.calendarization-viewbar h2 { margin: 0; font-size: 21px; font-weight: 800; }
.calendarization-view-switch { display: inline-flex; gap: 3px; padding: 4px; border: 1px solid #e0e4ee; border-radius: 13px; background: #f4f6fa; }
.calendarization-view-switch button { display: inline-flex; min-height: 36px; align-items: center; gap: 6px; border: 0; border-radius: 9px; padding: 7px 12px; background: transparent; color: #6f7a91; font-size: 11px; font-weight: 800; }
.calendarization-view-switch button.active { background: #fff; color: #5847bc; box-shadow: 0 5px 13px rgba(42, 52, 83, .12); }
.calendarization-toolbar { position: sticky; top: 69px; z-index: 4; display: grid; grid-template-columns: minmax(220px, 1fr) 145px 185px 185px; gap: 10px; padding: 18px 0; border-bottom: 1px solid var(--line); background: rgba(255,255,255,.95); backdrop-filter: blur(12px); }
.calendarization-toolbar--calendar { grid-template-columns: minmax(240px, 1fr) 210px 210px; }
.calendarization-search { position: relative; }
.calendarization-search i { position: absolute; top: 50%; left: 14px; color: #8b95aa; font-size: 19px; transform: translateY(-50%); }
.calendarization-toolbar input, .calendarization-toolbar select, .calendarization-field input, .calendarization-field select, .calendarization-field textarea { width: 100%; border: 1px solid #dde2ed; border-radius: 12px; background: #fafbfe; color: #26334e; outline: none; transition: border-color .18s ease, box-shadow .18s ease; }
.calendarization-toolbar input, .calendarization-toolbar select { height: 44px; padding: 0 13px; }
.calendarization-toolbar input { padding-left: 42px; }
.calendarization-toolbar input:focus, .calendarization-toolbar select:focus, .calendarization-field input:focus, .calendarization-field select:focus, .calendarization-field textarea:focus { border-color: #7a67d7; box-shadow: 0 0 0 3px rgba(112, 92, 206, .1); }
.calendarization-month-view { padding: 18px 0 4px; }
.calendarization-month-view__legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-bottom: 15px; color: #667189; font-size: 10px; font-weight: 800; }
.calendarization-month-view__legend span { display: inline-flex; align-items: center; gap: 6px; }
.calendarization-month-view__legend span > i { width: 9px; height: 9px; border-radius: 3px; }
.calendarization-month-view__legend .calendarization-month-view__hint { margin-left: auto; color: #848da1; font-weight: 600; }
.calendarization-month-view__legend .calendarization-month-view__hint i { width: auto; height: auto; background: transparent !important; font-size: 14px; }
.calendarization-month-view :deep(.fc) { color: #26344f; font-family: inherit; }
.calendarization-month-view :deep(.fc .fc-toolbar.fc-header-toolbar) { margin-bottom: 16px; }
.calendarization-month-view :deep(.fc .fc-toolbar-title) { color: #1f2d49; font-size: 20px; font-weight: 850; text-transform: capitalize; }
.calendarization-month-view :deep(.fc .fc-button-primary) { border-color: #e0e4ed; border-radius: 9px; background: #fff; color: #5f6b83; box-shadow: none; font-size: 11px; font-weight: 800; }
.calendarization-month-view :deep(.fc .fc-button-primary:hover), .calendarization-month-view :deep(.fc .fc-button-primary:focus) { border-color: #7562d2; background: #f0edff; color: #5745ba; box-shadow: none; }
.calendarization-month-view :deep(.fc .fc-button-primary:not(:disabled).fc-button-active) { border-color: #6553c8; background: #6553c8; color: #fff; }
.calendarization-month-view :deep(.fc-theme-standard td), .calendarization-month-view :deep(.fc-theme-standard th), .calendarization-month-view :deep(.fc-theme-standard .fc-scrollgrid) { border-color: #e7eaf1; }
.calendarization-month-view :deep(.fc .fc-col-header-cell) { background: #f7f8fc; }
.calendarization-month-view :deep(.fc .fc-col-header-cell-cushion) { padding: 10px 5px; color: #7a849a; font-size: 10px; font-weight: 900; text-transform: uppercase; }
.calendarization-month-view :deep(.fc .fc-daygrid-day-number) { padding: 8px; color: #536078; font-size: 11px; font-weight: 800; }
.calendarization-month-view :deep(.fc .fc-day-today) { background: #faf9ff; }
.calendarization-month-view :deep(.fc .fc-daygrid-event) { margin: 2px 3px; border-radius: 6px; padding: 2px 4px; box-shadow: 0 3px 7px rgba(30, 38, 65, .11); cursor: pointer; }
.calendarization-month-view :deep(.fc .fc-event-title) { overflow: hidden; font-size: 9px; font-weight: 800; text-overflow: ellipsis; }
.calendarization-timeline { padding-top: 10px; }
.calendarization-week { display: grid; grid-template-columns: 58px 26px minmax(0, 1fr); gap: 0; }
.calendarization-week__date { display: flex; align-items: center; flex-direction: column; padding-top: 18px; color: #7a849a; text-transform: capitalize; }
.calendarization-week__date span { font-size: 11px; font-weight: 800; text-transform: uppercase; }
.calendarization-week__date strong { margin: -2px 0; color: #26334f; font-size: 25px; line-height: 1.2; }
.calendarization-week__date small { font-size: 10px; }
.calendarization-week__line { position: relative; display: flex; justify-content: center; }
.calendarization-week__line::before { width: 2px; height: 100%; background: #e6e8f0; content: ""; }
.calendarization-week__line span { position: absolute; top: 27px; width: 12px; height: 12px; border: 3px solid #fff; border-radius: 50%; background: #7160cf; box-shadow: 0 0 0 2px #dcd7fb; }
.calendarization-week__content { min-width: 0; padding: 13px 0 17px 14px; }
.calendarization-week__heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 9px; }
.calendarization-week__heading span { color: #56617a; font-size: 12px; font-weight: 800; }
.calendarization-week__heading small { color: #9098aa; font-size: 10px; }
.calendarization-week__cards { display: grid; gap: 8px; }
.calendarization-entry { position: relative; display: grid; grid-template-columns: 5px minmax(0, 1fr) auto; min-width: 0; overflow: hidden; border: 1px solid #e5e8f0; border-radius: 15px; background: #fff; box-shadow: 0 7px 18px rgba(34, 48, 77, .045); transition: border-color .18s ease, transform .18s ease; }
.calendarization-entry:hover { border-color: color-mix(in srgb, var(--layer-color) 38%, white); transform: translateY(-1px); }
.calendarization-entry__rail { background: var(--layer-color); }
.calendarization-entry__body { min-width: 0; padding: 13px 15px; }
.calendarization-entry__top, .calendarization-entry__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; }
.calendarization-entry__layer, .calendarization-entry__category { display: inline-flex; align-items: center; gap: 5px; border-radius: 8px; padding: 4px 7px; font-size: 9px; font-weight: 900; letter-spacing: .035em; text-transform: uppercase; }
.calendarization-entry__layer { background: var(--layer-soft); color: var(--layer-color); }
.calendarization-entry__category { background: #f3f5f8; color: #6f7990; }
.calendarization-entry h3 { margin: 8px 0 5px; font-size: 14px; font-weight: 800; line-height: 1.35; }
.calendarization-entry p { margin: 0 0 8px; color: #6f7990; font-size: 12px; line-height: 1.5; }
.calendarization-entry__meta { color: #788399; font-size: 10px; }
.calendarization-entry__meta span { display: inline-flex; align-items: center; gap: 4px; }
.calendarization-entry__unlinked { display: inline-flex; align-items: center; gap: 4px; border: 0; border-radius: 7px; padding: 3px 7px; background: #fff4df; color: #9a6417; font-size: 10px; font-weight: 800; }
.calendarization-entry__meta .status-completed { color: #17856c; }
.calendarization-entry__meta .status-cancelled { color: #c04458; }
.calendarization-entry__meta .status-confirmed { color: #3265c7; }
.calendarization-entry__actions { display: flex; align-items: center; gap: 5px; padding: 0 13px; }
.calendarization-entry__actions button, .calendarization-entry__add { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid #dfe3ed; border-radius: 10px; background: #fff; color: #637089; }
.calendarization-entry__actions button:hover { border-color: #7a67d7; color: #5b49bc; }
.calendarization-entry__actions button.danger:hover { border-color: #efb8c1; background: #fff5f6; color: #c53e54; }
.calendarization-entry__add { align-self: center; margin-right: 12px; border-color: color-mix(in srgb, var(--layer-color) 35%, white); background: var(--layer-soft); color: var(--layer-color); }
.calendarization-empty { display: flex; min-height: 320px; align-items: center; justify-content: center; flex-direction: column; gap: 8px; color: #7e889c; text-align: center; }
.calendarization-empty i { color: #7765d1; font-size: 42px; }
.calendarization-empty strong { color: #33415e; font-size: 16px; }
.calendarization-modal { position: fixed; inset: 0; z-index: 1090; display: grid; place-items: center; padding: 20px; background: rgba(20, 25, 48, .54); backdrop-filter: blur(5px); }
.calendarization-modal__dialog { width: min(780px, 100%); max-height: calc(100vh - 40px); overflow: auto; border-radius: 24px; background: #fff; box-shadow: 0 35px 80px rgba(15, 22, 48, .28); }
.calendarization-modal header, .calendarization-modal footer { display: flex; align-items: center; justify-content: space-between; gap: 15px; padding: 21px 24px; }
.calendarization-modal header { border-bottom: 1px solid var(--line); }
.calendarization-modal header h2 { margin: 0; font-size: 23px; font-weight: 800; }
.calendarization-modal header > button { display: grid; width: 38px; height: 38px; place-items: center; border: 0; border-radius: 11px; background: #f3f5f8; color: #657086; font-size: 21px; }
.calendarization-modal__body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px; padding: 23px 24px; }
.calendarization-field { display: flex; min-width: 0; flex-direction: column; gap: 7px; }
.calendarization-field--wide { grid-column: 1 / -1; }
.calendarization-field > span { color: #5e6981; font-size: 11px; font-weight: 800; }
.calendarization-field input, .calendarization-field select { height: 44px; padding: 0 12px; }
.calendarization-field textarea { padding: 11px 12px; resize: vertical; }
.calendarization-field__help { display: flex; align-items: flex-start; gap: 5px; color: #7b859a; font-size: 9px; line-height: 1.4; }
.calendarization-field__help i { color: #6655c7; font-size: 13px; }
.calendarization-modal footer { justify-content: flex-end; border-top: 1px solid var(--line); background: #fafbfe; }
@media (max-width: 1199px) {
  .calendarization-layers { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .calendarization-loaded-status { grid-template-columns: 52px minmax(240px, 1fr) auto; }
  .calendarization-loaded-status__metrics { grid-column: 2; }
  .calendarization-review-btn { grid-column: 3; grid-row: 1 / span 2; }
  .calendarization-association-guide { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .calendarization-association-guide__title { grid-column: 1 / -1; }
  .calendarization-association-arrow { display: none; }
}
@media (max-width: 767px) {
  .calendarization-page { padding-top: 0; }
  .calendarization-hero { flex-direction: column; padding: 27px 23px; border-radius: 22px; }
  .calendarization-hero__actions { min-width: 0; }
  .calendarization-reference { align-items: flex-start; flex-wrap: wrap; }
  .calendarization-reference__action { width: 100%; margin-left: 0; }
  .calendarization-reference__action .calendarization-primary-btn { width: 100%; }
  .calendarization-loaded-status { grid-template-columns: 42px minmax(0, 1fr); padding: 18px; }
  .calendarization-loaded-status__icon { width: 42px; height: 42px; font-size: 21px; }
  .calendarization-loaded-status__metrics { grid-column: 1 / -1; width: 100%; }
  .calendarization-loaded-status__metrics div { min-width: 0; flex: 1; }
  .calendarization-review-btn { grid-column: 1 / -1; grid-row: auto; width: 100%; }
  .calendarization-association-guide { grid-template-columns: 1fr; }
  .calendarization-association-guide__title { grid-column: auto; }
  .calendarization-section-heading { align-items: stretch; flex-direction: column; }
  .calendarization-all-toggle { justify-content: center; }
  .calendarization-layers { grid-template-columns: 1fr; }
  .calendarization-workspace { padding: 0 13px 22px; }
  .calendarization-viewbar { align-items: stretch; flex-direction: column; }
  .calendarization-view-switch { display: grid; grid-template-columns: 1fr 1fr; }
  .calendarization-view-switch button { justify-content: center; }
  .calendarization-toolbar { position: static; grid-template-columns: 1fr; }
  .calendarization-toolbar--calendar { grid-template-columns: 1fr; }
  .calendarization-month-view__legend .calendarization-month-view__hint { width: 100%; margin-left: 0; }
  .calendarization-month-view :deep(.fc .fc-toolbar) { align-items: stretch; flex-direction: column; gap: 9px; }
  .calendarization-month-view :deep(.fc .fc-toolbar-chunk) { display: flex; justify-content: center; }
  .calendarization-month-view :deep(.fc .fc-daygrid-event) { padding: 1px 2px; }
  .calendarization-month-view :deep(.fc .fc-event-title) { font-size: 8px; }
  .calendarization-week { grid-template-columns: 43px 18px minmax(0, 1fr); }
  .calendarization-week__content { padding-left: 8px; }
  .calendarization-week__heading small { display: none; }
  .calendarization-entry { grid-template-columns: 4px minmax(0, 1fr); }
  .calendarization-entry__actions { grid-column: 2; justify-content: flex-end; padding: 0 10px 10px; }
  .calendarization-entry__add { position: absolute; right: 8px; bottom: 8px; margin: 0; }
  .calendarization-modal__body { grid-template-columns: 1fr; }
  .calendarization-field--wide { grid-column: auto; }
}
</style>
