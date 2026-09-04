<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      error: "",
      entries: [],
      sources: [],
      catalogs: { priorities: [], statuses: [] },
      summary: { total: 0, today: 0, follow_up: 0, high_priority: 0 },
      pagination: { current_page: 1, last_page: 1, total: 0, per_page: 20, from: null, to: null },
      filters: {
        search: "",
        source: "",
        date_from: "",
        date_to: "",
        priority: "",
        status: "",
      },
      selectedEntry: null,
      showDetail: false,
    };
  },
  computed: {
    sourceOptions() {
      return [{ value: "", text: "Todas las áreas" }].concat(
        this.sources.map((source) => ({ value: source.value, text: source.label }))
      );
    },
    priorityOptions() {
      return [{ value: "", text: "Todas las prioridades" }].concat(
        (this.catalogs.priorities || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    statusOptions() {
      return [{ value: "", text: "Todos los estados" }].concat(
        (this.catalogs.statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
    activeFilterCount() {
      return Object.values(this.filters).filter((value) => String(value || "").trim() !== "").length;
    },
    rangeLabel() {
      if (!this.pagination.total) return "Sin registros para los filtros aplicados";
      return `Mostrando ${this.pagination.from}–${this.pagination.to} de ${this.pagination.total}`;
    },
    detailFacts() {
      const entry = this.selectedEntry;
      if (!entry) return [];

      const extra = entry.extra || {};
      const facts = [
        { label: "Área de origen", value: entry.source_label },
        { label: "Fecha y hora", value: this.formatDate(entry.occurred_at) },
        { label: "Registrado por", value: entry.author?.name },
        { label: "Categoría", value: this.humanize(entry.category) },
        { label: "Prioridad", value: this.humanize(entry.priority) },
        { label: "Estado", value: this.humanize(entry.status) },
        { label: "Estudiante", value: entry.student?.name },
        { label: "RUT", value: entry.student?.rut },
        { label: "Curso", value: entry.course?.name },
        { label: "Inspector/a", value: extra.inspector },
        { label: "Turno", value: extra.shift_label },
        { label: "Lugar", value: extra.place },
        { label: "Funcionario atrasado", value: extra.late_staff },
        { label: "Minutos de atraso", value: extra.lateness_minutes ? `${extra.lateness_minutes} min` : null },
        { label: "Cursos asociados", value: (extra.associated_courses || []).join(", ") },
        { label: "Caso asociado", value: extra.case_folio },
        { label: "Destino de derivación", value: extra.derivation_destination },
      ];

      return facts.filter((fact) => fact.value !== null && fact.value !== undefined && fact.value !== "");
    },
    detailNarratives() {
      const entry = this.selectedEntry;
      if (!entry) return [];
      const extra = entry.extra || {};

      return [
        { label: "Detalle registrado", value: entry.detail },
        { label: "Acción realizada", value: extra.action_taken || extra.immediate_action },
        { label: "Seguimiento", value: extra.follow_up_note },
        { label: "Contacto con apoderado", value: extra.guardian_contact_note },
      ].filter((item) => item.value);
    },
  },
  mounted() {
    this.loadEntries();
  },
  methods: {
    async loadEntries(page = 1) {
      this.loading = true;
      this.error = "";

      try {
        const params = { page, per_page: this.pagination.per_page };
        Object.entries(this.filters).forEach(([key, value]) => {
          if (String(value || "").trim() !== "") params[key] = value;
        });

        const { data } = await axios.get("/api/superadmin/logbooks", { params });
        this.entries = data.data || [];
        this.sources = data.sources || [];
        this.catalogs = data.catalogs || this.catalogs;
        this.summary = data.summary || this.summary;
        this.pagination = {
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
          total: data.total || 0,
          per_page: data.per_page || this.pagination.per_page,
          from: data.from,
          to: data.to,
        };
      } catch (error) {
        this.error = error.response?.data?.message || "No fue posible consolidar las bitácoras. Intenta nuevamente.";
      } finally {
        this.loading = false;
      }
    },
    chooseSource(source) {
      this.filters.source = this.filters.source === source ? "" : source;
      this.loadEntries(1);
    },
    clearFilters() {
      this.filters = { search: "", source: "", date_from: "", date_to: "", priority: "", status: "" };
      this.loadEntries(1);
    },
    openDetail(entry) {
      this.selectedEntry = entry;
      this.showDetail = true;
    },
    sourceDefinition(key) {
      return this.sources.find((source) => source.value === key) || {};
    },
    formatDate(value) {
      if (!value) return "Sin fecha";
      const normalized = /T|Z$/.test(value) ? value : String(value).replace(" ", "T");
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) return value;

      return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "long",
        timeStyle: "short",
      }).format(date);
    },
    formatDateParts(value) {
      if (!value) return { day: "--", month: "---", time: "--:--" };
      const normalized = /T|Z$/.test(value) ? value : String(value).replace(" ", "T");
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) return { day: "--", month: "---", time: "--:--" };

      return {
        day: new Intl.DateTimeFormat("es-CL", { day: "2-digit" }).format(date),
        month: new Intl.DateTimeFormat("es-CL", { month: "short" }).format(date).replace(".", ""),
        time: new Intl.DateTimeFormat("es-CL", { hour: "2-digit", minute: "2-digit", hour12: false }).format(date),
      };
    },
    humanize(value) {
      if (!value) return "—";
      return String(value)
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    priorityClass(priority) {
      return {
        urgente: "logbook-badge--danger",
        alta: "logbook-badge--warning",
        media: "logbook-badge--info",
        baja: "logbook-badge--muted",
      }[priority] || "logbook-badge--muted";
    },
    statusClass(status) {
      if (["cerrado", "revisado"].includes(status)) return "logbook-badge--success";
      if (["en_seguimiento", "destacado"].includes(status)) return "logbook-badge--warning";
      if (["convertido_caso", "convertido_derivacion"].includes(status)) return "logbook-badge--violet";
      return "logbook-badge--muted";
    },
  },
};
</script>

<template>
  <Layout>
    <main class="logbook-review">
      <section class="logbook-hero">
        <div class="logbook-hero__glow logbook-hero__glow--one"></div>
        <div class="logbook-hero__glow logbook-hero__glow--two"></div>
        <div class="logbook-hero__content">
          <div class="logbook-hero__eyebrow"><i class="bx bx-lock-alt"></i> Herramienta exclusiva de Superadmin</div>
          <h1>Revisión central de bitácoras</h1>
          <p>
            Una sola línea de tiempo para supervisar Inspectoría, Portería, Enfermería y Convivencia Escolar,
            sin alterar los registros de origen.
          </p>
          <div class="logbook-hero__trust">
            <span><i class="bx bx-show"></i> Sólo lectura</span>
            <span><i class="bx bx-data"></i> Fuentes originales</span>
            <span><i class="bx bx-shield-quarter"></i> Acceso restringido</span>
          </div>
        </div>
        <button type="button" class="logbook-refresh" :disabled="loading" @click="loadEntries(pagination.current_page)">
          <i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i>
          Actualizar revisión
        </button>
      </section>

      <BAlert v-if="error" show variant="danger" class="logbook-alert">
        <i class="bx bx-error-circle"></i><span>{{ error }}</span>
      </BAlert>

      <section class="logbook-metrics" aria-label="Resumen de bitácoras">
        <article class="logbook-metric logbook-metric--primary">
          <span class="logbook-metric__icon"><i class="bx bx-notepad"></i></span>
          <div><small>Registros encontrados</small><strong>{{ summary.total }}</strong><span>según los filtros activos</span></div>
        </article>
        <article class="logbook-metric logbook-metric--teal">
          <span class="logbook-metric__icon"><i class="bx bx-calendar-check"></i></span>
          <div><small>Actividad de hoy</small><strong>{{ summary.today }}</strong><span>registros de la jornada</span></div>
        </article>
        <article class="logbook-metric logbook-metric--amber">
          <span class="logbook-metric__icon"><i class="bx bx-git-branch"></i></span>
          <div><small>Con seguimiento</small><strong>{{ summary.follow_up }}</strong><span>continuidades abiertas</span></div>
        </article>
        <article class="logbook-metric logbook-metric--rose">
          <span class="logbook-metric__icon"><i class="bx bx-error-alt"></i></span>
          <div><small>Alta prioridad</small><strong>{{ summary.high_priority }}</strong><span>registros no cerrados</span></div>
        </article>
      </section>

      <section class="logbook-source-grid" aria-label="Bitácoras integradas">
        <button
          v-for="source in sources"
          :key="source.value"
          type="button"
          class="logbook-source"
          :class="{ 'logbook-source--active': filters.source === source.value }"
          :style="{ '--source-accent': source.accent }"
          @click="chooseSource(source.value)"
        >
          <span class="logbook-source__icon"><i class="bx" :class="source.icon"></i></span>
          <span class="logbook-source__copy"><strong>{{ source.label }}</strong><small>{{ source.description }}</small></span>
          <span class="logbook-source__count">{{ source.count }}</span>
        </button>
      </section>

      <section class="logbook-workspace">
        <form class="logbook-filters" @submit.prevent="loadEntries(1)">
          <div class="logbook-filter logbook-filter--search">
            <label for="logbook-search">Buscar en todas las bitácoras</label>
            <div class="logbook-search">
              <i class="bx bx-search"></i>
              <input id="logbook-search" v-model.trim="filters.search" type="search" placeholder="Título, detalle, estudiante, RUT, curso o responsable">
              <button type="submit" aria-label="Buscar"><i class="bx bx-right-arrow-alt"></i></button>
            </div>
          </div>
          <div class="logbook-filter">
            <label for="logbook-source">Área</label>
            <BFormSelect id="logbook-source" v-model="filters.source" :options="sourceOptions" @change="loadEntries(1)" />
          </div>
          <div class="logbook-filter">
            <label for="logbook-from">Desde</label>
            <input id="logbook-from" v-model="filters.date_from" type="date" class="form-control" @change="loadEntries(1)">
          </div>
          <div class="logbook-filter">
            <label for="logbook-to">Hasta</label>
            <input id="logbook-to" v-model="filters.date_to" type="date" class="form-control" @change="loadEntries(1)">
          </div>
          <div class="logbook-filter">
            <label for="logbook-priority">Prioridad</label>
            <BFormSelect id="logbook-priority" v-model="filters.priority" :options="priorityOptions" @change="loadEntries(1)" />
          </div>
          <div class="logbook-filter">
            <label for="logbook-status">Estado</label>
            <BFormSelect id="logbook-status" v-model="filters.status" :options="statusOptions" @change="loadEntries(1)" />
          </div>
          <button v-if="activeFilterCount" type="button" class="logbook-clear" @click="clearFilters">
            <i class="bx bx-x"></i> Limpiar {{ activeFilterCount }} {{ activeFilterCount === 1 ? "filtro" : "filtros" }}
          </button>
        </form>

        <div class="logbook-list-header">
          <div>
            <span class="logbook-list-header__eyebrow">Línea de tiempo consolidada</span>
            <h2>Todos los registros</h2>
          </div>
          <span class="logbook-range">{{ rangeLabel }}</span>
        </div>

        <LoadingState v-if="loading && !entries.length" message="Consolidando bitácoras institucionales..." />

        <div v-else-if="!entries.length" class="logbook-empty">
          <span><i class="bx bx-search-alt"></i></span>
          <h3>No hay registros coincidentes</h3>
          <p>Prueba ampliando las fechas o quitando alguno de los filtros.</p>
          <button v-if="activeFilterCount" type="button" @click="clearFilters">Ver todas las bitácoras</button>
        </div>

        <template v-else>
          <div class="logbook-table-wrap d-none d-lg-block" :class="{ 'logbook-table-wrap--loading': loading }">
            <table class="logbook-table">
              <thead><tr><th>Fecha</th><th>Origen</th><th>Registro</th><th>Persona / curso</th><th>Estado</th><th>Responsable</th><th></th></tr></thead>
              <tbody>
                <tr v-for="entry in entries" :key="entry.key">
                  <td>
                    <div class="logbook-date">
                      <strong>{{ formatDateParts(entry.occurred_at).day }}</strong>
                      <span>{{ formatDateParts(entry.occurred_at).month }}</span>
                      <small>{{ formatDateParts(entry.occurred_at).time }}</small>
                    </div>
                  </td>
                  <td>
                    <span class="logbook-origin" :style="{ '--source-accent': sourceDefinition(entry.source).accent }">
                      <i class="bx" :class="sourceDefinition(entry.source).icon"></i>{{ entry.source_label }}
                    </span>
                  </td>
                  <td class="logbook-table__record">
                    <strong>{{ entry.title }}</strong>
                    <p>{{ entry.detail }}</p>
                    <span>{{ humanize(entry.category) }}</span>
                  </td>
                  <td>
                    <div v-if="entry.student" class="logbook-person"><i class="bx bx-user"></i><span><strong>{{ entry.student.name }}</strong><small>{{ entry.course?.name || entry.student.rut || "Sin curso" }}</small></span></div>
                    <span v-else class="logbook-no-person">Registro general</span>
                  </td>
                  <td>
                    <div class="d-flex flex-column align-items-start gap-1">
                      <span v-if="entry.priority" class="logbook-badge" :class="priorityClass(entry.priority)">{{ humanize(entry.priority) }}</span>
                      <span class="logbook-badge" :class="statusClass(entry.status)">{{ humanize(entry.status) }}</span>
                    </div>
                  </td>
                  <td><div class="logbook-author"><span>{{ (entry.author?.name || "?").charAt(0).toUpperCase() }}</span><small>{{ entry.author?.name }}</small></div></td>
                  <td><button type="button" class="logbook-detail-button" :aria-label="`Ver detalle de ${entry.title}`" @click="openDetail(entry)"><i class="bx bx-show"></i></button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="logbook-mobile-list d-lg-none" :class="{ 'logbook-mobile-list--loading': loading }">
            <article v-for="entry in entries" :key="entry.key" class="logbook-mobile-card" :style="{ '--source-accent': sourceDefinition(entry.source).accent }">
              <div class="logbook-mobile-card__head">
                <span class="logbook-origin"><i class="bx" :class="sourceDefinition(entry.source).icon"></i>{{ entry.source_label }}</span>
                <time>{{ formatDate(entry.occurred_at) }}</time>
              </div>
              <h3>{{ entry.title }}</h3>
              <p>{{ entry.detail }}</p>
              <div v-if="entry.student" class="logbook-person"><i class="bx bx-user"></i><span><strong>{{ entry.student.name }}</strong><small>{{ entry.course?.name || entry.student.rut }}</small></span></div>
              <div class="logbook-mobile-card__foot">
                <div class="d-flex gap-1 flex-wrap">
                  <span v-if="entry.priority" class="logbook-badge" :class="priorityClass(entry.priority)">{{ humanize(entry.priority) }}</span>
                  <span class="logbook-badge" :class="statusClass(entry.status)">{{ humanize(entry.status) }}</span>
                </div>
                <button type="button" @click="openDetail(entry)">Ver ficha <i class="bx bx-right-arrow-alt"></i></button>
              </div>
            </article>
          </div>

          <div v-if="pagination.last_page > 1" class="logbook-pagination">
            <BPagination
              :model-value="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              first-number
              last-number
              @update:model-value="loadEntries"
            />
          </div>
        </template>
      </section>

      <BModal v-model="showDetail" size="xl" hide-footer centered scrollable body-class="p-0" modal-class="logbook-detail-modal">
        <template #header>
          <div class="logbook-detail-header">
            <div class="logbook-detail-header__icon" :style="{ '--source-accent': sourceDefinition(selectedEntry?.source).accent }">
              <i class="bx" :class="sourceDefinition(selectedEntry?.source).icon"></i>
            </div>
            <div><span>{{ selectedEntry?.source_label }}</span><h2>{{ selectedEntry?.title }}</h2></div>
          </div>
          <button type="button" class="logbook-detail-close" aria-label="Cerrar detalle" @click="showDetail = false"><i class="bx bx-x"></i></button>
        </template>

        <div v-if="selectedEntry" class="logbook-detail">
          <div class="logbook-detail__notice">
            <i class="bx bx-shield-quarter"></i>
            <div><strong>Consulta protegida y de sólo lectura</strong><span>La información permanece en la bitácora original y no puede modificarse desde esta herramienta.</span></div>
          </div>
          <div class="logbook-detail__badges">
            <span v-if="selectedEntry.priority" class="logbook-badge" :class="priorityClass(selectedEntry.priority)">{{ humanize(selectedEntry.priority) }}</span>
            <span class="logbook-badge" :class="statusClass(selectedEntry.status)">{{ humanize(selectedEntry.status) }}</span>
            <span v-if="selectedEntry.is_sensitive" class="logbook-badge logbook-badge--protected"><i class="bx bx-lock-alt"></i> Información sensible</span>
            <span v-if="selectedEntry.requires_follow_up" class="logbook-badge logbook-badge--warning"><i class="bx bx-git-branch"></i> Requiere seguimiento</span>
          </div>
          <section class="logbook-detail__facts">
            <div v-for="fact in detailFacts" :key="fact.label"><span>{{ fact.label }}</span><strong>{{ fact.value }}</strong></div>
          </section>
          <section class="logbook-detail__narratives">
            <article v-for="item in detailNarratives" :key="item.label"><span>{{ item.label }}</span><p>{{ item.value }}</p></article>
          </section>
          <div class="logbook-detail__footer">
            <span>Identificador de origen: {{ selectedEntry.source }} #{{ selectedEntry.source_id }}</span>
            <RouterLink v-if="sourceDefinition(selectedEntry.source).route" :to="sourceDefinition(selectedEntry.source).route" @click="showDetail = false">
              Abrir bitácora de origen <i class="bx bx-link-external"></i>
            </RouterLink>
          </div>
        </div>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.logbook-review { --ink: #172033; --muted: #6f7a8f; padding-bottom: 2rem; color: var(--ink); }
.logbook-hero { position: relative; isolation: isolate; display: flex; align-items: flex-end; justify-content: space-between; gap: 2rem; overflow: hidden; min-height: 250px; margin-bottom: 1.25rem; padding: 2.2rem 2.4rem; border-radius: 24px; color: #fff; background: linear-gradient(125deg, #172554 0%, #312e81 52%, #4c1d95 100%); box-shadow: 0 22px 55px rgba(30, 41, 89, .22); }
.logbook-hero__glow { position: absolute; z-index: -1; border-radius: 50%; filter: blur(2px); opacity: .35; }
.logbook-hero__glow--one { width: 290px; height: 290px; top: -160px; right: 17%; background: #38bdf8; }
.logbook-hero__glow--two { width: 240px; height: 240px; right: -90px; bottom: -130px; background: #f472b6; }
.logbook-hero__content { max-width: 740px; }
.logbook-hero__eyebrow { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .9rem; padding: .4rem .72rem; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; color: #dbeafe; background: rgba(255,255,255,.09); font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.logbook-hero h1 { margin: 0 0 .65rem; color: #fff; font-size: clamp(1.75rem, 3vw, 2.7rem); font-weight: 750; letter-spacing: -.035em; }
.logbook-hero p { max-width: 680px; margin: 0; color: rgba(255,255,255,.78); font-size: 1rem; line-height: 1.65; }
.logbook-hero__trust { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1.3rem; color: #e0e7ff; font-size: .8rem; }
.logbook-hero__trust span { display: inline-flex; align-items: center; gap: .35rem; }
.logbook-refresh { flex: 0 0 auto; display: inline-flex; align-items: center; gap: .5rem; padding: .72rem 1rem; border: 1px solid rgba(255,255,255,.28); border-radius: 12px; color: #fff; background: rgba(255,255,255,.11); font-weight: 650; transition: .2s ease; }
.logbook-refresh:hover:not(:disabled) { background: rgba(255,255,255,.2); transform: translateY(-1px); }
.logbook-alert { display: flex; align-items: center; gap: .55rem; border: 0; border-radius: 14px; }
.logbook-metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
.logbook-metric { display: flex; align-items: center; gap: .9rem; min-height: 112px; padding: 1rem 1.1rem; border: 1px solid #e9edf5; border-radius: 18px; background: #fff; box-shadow: 0 8px 22px rgba(36, 48, 79, .055); }
.logbook-metric__icon { display: grid; flex: 0 0 46px; width: 46px; height: 46px; place-items: center; border-radius: 14px; font-size: 1.35rem; }
.logbook-metric small, .logbook-metric span { display: block; color: var(--muted); }
.logbook-metric small { margin-bottom: .15rem; font-size: .75rem; font-weight: 650; }
.logbook-metric strong { display: block; color: var(--ink); font-size: 1.65rem; line-height: 1; }
.logbook-metric div > span { margin-top: .3rem; font-size: .7rem; }
.logbook-metric--primary .logbook-metric__icon { color: #4f46e5; background: #eef2ff; }
.logbook-metric--teal .logbook-metric__icon { color: #0f766e; background: #e8fbf7; }
.logbook-metric--amber .logbook-metric__icon { color: #b45309; background: #fff7e6; }
.logbook-metric--rose .logbook-metric__icon { color: #be3652; background: #fff0f3; }
.logbook-source-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin-bottom: 1rem; }
.logbook-source { --source-accent: #5b5bd6; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .7rem; padding: .85rem; border: 1px solid #e5eaf3; border-radius: 16px; text-align: left; background: #fff; transition: .2s ease; }
.logbook-source:hover, .logbook-source--active { border-color: color-mix(in srgb, var(--source-accent) 55%, #fff); box-shadow: 0 8px 20px color-mix(in srgb, var(--source-accent) 12%, transparent); transform: translateY(-1px); }
.logbook-source--active { box-shadow: inset 0 0 0 1px var(--source-accent), 0 8px 20px color-mix(in srgb, var(--source-accent) 12%, transparent); }
.logbook-source__icon { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 12px; color: var(--source-accent); background: color-mix(in srgb, var(--source-accent) 11%, #fff); font-size: 1.15rem; }
.logbook-source__copy { min-width: 0; }
.logbook-source__copy strong, .logbook-source__copy small { display: block; }
.logbook-source__copy strong { margin-bottom: .15rem; color: var(--ink); font-size: .82rem; }
.logbook-source__copy small { overflow: hidden; color: var(--muted); font-size: .68rem; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
.logbook-source__count { display: grid; min-width: 30px; height: 30px; padding: 0 .35rem; place-items: center; border-radius: 10px; color: var(--source-accent); background: color-mix(in srgb, var(--source-accent) 9%, #fff); font-size: .78rem; font-weight: 750; }
.logbook-workspace { overflow: hidden; border: 1px solid #e5eaf3; border-radius: 20px; background: #fff; box-shadow: 0 12px 32px rgba(36, 48, 79, .06); }
.logbook-filters { display: grid; grid-template-columns: minmax(260px, 2fr) repeat(5, minmax(125px, 1fr)); gap: .75rem; padding: 1rem 1.15rem; border-bottom: 1px solid #edf0f6; background: #f8faff; }
.logbook-filter label { display: block; margin: 0 0 .35rem; color: #576176; font-size: .68rem; font-weight: 750; letter-spacing: .025em; }
.logbook-filter :deep(.form-select), .logbook-filter .form-control, .logbook-search { min-height: 40px; border-color: #dde3ee; border-radius: 10px; background-color: #fff; font-size: .78rem; }
.logbook-search { display: flex; align-items: center; overflow: hidden; border: 1px solid #dde3ee; }
.logbook-search > i { margin-left: .75rem; color: #8a94a7; font-size: 1.05rem; }
.logbook-search input { min-width: 0; flex: 1; padding: .55rem .55rem; border: 0; outline: 0; color: var(--ink); background: transparent; }
.logbook-search button { display: grid; align-self: stretch; width: 42px; border: 0; place-items: center; color: #fff; background: #4f46e5; font-size: 1.15rem; }
.logbook-clear { grid-column: 1 / -1; justify-self: start; display: inline-flex; align-items: center; gap: .25rem; padding: .25rem .45rem; border: 0; color: #5b5bd6; background: transparent; font-size: .75rem; font-weight: 650; }
.logbook-list-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.25rem .8rem; }
.logbook-list-header__eyebrow { color: #6d5ce7; font-size: .68rem; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
.logbook-list-header h2 { margin: .12rem 0 0; color: var(--ink); font-size: 1.05rem; font-weight: 750; }
.logbook-range { color: var(--muted); font-size: .72rem; }
.logbook-table-wrap { overflow-x: auto; transition: opacity .2s; }
.logbook-table-wrap--loading, .logbook-mobile-list--loading { opacity: .48; pointer-events: none; }
.logbook-table { width: 100%; border-collapse: collapse; }
.logbook-table th { padding: .7rem 1rem; border-block: 1px solid #edf0f6; color: #7b8598; background: #fbfcfe; font-size: .65rem; font-weight: 750; letter-spacing: .045em; text-align: left; text-transform: uppercase; }
.logbook-table td { padding: .9rem 1rem; border-bottom: 1px solid #eff2f7; vertical-align: middle; }
.logbook-table tbody tr { transition: background .15s; }
.logbook-table tbody tr:hover { background: #fafbff; }
.logbook-date { display: grid; width: 42px; text-align: center; }
.logbook-date strong { color: var(--ink); font-size: 1.05rem; line-height: 1; }
.logbook-date span { color: #586276; font-size: .66rem; font-weight: 700; text-transform: uppercase; }
.logbook-date small { margin-top: .15rem; color: #9aa2b2; font-size: .62rem; }
.logbook-origin { --source-accent: #5b5bd6; display: inline-flex; align-items: center; gap: .35rem; color: var(--source-accent); font-size: .72rem; font-weight: 750; white-space: nowrap; }
.logbook-origin i { display: grid; width: 25px; height: 25px; place-items: center; border-radius: 8px; background: color-mix(in srgb, var(--source-accent) 10%, #fff); }
.logbook-table__record { min-width: 280px; max-width: 420px; }
.logbook-table__record > strong { display: block; margin-bottom: .2rem; color: var(--ink); font-size: .79rem; }
.logbook-table__record p { display: -webkit-box; overflow: hidden; margin: 0 0 .25rem; color: #697387; font-size: .72rem; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.logbook-table__record > span, .logbook-no-person { color: #9199a9; font-size: .65rem; }
.logbook-person { display: flex; align-items: center; gap: .45rem; min-width: 155px; }
.logbook-person > i { color: #6d5ce7; font-size: 1rem; }
.logbook-person strong, .logbook-person small { display: block; }
.logbook-person strong { max-width: 180px; overflow: hidden; color: var(--ink); font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }
.logbook-person small { margin-top: .1rem; color: #8b94a5; font-size: .64rem; }
.logbook-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .27rem .46rem; border-radius: 999px; font-size: .62rem; font-weight: 750; white-space: nowrap; }
.logbook-badge--danger { color: #b4233e; background: #ffe9ee; }
.logbook-badge--warning { color: #9a5b03; background: #fff1cf; }
.logbook-badge--info { color: #1e628f; background: #e8f5ff; }
.logbook-badge--muted { color: #647084; background: #edf1f6; }
.logbook-badge--success { color: #08785e; background: #e5f8f1; }
.logbook-badge--violet { color: #6541b5; background: #f0eaff; }
.logbook-badge--protected { color: #324577; background: #e8eefc; }
.logbook-author { display: flex; align-items: center; gap: .45rem; min-width: 135px; }
.logbook-author > span { display: grid; flex: 0 0 27px; width: 27px; height: 27px; place-items: center; border-radius: 9px; color: #4f46e5; background: #eeefff; font-size: .7rem; font-weight: 800; }
.logbook-author small { display: -webkit-box; overflow: hidden; color: #667085; font-size: .68rem; line-height: 1.2; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.logbook-detail-button { display: grid; width: 34px; height: 34px; border: 1px solid #e1e5ee; border-radius: 10px; place-items: center; color: #4f46e5; background: #fff; font-size: 1rem; transition: .15s; }
.logbook-detail-button:hover { border-color: #c7c7ff; background: #f3f2ff; }
.logbook-pagination { display: flex; justify-content: center; padding: 1.15rem; border-top: 1px solid #edf0f6; }
.logbook-pagination :deep(.pagination) { margin: 0; }
.logbook-empty { padding: 4rem 1rem; text-align: center; }
.logbook-empty > span { display: grid; width: 62px; height: 62px; margin: 0 auto 1rem; place-items: center; border-radius: 19px; color: #6258dc; background: #f0efff; font-size: 1.75rem; }
.logbook-empty h3 { margin: 0 0 .35rem; font-size: 1rem; }
.logbook-empty p { margin: 0 0 1rem; color: var(--muted); font-size: .8rem; }
.logbook-empty button { padding: .55rem .8rem; border: 0; border-radius: 10px; color: #fff; background: #4f46e5; font-size: .75rem; font-weight: 700; }
.logbook-detail-header { display: flex; align-items: center; gap: .75rem; min-width: 0; }
.logbook-detail-header__icon { --source-accent: #5b5bd6; display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 13px; color: var(--source-accent); background: color-mix(in srgb, var(--source-accent) 11%, #fff); font-size: 1.2rem; }
.logbook-detail-header span { color: #737d90; font-size: .68rem; font-weight: 700; text-transform: uppercase; }
.logbook-detail-header h2 { overflow: hidden; margin: .1rem 0 0; color: var(--ink); font-size: 1rem; text-overflow: ellipsis; white-space: nowrap; }
.logbook-detail-close { display: grid; width: 34px; height: 34px; margin-left: auto; border: 0; border-radius: 10px; place-items: center; color: #647084; background: #f1f3f7; font-size: 1.2rem; }
.logbook-detail { padding: 1.2rem; background: #f8f9fc; }
.logbook-detail__notice { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; padding: .8rem .9rem; border: 1px solid #dbe5fa; border-radius: 13px; color: #304a79; background: #f1f6ff; }
.logbook-detail__notice > i { font-size: 1.35rem; }
.logbook-detail__notice strong, .logbook-detail__notice span { display: block; }
.logbook-detail__notice strong { font-size: .78rem; }
.logbook-detail__notice span { margin-top: .1rem; color: #647594; font-size: .68rem; }
.logbook-detail__badges { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
.logbook-detail__facts { display: grid; grid-template-columns: repeat(3, 1fr); overflow: hidden; margin-bottom: 1rem; border: 1px solid #e5e9f1; border-radius: 14px; background: #fff; }
.logbook-detail__facts > div { min-width: 0; padding: .75rem .85rem; border-right: 1px solid #edf0f5; border-bottom: 1px solid #edf0f5; }
.logbook-detail__facts span, .logbook-detail__facts strong { display: block; }
.logbook-detail__facts span { margin-bottom: .2rem; color: #8a93a4; font-size: .62rem; text-transform: uppercase; }
.logbook-detail__facts strong { overflow-wrap: anywhere; color: #2c3548; font-size: .74rem; }
.logbook-detail__narratives { display: grid; gap: .75rem; }
.logbook-detail__narratives article { padding: .9rem; border: 1px solid #e5e9f1; border-radius: 14px; background: #fff; }
.logbook-detail__narratives span { color: #6d5ce7; font-size: .65rem; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; }
.logbook-detail__narratives p { margin: .4rem 0 0; color: #4e596d; font-size: .76rem; line-height: 1.65; white-space: pre-wrap; }
.logbook-detail__footer { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1rem; padding-top: .8rem; border-top: 1px solid #e2e6ee; color: #8b94a4; font-size: .66rem; }
.logbook-detail__footer a { color: #554fc7; font-weight: 700; }
.bx-spin { animation: logbook-spin .8s linear infinite; }
@keyframes logbook-spin { to { transform: rotate(360deg); } }
@media (max-width: 1399px) {
  .logbook-filters { grid-template-columns: repeat(3, 1fr); }
  .logbook-filter--search { grid-column: span 3; }
}
@media (max-width: 1199px) {
  .logbook-metrics, .logbook-source-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
  .logbook-hero { align-items: flex-start; min-height: 0; padding: 1.35rem; border-radius: 18px; flex-direction: column; }
  .logbook-refresh { width: 100%; justify-content: center; }
  .logbook-hero__trust { gap: .6rem 1rem; }
  .logbook-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
  .logbook-metric { min-height: 96px; padding: .8rem; }
  .logbook-metric__icon { width: 38px; height: 38px; flex-basis: 38px; }
  .logbook-metric strong { font-size: 1.35rem; }
  .logbook-metric div > span { display: none; }
  .logbook-source-grid { grid-template-columns: 1fr; }
  .logbook-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .9rem; }
  .logbook-filter--search { grid-column: span 2; }
  .logbook-list-header { align-items: flex-start; flex-direction: column; }
  .logbook-mobile-list { display: grid; gap: .75rem; padding: 0 .85rem 1rem; }
  .logbook-mobile-card { position: relative; overflow: hidden; padding: 1rem; border: 1px solid #e7eaf1; border-radius: 15px; background: #fff; box-shadow: 0 6px 18px rgba(39, 50, 78, .05); }
  .logbook-mobile-card::before { position: absolute; inset: 0 auto 0 0; width: 3px; background: var(--source-accent); content: ""; }
  .logbook-mobile-card__head, .logbook-mobile-card__foot { display: flex; align-items: center; justify-content: space-between; gap: .65rem; }
  .logbook-mobile-card__head time { color: #8b94a4; font-size: .62rem; }
  .logbook-mobile-card h3 { margin: .8rem 0 .3rem; color: var(--ink); font-size: .88rem; }
  .logbook-mobile-card > p { display: -webkit-box; overflow: hidden; margin: 0 0 .75rem; color: #657085; font-size: .73rem; line-height: 1.5; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
  .logbook-mobile-card__foot { margin-top: .85rem; padding-top: .7rem; border-top: 1px solid #edf0f5; }
  .logbook-mobile-card__foot button { display: inline-flex; align-items: center; gap: .15rem; border: 0; color: #4f46e5; background: transparent; font-size: .7rem; font-weight: 750; }
  .logbook-detail__facts { grid-template-columns: repeat(2, 1fr); }
  .logbook-detail__footer { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 480px) {
  .logbook-metrics { grid-template-columns: 1fr; }
  .logbook-filter:not(.logbook-filter--search) { grid-column: span 2; }
  .logbook-detail__facts { grid-template-columns: 1fr; }
}
</style>
