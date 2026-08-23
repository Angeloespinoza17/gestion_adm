<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import LibroDigitalStatePanel from "../LibroDigitalStatePanel.vue";
import LibroDigitalStatusBadge from "../LibroDigitalStatusBadge.vue";
import {
    bookLabel,
    confirmAction,
    contextParams,
    errorMessage,
    formatDate,
    hasCapability,
    humanize,
    payloadItems,
    payloadMeta,
    showError,
    showSuccess,
} from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    catalogs: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    refreshToken: { type: Number, default: 0 },
});
const emit = defineEmits(["select-book", "catalog-changed"]);

const loading = ref(false);
const error = ref(null);
const items = ref([]);
const search = ref("");
const educationType = ref("");
const statusFilter = ref("");
const selected = ref(new Set());
const bulkOpening = ref(false);
const showForm = ref(false);
const saving = ref(false);
const editing = ref(null);
const form = reactive({
    school_id: null,
    academic_year_id: null,
    course_section_id: null,
    schedule_subject_id: null,
    teacher_staff_id: null,
    name: "",
    normative_profile_id: null,
    modality: "regular",
    notes: "",
});
let controller = null;

const courses = computed(
    () => props.catalogs.course_sections || props.catalogs.courses || []
);
const subjects = computed(
    () => props.catalogs.subjects || props.catalogs.schedule_subjects || []
);
const teachers = computed(
    () => props.catalogs.teachers || props.catalogs.staff || []
);
const profiles = computed(
    () =>
        props.catalogs.regulatory_profiles ||
        props.catalogs.normative_profiles ||
        []
);
const canManageBooks = computed(() =>
    hasCapability(props.capabilities, "can_manage_books")
);
const scopeKey = computed(() => JSON.stringify(contextParams(props.context)));
const filtered = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase("es");
    return items.value.filter((item) => {
        const matchesSearch =
            !needle ||
            [
                bookLabel(item),
                item.status,
                item.course?.display_name,
                item.subject?.name,
                item.subject?.technical_name,
            ]
                .filter(Boolean)
                .join(" ")
                .toLocaleLowerCase("es")
                .includes(needle);
        const matchesEducation =
            !educationType.value ||
            item.course?.education_type === educationType.value;
        const matchesStatus =
            !statusFilter.value || item.status === statusFilter.value;

        return matchesSearch && matchesEducation && matchesStatus;
    });
});
const isActivatable = (item) =>
    ["draft", "pending_preflight"].includes(
        String(item.status || "").toLowerCase()
    );
const activatableFiltered = computed(() =>
    filtered.value.filter(isActivatable)
);
const selectedCount = computed(() => selected.value.size);
const allActivatableSelected = computed(
    () =>
        activatableFiltered.value.length > 0 &&
        activatableFiltered.value.every((item) => selected.value.has(item.id))
);

const resetForm = () =>
    Object.assign(form, {
        school_id: props.context.school_id,
        academic_year_id: props.context.academic_year_id,
        course_section_id: props.context.course_section_id,
        schedule_subject_id: props.context.schedule_subject_id,
        teacher_staff_id: null,
        name: "",
        normative_profile_id: null,
        modality: "regular",
        notes: "",
    });

const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        items.value = payloadItems(
            await libroDigitalApi.books(
                { ...contextParams(props.context), per_page: 200 },
                controller.signal
            )
        );
        const validIds = new Set(
            items.value.filter(isActivatable).map((item) => item.id)
        );
        selected.value = new Set(
            [...selected.value].filter((id) => validIds.has(id))
        );
    } catch (requestError) {
        if (requestError?.code !== "ERR_CANCELED") error.value = requestError;
    } finally {
        loading.value = false;
    }
};

watch([scopeKey, () => props.refreshToken], load, { immediate: true });
watch(scopeKey, () => {
    selected.value = new Set();
});
onBeforeUnmount(() => controller?.abort());

const openCreate = () => {
    editing.value = null;
    resetForm();
    showForm.value = true;
};
const openEdit = (item) => {
    editing.value = item;
    Object.assign(form, {
        school_id: item.school_id,
        academic_year_id: item.academic_year_id,
        course_section_id: item.course_section_id,
        schedule_subject_id: item.schedule_subject_id || item.subject_id,
        teacher_staff_id: item.teacher_staff_id || item.teacher_id,
        name: item.name || "",
        normative_profile_id:
            item.normative_profile_id || item.regulatory_profile_id,
        modality: item.modality || "regular",
        notes: item.notes || "",
    });
    showForm.value = true;
};

