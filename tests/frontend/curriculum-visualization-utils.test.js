import { describe, expect, it } from "vitest";
import {
    CURRICULUM_HIERARCHY_PRESETS,
    DEFAULT_HIERARCHY,
    accessibleVisualizationSummary,
    canonicalParams,
    contrastRatio,
    contrastTextColor,
    findHierarchyNode,
    flattenHierarchy,
    formatObjectiveCount,
    graphFromHierarchy,
    hierarchyToChartNode,
    nodeTooltipText,
    nodeTypeLabel,
    normalizeCurriculumNode,
    normalizeVisualizationPayload,
    paramsSignature,
    stableNodeColor,
} from "../../resources/js/utils/curriculum-visualization";

const rawPayload = () => ({
    meta: {
        scope: "filtered",
        view: "treemap",
        total_objectives: "12",
        filtered_total_objectives: "64",
        total_subjects: 1,
        total_curricular_groups: 2,
        available_objectives: 10,
        unavailable_objectives: 2,
        hierarchy: ["subject", "curricular_group", "grade", "objective"],
        max_depth: 4,
        leaves_included: true,
        aggregated: false,
        leaf_threshold: 500,
        graph_node_limit: 300,
        graph_total_nodes: 3,
        graph_truncated: false,
    },
    root: {
        id: "subject:root",
        entity_id: 10,
        parent_id: null,
        type: "subject",
        name: "Filosofía",
        short_name: "FIL",
        code: "FIL",
        value: 12,
        objective_count: 12,
        available_count: 10,
        unavailable_count: 2,
        percentage_of_parent: 100,
        depth: 0,
        has_children: true,
        path: [
            {
                id: "subject:root",
                type: "subject",
                name: "Filosofía",
                code: "FIL",
            },
        ],
        metadata: { subject_code: "FIL" },
        children: [
            {
                id: "curricular_group:ethics",
                parent_id: "subject:root",
                type: "curricular_group",
                name: "Ética",
                objective_count: 12,
                available_count: 10,
                unavailable_count: 2,
                percentage_of_parent: 100,
                depth: 1,
                has_children: false,
                children: [],
                path: [
                    { id: "subject:root", type: "subject", name: "Filosofía" },
                    {
                        id: "curricular_group:ethics",
                        type: "curricular_group",
                        name: "Ética",
                    },
                ],
            },
        ],
    },
    graph: {
        nodes: [
            {
                id: "subject:root",
                entity_id: 10,
                name: "Filosofía",
                code: "FIL",
                type: "subject",
                value: 12,
                depth: 0,
                category: "subject",
                has_children: true,
                metadata: {
                    available_count: 10,
                    unavailable_count: 2,
                    path: [
                        {
                            id: "subject:root",
                            type: "subject",
                            name: "Filosofía",
                            code: "FIL",
                        },
                    ],
                },
            },
            {
                id: "curricular_group:ethics",
                name: "Ética",
                type: "curricular_group",
                value: 12,
                depth: 1,
                has_children: false,
            },
        ],
        links: [
            {
                source: "subject:root",
                target: "curricular_group:ethics",
                value: "12",
                relation_type: "hierarchy",
                is_official: true,
            },
        ],
        node_limit: 300,
        total_nodes: 2,
        truncated: false,
    },
});

