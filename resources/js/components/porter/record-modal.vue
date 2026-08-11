<script>
export default {
  name: "PorterRecordModal",
  props: {
    modelValue: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: "Completa los datos necesarios para conservar la trazabilidad del movimiento.",
    },
    eyebrow: {
      type: String,
      default: "Nuevo registro",
    },
    icon: {
      type: String,
      default: "bx bx-plus-circle",
    },
    size: {
      type: String,
      default: "xl",
    },
  },
  emits: ["update:modelValue", "request-close"],
};
</script>

<template>
  <BModal
    :model-value="modelValue"
    :title="title"
    :size="size"
    hide-header
    hide-footer
    scrollable
    no-close-on-backdrop
    no-close-on-esc
    body-class="p-0"
    modal-class="porter-record-modal"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="porter-record-modal__shell">
      <div class="porter-record-modal__intro">
        <span class="porter-record-modal__icon"><i :class="icon"></i></span>
        <div class="porter-record-modal__copy">
          <div class="porter-record-modal__eyebrow">{{ eyebrow }}</div>
          <h4>{{ title }}</h4>
          <p>{{ subtitle }}</p>
        </div>
        <button type="button" class="porter-record-modal__close" :aria-label="`Cerrar ${title}`" @click="$emit('request-close')">
          <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="porter-record-modal__body">
        <slot />
      </div>
    </div>
  </BModal>
</template>

<style scoped>
:global(.porter-record-modal .modal-dialog) {
  max-width: min(94vw, 86rem);
}

:global(.porter-record-modal .modal-content) {
  border: 0;
  border-radius: 1rem;
  box-shadow: 0 1.5rem 4rem rgba(18, 36, 67, 0.26);
  overflow: hidden;
}

:global(.porter-record-modal .modal-body) {
  background: #f5f8fd;
}

.porter-record-modal__intro {
  align-items: center;
  background:
    radial-gradient(circle at 88% 0, rgba(255, 255, 255, 0.17), transparent 32%),
    linear-gradient(125deg, #182f57 0%, #315f9f 100%);
  color: #fff;
  display: flex;
  gap: 1rem;
  min-height: 7.25rem;
  padding: 1.2rem 1.35rem;
  position: relative;
}

.porter-record-modal__intro::after {
  background-image: radial-gradient(rgba(255, 255, 255, 0.18) 0.7px, transparent 0.7px);
  background-size: 13px 13px;
  content: "";
  inset: 0 0 0 55%;
  opacity: 0.34;
  pointer-events: none;
  position: absolute;
}

.porter-record-modal__icon {
  align-items: center;
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.8rem;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.65rem;
  height: 3.5rem;
  justify-content: center;
  width: 3.5rem;
}

.porter-record-modal__copy {
  min-width: 0;
  position: relative;
  z-index: 1;
}

.porter-record-modal__eyebrow {
  color: #7ce7bb;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}

.porter-record-modal h4 {
  color: #fff;
  font-weight: 750;
  letter-spacing: -0.02em;
  margin: 0;
}

.porter-record-modal p {
  color: rgba(255, 255, 255, 0.7);
  margin: 0.35rem 0 0;
}

.porter-record-modal__close {
  align-items: center;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 0.65rem;
  color: #fff;
  display: inline-flex;
  font-size: 1.35rem;
  height: 2.55rem;
  justify-content: center;
  margin-left: auto;
  position: relative;
  width: 2.55rem;
  z-index: 1;
}

.porter-record-modal__close:hover,
.porter-record-modal__close:focus-visible {
  background: rgba(255, 255, 255, 0.2);
  outline: none;
}

.porter-record-modal__body {
  padding: 1rem;
}

.porter-record-modal__body :deep(.card) {
  border: 1px solid rgba(185, 199, 220, 0.65);
  border-radius: 0.8rem;
  box-shadow: 0 0.55rem 1.6rem rgba(43, 67, 103, 0.07);
  margin: 0;
}

:global([data-bs-theme="dark"] .porter-record-modal .modal-body),
:global(body[data-layout-mode="dark"] .porter-record-modal .modal-body) {
  background: #1c283d;
}

@media (max-width: 575.98px) {
  .porter-record-modal__intro {
    align-items: flex-start;
    padding: 1rem;
  }

  .porter-record-modal__icon {
    display: none;
  }

  .porter-record-modal p {
    font-size: 0.78rem;
  }

  .porter-record-modal__body {
    padding: 0.75rem;
  }
}
</style>
