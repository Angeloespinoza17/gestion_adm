<script>
import { convivenciaProtocolRuntimeLabel } from "../protocol-runtime-labels";
import ConvivenciaRemoteSelect from "../ui/convivencia-remote-select.vue";

const deepClone = (value) => JSON.parse(JSON.stringify(value ?? null));
const toLocalDateTime = (value) => value ? String(value).replace(" ", "T").slice(0, 16) : "";
let stepSequence = 0;

const deadlineUnits = [
  ["hours", "Horas"],
  ["calendar_days", "Días corridos"],
  ["business_days", "Días hábiles"],
  ["school_days", "Días hábiles escolares"],
  ["external", "Plazo externo"],
  ["external_defined", "Definido por organismo"],
];

const deadlineAnchors = [
  ["activation_started", "Activación del protocolo"],
  ["step_started", "Inicio de esta etapa"],
  ["previous_step_completed", "Término de la etapa anterior"],
];

const stepTypes = [
  ["activation", "Activación"],
  ["safeguard", "Resguardo inmediato"],
  ["notification", "Notificación"],
  ["interview", "Entrevistas"],
  ["assessment", "Evaluación inicial"],
  ["investigation", "Investigación"],
  ["decision", "Resolución"],
  ["appeal", "Apelación"],
  ["follow_up", "Seguimiento"],
  ["closure", "Cierre"],
  ["other", "Otra etapa"],
];

const blankStep = (order = 1) => {
  stepSequence += 1;
  return {
    _key: `step-${Date.now()}-${stepSequence}`,
    id: null,
    step_order: order,
    code: `paso_${order}`,
    stage_name: "",
    description: "",
    step_type: order === 1 ? "activation" : "other",
    responsible_label: "Encargado/a de Convivencia Escolar",
    due_days: null,
    deadline_value: 1,
    deadline_unit: "business_days",
    deadline_anchor: order === 1 ? "activation_started" : "previous_step_completed",
    can_extend: false,
    extension_value: null,
    extension_unit: "business_days",
    completion_rule_text: "",
    completion_rule_text_initial: "",
    completion_rule_raw: {},
    completion_all_json: "",
    completion_all_json_initial: "",
    completion_any_json: "",
    completion_any_json_initial: "",
    completion_parts_mode: "inherit",
    completion_parts_mode_initial: "inherit",
    completion_notes_mode: "inherit",
    completion_notes_mode_initial: "inherit",
    active: true,
    required_documents: "",
    minimal_actions: "",
    safeguard_measures: "",
    metadata_json: "{}",
    part_links: [],
  };
};

const blankProtocol = () => ({
  id: null,
  expected_revision: null,
  code: "",
  version_label: "2026",
  name: "",
  protocol_type_item_id: null,
  criticality_item_id: null,
  regulatory_source: "Reglamento Interno de Convivencia Escolar 2026",
  education_scope: "todos_los_niveles",
  legal_reference: "",
  source_reference: "",
  effective_from: "",
  effective_to: "",
  published_at: "",
  description: "",
  required_documents: "",
  safeguard_measures: "",
  minimal_actions: "",
  default_due_days: 5,
  status: "borrador",
  is_sensitive: true,
  metadata: {},
  metadata_json: "{}",
  general_part_links: [],
  steps: [blankStep(1)],
});

