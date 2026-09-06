import axios from "axios";

const base = "/api/convivencia";

export const convivenciaPlanApi = {
  workspace(year) {
    return axios.get(`${base}/annual-plans/workspace`, { params: { year } });
  },
  createPlan(payload) {
    return axios.post(`${base}/plans`, payload);
  },
  updatePlan(id, payload) {
    return axios.put(`${base}/plans/${id}`, payload);
  },
  cloneToYear(id, payload) {
    return axios.post(`${base}/plans/${id}/clone-to-year`, payload);
  },
  exportData(id) {
    return axios.get(`${base}/plans/${id}/export-data`);
  },
  action(id) {
    return axios.get(`${base}/plan-actions/${id}`);
  },
  createAction(planId, payload) {
    return axios.post(`${base}/plans/${planId}/actions`, payload);
  },
  updateAction(id, payload) {
    return axios.put(`${base}/plan-actions/${id}`, payload);
  },
  deleteAction(id, planRevision) {
    return axios.delete(`${base}/plan-actions/${id}`, { data: { plan_revision: planRevision } });
  },
  createActivity(actionId, payload) {
    return axios.post(`${base}/plan-actions/${actionId}/activities`, payload);
  },
  updateActivity(id, payload) {
    return axios.put(`${base}/plan-activities/${id}`, payload);
  },
  deleteActivity(id, revision) {
    return axios.delete(`${base}/plan-activities/${id}`, { data: { revision } });
  },
  uploadEvidence(activityId, payload) {
    return axios.post(`${base}/plan-activities/${activityId}/attachments`, payload, {
      headers: { "Content-Type": "multipart/form-data" },
    });
  },
  uploadPlanDocument(planId, payload) {
    return axios.post(`${base}/plans/${planId}/attachments`, payload, {
      headers: { "Content-Type": "multipart/form-data" },
    });
  },
  calendar(planId, start, end) {
    return axios.get(`${base}/plans/${planId}/calendar`, { params: { start, end } });
  },
  restoreVersion(planId, versionId, revision) {
    return axios.post(`${base}/plans/${planId}/versions/${versionId}/restore`, { revision });
  },
};

export default convivenciaPlanApi;
