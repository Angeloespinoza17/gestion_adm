<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import { downloadPsychologyReportPdf } from "./psychology-report-pdf";
import PsychologyBadge from "./PsychologyBadge.vue";
import PsychologyModal from "./PsychologyModal.vue";
import {
    priorityLabels,
    psychologyStatusLabels,
    usePsychology,
} from "../../composables/usePsychology";
const props = defineProps({
    mode: { type: String, required: true },
    catalogs: { type: Object, required: true },
});
const emit = defineEmits(["changed"]);
const api = usePsychology();
const data = ref(null);
const configModal = ref("");
const filters = reactive({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    professional_id: "",
});
const settings = reactive({ ...props.catalogs.settings });
const catalogForm = reactive({
    type: "referral_reason",
    slug: "",
    name: "",
    description: "",
    active: true,
    sort_order: 99,
});
const catalogRows = computed(() =>
    Object.entries(props.catalogs.catalogs || {}).flatMap(([type, items]) =>
        (items || []).map((item) => ({ ...item, type }))
    )
);
watch(
    () => props.catalogs.settings,
    (value) => {
        if (configModal.value !== "settings")
            Object.assign(settings, value || {});
    },
    { deep: true, immediate: true }
);
const load = async () => {
    if (props.mode === "reports")
        data.value = await api.get("/api/psychology/reports", {
            from: filters.from,
            to: filters.to,
            ...(filters.professional_id
                ? { professional_id: filters.professional_id }
                : {}),
        });
    else if (props.mode === "audit")
        data.value = await api.get("/api/psychology/audit");
    else if (["tasks", "alerts"].includes(props.mode))
        data.value = await api.get("/api/psychology/dashboard");
};
watch(() => props.mode, load);
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, bootstrap5Plugin],
    locales: [esLocale],
    themeSystem: "bootstrap5",
    initialView: "dayGridMonth",
    locale: esLocale,
    firstDay: 1,
    fixedWeekCount: false,
    headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
    },
    buttonText: {
        today: "Hoy",
        month: "Mes",
        week: "Semana",
        day: "Día",
        list: "Agenda",
    },
    noEventsText: "No hay eventos programados en este período",
    moreLinkText: (count) => `+${count} más`,
    events: async (info, success, failure) => {
        try {
            const response = await api.get("/api/psychology/calendar", {
                from: info.startStr.slice(0, 10),
                to: info.endStr.slice(0, 10),
            });
            success(response.data);
        } catch (e) {
            failure(e);
        }
    },
    eventDidMount: ({ el, event }) => {
        const labels = {
            activity: "Atención",
            follow_up: "Seguimiento",
            task: "Tarea",
            coordination: "Coordinación aceptada",
        };
        el.title = `${labels[event.extendedProps.type] || "Actividad"}: ${
            event.title
        }`;
    },
    height: "auto",
}));
const reportCountLabels = {
    referrals: "Derivaciones",
    cases: "Casos",
    unique_students: "Estudiantes únicos",
    activities: "Atenciones registradas",
};
const reportCountIcons = {
    referrals: "bx-transfer-alt",
    cases: "bx-folder-open",
    unique_students: "bx-user-pin",
    activities: "bx-calendar-check",
};
const personalReportScope = computed(
    () =>
        data.value?.scope?.type === "personal" ||
        props.catalogs.capabilities?.personal_scope
);
const reportScopeType = computed(() =>
    personalReportScope.value ? "personal" : data.value?.scope?.type
);
const reportHeading = computed(() => {
    if (reportScopeType.value === "personal") return "Mis estadísticas";
    if (reportScopeType.value === "professional")
        return `Estadísticas de ${
            data.value?.scope?.professional_name || "la profesional"
        }`;
    return "Reportes de gestión";
});
const reportDescription = computed(() => {
    if (reportScopeType.value === "personal")
        return "Indicadores calculados únicamente con tus casos, derivaciones asignadas, atenciones y tareas.";
    if (reportScopeType.value === "professional")
        return "Resultados individuales de la profesional seleccionada, sin mezclar registros de otras psicólogas.";
    return "Selecciona una psicóloga para revisar su desempeño individual o conserva la vista institucional.";
});
const reportScopeChip = computed(() => {
    if (reportScopeType.value === "personal") return "Solo mis datos";
    if (reportScopeType.value === "professional") return "Vista individual";
    return "Vista institucional";
});
const reportBarWidth = (item, items) => {
    const maximum = Math.max(
        1,
        ...(items || []).map((row) => Number(row.total))
    );
    return `${Math.max(5, (Number(item.total) / maximum) * 100)}%`;
};
const reportMonthLabel = (value) => {
    if (!value) return "Sin fecha";
    return new Intl.DateTimeFormat("es-CL", {
        month: "short",
        year: "numeric",
    }).format(new Date(`${value}-01T12:00:00`));
};
const activityTypeLabel = (value) =>
    (props.catalogs.catalogs?.activity_type || []).find(
        (item) => item.slug === value
    )?.name || value;
