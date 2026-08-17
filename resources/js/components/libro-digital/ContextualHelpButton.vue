<script setup>
import Swal from "sweetalert2";

const props = defineProps({
  title: { type: String, required: true },
  explanation: { type: String, required: true },
  responsible: { type: String, default: "Responsable según asignación y permisos vigentes." },
  closingRule: { type: String, default: "Los cierres y reaperturas requieren validación del backend y quedan auditados." },
  statusHelp: { type: String, default: "Borrador permite edición; firmado o cerrado exige una enmienda formal." },
  source: { type: String, default: "Perfil normativo vigente del Libro Digital." },
});

const open = () => Swal.fire({
  title: props.title,
  html: `
    <div class="text-start small lh-lg">
      <p>${props.explanation}</p>
      <dl class="mb-0">
        <dt>Responsable</dt><dd>${props.responsible}</dd>
        <dt>Regla de cierre</dt><dd>${props.closingRule}</dd>
        <dt>Estados</dt><dd>${props.statusHelp}</dd>
        <dt>Referencia</dt><dd>${props.source}</dd>
      </dl>
    </div>
  `,
  icon: "info",
  confirmButtonText: "Entendido",
  focusConfirm: true,
  width: 620,
  customClass: {
    popup: "lcd-help-dialog",
    confirmButton: "lcd-help-dialog__confirm",
  },
});
</script>

<template>
  <BButton
    type="button"
    size="sm"
    variant="outline-secondary"
    class="lcd-help-button"
    :aria-label="`Ayuda contextual: ${title}`"
    :title="`Ayuda sobre ${title}`"
    @click="open"
  >
    <span class="lcd-help-button__icon" aria-hidden="true"><i class="bx bx-help-circle"></i></span>
    <span>Ayuda</span>
  </BButton>
</template>

<style scoped>
.lcd-help-button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 36px;
  padding: 0.32rem 0.62rem;
  border-color: rgba(255, 255, 255, 0.24);
  border-radius: var(--lcd-radius-sm);
  background: rgba(255, 255, 255, 0.08);
  color: #f3f7fa;
  font-size: 0.78rem;
  font-weight: 750;
  backdrop-filter: blur(8px);
}

.lcd-help-button:hover,
.lcd-help-button:focus-visible {
  border-color: rgba(255, 255, 255, 0.42);
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
}

.lcd-help-button__icon {
  display: grid;
  place-items: center;
  font-size: 1rem;
}

@media (max-width: 420px) {
  .lcd-help-button > span:last-child {
    display: none;
  }

  .lcd-help-button {
    width: 36px;
    padding: 0;
    justify-content: center;
  }
}
</style>
