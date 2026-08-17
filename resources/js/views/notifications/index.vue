<script setup>
import axios from "axios";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import Layout from "../../layouts/main.vue";

const router = useRouter();
const loading = ref(false);
const error = ref("");
const notifications = ref([]);
const search = ref("");
const status = ref("all");
const stats = reactive({ total: 0, unread: 0, read: 0 });
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
let searchTimer = null;

const filters = computed(() => [
  { value: "all", label: "Todas", count: stats.total },
  { value: "unread", label: "No leídas", count: stats.unread },
  { value: "read", label: "Leídas", count: stats.read },
]);

const visiblePages = computed(() => {
  const start = Math.max(1, pagination.current_page - 2);
  const end = Math.min(pagination.last_page, start + 4);
  return Array.from({ length: Math.max(0, end - start + 1) }, (_, index) => start + index);
});

const groupedNotifications = computed(() => {
  const groups = [];
  notifications.value.forEach((notification) => {
    const label = dateGroup(notification.created_at);
    let group = groups.find((item) => item.label === label);
    if (!group) {
      group = { label, items: [] };
      groups.push(group);
    }
    group.items.push(notification);
  });
  return groups;
});

async function load(page = 1) {
  loading.value = true;
  error.value = "";
  try {
    const response = await axios.get("/api/internal-notifications", {
      params: {
        page,
        limit: 20,
        status: status.value,
        search: search.value.trim() || null,
      },
    });
    notifications.value = response.data.data || [];
    stats.total = Number(response.data.total_count || 0);
    stats.unread = Number(response.data.unread_count || 0);
    stats.read = Number(response.data.read_count || 0);
    Object.assign(pagination, response.data.meta || { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
  } catch (requestError) {
    error.value = requestError.response?.data?.message || "No fue posible cargar el historial de notificaciones.";
  } finally {
    loading.value = false;
  }
}

async function openNotification(notification) {
  if (!notification.read_at) {
    try {
      const response = await axios.put(`/api/internal-notifications/${notification.id}/read`);
      notification.read_at = response.data.data?.read_at || new Date().toISOString();
      stats.unread = Math.max(0, stats.unread - 1);
      stats.read += 1;
      window.dispatchEvent(new CustomEvent("internal-notifications:refresh"));
    } catch (requestError) {
      error.value = requestError.response?.data?.message || "No fue posible marcar la notificación como leída.";
      return;
    }
  }

  if (notification.action_url && notification.action_url !== router.currentRoute.value.path) {
    await router.push(notification.action_url);
  }
}

async function markAllAsRead() {
  if (!stats.unread) return;
  try {
    await axios.put("/api/internal-notifications/read-all");
    window.dispatchEvent(new CustomEvent("internal-notifications:refresh"));
    await load(pagination.current_page);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || "No fue posible marcar las notificaciones como leídas.";
  }
}

function setStatus(value) {
  if (status.value === value) return;
  status.value = value;
}

function clearSearch() {
  search.value = "";
}

function asDate(value) {
  return new Date(String(value || "").replace(" ", "T"));
}

function dateGroup(value) {
  const date = asDate(value);
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);
  if (date.toDateString() === today.toDateString()) return "Hoy";
  if (date.toDateString() === yesterday.toDateString()) return "Ayer";
  return new Intl.DateTimeFormat("es-CL", { weekday: "long", day: "numeric", month: "long" }).format(date);
}

function formatDateTime(value) {
  const date = asDate(value);
  if (Number.isNaN(date.getTime())) return "";
  return new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" }).format(date);
}

function iconClass(notification) {
  return notification.icon || "bx bx-bell";
}

function tone(notification) {
  if (["alta", "critica"].includes(notification.priority)) return "danger";
  if (notification.priority === "baja") return "secondary";
  return "primary";
}

watch(status, () => load(1));
watch(search, () => {
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(() => load(1), 350);
});

onMounted(() => load());
onBeforeUnmount(() => window.clearTimeout(searchTimer));
</script>

<template>
  <Layout>
    <div class="notification-history">
      <section class="notification-hero">
        <div class="notification-hero__copy">
          <span class="notification-hero__icon"><i class="bx bx-bell"></i></span>
          <div><span class="notification-eyebrow">Centro personal</span><h1>Historial de notificaciones</h1><p>Revisa avisos, mensajes y actualizaciones importantes en un solo lugar.</p></div>
        </div>
        <button v-if="stats.unread" type="button" class="mark-all-button" @click="markAllAsRead"><i class="bx bx-check-double"></i> Marcar todo como leído</button>
      </section>

      <section class="notification-stats" aria-label="Resumen de notificaciones">
        <article><span class="stat-icon stat-icon--all"><i class="bx bx-layer"></i></span><div><strong>{{ stats.total }}</strong><small>Total histórico</small></div></article>
        <article><span class="stat-icon stat-icon--unread"><i class="bx bx-envelope"></i></span><div><strong>{{ stats.unread }}</strong><small>Pendientes de revisar</small></div></article>
        <article><span class="stat-icon stat-icon--read"><i class="bx bx-check-shield"></i></span><div><strong>{{ stats.read }}</strong><small>Notificaciones leídas</small></div></article>
      </section>

      <section class="notification-workspace">
        <header class="notification-toolbar">
          <div class="notification-filters" role="tablist" aria-label="Filtrar notificaciones">
            <button v-for="filter in filters" :key="filter.value" type="button" :class="{ active: status === filter.value }" @click="setStatus(filter.value)">{{ filter.label }} <span>{{ filter.count }}</span></button>
          </div>
          <label class="notification-search"><i class="bx bx-search"></i><input v-model="search" type="search" placeholder="Buscar en el historial" aria-label="Buscar notificaciones"><button v-if="search" type="button" aria-label="Limpiar búsqueda" @click="clearSearch"><i class="bx bx-x"></i></button></label>
        </header>

        <div v-if="error" class="notification-error"><i class="bx bx-error-circle"></i>{{ error }}<button type="button" @click="load(pagination.current_page)">Reintentar</button></div>

        <div v-if="loading" class="notification-loading"><span class="spinner-border" aria-hidden="true"></span><strong>Cargando historial</strong><small>Estamos organizando tus notificaciones…</small></div>

        <div v-else-if="!notifications.length" class="notification-empty">
          <span><i class="bx" :class="search ? 'bx-search-alt' : 'bx-bell-off'"></i></span>
          <h2>{{ search ? 'No encontramos coincidencias' : status === 'unread' ? 'No tienes notificaciones pendientes' : 'Tu historial está vacío' }}</h2>
          <p>{{ search ? 'Prueba con otras palabras o limpia la búsqueda.' : 'Los próximos avisos aparecerán organizados aquí.' }}</p>
          <button v-if="search" type="button" @click="clearSearch">Limpiar búsqueda</button>
        </div>

        <div v-else class="notification-groups">
          <section v-for="group in groupedNotifications" :key="group.label" class="notification-group">
            <h2><span>{{ group.label }}</span><i></i></h2>
            <button v-for="notification in group.items" :key="notification.id" type="button" class="history-item" :class="{ 'history-item--unread': !notification.read_at }" @click="openNotification(notification)">
              <span :class="`history-item__icon history-item__icon--${tone(notification)}`"><i :class="iconClass(notification)"></i></span>
              <span class="history-item__content"><span class="history-item__heading"><strong>{{ notification.title }}</strong><b v-if="!notification.read_at">Nueva</b></span><span class="history-item__message">{{ notification.message }}</span><span class="history-item__meta"><span><i class="bx bx-time-five"></i>{{ formatDateTime(notification.created_at) }}</span><span v-if="notification.priority"><i class="bx bx-flag"></i>Prioridad {{ notification.priority }}</span></span></span>
              <span class="history-item__action"><i :class="notification.action_url ? 'bx bx-right-arrow-alt' : notification.read_at ? 'bx bx-check' : 'bx bx-envelope-open'"></i></span>
            </button>
          </section>
        </div>

        <footer v-if="!loading && pagination.last_page > 1" class="notification-pagination">
          <span>Mostrando {{ pagination.from }}–{{ pagination.to }} de {{ pagination.total }}</span>
          <div><button type="button" :disabled="pagination.current_page === 1" aria-label="Página anterior" @click="load(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i></button><button v-for="page in visiblePages" :key="page" type="button" :class="{ active: pagination.current_page === page }" @click="load(page)">{{ page }}</button><button type="button" :disabled="pagination.current_page === pagination.last_page" aria-label="Página siguiente" @click="load(pagination.current_page + 1)"><i class="bx bx-chevron-right"></i></button></div>
        </footer>
      </section>
    </div>
  </Layout>
</template>

<style scoped>
.notification-history{--history-brand:#5755e8;--history-ink:#182139;--history-muted:#778298;max-width:1180px;margin:0 auto;padding:26px 20px 56px;color:var(--history-ink)}
.notification-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:24px;overflow:hidden;padding:30px 32px;color:#fff;background:linear-gradient(132deg,#5d5ae7 0%,#4542c8 62%,#3633aa 100%);border-radius:24px;box-shadow:0 20px 48px rgba(66,63,185,.22)}.notification-hero::after{content:"";position:absolute;right:-80px;top:-120px;width:300px;height:300px;border:52px solid rgba(255,255,255,.07);border-radius:50%}.notification-hero__copy{position:relative;z-index:1;display:flex;align-items:center;gap:18px}.notification-hero__icon{flex:0 0 auto;width:64px;height:64px;display:grid;place-items:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:19px;font-size:30px;box-shadow:inset 0 1px 0 rgba(255,255,255,.16)}.notification-eyebrow{display:block;margin-bottom:3px;color:#c9c8ff;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.notification-hero h1{margin:0;font-size:26px;font-weight:800}.notification-hero p{max-width:600px;margin:6px 0 0;color:rgba(255,255,255,.72);font-size:13px}.mark-all-button{position:relative;z-index:1;display:flex;align-items:center;gap:7px;flex:0 0 auto;padding:11px 15px;color:#4643c6;background:#fff;border:0;border-radius:12px;font-size:11px;font-weight:800;box-shadow:0 10px 25px rgba(27,25,105,.18)}.mark-all-button i{font-size:18px}
.notification-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0}.notification-stats article{display:flex;align-items:center;gap:13px;padding:17px 18px;background:#fff;border:1px solid #e8eaf1;border-radius:17px;box-shadow:0 8px 26px rgba(28,39,68,.05)}.stat-icon{width:43px;height:43px;display:grid;place-items:center;border-radius:13px;font-size:21px}.stat-icon--all{color:#5755e8;background:#eeefff}.stat-icon--unread{color:#d65a68;background:#fff0f2}.stat-icon--read{color:#26916b;background:#e9f8f2}.notification-stats article div{display:flex;flex-direction:column}.notification-stats strong{font-size:20px;line-height:1.1}.notification-stats small{margin-top:3px;color:var(--history-muted);font-size:10px}
.notification-workspace{overflow:hidden;background:#fff;border:1px solid #e7e9f0;border-radius:21px;box-shadow:0 14px 42px rgba(25,34,60,.07)}.notification-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px 19px;border-bottom:1px solid #eceef3}.notification-filters{display:flex;gap:6px}.notification-filters button{display:flex;align-items:center;gap:6px;padding:8px 11px;color:#6d778c;background:#f4f5f8;border:1px solid transparent;border-radius:10px;font-size:10px;font-weight:750}.notification-filters button span{min-width:18px;padding:2px 5px;background:rgba(112,121,144,.12);border-radius:8px;font-size:8px}.notification-filters button.active{color:#4d4ad1;background:#eeefff;border-color:#d8d9fb}.notification-search{width:min(320px,42%);display:flex;align-items:center;gap:7px;padding:0 10px;color:#9aa2b1;background:#f5f6f8;border:1px solid transparent;border-radius:11px}.notification-search:focus-within{background:#fff;border-color:#c9caf5;box-shadow:0 0 0 3px rgba(87,85,232,.06)}.notification-search input{flex:1;min-width:0;padding:10px 0;color:var(--history-ink);background:transparent;border:0;outline:0;font-size:10.5px}.notification-search button{display:grid;place-items:center;padding:0;color:#8993a5;background:transparent;border:0;font-size:17px}
.notification-error{display:flex;align-items:center;gap:7px;margin:16px 19px 0;padding:10px 12px;color:#a43d49;background:#fff0f2;border-radius:10px;font-size:10px}.notification-error button{margin-left:auto;color:inherit;background:transparent;border:0;font-weight:750}.notification-loading,.notification-empty{min-height:330px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}.notification-loading{gap:6px;color:var(--history-muted)}.notification-loading .spinner-border{width:27px;height:27px;margin-bottom:7px;color:var(--history-brand)}.notification-loading strong{color:var(--history-ink);font-size:12px}.notification-loading small{font-size:9px}.notification-empty>span{width:62px;height:62px;display:grid;place-items:center;color:#5755e8;background:#eeefff;border-radius:19px;font-size:28px}.notification-empty h2{margin:15px 0 5px;font-size:16px}.notification-empty p{margin:0;color:var(--history-muted);font-size:10.5px}.notification-empty button{margin-top:13px;padding:8px 11px;color:#fff;background:var(--history-brand);border:0;border-radius:9px;font-size:9px;font-weight:750}
.notification-groups{padding:8px 19px 18px}.notification-group h2{display:flex;align-items:center;gap:10px;margin:17px 0 9px;color:#7a8497;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.notification-group h2 i{flex:1;height:1px;background:#eceef3}.history-item{position:relative;width:100%;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:13px;margin-bottom:8px;padding:14px;text-align:left;color:var(--history-ink);background:#fff;border:1px solid #e9ebf1;border-radius:15px;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}.history-item:hover{transform:translateY(-1px);border-color:#d8d9f5;box-shadow:0 9px 24px rgba(31,40,72,.08)}.history-item--unread{background:linear-gradient(90deg,#f7f7ff,#fff 62%);border-color:#dedffd}.history-item--unread::before{content:"";position:absolute;left:-1px;top:16px;bottom:16px;width:3px;background:#5755e8;border-radius:0 3px 3px 0}.history-item__icon{width:43px;height:43px;display:grid;place-items:center;border-radius:13px;font-size:20px}.history-item__icon--primary{color:#5552da;background:#eeefff}.history-item__icon--danger{color:#d15463;background:#fff0f2}.history-item__icon--secondary{color:#6d778b;background:#f0f2f5}.history-item__content{min-width:0;display:flex;flex-direction:column}.history-item__heading{display:flex;align-items:center;gap:7px}.history-item__heading strong{overflow:hidden;font-size:11.5px;text-overflow:ellipsis;white-space:nowrap}.history-item__heading b{padding:3px 6px;color:#4c49ce;background:#e7e8ff;border-radius:7px;font-size:7px;text-transform:uppercase}.history-item__message{margin-top:3px;color:#69748a;font-size:10px;line-height:1.45}.history-item__meta{display:flex;align-items:center;gap:13px;margin-top:7px;color:#99a1af;font-size:8px}.history-item__meta span{display:flex;align-items:center;gap:3px}.history-item__action{width:31px;height:31px;display:grid;place-items:center;color:#777f91;background:#f3f4f7;border-radius:10px;font-size:17px}.history-item--unread .history-item__action{color:#fff;background:#5755e8}
.notification-pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 19px;border-top:1px solid #eceef3}.notification-pagination>span{color:#858fa1;font-size:9px}.notification-pagination>div{display:flex;gap:4px}.notification-pagination button{width:30px;height:30px;display:grid;place-items:center;padding:0;color:#69748a;background:#f4f5f8;border:0;border-radius:8px;font-size:10px}.notification-pagination button.active{color:#fff;background:#5755e8}.notification-pagination button:disabled{opacity:.38}
@media(max-width:767px){.notification-history{padding:16px 11px 42px}.notification-hero{align-items:flex-start;padding:22px;flex-direction:column}.notification-hero__icon{width:52px;height:52px;border-radius:16px}.notification-hero h1{font-size:21px}.notification-stats{grid-template-columns:1fr}.notification-toolbar{align-items:stretch;flex-direction:column}.notification-filters{overflow-x:auto}.notification-search{width:100%}.notification-groups{padding-inline:10px}.history-item{gap:9px;padding:11px}.history-item__icon{width:37px;height:37px}.history-item__meta{align-items:flex-start;flex-direction:column;gap:3px}.history-item__action{display:none}.notification-pagination{align-items:flex-start;gap:10px;flex-direction:column}}
</style>
