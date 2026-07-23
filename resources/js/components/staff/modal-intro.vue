<script>
export default {
  name: "StaffModalIntro",
  props: {
    title: { type: String, required: true },
    text: { type: String, required: true },
    icon: { type: String, default: "bx-info-circle" },
    tone: { type: String, default: "primary" },
  },
};
</script>

<template>
  <div :class="`staff-modal-intro staff-modal-intro--${tone}`">
    <span class="staff-modal-intro__icon" aria-hidden="true">
      <i :class="`bx ${icon}`"></i>
    </span>
    <div class="staff-modal-intro__copy">
      <strong>{{ title }}</strong>
      <span>{{ text }}</span>
    </div>
    <div v-if="$slots.default" class="staff-modal-intro__action">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.staff-modal-intro {
  align-items: center;
  background: linear-gradient(135deg, rgba(85, 110, 230, 0.09), rgba(52, 195, 143, 0.05));
  border: 1px solid rgba(85, 110, 230, 0.16);
  border-radius: 14px;
  display: flex;
  gap: 0.85rem;
  margin-bottom: 1.25rem;
  padding: 1rem;
}

.staff-modal-intro--success {
  background: linear-gradient(135deg, rgba(52, 195, 143, 0.11), rgba(80, 165, 241, 0.05));
  border-color: rgba(52, 195, 143, 0.22);
}

.staff-modal-intro__icon {
  align-items: center;
  background: #fff;
  border-radius: 11px;
  color: #556ee6;
  display: inline-flex;
  flex: 0 0 2.65rem;
  font-size: 1.25rem;
  height: 2.65rem;
  justify-content: center;
  box-shadow: 0 8px 18px rgba(42, 48, 66, 0.08);
}

.staff-modal-intro__copy {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.staff-modal-intro__copy strong {
  color: #2a3042;
  font-size: 0.92rem;
}

.staff-modal-intro__copy span {
  color: #667085;
  font-size: 0.83rem;
  line-height: 1.45;
}

@media (max-width: 575.98px) {
  .staff-modal-intro {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .staff-modal-intro__action {
    flex: 1 1 100%;
  }
}
</style>
