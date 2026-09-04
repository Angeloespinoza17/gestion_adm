<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      detailLoading: false,
      error: "",
      users: [],
      groups: {},
      summary: {
        total_users: 0,
        active_accounts: 0,
        users_with_usage: 0,
        users_without_usage: 0,
        adoption_rate: 0,
        login_count: 0,
        usage_count: 0,
        active_days: 0,
        last_activity_at: null,
      },
      period: { key: "30", label: "Últimos 30 días", from: null, to: null },
      tracking: { started_at: null, activity_window_minutes: 10, historical_note: "" },
      pagination: { current_page: 1, last_page: 1, total: 0, per_page: 20, from: null, to: null },
      filters: {
        group: "staff",
        period: "30",
        search: "",
        account_status: "",
        usage_status: "",
        sort: "last_activity",
        direction: "desc",
      },
      selectedUser: null,
      userDetail: null,
      showDetail: false,
    };
  },
  computed: {
    groupCards() {
      const definitions = {
        staff: { label: "Funcionarios", icon: "bx-id-card", description: "Equipo directivo, docente y administrativo" },
        student: { label: "Estudiantes", icon: "bx-book-reader", description: "Cuentas vinculadas a perfiles estudiantiles" },
      };

      return Object.entries(definitions).map(([key, definition]) => ({
        key,
        ...definition,
        total_users: 0,
        active_accounts: 0,
        users_with_usage: 0,
        adoption_rate: 0,
        login_count: 0,
        usage_count: 0,
        ...(this.groups[key] || {}),
      }));
    },
    currentGroupLabel() {
      return this.filters.group === "student" ? "estudiantes" : "funcionarios";
    },
    rangeLabel() {
      if (!this.pagination.total) return `Sin ${this.currentGroupLabel} para los filtros aplicados`;
      return `Mostrando ${this.pagination.from}–${this.pagination.to} de ${this.pagination.total} ${this.currentGroupLabel}`;
    },
    activeFilterCount() {
      return [this.filters.search, this.filters.account_status, this.filters.usage_status]
        .filter((value) => String(value || "").trim() !== "").length;
    },
    timelineMax() {
      return Math.max(...(this.userDetail?.timeline || []).map((item) => Number(item.usage || 0)), 1);
    },
  },
  mounted() {
    this.loadUsers();
  },
  methods: {
    async loadUsers(page = 1) {
      this.loading = true;
      this.error = "";

      try {
        const params = {
          page,
          per_page: this.pagination.per_page,
          ...this.filters,
        };
        Object.keys(params).forEach((key) => {
          if (params[key] === "") delete params[key];
        });

        const { data } = await axios.get("/api/superadmin/usage-level/users", { params });
        this.users = data.data || [];
        this.groups = data.groups || {};
        this.summary = { ...this.summary, ...(data.summary || {}) };
        this.period = data.period || this.period;
        this.tracking = data.tracking || this.tracking;
        this.pagination = {
          current_page: data.current_page || 1,
          last_page: data.last_page || 1,
          total: data.total || 0,
          per_page: data.per_page || this.pagination.per_page,
          from: data.from,
          to: data.to,
        };
      } catch (error) {
        this.error = error.response?.data?.message || "No fue posible consultar el nivel de uso. Intenta nuevamente.";
      } finally {
        this.loading = false;
      }
    },
    selectGroup(group) {
      if (this.filters.group === group) return;
      this.filters.group = group;
      this.loadUsers(1);
    },
    selectPeriod(period) {
      if (this.filters.period === period) return;
      this.filters.period = period;
      this.loadUsers(1);
    },
    applyFilters() {
      this.loadUsers(1);
    },
    clearFilters() {
      this.filters.search = "";
      this.filters.account_status = "";
      this.filters.usage_status = "";
      this.filters.sort = "last_activity";
      this.filters.direction = "desc";
      this.loadUsers(1);
    },
    changeSort() {
      this.filters.direction = this.filters.sort === "name" ? "asc" : "desc";
      this.loadUsers(1);
    },
    async openUser(user) {
      this.selectedUser = user;
      this.userDetail = null;
      this.showDetail = true;
      this.detailLoading = true;

      try {
        const { data } = await axios.get(`/api/superadmin/usage-level/users/${user.id}`, {
          params: { period: this.filters.period },
        });
        this.userDetail = data;
      } catch (error) {
        this.error = error.response?.data?.message || "No fue posible cargar el detalle de uso.";
        this.closeDetail();
      } finally {
        this.detailLoading = false;
      }
    },
    closeDetail() {
      this.showDetail = false;
      this.selectedUser = null;
      this.userDetail = null;
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatDate(value, includeTime = true) {
      if (!value) return "Sin actividad registrada";
      const raw = String(value);
      const normalized = /^\d{4}-\d{2}-\d{2}$/.test(raw)
        ? `${raw}T12:00:00`
        : (/T|Z$/.test(raw) ? raw : raw.replace(" ", "T"));
      const date = new Date(normalized);
      if (Number.isNaN(date.getTime())) return value;

      return new Intl.DateTimeFormat("es-CL", includeTime
        ? { dateStyle: "medium", timeStyle: "short" }
        : { day: "2-digit", month: "short" }).format(date);
    },
    initials(name) {
      return String(name || "U")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
    },
    usageLevel(user) {
      if (!user.last_activity_at) return { label: "Sin uso", className: "usage-level--none" };
      if (Number(user.active_days) >= 10 || Number(user.usage_count) >= 20) {
        return { label: "Uso alto", className: "usage-level--high" };
      }
      if (Number(user.active_days) >= 4 || Number(user.usage_count) >= 8) {
        return { label: "Uso medio", className: "usage-level--medium" };
      }
      return { label: "Uso inicial", className: "usage-level--low" };
    },
    timelineWidth(item) {
      return `${Math.max((Number(item.usage || 0) / this.timelineMax) * 100, 7)}%`;
    },
  },
};
</script>

