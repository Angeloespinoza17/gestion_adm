<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import Swal from "sweetalert2";
import {
    LIBRO_DIGITAL_API_BASE,
    libroDigitalApi,
    waitForLibroDigitalJob,
} from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
    contextParams,
    errorMessage,
    formatDateTime,
    hasCapability,
    payloadData,
    payloadItems,
    showError,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});
const loading = ref(false);
const generating = ref("");
const error = ref(null);
const catalog = ref([]);
const history = ref([]);
let controller = null;
const filters = reactive({
    report_type: "official_roster",
    period: "academic_year",
    from: "",
    to: "",
    teacher_staff_id: null,
    session_status: "",
    signature_status: "",
    normative_profile_id: null,
});
const scopeKey = computed(() => JSON.stringify(contextParams(props.context)));
const teachers = computed(
    () => props.catalogs.teachers || props.catalogs.staff || []
);
const profiles = computed(
    () =>
        props.catalogs.regulatory_profiles ||
        props.catalogs.normative_profiles ||
        []
);
const canExport = computed(() =>
    hasCapability(props.capabilities, "can_export_reports")
);
const builtInReports = [
    ["official_roster", "Nómina oficial", "Matrícula y vigencias del curso"],
    [
        "enrollment_movements",
        "Matrículas y retiros",
        "Altas, retiros y cambios históricos",
    ],
    [
        "session_attendance",
        "Asistencia por sesión",
        "Detalle por bloque pedagógico",
    ],
    ["daily_attendance", "Asistencia diaria", "Derivación diaria consolidada"],
    [
        "monthly_attendance",
        "Asistencia mensual",
        "Totales y cuadratura del mes",
    ],
    [
        "student_absences",
        "Inasistencia por estudiante",
        "Ausencias, atrasos y justificaciones",
    ],
    ["lesson_records", "Leccionario", "Objetivos, contenidos y actividades"],
    [
        "curriculum_coverage",
        "Cobertura curricular",
        "Avance por OA y asignatura",
    ],
    [
        "assessments",
        "Evaluaciones y calificaciones",
        "Instrumentos, resultados y cierres",
    ],
    ["coexistence", "Anotaciones", "Registros autorizados de convivencia"],
    ["pie", "Actividades PIE", "Trabajo colaborativo y apoyos"],
    [
        "early_withdrawals",
        "Salidas anticipadas",
        "Retiros, autorizaciones y retornos",
    ],
    [
        "prolonged_absences",
        "Ausencias prolongadas",
        "Casos, acciones y resultado",
    ],
    [
        "parvularia_book",
        "Libro Técnico Pedagógico",
        "Planificación y evaluación parvularia",
    ],
    [
        "amendment_history",
        "Historial de modificaciones",
        "Revisiones, correcciones y enmiendas",
    ],
    ["audit", "Auditoría", "Eventos e integridad"],
    ["closure_status", "Estado de cierres", "Sesiones, días y meses"],
].map(([code, name, description]) => ({
    code,
    name,
    description,
    formats: ["pdf", "csv", "json"],
}));
const reports = computed(() =>
    catalog.value.length ? catalog.value : builtInReports
);

