<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";
import Layout from "../../layouts/main.vue";

const loading = ref(false);
const savingId = ref(null);
const error = ref("");
const direction = ref("incoming");
const status = ref("");
const page = ref({ data: [], current_page: 1, last_page: 1, total: 0 });
const responses = reactive({});

const typeLabels = {
    meeting: "Reunión de coordinación",
    information_request: "Solicitud de antecedentes",
    case_review: "Revisión de caso",
    classroom_support: "Apoyo en aula",
    family_support: "Coordinación con familia",
    protocol_coordination: "Coordinación de protocolo",
    other: "Otra coordinación",
};
const statusLabels = {
    pending: "Pendiente",
    accepted: "Aceptada",
    rejected: "Rechazada",
};
const emptyTitle = computed(() =>
    direction.value === "incoming"
        ? "No tienes solicitudes recibidas"
        : "No has enviado solicitudes"
);
const formatDate = (value, withTime = false) => {
    if (!value) return "Sin fecha propuesta";
    const raw = String(value);
    const date = new Date(raw.length === 10 ? `${raw}T12:00:00` : raw);
    return new Intl.DateTimeFormat("es-CL", {
        dateStyle: "medium",
        ...(withTime ? { timeStyle: "short" } : {}),
    }).format(date);
};
const apiMessage = (exception) =>
    exception?.response?.data?.message ||
    Object.values(exception?.response?.data?.errors || {})?.[0]?.[0] ||
    "No fue posible completar la solicitud.";

const load = async (targetPage = 1) => {
    loading.value = true;
    error.value = "";
    try {
        const { data } = await axios.get("/api/psychology-coordinations/mine", {
            params: {
                direction: direction.value,
                status: status.value || undefined,
                page: targetPage,
                per_page: 20,
            },
        });
        page.value = data;
    } catch (exception) {
        error.value = apiMessage(exception);
    } finally {
        loading.value = false;
    }
};
const changeDirection = (value) => {
    direction.value = value;
    status.value = "";
    load(1);
};
const respond = async (item, responseStatus) => {
    const message = String(responses[item.id] || "").trim();
    if (!message) {
        error.value =
            "Escribe una respuesta antes de aceptar o rechazar la solicitud.";
        return;
    }
    savingId.value = item.id;
    error.value = "";
    try {
        await axios.patch(`/api/psychology-coordinations/${item.id}/respond`, {
            status: responseStatus,
            response_message: message,
        });
        delete responses[item.id];
        await load(page.value.current_page || 1);
    } catch (exception) {
        error.value = apiMessage(exception);
    } finally {
        savingId.value = null;
    }
};

onMounted(() => load());
</script>

