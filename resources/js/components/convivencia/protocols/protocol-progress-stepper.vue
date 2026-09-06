<script setup>
import { computed } from "vue";

const props = defineProps({
  steps: { type: Array, default: () => [] },
  progress: { type: Object, default: () => ({}) },
  selectedStepId: { type: [Number, String], default: null },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(["select"]);

const normalizedSteps = computed(() => [...(props.steps || [])].sort((a, b) =>
  Number(a.step_order ?? a.order ?? 0) - Number(b.step_order ?? b.order ?? 0)));

const percentage = computed(() => Math.max(0, Math.min(100, Number(props.progress?.percentage || 0))));
const total = computed(() => Number(props.progress?.total_steps ?? normalizedSteps.value.length));
const completed = computed(() => Number(props.progress?.completed_steps ?? normalizedSteps.value.filter((step) => status(step) === "completed").length));
const currentOrder = computed(() => Number(props.progress?.current_order || 0));

const keyFor = (step, index = 0) => step.id || step.runtime_step_id || step.protocol_step_id || step.code || index;

const status = (step, index = 0) => {
  const raw = String(step.status || step.runtime_status || "").toLowerCase();
  if (["completed", "completado", "cumplida", "closed", "cerrado"].includes(raw)) return "completed";
  if (["active", "activo", "in_progress", "en_proceso", "current"].includes(raw)) return "active";
  if (["skipped", "omitted", "omitido", "not_applicable", "no_aplica"].includes(raw)) return "skipped";
  if (["overdue", "vencido"].includes(raw)) return "overdue";
  const order = Number(step.step_order ?? step.order ?? index + 1);
  if (order < currentOrder.value) return "completed";
  if (order === currentOrder.value) return "active";
  return "pending";
};

const statusLabel = (value) => ({
  completed: "Completada",
  active: "Etapa actual",
  pending: "Pendiente",
  skipped: "No aplica",
  overdue: "Vencida",
}[value] || "Pendiente");

const statusIcon = (value) => ({
  completed: "bx-check",
  active: "bx-play",
  pending: "bx-time-five",
  skipped: "bx-minus",
  overdue: "bx-error",
}[value] || "bx-time-five");

const formatDate = (value) => {
  if (!value) return "Sin plazo";
  const source = String(value);
  const parsed = new Date(source.length === 10 ? `${source}T12:00:00` : source);
  if (Number.isNaN(parsed.getTime())) return source;
  return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium", timeStyle: source.length > 10 ? "short" : undefined }).format(parsed);
};

const partsCount = (step) => (step.parts || step.runtime_parts || step.part_links || []).length;
const completedParts = (step) => (step.parts || step.runtime_parts || step.part_links || []).filter((part) =>
  ["completed", "completado", "cumplida", "not_applicable", "no_aplica"].includes(String(part.status || part.runtime_status || part.pivot?.status || "").toLowerCase())).length;

const progressLabel = computed(() => props.progress?.label || `Paso ${currentOrder.value || Math.min(completed.value + 1, total.value || 1)} de ${total.value}`);
const deadlineLabel = computed(() => ({
  overdue: "Vencido",
  vencido: "Vencido",
  due_soon: "Próximo a vencer",
  on_time: "En plazo",
  completed: "Completado",
}[props.progress?.deadline_status] || "Plazo vigente"));
</script>

<template>
  <section class="protocol-stepper" :class="{ 'is-compact': compact }" aria-labelledby="protocol-progress-title">
    <header class="protocol-stepper__header">
      <div>
        <span class="protocol-stepper__eyebrow">Avance del protocolo</span>
        <h3 id="protocol-progress-title">{{ progressLabel }}</h3>
        <p>{{ completed }} de {{ total }} etapas completadas</p>
      </div>
      <div class="protocol-stepper__percentage" :aria-label="`${percentage}% completado`">{{ percentage }}%</div>
    </header>

    <div
      class="protocol-stepper__bar"
      role="progressbar"
      :aria-valuenow="percentage"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="progressLabel"
    >
      <span :style="{ width: `${percentage}%` }"></span>
    </div>

    <div class="protocol-stepper__deadline" :class="`is-${progress.deadline_status || 'neutral'}`">
      <i class="bx bx-calendar-exclamation" aria-hidden="true"></i>
      <span><strong>{{ deadlineLabel }}</strong>{{ formatDate(progress.due_at) }}</span>
    </div>

    <ol class="protocol-stepper__list">
      <li
        v-for="(step, index) in normalizedSteps"
        :key="keyFor(step, index)"
        :class="[`is-${status(step, index)}`, { 'is-selected': String(selectedStepId) === String(keyFor(step, index)) }]"
      >
        <button
          type="button"
          class="protocol-stepper__step"
          :aria-current="status(step, index) === 'active' ? 'step' : undefined"
          :aria-label="`Etapa ${index + 1}: ${step.stage_name || step.name}. ${statusLabel(status(step, index))}`"
          @click="emit('select', step)"
        >
          <span class="protocol-stepper__marker"><i class="bx" :class="statusIcon(status(step, index))" aria-hidden="true"></i></span>
          <span class="protocol-stepper__copy">
            <span class="protocol-stepper__meta">
              <strong>Etapa {{ index + 1 }}</strong>
              <em>{{ statusLabel(status(step, index)) }}</em>
            </span>
            <b>{{ step.stage_name || step.name || "Etapa sin nombre" }}</b>
            <small>
              <span v-if="step.due_at"><i class="bx bx-time-five" aria-hidden="true"></i>{{ formatDate(step.due_at) }}</span>
              <span v-if="partsCount(step)"><i class="bx bx-check-shield" aria-hidden="true"></i>{{ completedParts(step) }}/{{ partsCount(step) }} requisitos</span>
            </small>
          </span>
          <i class="bx bx-chevron-right protocol-stepper__chevron" aria-hidden="true"></i>
        </button>
      </li>
    </ol>

    <p v-if="normalizedSteps.length === 0" class="protocol-stepper__empty">Esta activación aún no tiene etapas disponibles.</p>
  </section>
</template>

<style scoped>
.protocol-stepper{--step-indigo:#4f63d9;--step-navy:#263a8f;--step-teal:#27876f;--step-line:#dce3ee;padding:1rem;border:1px solid var(--step-line);border-radius:18px;background:linear-gradient(180deg,#fff 0%,#f8faff 100%);box-shadow:0 12px 28px rgba(35,48,98,.07)}
.protocol-stepper__header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.protocol-stepper__eyebrow{display:block;margin-bottom:.2rem;color:#6c7790;font-size:.64rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.protocol-stepper__header h3{margin:0;color:var(--step-navy);font-size:1rem;font-weight:800}.protocol-stepper__header p{margin:.25rem 0 0;color:#788399;font-size:.72rem}.protocol-stepper__percentage{display:grid;min-width:54px;height:44px;padding:0 .6rem;color:#fff;font-size:.9rem;font-weight:800;place-items:center;border-radius:13px;background:linear-gradient(135deg,var(--step-navy),var(--step-indigo));box-shadow:0 8px 17px rgba(79,99,217,.22)}
.protocol-stepper__bar{height:8px;margin:.9rem 0 .75rem;overflow:hidden;border-radius:999px;background:#e6eaf2}.protocol-stepper__bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--step-indigo),#35a38b);transition:width .25s ease}
.protocol-stepper__deadline{display:flex;align-items:center;gap:.55rem;padding:.65rem .75rem;margin-bottom:.85rem;color:#526074;border:1px solid #e5eaf1;border-radius:12px;background:#fff}.protocol-stepper__deadline>i{font-size:1.1rem;color:#6b7891}.protocol-stepper__deadline span{display:flex;flex:1;justify-content:space-between;gap:.65rem;font-size:.7rem}.protocol-stepper__deadline strong{font-weight:800}.protocol-stepper__deadline.is-overdue,.protocol-stepper__deadline.is-vencido{color:#9d343b;border-color:#efc6c9;background:#fff6f6}.protocol-stepper__deadline.is-due_soon{color:#925e19;border-color:#eed6af;background:#fffaf1}.protocol-stepper__deadline.is-on_time{color:#1e715d;border-color:#c8e4db;background:#f5fcfa}
.protocol-stepper__list{display:grid;gap:.15rem;padding:0;margin:0;list-style:none}.protocol-stepper__list li{position:relative;padding-bottom:.4rem}.protocol-stepper__list li:not(:last-child)::after{position:absolute;top:38px;bottom:-3px;left:18px;width:2px;content:"";background:#dce3ed}.protocol-stepper__step{position:relative;z-index:1;display:grid;width:100%;grid-template-columns:37px minmax(0,1fr) 20px;align-items:center;gap:.7rem;padding:.62rem;border:1px solid transparent;border-radius:13px;color:inherit;text-align:left;background:transparent;transition:.16s ease}.protocol-stepper__step:hover,.protocol-stepper__list li.is-selected .protocol-stepper__step{border-color:#cfd7f8;background:#f2f5ff}.protocol-stepper__step:focus-visible{outline:3px solid rgba(79,99,217,.22);outline-offset:2px}.protocol-stepper__marker{display:grid;width:37px;height:37px;color:#7c879a;place-items:center;border:2px solid #dce3ed;border-radius:50%;background:#fff}.is-completed .protocol-stepper__marker{color:#fff;border-color:var(--step-teal);background:var(--step-teal)}.is-active .protocol-stepper__marker{color:#fff;border-color:var(--step-indigo);background:var(--step-indigo);box-shadow:0 0 0 5px rgba(79,99,217,.12)}.is-overdue .protocol-stepper__marker{color:#fff;border-color:#c54b52;background:#c54b52}.is-skipped .protocol-stepper__marker{color:#7c879a;background:#eef1f5}.protocol-stepper__copy{display:block;min-width:0}.protocol-stepper__meta{display:flex;align-items:center;justify-content:space-between;gap:.5rem}.protocol-stepper__meta strong,.protocol-stepper__meta em{font-size:.6rem;font-style:normal;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.protocol-stepper__meta strong{color:#788399}.protocol-stepper__meta em{color:#8792a4}.is-active .protocol-stepper__meta em{color:var(--step-indigo)}.is-completed .protocol-stepper__meta em{color:var(--step-teal)}.is-overdue .protocol-stepper__meta em{color:#b33c43}.protocol-stepper__copy>b{display:block;overflow:hidden;margin:.1rem 0;color:#303d57;font-size:.78rem;text-overflow:ellipsis;white-space:nowrap}.protocol-stepper__copy small{display:flex;flex-wrap:wrap;gap:.55rem;color:#7c8799;font-size:.62rem}.protocol-stepper__copy small span{display:inline-flex;align-items:center;gap:.22rem}.protocol-stepper__chevron{color:#9aa4b5;font-size:1rem}.protocol-stepper__empty{padding:1.25rem 0 .4rem;margin:0;color:#7c8799;font-size:.75rem;text-align:center}
.protocol-stepper.is-compact{padding:.8rem}.protocol-stepper.is-compact .protocol-stepper__deadline{display:none}.protocol-stepper.is-compact .protocol-stepper__copy small{display:none}
@media(max-width:575.98px){.protocol-stepper{padding:.85rem;border-radius:15px}.protocol-stepper__deadline span{flex-direction:column;gap:.12rem}.protocol-stepper__step{grid-template-columns:35px minmax(0,1fr);padding:.55rem}.protocol-stepper__chevron{display:none}.protocol-stepper__copy>b{white-space:normal}}
@media(prefers-reduced-motion:reduce){.protocol-stepper__bar span,.protocol-stepper__step{transition:none}}
</style>
