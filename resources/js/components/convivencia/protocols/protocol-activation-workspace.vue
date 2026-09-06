<script>
import axios from "axios";
import {
  formatConvivenciaDateTime,
  formatConvivenciaError,
  humanizeConvivenciaStatus,
  showConvivenciaError,
  showConvivenciaSuccess,
  toInputDateTime,
} from "../module-utils";
import { convivenciaProtocolRuntimeLabel } from "../protocol-runtime-labels";
import { downloadConvivenciaActivationPdf } from "../pdf/convivencia-activation-pdf";
import ProtocolProgressStepper from "./protocol-progress-stepper.vue";
import {
  acquireConvivenciaModalLock,
  releaseConvivenciaModalLock,
  trapConvivenciaModalFocus,
} from "../ui/modal-lifecycle";

const clone = (value) => JSON.parse(JSON.stringify(value ?? null));
const unwrap = (payload) => payload?.data?.data || payload?.data || payload?.activation || payload || {};
let workspaceSequence = 0;

export default {
  components: { ProtocolProgressStepper },
  props: {
    activationId: { type: [Number, String], required: true },
    capabilities: { type: Object, default: () => ({}) },
  },
  emits: ["close", "changed"],
  data() {
    workspaceSequence += 1;
    return {
      loading: true,
      saving: false,
      completing: false,
      materializing: false,
      exporting: false,
      activation: {},
      selectedStepId: null,
      stepForm: {},
      partSavingId: null,
      partDrafts: {},
      modalOwner: `convivencia-activation-workspace-${workspaceSequence}`,
      previousActiveElement: null,
    };
  },
  computed: {
    canManage() {
      return this.capabilities.can_activate_protocols === true;
    },
    canExport() {
      return this.capabilities.can_export_reports === true;
    },
    steps() {
      const source = this.activation.runtime_steps || this.activation.runtimeSteps || this.activation.steps || [];
      return [...source].sort((a, b) => Number(a.step_order ?? a.order ?? 0) - Number(b.step_order ?? b.order ?? 0));
    },
    progress() {
      const provided = this.activation.progress || {};
      const total = Number(provided.total_steps ?? this.steps.length);
      const completed = Number(provided.completed_steps ?? this.steps.filter((step) => step.status === "completed").length);
      const currentId = this.activation.current_activation_step_id
        || this.activation.current_runtime_step_id
        || provided.current_step_id;
      const current = this.steps.find((step) => Number(step.id) === Number(currentId))
        || this.steps.find((step) => step.status === "in_progress")
        || this.steps.find((step) => Number(step.step_order) === Number(provided.current_order));
      return {
        ...provided,
        total_steps: total,
        completed_steps: completed,
        current_order: Number(provided.current_order ?? current?.step_order ?? 0),
        percentage: Number(provided.percentage ?? (total ? Math.round(completed * 100 / total) : 0)),
        label: provided.label || (current ? `Paso ${current.step_order} de ${total}` : "Protocolo completado"),
        due_at: provided.due_at || current?.due_at || this.activation.due_at,
      };
    },
    currentStep() {
      const currentId = this.activation.current_activation_step_id
        || this.activation.current_runtime_step_id
        || this.progress?.current_step_id;
      return this.steps.find((step) => Number(step.id) === Number(currentId))
        || this.steps.find((step) => step.status === "in_progress")
        || this.steps.find((step) => Number(step.step_order) === Number(this.activation.progress?.current_order))
        || null;
    },
    selectedStep() {
      return this.steps.find((step) => Number(step.id) === Number(this.selectedStepId)) || this.currentStep || this.steps[0] || null;
    },
    selectedParts() {
      return this.selectedStep?.parts || this.selectedStep?.runtime_parts || [];
    },
    globalParts() {
      const parts = this.activation.runtime_parts || this.activation.runtimeParts || [];
      return parts.filter((part) => !part.activation_step_id);
    },
    displayedParts() {
      return [...this.globalParts, ...this.selectedParts];
    },
    protocolName() {
      return this.activation.protocol?.name || this.activation.protocol_snapshot?.name || "Protocolo activado";
    },
    reference() {
      return this.activation.case?.folio || this.activation.complaint?.folio || `Activación #${this.activation.id || this.activationId}`;
    },
    selectedIsCurrent() {
      return this.selectedStep && this.currentStep && Number(this.selectedStep.id) === Number(this.currentStep.id);
    },
    selectedIsComplete() {
      return this.selectedStep?.status === "completed";
    },
    activationIsMutable() {
      return !this.legacyMode && !["cerrado", "archivado", "closed", "completed"].includes(this.activation.status);
    },
    legacyMode() {
      return this.activation.legacy_mode === true;
    },
    canEditSelectedStep() {
      return Boolean(this.canManage && this.activationIsMutable && this.selectedIsCurrent && !this.selectedIsComplete);
    },
    completionRule() {
      return this.ruleForStep(this.selectedStep);
    },
    completionAll() {
      return this.normalizeCriteria(this.completionRule.all);
    },
    completionAny() {
      return this.normalizeCriteria(this.completionRule.any);
    },
    completionRequirements() {
      const rule = this.completionRule;
      return [
        (rule.requires_notes || rule.requires_completion_note) && "Exige notas de cierre",
        rule.requires_outcome && "Exige registrar el resultado",
        rule.requires_evidence && "Exige un resumen de evidencias",
      ].filter(Boolean);
    },
    completionExtraRules() {
      const ignored = new Set([
        "all", "any", "summary", "description", "label", "requires_notes",
        "requires_completion_note", "requires_outcome", "requires_evidence", "requires_all_parts",
      ]);
      return Object.entries(this.completionRule)
        .filter(([key, value]) => !ignored.has(key) && value !== null && value !== "" && value !== false)
        .map(([key, value]) => ({ key, label: this.humanizeToken(key), value: this.describeStructuredValue(value) }));
    },
    hasCompletionRule() {
      return Boolean(
        this.completionRule.summary
        || this.completionRule.description
        || this.completionRule.label
        || this.completionAll.length
        || this.completionAny.length
        || this.completionRequirements.length
        || this.completionExtraRules.length
      );
    },
    completionCriteriaSatisfied() {
      const values = this.stepForm.completion_criteria || {};
      const allSatisfied = this.completionAll.every((criterion, index) =>
        values[this.completionCriterionKey(criterion, "all", index)] === true);
      const anySatisfied = !this.completionAny.length || this.completionAny.some((criterion, index) =>
        values[this.completionCriterionKey(criterion, "any", index)] === true);
      return allSatisfied && anySatisfied;
    },
    selectedIsLastStep() {
      return Boolean(this.selectedStep && Number(this.selectedStep.step_order) === Math.max(0, ...this.steps.map((step) => Number(step.step_order || 0))));
    },
    incompleteRequiredParts() {
      return this.displayedParts.filter((part) => part.is_required && !["completed", "not_applicable"].includes(part.status));
    },
    deadlineChanged() {
      return Boolean(this.stepForm.initial_due_at !== undefined && this.stepForm.due_at !== this.stepForm.initial_due_at);
    },
    selectedDeadlineResolution() {
      return this.deadlineResolution(this.selectedStep);
    },
    selectedDeadlineUsesFallback() {
      return this.selectedDeadlineResolution?.fallback_used === true;
    },
    selectedDeadlineCanBeEdited() {
      return ["external", "external_defined"].includes(this.deadlineUnitFor(this.selectedStep));
    },
    selectedExtensionConfiguredMaximum() {
      const value = this.selectedStep?.extension_value ?? this.selectedStep?.snapshot?.extension_value;
      return value === null || value === undefined || value === "" ? null : Number(value);
    },
    selectedExtensionUsedValue() {
      return Math.max(0, Number(this.selectedStep?.data?.extension_used_value || 0));
    },
    selectedExtensionMaximum() {
      if (this.selectedExtensionConfiguredMaximum === null) return null;
      return Math.max(0, this.selectedExtensionConfiguredMaximum - this.selectedExtensionUsedValue);
    },
    selectedExtensionUnit() {
      return this.extensionUnitFor(this.selectedStep);
    },
    extensionRequiresApproval() {
      return (this.selectedStep?.snapshot?.metadata?.extension_requires_approval
        ?? this.selectedStep?.metadata?.extension_requires_approval) === true;
    },
  },
  watch: {
    activationId: {
      immediate: true,
      handler() { this.load(); },
    },
    selectedStep: {
      immediate: true,
      deep: true,
      handler(step) { this.prepareStepForm(step); },
    },
  },
  mounted() {
    this.previousActiveElement = document.activeElement;
    acquireConvivenciaModalLock(this.modalOwner);
    this.$nextTick(() => this.$refs.dialog?.focus());
  },
  beforeUnmount() {
    releaseConvivenciaModalLock(this.modalOwner);
    this.previousActiveElement?.focus?.();
    this.previousActiveElement = null;
  },
  methods: {
    workspaceBusy() {
      return Boolean(this.saving || this.completing || this.materializing || this.partSavingId);
    },
    requestClose() {
      if (!this.workspaceBusy()) this.$emit("close");
    },
    onKeydown(event) {
      if (event.key === "Escape") {
        event.preventDefault();
        this.requestClose();
        return;
      }
      trapConvivenciaModalFocus(event, this.$refs.dialog);
    },
    async load(preferredStepId = null) {
      if (!this.activationId) return;
      this.loading = true;
      try {
        const response = await axios.get(`/api/convivencia/protocol-activations/${this.activationId}`);
        this.activation = unwrap(response.data);
        const currentId = this.activation.current_activation_step_id
          || this.activation.current_runtime_step_id
          || this.activation.progress?.current_step_id
          || this.steps.find((step) => step.status === "in_progress")?.id
          || this.steps[0]?.id;
        this.selectedStepId = preferredStepId || currentId || null;
        this.partDrafts = {};
        this.steps.forEach((step) => (step.parts || step.runtime_parts || []).forEach((part) => {
          this.partDrafts[part.id] = this.partDraft(part);
        }));
        this.$emit("changed", this.activation);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo abrir la ejecución del protocolo."));
      } finally {
        this.loading = false;
      }
    },
    prepareStepForm(step) {
      if (!step) { this.stepForm = {}; return; }
      this.stepForm = {
        revision: this.activation.revision || null,
        status: step.status || "pending",
        notes: step.notes || "",
        outcome: step.outcome || "",
        evidence_summary: step.evidence_summary || "",
        due_at: toInputDateTime(step.due_at),
        initial_due_at: toInputDateTime(step.due_at),
        extension_value: null,
        extension_unit: this.extensionUnitFor(step) || "business_days",
        extension_approved: false,
        log_notes: "",
        data: clone(step.data || {}),
        completion_criteria: this.normalizeCompletionCriteria(step),
      };
    },
    selectStep(step) {
      this.selectedStepId = step.id;
    },
    partDraft(part) {
      return {
        revision: this.activation.revision || null,
        status: part.status || "pending",
        notes: part.notes || "",
        evidence_summary: part.evidence_summary || "",
        outcome: part.outcome || "",
        due_at: toInputDateTime(part.due_at),
        initial_due_at: toInputDateTime(part.due_at),
        data: clone(part.data || {}),
      };
    },
    partForm(part) {
      if (!this.partDrafts[part.id]) this.partDrafts[part.id] = this.partDraft(part);
      return this.partDrafts[part.id];
    },
    formatDate(value) {
      return formatConvivenciaDateTime(value);
    },
    statusLabel(value) {
      return ({
        pending: "Pendiente", in_progress: "En ejecución", completed: "Completada",
        blocked: "Bloqueada", skipped: "Omitida", not_applicable: "No aplica",
      })[value] || humanizeConvivenciaStatus(value);
    },
    categoryLabel(value) {
      return ({
        action: "Acción", sanction: "Sanción", protective_measure: "Medida protectora",
        formative_measure: "Medida formativa", restorative_measure: "Medida restaurativa",
        interview: "Entrevista", communication: "Comunicación", notification: "Notificación",
        document: "Documento", evidence: "Evidencia", internal_referral: "Derivación interna",
        external_referral: "Derivación externa", external_report: "Denuncia externa", appeal: "Apelación",
        follow_up: "Seguimiento", closure: "Cierre", special_rule: "Regla especial", other: "Otra parte",
      })[value] || humanizeConvivenciaStatus(value);
    },
    categoryIcon(value) {
      return ({ sanction: "bx-error-circle", protective_measure: "bx-shield-quarter", formative_measure: "bx-book-heart", restorative_measure: "bx-wrench", interview: "bx-conversation", document: "bx-file", evidence: "bx-check-square", external_report: "bx-export", appeal: "bx-revision", follow_up: "bx-calendar-check", closure: "bx-check-circle" })[value] || "bx-check-shield";
    },
    deadlineResolution(subject) {
      return subject?.snapshot?.deadline_resolution
        || subject?.deadline_resolution
        || subject?.snapshot?.metadata?.deadline_resolution
        || subject?.metadata?.deadline_resolution
        || null;
    },
    deadlineFallbackReason(subject) {
      const resolution = this.deadlineResolution(subject);
      return resolution?.fallback_reason || resolution?.reason || "No existe un calendario escolar suficiente para confirmar esta fecha.";
    },
    deadlineUsesFallback(subject) {
      return this.deadlineResolution(subject)?.fallback_used === true;
    },
    deadlineUnitFor(step) {
      return step?.deadline_unit || step?.snapshot?.deadline_unit || step?.snapshot?.deadline?.unit || "";
    },
    extensionUnitFor(step) {
      return step?.extension_unit || step?.snapshot?.extension_unit || step?.snapshot?.deadline?.extension_unit || this.deadlineUnitFor(step);
    },
    deadlineUnitLabel(value) {
      return ({
        hours: "horas",
        calendar_days: "días corridos",
        business_days: "días hábiles",
        school_days: "días escolares",
        external: "plazo externo",
        external_defined: "plazo externo",
      })[value] || humanizeConvivenciaStatus(value);
    },
    isGlobalPart(part) {
      return !part?.activation_step_id;
    },
    ruleForStep(step) {
      return step?.snapshot?.completion_rule || step?.completion_rule || {};
    },
    normalizeCriteria(value) {
      if (Array.isArray(value)) return value.filter((item) => item !== null && item !== "");
      return value === null || value === undefined || value === "" ? [] : [value];
    },
    humanizeToken(value) {
      const token = String(value ?? "").trim();
      if (!token) return "Criterio sin nombre";
      const translated = convivenciaProtocolRuntimeLabel(token);
      if (translated) return translated;
      const text = token.replaceAll("_", " ").replaceAll("-", " ");
      return text.charAt(0).toLocaleUpperCase("es-CL") + text.slice(1);
    },
    describeStructuredValue(value) {
      if (Array.isArray(value)) return value.map((item) => this.describeStructuredValue(item)).join(" · ");
      if (value && typeof value === "object") {
        return Object.entries(value)
          .map(([key, item]) => `${this.humanizeToken(key)}: ${this.describeStructuredValue(item)}`)
          .join(" · ");
      }
      if (typeof value === "boolean") return value ? "Sí" : "No";
      return this.humanizeToken(value);
    },
    criterionLabel(criterion) {
      if (typeof criterion === "string" || typeof criterion === "number") return this.humanizeToken(criterion);
      if (!criterion || typeof criterion !== "object") return "Criterio configurado";
      if (criterion.label || criterion.name || criterion.title) return criterion.label || criterion.name || criterion.title;
      const field = criterion.field || criterion.key || criterion.code;
      const operator = criterion.operator || criterion.rule;
      const target = criterion.value ?? criterion.expected;
      if (field) {
        return [this.humanizeToken(field), operator && this.humanizeToken(operator), target !== undefined && this.describeStructuredValue(target)]
          .filter(Boolean).join(": ");
      }
      return this.describeStructuredValue(criterion);
    },
    completionCriterionKey(criterion, group, index) {
      if (typeof criterion === "string" || typeof criterion === "number") return String(criterion);
      const candidate = criterion?.key || criterion?.code || criterion?.field || criterion?.name;
      return candidate ? String(candidate) : `${group}_${index + 1}`;
    },
    storedCriterionValue(stored, criterion, group, index) {
      const key = this.completionCriterionKey(criterion, group, index);
      if (Array.isArray(stored)) {
        return stored.some((item) => item === key || JSON.stringify(item) === JSON.stringify(criterion));
      }
      if (!stored || typeof stored !== "object") return false;
      if (Object.prototype.hasOwnProperty.call(stored, key)) return stored[key] === true;
      const grouped = stored[group];
      if (Array.isArray(grouped)) return grouped.some((item) => item === key || JSON.stringify(item) === JSON.stringify(criterion));
      if (grouped && typeof grouped === "object" && Object.prototype.hasOwnProperty.call(grouped, key)) return grouped[key] === true;
      return false;
    },
    normalizeCompletionCriteria(step) {
      const stored = step?.data?.completion_criteria || {};
      const rule = this.ruleForStep(step);
      const criteria = {};
      this.normalizeCriteria(rule.all).forEach((criterion, index) => {
        criteria[this.completionCriterionKey(criterion, "all", index)] = this.storedCriterionValue(stored, criterion, "all", index);
      });
      this.normalizeCriteria(rule.any).forEach((criterion, index) => {
        criteria[this.completionCriterionKey(criterion, "any", index)] = this.storedCriterionValue(stored, criterion, "any", index);
      });
      return criteria;
    },
    partCondition(part) {
      const condition = part?.snapshot?.condition || part?.condition || null;
      if (!condition || (typeof condition === "object" && !Object.keys(condition).length)) return null;
      return condition;
    },
    partConfiguration(part) {
      return part?.snapshot?.configuration || part?.configuration || {};
    },
    partConditionSummary(part) {
      return this.describeStructuredValue(this.partCondition(part));
    },
    partBlockedForPreschool(part) {
      return this.partConfiguration(part).blocked_for_preschool_child === true;
    },
    canMarkNotApplicable(part) {
      if (!part?.is_required) return true;
      const configuration = this.partConfiguration(part);
      return Boolean(
        this.partCondition(part)
        || configuration.allow_not_applicable === true
        || configuration.blocked_for_preschool_child === true
      );
    },
    onPartStatusChanged(part) {
      const form = this.partForm(part);
      if (form.status !== "not_applicable" || !this.partCondition(part)) return;
      form.data ||= {};
      form.data.condition_confirmed = false;
      form.data.condition_not_met_confirmed = false;
    },
    canEditPart(part) {
      return Boolean(this.canManage && this.activationIsMutable && (this.isGlobalPart(part) || this.selectedIsCurrent));
    },
    isRevisionConflict(error) {
      const status = error?.response?.status;
      const errors = error?.response?.data?.errors || {};
      return status === 409 || Boolean(errors.revision || errors.expected_revision);
    },
    async saveStep() {
      if (!this.selectedStep?.id || !this.canEditSelectedStep) return;
      const extensionValue = this.stepForm.extension_value === null || this.stepForm.extension_value === ""
        ? null
        : Number(this.stepForm.extension_value);
      if (this.deadlineChanged && !this.selectedDeadlineCanBeEdited) {
        showConvivenciaError("Este vencimiento se calcula desde el protocolo y no puede sobrescribirse. Utiliza la prórroga configurada.", "Plazo protegido");
        return;
      }
      if (extensionValue && this.selectedExtensionMaximum !== null && extensionValue > this.selectedExtensionMaximum) {
        showConvivenciaError(`El saldo disponible para nuevas prórrogas es de ${this.selectedExtensionMaximum} ${this.deadlineUnitLabel(this.selectedExtensionUnit)}.`, "Prórroga fuera de rango");
        return;
      }
      if (extensionValue && this.extensionRequiresApproval && this.stepForm.extension_approved !== true) {
        showConvivenciaError("Confirma que la instancia responsable autorizó esta prórroga.", "Autorización requerida");
        return;
      }
      if ((this.deadlineChanged || this.stepForm.extension_value) && !this.stepForm.log_notes?.trim()) {
        showConvivenciaError("Fundamenta el cambio de plazo o la prórroga para mantener la trazabilidad.", "Falta justificación");
        return;
      }
      this.saving = true;
      try {
        await axios.put(`/api/convivencia/protocol-activation-steps/${this.selectedStep.id}`, {
          revision: this.stepForm.revision || undefined,
          status: this.stepForm.status,
          notes: this.stepForm.notes || null,
          outcome: this.stepForm.outcome || null,
          evidence_summary: this.stepForm.evidence_summary || null,
          due_at: this.deadlineChanged ? (this.stepForm.due_at || null) : undefined,
          extension_value: extensionValue || undefined,
          extension_unit: extensionValue ? this.selectedExtensionUnit : undefined,
          extension_approved: extensionValue && this.extensionRequiresApproval
            ? this.stepForm.extension_approved === true
            : undefined,
          log_notes: this.stepForm.log_notes || null,
          completion_criteria: this.stepForm.completion_criteria || {},
          data: {
            ...(this.stepForm.data || {}),
            completion_criteria: this.stepForm.completion_criteria || {},
          },
        });
        await showConvivenciaSuccess("La etapa quedó actualizada.");
        await this.load(this.selectedStepId);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo actualizar la etapa."));
        if (this.isRevisionConflict(error)) await this.load(this.selectedStepId);
      } finally {
        this.saving = false;
      }
    },
    async completeStep() {
      if (!this.selectedStep?.id || !this.canEditSelectedStep) return;
      if (!this.completionCriteriaSatisfied) {
        showConvivenciaError("Marca todos los criterios obligatorios y, cuando corresponda, al menos una alternativa antes de completar.", "Criterios pendientes");
        return;
      }
      const closesActivation = this.selectedIsLastStep;
      this.completing = true;
      try {
        await axios.post(`/api/convivencia/protocol-activation-steps/${this.selectedStep.id}/complete`, {
          revision: this.stepForm.revision || undefined,
          notes: this.stepForm.notes || null,
          outcome: this.stepForm.outcome || null,
          evidence_summary: this.stepForm.evidence_summary || null,
          log_notes: this.stepForm.log_notes || null,
          completion_criteria: this.stepForm.completion_criteria || {},
          data: {
            ...(this.stepForm.data || {}),
            completion_criteria: this.stepForm.completion_criteria || {},
          },
        });
        await showConvivenciaSuccess(closesActivation
          ? "Etapa final completada. La activación quedó cerrada."
          : "Etapa completada. El protocolo avanzó al paso siguiente.");
        await this.load();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo completar la etapa."));
        if (this.isRevisionConflict(error)) await this.load();
      } finally {
        this.completing = false;
      }
    },
    async savePart(part, quickComplete = false) {
      if (!part?.id || !this.canEditPart(part)) return;
      const form = this.partForm(part);
      const deadlineChanged = form.due_at !== form.initial_due_at;
      const nextStatus = quickComplete ? "completed" : form.status;
      if (nextStatus === "not_applicable" && !this.canMarkNotApplicable(part)) {
        showConvivenciaError("Una parte obligatoria sin condición ni excepción configurada no puede marcarse como no aplicable.", "Estado no permitido");
        return;
      }
      if (nextStatus === "not_applicable" && !form.notes?.trim()) {
        showConvivenciaError("Explica en las notas por qué esta parte no aplica al expediente.", "Justificación requerida");
        return;
      }
      if (nextStatus === "not_applicable" && this.partCondition(part)) {
        if (form.data?.condition_confirmed === true) {
          showConvivenciaError("La parte no puede quedar como no aplicable mientras la condición figure como cumplida.", "Condición contradictoria");
          return;
        }
        if (form.data?.condition_not_met_confirmed !== true) {
          showConvivenciaError("Confirma expresamente que la condición de aplicación no se cumple en este expediente.", "Confirmación requerida");
          return;
        }
        form.data = { ...(form.data || {}), condition_confirmed: false };
      }
      if (quickComplete && part.requires_evidence && !form.evidence_summary?.trim()) {
        showConvivenciaError("Este requisito exige registrar un resumen de evidencia antes de marcarlo como completado.", "Evidencia requerida");
        return;
      }
      if (deadlineChanged && !form.notes?.trim()) {
        showConvivenciaError("Fundamenta el cambio de plazo en las notas del requisito para mantener la trazabilidad.", "Falta justificación");
        return;
      }
      if (this.partBlockedForPreschool(part) && nextStatus !== "not_applicable") {
        showConvivenciaError("Esta rama contradictoria del RICE está bloqueada para párvulos. Regístrala como no aplicable con su justificación.", "Aplicación bloqueada");
        return;
      }
      if (this.partCondition(part) && ["in_progress", "completed"].includes(nextStatus) && form.data?.condition_confirmed !== true) {
        showConvivenciaError("Confirma expresamente que se cumple la condición de aplicación antes de ejecutar esta parte.", "Condición pendiente");
        return;
      }
      this.partSavingId = part.id;
      try {
        const status = nextStatus;
        await axios.put(`/api/convivencia/protocol-activation-parts/${part.id}`, {
          revision: form.revision || undefined,
          status,
          notes: form.notes || null,
          evidence_summary: form.evidence_summary || null,
          outcome: form.outcome || null,
          due_at: deadlineChanged ? (form.due_at || null) : undefined,
          data: form.data || {},
        });
        await showConvivenciaSuccess(quickComplete ? "Requisito marcado como completado." : "Requisito actualizado.");
        await this.load(this.selectedStepId);
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo actualizar este requisito."));
        if (this.isRevisionConflict(error)) await this.load(this.selectedStepId);
      } finally {
        this.partSavingId = null;
      }
    },
    async materializeRuntime() {
      if (!this.canManage || !this.legacyMode || this.materializing) return;
      this.materializing = true;
      try {
        const endpoint = this.activation.runtime_materialization_endpoint
          || `/api/convivencia/protocol-activations/${this.activation.id || this.activationId}/materialize-runtime`;
        await axios.post(endpoint, { revision: Number(this.activation.revision) });
        await showConvivenciaSuccess("La ruta histórica quedó preparada y ya puede gestionarse paso a paso.");
        await this.load();
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo preparar la ruta histórica."));
      } finally {
        this.materializing = false;
      }
    },
    async exportPdf() {
      if (this.exporting) return;
      this.exporting = true;
      try {
        await downloadConvivenciaActivationPdf({ data: this.activation });
      } catch (error) {
        showConvivenciaError(formatConvivenciaError(error, "No se pudo generar el expediente PDF."));
      } finally {
        this.exporting = false;
      }
    },
  },
};
</script>

