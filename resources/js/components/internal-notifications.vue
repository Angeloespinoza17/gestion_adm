<script>
import axios from "axios";
import simplebar from "simplebar-vue";

export default {
  components: { simplebar },
  props: {
    sidebar: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      loading: false,
      notifications: [],
      unreadCount: 0,
      refreshTimer: null,
    };
  },
  mounted() {
    this.loadNotifications();
    this.refreshTimer = window.setInterval(() => this.loadNotifications(), 45000);
    window.addEventListener("internal-notifications:refresh", this.loadNotifications);
  },
  beforeUnmount() {
    if (this.refreshTimer) window.clearInterval(this.refreshTimer);
    window.removeEventListener("internal-notifications:refresh", this.loadNotifications);
  },
  methods: {
    async loadNotifications() {
      if (this.loading) return;
      this.loading = true;

      try {
        const response = await axios.get("/api/internal-notifications", {
          params: { limit: 15 },
        });
        this.notifications = response.data.data || [];
        this.unreadCount = Number(response.data.unread_count || 0);
      } catch (error) {
        if (error?.response?.status !== 401) {
          console.warn("No se pudieron cargar las notificaciones internas.", error);
        }
      } finally {
        this.loading = false;
      }
    },
    async openNotification(notification) {
      if (!notification.read_at) {
        try {
          await axios.put(`/api/internal-notifications/${notification.id}/read`);
          notification.read_at = new Date().toISOString();
          this.unreadCount = Math.max(0, this.unreadCount - 1);
        } catch (error) {
          console.warn("No se pudo marcar la notificación como leída.", error);
        }
      }

      if (notification.action_url && notification.action_url !== this.$route.path) {
        await this.$router.push(notification.action_url);
      }
    },
    async markAllAsRead() {
      if (!this.unreadCount) return;

      try {
        await axios.put("/api/internal-notifications/read-all");
        const readAt = new Date().toISOString();
        this.notifications = this.notifications.map((notification) => ({
          ...notification,
          read_at: notification.read_at || readAt,
        }));
        this.unreadCount = 0;
      } catch (error) {
        console.warn("No se pudieron marcar las notificaciones como leídas.", error);
      }
    },
    async openHistory() {
      this.$refs.notificationsDropdown?.hide?.();
      if (this.$route.path !== "/notificaciones") {
        await this.$router.push("/notificaciones");
      }
    },
    iconClass(notification) {
      return notification.icon || "bx bx-bell";
    },
    toneClass(notification) {
      if (notification.priority === "alta" || notification.priority === "critica") return "danger";
      if (notification.priority === "baja") return "secondary";
      return "primary";
    },
    formatDateTime(value) {
      if (!value) return "";
      const date = new Date(String(value).replace(" ", "T"));
      if (Number.isNaN(date.getTime())) return String(value);

      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      }).format(date);
    },
  },
};
</script>