const save = async () => {
    saving.value = true;
    try {
        if (editing.value)
            await libroDigitalApi.updateBook(editing.value, { ...form });
        else await libroDigitalApi.createBook({ ...form });
        showForm.value = false;
        await load();
        emit("catalog-changed");
        await showSuccess(editing.value ? "Libro actualizado" : "Libro creado");
    } catch (requestError) {
        await showError(requestError, "No se pudo guardar el libro");
        if (requestError.isConflict) await load();
    } finally {
        saving.value = false;
    }
};

const runAction = async (item, action) => {
    const labels = {
        preflight: [
            "Ejecutar preflight",
            "Se validará configuración, nómina y consistencia antes de operar.",
            "Validar",
        ],
        open: [
            "Abrir libro",
            "El libro quedará habilitado para la operación autorizada.",
            "Abrir",
        ],
        close: [
            "Cerrar libro",
            "Los registros cerrados no podrán editarse directamente.",
            "Cerrar",
        ],
        reopen: [
            "Solicitar reapertura",
            "La reapertura exige autorización, motivo y trazabilidad.",
            "Continuar",
        ],
    };
    const [title, text, confirmText] = labels[action];
    const confirmation = await confirmAction({ title, text, confirmText });
    if (!confirmation.isConfirmed) return;

    let reason = null;
    if (action === "reopen") {
        const reasonResult = await import("sweetalert2").then(
            ({ default: Swal }) =>
                Swal.fire({
                    title: "Motivo de reapertura",
                    input: "textarea",
                    inputLabel: "Fundamento obligatorio",
                    showCancelButton: true,
                    confirmButtonText: "Solicitar",
                    inputValidator: (value) =>
                        !value?.trim() ? "Ingresa un motivo." : undefined,
                })
        );
        if (!reasonResult.isConfirmed) return;
        reason = reasonResult.value.trim();
    }

    try {
        await libroDigitalApi.bookAction(
            item,
            action,
            reason ? { reason } : {}
        );
        await load();
        await showSuccess(
            action === "preflight"
                ? "Validación completada"
                : "Estado actualizado"
        );
    } catch (requestError) {
        await showError(requestError);
        if (requestError.isConflict) await load();
    }
};

const toggleSelected = (item) => {
    if (!isActivatable(item)) return;
    const next = new Set(selected.value);
    if (next.has(item.id)) next.delete(item.id);
    else next.add(item.id);
    selected.value = next;
};
const toggleAllActivatable = () => {
    const next = new Set(selected.value);
    if (allActivatableSelected.value)
        activatableFiltered.value.forEach((item) => next.delete(item.id));
    else activatableFiltered.value.forEach((item) => next.add(item.id));
    selected.value = next;
};
const failureSummary = (failedItems) => {
    const grouped = new Map();
    failedItems.forEach((item) => {
        const blockers = Array.isArray(item.details)
            ? item.details.filter(
                  (check) =>
                      check?.required !== false &&
                      check?.passed === false
              )
            : [];
        const cause = blockers.length
            ? blockers
                  .map(
                      (check) =>
                          check.label || check.remediation || check.code
                  )
                  .filter(Boolean)
                  .join(" y ")
            : item.message || "Revisión requerida";
        const names = grouped.get(cause) || [];
        names.push(item.display_name || `Libro #${item.id}`);
        grouped.set(cause, names);
    });

    return [...grouped.entries()]
        .slice(0, 5)
        .map(([cause, names]) => {
            const examples = names.slice(0, 2).join(", ");
            const more = names.length > 2 ? ` y ${names.length - 2} más` : "";
            return `• ${names.length} libro${names.length === 1 ? "" : "s"}: ${cause}.\n  ${examples}${more}`;
        })
        .join("\n");
};
const runBulkOpen = async () => {
    if (!selectedCount.value) return;
    const confirmation = await confirmAction({
        title: `Activar ${selectedCount.value} libros`,
        text: "Se validarán cumplimiento y nómina. Los libros sin docente también se abrirán, quedarán identificados como pendientes de asignación y no podrán registrar clases ni firmas hasta completar ese dato.",
        confirmText: "Validar y activar",
    });
    if (!confirmation.isConfirmed) return;
    bulkOpening.value = true;
    try {
        const selectedBooks = items.value.filter((item) =>
            selected.value.has(item.id)
        );
        const response = await libroDigitalApi.bulkOpenBooks({
            school_id: props.context.school_id,
            academic_year_id: props.context.academic_year_id || null,
            allow_unassigned_teacher: true,
            books: selectedBooks.map((item) => ({
                id: item.id,
                lock_version: item.lock_version,
            })),
        });
        const meta = payloadMeta(response);
        selected.value = new Set();
        await load();
        emit("catalog-changed");
        if (meta.failed) {
            const failed = failureSummary(
                payloadItems(response).filter(
                    (item) => item.result === "failed"
                )
            );
            const { default: Swal } = await import("sweetalert2");
            await Swal.fire({
                icon: meta.opened ? "warning" : "error",
                title: meta.opened
                    ? "Activación parcialmente completada"
                    : "No se activaron libros",
                text: `${meta.opened || 0} activados y ${meta.failed} pendientes de corrección.\n\n${failed}`,
                confirmButtonText: "Entendido",
                width: "42rem",
            });
        } else if (meta.opened_without_teacher) {
            const { default: Swal } = await import("sweetalert2");
            await Swal.fire({
                icon: "warning",
                title: "Libros activados con docente pendiente",
                text: `${meta.opened || 0} libros quedaron abiertos. ${meta.opened_without_teacher} requieren asignar un docente antes de registrar clases, evaluaciones o firmas.`,
                confirmButtonText: "Entendido",
                width: "40rem",
            });
        } else {
            await showSuccess(
                "Libros activados",
                `${meta.opened || 0} libros superaron el preflight y quedaron abiertos.`
            );
        }
    } catch (requestError) {
        await showError(requestError, "No se pudo ejecutar la activación masiva");
        if (requestError.isConflict) await load();
    } finally {
        bulkOpening.value = false;
    }
};

