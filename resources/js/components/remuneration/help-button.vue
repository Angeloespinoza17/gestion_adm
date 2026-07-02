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
  <BButton variant="outline-info" size="sm" @click="openHelp">
    <i class="bx bx-question-mark"></i>
  </BButton>
</template>
