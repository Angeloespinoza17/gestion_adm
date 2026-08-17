export const CURRICULUM_VIEW_KEYS = Object.freeze([
    "table",
    "treemap",
    "sunburst",
    "circle_packing",
    "sankey",
    "icicle",
    "radial_tree",
    "network",
    "mind_map",
]);

export const GRAPH_VIEW_KEYS = Object.freeze(
    CURRICULUM_VIEW_KEYS.filter((view) => view !== "table")
);

export const DEFAULT_HIERARCHY = "subject,curricular_group,grade,objective";

export const CURRICULUM_HIERARCHY_PRESETS = Object.freeze([
    {
        value: DEFAULT_HIERARCHY,
        label: "Asignatura → Eje/Núcleo → Grado → Objetivo",
        shortLabel: "Asignatura y eje",
    },
    {
        value: "education_level,grade,subject,curricular_group,objective",
        label: "Nivel → Grado → Asignatura → Eje/Núcleo → Objetivo",
        shortLabel: "Nivel educativo",
    },
    {
        value: "formation,subject,grade,curricular_group,objective",
        label: "Formación → Asignatura → Grado → Eje/Núcleo → Objetivo",
        shortLabel: "Formación",
    },
    {
        value: "objective_type,subject,curricular_group,objective",
        label: "Tipo → Asignatura → Eje/Núcleo → Objetivo",
        shortLabel: "Tipo de objetivo",
    },
    {
        value: "source,subject,grade,objective",
        label: "Fuente → Asignatura → Grado → Objetivo",
        shortLabel: "Fuente",
    },
]);

export const CURRICULUM_NODE_LABELS = Object.freeze({
    catalog: "Catálogo",
    education_level: "Nivel educativo",
    grade: "Grado",
    formation: "Formación",
    subject: "Asignatura",
    ambit: "Ámbito",
    nucleus: "Núcleo",
    axis: "Eje",
    curricular_group: "Agrupador curricular",
    objective_type: "Tipo de objetivo",
    source: "Fuente",
    status: "Estado",
    objective: "Objetivo de Aprendizaje",
});

const PALETTE = Object.freeze([
    "#245486",
    "#27718a",
    "#24735a",
    "#79589f",
    "#9a6f2a",
    "#426d7c",
    "#805769",
    "#3d6a91",
    "#537b52",
    "#835f32",
    "#4e5f94",
    "#2e7d76",
]);

const toNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
};

const text = (value, fallback = "") => {
    if (value === null || value === undefined) return fallback;
    return String(value);
};

export const normalizePathItem = (item = {}) => ({
    id: text(item.id),
    type: text(item.type, "catalog"),
    name: text(item.name, "Sin clasificación"),
    code:
        item.code === null || item.code === undefined ? null : text(item.code),
});

export const normalizeCurriculumNode = (node = {}, parentId = null) => {
    const normalized = {
        id: text(node.id),
        entityId:
            node.entity_id ?? node.entityId ?? node.metadata?.public_id ?? null,
        parentId: node.parent_id ?? node.parentId ?? parentId,
        type: text(node.type, "curricular_group"),
        name: text(node.name, "Sin clasificación"),
        shortName: node.short_name ?? node.shortName ?? null,
        code: node.code ?? null,
        description: node.description ?? null,
        value: toNumber(node.value ?? node.objective_count, 0),
        objectiveCount: toNumber(
            node.objective_count ?? node.objectiveCount ?? node.value,
            0
        ),
        availableCount: toNumber(
            node.available_count ?? node.availableCount,
            0
        ),
        unavailableCount: toNumber(
            node.unavailable_count ?? node.unavailableCount,
            0
        ),
        percentageOfParent: toNumber(
            node.percentage_of_parent ?? node.percentageOfParent,
            0
        ),
        depth: toNumber(node.depth, 0),
        hasChildren: Boolean(
            node.has_children ??
                node.hasChildren ??
                (Array.isArray(node.children) && node.children.length)
        ),
        path: Array.isArray(node.path) ? node.path.map(normalizePathItem) : [],
        metadata:
            node.metadata && typeof node.metadata === "object"
                ? { ...node.metadata }
                : {},
        children: [],
    };

    normalized.children = Array.isArray(node.children)
        ? node.children.map((child) =>
              normalizeCurriculumNode(child, normalized.id)
          )
        : [];

    if (!normalized.value) normalized.value = normalized.objectiveCount;
    return normalized;
};

