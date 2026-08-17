<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import AnalyticsPanel from "../AnalyticsPanel.vue";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import { contextParams, errorMessage, payloadData } from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});
const loading = ref(false);
const error = ref(null);
const statistics = ref(null);
let controller = null;
const filters = reactive({
    period: "academic_year",
    from: "",
    to: "",
    teacher_staff_id: null,
    session_status: "",
});
const teachers = computed(
    () => props.catalogs.teachers || props.catalogs.staff || []
);
const scopeKey = computed(() => JSON.stringify(contextParams(props.context)));
const summary = computed(() => statistics.value?.summary || {});
const kpis = computed(
    () =>
        statistics.value?.kpis || [
            {
                label: "Sesiones realizadas",
                value: summary.value.completed_sessions ?? 0,
                unit: "",
                icon: "bx-calendar-check",
            },
            {
                label: "Asistencia promedio",
                value: summary.value.attendance_rate ?? null,
                unit: "%",
                icon: "bx-user-check",
            },
            {
                label: "Cobertura curricular",
                value: summary.value.curriculum_coverage ?? null,
                unit: "%",
                icon: "bx-target-lock",
            },
            {
                label: "Sesiones firmadas",
                value: summary.value.signature_rate ?? null,
                unit: "%",
                icon: "bx-pen",
            },
            {
                label: "Promedio calificaciones",
                value: summary.value.average_grade ?? null,
                unit: "",
                icon: "bx-bar-chart",
            },
            {
                label: "Casos de ausencia",
                value: summary.value.open_absence_cases ?? 0,
                unit: "",
                icon: "bx-calendar-x",
            },
        ]
);
const timeline = computed(
    () =>
        statistics.value?.attendance_timeline ||
        statistics.value?.timeline ||
        []
);
const attendanceSeries = computed(() => [
    {
        name: "Asistencia",
        data: timeline.value.map((item) =>
            Number(item.attendance_rate ?? item.value ?? 0)
        ),
    },
]);
const attendanceOptions = computed(() => ({
    chart: {
        type: "area",
        toolbar: { show: true },
        animations: { enabled: false },
        fontFamily: "inherit",
    },
    colors: ["#405189"],
    dataLabels: { enabled: false },
    stroke: { curve: "straight", width: 2.5 },
    fill: { opacity: 0.12 },
    grid: { borderColor: "#edf0f4" },
    xaxis: {
        categories: timeline.value.map((item) => item.label || item.date),
    },
    yaxis: {
        min: 0,
        max: 100,
        labels: { formatter: (value) => `${Math.round(value)}%` },
    },
    tooltip: { y: { formatter: (value) => `${Number(value).toFixed(1)}%` } },
}));
const sessionStatuses = computed(
    () =>
        statistics.value?.session_statuses ||
        statistics.value?.sessions_by_status ||
        []
);
const sessionSeries = computed(() =>
    sessionStatuses.value.map((item) => Number(item.value ?? item.total ?? 0))
);
const sessionOptions = computed(() => ({
    chart: {
        type: "donut",
        toolbar: { show: true },
        animations: { enabled: false },
        fontFamily: "inherit",
    },
    labels: sessionStatuses.value.map(
        (item) => item.label || item.name || item.status
    ),
    colors: ["#405189", "#2b8a66", "#d59b26", "#c84f5a", "#718096"],
    legend: { position: "bottom", fontSize: "11px" },
    plotOptions: { pie: { donut: { size: "62%" } } },
}));
const coverage = computed(
    () =>
        statistics.value?.curriculum_coverage ||
        statistics.value?.coverage ||
        []
);
const coverageSeries = computed(() => [
    {
        name: "Cobertura",
        data: coverage.value.map((item) =>
            Number(item.percentage ?? item.value ?? 0)
        ),
    },
]);
const coverageOptions = computed(() => ({
    chart: {
        type: "bar",
        toolbar: { show: true },
        animations: { enabled: false },
        fontFamily: "inherit",
    },
    colors: ["#287f74"],
    dataLabels: { enabled: false },
    plotOptions: {
        bar: { horizontal: true, borderRadius: 2, barHeight: "58%" },
    },
    xaxis: {
        categories: coverage.value.map(
            (item) => item.label || item.subject || item.objective
        ),
        min: 0,
        max: 100,
        labels: { formatter: (value) => `${Math.round(value)}%` },
    },
    grid: { borderColor: "#edf0f4" },
}));
const risk = computed(
    () =>
        statistics.value?.risk_distribution ||
        statistics.value?.attendance_risk ||
        []
);
const riskSeries = computed(() =>
    risk.value.map((item) => Number(item.value ?? item.total ?? 0))
);
const riskOptions = computed(() => ({
    chart: {
        type: "donut",
        toolbar: { show: true },
        animations: { enabled: false },
        fontFamily: "inherit",
    },
    labels: risk.value.map((item) => item.label || item.name),
    colors:
        risk.value.map((item) => item.color).filter(Boolean).length ===
        risk.value.length
            ? risk.value.map((item) => item.color)
            : ["#c84f5a", "#d59b26", "#3b82a0", "#2b8a66"],
    legend: { position: "bottom", fontSize: "11px" },
    plotOptions: { pie: { donut: { size: "62%" } } },
}));