<template>
    <Layout>
        <main class="coordination-inbox">
            <header class="coordination-hero">
                <div class="coordination-hero__icon">
                    <i class="bx bx-conversation"></i>
                </div>
                <div>
                    <span>COLABORACIÓN INSTITUCIONAL</span>
                    <h1>Mis coordinaciones</h1>
                    <p>
                        Revisa y responde solicitudes de trabajo colaborativo
                        sin acceder a antecedentes confidenciales del caso.
                    </p>
                </div>
                <div class="coordination-hero__count">
                    <strong>{{ page.total || 0 }}</strong>
                    <small>{{
                        direction === "incoming" ? "recibidas" : "enviadas"
                    }}</small>
                </div>
            </header>

            <section class="coordination-toolbar">
                <div class="coordination-segmented">
                    <button
                        type="button"
                        :class="{ active: direction === 'incoming' }"
                        @click="changeDirection('incoming')"
                    >
                        <i class="bx bx-inbox"></i> Recibidas
                    </button>
                    <button
                        type="button"
                        :class="{ active: direction === 'outgoing' }"
                        @click="changeDirection('outgoing')"
                    >
                        <i class="bx bx-send"></i> Enviadas
                    </button>
                </div>
                <select v-model="status" class="form-select" @change="load(1)">
                    <option value="">Todos los estados</option>
                    <option value="pending">Pendientes</option>
                    <option value="accepted">Aceptadas</option>
                    <option value="rejected">Rechazadas</option>
                </select>
            </section>

            <div v-if="error" class="alert alert-danger coordination-alert">
                <i class="bx bx-error-circle"></i>{{ error }}
            </div>

            <section v-if="loading" class="coordination-state">
                <span class="spinner-border spinner-border-sm"></span>
                Cargando coordinaciones…
            </section>
            <section
                v-else-if="!page.data?.length"
                class="coordination-state empty"
            >
                <i class="bx bx-message-square-dots"></i>
                <h2>{{ emptyTitle }}</h2>
                <p>
                    Las solicitudes aparecerán aquí con su estado y
                    trazabilidad.
                </p>
            </section>
            <section v-else class="coordination-grid">
                <article
                    v-for="item in page.data"
                    :key="item.id"
                    class="coordination-card"
                >
                    <div class="coordination-card__top">
                        <div>
                            <span>{{
                                typeLabels[item.coordination_type] ||
                                "Coordinación"
                            }}</span>
                            <h2>{{ item.subject }}</h2>
                        </div>
                        <span class="coordination-pill" :class="item.status">
                            {{ statusLabels[item.status] || item.status }}
                        </span>
                    </div>

                    <div class="coordination-person">
                        <div><i class="bx bx-user"></i></div>
                        <span>
                            <small>{{
                                direction === "incoming"
                                    ? "Solicita"
                                    : "Destinatario(a)"
                            }}</small>
                            <strong>{{
                                direction === "incoming"
                                    ? item.requester?.name
                                    : item.recipient?.name
                            }}</strong>
                        </span>
                        <span class="coordination-date">
                            <small>Fecha propuesta</small>
                            <strong>{{
                                formatDate(item.requested_for)
                            }}</strong>
                        </span>
                    </div>

                    <div class="coordination-request">
                        <small>REQUERIMIENTO</small>
                        <p>{{ item.request_message }}</p>
                    </div>

                    <div
                        v-if="item.response_message"
                        class="coordination-answer"
                    >
                        <i class="bx bx-message-check"></i>
                        <div>
                            <small
                                >Respuesta registrada ·
                                {{ formatDate(item.responded_at, true) }}</small
                            >
                            <p>{{ item.response_message }}</p>
                        </div>
                    </div>

                    <div
                        v-else-if="
                            direction === 'incoming' &&
                            item.status === 'pending'
                        "
                        class="coordination-response-form"
                    >
                        <label :for="`response-${item.id}`"
                            >Tu respuesta institucional</label
                        >
                        <textarea
                            :id="`response-${item.id}`"
                            v-model="responses[item.id]"
                            class="form-control"
                            rows="3"
                            maxlength="4000"
                            placeholder="Confirma la coordinación o explica por qué no es posible aceptarla."
                        ></textarea>
                        <div>
                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                :disabled="savingId === item.id"
                                @click="respond(item, 'rejected')"
                            >
                                <i class="bx bx-x-circle me-1"></i>Rechazar
                            </button>
                            <button
                                type="button"
                                class="btn btn-success"
                                :disabled="savingId === item.id"
                                @click="respond(item, 'accepted')"
                            >
                                <i class="bx bx-check-circle me-1"></i>
                                {{
                                    savingId === item.id
                                        ? "Guardando…"
                                        : "Aceptar coordinación"
                                }}
                            </button>
                        </div>
                    </div>
                </article>
            </section>

            <nav v-if="page.last_page > 1" class="coordination-pagination">
                <button
                    type="button"
                    class="btn btn-light"
                    :disabled="page.current_page <= 1"
                    @click="load(page.current_page - 1)"
                >
                    Anterior
                </button>
                <span
                    >Página {{ page.current_page }} de
                    {{ page.last_page }}</span
                >
                <button
                    type="button"
                    class="btn btn-light"
                    :disabled="page.current_page >= page.last_page"
                    @click="load(page.current_page + 1)"
                >
                    Siguiente
                </button>
            </nav>
        </main>
    </Layout>
</template>

