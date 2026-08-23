// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import DailyLog from "../../resources/js/views/infirmary/daily-log.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
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

const categories = [
  { value: "atencion_relevante", label: "Atención relevante" },
  { value: "derivacion_traslado", label: "Derivación o traslado" },
  { value: "bioseguridad", label: "Bioseguridad y aseo clínico" },
  { value: "observacion_general", label: "Observación de jornada" },
];

const catalogs = {
  categories,
  priorities: [{ value: "media", label: "Media" }, { value: "alta", label: "Alta" }],
  statuses: [{ value: "registrado", label: "Registrado" }, { value: "en_seguimiento", label: "En seguimiento" }, { value: "cerrado", label: "Cerrado" }],
  courses: [{ id: 8, display_name: "1° medio A", academic_year_id: 3 }],
  current_academic_year: { id: 3, name: "Año escolar 2026", year: 2026 },
  capabilities: { can_manage: true },
};

const indexResponse = {
  data: [],
  current_page: 1,
  last_page: 1,
  total: 0,
  summary: { total_records: 4, today_records: 1, pending_follow_up: 2, high_priority: 1 },
  capabilities: { can_manage: true },
};

const mountView = () => mount(DailyLog, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { template: "<div><slot /></div>" },
      BModal: { props: ["modelValue"], template: "<div v-if='modelValue' class='modal-stub'><slot /></div>" },
    },
  },
});

describe("Bitácora diaria de Enfermería", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.put.mockReset();
    axios.get.mockImplementation((url) => Promise.resolve({ data: url.endsWith("/catalogs") ? catalogs : indexResponse }));
  });

  it("renders a nursing-only log without Inspectoría fields", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Bitácora diaria");
    expect(wrapper.text()).toContain("No reemplaza la ficha de atención");
    expect(wrapper.text()).toContain("Seguimientos pendientes");
    expect(categories.map((item) => item.value)).not.toContain("convivencia");
    expect(categories.map((item) => item.value)).not.toContain("asistencia");

    await wrapper.find("button.nursing-log-create").trigger("click");
    expect(wrapper.find("#nursing-log-student").exists()).toBe(true);
    expect(wrapper.find("#nursing-log-action").exists()).toBe(true);
    expect(wrapper.text()).not.toContain("Funcionario atrasado");
    expect(wrapper.text()).not.toContain("Cursos asociados");
  });

  it("uses a student datalist, derives the course and enables follow-up", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find("button.nursing-log-create").trigger("click");

    const student = { id: 44, name: "Antonia Soto", rut: "21.111.222-3", course_id: 8, course: "1° medio A" };
    const label = `${student.name} · ${student.rut} · ${student.course}`;
    await wrapper.setData({ studentOptions: [student], studentSearch: label });
    await wrapper.find("#nursing-log-student").trigger("change");

    expect(wrapper.find("#nursing-log-student").attributes("list")).toBe("nursing-log-student-options");
    expect(wrapper.vm.form.student_profile_id).toBe(44);
    expect(wrapper.vm.form.course_section_id).toBe(8);
    expect(wrapper.find("#nursing-log-course").attributes()).toHaveProperty("disabled");

    await wrapper.find(".follow-up-card input").setValue(true);
    expect(wrapper.vm.form.requires_follow_up).toBe(true);
    expect(wrapper.find("#nursing-log-follow-up").exists()).toBe(true);
  });

  it("sends nursing filters to the optimized log endpoint", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.setData({ filters: { search: "traslado", date: "2026-08-21", category: "derivacion_traslado", priority: "alta", status: "en_seguimiento", follow_up: true } });
    await wrapper.vm.load(1);

    expect(axios.get).toHaveBeenLastCalledWith("/api/infirmary/daily-log", {
      params: expect.objectContaining({
        search: "traslado",
        date: "2026-08-21",
        category: "derivacion_traslado",
        priority: "alta",
        status: "en_seguimiento",
        follow_up: 1,
      }),
    });
  });
});
