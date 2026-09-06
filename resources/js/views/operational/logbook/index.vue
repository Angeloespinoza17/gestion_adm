<script>
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import OperationalWorkspaceHeader from "../../../components/operational/operational-workspace-header.vue";
import axios from "axios";

const emptyPagination = () => ({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
});

const titleSuggestionsByCategory = {
    general: ["Observación de la jornada", "Antecedente relevante"],
    management: ["Gestión realizada", "Tarea pendiente"],
    meeting: ["Acuerdo de reunión", "Compromiso de equipo"],
    follow_up: ["Seguimiento realizado", "Próximo seguimiento"],
    incident: ["Situación informada", "Incidente registrado"],
    improvement: ["Propuesta de mejora", "Idea para implementar"],
    other: ["Antecedente especial", "Coordinación realizada"],
};

const detailPrompts = [
    { value: "context", label: "Contexto", icon: "bx-map-alt", template: "Contexto:" },
    { value: "agreement", label: "Acuerdo", icon: "bx-check-circle", template: "Acuerdo:" },
    { value: "responsible", label: "Responsable", icon: "bx-user", template: "Responsable:" },
    { value: "pending", label: "Pendiente", icon: "bx-time-five", template: "Pendiente:" },
    { value: "next_step", label: "Próximo paso", icon: "bx-right-arrow-alt", template: "Próximo paso:" },
];