const availableActions = (item) => {
    const status = String(item.status || "draft").toLowerCase();
    return {
        edit: ["draft", "pending_preflight"].includes(status),
        preflight: ["draft", "pending_preflight"].includes(status),
        open: status === "pending_preflight",
        close: ["open", "temporarily_locked"].includes(status),
        reopen: ["closed", "cerrado"].includes(status),
    };
};

const bookControlActions = (item) => {
    const available = availableActions(item);
    return [
        {
            key: "preflight",
            visible: available.preflight,
            icon: "bx-check-shield",
            tone: "info",
            title: "Ejecutar preflight",
            description: "Valida nómina, docente y requisitos normativos.",
        },
        {
            key: "open",
            visible: available.open,
            icon: "bx-lock-open-alt",
            tone: "success",
            title: "Abrir libro",
            description: "Habilita sesiones, asistencia y leccionario.",
        },
        {
            key: "close",
            visible: available.close,
            icon: "bx-lock-alt",
            tone: "danger",
            title: "Cerrar libro",
            description: "Protege los registros y detiene la edición directa.",
        },
        {
            key: "reopen",
            visible: available.reopen,
            icon: "bx-revision",
            tone: "warning",
            title: "Solicitar reapertura",
            description: "Inicia una solicitud trazable con fundamento.",
        },
    ].filter((action) => action.visible);
};
</script>

