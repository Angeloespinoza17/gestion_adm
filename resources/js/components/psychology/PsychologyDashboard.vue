<script setup>
import { computed, onMounted, ref } from "vue";
import VueApexCharts from "vue3-apexcharts";
import PsychologyBadge from "./PsychologyBadge.vue";
import {
    psychologyStatusLabels,
    usePsychology,
} from "../../composables/usePsychology";

const api = usePsychology();
const props = defineProps({
    catalogs: { type: Object, default: () => ({ capabilities: {} }) },
});
const dashboard = ref({ metrics: {}, charts: {}, recent: {} });

const load = async () => {
    dashboard.value = await api.get("/api/psychology/dashboard");
};

onMounted(load);

const statusOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: "inherit" },
    colors: ["#556ee6"],
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
    dataLabels: { enabled: false },
    grid: { borderColor: "#eef0f3" },
    xaxis: {
        categories: (dashboard.value.charts.by_status || []).map(
            (item) => psychologyStatusLabels[item.status] || item.status
        ),
    },
    noData: { text: "Sin datos" },
}));
const statusSeries = computed(() => [
    {
        name: "Derivaciones",
        data: (dashboard.value.charts.by_status || []).map(
            (item) => item.total
        ),
    },
]);
const monthlyOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: "inherit" },
    colors: ["#34c38f"],
    stroke: { curve: "smooth", width: 3 },
    dataLabels: { enabled: false },
    grid: { borderColor: "#eef0f3" },
    xaxis: {
        categories: (dashboard.value.charts.monthly || []).map(
            (item) => item.month
        ),
    },
    noData: { text: "Sin datos" },
}));
const monthlySeries = computed(() => [
    {
        name: "Derivaciones",
        data: (dashboard.value.charts.monthly || []).map((item) => item.total),
    },
]);
const metricLabels = {
    referrals_received: "Derivaciones recibidas",
    pending_review: "Pendientes de revisión",
    information_requested: "Requieren antecedentes",
    active_cases: "Casos activos",
    high_priority_cases: "Prioridad alta o crítica",
    inactive_cases: "Sin actividad reciente",
    overdue_tasks: "Tareas vencidas",
    upcoming_followups: "Seguimientos próximos",
    scheduled_sessions: "Sesiones programadas",
    closed_period: "Cerrados este mes",
    avg_first_response_hours: "Promedio primera respuesta (h)",
};
const metricIcons = {
    referrals_received: "bx-transfer-alt",
    pending_review: "bx-time-five",
    information_requested: "bx-file-find",
    active_cases: "bx-folder-open",
    high_priority_cases: "bx-error-circle",
    inactive_cases: "bx-pause-circle",
    overdue_tasks: "bx-error-alt",
    upcoming_followups: "bx-calendar-check",
    scheduled_sessions: "bx-calendar-event",
    closed_period: "bx-check-shield",
    avg_first_response_hours: "bx-timer",
};
const metricTone = (key) => {
    if (["high_priority_cases", "overdue_tasks"].includes(key)) return "danger";
    if (["pending_review", "information_requested", "inactive_cases"].includes(key)) return "warning";
    if (["closed_period", "upcoming_followups"].includes(key)) return "success";
    return "primary";
};
const personalScope = computed(
    () => props.catalogs?.capabilities?.personal_scope === true
);
const studentName = (student) =>
    student?.registered_name ||
    [student?.first_name, student?.last_name].filter(Boolean).join(" ") ||
    "Estudiante";
const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat("es-CL", {
              day: "2-digit",
              month: "short",
          }).format(new Date(value))
        : "Sin fecha";
</script>

