// @vitest-environment jsdom

import { shallowMount } from "@vue/test-utils";
import { reactive } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";

const routerHarness = vi.hoisted(() => ({
    route: { query: {} },
    replace: vi.fn(() => Promise.resolve()),
    push: vi.fn(() => Promise.resolve()),
}));
const apiHarness = vi.hoisted(() => ({
    curriculumObjectives: vi.fn(),
    curriculumObjective: vi.fn(),
}));

vi.mock("vue-router", () => ({
    useRoute: () => reactive(routerHarness.route),
    useRouter: () => ({
        replace: routerHarness.replace,
        push: routerHarness.push,
    }),
}));

vi.mock("../../resources/js/services/libro-digital/api", () => ({
    LIBRO_DIGITAL_API_BASE: "/api/libro-digital/v1",
    waitForLibroDigitalJob: vi.fn(),
    libroDigitalApi: {
        curriculumObjectives: apiHarness.curriculumObjectives,
        curriculumObjective: apiHarness.curriculumObjective,
    },
}));

import CurriculumObjectivesSection from "../../resources/js/components/libro-digital/sections/CurriculumObjectivesSection.vue";

const PanelStub = {
    name: "CurriculumVisualizationPanel",
    props: ["requestParams", "filtros", "context", "total"],
    emits: [
        "open-objective",
        "apply-node-filter",
        "view-node-in-table",
        "clear-filters",
    ],
    template: "<div class='panel-stub'><slot name='table' /></div>",
};

const mountSection = () =>
    shallowMount(CurriculumObjectivesSection, {
        props: {
            context: {
                school_id: 1,
                academic_year_id: 2026,
            },
            catalogs: {},
            capabilities: {},
        },
        global: {
            stubs: {
                CurriculumVisualizationPanel: PanelStub,
                BAlert: true,
                BButton: true,
                BModal: true,
                BSpinner: true,
            },
            config: {
                warnHandler: () => {},
            },
        },
    });

describe("integración del panel con el catálogo real", () => {
    beforeEach(() => {
        routerHarness.route.query = {};
        routerHarness.replace.mockClear();
        apiHarness.curriculumObjectives.mockReset();
        apiHarness.curriculumObjectives.mockResolvedValue({
            data: [],
            meta: {
                pagination: { total: 0, current_page: 1, last_page: 1 },
                summary: { total: 0 },
                facets: {},
            },
        });
        apiHarness.curriculumObjective.mockReset();
        apiHarness.curriculumObjective.mockResolvedValue({
            data: {
                public_id: "01HOBJECTIVEPUBLICID000001",
                code: "FIL-4M-OA-01",
            },
        });
    });

    it("adapta entityId del nodo y reutiliza el endpoint de detalle existente", async () => {
        const wrapper = mountSection();
        await Promise.resolve();
        const panel = wrapper.getComponent(PanelStub);

        panel.vm.$emit("open-objective", {
            id: "objective:hash",
            entityId: "01HOBJECTIVEPUBLICID000001",
            type: "objective",
            code: "FIL-4M-OA-01",
            description: "Descripción oficial",
            metadata: {},
        });
        await Promise.resolve();
        await Promise.resolve();

        expect(apiHarness.curriculumObjective).toHaveBeenCalledWith(
            "01HOBJECTIVEPUBLICID000001",
            {
                school_id: 1,
                academic_year_id: 2026,
            },
            expect.any(AbortSignal)
        );
        wrapper.unmount();
    });

    it("aplica filtros explícitos del nodo y vuelve a consultar la tabla", async () => {
        const wrapper = mountSection();
        await Promise.resolve();
        apiHarness.curriculumObjectives.mockClear();
        const panel = wrapper.getComponent(PanelStub);

        panel.vm.$emit("apply-node-filter", {
            filters: {
                subject_code: "FIL",
                grade_code: "4M",
            },
            node: { type: "subject", code: "FIL" },
        });
        await Promise.resolve();
        await Promise.resolve();

        expect(panel.props("filtros")).toMatchObject({
            subject_code: "FIL",
            grade_code: "4M",
        });
        expect(apiHarness.curriculumObjectives).toHaveBeenCalledWith(
            expect.objectContaining({
                subject_code: "FIL",
                grade_code: "4M",
            }),
            expect.any(AbortSignal)
        );
        wrapper.unmount();
    });
});