const reportOperationalMetrics = computed(() => [
    {
        label: "Promedio primera revisión",
        value: `${
            data.value?.service_levels?.average_first_review_hours || 0
        } h`,
        icon: "bx-time-five",
    },
    {
        label: "Mediana primera revisión",
        value: `${
            data.value?.service_levels?.median_first_review_hours || 0
        } h`,
        icon: "bx-timer",
    },
    {
        label: "Casos sin actividad reciente",
        value: data.value?.service_levels?.cases_without_activity || 0,
        icon: "bx-pause-circle",
    },
    {
        label: "Seguimientos pendientes",
        value: data.value?.tasks?.pending_followups || 0,
        icon: "bx-calendar-exclamation",
    },
    {
        label: "Tareas vencidas",
        value: data.value?.tasks?.overdue || 0,
        icon: "bx-error-circle",
    },
]);
const settingLabels = {
    first_review_hours: "Horas para primera revisión",
    first_intervention_hours: "Horas para primera intervención",
    inactive_days: "Días para considerar un caso inactivo",
    reiteration_days: "Días para detectar una reiteración",
    anonymization_threshold: "Umbral mínimo de anonimización",
    max_file_kb: "Tamaño máximo de archivo (KB)",
    closure_approval_required: "Requiere aprobación para cerrar",
};
const catalogTypeLabels = {
    referral_reason: "Motivo de derivación",
    activity_type: "Tipo de atención",
    risk_type: "Tipo de riesgo",
    closure_type: "Tipo de cierre",
    document_category: "Categoría documental",
};
const auditActionLabels = {
    "activity.addendum_created": "Adenda de atención creada",
    "activity.created": "Atención registrada",
    "activity.finalized": "Atención finalizada",
    "activity.pdf_exported": "Ficha de atención exportada en PDF",
    "plan.pdf_exported": "Plan de intervención exportado en PDF",
    "case.closed": "Caso cerrado",
    "case.opened": "Caso abierto",
    "case.reassigned": "Caso reasignado",
    "case.reopened": "Caso reabierto",
    "case.viewed": "Ficha de caso consultada",
    "catalog.updated": "Catálogo actualizado",
    "consent.created": "Consentimiento registrado",
    "document.archived": "Documento archivado",
    "document.downloaded": "Documento descargado",
    "document.uploaded": "Documento cargado",
    "external_referral.created": "Derivación externa registrada",
    "feedback.shared": "Retroalimentación compartida",
    "plan.created": "Plan de intervención creado",
    "plan.versioned": "Plan de intervención versionado",
    "referral.assigned": "Derivación asignada",
    "referral.created": "Derivación creada",
    "referral.status_changed": "Estado de derivación actualizado",
    "referral.updated": "Derivación actualizada",
    "referral.viewed": "Derivación consultada",
    "report.nominal_exported": "Reporte detallado exportado",
    "report.nominal_generated": "Reporte detallado generado",
    "report.queued_export_downloaded": "Exportación descargada",
    "risk.acknowledged": "Alerta de riesgo reconocida",
    "risk.created": "Evaluación de riesgo registrada",
    "task.created": "Tarea creada",
    "task.status_changed": "Estado de tarea actualizado",
};
const auditEntityLabels = {
    PsychologyActivity: "Atención",
    PsychologyCase: "Caso",
    PsychologyCatalogItem: "Catálogo",
    PsychologyConsent: "Consentimiento",
    PsychologyDocument: "Documento",
    PsychologyExternalReferral: "Derivación externa",
    PsychologyInterventionPlan: "Plan de intervención",
    PsychologyReferral: "Derivación",
    PsychologyReportExport: "Exportación",
    PsychologyRiskAssessment: "Evaluación de riesgo",
    PsychologyTask: "Tarea",
};
const auditEntity = (type) => {
    const model = (type || "").split("\\").pop();
    return auditEntityLabels[model] || model || "Registro";
};
const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat("es-CL", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "Sin fecha";
const exportPdf = async () => {
    if (!data.value) return;
    await downloadPsychologyReportPdf(data.value, {
        activityTypes: props.catalogs.catalogs?.activity_type || [],
    });
};
const exportExcel = () => {
    if (!data.value) return;
    const professional = ["personal", "professional"].includes(
        data.value.scope?.type
    );
    const rows = [
        [
            professional
                ? `Estadísticas de ${data.value.scope.professional_name}`
                : "Reporte institucional de Psicología",
        ],
        [
            "Alcance",
            professional
                ? `${data.value.scope.professional_name} · Solo actividad profesional propia`
                : "Institucional",
        ],
        [],
        ["Indicador", "Cantidad"],
        ["Derivaciones", data.value.counts.referrals],
        ["Casos", data.value.counts.cases],
        ["Estudiantes únicos", data.value.counts.unique_students],
        ["Actividades", data.value.counts.activities],
        [],
        ["Motivo", "Cantidad"],
        ...(data.value.by_reason || []).map((x) => [x.label, x.total]),
    ];
    const html = `<html><head><meta charset="UTF-8"></head><body><table>${rows
        .map(
            (r) =>
                `<tr>${r
                    .map(
                        (c) =>
                            `<td>${String(c ?? "").replace(
                                /[<>&]/g,
                                (s) =>
                                    ({ "<": "&lt;", ">": "&gt;", "&": "&amp;" }[
                                        s
                                    ])
                            )}</td>`
                    )
                    .join("")}</tr>`
        )
        .join("")}</table></body></html>`;
    const url = URL.createObjectURL(
        new Blob([html], { type: "application/vnd.ms-excel" })
    );
    const a = document.createElement("a");
    a.href = url;
    a.download = `${
        professional
            ? "estadisticas-profesional-psicologia"
            : "reporte-psicologia"
    }-${filters.from}-${filters.to}.xls`;
    a.click();
    URL.revokeObjectURL(url);
};
const exportCsv = async () => {
    const response = await axios.get("/api/psychology/reports/export.csv", {
        params: {
            from: filters.from,
            to: filters.to,
            ...(filters.professional_id
                ? { professional_id: filters.professional_id }
                : {}),
        },
        responseType: "blob",
    });
    const url = URL.createObjectURL(response.data);
    const a = document.createElement("a");
    a.href = url;
    a.download = `reporte-psicologia-${filters.from}-${filters.to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
};
const saveSettings = async () => {
    await api.put("/api/psychology/configuration/settings", { settings });
    configModal.value = "";
    emit("changed");
};
const saveCatalog = async () => {
    await api.post("/api/psychology/configuration/catalogs", catalogForm);
    Object.assign(catalogForm, { slug: "", name: "", description: "" });
    configModal.value = "";
    emit("changed");
};
onMounted(load);
</script>
<template>
    <div>
        <div v-if="api.error.value" class="alert alert-danger">
            {{ api.error.value }}
        </div>
        <section v-if="mode === 'calendar'" class="psi-workspace-card">
            <header class="psi-workspace-head">
                <div class="psi-workspace-title">
                    <span class="psi-workspace-icon">
                        <i class="bx bx-calendar-event"></i>
                    </span>
                    <div>
                        <small>Planificación profesional</small>
                        <h4>Agenda profesional</h4>
                        <p>
                            Los eventos usan etiquetas seguras y no exponen
                            información sensible.
                        </p>
                    </div>
                </div>
                <span class="psi-security-chip">
                    <i class="bx bx-lock-alt"></i> Contenido protegido
                </span>
            </header>
            <div class="psi-calendar-legend" aria-label="Tipos de eventos">
                <span class="is-attention"><i></i> Atenciones</span>
                <span class="is-followup"><i></i> Seguimientos</span>
                <span class="is-task"><i></i> Tareas</span>
                <span class="is-coordination"
                    ><i></i> Coordinaciones aceptadas</span
                >
            </div>
            <div class="psi-calendar-wrap">
                <FullCalendar :options="calendarOptions" />
            </div>
        </section>
        <section v-else-if="mode === 'tasks'" class="psi-task-workspace">
            <header class="psi-task-hero">
                <div class="psi-task-hero__identity">
                    <span class="psi-task-hero__icon">
                        <i class="bx bx-list-check"></i>
                    </span>
                    <div>
                        <small>ORGANIZACIÓN PERSONAL</small>
                        <h4>Tareas de Psicología</h4>
                        <p>
                            Reúne tus compromisos profesionales y accede a tu
                            backlog privado de funcionario.
                        </p>
                    </div>
                </div>
                <span class="psi-task-private-chip">
                    <i class="bx bx-lock-alt"></i> Vista personal
                </span>
            </header>

            <article class="psi-backlog-link-card">
                <div class="psi-backlog-link-card__visual">
                    <i class="bx bx-check-square"></i>
                </div>
                <div class="psi-backlog-link-card__content">
                    <span>BACKLOG DEL FUNCIONARIO AUTENTICADO</span>
                    <h5>Mi backlog personal</h5>
                    <p>
                        Organiza pendientes, prioridades y fechas. Solo verás
                        tus tareas y aquellas donde fuiste incorporado como
                        participante explícito.
                    </p>
                </div>
                <router-link to="/tasks/backlog" class="btn btn-primary">
                    <i class="bx bx-right-arrow-alt me-1"></i>
                    Abrir mi backlog
                </router-link>
            </article>

            <section class="psi-case-tasks">
                <div class="psi-case-tasks__head">
                    <div>
                        <span>COMPROMISOS DE CASOS</span>
                        <h5>Tareas vinculadas a Psicología</h5>
                    </div>
                    <strong>{{ data?.recent?.tasks?.length || 0 }}</strong>
                </div>
                <div v-if="!data?.recent?.tasks?.length" class="psi-empty">
                    <i class="bx bx-calendar-check"></i>
                    <h6>No tienes tareas de casos pendientes</h6>
                    <p>
                        Puedes continuar organizando el resto de tu trabajo en
                        tu backlog personal.
                    </p>
                </div>
                <div v-else class="psi-case-task-list">
                    <article
                        v-for="item in data?.recent?.tasks || []"
                        :key="item.id"
                        class="psi-case-task-row"
                    >
                        <span class="psi-case-task-row__icon">
                            <i class="bx bx-task"></i>
                        </span>
                        <div>
                            <strong>{{ item.title }}</strong>
                            <span>
                                {{ item.case?.code }} · vence
                                {{ formatDateTime(item.due_at) }}
                            </span>
                        </div>
                        <PsychologyBadge :value="item.status" />
                    </article>
                </div>
            </section>
        </section>
        <div v-else-if="mode === 'alerts'" class="card border-0 shadow-sm">
            <div class="card-body">
                <h4>Alertas y plazos</h4>
                <div class="psi-alert-grid">
                    <article>
                        <i class="bx bx-error-circle"></i
                        ><strong>{{
                            data?.metrics?.high_priority_cases || 0
                        }}</strong
                        ><span>Casos de prioridad alta o crítica</span>
                    </article>
                    <article>
                        <i class="bx bx-time-five"></i
                        ><strong>{{
                            data?.metrics?.inactive_cases || 0
                        }}</strong
                        ><span>Casos sin actividad reciente</span>
                    </article>
                    <article>
                        <i class="bx bx-error-alt"></i
                        ><strong>{{ data?.metrics?.overdue_tasks || 0 }}</strong
                        ><span>Tareas vencidas</span>
                    </article>
                    <article>
                        <i class="bx bx-message-error"></i
                        ><strong>{{
                            data?.metrics?.information_requested || 0
                        }}</strong
                        ><span>Antecedentes pendientes</span>
                    </article>
                </div>
                <p class="alert alert-warning mt-3 mb-0">
                    Las alertas muestran solo cantidades. Abre un caso
                    autorizado para consultar sus medidas de resguardo.
                </p>
            </div>
        </div>
        <section v-else-if="mode === 'reports'" class="psi-workspace-card">
            <header class="psi-workspace-head">
                <div class="psi-workspace-title">
                    <span class="psi-workspace-icon">
                        <i class="bx bx-bar-chart-alt-2"></i>
                    </span>
                    <div>
                        <small>{{
                            reportScopeType === "institutional"
                                ? "Análisis institucional"
                                : "Análisis profesional"
                        }}</small>
                        <h4>{{ reportHeading }}</h4>
                        <p>{{ reportDescription }}</p>
                        <span class="psi-scope-chip">
                            <i
                                :class="[
                                    'bx',
                                    reportScopeType === 'institutional'
                                        ? 'bx-buildings'
                                        : 'bx-lock-alt',
                                ]"
                            ></i>
                            {{ reportScopeChip }}
                        </span>
                    </div>
                </div>
                <div class="psi-export-actions">
                    <button
                        type="button"
                        class="psi-action-btn danger"
                        :disabled="!data"
                        @click="exportPdf"
                    >
                        <i class="bx bxs-file-pdf"></i>
                        <span>PDF</span>
                    </button>
                    <button
                        type="button"
                        class="psi-action-btn success"
                        :disabled="!data"
                        @click="exportExcel"
                    >
                        <i class="bx bx-spreadsheet"></i>
                        <span>Excel</span>
                    </button>
                    <button
                        v-if="catalogs.capabilities.reports_nominal"
                        type="button"
                        class="psi-action-btn neutral"
                        :disabled="!data"
                        title="Exportar detalle nominal en CSV"
                        @click="exportCsv"
                    >
                        <i class="bx bx-table"></i>
                        <span>CSV</span>
                    </button>
                </div>
            </header>
            <div class="psi-workspace-body">
                <div class="psi-report-filters">
                    <div
                        v-if="!personalReportScope"
                        class="psi-filter-field psi-filter-professional"
                    >
                        <label for="psychology-report-professional"
                            >Psicóloga</label
                        >
                        <select
                            id="psychology-report-professional"
                            v-model="filters.professional_id"
                            class="form-select"
                        >
                            <option value="">Todas las profesionales</option>
                            <option
                                v-for="professional in catalogs.professionals ||
                                []"
                                :key="professional.id"
                                :value="professional.id"
                            >
                                {{ professional.name }}
                            </option>
                        </select>
                    </div>
                    <div class="psi-filter-field">
                        <label for="psychology-report-from">Desde</label>
                        <input
                            id="psychology-report-from"
                            v-model="filters.from"
                            type="date"
                            class="form-control"
                        />
                    </div>
                    <div class="psi-filter-field">
                        <label for="psychology-report-to">Hasta</label>
                        <input
                            id="psychology-report-to"
                            v-model="filters.to"
                            type="date"
                            class="form-control"
                        />
                    </div>
                    <button
                        type="button"
                        class="psi-filter-button"
                        :disabled="api.loading.value"
                        @click="load"
                    >
                        <i class="bx bx-filter-alt"></i>
                        {{
                            api.loading.value
                                ? "Actualizando…"
                                : "Aplicar filtros"
                        }}
                    </button>
                </div>
                <div class="psi-counts">
                    <article
                        v-for="(value, key) in data?.counts || {}"
                        :key="key"
                    >
                        <span class="psi-count-icon">
                            <i
                                :class="`bx ${
                                    reportCountIcons[key] || 'bx-data'
                                }`"
                            ></i>
                        </span>
                        <span>
                            <strong>{{ value }}</strong>
                            <small>{{ reportCountLabels[key] || key }}</small>
                        </span>
                    </article>
                </div>
                <section class="psi-report-insights">
                    <article class="psi-insight-panel psi-insight-wide">
                        <header>
                            <span
                                ><i class="bx bx-line-chart"></i>Evolución de
                                derivaciones</span
                            >
                            <small>Por mes</small>
                        </header>
                        <div
                            v-if="data?.monthly_evolution?.length"
                            class="psi-bars"
                        >
                            <div
                                v-for="item in data.monthly_evolution"
                                :key="item.label"
                                class="psi-bar-row"
                            >
                                <span>{{ reportMonthLabel(item.label) }}</span>
                                <div>
                                    <i
                                        :style="{
                                            width: reportBarWidth(
                                                item,
                                                data.monthly_evolution
                                            ),
                                        }"
                                    ></i>
                                </div>
                                <strong>{{ item.total }}</strong>
                            </div>
                        </div>
                        <p v-else class="psi-insight-empty">
                            Sin derivaciones en el período seleccionado.
                        </p>
                    </article>
                    <article class="psi-insight-panel">
                        <header>
                            <span
                                ><i class="bx bx-pulse"></i>Estado de
                                derivaciones</span
                            >
                            <small>Distribución</small>
                        </header>
                        <div v-if="data?.by_status?.length" class="psi-bars">
                            <div
                                v-for="item in data.by_status"
                                :key="item.label"
                                class="psi-bar-row"
                            >
                                <span>{{
                                    psychologyStatusLabels[item.label] ||
                                    item.label
                                }}</span>
                                <div>
                                    <i
                                        :style="{
                                            width: reportBarWidth(
                                                item,
                                                data.by_status
                                            ),
                                        }"
                                    ></i>
                                </div>
                                <strong>{{ item.total }}</strong>
                            </div>
                        </div>
                        <p v-else class="psi-insight-empty">Sin datos.</p>
                    </article>
                    <article class="psi-insight-panel">
                        <header>
                            <span><i class="bx bx-flag"></i>Prioridad</span>
                            <small>Casos derivados</small>
                        </header>
                        <div v-if="data?.by_priority?.length" class="psi-bars">
                            <div
                                v-for="item in data.by_priority"
                                :key="item.label"
                                class="psi-bar-row"
                            >
                                <span>{{
                                    priorityLabels[item.label] || item.label
                                }}</span>
                                <div>
                                    <i
                                        :style="{
                                            width: reportBarWidth(
                                                item,
                                                data.by_priority
                                            ),
                                        }"
                                    ></i>
                                </div>
                                <strong>{{ item.total }}</strong>
                            </div>
                        </div>
                        <p v-else class="psi-insight-empty">Sin datos.</p>
                    </article>
                    <article class="psi-insight-panel">
                        <header>
                            <span
                                ><i class="bx bx-conversation"></i>Atenciones
                                por tipo</span
                            >
                            <small>Actividad profesional</small>
                        </header>
                        <div
                            v-if="data?.activities_by_type?.length"
                            class="psi-bars"
                        >
                            <div
                                v-for="item in data.activities_by_type"
                                :key="item.label"
                                class="psi-bar-row"
                            >
                                <span>{{ activityTypeLabel(item.label) }}</span>
                                <div>
                                    <i
                                        :style="{
                                            width: reportBarWidth(
                                                item,
                                                data.activities_by_type
                                            ),
                                        }"
                                    ></i>
                                </div>
                                <strong>{{ item.total }}</strong>
                            </div>
                        </div>
                        <p v-else class="psi-insight-empty">Sin atenciones.</p>
                    </article>
                    <article class="psi-insight-panel psi-operational-panel">
                        <header>
                            <span
                                ><i class="bx bx-tachometer"></i>Indicadores
                                operativos</span
                            >
                            <small>Seguimiento</small>
                        </header>
                        <div class="psi-operational-list">
                            <div
                                v-for="metric in reportOperationalMetrics"
                                :key="metric.label"
                            >
                                <i :class="['bx', metric.icon]"></i>
                                <span>{{ metric.label }}</span>
                                <strong>{{ metric.value }}</strong>
                            </div>
                        </div>
                    </article>
                </section>
                <div class="psi-report-table table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Motivo / grupo protegido</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in data?.by_reason || []"
                                :key="item.label"
                            >
                                <td>{{ item.label }}</td>
                                <td>{{ item.total }}</td>
                            </tr>
                            <tr v-if="!data?.by_reason?.length">
                                <td colspan="2" class="psi-empty compact">
                                    <i class="bx bx-bar-chart-square"></i>
                                    <span
                                        >No hay datos para el período
                                        seleccionado.</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <div v-else-if="mode === 'config'">
            <div class="psi-section-head">
                <div>
                    <h4>Configuración de Psicología</h4>
                    <p>Parámetros y catálogos institucionales versionables.</p>
                </div>
                <div class="d-flex gap-2">
                    <button
                        class="btn btn-outline-primary"
                        @click="configModal = 'settings'"
                    >
                        Editar parámetros
                    </button>
                    <button
                        class="btn btn-primary"
                        @click="configModal = 'catalog'"
                    >
                        <i class="bx bx-plus me-1"></i>Nuevo catálogo
                    </button>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="psi-table-card table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Parámetro</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(value, key) in settings" :key="key">
                                    <td>
                                        <strong>{{
                                            settingLabels[key] || key
                                        }}</strong>
                                    </td>
                                    <td>{{ value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="psi-table-card table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in catalogRows"
                                    :key="`${item.type}-${item.id}`"
                                >
                                    <td>
                                        {{
                                            catalogTypeLabels[item.type] ||
                                            item.type
                                        }}
                                    </td>
                                    <td>{{ item.slug }}</td>
                                    <td>
                                        <strong>{{ item.name }}</strong>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-success-subtle text-success"
                                            >{{
                                                item.active === false
                                                    ? "Inactivo"
                                                    : "Activo"
                                            }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!catalogRows.length">
                                    <td colspan="4" class="psi-empty">
                                        No hay ítems de catálogo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <PsychologyModal
                v-if="configModal === 'settings'"
                eyebrow="Configuración institucional"
                title="Editar parámetros internos"
                @close="configModal = ''"
            >
                <div class="row g-3">
                    <div
                        v-for="(value, key) in settings"
                        :key="key"
                        class="col-md-6"
                    >
                        <label class="form-label">{{
                            settingLabels[key] || key
                        }}</label
                        ><input v-model="settings[key]" class="form-control" />
                    </div>
                </div>
                <template #footer
                    ><button class="btn btn-light" @click="configModal = ''">
                        Cancelar</button
                    ><button class="btn btn-primary" @click="saveSettings">
                        Guardar parámetros
                    </button></template
                >
            </PsychologyModal>
            <PsychologyModal
                v-if="configModal === 'catalog'"
                eyebrow="Catálogo institucional"
                title="Nuevo ítem de catálogo"
                @close="configModal = ''"
            >
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label
                        ><select v-model="catalogForm.type" class="form-select">
                            <option value="referral_reason">
                                Motivo de derivación
                            </option>
                            <option value="activity_type">
                                Tipo de actividad
                            </option>
                            <option value="risk_type">Tipo de riesgo</option>
                            <option value="closure_type">Tipo de cierre</option>
                            <option value="document_category">
                                Categoría documental
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Código interno</label
                        ><input
                            v-model="catalogForm.slug"
                            class="form-control"
                        />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nombre visible</label
                        ><input
                            v-model="catalogForm.name"
                            class="form-control"
                        />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label
                        ><textarea
                            v-model="catalogForm.description"
                            class="form-control"
                            rows="3"
                        ></textarea>
                    </div>
                </div>
                <template #footer
                    ><button class="btn btn-light" @click="configModal = ''">
                        Cancelar</button
                    ><button class="btn btn-primary" @click="saveCatalog">
                        Guardar catálogo
                    </button></template
                >
            </PsychologyModal>
        </div>
        <div v-else-if="mode === 'audit'" class="card border-0 shadow-sm">
            <div class="card-body">
                <h4>Auditoría de accesos y acciones</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Entidad</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in data?.data || []" :key="item.id">
                                <td>
                                    {{ formatDateTime(item.occurred_at) }}
                                </td>
                                <td>{{ item.user_name || "Sistema" }}</td>
                                <td>
                                    {{
                                        auditActionLabels[item.action] ||
                                        item.action
                                    }}
                                </td>
                                <td>
                                    {{ auditEntity(item.auditable_type) }}
                                    #{{ item.auditable_id }}
                                </td>
                                <td>{{ item.reason || "—" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
<style scoped>
.alert-danger {
    margin-bottom: 0.75rem;
    padding: 0.65rem 0.8rem;
    border-radius: 10px;
    font-size: 0.72rem;
}
.psi-workspace-card {
    overflow: hidden;
    background: #fff;
    border: 1px solid #dce5ed;
    border-radius: 18px;
    box-shadow: 0 14px 38px rgba(40, 55, 78, 0.07);
}
.psi-workspace-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.95rem 1.1rem;
    background: linear-gradient(135deg, #fff, #f6f9fc);
    border-bottom: 1px solid #e5ebf1;
}
.psi-workspace-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}
.psi-workspace-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.55rem;
    height: 2.55rem;
    place-items: center;
    color: #5f4c7a;
    background: #f0edf8;
    border-radius: 12px;
    font-size: 1.25rem;
}
.psi-workspace-title small {
    display: block;
    margin-bottom: 0.05rem;
    color: #7e6a9b;
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.psi-workspace-title h4 {
    margin: 0;
    color: #26374c;
    font-size: 1rem;
}
.psi-workspace-title p {
    margin: 0.15rem 0 0;
    color: #748195;
    font-size: 0.72rem;
}
.psi-scope-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.4rem;
    padding: 0.28rem 0.52rem;
    color: #5d4a77;
    background: #f1edf8;
    border: 1px solid #ded4eb;
    border-radius: 999px;
    font-size: 0.62rem;
    font-weight: 800;
}
.psi-security-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex: 0 0 auto;
    padding: 0.42rem 0.65rem;
    color: #5d4a77;
    background: #f3eff8;
    border: 1px solid #e5ddec;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
}
.psi-calendar-wrap,
.psi-workspace-body {
    padding: 1rem 1.1rem 1.15rem;
}
.psi-calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    padding: 0.8rem 1.1rem 0;
}
.psi-calendar-legend span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.34rem 0.55rem;
    color: #59687b;
    background: #f8fafc;
    border: 1px solid #e2e8ef;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 700;
}
.psi-calendar-legend i {
    width: 0.52rem;
    height: 0.52rem;
    background: #5275c7;
    border-radius: 50%;
}
.psi-calendar-legend .is-followup i {
    background: #70578f;
}
.psi-calendar-legend .is-task i {
    background: #3f8c71;
}
.psi-calendar-legend .is-coordination i {
    background: #b8783d;
}
.psi-export-actions {
    display: flex;
    flex: 0 0 auto;
    flex-wrap: nowrap;
    gap: 0.45rem;
}
.psi-action-btn,
.psi-filter-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.38rem;
    height: 38px;
    padding: 0 0.75rem;
    background: #fff;
    border: 1px solid #d9e1e9;
    border-radius: 9px;
    font-size: 0.7rem;
    font-weight: 700;
    transition: transform 0.15s ease, box-shadow 0.15s ease,
        border-color 0.15s ease;
}
.psi-action-btn:hover:not(:disabled),
.psi-filter-button:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(42, 54, 76, 0.1);
}
.psi-action-btn:disabled {
    cursor: not-allowed;
    opacity: 0.48;
}
.psi-action-btn.danger {
    color: #b84e55;
    border-color: #efc9cd;
    background: #fff8f8;
}
.psi-action-btn.success {
    color: #248362;
    border-color: #c4e5d8;
    background: #f5fcf9;
}
.psi-action-btn.neutral {
    color: #526176;
    border-color: #d6dee7;
    background: #f8fafc;
}
.psi-report-filters {
    display: grid;
    grid-template-columns:
        minmax(220px, 1.25fr) repeat(2, minmax(180px, 0.8fr))
        max-content;
    align-items: end;
    gap: 0.65rem;
    margin-bottom: 0.9rem;
    padding: 0.8rem;
    background: #f4f8fa;
    border: 1px solid #dce7ec;
    border-radius: 13px;
}
.psi-filter-field label {
    display: block;
    margin-bottom: 0.3rem;
    color: #5e6d80;
    font-size: 0.65rem;
    font-weight: 700;
}
.psi-filter-field .form-control,
.psi-filter-field .form-select {
    height: 40px;
    padding: 0 0.75rem;
    border-color: #dbe3ec;
    border-radius: 9px;
    font-size: 0.75rem;
}
.psi-filter-button {
    min-width: 140px;
    color: #fff;
    background: #556ee6;
    border-color: #556ee6;
}
.psi-counts {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 0.9rem;
}
.psi-counts article {
    display: flex;
    align-items: center;
    flex-direction: row;
    gap: 0.6rem;
    min-height: 72px;
    padding: 0.75rem;
    border-radius: 12px;
    box-shadow: none;
}
.psi-count-icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: #66527f;
    background: #f0edf8;
    border-radius: 10px;
    font-size: 1.05rem;
}
.psi-counts article > span:last-child {
    display: flex;
    min-width: 0;
    flex-direction: column;
}
.psi-report-insights {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.8rem;
    margin-bottom: 0.9rem;
}
.psi-insight-panel {
    min-width: 0;
    padding: 0.9rem;
    background: linear-gradient(145deg, #fff, #f9fbfd);
    border: 1px solid #e0e7ee;
    border-radius: 14px;
}
.psi-insight-wide {
    grid-column: 1 / -1;
}
.psi-insight-panel > header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}
.psi-insight-panel > header span {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #34445a;
    font-size: 0.75rem;
    font-weight: 800;
}
.psi-insight-panel > header span i {
    color: #685485;
    font-size: 1rem;
}
.psi-insight-panel > header small {
    color: #8a96a6;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
}
.psi-bars {
    display: grid;
    gap: 0.6rem;
}
.psi-bar-row {
    display: grid;
    grid-template-columns: minmax(115px, 0.8fr) minmax(100px, 2fr) 28px;
    align-items: center;
    gap: 0.65rem;
}
.psi-bar-row > span {
    overflow: hidden;
    color: #607086;
    font-size: 0.68rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.psi-bar-row > div {
    height: 8px;
    overflow: hidden;
    background: #edf1f5;
    border-radius: 999px;
}
.psi-bar-row > div i {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #725b96, #6385c7);
    border-radius: inherit;
}
.psi-bar-row strong {
    color: #334258;
    font-size: 0.7rem;
    text-align: right;
}
.psi-insight-empty {
    margin: 0;
    padding: 0.65rem;
    color: #8793a3;
    background: #f7f9fb;
    border-radius: 9px;
    font-size: 0.68rem;
    text-align: center;
}
.psi-operational-list {
    display: grid;
    gap: 0.45rem;
}
.psi-operational-list > div {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.55rem;
    color: #607086;
    background: #f7f9fc;
    border-radius: 9px;
    font-size: 0.66rem;
}
.psi-operational-list i {
    color: #6d5a8e;
    font-size: 0.9rem;
}
.psi-operational-list strong {
    color: #2f4057;
    font-size: 0.72rem;
}
.psi-counts strong {
    font-size: 1.15rem;
    line-height: 1.1;
}
.psi-counts small {
    color: #758297;
    font-size: 0.66rem;
}
.psi-report-table {
    overflow: hidden;
    border: 1px solid #e1e7ed;
    border-radius: 12px;
}
.psi-report-table th {
    padding: 0.7rem 0.8rem;
    color: #6e7b8e;
    background: #f7f9fb;
    border-bottom-color: #e1e7ed;
    font-size: 0.63rem;
    letter-spacing: 0.07em;
    text-transform: uppercase;
}
.psi-report-table td {
    padding: 0.7rem 0.8rem;
    color: #445267;
    font-size: 0.74rem;
}
.psi-empty.compact {
    padding: 1.35rem;
}
.psi-empty.compact i {
    margin-right: 0.35rem;
    color: #8a759f;
    font-size: 1.1rem;
}
:deep(.fc) {
    --fc-border-color: #e1e7ed;
    --fc-button-text-color: #536176;
    --fc-button-bg-color: #fff;
    --fc-button-border-color: #d6dee7;
    --fc-button-hover-bg-color: #f2eff8;
    --fc-button-hover-border-color: #d9d0e5;
    --fc-button-active-bg-color: #66527f;
    --fc-button-active-border-color: #66527f;
    --fc-today-bg-color: #fff9e8;
    color: #435064;
    font-size: 0.74rem;
}
:deep(.fc .fc-toolbar) {
    gap: 0.75rem;
    margin-bottom: 0.85rem;
}
:deep(.fc .fc-toolbar-title) {
    color: #2d3d52;
    font-size: 1rem;
    font-weight: 700;
}
:deep(.fc .fc-button) {
    min-width: 36px;
    height: 34px;
    padding: 0 0.6rem;
    border-radius: 8px;
    box-shadow: none !important;
    font-size: 0.68rem;
    font-weight: 700;
}
:deep(.fc .fc-button-primary:not(:disabled).fc-button-active) {
    color: #fff;
}
:deep(.fc .btn-primary) {
    color: #536176 !important;
    background: #fff !important;
    border-color: #d6dee7 !important;
}
:deep(.fc .btn-primary:hover:not(:disabled)) {
    color: #5f4c7a !important;
    background: #f2eff8 !important;
    border-color: #d9d0e5 !important;
}
:deep(.fc .btn-primary.fc-button-active) {
    color: #fff !important;
    background: #66527f !important;
    border-color: #66527f !important;
}
:deep(.fc .fc-prev-button .fc-icon),
:deep(.fc .fc-next-button .fc-icon) {
    display: none;
}
:deep(.fc .fc-prev-button::before) {
    content: "‹";
    font-size: 1.15rem;
    line-height: 1;
}
:deep(.fc .fc-next-button::before) {
    content: "›";
    font-size: 1.15rem;
    line-height: 1;
}
:deep(.fc .fc-button-group) {
    gap: 0.25rem;
}
:deep(.fc .fc-button-group > .fc-button) {
    margin-left: 0;
    border-radius: 8px;
}
:deep(.fc .fc-col-header-cell-cushion) {
    padding: 0.5rem;
    color: #566276;
    text-transform: capitalize;
}
:deep(.fc .fc-daygrid-day-number) {
    padding: 0.42rem;
    color: #536176;
}
.psi-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}
.psi-section-head h4 {
    margin: 0;
    color: #24324a;
}
.psi-section-head p {
    margin: 0.2rem 0 0;
    color: #718096;
}
.psi-table-card {
    background: #fff;
    border: 1px solid #dfe7ef;
    border-radius: 20px;
    box-shadow: 0 18px 48px rgba(39, 48, 77, 0.075);
    overflow: hidden;
}
.psi-table-card td,
.psi-table-card th {
    padding: 0.82rem 1rem;
}
.psi-table-card thead th {
    color: #718096;
    font-size: 0.67rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.psi-empty {
    padding: 3rem;
    text-align: center;
    color: #7b8798;
}
.psi-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 0;
    border-bottom: 1px solid #e8ebef;
}
.psi-row span {
    display: block;
    color: #7b8798;
    font-size: 0.75rem;
}
.psi-task-workspace {
    overflow: hidden;
    background: linear-gradient(145deg, #f8fbfd 0%, #f4f3fb 100%);
    border: 1px solid #dfe6ee;
    border-radius: 24px;
    box-shadow: 0 22px 55px rgba(42, 56, 82, 0.08);
}
.psi-task-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.4rem 1.5rem;
    background: rgba(255, 255, 255, 0.82);
    border-bottom: 1px solid #e4e9f0;
}
.psi-task-hero__identity {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.psi-task-hero__icon {
    display: grid;
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    color: #665096;
    background: linear-gradient(145deg, #eee8f7, #f5f2fa);
    border: 1px solid #e1d9ef;
    border-radius: 18px;
    place-items: center;
    font-size: 1.65rem;
}
.psi-task-hero small,
.psi-backlog-link-card__content > span,
.psi-case-tasks__head span {
    color: #7258a1;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.11em;
}
.psi-task-hero h4 {
    margin: 0.2rem 0;
    color: #273650;
}
.psi-task-hero p {
    margin: 0;
    color: #748198;
}
.psi-task-private-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.48rem 0.72rem;
    color: #665096;
    background: #f2edf8;
    border: 1px solid #e2d9ef;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
.psi-backlog-link-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 1.1rem;
    margin: 1.25rem;
    padding: 1.25rem;
    background: radial-gradient(
            circle at 100% 0%,
            rgba(94, 116, 204, 0.12),
            transparent 38%
        ),
        #fff;
    border: 1px solid #dfe5ef;
    border-radius: 20px;
    box-shadow: 0 16px 35px rgba(51, 65, 90, 0.07);
}
.psi-backlog-link-card__visual {
    display: grid;
    width: 64px;
    height: 64px;
    color: #fff;
    background: linear-gradient(145deg, #5f78d0, #7255a2);
    border-radius: 19px;
    box-shadow: 0 12px 24px rgba(94, 92, 175, 0.24);
    place-items: center;
    font-size: 1.75rem;
}
.psi-backlog-link-card h5 {
    margin: 0.28rem 0;
    color: #293852;
}
.psi-backlog-link-card p {
    max-width: 720px;
    margin: 0;
    color: #6f7c91;
}
.psi-backlog-link-card .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding-right: 1rem;
    padding-left: 1rem;
    border-radius: 14px;
    font-weight: 700;
    box-shadow: 0 12px 24px rgba(85, 110, 230, 0.2);
}
.psi-case-tasks {
    margin: 0 1.25rem 1.25rem;
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid #dfe6ee;
    border-radius: 20px;
}
.psi-case-tasks__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.05rem 1.2rem;
    border-bottom: 1px solid #e6ebf1;
}
.psi-case-tasks__head h5 {
    margin: 0.2rem 0 0;
    color: #2d3a51;
}
.psi-case-tasks__head > strong {
    display: grid;
    width: 38px;
    height: 38px;
    color: #624a8f;
    background: #f0ebf7;
    border-radius: 12px;
    place-items: center;
}
.psi-case-tasks .psi-empty i {
    display: block;
    margin-bottom: 0.5rem;
    color: #7056a0;
    font-size: 2rem;
}
.psi-case-tasks .psi-empty h6 {
    margin: 0 0 0.25rem;
    color: #344158;
}
.psi-case-tasks .psi-empty p {
    margin: 0;
}
.psi-case-task-list {
    padding: 0.25rem 1.2rem;
}
.psi-case-task-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.8rem;
    padding: 0.9rem 0;
    border-bottom: 1px solid #e8edf2;
}
.psi-case-task-row:last-child {
    border-bottom: 0;
}
.psi-case-task-row__icon {
    display: grid;
    width: 40px;
    height: 40px;
    color: #5d75c4;
    background: #edf1fb;
    border-radius: 12px;
    place-items: center;
    font-size: 1.15rem;
}
.psi-case-task-row strong,
.psi-case-task-row span {
    display: block;
}
.psi-case-task-row > div > span {
    margin-top: 0.2rem;
    color: #7b8798;
    font-size: 0.75rem;
}
@media (max-width: 768px) {
    .psi-task-hero {
        align-items: flex-start;
    }
    .psi-task-private-chip {
        display: none;
    }
    .psi-backlog-link-card {
        grid-template-columns: auto minmax(0, 1fr);
    }
    .psi-backlog-link-card .btn {
        grid-column: 1 / -1;
    }
}
.psi-alert-grid,
.psi-counts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.8rem;
}
.psi-alert-grid article,
.psi-counts article {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    border: 1px solid #e3e8ee;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 12px 34px rgba(51, 65, 85, 0.055);
}
.psi-alert-grid i {
    font-size: 1.4rem;
    color: #506f91;
}
.psi-alert-grid strong,
.psi-counts strong {
    font-size: 1.6rem;
    color: #253750;
}
.psi-alert-grid span,
.psi-counts span {
    font-size: 0.78rem;
    color: #758297;
}
.psi-workspace-body .psi-counts > article {
    align-items: center;
    flex-direction: row;
    gap: 0.6rem;
    min-height: 72px;
    padding: 0.75rem;
    border-radius: 12px;
    box-shadow: none;
}
.psi-workspace-body .psi-counts strong {
    font-size: 1.15rem;
}
@media (max-width: 767px) {
    .psi-section-head,
    .psi-workspace-head {
        align-items: flex-start;
        flex-direction: column;
    }
    .psi-export-actions {
        width: 100%;
    }
    .psi-action-btn {
        flex: 1 1 120px;
    }
    .psi-report-filters {
        grid-template-columns: 1fr;
    }
    .psi-counts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .psi-report-insights {
        grid-template-columns: 1fr;
    }
    :deep(.fc .fc-toolbar) {
        align-items: stretch;
        flex-direction: column;
    }
    :deep(.fc .fc-toolbar-chunk) {
        display: flex;
        justify-content: center;
    }
}
@media (max-width: 430px) {
    .psi-calendar-wrap,
    .psi-workspace-body {
        padding: 0.75rem;
    }
    .psi-counts {
        grid-template-columns: 1fr;
    }
}
</style>
