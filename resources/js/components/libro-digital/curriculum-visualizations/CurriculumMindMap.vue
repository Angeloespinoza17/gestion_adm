<script setup>
import { computed, ref } from "vue";
import { Background, BackgroundVariant } from "@vue-flow/background";
import { Controls } from "@vue-flow/controls";
import { MarkerType, Position, VueFlow } from "@vue-flow/core";
import { MiniMap } from "@vue-flow/minimap";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import "@vue-flow/controls/dist/style.css";
import "@vue-flow/minimap/dist/style.css";
import {
    isObjectiveNode,
    nodeTypeLabel,
    stableNodeColor,
} from "../../../utils/curriculum-visualization";
import { svgStringToDataUrl } from "../../../utils/curriculum-visualization-export";

const props = defineProps({
    graph: { type: Object, default: () => ({ nodes: [], links: [] }) },
    labelMode: { type: String, default: "automatic" },
    reducedMotion: { type: Boolean, default: false },
    selectedNode: { type: Object, default: null },
});
const emit = defineEmits(["select-node", "drilldown", "open-objective"]);

const flowInstance = ref(null);
const search = ref("");
const collapsedIds = ref(new Set());

const sourceNodes = computed(() => props.graph.nodes || []);
const officialLinks = computed(() =>
    (props.graph.links || []).filter(
        (link) => link.relationType === "hierarchy" && link.isOfficial
    )
);
const childrenByParent = computed(() => {
    const result = new Map();
    for (const link of officialLinks.value) {
        if (!result.has(link.source)) result.set(link.source, []);
        result.get(link.source).push(link.target);
    }
    return result;
});
const hiddenIds = computed(() => {
    const hidden = new Set();
    const hideDescendants = (id) => {
        for (const child of childrenByParent.value.get(id) || []) {
            if (hidden.has(child)) continue;
            hidden.add(child);
            hideDescendants(child);
        }
    };
    for (const id of collapsedIds.value) hideDescendants(id);
    return hidden;
});
const visibleSourceNodes = computed(() =>
    sourceNodes.value.filter((node) => !hiddenIds.value.has(node.id))
);
const searchMatches = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase("es");
    if (!needle) return new Set();
    return new Set(
        visibleSourceNodes.value
            .filter((node) =>
                [node.name, node.code]
                    .filter(Boolean)
                    .some((value) =>
                        String(value).toLocaleLowerCase("es").includes(needle)
                    )
            )
            .map((node) => node.id)
    );
});
const positions = computed(() => {
    const byDepth = new Map();
    for (const node of visibleSourceNodes.value) {
        const depth = Number(node.depth || 0);
        if (!byDepth.has(depth)) byDepth.set(depth, []);
        byDepth.get(depth).push(node);
    }
    const result = new Map();
    const depths = [...byDepth.keys()].sort((left, right) => left - right);
    for (const depth of depths) {
        const group = byDepth
            .get(depth)
            .sort((left, right) =>
                String(left.code || left.name).localeCompare(
                    String(right.code || right.name),
                    "es"
                )
            );
        group.forEach((node, index) => {
            result.set(node.id, {
                x: (depth - depths[0]) * 285,
                y: (index - (group.length - 1) / 2) * 92,
            });
        });
    }
    return result;
});

const nodes = computed(() =>
    visibleSourceNodes.value.map((node) => ({
        id: node.id,
        position: positions.value.get(node.id) || { x: 0, y: 0 },
        sourcePosition: Position.Right,
        targetPosition: Position.Left,
        draggable: true,
        connectable: false,
        selectable: true,
        focusable: true,
        data: {
            node: { ...node, objectiveCount: node.value },
            label: node.code || node.name,
            subtitle: nodeTypeLabel(node),
            count: Number(node.value || 0).toLocaleString("es-CL"),
            color: stableNodeColor(node),
        },
        class: [
            props.selectedNode?.id === node.id ? "is-selected" : "",
            search.value.trim() && !searchMatches.value.has(node.id)
                ? "is-search-dimmed"
                : "",
        ]
            .filter(Boolean)
            .join(" "),
    }))
);

const edges = computed(() => {
    const ids = new Set(visibleSourceNodes.value.map((node) => node.id));
    return officialLinks.value
        .filter(
            (link) =>
                link.relationType === "hierarchy" &&
                link.isOfficial &&
                ids.has(link.source) &&
                ids.has(link.target)
        )
        .map((link, index) => ({
            id: `official-${index}-${link.source}-${link.target}`,
            source: link.source,
            target: link.target,
            type: "smoothstep",
            animated: false,
            markerEnd: MarkerType.ArrowClosed,
            style: { stroke: "#9fb3c7", strokeWidth: 1.25 },
        }));
});

