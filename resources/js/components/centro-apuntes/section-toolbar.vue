<script>
export default {
  props: {
    title: {
      type: String,
      required: true,
    },
    description: {
      type: String,
      default: "",
    },
    icon: {
      type: String,
      default: "bx-grid-alt",
    },
    eyebrow: {
      type: String,
      default: "Gestión operativa",
    },
  },
};
</script>

<template>
  <div class="section-toolbar">
    <div class="section-toolbar__heading">
      <span class="section-toolbar__icon" aria-hidden="true">
        <i class="bx" :class="icon"></i>
      </span>
      <div class="section-toolbar__copy">
        <span class="section-toolbar__eyebrow">{{ eyebrow }}</span>
        <h5>{{ title }}</h5>
        <p v-if="description">{{ description }}</p>
      </div>
    </div>
    <div class="section-toolbar__actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.section-toolbar {
  align-items: center;
  background: linear-gradient(120deg, rgba(var(--bs-primary-rgb), .075), rgba(var(--bs-info-rgb), .035));
  border: 1px solid rgba(var(--bs-primary-rgb), .13);
  border-radius: .85rem;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  padding: .95rem 1rem;
}
.section-toolbar__heading { align-items: center; display: flex; gap: .8rem; min-width: 0; }
.section-toolbar__icon {
  align-items: center;
  background: var(--bs-body-bg);
  border: 1px solid rgba(var(--bs-primary-rgb), .14);
  border-radius: .7rem;
  box-shadow: 0 .35rem 1rem rgba(62, 82, 120, .08);
  color: var(--bs-primary);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.25rem;
  height: 2.8rem;
  justify-content: center;
  width: 2.8rem;
}
.section-toolbar__copy { min-width: 0; }
.section-toolbar__eyebrow { color: var(--bs-primary); display: block; font-size: .64rem; font-weight: 800; letter-spacing: .085em; margin-bottom: .12rem; text-transform: uppercase; }
.section-toolbar h5 { color: var(--bs-heading-color); font-size: .95rem; font-weight: 700; margin: 0; }
.section-toolbar p { color: var(--bs-secondary-color); font-size: .76rem; line-height: 1.35; margin: .2rem 0 0; }
.section-toolbar__actions { align-items: center; display: flex; flex: 0 0 auto; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
@media (max-width: 767.98px) {
  .section-toolbar { align-items: stretch; flex-direction: column; }
  .section-toolbar__heading { align-items: flex-start; }
  .section-toolbar__actions { justify-content: stretch; padding-left: 3.6rem; }
  .section-toolbar__actions :deep(.btn:not(.centro-apuntes-help-button--icon)) { flex: 1 1 auto; }
}
@media (max-width: 575.98px) {
  .section-toolbar { padding: .85rem; }
  .section-toolbar__actions { padding-left: 0; }
}
</style>
