<script>
import { humanizeRiskStatus, statusVariant } from "./module-utils";

export default {
  props: {
    status: {
      type: String,
      default: "",
    },
    labels: {
      type: Object,
      default: () => ({}),
    },
  },
  computed: {
    variant() {
      return statusVariant(this.status);
    },
    label() {
      return this.labels[this.status] || humanizeRiskStatus(this.status);
    },
  },
};
</script>

<template>
  <BBadge :variant="variant" pill class="text-uppercase small">{{ label }}</BBadge>
</template>
