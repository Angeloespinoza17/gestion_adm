// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import Swal from "sweetalert2";
import { nextTick } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import Inspectoria from "../../resources/js/views/inspectoria/index.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), put: vi.fn(), post: vi.fn(), delete: vi.fn() },
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

const student = {
  id: 41,
  first_name: "María",
  last_name: "Pérez",
  registered_name: "María Pérez",
  registered_name_resolved: "María Pérez",
  rut: "22.222.222-2",
  general_status: "activo",
  phone: "+56 9 1111 1111",
  email: "maria@example.test",
  address: "Dirección anterior",
  commune: "Valdivia",
  guardian_name: "Ana Pérez",
  guardian_phone: "+56 9 2222 2222",
  guardian_email: "ana@example.test",
  guardian_address: "Dirección anterior",
  guardian_commune: "Valdivia",
  updated_at: "2026-08-24T15:00:00.000000Z",
  current_enrollment: { snapshot_course_display_name: "2° medio A" },
};

const file = { student, attendance_profile: null, attentions: [], passes: [], daily_logs: [] };

const catalogs = {
  academic_years: [], courses: [], current_courses: [], students: [], inspectors: [], staff: [],
  request_types: [], attention_actions: [], psychosocial_professionals: [], destinations: [],
  log_categories: [], withdrawal_statuses: [], withdrawal_reasons: [], withdrawal_relationships: [],
  pickup_restriction_types: [], active_academic_year_id: null,
  capabilities: { edit_student_profiles: true },
};

const stubs = {
  Layout: { template: "<div><slot /></div>" },
  LoadingState: { template: "<div class='loading-state' />" },
  BModal: {
    props: ["modelValue"],
    template: "<div v-if='modelValue' class='modal-stub'><slot /></div>",
  },
  BButton: {
    props: ["disabled", "type"],
    emits: ["click"],
    template: "<button :type='type || `button`' :disabled='disabled' @click='$emit(`click`)'><slot /></button>",
  },
  BFormInput: true,
  BFormSelect: true,
  BFormTextarea: true,
  BFormCheckbox: true,
  BPagination: true,
  BAlert: true,
};

describe("edición de ficha de alumna en Inspectoría", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.put.mockReset();
    Swal.fire.mockClear();
    axios.get.mockImplementation((url) => {
      if (url === "/api/inspectoria/catalogs") return Promise.resolve({ data: catalogs });
      if (url === "/api/inspectoria/students") return Promise.resolve({ data: { data: [], current_page: 1, total: 0, per_page: 18 } });
      return Promise.resolve({ data: {} });
    });
  });

  it("deja la Bitácora en modo de consulta cuando el perfil no administra Inspectoría", async () => {
    axios.get.mockImplementation((url) => {
      if (url === "/api/inspectoria/catalogs") {
        return Promise.resolve({
          data: {
            ...catalogs,
            capabilities: {
              view_module: false,
              view_daily_log: true,
              manage_daily_log: false,
              view_statistics: false,
            },
          },
        });
      }
      if (url === "/api/inspectoria/daily-log") {
        return Promise.resolve({ data: { data: [], current_page: 1, total: 0, per_page: 15 } });
      }
      return Promise.resolve({ data: {} });
    });

    const wrapper = mount(Inspectoria, {
      global: {
        mocks: { $route: { path: "/inspectoria/bitacora" }, $router: { push: vi.fn() } },
        stubs,
        config: { warnHandler: () => {} },
      },
    });
    await flushPromises();

    const tabs = wrapper.findAll(".module-tabs button");
    expect(tabs).toHaveLength(1);
    expect(tabs[0].text()).toContain("Bitácora diaria");
    expect(wrapper.text()).not.toContain("Nuevo registro");
  });

  it("abre el editor protegido y guarda la versión de la ficha junto con los contactos", async () => {
    const updatedStudent = { ...student, address: "Los Robles 125", guardian_phone: "+56 9 3333 3333", updated_at: "2026-08-24T15:05:00.000000Z" };
    axios.put.mockResolvedValue({ data: { data: { ...file, student: updatedStudent }, changed_fields: ["address", "guardian_phone"] } });

    const wrapper = mount(Inspectoria, {
      global: {
        mocks: { $route: { path: "/inspectoria/alumnas" }, $router: { push: vi.fn() } },
        stubs,
        config: { warnHandler: () => {} },
      },
    });
    await flushPromises();
    await wrapper.setData({ showStudentFile: true, selectedStudentFile: file });
    await nextTick();

    expect(wrapper.find(".student-file-edit-button").exists()).toBe(true);
    await wrapper.find(".student-file-edit-button").trigger("click");
    await nextTick();

    expect(wrapper.find(".student-profile-editor").text()).toContain("ACTUALIZACIÓN PROTEGIDA");
    expect(wrapper.find(".student-profile-editor").text()).toContain("Antecedentes de salud, PIE, retiros y alertas no se modifican aquí.");
    wrapper.vm.studentProfile.address = "Los Robles 125";
    wrapper.vm.studentProfile.guardian_phone = "+56 9 3333 3333";
    await wrapper.vm.saveStudentProfile();

    expect(axios.put).toHaveBeenCalledWith("/api/inspectoria/students/41/profile", expect.objectContaining({
      profile_updated_at: student.updated_at,
      address: "Los Robles 125",
      guardian_phone: "+56 9 3333 3333",
    }));
    expect(wrapper.vm.selectedStudentFile.student.address).toBe("Los Robles 125");
    expect(wrapper.vm.editingStudentProfile).toBe(false);
  });

  it("no expone SQL ni detalles internos cuando el servidor responde con error", async () => {
    const wrapper = mount(Inspectoria, {
      global: {
        mocks: { $route: { path: "/inspectoria/alumnas" }, $router: { push: vi.fn() } },
        stubs,
        config: { warnHandler: () => {} },
      },
    });
    await flushPromises();

    wrapper.vm.captureError({
      response: {
        status: 500,
        data: { message: "SQLSTATE[42S02]: insert into inspectoria_student_profile_change_logs" },
      },
    }, true);

    expect(wrapper.vm.error).toBe("Ocurrió un problema interno al guardar. Intenta nuevamente o informa el incidente a soporte.");
    expect(wrapper.vm.error).not.toContain("SQLSTATE");
    expect(Swal.fire).toHaveBeenCalledWith(expect.objectContaining({ text: wrapper.vm.error }));
  });
});
