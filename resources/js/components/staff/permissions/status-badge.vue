<script>
import "./permission-ui.css";

const STATUS_MAP = {
  borrador: { label: "Borrador", variant: "secondary", icon: "bx-edit" },
  ingresado: { label: "Ingresado", variant: "primary", icon: "bx-send" },
  observado: { label: "Observado", variant: "warning", icon: "bx-message-square-error" },
  pendiente: { label: "Pendiente", variant: "warning", icon: "bx-time-five" },
  pendiente_jefatura: { label: "Pendiente jefatura", variant: "warning", icon: "bx-user-check" },
  pendiente_direccion: { label: "Pendiente Dirección", variant: "warning", icon: "bx-buildings" },
  pendiente_rrhh: { label: "Pendiente RRHH", variant: "warning", icon: "bx-id-card" },
  aprobado: { label: "Aprobado", variant: "success", icon: "bx-check-circle" },
  validado: { label: "Validado", variant: "success", icon: "bx-check-shield" },
  ejecutado: { label: "Ejecutado", variant: "info", icon: "bx-check-double" },
  rechazado: { label: "Rechazado", variant: "danger", icon: "bx-x-circle" },
  cancelado: { label: "Cancelado", variant: "secondary", icon: "bx-block" },
  inactivo: { label: "Inactivo", variant: "secondary", icon: "bx-pause-circle" },
  activo: { label: "Activo", variant: "success", icon: "bx-check-circle" },
};

export default {
  props: {
    status: {
      type: [String, Boolean, Number],
      default: "",
    },
    label: {
      type: String,
      default: "",
    },
    variant: {
      type: String,
      default: "",
    },
    icon: {
      type: String,
      default: "",
    },
  },
  computed: {
    normalizedStatus() {
      return String(this.status ?? "").toLowerCase();
    },
    config() {
      return STATUS_MAP[this.normalizedStatus] || {
        label: this.formatLabel(this.status),
        variant: "dark",
        icon: "bx-circle",
      };
    },
    resolvedLabel() {
      return this.label || this.config.label;
    },
    resolvedVariant() {
      return this.variant || this.config.variant;
    },
    resolvedIcon() {
      return this.icon || this.config.icon;
    },
  },
  methods: {
    formatLabel(value) {
      if (value === null || value === undefined || value === "") return "Sin estado";
      return String(value)
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
  },
};
</script>

<template>
  <span :class="`permission-status-badge permission-status-badge--${resolvedVariant}`">
    <i v-if="resolvedIcon" :class="`bx ${resolvedIcon}`"></i>
    <span>{{ resolvedLabel }}</span>
  </span>
</template>
