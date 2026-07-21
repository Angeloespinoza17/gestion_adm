<script>
const variants = {
  registrado: "primary",
  autorizado: "success",
  observado: "warning",
  rechazado: "danger",
  rechazada: "danger",
  anulado: "secondary",
  destacado: "warning",
  novedad: "info",
  incidencia: "warning",
  visita: "primary",
  proveedor: "primary",
  llaves: "warning",
  observacion: "secondary",
  activo: "success",
  retirado: "secondary",
  egresado: "info",
  suspendido: "warning",
  matriculada: "primary",
  regular: "success",
  retirada: "secondary",
  egresada: "info",
  suspendida: "warning",
  trasladada: "info",
  recibido_en_porteria: "primary",
  derivado: "info",
  entregado_al_destinatario: "success",
  objeto: "primary",
  documento: "info",
  material_escolar: "success",
  medicamento: "warning",
  encomienda: "primary",
  otro: "secondary",
  derivado_a_departamento: "info",
  entregado_a_responsable: "success",
  pendiente: "warning",
  rechazado_item: "danger",
  recepcion_mercaderia: "primary",
  entrega_mercaderia: "success",
  retiro_mercaderia: "warning",
  guia_despacho: "info",
  factura: "primary",
  orden_compra: "warning",
  boleta: "success",
  disponible: "success",
  activa: "success",
  inactiva: "secondary",
  en_curso: "success",
  finalizado: "primary",
  finalizada: "primary",
  prestada: "warning",
  observada: "warning",
  devuelta: "success",
  alta: "danger",
  media: "warning",
  baja: "info",
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