<template>
  <BDropdown
    ref="notificationsDropdown"
    :class="['internal-notifications', { 'internal-notifications--sidebar': sidebar }]"
    :dropup="sidebar"
    menu-class="internal-notifications__menu p-0"
    toggle-class="internal-notifications__toggle"
    variant="link"
    no-caret
    @show="loadNotifications"
  >
    <template #button-content>
      <span class="internal-notifications__bell">
        <i :class="unreadCount ? 'bx bx-bell bx-tada' : 'bx bx-bell'"></i>
        <span v-if="unreadCount" class="internal-notifications__badge">
          {{ unreadCount > 99 ? "99+" : unreadCount }}
        </span>
      </span>
      <span v-if="sidebar" class="internal-notifications__label">Notificaciones</span>
      <span
        v-if="sidebar"
        class="internal-notifications__expand"
        role="button"
        tabindex="0"
        title="Ver historial completo"
        aria-label="Ver historial completo de notificaciones"
        @click.stop.prevent="openHistory"
        @keydown.enter.stop.prevent="openHistory"
      ><i class="bx bx-expand-alt"></i></span>
    </template>

    <div class="internal-notifications__header">
      <div>
        <strong>Notificaciones</strong>
        <small>{{ unreadCount ? `${unreadCount} sin leer` : "Todo al día" }}</small>
      </div>
      <div class="internal-notifications__header-actions">
        <button v-if="unreadCount" type="button" @click.stop="markAllAsRead">Marcar todas como leídas</button>
        <button type="button" class="internal-notifications__history-icon" title="Abrir historial" aria-label="Abrir historial completo" @click.stop="openHistory"><i class="bx bx-expand-alt"></i></button>
      </div>
    </div>

    <simplebar class="internal-notifications__list">
      <div v-if="loading && !notifications.length" class="internal-notifications__state">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        Cargando notificaciones...
      </div>
      <div v-else-if="!notifications.length" class="internal-notifications__state">
        <i class="bx bx-bell-off"></i>
        <strong>Sin notificaciones</strong>
        <span>Los nuevos avisos aparecerán aquí.</span>
      </div>
      <button
        v-for="notification in notifications"
        v-else
        :key="notification.id"
        type="button"
        class="internal-notifications__item"
        :class="{ 'internal-notifications__item--unread': !notification.read_at }"
        @click="openNotification(notification)"
      >
        <span :class="`internal-notifications__icon internal-notifications__icon--${toneClass(notification)}`">
          <i :class="iconClass(notification)"></i>
        </span>
        <span class="internal-notifications__content">
          <span class="internal-notifications__title">{{ notification.title }}</span>
          <span class="internal-notifications__message">{{ notification.message }}</span>
          <span class="internal-notifications__time">
            <i class="mdi mdi-clock-outline"></i>{{ formatDateTime(notification.created_at) }}
          </span>
        </span>
        <span v-if="!notification.read_at" class="internal-notifications__unread" aria-label="No leída"></span>
      </button>
    </simplebar>
    <button type="button" class="internal-notifications__history-link" @click.stop="openHistory">
      <span><i class="bx bx-list-ul"></i> Ver historial completo</span><i class="bx bx-right-arrow-alt"></i>
    </button>
  </BDropdown>
</template>

<style scoped>
.internal-notifications {
  display: inline-flex;
}

:deep(.internal-notifications__toggle) {
  align-items: center;
  color: inherit;
  display: inline-flex;
  justify-content: center;
  min-height: 42px;
  padding: 0.5rem 0.75rem;
  text-decoration: none;
}

.internal-notifications__bell {
  display: inline-flex;
  font-size: 1.35rem;
  position: relative;
}

.internal-notifications__badge {
  align-items: center;
  background: var(--bs-danger);
  border: 2px solid var(--bs-body-bg);
  border-radius: 999px;
  color: #fff;
  display: inline-flex;
  font-size: 0.58rem;
  font-weight: 800;
  height: 1.15rem;
  justify-content: center;
  min-width: 1.15rem;
  padding: 0 0.2rem;
  position: absolute;
  right: -0.65rem;
  top: -0.5rem;
}

:global(.internal-notifications__menu) {
  border: 1px solid var(--bs-border-color);
  box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.2);
  max-width: calc(100vw - 1.5rem);
  overflow: hidden;
  width: 390px;
}

.internal-notifications__header {
  align-items: center;
  border-bottom: 1px solid var(--bs-border-color);
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  padding: 0.85rem 1rem;
}

.internal-notifications__header strong,
.internal-notifications__header small {
  display: block;
}

.internal-notifications__header small {
  color: var(--bs-secondary-color);
  margin-top: 0.1rem;
}

.internal-notifications__header-actions {
  align-items: center;
  display: flex;
  gap: 0.65rem;
}

.internal-notifications__header-actions button {
  background: transparent;
  border: 0;
  color: var(--bs-primary);
  font-size: 0.72rem;
  padding: 0;
}

.internal-notifications__header-actions .internal-notifications__history-icon {
  align-items: center;
  background: rgba(var(--bs-primary-rgb), 0.09);
  border-radius: 0.55rem;
  display: inline-flex;
  font-size: 1rem;
  height: 2rem;
  justify-content: center;
  width: 2rem;
}

.internal-notifications__list {
  max-height: min(430px, 65vh);
}

.internal-notifications__item {
  align-items: flex-start;
  background: var(--bs-body-bg);
  border: 0;
  border-bottom: 1px solid var(--bs-border-color);
  color: var(--bs-body-color);
  display: grid;
  gap: 0.7rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  padding: 0.85rem 1rem;
  text-align: left;
  transition: background-color 0.15s ease;
  width: 100%;
}

