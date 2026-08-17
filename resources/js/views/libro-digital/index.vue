<script setup>
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import Layout from "../../layouts/main.vue";
import ContextualHelpButton from "../../components/libro-digital/ContextualHelpButton.vue";
import LibroDigitalContextBar from "../../components/libro-digital/LibroDigitalContextBar.vue";
import LibroDigitalStatePanel from "../../components/libro-digital/LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../../components/libro-digital/LibroDigitalStatusBadge.vue";
import {
    bookLabel,
    errorMessage,
    hasCapability,
    showError,
} from "../../components/libro-digital/module-utils";
import { useLibroDigitalContext } from "../../composables/useLibroDigitalContext";
import "../../components/libro-digital/libro-digital-theme.css";

const MyDaySection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/MyDaySection.vue")
);
const BooksSection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/BooksSection.vue")
);
const SubjectsSection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/SubjectsSection.vue")
);
const CurriculumObjectivesSection = defineAsyncComponent(() =>
    import(
        "../../components/libro-digital/sections/CurriculumObjectivesSection.vue"
    )
);
const BookWorkspaceSection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/BookWorkspaceSection.vue")
);
const StatisticsSection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/StatisticsSection.vue")
);
const ReportsSection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/ReportsSection.vue")
);
const ComplianceSection = defineAsyncComponent(() =>
    import("../../components/libro-digital/sections/ComplianceSection.vue")
);

const route = useRoute();
const router = useRouter();
const refreshToken = ref(0);
const online = ref(typeof navigator === "undefined" ? true : navigator.onLine);
const applyingContext = ref(false);

const {
    context,
    catalogs,
    books,
    capabilities,
    loading,
    loadingBooks,
    error,
    selectedBook,
    selectedYear,
    selectedCourse,
    selectedSchool,
    loadCatalogs,
    loadBooks,
    applyContext,
    selectBook,
} = useLibroDigitalContext(route, router);

const sections = [
    {
        key: "journey",
        label: "Mi jornada",
        shortLabel: "Jornada",
        description: "Agenda y pendientes",
        icon: "bx-sun",
        path: "/libro-digital",
        capability: "can_view_overview",
    },
    {
        key: "books",
        label: "Libros y cursos",
        shortLabel: "Libros",
        description: "Estructura académica",
        icon: "bx-book-bookmark",
        path: "/libro-digital/books",
        capability: "can_view_books",
    },
    {
        key: "subjects",
        label: "Asignaturas",
        shortLabel: "Asignaturas",
        description: "Catálogo curricular",
        icon: "bx-grid-alt",
        path: "/libro-digital/subjects",
        capability: "can_view_subjects",
    },
    {
        key: "curriculum",
        label: "Objetivos",
        shortLabel: "Objetivos",
        description: "Currículum oficial",
        icon: "bx-bullseye",
        path: "/libro-digital/objectives",
        capability: "can_view_curriculum",
    },
    {
        key: "workspace",
        label: "Trabajo docente",
        shortLabel: "Trabajo",
        description: "Registro pedagógico",
        icon: "bx-chalkboard",
        path: "/libro-digital/sessions",
        capability: "can_view_sessions",
    },
    {
        key: "statistics",
        label: "Estadísticas",
        shortLabel: "Estadísticas",
        description: "Indicadores y tendencias",
        icon: "bx-bar-chart-alt-2",
        path: "/libro-digital/statistics",
        capability: "can_view_statistics",
    },
    {
        key: "reports",
        label: "Reportes",
        shortLabel: "Reportes",
        description: "Informes trazables",
        icon: "bx-file",
        path: "/libro-digital/reports",
        capability: "can_view_reports",
    },
    {
        key: "compliance",
        label: "Cumplimiento",
        shortLabel: "Cumplimiento",
        description: "EDE y auditoría",
        icon: "bx-shield-quarter",
        path: "/libro-digital/ede",
        capability: "can_view_compliance",
    },
];

