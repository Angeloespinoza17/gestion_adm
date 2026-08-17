<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    LIBRO_DIGITAL_API_BASE,
    libroDigitalApi,
    waitForLibroDigitalJob,
} from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import CurriculumVisualizationPanel from "../curriculum-visualizations/CurriculumVisualizationPanel.vue";
import {
    cleanParams,
    errorMessage,
    humanize,
    payloadData,
    payloadItems,
    payloadMeta,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const error = ref(null);
const objectives = ref([]);
const responseMeta = ref({});
const currentPage = ref(Math.max(1, Number(route.query.page) || 1));
const perPage = ref(25);
const selectedObjective = ref(null);
const detailVisible = ref(false);
const detailLoading = ref(false);
const detailError = ref(null);
const exportVisible = ref(false);
const exportFormat = ref("pdf");
const exporting = ref(false);
const exportDownloading = ref(false);
const exportJob = ref(null);
const exportError = ref(null);
const exportTimedOut = ref(false);
const exportDownloaded = ref(false);
const exportJobSignature = ref("");
const queryDraft = ref(String(route.query.q || route.query.query || ""));
const searchPending = ref(false);
const exportTrigger = ref(null);
let listController = null;
let detailController = null;
let exportController = null;
let exportGeneration = 0;
let searchTimer = null;

const routeValue = (key, fallback = "") => {
    const value = route.query[key];
    return String(
        Array.isArray(value) ? value[0] ?? fallback : value ?? fallback
    );
};

const filters = reactive({
    level_code: routeValue("level_code"),
    grade_code: routeValue("grade_code"),
    curriculum_track: routeValue("curriculum_track", routeValue("track")),
    subject_code: routeValue("subject_code", routeValue("subject_id")),
    objective_type: routeValue("objective_type", routeValue("type")),
    axis_code: routeValue("axis_code"),
    source: routeValue("source"),
    status: routeValue("status", "all"),
    query: queryDraft.value,
});

const capabilityEntries = computed(() => Object.keys(props.capabilities || {}));
const canView = computed(() => {
    if (!capabilityEntries.value.length) return true;
    return Boolean(
        props.capabilities.can_view_curriculum ||
            props.capabilities.can_view_curriculum_objectives ||
            props.capabilities.can_view_books ||
            props.capabilities.can_manage_lesson ||
            props.capabilities["*"] ||
            props.capabilities.is_super_admin
    );
});
const canManageImports = computed(() =>
    Boolean(
        props.capabilities.can_view_curriculum_imports ||
            props.capabilities.can_manage_curriculum_imports ||
            props.capabilities.can_approve_curriculum_imports ||
            props.capabilities.can_activate_curriculum_imports ||
            props.capabilities["*"] ||
            props.capabilities.is_super_admin
    )
);
const canExport = computed(() =>
    Boolean(
        props.capabilities?.can_export_reports ||
            props.capabilities?.["*"] ||
            props.capabilities?.is_super_admin
    )
);
const contextReady = computed(
    () => props.context.school_id && props.context.academic_year_id
);
const bookScoped = computed(() => Boolean(props.context.book_id));
const scopedScheduleSubjectId = computed(() =>
    bookScoped.value ? props.context.schedule_subject_id || null : null
);
const effectiveStatus = computed(() =>
    bookScoped.value ? "active" : filters.status || "all"
);
const scopeKey = computed(
    () =>
        `${props.context.school_id || ""}:${
            props.context.academic_year_id || ""
        }:${props.context.course_section_id || ""}:${
            props.context.book_id || ""
        }:${scopedScheduleSubjectId.value || ""}`
);
const meta = computed(() => responseMeta.value || {});
const pagination = computed(() => meta.value.pagination || meta.value || {});
const facets = computed(() => meta.value.facets || {});
const summary = computed(() => meta.value.summary || meta.value.kpis || {});
const blocker = computed(
    () =>
        meta.value.compliance_blocker ||
        meta.value.context?.compliance_blocker ||
        null
);
const catalogStatus = computed(
    () =>
        meta.value.context?.catalog_status ||
        meta.value.catalog_status ||
        (blocker.value ? "not_imported" : "ready")
);
const blockerPresentation = computed(() => {
    const presentations = {
        schema_pending: {
            title: "Esquema curricular pendiente",
            message:
                "La instalación aún no tiene disponibles las tablas curriculares requeridas. Ejecuta las migraciones antes de importar el catálogo.",
        },
        not_imported: {
            title: "Catálogo aún no importado",
            message:
                "No existe un catálogo curricular oficial importado y activado para este establecimiento y año académico.",
        },
        validated: {
            title: "Catálogo pendiente de activación",
            message:
                "La importación fue validada, pero todavía debe aprobarse y activarse para quedar disponible.",
        },
        approved: {
            title: "Catálogo aprobado, pendiente de activación",
            message:
                "La importación ya fue aprobada. Falta activar su versión para habilitar los objetivos del año académico.",
        },
        invalid: {
            title: "Importación curricular con observaciones",
            message:
                "La última importación no superó la validación. Revisa sus errores antes de aprobarla o activarla.",
        },
        integrity_blocked: {
            title: "Integridad curricular bloqueada",
            message:
                "La activación existe, pero sus fuentes o huellas de integridad no cumplen las condiciones mínimas de trazabilidad.",
        },
        pending: {
            title: "Importación curricular en proceso",
            message:
                "Existe una importación pendiente de validación, aprobación o activación.",
        },
        uploaded: {
            title: "Importación pendiente de validación",
            message:
                "El archivo fue recibido y todavía debe completar su validación normativa.",
        },
    };
    const presentation =
        presentations[catalogStatus.value] || presentations.pending;
    return {
        title: presentation.title,
        message: presentation.message,
    };
});
const totalRows = computed(() =>
    Number(
        pagination.value.total ?? summary.value.total ?? objectives.value.length
    )
);
const lastPage = computed(() =>
    Math.max(
        1,
        Number(
            pagination.value.last_page ??
                Math.ceil(
                    totalRows.value /
                        (pagination.value.per_page || perPage.value)
                )
        ) || 1
    )
);
const hasActiveFilters = computed(() =>
    Boolean(
        filters.level_code ||
            filters.grade_code ||
            filters.curriculum_track ||
            (!bookScoped.value && filters.subject_code) ||
            filters.objective_type ||
            filters.axis_code ||
            filters.source ||
            filters.query ||
            (!bookScoped.value && filters.status && filters.status !== "all")
    )
);

const firstPresent = (object, keys, fallback = null) => {
    for (const key of keys) {
        if (object?.[key] !== null && object?.[key] !== undefined) {
            return object[key];
        }
    }
    return fallback;
};
const numberFrom = (object, keys, fallback = 0) => {
    const value = Number(firstPresent(object, keys, fallback));
    return Number.isFinite(value) ? value : fallback;
};
const formatNumber = (value) => Number(value || 0).toLocaleString("es-CL");

const facetRaw = (...keys) => {
    for (const key of keys) {
        if (facets.value?.[key] !== undefined) return facets.value[key];
    }
    return [];
};
const normalizeFacet = (raw, labeler = null) => {
    if (!raw) return [];
    const rows = Array.isArray(raw)
        ? raw
        : Object.entries(raw).map(([value, count]) =>
              typeof count === "object" && count !== null
                  ? { value, ...count }
                  : { value, count }
          );

    return rows
        .map((item) => {
            const record =
                typeof item === "object" && item !== null ? item : {};
            const candidate = firstPresent(record, [
                "value",
                "code",
                "id",
                "key",
                "subject_code",
                "axis_code",
                "objective_type",
            ]);
            const value = String(
                candidate ??
                    (Object.prototype.hasOwnProperty.call(record, "value")
                        ? "COMMON"
                        : typeof item === "object"
                        ? ""
                        : item) ??
                    ""
            );
            if (!value) return null;
            const providedLabel = firstPresent(record, [
                "label",
                "name",
                "text",
                "display_name",
            ]);
            const label = String(
                labeler
                    ? labeler(value, providedLabel)
                    : providedLabel || humanize(value)
            );
            const count = firstPresent(record, [
                "count",
                "total",
                "objectives_count",
            ]);
            return {
                value,
                text:
                    count === null || count === undefined
                        ? label
                        : `${label} · ${formatNumber(count)}`,
            };
        })
        .filter(Boolean);
};
const ensureSelected = (options, value, labeler = humanize) => {
    if (
        !value ||
        options.some((option) => String(option.value) === String(value))
    ) {
        return options;
    }
    return [...options, { value, text: labeler(value) }];
};

const levelLabels = {
    PARVULARIA: "Educación Parvularia",
    BASICA: "Educación Básica",
    MEDIA: "Educación Media",
};
const gradeLabels = {
    NT1: "NT1 · Prekínder",
    NT2: "NT2 · Kínder",
    "1B": "1.º básico",
    "2B": "2.º básico",
    "3B": "3.º básico",
    "4B": "4.º básico",
    "5B": "5.º básico",
    "6B": "6.º básico",
    "7B": "7.º básico",
    "8B": "8.º básico",
    "1M": "1.º medio",
    "2M": "2.º medio",
    "3M": "3.º medio",
    "4M": "4.º medio",
};
const trackLabels = {
    GENERAL: "Formación General",
    HC: "Humanístico-Científica",
    TP: "Técnico-Profesional",
    ARTISTICA: "Artística",
    PARVULARIA: "Educación Parvularia",
    COMMON: "Común / sin diferenciación",
    LCPOA: "Lengua y Cultura de Pueblos Originarios",
    LIND: "Lengua Indígena",
};
const typeLabels = {
    OA: "OA · Objetivo de aprendizaje",
    OAT: "OAT · Objetivo transversal",
    OAH: "OAH · Habilidades",
    OAA: "OAA · Actitudes",
    OAC: "OAC · Conocimiento y comprensión",
    OAG: "OAG · Objetivo general",
};

const fallbackLevels = Object.keys(levelLabels).map((value) => ({
    value,
    text: levelLabels[value],
}));
const fallbackGrades = Object.keys(gradeLabels).map((value) => ({
    value,
    text: gradeLabels[value],
}));
const fallbackTracks = Object.keys(trackLabels).map((value) => ({
    value,
    text: trackLabels[value],
}));
const fallbackTypes = Object.keys(typeLabels).map((value) => ({
    value,
    text: typeLabels[value],
}));

const optionsWithAll = (options, label) => [
    { value: "", text: label },
    ...options,
];
const levelOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(
                facetRaw("levels", "level_codes"),
                (value) => levelLabels[value] || humanize(value)
            ).length
                ? normalizeFacet(
                      facetRaw("levels", "level_codes"),
                      (value) => levelLabels[value] || humanize(value)
                  ).sort(
                      (a, b) =>
                          Object.keys(levelLabels).indexOf(a.value) -
                          Object.keys(levelLabels).indexOf(b.value)
                  )
                : fallbackLevels,
            filters.level_code,
            (value) => levelLabels[value] || humanize(value)
        ),
        "Todos los niveles"
    )
);
const gradeOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(
                facetRaw("grades", "grade_codes"),
                (value) => gradeLabels[value] || value
            ).length
                ? normalizeFacet(
                      facetRaw("grades", "grade_codes"),
                      (value) => gradeLabels[value] || value
                  ).sort(
                      (a, b) =>
                          Object.keys(gradeLabels).indexOf(a.value) -
                          Object.keys(gradeLabels).indexOf(b.value)
                  )
                : fallbackGrades,
            filters.grade_code,
            (value) => gradeLabels[value] || value
        ),
        "Todos los grados"
    )
);
const trackOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(
                facetRaw("tracks", "curriculum_tracks"),
                (value) => trackLabels[value] || humanize(value)
            ).length
                ? normalizeFacet(
                      facetRaw("tracks", "curriculum_tracks"),
                      (value) => trackLabels[value] || humanize(value)
                  ).sort(
                      (a, b) =>
                          Object.keys(trackLabels).indexOf(a.value) -
                          Object.keys(trackLabels).indexOf(b.value)
                  )
                : fallbackTracks,
            filters.curriculum_track,
            (value) => trackLabels[value] || humanize(value)
        ),
        "Todos los tipos de enseñanza"
    )
);
const typeOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(
                facetRaw("types", "objective_types"),
                (value) => typeLabels[value] || humanize(value)
            ).length
                ? normalizeFacet(
                      facetRaw("types", "objective_types"),
                      (value) => typeLabels[value] || humanize(value)
                  ).sort(
                      (a, b) =>
                          Object.keys(typeLabels).indexOf(a.value) -
                          Object.keys(typeLabels).indexOf(b.value)
                  )
                : fallbackTypes,
            filters.objective_type,
            (value) => typeLabels[value] || humanize(value)
        ),
        "Todos los tipos"
    )
);
const subjectOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(facetRaw("subjects", "subject_codes")),
            filters.subject_code
        ),
        "Todas las asignaturas"
    )
);
const axisOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(facetRaw("axes", "axis_codes"), humanize),
            filters.axis_code
        ),
        "Todos los ejes"
    )
);
const sourceOptions = computed(() =>
    optionsWithAll(
        ensureSelected(
            normalizeFacet(facetRaw("sources", "source_keys")),
            filters.source
        ),
        "Todas las fuentes"
    )
);
const statusOptions = [
    { value: "all", text: "Todos los estados" },
    { value: "active", text: "Disponibles" },
    { value: "inactive", text: "No disponibles" },
];
const statusSelectOptions = computed(() =>
    bookScoped.value ? [statusOptions[1]] : statusOptions
);
const statusFilterModel = computed({
    get: () => effectiveStatus.value,
    set: (value) => {
        filters.status = bookScoped.value ? "active" : value;
    },
});

const subjectName = (objective) =>
    objective?.subject?.name ||
    objective?.subject_name ||
    objective?.subject?.code ||
    objective?.subject_code ||
    "Transversal";
const subjectCode = (objective) =>
    objective?.subject?.code || objective?.subject_code || "";
const objectiveAvailable = (objective) =>
    objective?.available !== undefined
        ? Boolean(objective.available)
        : objective?.active !== undefined
        ? Boolean(objective.active)
        : !["inactive", "blocked", "unavailable"].includes(
              String(objective?.status || "").toLowerCase()
          );
const availabilityLabel = (objective) =>
    objectiveAvailable(objective) ? "Disponible" : "No disponible";
const gradeLabel = (value) => gradeLabels[value] || value || "Sin grado";
const trackLabel = (value) =>
    value ? trackLabels[value] || humanize(value) : "Común / transversal";
const typeLabel = (value) =>
    typeLabels[value]?.split(" · ")[0] || value || "Objetivo";
const axisLabel = (value) => (value ? humanize(value) : "Sin eje informado");
const catalogLabel = (objective) =>
    objective?.catalog?.name ||
    objective?.catalog_name ||
    "Catálogo curricular";
