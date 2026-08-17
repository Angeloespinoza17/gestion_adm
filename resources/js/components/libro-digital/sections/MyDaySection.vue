<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
    contextParams,
    errorMessage,
    formatDate,
    hasCapability,
    payloadData,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});
const emit = defineEmits(["open-workspace"]);

const loading = ref(false);
const error = ref(null);
const overview = ref(null);
let controller = null;

const scopeKey = computed(() => JSON.stringify(contextParams(props.context)));
const summary = computed(
    () => overview.value?.summary || overview.value?.today?.summary || {}
);
const sessions = computed(
    () => overview.value?.sessions || overview.value?.today?.sessions || []
);
const alerts = computed(
    () => overview.value?.alerts || overview.value?.today?.alerts || []
);
const canManageAttendance = computed(() =>
    hasCapability(props.capabilities, "can_manage_attendance")
);
const canManageLesson = computed(() =>
    hasCapability(props.capabilities, "can_manage_lesson")
);
const canSign = computed(() => hasCapability(props.capabilities, "can_sign"));
const dateLabel = computed(() =>
    formatDate(overview.value?.date || new Date().toISOString().slice(0, 10), {
        weekday: "long",
        day: "numeric",
        month: "long",
    })
);

const metrics = computed(() => [
    {
        label: "Clases programadas",
        value:
            summary.value.sessions ??
            summary.value.scheduled_sessions ??
            sessions.value.length,
        icon: "bx-calendar-event",
        tone: "primary",
    },
    {
        label: "Asistencia pendiente",
        value:
            summary.value.incomplete_attendance ??
            summary.value.pending_attendance ??
            sessions.value.filter((item) =>
                ["pending", "in_progress", "incomplete"].includes(
                    item.attendance_status
                )
            ).length,
        icon: "bx-user-check",
        tone: "warning",
    },
    {
        label: "Firmas pendientes",
        value:
            summary.value.pending_signatures ??
            sessions.value.filter((item) =>
                ["ready_to_sign", "pending_signature"].includes(item.status)
            ).length,
        icon: "bx-pen",
        tone: "info",
    },
    {
        label: "Alertas activas",
        value: summary.value.active_alerts ?? alerts.value.length,
        icon: "bx-error-circle",
        tone: "danger",
    },
]);

const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        overview.value = payloadData(
            await libroDigitalApi.overview(
                {
                    ...contextParams(props.context),
                    date: new Date().toISOString().slice(0, 10),
                },
                controller.signal
            )
        );
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loading.value = false;
    }
};

watch([scopeKey, () => props.refreshToken], load, { immediate: true });
onBeforeUnmount(() => controller?.abort());

const open = (session, tab = "sessions") =>
    emit("open-workspace", {
        bookId: session.book_id || session.book?.id,
        sessionId: session.id,
        tab,
    });
</script>

