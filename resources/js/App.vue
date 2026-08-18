<script setup>
import { computed, defineAsyncComponent } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const MessagingMiniChat = defineAsyncComponent(() => import("./modules/messaging/components/MessagingMiniChat.vue"));
const authenticated = computed(() => Boolean(localStorage.getItem("token")) && !["/login", "/register"].includes(route.path));
</script>

<template>
  <router-view />
  <MessagingMiniChat v-if="authenticated" />
</template>
