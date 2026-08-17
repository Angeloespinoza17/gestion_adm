<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
  confirmAction,
  errorMessage,
  formatDateTime,
  payloadData,
  showError,
  showSuccess,
} from "../module-utils";

const props = defineProps({
  book: { type: Object, required: true },
  roster: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  canReconcile: { type: Boolean, default: false },
});

const today = () => new Date().toISOString().slice(0, 10);
const loading = ref(false);
const saving = ref("");
const error = ref(null);
const dailySummary = ref(null);
const monthlySummary = ref(null);
const dailyClosure = ref(null);
const monthlyClosure = ref(null);
const reconciliation = ref(null);
const filters = reactive({ date: today(), month: new Date().getMonth() + 1 });
const reconcileForm = reactive({ evidence_reference: "", expected_total: 0, present_total: 0, absent_total: 0 });
let controller = null;

const teachingGroupPublicId = computed(() => props.book.teaching_group_public_id || props.book.teaching_group?.public_id || null);
const studentReferences = computed(() => props.roster
  .map((row) => row.enrollment_link_public_id || row.enrollment_link?.public_id || row.student_reference)
  .filter(Boolean));
const reconciliationReady = computed(() => Boolean(
  teachingGroupPublicId.value
  && props.roster.length
  && studentReferences.value.length === props.roster.length,
));
const summaryTotals = computed(() => monthlySummary.value?.totals || {});

const load = async () => {
  controller?.abort();
  controller = new AbortController();
  loading.value = true;
  error.value = null;
  try {
    const [day, month] = await Promise.all([
      libroDigitalApi.dailyAttendance(props.book.id, { date: filters.date }, controller.signal),
      libroDigitalApi.monthlyAttendance(props.book.id, {
        month: filters.month,
        year: props.book.academic_year?.year || new Date().getFullYear(),
      }, controller.signal),
    ]);
    dailySummary.value = payloadData(day);
    monthlySummary.value = payloadData(month);
  } catch (requestError) {
    if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
  } finally {
    loading.value = false;
  }
};

watch(() => props.book.id, load, { immediate: true });
watch(() => filters.date, load);
watch(() => filters.month, load);
onBeforeUnmount(() => controller?.abort());

const closeDay = async () => {
  const confirmation = await confirmAction({
    title: "Cerrar asistencia diaria",
    text: `Se generará un snapshot versionado para ${filters.date}. El servidor comprobará sesiones, firmas y cuadratura.`,
    confirmText: "Cerrar día",
  });
  if (!confirmation.isConfirmed) return;
  saving.value = "day";
  try {
    dailyClosure.value = payloadData(await libroDigitalApi.closeDailyAttendance(props.book, {
      date: filters.date,
      teaching_group_id: props.book.teaching_group_id || undefined,
    }));
    await load();
    await showSuccess("Cierre diario generado", "El snapshot y su hash quedaron registrados para auditoría.");
  } catch (requestError) {
    await showError(requestError, "No se pudo cerrar el día");
  } finally {
    saving.value = "";
  }
};

const closeMonth = async () => {
  const confirmation = await confirmAction({
    title: "Cerrar asistencia mensual",
    text: "El backend exigirá todos los cierres diarios aplicables antes de sellar el mes.",
    confirmText: "Cerrar mes",
  });
  if (!confirmation.isConfirmed) return;
  saving.value = "month";
  try {
    monthlyClosure.value = payloadData(await libroDigitalApi.closeMonthlyAttendance(props.book, {
      month: Number(filters.month),
      teaching_group_id: props.book.teaching_group_id || undefined,
    }));
    await load();
    await showSuccess("Cierre mensual generado", "Los totales quedaron sellados en una revisión auditable.");
  } catch (requestError) {
    await showError(requestError, "No se pudo cerrar el mes");
  } finally {
    saving.value = "";
  }
};

const reconcile = async () => {
  if (!reconciliationReady.value) return;
  const confirmation = await confirmAction({
    title: "Conciliar evidencia manual",
    text: "Esta operación compara evidencia externa local. No realiza ni acredita un envío oficial a SIGE.",
    confirmText: "Registrar conciliación",
  });
  if (!confirmation.isConfirmed) return;
  saving.value = "reconcile";
  try {
    reconciliation.value = payloadData(await libroDigitalApi.reconcileAttendance(props.book, {
      month: Number(filters.month),
      evidence_reference: reconcileForm.evidence_reference.trim(),
      rows: studentReferences.value.map((student_reference) => ({ student_reference })),
      groups: [{
        teaching_group_public_id: teachingGroupPublicId.value,
        expected_total: Number(reconcileForm.expected_total),
        present_total: Number(reconcileForm.present_total),
        absent_total: Number(reconcileForm.absent_total),
      }],
    }));
    await showSuccess("Conciliación registrada", "La evidencia quedó marcada como manual y no oficial.");
  } catch (requestError) {
    await showError(requestError, "No se pudo conciliar la evidencia");
  } finally {
    saving.value = "";
  }
};
</script>

