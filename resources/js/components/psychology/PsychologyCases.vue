<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import listPlugin from "@fullcalendar/list";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import PsychologyBadge from "./PsychologyBadge.vue";
import PsychologyModal from "./PsychologyModal.vue";
import { downloadPsychologyActivityPdf } from "./psychology-activity-pdf";
import { downloadPsychologyCasePdf } from "./psychology-case-pdf";
import { downloadPsychologyPlanPdf } from "./psychology-plan-pdf";
import {
    priorityLabels,
    psychologyStatusLabels,
    usePsychology,
} from "../../composables/usePsychology";
const props = defineProps({ catalogs: { type: Object, required: true } });
const api = usePsychology();
const router = useRouter();
const labelFor = (value, priority = false) =>
    (priority ? priorityLabels[value] : psychologyStatusLabels[value]) || value;
const catalogLabel = (type, value) =>
    (props.catalogs.catalogs?.[type] || []).find((item) => item.slug === value)
        ?.name || value;
const visibilityLabels = {
    private_psychology: "Privado de Psicología",
    psychology_team: "Equipo de Psicología",
    interdisciplinary_team: "Equipo interdisciplinario",
    referral_feedback: "Retroalimentación de derivación",
};
const consentActionLabels = {
    intervention: "Intervención psicológica",
    external_referral: "Derivación externa",
    information_sharing: "Entrega de información",
    evaluation: "Evaluación profesional",
};
const guardianInformationLabels = {
    pending: "Pendiente de informar",
    informed: "Apoderado informado",
    not_required: "No requerido",
    unable_to_contact: "No fue posible contactar",
    institutional_exception: "Excepción institucional",
};
const modalityLabels = {
    presencial: "Presencial",
    remota: "Remota",
    telefonica: "Telefónica",
};
const attendanceLabels = {
    realizada: "Realizada",
    completed: "Realizada",
    scheduled: "Programada",
    absent: "Inasistencia",
    cancelled: "Cancelada",
    rescheduled: "Reprogramada",
};
const confidentialityLabel = (value) =>
    visibilityLabels[value] || value || "Sin definir";
const guardianInformationLabel = (value) =>
    guardianInformationLabels[value] || value || "Sin definir";
const modalityLabel = (value) =>
    modalityLabels[value] || value || "No informada";
const attendanceLabel = (value) =>
    attendanceLabels[value] || value || "No informada";
const selectedPlanVersion = computed(
    () => selectedPlan.value?.versions?.[0] || null
);
const formatDate = (value) => {
    if (!value) return "Sin fecha";
    const source = String(value);
    const date = new Date(source.length === 10 ? `${source}T12:00:00` : source);
    return new Intl.DateTimeFormat("es-CL", { dateStyle: "medium" }).format(
        date
    );
};
const formatDateTime = (value) => {
    if (!value) return "Sin fecha";
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
};
const list = ref({ data: [], meta: {} });
const selected = ref(null);
const selectedActivity = ref(null);
const selectedPlan = ref(null);
const exportingActivityPdf = ref(false);
const exportingCasePdf = ref(false);
const exportingPlanPdf = ref(false);
const activeTab = ref("summary");
const formModal = ref("");
const showCreateCase = ref(false);
const caseOriginMode = ref("direct");
const loadingEligibleReferrals = ref(false);
const eligibleReferrals = ref([]);
const selectedReferralId = ref(null);
const directStudentSearch = ref("");
const directStudentResults = ref([]);
const selectedDirectStudent = ref(null);
const searchingDirectStudents = ref(false);
let timer;
let studentSearchTimer;
const padDeviceValue = (value) => String(value).padStart(2, "0");
const deviceDate = (date = new Date()) =>
    `${date.getFullYear()}-${padDeviceValue(
        date.getMonth() + 1
    )}-${padDeviceValue(date.getDate())}`;
const deviceTime = (date = new Date()) =>
    `${padDeviceValue(date.getHours())}:${padDeviceValue(date.getMinutes())}`;
const filters = reactive({
    search: "",
    status: "",
    priority: "",
    page: 1,
    per_page: 20,
});
const followupRows = ref({ data: [], current_page: 1, last_page: 1, total: 0 });
const followupLoading = ref(false);
const followupCalendarKey = ref(0);
const followupFilters = reactive({
    search: "",
    status: "",
    page: 1,
    per_page: 25,
});
let followupSearchTimer;
const blankCoordination = () => ({
    recipient_user_id: null,
    coordination_type: "meeting",
    subject: "",
    request_message: "",
    requested_for: "",
});
const blankActivity = () => {
    const now = new Date();

    return {
        id: null,
        record_updated_at: "",
        change_reason: "",
        type: "student_interview",
        interview_number: null,
        activity_on: deviceDate(now),
        starts_at: deviceTime(now),
        ends_at: "",
        modality: "presencial",
        location: "",
        participants: "",
        participant_types: ["student"],
        interviewee_type: "student",
        interviewee_name: "",
        interviewee_rut: "",
        interviewer_position_snapshot: "",
        objective: "",
        institutional_summary: "",
        private_note: "",
        general_background: "",
        result: "",
        agreements: "",
        next_steps: "",
        next_action_on: "",
        follow_up_type: "",
        attendance_status: "realizada",
        visibility: "psychology_team",
        referral_feedback: "",
        status: "draft",
        coordination_enabled: false,
        coordination: blankCoordination(),
    };
};
const activity = reactive(blankActivity());
const coordination = reactive(blankCoordination());
const participantTypeOptions = [
    ["student", "Estudiante"],
    ["guardian", "Apoderado(a)"],
    ["teacher", "Docente"],
    ["education_assistant", "Asistente de la educación"],
    ["other", "Otro"],
];
const followUpTypeOptions = [
    ["phone_call", "Llamada telefónica"],
    ["new_interview", "Nueva entrevista"],
    ["guardian_contact", "Contacto con apoderado(a)"],
    ["student_check_in", "Seguimiento con estudiante"],
    ["teacher_coordination", "Coordinación con docente"],
    ["external_coordination", "Coordinación externa"],
    ["case_review", "Revisión de caso"],
    ["other", "Otro seguimiento"],
];
const followUpTypeLabel = (value) =>
    followUpTypeOptions.find(([key]) => key === value)?.[1] || "Seguimiento";
const coordinationTypeOptions = [
    ["meeting", "Reunión de coordinación"],
    ["information_request", "Solicitud de antecedentes"],
    ["case_review", "Revisión de caso"],
    ["classroom_support", "Apoyo en aula"],
    ["family_support", "Coordinación con familia"],
    ["protocol_coordination", "Coordinación de protocolo"],
    ["other", "Otra coordinación"],
];
const coordinationTypeLabel = (value) =>
    coordinationTypeOptions.find(([key]) => key === value)?.[1] ||
    "Coordinación";
const coordinationStatus = {
    pending: ["Pendiente", "pending"],
    accepted: ["Aceptada", "accepted"],
    rejected: ["Rechazada", "rejected"],
};
const coordinationRows = computed(
    () => selected.value?.coordination_requests || []
);
const coordinationCount = (status) =>
    coordinationRows.value.filter((item) => item.status === status).length;
const coordinationRecipientPosition = (recipientId) =>
    (props.catalogs.coordination_recipients || []).find(
        (item) => Number(item.id) === Number(recipientId)
    )?.position || "Funcionario(a)";
const handleFollowUpTypeChange = () => {
    if (activity.follow_up_type) return;
    activity.next_action_on = "";
    activity.next_steps = "";
};
const additionalParticipantOptions = computed(() =>
    participantTypeOptions.filter(
        ([type]) => type !== activity.interviewee_type
    )
);
const intervieweeTypeLabel = (value) =>
    participantTypeOptions.find(([key]) => key === value)?.[1] || value;