<template>
  <Layout>
    <main class="usage-page">
      <section class="usage-hero">
        <div class="usage-hero__orb usage-hero__orb--one"></div>
        <div class="usage-hero__orb usage-hero__orb--two"></div>
        <div class="usage-hero__content">
          <div class="usage-eyebrow"><i class="bx bx-lock-alt"></i> Supervisión exclusiva de Superadmin</div>
          <h1>Nivel de uso</h1>
          <p>Consulta quiénes están utilizando el sistema, cuántas veces ingresan y con qué continuidad, separado entre funcionarios y estudiantes.</p>
          <div class="usage-hero__trust">
            <span><i class="bx bx-show"></i> Sólo lectura</span>
            <span><i class="bx bx-shield-quarter"></i> Sin contenido de navegación</span>
            <span><i class="bx bx-time-five"></i> Actividad agregada</span>
          </div>
        </div>
        <button type="button" class="usage-refresh" :disabled="loading" @click="loadUsers(pagination.current_page)">
          <i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i>
          Actualizar datos
        </button>
      </section>

      <BAlert v-if="error" show variant="danger" class="usage-alert">
        <i class="bx bx-error-circle"></i><span>{{ error }}</span>
      </BAlert>

      <section class="usage-groups" aria-label="Separación de usuarios">
        <button
          v-for="group in groupCards"
          :key="group.key"
          type="button"
          class="usage-segment"
          :class="[`usage-segment--${group.key}`, { 'is-active': filters.group === group.key }]"
          @click="selectGroup(group.key)"
        >
          <span class="usage-segment__icon"><i class="bx" :class="group.icon"></i></span>
          <span class="usage-segment__body">
            <span class="usage-segment__top"><strong>{{ group.label }}</strong><em>{{ formatNumber(group.total_users) }} cuentas</em></span>
            <small>{{ group.description }}</small>
            <span class="usage-segment__progress"><i :style="{ width: `${group.adoption_rate}%` }"></i></span>
            <span class="usage-segment__foot">
              <b>{{ group.adoption_rate }}%</b> con uso
              <span>{{ formatNumber(group.login_count) }} ingresos</span>
            </span>
          </span>
          <i class="bx bx-chevron-right usage-segment__arrow"></i>
        </button>
      </section>

      <section class="usage-period" aria-label="Período de análisis">
        <div>
          <span>Período observado</span>
          <strong>{{ period.label }}</strong>
        </div>
        <div class="usage-period__options">
          <button v-for="option in [{ key: '30', label: '30 días' }, { key: '90', label: '90 días' }, { key: '365', label: '12 meses' }, { key: 'all', label: 'Todo' }]"
            :key="option.key" type="button" :class="{ 'is-active': filters.period === option.key }" @click="selectPeriod(option.key)">
            {{ option.label }}
          </button>
        </div>
      </section>

      <section class="usage-metrics" aria-label="Resumen del nivel de uso">
        <article class="usage-metric usage-metric--violet">
          <span class="usage-metric__icon"><i class="bx bx-user-check"></i></span>
          <div><small>Con actividad</small><strong>{{ formatNumber(summary.users_with_usage) }}</strong><span>de {{ formatNumber(summary.total_users) }} cuentas</span></div>
        </article>
        <article class="usage-metric usage-metric--blue">
          <span class="usage-metric__icon"><i class="bx bx-log-in-circle"></i></span>
          <div><small>Ingresos exitosos</small><strong>{{ formatNumber(summary.login_count) }}</strong><span>en el período</span></div>
        </article>
        <article class="usage-metric usage-metric--teal">
          <span class="usage-metric__icon"><i class="bx bx-pulse"></i></span>
          <div><small>Bloques de actividad</small><strong>{{ formatNumber(summary.usage_count) }}</strong><span>ventanas de {{ tracking.activity_window_minutes }} min</span></div>
        </article>
        <article class="usage-metric usage-metric--amber">
          <span class="usage-metric__icon"><i class="bx bx-bar-chart-alt-2"></i></span>
          <div><small>Adopción</small><strong>{{ summary.adoption_rate }}%</strong><span>{{ formatNumber(summary.users_without_usage) }} sin uso</span></div>
        </article>
      </section>

      <section class="usage-workspace">
        <header class="usage-workspace__header">
          <div>
            <span class="usage-section-kicker">Directorio de {{ currentGroupLabel }}</span>
            <h2>Selecciona una cuenta para ver su actividad</h2>
            <p>{{ rangeLabel }}</p>
          </div>
          <span class="usage-readonly"><i class="bx bx-lock"></i> Consulta protegida</span>
        </header>

        <form class="usage-filters" @submit.prevent="applyFilters">
          <label class="usage-search">
            <i class="bx bx-search"></i>
            <input v-model="filters.search" type="search" placeholder="Buscar por nombre, correo o RUT" />
          </label>
          <label>
            <span>Estado de cuenta</span>
            <select v-model="filters.account_status" @change="applyFilters">
              <option value="">Todos</option>
              <option value="active">Activas</option>
              <option value="inactive">Inactivas</option>
            </select>
          </label>
          <label>
            <span>Actividad</span>
            <select v-model="filters.usage_status" @change="applyFilters">
              <option value="">Con y sin uso</option>
              <option value="with_usage">Con uso</option>
              <option value="without_usage">Sin uso</option>
            </select>
          </label>
          <label>
            <span>Ordenar por</span>
            <select v-model="filters.sort" @change="changeSort">
              <option value="last_activity">Actividad reciente</option>
              <option value="logins">Más ingresos</option>
              <option value="usage">Mayor uso</option>
              <option value="active_days">Más días activos</option>
              <option value="name">Nombre</option>
            </select>
          </label>
          <button type="submit" class="usage-filter-button"><i class="bx bx-search"></i> Buscar</button>
          <button v-if="activeFilterCount" type="button" class="usage-clear-button" @click="clearFilters">
            Limpiar {{ activeFilterCount }}
          </button>
        </form>

        <LoadingState v-if="loading" class="usage-loading" />

        <template v-else>
          <div v-if="users.length" class="usage-table-wrap">
            <table class="usage-table">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Nivel</th>
                  <th>Ingresos</th>
                  <th>Actividad</th>
                  <th>Días activos</th>
                  <th>Último uso</th>
                  <th><span class="visually-hidden">Seleccionar</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in users" :key="user.id">
                  <td>
                    <div class="usage-person">
                      <span class="usage-avatar" :class="`usage-avatar--${user.group}`">{{ initials(user.name) }}</span>
                      <div><strong>{{ user.name }}</strong><span>{{ user.email }}</span><small>{{ user.profile_label }}</small></div>
                    </div>
                  </td>
                  <td><span class="usage-level" :class="usageLevel(user).className">{{ usageLevel(user).label }}</span></td>
                  <td><strong class="usage-number">{{ formatNumber(user.login_count) }}</strong></td>
                  <td><strong class="usage-number">{{ formatNumber(user.usage_count) }}</strong><small class="usage-subvalue">bloques</small></td>
                  <td><strong class="usage-number">{{ formatNumber(user.active_days) }}</strong><small class="usage-subvalue">días</small></td>
                  <td><span class="usage-last">{{ formatDate(user.last_activity_at) }}</span></td>
                  <td>
                    <button type="button" class="usage-select-button" @click="openUser(user)">
                      Ver detalle <i class="bx bx-right-arrow-alt"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="users.length" class="usage-mobile-list">
            <article v-for="user in users" :key="`mobile-${user.id}`" class="usage-mobile-card">
              <header>
                <span class="usage-avatar" :class="`usage-avatar--${user.group}`">{{ initials(user.name) }}</span>
                <div><strong>{{ user.name }}</strong><span>{{ user.email }}</span></div>
                <span class="usage-level" :class="usageLevel(user).className">{{ usageLevel(user).label }}</span>
              </header>
              <div class="usage-mobile-card__metrics">
                <span><small>Ingresos</small><b>{{ formatNumber(user.login_count) }}</b></span>
                <span><small>Actividad</small><b>{{ formatNumber(user.usage_count) }}</b></span>
                <span><small>Días activos</small><b>{{ formatNumber(user.active_days) }}</b></span>
              </div>
              <p><i class="bx bx-time-five"></i>{{ formatDate(user.last_activity_at) }}</p>
              <button type="button" class="usage-select-button" @click="openUser(user)">Seleccionar usuario <i class="bx bx-right-arrow-alt"></i></button>
            </article>
          </div>

          <div v-if="!users.length" class="usage-empty">
            <span><i class="bx bx-user-x"></i></span>
            <h3>No hay cuentas para mostrar</h3>
            <p>Prueba otro grupo o limpia los filtros de búsqueda.</p>
            <button v-if="activeFilterCount" type="button" @click="clearFilters">Limpiar filtros</button>
          </div>

          <footer v-if="pagination.last_page > 1" class="usage-pagination">
            <span>{{ rangeLabel }}</span>
            <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadUsers" />
          </footer>
        </template>
      </section>

      <aside class="usage-method-note">
        <span><i class="bx bx-info-circle"></i></span>
        <div>
          <strong>Cómo se mide el uso</strong>
          <p>{{ tracking.historical_note }} Cada ingreso exitoso se cuenta una vez y la actividad autenticada se agrega en bloques de {{ tracking.activity_window_minutes }} minutos, sin guardar las páginas ni los contenidos consultados.</p>
        </div>
      </aside>

      <div v-if="showDetail" class="usage-detail-backdrop" role="presentation" @click.self="closeDetail">
        <section class="usage-detail" role="dialog" aria-modal="true" aria-labelledby="usage-detail-title">
          <header class="usage-detail__header">
            <div class="usage-person usage-person--detail">
              <span class="usage-avatar usage-avatar--large" :class="`usage-avatar--${selectedUser?.group}`">{{ initials(selectedUser?.name) }}</span>
              <div>
                <span class="usage-section-kicker">Detalle individual · Sólo lectura</span>
                <h2 id="usage-detail-title">{{ selectedUser?.name }}</h2>
                <p>{{ selectedUser?.email }}</p>
              </div>
            </div>
            <button type="button" class="usage-detail__close" aria-label="Cerrar detalle" @click="closeDetail"><i class="bx bx-x"></i></button>
          </header>

          <LoadingState v-if="detailLoading" class="usage-loading" />

          <div v-else-if="userDetail" class="usage-detail__body">
            <div class="usage-detail__status">
              <span :class="userDetail.user.active ? 'is-active' : 'is-inactive'">
                <i class="bx" :class="userDetail.user.active ? 'bx-check-circle' : 'bx-block'"></i>
                Cuenta {{ userDetail.user.active ? 'activa' : 'inactiva' }}
              </span>
              <span><i class="bx bx-briefcase-alt-2"></i>{{ userDetail.user.profile_label }}</span>
              <span><i class="bx bx-calendar"></i>{{ userDetail.period.label }}</span>
            </div>

            <div class="usage-detail__metrics">
              <article><small>Ingresos</small><strong>{{ formatNumber(userDetail.totals.login_count) }}</strong><i class="bx bx-log-in-circle"></i></article>
              <article><small>Actividad</small><strong>{{ formatNumber(userDetail.totals.usage_count) }}</strong><i class="bx bx-pulse"></i></article>
              <article><small>Días activos</small><strong>{{ formatNumber(userDetail.totals.active_days) }}</strong><i class="bx bx-calendar-check"></i></article>
            </div>

            <div class="usage-detail__facts">
              <div><span>Último ingreso</span><strong>{{ formatDate(userDetail.totals.last_login_at) }}</strong></div>
              <div><span>Última actividad</span><strong>{{ formatDate(userDetail.totals.last_activity_at) }}</strong></div>
              <div><span>Primera actividad del período</span><strong>{{ formatDate(userDetail.totals.first_activity_at) }}</strong></div>
            </div>

            <section class="usage-timeline">
              <header><div><span class="usage-section-kicker">Continuidad</span><h3>Actividad por día registrado</h3></div><small>Hasta 60 días con actividad</small></header>
              <div v-if="userDetail.timeline.length" class="usage-timeline__list">
                <div v-for="item in userDetail.timeline" :key="item.date" class="usage-timeline__row">
                  <time>{{ formatDate(item.date, false) }}</time>
                  <span class="usage-timeline__track"><i :style="{ width: timelineWidth(item) }"></i></span>
                  <strong>{{ item.usage }}</strong>
                  <small>{{ item.logins }} ingresos</small>
                </div>
              </div>
              <div v-else class="usage-timeline__empty"><i class="bx bx-moon"></i><span>Sin actividad registrada en este período.</span></div>
            </section>
          </div>
        </section>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.usage-page {
  --usage-ink: #17213d;
  --usage-muted: #68738f;
  --usage-border: #e4e8f2;
  --usage-violet: #6256d9;
  --usage-blue: #2976d2;
  --usage-teal: #0f9b8e;
  --usage-amber: #d88918;
  color: var(--usage-ink);
  padding: 1.25rem;
}

