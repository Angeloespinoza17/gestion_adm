// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import MedicalLeaves from "../../resources/js/views/student-health/medical-leaves.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

const apiResponse = {
  data: [],
  current_page: 1,
  last_page: 1,
  total: 0,
  summary: { total_records: 2, permanent_records: 1, chronic_students: 1, ending_soon: 0 },
  capabilities: { can_create: true },
};

const mountView = () => mount(MedicalLeaves, {
  global: {
    mocks: { $route: { path: "/infirmary/medical-leaves" } },
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { template: "<div><slot /></div>" },
      BModal: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='modal-stub'><slot /></div>",
      },
    },
  },
});

describe("Licencias médicas compartidas", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.get.mockResolvedValue({ data: apiResponse });
  });

  it("renders the chronic-student filter and requests only permanent records when activated", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Enfermedad crónica");
    expect(wrapper.text()).toContain("Alumnas con condición crónica");
    await wrapper.find("button.chronic-filter").trigger("click");
    await flushPromises();

    expect(axios.get).toHaveBeenLastCalledWith("/api/student-medical-leaves", {
      params: expect.objectContaining({ permanent: 1 }),
    });
  });

  it("uses a datalist and accepts a permanent condition without an end date", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find("button.medical-create-button").trigger("click");

    const student = { id: 44, name: "Antonia Soto", rut: "21.111.222-3", course: "1° medio A" };
    const label = `${student.name} · ${student.rut} · ${student.course}`;
    await wrapper.setData({ studentOptions: [student], studentSearch: label });
    await wrapper.find("#medical-leave-student").trigger("change");

    expect(wrapper.find("#medical-leave-student").attributes("list")).toBe("medical-leave-student-options");
    expect(wrapper.find("#medical-leave-student-options").exists()).toBe(true);
    expect(wrapper.vm.form.student_profile_id).toBe(44);
    expect(wrapper.find(".overlap-notice").text()).toContain("Control automático de duplicados");
    expect(wrapper.find(".overlap-notice").text()).toContain("Enfermería o Inspectoría");

    await wrapper.find(".permanent-card input").setValue(true);
    expect(wrapper.vm.form.is_permanent).toBe(true);
    expect(wrapper.vm.form.ends_on).toBe("");
    expect(wrapper.find("#medical-leave-end").attributes()).toHaveProperty("disabled");
    expect(wrapper.find(".overlap-notice").text()).toContain("Condición crónica excluida del bloqueo");
  });
});
