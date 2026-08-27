import axios from "axios";

const base = "/api/pedagogical-management";
const readOptions = { timeout: 15000 };

export const pedagogicalPolling = Object.freeze({
    intervalMs: 4000,
    maxAttempts: 15,
    staleAfterMs: 120000,
});

export function analysisIsStale(instrument, now = Date.now()) {
    if (!["pending_analysis", "processing"].includes(instrument?.status))
        return false;
    const reference =
        instrument?.latest_analysis?.started_at ||
        instrument?.latest_analysis?.created_at ||
        instrument?.updated_at ||
        instrument?.created_at;
    const timestamp = new Date(reference || "").getTime();
    return (
        Number.isFinite(timestamp) &&
        now - timestamp >= pedagogicalPolling.staleAfterMs
    );
}

export function pollingDecision({ pending, attempts, requestFailed = false }) {
    if (!pending) return "complete";
    if (requestFailed || attempts >= pedagogicalPolling.maxAttempts)
        return "pause";
    return "continue";
}

export const pedagogicalManagementApi = {
    catalogs: (params = {}) =>
        axios
            .get(`${base}/catalogs`, { ...readOptions, params })
            .then(({ data }) => data.data),
    list: (params = {}) =>
        axios
            .get(`${base}/instruments`, { ...readOptions, params })
            .then(({ data }) => data),
    create: (formData) =>
        axios
            .post(`${base}/instruments`, formData, {
                headers: { "Content-Type": "multipart/form-data" },
                timeout: 60000,
            })
            .then(({ data }) => data.data),
    show: (id) =>
        axios
            .get(`${base}/instruments/${id}`, readOptions)
            .then(({ data }) => data.data),
    update: (id, payload) =>
        axios
            .patch(`${base}/instruments/${id}`, payload)
            .then(({ data }) => data.data),
    archive: (id) =>
        axios
            .post(`${base}/instruments/${id}/archive`)
            .then(({ data }) => data),
    uploadVersion: (id, formData) =>
        axios
            .post(`${base}/instruments/${id}/files`, formData, {
                headers: { "Content-Type": "multipart/form-data" },
            })
            .then(({ data }) => data.data),
    analyze: (id) =>
        axios
            .post(`${base}/instruments/${id}/analyses`, null, {
                timeout: 20000,
            })
            .then(({ data }) => data.data),
    analysis: (instrumentId, analysisId) =>
        axios
            .get(
                `${base}/instruments/${instrumentId}/analyses/${analysisId}`,
                readOptions
            )
            .then(({ data }) => data.data),
    viewFile: (instrumentId, fileId) =>
        axios
            .get(`${base}/instruments/${instrumentId}/files/${fileId}/view`, {
                responseType: "blob",
            })
            .then(({ data }) => data),
    downloadFile: (instrumentId, fileId) =>
        axios
            .get(
                `${base}/instruments/${instrumentId}/files/${fileId}/download`,
                { responseType: "blob" }
            )
            .then(({ data }) => data),
    resolve: (resultId, payload) =>
        axios
            .post(`${base}/validation-results/${resultId}/resolve`, payload)
            .then(({ data }) => data.data),
    reopen: (resultId, payload) =>
        axios
            .post(`${base}/validation-results/${resultId}/reopen`, payload)
            .then(({ data }) => data.data),
    reviewQueue: (params = {}) =>
        axios
            .get(`${base}/document-review`, { ...readOptions, params })
            .then(({ data }) => data),
    decide: (instrumentId, payload) =>
        axios
            .post(`${base}/instruments/${instrumentId}/reviews`, payload)
            .then(({ data }) => data),
    requestAiReport: (instrumentId) =>
        axios
            .post(`${base}/instruments/${instrumentId}/ai-reports`, null, {
                timeout: 20000,
            })
            .then(({ data }) => data.data),
    aiReport: (instrumentId, reportId) =>
        axios
            .get(
                `${base}/instruments/${instrumentId}/ai-reports/${reportId}`,
                readOptions
            )
            .then(({ data }) => data.data),
    guidanceDocuments: (schoolId) =>
        axios
            .get(`${base}/guidance-documents`, {
                ...readOptions,
                params: { school_id: schoolId },
            })
            .then(({ data }) => data.data),
    createGuidanceDocument: (payload) =>
        axios
            .post(`${base}/guidance-documents`, payload)
            .then(({ data }) => data),
    updateGuidanceDocument: (id, payload) =>
        axios
            .patch(`${base}/guidance-documents/${id}`, payload)
            .then(({ data }) => data),
    coordinatorAssignments: (params) =>
        axios
            .get(`${base}/coordinator-assignments`, {
                ...readOptions,
                params,
            })
            .then(({ data }) => data.data),
    saveCoordinatorAssignments: (payload) =>
        axios
            .put(`${base}/coordinator-assignments`, payload)
            .then(({ data }) => data),
    statistics: (params) =>
        axios
            .get(`${base}/statistics`, { ...readOptions, params })
            .then(({ data }) => data.data),
    instrumentStatistics: (instrumentId) =>
        axios
            .get(`${base}/statistics/instruments/${instrumentId}`, readOptions)
            .then(({ data }) => data.data),
};

