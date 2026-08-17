// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";

vi.mock(
    "../../resources/js/components/libro-digital/curriculum-visualizations/echarts",
    () => ({
        default: {
            name: "VChart",
            props: ["option", "autoresize"],
            emits: ["click"],
            template: "<div class='v-chart-stub'></div>",
        },
    })
);

import CurriculumEChartBase from "../../resources/js/components/libro-digital/curriculum-visualizations/CurriculumEChartBase.vue";

describe("tooltip de enlaces Sankey", () => {
    it("muestra origen, destino y cantidad de OA únicos con texto seguro", () => {
        const root = {
            id: "catalog:root",
            type: "catalog",
            name: "Resultados filtrados",
            objectiveCount: 12,
            value: 12,
            metadata: {},
            path: [],
            children: [],
        };
        const graph = {
            nodes: [
                {
                    id: "subject:fil",
                    name: "Filosofía",
                    code: "FIL",
                    type: "subject",
                    value: 12,
                    depth: 1,
                    metadata: {},
                },
                {
                    id: "group:ethics",
                    name: "Ética & convivencia",
                    code: "ETICA",
                    type: "curricular_group",
                    value: 12,
                    depth: 2,
                    metadata: {},
                },
            ],
            links: [
                {
                    source: "subject:fil",
                    target: "group:ethics",
                    value: 12,
                    relationType: "hierarchy",
                    isOfficial: true,
                },
            ],
        };
        const wrapper = mount(CurriculumEChartBase, {
            props: { variant: "sankey", root, graph },
        });
        const chart = wrapper.getComponent({ name: "VChart" });
        const option = chart.props("option");
        const link = option.series[0].links[0];
        const tooltip = option.tooltip.formatter({
            dataType: "edge",
            data: link,
        });

        expect(tooltip).toContain("Filosofía → Ética & convivencia");
        expect(tooltip).toContain("12 objetivos únicos");
        expect(tooltip).toContain("Relación jerárquica oficial");
        expect(option.tooltip.renderMode).toBe("richText");
    });

    it("limita etiquetas automáticas en columnas densas y permite mover nodos", () => {
        const root = {
            id: "subject:fil",
            type: "subject",
            name: "Filosofía",
            objectiveCount: 120,
            value: 120,
            metadata: {},
            path: [],
            children: [],
        };
        const nodes = Array.from({ length: 18 }, (_, index) => ({
            id: `group:${index}`,
            name: `Agrupador ${index + 1}`,
            code: `AG-${index + 1}`,
            type: "curricular_group",
            value: 18 - index,
            depth: 1,
            metadata: {},
        }));
        const wrapper = mount(CurriculumEChartBase, {
            props: {
                variant: "sankey",
                root,
                graph: { nodes, links: [] },
                labelMode: "automatic",
            },
        });
        const series = wrapper.getComponent({ name: "VChart" }).props("option")
            .series[0];
        const labels = series.data.map((node) =>
            series.label.formatter({ data: node })
        );

        expect(series.draggable).toBe(true);
        expect(labels.filter(Boolean)).toHaveLength(12);
        expect(labels.filter((label) => !label)).toHaveLength(6);
    });

    it("marca el nodo seleccionado con un contorno de alto contraste", () => {
        const selected = {
            id: "subject:fil",
            name: "Filosofía",
            type: "subject",
            value: 64,
            depth: 1,
            metadata: {},
        };
        const wrapper = mount(CurriculumEChartBase, {
            props: {
                variant: "sankey",
                root: {
                    id: "catalog:root",
                    type: "catalog",
                    name: "Catálogo",
                    value: 64,
                    objectiveCount: 64,
                    children: [],
                },
                graph: { nodes: [selected], links: [] },
                selectedNode: selected,
            },
        });
        const node = wrapper
            .getComponent({ name: "VChart" })
            .props("option")
            .series[0].data.find((item) => item.id === selected.id);

        expect(node.itemStyle).toMatchObject({
            borderColor: "#ffb000",
            borderWidth: 4,
        });
        expect(node.itemStyle.shadowBlur).toBeGreaterThan(0);
    });

    it("omite la raíz técnica del catálogo inicialmente y conserva la raíz curricular al profundizar", async () => {
        const catalogRoot = {
            id: "catalog:root",
            type: "catalog",
            name: "Resultados filtrados",
            objectiveCount: 12,
            value: 12,
            metadata: {},
            path: [],
            children: [],
        };
        const graph = {
            nodes: [
                {
                    id: "catalog:root",
                    name: "Catálogo",
                    type: "catalog",
                    value: 12,
                    depth: 0,
                    metadata: {},
                },
                {
                    id: "subject:fil",
                    name: "Filosofía",
                    type: "subject",
                    value: 12,
                    depth: 1,
                    metadata: {},
                },
                {
                    id: "group:ethics",
                    name: "Ética",
                    type: "curricular_group",
                    value: 12,
                    depth: 2,
                    metadata: {},
                },
                {
                    id: "grade:4m",
                    name: "4.º medio",
                    type: "grade",
                    value: 12,
                    depth: 3,
                    metadata: {},
                },
                {
                    id: "objective:one",
                    name: "OA 1",
                    type: "objective",
                    value: 1,
                    depth: 4,
                    metadata: {},
                },
            ],
            links: [
                {
                    source: "catalog:root",
                    target: "subject:fil",
                    value: 12,
                    relationType: "hierarchy",
                    isOfficial: true,
                },
                {
                    source: "subject:fil",
                    target: "group:ethics",
                    value: 12,
                    relationType: "hierarchy",
                    isOfficial: true,
                },
                {
                    source: "group:ethics",
                    target: "grade:4m",
                    value: 12,
                    relationType: "hierarchy",
                    isOfficial: true,
                },
                {
                    source: "grade:4m",
                    target: "objective:one",
                    value: 1,
                    relationType: "hierarchy",
                    isOfficial: true,
                },
            ],
        };
        const wrapper = mount(CurriculumEChartBase, {
            props: { variant: "sankey", root: catalogRoot, graph },
        });
        const series = () =>
            wrapper.getComponent({ name: "VChart" }).props("option").series[0];

        expect(series().data.map((node) => node.id)).not.toContain(
            "catalog:root"
        );
        expect(
            series().links.some((link) => link.source === "catalog:root")
        ).toBe(false);
        expect([...new Set(series().data.map((node) => node.depth))]).toEqual([
            0, 1, 2, 3,
        ]);

        await wrapper.setProps({
            root: {
                ...catalogRoot,
                id: "subject:fil",
                type: "subject",
                name: "Filosofía",
            },
            graph: {
                nodes: graph.nodes
                    .filter((node) => node.type !== "catalog")
                    .map((node) => ({ ...node, depth: node.depth - 1 })),
                links: graph.links.filter(
                    (link) => link.source !== "catalog:root"
                ),
            },
        });
        expect(series().data.map((node) => node.id)).toContain("subject:fil");
        expect(
            series().data.find((node) => node.id === "subject:fil").depth
        ).toBe(0);
    });
});
