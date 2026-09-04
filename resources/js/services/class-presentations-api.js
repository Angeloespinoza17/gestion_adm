import axios from "axios";

const base = "/api/gestion-pedagogica";
const read = { timeout: 20000 };

export const classPresentationsApi = {
    options: (params = {}) => axios.get(`${base}/generador-clases/opciones`, { ...read, params }).then(({ data }) => data.data),
    subjects: (courseId, params) => axios.get(`${base}/cursos/${courseId}/asignaturas`, { ...read, params }).then(({ data }) => data.data),
    units: (subjectId, params) => axios.get(`${base}/asignaturas/${subjectId}/unidades`, { ...read, params }).then(({ data }) => data.data),
    objectives: (unitId, params) => axios.get(`${base}/unidades/${unitId}/objetivos`, { ...read, params }).then(({ data }) => data.data),
    titles: (payload) => axios.post(`${base}/generador-clases/titulos`, payload).then(({ data }) => data.data),
    list: (params = {}) => axios.get(`${base}/presentaciones`, { ...read, params }).then(({ data }) => data),
    create: (payload) => axios.post(`${base}/presentaciones`, payload, { headers: { "Content-Type": "multipart/form-data" }, timeout: 60000 }).then(({ data }) => data.data),
    show: (id) => axios.get(`${base}/presentaciones/${id}`, read).then(({ data }) => data.data),
    status: (id) => axios.get(`${base}/presentaciones/${id}/estado`, read).then(({ data }) => data.data),
    regenerate: (id) => axios.post(`${base}/presentaciones/${id}/regenerar`).then(({ data }) => data.data),
    retry: (id) => axios.post(`${base}/presentaciones/${id}/reintentar`).then(({ data }) => data.data),
    archive: (id) => axios.post(`${base}/presentaciones/${id}/archivar`).then(({ data }) => data),
    download: (url) => axios.get(url, { responseType: "blob", timeout: 60000 }).then(({ data }) => data),
    canvaConnection: (params = {}) => axios.get(`${base}/canva/conexion`, { ...read, params }).then(({ data }) => data.data ?? data),
    beginCanvaAuthorization: (payload) => axios.post(`${base}/canva/autorizacion`, payload).then(({ data }) => data.data ?? data),
    disconnectCanva: (payload) => axios.delete(`${base}/canva/conexion`, { data: payload }).then(({ data }) => data.data ?? data),
    canvaTemplates: (params = {}) => axios.get(`${base}/canva/plantillas`, { ...read, params }).then(({ data }) => data.data ?? data),
    validateCanvaTemplate: (templateId, payload) => axios.post(`${base}/canva/plantillas/${encodeURIComponent(templateId)}/validar`, payload).then(({ data }) => data.data ?? data),
    canvaEditLink: (id) => axios.post(`${base}/presentaciones/${id}/canva/enlace-edicion`).then(({ data }) => data.data ?? data),
    syncCanvaDesign: (id) => axios.post(`${base}/presentaciones/${id}/canva/sincronizar`).then(({ data }) => data.data ?? data),
};

export const classPresentationStatus = Object.freeze({
    draft: ["Borrador", "neutral"], queued: ["En cola", "info"],
    preparing_content: ["Preparando contenido", "info"], generating_presentation: ["Generando presentación", "primary"],
    generating_teacher_guide: ["Generando guía docente", "primary"], generating_guide: ["Generando guía docente", "primary"],
    sending_to_canva: ["Enviando contenido a Canva", "primary"], creating_canva_design: ["Canva creando el diseño", "primary"],
    exporting_canva: ["Exportando desde Canva", "warning"], exporting_presentation: ["Exportando presentación", "warning"],
    validating: ["Validando", "warning"], ready: ["Lista", "success"], failed: ["Fallida", "danger"], archived: ["Archivada", "neutral"],
});

const pendingStatuses = new Set([
    "queued", "preparing_content", "generating_presentation", "generating_teacher_guide", "generating_guide",
    "sending_to_canva", "creating_canva_design", "exporting_canva", "exporting_presentation", "validating",
]);

export function isPresentationPending(item) {
    if (typeof item?.is_pending === "boolean") return item.is_pending;
    if (pendingStatuses.has(String(item?.status || ""))) return true;
    const provider = item?.presentation_provider || item?.provider || item?.configuration?.presentation_provider;
    const canvaStatus = item?.canva?.status;
    return provider === "canva"
        && String(item?.status || "") === "ready"
        && (canvaStatus == null || ["pending", "submitting", "in_progress"].includes(String(canvaStatus)));
}

export function statusPresentation(status) {
    const [label, tone] = classPresentationStatus[status] || [status || "Sin estado", "neutral"];
    return { label, tone };
}