const sectionMeta = {
    journey: {
        title: "Mi jornada",
        explanation:
            "Reúne las sesiones del día, asistencia pendiente, firmas y alertas operativas del docente.",
        responsible:
            "Docente asignado a cada sesión; coordinación y dirección supervisan según permisos.",
        closingRule:
            "La jornada no cierra registros por sí sola: cada asistencia, sesión y firma conserva su propio estado.",
    },
    books: {
        title: "Libros y cursos",
        explanation:
            "Crea y administra el contenedor anual por establecimiento, curso y asignatura, junto con su perfil normativo.",
        responsible:
            "Administración académica o dirección con permiso de gestión de libros.",
        closingRule:
            "Cerrar o reabrir requiere validación, motivo y auditoría. Nunca elimina el historial asociado.",
    },
    subjects: {
        title: "Asignaturas",
        explanation:
            "Mantiene el catálogo de asignaturas, códigos, áreas y colores que utilizan libros y horarios.",
        responsible: "Administración académica autorizada.",
        closingRule:
            "Una asignatura usada se desactiva; no se elimina ni rompe asociaciones históricas.",
    },
    curriculum: {
        title: "Objetivos curriculares",
        explanation:
            "Explora objetivos oficiales desde NT1 hasta 4.º medio, con filtros pedagógicos, estado y trazabilidad de sus fuentes.",
        responsible:
            "Docentes, UTP y administración académica con acceso a libros o gestión del leccionario.",
        closingRule:
            "La consulta no altera el catálogo. Solo los objetivos disponibles pueden incorporarse al leccionario.",
    },
    workspace: {
        title: "Trabajo docente",
        explanation:
            "Centraliza sesiones, leccionario, asistencia, evaluaciones, PIE, convivencia, ausencias y el libro técnico-pedagógico parvulario.",
        responsible:
            "Docente responsable y equipos especializados, limitados por rol, curso y confidencialidad.",
        closingRule:
            "Guardar no firma. La firma exige revisión, prevalidación y verificación explícita; una sesión firmada queda inmutable.",
    },
    statistics: {
        title: "Estadísticas",
        explanation:
            "Presenta indicadores operativos de asistencia, cobertura, firmas, evaluaciones y alertas con filtros académicos.",
        responsible:
            "Docentes, UTP, dirección y sostenedor según alcance autorizado.",
        closingRule:
            "Los indicadores son derivados y no alteran el registro fuente ni reemplazan informes oficiales.",
    },
    reports: {
        title: "Reportes e informes",
        explanation:
            "Genera informes privados y trazables en PDF, CSV o JSON con filtros, identificador y huella de integridad cuando corresponda.",
        responsible:
            "Usuario autorizado para el alcance académico seleccionado.",
        closingRule:
            "El PDF oficial se genera en servidor; una descarga no cambia estados ni constituye envío EDE.",
    },
    compliance: {
        title: "Cumplimiento y auditoría",
        explanation:
            "Administra versiones EDE, mapeos, prevalidaciones, paquetes interoperables, auditoría y activación gradual del módulo.",
        responsible:
            "Dirección, administración del sistema y responsables de cumplimiento con segregación de funciones.",
        closingRule:
            "Un paquete válido acredita consistencia técnica local; no presume certificación ni recepción por una autoridad externa.",
    },
};

const allowedSections = new Set(sections.map((item) => item.key));
const queryValue = (value) => (Array.isArray(value) ? value[0] : value);

const pathSection = computed(() => {
    const path = route.path;
    if (/^\/libro-digital\/books\/[^/]+/.test(path)) return "workspace";
    if (path.startsWith("/libro-digital/objectives")) return "curriculum";
    if (path.startsWith("/libro-digital/subjects")) return "subjects";
    if (
        [
            "/sessions",
            "/lesson-record",
            "/curriculum-coverage",
            "/attendance",
            "/assessments",
            "/pie",
            "/coexistence",
            "/absence-cases",
            "/parvularia",
            "/workspace",
            "/control",
        ].some((suffix) => path.includes(suffix))
    )
        return "workspace";
    if (path.startsWith("/libro-digital/statistics")) return "statistics";
    if (path.startsWith("/libro-digital/reports")) return "reports";
    if (
        ["/ede", "/audit", "/configuration"].some((suffix) =>
            path.startsWith(`/libro-digital${suffix}`)
        )
    )
        return "compliance";
    if (path.startsWith("/libro-digital/books")) return "books";
    return "journey";
});

