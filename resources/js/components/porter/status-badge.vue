<script>
const variants = {
  registrado: "primary",
  autorizado: "success",
  observado: "warning",
  rechazado: "danger",
  anulado: "secondary",
  recibido_en_porteria: "primary",
  derivado: "info",
  entregado_al_destinatario: "success",
  derivado_a_departamento: "info",
  entregado_a_responsable: "success",
  pendiente: "warning",
  rechazado_item: "danger",
};

export default {
  props: {
    value: {
      type: String,
      default: "",
    },
    label: {
      type: String,
      default: "",
    },
  },
  computed: {
    normalizedVariant() {
      if (this.value === "rechazado") return "danger";
      return variants[this.value] || "secondary";
    },
    text() {
      return this.label || this.value || "-";
    },
  },
};
</script>

<template>
  <BBadge :variant="normalizedVariant">{{ text }}</BBadge>
</template>
