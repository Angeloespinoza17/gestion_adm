<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import { getPdfMake } from "../../utils/pdfmake";
import PsychologyBadge from "./PsychologyBadge.vue";
import PsychologyModal from "./PsychologyModal.vue";
import { usePsychology } from "../../composables/usePsychology";
const props = defineProps({
    mode: { type: String, required: true },
    catalogs: { type: Object, required: true },
});
const api = usePsychology();
const data = ref(null);
const configModal = ref("");
const filters = reactive({
    from: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
    to: new Date().toISOString().slice(0, 10),
    nominal: false,
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
const load = async () => {
    if (props.mode === "reports")
        data.value = await api.get("/api/psychology/reports", filters);
    else if (props.mode === "audit")
        data.value = await api.get("/api/psychology/audit");
    else if (["tasks", "alerts"].includes(props.mode))
        data.value = await api.get("/api/psychology/dashboard");
};
watch(() => props.mode, load);
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, bootstrap5Plugin],
    themeSystem: "bootstrap5",
    initialView: "dayGridMonth",
    locale: "es",
    headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
    },
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
    height: "auto",
}));
const exportPdf = async () => {
    const report = data.value;
    if (!report) return;
    const pdf = await getPdfMake();
    const rows = [
        ["Indicador", "Cantidad"],
        ["Derivaciones", report.counts.referrals],
        ["Casos", report.counts.cases],
        ["Estudiantes únicos", report.counts.unique_students],
        ["Actividades", report.counts.activities],
    ];
    pdf.createPdf({
        pageMargins: [40, 50, 40, 45],
        header: {
            text: "Colegio · Reporte de Psicología Escolar",
            margin: [40, 20],
            fontSize: 9,
            color: "#66758a",
        },
        footer: (page, pages) => ({
            text: `Confidencial · Página ${page} de ${pages}`,
            alignment: "center",
            fontSize: 8,
            color: "#777",
        }),
        content: [
            { text: "Reporte agregado de Psicología", style: "title" },
            {
                text: `Período: ${report.period.from} al ${
                    report.period.to
                }\nGenerado: ${new Date(report.generated_at).toLocaleString(
                    "es-CL"
                )}`,
                margin: [0, 4, 0, 16],
                color: "#66758a",
            },
            {
                table: { widths: ["*", 90], body: rows },
                layout: "lightHorizontalLines",
            },
            { text: "Distribución por motivo", style: "subtitle" },
            {
                table: {
                    widths: ["*", 90],
                    body: [
                        ["Grupo", "Cantidad"],
                        ...(report.by_reason || []).map((x) => [
                            x.label,
                            x.total,
                        ]),
                    ],
                },
                layout: "lightHorizontalLines",
            },
            {
                text: "Documento agregado y anonimizado. Los grupos pequeños se protegen según el umbral institucional.",
                fontSize: 8,
                color: "#777",
                margin: [0, 18, 0, 0],
            },
        ],
        styles: {
            title: { fontSize: 18, bold: true, color: "#23354d" },
            subtitle: { fontSize: 12, bold: true, margin: [0, 18, 0, 7] },
        },
    }).download(`reporte-psicologia-${filters.from}-${filters.to}.pdf`);
};
const exportExcel = () => {
    if (!data.value) return;
    const rows = [
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
    a.download = `reporte-psicologia-${filters.from}-${filters.to}.xls`;
    a.click();
    URL.revokeObjectURL(url);
};
const exportCsv = async () => {
    const response = await axios.get("/api/psychology/reports/export.csv", {
        params: { from: filters.from, to: filters.to },
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
    await props.catalogs.load?.();
    configModal.value = "";
};
const saveCatalog = async () => {
    await api.post("/api/psychology/configuration/catalogs", catalogForm);
    Object.assign(catalogForm, { slug: "", name: "", description: "" });
    configModal.value = "";
};
onMounted(load);
</script>
<template>
    <div>
        <div v-if="api.error.value" class="alert alert-danger">
            {{ api.error.value }}
        </div>
        <div v-if="mode === 'calendar'" class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-3">
                    <h4>Agenda de Psicología</h4>
                    <p class="text-muted">
                        Los eventos usan etiquetas seguras y nunca exponen el
                        motivo sensible.
                    </p>
                </div>
                <FullCalendar :options="calendarOptions" />
            </div>
        </div>
        <div v-else-if="mode === 'tasks'" class="card border-0 shadow-sm">
            <div class="card-body">
                <h4>Tareas y vencimientos</h4>
                <div v-if="!data?.recent?.tasks?.length" class="psi-empty">
                    No hay tareas pendientes.
                </div>
                <div
                    v-for="item in data?.recent?.tasks || []"
                    :key="item.id"
                    class="psi-row"
                >
                    <div>
                        <strong>{{ item.title }}</strong
                        ><span
                            >{{ item.case?.code }} · vence
                            {{ item.due_at || "sin fecha" }}</span
                        >
                    </div>
                    <PsychologyBadge :value="item.status" />
                </div>
            </div>
        </div>
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
        <div v-else-if="mode === 'reports'" class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h4>Reportabilidad</h4>
                        <p class="text-muted">
                            Derivaciones, casos, estudiantes únicos y
                            actividades se contabilizan por separado.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <button
                            class="btn btn-outline-danger"
                            @click="exportPdf"
                        >
                            <i class="bx bxs-file-pdf me-1"></i>PDF</button
                        ><button
                            class="btn btn-outline-success"
                            @click="exportExcel"
                        >
                            <i class="bx bx-spreadsheet me-1"></i>Excel</button
                        ><button
                            v-if="catalogs.capabilities.reports_nominal"
                            class="btn btn-outline-secondary"
                            @click="exportCsv"
                        >
                            CSV nominal
                        </button>
                    </div>
                </div>
                <div class="row g-2 my-3">
                    <div class="col-md-3">
                        <label class="form-label">Desde</label
                        ><input
                            v-model="filters.from"
                            type="date"
                            class="form-control"
                        />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label
                        ><input
                            v-model="filters.to"
                            type="date"
                            class="form-control"
                        />
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary" @click="load">
                            Aplicar filtros
                        </button>
                    </div>
                </div>
                <div class="psi-counts">
                    <article
                        v-for="(value, key) in data?.counts || {}"
                        :key="key"
                    >
                        <strong>{{ value }}</strong
                        ><span>{{
                            {
                                referrals: "Derivaciones",
                                cases: "Casos",
                                unique_students: "Estudiantes únicos",
                                activities: "Actividades",
                            }[key]
                        }}</span>
                    </article>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table">
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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
                                        <strong>{{ key }}</strong>
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
                                    <td>{{ item.type }}</td>
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
                        <label class="form-label">{{ key }}</label
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
                                    {{
                                        new Date(
                                            item.occurred_at
                                        ).toLocaleString("es-CL")
                                    }}
                                </td>
                                <td>{{ item.user_name || "Sistema" }}</td>
                                <td>{{ item.action }}</td>
                                <td>
                                    {{ item.auditable_type.split("\\").pop() }}
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
@media (max-width: 767px) {
    .psi-section-head {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
