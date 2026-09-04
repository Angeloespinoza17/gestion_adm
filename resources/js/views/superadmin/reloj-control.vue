<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { downloadRelojControlPdf } from "../../utils/reloj-control-report-pdf";
import { downloadRelojControlAnalyticsPdf } from "../../utils/reloj-control-analytics-pdf";

const isoDate = (date) => {
  const local = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
  return local.toISOString().slice(0, 10);
};

export default {
  components: { Layout, LoadingState },
  data() {
    const today = new Date();
    const weekAgo = new Date(today);
    weekAgo.setDate(today.getDate() - 6);

    return {
      loadingUsers: false,
      querying: false,
      exportingPdf: false,
      reporting: false,
      exportingReportPdf: false,
      catalogError: "",
      queryError: "",
      users: [],
      catalogSummary: {
        total: 0,
        active: 0,
        inactive: 0,
        linked: 0,
        unlinked: 0,
        provider_total: 0,
        provider_only: 0,
        provider_available: null,
      },
      catalogFetchedAt: null,
      userFilters: { search: "", status: "" },
      selectedIds: [],
      query: { date_from: isoDate(weekAgo), date_to: isoDate(today) },
      result: null,
      currentPage: 1,
      perPage: 25,
      selectedRow: null,
      showDetail: false,
      showPeopleSelector: false,
      activeWorkspace: "attendance",
      activeReport: "general",
      reportError: "",
      reportResult: null,
      reportSearch: "",
      reportQuery: {
        date_from: isoDate(weekAgo),
        date_to: isoDate(today),
        scope: "all_linked",
        tolerance_minutes: 5,
      },
    };
  },
  computed: {
    filteredUsers() {
      const search = this.normalizeText(this.userFilters.search);
      const enabled = this.userFilters.status === "active";

      return this.users.filter((user) => {
        if (this.userFilters.status && user.enabled !== enabled) return false;
        if (!search) return true;
        return this.normalizeText([
          user.name,
          user.rut,
          user.email,
          user.group,
          user.position,
        ].join(" ")).includes(search);
      });
    },
    selectedUsers() {
      const selected = new Set(this.selectedIds);
      return this.users.filter((user) => selected.has(user.id));
    },
    selectableFilteredUsers() {
      return this.filteredUsers.filter((user) => user.geovictoria_linked);
    },
    allVisibleSelected() {
      return this.selectableFilteredUsers.length > 0
        && this.selectableFilteredUsers.every((user) => this.selectedIds.includes(user.id));
    },
    canQuery() {
      return this.selectedIds.length > 0
        && this.query.date_from
        && this.query.date_to
        && !this.querying;
    },
    rows() {
      return this.result?.data || [];
    },
    weeklyRows() {
      return this.result?.weekly || [];
    },
    paginatedRows() {
      const start = (this.currentPage - 1) * this.perPage;
      return this.rows.slice(start, start + this.perPage);
    },
    summary() {
      return this.result?.summary || {
        returned_users: 0,
        days: 0,
        punches: 0,
        absences: 0,
        worked_time_label: "0 h",
      };
    },
    periodLabel() {
      if (!this.result?.period) return "Aún no se ha ejecutado una consulta";
      return `${this.formatShortDate(this.result.period.date_from)} al ${this.formatShortDate(this.result.period.date_to)}`;
    },
    providerState() {
      if (this.catalogError || this.catalogSummary.provider_available === false) {
        return { label: "Conexión pendiente", error: true };
      }
      if (this.catalogSummary.provider_total > 0 && this.catalogSummary.linked === 0) {
        return { label: "Sin coincidencias", error: true };
      }
      return { label: "Integración operativa", error: false };
    },
    reportSummary() {
      return this.reportResult?.summary || {
        requested_users: 0,
        returned_users: 0,
        worked_time_label: "0 h",
        tardy_people: 0,
        tardiness_occurrences: 0,
        tardiness_minutes: 0,
        absent_people: 0,
        absences: 0,
      };
    },
    reportTabs() {
      return [
        { key: "general", label: "General", icon: "bx-bar-chart-alt-2", count: this.reportResult?.reports?.by_group?.length || 0 },
        { key: "tardiness", label: "Atrasos", icon: "bx-time-five", count: this.reportResult?.reports?.tardiness?.length || 0 },
        { key: "balances", label: "Balance de minutos", icon: "bx-transfer-alt", count: this.reportResult?.reports?.balances?.length || 0 },
        { key: "absences", label: "Ausencias", icon: "bx-user-x", count: this.reportResult?.reports?.absences?.length || 0 },
      ];
    },
    activeReportRows() {
      if (!this.reportResult) return [];
      const key = this.activeReport === "general" ? "by_group" : this.activeReport;
      const rows = this.reportResult.reports?.[key] || [];
      const search = this.normalizeText(this.reportSearch);
      if (!search) return rows;
      return rows.filter((row) => this.normalizeText([
        row.name,
        row.user?.name,
        row.user?.rut,
        row.user?.position,
        row.user?.group,
      ].join(" ")).includes(search));
    },
  },
  mounted() {
    this.loadUsers();
  },
  methods: {
    async loadUsers(force = false) {
      this.loadingUsers = true;
      this.catalogError = "";

      try {
        const { data } = await axios.get("/api/superadmin/reloj-control/users", {
          params: force ? { refresh: 1 } : {},
        });
        this.users = data.data || [];
        this.catalogSummary = data.summary || this.catalogSummary;
        this.catalogFetchedAt = data.fetched_at || null;
        const available = new Set(this.users
          .filter((user) => user.geovictoria_linked)
          .map((user) => user.id));
        this.selectedIds = this.selectedIds.filter((staffId) => available.has(staffId));
      } catch (error) {
        this.catalogError = this.errorMessage(error, "No fue posible cargar los colaboradores desde GeoVictoria.");
      } finally {
        this.loadingUsers = false;
      }
    },
    async runQuery() {
      if (!this.canQuery) return;
      this.querying = true;
      this.queryError = "";

      try {
        const { data } = await axios.post("/api/superadmin/reloj-control/attendance", {
          ...this.query,
          staff_ids: this.selectedIds,
        });
        this.result = data;
        this.currentPage = 1;
        this.showPeopleSelector = false;
      } catch (error) {
        this.queryError = this.errorMessage(error, "GeoVictoria no pudo completar la consulta de asistencia.");
      } finally {
        this.querying = false;
      }
    },
    toggleUser(user) {
      if (!user.geovictoria_linked) return;
      if (this.selectedIds.includes(user.id)) {
        this.selectedIds = this.selectedIds.filter((item) => item !== user.id);
        return;
      }

      if (this.selectedIds.length >= 50) {
        this.queryError = "Puedes consultar hasta 50 colaboradores a la vez.";
        return;
      }

      this.selectedIds = [...this.selectedIds, user.id];
      this.queryError = "";
    },
    toggleVisibleUsers() {
      const visibleIds = this.selectableFilteredUsers.map((user) => user.id);
      if (this.allVisibleSelected) {
        const visible = new Set(visibleIds);
        this.selectedIds = this.selectedIds.filter((identifier) => !visible.has(identifier));
        return;
      }

      this.selectedIds = Array.from(new Set([...this.selectedIds, ...visibleIds])).slice(0, 50);
    },
    clearSelection() {
      this.selectedIds = [];
    },
    removeSelected(staffId) {
      this.selectedIds = this.selectedIds.filter((item) => item !== staffId);
    },
    async exportPdf() {
      if (!this.result || this.exportingPdf) return;
      this.exportingPdf = true;
      this.queryError = "";

      try {
        await downloadRelojControlPdf(this.result);
      } catch (error) {
        this.queryError = "No fue posible generar el PDF de Reloj Control.";
      } finally {
        this.exportingPdf = false;
      }
    },
    async runReports() {
      if (this.reporting) return;
      if (this.reportQuery.scope === "selected" && !this.selectedIds.length) {
        this.reportError = "Selecciona funcionarios en Consulta diaria o usa toda la dotación vinculada.";
        return;
      }

      this.reporting = true;
      this.reportError = "";

      try {
        const payload = {
          ...this.reportQuery,
          tolerance_minutes: Number(this.reportQuery.tolerance_minutes || 0),
        };
        if (payload.scope === "selected") payload.staff_ids = this.selectedIds;
        const { data } = await axios.post("/api/superadmin/reloj-control/reports", payload);
        this.reportResult = data;
        this.activeReport = "general";
      } catch (error) {
        this.reportError = this.errorMessage(error, "No fue posible generar los reportes desde GeoVictoria.");
      } finally {
        this.reporting = false;
      }
    },
    async exportReportsPdf() {
      if (!this.reportResult || this.exportingReportPdf) return;
      this.exportingReportPdf = true;
      this.reportError = "";

      try {
        await downloadRelojControlAnalyticsPdf(this.reportResult);
      } catch (error) {
        this.reportError = "No fue posible generar el PDF consolidado de reportes.";
      } finally {
        this.exportingReportPdf = false;
      }
    },
    openDetail(row) {
      this.selectedRow = row;
      this.showDetail = true;
    },
    normalizeText(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
    },
    errorMessage(error, fallback) {
      const validation = error.response?.data?.errors;
      if (validation) {
        const first = Object.values(validation).flat().find(Boolean);
        if (first) return first;
      }
      return error.response?.data?.message || fallback;
    },
    parseProviderDate(value) {
      if (!value) return null;
      const raw = String(value);
      const dotNet = raw.match(/\/Date\((\d+)/);
      if (dotNet) return new Date(Number(dotNet[1]));
      if (/^\d{14}$/.test(raw)) {
        return new Date(`${raw.slice(0, 4)}-${raw.slice(4, 6)}-${raw.slice(6, 8)}T${raw.slice(8, 10)}:${raw.slice(10, 12)}:${raw.slice(12, 14)}`);
      }
      const date = new Date(raw.includes("T") ? raw : raw.replace(" ", "T"));
      return Number.isNaN(date.getTime()) ? null : date;
    },
    formatDate(value) {
      const date = this.parseProviderDate(value);
      if (!date) return value || "Sin fecha";
      return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium" }).format(date);
    },
    formatShortDate(value) {
      if (!value) return "—";
      const [year, month, day] = String(value).slice(0, 10).split("-");
      if (!year || !month || !day) return this.formatDate(value);
      return `${day}-${month}-${year}`;
    },
    formatTime(value) {
      const date = this.parseProviderDate(value);
      if (!date) return value || "—";
      return new Intl.DateTimeFormat("es-CL", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      }).format(date);
    },
    formatSynced(value) {
      const date = this.parseProviderDate(value);
      if (!date) return "Sin sincronizar";
      return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "short",
        timeStyle: "short",
      }).format(date);
    },
    initials(name) {
      return String(name || "GV")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },
    rowStatus(row) {
      if (row.absent) return { label: "Ausente", class: "clock-status--danger", icon: "bx-x-circle" };
      if (row.time_offs?.length) return { label: "Con permiso", class: "clock-status--warning", icon: "bx-calendar-exclamation" };
      if (row.holiday) return { label: "Vacaciones", class: "clock-status--violet", icon: "bx-sun" };
      if (row.worked) return { label: "Jornada registrada", class: "clock-status--success", icon: "bx-check-circle" };
      return { label: "Sin actividad", class: "clock-status--muted", icon: "bx-minus-circle" };
    },
    shiftLabel(row) {
      const shift = row.shifts?.find((item) => {
        const name = this.normalizeText(item?.name);
        return !["break", "colacion", "descanso"].some((term) => name.includes(term));
      });
      return shift?.name || "Sin turno informado";
    },
    deviationClass(minutes, moment) {
      if (minutes === null || minutes === undefined) return "clock-deviation--muted";
      if (minutes === 0) return "clock-deviation--exact";
      const isPositive = moment === "entry" ? minutes < 0 : minutes > 0;
      return isPositive ? "clock-deviation--positive" : "clock-deviation--negative";
    },
    deviationIcon(minutes, moment) {
      if (minutes === null || minutes === undefined) return "bx-minus";
      if (minutes === 0) return "bx-check";
      if (moment === "entry") return minutes < 0 ? "bx-up-arrow-alt" : "bx-down-arrow-alt";
      return minutes > 0 ? "bx-up-arrow-alt" : "bx-down-arrow-alt";
    },
    reportPeriodLabel() {
      if (!this.reportResult?.period) return "Sin período consultado";
      return `${this.formatShortDate(this.reportResult.period.date_from)} al ${this.formatShortDate(this.reportResult.period.date_to)}`;
    },
  },
};
</script>

