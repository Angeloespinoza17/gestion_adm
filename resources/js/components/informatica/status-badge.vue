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
  <BBadge :variant="variant" pill class="text-uppercase small">
    {{ text }}
  </BBadge>
</template>