const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        const [catalogResponse, historyResponse] = await Promise.all([
            libroDigitalApi.reportCatalog(
                contextParams(props.context),
                controller.signal
            ),
            libroDigitalApi
                .reportHistory(
                    { ...contextParams(props.context), per_page: 30 },
                    controller.signal
                )
                .catch(() => ({ data: [] })),
        ]);
        const catalogData = payloadData(catalogResponse);
        catalog.value = Array.isArray(catalogData)
            ? catalogData
            : catalogData.reports || payloadItems(catalogResponse);
        history.value = payloadItems(historyResponse);
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loading.value = false;
    }
};
watch([scopeKey, () => props.refreshToken], load, { immediate: true });
onBeforeUnmount(() => controller?.abort());
const selectedReport = computed(
    () =>
        reports.value.find(
            (item) => (item.code || item.value) === filters.report_type
        ) || reports.value[0]
);
const downloadCompleted = async (job, format) => {
    if (format === "json" && (job.payload || job.result) && !job.download_url) {
        libroDigitalApi.saveJson(
            job.payload || job.result,
            job.filename || `libro_digital_${filters.report_type}.json`
        );
        return;
    }
    const url =
        job.download_url ||
        `${LIBRO_DIGITAL_API_BASE}/reports/${job.id}/download`;
    await libroDigitalApi.download(
        url,
        job.filename || `libro_digital_${filters.report_type}.${format}`
    );
};
const generate = async (format) => {
    generating.value = format;
    let progressOpen = false;
    try {
        const response = payloadData(
            await libroDigitalApi.generateReport({
                ...contextParams(props.context),
                ...filters,
                format,
            })
        );
        Swal.fire({
            title: "Generando informe",
            text: "La descarga comenzará cuando el archivo esté listo.",
            allowEscapeKey: false,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });
        progressOpen = true;
        const completed = [
            "pending",
            "queued",
            "processing",
            "generating",
        ].includes(response.status)
            ? await waitForLibroDigitalJob(response, (job) =>
                  libroDigitalApi.report(job.id)
              )
            : response;
        Swal.close();
        progressOpen = false;
        await downloadCompleted(completed, format);
        await load();
        await Swal.fire({
            icon: "success",
            title: "Descarga iniciada",
            text: "El informe quedó registrado en el historial.",
            timer: 1500,
            showConfirmButton: false,
        });
    } catch (requestError) {
        if (progressOpen) Swal.close();
        if (requestError.code === "LCD_JOB_TIMEOUT")
            await Swal.fire({
                icon: "info",
                title: "El informe continúa",
                text: "Podrás descargarlo desde el historial cuando finalice.",
            });
        else await showError(requestError, "No se pudo generar el informe");
    } finally {
        generating.value = "";
    }
};
const redownload = (item) =>
    libroDigitalApi
        .download(
            item.download_url ||
                `${LIBRO_DIGITAL_API_BASE}/reports/${item.id}/download`,
            item.filename || `informe_${item.id}.${item.format || "pdf"}`
        )
        .catch(showError);
</script>

