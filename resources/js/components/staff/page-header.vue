<script>
export default {
  name: "StaffPageHeader",
  props: {
    eyebrow: { type: String, default: "Funcionarios" },
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    icon: { type: String, default: "bx-group" },
  },
};
</script>

<template>
  <section class="staff-page-hero">
    <div class="staff-page-hero__content">
      <span class="staff-page-hero__icon" aria-hidden="true">
        <i :class="`bx ${icon}`"></i>
      </span>
      <div>
        <span class="staff-page-hero__eyebrow">{{ eyebrow }}</span>
        <h4 class="staff-page-hero__title">{{ title }}</h4>
        <p v-if="subtitle" class="staff-page-hero__subtitle">{{ subtitle }}</p>
      </div>
    </div>
    <div v-if="$slots.actions" class="staff-page-hero__actions">
      <slot name="actions" />
    </div>
  </section>
</template>
