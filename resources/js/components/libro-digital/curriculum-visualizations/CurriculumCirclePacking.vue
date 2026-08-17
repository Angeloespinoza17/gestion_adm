<script setup>
import { hierarchy, pack } from "d3-hierarchy";
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
const height = ref(600);
const focusId = ref(null);
const zoomHistory = ref([]);
let observer = null;

const layout = computed(() => {
    const root = hierarchy(props.root)
        .sum((node) =>
            node.children?.length
                ? 0
                : Math.max(1, Number(node.objectiveCount || node.value || 1))
        )
        .sort((left, right) => (right.value || 0) - (left.value || 0));
    return pack()
        .size([width.value, height.value])
        .padding(5)(root)
        .descendants();
});
const focusedLayoutNode = computed(() =>
    focusId.value
        ? layout.value.find((node) => node.data.id === focusId.value)
        : null
);
const viewBox = computed(() => {
    const node = focusedLayoutNode.value;
    if (!node) return `0 0 ${width.value} ${height.value}`;
    const padding = Math.max(10, node.r * 0.08);
    const diameter = Math.max(1, node.r * 2 + padding * 2);
    return `${node.x - node.r - padding} ${
        node.y - node.r - padding
    } ${diameter} ${diameter}`;
});

const showLabel = (node) => {
    if (props.labelMode === "hide") return false;
    if (props.labelMode === "show") return node.r >= 17;
    return node.r >= 31 && (!node.children || node.depth <= 2);
};
const label = (node) => {
    const value = node.data.shortName || node.data.code || node.data.name;
    const maximum = Math.max(5, Math.floor(node.r / 4.6));
    return String(value || "").slice(0, maximum);
};
const activate = (node) => {
    emit("select-node", node.data);
    if (isObjectiveNode(node.data)) emit("open-objective", node.data);
    else if (node.children?.length) {
        zoomHistory.value = [...zoomHistory.value, focusId.value];
        focusId.value = node.data.id;
    } else if (
        node.data.hasChildren ||
        node.data.metadata?.children_truncated
    ) {
        emit("drilldown", node.data);
    }
};
const zoomOut = () => {
    if (!focusId.value) return;
    const history = [...zoomHistory.value];
    focusId.value = history.pop() || null;
    zoomHistory.value = history;
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
        470,
        Math.min(720, Math.round(bounds.width * 0.58))
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
    reset: () => {
        focusId.value = null;
        zoomHistory.value = [];
        resize();
    },
    exportImage: () =>
        svgElementToPngDataUrl(svg.value, {
            width: 1400,
            height: 840,
        }),
});
</script>

<template>
    <div ref="host" class="cv-pack">
        <svg
            ref="svg"
            class="cv-pack__svg"
            :viewBox="viewBox"
            role="img"
            aria-label="Círculos agrupados por jerarquía curricular"
        >
            <rect
                x="-100000"
                y="-100000"
                width="200000"
                height="200000"
                fill="#ffffff"
                class="cv-pack__background"
                @click="zoomOut"
            />
            <g
                v-for="node in layout"
                :key="node.data.id"
                class="cv-pack__node"
                :class="{
                    'cv-pack__node--selected':
                        selectedNode?.id === node.data.id,
                }"
                :transform="`translate(${node.x}, ${node.y})`"
                role="button"
                tabindex="0"
                :aria-label="`${node.data.name}, ${formatObjectiveCount(
                    node.data.objectiveCount
                )}`"
                @click.stop="activate(node)"
                @keydown="keyboardActivate($event, node)"
            >
                <circle
                    :r="node.r"
                    :fill="
                        node.depth === 0
                            ? '#f7f9fb'
                            : stableNodeColor(
                                  node.data,
                                  Math.max(0, node.depth - 1)
                              )
                    "
                    :fill-opacity="node.depth === 0 ? 1 : 0.96"
                    :stroke="
                        selectedNode?.id === node.data.id
                            ? '#ffb000'
                            : '#ffffff'
                    "
                    :stroke-width="selectedNode?.id === node.data.id ? 7 : 2"
                    :opacity="
                        selectedNode && selectedNode.id !== node.data.id
                            ? 0.48
                            : 1
                    "
                    :style="
                        selectedNode?.id === node.data.id
                            ? {
                                  filter: 'drop-shadow(0 0 7px rgba(23, 38, 61, .62))',
                              }
                            : undefined
                    "
                />
                <title>{{ nodeTooltipText(node.data) }}</title>
                <text
                    v-if="showLabel(node)"
                    text-anchor="middle"
                    dominant-baseline="middle"
                    :font-size="Math.max(9, Math.min(13, node.r / 5))"
                    :fill="
                        node.depth === 0
                            ? '#17263d'
                            : contrastTextColor(
                                  stableNodeColor(
                                      node.data,
                                      Math.max(0, node.depth - 1)
                                  )
                              )
                    "
                    pointer-events="none"
                >
                    <tspan x="0" dy="-0.15em">{{ label(node) }}</tspan>
                    <tspan x="0" dy="1.25em" font-weight="700">
                        {{
                            Number(
                                node.data.objectiveCount || 0
                            ).toLocaleString("es-CL")
                        }}
                    </tspan>
                </text>
            </g>
        </svg>
    </div>
</template>

<style scoped>
.cv-pack {
    width: 100%;
    min-height: 560px;
    overflow: hidden;
}

.cv-pack__svg {
    display: block;
    width: 100%;
    min-height: 560px;
    transition: all 320ms ease;
}

.cv-pack__background {
    cursor: zoom-out;
}

.cv-pack__node {
    cursor: pointer;
}

.cv-pack__node circle {
    transition: opacity 160ms ease, stroke-width 160ms ease;
}

.cv-pack__node:hover circle,
.cv-pack__node:focus circle {
    stroke: #245486;
    stroke-width: 4;
}

.cv-pack__node:focus {
    outline: none;
}

@media (max-width: 767px) {
    .cv-pack,
    .cv-pack__svg {
        min-height: 470px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cv-pack__svg,
    .cv-pack__node circle {
        transition: none;
    }
}
</style>