const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        statistics.value = payloadData(
            await libroDigitalApi.statistics(
                { ...contextParams(props.context), ...filters },
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
const fmt = (value, unit = "") =>
    value === null || value === undefined
        ? "Sin datos"
        : `${Number(value).toLocaleString("es-CL", {
              maximumFractionDigits: 2,
          })}${unit}`;
</script>

<template>
    <section
        class="ld-section ld-statistics"
        aria-labelledby="lcd-statistics-title"
        :aria-busy="loading"
    >
        <header class="ld-section-head">
            <div class="ld-section-head__identity">
                <span class="ld-section-head__icon" aria-hidden="true"
                    ><i class="bx bx-line-chart"></i
                ></span>
                <div>
                    <span class="ld-eyebrow">Inteligencia académica</span>
                    <h2 id="lcd-statistics-title">
                        Estadísticas del Libro Digital
                    </h2>
                    <p>
                        Indicadores operacionales, pedagógicos y de completitud
                        con filtros auditables.
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
                        ? 'Actualizando estadísticas'
                        : 'Actualizar estadísticas'
                "
                @click="load"
                ><span
                    v-if="loading"
                    class="spinner-border spinner-border-sm"
                    aria-hidden="true"
                ></span
                ><i v-else class="bx bx-reset" aria-hidden="true"></i
                ><span>{{
                    loading ? "Actualizando" : "Actualizar"
                }}</span></BButton
            >
        </header>

        <BCard class="border-0 ld-surface ld-filter-panel" body-class="p-0">
            <header class="ld-filter-panel__head">
                <div>
                    <span class="ld-filter-panel__icon" aria-hidden="true"
                        ><i class="bx bx-slider-alt"></i
                    ></span>
                    <div>
                        <h3>Filtros de análisis</h3>
                        <p>Acota el periodo y la responsabilidad docente.</p>
                    </div>
                </div>
                <span class="ld-filter-panel__hint"
                    >Los resultados no alteran registros fuente</span
                >
            </header>
            <form class="ld-filter-form" @submit.prevent="load">
                <div class="ld-field">
                    <label for="lcd-statistics-period">Periodo</label
                    ><BFormSelect
                        id="lcd-statistics-period"
                        v-model="filters.period"
                        ><option value="academic_year">Año académico</option>
                        <option value="current_month">Mes actual</option>
                        <option value="current_week">Semana actual</option>
                        <option value="custom">
                            Personalizado
                        </option></BFormSelect
                    >
                </div>
                <div v-if="filters.period === 'custom'" class="ld-field">
                    <label for="lcd-statistics-from">Desde</label
                    ><BFormInput
                        id="lcd-statistics-from"
                        v-model="filters.from"
                        type="date"
                    />
                </div>
                <div v-if="filters.period === 'custom'" class="ld-field">
                    <label for="lcd-statistics-to">Hasta</label
                    ><BFormInput
                        id="lcd-statistics-to"
                        v-model="filters.to"
                        type="date"
                    />
                </div>
                <div class="ld-field ld-field--grow">
                    <label for="lcd-statistics-teacher">Docente</label
                    ><BFormSelect
                        id="lcd-statistics-teacher"
                        v-model="filters.teacher_staff_id"
                        ><option :value="null">Todos los docentes</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.full_name || teacher.name }}
                        </option></BFormSelect
                    >
                </div>
                <BButton
                    type="submit"
                    variant="primary"
                    class="ld-filter-submit"
                    :disabled="loading"
                    ><span
                        v-if="loading"
                        class="spinner-border spinner-border-sm"
                        aria-hidden="true"
                    ></span
                    ><i v-else class="bx bx-filter-alt" aria-hidden="true"></i
                    ><span>Aplicar filtros</span></BButton
                >
            </form>
        </BCard>

        <LibroDigitalStatePanel
            v-if="loading && !statistics"
            state="loading"
            title="Calculando estadísticas"
            message="Agregando sesiones, asistencia, firmas, cobertura y evaluaciones."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !statistics"
            state="error"
            title="No se pudieron calcular las estadísticas"
            :message="errorMessage(error)"
            @retry="load"
        />
        <template v-else-if="statistics">
            <BAlert
                v-if="error"
                show
                variant="warning"
                class="ld-inline-alert mb-0"
                role="status"
                ><i class="bx bx-error-circle" aria-hidden="true"></i
                ><span
                    >No fue posible refrescar; se conserva la última lectura
                    visible.</span
                ><BButton
                    type="button"
                    size="sm"
                    variant="link"
                    :disabled="loading"
                    @click="load"
                    >Reintentar</BButton
                ></BAlert
            >
            <div
                class="ld-kpi-grid"
                aria-label="Indicadores principales"
                aria-live="polite"
            >
                <article
                    v-for="(item, index) in kpis"
                    :key="item.key || item.label"
                    class="ld-kpi-card"
                    :class="`ld-kpi-card--tone-${(index % 6) + 1}`"
                >
                    <div class="ld-kpi-card__top">
                        <span class="ld-kpi-card__icon" aria-hidden="true"
                            ><i
                                class="bx"
                                :class="item.icon || 'bx-bar-chart-alt-2'"
                            ></i></span
                        ><span class="ld-kpi-card__period">Periodo activo</span>
                    </div>
                    <strong
                        :class="{
                            'ld-kpi-card__empty':
                                item.value === null || item.value === undefined,
                        }"
                        >{{ fmt(item.value, item.unit) }}</strong
                    >
                    <span class="ld-kpi-card__label">{{ item.label }}</span>
                    <small>{{
                        item.help ||
                        item.description ||
                        "Según filtros aplicados"
                    }}</small>
                </article>
            </div>
            <div class="ld-analytics-grid">
                <AnalyticsPanel
                    title="Evolución de asistencia"
                    kicker="Tendencia"
                    help="Tasa ponderada sobre la nómina y días válidos del periodo."
                    :empty="!timeline.length"
                    :headers="['Periodo', 'Asistencia']"
                    :rows="
                        timeline.map((item) => [
                            item.label || item.date,
                            fmt(item.attendance_rate ?? item.value, '%'),
                        ])
                    "
                    ><apexchart
                        type="area"
                        height="290"
                        :options="attendanceOptions"
                        :series="attendanceSeries"
                /></AnalyticsPanel>
                <AnalyticsPanel
                    title="Estado de sesiones"
                    kicker="Completitud"
                    help="Distribuye sesiones por borrador, listas para firma, firmadas, cerradas o anuladas."
                    :empty="!sessionStatuses.length"
                    :headers="['Estado', 'Sesiones']"
                    :rows="
                        sessionStatuses.map((item) => [
                            item.label || item.name || item.status,
                            item.value ?? item.total,
                        ])
                    "
                    ><apexchart
                        type="donut"
                        height="290"
                        :options="sessionOptions"
                        :series="sessionSeries"
                /></AnalyticsPanel>
                <AnalyticsPanel
                    title="Cobertura curricular"
                    kicker="Currículum"
                    help="Avance declarado por asignatura u objetivo; no convierte una sesión automáticamente en cobertura completa."
                    :empty="!coverage.length"
                    :headers="['Asignatura u OA', 'Cobertura']"
                    :rows="
                        coverage.map((item) => [
                            item.label || item.subject || item.objective,
                            fmt(item.percentage ?? item.value, '%'),
                        ])
                    "
                    ><apexchart
                        type="bar"
                        height="310"
                        :options="coverageOptions"
                        :series="coverageSeries"
                /></AnalyticsPanel>
                <AnalyticsPanel
                    title="Distribución de riesgo"
                    kicker="Asistencia"
                    help="Tramos configurados institucionalmente; no reemplaza el análisis profesional del caso."
                    :empty="!risk.length"
                    :headers="['Tramo', 'Estudiantes']"
                    :rows="
                        risk.map((item) => [
                            item.label || item.name,
                            item.value ?? item.total,
                        ])
                    "
                    ><apexchart
                        type="donut"
                        height="310"
                        :options="riskOptions"
                        :series="riskSeries"
                /></AnalyticsPanel>
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
    font-size: 1.2rem;
    box-shadow: 0 6px 18px rgba(64, 81, 137, 0.08);
}
.ld-eyebrow {
    display: block;
    color: var(--ld-primary);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.ld-section-head h2 {
    margin: 0.14rem 0 0.2rem;
    color: var(--ld-ink);
    font-size: 1.35rem;
    letter-spacing: -0.025em;
}
.ld-section-head p {
    margin: 0;
    color: var(--ld-muted);
    font-size: 0.8rem;
    line-height: 1.45;
}
.ld-action-button,
.ld-filter-submit {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}
.ld-surface {
    overflow: hidden;
    border: 1px solid var(--ld-line) !important;
    border-radius: 14px;
    box-shadow: 0 8px 26px rgba(26, 39, 66, 0.05);
}
.ld-filter-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--ld-line);
    background: linear-gradient(180deg, #fff, #fbfcfe);
}
.ld-filter-panel__head > div {
    display: flex;
    align-items: center;
    gap: 0.62rem;
}
.ld-filter-panel__icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #eef2fb;
    color: var(--ld-primary);
    font-size: 1rem;
}
.ld-filter-panel h3 {
    margin: 0;
    color: var(--ld-ink);
    font-size: 0.9rem;
}
.ld-filter-panel p {
    margin: 0.12rem 0 0;
    color: var(--ld-muted);
    font-size: 0.72rem;
}
.ld-filter-panel__hint {
    color: #7b8797;
    font-size: 0.72rem;
}
.ld-filter-form {
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
}
.ld-field {
    min-width: 160px;
}
.ld-field--grow {
    flex: 1;
    max-width: 320px;
}
.ld-field label {
    display: block;
    margin-bottom: 0.32rem;
    color: #596579;
    font-size: 0.72rem;
    font-weight: 750;
}
.ld-field :deep(.form-control),
.ld-field :deep(.form-select) {
    min-height: 40px;
    font-size: 0.8rem;
}
.ld-filter-submit {
    min-width: 132px;
}
.ld-inline-alert {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 0.8rem;
    font-size: 0.8rem;
}
.ld-inline-alert > i {
    font-size: 1.05rem;
}
.ld-inline-alert .btn {
    min-height: 36px;
    margin-left: auto;
    padding-inline: 0.45rem;
    font-size: 0.75rem;
}
.ld-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.75rem;
}
.ld-kpi-card {
    position: relative;
    min-width: 0;
    padding: 0.9rem;
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
    background: #405189;
    content: "";
}
.ld-kpi-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}
.ld-kpi-card__icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #eef2fb;
    color: var(--ld-primary);
    font-size: 1rem;
}
.ld-kpi-card__period {
    overflow: hidden;
    color: #8a94a2;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-kpi-card strong,
.ld-kpi-card__label,
.ld-kpi-card small {
    display: block;
}
.ld-kpi-card strong {
    margin: 0.65rem 0 0.18rem;
    color: var(--ld-ink);
    font-size: 1.28rem;
    line-height: 1.05;
}
.ld-kpi-card strong.ld-kpi-card__empty {
    color: #8993a2;
    font-size: 0.78rem;
}
.ld-kpi-card__label {
    overflow: hidden;
    color: #475467;
    font-size: 0.76rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-kpi-card small {
    margin-top: 0.18rem;
    color: #8993a2;
    font-size: 0.68rem;
    line-height: 1.35;
}
.ld-kpi-card--tone-2::after {
    background: #2b8a66;
}
.ld-kpi-card--tone-2 .ld-kpi-card__icon {
    background: #e8f6f1;
    color: #247459;
}
.ld-kpi-card--tone-3::after {
    background: #3191a8;
}
.ld-kpi-card--tone-3 .ld-kpi-card__icon {
    background: #e8f6fa;
    color: #26758b;
}
.ld-kpi-card--tone-4::after {
    background: #7769aa;
}
.ld-kpi-card--tone-4 .ld-kpi-card__icon {
    background: #f0edfa;
    color: #65569a;
}
.ld-kpi-card--tone-5::after {
    background: #d99a24;
}
.ld-kpi-card--tone-5 .ld-kpi-card__icon {
    background: #fff6df;
    color: #9a6500;
}
.ld-kpi-card--tone-6::after {
    background: #d05a67;
}
.ld-kpi-card--tone-6 .ld-kpi-card__icon {
    background: #fff0f2;
    color: #b53b49;
}
.ld-analytics-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
@media (max-width: 1250px) {
    .ld-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 900px) {
    .ld-filter-panel__head {
        align-items: flex-start;
    }
    .ld-filter-panel__hint {
        display: none;
    }
    .ld-filter-form {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-field,
    .ld-field--grow {
        width: 100%;
        max-width: none;
    }
    .ld-filter-submit {
        width: 100%;
    }
    .ld-analytics-grid {
        grid-template-columns: 1fr;
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
    .ld-action-button {
        width: 38px;
        padding-inline: 0;
    }
    .ld-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 480px) {
    .ld-kpi-grid {
        grid-template-columns: 1fr;
    }
}
</style>