const activeSection = computed(() => {
    const requested = queryValue(route.query.section);
    return allowedSections.has(requested) ? requested : pathSection.value;
});
const activeMeta = computed(
    () => sectionMeta[activeSection.value] || sectionMeta.journey
);
const visibleSections = computed(() =>
    sections.filter((item) =>
        hasCapability(capabilities.value, item.capability)
    )
);
const workspaceTab = computed(() => {
    const requested = queryValue(route.query.tab);
    if (requested) return requested;
    const mapping = {
        "/lesson-record": "lesson",
        "/curriculum-coverage": "coverage",
        "/attendance": "attendance",
        "/control": "closures",
        "/assessments": "assessments",
        "/pie": "pie",
        "/coexistence": "coexistence",
        "/absence-cases": "absences",
        "/parvularia": "parvularia",
    };
    return (
        Object.entries(mapping).find(([suffix]) =>
            route.path.includes(suffix)
        )?.[1] || "sessions"
    );
});
const complianceTab = computed(() => {
    const requested = queryValue(route.query.subsection);
    if (["ede", "audit", "configuration"].includes(requested)) return requested;
    if (route.path.includes("/audit")) return "audit";
    if (route.path.includes("/configuration")) return "configuration";
    return "ede";
});
const initialSessionId = computed(() => queryValue(route.query.session_id));
const contextLabel = computed(
    () =>
        [
            selectedSchool.value?.name,
            selectedYear.value?.name || selectedYear.value?.year,
            selectedCourse.value?.display_name || selectedCourse.value?.name,
        ]
            .filter(Boolean)
            .join(" · ") || "Contexto académico pendiente"
);

const navigationContextKeys = new Set([
    "school_id",
    "academic_year_id",
    "education_level_id",
    "course_section_id",
    "book_id",
    "schedule_subject_id",
]);
const routeContextValue = (field) => {
    const queryValueForField = queryValue(route.query[field]);
    if (field !== "book_id") return queryValueForField ?? null;
    return queryValueForField || queryValue(route.params.id) || null;
};
const routeContextSignature = computed(() =>
    [...navigationContextKeys]
        .map((field) => `${field}:${routeContextValue(field) ?? ""}`)
        .join("|")
);
const navigate = async (section, options = {}) => {
    const definition =
        sections.find((item) => item.key === section) || sections[0];
    const baseQuery =
        section === activeSection.value
            ? { ...route.query }
            : Object.fromEntries(
                  Object.entries(route.query).filter(([key]) =>
                      navigationContextKeys.has(key)
                  )
              );
    const query = { ...baseQuery, ...options.query };
    delete query.section;
    await router.push({ path: options.path || definition.path, query });
};

const navigateWorkspaceTab = async (tab) => {
    const selectedBookPath = context.book_id
        ? `/libro-digital/books/${context.book_id}`
        : "/libro-digital/workspace";
    const paths = {
        sessions: "/libro-digital/sessions",
        lesson: "/libro-digital/lesson-record",
        coverage: "/libro-digital/curriculum-coverage",
        attendance: "/libro-digital/attendance",
        closures: "/libro-digital/control",
        assessments: "/libro-digital/assessments",
        pie: "/libro-digital/pie",
        coexistence: "/libro-digital/coexistence",
        absences: "/libro-digital/absence-cases",
        withdrawals: selectedBookPath,
        amendments: selectedBookPath,
        parvularia: "/libro-digital/parvularia",
        "late-arrivals": "/libro-digital/parvularia",
    };
    await navigate("workspace", {
        path: paths[tab] || paths.sessions,
        query: { tab },
    });
};

const navigateCompliance = async (subsection) => {
    await navigate("compliance", {
        path: `/libro-digital/${
            subsection === "configuration" ? "configuration" : subsection
        }`,
        query: { subsection },
    });
};

const handleContext = async (next) => {
    applyingContext.value = true;
    try {
        await applyContext(next);
        refreshToken.value += 1;
    } catch (requestError) {
        await showError(requestError, "No se pudo aplicar el contexto");
    } finally {
        applyingContext.value = false;
    }
};

const refresh = async () => {
    applyingContext.value = true;
    try {
        await loadCatalogs();
        refreshToken.value += 1;
    } catch (requestError) {
        await showError(requestError, "No se pudo actualizar el módulo");
    } finally {
        applyingContext.value = false;
    }
};

