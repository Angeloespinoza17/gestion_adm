// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import { nextTick, ref, shallowRef } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";

const harness = vi.hoisted(() => ({ state: null }));
const exportHarness = vi.hoisted(() => ({ exportVisualization: vi.fn() }));

vi.mock("../../resources/js/composables/useCurriculumVisualization", () => ({
    useCurriculumVisualization: () => harness.state,
}));

vi.mock("../../resources/js/utils/curriculum-visualization-export", () => ({
    exportCurriculumVisualizationPdf: exportHarness.exportVisualization,
    safeFilenamePart: (value) => String(value || "catalogo"),
}));

vi.mock(
    "../../resources/js/components/libro-digital/curriculum-visualizations/visualizationRegistry",
    () => ({
        visualizationRegistry: {
            table: {
                label: "Tabla",
                shortLabel: "Tabla",
                icon: "bx-table",
                description: "Tabla paginada",
                component: null,
            },
            treemap: {
                label: "Treemap",
                shortLabel: "Treemap",
                icon: "bx-grid-alt",
                description: "Mapa jerárquico",
                component: {
                    name: "GraphStub",
                    emits: ["select-node", "drilldown", "open-objective"],
                    data: () => ({
                        objective: {
                            id: "objective:hash",
                            entityId: "01HOBJECTIVEPUBLICID000001",
                            type: "objective",
                            code: "FIL-4M-OA-01",
                            name: "FIL-4M-OA-01",
                            objectiveCount: 1,
                        },
                        category: {
                            id: "subject:fil",
                            entityId: 10,
                            type: "subject",
                            name: "Filosofía",
                            objectiveCount: 64,
                            metadata: { filters: { subject_code: "FIL" } },
                        },
                    }),
                    methods: {
                        center() {},
                        reset() {},
                        exportImage() {
                            return "data:image/png;base64,c3R1Yg==";
                        },
                    },
                    template: `
                        <div class="graph-stub">
                            <button class="graph-objective" @click="$emit('open-objective', objective)">OA</button>
                            <button class="graph-category" @click="$emit('select-node', category)">Categoría</button>
                        </div>
                    `,
                },
            },
        },
        visualizationOptions: [
            {
                value: "table",
                text: "Tabla",
                label: "Tabla",
                shortLabel: "Tabla",
                icon: "bx-table",
                description: "Tabla paginada",
                component: null,
            },
            {
                value: "treemap",
                text: "Treemap",
                label: "Treemap",
                shortLabel: "Treemap",
                icon: "bx-grid-alt",
                description: "Mapa jerárquico",
                component: {},
            },
        ],
    })
);

import CurriculumVisualizationPanel from "../../resources/js/components/libro-digital/curriculum-visualizations/CurriculumVisualizationPanel.vue";

const category = {
    id: "subject:fil",
    entityId: 10,
    type: "subject",
    name: "Filosofía",
    code: "FIL",
    objectiveCount: 64,
    availableCount: 64,
    unavailableCount: 0,
    hasChildren: true,
    path: [],
    metadata: {
        filters: { subject_code: "FIL" },
        child_count: 2,
    },
    children: [],
};

const visualizationData = (total = 64) => ({
    meta: {
        totalObjectives: total,
        filteredTotalObjectives: total,
        leavesIncluded: true,
        aggregated: false,
        rootNode: "catalog:root",
        graphTruncated: false,
    },
    root: {
        id: "catalog:root",
        type: "catalog",
        name: "Resultados filtrados",
        objectiveCount: total,
        children: total ? [category] : [],
        path: [],
    },
    graph: {
        nodes: [],
        links: [],
        truncated: false,
    },
});

const makeState = ({ view = "table", data = visualizationData() } = {}) => {
    const activeView = ref(view);
    const hierarchyPreset = ref("subject,curricular_group,grade,objective");
    const scope = ref("filtered");
    const labelMode = ref("automatic");
    const selectedNode = shallowRef(null);
    return {
        activeView,
        hierarchyPreset,
        scope,
        labelMode,
        selectedNode,
        data: shallowRef(data),
        loading: ref(false),
        refreshing: ref(false),
        error: shallowRef(null),
        breadcrumbs: ref([
            { id: null, type: "catalog", name: "Resultados filtrados" },
            {
                id: "subject:fil",
                type: "subject",
                name: "Filosofía",
                code: "FIL",
            },
        ]),
        countMismatch: ref(false),
        setView: vi.fn((value) => {
            activeView.value = value;
        }),
        setHierarchy: vi.fn((value) => {
            hierarchyPreset.value = value;
        }),
        setScope: vi.fn((value) => {
            scope.value = value;
        }),
        drillDown: vi.fn(),
        goToBreadcrumb: vi.fn(),
        resetView: vi.fn(),
        refresh: vi.fn(),
    };
};

