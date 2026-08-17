<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import Swal from "sweetalert2";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
  confirmAction,
  errorMessage,
  formatDateTime,
  humanize,
  payloadItems,
  showError,
  showSuccess,
} from "../module-utils";

const props = defineProps({
  bookId: { type: [Number, String], required: true },
  canRequest: { type: Boolean, default: false },
  canReview: { type: Boolean, default: false },
  canApply: { type: Boolean, default: false },
});

const fieldDefinitions = {
  session: {
    label: "Sesión",
    fields: {
      objective_summary: { label: "Resumen de objetivo", type: "textarea", nullable: true },
      content_summary: { label: "Resumen de contenido", type: "textarea", nullable: true },
      activity_summary: { label: "Resumen de actividad", type: "textarea", nullable: true },
      observation: { label: "Observación", type: "textarea", nullable: true },
    },
  },
  session_attendance: {
    label: "Asistencia individual",
    fields: {
      status: { label: "Estado", options: ["present", "absent", "late", "left_early", "not_applicable"] },
      arrival_at: { label: "Hora de llegada", type: "datetime-local", nullable: true },
      departure_at: { label: "Hora de salida", type: "datetime-local", nullable: true },
      justification_status: { label: "Estado de justificación", options: ["not_required", "pending", "accepted", "rejected"] },
      notes: { label: "Notas", type: "textarea", nullable: true },
    },
  },
  assessment: {
    label: "Evaluación",
    fields: {
      name: { label: "Nombre" }, description: { label: "Descripción", type: "textarea", nullable: true },
      assessment_type: { label: "Tipo de evaluación" }, assessment_date: { label: "Fecha", type: "date" },
      weight: { label: "Ponderación", type: "number", nullable: true, min: 0, max: 100 },
      maximum_score: { label: "Puntaje máximo", type: "number", nullable: true, min: 0.01, step: "any" },
    },
  },
  student_result: {
    label: "Resultado individual",
    fields: {
      status: { label: "Estado", options: ["pending", "recorded", "absent", "exempt"] },
      raw_score: { label: "Puntaje bruto", type: "number", nullable: true, min: 0, step: "any" },
      numeric_value: { label: "Valor numérico", type: "number", nullable: true, step: "any" },
      qualitative_value: { label: "Valor cualitativo", nullable: true },
      absent: { label: "Ausente", type: "boolean" }, exempt: { label: "Eximido", type: "boolean" },
      observation: { label: "Observación", type: "textarea", nullable: true },
    },
  },
  coexistence_entry: {
    label: "Convivencia",
    fields: {
      category_code: { label: "Código de categoría" }, happened_at: { label: "Fecha y hora", type: "datetime-local" },
      description_encrypted: { label: "Descripción reservada", type: "textarea", nullable: true, sensitive: true },
      immediate_action_encrypted: { label: "Acción inmediata reservada", type: "textarea", nullable: true, sensitive: true },
      confidentiality_level: { label: "Confidencialidad", options: ["general", "reserved", "restricted", "high_confidentiality"] },
    },
  },
  pie_support_record: {
    label: "Registro PIE",
    fields: {
      record_type: { label: "Tipo de registro" }, recorded_at: { label: "Fecha y hora", type: "datetime-local" },
      objective_encrypted: { label: "Objetivo reservado", type: "textarea", nullable: true, sensitive: true },
      details_encrypted: { label: "Detalle reservado", type: "textarea", nullable: true, sensitive: true },
      agreements_encrypted: { label: "Acuerdos reservados", type: "textarea", nullable: true, sensitive: true },
      confidentiality_level: { label: "Confidencialidad", options: ["general", "reserved", "restricted", "high_confidentiality"] },
    },
  },
  absence_case: {
    label: "Caso de ausencia",
    fields: {
      risk_level: { label: "Nivel de riesgo", options: ["low", "medium", "high", "critical"] },
      next_deadline_on: { label: "Próximo plazo", type: "date", nullable: true },
      normative_basis: { label: "Fundamento normativo", type: "textarea", nullable: true },
    },
  },
  parvularia_plan: {
    label: "Planificación parvularia",
    fields: {
      plan_type: { label: "Tipo de plan" }, horizon: { label: "Horizonte" }, title: { label: "Título" },
      learning_experience: { label: "Experiencia de aprendizaje", type: "textarea", nullable: true },
      pedagogical_strategies: { label: "Estrategias pedagógicas", type: "textarea", nullable: true },
      starts_on: { label: "Fecha de inicio", type: "date" }, ends_on: { label: "Fecha de término", type: "date", nullable: true },
    },
  },
  parvularia_evaluation: {
    label: "Evaluación parvularia",
    fields: {
      evaluation_type: { label: "Tipo de evaluación" }, observed_at: { label: "Fecha de observación", type: "datetime-local" },
      achievement_level: { label: "Nivel de logro", nullable: true },
      observation_encrypted: { label: "Observación reservada", type: "textarea", nullable: true, sensitive: true },
      analysis_encrypted: { label: "Análisis reservado", type: "textarea", nullable: true, sensitive: true },
      feedback_encrypted: { label: "Retroalimentación reservada", type: "textarea", nullable: true, sensitive: true },
      pedagogical_decision_encrypted: { label: "Decisión pedagógica reservada", type: "textarea", nullable: true, sensitive: true },
    },
  },
};

