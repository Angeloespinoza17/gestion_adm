<script setup>
import { computed } from "vue";
import { CURRICULUM_HIERARCHY_PRESETS } from "../../../utils/curriculum-visualization";
import { visualizationOptions } from "./visualizationRegistry";

const props = defineProps({
    activeView: { type: String, default: "table" },
    hierarchy: { type: String, required: true },
    scope: { type: String, default: "filtered" },
    labelMode: { type: String, default: "automatic" },
    loading: { type: Boolean, default: false },
    exporting: { type: Boolean, default: false },
    fullscreen: { type: Boolean, default: false },
});

const emit = defineEmits([
    "update:view",
    "update:hierarchy",
    "update:scope",
    "update:label-mode",
    "reset",
    "center",
    "fullscreen",
    "export",
    "help",
]);

const graphActive = computed(() => props.activeView !== "table");
</script>

<template>
    <div class="cv-toolbar">
        <div class="cv-toolbar__heading">
            <div>
                <span class="cv-toolbar__eyebrow">Forma de explorar</span>
                <h3>Visualiza el catálogo curricular</h3>
            </div>
            <button
                type="button"
                class="cv-icon-button"
                aria-label="Abrir ayuda de visualizaciones"
                title="Cómo interpretar estas vistas"
                @click="emit('help')"
            >
                <i class="bx bx-help-circle" aria-hidden="true"></i>
            </button>
        </div>

        <label class="cv-toolbar__mobile-view">
            <span>Vista</span>
            <select
                :value="activeView"
                @change="emit('update:view', $event.target.value)"
            >
                <option
                    v-for="option in visualizationOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.text }}
                </option>
            </select>
        </label>

        <div
            class="cv-toolbar__views"
            role="radiogroup"
            aria-label="Tipo de visualización"
        >
            <button
                v-for="option in visualizationOptions"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="activeView === option.value"
                :class="[
                    'cv-view-button',
                    { 'cv-view-button--active': activeView === option.value },
                ]"
                :title="option.description"
                @click="emit('update:view', option.value)"
            >
                <i :class="['bx', option.icon]" aria-hidden="true"></i>
                <span>{{ option.shortLabel }}</span>
            </button>
        </div>

        <div class="cv-toolbar__controls">
            <label class="cv-field cv-field--hierarchy">
                <span>Organizar por</span>
                <select
                    :value="hierarchy"
                    :disabled="!graphActive || loading"
                    @change="emit('update:hierarchy', $event.target.value)"
                >
                    <option
                        v-for="preset in CURRICULUM_HIERARCHY_PRESETS"
                        :key="preset.value"
                        :value="preset.value"
                    >
                        {{ preset.label }}
                    </option>
                </select>
            </label>

            <label class="cv-field">
                <span>Alcance</span>
                <select
                    :value="scope"
                    :disabled="!graphActive || loading"
                    @change="emit('update:scope', $event.target.value)"
                >
                    <option value="filtered">Búsqueda filtrada</option>
                    <option value="catalog">Catálogo del contexto</option>
                </select>
            </label>

            <label class="cv-field">
                <span>Etiquetas</span>
                <select
                    :value="labelMode"
                    :disabled="!graphActive"
                    @change="emit('update:label-mode', $event.target.value)"
                >
                    <option value="automatic">Automáticas</option>
                    <option value="show">Mostrar todas</option>
                    <option value="hide">Ocultar</option>
                </select>
            </label>

            <div class="cv-toolbar__actions" aria-label="Acciones de la vista">
                <button
                    type="button"
                    class="cv-action-button"
                    :disabled="!graphActive || loading"
                    title="Volver a la raíz"
                    @click="emit('reset')"
                >
                    <i class="bx bx-reset" aria-hidden="true"></i>
                    <span>Reiniciar</span>
                </button>
                <button
                    type="button"
                    class="cv-action-button"
                    :disabled="!graphActive || loading"
                    title="Centrar contenido"
                    @click="emit('center')"
                >
                    <i class="bx bx-crosshair" aria-hidden="true"></i>
                    <span>Centrar</span>
                </button>
                <button
                    type="button"
                    class="cv-action-button"
                    :disabled="!graphActive"
                    :aria-pressed="fullscreen"
                    :title="
                        fullscreen
                            ? 'Salir de pantalla completa'
                            : 'Pantalla completa'
                    "
                    @click="emit('fullscreen')"
                >
                    <i
                        :class="[
                            'bx',
                            fullscreen ? 'bx-exit-fullscreen' : 'bx-fullscreen',
                        ]"
                        aria-hidden="true"
                    ></i>
                    <span>{{ fullscreen ? "Salir" : "Ampliar" }}</span>
                </button>
                <button
                    type="button"
                    class="cv-action-button cv-action-button--primary"
                    :disabled="!graphActive || loading || exporting"
                    title="Exportar la visualización como documento PDF"
                    @click="emit('export')"
                >
                    <i class="bx bx-download" aria-hidden="true"></i>
                    <span>{{ exporting ? "Exportando" : "Exportar PDF" }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cv-toolbar {
    padding: 18px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: var(--lcd-radius-lg, 16px);
    background: linear-gradient(145deg, #fff 0%, #f7fafc 100%);
    box-shadow: var(--lcd-shadow-sm, 0 2px 8px rgba(20, 39, 65, 0.055));
}

.cv-toolbar__heading,
.cv-toolbar__controls,
.cv-toolbar__actions {
    display: flex;
    align-items: center;
}

.cv-toolbar__heading {
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.cv-toolbar__eyebrow,
.cv-field > span,
.cv-toolbar__mobile-view > span {
    display: block;
    color: var(--lcd-brand-700, #245486);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}

.cv-toolbar h3 {
    margin: 2px 0 0;
    color: var(--lcd-ink, #17263d);
    font-size: 1.08rem;
}

.cv-icon-button,
.cv-view-button,
.cv-action-button {
    border: 1px solid var(--lcd-border, #dbe3eb);
    background: #fff;
    color: var(--lcd-ink-soft, #33445b);
    transition: border-color 160ms ease, background 160ms ease, color 160ms ease,
        transform 160ms ease;
}

.cv-icon-button:focus-visible,
.cv-view-button:focus-visible,
.cv-action-button:focus-visible,
select:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
    outline-offset: 2px;
}

.cv-icon-button {
    display: inline-grid;
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 10px;
    font-size: 1.25rem;
}

.cv-toolbar__views {
    display: grid;
    grid-template-columns: repeat(9, minmax(78px, 1fr));
    gap: 7px;
    margin-bottom: 16px;
}

.cv-view-button {
    display: flex;
    min-height: 62px;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 7px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.15;
}

.cv-view-button i {
    font-size: 1.08rem;
}

.cv-view-button:hover:not(:disabled) {
    border-color: var(--lcd-brand-500, #3e7daf);
    color: var(--lcd-brand-700, #245486);
    transform: translateY(-1px);
}

.cv-view-button--active {
    border-color: #ffb000;
    background: var(--lcd-brand-700, #245486);
    box-shadow: 0 0 0 2px #17263d, 0 8px 18px rgba(36, 84, 134, 0.28);
    color: #fff;
    transform: translateY(-1px);
}

.cv-view-button--active i {
    color: #ffd36a;
}

.cv-toolbar__controls {
    align-items: flex-end;
    gap: 10px;
}

.cv-field {
    min-width: 150px;
    margin: 0;
}

.cv-field--hierarchy {
    min-width: 300px;
    flex: 1 1 360px;
}

.cv-field select,
.cv-toolbar__mobile-view select {
    width: 100%;
    min-height: 40px;
    margin-top: 5px;
    padding: 8px 34px 8px 11px;
    border: 1px solid var(--lcd-border-strong, #c7d2de);
    border-radius: 9px;
    background: #fff;
    color: var(--lcd-ink, #17263d);
    font-size: 0.82rem;
}

.cv-field select:disabled {
    background: var(--lcd-surface-muted, #f7f9fb);
    color: var(--lcd-subtle, #8794a6);
}

.cv-toolbar__actions {
    flex: 0 0 auto;
    gap: 6px;
}

.cv-action-button {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 10px;
    border-radius: 9px;
    font-size: 0.77rem;
    font-weight: 750;
}

.cv-action-button i {
    font-size: 1rem;
}

.cv-action-button:hover:not(:disabled) {
    border-color: var(--lcd-brand-500, #3e7daf);
    color: var(--lcd-brand-700, #245486);
}

.cv-action-button--primary {
    border-color: var(--lcd-brand-700, #245486);
    background: var(--lcd-brand-700, #245486);
    color: #fff;
}

.cv-action-button:disabled,
.cv-view-button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.cv-toolbar__mobile-view {
    display: none;
}

@media (max-width: 1199px) {
    .cv-toolbar__views {
        grid-template-columns: repeat(5, minmax(90px, 1fr));
    }

    .cv-toolbar__controls {
        flex-wrap: wrap;
    }

    .cv-toolbar__actions {
        flex: 1 1 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 767px) {
    .cv-toolbar {
        padding: 14px;
    }

    .cv-toolbar__views {
        display: none;
    }

    .cv-toolbar__mobile-view {
        display: block;
        margin-bottom: 13px;
    }

    .cv-toolbar__controls,
    .cv-field,
    .cv-field--hierarchy {
        display: block;
        width: 100%;
        min-width: 0;
    }

    .cv-toolbar__actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        margin-top: 3px;
    }

    .cv-action-button {
        flex-direction: column;
        padding: 7px 4px;
        font-size: 0.68rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cv-icon-button,
    .cv-view-button,
    .cv-action-button {
        transition: none;
    }
}
</style>