const shortDescription = (value, length = 240) => {
    const text = String(value || "Sin descripción informada.");
    return text.length > length ? `${text.slice(0, length).trim()}…` : text;
};
const sourceRows = computed(() => {
    const detail = selectedObjective.value || {};
    const rows =
        detail.sources ||
        detail.objective_sources ||
        detail.traceability?.sources ||
        [];
    return Array.isArray(rows) ? rows : [];
});
const indicators = computed(() => {
    const rows = selectedObjective.value?.indicators;
    if (Array.isArray(rows)) return rows;
    if (rows && typeof rows === "object") return Object.values(rows).flat();
    return [];
});
const safeHttpUrl = (value) => {
    if (!value) return "";
    try {
        const url = new URL(String(value), window.location.origin);
        return ["http:", "https:"].includes(url.protocol) ? url.href : "";
    } catch {
        return "";
    }
};
const sourceUrl = (source) =>
    safeHttpUrl(
        source?.source_url ||
            source?.url ||
            source?.public_url ||
            source?.curriculum_source?.source_url
    );
const sourceTitle = (source) =>
    source?.source_name ||
    source?.name ||
    source?.title ||
    source?.curriculum_source?.name ||
    "Fuente curricular";
const sourceAuthority = (source) =>
    source?.authority ||
    source?.curriculum_source?.authority ||
    "Autoridad no informada";
const sourceDocument = (source) =>
    source?.document_number ||
    source?.normative_act ||
    source?.curriculum_source?.document_number ||
    "";
const sourceRoleLabels = {
    canonical_text: "Texto oficial",
    legal_basis: "Base jurídica",
    amendment: "Modificación",
    complementary: "Complementaria",
};
const sourceRole = (source) => {
    const role = source?.source_role || source?.role || "fuente";
    return sourceRoleLabels[role] || humanize(role);
};
const sourceLocator = (source) =>
    source?.source_locator || source?.locator || source?.page || "";
const sourceHash = (source) =>
    source?.verified_sha256 ||
    source?.declared_sha256 ||
    source?.sha256 ||
    source?.normative_source?.sha256 ||
    "";
const abbreviatedHash = (source) => {
    const hash = sourceHash(source);
    return hash ? `${hash.slice(0, 12)}…${hash.slice(-8)}` : "";
};
const sourceVerified = (source) =>
    source?.evidence?.status === "verified" ||
    (source?.status === "verified" && source?.hash_matches === true);

const kpis = computed(() => [
    {
        label: "Objetivos encontrados",
        value: numberFrom(
            summary.value,
            [
                "filtered_objectives",
                "total",
                "total_objectives",
                "objectives_count",
            ],
            totalRows.value
        ),
        icon: "bx-target-lock",
        tone: "brand",
        hint: hasActiveFilters.value
            ? "Con los filtros actuales"
            : "Catálogo del año seleccionado",
    },
    {
        label: "Disponibles",
        value: numberFrom(summary.value, [
            "active_objectives",
            "active",
            "active_count",
            "available",
            "available_count",
        ]),
        icon: "bx-check-shield",
        tone: "success",
        hint: "Total utilizable en el leccionario",
    },
    {
        label: "No disponibles",
        value: numberFrom(summary.value, [
            "inactive_objectives",
            "inactive",
            "inactive_count",
            "unavailable",
            "unavailable_count",
        ]),
        icon: "bx-block",
        tone: "warning",
        hint: "Total del catálogo activo",
    },
    {
        label: "Asignaturas",
        value: numberFrom(
            summary.value,
            ["subjects", "subjects_count", "subject_count"],
            normalizeFacet(facetRaw("subjects", "subject_codes")).length
        ),
        icon: "bx-book-content",
        tone: "info",
        hint: "En el catálogo activo",
    },
]);

const PDF_EXPORT_MAX_ROWS = 750;
const XLSX_EXPORT_MAX_ROWS = 10000;
const exportLimit = (format, fallback) => {
    const value = Number(meta.value?.export_limits?.[format]?.max_rows);
    return Number.isFinite(value) && value > 0 ? value : fallback;
};
const pdfExportMaxRows = computed(() =>
    exportLimit("pdf", PDF_EXPORT_MAX_ROWS)
);
const xlsxExportMaxRows = computed(() =>
    exportLimit("xlsx", XLSX_EXPORT_MAX_ROWS)
);
const pdfExportAvailable = computed(
    () =>
        totalRows.value > 0 &&
        totalRows.value <= pdfExportMaxRows.value &&
        meta.value?.export_limits?.pdf?.allowed !== false
);
const xlsxExportAvailable = computed(
    () =>
        totalRows.value > 0 &&
        totalRows.value <= xlsxExportMaxRows.value &&
        meta.value?.export_limits?.xlsx?.allowed !== false
);
const exportFormats = computed(() => [
    {
        value: "pdf",
        label: "Informe PDF",
        description: "Lectura ejecutiva, paginada y lista para compartir.",
        meta: `Hasta ${formatNumber(pdfExportMaxRows.value)} objetivos`,
        icon: "bxs-file-pdf",
        tone: "pdf",
        disabled: !pdfExportAvailable.value,
    },
    {
        value: "xlsx",
        label: "Libro Excel",
        description: "Datos tabulares para análisis y trabajo institucional.",
        meta: `Hasta ${formatNumber(xlsxExportMaxRows.value)} objetivos`,
        icon: "bx-spreadsheet",
        tone: "xlsx",
        disabled: !xlsxExportAvailable.value,
    },
]);
const selectedExportFormat = computed(() =>
    exportFormats.value.find((format) => format.value === exportFormat.value)
);
const exportFormatAvailable = computed(() =>
    Boolean(selectedExportFormat.value && !selectedExportFormat.value.disabled)
);
const catalogRows = (...keys) => {
    for (const key of keys) {
        if (Array.isArray(props.catalogs?.[key])) return props.catalogs[key];
    }
    return [];
};
const contextRecord = (id, ...keys) =>
    catalogRows(...keys).find(
        (record) =>
            String(record?.id ?? record?.value ?? "") === String(id ?? "")
    ) || null;
const contextDisplay = (record, fallback) =>
    firstPresent(record, ["display_name", "name", "label", "year"], fallback);
const schoolContextLabel = computed(() =>
    contextDisplay(
        contextRecord(props.context.school_id, "schools", "establishments"),
        props.context.school_name ||
            `Establecimiento #${props.context.school_id}`
    )
);
const yearContextLabel = computed(() =>
    contextDisplay(
        contextRecord(
            props.context.academic_year_id,
            "academic_years",
            "years"
        ),
        props.context.academic_year_name ||
            props.context.academic_year ||
            `Año académico #${props.context.academic_year_id}`
    )
);
const courseContextLabel = computed(() => {
    if (!props.context.course_section_id)
        return "Todos los cursos del contexto";
    return contextDisplay(
        contextRecord(
            props.context.course_section_id,
            "courses",
            "course_sections"
        ),
        props.context.course_section_name ||
            props.context.course_name ||
            `Curso #${props.context.course_section_id}`
    );
});
const subjectContextLabel = computed(() => {
    if (!scopedScheduleSubjectId.value) {
        return "Todas las asignaturas del contexto";
    }
    return contextDisplay(
        contextRecord(
            scopedScheduleSubjectId.value,
            "subjects",
            "schedule_subjects"
        ),
        props.context.schedule_subject_name ||
            props.context.subject_name ||
            `Asignatura #${scopedScheduleSubjectId.value}`
    );
});
const selectedOptionLabel = (options, value, fallback) => {
    const option = options.find(
        (candidate) => String(candidate?.value ?? "") === String(value ?? "")
    );
    return String(option?.text || fallback || value || "")
        .replace(/\s+·\s+[\d.]+$/, "")
        .trim();
};
const exportFilterChips = computed(() => {
    const chips = [];
    const add = (label, value) => {
        if (value) chips.push({ label, value });
    };
    if (props.context.book_id) {
        add(
            "Libro contextual",
            props.context.book_name || `Libro #${props.context.book_id}`
        );
    }
    add(
        "Nivel",
        filters.level_code
            ? selectedOptionLabel(
                  levelOptions.value,
                  filters.level_code,
                  levelLabels[filters.level_code]
              )
            : "Todos"
    );
    add(
        "Grado",
        filters.grade_code
            ? selectedOptionLabel(
                  gradeOptions.value,
                  filters.grade_code,
                  gradeLabel(filters.grade_code)
              )
            : "Todos"
    );
    add(
        "Tipo de enseñanza",
        filters.curriculum_track
            ? selectedOptionLabel(
                  trackOptions.value,
                  filters.curriculum_track,
                  trackLabel(filters.curriculum_track)
              )
            : "Todas"
    );
    if (bookScoped.value) {
        add("Asignatura contextual", subjectContextLabel.value);
    } else {
        add(
            "Asignatura",
            filters.subject_code
                ? selectedOptionLabel(
                      subjectOptions.value,
                      filters.subject_code,
                      filters.subject_code
                  )
                : "Todas"
        );
    }
    add(
        "Tipo de objetivo",
        filters.objective_type
            ? selectedOptionLabel(
                  typeOptions.value,
                  filters.objective_type,
                  typeLabels[filters.objective_type]
              )
            : "Todos"
    );
    add(
        "Eje",
        filters.axis_code
            ? selectedOptionLabel(
                  axisOptions.value,
                  filters.axis_code,
                  axisLabel(filters.axis_code)
              )
            : "Todos"
    );
    add(
        "Estado",
        selectedOptionLabel(statusOptions, effectiveStatus.value, "Todos")
    );
    add(
        "Fuente",
        filters.source
            ? selectedOptionLabel(
                  sourceOptions.value,
                  filters.source,
                  filters.source
              )
            : "Todas"
    );
    if (filters.query) add("Búsqueda", `“${filters.query}”`);
    return chips;
});
const exportFilterPayload = () =>
    cleanParams({
        level_code: filters.level_code,
        grade_code: filters.grade_code,
        curriculum_track: filters.curriculum_track,
        subject_code: bookScoped.value ? null : filters.subject_code,
        objective_type: filters.objective_type,
        axis_code: filters.axis_code,
        status: effectiveStatus.value,
        source: filters.source,
        query: filters.query,
        course_section_id: props.context.course_section_id,
        schedule_subject_id: scopedScheduleSubjectId.value,
    });
const exportScopeSignature = computed(() =>
    JSON.stringify({
        school_id: props.context.school_id || null,
        academic_year_id: props.context.academic_year_id || null,
        book_id: props.context.book_id || null,
        refresh_token: props.refreshToken,
        filters: exportFilterPayload(),
    })
);
const currentExportSignature = () =>
    JSON.stringify({
        scope: exportScopeSignature.value,
        format: exportFormat.value,
    });
const cancelExportGeneration = () => {
    exportGeneration += 1;
    exportController?.abort();
    exportController = null;
};
const beginExportGeneration = () => {
    cancelExportGeneration();
    const controller = new AbortController();
    const generation = exportGeneration;
    const signature = currentExportSignature();
    exportController = controller;
    return { controller, generation, signature };
};
const exportGenerationIsCurrent = (run) =>
    Boolean(
        run &&
            run.generation === exportGeneration &&
            run.controller === exportController &&
            !run.controller.signal.aborted &&
            run.signature === currentExportSignature()
    );
const exportJobStatus = computed(() =>
    String(
        exportJob.value?.status || (exporting.value ? "queued" : "")
    ).toLowerCase()
);
const exportStatusLabel = computed(
    () =>
        ({
            pending: "Preparando solicitud",
            queued: "En cola de generación",
            processing: "Procesando objetivos",
            generating: "Construyendo archivo",
            completed: "Archivo listo",
            complete: "Archivo listo",
            ready: "Archivo listo",
            generated: "Archivo listo",
            failed: "Generación fallida",
            rejected: "Solicitud rechazada",
            invalid: "Solicitud inválida",
            expired: "Archivo vencido",
        }[exportJobStatus.value] || "Preparando exportación")
);
const exportProgress = computed(() => {
    const raw = Number(
        exportJob.value?.progress ?? exportJob.value?.progress_percent ?? 0
    );
    if (Number.isFinite(raw) && raw > 0) return Math.min(100, Math.max(0, raw));
    return exporting.value ? 6 : exportDownloaded.value ? 100 : 0;
});
const exportReady = computed(() =>
    ["completed", "complete", "ready", "generated"].includes(
        exportJobStatus.value
    )
);
const exportFallbackFilename = computed(
    () =>
        `objetivos-curriculares-${
            props.context.academic_year_id || "contexto"
        }.${exportFormat.value}`
);

const requestParams = () =>
    cleanParams({
        school_id: props.context.school_id,
        academic_year_id: props.context.academic_year_id,
        book_id: props.context.book_id,
        schedule_subject_id: scopedScheduleSubjectId.value,
        level_code: filters.level_code,
        grade_code: filters.grade_code,
        curriculum_track: filters.curriculum_track,
        subject_code: bookScoped.value ? null : filters.subject_code,
        objective_type: filters.objective_type,
        axis_code: filters.axis_code,
        source: filters.source,
        status: effectiveStatus.value,
        query: filters.query,
        course_section_id: props.context.course_section_id,
        page: currentPage.value,
        per_page: perPage.value,
    });

const visualizationRequestParams = computed(() => {
    const params = requestParams();
    delete params.page;
    delete params.per_page;
    params.__cache_token = props.refreshToken;
    return params;
});

const updateUrl = () => {
    const query = { ...route.query };
    [
        "q",
        "query",
        "level_code",
        "grade_code",
        "curriculum_track",
        "track",
        "subject_code",
        "subject_id",
        "objective_type",
        "type",
        "axis_code",
        "source",
        "status",
        "page",
    ].forEach((key) => delete query[key]);
    Object.assign(
        query,
        cleanParams({
            q: filters.query,
            level_code: filters.level_code,
            grade_code: filters.grade_code,
            curriculum_track: filters.curriculum_track,
            subject_code: bookScoped.value ? "" : filters.subject_code,
            objective_type: filters.objective_type,
            axis_code: filters.axis_code,
            source: filters.source,
            status:
                bookScoped.value || filters.status === "all"
                    ? ""
                    : filters.status,
            page: currentPage.value > 1 ? currentPage.value : "",
        })
    );
    router.replace({ query }).catch(() => {});
};

const load = async () => {
    listController?.abort();
    listController = null;
    if (!canView.value || !contextReady.value) {
        loading.value = false;
        objectives.value = [];
        responseMeta.value = {};
        return;
    }
    const controller = new AbortController();
    listController = controller;
    loading.value = true;
    error.value = null;
    try {
        const payload = await libroDigitalApi.curriculumObjectives(
            requestParams(),
            controller.signal
        );
        if (listController !== controller || controller.signal.aborted) return;
        objectives.value = payloadItems(payload);
        responseMeta.value = payloadMeta(payload);
        const serverPage = Number(
            responseMeta.value?.pagination?.current_page ??
                responseMeta.value?.current_page
        );
        if (serverPage) currentPage.value = serverPage;
    } catch (requestError) {
        if (
            listController === controller &&
            requestError?.code !== "ERR_CANCELED"
        ) {
            error.value = requestError;
        }
    } finally {
        if (listController === controller) {
            listController = null;
            loading.value = false;
        }
    }
};