const openBook = async (book) => {
    try {
        await selectBook(book);
        refreshToken.value += 1;
        await navigate("workspace", {
            query: { book_id: book.id, tab: "sessions" },
        });
    } catch (requestError) {
        await showError(requestError, "No se pudo abrir el libro");
    }
};

const openWorkspace = async ({ bookId, sessionId, tab = "sessions" }) => {
    const book = books.value.find((item) => Number(item.id) === Number(bookId));
    if (book) await selectBook(book);
    else if (bookId) await applyContext({ book_id: bookId });
    await navigateWorkspaceTab(tab);
    await router.replace({
        query: {
            ...route.query,
            book_id: bookId || context.book_id,
            session_id: sessionId || undefined,
            tab,
        },
    });
};

const setOnline = () => {
    online.value = navigator.onLine;
};
onMounted(() => {
    window.addEventListener("online", setOnline);
    window.addEventListener("offline", setOnline);
    loadCatalogs();
});
onBeforeUnmount(() => {
    window.removeEventListener("online", setOnline);
    window.removeEventListener("offline", setOnline);
});

let routeContextInitialized = false;
watch(
    routeContextSignature,
    async () => {
        const firstSync = !routeContextInitialized;
        routeContextInitialized = true;
        const next = {};
        navigationContextKeys.forEach((field) => {
            const explicitInQuery = Object.prototype.hasOwnProperty.call(
                route.query,
                field
            );
            const explicitBookParam =
                field === "book_id" && queryValue(route.params.id);
            if (!firstSync || explicitInQuery || explicitBookParam) {
                next[field] = routeContextValue(field);
            }
        });
        const changed = Object.entries(next).some(
            ([field, value]) =>
                String(context[field] ?? "") !== String(value ?? "")
        );
        if (!changed) return;
        try {
            await applyContext(next);
        } catch (requestError) {
            if (requestError?.code !== "ERR_CANCELED") {
                await showError(
                    requestError,
                    "No se pudo sincronizar el contexto de navegación"
                );
            }
        }
    },
    { immediate: true }
);
</script>

