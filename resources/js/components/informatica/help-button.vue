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
      default: "",
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
  computed: {
    accessibleLabel() {
      return this.title?.replace(/^Ayuda:\s*/i, "Ayuda sobre ") || "Ver ayuda";
    },
    showButtonText() {
      return Boolean(this.buttonText && this.buttonText !== "?");
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
  <BButton
    :variant="variant"
    :size="size"
    class="informatica-help-button"
    :class="{ 'informatica-help-button--with-label': showButtonText }"
    :aria-label="accessibleLabel"
    :title="accessibleLabel"
    @click="openHelp"
  >
    <i class="bx bx-info-circle" aria-hidden="true"></i>
    <span v-if="showButtonText">{{ buttonText }}</span>
  </BButton>
</template>

<style scoped>
.informatica-help-button {
  display: inline-grid;
  width: 32px;
  height: 32px;
  min-width: 32px;
  padding: 0;
  place-items: center;
  color: #667085;
  border: 1px solid #dfe3ec;
  border-radius: 50%;
  background: rgba(255, 255, 255, .78);
  box-shadow: 0 2px 7px rgba(31, 42, 79, .07);
  transition: color .18s ease, background-color .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.informatica-help-button i {
  font-size: 1.08rem;
  line-height: 1;
}
.informatica-help-button:hover,
.informatica-help-button:focus-visible {
  color: #4057d6;
  border-color: #b9c3f5;
  background: #f1f3ff;
  box-shadow: 0 4px 12px rgba(64, 87, 214, .16);
  transform: translateY(-1px);
}
.informatica-help-button:focus-visible {
  outline: 3px solid rgba(64, 87, 214, .18);
  outline-offset: 2px;
}
.informatica-help-button--with-label {
  display: inline-flex;
  width: auto;
  padding: 0 .75rem;
  gap: .4rem;
  border-radius: 999px;
}
.informatica-help-button.btn-light {
  color: #fff;
  border-color: rgba(255, 255, 255, .35);
  background: rgba(255, 255, 255, .14);
  box-shadow: none;
}
.informatica-help-button.btn-light:hover,
.informatica-help-button.btn-light:focus-visible {
  color: #26387f;
  border-color: #fff;
  background: #fff;
}
</style>
