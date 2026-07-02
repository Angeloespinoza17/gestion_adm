<script>
import { humanizeCentroApuntesStatus, statusVariant } from "./module-utils";

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
      return humanizeCentroApuntesStatus(this.status);
    },
  },
};
</script>

<template>
  <BBadge pill :variant="variant">{{ label }}</BBadge>
</template>