<template>
    <Layout>
        <main class="lcd-shell">
            <a class="lcd-shell__skip-link" href="#lcd-main-content"
                >Saltar al contenido principal</a
            >

            <header class="lcd-hero" aria-labelledby="lcd-page-title">
                <span
                    class="lcd-hero__orb lcd-hero__orb--one"
                    aria-hidden="true"
                ></span>
                <span
                    class="lcd-hero__orb lcd-hero__orb--two"
                    aria-hidden="true"
                ></span>
                <div class="lcd-hero__identity">
                    <div class="lcd-hero__mark" aria-hidden="true">
                        <i class="bx bx-book-open"></i>
                    </div>
                    <div class="lcd-hero__copy">
                        <span class="lcd-hero__eyebrow"
                            ><i class="bx bxs-circle" aria-hidden="true"></i>
                            Gestión académica trazable</span
                        >
                        <h1 id="lcd-page-title">Libro Digital de Clases</h1>
                        <p>
                            Registro pedagógico institucional, seguro y
                            auditable.
                        </p>
                        <div
                            class="lcd-hero__context"
                            aria-label="Contexto académico seleccionado"
                        >
                            <i class="bx bx-map-pin" aria-hidden="true"></i>
                            <span>{{ contextLabel }}</span>
                        </div>
                    </div>
                </div>
                <div class="lcd-hero__operations">
                    <span class="lcd-hero__operations-label"
                        >Estado operativo</span
                    >
                    <div class="lcd-hero__actions">
                        <span
                            class="lcd-network"
                            :class="{ 'lcd-network--offline': !online }"
                            role="status"
                            aria-live="polite"
                        >
                            <span
                                class="lcd-network__pulse"
                                aria-hidden="true"
                            ></span>
                            <i
                                class="bx"
                                :class="online ? 'bx-wifi' : 'bx-wifi-off'"
                                aria-hidden="true"
                            ></i>
                            {{ online ? "En línea" : "Sin conexión" }}
                        </span>
                        <LibroDigitalStatusBadge
                            v-if="selectedBook"
                            :status="selectedBook.status"
                            :label="bookLabel(selectedBook)"
                        />
                        <ContextualHelpButton v-bind="activeMeta" />
                    </div>
                    <p>
                        Vista actual: <strong>{{ activeMeta.title }}</strong>
                    </p>
                </div>
            </header>

            <nav class="lcd-shell__nav" aria-label="Áreas del Libro Digital">
                <header class="lcd-shell__nav-heading">
                    <div>
                        <span class="lcd-eyebrow">Navegación principal</span>
                        <strong>{{ activeMeta.title }}</strong>
                    </div>
                    <span>{{ visibleSections.length }} áreas disponibles</span>
                </header>
                <div class="lcd-shell__nav-items">
                    <button
                        v-for="section in visibleSections"
                        :key="section.key"
                        type="button"
                        :class="{ active: activeSection === section.key }"
                        :aria-current="
                            activeSection === section.key ? 'page' : undefined
                        "
                        @click="navigate(section.key)"
                    >
                        <span class="lcd-shell__nav-icon" aria-hidden="true"
                            ><i class="bx" :class="section.icon"></i
                        ></span>
                        <span class="lcd-shell__nav-copy">
                            <strong>{{ section.label }}</strong>
                            <small>{{ section.description }}</small>
                        </span>
                        <i
                            class="bx bx-chevron-right lcd-shell__nav-arrow"
                            aria-hidden="true"
                        ></i>
                    </button>
                </div>
            </nav>

            <LibroDigitalContextBar
                :model-value="context"
                :catalogs="catalogs"
                :books="books"
                :loading="loading || loadingBooks || applyingContext"
                @apply="handleContext"
                @refresh="refresh"
            />

            <BAlert
                v-if="!online"
                show
                variant="warning"
                class="lcd-offline-alert"
                role="alert"
            >
                <span class="lcd-offline-alert__icon" aria-hidden="true"
                    ><i class="bx bx-wifi-off"></i
                ></span>
                <span
                    ><strong>Modo sin conexión.</strong> No se enviarán firmas
                    ni cierres. Los cambios de asistencia permanecen solo en
                    memoria hasta recuperar la conexión.</span
                >
            </BAlert>

            <LibroDigitalStatePanel
                id="lcd-main-content"
                v-if="loading && !Object.keys(catalogs).length"
                state="loading"
                title="Preparando el Libro Digital"
                message="Cargando establecimiento, año académico, permisos y configuración vigente."
            />
            <LibroDigitalStatePanel
                id="lcd-main-content"
                v-else-if="error && !Object.keys(catalogs).length"
                state="error"
                title="No se pudo iniciar el Libro Digital"
                :message="errorMessage(error)"
                @retry="loadCatalogs"
            />
            <section
                v-else
                id="lcd-main-content"
                class="lcd-shell__content"
                :aria-label="activeMeta.title"
                tabindex="-1"
            >
                <MyDaySection
                    v-if="activeSection === 'journey'"
                    :context="context"
                    :capabilities="capabilities"
                    :refresh-token="refreshToken"
                    @open-workspace="openWorkspace"
                />
                <BooksSection
                    v-else-if="activeSection === 'books'"
                    :context="context"
                    :catalogs="catalogs"
                    :capabilities="capabilities"
                    :refresh-token="refreshToken"
                    @select-book="openBook"
                    @catalog-changed="refresh"
                />
                <SubjectsSection
                    v-else-if="activeSection === 'subjects'"
                    :context="context"
                    :catalogs="catalogs"
                    :capabilities="capabilities"
                    :refresh-token="refreshToken"
                    @catalog-changed="refresh"
                />
                <CurriculumObjectivesSection
                    v-else-if="activeSection === 'curriculum'"
                    :context="context"
                    :catalogs="catalogs"
                    :capabilities="capabilities"
                    :refresh-token="refreshToken"
                />
                <BookWorkspaceSection
                    v-else-if="activeSection === 'workspace'"
                    :context="context"
                    :catalogs="catalogs"
                    :capabilities="capabilities"
                    :selected-book="selectedBook"
                    :initial-tab="workspaceTab"
                    :initial-session-id="initialSessionId"
                    :refresh-token="refreshToken"
                    @tab-change="navigateWorkspaceTab"
                    @book-updated="refresh"
                />
                <StatisticsSection
                    v-else-if="activeSection === 'statistics'"
                    :context="context"
                    :catalogs="catalogs"
                    :refresh-token="refreshToken"
                />
                <ReportsSection
                    v-else-if="activeSection === 'reports'"
                    :context="context"
                    :catalogs="catalogs"
                    :capabilities="capabilities"
                    :refresh-token="refreshToken"
                />
                <ComplianceSection
                    v-else
                    :context="context"
                    :catalogs="catalogs"
                    :capabilities="capabilities"
                    :subsection="complianceTab"
                    :refresh-token="refreshToken"
                    @subsection-change="navigateCompliance"
                    @configuration-updated="refresh"
                />
            </section>
        </main>
    </Layout>
