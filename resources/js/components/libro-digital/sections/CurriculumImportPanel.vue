<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
    errorMessage,
    formatDateTime,
    payloadData,
    payloadItems,
    showError,
    showSuccess,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});
const emit = defineEmits(["activated"]);

const loading = ref(false);
const processing = ref(false);
const downloading = ref(false);
const error = ref(null);
const items = ref([]);
const selectedFile = ref(null);
const fileInput = ref(null);
const evidenceRows = ref([
    { sourceKey: "", file: null, sha256: "", hashing: false, inputKey: 1 },
]);
const note = ref("");
let controller = null;
let evidenceInputKey = 1;

const canView = computed(
    () =>
        props.capabilities.can_view_curriculum_imports ||
        props.capabilities.can_manage_curriculum_imports ||
        props.capabilities.can_approve_curriculum_imports ||
        props.capabilities.can_activate_curriculum_imports ||
        props.capabilities.is_super_admin
);
const canManage = computed(
    () =>
        props.capabilities.can_manage_curriculum_imports ||
        props.capabilities.is_super_admin
);
const canApprove = computed(
    () =>
        props.capabilities.can_approve_curriculum_imports ||
        props.capabilities.is_super_admin
);
const canActivate = computed(
    () =>
        props.capabilities.can_activate_curriculum_imports ||
        props.capabilities.is_super_admin
);
const contextReady = computed(
    () =>
        Boolean(props.context.school_id) &&
        Boolean(props.context.academic_year_id)
);