describe("normalización del contrato de visualización", () => {
    it("normaliza snake_case, números, hijos y grafo sin perder el total filtrado", () => {
        const normalized = normalizeVisualizationPayload(rawPayload());

        expect(normalized.meta).toMatchObject({
            totalObjectives: 12,
            filteredTotalObjectives: 64,
            totalSubjects: 1,
            maxDepth: 4,
            leavesIncluded: true,
        });
        expect(normalized.root).toMatchObject({
            id: "subject:root",
            entityId: 10,
            shortName: "FIL",
            objectiveCount: 12,
            availableCount: 10,
            hasChildren: true,
        });
        expect(normalized.root.children[0]).toMatchObject({
            id: "curricular_group:ethics",
            parentId: "subject:root",
            objectiveCount: 12,
        });
        expect(normalized.graph.nodes).toHaveLength(2);
        expect(normalized.graph.nodes[0]).toMatchObject({
            objectiveCount: 12,
            availableCount: 10,
            unavailableCount: 2,
            path: [
                expect.objectContaining({
                    type: "subject",
                    name: "Filosofía",
                }),
            ],
        });
        expect(normalized.graph.links[0]).toEqual({
            source: "subject:root",
            target: "curricular_group:ethics",
            value: 12,
            relationType: "hierarchy",
            isOfficial: true,
        });
    });

    it("admite el envoltorio data de Axios y valores ausentes de forma segura", () => {
        const normalized = normalizeVisualizationPayload({
            data: rawPayload(),
        });
        const empty = normalizeVisualizationPayload();

        expect(normalized.meta.totalObjectives).toBe(12);
        expect(empty.meta.totalObjectives).toBe(0);
        expect(empty.root.children).toEqual([]);
        expect(empty.graph).toMatchObject({
            nodes: [],
            links: [],
            truncated: false,
        });
    });

    it("infiere hijos solo desde el arreglo y conserva metadatos sin mutar la entrada", () => {
        const source = {
            id: "catalog:a",
            metadata: { scope: "filtered" },
            children: [{ id: "subject:b", objective_count: "3" }],
        };
        const normalized = normalizeCurriculumNode(source);

        normalized.metadata.scope = "catalog";
        expect(normalized.hasChildren).toBe(true);
        expect(normalized.children[0].parentId).toBe("catalog:a");
        expect(normalized.children[0].objectiveCount).toBe(3);
        expect(source.metadata.scope).toBe("filtered");
    });
});

