<script>
import Swal from "sweetalert2";

export default {
  props: {
    title: {
      type: String,
      required: true,
    },
    text: {
      type: String,
      required: true,
    },
    buttonText: {
      type: String,
      default: "?",
    },
    variant: {
      type: String,
      default: "outline-secondary",
    },
    size: {
      type: String,
      default: "sm",
    },
  },
  methods: {
    openHelp() {
      return Swal.fire({
        title: this.title,
        text: this.text,
        icon: "info",
        confirmButtonText: "Entendido",
      });
    },
  },
};
</script>

<template>
  <BButton :variant="variant" :size="size" class="support-help-button" @click="openHelp">
    <i class="bx bx-question-mark me-1"></i>{{ buttonText }}
  </BButton>
</template>

<style scoped>
.support-help-button {
  border-radius: 999px;
}
</style>