const mountPanel = (options = {}) =>
    mount(CurriculumVisualizationPanel, {
        props: {
            requestParams: {
                school_id: 1,
                academic_year_id: 2026,
                subject_code: "FIL",
            },
            filtros: { subject_code: "FIL", status: "all" },
            context: { academic_year_id: 2026 },
            total: 64,
        },
        slots: {
            table: "<div data-test='table-slot'>Tabla original</div>",
        },
        ...options,
    });

describe("CurriculumVisualizationPanel", () => {
    beforeEach(() => {
        exportHarness.exportVisualization.mockReset();
        exportHarness.exportVisualization.mockImplementation(
            async ({ capture }) => capture()
        );
        window.matchMedia = vi.fn(() => ({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }));
        harness.state = makeState();
    });

    it("mantiene la tabla como vista predeterminada sin montar un gráfico", () => {
        const wrapper = mountPanel();

        expect(wrapper.get("[data-test='table-slot']").text()).toBe(
            "Tabla original"
        );
        expect(wrapper.find(".cv-workspace").exists()).toBe(false);
        expect(wrapper.find(".graph-stub").exists()).toBe(false);
    });

    it("cambia a una vista gráfica desde el selector compartido", async () => {
        const wrapper = mountPanel();
        const treemap = wrapper
            .findAll(".cv-view-button")
            .find((button) => button.text().includes("Treemap"));

        await treemap.trigger("click");
        await nextTick();

        expect(harness.state.setView).toHaveBeenCalledWith("treemap");
        expect(wrapper.find("[data-test='table-slot']").exists()).toBe(false);
        expect(wrapper.get(".graph-stub").exists()).toBe(true);
        expect(wrapper.text()).toContain("64 objetivos");
    });

    it("presenta estados de carga, error y vacío sin montar el gráfico", async () => {
        harness.state = makeState({ view: "treemap", data: null });
        harness.state.loading.value = true;
        const loading = mountPanel();
        expect(loading.text()).toContain("Construyendo mapa curricular");
        expect(loading.find(".graph-stub").exists()).toBe(false);
        loading.unmount();

        harness.state = makeState({ view: "treemap", data: null });
        harness.state.error.value = new Error("Servidor no disponible");
        const failed = mountPanel();
        expect(failed.text()).toContain(
            "No fue posible cargar la visualización"
        );
        expect(failed.text()).toContain("Servidor no disponible");
        await failed
            .findAll("button")
            .find((button) => button.text().includes("Reintentar"))
            .trigger("click");
        expect(harness.state.refresh).toHaveBeenCalledOnce();
        failed.unmount();

        harness.state = makeState({
            view: "treemap",
            data: visualizationData(0),
        });
        const empty = mountPanel();
        expect(empty.text()).toContain("No existen Objetivos de Aprendizaje");
        expect(empty.find(".graph-stub").exists()).toBe(false);
    });

    it("envía el breadcrumb elegido al composable para volver de nivel", async () => {
        harness.state = makeState({ view: "treemap" });
        const wrapper = mountPanel();
        const breadcrumbs = wrapper.findAll(".cv-breadcrumbs button");

        await breadcrumbs[0].trigger("click");
        expect(harness.state.goToBreadcrumb).toHaveBeenCalledWith(
            expect.objectContaining({ id: null, type: "catalog" })
        );
    });

    it("propaga el OA normalizado con entityId al detalle existente", async () => {
        harness.state = makeState({ view: "treemap" });
        const wrapper = mountPanel();

        await wrapper.get(".graph-objective").trigger("click");

        expect(wrapper.emitted("open-objective")).toEqual([
            [
                expect.objectContaining({
                    id: "objective:hash",
                    entityId: "01HOBJECTIVEPUBLICID000001",
                    type: "objective",
                }),
            ],
        ]);
    });

    it("muestra acciones del nodo y aplica sus filtros registrados", async () => {
        harness.state = makeState({ view: "treemap" });
        const wrapper = mountPanel();

        await wrapper.get(".graph-category").trigger("click");
        expect(wrapper.get(".cv-node-panel").text()).toContain("Filosofía");

        const apply = wrapper
            .findAll(".cv-node-panel button")
            .find((button) => button.text().includes("Aplicar como filtro"));
        await apply.trigger("click");
        expect(wrapper.emitted("apply-node-filter")).toEqual([
            [
                {
                    filters: { subject_code: "FIL" },
                    node: expect.objectContaining({
                        id: "subject:fil",
                        entityId: 10,
                    }),
                },
            ],
        ]);
    });

    it("permite acercar, alejar, mover, ajustar y exportar desde el lienzo", async () => {
        harness.state = makeState({ view: "treemap" });
        const wrapper = mountPanel();

        const zoomIn = wrapper.get(
            ".cv-viewport-tools [aria-label='Acercar visualización']"
        );
        await zoomIn.trigger("click");
        expect(wrapper.get(".cv-viewport-tools output").text()).toBe("115%");
        expect(
            wrapper.get(".cv-visualization-stage").attributes("style")
        ).toContain("scale(1.15)");

        const move = wrapper
            .findAll(".cv-viewport-tools button")
            .find((button) => button.text().includes("Mover"));
        await move.trigger("click");
        expect(move.attributes("aria-pressed")).toBe("true");

        const fit = wrapper
            .findAll(".cv-viewport-tools button")
            .find((button) => button.text().includes("Ajustar"));
        await fit.trigger("click");
        expect(wrapper.get(".cv-viewport-tools output").text()).toBe("100%");
        expect(move.attributes("aria-pressed")).toBe("false");

        const exportButton = wrapper.get(".cv-viewport-tools__export");
        expect(exportButton.text()).toContain("Exportar PDF");
        await exportButton.trigger("click");
        await nextTick();
        expect(exportHarness.exportVisualization).toHaveBeenCalledOnce();
        expect(wrapper.text()).toContain(
            "La visualización se exportó correctamente en formato PDF"
        );
    });

    it("retira el listener global de Escape al desmontarse", () => {
        const add = vi.spyOn(document, "addEventListener");
        const remove = vi.spyOn(document, "removeEventListener");
        const wrapper = mountPanel();

        expect(add).toHaveBeenCalledWith("keydown", expect.any(Function));
        const listener = add.mock.calls.find(([name]) => name === "keydown")[1];
        wrapper.unmount();
        expect(remove).toHaveBeenCalledWith("keydown", listener);
    });

    it("bloquea el fondo en modo ampliado y Escape restaura la página", async () => {
        harness.state = makeState({ view: "treemap" });
        document.body.style.overflow = "auto";
        const wrapper = mountPanel({ attachTo: document.body });
        const expand = wrapper
            .findAll(".cv-action-button")
            .find((button) => button.text().includes("Ampliar"));

        await expand.trigger("click");
        expect(wrapper.get(".cv-workspace").classes()).toContain(
            "cv-workspace--fullscreen"
        );
        expect(document.body.style.overflow).toBe("hidden");
        expect(document.activeElement).toBe(
            wrapper.get(".cv-workspace__exit-fullscreen").element
        );

        const workspace = wrapper.get(".cv-workspace");
        const lastControl = wrapper.get(".cv-workspace__footer button");
        lastControl.element.focus();
        await workspace.trigger("keydown", { key: "Tab" });
        expect(workspace.element.contains(document.activeElement)).toBe(true);

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
        await nextTick();
        expect(wrapper.get(".cv-workspace").classes()).not.toContain(
            "cv-workspace--fullscreen"
        );
        expect(document.body.style.overflow).toBe("auto");
        wrapper.unmount();
        document.body.style.overflow = "";
    });

    it("trata el panel móvil como diálogo, contiene el foco y lo devuelve al cerrar", async () => {
        window.matchMedia = vi.fn((query) => ({
            matches: query.includes("max-width"),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }));
        harness.state = makeState({ view: "treemap" });
        document.body.style.overflow = "auto";
        const wrapper = mountPanel({ attachTo: document.body });
        const trigger = wrapper.get(".graph-category");
        trigger.element.focus();

        await trigger.trigger("click");
        await nextTick();
        const panel = wrapper.get(".cv-node-panel");
        const close = wrapper.get(
            ".cv-node-panel [aria-label='Cerrar información del nodo']"
        );
        expect(panel.attributes("role")).toBe("dialog");
        expect(panel.attributes("aria-modal")).toBe("true");
        expect(document.activeElement).toBe(close.element);
        expect(document.body.style.overflow).toBe("hidden");

        const lastAction = wrapper
            .findAll(".cv-node-panel__actions button")
            .at(-1);
        lastAction.element.focus();
        await panel.trigger("keydown", { key: "Tab" });
        expect(document.activeElement).toBe(close.element);

        await panel.trigger("keydown", { key: "Escape" });
        await nextTick();
        expect(wrapper.find(".cv-node-panel").exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        expect(document.body.style.overflow).toBe("auto");

        wrapper.unmount();
        document.body.style.overflow = "";
    });
});