describe("jerarquía, colores y textos", () => {
    it("mantiene la jerarquía predeterminada y presets declarativos sin duplicados", () => {
        expect(DEFAULT_HIERARCHY).toBe(
            "subject,curricular_group,grade,objective"
        );
        expect(CURRICULUM_HIERARCHY_PRESETS).toHaveLength(5);
        expect(
            new Set(CURRICULUM_HIERARCHY_PRESETS.map(({ value }) => value)).size
        ).toBe(5);
        expect(
            CURRICULUM_HIERARCHY_PRESETS.every(
                ({ value }) => value.split(",").at(-1) === "objective"
            )
        ).toBe(true);
    });

    it("asigna el mismo color estable a nodos de la misma asignatura", () => {
        const first = {
            id: "axis:a",
            path: [{ id: "subject:fil", type: "subject", name: "Filosofía" }],
        };
        const second = {
            id: "grade:b",
            path: [{ id: "subject:fil", type: "subject", name: "Filosofía" }],
        };

        expect(stableNodeColor(first)).toBe(stableNodeColor(first));
        expect(stableNodeColor(first)).toBe(stableNodeColor(second));
        expect(stableNodeColor(first)).toMatch(/^#[0-9a-f]{6}$/i);
        expect(stableNodeColor(first, 2)).toMatch(/^#[0-9a-f]{6}$/i);
        expect(stableNodeColor(first, 2)).not.toBe(stableNodeColor(first));
    });

    it("selecciona texto con contraste WCAG suficiente sobre los colores usados", () => {
        const representatives = new Map();
        for (
            let index = 0;
            index < 1000 && representatives.size < 12;
            index += 1
        ) {
            const node = { id: `contrast-node:${index}` };
            representatives.set(stableNodeColor(node), node);
        }

        expect(representatives.size).toBe(12);
        for (const node of representatives.values()) {
            for (let depth = 0; depth <= 5; depth += 1) {
                const background = stableNodeColor(node, depth);
                const foreground = contrastTextColor(background);
                expect(["#000000", "#ffffff"]).toContain(foreground);
                expect(
                    contrastRatio(foreground, background),
                    `${foreground} sobre ${background} en profundidad ${depth}`
                ).toBeGreaterThanOrEqual(4.5);
            }
        }
    });

    it("respeta la denominación oficial Núcleo para Parvularia", () => {
        expect(
            nodeTypeLabel({
                type: "subject",
                metadata: {
                    classification_label: "Núcleo",
                    semantic_type: "nucleus",
                },
            })
        ).toBe("Núcleo");
        expect(
            nodeTypeLabel({
                type: "subject",
                metadata: { semantic_type: "nucleus" },
            })
        ).toBe("Núcleo");
    });

    it("convierte la jerarquía al formato gráfico sin perder la referencia normalizada", () => {
        const root = normalizeVisualizationPayload(rawPayload()).root;
        const chart = hierarchyToChartNode(root);

        expect(chart).toMatchObject({
            id: "subject:root",
            name: "FIL",
            value: 12,
        });
        expect(chart.__node).toBe(root);
        expect(chart.children[0].__node).toBe(root.children[0]);
        expect(chart.children[0].itemStyle.color).not.toBe(
            chart.itemStyle.color
        );

        const selected = hierarchyToChartNode(root, 0, root.children[0].id);
        expect(selected.children[0].itemStyle).toMatchObject({
            borderColor: "#ffb000",
            borderWidth: 5,
        });
        expect(selected.itemStyle.borderColor).toBe("#ffffff");
    });

    it("aplana, encuentra y resume categorías de forma accesible", () => {
        const normalized = normalizeVisualizationPayload(rawPayload());
        normalized.meta.aggregated = true;

        expect(flattenHierarchy(normalized.root).map(({ id }) => id)).toEqual([
            "subject:root",
            "curricular_group:ethics",
        ]);
        expect(
            findHierarchyNode(normalized.root, "curricular_group:ethics")?.name
        ).toBe("Ética");
        expect(findHierarchyNode(normalized.root, "missing")).toBeNull();

        const summary = accessibleVisualizationSummary(normalized);
        expect(summary).toContain("12 objetivos");
        expect(summary).toContain("Ética");
        expect(summary).toContain("selecciona una categoría");
        expect(summary).toContain("no importancia curricular");
    });

    it("explica el conteo y la semántica en el tooltip", () => {
        const node = normalizeVisualizationPayload(rawPayload()).root
            .children[0];
        const tooltip = nodeTooltipText(node);

        expect(formatObjectiveCount(1)).toBe("1 objetivo");
        expect(formatObjectiveCount(12)).toBe("12 objetivos");
        expect(tooltip).toContain("Ética");
        expect(tooltip).toContain("Cantidad: 12 objetivos");
        expect(tooltip).toContain("no importancia curricular");
    });
});

describe("serialización y límites del grafo", () => {
    it("canoniza parámetros, elimina vacíos y conserva cero y falso", () => {
        const input = {
            view: "treemap",
            query: "filosofía",
            page: null,
            empty: "",
            include_leaves: false,
            max_depth: 0,
        };

        expect(Object.keys(canonicalParams(input))).toEqual([
            "include_leaves",
            "max_depth",
            "query",
            "view",
        ]);
        expect(paramsSignature(input)).toBe(
            paramsSignature({
                max_depth: 0,
                include_leaves: false,
                view: "treemap",
                query: "filosofía",
            })
        );
    });

    it("limita el grafo de forma determinista e informa truncamiento", () => {
        const root = normalizeVisualizationPayload(rawPayload()).root;
        root.children[0].children = [
            normalizeCurriculumNode(
                {
                    id: "grade:fourth",
                    type: "grade",
                    name: "4° medio",
                    objective_count: 12,
                    children: [],
                },
                root.children[0].id
            ),
        ];
        root.children[0].hasChildren = true;

        const limited = graphFromHierarchy(root, 2);
        expect(limited.nodes.map(({ id }) => id)).toEqual([
            "subject:root",
            "curricular_group:ethics",
        ]);
        expect(limited.links).toEqual([
            expect.objectContaining({
                source: "subject:root",
                target: "curricular_group:ethics",
                relationType: "hierarchy",
                isOfficial: true,
            }),
        ]);
        expect(limited.truncated).toBe(true);
    });
});
