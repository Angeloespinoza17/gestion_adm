<script>
import { humanizeConvivenciaStatus, statusVariant } from "./module-utils";

export default {
  props: {
    status: { type: String, default: "" },
  },
  computed: {
    label() {
      return humanizeConvivenciaStatus(this.status);
    },
    variant() {
      return statusVariant(this.status);
    },
  },
};
</script>

<template>
  <BBadge pill :variant="variant" class="convivencia-status-badge"><span class="convivencia-status-badge__dot"></span>{{ label }}</BBadge>
</template>

<style scoped>
.convivencia-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  line-height: 1;
}

.convivencia-status-badge__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.7;
}
</style>
