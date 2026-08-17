<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import Swal from "sweetalert2";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import QuickAttendanceGrid from "../workspace/QuickAttendanceGrid.vue";
import LessonRecordEditor from "../workspace/LessonRecordEditor.vue";
import CurriculumCoveragePanel from "../workspace/CurriculumCoveragePanel.vue";
import AssessmentPanel from "../workspace/AssessmentPanel.vue";
import OperationalRecordsPanel from "../workspace/OperationalRecordsPanel.vue";
import AttendanceClosuresPanel from "../workspace/AttendanceClosuresPanel.vue";
import AmendmentsPanel from "../workspace/AmendmentsPanel.vue";
import EarlyWithdrawalsPanel from "../workspace/EarlyWithdrawalsPanel.vue";
import ParvulariaLateArrivalsPanel from "../workspace/ParvulariaLateArrivalsPanel.vue";
import {
    bookLabel,
    confirmAction,
    errorMessage,
    formatDate,
    hasCapability,
    payloadData,
    payloadItems,
    showError,
    showSuccess,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    selectedBook: { type: Object, default: null },
    initialTab: { type: String, default: "sessions" },
    initialSessionId: { type: [Number, String], default: null },
    refreshToken: { type: Number, default: 0 },
});
const emit = defineEmits(["tab-change", "book-updated"]);

const loading = ref(false);
const loadingSessions = ref(false);
const error = ref(null);
const book = ref(null);
const roster = ref([]);
const sessions = ref([]);
const activeTab = ref(props.initialTab || "sessions");
const selectedSessionId = ref(
    props.initialSessionId ? Number(props.initialSessionId) : null
);
const showSessionForm = ref(false);
const savingSession = ref(false);
const openingBook = ref(false);
const showSignature = ref(false);
const preparingSignature = ref(false);
const signing = ref(false);
const signaturePreview = ref(null);
const otp = ref("");
const sessionForm = reactive({
    scheduled_date: "",
    start_time: "08:00",
    end_time: "08:45",
    school_day_block_id: null,
    teacher_staff_id: null,
    room_name: "",
    modality: "in_person",
    notes: "",
});
let controller = null;
let sessionsController = null;

const tabDefinitions = [
    {
        key: "sessions",
        label: "Sesiones",
        icon: "bx-calendar-event",
        capability: "can_view_sessions",
    },
    {
        key: "lesson",
        label: "Leccionario",
        icon: "bx-book-content",
        capability: "can_view_sessions",
    },
    {
        key: "coverage",
        label: "Cobertura",
        icon: "bx-target-lock",
        capability: "can_view_books",
    },
    {
        key: "attendance",
        label: "Asistencia",
        icon: "bx-user-check",
        capability: "can_view_sessions",
    },
    {
        key: "closures",
        label: "Cierres",
        icon: "bx-lock-alt",
        capability: "can_manage_closures",
        strict: true,
    },
    {
        key: "assessments",
        label: "Evaluaciones",
        icon: "bx-edit-alt",
        capability: "can_manage_assessments",
    },
    {
        key: "pie",
        label: "PIE",
        icon: "bx-support",
        capability: "can_view_pie",
    },
    {
        key: "coexistence",
        label: "Convivencia",
        icon: "bx-happy",
        capability: "can_view_coexistence",
    },
    {
        key: "absences",
        label: "Ausencias",
        icon: "bx-calendar-x",
        capability: "can_manage_absence",
    },
    {
        key: "withdrawals",
        label: "Retiros",
        icon: "bx-log-out-circle",
        capability: "can_view_withdrawals",
        strict: true,
    },
    {
        key: "amendments",
        label: "Enmiendas",
        icon: "bx-git-pull-request",
        capabilities: [
            "can_request_amendments",
            "can_review_amendments",
            "can_apply_amendments",
        ],
        strict: true,
    },
    {
        key: "parvularia",
        label: "Parvularia",
        icon: "bx-happy",
        capability: "can_manage_parvularia",
    },
    {
        key: "late-arrivals",
        label: "Atrasos párv.",
        icon: "bx-time-five",
        capability: "can_manage_parvularia",
        strict: true,
    },
];
const hasStrictCapability = (capability) =>
    Boolean(
        props.capabilities?.[capability] ||
            props.capabilities?.[capability?.replace(/^can_/, "")] ||
            props.capabilities?.["*"] ||
            props.capabilities?.is_super_admin
    );
