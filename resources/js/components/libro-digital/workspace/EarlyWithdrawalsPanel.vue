<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import Swal from "sweetalert2";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
  errorMessage,
  formatDateTime,
  payloadItems,
  showError,
  showSuccess,
  studentLabel,
} from "../module-utils";

const props = defineProps({
  bookId: { type: [Number, String], required: true },
  roster: { type: Array, default: () => [] },
  canView: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
});

const localDateTime = () => {
  const date = new Date();
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
const relationships = [
  { value: "madre", label: "Madre" }, { value: "padre", label: "Padre" },
  { value: "apoderado", label: "Apoderado" }, { value: "familiar", label: "Familiar" },
  { value: "transporte", label: "Transporte" }, { value: "otro", label: "Otro" },
];
const reasons = [
  { value: "medico", label: "Médico" }, { value: "familiar", label: "Familiar" },
  { value: "emergencia", label: "Emergencia" }, { value: "tramite", label: "Trámite" },
  { value: "otro", label: "Otro" },
];

const loading = ref(false); const error = ref(null); const items = ref([]); const showForm = ref(false); const saving = ref(false);
const filters = reactive({ status: "", date_from: "", date_to: "" });
const form = reactive({ student_profile_id: null, person_name: "", person_rut: "", person_relationship: "apoderado", person_phone: "", reason: "familiar", observations: "", occurred_at: localDateTime() });
let controller = null;
const students = computed(() => props.roster.map((row) => row.student || row).filter((student) => student?.id));

const load = async () => {
  if (!props.canView) return;
  controller?.abort(); controller = new AbortController(); loading.value = true; error.value = null;
  try { items.value = payloadItems(await libroDigitalApi.earlyWithdrawals(props.bookId, { ...filters, per_page: 100 }, controller.signal)); }
  catch (requestError) { if (requestError?.code !== "ERR_CANCELED") error.value = requestError; }
  finally { loading.value = false; }
};
watch([() => props.bookId, () => filters.status, () => filters.date_from, () => filters.date_to, () => props.canView], load, { immediate: true });
onBeforeUnmount(() => controller?.abort());

const resetForm = () => Object.assign(form, { student_profile_id: null, person_name: "", person_rut: "", person_relationship: "apoderado", person_phone: "", reason: "familiar", observations: "", occurred_at: localDateTime() });
const createPayload = (forceDuplicate = false) => ({
  student_profile_id: form.student_profile_id,
  person_name: form.person_name.trim(), person_rut: form.person_rut.trim() || null,
  person_relationship: form.person_relationship, person_phone: form.person_phone.trim() || null,
  reason: form.reason, observations: form.observations.trim() || null,
  occurred_at: form.occurred_at ? new Date(form.occurred_at).toISOString() : undefined,
  force_duplicate_confirmation: forceDuplicate,
});
const save = async (forceDuplicate = false) => {
  saving.value = true;
  try {
    const created = await libroDigitalApi.createEarlyWithdrawal(props.bookId, createPayload(forceDuplicate));
    showForm.value = false; await load();
    const record = created?.data || created;
    await showSuccess("Salida registrada", record?.requires_special_authorization ? "Quedó observada y sujeta al flujo de autorización especial." : "El registro quedó vinculado al libro y a la nómina vigente.");
  } catch (requestError) {
    if (requestError.code === "LCD_WITHDRAWAL_DUPLICATE" && !forceDuplicate) {
      const confirmation = await Swal.fire({ icon: "warning", title: "Ya existe una salida vigente", text: "El servidor detectó otro registro para esta estudiante en la misma fecha. Confirma solo si corresponde a una salida distinta.", showCancelButton: true, confirmButtonText: "Registrar de todos modos", cancelButtonText: "Revisar" });
      if (confirmation.isConfirmed) { saving.value = false; await save(true); return; }
    } else await showError(requestError, "No se pudo registrar la salida");
  } finally { saving.value = false; }
};
const registerReturn = async (item) => {
  const result = await Swal.fire({
    title: "Registrar retorno", html: '<label for="lcd-return-at" class="swal2-label">Fecha y hora de retorno</label><input id="lcd-return-at" type="datetime-local" class="swal2-input"><label for="lcd-return-reason" class="swal2-label">Motivo o antecedente</label><textarea id="lcd-return-reason" class="swal2-textarea" maxlength="2000"></textarea>',
    showCancelButton: true, confirmButtonText: "Registrar retorno", cancelButtonText: "Cancelar",
    didOpen: () => { document.getElementById("lcd-return-at").value = localDateTime(); },
    preConfirm: () => {
      const returnedAt = document.getElementById("lcd-return-at").value;
      const reason = document.getElementById("lcd-return-reason").value.trim();
      if (!returnedAt || !reason) { Swal.showValidationMessage("Completa fecha, hora y motivo."); return false; }
      return { returned_at: new Date(returnedAt).toISOString(), reason };
    },
  });
  if (!result.isConfirmed) return;
  try { await libroDigitalApi.returnEarlyWithdrawal(item, result.value); await load(); await showSuccess("Retorno registrado", "La salida quedó cerrada en una nueva revisión."); }
  catch (requestError) { await showError(requestError, "No se pudo registrar el retorno"); if (requestError.isConflict) await load(); }
};
</script>

<template>
  <section class="lcd-withdrawals" aria-labelledby="lcd-withdrawals-title" :aria-busy="loading || saving">
    <header><div><span>TRAZABILIDAD DE SALIDA</span><h3 id="lcd-withdrawals-title">Retiros anticipados</h3><p>Registro integrado con portería, nómina vigente, persona responsable, restricciones y evidencia de retorno.</p></div><BButton v-if="canManage" type="button" size="sm" variant="primary" @click="resetForm(); showForm = true"><i class="bx bx-log-out-circle"></i> Registrar salida</BButton></header>
    <BAlert v-if="!canView" show variant="secondary" class="small mb-0">El historial permanece oculto porque no cuentas con capability de consulta de retiros.</BAlert>
    <template v-else>
      <BAlert show variant="info" class="small mb-0">Si la persona no está autorizada o existe una restricción de retiro, el servidor registra el evento como <strong>observado</strong>. Esta vista no permite eludir ni aprobar esa restricción.</BAlert>
      <div class="lcd-withdrawals__filters"><BFormSelect v-model="filters.status" size="sm" aria-label="Filtrar retiros por estado"><option value="">Todos los estados</option><option value="recorded">Registrado</option><option value="authorized">Autorizado</option><option value="observed">Observado</option><option value="returned">Retornado</option></BFormSelect><BFormInput v-model="filters.date_from" type="date" size="sm" aria-label="Retiros desde" /><BFormInput v-model="filters.date_to" type="date" size="sm" aria-label="Retiros hasta" /></div>
      <LibroDigitalStatePanel v-if="loading && !items.length" state="loading" compact title="Cargando retiros" message="Consultando movimientos vinculados al libro." />
      <LibroDigitalStatePanel v-else-if="error && !items.length" state="error" compact title="No se pudieron cargar los retiros" :message="errorMessage(error)" @retry="load" />
      <div v-else-if="items.length" class="table-responsive lcd-withdrawals__table"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Retiros anticipados y retornos registrados para el libro activo</caption><thead><tr><th>Fecha y estudiante</th><th>Persona responsable</th><th>Motivo</th><th>Estado</th><th>Retorno</th><th class="text-end">Acción</th></tr></thead><tbody><tr v-for="item in items" :key="item.id"><td><strong>{{ formatDateTime(item.occurred_at) }}</strong><small>{{ item.student_name }} · {{ item.course }}</small></td><td><strong>{{ item.person_name || '—' }}</strong><small>{{ relationships.find((option) => option.value === item.person_relationship)?.label || item.person_relationship || '—' }}</small></td><td>{{ reasons.find((option) => option.value === item.reason)?.label || item.reason || '—' }}</td><td><LibroDigitalStatusBadge :status="item.status" /><small v-if="item.requires_special_authorization" class="text-warning">Requiere autorización especial</small></td><td>{{ formatDateTime(item.returned_at) }}</td><td><div class="lcd-withdrawals__actions"><BButton v-if="canManage && !item.returned_at && ['recorded','authorized'].includes(item.status)" type="button" size="sm" variant="outline-primary" @click="registerReturn(item)"><i class="bx bx-log-in-circle" aria-hidden="true"></i> Registrar retorno</BButton></div></td></tr></tbody></table></div>
      <LibroDigitalStatePanel v-else compact title="Sin retiros" message="No hay retiros anticipados con los filtros seleccionados." />
    </template>

    <BModal v-model="showForm" title="Registrar retiro anticipado" size="lg" hide-footer>
      <BAlert show variant="warning" class="small">Esta acción registra la salida; no la convierte automáticamente en retiro autorizado. Las reglas de persona autorizada y restricciones las resuelve el backend.</BAlert>
      <form class="row g-3" @submit.prevent="save(false)"><div class="col-md-7"><label class="form-label" for="lcd-withdrawal-student">Estudiante</label><BFormSelect id="lcd-withdrawal-student" v-model="form.student_profile_id" required><option :value="null">Seleccionar de nómina vigente</option><option v-for="student in students" :key="student.id" :value="student.id">{{ studentLabel(student) }}</option></BFormSelect></div><div class="col-md-5"><label class="form-label" for="lcd-withdrawal-at">Fecha y hora</label><BFormInput id="lcd-withdrawal-at" v-model="form.occurred_at" type="datetime-local" required /></div><div class="col-md-6"><label class="form-label" for="lcd-withdrawal-person">Persona que retira</label><BFormInput id="lcd-withdrawal-person" v-model.trim="form.person_name" maxlength="191" required /></div><div class="col-md-3"><label class="form-label" for="lcd-withdrawal-rut">RUT informado</label><BFormInput id="lcd-withdrawal-rut" v-model.trim="form.person_rut" maxlength="20" /></div><div class="col-md-3"><label class="form-label" for="lcd-withdrawal-phone">Teléfono</label><BFormInput id="lcd-withdrawal-phone" v-model.trim="form.person_phone" maxlength="50" /></div><div class="col-md-6"><label class="form-label" for="lcd-withdrawal-relationship">Relación</label><BFormSelect id="lcd-withdrawal-relationship" v-model="form.person_relationship" required><option v-for="option in relationships" :key="option.value" :value="option.value">{{ option.label }}</option></BFormSelect></div><div class="col-md-6"><label class="form-label" for="lcd-withdrawal-reason">Motivo</label><BFormSelect id="lcd-withdrawal-reason" v-model="form.reason" required><option v-for="option in reasons" :key="option.value" :value="option.value">{{ option.label }}</option></BFormSelect></div><div class="col-12"><label class="form-label" for="lcd-withdrawal-observations">Observaciones</label><BFormTextarea id="lcd-withdrawal-observations" v-model.trim="form.observations" rows="3" maxlength="4000" /></div><div class="col-12 d-flex justify-content-end gap-2"><BButton type="button" variant="outline-secondary" @click="showForm = false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm"></span> Registrar salida</BButton></div></form>
    </BModal>
  </section>
</template>

<style scoped>
.lcd-withdrawals{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-withdrawals>header{display:flex;align-items:flex-end;justify-content:space-between;gap:.8rem}.lcd-withdrawals>header span{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.07em}.lcd-withdrawals h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-withdrawals header p{margin:0;color:var(--lcd-muted,#748093);font-size:.8rem}.lcd-withdrawals header .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.75rem}.lcd-withdrawals__filters{display:grid;grid-template-columns:220px 180px 180px;gap:.55rem;padding:.55rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-md,10px);background:var(--lcd-surface-muted,#f6f8fb)}.lcd-withdrawals__filters :deep(.form-control),.lcd-withdrawals__filters :deep(.form-select){min-height:38px;border-color:var(--lcd-border,#dfe5ec);font-size:.78rem}
.lcd-withdrawals :deep(.btn-sm){min-height:36px}.lcd-withdrawals :deep(.btn:not(.btn-sm)){min-height:40px}.lcd-withdrawals :deep(.form-control),.lcd-withdrawals :deep(.form-select){min-height:40px;font-size:.8rem}.lcd-withdrawals :deep(.form-control-sm),.lcd-withdrawals :deep(.form-select-sm){min-height:36px}
.lcd-withdrawals__table{max-height:600px;border:1px solid var(--lcd-border,#e0e6ed);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-withdrawals__table table{font-size:.78rem}.lcd-withdrawals__table th{position:sticky;z-index:1;top:0;padding:.64rem .7rem;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#647184);font-size:.69rem;letter-spacing:.04em;text-transform:uppercase}.lcd-withdrawals__table td{padding:.64rem .7rem;border-color:var(--lcd-border,#edf0f4)}.lcd-withdrawals__table td strong,.lcd-withdrawals__table td small{display:block}.lcd-withdrawals__table td small{margin-top:.14rem;color:var(--lcd-muted,#7d8998);font-size:.72rem}.lcd-withdrawals__actions{display:flex;justify-content:flex-end}.lcd-withdrawals__actions :deep(.btn){display:inline-flex;align-items:center;gap:.28rem;min-height:36px;border-radius:6px;font-size:.72rem}.form-label{color:var(--lcd-ink-soft,#536174);font-size:.75rem;font-weight:700}
@media(max-width:760px){.lcd-withdrawals>header{align-items:flex-start;flex-direction:column}.lcd-withdrawals__filters{grid-template-columns:1fr}}
</style>