<template>
  <section class="lcd-closures" aria-labelledby="lcd-closures-title" :aria-busy="loading || Boolean(saving)">
    <header>
      <div><span>CIERRE Y CUADRATURA</span><h3 id="lcd-closures-title">Consolidación de asistencia</h3><p>Cierres derivados, versionados y validados por el servidor. Las correcciones posteriores se tramitan como enmiendas.</p></div>
    </header>
    <BAlert v-if="!canManage" show variant="info" class="small mb-0">Vista de consulta. Las acciones de cierre permanecen deshabilitadas por permisos.</BAlert>
    <LibroDigitalStatePanel v-if="loading && !dailySummary" state="loading" compact title="Calculando asistencia" message="Consultando totales diarios y mensuales del libro." />
    <LibroDigitalStatePanel v-else-if="error && !dailySummary" state="error" compact title="No se pudieron consultar los totales" :message="errorMessage(error)" @retry="load" />
    <template v-else>
      <div class="lcd-closures__grid">
        <article>
          <div class="lcd-closures__article-head"><div><small>CIERRE DIARIO</small><h4>Jornada seleccionada</h4></div><BFormInput v-model="filters.date" type="date" size="sm" aria-label="Fecha del cierre diario" /></div>
          <div class="lcd-closures__metrics"><div><span>Registrados</span><strong>{{ dailySummary?.totals?.recorded ?? 0 }}</strong></div><div><span>Presentes</span><strong>{{ dailySummary?.totals?.present ?? 0 }}</strong></div><div><span>Ausentes</span><strong>{{ dailySummary?.totals?.absent ?? 0 }}</strong></div><div><span>Tasa</span><strong>{{ dailySummary?.totals?.attendance_rate ?? 0 }}%</strong></div></div>
          <div v-if="dailyClosure" class="lcd-closures__receipt"><LibroDigitalStatusBadge :status="dailyClosure.status" /><span>Rev. {{ dailyClosure.revision }}</span><code>{{ dailyClosure.snapshot_hash?.slice(0, 18) }}…</code></div>
          <BButton type="button" size="sm" variant="primary" :disabled="!canManage || saving" @click="closeDay"><span v-if="saving === 'day'" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-lock-alt"></i> Cerrar día</BButton>
        </article>
        <article>
          <div class="lcd-closures__article-head"><div><small>CIERRE MENSUAL</small><h4>Consolidado del mes</h4></div><BFormSelect v-model="filters.month" size="sm" aria-label="Mes del cierre mensual"><option v-for="month in 12" :key="month" :value="month">Mes {{ month }}</option></BFormSelect></div>
          <div class="lcd-closures__metrics"><div><span>Registros</span><strong>{{ summaryTotals.recorded ?? 0 }}</strong></div><div><span>Presentes</span><strong>{{ summaryTotals.present ?? 0 }}</strong></div><div><span>Ausentes</span><strong>{{ summaryTotals.absent ?? 0 }}</strong></div><div><span>Tasa</span><strong>{{ summaryTotals.attendance_rate ?? 0 }}%</strong></div></div>
          <div v-if="monthlyClosure" class="lcd-closures__receipt"><LibroDigitalStatusBadge :status="monthlyClosure.status" /><span>{{ monthlyClosure.school_days_count }} días</span><code>{{ monthlyClosure.snapshot_hash?.slice(0, 18) }}…</code></div>
          <BButton type="button" size="sm" variant="primary" :disabled="!canManage || saving" @click="closeMonth"><span v-if="saving === 'month'" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-calendar-check"></i> Cerrar mes</BButton>
        </article>
      </div>

      <article class="lcd-reconcile">
        <header><div><small>CONCILIACIÓN MANUAL</small><h4>Evidencia externa</h4></div><span class="badge bg-warning-subtle text-warning">No oficial</span></header>
        <BAlert show variant="warning" class="small">La conciliación solo registra una comparación local con respaldo identificable. No ejecuta un envío oficial ni acredita recepción por SIGE.</BAlert>
        <BAlert v-if="canReconcile && !reconciliationReady" show variant="secondary" class="small">Operación bloqueada: la API de contexto aún no entrega los identificadores públicos del grupo docente y de cada vínculo de matrícula requeridos por el contrato de conciliación.</BAlert>
        <form class="row g-2" @submit.prevent="reconcile">
          <div class="col-lg-5"><label class="form-label" for="lcd-reconcile-reference">Referencia de evidencia</label><BFormInput id="lcd-reconcile-reference" v-model.trim="reconcileForm.evidence_reference" minlength="8" maxlength="191" required placeholder="Folio, archivo o acta verificable" /></div>
          <div class="col"><label class="form-label" for="lcd-reconcile-expected">Esperados externos</label><BFormInput id="lcd-reconcile-expected" v-model.number="reconcileForm.expected_total" type="number" min="0" required /></div>
          <div class="col"><label class="form-label" for="lcd-reconcile-present">Presentes externos</label><BFormInput id="lcd-reconcile-present" v-model.number="reconcileForm.present_total" type="number" min="0" required /></div>
          <div class="col"><label class="form-label" for="lcd-reconcile-absent">Ausentes externos</label><BFormInput id="lcd-reconcile-absent" v-model.number="reconcileForm.absent_total" type="number" min="0" required /></div>
          <div class="col-12 d-flex justify-content-end"><BButton type="submit" size="sm" variant="outline-primary" :disabled="!canReconcile || !reconciliationReady || saving || reconcileForm.evidence_reference.trim().length < 8"><span v-if="saving === 'reconcile'" class="spinner-border spinner-border-sm"></span> Registrar conciliación</BButton></div>
        </form>
        <div v-if="reconciliation" class="lcd-reconcile__result"><LibroDigitalStatusBadge :status="reconciliation.status" /><span>Creada {{ formatDateTime(reconciliation.created_at) }}</span><strong>{{ reconciliation.official_submission_performed ? 'Envío informado por servidor' : 'Sin envío oficial' }}</strong></div>
      </article>
    </template>
  </section>