const loading = ref(false);
const error = ref(null);
const items = ref([]);
const saving = ref(false);
const showForm = ref(false);
const filterStatus = ref("");
const form = reactive({ entity_type: "session", entity_id: "", original_revision: 1, field: "objective_summary", value: "", reason: "" });
let controller = null;

const currentDefinition = computed(() => fieldDefinitions[form.entity_type]);
const currentFields = computed(() => currentDefinition.value?.fields || {});
const currentField = computed(() => currentFields.value[form.field] || {});
const maySeePanel = computed(() => props.canRequest || props.canReview || props.canApply);

const resetForm = () => Object.assign(form, { entity_type: "session", entity_id: "", original_revision: 1, field: "objective_summary", value: "", reason: "" });
const load = async () => {
  if (!maySeePanel.value) return;
  controller?.abort(); controller = new AbortController(); loading.value = true; error.value = null;
  try {
    items.value = payloadItems(await libroDigitalApi.amendments({ book_id: props.bookId, status: filterStatus.value || undefined, per_page: 100 }, controller.signal));
  } catch (requestError) {
    if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
  } finally { loading.value = false; }
};
watch([() => props.bookId, filterStatus, maySeePanel], load, { immediate: true });
watch(() => form.entity_type, () => { form.field = Object.keys(currentFields.value)[0] || ""; form.value = ""; });
watch(() => form.field, () => { form.value = currentField.value.type === "boolean" ? false : ""; });
onBeforeUnmount(() => controller?.abort());

