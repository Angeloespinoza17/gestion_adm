<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";

const emptyFilters = () => ({
  search: "",
  owner_user_id: null,
  status: null,
  priority: null,
  stakeholder_user_id: null,
  created_by_user_id: null,
  due_date_from: "",
  due_date_to: "",
  created_scope: null,
  visibility: null,
  overdue: false,
  has_subtasks: false,
  include_subtasks: false,
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: true,
      exporting: false,
      error: null,
      showAdvancedFilters: false,
      catalogs: { priorities: [], statuses: [], users: [] },
      stats: null,
      tasks: [],
      pagination: { current_page: 1, last_page: 1, per_page: 25, total: 0, from: 0, to: 0 },
      filters: emptyFilters(),
      perPage: 25,
      sortBy: "due_date",
      sortDirection: "asc",
    };
  },
  computed: {
    isSuperAdminOverview() {
      return this.$route.path === "/tasks/all";
    },
    apiBase() {
      return this.isSuperAdminOverview ? "/api/tasks/all" : "/api/tasks/reports";
    },
    userOptions() {
      return [{ value: null, label: "Todos" }].concat((this.catalogs.users || []).map((user) => ({
        value: user.id,
        label: this.userLabel(user),
      })));
    },
    statusOptions() {
      return [{ value: null, label: "Todos los estados" }].concat((this.catalogs.statuses || []).map((item) => ({ value: item.value, label: item.label })));
    },
    createdScopeOptions() {
      return [
        { value: null, label: "Cualquier origen" },
        { value: "mine", label: "Creada por el responsable" },
        { value: "third_party", label: "Asignada por un tercero" },
      ];
    },
    visibilityOptions() {
      return [
        { value: null, label: "Privadas y compartidas" },
        { value: "private", label: "Solo privadas" },
        { value: "shared", label: "Solo compartidas" },
      ];
    },
    sortOptions() {
      return [
        { value: "due_date", label: "Fecha de vencimiento" },
        { value: "priority", label: "Prioridad" },
        { value: "status", label: "Estado" },
        { value: "title", label: "Nombre de tarea" },
        { value: "updated_at", label: "Última actualización" },
        { value: "created_at", label: "Fecha de creación" },
      ];
    },
    cards() {
      if (!this.stats) return [];
      return [
        { key: "total", label: "Tareas registradas", value: this.stats.total, icon: "bx-layer", tone: "blue" },
        { key: "progress", label: "En progreso", value: this.stats.in_progress, icon: "bx-loader-circle", tone: "indigo", status: "en_progreso" },
        { key: "overdue", label: "Vencidas", value: this.stats.overdue, icon: "bx-error-circle", tone: "red" },
        { key: "completed", label: "Completadas", value: this.stats.completed, icon: "bx-check-circle", tone: "green", status: "completada" },
      ];
    },
    priorityViews() {
      const items = (this.stats?.by_priority || []).map((item) => ({ ...item, tone: item.value }));
      return [{ value: null, label: "Todas", count: this.stats?.total || 0, tone: "all" }, ...items];
    },
    activeFilterCount() {
      return Object.values(this.filters).filter((value) => value !== null && value !== "" && value !== false).length;
    },
    activeFilterTags() {
      const tags = [];
      const add = (key, label) => tags.push({ key, label });
      if (this.filters.search) add("search", `Búsqueda: ${this.filters.search}`);
      if (this.filters.owner_user_id) add("owner_user_id", `Responsable: ${this.optionLabel(this.userOptions, this.filters.owner_user_id)}`);
      if (this.filters.status) add("status", `Estado: ${this.optionLabel(this.statusOptions, this.filters.status)}`);
      if (this.filters.priority) add("priority", `Prioridad: ${this.priorityLabel(this.filters.priority)}`);
      if (this.filters.stakeholder_user_id) add("stakeholder_user_id", `Stakeholder: ${this.optionLabel(this.userOptions, this.filters.stakeholder_user_id)}`);
      if (this.filters.created_by_user_id) add("created_by_user_id", `Creador: ${this.optionLabel(this.userOptions, this.filters.created_by_user_id)}`);
      if (this.filters.due_date_from) add("due_date_from", `Desde: ${this.formatDate(this.filters.due_date_from)}`);
      if (this.filters.due_date_to) add("due_date_to", `Hasta: ${this.formatDate(this.filters.due_date_to)}`);
      if (this.filters.created_scope) add("created_scope", this.optionLabel(this.createdScopeOptions, this.filters.created_scope));
      if (this.filters.visibility) add("visibility", this.optionLabel(this.visibilityOptions, this.filters.visibility));
      if (this.filters.overdue) add("overdue", "Solo vencidas");
      if (this.filters.has_subtasks) add("has_subtasks", "Con subtareas");
      if (this.filters.include_subtasks) add("include_subtasks", "Incluye subtareas");
      return tags;
    },
    pageNumbers() {
      const total = this.pagination.last_page || 1;
      const current = this.pagination.current_page || 1;
      let start = Math.max(1, current - 2);
      let end = Math.min(total, start + 4);
      start = Math.max(1, end - 4);
      return Array.from({ length: end - start + 1 }, (_, index) => start + index);
    },
    resultRange() {
      if (!this.pagination.total) return "0 resultados";
      return `${this.pagination.from}–${this.pagination.to} de ${this.pagination.total}`;
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.refresh();
  },
  methods: {
    params(extra = {}) {
      const payload = {
        ...this.filters,
        overdue: this.filters.overdue ? 1 : null,
        has_subtasks: this.filters.has_subtasks ? 1 : null,
        include_subtasks: this.filters.include_subtasks ? 1 : null,
        ...extra,
      };
      return Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== null && value !== "" && value !== false));
    },
    async loadCatalogs() {
      try {
        const response = await axios.get(`${this.apiBase}/catalogs`);
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.message(error);
      }
    },
    async refresh(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const listParams = this.params({
          page,
          per_page: this.perPage,
          sort_by: this.sortBy,
          sort_direction: this.sortDirection,
        });
        const statsParams = this.params();
        delete statsParams.priority;

        const [listResponse, statsResponse] = await Promise.all([
          axios.get(this.apiBase, { params: listParams }),
          axios.get(`${this.apiBase}/stats`, { params: statsParams }),
        ]);
        this.tasks = listResponse.data.data || [];
        this.pagination = {
          current_page: listResponse.data.current_page || 1,
          last_page: listResponse.data.last_page || 1,
          per_page: listResponse.data.per_page || this.perPage,
          total: listResponse.data.total || 0,
          from: listResponse.data.from || 0,
          to: listResponse.data.to || 0,
        };
        this.stats = statsResponse.data.data;
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.loading = false;
      }
    },
    clearFilters() {
      this.filters = emptyFilters();
      this.showAdvancedFilters = false;
      this.refresh();
    },
    removeFilter(key) {
      this.filters[key] = typeof this.filters[key] === "boolean" ? false : (key.includes("date") || key === "search" ? "" : null);
      this.refresh();
    },
    applyPriority(priority) {
      this.filters.priority = priority;
      this.refresh();
    },
    applyOverview(card) {
      this.filters.status = card.status || null;
      this.filters.overdue = card.key === "overdue";
      this.refresh();
    },
    overviewIsActive(card) {
      if (card.key === "overdue") return this.filters.overdue;
      if (this.filters.overdue) return false;
      return card.key === "total" ? !this.filters.status : this.filters.status === card.status;
    },
    changeSort() {
      this.refresh(1);
    },
    sortByColumn(column) {
      if (this.sortBy === column) {
        this.sortDirection = this.sortDirection === "asc" ? "desc" : "asc";
      } else {
        this.sortBy = column;
        this.sortDirection = column === "updated_at" || column === "created_at" ? "desc" : "asc";
      }
      this.refresh(1);
    },
    sortIcon(column) {
      if (this.sortBy !== column) return "bx-sort";
      return this.sortDirection === "asc" ? "bx-sort-up" : "bx-sort-down";
    },
    goToPage(page) {
      if (page < 1 || page > this.pagination.last_page || page === this.pagination.current_page) return;
      this.refresh(page);
    },
    async exportCsv() {
      this.exporting = true;
      try {
        const response = await axios.get(this.apiBase, {
          params: this.params({ per_page: "all", sort_by: this.sortBy, sort_direction: this.sortDirection }),
        });
        const rows = (response.data.data || []).map((task) => [
          task.id,
          task.title,
          this.userLabel(task.owner),
          task.priority_label,
          task.status_label,
          task.due_date || "",
          (task.stakeholders || []).map((user) => this.userLabel(user)).join(" | "),
          task.creator?.name || "",
          task.updated_at || "",
        ]);
        const header = ["ID", "Tarea", "Responsable", "Prioridad", "Estado", "Fecha de corte", "Stakeholders", "Creada por", "Actualizada"];
        const csv = [header, ...rows].map((row) => row.map(this.csvCell).join(",")).join("\n");
        const url = URL.createObjectURL(new Blob([`\ufeff${csv}`], { type: "text/csv;charset=utf-8" }));
        const link = document.createElement("a");
        link.href = url;
        link.download = `${this.isSuperAdminOverview ? "todas-las-tareas" : "reporte-tareas"}-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
      } catch (error) {
        this.error = this.message(error);
      } finally {
        this.exporting = false;
      }
    },
    csvCell(value) {
      return `"${String(value ?? "").replaceAll('"', '""')}"`;
    },
    optionLabel(options, value) {
      return options.find((option) => String(option.value) === String(value))?.label || value;
    },
    userLabel(user) {
      if (!user) return "—";
      const name = user.staff?.full_name || user.name;
      const cargo = user.staff?.cargo?.name;
      return cargo ? `${name} · ${cargo}` : name;
    },
    initials(user) {
      const name = user?.staff?.full_name || user?.name || "?";
      return name.split(/\s+/).slice(0, 2).map((part) => part[0]).join("").toUpperCase();
    },
    statusClass(status) {
      return { pendiente: "secondary", en_progreso: "primary", bloqueada: "danger", en_revision: "warning", completada: "success", cancelada: "dark" }[status] || "secondary";
    },
    priorityClass(priority) {
      return { urgente: "urgent", alta: "high", media: "medium", baja: "low" }[priority] || "medium";
    },
    priorityLabel(priority) {
      return (this.catalogs.priorities || []).find((item) => item.value === priority)?.label || priority || "—";
    },
    stakeholderNames(task) {
      return (task.stakeholders || []).map((user) => this.userLabel(user));
    },
    formatDate(value) {
      if (!value) return "Sin fecha";
      return new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString("es-CL", { day: "2-digit", month: "short", year: "numeric" });
    },
    formatDateTime(value) {
      if (!value) return "—";
      return new Date(value).toLocaleDateString("es-CL", { day: "2-digit", month: "short", year: "numeric" });
    },
    dueInfo(task) {
      if (!task.due_date) return { label: "Sin fecha", detail: "Sin vencimiento", tone: "none" };
      const due = new Date(`${String(task.due_date).slice(0, 10)}T12:00:00`);
      const today = new Date();
      today.setHours(12, 0, 0, 0);
      const days = Math.round((due.getTime() - today.getTime()) / 86400000);
      if (["completada", "cancelada"].includes(task.status)) return { label: this.formatDate(task.due_date), detail: "Cerrada", tone: "closed" };
      if (days < 0) return { label: this.formatDate(task.due_date), detail: `Vencida hace ${Math.abs(days)} d`, tone: "overdue" };
      if (days === 0) return { label: this.formatDate(task.due_date), detail: "Vence hoy", tone: "today" };
      if (days <= 7) return { label: this.formatDate(task.due_date), detail: `En ${days} d`, tone: "soon" };
      return { label: this.formatDate(task.due_date), detail: `En ${days} d`, tone: "normal" };
    },
    message(error) {
      return error?.response?.data?.message || error?.message || "No fue posible cargar las tareas.";
    },
  },
};
</script>

