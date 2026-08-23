// @vitest-environment jsdom

import { mount } from "@vue/test-utils";
import axios from "axios";
import { describe, expect, it, vi } from "vitest";
import AnnualGradeImport from "../../resources/js/components/students/annual-grade-import.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}));

describe("Importación anual de calificaciones", () => {
  it("explica que las notas se asocian a la asignatura aunque falte docente", async () => {
    const wrapper = mount(AnnualGradeImport, {
      props: {
        academicYears: [{ id: 20, name: "2026", is_active: true }],
        initialYearId: 20,
      },
      global: { stubs: { Teleport: true } },
    });

    await wrapper.setData({ open: true });

    expect(wrapper.text()).toContain("Docente posterior");
    expect(wrapper.text()).toContain(
      "La nota se asocia a la asignatura aunque el libro aún no tenga docente."
    );
  });
});
