<script>
import { humanizeLibraryStatus, statusVariant } from "./module-utils";

export default {
  props: {
    status: {
      type: String,
      default: "",
    },
  },
  computed: {
    label() {
      return humanizeLibraryStatus(this.status);
    },
    variant() {
      return statusVariant(this.status);
    },
  },
};
</script>

<template>
  <BBadge :variant="variant" class="text-uppercase fw-semibold">
    {{ label }}
  </BBadge>
</template>