const prefillInterviewee = (type) => {
    const student = selected.value?.student;
    if (!student) return;

    if (type === "student") {
        activity.interviewee_name = student.name || "";
        activity.interviewee_rut = student.rut || "";
        return;
    }
    if (type === "guardian") {
        activity.interviewee_name = student.guardian_name || "";
        activity.interviewee_rut = student.guardian_rut || "";
        return;
    }

    activity.interviewee_name = "";
    activity.interviewee_rut = "";
};
const selectPrimaryInterviewee = (type) => {
    const interviewTypes = {
        student: "student_interview",
        guardian: "guardian_interview",
        teacher: "teacher_interview",
    };
    if (interviewTypes[type]) {
        activity.type = interviewTypes[type];
    }
    activity.interviewee_type = type;
    activity.participant_types = [type];
    prefillInterviewee(type);
};
const resetActivityForm = () => {
    Object.assign(activity, blankActivity());
    prefillInterviewee("student");
};
const openActivityForm = () => {
    api.error.value = "";
    resetActivityForm();
    openForm("activity");
};
const openActivityEdit = (item) => {
    if (!item?.id) return;

    const defaults = blankActivity();
    const hydrated = Object.fromEntries(
        Object.keys(defaults)
            .filter((key) => !["coordination", "coordination_enabled"].includes(key))
            .map((key) => [key, item[key] ?? defaults[key]])
    );
    Object.assign(activity, defaults, hydrated, {
        id: item.id,
        record_updated_at: item.updated_at || "",
        change_reason: "",
        activity_on: item.activity_on?.slice(0, 10) || "",
        starts_at: item.starts_at?.slice(0, 5) || "",
        ends_at: item.ends_at?.slice(0, 5) || "",
        next_action_on: item.next_action_on?.slice(0, 10) || "",
        participant_types: [...(item.participant_types || [])],
        coordination_enabled: false,
        coordination: blankCoordination(),
    });
    selectedActivity.value = null;
    api.error.value = "";
    openForm("activity");
};
const openForm = (name) => {
    formModal.value = name;
};
const closeForm = () => {
    formModal.value = "";
};
const openCoordinationForm = () => {
    Object.assign(coordination, blankCoordination());
    api.error.value = "";
    openForm("coordination");
};
const task = reactive({
    responsible_user_id: null,
    title: "",
    description: "",
    type: "follow_up",
    priority: "medium",
    due_at: "",
    remind_at: "",
});
const risk = reactive({
    risk_type: "emotional_crisis",
    level: "medium",
    structured_indicators: "",
    professional_rationale: "",
    immediate_action: "",
    response_responsible_user_id: null,
    response_at: "",
    protocol_reference: "",
});
const closure = reactive({
    closure_type: "objectives_met",
    reason: "",
    result_summary: "",
    recommendations: "",
});
const reassignment = reactive({ user_id: null, reason: "" });
const reopening = reactive({ reason: "" });
const plan = reactive({
    status: "draft",
    review_on: "",
    general_situation: "",
    general_objective: "",
    specific_objectives: "",
    planned_actions: "",
    responsibles: "",
    frequency: "",
    estimated_start_on: "",
    estimated_end_on: "",
    monitoring_indicators: "",
    participants: "",
    family_coordination: "",
    teacher_coordination: "",
    coexistence_coordination: "",
    external_coordination: "",
});
const consent = reactive({
    action_type: "intervention",
    guardian_informed: false,
    informed_at: "",
    contact_method: "phone",
    contact_result: "",
    consent_required: false,
    status: "pending",
    institutional_exception: false,
    observations: "",
});
const external = reactive({
    institution: "",
    institution_type: "",
    general_reason: "",
    referred_on: new Date().toISOString().slice(0, 10),
    guardian_informed: false,
    follow_up_pending: true,
    next_contact_on: "",
});
const feedback = reactive({ referral_id: null, content: "" });
const documentForm = reactive({
    file: null,
    category: "background",
    description: "",
    visibility: "private_psychology",
});
const blankCaseCreation = () => ({
    responsible_user_id: null,
    priority: "medium",
    confidentiality: "private_psychology",
    general_reason: "",
    objectives: "",
    next_action: "",
    next_review_on: "",
    guardian_information_status: "pending",
});
const caseCreation = reactive(blankCaseCreation());
const blankCaseEdit = () => ({
    status: "open",
    priority: "medium",
    confidentiality: "private_psychology",
    general_reason: "",
    categories: "",
    objectives: "",
    next_action: "",
    next_review_on: "",
    guardian_information_status: "pending",
    change_reason: "",
});
const caseEdit = reactive(blankCaseEdit());
const selectedReferral = computed(() =>
    eligibleReferrals.value.find(
        (item) => Number(item.id) === Number(selectedReferralId.value)
    )
);
const caseStudent = computed(() =>
    caseOriginMode.value === "referral"
        ? selectedReferral.value?.student
        : selectedDirectStudent.value
);
const caseCourse = computed(() =>
    caseOriginMode.value === "referral"
        ? selectedReferral.value?.course?.name
        : selectedDirectStudent.value?.course
);
const tabs = [
    ["summary", "Resumen"],
    ["referrals", "Derivaciones"],
    ["plans", "Plan de intervención"],
    ["activities", "Sesiones y entrevistas"],
    ["followups", "Seguimientos"],
    ["coordination", "Coordinaciones"],
    ["risks", "Riesgos y resguardo"],
    ["tasks", "Tareas y compromisos"],
    ["documents", "Documentos"],
    ["feedback", "Comunicaciones"],
    ["timeline", "Línea de tiempo"],
    ["closure", "Cierre"],
];
const load = async () => {
    list.value = await api.get("/api/psychology/cases", filters);
};
const loadFollowUps = async (page = followupFilters.page) => {
    followupLoading.value = true;
    followupFilters.page = page;
    try {
        followupRows.value = await api.get("/api/psychology/follow-ups", {
            search: followupFilters.search || undefined,
            status: followupFilters.status || undefined,
            page,
            per_page: followupFilters.per_page,
        });
    } finally {
        followupLoading.value = false;
    }
};
const refreshFollowUps = () => {
    followupCalendarKey.value += 1;
    loadFollowUps(1);
};
const openFollowUpCase = async (item) => {
    const response = await api.get(`/api/psychology/cases/${item.case_id}`);
    selected.value = response.data || response;
    resetActivityForm();
    activeTab.value = "followups";
};
const followupEventColor = (item) => {
    if (item.is_overdue || item.status === "overdue") return "#c95459";
    if (item.status === "completed") return "#3f8c71";
    if (item.status === "cancelled") return "#8491a3";
    if (item.status === "in_progress") return "#5275c7";
    return "#70578f";
};
const followupCalendarOptions = computed(() => ({
    plugins: [dayGridPlugin, listPlugin, bootstrap5Plugin],
    locales: [esLocale],
    locale: esLocale,
    themeSystem: "bootstrap5",
    initialView: "dayGridMonth",
    firstDay: 1,
    fixedWeekCount: false,
    headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,listMonth",
    },
    buttonText: {
        today: "Hoy",
        month: "Mes",
        list: "Agenda",
    },
    noEventsText: "No hay seguimientos programados en este período",
    moreLinkText: (count) => `+${count} más`,
    events: async (info, success, failure) => {
        try {
            const response = await api.get("/api/psychology/follow-ups", {
                from: info.startStr.slice(0, 10),
                to: info.endStr.slice(0, 10),
                search: followupFilters.search || undefined,
                status: followupFilters.status || undefined,
                per_page: 500,
            });
            success(
                (response.data || [])
                    .filter((item) => item.due_at)
                    .map((item) => ({
                        id: `followup-${item.id}`,
                        title: `${item.follow_up_type_label} · ${item.case_code} · ${item.student_name}`,
                        start: item.due_at,
                        allDay: true,
                        backgroundColor: followupEventColor(item),
                        borderColor: followupEventColor(item),
                        extendedProps: item,
                    }))
            );
        } catch (error) {
            failure(error);
        }
    },
    eventClick: ({ event }) => openFollowUpCase(event.extendedProps),
    eventDidMount: ({ el, event }) => {
        el.title = `${event.extendedProps.title} · ${
            event.extendedProps.responsible_name || "Sin responsable"
        }`;
    },
    height: "auto",
}));
const loadEligibleReferrals = async () => {
    const [accepted, reviewing] = await Promise.all([
        api.get("/api/psychology/referrals", {
            status: "accepted",
            per_page: 100,
        }),
        api.get("/api/psychology/referrals", {
            status: "under_review",
            per_page: 100,
        }),
    ]);
    eligibleReferrals.value = [...accepted.data, ...reviewing.data]
        .filter((item) => !item.case_id)
        .sort((a, b) =>
            String(a.student?.name || "").localeCompare(
                String(b.student?.name || ""),
                "es"
            )
        );
};
const resetCaseCreation = () => {
    selectedReferralId.value = null;
    directStudentSearch.value = "";
    directStudentResults.value = [];
    selectedDirectStudent.value = null;
    Object.assign(caseCreation, blankCaseCreation());
};
const openCreateCase = () => {
    showCreateCase.value = true;
    caseOriginMode.value = "direct";
    resetCaseCreation();
};
const selectCaseOrigin = async (mode) => {
    caseOriginMode.value = mode;
    resetCaseCreation();
    if (mode !== "referral") return;

    loadingEligibleReferrals.value = true;
    try {
        await loadEligibleReferrals();
    } finally {
        loadingEligibleReferrals.value = false;
    }
};
const searchDirectStudents = () => {
    clearTimeout(studentSearchTimer);
    selectedDirectStudent.value = null;
    if (directStudentSearch.value.trim().length < 2) {
        directStudentResults.value = [];
        return;
    }
    studentSearchTimer = setTimeout(async () => {
        searchingDirectStudents.value = true;
        try {
            directStudentResults.value = (
                await api.get("/api/psychology/students", {
                    search: directStudentSearch.value.trim(),
                })
            ).data;
        } finally {
            searchingDirectStudents.value = false;
        }
    }, 300);
};
const chooseDirectStudent = (student) => {
    selectedDirectStudent.value = student;
    directStudentSearch.value = `${student.name} · ${
        student.course || "Sin curso"
    }`;
    directStudentResults.value = [];
};
const closeCreateCase = () => {
    showCreateCase.value = false;
    eligibleReferrals.value = [];
    resetCaseCreation();
};
const createCase = async () => {
    const direct = caseOriginMode.value === "direct";
    const response = await api.post(
        direct
            ? "/api/psychology/cases"
            : `/api/psychology/referrals/${selectedReferralId.value}/open-case`,
        direct
            ? {
                  ...caseCreation,
                  student_profile_id: selectedDirectStudent.value.id,
              }
            : caseCreation
    );
    const created = response.data;
    closeCreateCase();
    await load();
    if (created) await open(created);
};
watch(
    () => [filters.search, filters.status, filters.priority],
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            filters.page = 1;
            load();
        }, 350);
    }
);
watch(
    () => activity.type,
    (type) => {
        const intervieweeTypes = {
            student_interview: "student",
            guardian_interview: "guardian",
            teacher_interview: "teacher",
        };
        const intervieweeType = intervieweeTypes[type];
        if (!intervieweeType) return;

        activity.interviewee_type = intervieweeType;
        activity.participant_types = [intervieweeType];
        prefillInterviewee(intervieweeType);
    }
);
watch(
    () => activity.interviewee_type,
    (type) => prefillInterviewee(type)
);
watch(selectedReferralId, () => {
    if (!selectedReferral.value) return;
    caseCreation.responsible_user_id =
        selectedReferral.value.assigned_user?.id || null;
    caseCreation.priority =
        selectedReferral.value.professional_priority ||
        selectedReferral.value.suggested_urgency ||
        "medium";
    caseCreation.general_reason = selectedReferral.value.primary_reason || "";
});
watch(
    () => [followupFilters.search, followupFilters.status],
    () => {
        clearTimeout(followupSearchTimer);
        followupSearchTimer = setTimeout(() => {
            followupCalendarKey.value += 1;
            loadFollowUps(1);
        }, 300);
    }
);
watch(activeTab, (tab) => {
    closeForm();
    selectedActivity.value = null;
    selectedPlan.value = null;
    if (tab === "followups") loadFollowUps(1);
});
const open = async (item) => {
    const response = await api.get(`/api/psychology/cases/${item.id}`);
    selected.value = response.data || response;
    resetActivityForm();
    activeTab.value = "summary";
};
const closeCaseFile = () => {
    selected.value = null;
    selectedActivity.value = null;
    selectedPlan.value = null;
    activeTab.value = "summary";
    closeForm();
};
const openCaseEditForm = () => {
    if (!selected.value) return;

    Object.assign(caseEdit, blankCaseEdit(), {
        status: selected.value.status || "open",
        priority: selected.value.priority || "medium",
        confidentiality:
            selected.value.confidentiality || "private_psychology",
        general_reason: selected.value.general_reason || "",
        categories: selected.value.categories || "",
        objectives: selected.value.objectives || "",
        next_action: selected.value.next_action || "",
        next_review_on: selected.value.next_review_on || "",
        guardian_information_status:
            selected.value.guardian_information_status || "pending",
    });
    api.error.value = "";
    openForm("edit-case");
};
const reloadCase = async () => {
    if (!selected.value) return;
    const currentTab = activeTab.value;
    await open(selected.value);
    activeTab.value = currentTab;
};
const saveActivity = async (finalized = false) => {
    activity.status = finalized ? "finalized" : "draft";
    if (!activity.id) activity.ends_at = deviceTime();
    const payload = {
        ...activity,
        coordination: activity.coordination_enabled
            ? { ...activity.coordination }
            : undefined,
    };
    const activityId = payload.id;
    delete payload.id;
    delete payload.coordination_enabled;
    if (!activity.coordination_enabled) delete payload.coordination;
    try {
        if (activityId) {
            await api.patch(`/api/psychology/activities/${activityId}`, payload);
        } else {
            await api.post(
                `/api/psychology/cases/${selected.value.id}/activities`,
                payload
            );
        }
        await reloadCase();
        resetActivityForm();
        closeForm();
    } catch {
        // El mensaje validado se mantiene visible dentro del formulario.
    }
};
const saveCaseChanges = async () => {
    try {
        const response = await api.patch(
            `/api/psychology/cases/${selected.value.id}`,
            { ...caseEdit }
        );
        selected.value = response.data || response;
        await load();
        closeForm();
    } catch {
        // El formulario conserva el mensaje validado por el servidor.
    }
};
const saveCoordination = async () => {
    try {
        await api.post(
            `/api/psychology/cases/${selected.value.id}/coordinations`,
            coordination
        );
        await reloadCase();
        Object.assign(coordination, blankCoordination());
        closeForm();
    } catch {
        // El formulario conserva el mensaje de validación del servidor.
    }
};
const exportSelectedActivityPdf = async (
    activityItem = selectedActivity.value
) => {
    if (!activityItem || exportingActivityPdf.value) return;

    exportingActivityPdf.value = true;
    api.error.value = "";
    try {
        const response = await api.post(
            `/api/psychology/activities/${activityItem.id}/export`
        );
        await downloadPsychologyActivityPdf(response.data || response);
    } catch {
        // El servicio conserva el mensaje de autorización o descarga.
    } finally {
        exportingActivityPdf.value = false;
    }
};
const exportCasePdf = async () => {
    if (!selected.value || exportingCasePdf.value) return;

    exportingCasePdf.value = true;
    api.error.value = "";
    try {
        const response = await api.post(
            `/api/psychology/cases/${selected.value.id}/export`
        );
        await downloadPsychologyCasePdf(response);
    } catch {
        // El servicio conserva el mensaje de autorización o descarga.
    } finally {
        exportingCasePdf.value = false;
    }
};
const exportPlanPdf = async (planItem = selectedPlan.value) => {
    if (!planItem || exportingPlanPdf.value) return;

    exportingPlanPdf.value = true;
    api.error.value = "";
    try {
        const response = await api.post(
            `/api/psychology/plans/${planItem.id}/export`
        );
        await downloadPsychologyPlanPdf(response.data || response);
    } catch {
        // El servicio conserva el mensaje de autorización o descarga.
    } finally {
        exportingPlanPdf.value = false;
    }
};
const saveTask = async () => {
    await api.post(`/api/psychology/cases/${selected.value.id}/tasks`, task);
    Object.assign(task, { title: "", description: "", due_at: "" });
    await reloadCase();
    closeForm();
};
const saveRisk = async () => {
    await api.post(
        `/api/psychology/cases/${selected.value.id}/risk-assessments`,
        risk
    );
    await reloadCase();
    closeForm();
};
const savePlan = async () => {
    await api.post(`/api/psychology/cases/${selected.value.id}/plans`, plan);
    await reloadCase();
    closeForm();
};
const saveConsent = async () => {
    await api.post(
        `/api/psychology/cases/${selected.value.id}/consents`,
        consent
    );
    await reloadCase();
    closeForm();
};
const saveExternal = async () => {
    await api.post(
        `/api/psychology/cases/${selected.value.id}/external-referrals`,
        external
    );
    await reloadCase();
    closeForm();
};
const saveFeedback = async () => {
    await api.post(
        `/api/psychology/cases/${selected.value.id}/feedback`,
        feedback
    );
    feedback.content = "";
    await reloadCase();
    closeForm();
};
const uploadDocument = async () => {
    const body = new FormData();
    body.append("document", documentForm.file);
    body.append("case_id", selected.value.id);
    body.append("category", documentForm.category);
    body.append("description", documentForm.description || "");
    body.append("visibility", documentForm.visibility);
    await api.post("/api/psychology/documents", body, {
        headers: { "Content-Type": "multipart/form-data" },
    });
    await reloadCase();
    closeForm();
};
const downloadDocument = async (item) => {
    const response = await axios.get(
        `/api/psychology/documents/${item.id}/download`,
        { responseType: "blob" }
    );
    const url = URL.createObjectURL(response.data);
    const a = document.createElement("a");
    a.href = url;
    a.download = item.original_name;
    a.click();
    URL.revokeObjectURL(url);
};
const closeCase = async () => {
    await api.post(`/api/psychology/cases/${selected.value.id}/close`, closure);
    await reloadCase();
    await load();
    closeForm();
};
const reassignCase = async () => {
    await api.post(
        `/api/psychology/cases/${selected.value.id}/assign`,
        reassignment
    );
    Object.assign(reassignment, { user_id: null, reason: "" });
    await reloadCase();
    await load();
    closeForm();
};
const reopenCase = async () => {
    await api.post(
        `/api/psychology/cases/${selected.value.id}/reopen`,
        reopening
    );
    reopening.reason = "";
    await reloadCase();
    await load();
};
const completeTask = async (item) => {
    await api.patch(`/api/psychology/tasks/${item.id}`, {
        status: "completed",
        completion_evidence: "Cumplimiento registrado desde la ficha.",
    });
    await reloadCase();
};
onMounted(load);
</script>
<template>
    <div>
        <div class="psi-toolbar">
            <div class="psi-toolbar-copy">
                <span class="psi-toolbar-icon"
                    ><i class="bx bx-folder-open"></i
                ></span>
                <div>
                    <span class="psi-eyebrow">Cartera profesional</span>
                    <h4>
                        {{
                            catalogs.capabilities.personal_scope
                                ? "Mis casos de Psicología"
                                : "Casos de Psicología"
                        }}
                    </h4>
                    <p>
                        {{
                            catalogs.capabilities.full_domain
                                ? "Vista global de todos los casos y equipos responsables."
                                : catalogs.capabilities.personal_scope
                                ? "Solo aparecen los casos que tienes asignados como profesional responsable."
                                : "Acceso limitado a casos asignados o autorizados."
                        }}
                    </p>
                </div>
            </div>
            <div class="psi-toolbar-actions">
                <span
                    v-if="catalogs.capabilities.personal_scope"
                    class="psi-private-chip"
                >
                    <i class="bx bx-lock-alt"></i> Cartera privada
                </span>
                <button
                    v-if="catalogs.capabilities.create_case"
                    type="button"
                    class="btn btn-primary psi-primary-action"
                    @click="openCreateCase"
                >
                    <i class="bx bx-plus"></i>
                    Crear caso
                </button>
            </div>
        </div>
        <div v-if="api.error.value" class="alert alert-danger">
            {{ api.error.value }}
        </div>
        <div class="psi-table-card">
            <div class="card-body">
                <div class="psi-list-filters">
                    <div class="psi-search-field">
                        <i class="bx bx-search"></i>
                        <input
                            v-model="filters.search"
                            class="form-control"
                            placeholder="Buscar código o estudiante"
                        />
                    </div>
                    <div>
                        <select v-model="filters.status" class="form-select">
                            <option value="">Todos los estados</option>
                            <option
                                v-for="v in [
                                    'open',
                                    'assessment',
                                    'active_intervention',
                                    'monitoring',
                                    'awaiting_information',
                                    'paused',
                                    'closed',
                                    'reopened',
                                ]"
                                :key="v"
                                :value="v"
                            >
                                {{ labelFor(v) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <select v-model="filters.priority" class="form-select">
                            <option value="">Toda prioridad</option>
                            <option
                                v-for="v in [
                                    'low',
                                    'medium',
                                    'high',
                                    'critical',
                                ]"
                                :key="v"
                                :value="v"
                            >
                                {{ labelFor(v, true) }}
                            </option>
                        </select>
                    </div>
                </div>
                <div
                    v-if="!list.data.length && !api.loading.value"
                    class="psi-empty"
                >
                    <i class="bx bx-folder-open"></i>
                    <strong>No hay casos para mostrar</strong>
                    <span>Prueba con otros criterios de búsqueda.</span>
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Código / caso</th>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Responsable</th>
                                <th>Próxima acción</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in list.data" :key="item.id">
                                <td>
                                    <strong>{{ item.code }}</strong>
                                </td>
                                <td>{{ item.student?.name }}</td>
                                <td>
                                    {{ item.student?.course || "Sin curso" }}
                                </td>
                                <td>
                                    <PsychologyBadge :value="item.status" />
                                </td>
                                <td>
                                    <PsychologyBadge
                                        :value="item.priority"
                                        priority
                                    />
                                </td>
                                <td>
                                    {{
                                        item.responsible_user?.name ||
                                        "Sin asignar"
                                    }}
                                </td>
                                <td>{{ item.next_action || "Por definir" }}</td>
                                <td class="text-end">
                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        @click="open(item)"
                                    >
                                        Abrir ficha
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="list.meta?.last_page > 1" class="psi-pagination">
                    <span>
                        Página {{ list.meta.current_page }} de
                        {{ list.meta.last_page }} · {{ list.meta.total }} casos
                    </span>
                    <div>
                        <button
                            class="btn btn-sm btn-light"
                            :disabled="filters.page <= 1 || api.loading.value"
                            @click="
                                filters.page--;
                                load();
                            "
                        >
                            Anterior
                        </button>
                        <button
                            class="btn btn-sm btn-light"
                            :disabled="
                                filters.page >= list.meta.last_page ||
                                api.loading.value
                            "
                            @click="
                                filters.page++;
                                load();
                            "
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <PsychologyModal
            v-if="showCreateCase"
            eyebrow="Apertura confidencial"
            title="Crear caso de Psicología"
            size="xlarge-tall"
            @close="closeCreateCase"
        >
            <div class="psi-origin-selector" aria-label="Origen del caso">
                <button
                    type="button"
                    :class="{ active: caseOriginMode === 'direct' }"
                    @click="selectCaseOrigin('direct')"
                >
                    <i class="bx bx-user-plus"></i>
                    <span>
                        <strong>Atención directa</strong>
                        <small>Sin derivación previa</small>
                    </span>
                    <i class="bx bx-check-circle check"></i>
                </button>
                <button
                    type="button"
                    :class="{ active: caseOriginMode === 'referral' }"
                    @click="selectCaseOrigin('referral')"
                >
                    <i class="bx bx-link-alt"></i>
                    <span>
                        <strong>Desde derivación</strong>
                        <small>Vincular antecedente revisado</small>
                    </span>
                    <i class="bx bx-check-circle check"></i>
                </button>
            </div>

            <div v-if="caseOriginMode === 'direct'" class="psi-create-case">
                <div class="psi-create-guidance">
                    <i class="bx bx-shield-quarter"></i>
                    <div>
                        <strong>Apertura profesional directa</strong>
                        <span
                            >El caso quedará auditado como atención directa y
                            visible únicamente según su responsable y nivel de
                            confidencialidad.</span
                        >
                    </div>
                </div>
                <div class="psi-create-grid">
                    <div class="wide psi-student-picker">
                        <label class="form-label"
                            >Estudiante <span>*</span></label
                        >
                        <div class="psi-search-field">
                            <i class="bx bx-search"></i>
                            <input
                                v-model="directStudentSearch"
                                class="form-control"
                                autocomplete="off"
                                placeholder="Buscar por nombre o RUT"
                                @input="searchDirectStudents"
                            />
                            <span
                                v-if="searchingDirectStudents"
                                class="spinner-border spinner-border-sm"
                            ></span>
                        </div>
                        <div
                            v-if="directStudentResults.length"
                            class="psi-student-results"
                        >
                            <button
                                v-for="student in directStudentResults"
                                :key="student.id"
                                type="button"
                                @click="chooseDirectStudent(student)"
                            >
                                <span>
                                    <strong>{{ student.name }}</strong>
                                    <small
                                        >{{ student.course || "Sin curso" }} ·
                                        {{ student.rut || "Sin RUT" }}</small
                                    >
                                </span>
                                <em v-if="student.active_case">Caso activo</em>
                                <i v-else class="bx bx-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else-if="loadingEligibleReferrals"
                class="psi-create-loading"
            >
                <span class="spinner-border spinner-border-sm"></span>
                Buscando derivaciones disponibles…
            </div>
            <div v-else-if="eligibleReferrals.length" class="psi-create-case">
                <div class="psi-create-guidance">
                    <i class="bx bx-link-alt"></i>
                    <div>
                        <strong>Selecciona una derivación revisada</strong>
                        <span
                            >El nuevo caso conservará el antecedente de origen y
                            su trazabilidad institucional.</span
                        >
                    </div>
                </div>
                <div class="psi-create-grid">
                    <div class="wide">
                        <label class="form-label"
                            >Derivación de origen <span>*</span></label
                        >
                        <select
                            v-model="selectedReferralId"
                            class="form-select"
                        >
                            <option :value="null">
                                Selecciona una derivación
                            </option>
                            <option
                                v-for="referral in eligibleReferrals"
                                :key="referral.id"
                                :value="referral.id"
                            >
                                {{ referral.code }} ·
                                {{ referral.student?.name }} ·
                                {{ labelFor(referral.status) }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            <div v-else class="psi-create-empty compact">
                <span><i class="bx bx-transfer-alt"></i></span>
                <strong>No hay derivaciones listas</strong>
                <p>
                    Puedes abrir el caso como atención directa o revisar las
                    derivaciones pendientes.
                </p>
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    @click="router.push('/psychology/referrals')"
                >
                    Ir a derivaciones
                </button>
            </div>

            <div
                v-if="caseStudent"
                class="psi-create-grid psi-common-case-fields"
            >
                <div class="psi-referral-summary wide">
                    <span class="psi-referral-avatar">
                        <i class="bx bx-user"></i>
                    </span>
                    <div>
                        <small>Estudiante seleccionada</small>
                        <strong>{{ caseStudent.name }}</strong>
                        <span>
                            {{ caseCourse || "Sin curso" }}
                            <template v-if="selectedReferral">
                                · {{ selectedReferral.code }}
                            </template>
                        </span>
                    </div>
                    <PsychologyBadge
                        v-if="selectedReferral"
                        :value="selectedReferral.status"
                    />
                </div>
                <div
                    v-if="selectedDirectStudent?.active_case"
                    class="alert alert-warning wide mb-0"
                >
                    <i class="bx bx-error-circle me-1"></i>
                    La estudiante ya tiene un caso activo. Abre su ficha actual
                    antes de crear uno nuevo.
                </div>
                <div>
                    <label class="form-label">Profesional responsable</label>
                    <select
                        v-model="caseCreation.responsible_user_id"
                        class="form-select"
                    >
                        <option :value="null">
                            {{
                                caseOriginMode === "direct"
                                    ? "Profesional que crea el caso"
                                    : "Profesional de la derivación"
                            }}
                        </option>
                        <option
                            v-for="person in catalogs.professionals"
                            :key="person.id"
                            :value="person.id"
                        >
                            {{ person.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Prioridad <span>*</span></label>
                    <select v-model="caseCreation.priority" class="form-select">
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                        <option value="critical">Crítica</option>
                    </select>
                </div>
                <div>
                    <label class="form-label"
                        >Confidencialidad <span>*</span></label
                    >
                    <select
                        v-model="caseCreation.confidentiality"
                        class="form-select"
                    >
                        <option value="private_psychology">
                            Privada de Psicología
                        </option>
                        <option value="psychology_team">
                            Equipo de Psicología
                        </option>
                        <option value="interdisciplinary_team">
                            Equipo interdisciplinario
                        </option>
                    </select>
                </div>
                <div class="wide">
                    <label class="form-label"
                        >Motivo general <span>*</span></label
                    >
                    <input
                        v-model="caseCreation.general_reason"
                        class="form-control"
                        maxlength="191"
                        placeholder="Describe el motivo general del caso"
                    />
                </div>
                <div class="wide">
                    <label class="form-label">Objetivos iniciales</label>
                    <textarea
                        v-model="caseCreation.objectives"
                        class="form-control"
                        rows="3"
                        placeholder="Resultados esperados de la intervención"
                    ></textarea>
                </div>
                <div>
                    <label class="form-label">Próxima revisión</label>
                    <input
                        v-model="caseCreation.next_review_on"
                        type="date"
                        class="form-control"
                    />
                </div>
                <div>
                    <label class="form-label">Próxima acción</label>
                    <input
                        v-model="caseCreation.next_action"
                        class="form-control"
                        placeholder="Ej.: entrevista inicial"
                    />
                </div>
            </div>
            <template #footer>
                <button
                    type="button"
                    class="btn btn-light"
                    @click="closeCreateCase"
                >
                    Cancelar
                </button>
                <button
                    v-if="caseStudent"
                    type="button"
                    class="btn btn-primary"
                    :disabled="
                        selectedDirectStudent?.active_case ||
                        !caseCreation.general_reason.trim() ||
                        api.loading.value
                    "
                    @click="createCase"
                >
                    <i class="bx bx-folder-plus me-1"></i>
                    {{ api.loading.value ? "Creando…" : "Crear caso" }}
                </button>
            </template>
        </PsychologyModal>
        <PsychologyModal
            v-if="selected"
            eyebrow="Ficha confidencial"
            :title="`${selected.code} · ${selected.student?.name}`"
            size="xlarge-tall"
            @close="closeCaseFile"
        >
            <div class="psi-casefile is-modal">
                <div class="psi-case-identity">
                    <span class="psi-case-identity-icon">
                        <i class="bx bx-folder-open"></i>
                    </span>
                    <div>
                        <small>Motivo general</small>
                        <strong>{{ selected.general_reason }}</strong>
                        <span>
                            {{ selected.student?.course || "Sin curso" }} ·
                            {{
                                selected.origin === "referral"
                                    ? "Desde derivación"
                                    : "Atención directa"
                            }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="btn psi-case-export-button"
                        :disabled="exportingCasePdf"
                        @click="exportCasePdf"
                    >
                        <i class="bx bxs-file-pdf"></i>
                        <span>{{
                            exportingCasePdf
                                ? "Preparando expediente…"
                                : "Exportar caso"
                        }}</span>
                    </button>
                    <div class="psi-case-identity-badges">
                        <PsychologyBadge :value="selected.priority" priority />
                        <PsychologyBadge :value="selected.status" />
                    </div>
                </div>
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="bx bx-lock-alt me-1"></i>Información sensible.
                    Todo acceso y descarga queda registrado.
                </div>
                <nav>
                    <button
                        v-for="tab in tabs"
                        :key="tab[0]"
                        :class="{ active: activeTab === tab[0] }"
                        @click="activeTab = tab[0]"
                    >
                        {{ tab[1] }}
                    </button>
                </nav>
                <section v-if="activeTab === 'summary'" class="psi-section">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="card psi-case-summary-card">
                                <div class="card-body">
                                    <div class="psi-card-title">
                                        <span><i class="bx bx-file"></i></span>
                                        <div>
                                            <h5>Información del caso</h5>
                                            <p>
                                                Datos vigentes y actualizables
                                                del expediente.
                                            </p>
                                        </div>
                                        <button
                                            v-if="
                                                catalogs.capabilities
                                                    .edit_case &&
                                                selected.status !== 'closed'
                                            "
                                            type="button"
                                            class="btn psi-edit-case-button"
                                            @click="openCaseEditForm"
                                        >
                                            <i class="bx bx-edit-alt"></i>
                                            Editar
                                        </button>
                                    </div>
                                    <dl class="psi-case-data-grid">
                                        <div class="wide emphasized">
                                            <dt>Motivo general</dt>
                                            <dd>
                                                {{
                                                    selected.general_reason ||
                                                    "Sin motivo registrado"
                                                }}
                                            </dd>
                                        </div>
                                        <div class="wide">
                                            <dt>Objetivos iniciales</dt>
                                            <dd>
                                                {{
                                                    selected.objectives ||
                                                    "Por definir"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Próxima acción</dt>
                                            <dd>
                                                {{
                                                    selected.next_action ||
                                                    "Por definir"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Próxima revisión</dt>
                                            <dd>
                                                {{
                                                    selected.next_review_on
                                                        ? formatDate(
                                                              selected.next_review_on
                                                          )
                                                        : "Sin fecha"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Categorías</dt>
                                            <dd>
                                                {{
                                                    selected.categories ||
                                                    "Sin categorías"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Origen del caso</dt>
                                            <dd>
                                                {{
                                                    selected.origin ===
                                                    "referral"
                                                        ? "Desde derivación"
                                                        : "Atención directa"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Confidencialidad</dt>
                                            <dd>
                                                {{
                                                    confidentialityLabel(
                                                        selected.confidentiality
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Información al apoderado</dt>
                                            <dd>
                                                {{
                                                    guardianInformationLabel(
                                                        selected.guardian_information_status
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Fecha de apertura</dt>
                                            <dd>
                                                {{
                                                    formatDateTime(
                                                        selected.opened_at
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card psi-person-card">
                                <div class="card-body">
                                    <div class="psi-card-title">
                                        <span><i class="bx bx-user"></i></span>
                                        <div>
                                            <h5>Estudiante</h5>
                                            <p>
                                                Antecedentes de identificación
                                            </p>
                                        </div>
                                    </div>
                                    <strong>{{
                                        selected.student?.name
                                    }}</strong>
                                    <dl>
                                        <div>
                                            <dt>Curso</dt>
                                            <dd>
                                                {{
                                                    selected.student?.course ||
                                                    "Sin curso"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>RUT</dt>
                                            <dd>
                                                {{
                                                    selected.student?.rut ||
                                                    "Sin registro"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Apoderado</dt>
                                            <dd>
                                                {{
                                                    selected.student
                                                        ?.guardian_name ||
                                                    "Sin registro"
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            <div class="card psi-person-card mt-3">
                                <div class="card-body">
                                    <div class="psi-card-title">
                                        <span><i class="bx bx-group"></i></span>
                                        <div>
                                            <h5>Equipo responsable</h5>
                                            <p>
                                                Profesionales con acceso al caso
                                            </p>
                                        </div>
                                    </div>
                                    <strong>{{
                                        selected.responsible_user?.name
                                    }}</strong>
                                    <p class="text-muted small">
                                        Profesional responsable
                                    </p>
                                    <button
                                        v-if="
                                            catalogs.capabilities
                                                .reassign_case &&
                                            selected.status !== 'closed'
                                        "
                                        class="btn btn-sm btn-outline-primary mb-3"
                                        @click="openForm('reassign')"
                                    >
                                        <i class="bx bx-transfer me-1"></i
                                        >Reasignar
                                    </button>
                                    <div
                                        v-for="person in selected.collaborators"
                                        :key="person.id"
                                    >
                                        {{ person.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <PsychologyModal
                        v-if="formModal === 'edit-case'"
                        eyebrow="Actualización trazable"
                        title="Editar información del caso"
                        @close="closeForm"
                    >
                        <div class="psi-edit-case-context">
                            <span><i class="bx bx-lock-alt"></i></span>
                            <div>
                                <strong>{{ selected.code }}</strong>
                                <p>
                                    {{ selected.student?.name }} · El código,
                                    estudiante, origen y fecha técnica de
                                    apertura no se modifican.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="api.error.value"
                            class="alert alert-danger psi-form-error"
                            role="alert"
                        >
                            <i class="bx bx-error-circle"></i>
                            <div>
                                <strong>No se pudo actualizar el caso</strong>
                                <span>{{ api.error.value }}</span>
                            </div>
                        </div>
                        <div class="psi-create-grid psi-edit-case-grid">
                            <div class="wide">
                                <label
                                    for="psi-case-edit-general-reason"
                                    class="form-label"
                                    >Motivo general <span>*</span></label
                                >
                                <input
                                    id="psi-case-edit-general-reason"
                                    v-model="caseEdit.general_reason"
                                    class="form-control"
                                    maxlength="191"
                                />
                            </div>
                            <div class="wide">
                                <label
                                    for="psi-case-edit-objectives"
                                    class="form-label"
                                    >Objetivos del caso</label
                                >
                                <textarea
                                    id="psi-case-edit-objectives"
                                    v-model="caseEdit.objectives"
                                    class="form-control"
                                    rows="3"
                                ></textarea>
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-status"
                                    class="form-label"
                                    >Estado del caso</label
                                >
                                <select
                                    id="psi-case-edit-status"
                                    v-model="caseEdit.status"
                                    class="form-select"
                                >
                                    <option value="open">Abierto</option>
                                    <option value="assessment">
                                        Evaluación
                                    </option>
                                    <option value="active_intervention">
                                        Intervención activa
                                    </option>
                                    <option value="monitoring">
                                        Seguimiento
                                    </option>
                                    <option value="awaiting_information">
                                        Esperando antecedentes
                                    </option>
                                    <option value="awaiting_external_response">
                                        Esperando red externa
                                    </option>
                                    <option value="paused">Pausado</option>
                                    <option value="externally_referred">
                                        Derivación externa
                                    </option>
                                    <option value="closure_pending">
                                        Cierre pendiente
                                    </option>
                                    <option value="reopened">Reabierto</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-priority"
                                    class="form-label"
                                    >Prioridad</label
                                >
                                <select
                                    id="psi-case-edit-priority"
                                    v-model="caseEdit.priority"
                                    class="form-select"
                                >
                                    <option value="low">Baja</option>
                                    <option value="medium">Media</option>
                                    <option value="high">Alta</option>
                                    <option value="critical">Crítica</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-confidentiality"
                                    class="form-label"
                                    >Confidencialidad</label
                                >
                                <select
                                    id="psi-case-edit-confidentiality"
                                    v-model="caseEdit.confidentiality"
                                    class="form-select"
                                >
                                    <option value="private_psychology">
                                        Privada de Psicología
                                    </option>
                                    <option value="psychology_team">
                                        Equipo de Psicología
                                    </option>
                                    <option value="interdisciplinary_team">
                                        Equipo interdisciplinario
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-next-action"
                                    class="form-label"
                                    >Próxima acción</label
                                >
                                <input
                                    id="psi-case-edit-next-action"
                                    v-model="caseEdit.next_action"
                                    class="form-control"
                                    maxlength="2000"
                                />
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-next-review"
                                    class="form-label"
                                    >Próxima revisión</label
                                >
                                <input
                                    id="psi-case-edit-next-review"
                                    v-model="caseEdit.next_review_on"
                                    type="date"
                                    class="form-control"
                                />
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-guardian-status"
                                    class="form-label"
                                    >Información al apoderado</label
                                >
                                <select
                                    id="psi-case-edit-guardian-status"
                                    v-model="
                                        caseEdit.guardian_information_status
                                    "
                                    class="form-select"
                                >
                                    <option value="pending">
                                        Pendiente de informar
                                    </option>
                                    <option value="informed">
                                        Apoderado informado
                                    </option>
                                    <option value="not_required">
                                        No requerido
                                    </option>
                                    <option value="unable_to_contact">
                                        No fue posible contactar
                                    </option>
                                    <option value="institutional_exception">
                                        Excepción institucional
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    for="psi-case-edit-categories"
                                    class="form-label"
                                    >Categorías o etiquetas</label
                                >
                                <input
                                    id="psi-case-edit-categories"
                                    v-model="caseEdit.categories"
                                    class="form-control"
                                    placeholder="Ej.: convivencia, bienestar"
                                />
                            </div>
                            <div class="wide psi-change-reason-field">
                                <label
                                    for="psi-case-edit-change-reason"
                                    class="form-label"
                                    >Motivo de la actualización
                                    <span>*</span></label
                                >
                                <textarea
                                    id="psi-case-edit-change-reason"
                                    v-model="caseEdit.change_reason"
                                    class="form-control"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Ej.: corrección de antecedente o actualización acordada"
                                ></textarea>
                                <small>
                                    Este motivo quedará en la trazabilidad del
                                    expediente; el contenido sensible no se
                                    copiará a la auditoría.
                                </small>
                            </div>
                        </div>
                        <template #footer>
                            <button
                                type="button"
                                class="btn btn-light"
                                @click="closeForm"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="
                                    !caseEdit.general_reason.trim() ||
                                    caseEdit.change_reason.trim().length < 5 ||
                                    api.loading.value
                                "
                                @click="saveCaseChanges"
                            >
                                <i class="bx bx-save me-1"></i>
                                {{
                                    api.loading.value
                                        ? "Guardando…"
                                        : "Guardar cambios"
                                }}
                            </button>
                        </template>
                    </PsychologyModal>
                    <PsychologyModal
                        v-if="formModal === 'reassign'"
                        eyebrow="Gestión del equipo"
                        title="Reasignar caso"
                        @close="closeForm"
                    >
                        <label class="form-label"
                            >Nueva profesional responsable</label
                        >
                        <select
                            v-model="reassignment.user_id"
                            class="form-select mb-3"
                        >
                            <option :value="null">
                                Selecciona una profesional
                            </option>
                            <option
                                v-for="person in catalogs.professionals"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{ person.name }}
                            </option>
                        </select>
                        <label class="form-label"
                            >Motivo de la reasignación</label
                        >
                        <textarea
                            v-model="reassignment.reason"
                            class="form-control"
                            rows="3"
                        ></textarea>
                        <template #footer>
                            <button class="btn btn-light" @click="closeForm">
                                Cancelar
                            </button>
                            <button
                                class="btn btn-primary"
                                :disabled="
                                    !reassignment.user_id ||
                                    !reassignment.reason ||
                                    api.loading.value
                                "
                                @click="reassignCase"
                            >
                                Confirmar reasignación
                            </button>
                        </template>
                    </PsychologyModal>
                </section>
                <section v-else-if="activeTab === 'plans'" class="psi-section">
                    <div class="psi-section-head">
                        <div>
                            <h5>Planes de intervención</h5>
                            <p>Versiones y próximas revisiones del caso.</p>
                        </div>
                        <button
                            v-if="catalogs.capabilities.create_activity"
                            class="btn btn-primary"
                            @click="openForm('plan')"
                        >
                            <i class="bx bx-plus me-1"></i>Nuevo plan
                        </button>
                    </div>
                    <div class="row g-3">
                        <div
                            v-if="
                                catalogs.capabilities.create_activity &&
                                formModal === 'plan'
                            "
                            class="psi-form-modal"
                            @click.self="closeForm"
                        >
                            <div class="card psi-modal-card">
                                <div class="card-body">
                                    <div class="psi-modal-heading">
                                        <div>
                                            <span>Plan confidencial</span>
                                            <h5>Nuevo plan versionado</h5>
                                        </div>
                                        <button
                                            class="psi-close"
                                            type="button"
                                            @click="closeForm"
                                        >
                                            <i class="bx bx-x"></i>
                                        </button>
                                    </div>
                                    <textarea
                                        v-model="plan.general_situation"
                                        class="form-control mb-2"
                                        rows="2"
                                        placeholder="Situación general"
                                    ></textarea
                                    ><textarea
                                        v-model="plan.general_objective"
                                        class="form-control mb-2"
                                        rows="2"
                                        placeholder="Objetivo general"
                                    ></textarea
                                    ><textarea
                                        v-model="plan.specific_objectives"
                                        class="form-control mb-2"
                                        rows="2"
                                        placeholder="Objetivos específicos"
                                    ></textarea
                                    ><textarea
                                        v-model="plan.planned_actions"
                                        class="form-control mb-2"
                                        rows="3"
                                        placeholder="Acciones planificadas"
                                    ></textarea>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input
                                                v-model="plan.responsibles"
                                                class="form-control"
                                                placeholder="Responsables"
                                            />
                                        </div>
                                        <div class="col-6">
                                            <input
                                                v-model="plan.frequency"
                                                class="form-control"
                                                placeholder="Frecuencia"
                                            />
                                        </div>
                                        <div class="col-6">
                                            <input
                                                v-model="
                                                    plan.estimated_start_on
                                                "
                                                type="date"
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-6">
                                            <input
                                                v-model="plan.review_on"
                                                type="date"
                                                class="form-control"
                                            />
                                        </div>
                                    </div>
                                    <textarea
                                        v-model="plan.monitoring_indicators"
                                        class="form-control my-2"
                                        rows="2"
                                        placeholder="Indicadores de seguimiento"
                                    ></textarea
                                    ><button
                                        class="btn btn-primary w-100"
                                        @click="savePlan"
                                    >
                                        Crear plan
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="psi-table-card table-responsive">
                                <table
                                    class="table table-hover align-middle mb-0"
                                >
                                    <thead>
                                        <tr>
                                            <th>Versión</th>
                                            <th>Objetivo general</th>
                                            <th>Revisión</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in selected.plans || []"
                                            :key="item.id"
                                        >
                                            <td>
                                                <strong
                                                    >Plan v{{
                                                        item.current_version
                                                    }}</strong
                                                >
                                            </td>
                                            <td>
                                                {{
                                                    item.versions?.[0]
                                                        ?.general_objective ||
                                                    "—"
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    item.review_on ||
                                                    "Sin fecha"
                                                }}
                                            </td>
                                            <td>
                                                <PsychologyBadge
                                                    :value="item.status"
                                                />
                                            </td>
                                            <td class="text-end">
                                                <div
                                                    class="psi-record-row-actions"
                                                >
                                                    <button
                                                        type="button"
                                                        class="psi-record-icon-button"
                                                        title="Ver plan"
                                                        aria-label="Ver plan"
                                                        @click="
                                                            selectedPlan = item
                                                        "
                                                    >
                                                        <i
                                                            class="bx bx-show"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="psi-record-icon-button is-pdf"
                                                        title="Exportar plan en PDF"
                                                        aria-label="Exportar plan en PDF"
                                                        :disabled="
                                                            exportingPlanPdf
                                                        "
                                                        @click="
                                                            exportPlanPdf(item)
                                                        "
                                                    >
                                                        <i
                                                            class="bx bxs-file-pdf"
                                                        ></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!selected.plans?.length">
                                            <td colspan="5" class="psi-empty">
                                                No hay planes registrados.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'activities'"
                    class="psi-section"
                >
                    <div class="psi-section-head">
                        <div>
                            <h5>Atenciones y entrevistas</h5>
                            <p>
                                Registro cronológico de actuaciones
                                profesionales.
                            </p>
                        </div>
                        <button
                            v-if="catalogs.capabilities.create_activity"
                            class="btn btn-primary"
                            @click="openActivityForm"
                        >
                            <i class="bx bx-plus me-1"></i>Nueva atención
                        </button>
                    </div>
                    <div class="row g-3">
                        <div
                            v-if="
                                catalogs.capabilities.create_activity &&
                                formModal === 'activity'
                            "
                            class="psi-form-modal"
                        >
                            <div class="card psi-modal-card psi-modal-card-xl">
                                <div class="card-body">
                                    <div class="psi-modal-heading mb-4">
                                        <div>
                                            <span>Registro confidencial</span>
                                            <h5 class="mb-1">
                                                {{
                                                    activity.id
                                                        ? "Editar ficha de atención"
                                                        : "Nueva atención"
                                                }}
                                            </h5>
                                            <p class="text-muted small mb-0">
                                                Registro de entrevista, acuerdos
                                                y seguimiento. Los campos
                                                sensibles se almacenan cifrados.
                                            </p>
                                        </div>
                                        <button
                                            class="psi-close"
                                            type="button"
                                            @click="closeForm"
                                        >
                                            <i class="bx bx-x"></i>
                                        </button>
                                    </div>

                                    <div
                                        v-if="api.error.value"
                                        class="alert alert-danger psi-form-error"
                                        role="alert"
                                    >
                                        <i class="bx bx-error-circle"></i>
                                        <div>
                                            <strong
                                                >No se pudo guardar la
                                                atención</strong
                                            >
                                            <span>{{ api.error.value }}</span>
                                        </div>
                                    </div>

                                    <div class="psi-form-section">
                                        <h6>Identificación de la atención</h6>
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <label class="form-label"
                                                    >Tipo de actividad</label
                                                >
                                                <select
                                                    v-model="activity.type"
                                                    class="form-select"
                                                >
                                                    <option
                                                        v-for="item in catalogs
                                                            .catalogs
                                                            .activity_type ||
                                                        []"
                                                        :key="item.id"
                                                        :value="item.slug"
                                                    >
                                                        {{ item.name }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <label class="form-label"
                                                    >Fecha de la atención</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.activity_on
                                                    "
                                                    type="date"
                                                    class="form-control"
                                                    required
                                                />
                                                <small class="psi-date-help"
                                                    >Puedes registrar una fecha
                                                    histórica, aunque sea
                                                    anterior a la apertura del
                                                    caso.</small
                                                >
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <label class="form-label"
                                                    >Entrevista N°</label
                                                >
                                                <input
                                                    v-model.number="
                                                        activity.interview_number
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="9999"
                                                    class="form-control"
                                                    placeholder="Automático"
                                                />
                                            </div>
                                            <div class="col-lg-6">
                                                <div
                                                    class="psi-device-time-panel"
                                                >
                                                    <i
                                                        class="bx bx-time-five"
                                                    ></i>
                                                    <div>
                                                        <small
                                                            >Inicio
                                                            automático</small
                                                        >
                                                        <strong>{{
                                                            activity.starts_at
                                                        }}</strong>
                                                    </div>
                                                    <span></span>
                                                    <div>
                                                        <small
                                                            >Término
                                                            automático</small
                                                        >
                                                        <strong>{{
                                                            activity.ends_at ||
                                                            "Al guardar"
                                                        }}</strong>
                                                    </div>
                                                    <em
                                                        >Hora del
                                                        dispositivo</em
                                                    >
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <label class="form-label"
                                                    >Modalidad</label
                                                >
                                                <select
                                                    v-model="activity.modality"
                                                    class="form-select"
                                                >
                                                    <option value="presencial">
                                                        Presencial
                                                    </option>
                                                    <option value="remota">
                                                        Remota
                                                    </option>
                                                    <option value="telefonica">
                                                        Telefónica
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-lg-3 col-md-6">
                                                <label class="form-label"
                                                    >Lugar</label
                                                >
                                                <input
                                                    v-model="activity.location"
                                                    class="form-control"
                                                    placeholder="Ej. oficina"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="psi-form-section">
                                        <h6>Personas participantes</h6>
                                        <p class="psi-form-section-copy">
                                            Datos disponibles en la ficha del
                                            estudiante para evitar digitación
                                            duplicada.
                                        </p>
                                        <div class="psi-person-context-grid">
                                            <article>
                                                <i class="bx bx-user"></i>
                                                <span>
                                                    <small>Estudiante</small>
                                                    <strong>{{
                                                        selected.student?.name
                                                    }}</strong>
                                                    <em>
                                                        {{
                                                            selected.student
                                                                ?.course ||
                                                            "Curso no informado"
                                                        }}
                                                        · RUT
                                                        {{
                                                            selected.student
                                                                ?.rut ||
                                                            "no registrado"
                                                        }}
                                                    </em>
                                                </span>
                                            </article>
                                            <article>
                                                <i class="bx bx-group"></i>
                                                <span>
                                                    <small>Apoderado(a)</small>
                                                    <strong>{{
                                                        selected.student
                                                            ?.guardian_name ||
                                                        "Sin apoderado registrado"
                                                    }}</strong>
                                                    <em>
                                                        {{
                                                            selected.student
                                                                ?.guardian_relationship ||
                                                            "Relación no informada"
                                                        }}
                                                        · RUT
                                                        {{
                                                            selected.student
                                                                ?.guardian_rut ||
                                                            "no registrado"
                                                        }}
                                                    </em>
                                                    <em
                                                        v-if="
                                                            selected.student
                                                                ?.guardian_phone ||
                                                            selected.student
                                                                ?.guardian_email
                                                        "
                                                    >
                                                        {{
                                                            selected.student
                                                                ?.guardian_phone ||
                                                            "Sin teléfono"
                                                        }}
                                                        ·
                                                        {{
                                                            selected.student
                                                                ?.guardian_email ||
                                                            "Sin correo"
                                                        }}
                                                    </em>
                                                </span>
                                            </article>
                                        </div>
                                        <label class="form-label mt-3"
                                            >Persona entrevistada
                                            principal</label
                                        >
                                        <div class="d-flex flex-wrap gap-2">
                                            <button
                                                v-for="option in participantTypeOptions"
                                                :key="option[0]"
                                                type="button"
                                                class="psi-primary-person"
                                                :class="{
                                                    active:
                                                        activity.interviewee_type ===
                                                        option[0],
                                                }"
                                                :disabled="
                                                    option[0] === 'guardian' &&
                                                    !selected.student
                                                        ?.guardian_name
                                                "
                                                @click="
                                                    selectPrimaryInterviewee(
                                                        option[0]
                                                    )
                                                "
                                            >
                                                <i
                                                    :class="
                                                        activity.interviewee_type ===
                                                        option[0]
                                                            ? 'bx bx-radio-circle-marked'
                                                            : 'bx bx-radio-circle'
                                                    "
                                                ></i>
                                                {{ option[1] }}
                                            </button>
                                        </div>
                                        <label class="form-label mt-3"
                                            >Participantes adicionales
                                            <small>(opcional)</small></label
                                        >
                                        <div
                                            class="d-flex flex-wrap gap-2 mb-3"
                                        >
                                            <label
                                                v-for="option in additionalParticipantOptions"
                                                :key="option[0]"
                                                class="psi-check"
                                            >
                                                <input
                                                    v-model="
                                                        activity.participant_types
                                                    "
                                                    type="checkbox"
                                                    :value="option[0]"
                                                />
                                                <span>{{ option[1] }}</span>
                                            </label>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label"
                                                    >Nombre entrevistado</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.interviewee_name
                                                    "
                                                    class="form-control"
                                                    :readonly="
                                                        [
                                                            'student',
                                                            'guardian',
                                                        ].includes(
                                                            activity.interviewee_type
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label"
                                                    >RUT entrevistado</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.interviewee_rut
                                                    "
                                                    class="form-control psi-private"
                                                    :readonly="
                                                        [
                                                            'student',
                                                            'guardian',
                                                        ].includes(
                                                            activity.interviewee_type
                                                        )
                                                    "
                                                    placeholder="Sin RUT registrado"
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Cargo de quien
                                                    entrevista</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.interviewer_position_snapshot
                                                    "
                                                    class="form-control"
                                                    placeholder="Se completa desde el perfil si se deja vacío"
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Otros participantes</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.participants
                                                    "
                                                    class="form-control"
                                                    placeholder="Nombres y relación con el caso"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="psi-form-section">
                                        <h6>Registro profesional</h6>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label"
                                                    >Motivo u objetivo</label
                                                >
                                                <textarea
                                                    v-model="activity.objective"
                                                    class="form-control"
                                                    rows="2"
                                                    placeholder="Propósito de la atención"
                                                ></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"
                                                    >Resumen
                                                    institucional</label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.institutional_summary
                                                    "
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Resumen institucional compartible"
                                                ></textarea>
                                            </div>
                                            <div
                                                v-if="
                                                    catalogs.capabilities
                                                        .view_private
                                                "
                                                class="col-12"
                                            >
                                                <label class="form-label"
                                                    ><i
                                                        class="bx bx-lock-alt me-1"
                                                    ></i
                                                    >Antecedentes generales
                                                    (cifrado)</label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.general_background
                                                    "
                                                    class="form-control psi-private"
                                                    rows="4"
                                                    placeholder="Contexto relevante para la intervención"
                                                ></textarea>
                                            </div>
                                            <div
                                                v-if="
                                                    catalogs.capabilities
                                                        .view_private
                                                "
                                                class="col-12"
                                            >
                                                <label class="form-label"
                                                    ><i
                                                        class="bx bx-lock-alt me-1"
                                                    ></i
                                                    >Nota profesional privada
                                                    (cifrada)</label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.private_note
                                                    "
                                                    class="form-control psi-private"
                                                    rows="4"
                                                    placeholder="Nota profesional privada (cifrada)"
                                                ></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Resultado</label
                                                >
                                                <textarea
                                                    v-model="activity.result"
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Resultado de la atención"
                                                ></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Acuerdos</label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.agreements
                                                    "
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Acuerdos y tareas"
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="psi-form-section">
                                        <h6>Seguimiento</h6>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div
                                                    class="psi-followup-form-hint"
                                                >
                                                    <i
                                                        class="bx bx-calendar-plus"
                                                    ></i>
                                                    <span>
                                                        <strong
                                                            >Activar seguimiento
                                                            desde esta
                                                            atención</strong
                                                        >
                                                        Selecciona el tipo y una
                                                        única fecha. El caso
                                                        aparecerá
                                                        automáticamente en
                                                        Seguimientos y en la
                                                        Agenda profesional.
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Tipo de seguimiento</label
                                                >
                                                <select
                                                    v-model="
                                                        activity.follow_up_type
                                                    "
                                                    class="form-select"
                                                    @change="
                                                        handleFollowUpTypeChange
                                                    "
                                                >
                                                    <option value="">
                                                        Sin seguimiento
                                                    </option>
                                                    <option
                                                        v-for="[
                                                            value,
                                                            label,
                                                        ] in followUpTypeOptions"
                                                        :key="value"
                                                        :value="value"
                                                    >
                                                        {{ label }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Fecha de seguimiento</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.next_action_on
                                                    "
                                                    type="date"
                                                    class="form-control"
                                                    :min="activity.activity_on"
                                                    :disabled="
                                                        !activity.follow_up_type
                                                    "
                                                />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"
                                                    >Detalle del seguimiento
                                                    <small
                                                        >(opcional)</small
                                                    ></label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.next_steps
                                                    "
                                                    class="form-control"
                                                    rows="2"
                                                    placeholder="Indicaciones o contexto breve para la próxima acción"
                                                    :disabled="
                                                        !activity.follow_up_type
                                                    "
                                                ></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"
                                                    >Retroalimentación al área
                                                    derivante</label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.referral_feedback
                                                    "
                                                    class="form-control"
                                                    rows="2"
                                                    placeholder="Retroalimentación para área derivante"
                                                ></textarea>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Asistencia</label
                                                >
                                                <select
                                                    v-model="
                                                        activity.attendance_status
                                                    "
                                                    class="form-select"
                                                >
                                                    <option value="realizada">
                                                        Realizada
                                                    </option>
                                                    <option value="scheduled">
                                                        Programada
                                                    </option>
                                                    <option value="absent">
                                                        Inasistencia
                                                    </option>
                                                    <option value="cancelled">
                                                        Cancelada
                                                    </option>
                                                    <option value="rescheduled">
                                                        Reprogramada
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Visibilidad</label
                                                >
                                                <select
                                                    v-model="
                                                        activity.visibility
                                                    "
                                                    class="form-select"
                                                >
                                                    <option
                                                        value="private_psychology"
                                                    >
                                                        Privada de Psicología
                                                    </option>
                                                    <option
                                                        value="psychology_team"
                                                    >
                                                        Equipo de Psicología
                                                    </option>
                                                    <option
                                                        value="interdisciplinary_team"
                                                    >
                                                        Equipo
                                                        interdisciplinario
                                                    </option>
                                                    <option
                                                        value="referral_feedback"
                                                    >
                                                        Retroalimentación
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="!activity.id"
                                        class="psi-form-section psi-coordination-inline"
                                    >
                                        <div class="psi-coordination-toggle">
                                            <div
                                                class="psi-coordination-toggle__icon"
                                            >
                                                <i
                                                    class="bx bx-conversation"
                                                ></i>
                                            </div>
                                            <div>
                                                <h6>
                                                    Requerir coordinación
                                                    institucional
                                                </h6>
                                                <p>
                                                    Envía una solicitud formal a
                                                    otro funcionario junto con
                                                    esta atención.
                                                </p>
                                            </div>
                                            <label
                                                class="form-check form-switch ms-auto mb-0"
                                            >
                                                <input
                                                    v-model="
                                                        activity.coordination_enabled
                                                    "
                                                    class="form-check-input"
                                                    type="checkbox"
                                                />
                                            </label>
                                        </div>
                                        <div
                                            v-if="activity.coordination_enabled"
                                            class="row g-3 mt-1"
                                        >
                                            <div class="col-md-6">
                                                <label class="form-label"
                                                    >Funcionario
                                                    solicitado</label
                                                >
                                                <select
                                                    v-model="
                                                        activity.coordination
                                                            .recipient_user_id
                                                    "
                                                    class="form-select"
                                                >
                                                    <option
                                                        :value="null"
                                                        disabled
                                                    >
                                                        Selecciona un
                                                        funcionario
                                                    </option>
                                                    <option
                                                        v-for="recipient in catalogs.coordination_recipients ||
                                                        []"
                                                        :key="recipient.id"
                                                        :value="recipient.id"
                                                    >
                                                        {{ recipient.name }} ·
                                                        {{
                                                            recipient.position ||
                                                            "Sin cargo informado"
                                                        }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"
                                                    >Tipo</label
                                                >
                                                <select
                                                    v-model="
                                                        activity.coordination
                                                            .coordination_type
                                                    "
                                                    class="form-select"
                                                >
                                                    <option
                                                        v-for="[
                                                            value,
                                                            label,
                                                        ] in coordinationTypeOptions"
                                                        :key="value"
                                                        :value="value"
                                                    >
                                                        {{ label }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"
                                                    >Fecha propuesta
                                                    <small
                                                        >(opcional)</small
                                                    ></label
                                                >
                                                <input
                                                    v-model="
                                                        activity.coordination
                                                            .requested_for
                                                    "
                                                    type="date"
                                                    class="form-control"
                                                    :min="activity.activity_on"
                                                />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"
                                                    >Asunto</label
                                                >
                                                <input
                                                    v-model="
                                                        activity.coordination
                                                            .subject
                                                    "
                                                    class="form-control"
                                                    maxlength="191"
                                                    placeholder="Ej.: Solicitud de antecedentes pedagógicos"
                                                />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label"
                                                    >Requerimiento</label
                                                >
                                                <textarea
                                                    v-model="
                                                        activity.coordination
                                                            .request_message
                                                    "
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Describe qué coordinación o respuesta necesitas. No incluyas información clínica innecesaria."
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="activity.id"
                                        class="psi-form-section psi-correction-reason"
                                    >
                                        <h6>Motivo de la corrección</h6>
                                        <p>
                                            Se conservará una copia cifrada de la
                                            versión anterior junto con este motivo.
                                        </p>
                                        <textarea
                                            v-model="activity.change_reason"
                                            class="form-control"
                                            rows="2"
                                            minlength="5"
                                            maxlength="1000"
                                            required
                                            placeholder="Ej.: Corrección de fecha y acuerdos según revisión del acta."
                                        ></textarea>
                                    </div>

                                    <div class="psi-form-actions">
                                        <button
                                            type="button"
                                            class="btn btn-light"
                                            :disabled="api.loading.value"
                                            @click="closeForm"
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            v-if="!activity.id || activity.status === 'draft'"
                                            type="button"
                                            class="btn btn-outline-primary"
                                            :disabled="api.loading.value"
                                            @click="saveActivity(false)"
                                        >
                                            <i class="bx bx-save me-1"></i>
                                            Guardar borrador
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            :disabled="api.loading.value"
                                            @click="saveActivity(true)"
                                        >
                                            <i
                                                class="bx bx-check-circle me-1"
                                            ></i>
                                            {{
                                                api.loading.value
                                                    ? "Guardando…"
                                                    : activity.id
                                                      ? "Guardar corrección"
                                                      : "Finalizar atención"
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="psi-table-card table-responsive">
                                <table
                                    class="table table-hover align-middle mb-0"
                                >
                                    <thead>
                                        <tr>
                                            <th>Registro</th>
                                            <th>Entrevistado(a)</th>
                                            <th>Fecha</th>
                                            <th>Profesional</th>
                                            <th>Seguimiento</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in selected.activities ||
                                            []"
                                            :key="item.id"
                                        >
                                            <td>
                                                <strong>
                                                    {{
                                                        item.interview_number
                                                            ? `Entrevista N° ${item.interview_number}`
                                                            : catalogLabel(
                                                                  "activity_type",
                                                                  item.type
                                                              )
                                                    }}
                                                </strong>
                                                <small>{{
                                                    catalogLabel(
                                                        "activity_type",
                                                        item.type
                                                    )
                                                }}</small>
                                            </td>
                                            <td>
                                                <strong>{{
                                                    item.interviewee_name || "—"
                                                }}</strong>
                                                <small>{{
                                                    intervieweeTypeLabel(
                                                        item.interviewee_type
                                                    )
                                                }}</small>
                                            </td>
                                            <td>
                                                {{
                                                    formatDate(item.activity_on)
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    item.responsible_user
                                                        ?.name || "—"
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    item.next_action_on
                                                        ? `${followUpTypeLabel(
                                                              item.follow_up_type
                                                          )} · ${formatDate(
                                                              item.next_action_on
                                                          )}`
                                                        : "Sin seguimiento"
                                                }}
                                            </td>
                                            <td>
                                                <PsychologyBadge
                                                    :value="item.status"
                                                />
                                            </td>
                                            <td class="text-end">
                                                <div
                                                    class="psi-record-row-actions"
                                                >
                                                    <button
                                                        v-if="catalogs.capabilities.create_activity"
                                                        type="button"
                                                        class="psi-record-icon-button is-edit"
                                                        title="Editar ficha"
                                                        aria-label="Editar ficha"
                                                        @click="openActivityEdit(item)"
                                                    >
                                                        <i class="bx bx-edit-alt"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="psi-record-icon-button"
                                                        title="Ver atención"
                                                        aria-label="Ver atención"
                                                        @click="
                                                            selectedActivity =
                                                                item
                                                        "
                                                    >
                                                        <i
                                                            class="bx bx-show"
                                                        ></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="psi-record-icon-button is-pdf"
                                                        title="Exportar atención en PDF"
                                                        aria-label="Exportar atención en PDF"
                                                        :disabled="
                                                            exportingActivityPdf
                                                        "
                                                        @click="
                                                            exportSelectedActivityPdf(
                                                                item
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="bx bxs-file-pdf"
                                                        ></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!selected.activities?.length">
                                            <td colspan="7" class="psi-empty">
                                                No hay actividades registradas.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'referrals'"
                    class="psi-section"
                >
                    <article
                        v-for="item in selected.referrals || []"
                        :key="item.id"
                        class="card mb-2"
                    >
                        <div class="card-body d-flex justify-content-between">
                            <div>
                                <strong>{{ item.code }}</strong>
                                <p class="mb-0">{{ item.primary_reason }}</p>
                            </div>
                            <PsychologyBadge :value="item.status" />
                        </div>
                    </article>
                    <div v-if="!selected.referrals?.length" class="psi-empty">
                        No hay derivaciones asociadas.
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'followups'"
                    class="psi-section"
                >
                    <div class="psi-followup-workspace">
                        <header class="psi-followup-head">
                            <div class="psi-followup-title">
                                <span
                                    ><i class="bx bx-calendar-check"></i
                                ></span>
                                <div>
                                    <small>Cartera profesional</small>
                                    <h5>Agenda de seguimientos</h5>
                                    <p>
                                        Casos visibles que tienen un seguimiento
                                        registrado desde una ficha de atención.
                                    </p>
                                </div>
                            </div>
                            <div class="psi-followup-head-meta">
                                <span class="psi-security-chip">
                                    <i class="bx bx-lock-alt"></i>
                                    Acceso según cartera
                                </span>
                                <strong>{{ followupRows.total || 0 }}</strong>
                                <small>seguimientos</small>
                            </div>
                        </header>

                        <div class="psi-followup-filters">
                            <div class="psi-search-field">
                                <i class="bx bx-search"></i>
                                <input
                                    v-model="followupFilters.search"
                                    class="form-control"
                                    placeholder="Buscar caso, estudiante o seguimiento"
                                />
                            </div>
                            <select
                                v-model="followupFilters.status"
                                class="form-select"
                            >
                                <option value="">Todos los estados</option>
                                <option value="pending">Pendiente</option>
                                <option value="overdue">Vencido</option>
                                <option value="completed">Completado</option>
                            </select>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                :disabled="followupLoading"
                                @click="refreshFollowUps"
                            >
                                <i class="bx bx-refresh"></i>
                                Actualizar
                            </button>
                        </div>

                        <section class="psi-followup-panel">
                            <div class="psi-followup-panel-head">
                                <div>
                                    <small>Vista mensual</small>
                                    <h6>Calendario de compromisos</h6>
                                </div>
                                <span>
                                    Selecciona un evento para abrir su ficha
                                </span>
                            </div>
                            <div class="psi-followup-calendar">
                                <FullCalendar
                                    :key="followupCalendarKey"
                                    :options="followupCalendarOptions"
                                />
                            </div>
                        </section>

                        <section class="psi-followup-panel">
                            <div class="psi-followup-panel-head">
                                <div>
                                    <small>Vista detallada</small>
                                    <h6>Tabla de seguimientos</h6>
                                </div>
                                <span>
                                    Ordenados por la próxima fecha comprometida
                                </span>
                            </div>
                            <div
                                v-if="followupLoading"
                                class="psi-followup-loading"
                            >
                                <span
                                    class="spinner-border spinner-border-sm"
                                ></span>
                                Actualizando seguimientos…
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Caso / estudiante</th>
                                            <th>Seguimiento</th>
                                            <th>Responsable</th>
                                            <th>Estado</th>
                                            <th class="text-end">Ficha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in followupRows.data ||
                                            []"
                                            :key="item.id"
                                            :class="{
                                                'is-current-case':
                                                    item.case_id ===
                                                    selected.id,
                                            }"
                                        >
                                            <td>
                                                <strong>{{
                                                    item.due_at
                                                        ? formatDateTime(
                                                              item.due_at
                                                          )
                                                        : "Sin fecha"
                                                }}</strong>
                                                <small
                                                    v-if="item.is_overdue"
                                                    class="psi-overdue-label"
                                                >
                                                    Vencido
                                                </small>
                                            </td>
                                            <td>
                                                <strong>{{
                                                    item.case_code
                                                }}</strong>
                                                <small>
                                                    {{ item.student_name }} ·
                                                    {{
                                                        item.course ||
                                                        "Sin curso"
                                                    }}
                                                </small>
                                            </td>
                                            <td>
                                                <strong>{{
                                                    item.title
                                                }}</strong>
                                                <small>{{
                                                    item.description ||
                                                    "Sin detalle adicional"
                                                }}</small>
                                            </td>
                                            <td>
                                                {{
                                                    item.responsible_name ||
                                                    item.case_responsible_name ||
                                                    "Sin responsable"
                                                }}
                                            </td>
                                            <td>
                                                <PsychologyBadge
                                                    :value="item.status"
                                                />
                                            </td>
                                            <td class="text-end">
                                                <button
                                                    type="button"
                                                    class="psi-record-icon-button"
                                                    title="Abrir ficha del caso"
                                                    aria-label="Abrir ficha del caso"
                                                    @click="
                                                        openFollowUpCase(item)
                                                    "
                                                >
                                                    <i class="bx bx-show"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!followupRows.data?.length">
                                            <td colspan="6" class="psi-empty">
                                                No hay seguimientos con estos
                                                filtros.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-if="followupRows.last_page > 1"
                                class="psi-pagination"
                            >
                                <span>
                                    Página {{ followupRows.current_page }} de
                                    {{ followupRows.last_page }}
                                </span>
                                <div>
                                    <button
                                        class="btn btn-sm btn-light"
                                        :disabled="
                                            followupRows.current_page <= 1
                                        "
                                        @click="
                                            loadFollowUps(
                                                followupRows.current_page - 1
                                            )
                                        "
                                    >
                                        Anterior
                                    </button>
                                    <button
                                        class="btn btn-sm btn-light"
                                        :disabled="
                                            followupRows.current_page >=
                                            followupRows.last_page
                                        "
                                        @click="
                                            loadFollowUps(
                                                followupRows.current_page + 1
                                            )
                                        "
                                    >
                                        Siguiente
                                    </button>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'coordination'"
                    class="psi-section psi-coordination-workspace"
                >
                    <div class="psi-section-head">
                        <div>
                            <span class="psi-eyebrow"
                                >TRABAJO COLABORATIVO</span
                            >
                            <h5>Solicitudes de coordinación</h5>
                            <p>
                                Requerimientos enviados a otros funcionarios y
                                sus respuestas institucionales.
                            </p>
                        </div>
                        <button
                            v-if="catalogs.capabilities.create_activity"
                            class="btn btn-primary"
                            @click="openCoordinationForm"
                        >
                            <i class="bx bx-conversation me-1"></i>Nueva
                            solicitud
                        </button>
                    </div>

                    <div class="psi-coordination-metrics">
                        <div>
                            <span class="psi-status-dot pending"></span>
                            <strong>{{ coordinationCount("pending") }}</strong>
                            <small>Pendientes</small>
                        </div>
                        <div>
                            <span class="psi-status-dot accepted"></span>
                            <strong>{{ coordinationCount("accepted") }}</strong>
                            <small>Aceptadas</small>
                        </div>
                        <div>
                            <span class="psi-status-dot rejected"></span>
                            <strong>{{ coordinationCount("rejected") }}</strong>
                            <small>Rechazadas</small>
                        </div>
                    </div>

                    <div
                        v-if="formModal === 'coordination'"
                        class="psi-coordination-compose"
                    >
                        <div class="psi-coordination-compose__head">
                            <div>
                                <span>NUEVO REQUERIMIENTO</span>
                                <h6>Solicitar coordinación a un funcionario</h6>
                            </div>
                            <button
                                class="btn-close"
                                type="button"
                                @click="closeForm"
                            ></button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label"
                                    >Funcionario solicitado</label
                                >
                                <select
                                    v-model="coordination.recipient_user_id"
                                    class="form-select"
                                >
                                    <option :value="null" disabled>
                                        Selecciona un funcionario
                                    </option>
                                    <option
                                        v-for="recipient in catalogs.coordination_recipients ||
                                        []"
                                        :key="recipient.id"
                                        :value="recipient.id"
                                    >
                                        {{ recipient.name }} ·
                                        {{
                                            recipient.position ||
                                            "Sin cargo informado"
                                        }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label"
                                    >Tipo de coordinación</label
                                >
                                <select
                                    v-model="coordination.coordination_type"
                                    class="form-select"
                                >
                                    <option
                                        v-for="[
                                            value,
                                            label,
                                        ] in coordinationTypeOptions"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Asunto</label>
                                <input
                                    v-model="coordination.subject"
                                    class="form-control"
                                    maxlength="191"
                                    placeholder="Asunto breve y accionable"
                                />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"
                                    >Fecha propuesta
                                    <small>(opcional)</small></label
                                >
                                <input
                                    v-model="coordination.requested_for"
                                    type="date"
                                    class="form-control"
                                />
                                <small class="psi-date-help"
                                    >También admite fechas históricas para
                                    coordinaciones ya realizadas.</small
                                >
                            </div>
                            <div class="col-12">
                                <label class="form-label">Solicitud</label>
                                <textarea
                                    v-model="coordination.request_message"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Explica qué necesitas coordinar, evitando antecedentes sensibles innecesarios."
                                ></textarea>
                            </div>
                        </div>
                        <div class="psi-form-actions mt-3">
                            <button
                                class="btn btn-light"
                                type="button"
                                @click="closeForm"
                            >
                                Cancelar
                            </button>
                            <button
                                class="btn btn-primary"
                                type="button"
                                :disabled="api.loading.value"
                                @click="saveCoordination"
                            >
                                <i class="bx bx-send me-1"></i>
                                {{
                                    api.loading.value
                                        ? "Enviando…"
                                        : "Enviar solicitud"
                                }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="coordinationRows.length"
                        class="psi-coordination-list"
                    >
                        <article
                            v-for="item in coordinationRows"
                            :key="item.id"
                            class="psi-coordination-card"
                        >
                            <div class="psi-coordination-card__rail"></div>
                            <div class="psi-coordination-card__main">
                                <div class="psi-coordination-card__top">
                                    <div>
                                        <span>{{
                                            coordinationTypeLabel(
                                                item.coordination_type
                                            )
                                        }}</span>
                                        <h6>{{ item.subject }}</h6>
                                    </div>
                                    <span
                                        class="psi-coordination-status"
                                        :class="
                                            coordinationStatus[item.status]?.[1]
                                        "
                                    >
                                        {{
                                            coordinationStatus[
                                                item.status
                                            ]?.[0] || item.status
                                        }}
                                    </span>
                                </div>
                                <p class="psi-coordination-message">
                                    {{ item.request_message }}
                                </p>
                                <div class="psi-coordination-people">
                                    <div>
                                        <i class="bx bx-user-pin"></i>
                                        <span>
                                            <small>Solicitado a</small>
                                            <strong>{{
                                                item.recipient?.name
                                            }}</strong>
                                            <em>{{
                                                coordinationRecipientPosition(
                                                    item.recipient_user_id
                                                )
                                            }}</em>
                                        </span>
                                    </div>
                                    <div>
                                        <i class="bx bx-calendar"></i>
                                        <span>
                                            <small>Fecha propuesta</small>
                                            <strong>{{
                                                item.requested_for
                                                    ? formatDate(
                                                          item.requested_for
                                                      )
                                                    : "Sin fecha propuesta"
                                            }}</strong>
                                            <em
                                                >Enviada por
                                                {{ item.requester?.name }}</em
                                            >
                                        </span>
                                    </div>
                                    <div v-if="item.activity_id">
                                        <i class="bx bx-notepad"></i>
                                        <span>
                                            <small>Origen</small>
                                            <strong>Ficha de atención</strong>
                                            <em
                                                >Solicitud vinculada al
                                                registro</em
                                            >
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="item.response_message"
                                    class="psi-coordination-response"
                                >
                                    <i class="bx bx-message-check"></i>
                                    <div>
                                        <small
                                            >Respuesta de
                                            {{
                                                item.responder?.name ||
                                                item.recipient?.name
                                            }}</small
                                        >
                                        <p>{{ item.response_message }}</p>
                                        <span>{{
                                            formatDateTime(item.responded_at)
                                        }}</span>
                                    </div>
                                </div>
                                <router-link
                                    v-if="item.can_respond"
                                    to="/mis-coordinaciones"
                                    class="btn btn-sm btn-outline-primary mt-3"
                                >
                                    <i
                                        class="bx bx-message-square-check me-1"
                                    ></i
                                    >Responder solicitud
                                </router-link>
                            </div>
                        </article>
                    </div>
                    <div v-else class="psi-coordination-empty">
                        <i class="bx bx-conversation"></i>
                        <h6>Aún no hay solicitudes</h6>
                        <p>
                            Puedes crear una aquí o generarla como requerimiento
                            al registrar una atención.
                        </p>
                    </div>
                </section>
                <section v-else-if="activeTab === 'risks'" class="psi-section">
                    <div class="psi-section-head">
                        <div>
                            <h5>Riesgos y medidas de resguardo</h5>
                            <p>
                                La clasificación interna no constituye un
                                diagnóstico.
                            </p>
                        </div>
                        <button
                            v-if="catalogs.capabilities.risk"
                            class="btn btn-primary"
                            @click="openForm('risk')"
                        >
                            <i class="bx bx-plus me-1"></i>Nueva evaluación
                        </button>
                    </div>
                    <div class="row g-3">
                        <div
                            v-if="
                                catalogs.capabilities.risk &&
                                formModal === 'risk'
                            "
                            class="psi-form-modal"
                            @click.self="closeForm"
                        >
                            <div class="card psi-modal-card">
                                <div class="card-body">
                                    <div class="psi-modal-heading">
                                        <div>
                                            <span>Evaluación confidencial</span>
                                            <h5>Registrar evaluación</h5>
                                        </div>
                                        <button
                                            class="psi-close"
                                            @click="closeForm"
                                        >
                                            <i class="bx bx-x"></i>
                                        </button>
                                    </div>
                                    <select
                                        v-model="risk.risk_type"
                                        class="form-select mb-2"
                                    >
                                        <option
                                            v-for="item in catalogs.catalogs
                                                .risk_type || []"
                                            :key="item.id"
                                            :value="item.slug"
                                        >
                                            {{ item.name }}
                                        </option></select
                                    ><select
                                        v-model="risk.level"
                                        class="form-select mb-2"
                                    >
                                        <option value="low">Baja</option>
                                        <option value="medium">Media</option>
                                        <option value="high">Alta</option>
                                        <option value="critical">
                                            Crítica
                                        </option></select
                                    ><textarea
                                        v-model="risk.structured_indicators"
                                        class="form-control mb-2"
                                        rows="2"
                                        placeholder="Indicadores estructurados"
                                    ></textarea
                                    ><textarea
                                        v-model="risk.professional_rationale"
                                        class="form-control psi-private mb-2"
                                        rows="3"
                                        placeholder="Fundamento profesional privado"
                                    ></textarea
                                    ><template v-if="risk.level === 'critical'">
                                        <textarea
                                            v-model="risk.immediate_action"
                                            class="form-control mb-2"
                                            required
                                            placeholder="Acción inmediata obligatoria"
                                        ></textarea
                                        ><select
                                            v-model="
                                                risk.response_responsible_user_id
                                            "
                                            class="form-select mb-2"
                                        >
                                            <option :value="null">
                                                Responsable de respuesta
                                            </option>
                                            <option
                                                v-for="p in catalogs.professionals"
                                                :key="p.id"
                                                :value="p.id"
                                            >
                                                {{ p.name }}
                                            </option></select
                                        ><input
                                            v-model="risk.response_at"
                                            type="datetime-local"
                                            class="form-control mb-2" /><input
                                            v-model="risk.protocol_reference"
                                            class="form-control mb-2"
                                            placeholder="Protocolo institucional vinculado" /></template
                                    ><button
                                        class="btn btn-primary w-100"
                                        @click="saveRisk"
                                    >
                                        Registrar evaluación
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="psi-table-card table-responsive">
                                <table
                                    class="table table-hover align-middle mb-0"
                                >
                                    <thead>
                                        <tr>
                                            <th>Evaluación</th>
                                            <th>Indicadores</th>
                                            <th>Acción inmediata</th>
                                            <th>Nivel</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in selected.risk_assessments ||
                                            []"
                                            :key="item.id"
                                        >
                                            <td>
                                                <strong>{{
                                                    item.risk_type
                                                }}</strong>
                                            </td>
                                            <td>
                                                {{
                                                    item.structured_indicators ||
                                                    "—"
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    item.immediate_action ||
                                                    "No requerida"
                                                }}
                                            </td>
                                            <td>
                                                <PsychologyBadge
                                                    :value="item.level"
                                                    priority
                                                />
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                !selected.risk_assessments
                                                    ?.length
                                            "
                                        >
                                            <td colspan="4" class="psi-empty">
                                                No hay evaluaciones registradas.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
                <section v-else-if="activeTab === 'tasks'" class="psi-section">
                    <div class="psi-section-head">
                        <div>
                            <h5>Tareas y compromisos</h5>
                            <p>
                                Responsables, vencimientos y evidencia de
                                cumplimiento.
                            </p>
                        </div>
                        <button
                            v-if="catalogs.capabilities.create_activity"
                            class="btn btn-primary"
                            @click="openForm('task')"
                        >
                            <i class="bx bx-plus me-1"></i>Nueva tarea
                        </button>
                    </div>
                    <div
                        v-if="
                            catalogs.capabilities.create_activity &&
                            formModal === 'task'
                        "
                        class="psi-form-modal"
                        @click.self="closeForm"
                    >
                        <div class="card psi-modal-card">
                            <div class="card-body">
                                <div class="psi-modal-heading">
                                    <div>
                                        <span>Compromiso trazable</span>
                                        <h5>Nueva tarea</h5>
                                    </div>
                                    <button
                                        class="psi-close"
                                        @click="closeForm"
                                    >
                                        <i class="bx bx-x"></i>
                                    </button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input
                                            v-model="task.title"
                                            class="form-control"
                                            placeholder="Título de la tarea"
                                        />
                                    </div>
                                    <div class="col-md-3">
                                        <select
                                            v-model="task.responsible_user_id"
                                            class="form-select"
                                        >
                                            <option :value="null">
                                                Responsable
                                            </option>
                                            <option
                                                v-for="p in catalogs.professionals"
                                                :key="p.id"
                                                :value="p.id"
                                            >
                                                {{ p.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input
                                            v-model="task.due_at"
                                            type="datetime-local"
                                            class="form-control"
                                        />
                                    </div>
                                    <div class="col-md-2">
                                        <select
                                            v-model="task.priority"
                                            class="form-select"
                                        >
                                            <option value="low">Baja</option>
                                            <option value="medium">
                                                Media
                                            </option>
                                            <option value="high">Alta</option>
                                            <option value="critical">
                                                Crítica
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button
                                            class="btn btn-primary w-100"
                                            @click="saveTask"
                                        >
                                            <i class="bx bx-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="psi-table-card table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tarea</th>
                                    <th>Responsable</th>
                                    <th>Vence</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in selected.tasks || []"
                                    :key="item.id"
                                >
                                    <td>{{ item.title }}</td>
                                    <td>{{ item.responsible_user?.name }}</td>
                                    <td>{{ formatDateTime(item.due_at) }}</td>
                                    <td>
                                        <PsychologyBadge :value="item.status" />
                                    </td>
                                    <td>
                                        <button
                                            v-if="item.status !== 'completed'"
                                            class="btn btn-sm btn-outline-success"
                                            @click="completeTask(item)"
                                        >
                                            Completar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'documents'"
                    class="psi-section"
                >
                    <div class="psi-section-head">
                        <div>
                            <h5>Documentos</h5>
                            <p>Archivos privados con descarga auditada.</p>
                        </div>
                        <button
                            v-if="catalogs.capabilities.documents_upload"
                            class="btn btn-primary"
                            @click="openForm('document')"
                        >
                            <i class="bx bx-upload me-1"></i>Adjuntar documento
                        </button>
                    </div>
                    <div class="row g-3">
                        <div
                            v-if="
                                catalogs.capabilities.documents_upload &&
                                formModal === 'document'
                            "
                            class="psi-form-modal"
                            @click.self="closeForm"
                        >
                            <div class="card psi-modal-card">
                                <div class="card-body">
                                    <div class="psi-modal-heading">
                                        <div>
                                            <span>Archivo confidencial</span>
                                            <h5>Adjuntar documento privado</h5>
                                        </div>
                                        <button
                                            class="psi-close"
                                            @click="closeForm"
                                        >
                                            <i class="bx bx-x"></i>
                                        </button>
                                    </div>
                                    <input
                                        type="file"
                                        class="form-control mb-2"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png"
                                        @change="
                                            documentForm.file =
                                                $event.target.files[0]
                                        "
                                    /><select
                                        v-model="documentForm.category"
                                        class="form-select mb-2"
                                    >
                                        <option value="background">
                                            Antecedente
                                        </option>
                                        <option value="consent">
                                            Consentimiento
                                        </option>
                                        <option value="report">Informe</option>
                                        <option value="protocol">
                                            Protocolo
                                        </option>
                                        <option value="other">
                                            Otro
                                        </option></select
                                    ><select
                                        v-model="documentForm.visibility"
                                        class="form-select mb-2"
                                    >
                                        <option value="private_psychology">
                                            Privado Psicología
                                        </option>
                                        <option value="psychology_team">
                                            Equipo de Psicología
                                        </option>
                                        <option value="interdisciplinary_team">
                                            Equipo interdisciplinario
                                        </option></select
                                    ><textarea
                                        v-model="documentForm.description"
                                        class="form-control mb-2"
                                        rows="2"
                                        placeholder="Descripción general, sin diagnóstico"
                                    ></textarea
                                    ><button
                                        class="btn btn-primary w-100"
                                        :disabled="!documentForm.file"
                                        @click="uploadDocument"
                                    >
                                        Subir de forma segura</button
                                    ><small class="d-block text-muted mt-2"
                                        >El archivo se guarda fuera del disco
                                        público; acceso y descarga quedan
                                        auditados.</small
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="psi-table-card table-responsive">
                                <table
                                    class="table table-hover align-middle mb-0"
                                >
                                    <thead>
                                        <tr>
                                            <th>Documento</th>
                                            <th>Categoría</th>
                                            <th>Visibilidad</th>
                                            <th>Tamaño</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="item in selected.documents ||
                                            []"
                                            :key="item.id"
                                        >
                                            <td>
                                                <strong
                                                    ><i
                                                        class="bx bx-file me-1"
                                                    ></i
                                                    >{{
                                                        item.original_name
                                                    }}</strong
                                                >
                                            </td>
                                            <td>
                                                {{
                                                    catalogLabel(
                                                        "document_category",
                                                        item.category
                                                    )
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    visibilityLabels[
                                                        item.visibility
                                                    ] || item.visibility
                                                }}
                                            </td>
                                            <td>
                                                {{
                                                    Math.ceil(
                                                        item.size_bytes / 1024
                                                    )
                                                }}
                                                KB
                                            </td>
                                            <td class="text-end">
                                                <button
                                                    v-if="
                                                        catalogs.capabilities
                                                            .documents_download
                                                    "
                                                    class="btn btn-sm btn-outline-primary"
                                                    @click="
                                                        downloadDocument(item)
                                                    "
                                                >
                                                    <i
                                                        class="bx bx-download me-1"
                                                    ></i
                                                    >Descargar
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!selected.documents?.length">
                                            <td colspan="5" class="psi-empty">
                                                No hay documentos adjuntos.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'feedback'"
                    class="psi-section"
                >
                    <div class="psi-section-head">
                        <div>
                            <h5>Comunicaciones y coordinaciones</h5>
                            <p>
                                Registros compartibles, consentimientos y
                                derivaciones externas.
                            </p>
                        </div>
                        <div
                            v-if="catalogs.capabilities.create_activity"
                            class="d-flex flex-wrap gap-2"
                        >
                            <button
                                class="btn btn-outline-primary"
                                @click="openForm('feedback')"
                            >
                                Retroalimentación
                            </button>
                            <button
                                class="btn btn-outline-primary"
                                @click="openForm('consent')"
                            >
                                Consentimiento
                            </button>
                            <button
                                class="btn btn-primary"
                                @click="openForm('external')"
                            >
                                Derivación externa
                            </button>
                        </div>
                    </div>
                    <div
                        v-if="
                            catalogs.capabilities.create_activity &&
                            ['feedback', 'consent', 'external'].includes(
                                formModal
                            )
                        "
                        class="psi-form-modal"
                        @click.self="closeForm"
                    >
                        <div class="psi-feedback-modal-grid">
                            <div v-if="formModal === 'feedback'">
                                <div class="card psi-modal-card">
                                    <div class="card-body">
                                        <div class="psi-modal-heading">
                                            <div>
                                                <span
                                                    >Comunicación mínima
                                                    necesaria</span
                                                >
                                                <h5>
                                                    Retroalimentación
                                                    compartible
                                                </h5>
                                            </div>
                                            <button
                                                class="psi-close"
                                                @click="closeForm"
                                            >
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </div>
                                        <select
                                            v-model="feedback.referral_id"
                                            class="form-select mb-2"
                                        >
                                            <option :value="null">
                                                Sin derivación específica
                                            </option>
                                            <option
                                                v-for="item in selected.referrals ||
                                                []"
                                                :key="item.id"
                                                :value="item.id"
                                            >
                                                {{ item.code }}
                                            </option></select
                                        ><textarea
                                            v-model="feedback.content"
                                            class="form-control mb-2"
                                            rows="4"
                                            placeholder="Información mínima necesaria para el área derivante"
                                        ></textarea
                                        ><button
                                            class="btn btn-primary w-100"
                                            :disabled="!feedback.content"
                                            @click="saveFeedback"
                                        >
                                            Publicar retroalimentación
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="formModal === 'consent'">
                                <div class="card psi-modal-card">
                                    <div class="card-body">
                                        <div class="psi-modal-heading">
                                            <div>
                                                <span>Registro trazable</span>
                                                <h5>
                                                    Información y consentimiento
                                                </h5>
                                            </div>
                                            <button
                                                class="psi-close"
                                                @click="closeForm"
                                            >
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </div>
                                        <input
                                            v-model="consent.action_type"
                                            class="form-control mb-2"
                                            placeholder="Acción informada"
                                        />
                                        <div class="form-check mb-2">
                                            <input
                                                id="guardian-informed"
                                                v-model="
                                                    consent.guardian_informed
                                                "
                                                class="form-check-input"
                                                type="checkbox"
                                            /><label
                                                class="form-check-label"
                                                for="guardian-informed"
                                                >Apoderado informado</label
                                            >
                                        </div>
                                        <input
                                            v-model="consent.informed_at"
                                            type="datetime-local"
                                            class="form-control mb-2"
                                        /><select
                                            v-model="consent.status"
                                            class="form-select mb-2"
                                        >
                                            <option value="pending">
                                                Pendiente
                                            </option>
                                            <option value="granted">
                                                Otorgado
                                            </option>
                                            <option value="rejected">
                                                Rechazado
                                            </option>
                                            <option value="not_required">
                                                No requerido
                                            </option>
                                            <option value="exception">
                                                Excepción institucional
                                            </option>
                                        </select>
                                        <div class="form-check mb-2">
                                            <input
                                                id="consent-required"
                                                v-model="
                                                    consent.consent_required
                                                "
                                                class="form-check-input"
                                                type="checkbox"
                                            /><label
                                                class="form-check-label"
                                                for="consent-required"
                                                >Requiere consentimiento</label
                                            >
                                        </div>
                                        <textarea
                                            v-model="consent.observations"
                                            class="form-control mb-2"
                                            rows="2"
                                            placeholder="Observaciones"
                                        ></textarea
                                        ><button
                                            class="btn btn-outline-primary w-100"
                                            @click="saveConsent"
                                        >
                                            Registrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="formModal === 'external'">
                                <div class="card psi-modal-card">
                                    <div class="card-body">
                                        <div class="psi-modal-heading">
                                            <div>
                                                <span
                                                    >Coordinación
                                                    interinstitucional</span
                                                >
                                                <h5>Derivación externa</h5>
                                            </div>
                                            <button
                                                class="psi-close"
                                                @click="closeForm"
                                            >
                                                <i class="bx bx-x"></i>
                                            </button>
                                        </div>
                                        <input
                                            v-model="external.institution"
                                            class="form-control mb-2"
                                            placeholder="Institución"
                                        /><input
                                            v-model="external.institution_type"
                                            class="form-control mb-2"
                                            placeholder="Tipo de institución"
                                        /><textarea
                                            v-model="external.general_reason"
                                            class="form-control mb-2"
                                            rows="2"
                                            placeholder="Motivo general"
                                        ></textarea
                                        ><input
                                            v-model="external.referred_on"
                                            type="date"
                                            class="form-control mb-2"
                                        />
                                        <div class="form-check mb-2">
                                            <input
                                                id="external-guardian"
                                                v-model="
                                                    external.guardian_informed
                                                "
                                                class="form-check-input"
                                                type="checkbox"
                                            /><label
                                                class="form-check-label"
                                                for="external-guardian"
                                                >Apoderado informado</label
                                            >
                                        </div>
                                        <input
                                            v-model="external.next_contact_on"
                                            type="date"
                                            class="form-control mb-2"
                                        /><button
                                            class="btn btn-outline-primary w-100"
                                            :disabled="
                                                !external.institution ||
                                                !external.general_reason
                                            "
                                            @click="saveExternal"
                                        >
                                            Registrar derivación
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="psi-table-card table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Registro</th>
                                    <th>Detalle</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in selected.shared_feedback ||
                                    []"
                                    :key="`feedback-${item.id}`"
                                >
                                    <td><strong>Retroalimentación</strong></td>
                                    <td>{{ item.content }}</td>
                                    <td>
                                        {{ formatDateTime(item.created_at) }}
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-primary-subtle text-primary"
                                            >Compartida</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in selected.consents || []"
                                    :key="`consent-${item.id}`"
                                >
                                    <td><strong>Consentimiento</strong></td>
                                    <td>
                                        {{
                                            consentActionLabels[
                                                item.action_type
                                            ] || item.action_type
                                        }}
                                    </td>
                                    <td>
                                        {{
                                            formatDateTime(
                                                item.informed_at ||
                                                    item.created_at
                                            )
                                        }}
                                    </td>
                                    <td>
                                        <PsychologyBadge :value="item.status" />
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in selected.external_referrals ||
                                    []"
                                    :key="`external-${item.id}`"
                                >
                                    <td>
                                        <strong>Derivación externa</strong
                                        ><small>{{ item.institution }}</small>
                                    </td>
                                    <td>{{ item.general_reason }}</td>
                                    <td>{{ formatDate(item.referred_on) }}</td>
                                    <td>
                                        <PsychologyBadge :value="item.status" />
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        !selected.shared_feedback?.length &&
                                        !selected.consents?.length &&
                                        !selected.external_referrals?.length
                                    "
                                >
                                    <td colspan="4" class="psi-empty">
                                        No hay comunicaciones registradas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'timeline'"
                    class="psi-section"
                >
                    <div class="psi-timeline">
                        <article
                            v-for="item in [
                                ...(selected.activities || []).map((x) => ({
                                    ...x,
                                    event_type: 'Actividad',
                                    event_at: x.activity_on,
                                    event_title: x.type,
                                })),
                                ...(selected.tasks || []).map((x) => ({
                                    ...x,
                                    event_type: 'Tarea',
                                    event_at: x.created_at,
                                    event_title: x.title,
                                })),
                                ...(selected.closures || []).map((x) => ({
                                    ...x,
                                    event_type: 'Cierre',
                                    event_at: x.closed_at,
                                    event_title: x.reason,
                                })),
                                ...(selected.reopenings || []).map((x) => ({
                                    ...x,
                                    event_type: 'Reapertura',
                                    event_at: x.reopened_at,
                                    event_title: x.reason,
                                })),
                            ].sort((a, b) =>
                                String(b.event_at).localeCompare(
                                    String(a.event_at)
                                )
                            )"
                            :key="`${item.event_type}-${item.id}`"
                        >
                            <small>{{ formatDateTime(item.event_at) }}</small
                            ><strong
                                >{{ item.event_type }} ·
                                {{ item.event_title }}</strong
                            >
                            <p>
                                {{
                                    item.institutional_summary ||
                                    item.result_summary ||
                                    item.description ||
                                    ""
                                }}
                            </p>
                        </article>
                        <div
                            v-if="
                                !selected.activities?.length &&
                                !selected.tasks?.length &&
                                !selected.closures?.length &&
                                !selected.reopenings?.length
                            "
                            class="psi-empty"
                        >
                            Aún no hay eventos en la línea de tiempo.
                        </div>
                    </div>
                </section>
                <section
                    v-else-if="activeTab === 'closure'"
                    class="psi-section"
                >
                    <div class="psi-section-head">
                        <div>
                            <h5>Cierre del caso</h5>
                            <p>
                                Conclusión profesional y conservación del
                                historial.
                            </p>
                        </div>
                        <button
                            v-if="
                                selected.status !== 'closed' &&
                                catalogs.capabilities.close_case
                            "
                            class="btn btn-outline-danger"
                            @click="openForm('closure')"
                        >
                            <i class="bx bx-lock-alt me-1"></i>Cerrar caso
                        </button>
                    </div>
                    <div
                        v-if="
                            selected.status !== 'closed' &&
                            catalogs.capabilities.close_case &&
                            formModal === 'closure'
                        "
                        class="psi-form-modal"
                        @click.self="closeForm"
                    >
                        <div class="card psi-modal-card">
                            <div class="card-body">
                                <div class="psi-modal-heading">
                                    <div>
                                        <span
                                            >Acción irreversible y
                                            auditada</span
                                        >
                                        <h5>Cierre profesional</h5>
                                    </div>
                                    <button
                                        class="psi-close"
                                        @click="closeForm"
                                    >
                                        <i class="bx bx-x"></i>
                                    </button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <select
                                            v-model="closure.closure_type"
                                            class="form-select"
                                        >
                                            <option
                                                v-for="item in catalogs.catalogs
                                                    .closure_type || []"
                                                :key="item.id"
                                                :value="item.slug"
                                            >
                                                {{ item.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <input
                                            v-model="closure.reason"
                                            class="form-control"
                                            placeholder="Motivo de cierre"
                                        />
                                    </div>
                                    <div class="col-12">
                                        <textarea
                                            v-model="closure.result_summary"
                                            class="form-control"
                                            rows="3"
                                            placeholder="Resumen de resultados obligatorio"
                                        ></textarea>
                                    </div>
                                    <div class="col-12">
                                        <textarea
                                            v-model="closure.recommendations"
                                            class="form-control"
                                            rows="2"
                                            placeholder="Recomendaciones generales"
                                        ></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button
                                            class="btn btn-danger"
                                            @click="closeCase"
                                        >
                                            Cerrar caso
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else-if="selected.status === 'closed'"
                        class="alert alert-success"
                    >
                        <div
                            class="d-flex flex-wrap align-items-center justify-content-between gap-2"
                        >
                            <span
                                >Caso cerrado el
                                {{ formatDateTime(selected.closed_at) }}. El
                                historial permanece disponible.</span
                            >
                            <button
                                v-if="catalogs.capabilities.reopen_case"
                                class="btn btn-sm btn-success"
                                @click="openForm('reopen')"
                            >
                                Reabrir caso
                            </button>
                        </div>
                    </div>
                    <div
                        v-else-if="!catalogs.capabilities.close_case"
                        class="alert alert-light"
                    >
                        No tienes permiso para cerrar este caso.
                    </div>
                    <PsychologyModal
                        v-if="formModal === 'reopen'"
                        eyebrow="Continuidad del acompañamiento"
                        title="Reabrir caso"
                        @close="closeForm"
                    >
                        <label class="form-label">Motivo de reapertura</label>
                        <textarea
                            v-model="reopening.reason"
                            class="form-control"
                            rows="4"
                        ></textarea>
                        <template #footer>
                            <button class="btn btn-light" @click="closeForm">
                                Cancelar
                            </button>
                            <button
                                class="btn btn-success"
                                :disabled="
                                    !reopening.reason || api.loading.value
                                "
                                @click="reopenCase"
                            >
                                Confirmar reapertura
                            </button>
                        </template>
                    </PsychologyModal>
                </section>
                <section v-else class="psi-section">
                    <div class="psi-empty">
                        Esta pestaña consolida registros vinculados al caso. Usa
                        las acciones disponibles o la línea de tiempo para
                        consultar el historial.
                    </div>
                </section>
            </div>
        </PsychologyModal>

        <PsychologyModal
            v-if="selectedPlan && selectedPlanVersion"
            eyebrow="Plan profesional confidencial"
            :title="`Plan de intervención · Versión ${selectedPlan.current_version}`"
            size="xlarge-tall"
            @close="selectedPlan = null"
        >
            <div class="psi-activity-file psi-plan-file">
                <div class="psi-activity-file-hero">
                    <span class="psi-activity-file-icon">
                        <i class="bx bx-target-lock"></i>
                    </span>
                    <div>
                        <small>Plan de intervención vigente</small>
                        <strong>{{ selected.student?.name }}</strong>
                        <span>
                            {{ selected.code }} ·
                            {{
                                selected.student?.course || "Curso no informado"
                            }}
                        </span>
                    </div>
                    <PsychologyBadge :value="selectedPlan.status" />
                </div>

                <div class="psi-sensitive-notice">
                    <i class="bx bx-shield-quarter"></i>
                    <span>
                        <strong>Documento profesional confidencial</strong>
                        Su consulta y exportación quedan registradas en la
                        auditoría institucional.
                    </span>
                </div>

                <div class="psi-activity-meta-grid">
                    <article>
                        <i class="bx bx-user-check"></i>
                        <div>
                            <small>Profesional responsable</small>
                            <strong>{{
                                selectedPlan.responsible_user?.name ||
                                selected.responsible_user?.name ||
                                "No informado"
                            }}</strong>
                            <span>Autoría de la versión vigente</span>
                        </div>
                    </article>
                    <article>
                        <i class="bx bx-git-branch"></i>
                        <div>
                            <small>Versión del plan</small>
                            <strong
                                >Versión
                                {{ selectedPlan.current_version }}</strong
                            >
                            <span>
                                Creada
                                {{
                                    formatDateTime(
                                        selectedPlanVersion.created_at
                                    )
                                }}
                            </span>
                        </div>
                    </article>
                    <article>
                        <i class="bx bx-calendar-check"></i>
                        <div>
                            <small>Próxima revisión</small>
                            <strong>{{
                                formatDate(selectedPlan.review_on)
                            }}</strong>
                            <span>
                                {{
                                    formatDate(
                                        selectedPlanVersion.estimated_start_on
                                    )
                                }}
                                –
                                {{
                                    formatDate(
                                        selectedPlanVersion.estimated_end_on
                                    )
                                }}
                            </span>
                        </div>
                    </article>
                </div>

                <section class="psi-activity-file-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h5>Fundamentos y objetivos</h5>
                            <p>
                                Situación abordada y propósito de intervención.
                            </p>
                        </div>
                    </header>
                    <div class="psi-activity-narratives">
                        <article class="wide">
                            <small>Situación general</small>
                            <p>
                                {{
                                    selectedPlanVersion.general_situation ||
                                    "Sin información registrada."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Objetivo general</small>
                            <p>
                                {{
                                    selectedPlanVersion.general_objective ||
                                    "Sin información registrada."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Objetivos específicos</small>
                            <p>
                                {{
                                    selectedPlanVersion.specific_objectives ||
                                    "Sin objetivos específicos."
                                }}
                            </p>
                        </article>
                    </div>
                </section>

                <section class="psi-activity-file-section">
                    <header>
                        <span>02</span>
                        <div>
                            <h5>Implementación y seguimiento</h5>
                            <p>
                                Acciones, responsables e indicadores del plan.
                            </p>
                        </div>
                    </header>
                    <div class="psi-activity-narratives">
                        <article class="wide">
                            <small>Acciones planificadas</small>
                            <p>
                                {{
                                    selectedPlanVersion.planned_actions ||
                                    "Sin acciones registradas."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Responsables</small>
                            <p>
                                {{
                                    selectedPlanVersion.responsibles ||
                                    "Sin responsables informados."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Frecuencia</small>
                            <p>
                                {{
                                    selectedPlanVersion.frequency ||
                                    "Sin frecuencia informada."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Participantes</small>
                            <p>
                                {{
                                    selectedPlanVersion.participants ||
                                    "Sin participantes adicionales."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Indicadores de seguimiento</small>
                            <p>
                                {{
                                    selectedPlanVersion.monitoring_indicators ||
                                    "Sin indicadores registrados."
                                }}
                            </p>
                        </article>
                    </div>
                </section>

                <section class="psi-activity-file-section">
                    <header>
                        <span>03</span>
                        <div>
                            <h5>Coordinaciones</h5>
                            <p>Articulación institucional y redes de apoyo.</p>
                        </div>
                    </header>
                    <div class="psi-activity-narratives">
                        <article>
                            <small>Familia</small>
                            <p>
                                {{
                                    selectedPlanVersion.family_coordination ||
                                    "Sin coordinación registrada."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Equipo docente</small>
                            <p>
                                {{
                                    selectedPlanVersion.teacher_coordination ||
                                    "Sin coordinación registrada."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Convivencia escolar</small>
                            <p>
                                {{
                                    selectedPlanVersion.coexistence_coordination ||
                                    "Sin coordinación registrada."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Red externa</small>
                            <p>
                                {{
                                    selectedPlanVersion.external_coordination ||
                                    "Sin coordinación registrada."
                                }}
                            </p>
                        </article>
                        <article
                            v-if="selectedPlanVersion.review_result"
                            class="wide"
                        >
                            <small>Resultado de la revisión</small>
                            <p>{{ selectedPlanVersion.review_result }}</p>
                        </article>
                    </div>
                </section>
            </div>
            <template #footer>
                <button class="btn btn-light" @click="selectedPlan = null">
                    Cerrar
                </button>
                <button
                    class="btn btn-danger"
                    :disabled="exportingPlanPdf"
                    @click="exportPlanPdf()"
                >
                    <i class="bx bxs-file-pdf me-1"></i>
                    {{ exportingPlanPdf ? "Preparando PDF…" : "Exportar PDF" }}
                </button>
            </template>
        </PsychologyModal>

        <PsychologyModal
            v-if="selectedActivity"
            eyebrow="Registro profesional"
            :title="
                selectedActivity.interview_number
                    ? `Entrevista N° ${selectedActivity.interview_number}`
                    : 'Detalle de atención'
            "
            size="xlarge-tall"
            :close-on-backdrop="false"
            @close="selectedActivity = null"
        >
            <div class="psi-activity-file">
                <div class="psi-activity-file-hero">
                    <span class="psi-activity-file-icon">
                        <i class="bx bx-conversation"></i>
                    </span>
                    <div>
                        <small>{{
                            catalogLabel("activity_type", selectedActivity.type)
                        }}</small>
                        <strong>{{
                            selectedActivity.interviewee_name ||
                            "Persona no informada"
                        }}</strong>
                        <span>
                            {{ selected.code }} ·
                            {{ selected.student?.name }}
                        </span>
                    </div>
                    <PsychologyBadge :value="selectedActivity.status" />
                </div>

                <div class="psi-sensitive-notice">
                    <i class="bx bx-shield-quarter"></i>
                    <span>
                        <strong>Ficha profesional confidencial</strong>
                        Su consulta y exportación quedan registradas en la
                        auditoría institucional.
                    </span>
                </div>

                <div class="psi-activity-meta-grid">
                    <article>
                        <i class="bx bx-user"></i>
                        <div>
                            <small>Persona entrevistada</small>
                            <strong>{{
                                selectedActivity.interviewee_name || "—"
                            }}</strong>
                            <span>
                                {{
                                    intervieweeTypeLabel(
                                        selectedActivity.interviewee_type
                                    )
                                }}
                                <template
                                    v-if="selectedActivity.interviewee_rut"
                                >
                                    · RUT
                                    {{ selectedActivity.interviewee_rut }}
                                </template>
                            </span>
                        </div>
                    </article>
                    <article>
                        <i class="bx bx-calendar"></i>
                        <div>
                            <small>Fecha y horario</small>
                            <strong>{{
                                formatDate(selectedActivity.activity_on)
                            }}</strong>
                            <span>
                                {{
                                    selectedActivity.starts_at
                                        ? selectedActivity.starts_at.slice(0, 5)
                                        : "Sin inicio"
                                }}
                                –
                                {{
                                    selectedActivity.ends_at
                                        ? selectedActivity.ends_at.slice(0, 5)
                                        : "Sin término"
                                }}
                            </span>
                        </div>
                    </article>
                    <article>
                        <i class="bx bx-briefcase"></i>
                        <div>
                            <small>Profesional responsable</small>
                            <strong>{{
                                selectedActivity.interviewer_name_snapshot ||
                                selectedActivity.responsible_user?.name ||
                                "—"
                            }}</strong>
                            <span>{{
                                selectedActivity.interviewer_position_snapshot ||
                                "Cargo no informado"
                            }}</span>
                        </div>
                    </article>
                </div>

                <section class="psi-activity-file-section">
                    <header>
                        <span>01</span>
                        <div>
                            <h5>Encuadre de la atención</h5>
                            <p>Contexto operativo y participantes.</p>
                        </div>
                    </header>
                    <dl class="psi-activity-facts">
                        <div>
                            <dt>Modalidad</dt>
                            <dd>
                                {{ modalityLabel(selectedActivity.modality) }}
                            </dd>
                        </div>
                        <div>
                            <dt>Lugar</dt>
                            <dd>
                                {{
                                    selectedActivity.location || "No informado"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Asistencia</dt>
                            <dd>
                                {{
                                    attendanceLabel(
                                        selectedActivity.attendance_status
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Visibilidad</dt>
                            <dd>
                                {{
                                    confidentialityLabel(
                                        selectedActivity.visibility
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="wide">
                            <dt>Participantes</dt>
                            <dd class="psi-activity-participants">
                                <span
                                    v-for="type in selectedActivity.participant_types ||
                                    []"
                                    :key="type"
                                >
                                    {{ intervieweeTypeLabel(type) }}
                                </span>
                                <em
                                    v-if="
                                        !selectedActivity.participant_types
                                            ?.length
                                    "
                                    >Sin participantes tipificados</em
                                >
                            </dd>
                        </div>
                        <div v-if="selectedActivity.participants" class="wide">
                            <dt>Otros participantes</dt>
                            <dd>{{ selectedActivity.participants }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="psi-activity-file-section">
                    <header>
                        <span>02</span>
                        <div>
                            <h5>Registro profesional</h5>
                            <p>
                                Objetivo, resumen y resultados de la atención.
                            </p>
                        </div>
                    </header>
                    <div class="psi-activity-narratives">
                        <article>
                            <small>Motivo u objetivo</small>
                            <p>
                                {{
                                    selectedActivity.objective ||
                                    "Sin objetivo registrado."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Resumen institucional</small>
                            <p>
                                {{
                                    selectedActivity.institutional_summary ||
                                    "Sin resumen registrado."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Resultado</small>
                            <p>
                                {{
                                    selectedActivity.result ||
                                    "Sin resultado registrado."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Acuerdos</small>
                            <p>
                                {{
                                    selectedActivity.agreements ||
                                    "Sin acuerdos registrados."
                                }}
                            </p>
                        </article>
                    </div>
                </section>

                <section
                    v-if="
                        selectedActivity.general_background ||
                        selectedActivity.private_note
                    "
                    class="psi-activity-file-section is-private"
                >
                    <header>
                        <span><i class="bx bx-lock-alt"></i></span>
                        <div>
                            <h5>Contenido profesional protegido</h5>
                            <p>
                                Visible únicamente con autorización explícita.
                            </p>
                        </div>
                    </header>
                    <div class="psi-activity-narratives">
                        <article v-if="selectedActivity.general_background">
                            <small>Antecedentes generales</small>
                            <p>{{ selectedActivity.general_background }}</p>
                        </article>
                        <article v-if="selectedActivity.private_note">
                            <small>Nota profesional privada</small>
                            <p>{{ selectedActivity.private_note }}</p>
                        </article>
                    </div>
                </section>

                <section class="psi-activity-file-section">
                    <header>
                        <span>03</span>
                        <div>
                            <h5>Seguimiento</h5>
                            <p>Continuidad y comunicación institucional.</p>
                        </div>
                    </header>
                    <div class="psi-activity-narratives">
                        <article>
                            <small>Tipo de seguimiento</small>
                            <p>
                                {{
                                    selectedActivity.next_action_on
                                        ? followUpTypeLabel(
                                              selectedActivity.follow_up_type
                                          )
                                        : "Sin seguimiento programado."
                                }}
                            </p>
                        </article>
                        <article>
                            <small>Fecha de seguimiento</small>
                            <p>
                                {{
                                    selectedActivity.next_action_on
                                        ? formatDate(
                                              selectedActivity.next_action_on
                                          )
                                        : "Sin seguimiento programado."
                                }}
                            </p>
                        </article>
                        <article
                            v-if="selectedActivity.next_steps"
                            class="wide"
                        >
                            <small>Detalle del seguimiento</small>
                            <p>
                                {{ selectedActivity.next_steps }}
                            </p>
                        </article>
                        <article
                            v-if="selectedActivity.referral_feedback"
                            class="wide"
                        >
                            <small>Retroalimentación al área derivante</small>
                            <p>{{ selectedActivity.referral_feedback }}</p>
                        </article>
                    </div>
                </section>

                <section
                    v-if="selectedActivity.addenda?.length"
                    class="psi-activity-file-section"
                >
                    <header>
                        <span>04</span>
                        <div>
                            <h5>Adendas</h5>
                            <p>
                                Correcciones posteriores sin alterar el
                                original.
                            </p>
                        </div>
                    </header>
                    <div class="psi-activity-addenda">
                        <article
                            v-for="addendum in selectedActivity.addenda"
                            :key="addendum.id"
                        >
                            <small>
                                {{ formatDateTime(addendum.created_at) }} ·
                                {{ addendum.author?.name }}
                            </small>
                            <strong>{{ addendum.reason }}</strong>
                            <p>{{ addendum.content }}</p>
                        </article>
                    </div>
                </section>
            </div>
            <template #footer>
                <button
                    v-if="catalogs.capabilities.create_activity"
                    type="button"
                    class="btn btn-outline-primary"
                    @click="openActivityEdit(selectedActivity)"
                >
                    <i class="bx bx-edit-alt me-1"></i>
                    Editar ficha
                </button>
                <button
                    type="button"
                    class="btn btn-light"
                    @click="selectedActivity = null"
                >
                    Cerrar
                </button>
                <button
                    type="button"
                    class="btn btn-danger psi-pdf-button"
                    :disabled="exportingActivityPdf"
                    @click="exportSelectedActivityPdf"
                >
                    <i class="bx bxs-file-pdf me-1"></i>
                    {{
                        exportingActivityPdf
                            ? "Preparando PDF…"
                            : "Exportar PDF"
                    }}
                </button>
            </template>
        </PsychologyModal>
    </div>
</template>
<style scoped>
.psi-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.75rem;
    padding: 0.85rem 1rem;
    background: radial-gradient(
            circle at 96% 0,
            rgba(117, 93, 157, 0.13),
            transparent 40%
        ),
        linear-gradient(135deg, #fff, #f7fbff);
    border: 1px solid #dfe7ef;
    border-radius: 15px;
    box-shadow: 0 10px 28px rgba(39, 48, 77, 0.055);
}
.psi-toolbar-copy {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.psi-toolbar-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.4rem;
    height: 2.4rem;
    place-items: center;
    color: #65517f;
    background: #f0edf8;
    border-radius: 11px;
    font-size: 1.15rem;
}
.psi-eyebrow {
    color: #806c9d;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.psi-toolbar h4 {
    margin: 0;
    color: #25364c;
    font-size: 0.95rem;
}
.psi-toolbar p {
    margin: 0.18rem 0 0;
    color: #78859a;
    font-size: 0.7rem;
}
.psi-private-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex: 0 0 auto;
    padding: 0.5rem 0.7rem;
    color: #5c4a76;
    background: rgba(240, 237, 248, 0.9);
    border: 1px solid #e3dced;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
}
.psi-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.psi-primary-action {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 38px;
    padding: 0 0.9rem;
    border-radius: 9px;
    font-size: 0.74rem;
    font-weight: 700;
    box-shadow: 0 8px 18px rgba(85, 110, 230, 0.2);
}
.psi-origin-selector {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    margin-bottom: 1rem;
}
.psi-origin-selector > button {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-height: 62px;
    padding: 0.7rem 0.8rem;
    color: #617085;
    text-align: left;
    background: #f8fafc;
    border: 1px solid #dde5ed;
    border-radius: 12px;
    transition: border-color 0.16s ease, box-shadow 0.16s ease,
        background 0.16s ease;
}
.psi-origin-selector > button > i:first-child {
    display: grid;
    flex: 0 0 auto;
    width: 2.2rem;
    height: 2.2rem;
    place-items: center;
    color: #687a91;
    background: #fff;
    border-radius: 9px;
    font-size: 1.1rem;
}
.psi-origin-selector > button span {
    min-width: 0;
    margin-right: auto;
}
.psi-origin-selector strong,
.psi-origin-selector small {
    display: block;
}
.psi-origin-selector strong {
    color: #34445a;
    font-size: 0.78rem;
}
.psi-origin-selector small {
    margin-top: 0.12rem;
    color: #8491a3;
    font-size: 0.66rem;
}
.psi-origin-selector .check {
    color: transparent;
    font-size: 1rem;
}
.psi-origin-selector > button.active {
    background: #f4f1fb;
    border-color: #927ab3;
    box-shadow: 0 6px 18px rgba(102, 80, 128, 0.1);
}
.psi-origin-selector > button.active > i:first-child {
    color: #655080;
}
.psi-origin-selector > button.active .check {
    color: #655080;
}
.psi-create-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    min-height: 220px;
    color: #718096;
}
.psi-create-guidance {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 0.8rem 0.9rem;
    color: #526078;
    background: #f5f3fb;
    border: 1px solid #e4def0;
    border-radius: 12px;
}
.psi-create-guidance > i {
    display: grid;
    flex: 0 0 auto;
    width: 2.2rem;
    height: 2.2rem;
    place-items: center;
    color: #665080;
    background: #fff;
    border-radius: 9px;
    font-size: 1.1rem;
}
.psi-create-guidance strong,
.psi-create-guidance span {
    display: block;
}
.psi-create-guidance strong {
    color: #2e3e52;
    font-size: 0.82rem;
}
.psi-create-guidance span {
    margin-top: 0.15rem;
    font-size: 0.72rem;
}
.psi-create-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}
.psi-create-grid > .wide {
    grid-column: 1 / -1;
}
.psi-create-grid .form-label {
    margin-bottom: 0.35rem;
    color: #4a586c;
    font-size: 0.72rem;
    font-weight: 700;
}
.psi-create-grid .form-label span {
    color: #d14343;
}
.psi-create-grid .form-control,
.psi-create-grid .form-select {
    min-height: 40px;
    border-color: #dbe3ec;
    border-radius: 9px;
    font-size: 0.76rem;
}
.psi-common-case-fields {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e8edf3;
}
.psi-student-picker {
    position: relative;
}
.psi-student-picker .psi-search-field > .spinner-border {
    position: absolute;
    top: calc(50% - 0.45rem);
    right: 0.85rem;
    width: 0.9rem;
    height: 0.9rem;
    color: #6c5a87;
}
.psi-student-results {
    position: absolute;
    z-index: 5;
    top: calc(100% + 0.3rem);
    right: 0;
    left: 0;
    max-height: 230px;
    overflow-y: auto;
    padding: 0.3rem;
    background: #fff;
    border: 1px solid #dce4ec;
    border-radius: 11px;
    box-shadow: 0 18px 42px rgba(30, 41, 59, 0.16);
}
.psi-student-results > button {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    width: 100%;
    padding: 0.65rem 0.7rem;
    color: #425168;
    text-align: left;
    background: transparent;
    border: 0;
    border-radius: 8px;
}
.psi-student-results > button:hover,
.psi-student-results > button:focus-visible {
    background: #f3f6fa;
}
.psi-student-results > button > span {
    min-width: 0;
    margin-right: auto;
}
.psi-student-results strong,
.psi-student-results small {
    display: block;
}
.psi-student-results strong {
    font-size: 0.76rem;
}
.psi-student-results small {
    margin-top: 0.1rem;
    color: #8591a2;
    font-size: 0.66rem;
}
.psi-student-results em {
    flex: 0 0 auto;
    padding: 0.2rem 0.4rem;
    color: #9a5e09;
    background: #fff3d8;
    border-radius: 999px;
    font-size: 0.62rem;
    font-style: normal;
    font-weight: 700;
}
.psi-referral-summary {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8fbfd;
    border: 1px solid #dfe8ee;
    border-radius: 12px;
}
.psi-referral-avatar {
    display: grid;
    flex: 0 0 auto;
    width: 2.35rem;
    height: 2.35rem;
    place-items: center;
    color: #4d718c;
    background: #e8f2f7;
    border-radius: 10px;
    font-size: 1.1rem;
}
.psi-referral-summary > div {
    min-width: 0;
    margin-right: auto;
}
.psi-referral-summary small,
.psi-referral-summary strong,
.psi-referral-summary > div > span {
    display: block;
}
.psi-referral-summary small,
.psi-referral-summary > div > span {
    color: #78859a;
    font-size: 0.68rem;
}
.psi-referral-summary strong {
    overflow: hidden;
    color: #27384f;
    font-size: 0.82rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-create-empty {
    display: flex;
    align-items: center;
    flex-direction: column;
    justify-content: center;
    min-height: 280px;
    padding: 2rem;
    text-align: center;
}
.psi-create-empty > span {
    display: grid;
    width: 3.6rem;
    height: 3.6rem;
    margin-bottom: 0.8rem;
    place-items: center;
    color: #6a5687;
    background: #f0edf8;
    border-radius: 18px;
    font-size: 1.55rem;
}
.psi-create-empty strong {
    color: #2e3e52;
}
.psi-create-empty p {
    max-width: 460px;
    margin: 0.4rem 0 1rem;
    color: #718096;
    font-size: 0.8rem;
}
.psi-create-empty.compact {
    min-height: 190px;
    padding: 1.2rem;
}
.psi-table-card {
    background: #fff;
    border: 1px solid #dfe7ef;
    border-radius: 16px;
    box-shadow: 0 14px 38px rgba(39, 48, 77, 0.06);
    overflow: hidden;
}
.psi-table-card > .card-body {
    padding: 0.8rem;
}
.psi-list-filters {
    display: grid;
    grid-template-columns: minmax(280px, 2fr) minmax(170px, 1fr) minmax(
            170px,
            1fr
        );
    gap: 0.6rem;
    margin-bottom: 0.8rem;
    padding: 0.7rem;
    background: #f4f8fa;
    border: 1px solid #dce7ec;
    border-radius: 12px;
}
.psi-list-filters .form-control,
.psi-list-filters .form-select {
    height: 40px;
    border-color: #dbe3ec;
    border-radius: 9px;
    font-size: 0.74rem;
}
.psi-search-field {
    position: relative;
}
.psi-search-field > i {
    position: absolute;
    z-index: 1;
    top: 50%;
    left: 0.75rem;
    color: #8492a4;
    font-size: 1rem;
    transform: translateY(-50%);
}
.psi-search-field .form-control {
    padding-left: 2.25rem;
}
.psi-table-card td strong,
.psi-table-card td small {
    display: block;
}
.psi-table-card td small {
    color: #8290a0;
}
.psi-table-card .table > :not(caption) > * > * {
    padding: 0.82rem 1rem;
}
.psi-table-card thead th {
    color: #718096;
    font-size: 0.67rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.psi-record-row-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
}
.psi-record-icon-button {
    display: inline-grid;
    width: 2.25rem;
    height: 2.25rem;
    padding: 0;
    place-items: center;
    color: #62507e;
    background: #f4f1fa;
    border: 1px solid #ded6eb;
    border-radius: 10px;
    font-size: 1.05rem;
    transition: transform 0.16s ease, box-shadow 0.16s ease,
        border-color 0.16s ease;
}
.psi-record-icon-button:hover,
.psi-record-icon-button:focus-visible {
    color: #4e3c69;
    border-color: #a996c5;
    box-shadow: 0 6px 16px rgba(89, 70, 116, 0.14);
    transform: translateY(-1px);
}
.psi-record-icon-button.is-pdf {
    color: #c84f55;
    background: #fff3f3;
    border-color: #f2c9cb;
}
.psi-record-icon-button.is-edit {
    color: #1f6b60;
    background: #eef9f6;
    border-color: #c6e8df;
}
.psi-record-icon-button.is-edit:hover,
.psi-record-icon-button.is-edit:focus-visible {
    color: #155047;
    border-color: #7cc8b5;
}
.psi-record-icon-button.is-pdf:hover,
.psi-record-icon-button.is-pdf:focus-visible {
    color: #a8353c;
    border-color: #dc8f93;
}
.psi-record-icon-button:disabled {
    cursor: wait;
    opacity: 0.55;
    transform: none;
}
.psi-followup-workspace {
    display: grid;
    gap: 1rem;
}
.psi-followup-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    background: radial-gradient(
            circle at 90% 0,
            rgba(108, 83, 143, 0.16),
            transparent 38%
        ),
        linear-gradient(135deg, #fff, #f7f9fc);
    border: 1px solid #dce5ed;
    border-radius: 16px;
}
.psi-followup-title {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.psi-followup-title > span {
    display: grid;
    flex: 0 0 auto;
    width: 2.8rem;
    height: 2.8rem;
    place-items: center;
    color: #665080;
    background: #f0edf8;
    border-radius: 12px;
    font-size: 1.25rem;
}
.psi-followup-title small,
.psi-followup-title h5,
.psi-followup-title p {
    display: block;
    margin: 0;
}
.psi-followup-title small,
.psi-followup-panel-head small {
    color: #786390;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.psi-followup-title h5 {
    margin-top: 0.1rem;
    color: #2e3e52;
    font-size: 1rem;
}
.psi-followup-title p {
    margin-top: 0.18rem;
    color: #718096;
    font-size: 0.72rem;
}
.psi-followup-head-meta {
    display: grid;
    grid-template-columns: auto auto;
    align-items: baseline;
    justify-items: end;
    gap: 0.1rem 0.35rem;
}
.psi-followup-head-meta .psi-security-chip {
    grid-column: 1 / -1;
    margin-bottom: 0.28rem;
}
.psi-followup-head-meta > strong {
    color: #33445b;
    font-size: 1.15rem;
}
.psi-followup-head-meta > small {
    color: #7b889a;
    font-size: 0.68rem;
}
.psi-security-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.36rem 0.6rem;
    color: #665080;
    background: #f2eef8;
    border: 1px solid #e1d8ec;
    border-radius: 999px;
    font-size: 0.64rem;
    font-weight: 700;
}
.psi-followup-filters {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) minmax(170px, 0.32fr) auto;
    gap: 0.65rem;
    padding: 0.75rem;
    background: #f5f8fa;
    border: 1px solid #dce7ec;
    border-radius: 13px;
}
.psi-followup-filters .form-control,
.psi-followup-filters .form-select,
.psi-followup-filters .btn {
    min-height: 40px;
    border-radius: 9px;
    font-size: 0.72rem;
}
.psi-followup-panel {
    overflow: hidden;
    background: #fff;
    border: 1px solid #dfe7ef;
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(39, 48, 77, 0.055);
}
.psi-followup-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 1rem;
    border-bottom: 1px solid #e8edf3;
}
.psi-followup-panel-head h6 {
    margin: 0.08rem 0 0;
    color: #33445b;
    font-size: 0.86rem;
}
.psi-followup-panel-head > span {
    color: #8491a3;
    font-size: 0.66rem;
}
.psi-followup-calendar {
    padding: 0.8rem;
    --fc-border-color: #e1e7ed;
    --fc-button-text-color: #536176;
    --fc-button-bg-color: #fff;
    --fc-button-border-color: #d6dee7;
    --fc-button-hover-bg-color: #f2eff8;
    --fc-button-hover-border-color: #d9d0e5;
    --fc-button-active-bg-color: #66527f;
    --fc-button-active-border-color: #66527f;
    --fc-today-bg-color: #fff9e8;
}
.psi-followup-calendar :deep(.fc .fc-toolbar) {
    gap: 0.65rem;
    margin-bottom: 0.8rem;
}
.psi-followup-calendar :deep(.fc .fc-toolbar-title) {
    color: #34445a;
    font-size: 1rem;
    text-transform: capitalize;
}
.psi-followup-calendar :deep(.fc .fc-button) {
    padding: 0.34rem 0.58rem;
    border-radius: 8px;
    font-size: 0.68rem;
    font-weight: 700;
}
.psi-followup-calendar :deep(.fc .fc-col-header-cell-cushion),
.psi-followup-calendar :deep(.fc .fc-daygrid-day-number) {
    color: #526078;
    font-size: 0.68rem;
}
.psi-followup-calendar :deep(.fc .fc-event) {
    cursor: pointer;
    border-radius: 6px;
    font-size: 0.62rem;
}
.psi-followup-panel .table > :not(caption) > * > * {
    padding: 0.78rem 0.9rem;
}
.psi-followup-panel .table thead th {
    color: #718096;
    background: #fafbfd;
    font-size: 0.64rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.psi-followup-panel .table td strong,
.psi-followup-panel .table td small {
    display: block;
}
.psi-followup-panel .table td strong {
    color: #34445a;
    font-size: 0.75rem;
}
.psi-followup-panel .table td small {
    max-width: 330px;
    overflow: hidden;
    color: #8491a3;
    font-size: 0.66rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-followup-panel tr.is-current-case td {
    background: #f7f3fb;
}
.psi-overdue-label {
    color: #c64e55 !important;
    font-weight: 800;
    text-transform: uppercase;
}
.psi-followup-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 170px;
    color: #718096;
    font-size: 0.72rem;
}
.psi-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.9rem 1rem;
    color: #718096;
    border-top: 1px solid #edf0f4;
    font-size: 0.8rem;
}
.psi-pagination > div {
    display: flex;
    gap: 0.4rem;
}
@media (max-width: 575.98px) {
    .psi-toolbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 1rem;
    }
    .psi-toolbar-copy {
        align-items: flex-start;
    }
    .psi-list-filters {
        grid-template-columns: 1fr;
    }
    .psi-toolbar-actions {
        justify-content: space-between;
        width: 100%;
    }
    .psi-create-grid {
        grid-template-columns: 1fr;
    }
    .psi-origin-selector {
        grid-template-columns: 1fr;
    }
    .psi-create-grid > .wide {
        grid-column: auto;
    }
    .psi-referral-summary {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .psi-followup-head,
    .psi-followup-panel-head {
        align-items: flex-start;
        flex-direction: column;
    }
    .psi-followup-head-meta {
        grid-template-columns: auto auto;
        justify-items: start;
    }
    .psi-followup-filters {
        grid-template-columns: 1fr;
    }
    .psi-followup-calendar :deep(.fc .fc-toolbar) {
        align-items: stretch;
        flex-direction: column;
    }
    .psi-followup-calendar :deep(.fc .fc-toolbar-chunk) {
        display: flex;
        justify-content: center;
    }
}
.psi-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}
.psi-section-head h5 {
    margin: 0;
    color: #2e3e52;
}
.psi-section-head p {
    margin: 0.2rem 0 0;
    color: #718096;
    font-size: 0.8rem;
}
.psi-form-modal {
    position: fixed;
    z-index: 1095;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.62);
    backdrop-filter: blur(6px);
}
.psi-modal-card {
    width: min(760px, 96vw);
    max-height: 95vh;
    overflow: auto;
    overflow-x: hidden;
    border: 0;
    border-radius: 22px;
    box-shadow: 0 30px 90px rgba(15, 23, 42, 0.35);
}
.psi-modal-card-xl {
    width: min(1160px, 98vw);
    max-height: 94vh;
}
.psi-modal-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e8edf3;
}
.psi-modal-heading span {
    color: #735c9a;
    font-size: 0.67rem;
    font-weight: 800;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}
.psi-modal-heading h5 {
    margin: 0.18rem 0 0;
    color: #24324a;
}
.psi-close {
    display: grid;
    flex: 0 0 auto;
    width: 37px;
    height: 37px;
    place-items: center;
    padding: 0;
    background: #f1f5f9;
    border: 0;
    border-radius: 50%;
    font-size: 1.35rem;
}
.psi-feedback-modal-grid {
    width: min(760px, 96vw);
}
.psi-activity-file {
    display: grid;
    gap: 0.85rem;
}
.psi-activity-file-hero {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.9rem;
    background: radial-gradient(
            circle at 92% 0,
            rgba(126, 100, 164, 0.14),
            transparent 38%
        ),
        linear-gradient(135deg, #fff, #f7f9fc);
    border: 1px solid #dfe6ee;
    border-radius: 15px;
}
.psi-activity-file-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    color: #665080;
    background: #f0edf8;
    border-radius: 12px;
    font-size: 1.25rem;
}
.psi-activity-file-hero > div {
    min-width: 0;
    margin-right: auto;
}
.psi-activity-file-hero small,
.psi-activity-file-hero strong,
.psi-activity-file-hero > div > span {
    display: block;
}
.psi-activity-file-hero small {
    color: #7a8799;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.psi-activity-file-hero strong {
    overflow: hidden;
    margin: 0.1rem 0;
    color: #2d3d53;
    font-size: 0.92rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-activity-file-hero > div > span {
    color: #7c899a;
    font-size: 0.68rem;
}
.psi-sensitive-notice {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.7rem 0.8rem;
    color: #6d5529;
    background: #fff8e7;
    border: 1px solid #f2dfae;
    border-radius: 11px;
    font-size: 0.68rem;
}
.psi-sensitive-notice > i {
    flex: 0 0 auto;
    font-size: 1.1rem;
}
.psi-sensitive-notice strong {
    display: block;
    margin-bottom: 0.08rem;
    color: #59431e;
}
.psi-activity-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}
.psi-activity-meta-grid > article {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    min-width: 0;
    padding: 0.75rem;
    background: #fff;
    border: 1px solid #e2e8ef;
    border-radius: 12px;
}
.psi-activity-meta-grid > article > i {
    display: grid;
    flex: 0 0 auto;
    width: 2.2rem;
    height: 2.2rem;
    place-items: center;
    color: #65507f;
    background: #f2eef8;
    border-radius: 9px;
}
.psi-activity-meta-grid article > div {
    min-width: 0;
}
.psi-activity-meta-grid small,
.psi-activity-meta-grid strong,
.psi-activity-meta-grid span {
    display: block;
}
.psi-activity-meta-grid small {
    color: #8490a0;
    font-size: 0.61rem;
}
.psi-activity-meta-grid strong {
    overflow: hidden;
    margin: 0.08rem 0;
    color: #34445a;
    font-size: 0.76rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-activity-meta-grid span {
    color: #758296;
    font-size: 0.65rem;
}
.psi-activity-file-section {
    padding: 0.9rem;
    background: #fff;
    border: 1px solid #e2e8ef;
    border-radius: 14px;
}
.psi-activity-file-section > header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
}
.psi-activity-file-section > header > span {
    display: grid;
    flex: 0 0 auto;
    width: 2rem;
    height: 2rem;
    place-items: center;
    color: #fff;
    background: #665080;
    border-radius: 9px;
    font-size: 0.65rem;
    font-weight: 800;
}
.psi-activity-file-section h5,
.psi-activity-file-section header p {
    margin: 0;
}
.psi-activity-file-section h5 {
    color: #34445a;
    font-size: 0.8rem;
}
.psi-activity-file-section header p {
    margin-top: 0.08rem;
    color: #8490a0;
    font-size: 0.62rem;
}
.psi-activity-file-section.is-private {
    background: #fbf9fd;
    border-color: #dfd4eb;
}
.psi-activity-file-section.is-private > header > span {
    color: #624978;
    background: #eee7f5;
}
.psi-activity-facts {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.55rem;
    margin: 0;
}
.psi-activity-facts > div {
    min-width: 0;
    padding: 0.65rem;
    background: #f8fafc;
    border-radius: 9px;
}
.psi-activity-facts > .wide {
    grid-column: 1 / -1;
}
.psi-activity-facts dt {
    color: #8490a0;
    font-size: 0.59rem;
    letter-spacing: 0.05em;
}
.psi-activity-facts dd {
    margin: 0.15rem 0 0;
    color: #46556a;
    font-size: 0.7rem;
}
.psi-activity-participants {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.psi-activity-participants span {
    padding: 0.24rem 0.42rem;
    color: #5f4a79;
    background: #ece6f4;
    border-radius: 999px;
    font-size: 0.62rem;
}
.psi-activity-participants em {
    color: #8490a0;
    font-style: normal;
}
.psi-activity-narratives {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
}
.psi-activity-narratives > article {
    min-width: 0;
    padding: 0.7rem;
    background: #f9fafc;
    border: 1px solid #edf0f4;
    border-radius: 10px;
}
.psi-activity-narratives > .wide {
    grid-column: 1 / -1;
}
.psi-activity-narratives small {
    display: block;
    color: #7d899a;
    font-size: 0.61rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.psi-activity-narratives p {
    margin: 0.3rem 0 0;
    color: #445267;
    font-size: 0.72rem;
    white-space: pre-wrap;
}
.psi-activity-addenda {
    display: grid;
    gap: 0.5rem;
}
.psi-activity-addenda > article {
    padding: 0.65rem;
    background: #fffaf0;
    border-left: 3px solid #d19a37;
    border-radius: 7px;
}
.psi-activity-addenda small,
.psi-activity-addenda strong {
    display: block;
}
.psi-activity-addenda small {
    color: #8c7a5b;
    font-size: 0.6rem;
}
.psi-activity-addenda strong {
    margin-top: 0.12rem;
    color: #644f2d;
    font-size: 0.7rem;
}
.psi-activity-addenda p {
    margin: 0.25rem 0 0;
    font-size: 0.7rem;
}
.psi-pdf-button {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
@media (max-width: 767.98px) {
    .psi-activity-file-hero {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .psi-activity-meta-grid,
    .psi-activity-narratives {
        grid-template-columns: 1fr;
    }
    .psi-activity-facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.psi-case {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    width: 100%;
    padding: 1rem;
    text-align: left;
    background: #fff;
    border: 1px solid #e4e9ef;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.35rem rgba(18, 38, 63, 0.04);
    transition: border-color 0.15s ease, box-shadow 0.15s ease,
        transform 0.15s ease;
}
.psi-case:hover {
    border-color: rgba(85, 110, 230, 0.4);
    box-shadow: 0 0.35rem 0.8rem rgba(18, 38, 63, 0.08);
    transform: translateY(-1px);
}
.psi-case div:first-child span,
.psi-case small {
    display: block;
    color: #758196;
}
.psi-casefile {
    position: relative;
}
.psi-casefile.is-modal {
    margin-top: 0;
}
.psi-case-identity {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 0.75rem;
    padding: 0.85rem 0.95rem;
    background: radial-gradient(circle at 90% 0, #eee9ff 0, transparent 34%),
        linear-gradient(135deg, #fff, #f7fbff);
    border: 1px solid #dfe7ef;
    border-radius: 14px;
}
.psi-case-identity-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.65rem;
    height: 2.65rem;
    place-items: center;
    color: #675181;
    background: #f0edf8;
    border-radius: 12px;
    font-size: 1.25rem;
}
.psi-case-identity > div:nth-child(2) {
    min-width: 0;
    margin-right: auto;
}
.psi-case-identity small,
.psi-case-identity strong,
.psi-case-identity > div:nth-child(2) > span {
    display: block;
}
.psi-case-identity small {
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #6d7890;
    font-size: 0.65rem;
    text-transform: uppercase;
}
.psi-case-identity strong {
    overflow: hidden;
    margin: 0.12rem 0;
    color: #2d3d53;
    font-size: 0.9rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-case-identity > div:nth-child(2) > span {
    color: #6e7d91;
    font-size: 0.72rem;
}
.psi-case-identity-badges {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.4rem;
}
.psi-case-export-button {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.45rem;
    min-height: 38px;
    padding: 0.5rem 0.75rem;
    color: #684255;
    background: linear-gradient(135deg, #fff8f8, #f8edf1);
    border: 1px solid #e6cbd5;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 800;
    box-shadow: 0 6px 16px rgba(121, 68, 87, 0.08);
}
.psi-case-export-button:hover,
.psi-case-export-button:focus-visible {
    color: #563244;
    background: #f7e9ee;
    border-color: #d7aeba;
}
.psi-case-export-button i {
    color: #b24a64;
    font-size: 1rem;
}
.psi-casefile nav {
    display: flex;
    gap: 0.25rem;
    overflow: auto;
    padding: 0.65rem 1rem;
    background: #fff;
    border: 1px solid #dfe7ef;
    border-top: 0;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 12px 34px rgba(51, 65, 85, 0.055);
}
.psi-casefile nav button {
    padding: 0.55rem 0.75rem;
    white-space: nowrap;
    background: transparent;
    border: 0;
    border-bottom: 0;
    border-radius: 11px;
    color: #68768a;
}
.psi-casefile nav button.active {
    color: #574277;
    background: #f0edf8;
    font-weight: 700;
}
.psi-section {
    padding: 1.25rem 0 2rem;
}
.psi-case-summary-card,
.psi-person-card {
    overflow: hidden;
    border: 1px solid #dfe7ef;
    border-radius: 16px;
    box-shadow: 0 12px 34px rgba(44, 57, 79, 0.055);
}
.psi-case-summary-card .card-body,
.psi-person-card .card-body {
    padding: 1.1rem;
}
.psi-card-title {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 1rem;
}
.psi-card-title > div {
    min-width: 0;
}
.psi-edit-case-button {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.35rem;
    min-height: 34px;
    margin-left: auto;
    padding: 0.4rem 0.65rem;
    color: #604b7b;
    background: #f4f0fa;
    border: 1px solid #ded5eb;
    border-radius: 9px;
    font-size: 0.69rem;
    font-weight: 800;
}
.psi-edit-case-button:hover,
.psi-edit-case-button:focus-visible {
    color: #4d3968;
    background: #ece5f5;
    border-color: #bbaacb;
}
.psi-edit-case-context {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 0.8rem;
    color: #536176;
    background: linear-gradient(135deg, #f7f4fb, #f8fbfd);
    border: 1px solid #e2dced;
    border-radius: 12px;
}
.psi-edit-case-context > span {
    display: grid;
    flex: 0 0 auto;
    width: 2.3rem;
    height: 2.3rem;
    place-items: center;
    color: #675181;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 14px rgba(84, 64, 109, 0.09);
}
.psi-edit-case-context strong,
.psi-edit-case-context p {
    display: block;
    margin: 0;
}
.psi-edit-case-context strong {
    color: #34445a;
    font-size: 0.78rem;
}
.psi-edit-case-context p {
    margin-top: 0.12rem;
    font-size: 0.69rem;
    line-height: 1.4;
}
.psi-edit-case-grid {
    padding: 0.1rem;
}
.psi-change-reason-field {
    padding: 0.85rem;
    background: #fffaf0;
    border: 1px solid #f0dfb8;
    border-radius: 12px;
}
.psi-change-reason-field small {
    display: block;
    margin-top: 0.35rem;
    color: #806b43;
    font-size: 0.66rem;
    line-height: 1.4;
}
.psi-card-title > span {
    display: grid;
    flex: 0 0 auto;
    width: 2.35rem;
    height: 2.35rem;
    place-items: center;
    color: #665080;
    background: #f0edf8;
    border-radius: 10px;
    font-size: 1.05rem;
}
.psi-card-title h5,
.psi-card-title p {
    margin: 0;
}
.psi-card-title h5 {
    color: #2d3d53;
    font-size: 0.9rem;
}
.psi-card-title p {
    margin-top: 0.12rem;
    color: #8290a2;
    font-size: 0.68rem;
}
.psi-case-data-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
    margin: 0;
}
.psi-case-data-grid > div {
    min-width: 0;
    padding: 0.7rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e6ebf1;
    border-radius: 10px;
}
.psi-case-data-grid > .wide {
    grid-column: 1 / -1;
}
.psi-case-data-grid > .emphasized {
    background: #f7f4fb;
    border-color: #e5dfee;
}
.psi-case-data-grid dt,
.psi-person-card dt {
    color: #7b8799;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.psi-case-data-grid dd {
    margin: 0.24rem 0 0;
    color: #34445a;
    font-size: 0.78rem;
    line-height: 1.45;
    white-space: pre-wrap;
}
.psi-person-card > .card-body > strong {
    display: block;
    margin-bottom: 0.75rem;
    color: #2f3f54;
    font-size: 0.82rem;
}
.psi-person-card dl {
    display: grid;
    gap: 0.45rem;
    margin: 0;
}
.psi-person-card dl > div {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid #edf0f4;
}
.psi-person-card dl > div:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}
.psi-person-card dd {
    margin: 0;
    color: #526078;
    font-size: 0.72rem;
    text-align: right;
}
@media (max-width: 767.98px) {
    .psi-case-identity {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .psi-case-identity > div:nth-child(2) {
        width: calc(100% - 3.5rem);
    }
    .psi-case-identity-badges {
        justify-content: flex-start;
        width: 100%;
        padding-left: 3.45rem;
    }
    .psi-case-export-button {
        order: 3;
        width: 100%;
        justify-content: center;
    }
    .psi-case-data-grid {
        grid-template-columns: 1fr;
    }
    .psi-case-data-grid > .wide {
        grid-column: auto;
    }
}
.psi-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    min-height: 150px;
    flex-direction: column;
    padding: 1.5rem;
    text-align: center;
    color: #8490a0;
    background: #fbfcfd;
    border: 1px dashed #d7e0e7;
    border-radius: 11px;
}
.psi-empty > i {
    margin-bottom: 0.2rem;
    color: #8a759f;
    font-size: 1.5rem;
}
.psi-empty > strong {
    color: #566478;
    font-size: 0.78rem;
}
.psi-empty > span {
    font-size: 0.68rem;
}
.psi-private {
    border-color: #a99bd1;
    background: #fbfaff;
}
.psi-private-note {
    margin-top: 0.7rem;
    padding: 0.65rem;
    background: #f4effa;
    color: #513c70;
    border-left: 3px solid #8064a2;
    white-space: pre-wrap;
}
.psi-addendum {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #fff8e7;
}
.psi-form-section {
    counter-increment: form-section;
    margin-bottom: 1.25rem;
    padding: 1.1rem;
    background: linear-gradient(145deg, #fff, #fbfcfe);
    border: 1px solid #e7ecf2;
    border-radius: 15px;
    box-shadow: 0 8px 26px rgba(41, 51, 78, 0.035);
}
.psi-modal-card-xl .card-body {
    counter-reset: form-section;
    padding: 1.4rem;
}
.psi-form-section h6 {
    margin-bottom: 1rem;
    color: #495057;
    font-weight: 600;
}
.psi-form-section h6::before {
    display: inline-grid;
    width: 23px;
    height: 23px;
    place-items: center;
    margin-right: 0.35rem;
    content: counter(form-section);
    color: #fff;
    background: #6a55a0;
    border-radius: 50%;
    font-size: 0.7rem;
}
.psi-correction-reason {
    background: linear-gradient(135deg, #f3f8ff, #f8f3ff);
    border-color: #dbe2f2;
}
.psi-correction-reason p {
    margin: -0.45rem 0 0.75rem 1.9rem;
    color: #718096;
    font-size: 0.76rem;
}
.psi-form-error {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 1rem;
    border-radius: 12px;
}
.psi-form-error > i {
    margin-top: 0.05rem;
    font-size: 1.2rem;
}
.psi-form-error strong,
.psi-form-error span {
    display: block;
}
.psi-form-error span {
    margin-top: 0.1rem;
    font-size: 0.75rem;
}
.psi-date-help {
    display: block;
    margin-top: 0.3rem;
    color: #748297;
    font-size: 0.66rem;
    line-height: 1.35;
}
.psi-device-time-panel {
    display: grid;
    grid-template-columns: auto 1fr auto 1fr auto;
    align-items: center;
    gap: 0.7rem;
    min-height: 62px;
    padding: 0.65rem 0.8rem;
    color: #526078;
    background: #f4f1fb;
    border: 1px solid #e2dbef;
    border-radius: 12px;
}
.psi-device-time-panel > i {
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: #665080;
    background: #fff;
    border-radius: 10px;
    font-size: 1.1rem;
}
.psi-device-time-panel small,
.psi-device-time-panel strong {
    display: block;
}
.psi-device-time-panel small {
    color: #7d8899;
    font-size: 0.62rem;
}
.psi-device-time-panel strong {
    margin-top: 0.1rem;
    color: #2f3f55;
    font-size: 0.82rem;
}
.psi-device-time-panel > span {
    width: 1px;
    height: 28px;
    background: #dcd4e9;
}
.psi-device-time-panel em {
    padding: 0.28rem 0.48rem;
    color: #6b587f;
    background: #fff;
    border-radius: 999px;
    font-size: 0.6rem;
    font-style: normal;
    font-weight: 700;
    white-space: nowrap;
}
.psi-person-context-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}
.psi-form-section-copy {
    margin: -0.45rem 0 0.85rem;
    color: #7c899b;
    font-size: 0.7rem;
}
.psi-followup-form-hint {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.7rem 0.8rem;
    color: #526078;
    background: linear-gradient(135deg, #f5f2fb, #f8fafc);
    border: 1px solid #dfd7eb;
    border-radius: 12px;
    font-size: 0.7rem;
}
.psi-followup-form-hint > i {
    display: grid;
    flex: 0 0 auto;
    width: 2.2rem;
    height: 2.2rem;
    place-items: center;
    color: #665080;
    background: #fff;
    border-radius: 9px;
    font-size: 1.05rem;
}
.psi-followup-form-hint strong {
    display: block;
    margin-bottom: 0.08rem;
    color: #3c4b60;
    font-size: 0.74rem;
}
.psi-person-context-grid > article {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    min-height: 92px;
    padding: 0.8rem;
    color: #526078;
    background: #f8fafc;
    border: 1px solid #dfe6ee;
    border-radius: 13px;
}
.psi-person-context-grid > article > i:first-child {
    display: grid;
    flex: 0 0 auto;
    width: 2.35rem;
    height: 2.35rem;
    place-items: center;
    color: #6e7e92;
    background: #fff;
    border-radius: 10px;
    font-size: 1.1rem;
}
.psi-person-context-grid > article > span {
    min-width: 0;
    margin-right: auto;
}
.psi-person-context-grid small,
.psi-person-context-grid strong,
.psi-person-context-grid em {
    display: block;
}
.psi-person-context-grid small {
    color: #7b8798;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
}
.psi-person-context-grid strong {
    overflow: hidden;
    margin: 0.12rem 0;
    color: #34445a;
    font-size: 0.78rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-person-context-grid em {
    color: #7d899a;
    font-size: 0.66rem;
    font-style: normal;
}
.psi-primary-person {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 34px;
    padding: 0.4rem 0.7rem;
    color: #68768a;
    background: #f8fafc;
    border: 1px solid #dfe5ec;
    border-radius: 9px;
    font-size: 0.72rem;
    font-weight: 700;
}
.psi-primary-person > i {
    font-size: 0.95rem;
}
.psi-primary-person.active {
    color: #5f487d;
    background: #f1edf8;
    border-color: #927ab3;
    box-shadow: 0 5px 15px rgba(103, 81, 133, 0.09);
}
.psi-primary-person:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}
.psi-form-actions {
    position: sticky;
    z-index: 2;
    bottom: -1.4rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.55rem;
    margin: 0 -1.4rem -1.4rem;
    padding: 0.9rem 1.4rem;
    background: rgba(255, 255, 255, 0.96);
    border-top: 1px solid #e8edf3;
    backdrop-filter: blur(8px);
}
@media (max-width: 767.98px) {
    .psi-modal-card-xl .card-body {
        padding: 1rem;
    }
    .psi-person-context-grid {
        grid-template-columns: 1fr;
    }
    .psi-device-time-panel {
        grid-template-columns: auto 1fr auto 1fr;
    }
    .psi-device-time-panel em {
        grid-column: 2 / -1;
        justify-self: start;
    }
    .psi-form-actions {
        bottom: -1rem;
        margin: 0 -1rem -1rem;
        padding: 0.75rem 1rem;
    }
    .psi-form-actions .btn-primary {
        flex: 1 1 100%;
        order: -1;
    }
}
.psi-check {
    position: relative;
    cursor: pointer;
}
.psi-check input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.psi-check span {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 0.4rem 0.7rem;
    color: #74788d;
    background: #f8f9fa;
    border: 1px solid #e5e7eb;
    border-radius: 0.35rem;
}
.psi-check input:checked + span {
    color: var(--bs-primary, #556ee6);
    background: rgba(85, 110, 230, 0.1);
    border-color: rgba(85, 110, 230, 0.35);
}
.psi-timeline {
    position: relative;
    padding-left: 1rem;
    border-left: 2px solid #dce4ec;
}
.psi-timeline article {
    position: relative;
    margin: 0 0 1rem;
    padding: 1rem;
    background: #fff;
    border: 1px solid #e4e9ef;
}
.psi-timeline article:before {
    position: absolute;
    top: 1.15rem;
    left: -1.42rem;
    width: 0.75rem;
    height: 0.75rem;
    content: "";
    background: #386fa4;
    border: 2px solid #f5f7fa;
    border-radius: 50%;
}
.psi-timeline article small,
.psi-timeline article strong {
    display: block;
}
.psi-timeline article p {
    margin: 0.35rem 0 0;
    color: #64748b;
}
dl dt {
    font-size: 0.72rem;
    text-transform: uppercase;
    color: #78859a;
}
dl dd {
    margin-bottom: 1rem;
}
.psi-coordination-inline {
    border-color: #ded5ee;
    background: linear-gradient(135deg, #fbf9fe 0%, #f4f1fa 100%);
}
.psi-coordination-toggle {
    display: flex;
    align-items: center;
    gap: 0.9rem;
}
.psi-coordination-toggle__icon {
    display: grid;
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    color: #6d52a3;
    background: #fff;
    border: 1px solid #e4dcf1;
    border-radius: 14px;
    place-items: center;
    font-size: 1.35rem;
}
.psi-coordination-toggle h6,
.psi-coordination-toggle p {
    margin: 0;
}
.psi-coordination-toggle p {
    margin-top: 0.2rem;
    color: #748198;
}
.psi-coordination-workspace {
    padding-bottom: 2rem;
}
.psi-eyebrow {
    display: block;
    margin-bottom: 0.35rem;
    color: #755ba6;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.12em;
}
.psi-coordination-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
    margin: 1.2rem 0;
}
.psi-coordination-metrics > div {
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: center;
    gap: 0.55rem;
    min-height: 64px;
    padding: 0.8rem 1rem;
    background: #fff;
    border: 1px solid #e4e9f1;
    border-radius: 16px;
}
.psi-coordination-metrics strong {
    color: #293852;
    font-size: 1.25rem;
}
.psi-coordination-metrics small {
    color: #758198;
    font-weight: 600;
}
.psi-status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
}
.psi-status-dot.pending {
    background: #c88b38;
}
.psi-status-dot.accepted {
    background: #3e9879;
}
.psi-status-dot.rejected {
    background: #c75a64;
}
.psi-coordination-compose {
    margin-bottom: 1.25rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, #faf8fd 0%, #f5f8fc 100%);
    border: 1px solid #dcd4eb;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(68, 53, 99, 0.08);
}
.psi-coordination-compose__head {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}
.psi-coordination-compose__head span {
    color: #755ba6;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.1em;
}
.psi-coordination-compose__head h6 {
    margin: 0.2rem 0 0;
}
.psi-coordination-list {
    display: grid;
    gap: 0.9rem;
}
.psi-coordination-card {
    display: grid;
    grid-template-columns: 5px minmax(0, 1fr);
    overflow: hidden;
    background: #fff;
    border: 1px solid #e1e7f0;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(46, 59, 83, 0.06);
}
.psi-coordination-card__rail {
    background: linear-gradient(180deg, #7458a7, #a88ac8);
}
.psi-coordination-card__main {
    padding: 1.1rem 1.25rem;
}
.psi-coordination-card__top {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.psi-coordination-card__top > div > span {
    color: #7a689d;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
}
.psi-coordination-card__top h6 {
    margin: 0.22rem 0 0;
    color: #293852;
    font-size: 1.02rem;
}
.psi-coordination-status {
    align-self: flex-start;
    padding: 0.38rem 0.7rem;
    font-size: 0.73rem;
    font-weight: 800;
    border-radius: 999px;
}
.psi-coordination-status.pending {
    color: #90611e;
    background: #fff3df;
}
.psi-coordination-status.accepted {
    color: #28745d;
    background: #e4f5ef;
}
.psi-coordination-status.rejected {
    color: #a23f49;
    background: #fdecef;
}
.psi-coordination-message {
    margin: 0.8rem 0 1rem;
    color: #536075;
    line-height: 1.55;
}
.psi-coordination-people {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.7rem;
}
.psi-coordination-people > div {
    display: flex;
    gap: 0.65rem;
    padding: 0.7rem;
    background: #f8fafc;
    border-radius: 13px;
}
.psi-coordination-people i {
    color: #7256a5;
    font-size: 1.2rem;
}
.psi-coordination-people span,
.psi-coordination-people small,
.psi-coordination-people strong,
.psi-coordination-people em {
    display: block;
}
.psi-coordination-people small {
    color: #7b879a;
}
.psi-coordination-people strong {
    color: #35435a;
}
.psi-coordination-people em {
    color: #8a95a7;
    font-size: 0.75rem;
    font-style: normal;
}
.psi-coordination-response {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.9rem;
    padding: 0.85rem;
    color: #315e50;
    background: #edf8f4;
    border: 1px solid #d2ede3;
    border-radius: 13px;
}
.psi-coordination-response i {
    font-size: 1.3rem;
}
.psi-coordination-response p {
    margin: 0.2rem 0;
    color: #3d574f;
}
.psi-coordination-response span {
    color: #728c83;
    font-size: 0.74rem;
}
.psi-coordination-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: #7b8799;
    background: linear-gradient(135deg, #fafbfd, #f7f4fb);
    border: 1px dashed #d8dfe9;
    border-radius: 20px;
}
.psi-coordination-empty i {
    color: #8064ae;
    font-size: 2.2rem;
}
.psi-coordination-empty h6 {
    margin: 0.55rem 0 0.25rem;
    color: #344158;
}
.psi-coordination-empty p {
    margin: 0;
}
@media (max-width: 768px) {
    .psi-coordination-metrics,
    .psi-coordination-people {
        grid-template-columns: 1fr;
    }
    .psi-coordination-card__top,
    .psi-coordination-toggle {
        align-items: flex-start;
    }
}
</style>