export default {
  components: { ConvivenciaRemoteSelect },
  props: {
    protocol: { type: Object, default: null },
    parts: { type: Array, default: () => [] },
    catalogs: { type: Object, default: () => ({}) },
    saving: { type: Boolean, default: false },
  },
  emits: ["save", "cancel"],
  data() {
    return {
      form: blankProtocol(),
      section: "general",
      selectedStepKey: null,
      validationAttempted: false,
      jsonError: "",
      partSearch: "",
      partCategory: "",
      generalPartSearch: "",
      generalPartCategory: "",
      remoteParts: [],
      remoteGeneralPartId: null,
      remoteStepPartId: null,
      deadlineUnits,
      deadlineAnchors,
      stepTypes,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    selectedStep() {
      return this.form.steps.find((step) => step._key === this.selectedStepKey) || this.form.steps[0] || null;
    },
    protocolTypeOptions() {
      return this.catalogItems("protocol_type");
    },
    criticalityOptions() {
      return this.catalogItems("criticality");
    },
    activeParts() {
      const records = new Map();
      [...(this.parts || []), ...this.remoteParts].forEach((part) => {
        if (part?.id && part.active !== false) records.set(Number(part.id), part);
      });
      return [...records.values()];
    },
    partCategories() {
      const categories = new Map();
      this.activeParts.forEach((part) => {
        const key = part.category || "other";
        categories.set(key, this.partCategoryLabel(key));
      });
      return [...categories.entries()].map(([value, text]) => ({ value, text }));
    },
    filteredParts() {
      const search = this.partSearch.trim().toLocaleLowerCase("es-CL");
      return this.activeParts.filter((part) => {
        if (this.partCategory && part.category !== this.partCategory) return false;
        if (!search) return true;
        return [part.code, part.title, part.description]
          .filter(Boolean)
          .some((value) => String(value).toLocaleLowerCase("es-CL").includes(search));
      });
    },
    groupedParts() {
      return this.filteredParts.reduce((result, part) => {
        const category = part.category || "other";
        result[category] ||= [];
        result[category].push(part);
        return result;
      }, {});
    },
    filteredGeneralParts() {
      return this.filterParts(this.generalPartSearch, this.generalPartCategory);
    },
    groupedGeneralParts() {
      return this.filteredGeneralParts.reduce((result, part) => {
        const category = part.category || "other";
        result[category] ||= [];
        result[category].push(part);
        return result;
      }, {});
    },
    linkedPartsCount() {
      return this.form.general_part_links.length + this.form.steps.reduce((total, step) => total + step.part_links.length, 0);
    },
    warnings() {
      const value = this.form.metadata?.warnings;
      if (Array.isArray(value)) return value.filter(Boolean);
      return value ? [value] : [];
    },
    validationErrors() {
      const errors = [];
      if (this.jsonError) errors.push(this.jsonError);
      if (!this.form.code?.trim()) errors.push("El protocolo requiere un código.");
      if (!this.form.name?.trim()) errors.push("El protocolo requiere un nombre.");
      if (!this.form.steps.length) errors.push("Agrega al menos una etapa.");
      const stepCodes = this.form.steps.map((step) => step.code?.trim()).filter(Boolean);
      if (new Set(stepCodes).size !== stepCodes.length) errors.push("Los códigos de las etapas no pueden repetirse.");
      this.form.steps.forEach((step, index) => {
        if (!step.code?.trim()) errors.push(`La etapa ${index + 1} requiere un código.`);
        if (!step.stage_name?.trim()) errors.push(`La etapa ${index + 1} requiere un nombre.`);
        if (step.can_extend && !step.extension_value) errors.push(`La etapa ${index + 1} permite prórroga, pero no indica su duración.`);
        const allError = this.completionCriteriaIssue(step.completion_all_json, step.completion_all_json_initial, `La regla “todas” de la etapa ${index + 1}`);
        const anyError = this.completionCriteriaIssue(step.completion_any_json, step.completion_any_json_initial, `La regla “alguna” de la etapa ${index + 1}`);
        if (allError) errors.push(allError);
        if (anyError) errors.push(anyError);
        step.part_links.forEach((link, linkIndex) => {
          const prefix = `El vínculo ${linkIndex + 1} de la etapa ${index + 1}`;
          const conditionError = this.jsonObjectIssue(link.condition_json, `${prefix}: la condición`);
          const configurationError = this.jsonObjectIssue(link.configuration_json, `${prefix}: la configuración`);
          if (conditionError) errors.push(conditionError);
          if (configurationError) errors.push(configurationError);
        });
      });
      this.form.general_part_links.forEach((link, index) => {
        const prefix = `El vínculo general ${index + 1}`;
        const conditionError = this.jsonObjectIssue(link.condition_json, `${prefix}: la condición`);
        const configurationError = this.jsonObjectIssue(link.configuration_json, `${prefix}: la configuración`);
        if (conditionError) errors.push(conditionError);
        if (configurationError) errors.push(configurationError);
      });
      if (this.form.effective_from && this.form.effective_to && this.form.effective_to < this.form.effective_from) {
        errors.push("La fecha final de vigencia no puede ser anterior a la inicial.");
      }
      return errors;
    },
  },
  watch: {
    protocol: {
      immediate: true,
      deep: true,
      handler(value) {
        this.hydrate(value);
      },
    },
  },
  methods: {
    catalogItems(group) {
      const source = this.catalogs?.catalogs?.[group] || [];
      return source.map((item) => ({ value: item.id ?? item.value, text: item.name || item.label }));
    },
    partCategoryLabel(value) {
      return ({
        action: "Acciones", sanction: "Sanciones", protective_measure: "Medidas protectoras",
        formative_measure: "Medidas formativas", restorative_measure: "Medidas restaurativas",
        interview: "Entrevistas", communication: "Comunicaciones", notification: "Notificaciones",
        document: "Documentos", evidence: "Evidencias", internal_referral: "Derivaciones internas",
        external_referral: "Derivaciones externas", external_report: "Denuncias externas",
        appeal: "Apelaciones", follow_up: "Seguimientos", closure: "Cierres",
        special_rule: "Reglas especiales", other: "Otras partes",
      })[value] || String(value || "Otra parte").replaceAll("_", " ");
    },
    partIcon(value) {
      return ({
        sanction: "bx-error-circle", protective_measure: "bx-shield-quarter", formative_measure: "bx-book-heart",
        restorative_measure: "bx-wrench", document: "bx-file", evidence: "bx-check-square",
        interview: "bx-conversation", communication: "bx-message-detail", notification: "bx-bell",
        external_report: "bx-export", special_rule: "bx-git-branch", closure: "bx-check-circle",
      })[value] || "bx-grid-alt";
    },
    filterParts(searchValue = "", category = "") {
      const search = String(searchValue || "").trim().toLocaleLowerCase("es-CL");
      return this.activeParts.filter((part) => {
        if (category && part.category !== category) return false;
        if (!search) return true;
        return [part.code, part.title, part.description]
          .filter(Boolean)
          .some((value) => String(value).toLocaleLowerCase("es-CL").includes(search));
      });
    },
    normalizeLink(link) {
      const part = link?.part || link?.protocol_part || link?.protocolPart || {};
      const condition = deepClone(link?.condition || link?.pivot?.condition || null);
      const configuration = deepClone(link?.configuration || link?.pivot?.configuration || null);
      return {
        id: link?.id || null,
        protocol_step_id: link?.protocol_step_id || link?.step?.id || null,
        step_code: link?.step_code || link?.step?.code || null,
        protocol_part_id: Number(link?.protocol_part_id || part.id || link?.part_id),
        sort_order: Number(link?.sort_order || link?.pivot?.sort_order || 1),
        is_required: Boolean(link?.is_required ?? link?.required ?? link?.pivot?.is_required ?? false),
        condition,
        configuration,
        condition_json: JSON.stringify(condition || {}, null, 2),
        configuration_json: JSON.stringify(configuration || {}, null, 2),
      };
    },
    normalizeStep(step, index, protocolLinks) {
      const code = step.code || `paso_${index + 1}`;
      const directLinks = step.part_links || step.partLinks || step.parts || [];
      const inheritedLinks = protocolLinks.filter((link) =>
        (link.protocol_step_id && Number(link.protocol_step_id) === Number(step.id))
        || (link.step_code && link.step_code === code));
      const links = [...directLinks, ...inheritedLinks].map(this.normalizeLink).filter((link) => link.protocol_part_id);
      const unique = [...new Map(links.map((link) => [link.protocol_part_id, link])).values()];
      const completionRule = step.completion_rule || {};
      const summary = Object.prototype.hasOwnProperty.call(completionRule, "summary") ? (completionRule.summary || "") : "";
      const allJson = Object.prototype.hasOwnProperty.call(completionRule, "all") ? JSON.stringify(completionRule.all, null, 2) : "";
      const anyJson = Object.prototype.hasOwnProperty.call(completionRule, "any") ? JSON.stringify(completionRule.any, null, 2) : "";
      const partsMode = Object.prototype.hasOwnProperty.call(completionRule, "requires_all_parts")
        ? (completionRule.requires_all_parts ? "required" : "optional")
        : "inherit";
      const hasNotesRule = Object.prototype.hasOwnProperty.call(completionRule, "requires_notes")
        || Object.prototype.hasOwnProperty.call(completionRule, "requires_completion_note");
      const notesMode = hasNotesRule
        ? ((completionRule.requires_notes ?? completionRule.requires_completion_note) ? "required" : "optional")
        : "inherit";
      return {
        ...blankStep(index + 1),
        id: step.id || null,
        _key: `step-${step.id || code}-${++stepSequence}`,
        step_order: Number(step.step_order ?? step.order ?? index + 1),
        code,
        stage_name: step.stage_name || step.name || "",
        description: step.description || "",
        step_type: step.step_type || "other",
        responsible_label: step.responsible_label || "",
        due_days: step.due_days || null,
        deadline_value: step.deadline_value || step.due_days || null,
        deadline_unit: step.deadline_unit || (step.due_days ? "calendar_days" : "business_days"),
        deadline_anchor: step.deadline_anchor || (index === 0 ? "activation_started" : "previous_step_completed"),
        can_extend: Boolean(step.can_extend),
        extension_value: step.extension_value || null,
        extension_unit: step.extension_unit || "business_days",
        completion_rule_text: summary,
        completion_rule_text_initial: summary,
        completion_rule_raw: deepClone(completionRule),
        completion_all_json: allJson,
        completion_all_json_initial: allJson,
        completion_any_json: anyJson,
        completion_any_json_initial: anyJson,
        completion_parts_mode: partsMode,
        completion_parts_mode_initial: partsMode,
        completion_notes_mode: notesMode,
        completion_notes_mode_initial: notesMode,
        active: step.active !== false,
        required_documents: step.required_documents || "",
        minimal_actions: step.minimal_actions || "",
        safeguard_measures: step.safeguard_measures || "",
        metadata_json: JSON.stringify(step.metadata || {}, null, 2),
        part_links: unique,
      };
    },
    hydrate(source) {
      this.validationAttempted = false;
      this.jsonError = "";
      this.remoteParts = [];
      this.remoteGeneralPartId = null;
      this.remoteStepPartId = null;
      if (!source) {
        this.form = blankProtocol();
        this.selectedStepKey = this.form.steps[0]._key;
        this.section = "general";
        return;
      }
      const protocol = deepClone(source);
      const protocolLinks = (protocol.part_links || protocol.partLinks || protocol.parts || []).map(this.normalizeLink).filter((link) => link.protocol_part_id);
      const steps = (protocol.steps || []).map((step, index) => this.normalizeStep(step, index, protocolLinks));
      const stepIds = new Set((protocol.steps || []).map((step) => Number(step.id)).filter(Boolean));
      const stepCodes = new Set((protocol.steps || []).map((step) => step.code).filter(Boolean));
      const generalLinks = protocolLinks.filter((link) =>
        (!link.protocol_step_id || !stepIds.has(Number(link.protocol_step_id)))
        && (!link.step_code || !stepCodes.has(link.step_code)));
      this.form = {
        ...blankProtocol(),
        ...protocol,
        metadata: protocol.metadata || {},
        metadata_json: JSON.stringify(protocol.metadata || {}, null, 2),
        expected_revision: protocol.revision || protocol.expected_revision || null,
        effective_from: protocol.effective_from ? String(protocol.effective_from).slice(0, 10) : "",
        effective_to: protocol.effective_to ? String(protocol.effective_to).slice(0, 10) : "",
        published_at: toLocalDateTime(protocol.published_at),
        general_part_links: generalLinks,
        steps: steps.length ? steps : [blankStep(1)],
      };
      this.selectedStepKey = this.form.steps[0]._key;
      this.section = "general";
    },
    addStep() {
      const step = blankStep(this.form.steps.length + 1);
      this.form.steps.push(step);
      this.renumberSteps();
      this.selectedStepKey = step._key;
      this.section = "steps";
    },
    removeStep(step) {
      if (this.form.steps.length === 1) return;
      const index = this.form.steps.findIndex((item) => item._key === step._key);
      if (index < 0) return;
      this.form.steps.splice(index, 1);
      this.renumberSteps();
      this.selectedStepKey = this.form.steps[Math.min(index, this.form.steps.length - 1)]?._key || null;
    },
    moveStep(step, direction) {
      const index = this.form.steps.findIndex((item) => item._key === step._key);
      const target = index + direction;
      if (index < 0 || target < 0 || target >= this.form.steps.length) return;
      const [moved] = this.form.steps.splice(index, 1);
      this.form.steps.splice(target, 0, moved);
      this.renumberSteps();
    },
    renumberSteps() {
      this.form.steps.forEach((step, index) => { step.step_order = index + 1; });
    },
    linkFor(step, partId) {
      return (step?.part_links || []).find((link) => Number(link.protocol_part_id) === Number(partId));
    },
    isLinked(step, partId) {
      return Boolean(this.linkFor(step, partId));
    },
    generalLinkFor(partId) {
      return this.form.general_part_links.find((link) => Number(link.protocol_part_id) === Number(partId));
    },
    isGeneralLinked(partId) {
      return Boolean(this.generalLinkFor(partId));
    },
    newPartLink(part, step = null) {
      return {
        id: null,
        protocol_step_id: step?.id || null,
        step_code: step?.code || null,
        protocol_part_id: part.id,
        sort_order: (step?.part_links || this.form.general_part_links).length + 1,
        is_required: false,
        condition: null,
        configuration: null,
        condition_json: "{}",
        configuration_json: "{}",
      };
    },
    togglePart(step, part) {
      const index = step.part_links.findIndex((link) => Number(link.protocol_part_id) === Number(part.id));
      if (index >= 0) step.part_links.splice(index, 1);
      else step.part_links.push(this.newPartLink(part, step));
    },
    toggleGeneralPart(part) {
      const index = this.form.general_part_links.findIndex((link) => Number(link.protocol_part_id) === Number(part.id));
      if (index >= 0) this.form.general_part_links.splice(index, 1);
      else this.form.general_part_links.push(this.newPartLink(part));
    },
    remotePartFromOption(option) {
      if (!option || typeof option !== "object") return null;
      const id = Number(option.value || option.id);
      if (!id) return null;
      const label = String(option.label || "").trim();
      const separator = label.indexOf(" · ");
      return {
        id,
        code: separator >= 0 ? label.slice(0, separator) : "",
        title: separator >= 0 ? label.slice(separator + 3) : (label || `Parte #${id}`),
        category: option.secondary || "other",
        active: true,
      };
    },
    linkRemotePart(option, scope) {
      const part = this.remotePartFromOption(option);
      if (!part) return;
      const index = this.remoteParts.findIndex((item) => Number(item.id) === Number(part.id));
      if (index >= 0) this.remoteParts.splice(index, 1, { ...this.remoteParts[index], ...part });
      else this.remoteParts.push(part);
      if (scope === "general") {
        if (!this.isGeneralLinked(part.id)) this.toggleGeneralPart(part);
        this.$nextTick(() => { this.remoteGeneralPartId = null; });
        return;
      }
      if (this.selectedStep && !this.isLinked(this.selectedStep, part.id)) this.togglePart(this.selectedStep, part);
      this.$nextTick(() => { this.remoteStepPartId = null; });
    },
    changeStepCode(step) {
      step.part_links.forEach((link) => { link.step_code = step.code; });
    },
    parseJson(value, label) {
      try {
        const parsed = value?.trim() ? JSON.parse(value) : {};
        if (!parsed || Array.isArray(parsed) || typeof parsed !== "object") throw new Error();
        return parsed;
      } catch {
        throw new Error(`${label} debe contener un objeto JSON válido.`);
      }
    },
    parseLinkJson(value, label) {
      const parsed = this.parseJson(value, label);
      return Object.keys(parsed).length ? parsed : null;
    },
    jsonObjectIssue(value, label) {
      try {
        this.parseJson(value, label);
        return "";
      } catch (error) {
        return error.message;
      }
    },
    parseCompletionCriteria(value, label) {
      if (!String(value || "").trim()) return { present: false, value: null };
      try {
        const parsed = JSON.parse(value);
        if (!parsed || (typeof parsed !== "object")) throw new Error();
        return { present: true, value: parsed };
      } catch {
        throw new Error(`${label} debe ser una lista u objeto JSON válido.`);
      }
    },
    completionCriteriaIssue(value, initialValue, label) {
      if (value === initialValue) return "";
      try {
        this.parseCompletionCriteria(value, label);
        return "";
      } catch (error) {
        return error.message;
      }
    },
    criteriaPreview(value) {
      try {
        const parsed = this.parseCompletionCriteria(value, "El criterio");
        if (!parsed.present) return [];
        if (Array.isArray(parsed.value)) return parsed.value;
        return Object.entries(parsed.value).map(([key, item]) => ({ key, value: item }));
      } catch {
        return [];
      }
    },
    criteriaPreviewLabel(criterion) {
      if (typeof criterion === "string" || typeof criterion === "number") {
        return convivenciaProtocolRuntimeLabel(criterion, String(criterion).replaceAll("_", " "));
      }
      if (!criterion || typeof criterion !== "object") return "Criterio";
      if (criterion.label || criterion.name || criterion.title || criterion.code) {
        return criterion.label || criterion.name || criterion.title || criterion.code;
      }
      if (criterion.field) {
        return [criterion.field, criterion.operator, criterion.value]
          .filter((item) => item !== undefined && item !== null && item !== "")
          .map((item) => typeof item === "string" ? convivenciaProtocolRuntimeLabel(item, item.replaceAll("_", " ")) : JSON.stringify(item))
          .join(" · ");
      }
      if (criterion.key !== undefined && criterion.value !== undefined) {
        const key = convivenciaProtocolRuntimeLabel(criterion.key, String(criterion.key).replaceAll("_", " "));
        const value = typeof criterion.value === "object"
          ? JSON.stringify(criterion.value)
          : convivenciaProtocolRuntimeLabel(criterion.value, criterion.value);
        return `${key}: ${value}`;
      }
      return JSON.stringify(criterion);
    },
    buildCompletionRule(step, index) {
      const rule = deepClone(step.completion_rule_raw || {});
      if (step.completion_rule_text !== step.completion_rule_text_initial) {
        if (step.completion_rule_text?.trim()) rule.summary = step.completion_rule_text.trim();
        else delete rule.summary;
      }
      if (step.completion_all_json !== step.completion_all_json_initial) {
        const parsed = this.parseCompletionCriteria(step.completion_all_json, `La regla “todas” de la etapa ${index + 1}`);
        if (parsed.present) rule.all = parsed.value;
        else delete rule.all;
      }
      if (step.completion_any_json !== step.completion_any_json_initial) {
        const parsed = this.parseCompletionCriteria(step.completion_any_json, `La regla “alguna” de la etapa ${index + 1}`);
        if (parsed.present) rule.any = parsed.value;
        else delete rule.any;
      }
      if (step.completion_parts_mode !== step.completion_parts_mode_initial) {
        delete rule.requires_all_parts;
        if (step.completion_parts_mode !== "inherit") rule.requires_all_parts = step.completion_parts_mode === "required";
      }
      if (step.completion_notes_mode !== step.completion_notes_mode_initial) {
        delete rule.requires_notes;
        delete rule.requires_completion_note;
        if (step.completion_notes_mode !== "inherit") rule.requires_notes = step.completion_notes_mode === "required";
      }
      return rule;
    },
    buildPayload() {
      const metadata = this.parseJson(this.form.metadata_json, "La configuración general");
      const steps = this.form.steps.map((step, index) => ({
        id: step.id || undefined,
        step_order: index + 1,
        code: step.code,
        stage_name: step.stage_name,
        description: step.description || null,
        step_type: step.step_type || null,
        responsible_label: step.responsible_label || null,
        due_days: step.deadline_unit === "calendar_days" && step.deadline_value ? Number(step.deadline_value) : null,
        deadline_value: step.deadline_value ? Number(step.deadline_value) : null,
        deadline_unit: step.deadline_value ? step.deadline_unit : null,
        deadline_anchor: step.deadline_value ? step.deadline_anchor : null,
        can_extend: Boolean(step.can_extend),
        extension_value: step.can_extend && step.extension_value ? Number(step.extension_value) : null,
        extension_unit: step.can_extend && step.extension_value ? step.extension_unit : null,
        completion_rule: this.buildCompletionRule(step, index),
        active: Boolean(step.active),
        metadata: this.parseJson(step.metadata_json, `Los metadatos de la etapa ${index + 1}`),
        required_documents: step.required_documents || null,
        minimal_actions: step.minimal_actions || null,
        safeguard_measures: step.safeguard_measures || null,
      }));
      const stepLinks = this.form.steps.flatMap((step) => step.part_links.map((link, index) => ({
        id: link.id || undefined,
        protocol_step_id: step.id || undefined,
        step_code: step.code,
        protocol_part_id: Number(link.protocol_part_id),
        sort_order: index + 1,
        is_required: Boolean(link.is_required),
        condition: this.parseLinkJson(link.condition_json, `La condición del vínculo ${index + 1} de la etapa ${step.step_order}`),
        configuration: this.parseLinkJson(link.configuration_json, `La configuración del vínculo ${index + 1} de la etapa ${step.step_order}`),
      })));
      const generalLinks = this.form.general_part_links.map((link, index) => ({
        id: link.id || undefined,
        protocol_part_id: Number(link.protocol_part_id),
        sort_order: index + 1,
        is_required: Boolean(link.is_required),
        condition: this.parseLinkJson(link.condition_json, `La condición del vínculo general ${index + 1}`),
        configuration: this.parseLinkJson(link.configuration_json, `La configuración del vínculo general ${index + 1}`),
      }));
      return {
        expected_revision: this.form.expected_revision || undefined,
        code: this.form.code,
        version_label: this.form.version_label || null,
        name: this.form.name,
        protocol_type_item_id: this.form.protocol_type_item_id || null,
        criticality_item_id: this.form.criticality_item_id || null,
        regulatory_source: this.form.regulatory_source || null,
        education_scope: this.form.education_scope || null,
        legal_reference: this.form.legal_reference || null,
        source_reference: this.form.source_reference || null,
        effective_from: this.form.effective_from || null,
        effective_to: this.form.effective_to || null,
        published_at: this.form.published_at || null,
        description: this.form.description || null,
        required_documents: this.form.required_documents || null,
        safeguard_measures: this.form.safeguard_measures || null,
        minimal_actions: this.form.minimal_actions || null,
        default_due_days: this.form.default_due_days ? Number(this.form.default_due_days) : null,
        status: this.form.status,
        is_sensitive: Boolean(this.form.is_sensitive),
        metadata,
        steps,
        part_links: [...generalLinks, ...stepLinks],
      };
    },
    submit() {
      this.validationAttempted = true;
      this.jsonError = "";
      if (this.validationErrors.length) {
        this.section = this.validationErrors.some((error) => error.includes("etapa") || error.includes("códigos")) ? "steps" : "general";
        return;
      }
      try {
        this.$emit("save", this.buildPayload());
      } catch (error) {
        this.validationAttempted = true;
        this.jsonError = error.message;
      }
    },
  },
};
</script>

<template>
  <section class="protocol-editor" aria-labelledby="protocol-editor-title">
    <header class="protocol-editor__hero">
      <div class="protocol-editor__hero-main">
        <span class="protocol-editor__hero-icon"><i class="bx bx-shield-quarter" aria-hidden="true"></i></span>
        <div>
          <span>{{ isEditing ? "Edición versionada" : "Nueva definición" }}</span>
          <h2 id="protocol-editor-title">{{ form.name || "Constructor de protocolo" }}</h2>
          <p>Configura la ruta, sus plazos y las partes reglamentarias relacionadas.</p>
        </div>
      </div>
      <div class="protocol-editor__hero-actions">
        <button type="button" class="btn btn-light" @click="$emit('cancel')">Cancelar</button>
        <button type="button" class="btn btn-success" :disabled="saving" @click="submit"><i class="bx bx-save" aria-hidden="true"></i>{{ saving ? "Guardando…" : "Guardar protocolo" }}</button>
      </div>
    </header>

    <div v-if="form.metadata?.review_required || warnings.length" class="protocol-editor__warning" role="alert">
      <i class="bx bx-error-circle" aria-hidden="true"></i>
      <div><strong>Revisión normativa requerida</strong><p>El documento fuente contiene observaciones que deben resolverse mediante revisión humana. El sistema no interpreta estas contradicciones.</p><ul v-if="warnings.length"><li v-for="(warning, index) in warnings" :key="index">{{ warning }}</li></ul></div>
    </div>

    <div class="protocol-editor__summary">
      <span><small>Versión</small><strong>{{ form.version_label || "Sin versión" }}</strong></span>
      <span><small>Etapas</small><strong>{{ form.steps.length }}</strong></span>
      <span><small>Partes vinculadas</small><strong>{{ linkedPartsCount }}</strong></span>
      <span><small>Estado</small><strong>{{ form.status }}</strong></span>
    </div>

    <nav class="protocol-editor__nav" aria-label="Secciones del editor">
      <button type="button" :class="{ active: section === 'general' }" @click="section = 'general'"><i class="bx bx-file" aria-hidden="true"></i>Identificación y alcance</button>
      <button type="button" :class="{ active: section === 'steps' }" @click="section = 'steps'"><i class="bx bx-list-ol" aria-hidden="true"></i>Etapas y relaciones <span>{{ form.steps.length }}</span></button>
    </nav>

    <div v-if="validationAttempted && validationErrors.length" class="protocol-editor__validation" role="alert">
      <strong>Revisa antes de guardar:</strong><ul><li v-for="error in validationErrors" :key="error">{{ error }}</li></ul>
    </div>

    <form @submit.prevent="submit">
      <div v-if="section === 'general'" class="protocol-editor__panel">
        <div class="protocol-editor__section-title"><span>01</span><div><h3>Identificación del protocolo</h3><p>Datos que permiten reconocer y controlar la versión reglamentaria.</p></div></div>
        <div class="row g-3">
          <div class="col-lg-3"><label class="form-label">Código *</label><input v-model="form.code" class="form-control" maxlength="80" placeholder="rice_protocolo_01" required /></div>
          <div class="col-lg-2"><label class="form-label">Versión</label><input v-model="form.version_label" class="form-control" maxlength="80" placeholder="2026" /></div>
          <div class="col-lg-7"><label class="form-label">Nombre *</label><input v-model="form.name" class="form-control" maxlength="191" required /></div>
          <div class="col-lg-4"><label class="form-label">Tipo</label><select v-model="form.protocol_type_item_id" class="form-select"><option :value="null">Sin tipo</option><option v-for="item in protocolTypeOptions" :key="item.value" :value="item.value">{{ item.text }}</option></select></div>
          <div class="col-lg-4"><label class="form-label">Criticidad</label><select v-model="form.criticality_item_id" class="form-select"><option :value="null">Sin criticidad</option><option v-for="item in criticalityOptions" :key="item.value" :value="item.value">{{ item.text }}</option></select></div>
          <div class="col-lg-4"><label class="form-label">Ámbito educativo</label><select v-model="form.education_scope" class="form-select"><option value="todos_los_niveles">Todos los niveles</option><option value="parvularia">Educación Parvularia</option><option value="basica">Educación Básica</option><option value="media">Educación Media</option><option value="funcionarios">Funcionarios</option></select></div>
          <div class="col-12"><label class="form-label">Descripción y propósito</label><textarea v-model="form.description" class="form-control" rows="3"></textarea></div>
        </div>

        <div class="protocol-editor__section-title is-sub"><span>02</span><div><h3>Fuente y vigencia</h3><p>Referencia explícita al reglamento y al marco aplicable.</p></div></div>
        <div class="row g-3">
          <div class="col-lg-6"><label class="form-label">Fuente reglamentaria</label><input v-model="form.regulatory_source" class="form-control" maxlength="191" /></div>
          <div class="col-lg-6"><label class="form-label">Referencia de origen</label><input v-model="form.source_reference" class="form-control" placeholder="RICE 2026, Protocolo 3" /></div>
          <div class="col-12"><label class="form-label">Referencia legal</label><textarea v-model="form.legal_reference" class="form-control" rows="2"></textarea></div>
          <div class="col-md-3"><label class="form-label">Vigente desde</label><input v-model="form.effective_from" type="date" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">Vigente hasta</label><input v-model="form.effective_to" type="date" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">Publicación</label><input v-model="form.published_at" type="datetime-local" class="form-control" /></div>
          <div class="col-md-3"><label class="form-label">Estado</label><select v-model="form.status" class="form-select"><option value="borrador">Borrador</option><option value="activo">Activo</option><option value="inactivo">Inactivo</option></select></div>
          <div class="col-md-3 protocol-editor__checks"><label><input v-model="form.is_sensitive" type="checkbox" />Definición sensible</label></div>
        </div>

        <div class="protocol-editor__section-title is-sub"><span>03</span><div><h3>Reglas generales</h3><p>Resguardos que aplican al protocolo completo, además de las reglas de cada etapa.</p></div></div>
        <div class="row g-3">
          <div class="col-lg-4"><label class="form-label">Acciones mínimas</label><textarea v-model="form.minimal_actions" class="form-control" rows="3"></textarea></div>
          <div class="col-lg-4"><label class="form-label">Medidas protectoras</label><textarea v-model="form.safeguard_measures" class="form-control" rows="3"></textarea></div>
          <div class="col-lg-4"><label class="form-label">Documentos requeridos</label><textarea v-model="form.required_documents" class="form-control" rows="3"></textarea></div>
          <div class="col-md-3"><label class="form-label">Plazo general heredado</label><div class="input-group"><input v-model.number="form.default_due_days" type="number" min="1" max="180" class="form-control" /><span class="input-group-text">días</span></div></div>
          <div class="col-md-9"><details class="protocol-editor__advanced"><summary>Metadatos y advertencias estructuradas</summary><textarea v-model="form.metadata_json" class="form-control font-monospace mt-2" rows="5" spellcheck="false"></textarea></details></div>
        </div>

        <section class="protocol-editor__general-parts">
          <header>
            <i class="bx bx-layer" aria-hidden="true"></i>
            <div><h4>Partes generales del protocolo</h4><p>Se instancian una vez por activación y deben resolverse aunque no pertenezcan a una etapa específica.</p></div>
            <span>{{ form.general_part_links.length }} vinculada(s)</span>
          </header>
          <div class="protocol-editor__part-filters">
            <input v-model="generalPartSearch" type="search" class="form-control" placeholder="Buscar parte general" aria-label="Buscar parte general para vincular" />
            <select v-model="generalPartCategory" class="form-select" aria-label="Categoría de parte general"><option value="">Todas las categorías</option><option v-for="category in partCategories" :key="category.value" :value="category.value">{{ category.text }}</option></select>
          </div>
          <div class="protocol-editor__remote-part">
            <label class="form-label">Buscar en toda la biblioteca</label>
            <ConvivenciaRemoteSelect v-model="remoteGeneralPartId" type="parts" placeholder="Buscar por código o nombre" aria-label="Buscar parte general en toda la biblioteca" @select="linkRemotePart($event, 'general')" />
            <small>La búsqueda consulta el catálogo completo y agrega la parte como vínculo general.</small>
          </div>
          <div v-for="(categoryParts, category) in groupedGeneralParts" :key="category" class="protocol-editor__part-group">
            <h5>{{ partCategoryLabel(category) }}</h5>
            <div class="protocol-editor__part-grid">
              <button v-for="part in categoryParts" :key="part.id" type="button" :class="{ selected: isGeneralLinked(part.id) }" @click="toggleGeneralPart(part)"><i class="bx" :class="partIcon(category)" aria-hidden="true"></i><span><strong>{{ part.title }}</strong><small>{{ part.code }}</small></span><i class="bx" :class="isGeneralLinked(part.id) ? 'bx-check-circle' : 'bx-plus-circle'" aria-hidden="true"></i></button>
            </div>
          </div>
          <p v-if="!filteredGeneralParts.length" class="protocol-editor__parts-empty">No hay partes disponibles para estos filtros.</p>
          <div v-if="form.general_part_links.length" class="protocol-editor__linked-list">
            <h5>Configuración de vínculos generales</h5>
            <article v-for="(link, index) in form.general_part_links" :key="link.protocol_part_id" class="protocol-editor__link-config">
              <header><div><small>Vínculo general {{ index + 1 }}</small><strong>{{ activeParts.find(part => Number(part.id) === Number(link.protocol_part_id))?.title || `Parte #${link.protocol_part_id}` }}</strong></div><label><input v-model="link.is_required" type="checkbox" />Obligatoria</label><button type="button" aria-label="Desvincular parte general" @click="form.general_part_links.splice(index, 1)"><i class="bx bx-x" aria-hidden="true"></i></button></header>
              <div class="protocol-editor__link-json"><div><label class="form-label">Condición de aplicación (JSON)</label><textarea v-model="link.condition_json" class="form-control font-monospace" rows="3" spellcheck="false" placeholder='{"education_scope":"parvularia"}'></textarea><small>Vacío o <code>{}</code> significa que aplica sin condición.</small></div><div><label class="form-label">Configuración operativa (JSON)</label><textarea v-model="link.configuration_json" class="form-control font-monospace" rows="3" spellcheck="false" placeholder='{"allow_not_applicable":true}'></textarea><small>Controla excepciones sin alterar la definición de la parte.</small></div></div>
            </article>
          </div>
        </section>
      </div>

      <div v-else class="protocol-editor__steps-layout">
        <aside class="protocol-editor__outline">
          <header><div><span>Ruta del protocolo</span><strong>{{ form.steps.length }} etapa(s)</strong></div><button type="button" class="btn btn-sm btn-primary" @click="addStep"><i class="bx bx-plus"></i>Agregar</button></header>
          <ol>
            <li v-for="(step, index) in form.steps" :key="step._key" :class="{ active: selectedStepKey === step._key, inactive: !step.active }">
              <button type="button" class="protocol-editor__outline-main" @click="selectedStepKey = step._key"><span>{{ index + 1 }}</span><div><small>{{ step.code || "Sin código" }}</small><strong>{{ step.stage_name || "Etapa sin nombre" }}</strong></div></button>
              <div class="protocol-editor__outline-actions"><button type="button" :disabled="index === 0" :aria-label="`Subir ${step.stage_name}`" @click="moveStep(step, -1)"><i class="bx bx-up-arrow-alt"></i></button><button type="button" :disabled="index === form.steps.length - 1" :aria-label="`Bajar ${step.stage_name}`" @click="moveStep(step, 1)"><i class="bx bx-down-arrow-alt"></i></button><button type="button" :disabled="form.steps.length === 1" :aria-label="`Eliminar ${step.stage_name}`" @click="removeStep(step)"><i class="bx bx-trash"></i></button></div>
            </li>
          </ol>
        </aside>

        <div v-if="selectedStep" class="protocol-editor__step-panel">
          <header class="protocol-editor__step-heading"><div><span>Etapa {{ selectedStep.step_order }}</span><h3>{{ selectedStep.stage_name || "Configurar etapa" }}</h3></div><label><input v-model="selectedStep.active" type="checkbox" />Etapa disponible</label></header>
          <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Código *</label><input v-model="selectedStep.code" class="form-control" maxlength="80" required @change="changeStepCode(selectedStep)" /></div>
            <div class="col-md-6"><label class="form-label">Nombre de etapa *</label><input v-model="selectedStep.stage_name" class="form-control" maxlength="160" required /></div>
            <div class="col-md-3"><label class="form-label">Tipo</label><select v-model="selectedStep.step_type" class="form-select"><option v-for="item in stepTypes" :key="item[0]" :value="item[0]">{{ item[1] }}</option></select></div>
            <div class="col-md-7"><label class="form-label">Descripción u objetivo</label><textarea v-model="selectedStep.description" class="form-control" rows="2"></textarea></div>
            <div class="col-md-5"><label class="form-label">Responsable</label><input v-model="selectedStep.responsible_label" class="form-control" maxlength="160" /></div>
          </div>

          <section class="protocol-editor__step-section"><header><i class="bx bx-time-five"></i><div><h4>Plazo y prórroga</h4><p>Define la unidad exacta y el hecho que inicia el cómputo.</p></div></header><div class="row g-3">
            <div class="col-md-3"><label class="form-label">Cantidad</label><input v-model.number="selectedStep.deadline_value" type="number" min="1" max="365" class="form-control" /></div>
            <div class="col-md-4"><label class="form-label">Unidad</label><select v-model="selectedStep.deadline_unit" class="form-select"><option v-for="unit in deadlineUnits" :key="unit[0]" :value="unit[0]">{{ unit[1] }}</option></select></div>
            <div class="col-md-5"><label class="form-label">Contar desde</label><select v-model="selectedStep.deadline_anchor" class="form-select"><option v-for="anchor in deadlineAnchors" :key="anchor[0]" :value="anchor[0]">{{ anchor[1] }}</option></select></div>
            <div class="col-md-3 protocol-editor__checks"><label><input v-model="selectedStep.can_extend" type="checkbox" />Permite prórroga</label></div>
            <template v-if="selectedStep.can_extend"><div class="col-md-3"><label class="form-label">Extensión máxima</label><input v-model.number="selectedStep.extension_value" type="number" min="1" max="365" class="form-control" /></div><div class="col-md-4"><label class="form-label">Unidad de extensión</label><select v-model="selectedStep.extension_unit" class="form-select"><option v-for="unit in deadlineUnits" :key="unit[0]" :value="unit[0]">{{ unit[1] }}</option></select></div></template>
          </div></section>

          <section class="protocol-editor__step-section protocol-editor__completion"><header><i class="bx bx-check-shield"></i><div><h4>Reglas y exigencias</h4><p>La edición conserva las claves reglamentarias no modificadas; ninguna exigencia se agrega automáticamente.</p></div></header><div class="row g-3">
            <div class="col-12"><label class="form-label">Resumen operativo (opcional)</label><textarea v-model="selectedStep.completion_rule_text" class="form-control" rows="2" placeholder="Describe qué debe cumplirse antes de avanzar."></textarea><small>Solo crea o modifica <code>summary</code> cuando cambias este texto.</small></div>
            <div class="col-lg-6">
              <label class="form-label">Todas deben cumplirse · <code>all</code></label>
              <textarea v-model="selectedStep.completion_all_json" class="form-control font-monospace" rows="5" spellcheck="false" placeholder='["informe_registrado", {"field":"evidence","operator":"present"}]'></textarea>
              <small>Lista u objeto JSON. Conserva criterios complejos sin convertirlos.</small>
              <div v-if="criteriaPreview(selectedStep.completion_all_json).length" class="protocol-editor__criteria-preview" aria-label="Vista previa de criterios obligatorios"><span v-for="(criterion, index) in criteriaPreview(selectedStep.completion_all_json)" :key="`all-${index}`"><i class="bx bx-check" aria-hidden="true"></i>{{ criteriaPreviewLabel(criterion) }}</span></div>
            </div>
            <div class="col-lg-6">
              <label class="form-label">Al menos una debe cumplirse · <code>any</code></label>
              <textarea v-model="selectedStep.completion_any_json" class="form-control font-monospace" rows="5" spellcheck="false" placeholder='["acta_firmada", "constancia_de_notificacion"]'></textarea>
              <small>Déjalo vacío para conservar la ausencia de esta regla.</small>
              <div v-if="criteriaPreview(selectedStep.completion_any_json).length" class="protocol-editor__criteria-preview is-any" aria-label="Vista previa de criterios alternativos"><span v-for="(criterion, index) in criteriaPreview(selectedStep.completion_any_json)" :key="`any-${index}`"><i class="bx bx-git-branch" aria-hidden="true"></i>{{ criteriaPreviewLabel(criterion) }}</span></div>
            </div>
            <div class="col-md-6"><label class="form-label">Exigencia adicional sobre partes</label><select v-model="selectedStep.completion_parts_mode" class="form-select"><option value="inherit">Sin regla explícita: respetar cada vínculo</option><option value="required">Exigir todas las partes</option><option value="optional">No exigir todas las partes</option></select><small>Solo modifica <code>requires_all_parts</code> si cambias esta selección.</small></div>
            <div class="col-md-6"><label class="form-label">Notas para completar</label><select v-model="selectedStep.completion_notes_mode" class="form-select"><option value="inherit">Sin regla explícita</option><option value="required">Exigir notas de cierre</option><option value="optional">No exigir notas de cierre</option></select><small>Al editar, se usa la clave canónica <code>requires_notes</code>.</small></div>
            <div class="col-lg-4"><label class="form-label">Acciones mínimas</label><textarea v-model="selectedStep.minimal_actions" class="form-control" rows="3"></textarea></div>
            <div class="col-lg-4"><label class="form-label">Medidas protectoras</label><textarea v-model="selectedStep.safeguard_measures" class="form-control" rows="3"></textarea></div>
            <div class="col-lg-4"><label class="form-label">Documentos requeridos</label><textarea v-model="selectedStep.required_documents" class="form-control" rows="3"></textarea></div>
          </div></section>

          <section class="protocol-editor__step-section protocol-editor__parts"><header><i class="bx bx-link-alt"></i><div><h4>Partes relacionadas</h4><p>Selecciona medidas, sanciones, documentos y otras piezas de la biblioteca.</p></div><span>{{ selectedStep.part_links.length }} vinculada(s)</span></header>
            <div class="protocol-editor__part-filters"><input v-model="partSearch" type="search" class="form-control" placeholder="Buscar en la biblioteca" aria-label="Buscar parte para vincular" /><select v-model="partCategory" class="form-select" aria-label="Categoría de parte"><option value="">Todas las categorías</option><option v-for="category in partCategories" :key="category.value" :value="category.value">{{ category.text }}</option></select></div>
            <div class="protocol-editor__remote-part">
              <label class="form-label">Buscar en toda la biblioteca</label>
              <ConvivenciaRemoteSelect v-model="remoteStepPartId" type="parts" placeholder="Buscar por código o nombre" aria-label="Buscar parte para vincular a la etapa" @select="linkRemotePart($event, 'step')" />
              <small>Al seleccionar se crea el vínculo con esta etapa; luego puedes configurar obligatoriedad y condiciones.</small>
            </div>
            <div v-for="(categoryParts, category) in groupedParts" :key="category" class="protocol-editor__part-group"><h5>{{ partCategoryLabel(category) }}</h5><div class="protocol-editor__part-grid"><button v-for="part in categoryParts" :key="part.id" type="button" :class="{ selected: isLinked(selectedStep, part.id) }" @click="togglePart(selectedStep, part)"><i class="bx" :class="partIcon(category)"></i><span><strong>{{ part.title }}</strong><small>{{ part.code }}</small></span><i class="bx" :class="isLinked(selectedStep, part.id) ? 'bx-check-circle' : 'bx-plus-circle'"></i></button></div></div>
            <div v-if="selectedStep.part_links.length" class="protocol-editor__linked-list">
              <h5>Configuración de vínculos</h5>
              <article v-for="(link, index) in selectedStep.part_links" :key="link.protocol_part_id" class="protocol-editor__link-config">
                <header><div><small>Vínculo de etapa {{ index + 1 }}</small><strong>{{ activeParts.find(part => Number(part.id) === Number(link.protocol_part_id))?.title || `Parte #${link.protocol_part_id}` }}</strong></div><label><input v-model="link.is_required" type="checkbox" />Obligatoria en esta etapa</label><button type="button" aria-label="Desvincular parte" @click="selectedStep.part_links.splice(index, 1)"><i class="bx bx-x" aria-hidden="true"></i></button></header>
                <div class="protocol-editor__link-json"><div><label class="form-label">Condición de aplicación (JSON)</label><textarea v-model="link.condition_json" class="form-control font-monospace" rows="3" spellcheck="false" placeholder='{"role":"affected"}'></textarea><small>La persona ejecutora deberá confirmar esta condición.</small></div><div><label class="form-label">Configuración operativa (JSON)</label><textarea v-model="link.configuration_json" class="form-control font-monospace" rows="3" spellcheck="false" placeholder='{"allow_not_applicable":true}'></textarea><small>Define excepciones o bloqueos específicos del vínculo.</small></div></div>
              </article>
            </div>
          </section>

          <details class="protocol-editor__advanced"><summary>Metadatos avanzados de la etapa</summary><textarea v-model="selectedStep.metadata_json" class="form-control font-monospace mt-2" rows="4" spellcheck="false"></textarea></details>
        </div>
      </div>
    </form>

    <footer class="protocol-editor__footer"><div><strong>{{ validationErrors.length ? `${validationErrors.length} observación(es)` : "Definición lista para guardar" }}</strong><span>Los casos ya activados conservarán su versión histórica.</span></div><div><button type="button" class="btn btn-light" @click="$emit('cancel')">Cancelar</button><button type="button" class="btn btn-success" :disabled="saving" @click="submit"><i class="bx bx-save"></i>{{ saving ? "Guardando…" : "Guardar protocolo" }}</button></div></footer>
  </section>
</template>

<style scoped>
.protocol-editor{--pe-navy:#263a8f;--pe-indigo:#4f63d9;--pe-teal:#278a72;display:grid;gap:1rem}.protocol-editor__hero{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.15rem 1.3rem;color:#fff;border-radius:18px;background:radial-gradient(circle at 85% 15%,rgba(255,255,255,.18),transparent 26%),linear-gradient(125deg,#263a8f,#4f63d9 60%,#318e89);box-shadow:0 16px 34px rgba(42,57,126,.2)}.protocol-editor__hero-main,.protocol-editor__hero-actions,.protocol-editor__summary,.protocol-editor__nav,.protocol-editor__section-title,.protocol-editor__step-heading,.protocol-editor__step-section>header,.protocol-editor__parts>header,.protocol-editor__part-filters,.protocol-editor__linked-row,.protocol-editor__footer,.protocol-editor__footer>div{display:flex;align-items:center}.protocol-editor__hero-main{gap:.8rem;min-width:0}.protocol-editor__hero-icon{display:grid;flex:0 0 48px;width:48px;height:48px;font-size:1.3rem;place-items:center;border:1px solid rgba(255,255,255,.25);border-radius:14px;background:rgba(255,255,255,.13)}.protocol-editor__hero-main>div>span{font-size:.62rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;opacity:.76}.protocol-editor__hero h2{overflow:hidden;margin:.15rem 0;color:#fff;font-size:1.15rem;text-overflow:ellipsis;white-space:nowrap}.protocol-editor__hero p{margin:0;color:rgba(255,255,255,.75);font-size:.72rem}.protocol-editor__hero-actions{gap:.55rem;flex:0 0 auto}.protocol-editor__hero-actions .btn,.protocol-editor__footer .btn{display:inline-flex;align-items:center;gap:.35rem}.protocol-editor__warning{display:grid;grid-template-columns:36px 1fr;gap:.75rem;padding:.9rem 1rem;color:#88571d;border:1px solid #ecd2a8;border-radius:14px;background:#fff9ef}.protocol-editor__warning>i{font-size:1.35rem}.protocol-editor__warning strong{display:block}.protocol-editor__warning p{margin:.12rem 0;font-size:.72rem}.protocol-editor__warning ul{padding-left:1rem;margin:.4rem 0 0;font-size:.7rem}.protocol-editor__summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem}.protocol-editor__summary>span{display:flex;min-width:0;flex-direction:column;padding:.75rem .85rem;border:1px solid #e0e6ef;border-radius:13px;background:#fff}.protocol-editor__summary small{color:#7b8699;font-size:.6rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.protocol-editor__summary strong{overflow:hidden;margin-top:.15rem;color:#34415a;font-size:.85rem;text-overflow:ellipsis;white-space:nowrap}.protocol-editor__nav{gap:.45rem;padding:.35rem;border:1px solid #e0e6ef;border-radius:13px;background:#f3f5f9}.protocol-editor__nav button{display:inline-flex;align-items:center;gap:.4rem;min-height:38px;padding:.45rem .7rem;color:#647087;font-size:.72rem;font-weight:750;border:0;border-radius:9px;background:transparent}.protocol-editor__nav button.active{color:var(--pe-navy);background:#fff;box-shadow:0 4px 12px rgba(43,54,91,.08)}.protocol-editor__nav button span{padding:.1rem .36rem;color:#fff;font-size:.58rem;border-radius:999px;background:var(--pe-indigo)}.protocol-editor__validation{padding:.8rem .95rem;color:#9b343a;font-size:.72rem;border:1px solid #ebc6c9;border-radius:12px;background:#fff6f6}.protocol-editor__validation ul{padding-left:1.1rem;margin:.25rem 0 0}.protocol-editor__panel,.protocol-editor__step-panel{padding:1.1rem;border:1px solid #e0e6ef;border-radius:17px;background:#fff;box-shadow:0 9px 24px rgba(40,50,79,.05)}.protocol-editor__section-title{align-items:flex-start;gap:.65rem;margin-bottom:.9rem}.protocol-editor__section-title.is-sub{padding-top:1.1rem;margin-top:1.1rem;border-top:1px solid #edf0f5}.protocol-editor__section-title>span{display:grid;flex:0 0 28px;width:28px;height:28px;color:#fff;font-size:.62rem;font-weight:800;place-items:center;border-radius:9px;background:var(--pe-indigo)}.protocol-editor__section-title h3,.protocol-editor__step-heading h3,.protocol-editor__step-section h4{margin:0;color:#303e58}.protocol-editor__section-title h3{font-size:.87rem}.protocol-editor__section-title p,.protocol-editor__step-section p{margin:.14rem 0 0;color:#7c879a;font-size:.67rem}.protocol-editor__checks{display:flex;align-items:flex-end;padding-bottom:.45rem}.protocol-editor__checks label{display:inline-flex;align-items:center;gap:.42rem;margin:0;color:#536079;font-size:.7rem;font-weight:750}.protocol-editor__checks input{width:17px;height:17px;accent-color:var(--pe-indigo)}.protocol-editor__advanced{padding:.7rem .8rem;border:1px solid #e1e6ee;border-radius:11px;background:#f8fafc}.protocol-editor__advanced summary{color:#59677d;font-size:.7rem;font-weight:750;cursor:pointer}.protocol-editor__steps-layout{display:grid;grid-template-columns:285px minmax(0,1fr);gap:1rem;align-items:start}.protocol-editor__outline{position:sticky;top:84px;overflow:hidden;border:1px solid #dfe5ee;border-radius:16px;background:#fff;box-shadow:0 9px 24px rgba(40,50,79,.05)}.protocol-editor__outline>header{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.85rem;border-bottom:1px solid #e8ecf2;background:#f7f9fc}.protocol-editor__outline>header div{display:flex;flex-direction:column}.protocol-editor__outline>header span{color:#7c879a;font-size:.58rem;font-weight:800;text-transform:uppercase}.protocol-editor__outline>header strong{color:#334159;font-size:.75rem}.protocol-editor__outline ol{display:grid;gap:.25rem;max-height:67vh;overflow:auto;padding:.55rem;margin:0;list-style:none}.protocol-editor__outline li{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;border:1px solid transparent;border-radius:11px}.protocol-editor__outline li.active{border-color:#cdd5f5;background:#f2f5ff}.protocol-editor__outline li.inactive{opacity:.58}.protocol-editor__outline-main{display:grid;min-width:0;grid-template-columns:29px minmax(0,1fr);align-items:center;gap:.5rem;padding:.55rem;color:inherit;text-align:left;border:0;background:transparent}.protocol-editor__outline-main>span{display:grid;width:29px;height:29px;color:#fff;font-size:.65rem;font-weight:800;place-items:center;border-radius:9px;background:var(--pe-indigo)}.protocol-editor__outline-main div{min-width:0}.protocol-editor__outline-main small,.protocol-editor__outline-main strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.protocol-editor__outline-main small{color:#78849a;font-size:.56rem}.protocol-editor__outline-main strong{color:#34415a;font-size:.7rem}.protocol-editor__outline-actions{display:flex;padding-right:.35rem}.protocol-editor__outline-actions button{display:grid;width:24px;height:26px;padding:0;color:#7c879a;place-items:center;border:0;background:transparent}.protocol-editor__outline-actions button:disabled{opacity:.25}.protocol-editor__step-heading{justify-content:space-between;padding-bottom:.85rem;margin-bottom:.9rem;border-bottom:1px solid #edf0f5}.protocol-editor__step-heading>div>span{color:var(--pe-indigo);font-size:.62rem;font-weight:800;text-transform:uppercase}.protocol-editor__step-heading h3{font-size:1rem}.protocol-editor__step-heading>label{display:inline-flex;align-items:center;gap:.35rem;color:#536079;font-size:.68rem;font-weight:750}.protocol-editor__step-heading input{accent-color:var(--pe-indigo)}.protocol-editor__step-section{padding:.9rem;margin-top:1rem;border:1px solid #e4e9f0;border-radius:14px;background:#fbfcfe}.protocol-editor__step-section>header{align-items:flex-start;gap:.55rem;margin-bottom:.7rem}.protocol-editor__step-section>header>i{display:grid;flex:0 0 31px;width:31px;height:31px;color:var(--pe-indigo);font-size:1rem;place-items:center;border-radius:9px;background:#eef1ff}.protocol-editor__step-section h4{font-size:.78rem}.protocol-editor__parts>header>span{margin-left:auto;padding:.22rem .48rem;color:#4e5f84;font-size:.6rem;font-weight:800;border-radius:999px;background:#e9edf8}.protocol-editor__part-filters{gap:.55rem;margin-bottom:.75rem}.protocol-editor__part-filters .form-control{flex:1}.protocol-editor__part-filters .form-select{max-width:240px}.protocol-editor__part-group{margin-top:.7rem}.protocol-editor__part-group h5,.protocol-editor__linked-list h5{margin:0 0 .4rem;color:#657187;font-size:.62rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.protocol-editor__part-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem}.protocol-editor__part-grid button{display:grid;min-width:0;grid-template-columns:29px minmax(0,1fr) 19px;align-items:center;gap:.5rem;min-height:52px;padding:.5rem;color:#57647a;text-align:left;border:1px solid #dde3ec;border-radius:10px;background:#fff}.protocol-editor__part-grid button.selected{color:#30478f;border-color:#aebcf0;background:#f1f4ff}.protocol-editor__part-grid button>i:first-child{display:grid;width:29px;height:29px;place-items:center;border-radius:8px;background:#f0f3f7}.protocol-editor__part-grid button.selected>i:first-child{background:#dfe5ff}.protocol-editor__part-grid button span{min-width:0}.protocol-editor__part-grid strong,.protocol-editor__part-grid small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.protocol-editor__part-grid strong{font-size:.67rem}.protocol-editor__part-grid small{color:#8893a4;font-size:.55rem}.protocol-editor__linked-list{display:grid;gap:.35rem;padding-top:.8rem;margin-top:.8rem;border-top:1px solid #e3e8ef}.protocol-editor__linked-row{gap:.6rem;padding:.5rem .6rem;border:1px solid #e1e6ee;border-radius:9px;background:#fff}.protocol-editor__linked-row>span{flex:1;min-width:0;overflow:hidden;color:#48566e;font-size:.67rem;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.protocol-editor__linked-row label{display:inline-flex;align-items:center;gap:.3rem;margin:0;color:#69758a;font-size:.6rem}.protocol-editor__linked-row input{accent-color:var(--pe-indigo)}.protocol-editor__linked-row button{display:grid;width:25px;height:25px;padding:0;color:#a2484e;font-size:1rem;place-items:center;border:0;border-radius:7px;background:#fff0f1}.protocol-editor__footer{position:sticky;z-index:4;bottom:.5rem;justify-content:space-between;gap:1rem;padding:.8rem 1rem;border:1px solid #dbe1eb;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 12px 30px rgba(33,43,74,.14);backdrop-filter:blur(12px)}.protocol-editor__footer>div:first-child{align-items:flex-start;flex-direction:column}.protocol-editor__footer strong{color:#34415a;font-size:.72rem}.protocol-editor__footer span{color:#7c879a;font-size:.62rem}.protocol-editor__footer>div:last-child{gap:.5rem}
.protocol-editor__general-parts{padding:.9rem;margin-top:1rem;border:1px solid #dce3ee;border-radius:14px;background:#f8faff}.protocol-editor__general-parts>header{display:flex;align-items:flex-start;gap:.55rem;margin-bottom:.75rem}.protocol-editor__general-parts>header>i{display:grid;width:32px;height:32px;flex:0 0 32px;color:#fff;place-items:center;border-radius:9px;background:linear-gradient(135deg,var(--pe-navy),var(--pe-indigo))}.protocol-editor__general-parts>header>div{flex:1}.protocol-editor__general-parts h4{margin:0;color:#303e58;font-size:.8rem}.protocol-editor__general-parts header p{margin:.14rem 0 0;color:#7c879a;font-size:.67rem}.protocol-editor__general-parts>header>span{padding:.22rem .48rem;color:#4e5f84;font-size:.6rem;font-weight:800;border-radius:999px;background:#e4e9fb}.protocol-editor__link-config{overflow:hidden;border:1px solid #dfe5ee;border-radius:11px;background:#fff}.protocol-editor__link-config>header{display:flex;align-items:center;gap:.65rem;padding:.58rem .65rem;background:#f8fafc}.protocol-editor__link-config>header>div{display:flex;min-width:0;flex:1;flex-direction:column}.protocol-editor__link-config>header small{color:#8590a3;font-size:.54rem;text-transform:uppercase}.protocol-editor__link-config>header strong{overflow:hidden;color:#435169;font-size:.68rem;text-overflow:ellipsis;white-space:nowrap}.protocol-editor__link-config>header label{display:inline-flex;align-items:center;gap:.3rem;margin:0;color:#67748a;font-size:.6rem}.protocol-editor__link-config>header input{accent-color:var(--pe-indigo)}.protocol-editor__link-config>header button{display:grid;width:26px;height:26px;padding:0;color:#a2484e;font-size:1rem;place-items:center;border:0;border-radius:7px;background:#fff0f1}.protocol-editor__link-json{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;padding:.65rem}.protocol-editor__link-json .form-label{margin-bottom:.25rem;color:#56647c;font-size:.61rem;font-weight:750}.protocol-editor__link-json textarea{font-size:.63rem;border-color:#dce2eb;background:#fbfcfe}.protocol-editor__link-json small{display:block;margin-top:.25rem;color:#8792a4;font-size:.55rem}.protocol-editor__link-json code{color:#6674b5}.protocol-editor__parts-empty{padding:.8rem;margin:.5rem 0 0;color:#7c8799;font-size:.65rem;text-align:center;border:1px dashed #d4dbe6;border-radius:10px;background:#fff}
.protocol-editor__remote-part{display:grid;grid-template-columns:minmax(0,1fr);gap:.22rem;padding:.65rem;margin-bottom:.75rem;border:1px solid #dce3ef;border-radius:11px;background:#fff}.protocol-editor__remote-part .form-label{margin:0;color:#53617a;font-size:.62rem;font-weight:800}.protocol-editor__remote-part>small{color:#7d899b;font-size:.56rem;line-height:1.4}
.protocol-editor__completion .row>div>small{display:block;margin-top:.3rem;color:#7f8a9c;font-size:.57rem;line-height:1.4}.protocol-editor__completion code{color:#5868ad}.protocol-editor__completion textarea.font-monospace{font-size:.64rem;line-height:1.45;border-color:#d8deea;background:#fff}.protocol-editor__criteria-preview{display:flex;flex-wrap:wrap;gap:.3rem;padding-top:.45rem}.protocol-editor__criteria-preview span{display:inline-flex;max-width:100%;align-items:center;gap:.25rem;padding:.26rem .42rem;color:#354d91;font-size:.56rem;font-weight:750;border:1px solid #ced7f6;border-radius:999px;background:#eef2ff}.protocol-editor__criteria-preview span i{flex:0 0 auto}.protocol-editor__criteria-preview.is-any span{color:#236a5a;border-color:#c6e4dc;background:#ecf8f4}
@media(max-width:991.98px){.protocol-editor__hero{align-items:flex-start;flex-direction:column}.protocol-editor__hero-actions{width:100%;justify-content:flex-end}.protocol-editor__steps-layout{grid-template-columns:1fr}.protocol-editor__outline{position:static}.protocol-editor__outline ol{max-height:240px}.protocol-editor__summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575.98px){.protocol-editor__hero{padding:1rem}.protocol-editor__hero-icon{display:none}.protocol-editor__hero-actions{display:grid;grid-template-columns:1fr 1fr}.protocol-editor__hero-actions .btn{justify-content:center}.protocol-editor__summary{grid-template-columns:1fr 1fr}.protocol-editor__nav{overflow-x:auto}.protocol-editor__nav button{white-space:nowrap}.protocol-editor__panel,.protocol-editor__step-panel{padding:.85rem}.protocol-editor__part-filters{align-items:stretch;flex-direction:column}.protocol-editor__part-filters .form-select{max-width:none}.protocol-editor__part-grid,.protocol-editor__link-json{grid-template-columns:1fr}.protocol-editor__linked-row{align-items:flex-start;flex-wrap:wrap}.protocol-editor__linked-row>span{flex-basis:calc(100% - 34px)}.protocol-editor__link-config>header{align-items:flex-start;flex-wrap:wrap}.protocol-editor__link-config>header>div{flex-basis:calc(100% - 36px)}.protocol-editor__footer{bottom:0;align-items:stretch;flex-direction:column}.protocol-editor__footer>div:last-child{display:grid;grid-template-columns:1fr 1fr}.protocol-editor__footer .btn{justify-content:center}}
</style>