const printBase = "/api/pedagogical-print-center";

export const pedagogicalPrintApi = {
    list: (params = {}) =>
        axios
            .get(`${printBase}/requests`, { ...readOptions, params })
            .then(({ data }) => data),
    file: (id, download = false) =>
        axios
            .get(`${printBase}/requests/${id}/file`, {
                responseType: "blob",
                params: { download: download ? 1 : 0 },
            })
            .then(({ data }) => data),
    action: (id, action) =>
        axios
            .post(`${printBase}/requests/${id}/actions`, { action })
            .then(({ data }) => data),
};

export const workflowStatus = {
    submitted: ["En revisión", "info"],
    rectification_requested: ["Rectificación solicitada", "danger"],
    resubmitted: ["Rectificación enviada", "warning"],
    approved: ["Aprobado", "success"],
    approved_with_observations: ["Aprobado con observaciones", "success"],
    archived: ["Archivado", "neutral"],
};

export function workflowPresentation(status) {
    const [label, tone] = workflowStatus[status] || [
        String(status || "Sin estado"),
        "neutral",
    ];
    return { label, tone };
}

export const printStatus = {
    pending: ["Pendiente", "warning"],
    in_process: ["En preparación", "info"],
    printed: ["Impreso", "primary"],
    completed: ["Completado", "success"],
};

export function printPresentation(status) {
    const [label, tone] = printStatus[status] || [
        String(status || "Sin estado"),
        "neutral",
    ];
    return { label, tone };
}

export function saveBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = filename || "documento.pdf";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}

export function openBlob(blob) {
    const url = URL.createObjectURL(blob);
    window.open(url, "_blank", "noopener,noreferrer");
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
}

export const instrumentStatus = {
    draft: ["Borrador", "neutral"],
    uploaded: ["Archivo cargado", "info"],
    pending_analysis: ["En cola", "info"],
    processing: ["Aplicando reglas determinísticas", "info"],
    review_required: ["Con errores", "danger"],
    validated_with_warnings: ["Con sugerencias", "warning"],
    validated: ["Revisado", "success"],
    not_analyzable: ["No analizable", "danger"],
    failed: ["Falló", "danger"],
    archived: ["Archivado", "neutral"],
};

export function statusPresentation(status) {
    const [label, tone] = instrumentStatus[status] || [
        String(status || "Sin estado"),
        "neutral",
    ];
    return { label, tone };
}

const acceptedInstrumentMimes = {
    pdf: ["application/pdf"],
    docx: [
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/zip",
        "application/octet-stream",
    ],
};

export function instrumentFileExtension(fileOrName) {
    const name = typeof fileOrName === "string" ? fileOrName : fileOrName?.name;
    return String(name || "").toLowerCase().split(".").pop();
}

export function instrumentFileIcon(fileOrName) {
    return instrumentFileExtension(fileOrName) === "pdf"
        ? "bxs-file-pdf"
        : "bx-file";
}

export function validateInstrumentCandidate(file, maxKb = 20480) {
    if (!file) return "Selecciona un archivo PDF o Word (.docx).";
    const extension = String(file.name || "")
        .toLowerCase()
        .split(".")
        .pop();
    if (!Object.hasOwn(acceptedInstrumentMimes, extension))
        return "Solo se permiten archivos PDF o Word (.docx).";
    if (
        file.type &&
        !acceptedInstrumentMimes[extension].includes(file.type.toLowerCase())
    )
        return "El navegador no reconoce el contenido como un "
            + (extension === "docx" ? "Word .docx" : "PDF")
            + " válido.";
    if (file.size <= 0) return "El archivo está vacío.";
    if (file.size > Number(maxKb) * 1024)
        return `El archivo supera el máximo de ${Math.round(
            Number(maxKb) / 1024
        )} MB.`;
    return null;
}

export const validatePdfCandidate = validateInstrumentCandidate;

export function errorMessage(
    error,
    fallback = "No fue posible completar la operación."
) {
    const errors = error?.response?.data?.errors;
    if (errors && typeof errors === "object")
        return Object.values(errors).flat().join(" ");
    return error?.response?.data?.message || fallback;
}
