// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";

const harness = vi.hoisted(() => ({
    objectives: vi.fn(),
    create: vi.fn(),
    success: vi.fn(),
    error: vi.fn(),
}));

vi.mock("../../resources/js/services/libro-digital/api", () => ({
    libroDigitalApi: {
        curriculumObjectives: harness.objectives,
        createManualCurriculumProgram: harness.create,
    },
}));

vi.mock("../../resources/js/components/libro-digital/module-utils", () => ({
    payloadData: (payload) => payload?.data || payload,
    payloadItems: (payload) => payload?.data || payload || [],
    showSuccess: harness.success,
    showError: harness.error,
}));

import ManualCurriculumProgramForm from "../../resources/js/components/libro-digital/sections/ManualCurriculumProgramForm.vue";

describe("creación manual de programas curriculares", () => {
    beforeEach(() => {
        Object.values(harness).forEach((mock) => mock.mockReset());
        harness.success.mockResolvedValue();
        harness.error.mockResolvedValue();
        harness.objectives.mockResolvedValue({ data: [
            { id: 183, code: "CN01 OA 01", objective_type: "OA", axis_code: "CIENCIAS_DE_LA_VIDA", description: "Reconocer seres vivos." },
            { id: 201, code: "CN01 OAH a", objective_type: "OAH", axis_code: "OBSERVAR_Y_PREGUNTAR", description: "Explorar y observar." },
        ] });
        harness.create.mockResolvedValue({ data: { id: "program-1", status: "draft", units: [] } });
    });

    it("construye un programa y envía OA, ejes y unidades sin ejecutar el parser PDF", async () => {
        const wrapper = mount(ManualCurriculumProgramForm, {
            props: {
                context: { school_id: 1, academic_year_id: 2 },
                capabilities: { can_publish_curriculum_programs: true },
                matrix: {
                    levels: [{ id: 5, name: "1° básico", grade_code: "1B" }],
                    rows: [{ subject: { id: 7, name: "Ciencias Naturales", code: "CNA" }, cells: [] }],
                },
            },
        });

        wrapper.vm.form.schedule_subject_id = 7;
        wrapper.vm.form.education_level_id = 5;
        await wrapper.vm.loadObjectives();
        wrapper.vm.selectAllObjectives();
        wrapper.vm.syncAxes();
        wrapper.vm.form.units[0].objective_ids = [183];
        wrapper.vm.form.units[0].estimated_pedagogical_hours = 30;
        wrapper.vm.form.units[0].knowledge_text = "Seres vivos\nHábitos saludables";
        await wrapper.vm.submit();
        await flushPromises();

        expect(harness.objectives).toHaveBeenCalledWith(expect.objectContaining({
            schedule_subject_id: 7,
            grade_code: "1B",
        }));
        const body = harness.create.mock.calls[0][0];
        expect(JSON.parse(body.get("objective_ids"))).toEqual([183, 201]);
        expect(JSON.parse(body.get("axes"))[0]).toMatchObject({ name: "Ciencias de la Vida", objective_ids: [183] });
        expect(JSON.parse(body.get("units"))[0]).toMatchObject({
            unit_code: "U1",
            objective_ids: [183],
            knowledge: ["Seres vivos", "Hábitos saludables"],
        });
        expect(wrapper.emitted("created")[0][0]).toMatchObject({ id: "program-1" });
    });
});
