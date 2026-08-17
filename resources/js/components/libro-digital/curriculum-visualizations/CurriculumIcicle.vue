<script setup>
import { hierarchy, partition } from "d3-hierarchy";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import {
    contrastTextColor,
    formatObjectiveCount,
    isObjectiveNode,
    nodeTooltipText,
    stableNodeColor,
} from "../../../utils/curriculum-visualization";
import { svgElementToPngDataUrl } from "../../../utils/curriculum-visualization-export";

const props = defineProps({
    root: { type: Object, required: true },
    labelMode: { type: String, default: "automatic" },
    reducedMotion: { type: Boolean, default: false },
    selectedNode: { type: Object, default: null },
});
const emit = defineEmits(["select-node", "drilldown", "open-objective"]);

const host = ref(null);
const svg = ref(null);
const width = ref(1040);
const height = ref(590);
const orientation = ref("horizontal");
let observer = null;

const layout = computed(() => {
    const source = hierarchy(props.root)
        .sum((node) =>
            node.children?.length
                ? 0
                : Math.max(1, Number(node.objectiveCount || node.value || 1))
        )
        .sort((left, right) => (right.value || 0) - (left.value || 0));
    const horizontal = orientation.value === "horizontal";
    const result = partition().size(
        horizontal ? [height.value, width.value] : [width.value, height.value]
    )(source);
    return result.descendants().map((node) => ({
        hierarchyNode: node,
        data: node.data,
        x: horizontal ? node.y0 : node.x0,
        y: horizontal ? node.x0 : node.y0,
        width: Math.max(0, horizontal ? node.y1 - node.y0 : node.x1 - node.x0),
        height: Math.max(0, horizontal ? node.x1 - node.x0 : node.y1 - node.y0),
    }));
});

const showLabel = (node) => {
    if (props.labelMode === "hide") return false;
    const enoughSpace = node.width > 74 && node.height > 22;
    return props.labelMode === "show"
        ? node.width > 42 && node.height > 17
        : enoughSpace;
};
const label = (node) => {
    const source = node.data.shortName || node.data.code || node.data.name;
    const characters = Math.max(4, Math.floor(node.width / 7.2));
    const value = String(source || "");
    return value.length > characters
        ? `${value.slice(0, Math.max(1, characters - 1))}…`
        : value;
};
const activate = (node) => {
    emit("select-node", node.data);
    if (isObjectiveNode(node.data)) emit("open-objective", node.data);
    else emit("drilldown", node.data);
};
const keyboardActivate = (event, node) => {
    if (!["Enter", " "].includes(event.key)) return;
    event.preventDefault();
    activate(node);
};
const resize = () => {
    const bounds = host.value?.getBoundingClientRect();
    if (!bounds?.width) return;
    width.value = Math.max(320, Math.round(bounds.width));
    height.value = Math.max(
        500,
        Math.min(700, Math.round(bounds.width * 0.54))
    );
};

onMounted(() => {
    resize();
    if (typeof ResizeObserver !== "undefined") {
        observer = new ResizeObserver(resize);
        observer.observe(host.value);
    } else {
        window.addEventListener("resize", resize, { passive: true });
    }
});
onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener("resize", resize);
});

defineExpose({
    center: resize,
    reset: resize,
    exportImage: () =>
        svgElementToPngDataUrl(svg.value, { width: 1400, height: 820 }),
});
</script>

<template>
    <div ref="host" class="cv-icicle">
        <div class="cv-icicle__toggle" aria-label="Orientación del gráfico">
            <button
                type="button"
                :aria-pressed="orientation === 'horizontal'"
                @click="orientation = 'horizontal'"
            >
                Horizontal
            </button>
            <button
                type="button"
                :aria-pressed="orientation === 'vertical'"
                @click="orientation = 'vertical'"
            >
                Vertical
            </button>
        </div>
        <svg
            ref="svg"
            class="cv-icicle__svg"
            :viewBox="`0 0 ${width} ${height}`"
            role="img"
            aria-label="Jerarquía curricular rectangular"
        >
            <rect width="100%" height="100%" fill="#ffffff" />
            <g
                v-for="node in layout"
                :key="node.data.id"
                class="cv-icicle__node"
                role="button"
                tabindex="0"
                :aria-label="`${node.data.name}, ${formatObjectiveCount(
                    node.data.objectiveCount
                )}`"
                @click="activate(node)"
                @keydown="keyboardActivate($event, node)"
            >
                <rect
                    :x="node.x + 1"
                    :y="node.y + 1"
                    :width="Math.max(0, node.width - 2)"
                    :height="Math.max(0, node.height - 2)"
                    rx="3"
                    :fill="stableNodeColor(node.data, node.hierarchyNode.depth)"
                    fill-opacity="0.96"
                    :stroke="
                        selectedNode?.id === node.data.id
                            ? '#ffb000'
                            : '#ffffff'
                    "
                    :stroke-width="selectedNode?.id === node.data.id ? 5 : 1"
                    :opacity="
                        selectedNode && selectedNode.id !== node.data.id
                            ? 0.42
                            : 1
                    "
                />
                <title>{{ nodeTooltipText(node.data) }}</title>
                <text
                    v-if="showLabel(node)"
                    :x="node.x + 7"
                    :y="node.y + Math.min(node.height / 2 + 4, 18)"
                    :fill="
                        contrastTextColor(
                            stableNodeColor(node.data, node.hierarchyNode.depth)
                        )
                    "
                    font-size="10"
                    font-weight="650"
                    pointer-events="none"
                >
                    {{ label(node) }}
                </text>
            </g>
        </svg>
    </div>
</template>

<style scoped>
.cv-icicle {
    position: relative;
    width: 100%;
    min-height: 560px;
    padding-top: 44px;
    overflow: hidden;
}

.cv-icicle__toggle {
    position: absolute;
    z-index: 2;
    top: 4px;
    right: 4px;
    display: inline-flex;
    padding: 3px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    background: #fff;
}

.cv-icicle__toggle button {
    min-height: 31px;
    padding: 5px 9px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--lcd-muted, #627187);
    font-size: 0.72rem;
    font-weight: 700;
}

.cv-icicle__toggle button[aria-pressed="true"] {
    background: var(--lcd-brand-700, #245486);
    color: #fff;
}

.cv-icicle__toggle button:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
}

.cv-icicle__svg {
    display: block;
    width: 100%;
    min-height: 516px;
}

.cv-icicle__node {
    cursor: pointer;
}

.cv-icicle__node rect {
    transition: opacity 160ms ease;
}

.cv-icicle__node:hover rect,
.cv-icicle__node:focus rect {
    opacity: 0.82;
    stroke: #17263d;
    stroke-width: 3;
}

.cv-icicle__node:focus {
    outline: none;
}

@media (prefers-reduced-motion: reduce) {
    .cv-icicle__node rect {
        transition: none;
    }
}
</style>
