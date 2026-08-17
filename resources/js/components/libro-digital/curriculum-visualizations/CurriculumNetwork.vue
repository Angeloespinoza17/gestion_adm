<script setup>
import { computed, ref, watch } from "vue";
import {
    graphNodeTooltipText,
    isObjectiveNode,
    nodeTypeLabel,
    stableNodeColor,
} from "../../../utils/curriculum-visualization";
import VChart from "./echarts";
import { useRenderableEChart } from "./useRenderableEChart";

const props = defineProps({
    graph: { type: Object, default: () => ({ nodes: [], links: [] }) },
    labelMode: { type: String, default: "automatic" },
    reducedMotion: { type: Boolean, default: false },
    selectedNode: { type: Object, default: null },
});
const emit = defineEmits(["select-node", "drilldown", "open-objective"]);

const {
    chart,
    chartHost,
    chartInstance,
    resize,
    scheduleResize,
    exportImage: captureChart,
} = useRenderableEChart();
const search = ref("");
const typeFilter = ref("");
const relationFilter = ref("hierarchy");

const types = computed(() => {
    const options = new Map();
    for (const node of props.graph.nodes || []) {
        if (!node.type || options.has(node.type)) continue;
        options.set(node.type, nodeTypeLabel(node));
    }
    return [...options.entries()]
        .map(([value, label]) => ({ value, label }))
        .sort((left, right) => left.label.localeCompare(right.label, "es"));
});

const filtered = computed(() => {
    const sourceNodes = (props.graph.nodes || []).filter((node) => {
        if (typeFilter.value && node.type !== typeFilter.value) return false;
        return true;
    });
    const nodes = sourceNodes;
    const ids = new Set(nodes.map((node) => node.id));
    const links = (props.graph.links || []).filter(
        (link) =>
            link.relationType === "hierarchy" &&
            link.isOfficial &&
            ids.has(link.source) &&
            ids.has(link.target)
    );
    return { nodes, links };
});
const matchingIds = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase("es");
    if (!needle) return new Set();
    return new Set(
        filtered.value.nodes
            .filter((node) =>
                [node.name, node.code, node.type]
                    .filter(Boolean)
                    .some((value) =>
                        String(value).toLocaleLowerCase("es").includes(needle)
                    )
            )
            .map((node) => node.id)
    );
});
const selectedNeighborhood = computed(() => {
    if (!props.selectedNode?.id) return new Set();
    const ids = new Set([props.selectedNode.id]);
    for (const link of filtered.value.links) {
        if (link.source === props.selectedNode.id) ids.add(link.target);
        if (link.target === props.selectedNode.id) ids.add(link.source);
    }
    return ids;
});

const option = computed(() => {
    const count = Math.max(1, filtered.value.nodes.length);
    const chartNodes = filtered.value.nodes.map((node, index) => {
        const angle = (index / count) * Math.PI * 2;
        const radius = 210 + (node.depth % 3) * 70;
        const searchActive = matchingIds.value.size > 0 || search.value.trim();
        const selectionActive = selectedNeighborhood.value.size > 0;
        const emphasizedBySearch =
            !searchActive || matchingIds.value.has(node.id);
        const emphasizedBySelection =
            !selectionActive || selectedNeighborhood.value.has(node.id);
        return {
            id: node.id,
            name: node.id,
            x: Math.cos(angle) * radius,
            y: Math.sin(angle) * radius,
            value: node.value,
            symbolSize: Math.max(
                8,
                Math.min(30, 8 + Math.log10(Number(node.value || 0) + 1) * 7)
            ),
            itemStyle: {
                color: stableNodeColor(node),
                opacity: emphasizedBySearch && emphasizedBySelection ? 1 : 0.14,
                borderColor:
                    props.selectedNode?.id === node.id
                        ? "#ffb000"
                        : matchingIds.value.has(node.id)
                        ? "#17263d"
                        : "#ffffff",
                borderWidth:
                    props.selectedNode?.id === node.id
                        ? 5
                        : matchingIds.value.has(node.id)
                        ? 3
                        : 1,
                shadowBlur: props.selectedNode?.id === node.id ? 14 : 0,
                shadowColor:
                    props.selectedNode?.id === node.id
                        ? "rgba(23, 38, 61, 0.58)"
                        : "transparent",
            },
            __node: { ...node, objectiveCount: node.value },
        };
    });
    return {
        animation: !props.reducedMotion,
        aria: {
            enabled: true,
            description:
                "Red de relaciones jerárquicas oficiales del catálogo curricular.",
        },
        tooltip: {
            trigger: "item",
            renderMode: "richText",
            confine: true,
            backgroundColor: "#17263d",
            borderWidth: 0,
            textStyle: { color: "#fff", fontSize: 12, lineHeight: 18 },
            formatter: (params) =>
                params?.dataType === "edge"
                    ? params?.data?.__tooltip || "Relación jerárquica oficial"
                    : graphNodeTooltipText(params?.data?.__node),
        },
        series: [
            {
                type: "graph",
                layout: "force",
                data: chartNodes,
                links: filtered.value.links.map((link) => {
                    const source = filtered.value.nodes.find(
                        (node) => node.id === link.source
                    );
                    const target = filtered.value.nodes.find(
                        (node) => node.id === link.target
                    );
                    const adjacent =
                        !props.selectedNode?.id ||
                        link.source === props.selectedNode.id ||
                        link.target === props.selectedNode.id;
                    return {
                        source: link.source,
                        target: link.target,
                        value: link.value,
                        __tooltip: `${source?.name || "Origen"} → ${
                            target?.name || "Destino"
                        }\n${Number(link.value || 0).toLocaleString(
                            "es-CL"
                        )} objetivos únicos\nRelación jerárquica oficial`,
                        lineStyle: {
                            width: adjacent ? 1.8 : 1,
                            color: "#9fb3c7",
                            opacity: adjacent ? 0.65 : 0.12,
                        },
                    };
                }),
                roam: true,
                draggable: true,
                force: {
                    repulsion: Math.min(420, 95 + count * 1.2),
                    gravity: 0.08,
                    edgeLength: [45, 125],
                    friction: 0.55,
                    layoutAnimation: !props.reducedMotion,
                },
                label: {
                    show:
                        props.labelMode === "show" ||
                        (props.labelMode === "automatic" && count <= 90),
                    position: "right",
                    color: "#33445b",
                    fontSize: 9,
                    overflow: "truncate",
                    width: 100,
                    formatter: (params) => {
                        const node = params.data?.__node;
                        return node?.code || node?.name || "";
                    },
                },
                emphasis: { focus: "adjacency", lineStyle: { width: 2 } },
            },
        ],
    };
});

