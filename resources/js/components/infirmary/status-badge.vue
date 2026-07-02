<script>
import { humanizeInfirmaryStatus, statusVariant } from "./module-utils";

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
      return statusVariant(this.status);
    },
    text() {
      return this.label || humanizeInfirmaryStatus(this.status);
    },
  },
};
</script>

<template>
  <BBadge :variant="variant" pill class="text-uppercase small">{{ text }}</BBadge>
</template>
