<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
  errorMessage,
  formatDate,
  formatDateTime,
  payloadItems,
  payloadMeta,
  showError,
  showSuccess,
  studentLabel,
} from "../module-utils";

const props = defineProps({
  bookId: { type: [Number, String], required: true },
  roster: { type: Array, default: () => [] },
  sessions: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const localDateTime = () => {
  const date = new Date();
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
const loading = ref(false); const error = ref(null); const items = ref([]); const meta = ref({}); const showForm = ref(false); const saving = ref(false);
const filters = reactive({ date_from: "", date_to: "", student_profile_id: null });
const form = reactive({ student_profile_id: null, arrival_at: localDateTime(), class_session_id: null, justification: "", source: "manual" });
let controller = null;
const students = computed(() => props.roster.map((row) => row.student || row).filter((student) => student?.id));
const arrivalDate = computed(() => form.arrival_at?.slice(0, 10) || "");
const availableSessions = computed(() => props.sessions.filter((session) => String(session.scheduled_date || session.session_date || session.date || "").slice(0, 10) === arrivalDate.value));

const load = async () => {
  if (!props.canManage) return;
  controller?.abort(); controller = new AbortController(); loading.value = true; error.value = null;
  try {
    const response = await libroDigitalApi.parvulariaLateArrivals(props.bookId, { ...filters, per_page: 100 }, controller.signal);
    items.value = payloadItems(response); meta.value = payloadMeta(response);
  } catch (requestError) { if (requestError?.code !== "ERR_CANCELED") error.value = requestError; }
  finally { loading.value = false; }
};
watch([() => props.bookId, () => filters.date_from, () => filters.date_to, () => filters.student_profile_id, () => props.canManage], load, { immediate: true });
watch(() => form.class_session_id, (id) => { if (id && form.source === "manual") form.source = "session_attendance"; });
watch(arrivalDate, () => { if (!availableSessions.value.some((session) => Number(session.id) === Number(form.class_session_id))) form.class_session_id = null; });
onBeforeUnmount(() => controller?.abort());
const resetForm = () => Object.assign(form, { student_profile_id: null, arrival_at: localDateTime(), class_session_id: null, justification: "", source: "manual" });
const save = async () => {
  saving.value = true;
  try {
    await libroDigitalApi.createParvulariaLateArrival(props.bookId, {
      student_profile_id: form.student_profile_id,
      arrival_at: new Date(form.arrival_at).toISOString(),
      class_session_id: form.class_session_id || null,
      justification: form.justification.trim() || null,
      source: form.source,
    });
    showForm.value = false; await load();
    await showSuccess("Atraso registrado", "El registro quedó sin clasificación horaria regulatoria no verificada.");
  } catch (requestError) { await showError(requestError, "No se pudo registrar el atraso"); }
  finally { saving.value = false; }
};
</script>

<template>
  <section class="lcd-late" aria-labelledby="lcd-late-title" :aria-busy="loading || saving">
    <header><div><span>EDUCACIÓN PARVULARIA</span><h3 id="lcd-late-title">Atrasos y hora de llegada</h3><p>Registro factual de llegada vinculado a nómina y, cuando corresponde, a una sesión del mismo día.</p></div><BButton v-if="canManage" type="button" size="sm" variant="primary" @click="resetForm(); showForm = true"><i class="bx bx-time-five"></i> Registrar atraso</BButton></header>
    <BAlert show variant="warning" class="small mb-0"><strong>Regla deshabilitada:</strong> {{ meta.policy_notice || 'La clasificación horaria regulatoria permanece deshabilitada hasta contar con una regla oficial verificada.' }} La interfaz no etiqueta atrasos como leves, graves, justificados o equivalentes.</BAlert>
    <BAlert v-if="!canManage" show variant="secondary" class="small mb-0">La consulta y el registro permanecen bloqueados porque falta la capability de gestión parvularia.</BAlert>
    <template v-else>
      <div class="lcd-late__filters"><BFormInput v-model="filters.date_from" type="date" size="sm" aria-label="Atrasos desde" /><BFormInput v-model="filters.date_to" type="date" size="sm" aria-label="Atrasos hasta" /><BFormSelect v-model="filters.student_profile_id" size="sm" aria-label="Filtrar atrasos por estudiante"><option :value="null">Todas las estudiantes</option><option v-for="student in students" :key="student.id" :value="student.id">{{ studentLabel(student) }}</option></BFormSelect></div>
      <LibroDigitalStatePanel v-if="loading && !items.length" state="loading" compact title="Cargando atrasos" message="Verificando disponibilidad y perfil regulatorio parvulario." />
      <LibroDigitalStatePanel v-else-if="error && !items.length" state="error" compact title="Atrasos no disponibles" :message="errorMessage(error)" @retry="load" />
      <div v-else-if="items.length" class="table-responsive lcd-late__table"><table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Horas de llegada factuales registradas para estudiantes de educación parvularia</caption><thead><tr><th>Fecha y hora</th><th>Estudiante</th><th>Minutos</th><th>Origen</th><th>Periodo</th><th>Estado</th></tr></thead><tbody><tr v-for="item in items" :key="item.id"><td><strong>{{ formatDateTime(item.arrival_at) }}</strong><small>{{ formatDate(item.arrival_at) }}</small></td><td><strong>{{ item.student_name }}</strong><small>{{ item.course }}</small></td><td>{{ item.minutes_late ?? 'No calculado' }}</td><td>{{ item.source }}</td><td>{{ item.period?.name || 'Sin periodo asociado' }}</td><td><LibroDigitalStatusBadge :status="item.status" /></td></tr></tbody></table></div>
      <LibroDigitalStatePanel v-else compact title="Sin atrasos" message="No hay registros factuales con los filtros seleccionados." />
    </template>

    <BModal v-model="showForm" title="Registrar atraso parvulario" size="lg" hide-footer>
      <BAlert show variant="warning" class="small">Se registrará la hora observada. El servidor no aplicará una clasificación regulatoria mientras no exista una regla oficial verificada. La justificación se conserva cifrada y no se vuelve a mostrar en el listado.</BAlert>
      <form class="row g-3" @submit.prevent="save"><div class="col-md-7"><label class="form-label" for="lcd-late-student">Estudiante</label><BFormSelect id="lcd-late-student" v-model="form.student_profile_id" required><option :value="null">Seleccionar de nómina vigente</option><option v-for="student in students" :key="student.id" :value="student.id">{{ studentLabel(student) }}</option></BFormSelect></div><div class="col-md-5"><label class="form-label" for="lcd-late-at">Hora de llegada</label><BFormInput id="lcd-late-at" v-model="form.arrival_at" type="datetime-local" required /></div><div class="col-md-7"><label class="form-label" for="lcd-late-session">Sesión del mismo día</label><BFormSelect id="lcd-late-session" v-model="form.class_session_id"><option :value="null">Sin sesión asociada</option><option v-for="session in availableSessions" :key="session.id" :value="session.id">{{ session.start_time?.slice(0,5) || 'Sin hora' }} · {{ session.status }}</option></BFormSelect><small v-if="!availableSessions.length">No hay sesiones del libro en la fecha seleccionada.</small></div><div class="col-md-5"><label class="form-label" for="lcd-late-source">Origen del registro</label><BFormSelect id="lcd-late-source" v-model="form.source" required><option value="manual">Ingreso manual</option><option value="session_attendance">Asistencia de sesión</option><option value="porter">Portería</option></BFormSelect></div><div class="col-12"><label class="form-label" for="lcd-late-justification">Antecedente o justificación informada</label><BFormTextarea id="lcd-late-justification" v-model.trim="form.justification" rows="3" maxlength="4000" /></div><div class="col-12 d-flex justify-content-end gap-2"><BButton type="button" variant="outline-secondary" @click="showForm = false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving"><span v-if="saving" class="spinner-border spinner-border-sm"></span> Registrar hora</BButton></div></form>
    </BModal>
  </section>
</template>

<style scoped>
.lcd-late{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-late>header{display:flex;align-items:flex-end;justify-content:space-between;gap:.8rem}.lcd-late>header span{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.07em}.lcd-late h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-late header p{margin:0;color:var(--lcd-muted,#748093);font-size:.8rem}.lcd-late header .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.75rem}.lcd-late__filters{display:grid;grid-template-columns:180px 180px minmax(240px,1fr);gap:.55rem;padding:.55rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-md,10px);background:var(--lcd-surface-muted,#f6f8fb)}.lcd-late__filters :deep(.form-control),.lcd-late__filters :deep(.form-select){min-height:38px;border-color:var(--lcd-border,#dfe5ec);font-size:.78rem}
.lcd-late :deep(.btn-sm){min-height:36px}.lcd-late :deep(.btn:not(.btn-sm)){min-height:40px}.lcd-late :deep(.form-control),.lcd-late :deep(.form-select){min-height:40px;font-size:.8rem}.lcd-late :deep(.form-control-sm),.lcd-late :deep(.form-select-sm){min-height:36px}
.lcd-late__table{max-height:600px;border:1px solid var(--lcd-border,#e0e6ed);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-late__table table{font-size:.78rem}.lcd-late__table th{position:sticky;z-index:1;top:0;padding:.64rem .7rem;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#647184);font-size:.69rem;letter-spacing:.04em;text-transform:uppercase}.lcd-late__table td{padding:.64rem .7rem;border-color:var(--lcd-border,#edf0f4)}.lcd-late__table td strong,.lcd-late__table td small{display:block}.lcd-late__table td small{margin-top:.14rem;color:var(--lcd-muted,#7d8998);font-size:.72rem}.form-label{color:var(--lcd-ink-soft,#536174);font-size:.75rem;font-weight:700}.form-label+*+small{display:block;margin-top:.28rem;color:var(--lcd-muted,#748093);font-size:.72rem}.lcd-late :deep(.alert-warning){border-left:4px solid var(--lcd-warning,#f7b84b)}
@media(max-width:760px){.lcd-late>header{align-items:flex-start;flex-direction:column}.lcd-late__filters{grid-template-columns:1fr}}
</style>