</template>

<style scoped>
.lcd-shell {
    position: relative;
    display: grid;
    gap: 1rem;
    min-width: 0;
    padding: 0.25rem 0 2rem;
}

.lcd-shell__skip-link {
    position: fixed;
    top: 0.75rem;
    left: 50%;
    z-index: 1100;
    padding: 0.65rem 1rem;
    border-radius: var(--lcd-radius-sm);
    background: var(--lcd-brand-950);
    box-shadow: var(--lcd-shadow-lg);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 800;
    transform: translate(-50%, -160%);
    transition: transform var(--lcd-transition);
}

.lcd-shell__skip-link:focus {
    color: #fff;
    transform: translate(-50%, 0);
}

.lcd-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    min-height: 168px;
    overflow: hidden;
    padding: clamp(1.25rem, 2.5vw, 2rem);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: var(--lcd-radius-xl);
    background: linear-gradient(
            112deg,
            rgba(12, 32, 57, 0.98),
            rgba(24, 65, 104, 0.96) 62%,
            rgba(39, 91, 130, 0.94)
        ),
        var(--lcd-brand-950);
    box-shadow: var(--lcd-shadow-md);
    color: #fff;
}

.lcd-hero::after {
    position: absolute;
    right: 31%;
    bottom: 0;
    width: 1px;
    height: 72%;
    background: linear-gradient(transparent, rgba(255, 255, 255, 0.16));
    content: "";
}

.lcd-hero__orb {
    position: absolute;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    pointer-events: none;
}

.lcd-hero__orb--one {
    top: -116px;
    right: 8%;
    width: 290px;
    height: 290px;
    background: rgba(255, 255, 255, 0.025);
}

.lcd-hero__orb--two {
    right: -42px;
    bottom: -145px;
    width: 330px;
    height: 330px;
    background: rgba(184, 137, 63, 0.07);
}

.lcd-hero__identity,
.lcd-hero__operations {
    position: relative;
    z-index: 1;
}

.lcd-hero__identity {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.lcd-hero__mark {
    display: grid;
    flex: 0 0 64px;
    place-items: center;
    width: 64px;
    height: 64px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 18px;
    background: linear-gradient(
        145deg,
        rgba(255, 255, 255, 0.17),
        rgba(255, 255, 255, 0.07)
    );
    box-shadow: inset 0 1px rgba(255, 255, 255, 0.18),
        0 12px 28px rgba(5, 20, 38, 0.2);
    color: #fff;
    font-size: 1.8rem;
}

.lcd-hero__copy {
    min-width: 0;
}

.lcd-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    color: #d9e9f5;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.lcd-hero__eyebrow i {
    color: #d4aa64;
    font-size: 0.38rem;
}

