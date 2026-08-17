<script setup>
import { computed } from "vue";

const props = defineProps({
  state: { type: String, default: "empty" },
  title: { type: String, default: "Sin información" },
  message: { type: String, default: "No hay registros para los filtros seleccionados." },
  compact: { type: Boolean, default: false },
});

defineEmits(["retry"]);

const icon = computed(() => ({
  error: "bx-error-circle",
  success: "bx-check-circle",
  restricted: "bx-lock-alt",
  warning: "bx-error",
}[props.state] || "bx-folder-open"));
</script>

<template>
  <div class="lcd-state" :class="[{ 'lcd-state--compact': compact }, `lcd-state--${state}`]" :role="state === 'error' ? 'alert' : 'status'" aria-live="polite">
    <div class="lcd-state__visual" aria-hidden="true">
      <span v-if="state === 'loading'" class="lcd-state__spinner"><span class="spinner-border"></span></span>
      <i v-else class="bx" :class="icon"></i>
    </div>
    <div class="lcd-state__copy">
      <strong>{{ title }}</strong>
      <span>{{ message }}</span>
    </div>
    <BButton v-if="state === 'error'" type="button" size="sm" variant="outline-danger" @click="$emit('retry')"><i class="bx bx-reset" aria-hidden="true"></i> Reintentar</BButton>
    <div v-if="$slots.default" class="lcd-state__actions"><slot /></div>
  </div>
</template>

<style scoped>
.lcd-state {
  position: relative;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 0.8rem;
  min-height: 300px;
  overflow: hidden;
  padding: 2rem;
  border: 1px solid var(--lcd-border);
  border-radius: var(--lcd-radius-lg);
  background:
    radial-gradient(circle at 50% -20%, var(--lcd-brand-100), transparent 48%),
    var(--lcd-surface-raised);
  box-shadow: var(--lcd-shadow-sm);
  color: var(--lcd-muted);
  text-align: center;
}

.lcd-state::before {
  position: absolute;
  inset: 10px;
  border: 1px dashed var(--lcd-border-strong);
  border-radius: calc(var(--lcd-radius-lg) - 5px);
  content: "";
  pointer-events: none;
}

.lcd-state--compact {
  min-height: 170px;
  padding: 1.35rem;
}

.lcd-state__visual,
.lcd-state__copy,
.lcd-state > .btn,
.lcd-state__actions {
  position: relative;
  z-index: 1;
}

.lcd-state__visual {
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  border: 1px solid var(--lcd-border);
  border-radius: 18px;
  background: var(--lcd-surface);
  box-shadow: var(--lcd-shadow-sm);
  color: var(--lcd-brand-600);
  font-size: 1.65rem;
}

.lcd-state--compact .lcd-state__visual {
  width: 46px;
  height: 46px;
  border-radius: 14px;
  font-size: 1.35rem;
}

.lcd-state__spinner {
  display: grid;
  place-items: center;
}

.lcd-state__spinner .spinner-border {
  width: 1.45rem;
  height: 1.45rem;
  border-width: 0.15em;
  color: var(--lcd-brand-600);
}

.lcd-state__copy {
  display: grid;
  justify-items: center;
  gap: 0.28rem;
}

.lcd-state__copy strong {
  color: var(--lcd-ink);
  font-size: 0.94rem;
  font-weight: 750;
}

.lcd-state__copy span {
  max-width: 540px;
  font-size: 0.8rem;
  line-height: 1.6;
}

.lcd-state--error .lcd-state__visual {
  border-color: rgba(180, 62, 77, 0.22);
  background: rgba(180, 62, 77, 0.08);
  color: var(--lcd-danger);
}

.lcd-state--success .lcd-state__visual {
  border-color: rgba(36, 115, 90, 0.22);
  background: rgba(36, 115, 90, 0.08);
  color: var(--lcd-success);
}

.lcd-state--warning .lcd-state__visual {
  border-color: rgba(165, 104, 25, 0.22);
  background: rgba(165, 104, 25, 0.08);
  color: var(--lcd-warning);
}

.lcd-state__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem;
}

@media (max-width: 575.98px) {
  .lcd-state {
    min-height: 240px;
    padding: 1.4rem;
  }

  .lcd-state--compact {
    min-height: 155px;
  }
}
</style>