<template>
  <Layout>
    <section class="report-hero mb-3">
      <div>
        <span class="report-eyebrow"><i class="bx bx-shield-quarter"></i> {{ isSuperAdminOverview ? "Vista exclusiva superadmin" : "Acceso restringido" }}</span>
        <h2>{{ isSuperAdminOverview ? "Todas las tareas" : "Reportes de tareas" }}</h2>
        <p>{{ isSuperAdminOverview ? "Supervisa todos los backlogs institucionales desde un único espacio operativo." : "Analiza carga, cumplimiento y prioridades sin modificar los backlogs personales." }}</p>
      </div>
      <div class="report-hero-actions">
        <BButton variant="light" @click="$router.push('/tasks/backlog')"><i class="bx bx-arrow-back me-1"></i>Mi backlog</BButton>
        <BButton variant="warning" :disabled="exporting" @click="exportCsv"><i class="bx bx-download me-1"></i>{{ exporting ? "Exportando..." : "Exportar CSV" }}</BButton>
      </div>
    </section>

    <BAlert v-if="error" variant="danger" show dismissible @dismissed="error = null">{{ error }}</BAlert>

    <section class="report-summary mb-3">
      <button
        v-for="card in cards"
        :key="card.key"
        type="button"
        class="report-stat"
        :class="[`report-stat--${card.tone}`, { active: overviewIsActive(card) }]"
        @click="applyOverview(card)"
      >
        <i class="bx" :class="card.icon"></i>
        <span><small>{{ card.label }}</small><strong>{{ card.value }}</strong></span>
        <i class="bx bx-chevron-right report-stat-arrow"></i>
      </button>
      <article class="report-progress-card">
        <div><span>Avance global</span><strong>{{ stats?.global_progress || 0 }}%</strong></div>
        <div class="progress"><div class="progress-bar" :style="{ width: `${stats?.global_progress || 0}%` }"></div></div>
        <small>Promedio de cierre: {{ stats?.average_days_to_complete === null || !stats ? "—" : `${stats.average_days_to_complete} días` }}</small>
      </article>
    </section>

    <section class="priority-view mb-3">
      <div class="priority-view-title">
        <i class="bx bx-flag"></i>
        <div><strong>Vista por prioridad</strong><small>Cambia el foco del listado con un clic.</small></div>
      </div>
      <div class="priority-tabs">
        <button
          v-for="item in priorityViews"
          :key="item.value || 'all'"
          type="button"
          :class="[`priority-tab--${item.tone}`, { active: filters.priority === item.value }]"
          @click="applyPriority(item.value)"
        >
          <span class="priority-dot"></span>{{ item.label }}<strong>{{ item.count }}</strong>
        </button>
      </div>
    </section>

    <BCard no-body class="report-panel report-filter-panel mb-3">
      <BCardBody>
        <div class="report-filter-toolbar">
          <div class="report-search">
            <i class="bx bx-search"></i>
            <BFormInput v-model="filters.search" placeholder="Buscar por tarea, responsable o creador..." aria-label="Buscar tareas" @keyup.enter="refresh()" />
          </div>
          <div class="report-filter-select"><Multiselect v-model="filters.owner_user_id" :options="userOptions" :searchable="true" placeholder="Responsable" /></div>
          <div class="report-filter-select"><Multiselect v-model="filters.status" :options="statusOptions" placeholder="Estado" /></div>
          <BButton variant="outline-secondary" class="more-filter-button" @click="showAdvancedFilters = !showAdvancedFilters">
            <i class="bx bx-slider-alt me-1"></i>Más filtros
            <span v-if="activeFilterCount" class="filter-count">{{ activeFilterCount }}</span>
            <i class="bx ms-1" :class="showAdvancedFilters ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
          </BButton>
          <BButton variant="primary" @click="refresh()"><i class="bx bx-filter-alt me-1"></i>Aplicar</BButton>
          <BButton v-if="activeFilterCount" variant="link" class="clear-filter-button" @click="clearFilters">Limpiar</BButton>
        </div>

        <div v-show="showAdvancedFilters" class="report-advanced-filters">
          <div class="row g-3 align-items-end">
            <div class="col-md-4 col-xl-3"><label class="form-label">Creador</label><Multiselect v-model="filters.created_by_user_id" :options="userOptions" :searchable="true" /></div>
            <div class="col-md-4 col-xl-3"><label class="form-label">Stakeholder</label><Multiselect v-model="filters.stakeholder_user_id" :options="userOptions" :searchable="true" /></div>
            <div class="col-md-4 col-xl-3"><label class="form-label">Origen</label><Multiselect v-model="filters.created_scope" :options="createdScopeOptions" /></div>
            <div class="col-md-4 col-xl-3"><label class="form-label">Visibilidad</label><Multiselect v-model="filters.visibility" :options="visibilityOptions" /></div>
            <div class="col-md-4 col-xl-3"><label class="form-label">Vence desde</label><BFormInput v-model="filters.due_date_from" type="date" /></div>
            <div class="col-md-4 col-xl-3"><label class="form-label">Vence hasta</label><BFormInput v-model="filters.due_date_to" type="date" /></div>
            <div class="col-md-8 col-xl-6 report-check-filters">
              <BFormCheckbox v-model="filters.overdue">Solo vencidas</BFormCheckbox>
              <BFormCheckbox v-model="filters.has_subtasks">Con subtareas</BFormCheckbox>
              <BFormCheckbox v-model="filters.include_subtasks">Incluir subtareas</BFormCheckbox>
            </div>
          </div>
        </div>

        <div v-if="activeFilterTags.length" class="active-filter-list">
          <span v-for="tag in activeFilterTags" :key="tag.key">{{ tag.label }}<button type="button" :aria-label="`Quitar ${tag.label}`" @click="removeFilter(tag.key)"><i class="bx bx-x"></i></button></span>
        </div>
      </BCardBody>
    </BCard>

    <BCard no-body class="report-panel report-table-panel">
      <div class="report-table-toolbar">
        <div>
          <span class="report-section-kicker">Detalle institucional</span>
          <h5>Listado de tareas <span>{{ pagination.total }}</span></h5>
          <small>Mostrando {{ resultRange }}</small>
        </div>
        <div class="table-controls">
          <label>Ordenar por
            <select v-model="sortBy" class="form-select form-select-sm" @change="changeSort">
              <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <button type="button" class="sort-direction" :aria-label="sortDirection === 'asc' ? 'Orden ascendente' : 'Orden descendente'" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; changeSort()">
            <i class="bx" :class="sortDirection === 'asc' ? 'bx-sort-up' : 'bx-sort-down'"></i>
          </button>
          <label>Filas
            <select v-model.number="perPage" class="form-select form-select-sm" @change="refresh(1)">
              <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option>
            </select>
          </label>
        </div>
      </div>

      <LoadingState v-if="loading" message="Cargando tareas institucionales..." compact />
      <div v-else-if="tasks.length" class="table-responsive report-table-wrap">
        <table class="table align-middle report-table mb-0">
          <thead>
            <tr>
              <th><button type="button" @click="sortByColumn('title')">Tarea <i class="bx" :class="sortIcon('title')"></i></button></th>
              <th>Responsable</th>
              <th><button type="button" @click="sortByColumn('priority')">Prioridad <i class="bx" :class="sortIcon('priority')"></i></button></th>
              <th><button type="button" @click="sortByColumn('status')">Estado <i class="bx" :class="sortIcon('status')"></i></button></th>
              <th><button type="button" @click="sortByColumn('due_date')">Vencimiento <i class="bx" :class="sortIcon('due_date')"></i></button></th>
              <th>Colaboración</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="task in tasks" :key="task.id" :class="{ 'task-row-overdue': task.is_overdue }">
              <td class="task-main-cell">
                <strong>{{ task.title }}</strong>
                <small><span>#{{ task.id }}</span> · {{ task.creator?.name || "—" }} · Act. {{ formatDateTime(task.updated_at) }}</small>
              </td>
              <td>
                <div class="owner-cell"><span class="owner-avatar">{{ initials(task.owner) }}</span><span><strong>{{ task.owner?.staff?.full_name || task.owner?.name || "—" }}</strong><small>{{ task.owner?.staff?.cargo?.name || task.owner?.email || "" }}</small></span></div>
              </td>
              <td><span class="priority-pill" :class="`priority-pill--${priorityClass(task.priority)}`"><i class="bx bx-flag"></i>{{ task.priority_label }}</span></td>
              <td><span class="badge rounded-pill status-pill" :class="`badge-soft-${statusClass(task.status)}`">{{ task.status_label }}</span></td>
              <td>
                <div class="due-cell" :class="`due-cell--${dueInfo(task).tone}`"><strong>{{ dueInfo(task).label }}</strong><small>{{ dueInfo(task).detail }}</small></div>
              </td>
              <td>
                <span v-if="!stakeholderNames(task).length" class="privacy-pill"><i class="bx bx-lock-alt"></i>Privada</span>
                <div v-else class="stakeholder-cell"><span><i class="bx bx-group"></i>{{ stakeholderNames(task).length }} stakeholder{{ stakeholderNames(task).length === 1 ? "" : "s" }}</span><small>{{ stakeholderNames(task).slice(0, 2).join(", ") }}<template v-if="stakeholderNames(task).length > 2"> +{{ stakeholderNames(task).length - 2 }}</template></small></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="report-empty">
        <span><i class="bx bx-search-alt"></i></span>
        <h5>No encontramos tareas</h5>
        <p>Prueba cambiando la prioridad o quitando algunos filtros.</p>
        <BButton variant="outline-secondary" @click="clearFilters">Limpiar filtros</BButton>
      </div>

      <div v-if="!loading && pagination.total" class="report-pagination">
        <span>{{ resultRange }}</span>
        <nav aria-label="Paginación de tareas">
          <button type="button" aria-label="Página anterior" :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
          <button v-if="pageNumbers[0] > 1" type="button" @click="goToPage(1)">1</button>
          <span v-if="pageNumbers[0] > 2">…</span>
          <button v-for="page in pageNumbers" :key="page" type="button" :class="{ active: page === pagination.current_page }" @click="goToPage(page)">{{ page }}</button>
          <span v-if="pageNumbers[pageNumbers.length - 1] < pagination.last_page - 1">…</span>
          <button v-if="pageNumbers[pageNumbers.length - 1] < pagination.last_page" type="button" @click="goToPage(pagination.last_page)">{{ pagination.last_page }}</button>
          <button type="button" aria-label="Página siguiente" :disabled="pagination.current_page >= pagination.last_page" @click="goToPage(pagination.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
        </nav>
        <span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
      </div>
    </BCard>

    <div v-if="!isSuperAdminOverview && stats" class="row g-3 mt-1">
      <div class="col-lg-7">
        <BCard no-body class="report-panel h-100"><BCardBody>
          <h5>Distribución por estado</h5>
          <div v-for="item in stats.by_status" :key="item.value" class="metric-row"><span>{{ item.label }}</span><div class="progress"><div class="progress-bar" :class="`bg-${statusClass(item.value)}`" :style="{ width: `${stats.total ? (item.count / stats.total) * 100 : 0}%` }"></div></div><strong>{{ item.count }}</strong></div>
        </BCardBody></BCard>
      </div>
      <div class="col-lg-5">
        <BCard no-body class="report-panel h-100"><BCardBody>
          <h5>Stakeholders con mayor participación</h5>
          <div v-for="item in (stats.by_stakeholder || []).slice(0, 7)" :key="item.label" class="stakeholder-row"><span>{{ item.label }}</span><strong>{{ item.count }}</strong></div>
          <p v-if="!(stats.by_stakeholder || []).length" class="text-muted mb-0">Sin datos para los filtros seleccionados.</p>
        </BCardBody></BCard>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.report-hero { position:relative; overflow:hidden; display:flex; justify-content:space-between; align-items:center; gap:20px; padding:22px 26px; border-radius:18px; color:#fff; background:linear-gradient(125deg,#111827,#312e81 62%,#4338ca); box-shadow:0 14px 34px rgba(49,46,129,.18); }
.report-hero::after { content:""; position:absolute; width:240px; height:240px; right:-70px; top:-125px; border-radius:50%; background:rgba(255,255,255,.07); }
.report-hero h2 { margin:5px 0 4px; color:#fff; font-size:1.65rem; }
.report-hero p { max-width:720px; margin:0; color:rgba(255,255,255,.74); }
.report-eyebrow { color:#c7d2fe; font-size:.72rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.report-hero-actions { position:relative; z-index:1; display:flex; flex-shrink:0; gap:9px; }

.report-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)) minmax(190px,1.25fr); gap:11px; }
.report-stat { display:flex; align-items:center; gap:10px; min-width:0; padding:12px 13px; text-align:left; border:1px solid #e6eaf1; border-radius:13px; background:#fff; box-shadow:0 4px 16px rgba(15,23,42,.04); transition:.16s ease; }
.report-stat:hover { transform:translateY(-1px); border-color:#b9c7e9; box-shadow:0 8px 22px rgba(30,64,175,.08); }
.report-stat.active { border-color:#5b78df; box-shadow:0 0 0 2px rgba(79,70,229,.12); }
.report-stat > i:first-child { display:grid; place-items:center; width:38px; height:38px; flex:0 0 38px; border-radius:11px; color:#2563eb; background:#eff6ff; font-size:1.2rem; }
.report-stat > span { display:flex; min-width:0; flex:1; flex-direction:column; }
.report-stat small { overflow:hidden; color:#6c7890; font-size:.71rem; text-overflow:ellipsis; white-space:nowrap; }
.report-stat strong { color:#172554; font-size:1.22rem; line-height:1.1; }
.report-stat-arrow { color:#a9b2c1; }
.report-stat--red > i:first-child { color:#dc2626; background:#fef2f2; }.report-stat--green > i:first-child { color:#059669; background:#ecfdf5; }.report-stat--indigo > i:first-child { color:#4f46e5; background:#eef2ff; }
.report-progress-card { display:flex; justify-content:center; flex-direction:column; padding:11px 14px; border:1px solid #dfe6ef; border-radius:13px; background:linear-gradient(135deg,#f8fafc,#f1f5ff); }
.report-progress-card > div:first-child { display:flex; justify-content:space-between; color:#637086; font-size:.73rem; }.report-progress-card > div strong { color:#263c7a; font-size:1rem; }.report-progress-card .progress { height:6px; margin:6px 0; background:#e3e8f2; }.report-progress-card .progress-bar { background:linear-gradient(90deg,#4f46e5,#2563eb); }.report-progress-card > small { color:#8290a4; font-size:.67rem; }

.priority-view { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:12px 14px; border:1px solid #e1e6ee; border-radius:13px; background:#fff; }
.priority-view-title { display:flex; align-items:center; flex:0 0 auto; gap:9px; }.priority-view-title > i { display:grid; width:35px; height:35px; place-items:center; color:#4f46e5; border-radius:10px; background:#eef2ff; font-size:1.1rem; }.priority-view-title strong,.priority-view-title small { display:block; }.priority-view-title strong { color:#26364d; font-size:.83rem; }.priority-view-title small { color:#8792a4; font-size:.68rem; }
.priority-tabs { display:flex; min-width:0; gap:7px; overflow-x:auto; padding:2px; }
.priority-tabs button { display:inline-flex; align-items:center; flex:0 0 auto; gap:7px; padding:7px 10px; color:#5f6d82; border:1px solid #e2e7ee; border-radius:9px; background:#fafbfc; font-size:.76rem; font-weight:600; }
.priority-tabs button:hover,.priority-tabs button.active { color:#263c7a; border-color:#aebfeb; background:#f1f5ff; }.priority-tabs button strong { display:grid; min-width:20px; height:20px; padding:0 5px; place-items:center; border-radius:999px; color:#64748b; background:#e9edf3; font-size:.67rem; }.priority-dot { width:7px; height:7px; border-radius:50%; background:#94a3b8; }.priority-tab--urgente .priority-dot { background:#dc3545; }.priority-tab--alta .priority-dot { background:#f59f00; }.priority-tab--media .priority-dot { background:#339af0; }.priority-tab--baja .priority-dot { background:#2f9e44; }

.report-panel { border:1px solid #e3e8ef; border-radius:14px; box-shadow:0 5px 20px rgba(15,23,42,.04); }
.report-filter-panel :deep(.card-body) { padding:13px 14px; }
.report-filter-toolbar { display:grid; grid-template-columns:minmax(280px,1.35fr) minmax(190px,.7fr) minmax(170px,.55fr) auto auto auto; align-items:center; gap:9px; }
.report-search { position:relative; }.report-search > i { position:absolute; z-index:2; top:50%; left:13px; color:#8a96a8; font-size:1.08rem; transform:translateY(-50%); pointer-events:none; }.report-search :deep(.form-control) { height:40px; padding-left:38px; border-color:#dfe5ed; border-radius:9px; }
.report-filter-select :deep(.multiselect) { min-height:40px; border-color:#dfe5ed; border-radius:9px; }.more-filter-button { display:inline-flex; align-items:center; white-space:nowrap; }.filter-count { display:inline-grid; min-width:19px; height:19px; margin-left:6px; padding:0 5px; place-items:center; border-radius:999px; color:#fff; background:#465fca; font-size:.66rem; }.clear-filter-button { padding-right:3px; padding-left:3px; text-decoration:none; white-space:nowrap; }
.report-advanced-filters { margin-top:14px; padding-top:14px; border-top:1px solid #edf0f4; }.report-advanced-filters .form-label { margin-bottom:5px; color:#59677a; font-size:.75rem; font-weight:650; }.report-check-filters { display:flex; align-items:center; gap:20px; min-height:40px; padding-bottom:8px; }
.active-filter-list { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; padding-top:11px; border-top:1px solid #edf0f4; }.active-filter-list > span { display:inline-flex; align-items:center; gap:4px; padding:4px 5px 4px 9px; color:#40516b; border:1px solid #dce4f2; border-radius:999px; background:#f5f8fd; font-size:.7rem; }.active-filter-list button { display:grid; width:18px; height:18px; padding:0; place-items:center; color:#718096; border:0; border-radius:50%; background:#e5eaf2; }

.report-table-panel { overflow:hidden; }.report-table-toolbar { display:flex; align-items:center; justify-content:space-between; gap:18px; padding:16px 18px; border-bottom:1px solid #e8edf3; }.report-section-kicker { display:block; margin-bottom:2px; color:#4f46e5; font-size:.67rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }.report-table-toolbar h5 { margin:0; color:#26364d; }.report-table-toolbar h5 span { display:inline-grid; min-width:24px; height:24px; margin-left:4px; padding:0 6px; place-items:center; border-radius:999px; color:#4c5d75; background:#edf1f6; font-size:.7rem; }.report-table-toolbar > div:first-child > small { color:#8390a3; font-size:.72rem; }
.table-controls { display:flex; align-items:flex-end; gap:8px; }.table-controls label { display:flex; gap:5px; flex-direction:column; color:#7b8798; font-size:.66rem; font-weight:600; }.table-controls label:first-child .form-select { min-width:175px; }.table-controls .form-select { height:34px; border-color:#dce2ea; border-radius:8px; font-size:.75rem; }.sort-direction { display:grid; width:34px; height:34px; place-items:center; color:#52627b; border:1px solid #dce2ea; border-radius:8px; background:#fff; font-size:1.05rem; }
.report-table-wrap { width:100%; }.report-table { min-width:900px; }.report-table thead th { padding:11px 12px; color:#6b7890; border-bottom:1px solid #dfe5ed; background:#f7f9fc; font-size:.67rem; letter-spacing:.045em; text-transform:uppercase; white-space:nowrap; }.report-table thead th button { display:inline-flex; align-items:center; gap:5px; padding:0; color:inherit; border:0; background:transparent; font:inherit; letter-spacing:inherit; text-transform:inherit; }.report-table tbody td { padding:13px 12px; color:#34435a; border-bottom-color:#edf0f4; font-size:.78rem; }.report-table tbody tr:last-child td { border-bottom:0; }.report-table tbody tr:hover td { background:#f7faff; }.report-table tbody tr.task-row-overdue td { background:#fffafb; }.report-table tbody tr.task-row-overdue:hover td { background:#fff4f5; }
.task-main-cell { min-width:210px; }.task-main-cell strong,.task-main-cell small { display:block; }.task-main-cell > strong { max-width:280px; overflow:hidden; color:#26364d; font-size:.82rem; text-overflow:ellipsis; white-space:nowrap; }.task-main-cell small { max-width:280px; margin-top:4px; overflow:hidden; color:#8a96a8; font-size:.68rem; text-overflow:ellipsis; white-space:nowrap; }.task-main-cell small span { color:#60708a; font-weight:700; }
.owner-cell { display:flex; align-items:center; min-width:165px; gap:9px; }.owner-avatar { display:grid; width:32px; height:32px; flex:0 0 32px; place-items:center; color:#3e56b3; border-radius:9px; background:#edf1ff; font-size:.67rem; font-weight:800; }.owner-cell > span:last-child { min-width:0; }.owner-cell strong,.owner-cell small { display:block; max-width:155px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.owner-cell strong { color:#34435a; font-size:.76rem; }.owner-cell small { margin-top:2px; color:#8a96a8; font-size:.66rem; }
.priority-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 8px; border-radius:999px; font-size:.7rem; font-weight:700; }.priority-pill--urgent { color:#b4232f; background:#fdebed; }.priority-pill--high { color:#a56000; background:#fff3db; }.priority-pill--medium { color:#1769a5; background:#e8f4fc; }.priority-pill--low { color:#197044; background:#e8f7ef; }.status-pill { padding:5px 9px; font-size:.69rem; }
.due-cell strong,.due-cell small { display:block; white-space:nowrap; }.due-cell strong { color:#425168; font-size:.74rem; }.due-cell small { margin-top:2px; color:#8a96a8; font-size:.66rem; }.due-cell--overdue strong,.due-cell--overdue small { color:#c92a3a; }.due-cell--today strong,.due-cell--today small { color:#d97706; }.due-cell--soon small { color:#b7791f; }.due-cell--none strong { color:#8793a6; font-weight:500; }
.privacy-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 8px; color:#66758a; border-radius:999px; background:#f0f3f7; font-size:.69rem; }.stakeholder-cell { min-width:140px; }.stakeholder-cell span,.stakeholder-cell small { display:block; }.stakeholder-cell span { color:#40516b; font-size:.72rem; font-weight:700; }.stakeholder-cell small { max-width:165px; margin-top:3px; overflow:hidden; color:#8a96a8; font-size:.65rem; text-overflow:ellipsis; white-space:nowrap; }
.report-empty { display:flex; min-height:270px; align-items:center; justify-content:center; flex-direction:column; padding:34px 20px; text-align:center; }.report-empty > span { display:grid; width:52px; height:52px; margin-bottom:12px; place-items:center; color:#4f46e5; border-radius:15px; background:#eef2ff; font-size:1.7rem; }.report-empty h5 { margin-bottom:4px; color:#2f3e54; }.report-empty p { margin-bottom:15px; color:#8491a4; }
.report-pagination { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:14px; padding:13px 18px; color:#7b8798; border-top:1px solid #e8edf3; background:#fafbfc; font-size:.7rem; }.report-pagination > span:last-child { text-align:right; }.report-pagination nav { display:flex; align-items:center; gap:4px; }.report-pagination nav button { display:grid; min-width:31px; height:31px; padding:0 7px; place-items:center; color:#526179; border:1px solid #dfe4eb; border-radius:8px; background:#fff; }.report-pagination nav button:hover:not(:disabled),.report-pagination nav button.active { color:#fff; border-color:#4f5fc4; background:#4f5fc4; }.report-pagination nav button:disabled { opacity:.45; cursor:not-allowed; }.report-pagination nav > span { padding:0 3px; }
.metric-row { display:grid; grid-template-columns:120px 1fr 32px; align-items:center; gap:12px; padding:8px 0; }.metric-row .progress { height:8px; background:#eef2f7; }.stakeholder-row { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #eef2f7; }.stakeholder-row:last-child { border:0; }

@media(max-width:1199.98px){.report-summary{grid-template-columns:repeat(4,minmax(0,1fr))}.report-progress-card{grid-column:1/-1}.report-filter-toolbar{grid-template-columns:minmax(250px,1fr) minmax(180px,.7fr) minmax(160px,.6fr) auto auto}.clear-filter-button{grid-column:1/-1;justify-self:start}.priority-view{align-items:flex-start;flex-direction:column}.priority-tabs{width:100%}}
@media(max-width:991.98px){.report-hero{align-items:flex-start;flex-direction:column;padding:22px}.report-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.report-progress-card{grid-column:1/-1}.report-filter-toolbar{grid-template-columns:1fr 1fr}.report-search{grid-column:1/-1}.report-table-toolbar{align-items:flex-start;flex-direction:column}.table-controls{width:100%;flex-wrap:wrap}.metric-row{grid-template-columns:95px 1fr 28px}}
@media(max-width:767.98px){.report-hero-actions{width:100%;flex-wrap:wrap}.report-hero-actions .btn{flex:1 1 calc(50% - 5px);white-space:nowrap}.report-check-filters{align-items:flex-start;flex-direction:column;gap:9px}.report-pagination{grid-template-columns:1fr;justify-items:center}.report-pagination>span:last-child{text-align:center}.report-table-toolbar{padding:14px}.table-controls label:first-child{flex:1}.table-controls label:first-child .form-select{width:100%;min-width:160px}}
@media(max-width:479.98px){.report-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.report-stat{gap:7px;padding:10px}.report-stat>i:first-child{width:34px;height:34px;flex-basis:34px}.report-stat-arrow{display:none}.report-stat small{white-space:normal}.report-filter-toolbar{grid-template-columns:1fr}.report-filter-toolbar>*{width:100%}.report-filter-toolbar>.btn{justify-content:center}.priority-view{padding:11px}.table-controls{display:grid;grid-template-columns:1fr 36px 72px}.table-controls label:first-child .form-select{min-width:0}.report-pagination nav{max-width:100%;overflow-x:auto}}
</style>
