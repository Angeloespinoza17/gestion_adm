import { toInputDateTime } from "../module-utils";

const own = (value, key) => Object.prototype.hasOwnProperty.call(value || {}, key);

const pick = (source, fields, defaults = {}) => fields.reduce((record, field) => {
  record[field] = own(source, field) ? source[field] : (own(defaults, field) ? defaults[field] : null);
  return record;
}, {});

const dateTime = (value) => (value ? toInputDateTime(value) : "");

const planActionFields = [
  "dimension_item_id", "responsible_user_id", "responsible_staff_id", "responsible_department_id",
  "action_type", "title", "description", "dimension_label", "responsible_label", "starts_on", "ends_on",
  "required_resources", "indicator_summary", "verification_means", "status", "advance_percentage",
  "observations", "evidence_summary",
];

const casePersonFields = [
  "student_profile_id", "user_id", "staff_id", "course_section_id", "person_type", "role_type", "full_name",
  "identifier", "relationship_label", "contact_reference", "notes", "is_sensitive",
];

const complaintPersonFields = ["person_type", "role_type", "full_name", "identifier", "contact_reference"];

const participantFields = [
  "student_profile_id", "user_id", "staff_id", "participant_type", "participant_role", "full_name",
  "contact_reference", "notes",
];

const questionFields = ["prompt", "selection_type", "max_choices", "active"];
const answerFields = ["question_order", "respondent_student_id", "selected_student_id", "selection_type", "notes"];

