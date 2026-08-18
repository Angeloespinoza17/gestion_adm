<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import PsychologyBadge from "./PsychologyBadge.vue";
import PsychologyModal from "./PsychologyModal.vue";
import { usePsychology } from "../../composables/usePsychology";
const props = defineProps({ catalogs: { type: Object, required: true } });
const api = usePsychology();
const list = ref({ data: [], meta: {} });
const selected = ref(null);
const selectedActivity = ref(null);
const activeTab = ref("summary");
const formModal = ref("");
let timer;
const filters = reactive({
    search: "",
    status: "",
    priority: "",
    page: 1,
    per_page: 20,
});
const blankActivity = () => ({
    type: "student_interview",
    interview_number: null,
    activity_on: new Date().toISOString().slice(0, 10),
    starts_at: "",
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
    next_interview_at: "",
    acknowledgement_status: "not_requested",
    acknowledged_name: "",
    acknowledged_rut: "",
    acknowledged_at: "",
    acknowledgement_observations: "",
    attendance_status: "realizada",
    visibility: "psychology_team",
    referral_feedback: "",
    status: "draft",
});
const activity = reactive(blankActivity());
const participantTypeOptions = [
    ["student", "Estudiante"],
    ["guardian", "Apoderado(a)"],
    ["teacher", "Docente"],
    ["education_assistant", "Asistente de la educación"],
    ["other", "Otro"],
];
const intervieweeTypeLabel = (value) =>
    participantTypeOptions.find(([key]) => key === value)?.[1] || value;
