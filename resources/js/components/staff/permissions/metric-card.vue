<script>
import "./permission-ui.css";

export default {
  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: [String, Number],
      default: 0,
    },
    hint: {
      type: String,
      default: "",
    },
    icon: {
      type: String,
      default: "bx-bar-chart-alt-2",
    },
    variant: {
      type: String,
      default: "primary",
    },
  },
};
</script>

<template>
  <div :class="`permission-metric-card permission-metric-card--${variant}`">
    <div class="permission-metric-card__body">
      <div>
        <div class="permission-metric-card__label">{{ label }}</div>
        <div class="permission-metric-card__value">{{ value }}</div>
      </div>
      <div class="permission-metric-card__icon">
        <i :class="`bx ${icon}`"></i>
      </div>
    </div>
    <div v-if="hint" class="permission-metric-card__hint">{{ hint }}</div>
  </div>
</template>