const activate = (node) => {
    if (!node) return;
    emit("select-node", node);
    if (isObjectiveNode(node)) emit("open-objective", node);
    else if ((childrenByParent.value.get(node.id) || []).length) {
        const next = new Set(collapsedIds.value);
        if (next.has(node.id)) next.delete(node.id);
        else next.add(node.id);
        collapsedIds.value = next;
        requestAnimationFrame(center);
    } else if (node.hasChildren || node.metadata?.children_truncated) {
        emit("drilldown", node);
    }
};
const handleNodeClick = ({ node }) => activate(node?.data?.node);
const handleInit = (instance) => {
    flowInstance.value = instance;
    requestAnimationFrame(() => instance.fitView?.({ padding: 0.16 }));
};
const center = () =>
    flowInstance.value?.fitView?.({
        padding: 0.16,
        duration: props.reducedMotion ? 0 : 260,
    });

const escapeXml = (value) =>
    String(value || "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&apos;");
const exportImage = () => {
    const exportNodes = nodes.value;
    if (!exportNodes.length) return null;
    const minimumX = Math.min(...exportNodes.map((node) => node.position.x));
    const maximumX =
        Math.max(...exportNodes.map((node) => node.position.x)) + 210;
    const minimumY = Math.min(...exportNodes.map((node) => node.position.y));
    const maximumY =
        Math.max(...exportNodes.map((node) => node.position.y)) + 68;
    const padding = 70;
    const width = Math.max(600, maximumX - minimumX + padding * 2);
    const height = Math.max(420, maximumY - minimumY + padding * 2);
    const offsetX = padding - minimumX;
    const offsetY = padding - minimumY;
    const nodeMap = new Map(exportNodes.map((node) => [node.id, node]));
    const edgeMarkup = edges.value
        .map((edge) => {
            const source = nodeMap.get(edge.source);
            const target = nodeMap.get(edge.target);
            if (!source || !target) return "";
            const x1 = source.position.x + offsetX + 210;
            const y1 = source.position.y + offsetY + 34;
            const x2 = target.position.x + offsetX;
            const y2 = target.position.y + offsetY + 34;
            const middle = (x1 + x2) / 2;
            return `<path d="M ${x1} ${y1} C ${middle} ${y1}, ${middle} ${y2}, ${x2} ${y2}" fill="none" stroke="#9fb3c7" stroke-width="2"/>`;
        })
        .join("");
    const nodeMarkup = exportNodes
        .map((node) => {
            const x = node.position.x + offsetX;
            const y = node.position.y + offsetY;
            const label = escapeXml(String(node.data.label || "").slice(0, 28));
            const subtitle = escapeXml(
                `${node.data.subtitle} · ${node.data.count}`.slice(0, 42)
            );
            return `<g transform="translate(${x} ${y})"><rect width="210" height="68" rx="12" fill="#ffffff" stroke="${node.data.color}" stroke-width="2"/><rect width="7" height="68" rx="3" fill="${node.data.color}"/><text x="20" y="28" fill="#17263d" font-family="system-ui,sans-serif" font-size="13" font-weight="700">${label}</text><text x="20" y="49" fill="#627187" font-family="system-ui,sans-serif" font-size="10">${subtitle}</text></g>`;
        })
        .join("");
    return svgStringToDataUrl(
        `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"><rect width="100%" height="100%" fill="#f7f9fb"/>${edgeMarkup}${nodeMarkup}</svg>`
    );
};

const reset = () => {
    search.value = "";
    collapsedIds.value = new Set();
    requestAnimationFrame(center);
};
defineExpose({ center, reset, exportImage });
</script>

<template>
    <div class="cv-mind-map">
        <div class="cv-mind-map__toolbar">
            <label>
                <i class="bx bx-search" aria-hidden="true"></i>
                <span class="visually-hidden"
                    >Buscar nodo en el mapa mental</span
                >
                <input
                    v-model="search"
                    type="search"
                    placeholder="Buscar nodo"
                />
            </label>
            <button type="button" @click="collapsedIds = new Set()">
                Expandir todos
            </button>
            <button
                type="button"
                @click="
                    collapsedIds = new Set(
                        [...childrenByParent.keys()].filter(
                            (id) =>
                                !officialLinks.some(
                                    (link) => link.target === id
                                )
                        )
                    )
                "
            >
                Contraer ramas
            </button>
            <span v-if="search"> {{ searchMatches.size }} coincidencias </span>
        </div>
        <VueFlow
            :nodes="nodes"
            :edges="edges"
            :nodes-connectable="false"
            :edges-updatable="false"
            :zoom-on-double-click="false"
            :min-zoom="0.2"
            :max-zoom="2.4"
            fit-view-on-init
            class="cv-mind-map__flow"
            aria-label="Mapa mental curricular de solo lectura"
            @init="handleInit"
            @node-click="handleNodeClick"
        >
            <Background
                :variant="BackgroundVariant.Dots"
                :gap="18"
                :size="1.2"
                pattern-color="#cad7e3"
            />
            <MiniMap
                pannable
                zoomable
                :node-color="(node) => node.data?.color || '#245486'"
            />
            <Controls :show-interactive="false" />

            <template #node-default="{ data }">
                <button
                    type="button"
                    class="cv-mind-map__node"
                    :style="{ '--node-color': data.color }"
                    :title="`${data.label} · ${data.subtitle} · ${data.count} objetivos`"
                    @click.stop="activate(data.node)"
                >
                    <span class="cv-mind-map__accent" aria-hidden="true"></span>
                    <span class="cv-mind-map__copy">
                        <strong>{{ data.label }}</strong>
                        <small>{{ data.subtitle }} · {{ data.count }}</small>
                    </span>
                    <i class="bx bx-chevron-right" aria-hidden="true"></i>
                </button>
            </template>
        </VueFlow>
    </div>
