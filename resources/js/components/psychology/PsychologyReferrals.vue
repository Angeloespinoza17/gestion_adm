<script setup>
import { onMounted, reactive, ref, watch } from "vue";
import PsychologyBadge from "./PsychologyBadge.vue";
import PsychologyModal from "./PsychologyModal.vue";
import { usePsychology } from "../../composables/usePsychology";
const props = defineProps({ catalogs: { type: Object, required: true } });
const emit = defineEmits(["changed"]);
const api = usePsychology();
const list = ref({ data: [], meta: {} });
const showForm = ref(false);
const selected = ref(null);
const students = ref([]);
let timer;
const filters = reactive({
    search: "",
    status: "",
    priority: "",
    page: 1,
    per_page: 20,
});
const blank = () => ({
    student_profile_id: null,
    course_section_id: null,
    suggested_urgency: "medium",
    primary_reason: "",
    secondary_reasons: "",
    observed_facts: "",
    approximate_started_on: "",
    people_involved: "",
    measures_taken: "",
    known_previous_interventions: "",
    observed_risk_indicators: "",
    immediate_response_needed: false,
    guardian_informed: false,
    guardian_contact_status: "not_contacted",
    observations: "",
    purpose_declaration_accepted: false,
    submit: false,
});
const form = reactive(blank());
const decision = reactive({
    status: "under_review",
    reason: "",
    shared_note: "",
    internal_note: "",
    information_response: "",
    professional_priority: "medium",
});
const caseForm = reactive({
    responsible_user_id: null,
    priority: "medium",
    confidentiality: "psychology_team",
    general_reason: "",
    objectives: "",
    next_action: "",
    next_review_on: "",
    guardian_information_status: "pending",
});
const load = async () => {
    list.value = await api.get("/api/psychology/referrals", filters);
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
const searchStudents = async () => {
    if ((form.student_search || "").length < 2) return;
    students.value = (
        await api.get("/api/psychology/students", {
            search: form.student_search,
        })
    ).data;
};
const chooseStudent = (student) => {
    form.student_profile_id = student.id;
    form.course_section_id = student.course_section_id;
    form.student_search = `${student.name} · ${student.course || "Sin curso"}`;
    students.value = [];
    form.student_warning = student.active_case
        ? "Esta estudiante ya tiene un caso activo. La derivación no se bloqueará."
        : "";
};
const save = async (submit) => {
    form.submit = submit;
    await api.post("/api/psychology/referrals", form);
    Object.assign(form, blank());
    showForm.value = false;
    await load();
    emit("changed");
};
const open = async (item) => {
    selected.value =
        (await api.get(`/api/psychology/referrals/${item.id}`)).data ||
        (await api.get(`/api/psychology/referrals/${item.id}`));
};
const transition = async () => {
    await api.post(
        `/api/psychology/referrals/${selected.value.id}/transition`,
        decision
    );
    selected.value = null;
    await load();
    emit("changed");
};
const assign = async () => {
    await api.post(`/api/psychology/referrals/${selected.value.id}/assign`, {
        user_id: decision.user_id,
        reason: decision.reason,
        professional_priority: decision.professional_priority,
    });
    selected.value = null;
    await load();
    emit("changed");
};
const openCase = async () => {
    await api.post(
        `/api/psychology/referrals/${selected.value.id}/open-case`,
        caseForm
    );
    selected.value = null;
    await load();
    emit("changed");
};
const respondInformation = async () => {
    await api.post(
        `/api/psychology/referrals/${selected.value.id}/transition`,
        {
            status: "submitted",
            information_response: decision.information_response,
        }
    );
    selected.value = null;
    await load();
};
const submitDraft = async (item) => {
    await api.post(`/api/psychology/referrals/${item.id}/transition`, {
        status: "submitted",
    });
    await load();
};
onMounted(load);
</script>
<template>
    <div>
        <div class="psi-toolbar">
            <div>
                <h4>Derivaciones</h4>
                <p>Bandeja segura con paginación del servidor.</p>
            </div>
            <button
                v-if="catalogs.capabilities.create_referral"
                class="btn btn-primary"
                @click="showForm = true"
            >
                <i class="bx bx-plus me-1"></i>Nueva derivación
            </button>
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
                            placeholder="Buscar por código o estudiante"
                        />
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.status" class="form-select">
                            <option value="">Todos los estados</option>
                            <option
                                v-for="value in [
                                    'draft',
                                    'submitted',
                                    'under_review',
                                    'information_requested',
                                    'accepted',
                                    'linked_to_existing_case',
                                    'redirected',
                                    'rejected',
                                    'duplicated',
                                    'completed',
                                ]"
                                :key="value"
                                :value="value"
                            >
                                {{ value }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.priority" class="form-select">
                            <option value="">Toda prioridad</option>
                            <option
                                v-for="value in [
                                    'low',
                                    'medium',
                                    'high',
                                    'critical',
                                ]"
                                :key="value"
                                :value="value"
                            >
                                {{ value }}
                            </option>
                        </select>
                    </div>
                </div>
                <div v-if="api.loading.value" class="psi-skeleton"></div>
                <div v-else-if="!list.data.length" class="psi-empty">
                    <i class="bx bx-inbox"></i
                    ><strong>No hay derivaciones</strong
                    ><span
                        >Ajusta los filtros o crea la primera derivación.</span
                    >
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Código / estudiante</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Profesional</th>
                                <th>Actualización</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in list.data" :key="item.id">
                                <td>
                                    <strong>{{
                                        item.code || "Borrador"
                                    }}</strong
                                    ><small
                                        >{{ item.student?.name }} ·
                                        {{
                                            item.course?.name || "Sin curso"
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <PsychologyBadge :value="item.status" />
                                </td>
                                <td>
                                    <PsychologyBadge
                                        :value="
                                            item.professional_priority ||
                                            item.suggested_urgency
                                        "
                                        priority
                                    />
                                </td>
                                <td>
                                    {{
                                        item.assigned_user?.name ||
                                        "Sin asignar"
                                    }}
                                </td>
                                <td>
                                    {{
                                        new Date(
                                            item.updated_at
                                        ).toLocaleString("es-CL")
                                    }}
                                </td>
                                <td class="text-end">
                                    <button
                                        v-if="item.status === 'draft'"
                                        class="btn btn-sm btn-outline-primary me-1"
                                        @click="submitDraft(item)"
                                    >
                                        Enviar</button
                                    ><button
                                        class="btn btn-sm btn-light"
                                        @click="open(item)"
                                    >
                                        Ver
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="list.meta?.last_page > 1"
                    class="d-flex justify-content-between align-items-center"
                >
                    <span
                        >Página {{ list.meta.current_page }} de
                        {{ list.meta.last_page }}</span
                    >
                    <div>
                        <button
                            class="btn btn-sm btn-light me-1"
                            :disabled="filters.page <= 1"
                            @click="
                                filters.page--;
                                load();
                            "
                        >
                            Anterior</button
                        ><button
                            class="btn btn-sm btn-light"
                            :disabled="filters.page >= list.meta.last_page"
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
            v-if="showForm"
            eyebrow="Solicitud confidencial"
            title="Nueva derivación a Psicología"
            @close="showForm = false"
        >
            <form @submit.prevent="save(false)">
                <div class="alert alert-warning small">
                    <strong>Registro responsable:</strong> describe hechos
                    observables, evita juicios o diagnósticos sin respaldo,
                    omite información innecesaria y usa lenguaje respetuoso.
                </div>
                <label class="form-label">Estudiante *</label
                ><input
                    v-model="form.student_search"
                    class="form-control"
                    placeholder="Escribe nombre o RUT"
                    @input="searchStudents"
                />
                <div v-if="students.length" class="psi-results">
                    <button
                        v-for="student in students"
                        :key="student.id"
                        type="button"
                        @click="chooseStudent(student)"
                    >
                        <strong>{{ student.name }}</strong
                        ><span>{{ student.course }} · {{ student.rut }}</span>
                    </button>
                </div>
                <div
                    v-if="form.student_warning"
                    class="alert alert-info mt-2 py-2"
                >
                    {{ form.student_warning }}
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">Motivo principal *</label
                        ><select
                            v-model="form.primary_reason"
                            class="form-select"
                            required
                        >
                            <option value="">Selecciona</option>
                            <option
                                v-for="item in catalogs.catalogs
                                    .referral_reason || []"
                                :key="item.id"
                                :value="item.name"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Urgencia sugerida *</label
                        ><select
                            v-model="form.suggested_urgency"
                            class="form-select"
                        >
                            <option value="low">Baja</option>
                            <option value="medium">Media</option>
                            <option value="high">Alta</option>
                            <option value="critical">Crítica</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"
                            >Descripción objetiva de hechos *</label
                        ><textarea
                            v-model="form.observed_facts"
                            class="form-control"
                            rows="4"
                            minlength="10"
                            required
                        ></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Medidas ya adoptadas</label
                        ><textarea
                            v-model="form.measures_taken"
                            class="form-control"
                            rows="3"
                        ></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"
                            >Indicadores de riesgo observados</label
                        ><textarea
                            v-model="form.observed_risk_indicators"
                            class="form-control"
                            rows="3"
                        ></textarea>
                    </div>
                    <div class="col-md-6 form-check ms-2">
                        <input
                            id="immediate"
                            v-model="form.immediate_response_needed"
                            type="checkbox"
                            class="form-check-input"
                        /><label for="immediate" class="form-check-label"
                            >Requiere respuesta inmediata</label
                        >
                    </div>
                    <div class="col-md-5 form-check">
                        <input
                            id="guardian"
                            v-model="form.guardian_informed"
                            type="checkbox"
                            class="form-check-input"
                        /><label for="guardian" class="form-check-label"
                            >Apoderado informado</label
                        >
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input
                            id="purpose"
                            v-model="form.purpose_declaration_accepted"
                            type="checkbox"
                            class="form-check-input"
                            required
                        /><label for="purpose" class="form-check-label"
                            >Declaro que esta información se registra con
                            finalidad institucional y de resguardo.</label
                        >
                    </div>
                </div>
                <div class="psi-form-actions">
                    <button
                        type="button"
                        class="btn btn-light"
                        @click="showForm = false"
                    >
                        Cancelar</button
                    ><button
                        class="btn btn-outline-primary"
                        :disabled="api.loading.value"
                    >
                        Guardar borrador</button
                    ><button
                        type="button"
                        class="btn btn-primary"
                        :disabled="api.loading.value"
                        @click="save(true)"
                    >
                        Enviar derivación
                    </button>
                </div>
            </form>
        </PsychologyModal>

        <PsychologyModal
            v-if="selected"
            :eyebrow="selected.code || 'Borrador'"
            :title="selected.student?.name || 'Detalle de derivación'"
            @close="selected = null"
        >
            <div>
                <div class="d-flex gap-2 mb-3">
                    <PsychologyBadge :value="selected.status" /><PsychologyBadge
                        :value="
                            selected.professional_priority ||
                            selected.suggested_urgency
                        "
                        priority
                    />
                </div>
                <h6>Motivo general</h6>
                <p>{{ selected.primary_reason }}</p>
                <h6>Hechos observados</h6>
                <p class="psi-pre">{{ selected.observed_facts }}</p>
                <div
                    v-if="
                        selected.status === 'information_requested' &&
                        catalogs.capabilities.create_referral
                    "
                    class="alert alert-info"
                >
                    <strong>Solicitud de antecedentes</strong>
                    <p>
                        {{
                            selected.information_request ||
                            selected.shared_decision_note
                        }}
                    </p>
                    <textarea
                        v-model="decision.information_response"
                        class="form-control mb-2"
                        rows="3"
                        placeholder="Complemento auditable; el texto original no se modifica"
                    ></textarea
                    ><button
                        class="btn btn-primary"
                        @click="respondInformation"
                    >
                        Enviar antecedentes
                    </button>
                </div>
                <div
                    v-if="catalogs.capabilities.assign"
                    class="border-top pt-3 mt-3"
                >
                    <h6>Asignar profesional</h6>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <select
                                v-model="decision.user_id"
                                class="form-select"
                            >
                                <option :value="undefined">
                                    Selecciona profesional
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
                        <div class="col-md-4">
                            <input
                                v-model="decision.reason"
                                class="form-control"
                                placeholder="Motivo de asignación"
                            />
                        </div>
                        <div class="col-md-3">
                            <button
                                class="btn btn-primary w-100"
                                @click="assign"
                            >
                                Asignar
                            </button>
                        </div>
                    </div>
                </div>
                <div
                    v-if="catalogs.capabilities.manage_referrals"
                    class="border-top pt-3 mt-3"
                >
                    <h6>Decisión profesional</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select
                                v-model="decision.status"
                                class="form-select"
                            >
                                <option value="under_review">
                                    En revisión
                                </option>
                                <option value="information_requested">
                                    Solicitar antecedentes
                                </option>
                                <option value="accepted">Aceptar</option>
                                <option value="redirected">Redirigir</option>
                                <option value="rejected">Rechazar</option>
                                <option value="duplicated">Duplicada</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <input
                                v-model="decision.reason"
                                class="form-control"
                                placeholder="Fundamento obligatorio"
                            />
                        </div>
                        <div class="col-12">
                            <textarea
                                v-model="decision.shared_note"
                                class="form-control"
                                rows="2"
                                placeholder="Retroalimentación compartible con Inspectoría"
                            ></textarea>
                        </div>
                        <div class="col-12">
                            <textarea
                                v-model="decision.internal_note"
                                class="form-control"
                                rows="2"
                                placeholder="Nota interna (no visible para Inspectoría)"
                            ></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary" @click="transition">
                                Registrar decisión
                            </button>
                        </div>
                    </div>
                </div>
                <div
                    v-if="
                        selected.status === 'accepted' &&
                        catalogs.capabilities.create_case
                    "
                    class="border-top pt-3 mt-3"
                >
                    <h6>Abrir caso</h6>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <select
                                v-model="caseForm.responsible_user_id"
                                class="form-select"
                            >
                                <option :value="null">
                                    Profesional asignada
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
                        <div class="col-md-3">
                            <select
                                v-model="caseForm.priority"
                                class="form-select"
                            >
                                <option value="low">Baja</option>
                                <option value="medium">Media</option>
                                <option value="high">Alta</option>
                                <option value="critical">Crítica</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select
                                v-model="caseForm.confidentiality"
                                class="form-select"
                            >
                                <option value="private_psychology">
                                    Privada
                                </option>
                                <option value="psychology_team">
                                    Equipo Psicología
                                </option>
                                <option value="interdisciplinary_team">
                                    Interdisciplinaria
                                </option>
                            </select>
                        </div>
                        <div class="col-12">
                            <input
                                v-model="caseForm.general_reason"
                                class="form-control"
                                placeholder="Motivo general del caso"
                            />
                        </div>
                        <div class="col-12">
                            <textarea
                                v-model="caseForm.objectives"
                                class="form-control"
                                rows="2"
                                placeholder="Objetivos iniciales"
                            ></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-success" @click="openCase">
                                Abrir ficha de caso
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </PsychologyModal>
    </div>
</template>
<style scoped>
.psi-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 1.35rem 1.5rem;
    background: radial-gradient(circle at 92% 0, #eee9ff 0, transparent 38%),
        linear-gradient(135deg, #fff, #f7fbff);
    border: 1px solid #dfe7ef;
    border-radius: 20px;
    box-shadow: 0 18px 48px rgba(39, 48, 77, 0.075);
}
.psi-toolbar h4 {
    margin: 0;
    color: #23354d;
}
.psi-toolbar p {
    margin: 0.2rem 0 0;
    color: #758298;
    font-size: 0.8rem;
}
td small {
    display: block;
    color: #7c8797;
}
.psi-empty {
    display: grid;
    place-items: center;
    padding: 3rem;
    color: #8590a0;
}
.psi-empty i {
    font-size: 2rem;
}
.psi-empty strong,
.psi-empty span {
    display: block;
}
.psi-skeleton {
    height: 240px;
    background: linear-gradient(90deg, #f1f3f5, #fafafa, #f1f3f5);
    background-size: 200% 100%;
    animation: pulse 1.3s infinite;
}
.psi-table-card {
    background: #fff;
    border: 1px solid #dfe7ef;
    border-radius: 20px;
    box-shadow: 0 18px 48px rgba(39, 48, 77, 0.075);
    overflow: hidden;
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
.psi-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 1rem;
}
.psi-results {
    position: absolute;
    z-index: 2;
    width: min(650px, 90%);
    background: white;
    border: 1px solid #dfe4ea;
    box-shadow: 0 8px 20px #23354d1c;
}
.psi-results button {
    display: block;
    width: 100%;
    padding: 0.65rem;
    text-align: left;
    background: #fff;
    border: 0;
    border-bottom: 1px solid #edf0f3;
}
.psi-results span {
    display: block;
    color: #7b8798;
    font-size: 0.75rem;
}
.psi-pre {
    white-space: pre-wrap;
}
@keyframes pulse {
    to {
        background-position: -200% 0;
    }
}
</style>