.usage-hero {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
  min-height: 230px;
  padding: 2.5rem;
  color: #fff;
  border-radius: 24px;
  background: linear-gradient(128deg, #171c3f 0%, #30306d 48%, #6256d9 100%);
  box-shadow: 0 22px 56px rgba(47, 43, 111, 0.23);
}

.usage-hero__content { position: relative; z-index: 2; max-width: 760px; }
.usage-eyebrow, .usage-section-kicker { font-size: .72rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.usage-eyebrow { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .72rem; color: #e9e7ff; border: 1px solid rgba(255,255,255,.18); border-radius: 999px; background: rgba(255,255,255,.08); }
.usage-hero h1 { margin: .85rem 0 .5rem; color: #fff; font-size: clamp(2rem, 4vw, 3.1rem); font-weight: 800; letter-spacing: -.04em; }
.usage-hero p { max-width: 700px; margin: 0; color: rgba(255,255,255,.78); font-size: 1.02rem; line-height: 1.65; }
.usage-hero__trust { display: flex; flex-wrap: wrap; gap: .65rem 1.1rem; margin-top: 1.3rem; }
.usage-hero__trust span { display: inline-flex; align-items: center; gap: .35rem; color: rgba(255,255,255,.84); font-size: .8rem; font-weight: 700; }
.usage-hero__orb { position: absolute; z-index: -1; border-radius: 50%; filter: blur(2px); opacity: .25; }
.usage-hero__orb--one { top: -100px; right: 18%; width: 260px; height: 260px; background: #a9e7ff; }
.usage-hero__orb--two { right: -55px; bottom: -125px; width: 330px; height: 330px; background: #ffbd84; }
.usage-refresh { position: relative; z-index: 2; display: inline-flex; align-items: center; gap: .55rem; flex: 0 0 auto; padding: .85rem 1.05rem; color: #262458; font-weight: 800; border: 0; border-radius: 12px; background: #fff; box-shadow: 0 12px 30px rgba(14,17,48,.18); }
.usage-refresh:disabled { opacity: .65; }
.usage-alert { display: flex; align-items: center; gap: .55rem; margin-top: 1rem; border-radius: 14px; }

.usage-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin: 1.25rem 0; }
.usage-segment { display: flex; align-items: center; gap: 1rem; min-width: 0; padding: 1.25rem; text-align: left; color: var(--usage-ink); border: 1px solid var(--usage-border); border-radius: 18px; background: #fff; box-shadow: 0 8px 26px rgba(35,47,84,.06); transition: .2s ease; }
.usage-segment:hover, .usage-segment.is-active { transform: translateY(-2px); box-shadow: 0 15px 36px rgba(35,47,84,.11); }
.usage-segment--staff.is-active { border-color: rgba(98,86,217,.4); box-shadow: 0 15px 36px rgba(98,86,217,.13); }
.usage-segment--student.is-active { border-color: rgba(15,155,142,.4); box-shadow: 0 15px 36px rgba(15,155,142,.13); }
.usage-segment__icon { display: grid; place-items: center; flex: 0 0 54px; width: 54px; height: 54px; color: var(--usage-violet); font-size: 1.55rem; border-radius: 16px; background: #efedff; }
.usage-segment--student .usage-segment__icon { color: var(--usage-teal); background: #e6f8f5; }
.usage-segment__body { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.usage-segment__top, .usage-segment__foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.usage-segment__top strong { font-size: 1.05rem; }
.usage-segment__top em { color: var(--usage-muted); font-size: .75rem; font-style: normal; font-weight: 700; }
.usage-segment__body > small { overflow: hidden; margin-top: .18rem; color: var(--usage-muted); text-overflow: ellipsis; white-space: nowrap; }
.usage-segment__progress { overflow: hidden; height: 6px; margin: .8rem 0 .48rem; border-radius: 999px; background: #eef0f6; }
.usage-segment__progress i { display: block; height: 100%; border-radius: inherit; background: var(--usage-violet); }
.usage-segment--student .usage-segment__progress i { background: var(--usage-teal); }
.usage-segment__foot { color: var(--usage-muted); font-size: .75rem; }
.usage-segment__foot b { color: var(--usage-ink); }
.usage-segment__arrow { color: #a2aac0; font-size: 1.25rem; }

.usage-period { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: .85rem 1rem; border: 1px solid var(--usage-border); border-radius: 14px; background: #f9faff; }
.usage-period > div:first-child { display: flex; flex-direction: column; }
.usage-period span { color: var(--usage-muted); font-size: .72rem; }
.usage-period strong { font-size: .9rem; }
.usage-period__options { display: flex; flex-wrap: wrap; gap: .35rem; }
.usage-period__options button { padding: .48rem .72rem; color: #5b6681; font-size: .78rem; font-weight: 700; border: 0; border-radius: 9px; background: transparent; }
.usage-period__options button.is-active { color: #fff; background: var(--usage-violet); box-shadow: 0 6px 16px rgba(98,86,217,.22); }

.usage-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.usage-metric { display: flex; align-items: center; gap: .85rem; min-width: 0; padding: 1rem; border: 1px solid var(--usage-border); border-radius: 16px; background: #fff; box-shadow: 0 7px 22px rgba(35,47,84,.05); }
.usage-metric__icon { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 13px; font-size: 1.25rem; }
.usage-metric--violet .usage-metric__icon { color: var(--usage-violet); background: #efedff; }
.usage-metric--blue .usage-metric__icon { color: var(--usage-blue); background: #eaf3ff; }
.usage-metric--teal .usage-metric__icon { color: var(--usage-teal); background: #e6f8f5; }
.usage-metric--amber .usage-metric__icon { color: var(--usage-amber); background: #fff4df; }
.usage-metric div { display: flex; flex-direction: column; min-width: 0; }
.usage-metric small { color: var(--usage-muted); font-size: .7rem; font-weight: 800; text-transform: uppercase; }
.usage-metric strong { margin: .05rem 0; font-size: 1.5rem; line-height: 1.1; }
.usage-metric div span { overflow: hidden; color: var(--usage-muted); font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }

.usage-workspace { overflow: hidden; border: 1px solid var(--usage-border); border-radius: 20px; background: #fff; box-shadow: 0 10px 32px rgba(35,47,84,.07); }
.usage-workspace__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.4rem 1.5rem 1rem; }
.usage-section-kicker { color: var(--usage-violet); }
.usage-workspace h2 { margin: .25rem 0 .2rem; font-size: 1.25rem; }
.usage-workspace__header p { margin: 0; color: var(--usage-muted); font-size: .82rem; }
.usage-readonly { display: inline-flex; align-items: center; gap: .35rem; padding: .46rem .7rem; color: #56617d; font-size: .72rem; font-weight: 800; border-radius: 999px; background: #f1f3f8; }
.usage-filters { display: grid; grid-template-columns: minmax(230px, 1.5fr) repeat(3, minmax(135px, .72fr)) auto auto; align-items: end; gap: .7rem; padding: 1rem 1.5rem; border-block: 1px solid var(--usage-border); background: #fafbfe; }
.usage-filters label { display: flex; flex-direction: column; gap: .32rem; min-width: 0; }
.usage-filters label > span { color: #59647e; font-size: .69rem; font-weight: 800; text-transform: uppercase; }
.usage-filters input, .usage-filters select { width: 100%; height: 40px; color: var(--usage-ink); border: 1px solid #dfe3ed; border-radius: 10px; outline: none; background: #fff; }
.usage-filters input:focus, .usage-filters select:focus { border-color: #9289e8; box-shadow: 0 0 0 3px rgba(98,86,217,.09); }
.usage-filters select { padding: 0 .7rem; font-size: .78rem; }
.usage-search { position: relative; }
.usage-search i { position: absolute; left: .75rem; bottom: 12px; color: #8a93aa; }
.usage-search input { padding: 0 .8rem 0 2.25rem; font-size: .82rem; }
.usage-filter-button, .usage-clear-button { height: 40px; padding: 0 .85rem; font-size: .76rem; font-weight: 800; border-radius: 10px; white-space: nowrap; }
.usage-filter-button { color: #fff; border: 0; background: var(--usage-violet); }
.usage-clear-button { color: #5e6880; border: 1px solid #dfe3ed; background: #fff; }
.usage-loading { min-height: 240px; }

.usage-table-wrap { overflow-x: auto; }
.usage-table { width: 100%; border-collapse: collapse; }
.usage-table th { padding: .75rem 1rem; color: #778097; font-size: .67rem; font-weight: 800; text-align: left; text-transform: uppercase; background: #fff; }
.usage-table td { padding: .9rem 1rem; border-top: 1px solid #edf0f5; vertical-align: middle; }
.usage-table tbody tr { transition: background .15s ease; }
.usage-table tbody tr:hover { background: #fafaff; }
.usage-person { display: flex; align-items: center; gap: .72rem; min-width: 210px; }
.usage-person > div { display: flex; flex-direction: column; min-width: 0; }
.usage-person strong { overflow: hidden; max-width: 250px; font-size: .85rem; text-overflow: ellipsis; white-space: nowrap; }
.usage-person div > span, .usage-person div > small { overflow: hidden; max-width: 250px; color: var(--usage-muted); font-size: .7rem; text-overflow: ellipsis; white-space: nowrap; }
.usage-person div > small { color: #8c85c6; font-weight: 700; }
.usage-avatar { display: grid; place-items: center; flex: 0 0 38px; width: 38px; height: 38px; color: #564cb4; font-size: .72rem; font-weight: 900; border-radius: 12px; background: #efedff; }
.usage-avatar--student { color: #087c72; background: #e1f6f2; }
.usage-avatar--large { flex-basis: 52px; width: 52px; height: 52px; font-size: .9rem; border-radius: 16px; }
.usage-level { display: inline-flex; padding: .38rem .55rem; font-size: .66rem; font-weight: 900; border-radius: 999px; white-space: nowrap; }
.usage-level--high { color: #087c72; background: #def5ef; }
.usage-level--medium { color: #9a5b08; background: #fff0d5; }
.usage-level--low { color: #2c66ad; background: #e8f2ff; }
.usage-level--none { color: #727b91; background: #eef0f4; }
.usage-number { display: block; font-size: .92rem; }
.usage-subvalue { display: block; color: var(--usage-muted); font-size: .66rem; }
.usage-last { display: block; max-width: 135px; color: #4f5b76; font-size: .72rem; line-height: 1.35; }
.usage-select-button { display: inline-flex; align-items: center; justify-content: center; gap: .25rem; padding: .5rem .68rem; color: #554bc0; font-size: .72rem; font-weight: 900; border: 1px solid #dedafc; border-radius: 9px; background: #f6f5ff; white-space: nowrap; }
.usage-mobile-list { display: none; }
.usage-empty { display: grid; place-items: center; min-height: 280px; padding: 2rem; text-align: center; }
.usage-empty > span { display: grid; place-items: center; width: 62px; height: 62px; color: #8780ce; font-size: 1.8rem; border-radius: 18px; background: #efedff; }
.usage-empty h3 { margin: .8rem 0 .2rem; font-size: 1rem; }
.usage-empty p { margin: 0; color: var(--usage-muted); font-size: .82rem; }
.usage-empty button { margin-top: .8rem; padding: .55rem .8rem; color: #fff; font-weight: 700; border: 0; border-radius: 9px; background: var(--usage-violet); }
.usage-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1.3rem; border-top: 1px solid var(--usage-border); }
.usage-pagination > span { color: var(--usage-muted); font-size: .75rem; }

.usage-method-note { display: flex; gap: .85rem; margin-top: 1rem; padding: 1rem 1.15rem; border: 1px solid #dce8f5; border-radius: 14px; background: #f5f9fd; }
.usage-method-note > span { display: grid; place-items: center; flex: 0 0 36px; width: 36px; height: 36px; color: #3172b8; font-size: 1.1rem; border-radius: 10px; background: #e4f0fb; }
.usage-method-note div { display: flex; flex-direction: column; }
.usage-method-note strong { font-size: .8rem; }
.usage-method-note p { margin: .16rem 0 0; color: #627087; font-size: .75rem; line-height: 1.55; }

.usage-detail-backdrop { position: fixed; z-index: 1060; inset: 0; display: flex; align-items: stretch; justify-content: flex-end; padding: 0; background: rgba(13,18,41,.52); backdrop-filter: blur(4px); }
.usage-detail { overflow-y: auto; width: min(620px, 100%); height: 100%; background: #fff; box-shadow: -22px 0 60px rgba(13,18,41,.2); animation: usage-slide-in .22s ease-out; }
.usage-detail__header { position: sticky; z-index: 2; top: 0; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.35rem; border-bottom: 1px solid var(--usage-border); background: rgba(255,255,255,.95); backdrop-filter: blur(12px); }
.usage-person--detail { align-items: flex-start; }
.usage-person--detail h2 { margin: .14rem 0; font-size: 1.2rem; }
.usage-person--detail p { margin: 0; color: var(--usage-muted); font-size: .78rem; }
.usage-detail__close { display: grid; place-items: center; flex: 0 0 38px; width: 38px; height: 38px; color: #535d75; font-size: 1.3rem; border: 0; border-radius: 11px; background: #f1f3f7; }
.usage-detail__body { padding: 1.35rem; }
.usage-detail__status { display: flex; flex-wrap: wrap; gap: .45rem; margin-bottom: 1rem; }
.usage-detail__status span { display: inline-flex; align-items: center; gap: .3rem; padding: .42rem .58rem; color: #5e6880; font-size: .68rem; font-weight: 800; border-radius: 999px; background: #f0f2f6; }
.usage-detail__status .is-active { color: #087c72; background: #def5ef; }
.usage-detail__status .is-inactive { color: #a33c4d; background: #fde8ec; }
.usage-detail__metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: .7rem; }
.usage-detail__metrics article { position: relative; overflow: hidden; display: flex; flex-direction: column; padding: 1rem; border: 1px solid var(--usage-border); border-radius: 14px; background: linear-gradient(145deg,#fff,#f8f8ff); }
.usage-detail__metrics small { color: var(--usage-muted); font-size: .68rem; font-weight: 800; text-transform: uppercase; }
.usage-detail__metrics strong { margin-top: .15rem; font-size: 1.55rem; }
.usage-detail__metrics i { position: absolute; right: .7rem; bottom: .55rem; color: rgba(98,86,217,.14); font-size: 2.2rem; }
.usage-detail__facts { display: grid; gap: .55rem; margin: 1rem 0; }
.usage-detail__facts div { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem .85rem; border-radius: 11px; background: #f7f8fb; }
.usage-detail__facts span { color: var(--usage-muted); font-size: .72rem; }
.usage-detail__facts strong { font-size: .75rem; text-align: right; }
.usage-timeline { margin-top: 1.2rem; padding-top: 1.15rem; border-top: 1px solid var(--usage-border); }
.usage-timeline > header { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: .9rem; }
.usage-timeline h3 { margin: .18rem 0 0; font-size: 1rem; }
.usage-timeline header > small { color: var(--usage-muted); font-size: .68rem; }
.usage-timeline__list { display: grid; gap: .5rem; }
.usage-timeline__row { display: grid; grid-template-columns: 62px 1fr 28px 72px; align-items: center; gap: .55rem; }
.usage-timeline__row time, .usage-timeline__row small { color: var(--usage-muted); font-size: .68rem; }
.usage-timeline__row strong { font-size: .76rem; text-align: right; }
.usage-timeline__track { overflow: hidden; height: 9px; border-radius: 999px; background: #eceef5; }
.usage-timeline__track i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--usage-violet), #8f87e9); }
.usage-timeline__empty { display: flex; align-items: center; justify-content: center; gap: .5rem; min-height: 120px; color: var(--usage-muted); font-size: .78rem; border: 1px dashed #dce0e9; border-radius: 12px; }

@keyframes usage-slide-in { from { transform: translateX(30px); opacity: .7; } to { transform: translateX(0); opacity: 1; } }

@media (max-width: 1199.98px) {
  .usage-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .usage-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .usage-search { grid-column: span 2; }
}

@media (max-width: 767.98px) {
  .usage-page { padding: .75rem; }
  .usage-hero { align-items: flex-start; flex-direction: column; min-height: auto; padding: 1.5rem; border-radius: 18px; }
  .usage-hero p { font-size: .9rem; }
  .usage-refresh { width: 100%; justify-content: center; }
  .usage-groups, .usage-metrics { grid-template-columns: 1fr; }
  .usage-segment { padding: 1rem; }
  .usage-period { align-items: flex-start; flex-direction: column; }
  .usage-period__options { width: 100%; }
  .usage-period__options button { flex: 1; }
  .usage-workspace__header { flex-direction: column; padding: 1.1rem; }
  .usage-filters { grid-template-columns: 1fr; padding: 1rem; }
  .usage-search { grid-column: auto; }
  .usage-table-wrap { display: none; }
  .usage-mobile-list { display: grid; gap: .75rem; padding: .85rem; background: #f7f8fb; }
  .usage-mobile-card { padding: 1rem; border: 1px solid var(--usage-border); border-radius: 15px; background: #fff; }
  .usage-mobile-card header { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; align-items: center; gap: .65rem; }
  .usage-mobile-card header div { display: flex; flex-direction: column; min-width: 0; }
  .usage-mobile-card header strong, .usage-mobile-card header span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .usage-mobile-card header strong { font-size: .8rem; }
  .usage-mobile-card header div span { color: var(--usage-muted); font-size: .68rem; }
  .usage-mobile-card__metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; margin: .8rem 0; }
  .usage-mobile-card__metrics span { display: flex; flex-direction: column; padding: .55rem; border-radius: 9px; background: #f7f8fb; }
  .usage-mobile-card__metrics small { color: var(--usage-muted); font-size: .6rem; }
  .usage-mobile-card__metrics b { font-size: .9rem; }
  .usage-mobile-card p { display: flex; align-items: center; gap: .35rem; margin: 0 0 .7rem; color: var(--usage-muted); font-size: .7rem; }
  .usage-mobile-card .usage-select-button { width: 100%; }
  .usage-pagination { align-items: flex-start; flex-direction: column; }
  .usage-method-note { padding: .85rem; }
  .usage-detail__metrics { grid-template-columns: 1fr; }
  .usage-detail__facts div { align-items: flex-start; flex-direction: column; gap: .2rem; }
  .usage-detail__facts strong { text-align: left; }
  .usage-timeline__row { grid-template-columns: 56px 1fr 24px; }
  .usage-timeline__row small { display: none; }
}
</style>
