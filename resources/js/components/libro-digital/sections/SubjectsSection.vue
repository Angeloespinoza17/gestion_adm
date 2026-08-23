<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import CurriculumImportPanel from "./CurriculumImportPanel.vue";
import {
    confirmAction,
    errorMessage,
    payloadItems,
    payloadMeta,
    showError,
    showSuccess,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});
const emit = defineEmits(["catalog-changed"]);
const loading = ref(false);
const error = ref(null);
const items = ref([]);
const subjectMeta = ref({});
const search = ref("");
const educationType = ref("");
const availability = ref("");
const selected = ref(new Set());
const bulkSaving = ref(false);
const showForm = ref(false);
const saving = ref(false);
const editing = ref(null);
const showExternalMappings = ref(false);
const externalLoading = ref(false);
const externalSaving = ref(false);
const externalError = ref(null);
const externalItems = ref([]);
const externalMeta = ref({});
const externalSearch = ref("");
const externalScope = ref("");
const externalStatus = ref("");
const externalPage = ref(1);
const externalPageSize = 12;
const mappingDraft = ref({});
let controller = null;
let externalController = null;

const form = reactive({
    name: "",
    display_name: "",
    code: "",
    area: "",
    subject_type: "official",
    description: "",
    education_types: [],
    color: "#405189",
    active: true,
});
const canManage = computed(() =>
    Boolean(props.capabilities.can_manage_subject_catalog)
);
const educationOptions = computed(
    () =>
        subjectMeta.value.education_types || [
            { value: "parvularia", label: "Educación Parvularia" },
            { value: "basica", label: "Enseñanza Básica" },
            { value: "media", label: "Enseñanza Media" },
        ]
);
const subjectTypeOptions = computed(
    () => subjectMeta.value.subject_types || []
);
const filtered = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase("es");
    return items.value.filter((item) => {
        const matchesSearch =
            !needle ||
            [
                item.name,
                item.technical_name,
                item.code,
                item.official_code,
                item.area,
                item.description,
                ...(item.aliases || []).map((alias) => alias.external_name),
            ]
                .filter(Boolean)
                .join(" ")
                .toLocaleLowerCase("es")
                .includes(needle);
        const matchesEducation =
            !educationType.value ||
            (item.education_types || []).includes(educationType.value);
        const matchesAvailability =
            !availability.value ||
            (availability.value === "active" ? item.active : !item.active);

        return matchesSearch && matchesEducation && matchesAvailability;
    });
});
const selectedCount = computed(() => selected.value.size);
const allVisibleSelected = computed(
    () =>
        filtered.value.length > 0 &&
        filtered.value.every((item) => selected.value.has(item.id))
);
const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        const response = await libroDigitalApi.subjects(
            { school_id: props.context.school_id, per_page: 250 },
            controller.signal
        );
        items.value = payloadItems(response);
        subjectMeta.value = payloadMeta(response);
        const availableIds = new Set(items.value.map((item) => item.id));
        selected.value = new Set(
            [...selected.value].filter((id) => availableIds.has(id))
        );
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loading.value = false;
    }
};
watch([() => props.context.school_id, () => props.refreshToken], load, {
    immediate: true,
});
watch(
    () => props.context.school_id,
    () => {
        selected.value = new Set();
        externalItems.value = [];
        mappingDraft.value = {};
    }
);
onBeforeUnmount(() => {
    controller?.abort();
    externalController?.abort();
});

const resetForm = () =>
    Object.assign(form, {
        name: "",
        display_name: "",
        code: "",
        area: "",
        subject_type: "official",
        description: "",
        education_types: [],
        color: "#405189",
        active: true,
    });