</template>

<style scoped>
.cv-mind-map,
.cv-mind-map__flow {
    width: 100%;
    min-height: 560px;
}

.cv-mind-map {
    position: relative;
    overflow: hidden;
    background: #f7f9fb;
}

.cv-mind-map__toolbar {
    position: absolute;
    z-index: 5;
    top: 8px;
    left: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 4px 14px rgba(20, 39, 65, 0.08);
}

.cv-mind-map__toolbar label {
    position: relative;
}

.cv-mind-map__toolbar label i {
    position: absolute;
    top: 50%;
    left: 8px;
    color: var(--lcd-muted, #627187);
    transform: translateY(-50%);
}

.cv-mind-map__toolbar input,
.cv-mind-map__toolbar button {
    min-height: 33px;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 7px;
    background: #fff;
    color: var(--lcd-ink-soft, #33445b);
    font-size: 0.7rem;
}

.cv-mind-map__toolbar input {
    width: 170px;
    padding: 6px 8px 6px 27px;
}

.cv-mind-map__toolbar button {
    padding: 6px 8px;
    color: var(--lcd-brand-700, #245486);
    font-weight: 700;
}

.cv-mind-map__toolbar > span {
    color: var(--lcd-muted, #627187);
    font-size: 0.65rem;
}

.cv-mind-map__node {
    display: flex;
    width: 210px;
    min-height: 68px;
    align-items: center;
    gap: 10px;
    padding: 9px 11px 9px 0;
    overflow: hidden;
    border: 1px solid color-mix(in srgb, var(--node-color) 75%, #ffffff);
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(20, 39, 65, 0.09);
    color: var(--lcd-ink, #17263d);
    text-align: left;
}

.cv-mind-map__accent {
    width: 7px;
    align-self: stretch;
    flex: 0 0 auto;
    border-radius: 0 4px 4px 0;
    background: var(--node-color);
}

.cv-mind-map__copy {
    min-width: 0;
    flex: 1;
}

.cv-mind-map__copy strong,
.cv-mind-map__copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cv-mind-map__copy strong {
    font-size: 0.77rem;
}

.cv-mind-map__copy small {
    margin-top: 4px;
    color: var(--lcd-muted, #627187);
    font-size: 0.64rem;
}

.cv-mind-map__node > i {
    color: var(--node-color);
    font-size: 1rem;
}

.cv-mind-map__node:hover,
.cv-mind-map__node:focus-visible {
    border-color: var(--node-color);
    box-shadow: 0 7px 19px rgba(20, 39, 65, 0.14);
}

.cv-mind-map__node:focus-visible {
    outline: 3px solid var(--lcd-focus, rgba(46, 105, 156, 0.32));
    outline-offset: 2px;
}

:deep(.vue-flow__node.is-selected .cv-mind-map__node),
:deep(.vue-flow__node.selected .cv-mind-map__node) {
    border-color: #17263d;
    background: #fff8df;
    box-shadow: 0 0 0 4px #ffb000, 0 9px 24px rgba(23, 38, 61, 0.28);
    outline: 2px solid #17263d;
    outline-offset: 3px;
}

:deep(.vue-flow__node.is-search-dimmed) {
    opacity: 0.16;
}

:deep(.vue-flow__controls) {
    overflow: hidden;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    box-shadow: 0 4px 14px rgba(20, 39, 65, 0.08);
}

:deep(.vue-flow__minimap) {
    overflow: hidden;
    border: 1px solid var(--lcd-border, #dbe3eb);
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.94);
}

@media (max-width: 767px) {
    .cv-mind-map,
    .cv-mind-map__flow {
        min-height: 500px;
    }

    :deep(.vue-flow__minimap) {
        display: none;
    }

    .cv-mind-map__toolbar {
        right: 8px;
        flex-wrap: wrap;
    }

    .cv-mind-map__toolbar label,
    .cv-mind-map__toolbar input {
        width: 100%;
    }
}
</style>
