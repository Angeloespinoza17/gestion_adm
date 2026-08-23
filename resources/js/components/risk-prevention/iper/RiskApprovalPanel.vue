<script setup>
import { computed, ref } from "vue";
const props = defineProps({ version: { type: Object, required: true }, permissions: { type: Array, default: () => [] }, busy: Boolean });
const emit = defineEmits(["action"]);
const reason = ref("");
const exception = ref(false);
const status = computed(() => typeof props.version.status === "string" ? props.version.status : props.version.status?.value);
const can = (permission) => props.permissions.includes("__superadmin__") || props.permissions.includes(permission);
function action(name, extra = {}) { emit("action", name, { reason: reason.value, notes: reason.value, justification: reason.value, additional_approval: exception.value, ...extra }); }
</script>

<template>
  <section class="approval-panel">
    <header><div><span>Gobernanza de versión</span><h5>Revisión y aprobación interna</h5></div><span class="state-chip">{{ status }}</span></header>
    <div class="approval-timeline">
      <div class="is-done"><i class="bx bx-edit-alt"></i><span>Borrador</span></div>
      <div :class="{ 'is-done': ['in_review','approved','superseded','archived'].includes(status) }"><i class="bx bx-search-alt"></i><span>Revisión</span></div>
      <div :class="{ 'is-done': version.reviewed_at }"><i class="bx bx-check-circle"></i><span>Validación técnica</span></div>
      <div :class="{ 'is-done': ['approved','superseded','archived'].includes(status) }"><i class="bx bx-lock-alt"></i><span>Aprobada e inmutable</span></div>
    </div>
    <label v-if="!['approved','superseded','archived'].includes(status)"><span>Fundamento u observación</span><textarea v-model="reason" class="form-control" rows="3" placeholder="Registra una decisión trazable…"></textarea></label>
    <label v-if="status === 'in_review' && can('risk-matrix.override-block')" class="exception-check"><input v-model="exception" type="checkbox" /> Confirmo aprobación adicional para una excepción documentada de riesgo intolerable.</label>
    <div class="approval-actions">
      <button v-if="['draft','observed'].includes(status) && can('risk-matrix.submit')" type="button" class="btn btn-primary" :disabled="busy" @click="action('submit')"><i class="bx bx-send"></i> Enviar a revisión</button>
      <button v-if="status === 'in_review' && can('risk-matrix.review')" type="button" class="btn btn-outline-primary" :disabled="busy || !reason" @click="action('review')"><i class="bx bx-check-double"></i> Validar técnicamente</button>
      <button v-if="status === 'in_review' && can('risk-matrix.observe')" type="button" class="btn btn-outline-warning" :disabled="busy || !reason" @click="action('observe')"><i class="bx bx-message-error"></i> Formular observaciones</button>
      <button v-if="status === 'observed' && can('risk-matrix.review')" type="button" class="btn btn-light" :disabled="busy || !reason" @click="action('draft')">Devolver a borrador</button>
      <button v-if="status === 'in_review' && can('risk-matrix.approve')" type="button" class="btn btn-success" :disabled="busy" @click="action('approve')"><i class="bx bx-badge-check"></i> Aprobar y bloquear</button>
    </div>
    <p class="approval-note"><i class="bx bx-info-circle"></i> Esta acción registra una aprobación interna. No se presenta como firma electrónica avanzada.</p>
  </section>
</template>

<style scoped>
.approval-panel{padding:1rem;border:1px solid #dce4ec;border-radius:12px;background:linear-gradient(145deg,#fff,#f8fbfd)}.approval-panel header{display:flex;justify-content:space-between;gap:1rem}.approval-panel header span,.approval-panel label>span{color:#667085;font-size:.62rem;font-weight:750;text-transform:uppercase;letter-spacing:.05em}.approval-panel h5{margin:.12rem 0}.state-chip{align-self:flex-start;padding:.28rem .5rem;border-radius:999px;background:#e9f2f8;color:#28536f!important}.approval-timeline{display:grid;grid-template-columns:repeat(4,1fr);margin:1rem 0}.approval-timeline div{position:relative;display:flex;align-items:center;gap:.35rem;color:#98a2b3;font-size:.63rem}.approval-timeline div:not(:last-child):after{position:absolute;z-index:0;right:5%;left:65%;height:2px;background:#e4e7ec;content:""}.approval-timeline i{z-index:1;display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:#eaecf0;font-size:.95rem}.approval-timeline .is-done{color:#175cd3}.approval-timeline .is-done i,.approval-timeline .is-done:after{background:#d1e9ff}.approval-actions{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.7rem}.approval-actions .btn{display:inline-flex;align-items:center;gap:.35rem}.exception-check{display:flex;align-items:flex-start;gap:.4rem;margin-top:.65rem;padding:.55rem;background:#fff7ed;color:#9a3412;font-size:.66rem}.approval-note{display:flex;align-items:center;gap:.3rem;margin:.75rem 0 0;color:#667085;font-size:.62rem}@media(max-width:700px){.approval-timeline{grid-template-columns:1fr 1fr;gap:.6rem}.approval-timeline div:after{display:none}}
</style>