const normalizeGraphNode = (node = {}) => ({
    id: text(node.id),
    entityId: node.entity_id ?? node.entityId ?? null,
    name: text(node.name, "Sin clasificación"),
    code: node.code ?? null,
    type: text(node.type, "curricular_group"),
    value: toNumber(node.value ?? node.objective_count, 0),
    depth: toNumber(node.depth, 0),
    category: text(node.category ?? node.type, "curricular_group"),
    hasChildren: Boolean(node.has_children ?? node.hasChildren),
    objectiveCount: toNumber(node.objective_count ?? node.value, 0),
    availableCount: toNumber(
        node.available_count ?? node.metadata?.available_count,
        0
    ),
    unavailableCount: toNumber(
        node.unavailable_count ?? node.metadata?.unavailable_count,
        0
    ),
    percentageOfParent: toNumber(
        node.percentage_of_parent ?? node.metadata?.percentage_of_parent,
        0
    ),
    path: Array.isArray(node.path ?? node.metadata?.path)
        ? (node.path ?? node.metadata?.path).map(normalizePathItem)
        : [],
    metadata:
        node.metadata && typeof node.metadata === "object"
            ? { ...node.metadata }
            : {},
});

const normalizeGraphLink = (link = {}) => ({
    source: text(link.source),
    target: text(link.target),
    value: toNumber(link.value, 1),
    relationType: text(link.relation_type ?? link.relationType, "hierarchy"),
    isOfficial: Boolean(link.is_official ?? link.isOfficial ?? true),
});

export const normalizeVisualizationPayload = (payload = {}) => {
    const body =
        payload?.data && !Array.isArray(payload.data) ? payload.data : payload;
    const rawMeta = body?.meta || payload?.meta || {};
    const rawGraph = body?.graph || {};
    const root = normalizeCurriculumNode(body?.root || {});

    return {
        meta: {
            scope: text(rawMeta.scope, "filtered"),
            view: text(rawMeta.view),
            totalObjectives: toNumber(
                rawMeta.total_objectives ?? rawMeta.filtered_total_objectives,
                root.objectiveCount
            ),
            filteredTotalObjectives: toNumber(
                rawMeta.filtered_total_objectives ?? rawMeta.total_objectives,
                root.objectiveCount
            ),
            totalSubjects: toNumber(rawMeta.total_subjects, 0),
            totalCurricularGroups: toNumber(rawMeta.total_curricular_groups, 0),
            availableObjectives: toNumber(rawMeta.available_objectives, 0),
            unavailableObjectives: toNumber(rawMeta.unavailable_objectives, 0),
            hierarchy: Array.isArray(rawMeta.hierarchy)
                ? rawMeta.hierarchy.map(String)
                : text(rawMeta.hierarchy).split(",").filter(Boolean),
            maxDepth: toNumber(rawMeta.max_depth, 4),
            leavesIncluded: Boolean(rawMeta.leaves_included),
            aggregated: Boolean(rawMeta.aggregated),
            leafThreshold: toNumber(rawMeta.leaf_threshold, 500),
            rootNode: rawMeta.root_node ?? null,
            graphNodeLimit: toNumber(
                rawMeta.graph_node_limit ?? rawGraph.node_limit,
                0
            ),
            graphTotalNodes: toNumber(
                rawMeta.graph_total_nodes ?? rawGraph.total_nodes,
                0
            ),
            graphTruncated: Boolean(
                rawMeta.graph_truncated ?? rawGraph.truncated
            ),
            raw: rawMeta,
        },
        root,
        graph: {
            nodes: Array.isArray(rawGraph.nodes)
                ? rawGraph.nodes.map(normalizeGraphNode)
                : [],
            links: Array.isArray(rawGraph.links)
                ? rawGraph.links.map(normalizeGraphLink)
                : [],
            nodeLimit: toNumber(rawGraph.node_limit, 0),
            totalNodes: toNumber(rawGraph.total_nodes, 0),
            truncated: Boolean(rawGraph.truncated),
        },
    };
};