.lcd-hero h1 {
    margin: 0.25rem 0 0;
    color: #fff;
    font-size: clamp(1.55rem, 2.5vw, 2.15rem);
    font-weight: 750;
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.lcd-hero__copy > p {
    margin: 0.35rem 0 0;
    color: rgba(235, 244, 250, 0.78);
    font-size: 0.82rem;
}

.lcd-hero__context {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    max-width: 100%;
    margin-top: 0.8rem;
    padding: 0.35rem 0.55rem;
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 999px;
    background: rgba(8, 26, 47, 0.24);
    color: rgba(242, 247, 251, 0.9);
    font-size: 0.72rem;
}

.lcd-hero__context span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lcd-hero__operations {
    display: grid;
    justify-items: end;
    min-width: min(100%, 330px);
}

.lcd-hero__operations-label {
    margin-bottom: 0.45rem;
    color: rgba(225, 238, 247, 0.7);
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.lcd-hero__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    max-width: 100%;
}

.lcd-hero__operations > p {
    margin: 0.65rem 0 0;
    color: rgba(225, 238, 247, 0.72);
    font-size: 0.72rem;
}

.lcd-hero__operations > p strong {
    color: #fff;
    font-weight: 700;
}

.lcd-network {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    min-height: 32px;
    padding: 0.3rem 0.55rem;
    border: 1px solid rgba(121, 220, 181, 0.26);
    border-radius: 999px;
    background: rgba(18, 104, 78, 0.22);
    color: #bcf0dc;
    font-size: 0.72rem;
    font-weight: 750;
    white-space: nowrap;
}

.lcd-network__pulse {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #6ed0ab;
    box-shadow: 0 0 0 4px rgba(110, 208, 171, 0.1);
}

.lcd-network--offline {
    border-color: rgba(244, 192, 104, 0.3);
    background: rgba(151, 91, 14, 0.24);
    color: #f5d59f;
}

.lcd-network--offline .lcd-network__pulse {
    background: #e8aa50;
    box-shadow: 0 0 0 4px rgba(232, 170, 80, 0.11);
}

.lcd-shell__nav {
    overflow: hidden;
    border: 1px solid var(--lcd-border);
    border-radius: var(--lcd-radius-lg);
    background: var(--lcd-surface-raised);
    box-shadow: var(--lcd-shadow-sm);
}

.lcd-shell__nav-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem 0.65rem;
    border-bottom: 1px solid var(--lcd-border);
}

.lcd-shell__nav-heading .lcd-eyebrow {
    margin-bottom: 0.08rem;
    font-size: 0.68rem;
}

.lcd-shell__nav-heading strong {
    color: var(--lcd-ink);
    font-size: 0.78rem;
}

.lcd-shell__nav-heading > span {
    color: var(--lcd-subtle);
    font-size: 0.72rem;
}

.lcd-shell__nav-items {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
}

.lcd-shell__nav-items button {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
    min-height: 68px;
    padding: 0.7rem 0.65rem;
    border: 0;
    border-right: 1px solid var(--lcd-border);
    background: transparent;
    color: var(--lcd-muted);
    text-align: left;
    transition: background-color var(--lcd-transition),
        color var(--lcd-transition), box-shadow var(--lcd-transition);
}

.lcd-shell__nav-items button:last-child {
    border-right: 0;
}

.lcd-shell__nav-items button::after {
    position: absolute;
    right: 10%;
    bottom: 0;
    left: 10%;
    height: 3px;
    border-radius: 3px 3px 0 0;
    background: transparent;
    content: "";
    transition: background-color var(--lcd-transition),
        left var(--lcd-transition), right var(--lcd-transition);
}

.lcd-shell__nav-items button:hover {
    background: var(--lcd-surface-muted);
    color: var(--lcd-brand-800);
}

.lcd-shell__nav-items button.active {
    background: linear-gradient(
        180deg,
        var(--lcd-brand-50),
        var(--lcd-surface-raised)
    );
    color: var(--lcd-brand-800);
}

.lcd-shell__nav-items button.active::after {
    right: 0;
    left: 0;
    background: linear-gradient(
        90deg,
        var(--lcd-brand-600),
        var(--lcd-gold-500)
    );
}

.lcd-shell__nav-icon {
    display: grid;
    flex: 0 0 34px;
    place-items: center;
    width: 34px;
    height: 34px;
    border: 1px solid var(--lcd-border);
    border-radius: 10px;
    background: var(--lcd-surface);
    color: var(--lcd-muted);
    font-size: 1rem;
    transition: border-color var(--lcd-transition),
        background-color var(--lcd-transition), color var(--lcd-transition);
}

.lcd-shell__nav-items button.active .lcd-shell__nav-icon {
    border-color: rgba(46, 105, 156, 0.35);
    background: var(--lcd-brand-700);
    color: #fff;
    box-shadow: 0 5px 12px rgba(36, 84, 134, 0.18);
}

