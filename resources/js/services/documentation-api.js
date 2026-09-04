import axios from "axios";

const BASE_URL = "/api/documentation";

const compactParams = (params = {}) => Object.fromEntries(
  Object.entries(params).filter(([, value]) => value !== "" && value !== null && value !== undefined),
);

const appendValue = (formData, key, value) => {
  if (value === undefined || value === null) return;
  formData.append(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
};

export const documentationPayload = (document = {}) => {
  const formData = new FormData();

  ["title", "category", "year", "version", "description"].forEach((field) => {
    appendValue(formData, field, document[field]);
  });
  appendValue(formData, "is_public", Boolean(document.is_public));
  appendValue(formData, "is_active", Boolean(document.is_active));

  if (document.file instanceof File || document.file instanceof Blob) {
    formData.append("file", document.file);
  }

  return formData;
};

export const listDocumentation = (params = {}) => axios.get(BASE_URL, {
  params: compactParams(params),
});

export const getDocumentationCatalogs = () => axios.get(`${BASE_URL}/catalogs`);

export const createDocumentation = (document) => axios.post(
  BASE_URL,
  documentationPayload(document),
  { headers: { "Content-Type": "multipart/form-data" } },
);

export const updateDocumentation = (id, document) => {
  const payload = documentationPayload(document);
  payload.append("_method", "PUT");

  return axios.post(`${BASE_URL}/${id}`, payload, {
    headers: { "Content-Type": "multipart/form-data" },
  });
};

export const removeDocumentation = (id) => axios.delete(`${BASE_URL}/${id}`);

export const downloadDocumentation = (id) => axios.get(`${BASE_URL}/${id}/download`, {
  responseType: "blob",
});

export const filenameFromDisposition = (disposition, fallback = "documento") => {
  const value = String(disposition || "");
  const encoded = value.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
  if (encoded) {
    try {
      return decodeURIComponent(encoded.replace(/["']/g, ""));
    } catch (error) {
      return encoded.replace(/["']/g, "");
    }
  }

  return value.match(/filename="?([^";]+)"?/i)?.[1]?.trim() || fallback;
};
