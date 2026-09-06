<script>
export default {
  name: "ConvivenciaRowActions",
  props: {
    actions: { type: Array, default: () => [] },
    itemLabel: { type: String, default: "registro" },
  },
  emits: ["select"],
  computed: {
    visibleActions() { return this.actions.filter((action) => action && action.visible !== false); },
  },
  methods: {
    actionClass(action) {
      return `is-${action.tone || (action.key === "archive" || action.key === "delete" ? "danger" : "primary")}`;
    },
  },
};
</script>

<template>
  <div class="convivencia-row-actions" role="group" :aria-label="`Acciones para ${itemLabel}`">
    <button
      v-for="action in visibleActions"
      :key="action.key"
      type="button"
      class="convivencia-row-actions__button"
      :class="[actionClass(action), { 'is-icon-only': action.iconOnly, 'is-prominent': action.prominent }]"
      :disabled="action.disabled"
      :title="`${action.label} ${itemLabel}`"
      :aria-label="`${action.label} ${itemLabel}`"
      :aria-busy="action.loading ? 'true' : undefined"
      @click="$emit('select', action.key)"
    >
      <i class="bx" :class="action.loading ? 'bx-loader-alt bx-spin' : (action.icon || 'bx-dots-horizontal-rounded')" aria-hidden="true"></i><span class="convivencia-row-actions__label">{{ action.label }}</span>
    </button>
  </div>
</template>

<style scoped>
.convivencia-row-actions{display:flex;flex-wrap:nowrap;align-items:center;justify-content:flex-end;gap:.38rem}.convivencia-row-actions__button{position:relative;display:inline-flex;min-height:34px;align-items:center;justify-content:center;gap:.34rem;padding:.42rem .65rem;color:#53617a;font-size:.64rem;font-weight:780;line-height:1;border:1px solid #dbe2ed;border-radius:10px;background:linear-gradient(180deg,#fff,#f9fbfd);box-shadow:0 3px 9px rgba(39,50,82,.045);white-space:nowrap;transition:transform .16s ease,box-shadow .16s ease,color .16s ease,background .16s ease,border-color .16s ease}.convivencia-row-actions__button i{font-size:.9rem}.convivencia-row-actions__button:hover:not(:disabled){z-index:1;transform:translateY(-2px);color:#3d51c5;border-color:#bdc8f3;background:#f1f4ff;box-shadow:0 8px 18px rgba(53,71,160,.14)}.convivencia-row-actions__button:focus-visible{z-index:2;outline:3px solid rgba(79,99,217,.2);outline-offset:2px}.convivencia-row-actions__button.is-icon-only{width:34px;padding:0;border-radius:10px}.convivencia-row-actions__button.is-icon-only .convivencia-row-actions__label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}.convivencia-row-actions__button.is-prominent{color:#fff;border-color:#4f63d9;background:linear-gradient(135deg,#5065dc,#3d51c5);box-shadow:0 6px 14px rgba(63,81,193,.2)}.convivencia-row-actions__button.is-prominent:hover:not(:disabled){color:#fff;border-color:#3448b5;background:linear-gradient(135deg,#4459d0,#3448b5)}.convivencia-row-actions__button.is-success{color:#24765f;border-color:#cce6de;background:#f6fbf9}.convivencia-row-actions__button.is-success:hover:not(:disabled){color:#17614c;border-color:#9fd3c3;background:#eaf8f3}.convivencia-row-actions__button.is-warning{color:#936117;border-color:#ecd9b7;background:#fffaf1}.convivencia-row-actions__button.is-danger{color:#b44553;border-color:#efcfd3;background:#fff8f8}.convivencia-row-actions__button.is-danger:hover:not(:disabled){color:#9d3341;border-color:#e7adb5;background:#fff0f1}.convivencia-row-actions__button.is-pdf{color:#b4434d;border-color:#efcbd0;background:#fff7f7}.convivencia-row-actions__button.is-pdf:hover:not(:disabled){color:#fff;border-color:#bd3d48;background:linear-gradient(135deg,#d55861,#bd3d48)}.convivencia-row-actions__button:disabled{cursor:not-allowed;opacity:.5;box-shadow:none}
@media(max-width:767.98px){.convivencia-row-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:.15rem}.convivencia-row-actions__button,.convivencia-row-actions__button.is-icon-only{width:100%;justify-content:center;padding:.58rem}.convivencia-row-actions__button.is-icon-only .convivencia-row-actions__label{position:static;width:auto;height:auto;overflow:visible;clip:auto;white-space:normal}}
</style>
