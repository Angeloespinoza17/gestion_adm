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
  <BBadge :variant="variant">{{ label }}</BBadge>
</template>
