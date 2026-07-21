<script>
import { humanizeCentroApuntesStatus, statusVariant } from "./module-utils";

export default {
  props: {
    status: {
      type: String,
      default: null,
    },
    label: {
      type: String,
      default: null,
    },
  },
  computed: {
    variant() {
      return statusVariant(this.status);
    },
    displayLabel() {
      return this.label || humanizeCentroApuntesStatus(this.status);
    },
  },
};
</script>

<template>
  <BBadge :variant="variant" class="status-chip" :class="`status-chip--${variant}`">
    <span class="status-dot" aria-hidden="true"></span>
    <span>{{ displayLabel }}</span>
  </BBadge>
</template>

<style scoped>
.status-chip {
  align-items: center;
  align-self: flex-start;
  border: 1px solid transparent;
  border-radius: 999px;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: .68rem;
  font-weight: 700;
  gap: .35rem;
  letter-spacing: .01em;
  line-height: 1.1;
  max-width: 100%;
  min-height: 1.55rem;
  padding: .28rem .55rem;
  white-space: nowrap;
}
.status-dot { background: currentColor; border-radius: 50%; flex: 0 0 auto; height: .38rem; width: .38rem; }
.status-chip--primary { background: rgba(var(--bs-primary-rgb), .11) !important; border-color: rgba(var(--bs-primary-rgb), .2); color: var(--bs-primary) !important; }
.status-chip--success { background: rgba(var(--bs-success-rgb), .11) !important; border-color: rgba(var(--bs-success-rgb), .22); color: #16835e !important; }
.status-chip--warning { background: rgba(var(--bs-warning-rgb), .14) !important; border-color: rgba(var(--bs-warning-rgb), .28); color: #9a6810 !important; }
.status-chip--danger { background: rgba(var(--bs-danger-rgb), .11) !important; border-color: rgba(var(--bs-danger-rgb), .22); color: #d64747 !important; }
.status-chip--info { background: rgba(var(--bs-info-rgb), .11) !important; border-color: rgba(var(--bs-info-rgb), .22); color: #257fbd !important; }
.status-chip--secondary { background: rgba(var(--bs-secondary-rgb), .11) !important; border-color: rgba(var(--bs-secondary-rgb), .2); color: var(--bs-secondary) !important; }
.status-chip--light { background: var(--bs-tertiary-bg) !important; border-color: var(--bs-border-color); color: var(--bs-secondary-color) !important; }
@media (max-width: 575.98px) { .status-chip { white-space: normal; } }
</style>