<style scoped>
.coordination-inbox {
    min-height: calc(100vh - 100px);
    padding: 1.5rem;
    color: #344158;
    background: radial-gradient(
            circle at 0 0,
            rgba(91, 118, 202, 0.09),
            transparent 30%
        ),
        radial-gradient(
            circle at 100% 10%,
            rgba(122, 87, 159, 0.1),
            transparent 28%
        ),
        #f5f7fb;
}
.coordination-hero {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    max-width: 1180px;
    margin: 0 auto 1rem;
    padding: 1.45rem;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #e0e6ef;
    border-radius: 24px;
    box-shadow: 0 20px 45px rgba(48, 62, 87, 0.08);
}
.coordination-hero__icon {
    display: grid;
    width: 62px;
    height: 62px;
    flex: 0 0 62px;
    color: #6f55a0;
    background: #f0ebf7;
    border-radius: 19px;
    place-items: center;
    font-size: 1.75rem;
}
.coordination-hero span {
    color: #7258a1;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.12em;
}
.coordination-hero h1 {
    margin: 0.2rem 0;
    color: #273650;
    font-size: 1.65rem;
}
.coordination-hero p {
    margin: 0;
    color: #758198;
}
.coordination-hero__count {
    margin-left: auto;
    text-align: center;
}
.coordination-hero__count strong {
    display: block;
    color: #59417f;
    font-size: 1.8rem;
}
.coordination-hero__count small {
    color: #7e8999;
}
.coordination-toolbar {
    display: flex;
    justify-content: space-between;
    max-width: 1180px;
    margin: 0 auto 1rem;
    padding: 0.6rem;
    background: #fff;
    border: 1px solid #e0e6ee;
    border-radius: 18px;
}
.coordination-toolbar .form-select {
    width: 230px;
    border-radius: 13px;
}
.coordination-segmented {
    display: flex;
    gap: 0.35rem;
}
.coordination-segmented button {
    padding: 0.68rem 1rem;
    color: #67748a;
    background: transparent;
    border: 0;
    border-radius: 12px;
    font-weight: 700;
}
.coordination-segmented button.active {
    color: #59417f;
    background: #f0ebf7;
}
.coordination-alert,
.coordination-grid,
.coordination-state,
.coordination-pagination {
    max-width: 1180px;
    margin-right: auto;
    margin-left: auto;
}
.coordination-alert {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.coordination-grid {
    display: grid;
    gap: 1rem;
}
.coordination-card {
    padding: 1.3rem;
    background: #fff;
    border: 1px solid #e0e6ee;
    border-radius: 21px;
    box-shadow: 0 14px 34px rgba(48, 62, 87, 0.06);
}
.coordination-card__top {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.coordination-card__top > div > span {
    color: #7962a1;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
}
.coordination-card h2 {
    margin: 0.25rem 0 0;
    color: #293852;
    font-size: 1.08rem;
}
.coordination-pill {
    align-self: flex-start;
    padding: 0.4rem 0.75rem;
    border-radius: 999px;
    font-size: 0.73rem;
    font-weight: 800;
}
.coordination-pill.pending {
    color: #8c5d18;
    background: #fff1d8;
}
.coordination-pill.accepted {
    color: #28745d;
    background: #e2f5ee;
}
.coordination-pill.rejected {
    color: #a13d49;
    background: #fde9ed;
}
.coordination-person {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin: 1rem 0;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 14px;
}
.coordination-person > div {
    display: grid;
    width: 38px;
    height: 38px;
    color: #6d529f;
    background: #fff;
    border-radius: 11px;
    place-items: center;
}
.coordination-person span,
.coordination-person small,
.coordination-person strong {
    display: block;
}
.coordination-person small {
    color: #7f8a9b;
}
.coordination-person strong {
    color: #38455b;
}
.coordination-date {
    margin-left: auto;
    text-align: right;
}
.coordination-request small,
.coordination-answer small {
    color: #8490a2;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}
.coordination-request p {
    margin: 0.35rem 0 0;
    color: #536075;
    line-height: 1.55;
}
.coordination-answer {
    display: flex;
    gap: 0.7rem;
    margin-top: 1rem;
    padding: 0.9rem;
    color: #32735f;
    background: #edf8f4;
    border: 1px solid #d0ebdf;
    border-radius: 14px;
}
.coordination-answer i {
    font-size: 1.25rem;
}
.coordination-answer p {
    margin: 0.3rem 0 0;
    color: #3f5c53;
}
.coordination-response-form {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e7ebf1;
}
.coordination-response-form label {
    margin-bottom: 0.45rem;
    font-weight: 700;
}
.coordination-response-form textarea {
    border-radius: 13px;
}
.coordination-response-form > div {
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    margin-top: 0.75rem;
}
.coordination-state {
    padding: 4rem 1rem;
    text-align: center;
    color: #7a8698;
    background: #fff;
    border: 1px dashed #dbe2eb;
    border-radius: 22px;
}
.coordination-state.empty i {
    color: #7459a5;
    font-size: 2.5rem;
}
.coordination-state h2 {
    margin: 0.65rem 0 0.25rem;
    color: #35435a;
    font-size: 1.15rem;
}
.coordination-state p {
    margin: 0;
}
.coordination-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
}
@media (max-width: 700px) {
    .coordination-inbox {
        padding: 0.75rem;
    }
    .coordination-hero {
        align-items: flex-start;
    }
    .coordination-hero__count {
        display: none;
    }
    .coordination-toolbar {
        align-items: stretch;
        flex-direction: column;
        gap: 0.5rem;
    }
    .coordination-toolbar .form-select {
        width: 100%;
    }
    .coordination-person {
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .coordination-date {
        width: 100%;
        margin-left: 45px;
        text-align: left;
    }
}
</style>
