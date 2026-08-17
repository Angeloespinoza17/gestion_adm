import axios from "axios";

export const LIBRO_DIGITAL_API_BASE = "/api/libro-digital/v1";

const mutationMethods = new Set(["post", "put", "patch"]);

const createIdempotencyKey = () => {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();

    return `lcd-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
};

const recordVersion = (record) =>
    record?._etag ??
    record?.etag ??
    record?.lock_version ??
    record?.version ??
    null;

const withConcurrencyHeaders = (method, record, headers = {}) => {
    const next = { ...headers };

    if (mutationMethods.has(method) && !next["Idempotency-Key"]) {
        next["Idempotency-Key"] = createIdempotencyKey();
    }

    const version = recordVersion(record);
    if (version !== null && version !== undefined && version !== "") {
        next["If-Match"] = String(version);
    }

    return next;
};

const attachResponseMetadata = (payload, response) => {
    if (!payload || typeof payload !== "object" || Array.isArray(payload))
        return payload;

    const etag = response.headers?.etag;
    if (etag && !Object.prototype.hasOwnProperty.call(payload, "_etag")) {
        Object.defineProperty(payload, "_etag", {
            value: etag,
            enumerable: true,
            configurable: true,
        });
    }

    return payload;
};

export class LibroDigitalApiError extends Error {
    constructor(error) {
        const payload = error?.response?.data || {};
        super(
            payload.message ||
                error?.message ||
                "No fue posible completar la operación."
        );
        this.name = "LibroDigitalApiError";
        this.status = error?.response?.status || 0;
        this.code = payload.code || null;
        this.details = payload.details || payload.errors || null;
        this.correlationId =
            payload.correlation_id ||
            error?.response?.headers?.["x-correlation-id"] ||
            null;
        this.current = payload.current || payload.data?.current || null;
        this.original = error;
    }

    get isConflict() {
        return this.status === 409 || this.status === 412;
    }
}

const request = async (method, path, options = {}) => {
    try {
        const response = await axios({
            method,
            url: `${LIBRO_DIGITAL_API_BASE}${path}`,
            params: options.params,
            data: options.data,
            signal: options.signal,
            responseType: options.responseType,
            headers: withConcurrencyHeaders(
                method,
                options.record,
                options.headers
            ),
        });

        return attachResponseMetadata(response.data, response);
    } catch (error) {
        if (error?.code === "ERR_CANCELED") throw error;
        throw new LibroDigitalApiError(error);
    }
};

const get = (path, params, signal) => request("get", path, { params, signal });
const post = (path, data, record, signal) =>
    request("post", path, { data, record, signal });
const put = (path, data, record) => request("put", path, { data, record });
const patch = (path, data, record) => request("patch", path, { data, record });

const blobFilename = (headers, fallback) => {
    const disposition = headers?.["content-disposition"] || "";
    const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    if (encoded) {
        try {
            return decodeURIComponent(encoded.replaceAll('"', ""));
        } catch {
            return fallback;
        }
    }

    return disposition.match(/filename="?([^";]+)"?/i)?.[1] || fallback;
};

const downloadBlob = (blob, filename) => {
    const objectUrl = window.URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.style.display = "none";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 1000);
};

const download = async (url, fallbackFilename, signal) => {
    try {
        const response = await axios.get(url, { responseType: "blob", signal });
        if (signal?.aborted) {
            const cancellation = new Error("La descarga fue cancelada.");
            cancellation.name = "AbortError";
            cancellation.code = "ERR_CANCELED";
            throw cancellation;
        }
        downloadBlob(
            response.data,
            blobFilename(response.headers, fallbackFilename)
        );
    } catch (error) {
        if (error?.code === "ERR_CANCELED" || error?.name === "AbortError") {
            throw error;
        }
        throw new LibroDigitalApiError(error);
    }
};

const saveJson = (payload, filename) => {
    const blob = new Blob([JSON.stringify(payload, null, 2)], {
        type: "application/json;charset=utf-8",
    });
    downloadBlob(blob, filename);
};

const subjectMutationPayload = (payload = {}) =>
    Object.fromEntries(
        ["school_id", "name", "code", "area", "color", "active"]
            .filter((key) => payload[key] !== undefined)
            .map((key) => [key, payload[key]])
    );

export const libroDigitalApi = {
    overview: (params, signal) => get("/overview", params, signal),
    catalogs: (params, signal) => get("/catalogs", params, signal),

    books: (params, signal) => get("/books", params, signal),
    book: (bookId, params, signal) => get(`/books/${bookId}`, params, signal),
    createBook: (payload) => post("/books", payload),
    updateBook: (book, payload) =>
        patch(
            `/books/${book.id}`,
            { ...payload, lock_version: recordVersion(book) },
            book
        ),
    bookAction: (book, action, payload = {}) =>
        post(
            `/books/${book.id}/${action}`,
            { ...payload, lock_version: recordVersion(book) },
            book
        ),
    roster: (bookId, params, signal) =>
        get(`/books/${bookId}/roster`, params, signal),

    subjects: (params, signal) => get("/subjects", params, signal),
    createSubject: (payload) =>
        post("/subjects", subjectMutationPayload(payload)),
    updateSubject: (subject, payload) =>
        patch(
            `/subjects/${subject.id}`,
            {
                ...subjectMutationPayload(payload),
                lock_version: recordVersion(subject),
            },
            subject
        ),

    sessions: (bookId, params, signal) =>
        get(`/books/${bookId}/sessions`, params, signal),
    session: (sessionId, params, signal) =>
        get(`/sessions/${sessionId}`, params, signal),
    createSession: (bookId, payload) =>
        post(`/books/${bookId}/sessions`, payload),
    updateSession: (session, payload) =>
        patch(
            `/sessions/${session.id}`,
            { ...payload, lock_version: recordVersion(session) },
            session
        ),
    cancelSession: (session, payload) =>
        post(
            `/sessions/${session.id}/cancel`,
            { ...payload, lock_version: recordVersion(session) },
            session
        ),
    prepareSignature: (session) =>
        post(
            `/sessions/${session.id}/prepare-signature`,
            { lock_version: recordVersion(session) },
            session
        ),
    signSession: (session, payload) =>
        post(
            `/sessions/${session.id}/sign`,
            { ...payload, lock_version: recordVersion(session) },
            session
        ),

    lessonRecord: (sessionId, signal) =>
        get(`/sessions/${sessionId}/lesson-record`, undefined, signal),
    updateLessonRecord: (session, record, payload) =>
        put(
            `/sessions/${session.id}/lesson-record`,
            { ...payload, lock_version: recordVersion(record || session) },
            record || session
        ),
    curriculumObjectives: (params, signal) =>
        get("/curriculum/objectives", params, signal),
    curriculumVisualization: (params, signal) =>
        get("/curriculum/objectives/visualization", params, signal),
    curriculumObjective: (objectiveId, params, signal) =>
        get(`/curriculum/objectives/${objectiveId}`, params, signal),
    curriculumCoverage: (bookId, params, signal) =>
        get(`/books/${bookId}/curriculum-coverage`, params, signal),
    curriculumImports: (params, signal) =>
        get("/curriculum/imports", params, signal),
    curriculumImport: (record, signal) =>
        get(
            `/curriculum/imports/${record.public_id || record.id}`,
            undefined,
            signal
        ),
    validateCurriculumImport: (formData) =>
        post("/curriculum/imports/validate", formData),
    approveCurriculumImport: (record, payload) =>
        post(
            `/curriculum/imports/${record.public_id || record.id}/approve`,
            { ...payload, lock_version: recordVersion(record) },
            record
        ),
    activateCurriculumImport: (record, payload) =>
        payload instanceof FormData
            ? (() => {
                  payload.set(
                      "lock_version",
                      String(recordVersion(record) ?? "")
                  );
                  return post(
                      `/curriculum/imports/${
                          record.public_id || record.id
                      }/activate`,
                      payload,
                      record
                  );
              })()
            : post(
                  `/curriculum/imports/${
                      record.public_id || record.id
                  }/activate`,
                  { ...payload, lock_version: recordVersion(record) },
                  record
              ),
    downloadCurriculumImportTemplate: () =>
        download(
            `${LIBRO_DIGITAL_API_BASE}/curriculum/imports/template`,
            "plantilla-importacion-curriculo-nt1-4m.xlsx"
        ),

    attendance: (sessionId, signal) =>
        get(`/sessions/${sessionId}/attendance`, undefined, signal),
    updateAttendance: (session, attendance, payload) =>
        put(
            `/sessions/${session.id}/attendance`,
            { ...payload, lock_version: recordVersion(attendance || session) },
            attendance || session
        ),
    completeAttendance: (session, attendance) =>
        post(
            `/sessions/${session.id}/attendance/complete`,
            { lock_version: recordVersion(attendance || session) },
            attendance || session
        ),
    dailyAttendance: (bookId, params, signal) =>
        get(`/books/${bookId}/attendance/daily`, params, signal),
    monthlyAttendance: (bookId, params, signal) =>
        get(`/books/${bookId}/attendance/monthly`, params, signal),
    closeDailyAttendance: (book, payload) =>
        post(
            `/books/${book.id}/attendance/daily-close`,
            { ...payload, lock_version: recordVersion(book) },
            book
        ),
    closeMonthlyAttendance: (book, payload) =>
        post(
            `/books/${book.id}/attendance/monthly-close`,
            { ...payload, lock_version: recordVersion(book) },
            book
        ),
    reconcileAttendance: (book, payload) =>
        post(
            `/books/${book.id}/attendance/reconcile`,
            { ...payload, lock_version: recordVersion(book) },
            book
        ),

    assessments: (bookId, params, signal) =>
        get(`/books/${bookId}/assessments`, params, signal),
    assessment: (assessmentId, signal) =>
        get(`/assessments/${assessmentId}`, undefined, signal),
    createAssessment: (bookId, payload) =>
        post(`/books/${bookId}/assessments`, payload),
    updateAssessment: (assessment, payload) =>
        patch(
            `/assessments/${assessment.id}`,
            { ...payload, lock_version: recordVersion(assessment) },
            assessment
        ),
    updateAssessmentResults: (assessment, payload) =>
        put(
            `/assessments/${assessment.id}/results`,
            { ...payload, lock_version: recordVersion(assessment) },
            assessment
        ),
    closeAssessment: (assessment) =>
        post(
            `/assessments/${assessment.id}/close`,
            { lock_version: recordVersion(assessment) },
            assessment
        ),

    pieRecords: (bookId, params, signal) =>
        get(`/books/${bookId}/pie`, params, signal),
    pieRecord: (recordId, params, signal) =>
        get(`/pie/${recordId}`, params, signal),
    createPieRecord: (bookId, payload) => post(`/books/${bookId}/pie`, payload),
    updatePieRecord: (record, payload) =>
        patch(
            `/pie/${record.id}`,
            { ...payload, lock_version: recordVersion(record) },
            record
        ),
    coexistence: (studentId, params, signal) =>
        get(`/students/${studentId}/coexistence`, params, signal),
    coexistenceRecord: (recordId, params, signal) =>
        get(`/coexistence/${recordId}`, params, signal),
    createCoexistence: (studentId, payload) =>
        post(`/students/${studentId}/coexistence`, payload),
    updateCoexistence: (record, payload) =>
        patch(
            `/coexistence/${record.id}`,
            { ...payload, lock_version: recordVersion(record) },
            record
        ),

    absenceCases: (params, signal) => get("/absence-cases", params, signal),
    createAbsenceCase: (payload) => post("/absence-cases", payload),
    addAbsenceAction: (record, payload) =>
        post(
            `/absence-cases/${record.id}/actions`,
            { ...payload, lock_version: recordVersion(record) },
            record
        ),
    resolveAbsenceCase: (record, payload) =>
        post(
            `/absence-cases/${record.id}/resolve`,
            { ...payload, lock_version: recordVersion(record) },
            record
        ),

    earlyWithdrawals: (bookId, params, signal) =>
        get(`/books/${bookId}/early-withdrawals`, params, signal),
    createEarlyWithdrawal: (bookId, payload) =>
        post(`/books/${bookId}/early-withdrawals`, payload),
    returnEarlyWithdrawal: (record, payload) =>
        post(
            `/early-withdrawals/${record.id}/return`,
            { ...payload, lock_version: recordVersion(record) },
            record
        ),

    amendments: (params, signal) => get("/amendments", params, signal),
    createAmendment: (payload) => post("/amendments", payload),
    approveAmendment: (record, payload) =>
        post(`/amendments/${record.id}/approve`, payload, record),
    rejectAmendment: (record, payload) =>
        post(`/amendments/${record.id}/reject`, payload, record),
    applyAmendment: (record) =>
        post(`/amendments/${record.id}/apply`, {}, record),

    parvulariaBooks: (params, signal) =>
        get("/parvularia/books", params, signal),
    createParvulariaBook: (payload) => post("/parvularia/books", payload),
    parvulariaBook: (bookId, signal) =>
        get(`/parvularia/books/${bookId}`, undefined, signal),
    parvulariaPlanning: (bookId, params, signal) =>
        get(`/parvularia/books/${bookId}/planning`, params, signal),
    createParvulariaPlanning: (bookId, payload) =>
        post(`/parvularia/books/${bookId}/planning`, payload),
    parvulariaEvaluations: (bookId, params, signal) =>
        get(`/parvularia/books/${bookId}/evaluations`, params, signal),
    createParvulariaEvaluation: (bookId, payload) =>
        post(`/parvularia/books/${bookId}/evaluations`, payload),
    parvulariaLateArrivals: (bookId, params, signal) =>
        get(`/parvularia/books/${bookId}/late-arrivals`, params, signal),
    createParvulariaLateArrival: (bookId, payload) =>
        post(`/parvularia/books/${bookId}/late-arrivals`, payload),

    statistics: (params, signal) => get("/statistics", params, signal),
    reportCatalog: (params, signal) => get("/reports", params, signal),
    generateReport: (payload, signal) =>
        post("/reports", payload, undefined, signal),
    report: (reportId, signal) =>
        get(`/reports/${reportId}`, undefined, signal),
    reportHistory: (params, signal) => get("/reports/history", params, signal),

    edeVersions: (signal) => get("/ede/versions", undefined, signal),
    importEdeStandard: (formData) => post("/ede/import-standard", formData),
    edeMappings: (params, signal) => get("/ede/mappings", params, signal),
    edeExports: (params, signal) => get("/ede/exports", params, signal),
    createEdeExport: (payload) => post("/ede/exports", payload),
    edeExport: (exportId, signal) =>
        get(`/ede/exports/${exportId}`, undefined, signal),
    validateEdeExport: (record) =>
        post(
            `/ede/exports/${record.id}/validate`,
            { lock_version: recordVersion(record) },
            record
        ),
    edeExportReport: (record, signal) =>
        get(`/ede/exports/${record.id}/report`, undefined, signal),

    audit: (params, signal) => get("/audit", params, signal),
    verifyAudit: (payload) => post("/audit/verify", payload),
    configuration: (params, signal) => get("/configuration", params, signal),
    updateConfiguration: (configuration, payload) =>
        put(
            "/configuration",
            { ...payload, lock_version: recordVersion(configuration) },
            configuration
        ),

    download,
    saveJson,
    createIdempotencyKey,
    recordVersion,
};

export const waitForLibroDigitalJob = async (job, loader, options = {}) => {
    const pendingStatuses = options.pendingStatuses || [
        "pending",
        "queued",
        "processing",
        "generating",
        "validation_queued",
        "validating",
    ];
    const timeoutMs = options.timeoutMs ?? 180000;
    const intervalMs = options.intervalMs ?? 1500;
    const startedAt = Date.now();
    let current = job;

    while (pendingStatuses.includes(current?.status)) {
        if (Date.now() - startedAt > timeoutMs) {
            const error = new Error(
                "La tarea continúa ejecutándose en segundo plano."
            );
            error.code = "LCD_JOB_TIMEOUT";
            throw error;
        }

        await new Promise((resolve) => window.setTimeout(resolve, intervalMs));
        current = await loader(current);
        options.onProgress?.(current);
    }

    if (["failed", "rejected", "invalid"].includes(current?.status)) {
        throw new Error(
            current.failure_message ||
                current.message ||
                "La tarea no pudo completarse."
        );
    }

    return current;
};