const openForm = (name) => {
    formModal.value = name;
};
const closeForm = () => {
    formModal.value = "";
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
watch(
    () => [filters.search, filters.status, filters.priority],
    () => {
        clearTimeout(timer);
        timer = setTimeout(load, 350);
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
        activity.interviewee_name =
            intervieweeType === "student"
                ? selected.value?.student?.name || ""
                : intervieweeType === "guardian"
                ? selected.value?.student?.guardian_name || ""
                : "";
        activity.participant_types = [intervieweeType];
    }
);
watch(activeTab, () => {
    closeForm();
    selectedActivity.value = null;
});
const open = async (item) => {
    const response = await api.get(`/api/psychology/cases/${item.id}`);
    selected.value = response.data || response;
    Object.assign(activity, blankActivity(), {
        interviewee_name: selected.value.student?.name || "",
    });
    activeTab.value = "summary";
};
const reloadCase = async () => {
    if (selected.value) await open(selected.value);
};
const saveActivity = async (finalized = false) => {
    activity.status = finalized ? "finalized" : "draft";
    await api.post(
        `/api/psychology/cases/${selected.value.id}/activities`,
        activity
    );
    await reloadCase();
    Object.assign(activity, blankActivity(), {
        interviewee_name: selected.value.student?.name || "",
    });
    closeForm();
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
            <div>
                <h4>Casos de Psicología</h4>
                <p>Acceso limitado a casos asignados o autorizados.</p>
            </div>
        </div>
        <div v-if="api.error.value" class="alert alert-danger">
            {{ api.error.value }}
        </div>
        <div class="psi-table-card">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <input
                            v-model="filters.search"
                            class="form-control"
                            placeholder="Buscar código o estudiante"
                        />
                    </div>
                    <div class="col-md-3">
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
                            >
                                {{ v }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
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
                            >
                                {{ v }}
                            </option>
                        </select>
                    </div>
                </div>
                <div
                    v-if="!list.data.length && !api.loading.value"
                    class="psi-empty"
                >
                    No hay casos visibles con estos filtros.
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
            </div>
        </div>
        <div v-if="selected" class="psi-casefile">
            <header>
                <button class="btn btn-light btn-sm" @click="selected = null">
                    <i class="bx bx-arrow-back"></i>
                </button>
                <div>
                    <small>FICHA CONFIDENCIAL</small>
                    <h4>{{ selected.code }} · {{ selected.student?.name }}</h4>
                    <span>{{ selected.general_reason }}</span>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <PsychologyBadge
                        :value="selected.priority"
                        priority
                    /><PsychologyBadge :value="selected.status" />
                </div>
            </header>
            <div class="alert alert-warning py-2 small mb-0">
                <i class="bx bx-lock-alt me-1"></i>Información sensible. Todo
                acceso y descarga queda registrado.
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
                        <div class="card">
                            <div class="card-body">
                                <h5>Resumen</h5>
                                <dl>
                                    <dt>Objetivos</dt>
                                    <dd>
                                        {{
                                            selected.objectives || "Por definir"
                                        }}
                                    </dd>
                                    <dt>Próxima acción</dt>
                                    <dd>
                                        {{
                                            selected.next_action ||
                                            "Por definir"
                                        }}
                                        ·
                                        {{
                                            selected.next_review_on ||
                                            "Sin fecha"
                                        }}
                                    </dd>
                                    <dt>Información al apoderado</dt>
                                    <dd>
                                        {{
                                            selected.guardian_information_status
                                        }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5>Equipo</h5>
                                <strong>{{
                                    selected.responsible_user?.name
                                }}</strong>
                                <p class="text-muted small">
                                    Profesional responsable
                                </p>
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
                                            v-model="plan.estimated_start_on"
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
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Versión</th>
                                        <th>Objetivo general</th>
                                        <th>Revisión</th>
                                        <th>Estado</th>
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
                                                    ?.general_objective || "—"
                                            }}
                                        </td>
                                        <td>
                                            {{ item.review_on || "Sin fecha" }}
                                        </td>
                                        <td>
                                            <PsychologyBadge
                                                :value="item.status"
                                            />
                                        </td>
                                    </tr>
                                    <tr v-if="!selected.plans?.length">
                                        <td colspan="4" class="psi-empty">
                                            No hay planes registrados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <section v-else-if="activeTab === 'activities'" class="psi-section">
                <div class="psi-section-head">
                    <div>
                        <h5>Atenciones y entrevistas</h5>
                        <p>
                            Registro cronológico de actuaciones profesionales.
                        </p>
                    </div>
                    <button
                        v-if="catalogs.capabilities.create_activity"
                        class="btn btn-primary"
                        @click="openForm('activity')"
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
                        @click.self="closeForm"
                    >
                        <div class="card psi-modal-card psi-modal-card-xl">
                            <div class="card-body">
                                <div class="psi-modal-heading mb-4">
                                    <div>
                                        <span>Registro confidencial</span>
                                        <h5 class="mb-1">Nueva atención</h5>
                                        <p class="text-muted small mb-0">
                                            Registro de entrevista, acuerdos y
                                            seguimiento. Los campos sensibles se
                                            almacenan cifrados.
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

                                <div class="psi-form-section">
                                    <h6>Identificación de la atención</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
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
                                                        .activity_type || []"
                                                    :key="item.id"
                                                    :value="item.slug"
                                                >
                                                    {{ item.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Fecha</label
                                            >
                                            <input
                                                v-model="activity.activity_on"
                                                type="date"
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-3">
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
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Hora inicio</label
                                            >
                                            <input
                                                v-model="activity.starts_at"
                                                type="time"
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >Hora término</label
                                            >
                                            <input
                                                v-model="activity.ends_at"
                                                type="time"
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-3">
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
                                        <div class="col-md-3">
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
                                    <div
                                        class="alert alert-light border py-2 small"
                                    >
                                        <strong>{{
                                            selected.student?.name
                                        }}</strong>
                                        <span class="text-muted">
                                            ·
                                            {{
                                                selected.student?.course ||
                                                "Curso no informado"
                                            }}
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <label
                                            v-for="option in participantTypeOptions"
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
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Tipo de entrevistado</label
                                            >
                                            <select
                                                v-model="
                                                    activity.interviewee_type
                                                "
                                                class="form-select"
                                            >
                                                <option
                                                    v-for="option in participantTypeOptions"
                                                    :key="option[0]"
                                                    :value="option[0]"
                                                >
                                                    {{ option[1] }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label"
                                                >Nombre entrevistado</label
                                            >
                                            <input
                                                v-model="
                                                    activity.interviewee_name
                                                "
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >RUT entrevistado</label
                                            >
                                            <input
                                                v-model="
                                                    activity.interviewee_rut
                                                "
                                                class="form-control psi-private"
                                                placeholder="Dato cifrado"
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
                                                v-model="activity.participants"
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
                                                >Resumen institucional</label
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
                                                v-model="activity.private_note"
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
                                                v-model="activity.agreements"
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
                                        <div class="col-md-6">
                                            <label class="form-label"
                                                >Próximos pasos</label
                                            >
                                            <textarea
                                                v-model="activity.next_steps"
                                                class="form-control"
                                                rows="2"
                                            ></textarea>
                                        </div>
                                        <div class="col-md-6">
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
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Próxima entrevista</label
                                            >
                                            <input
                                                v-model="
                                                    activity.next_interview_at
                                                "
                                                type="datetime-local"
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-4">
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
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Visibilidad</label
                                            >
                                            <select
                                                v-model="activity.visibility"
                                                class="form-select"
                                            >
                                                <option
                                                    value="private_psychology"
                                                >
                                                    Privada de Psicología
                                                </option>
                                                <option value="psychology_team">
                                                    Equipo de Psicología
                                                </option>
                                                <option
                                                    value="interdisciplinary_team"
                                                >
                                                    Equipo interdisciplinario
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

                                <div class="psi-form-section">
                                    <h6>Constancia de lectura</h6>
                                    <p class="text-muted small">
                                        Registra la constancia del documento; no
                                        reemplaza una firma electrónica avanzada
                                        ni su mecanismo de custodia.
                                    </p>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Estado</label
                                            >
                                            <select
                                                v-model="
                                                    activity.acknowledgement_status
                                                "
                                                class="form-select"
                                            >
                                                <option value="not_requested">
                                                    No solicitada
                                                </option>
                                                <option value="pending">
                                                    Pendiente
                                                </option>
                                                <option value="acknowledged">
                                                    Leída y aceptada
                                                </option>
                                                <option value="declined">
                                                    Rechazada
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label"
                                                >Nombre de quien deja
                                                constancia</label
                                            >
                                            <input
                                                v-model="
                                                    activity.acknowledged_name
                                                "
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"
                                                >RUT</label
                                            >
                                            <input
                                                v-model="
                                                    activity.acknowledged_rut
                                                "
                                                class="form-control psi-private"
                                                placeholder="Dato cifrado"
                                            />
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label"
                                                >Fecha y hora</label
                                            >
                                            <input
                                                v-model="
                                                    activity.acknowledged_at
                                                "
                                                type="datetime-local"
                                                class="form-control"
                                            />
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label"
                                                >Observaciones</label
                                            >
                                            <input
                                                v-model="
                                                    activity.acknowledgement_observations
                                                "
                                                class="form-control"
                                                placeholder="Medio utilizado o motivo de rechazo"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="d-flex flex-wrap justify-content-end gap-2"
                                >
                                    <button
                                        class="btn btn-light"
                                        @click="saveActivity(false)"
                                    >
                                        Guardar borrador</button
                                    ><button
                                        class="btn btn-primary"
                                        @click="saveActivity(true)"
                                    >
                                        Finalizar y confirmar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="psi-table-card table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Registro</th>
                                        <th>Entrevistado(a)</th>
                                        <th>Fecha</th>
                                        <th>Profesional</th>
                                        <th>Próxima entrevista</th>
                                        <th>Estado</th>
                                        <th></th>
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
                                                        : item.type
                                                }}
                                            </strong>
                                            <small>{{ item.type }}</small>
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
                                        <td>{{ item.activity_on }}</td>
                                        <td>
                                            {{
                                                item.responsible_user?.name ||
                                                "—"
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                item.next_interview_at ||
                                                "Sin fecha"
                                            }}
                                        </td>
                                        <td>
                                            <PsychologyBadge
                                                :value="item.status"
                                            />
                                        </td>
                                        <td class="text-end">
                                            <button
                                                class="btn btn-sm btn-light"
                                                @click="selectedActivity = item"
                                            >
                                                Revisar
                                            </button>
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
            <section v-else-if="activeTab === 'referrals'" class="psi-section">
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
            <section v-else-if="activeTab === 'followups'" class="psi-section">
                <div class="alert alert-info">
                    Los seguimientos se gestionan como tareas trazables; crea
                    una tarea de tipo seguimiento en la pestaña Tareas.
                </div>
                <article
                    v-for="item in (selected.tasks || []).filter(
                        (x) => x.type === 'follow_up'
                    )"
                    :key="item.id"
                    class="card mb-2"
                >
                    <div class="card-body d-flex justify-content-between">
                        <div>
                            <strong>{{ item.title }}</strong
                            ><small class="d-block">{{ item.due_at }}</small>
                        </div>
                        <PsychologyBadge :value="item.status" />
                    </div>
                </article>
            </section>
            <section
                v-else-if="activeTab === 'coordination'"
                class="psi-section"
            >
                <article
                    v-for="item in (selected.activities || []).filter(
                        (x) =>
                            String(x.type).includes('coordination') ||
                            String(x.type).includes('meeting')
                    )"
                    :key="item.id"
                    class="card mb-2"
                >
                    <div class="card-body">
                        <strong>{{ item.type }}</strong>
                        <p>{{ item.institutional_summary }}</p>
                    </div>
                </article>
                <div class="alert alert-light">
                    Registra nuevas coordinaciones desde “Sesiones y
                    entrevistas” seleccionando el tipo correspondiente.
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
                            catalogs.capabilities.risk && formModal === 'risk'
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
                            <table class="table table-hover align-middle mb-0">
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
                                            !selected.risk_assessments?.length
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
                                <button class="psi-close" @click="closeForm">
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
                                        <option value="medium">Media</option>
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
                                <td>{{ item.due_at }}</td>
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
            <section v-else-if="activeTab === 'documents'" class="psi-section">
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
                                    <option value="protocol">Protocolo</option>
                                    <option value="other">Otro</option></select
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
                            <table class="table table-hover align-middle mb-0">
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
                                        v-for="item in selected.documents || []"
                                        :key="item.id"
                                    >
                                        <td>
                                            <strong
                                                ><i class="bx bx-file me-1"></i
                                                >{{
                                                    item.original_name
                                                }}</strong
                                            >
                                        </td>
                                        <td>{{ item.category }}</td>
                                        <td>{{ item.visibility }}</td>
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
                                                @click="downloadDocument(item)"
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
            <section v-else-if="activeTab === 'feedback'" class="psi-section">
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
                        ['feedback', 'consent', 'external'].includes(formModal)
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
                                                Retroalimentación compartible
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
                                            v-model="consent.guardian_informed"
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
                                            v-model="consent.consent_required"
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
                                            v-model="external.guardian_informed"
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
                                v-for="item in selected.shared_feedback || []"
                                :key="`feedback-${item.id}`"
                            >
                                <td><strong>Retroalimentación</strong></td>
                                <td>{{ item.content }}</td>
                                <td>{{ item.created_at }}</td>
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
                                <td>{{ item.action_type }}</td>
                                <td>
                                    {{ item.informed_at || item.created_at }}
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
                                <td>{{ item.referred_on }}</td>
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
            <section v-else-if="activeTab === 'timeline'" class="psi-section">
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
                            String(b.event_at).localeCompare(String(a.event_at))
                        )"
                        :key="`${item.event_type}-${item.id}`"
                    >
                        <small>{{ item.event_at }}</small
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
            <section v-else-if="activeTab === 'closure'" class="psi-section">
                <div class="psi-section-head">
                    <div>
                        <h5>Cierre del caso</h5>
                        <p>
                            Conclusión profesional y conservación del historial.
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
                                    <span>Acción irreversible y auditada</span>
                                    <h5>Cierre profesional</h5>
                                </div>
                                <button class="psi-close" @click="closeForm">
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
                    Caso cerrado el {{ selected.closed_at }}. El historial
                    permanece disponible.
                </div>
                <div
                    v-else-if="!catalogs.capabilities.close_case"
                    class="alert alert-light"
                >
                    No tienes permiso para cerrar este caso.
                </div>
            </section>
            <section v-else class="psi-section">
                <div class="psi-empty">
                    Esta pestaña consolida registros vinculados al caso. Usa las
                    acciones disponibles o la línea de tiempo para consultar el
                    historial.
                </div>
            </section>
        </div>

        <PsychologyModal
            v-if="selectedActivity"
            eyebrow="Registro profesional"
            :title="
                selectedActivity.interview_number
                    ? `Entrevista N° ${selectedActivity.interview_number}`
                    : 'Detalle de atención'
            "
            @close="selectedActivity = null"
        >
            <div class="psi-activity-detail">
                <div>
                    <span>Entrevistado(a)</span>
                    <strong>{{
                        selectedActivity.interviewee_name || "—"
                    }}</strong>
                    <small>{{
                        intervieweeTypeLabel(selectedActivity.interviewee_type)
                    }}</small>
                </div>
                <div>
                    <span>Fecha y profesional</span>
                    <strong>{{ selectedActivity.activity_on }}</strong>
                    <small>{{ selectedActivity.responsible_user?.name }}</small>
                </div>
                <div class="wide">
                    <span>Resumen institucional</span>
                    <p>
                        {{
                            selectedActivity.institutional_summary ||
                            "Sin resumen."
                        }}
                    </p>
                </div>
                <div
                    v-if="selectedActivity.general_background"
                    class="wide private"
                >
                    <span
                        ><i class="bx bx-lock-alt me-1"></i>Antecedentes
                        generales</span
                    >
                    <p>{{ selectedActivity.general_background }}</p>
                </div>
                <div v-if="selectedActivity.private_note" class="wide private">
                    <span
                        ><i class="bx bx-lock-alt me-1"></i>Nota profesional
                        privada</span
                    >
                    <p>{{ selectedActivity.private_note }}</p>
                </div>
                <div class="wide">
                    <span>Acuerdos</span>
                    <p>
                        {{
                            selectedActivity.agreements ||
                            "Sin acuerdos registrados."
                        }}
                    </p>
                </div>
            </div>
        </PsychologyModal>
    </div>