const normalizers = {
  planes(record) {
    const normalized = pick(record, [
      "id", "academic_year_id", "responsible_user_id", "responsible_staff_id", "name", "general_objective",
      "specific_objectives", "resources_required", "indicators_summary", "verification_means_summary", "status",
      "advance_percentage", "starts_on", "ends_on", "observations", "final_evaluation", "is_sensitive",
    ], { specific_objectives: [], is_sensitive: false });
    normalized.actions = (record.actions || []).map((action) => pick(action, planActionFields, { advance_percentage: 0 }));
    return normalized;
  },
  casos(record) {
    const normalized = pick(record, [
      "id", "academic_year_id", "course_section_id", "student_profile_id", "case_type_item_id",
      "classification_item_id", "subclassification_item_id", "criticality_item_id", "responsible_user_id",
      "responsible_staff_id", "opened_at", "happened_at", "origin", "status", "case_type_label",
      "classification_label", "subclassification_label", "criticality_label", "place", "initial_report",
      "background", "immediate_measures", "safeguarding_measures", "internal_notes", "resolution", "conclusion",
      "follow_up_due_at", "is_sensitive",
    ], { is_sensitive: false });
    normalized.opened_at = dateTime(record.opened_at);
    normalized.happened_at = dateTime(record.happened_at);
    normalized.follow_up_due_at = dateTime(record.follow_up_due_at);
    normalized.responsible_user_name = record.responsible_user?.name || null;
    normalized.responsible_staff_name = record.responsible_staff?.full_name || null;
    normalized.people = (record.people || []).map((person) => pick(person, casePersonFields, { is_sensitive: false }));
    return normalized;
  },
  denuncias(record) {
    const normalized = pick(record, [
      "id", "academic_year_id", "course_section_id", "affected_student_id", "situation_type_item_id",
      "responsible_user_id", "case_id", "complainant_name", "complainant_type", "contact_email", "contact_phone",
      "situation_type_label", "place", "received_at", "happened_at", "report_text", "truth_declaration_accepted",
      "is_anonymous", "is_sensitive", "status", "admissibility_result",
    ], { truth_declaration_accepted: true, is_anonymous: false, is_sensitive: false });
    normalized.received_at = dateTime(record.received_at);
    normalized.happened_at = dateTime(record.happened_at);
    normalized.involved_snapshot = (record.involved_snapshot || []).map((person) => pick(person, complaintPersonFields));
    return normalized;
  },
  derivaciones(record) {
    const normalized = pick(record, [
      "id", "case_id", "academic_year_id", "course_section_id", "student_profile_id", "destination_department_id",
      "destination_staff_id", "destination_user_id", "external_institution_id", "responsible_user_id", "scope",
      "status", "priority_level", "confidentiality_level", "destination_label", "external_contact_name",
      "external_contact_email", "external_contact_phone", "derived_at", "sent_at", "response_due_at", "responded_at",
      "closed_at", "motive", "narrative", "response_text", "suggested_actions", "follow_up_notes", "is_sensitive",
    ], { is_sensitive: false });
    ["derived_at", "sent_at", "response_due_at", "responded_at", "closed_at"].forEach((key) => {
      normalized[key] = dateTime(record[key]);
    });
    return normalized;
  },
  medidas(record) {
    const normalized = pick(record, [
      "id", "case_id", "student_profile_id", "course_section_id", "measure_type_item_id", "responsible_user_id",
      "responsible_staff_id", "validated_by", "measure_type_label", "description", "training_objective", "assigned_at",
      "due_at", "status", "evidence_summary", "student_reflection", "repair_action", "responsible_notes",
      "closure_notes", "closed_at", "is_sensitive",
    ], { is_sensitive: false });
    ["assigned_at", "due_at", "closed_at"].forEach((key) => { normalized[key] = dateTime(record[key]); });
    return normalized;
  },
  entrevistas(record) {
    const normalized = pick(record, [
      "id", "case_id", "student_profile_id", "course_section_id", "interview_type_item_id", "responsible_user_id",
      "responsible_staff_id", "interview_type_label", "interview_at", "motive", "topics", "agreements", "commitments",
      "follow_up_date", "follow_up_status", "internal_notes", "is_sensitive",
    ], { is_sensitive: false });
    normalized.interview_at = dateTime(record.interview_at);
    normalized.participants = (record.participants || []).map((participant) => pick(participant, participantFields));
    return normalized;
  },
  bitacora(record) {
    const normalized = pick(record, [
      "id", "case_id", "academic_year_id", "course_section_id", "student_profile_id", "daily_log_type_item_id",
      "inspector_user_id", "inspector_staff_id", "happened_at", "daily_log_type_label", "place", "description",
      "immediate_action", "guardian_informed", "guardian_contact_note", "status", "is_sensitive",
    ], { guardian_informed: false, is_sensitive: false });
    normalized.happened_at = dateTime(record.happened_at);
    normalized.involved_snapshot = (record.involved_snapshot || []).map((person) => pick(person, ["full_name"]));
    return normalized;
  },
  sociogramas(record) {
    const normalized = pick(record, [
      "id", "academic_year_id", "course_section_id", "title", "applied_on", "status", "confidentiality_level",
      "matrix_summary", "result_summary", "interpretation", "is_sensitive",
    ], { matrix_summary: [], result_summary: [], is_sensitive: true });
    const questionOrderById = new Map((record.questions || []).map((question, index) => [Number(question.id), index + 1]));
    normalized.questions = (record.questions || []).map((question) => pick(question, questionFields, { active: true }));
    normalized.answers = (record.answers || []).map((answer, index) => {
      const fallbackOrder = answer.question_order || questionOrderById.get(Number(answer.question_id)) || index + 1;
      return pick(answer, answerFields, { question_order: fallbackOrder });
    });
    return normalized;
  },
};

export function normalizeConvivenciaRecord(section, record) {
  const normalize = normalizers[section];
  if (!normalize) throw new Error(`No existe un normalizador de edición para ${section}.`);
  return normalize(record || {});
}

export const convivenciaRecordEndpoints = Object.freeze({
  planes: "/api/convivencia/plans",
  casos: "/api/convivencia/cases",
  denuncias: "/api/convivencia/complaints",
  derivaciones: "/api/convivencia/derivations",
  entrevistas: "/api/convivencia/interviews",
  medidas: "/api/convivencia/measures",
  bitacora: "/api/convivencia/daily-logs",
  sociogramas: "/api/convivencia/sociograms",
});