<template>
  <Layout>
    <main class="clock-control">
      <section class="clock-hero">
        <div class="clock-hero__orb clock-hero__orb--one"></div>
        <div class="clock-hero__orb clock-hero__orb--two"></div>
        <div class="clock-hero__copy">
          <span class="clock-eyebrow"><i class="bx bx-shield-quarter"></i> Módulo exclusivo de Superadmin</span>
          <h1>Reloj Control</h1>
          <p>Consulta el libro de asistencia de GeoVictoria desde un espacio institucional seguro, simple y completamente de sólo lectura.</p>
          <div class="clock-hero__signals">
            <span><i class="bx bx-cloud"></i> Fuente GeoVictoria</span>
            <span><i class="bx bx-lock-alt"></i> Credenciales protegidas</span>
            <span><i class="bx bx-show"></i> Sin modificaciones</span>
          </div>
        </div>
        <div class="clock-hero__timepiece" aria-hidden="true">
          <div class="clock-face"><span></span><i></i><b></b></div>
          <div class="clock-hero__status">
            <span :class="{ 'clock-live-dot--error': providerState.error }" class="clock-live-dot"></span>
            {{ providerState.label }}
          </div>
        </div>
      </section>

      <BAlert v-if="catalogError" :model-value="true" variant="danger" class="clock-alert">
        <i class="bx bx-error-circle"></i>
        <div><strong>No se pudo sincronizar el catálogo</strong><span>{{ catalogError }}</span></div>
        <button type="button" :disabled="loadingUsers" @click="loadUsers(true)">Reintentar</button>
      </BAlert>

      <BAlert
        v-else-if="catalogFetchedAt && catalogSummary.provider_available !== true"
        :model-value="true"
        variant="warning"
        class="clock-alert clock-alert--warning"
      >
        <i class="bx bx-key"></i>
        <div>
          <strong>GeoVictoria rechazó las credenciales</strong>
          <span>La dotación institucional continúa visible, pero no se podrán consultar marcaciones hasta validar la nueva clave API.</span>
        </div>
        <button type="button" :disabled="loadingUsers" @click="loadUsers(true)">Probar nuevamente</button>
      </BAlert>

      <BAlert
        v-else-if="!loadingUsers && catalogSummary.provider_total > 0 && catalogSummary.linked === 0"
        :model-value="true"
        variant="warning"
        class="clock-alert clock-alert--warning"
      >
        <i class="bx bx-shield-x"></i>
        <div>
          <strong>La cuenta GeoVictoria no coincide con la dotación institucional</strong>
          <span>No se encontró ninguna coincidencia exacta de RUT. Las personas ajenas a la institución fueron ocultadas.</span>
        </div>
      </BAlert>

      <section class="clock-catalog-strip" aria-label="Resumen de funcionarios y vinculación GeoVictoria">
        <article><span class="clock-catalog-strip__icon clock-catalog-strip__icon--blue"><i class="bx bx-user"></i></span><div><strong>{{ catalogSummary.total }}</strong><small>Funcionarios</small></div></article>
        <article><span class="clock-catalog-strip__icon clock-catalog-strip__icon--green"><i class="bx bx-user-check"></i></span><div><strong>{{ catalogSummary.active }}</strong><small>Activos</small></div></article>
        <article><span class="clock-catalog-strip__icon clock-catalog-strip__icon--slate"><i class="bx bx-link-alt"></i></span><div><strong>{{ catalogSummary.linked }}</strong><small>Vinculados</small></div></article>
        <article><span class="clock-catalog-strip__icon clock-catalog-strip__icon--amber"><i class="bx bx-unlink"></i></span><div><strong>{{ catalogSummary.unlinked }}</strong><small>Sin vincular</small></div></article>
        <div class="clock-catalog-strip__sync">
          <span>Catálogo actualizado</span>
          <strong>{{ formatSynced(catalogFetchedAt) }}</strong>
          <button type="button" :disabled="loadingUsers" @click="loadUsers(true)"><i class="bx bx-refresh" :class="{ 'bx-spin': loadingUsers }"></i> Sincronizar</button>
        </div>
      </section>

      <nav class="clock-mode-tabs" aria-label="Vistas de Reloj Control">
        <button type="button" :class="{ active: activeWorkspace === 'attendance' }" @click="activeWorkspace = 'attendance'"><span><i class="bx bx-calendar-check"></i></span><div><strong>Consulta diaria</strong><small>Jornadas y marcaciones individuales</small></div></button>
        <button type="button" :class="{ active: activeWorkspace === 'reports' }" @click="activeWorkspace = 'reports'"><span><i class="bx bx-file-find"></i></span><div><strong>Reportes del período</strong><small>Atrasos, balances, ausencias y total general</small></div><em>Nuevo</em></button>
      </nav>

      <template v-if="activeWorkspace === 'attendance'">

      <section class="clock-query-panel">
        <header class="clock-query-panel__header">
          <div><span>Consulta de asistencia</span><h2>Funcionarios y período</h2><p>Configura la búsqueda en una sola línea. Los cálculos se generan al consultar.</p></div>
          <span class="clock-query-panel__limit"><i class="bx bx-group"></i>{{ selectedIds.length }} de 50</span>
        </header>

        <form class="clock-query-bar" @submit.prevent="runQuery">
          <div class="clock-selector-control">
            <label>Funcionarios</label>
            <button
              type="button"
              class="clock-selector-trigger"
              :aria-expanded="showPeopleSelector"
              @click="showPeopleSelector = !showPeopleSelector"
            >
              <span class="clock-selector-trigger__avatars">
                <i v-if="!selectedUsers.length" class="bx bx-user-plus"></i>
                <b v-for="user in selectedUsers.slice(0, 3)" :key="user.id">{{ initials(user.name) }}</b>
              </span>
              <span><strong>{{ selectedIds.length ? `${selectedIds.length} seleccionado${selectedIds.length === 1 ? '' : 's'}` : "Seleccionar funcionarios" }}</strong><small>{{ selectedIds.length ? "Cambiar selección" : "Busca por nombre, RUT o cargo" }}</small></span>
              <i class="bx" :class="showPeopleSelector ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
            </button>
          </div>
          <label><span>Desde</span><input v-model="query.date_from" type="date" class="form-control" required></label>
          <span class="clock-query-bar__arrow"><i class="bx bx-right-arrow-alt"></i></span>
          <label><span>Hasta</span><input v-model="query.date_to" type="date" class="form-control" required></label>
          <button type="submit" class="clock-query-button" :disabled="!canQuery">
            <i class="bx" :class="querying ? 'bx-loader-alt bx-spin' : 'bx-search-alt'"></i>
            {{ querying ? "Consultando..." : "Consultar asistencia" }}
          </button>
        </form>

        <div v-if="selectedUsers.length" class="clock-selected-chips">
          <span v-for="user in selectedUsers.slice(0, 6)" :key="user.id">{{ user.name }}<button type="button" :aria-label="`Quitar a ${user.name}`" @click="removeSelected(user.id)"><i class="bx bx-x"></i></button></span>
          <em v-if="selectedUsers.length > 6">+{{ selectedUsers.length - 6 }} más</em>
          <button type="button" @click="clearSelection">Limpiar selección</button>
        </div>

        <section v-if="showPeopleSelector" class="clock-people" aria-label="Selector de funcionarios">
          <div class="clock-people__tools">
            <div class="clock-people__search">
              <i class="bx bx-search"></i>
              <input v-model.trim="userFilters.search" type="search" placeholder="Nombre, RUT, cargo o correo" aria-label="Buscar funcionarios">
              <button v-if="userFilters.search" type="button" aria-label="Limpiar búsqueda" @click="userFilters.search = ''"><i class="bx bx-x"></i></button>
            </div>
            <div class="clock-segmented" aria-label="Estado del funcionario">
              <button type="button" :class="{ active: userFilters.status === 'active' }" @click="userFilters.status = 'active'">Activos</button>
              <button type="button" :class="{ active: userFilters.status === 'inactive' }" @click="userFilters.status = 'inactive'">Inactivos</button>
              <button type="button" :class="{ active: userFilters.status === '' }" @click="userFilters.status = ''">Todos</button>
            </div>
            <button type="button" class="clock-people__bulk-action" :disabled="!selectableFilteredUsers.length" @click="toggleVisibleUsers">
              <i class="bx" :class="allVisibleSelected ? 'bx-checkbox-checked' : 'bx-checkbox'"></i>{{ allVisibleSelected ? "Quitar visibles" : "Seleccionar visibles" }}
            </button>
            <button type="button" class="clock-people__done" @click="showPeopleSelector = false">Listo</button>
          </div>

          <LoadingState v-if="loadingUsers && !users.length" message="Sincronizando colaboradores..." />
          <div v-else-if="!filteredUsers.length" class="clock-people__empty"><i class="bx bx-user-x"></i><strong>Sin coincidencias</strong><span>Prueba con otra búsqueda o estado.</span></div>
          <div v-else class="clock-people__list">
            <button
              v-for="user in filteredUsers"
              :key="user.id"
              type="button"
              class="clock-person"
              :class="{ 'clock-person--selected': selectedIds.includes(user.id), 'clock-person--unlinked': !user.geovictoria_linked }"
              :disabled="!user.geovictoria_linked"
              :title="user.geovictoria_linked ? `Consultar a ${user.name}` : 'Sin coincidencia exacta de RUT en GeoVictoria'"
              @click="toggleUser(user)"
            >
              <span class="clock-person__avatar">{{ initials(user.name) }}</span>
              <span class="clock-person__identity"><strong>{{ user.name || "Sin nombre" }}</strong><small>{{ user.position || user.group || "Sin cargo informado" }} · {{ user.rut || "Sin RUT" }}</small><em :class="user.geovictoria_linked ? 'clock-link--ok' : 'clock-link--pending'"><i class="bx" :class="user.geovictoria_linked ? 'bx-link' : 'bx-unlink'"></i>{{ user.geovictoria_linked ? "Vinculado" : "Sin vincular" }}</em></span>
              <i class="bx" :class="selectedIds.includes(user.id) ? 'bxs-check-circle' : (user.geovictoria_linked ? 'bx-circle' : 'bx-lock-alt')"></i>
            </button>
          </div>
          <footer>{{ filteredUsers.length }} visibles · {{ selectableFilteredUsers.length }} consultables</footer>
        </section>
      </section>

      <section class="clock-workspace">

          <BAlert v-if="queryError" :model-value="true" variant="danger" class="clock-query-error">
            <i class="bx bx-error-circle"></i><span>{{ queryError }}</span>
          </BAlert>

          <section class="clock-results-head">
            <div><span>Resultado</span><h2>Libro de asistencia</h2><p>{{ periodLabel }}</p></div>
            <div v-if="result" class="clock-results-head__actions">
              <div class="clock-results-head__stamp"><i class="bx bx-check-shield"></i><span>Consulta completada<strong>{{ formatSynced(result.queried_at) }}</strong></span></div>
              <button type="button" class="clock-pdf-button" :disabled="exportingPdf" @click="exportPdf"><i class="bx" :class="exportingPdf ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i>{{ exportingPdf ? "Generando..." : "Exportar PDF" }}</button>
            </div>
          </section>

          <section class="clock-metrics" aria-label="Resumen de la consulta">
            <article><span class="clock-metric-icon clock-metric-icon--indigo"><i class="bx bx-group"></i></span><div><small>Personas</small><strong>{{ summary.returned_users }}</strong></div></article>
            <article><span class="clock-metric-icon clock-metric-icon--sky"><i class="bx bx-calendar"></i></span><div><small>Jornadas</small><strong>{{ summary.days }}</strong></div></article>
            <article><span class="clock-metric-icon clock-metric-icon--teal"><i class="bx bx-fingerprint"></i></span><div><small>Marcaciones</small><strong>{{ summary.punches }}</strong></div></article>
            <article><span class="clock-metric-icon clock-metric-icon--rose"><i class="bx bx-user-x"></i></span><div><small>Ausencias</small><strong>{{ summary.absences }}</strong></div></article>
            <article><span class="clock-metric-icon clock-metric-icon--amber"><i class="bx bx-time-five"></i></span><div><small>Tiempo trabajado</small><strong>{{ summary.worked_time_label }}</strong></div></article>
          </section>

          <section v-if="result" class="clock-variance-summary" aria-label="Resumen de diferencias horarias">
            <article class="clock-variance-summary--good"><i class="bx bx-log-in-circle"></i><div><small>Entradas anticipadas</small><strong>{{ summary.entry_early_minutes || 0 }} min</strong></div></article>
            <article class="clock-variance-summary--bad"><i class="bx bx-time"></i><div><small>Atrasos de entrada</small><strong>{{ summary.entry_late_minutes || 0 }} min</strong></div></article>
            <article class="clock-variance-summary--bad"><i class="bx bx-log-out-circle"></i><div><small>Salidas anticipadas</small><strong>{{ summary.exit_early_minutes || 0 }} min</strong></div></article>
            <article class="clock-variance-summary--good"><i class="bx bx-plus-circle"></i><div><small>Salidas posteriores</small><strong>{{ summary.exit_after_minutes || 0 }} min</strong></div></article>
          </section>

          <LoadingState v-if="querying && !result" message="Consultando el libro de asistencia en GeoVictoria..." />

          <div v-else-if="!result" class="clock-results-empty">
            <span class="clock-results-empty__icon"><i class="bx bx-time-five"></i></span>
            <h3>Tu consulta aparecerá aquí</h3>
            <p>Selecciona uno o más funcionarios, define el período y presiona “Consultar”.</p>
            <div><span><i class="bx bx-check"></i> Máximo 31 días</span><span><i class="bx bx-check"></i> Hasta 50 personas</span><span><i class="bx bx-check"></i> Sólo lectura</span></div>
          </div>

          <div v-else-if="!rows.length" class="clock-results-empty clock-results-empty--complete">
            <span class="clock-results-empty__icon"><i class="bx bx-calendar-x"></i></span>
            <h3>Sin jornadas en el período</h3>
            <p>GeoVictoria respondió correctamente, pero no informó intervalos planificados para la selección.</p>
          </div>

          <template v-else>
            <section v-if="weeklyRows.length" class="clock-weekly">
              <header><div><span>Consolidado semanal</span><h3>Balance por funcionario</h3><p>Suma de minutos calculada desde la planificación y las marcaciones consultadas.</p></div><span class="clock-weekly__count">{{ weeklyRows.length }} {{ weeklyRows.length === 1 ? "resumen" : "resúmenes" }}</span></header>
              <div class="clock-weekly__table-wrap">
                <table>
                  <thead><tr><th>Semana / funcionario</th><th>Jornadas</th><th>Tiempo trabajado</th><th>Entrada antes</th><th>Atrasos</th><th>Salida antes</th><th>Salida después</th></tr></thead>
                  <tbody><tr v-for="week in weeklyRows" :key="week.key">
                    <td><strong>{{ week.user.name }}</strong><small>{{ formatShortDate(week.week_start) }} al {{ formatShortDate(week.week_end) }}</small></td>
                    <td><strong>{{ week.worked_days }}/{{ week.days }}</strong><small>{{ week.absences }} ausencias</small></td>
                    <td><strong>{{ week.worked_time_label }}</strong><small>{{ week.days_with_entry }} entradas · {{ week.days_with_exit }} salidas</small></td>
                    <td><span class="clock-weekly-value clock-weekly-value--good">{{ week.entry_early_minutes }} min</span></td>
                    <td><span class="clock-weekly-value clock-weekly-value--bad">{{ week.entry_late_minutes }} min</span></td>
                    <td><span class="clock-weekly-value clock-weekly-value--bad">{{ week.exit_early_minutes }} min</span></td>
                    <td><span class="clock-weekly-value clock-weekly-value--good">{{ week.exit_after_minutes }} min</span></td>
                  </tr></tbody>
                </table>
              </div>
            </section>

            <div class="clock-daily-heading"><div><span>Detalle diario</span><h3>Entradas, salidas y diferencias</h3></div><small>Antes = valor negativo · Después = valor positivo</small></div>
            <div class="clock-table-wrap d-none d-lg-block" :class="{ 'clock-table-wrap--loading': querying }">
              <table class="clock-table">
                <thead><tr><th>Fecha</th><th>Colaborador</th><th>Jornada planificada</th><th>Entrada real / diferencia</th><th>Salida real / diferencia</th><th>Horas</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="row in paginatedRows" :key="row.key">
                    <td><time>{{ formatDate(row.date) }}</time></td>
                    <td><div class="clock-table-person"><span>{{ initials(row.user.name) }}</span><div><strong>{{ row.user.name }}</strong><small>{{ row.user.group || row.user.identifier }}</small></div></div></td>
                    <td><div class="clock-shift"><strong>{{ shiftLabel(row) }}</strong><small v-if="row.schedule?.start_at && row.schedule?.end_at">{{ formatTime(row.schedule.start_at) }} a {{ formatTime(row.schedule.end_at) }}</small><small v-else>Sin horario calculable</small></div></td>
                    <td><div class="clock-mark"><strong>{{ formatTime(row.attendance_variance?.entry_at) }}</strong><span :class="deviationClass(row.attendance_variance?.entry_delta_minutes, 'entry')" class="clock-deviation"><i class="bx" :class="deviationIcon(row.attendance_variance?.entry_delta_minutes, 'entry')"></i>{{ row.attendance_variance?.entry_label || "Sin cálculo" }}</span></div></td>
                    <td><div class="clock-mark"><strong>{{ formatTime(row.attendance_variance?.exit_at) }}</strong><span :class="deviationClass(row.attendance_variance?.exit_delta_minutes, 'exit')" class="clock-deviation"><i class="bx" :class="deviationIcon(row.attendance_variance?.exit_delta_minutes, 'exit')"></i>{{ row.attendance_variance?.exit_label || "Sin cálculo" }}</span></div></td>
                    <td><div class="clock-hours"><strong>{{ row.worked_hours || "0:00" }}</strong><small>trabajadas</small></div></td>
                    <td><span class="clock-status" :class="rowStatus(row).class"><i class="bx" :class="rowStatus(row).icon"></i>{{ rowStatus(row).label }}</span></td>
                    <td><button type="button" class="clock-detail-button" :aria-label="`Ver detalle de ${row.user.name}`" @click="openDetail(row)"><i class="bx bx-show"></i></button></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="clock-mobile-results d-lg-none" :class="{ 'clock-table-wrap--loading': querying }">
              <article v-for="row in paginatedRows" :key="row.key" class="clock-mobile-card">
                <header><time>{{ formatDate(row.date) }}</time><span class="clock-status" :class="rowStatus(row).class"><i class="bx" :class="rowStatus(row).icon"></i>{{ rowStatus(row).label }}</span></header>
                <div class="clock-table-person"><span>{{ initials(row.user.name) }}</span><div><strong>{{ row.user.name }}</strong><small>{{ row.user.group || row.user.identifier }}</small></div></div>
                <dl><div><dt>Turno</dt><dd>{{ row.schedule?.start_at ? `${formatTime(row.schedule.start_at)}–${formatTime(row.schedule.end_at)}` : "Sin turno" }}</dd></div><div><dt>Entrada</dt><dd>{{ formatTime(row.attendance_variance?.entry_at) }}</dd><span :class="deviationClass(row.attendance_variance?.entry_delta_minutes, 'entry')" class="clock-deviation">{{ row.attendance_variance?.entry_label || "Sin cálculo" }}</span></div><div><dt>Salida</dt><dd>{{ formatTime(row.attendance_variance?.exit_at) }}</dd><span :class="deviationClass(row.attendance_variance?.exit_delta_minutes, 'exit')" class="clock-deviation">{{ row.attendance_variance?.exit_label || "Sin cálculo" }}</span></div></dl>
                <button type="button" @click="openDetail(row)">Ver jornada completa <i class="bx bx-right-arrow-alt"></i></button>
              </article>
            </div>

            <div v-if="rows.length > perPage" class="clock-pagination">
              <span>Mostrando {{ ((currentPage - 1) * perPage) + 1 }}–{{ Math.min(currentPage * perPage, rows.length) }} de {{ rows.length }}</span>
              <BPagination v-model="currentPage" :total-rows="rows.length" :per-page="perPage" first-number last-number />
            </div>
          </template>
      </section>
      </template>

      <section v-else class="clock-report-center">
        <header class="clock-report-center__hero">
          <div><span><i class="bx bx-shield-quarter"></i> Reportería consolidada</span><h2>Reportes del período</h2><p>Consulta toda la dotación vinculada por lotes seguros y consolida los resultados después de recibir los datos de GeoVictoria.</p></div>
          <div class="clock-report-center__badge"><i class="bx bx-layer"></i><strong>{{ catalogSummary.linked }}</strong><small>funcionarios disponibles</small></div>
        </header>

        <form class="clock-report-form" @submit.prevent="runReports">
          <label><span>Alcance</span><select v-model="reportQuery.scope" class="form-select"><option value="all_linked">Toda la dotación vinculada</option><option value="selected">Selección actual ({{ selectedIds.length }})</option></select></label>
          <label><span>Desde</span><input v-model="reportQuery.date_from" type="date" class="form-control" required></label>
          <label><span>Hasta</span><input v-model="reportQuery.date_to" type="date" class="form-control" required></label>
          <label><span>Tolerancia de atraso</span><select v-model.number="reportQuery.tolerance_minutes" class="form-select"><option :value="0">Sin tolerancia</option><option :value="3">3 minutos</option><option :value="5">5 minutos</option><option :value="10">10 minutos</option><option :value="15">15 minutos</option></select></label>
          <button type="submit" :disabled="reporting"><i class="bx" :class="reporting ? 'bx-loader-alt bx-spin' : 'bx-line-chart'"></i>{{ reporting ? "Consultando lotes..." : "Generar reportes" }}</button>
        </form>
        <div class="clock-report-form__note"><i class="bx bx-info-circle"></i><span>La tolerancia define qué entradas se clasifican como atraso; los minutos se mantienen completos y no se compensan automáticamente con salidas posteriores.</span></div>

        <BAlert v-if="reportError" :model-value="true" variant="danger" class="clock-query-error"><i class="bx bx-error-circle"></i><span>{{ reportError }}</span></BAlert>
        <LoadingState v-if="reporting && !reportResult" message="Consultando y consolidando la dotación en lotes seguros..." />

        <div v-else-if="!reportResult" class="clock-report-empty">
          <span><i class="bx bx-file-find"></i></span><h3>Elige el período y genera el consolidado</h3><p>Obtendrás cuatro vistas: resultado general, atrasos, balance de minutos y ausencias explícitamente informadas por GeoVictoria.</p>
          <div><em><i class="bx bx-time-five"></i>Atrasos</em><em><i class="bx bx-transfer-alt"></i>Balance</em><em><i class="bx bx-user-x"></i>Ausencias</em><em><i class="bx bx-group"></i>Total general</em></div>
        </div>

        <template v-else>
          <section class="clock-report-result-head">
            <div><span>Reporte completado</span><h3>{{ reportPeriodLabel() }}</h3><p>{{ reportSummary.returned_users }} de {{ reportSummary.requested_users }} funcionarios respondidos · {{ reportResult.coverage.batches }} {{ reportResult.coverage.batches === 1 ? "lote" : "lotes" }}</p></div>
            <button type="button" :disabled="exportingReportPdf" @click="exportReportsPdf"><i class="bx" :class="exportingReportPdf ? 'bx-loader-alt bx-spin' : 'bxs-file-pdf'"></i>{{ exportingReportPdf ? "Generando..." : "Exportar reporte PDF" }}</button>
          </section>

          <section class="clock-report-kpis" aria-label="Indicadores generales del reporte">
            <article><span class="clock-report-kpis__icon clock-report-kpis__icon--blue"><i class="bx bx-group"></i></span><div><small>Funcionarios</small><strong>{{ reportSummary.returned_users }}/{{ reportSummary.requested_users }}</strong></div></article>
            <article><span class="clock-report-kpis__icon clock-report-kpis__icon--amber"><i class="bx bx-time-five"></i></span><div><small>Personas con atrasos</small><strong>{{ reportSummary.tardy_people }}</strong><em>{{ reportSummary.tardiness_minutes }} min</em></div></article>
            <article><span class="clock-report-kpis__icon clock-report-kpis__icon--rose"><i class="bx bx-user-x"></i></span><div><small>Personas ausentes</small><strong>{{ reportSummary.absent_people }}</strong><em>{{ reportSummary.absences }} jornadas</em></div></article>
            <article><span class="clock-report-kpis__icon clock-report-kpis__icon--teal"><i class="bx bx-log-in-circle"></i></span><div><small>Entradas anticipadas</small><strong>{{ reportSummary.entry_early_minutes }} min</strong></div></article>
            <article><span class="clock-report-kpis__icon clock-report-kpis__icon--red"><i class="bx bx-log-out-circle"></i></span><div><small>Salidas anticipadas</small><strong>{{ reportSummary.exit_early_minutes }} min</strong></div></article>
            <article><span class="clock-report-kpis__icon clock-report-kpis__icon--green"><i class="bx bx-plus-circle"></i></span><div><small>Salidas posteriores</small><strong>{{ reportSummary.exit_after_minutes }} min</strong></div></article>
          </section>

          <section class="clock-coverage-strip">
            <div><span>Cobertura de horarios</span><strong>{{ reportResult.coverage.scheduled_days }} jornadas planificadas</strong></div>
            <div><span>Entradas disponibles</span><strong>{{ reportResult.coverage.days_with_entry }}</strong></div>
            <div><span>Salidas disponibles</span><strong>{{ reportResult.coverage.days_with_exit }}</strong></div>
            <div><span>Marcas incompletas</span><strong>{{ reportSummary.missing_entry + reportSummary.missing_exit }}</strong></div>
            <div><span>Ausencias justificadas</span><strong>{{ reportSummary.justified_absences }}/{{ reportSummary.absences }}</strong></div>
          </section>

          <nav class="clock-report-tabs" aria-label="Tipos de reporte">
            <button v-for="tab in reportTabs" :key="tab.key" type="button" :class="{ active: activeReport === tab.key }" @click="activeReport = tab.key"><i class="bx" :class="tab.icon"></i><span>{{ tab.label }}</span><em>{{ tab.count }}</em></button>
          </nav>

          <div class="clock-report-toolbar">
            <div><span>{{ reportTabs.find((tab) => tab.key === activeReport)?.label }}</span><strong>{{ activeReportRows.length }} {{ activeReportRows.length === 1 ? "resultado" : "resultados" }}</strong></div>
            <label v-if="activeReport !== 'general'"><i class="bx bx-search"></i><input v-model.trim="reportSearch" type="search" placeholder="Buscar funcionario" aria-label="Buscar en el reporte"></label>
          </div>

          <div v-if="!activeReportRows.length" class="clock-report-table-empty"><i class="bx bx-check-shield"></i><strong>Sin resultados para este criterio</strong><span>GeoVictoria no informó registros de este tipo en el período seleccionado.</span></div>

          <div v-else class="clock-report-table-wrap">
            <table v-if="activeReport === 'general'" class="clock-report-table">
              <thead><tr><th>Departamento o cargo</th><th>Funcionarios</th><th>Tiempo trabajado</th><th>Eventos de atraso</th><th>Minutos de atraso</th><th>Ausencias</th></tr></thead>
              <tbody><tr v-for="row in activeReportRows" :key="row.name"><td><strong>{{ row.name }}</strong></td><td>{{ row.people }}</td><td>{{ row.worked_time_label }}</td><td>{{ row.tardiness_occurrences }}</td><td><span class="clock-report-pill clock-report-pill--bad">{{ row.tardiness_minutes }} min</span></td><td><span class="clock-report-pill clock-report-pill--absence">{{ row.absences }}</span></td></tr></tbody>
            </table>

            <table v-else-if="activeReport === 'tardiness'" class="clock-report-table">
              <thead><tr><th>Funcionario</th><th>Eventos</th><th>Minutos acumulados</th><th>Promedio</th><th>Máximo</th><th>Fechas</th></tr></thead>
              <tbody><tr v-for="row in activeReportRows" :key="row.user.id"><td><div class="clock-report-person"><span>{{ initials(row.user.name) }}</span><div><strong>{{ row.user.name }}</strong><small>{{ row.user.position || row.user.group || row.user.rut }}</small></div></div></td><td>{{ row.occurrences }}</td><td><span class="clock-report-pill clock-report-pill--bad">{{ row.minutes }} min</span></td><td>{{ row.average_minutes }} min</td><td>{{ row.maximum_minutes }} min</td><td><div class="clock-report-dates"><span v-for="detail in row.details.slice(0, 4)" :key="detail.date">{{ formatShortDate(String(detail.date).replace(/^(\d{4})(\d{2})(\d{2}).*$/, '$1-$2-$3')) }} · {{ detail.minutes }} min</span><em v-if="row.details.length > 4">+{{ row.details.length - 4 }} más</em></div></td></tr></tbody>
            </table>

            <table v-else-if="activeReport === 'balances'" class="clock-report-table">
              <thead><tr><th>Funcionario</th><th>Trabajado</th><th>Entrada antes</th><th>Atrasos</th><th>Salida antes</th><th>Salida después</th><th>Saldo informativo</th></tr></thead>
              <tbody><tr v-for="row in activeReportRows" :key="row.user.id"><td><div class="clock-report-person"><span>{{ initials(row.user.name) }}</span><div><strong>{{ row.user.name }}</strong><small>{{ row.worked_days }}/{{ row.days }} jornadas trabajadas</small></div></div></td><td>{{ row.worked_time_label }}</td><td><span class="clock-report-pill clock-report-pill--good">{{ row.entry_early_minutes }} min</span><small>{{ row.entry_early_occurrences }} eventos</small></td><td><span class="clock-report-pill clock-report-pill--bad">{{ row.entry_late_minutes }} min</span><small>{{ row.entry_late_occurrences }} eventos</small></td><td><span class="clock-report-pill clock-report-pill--bad">{{ row.exit_early_minutes }} min</span><small>{{ row.exit_early_occurrences }} eventos</small></td><td><span class="clock-report-pill clock-report-pill--good">{{ row.exit_after_minutes }} min</span><small>{{ row.exit_after_occurrences }} eventos</small></td><td><span class="clock-report-net" :class="row.net_minutes < 0 ? 'clock-report-net--negative' : 'clock-report-net--positive'">{{ row.net_label }}</span></td></tr></tbody>
            </table>

            <table v-else class="clock-report-table">
              <thead><tr><th>Funcionario</th><th>Ausencias</th><th>Justificadas</th><th>Sin justificación informada</th><th>Horas no trabajadas</th><th>Fechas y antecedentes</th></tr></thead>
              <tbody><tr v-for="row in activeReportRows" :key="row.user.id"><td><div class="clock-report-person"><span>{{ initials(row.user.name) }}</span><div><strong>{{ row.user.name }}</strong><small>{{ row.user.position || row.user.group || row.user.rut }}</small></div></div></td><td><span class="clock-report-pill clock-report-pill--absence">{{ row.occurrences }}</span></td><td>{{ row.justified }}</td><td>{{ row.without_justification }}</td><td>{{ Math.floor(row.non_worked_minutes / 60) }} h {{ row.non_worked_minutes % 60 }} min</td><td><div class="clock-report-dates"><span v-for="detail in row.details.slice(0, 4)" :key="detail.date"><b>{{ formatShortDate(String(detail.date).replace(/^(\d{4})(\d{2})(\d{2}).*$/, '$1-$2-$3')) }}</b> · {{ detail.justified ? (detail.justifications.join(', ') || 'Justificada') : 'Sin justificación informada' }}</span><em v-if="row.details.length > 4">+{{ row.details.length - 4 }} más</em></div></td></tr></tbody>
            </table>
          </div>
        </template>
      </section>

      <BModal v-model="showDetail" size="lg" hide-footer centered scrollable body-class="p-0" modal-class="clock-detail-modal">
        <template #header>
          <div class="clock-detail-header"><span><i class="bx bx-time-five"></i></span><div><small>Detalle de jornada</small><h2>{{ selectedRow?.user?.name }}</h2></div></div>
          <button type="button" class="clock-detail-close" aria-label="Cerrar" @click="showDetail = false"><i class="bx bx-x"></i></button>
        </template>
        <div v-if="selectedRow" class="clock-detail">
          <div class="clock-detail__notice"><i class="bx bx-lock-alt"></i><div><strong>Consulta protegida y de sólo lectura</strong><span>Esta vista no modifica datos ni marcaciones en GeoVictoria.</span></div></div>
          <section class="clock-detail__summary">
            <div><span>Fecha</span><strong>{{ formatDate(selectedRow.date) }}</strong></div>
            <div><span>Identificador</span><strong>{{ selectedRow.user.identifier }}</strong></div>
            <div><span>Grupo</span><strong>{{ selectedRow.user.group || "Sin grupo" }}</strong></div>
            <div><span>Horas trabajadas</span><strong>{{ selectedRow.worked_hours || "0:00" }}</strong></div>
            <div><span>Horas no trabajadas</span><strong>{{ selectedRow.non_worked_hours || "0:00" }}</strong></div>
            <div><span>Estado</span><strong>{{ rowStatus(selectedRow).label }}</strong></div>
            <div><span>Entrada</span><strong>{{ formatTime(selectedRow.attendance_variance?.entry_at) }} · {{ selectedRow.attendance_variance?.entry_label || "Sin cálculo" }}</strong></div>
            <div><span>Salida</span><strong>{{ formatTime(selectedRow.attendance_variance?.exit_at) }} · {{ selectedRow.attendance_variance?.exit_label || "Sin cálculo" }}</strong></div>
          </section>
          <section class="clock-detail__section">
            <header><span><i class="bx bx-calendar-event"></i></span><div><small>Planificación</small><h3>Turnos informados</h3></div></header>
            <div v-if="selectedRow.shifts.length" class="clock-detail__items">
              <article v-for="(shift, index) in selectedRow.shifts" :key="index"><strong>{{ shift.name || `Turno ${index + 1}` }}</strong><span>{{ shift.start_time || "--:--" }} a {{ shift.exit_time || "--:--" }}</span><small v-if="shift.delay">Atraso: {{ shift.delay }}</small><small v-if="shift.early_leave">Salida anticipada: {{ shift.early_leave }}</small></article>
            </div>
            <p v-else class="clock-detail__empty">No se informó un turno para esta jornada.</p>
          </section>
          <section class="clock-detail__section">
            <header><span><i class="bx bx-fingerprint"></i></span><div><small>Registro</small><h3>Marcaciones</h3></div></header>
            <div v-if="selectedRow.punches.length" class="clock-detail__timeline">
              <article v-for="(punch, index) in selectedRow.punches" :key="`${punch.date}-${index}`"><span><i class="bx" :class="normalizeText(punch.type).includes('sal') ? 'bx-log-out' : 'bx-log-in'"></i></span><div><strong>{{ punch.type || "Marcación" }} · {{ formatTime(punch.date) }}</strong><small>{{ punch.origin || "Origen no informado" }}</small></div></article>
            </div>
            <p v-else class="clock-detail__empty">No hay marcaciones asociadas a esta jornada.</p>
          </section>
          <section v-if="selectedRow.time_offs.length" class="clock-detail__section">
            <header><span><i class="bx bx-calendar-exclamation"></i></span><div><small>Justificación</small><h3>Permisos</h3></div></header>
            <div class="clock-detail__items"><article v-for="(timeOff, index) in selectedRow.time_offs" :key="index"><strong>{{ timeOff.type || "Permiso" }}</strong><span>{{ formatDate(timeOff.starts) }} al {{ formatDate(timeOff.ends) }}</span><small>{{ timeOff.origin }}</small></article></div>
          </section>
        </div>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.clock-control { --clock-ink: #182235; --clock-muted: #718096; --clock-line: #e7ebf2; padding-bottom: 2rem; color: var(--clock-ink); }
.clock-hero { position: relative; isolation: isolate; display: flex; align-items: center; justify-content: space-between; gap: 2rem; overflow: hidden; min-height: 258px; margin-bottom: 1rem; padding: 2.4rem 2.6rem; border-radius: 26px; color: #fff; background: linear-gradient(120deg, #0b1c35 0%, #12395c 48%, #075e61 100%); box-shadow: 0 24px 60px rgba(11, 37, 60, .22); }
.clock-hero::after { position: absolute; z-index: -1; inset: 0; opacity: .13; background-image: linear-gradient(rgba(255,255,255,.22) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.22) 1px, transparent 1px); background-size: 36px 36px; content: ""; mask-image: linear-gradient(90deg, transparent, #000); }
.clock-hero__orb { position: absolute; z-index: -1; border-radius: 50%; filter: blur(1px); }
.clock-hero__orb--one { width: 300px; height: 300px; top: -190px; right: 22%; background: rgba(45, 212, 191, .28); }
.clock-hero__orb--two { width: 240px; height: 240px; right: -90px; bottom: -140px; background: rgba(56, 189, 248, .22); }
.clock-hero__copy { max-width: 720px; }
.clock-eyebrow { display: inline-flex; align-items: center; gap: .42rem; margin-bottom: .9rem; padding: .38rem .68rem; border: 1px solid rgba(255,255,255,.22); border-radius: 999px; color: #c7f9f3; background: rgba(255,255,255,.08); font-size: .7rem; font-weight: 750; letter-spacing: .065em; text-transform: uppercase; }
.clock-hero h1 { margin: 0 0 .6rem; color: #fff; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 780; letter-spacing: -.045em; }
.clock-hero p { max-width: 650px; margin: 0; color: rgba(255,255,255,.75); font-size: 1rem; line-height: 1.7; }
.clock-hero__signals { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1.25rem; color: #d3eee9; font-size: .74rem; }
.clock-hero__signals span { display: inline-flex; align-items: center; gap: .32rem; }
.clock-hero__timepiece { flex: 0 0 150px; display: grid; justify-items: center; gap: .75rem; }
.clock-face { position: relative; width: 112px; height: 112px; border: 8px solid rgba(255,255,255,.14); border-radius: 50%; background: rgba(255,255,255,.94); box-shadow: 0 16px 32px rgba(3, 24, 36, .3), inset 0 0 0 1px rgba(14, 74, 87, .1); }
.clock-face::before, .clock-face::after, .clock-face span::before, .clock-face span::after { position: absolute; border-radius: 5px; background: #8aa3aa; content: ""; }
.clock-face::before, .clock-face::after { left: 50%; width: 2px; height: 7px; transform: translateX(-50%); }
.clock-face::before { top: 7px; }.clock-face::after { bottom: 7px; }
.clock-face span::before, .clock-face span::after { top: 50%; width: 7px; height: 2px; transform: translateY(-50%); }
.clock-face span::before { left: 7px; }.clock-face span::after { right: 7px; }
.clock-face i, .clock-face b { position: absolute; left: 50%; bottom: 50%; width: 3px; border-radius: 4px; background: #12395c; transform-origin: bottom center; content: ""; }
.clock-face i { height: 30px; transform: translateX(-50%) rotate(38deg); }.clock-face b { height: 22px; background: #0f8f85; transform: translateX(-50%) rotate(132deg); }
.clock-face b::after { position: absolute; left: 50%; bottom: -5px; width: 10px; height: 10px; border-radius: 50%; background: #0f8f85; transform: translateX(-50%); content: ""; }
.clock-hero__status { display: inline-flex; align-items: center; gap: .45rem; color: #d6efec; font-size: .68rem; font-weight: 700; }
.clock-live-dot { width: 8px; height: 8px; border: 2px solid rgba(52, 211, 153, .35); border-radius: 50%; background: #34d399; box-shadow: 0 0 0 5px rgba(52,211,153,.12); }.clock-live-dot--error { background: #fb7185; box-shadow: 0 0 0 5px rgba(251,113,133,.12); }
.clock-alert { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; border: 0; border-radius: 15px; }.clock-alert > i { font-size: 1.3rem; }.clock-alert div { flex: 1; }.clock-alert strong, .clock-alert span { display: block; }.clock-alert span { margin-top: .12rem; font-size: .72rem; }.clock-alert button { padding: .45rem .7rem; border: 0; border-radius: 9px; color: #fff; background: #b4233e; font-size: .7rem; font-weight: 700; }.clock-alert--warning button { color: #714006; background: #f5c451; }
.clock-catalog-strip { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)) minmax(245px, 1.45fr); overflow: hidden; margin-bottom: 1rem; border: 1px solid var(--clock-line); border-radius: 18px; background: #fff; box-shadow: 0 8px 24px rgba(35, 48, 74, .05); }
.clock-catalog-strip > article { display: flex; align-items: center; gap: .65rem; min-height: 82px; padding: .85rem 1rem; border-right: 1px solid var(--clock-line); }.clock-catalog-strip__icon { display: grid; flex: 0 0 38px; width: 38px; height: 38px; place-items: center; border-radius: 12px; font-size: 1.05rem; }.clock-catalog-strip__icon--blue { color: #285fa8; background: #eaf3ff; }.clock-catalog-strip__icon--green { color: #08785e; background: #e6f8f1; }.clock-catalog-strip__icon--slate { color: #667085; background: #eef1f5; }.clock-catalog-strip__icon--amber { color: #a86208; background: #fff4dd; }
.clock-catalog-strip article strong, .clock-catalog-strip article small { display: block; }.clock-catalog-strip article strong { font-size: 1.2rem; line-height: 1; }.clock-catalog-strip article small { margin-top: .2rem; color: var(--clock-muted); font-size: .66rem; }
.clock-catalog-strip__sync { display: grid; grid-template-columns: 1fr auto; align-content: center; padding: .85rem 1rem; background: #f8fafc; }.clock-catalog-strip__sync span { color: var(--clock-muted); font-size: .62rem; }.clock-catalog-strip__sync strong { display: block; margin-top: .12rem; font-size: .7rem; }.clock-catalog-strip__sync button { grid-row: 1 / 3; grid-column: 2; align-self: center; display: inline-flex; align-items: center; gap: .3rem; padding: .45rem .6rem; border: 1px solid #dce3eb; border-radius: 9px; color: #176b69; background: #fff; font-size: .65rem; font-weight: 700; }
.clock-query-shell { display: grid; grid-template-columns: 350px minmax(0, 1fr); gap: 1rem; align-items: start; }
.clock-people, .clock-workspace { overflow: hidden; border: 1px solid var(--clock-line); border-radius: 20px; background: #fff; box-shadow: 0 12px 32px rgba(33, 47, 73, .06); }
.clock-people { position: sticky; top: 84px; }
.clock-people__header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.05rem .7rem; }.clock-people__header span, .clock-query-bar__heading span, .clock-results-head > div > span { color: #11827b; font-size: .62rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }.clock-people__header h2, .clock-query-bar__heading h2, .clock-results-head h2 { margin: .15rem 0 0; color: var(--clock-ink); font-size: .9rem; font-weight: 760; }.clock-selection-count { display: grid; min-width: 45px; height: 32px; padding: 0 .45rem; place-items: center; border-radius: 10px; color: #0f766e !important; background: #e9f8f5; font-size: .7rem !important; letter-spacing: 0 !important; }
.clock-people__search { display: flex; align-items: center; min-height: 42px; margin: 0 1rem .65rem; border: 1px solid #dde4ec; border-radius: 11px; background: #fbfcfe; }.clock-people__search > i { margin-left: .72rem; color: #8290a2; }.clock-people__search input { min-width: 0; flex: 1; padding: .55rem .5rem; border: 0; outline: 0; color: var(--clock-ink); background: transparent; font-size: .72rem; }.clock-people__search button { display: grid; width: 34px; align-self: stretch; border: 0; place-items: center; color: #718096; background: transparent; }
.clock-segmented { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3px; margin: 0 1rem .65rem; padding: 3px; border-radius: 10px; background: #f0f3f7; }.clock-segmented button { padding: .42rem .3rem; border: 0; border-radius: 8px; color: #768195; background: transparent; font-size: .63rem; font-weight: 700; }.clock-segmented button.active { color: #135f5d; background: #fff; box-shadow: 0 2px 6px rgba(31, 49, 68, .08); }
.clock-people__bulk { display: flex; justify-content: space-between; gap: .5rem; padding: .55rem 1rem; border-block: 1px solid #edf0f4; background: #f9fafc; }.clock-people__bulk button { display: inline-flex; align-items: center; gap: .25rem; border: 0; color: #236e6c; background: transparent; font-size: .64rem; font-weight: 700; }.clock-people__bulk button:last-child { color: #8b5361; }
.clock-people__list { max-height: 560px; overflow-y: auto; padding: .45rem; scrollbar-width: thin; scrollbar-color: #cbd5df transparent; }.clock-person { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; align-items: center; width: 100%; gap: .55rem; padding: .65rem; border: 1px solid transparent; border-radius: 12px; text-align: left; background: transparent; transition: .16s ease; }.clock-person:hover { background: #f8fafc; }.clock-person--selected { border-color: #b9e0dc; background: #f0faf8 !important; }.clock-person--unlinked { cursor: not-allowed; border-color: #f0e6d5; background: #fffcf7; }.clock-person--unlinked:hover { background: #fffcf7; }.clock-person--unlinked .clock-person__avatar { color: #7f8793; background: #eef1f4; }.clock-person__avatar { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 11px; color: #166b67; background: #e5f5f2; font-size: .65rem; font-weight: 800; }.clock-person__identity { min-width: 0; }.clock-person__identity strong, .clock-person__identity small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.clock-person__identity strong { color: #263247; font-size: .7rem; }.clock-person__identity small { margin-top: .12rem; color: #8791a2; font-size: .59rem; }.clock-person__identity em { display: flex; align-items: center; gap: .18rem; margin-top: .22rem; font-size: .53rem; font-style: normal; font-weight: 700; }.clock-link--ok { color: #0d7d6c; }.clock-link--pending { color: #a26716; }.clock-person__id { color: #8a94a5; font-size: .58rem; }.clock-person > i { color: #0f8f85; font-size: 1.05rem; }.clock-person:not(.clock-person--selected) > i { color: #c9d0da; }
.clock-people footer { padding: .55rem 1rem; border-top: 1px solid #edf0f4; color: #8b94a5; background: #fafbfd; font-size: .6rem; text-align: center; }.clock-people__empty { display: grid; min-height: 230px; padding: 1rem; place-items: center; align-content: center; color: #8c96a7; text-align: center; }.clock-people__empty i { margin-bottom: .5rem; font-size: 1.7rem; }.clock-people__empty strong, .clock-people__empty span { display: block; }.clock-people__empty strong { color: #4d596d; font-size: .75rem; }.clock-people__empty span { margin-top: .2rem; font-size: .63rem; }
.clock-query-bar { display: grid; grid-template-columns: minmax(155px, 1fr) minmax(125px, .8fr) auto minmax(125px, .8fr) auto; align-items: end; gap: .7rem; padding: 1rem 1.1rem; border-bottom: 1px solid var(--clock-line); background: #f8fafc; }.clock-query-bar label span { display: block; margin-bottom: .35rem; color: #687487; font-size: .62rem; font-weight: 750; }.clock-query-bar input { min-height: 40px; border-color: #dce3eb; border-radius: 10px; font-size: .72rem; }.clock-query-bar__arrow { align-self: center; margin-top: 1.15rem; color: #9aa4b2; }.clock-query-button { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; min-height: 40px; padding: .55rem .85rem; border: 0; border-radius: 11px; color: #fff; background: linear-gradient(135deg, #0f766e, #0d9488); box-shadow: 0 8px 18px rgba(13,148,136,.2); font-size: .7rem; font-weight: 750; }.clock-query-button:disabled { opacity: .48; box-shadow: none; }
.clock-query-error { display: flex; align-items: center; gap: .45rem; margin: .85rem 1rem 0; border: 0; border-radius: 11px; font-size: .7rem; }
.clock-results-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.15rem .65rem; }.clock-results-head p { margin: .18rem 0 0; color: var(--clock-muted); font-size: .64rem; }.clock-results-head__stamp { display: flex; align-items: center; gap: .45rem; color: #0b7768; }.clock-results-head__stamp > i { font-size: 1.35rem; }.clock-results-head__stamp span, .clock-results-head__stamp strong { display: block; }.clock-results-head__stamp span { font-size: .58rem; }.clock-results-head__stamp strong { margin-top: .1rem; color: #5e6c7f; font-size: .58rem; }
.clock-metrics { display: grid; grid-template-columns: repeat(5, 1fr); gap: .65rem; padding: 0 1rem 1rem; }.clock-metrics article { display: flex; align-items: center; gap: .55rem; min-height: 70px; padding: .65rem; border: 1px solid #e9edf3; border-radius: 14px; background: #fff; box-shadow: 0 5px 14px rgba(35, 48, 72, .04); }.clock-metric-icon { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border-radius: 11px; }.clock-metric-icon--indigo { color: #5145a8; background: #efedff; }.clock-metric-icon--sky { color: #2874a8; background: #eaf6ff; }.clock-metric-icon--teal { color: #08786d; background: #e7f8f4; }.clock-metric-icon--rose { color: #b13b56; background: #ffedf1; }.clock-metric-icon--amber { color: #a35f07; background: #fff4dd; }.clock-metrics small, .clock-metrics strong { display: block; }.clock-metrics small { color: #7a8597; font-size: .57rem; }.clock-metrics strong { margin-top: .1rem; font-size: .82rem; white-space: nowrap; }
.clock-results-empty { display: grid; min-height: 325px; padding: 2rem; place-items: center; align-content: center; text-align: center; background: radial-gradient(circle at center, #f6fbfa 0%, #fff 55%); }.clock-results-empty__icon { display: grid; width: 64px; height: 64px; margin-bottom: .8rem; border: 1px solid #d7ece9; border-radius: 21px; place-items: center; color: #0b8077; background: #eaf8f5; font-size: 1.7rem; box-shadow: 0 10px 25px rgba(21, 112, 103, .1); }.clock-results-empty h3 { margin: 0 0 .3rem; font-size: .92rem; }.clock-results-empty p { max-width: 460px; margin: 0; color: var(--clock-muted); font-size: .7rem; line-height: 1.55; }.clock-results-empty > div { display: flex; flex-wrap: wrap; justify-content: center; gap: .7rem; margin-top: .9rem; color: #647286; font-size: .61rem; }.clock-results-empty > div span { display: inline-flex; align-items: center; gap: .2rem; }.clock-results-empty > div i { color: #0d9488; }.clock-results-empty--complete { background: #fff; }
.clock-table-wrap { overflow-x: auto; transition: opacity .18s; }.clock-table-wrap--loading { opacity: .45; pointer-events: none; }.clock-table { width: 100%; border-collapse: collapse; }.clock-table th { padding: .68rem .85rem; border-block: 1px solid #edf0f4; color: #7d8798; background: #fafbfd; font-size: .58rem; font-weight: 780; letter-spacing: .045em; text-align: left; text-transform: uppercase; }.clock-table td { padding: .78rem .85rem; border-bottom: 1px solid #eff2f5; vertical-align: middle; }.clock-table tbody tr:hover { background: #fbfcfd; }.clock-table time { color: #566377; font-size: .65rem; font-weight: 700; white-space: nowrap; }
.clock-table-person { display: flex; align-items: center; gap: .5rem; min-width: 150px; }.clock-table-person > span { display: grid; flex: 0 0 31px; width: 31px; height: 31px; place-items: center; border-radius: 10px; color: #146b67; background: #e8f6f3; font-size: .6rem; font-weight: 800; }.clock-table-person strong, .clock-table-person small { display: block; }.clock-table-person strong { max-width: 170px; overflow: hidden; color: #283347; font-size: .66rem; text-overflow: ellipsis; white-space: nowrap; }.clock-table-person small { margin-top: .12rem; color: #8b95a5; font-size: .56rem; }.clock-shift { min-width: 115px; }.clock-shift strong, .clock-shift small { display: block; }.clock-shift strong { color: #445064; font-size: .64rem; }.clock-shift small { margin-top: .15rem; color: #929baa; font-size: .56rem; }.clock-punches { display: flex; align-items: center; gap: .26rem; min-width: 135px; }.clock-punches span { display: inline-flex; align-items: center; gap: .15rem; padding: .25rem .34rem; border-radius: 7px; color: #246e6a; background: #eaf7f5; font-size: .55rem; font-weight: 700; }.clock-punches small { color: #7f8999; font-size: .55rem; }.clock-no-data { color: #a0a8b5; font-size: .59rem; }.clock-hours strong, .clock-hours small { display: block; }.clock-hours strong { color: #283347; font-size: .7rem; }.clock-hours small { color: #909aaa; font-size: .53rem; }.clock-status { display: inline-flex; align-items: center; gap: .22rem; padding: .28rem .42rem; border-radius: 999px; font-size: .56rem; font-weight: 750; white-space: nowrap; }.clock-status--success { color: #08785e; background: #e5f8f1; }.clock-status--danger { color: #b4233e; background: #ffe9ee; }.clock-status--warning { color: #9a5b03; background: #fff1cf; }.clock-status--violet { color: #6345a8; background: #f0ebff; }.clock-status--muted { color: #687487; background: #edf1f5; }.clock-detail-button { display: grid; width: 32px; height: 32px; border: 1px solid #dfe5ec; border-radius: 10px; place-items: center; color: #0f766e; background: #fff; }.clock-detail-button:hover { border-color: #a9d8d3; background: #eef9f7; }
.clock-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; border-top: 1px solid #edf0f4; background: #fafbfd; }.clock-pagination > span { color: #7c8798; font-size: .6rem; }.clock-pagination :deep(.pagination) { margin: 0; }
.clock-detail-header { display: flex; align-items: center; gap: .65rem; min-width: 0; }.clock-detail-header > span { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; border-radius: 13px; color: #0f766e; background: #e8f7f4; font-size: 1.15rem; }.clock-detail-header small, .clock-detail-header h2 { display: block; }.clock-detail-header small { color: #7d8797; font-size: .58rem; text-transform: uppercase; }.clock-detail-header h2 { overflow: hidden; margin: .1rem 0 0; color: #263247; font-size: .9rem; text-overflow: ellipsis; white-space: nowrap; }.clock-detail-close { display: grid; width: 34px; height: 34px; margin-left: auto; border: 0; border-radius: 10px; place-items: center; color: #667085; background: #f0f2f5; font-size: 1.15rem; }.clock-detail { padding: 1rem; background: #f8fafc; }.clock-detail__notice { display: flex; align-items: center; gap: .6rem; margin-bottom: .8rem; padding: .7rem .8rem; border: 1px solid #d8e9f1; border-radius: 12px; color: #315b72; background: #f0f8fb; }.clock-detail__notice > i { font-size: 1.2rem; }.clock-detail__notice strong, .clock-detail__notice span { display: block; }.clock-detail__notice strong { font-size: .68rem; }.clock-detail__notice span { margin-top: .1rem; color: #6f8795; font-size: .59rem; }.clock-detail__summary { display: grid; grid-template-columns: repeat(3, 1fr); overflow: hidden; margin-bottom: .8rem; border: 1px solid #e2e7ed; border-radius: 13px; background: #fff; }.clock-detail__summary div { padding: .7rem; border-right: 1px solid #edf0f4; border-bottom: 1px solid #edf0f4; }.clock-detail__summary span, .clock-detail__summary strong { display: block; }.clock-detail__summary span { color: #8b95a5; font-size: .55rem; text-transform: uppercase; }.clock-detail__summary strong { margin-top: .15rem; color: #354055; font-size: .65rem; }.clock-detail__section { margin-top: .8rem; padding: .8rem; border: 1px solid #e3e7ed; border-radius: 13px; background: #fff; }.clock-detail__section > header { display: flex; align-items: center; gap: .5rem; margin-bottom: .65rem; }.clock-detail__section > header > span { display: grid; width: 31px; height: 31px; place-items: center; border-radius: 9px; color: #0f766e; background: #eaf7f5; }.clock-detail__section header small { color: #8b95a5; font-size: .53rem; text-transform: uppercase; }.clock-detail__section header h3 { margin: .08rem 0 0; font-size: .72rem; }.clock-detail__items { display: grid; grid-template-columns: repeat(2, 1fr); gap: .5rem; }.clock-detail__items article { padding: .65rem; border: 1px solid #edf0f4; border-radius: 10px; background: #fafbfd; }.clock-detail__items strong, .clock-detail__items span, .clock-detail__items small { display: block; }.clock-detail__items strong { font-size: .65rem; }.clock-detail__items span { margin-top: .16rem; color: #667286; font-size: .59rem; }.clock-detail__items small { margin-top: .14rem; color: #8a94a4; font-size: .55rem; }.clock-detail__timeline { position: relative; display: grid; gap: .5rem; }.clock-detail__timeline::before { position: absolute; top: 14px; bottom: 14px; left: 14px; width: 1px; background: #cde5e2; content: ""; }.clock-detail__timeline article { position: relative; display: flex; align-items: center; gap: .55rem; }.clock-detail__timeline article > span { z-index: 1; display: grid; flex: 0 0 29px; width: 29px; height: 29px; place-items: center; border: 4px solid #fff; border-radius: 50%; color: #0f766e; background: #e2f3f0; font-size: .7rem; }.clock-detail__timeline strong, .clock-detail__timeline small { display: block; }.clock-detail__timeline strong { color: #3f4b5f; font-size: .62rem; }.clock-detail__timeline small { margin-top: .1rem; color: #8993a3; font-size: .54rem; }.clock-detail__empty { margin: 0; color: #8b95a5; font-size: .61rem; }
.clock-mobile-results { display: grid; gap: .7rem; padding: 0 .85rem .85rem; }.clock-mobile-card { padding: .85rem; border: 1px solid #e5e9ef; border-radius: 14px; background: #fff; box-shadow: 0 5px 16px rgba(35,48,72,.04); }.clock-mobile-card header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .7rem; }.clock-mobile-card header time { color: #667286; font-size: .6rem; font-weight: 700; }.clock-mobile-card dl { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: .5rem; margin: .7rem 0; padding: .6rem; border-radius: 10px; background: #f7f9fb; }.clock-mobile-card dt { color: #8c96a6; font-size: .52rem; }.clock-mobile-card dd { overflow: hidden; margin: .12rem 0 0; color: #3f4b5f; font-size: .6rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }.clock-mobile-card > button { display: flex; align-items: center; gap: .2rem; margin-left: auto; border: 0; color: #0f766e; background: transparent; font-size: .61rem; font-weight: 750; }
.bx-spin { animation: clock-spin .8s linear infinite; } @keyframes clock-spin { to { transform: rotate(360deg); } }
@media (max-width: 1399px) { .clock-query-shell { grid-template-columns: 310px minmax(0, 1fr); }.clock-catalog-strip { grid-template-columns: repeat(4, 1fr); }.clock-catalog-strip__sync { grid-column: 1 / -1; border-top: 1px solid var(--clock-line); }.clock-metrics { grid-template-columns: repeat(3, 1fr); }.clock-query-bar { grid-template-columns: 1fr 1fr auto; }.clock-query-bar__heading { grid-column: 1 / -1; }.clock-query-bar__arrow { display: none; } }
@media (max-width: 991px) { .clock-query-shell { grid-template-columns: 1fr; }.clock-people { position: static; }.clock-people__list { max-height: 360px; }.clock-hero__timepiece { display: none; } }
@media (max-width: 767px) { .clock-hero { min-height: 0; padding: 1.4rem; border-radius: 19px; }.clock-hero h1 { font-size: 2.15rem; }.clock-catalog-strip { grid-template-columns: repeat(2, 1fr); }.clock-catalog-strip > article:nth-child(2) { border-right: 0; }.clock-catalog-strip > article { border-bottom: 1px solid var(--clock-line); }.clock-query-bar { grid-template-columns: 1fr 1fr; }.clock-query-button { grid-column: 1 / -1; }.clock-results-head { align-items: flex-start; flex-direction: column; }.clock-metrics { grid-template-columns: repeat(2, 1fr); }.clock-metrics article:last-child { grid-column: 1 / -1; }.clock-detail__summary { grid-template-columns: repeat(2, 1fr); }.clock-detail__items { grid-template-columns: 1fr; }.clock-alert { align-items: flex-start; flex-wrap: wrap; }.clock-alert button { margin-left: 2rem; } }
@media (max-width: 480px) { .clock-hero__signals { display: grid; gap: .5rem; }.clock-catalog-strip { grid-template-columns: 1fr; }.clock-catalog-strip > article { border-right: 0; }.clock-query-bar { grid-template-columns: 1fr; }.clock-metrics { grid-template-columns: 1fr; }.clock-metrics article:last-child { grid-column: auto; }.clock-detail__summary { grid-template-columns: 1fr; }.clock-pagination { align-items: flex-start; flex-direction: column; }.clock-people__list { max-height: 310px; } }

/* Consulta vertical: selector compacto arriba y resultados debajo. */
.clock-query-panel { position: relative; margin-bottom: 1rem; border: 1px solid var(--clock-line); border-radius: 20px; background: #fff; box-shadow: 0 12px 32px rgba(33, 47, 73, .06); }
.clock-query-panel__header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.2rem .35rem; }
.clock-query-panel__header span:first-child, .clock-results-head > div > span, .clock-weekly header > div > span, .clock-daily-heading span { color: #11827b; font-size: .62rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.clock-query-panel__header h2 { margin: .12rem 0 0; font-size: 1rem; }
.clock-query-panel__header p { margin: .2rem 0 0; color: var(--clock-muted); font-size: .65rem; }
.clock-query-panel__limit { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .7rem; border-radius: 999px; color: #0f766e; background: #e9f8f5; font-size: .66rem; font-weight: 800; white-space: nowrap; }
.clock-query-panel .clock-query-bar { display: grid; grid-template-columns: minmax(285px, 1.5fr) minmax(145px, .7fr) auto minmax(145px, .7fr) auto; align-items: end; gap: .75rem; padding: .8rem 1.2rem 1rem; border: 0; background: transparent; }
.clock-selector-control > label, .clock-query-panel .clock-query-bar > label > span { display: block; margin-bottom: .35rem; color: #687487; font-size: .62rem; font-weight: 750; }
.clock-selector-trigger { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; width: 100%; min-height: 48px; gap: .65rem; padding: .45rem .7rem; border: 1px solid #dce3eb; border-radius: 12px; text-align: left; background: #fbfcfe; }
.clock-selector-trigger:hover, .clock-selector-trigger[aria-expanded="true"] { border-color: #9bd2cd; background: #f5fbfa; box-shadow: 0 0 0 3px rgba(13,148,136,.08); }
.clock-selector-trigger__avatars { display: flex; align-items: center; min-width: 34px; }
.clock-selector-trigger__avatars > i { display: grid; width: 32px; height: 32px; place-items: center; border-radius: 10px; color: #0f766e; background: #e5f5f2; font-size: 1rem; }
.clock-selector-trigger__avatars b { display: grid; width: 29px; height: 29px; margin-left: -7px; border: 2px solid #fff; border-radius: 9px; place-items: center; color: #fff; background: linear-gradient(135deg, #176f70, #0d9488); font-size: .52rem; }
.clock-selector-trigger__avatars b:first-child { margin-left: 0; }
.clock-selector-trigger > span:nth-child(2) { min-width: 0; }
.clock-selector-trigger strong, .clock-selector-trigger small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.clock-selector-trigger strong { color: #263247; font-size: .68rem; }
.clock-selector-trigger small { margin-top: .12rem; color: #8a94a4; font-size: .56rem; }
.clock-selector-trigger > i { color: #718096; font-size: 1rem; }
.clock-selected-chips { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem; padding: 0 1.2rem 1rem; }
.clock-selected-chips > span { display: inline-flex; align-items: center; gap: .25rem; max-width: 210px; padding: .3rem .32rem .3rem .55rem; border: 1px solid #cde7e3; border-radius: 999px; overflow: hidden; color: #176b67; background: #eff9f7; font-size: .58rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.clock-selected-chips > span button { display: grid; flex: 0 0 19px; width: 19px; height: 19px; border: 0; border-radius: 50%; place-items: center; color: #55716f; background: #dbeeea; }
.clock-selected-chips > em { color: #718096; font-size: .58rem; font-style: normal; font-weight: 700; }
.clock-selected-chips > button { margin-left: auto; border: 0; color: #8b5361; background: transparent; font-size: .6rem; font-weight: 750; }
.clock-query-panel .clock-people { position: static; overflow: hidden; margin: 0 1.2rem 1.1rem; border-color: #dfe7ed; border-radius: 15px; box-shadow: 0 12px 28px rgba(32, 47, 70, .08); }
.clock-people__tools { display: grid; grid-template-columns: minmax(260px, 1.5fr) minmax(230px, .8fr) auto auto; align-items: center; gap: .65rem; padding: .7rem; border-bottom: 1px solid #e9edf2; background: #f8fafc; }
.clock-query-panel .clock-people__search, .clock-query-panel .clock-segmented { margin: 0; }
.clock-query-panel .clock-people__list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .35rem; max-height: 325px; padding: .6rem; }
.clock-query-panel .clock-person { grid-template-columns: auto minmax(0, 1fr) auto; min-height: 74px; border-color: #edf0f4; }
.clock-query-panel .clock-person__identity strong { max-width: 100%; }
.clock-people__bulk-action, .clock-people__done { display: inline-flex; align-items: center; justify-content: center; gap: .25rem; min-height: 37px; padding: .45rem .65rem; border-radius: 9px; font-size: .6rem; font-weight: 750; white-space: nowrap; }
.clock-people__bulk-action { border: 1px solid #dce3ea; color: #226d69; background: #fff; }
.clock-people__done { border: 0; color: #fff; background: #0f766e; }
.clock-workspace { margin-bottom: 1rem; }
.clock-results-head__actions { display: flex; align-items: center; gap: .8rem; }
.clock-pdf-button { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; min-height: 38px; padding: .5rem .75rem; border: 1px solid #f1c7cc; border-radius: 10px; color: #b4233e; background: #fff7f8; font-size: .63rem; font-weight: 800; }
.clock-pdf-button:hover { color: #fff; background: #b4233e; }
.clock-pdf-button:disabled { opacity: .55; }
.clock-variance-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: .65rem; margin: 0 1rem 1rem; padding: .7rem; border-radius: 14px; background: #f6f8fb; }
.clock-variance-summary article { display: flex; align-items: center; gap: .5rem; padding: .65rem; border: 1px solid #e7ebf0; border-radius: 11px; background: #fff; }
.clock-variance-summary article > i { display: grid; flex: 0 0 31px; width: 31px; height: 31px; place-items: center; border-radius: 9px; font-size: 1rem; }
.clock-variance-summary small, .clock-variance-summary strong { display: block; }
.clock-variance-summary small { color: #7e8999; font-size: .54rem; }
.clock-variance-summary strong { margin-top: .1rem; color: #263247; font-size: .72rem; }
.clock-variance-summary--good > i { color: #08785e; background: #e6f7f1; }
.clock-variance-summary--bad > i { color: #b4233e; background: #ffedf1; }
.clock-weekly { margin: .2rem 1rem 1rem; overflow: hidden; border: 1px solid #dfe7ed; border-radius: 15px; }
.clock-weekly > header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem .9rem; background: linear-gradient(110deg, #f2faf8, #f7f9fc); }
.clock-weekly h3, .clock-daily-heading h3 { margin: .1rem 0 0; color: #263247; font-size: .78rem; }
.clock-weekly header p { margin: .15rem 0 0; color: #7b8797; font-size: .56rem; }
.clock-weekly__count { padding: .3rem .55rem; border-radius: 999px; color: #176b67; background: #dff3ef; font-size: .56rem; font-weight: 750; white-space: nowrap; }
.clock-weekly__table-wrap { overflow-x: auto; }
.clock-weekly table { width: 100%; border-collapse: collapse; }
.clock-weekly th { padding: .55rem .7rem; border-bottom: 1px solid #e8edf1; color: #7c8798; background: #fff; font-size: .52rem; text-align: left; text-transform: uppercase; white-space: nowrap; }
.clock-weekly td { padding: .65rem .7rem; border-bottom: 1px solid #eef1f4; color: #3d495c; font-size: .6rem; }
.clock-weekly tr:last-child td { border-bottom: 0; }
.clock-weekly td strong, .clock-weekly td small { display: block; }
.clock-weekly td small { margin-top: .12rem; color: #8a94a4; font-size: .52rem; }
.clock-weekly-value { display: inline-flex; min-width: 48px; padding: .27rem .38rem; border-radius: 7px; justify-content: center; font-size: .55rem; font-weight: 750; white-space: nowrap; }
.clock-weekly-value--good { color: #08785e; background: #e5f8f1; }
.clock-weekly-value--bad { color: #b4233e; background: #ffedf1; }
.clock-daily-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: .25rem 1rem .65rem; }
.clock-daily-heading > small { color: #8a94a4; font-size: .52rem; }
.clock-mark { min-width: 104px; }
.clock-mark > strong { display: block; margin-bottom: .25rem; color: #263247; font-size: .68rem; }
.clock-deviation { display: inline-flex; align-items: center; gap: .15rem; padding: .23rem .34rem; border-radius: 7px; font-size: .52rem; font-weight: 750; white-space: nowrap; }
.clock-deviation--positive, .clock-deviation--exact { color: #08785e; background: #e5f8f1; }
.clock-deviation--negative { color: #b4233e; background: #ffedf1; }
.clock-deviation--muted { color: #718096; background: #edf1f5; }

@media (max-width: 1199px) {
  .clock-query-panel .clock-query-bar { grid-template-columns: minmax(250px, 1.25fr) minmax(135px, .7fr) minmax(135px, .7fr) auto; }
  .clock-query-panel .clock-query-bar__arrow { display: none; }
  .clock-query-panel .clock-people__list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .clock-people__tools { grid-template-columns: 1fr 250px auto; }
  .clock-people__done { grid-column: 3; }
  .clock-variance-summary { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
  .clock-query-panel__header { align-items: flex-start; }
  .clock-query-panel__header p { display: none; }
  .clock-query-panel .clock-query-bar { grid-template-columns: 1fr 1fr; }
  .clock-selector-control, .clock-query-panel .clock-query-button { grid-column: 1 / -1; }
  .clock-people__tools { grid-template-columns: 1fr 1fr; }
  .clock-people__tools .clock-people__search { grid-column: 1 / -1; }
  .clock-query-panel .clock-people__list { grid-template-columns: 1fr; }
  .clock-results-head__actions { width: 100%; justify-content: space-between; }
  .clock-variance-summary { grid-template-columns: 1fr 1fr; }
  .clock-daily-heading { align-items: flex-start; flex-direction: column; }
  .clock-mobile-card dl { grid-template-columns: repeat(3, 1fr); }
  .clock-mobile-card .clock-deviation { margin-top: .25rem; }
}
@media (max-width: 480px) {
  .clock-query-panel .clock-query-bar, .clock-people__tools, .clock-variance-summary { grid-template-columns: 1fr; }
  .clock-query-panel .clock-query-bar > label, .clock-selector-control, .clock-query-panel .clock-query-button, .clock-people__tools .clock-people__search { grid-column: auto; }
  .clock-query-panel .clock-people { margin-inline: .7rem; }
  .clock-selected-chips { padding-inline: .8rem; }
  .clock-people__done { grid-column: auto; }
  .clock-results-head__actions { align-items: stretch; flex-direction: column; }
  .clock-pdf-button { width: 100%; }
  .clock-mobile-card dl { grid-template-columns: 1fr; }
}

.clock-mode-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; margin-bottom: 1rem; padding: .35rem; border: 1px solid var(--clock-line); border-radius: 17px; background: #f1f4f7; }
.clock-mode-tabs > button { position: relative; display: flex; align-items: center; gap: .65rem; min-height: 60px; padding: .65rem .85rem; border: 1px solid transparent; border-radius: 13px; text-align: left; color: #6f7a8d; background: transparent; transition: .18s ease; }
.clock-mode-tabs > button.active { border-color: #dbe7e8; color: #155f5d; background: #fff; box-shadow: 0 6px 18px rgba(35, 48, 72, .07); }
.clock-mode-tabs > button > span { display: grid; flex: 0 0 37px; width: 37px; height: 37px; border-radius: 11px; place-items: center; color: #647286; background: #e4e8ed; font-size: 1.05rem; }
.clock-mode-tabs > button.active > span { color: #0f766e; background: #e3f5f2; }
.clock-mode-tabs strong, .clock-mode-tabs small { display: block; }
.clock-mode-tabs strong { color: #283347; font-size: .7rem; }
.clock-mode-tabs small { margin-top: .12rem; color: #8590a1; font-size: .56rem; }
.clock-mode-tabs em { margin-left: auto; padding: .22rem .4rem; border-radius: 999px; color: #0f766e; background: #dff3ef; font-size: .5rem; font-style: normal; font-weight: 800; text-transform: uppercase; }
.clock-report-center { overflow: hidden; border: 1px solid var(--clock-line); border-radius: 21px; background: #fff; box-shadow: 0 14px 34px rgba(33, 47, 73, .07); }
.clock-report-center__hero { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.4rem; color: #fff; background: linear-gradient(115deg, #12395c, #0e6265); }
.clock-report-center__hero > div:first-child > span { display: inline-flex; align-items: center; gap: .3rem; color: #9de3dc; font-size: .58rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.clock-report-center__hero h2 { margin: .22rem 0 0; color: #fff; font-size: 1.25rem; }
.clock-report-center__hero p { max-width: 750px; margin: .3rem 0 0; color: rgba(255,255,255,.72); font-size: .65rem; line-height: 1.5; }
.clock-report-center__badge { display: grid; grid-template-columns: auto auto; align-items: center; gap: 0 .45rem; min-width: 155px; padding: .65rem .8rem; border: 1px solid rgba(255,255,255,.16); border-radius: 13px; background: rgba(255,255,255,.08); }
.clock-report-center__badge > i { grid-row: 1 / 3; font-size: 1.5rem; color: #8ee3d9; }
.clock-report-center__badge strong { color: #fff; font-size: 1rem; }
.clock-report-center__badge small { color: rgba(255,255,255,.65); font-size: .52rem; }
.clock-report-form { display: grid; grid-template-columns: minmax(210px, 1.2fr) repeat(3, minmax(135px, .75fr)) auto; align-items: end; gap: .7rem; padding: 1rem 1.2rem .65rem; background: #f8fafc; }
.clock-report-form label > span { display: block; margin-bottom: .35rem; color: #687487; font-size: .58rem; font-weight: 750; }
.clock-report-form .form-control, .clock-report-form .form-select { min-height: 41px; border-color: #dce3eb; border-radius: 10px; color: #354055; background-color: #fff; font-size: .65rem; }
.clock-report-form > button, .clock-report-result-head > button { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; min-height: 41px; padding: .55rem .8rem; border: 0; border-radius: 10px; color: #fff; background: linear-gradient(135deg, #0f766e, #0d9488); box-shadow: 0 8px 18px rgba(13,148,136,.18); font-size: .64rem; font-weight: 800; white-space: nowrap; }
.clock-report-form > button:disabled, .clock-report-result-head > button:disabled { opacity: .55; }
.clock-report-form__note { display: flex; align-items: flex-start; gap: .35rem; padding: 0 1.2rem .85rem; color: #738094; background: #f8fafc; font-size: .55rem; }
.clock-report-form__note i { color: #0f766e; font-size: .8rem; }
.clock-report-empty { display: grid; min-height: 360px; padding: 2rem; place-items: center; align-content: center; text-align: center; background: radial-gradient(circle at center, #f2faf8, #fff 58%); }
.clock-report-empty > span { display: grid; width: 65px; height: 65px; margin-bottom: .8rem; border: 1px solid #cfe8e4; border-radius: 20px; place-items: center; color: #0f766e; background: #e7f6f3; font-size: 1.7rem; }
.clock-report-empty h3 { margin: 0; color: #283347; font-size: .92rem; }
.clock-report-empty p { max-width: 570px; margin: .35rem 0 0; color: #7c8798; font-size: .65rem; line-height: 1.5; }
.clock-report-empty > div { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin-top: .9rem; }
.clock-report-empty em { display: inline-flex; align-items: center; gap: .24rem; padding: .35rem .55rem; border-radius: 999px; color: #446276; background: #edf3f5; font-size: .56rem; font-style: normal; font-weight: 700; }
.clock-report-empty em i { color: #0f766e; }
.clock-report-result-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.2rem .7rem; }
.clock-report-result-head span { color: #0f8179; font-size: .55rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.clock-report-result-head h3 { margin: .12rem 0 0; color: #263247; font-size: .86rem; }
.clock-report-result-head p { margin: .18rem 0 0; color: #7e8999; font-size: .55rem; }
.clock-report-result-head > button { color: #b4233e; border: 1px solid #f0c6cc; background: #fff7f8; box-shadow: none; }
.clock-report-result-head > button:hover { color: #fff; background: #b4233e; }
.clock-report-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .55rem; padding: 0 1.1rem .9rem; }
.clock-report-kpis article { display: flex; align-items: center; min-height: 70px; gap: .5rem; padding: .6rem; border: 1px solid #e8ecf1; border-radius: 13px; box-shadow: 0 4px 12px rgba(35,48,72,.035); }
.clock-report-kpis__icon { display: grid; flex: 0 0 32px; width: 32px; height: 32px; border-radius: 10px; place-items: center; }
.clock-report-kpis__icon--blue { color: #4267a9; background: #eaf1ff; }.clock-report-kpis__icon--amber { color: #a35f07; background: #fff4dd; }.clock-report-kpis__icon--rose, .clock-report-kpis__icon--red { color: #b4233e; background: #ffedf1; }.clock-report-kpis__icon--teal, .clock-report-kpis__icon--green { color: #08785e; background: #e5f8f1; }
.clock-report-kpis small, .clock-report-kpis strong, .clock-report-kpis em { display: block; }
.clock-report-kpis small { color: #7b8798; font-size: .5rem; line-height: 1.25; }
.clock-report-kpis strong { margin-top: .12rem; color: #263247; font-size: .75rem; }
.clock-report-kpis em { color: #8c96a6; font-size: .48rem; font-style: normal; }
.clock-coverage-strip { display: grid; grid-template-columns: repeat(5, 1fr); margin: 0 1.1rem .9rem; overflow: hidden; border: 1px solid #e1e7ec; border-radius: 12px; background: #f8fafc; }
.clock-coverage-strip div { padding: .55rem .65rem; border-right: 1px solid #e4e9ee; }
.clock-coverage-strip div:last-child { border-right: 0; }
.clock-coverage-strip span, .clock-coverage-strip strong { display: block; }
.clock-coverage-strip span { color: #8590a1; font-size: .48rem; }
.clock-coverage-strip strong { margin-top: .12rem; color: #445064; font-size: .57rem; }
.clock-report-tabs { display: flex; gap: .35rem; padding: .55rem 1rem; border-block: 1px solid #e6ebf0; background: #f6f8fb; }
.clock-report-tabs button { display: inline-flex; align-items: center; gap: .3rem; min-height: 36px; padding: .4rem .6rem; border: 1px solid transparent; border-radius: 9px; color: #6f7b8e; background: transparent; font-size: .57rem; font-weight: 750; }
.clock-report-tabs button.active { border-color: #cae5e1; color: #0f716b; background: #fff; box-shadow: 0 3px 10px rgba(35,48,72,.05); }
.clock-report-tabs button em { display: grid; min-width: 20px; height: 20px; padding: 0 .25rem; border-radius: 999px; place-items: center; color: #607083; background: #e4e9ee; font-size: .48rem; font-style: normal; }
.clock-report-tabs button.active em { color: #0f716b; background: #def2ee; }
.clock-report-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .7rem 1.1rem; }
.clock-report-toolbar span, .clock-report-toolbar strong { display: block; }
.clock-report-toolbar span { color: #7d8899; font-size: .5rem; text-transform: uppercase; }
.clock-report-toolbar strong { margin-top: .08rem; color: #354055; font-size: .65rem; }
.clock-report-toolbar label { display: flex; align-items: center; width: min(260px, 100%); border: 1px solid #dce3ea; border-radius: 9px; background: #fff; }
.clock-report-toolbar label i { margin-left: .55rem; color: #8590a1; }
.clock-report-toolbar input { min-width: 0; width: 100%; padding: .45rem .55rem; border: 0; outline: 0; color: #354055; background: transparent; font-size: .58rem; }
.clock-report-table-wrap { max-height: 620px; overflow: auto; border-top: 1px solid #edf0f4; scrollbar-width: thin; }
.clock-report-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.clock-report-table th { position: sticky; z-index: 1; top: 0; padding: .65rem .75rem; border-bottom: 1px solid #e5e9ee; color: #748094; background: #f8fafc; font-size: .5rem; font-weight: 800; letter-spacing: .04em; text-align: left; text-transform: uppercase; white-space: nowrap; }
.clock-report-table td { padding: .7rem .75rem; border-bottom: 1px solid #edf0f4; color: #4a5669; font-size: .58rem; vertical-align: middle; }
.clock-report-table tbody tr:hover { background: #fbfdfd; }
.clock-report-table td > small { display: block; margin-top: .15rem; color: #8b95a5; font-size: .47rem; }
.clock-report-person { display: flex; align-items: center; min-width: 185px; gap: .5rem; }
.clock-report-person > span { display: grid; flex: 0 0 31px; width: 31px; height: 31px; border-radius: 10px; place-items: center; color: #0f716b; background: #e5f5f2; font-size: .53rem; font-weight: 800; }
.clock-report-person strong, .clock-report-person small { display: block; }
.clock-report-person strong { max-width: 210px; overflow: hidden; color: #2e394c; font-size: .6rem; text-overflow: ellipsis; white-space: nowrap; }
.clock-report-person small { margin-top: .1rem; color: #8993a3; font-size: .47rem; }
.clock-report-pill { display: inline-flex; min-width: 45px; padding: .25rem .37rem; border-radius: 7px; justify-content: center; font-size: .52rem; font-weight: 800; white-space: nowrap; }
.clock-report-pill--good { color: #08785e; background: #e5f8f1; }.clock-report-pill--bad { color: #b4233e; background: #ffedf1; }.clock-report-pill--absence { color: #9b4d16; background: #fff0df; }
.clock-report-net { display: inline-flex; padding: .28rem .42rem; border-radius: 8px; font-size: .54rem; font-weight: 800; }.clock-report-net--positive { color: #08785e; background: #e5f8f1; }.clock-report-net--negative { color: #b4233e; background: #ffedf1; }
.clock-report-dates { display: grid; min-width: 190px; gap: .15rem; }
.clock-report-dates span { color: #657286; font-size: .49rem; white-space: nowrap; }
.clock-report-dates span b { color: #3e4a5d; }.clock-report-dates em { color: #0f766e; font-size: .47rem; font-style: normal; font-weight: 700; }
.clock-report-table-empty { display: grid; min-height: 235px; padding: 1.5rem; place-items: center; align-content: center; color: #80909d; text-align: center; }
.clock-report-table-empty > i { margin-bottom: .5rem; color: #0f8b7f; font-size: 1.8rem; }.clock-report-table-empty strong, .clock-report-table-empty span { display: block; }.clock-report-table-empty strong { color: #445064; font-size: .72rem; }.clock-report-table-empty span { margin-top: .2rem; font-size: .55rem; }
@media (max-width: 1199px) { .clock-report-form { grid-template-columns: repeat(2, 1fr); }.clock-report-form > button { grid-column: 1 / -1; }.clock-report-kpis { grid-template-columns: repeat(3, 1fr); }.clock-coverage-strip { grid-template-columns: repeat(3, 1fr); }.clock-coverage-strip div { border-bottom: 1px solid #e4e9ee; } }
@media (max-width: 767px) { .clock-mode-tabs { grid-template-columns: 1fr; }.clock-report-center__hero { align-items: flex-start; }.clock-report-center__badge { display: none; }.clock-report-form { grid-template-columns: 1fr; }.clock-report-form > button { grid-column: auto; }.clock-report-kpis { grid-template-columns: repeat(2, 1fr); }.clock-coverage-strip { grid-template-columns: 1fr 1fr; }.clock-report-tabs { overflow-x: auto; }.clock-report-tabs button { flex: 0 0 auto; }.clock-report-toolbar { align-items: flex-start; flex-direction: column; }.clock-report-toolbar label { width: 100%; }.clock-report-result-head { align-items: flex-start; flex-direction: column; }.clock-report-result-head > button { width: 100%; } }
@media (max-width: 480px) { .clock-mode-tabs small { display: none; }.clock-report-center__hero { padding: 1rem; }.clock-report-kpis, .clock-coverage-strip { grid-template-columns: 1fr; } }
</style>