<template>
    <section
        class="ld-section ld-reports"
        aria-labelledby="lcd-reports-title"
        :aria-busy="loading"
    >
        <header class="ld-section-head">
            <div class="ld-section-head__identity">
                <span class="ld-section-head__icon" aria-hidden="true"
                    ><i class="bx bx-file"></i
                ></span>
                <div>
                    <span class="ld-eyebrow">Centro documental</span>
                    <h2 id="lcd-reports-title">Reportes e informes</h2>
                    <p>
                        Generación trazable en PDF, CSV y JSON. Los informes
                        operacionales no sustituyen el paquete EDE.
                    </p>
                </div>
            </div>
            <span class="ld-section-head__meta"
                ><i class="bx bx-lock-alt" aria-hidden="true"></i>Archivos
                privados</span
            >
        </header>

        <LibroDigitalStatePanel
            v-if="loading && !catalog.length && !history.length"
            state="loading"
            title="Cargando reportes"
            message="Consultando tipos y archivos generados."
        />
        <template v-else>
            <BAlert
                v-if="error"
                show
                variant="warning"
                class="ld-inline-alert mb-0"
                role="status"
                ><i class="bx bx-error-circle" aria-hidden="true"></i
                ><span
                    >{{ errorMessage(error) }} Se muestra el catálogo disponible
                    localmente.</span
                ><BButton
                    type="button"
                    size="sm"
                    variant="link"
                    :disabled="loading"
                    @click="load"
                    >Reintentar</BButton
                ></BAlert
            >

            <div class="ld-reports__layout">
                <BCard
                    class="border-0 ld-surface ld-report-builder"
                    body-class="p-0"
                >
                    <header class="ld-builder-head">
                        <div class="ld-builder-head__identity">
                            <span
                                class="ld-builder-head__icon"
                                aria-hidden="true"
                                ><i class="bx bx-file-find"></i
                            ></span>
                            <div>
                                <span>Configurar informe</span>
                                <h3>{{ selectedReport?.name || "Informe" }}</h3>
                                <p>{{ selectedReport?.description }}</p>
                            </div>
                        </div>
                        <span class="ld-step-badge">1 · Configuración</span>
                    </header>
                    <form class="ld-report-form" @submit.prevent>
                        <fieldset>
                            <legend>Documento</legend>
                            <div class="ld-report-form__grid">
                                <div class="ld-field ld-field--full">
                                    <label for="lcd-report-type"
                                        >Tipo de informe</label
                                    ><BFormSelect
                                        id="lcd-report-type"
                                        v-model="filters.report_type"
                                        ><option
                                            v-for="report in reports"
                                            :key="report.code || report.value"
                                            :value="report.code || report.value"
                                        >
                                            {{ report.name || report.label }}
                                        </option></BFormSelect
                                    >
                                </div>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Periodo informado</legend>
                            <div class="ld-report-form__grid">
                                <div class="ld-field">
                                    <label for="lcd-report-period"
                                        >Periodo</label
                                    ><BFormSelect
                                        id="lcd-report-period"
                                        v-model="filters.period"
                                        ><option value="academic_year">
                                            Año académico
                                        </option>
                                        <option value="current_month">
                                            Mes actual
                                        </option>
                                        <option value="first_semester">
                                            Primer semestre
                                        </option>
                                        <option value="second_semester">
                                            Segundo semestre
                                        </option>
                                        <option value="custom">
                                            Personalizado
                                        </option></BFormSelect
                                    >
                                </div>
                                <div class="ld-field">
                                    <label for="lcd-report-from">Desde</label
                                    ><BFormInput
                                        id="lcd-report-from"
                                        v-model="filters.from"
                                        type="date"
                                        :disabled="filters.period !== 'custom'"
                                    />
                                </div>
                                <div class="ld-field">
                                    <label for="lcd-report-to">Hasta</label
                                    ><BFormInput
                                        id="lcd-report-to"
                                        v-model="filters.to"
                                        type="date"
                                        :disabled="filters.period !== 'custom'"
                                    />
                                </div>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Filtros complementarios</legend>
                            <div class="ld-report-form__grid">
                                <div class="ld-field">
                                    <label for="lcd-report-teacher"
                                        >Docente</label
                                    ><BFormSelect
                                        id="lcd-report-teacher"
                                        v-model="filters.teacher_staff_id"
                                        ><option :value="null">
                                            Todos los docentes
                                        </option>
                                        <option
                                            v-for="teacher in teachers"
                                            :key="teacher.id"
                                            :value="teacher.id"
                                        >
                                            {{
                                                teacher.full_name ||
                                                teacher.name
                                            }}
                                        </option></BFormSelect
                                    >
                                </div>
                                <div class="ld-field">
                                    <label for="lcd-report-signature"
                                        >Estado de firma</label
                                    ><BFormSelect
                                        id="lcd-report-signature"
                                        v-model="filters.signature_status"
                                        ><option value="">
                                            Todos los estados
                                        </option>
                                        <option value="signed">Firmada</option>
                                        <option value="pending">
                                            Pendiente
                                        </option>
                                        <option value="failed">
                                            Fallida
                                        </option></BFormSelect
                                    >
                                </div>
                                <div class="ld-field">
                                    <label for="lcd-report-profile"
                                        >Perfil normativo</label
                                    ><BFormSelect
                                        id="lcd-report-profile"
                                        v-model="filters.normative_profile_id"
                                        ><option :value="null">
                                            Todos los perfiles
                                        </option>
                                        <option
                                            v-for="profile in profiles"
                                            :key="profile.id"
                                            :value="profile.id"
                                        >
                                            {{ profile.name }} ·
                                            {{ profile.version }}
                                        </option></BFormSelect
                                    >
                                </div>
                            </div>
                        </fieldset>
                    </form>
                    <div class="ld-integrity-note">
                        <span class="ld-integrity-note__icon" aria-hidden="true"
                            ><i class="bx bx-shield-quarter"></i
                        ></span>
                        <div>
                            <strong>Integridad incorporada</strong
                            ><span
                                >El PDF oficial se genera en servidor con
                                establecimiento, RBD, filtros, usuario,
                                paginación, identificador, hash y marca de
                                borrador cuando corresponde.</span
                            >
                        </div>
                    </div>
                    <BAlert
                        v-if="!canExport"
                        show
                        variant="info"
                        class="ld-permission-notice mb-0"
                        ><i class="bx bx-info-circle" aria-hidden="true"></i
                        ><span
                            >Puedes consultar catálogo e historial, pero no
                            generar ni descargar informes.</span
                        ></BAlert
                    >
                    <footer
                        class="ld-export-actions"
                        aria-label="Formatos de exportación"
                    >
                        <div>
                            <span class="ld-eyebrow">Formato de salida</span>
                            <p>Selecciona el archivo que necesitas generar.</p>
                        </div>
                        <div class="ld-export-actions__buttons">
                            <BButton
                                type="button"
                                variant="outline-danger"
                                :disabled="!!generating || !canExport"
                                :aria-busy="generating === 'pdf'"
                                @click="generate('pdf')"
                                ><span
                                    class="ld-format-icon ld-format-icon--pdf"
                                    ><span
                                        v-if="generating === 'pdf'"
                                        class="spinner-border spinner-border-sm"
                                        aria-hidden="true"
                                    ></span
                                    ><i
                                        v-else
                                        class="bx bxs-file-pdf"
                                        aria-hidden="true"
                                    ></i></span
                                ><span
                                    ><strong>PDF</strong
                                    ><small>Documento oficial</small></span
                                ></BButton
                            >
                            <BButton
                                type="button"
                                variant="outline-success"
                                :disabled="!!generating || !canExport"
                                :aria-busy="generating === 'csv'"
                                @click="generate('csv')"
                                ><span
                                    class="ld-format-icon ld-format-icon--csv"
                                    ><span
                                        v-if="generating === 'csv'"
                                        class="spinner-border spinner-border-sm"
                                        aria-hidden="true"
                                    ></span
                                    ><i
                                        v-else
                                        class="bx bx-table"
                                        aria-hidden="true"
                                    ></i></span
                                ><span
                                    ><strong>CSV</strong
                                    ><small>Datos tabulares</small></span
                                ></BButton
                            >
                            <BButton
                                type="button"
                                variant="outline-primary"
                                :disabled="!!generating || !canExport"
                                :aria-busy="generating === 'json'"
                                @click="generate('json')"
                                ><span
                                    class="ld-format-icon ld-format-icon--json"
                                    ><span
                                        v-if="generating === 'json'"
                                        class="spinner-border spinner-border-sm"
                                        aria-hidden="true"
                                    ></span
                                    ><i
                                        v-else
                                        class="bx bx-code-curly"
                                        aria-hidden="true"
                                    ></i></span
                                ><span
                                    ><strong>JSON</strong
                                    ><small>Interoperabilidad</small></span
                                ></BButton
                            >
                        </div>
                    </footer>
                </BCard>

                <BCard
                    class="border-0 ld-surface ld-report-catalog"
                    body-class="p-0"
                >
                    <header class="ld-panel-head">
                        <div class="ld-panel-head__title">
                            <span class="ld-panel-head__icon" aria-hidden="true"
                                ><i class="bx bx-grid-alt"></i
                            ></span>
                            <div>
                                <h3>Catálogo disponible</h3>
                                <p>Selecciona un informe para configurarlo</p>
                            </div>
                        </div>
                        <span class="ld-count-badge">{{ reports.length }}</span>
                    </header>
                    <div
                        class="ld-report-catalog__list"
                        role="listbox"
                        aria-label="Tipos de informe"
                    >
                        <button
                            v-for="report in reports"
                            :key="report.code || report.value"
                            type="button"
                            role="option"
                            :aria-selected="
                                filters.report_type ===
                                (report.code || report.value)
                            "
                            :class="{
                                active:
                                    filters.report_type ===
                                    (report.code || report.value),
                            }"
                            @click="
                                filters.report_type =
                                    report.code || report.value
                            "
                        >
                            <span
                                class="ld-report-catalog__icon"
                                aria-hidden="true"
                                ><i class="bx bx-file"></i></span
                            ><span
                                ><strong>{{
                                    report.name || report.label
                                }}</strong
                                ><small>{{ report.description }}</small></span
                            ><i
                                class="bx bx-chevron-right"
                                aria-hidden="true"
                            ></i>
                        </button>
                    </div>
                </BCard>
            </div>

            <BCard
                class="border-0 ld-surface ld-report-history"
                body-class="p-0"
            >
                <header class="ld-panel-head">
                    <div class="ld-panel-head__title">
                        <span class="ld-panel-head__icon" aria-hidden="true"
                            ><i class="bx bx-history"></i
                        ></span>
                        <div>
                            <h3>Historial de generación</h3>
                            <p>
                                Archivos privados disponibles según retención y
                                permisos
                            </p>
                        </div>
                    </div>
                    <div class="ld-panel-head__actions">
                        <span class="ld-count-badge">{{ history.length }}</span
                        ><BButton
                            type="button"
                            size="sm"
                            variant="outline-secondary"
                            :disabled="loading"
                            aria-label="Actualizar historial"
                            @click="load"
                            ><span
                                v-if="loading"
                                class="spinner-border spinner-border-sm"
                                aria-hidden="true"
                            ></span
                            ><i
                                v-else
                                class="bx bx-reset"
                                aria-hidden="true"
                            ></i
                        ></BButton>
                    </div>
                </header>
                <div
                    v-if="history.length"
                    class="table-responsive ld-table-wrap"
                >
                    <table
                        class="table table-hover align-middle mb-0 ld-data-table"
                    >
                        <caption class="visually-hidden">
                            Historial de informes generados
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Informe</th>
                                <th scope="col">Formato</th>
                                <th scope="col">Generado</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Identificador</th>
                                <th scope="col" class="text-end">Descarga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in history" :key="item.id">
                                <td>
                                    <div class="ld-history-record">
                                        <span
                                            class="ld-history-record__icon"
                                            aria-hidden="true"
                                            ><i class="bx bx-file-blank"></i
                                        ></span>
                                        <div>
                                            <strong>{{
                                                item.report_name ||
                                                item.report_type
                                            }}</strong
                                            ><small>{{ item.filename }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="ld-format-badge">{{
                                        String(item.format || "").toUpperCase()
                                    }}</span>
                                </td>
                                <td>
                                    {{
                                        formatDateTime(
                                            item.completed_at || item.created_at
                                        )
                                    }}
                                </td>
                                <td>
                                    <LibroDigitalStatusBadge
                                        :status="item.status"
                                    />
                                </td>
                                <td>
                                    <code>{{
                                        item.report_identifier ||
                                        item.uuid ||
                                        item.id
                                    }}</code>
                                </td>
                                <td class="text-end">
                                    <BButton
                                        v-if="
                                            canExport &&
                                            item.status === 'completed'
                                        "
                                        type="button"
                                        size="sm"
                                        variant="outline-primary"
                                        :aria-label="`Descargar ${
                                            item.report_name || item.report_type
                                        }`"
                                        @click="redownload(item)"
                                        ><i
                                            class="bx bx-download"
                                            aria-hidden="true"
                                        ></i
                                        ><span>Descargar</span></BButton
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <LibroDigitalStatePanel
                    v-else
                    compact
                    title="Sin informes generados"
                    message="Los archivos aparecerán aquí al completar su generación."
                />
            </BCard>
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
.ld-section-head__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.42rem 0.6rem;
    border: 1px solid #dfe5ef;
    border-radius: 999px;
    background: #fff;
    color: #697586;
    font-size: 0.72rem;
    font-weight: 700;
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
.ld-surface {
    overflow: hidden;
    border: 1px solid var(--ld-line) !important;
    border-radius: 14px;
    box-shadow: 0 8px 26px rgba(26, 39, 66, 0.05);
}
.ld-reports__layout {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(310px, 0.65fr);
    gap: 1rem;
    align-items: start;
}
.ld-builder-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--ld-line);
    background: linear-gradient(145deg, #fbfcff, #f4f7fd);
}
.ld-builder-head__identity {
    display: flex;
    align-items: center;
    gap: 0.72rem;
    min-width: 0;
}
.ld-builder-head__icon {
    display: grid;
    flex: 0 0 42px;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #e7edf9;
    color: var(--ld-primary);
    font-size: 1.2rem;
}
.ld-builder-head__identity > div > span {
    color: var(--ld-primary);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.ld-builder-head h3 {
    margin: 0.12rem 0;
    color: var(--ld-ink);
    font-size: 1rem;
}
.ld-builder-head p {
    margin: 0;
    color: var(--ld-muted);
    font-size: 0.75rem;
}
.ld-step-badge {
    flex: 0 0 auto;
    padding: 0.28rem 0.52rem;
    border: 1px solid #d8e0f0;
    border-radius: 999px;
    background: #fff;
    color: #59698c;
    font-size: 0.7rem;
    font-weight: 700;
}
.ld-report-form {
    display: grid;
    gap: 1rem;
    padding: 1rem 1.1rem;
}
.ld-report-form fieldset {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}
.ld-report-form legend {
    float: none;
    width: auto;
    margin: 0 0 0.58rem;
    color: #344054;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.045em;
    text-transform: uppercase;
}
.ld-report-form__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}
.ld-field--full {
    grid-column: 1/-1;
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
.ld-integrity-note {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin: 0 1.1rem 1rem;
    padding: 0.78rem 0.85rem;
    border: 1px solid #dbe3f1;
    border-radius: 11px;
    background: #f6f8fc;
}
.ld-integrity-note__icon {
    display: grid;
    flex: 0 0 36px;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #e5ebf8;
    color: var(--ld-primary);
    font-size: 1.05rem;
}
.ld-integrity-note strong,
.ld-integrity-note div > span {
    display: block;
}
.ld-integrity-note strong {
    color: #344054;
    font-size: 0.78rem;
}
.ld-integrity-note div > span {
    margin-top: 0.12rem;
    color: #667085;
    font-size: 0.72rem;
    line-height: 1.45;
}
.ld-permission-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 1.1rem 1rem !important;
    padding: 0.65rem 0.75rem;
    font-size: 0.75rem;
}
.ld-export-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-top: 1px solid var(--ld-line);
    background: #fbfcfe;
}
.ld-export-actions > div:first-child p {
    margin: 0.12rem 0 0;
    color: var(--ld-muted);
    font-size: 0.72rem;
}
.ld-export-actions__buttons {
    display: flex;
    gap: 0.5rem;
}
.ld-export-actions__buttons .btn {
    display: flex;
    min-width: 118px;
    min-height: 54px;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.65rem;
    text-align: left;
}
.ld-export-actions__buttons .btn > span:last-child strong,
.ld-export-actions__buttons .btn > span:last-child small {
    display: block;
}
.ld-export-actions__buttons strong {
    font-size: 0.78rem;
}
.ld-export-actions__buttons small {
    font-size: 0.68rem;
    opacity: 0.72;
}
.ld-format-icon {
    display: grid;
    flex: 0 0 30px;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(64, 81, 137, 0.09);
    font-size: 1rem;
}
.ld-format-icon--pdf {
    background: rgba(200, 79, 90, 0.1);
}
.ld-format-icon--csv {
    background: rgba(43, 138, 102, 0.1);
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
.ld-panel-head h3 {
    margin: 0;
    color: var(--ld-ink);
    font-size: 0.9rem;
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
.ld-report-catalog__list {
    max-height: 621px;
    overflow: auto;
    scrollbar-width: thin;
}
.ld-report-catalog__list button {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) 18px;
    align-items: center;
    width: 100%;
    gap: 0.6rem;
    padding: 0.7rem 0.85rem;
    border: 0;
    border-bottom: 1px solid #edf0f4;
    background: transparent;
    color: var(--ld-primary);
    text-align: left;
    transition: background 0.15s ease;
}
.ld-report-catalog__list button:hover {
    background: #f8f9fc;
}
.ld-report-catalog__list button.active {
    background: #edf1fa;
    box-shadow: inset 3px 0 var(--ld-primary);
}
.ld-report-catalog__icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: #f0f3f8;
    color: #667694;
    font-size: 1rem;
}
.ld-report-catalog__list button.active .ld-report-catalog__icon {
    background: #dfe6f7;
    color: var(--ld-primary);
}
.ld-report-catalog__list strong,
.ld-report-catalog__list small {
    display: block;
}
.ld-report-catalog__list strong {
    color: #344054;
    font-size: 0.76rem;
}
.ld-report-catalog__list small {
    margin-top: 0.12rem;
    color: #7b8797;
    font-size: 0.7rem;
    line-height: 1.35;
}
.ld-report-catalog__list button > i {
    color: #a0a8b5;
}
.ld-panel-head__actions {
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.ld-panel-head__actions .btn {
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    padding: 0;
}
.ld-table-wrap {
    scrollbar-width: thin;
}
.ld-data-table {
    min-width: 920px;
    font-size: 0.78rem;
}
.ld-data-table th {
    padding: 0.62rem 1rem;
    border-bottom-color: var(--ld-line);
    background: #f7f9fc;
    color: #697586;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.ld-data-table td {
    padding: 0.76rem 1rem;
    border-color: #edf0f4;
    color: #475467;
}
.ld-history-record {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 190px;
}
.ld-history-record__icon {
    display: grid;
    flex: 0 0 32px;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #eef2fb;
    color: var(--ld-primary);
}
.ld-history-record strong,
.ld-history-record small {
    display: block;
}
.ld-history-record strong {
    color: #344054;
    font-size: 0.77rem;
}
.ld-history-record small {
    margin-top: 0.12rem;
    color: #7d8998;
    font-size: 0.7rem;
}
.ld-format-badge {
    display: inline-flex;
    padding: 0.2rem 0.42rem;
    border-radius: 6px;
    background: #f0f3f8;
    color: #586579;
    font-size: 0.7rem;
    font-weight: 800;
}
.ld-data-table code {
    font-size: 0.72rem;
}
.ld-data-table .btn {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    gap: 0.32rem;
}
@media (max-width: 1120px) {
    .ld-reports__layout {
        grid-template-columns: 1fr;
    }
    .ld-report-catalog__list {
        max-height: 380px;
    }
}
@media (max-width: 800px) {
    .ld-report-form__grid {
        grid-template-columns: 1fr;
    }
    .ld-field--full {
        grid-column: auto;
    }
    .ld-export-actions {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-export-actions__buttons {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
    }
    .ld-export-actions__buttons .btn {
        min-width: 0;
    }
    .ld-step-badge {
        display: none;
    }
}
@media (max-width: 700px) {
    .ld-section-head {
        align-items: flex-start;
    }
    .ld-section-head__icon {
        display: none;
    }
    .ld-section-head__meta {
        display: none;
    }
    .ld-builder-head {
        align-items: flex-start;
    }
    .ld-export-actions__buttons {
        grid-template-columns: 1fr;
    }
    .ld-export-actions__buttons .btn {
        width: 100%;
    }
    .ld-panel-head {
        padding: 0.75rem 0.8rem;
    }
    .ld-data-table .btn span {
        display: none;
    }
}
</style>
