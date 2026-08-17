<script setup>
import { computed, watch } from "vue";
import {
    graphNodeTooltipText,
    hierarchyToChartNode,
    isObjectiveNode,
    nodeTooltipText,
    stableNodeColor,
} from "../../../utils/curriculum-visualization";
import VChart from "./echarts";
import { useRenderableEChart } from "./useRenderableEChart";

const props = defineProps({
    variant: { type: String, required: true },
    root: { type: Object, required: true },
    graph: { type: Object, default: () => ({ nodes: [], links: [] }) },
    labelMode: { type: String, default: "automatic" },
    reducedMotion: { type: Boolean, default: false },
    treeDepth: { type: Number, default: 2 },
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

const labelVisible = (automatic = true) =>
    props.labelMode === "show" ||
    (props.labelMode === "automatic" && automatic);

const chartRoot = computed(() =>
    hierarchyToChartNode(props.root, 0, props.selectedNode?.id || null)
);

const common = computed(() => ({
    animation: !props.reducedMotion,
    animationDuration: props.reducedMotion ? 0 : 320,
    textStyle: {
        color: "#33445b",
        fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, sans-serif",
    },
    aria: {
        enabled: true,
        description:
            "Jerarquía curricular. El tamaño representa cantidad de objetivos, no importancia curricular.",
    },
    tooltip: {
        trigger: "item",
        renderMode: "richText",
        confine: true,
        backgroundColor: "#17263d",
        borderWidth: 0,
        textStyle: { color: "#fff", fontSize: 12, lineHeight: 18 },
        formatter: (params) => {
            if (props.variant === "sankey" && params?.dataType === "edge") {
                return params?.data?.__tooltip || "Relación jerárquica oficial";
            }
            const node = params?.data?.__node;
            return props.variant === "sankey"
                ? graphNodeTooltipText(node)
                : nodeTooltipText(node);
        },
    },
}));

const treemapSeries = computed(() => ({
    type: "treemap",
    data: [chartRoot.value],
    roam: true,
    nodeClick: false,
    breadcrumb: { show: false },
    visibleMin: 3,
    upperLabel: {
        show: labelVisible(true),
        height: 30,
        fontWeight: 700,
    },
    label: {
        show: labelVisible(true),
        formatter: (params) => {
            const node = params.data?.__node;
            if (!node) return "";
            const name = node.shortName || node.code || node.name;
            return `${name}\n${Number(node.objectiveCount || 0).toLocaleString(
                "es-CL"
            )}`;
        },
        overflow: "truncate",
        fontSize: 11,
        lineHeight: 16,
    },
    itemStyle: { borderColor: "#fff", borderWidth: 2, gapWidth: 2 },
    levels: [
        { itemStyle: { borderWidth: 0, gapWidth: 3 } },
        { itemStyle: { borderWidth: 2, gapWidth: 2 } },
        { colorSaturation: [0.3, 0.55], itemStyle: { gapWidth: 1 } },
    ],
    emphasis: { focus: "ancestor" },
}));

const sunburstSeries = computed(() => ({
    type: "sunburst",
    data: chartRoot.value.children || [],
    radius: [32, "92%"],
    sort: (left, right) => (right.value || 0) - (left.value || 0),
    nodeClick: false,
    minAngle: 1.25,
    label: {
        show: labelVisible(true),
        rotate: "radial",
        minAngle: 7,
        overflow: "truncate",
        width: 90,
        fontSize: 10,
        formatter: (params) => params.data?.name || "",
    },
    itemStyle: { borderColor: "#fff", borderWidth: 2 },
    emphasis: { focus: "ancestor" },
}));

const sankeySeries = computed(() => {
    const initialCatalogRoot = props.root?.type === "catalog";
    const graphNodes = (props.graph.nodes || []).filter(
        (node) =>
            !(
                initialCatalogRoot &&
                node.type === "catalog" &&
                Number(node.depth || 0) === 0
            )
    );
    const nodeById = new Map(graphNodes.map((node) => [node.id, node]));
    const automaticLabelIds = new Set();
    const nodesByDepth = new Map();
    graphNodes.forEach((node) => {
        const depth = initialCatalogRoot
            ? Math.max(0, Number(node.depth || 0) - 1)
            : Number(node.depth || 0);
        if (!nodesByDepth.has(depth)) nodesByDepth.set(depth, []);
        nodesByDepth.get(depth).push(node);
    });
    nodesByDepth.forEach((depthNodes) => {
        depthNodes
            .sort(
                (left, right) =>
                    Number(right.value || 0) - Number(left.value || 0)
            )
            .slice(0, 12)
            .forEach((node) => automaticLabelIds.add(node.id));
    });
    const nodes = graphNodes.map((node) => {
        const selected = props.selectedNode?.id === node.id;
        return {
            id: node.id,
            name: node.id,
            value: node.value,
            depth: initialCatalogRoot
                ? Math.max(0, Number(node.depth || 0) - 1)
                : node.depth,
            itemStyle: {
                color: stableNodeColor(node),
                borderColor: selected ? "#ffb000" : "#ffffff",
                borderWidth: selected ? 4 : 1,
                shadowBlur: selected ? 12 : 0,
                shadowColor: selected ? "rgba(23, 38, 61, 0.5)" : "transparent",
            },
            label: { fontWeight: selected ? 800 : 500 },
            __node: { ...node, objectiveCount: node.value },
        };
    });
    const nodeIds = new Set(nodes.map((node) => node.id));
    const links = (props.graph.links || [])
        .filter(
            (link) =>
                link.relationType === "hierarchy" &&
                link.isOfficial &&
                nodeIds.has(link.source) &&
                nodeIds.has(link.target)
        )
        .map((link) => ({
            source: link.source,
            target: link.target,
            value: Math.max(1, link.value || 1),
            __tooltip: `${nodeById.get(link.source)?.name || "Origen"} → ${
                nodeById.get(link.target)?.name || "Destino"
            }\n${Number(link.value || 0).toLocaleString(
                "es-CL"
            )} objetivos únicos\nRelación jerárquica oficial`,
        }));
    return {
        type: "sankey",
        data: nodes,
        links,
        left: 18,
        right: 18,
        top: 18,
        bottom: 18,
        nodeWidth: 14,
        nodeGap: 6,
        nodeAlign: "justify",
        draggable: true,
        layoutIterations: props.reducedMotion ? 0 : 32,
        label: {
            show: props.labelMode !== "hide",
            color: "#33445b",
            fontSize: 10,
            overflow: "truncate",
            width: 108,
            formatter: (params) => {
                const node = params.data?.__node;
                if (
                    props.labelMode === "automatic" &&
                    !automaticLabelIds.has(params.data?.id)
                ) {
                    return "";
                }
                return node?.code || node?.name || "";
            },
        },
        lineStyle: { color: "gradient", opacity: 0.28, curveness: 0.5 },
        emphasis: { focus: "adjacency" },
    };
});

const radialSeries = computed(() => ({
    type: "tree",
    data: [chartRoot.value],
    layout: "radial",
    top: 34,
    right: 80,
    bottom: 34,
    left: 80,
    symbol: "circle",
    symbolSize: (value, params) => {
        const count = Number(params?.data?.value || 0);
        return Math.max(5, Math.min(17, 5 + Math.log10(count + 1) * 3));
    },
    roam: true,
    expandAndCollapse: true,
    initialTreeDepth: props.treeDepth,
    lineStyle: { color: "#b8c7d6", width: 1.2, curveness: 0.22 },
    itemStyle: { borderColor: "#fff", borderWidth: 1.5 },
    label: {
        show: labelVisible(false),
        position: "right",
        verticalAlign: "middle",
        align: "left",
        color: "#33445b",
        fontSize: 9,
        overflow: "truncate",
        width: 96,
    },
    leaves: {
        label: { show: labelVisible(true), position: "right" },
    },
    emphasis: { focus: "descendant" },
}));

const option = computed(() => ({
    ...common.value,
    series: [
        props.variant === "treemap"
            ? treemapSeries.value
            : props.variant === "sunburst"
            ? sunburstSeries.value
            : props.variant === "sankey"
            ? sankeySeries.value
            : radialSeries.value,
    ],
}));

const nodeFromEvent = (params) => params?.data?.__node || null;
const handleClick = (params) => {
    const node = nodeFromEvent(params);
    if (!node) return;
    emit("select-node", node);
    if (isObjectiveNode(node)) emit("open-objective", node);
    else if (
        props.variant !== "radial_tree" ||
        !Array.isArray(node.children) ||
        !node.children.length ||
        node.metadata?.children_truncated
    ) {
        emit("drilldown", node);
    }
};

const center = () => resize();
const reset = () => {
    const instance = chartInstance();
    instance?.setOption?.(option.value, { notMerge: true });
    scheduleResize();
};
const exportImage = () =>
    captureChart({
        type: "png",
        pixelRatio: 2,
        backgroundColor: "#ffffff",
        excludeComponents: ["toolbox"],
    });

watch(option, scheduleResize, { flush: "post" });

defineExpose({ center, reset, exportImage });
</script>

<template>
    <div
        ref="chartHost"
        class="cv-echart"
        role="img"
        aria-label="Visualización curricular interactiva"
    >
        <VChart
            ref="chart"
            class="cv-echart__canvas"
            :option="option"
            :autoresize="{ throttle: 100 }"
            @click="handleClick"
        />
    </div>
</template>

<style scoped>
.cv-echart,
.cv-echart__canvas {
    width: 100%;
    height: 100%;
    min-height: 560px;
}

@media (max-width: 767px) {
    .cv-echart,
    .cv-echart__canvas {
        min-height: 470px;
    }
}
</style>
