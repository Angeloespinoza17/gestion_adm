<script>
import simplebar from "simplebar-vue";

import SideNav from "./side-nav.vue";

/**
 * Sidebar component
 */
export default {
  components: { simplebar, SideNav },
  emits: ["toggle-menu"],
  props: {
    isCondensed: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      settings: {
        minScrollbarLength: 60,
      },
    };
  },
  methods: {
    onRoutechange() {
      setTimeout(() => {
        if (document.getElementsByClassName("mm-active").length > 0) {
          const currentPosition = document.getElementsByClassName("mm-active")[0].offsetTop;
          if (currentPosition > 500)
            this.$refs.currentMenu.SimpleBar.getScrollElement().scrollTo({ top: currentPosition + 300, behavior: "smooth" });;
        }
      }, 300);
    },
  },
  watch: {
    $route: {
      handler: "onRoutechange",
      immediate: true,
      deep: true,
    },
  }
};
</script>

<template>
  <!-- ========== Left Sidebar Start ========== -->
  <div class="vertical-menu premium-sidebar">
    <simplebar v-if="!isCondensed" :settings="settings" class="h-100" ref="currentMenu" id="my-element">
      <SideNav @toggle-menu="$emit('toggle-menu')" />
    </simplebar>

    <simplebar v-else class="h-100">
      <SideNav @toggle-menu="$emit('toggle-menu')" />
    </simplebar>
  </div>
  <!-- Left Sidebar End -->
</template>

