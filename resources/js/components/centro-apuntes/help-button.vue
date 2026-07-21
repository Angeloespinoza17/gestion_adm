<script>
import Swal from "sweetalert2";

export default {
  props: {
    title: { type: String, required: true },
    text: { type: String, required: true },
    buttonText: { type: String, default: "" },
    variant: { type: String, default: "outline-secondary" },
    size: { type: String, default: "sm" },
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
  <BButton
    :variant="variant"
    :size="size"
    class="centro-apuntes-help-button"
    :class="{ 'centro-apuntes-help-button--icon': !buttonText }"
    :aria-label="title"
    :title="title"
    @click="openHelp"
  >
    <i class="bx bx-help-circle" :class="{ 'me-1': buttonText }"></i><span v-if="buttonText">{{ buttonText }}</span>
  </BButton>
</template>

<style scoped>
.centro-apuntes-help-button {
  border-radius: 999px;
  align-items: center;
  display: inline-flex;
  justify-content: center;
}
.centro-apuntes-help-button--icon {
  height: 2.35rem;
  padding: 0;
  width: 2.35rem;
}
.centro-apuntes-help-button i {
  font-size: 1.15rem;
  line-height: 1;
}
</style>
