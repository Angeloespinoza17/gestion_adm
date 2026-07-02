<script>
export default {
  props: {
    message: {
      type: String,
      default: "Cargando contenido...",
    },
    compact: {
      type: Boolean,
      default: false,
    },
  },
};
</script>

<template>
  <div class="premium-loading-state" :class="{ 'premium-loading-state--compact': compact }" role="status" aria-live="polite">
    <span class="spinner-border text-primary" :class="compact ? 'spinner-border-sm' : ''" aria-hidden="true"></span>
    <span class="premium-loading-state__message">{{ message }}</span>
  </div>
</template>

<style scoped>
.premium-loading-state {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  width: 100%;
  min-height: 5rem;
  padding: 1rem 1.25rem;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.42);
  border: 1px solid rgba(255, 255, 255, 0.55);
  color: #6d7891;
  text-align: center;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

.premium-loading-state--compact {
  min-height: auto;
  padding: 0.9rem 1rem;
  border-radius: 0.95rem;
}

.premium-loading-state__message {
  font-size: 0.95rem;
  font-weight: 500;
}
</style>