const normalizedValue = () => {
  const definition = currentField.value;
  if (definition.nullable && form.value === "") return null;
  if (definition.type === "number") return Number(form.value);
  if (definition.type === "boolean") return Boolean(form.value);
  if (definition.type === "datetime-local") return new Date(form.value).toISOString();
  return form.value;
};
const save = async () => {
  saving.value = true;
  try {
    await libroDigitalApi.createAmendment({
      book_id: Number(props.bookId), entity_type: form.entity_type, entity_id: form.entity_id,
      original_revision: Number(form.original_revision), section: currentDefinition.value.label, field: form.field,
      proposed: { [form.field]: normalizedValue() }, reason: form.reason.trim(),
    });
    showForm.value = false; await load(); await showSuccess("Enmienda solicitada", "La solicitud quedó pendiente de revisión por una persona distinta.");
  } catch (requestError) { await showError(requestError, "No se pudo solicitar la enmienda"); }
  finally { saving.value = false; }
};
const review = async (item, decision) => {
  const result = await Swal.fire({
    title: decision === "approve" ? "Aprobar enmienda" : "Rechazar enmienda",
    input: "textarea", inputLabel: "Fundamento de la decisión", inputAttributes: { maxlength: "3000" },
    showCancelButton: true, confirmButtonText: decision === "approve" ? "Aprobar" : "Rechazar",
    inputValidator: (value) => value?.trim().length >= 5 ? undefined : "Ingresa al menos 5 caracteres.",
  });
  if (!result.isConfirmed) return;
  try {
    if (decision === "approve") await libroDigitalApi.approveAmendment(item, { note: result.value.trim() });
    else await libroDigitalApi.rejectAmendment(item, { note: result.value.trim() });
    await load(); await showSuccess(decision === "approve" ? "Enmienda aprobada" : "Enmienda rechazada");
  } catch (requestError) { await showError(requestError); }
};
const apply = async (item) => {
  const confirmation = await confirmAction({ title: "Aplicar enmienda aprobada", text: "Se creará una nueva revisión del registro fuente y los derivados de cumplimiento podrán quedar obsoletos.", confirmText: "Aplicar revisión" });
  if (!confirmation.isConfirmed) return;
  try { await libroDigitalApi.applyAmendment(item); await load(); await showSuccess("Enmienda aplicada", "Se generó una nueva revisión auditable."); }
  catch (requestError) { await showError(requestError); }
};
const proposalLabel = (item) => {
  const value = item.proposed?.[item.field] ?? Object.values(item.proposed || {})[0];
  const definition = fieldDefinitions[item.entity_type]?.fields?.[item.field];
  if (definition?.sensitive) return "Contenido reservado";
  if (typeof value === "boolean") return value ? "Sí" : "No";
  if (value === null) return "Vaciar campo";
  return String(value ?? "—");
};
</script>

