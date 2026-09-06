// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { readFileSync } from "node:fs";
import { beforeEach, describe, expect, it, vi } from "vitest";
import SecurityDashboard from "../../resources/js/views/security/dashboard.vue";

vi.mock("axios", () => ({
  default: { get: vi.fn() },
}));

vi.mock("sweetalert2", () => ({
  default: { fire: vi.fn() },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state'><slot /></div>" },
}));

const dashboardResponse = {
  totals: {
    rounds_total: 24,
    rounds_today: 3,
    attention_rounds: 2,
    active_shifts: 1,
    pending_incidents: 2,
    critical_incidents: 1,
    average_response_minutes: 18,
  },
  rounds_by_date: [{ label: "2026-09-05", total: 3 }],
  rounds_by_staff: [{ label: "José Campos", total: 14 }],
  sectors_with_most_incidents: [{ label: "Laboratorio", total: 2 }],
  recent_notifications: [],
  recent_rounds: [{
    id: 7,
    round_number: 2,
    recorded_at: "2026-09-05 01:15:00",
    overall_status: "requiere_atencion",
    observations: "Fuga de agua detectada y sector aislado.",
    act_number: "ACT-NOCHE-007",
    sectors_count: 4,
    incidents_count: 1,
    pending_incidents_count: 1,
    shift: { staff: { full_name: "José Campos" } },
  }],
  upcoming_shifts: [{
    id: 8,
    status: "programado",
    schedule_summary: "Viernes · 20:00 a 07:30",
    is_weekly_template: false,
    scheduled_start_at: "2026-09-05 20:00:00",
    staff: { full_name: "José Campos" },
  }],
};

const mountView = () => mount(SecurityDashboard, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { props: ["show"], template: "<div v-if='show'><slot /></div>" },
      RouterLink: { props: ["to"], template: "<a :href='to'><slot /></a>" },
    },
  },
});

describe("Panel operativo de nocheros", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.get.mockResolvedValue({ data: dashboardResponse });
  });

  it("presents the nightly logbook and operational metrics in one workspace", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith("/api/security/dashboard");
    expect(wrapper.text()).toContain("Panel de nocheros");
    expect(wrapper.text()).toContain("Bitácora nocturna reciente");
    expect(wrapper.text()).toContain("Rondas de hoy");
    expect(wrapper.text()).toContain("José Campos");
    expect(wrapper.text()).toContain("ACT-NOCHE-007");
    expect(wrapper.text()).toContain("Requiere atención");
    expect(wrapper.findAll(".night-metric")).toHaveLength(5);
  });

  it("keeps direct navigation to rounds and pending incidents", async () => {
    const wrapper = mountView();
    await flushPromises();

    const links = wrapper.findAll("a").map((link) => link.attributes("href"));
    expect(links).toContain("/security/shifts");
    expect(links).toContain("/security/incidents");
  });

  it("keeps the shift workspace focused on weekly days and the traceable logbook", () => {
    const shiftsView = readFileSync("resources/js/views/security/shifts.vue", "utf8");

    expect(shiftsView).toContain("Días de trabajo y rondas");
    expect(shiftsView).toContain("Asignar días de trabajo");
    expect(shiftsView).toContain("Quién entra y cuándo sale");
    expect(shiftsView).toContain("Sale {{ nextWeekdayLabel(option.value) }} · 07:30");
    expect(shiftsView).toContain("Bitácora de rondas");
    expect(shiftsView).toContain("Se habilita automáticamente");
    expect(shiftsView).toContain("roundStatusLabel");
    expect(shiftsView).toContain("round-card");
  });
});