const handleClick = (params) => {
    const node = params?.data?.__node;
    if (!node) return;
    emit("select-node", node);
    if (isObjectiveNode(node)) emit("open-objective", node);
};
const handleDoubleClick = (params) => {
    const node = params?.data?.__node;
    if (node && !isObjectiveNode(node)) emit("drilldown", node);
};

defineExpose({
    center: () => resize(),
    reset: () => {
        search.value = "";
        typeFilter.value = "";
        relationFilter.value = "hierarchy";
        const instance = chartInstance();
        instance?.setOption?.(option.value, { notMerge: true });
        scheduleResize();
    },
    exportImage: () =>
        captureChart({
            type: "png",
            pixelRatio: 2,
            backgroundColor: "#ffffff",
        }),
});

watch(option, scheduleResize, { flush: "post" });
</script>

<template>
    <div ref="chartHost" class="cv-network">
        <div class="cv-network__filters">
            <label>
                <span class="visually-hidden">Buscar nodo en la red</span>
                <i class="bx bx-search" aria-hidden="true"></i>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Buscar código o categoría"
                />
            </label>
            <label>
                <span class="visually-hidden">Filtrar por tipo de nodo</span>
                <select v-model="typeFilter">
                    <option value="">Todos los tipos</option>
                    <option
                        v-for="type in types"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ type.label }}
                    </option>
                </select>
            </label>
            <label>
                <span class="visually-hidden"
                    >Filtrar por tipo de relación</span
                >
                <select v-model="relationFilter">
                    <option value="hierarchy">Jerarquía oficial</option>
                </select>
            </label>
            <span>{{ filtered.nodes.length }} nodos visibles</span>
        </div>
        <div
            v-if="!filtered.nodes.length"
            class="cv-network__empty"
            role="status"
        >
            No hay nodos que coincidan con esta búsqueda.
        </div>
        <VChart
            v-else
            ref="chart"
            class="cv-network__chart"
            :option="option"
            :autoresize="{ throttle: 100 }"
            role="img"
            aria-label="Red curricular interactiva"
            @click="handleClick"
            @dblclick="handleDoubleClick"
        />
        <p class="cv-network__disclosure">
            Solo se muestran relaciones jerárquicas oficiales; no se generan
            vínculos OA↔OA por similitud.
        </p>
    </div>
</template>

<style scoped>
.cv-network {
    position: relative;
    width: 100%;
    min-height: 560px;
}

.cv-network__filters {
    position: absolute;
    z-index: 2;
    top: 8px;
    left: 8px;
    display: flex;
    max-width: calc(100% - 16px);
    align-items: center;
    gap: 7px;
    padding: 6px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 4px 14px rgba(20, 39, 65, 0.08);
}

.cv-network__filters label:first-child {
    position: relative;
}

.cv-network__filters i {
    position: absolute;
    top: 50%;
    left: 9px;
    color: var(--lcd-muted, #627187);
    transform: translateY(-50%);
}

.cv-network__filters input,
.cv-network__filters select {
    min-height: 34px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 7px;
    background: #fff;
    color: var(--lcd-ink, #17263d);
    font-size: 0.75rem;
}

.cv-network__filters input {
    width: 205px;
    padding: 6px 8px 6px 29px;
}

.cv-network__filters select {
    width: 150px;
    padding: 6px 8px;
}

.cv-network__filters > span {
    color: var(--lcd-muted, #627187);
    font-size: 0.72rem;
    white-space: nowrap;
}

.cv-network__filters input:focus-visible,
.cv-network__filters select:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
}

.cv-network__chart {
    width: 100%;
    min-height: 560px;
}

.cv-network__empty {
    display: grid;
    min-height: 560px;
    color: var(--lcd-muted, #627187);
    place-items: center;
}

.cv-network__disclosure {
    position: absolute;
    right: 10px;
    bottom: 8px;
    margin: 0;
    padding: 5px 8px;
    border-radius: 7px;
    background: rgba(255, 255, 255, 0.9);
    color: var(--lcd-muted, #627187);
    font-size: 0.66rem;
}

@media (max-width: 767px) {
    .cv-network__filters {
        right: 8px;
        flex-wrap: wrap;
    }

    .cv-network__filters label,
    .cv-network__filters input,
    .cv-network__filters select {
        width: 100%;
    }

    .cv-network__chart,
    .cv-network__empty {
        min-height: 500px;
    }
}
</style>