<template>
  <Teleport to="body">
    <div ref="dialog" class="activation-workspace" role="dialog" aria-modal="true" aria-labelledby="activation-workspace-title" tabindex="-1" @keydown="onKeydown">
      <div class="activation-workspace__shell">
        <header class="activation-workspace__header">
          <div class="activation-workspace__identity">
            <span class="activation-workspace__mark"><i class="bx bx-git-branch" aria-hidden="true"></i></span>
            <div>
              <span>Expediente operativo · {{ reference }}</span>
              <h2 id="activation-workspace-title">{{ protocolName }}</h2>
              <p v-if="!loading">{{ progress.label }} · {{ progress.completed_steps }}/{{ progress.total_steps }} etapas completadas</p>
            </div>
          </div>
          <div class="activation-workspace__actions">
            <button v-if="canExport && !loading" type="button" class="btn btn-outline-light" :disabled="exporting" @click="exportPdf">
              <i class="bx" :class="exporting ? 'bx-loader-alt bx-spin' : 'bx-file'" aria-hidden="true"></i><span>PDF expediente</span>
            </button>
            <button type="button" class="activation-workspace__close" :disabled="workspaceBusy()" aria-label="Cerrar espacio de trabajo" @click="requestClose"><i class="bx bx-x" aria-hidden="true"></i></button>
          </div>
        </header>

        <div v-if="loading" class="activation-workspace__loading" role="status">
          <span class="spinner-border text-primary" aria-hidden="true"></span><b>Cargando ruta y requisitos…</b>
        </div>

        <div v-else class="activation-workspace__body">
          <aside class="activation-workspace__rail">
            <ProtocolProgressStepper :steps="steps" :progress="progress" :selected-step-id="selectedStepId" @select="selectStep" />
            <div class="activation-workspace__context">
              <span>Contexto de activación</span>
              <dl>
                <div><dt>Estado</dt><dd>{{ statusLabel(activation.status) }}</dd></div>
                <div><dt>Activada</dt><dd>{{ formatDate(activation.activated_at || activation.created_at) }}</dd></div>
                <div><dt>Responsable</dt><dd>{{ activation.activated_by?.name || activation.activatedBy?.name || "Sin dato" }}</dd></div>
              </dl>
            </div>
          </aside>

          <main v-if="selectedStep" class="activation-workspace__main">
            <section v-if="legacyMode" class="activation-workspace__legacy" role="status" aria-labelledby="legacy-runtime-title">
              <span><i class="bx bx-history" aria-hidden="true"></i></span>
              <div><h3 id="legacy-runtime-title">Activación histórica sin ruta ejecutable</h3><p>El expediente conserva su información original. Para registrar avances con etapas, plazos y requisitos verificables, primero hay que preparar su ruta operativa.</p></div>
              <button v-if="canManage" type="button" class="btn btn-primary" :disabled="materializing" @click="materializeRuntime"><i class="bx" :class="materializing ? 'bx-loader-alt bx-spin' : 'bx-git-branch'" aria-hidden="true"></i>{{ materializing ? "Preparando…" : "Preparar ruta histórica" }}</button>
              <small v-else>Se requiere permiso para activar protocolos.</small>
            </section>
            <section class="activation-stage" :class="{ 'is-current': selectedIsCurrent }">
              <header class="activation-stage__heading">
                <div>
                  <span>{{ selectedIsCurrent ? "Etapa actual" : statusLabel(selectedStep.status) }} · Paso {{ selectedStep.step_order }} de {{ progress.total_steps }}</span>
                  <h3>{{ selectedStep.stage_name }}</h3>
                  <p>{{ selectedStep.description || "Sin descripción adicional." }}</p>
                </div>
                <span class="activation-stage__status" :class="`is-${selectedStep.status}`"><i class="bx bx-radio-circle-marked" aria-hidden="true"></i>{{ statusLabel(selectedStep.status) }}</span>
              </header>

              <div class="activation-stage__facts">
                <div><i class="bx bx-user-pin" aria-hidden="true"></i><span>Responsable<b>{{ selectedStep.responsible_label || "Por asignar" }}</b></span></div>
                <div><i class="bx bx-calendar-event" aria-hidden="true"></i><span>Inicio<b>{{ formatDate(selectedStep.started_at) }}</b></span></div>
                <div><i class="bx bx-time-five" aria-hidden="true"></i><span>{{ selectedDeadlineUsesFallback ? "Vencimiento estimado" : "Vencimiento" }}<b>{{ formatDate(selectedStep.due_at) }}</b></span></div>
                <div><i class="bx bx-check-shield" aria-hidden="true"></i><span>Requisitos<b>{{ displayedParts.filter(p => p.status === 'completed').length }}/{{ displayedParts.length }}</b></span></div>
              </div>

              <div v-if="selectedDeadlineUsesFallback" class="activation-deadline-warning" role="alert">
                <i class="bx bx-calendar-exclamation" aria-hidden="true"></i>
                <span><b>Plazo estimado por falta de calendario escolar</b>La fecha mostrada es un cálculo alternativo y no debe considerarse confirmada. {{ deadlineFallbackReason(selectedStep) }}</span>
              </div>

              <section v-if="hasCompletionRule" class="activation-stage__rules" aria-labelledby="activation-completion-title">
                <header>
                  <span><i class="bx bx-list-check" aria-hidden="true"></i></span>
                  <div><h4 id="activation-completion-title">Condiciones para completar la etapa</h4><p>{{ completionRule.summary || completionRule.description || completionRule.label || "Verifica los criterios configurados antes de avanzar." }}</p></div>
                </header>
                <div v-if="completionAll.length || completionAny.length" class="activation-stage__criteria-grid">
                  <article v-if="completionAll.length">
                    <div class="activation-stage__criteria-title"><i class="bx bx-check-shield" aria-hidden="true"></i><span><b>Deben cumplirse todas</b><small>{{ completionAll.length }} criterio(s) obligatorio(s)</small></span></div>
                    <label v-for="(criterion, index) in completionAll" :key="`all-${completionCriterionKey(criterion, 'all', index)}`">
                      <input v-model="stepForm.completion_criteria[completionCriterionKey(criterion, 'all', index)]" type="checkbox" :disabled="!canEditSelectedStep" />
                      <span>{{ criterionLabel(criterion) }}</span>
                    </label>
                  </article>
                  <article v-if="completionAny.length" class="is-any">
                    <div class="activation-stage__criteria-title"><i class="bx bx-git-branch" aria-hidden="true"></i><span><b>Debe cumplirse al menos una</b><small>{{ completionAny.length }} alternativa(s) válida(s)</small></span></div>
                    <label v-for="(criterion, index) in completionAny" :key="`any-${completionCriterionKey(criterion, 'any', index)}`">
                      <input v-model="stepForm.completion_criteria[completionCriterionKey(criterion, 'any', index)]" type="checkbox" :disabled="!canEditSelectedStep" />
                      <span>{{ criterionLabel(criterion) }}</span>
                    </label>
                  </article>
                </div>
                <div v-if="completionRequirements.length" class="activation-stage__rule-tags" aria-label="Registros exigidos">
                  <span v-for="requirement in completionRequirements" :key="requirement"><i class="bx bx-check-circle" aria-hidden="true"></i>{{ requirement }}</span>
                </div>
                <dl v-if="completionExtraRules.length" class="activation-stage__extra-rules">
                  <div v-for="rule in completionExtraRules" :key="rule.key"><dt>{{ rule.label }}</dt><dd>{{ rule.value }}</dd></div>
                </dl>
              </section>

              <p v-if="canManage && !canEditSelectedStep" class="activation-stage__readonly" role="status"><i class="bx bx-lock-alt" aria-hidden="true"></i>{{ selectedIsComplete ? "Esta etapa ya fue completada y se conserva como registro histórico." : "Seleccionaste una etapa que aún no está activa. Puedes revisarla, pero solo la etapa actual admite cambios." }}</p>

              <div class="activation-stage__grid">
                <div><label class="form-label" for="activation-step-notes">Notas de gestión</label><textarea id="activation-step-notes" v-model="stepForm.notes" class="form-control" rows="3" :disabled="!canEditSelectedStep" placeholder="Registra gestiones, coordinaciones o antecedentes relevantes."></textarea></div>
                <div><label class="form-label" for="activation-step-outcome">Resultado de la etapa</label><textarea id="activation-step-outcome" v-model="stepForm.outcome" class="form-control" rows="3" :disabled="!canEditSelectedStep" placeholder="Síntesis del resultado alcanzado."></textarea></div>
                <div><label class="form-label" for="activation-step-evidence">Resumen de evidencias</label><textarea id="activation-step-evidence" v-model="stepForm.evidence_summary" class="form-control" rows="2" :disabled="!canEditSelectedStep" placeholder="Documentos, entrevistas o respaldos incorporados."></textarea></div>
                <div class="activation-stage__controls">
                  <label class="form-label" for="activation-step-due">Fecha límite</label>
                  <input id="activation-step-due" v-model="stepForm.due_at" type="datetime-local" class="form-control" :disabled="!canEditSelectedStep || !selectedDeadlineCanBeEdited" />
                  <small class="activation-stage__deadline-help">{{ selectedDeadlineCanBeEdited ? "Plazo definido externamente; cualquier ajuste requiere justificación." : "Fecha calculada por el protocolo; solo se modifica mediante una prórroga autorizada." }}</small>
                  <template v-if="selectedStep.can_extend">
                    <label class="form-label" for="activation-step-extension">Prórroga</label>
                    <div class="input-group"><input id="activation-step-extension" v-model.number="stepForm.extension_value" type="number" min="1" :max="selectedExtensionMaximum ?? undefined" class="form-control" :disabled="!canEditSelectedStep || selectedExtensionMaximum === 0" /><span class="input-group-text">{{ deadlineUnitLabel(selectedExtensionUnit) }}</span></div>
                    <small class="activation-stage__extension-help">{{ selectedExtensionMaximum === 0 ? `Máximo agotado: ya se utilizaron ${selectedExtensionUsedValue} ${deadlineUnitLabel(selectedExtensionUnit)}.` : selectedExtensionMaximum !== null ? `Saldo disponible: máximo ${selectedExtensionMaximum} de ${selectedExtensionConfiguredMaximum} ${deadlineUnitLabel(selectedExtensionUnit)}.` : "Extensión sujeta a la configuración del protocolo." }}</small>
                    <label v-if="extensionRequiresApproval && stepForm.extension_value" class="activation-stage__approval">
                      <input v-model="stepForm.extension_approved" type="checkbox" :disabled="!canEditSelectedStep" />
                      <span><b>Prórroga autorizada</b>Confirmo que la instancia responsable aprobó este ajuste.</span>
                    </label>
                  </template>
                </div>
                <div v-if="deadlineChanged || stepForm.extension_value" class="activation-stage__justification">
                  <label class="form-label" for="activation-step-log-notes">Justificación del cambio de plazo o prórroga</label>
                  <textarea id="activation-step-log-notes" v-model="stepForm.log_notes" class="form-control" rows="2" :disabled="!canEditSelectedStep" required placeholder="Fundamenta el ajuste para dejarlo en la trazabilidad del expediente."></textarea>
                </div>
              </div>

              <div v-if="canEditSelectedStep" class="activation-stage__buttons">
                <span v-if="incompleteRequiredParts.length"><i class="bx bx-info-circle" aria-hidden="true"></i>{{ incompleteRequiredParts.length }} requisito(s) obligatorio(s) aún pendiente(s).</span>
                <button type="button" class="btn btn-outline-primary" :disabled="saving || completing" @click="saveStep"><i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : 'bx-save'" aria-hidden="true"></i>Guardar etapa</button>
                <button type="button" class="btn btn-success" :disabled="saving || completing || !completionCriteriaSatisfied" @click="completeStep"><i class="bx" :class="completing ? 'bx-loader-alt bx-spin' : 'bx-check-double'" aria-hidden="true"></i>{{ selectedIsLastStep ? "Completar y cerrar" : "Completar y avanzar" }}</button>
              </div>
            </section>

            <section class="activation-parts" aria-labelledby="activation-parts-title">
              <header><div><span>Ejecución verificable</span><h3 id="activation-parts-title">Partes de la etapa y requisitos generales</h3></div><b>{{ displayedParts.length }} elementos</b></header>
              <div v-if="displayedParts.length" class="activation-parts__list">
                <article v-for="part in displayedParts" :key="part.id" class="activation-part" :class="[`is-${part.status}`, { 'is-required': part.is_required }]">
                  <div class="activation-part__top">
                    <span class="activation-part__icon"><i class="bx" :class="categoryIcon(part.category)" aria-hidden="true"></i></span>
                    <div><span>{{ categoryLabel(part.category) }} · {{ part.code }}<em v-if="isGlobalPart(part)">General</em><em v-if="part.is_required">Obligatoria</em></span><h4>{{ part.title }}</h4><p>{{ part.description || part.instructions || "Sin detalle adicional." }}</p><p v-if="partCondition(part)" class="activation-part__condition-preview"><i class="bx bx-git-branch" aria-hidden="true"></i><b>Aplica cuando:</b> {{ partConditionSummary(part) }}</p><p v-if="deadlineUsesFallback(part)" class="activation-part__deadline-warning" role="alert"><i class="bx bx-calendar-exclamation" aria-hidden="true"></i><b>Plazo estimado por falta de calendario escolar:</b> {{ deadlineFallbackReason(part) }}</p></div>
                    <span class="activation-part__state">{{ statusLabel(part.status) }}</span>
                  </div>
                  <details class="activation-part__details">
                    <summary>{{ canEditPart(part) ? "Actualizar cumplimiento y evidencia" : "Ver cumplimiento y evidencia" }}</summary>
                    <div class="activation-part__form">
                      <div v-if="partBlockedForPreschool(part)" class="activation-part__condition activation-part__wide" role="alert"><i class="bx bx-block"></i><span><b>Rama bloqueada para párvulos</b>La fuente contiene una contradicción normativa. Solo puede registrarse como no aplicable y con justificación.</span></div>
                      <label v-if="partCondition(part) && partForm(part).status === 'not_applicable'" class="activation-part__condition activation-part__wide is-not-applicable"><input v-model="partForm(part).data.condition_not_met_confirmed" type="checkbox" :disabled="!canEditPart(part)" /><span><b>Confirmación de no aplicabilidad</b>{{ partConditionSummary(part) }}. Confirmo que esta condición no se cumple en el expediente.</span></label>
                      <label v-else-if="partCondition(part) && !partBlockedForPreschool(part)" class="activation-part__condition activation-part__wide"><input v-model="partForm(part).data.condition_confirmed" type="checkbox" :disabled="!canEditPart(part)" /><span><b>Confirmación de aplicabilidad</b>{{ partConditionSummary(part) }}. Confirma que esta condición se cumple en el expediente.</span></label>
                      <div><label class="form-label">Estado</label><select v-model="partForm(part).status" class="form-select" :disabled="!canEditPart(part)" @change="onPartStatusChanged(part)"><option v-if="!partBlockedForPreschool(part)" value="pending">Pendiente</option><option v-if="!partBlockedForPreschool(part)" value="in_progress">En ejecución</option><option v-if="!partBlockedForPreschool(part)" value="completed">Completada</option><option v-if="canMarkNotApplicable(part)" value="not_applicable">No aplica</option></select></div>
                      <div><label class="form-label">Vencimiento</label><input v-model="partForm(part).due_at" type="datetime-local" class="form-control" :disabled="!canEditPart(part)" /></div>
                      <div class="activation-part__wide"><label class="form-label">Notas</label><textarea v-model="partForm(part).notes" rows="2" class="form-control" :disabled="!canEditPart(part)"></textarea></div>
                      <div class="activation-part__wide"><label class="form-label">Evidencia {{ part.requires_evidence ? '(requerida)' : '' }}</label><textarea v-model="partForm(part).evidence_summary" rows="2" class="form-control" :disabled="!canEditPart(part)" placeholder="Describe el respaldo incorporado al expediente."></textarea></div>
                    </div>
                    <div v-if="canEditPart(part)" class="activation-part__actions">
                      <button v-if="part.status !== 'completed' && !partBlockedForPreschool(part)" type="button" class="btn btn-sm btn-outline-success" :disabled="partSavingId === part.id" @click="savePart(part, true)"><i class="bx bx-check" aria-hidden="true"></i>Marcar cumplida</button>
                      <button type="button" class="btn btn-sm btn-primary" :disabled="partSavingId === part.id" @click="savePart(part)"><i class="bx" :class="partSavingId === part.id ? 'bx-loader-alt bx-spin' : 'bx-save'" aria-hidden="true"></i>Guardar</button>
                    </div>
                  </details>
                </article>
              </div>
              <p v-else class="activation-parts__empty"><i class="bx bx-layer" aria-hidden="true"></i>Esta etapa no tiene partes configuradas.</p>
            </section>
          </main>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.activation-workspace{--aw-navy:#202e6d;--aw-indigo:#5064d9;--aw-teal:#278875;--aw-line:#dce3ee;position:fixed;z-index:11000;inset:0;padding:1rem;background:rgba(14,22,45,.67);backdrop-filter:blur(8px)}.activation-workspace__shell{display:flex;height:100%;overflow:hidden;flex-direction:column;border:1px solid rgba(255,255,255,.4);border-radius:24px;background:#f4f7fb;box-shadow:0 28px 80px rgba(6,13,35,.35)}.activation-workspace__header{display:flex;min-height:88px;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.3rem;color:#fff;background:linear-gradient(118deg,#1d2a69,#475bc8 62%,#2b8a7b)}.activation-workspace__identity{display:flex;min-width:0;align-items:center;gap:.9rem}.activation-workspace__mark{display:grid;width:48px;height:48px;flex:0 0 48px;font-size:1.45rem;place-items:center;border:1px solid rgba(255,255,255,.28);border-radius:15px;background:rgba(255,255,255,.12)}.activation-workspace__identity>div{min-width:0}.activation-workspace__identity span{display:block;font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;opacity:.78}.activation-workspace__identity h2{overflow:hidden;margin:.15rem 0 0;font-size:1.15rem;font-weight:800;text-overflow:ellipsis;white-space:nowrap}.activation-workspace__identity p{margin:.2rem 0 0;font-size:.7rem;opacity:.82}.activation-workspace__actions{display:flex;align-items:center;gap:.55rem}.activation-workspace__actions .btn{display:inline-flex;align-items:center;gap:.4rem;border-color:rgba(255,255,255,.5)}.activation-workspace__close{display:grid;width:40px;height:40px;padding:0;color:#fff;font-size:1.6rem;place-items:center;border:1px solid rgba(255,255,255,.3);border-radius:12px;background:rgba(255,255,255,.1)}.activation-workspace__close:hover{background:rgba(255,255,255,.2)}.activation-workspace__close:disabled{cursor:not-allowed;opacity:.55}.activation-workspace__loading{display:grid;flex:1;gap:.8rem;color:#64718a;place-content:center;text-align:center}.activation-workspace__body{display:grid;min-height:0;flex:1;grid-template-columns:330px minmax(0,1fr)}.activation-workspace__rail{overflow:auto;padding:1rem;border-right:1px solid var(--aw-line);background:#eef2f8}.activation-workspace__context{padding:.9rem;margin-top:.85rem;border:1px solid var(--aw-line);border-radius:16px;background:#fff}.activation-workspace__context>span{color:#6f7c93;font-size:.62rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.activation-workspace__context dl{display:grid;gap:.55rem;margin:.7rem 0 0}.activation-workspace__context dl>div{display:flex;justify-content:space-between;gap:.8rem}.activation-workspace__context dt,.activation-workspace__context dd{margin:0;font-size:.68rem}.activation-workspace__context dt{color:#7c879a;font-weight:500}.activation-workspace__context dd{color:#34415a;font-weight:700;text-align:right}.activation-workspace__main{overflow:auto;padding:1rem 1.2rem 2rem}.activation-stage,.activation-parts{border:1px solid var(--aw-line);border-radius:20px;background:#fff;box-shadow:0 12px 30px rgba(37,48,80,.06)}.activation-stage{padding:1.15rem}.activation-stage.is-current{border-top:4px solid var(--aw-indigo)}.activation-stage__heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.activation-stage__heading>div>span,.activation-parts>header div>span{color:var(--aw-indigo);font-size:.64rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.activation-stage__heading h3,.activation-parts h3{margin:.18rem 0;color:var(--aw-navy);font-size:1.05rem;font-weight:800}.activation-stage__heading p{max-width:740px;margin:.3rem 0 0;color:#69758b;font-size:.73rem;line-height:1.55}.activation-stage__status{display:inline-flex;align-items:center;gap:.3rem;padding:.42rem .65rem;color:#526077;font-size:.63rem;font-weight:800;border-radius:999px;background:#eef1f5;white-space:nowrap}.activation-stage__status.is-in_progress{color:#344cb9;background:#e9edff}.activation-stage__status.is-completed{color:#1d725e;background:#e6f6f1}.activation-stage__status.is-blocked{color:#a63c43;background:#fff0f1}.activation-stage__facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;margin:1rem 0}.activation-stage__facts>div{display:flex;min-width:0;align-items:center;gap:.55rem;padding:.65rem;border:1px solid #e6eaf1;border-radius:13px;background:#f9fafc}.activation-stage__facts i{color:var(--aw-indigo);font-size:1.05rem}.activation-stage__facts span{display:block;min-width:0;color:#8490a3;font-size:.58rem;text-transform:uppercase}.activation-stage__facts b{display:block;overflow:hidden;margin-top:.1rem;color:#34415a;font-size:.68rem;text-overflow:ellipsis;text-transform:none;white-space:nowrap}.activation-stage__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.activation-stage__grid>div{padding:.75rem;border:1px solid #e8ebf2;border-radius:14px;background:#fbfcfe}.activation-stage .form-label,.activation-part .form-label{color:#5d6980;font-size:.66rem;font-weight:700}.activation-stage .form-control,.activation-stage .form-select,.activation-part .form-control,.activation-part .form-select{font-size:.72rem;border-color:#dce2ec}.activation-stage__controls{display:grid!important;align-content:start;grid-template-columns:1fr 1fr;gap:.4rem .65rem}.activation-stage__controls .input-group{grid-column:2}.activation-stage__buttons{display:flex;align-items:center;justify-content:flex-end;gap:.55rem;padding-top:1rem}.activation-stage__buttons>span{display:flex;margin-right:auto;color:#a06a23;font-size:.66rem;gap:.3rem}.activation-stage__buttons .btn,.activation-part__actions .btn{display:inline-flex;align-items:center;gap:.32rem}.activation-parts{padding:1.1rem;margin-top:1rem}.activation-parts>header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.activation-parts>header>b{padding:.38rem .62rem;color:#53617b;font-size:.65rem;border-radius:999px;background:#eef1f5}.activation-parts__list{display:grid;gap:.75rem;margin-top:.9rem}.activation-part{overflow:hidden;border:1px solid #e1e6ee;border-left:4px solid #c8d0de;border-radius:15px;background:#fff}.activation-part.is-completed{border-left-color:var(--aw-teal)}.activation-part.is-in_progress{border-left-color:var(--aw-indigo)}.activation-part.is-required:not(.is-completed){border-left-color:#d69b3e}.activation-part__top{display:grid;grid-template-columns:40px minmax(0,1fr) auto;align-items:start;gap:.7rem;padding:.8rem}.activation-part__icon{display:grid;width:40px;height:40px;color:var(--aw-indigo);font-size:1.1rem;place-items:center;border-radius:12px;background:#edf0ff}.activation-part__top>div>span{display:flex;align-items:center;gap:.4rem;color:#778399;font-size:.6rem;font-weight:800;text-transform:uppercase}.activation-part__top em{padding:.18rem .35rem;color:#945e16;font-size:.52rem;font-style:normal;border-radius:999px;background:#fff2d8}.activation-part__top h4{margin:.16rem 0;color:#31405c;font-size:.82rem;font-weight:800}.activation-part__top p{margin:0;color:#788398;font-size:.68rem;line-height:1.45}.activation-part__state{padding:.34rem .52rem;color:#5f6d84;font-size:.58rem;font-weight:800;border-radius:999px;background:#edf0f4}.activation-part__details{border-top:1px solid #edf0f4;background:#fafbfe}.activation-part__details summary{padding:.65rem .8rem;color:#516079;font-size:.66rem;font-weight:750;cursor:pointer}.activation-part__form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;padding:0 .8rem .8rem}.activation-part__wide{grid-column:1/-1}.activation-part__actions{display:flex;justify-content:flex-end;gap:.45rem;padding:0 .8rem .8rem}.activation-parts__empty{display:grid;gap:.4rem;padding:2rem;margin:.9rem 0 0;color:#8190a5;font-size:.72rem;place-items:center;border:1px dashed #ccd4e2;border-radius:14px}.activation-parts__empty i{font-size:1.4rem}
@media(max-width:991.98px){.activation-workspace{padding:0}.activation-workspace__shell{border-radius:0}.activation-workspace__body{grid-template-columns:275px minmax(0,1fr)}.activation-stage__facts{grid-template-columns:repeat(2,minmax(0,1fr))}.activation-stage__grid{grid-template-columns:1fr}}
@media(max-width:767.98px){.activation-workspace__header{min-height:76px;padding:.8rem}.activation-workspace__mark{display:none}.activation-workspace__identity p{display:none}.activation-workspace__actions .btn span{display:none}.activation-workspace__body{display:block;overflow:auto}.activation-workspace__rail,.activation-workspace__main{overflow:visible}.activation-workspace__rail{padding:.75rem;border:0}.activation-workspace__context{display:none}.activation-workspace__main{padding:.75rem  .75rem 1.5rem}.activation-stage,.activation-parts{border-radius:16px}.activation-stage__heading{display:block}.activation-stage__status{margin-top:.7rem}.activation-stage__facts{grid-template-columns:1fr 1fr}.activation-stage__buttons{flex-wrap:wrap}.activation-stage__buttons>span{width:100%}.activation-part__top{grid-template-columns:36px minmax(0,1fr)}.activation-part__state{grid-column:2;justify-self:start}.activation-part__form{grid-template-columns:1fr}.activation-part__wide{grid-column:auto}}
@media(max-width:419.98px){.activation-workspace__identity h2{font-size:.9rem}.activation-stage__facts{grid-template-columns:1fr}.activation-stage__controls{grid-template-columns:1fr}.activation-stage__controls .input-group{grid-column:auto}.activation-part__actions{flex-direction:column}.activation-part__actions .btn{justify-content:center}}
@media(prefers-reduced-motion:reduce){.activation-workspace{backdrop-filter:none}}
.activation-stage__justification{grid-column:1/-1;border-color:#efd3a8!important;background:#fffaf1!important}
.activation-deadline-warning{display:flex;align-items:flex-start;gap:.55rem;padding:.68rem .75rem;margin:0 0 1rem;color:#7a501b;border:1px solid #ebcf9f;border-radius:12px;background:#fff8e9}.activation-deadline-warning>i{flex:0 0 auto;margin-top:.08rem;font-size:1.05rem}.activation-deadline-warning span,.activation-deadline-warning b{display:block;font-size:.64rem;line-height:1.45}.activation-deadline-warning b{margin-bottom:.08rem;color:#653d0e}
.activation-stage__deadline-help,.activation-stage__extension-help{grid-column:1/-1;color:#7a879b;font-size:.58rem;line-height:1.4}.activation-stage__approval{display:flex!important;grid-column:1/-1;align-items:flex-start;gap:.5rem;padding:.55rem .6rem;color:#365f55;border:1px solid #cce2db;border-radius:10px;background:#f2faf7}.activation-stage__approval input{width:16px;height:16px;flex:0 0 16px;margin-top:.08rem;accent-color:var(--aw-teal)}.activation-stage__approval span,.activation-stage__approval b{display:block;font-size:.61rem;line-height:1.4}.activation-stage__approval b{color:#275248}.activation-stage__controls .input-group-text{color:#526178;font-size:.66rem;background:#eef2f7}
.activation-part__condition{display:flex!important;align-items:flex-start;gap:.55rem;padding:.65rem;color:#84591f;border:1px solid #eed5ad;border-radius:10px;background:#fff9ee}.activation-part__condition input{margin-top:.15rem}.activation-part__condition i{font-size:1.05rem}.activation-part__condition span,.activation-part__condition b{display:block;font-size:.63rem}.activation-part__condition b{margin-bottom:.12rem;color:#704618}
.activation-part__condition.is-not-applicable{color:#4f5f78;border-color:#d5ddea;background:#f5f7fb}.activation-part__condition.is-not-applicable b{color:#33445f}
.activation-workspace__legacy{display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:.8rem;padding:.85rem 1rem;margin-bottom:1rem;color:#654817;border:1px solid #e8c982;border-radius:16px;background:linear-gradient(110deg,#fff8e8,#fffdf7);box-shadow:0 8px 22px rgba(111,78,20,.07)}.activation-workspace__legacy>span{display:grid;width:42px;height:42px;color:#8c5f12;font-size:1.2rem;place-items:center;border-radius:12px;background:#ffedbe}.activation-workspace__legacy h3{margin:0;color:#61440f;font-size:.82rem}.activation-workspace__legacy p{max-width:760px;margin:.16rem 0 0;font-size:.67rem;line-height:1.5}.activation-workspace__legacy .btn{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}.activation-workspace__legacy small{font-size:.62rem;font-weight:700}
.activation-stage__rules{padding:.85rem;margin:0 0 1rem;border:1px solid #dce3f5;border-radius:15px;background:linear-gradient(145deg,#f8faff,#fff)}.activation-stage__rules>header{display:flex;align-items:flex-start;gap:.6rem}.activation-stage__rules>header>span{display:grid;width:32px;height:32px;flex:0 0 32px;color:#fff;place-items:center;border-radius:10px;background:linear-gradient(135deg,var(--aw-navy),var(--aw-indigo))}.activation-stage__rules h4{margin:0;color:var(--aw-navy);font-size:.79rem}.activation-stage__rules>header p{margin:.14rem 0 0;color:#69768d;font-size:.65rem;line-height:1.45}.activation-stage__criteria-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-top:.7rem}.activation-stage__criteria-grid>article{display:grid;align-content:start;gap:.4rem;padding:.65rem;border:1px solid #dbe2ee;border-radius:12px;background:#fff}.activation-stage__criteria-grid>article.is-any{border-color:#cfe4df;background:#fbfffe}.activation-stage__criteria-title{display:flex;align-items:center;gap:.45rem;padding-bottom:.4rem;border-bottom:1px solid #edf0f5}.activation-stage__criteria-title>i{color:var(--aw-indigo);font-size:1rem}.activation-stage__criteria-title span,.activation-stage__criteria-title b,.activation-stage__criteria-title small{display:block}.activation-stage__criteria-title b{color:#3e4c65;font-size:.65rem}.activation-stage__criteria-title small{margin-top:.08rem;color:#8390a5;font-size:.55rem}.activation-stage__criteria-grid label{display:flex;align-items:flex-start;gap:.45rem;margin:0;color:#536079;font-size:.64rem;line-height:1.4}.activation-stage__criteria-grid input{width:16px;height:16px;flex:0 0 16px;margin-top:.05rem;accent-color:var(--aw-indigo)}.activation-stage__rule-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.7rem}.activation-stage__rule-tags span{display:inline-flex;align-items:center;gap:.28rem;padding:.3rem .48rem;color:#2d685c;font-size:.58rem;font-weight:750;border-radius:999px;background:#e9f6f2}.activation-stage__extra-rules{display:grid;gap:.35rem;padding-top:.65rem;margin:.65rem 0 0;border-top:1px dashed #d6ddea}.activation-stage__extra-rules>div{display:grid;grid-template-columns:minmax(110px,.35fr) minmax(0,1fr);gap:.6rem}.activation-stage__extra-rules dt,.activation-stage__extra-rules dd{margin:0;font-size:.61rem}.activation-stage__extra-rules dt{color:#50607a;font-weight:800}.activation-stage__extra-rules dd{color:#707d91}.activation-stage__readonly{display:flex;align-items:center;gap:.4rem;padding:.55rem .65rem;margin:0 0 .8rem;color:#69768a;font-size:.64rem;border:1px solid #e1e5ec;border-radius:10px;background:#f7f8fa}.activation-stage__readonly i{color:#7e8ba0;font-size:.9rem}
.activation-part__condition-preview{display:flex;align-items:center;flex-wrap:wrap;gap:.25rem;padding:.42rem .5rem;margin-top:.45rem!important;color:#70511e!important;border:1px solid #efd8ad;border-radius:9px;background:#fff9ed}.activation-part__condition-preview i{font-size:.85rem}.activation-part__condition-preview b{font-size:.6rem}
.activation-part__deadline-warning{display:flex;align-items:flex-start;flex-wrap:wrap;gap:.25rem;padding:.42rem .5rem;margin-top:.4rem!important;color:#8a5117!important;border:1px solid #edcf9d;border-radius:9px;background:#fff8e9}.activation-part__deadline-warning i{font-size:.85rem}.activation-part__deadline-warning b{font-size:.6rem}
@media(max-width:767.98px){.activation-workspace__legacy{grid-template-columns:36px minmax(0,1fr)}.activation-workspace__legacy>span{width:36px;height:36px}.activation-workspace__legacy .btn,.activation-workspace__legacy>small{grid-column:1/-1;justify-self:stretch;justify-content:center}.activation-stage__criteria-grid{grid-template-columns:1fr}.activation-stage__extra-rules>div{grid-template-columns:1fr;gap:.1rem}}
</style>
