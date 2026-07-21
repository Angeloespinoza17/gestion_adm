<script>
export default {
  props: {
    value: { type: String, default: "" },
  },
  computed: {
    label() {
      if (!this.value) return "Sin criticidad";
      return String(this.value)
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    variant() {
      const map = {
        baja: "secondary",
        media: "warning",
        alta: "danger",
        critica: "dark",
      };

      return map[String(this.value || "").toLowerCase()] || "info";
    },
  },
};
</script>

<template>
  <BBadge pill :variant="variant" class="convivencia-criticality-badge"><i class="bx bx-pulse"></i>{{ label }}</BBadge>
</template>

<style scoped>
.convivencia-criticality-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  line-height: 1;
}
</style>
