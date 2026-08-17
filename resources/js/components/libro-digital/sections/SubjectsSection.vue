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
const search = ref("");
const showForm = ref(false);
const saving = ref(false);
const editing = ref(null);
let controller = null;
const form = reactive({
    name: "",
    code: "",
    area: "",
    color: "#405189",
    active: true,
});
const canManage = computed(() =>
    Boolean(props.capabilities.can_manage_subject_catalog)
);
const filtered = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase("es");
    return items.value.filter(
        (item) =>
            !needle ||
            [item.name, item.code, item.official_code, item.area]
                .filter(Boolean)
                .join(" ")
                .toLocaleLowerCase("es")
                .includes(needle)
    );
});
const load = async () => {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;
    try {
        items.value = payloadItems(
            await libroDigitalApi.subjects(
                { school_id: props.context.school_id, per_page: 250 },
                controller.signal
            )
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
onBeforeUnmount(() => controller?.abort());
const openCreate = () => {
    editing.value = null;
    Object.assign(form, {
        name: "",
        code: "",
        area: "",
        color: "#405189",
        active: true,
    });
    showForm.value = true;
};
const openEdit = (item) => {
    editing.value = item;
    Object.assign(form, {
        name: item.name || "",
        code: item.code || "",
        area: item.area || "",
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
            <BButton
                v-if="canManage"
                type="button"
                size="sm"
                variant="primary"
                class="ld-primary-action"
                @click="openCreate"
                ><i class="bx bx-plus" aria-hidden="true"></i
                ><span>Nueva asignatura</span></BButton
            >
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
                    :class="{ 'ld-subject-card--inactive': !item.active }"
                >
                    <span
                        class="ld-subject-card__accent"
                        :style="{ backgroundColor: item.color || '#405189' }"
                        aria-hidden="true"
                    ></span>
                    <div class="ld-subject-card__top">
                        <span
                            class="ld-subject-card__mark"
                            :style="{
                                color: item.color || '#405189',
                                backgroundColor: `${item.color || '#405189'}14`,
                            }"
                            aria-hidden="true"
                            ><i class="bx bx-book"></i
                        ></span>
                        <LibroDigitalStatusBadge
                            :status="item.active ? 'active' : 'cancelled'"
                            :label="item.active ? 'Activa' : 'Inactiva'"
                        />
                    </div>
                    <div class="ld-subject-card__body">
                        <h3>{{ item.name }}</h3>
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
                                <dd>{{ item.type || "Común" }}</dd>
                            </div>
                        </dl>
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
                            >Nombre <span aria-hidden="true">*</span></label
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
                </div>
                <BAlert show variant="light" class="ld-governance-note mb-0"
                    ><i class="bx bx-shield-quarter" aria-hidden="true"></i
                    ><span
                        >Los códigos oficiales, el tipo curricular y su
                        descripción normativa pertenecen al catálogo global
                        validado; esta ficha no los modifica.</span
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
.ld-commandbar__search > label {
    display: block;
    margin-bottom: 0.28rem;
    color: #596579;
    font-size: 0.72rem;
    font-weight: 750;
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
@media (max-width: 1100px) {
    .ld-subject-grid {
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
