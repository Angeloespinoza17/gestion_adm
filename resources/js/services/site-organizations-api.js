import axios from "axios";

const BASE_URL = "/api/admin/site-organizations";

const compactParams = (params = {}) => Object.fromEntries(
  Object.entries(params).filter(([, value]) => value !== "" && value !== null && value !== undefined),
);

export const listSiteOrganizations = (type, params = {}) => axios.get(BASE_URL, {
  params: compactParams({ type, ...params }),
});

export const getSiteOrganizationCatalogs = (type, params = {}) => axios.get(`${BASE_URL}/catalogs`, {
  params: compactParams({ type, ...params }),
});

export const getSiteOrganization = (type, id) => axios.get(`${BASE_URL}/${type}/${id}`);

export const createSiteOrganization = (type, payload) => axios.post(BASE_URL, { type, ...payload });

export const updateSiteOrganization = (type, id, payload) => axios.put(
  `${BASE_URL}/${type}/${id}`,
  payload,
);

export const archiveSiteOrganization = (type, id) => axios.delete(`${BASE_URL}/${type}/${id}`);

export const searchOrganizationStudents = (params = {}) => axios.get(`${BASE_URL}/students`, {
  params: compactParams({ year: params.year, search: params.search, page: params.page }),
});

export const searchOrganizationStaff = (params = {}) => axios.get(`${BASE_URL}/staff`, {
  params: compactParams({ type: params.type, search: params.search, page: params.page }),
});

export const createSiteOrganizationRole = (payload) => axios.post(`${BASE_URL}/roles`, payload);

export const updateSiteOrganizationRole = (id, payload) => axios.put(`${BASE_URL}/roles/${id}`, payload);

export { BASE_URL as SITE_ORGANIZATIONS_URL };
