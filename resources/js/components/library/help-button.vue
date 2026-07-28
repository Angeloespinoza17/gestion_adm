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
  computed: {
    accessibleLabel() {
      return this.title?.replace(/^Ayuda:\s*/i, "Ayuda sobre ") || "Ver información";
    },
    dialogTitle() {
      return this.title?.replace(/^Ayuda:\s*/i, "") || "Información del módulo";
    },
    showButtonText() {
      return Boolean(this.buttonText && this.buttonText !== "?");
    },
  },
  methods: {
    openHelp() {
      return Swal.fire({
        title: this.dialogTitle,
        text: this.text,
        icon: "info",
        confirmButtonText: "Entendido",
        showCloseButton: true,
        buttonsStyling: false,
        width: 480,
        customClass: {
          popup: "library-help-dialog",
          title: "library-help-dialog__title",
          htmlContainer: "library-help-dialog__text",
          icon: "library-help-dialog__icon",
          confirmButton: "library-help-dialog__confirm",
          closeButton: "library-help-dialog__close",
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
    class="library-help-button"
    :class="{ 'library-help-button--with-label': showButtonText }"
    :aria-label="accessibleLabel"
    :title="accessibleLabel"
    @click="openHelp"
  >
    <i class="bx bx-info-circle" aria-hidden="true"></i>
    <span v-if="showButtonText">{{ buttonText }}</span>
  </BButton>
</template>

<style scoped>
.library-help-button {
  display: inline-grid;
  flex: 0 0 auto;
  width: 38px;
  height: 38px;
  min-width: 38px;
  padding: 0 !important;
  place-items: center;
  color: #66738f !important;
  background: rgba(255, 255, 255, 0.94) !important;
  border: 1px solid rgba(207, 216, 234, 0.98) !important;
  border-radius: 12px !important;
  box-shadow: 0 5px 16px rgba(29, 43, 72, 0.1);
  transition:
    color 160ms ease,
    background-color 160ms ease,
    border-color 160ms ease,
    box-shadow 160ms ease,
    transform 160ms ease;
}

.library-help-button i {
  font-size: 1.2rem;
  line-height: 1;
}

.library-help-button:hover {
  color: #4f63d8 !important;
  background: #f2f4ff !important;
  border-color: #b7c2f3 !important;
  box-shadow: 0 8px 20px rgba(75, 96, 204, 0.18);
  transform: translateY(-1px);
}

.library-help-button:focus,
.library-help-button:focus-visible {
  color: #4f63d8 !important;
  background: #f2f4ff !important;
  border-color: #8fa0eb !important;
  box-shadow:
    0 0 0 4px rgba(82, 104, 221, 0.14),
    0 8px 20px rgba(75, 96, 204, 0.16) !important;
}

.library-help-button:active {
  transform: translateY(0);
}

.library-help-button--with-label {
  display: inline-flex;
  width: auto;
  min-width: 0;
  padding: 0 0.9rem !important;
  gap: 0.42rem;
  border-radius: 999px !important;
  font-size: 0.74rem;
  font-weight: 750;
  letter-spacing: 0.01em;
}
</style>

<style>
.library-help-dialog {
  padding: 1.75rem 1.75rem 1.6rem !important;
  border: 1px solid #e4e8f3 !important;
  border-radius: 22px !important;
  box-shadow: 0 24px 70px rgba(24, 38, 68, 0.2) !important;
}

.library-help-dialog__icon {
  width: 64px !important;
  height: 64px !important;
  margin: 0.2rem auto 1rem !important;
  color: #5369df !important;
  border-color: #cbd3fb !important;
}

.library-help-dialog__title {
  padding: 0 1.5rem !important;
  color: #1e3154 !important;
  font-size: 1.35rem !important;
  font-weight: 800 !important;
  line-height: 1.25 !important;
}

.library-help-dialog__text {
  margin: 0.65rem auto 0 !important;
  padding: 0 !important;
  max-width: 390px;
  color: #68748b !important;
  font-size: 0.96rem !important;
  line-height: 1.65 !important;
}

.library-help-dialog__confirm {
  min-width: 132px;
  margin-top: 1.35rem;
  padding: 0.72rem 1.2rem;
  color: #fff;
  font-weight: 750;
  background: linear-gradient(135deg, #536be4, #6f83e8);
  border: 0;
  border-radius: 12px;
  box-shadow: 0 8px 18px rgba(83, 107, 228, 0.24);
  transition:
    box-shadow 160ms ease,
    transform 160ms ease;
}

.library-help-dialog__confirm:hover {
  box-shadow: 0 10px 22px rgba(83, 107, 228, 0.32);
  transform: translateY(-1px);
}

.library-help-dialog__confirm:focus-visible {
  outline: 3px solid rgba(83, 107, 228, 0.24);
  outline-offset: 3px;
}

.library-help-dialog__close {
  top: 0.8rem !important;
  right: 0.8rem !important;
  width: 36px !important;
  height: 36px !important;
  color: #7d879a !important;
  border-radius: 10px !important;
}

.library-help-dialog__close:hover {
  color: #304268 !important;
  background: #f1f3f8 !important;
}
</style>