<template>
    <div class="psi-dashboard">
        <div v-if="api.error.value" class="alert alert-danger">
            {{ api.error.value }}
        </div>

        <section class="psi-overview">
            <div>
                <p>Panel operativo</p>
                <h2>
                    {{
                        personalScope
                            ? "Tu jornada profesional, en un solo lugar"
                            : "Panorama de atención psicológica"
                    }}
                </h2>
                <span>
                    {{
                        personalScope
                            ? "Indicadores calculados exclusivamente con tus casos y atenciones."
                            : "Prioridades, seguimiento y carga profesional actualizados."
                    }}
                </span>
            </div>
            <div class="psi-overview-signals">
                <article>
                    <i class="bx bx-folder-open"></i>
                    <span><strong>{{ dashboard.metrics.active_cases || 0 }}</strong> casos activos</span>
                </article>
                <article :class="{ attention: dashboard.metrics.high_priority_cases }">
                    <i class="bx bx-error-circle"></i>
                    <span><strong>{{ dashboard.metrics.high_priority_cases || 0 }}</strong> prioritarios</span>
                </article>
                <article>
                    <i class="bx bx-calendar-check"></i>
                    <span><strong>{{ dashboard.metrics.scheduled_sessions || 0 }}</strong> atenciones próximas</span>
                </article>
            </div>
        </section>

        <div class="psi-section-title">
            <div>
                <span>Indicadores clave</span>
                <h3>{{ personalScope ? "Tu actividad" : "Actividad del módulo" }}</h3>
            </div>
            <span class="psi-scope-pill">
                <i :class="`bx ${personalScope ? 'bx-user-check' : 'bx-shield-quarter'}`"></i>
                {{ personalScope ? "Datos personales" : "Datos autorizados" }}
            </span>
        </div>
        <section class="psi-metric-grid">
            <article
                v-for="(value, key) in dashboard.metrics"
                :key="key"
                :class="['psi-metric', `tone-${metricTone(key)}`]"
            >
                <div class="psi-metric-icon">
                    <i :class="`bx ${metricIcons[key] || 'bx-data'}`"></i>
                </div>
                <div>
                    <strong>{{ value }}</strong>
                    <span>{{ metricLabels[key] || key }}</span>
                </div>
            </article>
        </section>

        <div class="psi-section-title charts-title">
            <div>
                <span>Tendencias</span>
                <h3>Lectura de la demanda</h3>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card psi-card h-100">
                    <div class="card-body">
                        <div class="psi-card-title">
                            <span class="psi-card-icon purple"><i class="bx bx-transfer-alt"></i></span>
                            <div><small>Distribución actual</small><h5>Derivaciones por estado</h5></div>
                        </div>
                        <VueApexCharts
                            type="bar"
                            height="270"
                            :options="statusOptions"
                            :series="statusSeries"
                        />
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card psi-card h-100">
                    <div class="card-body">
                        <div class="psi-card-title">
                            <span class="psi-card-icon green"><i class="bx bx-line-chart"></i></span>
                            <div><small>Últimos meses</small><h5>Evolución mensual</h5></div>
                        </div>
                        <VueApexCharts
                            type="line"
                            height="270"
                            :options="monthlyOptions"
                            :series="monthlySeries"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-0">
            <div class="col-xl-5">
                <div class="card psi-card h-100">
                    <div class="card-body">
                        <div class="psi-card-title">
                            <span class="psi-card-icon blue"><i class="bx bx-briefcase-alt-2"></i></span>
                            <div><small>Cartera vigente</small><h5>{{ personalScope ? "Tu carga activa" : "Carga por profesional" }}</h5></div>
                        </div>
                        <div v-if="!dashboard.charts.workload?.length" class="psi-empty">
                            No hay casos activos para mostrar.
                        </div>
                        <div v-for="item in dashboard.charts.workload" :key="item.name" class="psi-load">
                            <span><i class="bx bx-user-circle"></i>{{ item.name }}</span>
                            <strong>{{ item.total }}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card psi-card h-100">
                    <div class="card-body">
                        <div class="psi-card-title">
                            <span class="psi-card-icon purple"><i class="bx bx-time-five"></i></span>
                            <div><small>Actividad reciente</small><h5>Últimas derivaciones visibles</h5></div>
                        </div>
                        <div v-if="!dashboard.recent.referrals?.length" class="psi-empty">
                            No hay derivaciones recientes para mostrar.
                        </div>
                        <div v-for="item in dashboard.recent.referrals" :key="item.id" class="psi-recent">
                            <span class="psi-recent-avatar">{{ studentName(item.student).slice(0, 1) }}</span>
                            <span class="psi-recent-copy">
                                <strong>{{ studentName(item.student) }}</strong>
                                <small>{{ item.code || "Derivación en registro" }} · {{ formatDate(item.updated_at) }}</small>
                            </span>
                            <PsychologyBadge :value="item.status" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.psi-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.85rem;
}
.psi-overview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    margin-bottom: 1.35rem;
    padding: 1.35rem 1.5rem;
    color: #fff;
    background:
        radial-gradient(circle at 90% 20%, rgba(87, 190, 201, 0.27), transparent 32%),
        linear-gradient(125deg, #2e3c58, #4e456f 62%, #466f82);
    border-radius: 20px;
    box-shadow: 0 18px 40px rgba(38, 48, 76, 0.18);
}
.psi-overview p,
.psi-section-title > div > span,
.psi-card-title small {
    margin: 0 0 0.25rem;
    color: #8b78aa;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.11em;
    text-transform: uppercase;
}
.psi-overview p {
    color: #cfc6e8;
}
.psi-overview h2 {
    margin: 0;
    color: #fff;
    font-size: clamp(1.15rem, 2vw, 1.55rem);
    letter-spacing: -0.025em;
}
.psi-overview > div:first-child > span {
    display: block;
    max-width: 610px;
    margin-top: 0.4rem;
    color: #dbe2ef;
    font-size: 0.78rem;
}
.psi-overview-signals {
    display: grid;
    grid-template-columns: repeat(3, minmax(105px, 1fr));
    gap: 0.55rem;
}
.psi-overview-signals article {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 13px;
    backdrop-filter: blur(8px);
}
.psi-overview-signals article.attention {
    background: rgba(244, 157, 144, 0.16);
}
.psi-overview-signals i {
    font-size: 1.15rem;
}
.psi-overview-signals span {
    display: flex;
    flex-direction: column;
    color: #dbe2ef;
    font-size: 0.65rem;
}
.psi-overview-signals strong {
    color: #fff;
    font-size: 1rem;
}
.psi-section-title {
    display: flex;
    align-items: end;
    justify-content: space-between;
    margin: 0 0 0.75rem;
}
.psi-section-title h3 {
    margin: 0;
    color: #26374d;
    font-size: 1rem;
}
.charts-title {
    margin-top: 1.6rem;
}
.psi-scope-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.45rem 0.7rem;
    color: #5f4e7b;
    background: #f2eef8;
    border: 1px solid #e7dff0;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
}
.psi-metric,
.psi-card {
    background: #fff;
    border: 1px solid #e3e8ef;
    border-radius: 16px;
    box-shadow: 0 12px 34px rgba(51, 65, 85, 0.055);
}
.psi-metric {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
    padding: 1rem 1.05rem;
}
.psi-metric::after {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 42px;
    height: 3px;
    background: var(--tone, #675584);
    border-radius: 3px 0 0;
    content: "";
}
.psi-metric-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.65rem;
    height: 2.65rem;
    place-items: center;
    color: var(--tone, #675584);
    background: var(--tone-soft, #f0edf8);
    border-radius: 13px;
}
.psi-metric-icon i {
    font-size: 1.45rem;
}
.psi-metric > div:last-child {
    display: flex;
    min-width: 0;
    flex-direction: column;
}
.psi-metric strong {
    color: #263449;
    font-size: 1.35rem;
}
.psi-metric span {
    color: #68778a;
    font-size: 0.78rem;
}
.tone-primary { --tone: #675584; --tone-soft: #f0edf8; }
.tone-warning { --tone: #c18432; --tone-soft: #fff4df; }
.tone-danger { --tone: #cf6156; --tone-soft: #fff0ee; }
.tone-success { --tone: #2e9974; --tone-soft: #e9f8f2; }
.psi-card .card-body {
    padding: 1.25rem;
}
.psi-card-title {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 0.6rem;
}
.psi-card-title h5 {
    margin: 0;
    color: #29394e;
    font-size: 0.92rem;
}
.psi-card-title small {
    display: block;
    margin: 0;
}
.psi-card-icon {
    display: grid;
    width: 2.3rem;
    height: 2.3rem;
    place-items: center;
    border-radius: 11px;
    font-size: 1.2rem;
}
.psi-card-icon.purple { color: #675584; background: #f0edf8; }
.psi-card-icon.green { color: #2e9974; background: #e9f8f2; }
.psi-card-icon.blue { color: #3b7e9c; background: #eaf6fb; }
.psi-load {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #edf0f4;
}
.psi-load:last-child {
    border-bottom: 0;
}
.psi-load > span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #48586d;
}
.psi-load i {
    color: #7b6b96;
    font-size: 1.15rem;
}
.psi-load > strong {
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    color: #5d4d77;
    background: #f2eef8;
    border-radius: 9px;
}
.psi-recent {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.68rem 0;
    border-bottom: 1px solid #edf0f4;
}
.psi-recent:last-child { border-bottom: 0; }
.psi-recent-avatar {
    display: grid;
    flex: 0 0 auto;
    width: 2.1rem;
    height: 2.1rem;
    place-items: center;
    color: #5e4e79;
    background: linear-gradient(145deg, #f3eff9, #eaf5f8);
    border-radius: 10px;
    font-weight: 800;
}
.psi-recent-copy {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
}
.psi-recent-copy strong {
    overflow: hidden;
    color: #34445a;
    font-size: 0.79rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-recent-copy small {
    color: #8390a1;
    font-size: 0.68rem;
}
.psi-empty {
    padding: 2rem;
    text-align: center;
    color: #7c8798;
}
@media (max-width: 991px) {
    .psi-overview {
        align-items: stretch;
        flex-direction: column;
    }
    .psi-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .psi-overview {
        padding: 1.1rem;
    }
    .psi-overview-signals {
        grid-template-columns: 1fr;
    }
    .psi-metric-grid {
        grid-template-columns: 1fr;
    }
    .psi-section-title {
        align-items: flex-start;
        gap: 0.7rem;
        flex-direction: column;
    }
}
</style>