</template>

<style scoped>
.lcd-closures{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-closures>header span,.lcd-closures article small{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.06em}.lcd-closures h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-closures>header p{margin:0;color:var(--lcd-muted,#748093);font-size:.8rem}.lcd-closures__grid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}.lcd-closures article{display:grid;gap:.8rem;padding:.9rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-closures__article-head,.lcd-reconcile>header{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.lcd-closures h4{margin:.1rem 0;color:var(--lcd-ink,#2d394b);font-size:.9rem}.lcd-closures__article-head .form-control,.lcd-closures__article-head .form-select{width:165px;min-height:38px;border-color:var(--lcd-border,#dfe5ec);font-size:.76rem}
.lcd-closures :deep(.btn-sm){min-height:36px}.lcd-closures :deep(.btn:not(.btn-sm)){min-height:40px}.lcd-closures :deep(.form-control),.lcd-closures :deep(.form-select){min-height:40px;font-size:.8rem}.lcd-closures :deep(.form-control-sm),.lcd-closures :deep(.form-select-sm){min-height:36px}
.lcd-closures__metrics{display:grid;grid-template-columns:repeat(4,1fr);overflow:hidden;border:1px solid var(--lcd-border,#e5e9ef);border-radius:var(--lcd-radius-md,9px);background:var(--lcd-surface-muted,#f7f8fa)}.lcd-closures__metrics div{padding:.62rem;border-right:1px solid var(--lcd-border,#e5e9ef)}.lcd-closures__metrics div:last-child{border:0}.lcd-closures__metrics span,.lcd-closures__metrics strong{display:block}.lcd-closures__metrics span{color:var(--lcd-muted,#778496);font-size:.69rem}.lcd-closures__metrics strong{margin-top:.08rem;color:var(--lcd-ink,#293547);font-size:.95rem}.lcd-closures article>.btn{justify-self:end;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.75rem}.lcd-closures__receipt,.lcd-reconcile__result{display:flex;align-items:center;gap:.6rem;padding:.58rem .65rem;border-radius:var(--lcd-radius-sm,7px);background:var(--lcd-brand-50,#f5f7fc);color:var(--lcd-muted,#6c7889);font-size:.72rem}.lcd-closures__receipt code{margin-left:auto;color:var(--lcd-brand-700,#405189);font-size:.7rem}.lcd-reconcile{border-left:4px solid var(--lcd-warning,#f7b84b)!important}.lcd-reconcile form{align-items:end}.lcd-reconcile :deep(.form-control){min-height:40px;border-color:var(--lcd-border,#dfe5ec);font-size:.78rem}.form-label{color:var(--lcd-ink-soft,#536174);font-size:.74rem;font-weight:700}.lcd-reconcile__result strong{margin-left:auto;color:var(--lcd-ink,#2d394b)}
@media(max-width:900px){.lcd-closures__grid{grid-template-columns:1fr}}@media(max-width:560px){.lcd-closures__metrics{grid-template-columns:repeat(2,1fr)}.lcd-closures__metrics div:nth-child(2){border-right:0}.lcd-closures__metrics div:nth-child(n+3){border-top:1px solid var(--lcd-border,#e5e9ef)}.lcd-closures__article-head{align-items:stretch;flex-direction:column}.lcd-closures__article-head .form-control,.lcd-closures__article-head .form-select{width:100%}.lcd-closures__receipt,.lcd-reconcile__result{align-items:flex-start;flex-direction:column}.lcd-closures__receipt code,.lcd-reconcile__result strong{margin-left:0}}
</style>
