<script>
import "./permission-ui.css";

export default {
  props: {
    eyebrow: {
      type: String,
      default: "Permisos del personal",
    },
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: "",
    },
    icon: {
      type: String,
      default: "bx-calendar-check",
    },
  },
};
</script>

<template>
  <section class="permission-page-header">
    <div class="permission-page-header__main">
      <div class="permission-page-header__icon">
        <i :class="`bx ${icon}`"></i>
      </div>
      <div class="min-w-0">
        <div class="permission-page-header__eyebrow">{{ eyebrow }}</div>
        <h4 class="permission-page-header__title">{{ title }}</h4>
        <div v-if="subtitle" class="permission-page-header__subtitle">{{ subtitle }}</div>
      </div>
    </div>
    <div v-if="$slots.actions" class="permission-page-header__actions">
      <slot name="actions" />
    </div>
  </section>
</template>
