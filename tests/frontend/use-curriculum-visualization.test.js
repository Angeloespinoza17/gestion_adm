import { effectScope, nextTick, ref } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const lifecycle = vi.hoisted(() => ({ beforeUnmount: [] }));
const routerHarness = vi.hoisted(() => ({
    route: { query: {} },
    replace: vi.fn(),
}));
const apiHarness = vi.hoisted(() => ({
    curriculumVisualization: vi.fn(),
}));
const route = routerHarness.route;
const routerReplace = routerHarness.replace;
const curriculumVisualization = apiHarness.curriculumVisualization;

vi.mock("vue-router", () => ({
    useRoute: () => routerHarness.route,
    useRouter: () => ({ replace: routerHarness.replace }),
}));

vi.mock("vue", async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        onBeforeUnmount: (callback) => lifecycle.beforeUnmount.push(callback),
    };
});

vi.mock("../../resources/js/services/libro-digital/api", () => ({
    libroDigitalApi: {
        curriculumVisualization: apiHarness.curriculumVisualization,
    },
}));

import { useCurriculumVisualization } from "../../resources/js/composables/useCurriculumVisualization";

const payload = ({ filtered = 64, current = filtered } = {}) => ({
    meta: {
        scope: "filtered",
        view: "radial_tree",
        total_objectives: current,
        filtered_total_objectives: filtered,
        hierarchy: ["subject", "curricular_group", "grade", "objective"],
        max_depth: 3,
        aggregated: true,
    },
    root: {
        id: "catalog:root",
        type: "catalog",
        name: "Resultados filtrados",
        objective_count: current,
        has_children: true,
        children: [],
        path: [],
    },
    graph: { nodes: [], links: [] },
});

const mountComposable = ({ params, total = 64 } = {}) => {
    const requestParams = ref(
        params || {
            school_id: 1,
            academic_year_id: 2026,
            subject_code: "FIL",
            query: "ética",
            page: 3,
            per_page: 25,
        }
    );
    const expectedTotal = ref(total);
    let result;
    const scope = effectScope();
    scope.run(() => {
        result = useCurriculumVisualization({
            requestParams,
            expectedTotal,
        });
    });
    const dispose = () => {
        lifecycle.beforeUnmount.splice(0).forEach((callback) => callback());
        scope.stop();
    };
    return { dispose, result, requestParams, expectedTotal };
};

describe("useCurriculumVisualization", () => {
    beforeEach(() => {
        vi.useFakeTimers();
        const values = new Map();
        globalThis.window = {
            setTimeout: globalThis.setTimeout,
            clearTimeout: globalThis.clearTimeout,
            localStorage: {
                getItem: (key) => values.get(key) ?? null,
                setItem: (key, value) => values.set(key, String(value)),
                removeItem: (key) => values.delete(key),
                clear: () => values.clear(),
            },
        };
        lifecycle.beforeUnmount.length = 0;
        route.query = {};
        window.localStorage.clear();
        routerReplace.mockClear();
        routerReplace.mockImplementation(async ({ query }) => {
            route.query = { ...query };
        });
        curriculumVisualization.mockReset();
        curriculumVisualization.mockResolvedValue(payload());
    });

    afterEach(() => {
        lifecycle.beforeUnmount.splice(0).forEach((callback) => callback());
        vi.clearAllTimers();
        vi.useRealTimers();
        delete globalThis.window;
    });

    it("no consulta gráficos en Tabla y conserva filtros sin paginación en el payload", async () => {
        const { dispose, result } = mountComposable();

        expect(result.activeView.value).toBe("table");
        expect(result.requestPayload.value).toMatchObject({
            school_id: 1,
            academic_year_id: 2026,
            subject_code: "FIL",
            query: "ética",
            scope: "filtered",
            hierarchy: "subject,curricular_group,grade,objective",
        });
        expect(result.requestPayload.value).not.toHaveProperty("page");
        expect(result.requestPayload.value).not.toHaveProperty("per_page");

        await vi.runAllTimersAsync();
        expect(curriculumVisualization).not.toHaveBeenCalled();
        dispose();
    });

    it("limita el radial inicial a tres niveles y libera la profundidad al profundizar", async () => {
        const { dispose, result } = mountComposable();
        const subjectRoot = `subject:${"a".repeat(64)}`;

        result.setView("radial_tree");
        await nextTick();
        expect(result.requestPayload.value).toMatchObject({
            view: "radial_tree",
            max_depth: 3,
            include_leaves: 1,
        });

        result.drillDown({
            id: subjectRoot,
            type: "subject",
            name: "Filosofía",
            hasChildren: false,
        });
        await nextTick();

        expect(result.rootNode.value).toBe(subjectRoot);
        expect(result.requestPayload.value).toMatchObject({
            max_depth: 4,
            include_leaves: 1,
            root_node: subjectRoot,
        });
        dispose();
    });

    it("acota Sankey a cuatro columnas aun con un preset de cinco niveles", async () => {
        route.query = {
            view: "sankey",
            hierarchy:
                "education_level,grade,subject,curricular_group,objective",
        };
        const { dispose, result } = mountComposable();
        await nextTick();

        expect(result.requestPayload.value.max_depth).toBe(4);
        expect(result.requestPayload.value.hierarchy).toBe(
            "education_level,grade,subject,curricular_group,objective"
        );
        dispose();
    });

    it("serializa la vista y jerarquía en URL y restaura valores válidos", async () => {
        route.query = {
            view: "sunburst",
            hierarchy: "source,subject,grade,objective",
            scope: "catalog",
            labels: "hide",
        };
        const { dispose, result } = mountComposable();

        expect(result.activeView.value).toBe("sunburst");
        expect(result.hierarchyPreset.value).toBe(
            "source,subject,grade,objective"
        );
        expect(result.scope.value).toBe("catalog");
        expect(result.labelMode.value).toBe("hide");

        await vi.advanceTimersByTimeAsync(25);
        expect(routerReplace).toHaveBeenCalledWith({
            query: expect.objectContaining({
                view: "sunburst",
                hierarchy: "source,subject,grade,objective",
                scope: "catalog",
                labels: "hide",
            }),
        });
        dispose();
    });

    it("compara la tabla con filtered_total_objectives y no con el subtotal del drilldown", async () => {
        curriculumVisualization.mockResolvedValue(
            payload({ filtered: 64, current: 12 })
        );
        const { dispose, result } = mountComposable();

        result.setView("treemap");
        await vi.runAllTimersAsync();
        await nextTick();

        expect(result.data.value.meta.totalObjectives).toBe(12);
        expect(result.data.value.meta.filteredTotalObjectives).toBe(64);
        expect(result.countMismatch.value).toBe(false);
        dispose();
    });

    it("aborta la solicitud anterior al forzar una nueva carga", async () => {
        const signals = [];
        curriculumVisualization.mockImplementation((_params, signal) => {
            signals.push(signal);
            return new Promise(() => {});
        });
        const { dispose, result } = mountComposable();
        result.setView("treemap");
        await nextTick();

        void result.loadVisualization({ force: true });
        await nextTick();
        void result.loadVisualization({ force: true });
        await nextTick();

        expect(signals).toHaveLength(2);
        expect(signals[0].aborted).toBe(true);
        expect(signals[1].aborted).toBe(false);
        dispose();
        expect(signals[1].aborted).toBe(true);
    });
});