const tabs = computed(() =>
    tabDefinitions.filter((tab) =>
        tab.capabilities
            ? tab.capabilities.some((capability) =>
                  tab.strict
                      ? hasStrictCapability(capability)
                      : hasCapability(props.capabilities, capability)
              )
            : tab.strict
            ? hasStrictCapability(tab.capability)
            : hasCapability(props.capabilities, tab.capability)
    )
);
const currentBookId = computed(
    () => props.context.book_id || props.selectedBook?.id || null
);
const selectedSession = computed(
    () =>
        sessions.value.find(
            (item) => Number(item.id) === Number(selectedSessionId.value)
        ) || null
);
const teachers = computed(
    () => props.catalogs.teachers || props.catalogs.staff || []
);
const blocks = computed(
    () => props.catalogs.school_day_blocks || props.catalogs.blocks || []
);
const bookIsOpen = computed(
    () => String(book.value?.status || "").toLowerCase() === "open"
);
const bookStatus = computed(() =>
    String(book.value?.status || "draft").toLowerCase()
);
const canManageBooks = computed(() =>
    hasCapability(props.capabilities, "can_manage_books")
);
const canManageSessions = computed(() =>
    hasCapability(props.capabilities, "can_manage_sessions")
);
const canPrepareBook = computed(
    () =>
        canManageBooks.value &&
        ["draft", "pending_preflight"].includes(bookStatus.value)
);
const bookProtectionLabel = computed(() => {
    if (bookIsOpen.value) return "Edición sujeta a permisos";
    if (bookStatus.value === "draft") return "Borrador: apertura pendiente";
    if (bookStatus.value === "pending_preflight")
        return "Preflight aprobado: falta abrir";
    return "Registros protegidos";
});
const sessionCreationMessage = computed(() => {
    if (["draft", "pending_preflight"].includes(bookStatus.value)) {
        return canPrepareBook.value
            ? "El libro todavía está protegido. Prepáralo y ábrelo para habilitar la primera sesión."
            : "El libro todavía está protegido. Un usuario con permiso de gestión de libros debe completar el preflight y abrirlo.";
    }
    if (bookIsOpen.value && !canManageSessions.value)
        return "El libro está abierto, pero tu perfil no tiene permiso para gestionar sesiones.";
    if (!bookIsOpen.value)
        return "El estado actual del libro protege la creación de nuevas sesiones.";
    return "Crea la primera sesión para comenzar el leccionario y la asistencia.";
});
const canManageAttendance = computed(() =>
    hasCapability(props.capabilities, "can_manage_attendance")
);
const canManageLesson = computed(() =>
    hasCapability(props.capabilities, "can_manage_lesson")
);
const canSign = computed(() => hasCapability(props.capabilities, "can_sign"));
const canManageAssessments = computed(() =>
    hasCapability(props.capabilities, "can_manage_assessments")
);
const canManageClosures = computed(() =>
    hasStrictCapability("can_manage_closures")
);
const canReconcileAttendance = computed(() =>
    hasStrictCapability("can_reconcile_attendance")
);
const canViewWithdrawals = computed(() =>
    hasStrictCapability("can_view_withdrawals")
);
const canManageWithdrawals = computed(() =>
    hasStrictCapability("can_manage_withdrawals")
);
const canRequestAmendments = computed(() =>
    hasStrictCapability("can_request_amendments")
);
const canReviewAmendments = computed(() =>
    hasStrictCapability("can_review_amendments")
);
const canApplyAmendments = computed(() =>
    hasStrictCapability("can_apply_amendments")
);
const operationalManageCapability = {
    pie: "can_manage_pie",
    coexistence: "can_manage_coexistence",
    absences: "can_manage_absence",
    parvularia: "can_manage_parvularia",
    "late-arrivals": "can_manage_parvularia",
};
const canManageOperational = computed(() =>
    activeTab.value === "late-arrivals"
        ? hasStrictCapability("can_manage_parvularia")
        : hasCapability(
              props.capabilities,
              operationalManageCapability[activeTab.value]
          )
);
const sessionSummary = computed(() => ({
    total: sessions.value.length,
    pendingAttendance: sessions.value.filter((item) =>
        ["pending", "in_progress", "incomplete"].includes(
            item.attendance_status
        )
    ).length,
    pendingSignatures: sessions.value.filter((item) =>
        ["ready_to_sign", "pending_signature"].includes(item.status)
    ).length,
    signed: sessions.value.filter((item) =>
        ["signed", "firmado"].includes(item.status)
    ).length,
}));
const activeTabDefinition = computed(
    () =>
        tabs.value.find((tab) => tab.key === activeTab.value) ||
        tabs.value[0] ||
        null
);

const loadSessions = async () => {
    if (!currentBookId.value) return;
    sessionsController?.abort();
    sessionsController = new AbortController();
    loadingSessions.value = true;
    try {
        sessions.value = payloadItems(
            await libroDigitalApi.sessions(
                currentBookId.value,
                { per_page: 250, sort: "-scheduled_date,start_time" },
                sessionsController.signal
            )
        );
        if (
            props.initialSessionId &&
            sessions.value.some(
                (item) => Number(item.id) === Number(props.initialSessionId)
            )
        )
            selectedSessionId.value = Number(props.initialSessionId);
        if (!selectedSession.value && sessions.value.length)
            selectedSessionId.value = sessions.value[0].id;
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loadingSessions.value = false;
    }
};
const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        book.value =
            props.selectedBook ||
            payloadData(
                await libroDigitalApi.book(
                    currentBookId.value,
                    undefined,
                    controller.signal
                )
            );
        const rosterPayload = await libroDigitalApi.roster(
            currentBookId.value,
            { date: new Date().toISOString().slice(0, 10), per_page: 250 },
            controller.signal
        );
        roster.value = payloadItems(rosterPayload);
        await loadSessions();
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loading.value = false;
    }
};

watch(
    [currentBookId, () => props.refreshToken],
    () => {
        if (currentBookId.value) load();
    },
    { immediate: true }
);
watch(
    () => props.initialTab,
    (tab) => {
        if (tab) activeTab.value = tab;
    }
);
watch(
    tabs,
    (availableTabs) => {
        if (!availableTabs.some((tab) => tab.key === activeTab.value))
            activeTab.value = availableTabs[0]?.key || "sessions";
    },
    { immediate: true }
);
watch(
    () => props.initialSessionId,
    (id) => {
        if (id) selectedSessionId.value = Number(id);
    }
);
onBeforeUnmount(() => {
    controller?.abort();
    sessionsController?.abort();
});

