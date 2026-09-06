<script>
import { defineAsyncComponent } from "vue";
import layoutMixin from "./mixins/layouts.mixin";
import {
  clearMessagingSession,
  loadMessagingAccess,
  MESSAGING_ACCESS_REVOKED_EVENT,
  messagingAccess,
  resetMessagingAccess,
} from "./modules/messaging/services/messagingAccess";

export default {
  name: "App",
  mixins: [layoutMixin],
  components: {
    MessagingMiniChat: defineAsyncComponent(() => import("./modules/messaging/components/MessagingMiniChat.vue")),
  },
  computed: {
    authenticated() {
      return Boolean(localStorage.getItem("token"))
        && !["/login", "/register"].includes(this.$route.path);
    },
    showMessagingMiniChat() {
      return this.authenticated
        && messagingAccess.resolved
        && messagingAccess.allowed;
    },
  },
  watch: {
    authenticated: {
      immediate: true,
      handler(isAuthenticated) {
        if (!isAuthenticated) {
          clearMessagingSession({ code: "MESSAGING_SESSION_ENDED" });
          return;
        }

        loadMessagingAccess().catch(() => null);
      },
    },
  },
  mounted() {
    window.addEventListener(
      MESSAGING_ACCESS_REVOKED_EVENT,
      this.handleMessagingAccessRevoked
    );
  },
  beforeUnmount() {
    window.removeEventListener(
      MESSAGING_ACCESS_REVOKED_EVENT,
      this.handleMessagingAccessRevoked
    );
  },
  methods: {
    handleMessagingAccessRevoked() {
      resetMessagingAccess();
      if (this.$route.path.startsWith("/mensajeria")) {
        this.$router.replace("/inicio");
      }
    },
  },
};
</script>

<template>
  <router-view />
  <MessagingMiniChat v-if="showMessagingMiniChat" />
</template>
