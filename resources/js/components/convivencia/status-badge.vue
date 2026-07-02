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
  <BBadge pill :variant="variant">{{ label }}</BBadge>
</template>
