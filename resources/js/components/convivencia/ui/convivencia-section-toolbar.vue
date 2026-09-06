<script>
export default {
  name: "ConvivenciaSectionToolbar",
  props: {
    title: { type: String, required: true },
    description: { type: String, default: "" },
    icon: { type: String, default: "bx-filter-alt" },
    primaryLabel: { type: String, default: "Nuevo registro" },
    primaryIcon: { type: String, default: "bx-plus" },
    canCreate: { type: Boolean, default: true },
    refreshing: { type: Boolean, default: false },
  },
  emits: ["create", "refresh"],
};
</script>

<template>
  <section class="convivencia-section-toolbar">
    <div class="convivencia-section-toolbar__copy">
      <span aria-hidden="true"><i class="bx" :class="icon"></i></span>
      <div><h2>{{ title }}</h2><p v-if="description">{{ description }}</p></div>
    </div>
    <div class="convivencia-section-toolbar__actions">
      <slot name="filters" />
      <button type="button" class="btn btn-outline-secondary" :disabled="refreshing" title="Actualizar listado" @click="$emit('refresh')"><i class="bx bx-refresh" :class="{ 'bx-spin': refreshing }" aria-hidden="true"></i><span>Actualizar</span></button>
      <button v-if="canCreate" type="button" class="btn btn-primary" @click="$emit('create')"><i class="bx" :class="primaryIcon" aria-hidden="true"></i>{{ primaryLabel }}</button>
    </div>
  </section>
</template>

<style scoped>
.convivencia-section-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border:1px solid #dfe5ef;border-radius:17px;background:#fff;box-shadow:0 9px 22px rgba(35,48,80,.055)}.convivencia-section-toolbar__copy{display:flex;min-width:0;align-items:center;gap:.65rem}.convivencia-section-toolbar__copy>span{display:grid;width:42px;height:42px;flex:0 0 42px;color:#fff;font-size:1.15rem;place-items:center;border-radius:13px;background:linear-gradient(135deg,#4f63d9,#2d9480)}.convivencia-section-toolbar h2{margin:0;color:#263466;font-size:.92rem;font-weight:800}.convivencia-section-toolbar p{margin:.18rem 0 0;color:#7c8799;font-size:.67rem}.convivencia-section-toolbar__actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.5rem}.convivencia-section-toolbar__actions .btn{display:inline-flex;align-items:center;gap:.32rem;font-size:.7rem;font-weight:700;white-space:nowrap}
@media(max-width:991.98px){.convivencia-section-toolbar{align-items:stretch;flex-direction:column}.convivencia-section-toolbar__actions{justify-content:flex-start}}@media(max-width:575.98px){.convivencia-section-toolbar__actions{display:grid;grid-template-columns:1fr 1fr}.convivencia-section-toolbar__actions :deep(.convivencia-toolbar-filter){grid-column:1/-1}.convivencia-section-toolbar__actions .btn{justify-content:center}.convivencia-section-toolbar__copy p{display:none}}
</style>