const load = async () => {
    if (!canView.value || !props.context.school_id) return;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        items.value = payloadItems(
            await libroDigitalApi.curriculumImports(
                {
                    school_id: props.context.school_id,
                    academic_year_id: props.context.academic_year_id,
                    per_page: 25,
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

watch(
    [
        () => props.context.school_id,
        () => props.context.academic_year_id,
        () => props.refreshToken,
        canView,
    ],
    load,
    { immediate: true }
);
onBeforeUnmount(() => controller?.abort());

const chooseFile = (event) => {
    selectedFile.value = event.target.files?.[0] || null;
};

const addEvidenceRow = () => {
    evidenceInputKey += 1;
    evidenceRows.value.push({
        sourceKey: "",
        file: null,
        sha256: "",
        hashing: false,
        inputKey: evidenceInputKey,
    });
};

const removeEvidenceRow = (index) => {
    evidenceRows.value.splice(index, 1);
    if (!evidenceRows.value.length) addEvidenceRow();
};

const chooseEvidenceFile = async (index, event) => {
    const file = event.target.files?.[0] || null;
    const row = evidenceRows.value[index];
    row.file = file;
    row.sha256 = "";
    if (!file) return;
    if (file.size > 20 * 1024 * 1024) {
        row.file = null;
        event.target.value = "";
        await showError(
            new Error("La evidencia supera el máximo de 20 MiB."),
            "Archivo demasiado grande"
        );
        return;
    }
    if (!globalThis.crypto?.subtle) return;
    row.hashing = true;
    try {
        const digest = await globalThis.crypto.subtle.digest(
            "SHA-256",
            await file.arrayBuffer()
        );
        row.sha256 = Array.from(new Uint8Array(digest), (byte) =>
            byte.toString(16).padStart(2, "0")
        ).join("");
    } finally {
        row.hashing = false;
    }
};

const resetEvidenceRows = () => {
    evidenceInputKey += 1;
    evidenceRows.value = [
        {
            sourceKey: "",
            file: null,
            sha256: "",
            hashing: false,
            inputKey: evidenceInputKey,
        },
    ];
};

const appendEvidenceFiles = (data) => {
    const usedKeys = new Set();
    for (const row of evidenceRows.value) {
        const sourceKey = String(row.sourceKey || "")
            .trim()
            .toUpperCase();
        if (!sourceKey && !row.file) continue;
        if (!sourceKey || !row.file) {
            throw new Error(
                "Cada evidencia debe indicar source_key y seleccionar su archivo oficial."
            );
        }
        if (!/^[A-Z0-9._-]{1,100}$/.test(sourceKey)) {
            throw new Error(
                `La clave ${
                    sourceKey || "indicada"
                } solo puede usar letras, números, punto, guion y guion bajo.`
            );
        }
        if (usedKeys.has(sourceKey)) {
            throw new Error(`La evidencia ${sourceKey} está repetida.`);
        }
        usedKeys.add(sourceKey);
        data.append(`evidence_files[${sourceKey}]`, row.file);
    }
};

const resetForm = () => {
    selectedFile.value = null;
    note.value = "";
    resetEvidenceRows();
    if (fileInput.value) fileInput.value.value = "";
};

const downloadTemplate = async () => {
    downloading.value = true;
    try {
        await libroDigitalApi.downloadCurriculumImportTemplate();
    } catch (requestError) {
        await showError(requestError, "No se pudo descargar la plantilla");
    } finally {
        downloading.value = false;
    }
};

const validateFile = async () => {
    if (!selectedFile.value || !contextReady.value) return;
    processing.value = true;
    try {
        const data = new FormData();
        data.append("file", selectedFile.value);
        data.append("school_id", String(props.context.school_id));
        data.append("academic_year_id", String(props.context.academic_year_id));
        appendEvidenceFiles(data);
        const response = await libroDigitalApi.validateCurriculumImport(data);
        const record = payloadData(response, {});
        resetForm();
        await load();
        if (record.status === "invalid") {
            await showError(
                {
                    message:
                        firstValidationError(record) ||
                        "El archivo quedó registrado, pero contiene errores que debes corregir en el XLSX.",
                },
                "El archivo necesita correcciones"
            );
        } else {
            await showSuccess(
                "Archivo recibido",
                "El lote quedó validado y pendiente de revisión; aún no modifica el currículo activo."
            );
        }
    } catch (requestError) {
        await showError(
            requestError,
            "No se pudo validar el archivo curricular"
        );
    } finally {
        processing.value = false;
    }
};

const action = async (record, type) => {
    processing.value = true;
    try {
        if (type === "approve")
            await libroDigitalApi.approveCurriculumImport(record, {
                note: note.value || "Revisión curricular aprobada.",
            });
        else {
            const data = new FormData();
            data.append("note", note.value || "Versión curricular activada.");
            appendEvidenceFiles(data);
            await libroDigitalApi.activateCurriculumImport(record, data);
            resetEvidenceRows();
        }
        note.value = "";
        await load();
        if (type === "activate") emit("activated");
        await showSuccess(
            type === "approve" ? "Lote aprobado" : "Currículo activado"
        );
    } catch (requestError) {
        await showError(
            requestError,
            type === "approve"
                ? "No se pudo aprobar el lote"
                : "No se pudo activar el currículo"
        );
    } finally {
        processing.value = false;
    }
};

const rowCount = (record, key) =>
    [
        key,
        key === "links" ? "subject_links" : null,
        key === "objectives" ? "objective_rows" : null,
    ]
        .filter(Boolean)
        .map(
            (candidate) =>
                record?.summary?.[candidate] ??
                record?.counts?.[candidate] ??
                record?.[`${candidate}_count`]
        )
        .find((value) => value !== null && value !== undefined) ?? 0;

const evidenceCount = (record) =>
    record?.counts?.sources ??
    record?.counts?.evidences ??
    record?.integrity?.evidence_count ??
    record?.evidences?.length ??
    0;

const missingEvidenceKeys = (record) =>
    Array.isArray(record?.source_evidence?.missing_source_keys)
        ? record.source_evidence.missing_source_keys
        : [];

const catalogName = (record) =>
    record?.catalog_name ||
    record?.catalog?.name ||
    record?.manifest?.catalog?.name ||
    record?.catalog_code ||
    "Catálogo curricular";

const fileName = (record) =>
    record?.original_filename ||
    record?.filename ||
    record?.file?.original_name ||
    "Archivo XLSX";

const fileHash = (record) =>
    record?.file_hash ||
    record?.source_hash ||
    record?.file?.sha256 ||
    record?.integrity?.source_sha256 ||
    "";

const firstValidationError = (record) => {
    const error = Array.isArray(record?.validation?.errors)
        ? record.validation.errors[0]
        : record?.validation?.errors?.errors?.[0];
    return (
        error?.message ||
        error?.reason ||
        record?.validation?.error_summary ||
        ""
    );
};

const importStatusLabel = (status) =>
    ({
        uploaded: "Recibido",
        validating: "Validando",
        invalid: "Con errores",
        validated: "Validado",
        pending_approval: "Pendiente de aprobación",
        approved: "Aprobado",
        activated: "Activo",
    }[status] || status);

const recordCanApprove = (record) =>
    canApprove.value &&
    (record?.capabilities?.can_approve ??
        ["validated", "pending_approval"].includes(record?.status));

const recordCanActivate = (record) =>
    canActivate.value &&
    (record?.capabilities?.can_activate ?? record?.status === "approved");
</script>

<template>
    <section
        v-if="canView"
        class="ld-curriculum-import"
        aria-labelledby="lcd-curriculum-import-title"
    >
        <div class="ld-curriculum-import__head">
            <div class="ld-curriculum-import__identity">
                <span aria-hidden="true"
                    ><i class="bx bx-spreadsheet"></i
                ></span>
                <div>
                    <small>Gobierno curricular</small>
                    <h3 id="lcd-curriculum-import-title">
                        Importar OA/OAT desde Excel
                    </h3>
                    <p>
                        Plantilla oficial del sistema para NT1, NT2, 1°–8°
                        Básico y 1°–4° Medio. La validación, aprobación y
                        activación son pasos separados.
                    </p>
                </div>
            </div>
            <BButton
                type="button"
                variant="outline-primary"
                :disabled="downloading"
                @click="downloadTemplate"
            >
                <span
                    v-if="downloading"
                    class="spinner-border spinner-border-sm"
                    aria-hidden="true"
                ></span>
                <i v-else class="bx bx-download" aria-hidden="true"></i>
                Descargar plantilla XLSX
            </BButton>
        </div>

        <BAlert show variant="info" class="ld-curriculum-import__notice mb-0">
            <i class="bx bx-shield-quarter" aria-hidden="true"></i>
            <div>
                <strong>Importación controlada y sin sobrescrituras</strong>
                <span>
                    El archivo original se conserva con SHA-256. Un lote
                    validado no queda activo hasta contar con aprobación
                    independiente y activación autorizada.
                </span>
            </div>
        </BAlert>

        <fieldset
            v-if="canManage || canActivate"
            class="ld-evidence-files"
            :disabled="processing"
        >
            <div class="ld-evidence-files__head">
                <div>
                    <legend>Archivos oficiales de respaldo</legend>
                    <p>
                        Agrega el PDF, HTML/XHTML o XLSX de cada documento de la
                        hoja <strong>Fuentes</strong>. La clave debe ser
                        idéntica a <code>source_key</code> y el SHA-256 debe
                        coincidir.
                    </p>
                </div>
                <BButton
                    type="button"
                    size="sm"
                    variant="outline-primary"
                    @click="addEvidenceRow"
                >
                    <i class="bx bx-plus" aria-hidden="true"></i>
                    Agregar evidencia
                </BButton>
            </div>
            <div class="ld-evidence-files__rows">
                <div
                    v-for="(row, index) in evidenceRows"
                    :key="row.inputKey"
                    class="ld-evidence-files__row"
                >
                    <div>
                        <label :for="`lcd-source-key-${row.inputKey}`"
                            >source_key</label
                        >
                        <BFormInput
                            :id="`lcd-source-key-${row.inputKey}`"
                            v-model.trim="row.sourceKey"
                            maxlength="100"
                            placeholder="Ej. BC_PARVULARIA_2018"
                            @update:model-value="
                                row.sourceKey = String(
                                    row.sourceKey || ''
                                ).toUpperCase()
                            "
                        />
                    </div>
                    <div>
                        <label :for="`lcd-source-file-${row.inputKey}`"
                            >Archivo oficial</label
                        >
                        <input
                            :id="`lcd-source-file-${row.inputKey}`"
                            class="form-control"
                            type="file"
                            accept=".pdf,.html,.htm,.xhtml,.xlsx,application/pdf,text/html,application/xhtml+xml,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            @change="chooseEvidenceFile(index, $event)"
                        />
                        <span v-if="row.hashing" class="ld-evidence-files__hash"
                            >Calculando SHA-256…</span
                        >
                        <code
                            v-else-if="row.sha256"
                            class="ld-evidence-files__hash"
                            :title="row.sha256"
                            >SHA-256 {{ row.sha256 }}</code
                        >
                    </div>
                    <BButton
                        type="button"
                        variant="outline-danger"
                        :aria-label="`Quitar evidencia ${index + 1}`"
                        @click="removeEvidenceRow(index)"
                    >
                        <i class="bx bx-trash" aria-hidden="true"></i>
                    </BButton>
                </div>
            </div>
            <small>
                Máximo 20 MiB por archivo. Puedes adjuntarlos al validar o
                completar los faltantes justo antes de activar.
            </small>
        </fieldset>

        <form
            v-if="canManage"
            class="ld-import-form"
            @submit.prevent="validateFile"
        >
            <div class="ld-import-form__file">
                <label for="lcd-curriculum-xlsx">Archivo Excel</label>
                <input
                    id="lcd-curriculum-xlsx"
                    ref="fileInput"
                    class="form-control"
                    type="file"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    required
                    @change="chooseFile"
                />
                <span v-if="selectedFile">
                    {{ selectedFile.name }} ·
                    {{ Math.ceil(selectedFile.size / 1024) }} KB
                </span>
            </div>
            <div class="ld-import-form__footer">
                <p>
                    Los datos, claves y hashes se leen exclusivamente desde el
                    XLSX. Los archivos de respaldo seleccionados arriba se
                    verifican byte a byte en el servidor.
                </p>
                <BButton
                    type="submit"
                    variant="primary"
                    :disabled="processing || !selectedFile || !contextReady"
                >
                    <span
                        v-if="processing"
                        class="spinner-border spinner-border-sm"
                        aria-hidden="true"
                    ></span>
                    <i v-else class="bx bx-check-shield" aria-hidden="true"></i>
                    Validar y registrar lote
                </BButton>
            </div>
            <BAlert
                v-if="!contextReady"
                show
                variant="warning"
                class="mt-3 mb-0"
            >
                Selecciona establecimiento y año académico en la barra de
                contexto antes de cargar la plantilla.
            </BAlert>
        </form>

        <BAlert v-else show variant="light" class="mb-0">
            Puede consultar las importaciones, pero no cargar nuevos archivos
            curriculares.
        </BAlert>

        <div class="ld-import-history__head">
            <div>
                <h4>Historial de importaciones</h4>
                <p>
                    Cada versión conserva archivo fuente, hash, validaciones y
                    responsables.
                </p>
            </div>
            <BFormInput
                v-if="canApprove || canActivate"
                v-model.trim="note"
                maxlength="500"
                placeholder="Nota de revisión o activación"
            />
        </div>

        <LibroDigitalStatePanel
            v-if="loading && !items.length"
            state="loading"
            title="Consultando importaciones"
            message="Recuperando la cadena de aprobación curricular."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !items.length"
            state="error"
            title="No se pudo cargar el historial"
            :message="errorMessage(error)"
            @retry="load"
        />
        <div v-else-if="items.length" class="ld-import-table-wrap">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">
                    Importaciones curriculares registradas
                </caption>
                <thead>
                    <tr>
                        <th>Catálogo</th>
                        <th>Archivo / hash</th>
                        <th>Contenido</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="record in items"
                        :key="record.public_id || record.id"
                    >
                        <td>
                            <strong>{{ catalogName(record) }}</strong>
                            <span
                                >{{ record.catalog_code || record.code }} ·
                                {{
                                    record.catalog_version || record.version
                                }}</span
                            >
                        </td>
                        <td>
                            <strong>{{ fileName(record) }}</strong>
                            <code
                                >{{ fileHash(record).slice(0, 16) || "Sin hash"
                                }}<template v-if="fileHash(record)"
                                    >…</template
                                ></code
                            >
                        </td>
                        <td>
                            <span>{{ evidenceCount(record) }} fuentes</span>
                            <span
                                >{{
                                    rowCount(record, "objectives")
                                }}
                                objetivos</span
                            >
                            <span
                                >{{ rowCount(record, "links") }} vínculos</span
                            >
                            <span
                                v-if="missingEvidenceKeys(record).length"
                                class="ld-import-missing-evidence"
                                :title="missingEvidenceKeys(record).join(', ')"
                            >
                                {{ missingEvidenceKeys(record).length }}
                                evidencias pendientes
                            </span>
                        </td>
                        <td>
                            <LibroDigitalStatusBadge
                                :status="record.status"
                                :label="importStatusLabel(record.status)"
                            />
                            <span
                                v-if="firstValidationError(record)"
                                class="ld-import-validation-error"
                                :title="firstValidationError(record)"
                            >
                                {{ firstValidationError(record) }}
                            </span>
                        </td>
                        <td>
                            <span>{{ formatDateTime(record.created_at) }}</span>
                            <span v-if="record.correlation_id"
                                >ID {{ record.correlation_id }}</span
                            >
                        </td>
                        <td>
                            <div class="ld-import-actions">
                                <BButton
                                    v-if="recordCanApprove(record)"
                                    type="button"
                                    size="sm"
                                    variant="outline-success"
                                    :disabled="processing"
                                    @click="action(record, 'approve')"
                                    ><i
                                        class="bx bx-check-circle"
                                        aria-hidden="true"
                                    ></i
                                    >Aprobar</BButton
                                >
                                <BButton
                                    v-if="recordCanActivate(record)"
                                    type="button"
                                    size="sm"
                                    variant="primary"
                                    :disabled="processing"
                                    @click="action(record, 'activate')"
                                    ><i
                                        class="bx bx-power-off"
                                        aria-hidden="true"
                                    ></i
                                    >Activar</BButton
                                >
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <LibroDigitalStatePanel
            v-else
            title="Sin importaciones curriculares"
            message="Descarga la plantilla y registra la primera versión oficial para NT1 a 4° Medio."
        />
    </section>
</template>

<style scoped>
.ld-curriculum-import {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--ld-color-line, #e4e9f0);
    border-radius: 16px;
    background: var(--ld-color-surface, #fff);
    box-shadow: 0 10px 30px rgba(17, 38, 76, 0.06);
}
.ld-curriculum-import__head,
.ld-import-history__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.ld-curriculum-import__identity {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}
.ld-curriculum-import__identity > span {
    display: grid;
    flex: 0 0 46px;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: #eaf2ff;
    color: #1d4ed8;
    font-size: 1.3rem;
}
.ld-curriculum-import small {
    color: #405189;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.ld-curriculum-import h3,
.ld-curriculum-import h4 {
    margin: 0.12rem 0 0.2rem;
    color: var(--ld-color-ink, #172033);
}
.ld-curriculum-import h3 {
    font-size: 1.1rem;
}
.ld-curriculum-import h4 {
    font-size: 0.88rem;
}
.ld-curriculum-import p {
    margin: 0;
    color: var(--ld-color-muted, #667085);
    font-size: 0.76rem;
}
.ld-curriculum-import .btn {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    gap: 0.36rem;
}
.ld-curriculum-import__notice {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.72rem 0.8rem;
}
.ld-curriculum-import__notice > i {
    font-size: 1.2rem;
}
.ld-curriculum-import__notice strong,
.ld-curriculum-import__notice span {
    display: block;
}
.ld-curriculum-import__notice strong {
    font-size: 0.78rem;
}
.ld-curriculum-import__notice span {
    margin-top: 0.1rem;
    font-size: 0.72rem;
}
.ld-evidence-files {
    display: grid;
    gap: 0.75rem;
    min-width: 0;
    margin: 0;
    padding: 0.9rem;
    border: 1px solid #c9d9ef;
    border-radius: 14px;
    background: #f5f8fd;
}
.ld-evidence-files__head,
.ld-evidence-files__row {
    display: flex;
    align-items: end;
    gap: 0.75rem;
}
.ld-evidence-files__head {
    align-items: start;
    justify-content: space-between;
}
.ld-evidence-files legend {
    float: none;
    width: auto;
    margin: 0 0 0.18rem;
    color: #253858;
    font-size: 0.82rem;
    font-weight: 800;
}
.ld-evidence-files code {
    color: #405189;
}
.ld-evidence-files__rows {
    display: grid;
    gap: 0.6rem;
}
.ld-evidence-files__row > div:first-child {
    flex: 0 0 min(280px, 32%);
}
.ld-evidence-files__row > div:nth-child(2) {
    flex: 1 1 420px;
}
.ld-evidence-files label {
    display: block;
    margin-bottom: 0.25rem;
    color: #536174;
    font-size: 0.72rem;
    font-weight: 750;
}
.ld-evidence-files .form-control {
    min-height: 40px;
    font-size: 0.76rem;
}
.ld-evidence-files__row > .btn {
    flex: 0 0 40px;
    width: 40px;
    min-height: 40px;
    padding: 0;
}
.ld-evidence-files__hash {
    display: block;
    margin-top: 0.28rem;
    overflow: hidden;
    color: #405189;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.7rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-evidence-files > small {
    color: #667085;
    font-size: 0.7rem;
}
.ld-import-form {
    padding: 0.9rem;
    border: 1px solid #dfe7f2;
    border-radius: 14px;
    background: #f8fafc;
}
.ld-import-form label {
    display: block;
    margin-bottom: 0.3rem;
    color: #536174;
    font-size: 0.72rem;
    font-weight: 750;
}
.ld-import-form .form-control {
    min-height: 40px;
    font-size: 0.78rem;
}
.ld-import-form__file {
    margin-bottom: 0.85rem;
    padding: 0.75rem;
    border: 1px dashed #aebed4;
    border-radius: 12px;
    background: #fff;
}
.ld-import-form__file > span {
    display: block;
    margin-top: 0.32rem;
    color: #405189;
    font-size: 0.72rem;
    font-weight: 700;
}
.ld-import-form__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 1rem;
}
.ld-import-form__footer p {
    margin-right: auto;
    max-width: 680px;
}
.ld-import-history__head {
    align-items: flex-end;
    padding-top: 0.25rem;
    border-top: 1px solid #edf0f4;
}
.ld-import-history__head .form-control {
    max-width: 420px;
    min-height: 38px;
}
.ld-import-table-wrap {
    overflow-x: auto;
    border: 1px solid #e4e9f0;
    border-radius: 12px;
}
.ld-import-table-wrap thead th {
    padding: 0.65rem;
    border-bottom: 1px solid #dfe5ee;
    background: #f5f7fb;
    color: #667085;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
}
.ld-import-table-wrap td {
    padding: 0.68rem;
    font-size: 0.74rem;
}
.ld-import-table-wrap td strong,
.ld-import-table-wrap td > span,
.ld-import-table-wrap td code {
    display: block;
}
.ld-import-table-wrap .ld-import-validation-error {
    display: -webkit-box;
    max-width: 240px;
    overflow: hidden;
    color: #b42318;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
.ld-import-table-wrap td > span,
.ld-import-table-wrap td code {
    margin-top: 0.12rem;
    color: #7b8797;
    font-size: 0.7rem;
}
.ld-import-table-wrap td .ld-import-missing-evidence {
    color: #b54708;
    font-weight: 750;
}
.ld-import-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.32rem;
}
.ld-import-actions .btn {
    min-height: 36px;
    font-size: 0.72rem;
}
@media (max-width: 768px) {
    .ld-curriculum-import__head,
    .ld-import-history__head {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-curriculum-import__head .btn,
    .ld-import-history__head .form-control {
        width: 100%;
        max-width: none;
    }
    .ld-import-form__footer {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-evidence-files__head,
    .ld-evidence-files__row {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-evidence-files__head .btn,
    .ld-evidence-files__row > div:first-child,
    .ld-evidence-files__row > div:nth-child(2) {
        width: 100%;
        flex-basis: auto;
    }
    .ld-evidence-files__row > .btn {
        align-self: flex-end;
    }
    .ld-import-form__footer .btn {
        width: 100%;
    }
}
</style>
