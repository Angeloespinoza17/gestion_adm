<script>
import { humanizeInformaticaStatus, informaticaStatusVariant } from "./module-utils";

export default {
  props: {
    status: {
      type: String,
      default: "",
    },
    label: {
      type: String,
      default: "",
    },
  },
  computed: {
    text() {
      return this.label || humanizeInformaticaStatus(this.status);
    },
    variant() {
      return informaticaStatusVariant(this.status);
    },
  },
};
</script>

<template>
  <BBadge :variant="variant" pill class="informatica-status-badge">
    <span class="informatica-status-badge__dot"></span>{{ text }}
  </BBadge>
</template>

<style scoped>
.informatica-status-badge { display: inline-flex; align-items: center; gap: .38rem; padding: .38rem .62rem; font-size: .66rem; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }
.informatica-status-badge__dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 13%, transparent); }
</style>