.lcd-shell__nav-copy {
    display: grid;
    min-width: 0;
}

.lcd-shell__nav-copy strong {
    overflow: hidden;
    font-size: 0.76rem;
    font-weight: 750;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lcd-shell__nav-copy small {
    overflow: hidden;
    margin-top: 0.12rem;
    color: var(--lcd-subtle);
    font-size: 0.68rem;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lcd-shell__nav-arrow {
    display: none;
    margin-left: auto;
    font-size: 1rem;
}

.lcd-offline-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
    padding: 0.75rem 0.9rem;
    font-size: 0.78rem;
    line-height: 1.5;
}

.lcd-offline-alert__icon {
    display: grid;
    flex: 0 0 34px;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: rgba(165, 104, 25, 0.12);
    color: var(--lcd-warning);
    font-size: 1rem;
}

.lcd-shell__content {
    min-width: 0;
    padding-top: 0.2rem;
    animation: lcd-fade-in 180ms ease-out;
}

.lcd-shell__content:focus {
    outline: none;
}

@keyframes lcd-fade-in {
    from {
        opacity: 0.45;
        transform: translateY(3px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (max-width: 1399.98px) {
    .lcd-shell__nav-items {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .lcd-shell__nav-items button:nth-child(-n + 4) {
        border-bottom: 1px solid var(--lcd-border);
    }

    .lcd-shell__nav-items button:nth-child(4) {
        border-right: 0;
    }
}

@media (max-width: 991.98px) {
    .lcd-hero {
        align-items: flex-start;
        flex-direction: column;
        gap: 1.2rem;
    }

    .lcd-hero::after {
        display: none;
    }

    .lcd-hero__operations {
        justify-items: start;
        width: 100%;
    }

    .lcd-hero__actions {
        justify-content: flex-start;
        flex-wrap: wrap;
    }

    .lcd-shell__nav-items {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .lcd-shell__nav-items button,
    .lcd-shell__nav-items button:nth-child(4) {
        border-right: 1px solid var(--lcd-border);
        border-bottom: 1px solid var(--lcd-border);
    }

    .lcd-shell__nav-items button:nth-child(even) {
        border-right: 0;
    }

    .lcd-shell__nav-items button:last-child {
        border-bottom: 0;
    }

    .lcd-shell__nav-items button:nth-last-child(-n + 2) {
        border-bottom: 0;
    }
}

@media (max-width: 575.98px) {
    .lcd-shell {
        gap: 0.8rem;
    }

    .lcd-hero {
        min-height: 0;
        padding: 1rem;
        border-radius: var(--lcd-radius-lg);
    }

    .lcd-hero__identity {
        align-items: flex-start;
        width: 100%;
    }

    .lcd-hero__copy {
        flex: 1 1 auto;
    }

    .lcd-hero__mark {
        flex-basis: 48px;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        font-size: 1.35rem;
    }

    .lcd-hero h1 {
        font-size: 1.42rem;
    }

    .lcd-hero__copy > p {
        display: none;
    }

    .lcd-hero__context {
        display: flex;
        width: 100%;
        margin-top: 0.6rem;
    }

    .lcd-hero__operations > p,
    .lcd-hero__operations-label {
        display: none;
    }

    .lcd-shell__nav-heading {
        padding: 0.65rem 0.75rem;
    }

    .lcd-shell__nav-heading > span {
        display: none;
    }

    .lcd-shell__nav-items {
        display: flex;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scroll-snap-type: x proximity;
        scrollbar-width: thin;
    }

    .lcd-shell__nav-items button,
    .lcd-shell__nav-items button:nth-child(even),
    .lcd-shell__nav-items button:nth-last-child(-n + 2) {
        flex: 0 0 178px;
        min-height: 62px;
        border-right: 1px solid var(--lcd-border);
        border-bottom: 0;
        scroll-snap-align: start;
    }

    .lcd-shell__nav-items button:last-child {
        border-right: 0;
    }

    .lcd-shell__nav-copy small {
        display: none;
    }

    .lcd-shell__nav-arrow {
        display: inline-block;
    }

    .lcd-offline-alert {
        align-items: flex-start;
    }
}
</style>
