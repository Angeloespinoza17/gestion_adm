<script>
import Swal from "sweetalert2";

export default {
  props: {
    title: {
      type: String,
      default: "Ayuda",
    },
    text: {
      type: String,
      required: true,
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
  <BButton class="pme-help-button" variant="outline-info" size="sm" :title="title" :aria-label="title" @click="openHelp">
    <i class="bx bx-help-circle"></i>
  </BButton>
</template>
