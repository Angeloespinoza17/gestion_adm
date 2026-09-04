// @vitest-environment jsdom

import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import RelojControl from "../../resources/js/views/superadmin/reloj-control.vue";
import { downloadRelojControlPdf } from "../../resources/js/utils/reloj-control-report-pdf";
import { downloadRelojControlAnalyticsPdf } from "../../resources/js/utils/reloj-control-analytics-pdf";

vi.mock("axios", () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("../../resources/js/layouts/main.vue", () => ({
  default: { template: "<div><slot /></div>" },
}));

vi.mock("../../resources/js/components/ui/loading-state.vue", () => ({
  default: { template: "<div class='loading-state'><slot /></div>" },
}));

vi.mock("../../resources/js/utils/reloj-control-report-pdf", () => ({
  downloadRelojControlPdf: vi.fn().mockResolvedValue(undefined),
}));

vi.mock("../../resources/js/utils/reloj-control-analytics-pdf", () => ({
  downloadRelojControlAnalyticsPdf: vi.fn().mockResolvedValue(undefined),
}));

const usersResponse = {
  data: [
    { id: 101, identifier: "11.111.111-1", rut: "11.111.111-1", name: "Ana Institucional", email: "ana@institucion.test", group: "", position: "Secretaria", enabled: true, geovictoria_linked: true },
    { id: 103, identifier: "33.333.333-3", rut: "33.333.333-3", name: "Carla Local", email: "carla@institucion.test", group: "", position: "Docente", enabled: true, geovictoria_linked: false },
    { id: 102, identifier: "22.222.222-2", rut: "22.222.222-2", name: "Bruno Institucional", email: "bruno@institucion.test", group: "", position: "Auxiliar", enabled: false, geovictoria_linked: true },
  ],
  summary: { total: 3, active: 2, inactive: 1, linked: 2, unlinked: 1, provider_total: 3, provider_only: 1, provider_available: true },
  fetched_at: "2026-08-30T10:00:00-04:00",
};

const resultResponse = {
  data: [{
    key: "11111111-1-20260828",
    date: "20260828000000",
    user: usersResponse.data[0],
    worked_hours: "08:15",
    non_worked_hours: "00:00",
    absent: false,
    holiday: false,
    worked: true,
    punches: [
      { type: "Ingreso", date: "20260828080000", origin: "Reloj control" },
      { type: "Salida", date: "20260828161500", origin: "Reloj control" },
    ],
    shifts: [{ name: "Administrativo", start_time: "08:00", exit_time: "16:15", delay: "00:00" }],
    time_offs: [],
    schedule: { start_at: "2026-08-28T08:00:00-04:00", end_at: "2026-08-28T16:15:00-04:00" },
    attendance_variance: {
      entry_at: "2026-08-28T07:53:00-04:00",
      exit_at: "2026-08-28T16:20:00-04:00",
      entry_delta_minutes: -7,
      exit_delta_minutes: 5,
      entry_label: "7 min antes",
      exit_label: "5 min después",
    },
  }],
  people: [],
  weekly: [{
    key: "101-2026-08-24",
    week_start: "2026-08-24",
    week_end: "2026-08-30",
    user: usersResponse.data[0],
    days: 1,
    worked_days: 1,
    absences: 0,
    days_with_entry: 1,
    days_with_exit: 1,
    worked_minutes: 495,
    worked_time_label: "8 h 15 min",
    entry_early_minutes: 7,
    entry_late_minutes: 0,
    exit_early_minutes: 0,
    exit_after_minutes: 5,
  }],
  summary: { returned_users: 1, days: 1, punches: 2, absences: 0, worked_time_label: "8 h 15 min", entry_early_minutes: 7, entry_late_minutes: 0, exit_early_minutes: 0, exit_after_minutes: 5 },
  period: { date_from: "2026-08-28", date_to: "2026-08-28" },
  queried_at: "2026-08-30T10:01:00-04:00",
};

const reportsResponse = {
  summary: {
    requested_users: 2,
    returned_users: 2,
    days: 4,
    scheduled_days: 4,
    worked_days: 3,
    worked_time_label: "24 h",
    tardy_people: 1,
    tardiness_occurrences: 1,
    tardiness_minutes: 8,
    entry_early_minutes: 7,
    exit_early_minutes: 10,
    exit_after_minutes: 12,
    absent_people: 1,
    absences: 1,
    justified_absences: 0,
    absences_without_justification: 1,
    missing_entry: 0,
    missing_exit: 0,
  },
  reports: {
    by_group: [{ name: "Administración", people: 2, worked_time_label: "24 h", tardiness_occurrences: 1, tardiness_minutes: 8, absences: 1 }],
    tardiness: [{ user: usersResponse.data[0], occurrences: 1, minutes: 8, average_minutes: 8, maximum_minutes: 8, details: [{ date: "20260828000000", minutes: 8 }] }],
    balances: [{ user: usersResponse.data[0], days: 3, worked_days: 2, worked_time_label: "16 h", entry_early_occurrences: 1, entry_early_minutes: 7, entry_late_occurrences: 1, entry_late_minutes: 8, exit_early_occurrences: 1, exit_early_minutes: 10, exit_after_occurrences: 1, exit_after_minutes: 12, net_minutes: 1, net_label: "+1 min" }],
    absences: [{ user: usersResponse.data[0], occurrences: 1, justified: 0, without_justification: 1, non_worked_minutes: 480, details: [{ date: "20260827000000", justified: false, justifications: [] }] }],
  },
  coverage: { requested_users: 2, returned_users: 2, scheduled_days: 4, days_with_entry: 3, days_with_exit: 3, batches: 1 },
  filters: { scope: "all_linked", tolerance_minutes: 5 },
  period: { date_from: "2026-08-27", date_to: "2026-08-28" },
  queried_at: "2026-08-30T22:40:00-04:00",
};

const mountView = () => mount(RelojControl, {
  global: {
    stubs: {
      Layout: { template: "<div><slot /></div>" },
      LoadingState: { template: "<div class='loading-state' />" },
      BAlert: { props: ["modelValue"], template: "<div v-if='modelValue' class='alert-stub'><slot /></div>" },
      BPagination: { props: ["modelValue"], template: "<nav class='pagination-stub' />" },
      BModal: {
        props: ["modelValue"],
        template: "<div v-if='modelValue' class='modal-stub'><div><slot name='header' /></div><slot /></div>",
      },
    },
  },
});

describe("Reloj Control GeoVictoria", () => {
  beforeEach(() => {
    axios.get.mockReset();
    axios.post.mockReset();
    axios.get.mockResolvedValue({ data: usersResponse });
    axios.post.mockResolvedValue({ data: resultResponse });
    downloadRelojControlPdf.mockClear();
    downloadRelojControlAnalyticsPdf.mockClear();
  });

  it("loads the protected GeoVictoria catalog and presents the read-only workspace", async () => {
    const wrapper = mountView();
    await flushPromises();

    expect(axios.get).toHaveBeenCalledWith("/api/superadmin/reloj-control/users", { params: {} });
    expect(wrapper.text()).toContain("Reloj Control");
    expect(wrapper.text()).toContain("Módulo exclusivo de Superadmin");
    expect(wrapper.text()).toContain("Fuente GeoVictoria");
    expect(wrapper.text()).toContain("Seleccionar funcionarios");
    expect(wrapper.text()).toContain("Los cálculos se generan al consultar");
    await wrapper.find(".clock-selector-trigger").trigger("click");
    expect(wrapper.text()).toContain("Ana Institucional");
    expect(wrapper.text()).toContain("Bruno Institucional");
    expect(wrapper.text()).toContain("Carla Local");
    expect(wrapper.text()).toContain("Sin vincular");
    expect(wrapper.findAll(".clock-person")).toHaveLength(3);
    expect(wrapper.find(".clock-person--unlinked").attributes("disabled")).toBeDefined();
  });

  it("queries the selected users and renders attendance metrics and rows", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find(".clock-selector-trigger").trigger("click");
    await wrapper.find(".clock-person").trigger("click");
    await wrapper.setData({ query: { date_from: "2026-08-28", date_to: "2026-08-28" } });

    await wrapper.vm.runQuery();
    await flushPromises();

    expect(axios.post).toHaveBeenCalledWith("/api/superadmin/reloj-control/attendance", {
      date_from: "2026-08-28",
      date_to: "2026-08-28",
      staff_ids: [101],
    });
    expect(wrapper.text()).toContain("8 h 15 min");
    expect(wrapper.text()).toContain("Administrativo");
    expect(wrapper.text()).toContain("Jornada registrada");
    expect(wrapper.text()).toContain("7 min antes");
    expect(wrapper.text()).toContain("5 min después");
    expect(wrapper.text()).toContain("Consolidado semanal");
    expect(wrapper.text()).toContain("Balance por funcionario");

    await wrapper.find(".clock-pdf-button").trigger("click");
    await flushPromises();
    expect(downloadRelojControlPdf).toHaveBeenCalledWith(resultResponse);
  });

  it("opens a protected detail without edit or delete actions", async () => {
    const wrapper = mountView();
    await flushPromises();
    await wrapper.find(".clock-selector-trigger").trigger("click");
    await wrapper.setData({ result: resultResponse });
    await wrapper.find(".clock-detail-button").trigger("click");

    expect(wrapper.find(".modal-stub").exists()).toBe(true);
    expect(wrapper.find(".modal-stub").text()).toContain("Consulta protegida y de sólo lectura");
    expect(wrapper.find(".modal-stub").text()).toContain("Marcaciones");
    expect(wrapper.find(".modal-stub").text()).not.toContain("Editar");
    expect(wrapper.find(".modal-stub").text()).not.toContain("Eliminar");
  });

  it("generates general, tardiness, balance and absence reports for all linked staff", async () => {
    axios.post.mockResolvedValueOnce({ data: reportsResponse });
    const wrapper = mountView();
    await flushPromises();
    await wrapper.findAll(".clock-mode-tabs button")[1].trigger("click");
    await wrapper.setData({
      reportQuery: {
        date_from: "2026-08-27",
        date_to: "2026-08-28",
        scope: "all_linked",
        tolerance_minutes: 5,
      },
    });

    await wrapper.vm.runReports();
    await flushPromises();

    expect(axios.post).toHaveBeenCalledWith("/api/superadmin/reloj-control/reports", {
      date_from: "2026-08-27",
      date_to: "2026-08-28",
      scope: "all_linked",
      tolerance_minutes: 5,
    });
    expect(wrapper.text()).toContain("Reportes del período");
    expect(wrapper.text()).toContain("Personas con atrasos");
    expect(wrapper.text()).toContain("Personas ausentes");
    expect(wrapper.text()).toContain("Administración");

    await wrapper.findAll(".clock-report-tabs button")[3].trigger("click");
    expect(wrapper.text()).toContain("Sin justificación informada");
    expect(wrapper.text()).toContain("8 h 0 min");

    await wrapper.find(".clock-report-result-head > button").trigger("click");
    await flushPromises();
    expect(downloadRelojControlAnalyticsPdf).toHaveBeenCalledWith(reportsResponse);
  });

  it("keeps the institutional catalog visible and explains rejected credentials", async () => {
    axios.get.mockResolvedValue({
      data: {
        ...usersResponse,
        data: usersResponse.data.map((user) => ({ ...user, geovictoria_linked: false })),
        summary: {
          ...usersResponse.summary,
          linked: 0,
          unlinked: 3,
          provider_total: 0,
          provider_available: false,
          provider_status: 401,
        },
      },
    });

    const wrapper = mountView();
    await flushPromises();
    await wrapper.find(".clock-selector-trigger").trigger("click");

    expect(wrapper.text()).toContain("GeoVictoria rechazó las credenciales");
    expect(wrapper.findAll(".clock-person")).toHaveLength(3);
    expect(wrapper.findAll(".clock-person:disabled")).toHaveLength(3);
  });
});