const openCreate = () => {
    editing.value = null;
    resetForm();
    showForm.value = true;
};
const openEdit = (item) => {
    editing.value = item;
    Object.assign(form, {
        name: item.technical_name || item.name || "",
        display_name:
            item.name !== item.technical_name ? item.name || "" : "",
        code: item.code || "",
        area: item.area || "",
        subject_type: item.type || "official",
        description: item.description || "",
        education_types: [...(item.education_types || [])],
        color: item.color || "#405189",
        active: Boolean(item.active),
    });
    showForm.value = true;
};
const save = async () => {
    saving.value = true;
    try {
        const scopedPayload = { ...form, school_id: props.context.school_id };
        if (editing.value)
            await libroDigitalApi.updateSubject(editing.value, scopedPayload);
        else await libroDigitalApi.createSubject(scopedPayload);
        showForm.value = false;
        await load();
        emit("catalog-changed");
        await showSuccess(
            editing.value ? "Asignatura actualizada" : "Asignatura creada"
        );
    } catch (requestError) {
        await showError(requestError, "No se pudo guardar la asignatura");
        if (requestError.isConflict) await load();
    } finally {
        saving.value = false;
    }
};
const toggleActive = async (item) => {
    const next = !item.active;
    const confirmation = await confirmAction({
        title: next ? "Reactivar asignatura" : "Desactivar asignatura",
        text: next
            ? "Volverá a estar disponible para nuevas asignaciones."
            : "Se conservará todo su historial y dejará de ofrecerse en nuevos libros.",
        confirmText: next ? "Reactivar" : "Desactivar",
    });
    if (!confirmation.isConfirmed) return;
    try {
        await libroDigitalApi.updateSubject(item, {
            active: next,
            school_id: props.context.school_id,
        });
        await load();
        emit("catalog-changed");
    } catch (requestError) {
        await showError(requestError);
    }
};
const toggleSelected = (id) => {
    const next = new Set(selected.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selected.value = next;
};
const toggleVisible = () => {
    const next = new Set(selected.value);
    if (allVisibleSelected.value)
        filtered.value.forEach((item) => next.delete(item.id));
    else filtered.value.forEach((item) => next.add(item.id));
    selected.value = next;
};
const runBulkStatus = async (active) => {
    if (!selectedCount.value) return;
    const confirmation = await confirmAction({
        title: active
            ? `Activar ${selectedCount.value} asignaturas`
            : `Desactivar ${selectedCount.value} asignaturas`,
        text: "La acción conserva nombres técnicos, relaciones e historial. El cambio afecta el catálogo institucional compartido.",
        confirmText: active ? "Activar selección" : "Desactivar selección",
    });
    if (!confirmation.isConfirmed) return;
    bulkSaving.value = true;
    try {
        const response = await libroDigitalApi.bulkSubjectStatus({
            school_id: props.context.school_id,
            subject_ids: [...selected.value],
            active,
        });
        const changed = payloadMeta(response).changed ?? selectedCount.value;
        selected.value = new Set();
        await load();
        emit("catalog-changed");
        await showSuccess(
            active ? "Asignaturas activadas" : "Asignaturas desactivadas",
            `${changed} registros actualizados sin eliminar historial.`
        );
    } catch (requestError) {
        await showError(requestError, "No se pudo completar la acción masiva");
    } finally {
        bulkSaving.value = false;
    }
};

const externalState = (item) => {
    const draft = Number(mappingDraft.value[item.mapping_key] || 0);
    if (draft) return draft === Number(item.mapped_subject_id || 0) ? "mapped" : "ready";
    return item.status;
};
const externalFiltered = computed(() => {
    const needle = externalSearch.value.trim().toLocaleLowerCase("es");
    return externalItems.value.filter((item) => {
        const matchesSearch =
            !needle ||
            [
                item.external_name,
                item.scope_label,
                item.mapped_subject?.name,
                item.suggested_subject?.name,
            ]
                .filter(Boolean)
                .join(" ")
                .toLocaleLowerCase("es")
                .includes(needle);
        const matchesScope =
            !externalScope.value || item.scope_code === externalScope.value;
        const matchesStatus =
            !externalStatus.value ||
            externalState(item) === externalStatus.value;
        return matchesSearch && matchesScope && matchesStatus;
    });
});
const externalPageCount = computed(() =>
    Math.max(1, Math.ceil(externalFiltered.value.length / externalPageSize))
);
const externalPageItems = computed(() => {
    const start = (externalPage.value - 1) * externalPageSize;
    return externalFiltered.value.slice(start, start + externalPageSize);
});
const externalPageRange = computed(() => {
    if (!externalFiltered.value.length) return { from: 0, to: 0 };
    const from = (externalPage.value - 1) * externalPageSize + 1;
    return {
        from,
        to: Math.min(from + externalPageSize - 1, externalFiltered.value.length),
    };
});
watch([externalSearch, externalScope, externalStatus], () => {
    externalPage.value = 1;
});
watch(externalPageCount, (pageCount) => {
    if (externalPage.value > pageCount) externalPage.value = pageCount;
});
const changedMappings = computed(() =>
    externalItems.value.filter((item) => {
        const draft = Number(mappingDraft.value[item.mapping_key] || 0);
        return draft && draft !== Number(item.mapped_subject_id || 0);
    })
);
const loadExternalCatalog = async () => {
    externalController?.abort();
    externalController = new AbortController();
    externalLoading.value = true;
    externalError.value = null;
    try {
        const response = await libroDigitalApi.externalSubjectCatalog(
            {
                school_id: props.context.school_id,
                source_system: "legacy_gradebook",
            },
            externalController.signal
        );
        externalItems.value = payloadItems(response);
        externalMeta.value = payloadMeta(response);
        externalPage.value = 1;
        mappingDraft.value = Object.fromEntries(
            externalItems.value.map((item) => [
                item.mapping_key,
                item.mapped_subject_id || "",
            ])
        );
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED")
            externalError.value = requestError;
    } finally {
        externalLoading.value = false;
    }
};
const openExternalCatalog = async () => {
    showExternalMappings.value = true;
    await loadExternalCatalog();
};
const applySuggestions = () => {
    const next = { ...mappingDraft.value };
    externalPageItems.value.forEach((item) => {
        if (!next[item.mapping_key] && item.suggested_subject_id)
            next[item.mapping_key] = item.suggested_subject_id;
    });
    mappingDraft.value = next;
};
const saveExternalMappings = async () => {
    if (!changedMappings.value.length) return;
    externalSaving.value = true;
    try {
        await libroDigitalApi.updateExternalSubjectMappings({
            school_id: props.context.school_id,
            source_system: "legacy_gradebook",
            mappings: changedMappings.value.map((item) => ({
                scope_code: item.scope_code,
                external_name: item.external_name,
                schedule_subject_id: Number(
                    mappingDraft.value[item.mapping_key]
                ),
            })),
        });
        await Promise.all([loadExternalCatalog(), load()]);
        emit("catalog-changed");
        await showSuccess(
            "Equivalencias confirmadas",
            "La importación anual ya puede resolver estos nombres con trazabilidad."
        );
    } catch (requestError) {
        await showError(requestError, "No se pudieron guardar las equivalencias");
    } finally {
        externalSaving.value = false;
    }
};
const educationLabel = (value) =>
    educationOptions.value.find((option) => option.value === value)?.label ||
    value;
const subjectTypeLabel = (value) =>
    subjectTypeOptions.value.find((option) => option.value === value)?.label ||
    "Oficial MINEDUC";
</script>

<template>
    <section
        class="ld-section ld-subjects"
        aria-labelledby="lcd-subjects-title"
        :aria-busy="loading"
    >
        <header class="ld-section-head">
            <div class="ld-section-head__identity">
                <span class="ld-section-head__icon" aria-hidden="true"
                    ><i class="bx bx-grid-alt"></i
                ></span>
                <div>
                    <span class="ld-eyebrow">Catálogo curricular</span>
                    <h2 id="lcd-subjects-title">Asignaturas</h2>
                    <p>
                        Catálogo institucional reutilizado por horarios, planes
                        de estudio y Libro Digital.
                    </p>
                </div>
            </div>
            <div v-if="canManage" class="ld-section-head__actions">
                <BButton
                    type="button"
                    size="sm"
                    variant="outline-primary"
                    class="ld-primary-action"
                    aria-label="Equivalencias de importación"
                    title="Equivalencias de importación"
                    @click="openExternalCatalog"
                    ><i class="bx bx-transfer-alt" aria-hidden="true"></i
                    ><span>Equivalencias de importación</span></BButton
                >
                <BButton
                    type="button"
                    size="sm"
                    variant="primary"
                    class="ld-primary-action"
                    aria-label="Nueva asignatura"
                    title="Nueva asignatura"
                    @click="openCreate"
                    ><i class="bx bx-plus" aria-hidden="true"></i
                    ><span>Nueva asignatura</span></BButton
                >
            </div>
        </header>

        <BAlert
            v-if="!canManage"
            show
            variant="info"
            class="ld-permission-notice mb-0"
            role="status"
        >
            <span class="ld-permission-notice__icon" aria-hidden="true"
                ><i class="bx bx-lock-alt"></i
            ></span>
            <div>
                <strong>Catálogo en modo consulta</strong
                ><span
                    >La administración institucional requiere el permiso
                    específico de catálogo de asignaturas.</span
                >
            </div>
        </BAlert>

        <aside class="ld-import-readiness" aria-label="Preparación de importación">
            <span class="ld-import-readiness__icon" aria-hidden="true"
                ><i class="bx bx-git-compare"></i
            ></span>
            <div>
                <span class="ld-eyebrow">Importación desde el libro anterior</span>
                <strong>Nombres externos separados del catálogo oficial</strong>
                <p>
                    Las cuatro listas de las capturas están disponibles para
                    confirmar equivalencias sin renombrar ni duplicar las
                    asignaturas oficiales.
                </p>
            </div>
            <div class="ld-import-readiness__metrics">
                <span><strong>{{ externalMeta.mapped || 0 }}</strong> confirmadas</span>
                <span><strong>{{ externalMeta.total || "4 listas" }}</strong> en referencia</span>
            </div>
            <BButton
                type="button"
                size="sm"
                variant="light"
                @click="openExternalCatalog"
                >Revisar mapeo <i class="bx bx-right-arrow-alt" aria-hidden="true"></i
            ></BButton>
        </aside>

        <div class="ld-commandbar" role="search">
            <div class="ld-commandbar__search">
                <label for="lcd-subject-search">Buscar en asignaturas</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" aria-hidden="true"
                        ><i class="bx bx-search"></i></span
                    ><BFormInput
                        id="lcd-subject-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        placeholder="Nombre, código oficial o área"
                    /><BButton
                        v-if="search"
                        type="button"
                        size="sm"
                        variant="light"
                        aria-label="Limpiar búsqueda"
                        @click="search = ''"
                        ><i class="bx bx-x" aria-hidden="true"></i
                    ></BButton>
                </div>
            </div>
            <div class="ld-commandbar__filter">
                <label for="lcd-subject-education">Tipo de enseñanza</label>
                <BFormSelect id="lcd-subject-education" v-model="educationType" size="sm">
                    <option value="">Todos los niveles</option>
                    <option
                        v-for="option in educationOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </BFormSelect>
            </div>
            <div class="ld-commandbar__filter">
                <label for="lcd-subject-availability">Disponibilidad</label>
                <BFormSelect id="lcd-subject-availability" v-model="availability" size="sm">
                    <option value="">Activas e inactivas</option>
                    <option value="active">Solo activas</option>
                    <option value="inactive">Solo inactivas</option>
                </BFormSelect>
            </div>
            <div class="ld-commandbar__summary" aria-live="polite">
                <span class="ld-result-count"
                    ><strong>{{ filtered.length }}</strong> de
                    {{ items.length }} asignaturas</span
                ><span v-if="search" class="ld-filter-chip"
                    ><i class="bx bx-filter-alt" aria-hidden="true"></i>Filtro
                    activo</span
                >
            </div>
        </div>

        <div v-if="canManage && items.length" class="ld-bulkbar">
            <BFormCheckbox
                :model-value="allVisibleSelected"
                :indeterminate="selectedCount > 0 && !allVisibleSelected"
                @update:model-value="toggleVisible"
            >
                {{ allVisibleSelected ? "Quitar selección visible" : "Seleccionar resultados" }}
            </BFormCheckbox>
            <span class="ld-bulkbar__count">
                <strong>{{ selectedCount }}</strong> seleccionadas
            </span>
            <div class="ld-bulkbar__actions">
                <BButton
                    type="button"
                    size="sm"
                    variant="outline-success"
                    :disabled="!selectedCount || bulkSaving"
                    @click="runBulkStatus(true)"
                    ><i class="bx bx-check-circle" aria-hidden="true"></i>Activar</BButton
                >
                <BButton
                    type="button"
                    size="sm"
                    variant="outline-secondary"
                    :disabled="!selectedCount || bulkSaving"
                    @click="runBulkStatus(false)"
                    ><i class="bx bx-pause-circle" aria-hidden="true"></i>Desactivar</BButton
                >
            </div>
        </div>

        <LibroDigitalStatePanel
            v-if="loading && !items.length"
            state="loading"
            title="Cargando asignaturas"
            message="Consultando el catálogo institucional."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !items.length"
            state="error"
            title="No se pudieron cargar las asignaturas"
            :message="errorMessage(error)"
            @retry="load"
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
                    >No fue posible actualizar el catálogo; se conserva la
                    última lectura visible.</span
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
                v-if="filtered.length"
                class="ld-subject-grid"
                aria-label="Catálogo de asignaturas"
            >
                <article
                    v-for="item in filtered"
                    :key="item.id"
                    class="ld-subject-card"
                    :class="{
                        'ld-subject-card--inactive': !item.active,
                        'ld-subject-card--selected': selected.has(item.id),
                    }"
                    :aria-selected="selected.has(item.id)"
                >
                    <span
                        class="ld-subject-card__accent"
                        :style="{ backgroundColor: item.color || '#405189' }"
                        aria-hidden="true"
                    ></span>
                    <div class="ld-subject-card__top">
                        <div class="ld-subject-card__identity">
                            <BFormCheckbox
                                v-if="canManage"
                                :model-value="selected.has(item.id)"
                                :aria-label="`Seleccionar ${item.name}`"
                                @update:model-value="toggleSelected(item.id)"
                            />
                            <span
                                class="ld-subject-card__mark"
                                :style="{
                                    color: item.color || '#405189',
                                    backgroundColor: `${item.color || '#405189'}14`,
                                }"
                                aria-hidden="true"
                                ><i class="bx bx-book"></i
                            ></span>
                        </div>
                        <LibroDigitalStatusBadge
                            :status="item.active ? 'active' : 'cancelled'"
                            :label="item.active ? 'Activa' : 'Inactiva'"
                        />
                    </div>
                    <div class="ld-subject-card__body">
                        <h3>{{ item.name }}</h3>
                        <small
                            v-if="item.technical_name && item.technical_name !== item.name"
                            class="ld-subject-card__technical"
                            >Técnico · {{ item.technical_name }}</small
                        >
                        <div class="ld-subject-card__codes">
                            <span>{{ item.code || "Sin código interno" }}</span
                            ><span v-if="item.official_code"
                                >Oficial · {{ item.official_code }}</span
                            >
                        </div>
                        <p>
                            {{
                                item.description ||
                                "Sin descripción complementaria."
                            }}
                        </p>
                        <div v-if="item.alias_count" class="ld-subject-card__aliases">
                            <span><i class="bx bx-link-alt" aria-hidden="true"></i>{{ item.alias_count }} equivalencias</span>
                            <span
                                v-for="alias in (item.aliases || []).slice(0, 2)"
                                :key="alias.id"
                                :title="alias.external_name"
                                >{{ alias.external_name }}</span
                            >
                        </div>
                        <dl class="ld-subject-card__meta">
                            <div>
                                <dt>
                                    <i
                                        class="bx bx-grid-alt"
                                        aria-hidden="true"
                                    ></i
                                    >Área
                                </dt>
                                <dd>{{ item.area || "Sin área" }}</dd>
                            </div>
                            <div>
                                <dt>
                                    <i
                                        class="bx bx-bookmark"
                                        aria-hidden="true"
                                    ></i
                                    >Tipo
                                </dt>
                                <dd>{{ subjectTypeLabel(item.type) }}</dd>
                            </div>
                        </dl>
                        <div class="ld-subject-card__levels">
                            <span
                                v-for="type in item.education_types || []"
                                :key="type"
                                >{{ educationLabel(type) }}</span
                            ><span v-if="!(item.education_types || []).length"
                                >Nivel por definir</span
                            >
                        </div>
                    </div>
                    <footer v-if="canManage" class="ld-subject-card__actions">
                        <BButton
                            type="button"
                            size="sm"
                            variant="outline-primary"
                            :aria-label="`Editar ${item.name}`"
                            @click="openEdit(item)"
                            ><i class="bx bx-edit" aria-hidden="true"></i
                            ><span>Editar</span></BButton
                        >
                        <BButton
                            type="button"
                            size="sm"
                            :variant="
                                item.active
                                    ? 'outline-secondary'
                                    : 'outline-success'
                            "
                            :aria-label="`${
                                item.active ? 'Desactivar' : 'Reactivar'
                            } ${item.name}`"
                            @click="toggleActive(item)"
                            ><i
                                class="bx"
                                :class="
                                    item.active
                                        ? 'bx-pause-circle'
                                        : 'bx-check-circle'
                                "
                                aria-hidden="true"
                            ></i
                            ><span>{{
                                item.active ? "Desactivar" : "Reactivar"
                            }}</span></BButton
                        >
                    </footer>
                </article>
            </div>
            <LibroDigitalStatePanel
                v-else
                :title="search ? 'Sin coincidencias' : 'Sin asignaturas'"
                :message="
                    search
                        ? 'No encontramos asignaturas para esa búsqueda. Prueba con otro nombre, código o área.'
                        : 'Crea una asignatura para comenzar. Los registros históricos nunca se eliminan.'
                "
            />
        </template>

        <BModal
            v-model="showForm"
            :title="editing ? 'Editar asignatura' : 'Nueva asignatura'"
            size="lg"
            body-class="ld-modal-body"
            hide-footer
        >
            <form
                class="ld-form"
                aria-describedby="ld-subject-form-help"
                @submit.prevent="save"
            >
                <div class="ld-form__intro">
                    <span
                        class="ld-form__intro-icon"
                        :style="{
                            color: form.color,
                            backgroundColor: `${form.color}14`,
                        }"
                        aria-hidden="true"
                        ><i class="bx bx-grid-alt"></i
                    ></span>
                    <div>
                        <strong>{{
                            editing
                                ? "Actualiza la ficha institucional"
                                : "Crea una asignatura institucional"
                        }}</strong>
                        <p id="ld-subject-form-help">
                            Esta ficha se reutiliza en horarios, planes y libros
                            digitales.
                        </p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="lcd-subject-name"
                            >Nombre técnico <span aria-hidden="true">*</span></label
                        ><BFormInput
                            id="lcd-subject-name"
                            v-model.trim="form.name"
                            maxlength="150"
                            required
                            placeholder="Ej.: Lengua y Literatura"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="lcd-subject-color"
                            >Color de identificación</label
                        >
                        <div class="ld-color-control">
                            <BFormInput
                                id="lcd-subject-color"
                                v-model="form.color"
                                type="color"
                            /><code>{{ form.color }}</code>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="lcd-subject-display-name"
                            >Nombre visible limpio</label
                        ><BFormInput
                            id="lcd-subject-display-name"
                            v-model.trim="form.display_name"
                            maxlength="191"
                            placeholder="Ej.: Lengua y Literatura"
                        />
                        <small class="ld-field-help">Se muestra en la interfaz sin alterar la identidad técnica ni los libros históricos.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="lcd-subject-type">Categoría</label>
                        <BFormSelect id="lcd-subject-type" v-model="form.subject_type">
                            <option
                                v-for="option in subjectTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >{{ option.label }}</option>
                        </BFormSelect>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lcd-subject-code"
                            >Código interno</label
                        ><BFormInput
                            id="lcd-subject-code"
                            v-model.trim="form.code"
                            maxlength="50"
                            placeholder="Ej.: LENG-01"
                        />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lcd-subject-area"
                            >Área</label
                        ><BFormInput
                            id="lcd-subject-area"
                            v-model.trim="form.area"
                            maxlength="100"
                            placeholder="Ej.: Humanidades"
                        />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tipos de enseñanza</label>
                        <div class="ld-education-options">
                            <BFormCheckbox
                                v-for="option in educationOptions"
                                :key="option.value"
                                v-model="form.education_types"
                                :value="option.value"
                            >{{ option.label }}</BFormCheckbox>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="lcd-subject-description">Descripción visible</label>
                        <BFormTextarea
                            id="lcd-subject-description"
                            v-model.trim="form.description"
                            rows="2"
                            maxlength="1000"
                            placeholder="Uso pedagógico o alcance institucional de la asignatura"
                        />
                    </div>
                </div>
                <BAlert show variant="light" class="ld-governance-note mb-0"
                    ><i class="bx bx-shield-quarter" aria-hidden="true"></i
                    ><span
                        >El nombre técnico y el código siguen siendo la identidad
                        de integración. El nombre visible y los niveles son una
                        capa de presentación reversible y auditable.</span
                    ></BAlert
                >
                <div class="ld-availability-control">
                    <div>
                        <strong>Disponibilidad</strong
                        ><span
                            >Controla si puede usarse en nuevos libros y
                            planes.</span
                        >
                    </div>
                    <BFormCheckbox v-model="form.active" switch
                        ><span class="visually-hidden"
                            >Disponible para nuevos libros y planes</span
                        ></BFormCheckbox
                    >
                </div>
                <div class="ld-form__actions">
                    <BButton
                        type="button"
                        variant="outline-secondary"
                        :disabled="saving"
                        @click="showForm = false"
                        >Cancelar</BButton
                    ><BButton type="submit" variant="primary" :disabled="saving"
                        ><span
                            v-if="saving"
                            class="spinner-border spinner-border-sm"
                            aria-hidden="true"
                        ></span
                        ><i v-else class="bx bx-save" aria-hidden="true"></i
                        >{{
                            saving ? "Guardando" : "Guardar asignatura"
                        }}</BButton
                    >
                </div>
            </form>
        </BModal>

        <BModal
            v-model="showExternalMappings"
            title="Equivalencias con el libro digital anterior"
            size="xl"
            body-class="ld-external-modal"
            hide-footer
            no-close-on-backdrop
        >
            <div class="ld-external-hero">
                <span aria-hidden="true"><i class="bx bx-transfer-alt"></i></span>
                <div>
                    <strong>{{ externalMeta.source_label || "Libro digital anterior" }}</strong>
                    <p>{{ externalMeta.source_description || "Confirma el destino de cada nombre externo antes de importar notas." }}</p>
                </div>
                <dl>
                    <div><dt>Total</dt><dd>{{ externalMeta.total || externalItems.length }}</dd></div>
                    <div><dt>Confirmadas</dt><dd>{{ externalMeta.mapped || 0 }}</dd></div>
                    <div><dt>Sugeridas</dt><dd>{{ externalMeta.suggested || 0 }}</dd></div>
                    <div><dt>Pendientes</dt><dd>{{ externalMeta.pending || 0 }}</dd></div>
                </dl>
            </div>
            <div class="ld-external-toolbar">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                    <BFormInput v-model="externalSearch" type="search" placeholder="Buscar nombre externo o asignatura propia" />
                </div>
                <BFormSelect v-model="externalScope" size="sm">
                    <option value="">Todos los listados</option>
                    <option v-for="group in externalMeta.groups || []" :key="group.code" :value="group.code">{{ group.label }}</option>
                </BFormSelect>
                <BFormSelect v-model="externalStatus" size="sm">
                    <option value="">Todos los estados</option>
                    <option value="mapped">Confirmadas</option>
                    <option value="suggested">Con sugerencia</option>
                    <option value="pending">Pendientes</option>
                    <option value="ready">Listas para guardar</option>
                </BFormSelect>
                <BButton type="button" size="sm" variant="outline-primary" :disabled="externalLoading" @click="applySuggestions">
                    <i class="bx bx-bulb" aria-hidden="true"></i>Aplicar sugerencias visibles
                </BButton>
            </div>
            <LibroDigitalStatePanel
                v-if="externalLoading && !externalItems.length"
                state="loading"
                title="Cargando nombres externos"
                message="Preparando las listas transcritas desde las capturas."
            />
            <LibroDigitalStatePanel
                v-else-if="externalError && !externalItems.length"
                state="error"
                title="No se pudo cargar el catálogo externo"
                :message="errorMessage(externalError)"
                @retry="loadExternalCatalog"
            />
            <div v-else class="ld-external-table-wrap">
                <table class="table align-middle mb-0 ld-external-table">
                    <thead><tr><th>Nombre en el otro software</th><th>Listado / nivel</th><th>Asignatura del Libro Digital propio</th><th>Estado</th></tr></thead>
                    <tbody>
                        <tr v-for="item in externalPageItems" :key="item.mapping_key">
                            <td><strong>{{ item.external_name }}</strong><small>{{ item.education_type === "all" ? "Uso institucional" : educationLabel(item.education_type) }}</small></td>
                            <td><span class="ld-scope-chip">{{ item.scope_label }}</span></td>
                            <td>
                                <BFormSelect
                                    :model-value="mappingDraft[item.mapping_key]"
                                    size="sm"
                                    @update:model-value="mappingDraft = { ...mappingDraft, [item.mapping_key]: $event }"
                                >
                                    <option value="">Seleccionar equivalencia</option>
                                    <option v-for="subject in items" :key="subject.id" :value="subject.id">{{ subject.name }} · {{ subject.code || "sin código" }}</option>
                                </BFormSelect>
                                <button
                                    v-if="item.suggested_subject && !mappingDraft[item.mapping_key]"
                                    type="button"
                                    class="ld-suggestion-link"
                                    @click="mappingDraft = { ...mappingDraft, [item.mapping_key]: item.suggested_subject_id }"
                                >
                                    Sugerencia: {{ item.suggested_subject.name }}
                                </button>
                            </td>
                            <td><span class="ld-mapping-status" :class="`is-${externalState(item)}`">{{ externalState(item) === "mapped" ? "Confirmada" : externalState(item) === "ready" ? "Lista" : externalState(item) === "suggested" ? "Sugerida" : "Pendiente" }}</span></td>
                        </tr>
                    </tbody>
                </table>
                <LibroDigitalStatePanel v-if="!externalFiltered.length" compact title="Sin resultados" message="Ajusta los filtros del catálogo externo." />
            </div>
            <nav
                v-if="externalFiltered.length"
                class="ld-external-pagination"
                aria-label="Páginas del catálogo externo"
            >
                <span>
                    Mostrando <strong>{{ externalPageRange.from }}–{{ externalPageRange.to }}</strong>
                    de {{ externalFiltered.length }} nombres
                </span>
                <div>
                    <BButton
                        type="button"
                        size="sm"
                        variant="outline-secondary"
                        :disabled="externalPage <= 1"
                        aria-label="Página anterior"
                        @click="externalPage -= 1"
                    ><i class="bx bx-chevron-left" aria-hidden="true"></i>Anterior</BButton>
                    <span aria-live="polite">Página {{ externalPage }} de {{ externalPageCount }}</span>
                    <BButton
                        type="button"
                        size="sm"
                        variant="outline-secondary"
                        :disabled="externalPage >= externalPageCount"
                        aria-label="Página siguiente"
                        @click="externalPage += 1"
                    >Siguiente<i class="bx bx-chevron-right" aria-hidden="true"></i></BButton>
                </div>
            </nav>
            <footer class="ld-external-footer">
                <span><strong>{{ changedMappings.length }}</strong> equivalencias nuevas por confirmar</span>
                <div>
                    <BButton type="button" variant="outline-secondary" :disabled="externalSaving" @click="showExternalMappings = false">Cerrar</BButton>
                    <BButton type="button" variant="primary" :disabled="!changedMappings.length || externalSaving" @click="saveExternalMappings">
                        <span v-if="externalSaving" class="spinner-border spinner-border-sm" aria-hidden="true"></span><i v-else class="bx bx-check-shield" aria-hidden="true"></i>{{ externalSaving ? "Guardando" : "Confirmar equivalencias" }}
                    </BButton>
                </div>
            </footer>
        </BModal>

        <CurriculumImportPanel
            :context="context"
            :capabilities="capabilities"
            :refresh-token="refreshToken"
            @activated="emit('catalog-changed')"
        />
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
    margin: 0;
    color: var(--ld-muted);
    font-size: 0.8rem;
    line-height: 1.45;
}
.ld-section-head__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
}
.ld-primary-action,
.ld-subject-card__actions .btn,
.ld-form__actions .btn {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    justify-content: center;
    gap: 0.36rem;
}
.ld-permission-notice {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.68rem 0.8rem;
    border-color: #cfe4ee;
    background: #f2f9fc;
    color: #395c6a;
}
.ld-permission-notice__icon {
    display: grid;
    flex: 0 0 34px;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #dff1f7;
    color: #28768d;
    font-size: 1rem;
}
.ld-permission-notice strong,
.ld-permission-notice div > span {
    display: block;
}
.ld-permission-notice strong {
    font-size: 0.78rem;
}
.ld-permission-notice div > span {
    margin-top: 0.08rem;
    font-size: 0.72rem;
}
.ld-import-readiness {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 0.85rem;
    padding: 0.9rem 1rem;
    overflow: hidden;
    border: 1px solid #d8e1f3;
    border-radius: 16px;
    background:
        radial-gradient(circle at 95% 0, rgba(79, 70, 229, 0.1), transparent 34%),
        linear-gradient(135deg, #fbfcff 0%, #f4f7ff 100%);
    box-shadow: 0 8px 26px rgba(41, 55, 95, 0.05);
}
.ld-import-readiness__icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 13px;
    background: #e8edfb;
    color: var(--ld-primary);
    font-size: 1.18rem;
}
.ld-import-readiness strong {
    display: block;
    margin-top: 0.12rem;
    color: var(--ld-ink);
    font-size: 0.84rem;
}
.ld-import-readiness p {
    margin: 0.18rem 0 0;
    color: var(--ld-muted);
    font-size: 0.72rem;
    line-height: 1.45;
}
.ld-import-readiness__metrics {
    display: flex;
    gap: 0.4rem;
}
.ld-import-readiness__metrics span {
    min-width: 88px;
    padding: 0.45rem 0.55rem;
    border: 1px solid rgba(64, 81, 137, 0.13);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.72);
    color: #667085;
    font-size: 0.68rem;
    text-align: center;
}
.ld-import-readiness__metrics strong {
    margin: 0 0 0.08rem;
    color: var(--ld-primary);
    font-size: 0.82rem;
}
.ld-import-readiness .btn {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    gap: 0.3rem;
    border-color: #d8e0ef;
    color: var(--ld-primary);
    font-size: 0.72rem;
    font-weight: 750;
}
.ld-commandbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.72rem 0.8rem;
    border: 1px solid var(--ld-line);
    border-radius: 14px;
    background: var(--ld-surface);
    box-shadow: 0 5px 18px rgba(26, 39, 66, 0.035);
}
.ld-commandbar__search {
    flex: 1;
    max-width: 480px;
}
.ld-commandbar__search > label,
.ld-commandbar__filter > label {
    display: block;
    margin-bottom: 0.28rem;
    color: #596579;
    font-size: 0.72rem;
    font-weight: 750;
}
.ld-commandbar__filter {
    flex: 0 1 210px;
}
.ld-commandbar__filter .form-select {
    min-height: 40px;
    font-size: 0.76rem;
}
.ld-commandbar__search .input-group-text {
    border-right: 0;
    background: #f8fafc;
    color: #7c8798;
}
.ld-commandbar__search .form-control {
    min-height: 40px;
    border-left: 0;
    font-size: 0.8rem;
}
.ld-commandbar__search .btn {
    min-width: 40px;
    border-color: #ced4da;
}
.ld-commandbar__summary {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 40px;
}
.ld-result-count {
    color: var(--ld-muted);
    font-size: 0.75rem;
}
.ld-result-count strong {
    color: var(--ld-ink);
    font-size: 0.78rem;
}
.ld-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.24rem;
    padding: 0.22rem 0.48rem;
    border-radius: 999px;
    background: #eef2fb;
    color: var(--ld-primary);
    font-size: 0.7rem;
    font-weight: 700;
}
.ld-bulkbar {
    display: flex;
    min-height: 50px;
    align-items: center;
    gap: 0.8rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid #dae2f0;
    border-radius: 13px;
    background: #f8faff;
    color: #475467;
    font-size: 0.75rem;
}
.ld-bulkbar__count {
    padding-left: 0.8rem;
    border-left: 1px solid #d8dfeb;
    color: #697586;
}
.ld-bulkbar__count strong {
    color: var(--ld-primary);
}
.ld-bulkbar__actions {
    display: flex;
    gap: 0.4rem;
    margin-left: auto;
}
.ld-bulkbar__actions .btn {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
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
.ld-subject-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}
.ld-subject-card {
    position: relative;
    display: flex;
    min-width: 0;
    min-height: 244px;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--ld-line);
    border-radius: 14px;
    background: var(--ld-surface);
    box-shadow: 0 7px 22px rgba(26, 39, 66, 0.045);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.ld-subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(26, 39, 66, 0.08);
}
.ld-subject-card--inactive {
    background: #fbfcfd;
    opacity: 0.82;
}
.ld-subject-card--selected {
    border-color: #91a4d5;
    box-shadow: 0 0 0 3px rgba(64, 81, 137, 0.1), 0 12px 28px rgba(26, 39, 66, 0.08);
}
.ld-subject-card__accent {
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
}
.ld-subject-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 0.95rem 0.25rem;
}
.ld-subject-card__identity {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.ld-subject-card__mark {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 11px;
    font-size: 1.05rem;
}
.ld-subject-card__body {
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    padding: 0.25rem 0.95rem 0.82rem;
}
.ld-subject-card__body h3 {
    margin: 0.35rem 0 0.2rem;
    color: var(--ld-ink);
    font-size: 0.9rem;
    font-weight: 760;
}
.ld-subject-card__technical {
    display: block;
    overflow: hidden;
    margin: -0.05rem 0 0.4rem;
    color: #8993a2;
    font-size: 0.66rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-subject-card__codes {
    display: flex;
    flex-wrap: wrap;
    gap: 0.32rem;
}
.ld-subject-card__codes span {
    padding: 0.18rem 0.38rem;
    border-radius: 5px;
    background: #f3f5f8;
    color: #697586;
    font-size: 0.7rem;
    font-weight: 650;
}
.ld-subject-card__body p {
    min-height: 38px;
    margin: 0.68rem 0;
    color: #697587;
    font-size: 0.76rem;
    line-height: 1.5;
}
.ld-subject-card__aliases,
.ld-subject-card__levels {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.ld-subject-card__aliases {
    margin: -0.15rem 0 0.65rem;
}
.ld-subject-card__aliases span,
.ld-subject-card__levels span {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    gap: 0.2rem;
    overflow: hidden;
    padding: 0.18rem 0.38rem;
    border-radius: 6px;
    background: #eef6f3;
    color: #397261;
    font-size: 0.64rem;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-subject-card__levels {
    margin-top: 0.65rem;
}
.ld-subject-card__levels span {
    background: #eef2fb;
    color: #4d5f91;
}
.ld-subject-card__meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin: auto 0 0;
    padding-top: 0.68rem;
    border-top: 1px solid #edf0f4;
}
.ld-subject-card__meta div {
    min-width: 0;
}
.ld-subject-card__meta dt {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #8993a2;
    font-size: 0.68rem;
    font-weight: 650;
}
.ld-subject-card__meta dd {
    overflow: hidden;
    margin: 0.12rem 0 0;
    color: #475467;
    font-size: 0.75rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ld-subject-card__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.32rem;
    padding: 0.7rem 0.95rem;
    border-top: 1px solid #edf0f4;
    background: #fbfcfe;
}
.ld-subject-card__actions .btn {
    font-size: 0.72rem;
}
.ld-form {
    display: grid;
    gap: 1rem;
}
.ld-form__intro {
    display: flex;
    align-items: center;
    gap: 0.72rem;
    padding: 0.72rem 0.8rem;
    border: 1px solid #dfe5ef;
    border-radius: 12px;
    background: #f8fafc;
}
.ld-form__intro-icon {
    display: grid;
    flex: 0 0 38px;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    font-size: 1.08rem;
}
.ld-form__intro strong {
    display: block;
    color: #344054;
    font-size: 0.8rem;
}
.ld-form__intro p {
    margin: 0.14rem 0 0;
    color: #7b8797;
    font-size: 0.72rem;
}
.form-label {
    margin-bottom: 0.32rem;
    color: #536174;
    font-size: 0.72rem;
    font-weight: 700;
}
.form-label > span {
    color: #c34c58;
}
.ld-color-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.ld-color-control .form-control {
    width: 68px;
    padding: 0.2rem;
}
.ld-color-control code {
    color: #697586;
    font-size: 0.7rem;
}
.ld-field-help {
    display: block;
    margin-top: 0.28rem;
    color: #8993a2;
    font-size: 0.66rem;
    line-height: 1.4;
}
.ld-education-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    padding: 0.7rem 0.75rem;
    border: 1px solid var(--ld-line);
    border-radius: 10px;
    background: #fbfcfe;
    color: #475467;
    font-size: 0.74rem;
}
.ld-governance-note {
    display: flex;
    align-items: flex-start;
    gap: 0.48rem;
    padding: 0.62rem 0.7rem;
    color: #5f6c7e;
    font-size: 0.72rem;
    line-height: 1.45;
}
.ld-governance-note i {
    color: var(--ld-primary);
    font-size: 1rem;
}
.ld-availability-control {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.7rem 0.8rem;
    border: 1px solid var(--ld-line);
    border-radius: 10px;
}
.ld-availability-control strong,
.ld-availability-control div > span {
    display: block;
}
.ld-availability-control strong {
    color: #344054;
    font-size: 0.78rem;
}
.ld-availability-control div > span {
    margin-top: 0.1rem;
    color: #7d8998;
    font-size: 0.72rem;
}
.ld-form__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.45rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--ld-line);
}
:global(.ld-external-modal) {
    padding: 0 !important;
}
.ld-external-hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.8rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid #e1e7f0;
    background: linear-gradient(135deg, #f4f7ff, #fbfcff);
}
.ld-external-hero > span {
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    border-radius: 13px;
    background: #e4eafa;
    color: var(--ld-primary);
    font-size: 1.25rem;
}
.ld-external-hero strong {
    color: var(--ld-ink);
    font-size: 0.9rem;
}
.ld-external-hero p {
    margin: 0.18rem 0 0;
    color: var(--ld-muted);
    font-size: 0.72rem;
}
.ld-external-hero dl {
    display: grid;
    grid-template-columns: repeat(4, minmax(68px, 1fr));
    gap: 0.38rem;
    margin: 0;
}
.ld-external-hero dl div {
    padding: 0.42rem 0.5rem;
    border: 1px solid #dce4f2;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.78);
    text-align: center;
}
.ld-external-hero dt {
    color: #8993a2;
    font-size: 0.58rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.ld-external-hero dd {
    margin: 0.06rem 0 0;
    color: var(--ld-primary);
    font-size: 0.86rem;
    font-weight: 800;
}
.ld-external-toolbar {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) 220px 160px auto;
    gap: 0.5rem;
    padding: 0.72rem 1rem;
    border-bottom: 1px solid #e5eaf1;
    background: #fff;
}
.ld-external-toolbar .form-control,
.ld-external-toolbar .form-select,
.ld-external-toolbar .btn {
    min-height: 38px;
    font-size: 0.72rem;
}
.ld-external-toolbar .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.28rem;
}
.ld-external-table-wrap {
    max-height: min(58vh, 650px);
    overflow: auto;
}
.ld-external-table {
    min-width: 980px;
    font-size: 0.74rem;
}
.ld-external-table th {
    position: sticky;
    z-index: 2;
    top: 0;
    padding: 0.62rem 0.8rem;
    border-bottom-color: #dfe5ee;
    background: #f7f9fc;
    color: #697586;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.045em;
    text-transform: uppercase;
}
.ld-external-table td {
    padding: 0.62rem 0.8rem;
    border-color: #edf0f4;
}
.ld-external-table td:first-child {
    width: 30%;
}
.ld-external-table td:nth-child(2) {
    width: 23%;
}
.ld-external-table td:nth-child(3) {
    width: 34%;
}
.ld-external-table td > strong,
.ld-external-table td > small {
    display: block;
}
.ld-external-table td > strong {
    color: #344054;
    font-size: 0.74rem;
}
.ld-external-table td > small {
    margin-top: 0.15rem;
    color: #8a94a2;
    font-size: 0.64rem;
}
.ld-scope-chip,
.ld-mapping-status {
    display: inline-flex;
    padding: 0.24rem 0.44rem;
    border-radius: 999px;
    background: #eef2f7;
    color: #596579;
    font-size: 0.64rem;
    font-weight: 700;
}
.ld-mapping-status.is-mapped {
    background: #e8f6ef;
    color: #287359;
}
.ld-mapping-status.is-ready {
    background: #eaf0ff;
    color: #405189;
}
.ld-mapping-status.is-suggested {
    background: #fff5df;
    color: #916315;
}
.ld-suggestion-link {
    margin-top: 0.28rem;
    padding: 0;
    border: 0;
    background: none;
    color: #5267a5;
    font-size: 0.64rem;
    text-align: left;
}
.ld-suggestion-link:hover {
    text-decoration: underline;
}
.ld-external-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    border-top: 1px solid #e8ecf2;
    background: #fff;
    color: #697586;
    font-size: 0.7rem;
}
.ld-external-pagination > div {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.ld-external-pagination .btn {
    display: inline-flex;
    min-height: 34px;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.68rem;
}
.ld-external-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.8rem 1rem;
    border-top: 1px solid #e2e7ef;
    background: #fbfcfe;
    color: #697586;
    font-size: 0.72rem;
}
.ld-external-footer > div {
    display: flex;
    gap: 0.45rem;
}
.ld-external-footer .btn {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
}
@media (max-width: 1100px) {
    .ld-subject-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .ld-import-readiness {
        grid-template-columns: auto minmax(0, 1fr) auto;
    }
    .ld-import-readiness__metrics {
        display: none;
    }
    .ld-commandbar {
        align-items: stretch;
        flex-wrap: wrap;
    }
    .ld-commandbar__search {
        flex-basis: 100%;
        max-width: none;
    }
    .ld-external-hero {
        grid-template-columns: auto minmax(0, 1fr);
    }
    .ld-external-hero dl {
        grid-column: 1 / -1;
    }
    .ld-external-toolbar {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 700px) {
    .ld-section-head {
        align-items: flex-start;
    }
    .ld-section-head__icon {
        display: none;
    }
    .ld-primary-action span {
        display: none;
    }
    .ld-primary-action {
        width: 36px;
        padding-inline: 0;
    }
    .ld-commandbar {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-commandbar__search {
        max-width: none;
    }
    .ld-commandbar__summary {
        justify-content: space-between;
    }
    .ld-commandbar__filter {
        flex-basis: 100%;
    }
    .ld-import-readiness {
        grid-template-columns: minmax(0, 1fr) auto;
    }
    .ld-import-readiness__icon {
        display: none;
    }
    .ld-import-readiness .btn {
        width: 38px;
        padding-inline: 0;
        overflow: hidden;
        color: transparent;
    }
    .ld-import-readiness .btn i {
        color: var(--ld-primary);
    }
    .ld-bulkbar {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-bulkbar__count {
        padding-left: 0;
        border-left: 0;
    }
    .ld-bulkbar__actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        margin-left: 0;
    }
    .ld-external-hero {
        grid-template-columns: 1fr;
    }
    .ld-external-hero > span {
        display: none;
    }
    .ld-external-hero dl {
        grid-template-columns: repeat(2, 1fr);
    }
    .ld-external-toolbar {
        grid-template-columns: 1fr;
    }
    .ld-external-footer {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-external-pagination {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-external-pagination > div {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
    }
    .ld-external-pagination .btn {
        justify-content: center;
    }
    .ld-external-footer > div {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
    .ld-subject-grid {
        grid-template-columns: 1fr;
    }
    .ld-subject-card {
        min-height: 0;
    }
    .ld-form__actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
@media (prefers-reduced-motion: reduce) {
    .ld-subject-card {
        transition: none;
    }
    .ld-subject-card:hover {
        transform: none;
    }
}
</style>
