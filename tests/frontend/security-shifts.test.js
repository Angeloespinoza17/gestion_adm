// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import SecurityShifts from "../../resources/js/views/security/shifts.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state' />" },
}));

const weekdays = [
  ["Monday", "Lunes"], ["Tuesday", "Martes"], ["Wednesday", "Miércoles"],
  ["Thursday", "Jueves"], ["Friday", "Viernes"], ["Saturday", "Sábado"], ["Sunday", "Domingo"],
].map(([value, label]) => ({ value, label }));

const schedule = {
  id: 9,
  staff_id: 4,
  status: "programado",
  schedule_type: "weekly",
  is_weekly_template: true,
  weekdays: ["Monday", "Wednesday", "Friday"],
  weekday_labels: ["Lunes", "Miércoles", "Viernes"],
  template_start_time: "20:00",
  template_end_time: "07:30",
  recurrence_starts_on: "2026-09-01",
  recurrence_ends_on: null,
  staff: { id: 4, full_name: "José Campos" },
  registration: {
    open: true,
    can_register: true,
    starts_at: "2026-09-04 20:00",
    ends_at: "2026-09-05 07:30",
  },
};

const catalogs = {
  weekday_options: weekdays,
  round_statuses: [{ value: "sin_novedad", label: "Sin novedad" }],
  sector_states: [{ value: "sin_novedad", label: "Sin novedad" }],
  priorities: [{ value: "media", label: "Media" }],
  staff: [
    { id: 4, full_name: "José Campos" },
    { id: 7, full_name: "Ana Soto" },
  ],
  inventory_items: [],
  responsible_users: [],
  current_user: { id: 1, name: "Super Admin", staff_id: null, is_superadmin: true },
  capabilities: { can_manage_shifts: true, can_register_rounds: true, can_export: true },
};

const mountView = () => mount(SecurityShifts, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { props: ["show"], template: "<div v-if='show'><slot /></div>" },
      BModal: { props: ["modelValue"], template: "<div v-if='modelValue' class='modal-stub'><slot /></div>" },
      BButton: { template: "<button><slot /></button>" },
      BFormInput: { template: "<input />" },
      BFormTextarea: { template: "<textarea />" },
      Multiselect: {
        props: ["modelValue", "options"],
        emits: ["update:modelValue"],
        template: "<select><option v-for='option in options' :key='option.value'>{{ option.label }}</option></select>",
      },
    },
  },
});

describe("Programación semanal de nocheros", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.put.mockReset();
    axios.get.mockImplementation((url) => {
      if (url === "/api/security/catalogs") return Promise.resolve({ data: catalogs });
      if (url === "/api/security/shifts") {
        return Promise.resolve({ data: { data: [schedule], current_page: 1, last_page: 1, total: 1 } });
      }
      if (url === "/api/security/shifts/9") {
        return Promise.resolve({ data: { data: schedule, recent_rounds: [] } });
      }
      return Promise.reject(new Error(`Unexpected URL: ${url}`));
    });
  });

  it("shows the weekly days and enables direct round registration during the active shift", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(wrapper.text()).toContain("Días de trabajo y rondas");
    expect(wrapper.text()).toContain("Quién entra y cuándo sale");
    expect(wrapper.text()).toContain("José Campos");
    expect(wrapper.text()).toContain("Entra");
    expect(wrapper.text()).toContain("Sale");
    expect(wrapper.text()).toContain("Registro habilitado");
    expect(wrapper.text()).toContain("Agregar registro");
    expect(wrapper.text()).not.toContain("Tipo de programación");
    expect(wrapper.vm.weeklyCoverage.find((day) => day.value === "Friday").exitLabel).toBe("Sábado");
    expect(axios.get).toHaveBeenCalledWith("/api/security/shifts", {
      params: { page: 1, per_page: 100, templates_only: 1 },
    });
  });

  it("lets the superadmin open a traceable administrative entry for the selected shift", async () => {
    const wrapper = mountView();
    await flushPromises();

    wrapper.vm.openRoundModal(true);
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.roundForm.administrative_entry).toBe(true);
    expect(wrapper.vm.roundForm.occurrence_date).not.toBe("");
    expect(wrapper.text()).toContain("Intervención administrativa trazable");
    expect(wrapper.text()).toContain("Noche programada");
    expect(wrapper.text()).toContain("Fecha y hora real");
    expect(wrapper.text()).toContain("Agregar al turno");
  });

  it("sends the selected night and real timestamp with the superadmin entry", async () => {
    axios.post.mockResolvedValue({ data: { data: { id: 21 } } });
    const wrapper = mountView();
    await flushPromises();

    wrapper.vm.openRoundModal(true);
    wrapper.vm.roundForm.occurrence_date = "2026-09-04";
    wrapper.vm.roundForm.recorded_at = "2026-09-05T07:15";
    wrapper.vm.roundForm.sectors[0].sector_name = "Acceso principal";
    await wrapper.vm.saveRound();

    const formData = axios.post.mock.calls[0][1];
    const payload = JSON.parse(formData.get("payload"));
    expect(payload).toEqual(expect.objectContaining({
      administrative_entry: true,
      occurrence_date: "2026-09-04",
      recorded_at: "2026-09-05T07:15",
    }));
  });

  it("sends a weekly schedule using only the selected staff member and day chips", async () => {
    axios.post.mockResolvedValue({ data: { data: { ...schedule, id: 10, staff_id: 7 } } });
    const wrapper = mountView();
    await flushPromises();

    wrapper.vm.newShift();
    wrapper.vm.shiftForm.staff_id = 7;
    wrapper.vm.toggleWeekday("Tuesday");
    wrapper.vm.toggleWeekday("Thursday");
    await wrapper.vm.saveShift();

    const payload = axios.post.mock.calls[0][1];
    expect(payload).toEqual(expect.objectContaining({
      staff_id: 7,
      schedule_type: "weekly",
      weekdays: ["Tuesday", "Thursday"],
      coverage_label: "Todo el colegio",
    }));
    expect(payload).not.toHaveProperty("template_start_time");
    expect(payload).not.toHaveProperty("template_end_time");
  });
});
