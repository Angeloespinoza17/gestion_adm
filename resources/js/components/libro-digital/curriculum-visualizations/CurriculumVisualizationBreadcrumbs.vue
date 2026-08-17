<script setup>
import { nodeTypeLabel } from "../../../utils/curriculum-visualization";

defineProps({
    items: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(["select"]);
</script>

<template>
    <nav class="cv-breadcrumbs" aria-label="Ruta curricular explorada">
        <ol>
            <li v-for="(item, index) in items" :key="item.id || 'root'">
                <button
                    type="button"
                    :disabled="busy || index === items.length - 1"
                    :aria-current="
                        index === items.length - 1 ? 'location' : undefined
                    "
                    :title="`${nodeTypeLabel(item)}: ${item.name}`"
                    @click="emit('select', item)"
                >
                    <span v-if="item.code" class="cv-breadcrumbs__code">{{
                        item.code
                    }}</span>
                    <span>{{ item.name }}</span>
                </button>
                <i
                    v-if="index < items.length - 1"
                    class="bx bx-chevron-right"
                    aria-hidden="true"
                ></i>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.cv-breadcrumbs {
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: thin;
}

.cv-breadcrumbs ol {
    display: flex;
    min-width: max-content;
    align-items: center;
    gap: 2px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.cv-breadcrumbs li {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.cv-breadcrumbs button {
    display: inline-flex;
    max-width: 260px;
    min-height: 34px;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    overflow: hidden;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--lcd-brand-700, #245486);
    font-size: 0.78rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cv-breadcrumbs button:not(:disabled):hover {
    background: var(--lcd-brand-50, #f1f7fb);
}

.cv-breadcrumbs button:disabled {
    color: var(--lcd-ink, #17263d);
    cursor: default;
}

.cv-breadcrumbs button:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
}

.cv-breadcrumbs__code {
    padding: 2px 5px;
    border-radius: 5px;
    background: var(--lcd-brand-50, #f1f7fb);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.68rem;
}

.cv-breadcrumbs i {
    color: var(--lcd-subtle, #8794a6);
    font-size: 1rem;
}
</style>
