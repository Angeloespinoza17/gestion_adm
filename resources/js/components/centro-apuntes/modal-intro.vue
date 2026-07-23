<script>
export default {
  props: {
    title: {
      type: String,
      required: true,
    },
    text: {
      type: String,
      required: true,
    },
    icon: {
      type: String,
      default: "bx-info-circle",
    },
  },
};
</script>

<template>
  <div class="centro-apuntes-modal-intro">
    <span class="centro-apuntes-modal-intro__icon" aria-hidden="true">
      <i class="bx" :class="icon"></i>
    </span>
    <div class="centro-apuntes-modal-intro__copy">
      <strong>{{ title }}</strong>
      <span>{{ text }}</span>
    </div>
    <div v-if="$slots.default" class="centro-apuntes-modal-intro__action">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.centro-apuntes-modal-intro {
  align-items: center;
  background: linear-gradient(120deg, rgba(var(--bs-primary-rgb), .085), rgba(var(--bs-info-rgb), .04));
  border: 1px solid rgba(var(--bs-primary-rgb), .14);
  border-radius: .8rem;
  display: grid;
  gap: .75rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  margin-bottom: 1.15rem;
  padding: .8rem .9rem;
}
.centro-apuntes-modal-intro__icon {
  align-items: center;
  background: var(--bs-body-bg);
  border: 1px solid rgba(var(--bs-primary-rgb), .14);
  border-radius: .62rem;
  color: var(--bs-primary);
  display: inline-flex;
  font-size: 1.2rem;
  height: 2.5rem;
  justify-content: center;
  width: 2.5rem;
}
.centro-apuntes-modal-intro__copy { display: flex; flex-direction: column; gap: .14rem; min-width: 0; }
.centro-apuntes-modal-intro__copy strong { color: var(--bs-heading-color); font-size: .8rem; }
.centro-apuntes-modal-intro__copy span { color: var(--bs-secondary-color); font-size: .72rem; line-height: 1.4; }
.centro-apuntes-modal-intro__action { display: flex; }
@media (max-width: 575.98px) {
  .centro-apuntes-modal-intro { align-items: start; grid-template-columns: auto minmax(0, 1fr); }
  .centro-apuntes-modal-intro__action { grid-column: 2; justify-content: flex-start; }
}
</style>