export default {
    components: { Layout, LoadingState, OperationalWorkspaceHeader },
    data() {
        return {
            loading: true,
            saving: false,
            error: "",
            success: "",
            entries: [],
            categories: [],
            staff: [],
            scope: {
                mode: "own",
                is_superadmin: false,
                can_create: false,
                privacy_note: "Tu bitácora se mantiene en un espacio interno y protegido.",
            },
            detailPrompts,
            summary: { total: 0, today: 0, staff: 0 },
            pagination: emptyPagination(),
            filters: {
                search: "",
                category: "",
                date_from: "",
                date_to: "",
                owner_user_id: "",
                per_page: 20,
                page: 1,
            },
            showForm: false,
            editingId: null,
            formErrors: {},
            form: this.emptyForm(),
            expandedEntries: [],
        };
    },
    computed: {
        isSuperAdmin() {
            return this.scope.is_superadmin;
        },
        hasFilters() {
            return Boolean(
                this.filters.search ||
                this.filters.category ||
                this.filters.date_from ||
                this.filters.date_to ||
                this.filters.owner_user_id
            );
        },
        headingTitle() {
            return this.isSuperAdmin ? "Bitácoras de funcionarios" : "Mi bitácora";
        },
        headingSubtitle() {
            return this.isSuperAdmin
                ? "Una línea de tiempo consolidada para consultar los registros de todos los funcionarios."
                : "Registra acuerdos, seguimientos, incidencias e ideas que quieras conservar como referencia.";
        },
        maxDateTime() {
            return this.toDateTimeInput(new Date());
        },
        titleSuggestions() {
            return titleSuggestionsByCategory[this.form.category] || titleSuggestionsByCategory.general;
        },
    },
    mounted() {
        this.loadEntries();
    },
    beforeUnmount() {
        document.body.classList.remove("modal-open");
    },
    methods: {
        emptyForm() {
            return {
                occurred_at: this.toDateTimeInput(new Date()),
                category: "general",
                custom_category: "",
                title: "",
                details: "",
            };
        },
        toDateTimeInput(value) {
            const date = value instanceof Date ? value : new Date(String(value || "").replace(" ", "T"));
            if (Number.isNaN(date.getTime())) return "";
            const pad = (part) => String(part).padStart(2, "0");
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        },
        cleanParams() {
            return Object.fromEntries(
                Object.entries(this.filters).filter(([, value]) => value !== "" && value !== null)
            );
        },
        async loadEntries(page = this.filters.page) {
            this.loading = true;
            this.error = "";
            this.filters.page = page;

            try {
                const { data } = await axios.get("/api/operational/logbook", {
                    params: this.cleanParams(),
                });
                this.entries = data.data || [];
                this.categories = data.categories || [];
                this.staff = data.staff || [];
                this.scope = { ...this.scope, ...(data.scope || {}) };
                this.summary = { ...this.summary, ...(data.summary || {}) };
                this.pagination = {
                    current_page: data.current_page || 1,
                    last_page: data.last_page || 1,
                    per_page: data.per_page || Number(this.filters.per_page),
                    total: data.total || 0,
                    from: data.from ?? null,
                    to: data.to ?? null,
                };
            } catch (error) {
                this.error = this.errorMessage(error, "No fue posible cargar la bitácora.");
            } finally {
                this.loading = false;
            }
        },
        applyFilters() {
            this.loadEntries(1);
        },
        resetFilters() {
            this.filters = {
                search: "",
                category: "",
                date_from: "",
                date_to: "",
                owner_user_id: "",
                per_page: 20,
                page: 1,
            };
            this.loadEntries(1);
        },
        openCreate() {
            this.editingId = null;
            this.form = this.emptyForm();
            this.formErrors = {};
            this.error = "";
            this.showForm = true;
            document.body.classList.add("modal-open");
        },
        openEdit(entry) {
            if (!entry.can_edit) return;
            this.editingId = entry.id;
            this.form = {
                occurred_at: this.toDateTimeInput(entry.occurred_at),
                category: entry.category,
                custom_category: entry.custom_category || "",
                title: entry.title,
                details: entry.details,
            };
            this.formErrors = {};
            this.error = "";
            this.showForm = true;
            document.body.classList.add("modal-open");
        },
        closeForm() {
            if (this.saving) return;
            this.showForm = false;
            this.formErrors = {};
            document.body.classList.remove("modal-open");
        },
        selectCategory(category) {
            this.form.category = category.value;
            if (category.value !== "other") this.form.custom_category = "";
        },
        selectTitleSuggestion(title) {
            this.form.title = title;
        },
        hasDetailPrompt(prompt) {
            return this.form.details.includes(prompt.template);
        },
        insertDetailPrompt(prompt) {
            if (!this.hasDetailPrompt(prompt)) {
                const currentDetails = this.form.details.trimEnd();
                const separator = currentDetails ? "\n\n" : "";
                this.form.details = `${currentDetails}${separator}${prompt.template} `;
            }

            this.$nextTick(() => {
                const textarea = this.$refs.detailsInput;
                if (!textarea) return;
                textarea.focus();
                if (typeof textarea.setSelectionRange === "function") {
                    const cursor = textarea.value.length;
                    textarea.setSelectionRange(cursor, cursor);
                }
            });
        },
        async saveEntry() {
            this.saving = true;
            this.formErrors = {};
            this.error = "";

            const payload = {
                ...this.form,
                occurred_at: this.form.occurred_at.replace("T", " "),
                custom_category: this.form.category === "other" ? this.form.custom_category : null,
            };

            try {
                const response = this.editingId
                    ? await axios.put(`/api/operational/logbook/${this.editingId}`, payload)
                    : await axios.post("/api/operational/logbook", payload);
                this.success = response.data.message || "Registro guardado.";
                this.showForm = false;
                document.body.classList.remove("modal-open");
                await this.loadEntries(this.editingId ? this.filters.page : 1);
                window.setTimeout(() => { this.success = ""; }, 3500);
            } catch (error) {
                if (error?.response?.status === 422) {
                    this.formErrors = error.response.data.errors || {};
                } else {
                    this.error = this.errorMessage(error, "No fue posible guardar el registro.");
                }
            } finally {
                this.saving = false;
            }
        },
        errorMessage(error, fallback) {
            return error?.response?.data?.message || fallback;
        },
        fieldError(field) {
            return this.formErrors[field]?.[0] || "";
        },
        categoryOption(value) {
            return this.categories.find((item) => item.value === value) || {
                value,
                label: value,
                icon: "bx-note",
            };
        },
        categoryClass(value) {
            return `logbook-category--${value || "general"}`;
        },
        initials(name) {
            return String(name || "F")
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => part[0])
                .join("")
                .toUpperCase();
        },
        formatDateTime(value) {
            if (!value) return "—";
            const date = new Date(String(value).replace(" ", "T"));
            if (Number.isNaN(date.getTime())) return value;
            return new Intl.DateTimeFormat("es-CL", {
                weekday: "short",
                day: "2-digit",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            }).format(date);
        },
        isExpanded(id) {
            return this.expandedEntries.includes(id);
        },
        toggleExpanded(id) {
            this.expandedEntries = this.isExpanded(id)
                ? this.expandedEntries.filter((entryId) => entryId !== id)
                : [...this.expandedEntries, id];
        },
        visibleDetails(entry) {
            if (this.isExpanded(entry.id) || entry.details.length <= 420) return entry.details;
            return `${entry.details.slice(0, 420).trim()}…`;
        },
    },
};
</script>

