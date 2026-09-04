import axios from "axios";

const base = "/api/orientation";

export const orientationApi = {
  context(year) {
    return axios.get(`${base}/plans`, { params: { year } });
  },
  createPlan(payload) {
    return axios.post(`${base}/plans`, payload);
  },
  updatePlan(id, payload) {
    return axios.put(`${base}/plans/${id}`, payload);
  },
  action(id) {
    return axios.get(`${base}/actions/${id}`);
  },
  createAction(planId, payload) {
    return axios.post(`${base}/plans/${planId}/actions`, payload);
  },
  updateAction(id, payload) {
    return axios.put(`${base}/actions/${id}`, payload);
  },
  deleteAction(id) {
    return axios.delete(`${base}/actions/${id}`);
  },
  createRelatedPlan(planId, payload) {
    return axios.post(`${base}/plans/${planId}/related-plans`, payload);
  },
  updateRelatedPlan(id, payload) {
    return axios.put(`${base}/related-plans/${id}`, payload);
  },
  createActivity(actionId, payload) {
    return axios.post(`${base}/actions/${actionId}/activities`, payload);
  },
  updateActivity(id, payload) {
    return axios.put(`${base}/activities/${id}`, payload);
  },
  createEvidence(actionId, payload) {
    return axios.post(`${base}/actions/${actionId}/evidences`, payload, {
      headers: { "Content-Type": "multipart/form-data" },
    });
  },
  calendar(planId, start, end) {
    return axios.get(`${base}/plans/${planId}/calendar`, { params: { start, end } });
  },
  statistics(planId) {
    return axios.get(`${base}/plans/${planId}/statistics`);
  },
  calendarization(year) {
    return axios.get(`${base}/calendarization`, { params: { year } });
  },
  importCalendarizationReference(planId) {
    return axios.post(`${base}/plans/${planId}/calendarization/import-reference`);
  },
  createCalendarizationEntry(planId, payload) {
    return axios.post(`${base}/plans/${planId}/calendarization`, payload);
  },
  updateCalendarizationEntry(id, payload) {
    return axios.put(`${base}/calendarization/${id}`, payload);
  },
  deleteCalendarizationEntry(id) {
    return axios.delete(`${base}/calendarization/${id}`);
  },
};

export default orientationApi;