<template>
  <section class="lcd-amendments" aria-labelledby="lcd-amendments-title" :aria-busy="loading || saving">
    <header><div><span>CORRECCIÓN FORMAL</span><h3 id="lcd-amendments-title">Enmiendas y revisiones</h3><p>Solicitante, revisor y aplicador deben ser personas distintas. El servidor valida pertenencia, revisión original y campos permitidos.</p></div><BButton v-if="canRequest" type="button" size="sm" variant="primary" @click="resetForm(); showForm = true"><i class="bx bx-git-pull-request"></i> Solicitar enmienda</BButton></header>
    <BAlert v-if="!maySeePanel" show variant="secondary" class="small mb-0">No cuentas con una capability de enmiendas. La información y las acciones permanecen ocultas.</BAlert>
    <template v-else>
      <BAlert show variant="info" class="small mb-0"><strong>Segregación obligatoria:</strong> la API impide revisar una solicitud propia y aplicar una solicitud creada o revisada por la misma persona.</BAlert>
      <div class="lcd-amendments__filters"><label for="lcd-amendment-status">Estado</label><BFormSelect id="lcd-amendment-status" v-model="filterStatus" size="sm"><option value="">Todos</option><option value="requested">Solicitada</option><option value="under_review">En revisión</option><option value="approved">Aprobada</option><option value="rejected">Rechazada</option><option value="applied">Aplicada</option></BFormSelect></div>
      <LibroDigitalStatePanel v-if="loading && !items.length" state="loading" compact title="Cargando enmiendas" message="Consultando solicitudes autorizadas para este libro." />
      <LibroDigitalStatePanel v-else-if="error && !items.length" state="error" compact title="No se pudieron cargar las enmiendas" :message="errorMessage(error)" @retry="load" />
      <div v-else-if="items.length" class="table-responsive lcd-amendments__table">
        <table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Solicitudes de enmienda, revisión y aplicación del libro activo</caption>
          <thead><tr><th>Solicitud</th><th>Registro</th><th>Propuesta</th><th>Solicitante</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
          <tbody><tr v-for="item in items" :key="item.id">
            <td><strong>{{ item.public_id || `#${item.id}` }}</strong><small>{{ formatDateTime(item.requested_at) }}</small></td>
            <td><strong>{{ fieldDefinitions[item.entity_type]?.label || humanize(item.entity_type) }} #{{ item.entity_id }}</strong><small>Revisión original {{ item.original_revision }} · {{ fieldDefinitions[item.entity_type]?.fields?.[item.field]?.label || humanize(item.field) }}</small></td>
            <td><span class="lcd-amendments__proposal">{{ proposalLabel(item) }}</span><small>{{ item.reason }}</small></td>
            <td>{{ item.requester_name || 'Usuario autorizado' }}</td><td><LibroDigitalStatusBadge :status="item.status" /></td>
            <td><div class="lcd-amendments__actions"><BButton v-if="canReview && ['requested','under_review'].includes(item.status)" type="button" size="sm" variant="outline-success" @click="review(item, 'approve')">Aprobar</BButton><BButton v-if="canReview && ['requested','under_review'].includes(item.status)" type="button" size="sm" variant="outline-danger" @click="review(item, 'reject')">Rechazar</BButton><BButton v-if="canApply && item.status === 'approved'" type="button" size="sm" variant="primary" @click="apply(item)">Aplicar</BButton></div></td>
          </tr></tbody>
        </table>
      </div>
      <LibroDigitalStatePanel v-else compact title="Sin enmiendas" message="No hay solicitudes visibles con los filtros seleccionados." />
    </template>

    <BModal v-model="showForm" title="Solicitar enmienda" size="lg" hide-footer>
      <BAlert show variant="warning" class="small">Usa el identificador y la revisión mostrados por el registro fuente. No se admiten correcciones sobre cierres derivados: al enmendar la fuente, el backend versiona o reabre los derivados correspondientes.</BAlert>
      <form class="row g-3" @submit.prevent="save"><div class="col-md-5"><label class="form-label" for="lcd-amendment-type">Tipo de registro</label><BFormSelect id="lcd-amendment-type" v-model="form.entity_type"><option v-for="(definition, key) in fieldDefinitions" :key="key" :value="key">{{ definition.label }}</option></BFormSelect></div><div class="col-md-4"><label class="form-label" for="lcd-amendment-entity">ID del registro</label><BFormInput id="lcd-amendment-entity" v-model.trim="form.entity_id" required maxlength="40" /></div><div class="col-md-3"><label class="form-label" for="lcd-amendment-revision">Revisión original</label><BFormInput id="lcd-amendment-revision" v-model.number="form.original_revision" type="number" min="1" required /></div><div class="col-md-5"><label class="form-label" for="lcd-amendment-field">Campo permitido</label><BFormSelect id="lcd-amendment-field" v-model="form.field"><option v-for="(definition, key) in currentFields" :key="key" :value="key">{{ definition.label }}</option></BFormSelect></div><div class="col-md-7"><label class="form-label" for="lcd-amendment-value">Nuevo valor</label><BFormSelect v-if="currentField.options" id="lcd-amendment-value" v-model="form.value" required><option value="" disabled>Seleccionar</option><option v-for="option in currentField.options" :key="option" :value="option">{{ humanize(option) }}</option></BFormSelect><BFormSelect v-else-if="currentField.type === 'boolean'" id="lcd-amendment-value" v-model="form.value"><option :value="true">Sí</option><option :value="false">No</option></BFormSelect><BFormTextarea v-else-if="currentField.type === 'textarea'" id="lcd-amendment-value" v-model="form.value" rows="3" :required="!currentField.nullable" /><BFormInput v-else id="lcd-amendment-value" v-model="form.value" :type="currentField.type || 'text'" :min="currentField.min" :max="currentField.max" :step="currentField.step" :required="!currentField.nullable" /></div><div v-if="currentField.sensitive" class="col-12"><small class="text-muted"><i class="bx bx-lock-alt"></i> El backend cifra este valor. La tabla no vuelve a exponer su contenido.</small></div><div class="col-12"><label class="form-label" for="lcd-amendment-reason">Motivo y fundamento</label><BFormTextarea id="lcd-amendment-reason" v-model.trim="form.reason" rows="3" minlength="10" maxlength="3000" required /></div><div class="col-12 d-flex justify-content-end gap-2"><BButton type="button" variant="outline-secondary" @click="showForm = false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving || form.reason.length < 10"><span v-if="saving" class="spinner-border spinner-border-sm"></span> Enviar solicitud</BButton></div></form>
    </BModal>
  </section>
