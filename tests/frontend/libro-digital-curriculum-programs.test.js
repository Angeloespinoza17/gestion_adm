// @vitest-environment jsdom

import { flushPromises, shallowMount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

const harness = vi.hoisted(() => ({
    matrix: vi.fn(),
    imports: vi.fn(),
    importDetail: vi.fn(),
    review: vi.fn(),
    program: vi.fn(),
    exportPdf: vi.fn(),
    report: vi.fn(),
    download: vi.fn(),
    waitJob: vi.fn(),
    routerReplace: vi.fn(),
}));

vi.mock("vue-router", () => ({
    useRoute: () => ({ query: {} }),
    useRouter: () => ({ replace: harness.routerReplace }),
}));

vi.mock("vue3-apexcharts", () => ({ default: { template: "<div class='chart-stub' />" } }));
vi.mock("sweetalert2", () => ({ default: { fire: vi.fn() } }));

vi.mock("../../resources/js/services/libro-digital/api", () => ({
    LIBRO_DIGITAL_API_BASE: "/api/libro-digital/v1",
    waitForLibroDigitalJob: harness.waitJob,
    libroDigitalApi: {
        curriculumProgramMatrix: harness.matrix,
        curriculumProgramImports: harness.imports,
        curriculumProgramImport: harness.importDetail,
        reviewCurriculumProgramCandidate: harness.review,
        curriculumProgram: harness.program,
        exportCurriculumProgramPdf: harness.exportPdf,
        report: harness.report,
        download: harness.download,
        curriculumDocumentUrl: (id) => `/documents/${id}`,
    },
}));

vi.mock("../../resources/js/components/libro-digital/module-utils", async () => {
    const original = await vi.importActual(
        "../../resources/js/components/libro-digital/module-utils"
    );
    return {
        ...original,
        confirmAction: vi.fn(() => Promise.resolve({ isConfirmed: true })),
        showSuccess: vi.fn(() => Promise.resolve()),
        showError: vi.fn(() => Promise.resolve()),
    };
});

import CurriculumProgramsSection from "../../resources/js/components/libro-digital/sections/CurriculumProgramsSection.vue";

const context = { school_id: 1, academic_year_id: 2035 };
const capabilities = {
    can_import_curriculum_programs: true,
    can_import_curriculum_programs_batch: true,
    can_review_curriculum_programs: true,
    can_resolve_curriculum_conflicts: true,
    can_publish_curriculum_programs: true,
    can_reprocess_curriculum_programs: true,
    can_export_curriculum_programs_pdf: true,
    can_view_curriculum_documents: true,
};

describe("catálogo curricular ministerial", () => {
    beforeEach(() => {
        Object.values(harness).forEach((mock) => mock.mockReset());
        harness.routerReplace.mockResolvedValue();
        harness.matrix.mockResolvedValue({
            data: {
                levels: [{ id: 10, name: "1° básico", grade_code: "1B" }],
                rows: [
                    {
                        subject: { id: 4, name: "Ciencias Naturales", area: "Ciencias", color: "#0ab39c" },
                        cells: [
                            {
                                education_level_id: 10,
                                status: "pending_review",
                                import: { id: "import-1", status: "pending_review", progress: 100 },
                            },
                        ],
                    },
                ],
                chart: { labels: ["pending_review"], series: [1] },
                legend: ["missing", "pending_review", "published"],
            },
        });
        harness.imports.mockResolvedValue({
            data: [{ id: "import-1", name: "programa.pdf", status: "pending_review", progress: 100 }],
        });
        harness.importDetail.mockResolvedValue({
            data: {
                id: "import-1",
                name: "programa.pdf",
                status: "pending_review",
                progress: 100,
                document: { id: "doc-1", subject: "Ciencias Naturales", grade: "1° básico", page_count: 184 },
                candidates: [
                    {
                        id: "unit-1",
                        type: "unit",
                        key: "unit:1",
                        value: "Unidad 1",
                        confidence: 0.97,
                        physical_page: 57,
                        review_status: "pending",
                        children: [
                            {
                                id: "skill-1",
                                type: "skills",
                                value: "Explorar y observar",
                                confidence: 0.82,
                                physical_page: 57,
                                suggested_action: "review",
                                review_status: "pending",
                                warnings: ["extracted_summary_requires_review"],
                            },
                        ],
                    },
                ],
                conflicts: [],
                logs: [],
            },
        });
        harness.review.mockResolvedValue({ data: { id: "skill-1", review_status: "accepted" } });
        harness.program.mockResolvedValue({
            data: {
                id: "program-1",
                name: "Ciencias Naturales",
                grade_code: "1B",
                status: "published",
                weeks: 38,
                hours: 114,
                subject: { name: "Ciencias Naturales" },
                education_level: { name: "1° básico" },
                version: { name: "Edición 2018" },
                objectives: [],
                axes: [],
                units: [],
                documents: [],
                charts: {
                    hours_by_unit: { labels: [], series: [] },
                    objectives_by_axis: { labels: [], series: [] },
                },
            },
        });
        harness.exportPdf.mockResolvedValue({
            data: { id: "report-1", status: "queued", progress: 0 },
        });
        harness.report.mockResolvedValue({
            data: { id: "report-1", status: "completed", progress: 100 },
        });
        harness.download.mockResolvedValue();
    });

    it("muestra la matriz dinámica de asignatura por nivel", async () => {
        const wrapper = shallowMount(CurriculumProgramsSection, {
            props: { context, capabilities },
            global: { stubs: { LibroDigitalStatePanel: true }, config: { warnHandler: () => {} } },
        });
        await flushPromises();

        expect(wrapper.text()).toContain("Ciencias Naturales");
        expect(wrapper.text()).toContain("1° básico");
        expect(wrapper.text()).toContain("Revisión pendiente");
        wrapper.unmount();
    });

    it("permite revisar explícitamente extractos secundarios antes de publicarlos", async () => {
        const wrapper = shallowMount(CurriculumProgramsSection, {
            props: { context, capabilities },
            global: { stubs: { LibroDigitalStatePanel: true }, config: { warnHandler: () => {} } },
        });
        await flushPromises();
        wrapper.vm.setView("imports");
        await wrapper.vm.openImport({ id: "import-1" });
        await flushPromises();

        expect(wrapper.find(".child-candidate").exists()).toBe(true);
        expect(wrapper.text()).toContain("requiere revisión de extracción");
        await wrapper.vm.reviewCandidate(wrapper.vm.selectedImport.candidates[0].children[0], "accepted");

        expect(harness.review).toHaveBeenCalledWith("skill-1", {
            review_status: "accepted",
            suggested_action: "create",
        });
        wrapper.unmount();
    });

    it("muestra el estado real de una exportación en cola sin inventar 5 por ciento", async () => {
        const wrapper = shallowMount(CurriculumProgramsSection, {
            props: { context, capabilities },
            global: { stubs: { LibroDigitalStatePanel: true }, config: { warnHandler: () => {} } },
        });
        await flushPromises();
        await wrapper.vm.openProgram({ id: "program-1" });

        harness.waitJob.mockImplementationOnce(async (job, loader, options) => {
            options.onProgress({ ...job, status: "queued", progress: 0 });
            await wrapper.vm.$nextTick();
            expect(wrapper.vm.exportButtonLabel).toBe("PDF en cola…");
            expect(wrapper.vm.exportProgress).toBe(0);

            return { ...job, status: "completed", progress: 100 };
        });

        await wrapper.vm.exportPdf();

        expect(harness.download).toHaveBeenCalledWith(
            "/api/libro-digital/v1/reports/report-1/download",
            "programa-curricular-1B.pdf"
        );
        expect(wrapper.vm.exportButtonLabel).toBe("Exportar PDF");
        wrapper.unmount();
    });
});
