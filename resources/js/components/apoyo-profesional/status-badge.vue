<script>
import { humanizeSupportStatus, supportStatusVariant } from "./module-utils";

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
    variant() {
      return supportStatusVariant(this.status);
    },
    text() {
      return this.label || humanizeSupportStatus(this.status);
    },
  },
};
</script>

<template>
  <BBadge :variant="variant" pill class="text-uppercase small">{{ text }}</BBadge>
</template>