</template>
<style scoped>
.psi-toolbar {
    display: flex;
    justify-content: space-between;
}
.psi-toolbar h4 {
    margin: 0;
}
.psi-toolbar p {
    color: #78859a;
}
.psi-table-card {
    background: #fff;
    border: 1px solid #dfe7ef;
    border-radius: 20px;
    box-shadow: 0 18px 48px rgba(39, 48, 77, 0.075);
    overflow: hidden;
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
    border: 0;
    border-radius: 22px;
    box-shadow: 0 30px 90px rgba(15, 23, 42, 0.35);
}
.psi-modal-card-xl {
    width: min(1160px, 98vw);
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
.psi-activity-detail {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}
.psi-activity-detail > div {
    padding: 0.85rem;
    border: 1px solid #e8edf3;
    border-radius: 12px;
}
.psi-activity-detail .wide {
    grid-column: 1 / -1;
}
.psi-activity-detail .private {
    color: #584773;
    background: #f8f5fc;
    border-color: #e2d9ef;
}
.psi-activity-detail span,
.psi-activity-detail small {
    display: block;
    color: #7b8796;
    font-size: 0.72rem;
}
.psi-activity-detail p {
    margin: 0.35rem 0 0;
    white-space: pre-wrap;
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
    margin-top: 1.5rem;
}
.psi-casefile > header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: radial-gradient(circle at 90% 0, #eee9ff 0, transparent 34%),
        linear-gradient(135deg, #fff, #f7fbff);
    border: 1px solid #dfe7ef;
    border-radius: 20px 20px 0 0;
}
.psi-casefile > header small {
    font-weight: 800;
    letter-spacing: 0.1em;
    color: #6d7890;
}
.psi-casefile > header h4 {
    margin: 0.15rem 0;
}
.psi-casefile > header span {
    color: #6e7d91;
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
.psi-empty {
    padding: 3rem;
    text-align: center;
    color: #8490a0;
    background: #fff;
    border: 1px dashed #d7dde5;
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
    padding: 1rem;
    border: 1px solid #e7ecf2;
    border-radius: 15px;
}
.psi-modal-card-xl .card-body {
    counter-reset: form-section;
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
</style>