<template>
    <section
        class="ld-section ld-day"
        aria-labelledby="lcd-day-title"
        :aria-busy="loading"
    >
        <header class="ld-section-head">
            <div class="ld-section-head__identity">
                <span class="ld-section-head__icon" aria-hidden="true"
                    ><i class="bx bx-sun"></i
                ></span>
                <div>
                    <span class="ld-eyebrow">Operación docente</span>
                    <h2 id="lcd-day-title">Mi jornada</h2>
                    <p>
                        <i class="bx bx-calendar" aria-hidden="true"></i
                        >{{ dateLabel }}
                    </p>
                </div>
            </div>
            <BButton
                type="button"
                size="sm"
                variant="outline-secondary"
                class="ld-action-button"
                :disabled="loading"
                :aria-label="
                    loading
                        ? 'Actualizando la jornada'
                        : 'Actualizar la jornada'
                "
                @click="load"
            >
                <span
                    v-if="loading"
                    class="spinner-border spinner-border-sm"
                    aria-hidden="true"
                ></span>
                <i v-else class="bx bx-reset" aria-hidden="true"></i>
                <span>{{ loading ? "Actualizando" : "Actualizar" }}</span>
            </BButton>
        </header>

        <LibroDigitalStatePanel
            v-if="loading && !overview"
            state="loading"
            title="Cargando jornada"
            message="Consultando clases, pendientes y alertas del día."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !overview"
            state="error"
            title="No se pudo cargar la jornada"
            :message="errorMessage(error)"
            @retry="load"
        />
        <template v-else>
            <div
                class="ld-kpi-grid"
                aria-label="Resumen de la jornada"
                aria-live="polite"
            >
                <article
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="ld-kpi-card"
                    :class="`ld-kpi-card--${metric.tone}`"
                >
                    <span class="ld-kpi-card__icon" aria-hidden="true"
                        ><i class="bx" :class="metric.icon"></i
                    ></span>
                    <div class="ld-kpi-card__content">
                        <span>{{ metric.label }}</span>
                        <strong>{{
                            Number(metric.value || 0).toLocaleString("es-CL")
                        }}</strong>
                        <small>Jornada seleccionada</small>
                    </div>
                </article>
            </div>

            <BAlert
                v-if="error"
                show
                variant="warning"
                class="ld-inline-alert mb-0"
                role="status"
            >
                <i class="bx bx-error-circle" aria-hidden="true"></i>
                <span
                    >La actualización falló; se mantiene la última información
                    visible.</span
                >
                <BButton
                    type="button"
                    size="sm"
                    variant="link"
                    :disabled="loading"
                    @click="load"
                    >Reintentar</BButton
                >
            </BAlert>

            <div class="ld-day__grid">
                <BCard
                    class="border-0 ld-surface ld-schedule-panel"
                    body-class="p-0"
                >
                    <header class="ld-panel-head">
                        <div class="ld-panel-head__title">
                            <span class="ld-panel-head__icon" aria-hidden="true"
                                ><i class="bx bx-calendar-event"></i
                            ></span>
                            <div>
                                <h3>Clases programadas</h3>
                                <p>Acciones rápidas por bloque pedagógico</p>
                            </div>
                        </div>
                        <span class="ld-count-badge"
                            >{{ sessions.length }}
                            {{
                                sessions.length === 1 ? "clase" : "clases"
                            }}</span
                        >
                    </header>
                    <div
                        v-if="sessions.length"
                        class="table-responsive ld-table-wrap"
                    >
                        <table
                            class="table table-hover align-middle mb-0 ld-data-table"
                        >
                            <caption class="visually-hidden">
                                Clases programadas para
                                {{
                                    dateLabel
                                }}
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Bloque</th>
                                    <th scope="col">Curso y asignatura</th>
                                    <th scope="col">Sala</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" class="text-end">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="session in sessions"
                                    :key="session.id"
                                >
                                    <td>
                                        <div
                                            class="ld-cell-primary ld-cell-primary--time"
                                        >
                                            <span
                                                class="ld-time-icon"
                                                aria-hidden="true"
                                                ><i class="bx bx-time-five"></i
                                            ></span>
                                            <div>
                                                <strong>{{
                                                    session.block_label ||
                                                    session.block?.label ||
                                                    session.start_time?.slice(
                                                        0,
                                                        5
                                                    ) ||
                                                    "—"
                                                }}</strong
                                                ><small
                                                    >{{
                                                        session.start_time?.slice(
                                                            0,
                                                            5
                                                        )
                                                    }}<template
                                                        v-if="session.end_time"
                                                        >–{{
                                                            session.end_time.slice(
                                                                0,
                                                                5
                                                            )
                                                        }}</template
                                                    ></small
                                                >
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ld-cell-primary">
                                            <strong>{{
                                                session.course?.display_name ||
                                                session.course_name ||
                                                "Curso"
                                            }}</strong
                                            ><small
                                                >{{
                                                    session.subject?.name ||
                                                    session.subject_name ||
                                                    "Sin asignatura"
                                                }}<template
                                                    v-if="session.teacher?.name"
                                                >
                                                    ·
                                                    {{
                                                        session.teacher.name
                                                    }}</template
                                                ></small
                                            >
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ld-room"
                                            ><i
                                                class="bx bx-map"
                                                aria-hidden="true"
                                            ></i
                                            >{{
                                                session.room_name ||
                                                session.room ||
                                                "—"
                                            }}</span
                                        >
                                    </td>
                                    <td>
                                        <LibroDigitalStatusBadge
                                            :status="
                                                session.status ||
                                                session.attendance_status
                                            "
                                        />
                                    </td>
                                    <td>
                                        <div class="ld-row-actions">
                                            <BButton
                                                v-if="canManageAttendance"
                                                type="button"
                                                size="sm"
                                                variant="outline-primary"
                                                :aria-label="`Registrar asistencia de ${
                                                    session.course
                                                        ?.display_name ||
                                                    session.course_name ||
                                                    'la clase'
                                                }`"
                                                @click="
                                                    open(session, 'attendance')
                                                "
                                                ><i
                                                    class="bx bx-user-check"
                                                    aria-hidden="true"
                                                ></i
                                                ><span
                                                    >Asistencia</span
                                                ></BButton
                                            >
                                            <BButton
                                                v-if="canManageLesson"
                                                type="button"
                                                size="sm"
                                                variant="outline-secondary"
                                                :aria-label="`Abrir leccionario de ${
                                                    session.course
                                                        ?.display_name ||
                                                    session.course_name ||
                                                    'la clase'
                                                }`"
                                                @click="open(session, 'lesson')"
                                                ><i
                                                    class="bx bx-book-content"
                                                    aria-hidden="true"
                                                ></i
                                                ><span
                                                    >Leccionario</span
                                                ></BButton
                                            >
                                            <BButton
                                                v-if="canSign"
                                                type="button"
                                                size="sm"
                                                variant="outline-success"
                                                :aria-label="`Revisar firma de ${
                                                    session.course
                                                        ?.display_name ||
                                                    session.course_name ||
                                                    'la clase'
                                                }`"
                                                @click="
                                                    open(session, 'sessions')
                                                "
                                                ><i
                                                    class="bx bx-pen"
                                                    aria-hidden="true"
                                                ></i
                                                ><span>Firma</span></BButton
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <LibroDigitalStatePanel
                        v-else
                        compact
                        title="Sin clases programadas"
                        message="No hay sesiones asignadas para el contexto y fecha seleccionados."
                    />
                </BCard>

                <BCard
                    class="border-0 ld-surface ld-alert-panel"
                    body-class="p-0"
                >
                    <header class="ld-panel-head">
                        <div class="ld-panel-head__title">
                            <span
                                class="ld-panel-head__icon ld-panel-head__icon--warning"
                                aria-hidden="true"
                                ><i class="bx bx-bell"></i
                            ></span>
                            <div>
                                <h3>Alertas y pendientes</h3>
                                <p>Atención prioritaria de la jornada</p>
                            </div>
                        </div>
                        <span
                            class="ld-count-badge"
                            :class="{
                                'ld-count-badge--warning': alerts.length,
                            }"
                            >{{ alerts.length }}</span
                        >
                    </header>
                    <ul
                        v-if="alerts.length"
                        class="ld-alert-list"
                        aria-label="Alertas activas"
                    >
                        <li
                            v-for="alert in alerts"
                            :key="alert.id || `${alert.type}-${alert.title}`"
                        >
                            <span class="ld-alert-list__icon" aria-hidden="true"
                                ><i
                                    class="bx"
                                    :class="alert.icon || 'bx-error-circle'"
                                ></i
                            ></span>
                            <div>
                                <strong>{{
                                    alert.title || alert.label || "Alerta"
                                }}</strong
                                ><span>{{
                                    alert.message || alert.description
                                }}</span>
                            </div>
                            <LibroDigitalStatusBadge
                                :status="
                                    alert.severity || alert.status || 'pending'
                                "
                            />
                        </li>
                    </ul>
                    <LibroDigitalStatePanel
                        v-else
                        compact
                        title="Sin alertas activas"
                        message="No hay pendientes críticos para esta jornada."
                    />
                </BCard>
            </div>
        </template>
    </section>
</template>

<style scoped>
.ld-section {
    --ld-ink: var(--ld-color-ink, #172033);
    --ld-muted: var(--ld-color-muted, #667085);
    --ld-line: var(--ld-color-line, #e4e9f0);
    --ld-primary: var(--ld-color-primary, #405189);
    --ld-surface: var(--ld-color-surface, #fff);
    display: grid;
    gap: 1rem;
}
.ld-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.15rem 0;
}
.ld-section-head__identity {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-width: 0;
}
.ld-section-head__icon {
    display: grid;
    flex: 0 0 44px;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 1px solid #d9e1f4;
    border-radius: 14px;
    background: linear-gradient(145deg, #f4f7ff, #e8eefc);
    color: var(--ld-primary);
    font-size: 1.25rem;
    box-shadow: 0 6px 18px rgba(64, 81, 137, 0.08);
}
.ld-eyebrow {
    display: block;
    color: var(--ld-primary);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}
.ld-section-head h2 {
    margin: 0.14rem 0 0.2rem;
    color: var(--ld-ink);
    font-size: 1.35rem;
    letter-spacing: -0.025em;
}
.ld-section-head p {
    display: flex;
    align-items: center;
    gap: 0.32rem;
    margin: 0;
    color: var(--ld-muted);
    font-size: 0.8rem;
    text-transform: capitalize;
}
.ld-action-button,
.ld-row-actions .btn {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    justify-content: center;
    gap: 0.36rem;
    font-size: 0.75rem;
}
.ld-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}
.ld-kpi-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.72rem;
    min-width: 0;
    padding: 0.9rem 1rem;
    overflow: hidden;
    border: 1px solid var(--ld-line);
    border-radius: 14px;
    background: var(--ld-surface);
    box-shadow: 0 6px 20px rgba(26, 39, 66, 0.045);
}
.ld-kpi-card::after {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 3px;
    background: #6675ad;
    content: "";
}
.ld-kpi-card__icon {
    display: grid;
    flex: 0 0 42px;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #eef2fb;
    color: var(--ld-primary);
    font-size: 1.18rem;
}
.ld-kpi-card__content {
    min-width: 0;
}
.ld-kpi-card__content span,
.ld-kpi-card__content strong,
.ld-kpi-card__content small {
    display: block;
}
.ld-kpi-card__content span {
    overflow: hidden;
    color: var(--ld-muted);
    font-size: 0.76rem;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-kpi-card__content strong {
    margin: 0.08rem 0;
    color: var(--ld-ink);
    font-size: 1.35rem;
    line-height: 1.1;
}
.ld-kpi-card__content small {
    color: #98a2b3;
    font-size: 0.68rem;
}
.ld-kpi-card--warning::after {
    background: #d99a24;
}
.ld-kpi-card--warning .ld-kpi-card__icon {
    background: #fff6df;
    color: #9a6500;
}
.ld-kpi-card--info::after {
    background: #3191a8;
}
.ld-kpi-card--info .ld-kpi-card__icon {
    background: #e8f6fa;
    color: #26758b;
}
.ld-kpi-card--danger::after {
    background: #d05a67;
}
.ld-kpi-card--danger .ld-kpi-card__icon {
    background: #fff0f2;
    color: #b53b49;
}
.ld-inline-alert {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.8rem;
    font-size: 0.8rem;
}
.ld-inline-alert > i {
    font-size: 1rem;
}
.ld-inline-alert .btn {
    min-height: 36px;
    margin-left: auto;
    padding-inline: 0.45rem;
    font-size: 0.75rem;
}
.ld-day__grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(300px, 0.55fr);
    gap: 1rem;
    align-items: start;
}
.ld-surface {
    overflow: hidden;
    border: 1px solid var(--ld-line) !important;
    border-radius: 14px;
    box-shadow: 0 8px 26px rgba(26, 39, 66, 0.05);
}
.ld-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--ld-line);
    background: linear-gradient(180deg, #fff, #fbfcfe);
}
.ld-panel-head__title {
    display: flex;
    align-items: center;
    gap: 0.62rem;
    min-width: 0;
}
.ld-panel-head__icon {
    display: grid;
    flex: 0 0 34px;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #eef2fb;
    color: var(--ld-primary);
    font-size: 1rem;
}
.ld-panel-head__icon--warning {
    background: #fff6df;
    color: #a06a06;
}
.ld-panel-head h3 {
    margin: 0;
    color: var(--ld-ink);
    font-size: 0.9rem;
    font-weight: 750;
}
.ld-panel-head p {
    margin: 0.12rem 0 0;
    color: var(--ld-muted);
    font-size: 0.72rem;
}
.ld-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    padding: 0.22rem 0.5rem;
    border: 1px solid #dfe5ef;
    border-radius: 999px;
    background: #f7f9fc;
    color: #596579;
    font-size: 0.72rem;
    font-weight: 750;
}
.ld-count-badge--warning {
    border-color: #f0ddb2;
    background: #fff8e9;
    color: #94600a;
}
.ld-table-wrap {
    scrollbar-width: thin;
}
.ld-data-table {
    min-width: 760px;
    font-size: 0.78rem;
}
.ld-data-table th {
    padding: 0.62rem 1rem;
    border-bottom-color: var(--ld-line);
    background: #f7f9fc;
    color: #697586;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.055em;
    text-transform: uppercase;
}
.ld-data-table td {
    padding: 0.72rem 1rem;
    border-color: #edf0f4;
    color: #475467;
}
.ld-cell-primary {
    min-width: 0;
}
.ld-cell-primary--time {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.ld-cell-primary strong,
.ld-cell-primary small {
    display: block;
}
.ld-cell-primary strong {
    color: #263247;
    font-size: 0.78rem;
}
.ld-cell-primary small {
    margin-top: 0.13rem;
    color: #7d8999;
    font-size: 0.7rem;
}
.ld-time-icon {
    display: grid;
    flex: 0 0 28px;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #f2f5fa;
    color: #58698c;
}
.ld-room {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.ld-room i {
    color: #7b8798;
}
.ld-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.28rem;
    white-space: nowrap;
}
.ld-row-actions .btn {
    font-size: 0.72rem;
}
.ld-alert-list {
    display: grid;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}
.ld-alert-list li {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.62rem;
    padding: 0.78rem 1rem;
    border-bottom: 1px solid #edf0f4;
}
.ld-alert-list li:last-child {
    border-bottom: 0;
}
.ld-alert-list__icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #fff6df;
    color: #a46d0b;
    font-size: 1rem;
}
.ld-alert-list strong,
.ld-alert-list div > span {
    display: block;
}
.ld-alert-list strong {
    color: #344054;
    font-size: 0.78rem;
}
.ld-alert-list div > span {
    margin-top: 0.14rem;
    color: #7a8696;
    font-size: 0.72rem;
    line-height: 1.45;
}
@media (max-width: 1100px) {
    .ld-day__grid {
        grid-template-columns: 1fr;
    }
    .ld-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 700px) {
    .ld-section-head {
        align-items: flex-start;
    }
    .ld-section-head__icon {
        display: none;
    }
    .ld-action-button > span:last-child {
        display: none;
    }
    .ld-kpi-grid {
        grid-template-columns: 1fr;
    }
    .ld-kpi-card {
        padding: 0.78rem 0.85rem;
    }
    .ld-row-actions .btn span {
        display: none;
    }
    .ld-row-actions .btn {
        width: 36px;
        padding-inline: 0;
    }
    .ld-alert-list li {
        grid-template-columns: 34px minmax(0, 1fr);
        padding: 0.7rem 0.8rem;
    }
    .ld-alert-list li > :last-child {
        grid-column: 2;
        justify-self: start;
    }
    .ld-panel-head {
        padding: 0.75rem 0.8rem;
    }
}
</style>