const setTab = (tab) => {
    if (!tabs.value.some((item) => item.key === tab)) return;
    activeTab.value = tab;
    emit("tab-change", tab);
};
const openSession = (session, tab = "sessions") => {
    selectedSessionId.value = session.id;
    setTab(tab);
};
const openCreateSession = () => {
    Object.assign(sessionForm, {
        scheduled_date: new Date().toISOString().slice(0, 10),
        start_time: "08:00",
        end_time: "08:45",
        school_day_block_id: null,
        teacher_staff_id:
            book.value?.teacher_staff_id || book.value?.teacher_id || null,
        room_name: "",
        modality: "in_person",
        notes: "",
    });
    showSessionForm.value = true;
};
const prepareAndOpenBook = async () => {
    if (!canPrepareBook.value || openingBook.value) return;
    const confirmation = await confirmAction({
        title:
            bookStatus.value === "draft"
                ? "Preparar y abrir libro"
                : "Abrir libro",
        text: "Se validarán el preflight, la nómina sellada y la asignación docente antes de habilitar las sesiones.",
        confirmText:
            bookStatus.value === "draft" ? "Validar y abrir" : "Abrir libro",
    });
    if (!confirmation.isConfirmed) return;
    openingBook.value = true;
    try {
        let current = book.value;
        if (bookStatus.value === "draft")
            current = payloadData(
                await libroDigitalApi.bookAction(current, "preflight")
            );
        if (String(current?.status || "").toLowerCase() === "pending_preflight")
            current = payloadData(
                await libroDigitalApi.bookAction(current, "open")
            );
        book.value = current;
        emit("book-updated", current);
        await load();
        await showSuccess(
            "Libro abierto",
            "Ya puedes crear sesiones de clase."
        );
    } catch (requestError) {
        await showError(requestError, "No se pudo abrir el libro");
        if (requestError.isConflict) await load();
    } finally {
        openingBook.value = false;
    }
};
const saveSession = async () => {
    savingSession.value = true;
    try {
        const response = await libroDigitalApi.createSession(
            currentBookId.value,
            {
                ...sessionForm,
                schedule_subject_id:
                    book.value?.schedule_subject_id || book.value?.subject_id,
            }
        );
        showSessionForm.value = false;
        await loadSessions();
        selectedSessionId.value =
            payloadData(response)?.id || selectedSessionId.value;
        await showSuccess("Sesión creada");
    } catch (requestError) {
        await showError(requestError, "No se pudo crear la sesión");
    } finally {
        savingSession.value = false;
    }
};
const cancelSession = async (session) => {
    const result = await Swal.fire({
        title: "Cancelar sesión",
        input: "textarea",
        inputLabel: "Motivo obligatorio",
        text: "La sesión se conservará como registro auditable.",
        showCancelButton: true,
        confirmButtonText: "Cancelar sesión",
        inputValidator: (value) =>
            !value?.trim() ? "Ingresa el motivo." : undefined,
    });
    if (!result.isConfirmed) return;
    try {
        await libroDigitalApi.cancelSession(session, {
            reason: result.value.trim(),
        });
        await loadSessions();
        await showSuccess("Sesión cancelada");
    } catch (requestError) {
        await showError(requestError);
        if (requestError.isConflict) await loadSessions();
    }
};
const prepareSignature = async (session) => {
    preparingSignature.value = true;
    try {
        const prepared = payloadData(
            await libroDigitalApi.prepareSignature(session)
        );
        signaturePreview.value = prepared;
        sessions.value = sessions.value.map((item) =>
            Number(item.id) === Number(prepared?.id || session.id)
                ? { ...item, ...prepared }
                : item
        );
        selectedSessionId.value = session.id;
        otp.value = "";
        showSignature.value = true;
    } catch (requestError) {
        await showError(requestError, "No es posible preparar la firma");
        if (requestError.isConflict) await loadSessions();
    } finally {
        preparingSignature.value = false;
    }
};
const sign = async () => {
    if (!otp.value.trim()) return;
    signing.value = true;
    const submittedOtp = otp.value.trim();
    otp.value = "";
    try {
        const signatureSession =
            signaturePreview.value || selectedSession.value;
        await libroDigitalApi.signSession(signatureSession, {
            otp: submittedOtp,
            timestamp: new Date().toISOString(),
            payload_hash:
                signaturePreview.value?.canonical_hash ||
                signaturePreview.value?.payload_hash ||
                signaturePreview.value?.hash,
        });
        showSignature.value = false;
        signaturePreview.value = null;
        await loadSessions();
        await showSuccess(
            "Sesión firmada",
            "La revisión firmada quedó bloqueada y auditada."
        );
    } catch (requestError) {
        await showError(requestError, "No fue posible firmar");
    } finally {
        signing.value = false;
        otp.value = "";
    }
};
</script>

