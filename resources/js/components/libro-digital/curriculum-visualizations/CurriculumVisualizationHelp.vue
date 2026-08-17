<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from "vue";

const props = defineProps({ modelValue: { type: Boolean, default: false } });
const emit = defineEmits(["update:model-value"]);

const dialog = ref(null);
const closeButton = ref(null);
let returnFocus = null;

const focusableElements = () =>
    [
        ...(dialog.value?.querySelectorAll(
            "button, [href], input, select, [tabindex]:not([tabindex='-1'])"
        ) || []),
    ].filter((element) => !element.disabled);
const handleKeydown = (event) => {
    if (event.key === "Escape") {
        event.preventDefault();
        emit("update:model-value", false);
        return;
    }
    if (event.key !== "Tab") return;
    const focusable = focusableElements();
    if (!focusable.length) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

watch(
    () => props.modelValue,
    async (visible) => {
        if (visible) {
            returnFocus = document.activeElement;
            await nextTick();
            (closeButton.value || dialog.value)?.focus?.();
        } else {
            returnFocus?.focus?.();
            returnFocus = null;
        }
    }
);
onBeforeUnmount(() => returnFocus?.focus?.());
</script>

<template>
    <div
        v-if="modelValue"
        class="cv-help-backdrop"
        role="presentation"
        @mousedown.self="emit('update:model-value', false)"
    >
        <section
            ref="dialog"
            class="cv-help"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cv-help-title"
            aria-describedby="cv-help-description"
            tabindex="-1"
            @keydown="handleKeydown"
        >
            <header>
                <div>
                    <span>Guía de lectura</span>
                    <h3 id="cv-help-title">
                        Cómo interpretar el mapa curricular
                    </h3>
                </div>
                <button
                    ref="closeButton"
                    type="button"
                    aria-label="Cerrar ayuda"
                    @click="emit('update:model-value', false)"
                >
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <div id="cv-help-description" class="cv-help__content">
                <article>
                    <i class="bx bx-shape-circle" aria-hidden="true"></i>
                    <div>
                        <h4>Tamaño</h4>
                        <p>
                            Representa la cantidad de objetivos contenidos. Una
                            forma más grande no implica mayor importancia
                            curricular.
                        </p>
                    </div>
                </article>
                <article>
                    <i class="bx bx-palette" aria-hidden="true"></i>
                    <div>
                        <h4>Color</h4>
                        <p>
                            Ayuda a distinguir ramas y asignaturas. Se mantiene
                            estable entre vistas para facilitar la comparación.
                        </p>
                    </div>
                </article>
                <article>
                    <i class="bx bx-mouse" aria-hidden="true"></i>
                    <div>
                        <h4>Navegación</h4>
                        <p>
                            Selecciona una categoría para verla y vuelve con la
                            ruta superior. Un OA abre su ficha oficial en el
                            módulo.
                        </p>
                    </div>
                </article>
                <article>
                    <i class="bx bx-accessibility" aria-hidden="true"></i>
                    <div>
                        <h4>Alternativa accesible</h4>
                        <p>
                            Debajo de cada gráfico existe un resumen y una lista
                            de categorías navegable con teclado.
                        </p>
                    </div>
                </article>
            </div>

            <aside>
                <i class="bx bx-info-circle" aria-hidden="true"></i>
                <p>
                    Las relaciones mostradas provienen exclusivamente de la
                    jerarquía curricular registrada. No se infieren vínculos
                    entre OA.
                </p>
            </aside>

            <footer>
                <button
                    type="button"
                    class="cv-help__close"
                    @click="emit('update:model-value', false)"
                >
                    Entendido
                </button>
            </footer>
        </section>
    </div>
</template>

<style scoped>
.cv-help-backdrop {
    position: fixed;
    z-index: 1095;
    display: grid;
    padding: 22px;
    background: rgba(12, 28, 49, 0.58);
    inset: 0;
    place-items: center;
}

.cv-help {
    width: min(680px, 100%);
    max-height: calc(100vh - 44px);
    overflow: auto;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 30px 75px rgba(17, 35, 62, 0.27);
}

.cv-help > header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px 18px;
    border-bottom: 1px solid var(--lcd-border, #dbe3eb);
}

.cv-help > header span {
    color: var(--lcd-brand-700, #245486);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.cv-help h3 {
    margin: 3px 0 0;
    color: var(--lcd-ink, #17263d);
    font-size: 1.25rem;
}

.cv-help > header button {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 10px;
    background: #fff;
    color: var(--lcd-ink-soft, #33445b);
    font-size: 1.4rem;
    place-items: center;
}

.cv-help__content {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 22px 24px 12px;
}

.cv-help article {
    display: flex;
    gap: 12px;
    padding: 15px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 12px;
    background: var(--lcd-surface-muted, #f7f9fb);
}

.cv-help article > i {
    color: var(--lcd-brand-700, #245486);
    font-size: 1.35rem;
}

.cv-help h4 {
    margin: 0 0 4px;
    color: var(--lcd-ink, #17263d);
    font-size: 0.91rem;
}

.cv-help p {
    margin: 0;
    color: var(--lcd-muted, #627187);
    font-size: 0.82rem;
    line-height: 1.55;
}

.cv-help aside {
    display: flex;
    gap: 10px;
    margin: 4px 24px 0;
    padding: 14px;
    border-radius: 11px;
    background: var(--lcd-brand-50, #f1f7fb);
}

.cv-help aside i {
    color: var(--lcd-brand-700, #245486);
    font-size: 1.15rem;
}

.cv-help > footer {
    display: flex;
    justify-content: flex-end;
    padding: 18px 24px 22px;
}

.cv-help__close {
    min-height: 42px;
    padding: 9px 18px;
    border: 1px solid var(--lcd-brand-700, #245486);
    border-radius: 10px;
    background: var(--lcd-brand-700, #245486);
    color: #fff;
    font-weight: 750;
}

.cv-help button:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
    outline-offset: 2px;
}

@media (max-width: 575px) {
    .cv-help-backdrop {
        padding: 10px;
    }

    .cv-help__content {
        grid-template-columns: 1fr;
        padding-inline: 16px;
    }

    .cv-help > header,
    .cv-help > footer {
        padding-inline: 16px;
    }

    .cv-help aside {
        margin-inline: 16px;
    }
}
</style>