.internal-notifications__item:hover,
.internal-notifications__item--unread {
  background: rgba(var(--bs-primary-rgb), 0.06);
}

.internal-notifications__icon {
  align-items: center;
  border-radius: 50%;
  display: inline-flex;
  font-size: 1.1rem;
  height: 2.4rem;
  justify-content: center;
  width: 2.4rem;
}

.internal-notifications__icon--primary {
  background: rgba(var(--bs-primary-rgb), 0.12);
  color: var(--bs-primary);
}

.internal-notifications__icon--danger {
  background: rgba(var(--bs-danger-rgb), 0.12);
  color: var(--bs-danger);
}

.internal-notifications__icon--secondary {
  background: rgba(var(--bs-secondary-rgb), 0.12);
  color: var(--bs-secondary);
}

.internal-notifications__content,
.internal-notifications__title,
.internal-notifications__message,
.internal-notifications__time {
  display: block;
}

.internal-notifications__title {
  font-size: 0.86rem;
  font-weight: 700;
}

.internal-notifications__message {
  color: var(--bs-secondary-color);
  font-size: 0.76rem;
  line-height: 1.4;
  margin-top: 0.2rem;
}

.internal-notifications__time {
  color: var(--bs-secondary-color);
  font-size: 0.68rem;
  margin-top: 0.35rem;
}

.internal-notifications__time i {
  margin-right: 0.25rem;
}

.internal-notifications__unread {
  background: var(--bs-primary);
  border-radius: 50%;
  height: 0.5rem;
  margin-top: 0.35rem;
  width: 0.5rem;
}

.internal-notifications__state {
  align-items: center;
  color: var(--bs-secondary-color);
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  justify-content: center;
  min-height: 10rem;
  padding: 1.25rem;
  text-align: center;
}

.internal-notifications__state i {
  font-size: 2rem;
}

.internal-notifications__history-link {
  align-items: center;
  background: var(--bs-body-bg);
  border: 0;
  border-top: 1px solid var(--bs-border-color);
  color: var(--bs-primary);
  display: flex;
  font-size: 0.78rem;
  font-weight: 700;
  justify-content: space-between;
  padding: 0.8rem 1rem;
  width: 100%;
}

.internal-notifications__history-link span {
  align-items: center;
  display: inline-flex;
  gap: 0.4rem;
}

.internal-notifications--sidebar {
  display: block;
  margin-bottom: 0.6rem;
  width: 100%;
}

.internal-notifications--sidebar :deep(.dropdown-toggle) {
  background: rgba(var(--bs-primary-rgb), 0.07);
  border: 1px solid rgba(var(--bs-primary-rgb), 0.12);
  border-radius: 0.65rem;
  color: var(--bs-body-color);
  gap: 0.65rem;
  justify-content: flex-start;
  min-height: 2.55rem;
  width: 100%;
}

.internal-notifications--sidebar :deep(.dropdown-toggle:hover) {
  background: rgba(var(--bs-primary-rgb), 0.12);
  color: var(--bs-primary);
}

.internal-notifications__label {
  flex: 1;
  font-size: 0.78rem;
  font-weight: 600;
  text-align: left;
}

.internal-notifications__expand {
  align-items: center;
  border-radius: 0.45rem;
  color: var(--bs-secondary-color);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1rem;
  height: 1.8rem;
  justify-content: center;
  transition: background-color 0.15s ease, color 0.15s ease;
  width: 1.8rem;
}

.internal-notifications__expand:hover,
.internal-notifications__expand:focus-visible {
  background: rgba(var(--bs-primary-rgb), 0.12);
  color: var(--bs-primary);
  outline: none;
}

:global(body.vertical-collpsed) .internal-notifications--sidebar :deep(.dropdown-toggle) {
  height: 2.95rem;
  justify-content: center;
  margin-inline: auto;
  min-width: 2.95rem;
  padding: 0;
  width: 2.95rem;
}

:global(body.vertical-collpsed) .internal-notifications__label {
  display: none;
}

:global(body.vertical-collpsed) .internal-notifications__expand {
  display: none;
}
</style>