</template>

<style scoped>
.lcd-amendments{display:grid;gap:.9rem;min-width:0;color:var(--lcd-ink,#273244)}.lcd-amendments>header{display:flex;align-items:flex-end;justify-content:space-between;gap:.8rem}.lcd-amendments>header span{color:var(--lcd-brand-700,#405189);font-size:.7rem;font-weight:800;letter-spacing:.07em}.lcd-amendments h3{margin:.12rem 0;color:var(--lcd-ink,#293547);font-size:1.02rem}.lcd-amendments header p{margin:0;max-width:780px;color:var(--lcd-muted,#748093);font-size:.8rem}.lcd-amendments header .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:38px;border-radius:var(--lcd-radius-sm,7px);font-size:.75rem}.lcd-amendments__filters{display:grid;grid-template-columns:90px 230px;align-items:center;gap:.6rem;padding:.55rem .7rem;border:1px solid var(--lcd-border,#dfe5ec);border-radius:var(--lcd-radius-md,10px);background:var(--lcd-surface-muted,#f6f8fb)}.lcd-amendments__filters label,.form-label{color:var(--lcd-ink-soft,#536174);font-size:.74rem;font-weight:700}.lcd-amendments__filters :deep(.form-select){min-height:38px;border-color:var(--lcd-border,#dfe5ec);font-size:.78rem}
.lcd-amendments :deep(.btn-sm){min-height:36px}.lcd-amendments :deep(.btn:not(.btn-sm)){min-height:40px}.lcd-amendments :deep(.form-control),.lcd-amendments :deep(.form-select){min-height:40px;font-size:.8rem}.lcd-amendments :deep(.form-control-sm),.lcd-amendments :deep(.form-select-sm){min-height:36px}
.lcd-amendments__table{max-height:600px;border:1px solid var(--lcd-border,#e0e6ed);border-radius:var(--lcd-radius-lg,12px);background:var(--lcd-surface,#fff);box-shadow:var(--lcd-shadow-sm,0 2px 10px rgba(37,47,63,.04))}.lcd-amendments__table table{font-size:.76rem}.lcd-amendments__table th{position:sticky;z-index:1;top:0;padding:.64rem .7rem;background:var(--lcd-surface-muted,#f5f7fa);color:var(--lcd-muted,#647184);font-size:.69rem;letter-spacing:.04em;text-transform:uppercase}.lcd-amendments__table td{padding:.64rem .7rem;border-color:var(--lcd-border,#edf0f4)}.lcd-amendments__table td strong,.lcd-amendments__table td small{display:block}.lcd-amendments__table td small{max-width:320px;margin-top:.15rem;color:var(--lcd-muted,#7d8998);font-size:.7rem;line-height:1.4}.lcd-amendments__proposal{display:block;max-width:260px;overflow:hidden;color:var(--lcd-ink,#2d394b);font-weight:700;text-overflow:ellipsis;white-space:nowrap}.lcd-amendments__actions{display:flex;justify-content:flex-end;gap:.3rem;white-space:nowrap}.lcd-amendments__actions :deep(.btn){min-height:36px;border-radius:6px;font-size:.72rem}
@media(max-width:700px){.lcd-amendments>header{align-items:flex-start;flex-direction:column}.lcd-amendments__filters{grid-template-columns:1fr}}
</style>