export const flattenHierarchy = (root) => {
    if (!root?.id) return [];
    const rows = [];
    const visit = (node) => {
        rows.push(node);
        node.children?.forEach(visit);
    };
    visit(root);
    return rows;
};

export const findHierarchyNode = (root, id) => {
    if (!root || !id) return null;
    if (root.id === id) return root;
    for (const child of root.children || []) {
        const found = findHierarchyNode(child, id);
        if (found) return found;
    }
    return null;
};

export const isObjectiveNode = (node) => node?.type === "objective";

export const nodeTypeLabel = (node) =>
    node?.metadata?.classification_label ||
    node?.metadata?.type_label ||
    node?.metadata?.group_label ||
    CURRICULUM_NODE_LABELS[node?.metadata?.semantic_type] ||
    CURRICULUM_NODE_LABELS[node?.type] ||
    "Categoría curricular";

export const formatObjectiveCount = (value) =>
    `${toNumber(value).toLocaleString("es-CL")} ${
        toNumber(value) === 1 ? "objetivo" : "objetivos"
    }`;

const hashString = (value) => {
    let hash = 2166136261;
    const source = String(value || "curriculum");
    for (let index = 0; index < source.length; index += 1) {
        hash ^= source.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
};

const colorKey = (node) => {
    const subjectPath = node?.path?.find((item) => item.type === "subject");
    return (
        node?.metadata?.subject_id ||
        node?.metadata?.subject_code ||
        subjectPath?.id ||
        subjectPath?.code ||
        node?.id ||
        node?.name
    );
};

export const stableNodeColor = (node, offset = 0) => {
    const hash = hashString(colorKey(node));
    const base = PALETTE[hash % PALETTE.length];
    if (!offset) return base;

    const amount = Math.min(0.44, Math.max(0, offset * 0.09));
    const parsed = base
        .match(/[a-f\d]{2}/gi)
        ?.map((entry) => parseInt(entry, 16));
    if (!parsed || parsed.length !== 3) return base;
    const mixed = parsed.map((channel) =>
        Math.round(channel + (255 - channel) * amount)
    );
    return `#${mixed
        .map((channel) => channel.toString(16).padStart(2, "0"))
        .join("")}`;
};

const colorChannels = (color) => {
    const normalized = String(color || "").replace("#", "");
    const expanded =
        normalized.length === 3
            ? normalized
                  .split("")
                  .map((channel) => `${channel}${channel}`)
                  .join("")
            : normalized;
    if (!/^[0-9a-f]{6}$/i.test(expanded)) return [255, 255, 255];
    return expanded.match(/.{2}/g).map((channel) => parseInt(channel, 16));
};

export const relativeLuminance = (color) => {
    const channels = colorChannels(color).map((channel) => {
        const normalized = channel / 255;
        return normalized <= 0.03928
            ? normalized / 12.92
            : ((normalized + 0.055) / 1.055) ** 2.4;
    });
    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
};

export const contrastRatio = (foreground, background) => {
    const lighter = Math.max(
        relativeLuminance(foreground),
        relativeLuminance(background)
    );
    const darker = Math.min(
        relativeLuminance(foreground),
        relativeLuminance(background)
    );
    return (lighter + 0.05) / (darker + 0.05);
};

export const contrastTextColor = (background) => {
    const dark = "#000000";
    const light = "#ffffff";
    return contrastRatio(dark, background) >= contrastRatio(light, background)
        ? dark
        : light;
};

export const hierarchyToChartNode = (node, depth = 0, selectedId = null) => {
    const selected = Boolean(selectedId && node.id === selectedId);
    const color = stableNodeColor(node, depth);
    return {
        id: node.id,
        name: node.shortName || node.name,
        value: Math.max(0, node.value || node.objectiveCount || 0),
        itemStyle: {
            color,
            borderColor: selected ? "#ffb000" : "#ffffff",
            borderWidth: selected ? 5 : 1,
            shadowBlur: selected ? 14 : 0,
            shadowColor: selected ? "rgba(23, 38, 61, 0.5)" : "transparent",
        },
        label: {
            color: contrastTextColor(color),
            fontWeight: selected ? 800 : 500,
        },
        upperLabel: {
            color: contrastTextColor(color),
            fontWeight: selected ? 800 : 700,
        },
        __node: node,
        children: (node.children || []).map((child) =>
            hierarchyToChartNode(child, depth + 1, selectedId)
        ),
    };
};

export const nodeTooltipText = (node) => {
    if (!node) return "";
    const path = (node.path || [])
        .map((item) => item.name)
        .filter(Boolean)
        .join(" → ");
    const lines = [
        node.code ? `${node.code} · ${node.name}` : node.name,
        nodeTypeLabel(node),
        path ? `Ruta: ${path}` : "",
        `Cantidad: ${formatObjectiveCount(node.objectiveCount)}`,
        node.percentageOfParent
            ? `${node.percentageOfParent.toLocaleString("es-CL", {
                  maximumFractionDigits: 1,
              })}% del nivel anterior`
            : "",
        `Disponibles: ${node.availableCount.toLocaleString("es-CL")}`,
        `No disponibles: ${node.unavailableCount.toLocaleString("es-CL")}`,
        "El tamaño representa cantidad de objetivos, no importancia curricular.",
    ];
    return lines.filter(Boolean).join("\n");
};

export const graphNodeTooltipText = (node) => {
    if (!node) return "";
    return [
        node.code ? `${node.code} · ${node.name}` : node.name,
        nodeTypeLabel(node),
        formatObjectiveCount(node.value),
        "Solo se muestran relaciones explícitas registradas.",
    ].join("\n");
};

export const accessibleVisualizationSummary = (data) => {
    const meta = data?.meta || {};
    const root = data?.root;
    const children = [...(root?.children || [])].sort(
        (left, right) => right.objectiveCount - left.objectiveCount
    );
    const parts = [
        `La visualización contiene ${formatObjectiveCount(
            meta.totalObjectives ?? root?.objectiveCount
        )}.`,
    ];
    if (children.length) {
        parts.push(
            `Se distribuye inicialmente en ${children.length.toLocaleString(
                "es-CL"
            )} categorías.`
        );
        parts.push(
            `La categoría con mayor cantidad es ${
                children[0].name
            }, con ${formatObjectiveCount(children[0].objectiveCount)}.`
        );
    }
    if (meta.aggregated) {
        parts.push(
            "La vista está agregada; selecciona una categoría para profundizar."
        );
    }
    parts.push(
        "El tamaño de los elementos representa cantidad de objetivos y no importancia curricular."
    );
    return parts.join(" ");
};

export const canonicalParams = (params = {}) =>
    Object.fromEntries(
        Object.entries(params)
            .filter(
                ([, value]) =>
                    value !== null && value !== undefined && value !== ""
            )
            .sort(([left], [right]) => left.localeCompare(right))
    );

export const paramsSignature = (params = {}) =>
    JSON.stringify(canonicalParams(params));

export const graphFromHierarchy = (root, maximum = 300) => {
    const nodes = [];
    const links = [];
    const visit = (node) => {
        if (!node?.id || nodes.length >= maximum) return;
        nodes.push({
            id: node.id,
            entityId: node.entityId,
            name: node.name,
            code: node.code,
            type: node.type,
            value: node.objectiveCount,
            depth: node.depth,
            category: node.type,
            hasChildren: node.hasChildren,
            metadata: { ...node.metadata, hierarchyNode: node },
        });
        for (const child of node.children || []) {
            if (nodes.length >= maximum) break;
            links.push({
                source: node.id,
                target: child.id,
                value: child.objectiveCount,
                relationType: "hierarchy",
                isOfficial: true,
            });
            visit(child);
        }
    };
    visit(root);
    return {
        nodes,
        links,
        truncated: flattenHierarchy(root).length > nodes.length,
    };
};
