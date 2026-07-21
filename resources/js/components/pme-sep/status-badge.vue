<script>
import { humanizePmeStatus, statusVariant } from "./module-utils";

export default {
  props: {
    status: {
      type: String,
      default: null,
    },
  },
  computed: {
    variant() {
      return statusVariant(this.status);
    },
    label() {
      return humanizePmeStatus(this.status);
    },
  },
};
</script>

<template>
  <BBadge class="pme-status-badge" :variant="variant">
    <span class="pme-status-badge__dot" aria-hidden="true"></span>{{ label }}
  </BBadge>
</template>