<template>
    <Layout>
        <main class="container-fluid py-3 operational-workspace staff-logbook">
            <OperationalWorkspaceHeader
                :title="headingTitle"
                :subtitle="headingSubtitle"
                icon="bx bx-notepad"
                section-label="Bitácora institucional"
                :show-tools="false"
            >
                <template #actions>
                    <button
                        v-if="scope.can_create"
                        type="button"
                        class="btn btn-light"
                        @click="openCreate"
                    >
                        <i class="bx bx-plus me-1"></i>Nueva entrada
                    </button>
                </template>
            </OperationalWorkspaceHeader>

            <div v-if="success" class="alert alert-success logbook-alert" role="status">
                <i class="bx bx-check-circle"></i>
                <span>{{ success }}</span>
            </div>
            <div v-if="error" class="alert alert-danger logbook-alert" role="alert">
                <i class="bx bx-error-circle"></i>
                <span>{{ error }}</span>
            </div>

            <section class="privacy-banner" :class="{ 'privacy-banner--super': isSuperAdmin }">
                <span class="privacy-banner__icon">
                    <i :class="isSuperAdmin ? 'bx bx-shield-quarter' : 'bx bx-lock-alt'"></i>
                </span>
                <div>
                    <strong>{{ isSuperAdmin ? 'Vista institucional protegida' : 'Espacio personal protegido' }}</strong>
                    <p>{{ scope.privacy_note }}</p>
                </div>
                <span class="privacy-banner__scope">
                    {{ isSuperAdmin ? 'Todos los funcionarios' : 'Solo mi bitácora' }}
                </span>
            </section>

            <section class="row g-3 mb-3" aria-label="Resumen de bitácora">
                <div class="col-12 col-sm-4">
                    <article class="op-metric-card op-tone-indigo">
                        <div class="op-metric-top">
                            <span class="op-metric-label">Registros encontrados</span>
                            <span class="op-metric-icon"><i class="bx bx-notepad"></i></span>
                        </div>
                        <div class="op-metric-value">{{ summary.total }}</div>
                        <div class="op-metric-note">Según los filtros seleccionados</div>
                    </article>
                </div>
                <div class="col-12 col-sm-4">
                    <article class="op-metric-card op-tone-sky">
                        <div class="op-metric-top">
                            <span class="op-metric-label">Registrados hoy</span>
                            <span class="op-metric-icon"><i class="bx bx-calendar-check"></i></span>
                        </div>
                        <div class="op-metric-value">{{ summary.today }}</div>
                        <div class="op-metric-note">Dentro de la consulta actual</div>
                    </article>
                </div>
                <div class="col-12 col-sm-4">
                    <article class="op-metric-card op-tone-emerald">
                        <div class="op-metric-top">
                            <span class="op-metric-label">{{ isSuperAdmin ? 'Funcionarios' : 'Privacidad' }}</span>
                            <span class="op-metric-icon"><i :class="isSuperAdmin ? 'bx bx-group' : 'bx bx-user-check'"></i></span>
                        </div>
                        <div class="op-metric-value">{{ isSuperAdmin ? summary.staff : 'Personal' }}</div>
                        <div class="op-metric-note">{{ isSuperAdmin ? 'Con registros en esta consulta' : 'Seguimiento institucional protegido' }}</div>
                    </article>
                </div>
            </section>

            <section class="card op-surface logbook-browser">
                <div class="card-body p-3 p-lg-4">
                    <form class="op-filter-panel logbook-filters" @submit.prevent="applyFilters">
                        <div class="logbook-filter-heading">
                            <div>
                                <span class="op-filter-label mb-1">Explorar registros</span>
                                <strong>Encuentra rápidamente un antecedente</strong>
                            </div>
                            <button
                                v-if="hasFilters"
                                type="button"
                                class="btn btn-sm btn-link text-decoration-none"
                                @click="resetFilters"
                            >
                                Limpiar filtros
                            </button>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-lg-4">
                                <label class="op-filter-label" for="logbook-search">Buscar en texto</label>
                                <div class="logbook-search">
                                    <i class="bx bx-search"></i>
                                    <input
                                        id="logbook-search"
                                        v-model="filters.search"
                                        type="search"
                                        class="form-control"
                                        placeholder="Título, detalle o funcionario"
                                    />
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-2">
                                <label class="op-filter-label" for="logbook-category">Categoría</label>
                                <select id="logbook-category" v-model="filters.category" class="form-select">
                                    <option value="">Todas</option>
                                    <option v-for="category in categories" :key="category.value" :value="category.value">
                                        {{ category.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="isSuperAdmin" class="col-12 col-sm-6 col-lg-2">
                                <label class="op-filter-label" for="logbook-owner">Funcionario</label>
                                <select id="logbook-owner" v-model="filters.owner_user_id" class="form-select">
                                    <option value="">Todos</option>
                                    <option v-for="person in staff" :key="person.id" :value="person.id">
                                        {{ person.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-4" :class="isSuperAdmin ? 'col-lg-1' : 'col-lg-2'">
                                <label class="op-filter-label" for="logbook-from">Desde</label>
                                <input id="logbook-from" v-model="filters.date_from" type="date" class="form-control" />
                            </div>
                            <div class="col-6 col-sm-4" :class="isSuperAdmin ? 'col-lg-1' : 'col-lg-2'">
                                <label class="op-filter-label" for="logbook-to">Hasta</label>
                                <input id="logbook-to" v-model="filters.date_to" type="date" class="form-control" />
                            </div>
                            <div class="col-12 col-sm-4 col-lg-2 d-grid">
                                <button type="submit" class="btn btn-primary logbook-filter-button">
                                    <i class="bx bx-filter-alt me-1"></i>Aplicar
                                </button>
                            </div>
                        </div>
                    </form>

                    <LoadingState
                        v-if="loading"
                        message="Cargando bitácora..."
                        helper="Protegiendo el alcance de la consulta"
                    />

                    <div v-else-if="!entries.length" class="logbook-empty">
                        <span><i class="bx bx-book-open"></i></span>
                        <h4>{{ hasFilters ? 'No hay coincidencias' : 'Tu bitácora está lista' }}</h4>
                        <p>
                            {{ hasFilters
                                ? 'Prueba cambiando o quitando alguno de los filtros.'
                                : 'Agrega tu primera entrada para conservar un antecedente relevante.'
                            }}
                        </p>
                        <button v-if="scope.can_create && !hasFilters" type="button" class="btn btn-primary" @click="openCreate">
                            <i class="bx bx-plus me-1"></i>Crear primera entrada
                        </button>
                    </div>

                    <div v-else class="logbook-timeline">
                        <article v-for="entry in entries" :key="entry.id" class="logbook-entry">
                            <div class="logbook-entry__rail">
                                <span :class="['logbook-entry__dot', categoryClass(entry.category)]">
                                    <i :class="`bx ${categoryOption(entry.category).icon}`"></i>
                                </span>
                            </div>
                            <div class="logbook-entry__card">
                                <div class="logbook-entry__topline">
                                    <div class="logbook-entry__badges">
                                        <span :class="['logbook-category', categoryClass(entry.category)]">
                                            {{ entry.category_label }}
                                        </span>
                                        <span v-if="entry.was_edited" class="logbook-edited">Editado</span>
                                    </div>
                                    <time :datetime="entry.occurred_at">{{ formatDateTime(entry.occurred_at) }}</time>
                                </div>

                                <h3>{{ entry.title }}</h3>
                                <p class="logbook-entry__details">{{ visibleDetails(entry) }}</p>
                                <button
                                    v-if="entry.details.length > 420"
                                    type="button"
                                    class="logbook-more"
                                    @click="toggleExpanded(entry.id)"
                                >
                                    {{ isExpanded(entry.id) ? 'Mostrar menos' : 'Leer registro completo' }}
                                </button>

                                <footer class="logbook-entry__footer">
                                    <div class="logbook-person">
                                        <span class="logbook-person__avatar">{{ initials(entry.owner.name) }}</span>
                                        <span>
                                            <strong>{{ entry.owner.name }}</strong>
                                            <small>{{ entry.owner.position || 'Funcionario/a' }}</small>
                                        </span>
                                    </div>
                                    <button
                                        v-if="entry.can_edit"
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        @click="openEdit(entry)"
                                    >
                                        <i class="bx bx-edit-alt me-1"></i>Corregir
                                    </button>
                                </footer>
                            </div>
                        </article>
                    </div>

                    <div v-if="!loading && pagination.last_page > 1" class="logbook-pagination">
                        <small>
                            Mostrando {{ pagination.from }}–{{ pagination.to }} de {{ pagination.total }}
                        </small>
                        <BPagination
                            v-model="filters.page"
                            :total-rows="pagination.total"
                            :per-page="pagination.per_page"
                            @update:model-value="loadEntries"
                        />
                    </div>
                </div>
            </section>

            <Teleport to="body">
                <div
                    v-if="showForm"
                    class="logbook-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="logbook-form-title"
                    @click.self="closeForm"
                >
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <form class="modal-content" @submit.prevent="saveEntry">
                        <div class="modal-header op-modal-header">
                            <div>
                                <span class="logbook-modal__eyebrow">Bitácora personal</span>
                                <h5 id="logbook-form-title" class="modal-title">
                                    {{ editingId ? 'Corregir entrada' : 'Registrar algo relevante' }}
                                </h5>
                            </div>
                            <button type="button" class="btn-close" aria-label="Cerrar" @click="closeForm"></button>
                        </div>
                        <div class="modal-body p-3 p-lg-4">
                            <div class="logbook-form-privacy">
                                <i class="bx bx-shield-quarter"></i>
                                <span>Registro interno para apoyar el seguimiento institucional.</span>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">¿Cómo quieres categorizarlo?</label>
                                <div class="category-picker">
                                    <button
                                        v-for="category in categories"
                                        :key="category.value"
                                        type="button"
                                        :class="['category-picker__item', { active: form.category === category.value }]"
                                        @click="selectCategory(category)"
                                    >
                                        <i :class="`bx ${category.icon}`"></i>
                                        <span>{{ category.label }}</span>
                                    </button>
                                </div>
                                <div v-if="fieldError('category')" class="invalid-feedback d-block">{{ fieldError('category') }}</div>
                            </div>

                            <div v-if="form.category === 'other'" class="mb-3">
                                <label for="logbook-custom-category" class="form-label">Nombre de la categoría</label>
                                <input
                                    id="logbook-custom-category"
                                    v-model="form.custom_category"
                                    type="text"
                                    maxlength="80"
                                    class="form-control"
                                    :class="{ 'is-invalid': fieldError('custom_category') }"
                                    placeholder="Ej.: Coordinación de actividad"
                                />
                                <div class="invalid-feedback">{{ fieldError('custom_category') }}</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-5">
                                    <label for="logbook-date" class="form-label">Fecha y hora del antecedente</label>
                                    <input
                                        id="logbook-date"
                                        v-model="form.occurred_at"
                                        type="datetime-local"
                                        :max="maxDateTime"
                                        class="form-control"
                                        :class="{ 'is-invalid': fieldError('occurred_at') }"
                                        required
                                    />
                                    <div class="invalid-feedback">{{ fieldError('occurred_at') }}</div>
                                </div>
                                <div class="col-12 col-md-7">
                                    <label for="logbook-title" class="form-label">Título breve</label>
                                    <input
                                        id="logbook-title"
                                        v-model="form.title"
                                        type="text"
                                        maxlength="180"
                                        class="form-control"
                                        :class="{ 'is-invalid': fieldError('title') }"
                                        placeholder="Resume el antecedente en una frase"
                                        required
                                    />
                                    <div class="invalid-feedback">{{ fieldError('title') }}</div>
                                    <div class="title-suggestions" aria-label="Sugerencias de título">
                                        <span>Sugerencias:</span>
                                        <button
                                            v-for="suggestion in titleSuggestions"
                                            :key="suggestion"
                                            type="button"
                                            :class="['form-option-chip', { active: form.title === suggestion }]"
                                            @click="selectTitleSuggestion(suggestion)"
                                        >
                                            {{ suggestion }}
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="detail-heading">
                                        <div>
                                            <label for="logbook-details" class="form-label mb-0">Detalle del registro</label>
                                            <small>Agrega solo los bloques que te resulten útiles.</small>
                                        </div>
                                        <small class="text-muted">{{ form.details.length }}/10.000</small>
                                    </div>
                                    <div class="detail-prompt-picker" aria-label="Opciones rápidas para el detalle">
                                        <button
                                            v-for="prompt in detailPrompts"
                                            :key="prompt.value"
                                            type="button"
                                            :class="['form-option-chip', { active: hasDetailPrompt(prompt) }]"
                                            @click="insertDetailPrompt(prompt)"
                                        >
                                            <i :class="`bx ${prompt.icon}`"></i>
                                            {{ prompt.label }}
                                        </button>
                                    </div>
                                    <textarea
                                        id="logbook-details"
                                        ref="detailsInput"
                                        v-model="form.details"
                                        rows="7"
                                        maxlength="10000"
                                        class="form-control logbook-textarea"
                                        :class="{ 'is-invalid': fieldError('details') }"
                                        placeholder="Describe el contexto, acuerdos, pendientes o cualquier información relevante..."
                                        required
                                    ></textarea>
                                    <div class="invalid-feedback">{{ fieldError('details') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer logbook-modal__footer">
                            <button type="button" class="btn btn-light" :disabled="saving" @click="closeForm">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bx bx-save me-1"></i>
                                {{ saving ? 'Guardando...' : (editingId ? 'Guardar corrección' : 'Agregar a mi bitácora') }}
                            </button>
                        </div>
                        </form>
                    </div>
                </div>
            </Teleport>
        </main>
    </Layout>
</template>

<style scoped>
.staff-logbook {
    --logbook-navy: #172554;
    --logbook-indigo: #4f46e5;
    color: #334155;
}

.logbook-alert {
    display: flex;
    align-items: center;
    gap: .65rem;
    border: 0;
    border-radius: .9rem;
}

.logbook-alert i { font-size: 1.25rem; }

.privacy-banner {
    display: flex;
    align-items: center;
    gap: .9rem;
    margin-bottom: 1rem;
    padding: .95rem 1rem;
    border: 1px solid #dbeafe;
    border-radius: 1rem;
    background: linear-gradient(115deg, #eff6ff, #f8fafc 68%);
}

.privacy-banner--super {
    border-color: #ddd6fe;
    background: linear-gradient(115deg, #f5f3ff, #fafafa 68%);
}

.privacy-banner__icon {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 2.65rem;
    height: 2.65rem;
    border-radius: .8rem;
    color: #2563eb;
    background: #dbeafe;
    font-size: 1.25rem;
}

.privacy-banner--super .privacy-banner__icon {
    color: #6d28d9;
    background: #ede9fe;
}

.privacy-banner strong { display: block; color: var(--logbook-navy); }
.privacy-banner p { margin: .15rem 0 0; color: #64748b; font-size: .82rem; }

.privacy-banner__scope {
    margin-left: auto;
    padding: .38rem .7rem;
    border-radius: 999px;
    color: #1d4ed8;
    background: rgba(255, 255, 255, .8);
    font-size: .72rem;
    font-weight: 700;
    white-space: nowrap;
}

.privacy-banner--super .privacy-banner__scope { color: #6d28d9; }
.logbook-browser { overflow: hidden; }

.logbook-filter-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .85rem;
}

.logbook-filter-heading strong { color: var(--logbook-navy); }

.logbook-search { position: relative; }
.logbook-search i {
    position: absolute;
    z-index: 1;
    top: 50%;
    left: .8rem;
    color: #94a3b8;
    font-size: 1.05rem;
    transform: translateY(-50%);
}
.logbook-search .form-control { padding-left: 2.35rem; }
.logbook-filter-button { min-height: 40px; border-radius: .65rem; }

.logbook-timeline {
    position: relative;
    max-width: 1120px;
    margin: 1.25rem auto 0;
}

.logbook-timeline::before {
    position: absolute;
    top: .5rem;
    bottom: .5rem;
    left: 1.35rem;
    width: 2px;
    border-radius: 99px;
    background: linear-gradient(#c7d2fe, #e2e8f0);
    content: "";
}

.logbook-entry {
    position: relative;
    display: grid;
    grid-template-columns: 2.75rem minmax(0, 1fr);
    gap: .9rem;
    padding-bottom: 1rem;
}

.logbook-entry:last-child { padding-bottom: 0; }

.logbook-entry__rail {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: center;
    padding-top: 1rem;
}

.logbook-entry__dot {
    display: grid;
    place-items: center;
    width: 2.2rem;
    height: 2.2rem;
    border: 4px solid #fff;
    border-radius: .75rem;
    color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 3px 12px rgba(51, 65, 85, .12);
}

.logbook-entry__card {
    padding: 1rem 1.1rem;
    border: 1px solid #e6eaf2;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 8px 24px rgba(30, 41, 59, .045);
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.logbook-entry__card:hover {
    border-color: #cfd6e6;
    box-shadow: 0 12px 32px rgba(30, 41, 59, .075);
    transform: translateY(-1px);
}

.logbook-entry__topline,
.logbook-entry__footer,
.logbook-person,
.logbook-entry__badges {
    display: flex;
    align-items: center;
}

.logbook-entry__topline {
    justify-content: space-between;
    gap: .75rem;
}

.logbook-entry__topline time {
    color: #64748b;
    font-size: .75rem;
    font-weight: 600;
}

.logbook-entry__badges { flex-wrap: wrap; gap: .4rem; }

.logbook-category,
.logbook-edited {
    display: inline-flex;
    align-items: center;
    padding: .32rem .58rem;
    border-radius: 999px;
    color: #4338ca;
    background: #eef2ff;
    font-size: .68rem;
    font-weight: 750;
}

.logbook-edited { color: #64748b; background: #f1f5f9; }
.logbook-category--management { color: #0369a1; background: #e0f2fe; }
.logbook-category--meeting { color: #7e22ce; background: #f3e8ff; }
.logbook-category--follow_up { color: #047857; background: #d1fae5; }
.logbook-category--incident { color: #be123c; background: #ffe4e6; }
.logbook-category--improvement { color: #b45309; background: #fef3c7; }
.logbook-category--other { color: #475569; background: #e2e8f0; }

.logbook-entry__card h3 {
    margin: .8rem 0 .45rem;
    color: var(--logbook-navy);
    font-size: 1rem;
    font-weight: 750;
}

.logbook-entry__details {
    margin: 0;
    color: #475569;
    line-height: 1.65;
    white-space: pre-wrap;
}

.logbook-more {
    margin-top: .35rem;
    padding: 0;
    border: 0;
    color: #4f46e5;
    background: transparent;
    font-size: .78rem;
    font-weight: 700;
}

.logbook-entry__footer {
    justify-content: space-between;
    gap: 1rem;
    margin-top: .9rem;
    padding-top: .85rem;
    border-top: 1px solid #eef1f6;
}

.logbook-person { gap: .6rem; }
.logbook-person__avatar {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border-radius: .65rem;
    color: #fff;
    background: linear-gradient(135deg, #3b4bb3, #6366f1);
    font-size: .7rem;
    font-weight: 800;
}
.logbook-person strong,
.logbook-person small { display: block; }
.logbook-person strong { color: #334155; font-size: .78rem; }
.logbook-person small { margin-top: .05rem; color: #94a3b8; font-size: .68rem; }

.logbook-empty {
    max-width: 520px;
    margin: 1rem auto;
    padding: 3.5rem 1rem;
    text-align: center;
}
.logbook-empty > span {
    display: grid;
    width: 4.2rem;
    height: 4.2rem;
    margin: 0 auto 1rem;
    place-items: center;
    border-radius: 1.25rem;
    color: #6366f1;
    background: #eef2ff;
    font-size: 2rem;
}
.logbook-empty h4 { color: var(--logbook-navy); }
.logbook-empty p { color: #64748b; }

.logbook-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid #edf0f5;
    color: #64748b;
}
.logbook-pagination :deep(.pagination) { margin-bottom: 0; }

.logbook-modal {
    position: fixed;
    z-index: 1070;
    inset: 0;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 1rem;
    background: rgba(15, 23, 42, .58);
    backdrop-filter: blur(5px);
}
.logbook-modal .modal-dialog { min-height: calc(100% - 2rem); margin: 0 auto; }
.logbook-modal .modal-content { overflow: hidden; border: 0; border-radius: 1.2rem; box-shadow: 0 28px 80px rgba(15, 23, 42, .32); }
.logbook-modal__eyebrow { color: rgba(255, 255, 255, .7); font-size: .66rem; font-weight: 750; letter-spacing: .1em; text-transform: uppercase; }

.logbook-form-privacy {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: 1.25rem;
    padding: .7rem .8rem;
    border-radius: .75rem;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: .78rem;
}
.logbook-form-privacy i { font-size: 1rem; }

.category-picker {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .55rem;
}
.category-picker__item {
    display: flex;
    min-height: 70px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .32rem;
    padding: .65rem .4rem;
    border: 1px solid #e2e8f0;
    border-radius: .8rem;
    color: #64748b;
    background: #fff;
    font-size: .7rem;
    font-weight: 650;
    text-align: center;
    transition: border-color .15s ease, background .15s ease, color .15s ease, transform .15s ease;
}
.category-picker__item i { font-size: 1.2rem; }
.category-picker__item:hover { border-color: #a5b4fc; color: #4338ca; transform: translateY(-1px); }
.category-picker__item.active { border-color: #818cf8; color: #3730a3; background: #eef2ff; box-shadow: 0 0 0 2px rgba(99, 102, 241, .1); }

.title-suggestions,
.detail-prompt-picker {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .45rem;
}
.title-suggestions { margin-top: .65rem; }
.title-suggestions > span {
    color: #94a3b8;
    font-size: .68rem;
    font-weight: 700;
}
.form-option-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    min-height: 30px;
    padding: .3rem .65rem;
    border: 1px solid #dbe3ef;
    border-radius: 999px;
    color: #475569;
    background: #fff;
    font-size: .68rem;
    font-weight: 700;
    transition: border-color .15s ease, background .15s ease, color .15s ease, transform .15s ease;
}
.form-option-chip:hover {
    border-color: #a5b4fc;
    color: #4338ca;
    background: #f8faff;
    transform: translateY(-1px);
}
.form-option-chip.active {
    border-color: #a5b4fc;
    color: #3730a3;
    background: #eef2ff;
}
.detail-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: .6rem;
}
.detail-heading small { display: block; margin-top: .15rem; color: #94a3b8; font-size: .68rem; }
.detail-prompt-picker { margin-bottom: .65rem; }

.logbook-textarea { min-height: 150px; resize: vertical; }
.logbook-modal__footer { padding: .85rem 1.25rem; border-color: #eef1f6; background: #f8fafc; }

@media (max-width: 991.98px) {
    .category-picker { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 575.98px) {
    .privacy-banner { align-items: flex-start; }
    .privacy-banner__scope { display: none; }
    .logbook-filter-heading { align-items: flex-start; }
    .logbook-entry { grid-template-columns: 1.9rem minmax(0, 1fr); gap: .5rem; }
    .logbook-timeline::before { left: .9rem; }
    .logbook-entry__dot { width: 1.8rem; height: 1.8rem; border-width: 3px; border-radius: .6rem; font-size: .8rem; }
    .logbook-entry__card { padding: .9rem; }
    .logbook-entry__topline { align-items: flex-start; flex-direction: column; }
    .logbook-entry__footer { align-items: flex-start; }
    .logbook-pagination { align-items: flex-start; flex-direction: column; }
    .category-picker { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .detail-heading { align-items: flex-start; }
    .logbook-modal { padding: .5rem; }
    .logbook-modal .modal-dialog { min-height: calc(100% - 1rem); }
}
</style>
