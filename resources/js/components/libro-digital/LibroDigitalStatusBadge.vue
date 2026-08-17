<script setup>
import { computed } from "vue";
import { humanize, statusVariant } from "./module-utils";

const props = defineProps({
  status: { type: String, default: "" },
  label: { type: String, default: "" },
});

const variant = computed(() => statusVariant(props.status));
const text = computed(() => props.label || humanize(props.status));
</script>

<template>
  <BBadge :variant="variant" class="lcd-status-badge" :class="`lcd-status-badge--${variant}`" :aria-label="`Estado: ${text}`" :title="text">
    <span class="lcd-status-badge__dot" aria-hidden="true"></span><span class="lcd-status-badge__label">{{ text }}</span>
  </BBadge>
</template>

<style scoped>
.lcd-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  max-width: min(100%, 280px);
  min-height: 28px;
  padding: 0.32rem 0.58rem;
  border: 1px solid currentColor;
  border-radius: 999px;
  background: transparent !important;
  font-size: 0.72rem;
  font-weight: 750;
  letter-spacing: 0.005em;
  line-height: 1.25;
}

.lcd-status-badge__dot {
  flex: 0 0 7px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 13%, transparent);
}

.lcd-status-badge__label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.lcd-status-badge--primary { color: var(--lcd-brand-700) !important; }
.lcd-status-badge--secondary,
.lcd-status-badge--light,
.lcd-status-badge--dark { color: var(--lcd-muted) !important; }
.lcd-status-badge--success { color: var(--lcd-success) !important; }
.lcd-status-badge--warning { color: var(--lcd-warning) !important; }
.lcd-status-badge--danger { color: var(--lcd-danger) !important; }
.lcd-status-badge--info { color: var(--lcd-info) !important; }

:global(.lcd-hero .lcd-status-badge) {
  border-color: rgba(255, 255, 255, 0.25);
  background: rgba(255, 255, 255, 0.08) !important;
  color: #eef5fa !important;
}
</style>
