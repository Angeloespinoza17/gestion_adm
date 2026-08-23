import axios from "axios";

const base = "/api/risk-prevention";

export const riskMatrixApi = {
  catalogs: (params = {}) => axios.get(`${base}/risk-matrices/catalogs`, { params }).then(({ data }) => data.data),
  dashboard: () => axios.get(`${base}/risk-matrices/dashboard`).then(({ data }) => data.data),
  list: (params = {}) => axios.get(`${base}/risk-matrices`, { params }).then(({ data }) => data),
  create: (payload) => axios.post(`${base}/risk-matrices`, payload).then(({ data }) => data),
  matrix: (id, params = {}) => axios.get(`${base}/risk-matrices/${id}`, { params }).then(({ data }) => data.data),
  version: (id) => axios.get(`${base}/risk-matrix-versions/${id}`).then(({ data }) => data.data),
  versions: (matrixId) => axios.get(`${base}/risk-matrices/${matrixId}/versions`).then(({ data }) => data.data),
  updateVersion: (id, payload) => axios.patch(`${base}/risk-matrix-versions/${id}`, payload).then(({ data }) => data.data),
  saveStructure: (id, payload) => axios.put(`${base}/risk-matrix-versions/${id}/structure`, payload).then(({ data }) => data.data),
  calculate: (payload) => axios.post(`${base}/risk-matrices/vep/calculate`, payload).then(({ data }) => data.data),
  validation: (id) => axios.get(`${base}/risk-matrix-versions/${id}/validation`).then(({ data }) => data),
  workflow: (id, action, payload = {}) => axios.post(`${base}/risk-matrix-versions/${id}/${action}`, payload).then(({ data }) => data),
  createVersion: (matrixId, payload) => axios.post(`${base}/risk-matrices/${matrixId}/versions`, payload).then(({ data }) => data),
  compare: (from, to) => axios.get(`${base}/risk-matrix-versions/${from}/compare/${to}`).then(({ data }) => data.data),
  imports: () => axios.get(`${base}/risk-matrices/imports`).then(({ data }) => data),
  previewImport: (formData) => axios.post(`${base}/risk-matrices/imports/preview`, formData, { headers: { "Content-Type": "multipart/form-data" } }).then(({ data }) => data.data),
  importBatch: (id) => axios.get(`${base}/risk-matrices/imports/${id}`).then(({ data }) => data.data),
  commitImport: (id, payload) => axios.post(`${base}/risk-matrices/imports/${id}/commit`, payload).then(({ data }) => data),
  programs: (params = {}) => axios.get(`${base}/preventive-programs`, { params }).then(({ data }) => data),
  updateAction: (id, payload) => axios.patch(`${base}/preventive-program-actions/${id}`, payload).then(({ data }) => data),
  verifyAction: (id, payload) => axios.post(`${base}/preventive-program-actions/${id}/verify`, payload).then(({ data }) => data),
  createCatalogItem: (payload) => axios.post(`${base}/risk-matrices/catalogs/items`, payload).then(({ data }) => data),
  toggleCatalogItem: (id, payload) => axios.patch(`${base}/risk-matrices/catalogs/items/${id}/status`, payload).then(({ data }) => data),
  uploadEvidence: (versionId, formData) => axios.post(`${base}/risk-matrix-versions/${versionId}/evidences`, formData, { headers: { "Content-Type": "multipart/form-data" } }).then(({ data }) => data),
  addParticipation: (versionId, payload) => axios.post(`${base}/risk-matrix-versions/${versionId}/participations`, payload).then(({ data }) => data),
  deleteParticipation: (id) => axios.delete(`${base}/risk-matrix-participations/${id}`).then(({ data }) => data),
};

export function isEmptyDraftRisk(risk = {}) {
  const hasText = [
    risk.specific_risk_code,
    risk.specific_risk_name,
    risk.possible_harm,
    risk.legal_or_protocol_reference,
    risk.notes,
    ...(risk.hazard_factors || []).flatMap((factor) => [factor.hazard_description, factor.risk_factor_description]),
    ...(risk.controls || []).flatMap((control) => [control.description, control.responsible_text]),
  ].some((value) => String(value || "").trim() !== "");

  return !hasText;
}

export function toPersistableStructure(processes = []) {
  return processes.map((process) => ({
    ...process,
    tasks: (process.tasks || []).map((task) => ({
      ...task,
      risks: (task.risks || []).filter((risk) => !isEmptyDraftRisk(risk)),
    })),
  }));
}

export function toEditableStructure(version) {
  return (version?.processes || []).map((process) => ({
    name: process.name,
    description: process.description,
    process_type: process.process_type,
    observations: process.observations,
    tasks: (process.tasks || []).map((task) => ({
      activity_name: task.activity_name,
      task_name: task.task_name,
      routine_type: task.routine_type,
      job_position_id: task.job_position_id,
      position_ids: (task.positions || []).map(({ id }) => id),
      job_position_text: task.job_position_text,
      location_id: task.location_id,
      specific_location: task.specific_location,
      zero_exposure_justification: task.zero_exposure_justification,
      observations: task.observations,
      exposures: (task.exposures || []).map(({ exposure_category_id, count, observations }) => ({ exposure_category_id, count, observations })),
      risks: (task.risks || []).map((risk) => ({
        risk_family_id: risk.risk_family_id,
        risk_catalog_item_id: risk.risk_catalog_item_id,
        specific_risk_code: risk.specific_risk_code,
        specific_risk_name: risk.specific_risk_name,
        possible_harm: risk.possible_harm,
        evaluation_method: risk.evaluation_method,
        declared_controlled_status: risk.declared_controlled_status,
        verified_controlled_status: risk.verified_controlled_status,
        legal_or_protocol_reference: risk.legal_or_protocol_reference,
        notes: risk.notes,
        hazard_factors: (risk.hazard_factors || []).map(({ category, hazard_description, risk_factor_description, source_catalog_id }) => ({ category, hazard_description, risk_factor_description, source_catalog_id })),
        assessments: (risk.assessments || []).filter(({ active }) => active).map((assessment) => ({
          phase: assessment.phase,
          method: assessment.method,
          probability: assessment.probability,
          consequence: assessment.consequence,
          protocol_id: assessment.protocol_id,
          protocol_version: assessment.protocol_version,
          exposure_value: assessment.exposure_value,
          exposure_unit: assessment.exposure_unit,
          result_value: assessment.result_value,
          result_level: assessment.result_level,
          instrument: assessment.instrument,
          evaluator: assessment.evaluator,
          instrument_date: assessment.instrument_date,
          next_measurement_at: assessment.next_measurement_at,
          assessment_notes: assessment.assessment_notes,
        })),
        controls: (risk.controls || []).map(({ control_stage, hierarchy_type, description, responsible_user_id, responsible_employee_id, responsible_text, status, priority, planned_start_date, due_date, periodicity_type, periodicity_value, progress_percentage, creates_program_action }) => ({
          control_stage, hierarchy_type, description, responsible_user_id, responsible_employee_id, responsible_text, status, priority, planned_start_date, due_date, periodicity_type, periodicity_value, progress_percentage, creates_program_action,
        })),
      })),
    })),
  }));
}
