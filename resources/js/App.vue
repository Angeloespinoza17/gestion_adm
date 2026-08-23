<script>
import { defineAsyncComponent } from "vue";
import layoutMixin from "./mixins/layouts.mixin";

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
  },
};
</script>

<template>
  <router-view />
  <MessagingMiniChat v-if="authenticated" />
</template>
