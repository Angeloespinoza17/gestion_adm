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
  computed: {
    hasVisibleLabel() {
      return Boolean(this.buttonText && this.buttonText !== "?");
    },
    accessibleLabel() {
      return this.title.replace(/^Ayuda:\s*/i, "Ayuda sobre ");
    },
  },
  methods: {
    openHelp() {
      return Swal.fire({
        title: this.title,
        text: this.text,
        icon: "info",
        confirmButtonText: "Entendido",
        buttonsStyling: false,
        customClass: {
          popup: "infirmary-help-dialog",
          title: "infirmary-help-dialog__title",
          htmlContainer: "infirmary-help-dialog__content",
          confirmButton: "btn btn-primary infirmary-help-dialog__confirm",
        },
      });
    },
  },
};
</script>

<template>
  <BButton
    :variant="variant"
    :size="size"
    class="infirmary-help-button"
    :class="{ 'has-label': hasVisibleLabel }"
    type="button"
    :title="accessibleLabel"
    :aria-label="accessibleLabel"
    @click="openHelp"
  >
    <span class="help-glyph" aria-hidden="true">?</span>
    <span v-if="hasVisibleLabel" class="help-label">{{ buttonText }}</span>
  </BButton>
</template>

<style scoped>
.infirmary-help-button {
  position: relative;
  width: 36px;
  height: 36px;
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  padding: 0;
  overflow: visible;
  border: 1px solid #cfd9ea;
  border-radius: 50%;
  background: linear-gradient(145deg, #ffffff 0%, #eef4ff 100%);
  color: #3568d4;
  box-shadow: 0 4px 12px rgba(48, 82, 145, 0.12);
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
}

.infirmary-help-button::after {
  position: absolute;
  inset: 3px;
  z-index: 0;
  border: 1px solid rgba(53, 104, 212, 0.12);
  border-radius: inherit;
  content: "";
}

.infirmary-help-button:hover,
.infirmary-help-button:focus-visible {
  border-color: #7e9fe3;
  background: linear-gradient(145deg, #f8fbff 0%, #e2ecff 100%);
  color: #234fbd;
  box-shadow: 0 7px 18px rgba(48, 82, 145, 0.2);
  transform: translateY(-1px);
}

.infirmary-help-button:focus-visible {
  outline: 3px solid rgba(53, 104, 212, 0.2);
  outline-offset: 2px;
}

.infirmary-help-button.has-label {
  width: auto;
  padding: 0 12px 0 8px;
  border-radius: 999px;
}

.help-glyph {
  position: relative;
  z-index: 1;
  display: inline-grid;
  width: 24px;
  height: 24px;
  place-items: center;
  border-radius: 50%;
  background: #3568d4;
  color: #ffffff;
  font-family: Inter, system-ui, sans-serif;
  font-size: 15px;
  font-weight: 800;
  line-height: 1;
  box-shadow: 0 2px 5px rgba(53, 104, 212, 0.28);
}

.help-label {
  position: relative;
  z-index: 1;
  margin-left: 6px;
  color: #31569d;
  font-size: 12px;
  font-weight: 700;
}

:global(.infirmary-help-dialog) {
  max-width: 520px;
  padding: 28px 28px 24px !important;
  border: 1px solid #dbe4f2 !important;
  border-radius: 18px !important;
  box-shadow: 0 24px 70px rgba(35, 51, 80, 0.22) !important;
}

:global(.infirmary-help-dialog .swal2-icon) {
  margin-top: 4px;
  transform: scale(0.82);
}

:global(.infirmary-help-dialog__title) {
  padding-top: 4px !important;
  color: #263247 !important;
  font-size: 21px !important;
}

:global(.infirmary-help-dialog__content) {
  margin: 8px 0 20px !important;
  color: #5f6b7e !important;
  font-size: 14px !important;
  line-height: 1.65 !important;
}

:global(.infirmary-help-dialog__confirm) {
  min-width: 132px;
  border-radius: 9px !important;
  box-shadow: 0 6px 15px rgba(53, 104, 212, 0.2);
}
</style>