<template>
    <section
        class="ld-section ld-books"
        aria-labelledby="lcd-books-title"
        :aria-busy="loading"
    >
        <header class="ld-section-head">
            <div class="ld-section-head__identity">
                <span class="ld-section-head__icon" aria-hidden="true"
                    ><i class="bx bx-book-bookmark"></i
                ></span>
                <div>
                    <span class="ld-eyebrow">Estructura académica</span>
                    <h2 id="lcd-books-title">Libros y cursos</h2>
                    <p>
                        Administra libros por año, curso y asignatura sin
                        alterar matrículas históricas.
                    </p>
                </div>
            </div>
            <BButton
                v-if="canManageBooks"
                type="button"
                size="sm"
                variant="primary"
                class="ld-primary-action"
                aria-label="Nuevo libro"
                title="Nuevo libro"
                @click="openCreate"
            >
                <i class="bx bx-plus" aria-hidden="true"></i
                ><span>Nuevo libro</span>
            </BButton>
        </header>

        <div class="ld-commandbar" role="search">
            <div class="ld-commandbar__search">
                <label for="lcd-book-search">Buscar en libros</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" aria-hidden="true"
                        ><i class="bx bx-search"></i
                    ></span>
                    <BFormInput
                        id="lcd-book-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        placeholder="Curso, asignatura, código o estado"
                    />
                    <BButton
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
                <label for="lcd-book-education">Tipo de enseñanza</label>
                <BFormSelect id="lcd-book-education" v-model="educationType" size="sm">
                    <option value="">Todos los niveles</option>
                    <option value="parvularia">Educación Parvularia</option>
                    <option value="basica">Enseñanza Básica</option>
                    <option value="media">Enseñanza Media</option>
                </BFormSelect>
            </div>
            <div class="ld-commandbar__filter">
                <label for="lcd-book-status">Estado del libro</label>
                <BFormSelect id="lcd-book-status" v-model="statusFilter" size="sm">
                    <option value="">Todos los estados</option>
                    <option value="draft">Borrador</option>
                    <option value="pending_preflight">Pendiente de preflight</option>
                    <option value="open">Abierto</option>
                    <option value="temporarily_locked">Bloqueado temporalmente</option>
                    <option value="closed">Cerrado</option>
                </BFormSelect>
            </div>
            <div class="ld-commandbar__summary" aria-live="polite">
                <span class="ld-result-count"
                    ><strong>{{ filtered.length }}</strong> de
                    {{ items.length }} libros</span
                >
                <span v-if="search" class="ld-filter-chip"
                    ><i class="bx bx-filter-alt" aria-hidden="true"></i>Filtro
                    activo</span
                >
            </div>
        </div>

        <div v-if="canManageBooks && items.length" class="ld-bulk-open">
            <div class="ld-bulk-open__identity">
                <span aria-hidden="true"><i class="bx bx-list-check"></i></span>
                <div>
                    <strong>Activación masiva con preflight</strong>
                    <small>Selecciona borradores o libros validados; cada uno conserva su resultado y auditoría.</small>
                </div>
            </div>
            <BFormCheckbox
                :model-value="allActivatableSelected"
                :indeterminate="selectedCount > 0 && !allActivatableSelected"
                @update:model-value="toggleAllActivatable"
            >
                Seleccionar {{ activatableFiltered.length }} activables
            </BFormCheckbox>
            <span class="ld-bulk-open__count"><strong>{{ selectedCount }}</strong> seleccionados</span>
            <BButton
                type="button"
                size="sm"
                variant="success"
                :disabled="!selectedCount || bulkOpening"
                @click="runBulkOpen"
            >
                <span v-if="bulkOpening" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <i v-else class="bx bx-lock-open-alt" aria-hidden="true"></i>
                {{ bulkOpening ? "Activando" : "Activar selección" }}
            </BButton>
        </div>

        <LibroDigitalStatePanel
            v-if="loading && !items.length"
            state="loading"
            title="Cargando libros"
            message="Consultando estructura académica y estados."
        />
        <LibroDigitalStatePanel
            v-else-if="error && !items.length"
            state="error"
            title="No se pudieron cargar los libros"
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
            >
                <i class="bx bx-error-circle" aria-hidden="true"></i
                ><span
                    >No fue posible actualizar el listado; se conserva la última
                    lectura visible.</span
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
            <BCard class="border-0 ld-surface ld-table-card" body-class="p-0">
                <header class="ld-panel-head">
                    <div class="ld-panel-head__title">
                        <span class="ld-panel-head__icon" aria-hidden="true"
                            ><i class="bx bx-book-content"></i
                        ></span>
                        <div>
                            <h3>Registro de libros</h3>
                            <p>
                                Estado operativo, perfil normativo y actividad
                                reciente
                            </p>
                        </div>
                    </div>
                    <span class="ld-count-badge">{{ filtered.length }}</span>
                </header>
                <div
                    v-if="filtered.length"
                    class="table-responsive ld-table-wrap"
                >
                    <table
                        class="table table-hover align-middle mb-0 ld-data-table"
                    >
                        <caption class="visually-hidden">
                            Libros digitales disponibles para el contexto
                            seleccionado
                        </caption>
                        <thead>
                            <tr>
                                <th v-if="canManageBooks" scope="col" class="ld-select-column">
                                    <BFormCheckbox
                                        :model-value="allActivatableSelected"
                                        :indeterminate="selectedCount > 0 && !allActivatableSelected"
                                        aria-label="Seleccionar libros activables visibles"
                                        @update:model-value="toggleAllActivatable"
                                    />
                                </th>
                                <th scope="col">Libro</th>
                                <th scope="col">Año / perfil</th>
                                <th scope="col">Docente</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Actividad</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in filtered"
                                :key="item.id"
                                :class="{ 'ld-book-row--selected': selected.has(item.id) }"
                            >
                                <td v-if="canManageBooks" class="ld-select-column">
                                    <BFormCheckbox
                                        :model-value="selected.has(item.id)"
                                        :disabled="!isActivatable(item)"
                                        :aria-label="isActivatable(item) ? `Seleccionar ${bookLabel(item)}` : `${bookLabel(item)} no está disponible para activación masiva`"
                                        @update:model-value="toggleSelected(item)"
                                    />
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="ld-record-link"
                                        :aria-label="`Abrir ${bookLabel(item)}`"
                                        @click="emit('select-book', item)"
                                    >
                                        <span
                                            class="ld-record-link__icon"
                                            aria-hidden="true"
                                            ><i
                                                class="bx bx-book-open"
                                            ></i></span
                                        ><span
                                            ><strong>{{
                                                bookLabel(item)
                                            }}</strong
                                            ><small>{{
                                                item.code || `LCD-${item.id}`
                                            }}</small></span
                                        >
                                    </button>
                                </td>
                                <td>
                                    <div class="ld-cell-primary">
                                        <strong>{{
                                            item.academic_year?.name ||
                                            item.academic_year?.year ||
                                            item.academic_year_label ||
                                            "—"
                                        }}</strong
                                        ><small
                                            ><i
                                                class="bx bx-shield-quarter"
                                                aria-hidden="true"
                                            ></i
                                            >{{
                                                item.regulatory_profile?.name ||
                                                item.normative_profile?.name ||
                                                "Perfil pendiente"
                                            }}</small
                                        >
                                        <small class="ld-education-tag">
                                            <i class="bx bx-layer" aria-hidden="true"></i>
                                            {{ item.course?.education_type === "basica" ? "Enseñanza Básica" : item.course?.education_type === "media" ? "Enseñanza Media" : item.course?.education_type === "parvularia" ? "Parvularia" : "Nivel no definido" }}
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="ld-teacher"
                                        ><i
                                            class="bx bx-user"
                                            aria-hidden="true"
                                        ></i
                                        >{{
                                            item.teacher?.name ||
                                            item.teacher_name ||
                                            "Sin asignar"
                                        }}</span
                                    >
                                </td>
                                <td>
                                    <LibroDigitalStatusBadge
                                        :status="item.status"
                                    />
                                </td>
                                <td>
                                    <div class="ld-cell-primary">
                                        <strong
                                            >{{
                                                item.sessions_count ?? 0
                                            }}
                                            sesiones</strong
                                        ><small
                                            ><i
                                                class="bx bx-history"
                                                aria-hidden="true"
                                            ></i
                                            >Actualizado
                                            {{
                                                formatDate(item.updated_at)
                                            }}</small
                                        >
                                    </div>
                                </td>
                                <td>
                                    <div class="ld-row-actions">
                                        <BButton
                                            type="button"
                                            size="sm"
                                            variant="outline-primary"
                                            :aria-label="`Abrir ${bookLabel(
                                                item
                                            )}`"
                                            @click="emit('select-book', item)"
                                            ><i
                                                class="bx bx-right-arrow-alt"
                                                aria-hidden="true"
                                            ></i
                                            ><span>Abrir</span></BButton
                                        >
                                        <BButton
                                            v-if="
                                                canManageBooks &&
                                                availableActions(item).edit
                                            "
                                            type="button"
                                            size="sm"
                                            variant="outline-secondary"
                                            :aria-label="`Editar ${bookLabel(
                                                item
                                            )}`"
                                            @click="openEdit(item)"
                                            ><i
                                                class="bx bx-edit"
                                                aria-hidden="true"
                                            ></i
                                        ></BButton>
                                        <BDropdown
                                            v-if="canManageBooks"
                                            size="sm"
                                            variant="outline-secondary"
                                            container="body"
                                            boundary="viewport"
                                            strategy="fixed"
                                            end
                                            :offset="6"
                                            text="Control"
                                            :aria-label="`Control operativo de ${bookLabel(
                                                item
                                            )}`"
                                            toggle-class="ld-control-toggle"
                                            menu-class="ld-book-control-menu"
                                        >
                                            <li
                                                class="ld-book-control-menu__header"
                                                role="presentation"
                                            >
                                                <span>Control operativo</span>
                                                <strong>{{
                                                    humanize(item.status)
                                                }}</strong>
                                            </li>
                                            <BDropdownItem
                                                v-for="action in bookControlActions(
                                                    item
                                                )"
                                                :key="action.key"
                                                :link-class="[
                                                    'ld-book-control-action',
                                                    `is-${action.tone}`,
                                                ]"
                                                @click="
                                                    runAction(item, action.key)
                                                "
                                            >
                                                <span
                                                    class="ld-book-control-action__icon"
                                                    aria-hidden="true"
                                                >
                                                    <i
                                                        :class="[
                                                            'bx',
                                                            action.icon,
                                                        ]"
                                                    ></i>
                                                </span>
                                                <span
                                                    class="ld-book-control-action__copy"
                                                >
                                                    <strong>{{
                                                        action.title
                                                    }}</strong>
                                                    <small>{{
                                                        action.description
                                                    }}</small>
                                                </span>
                                                <i
                                                    class="bx bx-chevron-right ld-book-control-action__arrow"
                                                    aria-hidden="true"
                                                ></i>
                                            </BDropdownItem>
                                        </BDropdown>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <LibroDigitalStatePanel
                    v-else
                    compact
                    :title="search ? 'Sin coincidencias' : 'Sin libros'"
                    :message="
                        search
                            ? 'No encontramos libros para esa búsqueda. Prueba con otro curso, asignatura o estado.'
                            : 'Crea el primer libro para el contexto seleccionado.'
                    "
                />
            </BCard>
        </template>

        <BModal
            v-model="showForm"
            :title="editing ? 'Editar libro' : 'Nuevo libro digital'"
            size="lg"
            body-class="ld-modal-body"
            hide-footer
            no-close-on-backdrop
        >
            <form
                class="ld-form"
                aria-describedby="ld-book-form-help"
                @submit.prevent="save"
            >
                <div class="ld-form__intro">
                    <span class="ld-form__intro-icon" aria-hidden="true"
                        ><i class="bx bx-plus-circle"></i
                    ></span>
                    <div>
                        <strong>{{
                            editing
                                ? "Actualiza la configuración del libro"
                                : "Configura el contenedor académico"
                        }}</strong>
                        <p id="ld-book-form-help">
                            Los campos marcados son necesarios para resolver
                            nómina, horario y perfil normativo.
                        </p>
                    </div>
                </div>
                <fieldset class="ld-form__section">
                    <legend>Contexto académico</legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lcd-book-year" class="form-label"
                                >Año académico
                                <span aria-hidden="true">*</span></label
                            ><BFormSelect
                                id="lcd-book-year"
                                v-model="form.academic_year_id"
                                required
                                ><option :value="null">Seleccionar</option>
                                <option
                                    v-for="year in catalogs.academic_years ||
                                    []"
                                    :key="year.id"
                                    :value="year.id"
                                >
                                    {{ year.name || year.year }}
                                </option></BFormSelect
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="lcd-book-course" class="form-label"
                                >Curso <span aria-hidden="true">*</span></label
                            ><BFormSelect
                                id="lcd-book-course"
                                v-model="form.course_section_id"
                                required
                                ><option :value="null">Seleccionar</option>
                                <option
                                    v-for="course in courses"
                                    :key="course.id"
                                    :value="course.id"
                                >
                                    {{ course.display_name || course.name }}
                                </option></BFormSelect
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="lcd-book-subject" class="form-label"
                                >Asignatura
                                <span aria-hidden="true">*</span></label
                            ><BFormSelect
                                id="lcd-book-subject"
                                v-model="form.schedule_subject_id"
                                required
                                ><option :value="null">Seleccionar</option>
                                <option
                                    v-for="subject in subjects"
                                    :key="subject.id"
                                    :value="subject.id"
                                >
                                    {{ subject.name }}
                                </option></BFormSelect
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="lcd-book-teacher" class="form-label"
                                >Docente responsable
                                <span aria-hidden="true">*</span></label
                            ><BFormSelect
                                id="lcd-book-teacher"
                                v-model="form.teacher_staff_id"
                                required
                                ><option :value="null">Seleccionar</option>
                                <option
                                    v-for="teacher in teachers"
                                    :key="teacher.id"
                                    :value="teacher.id"
                                >
                                    {{ teacher.full_name || teacher.name }}
                                </option></BFormSelect
                            >
                        </div>
                    </div>
                </fieldset>
                <fieldset class="ld-form__section">
                    <legend>Configuración normativa</legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lcd-book-profile" class="form-label"
                                >Perfil normativo</label
                            ><BFormSelect
                                id="lcd-book-profile"
                                v-model="form.normative_profile_id"
                                ><option :value="null">
                                    Resolver automáticamente
                                </option>
                                <option
                                    v-for="profile in profiles"
                                    :key="profile.id"
                                    :value="profile.id"
                                >
                                    {{ profile.name }} · {{ profile.version }}
                                </option></BFormSelect
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="lcd-book-modality" class="form-label"
                                >Modalidad</label
                            ><BFormSelect
                                id="lcd-book-modality"
                                v-model="form.modality"
                                ><option value="regular">Regular</option>
                                <option value="special">Especial</option>
                                <option value="adult">
                                    Educación de personas jóvenes y adultas
                                </option>
                                <option value="parvularia">
                                    Parvularia
                                </option></BFormSelect
                            >
                        </div>
                        <div class="col-12">
                            <label for="lcd-book-name" class="form-label"
                                >Nombre complementario
                                <small>Opcional</small></label
                            ><BFormInput
                                id="lcd-book-name"
                                v-model.trim="form.name"
                                maxlength="150"
                                placeholder="Ej.: Taller integrado de ciencias"
                            />
                        </div>
                        <div class="col-12">
                            <label for="lcd-book-notes" class="form-label"
                                >Observaciones</label
                            ><BFormTextarea
                                id="lcd-book-notes"
                                v-model.trim="form.notes"
                                rows="3"
                                maxlength="1000"
                                placeholder="Antecedentes administrativos o pedagógicos relevantes"
                            />
                        </div>
                    </div>
                </fieldset>
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
                        >{{ saving ? "Guardando" : "Guardar libro" }}</BButton
                    >
                </div>
            </form>
        </BModal>
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
.ld-primary-action,
.ld-form__actions .btn {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    justify-content: center;
    gap: 0.38rem;
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
    max-width: 520px;
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
    flex: 0 1 205px;
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
.ld-bulk-open {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.7rem 0.8rem;
    border: 1px solid #cfe3da;
    border-radius: 14px;
    background:
        radial-gradient(circle at 90% 0, rgba(30, 126, 92, 0.08), transparent 34%),
        linear-gradient(135deg, #f8fcfa, #f2f9f6);
    color: #40554e;
}
.ld-bulk-open__identity {
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
    gap: 0.58rem;
}
.ld-bulk-open__identity > span {
    display: grid;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 10px;
    background: #dff1ea;
    color: #237357;
    font-size: 1rem;
}
.ld-bulk-open__identity strong,
.ld-bulk-open__identity small {
    display: block;
}
.ld-bulk-open__identity strong {
    color: #2e5447;
    font-size: 0.78rem;
}
.ld-bulk-open__identity small {
    margin-top: 0.12rem;
    color: #6c827a;
    font-size: 0.67rem;
}
.ld-bulk-open .form-check {
    margin: 0;
    font-size: 0.72rem;
}
.ld-bulk-open__count {
    padding: 0.34rem 0.5rem;
    border: 1px solid #d4e5df;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.7);
    color: #6a7d76;
    font-size: 0.68rem;
}
.ld-bulk-open__count strong {
    color: #237357;
}
.ld-bulk-open .btn {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    gap: 0.32rem;
    font-size: 0.72rem;
    font-weight: 750;
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
}
.ld-panel-head__icon {
    display: grid;
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
.ld-table-wrap {
    position: relative;
    scrollbar-width: thin;
}
.ld-data-table {
    min-width: 900px;
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
    padding: 0.74rem 1rem;
    border-color: #edf0f4;
    color: #475467;
}
.ld-data-table .ld-select-column {
    width: 46px;
    min-width: 46px;
    padding-right: 0.35rem;
    text-align: center;
}
.ld-data-table .ld-select-column .form-check {
    display: inline-flex;
    margin: 0;
}
.ld-book-row--selected > td {
    background: #f5f8ff;
}
.ld-data-table th:last-child,
.ld-data-table td:last-child {
    position: sticky;
    z-index: 3;
    right: 0;
    min-width: 212px;
    background: var(--ld-surface);
    box-shadow: -10px 0 18px -17px rgba(23, 32, 51, 0.72);
}
.ld-data-table th:last-child {
    z-index: 4;
    background: #f7f9fc;
}
.ld-data-table tbody tr:hover td:last-child {
    background: #f8faff;
}
.ld-record-link {
    display: flex;
    align-items: center;
    gap: 0.52rem;
    min-width: 190px;
    padding: 0;
    border: 0;
    background: none;
    color: var(--ld-primary);
    text-align: left;
}
.ld-record-link__icon {
    display: grid;
    flex: 0 0 32px;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #eef2fb;
    font-size: 0.9rem;
}
.ld-record-link strong,
.ld-record-link small,
.ld-cell-primary strong,
.ld-cell-primary small {
    display: block;
}
.ld-cell-primary .ld-education-tag {
    margin-top: 0.28rem;
    color: #62729c;
}
.ld-record-link strong {
    font-size: 0.78rem;
}
.ld-record-link small,
.ld-cell-primary small {
    margin-top: 0.14rem;
    color: #7f8a99;
    font-size: 0.7rem;
}
.ld-cell-primary strong {
    color: #344054;
    font-size: 0.78rem;
}
.ld-cell-primary small {
    display: flex;
    align-items: center;
    gap: 0.24rem;
}
.ld-teacher {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
}
.ld-teacher i {
    color: #8290a3;
}
.ld-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.3rem;
    white-space: nowrap;
}
.ld-row-actions .btn {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    justify-content: center;
    gap: 0.28rem;
    font-size: 0.72rem;
}
.ld-row-actions :deep(.ld-control-toggle) {
    min-width: 112px;
    border-color: #b9c4d4;
    background: #fff;
    color: var(--ld-ink);
    font-weight: 750;
}
.ld-row-actions :deep(.ld-control-toggle:hover),
.ld-row-actions :deep(.ld-control-toggle[aria-expanded="true"]) {
    border-color: var(--ld-primary);
    background: #eef3fb;
    color: var(--ld-primary);
    box-shadow: 0 0 0 3px rgba(64, 81, 137, 0.12);
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
    background: #e9eefb;
    color: var(--ld-primary);
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
.ld-form__section {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}
.ld-form__section legend {
    float: none;
    width: auto;
    margin: 0 0 0.6rem;
    color: #344054;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.035em;
    text-transform: uppercase;
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
.form-label small {
    color: #8a94a2;
    font-size: 0.68rem;
    font-weight: 500;
}
.ld-form__actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.45rem;
    margin-top: 0.1rem;
    padding-top: 0.85rem;
    border-top: 1px solid var(--ld-line);
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
    .ld-bulk-open {
        align-items: stretch;
        flex-direction: column;
    }
    .ld-bulk-open__count {
        text-align: center;
    }
    .ld-bulk-open .btn {
        justify-content: center;
    }
    .ld-panel-head {
        padding: 0.75rem 0.8rem;
    }
    .ld-row-actions .btn span {
        display: none;
    }
    .ld-form__intro {
        align-items: flex-start;
    }
    .ld-form__actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<style>
.ld-book-control-menu.dropdown-menu {
    z-index: 1095;
    width: min(330px, calc(100vw - 24px));
    min-width: 290px;
    max-height: min(420px, calc(100vh - 24px)) !important;
    padding: 8px;
    overflow: auto;
    border: 1px solid #d7dfeb;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 18px 48px rgba(23, 32, 51, 0.2),
        0 3px 10px rgba(23, 32, 51, 0.08);
    animation: none !important;
}

/*
 * El tema global fuerza top: 100% y anima el transform de cada dropdown.
 * Este menú está teletransportado al body y Floating UI posiciona con un
 * transform propio, por lo que ambas reglas globales lo enviaban fuera de la
 * pantalla. Conservamos el cálculo dinámico y anulamos solo el top heredado.
 */
.ld-book-control-menu.dropdown-menu.show {
    top: 0 !important;
}

.ld-book-control-menu__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 5px;
    padding: 8px 10px 10px;
    border-bottom: 1px solid #e7ebf1;
}

.ld-book-control-menu__header > span {
    color: #667085;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.075em;
    text-transform: uppercase;
}

.ld-book-control-menu__header > strong {
    padding: 3px 7px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #f5f7fa;
    color: #344054;
    font-size: 0.68rem;
}

.ld-book-control-menu .ld-book-control-action.dropdown-item {
    display: grid;
    min-height: 64px;
    grid-template-columns: 38px minmax(0, 1fr) 18px;
    align-items: center;
    gap: 10px;
    padding: 8px 9px;
    border-radius: 10px;
    color: #344054;
    white-space: normal;
}

.ld-book-control-menu .ld-book-control-action.dropdown-item:hover,
.ld-book-control-menu .ld-book-control-action.dropdown-item:focus-visible {
    background: #f2f6fb;
    color: #172033;
}

.ld-book-control-action__icon {
    display: grid;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #eef3fb;
    color: #405189;
    font-size: 1.05rem;
    place-items: center;
}

.ld-book-control-action__copy {
    min-width: 0;
}

.ld-book-control-action__copy strong,
.ld-book-control-action__copy small {
    display: block;
}

.ld-book-control-action__copy strong {
    color: #253247;
    font-size: 0.76rem;
    font-weight: 800;
}

.ld-book-control-action__copy small {
    margin-top: 2px;
    color: #667085;
    font-size: 0.68rem;
    line-height: 1.38;
}

.ld-book-control-action__arrow {
    color: #98a2b3;
    font-size: 1rem;
}

.ld-book-control-action.is-success .ld-book-control-action__icon {
    background: #eaf8f2;
    color: #176149;
}

.ld-book-control-action.is-danger .ld-book-control-action__icon {
    background: #fff0f2;
    color: #a83445;
}

.ld-book-control-action.is-warning .ld-book-control-action__icon {
    background: #fff6e8;
    color: #8a5b13;
}

@media (max-width: 575.98px) {
    .ld-book-control-menu.dropdown-menu {
        width: calc(100vw - 20px);
        min-width: 0;
    }
}

[data-bs-theme="dark"] .ld-book-control-menu.dropdown-menu {
    border-color: #354158;
    background: #202b3e;
    box-shadow: 0 18px 48px rgba(0, 0, 0, 0.42);
}

[data-bs-theme="dark"] .ld-book-control-menu__header {
    border-color: #354158;
}

[data-bs-theme="dark"] .ld-book-control-menu__header > span,
[data-bs-theme="dark"] .ld-book-control-action__copy small {
    color: #aab5c6;
}

[data-bs-theme="dark"] .ld-book-control-menu__header > strong {
    border-color: #46536a;
    background: #2a364b;
    color: #e7edf6;
}

[data-bs-theme="dark"]
    .ld-book-control-menu
    .ld-book-control-action.dropdown-item {
    color: #e7edf6;
}

[data-bs-theme="dark"]
    .ld-book-control-menu
    .ld-book-control-action.dropdown-item:hover,
[data-bs-theme="dark"]
    .ld-book-control-menu
    .ld-book-control-action.dropdown-item:focus-visible {
    background: #2a364b;
}

[data-bs-theme="dark"] .ld-book-control-action__copy strong {
    color: #f3f6fb;
}
</style>