const applyFilters = async () => {
    window.clearTimeout(searchTimer);
    searchTimer = null;
    searchPending.value = false;
    filters.query = queryDraft.value.trim();
    currentPage.value = 1;
    updateUrl();
    await load();
};
const scheduleSearch = () => {
    window.clearTimeout(searchTimer);
    searchPending.value = true;
    searchTimer = window.setTimeout(() => {
        searchTimer = null;
        searchPending.value = false;
        void applyFilters();
    }, 420);
};
const clearFilters = () => {
    window.clearTimeout(searchTimer);
    searchTimer = null;
    searchPending.value = false;
    queryDraft.value = "";
    Object.assign(filters, {
        level_code: "",
        grade_code: "",
        curriculum_track: "",
        subject_code: "",
        objective_type: "",
        axis_code: "",
        source: "",
        status: bookScoped.value ? "active" : "all",
        query: "",
    });
    currentPage.value = 1;
    updateUrl();
    load();
};
const changePage = (page) => {
    const next = Math.min(lastPage.value, Math.max(1, Number(page) || 1));
    if (next === currentPage.value) return;
    currentPage.value = next;
    updateUrl();
    load();
    nextTick(() => document.getElementById("lcd-objective-results")?.focus());
};

const openDetail = async (objective) => {
    selectedObjective.value = objective;
    detailVisible.value = true;
    detailLoading.value = true;
    detailError.value = null;
    detailController?.abort();
    detailController = new AbortController();
    try {
        const payload = await libroDigitalApi.curriculumObjective(
            objective.public_id || objective.id,
            {
                school_id: props.context.school_id,
                academic_year_id: props.context.academic_year_id,
            },
            detailController.signal
        );
        selectedObjective.value =
            payload?.data === null
                ? objective
                : payload?.data ?? payload ?? objective;
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED")
            detailError.value = requestError;
    } finally {
        detailLoading.value = false;
    }
};
const openVisualizationObjective = (node) => {
    const publicId =
        node?.entityId ||
        node?.metadata?.public_id ||
        node?.metadata?.objective_public_id ||
        null;
    if (!publicId) return;
    void openDetail({
        ...(node?.metadata?.objective || {}),
        code: node?.code || node?.metadata?.code || null,
        description: node?.description || node?.metadata?.description || null,
        public_id: publicId,
        id: publicId,
    });
};
const visualizationFilterValue = (value) => {
    if (value === null || value === undefined || value === "") return "";
    if (typeof value !== "object") return String(value);
    return String(value.code ?? value.value ?? value.slug ?? "");
};
const applyVisualizationNodeFilters = async (payload = {}) => {
    const source = payload.filters || payload;
    const node = payload.node || null;
    const next = {};
    const assign = (target, ...candidates) => {
        for (const candidate of candidates) {
            const value = visualizationFilterValue(candidate);
            if (!value) continue;
            next[target] = value;
            return;
        }
    };
    assign(
        "level_code",
        source.level_code,
        source.education_level_code,
        source.education_level
    );
    assign("grade_code", source.grade_code, source.grade);
    assign(
        "curriculum_track",
        source.curriculum_track,
        source.formation_code,
        source.formation
    );
    assign("subject_code", source.subject_code);
    assign("objective_type", source.objective_type);
    assign(
        "axis_code",
        source.axis_code,
        source.curricular_group_code,
        source.curricular_group
    );
    assign("source", source.source_code, source.source);
    assign("status", source.status);

    for (const item of node?.path || []) {
        if (!item?.code) continue;
        if (item.type === "subject" && !next.subject_code) {
            next.subject_code = String(item.code);
        } else if (item.type === "grade" && !next.grade_code) {
            next.grade_code = String(item.code);
        } else if (item.type === "education_level" && !next.level_code) {
            next.level_code = String(item.code);
        } else if (item.type === "formation" && !next.curriculum_track) {
            next.curriculum_track = String(item.code);
        } else if (
            ["axis", "nucleus", "ambit", "curricular_group"].includes(
                item.type
            ) &&
            !next.axis_code
        ) {
            next.axis_code = String(item.code);
        }
    }

    const dimensionType = node?.type;
    if (node?.code) {
        if (dimensionType === "subject" && !next.subject_code) {
            next.subject_code = String(node.code);
        } else if (
            ["axis", "nucleus", "ambit", "curricular_group"].includes(
                dimensionType
            ) &&
            !next.axis_code
        ) {
            next.axis_code = String(node.code);
        } else if (dimensionType === "grade" && !next.grade_code) {
            next.grade_code = String(node.code);
        } else if (dimensionType === "education_level" && !next.level_code) {
            next.level_code = String(node.code);
        } else if (dimensionType === "formation" && !next.curriculum_track) {
            next.curriculum_track = String(node.code);
        } else if (dimensionType === "objective_type" && !next.objective_type) {
            next.objective_type = String(node.code);
        }
    }

    if (bookScoped.value) delete next.subject_code;
    if (bookScoped.value) next.status = "active";
    Object.assign(filters, next);
    queryDraft.value = filters.query || "";
    currentPage.value = 1;
    updateUrl();
    await load();
};
const retryDetail = () => {
    if (selectedObjective.value) openDetail(selectedObjective.value);
};
const exportIdentifier = (job) =>
    job?.public_id || job?.report_identifier || null;
const exportFailure = (message, code, job = exportJob.value) => {
    const failure = new Error(message);
    failure.code = code;
    failure.correlationId =
        job?.correlation_id || job?.request_id || job?.correlationId || null;
    return failure;
};
const assertExportGeneration = (run, job = exportJob.value) => {
    if (exportGenerationIsCurrent(run)) return;
    throw exportFailure(
        "El alcance de la exportación cambió durante la solicitud.",
        "ERR_CANCELED",
        job
    );
};
const enrichedExportError = (requestError) => {
    if (!requestError)
        return exportFailure("No se pudo completar la exportación.");
    if (!requestError.correlationId) {
        requestError.correlationId =
            exportJob.value?.correlation_id ||
            exportJob.value?.request_id ||
            exportJob.value?.correlationId ||
            null;
    }
    return requestError;
};
const assertExportReady = (job) => {
    const status = String(job?.status || "").toLowerCase();
    if (["failed", "rejected", "invalid"].includes(status)) {
        throw exportFailure(
            job?.failure_message ||
                job?.message ||
                "El servidor no pudo generar el archivo solicitado.",
            job?.failure_code || "LCD_CURRICULUM_EXPORT_FAILED",
            job
        );
    }
    if (status === "expired") {
        throw exportFailure(
            "El archivo venció antes de poder descargarse. Genera una nueva copia.",
            "LCD_CURRICULUM_EXPORT_EXPIRED",
            job
        );
    }
    if (!["completed", "complete", "ready", "generated"].includes(status)) {
        throw exportFailure(
            "La generación terminó en un estado que no permite descargar el archivo.",
            "LCD_CURRICULUM_EXPORT_NOT_READY",
            job
        );
    }
};
const waitForExport = async (record, run) => {
    assertExportGeneration(run, record);
    const identifier = exportIdentifier(record);
    if (!identifier) {
        throw exportFailure(
            "El servidor no entregó un identificador válido para consultar el informe.",
            "LCD_CURRICULUM_EXPORT_IDENTIFIER_MISSING",
            record
        );
    }
    const pendingStatuses = ["pending", "queued", "processing", "generating"];
    const completed = pendingStatuses.includes(
        String(record?.status || "").toLowerCase()
    )
        ? await waitForLibroDigitalJob(
              record,
              () => libroDigitalApi.report(identifier, run.controller.signal),
              {
                  pendingStatuses,
                  timeoutMs: 180000,
                  intervalMs: 1600,
                  onProgress: (current) => {
                      assertExportGeneration(run, current);
                      exportJob.value = current;
                  },
              }
          )
        : record;
    assertExportGeneration(run, completed);
    exportJob.value = completed;
    assertExportReady(completed);
    return completed;
};
const downloadExport = async (job = exportJob.value, activeRun = null) => {
    if (!canExport.value || exportDownloading.value) return;
    let run = activeRun;
    try {
        if (!run) {
            if (
                exportJobSignature.value &&
                exportJobSignature.value !== currentExportSignature()
            ) {
                throw exportFailure(
                    "Los filtros cambiaron desde que se generó este informe.",
                    "ERR_CANCELED",
                    job
                );
            }
            run = beginExportGeneration();
        }
        assertExportGeneration(run, job);
        const identifier = exportIdentifier(job);
        if (!identifier) {
            throw exportFailure(
                "No existe un identificador público para descargar el informe.",
                "LCD_CURRICULUM_EXPORT_IDENTIFIER_MISSING",
                job
            );
        }
        await focusExportProgress();
        assertExportGeneration(run, job);
        exportDownloading.value = true;
        exportError.value = null;
        await libroDigitalApi.download(
            `${LIBRO_DIGITAL_API_BASE}/reports/${encodeURIComponent(
                identifier
            )}/download`,
            job?.filename || exportFallbackFilename.value,
            run.controller.signal
        );
        assertExportGeneration(run, job);
        exportDownloaded.value = true;
    } catch (requestError) {
        handleExportFailure(requestError, run);
    } finally {
        if (run?.generation === exportGeneration) {
            exportDownloading.value = false;
        }
    }
};
const finishExport = async (record, run) => {
    const completed = await waitForExport(record, run);
    await downloadExport(completed, run);
};
const handleExportFailure = (requestError, run = null) => {
    if (requestError?.code === "ERR_CANCELED") return;
    if (run && !exportGenerationIsCurrent(run)) return;
    if (requestError?.code === "LCD_JOB_TIMEOUT") {
        exportTimedOut.value = true;
        return;
    }
    exportError.value = enrichedExportError(requestError);
};
const generateExport = async () => {
    if (
        !canExport.value ||
        !contextReady.value ||
        loading.value ||
        searchPending.value ||
        exporting.value ||
        exportDownloading.value ||
        !exportFormatAvailable.value ||
        totalRows.value < 1
    ) {
        return;
    }
    const run = beginExportGeneration();
    exportJobSignature.value = run.signature;
    exporting.value = true;
    await focusExportProgress();
    exportError.value = null;
    exportTimedOut.value = false;
    exportDownloaded.value = false;
    exportJob.value = null;
    try {
        const record = payloadData(
            await libroDigitalApi.generateReport(
                {
                    school_id: props.context.school_id,
                    academic_year_id: props.context.academic_year_id,
                    book_id: props.context.book_id || undefined,
                    report_type: "curriculum_objectives",
                    format: exportFormat.value,
                    filters: exportFilterPayload(),
                },
                run.controller.signal
            )
        );
        assertExportGeneration(run, record);
        if (!exportIdentifier(record)) {
            throw exportFailure(
                "El servidor no entregó un identificador válido para el informe.",
                "LCD_CURRICULUM_EXPORT_IDENTIFIER_MISSING",
                record
            );
        }
        exportJob.value = record;
        await finishExport(record, run);
    } catch (requestError) {
        handleExportFailure(requestError, run);
    } finally {
        if (run.generation === exportGeneration) exporting.value = false;
    }
};
const resumeExport = async () => {
    if (
        !canExport.value ||
        loading.value ||
        searchPending.value ||
        exporting.value ||
        !exportJob.value ||
        exportJobSignature.value !== currentExportSignature()
    ) {
        return;
    }
    const run = beginExportGeneration();
    exporting.value = true;
    await focusExportProgress();
    exportTimedOut.value = false;
    exportError.value = null;
    try {
        await finishExport(exportJob.value, run);
    } catch (requestError) {
        handleExportFailure(requestError, run);
    } finally {
        if (run.generation === exportGeneration) exporting.value = false;
    }
};
const openExport = () => {
    if (
        !canExport.value ||
        !contextReady.value ||
        loading.value ||
        searchPending.value ||
        totalRows.value < 1
    )
        return;
    cancelExportGeneration();
    exportFormat.value = pdfExportAvailable.value ? "pdf" : "xlsx";
    exportJob.value = null;
    exportJobSignature.value = "";
    exportError.value = null;
    exportTimedOut.value = false;
    exportDownloaded.value = false;
    exportVisible.value = true;
};
const focusExportFormat = () =>
    nextTick(() =>
        document
            .getElementById(`lcd-objective-export-format-${exportFormat.value}`)
            ?.focus()
    );
const focusExportProgress = () =>
    nextTick(() =>
        document.getElementById("lcd-objective-export-progress")?.focus()
    );
