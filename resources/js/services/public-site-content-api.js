import axios from "axios";

const TESTIMONIALS_URL = "/api/admin/testimonials";
const STUDENT_LIFE_URL = "/api/admin/student-life";
const INSTALLATIONS_URL = "/api/admin/installations";

const compactParams = (params = {}) => Object.fromEntries(
  Object.entries(params).filter(([, value]) => value !== "" && value !== null && value !== undefined),
);

const appendValue = (payload, key, value) => {
  if (value === undefined || value === null) return;
  payload.append(key, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
};

const appendFile = (payload, key, file) => {
  if (file instanceof File || file instanceof Blob) payload.append(key, file);
};

export const testimonialPayload = (testimonial = {}) => {
  const payload = new FormData();

  [
    "quote",
    "author_name",
    "author_role",
    "external_image_url",
    "image_alt",
    "status",
    "sort_order",
    "published_at",
  ].forEach((field) => appendValue(payload, field, testimonial[field]));
  ["active", "featured", "remove_image", "authorization_confirmed"].forEach((field) => {
    appendValue(payload, field, Boolean(testimonial[field]));
  });
  appendFile(payload, "image", testimonial.image);

  return payload;
};

export const studentLifePayload = (entry = {}) => {
  const payload = new FormData();

  [
    "title",
    "slug",
    "category",
    "summary",
    "body",
    "external_cover_image_url",
    "cover_image_alt",
    "event_date",
    "meta_title",
    "meta_description",
    "status",
    "sort_order",
    "published_at",
  ].forEach((field) => appendValue(payload, field, entry[field]));
  ["active", "featured", "remove_cover_image"].forEach((field) => {
    appendValue(payload, field, Boolean(entry[field]));
  });
  appendFile(payload, "cover_image", entry.cover_image);

  (entry.gallery || []).forEach((file) => appendFile(payload, "gallery[]", file));
  (entry.gallery_alts || []).forEach((alt) => appendValue(payload, "gallery_alts[]", alt));
  (entry.remove_gallery_image_ids || []).forEach((id) => {
    appendValue(payload, "remove_gallery_image_ids[]", id);
  });

  return payload;
};

export const installationPayload = (installation = {}) => {
  const payload = new FormData();

  [
    "title",
    "slug",
    "category",
    "summary",
    "body",
    "location_label",
    "capacity",
    "accessibility_notes",
    "icon",
    "meta_title",
    "meta_description",
    "status",
    "sort_order",
    "published_at",
    "cover_image_alt",
  ].forEach((field) => appendValue(payload, field, installation[field]));
  ["active", "featured", "remove_cover_image"].forEach((field) => {
    appendValue(payload, field, Boolean(installation[field]));
  });
  appendFile(payload, "cover_image", installation.cover_image);

  (installation.features || [])
    .map((feature) => String(feature || "").trim())
    .filter(Boolean)
    .forEach((feature) => appendValue(payload, "features[]", feature));
  (installation.gallery || []).forEach((file) => appendFile(payload, "gallery[]", file));
  (installation.gallery_alts || []).forEach((alt) => appendValue(payload, "gallery_alts[]", alt));
  (installation.remove_gallery_image_ids || []).forEach((id) => {
    appendValue(payload, "remove_gallery_image_ids[]", id);
  });
  (installation.gallery_order || []).forEach((id) => appendValue(payload, "gallery_order[]", id));

  return payload;
};

const multipartUpdate = (url, payload) => {
  payload.append("_method", "PUT");
  return axios.post(url, payload, { headers: { "Content-Type": "multipart/form-data" } });
};

export const listTestimonials = (params = {}) => axios.get(TESTIMONIALS_URL, {
  params: compactParams(params),
});
export const getTestimonialCatalogs = () => axios.get(`${TESTIMONIALS_URL}/catalogs`);
export const createTestimonial = (testimonial) => axios.post(
  TESTIMONIALS_URL,
  testimonialPayload(testimonial),
  { headers: { "Content-Type": "multipart/form-data" } },
);
export const updateTestimonial = (id, testimonial) => multipartUpdate(
  `${TESTIMONIALS_URL}/${id}`,
  testimonialPayload(testimonial),
);
export const removeTestimonial = (id) => axios.delete(`${TESTIMONIALS_URL}/${id}`);

export const listStudentLife = (params = {}) => axios.get(STUDENT_LIFE_URL, {
  params: compactParams(params),
});
export const getStudentLifeCatalogs = () => axios.get(`${STUDENT_LIFE_URL}/catalogs`);
export const createStudentLife = (entry) => axios.post(
  STUDENT_LIFE_URL,
  studentLifePayload(entry),
  { headers: { "Content-Type": "multipart/form-data" } },
);
export const updateStudentLife = (id, entry) => multipartUpdate(
  `${STUDENT_LIFE_URL}/${id}`,
  studentLifePayload(entry),
);
export const removeStudentLife = (id) => axios.delete(`${STUDENT_LIFE_URL}/${id}`);

export const listInstallations = (params = {}) => axios.get(INSTALLATIONS_URL, {
  params: compactParams(params),
});
export const getInstallationCatalogs = () => axios.get(`${INSTALLATIONS_URL}/catalogs`);
export const createInstallation = (installation) => axios.post(
  INSTALLATIONS_URL,
  installationPayload(installation),
  { headers: { "Content-Type": "multipart/form-data" } },
);
export const updateInstallation = (id, installation) => multipartUpdate(
  `${INSTALLATIONS_URL}/${id}`,
  installationPayload(installation),
);
export const removeInstallation = (id) => axios.delete(`${INSTALLATIONS_URL}/${id}`);
export const reorderInstallations = (items) => axios.patch(`${INSTALLATIONS_URL}/reorder`, { items });