<template>
    <section
        class="lcd-book-workspace"
        aria-labelledby="lcd-book-workspace-title"
        :aria-busy="loading || loadingSessions"
    >
        <LibroDigitalStatePanel
            v-if="!currentBookId"
            title="Selecciona un libro"
            message="Usa el selector de contexto o abre un libro desde Libros y cursos."
        />
        <LibroDigitalStatePanel
            v-else-if="loading && !book"
            state="loading"
            title="Abriendo libro"
            message="Cargando nómina, sesiones y permisos aplicables."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !book"
            state="error"
            title="No se pudo abrir el libro"
            :message="errorMessage(error)"
            @retry="load"
        />
        <template v-else-if="book">
            <header class="lcd-book-workspace__header lcd-surface">
                <div class="lcd-book-workspace__identity">
                    <div class="lcd-book-workspace__mark" aria-hidden="true">
                        <i class="bx bx-book-open"></i>
                    </div>
                    <div>
                        <span class="lcd-eyebrow">LIBRO ACTIVO</span>
                        <h2 id="lcd-book-workspace-title">
                            {{ bookLabel(book) }}
                        </h2>
                        <div class="lcd-book-workspace__meta">
                            <span
                                ><i class="bx bx-calendar"></i
                                >{{
                                    book.academic_year?.name ||
                                    book.academic_year?.year ||
                                    "Año vigente"
                                }}</span
                            ><span
                                ><i class="bx bx-group"></i
                                >{{ roster.length }} estudiantes</span
                            ><span
                                ><i class="bx bx-bookmark"></i
                                >{{
                                    book.regulatory_profile?.code ||
                                    book.normative_profile?.code ||
                                    "Perfil por resolver"
                                }}</span
                            >
                        </div>
                    </div>
                </div>
                <div class="lcd-book-workspace__status">
                    <LibroDigitalStatusBadge :status="book.status" /><span
                        ><i
                            class="bx"
                            :class="bookIsOpen ? 'bx-edit-alt' : 'bx-lock-alt'"
                        ></i
                        >{{ bookProtectionLabel }}</span
                    ><BButton
                        type="button"
                        size="sm"
                        variant="outline-secondary"
                        :disabled="loading || openingBook"
                        aria-label="Actualizar libro, nómina y sesiones"
                        @click="load"
                        ><i class="bx bx-reset" aria-hidden="true"></i
                        ><span>Actualizar</span></BButton
                    >
                </div>
            </header>
            <div class="lcd-workspace-navigation lcd-surface">
                <div class="lcd-workspace-navigation__context">
                    <span>Espacio de trabajo</span
                    ><strong>{{
                        activeTabDefinition?.label || "Sesiones"
                    }}</strong>
                </div>
                <nav
                    class="lcd-workspace-tabs"
                    role="tablist"
                    aria-label="Secciones del libro"
                >
                    <button
                        v-for="tab in tabs"
                        :id="`lcd-workspace-tab-${tab.key}`"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :class="{ active: activeTab === tab.key }"
                        :aria-selected="activeTab === tab.key"
                        :aria-controls="`lcd-workspace-panel-${tab.key}`"
                        :tabindex="activeTab === tab.key ? 0 : -1"
                        @click="setTab(tab.key)"
                    >
                        <i class="bx" :class="tab.icon" aria-hidden="true"></i
                        ><span>{{ tab.label }}</span>
                    </button>
                </nav>
            </div>

            <section
                v-if="activeTab === 'sessions'"
                id="lcd-workspace-panel-sessions"
                class="lcd-sessions"
                role="tabpanel"
                aria-labelledby="lcd-workspace-tab-sessions"
            >
                <header class="lcd-section-heading">
                    <div>
                        <span class="lcd-eyebrow">OPERACIÓN DIARIA</span>
                        <h3>Sesiones de clase</h3>
                        <p>
                            Creación, asistencia, leccionario, estados y firma
                            docente en una secuencia trazable.
                        </p>
                    </div>
                    <BButton
                        v-if="bookIsOpen && canManageSessions"
                        type="button"
                        size="sm"
                        variant="primary"
                        @click="openCreateSession"
                        ><i class="bx bx-plus" aria-hidden="true"></i> Nueva
                        sesión</BButton
                    ><BButton
                        v-else-if="canPrepareBook"
                        type="button"
                        size="sm"
                        variant="warning"
                        :disabled="openingBook"
                        @click="prepareAndOpenBook"
                        ><span
                            v-if="openingBook"
                            class="spinner-border spinner-border-sm"
                            aria-hidden="true"
                        ></span
                        ><i
                            v-else
                            class="bx bx-lock-open-alt"
                            aria-hidden="true"
                        ></i
                        >{{
                            bookStatus === "draft"
                                ? "Preparar y abrir libro"
                                : "Abrir libro"
                        }}</BButton
                    >
                </header>
                <div
                    class="lcd-session-summary"
                    aria-label="Resumen de sesiones"
                >
                    <div>
                        <i class="bx bx-calendar-event" aria-hidden="true"></i
                        ><span>Sesiones</span
                        ><strong>{{ sessionSummary.total }}</strong>
                    </div>
                    <div class="is-warning">
                        <i class="bx bx-user-x" aria-hidden="true"></i
                        ><span>Asistencia pendiente</span
                        ><strong>{{ sessionSummary.pendingAttendance }}</strong>
                    </div>
                    <div class="is-info">
                        <i class="bx bx-pen" aria-hidden="true"></i
                        ><span>Firmas pendientes</span
                        ><strong>{{ sessionSummary.pendingSignatures }}</strong>
                    </div>
                    <div class="is-success">
                        <i class="bx bx-check-shield" aria-hidden="true"></i
                        ><span>Firmadas</span
                        ><strong>{{ sessionSummary.signed }}</strong>
                    </div>
                </div>
                <LibroDigitalStatePanel
                    v-if="loadingSessions && !sessions.length"
                    state="loading"
                    compact
                    title="Cargando sesiones"
                    message="Consultando el año académico seleccionado."
                />
                <div
                    v-else-if="sessions.length"
                    class="table-responsive lcd-sessions__table"
                >
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">
                            Sesiones registradas en el libro activo
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Fecha / bloque</th>
                                <th scope="col">Docente y sala</th>
                                <th scope="col">Asistencia</th>
                                <th scope="col">Leccionario</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="session in sessions"
                                :key="session.id"
                                :class="{
                                    'table-active':
                                        selectedSessionId === session.id,
                                }"
                            >
                                <td>
                                    <strong>{{
                                        formatDate(
                                            session.scheduled_date ||
                                                session.date
                                        )
                                    }}</strong
                                    ><small>{{
                                        session.block?.label ||
                                        session.block_label ||
                                        `${
                                            session.start_time?.slice(0, 5) ||
                                            ""
                                        }–${
                                            session.end_time?.slice(0, 5) || ""
                                        }`
                                    }}</small>
                                </td>
                                <td>
                                    <strong>{{
                                        session.teacher?.name ||
                                        session.teacher_name ||
                                        "Sin docente"
                                    }}</strong
                                    ><small
                                        ><i
                                            class="bx bx-map"
                                            aria-hidden="true"
                                        ></i
                                        >{{
                                            session.room_name || "Sin sala"
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <LibroDigitalStatusBadge
                                        :status="
                                            session.attendance_status ||
                                            'pending'
                                        "
                                    />
                                </td>
                                <td>
                                    <LibroDigitalStatusBadge
                                        :status="
                                            session.lesson_record_status ||
                                            (session.lesson_record
                                                ? 'completed'
                                                : 'pending')
                                        "
                                    />
                                </td>
                                <td>
                                    <LibroDigitalStatusBadge
                                        :status="session.status"
                                    />
                                </td>
                                <td>
                                    <div class="lcd-row-actions">
                                        <BButton
                                            type="button"
                                            size="sm"
                                            variant="outline-primary"
                                            @click="
                                                openSession(
                                                    session,
                                                    'attendance'
                                                )
                                            "
                                            ><i
                                                class="bx bx-user-check"
                                                aria-hidden="true"
                                            ></i>
                                            Asistencia</BButton
                                        ><BButton
                                            type="button"
                                            size="sm"
                                            variant="outline-secondary"
                                            @click="
                                                openSession(session, 'lesson')
                                            "
                                            ><i
                                                class="bx bx-book-content"
                                                aria-hidden="true"
                                            ></i>
                                            Leccionario</BButton
                                        ><BButton
                                            v-if="
                                                canSign &&
                                                ![
                                                    'signed',
                                                    'closed',
                                                    'cancelled',
                                                ].includes(session.status)
                                            "
                                            type="button"
                                            size="sm"
                                            variant="outline-success"
                                            :disabled="preparingSignature"
                                            @click="prepareSignature(session)"
                                            ><i
                                                class="bx bx-pen"
                                                aria-hidden="true"
                                            ></i>
                                            Firmar</BButton
                                        ><BDropdown
                                            v-if="
                                                canManageSessions &&
                                                ![
                                                    'signed',
                                                    'closed',
                                                    'cancelled',
                                                ].includes(session.status)
                                            "
                                            size="sm"
                                            variant="outline-secondary"
                                            no-caret
                                            aria-label="Más acciones de la sesión"
                                            ><template #button-content
                                                ><i
                                                    class="bx bx-dots-horizontal-rounded"
                                                    aria-hidden="true"
                                                ></i
                                                ><span class="visually-hidden"
                                                    >Más acciones</span
                                                ></template
                                            ><BDropdownItem
                                                @click="cancelSession(session)"
                                                >Cancelar con
                                                motivo</BDropdownItem
                                            ></BDropdown
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <LibroDigitalStatePanel
                    v-else
                    :state="
                        bookIsOpen && canManageSessions ? 'empty' : 'warning'
                    "
                    compact
                    :title="
                        bookIsOpen && canManageSessions
                            ? 'Sin sesiones'
                            : 'Creación de sesiones protegida'
                    "
                    :message="sessionCreationMessage"
                    ><BButton
                        v-if="canPrepareBook"
                        type="button"
                        size="sm"
                        variant="primary"
                        :disabled="openingBook"
                        @click="prepareAndOpenBook"
                        ><span
                            v-if="openingBook"
                            class="spinner-border spinner-border-sm"
                            aria-hidden="true"
                        ></span
                        ><i
                            v-else
                            class="bx bx-lock-open-alt"
                            aria-hidden="true"
                        ></i
                        >{{
                            bookStatus === "draft"
                                ? "Validar y abrir libro"
                                : "Abrir libro"
                        }}</BButton
                    ></LibroDigitalStatePanel
                >
            </section>

            <section
                v-else
                :id="`lcd-workspace-panel-${activeTab}`"
                class="lcd-workspace-panel"
                role="tabpanel"
                :aria-labelledby="`lcd-workspace-tab-${activeTab}`"
            >
                <template
                    v-if="activeTab === 'attendance' || activeTab === 'lesson'"
                >
                    <div class="lcd-session-selector lcd-surface">
                        <div>
                            <i
                                class="bx bx-calendar-check"
                                aria-hidden="true"
                            ></i
                            ><label for="lcd-workspace-session"
                                >Sesión de trabajo</label
                            >
                        </div>
                        <BFormSelect
                            id="lcd-workspace-session"
                            v-model="selectedSessionId"
                            size="sm"
                            ><option :value="null">Seleccionar sesión</option>
                            <option
                                v-for="session in sessions"
                                :key="session.id"
                                :value="session.id"
                            >
                                {{
                                    formatDate(
                                        session.scheduled_date || session.date
                                    )
                                }}
                                ·
                                {{
                                    session.block?.label ||
                                    session.start_time?.slice(0, 5)
                                }}
                                · {{ session.status }}
                            </option></BFormSelect
                        >
                    </div>
                    <QuickAttendanceGrid
                        v-if="activeTab === 'attendance' && selectedSession"
                        :session="selectedSession"
                        :can-edit="canManageAttendance"
                        @completed="loadSessions"
                    />
                    <LessonRecordEditor
                        v-else-if="activeTab === 'lesson' && selectedSession"
                        :session="selectedSession"
                        :context="context"
                        :can-edit="canManageLesson"
                        @updated="loadSessions"
                    />
                    <LibroDigitalStatePanel
                        v-else
                        compact
                        title="Selecciona una sesión"
                        message="El registro se vincula a una sesión y a su snapshot de nómina."
                    />
                </template>
                <AssessmentPanel
                    v-else-if="activeTab === 'assessments'"
                    :book-id="currentBookId"
                    :context="context"
                    :roster="roster"
                    :can-manage="canManageAssessments"
                />
                <CurriculumCoveragePanel
                    v-else-if="activeTab === 'coverage'"
                    :book-id="currentBookId"
                    :context="context"
                />
                <AttendanceClosuresPanel
                    v-else-if="activeTab === 'closures'"
                    :book="book"
                    :roster="roster"
                    :can-manage="canManageClosures"
                    :can-reconcile="canReconcileAttendance"
                />
                <EarlyWithdrawalsPanel
                    v-else-if="activeTab === 'withdrawals'"
                    :book-id="currentBookId"
                    :roster="roster"
                    :can-view="canViewWithdrawals"
                    :can-manage="canManageWithdrawals"
                />
                <AmendmentsPanel
                    v-else-if="activeTab === 'amendments'"
                    :book-id="currentBookId"
                    :can-request="canRequestAmendments"
                    :can-review="canReviewAmendments"
                    :can-apply="canApplyAmendments"
                />
                <ParvulariaLateArrivalsPanel
                    v-else-if="activeTab === 'late-arrivals'"
                    :book-id="currentBookId"
                    :roster="roster"
                    :sessions="sessions"
                    :can-manage="canManageOperational"
                />
                <OperationalRecordsPanel
                    v-else-if="
                        [
                            'pie',
                            'coexistence',
                            'absences',
                            'parvularia',
                        ].includes(activeTab)
                    "
                    :type="activeTab"
                    :book-id="currentBookId"
                    :context="context"
                    :roster="roster"
                    :can-manage="canManageOperational"
                />
            </section>
        </template>

        <BModal
            v-model="showSessionForm"
            title="Nueva sesión de clase"
            size="lg"
            hide-footer
        >
            <form class="row g-3" @submit.prevent="saveSession">
                <div class="col-md-4">
                    <label class="form-label" for="lcd-session-date"
                        >Fecha</label
                    ><BFormInput
                        id="lcd-session-date"
                        v-model="sessionForm.scheduled_date"
                        type="date"
                        required
                    />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lcd-session-start"
                        >Inicio</label
                    ><BFormInput
                        id="lcd-session-start"
                        v-model="sessionForm.start_time"
                        type="time"
                        required
                    />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lcd-session-end"
                        >Término</label
                    ><BFormInput
                        id="lcd-session-end"
                        v-model="sessionForm.end_time"
                        type="time"
                        required
                    />
                </div>
                <div v-if="blocks.length" class="col-md-6">
                    <label class="form-label" for="lcd-session-block"
                        >Bloque</label
                    ><BFormSelect
                        id="lcd-session-block"
                        v-model="sessionForm.school_day_block_id"
                        ><option :value="null">Resolver por horario</option>
                        <option
                            v-for="block in blocks"
                            :key="block.id"
                            :value="block.id"
                        >
                            {{ block.label || block.name }}
                        </option></BFormSelect
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="lcd-session-teacher"
                        >Docente</label
                    ><BFormSelect
                        id="lcd-session-teacher"
                        v-model="sessionForm.teacher_staff_id"
                        required
                        ><option :value="null">Seleccionar</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.full_name || teacher.name }}
                        </option></BFormSelect
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="lcd-session-room">Sala</label
                    ><BFormInput
                        id="lcd-session-room"
                        v-model.trim="sessionForm.room_name"
                        maxlength="100"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="lcd-session-modality"
                        >Modalidad</label
                    ><BFormSelect
                        id="lcd-session-modality"
                        v-model="sessionForm.modality"
                        ><option value="in_person">Presencial</option>
                        <option value="remote">Remota</option>
                        <option value="hybrid">Híbrida</option>
                        <option value="field_activity">
                            Salida pedagógica
                        </option></BFormSelect
                    >
                </div>
                <div class="col-12">
                    <label class="form-label" for="lcd-session-notes"
                        >Notas administrativas</label
                    ><BFormTextarea
                        id="lcd-session-notes"
                        v-model.trim="sessionForm.notes"
                        rows="2"
                        maxlength="1000"
                    />
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <BButton
                        type="button"
                        variant="outline-secondary"
                        @click="showSessionForm = false"
                        >Cancelar</BButton
                    ><BButton
                        type="submit"
                        variant="primary"
                        :disabled="savingSession"
                        ><span
                            v-if="savingSession"
                            class="spinner-border spinner-border-sm"
                        ></span>
                        Crear sesión</BButton
                    >
                </div>
            </form>
        </BModal>

        <BModal
            v-model="showSignature"
            title="Firma docente de la sesión"
            size="lg"
            hide-footer
            no-close-on-backdrop
            @hidden="
                otp = '';
                signaturePreview = null;
            "
        >
            <div class="lcd-signature">
                <BAlert show variant="warning"
                    ><strong>Registro inmutable.</strong> Verifica el resumen
                    antes de ingresar el código. Guardar asistencia o
                    leccionario nunca firma automáticamente.</BAlert
                >
                <div class="lcd-signature__summary">
                    <div>
                        <span>Docente</span
                        ><strong>{{
                            signaturePreview?.teacher?.name ||
                            selectedSession?.teacher?.name ||
                            "—"
                        }}</strong>
                    </div>
                    <div>
                        <span>Curso</span
                        ><strong>{{
                            book?.course?.display_name ||
                            book?.course_name ||
                            "—"
                        }}</strong>
                    </div>
                    <div>
                        <span>Fecha / bloque</span
                        ><strong
                            >{{ formatDate(selectedSession?.scheduled_date) }} ·
                            {{
                                selectedSession?.block?.label ||
                                selectedSession?.start_time?.slice(0, 5)
                            }}</strong
                        >
                    </div>
                    <div>
                        <span>Asistencia</span
                        ><strong>{{
                            signaturePreview?.attendance_summary ||
                            signaturePreview?.totals_label ||
                            "Validada por backend"
                        }}</strong>
                    </div>
                    <div class="lcd-signature__wide">
                        <span>Resumen del leccionario</span
                        ><strong>{{
                            signaturePreview?.lesson_summary ||
                            signaturePreview?.lesson_record_summary ||
                            "—"
                        }}</strong>
                    </div>
                    <div class="lcd-signature__wide">
                        <span>Hash canónico</span
                        ><code
                            >{{
                                (
                                    signaturePreview?.canonical_hash ||
                                    signaturePreview?.payload_hash ||
                                    signaturePreview?.hash ||
                                    "Pendiente"
                                ).slice(0, 20)
                            }}…</code
                        >
                    </div>
                </div>
                <form @submit.prevent="sign">
                    <label class="form-label" for="lcd-signature-otp"
                        >Código de verificación (OTP)</label
                    ><BFormInput
                        id="lcd-signature-otp"
                        v-model.trim="otp"
                        type="password"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="12"
                        required
                    /><small
                        >El código no se mostrará ni conservará después de
                        enviarlo.</small
                    >
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <BButton
                            type="button"
                            variant="outline-secondary"
                            @click="showSignature = false"
                            >Cancelar</BButton
                        ><BButton
                            type="submit"
                            variant="success"
                            :disabled="signing || !otp"
                            ><span
                                v-if="signing"
                                class="spinner-border spinner-border-sm"
                            ></span
                            ><i v-else class="bx bx-pen"></i> Verificar y
                            firmar</BButton
                        >
                    </div>
                </form>
            </div>
        </BModal>
    </section>