const trapExportFocus = (event) => {
    if (event.key !== "Tab") return;
    const modal = document.getElementById("lcd-objective-export-modal");
    const focusable = Array.from(
        modal?.querySelectorAll(
            'button:not(:disabled), input:not(:disabled), [href], [tabindex]:not([tabindex="-1"])'
        ) || []
    ).filter(
        (element) =>
            !element.hasAttribute("hidden") &&
            element.getAttribute("aria-hidden") !== "true" &&
            element.getClientRects().length > 0
    );
    if (!focusable.length) {
        event.preventDefault();
        (
            document.getElementById("lcd-objective-export-progress") || modal
        )?.focus?.();
        return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};
const restoreExportFocus = () =>
    nextTick(() =>
        (exportTrigger.value?.$el || exportTrigger.value)?.focus?.()
    );
const guardExportHide = (event) => {
    if (exporting.value || exportDownloading.value) {
        event?.preventDefault?.();
        return;
    }
    cancelExportGeneration();
};
const invalidateExportDialog = () => {
    cancelExportGeneration();
    exporting.value = false;
    exportDownloading.value = false;
    exportJob.value = null;
    exportJobSignature.value = "";
    exportError.value = null;
    exportTimedOut.value = false;
    exportDownloaded.value = false;
    exportVisible.value = false;
};
const clearExportFilters = () => {
    invalidateExportDialog();
    clearFilters();
};
const openCatalogManagement = () =>
    router.push({
        path: "/libro-digital/subjects",
        query: { ...route.query },
        hash: "#lcd-curriculum-import-title",
    });

const routeFilterSignature = computed(() =>
    [
        route.query.q ?? route.query.query,
        route.query.level_code,
        route.query.grade_code,
        route.query.curriculum_track ?? route.query.track,
        route.query.subject_code ?? route.query.subject_id,
        route.query.objective_type ?? route.query.type,
        route.query.axis_code,
        route.query.source,
        route.query.status,
        route.query.page,
    ]
        .map((value) => (Array.isArray(value) ? value[0] : value) || "")
        .join("|")
);
watch(
    exportScopeSignature,
    () => {
        if (
            exportVisible.value ||
            exporting.value ||
            exportDownloading.value ||
            exportJob.value
        ) {
            invalidateExportDialog();
        } else {
            cancelExportGeneration();
        }
    },
    { flush: "sync" }
);
watch(routeFilterSignature, () => {
    window.clearTimeout(searchTimer);
    searchTimer = null;
    searchPending.value = false;
    const next = {
        query: routeValue("q", routeValue("query")),
        level_code: routeValue("level_code"),
        grade_code: routeValue("grade_code"),
        curriculum_track: routeValue("curriculum_track", routeValue("track")),
        subject_code: bookScoped.value
            ? ""
            : routeValue("subject_code", routeValue("subject_id")),
        objective_type: routeValue("objective_type", routeValue("type")),
        axis_code: routeValue("axis_code"),
        source: routeValue("source"),
        status: bookScoped.value ? "active" : routeValue("status", "all"),
    };
    const nextPage = Math.max(1, Number(route.query.page) || 1);
    const changed =
        Object.entries(next).some(([key, value]) => filters[key] !== value) ||
        currentPage.value !== nextPage;
    if (!changed) return;
    if (exportVisible.value) invalidateExportDialog();
    Object.assign(filters, next);
    queryDraft.value = next.query;
    currentPage.value = nextPage;
    load();
});

let globalStatusBeforeBook = bookScoped.value ? "all" : filters.status;
watch(
    bookScoped,
    (scoped, previous) => {
        if (scoped) {
            if (previous === false) {
                globalStatusBeforeBook = filters.status || "all";
            }
            filters.status = "active";
            filters.subject_code = "";
        } else if (previous === true) {
            filters.status = globalStatusBeforeBook || "all";
        }
    },
    { immediate: true }
);
watch(
    [scopeKey, () => props.refreshToken, canView],
    () => {
        currentPage.value = 1;
        updateUrl();
        load();
    },
    { immediate: true }
);
watch(totalRows, () => {
    if (exportFormatAvailable.value) return;
    if (xlsxExportAvailable.value) {
        exportFormat.value = "xlsx";
    } else if (pdfExportAvailable.value) {
        exportFormat.value = "pdf";
    }
});
onBeforeUnmount(() => {
    listController?.abort();
    detailController?.abort();
    cancelExportGeneration();
    window.clearTimeout(searchTimer);
    searchPending.value = false;
});
</script>

<template>
    <section
        class="ld-section ld-objectives"
        aria-labelledby="lcd-objectives-title"
        :aria-busy="loading"
    >
        <header class="ld-objectives__header">
            <div class="ld-objectives__identity">
                <span class="ld-objectives__mark" aria-hidden="true">
                    <i class="bx bx-bullseye"></i>
                </span>
                <div>
                    <span class="ld-eyebrow">Currículum oficial trazable</span>
                    <h2 id="lcd-objectives-title">Explorador de objetivos</h2>
                    <p>
                        Consulta el catálogo institucional desde NT1 hasta 4.º
                        medio, sus ejes, fuentes y disponibilidad pedagógica.
                    </p>
                </div>
            </div>
            <div class="ld-objectives__header-actions">
                <span class="ld-objectives__scope">
                    <i class="bx bx-buildings" aria-hidden="true"></i>
                    Establecimiento y año seleccionados
                </span>
                <BButton
                    v-if="canExport"
                    ref="exportTrigger"
                    type="button"
                    size="sm"
                    variant="primary"
                    :disabled="
                        !contextReady ||
                        loading ||
                        searchPending ||
                        totalRows < 1
                    "
                    @click="openExport"
                >
                    <i class="bx bx-download" aria-hidden="true"></i>
                    Exportar
                </BButton>
                <BButton
                    v-if="canManageImports"
                    type="button"
                    size="sm"
                    variant="outline-primary"
                    @click="openCatalogManagement"
                >
                    <i class="bx bx-cog" aria-hidden="true"></i>
                    Gestionar catálogo
                </BButton>
            </div>
        </header>

        <BAlert
            v-if="canView"
            :model-value="true"
            variant="info"
            class="ld-objectives__usage-note mb-0"
            role="note"
        >
            <i class="bx bx-info-circle" aria-hidden="true"></i>
            <span v-if="bookScoped">
                <strong>Alcance del libro seleccionado.</strong> La consulta
                queda restringida al curso y asignatura del contexto; solo
                muestra objetivos <b>Disponibles</b> que pueden utilizarse en el
                leccionario.
            </span>
            <span v-else>
                <strong>Catálogo en modo consulta.</strong> Solo los objetivos
                marcados como <b>Disponibles</b> pueden seleccionarse en el
                leccionario; los demás se conservan para referencia y
                trazabilidad.
            </span>
        </BAlert>

        <LibroDigitalStatePanel
            v-if="!canView"
            state="restricted"
            title="Acceso restringido al catálogo curricular"
            message="Necesitas permiso para consultar libros o gestionar el leccionario. Solicita acceso a la administración institucional."
        />
        <LibroDigitalStatePanel
            v-else-if="!contextReady"
            state="warning"
            title="Selecciona el contexto académico"
            message="Define establecimiento y año académico en la barra superior para consultar el catálogo correspondiente."
        />

        <template v-else>
            <div class="ld-objectives__kpis" aria-label="Resumen del catálogo">
                <article
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="ld-objectives-kpi"
                    :class="`ld-objectives-kpi--${kpi.tone}`"
                >
                    <span class="ld-objectives-kpi__icon" aria-hidden="true">
                        <i class="bx" :class="kpi.icon"></i>
                    </span>
                    <div>
                        <span>{{ kpi.label }}</span>
                        <strong>{{ formatNumber(kpi.value) }}</strong>
                        <small>{{ kpi.hint }}</small>
                    </div>
                </article>
            </div>

            <form
                class="ld-objectives__filters"
                role="search"
                aria-label="Filtros de objetivos curriculares"
                @submit.prevent="applyFilters"
            >
                <div class="ld-objectives__filter-heading">
                    <div>
                        <span class="ld-eyebrow">Búsqueda avanzada</span>
                        <h3>Acota el catálogo</h3>
                    </div>
                    <BButton
                        v-if="hasActiveFilters"
                        type="button"
                        size="sm"
                        variant="link"
                        @click="clearFilters"
                    >
                        <i class="bx bx-reset" aria-hidden="true"></i>
                        Limpiar filtros
                    </BButton>
                </div>

                <div class="ld-objectives__search-field">
                    <label for="lcd-objective-search">Palabra o código</label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">
                            <i class="bx bx-search"></i>
                        </span>
                        <BFormInput
                            id="lcd-objective-search"
                            v-model="queryDraft"
                            type="search"
                            maxlength="120"
                            autocomplete="off"
                            placeholder="Ej.: OA 03, lectura, ciudadanía…"
                            @input="scheduleSearch"
                        />
                        <BButton type="submit" variant="primary">
                            Buscar
                        </BButton>
                    </div>
                </div>

                <div class="ld-objectives__filter-grid">
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-level">Nivel</label>
                        <BFormSelect
                            id="lcd-objective-level"
                            v-model="filters.level_code"
                            :options="levelOptions"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-grade">Grado</label>
                        <BFormSelect
                            id="lcd-objective-grade"
                            v-model="filters.grade_code"
                            :options="gradeOptions"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-track"
                            >Tipo de enseñanza</label
                        >
                        <BFormSelect
                            id="lcd-objective-track"
                            v-model="filters.curriculum_track"
                            :options="trackOptions"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-subject">
                            {{
                                bookScoped
                                    ? "Asignatura del libro"
                                    : "Asignatura"
                            }}
                        </label>
                        <BFormSelect
                            id="lcd-objective-subject"
                            v-model="filters.subject_code"
                            :options="
                                bookScoped
                                    ? [
                                          {
                                              value: '',
                                              text: 'Definida por el libro',
                                          },
                                      ]
                                    : subjectOptions
                            "
                            :disabled="bookScoped"
                            @change="applyFilters"
                        />
                        <small
                            v-if="bookScoped"
                            class="ld-objectives__field-note"
                        >
                            {{ subjectContextLabel }} · el alcance no admite
                            otra asignatura.
                        </small>
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-type">Tipo</label>
                        <BFormSelect
                            id="lcd-objective-type"
                            v-model="filters.objective_type"
                            :options="typeOptions"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-axis">Eje</label>
                        <BFormSelect
                            id="lcd-objective-axis"
                            v-model="filters.axis_code"
                            :options="axisOptions"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-source">Fuente</label>
                        <BFormSelect
                            id="lcd-objective-source"
                            v-model="filters.source"
                            :options="sourceOptions"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="ld-objectives__field">
                        <label for="lcd-objective-status">Estado</label>
                        <BFormSelect
                            id="lcd-objective-status"
                            v-model="statusFilterModel"
                            :options="statusSelectOptions"
                            :disabled="bookScoped"
                            :aria-describedby="
                                bookScoped
                                    ? 'lcd-objective-book-status-note'
                                    : undefined
                            "
                            @change="applyFilters"
                        />
                        <small
                            v-if="bookScoped"
                            id="lcd-objective-book-status-note"
                            class="ld-objectives__field-note"
                        >
                            El libro solo admite objetivos disponibles.
                        </small>
                    </div>
                </div>
            </form>

            <BAlert
                v-if="error && objectives.length"
                :model-value="true"
                variant="warning"
                class="ld-objectives__stale-alert mb-0"
                role="status"
            >
                <i class="bx bx-error-circle" aria-hidden="true"></i>
                <span>
                    No fue posible actualizar los resultados. Se conserva la
                    última lectura visible.
                </span>
                <BButton
                    type="button"
                    size="sm"
                    variant="link"
                    :disabled="loading"
                    @click="load"
                >
                    Reintentar
                </BButton>
            </BAlert>

            <CurriculumVisualizationPanel
                :request-params="visualizationRequestParams"
                :filtros="filters"
                :context="props.context"
                :total="totalRows"
                @open-objective="openVisualizationObjective"
                @apply-node-filter="applyVisualizationNodeFilters"
                @view-node-in-table="applyVisualizationNodeFilters"
                @clear-filters="clearFilters"
            >
                <template #table>
                    <LibroDigitalStatePanel
                        v-if="loading && !objectives.length"
                        state="loading"
                        title="Consultando objetivos curriculares"
                        message="Aplicando filtros sobre el catálogo oficial versionado."
                    />
                    <LibroDigitalStatePanel
                        v-else-if="error && !objectives.length"
                        state="error"
                        title="No se pudieron cargar los objetivos"
                        :message="errorMessage(error)"
                        @retry="load"
                    />
                    <LibroDigitalStatePanel
                        v-else-if="blocker && !objectives.length"
                        state="warning"
                        :title="blockerPresentation.title"
                        :message="blockerPresentation.message"
                    >
                        <BButton
                            v-if="canManageImports"
                            type="button"
                            size="sm"
                            variant="primary"
                            @click="openCatalogManagement"
                        >
                            Revisar configuración curricular
                        </BButton>
                    </LibroDigitalStatePanel>
                    <LibroDigitalStatePanel
                        v-else-if="!objectives.length"
                        state="empty"
                        title="No encontramos objetivos"
                        :message="
                            catalogStatus === 'not_imported'
                                ? 'El contexto aún no tiene un catálogo curricular disponible.'
                                : 'Prueba ampliando los filtros o usando otro término de búsqueda.'
                        "
                    >
                        <BButton
                            v-if="hasActiveFilters"
                            type="button"
                            size="sm"
                            variant="outline-primary"
                            @click="clearFilters"
                        >
                            Ver todo el catálogo
                        </BButton>
                    </LibroDigitalStatePanel>

                    <section
                        v-else
                        id="lcd-objective-results"
                        class="ld-objectives__results"
                        aria-labelledby="lcd-objective-results-title"
                        tabindex="-1"
                    >
                        <header class="ld-objectives__results-head">
                            <div>
                                <span class="ld-eyebrow">Resultados</span>
                                <h3 id="lcd-objective-results-title">
                                    {{ formatNumber(totalRows) }} objetivos
                                </h3>
                            </div>
                            <span
                                class="ld-objectives__page-status"
                                aria-live="polite"
                            >
                                Página {{ currentPage }} de {{ lastPage }}
                                <span
                                    v-if="loading"
                                    class="spinner-border"
                                    aria-hidden="true"
                                ></span>
                            </span>
                        </header>

                        <div class="ld-objectives__table-wrap">
                            <table class="ld-objectives__table">
                                <caption class="visually-hidden">
                                    Objetivos curriculares filtrados. Cada fila
                                    permite abrir el detalle y sus fuentes.
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Código y tipo</th>
                                        <th scope="col">Nivel</th>
                                        <th scope="col">Asignatura</th>
                                        <th scope="col">Objetivo</th>
                                        <th scope="col">Eje</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">
                                            <span class="visually-hidden"
                                                >Acciones</span
                                            >
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="objective in objectives"
                                        :key="
                                            objective.public_id || objective.id
                                        "
                                    >
                                        <td>
                                            <button
                                                type="button"
                                                class="ld-objective-code"
                                                @click="openDetail(objective)"
                                            >
                                                {{
                                                    objective.code ||
                                                    "Sin código"
                                                }}
                                            </button>
                                            <span class="ld-objective-type">{{
                                                typeLabel(
                                                    objective.objective_type
                                                )
                                            }}</span>
                                        </td>
                                        <td>
                                            <strong>{{
                                                gradeLabel(objective.grade_code)
                                            }}</strong>
                                            <span>{{
                                                trackLabel(
                                                    objective.curriculum_track
                                                )
                                            }}</span>
                                        </td>
                                        <td>
                                            <strong>{{
                                                subjectName(objective)
                                            }}</strong>
                                            <span
                                                v-if="subjectCode(objective)"
                                                >{{
                                                    subjectCode(objective)
                                                }}</span
                                            >
                                        </td>
                                        <td>
                                            <p>
                                                {{
                                                    shortDescription(
                                                        objective.description
                                                    )
                                                }}
                                            </p>
                                        </td>
                                        <td>
                                            <span class="ld-objective-axis">{{
                                                axisLabel(objective.axis_code)
                                            }}</span>
                                        </td>
                                        <td>
                                            <span
                                                class="ld-objective-status"
                                                :class="
                                                    objectiveAvailable(
                                                        objective
                                                    )
                                                        ? 'ld-objective-status--active'
                                                        : 'ld-objective-status--inactive'
                                                "
                                            >
                                                <span aria-hidden="true"></span>
                                                {{
                                                    availabilityLabel(objective)
                                                }}
                                            </span>
                                        </td>
                                        <td>
                                            <BButton
                                                type="button"
                                                size="sm"
                                                variant="outline-primary"
                                                :aria-label="`Ver detalle de ${
                                                    objective.code || 'objetivo'
                                                }`"
                                                @click="openDetail(objective)"
                                            >
                                                Ver detalle
                                            </BButton>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="ld-objectives__cards"
                            aria-label="Objetivos curriculares"
                        >
                            <article
                                v-for="objective in objectives"
                                :key="`card-${
                                    objective.public_id || objective.id
                                }`"
                                class="ld-objective-card"
                            >
                                <header>
                                    <div>
                                        <span>{{
                                            typeLabel(objective.objective_type)
                                        }}</span>
                                        <strong>{{
                                            objective.code || "Sin código"
                                        }}</strong>
                                    </div>
                                    <span
                                        class="ld-objective-status"
                                        :class="
                                            objectiveAvailable(objective)
                                                ? 'ld-objective-status--active'
                                                : 'ld-objective-status--inactive'
                                        "
                                    >
                                        <span aria-hidden="true"></span>
                                        {{ availabilityLabel(objective) }}
                                    </span>
                                </header>
                                <div class="ld-objective-card__meta">
                                    <span
                                        ><i
                                            class="bx bx-layer"
                                            aria-hidden="true"
                                        ></i
                                        >{{
                                            gradeLabel(objective.grade_code)
                                        }}</span
                                    >
                                    <span
                                        ><i
                                            class="bx bx-book"
                                            aria-hidden="true"
                                        ></i
                                        >{{ subjectName(objective) }}</span
                                    >
                                    <span
                                        ><i
                                            class="bx bx-grid-alt"
                                            aria-hidden="true"
                                        ></i
                                        >{{
                                            axisLabel(objective.axis_code)
                                        }}</span
                                    >
                                </div>
                                <p>
                                    {{
                                        shortDescription(
                                            objective.description,
                                            320
                                        )
                                    }}
                                </p>
                                <footer>
                                    <span>{{
                                        trackLabel(objective.curriculum_track)
                                    }}</span>
                                    <BButton
                                        type="button"
                                        size="sm"
                                        variant="outline-primary"
                                        @click="openDetail(objective)"
                                    >
                                        Ver ficha completa
                                        <i
                                            class="bx bx-right-arrow-alt"
                                            aria-hidden="true"
                                        ></i>
                                    </BButton>
                                </footer>
                            </article>
                        </div>

                        <footer
                            v-if="lastPage > 1"
                            class="ld-objectives__pagination"
                        >
                            <span>
                                Mostrando {{ objectives.length }} de
                                {{ formatNumber(totalRows) }} resultados
                            </span>
                            <BPagination
                                :model-value="currentPage"
                                :total-rows="totalRows"
                                :per-page="
                                    Number(pagination.per_page || perPage)
                                "
                                :limit="5"
                                size="sm"
                                label-first-page="Primera página"
                                label-prev-page="Página anterior"
                                label-next-page="Página siguiente"
                                label-last-page="Última página"
                                @update:model-value="changePage"
                            />
                        </footer>
                    </section>
                </template>
            </CurriculumVisualizationPanel>
        </template>

        <BModal
            v-if="canExport"
            id="lcd-objective-export-modal"
            v-model="exportVisible"
            size="lg"
            title="Exportar objetivos curriculares"
            modal-class="ld-objective-export-modal"
            body-class="ld-objective-export-modal__body"
            hide-footer
            scrollable
            :no-close-on-backdrop="exporting || exportDownloading"
            :no-close-on-esc="exporting || exportDownloading"
            :hide-header-close="exporting || exportDownloading"
            header-close-label="Cerrar exportador"
            @hide="guardExportHide"
            @hidden="restoreExportFocus"
            @keydown="trapExportFocus"
            @shown="focusExportFormat"
        >
            <div class="ld-objective-export" role="document">
                <header class="ld-objective-export__hero">
                    <span
                        class="ld-objective-export__hero-icon"
                        aria-hidden="true"
                    >
                        <i class="bx bxs-report"></i>
                        <span><i class="bx bx-download"></i></span>
                    </span>
                    <div class="ld-objective-export__hero-copy">
                        <span class="ld-eyebrow"
                            >Exportación institucional</span
                        >
                        <h3>Un informe fiel a la búsqueda actual</h3>
                        <p>
                            Incluye todos los objetivos que cumplen los filtros,
                            no solo los de la página visible.
                        </p>
                    </div>
                    <div
                        class="ld-objective-export__hero-total"
                        :aria-label="`${formatNumber(
                            totalRows
                        )} objetivos en el alcance`"
                    >
                        <span>Alcance</span>
                        <strong>{{ formatNumber(totalRows) }}</strong>
                        <small>objetivos</small>
                    </div>
                </header>

                <div
                    class="ld-objective-export__context"
                    role="group"
                    aria-label="Contexto de la exportación"
                >
                    <div>
                        <span
                            class="ld-objective-export__context-icon"
                            aria-hidden="true"
                        >
                            <i class="bx bxs-school"></i>
                        </span>
                        <span class="ld-objective-export__context-copy">
                            <span class="ld-objective-export__context-label"
                                >Establecimiento</span
                            >
                            <strong>{{ schoolContextLabel }}</strong>
                        </span>
                    </div>
                    <div>
                        <span
                            class="ld-objective-export__context-icon"
                            aria-hidden="true"
                        >
                            <i class="bx bx-calendar"></i>
                        </span>
                        <span class="ld-objective-export__context-copy">
                            <span class="ld-objective-export__context-label"
                                >Año académico</span
                            >
                            <strong>{{ yearContextLabel }}</strong>
                        </span>
                    </div>
                    <div>
                        <span
                            class="ld-objective-export__context-icon"
                            aria-hidden="true"
                        >
                            <i class="bx bx-group"></i>
                        </span>
                        <span class="ld-objective-export__context-copy">
                            <span class="ld-objective-export__context-label"
                                >Curso contextual</span
                            >
                            <strong>{{ courseContextLabel }}</strong>
                        </span>
                    </div>
                    <div>
                        <span
                            class="ld-objective-export__context-icon"
                            aria-hidden="true"
                        >
                            <i class="bx bx-book-open"></i>
                        </span>
                        <span class="ld-objective-export__context-copy">
                            <span class="ld-objective-export__context-label"
                                >Modo de consulta</span
                            >
                            <strong>{{
                                bookScoped
                                    ? "Libro seleccionado"
                                    : "Catálogo global"
                            }}</strong>
                        </span>
                    </div>
                </div>

                <fieldset
                    class="ld-objective-export__formats"
                    :disabled="exporting || exportDownloading || exportReady"
                    aria-describedby="lcd-objective-export-format-help"
                >
                    <legend>
                        <span>Elige el formato de salida</span>
                        <small id="lcd-objective-export-format-help">
                            Ambos archivos respetan exactamente el alcance
                            indicado.
                        </small>
                    </legend>
                    <label
                        v-for="format in exportFormats"
                        :key="format.value"
                        class="ld-objective-export-format"
                        :class="[
                            `ld-objective-export-format--${format.tone}`,
                            {
                                'ld-objective-export-format--selected':
                                    exportFormat === format.value,
                                'ld-objective-export-format--disabled':
                                    format.disabled,
                            },
                        ]"
                        :aria-disabled="format.disabled ? 'true' : undefined"
                    >
                        <input
                            :id="`lcd-objective-export-format-${format.value}`"
                            v-model="exportFormat"
                            type="radio"
                            name="lcd-objective-export-format"
                            :value="format.value"
                            :disabled="format.disabled"
                            :aria-describedby="
                                format.disabled
                                    ? `lcd-objective-export-${format.value}-limit`
                                    : undefined
                            "
                        />
                        <span
                            class="ld-objective-export-format__icon"
                            aria-hidden="true"
                        >
                            <i class="bx" :class="format.icon"></i>
                        </span>
                        <span class="ld-objective-export-format__body">
                            <span class="ld-objective-export-format__title">
                                <strong>{{ format.label }}</strong>
                                <b>{{ format.value.toUpperCase() }}</b>
                            </span>
                            <small>{{ format.description }}</small>
                            <span class="ld-objective-export-format__meta">
                                <i class="bx bx-check" aria-hidden="true"></i>
                                {{ format.meta }}
                            </span>
                        </span>
                        <span
                            class="ld-objective-export-format__check"
                            aria-hidden="true"
                        >
                            <i class="bx bx-check"></i>
                        </span>
                    </label>
                </fieldset>

                <BAlert
                    v-if="!pdfExportAvailable"
                    id="lcd-objective-export-pdf-limit"
                    :model-value="true"
                    variant="warning"
                    class="ld-objective-export__alert mb-0"
                >
                    <i class="bx bx-filter-alt" aria-hidden="true"></i>
                    <div>
                        <strong>
                            PDF disponible hasta
                            {{ formatNumber(pdfExportMaxRows) }} objetivos
                        </strong>
                        <p>
                            Este alcance contiene {{ formatNumber(totalRows) }}.
                            Aplica filtros para PDF o conserva todos los
                            resultados con el Libro Excel preseleccionado.
                        </p>
                    </div>
                </BAlert>

                <BAlert
                    v-if="!xlsxExportAvailable"
                    id="lcd-objective-export-xlsx-limit"
                    :model-value="true"
                    variant="danger"
                    class="ld-objective-export__alert mb-0"
                    role="alert"
                >
                    <i class="bx bx-error-circle" aria-hidden="true"></i>
                    <div>
                        <strong>El alcance supera el máximo exportable</strong>
                        <p>
                            Excel admite hasta
                            {{ formatNumber(xlsxExportMaxRows) }} objetivos.
                            Aplica uno o más filtros antes de generar el
                            archivo.
                        </p>
                    </div>
                </BAlert>

                <section
                    class="ld-objective-export__scope"
                    aria-labelledby="lcd-objective-export-scope-title"
                >
                    <div class="ld-objective-export__scope-head">
                        <span
                            class="ld-objective-export__scope-icon"
                            aria-hidden="true"
                        >
                            <i class="bx bx-filter-alt"></i>
                        </span>
                        <div>
                            <span class="ld-eyebrow">Alcance exacto</span>
                            <h4 id="lcd-objective-export-scope-title">
                                Filtros aplicados
                                <small
                                    >{{
                                        exportFilterChips.length
                                    }}
                                    criterios</small
                                >
                            </h4>
                        </div>
                        <BButton
                            v-if="hasActiveFilters && !exportJob && !exporting"
                            type="button"
                            size="sm"
                            variant="link"
                            class="ld-objective-export__clear"
                            :disabled="loading || exportDownloading"
                            @click="clearExportFilters"
                        >
                            <i class="bx bx-reset" aria-hidden="true"></i>
                            Limpiar filtros
                        </BButton>
                    </div>
                    <div class="ld-objective-export__chips">
                        <span
                            v-for="chip in exportFilterChips"
                            :key="`${chip.label}:${chip.value}`"
                            :aria-label="`${chip.label}: ${chip.value}`"
                        >
                            <b>{{ chip.label }}</b>
                            <span>{{ chip.value }}</span>
                        </span>
                    </div>
                </section>

                <section
                    v-if="exporting || exportJob"
                    id="lcd-objective-export-progress"
                    class="ld-objective-export__progress"
                    :class="{
                        'ld-objective-export__progress--ready': exportReady,
                    }"
                    aria-live="polite"
                    aria-atomic="true"
                    tabindex="-1"
                >
                    <span
                        class="ld-objective-export__progress-icon"
                        aria-hidden="true"
                    >
                        <span
                            v-if="exporting && !exportReady"
                            class="spinner-border"
                        ></span>
                        <i v-else class="bx bx-check-shield"></i>
                    </span>
                    <div>
                        <div class="ld-objective-export__progress-heading">
                            <strong>{{ exportStatusLabel }}</strong>
                            <span>{{ Math.round(exportProgress) }}%</span>
                        </div>
                        <div
                            class="ld-objective-export__progress-track"
                            role="progressbar"
                            aria-label="Progreso de generación"
                            :aria-valuenow="Math.round(exportProgress)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <span
                                :style="{ width: `${exportProgress}%` }"
                            ></span>
                        </div>
                        <small>
                            El archivo se genera en un proceso privado y se
                            descarga mediante tu sesión autenticada.
                        </small>
                    </div>
                </section>

                <BAlert
                    v-if="exportTimedOut"
                    :model-value="true"
                    variant="info"
                    class="ld-objective-export__alert mb-0"
                >
                    <i class="bx bx-time-five" aria-hidden="true"></i>
                    <div>
                        <strong>La generación continúa en segundo plano</strong>
                        <p>
                            Puedes volver a consultar el estado sin crear una
                            solicitud duplicada.
                        </p>
                    </div>
                </BAlert>

                <BAlert
                    v-if="exportDownloaded && !exportError"
                    :model-value="true"
                    variant="success"
                    class="ld-objective-export__alert mb-0"
                >
                    <i class="bx bx-check-circle" aria-hidden="true"></i>
                    <div>
                        <strong>Descarga iniciada</strong>
                        <p>
                            El informe quedó generado y disponible para volver a
                            descargar.
                        </p>
                    </div>
                </BAlert>

                <BAlert
                    v-if="exportError"
                    :model-value="true"
                    variant="danger"
                    class="ld-objective-export__alert mb-0"
                    role="alert"
                >
                    <i class="bx bx-error-circle" aria-hidden="true"></i>
                    <div>
                        <strong>No se pudo completar la exportación</strong>
                        <p>{{ errorMessage(exportError) }}</p>
                        <code v-if="exportError.correlationId">
                            ID de seguimiento: {{ exportError.correlationId }}
                        </code>
                    </div>
                </BAlert>

                <footer class="ld-objective-export__footer">
                    <span>
                        <i class="bx bx-lock-alt" aria-hidden="true"></i>
                        Descarga privada y trazable
                    </span>
                    <div>
                        <BButton
                            type="button"
                            variant="outline-secondary"
                            :disabled="exporting || exportDownloading"
                            @click="exportVisible = false"
                        >
                            {{ exportDownloaded ? "Cerrar" : "Cancelar" }}
                        </BButton>
                        <BButton
                            v-if="exportTimedOut"
                            type="button"
                            variant="primary"
                            :disabled="exporting || exportDownloading"
                            @click="resumeExport"
                        >
                            <span
                                v-if="exporting"
                                class="spinner-border spinner-border-sm"
                            ></span>
                            <i
                                v-else
                                class="bx bx-refresh"
                                aria-hidden="true"
                            ></i>
                            Consultar estado
                        </BButton>
                        <BButton
                            v-else-if="exportReady"
                            type="button"
                            variant="primary"
                            :disabled="exporting || exportDownloading"
                            @click="downloadExport()"
                        >
                            <span
                                v-if="exportDownloading"
                                class="spinner-border spinner-border-sm"
                            ></span>
                            <i
                                v-else
                                class="bx bx-download"
                                aria-hidden="true"
                            ></i>
                            {{
                                exportDownloaded
                                    ? "Descargar nuevamente"
                                    : "Descargar"
                            }}
                        </BButton>
                        <BButton
                            v-else
                            type="button"
                            variant="primary"
                            :disabled="
                                exporting ||
                                exportDownloading ||
                                loading ||
                                searchPending ||
                                totalRows < 1 ||
                                !exportFormatAvailable
                            "
                            @click="generateExport"
                        >
                            <span
                                v-if="exporting"
                                class="spinner-border spinner-border-sm"
                            ></span>
                            <i
                                v-else
                                class="bx bx-download"
                                aria-hidden="true"
                            ></i>
                            {{
                                exporting
                                    ? "Generando…"
                                    : `Generar ${exportFormat.toUpperCase()}`
                            }}
                        </BButton>
                    </div>
                </footer>
            </div>
        </BModal>

        <BModal
            v-model="detailVisible"
            size="xl"
            title="Ficha del objetivo curricular"
            modal-class="ld-objective-detail-modal"
            body-class="ld-objective-detail-modal__body"
            hide-footer
            scrollable
        >
            <LibroDigitalStatePanel
                v-if="detailLoading && !selectedObjective"
                compact
                state="loading"
                title="Cargando ficha curricular"
                message="Consultando descripción y trazabilidad oficial."
            />
            <LibroDigitalStatePanel
                v-else-if="detailError && !selectedObjective"
                compact
                state="error"
                title="No se pudo cargar el detalle"
                :message="errorMessage(detailError)"
                @retry="retryDetail"
            />
            <article v-else-if="selectedObjective" class="ld-objective-detail">
                <header class="ld-objective-detail__hero">
                    <div>
                        <span class="ld-objective-detail__eyebrow">
                            {{
                                typeLabels[selectedObjective.objective_type] ||
                                humanize(selectedObjective.objective_type)
                            }}
                        </span>
                        <h3>
                            {{
                                selectedObjective.code || "Objetivo sin código"
                            }}
                        </h3>
                        <p>{{ catalogLabel(selectedObjective) }}</p>
                    </div>
                    <span
                        class="ld-objective-status ld-objective-status--large"
                        :class="
                            objectiveAvailable(selectedObjective)
                                ? 'ld-objective-status--active'
                                : 'ld-objective-status--inactive'
                        "
                    >
                        <span aria-hidden="true"></span>
                        {{ availabilityLabel(selectedObjective) }}
                    </span>
                </header>

                <BAlert
                    v-if="detailError"
                    :model-value="true"
                    variant="warning"
                    class="mb-0"
                    role="status"
                >
                    <i class="bx bx-error-circle" aria-hidden="true"></i>
                    Se muestra el resumen disponible; no fue posible recuperar
                    toda la trazabilidad.
                    <BButton
                        type="button"
                        size="sm"
                        variant="link"
                        @click="retryDetail"
                        >Reintentar</BButton
                    >
                </BAlert>

                <section
                    class="ld-objective-detail__text"
                    aria-labelledby="lcd-objective-description-title"
                >
                    <span class="ld-eyebrow">Texto oficial</span>
                    <h4 id="lcd-objective-description-title">Descripción</h4>
                    <p>
                        {{
                            selectedObjective.description ||
                            "Sin descripción informada."
                        }}
                    </p>
                </section>

                <dl class="ld-objective-detail__facts">
                    <div>
                        <dt>Grado</dt>
                        <dd>{{ gradeLabel(selectedObjective.grade_code) }}</dd>
                    </div>
                    <div>
                        <dt>Nivel</dt>
                        <dd>
                            {{
                                levelLabels[selectedObjective.level_code] ||
                                humanize(selectedObjective.level_code)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>Tipo de enseñanza</dt>
                        <dd>
                            {{ trackLabel(selectedObjective.curriculum_track) }}
                        </dd>
                    </div>
                    <div>
                        <dt>Asignatura</dt>
                        <dd>{{ subjectName(selectedObjective) }}</dd>
                    </div>
                    <div>
                        <dt>Eje</dt>
                        <dd>{{ axisLabel(selectedObjective.axis_code) }}</dd>
                    </div>
                    <div>
                        <dt>Unidad</dt>
                        <dd>
                            {{
                                selectedObjective.unit_code
                                    ? humanize(selectedObjective.unit_code)
                                    : "No especificada"
                            }}
                        </dd>
                    </div>
                </dl>

                <section
                    v-if="indicators.length"
                    class="ld-objective-detail__section"
                >
                    <div class="ld-objective-detail__section-head">
                        <span
                            class="ld-objective-detail__section-icon"
                            aria-hidden="true"
                            ><i class="bx bx-list-check"></i
                        ></span>
                        <div>
                            <span class="ld-eyebrow">Apoyo pedagógico</span>
                            <h4>Indicadores</h4>
                        </div>
                    </div>
                    <ul class="ld-objective-detail__indicators">
                        <li
                            v-for="(indicator, index) in indicators"
                            :key="index"
                        >
                            {{
                                typeof indicator === "object"
                                    ? indicator.description ||
                                      indicator.text ||
                                      JSON.stringify(indicator)
                                    : indicator
                            }}
                        </li>
                    </ul>
                </section>

                <section
                    class="ld-objective-detail__section"
                    aria-labelledby="lcd-objective-sources-title"
                >
                    <div class="ld-objective-detail__section-head">
                        <span
                            class="ld-objective-detail__section-icon"
                            aria-hidden="true"
                            ><i class="bx bx-file-find"></i
                        ></span>
                        <div>
                            <span class="ld-eyebrow"
                                >Trazabilidad normativa</span
                            >
                            <h4 id="lcd-objective-sources-title">
                                Fuentes oficiales
                            </h4>
                        </div>
                    </div>
                    <div
                        v-if="sourceRows.length"
                        class="ld-objective-detail__sources"
                    >
                        <article
                            v-for="(source, index) in sourceRows"
                            :key="source.public_id || source.id || index"
                        >
                            <span class="ld-objective-detail__source-role">{{
                                sourceRole(source)
                            }}</span>
                            <span
                                class="ld-objective-detail__source-verification"
                                :class="
                                    sourceVerified(source)
                                        ? 'ld-objective-detail__source-verification--verified'
                                        : 'ld-objective-detail__source-verification--attention'
                                "
                            >
                                <i
                                    class="bx"
                                    :class="
                                        sourceVerified(source)
                                            ? 'bx-check-shield'
                                            : 'bx-error-circle'
                                    "
                                    aria-hidden="true"
                                ></i>
                                {{
                                    sourceVerified(source)
                                        ? "Verificada"
                                        : "Requiere revisión"
                                }}
                            </span>
                            <h5>{{ sourceTitle(source) }}</h5>
                            <p>
                                {{ sourceAuthority(source) }}
                                <template v-if="sourceDocument(source)">
                                    · {{ sourceDocument(source) }}</template
                                >
                            </p>
                            <div>
                                <span v-if="sourceLocator(source)"
                                    ><i class="bx bx-map" aria-hidden="true"></i
                                    >{{ sourceLocator(source) }}</span
                                >
                                <code
                                    v-if="sourceHash(source)"
                                    :title="`SHA-256: ${sourceHash(source)}`"
                                    >SHA-256 {{ abbreviatedHash(source) }}</code
                                >
                                <a
                                    v-if="sourceUrl(source)"
                                    :href="sourceUrl(source)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir fuente oficial
                                    <span class="visually-hidden">
                                        (se abre en una pestaña nueva)</span
                                    >
                                    <i
                                        class="bx bx-link-external"
                                        aria-hidden="true"
                                    ></i>
                                </a>
                            </div>
                        </article>
                    </div>
                    <LibroDigitalStatePanel
                        v-else-if="detailLoading"
                        compact
                        state="loading"
                        title="Consultando fuentes"
                        message="Recuperando las relaciones de trazabilidad."
                    />
                    <LibroDigitalStatePanel
                        v-else
                        compact
                        state="empty"
                        title="Sin fuentes visibles"
                        message="La ficha no informó vínculos públicos para este objetivo."
                    />
                </section>

                <footer class="ld-objective-detail__footer">
                    <span>
                        <i class="bx bx-shield-quarter" aria-hidden="true"></i>
                        {{
                            objectiveAvailable(selectedObjective)
                                ? "Disponible para uso pedagógico en el leccionario."
                                : "Conservado como referencia; no puede seleccionarse en el leccionario."
                        }}
                    </span>
                    <BButton
                        type="button"
                        variant="primary"
                        @click="detailVisible = false"
                        >Cerrar ficha</BButton
                    >
                </footer>
            </article>
        </BModal>
    </section>
</template>

<style scoped>
.ld-objectives {
    display: grid;
    gap: 1rem;
    min-width: 0;
}

.ld-objectives__header {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    overflow: hidden;
    padding: 1.25rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-lg);
    background: radial-gradient(
            circle at 90% -45%,
            rgba(185, 137, 63, 0.2),
            transparent 43%
        ),
        linear-gradient(135deg, var(--lcd-brand-50), #fff 62%);
    box-shadow: var(--lcd-shadow-sm);
}

.ld-objectives__identity {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    min-width: 0;
}

.ld-objectives__mark {
    display: grid;
    flex: 0 0 48px;
    place-items: center;
    width: 48px;
    height: 48px;
    border: 1px solid rgba(36, 84, 134, 0.16);
    border-radius: 14px;
    background: linear-gradient(
        145deg,
        var(--lcd-brand-700),
        var(--lcd-brand-900)
    );
    box-shadow: 0 9px 22px rgba(28, 65, 108, 0.18);
    color: #fff;
    font-size: 1.35rem;
}

.ld-objectives h2,
.ld-objectives h3,
.ld-objectives h4,
.ld-objectives h5,
.ld-objectives p {
    margin-top: 0;
}

.ld-objectives__header h2 {
    margin-bottom: 0.25rem;
    color: var(--lcd-ink);
    font-size: clamp(1.25rem, 2vw, 1.6rem);
    font-weight: 750;
    letter-spacing: -0.03em;
}

.ld-objectives__header p {
    max-width: 720px;
    margin-bottom: 0;
    color: var(--lcd-muted);
    font-size: 0.82rem;
    line-height: 1.6;
}

.ld-objectives__header-actions {
    display: grid;
    flex: 0 0 auto;
    justify-items: end;
    gap: 0.55rem;
}

.ld-objectives__scope {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--lcd-muted);
    font-size: 0.72rem;
    font-weight: 700;
}

.ld-objective-export {
    display: grid;
    gap: 0.9rem;
    min-width: 0;
    color: var(--lcd-ink-soft);
}

.ld-objective-export__hero {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.9rem;
    overflow: hidden;
    padding: 0.9rem;
    border: 1px solid rgba(36, 84, 134, 0.16);
    border-radius: var(--lcd-radius-md);
    background: radial-gradient(
            circle at 93% -45%,
            rgba(185, 137, 63, 0.24),
            transparent 43%
        ),
        linear-gradient(135deg, var(--lcd-brand-50), #fff 72%);
}

.ld-objective-export__hero-icon {
    position: relative;
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: linear-gradient(
        145deg,
        var(--lcd-brand-700),
        var(--lcd-brand-900)
    );
    box-shadow: 0 9px 20px rgba(28, 65, 108, 0.2);
    color: #fff;
    font-size: 1.35rem;
}

.ld-objective-export__hero-icon > span {
    position: absolute;
    right: -4px;
    bottom: -4px;
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: var(--lcd-gold-500);
    color: var(--lcd-brand-950);
    font-size: 0.72rem;
}

.ld-objective-export__hero-copy {
    min-width: 0;
}

.ld-objective-export__hero h3 {
    margin: 0.12rem 0 0.22rem;
    color: var(--lcd-ink);
    font-size: 1.02rem;
    font-weight: 760;
    letter-spacing: -0.02em;
}

.ld-objective-export__hero p {
    margin-bottom: 0;
    color: var(--lcd-muted);
    font-size: 0.75rem;
    line-height: 1.5;
}

.ld-objective-export__hero-total {
    display: grid;
    min-width: 104px;
    padding-left: 0.9rem;
    border-left: 1px solid rgba(36, 84, 134, 0.16);
    text-align: right;
}

.ld-objective-export__hero-total > span {
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.055em;
    text-transform: uppercase;
}

.ld-objective-export__hero-total strong {
    color: var(--lcd-brand-900);
    font-size: 1.42rem;
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -0.04em;
}

.ld-objective-export__hero-total small {
    color: var(--lcd-muted);
    font-size: 0.72rem;
    font-weight: 650;
}

.ld-objective-export__context {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow: hidden;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-md);
    background: var(--lcd-surface-raised);
}

.ld-objective-export__context > div {
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    padding: 0.68rem 0.75rem;
    border-right: 1px solid var(--lcd-border);
    border-bottom: 1px solid var(--lcd-border);
}

.ld-objective-export__context > div:nth-child(2n) {
    border-right: 0;
}
.ld-objective-export__context > div:nth-last-child(-n + 2) {
    border-bottom: 0;
}

.ld-objective-export__context-icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--lcd-brand-50);
    color: var(--lcd-brand-700);
    font-size: 0.98rem;
}

.ld-objective-export__context-copy {
    display: grid;
    min-width: 0;
}

.ld-objective-export__context-label {
    margin-bottom: 0.08rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.045em;
    text-transform: uppercase;
}

.ld-objective-export__context-copy strong {
    color: var(--lcd-ink);
    font-size: 0.76rem;
    font-weight: 720;
    line-height: 1.38;
    overflow-wrap: anywhere;
}

.ld-objective-export__formats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.ld-objective-export__formats legend {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    float: none;
    grid-column: 1 / -1;
    width: 100%;
    margin-bottom: -0.05rem;
    color: var(--lcd-ink-soft);
    font-size: 0.78rem;
    font-weight: 800;
}

.ld-objective-export__formats legend small {
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.4;
    text-align: right;
}

.ld-objective-export-format {
    position: relative;
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr) 24px;
    align-items: center;
    gap: 0.72rem;
    min-width: 0;
    min-height: 96px;
    padding: 0.8rem;
    border: 1px solid var(--lcd-border-strong);
    border-radius: var(--lcd-radius-md);
    background: #fff;
    cursor: pointer;
    transition: border-color var(--lcd-transition),
        background-color var(--lcd-transition), box-shadow var(--lcd-transition),
        transform var(--lcd-transition);
}

.ld-objective-export-format:not(.ld-objective-export-format--disabled):hover {
    border-color: var(--lcd-brand-500);
    box-shadow: var(--lcd-shadow-sm);
    transform: translateY(-1px);
}

.ld-objective-export-format--selected {
    border-color: var(--lcd-brand-600);
    background: var(--lcd-brand-50);
    box-shadow: 0 0 0 3px rgba(36, 84, 134, 0.08);
}

.ld-objective-export-format:focus-within {
    outline: 3px solid var(--lcd-focus);
    outline-offset: 2px;
}

.ld-objective-export-format input {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    opacity: 0;
    white-space: nowrap;
}

.ld-objective-export-format__icon {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--lcd-surface-muted);
    color: var(--lcd-brand-700);
    font-size: 1.3rem;
}

.ld-objective-export-format--pdf .ld-objective-export-format__icon {
    background: rgba(180, 62, 77, 0.09);
    color: var(--lcd-danger);
}

.ld-objective-export-format--xlsx .ld-objective-export-format__icon {
    background: rgba(36, 115, 90, 0.09);
    color: var(--lcd-success);
}

.ld-objective-export-format--pdf.ld-objective-export-format--selected {
    border-color: rgba(180, 62, 77, 0.58);
    background: rgba(180, 62, 77, 0.045);
}

.ld-objective-export-format--xlsx.ld-objective-export-format--selected {
    border-color: rgba(36, 115, 90, 0.58);
    background: rgba(36, 115, 90, 0.045);
}

.ld-objective-export-format--selected .ld-objective-export-format__icon {
    background: currentColor;
    color: #fff;
}

.ld-objective-export-format--pdf.ld-objective-export-format--selected
    .ld-objective-export-format__icon {
    background: var(--lcd-danger);
}

.ld-objective-export-format--xlsx.ld-objective-export-format--selected
    .ld-objective-export-format__icon {
    background: var(--lcd-success);
}

.ld-objective-export-format__body {
    display: grid;
    min-width: 0;
}

.ld-objective-export-format__title {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.ld-objective-export-format__title strong {
    color: var(--lcd-ink);
    font-size: 0.8rem;
    font-weight: 800;
}

.ld-objective-export-format__title b {
    padding: 0.14rem 0.3rem;
    border-radius: 5px;
    background: var(--lcd-surface-muted);
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.ld-objective-export-format__body > small {
    margin-top: 0.14rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
    line-height: 1.4;
}

.ld-objective-export-format__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.22rem;
    margin-top: 0.3rem;
    color: var(--lcd-ink-soft);
    font-size: 0.7rem;
    font-weight: 700;
}

.ld-objective-export-format__meta i {
    color: var(--lcd-success);
    font-size: 0.88rem;
}

.ld-objective-export-format__check {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border: 1px solid var(--lcd-border-strong);
    border-radius: 50%;
    background: #fff;
    color: transparent;
    font-size: 0.92rem;
}

.ld-objective-export-format--selected .ld-objective-export-format__check {
    border-color: var(--lcd-brand-700);
    background: var(--lcd-brand-700);
    color: #fff;
}

.ld-objective-export-format--pdf.ld-objective-export-format--selected
    .ld-objective-export-format__check {
    border-color: var(--lcd-danger);
    background: var(--lcd-danger);
}

.ld-objective-export-format--xlsx.ld-objective-export-format--selected
    .ld-objective-export-format__check {
    border-color: var(--lcd-success);
    background: var(--lcd-success);
}

.ld-objective-export-format--disabled {
    cursor: not-allowed;
    opacity: 0.58;
}
.ld-objective-export-format--disabled:hover {
    border-color: var(--lcd-border);
    box-shadow: none;
    transform: none;
}
.ld-objective-export__formats:disabled .ld-objective-export-format {
    cursor: not-allowed;
    opacity: 0.68;
    transform: none;
}

.ld-objective-export__scope {
    display: grid;
    gap: 0.7rem;
    padding: 0.8rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-md);
    background: var(--lcd-surface-muted);
}

.ld-objective-export__scope-head {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.65rem;
}

.ld-objective-export__scope-icon {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--lcd-brand-100);
    color: var(--lcd-brand-700);
    font-size: 1rem;
}

.ld-objective-export__scope h4 {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.38rem;
    margin: 0.08rem 0 0;
    color: var(--lcd-ink);
    font-size: 0.85rem;
    font-weight: 760;
}

.ld-objective-export__scope h4 small {
    padding: 0.14rem 0.35rem;
    border-radius: 999px;
    background: #fff;
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 700;
}

.ld-objective-export__clear {
    justify-self: end;
    min-height: 36px;
    padding-inline: 0.45rem;
    color: var(--lcd-brand-700);
    text-decoration: none;
}

.ld-objective-export__clear:hover {
    color: var(--lcd-brand-900);
    text-decoration: underline;
}

.ld-objective-export__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.42rem;
}

.ld-objective-export__chips > span {
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
    max-width: 100%;
    padding: 0.36rem 0.5rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-sm);
    background: #fff;
    color: var(--lcd-ink-soft);
    font-size: 0.7rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.ld-objective-export__chips b {
    flex: 0 0 auto;
    color: var(--lcd-brand-700);
    font-weight: 800;
}

.ld-objective-export__chips b::after {
    color: var(--lcd-subtle);
    content: ":";
}
.ld-objective-export__chips > span > span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.ld-objective-export__progress {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    gap: 0.72rem;
    padding: 0.85rem;
    border: 1px solid rgba(39, 113, 138, 0.22);
    border-radius: var(--lcd-radius-md);
    background: rgba(39, 113, 138, 0.06);
}

.ld-objective-export__progress--ready {
    border-color: rgba(36, 115, 90, 0.22);
    background: rgba(36, 115, 90, 0.06);
}

.ld-objective-export__progress-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 11px;
    background: #fff;
    color: var(--lcd-info);
    font-size: 1.15rem;
}

.ld-objective-export__progress--ready .ld-objective-export__progress-icon {
    color: var(--lcd-success);
}
.ld-objective-export__progress-icon .spinner-border {
    width: 1.1rem;
    height: 1.1rem;
    border-width: 0.14em;
}
.ld-objective-export__progress-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.ld-objective-export__progress-heading strong {
    color: var(--lcd-ink);
    font-size: 0.76rem;
    font-weight: 780;
}
.ld-objective-export__progress-heading span {
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 760;
}
.ld-objective-export__progress-track {
    height: 7px;
    margin: 0.5rem 0 0.38rem;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(39, 113, 138, 0.13);
}
.ld-objective-export__progress-track > span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--lcd-brand-600), var(--lcd-info));
    transition: width 0.25s ease;
}
.ld-objective-export__progress--ready
    .ld-objective-export__progress-track
    > span {
    background: var(--lcd-success);
}
.ld-objective-export__progress small {
    color: var(--lcd-muted);
    font-size: 0.7rem;
    line-height: 1.4;
}

.ld-objective-export__alert {
    display: flex;
    align-items: flex-start;
    gap: 0.62rem;
    font-size: 0.74rem;
    line-height: 1.5;
}
.ld-objective-export__alert > i {
    flex: 0 0 auto;
    margin-top: 0.08rem;
    font-size: 1.1rem;
}
.ld-objective-export__alert strong {
    display: block;
    margin-bottom: 0.08rem;
    color: inherit;
}
.ld-objective-export__alert p {
    margin-bottom: 0;
    color: inherit;
}
.ld-objective-export__alert code {
    display: inline-block;
    margin-top: 0.38rem;
    padding: 0.24rem 0.38rem;
    border-radius: 5px;
    background: rgba(255, 255, 255, 0.72);
    color: inherit;
    font-size: 0.7rem;
    overflow-wrap: anywhere;
}

.ld-objective-export__footer {
    position: sticky;
    z-index: 4;
    bottom: -1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    margin: 0 -1rem -1rem;
    padding: 0.82rem 1rem 1rem;
    border-top: 1px solid var(--lcd-border);
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 -10px 22px rgba(20, 39, 65, 0.055);
    backdrop-filter: blur(10px);
}

.ld-objective-export__footer > span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 700;
}

.ld-objective-export__footer > div {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}
.ld-objective-export__footer .btn {
    min-height: 40px;
}

.ld-objectives__usage-note,
.ld-objectives__stale-alert {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    border-color: rgba(39, 113, 138, 0.22);
    background: rgba(39, 113, 138, 0.07);
    color: var(--lcd-ink-soft);
    font-size: 0.78rem;
    line-height: 1.5;
}

.ld-objectives__usage-note > i,
.ld-objectives__stale-alert > i {
    flex: 0 0 auto;
    color: var(--lcd-info);
    font-size: 1.15rem;
}

.ld-objectives__kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.ld-objectives-kpi {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.72rem;
    overflow: hidden;
    min-width: 0;
    padding: 0.95rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-md);
    background: var(--lcd-surface-raised);
    box-shadow: var(--lcd-shadow-sm);
}

.ld-objectives-kpi::after {
    position: absolute;
    inset: auto 0 0;
    height: 3px;
    background: var(--lcd-brand-600);
    content: "";
}

.ld-objectives-kpi__icon {
    display: grid;
    flex: 0 0 38px;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: var(--lcd-brand-50);
    color: var(--lcd-brand-700);
    font-size: 1.08rem;
}

.ld-objectives-kpi > div {
    display: grid;
    min-width: 0;
}

.ld-objectives-kpi > div > span {
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 720;
}

.ld-objectives-kpi strong {
    margin: 0.12rem 0;
    color: var(--lcd-ink);
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.1;
}

.ld-objectives-kpi small {
    min-height: 1.9em;
    color: var(--lcd-subtle);
    font-size: 0.7rem;
    line-height: 1.35;
    white-space: normal;
}

.ld-objectives-kpi--success::after {
    background: var(--lcd-success);
}
.ld-objectives-kpi--success .ld-objectives-kpi__icon {
    background: rgba(36, 115, 90, 0.09);
    color: var(--lcd-success);
}
.ld-objectives-kpi--warning::after {
    background: var(--lcd-warning);
}
.ld-objectives-kpi--warning .ld-objectives-kpi__icon {
    background: rgba(165, 104, 25, 0.09);
    color: var(--lcd-warning);
}
.ld-objectives-kpi--info::after {
    background: var(--lcd-info);
}
.ld-objectives-kpi--info .ld-objectives-kpi__icon {
    background: rgba(39, 113, 138, 0.09);
    color: var(--lcd-info);
}

.ld-objectives__filters {
    display: grid;
    gap: 0.85rem;
    padding: 1rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-lg);
    background: var(--lcd-surface-raised);
    box-shadow: var(--lcd-shadow-sm);
}

.ld-objectives__filter-heading,
.ld-objectives__results-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.ld-objectives__filter-heading h3,
.ld-objectives__results-head h3 {
    margin-bottom: 0;
    color: var(--lcd-ink);
    font-size: 0.98rem;
    font-weight: 760;
}

.ld-objectives__search-field > label,
.ld-objectives__field > label {
    display: block;
    margin-bottom: 0.32rem;
    color: var(--lcd-ink-soft);
    font-size: 0.7rem;
    font-weight: 750;
}

.ld-objectives__search-field .input-group-text {
    border-right: 0;
    color: var(--lcd-brand-600);
}

.ld-objectives__search-field .form-control {
    border-right: 0;
    border-left: 0;
}

.ld-objectives__search-field .btn {
    min-width: 96px;
}

.ld-objectives__filter-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.7rem;
}

.ld-objectives__field {
    min-width: 0;
}

.ld-objectives__field-note {
    display: block;
    margin-top: 0.3rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
    line-height: 1.4;
}

.ld-objectives__results {
    overflow: hidden;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-lg);
    background: var(--lcd-surface-raised);
    box-shadow: var(--lcd-shadow-sm);
}

.ld-objectives__results:focus {
    outline: none;
}

.ld-objectives__results-head {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid var(--lcd-border);
    background: linear-gradient(180deg, #fff, var(--lcd-surface-muted));
}

.ld-objectives__page-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--lcd-muted);
    font-size: 0.72rem;
    font-weight: 700;
}

.ld-objectives__page-status .spinner-border {
    width: 0.9rem;
    height: 0.9rem;
    border-width: 0.12em;
    color: var(--lcd-brand-600);
}

.ld-objectives__table-wrap {
    overflow-x: auto;
}

.ld-objectives__table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

.ld-objectives__table th {
    padding: 0.68rem 0.8rem;
    border-bottom: 1px solid var(--lcd-border-strong);
    background: var(--lcd-surface-muted);
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.055em;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}

.ld-objectives__table td {
    max-width: 360px;
    padding: 0.82rem;
    border-bottom: 1px solid var(--lcd-border);
    color: var(--lcd-ink-soft);
    font-size: 0.74rem;
    line-height: 1.48;
    vertical-align: top;
}

.ld-objectives__table tbody tr:last-child td {
    border-bottom: 0;
}
.ld-objectives__table tbody tr:hover {
    background: var(--lcd-brand-50);
}
.ld-objectives__table td > strong,
.ld-objectives__table td > span {
    display: block;
}
.ld-objectives__table td > strong {
    color: var(--lcd-ink);
    font-weight: 750;
}
.ld-objectives__table td > span:not(.ld-objective-status) {
    margin-top: 0.15rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
}
.ld-objectives__table td > p {
    margin-bottom: 0;
    color: var(--lcd-ink-soft);
}

.ld-objective-code {
    display: block;
    max-width: 190px;
    overflow-wrap: anywhere;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--lcd-brand-700);
    font-size: 0.75rem;
    font-weight: 800;
    text-align: left;
    text-decoration: underline;
    text-decoration-color: transparent;
    text-underline-offset: 3px;
}

.ld-objective-code:hover {
    color: var(--lcd-brand-900);
    text-decoration-color: currentColor;
}

.ld-objective-type {
    margin-top: 0.28rem !important;
    color: var(--lcd-muted) !important;
    font-size: 0.7rem !important;
    font-weight: 750;
}

.ld-objective-axis {
    display: inline-flex !important;
    padding: 0.25rem 0.42rem;
    border: 1px solid var(--lcd-border);
    border-radius: 999px;
    background: var(--lcd-surface-muted);
    color: var(--lcd-ink-soft) !important;
    line-height: 1.3;
}

.ld-objective-status {
    display: inline-flex !important;
    align-items: center;
    gap: 0.38rem;
    min-width: max-content;
    padding: 0.28rem 0.48rem;
    border: 1px solid currentColor;
    border-radius: 999px;
    font-size: 0.7rem !important;
    font-weight: 750;
    line-height: 1.25;
}

.ld-objective-status > span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.ld-objective-status--active {
    background: rgba(36, 115, 90, 0.06);
    color: var(--lcd-success) !important;
}
.ld-objective-status--inactive {
    background: rgba(165, 104, 25, 0.06);
    color: var(--lcd-warning) !important;
}
.ld-objective-status--large {
    padding: 0.4rem 0.62rem;
    font-size: 0.72rem !important;
}

.ld-objectives__cards {
    display: none;
}

.ld-objectives__pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-top: 1px solid var(--lcd-border);
    background: var(--lcd-surface-muted);
}

.ld-objectives__pagination > span {
    color: var(--lcd-muted);
    font-size: 0.72rem;
}
.ld-objectives__pagination :deep(.pagination) {
    margin-bottom: 0;
}
.ld-objectives__pagination :deep(.page-link) {
    border-color: var(--lcd-border);
    color: var(--lcd-brand-700);
    font-weight: 700;
}
.ld-objectives__pagination :deep(.active .page-link) {
    border-color: var(--lcd-brand-700);
    background: var(--lcd-brand-700);
    color: #fff;
}

.ld-objective-detail {
    display: grid;
    gap: 1rem;
}

.ld-objective-detail__hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem;
    border-radius: var(--lcd-radius-md);
    background: radial-gradient(
            circle at 90% 0,
            rgba(185, 137, 63, 0.2),
            transparent 48%
        ),
        linear-gradient(135deg, var(--lcd-brand-950), var(--lcd-brand-700));
    color: #fff;
}

.ld-objective-detail__eyebrow {
    color: rgba(232, 242, 249, 0.75);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.ld-objective-detail__hero h3 {
    margin: 0.25rem 0;
    color: #fff;
    font-size: clamp(1.15rem, 2.4vw, 1.55rem);
    overflow-wrap: anywhere;
}

.ld-objective-detail__hero p {
    margin-bottom: 0;
    color: rgba(240, 247, 251, 0.78);
    font-size: 0.76rem;
}
.ld-objective-detail__hero .ld-objective-status {
    border-color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.1);
    color: #fff !important;
}

.ld-objective-detail__text {
    padding: 1.15rem;
    border: 1px solid var(--lcd-border);
    border-left: 4px solid var(--lcd-gold-500);
    border-radius: var(--lcd-radius-md);
    background: var(--lcd-surface-raised);
}

.ld-objective-detail__text h4,
.ld-objective-detail__section h4 {
    margin-bottom: 0.45rem;
    color: var(--lcd-ink);
    font-size: 0.94rem;
    font-weight: 750;
}
.ld-objective-detail__text p {
    margin-bottom: 0;
    color: var(--lcd-ink-soft);
    font-size: 0.88rem;
    line-height: 1.75;
    white-space: pre-line;
}

.ld-objective-detail__facts {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0;
    margin: 0;
    overflow: hidden;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-md);
}

.ld-objective-detail__facts > div {
    padding: 0.8rem;
    border-right: 1px solid var(--lcd-border);
    border-bottom: 1px solid var(--lcd-border);
}
.ld-objective-detail__facts > div:nth-child(3n) {
    border-right: 0;
}
.ld-objective-detail__facts > div:nth-last-child(-n + 3) {
    border-bottom: 0;
}
.ld-objective-detail__facts dt {
    margin-bottom: 0.2rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.ld-objective-detail__facts dd {
    margin: 0;
    color: var(--lcd-ink);
    font-size: 0.77rem;
    font-weight: 720;
    overflow-wrap: anywhere;
}

.ld-objective-detail__section {
    padding: 1rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-md);
    background: var(--lcd-surface-muted);
}

.ld-objective-detail__section-head {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 0.8rem;
}
.ld-objective-detail__section-head h4 {
    margin-bottom: 0;
}
.ld-objective-detail__section-icon {
    display: grid;
    flex: 0 0 38px;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: var(--lcd-brand-100);
    color: var(--lcd-brand-700);
    font-size: 1.05rem;
}

.ld-objective-detail__indicators {
    display: grid;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
    list-style: none;
}
.ld-objective-detail__indicators li {
    position: relative;
    padding: 0.68rem 0.75rem 0.68rem 1.85rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-sm);
    background: #fff;
    color: var(--lcd-ink-soft);
    font-size: 0.76rem;
    line-height: 1.55;
}
.ld-objective-detail__indicators li::before {
    position: absolute;
    top: 0.75rem;
    left: 0.7rem;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--lcd-success);
    box-shadow: 0 0 0 3px rgba(36, 115, 90, 0.1);
    content: "";
}

.ld-objective-detail__sources {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
}
.ld-objective-detail__sources article {
    padding: 0.85rem;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-sm);
    background: #fff;
}
.ld-objective-detail__source-role,
.ld-objective-detail__source-verification {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    margin: 0 0.3rem 0.45rem 0;
    padding: 0.24rem 0.44rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 800;
}
.ld-objective-detail__source-role {
    background: var(--lcd-brand-50);
    color: var(--lcd-brand-700);
}
.ld-objective-detail__source-verification--verified {
    border-color: rgba(36, 115, 90, 0.2);
    background: rgba(36, 115, 90, 0.08);
    color: var(--lcd-success);
}
.ld-objective-detail__source-verification--attention {
    border-color: rgba(165, 104, 25, 0.2);
    background: rgba(165, 104, 25, 0.08);
    color: var(--lcd-warning);
}
.ld-objective-detail__sources h5 {
    margin-bottom: 0.2rem;
    color: var(--lcd-ink);
    font-size: 0.79rem;
    font-weight: 760;
}
.ld-objective-detail__sources p {
    margin-bottom: 0.65rem;
    color: var(--lcd-muted);
    font-size: 0.7rem;
}
.ld-objective-detail__sources article > div {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.6rem;
    padding-top: 0.55rem;
    border-top: 1px solid var(--lcd-border);
}
.ld-objective-detail__sources article > div > span,
.ld-objective-detail__sources a {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.7rem;
    font-weight: 720;
}
.ld-objective-detail__sources article > div > span {
    color: var(--lcd-muted);
}
.ld-objective-detail__sources a {
    color: var(--lcd-brand-700);
}
.ld-objective-detail__sources code {
    max-width: 100%;
    overflow: hidden;
    padding: 0.25rem 0.4rem;
    border-radius: 5px;
    background: var(--lcd-surface-muted);
    color: var(--lcd-ink-soft);
    font-size: 0.7rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ld-objective-detail__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.9rem;
    border-top: 1px solid var(--lcd-border);
}
.ld-objective-detail__footer > span {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--lcd-muted);
    font-size: 0.72rem;
    line-height: 1.5;
}
.ld-objective-detail__footer > span i {
    color: var(--lcd-brand-600);
    font-size: 1rem;
}

:global(.ld-objective-export-modal) {
    --lcd-brand-950: #10233f;
    --lcd-brand-900: #163153;
    --lcd-brand-800: #1c416c;
    --lcd-brand-700: #245486;
    --lcd-brand-600: #2e699c;
    --lcd-brand-500: #3e7daf;
    --lcd-brand-100: #dceaf5;
    --lcd-brand-50: #f1f7fb;
    --lcd-gold-500: #b9893f;
    --lcd-success: #24735a;
    --lcd-warning: #a56819;
    --lcd-danger: #b43e4d;
    --lcd-info: #27718a;
    --lcd-ink: #17263d;
    --lcd-ink-soft: #33445b;
    --lcd-muted: #627187;
    --lcd-subtle: #8794a6;
    --lcd-surface-raised: #fff;
    --lcd-surface-muted: #f7f9fb;
    --lcd-border: #dbe3eb;
    --lcd-border-strong: #c7d2de;
    --lcd-focus: rgba(46, 105, 156, 0.32);
    --lcd-shadow-sm: 0 2px 8px rgba(20, 39, 65, 0.055);
    --lcd-shadow-lg: 0 24px 54px rgba(17, 35, 62, 0.14);
    --lcd-radius-sm: 8px;
    --lcd-radius-md: 12px;
    --lcd-radius-lg: 16px;
    --lcd-transition: 160ms ease;
    color: var(--lcd-ink-soft);
    color-scheme: light;
}

:global(.ld-objective-detail-modal .modal-content) {
    overflow: hidden;
    border: 0;
    border-radius: var(--lcd-radius-lg);
    box-shadow: var(--lcd-shadow-lg);
}
:global(.ld-objective-detail-modal .modal-header) {
    border-bottom-color: var(--lcd-border);
    background: var(--lcd-surface-muted);
}
:global(.ld-objective-detail-modal .modal-title) {
    color: var(--lcd-ink);
    font-size: 0.92rem;
    font-weight: 750;
}
:global(.ld-objective-export-modal .modal-dialog) {
    max-width: 780px;
}
:global(.ld-objective-export-modal .modal-content) {
    overflow: hidden;
    border: 0;
    border-radius: var(--lcd-radius-lg);
    box-shadow: var(--lcd-shadow-lg);
}
:global(.ld-objective-export-modal .modal-header) {
    min-height: 54px;
    padding: 0.8rem 1rem;
    border-bottom-color: var(--lcd-border);
    background: var(--lcd-surface-muted);
}
:global(.ld-objective-export-modal .modal-title) {
    color: var(--lcd-ink);
    font-size: 0.92rem;
    font-weight: 760;
}
:global(.ld-objective-export-modal .modal-body) {
    padding: 1rem;
    overscroll-behavior: contain;
    background: #fff;
}
:global(.ld-objective-export-modal .btn) {
    min-height: 40px;
    border-radius: var(--lcd-radius-sm);
    font-size: 0.78rem;
    font-weight: 720;
}
:global(.ld-objective-export-modal .btn-sm) {
    min-height: 36px;
}
:global(.ld-objective-export-modal .btn-primary) {
    border-color: var(--lcd-brand-700);
    background: var(--lcd-brand-700);
    box-shadow: 0 4px 12px rgba(36, 84, 134, 0.18);
}
:global(.ld-objective-export-modal .btn-primary:hover),
:global(.ld-objective-export-modal .btn-primary:focus-visible) {
    border-color: var(--lcd-brand-900);
    background: var(--lcd-brand-900);
}
:global(
        .ld-objective-export-modal
            :where(button, input, [tabindex]):focus-visible
    ) {
    outline: 3px solid var(--lcd-focus);
    outline-offset: 2px;
}

@media (max-width: 1199.98px) {
    .ld-objectives__filter-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .ld-objectives__kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 991.98px) {
    .ld-objectives__table-wrap {
        display: none;
    }
    .ld-objectives__cards {
        display: grid;
        gap: 0.7rem;
        padding: 0.8rem;
    }
    .ld-objective-card {
        display: grid;
        gap: 0.75rem;
        padding: 0.9rem;
        border: 1px solid var(--lcd-border);
        border-radius: var(--lcd-radius-md);
        background: #fff;
        box-shadow: var(--lcd-shadow-sm);
    }
    .ld-objective-card header,
    .ld-objective-card footer {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .ld-objective-card header > div {
        display: grid;
    }
    .ld-objective-card header > div > span {
        color: var(--lcd-muted);
        font-size: 0.7rem;
        font-weight: 760;
    }
    .ld-objective-card header strong {
        margin-top: 0.12rem;
        color: var(--lcd-brand-700);
        font-size: 0.82rem;
        overflow-wrap: anywhere;
    }
    .ld-objective-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.38rem;
    }
    .ld-objective-card__meta span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.45rem;
        border-radius: 999px;
        background: var(--lcd-surface-muted);
        color: var(--lcd-muted);
        font-size: 0.7rem;
    }
    .ld-objective-card p {
        margin-bottom: 0;
        color: var(--lcd-ink-soft);
        font-size: 0.76rem;
        line-height: 1.6;
    }
    .ld-objective-card footer {
        align-items: center;
        padding-top: 0.65rem;
        border-top: 1px solid var(--lcd-border);
    }
    .ld-objective-card footer > span {
        color: var(--lcd-muted);
        font-size: 0.7rem;
    }
}

@media (max-width: 767.98px) {
    .ld-objectives__header {
        display: grid;
    }
    .ld-objectives__header-actions {
        justify-items: start;
    }
    .ld-objectives__filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .ld-objective-detail__facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .ld-objective-detail__facts > div:nth-child(3n) {
        border-right: 1px solid var(--lcd-border);
    }
    .ld-objective-detail__facts > div:nth-child(2n) {
        border-right: 0;
    }
    .ld-objective-detail__facts > div:nth-last-child(-n + 3) {
        border-bottom: 1px solid var(--lcd-border);
    }
    .ld-objective-detail__facts > div:nth-last-child(-n + 2) {
        border-bottom: 0;
    }
    .ld-objective-detail__sources {
        grid-template-columns: 1fr;
    }
    .ld-objective-detail__footer {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-objective-export__footer {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-objective-export__footer > div {
        width: 100%;
    }
    .ld-objective-export__footer .btn {
        flex: 1 1 0;
    }
}

@media (max-width: 575.98px) {
    :global(.ld-objective-export-modal .modal-dialog) {
        width: auto;
        margin: 0.5rem;
    }
    :global(.ld-objective-export-modal .modal-content) {
        max-height: calc(100dvh - 1rem);
        border-radius: var(--lcd-radius-md);
    }
    :global(.ld-objective-export-modal .modal-header) {
        min-height: 50px;
        padding: 0.7rem 0.75rem;
    }
    :global(.ld-objective-export-modal .modal-body) {
        padding: 0.75rem;
    }
    .ld-objectives__identity {
        display: grid;
    }
    .ld-objectives__mark {
        width: 42px;
        height: 42px;
    }
    .ld-objectives__kpis,
    .ld-objectives__filter-grid {
        grid-template-columns: 1fr;
    }
    .ld-objectives__filter-heading {
        align-items: flex-start;
    }
    .ld-objectives__search-field .input-group {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr);
    }
    .ld-objectives__search-field .input-group-text {
        border-right: 0;
        border-radius: var(--lcd-radius-sm) 0 0 var(--lcd-radius-sm);
    }
    .ld-objectives__search-field .form-control {
        grid-column: 2;
        grid-row: 1;
        width: 100%;
        min-width: 0;
        border-right: 1px solid var(--lcd-border-strong);
        border-left: 0;
        border-radius: 0 var(--lcd-radius-sm) var(--lcd-radius-sm) 0 !important;
    }
    .ld-objectives__search-field .btn {
        grid-column: 1 / -1;
        width: 100%;
        margin-top: 0.45rem;
        border-radius: var(--lcd-radius-sm) !important;
    }
    .ld-objectives__pagination {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-objectives__pagination :deep(.pagination) {
        justify-content: center;
    }
    .ld-objective-card header,
    .ld-objective-card footer {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-objective-card footer .btn {
        width: 100%;
    }
    .ld-objective-detail__hero {
        flex-direction: column;
    }
    .ld-objective-detail__facts {
        grid-template-columns: 1fr;
    }
    .ld-objective-detail__facts > div {
        border-right: 0 !important;
        border-bottom: 1px solid var(--lcd-border) !important;
    }
    .ld-objective-detail__facts > div:last-child {
        border-bottom: 0 !important;
    }
    .ld-objective-detail__sources article > div {
        align-items: flex-start;
        flex-direction: column;
    }
    .ld-objective-export {
        gap: 0.75rem;
    }
    .ld-objective-export__hero {
        grid-template-columns: 44px minmax(0, 1fr);
        gap: 0.72rem;
        padding: 0.75rem;
    }
    .ld-objective-export__hero-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        font-size: 1.18rem;
    }
    .ld-objective-export__hero-total {
        grid-column: 1 / -1;
        grid-template-columns: auto 1fr auto;
        align-items: baseline;
        gap: 0.35rem;
        min-width: 0;
        padding: 0.65rem 0 0;
        border-top: 1px solid rgba(36, 84, 134, 0.16);
        border-left: 0;
        text-align: left;
    }
    .ld-objective-export__hero-total strong {
        justify-self: end;
        font-size: 1.25rem;
    }
    .ld-objective-export__context,
    .ld-objective-export__formats {
        grid-template-columns: 1fr;
    }
    .ld-objective-export__context > div {
        border-right: 0;
        border-bottom: 1px solid var(--lcd-border) !important;
    }
    .ld-objective-export__context > div:last-child {
        border-bottom: 0 !important;
    }
    .ld-objective-export__formats legend {
        display: grid;
        align-items: start;
        gap: 0.12rem;
    }
    .ld-objective-export__formats legend small {
        text-align: left;
    }
    .ld-objective-export-format {
        grid-template-columns: 42px minmax(0, 1fr) 22px;
        min-height: 92px;
        padding: 0.72rem;
    }
    .ld-objective-export-format__icon {
        width: 42px;
        height: 42px;
    }
    .ld-objective-export__scope-head {
        grid-template-columns: 36px minmax(0, 1fr);
    }
    .ld-objective-export__clear {
        grid-column: 1 / -1;
        justify-self: stretch;
        width: 100%;
        border: 1px solid var(--lcd-border);
        background: #fff;
    }
    .ld-objective-export__chips > span {
        align-items: flex-start;
        width: 100%;
    }
    .ld-objective-export__progress {
        grid-template-columns: 38px minmax(0, 1fr);
    }
    .ld-objective-export__progress-icon {
        width: 38px;
        height: 38px;
    }
    .ld-objective-export__footer {
        bottom: -0.75rem;
        margin: 0 -0.75rem -0.75rem;
        padding: 0.75rem;
    }
    .ld-objective-export__footer > div {
        align-items: stretch;
        flex-direction: column-reverse;
    }
    .ld-objective-export__footer .btn {
        width: 100%;
    }
}
</style>
