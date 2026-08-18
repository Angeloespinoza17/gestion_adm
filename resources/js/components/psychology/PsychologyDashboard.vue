<script setup>
import { computed, onMounted, ref } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { usePsychology } from "../../composables/usePsychology";

const api = usePsychology();
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
            (item) => item.status
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
</script>

<template>
    <div>
        <div v-if="api.error.value" class="alert alert-danger">
            {{ api.error.value }}
        </div>

        <section class="psi-metric-grid">
            <article
                v-for="(value, key) in dashboard.metrics"
                :key="key"
                class="psi-metric"
            >
                <i :class="`bx ${metricIcons[key] || 'bx-data'}`"></i>
                <strong>{{ value }}</strong>
                <span>{{ metricLabels[key] || key }}</span>
            </article>
        </section>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="card psi-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Derivaciones por estado</h5>
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
                        <h5 class="card-title">Evolución mensual</h5>
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

        <div class="card psi-card mt-3">
            <div class="card-body">
                <h5 class="card-title">Carga activa por profesional</h5>
                <div
                    v-if="!dashboard.charts.workload?.length"
                    class="psi-empty"
                >
                    No hay casos activos para mostrar.
                </div>
                <div
                    v-for="item in dashboard.charts.workload"
                    :key="item.name"
                    class="psi-load"
                >
                    <span>{{ item.name }}</span>
                    <span class="badge bg-primary-subtle text-primary">
                        {{ item.total }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.psi-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.8rem;
}
.psi-metric,
.psi-card {
    background: #fff;
    border: 1px solid #e3e8ef;
    border-radius: 16px;
    box-shadow: 0 12px 34px rgba(51, 65, 85, 0.055);
}
.psi-metric {
    display: grid;
    grid-template-columns: auto 1fr;
    column-gap: 0.7rem;
    padding: 1rem;
}
.psi-metric > i {
    grid-row: 1 / 3;
    display: grid;
    place-items: center;
    padding: 0.6rem;
    color: #675584;
    background: #f0edf8;
    border-radius: 12px;
    font-size: 1.45rem;
}
.psi-metric strong {
    color: #263449;
    font-size: 1.35rem;
}
.psi-metric span {
    color: #68778a;
    font-size: 0.78rem;
}
.psi-load {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #edf0f4;
}
.psi-load:last-child {
    border-bottom: 0;
}
.psi-empty {
    padding: 2rem;
    text-align: center;
    color: #7c8798;
}
@media (max-width: 991px) {
    .psi-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .psi-metric-grid {
        grid-template-columns: 1fr;
    }
}
</style>