</template>

<style scoped>
.lcd-book-workspace {
    display: grid;
    gap: 1rem;
    min-width: 0;
    color: var(--lcd-ink, #273244);
}
.lcd-book-workspace :deep(.btn-sm) {
    min-height: 36px;
}
.lcd-book-workspace :deep(.btn:not(.btn-sm)) {
    min-height: 40px;
}
.lcd-book-workspace :deep(.form-control),
.lcd-book-workspace :deep(.form-select) {
    min-height: 40px;
    font-size: 0.8rem;
}
.lcd-book-workspace :deep(.form-control-sm),
.lcd-book-workspace :deep(.form-select-sm) {
    min-height: 36px;
}
.lcd-surface {
    border: 1px solid var(--lcd-border, #dfe5ec);
    background: var(--lcd-surface, #fff);
    box-shadow: var(--lcd-shadow-sm, 0 2px 10px rgba(37, 47, 63, 0.05));
}
.lcd-book-workspace__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-radius: var(--lcd-radius-lg, 14px);
    background: linear-gradient(
        135deg,
        var(--lcd-surface, #fff) 62%,
        var(--lcd-brand-50, #f1f5fb)
    );
    overflow: hidden;
    position: relative;
}
.lcd-book-workspace__header::after {
    position: absolute;
    right: -44px;
    bottom: -72px;
    width: 180px;
    height: 180px;
    border: 28px solid
        color-mix(in srgb, var(--lcd-brand-500, #405189) 8%, transparent);
    border-radius: 50%;
    content: "";
    pointer-events: none;
}
.lcd-book-workspace__identity,
.lcd-book-workspace__status {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    position: relative;
    z-index: 1;
}
.lcd-book-workspace__mark {
    display: grid;
    place-items: center;
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    border-radius: var(--lcd-radius-md, 10px);
    background: linear-gradient(
        145deg,
        var(--lcd-brand-900, #2f3e70),
        var(--lcd-brand-600, #5268a3)
    );
    box-shadow: var(--lcd-shadow-md, 0 8px 20px rgba(47, 62, 112, 0.2));
    color: #fff;
    font-size: 1.35rem;
}
.lcd-eyebrow {
    display: block;
    color: var(--lcd-brand-700, #405189);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}
.lcd-book-workspace__header h2 {
    margin: 0.1rem 0 0.35rem;
    color: var(--lcd-ink, #273244);
    font-size: clamp(1.12rem, 2vw, 1.45rem);
    font-weight: 750;
    letter-spacing: -0.02em;
}
.lcd-book-workspace__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.42rem 0.8rem;
    color: var(--lcd-muted, #6e7a8b);
    font-size: 0.76rem;
}
.lcd-book-workspace__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.lcd-book-workspace__meta i {
    color: var(--lcd-brand-600, #5268a3);
    font-size: 0.8rem;
}
.lcd-book-workspace__status {
    align-items: flex-end;
    flex-direction: column;
}
.lcd-book-workspace__status > span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: var(--lcd-muted, #6e7a8b);
    font-size: 0.72rem;
    font-weight: 650;
}
.lcd-book-workspace__status .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    min-height: 36px;
    border-radius: var(--lcd-radius-sm, 7px);
    font-size: 0.74rem;
}
.lcd-workspace-navigation {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr);
    align-items: stretch;
    border-radius: var(--lcd-radius-lg, 12px);
    overflow: hidden;
}
.lcd-workspace-navigation__context {
    display: flex;
    justify-content: center;
    flex-direction: column;
    padding: 0.65rem 0.85rem;
    border-right: 1px solid var(--lcd-border, #dfe5ec);
    background: var(--lcd-surface-muted, #f6f8fb);
}
.lcd-workspace-navigation__context span,
.lcd-workspace-navigation__context strong {
    display: block;
}
.lcd-workspace-navigation__context span {
    color: var(--lcd-muted, #6e7a8b);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.lcd-workspace-navigation__context strong {
    margin-top: 0.12rem;
    color: var(--lcd-brand-800, #354574);
    font-size: 0.78rem;
}
.lcd-workspace-tabs {
    display: flex;
    gap: 0.2rem;
    overflow-x: auto;
    padding: 0.32rem;
    scrollbar-width: thin;
}
.lcd-workspace-tabs button {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    min-width: max-content;
    height: 42px;
    padding: 0 0.7rem;
    border: 0;
    border-radius: var(--lcd-radius-sm, 7px);
    background: transparent;
    color: var(--lcd-muted, #6e7a8b);
    font-size: 0.76rem;
    font-weight: 700;
    transition: var(--lcd-transition, all 0.16s ease);
}
.lcd-workspace-tabs button i {
    font-size: 0.95rem;
}
.lcd-workspace-tabs button:hover {
    background: var(--lcd-surface-muted, #f6f8fb);
    color: var(--lcd-brand-700, #405189);
}
.lcd-workspace-tabs button.active {
    background: var(--lcd-brand-100, #e8edf8);
    box-shadow: inset 0 0 0 1px
        color-mix(in srgb, var(--lcd-brand-500, #405189) 16%, transparent);
    color: var(--lcd-brand-800, #354574);
}
.lcd-workspace-tabs button:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(64, 81, 137, 0.25));
    outline-offset: 1px;
}
.lcd-workspace-panel {
    display: grid;
    gap: 0.9rem;
    min-width: 0;
    outline: none;
}
.lcd-sessions {
    display: grid;
    gap: 0.85rem;
    min-width: 0;
}
.lcd-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
}
.lcd-section-heading h3 {
    margin: 0.12rem 0;
    color: var(--lcd-ink, #273244);
    font-size: 1.02rem;
}
.lcd-section-heading p {
    margin: 0;
    color: var(--lcd-muted, #748093);
    font-size: 0.8rem;
}
.lcd-section-heading .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 38px;
    border-radius: var(--lcd-radius-sm, 7px);
    font-size: 0.75rem;
}
.lcd-session-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.55rem;
}
.lcd-session-summary > div {
    display: grid;
    grid-template-columns: 32px 1fr;
    grid-template-rows: auto auto;
    align-items: center;
    column-gap: 0.55rem;
    padding: 0.72rem 0.78rem;
    border: 1px solid var(--lcd-border, #dfe5ec);
    border-radius: var(--lcd-radius-md, 10px);
    background: var(--lcd-surface, #fff);
    box-shadow: var(--lcd-shadow-sm, 0 2px 8px rgba(37, 47, 63, 0.04));
}
.lcd-session-summary i {
    grid-row: 1/3;
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--lcd-brand-100, #e8edf8);
    color: var(--lcd-brand-700, #405189);
    font-size: 1rem;
}
.lcd-session-summary .is-warning i {
    background: color-mix(
        in srgb,
        var(--lcd-warning, #f7b84b) 16%,
        transparent
    );
    color: #a56608;
}
.lcd-session-summary .is-info i {
    background: color-mix(in srgb, var(--lcd-info, #299cdb) 14%, transparent);
    color: #1877a8;
}
.lcd-session-summary .is-success i {
    background: color-mix(
        in srgb,
        var(--lcd-success, #0ab39c) 14%,
        transparent
    );
    color: #087b6c;
}
.lcd-session-summary span,
.lcd-session-summary strong {
    display: block;
}
.lcd-session-summary span {
    color: var(--lcd-muted, #758194);
    font-size: 0.7rem;
}
.lcd-session-summary strong {
    color: var(--lcd-ink, #2d394b);
    font-size: 0.96rem;
    line-height: 1.15;
}
.lcd-sessions__table {
    max-height: 600px;
    border: 1px solid var(--lcd-border, #e0e6ed);
    border-radius: var(--lcd-radius-lg, 12px);
    background: var(--lcd-surface, #fff);
    box-shadow: var(--lcd-shadow-sm, 0 2px 10px rgba(37, 47, 63, 0.04));
}
.lcd-sessions__table table {
    font-size: 0.78rem;
}
.lcd-sessions__table th {
    position: sticky;
    z-index: 1;
    top: 0;
    padding: 0.62rem 0.7rem;
    border-bottom: 1px solid var(--lcd-border-strong, #d5dce5);
    background: var(--lcd-surface-muted, #f5f7fa);
    color: var(--lcd-muted, #647184);
    font-size: 0.69rem;
    letter-spacing: 0.045em;
    text-transform: uppercase;
}
.lcd-sessions__table td {
    padding: 0.62rem 0.7rem;
    border-color: var(--lcd-border, #edf0f4);
}
.lcd-sessions__table tbody tr {
    transition: var(--lcd-transition, background 0.15s ease);
}
.lcd-sessions__table tbody tr.table-active {
    box-shadow: inset 3px 0 var(--lcd-brand-600, #405189);
    background: var(--lcd-brand-50, #f5f7fc);
}
.lcd-sessions__table td strong,
.lcd-sessions__table td small {
    display: block;
}
.lcd-sessions__table td small {
    margin-top: 0.14rem;
    color: var(--lcd-muted, #7d8998);
}
.lcd-sessions__table td small i {
    margin-right: 0.2rem;
}
.lcd-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.28rem;
    white-space: nowrap;
}
.lcd-row-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-height: 36px;
    border-radius: 6px;
    font-size: 0.72rem;
}
.lcd-session-selector {
    display: grid;
    grid-template-columns: 145px minmax(240px, 620px);
    align-items: center;
    gap: 0.7rem;
    padding: 0.65rem 0.75rem;
    border-radius: var(--lcd-radius-md, 10px);
}
.lcd-session-selector > div {
    display: flex;
    align-items: center;
    gap: 0.38rem;
}
.lcd-session-selector > div i {
    color: var(--lcd-brand-600, #405189);
    font-size: 1rem;
}
.lcd-session-selector label {
    color: var(--lcd-ink-soft, #4c596b);
    font-size: 0.74rem;
    font-weight: 750;
}
.lcd-session-selector :deep(.form-select) {
    min-height: 38px;
    border-color: var(--lcd-border, #dfe5ec);
    font-size: 0.78rem;
}
.form-label {
    color: var(--lcd-ink-soft, #4c596b);
    font-size: 0.74rem;
    font-weight: 700;
}
.lcd-signature {
    display: grid;
    gap: 0.85rem;
}
.lcd-signature__summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    overflow: hidden;
    border: 1px solid var(--lcd-border, #dfe5ec);
    border-radius: var(--lcd-radius-md, 10px);
    background: var(--lcd-surface, #fff);
}
.lcd-signature__summary div {
    padding: 0.7rem 0.8rem;
    border-right: 1px solid var(--lcd-border, #e5e9ef);
    border-bottom: 1px solid var(--lcd-border, #e5e9ef);
}
.lcd-signature__summary div:nth-child(even),
.lcd-signature__summary .lcd-signature__wide {
    border-right: 0;
}
.lcd-signature__summary .lcd-signature__wide {
    grid-column: 1/-1;
}
.lcd-signature__summary span,
.lcd-signature__summary strong {
    display: block;
}
.lcd-signature__summary span {
    color: var(--lcd-muted, #758194);
    font-size: 0.7rem;
}
.lcd-signature__summary strong,
.lcd-signature__summary code {
    margin-top: 0.18rem;
    color: var(--lcd-ink, #2d394b);
    font-size: 0.78rem;
}
.lcd-signature form > small {
    display: block;
    margin-top: 0.28rem;
    color: var(--lcd-muted, #778496);
    font-size: 0.72rem;
}
@media (max-width: 1100px) {
    .lcd-session-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    .lcd-book-workspace__header {
        align-items: flex-start;
    }
    .lcd-book-workspace__status {
        min-width: 150px;
    }
}
@media (max-width: 760px) {
    .lcd-book-workspace__header {
        align-items: stretch;
        flex-direction: column;
    }
    .lcd-book-workspace__status {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .lcd-book-workspace__status .btn span {
        display: none;
    }
    .lcd-workspace-navigation {
        grid-template-columns: 1fr;
    }
    .lcd-workspace-navigation__context {
        display: none;
    }
    .lcd-section-heading {
        align-items: flex-start;
        flex-direction: column;
    }
    .lcd-session-selector {
        grid-template-columns: 1fr;
    }
    .lcd-signature__summary {
        grid-template-columns: 1fr;
    }
    .lcd-signature__summary div,
    .lcd-signature__summary div:nth-child(even) {
        grid-column: auto;
        border-right: 0;
    }
}
@media (max-width: 520px) {
    .lcd-book-workspace__mark {
        display: none;
    }
    .lcd-book-workspace__header {
        padding: 0.85rem;
    }
    .lcd-book-workspace__meta {
        display: grid;
        gap: 0.25rem;
    }
    .lcd-session-summary {
        grid-template-columns: 1fr;
    }
    .lcd-workspace-tabs button {
        height: 40px;
        padding: 0 0.58rem;
    }
    .lcd-row-actions .btn i + span {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .lcd-workspace-tabs button,
    .lcd-sessions__table tbody tr {
        transition: none;
    }
}
</style>
